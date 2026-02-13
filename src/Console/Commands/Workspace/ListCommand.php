<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Workspace;

use function array_map;
use function explode;

use Illuminate\Support\Collection;

use function json_encode;

use Override;
use PhpHive\Cli\Console\Commands\BaseCommand;

use function str_starts_with;
use function strlen;
use function substr;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ucfirst;

/**
 * List Command.
 *
 * This command lists all workspaces in the monorepo with their metadata,
 * including name, type (app/package), package name, and path. It provides
 * filtering options to show only apps or packages, and can output as JSON
 * for programmatic consumption.
 *
 * The command discovers workspaces by:
 * 1. Reading pnpm-workspace.yaml for workspace patterns
 * 2. Scanning directories matching those patterns
 * 3. Extracting metadata from package.json and composer.json
 * 4. Categorizing as 'app' or 'package' based on location
 *
 * Workspace discovery process:
 * - Parses pnpm-workspace.yaml to find workspace globs
 * - Expands globs to find all matching directories
 * - Reads package.json from each directory for metadata
 * - Checks for composer.json presence
 * - Determines type based on directory location (apps/* vs packages/*)
 * - Builds comprehensive metadata array for each workspace
 *
 * Features:
 * - Beautiful table output with workspace details
 * - Filter by type (apps only or packages only)
 * - JSON output for scripting and automation
 * - Summary statistics (total apps and packages)
 * - Composer availability indicator
 * - Multiple command aliases for convenience
 * - Color-coded output for better readability
 *
 * Output formats:
 * - Table format (default): Human-readable table with columns
 * - JSON format (--json): Machine-readable JSON array
 *
 * Example usage:
 * ```bash
 * # List all workspaces in table format
 * hive list-workspaces
 *
 * # List apps only
 * hive list-workspaces --apps
 * hive ls -a
 *
 * # List packages only
 * hive list-workspaces --packages
 * hive workspaces -p
 *
 * # Output as JSON for scripting
 * hive list-workspaces --json
 * hive ls -j
 *
 * # Combine filters (apps only as JSON)
 * hive list-workspaces --apps --json
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithMonorepo For workspace discovery
 * @see InfoCommand For detailed workspace information
 */
