<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Quality;

use Override;
use PhpHive\Cli\Console\Commands\BaseCommand;
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
 * hive test
 *
 * # Test specific workspace
 * hive test --workspace calculator
 *
 * # Run only unit tests
 * hive test --unit
 *
 * # Run only feature tests
 * hive test --feature
 *
 * # Generate coverage report
 * hive test --coverage
 *
 * # Filter tests by name pattern
 * hive test --filter=UserTest
 *
 * # Combine options
 * hive t -w demo-app --unit --filter=Auth
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see MutateCommand For mutation testing after tests pass
 */
#[AsCommand(
    name: 'quality:test',
    description: 'Run PHPUnit tests',
    aliases: ['test', 't', 'phpunit'],
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
            ->addOption(
                'json',
                'j',
                InputOption::VALUE_NONE,
                'Output results as JSON (for CI/CD integration)',
            )
            ->addOption(
                'table',
                null,
                InputOption::VALUE_NONE,
                'Display test summary in table format',
            )
            ->addOption(
                'summary',
                's',
                InputOption::VALUE_NONE,
                'Show quick overview only',
            )
            ->setHelp(
                <<<'HELP'
                The <info>test</info> command runs PHPUnit tests across workspaces.

                <comment>Examples:</comment>
                  <info>hive test</info>                      Run all tests
                  <info>hive test --workspace=calculator</info> Test specific workspace
                  <info>hive test --unit</info>               Run unit tests only
                  <info>hive test --feature</info>            Run feature tests only
                  <info>hive test --coverage</info>           Generate coverage report
                  <info>hive test --filter=UserTest</info>    Filter by test name
                  <info>hive test --json</info>               Output as JSON for CI/CD
                  <info>hive test --table</info>              Show summary table
                  <info>hive test --summary</info>            Quick overview only

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
        // Check for JSON output mode first
        $jsonMode = $this->hasOption('json');
        $tableMode = $this->hasOption('table');
        $summaryMode = $this->hasOption('summary');

        // Display intro banner (skip in JSON mode)
        if (! $jsonMode) {
            $this->intro('Running tests...');
        }

        // Determine which test task to run based on options
        // This selects between test, test:unit, test:feature, or test:coverage
        $task = $this->determineTestTask($jsonMode);

        // Build Turbo options array
        $options = [];

        // Handle workspace filtering
        $workspace = $input->getOption('workspace');
        if (is_string($workspace) && $workspace !== '') {
            // Verify workspace exists before proceeding
            // This prevents cryptic errors from Turbo
            if (! $this->hasWorkspace($workspace)) {
                if ($jsonMode) {
                    $this->outputJson([
                        'status' => 'error',
                        'message' => "Workspace '{$workspace}' not found",
                        'timestamp' => date('c'),
                    ]);
                } else {
                    $this->error("Workspace '{$workspace}' not found");
                }

                return self::FAILURE;
            }
            // Filter to specific workspace
            $options['filter'] = $workspace;
            if (! $jsonMode && ! $summaryMode) {
                $this->comment("Workspace: {$workspace}");
            }
        } elseif (! $jsonMode && ! $summaryMode) {
            // Running tests in all workspaces
            $this->comment('Running tests in all workspaces');
        }

        // Show test name filter if specified
        // This is passed to PHPUnit's --filter option
        $filter = $input->getOption('filter');
        if (is_string($filter) && $filter !== '' && ! $jsonMode && ! $summaryMode) {
            $this->comment("Filter: {$filter}");
        }

        if (! $jsonMode && ! $summaryMode) {
            $this->line('');
        }

        // Capture start time for metrics
        $startTime = microtime(true);

        // Run the test task via Turbo
        // Turbo will handle parallel execution and caching
        $exitCode = $this->turboRun($task, $options);

        // Calculate duration
        $duration = round(microtime(true) - $startTime, 2);

        // Handle different output modes
        if ($jsonMode) {
            return $this->outputJsonResults($exitCode, $task, $workspace, $filter, $duration);
        }

        if ($tableMode) {
            return $this->outputTableResults($exitCode, $task, $workspace, $duration);
        }

        if ($summaryMode) {
            return $this->outputSummaryResults($exitCode, $task, $workspace, $duration);
        }

        // Default output mode
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
     * @param  bool   $jsonMode Whether JSON output mode is enabled
     * @return string Turbo task name to execute
     */
    private function determineTestTask(bool $jsonMode = false): string
    {
        // Check for unit tests flag
        if ($this->hasOption('unit')) {
            if (! $jsonMode) {
                $this->info('Running unit tests...');
            }

            return 'test:unit';
        }
        // Check for feature tests flag
        if ($this->hasOption('feature')) {
            if (! $jsonMode) {
                $this->info('Running feature tests...');
            }

            return 'test:feature';
        }
        // Check for coverage flag
        if ($this->hasOption('coverage')) {
            if (! $jsonMode) {
                $this->info('Running tests with coverage...');
            }

            return 'test:coverage';
        }
        // Default: run all tests
        if (! $jsonMode) {
            $this->info('Running all tests...');
        }

        return 'test';
    }

    /**
     * Output test results in JSON format for CI/CD integration.
     *
     * @param  int         $exitCode  Test execution exit code
     * @param  string      $task      Test task that was run
     * @param  string|null $workspace Workspace filter (if any)
     * @param  string|null $filter    Test name filter (if any)
     * @param  float       $duration  Execution duration in seconds
     * @return int         Exit code to return
     */
    private function outputJsonResults(
        int $exitCode,
        string $task,
        ?string $workspace,
        ?string $filter,
        float $duration
    ): int {
        $data = [
            'status' => $exitCode === 0 ? 'success' : 'failure',
            'task' => $task,
            'exitCode' => $exitCode,
            'duration' => $duration,
            'timestamp' => date('c'),
        ];

        if ($workspace !== null && $workspace !== '') {
            $data['workspace'] = $workspace;
        }

        if ($filter !== null && $filter !== '') {
            $data['filter'] = $filter;
        }

        $this->outputJson($data);

        return $exitCode;
    }

    /**
     * Output test results in table format.
     *
     * @param  int         $exitCode  Test execution exit code
     * @param  string      $task      Test task that was run
     * @param  string|null $workspace Workspace filter (if any)
     * @param  float       $duration  Execution duration in seconds
     * @return int         Exit code to return
     */
    private function outputTableResults(
        int $exitCode,
        string $task,
        ?string $workspace,
        float $duration
    ): int {
        $this->line('');
        $this->line('Test Summary');
        $this->line('');

        $rows = [
            ['Task', $task],
            ['Status', $exitCode === 0 ? '✓ Passed' : '✗ Failed'],
            ['Duration', $duration . 's'],
        ];

        $rows[] = $workspace !== null && $workspace !== '' ? ['Workspace', $workspace] : ['Workspace', 'All'];

        $this->table(['Property', 'Value'], $rows);

        return $exitCode;
    }

    /**
     * Output test results in summary format.
     *
     * @param  int         $exitCode  Test execution exit code
     * @param  string      $task      Test task that was run
     * @param  string|null $workspace Workspace filter (if any)
     * @param  float       $duration  Execution duration in seconds
     * @return int         Exit code to return
     */
    private function outputSummaryResults(
        int $exitCode,
        string $task,
        ?string $workspace,
        float $duration
    ): int {
        $status = $exitCode === 0 ? '✓ PASSED' : '✗ FAILED';
        $workspaceInfo = $workspace !== null && $workspace !== '' ? " ({$workspace})" : ' (all workspaces)';

        $this->line("{$status}: {$task}{$workspaceInfo} in {$duration}s");

        return $exitCode;
    }
}
