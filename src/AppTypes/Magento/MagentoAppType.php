<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes\Magento;

use Override;
use PhpHive\Cli\AppTypes\AbstractAppType;
use PhpHive\Cli\AppTypes\Magento\Concerns\CollectsAdminConfiguration;
use PhpHive\Cli\AppTypes\Magento\Concerns\CollectsAuthenticationConfiguration;
use PhpHive\Cli\AppTypes\Magento\Concerns\CollectsBasicConfiguration;
use PhpHive\Cli\AppTypes\Magento\Concerns\CollectsOptionalFeatures;
use PhpHive\Cli\AppTypes\Magento\Concerns\CollectsStoreConfiguration;
use PhpHive\Cli\AppTypes\Magento\Concerns\CollectsVersionConfiguration;
use PhpHive\Cli\AppTypes\Magento\Concerns\ProvidesWritableConfiguration;
use PhpHive\Cli\Concerns\InteractsWithMagentoMarketplace;
use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\Enums\MagentoVersion;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Magento Application Type.
 *
 * This class handles the scaffolding and configuration of Magento 2 e-commerce
 * applications within the PhpHive monorepo. Magento is a complex platform requiring
 * extensive configuration including authentication, database, admin users, store
 * settings, and optional services.
 *
 * Configuration is organized into focused concerns for maintainability:
 * - CollectsBasicConfiguration: Application name and description
 * - CollectsAuthenticationConfiguration: Magento Marketplace credentials and edition
 * - CollectsVersionConfiguration: Magento version selection (2.4.5, 2.4.6, 2.4.7)
 * - CollectsDatabaseConfiguration: Database setup (MySQL/MariaDB)
 * - CollectsAdminConfiguration: Admin user credentials
 * - CollectsStoreConfiguration: Store URL, language, currency, timezone
 * - CollectsOptionalFeatures: Sample data installation
 * - CollectsRedisConfiguration: Redis for caching and sessions
 * - CollectsSearchConfiguration: Elasticsearch or Meilisearch
 * - CollectsStorageConfiguration: Minio for object storage
 *
 * Installation workflow:
 * 1. Collect Magento Marketplace authentication keys (required)
 * 2. Select edition (Community/Enterprise) and version
 * 3. Configure database, admin user, and store settings
 * 4. Set up optional services (Redis, Elasticsearch, Minio)
 * 5. Install Magento via composer create-project
 * 6. Run setup:install with collected configuration
 * 7. Configure services and deploy static content
 *
 * Post-installation includes:
 * - Monorepo packages support configuration
 * - Laravel Pint installation for code quality
 * - File permissions setup
 * - Sample data deployment (if selected)
 * - Redis configuration for cache/sessions
 * - Static content deployment and compilation
 *
 * @see AbstractAppType Base class with common functionality
 * @see AppTypeInterface Interface defining the contract
 */
class MagentoAppType extends AbstractAppType
{
    use CollectsAdminConfiguration;
    use CollectsAuthenticationConfiguration;

    // Configuration collection concerns
    use CollectsBasicConfiguration;
    use CollectsOptionalFeatures;
    use CollectsStoreConfiguration;
    use CollectsVersionConfiguration;
    use InteractsWithMagentoMarketplace;
    use ProvidesWritableConfiguration;

    /**
     * Configuration array collected during collectConfiguration().
     *
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * Get the application type name.
     *
     * Returns the display name for this application type, shown in the
     * app type selection menu during `php phive create:app`.
     *
     * @return string The display name "Magento"
     */
    public function getName(): string
    {
        return 'Magento';
    }

    /**
     * Get the application type description.
     *
     * Returns a brief description of Magento, shown in the app type
     * selection menu to help users choose the right platform.
     *
     * @return string Brief description of Magento
     */
    public function getDescription(): string
    {
        return 'Enterprise e-commerce platform';
    }

