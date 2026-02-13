<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Make;

use PhpHive\Cli\Console\Commands\BaseCommand;
use PhpHive\Cli\Support\PreflightResult;

use function sprintf;

/**
 * Base Make Command.
 *
 * This abstract class provides common functionality for all "make" commands
 * (create:app, create:package, make:workspace). It extracts shared logic
 * for preflight checks, name validation, and success message display.
 *
 * Features:
 * - Unified preflight check execution and display
 * - Common name validation logic
 * - Consistent error handling for quiet/json modes
 * - Reusable success message formatting
 *
 * All make commands should extend this class to inherit these capabilities
 * and ensure consistent behavior across the CLI.
 *
 * Example usage:
 * ```php
 * class CreateAppCommand extends BaseMakeCommand
 * {
 *     protected function execute(InputInterface $input, OutputInterface $output): int
 *     {
 *         $isQuiet = $input->getOption('quiet') === true;
 *         $isJson = $input->getOption('json') === true;
 *
 *         // Run preflight checks
 *         $preflightResult = $this->runPreflightChecks($isQuiet, $isJson);
 *         if ($preflightResult->failed()) {
 *             $this->displayPreflightErrors($preflightResult, $isQuiet, $isJson);
 *             return Command::FAILURE;
 *         }
 *
 *         // ... rest of command logic
 *
 *         return Command::SUCCESS;
 *     }
 * }
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see CreateAppCommand For app creation implementation
 * @see CreatePackageCommand For package creation implementation
 * @see MakeWorkspaceCommand For workspace creation implementation
 */
abstract class BaseMakeCommand extends BaseCommand
{
    /**
     * Run preflight checks to validate the development environment.
     *
     * This method executes all preflight checks using the PreflightChecker service
     * and displays the results in a user-friendly format. The output is automatically
     * adjusted based on quiet and json modes.
     *
     * Preflight checks validate:
     * - Required system dependencies (PHP, Composer, Node.js, pnpm, etc.)
     * - Correct versions of tools
     * - Proper configuration
     * - Available disk space
     *
     * In normal mode, displays each check with ✓ or ✗ symbols and colored output.
     * In quiet/json mode, suppresses all output (errors handled by caller).
     *
     * Example usage:
     * ```php
     * $preflightResult = $this->runPreflightChecks($isQuiet, $isJson);
     * if ($preflightResult->failed()) {
     *     $this->displayPreflightErrors($preflightResult, $isQuiet, $isJson);
     *     return Command::FAILURE;
     * }
     * ```
     *
     * @param  bool            $isQuiet Suppress all output except errors
     * @param  bool            $isJson  Output results as JSON
     * @return PreflightResult The preflight check results
     */
    protected function runPreflightChecks(bool $isQuiet, bool $isJson): PreflightResult
    {
        $preflightChecker = $this->preflightChecker();
        $preflightResult = $preflightChecker->check();

        // Display check results (skip in quiet/json mode)
        if (! $isQuiet && ! $isJson) {
            foreach ($preflightResult->checks as $checkName => $checkResult) {
                if ($checkResult['passed']) {
                    $this->comment("✓ {$checkName}: {$checkResult['message']}");
                } else {
                    $this->error("✗ {$checkName}: {$checkResult['message']}");

                    if (isset($checkResult['fix'])) {
                        $this->line('');
                        $this->note($checkResult['fix'], 'Suggested fix');
                    }
                }
            }

            if ($preflightResult->passed) {
                $this->line('');
                $this->info('✓ All checks passed');
            }
        }

        return $preflightResult;
    }

    /**
     * Display preflight check errors in the appropriate format.
     *
     * This method handles the display of preflight check failures based on
     * the output mode (normal, quiet, or json). It ensures consistent error
     * reporting across all make commands.
     *
     * In json mode, outputs a structured JSON object with:
     * - success: false
     * - error: "Preflight checks failed"
     * - checks: Array of all check results
     *
     * In normal mode, errors are already displayed by runPreflightChecks(),
     * so this method is primarily for json mode handling.
     *
     * Example usage:
     * ```php
     * $preflightResult = $this->runPreflightChecks($isQuiet, $isJson);
     * if ($preflightResult->failed()) {
     *     $this->displayPreflightErrors($preflightResult, $isQuiet, $isJson);
     *     return Command::FAILURE;
     * }
     * ```
     *
     * @param PreflightResult $preflightResult The preflight check results
     * @param bool            $isQuiet         Suppress all output except errors
     * @param bool            $isJson          Output results as JSON
     */
    protected function displayPreflightErrors(PreflightResult $preflightResult, bool $isQuiet, bool $isJson): void
    {
        if ($isJson) {
            $this->outputJson([
                'success' => false,
                'error' => 'Preflight checks failed',
                'checks' => $preflightResult->checks,
            ]);
        }
        // In normal mode, errors are already displayed by runPreflightChecks()
    }

    /**
     * Check if a directory already exists at the specified path.
     *
     * This method checks if a directory exists and provides appropriate
     * error messages based on the output mode. It's used to prevent
     * accidental overwrites of existing apps, packages, or workspaces.
     *
     * Example usage:
     * ```php
     * $appPath = "{$root}/apps/{$name}";
     * if (!$this->checkDirectoryExists($name, $appPath, 'application', $isQuiet, $isJson)) {
     *     return Command::FAILURE;
     * }
     * ```
     *
     * @param  string $name    The name being checked (e.g., "my-app")
     * @param  string $path    The full path to check (e.g., "/path/to/apps/my-app")
     * @param  string $type    The type of entity (e.g., "application", "package", "workspace")
     * @param  bool   $isQuiet Suppress all output except errors
     * @param  bool   $isJson  Output results as JSON
     * @return bool   True if directory exists, false if available
     */
    protected function checkDirectoryExists(string $name, string $path, string $type, bool $isQuiet, bool $isJson): bool
    {
        if ($this->filesystem()->isDirectory($path)) {
            if (! $isQuiet && ! $isJson) {
                $this->warning(ucfirst($type) . " '{$name}' already exists");
            }

            return true;
        }

        if (! $isQuiet && ! $isJson) {
            $this->info('✓ ' . ucfirst($type) . " name '{$name}' is available");
        }

        return false;
    }

