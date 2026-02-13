<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns\Infrastructure;

use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\DTOs\Infrastructure\SearchConfig;
use PhpHive\Cli\Enums\SearchEngine;
use PhpHive\Cli\Services\Infrastructure\SearchSetupService;

/**
 * Meilisearch Interaction Trait.
 *
 * This trait provides user interaction and prompting for Meilisearch search engine setup.
 * It focuses solely on collecting user input and delegates all business logic to
 * SearchSetupService for better separation of concerns and testability.
 *
 * Meilisearch is a powerful, fast, open-source search engine that provides:
 * - Typo-tolerant search
 * - Instant search results (< 50ms)
 * - Easy to deploy and maintain
 * - RESTful API
 * - Built-in dashboard for testing
 *
 * Architecture:
 * - This trait handles user prompts and input collection
 * - SearchSetupService handles Docker setup and container management
 * - SearchConfig DTO provides type-safe configuration
 *
 * Docker-first approach:
 * 1. Check if Docker is available
 * 2. If yes, offer Docker setup (recommended for isolation)
 * 3. If Docker fails or unavailable, fall back to local setup
 *
 * Example usage:
 * ```php
 * use PhpHive\Cli\Concerns\Infrastructure\InteractsWithMeilisearch;
 *
 * class MyAppType extends AbstractAppType
 * {
 *     use InteractsWithMeilisearch;
 *
 *     public function collectConfiguration($input, $output): array
 *     {
 *         // Setup Meilisearch for the application
 *         $searchConfig = $this->setupMeilisearch('my-app', '/path/to/app');
 *
 *         return array_merge($config, $searchConfig);
 *     }
 * }
 * ```
 *
 * @see SearchSetupService For Meilisearch setup business logic
 * @see SearchConfig For type-safe configuration DTO
 * @see InteractsWithDocker For Docker availability checks
 * @see InteractsWithPrompts For prompt helper methods
 */
trait InteractsWithMeilisearch
{
    /**
     * Get the SearchSetupService instance.
     *
     * This abstract method must be implemented by the class using this trait
     * to provide access to the SearchSetupService for delegating setup operations.
     *
     * @return SearchSetupService The search setup service instance
     */
    abstract protected function searchSetupService(): SearchSetupService;

    /**
     * Orchestrate Meilisearch setup with Docker-first approach.
     *
     * This is the main entry point for Meilisearch setup. It determines whether
     * to use Docker or local installation based on availability and user preference.
     *
     * Workflow:
     * 1. Check if Docker is available
     * 2. If yes, prompt user to use Docker (recommended)
     * 3. If user accepts Docker:
     *    - Collect Docker configuration (port, master key)
     *    - Delegate to SearchSetupService for container setup
     *    - Return configuration if successful
     * 4. If Docker fails or user declines:
     *    - Fall back to local Meilisearch setup
     *    - Prompt for local connection details
     *
     * @param  string $appName Application name (unused but kept for interface consistency)
     * @param  string $appPath Absolute path to application directory for Docker Compose
     * @return array  Meilisearch configuration array with keys:
     *                - meilisearch_host: Server host URL
     *                - meilisearch_port: Server port number
     *                - meilisearch_master_key: Authentication key
     *                - using_docker: Whether Docker is being used
     */
    protected function setupMeilisearch(string $appName, string $appPath): array
    {
        // Check if Docker is available and user wants to use it
        if ($this->isDockerAvailable() && $this->confirm('Use Docker for Meilisearch? (recommended)', true)) {
            // Collect Docker configuration from user
            $config = $this->promptDockerMeilisearchConfig();

            // Delegate Docker setup to service
            $result = $this->searchSetupService()->setupDocker($config, $appPath);

            // If Docker setup succeeded, convert config to array and return
            if ($result !== null) {
                return $this->meilisearchConfigToArray($result);
            }

            // Docker setup failed, inform user and fall back to local
            $this->warning('Docker setup failed. Falling back to local setup.');
        }

        // Use local Meilisearch installation
        return $this->setupLocalMeilisearch();
    }

    /**
     * Prompt user for Docker Meilisearch configuration.
     *
     * Collects configuration needed for Docker-based Meilisearch setup:
     * - Port number (default: 7700)
     * - Master key (auto-generated for security)
     *
     * The master key is automatically generated using cryptographically secure
     * random bytes to ensure security. It's displayed to the user for reference.
     *
     * @return SearchConfig Type-safe configuration object for Docker setup
     */
    private function promptDockerMeilisearchConfig(): SearchConfig
    {
        // Generate a secure 32-character master key (16 bytes = 32 hex chars)
        $masterKey = bin2hex(random_bytes(16));

        // Prompt for port number with availability checking (default: 7700 - Meilisearch standard port)
        $port = $this->promptForAvailablePort(
            label: 'Meilisearch port',
            defaultPort: 7700,
            hint: 'Port will be checked for availability'
        );

        // Display dashboard URL and master key for user reference
        $this->info("Dashboard: http://localhost:{$port} | Master key: {$masterKey}");

        // Create and return type-safe configuration object
        return new SearchConfig(
            engine: SearchEngine::MEILISEARCH,
            host: 'http://localhost',
            port: $port,
            apiKey: $masterKey,
            usingDocker: true
        );
    }

    /**
     * Set up Meilisearch using local installation.
     *
     * Prompts user for local Meilisearch connection details. This is used when:
     * - Docker is not available
     * - User prefers local installation
     * - Docker setup failed
     *
     * In non-interactive mode, uses defaults (http://localhost:7700, auto-generated key).
     *
     * @return array Meilisearch configuration array with connection details
     */
    private function setupLocalMeilisearch(): array
    {
        // Prompt for Meilisearch host URL
        // In non-interactive mode, automatically uses default
        $host = $this->text('Meilisearch host', default: 'http://localhost', required: true);

        // Prompt for Meilisearch port
        // In non-interactive mode, automatically uses default
        $port = (int) $this->text('Meilisearch port', default: '7700', required: true);

        // Prompt for master key - offer to generate or let user provide their own
        // In non-interactive mode, defaults to true (generate new key)
        $masterKey = $this->confirm('Generate new master key?', true)
            ? bin2hex(random_bytes(16))  // Generate secure key
            : $this->text('Master key', required: true);  // Use user-provided key

        // Return configuration array
        return [
            'meilisearch_host' => $host,
            'meilisearch_port' => $port,
            'meilisearch_master_key' => $masterKey,
            AppTypeInterface::CONFIG_USING_DOCKER => false,
        ];
    }

    /**
     * Convert SearchConfig DTO to Meilisearch configuration array.
     *
     * Transforms the type-safe SearchConfig object into an associative array
     * format expected by application configuration files and environment setup.
     *
     * @param  SearchConfig $searchConfig The search configuration object
     * @return array        Meilisearch-specific configuration array
     */
    private function meilisearchConfigToArray(SearchConfig $searchConfig): array
    {
        return [
            'meilisearch_host' => $searchConfig->host,
            'meilisearch_port' => $searchConfig->port,
            'meilisearch_master_key' => $searchConfig->apiKey ?? '',
            AppTypeInterface::CONFIG_USING_DOCKER => $searchConfig->usingDocker,
        ];
    }
}
