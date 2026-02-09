<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handlers for Applications
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: approve_application, decline_application, screen_application, delete_application
 */
class Rental_Gates_Ajax_Applications {

    public function __construct() {
        add_action('wp_ajax_rental_gates_approve_application', array($this, 'handle_approve_application'));
        add_action('wp_ajax_rental_gates_decline_application', array($this, 'handle_decline_application'));
        add_action('wp_ajax_rental_gates_screen_application', array($this, 'handle_screen_application'));
        add_action('wp_ajax_rental_gates_delete_application', array($this, 'handle_delete_application'));
    }

    private function get_org_id() {
        return Rental_Gates_Roles::get_organization_id();
    }

    private function verify_nonce() {
        check_ajax_referer('rental_gates_nonce', 'nonce');
    }

    /**
     * Handle approve application AJAX request
     */
    public function handle_approve_application()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_applications') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $application_id = intval($_POST['application_id'] ?? 0);
        if (!$application_id) {
            wp_send_json_error(__('Invalid application ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $application = Rental_Gates_Application::get($application_id);

        if (!$application || $application['organization_id'] != $org_id) {
            wp_send_json_error(__('Application not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Application::approve($application_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('approved' => true));
    }

    /**
     * Handle decline application AJAX request
     */
    public function handle_decline_application()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_applications') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $application_id = intval($_POST['application_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        if (!$application_id) {
            wp_send_json_error(__('Invalid application ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $application = Rental_Gates_Application::get($application_id);

        if (!$application || $application['organization_id'] != $org_id) {
            wp_send_json_error(__('Application not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Application::decline($application_id, $reason);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('declined' => true));
    }

    /**
     * Handle AI screening of application
     */
    public function handle_screen_application()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_applications') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $application_id = intval($_POST['application_id'] ?? 0);
        if (!$application_id) {
            wp_send_json_error(__('Invalid application ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $application = Rental_Gates_Application::get($application_id);

        if (!$application || $application['organization_id'] != $org_id) {
            wp_send_json_error(__('Application not found or access denied', 'rental-gates'));
        }

        // Check AI access
        if (!class_exists('Rental_Gates_AI') || !Rental_Gates_AI::org_has_access($org_id)) {
            wp_send_json_error(__('AI screening is not available on your plan', 'rental-gates'));
        }

        $ai = rental_gates_ai();
        $result = $ai->screen_application($application_id, $org_id, get_current_user_id());

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle delete application AJAX request
     */
    public function handle_delete_application()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_applications') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $application_id = intval($_POST['application_id'] ?? 0);
        if (!$application_id) {
            wp_send_json_error(__('Invalid application ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $application = Rental_Gates_Application::get($application_id);

        if (!$application || $application['organization_id'] != $org_id) {
            wp_send_json_error(__('Application not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Application::delete($application_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('deleted' => true));
    }
}
