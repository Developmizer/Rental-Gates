<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handlers for Maintenance / Work Orders
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: create_maintenance, update_maintenance, delete_maintenance,
 *          update_maintenance_status, complete_maintenance, add_maintenance_note
 */
class Rental_Gates_Ajax_Maintenance {

    public function __construct() {
        add_action('wp_ajax_rental_gates_create_maintenance', array($this, 'handle_create_maintenance'));
        add_action('wp_ajax_rental_gates_update_maintenance', array($this, 'handle_update_maintenance'));
        add_action('wp_ajax_rental_gates_delete_maintenance', array($this, 'handle_delete_maintenance'));
        add_action('wp_ajax_rental_gates_update_maintenance_status', array($this, 'handle_update_maintenance_status'));
        add_action('wp_ajax_rental_gates_complete_maintenance', array($this, 'handle_complete_maintenance'));
        add_action('wp_ajax_rental_gates_add_maintenance_note', array($this, 'handle_add_maintenance_note'));
    }

    private function get_org_id() {
        return Rental_Gates_Roles::get_organization_id();
    }

    private function verify_nonce() {
        check_ajax_referer('rental_gates_nonce', 'nonce');
    }

    /**
     * Handle create maintenance AJAX request
     */
    public function handle_create_maintenance()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_maintenance') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(__('Organization not found', 'rental-gates'));
        }

        $data = array(
            'organization_id' => $org_id,
            'building_id' => intval($_POST['building_id'] ?? 0),
            'unit_id' => intval($_POST['unit_id'] ?? 0) ?: null,
            'tenant_id' => intval($_POST['tenant_id'] ?? 0) ?: null,
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? 'general'),
            'priority' => sanitize_text_field($_POST['priority'] ?? 'medium'),
            'permission_to_enter' => !empty($_POST['permission_to_enter']),
            'access_instructions' => sanitize_textarea_field($_POST['access_instructions'] ?? ''),
            'cost_estimate' => floatval($_POST['cost_estimate'] ?? 0) ?: null,
            'scheduled_date' => sanitize_text_field($_POST['scheduled_date'] ?? ''),
            'internal_notes' => sanitize_textarea_field($_POST['internal_notes'] ?? ''),
        );

        $result = Rental_Gates_Maintenance::create($data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('work_order' => $result));
    }

    /**
     * Handle update maintenance AJAX request
     */
    public function handle_update_maintenance()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_maintenance') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $work_order_id = intval($_POST['work_order_id'] ?? 0);
        if (!$work_order_id) {
            wp_send_json_error(__('Invalid work order ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $work_order = Rental_Gates_Maintenance::get($work_order_id);

        if (!$work_order || $work_order['organization_id'] !== $org_id) {
            wp_send_json_error(__('Work order not found or access denied', 'rental-gates'));
        }

        $data = array(
            'building_id' => intval($_POST['building_id'] ?? 0),
            'unit_id' => intval($_POST['unit_id'] ?? 0) ?: null,
            'tenant_id' => intval($_POST['tenant_id'] ?? 0) ?: null,
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? ''),
            'priority' => sanitize_text_field($_POST['priority'] ?? ''),
            'permission_to_enter' => !empty($_POST['permission_to_enter']),
            'access_instructions' => sanitize_textarea_field($_POST['access_instructions'] ?? ''),
            'cost_estimate' => floatval($_POST['cost_estimate'] ?? 0) ?: null,
            'scheduled_date' => sanitize_text_field($_POST['scheduled_date'] ?? ''),
            'internal_notes' => sanitize_textarea_field($_POST['internal_notes'] ?? ''),
        );

        $result = Rental_Gates_Maintenance::update($work_order_id, $data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('work_order' => $result));
    }

    /**
     * Handle delete maintenance AJAX request
     */
    public function handle_delete_maintenance()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_maintenance') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $work_order_id = intval($_POST['work_order_id'] ?? 0);
        if (!$work_order_id) {
            wp_send_json_error(__('Invalid work order ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $work_order = Rental_Gates_Maintenance::get($work_order_id);

        if (!$work_order || $work_order['organization_id'] !== $org_id) {
            wp_send_json_error(__('Work order not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Maintenance::delete($work_order_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('deleted' => true));
    }

    /**
     * Handle update maintenance status AJAX request
     */
    public function handle_update_maintenance_status()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_maintenance') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $work_order_id = intval($_POST['work_order_id'] ?? 0);
        if (!$work_order_id) {
            wp_send_json_error(__('Invalid work order ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $work_order = Rental_Gates_Maintenance::get($work_order_id);

        if (!$work_order || $work_order['organization_id'] !== $org_id) {
            wp_send_json_error(__('Work order not found or access denied', 'rental-gates'));
        }

        $status = sanitize_text_field($_POST['status'] ?? '');
        $result = Rental_Gates_Maintenance::update_status($work_order_id, $status);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('work_order' => $result));
    }

    /**
     * Handle complete maintenance AJAX request
     */
    public function handle_complete_maintenance()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_maintenance') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $work_order_id = intval($_POST['work_order_id'] ?? 0);
        if (!$work_order_id) {
            wp_send_json_error(__('Invalid work order ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $work_order = Rental_Gates_Maintenance::get($work_order_id);

        if (!$work_order || $work_order['organization_id'] !== $org_id) {
            wp_send_json_error(__('Work order not found or access denied', 'rental-gates'));
        }

        $final_cost = floatval($_POST['final_cost'] ?? 0) ?: null;
        $cause = sanitize_text_field($_POST['cause'] ?? '') ?: null;

        $result = Rental_Gates_Maintenance::complete($work_order_id, $final_cost, $cause);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('work_order' => $result));
    }

    /**
     * Handle add maintenance note AJAX request
     */
    public function handle_add_maintenance_note()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_maintenance') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $work_order_id = intval($_POST['work_order_id'] ?? 0);
        if (!$work_order_id) {
            wp_send_json_error(__('Invalid work order ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $work_order = Rental_Gates_Maintenance::get($work_order_id);

        if (!$work_order || $work_order['organization_id'] !== $org_id) {
            wp_send_json_error(__('Work order not found or access denied', 'rental-gates'));
        }

        $note = sanitize_textarea_field($_POST['note'] ?? '');
        if (empty($note)) {
            wp_send_json_error(__('Note cannot be empty', 'rental-gates'));
        }

        $is_internal = !empty($_POST['is_internal']);
        $user_id = get_current_user_id();

        $result = Rental_Gates_Maintenance::add_note($work_order_id, $user_id, 'staff', $note, $is_internal);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('note_id' => $result));
    }
}
