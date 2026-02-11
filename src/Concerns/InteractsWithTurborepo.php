<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Concerns;

use function array_keys;
use function file_exists;
use function file_get_contents;
use function implode;
use function json_decode;

use Symfony\Component\Process\Process;

use function trim;

/**
 * Turborepo Integration Trait.
 *
 * This trait provides methods for interacting with Turborepo, the high-performance
 * build system for JavaScript and TypeScript monorepos. It enables commands to
 * orchestrate tasks across workspaces, leverage caching, and manage parallel execution.
 *
 * Turborepo features supported:
 * - Task execution with dependency graph awareness
 * - Intelligent caching for faster builds
 * - Parallel task execution with concurrency control
 * - Workspace filtering for targeted operations
 * - Dry-run mode for testing task execution
 *
 * Example usage:
 * ```php
 * // Run a task across all workspaces
 * $this->turboRun('build');
 *
 * // Run a task with options
 * $this->turboRun('test', [
 *     'filter' => 'api',
 *     'force' => true,
 *     'concurrency' => 4
 * ]);
 *
 * // Check available tasks
 * $tasks = $this->getTurboTasks();
 * ```
 */
trait InteractsWithTurborepo
{
    /**
     * Run a Turbo command with optional configuration.
     *
     * This is the base method for executing Turborepo commands. It runs
     * commands through pnpm (the package manager) and supports all Turbo
     * CLI options through the $options array.
     *
     * @param  string $command The Turbo command to run (e.g., 'run build', 'prune')
     * @param  array  $options Optional configuration (filter, force, cache, etc.)
     * @return int    The exit code (0 for success, non-zero for failure)
     */
    protected function turbo(string $command, array $options = []): int
    {
        // Get monorepo root directory
        $cwd = $this->getMonorepoRoot();

        // Build options string from array
        $optionsString = $this->buildTurboOptions($options);

        // Construct full command with pnpm prefix
        $fullCommand = "pnpm turbo {$command} {$optionsString}";

        // Create process with no timeout for long-running tasks
        $process = Process::fromShellCommandline(
            $fullCommand,
            $cwd,
            timeout: null,
        );

        // Enable TTY mode for interactive output (colors, progress)
        $process->setTty(Process::isTtySupported());

        // Run process and stream output in real-time
        return $process->run(function ($type, $buffer): void {
            echo $buffer;
        });
    }

    /**
     * Run a Turbo task across workspaces.
     *
     * Executes a task defined in turbo.json across all matching workspaces.
     * Tasks are executed according to their dependency graph, with automatic
     * caching and parallelization.
     *
     * @param  string $task    The task name (e.g., 'build', 'test', 'lint')
     * @param  array  $options Optional configuration for task execution
     * @return int    The exit code (0 for success, non-zero for failure)
     */
    protected function turboRun(string $task, array $options = []): int
    {
        return $this->turbo("run {$task}", $options);
    }

    /**
     * Build Turbo CLI options string from array.
     *
     * Converts an associative array of options into a CLI-compatible string.
     * Supports all major Turborepo CLI flags and options.
     *
     * Supported options:
     * - filter: Workspace filter pattern (e.g., 'api', '@scope/*')
     * - force: Ignore cache and force re-execution
     * - cache: Enable/disable caching (default: true)
     * - concurrency: Max concurrent tasks (number or 'unlimited')
     * - parallel: Run tasks in parallel without respecting dependencies
     * - continue: Continue execution even if tasks fail
     * - dry: Dry-run mode ('json' for JSON output, true for text)
     * - graph: Generate task dependency graph
     * - output-logs: Control log output ('full', 'hash-only', 'new-only', 'none')
     *
     * @param  array  $options Associative array of options
     * @return string CLI options string
     */
    protected function buildTurboOptions(array $options): string
    {
        $parts = [];

        // Filter by workspace pattern
        if (isset($options['filter'])) {
            $parts[] = "--filter={$options['filter']}";
        }

        // Force re-execution (ignore cache)
        if (isset($options['force']) && $options['force']) {
            $parts[] = '--force';
        }

        // Disable caching
        if (isset($options['cache']) && ! $options['cache']) {
            $parts[] = '--no-cache';
        }

        // Set concurrency limit
        if (isset($options['concurrency'])) {
            $parts[] = "--concurrency={$options['concurrency']}";
        }

        // Run tasks in parallel (ignore dependencies)
        if (isset($options['parallel']) && $options['parallel']) {
            $parts[] = '--parallel';
        }

        // Continue on error
        if (isset($options['continue']) && $options['continue']) {
            $parts[] = '--continue';
        }

        // Dry-run mode
        if (isset($options['dry'])) {
            $parts[] = $options['dry'] === 'json' ? '--dry=json' : '--dry';
        }

        // Generate dependency graph
        if (isset($options['graph'])) {
            $parts[] = '--graph';
        }

        // Control output logs
        if (isset($options['output-logs'])) {
            $parts[] = "--output-logs={$options['output-logs']}";
        }

        return implode(' ', $parts);
    }

    /**
     * Check if Turborepo is installed and available.
     *
     * Attempts to run `pnpm turbo --version` to verify Turbo is installed
     * and accessible through pnpm. Useful for validating environment before
     * running Turbo commands.
     *
     * @return bool True if Turbo is available, false otherwise
     */
    protected function hasTurbo(): bool
    {
        $process = Process::fromShellCommandline('pnpm turbo --version');
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Get the installed Turborepo version.
     *
     * Retrieves the version number of the installed Turbo CLI. Returns null
     * if Turbo is not available or version cannot be determined.
     *
     * @return string|null The Turbo version (e.g., '2.8.5'), or null if unavailable
     */
    protected function getTurboVersion(): ?string
    {
        $process = Process::fromShellCommandline('pnpm turbo --version');
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        // Version is output directly without prefix
        return trim($process->getOutput());
    }

    /**
     * Get list of available Turbo tasks from turbo.json.
     *
     * Parses the turbo.json configuration file to extract all defined tasks.
     * Tasks are the keys in the "tasks" object and represent operations that
     * can be executed across workspaces.
     *
     * @return array<string> Array of task names (e.g., ['build', 'test', 'lint'])
     */
    protected function getTurboTasks(): array
    {
        // Locate turbo.json in monorepo root
        $turboJson = $this->getMonorepoRoot() . '/turbo.json';

        if (! file_exists($turboJson)) {
            return [];
        }

        // Parse JSON configuration
        $content = file_get_contents($turboJson);
        if ($content === false) {
            return [];
        }
        $config = json_decode($content, true);
        if (! is_array($config)) {
            return [];
        }

        // Extract task names from tasks object
        $tasks = $config['tasks'] ?? [];
        if (! is_array($tasks)) {
            return [];
        }

        return array_values(array_filter(array_keys($tasks), 'is_string'));
    }
}
