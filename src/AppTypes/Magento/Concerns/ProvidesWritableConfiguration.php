<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes\Magento\Concerns;

use PhpHive\Cli\Support\Config;
use PhpHive\Cli\Support\ConfigOperation;

/**
 * Provides Writable Configuration for Magento Applications.
 *
 * This trait defines all environment and configuration file changes that need
 * to be applied after Magento installation. It centralizes configuration
 * management for better maintainability and testability.
 *
 * Configuration includes:
 * - Environment variables in .env file
 * - Magento-specific env.php configuration
 * - Database connection settings
 * - Redis cache and session configuration
 * - Elasticsearch/search engine settings
 * - Optional services (Minio, Meilisearch)
 *
 * The configuration is returned as ConfigOperation objects that are processed
 * by a ConfigWriter service, which handles different file formats and action types.
 */
trait ProvidesWritableConfiguration
{
    /**
     * Get configuration operations to be applied after installation.
     *
     * Returns an array of ConfigOperation objects defining what environment
     * and configuration files need to be created or modified after Magento
     * is installed.
     *
     * Operations include:
     * 1. Setting base environment variables (.env)
     * 2. Merging Magento env.php configuration
     * 3. Configuring database, cache, session, and search services
     *
     * @return array<ConfigOperation> Array of configuration operations
     */
    public function getWritableConfig(): array
    {
        // Get configuration from the AppType instance
        // This assumes the config is stored in a property after collectConfiguration()
        $config = $this->config ?? [];

        $operations = [];

        // Base environment variables
        $operations[] = $this->getBaseEnvConfig($config);

        // Magento env.php configuration
        $operations[] = $this->getMagentoEnvPhpConfig($config);

        return $operations;
    }

    /**
     * Get base environment variable configuration.
     *
     * Defines core environment variables needed for Magento to connect
     * to services like database, Redis, Elasticsearch, etc.
     *
     * @param  array<string, mixed> $config Application configuration
     * @return ConfigOperation      The .env file configuration operation
     */
    private function getBaseEnvConfig(array $config): ConfigOperation
    {
        $envVars = [
            // Database configuration
            'DATABASE_HOST' => $config['db_host'] ?? 'db',
            'DATABASE_NAME' => $config['db_name'] ?? 'magento',
            'DATABASE_USER' => $config['db_user'] ?? 'root',
            'DATABASE_PASSWORD' => $config['db_password'] ?? '',

            // Application settings
            'APP_ENV' => 'development',
            'MAGE_MODE' => 'developer',
        ];

        // Redis configuration (if enabled)
        if (($config['use_redis'] ?? false) === true) {
            $envVars['REDIS_HOST'] = $config['redis_host'] ?? 'redis';
            $envVars['REDIS_PORT'] = $config['redis_port'] ?? '6379';
            if (isset($config['redis_password']) && $config['redis_password'] !== '') {
                $envVars['REDIS_PASSWORD'] = $config['redis_password'];
            }
        }

        // Elasticsearch configuration (if enabled)
        if (($config['use_elasticsearch'] ?? false) === true) {
            $envVars['ELASTICSEARCH_HOST'] = $config['elasticsearch_host'] ?? 'elasticsearch';
            $envVars['ELASTICSEARCH_PORT'] = $config['elasticsearch_port'] ?? '9200';
        }

        // Meilisearch configuration (if enabled)
        if (($config['use_meilisearch'] ?? false) === true) {
            $envVars['MEILISEARCH_HOST'] = $config['meilisearch_host'] ?? 'meilisearch';
            $envVars['MEILISEARCH_PORT'] = $config['meilisearch_port'] ?? '7700';
            if (isset($config['meilisearch_key']) && $config['meilisearch_key'] !== '') {
                $envVars['MEILISEARCH_KEY'] = $config['meilisearch_key'];
            }
        }

        // Minio configuration (if enabled)
        if (($config['use_minio'] ?? false) === true) {
            $envVars['MINIO_ENDPOINT'] = $config['minio_endpoint'] ?? 'minio:9000';
            $envVars['MINIO_ACCESS_KEY'] = $config['minio_access_key'] ?? 'minioadmin';
            $envVars['MINIO_SECRET_KEY'] = $config['minio_secret_key'] ?? 'minioadmin';
            $envVars['MINIO_BUCKET'] = $config['minio_bucket'] ?? 'magento';
        }

        return Config::set('.env', $envVars);
    }

    /**
     * Get Magento env.php configuration.
     *
     * Defines the app/etc/env.php configuration array that Magento uses
     * for cache, session, and other service configurations. This uses
     * deep merge to preserve existing configuration while adding our settings.
     *
     * @param  array<string, mixed> $config Application configuration
     * @return ConfigOperation      The env.php merge operation
     */
    private function getMagentoEnvPhpConfig(array $config): ConfigOperation
    {
        $envPhpConfig = [];

        // Redis cache configuration (if enabled)
        if (($config['use_redis'] ?? false) === true) {
            $redisHost = $config['redis_host'] ?? 'redis';
            $redisPort = $config['redis_port'] ?? '6379';
            $redisPassword = $config['redis_password'] ?? '';

            // Default cache backend
            $envPhpConfig['cache'] = [
                'frontend' => [
                    'default' => [
                        'backend' => 'Cm_Cache_Backend_Redis',
                        'backend_options' => [
                            'server' => $redisHost,
                            'port' => $redisPort,
                            'database' => '0',
                            'compress_data' => '1',
                        ],
                    ],
                    'page_cache' => [
                        'backend' => 'Cm_Cache_Backend_Redis',
                        'backend_options' => [
                            'server' => $redisHost,
                            'port' => $redisPort,
                            'database' => '1',
                            'compress_data' => '0',
                        ],
                    ],
                ],
            ];

            // Add password if provided
            if ($redisPassword !== '') {
                $envPhpConfig['cache']['frontend']['default']['backend_options']['password'] = $redisPassword;
                $envPhpConfig['cache']['frontend']['page_cache']['backend_options']['password'] = $redisPassword;
            }

            // Session configuration
            $envPhpConfig['session'] = [
                'save' => 'redis',
                'redis' => [
                    'host' => $redisHost,
                    'port' => $redisPort,
                    'database' => '2',
                    'compression_threshold' => '2048',
                    'compression_library' => 'gzip',
                    'log_level' => '4',
                    'max_concurrency' => '6',
                    'break_after_frontend' => '5',
                    'break_after_adminhtml' => '30',
                    'first_lifetime' => '600',
                    'bot_first_lifetime' => '60',
                    'bot_lifetime' => '7200',
                    'disable_locking' => '0',
                    'min_lifetime' => '60',
                    'max_lifetime' => '2592000',
                ],
            ];

            // Add password if provided
            if ($redisPassword !== '') {
                $envPhpConfig['session']['redis']['password'] = $redisPassword;
            }
        }

        return Config::merge('app/etc/env.php', $envPhpConfig);
    }
}
