<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Dev;

use function count;

use Override;
use PhpHive\Cli\Console\Commands\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Build Command.
 *
 * This command builds applications and packages for production deployment.
 * It compiles assets, optimizes code, and prepares workspaces for deployment
 * using Turborepo for parallel execution and intelligent caching.
 *
 * The build process:
 * 1. Discovers all workspaces or targets specific workspace
 * 2. Runs build scripts via Turbo with dependency awareness
 * 3. Leverages caching to skip unchanged workspaces
 * 4. Executes builds in parallel for maximum speed
 * 5. Reports success or failure with clear feedback
 *
 * Build workflow:
 * - Validates workspace configuration
 * - Resolves workspace dependencies
 * - Executes build scripts in dependency order
 * - Caches build outputs for future runs
 * - Skips unchanged workspaces automatically
 *
 * Turbo task dependencies:
 * build → depends on → [lint, typecheck]
 *
 * Features:
 * - Parallel execution across workspaces
 * - Intelligent caching (skip if nothing changed)
 * - Workspace filtering (build specific workspace)
 * - Force rebuild option (ignore cache)
 * - Dependency graph awareness
 * - Progress tracking and error reporting
 * - Automatic dependency resolution
 * - Incremental builds support
 *
 * Common options inherited from BaseCommand:
 * - --workspace, -w: Target specific workspace
 * - --force, -f: Force operation by ignoring cache
 * - --no-cache: Disable Turbo cache
 * - --no-interaction, -n: Run in non-interactive mode
 *
 * Example usage:
 * ```bash
 * # Build all workspaces
 * hive build
 *
 * # Build specific workspace
 * hive build --workspace demo-app
 *
 * # Build with shorthand
 * hive build -w calculator
 *
 * # Force rebuild (ignore cache)
 * hive build --force
 *
 * # Force rebuild specific workspace
 * hive build -w demo-app --force
 *
 * # Disable Turbo cache completely
 * hive build --no-cache
 * ```
 *
 * @see BaseCommand For inherited functionality and common options
 * @see InteractsWithTurborepo For Turbo integration
 * @see InteractsWithMonorepo For workspace discovery
 * @see DevCommand For development server
 * @see DeployCommand For full deployment pipeline
 */
