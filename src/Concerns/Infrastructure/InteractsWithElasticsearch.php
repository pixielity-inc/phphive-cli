<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns\Infrastructure;

use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\DTOs\Infrastructure\SearchConfig;
use PhpHive\Cli\Enums\SearchEngine;
use PhpHive\Cli\Services\Infrastructure\SearchSetupService;

/**
 * Elasticsearch Interaction Trait.
 *
 * This trait provides user interaction and prompting for Elasticsearch search engine setup.
 * It focuses solely on collecting user input and delegates all business logic to
 * SearchSetupService for better separation of concerns and testability.
 *
 * Elasticsearch is a distributed, RESTful search and analytics engine that provides:
 * - Full-text search with advanced query capabilities
 * - Real-time indexing and search
 * - Distributed architecture for scalability
 * - RESTful API with JSON documents
 * - Powerful aggregations and analytics
 * - Support for multiple data types and mappings
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
 * use PhpHive\Cli\Concerns\Infrastructure\InteractsWithElasticsearch;
 *
 * class MyAppType extends AbstractAppType
 * {
 *     use InteractsWithElasticsearch;
 *
 *     public function collectConfiguration($input, $output): array
 *     {
 *         // Setup Elasticsearch for the application
 *         $searchConfig = $this->setupElasticsearch('my-app', '/path/to/app');
 *
 *         return array_merge($config, $searchConfig);
 *     }
 * }
 * ```
 *
 * @see SearchSetupService For Elasticsearch setup business logic
 * @see SearchConfig For type-safe configuration DTO
 * @see InteractsWithDocker For Docker availability checks
 * @see InteractsWithPrompts For prompt helper methods
 */
