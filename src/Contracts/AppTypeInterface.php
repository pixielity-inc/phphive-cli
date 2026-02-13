<?php

declare(strict_types=1);

namespace PhpHive\Cli\Contracts;

use PhpHive\Cli\Support\ConfigOperation;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface for different application types.
 *
 * Each app type (Laravel, Symfony, Magento, etc.) implements this interface
 * to define its specific scaffolding behavior, questions, and installation commands.
 */
interface AppTypeInterface
{
    // =========================================================================
    // CONFIGURATION KEY CONSTANTS
    // =========================================================================

    /**
     * Application name configuration key.
     *
     * Used to store the application name in the configuration array.
     * This name is used for directory naming, package naming, and namespace generation.
     */
    public const CONFIG_NAME = 'name';

    /**
     * Application description configuration key.
     *
     * Used to store the application description in composer.json and documentation.
     */
    public const CONFIG_DESCRIPTION = 'description';

    /**
     * PHP version configuration key.
     *
     * Specifies the minimum PHP version requirement for the application.
     * Used in composer.json "require.php" constraint.
     */
    public const CONFIG_PHP_VERSION = 'php_version';

    /**
     * Laravel version configuration key.
     *
     * Specifies which Laravel version to install (e.g., '10', '11', '12').
     */
    public const CONFIG_LARAVEL_VERSION = 'laravel_version';

    /**
     * Symfony version configuration key.
     *
     * Specifies which Symfony version to install (e.g., '6.4', '7.0', '7.1').
     */
    public const CONFIG_SYMFONY_VERSION = 'symfony_version';

    /**
     * Magento version configuration key.
     *
     * Specifies which Magento version to install (e.g., '2.4.6', '2.4.7').
     */
    public const CONFIG_MAGENTO_VERSION = 'magento_version';

    /**
     * Database driver configuration key.
     *
     * Specifies the database driver to use (e.g., 'mysql', 'pgsql', 'sqlite', 'sqlsrv').
     */
    public const CONFIG_DATABASE = 'database';

    /**
     * Starter kit configuration key.
     *
     * Specifies which starter kit to install (e.g., 'none', 'breeze', 'jetstream').
     * Used primarily for Laravel applications.
     */
    public const CONFIG_STARTER_KIT = 'starter_kit';

    /**
     * Project type configuration key.
     *
     * Specifies the project type (e.g., 'webapp', 'skeleton').
     * Used primarily for Symfony applications.
     */
    public const CONFIG_PROJECT_TYPE = 'project_type';

    /**
     * Install Maker Bundle configuration key.
     *
     * Boolean flag indicating whether to install Symfony Maker Bundle for code generation.
     */
    public const CONFIG_INSTALL_MAKER = 'install_maker';

    /**
     * Install Security Bundle configuration key.
     *
     * Boolean flag indicating whether to install Symfony Security Bundle for authentication.
     */
    public const CONFIG_INSTALL_SECURITY = 'install_security';

    /**
     * Install Horizon configuration key.
     *
     * Boolean flag indicating whether to install Laravel Horizon for queue monitoring.
     */
    public const CONFIG_INSTALL_HORIZON = 'install_horizon';

    /**
     * Install Telescope configuration key.
     *
     * Boolean flag indicating whether to install Laravel Telescope for debugging.
     */
    public const CONFIG_INSTALL_TELESCOPE = 'install_telescope';

    /**
     * Install Sanctum configuration key.
     *
     * Boolean flag indicating whether to install Laravel Sanctum for API authentication.
     */
    public const CONFIG_INSTALL_SANCTUM = 'install_sanctum';

    /**
     * Install Octane configuration key.
     *
     * Boolean flag indicating whether to install Laravel Octane for high-performance server.
     */
    public const CONFIG_INSTALL_OCTANE = 'install_octane';

    /**
     * Octane server configuration key.
     *
     * Specifies which Octane server to use (e.g., 'roadrunner', 'swoole', 'frankenphp').
     */
    public const CONFIG_OCTANE_SERVER = 'octane_server';

    /**
     * Include tests configuration key.
     *
     * Boolean flag indicating whether to include PHPUnit for testing.
     */
    public const CONFIG_INCLUDE_TESTS = 'include_tests';

    /**
     * Include quality tools configuration key.
     *
     * Boolean flag indicating whether to include quality tools (PHPStan, Pint).
     */
    public const CONFIG_INCLUDE_QUALITY_TOOLS = 'include_quality_tools';

    // =========================================================================
    // DATABASE CONFIGURATION CONSTANTS
    // =========================================================================

    /**
     * Database type configuration key.
     *
     * Specifies the database type (e.g., 'mysql', 'postgresql', 'sqlite').
     */
    public const CONFIG_DB_TYPE = 'db_type';

    /**
     * Database host configuration key.
     *
     * Specifies the database host address.
     */
    public const CONFIG_DB_HOST = 'db_host';

    /**
     * Database port configuration key.
     *
     * Specifies the database port number.
     */
    public const CONFIG_DB_PORT = 'db_port';

    /**
     * Database name configuration key.
     *
     * Specifies the database name.
     */
    public const CONFIG_DB_NAME = 'db_name';

    /**
     * Database user configuration key.
     *
     * Specifies the database username.
     */
    public const CONFIG_DB_USER = 'db_user';

    /**
     * Database password configuration key.
     *
     * Specifies the database password.
     */
    public const CONFIG_DB_PASSWORD = 'db_password';

    /**
     * Using Docker configuration key.
     *
     * Boolean flag indicating whether the application is using Docker.
     */
    public const CONFIG_USING_DOCKER = 'using_docker';

    // =========================================================================
    // WORKSPACE TYPE CONSTANTS
    // =========================================================================

