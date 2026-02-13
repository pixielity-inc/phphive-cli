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
 * Format Command.
 *
 * This command automatically fixes code style issues using Laravel Pint across
 * the monorepo. It applies the configured style rules and modifies files to
 * ensure consistent code formatting throughout the codebase.
 *
 * The formatting process:
 * 1. Discovers all workspaces or targets specific workspace
 * 2. Runs Pint in fix mode (default behavior) via Turbo
 * 3. Automatically modifies files to fix style issues
 * 4. Reports which files were changed
 * 5. Returns success when all formatting is complete
 *
 * Features:
 * - Parallel execution across workspaces
 * - Workspace filtering (format specific workspace)
 * - Check-only mode (--check delegates to lint command)
 * - Automatic file modification
 * - Clear reporting of changes made
 *
 * Example usage:
 * ```bash
 * # Fix code style for all workspaces
 * hive format
 *
 * # Fix specific workspace
 * hive format --workspace demo-app
 *
 * # Check only without fixing (delegates to lint command)
 * hive format --check
 *
 * # Using alias
 * hive fmt
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see LintCommand For checking without fixing
 */
#[AsCommand(
    name: 'quality:format',
    description: 'Fix code style with Pint',
    aliases: ['format', 'fmt'],
)]
final class FormatCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Defines command-specific options for formatting. Common options
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
                'check',
                null,
                InputOption::VALUE_NONE,
                'Check only without fixing (delegates to lint command)',
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
                'Display changes summary in table format',
            );
    }

    /**
     * Execute the format command.
     *
     * This method orchestrates the code style fixing:
     * 1. Checks if check-only mode is requested (delegates to lint)
     * 2. Determines which workspaces to format
     * 3. Runs Pint in fix mode via Turbo
     * 4. Reports which files were modified
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Extract options from user input
        $workspace = $this->option('workspace');
        $check = $this->hasOption('check');
        $jsonMode = $this->hasOption('json');
        $tableMode = $this->hasOption('table');

        // If check is requested, delegate to lint command
        // This provides a convenient shortcut: format --check = lint
        if ($check) {
            if (! $jsonMode) {
                $this->info('Check mode enabled, running lint command...');
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

            return $application->find('lint')->run($input, $output);
        }

        // Display intro banner (skip in JSON mode)
        // Show what we're formatting
        if (! $jsonMode) {
            $this->intro('Fixing Code Style');
            if (is_string($workspace) && $workspace !== '') {
                // Formatting specific workspace
                $this->info("Formatting workspace: {$workspace}");
            } else {
                // Formatting all workspaces
                $workspaces = $this->getWorkspaces();
                $this->info('Formatting ' . $workspaces->count() . ' workspace(s)');
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

        // Run the format task via Turbo
        // Each workspace runs: vendor/bin/pint (without --test flag)
        // Pint will automatically fix style issues and modify files
        $exitCode = $this->turboRun('format', $options);

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
            // Success - all files formatted
            $this->outro('✓ Code style fixed successfully!');
        } else {
            // Failure - formatting encountered errors
            $this->error('✗ Format failed');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Output format results in JSON format for CI/CD integration.
     *
     * @param  int         $exitCode  Format execution exit code
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
            'task' => 'format',
            'exitCode' => $exitCode,
            'duration' => $duration,
            'timestamp' => date('c'),
        ];

        if ($workspace !== null && is_string($workspace) && $workspace !== '') {
            $data['workspace'] = $workspace;
        }

        if ($exitCode !== 0) {
            $data['message'] = 'Format failed';
        }

        $this->outputJson($data);

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Output format results in table format.
     *
     * @param  int         $exitCode  Format execution exit code
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
        $this->line('Format Summary');
        $this->line('');

        $rows = [
            ['Task', 'format'],
            ['Status', $exitCode === 0 ? '✓ Success' : '✗ Failed'],
            ['Duration', $duration . 's'],
        ];

        if ($workspace !== null && is_string($workspace) && $workspace !== '') {
            $rows[] = ['Workspace', $workspace];
        } else {
            $rows[] = ['Workspace', 'All'];
        }

        $this->table(['Property', 'Value'], $rows);

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
