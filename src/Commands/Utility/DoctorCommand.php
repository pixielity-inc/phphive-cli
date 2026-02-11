<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Utility;

use function count;
use function extension_loaded;

use MonoPhp\Cli\Commands\BaseCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function trim;
use function version_compare;

/**
 * Doctor Command.
 *
 * This command performs comprehensive system health checks to verify that all
 * required tools and dependencies are properly installed and configured for
 * working with the monorepo. It acts as a diagnostic tool to help developers
 * quickly identify missing dependencies or configuration issues.
 *
 * The doctor command validates:
 * 1. PHP installation and version requirements
 * 2. Required PHP extensions (json, mbstring, xml)
 * 3. Composer installation and version
 * 4. Turborepo installation and version
 * 5. Node.js installation and version
 * 6. pnpm package manager installation
 * 7. Workspace configuration and discovery
 *
 * Health check process:
 * - Runs each check independently
 * - Reports success (✓) or failure (✗) for each check
 * - Provides version information for installed tools
 * - Suggests installation commands for missing tools
 * - Displays workspace statistics if configured correctly
 * - Returns overall pass/fail status
 *
 * Requirements checked:
 * - PHP >= 8.2.0
 * - PHP extensions: json, mbstring, xml
 * - Composer (any recent version)
 * - Turbo (for monorepo task orchestration)
 * - Node.js (for JavaScript tooling)
 * - pnpm (workspace package manager)
 * - Valid pnpm-workspace.yaml configuration
 *
 * Features:
 * - Comprehensive system validation
 * - Clear pass/fail indicators
 * - Version information display
 * - Installation suggestions for missing tools
 * - Workspace statistics (apps and packages count)
 * - Organized output with sections
 * - Overall health status summary
 * - Exit code indicates success/failure
 *
 * Example usage:
 * ```bash
 * # Run all health checks
 * ./cli/bin/mono doctor
 *
 * # Using aliases
 * ./cli/bin/mono check
 * ./cli/bin/mono health
 * ```
 *
 * Example output:
 * ```
 * Checking PHP...
 *   ✓ PHP version: 8.2.0
 *   ✓ Extension: json
 *   ✓ Extension: mbstring
 *
 * Checking Composer...
 *   ✓ Composer version: 2.6.5
 *
 * Checking Turbo...
 *   ✗ Turbo not found
 *   Install: npm install -g turbo
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see InteractsWithMonorepo For workspace discovery
 * @see VersionCommand For detailed version information
 */
#[AsCommand(
    name: 'doctor',
    description: 'Check system requirements and health',
    aliases: ['check', 'health'],
)]
final class DoctorCommand extends BaseCommand
{
    /**
     * Configure the command options.
     *
     * Common options (workspace, force, no-cache, no-interaction) are inherited from BaseCommand.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();
    }

    /**
     * Execute the doctor command.
     *
     * This method orchestrates all health checks in sequence:
     * 1. Displays intro banner
     * 2. Checks PHP version and extensions
     * 3. Checks Composer installation
     * 4. Checks Turbo installation
     * 5. Checks Node.js installation
     * 6. Checks pnpm installation
     * 7. Checks workspace configuration
     * 8. Reports overall health status
     *
     * Each check is independent and reports its own status. The command
     * tracks overall success and returns appropriate exit code.
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 if all checks pass, 1 if any check fails)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Display intro banner
        $this->intro('Running system health checks...');
        $this->line('');

        // Track overall health status
        // All checks must pass for overall success
        $allPassed = true;

        // Check PHP version and required extensions
        // Validates PHP >= 8.2.0 and json, mbstring, xml extensions
        if (! $this->checkPhp()) {
            $allPassed = false;
        }
        $this->line('');

        // Check Composer installation and version
        // Validates composer command is available
        if (! $this->checkComposer()) {
            $allPassed = false;
        }
        $this->line('');

        // Check Turbo installation and version
        // Validates turbo command is available for monorepo orchestration
        if (! $this->checkTurbo()) {
            $allPassed = false;
        }
        $this->line('');

        // Check Node.js installation and version
        // Validates node command is available for JavaScript tooling
        if (! $this->checkNode()) {
            $allPassed = false;
        }
        $this->line('');

        // Check pnpm installation and version
        // Validates pnpm command is available for workspace management
        if (! $this->checkPnpm()) {
            $allPassed = false;
        }
        $this->line('');

        // Check workspace configuration
        // Validates pnpm-workspace.yaml and discovers workspaces
        if (! $this->checkWorkspaces()) {
            $allPassed = false;
        }
        $this->line('');

        // Report overall health status
        if ($allPassed) {
            // All checks passed - system is healthy
            $this->outro('✓ All checks passed! System is healthy.');

            return Command::SUCCESS;
        }

        // One or more checks failed - system needs attention
        $this->error('✗ Some checks failed. Please fix the issues above.');

        return Command::FAILURE;
    }

    /**
     * Check PHP version and extensions.
     *
     * Validates that PHP version is >= 8.2.0 and that required extensions
     * (json, mbstring, xml) are loaded.
     *
     * @return bool True if all PHP checks pass, false otherwise
     */
    private function checkPhp(): bool
    {
        $this->info('Checking PHP...');

        $version = PHP_VERSION;
        $required = '8.2.0';

        if (version_compare($version, $required, '>=')) {
            $this->line("  ✓ PHP version: {$version}");
        } else {
            $this->line("  ✗ PHP version: {$version} (required: >= {$required})");

            return false;
        }

        // Check required extensions
        $requiredExtensions = ['json', 'mbstring', 'xml'];
        $allExtensionsLoaded = true;

        foreach ($requiredExtensions as $requiredExtension) {
            if (extension_loaded($requiredExtension)) {
                $this->line("  ✓ Extension: {$requiredExtension}");
            } else {
                $this->line("  ✗ Extension: {$requiredExtension} (missing)");
                $allExtensionsLoaded = false;
            }
        }

        return $allExtensionsLoaded;
    }

