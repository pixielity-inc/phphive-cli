<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns\Infrastructure;

use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\DTOs\Infrastructure\RedisConfig;
use PhpHive\Cli\Services\Infrastructure\RedisSetupService;

/**
 * Redis Interaction Trait.
 *
 * This trait provides user interaction and prompting for Redis setup.
 * It focuses solely on collecting user input and delegates all business logic to
 * RedisSetupService for better separation of concerns and testability.
 *
 * Redis is an open-source, in-memory data structure store that provides:
 * - High-performance caching (sub-millisecond latency)
 * - Session storage and management
 * - Message queues and pub/sub
 * - Real-time analytics
 * - Rate limiting and leaderboards
 *
 * Architecture:
 * - This trait handles user prompts and input collection
 * - RedisSetupService handles Docker setup and container management
 * - RedisConfig DTO provides type-safe configuration
 *
 * Docker-first approach:
 * 1. Check if Docker is available
 * 2. If yes, offer Docker setup (recommended for isolation)
 * 3. If Docker fails or unavailable, fall back to local setup
 * 4. Provide OS-specific installation guidance when needed
 *
 * Example usage:
 * ```php
 * use PhpHive\Cli\Concerns\Infrastructure\InteractsWithRedis;
 *
 * class MyAppType extends AbstractAppType
 * {
 *     use InteractsWithRedis;
 *
 *     public function collectConfiguration($input, $output): array
 *     {
 *         // Setup Redis for the application
 *         $redisConfig = $this->setupRedis('my-app', '/path/to/app');
 *
 *         return array_merge($config, $redisConfig);
 *     }
 * }
 * ```
 *
 * @see RedisSetupService For Redis setup business logic
 * @see RedisConfig For type-safe configuration DTO
 * @see InteractsWithDocker For Docker availability checks
 * @see InteractsWithPrompts For prompt helper methods
 */
trait InteractsWithRedis
{
    /**
     * Get the RedisSetupService instance.
     *
     * This abstract method must be implemented by the class using this trait
     * to provide access to the RedisSetupService for delegating setup operations.
     *
     * @return RedisSetupService The Redis setup service instance
     */
    abstract protected function redisSetupService(): RedisSetupService;

    /**
     * Orchestrate Redis setup with Docker-first approach.
     *
     * This is the main entry point for Redis setup. It determines whether
     * to use Docker or local installation based on availability and user preference.
     *
     * Workflow:
     * 1. Check if Docker is available
     * 2. If yes, inform user about Docker benefits and prompt to use it
     * 3. If user accepts Docker:
     *    - Collect Docker configuration (port, password)
     *    - Delegate to RedisSetupService for container setup
     *    - Return configuration if successful
     * 4. If Docker fails or user declines:
     *    - Fall back to local Redis setup
     *    - Prompt for local connection details or installation
     * 5. If Docker not installed, optionally show installation guidance
     *
     * @param  string $appName Application name for container naming
     * @param  string $appPath Absolute path to application directory for Docker Compose
     * @return array  Redis configuration array with keys:
     *                - redis_host: Server host (e.g., 'localhost', '127.0.0.1')
     *                - redis_port: Server port number (default: 6379)
     *                - redis_password: Authentication password (empty if none)
     *                - using_docker: Whether Docker is being used
     */
    protected function setupRedis(string $appName, string $appPath): array
    {
        // Check if Docker is available on the system
        if ($this->isDockerAvailable()) {
            // Inform user about Docker benefits (isolation, easy cleanup, no local install)
            $this->note('Docker detected! Using Docker provides isolated Redis instances.', 'Redis Setup');

            // Prompt user to use Docker (recommended for development)
            if ($this->confirm('Would you like to use Docker for Redis? (recommended)', true)) {
                // Attempt Docker setup
                $config = $this->setupDockerRedis($appName, $appPath);

                // If Docker setup succeeded, return the configuration
                if ($config !== null) {
                    return $config;
                }

                // Docker setup failed, inform user and fall back to local
                $this->warning('Docker setup failed. Falling back to local Redis.');
            }
        } elseif (! $this->isDockerInstalled() && $this->confirm('Docker not installed. See installation instructions?', false)) {
            // Docker not installed but user wants guidance
            $this->provideDockerInstallationGuidance();
        }

        // Use local Redis installation (either by choice or fallback)
        return $this->setupLocalRedis($appName);
    }

    /**
     * Set up Redis using Docker container.
     *
     * Creates and configures a Redis Docker container with secure defaults.
     * This method collects configuration from the user and delegates the actual
     * Docker operations to RedisSetupService.
     *
     * Workflow:
     * 1. Generate secure random password for Redis authentication
     * 2. Prompt user for port number (default: 6379)
     * 3. Create RedisConfig DTO with collected settings
     * 4. Delegate to RedisSetupService to create Docker container
     * 5. Display connection details on success
     *
     * The password is automatically generated using cryptographically secure
     * random bytes to ensure security. It's displayed to the user for reference.
     *
     * In non-interactive mode, uses default port 6379 and auto-generated password.
     *
     * @param  string     $appName Application name for container naming
     * @param  string     $appPath Absolute path to application directory for Docker Compose
     * @return array|null Redis configuration array on success, null on failure
     */
    protected function setupDockerRedis(string $appName, string $appPath): ?array
    {
        // Generate a secure 32-character password (16 bytes = 32 hex chars)
        $password = bin2hex(random_bytes(16));

        // Prompt for port number with availability checking (default: 6379 - Redis standard port)
        $port = $this->promptForAvailablePort(
            label: 'Redis port',
            defaultPort: 6379,
            hint: 'Port will be checked for availability'
        );

        // Create type-safe configuration object for Docker setup
        $redisConfig = new RedisConfig('localhost', $port, $password, true);

        // Delegate Docker container creation to service with loading spinner
        $result = $this->spin(
            fn (): ?RedisConfig => $this->redisSetupService()->setupDocker($redisConfig, $appPath),
            'Setting up Redis container...'
        );

        // Check if Docker setup failed
        if ($result === null) {
            $this->error('Failed to setup Redis container');

            return null;
        }

        // Display success message with connection details
        $this->info("✓ Redis ready at localhost:{$port} (password: {$password})");

        // Convert RedisConfig DTO to array format for application configuration
        return $result->toArray();
    }

