<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes;

use Override;
use PhpHive\Cli\Contracts\AppTypeInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Laravel Application Type.
 *
 * This class handles the scaffolding and configuration of Laravel applications
 * within the monorepo. Laravel is a full-stack PHP framework with elegant syntax,
 * powerful ORM (Eloquent), routing, authentication, and a rich ecosystem of packages.
 *
 * Features supported:
 * - Multiple Laravel versions (10, 11 LTS, 12 Latest)
 * - Starter kits (Breeze, Jetstream)
 * - Database configuration (MySQL, PostgreSQL, SQLite, SQL Server)
 * - Optional packages (Horizon, Telescope, Sanctum)
 * - Automatic key generation and migration
 *
 * The scaffolding process:
 * 1. Collect configuration through interactive prompts
 * 2. Create Laravel project using Composer
 * 3. Install selected starter kit and packages
 * 4. Configure database and run migrations
 * 5. Apply stub templates for monorepo integration
 *
 * File Operations:
 * All file operations use the Filesystem class via $this->filesystem() inherited
 * from AbstractAppType, providing consistent error handling and testability.
 *
 * Example configuration:
 * ```php
 * [
 *     'name' => 'api',
 *     'description' => 'REST API service',
 *     'laravel_version' => '12',
 *     'starter_kit' => 'none',
 *     'database' => 'mysql',
 *     'install_sanctum' => true,
 *     'install_horizon' => false,
 *     'install_telescope' => true,
 * ]
 * ```
 *
 * @see https://laravel.com Laravel Framework
 * @see AbstractAppType
 * @see Filesystem
 */
class LaravelAppType extends AbstractAppType
{
    /**
     * Get the display name of this application type.
     *
     * Returns a human-readable name shown in the application type selection menu.
     *
     * @return string The display name "Laravel"
     */
    public function getName(): string
    {
        return 'Laravel';
    }

    /**
     * Get a brief description of this application type.
     *
     * Returns a short description shown in the application type selection menu
     * to help users understand what this app type provides.
     *
     * @return string A brief description of Laravel
     */
    public function getDescription(): string
    {
        return 'Full-stack PHP framework with elegant syntax';
    }

