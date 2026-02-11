<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Turbo;

use function json_encode;

use MonoPhp\Cli\Commands\BaseCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run Command.
 *
 * This command provides a convenient interface for executing arbitrary Turborepo
 * tasks across workspaces in the monorepo. It acts as a wrapper around Turbo's
 * task execution system, allowing you to run any task defined in workspace
 * package.json files with full control over execution options.
 *
 * Task execution workflow:
 * 1. Validates the task name
 * 2. Builds Turbo options from command flags
 * 3. Determines which workspaces to target
 * 4. Executes the task via Turborepo
 * 5. Reports execution results
 *
 * Turbo features leveraged:
 * - Task dependency resolution (runs dependencies first)
 * - Intelligent caching (skips unchanged workspaces)
 * - Parallel execution (runs independent tasks simultaneously)
 * - Workspace filtering (target specific apps/packages)
 * - Force execution (bypass cache when needed)
 * - Continue on error (don't stop on first failure)
 *
 * Common tasks to run:
 * - build: Compile/prepare production assets
 * - test: Run test suites (unit, integration)
 * - lint: Check code style and standards
 * - typecheck: Run static analysis (PHPStan)
 * - format: Auto-fix code style issues
 * - clean: Remove cache and build artifacts
 * - deploy: Execute deployment scripts
 * - custom: Any task defined in package.json
 *
 * Workspace filtering:
 * - Without --workspace: Runs task in all workspaces that define it
 * - With --workspace: Runs task only in specified workspace
 * - Turbo automatically handles dependencies between workspaces
 *
 * Caching behavior:
 * - Default: Uses Turbo cache (skips if inputs unchanged)
 * - --force: Ignores cache, always runs task
 * - --no-cache: Disables caching for this execution
 * - Cache keys based on: task inputs, dependencies, configuration
 *
 * Parallel execution:
 * - Default: Turbo determines parallelization automatically
 * - --parallel: Forces parallel execution where possible
 * - Respects task dependencies (dependent tasks run sequentially)
 *
 * Error handling:
 * - Default: Stops on first error (fail-fast)
 * - --continue: Continues executing other tasks after errors
 * - Exit code reflects overall success/failure
 *
 * Features:
 * - Run any task defined in package.json
 * - Filter to specific workspace
 * - Force execution (bypass cache)
 * - Disable caching entirely
 * - Parallel execution control
 * - Continue on error option
 * - Detailed progress reporting
 *
 * Example usage:
 * ```bash
 * # Run build task in all workspaces
 * ./cli/bin/mono run build
 *
 * # Run tests in specific workspace
 * ./cli/bin/mono run test --workspace api
 * ./cli/bin/mono run test -w admin
 *
 * # Force run lint (ignore cache)
 * ./cli/bin/mono run lint --force
 * ./cli/bin/mono run lint -f
 *
 * # Run custom task without caching
 * ./cli/bin/mono run custom-task --no-cache
 *
 * # Run in parallel mode
 * ./cli/bin/mono run build --parallel
 * ./cli/bin/mono run build -p
 *
 * # Continue on error (don't stop on first failure)
 * ./cli/bin/mono run test --continue
 *
 * # Combine multiple options
 * ./cli/bin/mono run build -w api --force --parallel
 *
 * # Using aliases
 * ./cli/bin/mono exec test
 * ./cli/bin/mono execute lint
 * ```
 *
 * Task requirements:
 * - Task must be defined in workspace package.json scripts
 * - Task name must match exactly (case-sensitive)
 * - Turbo configuration in turbo.json (optional but recommended)
 *
 * @see BaseCommand For inherited functionality
 * @see TurboCommand For direct Turbo access
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 */
#[AsCommand(
    name: 'run',
    description: 'Run a Turbo task',
    aliases: ['exec', 'execute'],
)]
final class RunCommand extends BaseCommand
{
    /**
     * Configure the command options and arguments.
     *
     * Defines all command-line arguments and options that users can pass
     * to customize task execution behavior. The task argument is required
     * and must match a script defined in workspace package.json files.
     *
     * Common options (workspace, force, no-cache, no-interaction) are inherited from BaseCommand.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument(
                'task',
                InputArgument::REQUIRED,
                'The task to run (e.g., build, test, lint)',
            )
            ->addOption(
                'parallel',
                'p',
                InputOption::VALUE_NONE,
                'Run in parallel',
            )
            ->addOption(
                'continue',
                null,
                InputOption::VALUE_NONE,
                'Continue on error',
            )
            ->setHelp(
                <<<'HELP'
                The <info>run</info> command executes a Turbo task across workspaces.

                <comment>Examples:</comment>
                  <info>mono run build</info>                Run build task in all workspaces
                  <info>mono run test --workspace=api</info> Run test in specific workspace
                  <info>mono run lint --force</info>         Force run lint task
                  <info>mono run custom --parallel</info>    Run custom task in parallel

                The task must be defined in workspace package.json scripts.
                HELP
            );
    }

    /**
     * Execute the run command.
     *
     * This method orchestrates the task execution process:
     * 1. Extracts the task name from arguments
     * 2. Displays an intro message
     * 3. Builds Turbo options from command flags
     * 4. Executes the task via Turborepo
     * 5. Reports execution results
     *
     * The task is executed across all workspaces that define it in their
     * package.json, unless filtered to a specific workspace. Turbo handles
     * dependency resolution, caching, and parallel execution automatically.
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, non-zero for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Extract the task name from command arguments
        $task = $input->getArgument('task');

        // Display intro banner with task name
        $this->intro("Running task: {$task}");

        // Build options array for Turbo execution
        // These options control how Turbo executes the task
        $options = [];

        // Filter to specific workspace if requested
        // This limits execution to a single app or package
        $workspace = $input->getOption('workspace');
        if (! in_array($workspace, [null, false, ''], true)) {
            $options['filter'] = $workspace;
        }

        // Force execution (bypass cache)
        // Useful when you need to ensure task runs regardless of cache state
        if ($input->getOption('force') === true) {
            $options['force'] = true;
        }

        // Disable caching entirely
        // Different from force - this prevents cache writes too
        if ($input->getOption('no-cache') === true) {
            $options['cache'] = false;
        }

        // Enable parallel execution
        // Forces parallel mode even for tasks that might run sequentially
        if ($input->getOption('parallel') === true) {
            $options['parallel'] = true;
        }

        // Continue on error
        // Don't stop on first failure - run all tasks
        if ($input->getOption('continue') === true) {
            $options['continue'] = true;
        }

        // Display options if any were set
        // This helps users understand what Turbo is doing
        if ($options !== []) {
            $this->comment('Options: ' . json_encode($options));
        }

        $this->line('');

        // Run the task via Turbo
        // Turbo will handle dependency resolution, caching, and execution
        $exitCode = $this->turboRun($task, $options);

        // Report results to user
        if ($exitCode === 0) {
            // Success - task completed without errors
            $this->outro("✓ Task '{$task}' completed successfully");
        } else {
            // Failure - task encountered errors
            $this->error("✗ Task '{$task}' failed");
        }

        return $exitCode;
    }
}
