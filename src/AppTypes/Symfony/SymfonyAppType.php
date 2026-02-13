<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes\Symfony;

use Override;
use PhpHive\Cli\AppTypes\AbstractAppType;
use PhpHive\Cli\AppTypes\Symfony\Concerns\CollectsBasicConfiguration;
use PhpHive\Cli\AppTypes\Symfony\Concerns\CollectsOptionalBundles;
use PhpHive\Cli\AppTypes\Symfony\Concerns\CollectsProjectTypeConfiguration;
use PhpHive\Cli\AppTypes\Symfony\Concerns\CollectsVersionConfiguration;
use PhpHive\Cli\AppTypes\Symfony\Concerns\ProvidesWritableConfiguration;
use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\Enums\DatabaseType;
use PhpHive\Cli\Enums\SymfonyVersion;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Symfony Application Type.
 *
 * This class handles the scaffolding and configuration of Symfony applications
 * within the PhpHive monorepo. Symfony is a high-performance PHP framework
 * known for its flexibility, reusable components, and enterprise-grade features.
 *
 * Configuration is organized into focused concerns for maintainability:
 * - CollectsBasicConfiguration: Application name and description
 * - CollectsVersionConfiguration: Symfony version selection (6.4 LTS, 7.0, 7.1 LTS)
 * - CollectsProjectTypeConfiguration: Web app vs microservice/API
 * - CollectsDatabaseConfiguration: Database driver selection
 * - CollectsOptionalBundles: Maker Bundle, Security Bundle
 *
 * Project types:
 * - Web Application: Full-featured with Twig, forms, security, etc. (webapp-pack)
 * - Microservice/API: Minimal skeleton for APIs and microservices
 *
 * Installation workflow:
 * 1. collectConfiguration(): Gather all user preferences via interactive prompts
 * 2. getInstallCommand(): Return composer create-project command for Symfony skeleton
 * 3. getPostInstallCommands(): Return array of setup commands to run after installation
 * 4. getStubPath(): Provide path to Symfony-specific stub templates
 * 5. getStubVariables(): Provide variables for stub template replacement
 *
 * Post-installation setup includes:
 * - Installing webapp-pack (if web application selected)
 * - Installing Maker Bundle for code generation (if selected)
 * - Installing Security Bundle for authentication (if selected)
 * - Installing ORM pack for database access
 * - Creating database and running migrations
 *
 * Example usage:
 * ```php
 * $symfonyType = new SymfonyAppType();
 * $config = $symfonyType->collectConfiguration($input, $output);
 * $installCmd = $symfonyType->getInstallCommand($config);
 * $postInstallCmds = $symfonyType->getPostInstallCommands($config);
 * ```
 *
 * @see AbstractAppType Base class with common functionality
 * @see AppTypeInterface Interface defining the contract
 */
class SymfonyAppType extends AbstractAppType
{
    use CollectsBasicConfiguration;
    use CollectsOptionalBundles;
    use CollectsProjectTypeConfiguration;
    use CollectsVersionConfiguration;
    use ProvidesWritableConfiguration;

    /**
     * Configuration array collected during collectConfiguration().
     *
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * Get the application type name.
     *
     * Returns the display name for this application type, shown in the
     * app type selection menu during `php phive create:app`.
     *
     * @return string The display name "Symfony"
     */
    public function getName(): string
    {
        return 'Symfony';
    }

    /**
     * Get the application type description.
     *
     * Returns a brief description of Symfony, shown in the app type
     * selection menu to help users choose the right framework.
     *
     * @return string Brief description of Symfony
     */
    public function getDescription(): string
    {
        return 'High-performance PHP framework for web applications';
    }

    /**
     * Collect all configuration from user input.
     *
     * Orchestrates the collection of all Symfony-specific configuration by
     * calling methods from the various concerns. Each concern handles a
     * specific aspect of configuration.
     *
     * Configuration collected:
     * - Basic: name, description
     * - Version: Symfony version (6.4 LTS, 7.0, 7.1 LTS)
     * - Project type: webapp (full-featured) or skeleton (minimal)
     * - Infrastructure: database, cache, queue, search, storage (via unified setup)
     * - Optional bundles: Maker Bundle, Security Bundle
     *
     * @param  InputInterface       $input  Console input interface
     * @param  OutputInterface      $output Console output interface
     * @return array<string, mixed> Complete configuration array
     */
    #[Override]
    public function collectConfiguration(InputInterface $input, OutputInterface $output): array
    {
        // Store input/output for use in trait methods
        $this->input = $input;
        $this->output = $output;

        // Collect app-specific configuration
        $config = [];
        $config = array_merge($config, $this->collectBasicConfig());
        $config = array_merge($config, $this->collectVersionConfig());
        $config = array_merge($config, $this->collectProjectTypeConfig());

        // NOTE: Infrastructure setup is now done in post-install phase
        // to avoid creating files before the app directory exists

        return array_merge($config, $this->collectOptionalBundlesConfig());
    }

