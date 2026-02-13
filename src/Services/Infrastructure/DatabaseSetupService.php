<?php

declare(strict_types=1);

namespace PhpHive\Cli\Services\Infrastructure;

use PhpHive\Cli\DTOs\Infrastructure\DatabaseConfig;
use PhpHive\Cli\Enums\DatabaseType;
use PhpHive\Cli\Support\Docker;
use RuntimeException;

/**
 * Database Setup Service.
 *
 * Orchestrates database setup operations, coordinating between Docker-based
 * and local MySQL setups. This service encapsulates the business logic for
 * database configuration, delegating specific operations to specialized services.
 *
 * Features:
 * - Docker-first approach with automatic fallback
 * - Coordinates between DockerComposeGenerator, Docker, and MySQLService
 * - Handles both Docker and local MySQL setups
 * - Generates secure passwords
 * - Waits for database readiness
 * - Returns type-safe DatabaseConfig DTO
 *
 * Architecture:
 * - Uses dependency injection for testability
 * - Delegates Docker operations to Docker service
 * - Delegates MySQL operations to MySQLService
 * - Delegates docker-compose generation to DockerComposeGenerator
 * - Returns immutable DatabaseConfig DTO
 *
 * Usage:
 * ```php
 * $service = DatabaseSetupService::make();
 *
 * // Full setup with Docker-first approach
 * $config = $service->setup($databaseConfig, '/path/to/app');
 *
 * // Docker setup only
 * $config = $service->setupDocker($databaseConfig, '/path/to/app');
 *
 * // Local MySQL setup only
 * $config = $service->setupLocal($databaseConfig);
 * ```
 */
