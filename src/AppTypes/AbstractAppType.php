<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes;

use PhpHive\Cli\Concerns\InteractsWithDatabase;
use PhpHive\Cli\Concerns\InteractsWithDocker;
use PhpHive\Cli\Concerns\InteractsWithElasticsearch;
use PhpHive\Cli\Concerns\InteractsWithMeilisearch;
use PhpHive\Cli\Concerns\InteractsWithPrompts;
use PhpHive\Cli\Concerns\InteractsWithQueue;
use PhpHive\Cli\Concerns\InteractsWithRedis;
use PhpHive\Cli\Concerns\InteractsWithStorage;
use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\Enums\DatabaseType;
use PhpHive\Cli\Enums\SearchEngine;
use PhpHive\Cli\Services\Infrastructure\DatabaseSetupService;
use PhpHive\Cli\Services\Infrastructure\QueueSetupService;
use PhpHive\Cli\Services\Infrastructure\RedisSetupService;
use PhpHive\Cli\Services\Infrastructure\SearchSetupService;
use PhpHive\Cli\Services\Infrastructure\StorageSetupService;
use PhpHive\Cli\Support\Composer;
use PhpHive\Cli\Support\Container;
use PhpHive\Cli\Support\Filesystem;
use PhpHive\Cli\Support\Process;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract Base Class for Application Types.
 *
 * This abstract class provides common functionality and helper methods for all
 * application type implementations. It serves as the foundation for creating
 * different types of applications (Laravel, Symfony, Skeleton, etc.) within
 * the monorepo.
 *
 * Key responsibilities:
 * - Provide helper methods for interactive prompts (text, confirm, select)
 * - Handle name normalization and namespace generation
 * - Manage stub paths and variable replacement
 * - Define common configuration patterns
 * - Provide Filesystem, Process, and Composer services via constructor injection
 *
 * File Operations:
 * All file operations should use the Filesystem class via $this->filesystem
 * instead of raw PHP file_* functions. This provides better error handling,
 * testability, and consistency across the codebase.
 *
 * Each concrete app type (LaravelAppType, SymfonyAppType, etc.) extends this
 * class and implements the AppTypeInterface methods to define its specific
 * scaffolding behavior.
 *
 * Example usage:
 * ```php
 * class MyAppType extends AbstractAppType {
 *     public function collectConfiguration(...) {
 *         $name = $this->text('App name', 'my-app');
 *         $useDb = $this->confirm('Use database?');
 *
 *         // Use Filesystem for file operations
 *         if ($this->filesystem->exists('/path/to/config')) {
 *             $config = $this->filesystem->read('/path/to/config');
 *         }
 *
 *         return compact('name', 'useDb');
 *     }
 * }
 * ```
 *
 * @see AppTypeInterface
 * @see Filesystem
 */
abstract class AbstractAppType implements AppTypeInterface
{
    use InteractsWithDatabase;
    use InteractsWithDocker;
    use InteractsWithElasticsearch;
    use InteractsWithMeilisearch;
    use InteractsWithPrompts;
    use InteractsWithQueue;
    use InteractsWithRedis;
    use InteractsWithStorage;

    /**
     * Symfony Console input interface.
     *
     * Provides access to command-line arguments and options during the
     * configuration collection process. Set by concrete implementations
     * in their collectConfiguration() method.
     */
    protected InputInterface $input;

    /**
     * Symfony Console output interface.
     *
     * Provides access to console output for displaying messages, progress,
     * and other information during the scaffolding process. Set by concrete
     * implementations in their collectConfiguration() method.
     */
    protected OutputInterface $output;

    /**
     * Create a new AppType instance with dependencies.
     *
     * All dependencies are injected through the constructor to enable
     * proper dependency injection and improve testability.
     *
     * @param Filesystem $filesystem File system operations service
     * @param Process    $process    Shell command execution service
     * @param Composer   $composer   Composer operations service
     * @param Container  $container  Dependency injection container
     */
    public function __construct(
        protected readonly Filesystem $filesystem,
        protected readonly Process $process,
        protected readonly Composer $composer,
        protected readonly Container $container,
    ) {}

    /**
     * Get commands to run before installation.
     *
     * Returns an array of shell commands to execute before the main installation
     * command. By default, returns an empty array. Concrete app types can override
     * this method to provide pre-installation setup commands.
     *
     * Example use cases:
     * - Configure authentication (e.g., Magento repository credentials)
     * - Set up environment variables
     * - Create configuration files
     * - Install system dependencies
     *
     * @param  array<string, mixed> $config Configuration from collectConfiguration()
     * @return array<string>        Array of shell commands to execute sequentially
     */
    public function getPreInstallCommands(array $config): array
    {
        return [];
    }

