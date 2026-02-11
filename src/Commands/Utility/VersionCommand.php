<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Utility;

use MonoPhp\Cli\Commands\BaseCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
 * 1. Mono CLI tool itself
 * 2. PHP runtime and binary path
 * 3. Composer package manager
 * 4. Turborepo task orchestrator
 * 5. Node.js JavaScript runtime
 * 6. pnpm workspace package manager
 *
 * Version discovery process:
 * - Mono CLI: Hardcoded version constant
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
 * ./cli/bin/mono version
 *
 * # Using short alias
 * ./cli/bin/mono ver
 *
 * # Using single letter alias
 * ./cli/bin/mono v
 *
 * # Standard --version flag
 * ./cli/bin/mono --version
 * ```
 *
 * Example output:
 * ```
 * Mono CLI:
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
    name: 'version',
    description: 'Show version information',
    aliases: ['ver', 'v'],
)]
final class VersionCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Common options (workspace, force, no-cache, no-interaction) are inherited from BaseCommand.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();
    }

    /**
     * Execute the version command.
     *
     * This method performs the following steps:
     * 1. Displays intro banner
     * 2. Shows Mono CLI version
     * 3. Shows PHP version and binary path
     * 4. Shows Composer version (if installed)
     * 5. Shows Turbo version (if installed)
     * 6. Shows Node.js version (if installed)
     * 7. Shows pnpm version (if installed)
     * 8. Displays completion message
     *
     * Each tool's version is retrieved independently. If a tool is not
     * installed, "Not installed" is displayed instead of failing.
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (always 0 for success)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Display intro banner
        $this->intro('Version Information');
        $this->line('');

        // Display Mono CLI version
        // This is the version of the CLI tool itself
        $this->info('Mono CLI:');
        $this->line('  Version: 1.0.0');
        $this->line('');

        // Display PHP version and binary path
        // Shows runtime version and location for debugging
        $this->info('PHP:');
        $this->line('  Version: ' . PHP_VERSION);
        $this->line('  Binary: ' . PHP_BINARY);
        $this->line('');

        // Display Composer version if installed
        // Uses hasComposer() and getComposerVersion() from BaseCommand
        $this->info('Composer:');

        if ($this->hasComposer()) {
            $version = $this->getComposerVersion();
            $this->line("  Version: {$version}");
        } else {
            $this->line('  Not installed');
        }

        $this->line('');

        // Display Turbo version if installed
        // Turborepo is used for monorepo task orchestration
        $this->info('Turbo:');
        $turboVersion = $this->getToolVersion('turbo --version');

        if ($turboVersion !== null && $turboVersion !== '') {
            $this->line("  Version: {$turboVersion}");
        } else {
            $this->line('  Not installed');
        }

        $this->line('');

        // Display Node.js version if installed
        // Node.js is required for JavaScript tooling
        $this->info('Node.js:');
        $nodeVersion = $this->getToolVersion('node --version');

        if ($nodeVersion !== null && $nodeVersion !== '') {
            $this->line("  Version: {$nodeVersion}");
        } else {
            $this->line('  Not installed');
        }

        $this->line('');

        // Display pnpm version if installed
        // pnpm is the workspace package manager
        $this->info('pnpm:');
        $pnpmVersion = $this->getToolVersion('pnpm --version');

        if ($pnpmVersion !== null && $pnpmVersion !== '') {
            $this->line("  Version: {$pnpmVersion}");
        } else {
            $this->line('  Not installed');
        }

        $this->line('');

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
     * @param  string      $command Shell command to execute (e.g., 'node --version')
     * @return string|null Version string if successful, null if command fails
     */
    private function getToolVersion(string $command): ?string
    {
        $process = Process::fromShellCommandline($command);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput());
    }
}
