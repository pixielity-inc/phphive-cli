<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Composer;

use function array_column;

use MonoPhp\Cli\Commands\BaseCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Require Command.
 *
 * This command adds Composer package dependencies to workspaces in the monorepo.
 * It provides a convenient interface for installing packages without manually
 * editing composer.json files or navigating to workspace directories.
 *
 * The command wraps Composer's `require` command and adds monorepo-specific
 * features like workspace selection and validation. It supports both production
 * and development dependencies through the --dev flag.
 *
 * Features:
 * - Interactive workspace selection if not specified
 * - Workspace validation before installation
 * - Support for production and dev dependencies
 * - Version constraint support (e.g., symfony/console:^7.0)
 * - Real-time installation progress
 * - Automatic composer.json updates
 * - Dependency conflict detection
 *
 * Workflow:
 * 1. Validates package name format
 * 2. Selects or validates target workspace
 * 3. Runs composer require in workspace directory
 * 4. Updates composer.lock automatically
 * 5. Reports installation success/failure
 *
 * Example usage:
 * ```bash
 * # Add production dependency
 * ./cli/bin/mono require symfony/console
 *
 * # Add dev dependency
 * ./cli/bin/mono require phpunit/phpunit --dev
 *
 * # Add to specific workspace
 * ./cli/bin/mono require guzzlehttp/guzzle --workspace=api
 *
 * # Add with version constraint
 * ./cli/bin/mono require symfony/http-client:^7.0 -w api
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithComposer For Composer integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see UpdateCommand For updating existing packages
 * @see ComposerCommand For direct Composer access
 */
#[AsCommand(
    name: 'require',
    description: 'Add a Composer package to a workspace',
    aliases: ['req', 'add'],
)]
final class RequireCommand extends BaseCommand
{
    /**
     * Configure the command options and arguments.
     *
     * Defines all command-line options that users can pass to customize
     * the package installation behavior. The package argument is required,
     * while the dev option is command-specific. Common options like --workspace
     * are inherited from BaseCommand.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure(); // Inherit common options from BaseCommand

        $this
            ->addArgument(
                'package',
                InputArgument::REQUIRED,
                'The package to require (e.g., symfony/console:^7.0)',
            )
            ->addOption(
                'dev',
                'd',
                InputOption::VALUE_NONE,
                'Add as a development dependency',
            )
            ->setHelp(
                <<<'HELP'
                The <info>require</info> command adds a Composer package to a workspace.

                <comment>Examples:</comment>
                  <info>mono require symfony/console</info>
                  <info>mono require phpunit/phpunit --dev</info>
                  <info>mono require guzzlehttp/guzzle:^7.0 --workspace=api</info>

                If no workspace is specified, you'll be prompted to select one.
                HELP
            );
    }

    /**
     * Execute the require command.
     *
     * This method orchestrates the package installation process:
     * 1. Extracts package name and options from user input
     * 2. Selects target workspace (interactive if not specified)
     * 3. Validates workspace exists
     * 4. Displays installation details
     * 5. Runs composer require in workspace directory
     * 6. Reports installation results
     *
     * The command uses the composerRequire() method from InteractsWithComposer
     * trait which handles the actual Composer execution with proper error handling
     * and output streaming.
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Display intro banner
        $this->intro('Adding Composer package...');

        // Extract package name from arguments
        $package = $input->getArgument('package');

        // Check if this is a dev dependency
        $isDev = $input->getOption('dev');

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

        // Display installation details
        $this->info("Adding package: {$package}");
        $this->comment("Workspace: {$workspace}");
        $this->comment('Type: ' . ((in_array($isDev, [true, '1', 1], true)) ? 'development' : 'production'));
        $this->line('');

        // Run composer require in workspace directory
        // This will update composer.json and install the package
        $exitCode = $this->composerRequire($workspace, $package, $isDev);

        // Report results to user
        if ($exitCode === 0) {
            // Success - package installed
            $this->outro("✓ Package '{$package}' added successfully");
        } else {
            // Failure - installation failed
            $this->error("✗ Failed to add package '{$package}'");
        }

        return $exitCode;
    }
}
