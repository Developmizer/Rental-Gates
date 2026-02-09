<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handlers for Tenants
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: create_tenant, update_tenant, delete_tenant, invite_tenant
 */
class Rental_Gates_Ajax_Tenants {

    public function __construct() {
        add_action('wp_ajax_rental_gates_create_tenant', array($this, 'handle_create_tenant'));
        add_action('wp_ajax_rental_gates_update_tenant', array($this, 'handle_update_tenant'));
        add_action('wp_ajax_rental_gates_delete_tenant', array($this, 'handle_delete_tenant'));
        add_action('wp_ajax_rental_gates_invite_tenant', array($this, 'handle_invite_tenant'));
    }

    private function get_org_id() {
        return Rental_Gates_Roles::get_organization_id();
    }

    private function verify_nonce() {
        check_ajax_referer('rental_gates_nonce', 'nonce');
    }

    /**
     * Handle create tenant AJAX request
     */
    public function handle_create_tenant()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_tenants') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(__('No organization found', 'rental-gates'));
        }

        $data = array(
            'organization_id' => $org_id,
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'preferred_contact' => sanitize_text_field($_POST['preferred_contact'] ?? 'email'),
            'date_of_birth' => sanitize_text_field($_POST['date_of_birth'] ?? ''),
            'emergency_contact_name' => sanitize_text_field($_POST['emergency_contact_name'] ?? ''),
            'emergency_contact_phone' => sanitize_text_field($_POST['emergency_contact_phone'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'prospect'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        $result = Rental_Gates_Tenant::create($data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle update tenant AJAX request
     */
    public function handle_update_tenant()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_tenants') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        if (!$tenant_id) {
            wp_send_json_error(__('Invalid tenant ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $tenant = Rental_Gates_Tenant::get($tenant_id);

        if (!$tenant || $tenant['organization_id'] !== $org_id) {
            wp_send_json_error(__('Tenant not found or access denied', 'rental-gates'));
        }

        $data = array(
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'preferred_contact' => sanitize_text_field($_POST['preferred_contact'] ?? 'email'),
            'date_of_birth' => sanitize_text_field($_POST['date_of_birth'] ?? ''),
            'emergency_contact_name' => sanitize_text_field($_POST['emergency_contact_name'] ?? ''),
            'emergency_contact_phone' => sanitize_text_field($_POST['emergency_contact_phone'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        $result = Rental_Gates_Tenant::update($tenant_id, $data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle delete tenant AJAX request
     */
    public function handle_delete_tenant()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_tenants') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        if (!$tenant_id) {
            wp_send_json_error(__('Invalid tenant ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $tenant = Rental_Gates_Tenant::get($tenant_id);

        if (!$tenant || $tenant['organization_id'] !== $org_id) {
            wp_send_json_error(__('Tenant not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Tenant::delete($tenant_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('deleted' => true));
    }

    /**
     * Handle invite tenant to portal AJAX request
     */
    public function handle_invite_tenant()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_tenants') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        if (!$tenant_id) {
            wp_send_json_error(__('Invalid tenant ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $tenant = Rental_Gates_Tenant::get($tenant_id);

        if (!$tenant || $tenant['organization_id'] !== $org_id) {
            wp_send_json_error(__('Tenant not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Tenant::invite_to_portal($tenant_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }
}
