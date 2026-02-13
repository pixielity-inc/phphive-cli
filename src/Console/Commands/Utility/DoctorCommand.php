<?php

declare(strict_types=1);

namespace PhpHive\Cli\Console\Commands\Utility;

use function date;
use function extension_loaded;
use function json_encode;

use Override;
use PhpHive\Cli\Console\Commands\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function trim;
use function version_compare;

/**
 * System Doctor Command.
 *
 * This command performs comprehensive health checks on the development environment
 * to ensure all required tools and dependencies are properly installed and configured.
 * It validates PHP version, extensions, package managers, build tools, and workspace
 * configuration.
 *
 * The doctor command is essential for:
 * - Onboarding new developers (verify environment setup)
 * - Troubleshooting build/deployment issues
 * - CI/CD pipeline validation
 * - Pre-deployment health checks
 * - System requirement verification
 *
 * Health checks performed:
 * 1. PHP version and required extensions (json, mbstring, xml)
 * 2. Composer installation and version
 * 3. Turborepo installation and version
 * 4. Node.js installation and version
 * 5. pnpm installation and version
 * 6. Workspace discovery and validation
 *
 * Output formats:
 * - Default: Detailed output with colored status indicators
 * - Table: Structured table format for easy scanning
 * - JSON: Machine-readable format for CI/CD integration
 * - Quiet: Only show failures (useful for scripts)
 *
 * Exit codes:
 * - 0: All checks passed (system healthy)
 * - 1: One or more checks failed (action required)
 *
 * Examples:
 * ```bash
 * # Run all health checks with detailed output
 * php bin/cli system:doctor
 *
 * # Display results in table format
 * php bin/cli doctor --table
 *
 * # Output as JSON for CI/CD
 * php bin/cli doctor --json
 *
 * # Quiet mode (only show failures)
 * php bin/cli doctor --quiet
 * ```
 *
 * CI/CD Integration:
 * ```yaml
 * - name: Check system health
 *   run: php bin/cli doctor --json > health-report.json
 * ```
 *
 * @see BaseCommand For base command functionality
 */
#[AsCommand(
    name: 'system:doctor',
    description: 'Check system requirements and health',
    aliases: ['doctor', 'check', 'health'],
)]
final class DoctorCommand extends BaseCommand
{
    /**
     * Array to store check results for table/JSON output.
     *
     * Each result contains:
     * - component: Name of the component being checked
     * - status: Pass/Fail indicator
     * - details: Additional information about the check
     * - severity: critical, warning, or info
     * - passed: Boolean indicating if check passed
     *
     * @var array<array{component: string, status: string, details: string, severity: string, passed: bool}>
     */
    private array $checkResults = [];