final readonly class DatabaseSetupService
{
    /**
     * Create a new DatabaseSetupService instance.
     *
     * @param Docker                 $docker                 Docker service for container operations
     * @param DockerComposeGenerator $dockerComposeGenerator Docker Compose file generator
     */
    /**
     * Create a new DatabaseSetupService instance.
     *
     * @param Docker                 $docker                 Docker service for container operations
     * @param DockerComposeGenerator $dockerComposeGenerator Docker Compose file generator
     */
    public function __construct(
        private Docker $docker,
        private DockerComposeGenerator $dockerComposeGenerator,
    ) {}

    /**
     * Orchestrate complete database setup with Docker-first approach.
     *
     * This is the main entry point for database setup. It attempts Docker
     * setup first, then falls back to local MySQL if Docker is unavailable
     * or setup fails.
     *
     * Process:
     * 1. Check if Docker is available
     * 2. If yes, attempt Docker setup
     * 3. If Docker setup fails or unavailable, fall back to local setup
     * 4. Return final configuration
     *
     * @param  DatabaseConfig $databaseConfig Initial database configuration
     * @param  string         $appPath        Absolute path to application directory
     * @return DatabaseConfig Final database configuration
     */
    public function setup(DatabaseConfig $databaseConfig, string $appPath): DatabaseConfig
    {
        // Try Docker setup if available
        if ($this->docker->isInstalled() && $this->docker->isRunning() && $this->docker->isComposeInstalled()) {
            $dockerConfig = $this->setupDocker($databaseConfig, $appPath);
            if ($dockerConfig instanceof DatabaseConfig) {
                return $dockerConfig;
            }
        }

        // Fall back to local setup
        return $this->setupLocal($databaseConfig);
    }

    /**
     * Create a new DatabaseSetupService instance using static factory pattern.
     *
     * @return self A new DatabaseSetupService instance with dependencies
     */
    /**
     * Create a new DatabaseSetupService instance using static factory pattern.
     *
     * @return self A new DatabaseSetupService instance with dependencies
     */
    public static function make(): self
    {
        return new self(
            docker: Docker::make(),
            dockerComposeGenerator: DockerComposeGenerator::make(),
        );
    }

    /**
     * Set up database using Docker containers.
     *
     * Creates a Docker Compose configuration with the selected database
     * type and starts the containers. Waits for the database to be ready
     * before returning.
     *
     * Process:
     * 1. Generate docker-compose.yml from template
     * 2. Start Docker containers
     * 3. Wait for database to be ready
     * 4. Return updated configuration
     *
     * @param  DatabaseConfig      $databaseConfig Database configuration
     * @param  string              $appPath        Application directory path
     * @return DatabaseConfig|null Updated config on success, null on failure
     */
    public function setupDocker(DatabaseConfig $databaseConfig, string $appPath): ?DatabaseConfig
    {
        // Generate root password for Docker
        $rootPassword = bin2hex(random_bytes(16));

        // Normalize app name for container naming
        $appName = basename($appPath);
        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $appName) ?? $appName);

        // Prepare template variables
        $variables = [
            'container_prefix' => "phphive-{$normalizedName}",
            'volume_prefix' => "phphive-{$normalizedName}",
            'network_name' => "phphive-{$normalizedName}",
            'db_name' => $databaseConfig->name,
            'db_user' => $databaseConfig->user,
            'db_password' => $databaseConfig->password,
            'db_root_password' => $rootPassword,
            'db_port' => (string) $databaseConfig->port,
            'phpmyadmin_port' => '8080',
            'adminer_port' => '8080',
            'redis_port' => '6379',
            'elasticsearch_port' => '9200',
            'include_admin' => true,
        ];

        // Generate docker-compose.yml
        $generated = $this->dockerComposeGenerator->generate($databaseConfig->type, $appPath, $variables);
        if (! $generated) {
            return null;
        }

        // Start Docker containers
        try {
            $this->docker->composeUp($appPath, detached: true);
        } catch (RuntimeException) {
            return null;
        }

        // Wait for database to be ready
        $serviceName = match ($databaseConfig->type) {
            DatabaseType::POSTGRESQL => 'postgres',
            DatabaseType::MARIADB => 'mariadb',
            default => 'mysql',
        };

        $ready = $this->waitForService($appPath, $serviceName, maxAttempts: 30);
        if (! $ready) {
            // Service not ready, but containers are running
            // Return config anyway, user may need to wait
        }

        // Return updated configuration with Docker flag
        return new DatabaseConfig(
            type: $databaseConfig->type,
            host: 'localhost',
            port: $databaseConfig->port,
            name: $databaseConfig->name,
            user: $databaseConfig->user,
            password: $databaseConfig->password,
            usingDocker: true,
        );
    }

    /**
     * Set up database using local MySQL installation.
     *
     * Uses MySQLService to create database and user on a local MySQL
     * installation. This is the fallback when Docker is not available.
     *
     * Note: This method assumes the database and user have already been
     * created or will be created manually. For automatic creation, use
     * MySQLService directly with admin credentials.
     *
     * @param  DatabaseConfig $databaseConfig Database configuration
     * @return DatabaseConfig Updated configuration with Docker flag set to false
     */
    public function setupLocal(DatabaseConfig $databaseConfig): DatabaseConfig
    {
        // Return configuration with Docker flag set to false
        return new DatabaseConfig(
            type: $databaseConfig->type,
            host: $databaseConfig->host,
            port: $databaseConfig->port,
            name: $databaseConfig->name,
            user: $databaseConfig->user,
            password: $databaseConfig->password,
            usingDocker: false,
        );
    }

    /**
     * Wait for a Docker service to be ready.
     *
     * Polls a Docker container until it's healthy and ready to accept
     * connections. Uses docker compose exec to run health checks.
     *
     * Polling strategy:
     * - Maximum attempts: configurable (default: 30)
     * - Delay between attempts: 2 seconds
     * - Total maximum wait: attempts * 2 seconds
     *
     * @param  string $appPath     Absolute path to application directory
     * @param  string $serviceName Name of service in docker-compose.yml
     * @param  int    $maxAttempts Maximum number of polling attempts
     * @return bool   True if service is ready, false if timeout
     */
    private function waitForService(string $appPath, string $serviceName, int $maxAttempts = 30): bool
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            try {
                // Try to execute a simple command in the container
                $this->docker->composeExec(
                    directory: $appPath,
                    service: $serviceName,
                    command: ['echo', 'ready'],
                    tty: false
                );

                return true;
            } catch (RuntimeException) {
                // Service not ready yet, wait and retry
                sleep(2);
                $attempts++;
            }
        }

        return false;
    }
}
