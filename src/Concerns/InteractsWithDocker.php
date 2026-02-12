<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns;

use PhpHive\Cli\Support\Process;

/**
 * Docker Interaction Trait.
 *
 * This trait provides comprehensive Docker integration for application types,
 * enabling containerized development environments with database services,
 * caching layers, and other infrastructure components.
 *
 * Key features:
 * - Docker installation detection across platforms (macOS, Linux, Windows)
 * - Docker daemon status checking
 * - Docker Compose availability verification
 * - Container lifecycle management (start, stop, status)
 * - Docker Compose file generation from templates
 * - OS-specific installation guidance
 * - Beautiful user feedback using Laravel Prompts
 *
 * Architecture:
 * - Docker-first approach: Recommends Docker when available
 * - Graceful fallback: Falls back to local services if Docker unavailable
 * - Template-based: Uses stub templates for docker-compose.yml generation
 * - Reusable: Works across all app types (Magento, Laravel, Symfony, etc.)
 *
 * Typical workflow:
 * 1. Check if Docker is installed and running
 * 2. If not available, guide user to install or fall back to local
 * 3. Generate docker-compose.yml from templates
 * 4. Start containers with docker-compose up -d
 * 5. Wait for services to be ready
 * 6. Return connection details for application configuration
 *
 * Example usage:
 * ```php
 * use PhpHive\Cli\Concerns\InteractsWithDocker;
 *
 * class MyAppType extends AbstractAppType
 * {
 *     use InteractsWithDocker;
 *
 *     public function collectConfiguration($input, $output): array
 *     {
 *         $this->input = $input;
 *         $this->output = $output;
 *
 *         // Check Docker availability
 *         if ($this->isDockerAvailable()) {
 *             $useDocker = $this->confirm('Use Docker for database?', true);
 *             if ($useDocker) {
 *                 return $this->setupDockerDatabase('my-app');
 *             }
 *         }
 *
 *         // Fallback to local setup
 *         return $this->setupLocalDatabase('my-app');
 *     }
 * }
 * ```
 *
 * Docker Compose templates:
 * - Located in cli/stubs/docker/
 * - Support MySQL, PostgreSQL, MariaDB
 * - Include optional services (Redis, Elasticsearch, phpMyAdmin)
 * - Use environment variables for configuration
 *
 * Container naming convention:
 * - Format: phphive-{app-name}-{service}
 * - Example: phphive-my-shop-mysql, phphive-my-shop-redis
 * - Ensures uniqueness and easy identification
 *
 * Network configuration:
 * - All services on shared network: phphive-{app-name}
 * - Enables inter-service communication
 * - Isolated from other projects
 *
 * Volume management:
 * - Named volumes for data persistence
 * - Format: phphive-{app-name}-{service}-data
 * - Survives container recreation
 *
 * @phpstan-ignore-next-line trait.unused
 *
 * @see AbstractAppType For base app type functionality
 * @see InteractsWithDatabase For database setup functionality
 * @see InteractsWithPrompts For prompt helper methods
 */
