<?php

declare(strict_types=1);

namespace PhpHive\Cli\Support;

use function array_change_key_case;
use function array_chunk;
use function array_column;
use function array_combine;
use function array_diff;
use function array_diff_assoc;
use function array_diff_key;
use function array_fill;
use function array_fill_keys;
use function array_filter;
use function array_flip;
use function array_intersect;
use function array_intersect_assoc;
use function array_intersect_key;
use function array_is_list;
use function array_key_exists;
use function array_key_first;
use function array_key_last;
use function array_keys;
use function array_map;
use function array_merge;
use function array_pad;
use function array_pop;
use function array_product;
use function array_push;
use function array_rand;
use function array_reduce;
use function array_replace;
use function array_replace_recursive;
use function array_reverse;
use function array_search;
use function array_shift;
use function array_slice;
use function array_sum;
use function array_unique;
use function array_unshift;
use function array_values;
use function array_walk;
use function array_walk_recursive;
use function arsort;
use function asort;
use function call_user_func;
use function count;

use Illuminate\Support\Arr as BaseArr;

use function in_array;
use function is_int;
use function is_string;
use function krsort;
use function ksort;

use Override;

use function range;
use function rsort;
use function shuffle;
use function sort;
use function uasort;
use function uksort;
use function usort;

/**
 * Class Arr.
 *
 * This class extends Arr helper functionalities, providing additional methods
 * to manipulate arrays more conveniently, especially for translation and building
 * new arrays using callbacks.
 */
final class Arr extends BaseArr
{
    /**
     * Run a map over each of the items in the array.
     *
     *
     * @return array
     */
    public static function each(callable $callback, array $array)
    {
        return self::map($array, $callback);
    }

    /**
     * Build a new array using a callback function.
     *
     * This method iterates over each element in the provided array and applies the
     * specified callback to each key-value pair. The callback should return an array
     * containing the new key and value for the resulting array.
     *
     * @param  array    $array    The input array to be transformed.
     * @param  callable $callback A callback function that takes two parameters:
     *                            the key and value from the original array and
     *                            returns an array with the new key and value.
     * @return array    The newly built array with keys and values transformed
     *                  according to the callback.
     */
    public static function build($array, callable $callback): array
    {
        // Initialize an empty array to hold the results.
        $results = [];

        // Iterate over each key-value pair in the input array.
        foreach ($array as $key => $value) {
            // Call the provided callback function, which returns a new key and value.
            [$innerKey, $innerValue] = call_user_func($callback, $key, $value);

            // Assign the new key-value pair to the results array.
            $results[$innerKey] = $innerValue;
        }

        // Return the newly constructed array.
        return $results;
    }

    /**
     * Translate an array of strings, typically for dropdowns and checkbox list options.
     *
     * This method recursively walks through the provided array and translates
     * any string values using translation mechanism. The translated
     * values replace the original string values in the array.
     *
     * @param  array $arr The input array containing strings to be translated.
     * @return array The array with translated string values.
     */
    public static function trans(array $arr): array
    {
        // Use array_walk_recursive to apply a function to each value in the array.
        array_walk_recursive($arr, function (&$value, $key): void {
            // Check if the current value is a string.
            if (is_string($value)) {
                // For now, just keep the original value since __ function is not available
                // In a real implementation, you would use a translation service here
                // $translated = __($value);
                // $value = is_string($translated) ? $translated : (is_array($translated) ? (json_encode($translated) ?? $value) : (string) $translated);
            }
        });

        // Return the array with the translated values.
        return $arr;
    }

    /**
     * Get all the keys from the array.
     *
     * This method is a wrapper for PHP's `array_keys`, which returns all the keys of
     * an array. Optionally, it can also filter by a specific value.
     *
     * @param  array $array The input array.
     * @param  mixed $value (Optional) The value to filter keys by.
     * @return array The array of keys.
     */
    public static function keys(array $array, mixed $value = null): array
    {
        if ($value !== null) {
            return array_keys($array, $value, true);
        }

        return array_keys($array);
    }

    /**
     * Get all the values from the array.
     *
     * This method is a wrapper for PHP's `array_values`, which returns all the values
     * from an array, discarding the keys.
     *
     * @param  array $array The input array.
     * @return array The array of values.
     */
    public static function values(array $array): array
    {
        return array_values($array);
    }