    /**
     * Collect all configuration from user input.
     *
     * Orchestrates the collection of all Magento-specific configuration by
     * calling methods from the various concerns. Each concern handles a
     * specific aspect of Magento configuration.
     *
     * Configuration collected:
     * - Basic: name, description
     * - Authentication: Magento Marketplace keys, edition (Community/Enterprise)
     * - Version: Magento version (2.4.5, 2.4.6, 2.4.7)
     * - Admin: Admin user credentials
     * - Store: Base URL, language, currency, timezone
     * - Optional features: Sample data
     * - Infrastructure: database, cache, queue, search, storage (via unified setup)
     *
     * @param  InputInterface       $input  Console input interface
     * @param  OutputInterface      $output Console output interface
     * @return array<string, mixed> Complete configuration array
     */
    #[Override]
    public function collectConfiguration(InputInterface $input, OutputInterface $output): array
    {
        // Store input/output for use in trait methods
        $this->input = $input;
        $this->output = $output;

        // Collect basic configuration first to get app name for subsequent configs
        $basicConfig = $this->collectBasicConfig();

        // Collect app-specific configuration
        $config = [];
        $config = array_merge($config, $basicConfig);
        $config = array_merge($config, $this->collectAuthenticationConfig());
        $config = array_merge($config, $this->collectVersionConfig());
        $config = array_merge($config, $this->collectAdminConfig());
        $config = array_merge($config, $this->collectStoreConfig());

        // NOTE: Infrastructure setup is now done in post-install phase
        // to avoid creating files before the app directory exists

        return array_merge($config, $this->collectOptionalFeaturesConfig());
    }

    /**
     * Get the composer command to install Magento.
     *
     * Returns the composer create-project command that installs Magento from
     * the official Magento repository. Requires authentication via Magento
     * Marketplace keys.
     *
     * The command includes:
     * - COMPOSER_AUTH environment variable with Magento credentials
     * - Repository URL pointing to repo.magento.com
     * - Package name based on edition (community or enterprise)
     * - Version constraint
     * - --ignore-platform-reqs flag to bypass PHP extension checks during install
     *
     * Example output:
     * ```
     * COMPOSER_AUTH='{"http-basic":{"repo.magento.com":{"username":"...","password":"..."}}}' \
     * composer create-project --repository-url=https://repo.magento.com/ \
     * magento/project-community-edition:2.4.7 . --ignore-platform-reqs
     * ```
     *
     * @param  array<string, mixed> $config Configuration array from collectConfiguration()
     * @return string               The composer create-project command with authentication
     *
     * @throws RuntimeException If Magento authentication keys are missing
     */
    public function getInstallCommand(array $config): string
    {
        // Extract configuration values with defaults
        $versionValue = $config[AppTypeInterface::CONFIG_MAGENTO_VERSION] ?? MagentoVersion::default()->value;
        $magentoVersion = MagentoVersion::from($versionValue);
        $edition = $config['magento_edition'] ?? 'community';
        $publicKey = $config['magento_public_key'] ?? '';
        $privateKey = $config['magento_private_key'] ?? '';

        // Validate that authentication keys are provided
        if ($publicKey === '' || $privateKey === '') {
            throw new RuntimeException(
                'Magento authentication keys are required. Get your keys from: https://marketplace.magento.com/customer/accessKeys/'
            );
        }

        // Determine package name based on edition
        $packageName = $edition === 'enterprise'
            ? 'magento/project-enterprise-edition'
            : 'magento/project-community-edition';

        // Generate COMPOSER_AUTH JSON for Magento repository authentication
        $authJson = $this->getMagentoComposerAuth($publicKey, $privateKey);

        // Use --ignore-platform-reqs to bypass PHP extension checks during installation
        // Extensions will be validated during setup:install
        $flags = '--ignore-platform-reqs';

        return "COMPOSER_AUTH='{$authJson}' composer create-project --repository-url=https://repo.magento.com/ {$packageName}:{$magentoVersion->value} . {$flags}";
    }

