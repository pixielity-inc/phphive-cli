<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Workspace;

use function array_filter;
use function array_map;
use function count;
use function json_encode;

use MonoPhp\Cli\Commands\BaseCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
 * ./cli/bin/mono list-workspaces
 *
 * # List apps only
 * ./cli/bin/mono list-workspaces --apps
 * ./cli/bin/mono ls -a
 *
 * # List packages only
 * ./cli/bin/mono list-workspaces --packages
 * ./cli/bin/mono workspaces -p
 *
 * # Output as JSON for scripting
 * ./cli/bin/mono list-workspaces --json
 * ./cli/bin/mono ls -j
 *
 * # Combine filters (apps only as JSON)
 * ./cli/bin/mono list-workspaces --apps --json
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithMonorepo For workspace discovery
 * @see InfoCommand For detailed workspace information
 */
#[AsCommand(
    name: 'list-workspaces',
    description: 'List all workspaces',
    aliases: ['ls', 'workspaces'],
)]
final class ListCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Defines filtering and output format options for the list command:
     * - --apps/-a: Filter to show only application workspaces
     * - --packages/-p: Filter to show only package workspaces
     * - --json/-j: Output as JSON instead of table format
     *
     * Common options (workspace, force, no-cache, no-interaction) are inherited from BaseCommand.
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
    }

    /**
     * Execute the list command.
     *
     * This method performs the following steps:
     * 1. Discovers all workspaces in the monorepo
     * 2. Applies filters if requested (apps/packages only)
     * 3. Outputs in requested format (table or JSON)
     * 4. Displays summary statistics
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (always 0 for success)
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

        // Handle JSON output format
        if ($this->hasOption('json')) {
            // Output raw JSON for scripting/automation
            // Pretty-print for readability
            $jsonOutput = json_encode($workspaces, JSON_PRETTY_PRINT);
            if ($jsonOutput !== false) {
                $output->writeln($jsonOutput);
            }

            return Command::SUCCESS;
        }

        // Display intro banner for table output
        $this->intro('Monorepo Workspaces');

        // Check if any workspaces were found
        if ($workspaces === []) {
            $this->warning('No workspaces found');

            return Command::SUCCESS;
        }

        // Display workspaces in a formatted table
        // Columns: Name, Type, Package Name, Composer, Path
        $this->table(
            ['Name', 'Type', 'Package Name', 'Composer', 'Path'],
            array_map(fn (array $w): array => [
                $w['name'],              // Directory name
                $w['type'],              // 'app' or 'package'
                $w['packageName'],       // Name from package.json
                $w['hasComposer'] ? '✓' : '✗',  // Composer availability
                $w['path'],               // Absolute path
            ], $workspaces),
        );

        // Calculate and display summary statistics
        $apps = count(array_filter($workspaces, fn (array $w): bool => $w['type'] === 'app'));
        $packages = count(array_filter($workspaces, fn (array $w): bool => $w['type'] === 'package'));

        $this->info("Found {$apps} app(s) and {$packages} package(s)");

        return Command::SUCCESS;
    }
}
