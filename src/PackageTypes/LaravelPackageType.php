<?php

declare(strict_types=1);

namespace PhpHive\Cli\PackageTypes;

use Override;
use PhpHive\Cli\Enums\PackageType;

/**
 * Laravel Package Type.
 *
 * Handles the creation of Laravel packages within the monorepo, providing
 * scaffolding for Laravel-specific features and conventions. Laravel packages
 * are reusable components that can be integrated into Laravel applications,
 * offering services, commands, views, and other Laravel functionality.
 *
 * Stub Processing:
 * This package type uses Pixielity\StubGenerator\Facades\Stub for template
 * processing. The Stub facade handles:
 * - Loading stub files from the path returned by getStubPath()
 * - Automatic UPPERCASE conversion of variable names
 * - Replacing placeholders with actual values from prepareVariables()
 * - Generating final package files from templates
 *
 * Laravel Package Features:
 * - Service Provider for package registration and bootstrapping
 * - Module.json for Laravel Modules integration
 * - Route files (web.php, api.php) for package-specific routes
 * - Config files for package configuration
 * - Views, migrations, and translations support
 * - Artisan commands integration
 * - Event and listener registration
 *
 * Package Structure:
 * ```
 * packages/my-laravel-package/
 *   ├── src/
 *   │   ├── Providers/
 *   │   │   └── MyLaravelPackageServiceProvider.php  <- Auto-named
 *   │   ├── Http/
 *   │   │   └── Controllers/
 *   │   ├── Models/
 *   │   └── Commands/
 *   ├── config/
 *   │   └── my-laravel-package.php
 *   ├── routes/
 *   │   ├── web.php
 *   │   └── api.php
 *   ├── resources/
 *   │   └── views/
 *   ├── database/
 *   │   └── migrations/
 *   ├── composer.json
 *   └── module.json
 * ```
 *
 * Service Provider Naming:
 * Laravel convention requires the Service Provider to be named after the
 * package. This class implements getFileNamingRules() to automatically rename
 * ServiceProvider.php to {PackageName}ServiceProvider.php during scaffolding.
 *
 * Example: For package 'user-management', the Service Provider becomes:
 * - UserManagementServiceProvider.php
 *
 * Laravel Modules Integration:
 * The package includes module.json for compatibility with nWidart/laravel-modules,
 * allowing the package to be used as a modular component with:
 * - Module activation/deactivation
 * - Module-specific migrations and seeders
 * - Module asset publishing
 * - Module-level configuration
 *
 * Service Provider Registration:
 * The generated Service Provider handles:
 * - Configuration publishing (config files)
 * - View registration and publishing
 * - Migration publishing
 * - Route registration (web and API routes)
 * - Command registration (Artisan commands)
 * - Event listener registration
 * - Translation publishing
 *
 * Usage in Laravel Applications:
 * Once created, the package can be used in Laravel apps within the monorepo:
 * 1. Add to app's composer.json: "phphive/user-management": "*"
 * 2. Run: composer update
 * 3. Service Provider auto-discovered (Laravel 5.5+)
 * 4. Publish assets: php artisan vendor:publish --provider="PhpHive\UserManagement\Providers\UserManagementServiceProvider"
 *
 * Dependencies:
 * - illuminate/support: Laravel framework components
 * - illuminate/contracts: Laravel contracts and interfaces
 * - nwidart/laravel-modules: (Optional) Module system integration
 *
 * @see AbstractPackageType
 * @see https://laravel.com/docs/packages Laravel Package Development
 * @see https://nwidart.com/laravel-modules Laravel Modules
 */
final class LaravelPackageType extends AbstractPackageType
{
    /**
     * Get the package type identifier.
     *
     * Returns the unique identifier for this package type, used for:
     * - Stub directory resolution (stubs/packages/laravel/)
     * - Package type selection in CLI prompts
     * - Factory registration and lookup
     *
     * @return string Package type identifier 'laravel'
     */
    public function getType(): string
    {
        return PackageType::LARAVEL->value;
    }

    /**
     * Get the display name of this package type.
     *
     * Returns a human-readable name shown in the package type selection menu
     * and success messages. This helps users identify the package type in
     * interactive prompts.
     *
     * @return string The display name 'Laravel'
     */
    public function getDisplayName(): string
    {
        return 'Laravel';
    }

    /**
     * Get a brief description of this package type.
     *
     * Returns a short description shown in the package type selection menu
     * to help users understand what this package type provides and its key
     * features. Highlights the main Laravel-specific components included.
     *
     * @return string A brief description of Laravel package features
     */
    public function getDescription(): string
    {
        return 'Laravel Package (Service Provider, Module support)';
    }

    /**
     * Get special file naming rules for Laravel packages.
     *
     * Defines Laravel-specific file naming conventions that must be applied
     * during package scaffolding. Laravel requires the Service Provider class
     * to be named after the package for proper auto-discovery and convention.
     *
     * Naming rule:
     * - ServiceProvider.php -> {PackageName}ServiceProvider.php
     *
     * Example transformations:
     * - Package 'user-management' -> UserManagementServiceProvider.php
     * - Package 'api-client' -> ApiClientServiceProvider.php
     * - Package 'payment-gateway' -> PaymentGatewayServiceProvider.php
     *
     * The {{PACKAGE_NAMESPACE}} placeholder is replaced with the PascalCase
     * package namespace during file processing, ensuring the Service Provider
     * class name matches the file name and follows Laravel conventions.
     *
     * This enables Laravel's package auto-discovery to automatically register
     * the Service Provider without manual configuration in Laravel 5.5+.
     *
     * @return array<string, string> Map of file patterns to replacement patterns
     */
    #[Override]
    public function getFileNamingRules(): array
    {
        return [
            '/src/Providers/ServiceProvider.php' => '/src/Providers/{{PACKAGE_NAMESPACE}}ServiceProvider.php',
        ];
    }
}