trait InteractsWithDocker
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
     * Check if Docker is installed on the system.
     *
     * Attempts to execute 'docker --version' command to verify Docker
     * installation. This checks for Docker CLI availability but does not
     * verify if the Docker daemon is running.
     *
     * Detection process:
     * 1. Execute 'docker --version' command
     * 2. Check if command exits successfully (exit code 0)
     * 3. Return true if Docker CLI is available
     *
     * Supported platforms:
     * - macOS: Docker Desktop or Docker Engine
     * - Linux: Docker Engine or Docker Desktop
     * - Windows: Docker Desktop (WSL2 backend)
     *
     * Note: This only checks for Docker CLI installation, not daemon status.
     * Use isDockerRunning() to check if Docker daemon is active.
     *
     * @return bool True if Docker is installed, false otherwise
     */
    protected function isDockerInstalled(): bool
    {
        // Use Process service to check Docker version
        return $this->process()->succeeds(['docker', '--version']);
    }

    /**
     * Check if Docker daemon is running and accessible.
     *
     * Verifies that the Docker daemon is active and responding to commands
     * by attempting to execute 'docker ps'. This is more comprehensive than
     * just checking if Docker is installed.
     *
     * Verification process:
     * 1. Execute 'docker ps' command (lists running containers)
     * 2. Check if command exits successfully
     * 3. Return true if Docker daemon responds
     *
     * Common failure reasons:
     * - Docker daemon not started
     * - Docker Desktop not running (macOS/Windows)
     * - Insufficient permissions (Linux without sudo/docker group)
     * - Docker service stopped
     *
     * Troubleshooting:
     * - macOS/Windows: Start Docker Desktop application
     * - Linux: Run 'sudo systemctl start docker'
     * - Permissions: Add user to docker group
     *
     * @return bool True if Docker daemon is running, false otherwise
     */
    protected function isDockerRunning(): bool
    {
        // Use Process service to list running containers
        return $this->process()->succeeds(['docker', 'ps']);
    }

    /**
     * Check if Docker Compose is available.
     *
     * Verifies Docker Compose installation by checking for both:
     * - docker-compose (standalone binary, older installations)
     * - docker compose (Docker CLI plugin, newer installations)
     *
     * Docker Compose versions:
     * - V1 (standalone): docker-compose command
     * - V2 (plugin): docker compose command
     * - Both are supported and functionally equivalent
     *
     * Detection process:
     * 1. Try 'docker compose version' (V2 plugin)
     * 2. If fails, try 'docker-compose --version' (V1 standalone)
     * 3. Return true if either succeeds
     *
     * Installation:
     * - Docker Desktop: Includes Compose V2 by default
     * - Linux: Install docker-compose-plugin or standalone binary
     * - Windows: Included with Docker Desktop
     *
     * @return bool True if Docker Compose is available, false otherwise
     */
    protected function isDockerComposeAvailable(): bool
    {
        // Try Docker Compose V2 (plugin) using Process service
        if ($this->process()->succeeds(['docker', 'compose', 'version'])) {
            return true;
        }

        // Try Docker Compose V1 (standalone) using Process service
        return $this->process()->succeeds(['docker-compose', '--version']);
    }

    /**
     * Check if Docker is fully available and ready to use.
     *
     * Comprehensive check that verifies:
     * 1. Docker is installed
     * 2. Docker daemon is running
     * 3. Docker Compose is available
     *
     * This is the primary method to call before attempting any Docker
     * operations. It ensures the complete Docker environment is ready.
     *
     * Return value:
     * - true: Docker is fully operational, safe to proceed
     * - false: Docker is not available, fall back to local setup
     *
     * Usage:
     * ```php
     * if ($this->isDockerAvailable()) {
     *     // Proceed with Docker setup
     * } else {
     *     // Fall back to local setup
     * }
     * ```
     *
     * @return bool True if Docker is fully available, false otherwise
     */
    protected function isDockerAvailable(): bool
    {
        return $this->isDockerInstalled()
            && $this->isDockerRunning()
            && $this->isDockerComposeAvailable();
    }

    /**
     * Detect the operating system.
     *
     * Identifies the current operating system to provide OS-specific
     * guidance and commands. Uses PHP's PHP_OS constant for detection.
     *
     * Supported operating systems:
     * - macos: macOS (Darwin kernel)
     * - linux: Linux distributions
     * - windows: Windows (all versions)
     * - unknown: Other/unrecognized systems
     *
     * Detection logic:
     * - Checks PHP_OS constant
     * - Case-insensitive matching
     * - Returns normalized lowercase string
     *
     * Usage:
     * ```php
     * $os = $this->detectOS();
     * if ($os === 'macos') {
     *     // macOS-specific instructions
     * }
     * ```
     *
     * @return string Operating system identifier (macos, linux, windows, unknown)
     */
    protected function detectOS(): string
    {
        $os = strtolower(PHP_OS);

        if (str_contains($os, 'darwin')) {
            return 'macos';
        }

        if (str_contains($os, 'linux')) {
            return 'linux';
        }

        if (str_contains($os, 'win')) {
            return 'windows';
        }

        return 'unknown';
    }

    /**
     * Provide Docker installation guidance based on operating system.
     *
     * Displays helpful information and instructions for installing Docker
     * on the user's operating system. Includes download links, installation
     * methods, and verification steps.
     *
     * Installation guidance by OS:
     *
     * macOS:
     * - Docker Desktop (recommended)
     * - Homebrew installation option
     * - Download link and verification steps
     *
     * Linux:
     * - Distribution-specific package managers
     * - Official Docker repository setup
     * - Post-installation steps (docker group)
     *
     * Windows:
     * - Docker Desktop with WSL2 backend
     * - System requirements
     * - Download link and setup guide
     *
     * Display format:
     * - Uses Laravel Prompts $this->note() for visibility
     * - Includes clickable links
     * - Step-by-step instructions
     * - Verification commands
     */
    protected function provideDockerInstallationGuidance(): void
    {
        $os = $this->detectOS();

        $this->note(
            'Docker is not installed or not running. Docker provides isolated database containers for your applications.',
            'Docker Not Available'
        );

        match ($os) {
            'macos' => $this->provideMacOSInstallationGuidance(),
            'linux' => $this->provideLinuxInstallationGuidance(),
            'windows' => $this->provideWindowsInstallationGuidance(),
            default => $this->provideGenericInstallationGuidance(),
        };
    }

    /**
     * Provide macOS-specific Docker installation guidance.
     *
     * Displays installation instructions tailored for macOS users,
     * including multiple installation methods and verification steps.
     *
     * Installation methods:
     * 1. Docker Desktop (recommended): GUI application with all features
     * 2. Homebrew: Command-line installation for developers
     *
     * Instructions include:
     * - Download link for Docker Desktop
     * - Homebrew installation command
     * - Post-installation verification
     * - Troubleshooting tips
     */
    protected function provideMacOSInstallationGuidance(): void
    {
        $this->info('macOS Installation:');
        $this->info('');
        $this->info('Option 1: Docker Desktop (Recommended)');
        $this->info('  1. Download from: https://www.docker.com/products/docker-desktop');
        $this->info('  2. Install and start Docker Desktop');
        $this->info('  3. Wait for Docker to start (whale icon in menu bar)');
        $this->info('');
        $this->info('Option 2: Homebrew');
        $this->info('  brew install --cask docker');
        $this->info('');
        $this->info('After installation, verify with: docker --version');
    }

    /**
     * Provide Linux-specific Docker installation guidance.
     *
     * Displays installation instructions for Linux distributions,
     * covering the most common package managers and distributions.
     *
     * Supported distributions:
     * - Ubuntu/Debian: apt-get
     * - Fedora/RHEL/CentOS: dnf/yum
     * - Arch Linux: pacman
     *
     * Instructions include:
     * - Official Docker repository setup
     * - Package installation commands
     * - Post-installation steps (docker group, service start)
     * - Verification commands
     */
    protected function provideLinuxInstallationGuidance(): void
    {
        $this->info('Linux Installation:');
        $this->info('');
        $this->info('Ubuntu/Debian:');
        $this->info('  curl -fsSL https://get.docker.com -o get-docker.sh');
        $this->info('  sudo sh get-docker.sh');
        $this->info('  sudo usermod -aG docker $USER');
        $this->info('  sudo systemctl start docker');
        $this->info('');
        $this->info('Fedora/RHEL/CentOS:');
        $this->info('  sudo dnf install docker-ce docker-ce-cli containerd.io');
        $this->info('  sudo systemctl start docker');
        $this->info('');
        $this->info('After installation:');
        $this->info('  1. Log out and back in (for docker group)');
        $this->info('  2. Verify with: docker --version');
    }

    /**
     * Provide Windows-specific Docker installation guidance.
     *
     * Displays installation instructions for Windows users,
     * focusing on Docker Desktop with WSL2 backend.
     *
     * Requirements:
     * - Windows 10/11 (64-bit)
     * - WSL2 enabled
     * - Virtualization enabled in BIOS
     *
     * Instructions include:
     * - System requirements check
     * - WSL2 installation (if needed)
     * - Docker Desktop download link
     * - Post-installation verification
     */
    protected function provideWindowsInstallationGuidance(): void
    {
        $this->info('Windows Installation:');
        $this->info('');
        $this->info('Requirements:');
        $this->info('  - Windows 10/11 (64-bit)');
        $this->info('  - WSL2 enabled');
        $this->info('  - Virtualization enabled in BIOS');
        $this->info('');
        $this->info('Installation:');
        $this->info('  1. Enable WSL2: wsl --install');
        $this->info('  2. Download Docker Desktop: https://www.docker.com/products/docker-desktop');
        $this->info('  3. Install and start Docker Desktop');
        $this->info('  4. Verify with: docker --version');
    }

    /**
     * Provide generic Docker installation guidance.
     *
     * Displays basic installation instructions for unrecognized
     * operating systems or as a fallback.
     *
     * Includes:
     * - Official Docker documentation link
     * - General installation steps
     * - Verification command
     */
    protected function provideGenericInstallationGuidance(): void
    {
        $this->info('Docker Installation:');
        $this->info('');
        $this->info('Visit the official Docker documentation:');
        $this->info('  https://docs.docker.com/get-docker/');
        $this->info('');
        $this->info('After installation, verify with: docker --version');
    }

    /**
     * Start Docker containers using docker-compose.
     *
     * Executes 'docker compose up -d' to start all services defined in
     * the docker-compose.yml file in detached mode (background).
     *
     * Process:
     * 1. Change to application directory
     * 2. Execute docker compose up -d
     * 3. Wait for command completion
     * 4. Return success/failure status
     *
     * Command flags:
     * - up: Create and start containers
     * - -d: Detached mode (run in background)
     *
     * Note: This method assumes docker-compose.yml exists in the app directory.
     * Use generateDockerComposeFile() to create it first.
     *
     * @param  string $appPath Absolute path to application directory
     * @return bool   True if containers started successfully, false otherwise
     */
    protected function startDockerContainers(string $appPath): bool
    {
        // Start containers using Process service with 5 minute timeout
        return $this->process()->succeeds(['docker', 'compose', 'up', '-d'], $appPath, 300);
    }

    /**
     * Stop Docker containers using docker-compose.
     *
     * Executes 'docker compose down' to stop and remove all containers
     * defined in the docker-compose.yml file.
     *
     * Process:
     * 1. Change to application directory
     * 2. Execute docker compose down
     * 3. Wait for command completion
     * 4. Return success/failure status
     *
     * What gets removed:
     * - Containers
     * - Networks
     * - Default bridge network
     *
     * What persists:
     * - Volumes (data is preserved)
     * - Images (can be reused)
     *
     * @param  string $appPath Absolute path to application directory
     * @return bool   True if containers stopped successfully, false otherwise
     */
    protected function stopDockerContainers(string $appPath): bool
    {
        // Stop containers using Process service with 2 minute timeout
        return $this->process()->succeeds(['docker', 'compose', 'down'], $appPath, 120);
    }

    /**
     * Check if Docker containers are running for an application.
     *
     * Verifies that containers defined in docker-compose.yml are
     * currently running by executing 'docker compose ps'.
     *
     * Process:
     * 1. Change to application directory
     * 2. Execute docker compose ps
     * 3. Check if command succeeds and returns running containers
     * 4. Return status
     *
     * Use cases:
     * - Verify containers started successfully
     * - Check if services are ready
     * - Troubleshoot startup issues
     *
     * @param  string $appPath Absolute path to application directory
     * @return bool   True if containers are running, false otherwise
     */
    protected function areDockerContainersRunning(string $appPath): bool
    {
        // Check container status using Process service
        $output = $this->process()->run(
            ['docker', 'compose', 'ps', '--services', '--filter', 'status=running'],
            $appPath
        );

        // Return true if command succeeded and has output
        return $output !== null && trim($output) !== '';
    }

    /**
     * Wait for a Docker service to be ready.
     *
     * Polls a Docker container until it's healthy and ready to accept
     * connections. Uses docker compose exec to run health checks.
     *
     * Polling strategy:
     * - Maximum attempts: 30 (configurable)
     * - Delay between attempts: 2 seconds
     * - Total maximum wait: 60 seconds
     *
     * Health check methods:
     * - MySQL: Execute 'mysqladmin ping'
     * - PostgreSQL: Execute 'pg_isready'
     * - Redis: Execute 'redis-cli ping'
     *
     * @param  string $appPath     Absolute path to application directory
     * @param  string $serviceName Name of service in docker-compose.yml
     * @param  int    $maxAttempts Maximum number of polling attempts
     * @return bool   True if service is ready, false if timeout
     */
    protected function waitForDockerService(string $appPath, string $serviceName, int $maxAttempts = 30): bool
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            // Check if service is running using Process service
            if ($this->process()->succeeds(
                ['docker', 'compose', 'exec', '-T', $serviceName, 'echo', 'ready'],
                $appPath
            )) {
                return true;
            }

            // Wait 2 seconds before next attempt
            sleep(2);
            $attempts++;
        }

        return false;
    }
}
