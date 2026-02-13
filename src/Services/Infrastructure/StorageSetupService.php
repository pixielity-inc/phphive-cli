<?php

declare(strict_types=1);

namespace PhpHive\Cli\Services\Infrastructure;

use PhpHive\Cli\DTOs\Infrastructure\StorageConfig;
use PhpHive\Cli\Enums\StorageDriver;
use PhpHive\Cli\Support\Process;
use Pixielity\StubGenerator\Exceptions\StubNotFoundException;
use Pixielity\StubGenerator\Facades\Stub;
use RuntimeException;

/**
 * Storage Setup Service.
 *
 * Handles object storage infrastructure setup with support for multiple
 * backends (MinIO, S3). Provides Docker-first approach for MinIO and
 * configuration guidance for cloud-based S3.
 *
 * Supported storage drivers:
 * - MinIO: Self-hosted S3-compatible storage with Docker support
 * - Amazon S3: Fully managed cloud storage (configuration only)
 *
 * Key features:
 * - Multi-backend support (MinIO, S3)
 * - Docker container setup for MinIO
 * - Automatic bucket creation
 * - Health checking and readiness verification
 * - MinIO Console UI setup
 * - Graceful fallback between Docker and local
 *
 * Example usage:
 * ```php
 * $service = StorageSetupService::make($process);
 *
 * // Setup storage (tries Docker first for MinIO, falls back to local)
 * $config = $service->setup($config, '/path/to/app');
 *
 * // Setup Docker MinIO specifically
 * $config = $service->setupDocker($config, '/path/to/app');
 * ```
 */
