<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Utility;

use function implode;
use function json_decode;
use function json_encode;

use Override;
use PhpHive\Cli\Console\Commands\BaseCommand;

use function strtolower;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function trim;

/**
 * Version Command.
 *
 * This command displays comprehensive version information for the CLI tool
 * and all related development tools in the monorepo stack. It provides a
 * quick overview of the entire toolchain to help with debugging, support
 * requests, and ensuring environment consistency across team members.
 *
 * The command reports versions for:
 * 1. PhpHive CLI tool itself
 * 2. PHP runtime and binary path
 * 3. Composer package manager
 * 4. Turborepo task orchestrator
 * 5. Node.js JavaScript runtime
 * 6. pnpm workspace package manager
 *
 * Version discovery process:
 * - PhpHive CLI: Hardcoded version constant
 * - PHP: Built-in PHP_VERSION and PHP_BINARY constants
 * - Composer: Extracted via getComposerVersion() method
 * - Turbo: Executed via shell command 'turbo --version'
 * - Node.js: Executed via shell command 'node --version'
 * - pnpm: Executed via shell command 'pnpm --version'
 *
 * Features:
 * - Comprehensive toolchain version display
 * - Organized sections for each tool
 * - Graceful handling of missing tools
 * - PHP binary path for debugging
 * - Clean, readable output format
 * - Multiple command aliases
 * - No external dependencies required
 *
 * Use cases:
 * - Verify tool versions before starting work
 * - Include in bug reports and support requests
 * - Ensure team members have compatible versions
 * - Validate environment after setup
 * - Quick reference for installed versions
 * - CI/CD environment validation
 *
 * Example usage:
 * ```bash
 * # Display all version information
 * hive version
 *
 * # Using short alias
 * hive ver
 *
 * # Using single letter alias
 * hive v
 *
 * # Standard --version flag
 * hive --version
 * ```
 *
 * Example output:
 * ```
 * PhpHive CLI:
 *   Version: 1.0.0
 *
 * PHP:
 *   Version: 8.2.0
 *   Binary: /usr/bin/php
 *
 * Composer:
 *   Version: 2.6.5
 *
 * Turbo:
 *   Version: 1.10.16
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see DoctorCommand For comprehensive health checks
 */
