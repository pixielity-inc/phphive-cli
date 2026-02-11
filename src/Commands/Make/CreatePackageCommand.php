<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Commands\Make;

use function array_map;
use function explode;
use function implode;
use function is_dir;
use function json_encode;

use MonoPhp\Cli\Commands\BaseCommand;
use MonoPhp\Cli\Support\Filesystem;
use Override;
use RuntimeException;

use function str_replace;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function ucfirst;

/**
 * Create Package Command.
 *
 * This command scaffolds a reusable PHP library package within the monorepo's
 * packages/ directory. Packages are shared libraries that can be used by
 * multiple applications within the monorepo, promoting code reuse and
 * maintaining a clean separation of concerns.
 *
 * The scaffolding process:
 * 1. Validates the package name doesn't already exist
 * 2. Creates the directory structure (src, tests)
 * 3. Generates composer.json with library configuration
 * 4. Generates package.json with Turbo task definitions
 * 5. Creates PHPUnit configuration for testing
 * 6. Generates README.md with usage instructions
 * 7. Adds .gitkeep files to preserve empty directories
 *
 * Directory structure created:
 * packages/{name}/
 * ├── src/              # Package source code (PSR-4)
 * ├── tests/            # Test files
 * │   └── Unit/         # Unit tests
 * ├── composer.json     # PHP dependencies and autoloading
 * ├── package.json      # Turbo tasks and npm scripts
 * ├── phpunit.xml       # PHPUnit configuration
 * └── README.md         # Documentation
 *
 * Generated composer.json includes:
 * - PHP 8.2+ requirement
 * - Library type designation
 * - PHPUnit, PHPStan, and Pint dev dependencies
 * - PSR-4 autoloading configuration
 * - Proper namespace based on package name
 *
 * Generated package.json includes Turbo tasks:
 * - test: Run PHPUnit tests
 * - test:unit: Run unit tests only
 * - lint: Check code style with Pint
 * - format: Fix code style with Pint
 * - typecheck: Run PHPStan static analysis
 * - clean: Remove cache files
 *
 * PHPUnit configuration includes:
 * - Bootstrap with Composer autoloader
 * - Color output enabled
 * - Fail on risky tests and warnings
 * - Code coverage configuration
 * - Test suite definitions
 *
 * Naming conventions:
 * - Package names use kebab-case (e.g., logger, http-client)
 * - Namespaces use PascalCase (e.g., MonoPhp\Logger)
 * - Composer names use mono-php/{name} format
 * - NPM names use @mono-php/{name} format
 *
 * Package vs Application:
 * - Packages are libraries (type: library)
 * - Applications are projects (type: project)
 * - Packages don't have public/ or config/ directories
 * - Packages focus on reusable functionality
 * - Packages can be required by multiple apps
 *
 * Features:
 * - Automatic namespace generation from package name
 * - Pre-configured with monorepo best practices
 * - Ready for Turbo task execution
 * - Complete testing infrastructure
 * - Configured for code quality tools
 * - PSR-4 autoloading ready
 *
 * Example usage:
 * ```bash
 * # Create a logging package
 * ./cli/bin/mono create:package logger
 *
 * # Create an HTTP client package
 * ./cli/bin/mono create:package http-client
 *
 * # Create a validation package
 * ./cli/bin/mono create:package validator
 *
 * # Using aliases
 * ./cli/bin/mono make:package database
 * ./cli/bin/mono new:package cache
 * ```
 *
 * After creation workflow:
 * ```bash
 * # Navigate to the new package
 * cd packages/logger
 *
 * # Install dependencies
 * composer install
 *
 * # Start coding in src/
 * # Add your classes and interfaces
 *
 * # Run tests
 * composer test
 *
 * # Use in an application
 * # Add to app's composer.json:
 * # "require": { "mono-php/logger": "*" }
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see CreateAppCommand For creating applications
 * @see InteractsWithMonorepo For workspace discovery
 * @see Filesystem For file operations
 */
