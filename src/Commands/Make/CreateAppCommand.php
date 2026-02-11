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
 * Create App Command.
 *
 * This command scaffolds a complete PHP application structure within the monorepo's
 * apps/ directory. It creates all necessary files and directories to get started
 * with a new application, including configuration files, entry points, and
 * integration with the monorepo's tooling ecosystem.
 *
 * The scaffolding process:
 * 1. Validates the application name doesn't already exist
 * 2. Creates the directory structure (public, src, config, tests)
 * 3. Generates composer.json with PSR-4 autoloading
 * 4. Generates package.json with Turbo task definitions
 * 5. Creates a basic index.php entry point
 * 6. Generates README.md with usage instructions
 * 7. Adds .gitkeep files to preserve empty directories
 *
 * Directory structure created:
 * apps/{name}/
 * ├── public/           # Web-accessible files
 * │   └── index.php     # Application entry point
 * ├── src/              # Application source code (PSR-4)
 * ├── config/           # Configuration files
 * ├── tests/            # Test files
 * │   └── Unit/         # Unit tests
 * ├── composer.json     # PHP dependencies and autoloading
 * ├── package.json      # Turbo tasks and npm scripts
 * └── README.md         # Documentation
 *
 * Generated composer.json includes:
 * - PHP 8.2+ requirement
 * - PHPUnit, PHPStan, and Pint dev dependencies
 * - PSR-4 autoloading configuration
 * - Proper namespace based on app name
 *
 * Generated package.json includes Turbo tasks:
 * - dev: Start development server
 * - build: Build production assets
 * - test: Run PHPUnit tests
 * - lint: Check code style with Pint
 * - format: Fix code style with Pint
 * - typecheck: Run PHPStan static analysis
 * - deploy: Deploy the application
 *
 * Naming conventions:
 * - App names use kebab-case (e.g., admin-panel, api-gateway)
 * - Namespaces use PascalCase (e.g., MonoPhp\AdminPanel)
 * - Package names use @mono-php/{name} format
 *
 * Features:
 * - Automatic namespace generation from app name
 * - Pre-configured with monorepo best practices
 * - Ready for Turbo task execution
 * - Includes testing infrastructure
 * - Configured for code quality tools
 * - Development server ready
 *
 * Example usage:
 * ```bash
 * # Create a new admin application
 * ./cli/bin/mono create:app admin
 *
 * # Create an API application
 * ./cli/bin/mono create:app api-gateway
 *
 * # Create a customer portal
 * ./cli/bin/mono create:app customer-portal
 *
 * # Using aliases
 * ./cli/bin/mono make:app dashboard
 * ./cli/bin/mono new:app reporting
 * ```
 *
 * After creation workflow:
 * ```bash
 * # Navigate to the new app
 * cd apps/admin
 *
 * # Install dependencies
 * composer install
 *
 * # Start development server
 * mono dev --workspace=admin
 *
 * # Or use Turbo directly
 * turbo run dev --filter=admin
 * ```
 *
 * @see BaseCommand For inherited functionality
 * @see CreatePackageCommand For creating shared packages
 * @see InteractsWithMonorepo For workspace discovery
 * @see Filesystem For file operations
 */