    /**
     * Collect configuration through interactive prompts.
     *
     * This method guides the user through a series of interactive questions
     * to gather all necessary configuration for creating a Laravel application.
     *
     * Configuration collected:
     * - Application name and description
     * - Laravel version (10, 11 LTS, 12 Latest)
     * - Starter kit (None, Breeze, Jetstream)
     * - Database driver (MySQL, PostgreSQL, SQLite, SQL Server)
     * - Optional packages (Horizon, Telescope, Sanctum)
     *
     * The configuration array is used by:
     * - getInstallCommand() to determine the installation command
     * - getPostInstallCommands() to install additional packages
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
            placeholder: 'A Laravel application',
            required: false
        );

        // =====================================================================
        // LARAVEL VERSION
        // =====================================================================

        // Laravel version selection
        // - Version 12: Latest features and improvements
        // - Version 11: Long-term support (LTS) with extended maintenance
        // - Version 10: Previous stable version
        $config[AppTypeInterface::CONFIG_LARAVEL_VERSION] = $this->select(
            label: 'Laravel version',
            options: [
                'v12' => 'Laravel 12 (Latest)',
                'v11' => 'Laravel 11 (LTS)',
                'v10' => 'Laravel 10',
            ],
            default: 'v12'
        );

        // Extract version number (remove 'v' prefix)
        $config[AppTypeInterface::CONFIG_LARAVEL_VERSION] = ltrim((string) $config[AppTypeInterface::CONFIG_LARAVEL_VERSION], 'v');

        // =====================================================================
        // STARTER KIT
        // =====================================================================

        // Starter kit selection for authentication scaffolding
        // - None: No authentication scaffolding
        // - Breeze: Minimal authentication with Blade or Inertia
        // - Jetstream: Full-featured with teams, 2FA, and profile management
        $config[AppTypeInterface::CONFIG_STARTER_KIT] = $this->select(
            label: 'Starter kit',
            options: [
                'none' => 'None',
                'breeze' => 'Laravel Breeze (Simple authentication)',
                'jetstream' => 'Laravel Jetstream (Full-featured)',
            ],
            default: 'none'
        );

        // =====================================================================
        // DATABASE CONFIGURATION
        // =====================================================================

        // Database driver selection
        // Determines the default database connection in config/database.php
        $config[AppTypeInterface::CONFIG_DATABASE] = $this->select(
            label: 'Database driver',
            options: [
                'mysql' => 'MySQL',
                'pgsql' => 'PostgreSQL',
                'sqlite' => 'SQLite',
                'sqlsrv' => 'SQL Server',
            ],
            default: 'mysql'
        );

        // =====================================================================
        // ADDITIONAL FEATURES
        // =====================================================================

        // Laravel Horizon - Queue monitoring dashboard
        // Provides a beautiful dashboard and code-driven configuration for Redis queues
        $config[AppTypeInterface::CONFIG_INSTALL_HORIZON] = $this->confirm(
            label: 'Install Laravel Horizon (Queue monitoring)?',
            default: false
        );

        // Laravel Telescope - Debugging and insight tool
        // Provides insight into requests, exceptions, database queries, queued jobs, etc.
        $config[AppTypeInterface::CONFIG_INSTALL_TELESCOPE] = $this->confirm(
            label: 'Install Laravel Telescope (Debugging)?',
            default: false
        );

        // Laravel Sanctum - API authentication
        // Provides a simple token-based authentication system for SPAs and mobile apps
        $config[AppTypeInterface::CONFIG_INSTALL_SANCTUM] = $this->confirm(
            label: 'Install Laravel Sanctum (API authentication)?',
            default: true
        );

        // Laravel Octane - High-performance application server
        // Supercharges application performance using Swoole, RoadRunner, or FrankenPHP
        $config[AppTypeInterface::CONFIG_INSTALL_OCTANE] = $this->confirm(
            label: 'Install Laravel Octane (High-performance server)?',
            default: false
        );

        // If Octane is selected, ask which server to use
        if ($config[AppTypeInterface::CONFIG_INSTALL_OCTANE] === true) {
            $config[AppTypeInterface::CONFIG_OCTANE_SERVER] = $this->select(
                label: 'Octane server',
                options: [
                    'roadrunner' => 'RoadRunner (Pure PHP, no extensions required)',
                    'frankenphp' => 'FrankenPHP (Modern, built on Caddy)',
                    'swoole' => 'Swoole (Requires PHP extension via PECL)',
                ],
                default: 'roadrunner'
            );
        }

        return $config;
    }

    /**
     * Get the Composer command to install Laravel.
     *
     * Generates the Composer create-project command to install Laravel
     * with the specified version. The command creates a new Laravel project
     * in the current directory.
     *
     * Command format:
     * ```bash
     * composer create-project laravel/laravel:{version}.x . --prefer-dist
     * ```
     *
     * The --prefer-dist flag ensures distribution packages are downloaded
     * instead of cloning repositories, which is faster and more reliable.
     *
     * @param  array<string, mixed> $config Configuration from collectConfiguration()
     * @return string               The Composer command to execute
     */
    public function getInstallCommand(array $config): string
    {
        // Extract Laravel version from config, default to version 12
        $version = $config[AppTypeInterface::CONFIG_LARAVEL_VERSION] ?? '12';

        // Return Composer create-project command with version constraint
        // The .x allows any patch version (e.g., 12.0, 12.1, 12.2)
        return "composer create-project laravel/laravel:{$version}.x . --prefer-dist";
    }