    /**
     * Collect configuration from user input.
     *
     * This base implementation handles common configuration that all app types need:
     * - Name (skipped if already provided via command argument)
     * - Description (skipped if already provided via --description option)
     *
     * Child classes should override this method and call parent::collectConfiguration()
     * first, then add their specific configuration options.
     *
     * Example in child class:
     * ```php
     * public function collectConfiguration(InputInterface $input, OutputInterface $output): array
     * {
     *     $config = parent::collectConfiguration($input, $output);
     *
     *     // Add framework-specific configuration
     *     $config['php_version'] = $this->select(...);
     *
     *     return $config;
     * }
     * ```
     *
     * @param  InputInterface       $input  Command input
     * @param  OutputInterface      $output Command output
     * @return array<string, mixed> Configuration array
     */
    public function collectConfiguration(InputInterface $input, OutputInterface $output): array
    {
        // Store input/output for use in helper methods
        $this->input = $input;
        $this->output = $output;

        $config = [];

        // Name is handled by CreateAppCommand, so we don't prompt for it here
        // It will be set after collectConfiguration() returns

        // Description - only prompt if not provided via --description option
        $descriptionOption = $input->getOption('description');
        if ($descriptionOption !== null && $descriptionOption !== '') {
            $config[AppTypeInterface::CONFIG_DESCRIPTION] = $descriptionOption;
        }
        // If description not provided, it will be set by CreateAppCommand with a default

        return $config;
    }

    /**
     * Set up infrastructure services with Docker-first approach.
     *
     * This method orchestrates the setup of all infrastructure services
     * (database, cache, queue, search, storage) in a unified, consistent way.
     * It provides a Docker-first approach with graceful fallbacks to local setups.
     *
     * All services are optional and can be configured through the $options array.
     * Services are set up in order: Database → Cache → Queue → Search → Storage
     *
     * Options:
     * - needsDatabase (bool): Whether database is required (default: true)
     * - databases (array<DatabaseType>): Supported database types (default: [DatabaseType::MYSQL, DatabaseType::POSTGRESQL])
     * - needsCache (bool): Whether to prompt for cache (default: true)
     * - needsQueue (bool): Whether to prompt for queue (default: true)
     * - needsSearch (bool): Whether to prompt for search (default: true)
     * - needsStorage (bool): Whether to prompt for storage (default: false)
     *
     * Example usage:
     * ```php
     * $infraConfig = $this->setupInfrastructure(
     *     'my-app',
     *     '/path/to/app',
     *     [
     *         'needsDatabase' => true,
     *         'databases' => [DatabaseType::MYSQL, DatabaseType::POSTGRESQL, DatabaseType::SQLITE],
     *         'needsCache' => true,
     *         'needsQueue' => true,
     *         'needsSearch' => true,
     *         'needsStorage' => false,
     *     ]
     * );
     * ```
     *
     * Return value structure:
     * ```php
     * [
     *     // Database
     *     'db_type' => 'mysql',
     *     'db_host' => 'localhost',
     *     'db_port' => 3306,
     *     'db_name' => 'my_app',
     *     'db_user' => 'my_app_user',
     *     'db_password' => '********',
     *     'using_docker' => true,
     *
     *     // Cache (Redis)
     *     'redis_host' => 'localhost',
     *     'redis_port' => 6379,
     *     'redis_password' => '********',
     *
     *     // Queue
     *     'queue_driver' => 'rabbitmq',
     *     'queue_host' => 'localhost',
     *     'queue_port' => 5672,
     *
     *     // Search
     *     'search_engine' => 'opensearch',
     *     'opensearch_endpoint' => 'search-domain.us-east-1.es.amazonaws.com',
     *     'opensearch_region' => 'us-east-1',
     *
     *     // Storage
     *     'minio_endpoint' => 'localhost',
     *     'minio_port' => 9000,
     * ]
     * ```
     *
     * @param  string $appName Application name for defaults
     * @param  string $appPath Absolute path to application directory
     * @param  array  $options Configuration options
     * @return array  Infrastructure configuration array
     */
    public function setupInfrastructure(
        string $appName,
        string $appPath,
        array $options = []
    ): array {
        $config = [];

        $this->line('');
        $this->comment('Infrastructure Setup:');
        $this->line('');

        // 1. Database Setup (Docker-first)
        if ($options['needsDatabase'] ?? true) {
            $this->info('📦 Database Configuration');
            $dbConfig = $this->setupDatabase(
                $appName,
                $options['databases'] ?? [DatabaseType::MYSQL, DatabaseType::POSTGRESQL],
                $appPath
            );
            $config = array_merge($config, $dbConfig);
            $this->line('');
        }

        // 2. Cache Setup (Redis)
        if (($options['needsCache'] ?? true) && $this->confirm('Install Redis for caching/sessions?', true)) {
            $this->info('🔄 Redis Configuration');
            $redisConfig = $this->setupRedis($appName, $appPath);
            $config = array_merge($config, $redisConfig);
            $this->line('');
        }

        // 3. Queue Setup (Redis/RabbitMQ/SQS)
        if (($options['needsQueue'] ?? true) && $this->confirm('Configure message queue?', false)) {
            $this->info('📨 Queue Configuration');
            $queueConfig = $this->setupQueue($appName, $appPath);
            $config = array_merge($config, $queueConfig);
            $this->line('');
        }

        // 4. Search Engine Setup (Meilisearch/Elasticsearch/OpenSearch)
        if ($options['needsSearch'] ?? true) {
            $searchEngine = $this->select(
                label: 'Search engine',
                options: SearchEngine::choices(),
                default: SearchEngine::NONE->value
            );

            if ($searchEngine !== SearchEngine::NONE->value) {
                $searchEngineEnum = SearchEngine::from($searchEngine);
                $this->info('🔍 ' . $searchEngineEnum->getName() . ' Configuration');

                $searchConfig = match ($searchEngine) {
                    SearchEngine::OPENSEARCH->value => $this->setupOpenSearch($appName),
                    SearchEngine::MEILISEARCH->value => $this->setupMeilisearch($appName, $appPath),
                    SearchEngine::ELASTICSEARCH->value => $this->setupElasticsearch($appName, $appPath),
                    default => [],
                };

                $config = array_merge($config, $searchConfig);
                $this->line('');
            }
        }

        // 5. Object Storage Setup (MinIO/S3)
        if (($options['needsStorage'] ?? false) && $this->confirm('Install object storage (MinIO/S3)?', false)) {
            $this->info('💾 Storage Configuration');
            $storageConfig = $this->setupStorage($appName, $appPath);
            $config = array_merge($config, $storageConfig);
            $this->line('');
        }

        if ($config !== []) {
            $this->info('✓ Infrastructure setup complete!');
            $this->line('');
        }

        return $config;
    }

