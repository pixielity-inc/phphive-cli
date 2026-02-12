<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns;

use PhpHive\Cli\Support\Filesystem;
use RuntimeException;

/**
 * Redis Interaction Trait.
 *
 * This trait provides comprehensive Redis setup functionality for application
 * types that require caching and session storage configuration. It supports
 * both Docker-based and local Redis setups with automatic configuration and
 * graceful fallbacks.
 *
 * Key features:
 * - Docker-first approach: Recommends Docker when available
 * - Automatic Docker Compose integration
 * - Container management and health checking
 * - Secure password generation
 * - Local Redis fallback for non-Docker setups
 * - Graceful error handling with fallback options
 * - Detailed user feedback using Laravel Prompts
 * - Reusable across multiple app types (Magento, Laravel, Symfony, etc.)
 *
 * Docker-first workflow:
 * 1. Check if Docker is available
 * 2. If yes, offer Docker Redis setup (recommended)
 * 3. Generate secure password
 * 4. Generate docker-compose section for Redis
 * 5. Start Docker container
 * 6. Wait for Redis to be ready (health check)
 * 7. Return connection details with password
 * 8. If Docker unavailable or user declines, fall back to local setup
 *
 * Local Redis workflow:
 * 1. Assume Redis is installed and running locally
 * 2. Prompt for Redis host and port
 * 3. Prompt for password (if configured)
 * 4. Return configuration for application
 * 5. Provide installation guidance if needed
 *
 * Example usage:
 * ```php
 * use PhpHive\Cli\Concerns\InteractsWithRedis;
 * use PhpHive\Cli\Concerns\InteractsWithDocker;
 *
 * class MyAppType extends AbstractAppType
 * {
 *     use InteractsWithRedis;
 *     use InteractsWithDocker;
 *
 *     public function collectConfiguration($input, $output): array
 *     {
 *         $this->input = $input;
 *         $this->output = $output;
 *
 *         // Orchestrate Redis setup (Docker-first)
 *         $cacheConfig = $this->setupRedis('my-app', '/path/to/app');
 *
 *         return $cacheConfig;
 *     }
 * }
 * ```
 *
 * Security considerations:
 * - Passwords are generated using cryptographically secure random bytes
 * - Passwords are 32 characters long (hex-encoded 16 bytes)
 * - Docker containers are isolated per project
 * - Connection attempts include health checks
 * - Password authentication is enforced in Docker setup
 *
 * @phpstan-ignore-next-line trait.unused
 *
 * @see AbstractAppType For base app type functionality
 * @see InteractsWithDocker For Docker management functionality
 * @see InteractsWithPrompts For prompt helper methods
 */
trait InteractsWithRedis
{
    /**
     * Get the Filesystem service instance.
     *
     * This method provides access to the Filesystem service for file operations.
     * It should be implemented by the class using this trait to return the
     * appropriate Filesystem instance from the dependency injection container.
     *
     * @return Filesystem The Filesystem service instance
     */
    abstract protected function filesystem(): Filesystem;

