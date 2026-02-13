<?php

declare(strict_types=1);

namespace PhpHive\Cli\Enums;

use Illuminate\Support\Str;

/**
 * Laravel Version Enumeration.
 *
 * Defines all supported Laravel versions that can be installed via the CLI.
 * Each version has different PHP requirements, features, and support timelines.
 *
 * Laravel Release Cycle:
 * - Major releases: Every year (February)
 * - LTS (Long Term Support): Every 2 years
 * - Bug fixes: 18 months for LTS, 6 months for standard
 * - Security fixes: 2 years for LTS, 1 year for standard
 *
 * Version Support Timeline:
 * - Laravel 10 (LTS): Released Feb 2023, supported until Feb 2025
 * - Laravel 11 (LTS): Released Mar 2024, supported until Mar 2026
 * - Laravel 12: Released Feb 2026, supported until Aug 2026
 *
 * Usage:
 * ```php
 * // Get version number
 * $version = LaravelVersion::V12->value; // '12'
 *
 * // Get display label
 * $label = LaravelVersion::V12->getLabel(); // 'Laravel 12 (Latest)'
 *
 * // Get PHP requirement
 * $php = LaravelVersion::V12->getPhpRequirement(); // '8.2'
 *
 * // Check if LTS
 * $isLts = LaravelVersion::V11->isLts(); // true
 *
 * // Get composer constraint
 * $constraint = LaravelVersion::V12->getComposerConstraint(); // '12.x'
 *
 * // Get choices for prompts
 * $choices = LaravelVersion::choices();
 * ```
 *
 * @see https://laravel.com/docs/releases
 * @see https://laravel.com/docs/master/releases#support-policy
 */
enum LaravelVersion: string
{
    /**
     * Laravel 12 (Latest).
     *
     * Released: February 2026
     * PHP Requirement: 8.2+
     * Support: Bug fixes until August 2026, Security fixes until February 2027
     *
     * New Features:
     * - Latest framework improvements
     * - Performance enhancements
     * - New developer experience features
     * - Updated dependencies
     *
     * Best for: New projects that can upgrade frequently
     */
    case V12 = '12';

    /**
     * Laravel 11 (LTS - Long Term Support).
     *
     * Released: March 2024
     * PHP Requirement: 8.2+
     * Support: Bug fixes until March 2026, Security fixes until March 2027
     *
     * Key Features:
     * - Streamlined application structure
     * - Per-second rate limiting
     * - Health routing
     * - Graceful encryption key rotation
     * - Queue interaction testing improvements
     * - Resend mail notification
     * - Prompt validator integration
     *
     * Best for: Production applications requiring long-term stability
     * Recommended: Yes (LTS version with extended support)
     */
    case V11 = '11';

    /**
     * Laravel 10 (Previous LTS).
     *
     * Released: February 2023
     * PHP Requirement: 8.1+
     * Support: Bug fixes until February 2025, Security fixes until February 2026
     *
     * Key Features:
     * - Native type declarations
     * - Laravel Pennant (feature flags)
     * - Process layer improvements
     * - Test profiling
     * - Pest scaffolding
     * - Generator CLI prompts
     *
     * Best for: Projects requiring PHP 8.1 compatibility
     * Note: Approaching end of bug fix support
     */
    case V10 = '10';

    /**
     * Get choices array for CLI prompts.
     *
     * Returns an associative array suitable for use with Laravel Prompts
     * select() function. Format: ['Display Label' => 'value']
     *
     * Example output:
     * ```php
     * [
     *     'v12' => 'Laravel 12 (Latest)',
     *     'v11' => 'Laravel 11 (LTS)',
     *     'v10' => 'Laravel 10',
     * ]
     * ```
     *
     * Note: Keys are prefixed with 'v' to match the prompt format used
     * in CollectsVersionConfiguration trait.
     *
     * @return array<string, string> Map of key => display label
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::cases() as $case) {
            $choices["v{$case->value}"] = $case->getLabel();
        }

        return $choices;
    }

    /**
     * Get the default recommended version.
     *
     * Returns the version that should be selected by default in prompts.
     * Currently defaults to V12 (latest) for new projects.
     *
     * @return self Default version
     */
    public static function default(): self
    {
        return self::V12;
    }

    /**
     * Create from version string with 'v' prefix.
     *
     * Accepts version strings like 'v12', 'v11', 'v10' and returns
     * the corresponding enum case. Strips the 'v' prefix if present.
     *
     * @param  string    $version Version string (e.g., 'v12' or '12')
     * @return self|null Enum case or null if invalid
     */
    public static function fromString(string $version): ?self
    {
        // Strip 'v' prefix if present
        $version = Str::ltrim($version, 'v');

        return self::tryFrom($version);
    }

    /**
     * Get the display label for CLI prompts.
     *
     * Returns a formatted label indicating the version and its status
     * (Latest, LTS, or standard version).
     *
     * @return string Display label (e.g., 'Laravel 12 (Latest)', 'Laravel 11 (LTS)')
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::V12 => 'Laravel 12 (Latest)',
            self::V11 => 'Laravel 11 (LTS)',
            self::V10 => 'Laravel 10',
        };
    }

    /**
     * Get the minimum PHP version requirement.
     *
     * Returns the minimum PHP version required to run this Laravel version.
     * Used for validation and documentation.
     *
     * @return string PHP version (e.g., '8.2', '8.1')
     */
    public function getPhpRequirement(): string
    {
        return match ($this) {
            self::V12 => '8.2',
            self::V11 => '8.2',
            self::V10 => '8.1',
        };
    }

    /**
     * Check if this is an LTS (Long Term Support) version.
     *
     * LTS versions receive extended bug fixes (18 months) and security fixes (2 years).
     * Recommended for production applications requiring long-term stability.
     *
     * @return bool True if LTS version
     */
    public function isLts(): bool
    {
        return match ($this) {
            self::V11 => true,
            self::V10 => true,
            self::V12 => false,
        };
    }

    /**
     * Get the composer version constraint.
     *
     * Returns the version constraint used in composer create-project command.
     * Format: {major}.x to get the latest minor and patch versions.
     *
     * Example: '12.x' installs Laravel 12.0.0, 12.1.0, etc. (latest 12.x)
     *
     * @return string Composer version constraint
     */
    public function getComposerConstraint(): string
    {
        return "{$this->value}.x";
    }

    /**
     * Get the composer create-project command.
     *
     * Returns the full composer command to create a new Laravel project
     * with this version.
     *
     * @param  string $directory Target directory (use '.' for current directory)
     * @return string Composer create-project command
     */
    public function getCreateProjectCommand(string $directory = '.'): string
    {
        return "composer create-project laravel/laravel:{$this->getComposerConstraint()} {$directory}";
    }
}
