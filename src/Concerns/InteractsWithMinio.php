<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns;

use PhpHive\Cli\Support\Filesystem;
use PhpHive\Cli\Support\Process;
use RuntimeException;
use Symfony\Component\Process\Process as SymfonyProcess;

/**
 * Minio Interaction Trait.
 *
 * This trait provides comprehensive Minio (S3-compatible object storage) setup
 * functionality for application types that require object storage configuration.
 * It supports both Docker-based and local Minio setups with automatic configuration
 * and graceful fallbacks.
 *
 * Key features:
 * - Docker-first approach: Recommends Docker when available
 * - Automatic Minio container setup with Console UI
 * - Secure access key and secret key generation
 * - Automatic bucket creation
 * - Container health checking and readiness verification
 * - Local Minio fallback for non-Docker setups
 * - Secure credential handling with masked input
 * - Graceful error handling with fallback options
 * - Detailed user feedback using Laravel Prompts
 * - Reusable across multiple app types (Magento, Laravel, Symfony, etc.)
 *
 * Docker-first workflow:
 * 1. Check if Docker is available
 * 2. If yes, offer Docker Minio setup (recommended)
 * 3. Generate secure access keys and secret keys
 * 4. Generate docker-compose.yml section for Minio
 * 5. Start Docker containers
 * 6. Wait for Minio to be ready
 * 7. Create default bucket automatically
 * 8. Return connection details including Console URL
 * 9. If Docker unavailable or user declines, fall back to local setup
 *
 * Local Minio workflow:
 * 1. Ask user to provide existing Minio server details
 * 2. Prompt for endpoint, port, access key, secret key
 * 3. Prompt for bucket name
 * 4. Return credentials for application configuration
 * 5. If any step fails, fall back to manual prompts
 *
 * Example usage:
 * ```php
 * use PhpHive\Cli\Concerns\InteractsWithMinio;
 * use PhpHive\Cli\Concerns\InteractsWithDocker;
 *
 * class MyAppType extends AbstractAppType
 * {
 *     use InteractsWithMinio;
 *     use InteractsWithDocker;
 *
 *     public function collectConfiguration($input, $output): array
 *     {
 *         $this->input = $input;
 *         $this->output = $output;
 *
 *         // Orchestrate Minio setup (Docker-first)
 *         $minioConfig = $this->setupMinio('my-app', '/path/to/app');
 *
 *         return $minioConfig;
 *     }
 * }
 * ```
 *
 * Security considerations:
 * - Access keys are randomly generated (16 characters)
 * - Secret keys are randomly generated (32 characters)
 * - Credentials are masked during input
 * - Docker containers are isolated per project
 * - Minio Console provides secure web UI access
 * - Connection attempts are limited to prevent brute force
 *
 * Minio Console:
 * - Web-based UI for bucket management
 * - Default port: 9001
 * - Accessible at http://localhost:9001
 * - Login with generated access key and secret key
 *
 * @see AbstractAppType For base app type functionality
 * @see InteractsWithDocker For Docker management functionality
 * @see InteractsWithPrompts For prompt helper methods
 */
