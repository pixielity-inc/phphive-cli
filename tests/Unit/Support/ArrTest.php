<?php

declare(strict_types=1);

namespace MonoPhp\Cli\Tests\Unit\Support;

use function array_values;

use MonoPhp\Cli\Support\Arr;
use MonoPhp\Cli\Tests\TestCase;

/**
 * Array Helper Test.
 *
 * Tests for the Arr utility class that extends Laravel's array helpers.
 * Verifies array manipulation, transformation, filtering, and utility methods.
 */
final class ArrTest extends TestCase
{
    /**
     * Test that build() transforms an array using a callback.
     *
     * Verifies that the build method applies a callback to each key-value pair
     * and constructs a new array from the returned key-value pairs.
     */
    public function test_build_transforms_array_with_callback(): void
    {
        // Input array with key-value pairs
        $input = ['a' => 1, 'b' => 2];

        // Transform keys and values using callback
        $result = Arr::build($input, fn ($key, $value) => [$key . '_new', $value * 2]);

        // Assert the transformation is correct
        $this->assertEquals(['a_new' => 2, 'b_new' => 4], $result);
    }

    /**
     * Test that keys() returns all array keys.
     *
     * Verifies that the method extracts all keys from an associative array.
     */
    public function test_keys_returns_array_keys(): void
    {
        $input = ['a' => 1, 'b' => 2, 'c' => 3];
        $result = Arr::keys($input);

        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    /**
     * Test that values() returns all array values.
     *
     * Verifies that the method extracts all values and reindexes the array.
     */
    public function test_values_returns_array_values(): void
    {
        $input = ['a' => 1, 'b' => 2, 'c' => 3];
        $result = Arr::values($input);

        $this->assertEquals([1, 2, 3], $result);
    }

    /**
     * Test that flip() swaps keys and values.
     *
     * Verifies that array keys become values and values become keys.
     */
    public function test_flip_swaps_keys_and_values(): void
    {
        $input = ['a' => 'x', 'b' => 'y'];
        $result = Arr::flip($input);

        $this->assertEquals(['x' => 'a', 'y' => 'b'], $result);
    }

    /**
     * Test that combine() creates an array from separate keys and values.
     *
     * Verifies that two arrays are combined into a single associative array.
     */
    public function test_combine_creates_array_from_keys_and_values(): void
    {
        $keys = ['a', 'b', 'c'];
        $values = [1, 2, 3];
        $result = Arr::combine($keys, $values);

        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3], $result);
    }

    /**
     * Test that keyExists() checks for key existence.
     *
     * Verifies that the method correctly identifies existing and non-existing keys.
     */
    public function test_key_exists_checks_for_key(): void
    {
        $input = ['a' => 1, 'b' => 2];

        $this->assertTrue(Arr::keyExists('a', $input));
        $this->assertFalse(Arr::keyExists('c', $input));
    }

    /**
     * Test that unique() removes duplicate values.
     *
     * Verifies that duplicate values are removed from the array.
     */
    public function test_unique_removes_duplicates(): void
    {
        $input = [1, 2, 2, 3, 3, 3];
        $result = Arr::unique($input);

        $this->assertEquals([1, 2, 3], array_values($result));
    }

    /**
     * Test that diff() returns the difference between arrays.
     *
     * Verifies that values present in the first array but not in others are returned.
     */
    public function test_diff_returns_difference(): void
    {
        $array1 = [1, 2, 3, 4];
        $array2 = [3, 4, 5, 6];
        $result = Arr::diff($array1, $array2);

        $this->assertEquals([1, 2], array_values($result));
    }

    /**
     * Test that intersect() returns the intersection of arrays.
     *
     * Verifies that only values present in all arrays are returned.
     */
    public function test_intersect_returns_intersection(): void
    {
        $array1 = [1, 2, 3, 4];
        $array2 = [3, 4, 5, 6];
        $result = Arr::intersect($array1, $array2);

        $this->assertEquals([3, 4], array_values($result));
    }

    /**
     * Test that merge() combines multiple arrays.
     *
     * Verifies that arrays are merged with later values overwriting earlier ones.
     */
    public function test_merge_combines_arrays(): void
    {
        $array1 = ['a' => 1, 'b' => 2];
        $array2 = ['b' => 3, 'c' => 4];
        $result = Arr::merge($array1, $array2);

        $this->assertEquals(['a' => 1, 'b' => 3, 'c' => 4], $result);
    }

    /**
     * Test that chunk() splits an array into chunks.
     *
     * Verifies that the array is divided into smaller arrays of specified size.
     */
    public function test_chunk_splits_array(): void
    {
        $input = [1, 2, 3, 4, 5];
        $result = Arr::chunk($input, 2);

        $this->assertEquals([[1, 2], [3, 4], [5]], $result);
    }

    /**
     * Test that filter() removes elements based on callback.
     *
     * Verifies that only elements passing the callback test are retained.
     */
    public function test_filter_removes_elements(): void
    {
        $input = [1, 2, 3, 4, 5];
        $result = Arr::filter($input, fn ($value) => $value > 2);

        $this->assertEquals([3, 4, 5], array_values($result));
    }

    /**
     * Test that inArray() checks if a value exists in the array.
     *
     * Verifies that the method correctly identifies existing and non-existing values.
     */
    public function test_in_array_checks_value_existence(): void
    {
        $input = [1, 2, 3];

        $this->assertTrue(Arr::inArray(2, $input));
        $this->assertFalse(Arr::inArray(4, $input));
    }

    /**
     * Test that sum() calculates the total of all values.
     *
     * Verifies that all numeric values in the array are summed correctly.
     */
    public function test_sum_calculates_total(): void
    {
        $input = [1, 2, 3, 4, 5];
        $result = Arr::sum($input);

        $this->assertEquals(15, $result);
    }

    /**
     * Test that product() calculates the product of all values.
     *
     * Verifies that all numeric values in the array are multiplied together.
     */
    public function test_product_calculates_product(): void
    {
        $input = [2, 3, 4];
        $result = Arr::product($input);

        $this->assertEquals(24, $result);
    }

    /**
     * Test that count() returns the number of elements.
     *
     * Verifies that the method correctly counts array elements.
     */
    public function test_count_returns_element_count(): void
    {
        $input = [1, 2, 3, 4, 5];
        $result = Arr::count($input);

        $this->assertEquals(5, $result);
    }
}
