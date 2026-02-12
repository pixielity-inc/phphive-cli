<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns;

use function array_filter;
use function dirname;
use function file_exists;
use function file_get_contents;

use InvalidArgumentException;

use function is_dir;
use function json_decode;
use function preg_match_all;

use RuntimeException;

use function str_contains;
use function str_replace;

use Symfony\Component\Finder\Finder;

/**
 * Monorepo Workspace Management Trait.
 *
 * This trait provides comprehensive methods for discovering, inspecting, and
 * managing workspaces within the monorepo. It handles workspace detection,
 * metadata extraction, and provides convenient accessors for workspace information.
 *
 * The monorepo structure is defined by:
 * - turbo.json: Turborepo configuration
 * - pnpm-workspace.yaml: Workspace patterns and definitions
 * - Individual workspace package.json and composer.json files
 *
 * Workspaces are categorized as:
 * - Apps: Application workspaces (typically in apps/ directory)
 * - Packages: Shared library workspaces (typically in packages/ directory)
 *
 * Example usage:
 * ```php
 * // Get all workspaces
 * $workspaces = $this->getWorkspaces();
 *
 * // Get specific workspace info
 * $api = $this->getWorkspace('api');
 * $path = $this->getWorkspacePath('api');
 *
 * // Filter by type
 * $apps = $this->getApps();
 * $packages = $this->getPackages();
 *
 * // Check workspace configuration
 * $composerJson = $this->getWorkspaceComposerJson('api');
 * ```
 */
trait InteractsWithMonorepo
{
    /**
     * Get the absolute path to the monorepo root directory.
     *
     * The monorepo root is identified by the presence of both turbo.json
     * and pnpm-workspace.yaml files. This method searches upward from the
     * current working directory until it finds these marker files.
     *
     * The result is cached statically to avoid repeated filesystem traversal.
     *
     *
     * @return string Absolute path to monorepo root
     *
     * @throws RuntimeException If monorepo root cannot be found
     */
    protected function getMonorepoRoot(): string
    {
        // Use static cache to avoid repeated filesystem traversal
        static $root = null;

        if ($root !== null) {
            return $root;
        }

        // Start from current working directory
        $current = getcwd();

        if ($current === false) {
            throw new RuntimeException('Could not determine current working directory');
        }

        // Traverse upward until we find monorepo markers or reach filesystem root
        while ($current !== '/') {
            // Check for both turbo.json and pnpm-workspace.yaml
            if (file_exists($current . '/turbo.json') && file_exists($current . '/pnpm-workspace.yaml')) {
                $root = $current;

                return $root;
            }

            // Move up one directory
            $current = dirname($current);
        }

        throw new RuntimeException('Could not find monorepo root (turbo.json and pnpm-workspace.yaml not found)');
    }

    /**
     * Get all workspaces in the monorepo with their metadata.
     *
     * This method discovers workspaces by:
     * 1. Parsing pnpm-workspace.yaml for workspace patterns
     * 2. Scanning directories matching those patterns
     * 3. Extracting metadata from package.json and composer.json
     *
     * Each workspace entry contains:
     * - name: Directory name (e.g., 'api', 'calculator')
     * - path: Absolute path to workspace directory
     * - type: 'app' or 'package' based on directory location
     * - packageName: Name from package.json (e.g., '@repo/api')
     * - hasComposer: Whether composer.json exists
     * - hasPackageJson: Whether package.json exists
     *
     * @return array<array{name: string, path: string, type: string, packageName: string, hasComposer: bool, hasPackageJson: bool}>
     */
    protected function getWorkspaces(): array
    {
        $root = $this->getMonorepoRoot();
        $workspaceFile = $root . '/pnpm-workspace.yaml';

        if (! file_exists($workspaceFile)) {
            return [];
        }

        // Parse workspace patterns from YAML file
        $content = file_get_contents($workspaceFile);
        if ($content === false) {
            return [];
        }
        preg_match_all('/- "([^"]+)"/', $content, $matches);

        $patterns = $matches[1];
        $workspaces = [];

        // Process each workspace pattern
        foreach ($patterns as $pattern) {
            // Remove wildcard suffix (e.g., 'apps/*' -> 'apps')
            $pattern = str_replace('/*', '', $pattern);
            $dir = $root . '/' . $pattern;

            if (! is_dir($dir)) {
                continue;
            }

            // Find all subdirectories at depth 0
            $finder = new Finder();
            $finder->directories()->in($dir)->depth(0);

            foreach ($finder as $directory) {
                $name = $directory->getFilename();
                $path = $directory->getRealPath();

                // Skip if path is false
                if ($path === false) {
                    continue;
                }

                // Only include directories with package.json
                if (file_exists($path . '/package.json')) {
                    // Parse package.json for metadata
                    $packageJsonContent = file_get_contents($path . '/package.json');
                    if ($packageJsonContent === false) {
                        continue;
                    }
                    $packageJson = json_decode($packageJsonContent, true);
                    if (! is_array($packageJson)) {
                        continue;
                    }

                    $workspaces[] = [
                        'name' => $name,
                        'path' => $path,
                        // Determine type based on path (apps/ vs packages/)
                        'type' => str_contains($path, '/apps/') ? 'app' : 'package',
                        'packageName' => $packageJson['name'] ?? $name,
                        'hasComposer' => file_exists($path . '/composer.json'),
                        'hasPackageJson' => true,
                    ];
                }
            }
        }

        return $workspaces;
    }

