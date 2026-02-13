<?php

declare(strict_types=1);

namespace PhpHive\Cli\PackageTypes;

use PhpHive\Cli\Enums\PackageType;

/**
 * Skeleton Package Type.
 *
 * Handles the creation of minimal PHP library packages within the monorepo,
 * providing a lightweight scaffolding for framework-agnostic PHP libraries.
 * This package type is ideal for creating reusable PHP components that don't
 * require framework-specific features or conventions.
 *
 * Stub Processing:
 * This package type uses Pixielity\StubGenerator\Facades\Stub for template
 * processing. The Stub facade handles:
 * - Loading stub files from the path returned by getStubPath()
 * - Automatic UPPERCASE conversion of variable names
 * - Replacing placeholders with actual values from prepareVariables()
 * - Generating final package files from templates
 *
 * Skeleton Package Features:
 * - Minimal composer.json with essential configuration
 * - PSR-4 autoloading setup
 * - Basic directory structure (src/, tests/)
 * - Simple README.md template
 * - .gitignore for common PHP exclusions
 * - No framework dependencies or conventions
 *
 * Package Structure:
 * ```
 * packages/my-library/
 *   ├── src/
 *   │   └── (Your PHP classes here)
 *   ├── tests/
 *   │   └── (Your PHPUnit tests here)
 *   ├── composer.json
 *   ├── README.md
 *   └── .gitignore
 * ```
 *
 * Use Cases:
 * - Utility libraries (string helpers, array utilities, etc.)
 * - Domain models and value objects
 * - API clients and SDK wrappers
 * - Data transformation libraries
 * - Validation and sanitization libraries
 * - Framework-agnostic business logic
 * - Shared interfaces and contracts
 * - Mathematical or algorithmic libraries
 *
 * Minimal Dependencies:
 * The skeleton package includes only essential dependencies:
 * - PHP version requirement (e.g., ^8.1)
 * - PSR-4 autoloading configuration
 * - No framework-specific packages
 * - Optional: PHPUnit for testing (dev dependency)
 *
 * This keeps the package lightweight and ensures maximum compatibility
 * across different PHP projects and frameworks.
 *
 * PSR-4 Autoloading:
 * The package is configured with PSR-4 autoloading:
 * ```json
 * {
 *   "autoload": {
 *     "psr-4": {
 *       "PhpHive\\MyLibrary\\": "src/"
 *     }
 *   }
 * }
 * ```
 *
 * This allows classes to be automatically loaded based on their namespace
 * and file location, following PHP-FIG standards.
 *
 * Framework Compatibility:
 * Skeleton packages can be used in any PHP project:
 * - Laravel applications
 * - Symfony applications
 * - Magento modules
 * - WordPress plugins
 * - Standalone PHP scripts
 * - Other PHP frameworks (CodeIgniter, Yii, etc.)
 *
 * Usage in Applications:
 * Once created, the package can be used in any app within the monorepo:
 * 1. Add to app's composer.json: "phphive/my-library": "*"
 * 2. Run: composer update
 * 3. Use classes: use PhpHive\MyLibrary\MyClass;
 *
 * No additional configuration or registration required - just install and use.
 *
 * Testing:
 * The package includes a tests/ directory for PHPUnit tests:
 * ```php
 * namespace PhpHive\MyLibrary\Tests;
 *
 * use PHPUnit\Framework\TestCase;
 * use PhpHive\MyLibrary\MyClass;
 *
 * class MyClassTest extends TestCase
 * {
 *     public function testSomething(): void
 *     {
 *         $instance = new MyClass();
 *         $this->assertInstanceOf(MyClass::class, $instance);
 *     }
 * }
 * ```
 *
 * Best Practices:
 * - Keep packages focused on a single responsibility
 * - Follow PSR-12 coding standards
 * - Write comprehensive unit tests
 * - Document public APIs with PHPDoc
 * - Use semantic versioning
 * - Avoid framework-specific dependencies
 * - Provide clear usage examples in README
 * - Use type hints and return types (PHP 7.4+)
 *
 * When to Use Skeleton vs Framework Packages:
 * - Use Skeleton for: Utility libraries, domain logic, API clients
 * - Use Laravel for: Laravel-specific features (Service Providers, Facades)
 * - Use Symfony for: Symfony-specific features (Bundles, DI Extensions)
 * - Use Magento for: Magento modules (ComponentRegistrar, plugins)
 *
 * Advantages:
 * - Maximum portability across projects
 * - Minimal dependencies and overhead
 * - Fast installation and loading
 * - Easy to test in isolation
 * - No framework lock-in
 * - Simple to understand and maintain
 *
 * Example Libraries:
 * - String manipulation utilities
 * - Date/time helpers
 * - HTTP client wrappers
 * - Data validation rules
 * - Encryption/hashing utilities
 * - File system operations
 * - Configuration parsers
 * - Event dispatchers
 *
 * @see AbstractPackageType
 * @see https://www.php-fig.org/psr/psr-4/ PSR-4 Autoloading Standard
 * @see https://www.php-fig.org/psr/psr-12/ PSR-12 Coding Style
 */
final class SkeletonPackageType extends AbstractPackageType
{
    /**
     * Get the package type identifier.
     *
     * Returns the unique identifier for this package type, used for:
     * - Stub directory resolution (stubs/packages/skeleton/)
     * - Package type selection in CLI prompts
     * - Factory registration and lookup
     *
     * @return string Package type identifier 'skeleton'
     */
    public function getType(): string
    {
        return PackageType::SKELETON->value;
    }

    /**
     * Get the display name of this package type.
     *
     * Returns a human-readable name shown in the package type selection menu
     * and success messages. This helps users identify the package type in
     * interactive prompts.
     *
     * @return string The display name 'Skeleton'
     */
    public function getDisplayName(): string
    {
        return 'Skeleton';
    }

    /**
     * Get a brief description of this package type.
     *
     * Returns a short description shown in the package type selection menu
     * to help users understand what this package type provides. Emphasizes
     * the minimal, framework-agnostic nature of skeleton packages.
     *
     * @return string A brief description of skeleton package features
     */
    public function getDescription(): string
    {
        return 'Skeleton Package (Minimal PHP library)';
    }
}
