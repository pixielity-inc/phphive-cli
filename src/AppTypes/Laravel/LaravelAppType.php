<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes\Laravel;

use Override;
use PhpHive\Cli\AppTypes\AbstractAppType;
use PhpHive\Cli\AppTypes\Laravel\Concerns\CollectsBasicConfiguration;
use PhpHive\Cli\AppTypes\Laravel\Concerns\CollectsOptionalPackages;
use PhpHive\Cli\AppTypes\Laravel\Concerns\CollectsStarterKitConfiguration;
use PhpHive\Cli\AppTypes\Laravel\Concerns\CollectsVersionConfiguration;
use PhpHive\Cli\AppTypes\Laravel\Concerns\ProvidesWritableConfiguration;
use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\Enums\DatabaseType;
use PhpHive\Cli\Enums\LaravelVersion;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Laravel Application Type.
 *
 * This class handles the scaffolding and configuration of Laravel applications
 * within the PhpHive monorepo. It orchestrates the collection of user preferences,
 * installation of Laravel, and setup of optional packages and features.
 *
 * Configuration is organized into focused concerns for maintainability:
 * - CollectsBasicConfiguration: Application name and description
 * - CollectsVersionConfiguration: Laravel version selection (10, 11, 12)
 * - CollectsStarterKitConfiguration: Authentication scaffolding (Breeze, Jetstream)
 * - CollectsDatabaseConfiguration: Database driver selection
 * - CollectsOptionalPackages: Optional packages (Horizon, Telescope, Sanctum, Octane)
 *
 * Installation workflow:
 * 1. collectConfiguration(): Gather all user preferences via interactive prompts
 * 2. getInstallCommand(): Return composer create-project command for Laravel
 * 3. getPostInstallCommands(): Return array of setup commands to run after installation
 * 4. getStubPath(): Provide path to Laravel-specific stub templates
 * 5. getStubVariables(): Provide variables for stub template replacement
 *
 * Post-installation setup includes:
 * - Application key generation
 * - Monorepo modules support (nwidart/laravel-modules)
 * - Starter kit installation (Breeze or Jetstream)
 * - Optional package installation (Horizon, Telescope, Sanctum, Octane)
 * - Database migration
 *
 * Example usage:
 * ```php
 * $laravelType = new LaravelAppType();
 * $config = $laravelType->collectConfiguration($input, $output);
 * $installCmd = $laravelType->getInstallCommand($config);
 * $postInstallCmds = $laravelType->getPostInstallCommands($config);
 * ```
 *
 * @see AbstractAppType Base class with common functionality
 * @see AppTypeInterface Interface defining the contract
 */
class LaravelAppType extends AbstractAppType
{
    use CollectsBasicConfiguration;
    use CollectsOptionalPackages;
    use CollectsStarterKitConfiguration;
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
     * @return string The display name "Laravel"
     */
    public function getName(): string
    {
        return 'Laravel';
    }

    /**
     * Get the application type description.
     *
     * Returns a brief description of Laravel, shown in the app type
     * selection menu to help users choose the right framework.
     *
     * @return string Brief description of Laravel
     */
    public function getDescription(): string
    {
        return 'Full-stack PHP framework with elegant syntax';
    }

    /**
     * Collect all configuration from user input.
     *
     * Orchestrates the collection of all Laravel-specific configuration by
     * calling methods from the various concerns. Each concern handles a
     * specific aspect of configuration.
     *
     * Configuration collected:
     * - Basic: name, description
     * - Version: Laravel version (10, 11, 12)
     * - Starter kit: none, breeze, jetstream
     * - Infrastructure: database, cache, queue, search, storage (via unified setup)
     * - Optional packages: Horizon, Telescope, Sanctum, Octane
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
        $config = array_merge($config, $this->collectStarterKitConfig());

        // NOTE: Infrastructure setup is now done in post-install phase
        // to avoid creating files before the app directory exists

        return array_merge($config, $this->collectOptionalPackagesConfig());
    }

    /**
     * Get the composer command to install Laravel.
     *
     * Returns the composer create-project command that installs Laravel
     * into the current directory. The version is determined by the user's
     * selection during configuration.
     *
     * Example output:
     * - "composer create-project laravel/laravel:12.x . --prefer-dist"
     * - "composer create-project laravel/laravel:11.x . --prefer-dist"
     *
     * @param  array<string, mixed> $config Configuration array from collectConfiguration()
     * @return string               The composer create-project command
     */
    public function getInstallCommand(array $config): string
    {
        // Extract version from config, default to latest if not specified
        $versionValue = $config[AppTypeInterface::CONFIG_LARAVEL_VERSION] ?? LaravelVersion::default()->value;
        $laravelVersion = LaravelVersion::from($versionValue);

        return $laravelVersion->getCreateProjectCommand('.') . ' --prefer-dist';
    }