#[AsCommand(
    name: 'workspace:list',
    description: 'List all workspaces',
    aliases: ['list-workspaces', 'ls', 'workspaces'],
)]
final class ListCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Defines filtering and output format options for the list command.
     * This method sets up all command-line options that control how workspaces
     * are filtered, sorted, and displayed.
     *
     * Available options:
     * - --apps/-a: Filter to show only application workspaces (excludes packages)
     * - --packages/-p: Filter to show only package workspaces (excludes apps)
     * - --json/-j: Output as JSON instead of table format (for scripting)
     * - --compact/-c: Minimal output showing only workspace names (one per line)
     * - --absolute: Show absolute filesystem paths (default is relative to monorepo root)
     * - --sort/-s: Sort workspaces by name (default), type, or package
     * - --columns: Customize displayed columns (comma-separated list)
     *
     * Column options:
     * Available columns: name, type, package, version, composer, path
     * Default: name,type,package,version,composer,path
     *
     * Common options inherited from BaseCommand:
     * - --workspace: Specify workspace context (not used in list command)
     * - --force: Force operation without confirmation
     * - --no-cache: Bypass cache for workspace discovery
     * - --no-interaction: Run in non-interactive mode
     *
     * Option combinations:
     * - --apps and --packages are mutually exclusive (last one wins)
     * - --json and --compact are mutually exclusive (last one wins)
     * - --columns only applies to table format (ignored with --json or --compact)
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'apps',
            'a',
            InputOption::VALUE_NONE,
            'Show apps only (filter out packages)',
        );

        $this->addOption(
            'packages',
            'p',
            InputOption::VALUE_NONE,
            'Show packages only (filter out apps)',
        );

        $this->addOption(
            'json',
            'j',
            InputOption::VALUE_NONE,
            'Output as JSON for programmatic consumption',
        );

        $this->addOption(
            'compact',
            'c',
            InputOption::VALUE_NONE,
            'Minimal output (just workspace names)',
        );

        $this->addOption(
            'absolute',
            null,
            InputOption::VALUE_NONE,
            'Show absolute paths (default is relative)',
        );

        $this->addOption(
            'sort',
            's',
            InputOption::VALUE_REQUIRED,
            'Sort by: name, type, or package',
            'name',
        );

        $this->addOption(
            'columns',
            null,
            InputOption::VALUE_REQUIRED,
            'Comma-separated list of columns to display (name,type,package,version,composer,path)',
            'name,type,package,version,composer,path',
        );
    }

    /**
     * Execute the list command.
     *
     * This method orchestrates the entire workspace listing process, from discovery
     * through filtering, sorting, and final output. It handles multiple output formats
     * and provides flexible filtering options.
     *
     * Execution flow:
     * 1. Workspace Discovery:
     *    - Reads pnpm-workspace.yaml to find workspace patterns
     *    - Scans directories matching those patterns
     *    - Extracts metadata from package.json and composer.json
     *    - Categorizes workspaces as 'app' or 'package' based on location
     *
     * 2. Filtering:
     *    - If --apps specified: Filter to show only application workspaces
     *    - If --packages specified: Filter to show only package workspaces
     *    - If neither specified: Show all workspaces
     *
     * 3. Enrichment:
     *    - Reads version from package.json for each workspace
     *    - Uses Collection map() for efficient transformation
     *
     * 4. Sorting:
     *    - By name (default): Alphabetical order
     *    - By type: Groups apps and packages, then alphabetical within groups
     *    - By package: Sorts by package name (e.g., @phphive/api)
     *    - Uses Collection sortBy() for clean, declarative sorting
     *
     * 5. Path Conversion:
     *    - Converts absolute paths to relative (unless --absolute specified)
     *    - Makes output more readable and portable
     *    - Uses Collection map() for transformation
     *
     * 6. Output:
     *    - Compact mode: One workspace name per line (for piping to other commands)
     *    - JSON mode: Machine-readable JSON array (for scripting/automation)
     *    - Table mode (default): Formatted table with columns and summary
     *
     * Why we use Collections instead of arrays:
     * - Cleaner, more expressive code with chainable methods
     * - Built-in methods like map(), filter(), where(), sortBy() reduce boilerplate
     * - Lazy evaluation for better performance with large datasets
     * - Consistent API across the codebase
     * - Better readability and maintainability
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (Command::SUCCESS always, as listing cannot fail)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Discover all workspaces in the monorepo
        // This scans pnpm-workspace.yaml and extracts metadata
        $workspaces = $this->getWorkspaces();

        // Apply type filter if requested
        if ($this->hasOption('apps')) {
            // Show only application workspaces
            $workspaces = $this->getApps();
        } elseif ($this->hasOption('packages')) {
            // Show only package workspaces
            $workspaces = $this->getPackages();
        }

        // Check if any workspaces were found
        if ($workspaces->isEmpty()) {
            if (! $this->hasOption('compact') && ! $this->hasOption('json')) {
                $this->warning('No workspaces found');
            }

            return Command::SUCCESS;
        }

        // Enrich workspaces with version information
        $workspaces = $this->enrichWorkspacesWithVersion($workspaces);

        // Sort workspaces based on --sort option
        $workspaces = $this->sortWorkspaces($workspaces, $this->option('sort'));

        // Convert to relative paths unless --absolute is specified
        if (! $this->hasOption('absolute')) {
            $workspaces = $this->convertToRelativePaths($workspaces);
        }

        // Handle compact output format (just names)
        if ($this->hasOption('compact')) {
            foreach ($workspaces as $workspace) {
                $this->line($workspace['name']);
            }

            return Command::SUCCESS;
        }

        // Handle JSON output format
        if ($this->hasOption('json')) {
            // Output raw JSON for scripting/automation
            // Pretty-print for readability
            $jsonOutput = json_encode($workspaces, JSON_PRETTY_PRINT);
            if ($jsonOutput !== false) {
                $this->line($jsonOutput);
            }

            return Command::SUCCESS;
        }

        // Display intro banner for table output
        $this->intro('Monorepo Workspaces');

        // Parse columns option
        $columns = $this->parseColumns($this->option('columns'));

        // Display workspaces in a formatted table
        $this->table(
            $this->getTableHeaders($columns),
            $this->getTableRows($workspaces, $columns),
        );

        // Calculate and display summary statistics using Collection methods
        $apps = $workspaces->where('type', 'app')->count();
        $packages = $workspaces->where('type', 'package')->count();

        $this->info("Found {$apps} app(s) and {$packages} package(s)");

        return Command::SUCCESS;
    }

    /**
     * Enrich workspaces with version information from package.json.
     *
     * Reads the package.json file for each workspace and extracts the version field.
     * This provides version information in the workspace listing without requiring
     * a separate lookup.
     *
     * Why use Collection map():
     * - Transforms each workspace by adding a version field
     * - Returns a new Collection (immutable transformation)
     * - More expressive than foreach loops
     * - Chainable with other Collection methods
     *
     * Version handling:
     * - If version exists in package.json: Use that version
     * - If version missing or package.json not found: Use '-' as placeholder
     * - Ensures consistent output format even with missing data
     *
     * @param  Collection $workspaces Collection of workspace metadata arrays
     * @return Collection Enriched workspaces with version field added to each
     */
    private function enrichWorkspacesWithVersion(Collection $workspaces)
    {
        // Read package.json and extract version field
        // Uses getWorkspacePackageJson() helper from BaseCommand
        return $workspaces->map(function (array $workspace): array {
            $packageJson = $this->getWorkspacePackageJson($workspace['name']);

            // Add version field, defaulting to '-' if not found
            $workspace['version'] = $packageJson['version'] ?? '-';

            return $workspace;
        });
    }

    /**
     * Sort workspaces based on the specified criteria.
     *
     * Provides flexible sorting options to organize workspace listings in different ways.
     * Uses Collection sortBy() for clean, declarative sorting logic.
     *
     * Sorting options:
     * - 'name' (default): Alphabetical by workspace name (api, calculator, cli, etc.)
     * - 'type': Groups by type (apps first, then packages), then alphabetical within groups
     * - 'package': Alphabetical by package name (@phphive/api, @phphive/calculator, etc.)
     *
     * Why use Collection sortBy():
     * - Cleaner syntax than usort() with custom comparison functions
     * - Supports multi-level sorting with array syntax: [['type', 'asc'], ['name', 'asc']]
     * - Returns a new Collection (immutable operation)
     * - Consistent with Laravel/Illuminate Collection API
     *
     * Multi-level sorting for 'type':
     * - First sorts by type (app vs package)
     * - Then sorts by name within each type group
     * - Ensures consistent, predictable ordering
     *
     * @param  Collection $workspaces Collection of workspace metadata arrays
     * @param  string     $sortBy     Sort criteria: 'name', 'type', or 'package'
     * @return Collection Sorted workspaces (new Collection instance)
     */
    private function sortWorkspaces($workspaces, string $sortBy)
    {
        // Use match expression for clean sorting logic
        // Each case returns a sorted Collection
        return match ($sortBy) {
            // Sort by type first, then by name within each type
            // This groups all apps together, then all packages
            'type' => $workspaces->sortBy([['type', 'asc'], ['name', 'asc']]),

            // Sort alphabetically by package name (e.g., @phphive/api)
            'package' => $workspaces->sortBy('packageName'),

            // Default: Sort alphabetically by workspace name
            default => $workspaces->sortBy('name'),
        };
    }

    /**
     * Convert absolute paths to relative paths from monorepo root.
     *
     * Transforms absolute filesystem paths to relative paths for better readability
     * and portability. Relative paths are easier to read and work across different
     * environments (local dev, CI/CD, containers).
     *
     * Why use Collection map():
     * - Transforms each workspace's path field
     * - Returns a new Collection (immutable transformation)
     * - More expressive than foreach loops
     * - Chainable with other Collection operations
     *
     * Path transformation logic:
     * - If path starts with monorepo root: Strip root prefix and leading slash
     * - If path doesn't start with root: Leave unchanged (shouldn't happen)
     * - Example: /home/user/project/apps/api -> apps/api
     *
     * String operations:
     * - str_starts_with(): Check if path begins with root (PHP 8.0+)
     * - substr(): Remove root prefix from path
     * - strlen(): Get length of root path for substr offset
     * - +1 offset: Remove the trailing slash after root
     *
     * @param  Collection $workspaces Collection of workspace metadata arrays
     * @return Collection Workspaces with relative paths (new Collection instance)
     */
    private function convertToRelativePaths($workspaces)
    {
        // Get monorepo root path for comparison
        $root = $this->getMonorepoRoot();

        // Transform each workspace's path from absolute to relative
        return $workspaces->map(function (array $workspace) use ($root): array {
            // Check if path starts with monorepo root
            if (str_starts_with($workspace['path'], $root)) {
                // Strip root prefix and leading slash
                // Example: /home/user/project/apps/api -> apps/api
                $workspace['path'] = substr($workspace['path'], strlen($root) + 1);
            }

            return $workspace;
        });
    }

    /**
     * Parse the columns option into an array.
     *
     * Converts the comma-separated column string into an array of column names.
     * This allows users to customize which columns appear in the table output.
     *
     * Input format: "name,type,package,version"
     * Output format: ["name", "type", "package", "version"]
     *
     * String operations:
     * - explode(): Split string by comma delimiter
     * - array_map(): Apply trim() to each column name
     * - trim(): Remove whitespace from column names
     * - First-class callable syntax: trim(...) instead of fn($x) => trim($x)
     *
     * Why trim each column:
     * - Allows flexible input: "name,type" or "name, type" both work
     * - Prevents issues with accidental whitespace
     * - Makes the command more user-friendly
     *
     * @param  string $columnsOption Comma-separated column names (e.g., "name,type,package")
     * @return array  Array of trimmed column names
     */
    private function parseColumns(string $columnsOption): array
    {
        return array_map(trim(...), explode(',', $columnsOption));
    }

    /**
     * Get table headers based on selected columns.
     *
     * Maps internal column names to user-friendly header labels for table display.
     * This provides a clean separation between internal data structure and
     * presentation layer.
     *
     * Column mapping:
     * - 'name' -> 'Name': Workspace name (e.g., api, calculator)
     * - 'type' -> 'Type': Workspace type (app or package)
     * - 'package' -> 'Package Name': Full package name (e.g., @phphive/api)
     * - 'version' -> 'Version': Package version from package.json
     * - 'composer' -> 'Composer': Whether composer.json exists (✓ or ✗)
     * - 'path' -> 'Path': Filesystem path to workspace
     *
     * Fallback behavior:
     * - If column not in map: Capitalize first letter (ucfirst)
     * - Allows custom columns to work without explicit mapping
     * - Example: 'custom' -> 'Custom'
     *
     * Why use array_map():
     * - Transforms array of column names to array of header labels
     * - Clean, functional approach
     * - Single-line transformation with arrow function
     *
     * @param  array $columns Array of column names (e.g., ['name', 'type', 'package'])
     * @return array Array of header labels (e.g., ['Name', 'Type', 'Package Name'])
     */
    private function getTableHeaders(array $columns): array
    {
        // Define mapping from internal column names to display headers
        $headerMap = [
            'name' => 'Name',
            'type' => 'Type',
            'package' => 'Package Name',
            'version' => 'Version',
            'composer' => 'Composer',
            'path' => 'Path',
        ];

        // Map each column to its header label, with fallback to ucfirst
        return array_map(fn (string $col): string => $headerMap[$col] ?? ucfirst($col), $columns);
    }

    /**
     * Get table rows based on selected columns.
     *
     * Transforms workspace metadata into table rows, extracting only the columns
     * specified by the user. This provides flexible table output where users can
     * choose which information to display.
     *
     * Why use nested Collection operations:
     * - Outer map(): Transforms each workspace into a table row
     * - Inner collect() + map(): Transforms column names into cell values
     * - all(): Converts Collection back to array for table() method
     * - Clean, declarative approach without nested foreach loops
     *
     * Column value extraction:
     * - Uses match expression for clean, type-safe value mapping
     * - Each column name maps to a specific workspace field
     * - Default case returns '-' for unknown columns
     *
     * Special formatting:
     * - 'composer': Boolean to checkmark (✓ for true, ✗ for false)
     * - 'version': Defaults to '-' if not set
     * - Other fields: Direct value from workspace array
     *
     * Why this approach:
     * - Flexible: Easy to add new columns without changing structure
     * - Type-safe: Match expression ensures all cases handled
     * - Readable: Clear mapping from column name to value
     * - Maintainable: Single source of truth for column extraction
     *
     * @param  Collection $workspaces Collection of workspace metadata arrays
     * @param  array      $columns    Array of column names to include
     * @return array      Array of table rows (each row is an array of cell values)
     */
    private function getTableRows($workspaces, array $columns): array
    {
        // Transform workspaces into table rows
        // Outer map: Each workspace becomes a row
        // Inner map: Each column becomes a cell value
        return $workspaces->map(fn (array $workspace): array => collect($columns)->map(fn (string $column) => match ($column) {
            // Extract workspace name
            'name' => $workspace['name'],

            // Extract workspace type (app or package)
            'type' => $workspace['type'],

            // Extract full package name (e.g., @phphive/api)
            'package' => $workspace['packageName'],

            // Extract version, defaulting to '-' if not set
            'version' => $workspace['version'] ?? '-',

            // Convert boolean to checkmark symbol
            'composer' => $workspace['hasComposer'] ? '✓' : '✗',

            // Extract filesystem path
            'path' => $workspace['path'],

            // Default for unknown columns
            default => '-',
        })->all())->all();
    }
}
