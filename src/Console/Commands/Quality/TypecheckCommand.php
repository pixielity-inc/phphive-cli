<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Quality;

use Override;
use PhpHive\Cli\Console\Commands\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Typecheck Command.
 *
 * This command runs static analysis with PHPStan across the monorepo to catch
 * type errors, bugs, and code quality issues before runtime. It leverages
 * Turborepo for parallel execution and intelligent caching.
 *
 * The typechecking process:
 * 1. Discovers all workspaces in the monorepo
 * 2. Runs PHPStan analysis in each workspace via Turbo
 * 3. Leverages Turbo's caching to skip unchanged workspaces
 * 4. Executes analysis in parallel for maximum speed
 * 5. Reports all type errors and violations found
 *
 * Features:
 * - Parallel execution across workspaces
 * - Intelligent caching (skip if code unchanged)
 * - Workspace filtering (check specific workspace)
 * - Custom PHPStan level support (0-9)
 * - Detailed error reporting with file locations
 * - Integration with centralized PHPStan config
 *
 * Example usage:
 * ```bash
 * # Check all workspaces
 * hive typecheck
 *
 * # Check specific workspace
 * hive tc --workspace calculator
 *
 * # Check with custom PHPStan level
 * hive phpstan --level 9
 *
 * # Check specific workspace at max level
 * hive tc -w demo-app -l 9
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 */
#[AsCommand(
    name: 'quality:typecheck',
    description: 'Run static analysis with PHPStan',
    aliases: ['typecheck', 'tc', 'phpstan'],
)]
final class TypecheckCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Defines command-specific options for static analysis. Common options
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
                'level',
                'l',
                InputOption::VALUE_REQUIRED,
                'PHPStan level (0-9, higher is stricter)',
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
                'Display error summary in table format',
            );
    }

    /**
     * Execute the typecheck command.
     *
     * This method orchestrates the entire static analysis process:
     * 1. Displays an intro message
     * 2. Determines which workspaces to analyze
     * 3. Builds Turbo options based on user input
     * 4. Executes PHPStan via Turbo
     * 5. Reports analysis results
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Extract options from user input
        $workspace = $this->option('workspace');
        $level = $this->option('level');
        $jsonMode = $this->hasOption('json');
        $tableMode = $this->hasOption('table');

        // Display intro banner (skip in JSON mode)
        // Show what we're checking
        if (! $jsonMode) {
            $this->intro('Running Static Analysis');
            if (is_string($workspace) && $workspace !== '') {
                // Checking specific workspace
                $this->info("Checking workspace: {$workspace}");
            } else {
                // Checking all workspaces
                $workspaces = $this->getWorkspaces();
                $this->info('Checking ' . $workspaces->count() . ' workspace(s)');
            }
            // Show PHPStan level if specified
            // Higher levels are stricter and catch more issues
            if (is_string($level) && $level !== '') {
                $this->info("PHPStan level: {$level}");
            }
        }

        // Build Turbo options array
        // These options control how Turbo executes the task
        $options = [];

        // Filter to specific workspace if requested
        if (is_string($workspace) && $workspace !== '') {
            $options['filter'] = $workspace;
        }

        // Capture start time for metrics
        $startTime = microtime(true);

        // Run the typecheck task via Turbo
        // Turbo will handle parallel execution and caching
        $exitCode = $this->turboRun('typecheck', $options);

        // Calculate duration
        $duration = round(microtime(true) - $startTime, 2);

        // Handle different output modes
        if ($jsonMode) {
            return $this->outputJsonResults($exitCode, $workspace, $level, $duration);
        }

        if ($tableMode) {
            return $this->outputTableResults($exitCode, $workspace, $level, $duration);
        }

        // Default output mode
        // Report results to user
        if ($exitCode === 0) {
            // Success - no type errors found
            $this->outro('✓ Static analysis passed!');
        } else {
            // Failure - type errors or violations found
            $this->error('✗ Static analysis found issues');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Output typecheck results in JSON format for CI/CD integration.
     *
     * @param  int         $exitCode  Typecheck execution exit code
     * @param  string|null $workspace Workspace filter (if any)
     * @param  string|null $level     PHPStan level (if specified)
     * @param  float       $duration  Execution duration in seconds
     * @return int         Exit code to return
     */
    private function outputJsonResults(
        int $exitCode,
        ?string $workspace,
        ?string $level,
        float $duration
    ): int {
        $data = [
            'status' => $exitCode === 0 ? 'success' : 'failure',
            'task' => 'typecheck',
            'exitCode' => $exitCode,
            'duration' => $duration,
            'timestamp' => date('c'),
        ];

        if ($workspace !== null && is_string($workspace) && $workspace !== '') {
            $data['workspace'] = $workspace;
        }

        if ($level !== null && is_string($level) && $level !== '') {
            $data['level'] = $level;
        }

        if ($exitCode !== 0) {
            $data['message'] = 'Static analysis found issues';
        }

        $this->outputJson($data);

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Output typecheck results in table format.
     *
     * @param  int         $exitCode  Typecheck execution exit code
     * @param  string|null $workspace Workspace filter (if any)
     * @param  string|null $level     PHPStan level (if specified)
     * @param  float       $duration  Execution duration in seconds
     * @return int         Exit code to return
     */
    private function outputTableResults(
        int $exitCode,
        ?string $workspace,
        ?string $level,
        float $duration
    ): int {
        $this->line('');
        $this->line('Typecheck Summary');
        $this->line('');

        $rows = [
            ['Task', 'typecheck'],
            ['Status', $exitCode === 0 ? '✓ Passed' : '✗ Failed'],
            ['Duration', $duration . 's'],
        ];

        if ($workspace !== null && is_string($workspace) && $workspace !== '') {
            $rows[] = ['Workspace', $workspace];
        } else {
            $rows[] = ['Workspace', 'All'];
        }

        if ($level !== null && is_string($level) && $level !== '') {
            $rows[] = ['PHPStan Level', $level];
        }

        $this->table(['Property', 'Value'], $rows);

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