    /**
     * Get post-installation commands to execute.
     *
     * Returns an array of shell commands to execute after the base Laravel
     * installation is complete. These commands install additional packages,
     * configure the application, and run initial setup tasks.
     *
     * Command execution order:
     * 1. Generate application key (required for encryption)
     * 2. Install and configure starter kit (if selected)
     * 3. Install additional packages (Horizon, Telescope, Sanctum)
     * 4. Run database migrations
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
            // Generate application encryption key (required for Laravel)
            'php artisan key:generate',
        ];

        // =====================================================================
        // MONOREPO MODULES SUPPORT
        // =====================================================================

        // Allow wikimedia/composer-merge-plugin (required by nwidart/laravel-modules)
        $commands[] = 'composer config --no-plugins allow-plugins.wikimedia/composer-merge-plugin true';

        // Install Laravel Modules package for modular development
        // This allows the app to load modules from the monorepo packages directory
        $commands[] = 'composer require nwidart/laravel-modules';

        // Publish the modules configuration file
        $commands[] = 'php artisan vendor:publish --provider="Nwidart\Modules\LaravelModulesServiceProvider"';

        // Note: Manual configuration of config/modules.php may be needed to add monorepo packages path
        // The modules config should include: 'packages' => base_path('../../../packages')

        // =====================================================================
        // STARTER KIT INSTALLATION
        // =====================================================================

        // Install Laravel Breeze if selected
        // Breeze provides minimal authentication scaffolding
        if (($config[AppTypeInterface::CONFIG_STARTER_KIT] ?? 'none') === 'breeze') {
            // Install Breeze as a dev dependency
            $commands[] = 'composer require laravel/breeze --dev';

            // Run Breeze installation (creates auth views, routes, controllers)
            $commands[] = 'php artisan breeze:install';
        }

        // Install Laravel Jetstream if selected
        // Jetstream provides full-featured authentication with teams and 2FA
        elseif (($config[AppTypeInterface::CONFIG_STARTER_KIT] ?? 'none') === 'jetstream') {
            // Install Jetstream as a production dependency
            $commands[] = 'composer require laravel/jetstream';

            // Run Jetstream installation with Livewire stack
            // (Alternative: inertia for Vue.js/React)
            $commands[] = 'php artisan jetstream:install livewire';
        }

        // =====================================================================
        // ADDITIONAL PACKAGES
        // =====================================================================

        // Install Laravel Horizon if requested
        // Horizon provides a dashboard for monitoring Redis queues
        if (($config[AppTypeInterface::CONFIG_INSTALL_HORIZON] ?? false) === true) {
            $commands[] = 'composer require laravel/horizon';
            $commands[] = 'php artisan horizon:install';
        }

        // Install Laravel Telescope if requested
        // Telescope provides debugging and insight into application behavior
        if (($config[AppTypeInterface::CONFIG_INSTALL_TELESCOPE] ?? false) === true) {
            $commands[] = 'composer require laravel/telescope --dev';
            $commands[] = 'php artisan telescope:install';
        }

        // Install Laravel Sanctum if requested
        // Sanctum provides API token authentication
        if (($config[AppTypeInterface::CONFIG_INSTALL_SANCTUM] ?? false) === true) {
            // Publish Sanctum configuration and migrations
            $commands[] = 'php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"';
        }

        // Install Laravel Octane if requested
        // Octane provides high-performance application server with Swoole/RoadRunner/FrankenPHP
        // Using the user-selected server (default: RoadRunner - no PHP extensions required)
        if (($config[AppTypeInterface::CONFIG_INSTALL_OCTANE] ?? false) === true) {
            $server = $config[AppTypeInterface::CONFIG_OCTANE_SERVER] ?? 'roadrunner';

            // If Swoole is selected, check if it's installed and attempt automatic installation
            if ($server === 'swoole') {
                // Check if Swoole extension is installed
                $swooleInstalled = extension_loaded('swoole');

                if (! $swooleInstalled) {
                    $this->warning('Swoole PHP extension is not installed.');

                    // Ask if user wants to attempt automatic installation
                    $autoInstall = $this->confirm(
                        label: 'Would you like to attempt automatic Swoole installation?',
                        default: true,
                        hint: 'This will use PECL or Homebrew depending on your system'
                    );

                    if ($autoInstall) {
                        $this->info('Attempting to install Swoole...');

                        // Detect OS and try appropriate installation method
                        $os = PHP_OS_FAMILY;
                        $installSuccess = false;

                        if ($os === 'Darwin') {
                            // macOS - try Homebrew first
                            $this->info('Detected macOS - trying Homebrew installation...');
                            exec('which brew', $output, $brewExists);

                            if ($brewExists === 0) {
                                exec('brew install swoole 2>&1', $output, $result);
                                $installSuccess = ($result === 0);
                            } else {
                                $this->warning('Homebrew not found. Trying PECL...');
                                exec('pecl install swoole 2>&1', $output, $result);
                                $installSuccess = ($result === 0);
                            }
                        } else {
                            // Linux or other - try PECL
                            $this->info('Trying PECL installation...');
                            exec('pecl install swoole 2>&1', $output, $result);
                            $installSuccess = ($result === 0);
                        }

                        if ($installSuccess) {
                            $this->info('✓ Swoole installation completed!');
                            $this->warning('You may need to restart your terminal/PHP-FPM for changes to take effect.');
                        } else {
                            $this->error('Automatic installation failed.');
                            $this->note(
                                "Please install Swoole manually:\n\n" .
                                "  macOS (with Homebrew):\n" .
                                "    brew install swoole\n\n" .
                                "  Linux (with PECL):\n" .
                                "    pecl install swoole\n\n" .
                                '  Or use Docker with a Swoole-enabled PHP image.',
                                'Manual Installation'
                            );
                        }
                    } else {
                        $this->note(
                            "To install Swoole manually:\n\n" .
                            "  macOS (with Homebrew):\n" .
                            "    brew install swoole\n\n" .
                            "  Linux (with PECL):\n" .
                            "    pecl install swoole\n\n" .
                            '  Or use Docker with a Swoole-enabled PHP image.',
                            'Manual Installation'
                        );
                    }

                    $this->pause('Press enter to continue with Octane installation...');

                    // Note: extension_loaded() won't detect newly installed extensions in the same process
                    // User will need to restart terminal/PHP for it to be available
                    $this->warning('Note: If Swoole was just installed, you may need to restart your terminal.');
                }
            }

            $commands[] = 'composer require laravel/octane';
            $commands[] = "php artisan octane:install --server={$server}";
        }

        // =====================================================================
        // DATABASE SETUP
        // =====================================================================

        // Run database migrations to create tables
        // This includes migrations from Laravel, starter kits, and packages
        $commands[] = 'php artisan migrate';

        return $commands;
    }

    /**
     * Get the path to Laravel-specific stub templates.
     *
     * Returns the absolute path to the directory containing stub templates
     * specifically for Laravel applications. These stubs include:
     * - composer.json with Laravel-specific dependencies
     * - package.json for frontend assets
     * - phpunit.xml for testing configuration
     * - .env.example with Laravel environment variables
     * - Monorepo-specific configuration files
     *
     * The stub files contain placeholders (e.g., {{APP_NAME}}) that are
     * replaced with actual values using getStubVariables().
     *
     * @return string Absolute path to cli/stubs/apps/laravel directory
     */
    public function getStubPath(): string
    {
        // Get base stubs directory and append apps/laravel subdirectory
        return $this->getBaseStubPath() . '/apps/laravel';
    }

