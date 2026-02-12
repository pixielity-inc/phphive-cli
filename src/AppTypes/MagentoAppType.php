<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes;

use function Laravel\Prompts\note;

use PhpHive\Cli\Concerns\InteractsWithDatabase;
use PhpHive\Cli\Concerns\InteractsWithDocker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Magento Application Type.
 *
 * This class handles the scaffolding and configuration of Magento applications
 * within the monorepo. Magento is an enterprise-grade e-commerce platform built
 * on PHP, offering powerful features for online stores, multi-store management,
 * and extensive customization capabilities.
 *
 * Features supported:
 * - Magento Open Source (Community Edition)
 * - Multiple Magento versions (2.4.6, 2.4.7 Latest)
 * - Database configuration (MySQL only - Magento requirement)
 * - Admin user creation
 * - Sample data installation
 * - Optional modules (Elasticsearch, Redis, Varnish)
 * - Multi-store configuration
 * - Automatic setup and compilation
 *
 * The scaffolding process:
 * 1. Collect configuration through interactive prompts
 * 2. Create Magento project using Composer
 * 3. Configure database and admin credentials
 * 4. Install sample data (optional)
 * 5. Run setup:install command
 * 6. Configure caching and search (Redis, Elasticsearch)
 * 7. Deploy static content and compile DI
 * 8. Apply stub templates for monorepo integration
 *
 * System requirements:
 * - PHP 8.1+ (8.2+ recommended)
 * - MySQL 8.0+ or MariaDB 10.4+
 * - Elasticsearch 7.x or 8.x (for search)
 * - Redis (optional, for caching)
 * - Varnish (optional, for full-page cache)
 *
 * Example configuration:
 * ```php
 * [
 *     'name' => 'shop',
 *     'description' => 'E-commerce store',
 *     'magento_version' => '2.4.7',
 *     'install_sample_data' => true,
 *     'admin_firstname' => 'Admin',
 *     'admin_lastname' => 'User',
 *     'admin_email' => 'admin@example.com',
 *     'admin_user' => 'admin',
 *     'admin_password' => 'Admin123!',
 *     'use_elasticsearch' => true,
 *     'use_redis' => true,
 * ]
 * ```
 *
 * @see https://magento.com Magento Platform
 * @see AbstractAppType
 */
class MagentoAppType extends AbstractAppType
{
    use InteractsWithDatabase;
    use InteractsWithDocker;

    /**
     * Get the display name of this application type.
     *
     * Returns a human-readable name shown in the application type selection menu.
     *
     * @return string The display name "Magento"
     */
    public function getName(): string
    {
        return 'Magento';
    }

    /**
     * Get a brief description of this application type.
     *
     * Returns a short description shown in the application type selection menu
     * to help users understand what this app type provides.
     *
     * @return string A brief description of Magento
     */
    public function getDescription(): string
    {
        return 'Enterprise e-commerce platform';
    }

