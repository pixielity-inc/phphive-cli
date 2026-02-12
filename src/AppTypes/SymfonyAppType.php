<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes;

use Override;
use PhpHive\Cli\Contracts\AppTypeInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Symfony Application Type.
 *
 * This class handles the scaffolding and configuration of Symfony applications
 * within the monorepo. Symfony is a high-performance PHP framework for web
 * applications, known for its flexibility, reusable components, and strong
 * focus on best practices.
 *
 * Features supported:
 * - Multiple Symfony versions (6.4 LTS, 7.1 LTS, 7.2 Latest)
 * - Project types (Full web application or minimal microservice/API)
 * - Database configuration (MySQL, PostgreSQL, SQLite)
 * - Optional bundles (Maker, Security)
 * - Doctrine ORM integration
 * - Automatic database creation and migrations
 *
 * The scaffolding process:
 * 1. Collect configuration through interactive prompts
 * 2. Create Symfony project using Composer (webapp or skeleton)
 * 3. Install selected bundles and packages
 * 4. Configure database and run migrations
 * 5. Apply stub templates for monorepo integration
 *
 * File Operations:
 * All file operations use the Filesystem class via $this->filesystem() inherited
 * from AbstractAppType, providing consistent error handling and testability.
 *
 * Project types:
 * - webapp: Full-featured web application with Twig, forms, security, etc.
 * - skeleton: Minimal microservice/API with only essential components
 *
 * Example configuration:
 * ```php
 * [
 *     'name' => 'api',
 *     'description' => 'REST API service',
 *     'symfony_version' => '7.2',
 *     'project_type' => 'skeleton',
 *     'database' => 'postgresql',
 *     'install_maker' => true,
 *     'install_security' => true,
 * ]
 * ```
 *
 * @see https://symfony.com Symfony Framework
 * @see AbstractAppType
 * @see Filesystem
 */
class SymfonyAppType extends AbstractAppType
{
    /**
     * Get the display name of this application type.
     *
     * Returns a human-readable name shown in the application type selection menu.
     *
     * @return string The display name "Symfony"
     */
    public function getName(): string
    {
        return 'Symfony';
    }

    /**
     * Get a brief description of this application type.
     *
     * Returns a short description shown in the application type selection menu
     * to help users understand what this app type provides.
     *
     * @return string A brief description of Symfony
     */
    public function getDescription(): string
    {
        return 'High-performance PHP framework for web applications';
    }

    /**
     * Collect configuration through interactive prompts.
     *
     * This method guides the user through a series of interactive questions
     * to gather all necessary configuration for creating a Symfony application.
     *
     * Configuration collected:
     * - Application name and description
     * - Symfony version (6.4 LTS, 7.1 LTS, 7.2 Latest)
     * - Project type (Full webapp or minimal skeleton)
     * - Database driver (MySQL, PostgreSQL, SQLite)
     * - Optional bundles (Maker, Security)
     *
     * The configuration array is used by:
     * - getInstallCommand() to determine the installation command
     * - getPostInstallCommands() to install additional bundles
     * - getStubVariables() to populate stub templates
     *
     * @param  InputInterface       $input  Console input interface for reading arguments/options
     * @param  OutputInterface      $output Console output interface for displaying messages
     * @return array<string, mixed> Configuration array with all collected settings
     */
    #[Override]
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
        $config[AppTypeInterface::CONFIG_NAME] = $this->text(
            label: 'Application name',
            placeholder: 'my-app',
            required: true
        );

        // Application description - used in composer.json and documentation
        $config[AppTypeInterface::CONFIG_DESCRIPTION] = $this->text(
            label: 'Application description',
            placeholder: 'A Symfony application',
            required: false
        );

        // =====================================================================
        // SYMFONY VERSION
        // =====================================================================

