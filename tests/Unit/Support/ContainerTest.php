<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Tests\Unit\Support;

use MonoPhp\Cli\Support\Container;
use MonoPhp\Cli\Tests\TestCase;
use stdClass;

/**
 * Container Test.
 *
 * Tests for the dependency injection container that extends Laravel's container.
 * Verifies singleton pattern, service binding, resolution, and instance management.
 */
final class ContainerTest extends TestCase
{
    /**
     * Test that getInstance returns the same singleton instance.
     *
     * Verifies the singleton pattern implementation by ensuring that multiple
     * calls to getInstance() return the exact same container instance.
     */
    public function test_get_instance_returns_singleton(): void
    {
        // Get two instances
        $instance1 = Container::getInstance();
        $instance2 = Container::getInstance();

        // Assert they are the same object
        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test that services can be bound and resolved from the container.
     *
     * Verifies basic service binding using a closure and resolution
     * through the make() method.
     */
    public function test_can_bind_and_resolve_services(): void
    {
        // Create a new container instance
        $container = new Container();

        // Bind a service with a closure
        $container->bind('test', fn () => 'test-value');

        // Resolve the service
        $result = $container->make('test');

        // Assert the resolved value matches expected
        $this->assertEquals('test-value', $result);
    }

    /**
     * Test that singleton bindings return the same instance.
     *
     * Verifies that services bound as singletons are only instantiated once
     * and subsequent resolutions return the same instance.
     */
    public function test_can_bind_singleton(): void
    {
        // Create a new container instance
        $container = new Container();
        $counter = 0;

        // Bind a singleton that increments a counter
        $container->singleton('counter', function () use (&$counter) {
            $counter++;

            return $counter;
        });

        // Resolve the singleton twice
        $first = $container->make('counter');
        $second = $container->make('counter');

        // Assert both resolutions return the same value (1)
        // proving the closure was only called once
        $this->assertEquals(1, $first);
        $this->assertEquals(1, $second);
    }

    /**
     * Test that existing instances can be bound to the container.
     *
     * Verifies that pre-existing object instances can be registered
     * in the container and resolved later.
     */
    public function test_can_bind_instance(): void
    {
        // Create a new container instance
        $container = new Container();

        // Create an object instance
        $object = new stdClass();
        $object->value = 'test';

        // Bind the instance to the container
        $container->instance('object', $object);

        // Resolve the instance
        $resolved = $container->make('object');

        // Assert the resolved object is the exact same instance
        $this->assertSame($object, $resolved);
    }
}