    /**
     * Flip the keys and values of the given array.
     *
     * This method swaps the keys with their corresponding values in the input array.
     * If a value is not a string or integer, it will be skipped to avoid errors.
     * Note that if a value is duplicated, the last key-value pair will overwrite the others.
     *
     * @param  array $array The input array to flip.
     * @return array The array with keys and values swapped.
     */
    public static function flip(array $array): array
    {
        // Filter the array to include only string or integer values.
        $filteredArray = array_filter($array, fn ($value): bool => is_string($value) || is_int($value));

        // Perform the flip operation on the filtered array.
        return array_flip($filteredArray);
    }

    /**
     * Remove and return the first element of the array.
     *
     * This method is a wrapper for PHP's `array_shift`, which removes the first
     * element of the array and returns it. The array is re-indexed after the operation.
     *
     * @param  array $array The array to remove the first element from.
     * @return mixed The removed element.
     */
    public static function shift(array &$array): mixed
    {
        return array_shift($array);
    }

    /**
     * Create an array by using one array for keys and another for its values.
     *
     * This method is a wrapper for PHP's `array_combine`, which creates an array
     * by using the elements of one array as keys and the elements of another as values.
     *
     * @param  array $keys   The array of keys.
     * @param  array $values The array of values.
     * @return array The combined array.
     */
    public static function combine(array $keys, array $values): array
    {
        return array_combine($keys, $values);
    }

    /**
     * Check if a key exists in the array.
     *
     * This method is a wrapper for PHP's `array_key_exists`, which checks if the
     * given key exists in the array and returns a boolean result.
     *
     * @param  mixed $key   The key to check for.
     * @param  array $array The array to check.
     * @return bool  True if the key exists, otherwise false.
     */
    public static function keyExists(mixed $key, array $array): bool
    {
        return array_key_exists((string) $key, $array);
    }

    /**
     * Reduce the array to a single value.
     *
     * This method is a wrapper for PHP's `array_reduce`, which iterates over the
     * array and applies a callback function to accumulate a single result.
     *
     * @param  array    $array    The array to reduce.
     * @param  callable $callback The callback function to apply.
     * @param  mixed    $initial  The initial value to start the reduction.
     * @return mixed    The final reduced value.
     */
    public static function reduce(array $array, callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($array, $callback, $initial);
    }

    /**
     * Fill an array with values, using the given keys.
     *
     * @param  array $keys  The keys to be used in the resulting array.
     * @param  mixed $value The value to assign to each key.
     * @return array The filled array.
     */
    public static function fillKeys(array $keys, mixed $value): array
    {
        return array_fill_keys($keys, $value);
    }

    /**
     * Extract a slice of the array.
     *
     * @param  array    $array        The array to slice.
     * @param  int      $offset       The offset to start the slice.
     * @param  int|null $length       The length of the slice (optional).
     * @param  bool     $preserveKeys Whether to preserve keys (optional).
     * @return array    The sliced array.
     */
    public static function slice(array $array, int $offset, ?int $length = null, bool $preserveKeys = false): array
    {
        return array_slice($array, $offset, $length, $preserveKeys);
    }

    /**
     * Filter the elements of an array using a callback function.
     *
     * @param  array     $array    The array to filter.
     * @param  ?callable $callback The callback function to determine which elements to keep.
     * @return array     The filtered array.
     */
    public static function filter(array $array, ?callable $callback = null, int $mode = 0): array
    {
        return array_filter($array, $callback, $mode);
    }

    /**
     * Prepend one or more elements to the beginning of an array.
     *
     * @param  array $array     The array to modify.
     * @param  mixed ...$values The values to prepend.
     * @return int   The new number of elements in the array.
     */
    public static function unshift(array &$array, mixed ...$values): int
    {
        return array_unshift($array, ...$values);
    }

    /**
     * Change the case of all keys in an array.
     *
     * @param  array $array The array whose keys to change.
     * @param  int   $case  The case type (either CASE_UPPER or CASE_LOWER).
     * @return array The array with changed case for keys.
     */
    public static function changeKeyCase(array $array, int $case): array
    {
        return array_change_key_case($array, $case);
    }

    /**
     * Reverse the order of the elements in an array.
     *
     * @param  array $array        The array to reverse.
     * @param  bool  $preserveKeys Whether to preserve keys (optional).
     * @return array The reversed array.
     */
    public static function reverse(array $array, bool $preserveKeys = false): array
    {
        return array_reverse($array, $preserveKeys);
    }

