<?php

declare(strict_types=1);

namespace PhpHive\Cli\Enums;

/**
 * PHP Version Enumeration.
 *
 * Defines all supported PHP versions for applications in the PhpHive monorepo.
 * Each PHP version has different features, performance characteristics, and
 * framework compatibility.
 *
 * PHP Release Cycle:
 * - New major version: Annually (November/December)
 * - Active support: 2 years
 * - Security support: 3 years total
 *
 * Version Support Timeline (as of 2026):
 * - PHP 8.4: Released Nov 2024, supported until Nov 2027
 * - PHP 8.3: Released Nov 2023, supported until Nov 2026
 * - PHP 8.2: Released Dec 2022, supported until Dec 2025
 * - PHP 8.1: Released Nov 2021, security fixes until Nov 2024 (EOL soon)
 *
 * Usage:
 * ```php
 * // Get version number
 * $version = PhpVersion::PHP_8_3->value; // '8.3'
 *
 * // Get display label
 * $label = PhpVersion::PHP_8_3->getLabel(); // 'PHP 8.3'
 *
 * // Get composer constraint
 * $constraint = PhpVersion::PHP_8_3->getComposerConstraint(); // '^8.3'
 *
 * // Check if supported
 * $supported = PhpVersion::PHP_8_3->isActivelySupported(); // true
 *
 * // Get choices for prompts
 * $choices = PhpVersion::choices();
 * ```
 *
 * @see https://www.php.net/supported-versions.php
 * @see https://www.php.net/releases/
 */
enum PhpVersion: string
{
    /**
     * PHP 8.4.
     *
     * Released: November 2024
     * Active Support: Until November 2026
     * Security Support: Until November 2027
     *
     * Key Features:
     * - Property hooks
     * - Asymmetric visibility
     * - New array functions
     * - Performance improvements
     * - JIT enhancements
     *
     * Framework Compatibility:
     * - Laravel: 11+, 12+
     * - Symfony: 7.2+
     * - Magento: Not yet supported
     *
     * Best for: New projects wanting latest features
     */
    case PHP_8_4 = '8.4';

    /**
     * PHP 8.3.
     *
     * Released: November 2023
     * Active Support: Until November 2025
     * Security Support: Until November 2026
     *
     * Key Features:
     * - Typed class constants
     * - Dynamic class constant fetch
     * - #[\Override] attribute
     * - json_validate() function
     * - Randomizer additions
     * - Performance improvements
     *
     * Framework Compatibility:
     * - Laravel: 10+, 11+, 12+
     * - Symfony: 6.4+, 7.x
     * - Magento: 2.4.7+
     *
     * Best for: Production applications (actively supported)
     * Recommended: Yes (good balance of features and stability)
     */
    case PHP_8_3 = '8.3';

    /**
     * PHP 8.2.
     *
     * Released: December 2022
     * Active Support: Until December 2024
     * Security Support: Until December 2025
     *
     * Key Features:
     * - Readonly classes
     * - Disjunctive Normal Form (DNF) types
     * - null, false, and true as standalone types
     * - New random extension
     * - Constants in traits
     * - Deprecate dynamic properties
     *
     * Framework Compatibility:
     * - Laravel: 10+, 11+, 12+
     * - Symfony: 6.4+, 7.x
     * - Magento: 2.4.6+, 2.4.7+
     *
     * Best for: Production applications requiring stability
     * Note: Active support ending soon, but still widely used
     */
    case PHP_8_2 = '8.2';

    /**
     * PHP 8.1.
     *
     * Released: November 2021
     * Active Support: Ended November 2023
     * Security Support: Until November 2024
     *
     * Key Features:
     * - Enumerations
     * - Readonly properties
     * - First-class callable syntax
     * - Fibers
     * - Intersection types
     * - Never return type
     *
     * Framework Compatibility:
     * - Laravel: 10+
     * - Symfony: 6.4+
     * - Magento: 2.4.5+, 2.4.6+
     *
     * Best for: Legacy projects
     * Note: Approaching end of life, upgrade recommended
     */
    case PHP_8_1 = '8.1';

    /**
     * Get the recommended PHP version for new projects.
     *
     * Returns the PHP version that balances features, stability, and support.
     *
     * @return self Recommended version
     */
    public static function recommended(): self
    {
        return self::PHP_8_3;
    }

    /**
     * Get the latest PHP version.
     *
     * Returns the most recent PHP version available.
     *
     * @return self Latest version
     */
    public static function latest(): self
    {
        return self::PHP_8_4;
    }

