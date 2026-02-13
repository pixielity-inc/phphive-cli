<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes\Skeleton\Concerns;

use PhpHive\Cli\Support\Config;
use PhpHive\Cli\Support\ConfigOperation;

/**
 * Provides Writable Configuration for Skeleton Applications.
 *
 * This trait defines all environment and configuration file changes that need
 * to be applied after Skeleton installation. Since Skeleton is a minimal
 * application type, it provides basic configuration that can be extended.
 *
 * Configuration includes:
 * - Basic environment variables in .env file
 * - Database connection settings (if needed)
 * - Optional service configurations
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
     * and configuration files need to be created or modified after Skeleton
     * is installed.
     *
     * For Skeleton apps, we provide minimal configuration that developers
     * can extend based on their needs.
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
     * Defines minimal environment variables for a skeleton application.
     * Developers can extend this based on their specific requirements.
     *
     * @param  array<string, mixed> $config Application configuration
     * @return ConfigOperation      The .env file configuration operation
     */
    private function getBaseEnvConfig(array $config): ConfigOperation
    {
        $envVars = [
            // Application settings
            'APP_NAME' => $config['name'] ?? 'Skeleton',
            'APP_ENV' => 'development',
            'APP_DEBUG' => 'true',
        ];

        // Database configuration (if provided)
        if (isset($config['db_host']) && $config['db_host'] !== '') {
            $envVars['DB_HOST'] = $config['db_host'];
            $envVars['DB_PORT'] = $config['db_port'] ?? '3306';
            $envVars['DB_NAME'] = $config['db_name'] ?? 'skeleton';
            $envVars['DB_USER'] = $config['db_user'] ?? 'root';
            $envVars['DB_PASSWORD'] = $config['db_password'] ?? '';
        }

        // Redis configuration (if enabled)
        if (($config['use_redis'] ?? false) === true) {
            $envVars['REDIS_HOST'] = $config['redis_host'] ?? 'redis';
            $envVars['REDIS_PORT'] = $config['redis_port'] ?? '6379';
            if (isset($config['redis_password']) && $config['redis_password'] !== '') {
                $envVars['REDIS_PASSWORD'] = $config['redis_password'];
            }
        }

        return Config::set('.env', $envVars);
    }
}