    /**
     * Orchestrate Redis setup with Docker-first approach.
     *
     * This is the main entry point for Redis setup. It intelligently
     * chooses between Docker and local Redis based on availability and
     * user preference, with graceful fallbacks at each step.
     *
     * Decision flow:
     * 1. Check if Docker is available (requires InteractsWithDocker trait)
     * 2. If Docker available:
     *    - Offer Docker setup (recommended)
     *    - If user accepts → setupDockerRedis()
     *    - If user declines → setupLocalRedis()
     * 3. If Docker not available:
     *    - Show installation guidance (optional)
     *    - Fall back to setupLocalRedis()
     *
     * Redis features:
     * - In-memory data structure store
     * - Caching and session storage
     * - Pub/Sub messaging
     * - Persistence options (RDB, AOF)
     * - High performance and scalability
     *
     * Return value structure:
     * ```php
     * [
     *     'redis_host' => 'localhost',      // Host
     *     'redis_port' => 6379,             // Port
     *     'redis_password' => 'password',   // Password (optional)
     *     'using_docker' => true,           // Whether Docker is used
     * ]
     * ```
     *
     * @param  string $appName Application name for defaults
     * @param  string $appPath Absolute path to application directory
     * @return array  Redis configuration array
     */
    protected function setupRedis(string $appName, string $appPath): array
    {
        // Check if Docker is available (requires InteractsWithDocker trait)

        if ($this->isDockerAvailable()) {
            // Docker is available - offer Docker setup
            $this->note(
                'Docker detected! Using Docker provides isolated Redis instances, easy management, and no local installation needed.',
                'Redis Setup'
            );

            $useDocker = $this->confirm(
                label: 'Would you like to use Docker for Redis? (recommended)',
                default: true
            );

            if ($useDocker) {
                $cacheConfig = $this->setupDockerRedis($appName, $appPath);
                if ($cacheConfig !== null) {
                    return $cacheConfig;
                }

                // Docker setup failed, fall back to local
                $this->warning('Docker setup failed. Falling back to local Redis setup.');
            }

        } elseif (! $this->isDockerInstalled()) {
            // Docker not installed - offer installation guidance
            $installDocker = $this->confirm(
                label: 'Docker is not installed. Would you like to see installation instructions?',
                default: false
            );

            if ($installDocker) {
                $this->provideDockerInstallationGuidance();
                $this->info('After installing Docker, you can recreate this application to use Docker.');
            }
        }

        // Fall back to local Redis setup
        return $this->setupLocalRedis($appName);
    }

    /**
     * Set up Redis using Docker container.
     *
     * Creates a Docker Compose configuration with Redis service
     * and starts the container. Includes health checking to ensure
     * Redis is ready before returning.
     *
     * Process:
     * 1. Generate secure password
     * 2. Prompt for port configuration (default: 6379)
     * 3. Generate docker-compose.yml section for Redis
     * 4. Start Docker container
     * 5. Wait for Redis to be ready (health check)
     * 6. Return connection details with password
     *
     * Generated configuration:
     * - Service name: redis
     * - Image: redis:7-alpine
     * - Port: 6379 (default, configurable)
     * - Volume: Persistent data storage with AOF
     * - Command: redis-server with password and persistence
     * - Health check: redis-cli ping
     *
     * Container naming:
     * - Format: phphive-{app-name}-redis
     * - Example: phphive-my-shop-redis
     *
     * @param  string     $appName Application name
     * @param  string     $appPath Application directory path
     * @return array|null Redis config on success, null on failure
     */
    protected function setupDockerRedis(string $appName, string $appPath): ?array
    {
        // Check if running in non-interactive mode
        if (! $this->input->isInteractive()) {
            return null;
        }

        // =====================================================================
        // CONFIGURATION
        // =====================================================================

        $this->info('Configuring Redis...');

        // Generate secure password (32 characters hex)
        $password = bin2hex(random_bytes(16));

        // Prompt for port configuration
        $portInput = $this->text(
            label: 'Redis port',
            placeholder: '6379',
            default: '6379',
            required: true,
            hint: 'Port for Redis server'
        );
        $port = (int) $portInput;

        // =====================================================================
        // GENERATE DOCKER COMPOSE FILE
        // =====================================================================

        $this->info('Generating docker-compose.yml...');

        $composeGenerated = $this->generateRedisDockerComposeFile(
            $appPath,
            $appName,
            $port,
            $password
        );

        if (! $composeGenerated) {
            $this->error('Failed to generate docker-compose.yml');

            return null;
        }

        // =====================================================================
        // START CONTAINER
        // =====================================================================

        $this->info('Starting Redis container...');

        $started = $this->spin(
            callback: fn (): bool => $this->startDockerContainers($appPath),
            message: 'Starting Redis container...'
        );

        if (! $started) {
            $this->error('Failed to start Redis container');

            return null;
        }

        // =====================================================================
        // WAIT FOR REDIS TO BE READY
        // =====================================================================

        $this->info('Waiting for Redis to be ready...');

        $ready = $this->spin(
            callback: fn (): bool => $this->waitForDockerService($appPath, 'redis', 30),
            message: 'Waiting for Redis...'
        );

        if (! $ready) {
            $this->warning('Redis may not be fully ready. You may need to wait a moment before using it.');
        } else {
            $this->info('✓ Redis is ready!');
        }

        // =====================================================================
        // RETURN CONFIGURATION
        // =====================================================================

        $this->info('✓ Docker Redis setup complete!');
        $this->info("Redis connection: localhost:{$port}");
        $this->info("Redis password: {$password}");

        return [
            'redis_host' => 'localhost',
            'redis_port' => $port,
            'redis_password' => $password,
            'using_docker' => true,
        ];
    }

