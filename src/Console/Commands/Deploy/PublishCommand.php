<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Deploy;

use Override;
use PhpHive\Cli\Console\Commands\BaseCommand;
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
 * hive publish
 *
 * # Publish specific package
 * hive publish --workspace calculator
 *
 * # Publish with custom tag (beta release)
 * hive publish -w calculator -t beta
 *
 * # Publish alpha version
 * hive publish -w calculator --tag alpha
 *
 * # Dry run (test without publishing)
 * hive publish -w calculator --dry-run
 *
 * # Publish release candidate
 * hive publish -w calculator -t rc
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see InteractsWithPrompts For interactive selection
 * @see DeployCommand For app deployment (different from package publishing)
 */
#[AsCommand(
    name: 'deploy:publish',
    description: 'Publish packages to registry',
    aliases: ['publish'],
)]
final class PublishCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Inherits common options from BaseCommand (workspace, force, no-cache, no-interaction).
     * Defines additional command-specific options for publishing behavior.
     *
     * Options defined:
     * - --tag (-t): Version tag for the publish (latest, beta, alpha, rc, next)
     * - --dry-run: Test mode that simulates publish without actually publishing
     * - --json (-j): Machine-readable JSON output for CI/CD integration
     * - --summary (-s): Concise summary output format
     *
     * The tag option controls which distribution channel the package is published to:
     * - latest: Stable production release (default, recommended for most users)
     * - beta: Pre-release for testing (e.g., 2.0.0-beta.1)
     * - alpha: Early pre-release (e.g., 2.0.0-alpha.1)
     * - rc: Release candidate (e.g., 2.0.0-rc.1)
     * - next: Upcoming major version
     *
     * The dry-run option is useful for:
     * - Testing publish configuration before actual publish
     * - Validating package contents
     * - Checking credentials and permissions
     * - CI/CD pipeline testing
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure(); // Inherit common options from BaseCommand

        // Version tag option (controls distribution channel)
        $this->addOption(
            'tag',
            't',
            InputOption::VALUE_REQUIRED,
            'Version tag (e.g., latest, beta, alpha, rc)',
        );

        // Dry-run option (test mode without actual publishing)
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Simulate publish without actually publishing (test mode)',
        );

        // JSON output option (for CI/CD integration)
        $this->addOption(
            'json',
            'j',
            InputOption::VALUE_NONE,
            'Output as JSON (non-interactive mode only)',
        );

        // Summary output option (concise format)
        $this->addOption(
            'summary',
            's',
            InputOption::VALUE_NONE,
            'Output concise summary',
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
     * Execution flow details:
     * - Option parsing: Extracts workspace, tag, dry-run, and output format options
     * - Package discovery: Uses getPackages() to find all publishable packages
     * - Interactive selection: Prompts user if workspace not specified (interactive mode only)
     * - Validation: Ensures selected workspace is a package (not an app)
     * - Confirmation: Requires explicit user approval before publishing (safety gate)
     * - Execution: Runs 'publish' task via Turbo with workspace filtering
     * - Reporting: Displays results in requested format (default, JSON, or summary)
     *
     * Safety measures:
     * - Confirmation defaults to NO (user must explicitly approve)
     * - Dry-run mode available for testing
     * - Package-only validation prevents accidental app publishing
     * - Non-interactive mode requires workspace to be specified
     *
     * Output formats:
     * - Default: Colored, user-friendly messages with progress indicators
     * - JSON: Machine-readable format with status, workspace, tag, duration, timestamp
     * - Summary: Concise text format with key information
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // =====================================================================
        // PARSE OPTIONS AND INITIALIZE
        // =====================================================================

        // Extract workspace option (which package to publish)
        $workspaceOption = $this->option('workspace');
        $workspace = is_string($workspaceOption) && $workspaceOption !== '' ? $workspaceOption : null;

        // Extract tag option (distribution channel: latest, beta, alpha, rc)
        $tagOption = $this->option('tag');
        $tag = is_string($tagOption) && $tagOption !== '' ? $tagOption : 'latest'; // Default to 'latest' tag

        // Extract behavior flags
        $dryRun = $this->hasOption('dry-run');           // Test mode without actual publishing
        $jsonOutput = $this->hasOption('json');          // Machine-readable output
        $summaryOutput = $this->hasOption('summary');    // Concise text output
        $isInteractive = ! $this->hasOption('no-interaction'); // Interactive mode enabled

        // Track start time for duration calculation
        $startTime = microtime(true);

        // Display intro banner (skip for structured output formats)
        if (! $jsonOutput && ! $summaryOutput) {
            $this->intro('Publishing Packages');
        }

        // =====================================================================
        // DISCOVER AND VALIDATE PACKAGES
        // =====================================================================

        // Get all packages from monorepo (excludes apps)
        // Uses getPackages() which filters workspaces by type='package'
        $packages = $this->getPackages();

        // Validate that packages exist in monorepo
        if ($packages->isEmpty()) {
            if ($jsonOutput) {
                $this->outputJson([
                    'status' => 'error',
                    'message' => 'No packages found in the monorepo',
                    'timestamp' => date('c'),
                ]);
            } else {
                $this->error('No packages found in the monorepo');
            }

            return Command::FAILURE;
        }

        // =====================================================================
        // WORKSPACE SELECTION (INTERACTIVE OR SPECIFIED)
        // =====================================================================

        // If no workspace specified, prompt user to select one (only in interactive mode)
        if ($workspace === null) {
            if ($isInteractive && ! $jsonOutput && ! $summaryOutput) {
                if ($packages->count() === 1) {
                    // Only one package exists - auto-select it for convenience
                    $workspace = $packages->first()['name'];
                } else {
                    // Multiple packages exist - show interactive selection menu
                    // Uses pluck() to extract just the 'name' field from each package
                    $workspace = $this->select(
                        'Select package to publish',
                        $packages->pluck('name')->all(), // Convert collection to array of names
                    );
                }
            } else {
                // Non-interactive mode requires workspace to be specified via --workspace option
                if ($jsonOutput) {
                    $this->outputJson([
                        'status' => 'error',
                        'message' => 'Workspace must be specified in non-interactive mode',
                        'available_packages' => $packages->pluck('name')->all(),
                        'timestamp' => date('c'),
                    ]);
                } else {
                    $this->error('Workspace must be specified in non-interactive mode');
                }

                return Command::FAILURE;
            }
        }

        // =====================================================================
        // VALIDATE SELECTED WORKSPACE
        // =====================================================================

        // Verify the selected workspace is actually a package (not an app)
        // This prevents accidentally publishing apps to package registries
        $package = $this->getWorkspace($workspace);
        if ($package === null || $package['type'] !== 'package') {
            if ($jsonOutput) {
                $this->outputJson([
                    'status' => 'error',
                    'message' => "'{$workspace}' is not a package",
                    'workspace' => $workspace,
                    'timestamp' => date('c'),
                ]);
            } else {
                $this->error("'{$workspace}' is not a package");
            }

            return Command::FAILURE;
        }

        // =====================================================================
        // DISPLAY PUBLISH DETAILS
        // =====================================================================

        // Show what will be published (skip for structured output formats)
        if (! $jsonOutput && ! $summaryOutput) {
            $this->info("Publishing package: {$workspace}");
            $this->info("Tag: {$tag}");

            // Show dry-run warning if enabled (test mode)
            if ($dryRun) {
                $this->warning('DRY RUN MODE - No actual publishing will occur');
            }
        }

        // =====================================================================
        // USER CONFIRMATION (SAFETY GATE)
        // =====================================================================

        // Require explicit confirmation from user (only in interactive mode)
        // This is a critical safety measure to prevent accidental publishes
        $confirmed = true;
        if ($isInteractive && ! $jsonOutput && ! $summaryOutput) {
            $confirmed = $this->confirm(
                'Are you sure you want to publish?',
                default: false, // Default to NO for safety (user must explicitly approve)
            );

            // User cancelled - exit gracefully without error
            if (! $confirmed) {
                $this->info('Publish cancelled');

                return Command::SUCCESS;
            }
        }

        // =====================================================================
        // EXECUTE PUBLISH VIA TURBO
        // =====================================================================

        // User confirmed - proceed with publish
        // Run the publish task via Turbo with workspace filtering
        // Turbo will execute the 'publish' task defined in turbo.json
        // The filter option ensures only the selected workspace is published
        $exitCode = $this->turboRun('publish', [
            'filter' => $workspace, // Only run publish for this specific workspace
        ]);

        // =====================================================================
        // CALCULATE RESULTS AND PREPARE OUTPUT
        // =====================================================================

        // Calculate duration for performance tracking
        $duration = round(microtime(true) - $startTime, 2);

        // Prepare result data
        $success = $exitCode === 0;
        $status = $success ? 'success' : 'failed';

        // =====================================================================
        // DISPLAY RESULTS IN REQUESTED FORMAT
        // =====================================================================

        // Handle JSON output (for CI/CD integration)
        if ($jsonOutput) {
            $this->outputJson([
                'status' => $status,
                'workspace' => $workspace,
                'tag' => $tag,
                'dry_run' => $dryRun,
                'duration_seconds' => $duration,
                'exit_code' => $exitCode,
                'timestamp' => date('c'),
            ]);

            return $success ? Command::SUCCESS : Command::FAILURE;
        }

        // Handle summary output (concise text format)
        if ($summaryOutput) {
            $this->line("Publish: {$status}");
            $this->line("Package: {$workspace}");
            $this->line("Tag: {$tag}");
            $this->line("Duration: {$duration}s");
            if ($dryRun) {
                $this->line('Mode: dry-run');
            }

            return $success ? Command::SUCCESS : Command::FAILURE;
        }

        // Default output (user-friendly colored messages)
        if ($success) {
            $this->outro('✓ Package published successfully!');
        } else {
            $this->error('✗ Publish failed');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
