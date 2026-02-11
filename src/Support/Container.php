<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Support;

use Illuminate\Container\Container as IlluminateContainer;
use Override;

/**
 * Dependency Injection Container.
 *
 * Extends Laravel's container to provide dependency injection and service
 * location for the CLI application. This container manages all application
 * services and their dependencies.
 *
 * The container is used throughout the application to:
 * - Register services and their bindings
 * - Resolve dependencies automatically
 * - Manage singleton instances
 * - Provide service location for commands
 *
 * Example usage:
 * ```php
 * // Using static factory
 * $container = Container::make();
 *
 * // Using singleton instance
 * $container = Container::getInstance();
 *
 * // Registering services
 * $container->singleton(Workspace::class, fn() => Workspace::make(getcwd()));
 * $workspace = $container->make(Workspace::class);
 * ```
 */
final class Container extends IlluminateContainer
{
    /**
     * Get the globally available singleton instance of the container.
     *
     * This method implements the Singleton pattern, ensuring only one
     * container instance exists throughout the application lifecycle.
     * The instance is stored statically and reused on subsequent calls.
     *
     * Use this for the main application container to ensure all services
     * are registered in the same container instance.
     *
     * @return static The global container singleton instance
     */
    #[Override]
    public static function getInstance(): static
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
