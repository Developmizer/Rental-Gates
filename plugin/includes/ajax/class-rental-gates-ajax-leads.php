<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handlers for Leads
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: create_lead, get_lead, update_lead, delete_lead,
 *          add_lead_note, add_lead_interest
 */
class Rental_Gates_Ajax_Leads {

    public function __construct() {
        add_action('wp_ajax_rental_gates_create_lead', array($this, 'handle_create_lead'));
        add_action('wp_ajax_rental_gates_get_lead', array($this, 'handle_get_lead'));
        add_action('wp_ajax_rental_gates_update_lead', array($this, 'handle_update_lead'));
        add_action('wp_ajax_rental_gates_delete_lead', array($this, 'handle_delete_lead'));
        add_action('wp_ajax_rental_gates_add_lead_note', array($this, 'handle_add_lead_note'));
        add_action('wp_ajax_rental_gates_add_lead_interest', array($this, 'handle_add_lead_interest'));
    }

    private function get_org_id() {
        return Rental_Gates_Roles::get_organization_id();
    }

    public function handle_create_lead()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        $org_id = $this->get_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $data = array(
            'organization_id' => $org_id,
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'source' => sanitize_text_field($_POST['source'] ?? 'manual'),
            'assigned_to' => intval($_POST['assigned_to'] ?? 0) ?: null,
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'follow_up_date' => !empty($_POST['follow_up_date']) ? sanitize_text_field($_POST['follow_up_date']) : null,
            'building_id' => intval($_POST['building_id'] ?? 0) ?: null,
        );

        $result = Rental_Gates_Lead::create($data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('lead_id' => $result));
    }

    public function handle_get_lead()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        $org_id = $this->get_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $lead_id = intval($_POST['lead_id'] ?? 0);
        if (!$lead_id) {
            wp_send_json_error(array('message' => __('Invalid lead ID', 'rental-gates')));
        }

        $lead = Rental_Gates_Lead::get_with_details($lead_id);

        if (!$lead || $lead['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Lead not found', 'rental-gates')));
        }

        wp_send_json_success(array('lead' => $lead));
    }

    public function handle_update_lead()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        $org_id = $this->get_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $lead_id = intval($_POST['lead_id'] ?? 0);
        if (!$lead_id) {
            wp_send_json_error(array('message' => __('Invalid lead ID', 'rental-gates')));
        }

        $lead = Rental_Gates_Lead::get($lead_id);
        if (!$lead || $lead['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Lead not found', 'rental-gates')));
        }

        $data = array();

        if (isset($_POST['name']))
            $data['name'] = sanitize_text_field($_POST['name']);
        if (isset($_POST['email']))
            $data['email'] = sanitize_email($_POST['email']);
        if (isset($_POST['phone']))
            $data['phone'] = sanitize_text_field($_POST['phone']);
        if (isset($_POST['stage']))
            $data['stage'] = sanitize_text_field($_POST['stage']);
        if (isset($_POST['assigned_to']))
            $data['assigned_to'] = intval($_POST['assigned_to']) ?: null;
        if (isset($_POST['follow_up_date']))
            $data['follow_up_date'] = $_POST['follow_up_date'] ?: null;
        if (isset($_POST['notes']))
            $data['notes'] = sanitize_textarea_field($_POST['notes']);
        if (isset($_POST['lost_reason']))
            $data['lost_reason'] = sanitize_textarea_field($_POST['lost_reason']);

        $result = Rental_Gates_Lead::update($lead_id, $data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('lead' => $result));
    }

    public function handle_delete_lead()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        $org_id = $this->get_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $lead_id = intval($_POST['lead_id'] ?? 0);
        if (!$lead_id) {
            wp_send_json_error(array('message' => __('Invalid lead ID', 'rental-gates')));
        }

        $lead = Rental_Gates_Lead::get($lead_id);
        if (!$lead || $lead['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Lead not found', 'rental-gates')));
        }

        $result = Rental_Gates_Lead::delete($lead_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success();
    }

    public function handle_add_lead_note()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        $org_id = $this->get_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $lead_id = intval($_POST['lead_id'] ?? 0);
        $note = sanitize_textarea_field($_POST['note'] ?? '');

        if (!$lead_id || !$note) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        $lead = Rental_Gates_Lead::get($lead_id);
        if (!$lead || $lead['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Lead not found', 'rental-gates')));
        }

        $result = Rental_Gates_Lead::add_note($lead_id, $note);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('lead' => $result));
    }

    public function handle_add_lead_interest()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        $org_id = $this->get_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $lead_id = intval($_POST['lead_id'] ?? 0);
        $building_id = intval($_POST['building_id'] ?? 0);
        $unit_id = intval($_POST['unit_id'] ?? 0);

        if (!$lead_id || (!$building_id && !$unit_id)) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        $lead = Rental_Gates_Lead::get($lead_id);
        if (!$lead || $lead['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Lead not found', 'rental-gates')));
        }

        $result = Rental_Gates_Lead::add_interest($lead_id, $building_id ?: null, $unit_id ?: null);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to add interest', 'rental-gates')));
        }

        wp_send_json_success(array('interest_id' => $result));
    }
}
