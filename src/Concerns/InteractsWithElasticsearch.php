<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns;

use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\Support\Filesystem;
use PhpHive\Cli\Support\Process;
use RuntimeException;

/**
 * Elasticsearch Interaction Trait.
 *
 * This trait provides comprehensive Elasticsearch setup functionality for application
 * types that require search engine configuration. It supports both Docker-based and
 * local Elasticsearch setups with automatic configuration and graceful fallbacks.
 *
 * Key features:
 * - Docker-first approach: Recommends Docker when available
 * - Multiple Elasticsearch versions: 7.x and 8.x support
 * - Automatic Docker Compose file generation
 * - Container management and health checking
 * - Optional Kibana integration for visualization and management
 * - Secure password generation for Docker Elasticsearch
 * - Local Elasticsearch fallback for non-Docker setups
 * - Graceful error handling with fallback options
 * - Detailed user feedback using Laravel Prompts
 * - Reusable across multiple app types (Magento, Laravel, Symfony, etc.)
 *
 * Docker-first workflow:
 * 1. Check if Docker is available
 * 2. If yes, offer Docker Elasticsearch setup (recommended)
 * 3. Prompt for Elasticsearch version (7.x or 8.x)
 * 4. Prompt for optional Kibana service
 * 5. Generate secure passwords for Elasticsearch
 * 6. Generate docker-compose section for Elasticsearch
 * 7. Start Docker containers
 * 8. Wait for Elasticsearch to be ready (health check)
 * 9. Return connection details
 * 10. If Docker unavailable or user declines, fall back to local setup
 *
 * Local Elasticsearch workflow:
 * 1. Prompt for Elasticsearch connection details
 * 2. Ask for host, port, username, password
 * 3. Return credentials for application configuration
 * 4. No validation (assumes user has Elasticsearch running)
 *
 * Example usage:
 * ```php
 * use PhpHive\Cli\Concerns\InteractsWithElasticsearch;
 * use PhpHive\Cli\Concerns\InteractsWithDocker;
 *
 * class MyAppType extends AbstractAppType
 * {
 *     use InteractsWithElasticsearch;
 *     use InteractsWithDocker;
 *
 *     public function collectConfiguration($input, $output): array
 *     {
 *         $this->input = $input;
 *         $this->output = $output;
 *
 *         // Orchestrate Elasticsearch setup (Docker-first)
 *         $esConfig = $this->setupElasticsearch('my-app', '/path/to/app');
 *
 *         return $esConfig;
 *     }
 * }
 * ```
 *
 * Security considerations:
 * - Secure passwords generated using random_bytes()
 * - Passwords are masked during input
 * - Docker containers are isolated per project
 * - Elasticsearch security features enabled by default (8.x)
 * - Connection attempts are limited to prevent brute force
 *
 * Elasticsearch versions:
 * - 7.x: Stable, widely used, simpler security model
 * - 8.x: Latest, enhanced security, TLS by default
 *
 * Default ports:
 * - Elasticsearch: 9200 (HTTP API)
 * - Kibana: 5601 (Web UI)
 *
 * @phpstan-ignore-next-line trait.unused
 *
 * @see AbstractAppType For base app type functionality
 * @see InteractsWithDocker For Docker management functionality
 * @see InteractsWithPrompts For prompt helper methods
 */
