<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Quality;

use function count;

use MonoPhp\Cli\Commands\BaseCommand;
use Override;
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
 * ./cli/bin/mono lint
 *
 * # Check specific workspace
 * ./cli/bin/mono lint --workspace demo-app
 *
 * # Auto-fix issues (delegates to format command)
 * ./cli/bin/mono lint --fix
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see FormatCommand For auto-fixing code style
 */
#[AsCommand(
    name: 'lint',
    description: 'Check code style with Pint',
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

        $this->addOption(
            'fix',
            null,
            InputOption::VALUE_NONE,
            'Auto-fix issues (delegates to format command)',
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

        // If fix is requested, delegate to format command
        // This provides a convenient shortcut: lint --fix = format
        if ($fix) {
            $this->info('Auto-fix enabled, running format command...');

            $application = $this->getApplication();
            if (! $application instanceof Application) {
                $this->error('Application not available');

                return Command::FAILURE;
            }

            return $application->find('format')->run($input, $output);
        }

        // Display intro banner
        $this->intro('Checking Code Style');

        // Show what we're linting
        if (is_string($workspace) && $workspace !== '') {
            // Linting specific workspace
            $this->info("Linting workspace: {$workspace}");
        } else {
            // Linting all workspaces
            $workspaces = $this->getWorkspaces();
            $this->info('Linting ' . count($workspaces) . ' workspace(s)');
        }

        // Build Turbo options array
        $options = [];

        // Filter to specific workspace if requested
        if (is_string($workspace) && $workspace !== '') {
            $options['filter'] = $workspace;
        }

        // Run the lint task via Turbo
        // Each workspace runs: vendor/bin/pint --test
        $exitCode = $this->turboRun('lint', $options);

        // Report results to user
        if ($exitCode === 0) {
            // Success - no style violations found
            $this->outro('✓ Code style check passed!');
        } else {
            // Failure - style violations found
            $this->error('✗ Code style issues found');
            $this->info('Run "mono format" to auto-fix issues');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
