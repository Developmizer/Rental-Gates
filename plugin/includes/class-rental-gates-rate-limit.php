<?php
/**
 * Rental Gates Rate Limit Class
 * Handles rate limiting for API endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rental_Gates_Rate_Limit {
    
    /**
     * Rate limit configurations
     */
    const LIMITS = array(
        // Authentication endpoints
        'auth_login' => array(
            'limit' => 5,
            'window' => 300, // 5 minutes
        ),
        'auth_register' => array(
            'limit' => 3,
            'window' => 3600, // 1 hour
        ),
        'auth_password_reset' => array(
            'limit' => 3,
            'window' => 120, // 2 minutes
        ),
        
        // API endpoints
        'api_general' => array(
            'limit' => 100,
            'window' => 60, // 1 minute
        ),
        'api_search' => array(
            'limit' => 30,
            'window' => 60,
        ),
        'api_upload' => array(
            'limit' => 20,
            'window' => 60,
        ),
        
        // AI endpoints
        'ai_screening' => array(
            'limit' => 10,
            'window' => 3600,
        ),
        'ai_general' => array(
            'limit' => 30,
            'window' => 3600,
        ),
        
        // Public endpoints
        'public_inquiry' => array(
            'limit' => 10,
            'window' => 3600,
        ),
        'public_map' => array(
            'limit' => 60,
            'window' => 60,
        ),
        
        // Geocoding
        'geocode' => array(
            'limit' => 20,
            'window' => 60,
        ),

        // Organization creation
        'org_creation' => array(
            'limit' => 3,
            'window' => 3600,
        ),

        // Public application submissions
        'public_application' => array(
            'limit' => 5,
            'window' => 3600,
        ),
    );
    
    /**
     * Check if request is within rate limit
     */
    public static function check($endpoint, $identifier = null) {
        $config = self::get_config($endpoint);
        
        if (!$config) {
            return true; // No limit configured
        }
        
        $key = self::get_key($endpoint, $identifier);
        $count = self::get_count($key);
        
        if ($count >= $config['limit']) {
            $retry_after = self::get_retry_after($key, $config['window']);
            
            // Format retry time in a human-friendly way
            if ($retry_after >= 3600) {
                $time_str = sprintf(_n('%d hour', '%d hours', floor($retry_after / 3600), 'rental-gates'), floor($retry_after / 3600));
            } elseif ($retry_after >= 60) {
                $minutes = ceil($retry_after / 60);
                $time_str = sprintf(_n('%d minute', '%d minutes', $minutes, 'rental-gates'), $minutes);
            } else {
                $time_str = sprintf(_n('%d second', '%d seconds', $retry_after, 'rental-gates'), $retry_after);
            }
            
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __('Too many attempts. Please try again in %s.', 'rental-gates'),
                    $time_str
                ),
                array(
                    'status' => 429,
                    'retry_after' => $retry_after,
                )
            );
        }
        
        // Increment counter
        self::increment($key, $config['window']);
        
        return true;
    }
    
    /**
     * Get rate limit configuration for endpoint
     */
    private static function get_config($endpoint) {
        return self::LIMITS[$endpoint] ?? self::LIMITS['api_general'];
    }
    
    /**
     * Get rate limit key
     */
    private static function get_key($endpoint, $identifier = null) {
        if ($identifier === null) {
            $ip = Rental_Gates_Security::get_client_ip();
            // Add user-agent hash as secondary signal (defense-in-depth)
            $ua_hash = substr(md5($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 8);
            $identifier = $ip . '_' . $ua_hash;
        }

        return 'rg_rate_' . $endpoint . '_' . md5($identifier);
    }
    
    /**
     * Get current count for key
     */
    private static function get_count($key) {
        return intval(get_transient($key));
    }
    
    /**
     * Increment counter
     */
    private static function increment($key, $window) {
        $count = self::get_count($key);
        set_transient($key, $count + 1, $window);
    }
    
    /**
     * Get seconds until rate limit resets
     */
    private static function get_retry_after($key, $window) {
        $timeout_key = '_transient_timeout_' . $key;
        $timeout = get_option($timeout_key);
        
        if ($timeout) {
            return max(0, $timeout - time());
        }
        
        return $window;
    }
    
    /**
     * Reset rate limit for key
     */
    public static function reset($endpoint, $identifier = null) {
        $key = self::get_key($endpoint, $identifier);
        delete_transient($key);
    }
    
    /**
     * Get remaining requests
     */
    public static function get_remaining($endpoint, $identifier = null) {
        $config = self::get_config($endpoint);
        
        if (!$config) {
            return -1; // Unlimited
        }
        
        $key = self::get_key($endpoint, $identifier);
        $count = self::get_count($key);
        
        return max(0, $config['limit'] - $count);
    }
    
    /**
     * Add rate limit headers to response
     */
    public static function add_headers($response, $endpoint, $identifier = null) {
        $config = self::get_config($endpoint);
        
        if (!$config) {
            return $response;
        }
        
        $key = self::get_key($endpoint, $identifier);
        $count = self::get_count($key);
        $remaining = max(0, $config['limit'] - $count);
        $reset = self::get_retry_after($key, $config['window']);
        
        $response->header('X-RateLimit-Limit', $config['limit']);
        $response->header('X-RateLimit-Remaining', $remaining);
        $response->header('X-RateLimit-Reset', time() + $reset);
        
        return $response;
    }
    
    /**
     * Check rate limit for REST API request
     */
    public static function check_rest_request($request, $endpoint = 'api_general') {
        // Get user ID for authenticated requests
        $user_id = get_current_user_id();
        $identifier = $user_id ? 'user_' . $user_id : null;
        
        return self::check($endpoint, $identifier);
    }
    
    /**
     * Middleware for REST API
     */
    public static function middleware($result, $server, $request) {
        // Determine endpoint type
        $route = $request->get_route();
        $endpoint = 'api_general';
        
        if (strpos($route, '/auth/') !== false) {
            if (strpos($route, 'login') !== false) {
                $endpoint = 'auth_login';
            } elseif (strpos($route, 'register') !== false) {
                $endpoint = 'auth_register';
            }
        } elseif (strpos($route, '/search') !== false) {
            $endpoint = 'api_search';
        } elseif (strpos($route, '/upload') !== false) {
            $endpoint = 'api_upload';
        } elseif (strpos($route, '/ai/') !== false) {
            $endpoint = strpos($route, 'screening') !== false ? 'ai_screening' : 'ai_general';
        } elseif (strpos($route, '/public/') !== false) {
            $endpoint = strpos($route, 'inquiry') !== false ? 'public_inquiry' : 'public_map';
        }
        
        $check = self::check_rest_request($request, $endpoint);
        
        if (is_wp_error($check)) {
            return $check;
        }
        
        return $result;
    }
    
    /**
     * Block IP address temporarily
     */
    public static function block_ip($ip, $duration = 3600, $reason = '') {
        $key = 'rg_blocked_ip_' . md5($ip);
        
        set_transient($key, array(
            'ip' => $ip,
            'blocked_at' => current_time('mysql'),
            'duration' => $duration,
            'reason' => $reason,
        ), $duration);
        
        // Log the block
        Rental_Gates_Security::log_security_event('ip_blocked', array(
            'ip' => $ip,
            'duration' => $duration,
            'reason' => $reason,
        ));
    }
    
    /**
     * Check if IP is blocked
     */
    public static function is_ip_blocked($ip = null) {
        if ($ip === null) {
            $ip = Rental_Gates_Security::get_client_ip();
        }
        
        $key = 'rg_blocked_ip_' . md5($ip);
        return get_transient($key) !== false;
    }
    
    /**
     * Unblock IP address
     */
    public static function unblock_ip($ip) {
        $key = 'rg_blocked_ip_' . md5($ip);
        delete_transient($key);
    }
}
