<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handlers for Vendors
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: create_vendor, update_vendor, delete_vendor, assign_vendor,
 *          remove_vendor_assignment, get_vendors_for_category, invite_vendor
 */
class Rental_Gates_Ajax_Vendors {

    public function __construct() {
        add_action('wp_ajax_rental_gates_create_vendor', array($this, 'handle_create_vendor'));
        add_action('wp_ajax_rental_gates_update_vendor', array($this, 'handle_update_vendor'));
        add_action('wp_ajax_rental_gates_delete_vendor', array($this, 'handle_delete_vendor'));
        add_action('wp_ajax_rental_gates_assign_vendor', array($this, 'handle_assign_vendor'));
        add_action('wp_ajax_rental_gates_remove_vendor_assignment', array($this, 'handle_remove_vendor_assignment'));
        add_action('wp_ajax_rental_gates_get_vendors_for_category', array($this, 'handle_get_vendors_for_category'));
        add_action('wp_ajax_rental_gates_invite_vendor', array($this, 'handle_invite_vendor'));
    }

    private function get_org_id() {
        return Rental_Gates_Roles::get_organization_id();
    }

    private function verify_nonce() {
        check_ajax_referer('rental_gates_nonce', 'nonce');
    }

    /**
     * Handle create vendor
     */
    public function handle_create_vendor()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_vendors') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $data = array(
            'organization_id' => $org_id,
            'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
            'contact_name' => sanitize_text_field($_POST['contact_name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'hourly_rate' => !empty($_POST['hourly_rate']) ? floatval($_POST['hourly_rate']) : null,
            'service_categories' => isset($_POST['service_categories']) ? array_map('sanitize_text_field', $_POST['service_categories']) : array(),
            'service_buildings' => isset($_POST['service_buildings']) ? array_map('intval', $_POST['service_buildings']) : array(),
            'status' => sanitize_text_field($_POST['status'] ?? 'active'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        $result = Rental_Gates_Vendor::create($data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('vendor_id' => $result));
    }

    /**
     * Handle update vendor
     */
    public function handle_update_vendor()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_vendors') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $vendor_id = intval($_POST['vendor_id'] ?? 0);
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor ID', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $vendor = Rental_Gates_Vendor::get($vendor_id);

        if (!$vendor || $vendor['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Vendor not found or access denied', 'rental-gates')));
        }

        $data = array(
            'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
            'contact_name' => sanitize_text_field($_POST['contact_name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'hourly_rate' => isset($_POST['hourly_rate']) && $_POST['hourly_rate'] !== '' ? floatval($_POST['hourly_rate']) : null,
            'service_categories' => isset($_POST['service_categories']) ? array_map('sanitize_text_field', $_POST['service_categories']) : array(),
            'service_buildings' => isset($_POST['service_buildings']) ? array_map('intval', $_POST['service_buildings']) : array(),
            'status' => sanitize_text_field($_POST['status'] ?? 'active'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        $result = Rental_Gates_Vendor::update($vendor_id, $data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('vendor_id' => $vendor_id));
    }

    /**
     * Handle delete vendor
     */
    public function handle_delete_vendor()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_vendors') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $vendor_id = intval($_POST['vendor_id'] ?? 0);
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor ID', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $vendor = Rental_Gates_Vendor::get($vendor_id);

        if (!$vendor || $vendor['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Vendor not found or access denied', 'rental-gates')));
        }

        $result = Rental_Gates_Vendor::delete($vendor_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success();
    }

    /**
     * Handle assign vendor to work order
     */
    public function handle_assign_vendor()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_maintenance') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $vendor_id = intval($_POST['vendor_id'] ?? 0);
        $work_order_id = intval($_POST['work_order_id'] ?? 0);

        if (!$vendor_id || !$work_order_id) {
            wp_send_json_error(array('message' => __('Invalid vendor or work order ID', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();

        // Verify work order belongs to org
        $work_order = Rental_Gates_Maintenance::get($work_order_id);
        if (!$work_order || $work_order['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Work order not found', 'rental-gates')));
        }

        // Verify vendor belongs to org
        $vendor = Rental_Gates_Vendor::get($vendor_id);
        if (!$vendor || $vendor['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Vendor not found', 'rental-gates')));
        }

        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $result = Rental_Gates_Vendor::assign_to_work_order($vendor_id, $work_order_id, $notes);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('assignment_id' => $result));
    }

    /**
     * Handle remove vendor from work order
     */
    public function handle_remove_vendor_assignment()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_maintenance') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $vendor_id = intval($_POST['vendor_id'] ?? 0);
        $work_order_id = intval($_POST['work_order_id'] ?? 0);

        if (!$vendor_id || !$work_order_id) {
            wp_send_json_error(array('message' => __('Invalid vendor or work order ID', 'rental-gates')));
        }

        $result = Rental_Gates_Vendor::remove_from_work_order($vendor_id, $work_order_id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to remove vendor assignment', 'rental-gates')));
        }

        wp_send_json_success();
    }

    /**
     * Handle get vendors for category (for work order assignment dropdown)
     */
    public function handle_get_vendors_for_category()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $category = sanitize_text_field($_POST['category'] ?? '');
        $building_id = intval($_POST['building_id'] ?? 0);

        $vendors = Rental_Gates_Vendor::get_by_category($org_id, $category, $building_id ?: null);

        wp_send_json_success(array('vendors' => $vendors));
    }

    /**
     * Handle inviting vendor to portal
     */
    public function handle_invite_vendor()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_vendors')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $vendor_id = intval($_POST['vendor_id'] ?? 0);
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor ID', 'rental-gates')));
        }

        // Verify vendor belongs to user's organization
        $org_id = Rental_Gates_Roles::get_organization_id();
        $vendor = Rental_Gates_Vendor::get($vendor_id);

        if (!$vendor || $vendor['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Vendor not found', 'rental-gates')));
        }

        // Send invitation
        $result = Rental_Gates_Vendor::invite_to_portal($vendor_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('vendor' => $result));
    }
}