    /**
     * Get variables for stub template replacement.
     *
     * Returns an associative array of placeholder => value pairs used to
     * replace placeholders in stub template files. This method combines
     * common variables (from parent class) with Laravel-specific variables.
     *
     * Common variables (from AbstractAppType):
     * - {{APP_NAME}}: Original application name
     * - {{APP_NAME_NORMALIZED}}: Normalized directory/package name
     * - {{APP_NAMESPACE}}: PascalCase namespace component
     * - {{PACKAGE_NAME}}: Full Composer package name
     * - {{DESCRIPTION}}: Application description
     *
     * Laravel-specific variables:
     * - {{DATABASE_DRIVER}}: Selected database driver (mysql, pgsql, etc.)
     * - {{LARAVEL_VERSION}}: Selected Laravel version (10, 11, 12)
     *
     * Example stub usage:
     * ```json
     * {
     *   "name": "{{PACKAGE_NAME}}",
     *   "description": "{{DESCRIPTION}}",
     *   "require": {
     *     "laravel/framework": "^{{LARAVEL_VERSION}}.0"
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

        // Merge with Laravel-specific variables using spread operator
        return [
            ...$common,
            // Database driver for .env and config/database.php
            AppTypeInterface::STUB_DATABASE_DRIVER => $config[AppTypeInterface::CONFIG_DATABASE] ?? 'mysql',

            // Laravel version for composer.json constraints
            AppTypeInterface::STUB_LARAVEL_VERSION => $config[AppTypeInterface::CONFIG_LARAVEL_VERSION] ?? '12',
        ];
    }
}
