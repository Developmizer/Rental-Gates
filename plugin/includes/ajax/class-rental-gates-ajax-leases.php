<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handlers for Leases
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: save_lease, delete_lease, add_lease_tenant, remove_lease_tenant,
 *          sign_lease, activate_lease, terminate_lease, renew_lease
 */
class Rental_Gates_Ajax_Leases {

    public function __construct() {
        add_action('wp_ajax_rental_gates_save_lease', array($this, 'handle_save_lease'));
        add_action('wp_ajax_rental_gates_delete_lease', array($this, 'handle_delete_lease'));
        add_action('wp_ajax_rental_gates_add_lease_tenant', array($this, 'handle_add_lease_tenant'));
        add_action('wp_ajax_rental_gates_remove_lease_tenant', array($this, 'handle_remove_lease_tenant'));
        add_action('wp_ajax_rental_gates_sign_lease', array($this, 'handle_sign_lease'));
        add_action('wp_ajax_rental_gates_activate_lease', array($this, 'handle_activate_lease'));
        add_action('wp_ajax_rental_gates_terminate_lease', array($this, 'handle_terminate_lease'));
        add_action('wp_ajax_rental_gates_renew_lease', array($this, 'handle_renew_lease'));
    }

    private function get_org_id() {
        return Rental_Gates_Roles::get_organization_id();
    }

    private function verify_nonce() {
        check_ajax_referer('rental_gates_nonce', 'nonce');
    }

