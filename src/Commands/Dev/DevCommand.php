<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Dev;

use function array_column;
use function count;

use MonoPhp\Cli\Commands\BaseCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Dev Command.
 *
 * This command starts the development server for an application workspace.
 * It provides an interactive workspace selector if no workspace is specified,
 * making it easy to start development with a single command. The command
 * leverages Turborepo to execute the dev task with proper workspace filtering.
 *
 * The development process:
 * 1. Identifies available application workspaces
 * 2. Prompts user to select if multiple apps exist
 * 3. Auto-selects if only one app exists
 * 4. Validates workspace exists
 * 5. Starts the dev server via Turbo
 * 6. Streams output in real-time
 *
 * Development workflow:
 * - Discovers all application workspaces (excludes packages)
 * - Provides interactive selection for multiple apps
 * - Auto-selects when only one app is available
 * - Validates workspace before starting server
 * - Executes dev script from workspace's package.json
 * - Streams server output to console in real-time
 *
 * Features:
 * - Interactive workspace selection
 * - Auto-selection for single app
 * - Workspace validation
 * - Custom port support (future)
 * - Real-time output streaming
 * - Graceful error handling
 * - Hot reload support (via workspace dev script)
 * - Automatic dependency watching
 *
 * Common options inherited from BaseCommand:
 * - --workspace, -w: Target specific workspace
 * - --force, -f: Force operation by ignoring cache
 * - --no-cache: Disable Turbo cache
 * - --no-interaction, -n: Run in non-interactive mode
 *
 * Example usage:
 * ```bash
 * # Start dev server (interactive selection)
 * ./cli/bin/mono dev
 *
 * # Start specific app
 * ./cli/bin/mono dev --workspace demo-app
 *
 * # Start with shorthand
 * ./cli/bin/mono dev -w calculator
 *
 * # Start with custom port (future)
 * ./cli/bin/mono dev --workspace demo-app --port 3000
 * ```
 *
 * @see BaseCommand For inherited functionality and common options
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see InteractsWithPrompts For interactive selection
 * @see BuildCommand For production builds
 */
#[AsCommand(
    name: 'dev',
    description: 'Start development server',
)]
final class DevCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Inherits common options from BaseCommand (workspace, force, no-cache, no-interaction)
     * and adds command-specific options for development server customization.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'port',
            'p',
            InputOption::VALUE_REQUIRED,
            'Custom port number (future feature)',
        );
    }

    /**
     * Execute the dev command.
     *
     * This method orchestrates the development server startup:
     * 1. Gets workspace from option or prompts for selection
     * 2. Discovers available application workspaces
     * 3. Auto-selects if only one app exists
     * 4. Provides interactive selection for multiple apps
     * 5. Validates the selected workspace exists
     * 6. Starts the dev server via Turbo
     * 7. Streams output to console in real-time
     *
     * The dev task executes the 'dev' script from the workspace's package.json,
     * which typically starts a development server with hot reload capabilities.
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get workspace from option if provided
        $workspaceOption = $this->option('workspace');
        $workspace = is_string($workspaceOption) && $workspaceOption !== '' ? $workspaceOption : null;

        // If no workspace specified, provide interactive selection
        if ($workspace === null) {
            // Get all application workspaces (not packages)
            $apps = $this->getApps();

            // Check if any apps exist
            if ($apps === []) {
                $this->error('No apps found in the monorepo');

                return Command::FAILURE;
            }

            // Auto-select if only one app exists
            if (count($apps) === 1) {
                $workspace = $apps[0]['name'];
                $this->info("Starting {$workspace}...");
            } else {
                // Multiple apps - let user choose
                $workspace = $this->select(
                    'Select app to run',
                    array_column($apps, 'name'),
                );
            }
        }

        // Verify the selected workspace exists
        if (! $this->hasWorkspace($workspace)) {
            $this->error("Workspace '{$workspace}' not found");

            return Command::FAILURE;
        }

        // Display intro banner
        $this->intro("Starting Development Server: {$workspace}");

        // Run the dev task via Turbo with workspace filter
        // Turbo will execute the 'dev' script from the workspace's package.json
        // Output is streamed in real-time to the console
        $exitCode = $this->turboRun('dev', [
            'filter' => $workspace,  // Only run for this workspace
        ]);

        // Return appropriate exit code
        // 0 = success, non-zero = failure
        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