final readonly class StorageSetupService
{
    /**
     * Create a new storage setup service instance.
     *
     * @param Process $process Process service for command execution
     */
    public function __construct(
        private Process $process,
    ) {}

    /**
     * Setup storage with driver-specific approach.
     *
     * Orchestrates storage setup based on the selected driver:
     * - MinIO: Attempts Docker first, then falls back to local setup
     * - S3: Returns configuration as-is (cloud-based, no setup needed)
     *
     * @param  StorageConfig $storageConfig Storage configuration
     * @param  string        $appPath       Absolute path to application directory
     * @return StorageConfig Updated storage configuration
     */
    public function setup(StorageConfig $storageConfig, string $appPath): StorageConfig
    {
        // S3 is cloud-based, no setup needed
        if ($storageConfig->driver === StorageDriver::S3) {
            return $storageConfig;
        }

        // MinIO: Try Docker setup if using Docker
        if ($storageConfig->usingDocker) {
            $dockerConfig = $this->setupDocker($storageConfig, $appPath);
            if ($dockerConfig instanceof StorageConfig) {
                return $dockerConfig;
            }
        }

        // Fall back to local setup (return config as-is)
        return new StorageConfig(
            driver: $storageConfig->driver,
            bucket: $storageConfig->bucket,
            accessKey: $storageConfig->accessKey,
            secretKey: $storageConfig->secretKey,
            usingDocker: false,
            endpoint: $storageConfig->endpoint,
            port: $storageConfig->port,
            consolePort: $storageConfig->consolePort,
            region: $storageConfig->region,
        );
    }

    /**
     * Create a new instance using static factory pattern.
     *
     * @param  Process $process Process service for command execution
     * @return self    New StorageSetupService instance
     */
    public static function make(Process $process): self
    {
        return new self($process);
    }

    /**
     * Setup storage using Docker container (MinIO only).
     *
     * Creates a Docker Compose configuration with MinIO service,
     * starts the container, waits for readiness, and creates the
     * default bucket.
     *
     * Process:
     * 1. Generate docker-compose.yml section for MinIO
     * 2. Start Docker container
     * 3. Wait for MinIO to be ready (health check)
     * 4. Create default bucket
     * 5. Return updated configuration
     *
     * Note: This method only supports MinIO. S3 is cloud-based and
     * doesn't require Docker setup.
     *
     * @param  StorageConfig      $storageConfig Storage configuration
     * @param  string             $appPath       Application directory path
     * @return StorageConfig|null Updated config on success, null on failure
     */
    public function setupDocker(StorageConfig $storageConfig, string $appPath): ?StorageConfig
    {
        // Only MinIO supports Docker setup
        if ($storageConfig->driver !== StorageDriver::MINIO) {
            return null;
        }

        // Extract app name from path
        $appName = basename($appPath);

        // Generate docker-compose file
        $composeGenerated = $this->generateMinioDockerComposeFile(
            $appPath,
            $appName,
            $storageConfig->port ?? 9000,
            $storageConfig->accessKey,
            $storageConfig->secretKey
        );

        if (! $composeGenerated) {
            return null;
        }

        // Start Docker containers
        $started = $this->startDockerContainers($appPath);
        if (! $started) {
            return null;
        }

        // Wait for MinIO to be ready
        $ready = $this->waitForDockerService($appPath, 'minio', 30);
        if (! $ready) {
            // MinIO may not be fully ready, but continue anyway
        }

        // Create default bucket
        $this->createMinioBucket(
            $appPath,
            $storageConfig->bucket,
            $storageConfig->accessKey,
            $storageConfig->secretKey
        );

        // Return updated configuration with localhost endpoint
        return new StorageConfig(
            driver: StorageDriver::MINIO,
            bucket: $storageConfig->bucket,
            accessKey: $storageConfig->accessKey,
            secretKey: $storageConfig->secretKey,
            usingDocker: true,
            endpoint: 'localhost',
            port: $storageConfig->port ?? 9000,
            consolePort: $storageConfig->consolePort ?? 9001,
        );
    }

    /**
     * Check if storage is accessible at the given host and port.
     *
     * Attempts to connect to MinIO health endpoint to verify the connection.
     * Only applicable for MinIO (not S3).
     *
     * @param  string $host Storage host
     * @param  int    $port Storage port
     * @return bool   True if storage is accessible
     */
    public function checkStorageConnection(string $host, int $port): bool
    {
        try {
            return $this->process->succeeds(['curl', '-f', '-s', "http://{$host}:{$port}/minio/health/live"]);
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Generate docker-compose.yml file with MinIO service.
     *
     * Creates or appends to docker-compose.yml with MinIO server configuration.
     * Includes both API server and Console UI in a single container.
     *
     * @param  string $appPath   Application directory path
     * @param  string $appName   Application name
     * @param  int    $port      MinIO API port
     * @param  string $accessKey MinIO access key
     * @param  string $secretKey MinIO secret key
     * @return bool   True on success, false on failure
     */
    private function generateMinioDockerComposeFile(
        string $appPath,
        string $appName,
        int $port,
        string $accessKey,
        string $secretKey
    ): bool {
        try {
            // Set base path for stubs
            Stub::setBasePath(dirname(__DIR__, 3) . '/stubs');

            // Normalize app name for container/volume names
            $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $appName) ?? $appName);

            // Create stub with replacements
            Stub::create('docker/minio.yml', [
                'container_prefix' => "phphive-{$normalizedName}",
                'volume_prefix' => "phphive-{$normalizedName}",
                'network_name' => "phphive-{$normalizedName}",
                'minio_access_key' => $accessKey,
                'minio_secret_key' => $secretKey,
                'minio_port' => (string) $port,
                'minio_console_port' => (string) ($port + 1),
            ])->saveTo($appPath, 'docker-compose.yml');

            return true;
        } catch (StubNotFoundException) {
            return false;
        }
    }

    /**
     * Create a bucket in MinIO using Docker exec.
     *
     * Executes MinIO Client (mc) commands inside the MinIO container to:
     * 1. Configure mc alias for the local MinIO server
     * 2. Create the specified bucket
     *
     * @param  string $appPath   Application directory path
     * @param  string $bucket    Bucket name to create
     * @param  string $accessKey MinIO access key
     * @param  string $secretKey MinIO secret key
     * @return bool   True if bucket created successfully
     */
    private function createMinioBucket(
        string $appPath,
        string $bucket,
        string $accessKey,
        string $secretKey
    ): bool {
        // Configure mc alias
        $aliasProcess = $this->process->execute([
            'docker',
            'compose',
            'exec',
            '-T',
            'minio',
            'mc',
            'alias',
            'set',
            'local',
            'http://localhost:9000',
            $accessKey,
            $secretKey,
        ], $appPath);

        $aliasProcess->run();

        if (! $aliasProcess->isSuccessful()) {
            return false;
        }

        // Create bucket
        $bucketProcess = $this->process->execute([
            'docker',
            'compose',
            'exec',
            '-T',
            'minio',
            'mc',
            'mb',
            "local/{$bucket}",
        ], $appPath);

        $bucketProcess->run();

        // Return true if bucket created or already exists
        if ($bucketProcess->isSuccessful()) {
            return true;
        }

        return str_contains($bucketProcess->getErrorOutput(), 'already exists');
    }

    /**
     * Start Docker containers using docker-compose.
     *
     * @param  string $appPath Absolute path to application directory
     * @return bool   True if containers started successfully
     */
    private function startDockerContainers(string $appPath): bool
    {
        return $this->process->succeeds(['docker', 'compose', 'up', '-d'], $appPath, 300);
    }

    /**
     * Wait for a Docker service to be ready.
     *
     * @param  string $appPath     Absolute path to application directory
     * @param  string $serviceName Name of service in docker-compose.yml
     * @param  int    $maxAttempts Maximum number of polling attempts
     * @return bool   True if service is ready, false if timeout
     */
    private function waitForDockerService(string $appPath, string $serviceName, int $maxAttempts = 30): bool
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            if ($this->process->succeeds(
                ['docker', 'compose', 'exec', '-T', $serviceName, 'echo', 'ready'],
                $appPath
            )) {
                return true;
            }

            sleep(2);
            $attempts++;
        }

        return false;
    }
}
