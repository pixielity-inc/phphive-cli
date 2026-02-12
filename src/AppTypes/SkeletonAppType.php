<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Skeleton Application Type.
 *
 * This class handles the scaffolding of minimal PHP applications within the
 * monorepo. Unlike Laravel or Symfony which install full frameworks, the
 * Skeleton type creates a lightweight application structure with only the
 * essentials: Composer configuration, basic directory structure, and optional
 * quality tools.
 *
 * Perfect for:
 * - Microservices that don't need a full framework
 * - CLI tools and utilities
 * - Libraries and packages
 * - Custom applications with specific requirements
 * - Learning projects and prototypes
 *
 * Features supported:
 * - Configurable minimum PHP version (8.2, 8.4, 8.5)
 * - Optional PHPUnit for testing
 * - Optional quality tools (PHPStan, Pint)
 * - PSR-4 autoloading configuration
 * - Basic directory structure (src/, tests/, public/)
 *
 * The scaffolding process:
 * 1. Collect configuration through interactive prompts
 * 2. Create directory structure from stub templates
 * 3. Generate composer.json with selected dependencies
 * 4. Install Composer dependencies
 * 5. Run initial tests (if PHPUnit is included)
 *
 * File Operations:
 * All file operations use the Filesystem class via $this->filesystem() inherited
 * from AbstractAppType, providing consistent error handling and testability.
 *
 * Example configuration:
 * ```php
 * [
 *     'name' => 'my-service',
 *     'description' => 'A microservice',
 *     'php_version' => '8.3',
 *     'include_tests' => true,
 *     'include_quality_tools' => true,
 * ]
 * ```
 *
 * @see AbstractAppType
 * @see Filesystem
 */
class SkeletonAppType extends AbstractAppType
{
    /**
     * Get the display name of this application type.
     *
     * Returns a human-readable name shown in the application type selection menu.
     *
     * @return string The display name "Skeleton"
     */
    public function getName(): string
    {
        return 'Skeleton';
    }

    /**
     * Get a brief description of this application type.
     *
     * Returns a short description shown in the application type selection menu
     * to help users understand what this app type provides.
     *
     * @return string A brief description of the Skeleton type
     */
    public function getDescription(): string
    {
        return 'Minimal PHP application with Composer';
    }

    /**
     * Collect configuration through interactive prompts.
     *
     * This method guides the user through a series of interactive questions
     * to gather all necessary configuration for creating a skeleton application.
     *
     * Configuration collected:
     * - Application name and description
     * - Minimum PHP version (8.2, 8.4, 8.5)
     * - Whether to include PHPUnit for testing
     * - Whether to include quality tools (PHPStan, Pint)
     *
     * The configuration array is used by:
     * - getInstallCommand() - returns empty string (no framework to install)
     * - getPostInstallCommands() to run composer install and tests
     * - getStubVariables() to populate stub templates
     *
     * @param  InputInterface       $input  Console input interface for reading arguments/options
     * @param  OutputInterface      $output Console output interface for displaying messages
     * @return array<string, mixed> Configuration array with all collected settings
     */
    public function collectConfiguration(InputInterface $input, OutputInterface $output): array
    {
        // Store input/output for use in helper methods
        $this->input = $input;
        $this->output = $output;

        // Initialize configuration array
        $config = [];

        // =====================================================================
        // BASIC INFORMATION
        // =====================================================================

        // Application name - used for directory name, package name, and namespace
        $config['name'] = $this->text(
            label: 'Application name',
            placeholder: 'my-app',
            default: 'my-app',
            required: true
        );

        // Application description - used in composer.json and documentation
        $config['description'] = $this->text(
            label: 'Application description',
            placeholder: 'A PHP application',
            default: 'A PHP application',
            required: false
        );

        // =====================================================================
        // PHP VERSION
        // =====================================================================

        // Minimum PHP version requirement
        // This determines the "require.php" constraint in composer.json
        // and affects which language features can be used
        $config['php_version'] = $this->select(
            label: 'Minimum PHP version',
            options: [
                '8.5' => 'PHP 8.5',
                '8.4' => 'PHP 8.4',
                '8.3' => 'PHP 8.3',
                '8.2' => 'PHP 8.2',
            ],
            default: '8.3'
        );

        // =====================================================================
        // OPTIONAL FEATURES
        // =====================================================================

        // PHPUnit - Unit testing framework
        // Includes PHPUnit in require-dev and creates tests/ directory
        $config['include_tests'] = $this->confirm(
            label: 'Include PHPUnit for testing?',
            default: true
        );

        // Quality tools - Static analysis and code formatting
        // Includes PHPStan (static analysis) and Pint (code formatting)
        $config['include_quality_tools'] = $this->confirm(
            label: 'Include quality tools (PHPStan, Pint)?',
            default: true
        );

        return $config;
    }

