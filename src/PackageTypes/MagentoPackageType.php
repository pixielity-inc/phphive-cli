<?php

declare(strict_types=1);

namespace PhpHive\Cli\PackageTypes;

use Exception;
use Override;
use PhpHive\Cli\Enums\PackageType;

/**
 * Magento Package Type.
 *
 * Handles the creation of Magento 2 modules within the monorepo, providing
 * scaffolding for Magento-specific features and conventions. Magento modules
 * are reusable components that extend Magento applications with custom
 * functionality, integrations, and business logic.
 *
 * Stub Processing:
 * This package type uses Pixielity\StubGenerator\Facades\Stub for template
 * processing. The Stub facade handles:
 * - Loading stub files from the path returned by getStubPath()
 * - Automatic UPPERCASE conversion of variable names
 * - Replacing placeholders with actual values from prepareVariables()
 * - Generating final package files from templates
 *
 * Magento Module Features:
 * - ComponentRegistrar for module registration
 * - module.xml for module metadata and dependencies
 * - registration.php for Magento module discovery
 * - di.xml for dependency injection configuration
 * - routes.xml for frontend/adminhtml routing
 * - Controller and Block classes
 * - Plugin and Observer support
 * - Setup scripts for database modifications
 *
 * Module Structure:
 * ```
 * packages/my-magento-module/
 *   ├── registration.php                    <- Module registration
 *   ├── composer.json
 *   ├── etc/
 *   │   ├── module.xml                      <- Module metadata
 *   │   ├── di.xml                          <- Dependency injection
 *   │   ├── frontend/
 *   │   │   └── routes.xml                  <- Frontend routes
 *   │   └── adminhtml/
 *   │       └── routes.xml                  <- Admin routes
 *   ├── Controller/
 *   │   ├── Index/
 *   │   │   └── Index.php
 *   │   └── Adminhtml/
 *   ├── Block/
 *   ├── Model/
 *   ├── Plugin/
 *   ├── Observer/
 *   ├── Setup/
 *   │   └── InstallSchema.php
 *   └── view/
 *       ├── frontend/
 *       │   ├── layout/
 *       │   └── templates/
 *       └── adminhtml/
 * ```
 *
 * ComponentRegistrar:
 * Magento uses ComponentRegistrar to register modules with the application.
 * The registration.php file in the module root registers the module:
 *
 * ```php
 * use Magento\Framework\Component\ComponentRegistrar;
 *
 * ComponentRegistrar::register(
 *     ComponentRegistrar::MODULE,
 *     'PhpHive_MyMagentoModule',
 *     __DIR__
 * );
 * ```
 *
 * Module Naming Convention:
 * Magento modules follow the Vendor_ModuleName convention:
 * - Package 'user-management' -> PhpHive_UserManagement
 * - Package 'api-client' -> PhpHive_ApiClient
 * - Package 'payment-gateway' -> PhpHive_PaymentGateway
 *
 * Module.xml Configuration:
 * Defines module metadata and dependencies:
 * ```xml
 * <?xml version="1.0"?>
 * <config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
 *         xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
 *     <module name="PhpHive_UserManagement" setup_version="1.0.0">
 *         <sequence>
 *             <module name="Magento_Customer"/>
 *         </sequence>
 *     </module>
 * </config>
 * ```
 *
 * Dependency Injection (di.xml):
 * Configures service classes, plugins, and preferences:
 * ```xml
 * <?xml version="1.0"?>
 * <config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
 *         xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
 *     <type name="Magento\Customer\Model\Customer">
 *         <plugin name="phphive_user_management_customer_plugin"
 *                 type="PhpHive\UserManagement\Plugin\CustomerPlugin"/>
 *     </type>
 * </config>
 * ```
 *
 * Routing Configuration:
 * Defines URL routes for frontend and admin areas:
 * ```xml
 * <?xml version="1.0"?>
 * <config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
 *         xsi:noNamespaceSchemaLocation="urn:magento:framework:App/etc/routes.xsd">
 *     <router id="standard">
 *         <route id="usermanagement" frontName="usermanagement">
 *             <module name="PhpHive_UserManagement"/>
 *         </route>
 *     </router>
 * </config>
 * ```
 *
 * Magento Marketplace Authentication:
 * Installing Magento modules requires authentication with repo.magento.com.
 * The Magento repository and authentication must be configured in the package's
 * composer.json before installing magento/framework dependency.
 *
 * Get authentication keys from: https://marketplace.magento.com/customer/accessKeys/
 *
 * Usage in Magento Applications:
 * Once created, the module can be used in Magento apps within the monorepo:
 * 1. Add to app's composer.json: "phphive/user-management": "*"
 * 2. Run: composer update
 * 3. Enable module: php bin/magento module:enable PhpHive_UserManagement
 * 4. Run setup: php bin/magento setup:upgrade
 * 5. Compile: php bin/magento setup:di:compile
 *
 * Module Discovery:
 * Magento automatically discovers modules through:
 * - ComponentRegistrar in registration.php
 * - Composer autoloading (PSR-4)
 * - Module scanning in app/code/ and vendor/
 *
 * For monorepo packages, the Magento app's registration_globlist.php is
 * configured to include ../../../packages/*registration.php for automatic
 * module discovery.
 *
 * Dependencies:
 * - magento/framework: Core Magento framework components
 * - magento/module-*: Specific Magento modules (Customer, Catalog, etc.)
 * - PHP 8.1+ (Magento 2.4.6+) or PHP 8.4 (Magento 2.4.7+)
 *
 * Best Practices:
 * - Follow Magento coding standards (PSR-12 + Magento conventions)
 * - Use dependency injection instead of ObjectManager
 * - Prefer plugins over class rewrites
 * - Use observers for event-driven logic
 * - Implement proper ACL for admin resources
 * - Write integration tests for critical functionality
 * - Document module configuration options
 * - Version module.xml setup_version appropriately
 *
 * Common Module Types:
 * - Payment gateways (payment method integration)
 * - Shipping methods (custom shipping calculations)
 * - Product types (custom product configurations)
 * - Customer attributes (additional customer data)
 * - Admin grids (custom admin interfaces)
 * - API endpoints (REST/GraphQL extensions)
 * - Integrations (third-party service connections)
 * - Customizations (theme and layout modifications)
 *
 * @see AbstractPackageType
 * @see https://developer.adobe.com/commerce/php/development/ Magento Development
 * @see https://developer.adobe.com/commerce/php/development/components/modules/ Magento Modules
 * @see https://marketplace.magento.com Magento Marketplace
 */