    /**
     * Get the Filesystem service instance.
     *
     * Returns the injected Filesystem instance for file operations.
     * This method satisfies the abstract method requirement from traits.
     *
     * @return Filesystem The Filesystem service instance
     */
    protected function filesystem(): Filesystem
    {
        return $this->filesystem;
    }

    /**
     * Get the Process service instance.
     *
     * Returns the injected Process instance for executing shell commands.
     * This method satisfies the abstract method requirement from traits.
     *
     * @return Process The Process service instance
     */
    protected function process(): Process
    {
        return $this->process;
    }

    /**
     * Get the Composer service instance.
     *
     * Returns the injected Composer instance for composer operations.
     *
     * @return Composer The Composer service instance
     */
    protected function composer(): Composer
    {
        return $this->composer;
    }

    /**
     * Get the DatabaseSetupService instance.
     *
     * @return DatabaseSetupService The database setup service instance
     */
    protected function databaseSetupService(): DatabaseSetupService
    {
        return $this->container->make(DatabaseSetupService::class);
    }

    /**
     * Get the RedisSetupService instance.
     *
     * @return RedisSetupService The Redis setup service instance
     */
    protected function redisSetupService(): RedisSetupService
    {
        return $this->container->make(RedisSetupService::class);
    }

    /**
     * Get the StorageSetupService instance.
     *
     * @return StorageSetupService The storage setup service instance
     */
    protected function storageSetupService(): StorageSetupService
    {
        return $this->container->make(StorageSetupService::class);
    }

    /**
     * Get the SearchSetupService instance.
     *
     * @return SearchSetupService The search setup service instance
     */
    protected function searchSetupService(): SearchSetupService
    {
        return $this->container->make(SearchSetupService::class);
    }

    /**
     * Get the QueueSetupService instance.
     *
     * @return QueueSetupService The queue setup service instance
     */
    protected function queueSetupService(): QueueSetupService
    {
        return $this->container->make(QueueSetupService::class);
    }