    /**
     * Set up Redis using local installation.
     *
     * Handles local Redis setup when Docker is not available or not preferred.
     * This method checks if Redis is already running, attempts connection,
     * and provides installation guidance if needed.
     *
     * Workflow:
     * 1. Inform user about local Redis setup requirements
     * 2. Ask if Redis is already running locally
     * 3. If yes, test connection to default Redis port (6379)
     *    - On success: Return default configuration
     *    - On failure: Offer Docker as alternative or show installation guide
     * 4. If no, provide installation guidance and prompt for manual config
     *
     * This method handles multiple fallback scenarios to ensure the user
     * can successfully configure Redis regardless of their setup.
     *
     * @param  string $appName Application name (used for Docker fallback)
     * @return array  Redis configuration array with connection details
     */
    protected function setupLocalRedis(string $appName): array
    {
        // Inform user about local Redis requirements
        $this->note('Setting up local Redis. Ensure Redis is installed and running.', 'Local Redis');

        // Check if user has Redis already running
        if ($this->confirm('Is Redis already running locally?', false)) {
            // Test connection to default Redis instance (127.0.0.1:6379)
            if ($this->redisSetupService()->checkRedisConnection('127.0.0.1', 6379)) {
                // Connection successful, use default configuration
                $this->info('✓ Redis is running!');

                return ['redis_host' => '127.0.0.1', 'redis_port' => 6379, 'redis_password' => '', AppTypeInterface::CONFIG_USING_DOCKER => false];
            }

            // Connection failed, inform user
            $this->error('✗ Could not connect to Redis');

            // Offer Docker as a fallback option if available
            if ($this->isDockerAvailable() && $this->confirm('Use Docker instead?', true)) {
                $dockerConfig = $this->setupDockerRedis($appName, getcwd() ?? '.');
                if ($dockerConfig !== null) {
                    return $dockerConfig;
                }
            }

            // All options exhausted, show installation guide and exit
            $this->provideRedisInstallationGuidance();
            exit(1);
        }

        // Redis not running, provide installation guidance
        $this->provideRedisInstallationGuidance();

        // Prompt user for manual Redis configuration
        return $this->promptRedisConfiguration();
    }

    /**
     * Prompt user for manual Redis configuration.
     *
     * Collects Redis connection details from the user when automatic setup
     * is not possible or when Redis is installed but not yet configured.
     *
     * In non-interactive mode, returns sensible defaults (localhost:6379, no password).
     *
     * Configuration collected:
     * - Host: Redis server hostname or IP (default: localhost)
     * - Port: Redis server port (default: 6379)
     * - Password: Optional authentication password
     *
     * @return array Redis configuration array with connection details
     */
    protected function promptRedisConfiguration(): array
    {
        // Prompt for Redis host (default: localhost for local development)
        // In non-interactive mode, automatically uses default
        $host = $this->text('Redis host', 'localhost', 'localhost', true);

        // Prompt for Redis port (default: 6379 - Redis standard port)
        // In non-interactive mode, automatically uses default
        $port = (int) $this->text('Redis port', '6379', '6379', true);

        // Ask if password authentication is required
        // If yes, prompt for password securely (hidden input)
        // If no, use empty string (Redis default for no auth)
        // In non-interactive mode, defaults to false (no password)
        $password = $this->confirm('Does Redis require a password?', false) ? $this->password('Redis password') : '';

        // Return configuration array
        return ['redis_host' => $host, 'redis_port' => $port, 'redis_password' => $password, AppTypeInterface::CONFIG_USING_DOCKER => false];
    }

    /**
     * Provide Redis installation guidance based on operating system.
     *
     * Displays OS-specific installation instructions to help users install
     * Redis on their system. This method detects the user's operating system
     * and provides the most appropriate installation command or guidance.
     *
     * Supported operating systems:
     * - macOS: Homebrew installation command
     * - Linux: APT package manager command (Debian/Ubuntu)
     * - Windows: WSL2 recommendation or direct download link
     * - Other: Generic Redis documentation link
     *
     * Called when:
     * - Redis is not installed or not running
     * - Connection to local Redis fails
     * - User needs help setting up Redis
     */
    protected function provideRedisInstallationGuidance(): void
    {
        // Display informational message about Redis benefits
        $this->note('Redis provides high-performance caching and session storage.', 'Redis Not Available');

        // Provide OS-specific installation instructions
        match ($this->detectOS()) {
            // macOS: Use Homebrew package manager
            'macos' => $this->info('brew install redis && brew services start redis'),

            // Linux: Use APT package manager (Debian/Ubuntu)
            'linux' => $this->info('sudo apt-get install redis-server && sudo systemctl start redis-server'),

            // Windows: Recommend WSL2 or provide legacy download link
            'windows' => $this->info('Install WSL2 or download from https://github.com/microsoftarchive/redis/releases'),

            // Other/Unknown OS: Provide generic documentation link
            default => $this->info('Visit: https://redis.io/docs/getting-started/installation/'),
        };
    }
}
