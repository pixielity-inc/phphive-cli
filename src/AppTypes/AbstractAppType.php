<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes;

use function array_key_first;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

use PhpHive\Cli\Contracts\AppTypeInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract Base Class for Application Types.
 *
 * This abstract class provides common functionality and helper methods for all
 * application type implementations. It serves as the foundation for creating
 * different types of applications (Laravel, Symfony, Skeleton, etc.) within
 * the monorepo.
 *
 * Key responsibilities:
 * - Provide helper methods for interactive prompts (text, confirm, select)
 * - Handle name normalization and namespace generation
 * - Manage stub paths and variable replacement
 * - Define common configuration patterns
 *
 * Each concrete app type (LaravelAppType, SymfonyAppType, etc.) extends this
 * class and implements the AppTypeInterface methods to define its specific
 * scaffolding behavior.
 *
 * Example usage:
 * ```php
 * class MyAppType extends AbstractAppType {
 *     public function collectConfiguration(...) {
 *         $name = $this->askText('App name', 'my-app');
 *         $useDb = $this->askConfirm('Use database?');
 *         return compact('name', 'useDb');
 *     }
 * }
 * ```
 *
 * @see AppTypeInterface
 */
abstract class AbstractAppType implements AppTypeInterface
{
    /**
     * Symfony Console input interface.
     *
     * Provides access to command-line arguments and options during the
     * configuration collection process. Set by concrete implementations
     * in their collectConfiguration() method.
     */
    protected InputInterface $input;

    /**
     * Symfony Console output interface.
     *
     * Provides access to console output for displaying messages, progress,
     * and other information during the scaffolding process. Set by concrete
     * implementations in their collectConfiguration() method.
     */
    protected OutputInterface $output;

    /**
     * Get the base stub directory path.
     *
     * Returns the absolute path to the root stubs directory where all
     * application type stub templates are stored. This directory contains
     * subdirectories for each app type (laravel-app, symfony-app, etc.).
     *
     * The path is calculated relative to this file's location:
     * - Current file: cli/src/AppTypes/AbstractAppType.php
     * - Stubs directory: cli/stubs/
     *
     * @return string Absolute path to the stubs directory
     */
    protected function getBaseStubPath(): string
    {
        // Go up two directories from src/AppTypes to reach cli root, then into stubs
        return dirname(__DIR__, 2) . '/stubs';
    }

    /**
     * Ask a text input question with validation.
     *
     * Displays an interactive text input prompt using Laravel Prompts.
     * Supports placeholders, default values, and required validation.
     *
     * In non-interactive mode (--no-interaction flag), returns the default value
     * or an empty string if no default is provided.
     *
     * Example usage:
     * ```php
     * $name = $this->askText(
     *     label: 'Application name',
     *     placeholder: 'my-awesome-app',
     *     default: 'app',
     *     required: true
     * );
     * ```
     *
     * @param  string      $label       The question label to display
     * @param  string      $placeholder Placeholder text shown in the input field
     * @param  string|null $default     Default value if user presses enter without input
     * @param  bool        $required    Whether the input is required (cannot be empty)
     * @return string      The user's input value
     */
    protected function askText(string $label, string $placeholder = '', ?string $default = null, bool $required = true): string
    {
        // In non-interactive mode, return default value
        if (! $this->input->isInteractive()) {
            return $default ?? '';
        }

        return text(
            label: $label,
            placeholder: $placeholder,
            default: $default ?? '',
            required: $required
        );
    }

    /**
     * Ask a yes/no confirmation question.
     *
     * Displays an interactive confirmation prompt using Laravel Prompts.
     * The user can answer with yes/no, y/n, or press enter for the default.
     *
     * In non-interactive mode (--no-interaction flag), returns the default value.
     *
     * Example usage:
     * ```php
     * $installTests = $this->askConfirm(
     *     label: 'Install PHPUnit for testing?',
     *     default: true
     * );
     * ```
     *
     * @param  string $label   The question label to display
     * @param  bool   $default Default value (true = yes, false = no)
     * @return bool   True if user confirmed, false otherwise
     */
    protected function askConfirm(string $label, bool $default = true): bool
    {
        // In non-interactive mode, return default value
        if (! $this->input->isInteractive()) {
            return $default;
        }

        return confirm(
            label: $label,
            default: $default
        );
    }