trait InteractsWithMinio
{
    /**
     * Get the Process service instance.
     *
     * This method provides access to the Process service for command execution.
     * It should be implemented by the class using this trait to return the
     * appropriate Process instance from the dependency injection container.
     *
     * @return Process The Process service instance
     */
    abstract protected function process(): Process;

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
     * Orchestrate Minio setup with Docker-first approach.
     *
     * This is the main entry point for Minio setup. It intelligently
     * chooses between Docker and local Minio based on availability and
     * user preference, with graceful fallbacks at each step.
     *
     * Decision flow:
     * 1. Check if Docker is available (requires InteractsWithDocker trait)
     * 2. If Docker available:
     *    - Offer Docker setup (recommended)
     *    - If user accepts → setupDockerMinio()
     *    - If user declines → setupLocalMinio()
     * 3. If Docker not available:
     *    - Show installation guidance (optional)
     *    - Fall back to setupLocalMinio()
     *
     * Return value structure:
     * ```php
     * [
     *     'minio_endpoint' => 'localhost',    // Minio server endpoint
     *     'minio_port' => 9000,               // Minio API port
     *     'minio_access_key' => 'minioadmin', // Access key
     *     'minio_secret_key' => 'minioadmin', // Secret key
     *     'minio_bucket' => 'my-app',         // Default bucket name
     *     'minio_console_port' => 9001,       // Console UI port
     *     'using_docker' => true,             // Whether Docker is used
     * ]
     * ```
     *
     * @param  string $appName Application name for defaults
     * @param  string $appPath Absolute path to application directory
     * @return array  Minio configuration array
     */
    protected function setupMinio(string $appName, string $appPath): array
    {
        // Check if Docker is available (requires InteractsWithDocker trait)
        if ($this->isDockerAvailable()) {
            // Docker is available - offer Docker setup
            $this->note(
                'Docker detected! Using Docker provides isolated Minio storage, easy management, and includes web console.',
                'Minio Setup'
            );

            $useDocker = $this->confirm(
                label: 'Would you like to use Docker for Minio? (recommended)',
                default: true
            );

            if ($useDocker) {
                $minioConfig = $this->setupDockerMinio($appName, $appPath);
                if ($minioConfig !== null) {
                    return $minioConfig;
                }

                // Docker setup failed, fall back to local
                $this->warning('Docker setup failed. Falling back to local Minio setup.');
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

        // Fall back to local Minio setup
        return $this->setupLocalMinio($appName);
    }

    /**
     * Set up Minio using Docker containers.
     *
     * Creates a Docker Compose configuration with Minio server and Console,
     * starts the containers, waits for readiness, and creates the default bucket.
     *
     * Process:
     * 1. Generate secure access key and secret key
     * 2. Prompt for bucket name
     * 3. Generate docker-compose.yml section for Minio
     * 4. Start Docker containers
     * 5. Wait for Minio to be ready
     * 6. Create default bucket using mc (Minio Client)
     * 7. Return connection details including Console URL
     *
     * Generated services:
     * - minio: Minio server (API on port 9000, Console on port 9001)
     *
     * Container naming:
     * - Format: phphive-{app-name}-minio
     * - Example: phphive-my-shop-minio
     *
     * Minio Console:
     * - Web UI for bucket management
     * - Accessible at http://localhost:9001
     * - Login with generated credentials
     *
     * @param  string     $appName Application name
     * @param  string     $appPath Application directory path
     * @return array|null Minio config on success, null on failure
     */
    protected function setupDockerMinio(string $appName, string $appPath): ?array
    {
        // Check if running in non-interactive mode
        if (! $this->input->isInteractive()) {
            return null;
        }

        // =====================================================================
        // CONFIGURATION
        // =====================================================================

        $this->info('Configuring Minio object storage...');

        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $appName) ?? $appName);

        // Generate secure credentials
        $accessKey = $this->generateMinioAccessKey();
        $secretKey = $this->generateMinioSecretKey();

        $this->info("Generated access key: {$accessKey}");
        $this->info('Generated secret key: ' . str_repeat('*', strlen($secretKey)));

        // Prompt for bucket name
        $bucketName = $this->text(
            label: 'Default bucket name',
            default: $normalizedName,
            required: true,
            hint: 'Bucket name must be lowercase, alphanumeric, and may contain hyphens'
        );

        // Validate bucket name
        $bucketName = $this->validateBucketName($bucketName);

        // =====================================================================
        // GENERATE DOCKER COMPOSE FILE
        // =====================================================================

        $this->info('Generating docker-compose.yml for Minio...');

        $composeGenerated = $this->generateMinioDockerComposeFile(
            $appPath,
            $appName,
            $accessKey,
            $secretKey
        );

        if (! $composeGenerated) {
            $this->error('Failed to generate docker-compose.yml');

            return null;
        }

        // =====================================================================
        // START CONTAINERS
        // =====================================================================

        $this->info('Starting Minio Docker container...');

        $started = $this->spin(
            callback: fn (): bool => $this->startDockerContainers($appPath),
            message: 'Starting Minio container...'
        );

        if (! $started) {
            $this->error('Failed to start Minio Docker container');

            return null;
        }

        // =====================================================================
        // WAIT FOR MINIO
        // =====================================================================

        $this->info('Waiting for Minio to be ready...');

        $ready = $this->spin(
            callback: fn (): bool => $this->waitForDockerService($appPath, 'minio', 30),
            message: 'Waiting for Minio...'
        );

        if (! $ready) {
            $this->warning('Minio may not be fully ready. You may need to wait a moment before using it.');
        } else {
            $this->info('✓ Minio is ready!');
        }

        // =====================================================================
        // CREATE DEFAULT BUCKET
        // =====================================================================

        $this->info('Creating default bucket...');

        $bucketCreated = $this->spin(
            callback: fn (): bool => $this->createMinioBucket(
                $appPath,
                $bucketName,
                $accessKey,
                $secretKey
            ),
            message: 'Creating bucket...'
        );

        if (! $bucketCreated) {
            $this->warning("Failed to create bucket '{$bucketName}'. You can create it manually via Console.");
        } else {
            $this->info("✓ Bucket '{$bucketName}' created successfully!");
        }

        // =====================================================================
        // RETURN CONFIGURATION
        // =====================================================================

        $this->info('✓ Docker Minio setup complete!');
        $this->info('Minio Console: http://localhost:9001');
        $this->info("Login with access key: {$accessKey}");

        return [
            'minio_endpoint' => 'localhost',
            'minio_port' => 9000,
            'minio_access_key' => $accessKey,
            'minio_secret_key' => $secretKey,
            'minio_bucket' => $bucketName,
            'minio_console_port' => 9001,
            'using_docker' => true,
        ];
    }