    /**
     * Get commands to run after Magento installation.
     *
     * Returns an array of shell commands to execute after the base Magento
     * installation completes. These commands configure the Magento instance,
     * set up services, and prepare it for use.
     *
     * Commands executed (in order):
     * 1. Configure monorepo packages support
     * 2. Install Laravel Pint for code quality
     * 3. Clean up sample files and unnecessary documentation
     * 4. Set file permissions for Magento directories
     * 5. Deploy sample data (if selected)
     * 6. Run setup:install with all configuration
     * 7. Configure Redis for cache/sessions (if enabled)
     * 8. Note Minio/Meilisearch setup (requires additional modules)
     * 9. Deploy static content and compile DI
     * 10. Reindex and flush cache
     *
     * The setup:install command includes all collected configuration:
     * - Database connection details
     * - Admin user credentials
     * - Store settings (URL, language, currency, timezone)
     * - Search engine configuration (Elasticsearch)
     * - Backend frontname (admin URL path)
     *
     * @param  array<string, mixed> $config Configuration array from collectConfiguration()
     * @return array<string>        Array of shell commands to execute sequentially
     */
    public function getPostInstallCommands(array $config): array
    {
        $commands = [];

        // Monorepo packages support
        $commands[] = 'composer config repositories.monorepo-packages path "../../../packages/*"';
        $registrationGloblistPath = 'app/etc/registration_globlist.php';
        $commands[] = "php -r \"\\\$file = '{$registrationGloblistPath}'; \\\$content = file_get_contents(\\\$file); \\\$content = str_replace('];', \"    '../../../packages/*/registration.php',\\n];\", \\\$content); file_put_contents(\\\$file, \\\$content);\"";

        // Install Laravel Pint
        $commands[] = 'composer require laravel/pint --dev --no-interaction';

        // Cleanup
        $commands[] = 'find . -name "*.sample" -type f -delete';
        $commands[] = 'rm -rf app/design';
        $commands[] = 'rm -f COPYING.txt LICENSE_AFL.txt LICENSE.txt';
        $commands[] = 'rm -f CHANGELOG.md SECURITY.md';
        $commands[] = 'rm -f .php-cs-fixer.dist.php';

        // File permissions
        $commands[] = 'find var generated vendor pub/static pub/media app/etc -type f -exec chmod g+w {} +';
        $commands[] = 'find var generated vendor pub/static pub/media app/etc -type d -exec chmod g+ws {} +';
        $commands[] = 'chmod u+x bin/magento';

        // Sample data
        if (($config['install_sample_data'] ?? false) === true) {
            $commands[] = 'php bin/magento sampledata:deploy';
        }

        // Setup:install
        $setupCommand = 'php bin/magento setup:install';
        $setupCommand .= ' --base-url=' . ($config['base_url'] ?? 'http://localhost/');
        $setupCommand .= ' --db-host=' . ($config['db_host'] ?? '127.0.0.1');
        $setupCommand .= ' --db-name=' . ($config['db_name'] ?? 'magento');
        $setupCommand .= ' --db-user=' . ($config['db_user'] ?? 'root');

        $dbPassword = $config['db_password'] ?? '';
        if ($dbPassword !== '') {
            $setupCommand .= ' --db-password=' . $dbPassword;
        }

        $setupCommand .= ' --admin-firstname=' . ($config['admin_firstname'] ?? 'Admin');
        $setupCommand .= ' --admin-lastname=' . ($config['admin_lastname'] ?? 'User');
        $setupCommand .= ' --admin-email=' . ($config['admin_email'] ?? 'admin@example.com');
        $setupCommand .= ' --admin-user=' . ($config['admin_user'] ?? 'admin');
        $setupCommand .= ' --admin-password=' . ($config['admin_password'] ?? 'Admin123!');
        $setupCommand .= ' --language=' . ($config['language'] ?? 'en_US');
        $setupCommand .= ' --currency=' . ($config['currency'] ?? 'USD');
        $setupCommand .= ' --timezone=' . ($config['timezone'] ?? 'America/New_York');
        $setupCommand .= ' --backend-frontname=admin';

        if (($config['use_elasticsearch'] ?? true) === true) {
            $setupCommand .= ' --search-engine=elasticsearch7';
            $setupCommand .= ' --elasticsearch-host=' . ($config['elasticsearch_host'] ?? 'localhost');
            $setupCommand .= ' --elasticsearch-port=' . ($config['elasticsearch_port'] ?? '9200');
        }

        $setupCommand .= ' --session-save=db';
        $commands[] = $setupCommand;

        // Redis configuration
        if (($config['use_redis'] ?? false) === true) {
            $redisHost = $config['redis_host'] ?? '127.0.0.1';
            $redisPort = $config['redis_port'] ?? '6379';
            $redisPassword = $config['redis_password'] ?? '';

            $redisCommand = "php bin/magento setup:config:set --cache-backend=redis --cache-backend-redis-server={$redisHost} --cache-backend-redis-port={$redisPort} --cache-backend-redis-db=0";
            if ($redisPassword !== '') {
                $redisCommand .= " --cache-backend-redis-password={$redisPassword}";
            }
            $commands[] = $redisCommand;

            $pageCommand = "php bin/magento setup:config:set --page-cache=redis --page-cache-redis-server={$redisHost} --page-cache-redis-port={$redisPort} --page-cache-redis-db=1";
            if ($redisPassword !== '') {
                $pageCommand .= " --page-cache-redis-password={$redisPassword}";
            }
            $commands[] = $pageCommand;

            $sessionCommand = "php bin/magento setup:config:set --session-save=redis --session-save-redis-host={$redisHost} --session-save-redis-port={$redisPort} --session-save-redis-db=2";
            if ($redisPassword !== '') {
                $sessionCommand .= " --session-save-redis-password={$redisPassword}";
            }
            $commands[] = $sessionCommand;
        }

        // Minio configuration
        if (($config['use_minio'] ?? false) === true) {
            $commands[] = 'echo "Note: Minio is configured. Install a Magento Minio adapter module for full integration."';
        }

        // Meilisearch configuration
        if (($config['use_meilisearch'] ?? false) === true) {
            $commands[] = 'echo "Note: Meilisearch is configured. Install the Meilisearch Magento module for full integration."';
        }

        // Deployment
        $language = $config['language'] ?? 'en_US';
        $commands[] = "php bin/magento setup:static-content:deploy -f {$language}";
        $commands[] = 'php bin/magento setup:di:compile';
        $commands[] = 'php bin/magento indexer:reindex';
        $commands[] = 'php bin/magento cache:flush';

        return $commands;
    }

