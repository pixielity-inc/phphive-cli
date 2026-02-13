<?php

declare(strict_types=1);

namespace PhpHive\Cli\PackageTypes;

use Exception;
use Illuminate\Support\Str;
use PhpHive\Cli\Contracts\PackageTypeInterface;
use PhpHive\Cli\Support\Composer;

/**
 * Abstract Package Type.
 *
 * Base implementation for all package types in the monorepo, providing common
 * functionality for package creation, stub processing, and dependency management.
 * Concrete package types (Laravel, Magento, Symfony, Skeleton) extend this class
 * and override methods to provide framework-specific behavior.
 *
 * This class serves as the foundation for the package scaffolding system,
 * handling the common tasks that all package types need:
 * - Stub path resolution
 * - Variable preparation for template processing
 * - Namespace and package name generation
 * - Post-creation dependency installation
 *
 * Package Creation Flow:
 * 1. CreatePackageCommand instantiates the appropriate package type
 * 2. getStubPath() locates the template files for this package type
 * 3. prepareVariables() generates placeholder replacements
 * 4. getFileNamingRules() provides framework-specific file naming
 * 5. Stub files are copied and processed with variable replacement
 * 6. postCreate() runs composer install and any additional setup
 *
 * Variable System:
 * The variable system uses placeholder tokens (e.g., {{PACKAGE_NAME}}) in stub
 * files that are replaced with actual values during package creation. Common
 * variables include:
 * - {{PACKAGE_NAME}}: Original package name (e.g., 'test-laravel')
 * - {{PACKAGE_NAMESPACE}}: PascalCase namespace (e.g., 'TestLaravel')
 * - {{COMPOSER_PACKAGE_NAME}}: Full composer name (e.g., 'phphive/test-laravel')
 * - {{DESCRIPTION}}: Package description
 * - {{NAMESPACE}}: Full PHP namespace (e.g., 'PhpHive\TestLaravel')
 *
 * File Naming Rules:
 * Some frameworks require specific file naming conventions (e.g., Laravel's
 * ServiceProvider must be named after the package). The getFileNamingRules()
 * method allows package types to define these transformations.
 *
 * Example stub file (composer.json.stub):
 * ```json
 * {
 *   "name": "{{COMPOSER_PACKAGE_NAME}}",
 *   "description": "{{DESCRIPTION}}",
 *   "autoload": {
 *     "psr-4": {
 *       "{{NAMESPACE}}\\": "src/"
 *     }
 *   }
 * }
 * ```
 *
 * Extending this class:
 * Concrete package types should implement:
 * - getType(): Return the package type identifier (e.g., 'laravel')
 * - getDisplayName(): Return human-readable name (e.g., 'Laravel')
 * - getDescription(): Return description for CLI prompts
 * - getFileNamingRules(): (Optional) Define framework-specific file naming
 * - postCreate(): (Optional) Override for additional post-creation tasks
 *
 * @see PackageTypeInterface
 * @see CreatePackageCommand
 * @see Composer
 */
abstract class AbstractPackageType implements PackageTypeInterface
{
    /**
     * Create a new package type instance.
     *
     * Initializes the package type with a Composer service instance for
     * dependency management. The Composer service is used in postCreate()
     * to install package dependencies after scaffolding.
     *
     * @param Composer $composer Composer service for dependency management
     */
    public function __construct(
        protected readonly Composer $composer
    ) {}

    /**
     * Get the stub directory path for this package type.
     *
     * Returns the path used by Pixielity\StubGenerator\Facades\Stub for loading
     * package template files. This path is set via Stub::setBasePath() before
     * processing stub templates.
     *
     * The Stub facade handles:
     * - Loading stub files from the returned path
     * - Processing template variables (automatic UPPERCASE conversion)
     * - Replacing placeholders with actual values
     * - Generating final package files
     *
     * Directory structure:
     * ```
     * stubs/
     *   packages/
     *     laravel/       <- Laravel package stubs
     *     magento/       <- Magento module stubs
     *     symfony/       <- Symfony bundle stubs
     *     skeleton/      <- Generic PHP library stubs
     * ```
     *
     * Each stub directory typically contains:
     * - composer.json.stub: Package dependencies and metadata
     * - src/: Source code templates with placeholder variables
     * - README.md.stub: Package documentation template
     * - .gitignore: Version control exclusions
     *
     * Note: Variable names are automatically converted to UPPERCASE by the Stub
     * facade (e.g., 'package_name' becomes '{{PACKAGE_NAME}}' in templates).
     *
     * @param  string $stubsBasePath Base path to stubs directory (e.g., '/path/to/cli/stubs')
     * @return string Full path compatible with Stub::setBasePath() (e.g., '/path/to/cli/stubs/packages/laravel')
     */
    public function getStubPath(string $stubsBasePath): string
    {
        return "{$stubsBasePath}/packages/{$this->getType()}";
    }

