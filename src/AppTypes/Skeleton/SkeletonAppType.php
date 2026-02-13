<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes\Skeleton;

use Override;
use PhpHive\Cli\AppTypes\AbstractAppType;
use PhpHive\Cli\AppTypes\Skeleton\Concerns\CollectsBasicConfiguration;
use PhpHive\Cli\AppTypes\Skeleton\Concerns\CollectsQualityToolsConfiguration;
use PhpHive\Cli\AppTypes\Skeleton\Concerns\ProvidesWritableConfiguration;
use PhpHive\Cli\Contracts\AppTypeInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Skeleton Application Type.
 *
 * This class handles the scaffolding of minimal PHP applications with just
 * Composer and optional quality tools. It's ideal for:
 * - Microservices
 * - CLI tools
 * - Libraries and packages
 * - Custom PHP applications without a framework
 * - Learning projects
 *
 * The Skeleton app type provides:
 * - Basic composer.json with PSR-4 autoloading
 * - Optional PHPUnit for testing
 * - Optional quality tools (PHPStan for static analysis, Pint for code formatting)
 * - Minimal directory structure (src/, tests/)
 * - No framework dependencies
 *
 * Configuration is organized into focused concerns:
 * - CollectsBasicConfiguration: Name, description, PHP version
 * - CollectsQualityToolsConfiguration: Testing and quality tools
 *
 * Installation workflow:
 * 1. collectConfiguration(): Gather user preferences
 * 2. Generate files from stub templates (composer.json, directory structure)
 * 3. getInstallCommand(): Returns empty string (no installation needed)
 * 4. getPostInstallCommands(): Run composer install and optional tests
 *
 * Unlike framework-based app types (Laravel, Symfony), Skeleton doesn't use
 * composer create-project. Instead, it generates all files from stub templates.
 *
 * Example usage:
 * ```php
 * $skeletonType = new SkeletonAppType();
 * $config = $skeletonType->collectConfiguration($input, $output);
 * // Files are generated from stubs
 * $postInstallCmds = $skeletonType->getPostInstallCommands($config);
 * ```
 *
 * @see AbstractAppType Base class with common functionality
 * @see AppTypeInterface Interface defining the contract
 */
class SkeletonAppType extends AbstractAppType
{
    use CollectsBasicConfiguration;
    use CollectsQualityToolsConfiguration;
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
     * @return string The display name "Skeleton"
     */
    public function getName(): string
    {
        return 'Skeleton';
    }

    /**
     * Get the application type description.
     *
     * Returns a brief description of the Skeleton app type, shown in the
     * app type selection menu to help users choose the right option.
     *
     * @return string Brief description of Skeleton app type
     */
    public function getDescription(): string
    {
        return 'Minimal PHP application with Composer';
    }

    /**
     * Collect all configuration from user input.
     *
     * Orchestrates the collection of all Skeleton-specific configuration by
     * calling methods from the various concerns.
     *
     * Configuration collected:
     * - Basic: name, description, PHP version
     * - Quality tools: PHPUnit, PHPStan, Pint
     * - Infrastructure: none (minimal app)
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

        // NOTE: Infrastructure setup is now done in post-install phase
        // to avoid creating files before the app directory exists
        // (Skeleton apps typically don't need infrastructure anyway)

        return array_merge($config, $this->collectQualityToolsConfig());
    }

    /**
     * Get the composer command to install the application.
     *
     * For Skeleton apps, there's no installation command because all files
     * are generated from stub templates. Returns an empty string.
     *
     * @param  array<string, mixed> $config Configuration array from collectConfiguration()
     * @return string               Empty string (no installation needed)
     */
    public function getInstallCommand(array $config): string
    {
        // Skeleton apps don't use composer create-project
        // All files are generated from stub templates
        return '';
    }

    /**
     * Get commands to run after file generation.
     *
     * Returns an array of shell commands to execute after the skeleton files
     * have been generated from stub templates.
     *
     * Commands executed (in order):
     * 1. composer install: Install dependencies defined in composer.json
     * 2. composer test: Run PHPUnit tests (if tests are included)
     *
     * @param  array<string, mixed> $config Configuration array from collectConfiguration()
     * @return array<string>        Array of shell commands to execute sequentially
     */
    public function getPostInstallCommands(array $config): array
    {
        // Start with composer install to set up autoloading and dependencies
        $commands = ['composer install'];

        // If tests are included, run them to verify the setup
        if (($config[AppTypeInterface::CONFIG_INCLUDE_TESTS] ?? true) === true) {
            $commands[] = 'composer test';
        }

        return $commands;
    }

    /**
     * Get the stub template directory path.
     *
     * Returns the path to Skeleton-specific stub templates, used with
     * Pixielity\StubGenerator\Facades\Stub::setBasePath() for template resolution.
     *
     * Stub templates include:
     * - composer.json: Package definition with dependencies
     * - src/: Source code directory structure
     * - tests/: Test directory structure (if tests enabled)
     * - phpunit.xml: PHPUnit configuration (if tests enabled)
     * - phpstan.neon: PHPStan configuration (if quality tools enabled)
     * - pint.json: Pint configuration (if quality tools enabled)
     *
     * @return string Path to Skeleton stub templates
     */
    public function getStubPath(): string
    {
        return $this->getBaseStubPath() . '/apps/skeleton';
    }

    /**
     * Get variables for stub template replacement.
     *
     * Returns an associative array used by Pixielity\StubGenerator\Facades\Stub
     * for placeholder replacement in stub templates. Keys are automatically
     * converted to UPPERCASE and wrapped with $KEY$ or {{KEY}} delimiters.
     *
     * Variables provided:
     * - Common variables (name, namespace, package name, description)
     * - PHP version (minimum required PHP version)
     *
     * Example usage in stub templates:
     * ```json
     * {
     *   "name": "{{PACKAGE_NAME}}",
     *   "require": {
     *     "php": "^{{PHP_VERSION}}"
     *   }
     * }
     * ```
     *
     * @param  array<string, mixed>  $config Configuration array from collectConfiguration()
     * @return array<string, string> Lowercase keys (auto-converted to UPPERCASE by stub generator)
     */
    public function getStubVariables(array $config): array
    {
        // Get common variables (name, namespace, package name, description)
        $common = $this->getCommonStubVariables($config);

        return [
            ...$common,
            // PHP version for composer.json require section
            AppTypeInterface::STUB_PHP_VERSION => $config[AppTypeInterface::CONFIG_PHP_VERSION] ?? '8.3',
        ];
    }
}