    /**
     * Get the stub template directory path.
     *
     * Returns the path to Magento-specific stub templates, used with
     * Pixielity\StubGenerator\Facades\Stub::setBasePath() for template resolution.
     *
     * @return string Path to Magento stub templates
     */
    public function getStubPath(): string
    {
        return $this->getBaseStubPath() . '/apps/magento';
    }

    /**
     * Get variables for stub template replacement.
     *
     * Returns an associative array used by Pixielity\StubGenerator\Facades\Stub
     * for placeholder replacement. Keys are automatically converted to UPPERCASE
     * and wrapped with $KEY$ or {{KEY}} delimiters.
     *
     * @param  array<string, mixed>  $config Configuration array
     * @return array<string, string> Lowercase keys (auto-converted to UPPERCASE)
     */
    public function getStubVariables(array $config): array
    {
        $common = $this->getCommonStubVariables($config);

        // Get Magento version
        $versionValue = $config[AppTypeInterface::CONFIG_MAGENTO_VERSION] ?? MagentoVersion::default()->value;

        return [
            ...$common,
            AppTypeInterface::STUB_MAGENTO_VERSION => $versionValue,
            AppTypeInterface::STUB_BASE_URL => $config['base_url'] ?? 'http://localhost/',
            AppTypeInterface::STUB_ADMIN_USER => $config['admin_user'] ?? 'admin',
        ];
    }
}
