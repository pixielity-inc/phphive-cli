<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns;

use PhpHive\Cli\Support\Filesystem;
use PhpHive\Cli\Support\Process;
use RuntimeException;
use Symfony\Component\Process\Process as SymfonyProcess;

/**
 * Meilisearch Interaction Trait.
 *
 * This trait provides comprehensive Meilisearch setup functionality for application
 * types that require search engine configuration. It supports both Docker-based and
 * local Meilisearch setups with automatic configuration and graceful fallbacks.
 *
 * Key features:
 * - Docker-first approach: Recommends Docker when available
 * - Automatic Docker Compose integration
 * - Container management and health checking
 * - Secure master key generation
 * - Local Meilisearch fallback for non-Docker setups
 * - Graceful error handling with fallback options
 * - Detailed user feedback using Laravel Prompts
 * - Reusable across multiple app types (Magento, Laravel, Symfony, etc.)
 *
 * Docker-first workflow:
 * 1. Check if Docker is available
 * 2. If yes, offer Docker Meilisearch setup (recommended)
 * 3. Generate secure master key
 * 4. Generate docker-compose section for Meilisearch
 * 5. Start Docker container
 * 6. Wait for Meilisearch to be ready (health check)
 * 7. Return connection details with master key
 * 8. If Docker unavailable or user declines, fall back to local setup
 *
 * Local Meilisearch workflow:
 * 1. Assume Meilisearch is installed and running locally
 * 2. Prompt for Meilisearch host and port
 * 3. Prompt for master key (or generate one)
 * 4. Return configuration for application
 * 5. Provide installation guidance if needed
 *
 * Example usage:
 * ```php
 * use PhpHive\Cli\Concerns\InteractsWithMeilisearch;
 * use PhpHive\Cli\Concerns\InteractsWithDocker;
 *
 * class MyAppType extends AbstractAppType
 * {
 *     use InteractsWithMeilisearch;
 *     use InteractsWithDocker;
 *
 *     public function collectConfiguration($input, $output): array
 *     {
 *         $this->input = $input;
 *         $this->output = $output;
 *
 *         // Orchestrate Meilisearch setup (Docker-first)
 *         $searchConfig = $this->setupMeilisearch('my-app', '/path/to/app');
 *
 *         return $searchConfig;
 *     }
 * }
 * ```
 *
 * Security considerations:
 * - Master keys are generated using cryptographically secure random bytes
 * - Keys are 32 characters long (hex-encoded 16 bytes)
 * - Docker containers are isolated per project
 * - Connection attempts include health checks
 * - Master key is required for all Meilisearch operations
 *
 * @phpstan-ignore-next-line trait.unused
 *
 * @see AbstractAppType For base app type functionality
 * @see InteractsWithDocker For Docker management functionality
 * @see InteractsWithPrompts For prompt helper methods
 */