    /**
     * Pad an array to a specified length with a given value.
     *
     * @param  array $array The array to pad.
     * @param  int   $size  The desired length of the array.
     * @param  mixed $value The value to pad the array with.
     * @return array The padded array.
     */
    public static function pad(array $array, int $size, mixed $value): array
    {
        return array_pad($array, $size, $value);
    }

    /**
     * Replace elements in an array with new values.
     *
     * This method allows replacing elements in the original array with corresponding values
     * from one or more replacement arrays. Non-recursive replacement is performed.
     *
     * @param  array $array           The original array.
     * @param  array ...$replacements One or more arrays containing replacement values.
     * @return array The array with replaced values.
     */
    public static function replace(array $array, array ...$replacements): array
    {
        return array_replace($array, ...$replacements);
    }

    /**
     * Recursively replace elements in an array with new values.
     *
     * This method performs a recursive replacement of elements in the original array
     * with corresponding values from one or more replacement arrays.
     *
     * @param  array $array           The original array.
     * @param  array ...$replacements One or more arrays containing replacement values.
     * @return array The array with recursively replaced values.
     */
    public static function replaceRecursive(array $array, array ...$replacements): array
    {
        return array_replace_recursive($array, ...$replacements);
    }

    /**
     * Get a column from a multi-dimensional array.
     *
     * @param  array $array     The input array.
     * @param  mixed $columnKey The column to retrieve.
     * @param  mixed $indexKey  (Optional) The index to use for the resulting array.
     * @return array The array containing the column's values.
     */
    public static function column(array $array, mixed $columnKey, mixed $indexKey = null): array
    {
        return array_column($array, $columnKey, $indexKey);
    }

    /**
     * Get a random key or value from an array.
     *
     * @param  array $array The input array.
     * @param  int   $num   (Optional) The number of random elements to retrieve.
     * @return mixed The random element(s) from the array.
     */
    public static function rand(array $array, int $num = 1): mixed
    {
        return array_rand($array, $num);
    }

    /**
     * Remove duplicate values from an array.
     *
     * @param  array $array The input array.
     * @return array The array with duplicate values removed.
     */
    public static function unique(array $array): array
    {
        return array_unique($array);
    }

    /**
     * Compute the difference of arrays.
     *
     * @param  array $array     The array to compare.
     * @param  array ...$arrays The arrays to compare against.
     * @return array The array containing the values that are not present in the other arrays.
     */
    public static function diff(array $array, array ...$arrays): array
    {
        return array_diff($array, ...$arrays);
    }

    /**
     * Compute the difference of array keys.
     *
     * @param  array ...$arrays The arrays to compare against.
     * @return array The array containing the values that are not present in the other arrays.
     */
    public static function diffKey(array ...$arrays): array
    {
        return array_diff_key(...$arrays);
    }

    /**
     * Fill an array with values.
     *
     * @param  int   $count The number of elements to insert.
     * @param  mixed $value The value to fill the array with.
     * @return array The filled array.
     */
    public static function fill(int $start_index, int $count, mixed $value): array
    {
        return array_fill($start_index, $count, $value);
    }

    /**
     * Pop the last element from an array.
     *
     * @param  array $array The array to pop from.
     * @return mixed The popped element.
     */
    public static function pop(array &$array): mixed
    {
        return array_pop($array);
    }

    /**
     * Check if an array is a list.
     *
     * @param  array $array The input array.
     * @return bool  True if the array is a list, otherwise false.
     */
    /**
     * @param array<array-key, mixed> $array
     */
    #[Override]
    public static function isList($array): bool
    {
        return array_is_list($array);
    }

    /**
     * Get the last key of an array.
     *
     * @param  array $array The input array.
     * @return mixed The last key in the array.
     */
    public static function keyLast(array $array): mixed
    {
        return array_key_last($array);
    }

    /**
     * Get the intersection of arrays.
     *
     * @param  array $array     The array to compare.
     * @param  array ...$arrays The arrays to compare against.
     * @return array The array containing the intersection of the arrays.
     */
    public static function intersect(array $array, array ...$arrays): array
    {
        return array_intersect($array, ...$arrays);
    }

    /**
     * Walk through the array and apply a callback function to each element.
     *
     * @param array    $array    The input array.
     * @param callable $callback The callback function to apply.
     */
    public static function walk(array &$array, callable $callback): void
    {
        array_walk($array, $callback);
    }