    /**
     * Display a success message with next steps.
     *
     * This method provides a consistent success message format across all make
     * commands. It displays a celebration message, the created entity name,
     * and a list of recommended next steps for the user.
     *
     * The output is automatically adjusted based on quiet and json modes:
     * - Normal mode: Beautiful formatted output with emoji and colors
     * - Json mode: Structured JSON with success status and metadata
     * - Quiet mode: No output
     *
     * Example usage:
     * ```php
     * $this->displaySuccessMessage(
     *     'application',
     *     'my-app',
     *     '/path/to/apps/my-app',
     *     120.5,
     *     [
     *         "cd apps/my-app",
     *         "Review the generated files",
     *         "hive dev --workspace=my-app"
     *     ],
     *     $isQuiet,
     *     $isJson,
     *     $isVerbose
     * );
     * ```
     *
     * @param string        $type      The type of entity (e.g., "application", "package", "workspace")
     * @param string        $name      The name of the created entity
     * @param string        $path      The full path to the created entity
     * @param float         $duration  Total duration in seconds
     * @param array<string> $nextSteps Array of next step instructions
     * @param bool          $isQuiet   Suppress all output
     * @param bool          $isJson    Output results as JSON
     * @param bool          $isVerbose Show verbose output including duration
     */
    protected function displaySuccessMessage(
        string $type,
        string $name,
        string $path,
        float $duration,
        array $nextSteps,
        bool $isQuiet,
        bool $isJson,
        bool $isVerbose
    ): void {
        if ($isJson) {
            $this->outputJson([
                'success' => true,
                'type' => $type,
                'name' => $name,
                'path' => $path,
                'duration' => round($duration, 2),
                'next_steps' => $nextSteps,
            ]);
        } elseif (! $isQuiet) {
            $this->line('');
            $this->outro('🎉 ' . ucfirst($type) . " '{$name}' created successfully!");
            $this->line('');
            $this->comment('Next steps:');

            $stepNumber = 1;
            foreach ($nextSteps as $nextStep) {
                $this->line("  {$stepNumber}. {$nextStep}");
                $stepNumber++;
            }

            if ($isVerbose) {
                $this->line('');
                $this->comment(sprintf('Total time: %.2fs', $duration));
            }
        }
    }

    /**
     * Clean up resources after failed or cancelled workspace creation.
     *
     * Removes the partially created workspace directory (app/package/workspace)
     * and stops/removes any Docker containers that were started during setup.
     *
     * This method provides a centralized cleanup mechanism for all make commands,
     * ensuring consistent behavior when operations fail or are cancelled.
     *
     * Cleanup actions:
     * 1. Stop and remove Docker containers (if docker-compose.yml exists)
     * 2. Remove Docker volumes
     * 3. Delete the workspace directory
     *
     * The method handles errors gracefully and provides user feedback about
     * cleanup progress and any issues encountered.
     *
     * Example usage:
     * ```php
     * try {
     *     // ... workspace creation logic
     * } catch (Exception $e) {
     *     if ($workspaceCreated && $workspacePath !== null) {
     *         $this->cleanupFailedWorkspace($workspacePath, $isQuiet, $isJson);
     *     }
     *     return Command::FAILURE;
     * }
     * ```
     *
     * @param string $workspacePath Absolute path to the workspace directory
     * @param bool   $isQuiet       Whether to suppress output
     * @param bool   $isJson        Whether output is in JSON format
     */
    protected function cleanupFailedWorkspace(string $workspacePath, bool $isQuiet, bool $isJson): void
    {
        if (! $isQuiet && ! $isJson) {
            $this->line('');
            $this->warning('Cleaning up...');
        }

        $filesystem = $this->filesystem();

        // Check if workspace directory exists
        if (! $filesystem->isDirectory($workspacePath)) {
            return;
        }

        // Stop and remove Docker containers if docker-compose.yml exists
        $dockerComposePath = "{$workspacePath}/docker-compose.yml";
        if ($filesystem->exists($dockerComposePath)) {
            try {
                if (! $isQuiet && ! $isJson) {
                    $this->comment('  Stopping Docker containers...');
                }

                // Stop and remove containers, networks, and volumes
                $process = $this->process();
                $process->run(['docker', 'compose', 'down', '-v'], $workspacePath);
            } catch (Exception) {
                // Ignore Docker cleanup errors - containers may not be running
            }
        }

        // Remove the workspace directory
        try {
            if (! $isQuiet && ! $isJson) {
                $this->comment('  Removing workspace directory...');
            }

            $filesystem->deleteDirectory($workspacePath);

            if (! $isQuiet && ! $isJson) {
                $this->info('✓ Cleanup complete');
            }
        } catch (Exception $e) {
            if (! $isQuiet && ! $isJson) {
                $this->error("Failed to remove directory: {$e->getMessage()}");
                $this->comment("You may need to manually remove: {$workspacePath}");
            }
        }
    }
}
