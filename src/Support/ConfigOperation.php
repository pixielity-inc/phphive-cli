<?php

declare(strict_types=1);

namespace PhpHive\Cli\Support;

use InvalidArgumentException;

/**
 * Configuration Operation Value Object.
 *
 * Represents a single configuration operation to be performed on a config file.
 * This is an immutable value object that encapsulates the action type, target file,
 * and values to be written.
 *
 * Used by AppTypes to declare what configuration changes need to be applied
 * after installation, and processed by ConfigWriter to actually perform the changes.
 *
 * Supported Actions:
 * - set: Overwrite existing keys with new values (creates file if not exists)
 * - append: Add new keys without overwriting existing ones
 * - merge: Deep merge nested arrays (useful for PHP config files)
 *
 * File Format Detection:
 * The operation doesn't care about file format - that's handled by ConfigWriter
 * which detects format based on file extension (.env, .php, .yaml, etc.)
 *
 * @example
 * ```php
 * // Created via Config helper
 * $operation = Config::set('.env', ['DATABASE_HOST' => 'db']);
 *
 * // Or directly
 * $operation = new ConfigOperation('set', '.env', ['DATABASE_HOST' => 'db']);
 *
 * // Access properties
 * $action = $operation->getAction();  // 'set'
 * $file = $operation->getFile();      // '.env'
 * $values = $operation->getValues();  // ['DATABASE_HOST' => 'db']
 * ```
 */
final readonly class ConfigOperation
{
    /**
     * Valid action types.
     */
    private const array VALID_ACTIONS = ['set', 'append', 'merge'];

    /**
     * The action to perform (set, append, merge).
     */
    private string $action;

    /**
     * Create a new configuration operation.
     *
     * @param string               $action The action type (set, append, merge)
     * @param string               $file   The target file path
     * @param array<string, mixed> $values The configuration values
     *
     * @throws InvalidArgumentException If action type is invalid
     */
    public function __construct(string $action, private string $file, /**
     * The configuration values to write.
     */
        private array $values)
    {
        if (! in_array($action, self::VALID_ACTIONS, true)) {
            throw new InvalidArgumentException(
                "Invalid action '{$action}'. Must be one of: " . implode(', ', self::VALID_ACTIONS)
            );
        }

        $this->action = $action;
    }

    /**
     * Get the action type.
     *
     * @return string The action (set, append, merge)
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Get the target file path.
     *
     * @return string The file path relative to app root
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Get the configuration values.
     *
     * @return array<string, mixed> The values to write
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Check if this is a 'set' operation.
     *
     * @return bool True if action is 'set'
     */
    public function isSet(): bool
    {
        return $this->action === 'set';
    }

    /**
     * Check if this is an 'append' operation.
     *
     * @return bool True if action is 'append'
     */
    public function isAppend(): bool
    {
        return $this->action === 'append';
    }

    /**
     * Check if this is a 'merge' operation.
     *
     * @return bool True if action is 'merge'
     */
    public function isMerge(): bool
    {
        return $this->action === 'merge';
    }
}
