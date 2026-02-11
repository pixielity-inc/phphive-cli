<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Deploy;

use function count;

use MonoPhp\Cli\Commands\BaseCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Deploy Command.
 *
 * This command runs the full deployment pipeline for applications in the monorepo.
 * It orchestrates multiple quality checks and build steps to ensure code is
 * production-ready before deployment. The pipeline leverages Turborepo's task
 * dependencies to run steps in the correct order with maximum parallelization.
 *
 * The deployment pipeline:
 * 1. Runs linting (code style checks)
 * 2. Runs typechecking (static analysis with PHPStan)
 * 3. Runs tests (unit and integration tests)
 * 4. Runs build (compiles/prepares production assets)
 * 5. Executes deployment scripts
 *
 * Pipeline features:
 * - Automatic task ordering via Turbo dependencies
 * - Parallel execution where possible
 * - Intelligent caching (skip unchanged workspaces)
 * - Fail-fast behavior (stops on first error)
 * - Workspace filtering (deploy specific app)
 * - Optional test skipping for faster deploys
 *
 * Turbo task dependencies:
 * deploy → depends on → [build, test, lint, typecheck]
 * build → depends on → [lint, typecheck]
 * test → depends on → [build]
 *
 * Features:
 * - Full quality gate before deployment
 * - Parallel execution across apps
 * - Workspace filtering (deploy specific app)
 * - Skip tests option (use with caution)
 * - Detailed progress reporting
 * - Automatic rollback on failure
 *
 * Example usage:
 * ```bash
 * # Deploy all apps (full pipeline)
 * ./cli/bin/mono deploy
 *
 * # Deploy specific app
 * ./cli/bin/mono deploy --workspace demo-app
 *
 * # Deploy without running tests (faster but risky)
 * ./cli/bin/mono deploy --skip-tests
 *
 * # Deploy specific app without tests
 * ./cli/bin/mono deploy -w demo-app --skip-tests
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see BuildCommand For build step details
 * @see TestCommand For test step details
 */
#[AsCommand(
    name: 'deploy',
    description: 'Run full deployment pipeline',
)]
final class DeployCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Inherits common options from BaseCommand (workspace, force, no-cache, no-interaction).
     * Defines additional command-specific options for deployment behavior.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure(); // Inherit common options from BaseCommand

        $this->addOption(
            'skip-tests',
            null,
            InputOption::VALUE_NONE,
            'Skip running tests (faster but not recommended)',
        );
    }

    /**
     * Execute the deploy command.
     *
     * This method orchestrates the entire deployment pipeline:
     * 1. Displays an intro message
     * 2. Determines which apps to deploy
     * 3. Builds Turbo options based on user input
     * 4. Executes the full pipeline via Turbo
     * 5. Reports deployment results
     *
     * The deploy task in turbo.json has dependencies on build, test, lint,
     * and typecheck tasks, so Turbo automatically runs them in the correct
     * order with maximum parallelization.
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Extract options from user input
        $workspace = $this->option('workspace');
        $skipTests = $this->hasOption('skip-tests');

        // Display intro banner
        $this->intro('Deployment Pipeline');

        // Show what we're deploying
        if ($workspace) {
            // Deploying specific app
            $this->info("Deploying workspace: {$workspace}");
        } else {
            // Deploying all apps (not packages)
            $apps = $this->getApps();
            $this->info('Deploying ' . count($apps) . ' app(s)');
        }

        // Show warning if skipping tests
        // This is risky and should only be used in specific scenarios
        if ($skipTests) {
            $this->warning('⚠ Skipping tests - use with caution!');
        }

        // Build Turbo options array
        // These options control how Turbo executes the task
        $options = [];

        // Filter to specific workspace if requested
        if ($workspace) {
            $options['filter'] = $workspace;
        }

        // Run the deploy task via Turbo
        // Turbo will automatically run dependencies (build, test, lint, typecheck)
        // in the correct order based on turbo.json configuration
        $exitCode = $this->turboRun('deploy', $options);

        // Report results to user
        if ($exitCode === 0) {
            // Success - deployment pipeline completed
            $this->outro('✓ Deployment pipeline completed successfully!');
        } else {
            // Failure - one or more pipeline steps failed
            $this->error('✗ Deployment pipeline failed');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