        // Symfony version selection
        // - Version 7.1: Long-term support (LTS) with extended maintenance
        // - Version 7.0: Latest stable version
        // - Version 6.4: Previous LTS version for legacy compatibility
        $config[AppTypeInterface::CONFIG_SYMFONY_VERSION] = $this->select(
            label: 'Symfony version',
            options: [
                '7.1' => 'Symfony 7.1 (LTS)',
                '7.0' => 'Symfony 7.0',
                '6.4' => 'Symfony 6.4 (LTS)',
            ],
            default: '7.1'
        );

        // =====================================================================
        // PROJECT TYPE
        // =====================================================================

        // Project type selection determines which packages to install
        // - webapp: Installs symfony/webapp-pack for full-featured application
        // - skeleton: Minimal with only HTTP kernel and routing
        $config[AppTypeInterface::CONFIG_PROJECT_TYPE] = $this->select(
            label: 'Project type',
            options: [
                'webapp' => 'Web Application (Full-featured)',
                'skeleton' => 'Microservice/API (Minimal)',
            ],
            default: 'webapp'
        );

        // =====================================================================
        // DATABASE CONFIGURATION
        // =====================================================================

        // Database driver selection
        // Determines the default database connection in config/packages/doctrine.yaml
        $config[AppTypeInterface::CONFIG_DATABASE] = $this->select(
            label: 'Database driver',
            options: [
                'mysql' => 'MySQL',
                'postgresql' => 'PostgreSQL',
                'sqlite' => 'SQLite',
            ],
            default: 'mysql'
        );

        // =====================================================================
        // OPTIONAL BUNDLES
        // =====================================================================

        // Symfony Maker Bundle - Code generation tool
        // Provides console commands to generate controllers, entities, forms, etc.
        $config[AppTypeInterface::CONFIG_INSTALL_MAKER] = $this->confirm(
            label: 'Install Symfony Maker Bundle (Code generation)?',
            default: true
        );

        // Symfony Security Bundle - Authentication and authorization
        // Provides user authentication, authorization, and security features
        $config[AppTypeInterface::CONFIG_INSTALL_SECURITY] = $this->confirm(
            label: 'Install Security Bundle (Authentication)?',
            default: true
        );

