<?php
/**
 * Abstract Base Model
 *
 * Provides common CRUD, pagination, formatting, slug generation,
 * and transaction support for all Rental Gates models.
 *
 * @package RentalGates
 * @since 2.42.0
 */

if (!defined('ABSPATH')) exit;

abstract class Rental_Gates_Base_Model {

    /**
     * Table key in Rental_Gates_Database::get_table_names().
     * Must be set by each child class.
     *
     * @var string
     */
    protected static $table_key = '';

    /**
     * Resolved full table name cache (per-class).
     *
     * @var array
     */
    private static $table_name_cache = array();

    /**
     * JSON fields that need decoding in format().
     * Override in child classes.
     *
     * @var array
     */
    protected static $json_fields = array();

    /**
     * Integer fields that need casting in format().
     *
     * @var array
     */
    protected static $int_fields = array('id', 'organization_id');

    /**
     * Float fields that need casting in format().
     *
     * @var array
     */
    protected static $float_fields = array();

    /**
     * Get the fully qualified table name for the current model.
     *
     * @return string
     */
    protected static function table() {
        $class = static::class;
        if (!isset(self::$table_name_cache[$class])) {
            $tables = Rental_Gates_Database::get_table_names();
            $key = static::$table_key;
            self::$table_name_cache[$class] = isset($tables[$key]) ? $tables[$key] : '';
        }
        return self::$table_name_cache[$class];
    }

    /**
     * Get a single record by ID.
     *
     * @param int $id Primary key
     * @return array|null Formatted record or null
     */
    public static function get($id) {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . static::table() . " WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ? static::format($row) : null;
    }

    /**
     * Delete a record by ID.
     *
     * @param int $id Primary key
     * @return true|WP_Error
     */
    public static function delete($id) {
        global $wpdb;
        $result = $wpdb->delete(static::table(), array('id' => $id), array('%d'));
        if ($result === false) {
            return new WP_Error('delete_failed', __('Delete failed', 'rental-gates'));
        }
        return true;
    }

    /**
     * Count records for an organization, optionally filtered by status.
     *
     * @param int    $org_id Organization ID
     * @param string $status Optional status filter
     * @return int
     */
    public static function count_for_organization($org_id, $status = '') {
        global $wpdb;
        $table = static::table();

        $where = $wpdb->prepare("WHERE organization_id = %d", $org_id);
        if (!empty($status)) {
            $where .= $wpdb->prepare(" AND status = %s", $status);
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where}");
    }

    /**
     * Paginated list for an organization.
     *
     * @param int   $org_id Organization ID
     * @param array $args   Pagination/filter args
     * @return array {items: array, total: int, page: int, pages: int}
     */
    public static function get_for_organization($org_id, $args = array()) {
        global $wpdb;

        $defaults = array(
            'page'     => 1,
            'per_page' => 20,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
            'search'   => '',
            'status'   => '',
        );
        $args = wp_parse_args($args, $defaults);

        // Whitelist orderby to prevent SQL injection
        $allowed_orderby = array(
            'id', 'created_at', 'updated_at', 'name', 'status',
            'title', 'email', 'amount', 'priority', 'due_date',
            'first_name', 'last_name',
        );
        if (!in_array($args['orderby'], $allowed_orderby, true)) {
            $args['orderby'] = 'created_at';
        }
        $order = $args['order'] === 'ASC' ? 'ASC' : 'DESC';

        $table = static::table();
        $where = $wpdb->prepare("WHERE organization_id = %d", $org_id);

        if (!empty($args['status'])) {
            $where .= $wpdb->prepare(" AND status = %s", $args['status']);
        }

        // Search (child classes override get_search_columns())
        if (!empty($args['search'])) {
            $search_cols = static::get_search_columns();
            if (!empty($search_cols)) {
                $like = '%' . $wpdb->esc_like($args['search']) . '%';
                $search_clauses = array();
                foreach ($search_cols as $col) {
                    $search_clauses[] = $wpdb->prepare("{$col} LIKE %s", $like);
                }
                $where .= ' AND (' . implode(' OR ', $search_clauses) . ')';
            }
        }

        $offset = ($args['page'] - 1) * $args['per_page'];
        $per_page = intval($args['per_page']);

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where}");
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where} ORDER BY {$args['orderby']} {$order} LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        return array(
            'items' => array_map(array(static::class, 'format'), $rows ?: array()),
            'total' => $total,
            'page'  => (int) $args['page'],
            'pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 0,
        );
    }

    /**
     * Columns to search in get_for_organization().
     * Override in child classes.
     *
     * @return array Column names
     */
    protected static function get_search_columns() {
        return array('name');
    }

    /**
     * Format a database row for API output.
     * Handles JSON decoding, type casting, and null defaults.
     *
     * @param array|null $row Database row
     * @return array|null Formatted row
     */
    protected static function format($row) {
        if (!$row) {
            return null;
        }

        // Integer casting
        foreach (static::$int_fields as $field) {
            if (isset($row[$field])) {
                $row[$field] = (int) $row[$field];
            }
        }

        // Float casting
        foreach (static::$float_fields as $field) {
            if (isset($row[$field])) {
                $row[$field] = (float) $row[$field];
            }
        }

        // JSON decoding
        foreach (static::$json_fields as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $decoded = json_decode($row[$field], true);
                $row[$field] = is_array($decoded) ? $decoded : array();
            }
        }

        return $row;
    }

    /**
     * Generate a unique slug from a name.
     *
     * @param string   $name       Source string for the slug
     * @param int|null $exclude_id Exclude this ID from uniqueness check (for updates)
     * @return string Unique slug
     */
    protected static function generate_unique_slug($name, $exclude_id = null) {
        global $wpdb;
        $table = static::table();

        $base = sanitize_title($name);
        if (empty($base)) {
            $base = 'item-' . wp_rand(1000, 9999);
        }

        $slug = $base;
        $counter = 1;
        $max_attempts = 100;

        while ($counter <= $max_attempts) {
            $exclude_sql = $exclude_id
                ? $wpdb->prepare(" AND id != %d", $exclude_id)
                : '';

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE slug = %s {$exclude_sql} LIMIT 1",
                $slug
            ));

            if (!$exists) {
                break;
            }

            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Run a callback inside a database transaction.
     *
     * @param callable $callback Function to execute inside transaction
     * @return mixed Result of callback, or WP_Error on failure
     */
    protected static function transaction(callable $callback) {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            $result = $callback();
            if (is_wp_error($result)) {
                $wpdb->query('ROLLBACK');
                return $result;
            }
            $wpdb->query('COMMIT');
            return $result;
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('transaction_failed', $e->getMessage());
        }
    }
}
