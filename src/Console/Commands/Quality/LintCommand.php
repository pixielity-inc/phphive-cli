<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Quality;

use Override;
use PhpHive\Cli\Console\Commands\BaseCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lint Command.
 *
 * This command checks code style compliance using Laravel Pint across the monorepo.
 * It validates that code follows the configured style rules without making any
 * changes. Use the format command (or --fix option) to automatically fix issues.
 *
 * The linting process:
 * 1. Discovers all workspaces or targets specific workspace
 * 2. Runs Pint in test mode (--test flag) via Turbo
 * 3. Reports style violations without modifying files
 * 4. Provides helpful suggestions for fixing issues
 * 5. Returns appropriate exit code for CI/CD integration
 *
 * Features:
 * - Parallel execution across workspaces
 * - Workspace filtering (lint specific workspace)
 * - Auto-fix delegation (--fix delegates to format command)
 * - CI/CD friendly exit codes
 * - Clear error reporting with fix suggestions
 *
 * Example usage:
 * ```bash
 * # Check code style for all workspaces
 * hive lint
 *
 * # Check specific workspace
 * hive lint --workspace demo-app
 *
 * # Auto-fix issues (delegates to format command)
 * hive lint --fix
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see FormatCommand For auto-fixing code style
 */
#[AsCommand(
    name: 'quality:lint',
    description: 'Check code style with Pint',
    aliases: ['lint'],
)]
final class LintCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Defines command-specific options for linting. Common options
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
                'fix',
                null,
                InputOption::VALUE_NONE,
                'Auto-fix issues (delegates to format command)',
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
                'Display issue summary in table format',
            );
    }

    /**
     * Execute the lint command.
     *
     * This method orchestrates the code style checking:
     * 1. Checks if auto-fix is requested (delegates to format)
     * 2. Determines which workspaces to lint
     * 3. Runs Pint in test mode via Turbo
     * 4. Reports violations and provides fix suggestions
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for style violations)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Extract options from user input
        $workspace = $this->option('workspace');
        $fix = $this->hasOption('fix');
        $jsonMode = $this->hasOption('json');
        $tableMode = $this->hasOption('table');

        // If fix is requested, delegate to format command
        // This provides a convenient shortcut: lint --fix = format
        if ($fix) {
            if (! $jsonMode) {
                $this->info('Auto-fix enabled, running format command...');
            }

            $application = $this->getApplication();
            if (! $application instanceof Application) {
                if ($jsonMode) {
                    $this->outputJson([
                        'status' => 'error',
                        'message' => 'Application not available',
                        'timestamp' => date('c'),
                    ]);
                } else {
                    $this->error('Application not available');
                }

                return Command::FAILURE;
            }

            return $application->find('format')->run($input, $output);
        }

        // Display intro banner (skip in JSON mode)
        // Show what we're linting
        if (! $jsonMode) {
            $this->intro('Checking Code Style');
            if (is_string($workspace) && $workspace !== '') {
                // Linting specific workspace
                $this->info("Linting workspace: {$workspace}");
            } else {
                // Linting all workspaces
                $workspaces = $this->getWorkspaces();
                $this->info('Linting ' . $workspaces->count() . ' workspace(s)');
            }
        }

        // Build Turbo options array
        $options = [];

        // Filter to specific workspace if requested
        if (is_string($workspace) && $workspace !== '') {
            $options['filter'] = $workspace;
        }

        // Capture start time for metrics
        $startTime = microtime(true);

        // Run the lint task via Turbo
        // Each workspace runs: vendor/bin/pint --test
        $exitCode = $this->turboRun('lint', $options);

        // Calculate duration
        $duration = round(microtime(true) - $startTime, 2);

        // Handle different output modes
        if ($jsonMode) {
            return $this->outputJsonResults($exitCode, $workspace, $duration);
        }

        if ($tableMode) {
            return $this->outputTableResults($exitCode, $workspace, $duration);
        }

        // Default output mode
        // Report results to user
        if ($exitCode === 0) {
            // Success - no style violations found
            $this->outro('✓ Code style check passed!');
        } else {
            // Failure - style violations found
            $this->error('✗ Code style issues found');
            $this->info('Run "hive format" to auto-fix issues');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Output lint results in JSON format for CI/CD integration.
     *
     * @param  int         $exitCode  Lint execution exit code
     * @param  string|null $workspace Workspace filter (if any)
     * @param  float       $duration  Execution duration in seconds
     * @return int         Exit code to return
     */
    private function outputJsonResults(
        int $exitCode,
        ?string $workspace,
        float $duration
    ): int {
        $data = [
            'status' => $exitCode === 0 ? 'success' : 'failure',
            'task' => 'lint',
            'exitCode' => $exitCode,
            'duration' => $duration,
            'timestamp' => date('c'),
        ];

        if ($workspace !== null && is_string($workspace) && $workspace !== '') {
            $data['workspace'] = $workspace;
        }

        if ($exitCode !== 0) {
            $data['message'] = 'Code style issues found. Run "hive format" to auto-fix.';
        }

        $this->outputJson($data);

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Output lint results in table format.
     *
     * @param  int         $exitCode  Lint execution exit code
     * @param  string|null $workspace Workspace filter (if any)
     * @param  float       $duration  Execution duration in seconds
     * @return int         Exit code to return
     */
    private function outputTableResults(
        int $exitCode,
        ?string $workspace,
        float $duration
    ): int {
        $this->line('');
        $this->line('Lint Summary');
        $this->line('');

        $rows = [
            ['Task', 'lint'],
            ['Status', $exitCode === 0 ? '✓ Passed' : '✗ Failed'],
            ['Duration', $duration . 's'],
        ];

        if ($workspace !== null && is_string($workspace) && $workspace !== '') {
            $rows[] = ['Workspace', $workspace];
        } else {
            $rows[] = ['Workspace', 'All'];
        }

        $this->table(['Property', 'Value'], $rows);

        if ($exitCode !== 0) {
            $this->line('');
            $this->info('Run "hive format" to auto-fix issues');
        }

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
