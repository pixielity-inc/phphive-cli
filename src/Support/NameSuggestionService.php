<?php

declare(strict_types=1);

namespace PhpHive\Cli\Support;

/**
 * Name Suggestion Service.
 *
 * Provides intelligent name suggestions when a chosen name is already taken.
 * Uses multiple strategies to generate alternative names that are likely to
 * be available and meaningful to the user.
 *
 * Suggestion Strategies:
 * - Suffix-based: Adds common suffixes like -app, -dev, -kit
 * - Hash-based: Adds short hash for uniqueness
 * - Year-based: Adds current year
 * - Prefix-based: Adds common prefixes like my-, new-
 *
 * Example usage:
 * ```php
 * $service = new NameSuggestionService();
 * $suggestions = $service->suggest('fuelers-core', 'workspace');
 * // Returns: ['fuelers-core-app', 'fuelers-core-x3f', 'fuelers-core-dev', ...]
 * ```
 */
final class NameSuggestionService
{
    /**
     * Common suffixes to append to names.
     *
     * @var array<string>
     */
    private const array SUFFIXES = [
        'app',
        'dev',
        'workspace',
        'kit',
        'core',
        'project',
    ];

    /**
     * Common prefixes to prepend to names.
     *
     * @var array<string>
     */
    private const array PREFIXES = [
        'my',
        'new',
        'fx',
    ];

    /**
     * Maximum number of suggestions to generate.
     */
    private const int MAX_SUGGESTIONS = 5;

    /**
     * Generate name suggestions for a taken name.
     *
     * Creates multiple alternative names using different strategies and
     * filters them to only include available names. Returns up to
     * MAX_SUGGESTIONS unique suggestions.
     *
     * @param  string                 $name              The original name that's taken
     * @param  string                 $type              Type of entity (workspace, app, package)
     * @param  callable(string): bool $availabilityCheck Callback to check if name is available
     * @return array<string>          Array of available name suggestions
     */
    public function suggest(string $name, string $type, callable $availabilityCheck): array
    {
        $suggestions = [];

        // Strategy 1: Suffix-based suggestions
        foreach (self::SUFFIXES as $suffix) {
            $suggestion = "{$name}-{$suffix}";
            if ($availabilityCheck($suggestion)) {
                $suggestions[] = $suggestion;
            }
        }

        // Strategy 2: Hash-based suggestions (for uniqueness)
        for ($i = 0; $i < 3; $i++) {
            $hash = substr(md5($name . time() . $i), 0, 3);
            $suggestion = "{$name}-{$hash}";
            if ($availabilityCheck($suggestion)) {
                $suggestions[] = $suggestion;
            }
        }

        // Strategy 3: Year-based suggestion
        $year = date('Y');
        $suggestion = "{$name}-{$year}";
        if ($availabilityCheck($suggestion)) {
            $suggestions[] = $suggestion;
        }

        // Strategy 4: Prefix-based suggestions
        foreach (self::PREFIXES as $prefix) {
            $suggestion = "{$prefix}-{$name}";
            if ($availabilityCheck($suggestion)) {
                $suggestions[] = $suggestion;
            }
        }

        // Strategy 5: Type-specific suffix
        $suggestion = "{$name}-{$type}";
        if ($availabilityCheck($suggestion)) {
            $suggestions[] = $suggestion;
        }

        // Remove duplicates and limit to MAX_SUGGESTIONS
        $suggestions = array_unique($suggestions);
        $suggestions = array_slice($suggestions, 0, self::MAX_SUGGESTIONS);

        return array_values($suggestions);
    }

    /**
     * Get the best suggestion from a list.
     *
     * Selects the most appropriate suggestion based on heuristics:
     * - Prefers shorter names
     * - Prefers suffix-based over hash-based
     * - Prefers meaningful suffixes (app, dev) over random hashes
     *
     * @param  array<string> $suggestions List of suggestions
     * @return string|null   Best suggestion or null if list is empty
     */
    public function getBestSuggestion(array $suggestions): ?string
    {
        if ($suggestions === []) {
            return null;
        }

        // Score each suggestion
        $scored = array_map(function ($suggestion): array {
            $score = 0;

            // Prefer shorter names
            $score -= strlen($suggestion);

            // Prefer meaningful suffixes
            foreach (self::SUFFIXES as $suffix) {
                if (str_ends_with($suggestion, "-{$suffix}")) {
                    $score += 10;

                    break;
                }
            }

            // Penalize hash-based suggestions
            if (preg_match('/-[a-f0-9]{3}$/', $suggestion) === 1) {
                $score -= 5;
            }

            return ['name' => $suggestion, 'score' => $score];
        }, $suggestions);

        // Sort by score (highest first)
        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $scored[0]['name'];
    }

    /**
     * Format suggestions for display.
     *
     * Creates a formatted array suitable for CLI display with
     * numbered options and highlighting for the best suggestion.
     *
     * @param  array<string>         $suggestions List of suggestions
     * @return array<string, string> Formatted suggestions with string keys
     */
    public function formatForDisplay(array $suggestions): array
    {
        $formatted = [];
        $best = $this->getBestSuggestion($suggestions);

        $index = 1;
        foreach ($suggestions as $suggestion) {
            $marker = $suggestion === $best ? ' (recommended)' : '';
            $formatted[(string) $index] = "{$suggestion}{$marker}";
            $index++;
        }

        // @phpstan-ignore-next-line
        return $formatted;
    }
}
