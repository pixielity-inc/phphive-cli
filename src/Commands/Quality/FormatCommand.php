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
 * ./cli/bin/mono format
 *
 * # Fix specific workspace
 * ./cli/bin/mono format --workspace demo-app
 *
 * # Check only without fixing (delegates to lint command)
 * ./cli/bin/mono format --check
 *
 * # Using alias
 * ./cli/bin/mono fmt
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see LintCommand For checking without fixing
 */
#[AsCommand(
    name: 'format',
    description: 'Fix code style with Pint',
    aliases: ['fmt'],
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

        $this->addOption(
            'check',
            null,
            InputOption::VALUE_NONE,
            'Check only without fixing (delegates to lint command)',
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

        // If check is requested, delegate to lint command
        // This provides a convenient shortcut: format --check = lint
        if ($check) {
            $this->info('Check mode enabled, running lint command...');

            $application = $this->getApplication();
            if (! $application instanceof Application) {
                $this->error('Application not available');

                return Command::FAILURE;
            }

            return $application->find('lint')->run($input, $output);
        }

        // Display intro banner
        $this->intro('Fixing Code Style');

        // Show what we're formatting
        if (is_string($workspace) && $workspace !== '') {
            // Formatting specific workspace
            $this->info("Formatting workspace: {$workspace}");
        } else {
            // Formatting all workspaces
            $workspaces = $this->getWorkspaces();
            $this->info('Formatting ' . count($workspaces) . ' workspace(s)');
        }

        // Build Turbo options array
        $options = [];

        // Filter to specific workspace if requested
        if (is_string($workspace) && $workspace !== '') {
            $options['filter'] = $workspace;
        }

        // Run the format task via Turbo
        // Each workspace runs: vendor/bin/pint (without --test flag)
        // Pint will automatically fix style issues and modify files
        $exitCode = $this->turboRun('format', $options);

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
}