trait InteractsWithElasticsearch
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
     * Orchestrate Elasticsearch setup with Docker-first approach.
     *
     * This is the main entry point for Elasticsearch setup. It determines whether
     * to use Docker or local installation based on availability and user preference.
     *
     * Workflow:
     * 1. Check if Docker is available
     * 2. If yes, prompt user to use Docker (recommended)
     * 3. If user accepts Docker:
     *    - Collect Docker configuration (version, port, password)
     *    - Delegate to SearchSetupService for container setup
     *    - Return configuration if successful
     * 4. If Docker fails or user declines:
     *    - Fall back to local Elasticsearch setup
     *    - Prompt for local connection details
     *
     * @param  string $appName Application name (unused but kept for interface consistency)
     * @param  string $appPath Absolute path to application directory for Docker Compose
     * @return array  Elasticsearch configuration array with keys:
     *                - elasticsearch_host: Server host
     *                - elasticsearch_port: Server port number
     *                - elasticsearch_user: Authentication username
     *                - elasticsearch_password: Authentication password
     *                - using_docker: Whether Docker is being used
     */
    protected function setupElasticsearch(string $appName, string $appPath): array
    {
        // Check if Docker is available and user wants to use it
        if ($this->isDockerAvailable() && $this->confirm('Use Docker for Elasticsearch? (recommended)', true)) {
            // Collect Docker configuration from user
            $config = $this->promptDockerElasticsearchConfig();

            // Delegate Docker setup to service
            $result = $this->searchSetupService()->setupDocker($config, $appPath);

            // If Docker setup succeeded, convert config to array and return
            if ($result !== null) {
                return $this->elasticsearchConfigToArray($result);
            }

            // Docker setup failed, inform user and fall back to local
            $this->warning('Docker setup failed. Falling back to local setup.');
        }

        // Use local Elasticsearch installation
        return $this->setupLocalElasticsearch();
    }

    /**
     * Set up AWS OpenSearch Service configuration.
     *
     * OpenSearch is AWS's managed fork of Elasticsearch, providing:
     * - Fully managed service with automatic scaling
     * - AWS IAM integration for security
     * - Built-in monitoring and alerting
     * - Compatible with Elasticsearch APIs
     *
     * This method collects configuration for connecting to an existing
     * AWS OpenSearch domain. It does not create the domain itself.
     *
     * Workflow:
     * 1. Prompt for AWS region where OpenSearch domain is hosted
     * 2. Prompt for OpenSearch endpoint URL
     * 3. Prompt for index prefix to namespace application indices
     *
     * @param  string $appName Application name used as default index prefix
     * @return array  OpenSearch configuration array with keys:
     *                - search_engine: Set to 'opensearch'
     *                - opensearch_endpoint: Domain endpoint URL
     *                - opensearch_region: AWS region
     *                - opensearch_index_prefix: Prefix for index names
     *                - using_docker: Always false (managed service)
     */
    protected function setupOpenSearch(string $appName): array
    {
        // Prompt for AWS region (default: us-east-1)
        $region = $this->text('AWS Region', default: 'us-east-1', required: true);

        // Prompt for OpenSearch domain endpoint
        // Example: https://search-my-domain-abc123.us-east-1.es.amazonaws.com
        $endpoint = $this->text('OpenSearch endpoint', required: true);

        // Prompt for index prefix to avoid naming conflicts
        // Example: myapp_ will create indices like myapp_products, myapp_users
        $indexPrefix = $this->text('Index prefix', default: $appName);

        // Return OpenSearch configuration
        return [
            'search_engine' => SearchEngine::OPENSEARCH->value,
            'opensearch_endpoint' => $endpoint,
            'opensearch_region' => $region,
            'opensearch_index_prefix' => $indexPrefix,
            AppTypeInterface::CONFIG_USING_DOCKER => false,
        ];
    }

    /**
     * Prompt user for Docker Elasticsearch configuration.
     *
     * Collects configuration needed for Docker-based Elasticsearch setup:
     * - Version selection (7.x stable or 8.x latest)
     * - Port number (default: 9200)
     * - Secure password (auto-generated)
     *
     * Version differences:
     * - 7.x: Stable, widely used, simpler security model
     * - 8.x: Latest features, enhanced security by default, TLS required
     *
     * The password is automatically generated using cryptographically secure
     * random bytes to ensure security. It's used for the 'elastic' superuser.
     *
     * @return SearchConfig Type-safe configuration object for Docker setup
     */
    private function promptDockerElasticsearchConfig(): SearchConfig
    {
        // Prompt for Elasticsearch version
        // Version 8.x has security enabled by default and requires TLS
        // Version 7.x is more straightforward for development
        $this->select(
            'Elasticsearch version',
            ['7' => '7.x (Stable)', '8' => '8.x (Latest)'],
            default: '8'
        );

        // Generate a secure 32-character password (16 bytes = 32 hex chars)
        // This will be used for the 'elastic' superuser account
        $password = bin2hex(random_bytes(16));

        // Inform user that password was generated
        $this->info('Generated secure password for Elasticsearch');

        // Prompt for port number with availability checking (default: 9200 - Elasticsearch standard port)
        $port = $this->promptForAvailablePort(
            label: 'Elasticsearch port',
            defaultPort: 9200,
            hint: 'Port will be checked for availability'
        );

        // Create and return type-safe configuration object
        return new SearchConfig(
            engine: SearchEngine::ELASTICSEARCH,
            host: 'localhost',
            port: $port,
            apiKey: $password,
            usingDocker: true
        );
    }

    /**
     * Set up Elasticsearch using local installation.
     *
     * Prompts user for local Elasticsearch connection details. This is used when:
     * - Docker is not available
     * - User prefers local installation
     * - Docker setup failed
     *
     * Collects:
     * - Host (default: localhost)
     * - Port (default: 9200)
     * - Username (default: elastic)
     * - Password (optional, can be empty for development)
     *
     * In non-interactive mode, uses defaults (localhost:9200, user: elastic, no password).
     *
     * @return array Elasticsearch configuration array with connection details
     */
    private function setupLocalElasticsearch(): array
    {
        // Prompt for Elasticsearch host
        // In non-interactive mode, automatically uses default
        $host = $this->text('Elasticsearch host', default: 'localhost', required: true);

        // Prompt for Elasticsearch port (9200 is standard HTTP port)
        // In non-interactive mode, automatically uses default
        $port = (int) $this->text('Elasticsearch port', default: '9200', required: true);

        // Prompt for username (elastic is the default superuser)
        // In non-interactive mode, automatically uses default
        $user = $this->text('Elasticsearch username', default: 'elastic', required: true);

        // Prompt for password (optional for development environments)
        // Note: Production Elasticsearch should always have authentication enabled
        // In non-interactive mode, returns empty string
        $password = $this->password('Elasticsearch password', required: false);

        // Return configuration array
        return [
            'elasticsearch_host' => $host,
            'elasticsearch_port' => $port,
            'elasticsearch_user' => $user,
            'elasticsearch_password' => $password,
            AppTypeInterface::CONFIG_USING_DOCKER => false,
        ];
    }

    /**
     * Convert SearchConfig DTO to Elasticsearch configuration array.
     *
     * Transforms the type-safe SearchConfig object into an associative array
     * format expected by application configuration files and environment setup.
     *
     * Maps SearchConfig properties to Elasticsearch-specific keys:
     * - host → elasticsearch_host
     * - port → elasticsearch_port
     * - apiKey → elasticsearch_password (used for 'elastic' user)
     * - usingDocker → using_docker flag
     *
     * Note: Username is hardcoded to 'elastic' (Elasticsearch default superuser)
     *
     * @param  SearchConfig $searchConfig The search configuration object
     * @return array        Elasticsearch-specific configuration array
     */
    private function elasticsearchConfigToArray(SearchConfig $searchConfig): array
    {
        return [
            'elasticsearch_host' => $searchConfig->host,
            'elasticsearch_port' => $searchConfig->port,
            'elasticsearch_user' => 'elastic',
            'elasticsearch_password' => $searchConfig->apiKey ?? '',
            AppTypeInterface::CONFIG_USING_DOCKER => $searchConfig->usingDocker,
        ];
    }
}