    /**
     * Get commands to run after Laravel installation.
     *
     * Returns an array of shell commands to execute after the base Laravel
     * installation completes. These commands set up additional features,
     * packages, and configurations based on user selections.
     *
     * Commands executed (in order):
     * 1. Generate application key (required for encryption)
     * 2. Install and configure nwidart/laravel-modules for monorepo support
     * 3. Install selected starter kit (Breeze or Jetstream)
     * 4. Install optional packages (Horizon, Telescope, Sanctum, Octane)
     * 5. Run database migrations
     *
     * Monorepo setup:
     * - Configures composer to allow wikimedia/composer-merge-plugin
     * - Installs nwidart/laravel-modules for module management
     * - Publishes module configuration
     * - Updates config/modules.php to include packages directory
     *
     * @param  array<string, mixed> $config Configuration array from collectConfiguration()
     * @return array<string>        Array of shell commands to execute sequentially
     */
    public function getPostInstallCommands(array $config): array
    {
        // Start with essential Laravel setup command
        $commands = ['php artisan key:generate'];

        // Monorepo modules support - allows Laravel to work within monorepo structure
        // and discover modules/packages in the monorepo
        $commands[] = 'composer config --no-plugins allow-plugins.wikimedia/composer-merge-plugin true';
        $commands[] = 'composer require nwidart/laravel-modules';
        $commands[] = 'php artisan vendor:publish --provider="Nwidart\Modules\LaravelModulesServiceProvider"';

        // Update modules.php config to include packages directory for monorepo support
        $commands[] = <<<'PHP'
php -r "
\$config = file_get_contents('config/modules.php');
\$search = \"'modules' => base_path('Modules'),\";
\$replace = \"'modules' => base_path('Modules'),\n        'packages' => base_path('../../../packages'),\";
\$config = str_replace(\$search, \$replace, \$config);
file_put_contents('config/modules.php', \$config);
echo 'Updated modules.php to include packages path\n';
"
PHP;

        // Starter kit installation - Breeze or Jetstream
        if (($config[AppTypeInterface::CONFIG_STARTER_KIT] ?? 'none') === 'breeze') {
            $commands[] = 'composer require laravel/breeze --dev';
            $commands[] = 'php artisan breeze:install';
        } elseif (($config[AppTypeInterface::CONFIG_STARTER_KIT] ?? 'none') === 'jetstream') {
            $commands[] = 'composer require laravel/jetstream';
            $commands[] = 'php artisan jetstream:install livewire';
        }

        // Optional package: Laravel Horizon (queue monitoring)
        if (($config[AppTypeInterface::CONFIG_INSTALL_HORIZON] ?? false) === true) {
            $commands[] = 'composer require laravel/horizon';
            $commands[] = 'php artisan horizon:install';
        }

        // Optional package: Laravel Telescope (debugging assistant)
        if (($config[AppTypeInterface::CONFIG_INSTALL_TELESCOPE] ?? false) === true) {
            $commands[] = 'composer require laravel/telescope --dev';
            $commands[] = 'php artisan telescope:install';
        }

        // Optional package: Laravel Sanctum (API authentication)
        if (($config[AppTypeInterface::CONFIG_INSTALL_SANCTUM] ?? false) === true) {
            $commands[] = 'php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"';
        }

        // Optional package: Laravel Octane (high-performance server)
        if (($config[AppTypeInterface::CONFIG_INSTALL_OCTANE] ?? false) === true) {
            $commands[] = 'composer require laravel/octane';
            $server = $config[AppTypeInterface::CONFIG_OCTANE_SERVER] ?? 'roadrunner';
            $commands[] = "php artisan octane:install --server={$server}";
        }

        // Run database migrations to set up database schema
        $commands[] = 'php artisan migrate';

        return $commands;
    }

    /**
     * Get the stub template directory path.
     *
     * Returns the path to Laravel-specific stub templates, used with
     * Pixielity\StubGenerator\Facades\Stub::setBasePath() for template resolution.
     *
     * @return string Path to Laravel stub templates
     */
    public function getStubPath(): string
    {
        return $this->getBaseStubPath() . '/apps/laravel';
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
        $common = $this->getCommonStubVariables($config);

        // Get database driver using DatabaseType enum
        $databaseValue = $config[AppTypeInterface::CONFIG_DATABASE] ?? DatabaseType::MYSQL->value;
        $databaseType = DatabaseType::from($databaseValue);

        // Get Laravel version
        $versionValue = $config[AppTypeInterface::CONFIG_LARAVEL_VERSION] ?? LaravelVersion::default()->value;

        return [
            ...$common,
            AppTypeInterface::STUB_DATABASE_DRIVER => $databaseType->value,
            AppTypeInterface::STUB_LARAVEL_VERSION => $versionValue,
        ];
    }
}
