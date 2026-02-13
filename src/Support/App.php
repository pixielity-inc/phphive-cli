<?php

declare(strict_types=1);

namespace PhpHive\Cli\Support;

use Closure;
use PhpHive\Cli\Application;

/**
 * Application Facade.
 *
 * Provides static access to the Application instance and its services.
 * This facade simplifies access to the application container and common
 * services throughout the codebase without needing to pass the application
 * instance around.
 *
 * The facade pattern allows for:
 * - Clean, readable static method calls
 * - Easy access to container services
 * - Simplified testing with mockable static methods
 * - Consistent API across the application
 *
 * Common use cases:
 * - Accessing the DI container: App::container()
 * - Resolving services: App::make(ServiceClass::class)
 * - Checking if service is bound: App::bound(ServiceClass::class)
 * - Getting application instance: App::getInstance()
 *
 * Example usage:
 * ```php
 * // Get the container
 * $container = App::container();
 *
 * // Resolve a service from the container
 * $filesystem = App::make(Filesystem::class);
 *
 * // Check if a service is registered
 * if (App::bound(Composer::class)) {
 *     $composer = App::make(Composer::class);
 * }
 *
 * // Get the application instance
 * $app = App::getInstance();
 * ```
 *
 * @see Application
 * @see Container
 */
final class App
{
    /**
     * The application instance.
     *
     * Stores the singleton Application instance that this facade provides
     * access to. Set via setInstance() during application bootstrap.
     */
    private static ?Application $application = null;

    /**
     * Handle dynamic static method calls.
     *
     * Delegates all undefined static method calls to the container instance.
     * This allows the facade to proxy any container method without explicitly
     * defining wrapper methods for each one.
     *
     * This enables calls like:
     * - App::resolved('ServiceClass') → delegates to container
     * - App::flush() → delegates to container
     * - App::forgetInstance('ServiceClass') → delegates to container
     * - Any other container method
     *
     * Example usage:
     * ```php
     * // Check if a service has been resolved
     * if (App::resolved(Filesystem::class)) {
     *     // Service was already resolved
     * }
     *
     * // Flush all container bindings
     * App::flush();
     *
     * // Forget a specific instance
     * App::forgetInstance(MyService::class);
     * ```
     *
     * @param  string       $method     The method name being called
     * @param  array<mixed> $parameters The parameters passed to the method
     * @return mixed        The result from the container method
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        // @phpstan-ignore-next-line Dynamic method call on container for facade pattern
        return self::container()->$method(...$parameters);
    }

    /**
     * Set the application instance.
     *
     * This method is called during application bootstrap to register the
     * Application instance with the facade. Once set, all static methods
     * on this facade will delegate to this instance.
     *
     * @param Application $application The application instance to register
     */
    public static function setInstance(Application $application): void
    {
        self::$application = $application;
    }

    /**
     * Get the application instance.
     *
     * Returns the registered Application instance. If no instance has been
     * set, creates a new one automatically.
     *
     * @return Application The application instance
     */
    public static function getInstance(): Application
    {
        if (! self::$application instanceof Application) {
            self::$application = Application::make();
        }

        return self::$application;
    }

    /**
     * Get the dependency injection container.
     *
     * Provides access to the application's DI container for service
     * resolution and dependency management.
     *
     * Example usage:
     * ```php
     * $container = App::container();
     * $container->singleton(MyService::class, fn() => new MyService());
     * ```
     *
     * @return Container The application's DI container
     */
    public static function container(): Container
    {
        return self::getInstance()->container();
    }

    /**
     * Resolve a service from the container.
     *
     * This is a convenience method that delegates to the container's make()
     * method. It resolves a service by its class name or binding key,
     * automatically injecting any dependencies.
     *
     * Example usage:
     * ```php
     * $filesystem = App::make(Filesystem::class);
     * $composer = App::make(Composer::class);
     * ```
     *
     * @template T of object
     *
     * @param  class-string<T> $abstract   The class name or binding key to resolve
     * @param  array<mixed>    $parameters Optional parameters to pass to the constructor
     * @return T               The resolved instance
     */
    public static function make(string $abstract, array $parameters = []): mixed
    {
        return self::container()->make($abstract, $parameters);
    }

    /**
     * Check if a service is bound in the container.
     *
     * Determines whether a service has been registered in the container,
     * either as a singleton, binding, or instance.
     *
     * Example usage:
     * ```php
     * if (App::bound(Composer::class)) {
     *     $composer = App::make(Composer::class);
     * }
     * ```
     *
     * @param  string $abstract The class name or binding key to check
     * @return bool   True if the service is bound, false otherwise
     */
    public static function bound(string $abstract): bool
    {
        return self::container()->bound($abstract);
    }

    /**
     * Register a singleton in the container.
     *
     * Registers a service as a singleton, ensuring only one instance exists
     * throughout the application lifecycle. The closure is called once on
     * first resolution, and the same instance is returned on subsequent calls.
     *
     * Example usage:
     * ```php
     * App::singleton(MyService::class, fn() => new MyService());
     * ```
     *
     * @param string                                   $abstract The class name or binding key
     * @param (Closure(Container): object)|string|null $concrete Optional closure that creates the instance
     */
    public static function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        self::container()->singleton($abstract, $concrete);
    }

    /**
     * Register a binding in the container.
     *
     * Registers a service binding that creates a new instance on each
     * resolution. Unlike singleton(), this creates a fresh instance every
     * time the service is resolved.
     *
     * Example usage:
     * ```php
     * App::bind(MyService::class, fn() => new MyService());
     * ```
     *
     * @param string                                   $abstract The class name or binding key
     * @param (Closure(Container): object)|string|null $concrete Optional closure that creates the instance
     */
    public static function bind(string $abstract, Closure|string|null $concrete = null): void
    {
        self::container()->bind($abstract, $concrete);
    }

    /**
     * Register an existing instance in the container.
     *
     * Registers an already-instantiated object as a singleton in the container.
     * This is useful when you have an instance created outside the container
     * that you want to make available to other services.
     *
     * Example usage:
     * ```php
     * $service = new MyService();
     * App::instance(MyService::class, $service);
     * ```
     *
     * @param string $abstract The class name or binding key
     * @param mixed  $instance The instance to register
     */
    public static function instance(string $abstract, mixed $instance): void
    {
        self::container()->instance($abstract, $instance);
    }

    /**
     * Get the application version.
     *
     * Returns the current version string of the application.
     *
     * @return string The application version (e.g., '1.0.19')
     */
    public static function version(): string
    {
        return self::getInstance()->getVersion();
    }

    /**
     * Get the application name.
     *
     * Returns the name of the application.
     *
     * @return string The application name (e.g., 'PhpHive CLI')
     */
    public static function name(): string
    {
        return self::getInstance()->getName();
    }
}
