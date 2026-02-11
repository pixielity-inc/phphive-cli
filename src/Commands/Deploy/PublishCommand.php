<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Deploy;

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
 * Publish Command.
 *
 * This command publishes packages to package registries (e.g., Packagist for
 * Composer packages, npm for JavaScript packages). It provides a safe, interactive
 * workflow with confirmation prompts and dry-run support to prevent accidental
 * publishes of private or unfinished packages.
 *
 * The publishing process:
 * 1. Discovers all packages in the monorepo (excludes apps)
 * 2. Prompts user to select a package if not specified
 * 3. Validates that the workspace is actually a package (not an app)
 * 4. Shows what will be published (package name, version tag)
 * 5. Requires explicit user confirmation (safety measure)
 * 6. Executes publish script via Turbo
 * 7. Reports success or failure
 *
 * Features:
 * - Interactive package selection from available packages
 * - Package validation (prevents publishing apps)
 * - Version tag support (latest, beta, alpha, rc, etc.)
 * - Dry-run mode (simulate without actually publishing)
 * - Explicit confirmation required (prevents accidents)
 * - Integration with Turbo for caching and task management
 * - Detailed progress reporting
 * - Automatic workspace filtering
 *
 * Publishing workflow (recommended):
 * 1. Ensure package is built and tested (run tests first)
 * 2. Update version in composer.json (follow semver)
 * 3. Update CHANGELOG.md with release notes
 * 4. Run this command to publish to registry
 * 5. Tag the release in git (git tag v1.0.0)
 * 6. Push tags to remote (git push --tags)
 * 7. Create GitHub release with notes
 *
 * Version tags explained:
 * - latest: Stable production release (default)
 * - beta: Pre-release for testing (e.g., 2.0.0-beta.1)
 * - alpha: Early pre-release (e.g., 2.0.0-alpha.1)
 * - rc: Release candidate (e.g., 2.0.0-rc.1)
 * - next: Upcoming major version
 *
 * Safety features:
 * - Confirmation prompt prevents accidental publishes
 * - Dry-run mode allows testing without publishing
 * - Package-only validation prevents app publishing
 * - Workspace validation ensures package exists
 *
 * Example usage:
 * ```bash
 * # Publish with interactive selection
 * ./cli/bin/mono publish
 *
 * # Publish specific package
 * ./cli/bin/mono publish --workspace calculator
 *
 * # Publish with custom tag (beta release)
 * ./cli/bin/mono publish -w calculator -t beta
 *
 * # Publish alpha version
 * ./cli/bin/mono publish -w calculator --tag alpha
 *
 * # Dry run (test without publishing)
 * ./cli/bin/mono publish -w calculator --dry-run
 *
 * # Publish release candidate
 * ./cli/bin/mono publish -w calculator -t rc
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see InteractsWithPrompts For interactive selection
 * @see DeployCommand For app deployment (different from package publishing)
 */
#[AsCommand(
    name: 'publish',
    description: 'Publish packages to registry',
)]
final class PublishCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Inherits common options from BaseCommand (workspace, force, no-cache, no-interaction).
     * Defines additional command-specific options for publishing behavior.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure(); // Inherit common options from BaseCommand

        $this->addOption(
            'tag',
            't',
            InputOption::VALUE_REQUIRED,
            'Version tag (e.g., latest, beta, alpha, rc)',
        );

        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Simulate publish without actually publishing (test mode)',
        );
    }

    /**
     * Execute the publish command.
     *
     * This method orchestrates the entire publishing process:
     * 1. Discovers all packages in the monorepo
     * 2. Validates that packages exist
     * 3. Prompts for package selection if needed
     * 4. Validates the selected workspace is a package (not an app)
     * 5. Shows publish details (package, tag, dry-run status)
     * 6. Requires explicit user confirmation (safety measure)
     * 7. Executes the publish via Turbo with workspace filtering
     * 8. Reports success or failure
     *
     * The publish task is executed via Turbo which handles task dependencies,
     * caching, and parallel execution. The task should be defined in turbo.json
     * with appropriate dependencies (e.g., build, test, lint).
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Extract options from user input
        $workspace = $this->option('workspace');
        $tag = $this->option('tag') ?? 'latest'; // Default to 'latest' tag
        $dryRun = $this->hasOption('dry-run');

        // Display intro banner
        $this->intro('Publishing Packages');

        // Get all packages (excludes apps)
        // Packages are in packages/* directory
        $packages = $this->getPackages();

        // Validate that packages exist in monorepo
        if ($packages === []) {
            $this->error('No packages found in the monorepo');

            return Command::FAILURE;
        }

        // If no workspace specified, prompt user to select one
        if (! $workspace) {
            if (count($packages) === 1) {
                // Only one package - auto-select it
                $workspace = $packages[0]['name'];
            } else {
                // Multiple packages - show interactive selection
                $workspace = $this->select(
                    'Select package to publish',
                    array_column($packages, 'name'),
                );
            }
        }

        // Verify the selected workspace is actually a package
        // This prevents accidentally trying to publish an app
        $package = $this->getWorkspace($workspace);
        if (! $package || $package['type'] !== 'package') {
            $this->error("'{$workspace}' is not a package");

            return Command::FAILURE;
        }

        // Show what will be published
        $this->info("Publishing package: {$workspace}");
        $this->info("Tag: {$tag}");

        // Show dry-run warning if enabled
        if ($dryRun) {
            $this->warning('DRY RUN MODE - No actual publishing will occur');
        }

        // Require explicit confirmation from user
        // Publishing is a critical operation that can't be undone
        $confirmed = $this->confirm(
            'Are you sure you want to publish?',
            default: false, // Default to NO for safety
        );

        // User cancelled - exit gracefully
        if (! $confirmed) {
            $this->info('Publish cancelled');

            return Command::SUCCESS;
        }

        // User confirmed - proceed with publish
        // Run the publish task via Turbo
        // Filter to specific package only
        $exitCode = $this->turboRun('publish', [
            'filter' => $workspace,
        ]);

        // Report results to user
        if ($exitCode === 0) {
            // Success - package published
            $this->outro('✓ Package published successfully!');
        } else {
            // Failure - publish failed
            $this->error('✗ Publish failed');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
