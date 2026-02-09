<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handlers for Buildings & Units
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: save_building, save_unit, delete_building, delete_unit,
 *          bulk_add_units, geocode, save_settings, upload_image
 */
class Rental_Gates_Ajax_Buildings {

    public function __construct() {
        add_action('wp_ajax_rental_gates_upload_image', array($this, 'handle_image_upload'));
        add_action('wp_ajax_rental_gates_geocode', array($this, 'handle_geocode_request'));
        add_action('wp_ajax_rental_gates_save_building', array($this, 'handle_save_building'));
        add_action('wp_ajax_rental_gates_save_unit', array($this, 'handle_save_unit'));
        add_action('wp_ajax_rental_gates_delete_building', array($this, 'handle_delete_building'));
        add_action('wp_ajax_rental_gates_delete_unit', array($this, 'handle_delete_unit'));
        add_action('wp_ajax_rental_gates_bulk_add_units', array($this, 'handle_bulk_add_units'));
        add_action('wp_ajax_rental_gates_save_settings', array($this, 'handle_save_settings'));
    }

    private function get_org_id() {
        return Rental_Gates_Roles::get_organization_id();
    }

    private function verify_nonce() {
        check_ajax_referer('rental_gates_nonce', 'nonce');
    }

    /**
     * Handle image upload via AJAX
     */
    public function handle_image_upload()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        if (!isset($_FILES['file'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'rental-gates')));
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('file', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }

        // Optimize uploaded image
        if (class_exists('Rental_Gates_Image_Optimizer')) {
            $optimized = Rental_Gates_Image_Optimizer::optimize_attachment($attachment_id);
            if (is_wp_error($optimized)) {
                Rental_Gates_Logger::warning('buildings', 'Image optimization failed', array('attachment_id' => $attachment_id, 'error' => $optimized->get_error_message()));
            }
        }

