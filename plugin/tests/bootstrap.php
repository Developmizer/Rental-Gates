<?php
/**
 * PHPUnit Bootstrap
 *
 * Loads the WordPress test framework and the Rental Gates plugin.
 *
 * Usage:
 *   WP_TESTS_DIR=/path/to/wordpress-tests-lib phpunit
 *
 * @package RentalGates\Tests
 */

// Path to WordPress test library (set via environment or default)
$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find {$_tests_dir}/includes/functions.php\n";
    echo "Set WP_TESTS_DIR to point to the WordPress test library.\n";
    exit(1);
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin for testing.
 */
function _manually_load_plugin() {
    require dirname(__DIR__) . '/rental-gates.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';
