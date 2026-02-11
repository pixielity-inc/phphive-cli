<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Quality;

use MonoPhp\Cli\Commands\BaseCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Test Command.
 *
 * This command runs PHPUnit tests across workspaces using Turborepo for
 * parallel execution and intelligent caching. It supports filtering by
 * workspace, test type (unit/feature), coverage generation, and test name
 * patterns. Turbo ensures tests run efficiently with maximum parallelization.
 *
 * The testing process:
 * 1. Discovers all workspaces or targets specific workspace
 * 2. Determines test task based on options (unit/feature/coverage/all)
 * 3. Runs PHPUnit via Turbo with parallel execution
 * 4. Leverages Turbo's caching to skip unchanged tests
 * 5. Reports test results and failures
 *
 * Test types:
 * - Unit tests: Fast, isolated tests for individual units
 * - Feature tests: Integration tests for complete features
 * - All tests: Both unit and feature tests
 * - Coverage: Tests with code coverage reporting
 *
 * Features:
 * - Parallel execution across workspaces
 * - Intelligent caching (skip if code unchanged)
 * - Workspace filtering (test specific workspace)
 * - Test type filtering (unit/feature)
 * - Test name filtering (--filter option)
 * - Coverage report generation
 * - CI/CD friendly exit codes
 *
 * Example usage:
 * ```bash
 * # Run all tests in all workspaces
 * ./cli/bin/mono test
 *
 * # Test specific workspace
 * ./cli/bin/mono test --workspace calculator
 *
 * # Run only unit tests
 * ./cli/bin/mono test --unit
 *
 * # Run only feature tests
 * ./cli/bin/mono test --feature
 *
 * # Generate coverage report
 * ./cli/bin/mono test --coverage
 *
 * # Filter tests by name pattern
 * ./cli/bin/mono test --filter=UserTest
 *
 * # Combine options
 * ./cli/bin/mono t -w demo-app --unit --filter=Auth
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see MutateCommand For mutation testing after tests pass
 */
#[AsCommand(
    name: 'test',
    description: 'Run PHPUnit tests',
    aliases: ['t', 'phpunit'],
)]
final class TestCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Defines command-specific options for test execution. Common options
     * (workspace, force, no-cache, no-interaction) are inherited from BaseCommand.
     *
     * @see BaseCommand::configure() For inherited common options
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption(
                'unit',
                'u',
                InputOption::VALUE_NONE,
                'Run unit tests only',
            )
            ->addOption(
                'feature',
                null,
                InputOption::VALUE_NONE,
                'Run feature tests only',
            )
            ->addOption(
                'coverage',
                'c',
                InputOption::VALUE_NONE,
                'Generate coverage report',
            )
            ->addOption(
                'filter',
                null,
                InputOption::VALUE_REQUIRED,
                'Filter tests by pattern',
            )
            ->setHelp(
                <<<'HELP'
                The <info>test</info> command runs PHPUnit tests across workspaces.

                <comment>Examples:</comment>
                  <info>mono test</info>                      Run all tests
                  <info>mono test --workspace=calculator</info> Test specific workspace
                  <info>mono test --unit</info>               Run unit tests only
                  <info>mono test --feature</info>            Run feature tests only
                  <info>mono test --coverage</info>           Generate coverage report
                  <info>mono test --filter=UserTest</info>    Filter by test name

                Tests run in parallel using Turborepo for optimal performance.
                HELP
            );
    }

    /**
     * Execute the test command.
     *
     * This method orchestrates the entire test execution process:
     * 1. Displays an intro message
     * 2. Determines which test task to run (unit/feature/coverage/all)
     * 3. Validates workspace if specified
     * 4. Builds Turbo options based on user input
     * 5. Executes tests via Turbo
     * 6. Reports test results
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for test failures)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Display intro banner
        $this->intro('Running tests...');

        // Determine which test task to run based on options
        // This selects between test, test:unit, test:feature, or test:coverage
        $task = $this->determineTestTask();

        // Build Turbo options array
        $options = [];

        // Handle workspace filtering
        $workspace = $input->getOption('workspace');
        if (is_string($workspace) && $workspace !== '') {
            // Verify workspace exists before proceeding
            // This prevents cryptic errors from Turbo
            if (! $this->hasWorkspace($workspace)) {
                $this->error("Workspace '{$workspace}' not found");

                return self::FAILURE;
            }

            // Filter to specific workspace
            $options['filter'] = $workspace;
            $this->comment("Workspace: {$workspace}");
        } else {
            // Running tests in all workspaces
            $this->comment('Running tests in all workspaces');
        }

        // Show test name filter if specified
        // This is passed to PHPUnit's --filter option
        $filter = $input->getOption('filter');
        if (is_string($filter) && $filter !== '') {
            $this->comment("Filter: {$filter}");
        }

        $this->line('');

        // Run the test task via Turbo
        // Turbo will handle parallel execution and caching
        $exitCode = $this->turboRun($task, $options);

        $this->line('');

        // Report results to user
        if ($exitCode === 0) {
            // Success - all tests passed
            $this->outro('✓ All tests passed!');
        } else {
            // Failure - one or more tests failed
            $this->error('✗ Some tests failed');
        }

        return $exitCode;
    }

    /**
     * Determine which test task to run based on options.
     *
     * Maps user options to Turbo task names:
     * - --unit → test:unit
     * - --feature → test:feature
     * - --coverage → test:coverage
     * - (none) → test (all tests)
     *
     * @return string Turbo task name to execute
     */
    private function determineTestTask(): string
    {
        // Check for unit tests flag
        if ($this->hasOption('unit')) {
            $this->info('Running unit tests...');

            return 'test:unit';
        }
        // Check for feature tests flag
        if ($this->hasOption('feature')) {
            $this->info('Running feature tests...');

            return 'test:feature';
        }
        // Check for coverage flag
        if ($this->hasOption('coverage')) {
            $this->info('Running tests with coverage...');

            return 'test:coverage';
        }
        // Default: run all tests
        $this->info('Running all tests...');

        return 'test';
    }
}