    /**
     * Configure the command options.
     *
     * Defines command-line options for controlling output format and verbosity.
     *
     * Options:
     * - --table (-t): Display results in structured table format
     * - --json (-j): Output as JSON for CI/CD integration
     * - --quiet (-q): Only show failures (suppress success messages)
     *
     * Note: --json and --table are mutually exclusive
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('table', 't', InputOption::VALUE_NONE, 'Display results in table format')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output as JSON for CI/CD integration')
            ->addOption('quiet', 'q', InputOption::VALUE_NONE, 'Only show failures (quiet mode)');
    }

    /**
     * Execute the system health check command.
     *
     * Performs comprehensive health checks on the development environment
     * and displays results in the requested format.
     *
     * Execution flow:
     * 1. Validate option combinations (--json and --table are exclusive)
     * 2. Display intro message (unless JSON or quiet mode)
     * 3. Run all health checks sequentially:
     *    - PHP version and extensions
     *    - Composer installation
     *    - Turborepo installation
     *    - Node.js installation
     *    - pnpm installation
     *    - Workspace discovery
     * 4. Display results in requested format
     * 5. Show summary message
     * 6. Return appropriate exit code
     *
     * @param  InputInterface  $input  Command input interface
     * @param  OutputInterface $output Command output interface
     * @return int             Exit code (SUCCESS if all checks passed, FAILURE otherwise)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Parse output format options
        $useTable = $this->hasOption('table');
        $useJson = $this->hasOption('json');
        $quietMode = $this->hasOption('quiet');

        // Validate option combinations
        if ($useJson && $useTable) {
            $this->error('Cannot use --json and --table together');

            return Command::FAILURE;
        }

        // Display intro message (unless JSON or quiet mode)
        if (! $useJson && ! $quietMode) {
            $this->intro('Running system health checks...');
        }

        if (! $useTable && ! $useJson && ! $quietMode) {
            $this->line('');
        }

        // Track overall health status
        $allPassed = true;

        // =====================================================================
        // RUN HEALTH CHECKS
        // =====================================================================

        // Check PHP version and extensions
        if (! $this->checkPhp($useTable, $quietMode)) {
            $allPassed = false;
        }
        if (! $useTable && ! $useJson && ! $quietMode) {
            $this->line('');
        }

        // Check Composer installation
        if (! $this->checkComposer($useTable, $quietMode)) {
            $allPassed = false;
        }
        if (! $useTable && ! $useJson && ! $quietMode) {
            $this->line('');
        }

        // Check Turborepo installation
        if (! $this->checkTurbo($useTable, $quietMode)) {
            $allPassed = false;
        }
        if (! $useTable && ! $useJson && ! $quietMode) {
            $this->line('');
        }

        // Check Node.js installation
        if (! $this->checkNode($useTable, $quietMode)) {
            $allPassed = false;
        }
        if (! $useTable && ! $useJson && ! $quietMode) {
            $this->line('');
        }

        // Check pnpm installation
        if (! $this->checkPnpm($useTable, $quietMode)) {
            $allPassed = false;
        }
        if (! $useTable && ! $useJson && ! $quietMode) {
            $this->line('');
        }

        // Check workspace configuration
        if (! $this->checkWorkspaces($useTable, $quietMode)) {
            $allPassed = false;
        }
        if (! $useTable && ! $useJson && ! $quietMode) {
            $this->line('');
        }

        // =====================================================================
        // DISPLAY RESULTS
        // =====================================================================

        // JSON output for CI/CD
        if ($useJson) {
            return $this->displayJson($allPassed);
        }

        // Table output for structured view
        if ($useTable) {
            $this->displayTable();
        }

        // Quiet mode: only return exit code if all passed
        if ($quietMode && $allPassed) {
            return Command::SUCCESS;
        }

        // Display summary message
        if (! $quietMode) {
            if ($allPassed) {
                $this->outro('✓ All checks passed! System is healthy.');
            } else {
                $this->error('✗ Some checks failed. Please fix the issues above.');
            }
        }

        return $allPassed ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Check PHP version and required extensions.
     *
     * Validates that PHP meets minimum version requirements and that all
     * required extensions are loaded. This is critical for CLI functionality.
     *
     * Checks performed:
     * - PHP version >= 8.2.0 (minimum required)
     * - json extension (for JSON parsing)
     * - mbstring extension (for multibyte string handling)
     * - xml extension (for XML parsing)
     *
     * @param  bool $useTable  Whether to store results for table output
     * @param  bool $quietMode Whether to suppress success messages
     * @return bool True if all PHP checks passed, false otherwise
     */
    private function checkPhp(bool $useTable, bool $quietMode): bool
    {
        if (! $useTable && ! $quietMode) {
            $this->info('Checking PHP...');
        }

        // Check PHP version
        $version = PHP_VERSION;
        $required = '8.2.0';
        $passed = version_compare($version, $required, '>=');

        if ($useTable) {
            $this->checkResults[] = [
                'component' => 'PHP',
                'status' => $passed ? '✓ Pass' : '✗ Fail',
                'details' => $passed ? "Version: {$version}" : "Version: {$version} (required: >= {$required})",
                'severity' => $passed ? 'info' : 'critical',
                'passed' => $passed,
            ];
        } elseif ($passed) {
            if (! $quietMode) {
                $this->line("  ✓ PHP version: {$version}");
            }
        } else {
            $this->line("  ✗ PHP version: {$version} (required: >= {$required})");

            return false;
        }

        // Check required PHP extensions
        $requiredExtensions = ['json', 'mbstring', 'xml'];
        $allExtensionsLoaded = true;

        foreach ($requiredExtensions as $requiredExtension) {
            $loaded = extension_loaded($requiredExtension);

            if ($useTable) {
                $this->checkResults[] = [
                    'component' => "- {$requiredExtension}",
                    'status' => $loaded ? '✓ Pass' : '✗ Fail',
                    'details' => $loaded ? 'Extension loaded' : 'Extension missing',
                    'severity' => $loaded ? 'info' : 'critical',
                    'passed' => $loaded,
                ];
            } elseif ($loaded) {
                if (! $quietMode) {
                    $this->line("  ✓ Extension: {$requiredExtension}");
                }
            } else {
                $this->line("  ✗ Extension: {$requiredExtension} (missing)");
                $allExtensionsLoaded = false;
            }

            if (! $loaded) {
                $allExtensionsLoaded = false;
            }
        }

        return $passed && $allExtensionsLoaded;
    }

