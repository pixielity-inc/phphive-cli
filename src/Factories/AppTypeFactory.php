<?php

declare(strict_types=1);

namespace PhpHive\Cli\Factories;

use InvalidArgumentException;
use PhpHive\Cli\AppTypes\LaravelAppType;
use PhpHive\Cli\AppTypes\MagentoAppType;
use PhpHive\Cli\AppTypes\SkeletonAppType;
use PhpHive\Cli\AppTypes\SymfonyAppType;
use PhpHive\Cli\Contracts\AppTypeInterface;

/**
 * App Type Factory.
 *
 * This factory class is responsible for creating and managing different
 * application type instances. It provides a centralized registry of all
 * available app types and handles their instantiation.
 *
 * The factory pattern allows:
 * - Centralized management of app types
 * - Easy addition of new app types
 * - Type-safe app type creation
 * - Discovery of available app types
 *
 * Registered app types:
 * - Laravel: Full-stack PHP framework
 * - Symfony: High-performance PHP framework
 * - Magento: Enterprise e-commerce platform
 * - Skeleton: Minimal PHP application
 *
 * Example usage:
 * ```php
 * // Get all available app types
 * $types = AppTypeFactory::getAvailableTypes();
 *
 * // Create a specific app type
 * $laravel = AppTypeFactory::create('laravel');
 *
 * // Get app type choices for prompts
 * $choices = AppTypeFactory::getChoices();
 * ```
 *
 * Adding a new app type:
 * 1. Create a new class implementing AppTypeInterface
 * 2. Add it to the $types array in getAvailableTypes()
 * 3. The factory will automatically handle creation
 *
 * @see AppTypeInterface
 */
final class AppTypeFactory
{
    /**
     * Get all available app types.
     *
     * Returns an associative array of app type identifiers mapped to their
     * class names. This registry defines all app types that can be created
     * by the factory.
     *
     * The identifier (key) is used:
     * - In command-line arguments
     * - For user selection in prompts
     * - As a unique identifier for the app type
     *
     * The class name (value) must:
     * - Implement AppTypeInterface
     * - Be instantiable without constructor arguments
     * - Provide getName() and getDescription() methods
     *
     * @return array<string, class-string<AppTypeInterface>> Map of identifier => class name
     */
    public static function getAvailableTypes(): array
    {
        return [
            'laravel' => LaravelAppType::class,
            'symfony' => SymfonyAppType::class,
            'magento' => MagentoAppType::class,
            'skeleton' => SkeletonAppType::class,
        ];
    }

    /**
     * Create an app type instance by identifier.
     *
     * Instantiates and returns an app type object based on the provided
     * identifier. The identifier must match one of the keys in the
     * available types registry.
     *
     * Example usage:
     * ```php
     * $laravel = AppTypeFactory::create('laravel');
     * $config = $laravel->collectConfiguration($input, $output);
     * ```
     *
     * @param  string           $type The app type identifier (e.g., 'laravel', 'symfony')
     * @return AppTypeInterface The instantiated app type object
     *
     * @throws InvalidArgumentException If the app type identifier is not registered
     */
    public static function create(string $type): AppTypeInterface
    {
        // Get the registry of available types
        $types = self::getAvailableTypes();

        // Check if the requested type exists in the registry
        if (! isset($types[$type])) {
            throw new InvalidArgumentException("Unknown app type: {$type}");
        }

        // Get the class name for the requested type
        $className = $types[$type];

        // Instantiate and return the app type
        return new $className();
    }

    /**
     * Get app type choices for interactive prompts.
     *
     * Returns an associative array suitable for use with Laravel Prompts
     * $this->select() function. The array maps display labels to app type identifiers.
     *
     * Format: ['Display Name (Description)' => 'identifier']
     *
     * Example output:
     * ```php
     * [
     *     'Laravel (Full-stack PHP framework)' => 'laravel',
     *     'Symfony (High-performance PHP framework)' => 'symfony',
     *     'Magento (Enterprise e-commerce platform)' => 'magento',
     *     'Skeleton (Minimal PHP application)' => 'skeleton',
     * ]
     * ```
     *
     * Example usage:
     * ```php
     * $type = $this->select(
     *     label: 'Select application type',
     *     options: AppTypeFactory::getChoices()
     * );
     * ```
     *
     * @return array<string, string> Map of display label => identifier
     */
    public static function getChoices(): array
    {
        $choices = [];

        // Iterate through all available types
        foreach (self::getAvailableTypes() as $identifier => $className) {
            // Instantiate the app type to get its name and description
            $instance = new $className();

            // Create a display label combining name and description
            $label = "{$instance->getName()} ({$instance->getDescription()})";

            // Map the label to the identifier
            $choices[$label] = $identifier;
        }

        return $choices;
    }

    /**
     * Check if an app type identifier is valid.
     *
     * Validates whether a given identifier corresponds to a registered
     * app type in the factory.
     *
     * Example usage:
     * ```php
     * if (AppTypeFactory::isValid('laravel')) {
     *     $app = AppTypeFactory::create('laravel');
     * }
     * ```
     *
     * @param  string $type The app type identifier to validate
     * @return bool   True if the identifier is valid, false otherwise
     */
    public static function isValid(string $type): bool
    {
        return isset(self::getAvailableTypes()[$type]);
    }

    /**
     * Get a list of all app type identifiers.
     *
     * Returns a simple array of all registered app type identifiers.
     * Useful for validation, documentation, or displaying available options.
     *
     * Example output: ['laravel', 'symfony', 'magento', 'skeleton']
     *
     * Example usage:
     * ```php
     * $validTypes = AppTypeFactory::getIdentifiers();
     * echo "Available types: " . implode(', ', $validTypes);
     * ```
     *
     * @return array<string> List of app type identifiers
     */
    public static function getIdentifiers(): array
    {
        return array_keys(self::getAvailableTypes());
    }
}