        wp_send_json_success(array(
            'id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
            'thumbnail' => wp_get_attachment_image_url($attachment_id, 'medium'),
        ));
    }

    /**
     * Handle geocode request (reverse geocoding from pin)
     */
    public function handle_geocode_request()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        $lat = floatval($_POST['lat'] ?? 0);
        $lng = floatval($_POST['lng'] ?? 0);

        if (!$lat || !$lng) {
            wp_send_json_error(array('message' => __('Invalid coordinates', 'rental-gates')));
        }

        $map_service = rental_gates()->get_map_service();
        $result = $map_service->reverse_geocode($lat, $lng);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handle save building AJAX request
     */
    public function handle_save_building()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['building_nonce'] ?? '', 'rental_gates_building')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        // Check permissions - use correct capability name
        if (!current_user_can('rg_manage_buildings') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        // Validate required fields
        $lat = floatval($_POST['latitude'] ?? 0);
        $lng = floatval($_POST['longitude'] ?? 0);
        $address = sanitize_text_field($_POST['derived_address'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');

        if (!$lat || !$lng) {
            wp_send_json_error(__('Please place a pin on the map', 'rental-gates'));
        }

        if (empty($name)) {
            wp_send_json_error(__('Building name is required', 'rental-gates'));
        }

        // Get user's organization (will auto-create for admins if needed)
        $user_org_id = Rental_Gates_Roles::get_organization_id();

        if (!$user_org_id) {
            wp_send_json_error(__('No organization found. Please contact support.', 'rental-gates'));
        }

        // Use user's organization ID (ignore what was passed in form for security)
        $org_id = $user_org_id;

        // Check feature gate for new buildings
        $building_id = intval($_POST['building_id'] ?? 0);
        if (!$building_id) {
            // Creating new building - check limits
            $limit_check = rg_can_create('buildings', 1, $org_id);
            if (!$limit_check['allowed']) {
                wp_send_json_error(array(
                    'message' => $limit_check['message'],
                    'limit_reached' => true,
                    'current' => $limit_check['current'],
                    'limit' => $limit_check['limit'],
                ));
            }
        }

        // Handle amenities
        $amenities = isset($_POST['amenities']) ? array_map('sanitize_text_field', $_POST['amenities']) : array();

        // Prepare data
        $data = array(
            'organization_id' => $org_id,
            'name' => $name,
            'slug' => sanitize_title($name),
            'status' => sanitize_text_field($_POST['status'] ?? 'active'),
            'latitude' => $lat,
            'longitude' => $lng,
            'derived_address' => $address,
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'gallery' => $_POST['gallery'] ?? '[]', // Already JSON string from frontend
            'amenities' => $amenities, // Pass as array, model will encode
        );

        if ($building_id) {
            // Verify building belongs to user's organization before updating
            $existing = Rental_Gates_Building::get($building_id);
            if (!$existing || $existing['organization_id'] != $org_id) {
                wp_send_json_error(__('Building not found or access denied', 'rental-gates'));
            }

            // Update existing
            $result = Rental_Gates_Building::update($building_id, $data);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
        } else {
            // Create new
            $result = Rental_Gates_Building::create($data);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            // Result is the building array, get ID from it
            $building_id = is_array($result) ? $result['id'] : $result;
        }

        wp_send_json_success(array('id' => $building_id));
    }

    /**
     * Handle save unit AJAX request
     */
    public function handle_save_unit()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['unit_nonce'] ?? '', 'rental_gates_unit')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        // Check permissions - use correct capability name
        if (!current_user_can('rg_manage_units') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        // Validate required fields
        $building_id = intval($_POST['building_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $rent_amount = floatval($_POST['rent_amount'] ?? 0);

        if (!$building_id) {
            wp_send_json_error(__('Building is required', 'rental-gates'));
        }

        if (empty($name)) {
            wp_send_json_error(__('Unit name is required', 'rental-gates'));
        }

        // Get user's organization
        $user_org_id = Rental_Gates_Roles::get_organization_id();
        if (!$user_org_id) {
            wp_send_json_error(__('No organization found. Please contact support.', 'rental-gates'));
        }

        // Check feature gate for new units
        $unit_id = intval($_POST['unit_id'] ?? 0);
        if (!$unit_id) {
            // Creating new unit - check limits
            $limit_check = rg_can_create('units', 1, $user_org_id);
            if (!$limit_check['allowed']) {
                wp_send_json_error(array(
                    'message' => $limit_check['message'],
                    'limit_reached' => true,
                    'current' => $limit_check['current'],
                    'limit' => $limit_check['limit'],
                ));
            }
        }

        // Verify building belongs to user's organization
        $building = Rental_Gates_Building::get($building_id);
        if (!$building || $building['organization_id'] != $user_org_id) {
            wp_send_json_error(__('Building not found or access denied', 'rental-gates'));
        }

        // Handle amenities
        $amenities = isset($_POST['amenities']) ? array_map('sanitize_text_field', $_POST['amenities']) : array();

        // Prepare data
        $data = array(
            'organization_id' => $user_org_id,
            'building_id' => $building_id,
            'name' => $name,
            'slug' => sanitize_title($name),
            'unit_type' => sanitize_text_field($_POST['unit_type'] ?? ''),
            'rent_amount' => $rent_amount,
            'deposit_amount' => floatval($_POST['deposit_amount'] ?? 0),
            'bedrooms' => intval($_POST['bedrooms'] ?? 0),
            'bathrooms' => intval($_POST['bathrooms'] ?? 0),
            'living_rooms' => intval($_POST['living_rooms'] ?? 0),
            'kitchens' => intval($_POST['kitchens'] ?? 0),
            'parking_spots' => intval($_POST['parking_spots'] ?? 0),
            'square_footage' => intval($_POST['square_footage'] ?? 0),
            'availability' => sanitize_text_field($_POST['availability'] ?? 'available'),
            'available_from' => sanitize_text_field($_POST['available_from'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'gallery' => $_POST['gallery'] ?? '[]',
            'amenities' => $amenities, // Pass as array, model will encode
        );

        if ($unit_id) {
            // Verify unit belongs to user's organization before updating
            $existing = Rental_Gates_Unit::get($unit_id);
            if (!$existing || $existing['organization_id'] != $user_org_id) {
                wp_send_json_error(__('Unit not found or access denied', 'rental-gates'));
            }

            // Update existing
            $result = Rental_Gates_Unit::update($unit_id, $data);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
        } else {
            // Create new
            $result = Rental_Gates_Unit::create($data);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            // Result is the unit array, get ID from it
            $unit_id = is_array($result) ? $result['id'] : $result;
        }

        wp_send_json_success(array('id' => $unit_id));
    }

    /**
     * Handle delete building AJAX request
     */
    public function handle_delete_building()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        // Check permissions
        if (!current_user_can('rg_manage_buildings') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $building_id = intval($_POST['building_id'] ?? 0);
        if (!$building_id) {
            wp_send_json_error(__('Invalid building ID', 'rental-gates'));
        }

        // Get user's organization
        $user_org_id = Rental_Gates_Roles::get_organization_id();
        if (!$user_org_id) {
            wp_send_json_error(__('No organization found', 'rental-gates'));
        }

        // Verify building belongs to user's organization
        $building = Rental_Gates_Building::get($building_id);
        if (!$building || $building['organization_id'] != $user_org_id) {
            wp_send_json_error(__('Building not found or access denied', 'rental-gates'));
        }

        // Delete building
        $result = Rental_Gates_Building::delete($building_id);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('deleted' => true));
    }

    /**
     * Handle delete unit AJAX request
     */
    public function handle_delete_unit()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        // Check permissions
        if (!current_user_can('rg_manage_units') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $unit_id = intval($_POST['unit_id'] ?? 0);
        if (!$unit_id) {
            wp_send_json_error(__('Invalid unit ID', 'rental-gates'));
        }

        // Get user's organization
        $user_org_id = Rental_Gates_Roles::get_organization_id();
        if (!$user_org_id) {
            wp_send_json_error(__('No organization found', 'rental-gates'));
        }

        // Verify unit belongs to user's organization
        $unit = Rental_Gates_Unit::get($unit_id);
        if (!$unit || $unit['organization_id'] != $user_org_id) {
            wp_send_json_error(__('Unit not found or access denied', 'rental-gates'));
        }

        // Delete unit
        $result = Rental_Gates_Unit::delete($unit_id);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('deleted' => true, 'building_id' => $unit['building_id']));
    }

    /**
     * Handle bulk add units AJAX request
     */
    public function handle_bulk_add_units()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_bulk_units')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        // Check permissions
        if (!current_user_can('rg_manage_units') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $building_id = intval($_POST['building_id'] ?? 0);
        $prefix = sanitize_text_field($_POST['prefix'] ?? '');
        $start = intval($_POST['start'] ?? 1);
        $end = intval($_POST['end'] ?? 1);

        // Validate range
        $count = $end - $start + 1;
        if ($count <= 0 || $count > 100) {
            wp_send_json_error(__('Invalid unit range (1-100 units)', 'rental-gates'));
        }

        // Get user's organization
        $user_org_id = Rental_Gates_Roles::get_organization_id();
        if (!$user_org_id) {
            wp_send_json_error(__('No organization found', 'rental-gates'));
        }

        // Check feature gate - bulk operations module
        $module_check = rg_can_access_module('bulk_operations', $user_org_id);
        if (!$module_check['enabled']) {
            wp_send_json_error(array(
                'message' => $module_check['message'],
                'module_disabled' => true,
            ));
        }

        // Check feature gate - unit limits
        $limit_check = rg_can_create('units', $count, $user_org_id);
        if (!$limit_check['allowed']) {
            wp_send_json_error(array(
                'message' => $limit_check['message'],
                'limit_reached' => true,
                'current' => $limit_check['current'],
                'limit' => $limit_check['limit'],
                'remaining' => $limit_check['remaining'],
            ));
        }

        // Verify building belongs to user's organization
        $building = Rental_Gates_Building::get($building_id);
        if (!$building || $building['organization_id'] != $user_org_id) {
            wp_send_json_error(__('Building not found or access denied', 'rental-gates'));
        }

        // Prepare base unit data
        $base_data = array(
            'building_id' => $building_id,
            'bedrooms' => intval($_POST['bedrooms'] ?? 1),
            'bathrooms' => floatval($_POST['bathrooms'] ?? 1),
            'rent_amount' => floatval($_POST['rent'] ?? 0),
            'square_feet' => intval($_POST['sqft'] ?? 0),
            'availability' => sanitize_text_field($_POST['availability'] ?? 'available'),
            'status' => 'active',
        );

        $created = 0;
        $errors = array();
        $separator = $prefix ? ' ' : '';

        for ($i = $start; $i <= $end; $i++) {
            $unit_number = $prefix . $separator . $i;

            $data = array_merge($base_data, array(
                'unit_number' => $unit_number,
                'slug' => sanitize_title($building['slug'] . '-' . $unit_number),
            ));

            $result = Rental_Gates_Unit::create($data);
            if (is_wp_error($result)) {
                $errors[] = $unit_number . ': ' . $result->get_error_message();
            } else {
                $created++;
            }
        }

        if ($created === 0) {
            wp_send_json_error(__('Failed to create any units', 'rental-gates'));
        }

        $message = sprintf(__('%d units created', 'rental-gates'), $created);
        if (!empty($errors)) {
            $message .= '. ' . sprintf(__('%d errors', 'rental-gates'), count($errors));
        }

        wp_send_json_success(array(
            'created' => $created,
            'errors' => $errors,
            'message' => $message,
        ));
    }

    /**
     * Handle save settings AJAX request
     */
    public function handle_save_settings()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['settings_nonce'] ?? '', 'rental_gates_settings')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        // Check permissions - only owners/admins can edit settings
        if (!current_user_can('rg_manage_settings') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        // Get user's organization
        $user_org_id = Rental_Gates_Roles::get_organization_id();
        if (!$user_org_id) {
            wp_send_json_error(__('No organization found', 'rental-gates'));
        }

        // Collect settings data
        $data = array(
            'name' => sanitize_text_field($_POST['org_name'] ?? ''),
            'contact_email' => sanitize_email($_POST['contact_email'] ?? ''),
            'contact_phone' => sanitize_text_field($_POST['contact_phone'] ?? ''),
            'website' => esc_url_raw($_POST['website'] ?? ''),
            'address' => sanitize_textarea_field($_POST['address'] ?? ''),
            'timezone' => sanitize_text_field($_POST['timezone'] ?? 'America/New_York'),
            'currency' => sanitize_text_field($_POST['currency'] ?? 'USD'),
            'late_fee_grace_days' => intval($_POST['late_fee_grace_days'] ?? 5),
            'late_fee_type' => in_array($_POST['late_fee_type'] ?? '', array('flat', 'percentage')) ? $_POST['late_fee_type'] : 'flat',
            'late_fee_amount' => floatval($_POST['late_fee_amount'] ?? 0),
            'allow_partial_payments' => isset($_POST['allow_partial_payments']) ? 1 : 0,
            'coming_soon_window_days' => intval($_POST['coming_soon_window_days'] ?? 30),
            'renewal_notice_days' => intval($_POST['renewal_notice_days'] ?? 60),
        );

        // Update organization
        $result = Rental_Gates_Organization::update($user_org_id, $data);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Save map provider setting (global option)
        $map_provider = in_array($_POST['map_provider'] ?? '', array('google', 'openstreetmap')) ? $_POST['map_provider'] : 'google';
        update_option('rental_gates_map_provider', $map_provider);

        // Save Google Maps API key if provided
        if (!empty($_POST['google_maps_api_key'])) {
            update_option('rental_gates_google_maps_api_key', sanitize_text_field($_POST['google_maps_api_key']));
        }

        wp_send_json_success(array('message' => __('Settings saved successfully', 'rental-gates')));
    }
}
