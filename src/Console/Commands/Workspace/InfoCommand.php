<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Workspace;

use function count;

use Illuminate\Support\Str;
use Override;
use PhpHive\Cli\Console\Commands\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Info Command.
 *
 * This command displays comprehensive information about a specific workspace
 * in the monorepo. It provides a detailed view of workspace configuration,
 * dependencies, and available scripts from both Composer and npm/pnpm ecosystems.
 *
 * The command gathers information from multiple sources:
 * 1. Workspace metadata (name, type, path)
 * 2. composer.json (PHP dependencies, autoload, scripts)
 * 3. package.json (npm dependencies, scripts, version)
 * 4. File system (directory structure, file presence)
 *
 * Information displayed:
 * - Basic metadata (name, type, package name, path)
 * - Composer availability and configuration
 * - Package.json availability and configuration
 * - Production dependencies (require/dependencies)
 * - Development dependencies (require-dev/devDependencies)
 * - Available scripts (composer scripts, npm scripts)
 * - Package description and license information
 *
 * Features:
 * - Interactive workspace selection if none specified
 * - Organized sections for different config sources
 * - Dependency version display
 * - Script listing for quick reference
 * - Clear visual formatting with colors
 * - Validation of workspace existence
 *
 * Example usage:
 * ```bash
 * # Show info for specific workspace
 * hive info api
 *
 * # Show info for calculator workspace
 * hive info calculator
 *
 * # Interactive selection (no workspace specified)
 * hive info
 *
 * # Using alias
 * hive show demo-app
 * hive details demo-app
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithMonorepo For workspace discovery
 * @see ListCommand For viewing all workspaces
 */
#[AsCommand(
    name: 'workspace:info',
    description: 'Show detailed workspace information',
    aliases: ['info', 'show', 'details'],
)]
final class InfoCommand extends BaseCommand
{
    /**
     * Configure the command options and arguments.
     *
     * Defines the workspace argument and output format options for the info command.
     * This method sets up how users can specify which workspace to inspect and how
     * the information should be displayed.
     *
     * Arguments:
     * - workspace (optional): Name of the workspace to show info for
     *   - If provided: Show info for that specific workspace
     *   - If omitted: Prompt user for interactive selection
     *   - Validation: Must match an existing workspace name
     *
     * Available options:
     * - --json/-j: Output as JSON instead of text format (for scripting/automation)
     * - --format/-f: Output format (text, json, or table)
     *   - text (default): Human-readable formatted text with sections
     *   - json: Machine-readable JSON with full workspace data
     *   - table: Structured tables for dependencies and scripts
     * - --absolute: Show absolute filesystem paths (default is relative to monorepo root)
     *
     * Common options inherited from BaseCommand:
     * - --workspace: Specify workspace context (overridden by argument)
     * - --force: Force operation without confirmation
     * - --no-cache: Bypass cache for workspace discovery
     * - --no-interaction: Run in non-interactive mode (fails if workspace not specified)
     *
     * Interactive vs non-interactive mode:
     * - Interactive: If no workspace specified, prompts user to select from list
     * - Non-interactive (--no-interaction): Fails if workspace not specified
     * - Useful for scripts and CI/CD where prompts would block execution
     *
     * Help text:
     * - Provides usage examples and command explanation
     * - Displayed when user runs: hive info --help
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument(
                'workspace',
                InputArgument::OPTIONAL,
                'Workspace name to show info for',
            )
            ->addOption(
                'json',
                'j',
                InputOption::VALUE_NONE,
                'Output as JSON',
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_REQUIRED,
                'Output format: text (default), json, table',
                'text',
            )
            ->addOption(
                'absolute',
                null,
                InputOption::VALUE_NONE,
                'Show absolute paths (default is relative)',
            )
            ->setHelp(
                <<<'HELP'
                The <info>info</info> command shows detailed workspace information.

                <comment>Examples:</comment>
                  <info>hive info api</info>          Show info for 'api' workspace
                  <info>hive info calculator</info>   Show info for 'calculator' workspace
                  <info>hive info api --json</info>   Output as JSON
                  <info>hive info api --format=table</info>  Output as table

                If no workspace is specified, you'll be prompted to select one.
                HELP
            );
    }

    /**
     * Execute the info command.
     *
     * This method orchestrates the entire workspace information display process,
     * from workspace selection through data gathering and final output. It handles
     * multiple output formats and provides comprehensive workspace details.
     *
     * Execution flow:
     * 1. Workspace Selection:
     *    - If workspace argument provided: Use that workspace
     *    - If no argument: Discover all workspaces and prompt for selection
     *    - Validation: Ensure workspace exists in monorepo
     *    - Interactive mode: Shows selection menu with all workspace names
     *
     * 2. Workspace Retrieval:
     *    - Calls getWorkspace() to fetch full workspace metadata
     *    - Reads package.json for npm/pnpm configuration
     *    - Reads composer.json for PHP configuration (if exists)
     *    - Extracts dependencies, scripts, and metadata
     *
     * 3. Path Conversion:
     *    - Converts absolute paths to relative (unless --absolute specified)
     *    - Makes output more readable and portable
     *    - Example: /home/user/project/apps/api -> apps/api
     *
     * 4. Format Determination:
     *    - --json flag takes precedence over --format option
     *    - Allows shorthand: -j instead of --format=json
     *    - Validates format value (text, json, or table)
     *
     * 5. Output Generation:
     *    - Text format: Organized sections with colored output
     *    - JSON format: Complete workspace data as JSON
     *    - Table format: Structured tables for dependencies and scripts
     *
     * Error handling:
     * - No workspaces found: Display error and exit with FAILURE
     * - Workspace not found: Display error with workspace name and exit with FAILURE
     * - Invalid format: Falls back to text format (default case in match)
     *
     * Why separate output methods:
     * - Single Responsibility Principle: Each method handles one format
     * - Easier to maintain and test
     * - Clear separation of concerns
     * - Allows format-specific optimizations
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (Command::SUCCESS or Command::FAILURE)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get workspace name from argument or prompt for selection
        // This allows both direct specification and interactive selection
        $workspaceName = $input->getArgument('workspace');

        if ($workspaceName === null || $workspaceName === '') {
            // No workspace specified - discover all workspaces
            $workspaces = $this->getWorkspaces();

            // Validate that workspaces exist in the monorepo
            if ($workspaces->isEmpty()) {
                $this->error('No workspaces found');

                return Command::FAILURE;
            }

            // Prompt user to select a workspace interactively
            $workspaceName = $this->select(
                'Select workspace',
                $workspaces->pluck('name')->all(),
            );
        }

        // Retrieve detailed workspace information
        // This includes metadata from package.json and composer.json
        $workspace = $this->getWorkspace($workspaceName);

        // Validate workspace exists
        if ($workspace === null || $workspace === []) {
            $this->error("Workspace '{$workspaceName}' not found");

            return Command::FAILURE;
        }

        // Convert to relative path unless --absolute is specified
        if (! $this->hasOption('absolute')) {
            $workspace = $this->convertToRelativePath($workspace);
        }

        // Determine output format (--json flag takes precedence over --format)
        $format = $this->hasOption('json') ? 'json' : $this->option('format');

        // Output in requested format
        match ($format) {
            'json' => $this->outputJsonFormat($workspace),
            'table' => $this->outputTableFormat($workspace),
            default => $this->outputTextFormat($workspace),
        };

        return Command::SUCCESS;
    }

    /**
     * Convert absolute path to relative path from monorepo root.
     *
     * Transforms the workspace's absolute filesystem path to a relative path
     * for better readability and portability. Relative paths are easier to read
     * and work across different environments.
     *
     * String operations:
     * - Str::startWith(): Check if path begins with root (PHP 8.0+)
     * - Str::substr(): Remove root prefix from path
     * - strlen(): Get length of root path for Str::substr offset
     * - +1 offset: Remove the trailing slash after root
     *
     * Path transformation:
     * - Before: /home/user/project/apps/api
     * - After: apps/api
     *
     * Why modify in place:
     * - Single workspace (not a collection)
     * - Simple transformation
     * - Returns modified array
     *
     * @param  array $workspace Workspace metadata array with 'path' key
     * @return array Workspace with relative path (modified copy)
     */
    private function convertToRelativePath(array $workspace): array
    {
        // Get monorepo root path for comparison
        $root = $this->getMonorepoRoot();

        // Check if path starts with monorepo root and strip it
        if (Str::startsWith($workspace['path'], $root)) {
            // Strip root prefix and leading slash
            // Example: /home/user/project/apps/api -> apps/api
            $workspace['path'] = Str::substr($workspace['path'], Str::length($root) + 1);
        }

        return $workspace;
    }

    /**
     * Output workspace information in JSON format.
     *
     * Generates a comprehensive JSON representation of the workspace including
     * all metadata, composer.json contents, and package.json contents. This format
     * is ideal for scripting, automation, and programmatic consumption.
     *
     * JSON structure:
     * - Basic metadata: name, type, packageName, path
     * - Boolean flags: hasComposer, hasPackageJson
     * - composer: Full composer.json contents (if exists)
     * - package: Full package.json contents (if exists)
     *
     * Why include full config files:
     * - Provides complete information for automation
     * - Allows scripts to access any field without command changes
     * - Useful for CI/CD pipelines and tooling
     * - No information loss compared to text format
     *
     * Conditional inclusion:
     * - Only includes 'composer' key if composer.json exists and is not empty
     * - Only includes 'package' key if package.json exists and is not empty
     * - Keeps JSON clean and avoids null/empty values
     *
     * Output method:
     * - Uses outputJson() helper from BaseCommand
     * - Handles JSON encoding with pretty-print
     * - Ensures valid JSON output
     *
     * @param array $workspace Workspace metadata array
     */
    private function outputJsonFormat(array $workspace): void
    {
        // Read configuration files for the workspace
        $composerJson = $this->getWorkspaceComposerJson($workspace['name']);
        $packageJson = $this->getWorkspacePackageJson($workspace['name']);

        // Build base data structure with workspace metadata
        $data = [
            'name' => $workspace['name'],
            'type' => $workspace['type'],
            'packageName' => $workspace['packageName'],
            'path' => $workspace['path'],
            'hasComposer' => $workspace['hasComposer'],
            'hasPackageJson' => $workspace['hasPackageJson'],
        ];

        // Conditionally add composer.json contents if it exists
        if ($composerJson !== null && $composerJson !== []) {
            $data['composer'] = $composerJson;
        }

        // Conditionally add package.json contents if it exists
        if ($packageJson !== null && $packageJson !== []) {
            $data['package'] = $packageJson;
        }

        // Output as formatted JSON
        $this->outputJson($data);
    }

    /**
     * Output workspace information in table format.
     *
     * Displays workspace information using structured tables for easy scanning
     * and comparison. This format is useful for quickly identifying dependencies
     * and available scripts.
     *
     * Tables displayed:
     * 1. Basic Information: Core workspace metadata (name, type, path, etc.)
     * 2. Composer Dependencies: Production dependencies from composer.json
     * 3. Available Scripts: npm/pnpm scripts from package.json
     *
     * Why use tables:
     * - Easy to scan and compare values
     * - Structured format for dependencies and scripts
     * - Clear separation between different data types
     * - Professional appearance
     *
     * Collection usage:
     * - collect(): Wraps associative array for Collection methods
     * - map(): Transforms key-value pairs into table rows
     * - values(): Reindexes array (removes string keys)
     * - all(): Converts Collection back to array
     *
     * Why use Collection for table rows:
     * - Clean transformation from associative array to indexed array
     * - Declarative approach with map()
     * - Consistent with codebase patterns
     * - Easier to read than foreach loops
     *
     * Conditional display:
     * - Only shows Composer Dependencies if require section exists and not empty
     * - Only shows Available Scripts if scripts section exists and not empty
     * - Avoids empty tables that add no value
     *
     * @param array $workspace Workspace metadata array
     */
    private function outputTableFormat(array $workspace): void
    {
        // Read configuration files for the workspace
        $composerJson = $this->getWorkspaceComposerJson($workspace['name']);
        $packageJson = $this->getWorkspacePackageJson($workspace['name']);

        // Display intro banner with workspace name
        $this->intro("Workspace Information: {$workspace['name']}");
        $this->line('');

        // Display basic information table
        // Shows core workspace metadata in a structured format
        $this->table(
            ['Property', 'Value'],
            [
                ['Name', $workspace['name']],
                ['Type', $workspace['type']],
                ['Package Name', $workspace['packageName']],
                ['Path', $workspace['path']],
                ['Has Composer', $workspace['hasComposer'] ? 'Yes' : 'No'],
                ['Has Package.json', $workspace['hasPackageJson'] ? 'Yes' : 'No'],
            ],
        );

        // Display Composer dependencies table if they exist
        // Uses Collection to transform associative array into table rows
        if ($composerJson !== null && isset($composerJson['require']) && count($composerJson['require']) > 0) {
            $this->line('');
            $this->info('Composer Dependencies:');
            $this->table(
                ['Package', 'Version'],
                collect($composerJson['require'])
                    ->map(fn (string $version, string $package): array => [$package, $version])
                    ->values()
                    ->all()
            );
        }

        // Display package.json scripts table if they exist
        // Uses Collection to transform associative array into table rows
        if ($packageJson !== null && isset($packageJson['scripts']) && count($packageJson['scripts']) > 0) {
            $this->line('');
            $this->info('Available Scripts:');
            $this->table(
                ['Script', 'Command'],
                collect($packageJson['scripts'])
                    ->map(fn (string $command, string $script): array => [$script, $command])
                    ->values()
                    ->all()
            );
        }

        $this->line('');
        $this->outro('✓ Information displayed');
    }

    /**
     * Output workspace information in text format (default).
     *
     * Displays workspace information in a human-readable text format with
     * organized sections and colored output. This is the default format and
     * provides the most readable output for terminal viewing.
     *
     * Output sections:
     * 1. Intro banner: Displays workspace name prominently
     * 2. Basic Information: Core metadata (name, type, path, etc.)
     * 3. Composer Configuration: PHP dependencies and metadata (if composer.json exists)
     * 4. Package Configuration: npm/pnpm scripts and metadata (if package.json exists)
     * 5. Outro message: Success confirmation
     *
     * Why separate display methods:
     * - Single Responsibility Principle: Each method handles one section
     * - Easier to maintain and modify individual sections
     * - Clear separation of concerns
     * - Reusable components
     *
     * Conditional sections:
     * - Composer section only shown if hasComposer is true
     * - Package section only shown if hasPackageJson is true
     * - Avoids displaying empty sections
     *
     * Spacing:
     * - Empty lines between sections for readability
     * - Consistent spacing throughout output
     * - Makes terminal output easier to scan
     *
     * @param array $workspace Workspace metadata array
     */
    private function outputTextFormat(array $workspace): void
    {
        // Display intro banner with workspace name
        $this->intro("Workspace Information: {$workspace['name']}");
        $this->line('');

        // Display basic workspace metadata
        // Shows name, type, package name, path, and config file presence
        $this->displayBasicInfo($workspace);
        $this->line('');

        // Display Composer configuration if composer.json exists
        // Shows dependencies, dev dependencies, and package metadata
        if ($workspace['hasComposer'] === true) {
            $this->displayComposerInfo($workspace);
            $this->line('');
        }

        // Display package.json configuration if it exists
        // Shows npm dependencies, scripts, and package metadata
        if ($workspace['hasPackageJson'] === true) {
            $this->displayPackageInfo($workspace);
            $this->line('');
        }

        // Display success message
        $this->outro('✓ Information displayed');
    }

    /**
     * Display basic workspace information.
     *
     * Shows core metadata including name, type, package name, path,
     * and whether Composer and package.json files are present. This provides
     * a quick overview of the workspace's identity and configuration.
     *
     * Information displayed:
     * - Name: Workspace name (e.g., api, calculator, cli)
     * - Type: Workspace type (app or package)
     * - Package Name: Full package name (e.g., @phphive/api)
     * - Path: Filesystem path (relative or absolute based on --absolute flag)
     * - Has Composer: Whether composer.json exists (Yes/No)
     * - Has Package.json: Whether package.json exists (Yes/No)
     *
     * Output format:
     * - Section header: "Basic Information:" in info color
     * - Each field: "  Key: Value" with 2-space indentation
     * - Boolean values: Converted to Yes/No for readability
     *
     * Why this format:
     * - Easy to scan and read
     * - Consistent indentation
     * - Clear key-value pairs
     * - Human-friendly boolean display
     *
     * @param array $workspace Workspace metadata array
     */
    private function displayBasicInfo(array $workspace): void
    {
        $this->info('Basic Information:');
        $this->line("  Name: {$workspace['name']}");
        $this->line("  Type: {$workspace['type']}");
        $this->line("  Package Name: {$workspace['packageName']}");
        $this->line("  Path: {$workspace['path']}");
        $this->line('  Has Composer: ' . ($workspace['hasComposer'] ? 'Yes' : 'No'));
        $this->line('  Has Package.json: ' . ($workspace['hasPackageJson'] ? 'Yes' : 'No'));
    }

    /**
     * Display Composer configuration.
     *
     * Reads composer.json and displays package metadata, production
     * dependencies (require), and development dependencies (require-dev).
     * This provides insight into the PHP ecosystem dependencies.
     *
     * Information displayed:
     * - Description: Package description from composer.json
     * - Type: Composer package type (library, project, etc.)
     * - License: Package license (MIT, Apache-2.0, etc.)
     * - Dependencies: Production dependencies from 'require' section
     * - Dev Dependencies: Development dependencies from 'require-dev' section
     *
     * Output format:
     * - Section header: "Composer Configuration:" in info color
     * - Metadata: "  Key: Value" with 2-space indentation
     * - Dependencies: Listed with "    - package: version" (4-space indentation)
     * - Empty line before dependency lists for readability
     *
     * Conditional display:
     * - Only shows description if it exists in composer.json
     * - Only shows type if it exists in composer.json
     * - Only shows license if it exists in composer.json
     * - Only shows Dependencies section if require exists and not empty
     * - Only shows Dev Dependencies section if require-dev exists and not empty
     *
     * Why check count() > 0:
     * - Avoids displaying empty sections
     * - count() works on arrays (require/require-dev are arrays)
     * - More explicit than checking truthiness
     *
     * Business logic:
     * - Early return if composer.json not found or empty
     * - Prevents errors from accessing undefined array keys
     * - Keeps output clean when no Composer configuration exists
     *
     * @param array $workspace Workspace metadata array
     */
    private function displayComposerInfo(array $workspace): void
    {
        // Read composer.json for the workspace
        $composerJson = $this->getWorkspaceComposerJson($workspace['name']);

        // Early return if composer.json not found or empty
        // Prevents errors from accessing undefined array keys
        if ($composerJson === null || $composerJson === []) {
            return;
        }

        // Display section header
        $this->info('Composer Configuration:');

        // Display package description if it exists
        if (isset($composerJson['description'])) {
            $this->line("  Description: {$composerJson['description']}");
        }

        // Display package type if it exists (library, project, etc.)
        if (isset($composerJson['type'])) {
            $this->line("  Type: {$composerJson['type']}");
        }

        // Display package license if it exists
        if (isset($composerJson['license'])) {
            $this->line("  License: {$composerJson['license']}");
        }

        // Display production dependencies from 'require' section
        // Only show if dependencies exist to avoid empty sections
        if (isset($composerJson['require']) && count($composerJson['require']) > 0) {
            $this->line('');
            $this->comment('  Dependencies:');

            // Iterate through each dependency and display package: version
            foreach ($composerJson['require'] as $package => $version) {
                $this->line("    - {$package}: {$version}");
            }
        }

        // Display development dependencies from 'require-dev' section
        // Only show if dev dependencies exist to avoid empty sections
        if (isset($composerJson['require-dev']) && count($composerJson['require-dev']) > 0) {
            $this->line('');
            $this->comment('  Dev Dependencies:');

            // Iterate through each dev dependency and display package: version
            foreach ($composerJson['require-dev'] as $package => $version) {
                $this->line("    - {$package}: {$version}");
            }
        }
    }

    /**
     * Display package.json configuration.
     *
     * Reads package.json and displays version, description, and available
     * npm/pnpm scripts with their full commands. This provides insight into
     * the JavaScript/TypeScript ecosystem configuration.
     *
     * Information displayed:
     * - Version: Package version (e.g., 1.0.0, 0.1.0)
     * - Description: Package description
     * - Available Scripts: npm/pnpm scripts with their full commands
     *
     * Output format:
     * - Section header: "Package Configuration:" in info color
     * - Metadata: "  Key: Value" with 2-space indentation
     * - Scripts: Listed with "    - script: command" (4-space indentation)
     * - Empty line before scripts list for readability
     *
     * Script display:
     * - Shows script name and full command
     * - Example: "    - build: turbo run build"
     * - Helps users understand what each script does
     * - Useful for discovering available commands
     *
     * Conditional display:
     * - Only shows version if it exists in package.json
     * - Only shows description if it exists in package.json
     * - Only shows Available Scripts section if scripts exists and not empty
     *
     * Why check count() > 0:
     * - Avoids displaying empty sections
     * - count() works on arrays (scripts is an array)
     * - More explicit than checking truthiness
     *
     * Business logic:
     * - Early return if package.json not found or empty
     * - Prevents errors from accessing undefined array keys
     * - Keeps output clean when no package.json exists
     *
     * Common scripts:
     * - build: Build the package
     * - test: Run tests
     * - dev: Start development server
     * - lint: Run linter
     * - format: Format code
     *
     * @param array $workspace Workspace metadata array
     */
    private function displayPackageInfo(array $workspace): void
    {
        // Read package.json for the workspace
        $packageJson = $this->getWorkspacePackageJson($workspace['name']);

        // Early return if package.json not found or empty
        // Prevents errors from accessing undefined array keys
        if ($packageJson === null || $packageJson === []) {
            return;
        }

        // Display section header
        $this->info('Package Configuration:');

        // Display package version if it exists
        if (isset($packageJson['version'])) {
            $this->line("  Version: {$packageJson['version']}");
        }

        // Display package description if it exists
        if (isset($packageJson['description'])) {
            $this->line("  Description: {$packageJson['description']}");
        }

        // Display available npm/pnpm scripts with their full commands
        // Only show if scripts exist to avoid empty sections
        if (isset($packageJson['scripts']) && count($packageJson['scripts']) > 0) {
            $this->line('');
            $this->comment('  Available Scripts:');

            // Iterate through each script and display name: command
            // This helps users discover what commands are available
            foreach ($packageJson['scripts'] as $script => $command) {
                $this->line("    - {$script}: {$command}");
            }
        }
    }
}