    /**
     * Search for a value in an array and return the key if found.
     *
     * @param  mixed $needle   The value to search for.
     * @param  array $haystack The array to search in.
     * @param  bool  $strict   Whether to use strict comparison (optional).
     * @return mixed The key of the found element, or false if not found.
     */
    public static function search(mixed $needle, array $haystack, bool $strict = false): mixed
    {
        return array_search($needle, $haystack, true);
    }

    /**
     * Merge one or more arrays into the original array.
     *
     * This method merges the given arrays into the current array using `array_merge`.
     * It combines the input arrays into a single array, with later arrays overriding
     * values from earlier ones if they have the same keys.
     *
     * @param  array ...$arrays Arrays to be merged with the current array.
     * @return array The resulting array after merging all input arrays.
     */
    public static function merge(array ...$arrays): array
    {
        // Use array_merge to merge all input arrays and return the result.
        return array_merge(...$arrays);
    }

    /**
     * Check if any element in the array satisfies the given callback.
     *
     * @param  array    $array    The input array.
     * @param  callable $callback The callback function to test each element.
     * @return bool     True if any element satisfies the callback, otherwise false.
     */
    public static function any(array $array, callable $callback): bool
    {
        return array_any($array, fn ($value, $key) => $callback($value, $key));
    }

    /**
     * Check if all elements in the array satisfy the given callback.
     *
     * @param  array    $array    The input array.
     * @param  callable $callback The callback function to test each element.
     * @return bool     True if all elements satisfy the callback, otherwise false.
     */
    public static function all(array $array, callable $callback): bool
    {
        return array_all($array, fn ($value, $key) => $callback($value, $key));
    }

    /**
     * Get the sum of values in an array.
     *
     * @param  array     $array The input array.
     * @return float|int The sum of all values.
     */
    public static function sum(array $array): int|float
    {
        return array_sum($array);
    }

    /**
     * Get the product of values in an array.
     *
     * @param  array     $array The input array.
     * @return float|int The product of all values.
     */
    public static function product(array $array): int|float
    {
        return array_product($array);
    }

    /**
     * Count all elements in an array.
     *
     * @param  array $array The input array.
     * @param  int   $mode  (Optional) COUNT_NORMAL or COUNT_RECURSIVE.
     * @return int   The number of elements in the array.
     */
    public static function count(array $array, int $mode = COUNT_NORMAL): int
    {
        // Ensure mode is either 0 (COUNT_NORMAL) or 1 (COUNT_RECURSIVE)
        $validMode = ($mode === COUNT_RECURSIVE) ? COUNT_RECURSIVE : COUNT_NORMAL;

        return count($array, $validMode);
    }

    /**
     * Split an array into chunks.
     *
     * @param  array $array        The input array.
     * @param  int   $length       The size of each chunk.
     * @param  bool  $preserveKeys Whether to preserve keys (optional).
     * @return array The chunked array.
     */
    public static function chunk(array $array, int $length, bool $preserveKeys = false): array
    {
        // Ensure length is at least 1
        $validLength = max(1, $length);

        return array_chunk($array, $validLength, $preserveKeys);
    }

    /**
     * Apply a callback function to each element of an array.
     *
     * @param  callable $callback The callback function to apply.
     * @param  array    $array    The input array.
     * @return array    The array with the callback applied to each element.
     */
    public static function mapValues(callable $callback, array $array): array
    {
        return array_map($callback, $array);
    }

    /**
     * Recursively walk through an array and apply a callback.
     *
     * @param array    $array    The input array.
     * @param callable $callback The callback function to apply.
     */
    public static function walkRecursive(array &$array, callable $callback): void
    {
        array_walk_recursive($array, $callback);
    }

    /**
     * Get the first key of an array.
     *
     * @param  array $array The input array.
     * @return mixed The first key in the array.
     */
    public static function keyFirst(array $array): mixed
    {
        return array_key_first($array);
    }

    /**
     * Compute the intersection of arrays using keys for comparison.
     *
     * @param  array $array     The array to compare.
     * @param  array ...$arrays The arrays to compare against.
     * @return array The array containing the intersection based on keys.
     */
    public static function intersectKey(array $array, array ...$arrays): array
    {
        return array_intersect_key($array, ...$arrays);
    }

    /**
     * Compute the difference of arrays with additional index check.
     *
     * @param  array $array     The array to compare.
     * @param  array ...$arrays The arrays to compare against.
     * @return array The array containing the difference.
     */
    public static function diffAssoc(array $array, array ...$arrays): array
    {
        return array_diff_assoc($array, ...$arrays);
    }

