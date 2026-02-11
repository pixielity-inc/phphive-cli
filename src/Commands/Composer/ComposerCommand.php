<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Composer;

use function array_column;
use function implode;

use MonoPhp\Cli\Commands\BaseCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Composer Command.
 *
 * This command provides direct access to Composer commands within workspace contexts.
 * It acts as a passthrough wrapper that allows running any Composer command in a
 * specific workspace without manually navigating to workspace directories or managing
 * multiple composer.json files across the monorepo.
 *
 * The command forwards all arguments directly to Composer, preserving all flags,
 * options, and behavior of the underlying Composer command. This makes it a flexible
 * tool for any Composer operation that isn't covered by specialized commands like
 * require or update.
 *
 * Features:
 * - Run any Composer command in workspace context
 * - Interactive workspace selection if not specified
 * - Workspace validation before execution
 * - Full argument passthrough to Composer
 * - Support for all Composer flags and options
 * - Real-time command output streaming
 * - Automatic working directory management
 *
 * Common use cases:
 * - Show installed packages (composer show)
 * - Dump autoloader (composer dump-autoload)
 * - Validate composer.json (composer validate)
 * - Check for security issues (composer audit)
 * - Remove packages (composer remove)
 * - Run custom scripts (composer run-script)
 *
 * Workflow:
 * 1. Accepts any Composer command as arguments
 * 2. Selects or validates target workspace
 * 3. Changes to workspace directory
 * 4. Executes Composer with provided arguments
 * 5. Streams output in real-time
 * 6. Reports success or failure
 *
 * Example usage:
 * ```bash
 * # Show installed packages
 * ./cli/bin/mono composer show --installed
 *
 * # Dump optimized autoloader
 * ./cli/bin/mono composer dump-autoload -o --workspace=api
 *
 * # Validate composer.json
 * ./cli/bin/mono composer validate -w calculator
 *
 * # Check for security vulnerabilities
 * ./cli/bin/mono composer audit
 *
 * # Remove a package
 * ./cli/bin/mono composer remove symfony/console -w demo-app
 *
 * # Run custom script
 * ./cli/bin/mono composer run-script post-install
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithComposer For Composer integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see RequireCommand For adding packages
 * @see UpdateCommand For updating packages
 */
#[AsCommand(
    name: 'composer',
    description: 'Run Composer command in a workspace',
    aliases: ['comp'],
)]
final class ComposerCommand extends BaseCommand
{
    /**
     * Configure the command options and arguments.
     *
     * Defines the command signature with flexible argument handling to accept
     * any Composer command and its options. Common options like --workspace
     * are inherited from BaseCommand.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure(); // Inherit common options from BaseCommand

        $this
            ->addArgument(
                'command',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'The Composer command to run',
            )
            ->setHelp(
                <<<'HELP'
                The <info>composer</info> command runs Composer commands in workspace contexts.

                <comment>Examples:</comment>
                  <info>mono composer require symfony/console</info>
                  <info>mono composer update --workspace=api</info>
                  <info>mono composer show --installed</info>
                  <info>mono composer dump-autoload -o</info>

                If no workspace is specified, you'll be prompted to select one.
                HELP
            );
    }

    /**
     * Execute the composer command.
     *
     * This method orchestrates the Composer command execution:
     * 1. Extracts command arguments from user input
     * 2. Selects target workspace (interactive if not specified)
     * 3. Validates workspace exists
     * 4. Displays execution details
     * 5. Runs Composer command in workspace directory
     * 6. Reports execution results
     *
     * The command uses the composer() method from InteractsWithComposer trait
     * which handles the actual Composer execution with proper working directory
     * management and output streaming.
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Display intro banner
        $this->intro('Running Composer command...');

        // Get the composer command arguments
        // These are passed as an array and need to be joined
        $commandArgs = $input->getArgument('command');
        $composerCommand = implode(' ', $commandArgs);

        // Get or select workspace
        $workspace = $input->getOption('workspace');

        if (! is_string($workspace) || $workspace === '') {
            // No workspace specified - prompt user to select one
            $workspaces = $this->getWorkspaces();

            if ($workspaces === []) {
                // No workspaces found in monorepo
                $this->error('No workspaces found');

                return Command::FAILURE;
            }

            // Interactive workspace selection
            $workspace = $this->select(
                'Select workspace',
                array_column($workspaces, 'name'),
            );

            // Ensure workspace is a string after selection
            if (! is_string($workspace)) {
                $this->error('Invalid workspace selection');

                return Command::FAILURE;
            }
        }

        // Verify workspace exists
        if (! $this->hasWorkspace($workspace)) {
            $this->error("Workspace '{$workspace}' not found");

            return Command::FAILURE;
        }

        // Get the full path to the workspace directory
        $workspacePath = $this->getWorkspacePath($workspace);

        // Display execution details
        $this->info("Running: composer {$composerCommand}");
        $this->comment("Workspace: {$workspace}");
        $this->line('');

        // Run composer command in workspace directory
        // This executes Composer with the provided arguments in the workspace context
        $exitCode = $this->composer($composerCommand, $workspacePath);

        // Report results to user
        if ($exitCode === 0) {
            // Success - command completed
            $this->outro('✓ Composer command completed successfully');
        } else {
            // Failure - command failed
            $this->error('✗ Composer command failed');
        }

        return $exitCode;
    }
}