#[AsCommand(
    name: 'create:app',
    description: 'Create a new application',
    aliases: ['new:app', 'make:app'],
)]
final class CreateAppCommand extends BaseCommand
{
    /**
     * Configure the command options and arguments.
     *
     * Defines the command signature with required arguments and help text.
     * The name argument is required and should be a valid directory name
     * using kebab-case convention (e.g., admin, api-gateway).
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
                'Application name (e.g., admin, api)',
            )
            ->setHelp(
                <<<'HELP'
                The <info>create:app</info> command scaffolds a new PHP application.

                <comment>Examples:</comment>
                  <info>mono create:app admin</info>
                  <info>mono create:app api</info>

                This creates a complete application structure with all necessary files.
                HELP
            );
    }

    /**
     * Execute the create app command.
     *
     * This method orchestrates the entire application scaffolding process:
     * 1. Extracts and validates the application name
     * 2. Checks if the application already exists
     * 3. Creates the complete directory structure
     * 4. Generates all configuration files
     * 5. Creates entry points and documentation
     * 6. Displays next steps to the user
     *
     * The command will fail if an application with the same name already
     * exists in the apps/ directory to prevent accidental overwrites.
     *
     * @param  InputInterface  $input  Command input (arguments and options)
     * @param  OutputInterface $output Command output (for displaying messages)
     * @return int             Exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Extract the application name from command arguments
        $name = $input->getArgument('name');

        // Display intro banner with app name
        $this->intro("Creating application: {$name}");

        // Determine the full path for the new application
        $root = $this->getMonorepoRoot();
        $appPath = "{$root}/apps/{$name}";

        // Check if app already exists to prevent overwriting
        // This is a safety check to avoid destroying existing work
        if (is_dir($appPath)) {
            $this->error("Application '{$name}' already exists");

            return Command::FAILURE;
        }

        // Initialize filesystem helper for file operations
        $filesystem = Filesystem::make();

        // Create directory structure
        // This sets up the standard application layout with proper permissions
        $this->info('Creating directory structure...');
        $filesystem->makeDirectory("{$appPath}/public", 0755, true);
        $filesystem->makeDirectory("{$appPath}/src", 0755, true);
        $filesystem->makeDirectory("{$appPath}/config", 0755, true);
        $filesystem->makeDirectory("{$appPath}/tests/Unit", 0755, true);

        // Create composer.json
        // This file defines PHP dependencies, autoloading, and package metadata
        $this->info('Creating composer.json...');
        $composerJson = $this->generateComposerJson($name);
        $composerJsonContent = json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($composerJsonContent === false) {
            throw new RuntimeException('Failed to encode composer.json');
        }
        $filesystem->write("{$appPath}/composer.json", $composerJsonContent);

        // Create package.json
        // This file defines Turbo tasks and npm scripts for the application
        $this->info('Creating package.json...');
        $packageJson = $this->generatePackageJson($name);
        $packageJsonContent = json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($packageJsonContent === false) {
            throw new RuntimeException('Failed to encode package.json');
        }
        $filesystem->write("{$appPath}/package.json", $packageJsonContent);

        // Create index.php
        // This is the application entry point for web requests
        $this->info('Creating public/index.php...');
        $indexPhp = $this->generateIndexPhp($name);
        $filesystem->write("{$appPath}/public/index.php", $indexPhp);

        // Create README
        // This provides documentation and usage instructions for the application
        $this->info('Creating README.md...');
        $readme = $this->generateReadme($name);
        $filesystem->write("{$appPath}/README.md", $readme);

        // Create .gitkeep files
        // These preserve empty directories in version control
        $filesystem->write("{$appPath}/src/.gitkeep", '');
        $filesystem->write("{$appPath}/config/.gitkeep", '');

        // Display success message and next steps
        $this->line('');
        $this->outro("✓ Application '{$name}' created successfully!");
        $this->line('');
        $this->comment('Next steps:');
        $this->line("  1. cd apps/{$name}");
        $this->line('  2. composer install');
        $this->line("  3. mono dev --workspace={$name}");

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
            'description' => ucfirst(str_replace('-', ' ', $name)) . ' application',
            'type' => 'project',
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
                'dev' => 'php -S localhost:8000 -t public',
                'build' => 'echo "Build complete"',
                'test' => 'vendor/bin/phpunit',
                'test:unit' => 'vendor/bin/phpunit --testsuite=Unit',
                'lint' => 'vendor/bin/pint --test',
                'format' => 'vendor/bin/pint',
                'typecheck' => 'vendor/bin/phpstan analyse',
                'clean' => 'rm -rf .phpunit.cache .phpstan.cache',
                'deploy' => 'echo "Deploying application..."',
            ],
        ];
    }

    /**
     * Generate index.php content.
     */
    private function generateIndexPhp(string $name): string
    {
        $title = ucfirst(str_replace('-', ' ', $name));

        return <<<PHP
        <?php

        declare(strict_types=1);

        require_once __DIR__ . '/../vendor/autoload.php';

        // Simple application entry point
        echo "<h1>{$title} Application</h1>";
        echo "<p>Welcome to your new application!</p>";

        PHP;
    }

    /**
     * Generate README content.
     */
    private function generateReadme(string $name): string
    {
        $title = ucfirst(str_replace('-', ' ', $name));

        return <<<MD
        # {$title}

        {$title} application for the Mono PHP monorepo.

        ## Development

        ```bash
        # Start development server
        mono dev --workspace={$name}

        # Or directly
        php -S localhost:8000 -t public
        ```

        ## Testing

        ```bash
        composer test
        ```

        ## Deployment

        ```bash
        mono deploy --workspace={$name}
        ```

        ## License

        MIT
        MD;
    }

    /**
     * Convert app name to namespace.
     */
    private function nameToNamespace(string $name): string
    {
        $parts = explode('-', $name);
        $parts = array_map(ucfirst(...), $parts);

        return 'MonoPhp\\' . implode('', $parts);
    }
}