final class MagentoPackageType extends AbstractPackageType
{
    /**
     * Get the package type identifier.
     *
     * Returns the unique identifier for this package type, used for:
     * - Stub directory resolution (stubs/packages/magento/)
     * - Package type selection in CLI prompts
     * - Factory registration and lookup
     *
     * @return string Package type identifier 'magento'
     */
    public function getType(): string
    {
        return PackageType::MAGENTO->value;
    }

    /**
     * Get the display name of this package type.
     *
     * Returns a human-readable name shown in the package type selection menu
     * and success messages. This helps users identify the package type in
     * interactive prompts.
     *
     * @return string The display name 'Magento'
     */
    public function getDisplayName(): string
    {
        return 'Magento';
    }

    /**
     * Get a brief description of this package type.
     *
     * Returns a short description shown in the package type selection menu
     * to help users understand what this package type provides and its key
     * features. Highlights the main Magento-specific components included.
     *
     * @return string A brief description of Magento module features
     */
    public function getDescription(): string
    {
        return 'Magento Module (ComponentRegistrar)';
    }

    /**
     * Prepare variables for Magento module stub template processing.
     *
     * Extends parent variables with Magento-specific placeholders processed by
     * the Pixielity\StubGenerator\Facades\Stub facade:
     * - {{MODULE_NAME}}: Magento module name in Vendor_Module format
     * - {{PACKAGE_NAME_NORMALIZED}}: Kebab-case package name for routes
     *
     * Note: The Stub facade automatically converts variable names to UPPERCASE
     * when processing templates.
     *
     * Example transformations for package 'test-magento':
     * - {{MODULE_NAME}} -> 'Monorepo_TestMagento'
     * - {{PACKAGE_NAME_NORMALIZED}} -> 'test-magento'
     *
     * @param  string                $name        Package name
     * @param  string                $description Package description
     * @return array<string, string> Variables for template replacement via Stub facade
     */
    #[Override]
    public function prepareVariables(string $name, string $description): array
    {
        $variables = parent::prepareVariables($name, $description);

        // Add Magento-specific variables
        $packageNamespace = $variables['package_namespace'];

        // For Magento, create module name format (Vendor_Module)
        // Replace any hyphens with underscores in the namespace part
        // Example: 'test-magento' -> 'Monorepo_TestMagento'
        // Example: 'my-custom-module' -> 'Monorepo_MyCustomModule'
        $moduleName = 'Monorepo_' . str_replace('-', '_', $packageNamespace);

        // Create route ID (must use underscores, not hyphens)
        // Example: 'test-magento-new' -> 'test_magento_new'
        $routeId = str_replace('-', '_', $name);

        // Use lowercase keys - Stub facade will convert them to UPPERCASE
        // and wrap with {{KEY}} delimiters automatically
        $variables['module_name'] = $moduleName;
        $variables['route_id'] = $routeId;

        return $variables;
    }

    /**
     * Perform post-creation tasks for Magento packages.
     *
     * Extends the parent postCreate to add Magento-specific setup:
     * 1. Configures Magento Composer repository
     * 2. Runs composer install to fetch dependencies
     *
     * Note: Magento authentication keys should be configured globally or
     * via environment variables (COMPOSER_AUTH_MAGENTO_PUBLIC_KEY and
     * COMPOSER_AUTH_MAGENTO_PRIVATE_KEY) before creating Magento packages.
     *
     * Get authentication keys from: https://marketplace.magento.com/customer/accessKeys/
     *
     * @param string $packagePath Full path to created package directory
     *
     * @throws Exception If composer operations fail
     */
    #[Override]
    public function postCreate(string $packagePath): void
    {
        // Configure Magento repository before installing dependencies
        $this->composer->run(
            $packagePath,
            ['config', 'repositories.magento', 'composer', 'https://repo.magento.com/']
        );

        // Run parent postCreate to install dependencies
        parent::postCreate($packagePath);
    }
}
