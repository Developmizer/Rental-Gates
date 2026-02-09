<?php
/**
 * Cache Test
 *
 * Tests cache remember pattern (false value handling) and group flushing.
 *
 * @package RentalGates\Tests\Unit
 */

class CacheTest extends WP_UnitTestCase {

    public function test_remember_caches_false_values() {
        $call_count = 0;
        $callback = function() use (&$call_count) {
            $call_count++;
            return false;
        };

        Rental_Gates_Cache::remember('test_false_key', 'test_group', $callback, 300);
        Rental_Gates_Cache::remember('test_false_key', 'test_group', $callback, 300);

        // Callback should only be called once - second call should hit cache
        $this->assertEquals(1, $call_count);
    }

    public function test_remember_returns_cached_value() {
        $callback = function() {
            return 'hello world';
        };

        $first = Rental_Gates_Cache::remember('test_val', 'test_group', $callback, 300);
        $second = Rental_Gates_Cache::remember('test_val', 'test_group', $callback, 300);

        $this->assertEquals('hello world', $first);
        $this->assertEquals('hello world', $second);
    }

    public function test_flush_group_invalidates_keys() {
        // Set a value
        Rental_Gates_Cache::set('key1', 'value1', 'flush_test', 300);

        // Verify it's there
        $before = Rental_Gates_Cache::get('key1', 'flush_test');
        $this->assertEquals('value1', $before);

        // Flush the group
        Rental_Gates_Cache::flush_group('flush_test');

        // Value should no longer be retrievable (different versioned key)
        $after = Rental_Gates_Cache::get('key1', 'flush_test');
        $this->assertFalse($after);
    }

    public function test_set_and_get() {
        Rental_Gates_Cache::set('basic_key', 42, 'basic_group', 300);
        $value = Rental_Gates_Cache::get('basic_key', 'basic_group');
        $this->assertEquals(42, $value);
    }

    public function test_delete() {
        Rental_Gates_Cache::set('del_key', 'to_delete', 'del_group', 300);
        Rental_Gates_Cache::delete('del_key', 'del_group');

        $value = Rental_Gates_Cache::get('del_key', 'del_group');
        $this->assertFalse($value);
    }
}