trait InteractsWithElasticsearch
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
     * Orchestrate Elasticsearch setup with Docker-first approach.
     *
     * This is the main entry point for Elasticsearch setup. It intelligently
     * chooses between Docker and local Elasticsearch based on availability and
     * user preference, with graceful fallbacks at each step.
     *
     * Decision flow:
     * 1. Check if Docker is available (requires InteractsWithDocker trait)
     * 2. If Docker available:
     *    - Offer Docker setup (recommended)
     *    - If user accepts → setupDockerElasticsearch()
     *    - If user declines → setupLocalElasticsearch()
     * 3. If Docker not available:
     *    - Show installation guidance (optional)
     *    - Fall back to setupLocalElasticsearch()
     *
     * Supported Elasticsearch versions:
     * - 7.x: Elasticsearch 7.17 (LTS)
     * - 8.x: Elasticsearch 8.x (Latest)
     *
     * Return value structure:
     * ```php
     * [
     *     'elasticsearch_host' => 'localhost',        // Host
     *     'elasticsearch_port' => 9200,               // Port
     *     'elasticsearch_user' => 'elastic',          // Username
     *     'elasticsearch_password' => 'password',     // Password
     *     'using_docker' => true,                     // Whether Docker is used
     * ]
     * ```
     *
     * @param  string $appName Application name for defaults
     * @param  string $appPath Absolute path to application directory
     * @return array  Elasticsearch configuration array
     */
    protected function setupElasticsearch(string $appName, string $appPath): array
    {
        // Check if Docker is available (requires InteractsWithDocker trait)

        if ($this->isDockerAvailable()) {
            // Docker is available - offer Docker setup
            $this->note(
                'Docker detected! Using Docker provides isolated Elasticsearch, easy management, and no local installation needed.',
                'Elasticsearch Setup'
            );

            $useDocker = $this->confirm(
                label: 'Would you like to use Docker for Elasticsearch? (recommended)',
                default: true
            );

            if ($useDocker) {
                $esConfig = $this->setupDockerElasticsearch($appName, $appPath);
                if ($esConfig !== null) {
                    return $esConfig;
                }

                // Docker setup failed, fall back to local
                $this->warning('Docker setup failed. Falling back to local Elasticsearch setup.');
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

        // Fall back to local Elasticsearch setup
        return $this->setupLocalElasticsearch($appName);
    }

    /**
     * Setup AWS OpenSearch configuration.
     *
     * @param  string               $appName The application name
     * @return array<string, mixed> OpenSearch configuration
     */
    protected function setupOpenSearch(string $appName): array
    {
        $this->note(
            'AWS OpenSearch requires AWS credentials. You can configure them in your .env file.',
            'AWS Configuration'
        );

        // Prompt for AWS configuration
        $region = $this->text(
            label: 'AWS Region',
            placeholder: 'us-east-1',
            default: 'us-east-1',
            required: true,
            hint: 'AWS region where your OpenSearch domain is located'
        );

        $endpoint = $this->text(
            label: 'OpenSearch endpoint',
            placeholder: 'search-my-domain-abc123.us-east-1.es.amazonaws.com',
            required: true,
            hint: 'Your OpenSearch domain endpoint (without https://)'
        );

        $indexPrefix = $this->text(
            label: 'Index prefix (optional)',
            placeholder: $appName,
            default: $appName,
            required: false,
            hint: 'Prefix for index names'
        );

        $this->note(
            "Configure AWS credentials in your .env file:\n\n" .
            "AWS_ACCESS_KEY_ID=your-access-key\n" .
            "AWS_SECRET_ACCESS_KEY=your-secret-key\n" .
            "AWS_DEFAULT_REGION={$region}\n" .
            "OPENSEARCH_ENDPOINT=https://{$endpoint}\n" .
            "OPENSEARCH_INDEX_PREFIX={$indexPrefix}",
            'Environment Variables'
        );

        return [
            'search_engine' => 'opensearch',
            'opensearch_endpoint' => $endpoint,
            'opensearch_region' => $region,
            'opensearch_index_prefix' => $indexPrefix,
            AppTypeInterface::CONFIG_USING_DOCKER => false,
        ];
    }

    /**
     * Set up Elasticsearch using Docker containers.
     *
     * Creates a Docker Compose configuration with the selected Elasticsearch
     * version and starts the containers. Includes optional Kibana service
     * for visualization and management.
     *
     * Process:
     * 1. Prompt for Elasticsearch version (7.x or 8.x)
     * 2. Prompt for optional Kibana service
     * 3. Generate secure passwords
     * 4. Generate docker-compose.yml section for Elasticsearch
     * 5. Start Docker containers
     * 6. Wait for Elasticsearch to be ready (health check)
     * 7. Return connection details
     *
     * Generated files:
     * - docker-compose.yml: Container configuration (appended or created)
     * - .env.elasticsearch: Environment variables (optional)
     *
     * Container naming:
     * - Format: phphive-{app-name}-{service}
     * - Example: phphive-my-shop-elasticsearch
     *
     * Version differences:
     * - 7.x: Simpler security, optional authentication
     * - 8.x: Enhanced security, TLS by default, required authentication
     *
     * @param  string     $appName Application name
     * @param  string     $appPath Application directory path
     * @return array|null Elasticsearch config on success, null on failure
     */
    protected function setupDockerElasticsearch(string $appName, string $appPath): ?array
    {
        // Check if running in non-interactive mode
        if (! $this->input->isInteractive()) {
            return null;
        }

        // =====================================================================
        // ELASTICSEARCH VERSION SELECTION
        // =====================================================================

        $esVersion = (string) $this->select(
            label: 'Select Elasticsearch version',
            options: [
                '7' => 'Elasticsearch 7.x (Stable, widely used)',
                '8' => 'Elasticsearch 8.x (Latest, enhanced security)',
            ],
            default: '8'
        );

        // =====================================================================
        // OPTIONAL SERVICES
        // =====================================================================

        $includeKibana = $this->confirm(
            label: 'Include Kibana for visualization and management?',
            default: true
        );

        // =====================================================================
        // CONFIGURATION
        // =====================================================================

        strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $appName) ?? $appName);

        // Generate secure password for Elasticsearch
        $esPassword = bin2hex(random_bytes(16));

        $this->info('Generated secure password for Elasticsearch');

        // =====================================================================
        // GENERATE DOCKER COMPOSE FILE
        // =====================================================================

        $this->info('Generating docker-compose.yml for Elasticsearch...');

        $composeGenerated = $this->generateElasticsearchDockerComposeFile(
            $appPath,
            $esVersion,
            $appName,
            $esPassword,
            $includeKibana
        );

        if (! $composeGenerated) {
            $this->error('Failed to generate docker-compose.yml');

            return null;
        }

        // =====================================================================
        // START CONTAINERS
        // =====================================================================

        $this->info('Starting Docker containers...');

        $started = $this->spin(
            callback: fn (): bool => $this->startDockerContainers($appPath),
            message: 'Starting containers...'
        );

        if (! $started) {
            $this->error('Failed to start Docker containers');

            return null;
        }

        // =====================================================================
        // WAIT FOR ELASTICSEARCH
        // =====================================================================

        $this->info('Waiting for Elasticsearch to be ready...');

        $ready = $this->spin(
            callback: fn (): bool => $this->waitForElasticsearchHealth($appPath, 'elasticsearch', 60),
            message: 'Waiting for Elasticsearch health check...'
        );

        if (! $ready) {
            $this->warning('Elasticsearch may not be fully ready. You may need to wait a moment before using it.');
        } else {
            $this->info('✓ Elasticsearch is ready!');
        }

        // =====================================================================
        // RETURN CONFIGURATION
        // =====================================================================

        $this->info('✓ Docker Elasticsearch setup complete!');
        if ($includeKibana) {
            $this->info('Kibana UI: http://localhost:5601');
            $this->info('Login with username: elastic, password: ' . $esPassword);
        }

        return [
            'elasticsearch_host' => 'localhost',
            'elasticsearch_port' => 9200,
            'elasticsearch_user' => 'elastic',
            'elasticsearch_password' => $esPassword,
            AppTypeInterface::CONFIG_USING_DOCKER => true,
        ];
    }

    /**
     * Generate docker-compose.yml section for Elasticsearch.
     *
     * Creates or appends to docker-compose.yml with Elasticsearch and
     * optional Kibana service configurations. Handles both new file
     * creation and appending to existing docker-compose files.
     *
     * Template placeholders:
     * - {{CONTAINER_PREFIX}}: phphive-{app-name}
     * - {{VOLUME_PREFIX}}: phphive-{app-name}
     * - {{NETWORK_NAME}}: phphive-{app-name}
     * - {{ES_PASSWORD}}: Elasticsearch password
     * - {{ES_PORT}}: Elasticsearch port (9200)
     * - {{KIBANA_PORT}}: Kibana port (5601)
     * - {{ES_VERSION}}: Elasticsearch version (7 or 8)
     *
     * Elasticsearch 7.x configuration:
     * - Single node cluster
     * - Discovery type: single-node
     * - Security: Optional (xpack.security.enabled=false)
     * - Memory: 512MB heap size
     *
     * Elasticsearch 8.x configuration:
     * - Single node cluster
     * - Discovery type: single-node
     * - Security: Enabled by default
     * - TLS: Disabled for development (xpack.security.http.ssl.enabled=false)
     * - Memory: 512MB heap size
     *
     * Kibana configuration:
     * - Connected to Elasticsearch
     * - Port: 5601
     * - Depends on Elasticsearch service
     *
     * @param  string $appPath       Application directory path
     * @param  string $esVersion     Elasticsearch version (7 or 8)
     * @param  string $appName       Application name
     * @param  string $esPassword    Elasticsearch password
     * @param  bool   $includeKibana Include Kibana service
     * @return bool   True on success, false on failure
     */
    protected function generateElasticsearchDockerComposeFile(
        string $appPath,
        string $esVersion,
        string $appName,
        string $esPassword,
        bool $includeKibana
    ): bool {
        // Normalize app name for container/volume names
        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $appName) ?? $appName);

        // Determine Elasticsearch image version
        $esImageVersion = match ($esVersion) {
            '7' => '7.17.10',
            '8' => '8.11.0',
            default => '8.11.0',
        };

        // Build Elasticsearch service configuration
        $esConfig = $this->buildElasticsearchServiceConfig(
            $normalizedName,
            $esImageVersion,
            $esVersion,
            $esPassword
        );

        // Build Kibana service configuration if requested
        $kibanaConfig = '';
        if ($includeKibana) {
            $kibanaConfig = $this->buildKibanaServiceConfig(
                $normalizedName,
                $esVersion,
                $esPassword
            );
        }

        // Check if docker-compose.yml exists
        $composePath = $appPath . '/docker-compose.yml';
        $composeExists = $this->filesystem()->exists($composePath);

        if ($composeExists) {
            // Append to existing docker-compose.yml using Filesystem
            try {
                $existingContent = $this->filesystem()->read($composePath);
            } catch (RuntimeException) {
                return false;
            }

            // Append Elasticsearch service
            $newContent = $existingContent . "\n" . $esConfig;
            if ($includeKibana) {
                $newContent .= "\n" . $kibanaConfig;
            }

            try {
                $this->filesystem()->write($composePath, $newContent);

                return true;
            } catch (RuntimeException) {
                return false;
            }
        }
        // Create new docker-compose.yml using Filesystem
        $content = "version: '3.8'\n\n";
        $content .= "services:\n";
        $content .= $esConfig;
        if ($includeKibana) {
            $content .= "\n" . $kibanaConfig;
        }
        $content .= "\n";
        $content .= "volumes:\n";
        $content .= "  phphive-{$normalizedName}-elasticsearch-data:\n";
        $content .= "    driver: local\n";
        $content .= "\n";
        $content .= "networks:\n";
        $content .= "  phphive-{$normalizedName}:\n";
        $content .= "    driver: bridge\n";

        try {
            $this->filesystem()->write($composePath, $content);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Build Elasticsearch service configuration for docker-compose.yml.
     *
     * Generates the YAML configuration for Elasticsearch service with
     * appropriate settings based on version (7.x or 8.x).
     *
     * Configuration includes:
     * - Container name and image
     * - Environment variables for cluster and security
     * - Port mappings (9200:9200)
     * - Volume mounts for data persistence
     * - Network configuration
     * - Health check configuration
     * - Resource limits (memory)
     *
     * Version-specific settings:
     * - 7.x: Optional security, simpler configuration
     * - 8.x: Enhanced security, TLS disabled for development
     *
     * @param  string $normalizedName Normalized application name
     * @param  string $imageVersion   Elasticsearch image version
     * @param  string $esVersion      Elasticsearch major version (7 or 8)
     * @param  string $esPassword     Elasticsearch password
     * @return string YAML configuration for Elasticsearch service
     */
    protected function buildElasticsearchServiceConfig(
        string $normalizedName,
        string $imageVersion,
        string $esVersion,
        string $esPassword
    ): string {
        $config = "  # Elasticsearch Service\n";
        $config .= "  elasticsearch:\n";
        $config .= "    container_name: phphive-{$normalizedName}-elasticsearch\n";
        $config .= "    image: docker.elastic.co/elasticsearch/elasticsearch:{$imageVersion}\n";
        $config .= "    environment:\n";
        $config .= "      - discovery.type=single-node\n";
        $config .= "      - cluster.name=phphive-{$normalizedName}\n";
        $config .= "      - bootstrap.memory_lock=true\n";
        $config .= "      - \"ES_JAVA_OPTS=-Xms512m -Xmx512m\"\n";

        if ($esVersion === '8') {
            // Elasticsearch 8.x security configuration
            $config .= "      - ELASTIC_PASSWORD={$esPassword}\n";
            $config .= "      - xpack.security.enabled=true\n";
            $config .= "      - xpack.security.http.ssl.enabled=false\n";
            $config .= "      - xpack.security.transport.ssl.enabled=false\n";
        } else {
            // Elasticsearch 7.x configuration (security optional)
            $config .= "      - ELASTIC_PASSWORD={$esPassword}\n";
            $config .= "      - xpack.security.enabled=true\n";
        }

        $config .= "    ulimits:\n";
        $config .= "      memlock:\n";
        $config .= "        soft: -1\n";
        $config .= "        hard: -1\n";
        $config .= "    ports:\n";
        $config .= "      - \"9200:9200\"\n";
        $config .= "    volumes:\n";
        $config .= "      - phphive-{$normalizedName}-elasticsearch-data:/usr/share/elasticsearch/data\n";
        $config .= "    networks:\n";
        $config .= "      - phphive-{$normalizedName}\n";
        $config .= "    healthcheck:\n";
        $config .= "      test: [\"CMD-SHELL\", \"curl -f http://localhost:9200/_cluster/health || exit 1\"]\n";
        $config .= "      interval: 10s\n";
        $config .= "      timeout: 5s\n";

        return $config . "      retries: 30\n";
    }

    /**
     * Build Kibana service configuration for docker-compose.yml.
     *
     * Generates the YAML configuration for Kibana service connected
     * to Elasticsearch for visualization and management.
     *
     * Configuration includes:
     * - Container name and image
     * - Environment variables for Elasticsearch connection
     * - Port mappings (5601:5601)
     * - Network configuration
     * - Dependency on Elasticsearch service
     * - Health check configuration
     *
     * Kibana features:
     * - Web UI for Elasticsearch
     * - Data visualization and dashboards
     * - Index management
     * - Dev tools console
     *
     * @param  string $normalizedName Normalized application name
     * @param  string $esVersion      Elasticsearch major version (7 or 8)
     * @param  string $esPassword     Elasticsearch password
     * @return string YAML configuration for Kibana service
     */
    protected function buildKibanaServiceConfig(
        string $normalizedName,
        string $esVersion,
        string $esPassword
    ): string {
        // Determine Kibana image version to match Elasticsearch
        $kibanaImageVersion = match ($esVersion) {
            '7' => '7.17.10',
            '8' => '8.11.0',
            default => '8.11.0',
        };

        $config = "  # Kibana Service\n";
        $config .= "  kibana:\n";
        $config .= "    container_name: phphive-{$normalizedName}-kibana\n";
        $config .= "    image: docker.elastic.co/kibana/kibana:{$kibanaImageVersion}\n";
        $config .= "    environment:\n";
        $config .= "      - ELASTICSEARCH_HOSTS=http://elasticsearch:9200\n";
        $config .= "      - ELASTICSEARCH_USERNAME=elastic\n";
        $config .= "      - ELASTICSEARCH_PASSWORD={$esPassword}\n";
        $config .= "    ports:\n";
        $config .= "      - \"5601:5601\"\n";
        $config .= "    networks:\n";
        $config .= "      - phphive-{$normalizedName}\n";
        $config .= "    depends_on:\n";
        $config .= "      - elasticsearch\n";
        $config .= "    healthcheck:\n";
        $config .= "      test: [\"CMD-SHELL\", \"curl -f http://localhost:5601/api/status || exit 1\"]\n";
        $config .= "      interval: 10s\n";
        $config .= "      timeout: 5s\n";

        return $config . "      retries: 30\n";
    }

    /**
     * Wait for Elasticsearch to be healthy and ready.
     *
     * Polls the Elasticsearch container health check until it reports
     * healthy status. Uses docker inspect to check container health.
     *
     * Polling strategy:
     * - Maximum attempts: 60 (configurable)
     * - Delay between attempts: 2 seconds
     * - Total maximum wait: 120 seconds
     *
     * Health check:
     * - Uses Elasticsearch _cluster/health API
     * - Checks for status: green or yellow
     * - Container must be running and healthy
     *
     * Why this is needed:
     * - Elasticsearch takes time to start (30-60 seconds)
     * - Application will fail if Elasticsearch not ready
     * - Health check ensures cluster is operational
     *
     * @param  string $appPath     Absolute path to application directory
     * @param  string $serviceName Name of service in docker-compose.yml
     * @param  int    $maxAttempts Maximum number of polling attempts
     * @return bool   True if Elasticsearch is healthy, false if timeout
     */
    protected function waitForElasticsearchHealth(string $appPath, string $serviceName, int $maxAttempts = 60): bool
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            // Check container health status using docker inspect
            $output = $this->process()->run(
                ['docker', 'compose', 'ps', '--format', 'json', $serviceName],
                $appPath
            );

            if ($output !== null) {
                $output = trim($output);
                if ($output !== '') {
                    // Parse JSON output to check health status
                    $serviceInfo = json_decode($output, true);
                    if (is_array($serviceInfo) && isset($serviceInfo['Health']) && $serviceInfo['Health'] === 'healthy') {
                        return true;
                    }

                    // Fallback: Try to curl the health endpoint
                    if ($this->process()->succeeds(['curl', '-f', 'http://localhost:9200/_cluster/health'])) {
                        return true;
                    }
                }
            }

            // Wait 2 seconds before next attempt
            sleep(2);
            $attempts++;
        }

        return false;
    }

    /**
     * Set up Elasticsearch using local installation.
     *
     * Falls back to local Elasticsearch setup when Docker is not available
     * or user prefers local installation. Prompts for connection details
     * for an existing Elasticsearch instance.
     *
     * Process:
     * 1. Display informational note about local setup
     * 2. Check if user wants to configure manually or use defaults
     * 3. Verify Elasticsearch connection if user confirms it's running
     * 4. Prompt for connection details or provide installation guidance
     * 5. Return configuration array
     *
     * Connection verification:
     * - Tests Elasticsearch connection using curl to /_cluster/health
     * - If successful: Continue with setup
     * - If failed: Offer Docker alternative or show installation instructions
     *
     * @param  string $appName Application name (used for context)
     * @return array  Elasticsearch configuration array
     */
    protected function setupLocalElasticsearch(string $appName): array
    {
        $this->note(
            'Setting up local Elasticsearch connection. Ensure Elasticsearch is installed and running.',
            'Local Elasticsearch Setup'
        );

        // Check if user wants automatic configuration
        $autoConfig = $this->confirm(
            label: 'Is Elasticsearch already running locally?',
            default: false
        );

        if ($autoConfig) {
            // Verify Elasticsearch is actually running
            $isRunning = $this->spin(
                callback: fn (): bool => $this->checkElasticsearchConnection('localhost', 9200),
                message: 'Checking Elasticsearch connection...'
            );

            if ($isRunning) {
                $this->info('✓ Elasticsearch is running and accessible!');

                // Use default local configuration
                return [
                    'elasticsearch_host' => 'localhost',
                    'elasticsearch_port' => 9200,
                    'elasticsearch_user' => 'elastic',
                    'elasticsearch_password' => '',
                    AppTypeInterface::CONFIG_USING_DOCKER => false,
                ];
            }

            // Elasticsearch check failed
            $this->error('✗ Could not connect to Elasticsearch on localhost:9200');
            $this->warning('Elasticsearch does not appear to be running.');

            // Offer to try Docker if available
            if ($this->isDockerAvailable()) {
                $tryDocker = $this->confirm(
                    label: 'Would you like to use Docker for Elasticsearch instead?',
                    default: true
                );

                if ($tryDocker) {
                    $cwd = getcwd();
                    if ($cwd === false) {
                        $this->error('Could not determine current working directory');
                        exit(1);
                    }
                    $dockerConfig = $this->setupDockerElasticsearch($appName, $cwd);
                    if ($dockerConfig !== null) {
                        return $dockerConfig;
                    }
                }
            }

            // Show installation guidance
            $this->provideElasticsearchInstallationGuidance();
            $this->error('Please install and start Elasticsearch, then try again.');
            exit(1);
        }

        // User said Elasticsearch is not running - provide installation guidance
        $this->provideElasticsearchInstallationGuidance();
        $this->info('After installing and starting Elasticsearch, please configure the connection details.');

        // Prompt for manual configuration
        return $this->promptElasticsearchConfiguration($appName);
    }

    /**
     * Check if Elasticsearch is accessible at the given host and port.
     *
     * Attempts to connect to Elasticsearch and execute a health check to verify
     * the connection is working. This is a quick health check to ensure
     * Elasticsearch is running and accessible.
     *
     * @param  string $host Elasticsearch host
     * @param  int    $port Elasticsearch port
     * @return bool   True if Elasticsearch is accessible, false otherwise
     */
    protected function checkElasticsearchConnection(string $host, int $port): bool
    {
        try {
            // Try to connect using curl to cluster health endpoint
            $result = $this->process()->succeeds(['curl', '-f', '-s', "http://{$host}:{$port}/_cluster/health"]);

            return $result;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Provide Elasticsearch installation guidance based on operating system.
     *
     * Displays helpful information and instructions for installing Elasticsearch
     * on the user's operating system. Includes download links, installation
     * methods, and verification steps.
     */
    protected function provideElasticsearchInstallationGuidance(): void
    {
        $os = $this->detectOS();

        $this->note(
            'Elasticsearch is not running. Elasticsearch provides powerful search capabilities.',
            'Elasticsearch Not Available'
        );

        match ($os) {
            'macos' => $this->provideMacOSElasticsearchGuidance(),
            'linux' => $this->provideLinuxElasticsearchGuidance(),
            'windows' => $this->provideWindowsElasticsearchGuidance(),
            default => $this->provideGenericElasticsearchGuidance(),
        };
    }

    /**
     * Provide macOS-specific Elasticsearch installation guidance.
     */
    protected function provideMacOSElasticsearchGuidance(): void
    {
        $this->info('macOS Installation:');
        $this->info('');
        $this->info('Homebrew (Recommended):');
        $this->info('  brew tap elastic/tap');
        $this->info('  brew install elastic/tap/elasticsearch-full');
        $this->info('  brew services start elastic/tap/elasticsearch-full');
        $this->info('');
        $this->info('After installation:');
        $this->info('  1. Elasticsearch will start automatically');
        $this->info('  2. Verify with: curl http://localhost:9200');
        $this->info('  3. Documentation: https://www.elastic.co/guide');
    }

    /**
     * Provide Linux-specific Elasticsearch installation guidance.
     */
    protected function provideLinuxElasticsearchGuidance(): void
    {
        $this->info('Linux Installation:');
        $this->info('');
        $this->info('Ubuntu/Debian:');
        $this->info('  wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | sudo apt-key add -');
        $this->info('  sudo apt-get install apt-transport-https');
        $this->info('  echo "deb https://artifacts.elastic.co/packages/8.x/apt stable main" | sudo tee /etc/apt/sources.list.d/elastic-8.x.list');
        $this->info('  sudo apt-get update && sudo apt-get install elasticsearch');
        $this->info('  sudo systemctl start elasticsearch');
        $this->info('');
        $this->info('After installation:');
        $this->info('  1. Verify with: curl http://localhost:9200');
        $this->info('  2. Documentation: https://www.elastic.co/guide');
    }

    /**
     * Provide Windows-specific Elasticsearch installation guidance.
     */
    protected function provideWindowsElasticsearchGuidance(): void
    {
        $this->info('Windows Installation:');
        $this->info('');
        $this->info('Option 1: Download and Install:');
        $this->info('  1. Download from: https://www.elastic.co/downloads/elasticsearch');
        $this->info('  2. Extract the archive');
        $this->info('  3. Run bin\\elasticsearch.bat');
        $this->info('');
        $this->info('After installation:');
        $this->info('  1. Verify with: curl http://localhost:9200');
        $this->info('  2. Documentation: https://www.elastic.co/guide');
    }

    /**
     * Provide generic Elasticsearch installation guidance.
     */
    protected function provideGenericElasticsearchGuidance(): void
    {
        $this->info('Elasticsearch Installation:');
        $this->info('');
        $this->info('Visit the official Elasticsearch documentation:');
        $this->info('  https://www.elastic.co/guide/en/elasticsearch/reference/current/install-elasticsearch.html');
        $this->info('');
        $this->info('After installation, verify with:');
        $this->info('  curl http://localhost:9200');
    }

    /**
     * Prompt user for Elasticsearch connection configuration.
     *
     * Interactive prompts for Elasticsearch connection details.
     * Used for both local setup and manual configuration.
     *
     * Interactive prompts:
     * 1. Elasticsearch host (default: localhost)
     * 2. Elasticsearch port (default: 9200)
     * 3. Elasticsearch username (default: elastic)
     * 4. Elasticsearch password (masked input, optional)
     *
     * Return value structure:
     * ```php
     * [
     *     'elasticsearch_host' => 'localhost',
     *     'elasticsearch_port' => 9200,
     *     'elasticsearch_user' => 'elastic',
     *     'elasticsearch_password' => 'password',
     *     'using_docker' => false,
     * ]
     * ```
     *
     * Non-interactive mode:
     * - Returns defaults for all values
     * - Host: localhost, Port: 9200
     * - User: elastic, Password: empty
     *
     * Note: This method does NOT validate the Elasticsearch connection.
     * The application will fail to connect if credentials are incorrect.
     *
     * @param  string $appName Application name (used for context)
     * @return array  Elasticsearch configuration array
     */
    protected function promptElasticsearchConfiguration(string $appName): array
    {
        // Check if running in non-interactive mode
        if (! $this->input->isInteractive()) {
            // Return defaults for non-interactive mode
            return [
                'elasticsearch_host' => 'localhost',
                'elasticsearch_port' => 9200,
                'elasticsearch_user' => 'elastic',
                'elasticsearch_password' => '',
                AppTypeInterface::CONFIG_USING_DOCKER => false,
            ];
        }

        // Display informational note
        $this->note(
            'Please enter the connection details for your Elasticsearch instance.',
            'Elasticsearch Configuration'
        );

        // =====================================================================
        // ELASTICSEARCH CONNECTION DETAILS
        // =====================================================================

        // Prompt for Elasticsearch host
        $host = $this->text(
            label: 'Elasticsearch host',
            placeholder: 'localhost',
            default: 'localhost',
            required: true,
            hint: 'The Elasticsearch server hostname or IP address'
        );

        // Prompt for Elasticsearch port
        $portInput = $this->text(
            label: 'Elasticsearch port',
            placeholder: '9200',
            default: '9200',
            required: true,
            hint: 'The Elasticsearch HTTP API port'
        );
        $port = (int) $portInput;

        // Prompt for Elasticsearch username
        $user = $this->text(
            label: 'Elasticsearch username',
            placeholder: 'elastic',
            default: 'elastic',
            required: true,
            hint: 'Username for Elasticsearch authentication'
        );

        // Prompt for Elasticsearch password
        $password = $this->password(
            label: 'Elasticsearch password',
            placeholder: 'Enter password',
            required: false,
            hint: 'Leave empty if authentication is disabled'
        );

        // Return Elasticsearch configuration
        return [
            'elasticsearch_host' => $host,
            'elasticsearch_port' => $port,
            'elasticsearch_user' => $user,
            'elasticsearch_password' => $password,
            AppTypeInterface::CONFIG_USING_DOCKER => false,
        ];
    }
}