    /**
     * Prepare variables for stub template processing.
     *
     * Generates an associative array of placeholder => value pairs used by the
     * Pixielity\StubGenerator\Facades\Stub facade to replace placeholders in
     * stub template files. This method creates the common variables that all
     * package types need, including package names, namespaces, and metadata.
     *
     * Variable generation process:
     * 1. Convert package name to PascalCase namespace (e.g., 'test-laravel' -> 'TestLaravel')
     * 2. Generate composer package name with vendor prefix (e.g., 'phphive/test-laravel')
     * 3. Build full PHP namespace (e.g., 'PhpHive\TestLaravel')
     * 4. Include metadata (description, author information)
     *
     * Generated variables (processed by Stub facade):
     * - {{PACKAGE_NAME}}: Original package name as provided by user
     * - {{PACKAGE_NAMESPACE}}: PascalCase namespace component
     * - {{COMPOSER_PACKAGE_NAME}}: Full composer package name (vendor/package)
     * - {{DESCRIPTION}}: Package description
     * - {{AUTHOR_NAME}}: Package author name
     * - {{AUTHOR_EMAIL}}: Package author email
     * - {{NAMESPACE}}: Full PHP namespace for PSR-4 autoloading
     *
     * Note: The Stub facade automatically converts variable names to UPPERCASE
     * when processing templates. Variable keys should match the placeholder
     * format used in stub files (e.g., '{{PACKAGE_NAME}}').
     *
     * Example usage in stub files:
     * ```php
     * namespace {{NAMESPACE}};
     *
     * class {{PACKAGE_NAMESPACE}}ServiceProvider
     * {
     *     // ...
     * }
     * ```
     *
     * Concrete package types can override this method to add framework-specific
     * variables while preserving the common ones using parent::prepareVariables().
     *
     * @param  string                $name        Package name (e.g., 'test-laravel')
     * @param  string                $description Package description
     * @return array<string, string> Associative array of placeholder => value pairs for Stub facade
     */
    public function prepareVariables(string $name, string $description): array
    {
        // Convert package name to namespace (e.g., 'test-laravel' -> 'TestLaravel')
        $packageNamespace = $this->convertToNamespace($name);

        // Generate composer package name (e.g., 'phphive/test-laravel')
        $composerPackageName = $this->generateComposerPackageName($name);

        // Return lowercase keys - Stub facade will convert them to UPPERCASE
        // and wrap with {{KEY}} or $KEY$ delimiters automatically
        return [
            'package_name' => $name,
            'package_name_normalized' => $name, // Normalized version (same as package_name for kebab-case)
            'package_namespace' => $packageNamespace,
            'composer_package_name' => $composerPackageName,
            'description' => $description,
            'author_name' => 'PhpHive Team',
            'author_email' => 'team@phphive.com',
            'namespace' => "PhpHive\\{$packageNamespace}",
        ];
    }

    /**
     * Get special file naming rules for this package type.
     *
     * Returns an array of file path patterns and their replacement rules for
     * framework-specific file naming conventions. Some frameworks require files
     * to be named after the package (e.g., Laravel's ServiceProvider, Symfony's
     * Bundle class).
     *
     * The default implementation returns an empty array, meaning no special
     * naming rules. Concrete package types override this method to define
     * their specific naming conventions.
     *
     * Example from LaravelPackageType:
     * ```php
     * return [
     *     '/src/Providers/ServiceProvider.php' => '/src/Providers/{{PACKAGE_NAMESPACE}}ServiceProvider.php',
     * ];
     * ```
     *
     * This would rename:
     * - ServiceProvider.php -> TestLaravelServiceProvider.php (for package 'test-laravel')
     *
     * The replacement patterns can use any variable from prepareVariables(),
     * allowing dynamic file naming based on package configuration.
     *
     * @return array<string, string> Map of file patterns to replacement patterns
     */
    public function getFileNamingRules(): array
    {
        return [];
    }