#[AsCommand(
    name: 'system:version',
    description: 'Show version information',
    aliases: ['version', 'ver', 'v'],
)]
final class VersionCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Common options (workspace, force, no-cache, no-interaction) are inherited from BaseCommand.
     *
     * Options defined:
     * - --json (-j): Output version information as JSON for CI/CD integration
     * - --short (-s): Compact one-line output format (e.g., "CLI:1.0.0 PHP:8.2.0 ...")
     * - --tool: Show version for a specific tool only (cli, php, composer, turbo, node, pnpm)
     *
     * The --tool option is useful for:
     * - Scripting and automation (get specific version)
     * - Quick version checks
     * - CI/CD pipeline validation
     *
     * Output format examples:
     * - Default: Multi-section formatted output with headers
     * - JSON: Machine-readable structured data
     * - Short: Single line with all versions (CLI:1.0.0 PHP:8.2.0 ...)
     * - Tool: Just the version string for the specified tool
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        // JSON output option (for CI/CD integration)
        $this->addOption(
            'json',
            'j',
            InputOption::VALUE_NONE,
            'Output as JSON'
        );

        // Short output option (compact one-line format)
        $this->addOption(
            'short',
            's',
            InputOption::VALUE_NONE,
            'Compact one-line output'
        );

        // Tool-specific option (show version for single tool)
        $this->addOption(
            'tool',
            null,
            InputOption::VALUE_REQUIRED,
            'Show version for specific tool (cli, php, composer, turbo, node, pnpm)'
        );
    }

    /**
     * Execute the version command.
     *
     * This method performs the following steps:
     * 1. Gathers version information for all tools
     * 2. Handles --tool option for selective display
     * 3. Handles --json flag for machine-readable output
     * 4. Handles --short flag for compact output
     * 5. Displays default formatted output if no flags
     *
     * Each tool's version is retrieved independently. If a tool is not
     * installed, "Not installed" is displayed instead of failing.
     *
     * Execution flow:
     * - gatherVersions(): Collects version info for all tools
     * - Option handling: Checks for --tool, --json, --short flags
     * - Display routing: Calls appropriate display method based on flags
     *
     * Version gathering process:
     * - CLI: Read from composer.json in package directory
     * - PHP: Use built-in PHP_VERSION and PHP_BINARY constants
     * - Composer: Use getComposerVersion() from BaseCommand
     * - Turbo/Node/pnpm: Execute shell commands and parse output
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (always 0 for success, 1 if tool not found with --tool option)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // =====================================================================
        // GATHER VERSION INFORMATION
        // =====================================================================

        // Collect version information for all tools
        // Returns array with keys: cli, php, php_binary, composer, turbo, node, pnpm
        $versions = $this->gatherVersions();

        // =====================================================================
        // HANDLE SELECTIVE TOOL DISPLAY
        // =====================================================================

        // Handle --tool option for selective display
        // If specified, only show version for that specific tool
        $toolOption = $this->option('tool');
        if ($toolOption !== null && $toolOption !== '') {
            return $this->displaySingleTool($toolOption, $versions);
        }

        // =====================================================================
        // HANDLE OUTPUT FORMAT OPTIONS
        // =====================================================================

        // Handle --json flag (machine-readable output)
        if ($this->hasOption('json')) {
            return $this->displayJson($versions);
        }

        // Handle --short flag (compact one-line output)
        if ($this->hasOption('short')) {
            return $this->displayShort($versions);
        }

        // =====================================================================
        // DEFAULT FORMATTED OUTPUT
        // =====================================================================

        // Default: display full formatted output with sections
        return $this->displayDefault($versions);
    }

    /**
     * Gather version information for all tools.
     *
     * Collects version information for the entire development toolchain:
     * - PhpHive CLI: From composer.json in package directory
     * - PHP: Runtime version and binary path
     * - Composer: Package manager version
     * - Turbo: Task orchestrator version
     * - Node.js: JavaScript runtime version
     * - pnpm: Workspace package manager version
     *
     * Each tool is checked independently. If a tool is not installed,
     * null is returned for that tool's version.
     *
     * @return array<string, string|null> Associative array of tool versions
     */
    private function gatherVersions(): array
    {
        return [
            'cli' => $this->getCliVersion(),                                      // PhpHive CLI version
            'php' => PHP_VERSION,                                                 // PHP runtime version
            'php_binary' => PHP_BINARY,                                           // PHP binary path
            'composer' => $this->hasComposer() ? $this->getComposerVersion() : null, // Composer version (if installed)
            'turbo' => $this->getToolVersion('turbo --version'),                  // Turbo version (if installed)
            'node' => $this->getToolVersion('node --version'),                    // Node.js version (if installed)
            'pnpm' => $this->getToolVersion('pnpm --version'),                    // pnpm version (if installed)
        ];
    }

    /**
     * Get the CLI version from composer.json.
     *
     * Attempts to read the version from the CLI package's composer.json file.
     * Tries multiple possible paths to locate the file.
     *
     * Version resolution strategy:
     * 1. Look for 'version' field in composer.json (explicit version)
     * 2. If no version field but package name matches, return 'dev-main' (development version)
     * 3. If composer.json not found, return 'unknown'
     *
     * Possible paths checked (in order):
     * - __DIR__ . '/../../../../composer.json' (from Commands/Utility directory)
     * - __DIR__ . '/../../../composer.json' (alternative path)
     * - dirname(__DIR__, 4) . '/composer.json' (using dirname)
     *
     * @return string CLI version or 'unknown' if not found
     */
    private function getCliVersion(): string
    {
        // Try to find composer.json in the CLI package directory
        // Multiple paths are checked to handle different installation scenarios
        $possiblePaths = [
            __DIR__ . '/../../../../composer.json', // From Commands/Utility directory
            __DIR__ . '/../../../composer.json',    // Alternative path
            dirname(__DIR__, 4) . '/composer.json', // Using dirname
        ];

        // Check each possible path
        foreach ($possiblePaths as $possiblePath) {
            if ($this->filesystem()->exists($possiblePath)) {
                // Read and parse composer.json
                $content = $this->filesystem()->read($possiblePath);
                $data = json_decode($content, true);

                // Check for explicit version field
                if (isset($data['version'])) {
                    return $data['version'];
                }

                // If no version in composer.json, check for dev version
                // Development installations typically don't have a version field
                if (isset($data['name']) && $data['name'] === 'phphive/cli') {
                    return 'dev-main';
                }
            }
        }

        // Composer.json not found or doesn't contain version info
        return 'unknown';
    }

    /**
     * Display version for a single tool.
     *
     * Shows version information for a specific tool only.
     * Used when --tool option is specified.
     *
     * Validation:
     * - Checks if tool name is valid (cli, php, composer, turbo, node, pnpm)
     * - Returns FAILURE if tool is unknown
     * - Returns FAILURE if tool is not installed (version is null)
     * - Returns SUCCESS if version is found
     *
     * Output:
     * - Just the version string (no formatting)
     * - "Not installed" message if tool not found
     * - Error message if tool name is invalid
     *
     * @param  string|mixed               $tool     Tool name (cli, php, composer, turbo, node, pnpm)
     * @param  array<string, string|null> $versions Version data from gatherVersions()
     * @return int                        Exit code (SUCCESS if found, FAILURE if not)
     */
    private function displaySingleTool(mixed $tool, array $versions): int
    {
        // Ensure tool is a string
        if (! is_string($tool)) {
            $this->error('Tool name must be a string');

            return Command::FAILURE;
        }

        // Normalize tool name to lowercase for case-insensitive matching
        $tool = strtolower($tool);

        // Validate tool name
        if (! isset($versions[$tool])) {
            $this->error("Unknown tool: {$tool}");
            $this->line('Available tools: cli, php, composer, turbo, node, pnpm');

            return Command::FAILURE;
        }

        // Get version for the specified tool
        $version = $versions[$tool];

        // Check if tool is installed (check for empty string or null)
        if ($version === '' || ! is_string($version)) {
            $this->line('Not installed');

            return Command::FAILURE;
        }

        // Display version string
        $this->line($version);

        return Command::SUCCESS;
    }

    /**
     * Display versions in JSON format.
     *
     * Outputs version information as structured JSON for CI/CD integration
     * and automated processing.
     *
     * JSON structure:
     * ```json
     * {
     *   "cli": "1.0.0",
     *   "php": {
     *     "version": "8.2.0",
     *     "binary": "/usr/bin/php"
     *   },
     *   "tools": {
     *     "composer": "2.6.5",
     *     "turbo": "1.10.16",
     *     "node": "v18.17.0",
     *     "pnpm": "8.6.12"
     *   }
     * }
     * ```
     *
     * Features:
     * - Pretty-printed JSON (JSON_PRETTY_PRINT)
     * - Unescaped slashes (JSON_UNESCAPED_SLASHES)
     * - Null values for tools not installed
     * - Structured PHP section with version and binary path
     *
     * @param  array<string, string|null> $versions Version data from gatherVersions()
     * @return int                        Exit code (always SUCCESS)
     */
    private function displayJson(array $versions): int
    {
        // Build structured JSON output
        $output = [
            'cli' => $versions['cli'],
            'php' => [
                'version' => $versions['php'],
                'binary' => $versions['php_binary'],
            ],
            'tools' => [
                'composer' => $versions['composer'],
                'turbo' => $versions['turbo'],
                'node' => $versions['node'],
                'pnpm' => $versions['pnpm'],
            ],
        ];

        // Output as pretty-printed JSON
        $jsonOutput = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($jsonOutput !== false) {
            $this->line($jsonOutput);
        }

        return Command::SUCCESS;
    }

    /**
     * Display versions in short one-line format.
     *
     * Outputs all version information in a compact single-line format.
     * Useful for quick checks and scripting.
     *
     * Output format:
     * CLI:1.0.0 PHP:8.2.0 Composer:2.6.5 Turbo:1.10.16 Node:v18.17.0 pnpm:8.6.12
     *
     * Features:
     * - Space-separated key:value pairs
     * - Only includes installed tools (skips null values)
     * - Always includes CLI and PHP (required tools)
     * - Optional tools only shown if installed
     *
     * Use cases:
     * - Quick version checks
     * - Logging and monitoring
     * - CI/CD pipeline output
     * - Terminal status bars
     *
     * @param  array<string, string|null> $versions Version data from gatherVersions()
     * @return int                        Exit code (always SUCCESS)
     */
    private function displayShort(array $versions): int
    {
        $parts = [];

        // Always include CLI and PHP (required tools)
        $parts[] = "CLI:{$versions['cli']}";
        $parts[] = "PHP:{$versions['php']}";

        // Include optional tools only if installed
        if ($versions['composer'] !== null) {
            $parts[] = "Composer:{$versions['composer']}";
        }
        if ($versions['turbo'] !== null) {
            $parts[] = "Turbo:{$versions['turbo']}";
        }
        if ($versions['node'] !== null) {
            $parts[] = "Node:{$versions['node']}";
        }
        if ($versions['pnpm'] !== null) {
            $parts[] = "pnpm:{$versions['pnpm']}";
        }

        // Output as single line with space-separated parts
        $this->line(implode(' ', $parts));

        return Command::SUCCESS;
    }

    /**
     * Display versions in default formatted output.
     *
     * Shows version information in a user-friendly multi-section format
     * with headers and indentation for easy reading.
     *
     * Output structure:
     * - Intro banner
     * - PhpHive CLI section (version)
     * - PHP section (version and binary path)
     * - Composer section (version or "Not installed")
     * - Turbo section (version or "Not installed")
     * - Node.js section (version or "Not installed")
     * - pnpm section (version or "Not installed")
     * - Completion message
     *
     * Features:
     * - Colored output with info() for section headers
     * - Indented values for readability
     * - "Not installed" message for missing tools
     * - Empty lines between sections for visual separation
     * - Success message at the end
     *
     * This is the default output format when no flags are specified.
     *
     * @param  array<string, string|null> $versions Version data from gatherVersions()
     * @return int                        Exit code (always SUCCESS)
     */
    private function displayDefault(array $versions): int
    {
        // Display intro banner
        $this->intro('Version Information');
        $this->line('');

        // =====================================================================
        // PHPHIVE CLI SECTION
        // =====================================================================

        // Display PhpHive CLI version
        $this->info('PhpHive CLI:');
        $this->line('  Version: ' . $versions['cli']);
        $this->line('');

        // =====================================================================
        // PHP SECTION
        // =====================================================================

        // Display PHP version and binary path
        // Binary path is useful for debugging and ensuring correct PHP is used
        $this->info('PHP:');
        $this->line('  Version: ' . $versions['php']);
        $this->line('  Binary: ' . $versions['php_binary']);
        $this->line('');

        // =====================================================================
        // COMPOSER SECTION
        // =====================================================================

        // Display Composer version if installed
        // Composer is required for PHP dependency management
        $this->info('Composer:');
        if ($versions['composer'] !== null) {
            $this->line('  Version: ' . $versions['composer']);
        } else {
            $this->line('  Not installed');
        }
        $this->line('');

        // =====================================================================
        // TURBO SECTION
        // =====================================================================

        // Display Turbo version if installed
        // Turbo is the build system for monorepo task orchestration
        $this->info('Turbo:');
        $turboVersion = $versions['turbo'];
        if (is_string($turboVersion) && $turboVersion !== '') {
            $this->line('  Version: ' . $turboVersion);
        } else {
            $this->line('  Not installed');
        }
        $this->line('');

        // =====================================================================
        // NODE.JS SECTION
        // =====================================================================

        // Display Node.js version if installed
        // Node.js is required for JavaScript/TypeScript tooling
        $this->info('Node.js:');
        $nodeVersion = $versions['node'];
        if (is_string($nodeVersion) && $nodeVersion !== '') {
            $this->line('  Version: ' . $nodeVersion);
        } else {
            $this->line('  Not installed');
        }
        $this->line('');

        // =====================================================================
        // PNPM SECTION
        // =====================================================================

        // Display pnpm version if installed
        // pnpm is the package manager for JavaScript dependencies
        $this->info('pnpm:');
        $pnpmVersion = $versions['pnpm'];
        if (is_string($pnpmVersion) && $pnpmVersion !== '') {
            $this->line('  Version: ' . $pnpmVersion);
        } else {
            $this->line('  Not installed');
        }
        $this->line('');

        // =====================================================================
        // COMPLETION MESSAGE
        // =====================================================================

        // Display completion message
        $this->outro('✓ Version information displayed');

        return Command::SUCCESS;
    }

    /**
     * Get version of a tool by running a command.
     *
     * Executes a shell command to retrieve version information for
     * external tools. Returns null if the command fails (tool not installed).
     *
     * Process execution:
     * 1. Creates a Process from the shell command
     * 2. Runs the process synchronously
     * 3. Checks if execution was successful
     * 4. Returns trimmed output or null
     *
     * Error handling:
     * - If command fails (tool not found), returns null
     * - If command succeeds, returns trimmed output
     * - No exceptions thrown (graceful failure)
     *
     * Common commands:
     * - 'turbo --version': Get Turborepo version
     * - 'node --version': Get Node.js version
     * - 'pnpm --version': Get pnpm version
     *
     * @param  string      $command Shell command to execute (e.g., 'node --version')
     * @return string|null Version string if successful, null if command fails
     */
    private function getToolVersion(string $command): ?string
    {
        // Create process from shell command
        $process = Process::fromShellCommandline($command);

        // Execute the command synchronously
        $process->run();

        // Check if command executed successfully
        if (! $process->isSuccessful()) {
            // Tool not installed or command failed
            return null;
        }

        // Return trimmed output (version string)
        return trim($process->getOutput());
    }
}