trait InteractsWithMeilisearch
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
     * Orchestrate Meilisearch setup with Docker-first approach.
     *
     * This is the main entry point for Meilisearch setup. It intelligently
     * chooses between Docker and local Meilisearch based on availability and
     * user preference, with graceful fallbacks at each step.
     *
     * Decision flow:
     * 1. Check if Docker is available (requires InteractsWithDocker trait)
     * 2. If Docker available:
     *    - Offer Docker setup (recommended)
     *    - If user accepts → setupDockerMeilisearch()
     *    - If user declines → setupLocalMeilisearch()
     * 3. If Docker not available:
     *    - Show installation guidance (optional)
     *    - Fall back to setupLocalMeilisearch()
     *
     * Meilisearch features:
     * - Lightning-fast full-text search
     * - Typo-tolerant search
     * - Faceted search and filtering
     * - RESTful API
     * - Instant search results
     *
     * Return value structure:
     * ```php
     * [
     *     'meilisearch_host' => 'http://localhost',  // Host URL
     *     'meilisearch_port' => 7700,                // Port
     *     'meilisearch_master_key' => 'key...',      // Master key
     *     'using_docker' => true,                    // Whether Docker is used
     * ]
     * ```
     *
     * @param  string $appName Application name for defaults
     * @param  string $appPath Absolute path to application directory
     * @return array  Meilisearch configuration array
     */
    protected function setupMeilisearch(string $appName, string $appPath): array
    {
        // Check if Docker is available (requires InteractsWithDocker trait)
        if ($this->isDockerAvailable()) {
            // Docker is available - offer Docker setup
            $this->note(
                'Docker detected! Using Docker provides isolated Meilisearch instances, easy management, and no local installation needed.',
                'Meilisearch Setup'
            );

            $useDocker = $this->confirm(
                label: 'Would you like to use Docker for Meilisearch? (recommended)',
                default: true
            );

            if ($useDocker) {
                $searchConfig = $this->setupDockerMeilisearch($appName, $appPath);
                if ($searchConfig !== null) {
                    return $searchConfig;
                }

                // Docker setup failed, fall back to local
                $this->warning('Docker setup failed. Falling back to local Meilisearch setup.');
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

        // Fall back to local Meilisearch setup
        return $this->setupLocalMeilisearch($appName);
    }

    /**
     * Set up Meilisearch using Docker container.
     *
     * Creates a Docker Compose configuration with Meilisearch service
     * and starts the container. Includes health checking to ensure
     * Meilisearch is ready before returning.
     *
     * Process:
     * 1. Generate secure master key
     * 2. Prompt for port configuration (default: 7700)
     * 3. Generate docker-compose.yml section for Meilisearch
     * 4. Start Docker container
     * 5. Wait for Meilisearch to be ready (health check)
     * 6. Return connection details with master key
     *
     * Generated configuration:
     * - Service name: meilisearch
     * - Image: getmeili/meilisearch:latest
     * - Port: 7700 (default, configurable)
     * - Volume: Persistent data storage
     * - Environment: MEILI_MASTER_KEY
     * - Health check: HTTP endpoint polling
     *
     * Container naming:
     * - Format: phphive-{app-name}-meilisearch
     * - Example: phphive-my-shop-meilisearch
     *
     * @param  string     $appName Application name
     * @param  string     $appPath Application directory path
     * @return array|null Meilisearch config on success, null on failure
     */
    protected function setupDockerMeilisearch(string $appName, string $appPath): ?array
    {
        // Check if running in non-interactive mode
        if (! $this->input->isInteractive()) {
            return null;
        }

        // =====================================================================
        // CONFIGURATION
        // =====================================================================

        $this->info('Configuring Meilisearch...');

        // Generate secure master key (32 characters hex)
        $masterKey = bin2hex(random_bytes(16));

        // Prompt for port configuration
        $portInput = $this->text(
            label: 'Meilisearch port',
            placeholder: '7700',
            default: '7700',
            required: true,
            hint: 'Port for Meilisearch HTTP API'
        );
        $port = (int) $portInput;

        // =====================================================================
        // GENERATE DOCKER COMPOSE CONFIGURATION
        // =====================================================================

        $this->info('Generating docker-compose.yml configuration...');

        $composeGenerated = $this->generateMeilisearchDockerCompose(
            $appPath,
            $appName,
            $port,
            $masterKey
        );

        if (! $composeGenerated) {
            $this->error('Failed to generate docker-compose.yml configuration');

            return null;
        }

        // =====================================================================
        // START CONTAINER
        // =====================================================================

        $this->info('Starting Meilisearch container...');

        $started = $this->spin(
            callback: fn (): bool => $this->startDockerContainers($appPath),
            message: 'Starting Meilisearch container...'
        );

        if (! $started) {
            $this->error('Failed to start Meilisearch container');

            return null;
        }

        // =====================================================================
        // WAIT FOR MEILISEARCH TO BE READY
        // =====================================================================

        $this->info('Waiting for Meilisearch to be ready...');

        $ready = $this->spin(
            callback: fn (): bool => $this->waitForMeilisearch('http://localhost', $port, $masterKey, 30),
            message: 'Checking Meilisearch health...'
        );

        if (! $ready) {
            $this->warning('Meilisearch may not be fully ready. You may need to wait a moment before using it.');
        } else {
            $this->info('✓ Meilisearch is ready!');
        }

        // =====================================================================
        // RETURN CONFIGURATION
        // =====================================================================

        $this->info('✓ Docker Meilisearch setup complete!');
        $this->info("Meilisearch dashboard: http://localhost:{$port}");
        $this->info("Master key: {$masterKey}");

        return [
            'meilisearch_host' => 'http://localhost',
            'meilisearch_port' => $port,
            'meilisearch_master_key' => $masterKey,
            'using_docker' => true,
        ];
    }

    /**
     * Generate docker-compose.yml configuration for Meilisearch.
     *
     * Creates or updates the docker-compose.yml file in the application
     * directory with Meilisearch service configuration. If the file already
     * exists, it appends the Meilisearch service to it.
     *
     * Service configuration:
     * - Image: getmeili/meilisearch:latest
     * - Container name: phphive-{app-name}-meilisearch
     * - Port mapping: {port}:7700
     * - Volume: phphive-{app-name}-meilisearch-data:/meili_data
     * - Environment: MEILI_MASTER_KEY, MEILI_ENV=development
     * - Restart policy: unless-stopped
     * - Health check: curl http://localhost:7700/health
     *
     * @param  string $appPath   Application directory path
     * @param  string $appName   Application name
     * @param  int    $port      Meilisearch port
     * @param  string $masterKey Meilisearch master key
     * @return bool   True on success, false on failure
     */
    protected function generateMeilisearchDockerCompose(
        string $appPath,
        string $appName,
        int $port,
        string $masterKey
    ): bool {
        // Normalize app name for container/volume names
        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $appName) ?? $appName);

        // Meilisearch service configuration
        $meilisearchService = <<<YAML

  # Meilisearch Service
  meilisearch:
    image: getmeili/meilisearch:latest
    container_name: phphive-{$normalizedName}-meilisearch
    ports:
      - "{$port}:7700"
    volumes:
      - phphive-{$normalizedName}-meilisearch-data:/meili_data
    environment:
      MEILI_MASTER_KEY: {$masterKey}
      MEILI_ENV: development
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:7700/health"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - phphive-{$normalizedName}

YAML;

        // Meilisearch volume configuration
        $meilisearchVolume = "  phphive-{$normalizedName}-meilisearch-data:\n    driver: local\n";

        // Path to docker-compose.yml
        $composePath = $appPath . '/docker-compose.yml';

        // Check if docker-compose.yml exists
        if ($this->filesystem()->exists($composePath)) {
            // Read existing content using Filesystem
            try {
                $existingContent = $this->filesystem()->read($composePath);
            } catch (RuntimeException) {
                return false;
            }

            // Check if Meilisearch service already exists
            if (str_contains($existingContent, 'meilisearch:')) {
                $this->warning('Meilisearch service already exists in docker-compose.yml');

                return true;
            }

            // Append Meilisearch service to services section
            // Find the position to insert (before volumes section or at end)
            if (preg_match('/^volumes:/m', $existingContent) === 1) {
                // Insert before volumes section
                $existingContent = preg_replace(
                    '/^(volumes:)/m',
                    $meilisearchService . "\n$1",
                    $existingContent
                ) ?? $existingContent;

                // Append volume to volumes section
                $existingContent = preg_replace(
                    '/^volumes:\n/m',
                    "volumes:\n" . $meilisearchVolume,
                    $existingContent
                ) ?? $existingContent;
            } else {
                // Append at the end
                $existingContent .= $meilisearchService;
                $existingContent .= "\nvolumes:\n" . $meilisearchVolume;
            }

            // Write updated content using Filesystem
            try {
                $this->filesystem()->write($composePath, $existingContent);

                return true;
            } catch (RuntimeException) {
                return false;
            }
        }

        // Create new docker-compose.yml with Meilisearch service
        $composeContent = <<<YAML
version: '3.8'

services:
{$meilisearchService}

volumes:
{$meilisearchVolume}

networks:
  phphive-{$normalizedName}:
    driver: bridge

YAML;

        // Write docker-compose.yml using Filesystem
        try {
            $this->filesystem()->write($composePath, $composeContent);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Wait for Meilisearch to be ready and healthy.
     *
     * Polls the Meilisearch health endpoint until it responds successfully
     * or the maximum number of attempts is reached. This ensures Meilisearch
     * is fully started and ready to accept requests before proceeding.
     *
     * Health check process:
     * 1. Send GET request to /health endpoint
     * 2. Check if response status is 200 OK
     * 3. Verify response contains "available": true
     * 4. If not ready, wait 2 seconds and retry
     * 5. Repeat until ready or max attempts reached
     *
     * Polling strategy:
     * - Maximum attempts: 30 (configurable)
     * - Delay between attempts: 2 seconds
     * - Total maximum wait: 60 seconds
     *
     * Health endpoint response:
     * ```json
     * {
     *   "status": "available"
     * }
     * ```
     *
     * @param  string $host        Meilisearch host URL (e.g., 'http://localhost')
     * @param  int    $port        Meilisearch port (default: 7700)
     * @param  string $masterKey   Meilisearch master key for authentication
     * @param  int    $maxAttempts Maximum number of polling attempts
     * @return bool   True if Meilisearch is ready, false if timeout
     */
    protected function waitForMeilisearch(
        string $host,
        int $port,
        string $masterKey,
        int $maxAttempts = 30
    ): bool {
        $attempts = 0;
        $healthUrl = "{$host}:{$port}/health";

        while ($attempts < $maxAttempts) {
            // Create process to check health endpoint
            $process = new SymfonyProcess([
                'curl',
                '-f',
                '-s',
                '-H',
                "Authorization: Bearer {$masterKey}",
                $healthUrl,
            ]);

            $process->run();

            // Check if health check succeeded
            if ($process->isSuccessful()) {
                $output = $process->getOutput();

                // Verify response contains "available"
                if (str_contains($output, 'available')) {
                    return true;
                }
            }

            // Wait 2 seconds before next attempt
            sleep(2);
            $attempts++;
        }

        return false;
    }

    /**
     * Set up Meilisearch using local installation.
     *
     * Falls back to local Meilisearch setup when Docker is not available
     * or user prefers local installation. Prompts for connection details
     * and master key configuration.
     *
     * Process:
     * 1. Display informational note about local setup
     * 2. Check if user wants to configure manually or use defaults
     * 3. Verify Meilisearch connection if user confirms it's running
     * 4. Prompt for connection details or provide installation guidance
     * 5. Return configuration array
     *
     * Connection verification:
     * - Tests Meilisearch connection using curl to /health endpoint
     * - If successful: Continue with setup
     * - If failed: Offer Docker alternative or show installation instructions
     *
     * Local installation requirements:
     * - Meilisearch must be installed and running
     * - Default port: 7700
     * - Master key must be configured
     *
     * Installation guidance:
     * - macOS: brew install meilisearch
     * - Linux: curl -L https://install.meilisearch.com | sh
     * - Windows: Download from GitHub releases
     *
     * @param  string $appName Application name
     * @return array  Meilisearch configuration array
     */
    protected function setupLocalMeilisearch(string $appName): array
    {
        $this->note(
            'Setting up local Meilisearch. Ensure Meilisearch is installed and running.',
            'Local Meilisearch Setup'
        );

        // Check if user wants automatic configuration
        $autoConfig = $this->confirm(
            label: 'Is Meilisearch already running locally?',
            default: false
        );

        if ($autoConfig) {
            // Verify Meilisearch is actually running
            $isRunning = $this->spin(
                callback: fn (): bool => $this->checkMeilisearchConnection('http://localhost', 7700),
                message: 'Checking Meilisearch connection...'
            );

            if ($isRunning) {
                $this->info('✓ Meilisearch is running and accessible!');
                // Generate a master key for the user
                $masterKey = bin2hex(random_bytes(16));
                $this->info("Generated master key: {$masterKey}");
                $this->info('Please configure Meilisearch with this master key:');
                $this->info("  meilisearch --master-key=\"{$masterKey}\"");

                // Use default local configuration
                return [
                    'meilisearch_host' => 'http://localhost',
                    'meilisearch_port' => 7700,
                    'meilisearch_master_key' => $masterKey,
                    'using_docker' => false,
                ];
            }

            // Meilisearch check failed
            $this->error('✗ Could not connect to Meilisearch on http://localhost:7700');
            $this->warning('Meilisearch does not appear to be running.');

            // Offer to try Docker if available
            if ($this->isDockerAvailable()) {
                $tryDocker = $this->confirm(
                    label: 'Would you like to use Docker for Meilisearch instead?',
                    default: true
                );

                if ($tryDocker) {
                    $cwd = getcwd();
                    if ($cwd === false) {
                        $this->error('Could not determine current working directory');
                        exit(1);
                    }
                    $dockerConfig = $this->setupDockerMeilisearch($appName, $cwd);
                    if ($dockerConfig !== null) {
                        return $dockerConfig;
                    }
                }
            }

            // Show installation guidance
            $this->provideMeilisearchInstallationGuidance();
            $this->error('Please install and start Meilisearch, then try again.');
            exit(1);
        }

        // User said Meilisearch is not running - provide installation guidance
        $this->provideMeilisearchInstallationGuidance();
        $this->info('After installing and starting Meilisearch, please configure the connection details.');

        // Prompt for manual configuration
        return $this->promptMeilisearchConfiguration($appName);
    }

    /**
     * Check if Meilisearch is accessible at the given host and port.
     *
     * Attempts to connect to Meilisearch and execute a health check to verify
     * the connection is working. This is a quick health check to ensure
     * Meilisearch is running and accessible.
     *
     * @param  string $host Meilisearch host
     * @param  int    $port Meilisearch port
     * @return bool   True if Meilisearch is accessible, false otherwise
     */
    protected function checkMeilisearchConnection(string $host, int $port): bool
    {
        try {
            // Try to connect using curl to health endpoint
            $result = $this->process()->succeeds(['curl', '-f', '-s', "{$host}:{$port}/health"]);

            return $result;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Provide Meilisearch installation guidance based on operating system.
     *
     * Displays helpful information and instructions for installing Meilisearch
     * on the user's operating system. Includes download links, installation
     * methods, and verification steps.
     *
     * Installation guidance by OS:
     *
     * macOS:
     * - Homebrew installation (recommended)
     * - Direct binary download
     * - Verification and startup commands
     *
     * Linux:
     * - Installation script (recommended)
     * - Package manager options
     * - Systemd service setup
     *
     * Windows:
     * - Direct binary download
     * - PowerShell installation
     * - Service setup
     *
     * Display format:
     * - Uses Laravel Prompts $this->info() for visibility
     * - Includes clickable links
     * - Step-by-step instructions
     * - Startup and verification commands
     */
    protected function provideMeilisearchInstallationGuidance(): void
    {
        $os = $this->detectOS();

        $this->note(
            'Meilisearch is not running. Meilisearch provides lightning-fast search for your applications.',
            'Meilisearch Not Available'
        );

        match ($os) {
            'macos' => $this->provideMacOSMeilisearchGuidance(),
            'linux' => $this->provideLinuxMeilisearchGuidance(),
            'windows' => $this->provideWindowsMeilisearchGuidance(),
            default => $this->provideGenericMeilisearchGuidance(),
        };
    }

    /**
     * Provide macOS-specific Meilisearch installation guidance.
     *
     * Displays installation instructions tailored for macOS users,
     * including multiple installation methods and startup commands.
     *
     * Installation methods:
     * 1. Homebrew (recommended): Simple package manager installation
     * 2. Direct download: Binary download from GitHub
     *
     * Instructions include:
     * - Homebrew installation command
     * - Startup command with master key
     * - Verification steps
     * - Configuration tips
     */
    protected function provideMacOSMeilisearchGuidance(): void
    {
        $this->info('macOS Installation:');
        $this->info('');
        $this->info('Option 1: Homebrew (Recommended)');
        $this->info('  brew install meilisearch');
        $this->info('  meilisearch --master-key="YOUR_MASTER_KEY"');
        $this->info('');
        $this->info('Option 2: Direct Download');
        $this->info('  curl -L https://install.meilisearch.com | sh');
        $this->info('  ./meilisearch --master-key="YOUR_MASTER_KEY"');
        $this->info('');
        $this->info('After installation:');
        $this->info('  1. Start Meilisearch with a master key');
        $this->info('  2. Verify at: http://localhost:7700/health');
        $this->info('  3. Documentation: https://docs.meilisearch.com');
    }

    /**
     * Provide Linux-specific Meilisearch installation guidance.
     *
     * Displays installation instructions for Linux distributions,
     * covering the most common installation methods.
     *
     * Installation methods:
     * 1. Installation script (recommended): Automatic setup
     * 2. Manual download: Direct binary download
     * 3. Systemd service: Background service setup
     *
     * Instructions include:
     * - Installation script command
     * - Startup command with master key
     * - Systemd service configuration
     * - Verification commands
     */
    protected function provideLinuxMeilisearchGuidance(): void
    {
        $this->info('Linux Installation:');
        $this->info('');
        $this->info('Option 1: Installation Script (Recommended)');
        $this->info('  curl -L https://install.meilisearch.com | sh');
        $this->info('  ./meilisearch --master-key="YOUR_MASTER_KEY"');
        $this->info('');
        $this->info('Option 2: Systemd Service');
        $this->info('  1. Download and install Meilisearch');
        $this->info('  2. Create systemd service file');
        $this->info('  3. sudo systemctl start meilisearch');
        $this->info('');
        $this->info('After installation:');
        $this->info('  1. Start Meilisearch with a master key');
        $this->info('  2. Verify at: http://localhost:7700/health');
        $this->info('  3. Documentation: https://docs.meilisearch.com');
    }

    /**
     * Provide Windows-specific Meilisearch installation guidance.
     *
     * Displays installation instructions for Windows users,
     * focusing on direct binary download and PowerShell setup.
     *
     * Installation methods:
     * 1. Direct download: Binary from GitHub releases
     * 2. PowerShell: Automated download and setup
     *
     * Instructions include:
     * - Download link
     * - PowerShell installation command
     * - Startup command with master key
     * - Verification steps
     */
    protected function provideWindowsMeilisearchGuidance(): void
    {
        $this->info('Windows Installation:');
        $this->info('');
        $this->info('Option 1: Direct Download');
        $this->info('  1. Download from: https://github.com/meilisearch/meilisearch/releases');
        $this->info('  2. Extract the executable');
        $this->info('  3. Run: meilisearch.exe --master-key="YOUR_MASTER_KEY"');
        $this->info('');
        $this->info('Option 2: PowerShell');
        $this->info('  Invoke-WebRequest -Uri https://install.meilisearch.com -OutFile install.ps1');
        $this->info('  ./install.ps1');
        $this->info('');
        $this->info('After installation:');
        $this->info('  1. Start Meilisearch with a master key');
        $this->info('  2. Verify at: http://localhost:7700/health');
        $this->info('  3. Documentation: https://docs.meilisearch.com');
    }

    /**
     * Provide generic Meilisearch installation guidance.
     *
     * Displays basic installation instructions for unrecognized
     * operating systems or as a fallback.
     *
     * Includes:
     * - Official Meilisearch documentation link
     * - General installation steps
     * - Verification command
     */
    protected function provideGenericMeilisearchGuidance(): void
    {
        $this->info('Meilisearch Installation:');
        $this->info('');
        $this->info('Visit the official Meilisearch documentation:');
        $this->info('  https://docs.meilisearch.com/learn/getting_started/installation');
        $this->info('');
        $this->info('Quick start:');
        $this->info('  curl -L https://install.meilisearch.com | sh');
        $this->info('  ./meilisearch --master-key="YOUR_MASTER_KEY"');
        $this->info('');
        $this->info('After installation, verify with:');
        $this->info('  curl http://localhost:7700/health');
    }

    /**
     * Prompt user for manual Meilisearch configuration.
     *
     * This method provides configuration prompts when automatic setup
     * is not available or desired. It prompts the user to enter Meilisearch
     * connection details for an existing installation.
     *
     * Use cases:
     * - User prefers manual configuration
     * - Automatic setup failed
     * - Meilisearch already running
     * - Using remote Meilisearch server
     * - Using managed Meilisearch service
     *
     * Interactive prompts:
     * 1. Meilisearch host (default: http://localhost)
     * 2. Meilisearch port (default: 7700)
     * 3. Master key (generate or enter existing)
     *
     * Return value structure:
     * ```php
     * [
     *     'meilisearch_host' => 'http://localhost',
     *     'meilisearch_port' => 7700,
     *     'meilisearch_master_key' => 'key...',
     *     'using_docker' => false,
     * ]
     * ```
     *
     * Non-interactive mode:
     * - Returns defaults for all values
     * - Host: http://localhost, Port: 7700
     * - Generates secure master key
     *
     * Note: This method does NOT validate the Meilisearch connection.
     * The application will fail if credentials are incorrect or
     * Meilisearch is not running.
     *
     * @param  string $appName Application name (used for context)
     * @return array  Meilisearch configuration array with user-provided values
     */
    protected function promptMeilisearchConfiguration(string $appName): array
    {
        // Check if running in non-interactive mode
        if (! $this->input->isInteractive()) {
            // Return defaults for non-interactive mode
            return [
                'meilisearch_host' => 'http://localhost',
                'meilisearch_port' => 7700,
                'meilisearch_master_key' => bin2hex(random_bytes(16)),
                'using_docker' => false,
            ];
        }

        // Display informational note about manual configuration
        $this->note(
            'Please enter the connection details for your Meilisearch instance.',
            'Manual Meilisearch Configuration'
        );

        // =====================================================================
        // MEILISEARCH CONNECTION DETAILS
        // =====================================================================

        // Prompt for Meilisearch host
        $host = $this->text(
            label: 'Meilisearch host',
            placeholder: 'http://localhost',
            default: 'http://localhost',
            required: true,
            hint: 'The Meilisearch server URL (include http:// or https://)'
        );

        // Prompt for Meilisearch port
        $portInput = $this->text(
            label: 'Meilisearch port',
            placeholder: '7700',
            default: '7700',
            required: true,
            hint: 'The Meilisearch server port number'
        );
        $port = (int) $portInput;

        // =====================================================================
        // MASTER KEY CONFIGURATION
        // =====================================================================

        $generateKey = $this->confirm(
            label: 'Generate a new master key?',
            default: true,
            hint: 'A master key is required for Meilisearch authentication'
        );

        if ($generateKey) {
            // Generate secure master key
            $masterKey = bin2hex(random_bytes(16));
            $this->info("Generated master key: {$masterKey}");
            $this->info('Please configure Meilisearch with this master key:');
            $this->info("  meilisearch --master-key=\"{$masterKey}\"");
        } else {
            // Prompt for existing master key
            $masterKey = $this->text(
                label: 'Meilisearch master key',
                placeholder: 'Enter your existing master key',
                required: true,
                hint: 'The master key configured in your Meilisearch instance'
            );
        }

        // Return Meilisearch configuration
        return [
            'meilisearch_host' => $host,
            'meilisearch_port' => $port,
            'meilisearch_master_key' => $masterKey,
            'using_docker' => false,
        ];
    }
}
