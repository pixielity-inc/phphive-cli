<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes\Symfony\Concerns;

use PhpHive\Cli\Enums\CacheDriver;
use PhpHive\Cli\Support\Config;
use PhpHive\Cli\Support\ConfigOperation;

/**
 * Provides Writable Configuration for Symfony Applications.
 *
 * This trait defines all environment and configuration file changes that need
 * to be applied after Symfony installation. It centralizes configuration
 * management for better maintainability and testability.
 *
 * Configuration includes:
 * - Environment variables in .env and .env.local files
 * - Database URL (Doctrine format)
 * - Redis configuration
 * - Mailer DSN
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
     * and configuration files need to be created or modified after Symfony
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
     * Defines core environment variables needed for Symfony to connect
     * to services like database, Redis, mailer, etc.
     *
     * Symfony uses DSN format for many services (DATABASE_URL, MAILER_DSN, etc.)
     *
     * @param  array<string, mixed> $config Application configuration
     * @return ConfigOperation      The .env file configuration operation
     */
    private function getBaseEnvConfig(array $config): ConfigOperation
    {
        $envVars = [
            // Application settings
            'APP_ENV' => 'dev',
            'APP_DEBUG' => '1',
            'APP_SECRET' => bin2hex(random_bytes(16)), // Generate random secret
        ];

        // Database configuration (Doctrine DSN format)
        $dbHost = $config['db_host'] ?? 'db';
        $dbPort = $config['db_port'] ?? '3306';
        $dbName = $config['db_name'] ?? 'symfony';
        $dbUser = $config['db_user'] ?? 'root';
        $dbPassword = $config['db_password'] ?? '';
        $dbVersion = $config['db_version'] ?? '8.0';

        // Build DATABASE_URL in Doctrine format
        $databaseUrl = "mysql://{$dbUser}:{$dbPassword}@{$dbHost}:{$dbPort}/{$dbName}?serverVersion={$dbVersion}";
        $envVars['DATABASE_URL'] = $databaseUrl;

        // Redis configuration (if enabled)
        if (($config['use_redis'] ?? false) === true) {
            $redisHost = $config['redis_host'] ?? 'redis';
            $redisPort = $config['redis_port'] ?? '6379';
            $redisPassword = $config['redis_password'] ?? '';

            // Build REDIS_URL in DSN format
            if ($redisPassword !== '') {
                $envVars['REDIS_URL'] = "redis://:{$redisPassword}@{$redisHost}:{$redisPort}";
            } else {
                $envVars['REDIS_URL'] = "redis://{$redisHost}:{$redisPort}";
            }

            // Cache configuration
            $envVars['CACHE_DRIVER'] = CacheDriver::REDIS->value;
        }

        // Mailer configuration (if provided)
        if (isset($config['mail_host']) && $config['mail_host'] !== '') {
            $mailHost = $config['mail_host'];
            $mailPort = $config['mail_port'] ?? '1025';
            $mailUser = $config['mail_username'] ?? '';
            $mailPassword = $config['mail_password'] ?? '';

            // Build MAILER_DSN in Symfony format
            if ($mailUser !== '' && $mailPassword !== '') {
                $envVars['MAILER_DSN'] = "smtp://{$mailUser}:{$mailPassword}@{$mailHost}:{$mailPort}";
            } else {
                $envVars['MAILER_DSN'] = "smtp://{$mailHost}:{$mailPort}";
            }
        } else {
            // Default to null transport for development
            $envVars['MAILER_DSN'] = 'null://null';
        }

        // Meilisearch configuration (if enabled)
        if (($config['use_meilisearch'] ?? false) === true) {
            $meilisearchHost = $config['meilisearch_host'] ?? 'meilisearch';
            $meilisearchPort = $config['meilisearch_port'] ?? '7700';
            $meilisearchKey = $config['meilisearch_key'] ?? '';

            $envVars['MEILISEARCH_URL'] = "http://{$meilisearchHost}:{$meilisearchPort}";
            if ($meilisearchKey !== '') {
                $envVars['MEILISEARCH_API_KEY'] = $meilisearchKey;
            }
        }

        // Minio/S3 configuration (if enabled)
        if (($config['use_minio'] ?? false) === true) {
            $envVars['S3_ENDPOINT'] = $config['minio_endpoint'] ?? 'http://minio:9000';
            $envVars['S3_ACCESS_KEY'] = $config['minio_access_key'] ?? 'minioadmin';
            $envVars['S3_SECRET_KEY'] = $config['minio_secret_key'] ?? 'minioadmin';
            $envVars['S3_BUCKET'] = $config['minio_bucket'] ?? 'symfony';
            $envVars['S3_REGION'] = 'us-east-1';
        }

        return Config::set('.env', $envVars);
    }
}
