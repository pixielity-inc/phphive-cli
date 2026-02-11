<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Workspace;

use function array_column;

use MonoPhp\Cli\Commands\BaseCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Info Command.
 *
 * This command displays comprehensive information about a specific workspace
 * in the monorepo. It provides a detailed view of workspace configuration,
 * dependencies, and available scripts from both Composer and npm/pnpm ecosystems.
 *
 * The command gathers information from multiple sources:
 * 1. Workspace metadata (name, type, path)
 * 2. composer.json (PHP dependencies, autoload, scripts)
 * 3. package.json (npm dependencies, scripts, version)
 * 4. File system (directory structure, file presence)
 *
 * Information displayed:
 * - Basic metadata (name, type, package name, path)
 * - Composer availability and configuration
 * - Package.json availability and configuration
 * - Production dependencies (require/dependencies)
 * - Development dependencies (require-dev/devDependencies)
 * - Available scripts (composer scripts, npm scripts)
 * - Package description and license information
 *
 * Features:
 * - Interactive workspace selection if none specified
 * - Organized sections for different config sources
 * - Dependency version display
 * - Script listing for quick reference
 * - Clear visual formatting with colors
 * - Validation of workspace existence
 *
 * Example usage:
 * ```bash
 * # Show info for specific workspace
 * ./cli/bin/mono info api
 *
 * # Show info for calculator workspace
 * ./cli/bin/mono info calculator
 *
 * # Interactive selection (no workspace specified)
 * ./cli/bin/mono info
 *
 * # Using alias
 * ./cli/bin/mono show demo-app
 * ./cli/bin/mono details demo-app
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithMonorepo For workspace discovery
 * @see ListCommand For viewing all workspaces
 */
#[AsCommand(
    name: 'info',
    description: 'Show detailed workspace information',
    aliases: ['show', 'details'],
)]
final class InfoCommand extends BaseCommand
{
    /**
     * Configure the command options and arguments.
     *
     * Defines the workspace argument that allows users to specify which
     * workspace to display information for. If not provided, the command
     * will prompt for interactive selection.
     *
     * Common options (workspace, force, no-cache, no-interaction) are inherited from BaseCommand.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument(
                'workspace',
                InputArgument::OPTIONAL,
                'Workspace name to show info for',
            )
            ->setHelp(
                <<<'HELP'
                The <info>info</info> command shows detailed workspace information.

                <comment>Examples:</comment>
                  <info>mono info api</info>          Show info for 'api' workspace
                  <info>mono info calculator</info>   Show info for 'calculator' workspace

                If no workspace is specified, you'll be prompted to select one.
                HELP
            );
    }

    /**
     * Execute the info command.
     *
     * This method performs the following steps:
     * 1. Gets workspace name from argument or prompts for selection
     * 2. Validates workspace exists in the monorepo
     * 3. Displays basic workspace metadata
     * 4. Shows Composer configuration if available
     * 5. Shows package.json configuration if available
     * 6. Reports completion status
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get workspace name from argument or prompt for selection
        // This allows both direct specification and interactive selection
        $workspaceName = $input->getArgument('workspace');

        if ($workspaceName === null || $workspaceName === '') {
            // No workspace specified - discover all workspaces
            $workspaces = $this->getWorkspaces();

            // Validate that workspaces exist in the monorepo
            if ($workspaces === []) {
                $this->error('No workspaces found');

                return Command::FAILURE;
            }

            // Prompt user to select a workspace interactively
            $workspaceName = $this->select(
                'Select workspace',
                array_column($workspaces, 'name'),
            );
        }

        // Retrieve detailed workspace information
        // This includes metadata from package.json and composer.json
        $workspace = $this->getWorkspace($workspaceName);

        // Validate workspace exists
        if ($workspace === null || $workspace === []) {
            $this->error("Workspace '{$workspaceName}' not found");

            return Command::FAILURE;
        }

        // Display intro banner with workspace name
        $this->intro("Workspace Information: {$workspace['name']}");
        $this->line('');

        // Display basic workspace metadata
        // Shows name, type, package name, path, and config file presence
        $this->displayBasicInfo($workspace);
        $this->line('');

        // Display Composer configuration if composer.json exists
        // Shows dependencies, dev dependencies, and package metadata
        if ($workspace['hasComposer'] === true) {
            $this->displayComposerInfo($workspace);
            $this->line('');
        }

        // Display package.json configuration if it exists
        // Shows npm dependencies, scripts, and package metadata
        if ($workspace['hasPackageJson'] === true) {
            $this->displayPackageInfo($workspace);
            $this->line('');
        }

        // Display success message
        $this->outro('✓ Information displayed');

        return Command::SUCCESS;
    }

    /**
     * Display basic workspace information.
     *
     * Shows core metadata including name, type, package name, path,
     * and whether Composer and package.json files are present.
     *
     * @param array $workspace Workspace metadata array
     */
    private function displayBasicInfo(array $workspace): void
    {
        $this->info('Basic Information:');
        $this->line("  Name: {$workspace['name']}");
        $this->line("  Type: {$workspace['type']}");
        $this->line("  Package Name: {$workspace['packageName']}");
        $this->line("  Path: {$workspace['path']}");
        $this->line('  Has Composer: ' . ($workspace['hasComposer'] ? 'Yes' : 'No'));
        $this->line('  Has Package.json: ' . ($workspace['hasPackageJson'] ? 'Yes' : 'No'));
    }

    /**
     * Display Composer configuration.
     *
     * Reads composer.json and displays package metadata, production
     * dependencies (require), and development dependencies (require-dev).
     *
     * @param array $workspace Workspace metadata array
     */
    private function displayComposerInfo(array $workspace): void
    {
        $composerJson = $this->getWorkspaceComposerJson($workspace['name']);

        if ($composerJson === null || $composerJson === []) {
            return;
        }

        $this->info('Composer Configuration:');

        if (isset($composerJson['description'])) {
            $this->line("  Description: {$composerJson['description']}");
        }

        if (isset($composerJson['type'])) {
            $this->line("  Type: {$composerJson['type']}");
        }

        if (isset($composerJson['license'])) {
            $this->line("  License: {$composerJson['license']}");
        }

        // Dependencies
        if (isset($composerJson['require']) && count($composerJson['require']) > 0) {
            $this->line('');
            $this->comment('  Dependencies:');

            foreach ($composerJson['require'] as $package => $version) {
                $this->line("    - {$package}: {$version}");
            }
        }

        // Dev dependencies
        if (isset($composerJson['require-dev']) && count($composerJson['require-dev']) > 0) {
            $this->line('');
            $this->comment('  Dev Dependencies:');

            foreach ($composerJson['require-dev'] as $package => $version) {
                $this->line("    - {$package}: {$version}");
            }
        }
    }

    /**
     * Display package.json configuration.
     *
     * Reads package.json and displays version, description, and available
     * npm/pnpm scripts that can be executed in the workspace.
     *
     * @param array $workspace Workspace metadata array
     */
    private function displayPackageInfo(array $workspace): void
    {
        $packageJson = $this->getWorkspacePackageJson($workspace['name']);

        if ($packageJson === null || $packageJson === []) {
            return;
        }

        $this->info('Package Configuration:');

        if (isset($packageJson['version'])) {
            $this->line("  Version: {$packageJson['version']}");
        }

        if (isset($packageJson['description'])) {
            $this->line("  Description: {$packageJson['description']}");
        }

        // Scripts
        if (isset($packageJson['scripts']) && count($packageJson['scripts']) > 0) {
            $this->line('');
            $this->comment('  Available Scripts:');

            foreach ($packageJson['scripts'] as $script => $command) {
                $this->line("    - {$script}");
            }
        }
    }
}
