<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns\Infrastructure;

use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\DTOs\Infrastructure\StorageConfig;
use PhpHive\Cli\Enums\StorageDriver;
use PhpHive\Cli\Services\Infrastructure\StorageSetupService;

/**
 * Storage Interaction Trait.
 *
 * This trait provides user interaction and prompting for object storage setup.
 * It supports multiple storage backends (MinIO, S3) and focuses solely on
 * collecting user input, delegating all business logic to StorageSetupService
 * for better separation of concerns and testability.
 *
 * Supported storage drivers:
 * - MinIO: Self-hosted S3-compatible object storage (Docker-friendly)
 * - Amazon S3: Fully managed cloud storage service
 *
 * Object storage provides:
 * - Scalable file storage for uploads, media, backups
 * - S3-compatible API (works with AWS SDK and tools)
 * - High-performance blob storage
 * - Built-in web console (MinIO) or AWS Console (S3)
 * - Distributed and scalable architecture
 * - Access control with access keys and secret keys
 *
 * Architecture:
 * - This trait handles user prompts and input collection
 * - StorageSetupService handles Docker setup and container management
 * - StorageConfig DTO provides type-safe configuration
 * - StorageDriver enum defines available backends
 *
 * Docker-first approach (MinIO only):
 * 1. User selects storage driver (MinIO or S3)
 * 2. For MinIO: Check if Docker is available
 * 3. If yes, offer Docker setup (recommended for isolation and ease)
 * 4. If Docker fails or unavailable, fall back to local setup
 * 5. For S3: Collect AWS credentials and region (no Docker needed)
 * 6. Automatically create default bucket (MinIO only)
 *
 * Use cases:
 * - File uploads (user avatars, documents, attachments)
 * - Media storage (images, videos, audio files)
 * - Backup storage (database backups, application backups)
 * - Static asset hosting (CSS, JS, images for CDN)
 * - Data lake storage (logs, analytics data)
 *
 * Example usage:
 * ```php
 * use PhpHive\Cli\Concerns\Infrastructure\InteractsWithStorage;
 *
 * class MyAppType extends AbstractAppType
 * {
 *     use InteractsWithStorage;
 *
 *     public function collectConfiguration($input, $output): array
 *     {
 *         // Setup storage for the application
 *         $storageConfig = $this->setupStorage('my-app', '/path/to/app');
 *
 *         return array_merge($config, $storageConfig);
 *     }
 * }
 * ```
 *
 * @see StorageSetupService For storage setup business logic
 * @see StorageConfig For type-safe configuration DTO
 * @see StorageDriver For available storage backend enums
 * @see InteractsWithDocker For Docker availability checks
 * @see InteractsWithPrompts For prompt helper methods
 */
trait InteractsWithStorage
{
    /**
     * Get the StorageSetupService instance.
     *
     * This abstract method must be implemented by the class using this trait
     * to provide access to the StorageSetupService for delegating setup operations.
     *
     * @return StorageSetupService The storage setup service instance
     */
    abstract protected function storageSetupService(): StorageSetupService;

    /**
     * Orchestrate storage setup with driver selection.
     *
     * This is the main entry point for storage setup. It prompts the user to
     * select a storage driver and then delegates to the appropriate setup method.
     *
     * Workflow:
     * 1. Prompt user to select storage driver (MinIO or S3)
     * 2. Based on selection:
     *    - MinIO: Check Docker availability, collect config, setup container
     *    - S3: Collect AWS credentials and region
     * 3. Return configuration array
     *
     * @param  string $appName Application name used for bucket naming
     * @param  string $appPath Absolute path to application directory for Docker Compose
     * @return array  Storage configuration array with keys varying by driver:
     *                MinIO: storage_driver, storage_endpoint, storage_port, storage_bucket,
     *                storage_access_key, storage_secret_key, storage_console_port, using_docker
     *                S3: storage_driver, storage_region, storage_bucket,
     *                storage_access_key, storage_secret_key, using_docker
     */
    protected function setupStorage(string $appName, string $appPath): array
    {
        // Prompt user to select storage driver
        $driverValue = $this->select(
            label: 'Select storage backend',
            options: StorageDriver::choices(),
            default: StorageDriver::MINIO->value
        );

        $storageDriver = StorageDriver::from($driverValue);

        // Delegate to driver-specific setup method
        return match ($storageDriver) {
            StorageDriver::MINIO => $this->setupMinioStorage($appName, $appPath),
            StorageDriver::S3 => $this->setupS3Storage($appName),
        };
    }