    /**
     * Get the installation command.
     *
     * For skeleton applications, there is no framework to install via Composer.
     * Instead, the application structure is created entirely from stub templates.
     *
     * This method returns an empty string to indicate that no installation
     * command should be executed. The scaffolding process will:
     * 1. Copy stub files to the application directory
     * 2. Replace placeholders with actual values
     * 3. Run post-install commands (composer install, etc.)
     *
     * @param  array<string, mixed> $config Configuration from collectConfiguration()
     * @return string               Empty string (no installation command needed)
     */
    public function getInstallCommand(array $config): string
    {
        // No installation command - we create everything from stubs
        return '';
    }

    /**
     * Get post-installation commands to execute.
     *
     * Returns an array of shell commands to execute after the stub files
     * have been copied and processed. These commands install dependencies
     * and run initial setup tasks.
     *
     * Command execution order:
     * 1. Install Composer dependencies (including dev dependencies)
     * 2. Run PHPUnit tests (if included) to verify setup
     *
     * All commands are executed in the application directory and should
     * complete successfully before the scaffolding is considered complete.
     *
     * @param  array<string, mixed> $config Configuration from collectConfiguration()
     * @return array<string>        Array of shell commands to execute sequentially
     */
    public function getPostInstallCommands(array $config): array
    {
        // Initialize commands array with required setup
        $commands = [
            // Install all Composer dependencies (production and development)
            'composer install',
        ];

        // =====================================================================
        // OPTIONAL COMMANDS
        // =====================================================================

        // Run PHPUnit tests if testing is enabled
        // This verifies that the application structure is correct and
        // that all dependencies are properly installed
        if (($config['include_tests'] ?? true) === true) {
            $commands[] = 'composer test';
        }

        return $commands;
    }

    /**
     * Get the path to skeleton-specific stub templates.
     *
     * Returns the absolute path to the directory containing stub templates
     * specifically for skeleton applications. These stubs include:
     * - composer.json with minimal dependencies
     * - package.json for monorepo integration
     * - phpunit.xml (if tests are included)
     * - phpstan.neon (if quality tools are included)
     * - pint.json (if quality tools are included)
     * - Basic directory structure (src/, tests/, public/)
     * - Example classes and tests
     *
     * The stub files contain placeholders (e.g., {{APP_NAME}}) that are
     * replaced with actual values using getStubVariables().
     *
     * @return string Absolute path to cli/stubs/apps/skeleton directory
     */
    public function getStubPath(): string
    {
        // Get base stubs directory and append apps/skeleton subdirectory
        return $this->getBaseStubPath() . '/apps/skeleton';
    }

    /**
     * Get variables for stub template replacement.
     *
     * Returns an associative array of placeholder => value pairs used to
     * replace placeholders in stub template files. This method combines
     * common variables (from parent class) with skeleton-specific variables.
     *
     * Common variables (from AbstractAppType):
     * - {{APP_NAME}}: Original application name
     * - {{APP_NAME_NORMALIZED}}: Normalized directory/package name
     * - {{APP_NAMESPACE}}: PascalCase namespace component
     * - {{PACKAGE_NAME}}: Full Composer package name
     * - {{DESCRIPTION}}: Application description
     *
     * Skeleton-specific variables:
     * - {{PHP_VERSION}}: Selected minimum PHP version (8.2, 8.4, 8.5)
     *
     * Example stub usage:
     * ```json
     * {
     *   "name": "{{PACKAGE_NAME}}",
     *   "description": "{{DESCRIPTION}}",
     *   "require": {
     *     "php": "^{{PHP_VERSION}}"
     *   },
     *   "autoload": {
     *     "psr-4": {
     *       "PhpHive\\{{APP_NAMESPACE}}\\": "src/"
     *     }
     *   }
     * }
     * ```
     *
     * @param  array<string, mixed>  $config Configuration from collectConfiguration()
     * @return array<string, string> Associative array of placeholder => value pairs
     */
    public function getStubVariables(array $config): array
    {
        // Get common variables from parent class
        $common = $this->getCommonStubVariables($config);

        // Merge with skeleton-specific variables using spread operator
        return [
            ...$common,
            // PHP version for composer.json "require.php" constraint
            '{{PHP_VERSION}}' => $config['php_version'] ?? '8.3',
        ];
    }
}
