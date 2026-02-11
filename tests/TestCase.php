<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base Test Case.
 *
 * Abstract base class for all test cases in the CLI package.
 * Extends PHPUnit's TestCase and provides common setup/teardown
 * functionality that can be shared across all tests.
 *
 * All test classes should extend this class instead of PHPUnit's
 * TestCase directly to ensure consistent test environment setup.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Set up the test environment before each test.
     *
     * This method is called before each test method is executed.
     * Override this method in child classes to add custom setup logic,
     * but always call parent::setUp() to maintain the base setup.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Clean up the test environment after each test.
     *
     * This method is called after each test method is executed.
     * Override this method in child classes to add custom cleanup logic,
     * but always call parent::tearDown() to maintain the base cleanup.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
