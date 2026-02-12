<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes;

use PhpHive\Cli\Concerns\InteractsWithDatabase;
use PhpHive\Cli\Concerns\InteractsWithDocker;
use PhpHive\Cli\Concerns\InteractsWithElasticsearch;
use PhpHive\Cli\Concerns\InteractsWithMagentoMarketplace;
use PhpHive\Cli\Concerns\InteractsWithMeilisearch;
use PhpHive\Cli\Concerns\InteractsWithMinio;
use PhpHive\Cli\Concerns\InteractsWithRedis;
use PhpHive\Cli\Support\Process;
use RuntimeException;
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
 * - Optional services (Redis, Elasticsearch, Meilisearch, Minio)
 * - Multi-store configuration
 * - Automatic setup and compilation
 * - Non-interactive mode with comprehensive flags
 * - Docker Compose generation for all services
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
 * File Operations:
 * All file operations use the Filesystem class via $this->filesystem() inherited
 * from AbstractAppType, providing consistent error handling and testability.
 *
 * System requirements:
 * - PHP 8.1+ (8.2+ recommended)
 * - MySQL 8.0+ or MariaDB 10.4+
 * - Elasticsearch 7.x or 8.x (for search)
 * - Redis (optional, for caching)
 * - Varnish (optional, for full-page cache)
 *
 * Non-interactive mode flags:
 * --magento-version       Magento version (default: 2.4.7)
 * --app-name              Application name
 * --public-key            Magento public key (from marketplace)
 * --private-key           Magento private key (from marketplace)
 * --db-name               Database name
 * --db-user               Database username
 * --db-password           Database password
 * --admin-user            Admin username
 * --admin-email           Admin email address
 * --admin-password        Admin password
 * --base-url              Store base URL
 * --currency              Default currency (USD, EUR, GBP)
 * --timezone              Default timezone
 * --use-redis             Enable Redis caching (boolean)
 * --use-elasticsearch     Enable Elasticsearch search (boolean)
 * --use-meilisearch       Enable Meilisearch search (boolean)
 * --use-minio             Enable Minio object storage (boolean)
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
 *     'use_meilisearch' => false,
 *     'use_minio' => false,
 * ]
 * ```
 *
 * @see https://magento.com Magento Platform
 * @see AbstractAppType
 * @see Filesystem
 */
class MagentoAppType extends AbstractAppType
{
    use InteractsWithDatabase;
    use InteractsWithDocker;
    use InteractsWithElasticsearch;
    use InteractsWithMagentoMarketplace;
    use InteractsWithMeilisearch;
    use InteractsWithMinio;
    use InteractsWithRedis;

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
     * Collect configuration through interactive prompts or flags.
     *
     * This method guides the user through a series of interactive questions
     * to gather all necessary configuration for creating a Magento application.
     * In non-interactive mode, it reads configuration from command-line flags.
     *
     * Configuration collected:
     * - Application name and description
     * - Magento version (2.4.6, 2.4.7 Latest)
     * - Magento authentication keys (public/private)
     * - Database configuration (MySQL only)
     * - Admin user credentials
     * - Sample data installation
     * - Store configuration (URL, language, currency, timezone)
     * - Optional services:
     *   * Redis (caching and session storage)
     *   * Elasticsearch (search engine)
     *   * Meilisearch (alternative search engine)
     *   * Minio (S3-compatible object storage)
     *
     * Non-interactive mode flags:
     * All configuration can be provided via command-line flags for automated
     * deployments and CI/CD pipelines. See class docblock for complete flag list.
     *
     * Service integration:
     * When services are enabled, this method uses the following traits:
     * - InteractsWithRedis: Redis setup and Docker Compose generation
     * - InteractsWithElasticsearch: Elasticsearch setup with optional Kibana
     * - InteractsWithMeilisearch: Meilisearch setup and configuration
     * - InteractsWithMinio: Minio object storage with Console UI
     *
     * Docker Compose generation:
     * If Docker is available and services are enabled, a comprehensive
     * docker-compose.yml file is generated with all selected services,
     * including proper networking, volumes, and health checks.
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

        // Application name - from argument
        $config['name'] = $input->getArgument('name');

        // Application description - from option or prompt
        $config['description'] = $input->getOption('description') ?? $this->text(
            label: 'Application description',
            placeholder: 'A Magento e-commerce store',
            required: false
        );

        // =====================================================================
        // MAGENTO AUTHENTICATION
        // =====================================================================

        // Get Magento authentication keys using the concern
        $keys = $this->getMagentoAuthKeys(required: true);
        $config['magento_public_key'] = $keys['public_key'];
        $config['magento_private_key'] = $keys['private_key'];

        // =====================================================================
        // MAGENTO EDITION & VERSION
        // =====================================================================

        // Magento edition selection (Community or Enterprise)
        $config['magento_edition'] = $input->getOption('magento-edition') ?? $this->select(
            label: 'Magento edition',
            options: [
                'community' => 'Community Edition (Open Source)',
                'enterprise' => 'Enterprise Edition (Commerce)',
            ],
            default: 'community'
        );

        // Magento version selection
        $config['magento_version'] = $input->getOption('magento-version') ?? $this->select(
            label: 'Magento version',
            options: [
                '2.4.7' => 'Magento 2.4.7 (Latest)',
                '2.4.6' => 'Magento 2.4.6',
                '2.4.5' => 'Magento 2.4.5',
            ],
            default: '2.4.7'
        );

        // =====================================================================
        // DATABASE CONFIGURATION
        // =====================================================================

        // Check if database options are provided via flags
        $dbHost = $input->getOption('db-host');
        $dbPort = $input->getOption('db-port');
        $dbName = $input->getOption('db-name');
        $dbUser = $input->getOption('db-user');
        $dbPassword = $input->getOption('db-password');

        // If all database options provided, use them directly
        if ($dbHost !== null && $dbName !== null && $dbUser !== null) {
            $config['db_host'] = $dbHost;
            $config['db_port'] = $dbPort !== null ? (int) $dbPort : 3306;
            $config['db_name'] = $dbName;
            $config['db_user'] = $dbUser;
            $config['db_password'] = $dbPassword ?? '';
        } else {
            // Check Docker preference from flags
            $useDocker = $input->getOption('use-docker');
            $noDocker = $input->getOption('no-docker');

            // Use Docker-first database setup (supports Docker and local MySQL)
            $appPath = getcwd() . '/apps/' . $config['name'];

            // If --no-docker flag is set, skip Docker and go straight to local
            if ($noDocker === true) {
                $dbConfig = $this->setupLocalDatabase($config['name']);
            } elseif ($useDocker === true) {
                // Force Docker setup
                $dbConfig = $this->setupDockerDatabase($config['name'], ['mysql', 'mariadb'], $appPath);
                if ($dbConfig === null) {
                    // Docker setup failed, fall back to local
                    $dbConfig = $this->setupLocalDatabase($config['name']);
                }
            } else {
                // Normal flow - Docker-first with prompts
                $dbConfig = $this->setupDatabase($config['name'], ['mysql', 'mariadb'], $appPath);
            }

            // Merge database configuration into main config
            $config['db_host'] = $dbConfig['db_host'];
            $config['db_port'] = $dbConfig['db_port'];
            $config['db_name'] = $dbConfig['db_name'];
            $config['db_user'] = $dbConfig['db_user'];
            $config['db_password'] = $dbConfig['db_password'];
        }

        // =====================================================================
        // ADMIN USER CONFIGURATION
        // =====================================================================

        // Admin first name
        $config['admin_firstname'] = $input->getOption('admin-firstname') ?? $this->text(
            label: 'Admin first name',
            placeholder: 'Admin',
            default: 'Admin',
            required: true
        );

        // Admin last name
        $config['admin_lastname'] = $input->getOption('admin-lastname') ?? $this->text(
            label: 'Admin last name',
            placeholder: 'User',
            default: 'User',
            required: true
        );

        // Admin email
        $config['admin_email'] = $input->getOption('admin-email') ?? $this->text(
            label: 'Admin email',
            placeholder: 'admin@example.com',
            default: 'admin@example.com',
            required: true
        );

        // Admin username
        $config['admin_user'] = $input->getOption('admin-user') ?? $this->text(
            label: 'Admin username',
            placeholder: 'admin',
            default: 'admin',
            required: true
        );

        // Admin password
        $config['admin_password'] = $input->getOption('admin-password') ?? $this->text(
            label: 'Admin password (min 7 chars, must include letters and numbers)',
            placeholder: 'Admin123!',
            default: 'Admin123!',
            required: true
        );

        // =====================================================================
        // STORE CONFIGURATION
        // =====================================================================

        // Base URL
        $config['base_url'] = $input->getOption('base-url') ?? $this->text(
            label: 'Base URL',
            placeholder: 'http://localhost/',
            default: 'http://localhost/',
            required: true
        );

        // Language
        $config['language'] = $input->getOption('language') ?? $this->select(
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
        $config['currency'] = $input->getOption('currency') ?? $this->select(
            label: 'Default currency',
            options: [
                'USD' => 'US Dollar (USD)',
                'EUR' => 'Euro (EUR)',
                'GBP' => 'British Pound (GBP)',
            ],
            default: 'USD'
        );

        // Timezone
        $config['timezone'] = $input->getOption('timezone') ?? $this->select(
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
        $sampleDataOption = $input->getOption('sample-data');
        $config['install_sample_data'] = $sampleDataOption !== null ? (bool) $sampleDataOption : $this->confirm(
            label: 'Install sample data (demo products and content)?',
            default: false
        );

        // =====================================================================
        // REDIS CONFIGURATION
        // =====================================================================

        // Check if Redis should be enabled (flag or prompt)
        $redisOption = $input->getOption('use-redis');
        $config['use_redis'] = $redisOption !== null ? (bool) $redisOption : $this->confirm(
            label: 'Use Redis for caching and sessions?',
            default: true
        );

        // Setup Redis if enabled
        if ($config['use_redis']) {
            $appPath = getcwd() . '/apps/' . $config['name'];

            // Use InteractsWithRedis trait for comprehensive setup
            $redisConfig = $this->setupRedis($config['name'], $appPath);

            // Merge Redis configuration
            $config['redis_host'] = $redisConfig['redis_host'];
            $config['redis_port'] = $redisConfig['redis_port'];
            $config['redis_password'] = $redisConfig['redis_password'] ?? '';
            $config['redis_using_docker'] = $redisConfig['using_docker'] ?? false;
        }

        // =====================================================================
        // SEARCH ENGINE CONFIGURATION
        // =====================================================================

        // Determine which search engine to use (Elasticsearch or Meilisearch)
        $elasticsearchOption = $input->getOption('use-elasticsearch');
        $meilisearchOption = $input->getOption('use-meilisearch');

        // If both flags provided, Elasticsearch takes precedence
        if ($elasticsearchOption !== null && $meilisearchOption !== null) {
            if ((bool) $elasticsearchOption) {
                $config['use_elasticsearch'] = true;
                $config['use_meilisearch'] = false;
            } else {
                $config['use_elasticsearch'] = false;
                $config['use_meilisearch'] = (bool) $meilisearchOption;
            }
        } elseif ($elasticsearchOption !== null) {
            $config['use_elasticsearch'] = (bool) $elasticsearchOption;
            $config['use_meilisearch'] = false;
        } elseif ($meilisearchOption !== null) {
            $config['use_meilisearch'] = (bool) $meilisearchOption;
            $config['use_elasticsearch'] = false;
        } else {
            // Interactive mode - ask which search engine to use
            $searchEngine = $this->select(
                label: 'Select search engine',
                options: [
                    'elasticsearch' => 'Elasticsearch (Recommended for Magento)',
                    'meilisearch' => 'Meilisearch (Fast, lightweight alternative)',
                    'none' => 'None (Not recommended for production)',
                ],
                default: 'elasticsearch'
            );

            $config['use_elasticsearch'] = $searchEngine === 'elasticsearch';
            $config['use_meilisearch'] = $searchEngine === 'meilisearch';
        }

        // Setup Elasticsearch if enabled
        if ($config['use_elasticsearch']) {
            $appPath = getcwd() . '/apps/' . $config['name'];

            // Use InteractsWithElasticsearch trait for comprehensive setup
            $esConfig = $this->setupElasticsearch($config['name'], $appPath);

            // Merge Elasticsearch configuration
            $config['elasticsearch_host'] = $esConfig['elasticsearch_host'];
            $config['elasticsearch_port'] = $esConfig['elasticsearch_port'];
            $config['elasticsearch_user'] = $esConfig['elasticsearch_user'] ?? 'elastic';
            $config['elasticsearch_password'] = $esConfig['elasticsearch_password'] ?? '';
            $config['elasticsearch_using_docker'] = $esConfig['using_docker'] ?? false;
        }

        // Setup Meilisearch if enabled
        if ($config['use_meilisearch']) {
            $appPath = getcwd() . '/apps/' . $config['name'];

            // Use InteractsWithMeilisearch trait for comprehensive setup
            $meilisearchConfig = $this->setupMeilisearch($config['name'], $appPath);

            // Merge Meilisearch configuration
            $config['meilisearch_host'] = $meilisearchConfig['meilisearch_host'];
            $config['meilisearch_port'] = $meilisearchConfig['meilisearch_port'];
            $config['meilisearch_master_key'] = $meilisearchConfig['meilisearch_master_key'] ?? '';
            $config['meilisearch_using_docker'] = $meilisearchConfig['using_docker'] ?? false;
        }

        // =====================================================================
        // OBJECT STORAGE CONFIGURATION (MINIO)
        // =====================================================================

        // Check if Minio should be enabled (flag or prompt)
        $minioOption = $input->getOption('use-minio');
        $config['use_minio'] = $minioOption !== null ? (bool) $minioOption : $this->confirm(
            label: 'Use Minio for object storage (media files)?',
            default: false
        );

        // Setup Minio if enabled
        if ($config['use_minio']) {
            $appPath = getcwd() . '/apps/' . $config['name'];

            // Use InteractsWithMinio trait for comprehensive setup
            $minioConfig = $this->setupMinio($config['name'], $appPath);

            // Merge Minio configuration
            $config['minio_endpoint'] = $minioConfig['minio_endpoint'];
            $config['minio_port'] = $minioConfig['minio_port'];
            $config['minio_access_key'] = $minioConfig['minio_access_key'];
            $config['minio_secret_key'] = $minioConfig['minio_secret_key'];
            $config['minio_bucket'] = $minioConfig['minio_bucket'];
            $config['minio_using_docker'] = $minioConfig['using_docker'] ?? false;
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

        // Extract Magento edition from config, default to community
        $edition = $config['magento_edition'] ?? 'community';

        // Extract authentication keys
        $publicKey = $config['magento_public_key'] ?? '';
        $privateKey = $config['magento_private_key'] ?? '';

        // Validate that credentials are not empty
        if ($publicKey === '' || $privateKey === '') {
            throw new RuntimeException(
                'Magento authentication keys are required. Get your keys from: https://marketplace.magento.com/customer/accessKeys/'
            );
        }

        // Determine the package name based on edition
        $packageName = $edition === 'enterprise'
            ? 'magento/project-enterprise-edition'
            : 'magento/project-community-edition';

        // Get COMPOSER_AUTH JSON using the concern
        $authJson = $this->getMagentoComposerAuth($publicKey, $privateKey);

        // Add flags to handle compatibility issues:
        // --ignore-platform-reqs: Allow installation on newer PHP versions
        $flags = '--ignore-platform-reqs';

        // Return command with COMPOSER_AUTH environment variable
        return "COMPOSER_AUTH='{$authJson}' composer create-project --repository-url=https://repo.magento.com/ {$packageName}:{$version} . {$flags}";
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
     * 5. Configure Meilisearch (if enabled, via custom module)
     * 6. Configure Redis (if enabled)
     * 7. Configure Minio (if enabled, for media storage)
     * 8. Deploy static content
     * 9. Compile dependency injection
     * 10. Reindex data
     * 11. Flush cache
     *
     * Service integration:
     * - Redis: Configured for cache, page cache, and session storage
     * - Elasticsearch: Configured as search engine with connection details
     * - Meilisearch: Requires custom module for Magento integration
     * - Minio: Configured for media file storage (pub/media)
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
        // MONOREPO PACKAGES SUPPORT
        // =====================================================================

        // Add Composer path repositories for monorepo packages
        // This allows Magento to use local packages from the monorepo
        $commands[] = 'composer config repositories.monorepo-packages path "../../../packages/*"';

        // Update registration_globlist.php to include monorepo packages
        // This allows Magento to auto-discover modules from the packages directory
        $registrationGloblistPath = 'app/etc/registration_globlist.php';
        $commands[] = "php -r \"\\\$file = '{$registrationGloblistPath}'; \\\$content = file_get_contents(\\\$file); \\\$content = str_replace('];', \"    '../../../packages/*/registration.php',\\n];\", \\\$content); file_put_contents(\\\$file, \\\$content);\"";

        // =====================================================================
        // INSTALL LARAVEL PINT FOR CODE FORMATTING
        // =====================================================================

        // Install Laravel Pint as a dev dependency for consistent code formatting
        $commands[] = 'composer require laravel/pint --dev --no-interaction';

        // =====================================================================
        // CLEANUP UNNECESSARY FILES (BEFORE MAGENTO SETUP)
        // =====================================================================

        // Remove all .sample files after installation
        // Magento includes many .sample configuration files that should be removed
        $commands[] = 'find . -name "*.sample" -type f -delete';

        // Remove unnecessary Magento files that are not needed in monorepo
        $commands[] = 'rm -rf app/design';  // Remove default themes (use custom themes in packages)
        $commands[] = 'rm -f COPYING.txt LICENSE_AFL.txt LICENSE.txt';  // Remove license files
        $commands[] = 'rm -f CHANGELOG.md SECURITY.md';  // Remove changelog and security files
        $commands[] = 'rm -f .php-cs-fixer.dist.php';  // Remove php-cs-fixer in favor of Pint

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
        if (($config['use_redis'] ?? false) === true) {
            $redisHost = $config['redis_host'] ?? '127.0.0.1';
            $redisPort = $config['redis_port'] ?? '6379';
            $redisPassword = $config['redis_password'] ?? '';

            // Configure Redis for default cache
            $redisCommand = "php bin/magento setup:config:set --cache-backend=redis --cache-backend-redis-server={$redisHost} --cache-backend-redis-port={$redisPort} --cache-backend-redis-db=0";
            if ($redisPassword !== '') {
                $redisCommand .= " --cache-backend-redis-password={$redisPassword}";
            }
            $commands[] = $redisCommand;

            // Configure Redis for page cache
            $pageCommand = "php bin/magento setup:config:set --page-cache=redis --page-cache-redis-server={$redisHost} --page-cache-redis-port={$redisPort} --page-cache-redis-db=1";
            if ($redisPassword !== '') {
                $pageCommand .= " --page-cache-redis-password={$redisPassword}";
            }
            $commands[] = $pageCommand;

            // Configure Redis for session storage
            $sessionCommand = "php bin/magento setup:config:set --session-save=redis --session-save-redis-host={$redisHost} --session-save-redis-port={$redisPort} --session-save-redis-db=2";
            if ($redisPassword !== '') {
                $sessionCommand .= " --session-save-redis-password={$redisPassword}";
            }
            $commands[] = $sessionCommand;
        }

        // =====================================================================
        // MINIO CONFIGURATION
        // =====================================================================

        // Configure Minio for media storage if enabled
        if (($config['use_minio'] ?? false) === true) {
            // Note: Minio integration requires a custom Magento module
            // This is a placeholder for future implementation
            // You would typically install a module like:
            // - composer require vendor/magento2-minio-adapter
            // - php bin/magento module:enable Vendor_MinioAdapter
            // - php bin/magento setup:upgrade

            // For now, we'll add a comment in the configuration
            $commands[] = 'echo "Note: Minio is configured. Install a Magento Minio adapter module for full integration."';
        }

        // =====================================================================
        // MEILISEARCH CONFIGURATION
        // =====================================================================

        // Configure Meilisearch if enabled (requires custom module)
        if (($config['use_meilisearch'] ?? false) === true) {
            // Note: Meilisearch integration requires a custom Magento module
            // This is a placeholder for future implementation
            // You would typically install a module like:
            // - composer require meilisearch/search-magento2
            // - php bin/magento module:enable Meilisearch_Search
            // - php bin/magento setup:upgrade

            $commands[] = 'echo "Note: Meilisearch is configured. Install the Meilisearch Magento module for full integration."';
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
     * @return string Absolute path to cli/stubs/apps/magento directory
     */
    public function getStubPath(): string
    {
        // Get base stubs directory and append apps/magento subdirectory
        return $this->getBaseStubPath() . '/apps/magento';
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

    /**
     * Get the Process service instance.
     *
     * This method provides access to the Process service for command execution.
     * Required by traits that need to execute shell commands.
     *
     * @return Process The Process service instance
     */
    protected function process(): Process
    {
        return Process::make();
    }
}