    /**
     * Generate docker-compose.yml file from template.
     *
     * Reads the Redis template file, replaces placeholders with actual values,
     * and writes the docker-compose.yml file to the application directory.
     * If a docker-compose.yml already exists, it appends the Redis service.
     *
     * Template placeholders:
     * - {{CONTAINER_PREFIX}}: phphive-{app-name}
     * - {{VOLUME_PREFIX}}: phphive-{app-name}
     * - {{NETWORK_NAME}}: phphive-{app-name}
     * - {{REDIS_PORT}}: Redis port (6379)
     * - {{REDIS_PASSWORD}}: Redis password
     *
     * @param  string $appPath  Application directory path
     * @param  string $appName  Application name
     * @param  int    $port     Redis port
     * @param  string $password Redis password
     * @return bool   True on success, false on failure
     */
    protected function generateRedisDockerComposeFile(
        string $appPath,
        string $appName,
        int $port,
        string $password
    ): bool {
        // Get template path
        $templatePath = dirname(__DIR__, 2) . '/stubs/docker/redis.yml';

        if (! $this->filesystem()->exists($templatePath)) {
            return false;
        }

        // Read template using Filesystem
        try {
            $template = $this->filesystem()->read($templatePath);
        } catch (RuntimeException) {
            return false;
        }

        // Normalize app name for container/volume names
        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $appName) ?? $appName);

        // Replace placeholders
        $replacements = [
            '{{CONTAINER_PREFIX}}' => "phphive-{$normalizedName}",
            '{{VOLUME_PREFIX}}' => "phphive-{$normalizedName}",
            '{{NETWORK_NAME}}' => "phphive-{$normalizedName}",
            '{{REDIS_PORT}}' => (string) $port,
            '{{REDIS_PASSWORD}}' => $password,
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Write docker-compose.yml using Filesystem
        $outputPath = $appPath . '/docker-compose.yml';

        try {
            $this->filesystem()->write($outputPath, $content);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Set up Redis using local installation.
     *
     * Falls back to local Redis setup when Docker is not available
     * or user prefers local installation. Prompts for connection details
     * and password configuration.
     *
     * Process:
     * 1. Display informational note about local setup
     * 2. Check if user wants to configure manually or use defaults
     * 3. Prompt for Redis connection details
     * 4. Prompt for password (if configured)
     * 5. Return configuration array
     *
     * Local installation requirements:
     * - Redis must be installed and running
     * - Default port: 6379
     * - Password is optional but recommended
     *
     * Installation guidance:
     * - macOS: brew install redis
     * - Linux: apt-get install redis-server / yum install redis
     * - Windows: Download from GitHub releases or use WSL
     *
     * @param  string $appName Application name
     * @return array  Redis configuration array
     */
    protected function setupLocalRedis(string $appName): array
    {
        $this->note(
            'Setting up local Redis. Ensure Redis is installed and running.',
            'Local Redis Setup'
        );

        // Check if user wants automatic configuration
        $autoConfig = $this->confirm(
            label: 'Is Redis already running locally?',
            default: false
        );

        if ($autoConfig) {
            // Verify Redis is actually running
            $isRunning = $this->spin(
                callback: fn (): bool => $this->checkRedisConnection('127.0.0.1', 6379),
                message: 'Checking Redis connection...'
            );

            if ($isRunning) {
                $this->info('✓ Redis is running and accessible!');

                // Use default local configuration
                return [
                    'redis_host' => '127.0.0.1',
                    'redis_port' => 6379,
                    'redis_password' => '',
                    'using_docker' => false,
                ];
            }

            // Redis check failed
            $this->error('✗ Could not connect to Redis on 127.0.0.1:6379');
            $this->warning('Redis does not appear to be running.');

            // Offer to try Docker if available
            if ($this->isDockerAvailable()) {
                $tryDocker = $this->confirm(
                    label: 'Would you like to use Docker for Redis instead?',
                    default: true
                );

                if ($tryDocker) {
                    $cwd = getcwd();
                    if ($cwd === false) {
                        $this->error('Could not determine current working directory');
                        exit(1);
                    }
                    $dockerConfig = $this->setupDockerRedis($appName, $cwd);
                    if ($dockerConfig !== null) {
                        return $dockerConfig;
                    }
                }
            }

            // Show installation guidance
            $this->provideRedisInstallationGuidance();
            $this->error('Please install and start Redis, then try again.');
            exit(1);
        }

        // User said Redis is not running - provide installation guidance
        $this->provideRedisInstallationGuidance();
        $this->info('After installing and starting Redis, please configure the connection details.');

        // Prompt for manual configuration
        return $this->promptRedisConfiguration($appName);
    }

    /**
     * Check if Redis is accessible at the given host and port.
     *
     * Attempts to connect to Redis and execute a PING command to verify
     * the connection is working. This is a quick health check to ensure
     * Redis is running and accessible.
     *
     * @param  string $host Redis host
     * @param  int    $port Redis port
     * @return bool   True if Redis is accessible, false otherwise
     */
    protected function checkRedisConnection(string $host, int $port): bool
    {
        try {
            // Try to connect using redis-cli ping
            $result = $this->process()->run(['redis-cli', '-h', $host, '-p', (string) $port, 'ping']);

            return trim($result) === 'PONG';
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Provide Redis installation guidance based on operating system.
     *
     * Displays helpful information and instructions for installing Redis
     * on the user's operating system. Includes download links, installation
     * methods, and verification steps.
     *
     * Installation guidance by OS:
     *
     * macOS:
     * - Homebrew installation (recommended)
     * - Verification and startup commands
     *
     * Linux:
     * - Package manager installation
     * - Systemd service setup
     *
     * Windows:
     * - WSL installation (recommended)
     * - Native Windows port
     */
    protected function provideRedisInstallationGuidance(): void
    {

        $os = $this->detectOS();

        $this->note(
            'Redis is not running. Redis provides high-performance caching and session storage.',
            'Redis Not Available'
        );

        match ($os) {
            'macos' => $this->provideMacOSRedisGuidance(),
            'linux' => $this->provideLinuxRedisGuidance(),
            'windows' => $this->provideWindowsRedisGuidance(),
            default => $this->provideGenericRedisGuidance(),
        };
    }

    /**
     * Provide macOS-specific Redis installation guidance.
     */
    protected function provideMacOSRedisGuidance(): void
    {
        $this->info('macOS Installation:');
        $this->info('');
        $this->info('Homebrew (Recommended):');
        $this->info('  brew install redis');
        $this->info('  brew services start redis');
        $this->info('');
        $this->info('After installation:');
        $this->info('  1. Redis will start automatically');
        $this->info('  2. Verify with: redis-cli ping');
        $this->info('  3. Documentation: https://redis.io/docs');
    }

    /**
     * Provide Linux-specific Redis installation guidance.
     */
    protected function provideLinuxRedisGuidance(): void
    {
        $this->info('Linux Installation:');
        $this->info('');
        $this->info('Ubuntu/Debian:');
        $this->info('  sudo apt-get update');
        $this->info('  sudo apt-get install redis-server');
        $this->info('  sudo systemctl start redis-server');
        $this->info('');
        $this->info('RHEL/CentOS:');
        $this->info('  sudo yum install redis');
        $this->info('  sudo systemctl start redis');
        $this->info('');
        $this->info('After installation:');
        $this->info('  1. Verify with: redis-cli ping');
        $this->info('  2. Documentation: https://redis.io/docs');
    }

    /**
     * Provide Windows-specific Redis installation guidance.
     */
    protected function provideWindowsRedisGuidance(): void
    {
        $this->info('Windows Installation:');
        $this->info('');
        $this->info('Option 1: WSL (Recommended):');
        $this->info('  1. Install WSL2');
        $this->info('  2. Follow Linux installation steps');
        $this->info('');
        $this->info('Option 2: Native Windows Port:');
        $this->info('  1. Download from: https://github.com/microsoftarchive/redis/releases');
        $this->info('  2. Extract and run redis-server.exe');
        $this->info('');
        $this->info('After installation:');
        $this->info('  1. Verify with: redis-cli ping');
        $this->info('  2. Documentation: https://redis.io/docs');
    }

    /**
     * Provide generic Redis installation guidance.
     */
    protected function provideGenericRedisGuidance(): void
    {
        $this->info('Redis Installation:');
        $this->info('');
        $this->info('Visit the official Redis documentation:');
        $this->info('  https://redis.io/docs/getting-started/installation/');
        $this->info('');
        $this->info('After installation, verify with:');
        $this->info('  redis-cli ping');
    }

    /**
     * Prompt user for manual Redis configuration.
     *
     * This method provides configuration prompts when automatic setup
     * is not available or desired. It prompts the user to enter Redis
     * connection details for an existing installation.
     *
     * Use cases:
     * - User prefers manual configuration
     * - Automatic setup failed
     * - Redis already running
     * - Using remote Redis server
     * - Using managed Redis service
     *
     * Interactive prompts:
     * 1. Redis host (default: localhost)
     * 2. Redis port (default: 6379)
     * 3. Redis password (optional)
     *
     * Return value structure:
     * ```php
     * [
     *     'redis_host' => 'localhost',
     *     'redis_port' => 6379,
     *     'redis_password' => 'password',
     *     'using_docker' => false,
     * ]
     * ```
     *
     * Non-interactive mode:
     * - Returns defaults for all values
     * - Host: localhost, Port: 6379
     * - Password: empty
     *
     * @param  string $appName Application name (used for context)
     * @return array  Redis configuration array with user-provided values
     */
    protected function promptRedisConfiguration(string $appName): array
    {
        // Check if running in non-interactive mode
        if (! $this->input->isInteractive()) {
            // Return defaults for non-interactive mode
            return [
                'redis_host' => 'localhost',
                'redis_port' => 6379,
                'redis_password' => '',
                'using_docker' => false,
            ];
        }

        // Display informational note about manual configuration
        $this->note(
            'Please enter the connection details for your Redis instance.',
            'Manual Redis Configuration'
        );

        // =====================================================================
        // REDIS CONNECTION DETAILS
        // =====================================================================

        // Prompt for Redis host
        $host = $this->text(
            label: 'Redis host',
            placeholder: 'localhost',
            default: 'localhost',
            required: true,
            hint: 'The Redis server hostname or IP address'
        );

        // Prompt for Redis port
        $portInput = $this->text(
            label: 'Redis port',
            placeholder: '6379',
            default: '6379',
            required: true,
            hint: 'The Redis server port number'
        );
        $port = (int) $portInput;

        // =====================================================================
        // PASSWORD CONFIGURATION
        // =====================================================================

        $hasPassword = $this->confirm(
            label: 'Does Redis require a password?',
            default: false,
            hint: 'Redis can run with or without password authentication'
        );

        $redisPassword = '';
        if ($hasPassword) {
            $redisPassword = $this->password(
                label: 'Redis password',
                required: true,
                hint: 'Enter the Redis password'
            );
        }

        // Return configuration
        return [
            'redis_host' => $host,
            'redis_port' => $port,
            'redis_password' => $redisPassword,
            'using_docker' => false,
        ];
    }
}