    /**
     * Generate docker-compose.yml file with Minio service.
     *
     * Creates or appends to docker-compose.yml with Minio server configuration.
     * Includes both API server and Console UI in a single container.
     *
     * Minio configuration:
     * - Image: minio/minio:latest
     * - API Port: 9000 (S3-compatible API)
     * - Console Port: 9001 (Web UI)
     * - Volume: Persistent data storage
     * - Health check: Ensures container is ready
     *
     * Environment variables:
     * - MINIO_ROOT_USER: Access key for authentication
     * - MINIO_ROOT_PASSWORD: Secret key for authentication
     *
     * @param  string $appPath   Application directory path
     * @param  string $appName   Application name
     * @param  string $accessKey Minio access key
     * @param  string $secretKey Minio secret key
     * @return bool   True on success, false on failure
     */
    protected function generateMinioDockerComposeFile(
        string $appPath,
        string $appName,
        string $accessKey,
        string $secretKey
    ): bool {
        // Get template path
        $templatePath = dirname(__DIR__, 2) . '/stubs/docker/minio.yml';

        if (! $this->filesystem()->exists($templatePath)) {
            // If template doesn't exist, create inline
            $template = $this->getMinioDockerComposeTemplate();
        } else {
            // Read template using Filesystem
            try {
                $template = $this->filesystem()->read($templatePath);
            } catch (RuntimeException) {
                return false;
            }
        }

        // Normalize app name for container/volume names
        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $appName) ?? $appName);

        // Replace placeholders
        $replacements = [
            '{{CONTAINER_PREFIX}}' => "phphive-{$normalizedName}",
            '{{VOLUME_PREFIX}}' => "phphive-{$normalizedName}",
            '{{NETWORK_NAME}}' => "phphive-{$normalizedName}",
            '{{MINIO_ACCESS_KEY}}' => $accessKey,
            '{{MINIO_SECRET_KEY}}' => $secretKey,
            '{{MINIO_PORT}}' => '9000',
            '{{MINIO_CONSOLE_PORT}}' => '9001',
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Write docker-compose.yml
        $outputPath = $appPath . '/docker-compose.yml';

        // Check if docker-compose.yml already exists
        if ($this->filesystem()->exists($outputPath)) {
            // Append to existing file using Filesystem
            try {
                $existingContent = $this->filesystem()->read($outputPath);
            } catch (RuntimeException) {
                return false;
            }

            // Check if Minio service already exists
            if (str_contains($existingContent, 'minio:')) {
                $this->warning('Minio service already exists in docker-compose.yml');

                return true;
            }

            // Append Minio service
            $content = $existingContent . "\n" . $content;
        }

        // Write docker-compose.yml using Filesystem
        try {
            $this->filesystem()->write($outputPath, $content);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Get inline Minio docker-compose template.
     *
     * Returns a docker-compose.yml template for Minio when the stub file
     * is not available. This ensures the trait works even without external
     * template files.
     *
     * Template includes:
     * - Minio server service
     * - API and Console ports
     * - Persistent volume
     * - Health check
     * - Environment variables
     *
     * @return string Docker Compose YAML template
     */
    protected function getMinioDockerComposeTemplate(): string
    {
        return <<<'YAML'
  # Minio Object Storage Service
  minio:
    image: minio/minio:latest
    container_name: {{CONTAINER_PREFIX}}-minio
    ports:
      - "{{MINIO_PORT}}:9000"      # API port
      - "{{MINIO_CONSOLE_PORT}}:9001"  # Console port
    environment:
      MINIO_ROOT_USER: {{MINIO_ACCESS_KEY}}
      MINIO_ROOT_PASSWORD: {{MINIO_SECRET_KEY}}
    command: server /data --console-address ":9001"
    volumes:
      - {{VOLUME_PREFIX}}-minio-data:/data
    networks:
      - {{NETWORK_NAME}}
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:9000/minio/health/live"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  {{VOLUME_PREFIX}}-minio-data:
    driver: local

networks:
  {{NETWORK_NAME}}:
    driver: bridge

YAML;
    }

    /**
     * Create a bucket in Minio using Docker exec.
     *
     * Executes Minio Client (mc) commands inside the Minio container to:
     * 1. Configure mc alias for the local Minio server
     * 2. Create the specified bucket
     *
     * Process:
     * 1. Configure mc alias: mc alias set local http://localhost:9000 {access_key} {secret_key}
     * 2. Create bucket: mc mb local/{bucket_name}
     * 3. Verify bucket creation
     *
     * Note: This method assumes the Minio container is running and healthy.
     * Call waitForDockerService() before this method to ensure readiness.
     *
     * @param  string $appPath   Application directory path
     * @param  string $bucket    Bucket name to create
     * @param  string $accessKey Minio access key
     * @param  string $secretKey Minio secret key
     * @return bool   True if bucket created successfully, false otherwise
     */
    protected function createMinioBucket(
        string $appPath,
        string $bucket,
        string $accessKey,
        string $secretKey
    ): bool {
        // Configure mc alias
        $aliasProcess = new SymfonyProcess([
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
        $bucketProcess = new SymfonyProcess([
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
     * Set up Minio using local installation.
     *
     * Falls back to local Minio setup when Docker is not available
     * or user prefers local installation. Prompts for existing Minio
     * server connection details.
     *
     * Process:
     * 1. Display informational note about local setup
     * 2. Check if user wants to configure manually or use defaults
     * 3. Verify Minio connection if user confirms it's running
     * 4. Prompt for connection details or provide installation guidance
     * 5. Return configuration for application
     *
     * Connection verification:
     * - Tests Minio connection using curl to /minio/health/live endpoint
     * - If successful: Continue with setup
     * - If failed: Offer Docker alternative or show installation instructions
     *
     * Note: This method assumes Minio is already installed and running
     * locally. It does not install or start Minio.
     *
     * @param  string $appName Application name
     * @return array  Minio configuration array
     */
    protected function setupLocalMinio(string $appName): array
    {
        $this->note(
            'Setting up local Minio. Ensure Minio is installed and running.',
            'Local Minio Setup'
        );

        // Check if user wants automatic configuration
        $hasLocal = $this->confirm(
            label: 'Is Minio already running locally?',
            default: false
        );

        if ($hasLocal) {
            // Verify Minio is actually running
            $isRunning = $this->spin(
                callback: fn (): bool => $this->checkMinioConnection('localhost', 9000),
                message: 'Checking Minio connection...'
            );

            if ($isRunning) {
                $this->info('✓ Minio is running and accessible!');
                // Use default local configuration
                $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $appName) ?? $appName);

                return [
                    'minio_endpoint' => 'localhost',
                    'minio_port' => 9000,
                    'minio_access_key' => 'minioadmin',
                    'minio_secret_key' => 'minioadmin',
                    'minio_bucket' => $normalizedName,
                    'minio_console_port' => 9001,
                    'using_docker' => false,
                ];
            }

            // Minio check failed
            $this->error('✗ Could not connect to Minio on localhost:9000');
            $this->warning('Minio does not appear to be running.');

            // Offer to try Docker if available
            if ($this->isDockerAvailable()) {
                $tryDocker = $this->confirm(
                    label: 'Would you like to use Docker for Minio instead?',
                    default: true
                );

                if ($tryDocker) {
                    $cwd = getcwd();
                    if ($cwd === false) {
                        $this->error('Could not determine current working directory');
                        exit(1);
                    }
                    $dockerConfig = $this->setupDockerMinio($appName, $cwd);
                    if ($dockerConfig !== null) {
                        return $dockerConfig;
                    }
                }
            }

            // Show installation guidance
            $this->provideMinioInstallationGuidance();
            $this->error('Please install and start Minio, then try again.');
            exit(1);
        }

        // User said Minio is not running - provide installation guidance
        $this->info('Download Minio from: https://min.io/download');
        $this->info('Start Minio with: minio server /data --console-address ":9001"');
        $this->provideMinioInstallationGuidance();
        $this->info('After installing and starting Minio, please configure the connection details.');

        // Prompt for manual configuration
        return $this->promptMinioConfiguration($appName);
    }

    /**
     * Check if Minio is accessible at the given host and port.
     *
     * Attempts to connect to Minio and execute a health check to verify
     * the connection is working. This is a quick health check to ensure
     * Minio is running and accessible.
     *
     * @param  string $host Minio host
     * @param  int    $port Minio port
     * @return bool   True if Minio is accessible, false otherwise
     */
    protected function checkMinioConnection(string $host, int $port): bool
    {
        try {
            // Try to connect using curl to health endpoint
            $result = $this->process()->succeeds(['curl', '-f', '-s', "http://{$host}:{$port}/minio/health/live"]);

            return $result;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Provide Minio installation guidance based on operating system.
     *
     * Displays helpful information and instructions for installing Minio
     * on the user's operating system. Includes download links, installation
     * methods, and verification steps.
     */
    protected function provideMinioInstallationGuidance(): void
    {
        $os = $this->detectOS();

        $this->note(
            'Minio is not running. Minio provides S3-compatible object storage.',
            'Minio Not Available'
        );

        match ($os) {
            'macos' => $this->provideMacOSMinioGuidance(),
            'linux' => $this->provideLinuxMinioGuidance(),
            'windows' => $this->provideWindowsMinioGuidance(),
            default => $this->provideGenericMinioGuidance(),
        };
    }

    /**
     * Provide macOS-specific Minio installation guidance.
     */
    protected function provideMacOSMinioGuidance(): void
    {
        $this->info('macOS Installation:');
        $this->info('');
        $this->info('Homebrew (Recommended):');
        $this->info('  brew install minio/stable/minio');
        $this->info('  minio server /data --console-address ":9001"');
        $this->info('');
        $this->info('After installation:');
        $this->info('  1. Minio will start on port 9000');
        $this->info('  2. Console UI on port 9001');
        $this->info('  3. Verify with: curl http://localhost:9000/minio/health/live');
        $this->info('  4. Documentation: https://min.io/docs');
    }

    /**
     * Provide Linux-specific Minio installation guidance.
     */
    protected function provideLinuxMinioGuidance(): void
    {
        $this->info('Linux Installation:');
        $this->info('');
        $this->info('Download and Install:');
        $this->info('  wget https://dl.min.io/server/minio/release/linux-amd64/minio');
        $this->info('  chmod +x minio');
        $this->info('  ./minio server /data --console-address ":9001"');
        $this->info('');
        $this->info('After installation:');
        $this->info('  1. Minio will start on port 9000');
        $this->info('  2. Console UI on port 9001');
        $this->info('  3. Verify with: curl http://localhost:9000/minio/health/live');
        $this->info('  4. Documentation: https://min.io/docs');
    }

    /**
     * Provide Windows-specific Minio installation guidance.
     */
    protected function provideWindowsMinioGuidance(): void
    {
        $this->info('Windows Installation:');
        $this->info('');
        $this->info('Download and Install:');
        $this->info('  1. Download from: https://dl.min.io/server/minio/release/windows-amd64/minio.exe');
        $this->info('  2. Run: minio.exe server C:\\data --console-address ":9001"');
        $this->info('');
        $this->info('After installation:');
        $this->info('  1. Minio will start on port 9000');
        $this->info('  2. Console UI on port 9001');
        $this->info('  3. Verify with: curl http://localhost:9000/minio/health/live');
        $this->info('  4. Documentation: https://min.io/docs');
    }

    /**
     * Provide generic Minio installation guidance.
     */
    protected function provideGenericMinioGuidance(): void
    {
        $this->info('Minio Installation:');
        $this->info('');
        $this->info('Visit the official Minio documentation:');
        $this->info('  https://min.io/docs/minio/linux/operations/installation.html');
        $this->info('');
        $this->info('After installation, verify with:');
        $this->info('  curl http://localhost:9000/minio/health/live');
    }

    /**
     * Prompt user for manual Minio configuration.
     *
     * This method provides a fallback when automatic Minio setup fails or
     * is not desired. It prompts the user to manually enter Minio connection
     * details for an existing Minio server.
     *
     * Use cases:
     * - User prefers to use existing Minio server
     * - Automatic setup failed (connection issues, Docker errors)
     * - Using remote Minio server
     * - Using managed S3-compatible service (AWS S3, DigitalOcean Spaces, etc.)
     *
     * Interactive prompts:
     * 1. Minio endpoint (default: localhost)
     * 2. Minio port (default: 9000)
     * 3. Access key (default: minioadmin)
     * 4. Secret key (masked input, default: minioadmin)
     * 5. Bucket name (default: normalized app name)
     * 6. Console port (default: 9001)
     *
     * Return value structure:
     * ```php
     * [
     *     'minio_endpoint' => 'localhost',
     *     'minio_port' => 9000,
     *     'minio_access_key' => 'minioadmin',
     *     'minio_secret_key' => 'minioadmin',
     *     'minio_bucket' => 'my-app',
     *     'minio_console_port' => 9001,
     *     'using_docker' => false,
     * ]
     * ```
     *
     * Non-interactive mode:
     * - Returns defaults for all values
     * - Endpoint: localhost, Port: 9000
     * - Access key: minioadmin, Secret key: minioadmin
     * - Bucket: normalized app name
     *
     * Note: This method does NOT validate the Minio connection.
     * The application will fail if credentials are incorrect.
     *
     * @param  string $appName Application name used for default bucket name
     * @return array  Minio configuration array with user-provided values
     */
    protected function promptMinioConfiguration(string $appName): array
    {
        // Normalize app name for bucket naming (lowercase, hyphens)
        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $appName) ?? $appName);

        // Check if running in non-interactive mode
        if (! $this->input->isInteractive()) {
            // Return defaults for non-interactive mode
            return [
                'minio_endpoint' => 'localhost',
                'minio_port' => 9000,
                'minio_access_key' => 'minioadmin',
                'minio_secret_key' => 'minioadmin',
                'minio_bucket' => $normalizedName,
                'minio_console_port' => 9001,
                'using_docker' => false,
            ];
        }

        // Display informational note about manual setup
        $this->note(
            'Please enter the connection details for your Minio server.',
            'Manual Minio Configuration'
        );

        // =====================================================================
        // MINIO CONNECTION DETAILS
        // =====================================================================

        // Prompt for Minio endpoint
        $endpoint = $this->text(
            label: 'Minio endpoint',
            placeholder: 'localhost',
            default: 'localhost',
            required: true,
            hint: 'The Minio server hostname or IP address'
        );

        // Prompt for Minio port
        $portInput = $this->text(
            label: 'Minio API port',
            placeholder: '9000',
            default: '9000',
            required: true,
            hint: 'The Minio API port number'
        );
        $port = (int) $portInput;

        // Prompt for access key
        $accessKey = $this->text(
            label: 'Minio access key',
            placeholder: 'minioadmin',
            default: 'minioadmin',
            required: true,
            hint: 'The Minio root user or access key'
        );

        // Prompt for secret key
        $secretKey = $this->password(
            label: 'Minio secret key',
            placeholder: 'Enter secret key',
            required: true,
            hint: 'The Minio root password or secret key'
        );

        // Prompt for bucket name
        $bucketName = $this->text(
            label: 'Default bucket name',
            placeholder: $normalizedName,
            default: $normalizedName,
            required: true,
            hint: 'Bucket name must be lowercase, alphanumeric, and may contain hyphens'
        );

        // Validate bucket name
        $bucketName = $this->validateBucketName($bucketName);

        // Prompt for console port
        $consolePortInput = $this->text(
            label: 'Minio Console port',
            placeholder: '9001',
            default: '9001',
            required: true,
            hint: 'The Minio Console UI port number'
        );
        $consolePort = (int) $consolePortInput;

        // Return Minio configuration
        return [
            'minio_endpoint' => $endpoint,
            'minio_port' => $port,
            'minio_access_key' => $accessKey,
            'minio_secret_key' => $secretKey,
            'minio_bucket' => $bucketName,
            'minio_console_port' => $consolePort,
            'using_docker' => false,
        ];
    }

    /**
     * Generate a secure random access key for Minio.
     *
     * Creates a 16-character alphanumeric access key suitable for Minio
     * authentication. The key is randomly generated using cryptographically
     * secure random bytes.
     *
     * Key characteristics:
     * - Length: 16 characters
     * - Character set: Uppercase letters and numbers
     * - Cryptographically secure random generation
     * - Suitable for production use
     *
     * Example output: "A1B2C3D4E5F6G7H8"
     *
     * @return string Generated access key
     */
    protected function generateMinioAccessKey(): string
    {
        // Generate 16 random bytes and convert to uppercase alphanumeric
        $bytes = random_bytes(12);
        $key = strtoupper(bin2hex($bytes));

        // Return first 16 characters
        return substr($key, 0, 16);
    }

    /**
     * Generate a secure random secret key for Minio.
     *
     * Creates a 32-character alphanumeric secret key suitable for Minio
     * authentication. The key is randomly generated using cryptographically
     * secure random bytes.
     *
     * Key characteristics:
     * - Length: 32 characters
     * - Character set: Lowercase letters and numbers
     * - Cryptographically secure random generation
     * - Suitable for production use
     *
     * Example output: "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
     *
     * @return string Generated secret key
     */
    protected function generateMinioSecretKey(): string
    {
        // Generate 32 random bytes and convert to lowercase alphanumeric
        return bin2hex(random_bytes(16));
    }

    /**
     * Validate and normalize bucket name.
     *
     * Ensures bucket name follows S3/Minio naming conventions:
     * - Lowercase only
     * - Alphanumeric characters and hyphens
     * - No underscores, spaces, or special characters
     * - Length between 3 and 63 characters
     *
     * Normalization process:
     * 1. Convert to lowercase
     * 2. Replace invalid characters with hyphens
     * 3. Remove consecutive hyphens
     * 4. Trim hyphens from start and end
     * 5. Ensure minimum length of 3 characters
     *
     * @param  string $bucketName Input bucket name
     * @return string Validated and normalized bucket name
     */
    protected function validateBucketName(string $bucketName): string
    {
        // Convert to lowercase
        $bucketName = strtolower($bucketName);

        // Replace invalid characters with hyphens
        $bucketName = preg_replace('/[^a-z0-9-]/', '-', $bucketName) ?? $bucketName;

        // Remove consecutive hyphens
        $bucketName = preg_replace('/-+/', '-', $bucketName) ?? $bucketName;

        // Trim hyphens from start and end
        $bucketName = trim($bucketName, '-');

        // Ensure minimum length
        if (strlen($bucketName) < 3) {
            $bucketName = 'bucket-' . $bucketName;
        }

        // Ensure maximum length
        if (strlen($bucketName) > 63) {
            $bucketName = substr($bucketName, 0, 63);
            $bucketName = rtrim($bucketName, '-');
        }

        return $bucketName;
    }
}
