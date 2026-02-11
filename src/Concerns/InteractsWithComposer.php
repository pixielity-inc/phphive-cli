<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Concerns;

use function preg_match;

use Symfony\Component\Process\Process;

/**
 * Composer Integration Trait.
 *
 * This trait provides methods for interacting with Composer, PHP's dependency
 * manager. It enables commands to run Composer operations across the monorepo
 * workspaces, including installing dependencies, requiring packages, and
 * updating dependencies.
 *
 * All methods execute Composer commands in a subprocess with TTY support
 * for interactive output, and stream the output in real-time to the console.
 *
 * Example usage:
 * ```php
 * // Install dependencies in a workspace
 * $this->composerInstall('api');
 *
 * // Require a new package
 * $this->composerRequire('api', 'symfony/http-client');
 *
 * // Check if Composer is available
 * if ($this->hasComposer()) {
 *     $version = $this->getComposerVersion();
 * }
 * ```
 */
trait InteractsWithComposer
{
    /**
     * Run a Composer command in a specified directory.
     *
     * This is the base method for executing Composer commands. It creates
     * a subprocess with TTY support (if available) and streams the output
     * in real-time. The process has no timeout to accommodate long-running
     * operations like dependency resolution.
     *
     * @param  string      $command The Composer command to run (e.g., 'install', 'require symfony/console')
     * @param  string|null $cwd     The working directory (defaults to monorepo root)
     * @return int         The exit code (0 for success, non-zero for failure)
     */
    protected function composer(string $command, ?string $cwd = null): int
    {
        // Default to monorepo root if no directory specified
        $cwd ??= $this->getMonorepoRoot();

        // Create process from shell command to support complex commands
        $process = Process::fromShellCommandline(
            "composer {$command}",
            $cwd,
            timeout: null, // No timeout for long-running operations
        );

        // Enable TTY mode for interactive output (colors, progress bars)
        $process->setTty(Process::isTtySupported());

        // Run process and stream output in real-time
        return $process->run(function ($type, $buffer): void {
            echo $buffer;
        });
    }

    /**
     * Install Composer dependencies in a workspace.
     *
     * Runs `composer install` with production-optimized flags:
     * - --no-interaction: Non-interactive mode for CI/CD compatibility
     * - --prefer-dist: Download distribution archives instead of cloning repos
     * - --optimize-autoloader: Generate optimized autoloader for better performance
     *
     * @param  string $workspace The workspace name (e.g., 'api', 'calculator')
     * @return int    The exit code (0 for success, non-zero for failure)
     */
    protected function composerInstall(string $workspace): int
    {
        // Resolve workspace name to absolute path
        $path = $this->getWorkspacePath($workspace);

        // Run optimized install command
        return $this->composer(
            'install --no-interaction --prefer-dist --optimize-autoloader',
            $path,
        );
    }

    /**
     * Require a new Composer package in a workspace.
     *
     * Adds a new dependency to the workspace's composer.json and installs it.
     * Supports both production and development dependencies.
     *
     * @param  string $workspace The workspace name (e.g., 'api', 'calculator')
     * @param  string $package   The package to require (e.g., 'symfony/console:^7.0')
     * @param  bool   $dev       Whether to add as a dev dependency (default: false)
     * @return int    The exit code (0 for success, non-zero for failure)
     */
    protected function composerRequire(string $workspace, string $package, bool $dev = false): int
    {
        // Resolve workspace name to absolute path
        $path = $this->getWorkspacePath($workspace);

        // Add --dev flag for development dependencies
        $flag = $dev ? '--dev' : '';

        return $this->composer("require {$flag} {$package}", $path);
    }

    /**
     * Update Composer dependencies in a workspace.
     *
     * Updates dependencies to their latest versions according to version
     * constraints in composer.json. Can update all dependencies or a
     * specific package.
     *
     * @param  string      $workspace The workspace name (e.g., 'api', 'calculator')
     * @param  string|null $package   Optional specific package to update (e.g., 'symfony/console')
     * @return int         The exit code (0 for success, non-zero for failure)
     */
    protected function composerUpdate(string $workspace, ?string $package = null): int
    {
        // Resolve workspace name to absolute path
        $path = $this->getWorkspacePath($workspace);

        // Add package name if updating specific package
        $pkg = ($package !== null) ? " {$package}" : '';

        return $this->composer("update{$pkg}", $path);
    }

    /**
     * Check if Composer is installed and available.
     *
     * Attempts to run `composer --version` to verify Composer is in the
     * system PATH and executable. Useful for validating environment before
     * running Composer commands.
     *
     * @return bool True if Composer is available, false otherwise
     */
    protected function hasComposer(): bool
    {
        $process = Process::fromShellCommandline('composer --version');
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Get the installed Composer version.
     *
     * Parses the output of `composer --version` to extract the version number.
     * Returns null if Composer is not available or version cannot be determined.
     *
     * @return string|null The Composer version (e.g., '2.7.1'), or null if unavailable
     */
    protected function getComposerVersion(): ?string
    {
        // Run composer --version without ANSI colors for easier parsing
        $process = Process::fromShellCommandline('composer --version --no-ansi');
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        // Extract version number using regex (e.g., "Composer version 2.7.1")
        preg_match('/Composer version ([0-9.]+)/', $process->getOutput(), $matches);

        return (isset($matches[1]) && is_string($matches[1])) ? $matches[1] : null;
    }
}
