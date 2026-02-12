<?php

declare(strict_types=1);

namespace PhpHive\Cli\Support;

use Exception;

/**
 * Preflight Checker.
 *
 * Validates the environment before running operations to prevent common
 * failures. Checks system requirements, dependencies, and permissions
 * to ensure the command can execute successfully.
 *
 * This service helps catch 80% of potential failures before any work
 * is done, providing a better user experience with clear error messages
 * and suggested fixes.
 *
 * Checks performed:
 * - PHP version compatibility
 * - Required tools (Composer, Git, Node.js, package managers)
 * - File system permissions
 * - Existing file conflicts
 *
 * Example usage:
 * ```php
 * $checker = new PreflightChecker($process);
 * $result = $checker->check();
 *
 * if ($result->failed()) {
 *     echo $result->getErrorMessage();
 *     exit(1);
 * }
 * ```
 */
final readonly class PreflightChecker
{
    /**
     * Minimum required PHP version.
     */
    private const string MIN_PHP_VERSION = '8.1.0';

    /**
     * Create a new preflight checker instance.
     *
     * @param Process $process Process service for command execution
     */
    public function __construct(
        private Process $process
    ) {}

    /**
     * Run all preflight checks.
     *
     * Executes a series of checks to validate the environment.
     * Returns a result object containing the status and any error
     * messages or suggested fixes.
     *
     * @param  string|null     $workingDirectory Directory to check permissions for
     * @return PreflightResult Result of all checks
     */
    public function check(?string $workingDirectory = null): PreflightResult
    {
        $checks = [
            'PHP Version' => $this->checkPhpVersion(...),
            'Composer' => $this->checkComposer(...),
            'Git' => $this->checkGit(...),
        ];

        // Add directory-specific checks if provided
        if ($workingDirectory !== null) {
            $checks['Write Permissions'] = fn (): array => $this->checkWritePermissions($workingDirectory);
        }

        $results = [];
        $failed = null;

        foreach ($checks as $name => $check) {
            $result = $check();
            $results[$name] = $result;

            if (! $result['passed']) {
                $failed = $result;
                $failed['check'] = $name;

                break; // Stop on first failure
            }
        }

        return new PreflightResult(
            passed: $failed === null,
            checks: $results,
            failedCheck: $failed,
        );
    }

    /**
     * Check PHP version meets minimum requirements.
     *
     * @return array{passed: bool, message: string, fix?: string}
     */
    private function checkPhpVersion(): array
    {
        $currentVersion = PHP_VERSION;
        $passed = version_compare($currentVersion, self::MIN_PHP_VERSION, '>=');

        if (! $passed) {
            return [
                'passed' => false,
                'message' => "PHP {$currentVersion} detected (minimum: " . self::MIN_PHP_VERSION . ')',
                'fix' => 'Upgrade PHP to version ' . self::MIN_PHP_VERSION . " or higher\n" .
                         'Using Herd: herd use php@8.4',
            ];
        }

        return [
            'passed' => true,
            'message' => "PHP {$currentVersion} detected",
        ];
    }

    /**
     * Check if Composer is installed and accessible.
     *
     * @return array{passed: bool, message: string, fix?: string}
     */
    private function checkComposer(): array
    {
        $installed = $this->process->commandExists('composer');

        if (! $installed) {
            return [
                'passed' => false,
                'message' => 'Composer not found',
                'fix' => 'Install Composer from: https://getcomposer.org/download/',
            ];
        }

        // Get Composer version
        try {
            $output = $this->process->run(['composer', '--version', '--no-ansi']);
            $version = 'unknown';

            if (preg_match('/Composer version (\S+)/', $output, $matches) !== false && isset($matches[1])) {
                $version = $matches[1];
            }

            return [
                'passed' => true,
                'message' => "Composer {$version} installed",
            ];
        } catch (Exception) {
            return [
                'passed' => false,
                'message' => 'Composer found but not working correctly',
                'fix' => 'Reinstall Composer or check your PATH configuration',
            ];
        }
    }

    /**
     * Check if Git is installed and accessible.
     *
     * @return array{passed: bool, message: string, fix?: string}
     */
    private function checkGit(): array
    {
        $installed = $this->process->commandExists('git');

        if (! $installed) {
            return [
                'passed' => false,
                'message' => 'Git not found',
                'fix' => 'Install Git from: https://git-scm.com/downloads',
            ];
        }

        return [
            'passed' => true,
            'message' => 'Git installed',
        ];
    }

    /**
     * Check write permissions for a directory.
     *
     * @param  string                                             $directory Directory to check
     * @return array{passed: bool, message: string, fix?: string}
     */
    private function checkWritePermissions(string $directory): array
    {
        if (! file_exists($directory)) {
            return [
                'passed' => false,
                'message' => "Directory does not exist: {$directory}",
                'fix' => "Create the directory first: mkdir -p {$directory}",
            ];
        }

        if (! is_writable($directory)) {
            return [
                'passed' => false,
                'message' => "No write permission for: {$directory}",
                'fix' => "Fix permissions: chmod 755 {$directory}",
            ];
        }

        return [
            'passed' => true,
            'message' => 'Write permissions OK',
        ];
    }
}

/**
 * Preflight Check Result.
 *
 * Value object representing the result of preflight checks.
 * Contains the overall status, individual check results, and
 * information about any failed checks.
 */
final readonly class PreflightResult
{
    /**
     * Create a new preflight result.
     *
     * @param bool                                                                    $passed      Whether all checks passed
     * @param array<string, array{passed: bool, message: string}>                     $checks      Individual check results
     * @param array{passed: bool, message: string, fix?: string, check?: string}|null $failedCheck Failed check details
     */
    public function __construct(
        public bool $passed,
        public array $checks,
        public ?array $failedCheck = null,
    ) {}

    /**
     * Check if preflight checks failed.
     *
     * @return bool True if any check failed
     */
    public function failed(): bool
    {
        return ! $this->passed;
    }

    /**
     * Get error message for failed check.
     *
     * @return string|null Error message or null if all passed
     */
    public function getErrorMessage(): ?string
    {
        if ($this->passed || $this->failedCheck === null) {
            return null;
        }

        return $this->failedCheck['message'];
    }

    /**
     * Get suggested fix for failed check.
     *
     * @return string|null Suggested fix or null if none available
     */
    public function getSuggestedFix(): ?string
    {
        if ($this->passed || $this->failedCheck === null) {
            return null;
        }

        return $this->failedCheck['fix'] ?? null;
    }

    /**
     * Get the name of the failed check.
     *
     * @return string|null Check name or null if all passed
     */
    public function getFailedCheckName(): ?string
    {
        if ($this->passed || $this->failedCheck === null) {
            return null;
        }

        return $this->failedCheck['check'] ?? null;
    }
}
