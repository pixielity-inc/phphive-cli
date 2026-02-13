<?php

declare(strict_types=1);

namespace PhpHive\Cli\AppTypes\Laravel\Concerns;

use PhpHive\Cli\Contracts\AppTypeInterface;
use PhpHive\Cli\Enums\LaravelVersion;

/**
 * Collects Laravel version configuration.
 *
 * This trait prompts the user to select which major version of Laravel to install.
 * Different versions have different PHP requirements and feature sets:
 *
 * - Laravel 12: Latest version with newest features (PHP 8.2+)
 * - Laravel 11: LTS (Long Term Support) version with extended support (PHP 8.2+)
 * - Laravel 10: Previous LTS version (PHP 8.1+)
 *
 * The version selection determines:
 * - Which Laravel version is installed via composer create-project
 * - PHP version requirements
 * - Available features and packages
 * - Support timeline
 */
trait CollectsVersionConfiguration
{
    /**
     * Collect Laravel version selection.
     *
     * Prompts the user to choose a Laravel version. The version is returned
     * without the 'v' prefix (e.g., '12' instead of 'v12') for use in
     * composer commands.
     *
     * @return array<string, mixed> Configuration array with CONFIG_LARAVEL_VERSION key (numeric string)
     */
    protected function collectVersionConfig(): array
    {
        // Prompt for Laravel version selection
        $versionKey = $this->select(
            label: 'Laravel version',
            options: LaravelVersion::choices(),
            default: 'v' . LaravelVersion::default()->value
        );

        $version = LaravelVersion::fromString((string) $versionKey);

        return [
            // Return numeric version string for use in composer commands
            // e.g., '12' for "laravel/laravel:12.x"
            AppTypeInterface::CONFIG_LARAVEL_VERSION => $version->value,
        ];
    }
}