    /**
     * Compute the intersection of arrays with additional index check.
     *
     * @param  array $array     The array to compare.
     * @param  array ...$arrays The arrays to compare against.
     * @return array The array containing the intersection.
     */
    public static function intersectAssoc(array $array, array ...$arrays): array
    {
        return array_intersect_assoc($array, ...$arrays);
    }

    /**
     * Push one or more elements onto the end of an array (native PHP function).
     *
     * @param  array $array     The array to modify.
     * @param  mixed ...$values The values to push.
     * @return int   The new number of elements in the array.
     */
    public static function pushNative(array &$array, mixed ...$values): int
    {
        return array_push($array, ...$values);
    }

    /**
     * Create an array containing a range of elements.
     *
     * @param  mixed     $start The starting value.
     * @param  mixed     $end   The ending value.
     * @param  float|int $step  The step between values (optional).
     * @return array     The array containing the range.
     */
    public static function range(mixed $start, mixed $end, int|float $step = 1): array
    {
        return range($start, $end, $step);
    }

    /**
     * Check if a value exists in an array.
     *
     * @param  mixed $needle   The value to search for.
     * @param  array $haystack The array to search in.
     * @param  bool  $strict   Whether to use strict comparison (optional).
     * @return bool  True if the value exists, otherwise false.
     */
    public static function inArray(mixed $needle, array $haystack, bool $strict = false): bool
    {
        return in_array($needle, $haystack, true);
    }

    /**
     * Sort an array in ascending order (native PHP function).
     *
     * @param  array $array The array to sort.
     * @param  int   $flags Sort flags (optional).
     * @return bool  True on success, false on failure.
     */
    public static function sortNative(array &$array, int $flags = SORT_REGULAR): bool
    {
        return sort($array, $flags);
    }

    /**
     * Sort an array in descending order.
     *
     * @param  array $array The array to sort.
     * @param  int   $flags Sort flags (optional).
     * @return bool  True on success, false on failure.
     */
    public static function rsort(array &$array, int $flags = SORT_REGULAR): bool
    {
        return rsort($array, $flags);
    }

    /**
     * Sort an array by keys in ascending order.
     *
     * @param  array $array The array to sort.
     * @param  int   $flags Sort flags (optional).
     * @return bool  True on success, false on failure.
     */
    public static function ksort(array &$array, int $flags = SORT_REGULAR): bool
    {
        return ksort($array, $flags);
    }

    /**
     * Sort an array by keys in descending order.
     *
     * @param  array $array The array to sort.
     * @param  int   $flags Sort flags (optional).
     * @return bool  True on success, false on failure.
     */
    public static function krsort(array &$array, int $flags = SORT_REGULAR): bool
    {
        return krsort($array, $flags);
    }

    /**
     * Sort an array and maintain index association.
     *
     * @param  array $array The array to sort.
     * @param  int   $flags Sort flags (optional).
     * @return bool  True on success, false on failure.
     */
    public static function asort(array &$array, int $flags = SORT_REGULAR): bool
    {
        return asort($array, $flags);
    }

    /**
     * Sort an array in descending order and maintain index association.
     *
     * @param  array $array The array to sort.
     * @param  int   $flags Sort flags (optional).
     * @return bool  True on success, false on failure.
     */
    public static function arsort(array &$array, int $flags = SORT_REGULAR): bool
    {
        return arsort($array, $flags);
    }

    /**
     * Sort an array using a user-defined comparison function.
     *
     * @param  array    $array    The array to sort.
     * @param  callable $callback The comparison function.
     * @return bool     True on success, false on failure.
     */
    public static function usort(array &$array, callable $callback): bool
    {
        return usort($array, $callback);
    }

    /**
     * Sort an array by keys using a user-defined comparison function.
     *
     * @param  array    $array    The array to sort.
     * @param  callable $callback The comparison function.
     * @return bool     True on success, false on failure.
     */
    public static function uksort(array &$array, callable $callback): bool
    {
        return uksort($array, $callback);
    }

    /**
     * Sort an array with a user-defined comparison function and maintain index association.
     *
     * @param  array    $array    The array to sort.
     * @param  callable $callback The comparison function.
     * @return bool     True on success, false on failure.
     */
    public static function uasort(array &$array, callable $callback): bool
    {
        return uasort($array, $callback);
    }

    /**
     * Shuffle an array (native PHP function).
     *
     * @param  array $array The array to shuffle.
     * @return bool  True on success, false on failure.
     */
    public static function shuffleNative(array &$array): bool
    {
        return shuffle($array);
    }
}