    /**
     * Get metadata for a specific workspace by name.
     *
     * Searches for a workspace matching either the directory name or the
     * package name from package.json. This allows flexible workspace
     * referencing (e.g., 'api' or '@repo/api').
     *
     * @param  string     $name Workspace name or package name
     * @return array|null Workspace metadata array, or null if not found
     */
    protected function getWorkspace(string $name): ?array
    {
        $workspaces = $this->getWorkspaces();

        foreach ($workspaces as $workspace) {
            // Match by directory name or package name
            if ($workspace['name'] === $name || $workspace['packageName'] === $name) {
                return $workspace;
            }
        }

        return null;
    }

    /**
     * Get the absolute path to a workspace directory.
     *
     * Convenience method that extracts just the path from workspace metadata.
     * Throws an exception if the workspace doesn't exist.
     *
     * @param  string $name Workspace name or package name
     * @return string Absolute path to workspace directory
     *
     * @throws InvalidArgumentException If workspace is not found
     */
    protected function getWorkspacePath(string $name): string
    {
        $workspace = $this->getWorkspace($name);

        if ($workspace === null) {
            throw new InvalidArgumentException("Workspace '{$name}' not found");
        }

        return $workspace['path'];
    }

    /**
     * Get all application workspaces.
     *
     * Filters workspaces to return only those categorized as 'app'.
     * Applications are typically deployable services or frontends.
     *
     * @return array<array> Array of app workspace metadata
     */
    protected function getApps(): array
    {
        return array_filter($this->getWorkspaces(), fn (array $w): bool => $w['type'] === 'app');
    }

    /**
     * Get all package workspaces.
     *
     * Filters workspaces to return only those categorized as 'package'.
     * Packages are typically shared libraries or utilities.
     *
     * @return array<array> Array of package workspace metadata
     */
    protected function getPackages(): array
    {
        return array_filter($this->getWorkspaces(), fn (array $w): bool => $w['type'] === 'package');
    }

    /**
     * Check if a workspace exists in the monorepo.
     *
     * @param  string $name Workspace name or package name
     * @return bool   True if workspace exists, false otherwise
     */
    protected function hasWorkspace(string $name): bool
    {
        return $this->getWorkspace($name) !== null;
    }

