<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Turbo;

use function implode;
use function json_encode;

use MonoPhp\Cli\Commands\BaseCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Turbo Command.
 *
 * This command provides direct, low-level access to Turborepo's CLI with full
 * control over all Turbo commands, options, and flags. It acts as a passthrough
 * to the Turbo binary while maintaining integration with the monorepo CLI's
 * output formatting and error handling.
 *
 * Use cases:
 * - Access advanced Turbo features not wrapped by other commands
 * - Execute Turbo commands with specific flag combinations
 * - Debug Turbo behavior with verbose output
 * - Prune workspaces for deployment
 * - Generate Turbo configuration
 * - Access experimental Turbo features
 *
 * Common Turbo commands:
 * - run: Execute tasks (use RunCommand for convenience)
 * - prune: Prepare workspace for deployment
 * - daemon: Manage Turbo daemon process
 * - link: Link to remote cache
 * - unlink: Unlink from remote cache
 * - login: Authenticate with Vercel
 * - logout: Clear authentication
 * - bin: Print Turbo binary path
 *
 * Turbo run options:
 * - --filter: Filter to specific workspace(s)
 * - --force: Ignore cache and force execution
 * - --no-cache: Disable reading from cache
 * - --parallel: Run tasks in parallel
 * - --continue: Continue on error
 * - --dry-run: Show what would be executed
 * - --graph: Generate task dependency graph
 * - --concurrency: Limit concurrent tasks
 * - --output-logs: Control log output (full, hash-only, new-only, none)
 *
 * Turbo prune options:
 * - --scope: Workspace to prune for
 * - --docker: Generate Docker-optimized output
 * - --out-dir: Output directory for pruned workspace
 *
 * Workspace filtering:
 * - Single workspace: --filter=api
 * - Multiple workspaces: --filter=api --filter=admin
 * - Pattern matching: --filter=@mono-php/*
 * - Exclude pattern: --filter=!admin
 * - Changed workspaces: --filter=[HEAD^1]
 *
 * Advanced features:
 * - Remote caching (requires Vercel account)
 * - Task dependency graphs
 * - Dry-run mode for testing
 * - Custom concurrency limits
 * - Daemon management
 *
 * Features:
 * - Full access to Turbo CLI
 * - Support for all Turbo commands
 * - All Turbo options and flags
 * - Integrated error handling
 * - Consistent output formatting
 * - Workspace filtering
 *
 * Example usage:
 * ```bash
 * # Run tasks with Turbo directly
 * ./cli/bin/mono turbo run build --filter=api
 * ./cli/bin/mono turbo run test --force
 *
 * # Prune workspace for deployment
 * ./cli/bin/mono turbo prune --scope=api
 * ./cli/bin/mono turbo prune --scope=api --docker
 *
 * # Generate task dependency graph
 * ./cli/bin/mono turbo run build --graph
 * ./cli/bin/mono turbo run build --dry-run
 *
 * # Manage Turbo daemon
 * ./cli/bin/mono turbo daemon start
 * ./cli/bin/mono turbo daemon stop
 * ./cli/bin/mono turbo daemon status
 *
 * # Remote cache management
 * ./cli/bin/mono turbo login
 * ./cli/bin/mono turbo link
 * ./cli/bin/mono turbo unlink
 *
 * # Advanced run options
 * ./cli/bin/mono turbo run test --concurrency=2
 * ./cli/bin/mono turbo run build --output-logs=hash-only
 * ./cli/bin/mono turbo run lint --filter=[HEAD^1]
 *
 * # Multiple filters
 * ./cli/bin/mono turbo run build --filter=api --filter=admin
 *
 * # Using alias
 * ./cli/bin/mono tb run build
 * ```
 *
 * When to use this vs RunCommand:
 * - Use RunCommand for simple task execution
 * - Use TurboCommand for advanced Turbo features
 * - Use TurboCommand for non-run commands (prune, daemon, etc.)
 * - Use TurboCommand when you need specific Turbo flags
 *
 * @see BaseCommand For inherited functionality
 * @see RunCommand For simplified task execution
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 */
#[AsCommand(
    name: 'turbo',
    description: 'Run Turborepo command directly',
    aliases: ['tb'],
)]
final class TurboCommand extends BaseCommand
{
    /**
     * Configure the command options and arguments.
     *
     * Defines all command-line arguments and options that users can pass
     * to Turborepo. The command argument accepts an array to support
     * multi-word Turbo commands (e.g., "daemon start", "run build").
     *
     * Common options (workspace, force, no-cache, no-interaction) are inherited from BaseCommand.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument(
                'command',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'The Turbo command to run',
            )
            ->addOption(
                'filter',
                null,
                InputOption::VALUE_REQUIRED,
                'Filter to specific workspace(s)',
            )
            ->addOption(
                'parallel',
                'p',
                InputOption::VALUE_NONE,
                'Run tasks in parallel',
            )
            ->addOption(
                'continue',
                null,
                InputOption::VALUE_NONE,
                'Continue on error',
            )
            ->setHelp(
                <<<'HELP'
                The <info>turbo</info> command provides direct access to Turborepo.

                <comment>Examples:</comment>
                  <info>mono turbo run build --filter=api</info>
                  <info>mono turbo run test --force</info>
                  <info>mono turbo prune --scope=api</info>
                  <info>mono turbo run lint --no-cache</info>

                All Turbo options are supported. See Turbo documentation for details.
                HELP
            );
    }

    /**
     * Execute the turbo command.
     *
     * This method orchestrates direct Turbo command execution:
     * 1. Extracts the Turbo command from arguments
     * 2. Displays an intro message
     * 3. Builds Turbo options from command flags
     * 4. Executes the command via Turborepo
     * 5. Reports execution results
     *
     * This command acts as a passthrough to Turbo, allowing access to
     * all Turbo features including run, prune, daemon, link, and more.
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, non-zero for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Display intro banner
        $this->intro('Running Turbo command...');

        // Get the turbo command arguments
        // This supports multi-word commands like "daemon start"
        $commandArgs = $input->getArgument('command');
        $turboCommand = implode(' ', $commandArgs);

        // Build options array for Turbo execution
        // These options control how Turbo executes the command
        $options = [];

        // Filter to specific workspace(s)
        // Can be a single workspace or pattern
        $filter = $input->getOption('filter');
        if (! in_array($filter, [null, false, ''], true)) {
            $options['filter'] = $filter;
        }

        // Force execution (bypass cache)
        // Useful when you need to ensure command runs regardless of cache state
        if ($input->getOption('force') === true) {
            $options['force'] = true;
        }

        // Disable caching entirely
        // Different from force - this prevents cache reads and writes
        if ($input->getOption('no-cache') === true) {
            $options['cache'] = false;
        }

        // Enable parallel execution
        // Forces parallel mode even for commands that might run sequentially
        if ($input->getOption('parallel') === true) {
            $options['parallel'] = true;
        }

        // Continue on error
        // Don't stop on first failure - run all commands
        if ($input->getOption('continue') === true) {
            $options['continue'] = true;
        }

        // Display the command being executed
        $this->info("Running: turbo {$turboCommand}");

        // Display options if any were set
        // This helps users understand what Turbo is doing
        if ($options !== []) {
            $this->comment('Options: ' . json_encode($options));
        }

        $this->line('');

        // Run turbo command
        // This executes the Turbo binary with the specified command and options
        $exitCode = $this->turboRun($turboCommand, $options);

        // Report results to user
        if ($exitCode === 0) {
            // Success - command completed without errors
            $this->outro('✓ Turbo command completed successfully');
        } else {
            // Failure - command encountered errors
            $this->error('✗ Turbo command failed');
        }

        return $exitCode;
    }
}
