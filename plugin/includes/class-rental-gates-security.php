<?php
/**
 * Rental Gates Security Class
 * Handles CSRF protection, input validation, and security utilities
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rental_Gates_Security {
    
    /**
     * Verify REST API nonce
     */
    public static function verify_rest_nonce($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        
        if (!$nonce) {
            return new WP_Error(
                'missing_nonce',
                __('Security token is missing.', 'rental-gates'),
                array('status' => 401)
            );
        }
        
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'invalid_nonce',
                __('Invalid security token.', 'rental-gates'),
                array('status' => 401)
            );
        }
        
        return true;
    }
    
    /**
     * Verify AJAX nonce
     */
    public static function verify_ajax_nonce($nonce_value, $action = 'rental_gates_nonce') {
        if (!wp_verify_nonce($nonce_value, $action)) {
            wp_send_json_error(array(
                'message' => __('Security verification failed.', 'rental-gates'),
            ), 403);
        }
        
        return true;
    }
    
    /**
     * Generate a secure random token
     */
    public static function generate_token($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Generate application token
     */
    public static function generate_application_token() {
        return 'app_' . self::generate_token(24);
    }
    
    /**
     * Generate QR code identifier
     */
    public static function generate_qr_code() {
        return 'qr_' . self::generate_token(16);
    }
    
    /**
     * Hash a token using HMAC-SHA256 (prevents length extension attacks).
     *
     * @param string $token Raw token to hash
     * @return string Hex-encoded HMAC
     */
    public static function hash_token($token) {
        return hash_hmac('sha256', $token, wp_salt());
    }

    /**
     * Verify a token against its HMAC hash.
     * Supports both new HMAC format and legacy hash format for migration.
     *
     * @param string $token Raw token
     * @param string $hash  Stored hash to verify against
     * @return bool
     */
    public static function verify_token($token, $hash) {
        // Try new HMAC format first
        if (hash_equals($hash, hash_hmac('sha256', $token, wp_salt()))) {
            return true;
        }

        // Fallback: legacy format (hash + concatenation)
        if (hash_equals($hash, hash('sha256', $token . wp_salt()))) {
            return true;
        }

        return false;
    }
    
    /**
     * Sanitize and validate email
     */
    public static function sanitize_email($email) {
        $email = sanitize_email($email);
        
        if (!is_email($email)) {
            return false;
        }
        
        return $email;
    }
    
    /**
     * Sanitize phone number
     */
    public static function sanitize_phone($phone) {
        // Remove all non-numeric characters except + - ( ) and spaces
        $phone = preg_replace('/[^\d+\-\(\)\s]/', '', $phone);
        
        // Remove extra spaces
        $phone = preg_replace('/\s+/', ' ', trim($phone));
        
        return $phone;
    }
    
    /**
     * Sanitize currency amount
     */
    public static function sanitize_amount($amount) {
        // Remove currency symbols and commas
        $amount = preg_replace('/[^\d.-]/', '', $amount);
        
        // Convert to float
        $amount = floatval($amount);
        
        // Round to 2 decimal places
        return round($amount, 2);
    }
    
    /**
     * Sanitize and validate date
     */
    public static function sanitize_date($date, $format = 'Y-m-d') {
        $date_obj = DateTime::createFromFormat($format, $date);
        
        if (!$date_obj || $date_obj->format($format) !== $date) {
            return false;
        }
        
        return $date;
    }
    
    /**
     * Sanitize coordinates
     */
    public static function sanitize_coordinates($lat, $lng) {
        $lat = floatval($lat);
        $lng = floatval($lng);
        
        // Validate latitude (-90 to 90)
        if ($lat < -90 || $lat > 90) {
            return false;
        }
        
        // Validate longitude (-180 to 180)
        if ($lng < -180 || $lng > 180) {
            return false;
        }
        
        return array(
            'lat' => round($lat, 8),
            'lng' => round($lng, 8),
        );
    }
    
    /**
     * Sanitize HTML content (allow safe tags)
     */
    public static function sanitize_html($content) {
        $allowed_tags = array(
            'p' => array(),
            'br' => array(),
            'strong' => array(),
            'em' => array(),
            'b' => array(),
            'i' => array(),
            'u' => array(),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'a' => array(
                'href' => array(),
                'title' => array(),
                'target' => array(),
            ),
            'span' => array(
                'class' => array(),
            ),
            'div' => array(
                'class' => array(),
            ),
        );
        
        return wp_kses($content, $allowed_tags);
    }
    
    /**
     * Validate file upload
     */
    public static function validate_file_upload($file, $allowed_types = null, $max_size = null) {
        // Default allowed types
        if ($allowed_types === null) {
            $allowed_types = array(
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            );
        }
        
        // Default max size: 10MB
        if ($max_size === null) {
            $max_size = 10 * 1024 * 1024;
        }
        
        $errors = array();
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = array(
                UPLOAD_ERR_INI_SIZE => __('File exceeds server limit.', 'rental-gates'),
                UPLOAD_ERR_FORM_SIZE => __('File exceeds form limit.', 'rental-gates'),
                UPLOAD_ERR_PARTIAL => __('File was only partially uploaded.', 'rental-gates'),
                UPLOAD_ERR_NO_FILE => __('No file was uploaded.', 'rental-gates'),
                UPLOAD_ERR_NO_TMP_DIR => __('Missing temporary folder.', 'rental-gates'),
                UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk.', 'rental-gates'),
                UPLOAD_ERR_EXTENSION => __('File upload stopped by extension.', 'rental-gates'),
            );
            
            $errors[] = isset($upload_errors[$file['error']]) 
                ? $upload_errors[$file['error']] 
                : __('Unknown upload error.', 'rental-gates');
            
            return $errors;
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            $errors[] = sprintf(
                __('File size exceeds maximum allowed (%s).', 'rental-gates'),
                size_format($max_size)
            );
        }
        
        // Check MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);
        
        if (!in_array($mime_type, $allowed_types)) {
            $errors[] = __('File type is not allowed.', 'rental-gates');
        }
        
        // Additional security check for images
        if (strpos($mime_type, 'image/') === 0) {
            $image_info = getimagesize($file['tmp_name']);
            if ($image_info === false) {
                $errors[] = __('Invalid image file.', 'rental-gates');
            }
        }

        // Extension whitelist matching allowed MIME types
        $mime_to_extensions = array(
            'image/jpeg' => array('jpg', 'jpeg'),
            'image/png'  => array('png'),
            'image/gif'  => array('gif'),
            'image/webp' => array('webp'),
            'application/pdf' => array('pdf'),
            'application/msword' => array('doc'),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => array('docx'),
        );

        // Get file extension
        $filename = $file['name'] ?? '';
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Build allowed extensions from allowed MIME types
        $allowed_extensions = array();
        foreach ($allowed_types as $mime) {
            if (isset($mime_to_extensions[$mime])) {
                $allowed_extensions = array_merge($allowed_extensions, $mime_to_extensions[$mime]);
            }
        }

        if (!empty($allowed_extensions) && !in_array($extension, $allowed_extensions, true)) {
            $errors[] = __('File extension is not allowed.', 'rental-gates');
        }

        // Double-extension check (e.g., "image.php.jpg")
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $dangerous_extensions = array('php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'phar', 'cgi', 'pl', 'py', 'sh', 'bash');
        foreach ($dangerous_extensions as $ext) {
            if (stripos($basename, '.' . $ext) !== false) {
                $errors[] = __('File name contains a disallowed extension.', 'rental-gates');
                break;
            }
        }

        return empty($errors) ? true : $errors;
    }
    
    /**
     * Check if current request is from same site
     */
    public static function is_same_origin() {
        $referer = wp_get_referer();
        
        if (!$referer) {
            return false;
        }
        
        $home = parse_url(home_url());
        $ref = parse_url($referer);
        
        return $home['host'] === $ref['host'];
    }
    
    /**
     * Get client IP address - secure implementation.
     *
     * By default only trusts REMOTE_ADDR. When a trusted proxy is configured
     * (Cloudflare, load balancer), it will also check proxy-set headers but
     * ONLY after verifying the request actually came from the proxy.
     *
     * @return string Client IP address
     */
    public static function get_client_ip() {
        $proxy_mode = get_option('rental_gates_proxy_mode', 'none');

        switch ($proxy_mode) {
            case 'cloudflare':
                // Only trust CF-Connecting-IP if request came from a Cloudflare IP
                if (self::is_cloudflare_ip($_SERVER['REMOTE_ADDR'] ?? '')) {
                    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
                break;

            case 'trusted_proxy':
                // Trust X-Forwarded-For only if REMOTE_ADDR is in the trusted list
                $trusted_proxies = array_filter(array_map(
                    'trim',
                    explode(',', get_option('rental_gates_trusted_proxies', ''))
                ));

                if (in_array($_SERVER['REMOTE_ADDR'] ?? '', $trusted_proxies, true)) {
                    // Take the first (leftmost = original client) IP from XFF
                    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
                    if ($xff) {
                        $ips = array_map('trim', explode(',', $xff));
                        $client_ip = $ips[0];
                        if (filter_var($client_ip, FILTER_VALIDATE_IP)) {
                            return $client_ip;
                        }
                    }

                    // Fallback to X-Real-IP
                    $real_ip = $_SERVER['HTTP_X_REAL_IP'] ?? '';
                    if (filter_var($real_ip, FILTER_VALIDATE_IP)) {
                        return $real_ip;
                    }
                }
                break;

            case 'none':
            default:
                // Direct connection - only trust REMOTE_ADDR
                break;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Check if an IP is in Cloudflare's published IP ranges.
     * Cached for 24 hours.
     *
     * @param string $ip IP address to check
     * @return bool
     */
    private static function is_cloudflare_ip($ip) {
        // Cloudflare IPv4 ranges (hardcoded fallback, updated periodically)
        $cf_ranges = get_transient('rental_gates_cf_ip_ranges');

        if ($cf_ranges === false) {
            $cf_ranges = array(
                '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22',
                '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
                '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22',
                '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
                '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            );

            // Try to fetch fresh list
            $response = wp_remote_get('https://www.cloudflare.com/ips-v4', array('timeout' => 5));
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $fresh_ranges = array_filter(array_map('trim', explode("\n", $body)));
                if (count($fresh_ranges) > 5) {
                    $cf_ranges = $fresh_ranges;
                }
            }

            set_transient('rental_gates_cf_ip_ranges', $cf_ranges, DAY_IN_SECONDS);
        }

        $ip_long = ip2long($ip);
        if ($ip_long === false) {
            return false;
        }

        foreach ($cf_ranges as $range) {
            if (strpos($range, '/') === false) {
                continue;
            }
            list($subnet, $bits) = explode('/', $range);
            $subnet_long = ip2long($subnet);
            $mask = -1 << (32 - (int) $bits);
            if (($ip_long & $mask) === ($subnet_long & $mask)) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Log security event
     */
    public static function log_security_event($event_type, $details = array()) {
        global $wpdb;
        
        // Check if Database class exists and table is available
        if (!class_exists('Rental_Gates_Database')) {
            return;
        }
        
        $tables = Rental_Gates_Database::get_table_names();
        
        // Check if table exists before inserting
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $tables['activity_log']
        ));
        
        if (!$table_exists) {
            return; // Table doesn't exist yet, skip logging
        }
        
        // Get organization_id safely
        $org_id = null;
        if (class_exists('Rental_Gates_Roles')) {
            try {
                $org_id = Rental_Gates_Roles::get_organization_id();
            } catch (Exception $e) {
                $org_id = null;
            }
        }
        
        $wpdb->insert(
            $tables['activity_log'],
            array(
                'user_id' => get_current_user_id(),
                'organization_id' => $org_id,
                'action' => 'security_' . $event_type,
                'new_values' => wp_json_encode($details),
                'ip_address' => self::get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) 
                    ? sanitize_text_field(substr($_SERVER['HTTP_USER_AGENT'], 0, 500)) 
                    : '',
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Support mode timeout in seconds (30 minutes)
     */
    const SUPPORT_MODE_TIMEOUT = 1800;

    /**
     * Check if current admin is in support mode.
     * Uses WordPress user meta instead of PHP sessions.
     */
    public static function is_support_mode() {
        $admin_id = get_current_user_id();
        if (!$admin_id) {
            return false;
        }

        $support_data = get_user_meta($admin_id, '_rg_support_mode', true);
        if (empty($support_data)) {
            return false;
        }

        // Check timeout
        $started = strtotime($support_data['started_at'] ?? '');
        if (!$started || (time() - $started) > self::SUPPORT_MODE_TIMEOUT) {
            // Expired - auto-exit
            self::exit_support_mode();
            return false;
        }

        return true;
    }

    /**
     * Get support mode details from user meta.
     */
    public static function get_support_mode_details() {
        if (!self::is_support_mode()) {
            return null;
        }

        $admin_id = get_current_user_id();
        return get_user_meta($admin_id, '_rg_support_mode', true);
    }

    /**
     * Enter support mode using user meta.
     */
    public static function enter_support_mode($admin_user_id, $target_user_id, $organization_id, $reason) {
        if (!Rental_Gates_Roles::is_site_admin($admin_user_id)) {
            return new WP_Error('unauthorized', __('Only site admins can use support mode.', 'rental-gates'));
        }

        if (empty($reason)) {
            return new WP_Error('missing_reason', __('Support mode requires a reason.', 'rental-gates'));
        }

        $support_data = array(
            'admin_user_id'      => $admin_user_id,
            'viewing_as_user_id' => $target_user_id,
            'organization_id'    => $organization_id,
            'reason'             => sanitize_text_field($reason),
            'started_at'         => current_time('mysql'),
        );

        update_user_meta($admin_user_id, '_rg_support_mode', $support_data);

        // Log support mode entry
        self::log_security_event('support_mode_enter', $support_data);

        return true;
    }

    /**
     * Exit support mode.
     */
    public static function exit_support_mode() {
        $admin_id = get_current_user_id();
        if (!$admin_id) {
            return;
        }

        $details = get_user_meta($admin_id, '_rg_support_mode', true);
        if (!empty($details)) {
            self::log_security_event('support_mode_exit', $details);
        }

        delete_user_meta($admin_id, '_rg_support_mode');
    }
    
    /**
     * Encrypt sensitive data using AES-256-CBC with HMAC authentication.
     *
     * Format: base64(IV + ciphertext + HMAC)
     * - IV: 16 bytes (AES block size)
     * - HMAC: 32 bytes (SHA-256)
     * - Ciphertext: variable length
     *
     * @param string $data Plaintext to encrypt
     * @return string|false Base64-encoded encrypted data, or false on failure
     */
    public static function encrypt($data) {
        if (empty($data)) {
            return false;
        }

        // Derive a proper 32-byte key from the WordPress salt
        $key = hash('sha256', wp_salt('auth'), true);

        // Generate random IV
        $iv = random_bytes(16);

        // Encrypt
        $ciphertext = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($ciphertext === false) {
            return false;
        }

        // Authenticate: HMAC-SHA256 over IV + ciphertext (encrypt-then-MAC)
        $hmac_key = hash('sha256', wp_salt('secure_auth'), true);
        $hmac = hash_hmac('sha256', $iv . $ciphertext, $hmac_key, true);

        // Format: IV (16) + ciphertext (variable) + HMAC (32)
        return base64_encode($iv . $ciphertext . $hmac);
    }

    /**
     * Decrypt data encrypted with encrypt().
     * Verifies HMAC before decryption to prevent padding oracle attacks.
     *
     * @param string $data Base64-encoded encrypted data
     * @return string|false Decrypted plaintext, or false on failure
     */
    public static function decrypt($data) {
        if (empty($data)) {
            return false;
        }

        $raw = base64_decode($data, true);
        if ($raw === false) {
            return false;
        }

        // Minimum length: 16 (IV) + 16 (min ciphertext) + 32 (HMAC) = 64
        if (strlen($raw) < 64) {
            // Try legacy format (IV + ciphertext, no HMAC)
            return self::decrypt_legacy($data);
        }

        // Extract components
        $iv = substr($raw, 0, 16);
        $hmac = substr($raw, -32);
        $ciphertext = substr($raw, 16, -32);

        // Verify HMAC FIRST (before any decryption attempt)
        $hmac_key = hash('sha256', wp_salt('secure_auth'), true);
        $expected_hmac = hash_hmac('sha256', $iv . $ciphertext, $hmac_key, true);

        if (!hash_equals($expected_hmac, $hmac)) {
            // HMAC mismatch - possible tampering or legacy format
            return self::decrypt_legacy($data);
        }

        // Derive key
        $key = hash('sha256', wp_salt('auth'), true);

        // Decrypt
        $plaintext = openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $plaintext !== false ? $plaintext : false;
    }

    /**
     * Decrypt data in the legacy format (pre-security-hardening).
     * Used for backward compatibility during migration.
     *
     * @param string $data Base64-encoded legacy encrypted data
     * @return string|false
     */
    private static function decrypt_legacy($data) {
        $raw = base64_decode($data, true);
        if ($raw === false || strlen($raw) < 17) {
            return false;
        }

        // Legacy format: IV (16) + ciphertext
        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);

        // Legacy used wp_salt('auth') directly (not hashed)
        $key_legacy = wp_salt('auth');
        $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key_legacy, OPENSSL_RAW_DATA, $iv);

        if ($plaintext !== false) {
            return $plaintext;
        }

        // Also try with hashed key (in case of partial migration)
        $key_new = hash('sha256', wp_salt('auth'), true);
        return openssl_decrypt($ciphertext, 'AES-256-CBC', $key_new, OPENSSL_RAW_DATA, $iv);
    }
    
    /**
     * Mask sensitive data for display
     */
    public static function mask_email($email) {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }
        
        $name = $parts[0];
        $domain = $parts[1];
        
        $masked_name = substr($name, 0, 2) . str_repeat('*', max(strlen($name) - 2, 3));
        
        return $masked_name . '@' . $domain;
    }
    
    /**
     * Mask phone number
     */
    public static function mask_phone($phone) {
        $digits = preg_replace('/\D/', '', $phone);
        $length = strlen($digits);
        
        if ($length < 4) {
            return str_repeat('*', $length);
        }
        
        return str_repeat('*', $length - 4) . substr($digits, -4);
    }
    
    /**
     * Mask card number
     */
    public static function mask_card($card_number) {
        return '**** **** **** ' . substr($card_number, -4);
    }
}