    /**
     * Application workspace type identifier.
     *
     * Used to identify application-type workspaces in the monorepo.
     */
    public const WORKSPACE_TYPE_APP = 'app';

    /**
     * Package workspace type identifier.
     *
     * Used to identify package-type workspaces in the monorepo.
     */
    public const WORKSPACE_TYPE_PACKAGE = 'package';

    // =========================================================================
    // STUB VARIABLE CONSTANTS
    // =========================================================================

    /**
     * Application name stub variable.
     *
     * Placeholder for the original application name in stub templates.
     */
    public const STUB_APP_NAME = '{{APP_NAME}}';

    /**
     * Normalized application name stub variable.
     *
     * Placeholder for the normalized application name (lowercase, hyphenated) in stub templates.
     */
    public const STUB_APP_NAME_NORMALIZED = '{{APP_NAME_NORMALIZED}}';

    /**
     * Application namespace stub variable.
     *
     * Placeholder for the PascalCase namespace component in stub templates.
     */
    public const STUB_APP_NAMESPACE = '{{APP_NAMESPACE}}';

    /**
     * Package name stub variable.
     *
     * Placeholder for the full Composer package name in stub templates.
     */
    public const STUB_PACKAGE_NAME = '{{PACKAGE_NAME}}';

    /**
     * Description stub variable.
     *
     * Placeholder for the application description in stub templates.
     */
    public const STUB_DESCRIPTION = '{{DESCRIPTION}}';

    /**
     * PHP version stub variable.
     *
     * Placeholder for the PHP version in stub templates.
     */
    public const STUB_PHP_VERSION = '{{PHP_VERSION}}';

    /**
     * Database driver stub variable.
     *
     * Placeholder for the database driver in stub templates.
     */
    public const STUB_DATABASE_DRIVER = '{{DATABASE_DRIVER}}';

    /**
     * Laravel version stub variable.
     *
     * Placeholder for the Laravel version in stub templates.
     */
    public const STUB_LARAVEL_VERSION = '{{LARAVEL_VERSION}}';

    /**
     * Symfony version stub variable.
     *
     * Placeholder for the Symfony version in stub templates.
     */
    public const STUB_SYMFONY_VERSION = '{{SYMFONY_VERSION}}';

    /**
     * Magento version stub variable.
     *
     * Placeholder for the Magento version in stub templates.
     */
    public const STUB_MAGENTO_VERSION = '{{MAGENTO_VERSION}}';

    /**
     * Base URL stub variable.
     *
     * Placeholder for the base URL in stub templates.
     */
    public const STUB_BASE_URL = '{{BASE_URL}}';

    /**
     * Admin user stub variable.
     *
     * Placeholder for the admin username in stub templates.
     */
    public const STUB_ADMIN_USER = '{{ADMIN_USER}}';

    /**
     * Get the display name of the app type.
     */
    public function getName(): string;

    /**
     * Get a description of the app type.
     */
    public function getDescription(): string;

    /**
     * Ask all necessary questions and collect configuration.
     *
     * @return array<string, mixed> Configuration array
     */
    public function collectConfiguration(InputInterface $input, OutputInterface $output): array;

    /**
     * Get the installation command for this app type.
     *
     * @param array<string, mixed> $config Configuration from collectConfiguration
     */
    public function getInstallCommand(array $config): string;

    /**
     * Get commands to run before installation.
     *
     * @param  array<string, mixed> $config Configuration from collectConfiguration
     * @return array<string>        Array of commands to execute before installation
     */
    public function getPreInstallCommands(array $config): array;

    /**
     * Get additional setup commands to run after installation.
     *
     * @param  array<string, mixed> $config Configuration from collectConfiguration
     * @return array<string>        Array of commands to execute
     */
    public function getPostInstallCommands(array $config): array;

    /**
     * Get the stub directory path for this app type.
     */
    public function getStubPath(): string;

    /**
     * Get variables to replace in stub files.
     *
     * @param  array<string, mixed>  $config Configuration from collectConfiguration
     * @return array<string, string> Key-value pairs for stub replacement
     */
    public function getStubVariables(array $config): array;

    /**
     * Setup infrastructure (database, Redis, queues, etc.) after app is created.
     *
     * This method is called after the application framework is installed and
     * prompts the user for infrastructure choices, then creates and configures
     * the necessary services (Docker containers, configuration files, etc.).
     *
     * @param  string               $appName Application name
     * @param  string               $appPath Full path to application directory
     * @param  array<string, mixed> $options Infrastructure options (needsDatabase, needsCache, etc.)
     * @return array<string, mixed> Infrastructure configuration
     */
    public function setupInfrastructure(string $appName, string $appPath, array $options = []): array;

    /**
     * Get configuration operations to be applied after installation.
     *
     * Returns an array of ConfigOperation objects that define what environment
     * and configuration files need to be created or modified after the application
     * is installed. This allows each AppType to declare its required configuration
     * in a structured, testable way.
     *
     * Operations can include:
     * - Setting environment variables in .env files
     * - Appending additional configuration
     * - Merging complex nested structures (for PHP config files)
     *
     * The operations are processed by a ConfigWriter service that handles
     * different file formats (.env, PHP arrays, etc.) and action types.
     *
     * @return array<ConfigOperation> Array of configuration operations
     *
     * @example
     * ```php
     * public function getWritableConfig(): array
     * {
     *     return [
     *         Config::set('.env', [
     *             'DATABASE_HOST' => 'db',
     *             'REDIS_HOST' => 'redis',
     *         ]),
     *         Config::merge('app/etc/env.php', [
     *             'session' => ['save' => 'redis'],
     *         ]),
     *     ];
     * }
     * ```
     */
    public function getWritableConfig(): array;
}