    /**
     * Get parsed composer.json content for a workspace.
     *
     * Reads and parses the composer.json file from a workspace directory.
     * Returns null if the workspace doesn't exist or doesn't have composer.json.
     *
     * @param  string     $name Workspace name or package name
     * @return array|null Parsed composer.json as associative array, or null
     */
    protected function getWorkspaceComposerJson(string $name): ?array
    {
        $workspace = $this->getWorkspace($name);

        // Return null if workspace not found or has no composer.json
        if ($workspace === null || ! $workspace['hasComposer']) {
            return null;
        }

        $composerJson = $workspace['path'] . '/composer.json';

        $content = file_get_contents($composerJson);
        if ($content === false) {
            return null;
        }
        $data = json_decode($content, true);
        if (! is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Get parsed package.json content for a workspace.
     *
     * Reads and parses the package.json file from a workspace directory.
     * Returns null if the workspace doesn't exist.
     *
     * @param  string     $name Workspace name or package name
     * @return array|null Parsed package.json as associative array, or null
     */
    protected function getWorkspacePackageJson(string $name): ?array
    {
        $workspace = $this->getWorkspace($name);

        if ($workspace === null) {
            return null;
        }

        $packageJson = $workspace['path'] . '/package.json';

        $content = file_get_contents($packageJson);
        if ($content === false) {
            return null;
        }
        $data = json_decode($content, true);
        if (! is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Get all workspace names as a simple array.
     *
     * This method extracts just the workspace names from the full workspace
     * metadata, returning a simple array of strings. This is useful for
     * prompts, iteration, or filtering operations.
     *
     * Example:
     * ```php
     * $names = $this->getAllWorkspaceNames();
     * // Returns: ['api', 'calculator', 'shared-utils']
     * ```
     *
     * @return array<string> Array of workspace names
     */
    protected function getAllWorkspaceNames(): array
    {
        $workspaces = $this->getWorkspaces();

        return array_map(
            static fn (array $workspace): string => $workspace['name'],
            $workspaces
        );
    }

    /**
     * Select a single workspace interactively or from option.
     *
     * This method provides a unified way to get a workspace selection from the user.
     * It first checks if a workspace was specified via the --workspace option.
     * If not, it prompts the user to select from available workspaces interactively.
     *
     * The method uses Laravel Prompts for a beautiful interactive selection experience
     * when running in interactive mode. In non-interactive mode, it will return the
     * first available workspace if no workspace option was provided.
     *
     * Example:
     * ```php
     * $workspace = $this->selectWorkspace('Which workspace to install?');
     * $this->info("Installing in {$workspace}...");
     * ```
     *
     * @param  string $prompt The prompt message to display to the user
     * @return string The selected workspace name
     *
     * @throws RuntimeException If no workspaces are found
     */
    protected function selectWorkspace(string $prompt = 'Select workspace'): string
    {
        // Check if workspace was specified via --workspace option
        $workspace = $this->option('workspace');
        if (is_string($workspace) && $workspace !== '' && $workspace !== '0') {
            return $workspace;
        }

        // Get all available workspaces
        $workspaces = $this->getAllWorkspaceNames();

        // If no workspaces available, throw an exception
        if ($workspaces === []) {
            throw new RuntimeException('No workspaces found in the monorepo.');
        }

        // If only one workspace exists, return it automatically
        if (count($workspaces) === 1) {
            return $workspaces[0];
        }

        // In non-interactive mode, return the first workspace
        if ($this->option('no-interaction') === true) {
            return $workspaces[0];
        }

        // Use interactive prompt to select workspace (from InteractsWithPrompts trait)
        return (string) $this->select($prompt, $workspaces);
    }

    /**
     * Select multiple workspaces interactively or from option.
     *
     * This method allows users to select one or more workspaces either via
     * the --workspace option (comma-separated) or through an interactive
     * multi-select prompt.
     *
     * The method handles several scenarios:
     * - If --all flag is set, returns all available workspaces
     * - If --workspace option is provided, parses comma-separated values
     * - Otherwise, prompts user for interactive multi-selection
     *
     * Example:
     * ```php
     * $workspaces = $this->selectWorkspaces('Select workspaces to test');
     * foreach ($workspaces as $workspace) {
     *     $this->info("Testing {$workspace}...");
     * }
     * ```
     *
     * @param  string        $prompt The prompt message to display to the user
     * @return array<string> Array of selected workspace names
     *
     * @throws RuntimeException If no workspaces are found
     */
    protected function selectWorkspaces(string $prompt = 'Select workspaces'): array
    {
        // If --all flag is set, return all workspaces
        if ($this->shouldRunOnAll()) {
            return $this->getAllWorkspaceNames();
        }

        // Check if workspace(s) were specified via --workspace option
        $workspace = $this->option('workspace');
        if (is_string($workspace) && $workspace !== '' && $workspace !== '0') {
            // Split by comma to support multiple workspaces
            $workspaces = array_map(trim(...), explode(',', $workspace));

            return array_values(array_filter($workspaces, static fn ($w): bool => $w !== '' && $w !== '0'));
        }

        // Get all available workspaces
        $allWorkspaces = $this->getAllWorkspaceNames();

        // If no workspaces available, throw an exception
        if ($allWorkspaces === []) {
            throw new RuntimeException('No workspaces found in the monorepo.');
        }

        // In non-interactive mode, return all workspaces
        if ($this->option('no-interaction') === true) {
            return $allWorkspaces;
        }

        // Use interactive multi-select prompt (from InteractsWithPrompts trait)
        $selected = $this->multiselect($prompt, $allWorkspaces);

        // Ensure all values are strings
        return array_map(strval(...), $selected);
    }

    /**
     * Check if command should run on all workspaces.
     *
     * This method determines whether the command should be executed across
     * all available workspaces. It returns true in two scenarios:
     * 1. The --all flag is explicitly set
     * 2. No specific workspace was specified via --workspace option
     *
     * This provides a convenient way to implement "run everywhere by default"
     * behavior while still allowing users to target specific workspaces.
     *
     * Example:
     * ```php
     * if ($this->shouldRunOnAll()) {
     *     $workspaces = $this->getAllWorkspaceNames();
     * } else {
     *     $workspaces = [$this->selectWorkspace()];
     * }
     * ```
     *
     * @return bool True if --all flag is set or no workspace specified
     */
    protected function shouldRunOnAll(): bool
    {
        // Check if --all flag is explicitly set
        if ($this->hasOption('all')) {
            return true;
        }

        // Check if no specific workspace was specified
        $workspace = $this->option('workspace');

        return in_array($workspace, [null, '', '0'], true);
    }
}
