<?php

declare(strict_types=1);

namespace PhpHive\Cli\DTOs\Infrastructure;

use InvalidArgumentException;
use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\Enums\StorageDriver;

/**
 * Storage Configuration Data Transfer Object.
 *
 * Encapsulates all object storage configuration parameters in a type-safe,
 * immutable object. Supports multiple storage backends (MinIO, S3) with
 * a unified interface.
 *
 * Supported storage drivers:
 * - MinIO: Self-hosted S3-compatible storage (Docker-friendly)
 * - Amazon S3: Fully managed cloud storage service
 *
 * Benefits:
 * - Type safety: All properties are strongly typed
 * - Immutability: Configuration cannot be accidentally modified
 * - Validation: Constructor ensures all required fields are present
 * - IDE support: Full autocomplete and type hints
 * - Testability: Easy to create test fixtures
 * - Multi-backend: Supports both MinIO and S3 with same interface
 *
 * Example usage:
 * ```php
 * // MinIO configuration
 * $config = new StorageConfig(
 *     driver: StorageDriver::MINIO,
 *     endpoint: 'localhost',
 *     port: 9000,
 *     accessKey: 'minioadmin',
 *     secretKey: 'minioadmin',
 *     bucket: 'my-app',
 *     usingDocker: true
 * );
 *
 * // S3 configuration
 * $config = new StorageConfig(
 *     driver: StorageDriver::S3,
 *     region: 'us-east-1',
 *     bucket: 'my-app-bucket',
 *     accessKey: 'AKIAIOSFODNN7EXAMPLE',
 *     secretKey: 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
 *     usingDocker: false
 * );
 *
 * // Convert to array for legacy code
 * $array = $config->toArray();
 *
 * // Create from array
 * $config = StorageConfig::fromArray($array);
 * ```
 */
final readonly class StorageConfig
{
    /**
     * Create a new storage configuration instance.
     *
     * @param StorageDriver $driver      Storage backend (MinIO or S3)
     * @param string        $bucket      Bucket name for storage
     * @param string        $accessKey   Access key for authentication
     * @param string        $secretKey   Secret key for authentication
     * @param bool          $usingDocker Whether Docker is being used
     * @param string|null   $endpoint    Storage server endpoint (MinIO only)
     * @param int|null      $port        Storage API port (MinIO only)
     * @param int|null      $consolePort Console UI port (MinIO only)
     * @param string|null   $region      AWS region (S3 only)
     */
    public function __construct(
        public StorageDriver $driver,
        public string $bucket,
        public string $accessKey,
        public string $secretKey,
        public bool $usingDocker,
        public ?string $endpoint = null,
        public ?int $port = null,
        public ?int $consolePort = null,
        public ?string $region = null,
    ) {}

    /**
     * Create a StorageConfig instance from an associative array.
     *
     * Converts legacy array-based configuration to a type-safe DTO.
     * Validates that all required keys are present and determines
     * the storage driver from the configuration.
     *
     * Supports both new format (storage_*) and legacy format (minio_*).
     *
     * @param  array<string, mixed> $data Configuration array
     * @return self                 StorageConfig instance
     *
     * @throws InvalidArgumentException If required keys are missing
     */
    public static function fromArray(array $data): self
    {
        // Determine storage driver (default to MinIO for backward compatibility)
        $driverValue = $data['storage_driver'] ?? 'minio';
        $storageDriver = StorageDriver::from($driverValue);

        // Support legacy minio_* keys for backward compatibility
        $bucket = $data['storage_bucket'] ?? $data['minio_bucket'] ?? null;
        $accessKey = $data['storage_access_key'] ?? $data['minio_access_key'] ?? null;
        $secretKey = $data['storage_secret_key'] ?? $data['minio_secret_key'] ?? null;

        // Validate required keys
        if ($bucket === null || $accessKey === null || $secretKey === null) {
            throw new InvalidArgumentException('Missing required storage configuration keys');
        }

        // Extract driver-specific configuration
        $endpoint = $data['storage_endpoint'] ?? $data['minio_endpoint'] ?? null;
        $port = isset($data['storage_port']) ? (int) $data['storage_port'] : (isset($data['minio_port']) ? (int) $data['minio_port'] : null);
        $consolePort = isset($data['storage_console_port']) ? (int) $data['storage_console_port'] : (isset($data['minio_console_port']) ? (int) $data['minio_console_port'] : null);
        $region = $data['storage_region'] ?? null;

        return new self(
            driver: $storageDriver,
            bucket: $bucket,
            accessKey: $accessKey,
            secretKey: $secretKey,
            usingDocker: (bool) ($data[AppTypeInterface::CONFIG_USING_DOCKER] ?? false),
            endpoint: $endpoint,
            port: $port,
            consolePort: $consolePort,
            region: $region,
        );
    }

    /**
     * Convert the configuration to an associative array.
     *
     * Returns an array compatible with AppTypeInterface configuration
     * constants, allowing seamless integration with existing code.
     *
     * The array format varies based on the storage driver:
     * - MinIO: Includes endpoint, port, console_port
     * - S3: Includes region instead of endpoint/port
     *
     * @return array<string, mixed> Configuration array
     */
    public function toArray(): array
    {
        $config = [
            'storage_driver' => $this->driver->value,
            'storage_bucket' => $this->bucket,
            'storage_access_key' => $this->accessKey,
            'storage_secret_key' => $this->secretKey,
            AppTypeInterface::CONFIG_USING_DOCKER => $this->usingDocker,
        ];

        // Add driver-specific configuration using match expression
        match ($this->driver) {
            StorageDriver::MINIO => $config = array_merge($config, [
                'storage_endpoint' => $this->endpoint,
                'storage_port' => $this->port,
                'storage_console_port' => $this->consolePort,
            ]),
            StorageDriver::S3 => $config = array_merge($config, [
                'storage_region' => $this->region,
            ]),
        };

        return $config;
    }
}
