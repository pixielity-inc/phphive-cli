<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Lifecycle;

use function count;

use MonoPhp\Cli\Commands\BaseCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Install Command.
 *
 * This command installs all dependencies (Composer packages) across the monorepo
 * using Turborepo for parallel execution and intelligent caching. It can target
 * all workspaces or a specific workspace, with options to force reinstall or
 * disable caching for troubleshooting.
 *
 * The installation process:
 * 1. Discovers all workspaces in the monorepo
 * 2. Runs `composer install` in each workspace via Turbo
 * 3. Leverages Turbo's caching to skip unchanged workspaces
 * 4. Executes installations in parallel for maximum speed
 * 5. Reports success or failure with clear feedback
 *
 * Installation workflow:
 * - Identifies all workspaces with composer.json files
 * - Validates composer.json and composer.lock files
 * - Executes composer install in parallel via Turbo
 * - Caches installation results for future runs
 * - Skips unchanged workspaces automatically
 * - Handles dependency resolution across workspaces
 *
 * Features:
 * - Parallel execution across workspaces
 * - Intelligent caching (skip if nothing changed)
 * - Workspace filtering (install specific workspace)
 * - Force reinstall option (ignore cache)
 * - Disable cache option (for troubleshooting)
 * - Progress tracking and error reporting
 * - Automatic dependency resolution
 * - Lock file validation
 *
 * Common options inherited from BaseCommand:
 * - --workspace, -w: Target specific workspace
 * - --force, -f: Force operation by ignoring cache
 * - --no-cache: Disable Turbo cache
 * - --no-interaction, -n: Run in non-interactive mode
 *
 * Example usage:
 * ```bash
 * # Install all workspaces
 * ./cli/bin/mono install
 *
 * # Install with alias
 * ./cli/bin/mono i
 *
 * # Install specific workspace
 * ./cli/bin/mono install --workspace demo-app
 *
 * # Install with shorthand
 * ./cli/bin/mono i -w calculator
 *
 * # Force reinstall (ignore cache)
 * ./cli/bin/mono install --force
 *
 * # Force reinstall specific workspace
 * ./cli/bin/mono install -w demo-app -f
 *
 * # Disable Turbo cache completely
 * ./cli/bin/mono install --no-cache
 * ```
 *
 * @see BaseCommand For inherited functionality and common options
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see CleanupCommand For removing dependencies
 */
#[AsCommand(
    name: 'install',
    description: 'Install all dependencies',
    aliases: ['i'],
)]
final class InstallCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Inherits common options from BaseCommand (workspace, force, no-cache, no-interaction).
     * No additional command-specific options needed for this command.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();
    }

    /**
     * Execute the install command.
     *
     * This method orchestrates the entire installation process:
     * 1. Displays an intro banner
     * 2. Extracts user options (workspace, force, no-cache)
     * 3. Determines which workspaces to install
     * 4. Builds Turbo options array based on user input
     * 5. Executes the composer:install task via Turbo
     * 6. Reports success or failure with clear feedback
     *
     * The composer:install task executes `composer install` in each workspace,
     * installing dependencies based on composer.lock files. Turbo handles
     * parallel execution and caching automatically.
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Display intro banner
        $this->intro('Installing Dependencies');

        // Extract options from user input
        $workspace = $this->option('workspace');
        $force = $this->hasOption('force');
        $noCache = $this->hasOption('no-cache');

        // Show what we're installing
        if ($workspace !== null && $workspace !== '') {
            // Installing specific workspace
            $this->info("Installing dependencies for workspace: {$workspace}");
        } else {
            // Installing all workspaces
            $workspaces = $this->getWorkspaces();
            $this->info('Installing dependencies for ' . count($workspaces) . ' workspace(s)');
        }

        // Build Turbo options array
        // These options control how Turbo executes the task
        $options = [];

        // Filter to specific workspace if requested
        if ($workspace !== null && $workspace !== '') {
            $options['filter'] = $workspace;
        }

        // Force re-execution by ignoring cache
        if ($force) {
            $options['force'] = true;
        }

        // Disable cache completely
        if ($noCache) {
            $options['cache'] = false;
        }

        // Run the composer:install task via Turbo
        // Turbo will handle parallel execution and caching
        $this->info('Running Composer install via Turbo...');
        $exitCode = $this->turboRun('composer:install', $options);

        // Report results to user
        if ($exitCode === 0) {
            // Success - all installations completed
            $this->outro('✓ Dependencies installed successfully!');
        } else {
            // Failure - one or more installations failed
            $this->error('✗ Installation failed');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