    /**
     * Check Composer installation and version.
     *
     * Validates that Composer is installed and accessible. Composer is
     * required for PHP dependency management in the monorepo.
     *
     * @param  bool $useTable  Whether to store results for table output
     * @param  bool $quietMode Whether to suppress success messages
     * @return bool True if Composer is installed, false otherwise
     */
    private function checkComposer(bool $useTable, bool $quietMode): bool
    {
        if (! $useTable && ! $quietMode) {
            $this->info('Checking Composer...');
        }

        if (! $this->hasComposer()) {
            if ($useTable) {
                $this->checkResults[] = [
                    'component' => 'Composer',
                    'status' => '✗ Fail',
                    'details' => 'Not found',
                    'severity' => 'critical',
                    'passed' => false,
                ];
            } else {
                $this->line('  ✗ Composer not found');
            }

            return false;
        }

        $version = $this->getComposerVersion();

        if ($useTable) {
            $this->checkResults[] = [
                'component' => 'Composer',
                'status' => '✓ Pass',
                'details' => "Version: {$version}",
                'severity' => 'info',
                'passed' => true,
            ];
        } elseif (! $quietMode) {
            $this->line("  ✓ Composer version: {$version}");
        }

        return true;
    }

    /**
     * Check Turborepo installation and version.
     *
     * Validates that Turborepo is installed globally. Turborepo is the
     * build system used for managing monorepo tasks and caching.
     *
     * Installation: npm install -g turbo
     *
     * @param  bool $useTable  Whether to store results for table output
     * @param  bool $quietMode Whether to suppress success messages
     * @return bool True if Turborepo is installed, false otherwise
     */
    private function checkTurbo(bool $useTable, bool $quietMode): bool
    {
        if (! $useTable && ! $quietMode) {
            $this->info('Checking Turbo...');
        }

        $process = Process::fromShellCommandline('turbo --version');
        $process->run();

        if (! $process->isSuccessful()) {
            if ($useTable) {
                $this->checkResults[] = [
                    'component' => 'Turbo',
                    'status' => '✗ Fail',
                    'details' => 'Not found (npm install -g turbo)',
                    'severity' => 'warning',
                    'passed' => false,
                ];
            } else {
                $this->line('  ✗ Turbo not found');
                if (! $quietMode) {
                    $this->comment('  Install: npm install -g turbo');
                }
            }

            return false;
        }

        $version = trim($process->getOutput());

        if ($useTable) {
            $this->checkResults[] = [
                'component' => 'Turbo',
                'status' => '✓ Pass',
                'details' => "Version: {$version}",
                'severity' => 'info',
                'passed' => true,
            ];
        } elseif (! $quietMode) {
            $this->line("  ✓ Turbo version: {$version}");
        }

        return true;
    }

