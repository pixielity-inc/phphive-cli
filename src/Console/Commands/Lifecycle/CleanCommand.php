<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Lifecycle;

use Override;
use PhpHive\Cli\Console\Commands\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Clean Command.
 *
 * This command cleans caches and build artifacts across the monorepo to free
 * up disk space and ensure fresh builds. It removes temporary files created
 * during development, testing, and building without touching source code or
 * dependencies. This is a safe operation that preserves vendor and node_modules.
 *
 * The cleaning process:
 * 1. Discovers all workspaces in the monorepo
 * 2. Runs clean scripts in each workspace via Turbo
 * 3. Removes workspace-specific caches (.phpstan.cache, .phpunit.cache)
 * 4. Clears build artifacts and temporary files
 * 5. Executes cleaning in parallel for maximum speed
 *
 * Cleaning workflow:
 * - Identifies all workspaces with clean scripts
 * - Executes clean tasks in parallel via Turbo
 * - Removes PHPStan cache directories
 * - Removes PHPUnit cache directories
 * - Clears build output directories
 * - Removes temporary log files
 * - Preserves source code and dependencies
 *
 * Features:
 * - Parallel execution across workspaces
 * - Workspace filtering (clean specific workspace)
 * - Safe operation (preserves source code and dependencies)
 * - Removes PHPStan cache, PHPUnit cache, build artifacts
 * - Does NOT remove vendor or node_modules (use cleanup for that)
 * - Disables Turbo cache for fresh clean operation
 * - Fast execution with parallel processing
 *
 * What gets cleaned:
 * - .phpstan.cache directories
 * - .phpunit.cache directories
 * - Build output directories
 * - Temporary log files
 * - Turbo cache (optional)
 * - Compiled assets
 *
 * What is preserved:
 * - Source code files
 * - vendor directories (Composer dependencies)
 * - node_modules directories (npm dependencies)
 * - Configuration files
 * - Lock files
 *
 * Common options inherited from BaseCommand:
 * - --workspace, -w: Target specific workspace
 * - --force, -f: Force operation by ignoring cache
 * - --no-cache: Disable Turbo cache
 * - --no-interaction, -n: Run in non-interactive mode
 *
 * Example usage:
 * ```bash
 * # Clean all workspaces
 * hive clean
 *
 * # Clean specific workspace
 * hive clean --workspace demo-app
 *
 * # Clean with shorthand
 * hive clean -w calculator
 *
 * # Clean with fresh Turbo cache
 * hive clean --no-cache
 * ```
 *
 * @see BaseCommand For inherited functionality and common options
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see CleanupCommand For deep cleaning (removes dependencies)
 */
#[AsCommand(
    name: 'clean:cache',
    description: 'Clean caches and build artifacts',
    aliases: ['clean'],
)]
final class CleanCommand extends BaseCommand
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
     * Execute the clean command.
     *
     * This method orchestrates the entire cleaning process:
     * 1. Extracts user options (workspace)
     * 2. Displays an intro message
     * 3. Determines which workspaces to clean
     * 4. Builds Turbo options (disables cache for fresh clean)
     * 5. Executes the cleaning via Turbo
     * 6. Reports success or failure
     *
     * The clean task executes the 'clean' script from each workspace's
     * package.json, which typically removes caches and build artifacts.
     * Turbo cache is disabled to ensure a fresh clean operation.
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Extract options from user input
        $workspace = $this->option('workspace');

        // Display intro banner
        $this->intro('Cleaning Caches');

        // Show what we're cleaning
        if ($workspace !== null && $workspace !== '') {
            // Cleaning specific workspace
            $this->info("Cleaning workspace: {$workspace}");
        } else {
            // Cleaning all workspaces
            $workspaces = $this->getWorkspaces();
            $this->info('Cleaning ' . $workspaces->count() . ' workspace(s)');
        }

        // Build Turbo options array
        // Disable cache to ensure fresh clean operation
        $options = ['cache' => false];

        // Filter to specific workspace if requested
        if ($workspace !== null && $workspace !== '') {
            $options['filter'] = $workspace;
        }

        // Run the clean task via Turbo
        // Turbo will handle parallel execution
        $exitCode = $this->turboRun('clean', $options);

        // Report results to user
        if ($exitCode === 0) {
            // Success - all caches cleaned
            $this->outro('✓ Caches cleaned successfully!');
        } else {
            // Failure - cleaning failed
            $this->error('✗ Clean failed');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