    /**
     * Get choices array for CLI prompts.
     *
     * Returns an associative array suitable for use with Laravel Prompts
     * select() function. Format: ['version' => 'Display Label']
     *
     * @return array<string, string> Map of version => display label
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::cases() as $case) {
            $choices[$case->value] = $case->getLabel();
        }

        return $choices;
    }

    /**
     * Get choices for a specific framework version.
     *
     * Returns only PHP versions compatible with the given framework version.
     *
     * @param  LaravelVersion|SymfonyVersion|MagentoVersion $frameworkVersion Framework version
     * @return array<string, string>                        Map of version => display label
     */
    public static function choicesForFramework(LaravelVersion|SymfonyVersion|MagentoVersion $frameworkVersion): array
    {
        $choices = [];

        foreach (self::cases() as $case) {
            $compatible = match (true) {
                $frameworkVersion instanceof LaravelVersion => $case->isCompatibleWithLaravel($frameworkVersion),
                $frameworkVersion instanceof SymfonyVersion => $case->isCompatibleWithSymfony($frameworkVersion),
                default => $case->isCompatibleWithMagento($frameworkVersion),
            };

            if ($compatible) {
                $choices[$case->value] = $case->getLabel();
            }
        }

        return $choices;
    }

    /**
     * Get the display label for CLI prompts.
     *
     * Returns a formatted label for the PHP version.
     *
     * @return string Display label (e.g., 'PHP 8.3')
     */
    public function getLabel(): string
    {
        return "PHP {$this->value}";
    }

    /**
     * Get the composer version constraint.
     *
     * Returns the version constraint used in composer.json require section.
     * Format: ^X.Y to allow patch updates within the minor version.
     *
     * Example: '^8.3' allows 8.3.0, 8.3.1, etc. but not 8.4.0
     *
     * @return string Composer version constraint
     */
    public function getComposerConstraint(): string
    {
        return "^{$this->value}";
    }

    /**
     * Check if this PHP version is actively supported.
     *
     * Active support means the version receives bug fixes and new features.
     * After active support ends, only security fixes are provided.
     *
     * @return bool True if actively supported (as of 2026)
     */
    public function isActivelySupported(): bool
    {
        return match ($this) {
            self::PHP_8_4 => true,
            self::PHP_8_3 => true,
            self::PHP_8_2 => false, // Active support ended Dec 2024
            self::PHP_8_1 => false, // Active support ended Nov 2023
        };
    }

    /**
     * Check if this PHP version receives security fixes.
     *
     * Security support continues for 1 year after active support ends.
     *
     * @return bool True if security fixes are still provided (as of 2026)
     */
    public function hasSecuritySupport(): bool
    {
        return match ($this) {
            self::PHP_8_4 => true,
            self::PHP_8_3 => true,
            self::PHP_8_2 => true,
            self::PHP_8_1 => false, // Security support ends Nov 2024
        };
    }

    /**
     * Check if compatible with Laravel version.
     *
     * @param  LaravelVersion $laravelVersion Laravel version
     * @return bool           True if compatible
     */
    public function isCompatibleWithLaravel(LaravelVersion $laravelVersion): bool
    {
        return match ($laravelVersion) {
            LaravelVersion::V12 => $this->value >= '8.2',
            LaravelVersion::V11 => $this->value >= '8.2',
            LaravelVersion::V10 => $this->value >= '8.1',
        };
    }

    /**
     * Check if compatible with Symfony version.
     *
     * @param  SymfonyVersion $symfonyVersion Symfony version
     * @return bool           True if compatible
     */
    public function isCompatibleWithSymfony(SymfonyVersion $symfonyVersion): bool
    {
        return match ($symfonyVersion) {
            SymfonyVersion::V7_4 => $this->value >= '8.2',
            SymfonyVersion::V7_3 => $this->value >= '8.2',
            SymfonyVersion::V7_2 => $this->value >= '8.2',
            SymfonyVersion::V6_4 => $this->value >= '8.1',
        };
    }

    /**
     * Check if compatible with Magento version.
     *
     * @param  MagentoVersion $magentoVersion Magento version
     * @return bool           True if compatible
     */
    public function isCompatibleWithMagento(MagentoVersion $magentoVersion): bool
    {
        return match ($magentoVersion) {
            MagentoVersion::V2_4_7 => in_array($this->value, ['8.2', '8.3'], true),
            MagentoVersion::V2_4_6 => in_array($this->value, ['8.1', '8.2'], true),
            MagentoVersion::V2_4_5 => $this->value === '8.1',
        };
    }
}