    /**
     * Setup MinIO storage with Docker-first approach.
     *
     * Workflow:
     * 1. Check if Docker is available
     * 2. If yes, prompt user to use Docker (recommended)
     * 3. If user accepts Docker:
     *    - Collect Docker configuration (port, access key, secret key, bucket)
     *    - Delegate to StorageSetupService for container setup
     *    - Automatically create default bucket
     *    - Return configuration if successful
     * 4. If Docker fails or user declines:
     *    - Fall back to local MinIO setup
     *    - Prompt for local connection details
     *
     * Security considerations:
     * - Access keys and secret keys are auto-generated using cryptographically secure random bytes
     * - Keys are displayed to user for reference and configuration
     * - Default credentials (minioadmin/minioadmin) are avoided for security
     *
     * @param  string $appName Application name used for bucket naming
     * @param  string $appPath Absolute path to application directory for Docker Compose
     * @return array  MinIO storage configuration array
     */
    private function setupMinioStorage(string $appName, string $appPath): array
    {
        // Check if Docker is available and user wants to use it
        if ($this->isDockerAvailable() && $this->confirm('Use Docker for MinIO? (recommended)', true)) {
            // Collect Docker configuration from user
            $config = $this->promptDockerMinioConfig($appName);

            // Delegate Docker setup to service
            $result = $this->storageSetupService()->setupDocker($config, $appPath);

            // If Docker setup succeeded, convert config to array and return
            if ($result !== null) {
                return $result->toArray();
            }

            // Docker setup failed, inform user and fall back to local
            $this->warning('Docker setup failed. Falling back to local setup.');
        }

        // Use local MinIO installation
        return $this->setupLocalMinioStorage($appName);
    }

