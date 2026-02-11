<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Dev;

use function count;

use MonoPhp\Cli\Commands\BaseCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Build Command.
 *
 * This command builds applications and packages for production deployment.
 * It compiles assets, optimizes code, and prepares workspaces for deployment
 * using Turborepo for parallel execution and intelligent caching.
 *
 * The build process:
 * 1. Discovers all workspaces or targets specific workspace
 * 2. Runs build scripts via Turbo with dependency awareness
 * 3. Leverages caching to skip unchanged workspaces
 * 4. Executes builds in parallel for maximum speed
 * 5. Reports success or failure with clear feedback
 *
 * Build workflow:
 * - Validates workspace configuration
 * - Resolves workspace dependencies
 * - Executes build scripts in dependency order
 * - Caches build outputs for future runs
 * - Skips unchanged workspaces automatically
 *
 * Turbo task dependencies:
 * build → depends on → [lint, typecheck]
 *
 * Features:
 * - Parallel execution across workspaces
 * - Intelligent caching (skip if nothing changed)
 * - Workspace filtering (build specific workspace)
 * - Force rebuild option (ignore cache)
 * - Dependency graph awareness
 * - Progress tracking and error reporting
 * - Automatic dependency resolution
 * - Incremental builds support
 *
 * Common options inherited from BaseCommand:
 * - --workspace, -w: Target specific workspace
 * - --force, -f: Force operation by ignoring cache
 * - --no-cache: Disable Turbo cache
 * - --no-interaction, -n: Run in non-interactive mode
 *
 * Example usage:
 * ```bash
 * # Build all workspaces
 * ./cli/bin/mono build
 *
 * # Build specific workspace
 * ./cli/bin/mono build --workspace demo-app
 *
 * # Build with shorthand
 * ./cli/bin/mono build -w calculator
 *
 * # Force rebuild (ignore cache)
 * ./cli/bin/mono build --force
 *
 * # Force rebuild specific workspace
 * ./cli/bin/mono build -w demo-app --force
 *
 * # Disable Turbo cache completely
 * ./cli/bin/mono build --no-cache
 * ```
 *
 * @see BaseCommand For inherited functionality and common options
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see DevCommand For development server
 * @see DeployCommand For full deployment pipeline
 */
#[AsCommand(
    name: 'build',
    description: 'Build for production',
)]
final class BuildCommand extends BaseCommand
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
     * Execute the build command.
     *
     * This method orchestrates the entire build process:
     * 1. Extracts user options (workspace, force, no-cache)
     * 2. Displays an intro banner
     * 3. Determines which workspaces to build
     * 4. Builds Turbo options array based on user input
     * 5. Executes the build task via Turbo
     * 6. Reports success or failure with clear feedback
     *
     * The build task in turbo.json has dependencies on lint and typecheck
     * tasks, so Turbo automatically runs them first in the correct order
     * with maximum parallelization.
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Extract options from user input
        $workspaceOption = $this->option('workspace');
        $workspace = is_string($workspaceOption) && $workspaceOption !== '' ? $workspaceOption : null;
        $force = $this->hasOption('force');
        $noCache = $this->hasOption('no-cache');

        // Display intro banner
        $this->intro('Building for Production');

        // Show what we're building
        if ($workspace !== null) {
            // Building specific workspace
            $this->info("Building workspace: {$workspace}");
        } else {
            // Building all workspaces
            $workspaces = $this->getWorkspaces();
            $this->info('Building ' . count($workspaces) . ' workspace(s)');
        }

        // Build Turbo options array
        // These options control how Turbo executes the build task
        $options = [];

        // Filter to specific workspace if requested
        if ($workspace !== null) {
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

        // Run the build task via Turbo
        // Turbo will handle parallel execution, dependency resolution, and caching
        $this->info('Running build via Turbo...');
        $exitCode = $this->turboRun('build', $options);

        // Report results to user
        if ($exitCode === 0) {
            // Success - all builds completed
            $this->outro('✓ Build completed successfully!');
        } else {
            // Failure - one or more builds failed
            $this->error('✗ Build failed');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
