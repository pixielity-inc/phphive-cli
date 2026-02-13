<?php

declare(strict_types=1);

namespace PhpHive\Cli\PackageTypes;

use Override;
use PhpHive\Cli\Enums\PackageType;

/**
 * Symfony Package Type.
 *
 * Handles the creation of Symfony bundles within the monorepo, providing
 * scaffolding for Symfony-specific features and conventions. Symfony bundles
 * are reusable components that extend Symfony applications with services,
 * controllers, commands, and other Symfony functionality.
 *
 * Stub Processing:
 * This package type uses Pixielity\StubGenerator\Facades\Stub for template
 * processing. The Stub facade handles:
 * - Loading stub files from the path returned by getStubPath()
 * - Automatic UPPERCASE conversion of variable names
 * - Replacing placeholders with actual values from prepareVariables()
 * - Generating final package files from templates
 *
 * Symfony Bundle Features:
 * - Bundle class for bundle registration and configuration
 * - DependencyInjection Extension for service container configuration
 * - Configuration class for bundle configuration tree
 * - Service definitions (services.yaml)
 * - Route configuration (routes.yaml)
 * - Controller and command integration
 * - Event subscriber registration
 * - Twig extension support
 *
 * Bundle Structure:
 * ```
 * packages/my-symfony-bundle/
 *   ├── src/
 *   │   ├── MySymfonyBundleBundle.php              <- Auto-named
 *   │   ├── DependencyInjection/
 *   │   │   ├── MySymfonyBundleExtension.php
 *   │   │   └── Configuration.php
 *   │   ├── Controller/
 *   │   ├── Command/
 *   │   ├── EventSubscriber/
 *   │   └── Service/
 *   ├── config/
 *   │   ├── services.yaml
 *   │   └── routes.yaml
 *   ├── templates/
 *   ├── translations/
 *   ├── composer.json
 *   └── README.md
 * ```
 *
 * Bundle Class Naming:
 * Symfony convention requires the Bundle class to be named after the package
 * with a "Bundle" suffix. This class implements getFileNamingRules() to
 * automatically rename Bundle.php to {PackageName}Bundle.php during scaffolding.
 *
 * Example: For package 'user-management', the Bundle class becomes:
 * - UserManagementBundle.php
 * - Class: PhpHive\UserManagement\UserManagementBundle
 *
 * DependencyInjection Extension:
 * The Extension class handles:
 * - Loading service definitions from config/services.yaml
 * - Processing bundle configuration
 * - Registering compiler passes
 * - Configuring service parameters
 * - Enabling/disabling bundle features based on configuration
 *
 * The Extension class is automatically named {PackageName}Extension and
 * follows Symfony's naming convention for auto-discovery.
 *
 * Configuration Class:
 * Defines the configuration tree for the bundle using Symfony's Config component:
 * - Validates configuration values
 * - Provides default values
 * - Documents available options
 * - Enables IDE autocompletion for bundle config
 *
 * Example configuration in app/config/packages/my_symfony_bundle.yaml:
 * ```yaml
 * my_symfony_bundle:
 *   enabled: true
 *   api_key: '%env(API_KEY)%'
 *   cache_ttl: 3600
 * ```
 *
 * Service Registration:
 * Services are defined in config/services.yaml with:
 * - Autowiring enabled for automatic dependency injection
 * - Autoconfiguration for automatic tag registration
 * - PSR-4 autoloading for service discovery
 * - Public services for external access
 *
 * Usage in Symfony Applications:
 * Once created, the bundle can be used in Symfony apps within the monorepo:
 * 1. Add to app's composer.json: "phphive/user-management": "*"
 * 2. Run: composer update
 * 3. Register in config/bundles.php:
 *    ```php
 *    PhpHive\UserManagement\UserManagementBundle::class => ['all' => true],
 *    ```
 * 4. Configure in config/packages/user_management.yaml
 *
 * Bundle Registration:
 * Symfony 4+ uses config/bundles.php for bundle registration:
 * ```php
 * return [
 *     // ...
 *     PhpHive\UserManagement\UserManagementBundle::class => ['all' => true],
 * ];
 * ```
 *
 * Dependencies:
 * - symfony/config: Configuration component for bundle config
 * - symfony/dependency-injection: Service container integration
 * - symfony/http-kernel: Bundle interface and kernel integration
 * - symfony/framework-bundle: (Optional) Framework features
 *
 * Best Practices:
 * - Keep bundles focused on a single responsibility
 * - Use semantic configuration for user-friendly config
 * - Provide sensible defaults in Configuration class
 * - Document all configuration options
 * - Use compiler passes for advanced service manipulation
 * - Tag services appropriately (controller, command, event_subscriber)
 *
 * @see AbstractPackageType
 * @see https://symfony.com/doc/current/bundles.html Symfony Bundle Development
 * @see https://symfony.com/doc/current/bundles/extension.html DependencyInjection Extension
 */
final class SymfonyPackageType extends AbstractPackageType
{
    /**
     * Get the package type identifier.
     *
     * Returns the unique identifier for this package type, used for:
     * - Stub directory resolution (stubs/packages/symfony/)
     * - Package type selection in CLI prompts
     * - Factory registration and lookup
     *
     * @return string Package type identifier 'symfony'
     */
    public function getType(): string
    {
        return PackageType::SYMFONY->value;
    }

    /**
     * Get the display name of this package type.
     *
     * Returns a human-readable name shown in the package type selection menu
     * and success messages. This helps users identify the package type in
     * interactive prompts.
     *
     * @return string The display name 'Symfony'
     */
    public function getDisplayName(): string
    {
        return 'Symfony';
    }

    /**
     * Get a brief description of this package type.
     *
     * Returns a short description shown in the package type selection menu
     * to help users understand what this package type provides and its key
     * features. Highlights the main Symfony-specific components included.
     *
     * @return string A brief description of Symfony bundle features
     */
    public function getDescription(): string
    {
        return 'Symfony Bundle (DependencyInjection)';
    }

    /**
     * Get special file naming rules for Symfony bundles.
     *
     * Defines Symfony-specific file naming conventions that must be applied
     * during package scaffolding. Symfony requires the Bundle class to be
     * named after the package with a "Bundle" suffix for proper registration
     * and convention.
     *
     * Naming rule:
     * - Bundle.php -> {PackageName}Bundle.php
     *
     * Example transformations:
     * - Package 'user-management' -> UserManagementBundle.php
     * - Package 'api-client' -> ApiClientBundle.php
     * - Package 'payment-gateway' -> PaymentGatewayBundle.php
     *
     * The {{PACKAGE_NAMESPACE}} placeholder is replaced with the PascalCase
     * package namespace during file processing, ensuring the Bundle class
     * name matches the file name and follows Symfony conventions.
     *
     * This naming convention is required for:
     * - Symfony's bundle auto-discovery
     * - Proper DependencyInjection Extension naming
     * - Configuration class association
     * - Bundle registration in config/bundles.php
     *
     * The Extension class is automatically named based on the Bundle class:
     * - UserManagementBundle -> UserManagementExtension
     *
     * @return array<string, string> Map of file patterns to replacement patterns
     */
    #[Override]
    public function getFileNamingRules(): array
    {
        return [
            '/src/Bundle.php' => '/src/{{PACKAGE_NAMESPACE}}Bundle.php',
            '/src/DependencyInjection/Extension.php' => '/src/DependencyInjection/{{PACKAGE_NAMESPACE}}Extension.php',
        ];
    }
}