    /**
     * Setup Amazon S3 storage.
     *
     * Collects AWS credentials and configuration for S3 storage.
     * S3 is cloud-based, so no Docker setup is needed.
     *
     * Workflow:
     * 1. Display information about AWS credentials requirement
     * 2. Prompt for AWS region
     * 3. Prompt for bucket name
     * 4. Prompt for AWS access key and secret key
     * 5. Display instructions for environment variables
     * 6. Return configuration array
     *
     * @param  string $appName Application name used for default bucket naming
     * @return array  S3 storage configuration array
     */
    private function setupS3Storage(string $appName): array
    {
        $this->info('Setting up Amazon S3 storage...');

        // Display note about AWS credentials
        $this->note(
            'Amazon S3 requires AWS credentials. You can configure them in your .env file.',
            'AWS Configuration'
        );

        // Prompt for AWS region
        $region = $this->text(
            label: 'AWS Region',
            placeholder: 'us-east-1',
            default: 'us-east-1',
            required: true,
            hint: 'AWS region where your S3 bucket is located'
        );

        // Sanitize app name for bucket naming (lowercase, alphanumeric + hyphens)
        $defaultBucket = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $appName) ?? $appName);

        // Prompt for bucket name
        $bucket = $this->text(
            label: 'S3 bucket name',
            default: $defaultBucket,
            required: true,
            hint: 'Must be globally unique across all AWS accounts'
        );

        // Prompt for AWS access key
        $accessKey = $this->text(
            label: 'AWS Access Key ID',
            required: true,
            hint: 'Your AWS IAM access key'
        );

        // Prompt for AWS secret key (secure input)
        $secretKey = $this->password(
            label: 'AWS Secret Access Key',
            required: true,
            hint: 'Your AWS IAM secret key'
        );

        // Display environment variable instructions
        $this->note(
            "Configure AWS credentials in your .env file:\n\n" .
            "AWS_ACCESS_KEY_ID={$accessKey}\n" .
            "AWS_SECRET_ACCESS_KEY={$secretKey}\n" .
            "AWS_DEFAULT_REGION={$region}\n" .
            "AWS_BUCKET={$bucket}",
            'Environment Variables'
        );

        // Create and return configuration
        $storageConfig = new StorageConfig(
            driver: StorageDriver::S3,
            bucket: $bucket,
            accessKey: $accessKey,
            secretKey: $secretKey,
            usingDocker: false,
            region: $region,
        );

        return $storageConfig->toArray();
    }

    /**
     * Prompt user for Docker MinIO configuration.
     *
     * Collects configuration needed for Docker-based MinIO setup:
     * - API port number (default: 9000)
     * - Console port number (default: 9001)
     * - Access key (auto-generated for security)
     * - Secret key (auto-generated for security)
     * - Bucket name (derived from app name, sanitized)
     *
     * The access and secret keys are automatically generated using cryptographically
     * secure random bytes to ensure security. They're displayed to the user for
     * reference and must be saved in the application's environment configuration.
     *
     * Bucket naming:
     * - Derived from application name
     * - Converted to lowercase
     * - Special characters replaced with hyphens
     * - Must be DNS-compliant (S3 bucket naming rules)
     *
     * @param  string        $appName Application name for bucket naming
     * @return StorageConfig Type-safe configuration object for Docker setup
     */
    private function promptDockerMinioConfig(string $appName): StorageConfig
    {
        // Generate secure 20-character access key (10 bytes = 20 hex chars)
        $accessKey = bin2hex(random_bytes(10));

        // Generate secure 40-character secret key (20 bytes = 40 hex chars)
        $secretKey = bin2hex(random_bytes(20));

        // Prompt for API port number with availability checking (default: 9000 - MinIO standard port)
        $port = $this->promptForAvailablePort(
            label: 'MinIO API port',
            defaultPort: 9000,
            hint: 'Port will be checked for availability'
        );

        // Prompt for Console port number with availability checking (default: 9001 - MinIO Console standard port)
        $consolePort = $this->promptForAvailablePort(
            label: 'MinIO Console port',
            defaultPort: 9001,
            hint: 'Port will be checked for availability'
        );

        // Sanitize app name for bucket naming (lowercase, replace special chars with hyphens)
        $defaultBucket = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $appName) ?? $appName);

        // Prompt for bucket name with sanitized default
        $bucket = $this->text('Default bucket name', default: $defaultBucket, required: true);

        // Display connection information and credentials for user reference
        $this->info("API: http://localhost:{$port} | Console: http://localhost:{$consolePort}");
        $this->info("Access Key: {$accessKey} | Secret Key: {$secretKey}");

        // Create and return type-safe configuration object
        return new StorageConfig(
            driver: StorageDriver::MINIO,
            bucket: $bucket,
            accessKey: $accessKey,
            secretKey: $secretKey,
            usingDocker: true,
            endpoint: 'localhost',
            port: $port,
            consolePort: $consolePort,
        );
    }

    /**
     * Set up MinIO using local installation.
     *
     * Prompts user for local MinIO connection details. This is used when:
     * - Docker is not available
     * - User prefers local installation
     * - Docker setup failed
     *
     * In non-interactive mode, uses defaults (localhost:9000, auto-generated credentials).
     *
     * Security considerations:
     * - Prompts user to generate new credentials or provide existing ones
     * - Auto-generated credentials use cryptographically secure random bytes
     * - Credentials must match those configured in the local MinIO server
     *
     * @param  string $appName Application name for default bucket naming
     * @return array  MinIO storage configuration array with connection details
     */
    private function setupLocalMinioStorage(string $appName): array
    {
        // Prompt for MinIO server endpoint/hostname
        // In non-interactive mode, automatically uses default
        $endpoint = $this->text('MinIO endpoint', default: 'localhost', required: true);

        // Prompt for MinIO API port
        // In non-interactive mode, automatically uses default
        $port = (int) $this->text('MinIO API port', default: '9000', required: true);

        // Prompt for Console port
        // In non-interactive mode, automatically uses default
        $consolePort = (int) $this->text('MinIO Console port', default: '9001', required: true);

        // Prompt for access key - offer to generate or let user provide their own
        // In non-interactive mode, defaults to true (generate new key)
        $accessKey = $this->confirm('Generate new access key?', true)
            ? bin2hex(random_bytes(10))  // Generate secure 20-char key
            : $this->text('Access key', required: true);  // Use user-provided key

        // Prompt for secret key - offer to generate or let user provide their own
        // In non-interactive mode, defaults to true (generate new key)
        $secretKey = $this->confirm('Generate new secret key?', true)
            ? bin2hex(random_bytes(20))  // Generate secure 40-char key
            : $this->text('Secret key', required: true);  // Use user-provided key

        // Sanitize app name for bucket naming
        $defaultBucket = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $appName) ?? $appName);

        // Prompt for bucket name
        // In non-interactive mode, automatically uses default
        $bucket = $this->text('Default bucket name', default: $defaultBucket, required: true);

        // Return configuration array
        return [
            'storage_driver' => StorageDriver::MINIO->value,
            'storage_endpoint' => $endpoint,
            'storage_port' => $port,
            'storage_access_key' => $accessKey,
            'storage_secret_key' => $secretKey,
            'storage_bucket' => $bucket,
            'storage_console_port' => $consolePort,
            AppTypeInterface::CONFIG_USING_DOCKER => false,
        ];
    }
}