    /**
     * Get the composer command to install Symfony.
     *
     * Returns the composer create-project command that installs Symfony skeleton
     * into the current directory. The version is determined by the user's
     * selection during configuration.
     *
     * Symfony skeleton is a minimal installation that includes only core components.
     * Additional features (webapp-pack, bundles) are installed in post-install commands.
     *
     * Example output:
     * - "composer create-project symfony/skeleton:7.1.* ."
     * - "composer create-project symfony/skeleton:6.4.* ."
     *
     * @param  array<string, mixed> $config Configuration array from collectConfiguration()
     * @return string               The composer create-project command
     */
    public function getInstallCommand(array $config): string
    {
        // Extract version from config, default to latest LTS if not specified
        $versionValue = $config[AppTypeInterface::CONFIG_SYMFONY_VERSION] ?? SymfonyVersion::default()->value;
        $symfonyVersion = SymfonyVersion::from($versionValue);

        // Get app name from config
        $appName = $config[AppTypeInterface::CONFIG_NAME] ?? 'my-app';

        // Use composer create-project to install Symfony skeleton
        // This will create the app directory, so it must be run from parent directory
        return $symfonyVersion->getCreateSkeletonCommand($appName) . ' --no-interaction';
    }

    /**
     * Get commands to run after Symfony installation.
     *
     * Returns an array of shell commands to execute after the base Symfony
     * skeleton installation completes. These commands install additional
     * packages and set up the database based on user selections.
     *
     * Commands executed (in order):
     * 1. Install webapp-pack (if web application selected) - adds Twig, forms, security, etc.
     * 2. Install Maker Bundle (if selected) - code generation tools
     * 3. Install Security Bundle (if selected) - authentication and authorization
     * 4. Install ORM pack - Doctrine ORM for database access
     * 5. Create database (if it doesn't exist)
     * 6. Run database migrations
     *
     * Webapp-pack includes:
     * - Twig templating engine
     * - Form component
     * - Security component
     * - Asset management
     * - Translation
     * - And more...
     *
     * @param  array<string, mixed> $config Configuration array from collectConfiguration()
     * @return array<string>        Array of shell commands to execute sequentially
     */
    public function getPostInstallCommands(array $config): array
    {
        $commands = [];

        // Install webapp-pack for full-featured web applications
        // This adds Twig, forms, security, and other web-focused components
        if (($config[AppTypeInterface::CONFIG_PROJECT_TYPE] ?? 'webapp') === 'webapp') {
            $commands[] = 'composer require symfony/webapp-pack';
        }

        // Install Maker Bundle for code generation (make:controller, make:entity, etc.)
        // Installed as dev dependency as it's only needed during development
        if (($config[AppTypeInterface::CONFIG_INSTALL_MAKER] ?? true) === true) {
            $commands[] = 'composer require --dev symfony/maker-bundle';
        }

        // Install Security Bundle for authentication and authorization
        // Provides login, user management, and access control features
        if (($config[AppTypeInterface::CONFIG_INSTALL_SECURITY] ?? true) === true) {
            $commands[] = 'composer require symfony/security-bundle';
        }

        // Install ORM pack (Doctrine ORM + related packages)
        // Provides database abstraction, entity management, and migrations
        $commands[] = 'composer require symfony/orm-pack';

        // Create database if it doesn't exist
        // Uses DATABASE_URL from .env file
        $commands[] = 'php bin/console doctrine:database:create --if-not-exists';

        // Run database migrations to set up schema
        // --no-interaction flag prevents prompts during automated setup
        $commands[] = 'php bin/console doctrine:migrations:migrate --no-interaction';

        return $commands;
    }

    /**
     * Get the stub template directory path.
     *
     * Returns the path to Symfony-specific stub templates, used with
     * Pixielity\StubGenerator\Facades\Stub::setBasePath() for template resolution.
     *
     * @return string Path to Symfony stub templates
     */
    public function getStubPath(): string
    {
        return $this->getBaseStubPath() . '/apps/symfony';
    }

    /**
     * Get variables for stub template replacement.
     *
     * Returns an associative array used by Pixielity\StubGenerator\Facades\Stub
     * for placeholder replacement. Keys are automatically converted to UPPERCASE
     * and wrapped with $KEY$ or {{KEY}} delimiters.
     *
     * @param  array<string, mixed>  $config Configuration array
     * @return array<string, string> Lowercase keys (auto-converted to UPPERCASE)
     */
    public function getStubVariables(array $config): array
    {
        // Get common variables (name, namespace, package name, description)
        $common = $this->getCommonStubVariables($config);

        // Get database driver using DatabaseType enum
        $databaseValue = $config[AppTypeInterface::CONFIG_DATABASE] ?? DatabaseType::MYSQL->value;
        $databaseType = DatabaseType::from($databaseValue);

        // Get Symfony version
        $versionValue = $config[AppTypeInterface::CONFIG_SYMFONY_VERSION] ?? SymfonyVersion::default()->value;

        return [
            ...$common,
            // Database driver for DATABASE_URL in .env
            AppTypeInterface::STUB_DATABASE_DRIVER => $databaseType->value,
            // Symfony version for documentation and compatibility notes
            AppTypeInterface::STUB_SYMFONY_VERSION => $versionValue,
        ];
    }
}
