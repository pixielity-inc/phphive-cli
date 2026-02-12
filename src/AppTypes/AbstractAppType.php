<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes;

use PhpHive\Cli\Concerns\InteractsWithDatabase;
use PhpHive\Cli\Concerns\InteractsWithDocker;
use PhpHive\Cli\Concerns\InteractsWithElasticsearch;
use PhpHive\Cli\Concerns\InteractsWithMeilisearch;
use PhpHive\Cli\Concerns\InteractsWithMinio;
use PhpHive\Cli\Concerns\InteractsWithPrompts;
use PhpHive\Cli\Concerns\InteractsWithQueue;
use PhpHive\Cli\Concerns\InteractsWithRedis;
use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\Support\Composer;
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
 * - Provide Filesystem abstraction for file operations
 *
 * File Operations:
 * All file operations should use the Filesystem class via $this->filesystem()
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
 *         if ($this->filesystem()->exists('/path/to/config')) {
 *             $config = $this->filesystem()->read('/path/to/config');
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
    use InteractsWithMinio;
    use InteractsWithPrompts;
    use InteractsWithQueue;
    use InteractsWithRedis;

    /**
     * Filesystem instance for file operations.
     *
     * Lazy-loaded instance of the Filesystem class used for all file operations
     * within AppType classes. This provides a consistent, testable interface for
     * file system interactions instead of using raw PHP file_* functions.
     *
     * @see Filesystem
     */
    protected Filesystem $filesystem;

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
     * Get or create the Filesystem instance.
     *
     * Returns a lazy-loaded Filesystem instance for performing file operations.
     * The instance is created on first access and reused for subsequent calls.
     *
     * This method provides a consistent interface for file operations across all
     * AppType classes, replacing raw PHP file_* functions with a testable,
     * object-oriented API.
     *
     * Example usage:
     * ```php
     * // Check if file exists
     * if ($this->filesystem()->exists('/path/to/file')) {
     *     // Read file contents
     *     $content = $this->filesystem()->read('/path/to/file');
     * }
     *
     * // Write file contents
     * $this->filesystem()->write('/path/to/file', 'content');
     * ```
     *
     * @return Filesystem The Filesystem instance for file operations
     */
    protected function filesystem(): Filesystem
    {
        if (! isset($this->filesystem)) {
            $this->filesystem = new Filesystem();
        }

        return $this->filesystem;
    }

    /**
     * Get the base stub directory path.
     *
     * Returns the absolute path to the root stubs directory where all
     * application type stub templates are stored. This directory contains
     * subdirectories for each app type (laravel-app, symfony-app, etc.).
     *
     * The path is calculated relative to this file's location:
     * - Current file: cli/src/AppTypes/AbstractAppType.php
     * - Stubs directory: cli/stubs/
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

    /**
     * Get the Composer service instance.
     *
     * Returns a Composer service instance for performing composer operations.
     * This method creates a new instance each time it's called.
     *
     * Example usage:
     * ```php
     * // Install dependencies
     * $this->composerService()->install('/path/to/project');
     *
     * // Require a package
     * $this->composerService()->require('/path/to/project', 'symfony/console');
     * ```
     *
     * @return Composer The Composer service instance
     */
    protected function composerService(): Composer
    {
        return Composer::make();
    }

    /**
     * Get the Process service instance.
     *
     * Returns a Process service instance for executing shell commands.
     * This method creates a new instance each time it's called.
     *
     * Example usage:
     * ```php
     * // Run a command
     * $result = $this->process()->run(['php', '--version']);
     *
     * // Check if command succeeds
     * if ($this->process()->succeeds(['which', 'docker'])) {
     *     // Docker is available
     * }
     * ```
     *
     * @return Process The Process service instance
     */
    protected function process(): Process
    {
        return Process::make();
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
     * - databases (array): Supported database types (default: ['mysql', 'postgresql'])
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
     *         'databases' => ['mysql', 'postgresql', 'sqlite'],
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
    protected function setupInfrastructure(
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
                $options['databases'] ?? ['mysql', 'postgresql'],
                $appPath
            );
            $config = array_merge($config, $dbConfig);
            $this->line('');
        }

        // 2. Cache Setup (Redis)
        if ($options['needsCache'] ?? true) {
            if ($this->confirm('Install Redis for caching/sessions?', true)) {
                $this->info('🔄 Redis Configuration');
                $redisConfig = $this->setupRedis($appName, $appPath);
                $config = array_merge($config, $redisConfig);
                $this->line('');
            }
        }

        // 3. Queue Setup (Redis/RabbitMQ/SQS)
        if ($options['needsQueue'] ?? true) {
            if ($this->confirm('Configure message queue?', false)) {
                $this->info('📨 Queue Configuration');
                $queueConfig = $this->setupQueue($appName, $appPath);
                $config = array_merge($config, $queueConfig);
                $this->line('');
            }
        }

        // 4. Search Engine Setup (Meilisearch/Elasticsearch/OpenSearch)
        if ($options['needsSearch'] ?? true) {
            $searchEngine = $this->select(
                label: 'Search engine',
                options: [
                    'none' => 'None',
                    'meilisearch' => 'Meilisearch (Fast, typo-tolerant)',
                    'elasticsearch' => 'Elasticsearch (Full-featured, self-hosted)',
                    'opensearch' => 'AWS OpenSearch (Managed cloud service)',
                ],
                default: 'none'
            );

            if ($searchEngine !== 'none') {
                $this->info('🔍 ' . ucfirst($searchEngine) . ' Configuration');

                if ($searchEngine === 'opensearch') {
                    $searchConfig = $this->setupOpenSearch($appName);
                } else {
                    $setupMethod = 'setup' . ucfirst($searchEngine);
                    $searchConfig = $this->$setupMethod($appName, $appPath);
                }

                $config = array_merge($config, $searchConfig);
                $this->line('');
            }
        }

        // 5. Object Storage Setup (Minio)
        if ($options['needsStorage'] ?? false) {
            if ($this->confirm('Install Minio for object storage (S3-compatible)?', false)) {
                $this->info('💾 Minio Configuration');
                $minioConfig = $this->setupMinio($appName, $appPath);
                $config = array_merge($config, $minioConfig);
                $this->line('');
            }
        }

        if ($config !== []) {
            $this->info('✓ Infrastructure setup complete!');
            $this->line('');
        }

        return $config;
    }
}