    /**
     * Check Node.js installation and version.
     *
     * Validates that Node.js is installed. Node.js is required for
     * JavaScript/TypeScript tooling and frontend build processes.
     *
     * @param  bool $useTable  Whether to store results for table output
     * @param  bool $quietMode Whether to suppress success messages
     * @return bool True if Node.js is installed, false otherwise
     */
    private function checkNode(bool $useTable, bool $quietMode): bool
    {
        if (! $useTable && ! $quietMode) {
            $this->info('Checking Node.js...');
        }

        $process = Process::fromShellCommandline('node --version');
        $process->run();

        if (! $process->isSuccessful()) {
            if ($useTable) {
                $this->checkResults[] = [
                    'component' => 'Node.js',
                    'status' => '✗ Fail',
                    'details' => 'Not found',
                    'severity' => 'warning',
                    'passed' => false,
                ];
            } else {
                $this->line('  ✗ Node.js not found');
            }

            return false;
        }

        $version = trim($process->getOutput());

        if ($useTable) {
            $this->checkResults[] = [
                'component' => 'Node.js',
                'status' => '✓ Pass',
                'details' => "Version: {$version}",
                'severity' => 'info',
                'passed' => true,
            ];
        } elseif (! $quietMode) {
            $this->line("  ✓ Node.js version: {$version}");
        }

        return true;
    }

    /**
     * Check pnpm installation and version.
     *
     * Validates that pnpm is installed globally. pnpm is the package
     * manager used for managing JavaScript dependencies in the monorepo.
     *
     * Installation: npm install -g pnpm
     *
     * @param  bool $useTable  Whether to store results for table output
     * @param  bool $quietMode Whether to suppress success messages
     * @return bool True if pnpm is installed, false otherwise
     */
    private function checkPnpm(bool $useTable, bool $quietMode): bool
    {
        if (! $useTable && ! $quietMode) {
            $this->info('Checking pnpm...');
        }

        $process = Process::fromShellCommandline('pnpm --version');
        $process->run();

        if (! $process->isSuccessful()) {
            if ($useTable) {
                $this->checkResults[] = [
                    'component' => 'pnpm',
                    'status' => '✗ Fail',
                    'details' => 'Not found (npm install -g pnpm)',
                    'severity' => 'warning',
                    'passed' => false,
                ];
            } else {
                $this->line('  ✗ pnpm not found');
                if (! $quietMode) {
                    $this->comment('  Install: npm install -g pnpm');
                }
            }

            return false;
        }

        $version = trim($process->getOutput());

        if ($useTable) {
            $this->checkResults[] = [
                'component' => 'pnpm',
                'status' => '✓ Pass',
                'details' => "Version: {$version}",
                'severity' => 'info',
                'passed' => true,
            ];
        } elseif (! $quietMode) {
            $this->line("  ✓ pnpm version: {$version}");
        }

        return true;
    }

