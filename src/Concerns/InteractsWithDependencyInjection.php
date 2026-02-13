<?php

declare(strict_types=1);

namespace PhpHive\Cli\Concerns;

use PhpHive\Cli\Factories\AppTypeFactory;
use PhpHive\Cli\Factories\PackageTypeFactory;
use PhpHive\Cli\Services\Infrastructure\DatabaseSetupService;
use PhpHive\Cli\Services\Infrastructure\DockerComposeGenerator;
use PhpHive\Cli\Services\Infrastructure\MySQLService;
use PhpHive\Cli\Services\Infrastructure\QueueSetupService;
use PhpHive\Cli\Services\Infrastructure\RedisSetupService;
use PhpHive\Cli\Services\Infrastructure\SearchSetupService;
use PhpHive\Cli\Services\Infrastructure\StorageSetupService;
use PhpHive\Cli\Services\NameSuggestionService;
use PhpHive\Cli\Support\Composer;
use PhpHive\Cli\Support\Container;
use PhpHive\Cli\Support\Docker;
use PhpHive\Cli\Support\Filesystem;
use PhpHive\Cli\Support\PreflightChecker;
use PhpHive\Cli\Support\Process;
use PhpHive\Cli\Support\Reflection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;

/**
 * Provides dependency injection container management.
 *
 * This trait handles:
 * - Container initialization and access
 * - Core service registration
 * - Command container injection
 *
 * Service Categories:
 * - Core Services: Filesystem, Process, Reflection
 * - Development Tools: Composer, Docker
 * - Utilities: Finder, PreflightChecker, NameSuggestionService
 * - Factories: AppTypeFactory, PackageTypeFactory
 */
trait InteractsWithDependencyInjection
{
    /**
     * The dependency injection container.
     *
     * Provides service location and dependency resolution for commands
     * and other application components.
     */
    private Container $container;

    /**
     * Get the dependency injection container.
     *
     * Provides access to the application's DI container for service
     * resolution and dependency management.
     *
     * @return Container The application's DI container
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Initialize the dependency injection container.
     *
     * Creates a new container instance and registers all core services.
     */
    protected function initializeContainer(): void
    {
        $this->container = new Container();
        $this->registerServices();
    }

    /**
     * Inject container into command if supported.
     *
     * If the command has a setContainer() method, the application's
     * container is injected.
     *
     * @param Command $command The command instance to inject container into
     */
    protected function injectContainerIntoCommand(Command $command): void
    {
        if (Reflection::methodExists($command, 'setContainer')) {
            // @phpstan-ignore-next-line Method exists on BaseCommand but not on Symfony Command
            $command->setContainer($this->container);
        }
    }

    /**
     * Register core services in the dependency injection container.
     *
     * This method registers all core application services as singletons
     * in the container. Services registered here are available to all
     * commands via dependency injection.
     *
     * Service Categories:
     *
     * Core Services:
     * - Filesystem: File and directory operations
     * - Process: Shell command execution with Symfony Process
     * - Reflection: PHP reflection utilities
     *
     * Development Tools:
     * - Composer: Composer dependency management operations
     * - Docker: Docker and Docker Compose container operations
     *
     * Utilities:
     * - Finder: Symfony Finder for file/directory discovery
     * - PreflightChecker: Environment validation before operations
     * - NameSuggestionService: Name suggestion when conflicts occur
     *
     * Factories:
     * - AppTypeFactory: Creates application type instances
     * - PackageTypeFactory: Creates package type instances
     *
     * All services are registered as singletons to ensure a single instance
     * is shared across the application lifecycle, improving performance and
     * maintaining consistent state.
     */
    private function registerServices(): void
    {
        // =====================================================================
        // CORE SERVICES
        // =====================================================================

        // Filesystem service for file and directory operations
        $this->container->singleton(
            Filesystem::class,
            Filesystem::make(...)
        );

        // Process service for executing shell commands
        $this->container->singleton(
            Process::class,
            Process::make(...)
        );

        // Reflection utilities for PHP introspection
        $this->container->singleton(
            Reflection::class,
            fn (): Reflection => new Reflection()
        );

        // =====================================================================
        // DEVELOPMENT TOOLS
        // =====================================================================

        // Composer service for dependency management
        $this->container->singleton(
            Composer::class,
            Composer::make(...)
        );

        // Docker service for container operations
        $this->container->singleton(
            Docker::class,
            Docker::make(...)
        );

        // =====================================================================
        // UTILITIES
        // =====================================================================

        // Symfony Finder for file/directory discovery
        $this->container->singleton(
            Finder::class,
            fn (): Finder => new Finder()
        );

        // Preflight checker for environment validation
        $this->container->singleton(
            PreflightChecker::class,
            fn (Container $container): PreflightChecker => PreflightChecker::make(
                $container->make(Process::class)
            )
        );

        // Name suggestion service for conflict resolution
        $this->container->singleton(
            NameSuggestionService::class,
            NameSuggestionService::make(...)
        );

        // =====================================================================
        // INFRASTRUCTURE SERVICES
        // =====================================================================

        // MySQL service for MySQL database operations
        $this->container->singleton(
            MySQLService::class,
            MySQLService::make(...)
        );

        // Docker Compose generator for docker-compose.yml generation
        $this->container->singleton(
            DockerComposeGenerator::class,
            DockerComposeGenerator::make(...)
        );

        // Database setup service for database infrastructure management
        $this->container->singleton(
            DatabaseSetupService::class,
            DatabaseSetupService::make(...)
        );

        // Redis setup service for Redis infrastructure management
        $this->container->singleton(
            RedisSetupService::class,
            fn (Container $container): RedisSetupService => RedisSetupService::make(
                $container->make(Process::class)
            )
        );

        // Storage setup service for object storage management (MinIO, S3)
        $this->container->singleton(
            StorageSetupService::class,
            fn (Container $container): StorageSetupService => StorageSetupService::make(
                $container->make(Process::class)
            )
        );

        // Search setup service for search engine configuration
        $this->container->singleton(
            SearchSetupService::class,
            fn (Container $container): SearchSetupService => SearchSetupService::make(
                $container->make(Docker::class),
                $container->make(Process::class),
                $container->make(Filesystem::class)
            )
        );

        // Queue setup service for queue infrastructure management
        $this->container->singleton(
            QueueSetupService::class,
            fn (Container $container): QueueSetupService => QueueSetupService::make(
                $container->make(Process::class),
                $container->make(Filesystem::class)
            )
        );

        // =====================================================================
        // FACTORIES
        // =====================================================================

        // App type factory for creating application instances
        $this->container->singleton(
            AppTypeFactory::class,
            fn (Container $container): AppTypeFactory => new AppTypeFactory($container)
        );

        // Package type factory for creating package instances
        $this->container->singleton(
            PackageTypeFactory::class,
            fn (Container $container): PackageTypeFactory => new PackageTypeFactory(
                $container->make(Composer::class)
            )
        );
    }
}
