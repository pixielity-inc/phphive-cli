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
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Update Command.
 *
 * This command updates Composer dependencies in workspaces within the monorepo.
 * It provides a convenient interface for keeping packages up-to-date without
 * manually navigating to workspace directories or running Composer commands
 * directly. The command supports both full dependency updates and targeted
 * single-package updates.
 *
 * The update process follows Composer's standard update workflow:
 * 1. Reads composer.json to determine current constraints
 * 2. Checks for newer versions matching constraints
 * 3. Resolves dependency tree with new versions
 * 4. Downloads and installs updated packages
 * 5. Updates composer.lock with new versions
 *
 * Features:
 * - Update all dependencies in a workspace
 * - Update specific package only
 * - Interactive workspace selection if not specified
 * - Workspace validation before update
 * - Respects version constraints in composer.json
 * - Automatic composer.lock updates
 * - Real-time update progress
 * - Dependency conflict detection
 *
 * Update strategies:
 * - Full update: Updates all packages to latest allowed versions
 * - Targeted update: Updates only specified package and its dependencies
 * - Respects semantic versioning constraints (^, ~, etc.)
 * - Maintains compatibility with other packages
 *
 * Workflow:
 * 1. Selects or validates target workspace
 * 2. Determines update scope (all or specific package)
 * 3. Runs composer update in workspace directory
 * 4. Resolves and installs updated dependencies
 * 5. Updates composer.lock file
 * 6. Reports update success or failure
 *
 * Example usage:
 * ```bash
 * # Update all dependencies in selected workspace
 * ./cli/bin/mono update
 *
 * # Update all dependencies in specific workspace
 * ./cli/bin/mono update --workspace=api
 *
 * # Update specific package only
 * ./cli/bin/mono update symfony/console
 *
 * # Update specific package in specific workspace
 * ./cli/bin/mono update guzzlehttp/guzzle -w api
 *
 * # Using aliases
 * ./cli/bin/mono up symfony/http-client -w demo-app
 * ./cli/bin/mono upgrade --workspace=calculator
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithComposer For Composer integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see RequireCommand For adding new packages
 * @see ComposerCommand For direct Composer access
 */
#[AsCommand(
    name: 'update',
    description: 'Update Composer dependencies in a workspace',
    aliases: ['up', 'upgrade'],
)]
final class UpdateCommand extends BaseCommand
{
    /**
     * Configure the command options and arguments.
     *
     * Defines the command signature with optional package argument for targeted
     * updates. Common options like --workspace are inherited from BaseCommand.
     * If no package is specified, all dependencies will be updated.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure(); // Inherit common options from BaseCommand

        $this
            ->addArgument(
                'package',
                InputArgument::OPTIONAL,
                'Specific package to update (optional)',
            )
            ->setHelp(
                <<<'HELP'
                The <info>update</info> command updates Composer dependencies.

                <comment>Examples:</comment>
                  <info>mono update</info>                    Update all dependencies
                  <info>mono update symfony/console</info>    Update specific package
                  <info>mono update --workspace=api</info>    Update in specific workspace

                If no workspace is specified, you'll be prompted to select one.
                HELP
            );
    }

    /**
     * Execute the update command.
     *
     * This method orchestrates the dependency update process:
     * 1. Extracts package name (if specified) and options from user input
     * 2. Selects target workspace (interactive if not specified)
     * 3. Validates workspace exists
     * 4. Displays update details (full or targeted)
     * 5. Runs composer update in workspace directory
     * 6. Reports update results
     *
     * The command uses the composerUpdate() method from InteractsWithComposer
     * trait which handles the actual Composer execution with proper error handling
     * and output streaming. If a package is specified, only that package and its
     * dependencies are updated; otherwise all dependencies are updated.
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Display intro banner
        $this->intro('Updating Composer dependencies...');

        // Extract package name from arguments (optional)
        $package = $input->getArgument('package');

        // Get or select workspace
        $workspace = $input->getOption('workspace');

        if (! $workspace) {
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
        }

        // Verify workspace exists
        if (! $this->hasWorkspace($workspace)) {
            $this->error("Workspace '{$workspace}' not found");

            return Command::FAILURE;
        }

        // Display update details
        if ($package) {
            // Targeted update - specific package only
            $this->info("Updating package: {$package}");
        } else {
            // Full update - all dependencies
            $this->info('Updating all dependencies');
        }

        $this->comment("Workspace: {$workspace}");
        $this->line('');

        // Run composer update in workspace directory
        // This will update composer.lock and install new versions
        $exitCode = $this->composerUpdate($workspace, $package);

        // Report results to user
        if ($exitCode === 0) {
            // Success - dependencies updated
            if ($package) {
                $this->outro("✓ Package '{$package}' updated successfully");
            } else {
                $this->outro('✓ Dependencies updated successfully');
            }
        } else {
            // Failure - update failed
            $this->error('✗ Update failed');
        }

        return $exitCode;
    }
}