    /**
     * Collect configuration through interactive prompts.
     *
     * This method guides the user through a series of interactive questions
     * to gather all necessary configuration for creating a Magento application.
     *
     * Configuration collected:
     * - Application name and description
     * - Magento version (2.4.6, 2.4.7 Latest)
     * - Database configuration (MySQL only)
     * - Admin user credentials
     * - Sample data installation
     * - Elasticsearch configuration
     * - Redis caching
     * - Store configuration (URL, language, currency, timezone)
     *
     * The configuration array is used by:
     * - getInstallCommand() to determine the installation command
     * - getPostInstallCommands() to run setup and configuration
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

        // Application name - used for directory name, package name, and store name
        $config['name'] = $this->askText(
            label: 'Application name',
            placeholder: 'my-shop',
            required: true
        );

        // Application description - used in composer.json and documentation
        $config['description'] = $this->askText(
            label: 'Application description',
            placeholder: 'A Magento e-commerce store',
            required: false
        );

        // =====================================================================
        // MAGENTO AUTHENTICATION
        // =====================================================================

        // Magento requires authentication keys from repo.magento.com
        // Users can get these keys from: https://marketplace.magento.com/customer/accessKeys/
        note('Get your keys from: https://marketplace.magento.com/customer/accessKeys/', 'Magento Authentication Keys');

        // Public key (username)
        $config['magento_public_key'] = $this->askText(
            label: 'Magento Public Key (username)',
            placeholder: 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            required: true
        );

        // Private key (password) - masked input for security
        $config['magento_private_key'] = $this->askPassword(
            label: 'Magento Private Key (password)',
            placeholder: 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            required: true
        );

        // =====================================================================
        // MAGENTO VERSION
        // =====================================================================

        // Magento version selection
        // - Version 2.4.7: Latest features and improvements
        // - Version 2.4.6: Previous stable version
        $config['magento_version'] = $this->askSelect(
            label: 'Magento version',
            options: [
                '2.4.7' => 'Magento 2.4.7 (Latest)',
                '2.4.6' => 'Magento 2.4.6',
            ],
            default: '2.4.7'
        );

        // =====================================================================
        // DATABASE CONFIGURATION
        // =====================================================================

        // Use Docker-first database setup (supports Docker and local MySQL)
        // This will automatically detect Docker, offer Docker setup, and fall back to local MySQL
        $appPath = getcwd() . '/apps/' . $config['name'];
        $dbConfig = $this->setupDatabase($config['name'], ['mysql', 'mariadb'], $appPath);

        // Merge database configuration into main config
        $config['db_host'] = $dbConfig['db_host'];
        $config['db_port'] = $dbConfig['db_port'];
        $config['db_name'] = $dbConfig['db_name'];
        $config['db_user'] = $dbConfig['db_user'];
        $config['db_password'] = $dbConfig['db_password'];

        // =====================================================================
        // ADMIN USER CONFIGURATION
        // =====================================================================

        // Admin first name
        $config['admin_firstname'] = $this->askText(
            label: 'Admin first name',
            placeholder: 'Admin',
            default: 'Admin',
            required: true
        );

        // Admin last name
        $config['admin_lastname'] = $this->askText(
            label: 'Admin last name',
            placeholder: 'User',
            default: 'User',
            required: true
        );

        // Admin email
        $config['admin_email'] = $this->askText(
            label: 'Admin email',
            placeholder: 'admin@example.com',
            default: 'admin@example.com',
            required: true
        );

        // Admin username
        $config['admin_user'] = $this->askText(
            label: 'Admin username',
            placeholder: 'admin',
            default: 'admin',
            required: true
        );

        // Admin password
        $config['admin_password'] = $this->askText(
            label: 'Admin password (min 7 chars, must include letters and numbers)',
            placeholder: 'Admin123!',
            default: 'Admin123!',
            required: true
        );

        // =====================================================================
        // STORE CONFIGURATION
        // =====================================================================

        // Base URL
        $config['base_url'] = $this->askText(
            label: 'Base URL',
            placeholder: 'http://localhost/',
            default: 'http://localhost/',
            required: true
        );

        // Language
        $config['language'] = $this->askSelect(
            label: 'Default language',
            options: [
                'en_US' => 'English (United States)',
                'en_GB' => 'English (United Kingdom)',
                'fr_FR' => 'French (France)',
                'de_DE' => 'German (Germany)',
                'es_ES' => 'Spanish (Spain)',
            ],
            default: 'en_US'
        );

        // Currency
        $config['currency'] = $this->askSelect(
            label: 'Default currency',
            options: [
                'USD' => 'US Dollar (USD)',
                'EUR' => 'Euro (EUR)',
                'GBP' => 'British Pound (GBP)',
            ],
            default: 'USD'
        );

        // Timezone
        $config['timezone'] = $this->askSelect(
            label: 'Default timezone',
            options: [
                'America/New_York' => 'America/New_York (EST)',
                'America/Chicago' => 'America/Chicago (CST)',
                'America/Los_Angeles' => 'America/Los_Angeles (PST)',
                'Europe/London' => 'Europe/London (GMT)',
                'Europe/Paris' => 'Europe/Paris (CET)',
            ],
            default: 'America/New_York'
        );

        // =====================================================================
        // OPTIONAL FEATURES
        // =====================================================================

        // Sample data - Demo products, categories, and content
        $config['install_sample_data'] = $this->askConfirm(
            label: 'Install sample data (demo products and content)?',
            default: false
        );

        // Elasticsearch - Search engine (required for production)
        $config['use_elasticsearch'] = $this->askConfirm(
            label: 'Use Elasticsearch for search?',
            default: true
        );

        // Elasticsearch host (if enabled)
        if ($config['use_elasticsearch']) {
            $config['elasticsearch_host'] = $this->askText(
                label: 'Elasticsearch host',
                placeholder: 'localhost',
                default: 'localhost',
                required: true
            );

            $config['elasticsearch_port'] = $this->askText(
                label: 'Elasticsearch port',
                placeholder: '9200',
                default: '9200',
                required: true
            );
        }

        // Redis - Caching backend
        $config['use_redis'] = $this->askConfirm(
            label: 'Use Redis for caching?',
            default: true
        );

        // Redis host (if enabled)
        if ($config['use_redis']) {
            $config['redis_host'] = $this->askText(
                label: 'Redis host',
                placeholder: '127.0.0.1',
                default: '127.0.0.1',
                required: true
            );

            $config['redis_port'] = $this->askText(
                label: 'Redis port',
                placeholder: '6379',
                default: '6379',
                required: true
            );
        }

        return $config;
    }

    /**
     * Get the Composer command to install Magento.
     *
     * Generates the Composer create-project command to install Magento
     * with the specified version. The command creates a new Magento project
     * in the current directory.
     *
     * Command format includes inline authentication using COMPOSER_AUTH environment variable.
     *
     * Note: Requires Magento authentication keys from https://marketplace.magento.com/
     * Authentication is passed via COMPOSER_AUTH environment variable.
     *
     * @param  array<string, mixed> $config Configuration from collectConfiguration()
     * @return string               The Composer command to execute
     */
    public function getInstallCommand(array $config): string
    {
        // Extract Magento version from config, default to version 2.4.7
        $version = $config['magento_version'] ?? '2.4.7';

        // Extract authentication keys
        $publicKey = $config['magento_public_key'] ?? '';
        $privateKey = $config['magento_private_key'] ?? '';

        // Create auth JSON for COMPOSER_AUTH environment variable
        $authJson = json_encode([
            'http-basic' => [
                'repo.magento.com' => [
                    'username' => $publicKey,
                    'password' => $privateKey,
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);

        // Return command with COMPOSER_AUTH environment variable
        return "COMPOSER_AUTH='{$authJson}' composer create-project --repository-url=https://repo.magento.com/ magento/project-community-edition:{$version} .";
    }

    /**
     * Get pre-installation commands to execute.
     *
     * Returns an array of shell commands to execute before the base Magento
     * installation. These commands configure Composer authentication for
     * accessing the Magento repository.
     *
     * @param  array<string, mixed> $config Configuration from collectConfiguration()
     * @return array<string>        Array of shell commands to execute sequentially
     */

    /**
     * Get post-installation commands to execute.
     *
     * Returns an array of shell commands to execute after the base Magento
     * installation is complete. These commands configure the application,
     * install sample data, and run initial setup tasks.
     *
     * Command execution order:
     * 1. Set file permissions
     * 2. Install sample data (if requested)
     * 3. Run setup:install with all configuration
     * 4. Configure Elasticsearch (if enabled)
     * 5. Configure Redis (if enabled)
     * 6. Deploy static content
     * 7. Compile dependency injection
     * 8. Reindex data
     * 9. Flush cache
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
        // FILE PERMISSIONS
        // =====================================================================

        // Set proper file permissions for Magento directories
        $commands[] = 'find var generated vendor pub/static pub/media app/etc -type f -exec chmod g+w {} +';
        $commands[] = 'find var generated vendor pub/static pub/media app/etc -type d -exec chmod g+ws {} +';
        $commands[] = 'chmod u+x bin/magento';

        // =====================================================================
        // SAMPLE DATA
        // =====================================================================

        // Install sample data if requested
        if (($config['install_sample_data'] ?? false) === true) {
            $commands[] = 'php bin/magento sampledata:deploy';
        }

        // =====================================================================
        // SETUP:INSTALL
        // =====================================================================

        // Build the setup:install command with all configuration
        $setupCommand = 'php bin/magento setup:install';
        $setupCommand .= ' --base-url=' . ($config['base_url'] ?? 'http://localhost/');
        $setupCommand .= ' --db-host=' . ($config['db_host'] ?? '127.0.0.1');
        $setupCommand .= ' --db-name=' . ($config['db_name'] ?? 'magento');
        $setupCommand .= ' --db-user=' . ($config['db_user'] ?? 'root');

        // Add database password if provided
        $dbPassword = $config['db_password'] ?? '';
        if ($dbPassword !== '') {
            $setupCommand .= ' --db-password=' . $dbPassword;
        }

        // Add admin user configuration
        $setupCommand .= ' --admin-firstname=' . ($config['admin_firstname'] ?? 'Admin');
        $setupCommand .= ' --admin-lastname=' . ($config['admin_lastname'] ?? 'User');
        $setupCommand .= ' --admin-email=' . ($config['admin_email'] ?? 'admin@example.com');
        $setupCommand .= ' --admin-user=' . ($config['admin_user'] ?? 'admin');
        $setupCommand .= ' --admin-password=' . ($config['admin_password'] ?? 'Admin123!');

        // Add store configuration
        $setupCommand .= ' --language=' . ($config['language'] ?? 'en_US');
        $setupCommand .= ' --currency=' . ($config['currency'] ?? 'USD');
        $setupCommand .= ' --timezone=' . ($config['timezone'] ?? 'America/New_York');

        // Add backend frontname
        $setupCommand .= ' --backend-frontname=admin';

        // Add Elasticsearch configuration if enabled
        if (($config['use_elasticsearch'] ?? true) === true) {
            $setupCommand .= ' --search-engine=elasticsearch7';
            $setupCommand .= ' --elasticsearch-host=' . ($config['elasticsearch_host'] ?? 'localhost');
            $setupCommand .= ' --elasticsearch-port=' . ($config['elasticsearch_port'] ?? '9200');
        }

        // Use database for sessions
        $setupCommand .= ' --session-save=db';

        $commands[] = $setupCommand;

        // =====================================================================
        // REDIS CONFIGURATION
        // =====================================================================

        // Configure Redis for caching if enabled
        if (($config['use_redis'] ?? true) === true) {
            $redisHost = $config['redis_host'] ?? '127.0.0.1';
            $redisPort = $config['redis_port'] ?? '6379';

            // Configure Redis for default cache
            $commands[] = "php bin/magento setup:config:set --cache-backend=redis --cache-backend-redis-server={$redisHost} --cache-backend-redis-port={$redisPort} --cache-backend-redis-db=0";

            // Configure Redis for page cache
            $commands[] = "php bin/magento setup:config:set --page-cache=redis --page-cache-redis-server={$redisHost} --page-cache-redis-port={$redisPort} --page-cache-redis-db=1";

            // Configure Redis for session storage
            $commands[] = "php bin/magento setup:config:set --session-save=redis --session-save-redis-host={$redisHost} --session-save-redis-port={$redisPort} --session-save-redis-db=2";
        }

        // =====================================================================
        // DEPLOYMENT
        // =====================================================================

        // Deploy static content for the selected language
        $language = $config['language'] ?? 'en_US';
        $commands[] = "php bin/magento setup:static-content:deploy -f {$language}";

        // Compile dependency injection
        $commands[] = 'php bin/magento setup:di:compile';

        // Reindex all data
        $commands[] = 'php bin/magento indexer:reindex';

        // Flush cache
        $commands[] = 'php bin/magento cache:flush';

        // Enable production mode (optional, can be changed to developer mode)
        // $commands[] = 'php bin/magento deploy:mode:set production';

        return $commands;
    }

    /**
     * Get the path to Magento-specific stub templates.
     *
     * Returns the absolute path to the directory containing stub templates
     * specifically for Magento applications. These stubs include:
     * - composer.json with Magento-specific dependencies
     * - package.json for monorepo integration
     * - .gitignore with Magento-specific patterns
     * - README.md with Magento usage instructions
     * - Monorepo-specific configuration files
     *
     * The stub files contain placeholders (e.g., {{APP_NAME}}) that are
     * replaced with actual values using getStubVariables().
     *
     * @return string Absolute path to cli/stubs/magento-app directory
     */
    public function getStubPath(): string
    {
        // Get base stubs directory and append magento-app subdirectory
        return $this->getBaseStubPath() . '/magento-app';
    }

    /**
     * Get variables for stub template replacement.
     *
     * Returns an associative array of placeholder => value pairs used to
     * replace placeholders in stub template files. This method combines
     * common variables (from parent class) with Magento-specific variables.
     *
     * Common variables (from AbstractAppType):
     * - {{APP_NAME}}: Original application name
     * - {{APP_NAME_NORMALIZED}}: Normalized directory/package name
     * - {{APP_NAMESPACE}}: PascalCase namespace component
     * - {{PACKAGE_NAME}}: Full Composer package name
     * - {{DESCRIPTION}}: Application description
     *
     * Magento-specific variables:
     * - {{MAGENTO_VERSION}}: Selected Magento version (2.4.6, 2.4.7)
     * - {{BASE_URL}}: Store base URL
     * - {{ADMIN_USER}}: Admin username
     *
     * Example stub usage:
     * ```json
     * {
     *   "name": "{{PACKAGE_NAME}}",
     *   "description": "{{DESCRIPTION}}",
     *   "require": {
     *     "magento/product-community-edition": "{{MAGENTO_VERSION}}"
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

        // Merge with Magento-specific variables
        return [
            ...$common,
            // Magento version for composer.json constraints
            '{{MAGENTO_VERSION}}' => $config['magento_version'] ?? '2.4.7',

            // Store base URL for configuration
            '{{BASE_URL}}' => $config['base_url'] ?? 'http://localhost/',

            // Admin username for documentation
            '{{ADMIN_USER}}' => $config['admin_user'] ?? 'admin',
        ];
    }
}
