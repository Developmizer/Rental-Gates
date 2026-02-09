<?php
/**
 * Rental Gates Cache Class
 * Handles caching for performance optimization
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rental_Gates_Cache {
    
    /**
     * Cache prefix
     */
    const PREFIX = 'rg_';
    
    /**
     * Default expiration (1 hour)
     */
    const DEFAULT_EXPIRATION = 3600;
    
    /**
     * Cache groups
     */
    const GROUPS = array(
        'organizations' => 'rg_org',
        'buildings' => 'rg_bld',
        'units' => 'rg_unit',
        'tenants' => 'rg_tnt',
        'leases' => 'rg_lse',
        'payments' => 'rg_pay',
        'subscriptions' => 'rg_sub',
        'usage' => 'rg_use',
        'stats' => 'rg_stats',
        'maps' => 'rg_maps',
    );
    
    /**
     * Get cached value (version-aware).
     */
    public static function get($key, $group = 'default') {
        $resolved_group = self::get_group($group);
        $versioned_key = self::versioned_key($key, $group);
        return wp_cache_get($versioned_key, $resolved_group);
    }

    /**
     * Set cached value (version-aware).
     */
    public static function set($key, $value, $group = 'default', $expiration = null) {
        $resolved_group = self::get_group($group);
        $versioned_key = self::versioned_key($key, $group);
        $expiration = $expiration ?? self::DEFAULT_EXPIRATION;

        return wp_cache_set($versioned_key, $value, $resolved_group, $expiration);
    }

    /**
     * Delete cached value (version-aware).
     */
    public static function delete($key, $group = 'default') {
        $resolved_group = self::get_group($group);
        $versioned_key = self::versioned_key($key, $group);
        return wp_cache_delete($versioned_key, $resolved_group);
    }
    
    /**
     * Delete all values in a group by incrementing its version counter.
     * All existing cache keys become stale because they include the old version.
     *
     * @param string $group Cache group name
     * @return bool
     */
    public static function flush_group($group) {
        $version_key = 'rg_cache_ver_' . $group;
        $version = intval(get_option($version_key, 0)) + 1;
        update_option($version_key, $version, false); // autoload=false
        return true;
    }
    
    /**
     * Get or set cached value (remember pattern).
     * Uses wp_cache_get's $found parameter to correctly handle cached false/null.
     */
    public static function remember($key, $group, $callback, $expiration = null) {
        $resolved_group = self::get_group($group);
        $versioned_key = self::versioned_key($key, $group);
        $found = false;

        $value = wp_cache_get($versioned_key, $resolved_group, false, $found);

        if ($found) {
            return $value;
        }

        $value = call_user_func($callback);
        self::set($key, $value, $group, $expiration);

        return $value;
    }
    
    /**
     * Get cache group name
     */
    private static function get_group($group) {
        if (isset(self::GROUPS[$group])) {
            return self::GROUPS[$group];
        }

        return self::PREFIX . $group;
    }

    /**
     * Generate a versioned cache key.
     * When flush_group() increments the version, all old keys become stale.
     *
     * @param string $key   Base key
     * @param string $group Group name (for version lookup)
     * @return string Versioned key
     */
    private static function versioned_key($key, $group) {
        $version = intval(get_option('rg_cache_ver_' . $group, 0));
        return $key . '_v' . $version;
    }
    
    /**
     * Generate cache key for organization-specific data
     */
    public static function make_key($type, $id, $suffix = '') {
        $key = $type . '_' . $id;
        
        if ($suffix) {
            $key .= '_' . $suffix;
        }
        
        return $key;
    }
    
    /**
     * Get organization cache key
     */
    public static function org_key($org_id, $suffix = '') {
        return self::make_key('org', $org_id, $suffix);
    }
    
    /**
     * Clear organization-related cache
     */
    public static function clear_organization_cache($org_id) {
        self::delete(self::org_key($org_id), 'organizations');
        self::delete(self::org_key($org_id, 'buildings'), 'buildings');
        self::delete(self::org_key($org_id, 'units'), 'units');
        self::delete(self::org_key($org_id, 'stats'), 'stats');
        self::delete(self::org_key($org_id, 'usage'), 'usage');
    }
    
    /**
     * Clear building-related cache
     */
    public static function clear_building_cache($building_id, $org_id = null) {
        self::delete(self::make_key('building', $building_id), 'buildings');
        self::delete(self::make_key('building', $building_id, 'units'), 'units');
        
        if ($org_id) {
            self::delete(self::org_key($org_id, 'buildings'), 'buildings');
            self::delete(self::org_key($org_id, 'stats'), 'stats');
        }
    }
    
    /**
     * Clear unit-related cache
     */
    public static function clear_unit_cache($unit_id, $building_id = null, $org_id = null) {
        self::delete(self::make_key('unit', $unit_id), 'units');
        
        if ($building_id) {
            self::delete(self::make_key('building', $building_id, 'units'), 'units');
        }
        
        if ($org_id) {
            self::delete(self::org_key($org_id, 'units'), 'units');
            self::delete(self::org_key($org_id, 'stats'), 'stats');
        }
    }
    
    /**
     * Clear tenant-related cache
     */
    public static function clear_tenant_cache($tenant_id, $org_id = null) {
        self::delete(self::make_key('tenant', $tenant_id), 'tenants');
        
        if ($org_id) {
            self::delete(self::org_key($org_id, 'tenants'), 'tenants');
        }
    }
    
    /**
     * Clear lease-related cache
     */
    public static function clear_lease_cache($lease_id, $unit_id = null, $org_id = null) {
        self::delete(self::make_key('lease', $lease_id), 'leases');
        
        if ($unit_id) {
            self::clear_unit_cache($unit_id, null, $org_id);
        }
        
        if ($org_id) {
            self::delete(self::org_key($org_id, 'leases'), 'leases');
        }
    }
    
    /**
     * Clear subscription cache
     */
    public static function clear_subscription_cache($org_id) {
        self::delete(self::org_key($org_id, 'subscription'), 'subscriptions');
        self::delete(self::org_key($org_id, 'usage'), 'usage');
    }
    
    /**
     * Use transient for longer-lived cache
     */
    public static function get_transient($key) {
        return get_transient(self::PREFIX . $key);
    }
    
    /**
     * Set transient
     */
    public static function set_transient($key, $value, $expiration = null) {
        $expiration = $expiration ?? DAY_IN_SECONDS;
        return set_transient(self::PREFIX . $key, $value, $expiration);
    }
    
    /**
     * Delete transient
     */
    public static function delete_transient($key) {
        return delete_transient(self::PREFIX . $key);
    }
    
    /**
     * Cache geocoding results
     */
    public static function get_geocode_cache($lat, $lng) {
        $key = 'geocode_' . md5($lat . '_' . $lng);
        return self::get_transient($key);
    }
    
    /**
     * Set geocoding cache
     */
    public static function set_geocode_cache($lat, $lng, $data) {
        $key = 'geocode_' . md5($lat . '_' . $lng);
        return self::set_transient($key, $data, WEEK_IN_SECONDS);
    }
    
    /**
     * Get stats cache with organization context
     */
    public static function get_stats($org_id, $stat_type) {
        $key = self::org_key($org_id, 'stats_' . $stat_type);
        return self::get($key, 'stats');
    }
    
    /**
     * Set stats cache
     */
    public static function set_stats($org_id, $stat_type, $value, $expiration = 300) {
        $key = self::org_key($org_id, 'stats_' . $stat_type);
        return self::set($key, $value, 'stats', $expiration);
    }
    
    /**
     * Delete stats cache
     */
    public static function delete_stats($org_id, $stat_type) {
        $key = self::org_key($org_id, 'stats_' . $stat_type);
        return self::delete($key, 'stats');
    }
}