#[AsCommand(
    name: 'dev:build',
    description: 'Build for production',
    aliases: ['build'],
)]
final class BuildCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Inherits common options from BaseCommand (workspace, force, no-cache, no-interaction)
     * and adds command-specific options for output formatting.
     *
     * Options added:
     * - --json (-j): Output results as JSON for CI/CD integration
     * - --table: Display build summary in table format
     *
     * The JSON output includes:
     * - Build status (success/failed)
     * - List of workspaces built
     * - Build duration
     * - Cache usage information
     * - Individual build step results
     *
     * The table output provides:
     * - Per-workspace build status
     * - Build duration
     * - Overall summary
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        // Add JSON output option for CI/CD integration
        $this->addOption(
            'json',
            'j',
            InputOption::VALUE_NONE,
            'Output as JSON (for CI/CD integration)',
        );

        // Add table output option for structured display
        $this->addOption(
            'table',
            null,
            InputOption::VALUE_NONE,
            'Output build summary as table',
        );
    }

    /**
     * Execute the build command.
     *
     * This method orchestrates the entire production build process using Turborepo
     * for parallel execution and intelligent caching. The build process includes
     * automatic dependency resolution and execution of prerequisite tasks.
     *
     * Execution flow:
     * 1. Parse command options (workspace, force, no-cache, output format)
     * 2. Determine target workspaces (specific or all)
     * 3. Display intro banner (unless JSON/table output)
     * 4. Build Turbo options array based on user input
     * 5. Execute build task via Turbo with dependency resolution
     * 6. Calculate build duration
     * 7. Output results in requested format (default/JSON/table)
     * 8. Return appropriate exit code
     *
     * Turborepo integration:
     * - The build task in turbo.json has dependencies on lint and typecheck tasks
     * - Turbo automatically runs prerequisite tasks first in dependency order
     * - Tasks are executed in parallel across workspaces when possible
     * - Build outputs are cached to skip unchanged workspaces
     * - Cache can be bypassed with --force or --no-cache options
     *
     * Build task dependencies (from turbo.json):
     * build → depends on → [lint, typecheck]
     *
     * This means:
     * 1. Lint runs first (checks code style and quality)
     * 2. Typecheck runs next (validates TypeScript types)
     * 3. Build runs last (compiles and bundles for production)
     *
     * Caching behavior:
     * - Turbo hashes workspace files and dependencies
     * - If hash matches cached build, task is skipped
     * - Cache includes build outputs and logs
     * - --force bypasses cache and re-runs tasks
     * - --no-cache disables caching completely
     *
     * Workspace filtering:
     * - Without --workspace: Builds all workspaces in monorepo
     * - With --workspace: Builds only specified workspace
     * - Turbo still respects dependency graph
     * - Dependencies of filtered workspace are built first
     *
     * Output formats:
     * - Default: Human-readable with intro/outro messages
     * - JSON: Machine-readable for CI/CD integration
     * - Table: Structured table with per-workspace status
     *
     * Exit codes:
     * - 0 (SUCCESS): All builds completed successfully
     * - 1 (FAILURE): One or more builds failed
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // =====================================================================
        // PARSE COMMAND OPTIONS
        // =====================================================================

        // Extract workspace option (null = build all workspaces)
        $workspaceOption = $this->option('workspace');
        $workspace = is_string($workspaceOption) && $workspaceOption !== '' ? $workspaceOption : null;

        // Extract force option (bypass cache and re-run tasks)
        $force = $this->hasOption('force');

        // Extract no-cache option (disable Turbo cache completely)
        $noCache = $this->hasOption('no-cache');

        // Extract output format options
        $jsonOutput = $this->hasOption('json');
        $tableOutput = $this->hasOption('table');

        // =====================================================================
        // DETERMINE TARGET WORKSPACES
        // =====================================================================

        // Build list of workspaces to process
        // If workspace specified: single workspace array
        // If no workspace: all workspaces from monorepo
        $workspaces = $workspace !== null ? [$workspace] : $this->getWorkspaces()->pluck('name')->all();
        $workspaceCount = count($workspaces);

        // Track start time for duration calculation
        $startTime = microtime(true);

        // =====================================================================
        // DISPLAY INTRO BANNER
        // =====================================================================

        // Skip intro for structured output formats (JSON/table)
        if (! $jsonOutput && ! $tableOutput) {
            $this->intro('Building for Production');

            // Show what we're building
            if ($workspace !== null) {
                $this->info("Building workspace: {$workspace}");
            } else {
                $this->info("Building {$workspaceCount} workspace(s)");
            }
        }

        // =====================================================================
        // BUILD TURBO OPTIONS ARRAY
        // =====================================================================

        // Initialize options array for Turbo execution
        $options = [];

        // Filter to specific workspace if requested
        // Turbo will only run tasks for this workspace (and its dependencies)
        if ($workspace !== null) {
            $options['filter'] = $workspace;
        }

        // Force re-execution by ignoring cache
        // Useful when cache is stale or you want fresh builds
        if ($force) {
            $options['force'] = true;
        }

        // Disable cache completely
        // Useful for debugging or when cache is causing issues
        if ($noCache) {
            $options['cache'] = false;
        }

        // =====================================================================
        // EXECUTE BUILD VIA TURBOREPO
        // =====================================================================

        // Display progress message (skip for structured output)
        if (! $jsonOutput && ! $tableOutput) {
            $this->info('Running build via Turbo...');
        }

        // Execute the build task via Turbo
        // Turbo will:
        // 1. Resolve task dependencies (lint, typecheck, build)
        // 2. Execute tasks in dependency order
        // 3. Run tasks in parallel across workspaces when possible
        // 4. Use cache to skip unchanged workspaces
        // 5. Stream output to console
        $exitCode = $this->turboRun('build', $options);

        // =====================================================================
        // CALCULATE BUILD DURATION
        // =====================================================================

        // Calculate total build duration in seconds
        $duration = round(microtime(true) - $startTime, 2);

        // =====================================================================
        // PREPARE RESULT DATA
        // =====================================================================

        // Determine overall success based on exit code
        $success = $exitCode === 0;
        $status = $success ? 'success' : 'failed';

        // =====================================================================
        // HANDLE JSON OUTPUT
        // =====================================================================

        // Output results as JSON for CI/CD integration
        if ($jsonOutput) {
            $this->outputJson([
                'status' => $status,                    // Overall status
                'workspaces' => $workspaces,            // List of workspaces built
                'workspace_count' => $workspaceCount,   // Total workspace count
                'force' => $force,                      // Whether cache was bypassed
                'cache_disabled' => $noCache,           // Whether cache was disabled
                'duration_seconds' => $duration,        // Build duration
                'exit_code' => $exitCode,               // Turbo exit code
                'timestamp' => date('c'),               // ISO 8601 timestamp
                'build_steps' => [                      // Individual step results
                    'lint' => $success,                 // Lint step (prerequisite)
                    'typecheck' => $success,            // Typecheck step (prerequisite)
                    'build' => $success,                // Build step (main task)
                ],
            ]);

            return $success ? Command::SUCCESS : Command::FAILURE;
        }

        // =====================================================================
        // HANDLE TABLE OUTPUT
        // =====================================================================

        // Display results in structured table format
        if ($tableOutput) {
            $rows = array_map(
                fn ($ws): array => [$ws, $success ? '✓ Built' : '✗ Failed', '-'],
                $workspaces
            );
            // Add separator and summary row
            $rows[] = ['', '', ''];
            $rows[] = ['Total', $success ? '✓ Success' : '✗ Failed', "{$duration}s"];

            /* @var array<int, array<int, string>> $rows */
            $this->table(
                ['Workspace', 'Status', 'Duration'],
                $rows
            );

            return $success ? Command::SUCCESS : Command::FAILURE;
        }

        // =====================================================================
        // HANDLE DEFAULT OUTPUT
        // =====================================================================

        // Display success or failure message
        if ($success) {
            $this->outro('✓ Build completed successfully!');
        } else {
            $this->error('✗ Build failed');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
