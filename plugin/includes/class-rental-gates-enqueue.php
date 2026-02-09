<?php
if (!defined('ABSPATH')) exit;

/**
 * Asset Enqueue Handler
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: enqueue_public_assets, output_rental_gates_data_early,
 *          enqueue_admin_assets, get_current_user_data, get_i18n_strings
 */
class Rental_Gates_Enqueue {

    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('wp_head', array($this, 'output_rental_gates_data_early'), 1);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Enqueue public assets
     */
    public function enqueue_public_assets()
    {
        // Only load on Rental Gates pages
        if (!get_query_var('rental_gates_page')) {
            return;
        }

        // Enable WordPress Media Library for dashboard pages
        if (is_user_logged_in()) {
            wp_enqueue_media();
        }

        // CSS
        wp_enqueue_style(
            'rental-gates-main',
            RENTAL_GATES_PLUGIN_URL . 'assets/css/rental-gates.css',
            array(),
            RENTAL_GATES_VERSION
        );

        wp_enqueue_style(
            'rental-gates-components',
            RENTAL_GATES_PLUGIN_URL . 'assets/css/components.css',
            array('rental-gates-main'),
            RENTAL_GATES_VERSION
        );

        // Google Fonts
        wp_enqueue_style(
            'rental-gates-fonts',
            'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap',
            array(),
            RENTAL_GATES_VERSION
        );

        // Chart.js for analytics (load on dashboard pages)
        $current_page = get_query_var('rental_gates_section');
        if (in_array($current_page, array('overview', 'reports', 'billing')) || empty($current_page)) {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                array(),
                '4.4.1',
                true
            );
        }

        // JS - with jQuery dependency
        wp_enqueue_script(
            'rental-gates-app',
            RENTAL_GATES_PLUGIN_URL . 'assets/js/rental-gates.js',
            array('jquery'),
            RENTAL_GATES_VERSION,
            true
        );

        // Map scripts based on provider
        $map_provider = get_option('rental_gates_map_provider', 'google');
        if ($map_provider === 'google') {
            $api_key = get_option('rental_gates_google_maps_api_key', '');
            if ($api_key) {
                wp_enqueue_script(
                    'google-maps',
                    'https://maps.googleapis.com/maps/api/js?key=' . $api_key . '&libraries=places',
                    array(),
                    null,
                    true
                );
            }
        } else {
            wp_enqueue_style(
                'leaflet',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
                array(),
                '1.9.4'
            );
            wp_enqueue_script(
                'leaflet',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
                array(),
                '1.9.4',
                true
            );
        }