    /**
     * Set up infrastructure services with Docker-first approach.
     *
     * Returns the absolute path to the root stubs directory where all
     * application type stub templates are stored. This directory contains
     * subdirectories for each app type (laravel-app, symfony-app, etc.).
     *
     * The path is calculated relative to this file's location:
     * - Current file: cli/src/AppTypes/AbstractAppType.php
     * - Stubs directory: cli/stubs/
     *
     * This path is used with Pixielity\StubGenerator\Facades\Stub::setBasePath()
     * to configure the base directory for stub template resolution.
     *
     * @return string Absolute path to the stubs directory
     */
    protected function getBaseStubPath(): string
    {
        // Go up two directories from src/AppTypes to reach cli root, then into stubs
        return dirname(__DIR__, 2) . '/stubs';
    }

    /**
     * Normalize application name to valid directory/package name.
     *
     * Converts an application name into a format suitable for:
     * - Directory names (lowercase, hyphen-separated)
     * - Composer package names (lowercase, hyphen-separated)
     * - URL slugs
     *
     * Transformation rules:
     * - Converts to lowercase
     * - Replaces non-alphanumeric characters (except hyphens) with hyphens
     * - Preserves existing hyphens
     *
     * Examples:
     * - "My Awesome App" → "my-awesome-app"
     * - "API_Gateway" → "api-gateway"
     * - "user@service" → "user-service"
     *
     * @param  string $name The original application name
     * @return string The normalized name suitable for directories and packages
     */
    protected function normalizeAppName(string $name): string
    {
        // Replace any non-alphanumeric character (except hyphens) with a hyphen
        // Then convert to lowercase for consistency
        return strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $name) ?? $name);
    }

    /**
     * Convert application name to PHP namespace format.
     *
     * Transforms an application name into a valid PHP namespace component
     * following PSR-4 naming conventions.
     *
     * Transformation rules:
     * - Removes hyphens and underscores
     * - Capitalizes first letter of each word
     * - Results in PascalCase format
     *
     * Examples:
     * - "my-app" → "MyApp"
     * - "api_gateway" → "ApiGateway"
     * - "user-service" → "UserService"
     *
     * The resulting namespace component can be used in:
     * - PSR-4 autoload configuration
     * - Class namespaces
     * - Package namespaces
     *
     * @param  string $name The application name (can contain hyphens/underscores)
     * @return string The PascalCase namespace component
     */
    protected function nameToNamespace(string $name): string
    {
        // Capitalize first letter of each word (delimited by - or _)
        // Then remove the delimiters to create PascalCase
        return str_replace(['-', '_'], '', ucwords($name, '-_'));
    }

    /**
     * Get common stub template variables.
     *
     * Generates a standard set of variables used for replacing placeholders
     * in stub template files. These variables are common across all app types
     * and provide basic application metadata.
     *
     * Generated variables:
     * - {{APP_NAME}}: Original application name as entered by user
     * - {{APP_NAME_NORMALIZED}}: Normalized name for directories/packages
     * - {{APP_NAMESPACE}}: PascalCase namespace component
     * - {{PACKAGE_NAME}}: Full Composer package name (phphive/app-name)
     * - {{DESCRIPTION}}: Application description or default
     *
     * Example usage in stub files:
     * ```json
     * {
     *   "name": "{{PACKAGE_NAME}}",
     *   "description": "{{DESCRIPTION}}",
     *   "autoload": {
     *     "psr-4": {
     *       "PhpHive\\{{APP_NAMESPACE}}\\": "src/"
     *     }
     *   }
     * }
     * ```
     *
     * Concrete app types can extend this array with their own specific
     * variables by calling parent::getCommonStubVariables() and merging
     * additional variables.
     *
     * @param  array<string, mixed>  $config Configuration array from collectConfiguration()
     * @return array<string, string> Associative array of placeholder => value pairs
     */
    protected function getCommonStubVariables(array $config): array
    {
        // Extract app name from config, default to 'app' if not provided
        $appName = $config[AppTypeInterface::CONFIG_NAME] ?? 'app';

        // Normalize the name for use in directories and package names
        $normalizedName = $this->normalizeAppName($appName);

        return [
            // Original name as entered by user
            AppTypeInterface::STUB_APP_NAME => $appName,
            // Normalized name for directories and package names (lowercase, hyphenated)
            AppTypeInterface::STUB_APP_NAME_NORMALIZED => $normalizedName,
            // PascalCase namespace component for PHP classes
            AppTypeInterface::STUB_APP_NAMESPACE => $this->nameToNamespace($appName),
            // Full Composer package name following phphive/* convention
            AppTypeInterface::STUB_PACKAGE_NAME => "phphive/{$normalizedName}",
            // Application description from config or generated default
            AppTypeInterface::STUB_DESCRIPTION => $config[AppTypeInterface::CONFIG_DESCRIPTION] ?? "Application: {$appName}",
        ];
    }
}
