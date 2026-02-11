<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Tests\Unit\Support;

use MonoPhp\Cli\Support\Reflection;
use MonoPhp\Cli\Tests\TestCase;

/**
 * Reflection Test.
 *
 * Tests for the Reflection utility class that provides convenient wrappers
 * around PHP's reflection API. Verifies class inspection, method detection,
 * property access, and metadata extraction.
 */
final class ReflectionTest extends TestCase
{
    /**
     * Test that exists() correctly checks if a class exists.
     *
     * Verifies that the method returns true for existing classes
     * and false for non-existent classes.
     */
    public function test_exists_checks_class_existence(): void
    {
        // Assert existing class returns true
        $this->assertTrue(Reflection::exists(TestCase::class));

        // Assert non-existent class returns false
        $this->assertFalse(Reflection::exists('NonExistentClass'));
    }

    /**
     * Test that methodExists() correctly checks if a method exists.
     *
     * Verifies method existence checking on both class names and object instances.
     */
    public function test_method_exists_checks_method_existence(): void
    {
        // Assert existing method returns true
        $this->assertTrue(Reflection::methodExists($this, 'setUp'));

        // Assert non-existent method returns false
        $this->assertFalse(Reflection::methodExists($this, 'nonExistentMethod'));
    }

    /**
     * Test that propertyExists() correctly checks if a property exists.
     *
     * Verifies property existence checking on object instances.
     */
    public function test_property_exists_checks_property_existence(): void
    {
        // Create an anonymous class with a test property
        $obj = new class()
        {
            public string $testProperty = 'value';
        };

        // Assert existing property returns true
        $this->assertTrue(Reflection::propertyExists($obj, 'testProperty'));

        // Assert non-existent property returns false
        $this->assertFalse(Reflection::propertyExists($obj, 'nonExistentProperty'));
    }

    /**
     * Test that getClassName() returns the fully qualified class name.
     *
     * Verifies that the method returns the complete namespace and class name.
     */
    public function test_get_class_name_returns_full_class_name(): void
    {
        // Get the class name
        $className = Reflection::getClassName($this);

        // Assert it matches the fully qualified class name
        $this->assertEquals(self::class, $className);
    }

    /**
     * Test that getClassShortName() returns only the class name without namespace.
     *
     * Verifies that the method strips the namespace and returns only the class name.
     */
    public function test_get_class_short_name_returns_short_name(): void
    {
        // Get the short class name
        $shortName = Reflection::getClassShortName($this);

        // Assert it matches just the class name without namespace
        $this->assertEquals('ReflectionTest', $shortName);
    }

    /**
     * Test that isAbstract() correctly identifies abstract classes.
     *
     * Verifies that the method returns false for concrete classes.
     */
    public function test_is_abstract_checks_if_class_is_abstract(): void
    {
        // Create a concrete anonymous class
        $abstractClass = new class() {};

        // Assert it's not abstract
        $this->assertFalse(Reflection::isAbstract($abstractClass));
    }

    /**
     * Test that isFinal() correctly identifies final classes.
     *
     * Verifies that the method returns true for final classes.
     */
    public function test_is_final_checks_if_class_is_final(): void
    {
        // Assert this test class is final
        $this->assertTrue(Reflection::isFinal($this));
    }

    /**
     * Test that hasMethod() correctly checks for method existence.
     *
     * Verifies method existence checking using the hasMethod() wrapper.
     */
    public function test_has_method_checks_method_existence(): void
    {
        // Assert existing method returns true
        $this->assertTrue(Reflection::hasMethod($this, 'setUp'));

        // Assert non-existent method returns false
        $this->assertFalse(Reflection::hasMethod($this, 'nonExistent'));
    }

    /**
     * Test that getMethods() returns all methods of a class.
     *
     * Verifies that the method returns an array of ReflectionMethod objects.
     */
    public function test_get_methods_returns_all_methods(): void
    {
        // Get all methods
        $methods = Reflection::getMethods($this);

        // Assert it returns an array
        $this->assertIsArray($methods);

        // Assert the array is not empty
        $this->assertNotEmpty($methods);
    }

    /**
     * Test that getProperties() returns all properties of a class.
     *
     * Verifies that the method returns both public and private properties.
     */
    public function test_get_properties_returns_all_properties(): void
    {
        // Create an anonymous class with multiple properties
        $obj = new class()
        {
            public string $prop1 = 'value1';

            private string $prop2 = 'value2';
        };

        // Get all properties
        $properties = Reflection::getProperties($obj);

        // Assert it returns an array
        $this->assertIsArray($properties);

        // Assert it returns both properties
        $this->assertCount(2, $properties);
    }
}