#[AsCommand(
    name: 'create:package',
    description: 'Create a new package',
    aliases: ['new:package', 'make:package'],
)]
final class CreatePackageCommand extends BaseCommand
{
    /**
     * Configure the command options and arguments.
     *
     * Defines the command signature with required arguments and help text.
     * The name argument is required and should be a valid directory name
     * using kebab-case convention (e.g., logger, http-client).
     *
     * Common options (workspace, force, no-cache, no-interaction) are inherited from BaseCommand.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Package name (e.g., logger, http-client)',
            )
            ->setHelp(
                <<<'HELP'
                The <info>create:package</info> command scaffolds a new PHP package.

                <comment>Examples:</comment>
                  <info>mono create:package logger</info>
                  <info>mono create:package http-client</info>

                This creates a complete package structure with all necessary files.
                HELP
            );
    }

    /**
     * Execute the create package command.
     *
     * This method orchestrates the entire package scaffolding process:
     * 1. Extracts and validates the package name
     * 2. Checks if the package already exists
     * 3. Creates the complete directory structure
     * 4. Generates all configuration files
     * 5. Creates PHPUnit configuration and documentation
     * 6. Displays next steps to the user
     *
     * The command will fail if a package with the same name already
     * exists in the packages/ directory to prevent accidental overwrites.
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Extract the package name from command arguments
        $name = $input->getArgument('name');

        // Display intro banner with package name
        $this->intro("Creating package: {$name}");

        // Determine the full path for the new package
        $root = $this->getMonorepoRoot();
        $packagePath = "{$root}/packages/{$name}";

        // Check if package already exists to prevent overwriting
        // This is a safety check to avoid destroying existing work
        if (is_dir($packagePath)) {
            $this->error("Package '{$name}' already exists");

            return Command::FAILURE;
        }

        // Initialize filesystem helper for file operations
        $filesystem = Filesystem::make();

        // Create directory structure
        // Packages have a simpler structure than apps (no public/ or config/)
        $this->info('Creating directory structure...');
        $filesystem->makeDirectory("{$packagePath}/src", 0755, true);
        $filesystem->makeDirectory("{$packagePath}/tests/Unit", 0755, true);

        // Create composer.json
        // This file defines PHP dependencies, autoloading, and package metadata
        // Note: type is "library" for packages vs "project" for apps
        $this->info('Creating composer.json...');
        $composerJson = $this->generateComposerJson($name);
        $composerJsonContent = json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($composerJsonContent === false) {
            throw new RuntimeException('Failed to encode composer.json');
        }
        $filesystem->write("{$packagePath}/composer.json", $composerJsonContent);

        // Create package.json
        // This file defines Turbo tasks and npm scripts for the package
        $this->info('Creating package.json...');
        $packageJson = $this->generatePackageJson($name);
        $packageJsonContent = json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($packageJsonContent === false) {
            throw new RuntimeException('Failed to encode package.json');
        }
        $filesystem->write("{$packagePath}/package.json", $packageJsonContent);

        // Create README
        // This provides documentation and usage instructions for the package
        $this->info('Creating README.md...');
        $readme = $this->generateReadme($name);
        $filesystem->write("{$packagePath}/README.md", $readme);

        // Create PHPUnit configuration
        // This configures the test runner with proper settings and test suites
        $this->info('Creating phpunit.xml...');
        $phpunitXml = $this->generatePhpUnitXml();
        $filesystem->write("{$packagePath}/phpunit.xml", $phpunitXml);

        // Create .gitkeep files
        // These preserve empty directories in version control
        $filesystem->write("{$packagePath}/src/.gitkeep", '');

        // Display success message and next steps
        $this->line('');
        $this->outro("✓ Package '{$name}' created successfully!");
        $this->line('');
        $this->comment('Next steps:');
        $this->line("  1. cd packages/{$name}");
        $this->line('  2. composer install');
        $this->line('  3. Start coding in src/');

        return Command::SUCCESS;
    }

    /**
     * Generate composer.json content.
     */
    private function generateComposerJson(string $name): array
    {
        $namespace = $this->nameToNamespace($name);

        return [
            'name' => "mono-php/{$name}",
            'description' => ucfirst(str_replace('-', ' ', $name)) . ' package',
            'type' => 'library',
            'license' => 'MIT',
            'require' => [
                'php' => '^8.2',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^11.0',
                'phpstan/phpstan' => '^2.0',
                'laravel/pint' => '^1.18',
            ],
            'autoload' => [
                'psr-4' => [
                    "{$namespace}\\" => 'src/',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    "{$namespace}\\Tests\\" => 'tests/',
                ],
            ],
            'minimum-stability' => 'stable',
            'prefer-stable' => true,
        ];
    }

    /**
     * Generate package.json content.
     */
    private function generatePackageJson(string $name): array
    {
        return [
            'name' => "@mono-php/{$name}",
            'version' => '1.0.0',
            'private' => true,
            'scripts' => [
                'composer:install' => 'composer install --no-interaction --prefer-dist --optimize-autoloader',
                'test' => 'vendor/bin/phpunit',
                'test:unit' => 'vendor/bin/phpunit --testsuite=Unit',
                'lint' => 'vendor/bin/pint --test',
                'format' => 'vendor/bin/pint',
                'typecheck' => 'vendor/bin/phpstan analyse',
                'clean' => 'rm -rf .phpunit.cache .phpstan.cache',
            ],
        ];
    }

    /**
     * Generate README content.
     */
    private function generateReadme(string $name): string
    {
        $title = ucfirst(str_replace('-', ' ', $name));

        return <<<MD
        # {$title}

        {$title} package for the Mono PHP monorepo.

        ## Installation

        ```bash
        composer require mono-php/{$name}
        ```

        ## Usage

        ```php
        // Add usage examples here
        ```

        ## Testing

        ```bash
        composer test
        ```

        ## License

        MIT
        MD;
    }

    /**
     * Generate PHPUnit configuration.
     */
    private function generatePhpUnitXml(): string
    {
        return <<<'XML_WRAP'
        <?xml version="1.0" encoding="UTF-8"?>
        <phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                 xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
                 bootstrap="vendor/autoload.php"
                 colors="true"
                 failOnRisky="true"
                 failOnWarning="true"
                 cacheDirectory=".phpunit.cache">
            <testsuites>
                <testsuite name="Unit">
                    <directory>tests/Unit</directory>
                </testsuite>
            </testsuites>
            <source>
                <include>
                    <directory>src</directory>
                </include>
            </source>
        </phpunit>
        XML_WRAP;
    }

    /**
     * Convert package name to namespace.
     */
    private function nameToNamespace(string $name): string
    {
        $parts = explode('-', $name);
        $parts = array_map(ucfirst(...), $parts);

        return 'MonoPhp\\' . implode('', $parts);
    }
}