        return $config;
    }

    /**
     * Get the Composer command to install Symfony.
     *
     * Generates the Composer create-project command to install Symfony
     * with the specified version and project type. The command creates
     * a new Symfony project in the current directory.
     *
     * Project types:
     * - webapp: symfony/skeleton with webapp pack (full-featured)
     * - skeleton: symfony/skeleton (minimal)
     *
     * Command format:
     * ```bash
     * composer create-project symfony/skeleton:{version}.* .
     * ```
     *
     * The .* allows any patch version (e.g., 7.1.0, 7.1.1, 7.1.2)
     *
     * Note: symfony/website-skeleton is abandoned. We now use symfony/skeleton
     * and install symfony/webapp-pack for full-featured applications.
     *
     * @param  array<string, mixed> $config Configuration from collectConfiguration()
     * @return string               The Composer command to execute
     */
    public function getInstallCommand(array $config): string
    {
        // Extract version from config
        $version = $config[AppTypeInterface::CONFIG_SYMFONY_VERSION] ?? '7.1';

        // Always use symfony/skeleton (website-skeleton is abandoned)
        return "composer create-project symfony/skeleton:{$version}.* .";
    }

    /**
     * Get post-installation commands to execute.
     *
     * Returns an array of shell commands to execute after the base Symfony
     * installation is complete. These commands install additional bundles,
     * configure the application, and run initial setup tasks.
     *
     * Command execution order:
     * 1. Install webapp pack (if full-featured app selected)
     * 2. Install optional bundles (Maker, Security)
     * 3. Install Doctrine ORM pack
     * 4. Create database if it doesn't exist
     * 5. Run database migrations
     *
     * All commands are executed in the application directory and should
     * complete successfully before the scaffolding is considered complete.
     *
     * @param  array<string, mixed> $config Configuration from collectConfiguration()
     * @return array<string>        Array of shell commands to execute sequentially
     */
    public function getPostInstallCommands(array $config): array
    {
        // Initialize commands array
        $commands = [];

        // =====================================================================
        // WEBAPP PACK (if full-featured app)
        // =====================================================================

        // Install webapp pack for full-featured applications
        // This includes Twig, forms, security, asset management, etc.
        if (($config[AppTypeInterface::CONFIG_PROJECT_TYPE] ?? 'webapp') === 'webapp') {
            $commands[] = 'composer require symfony/webapp-pack';
        }

        // =====================================================================
        // OPTIONAL BUNDLES
        // =====================================================================

        // Install Symfony Maker Bundle if requested
        // Maker provides code generation commands (make:controller, make:entity, etc.)
        if (($config[AppTypeInterface::CONFIG_INSTALL_MAKER] ?? true) === true) {
            $commands[] = 'composer require --dev symfony/maker-bundle';
        }

        // Install Symfony Security Bundle if requested
        // Security provides authentication, authorization, and user management
        if (($config[AppTypeInterface::CONFIG_INSTALL_SECURITY] ?? true) === true) {
            $commands[] = 'composer require symfony/security-bundle';
        }

        // =====================================================================
        // DATABASE SETUP
        // =====================================================================

        // Install Doctrine ORM pack (includes doctrine-bundle, doctrine-orm, etc.)
        $commands[] = 'composer require symfony/orm-pack';

        // Create database if it doesn't exist
        // The --if-not-exists flag prevents errors if database already exists
        $commands[] = 'php bin/console doctrine:database:create --if-not-exists';

        // Run database migrations to create tables
        // The --no-interaction flag runs migrations without prompting
        $commands[] = 'php bin/console doctrine:migrations:migrate --no-interaction';

        return $commands;
    }

    /**
     * Get the path to Symfony-specific stub templates.
     *
     * Returns the absolute path to the directory containing stub templates
     * specifically for Symfony applications. These stubs include:
     * - composer.json with Symfony-specific dependencies
     * - package.json for frontend assets
     * - phpunit.xml for testing configuration
     * - .env.example with Symfony environment variables
     * - Monorepo-specific configuration files
     *
     * The stub files contain placeholders (e.g., {{APP_NAME}}) that are
     * replaced with actual values using getStubVariables().
     *
     * @return string Absolute path to cli/stubs/apps/symfony directory
     */
    public function getStubPath(): string
    {
        // Get base stubs directory and append apps/symfony subdirectory
        return $this->getBaseStubPath() . '/apps/symfony';
    }

    /**
     * Get variables for stub template replacement.
     *
     * Returns an associative array of placeholder => value pairs used to
     * replace placeholders in stub template files. This method combines
     * common variables (from parent class) with Symfony-specific variables.
     *
     * Common variables (from AbstractAppType):
     * - {{APP_NAME}}: Original application name
     * - {{APP_NAME_NORMALIZED}}: Normalized directory/package name
     * - {{APP_NAMESPACE}}: PascalCase namespace component
     * - {{PACKAGE_NAME}}: Full Composer package name
     * - {{DESCRIPTION}}: Application description
     *
     * Symfony-specific variables:
     * - {{DATABASE_DRIVER}}: Selected database driver (mysql, postgresql, etc.)
     * - {{SYMFONY_VERSION}}: Selected Symfony version (6.4, 7.1, 7.2)
     *
     * Example stub usage:
     * ```json
     * {
     *   "name": "{{PACKAGE_NAME}}",
     *   "description": "{{DESCRIPTION}}",
     *   "require": {
     *     "symfony/framework-bundle": "^{{SYMFONY_VERSION}}"
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

        // Merge with Symfony-specific variables using spread operator
        return [
            ...$common,
            // Database driver for .env and config/packages/doctrine.yaml
            AppTypeInterface::STUB_DATABASE_DRIVER => $config[AppTypeInterface::CONFIG_DATABASE] ?? 'mysql',

            // Symfony version for composer.json constraints
            AppTypeInterface::STUB_SYMFONY_VERSION => $config[AppTypeInterface::CONFIG_SYMFONY_VERSION] ?? '7.2',
        ];
    }
}