    /**
     * Ask a multiple-choice selection question.
     *
     * Displays an interactive selection menu using Laravel Prompts.
     * The user can navigate options with arrow keys and select with enter.
     *
     * In non-interactive mode (--no-interaction flag), returns the default value
     * or the first option if no default is provided.
     *
     * Example usage:
     * ```php
     * $version = $this->askSelect(
     *     label: 'PHP version',
     *     options: [
     *         '8.3' => 'PHP 8.3 (Recommended)',
     *         '8.2' => 'PHP 8.2',
     *         '8.1' => 'PHP 8.1',
     *     ],
     *     default: '8.3'
     * );
     * ```
     *
     * @param  string                $label   The question label to display
     * @param  array<string, string> $options Associative array of value => label pairs
     * @param  string|null           $default Default selected value (must be a key in $options)
     * @return string                The selected option's key
     */
    protected function askSelect(string $label, array $options, ?string $default = null): string
    {
        // In non-interactive mode, return default or first option
        if (! $this->input->isInteractive()) {
            return $default ?? (string) array_key_first($options);
        }

        $result = select(
            label: $label,
            options: $options,
            default: $default
        );

        return (string) $result;
    }

    /**
     * Normalize application name to valid directory/package name.
     *
     * Converts an application name into a format suitable for:
     * - Directory names (lowercase, hyphen-separated)
     * - Composer package names (lowercase, hyphen-separated)
     * - URL slugs
     *
     * Transformation rules:
     * - Converts to lowercase
     * - Replaces non-alphanumeric characters (except hyphens) with hyphens
     * - Preserves existing hyphens
     *
     * Examples:
     * - "My Awesome App" → "my-awesome-app"
     * - "API_Gateway" → "api-gateway"
     * - "user@service" → "user-service"
     *
     * @param  string $name The original application name
     * @return string The normalized name suitable for directories and packages
     */
    protected function normalizeAppName(string $name): string
    {
        // Replace any non-alphanumeric character (except hyphens) with a hyphen
        // Then convert to lowercase for consistency
        return strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $name) ?? $name);
    }

    /**
     * Convert application name to PHP namespace format.
     *
     * Transforms an application name into a valid PHP namespace component
     * following PSR-4 naming conventions.
     *
     * Transformation rules:
     * - Removes hyphens and underscores
     * - Capitalizes first letter of each word
     * - Results in PascalCase format
     *
     * Examples:
     * - "my-app" → "MyApp"
     * - "api_gateway" → "ApiGateway"
     * - "user-service" → "UserService"
     *
     * The resulting namespace component can be used in:
     * - PSR-4 autoload configuration
     * - Class namespaces
     * - Package namespaces
     *
     * @param  string $name The application name (can contain hyphens/underscores)
     * @return string The PascalCase namespace component
     */
    protected function nameToNamespace(string $name): string
    {
        // Capitalize first letter of each word (delimited by - or _)
        // Then remove the delimiters to create PascalCase
        return str_replace(['-', '_'], '', ucwords($name, '-_'));
    }

    /**
     * Get common stub template variables.
     *
     * Generates a standard set of variables used for replacing placeholders
     * in stub template files. These variables are common across all app types
     * and provide basic application metadata.
     *
     * Generated variables:
     * - {{APP_NAME}}: Original application name as entered by user
     * - {{APP_NAME_NORMALIZED}}: Normalized name for directories/packages
     * - {{APP_NAMESPACE}}: PascalCase namespace component
     * - {{PACKAGE_NAME}}: Full Composer package name (phphive/app-name)
     * - {{DESCRIPTION}}: Application description or default
     *
     * Example usage in stub files:
     * ```json
     * {
     *   "name": "{{PACKAGE_NAME}}",
     *   "description": "{{DESCRIPTION}}",
     *   "autoload": {
     *     "psr-4": {
     *       "PhpHive\\{{APP_NAMESPACE}}\\": "src/"
     *     }
     *   }
     * }
     * ```
     *
     * Concrete app types can extend this array with their own specific
     * variables by calling parent::getCommonStubVariables() and merging
     * additional variables.
     *
     * @param  array<string, mixed>  $config Configuration array from collectConfiguration()
     * @return array<string, string> Associative array of placeholder => value pairs
     */
    protected function getCommonStubVariables(array $config): array
    {
        // Extract app name from config, default to 'app' if not provided
        $appName = $config['name'] ?? 'app';

        // Normalize the name for use in directories and package names
        $normalizedName = $this->normalizeAppName($appName);

        return [
            // Original name as entered by user
            '{{APP_NAME}}' => $appName,

            // Normalized name for directories and package names (lowercase, hyphenated)
            '{{APP_NAME_NORMALIZED}}' => $normalizedName,

            // PascalCase namespace component for PHP classes
            '{{APP_NAMESPACE}}' => $this->nameToNamespace($appName),

            // Full Composer package name following phphive/* convention
            '{{PACKAGE_NAME}}' => "phphive/{$normalizedName}",

            // Application description from config or generated default
            '{{DESCRIPTION}}' => $config['description'] ?? "Application: {$appName}",
        ];
    }
}
