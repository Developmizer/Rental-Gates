<?php
if (!defined('ABSPATH')) exit;

/**
 * Health Check & System Monitoring
 *
 * Provides a REST API endpoint for monitoring system health,
 * database status, service availability, and performance metrics.
 *
 * Public endpoint (no auth):  GET /rental-gates/v1/health
 * Detailed endpoint (admin):  GET /rental-gates/v1/health/detailed
 *
 * @package RentalGates
 * @since 2.42.0
 */
class Rental_Gates_Health {

    /**
     * Register REST routes.
     */
    public static function register_routes() {
        register_rest_route('rental-gates/v1', '/health', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'handle_health'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('rental-gates/v1', '/health/detailed', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'handle_detailed'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));
    }

    // ─── Public Health Check ────────────────────────────────────

    /**
     * Lightweight health probe for uptime monitors and load balancers.
     * Returns 200 if the plugin is operational, 503 if degraded.
     */
    public static function handle_health() {
        $start = microtime(true);

        $db_ok = self::check_database();
        $healthy = $db_ok;

        $status_code = $healthy ? 200 : 503;

        $response = new WP_REST_Response(array(
            'status'  => $healthy ? 'healthy' : 'degraded',
            'version' => defined('RENTAL_GATES_VERSION') ? RENTAL_GATES_VERSION : 'unknown',
            'time_ms' => round((microtime(true) - $start) * 1000, 1),
        ), $status_code);

        $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }

    // ─── Detailed Health Check (Admin Only) ─────────────────────

    /**
     * Comprehensive system check for the admin dashboard.
     * Returns detailed status for database, services, and environment.
     */
    public static function handle_detailed() {
        $start = microtime(true);

        $checks = array(
            'database'    => self::check_database_detailed(),
            'tables'      => self::check_tables(),
            'stripe'      => self::check_stripe(),
            'ai'          => self::check_ai(),
            'email'       => self::check_email(),
            'cache'       => self::check_cache(),
            'environment' => self::check_environment(),
            'cron'        => self::check_cron(),
        );

        $all_ok = true;
        foreach ($checks as $check) {
            if (($check['status'] ?? 'fail') === 'fail') {
                $all_ok = false;
                break;
            }
        }

        $response = new WP_REST_Response(array(
            'status'  => $all_ok ? 'healthy' : 'degraded',
            'version' => defined('RENTAL_GATES_VERSION') ? RENTAL_GATES_VERSION : 'unknown',
            'db_version' => get_option('rental_gates_db_version', 'unknown'),
            'checks'  => $checks,
            'time_ms' => round((microtime(true) - $start) * 1000, 1),
        ), $all_ok ? 200 : 503);

        $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }

    // ─── Individual Checks ──────────────────────────────────────

    private static function check_database() {
        global $wpdb;
        $result = $wpdb->get_var('SELECT 1');
        return $result == 1;
    }

    private static function check_database_detailed() {
        global $wpdb;

        $start = microtime(true);
        $result = $wpdb->get_var('SELECT 1');
        $latency = round((microtime(true) - $start) * 1000, 1);

        if ($result != 1) {
            return array('status' => 'fail', 'message' => 'Cannot connect to database');
        }

        $version = $wpdb->get_var('SELECT VERSION()');

        return array(
            'status'     => 'pass',
            'latency_ms' => $latency,
            'version'    => $version,
            'prefix'     => $wpdb->prefix,
        );
    }

    private static function check_tables() {
        global $wpdb;

        if (!class_exists('Rental_Gates_Database')) {
            return array('status' => 'fail', 'message' => 'Database class not loaded');
        }

        $tables = Rental_Gates_Database::get_table_names();
        $missing = array();

        foreach ($tables as $key => $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            return array(
                'status'  => 'fail',
                'message' => 'Missing tables: ' . implode(', ', $missing),
                'total'   => count($tables),
                'missing' => count($missing),
            );
        }

        return array(
            'status' => 'pass',
            'total'  => count($tables),
        );
    }

    private static function check_stripe() {
        if (!class_exists('Rental_Gates_Stripe')) {
            return array('status' => 'warn', 'message' => 'Stripe class not loaded');
        }

        $configured = Rental_Gates_Stripe::is_configured();

        return array(
            'status'     => $configured ? 'pass' : 'warn',
            'configured' => $configured,
            'message'    => $configured ? 'Stripe keys configured' : 'Stripe not configured',
        );
    }

    private static function check_ai() {
        if (!class_exists('Rental_Gates_AI')) {
            return array('status' => 'warn', 'message' => 'AI class not loaded');
        }

        $ai = function_exists('rental_gates_ai') ? rental_gates_ai() : null;
        $configured = $ai && $ai->is_configured();

        return array(
            'status'     => $configured ? 'pass' : 'warn',
            'configured' => $configured,
            'message'    => $configured ? 'AI provider configured' : 'AI not configured',
        );
    }

    private static function check_email() {
        return array(
            'status'    => 'pass',
            'transport' => defined('WPMS_PLUGIN_VER') ? 'smtp_plugin' : 'wp_mail',
        );
    }

    private static function check_cache() {
        $start = microtime(true);
        $test_key = 'rg_health_check_' . time();
        wp_cache_set($test_key, 'ok', 'rental_gates', 10);
        $result = wp_cache_get($test_key, 'rental_gates');
        $latency = round((microtime(true) - $start) * 1000, 2);

        $using_external = wp_using_ext_object_cache();

        return array(
            'status'     => ($result === 'ok') ? 'pass' : 'warn',
            'latency_ms' => $latency,
            'backend'    => $using_external ? 'external' : 'default',
        );
    }

    private static function check_environment() {
        return array(
            'status'      => 'pass',
            'php_version' => PHP_VERSION,
            'wp_version'  => get_bloginfo('version'),
            'wp_debug'    => defined('WP_DEBUG') && WP_DEBUG,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max'  => ini_get('upload_max_filesize'),
        );
    }

    private static function check_cron() {
        $next_scheduled = wp_next_scheduled('rental_gates_daily_cron');
        $overdue = $next_scheduled && $next_scheduled < (time() - 86400);

        return array(
            'status'    => $overdue ? 'warn' : 'pass',
            'next_run'  => $next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : 'not scheduled',
            'wp_cron'   => !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON),
        );
    }
}