        // Localize script
        wp_localize_script('rental-gates-app', 'rentalGatesData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'apiBase' => rest_url('rental-gates/v1'),
            'restUrl' => rest_url('rental-gates/v1/'),
            'nonce' => wp_create_nonce('rental_gates_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'homeUrl' => home_url(),
            'pluginUrl' => RENTAL_GATES_PLUGIN_URL,
            'isLoggedIn' => is_user_logged_in(),
            'currentUser' => $this->get_current_user_data(),
            'mapProvider' => get_option('rental_gates_map_provider', 'google'),
            'googleMapsApiKey' => get_option('rental_gates_google_maps_api_key', ''),
            'defaultLang' => get_option('rental_gates_default_language', 'en'),
            'isRTL' => is_rtl(),
            'i18n' => $this->get_i18n_strings(),
        ));
    }

    /**
     * Output rental gates data early in wp_head
     */
    public function output_rental_gates_data_early()
    {
        // Check both query var and direct URL
        $is_rental_gates_page = get_query_var('rental_gates_page');

        // Also check URL directly for cases where rewrite rules might not match
        if (!$is_rental_gates_page) {
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            if (strpos($request_uri, '/rental-gates/') !== false) {
                $is_rental_gates_page = true;
            }
        }

        if (!$is_rental_gates_page) {
            return;
        }

        $data = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'apiBase' => rest_url('rental-gates/v1'),
            'restUrl' => rest_url('rental-gates/v1/'),
            'nonce' => wp_create_nonce('rental_gates_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'homeUrl' => home_url(),
            'pluginUrl' => RENTAL_GATES_PLUGIN_URL,
            'isLoggedIn' => is_user_logged_in(),
            'mapProvider' => get_option('rental_gates_map_provider', 'google'),
            'pwaEnabled' => rental_gates_pwa()->is_enabled(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
        );

        echo '<script id="rental-gates-data">window.rentalGatesData = ' . wp_json_encode($data) . '; var ajaxurl = "' . esc_js(admin_url('admin-ajax.php')) . '";</script>' . "\n";
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'rental-gates') === false) {
            return;
        }

        wp_enqueue_style(
            'rental-gates-admin',
            RENTAL_GATES_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            RENTAL_GATES_VERSION
        );

        wp_enqueue_script(
            'rental-gates-admin',
            RENTAL_GATES_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            RENTAL_GATES_VERSION,
            true
        );
    }

    /**
     * Get current user data for JS
     */
    private function get_current_user_data()
    {
        if (!is_user_logged_in()) {
            return null;
        }

        $user = wp_get_current_user();
        $org_id = Rental_Gates_Roles::get_organization_id();

        return array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'roles' => $user->roles,
            'avatar' => get_avatar_url($user->ID),
            'organizationId' => $org_id,
        );
    }

    /**
     * Get i18n strings for JS
     */
    private function get_i18n_strings()
    {
        return array(
            // General
            'loading' => __('Loading...', 'rental-gates'),
            'error' => __('An error occurred', 'rental-gates'),
            'success' => __('Success', 'rental-gates'),
            'confirm' => __('Are you sure?', 'rental-gates'),
            'cancel' => __('Cancel', 'rental-gates'),
            'save' => __('Save', 'rental-gates'),
            'delete' => __('Delete', 'rental-gates'),
            'edit' => __('Edit', 'rental-gates'),
            'view' => __('View', 'rental-gates'),
            'add' => __('Add', 'rental-gates'),
            'search' => __('Search...', 'rental-gates'),
            'noResults' => __('No results found', 'rental-gates'),

            // Availability states
            'available' => __('Available', 'rental-gates'),
            'comingSoon' => __('Coming Soon', 'rental-gates'),
            'occupied' => __('Occupied', 'rental-gates'),
            'renewalPending' => __('Renewal Pending', 'rental-gates'),
            'unlisted' => __('Unlisted', 'rental-gates'),

            // Map
            'pinLocation' => __('Click on the map to pin location', 'rental-gates'),
            'addressDerived' => __('Address derived from map pin', 'rental-gates'),

            // Delete confirmation modal
            'deleteConfirmTitle' => __('Confirm Delete', 'rental-gates'),
            'deleteConfirmMessage' => __('Are you sure you want to delete this item? This action cannot be undone.', 'rental-gates'),
            'deleting' => __('Deleting...', 'rental-gates'),
            'deleted' => __('Successfully deleted', 'rental-gates'),

            // Media library
            'selectImage' => __('Select Image', 'rental-gates'),
            'selectImages' => __('Select Images', 'rental-gates'),
            'useImage' => __('Use this image', 'rental-gates'),
            'useImages' => __('Use these images', 'rental-gates'),
            'removeImage' => __('Remove image', 'rental-gates'),
            'uploadImage' => __('Upload Image', 'rental-gates'),
            'addPhotos' => __('Add Photos', 'rental-gates'),

            // QR Code
            'generateQR' => __('Generate QR Code', 'rental-gates'),
            'qrGenerated' => __('QR code generated successfully', 'rental-gates'),
            'downloadPNG' => __('Download PNG', 'rental-gates'),
            'scans' => __('Scans', 'rental-gates'),
            'lastScan' => __('Last Scan', 'rental-gates'),

            // Toast messages
            'networkError' => __('Network error occurred', 'rental-gates'),
            'saveSuccess' => __('Changes saved successfully', 'rental-gates'),
            'saveFailed' => __('Failed to save changes', 'rental-gates'),
        );
    }
}