    /**
     * Check Composer installation.
     *
     * Validates that Composer is installed and accessible via the
     * hasComposer() method from BaseCommand.
     *
     * @return bool True if Composer is installed, false otherwise
     */
    private function checkComposer(): bool
    {
        $this->info('Checking Composer...');

        if (! $this->hasComposer()) {
            $this->line('  ✗ Composer not found');

            return false;
        }

        $version = $this->getComposerVersion();
        $this->line("  ✓ Composer version: {$version}");

        return true;
    }

    /**
     * Check Turbo installation.
     *
     * Validates that Turborepo is installed globally and accessible.
     * Provides installation instructions if not found.
     *
     * @return bool True if Turbo is installed, false otherwise
     */
    private function checkTurbo(): bool
    {
        $this->info('Checking Turbo...');

        $process = Process::fromShellCommandline('turbo --version');
        $process->run();

        if (! $process->isSuccessful()) {
            $this->line('  ✗ Turbo not found');
            $this->comment('  Install: npm install -g turbo');

            return false;
        }

        $version = trim($process->getOutput());
        $this->line("  ✓ Turbo version: {$version}");

        return true;
    }

    /**
     * Check Node.js installation.
     *
     * Validates that Node.js is installed and accessible via the
     * node command.
     *
     * @return bool True if Node.js is installed, false otherwise
     */
    private function checkNode(): bool
    {
        $this->info('Checking Node.js...');

        $process = Process::fromShellCommandline('node --version');
        $process->run();

        if (! $process->isSuccessful()) {
            $this->line('  ✗ Node.js not found');

            return false;
        }

        $version = trim($process->getOutput());
        $this->line("  ✓ Node.js version: {$version}");

        return true;
    }

    /**
     * Check pnpm installation.
     *
     * Validates that pnpm package manager is installed globally.
     * Provides installation instructions if not found.
     *
     * @return bool True if pnpm is installed, false otherwise
     */
    private function checkPnpm(): bool
    {
        $this->info('Checking pnpm...');

        $process = Process::fromShellCommandline('pnpm --version');
        $process->run();

        if (! $process->isSuccessful()) {
            $this->line('  ✗ pnpm not found');
            $this->comment('  Install: npm install -g pnpm');

            return false;
        }

        $version = trim($process->getOutput());
        $this->line("  ✓ pnpm version: {$version}");

        return true;
    }

    /**
     * Check workspace configuration.
     *
     * Validates that pnpm-workspace.yaml exists and workspaces can be
     * discovered. Displays statistics about apps and packages found.
     *
     * @return bool True if workspaces are configured correctly, false otherwise
     */
    private function checkWorkspaces(): bool
    {
        $this->info('Checking workspaces...');

        $workspaces = $this->getWorkspaces();

        if ($workspaces === []) {
            $this->line('  ✗ No workspaces found');

            return false;
        }

        $apps = $this->getApps();
        $packages = $this->getPackages();

        $this->line('  ✓ Found ' . count($workspaces) . ' workspace(s)');
        $this->line('    - Apps: ' . count($apps));
        $this->line('    - Packages: ' . count($packages));

        return true;
    }
}