    /**
     * Perform post-creation tasks.
     *
     * Called after package files are created and processed. This method handles
     * tasks that need to run after the package structure is in place, such as:
     * - Installing Composer dependencies
     * - Generating additional files
     * - Running framework-specific setup commands
     * - Initializing configuration
     *
     * The default implementation runs `composer install` to install package
     * dependencies defined in the generated composer.json. This ensures the
     * package is immediately usable after creation.
     *
     * Composer install options:
     * - dev: true - Install development dependencies (PHPUnit, etc.)
     * - optimize: false - Skip autoloader optimization for faster installation
     * - extraOptions: ['--prefer-dist'] - Download distribution archives instead of cloning repos
     *
     * Error handling:
     * If composer install fails, the exception is re-thrown to be handled by
     * the CreatePackageCommand, which displays a user-friendly warning message.
     *
     * Concrete package types can override this method to add framework-specific
     * post-creation tasks while preserving the composer install:
     * ```php
     * public function postCreate(string $packagePath): void
     * {
     *     parent::postCreate($packagePath); // Run composer install
     *
     *     // Add framework-specific tasks
     *     $this->generateConfigFiles($packagePath);
     * }
     * ```
     *
     * @param string $packagePath Full path to created package directory
     *
     * @throws Exception If composer install fails
     */
    public function postCreate(string $packagePath): void
    {
        $this->composer->install($packagePath, dev: true, optimize: false, extraOptions: ['--prefer-dist']);
    }

    /**
     * Convert package name to namespace.
     *
     * Converts kebab-case or snake_case package names to PascalCase namespace
     * components suitable for PHP class names and namespaces. This ensures
     * consistent naming conventions across all packages.
     *
     * Conversion process:
     * 1. Replace hyphens and underscores with spaces
     * 2. Capitalize first letter of each word (ucwords)
     * 3. Remove all spaces to create PascalCase
     *
     * Examples:
     * - 'test-laravel' -> 'TestLaravel'
     * - 'my-awesome-package' -> 'MyAwesomePackage'
     * - 'user_management' -> 'UserManagement'
     * - 'api-client-v2' -> 'ApiClientV2'
     *
     * The resulting namespace component is used in:
     * - PHP class names (e.g., TestLaravelServiceProvider)
     * - PSR-4 namespace declarations (e.g., PhpHive\TestLaravel)
     * - File naming rules (e.g., TestLaravelBundle.php)
     *
     * @param  string $name Package name in kebab-case or snake_case
     * @return string PascalCase namespace component
     */
    protected function convertToNamespace(string $name): string
    {
        return Str::replace(' ', '', Str::ucwords(Str::replace(['-', '_'], ' ', $name)));
    }

    /**
     * Generate composer package name.
     *
     * Creates a fully-qualified Composer package name by combining the vendor
     * prefix with the package name. The package name is normalized to lowercase
     * to follow Composer naming conventions.
     *
     * Format: vendor/package-name
     * - Vendor: 'phphive' (organization/vendor name)
     * - Package: lowercase package name
     *
     * Examples:
     * - 'test-laravel' -> 'phphive/test-laravel'
     * - 'MyPackage' -> 'phphive/mypackage'
     * - 'API-Client' -> 'phphive/api-client'
     *
     * The generated package name is used in:
     * - composer.json "name" field
     * - Package identification in monorepo
     * - Composer repository references
     *
     * Note: Composer package names must be lowercase and use hyphens for
     * word separation, following the format: vendor/package-name
     *
     * @param  string $name Package name
     * @return string Composer package name (e.g., 'phphive/test-laravel')
     */
    protected function generateComposerPackageName(string $name): string
    {
        return 'phphive/' . Str::lower($name);
    }
}
