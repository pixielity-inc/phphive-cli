<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes\Laravel\Concerns;

use PhpHive\Cli\Enums\CacheDriver;
use PhpHive\Cli\Enums\DatabaseType;
use PhpHive\Cli\Enums\FilesystemDriver;
use PhpHive\Cli\Enums\MailDriver;
use PhpHive\Cli\Enums\QueueDriver;
use PhpHive\Cli\Enums\SearchEngine;
use PhpHive\Cli\Support\Config;
use PhpHive\Cli\Support\ConfigOperation;

/**
 * Provides Writable Configuration for Laravel Applications.
 *
 * This trait defines all environment and configuration file changes that need
 * to be applied after Laravel installation. It centralizes configuration
 * management for better maintainability and testability.
 *
 * Configuration includes:
 * - Environment variables in .env file
 * - Database connection settings
 * - Redis cache and queue configuration
 * - Mail server settings
 * - Optional services (Meilisearch, Minio)
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
     * and configuration files need to be created or modified after Laravel
     * is installed.
     *
     * @return array<ConfigOperation> Array of configuration operations
     */
    public function getWritableConfig(): array
    {
        // Get configuration from the AppType instance
        $config = $this->config ?? [];

        return [
            $this->getBaseEnvConfig($config),
        ];
    }

    /**
     * Get base environment variable configuration.
     *
     * Defines core environment variables needed for Laravel to connect
     * to services like database, Redis, mail server, etc.
     *
     * @param  array<string, mixed> $config Application configuration
     * @return ConfigOperation      The .env file configuration operation
     */
    private function getBaseEnvConfig(array $config): ConfigOperation
    {
        $appName = $config['name'] ?? 'Laravel';

        $envVars = [
            // Application settings
            'APP_NAME' => $appName,
            'APP_ENV' => 'local',
            'APP_DEBUG' => 'true',
            'APP_URL' => $config['app_url'] ?? 'http://localhost',

            // Database configuration
            'DB_CONNECTION' => $config['db_type'] ?? DatabaseType::MYSQL->getLaravelDriver(),
            'DB_HOST' => $config['db_host'] ?? 'db',
            'DB_PORT' => $config['db_port'] ?? '3306',
            'DB_DATABASE' => $config['db_name'] ?? 'laravel',
            'DB_USERNAME' => $config['db_user'] ?? 'root',
            'DB_PASSWORD' => $config['db_password'] ?? '',
        ];

        // Redis configuration (if enabled)
        if (($config['use_redis'] ?? false) === true) {
            $envVars['REDIS_HOST'] = $config['redis_host'] ?? 'redis';
            $envVars['REDIS_PORT'] = $config['redis_port'] ?? '6379';
            if (isset($config['redis_password']) && $config['redis_password'] !== '') {
                $envVars['REDIS_PASSWORD'] = $config['redis_password'];
            }
            $envVars['CACHE_DRIVER'] = CacheDriver::REDIS->value;
            $envVars['QUEUE_CONNECTION'] = QueueDriver::REDIS->value;
            $envVars['SESSION_DRIVER'] = CacheDriver::REDIS->value;
        }

        // Mail configuration (if provided)
        if (isset($config['mail_host']) && $config['mail_host'] !== '') {
            $envVars['MAIL_MAILER'] = MailDriver::SMTP->value;
            $envVars['MAIL_HOST'] = $config['mail_host'];
            $envVars['MAIL_PORT'] = $config['mail_port'] ?? '1025';
            $envVars['MAIL_USERNAME'] = $config['mail_username'] ?? '';
            $envVars['MAIL_PASSWORD'] = $config['mail_password'] ?? '';
            $envVars['MAIL_ENCRYPTION'] = $config['mail_encryption'] ?? 'null';
            $envVars['MAIL_FROM_ADDRESS'] = $config['mail_from'] ?? 'hello@example.com';
            $envVars['MAIL_FROM_NAME'] = $appName;
        }

        // Meilisearch configuration (if enabled)
        if (($config['use_meilisearch'] ?? false) === true) {
            $envVars['MEILISEARCH_HOST'] = $config['meilisearch_host'] ?? 'http://meilisearch:7700';
            if (isset($config['meilisearch_key']) && $config['meilisearch_key'] !== '') {
                $envVars['MEILISEARCH_KEY'] = $config['meilisearch_key'];
            }
            $envVars['SCOUT_DRIVER'] = SearchEngine::MEILISEARCH->value;
        }

        // Minio/S3 configuration (if enabled)
        if (($config['use_minio'] ?? false) === true) {
            $envVars['FILESYSTEM_DISK'] = FilesystemDriver::S3->value;
            $envVars['AWS_ACCESS_KEY_ID'] = $config['minio_access_key'] ?? 'minioadmin';
            $envVars['AWS_SECRET_ACCESS_KEY'] = $config['minio_secret_key'] ?? 'minioadmin';
            $envVars['AWS_DEFAULT_REGION'] = 'us-east-1';
            $envVars['AWS_BUCKET'] = $config['minio_bucket'] ?? 'laravel';
            $envVars['AWS_ENDPOINT'] = $config['minio_endpoint'] ?? 'http://minio:9000';
            $envVars['AWS_USE_PATH_STYLE_ENDPOINT'] = 'true';
        }

        return Config::set('.env', $envVars);
    }
}