    /**
     * Handle save (create/update) lease AJAX request
     */
    public function handle_save_lease()
    {
        if (!wp_verify_nonce($_POST['lease_nonce'] ?? '', 'rental_gates_lease')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_leases') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(__('No organization found', 'rental-gates'));
        }

        $unit_id = intval($_POST['unit_id'] ?? 0);
        if (!$unit_id) {
            wp_send_json_error(__('Please select a unit', 'rental-gates'));
        }

        // Verify unit belongs to org
        $unit = Rental_Gates_Unit::get($unit_id);
        if (!$unit) {
            wp_send_json_error(__('Unit not found', 'rental-gates'));
        }

        $building = Rental_Gates_Building::get($unit['building_id']);
        if (!$building || $building['organization_id'] != $org_id) {
            wp_send_json_error(__('Unit not found or access denied', 'rental-gates'));
        }

        $lease_id = intval($_POST['lease_id'] ?? 0);

        // Check feature gate for new leases
        if (!$lease_id) {
            $limit_check = rg_can_create('leases', 1, $org_id);
            if (!$limit_check['allowed']) {
                wp_send_json_error(array(
                    'message' => $limit_check['message'],
                    'limit_reached' => true,
                    'current' => $limit_check['current'],
                    'limit' => $limit_check['limit'],
                ));
            }
        }

        $data = array(
            'organization_id' => $org_id,
            'unit_id' => $unit_id,
            'status' => sanitize_text_field($_POST['status'] ?? 'draft'),
            'lease_type' => sanitize_text_field($_POST['lease_type'] ?? 'fixed'),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date' => sanitize_text_field($_POST['end_date'] ?? ''),
            'rent_amount' => floatval($_POST['rent_amount'] ?? 0),
            'deposit_amount' => floatval($_POST['deposit_amount'] ?? 0),
            'payment_day' => intval($_POST['payment_day'] ?? 1),
            'payment_frequency' => sanitize_text_field($_POST['payment_frequency'] ?? 'monthly'),
            'late_fee_amount' => floatval($_POST['late_fee_amount'] ?? 0),
            'late_fee_grace_days' => intval($_POST['late_fee_grace_days'] ?? 5),
            'terms' => sanitize_textarea_field($_POST['terms'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        if ($lease_id) {
            // Verify lease belongs to org
            $existing = Rental_Gates_Lease::get($lease_id);
            if (!$existing || $existing['organization_id'] != $org_id) {
                wp_send_json_error(__('Lease not found or access denied', 'rental-gates'));
            }

            $result = Rental_Gates_Lease::update($lease_id, $data);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
        } else {
            $result = Rental_Gates_Lease::create($data);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            $lease_id = is_array($result) ? $result['id'] : $result;
        }

        // Handle tenants assignment
        if (isset($_POST['tenant_ids']) && is_array($_POST['tenant_ids'])) {
            $tenant_ids = array_map('intval', $_POST['tenant_ids']);
            Rental_Gates_Lease::sync_tenants($lease_id, $tenant_ids, $org_id);
        }

        wp_send_json_success(array('id' => $lease_id));
    }

    /**
     * Handle delete lease AJAX request
     */
    public function handle_delete_lease()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_leases') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $lease_id = intval($_POST['lease_id'] ?? 0);
        if (!$lease_id) {
            wp_send_json_error(__('Invalid lease ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $lease = Rental_Gates_Lease::get($lease_id);

        if (!$lease || $lease['organization_id'] != $org_id) {
            wp_send_json_error(__('Lease not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Lease::delete($lease_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('deleted' => true));
    }

    /**
     * Handle add tenant to lease AJAX request
     */
    public function handle_add_lease_tenant()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_leases') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $lease_id = intval($_POST['lease_id'] ?? 0);
        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        $role = sanitize_text_field($_POST['role'] ?? 'primary');

        if (!$lease_id || !$tenant_id) {
            wp_send_json_error(__('Missing required fields', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();

        // Verify lease belongs to org
        $lease = Rental_Gates_Lease::get($lease_id);
        if (!$lease || $lease['organization_id'] != $org_id) {
            wp_send_json_error(__('Lease not found', 'rental-gates'));
        }

        // Verify tenant belongs to org
        $tenant = Rental_Gates_Tenant::get($tenant_id);
        if (!$tenant || $tenant['organization_id'] != $org_id) {
            wp_send_json_error(__('Tenant not found', 'rental-gates'));
        }

        $result = Rental_Gates_Lease::add_tenant($lease_id, $tenant_id, $role);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle remove tenant from lease AJAX request
     */
    public function handle_remove_lease_tenant()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_leases') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $lease_id = intval($_POST['lease_id'] ?? 0);
        $tenant_id = intval($_POST['tenant_id'] ?? 0);

        if (!$lease_id || !$tenant_id) {
            wp_send_json_error(__('Missing required fields', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();

        // Verify lease belongs to org
        $lease = Rental_Gates_Lease::get($lease_id);
        if (!$lease || $lease['organization_id'] != $org_id) {
            wp_send_json_error(__('Lease not found', 'rental-gates'));
        }

        $result = Rental_Gates_Lease::remove_tenant($lease_id, $tenant_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('removed' => true));
    }

    /**
     * Handle lease signing
     */
    public function handle_sign_lease()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Please log in', 'rental-gates'));
        }

        $lease_id = intval($_POST['lease_id'] ?? 0);
        $signature_data = sanitize_text_field($_POST['signature'] ?? '');

        if (!$lease_id || !$signature_data) {
            wp_send_json_error(__('Missing required fields', 'rental-gates'));
        }

        $result = Rental_Gates_Lease::sign($lease_id, get_current_user_id(), $signature_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle lease activation
     */
    public function handle_activate_lease()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_leases') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $lease_id = intval($_POST['lease_id'] ?? 0);
        if (!$lease_id) {
            wp_send_json_error(__('Invalid lease', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $lease = Rental_Gates_Lease::get($lease_id);

        if (!$lease || $lease['organization_id'] != $org_id) {
            wp_send_json_error(__('Lease not found', 'rental-gates'));
        }

        $result = Rental_Gates_Lease::activate($lease_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('activated' => true));
    }

    /**
     * Handle lease termination
     */
    public function handle_terminate_lease()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_leases') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $lease_id = intval($_POST['lease_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        if (!$lease_id) {
            wp_send_json_error(__('Invalid lease', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $lease = Rental_Gates_Lease::get($lease_id);

        if (!$lease || $lease['organization_id'] != $org_id) {
            wp_send_json_error(__('Lease not found', 'rental-gates'));
        }

        $result = Rental_Gates_Lease::terminate($lease_id, $reason);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('terminated' => true));
    }

    /**
     * Handle lease renewal
     */
    public function handle_renew_lease()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_leases') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $lease_id = intval($_POST['lease_id'] ?? 0);
        $new_end_date = sanitize_text_field($_POST['new_end_date'] ?? '');
        $new_rent = floatval($_POST['new_rent_amount'] ?? 0);

        if (!$lease_id || !$new_end_date) {
            wp_send_json_error(__('Please provide lease ID and new end date', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $lease = Rental_Gates_Lease::get($lease_id);

        if (!$lease || $lease['organization_id'] != $org_id) {
            wp_send_json_error(__('Lease not found', 'rental-gates'));
        }

        $renewal_data = array(
            'end_date' => $new_end_date,
        );
        if ($new_rent > 0) {
            $renewal_data['rent_amount'] = $new_rent;
        }

        $result = Rental_Gates_Lease::renew($lease_id, $renewal_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }
}
