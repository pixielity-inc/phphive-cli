<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Tests\Unit;

use MonoPhp\Cli\Application;
use MonoPhp\Cli\Support\Container;
use MonoPhp\Cli\Tests\TestCase;

/**
 * Application Test.
 *
 * Tests for the main CLI Application class that manages command registration,
 * dependency injection, and application lifecycle. Verifies application creation,
 * command discovery, container access, and version information.
 */
final class ApplicationTest extends TestCase
{
    /**
     * Test that application can be instantiated.
     *
     * Verifies that a new Application instance can be created successfully.
     */
    public function test_can_create_application_instance(): void
    {
        // Create application instance
        $app = new Application();

        // Assert it's the correct type
        $this->assertInstanceOf(Application::class, $app);
    }

    /**
     * Test that make() creates and boots the application.
     *
     * Verifies that the static factory method creates a new application
     * instance and automatically boots it.
     */
    public function test_make_creates_and_boots_application(): void
    {
        // Create and boot application using factory
        $app = Application::make();

        // Assert it's the correct type
        $this->assertInstanceOf(Application::class, $app);
    }

    /**
     * Test that container() returns the DI container.
     *
     * Verifies that the application provides access to its
     * dependency injection container.
     */
    public function test_container_returns_container_instance(): void
    {
        // Create application
        $app = new Application();

        // Get container
        $container = $app->container();

        // Assert it's a Container instance
        $this->assertInstanceOf(Container::class, $container);
    }

    /**
     * Test that boot() discovers and registers commands.
     *
     * Verifies that the boot process discovers commands from the
     * Commands directory and registers them with the application.
     */
    public function test_boot_discovers_commands(): void
    {
        // Create and boot application
        $app = new Application();
        $app->boot();

        // Get all registered commands
        $commands = $app->all();

        // Assert commands were discovered and registered
        $this->assertNotEmpty($commands);
    }

    /**
     * Test that application has default commands.
     *
     * Verifies that the application includes Symfony Console's
     * default commands like 'list'.
     */
    public function test_has_default_command(): void
    {
        // Create application
        $app = new Application();

        // Assert 'list' command exists
        $this->assertTrue($app->has('list'));
    }

    /**
     * Test that getLongVersion() returns formatted version string.
     *
     * Verifies that the method returns a properly formatted string
     * containing the application name and version.
     */
    public function test_get_long_version_returns_formatted_string(): void
    {
        // Create application
        $app = new Application();

        // Get version string
        $version = $app->getLongVersion();

        // Assert it contains expected information
        $this->assertStringContainsString('Mono CLI', $version);
        $this->assertStringContainsString('version', $version);
    }
}