    /**
     * Check workspace configuration and discovery.
     *
     * Validates that workspaces are properly configured and can be discovered.
     * Displays counts of apps and packages found in the monorepo.
     *
     * This check ensures:
     * - pnpm-workspace.yaml is properly configured
     * - Workspaces can be discovered
     * - Apps and packages are correctly categorized
     *
     * @param  bool $useTable  Whether to store results for table output
     * @param  bool $quietMode Whether to suppress success messages
     * @return bool True if workspaces are found, false otherwise
     */
    private function checkWorkspaces(bool $useTable, bool $quietMode): bool
    {
        if (! $useTable && ! $quietMode) {
            $this->info('Checking workspaces...');
        }

        $workspaces = $this->getWorkspaces();

        if ($workspaces->isEmpty()) {
            if ($useTable) {
                $this->checkResults[] = [
                    'component' => 'Workspaces',
                    'status' => '✗ Fail',
                    'details' => 'No workspaces found',
                    'severity' => 'critical',
                    'passed' => false,
                ];
            } else {
                $this->line('  ✗ No workspaces found');
            }

            return false;
        }

        $apps = $this->getApps();
        $packages = $this->getPackages();
        $workspaceCount = $workspaces->count();
        $appCount = $apps->count();
        $packageCount = $packages->count();

        if ($useTable) {
            $details = "{$workspaceCount} workspace(s) ({$appCount} app(s), {$packageCount} package(s))";
            $this->checkResults[] = [
                'component' => 'Workspaces',
                'status' => '✓ Pass',
                'details' => $details,
                'severity' => 'info',
                'passed' => true,
            ];
        } elseif (! $quietMode) {
            $this->line("  ✓ Found {$workspaceCount} workspace(s)");
            $this->line("    - Apps: {$appCount}");
            $this->line("    - Packages: {$packageCount}");
        }

        return true;
    }

    /**
     * Display check results in table format.
     *
     * Renders all health check results in a structured table with columns:
     * - Component: Name of the component checked
     * - Status: Pass/Fail indicator
     * - Severity: critical, warning, or info
     * - Details: Additional information about the check
     *
     * This format is useful for:
     * - Quick scanning of results
     * - Identifying failures at a glance
     * - Understanding severity levels
     */
    private function displayTable(): void
    {
        $this->line('');

        $headers = ['Component', 'Status', 'Severity', 'Details'];
        $rows = [];

        foreach ($this->checkResults as $checkResult) {
            $rows[] = [
                (string) $checkResult['component'],
                (string) $checkResult['status'],
                (string) $checkResult['severity'],
                (string) $checkResult['details'],
            ];
        }

        /* @var array<int, array<int, string>> $rows */
        $this->table($headers, $rows);
        $this->line('');
    }

    /**
     * Display check results in JSON format.
     *
     * Outputs health check results as JSON for CI/CD integration and
     * automated processing. The JSON structure includes:
     * - status: Overall health status (healthy/unhealthy)
     * - summary: Aggregated statistics
     * - checks: Detailed results for each check
     * - timestamp: ISO 8601 timestamp
     *
     * JSON structure:
     * ```json
     * {
     *   "status": "healthy",
     *   "summary": {
     *     "total": 10,
     *     "passed": 10,
     *     "failed": 0,
     *     "critical": 0,
     *     "warning": 0,
     *     "info": 10
     *   },
     *   "checks": [...],
     *   "timestamp": "2026-02-12T10:30:00+00:00"
     * }
     * ```
     *
     * @param  bool $allPassed Whether all checks passed
     * @return int  Exit code (SUCCESS if all passed, FAILURE otherwise)
     */
    private function displayJson(bool $allPassed): int
    {
        $checks = [];
        $summary = [
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
            'critical' => 0,
            'warning' => 0,
            'info' => 0,
        ];

        foreach ($this->checkResults as $checkResult) {
            $checks[] = [
                'component' => $checkResult['component'],
                'passed' => $checkResult['passed'],
                'severity' => $checkResult['severity'],
                'details' => $checkResult['details'],
            ];

            $summary['total']++;
            if ($checkResult['passed']) {
                $summary['passed']++;
            } else {
                $summary['failed']++;
            }
            $summary[$checkResult['severity']]++;
        }

        $output = [
            'status' => $allPassed ? 'healthy' : 'unhealthy',
            'summary' => $summary,
            'checks' => $checks,
            'timestamp' => date('c'),
        ];

        $jsonOutput = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($jsonOutput !== false) {
            $this->line($jsonOutput);
        }

        return $allPassed ? Command::SUCCESS : Command::FAILURE;
    }
}
