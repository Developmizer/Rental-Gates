<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handlers for AI Tools
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: ai_generate, ai_credit_purchase, admin_credit_adjustment
 */
class Rental_Gates_Ajax_AI {

    public function __construct() {
        add_action('wp_ajax_rg_ai_generate', array($this, 'handle_ai_generate'));
        add_action('wp_ajax_rg_purchase_ai_credits', array($this, 'handle_ai_credit_purchase'));
        add_action('wp_ajax_rg_admin_adjust_credits', array($this, 'handle_admin_credit_adjustment'));
    }

    public function handle_ai_generate()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rg_ai_generate')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'rental-gates')));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in to use AI tools.', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $user_id = get_current_user_id();

        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found.', 'rental-gates')));
        }

        if (!class_exists('Rental_Gates_AI')) {
            wp_send_json_error(array('message' => __('AI module not available.', 'rental-gates')));
        }

        if (!Rental_Gates_AI::org_has_access($org_id)) {
            wp_send_json_error(array('message' => __('Your plan does not include AI tools.', 'rental-gates')));
        }

        $ai = rental_gates_ai();
        if (!$ai->is_configured()) {
            wp_send_json_error(array('message' => __('AI provider not configured.', 'rental-gates')));
        }

        $tool = sanitize_key($_POST['tool'] ?? '');
        $generation_id = sanitize_text_field($_POST['generation_id'] ?? '');

        if (!$tool) {
            wp_send_json_error(array('message' => __('Invalid tool specified.', 'rental-gates')));
        }

        $idempotency_key = 'ai_gen_' . $org_id . '_' . $generation_id;
        $cached_result = get_transient($idempotency_key);
        if ($cached_result !== false) {
            wp_send_json_success($cached_result);
        }

        $entity_id = intval($_POST['entity_id'] ?? 0);
        $entity_type = sanitize_key($_POST['entity_type'] ?? '');
        $entity_data = array();

        if ($entity_id && $entity_type) {
            global $wpdb;
            $tables = Rental_Gates_Database::get_table_names();

            if ($entity_type === 'unit') {
                $entity_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT u.*, b.name as building_name, b.address as building_address
                     FROM {$tables['units']} u
                     JOIN {$tables['buildings']} b ON u.building_id = b.id
                     WHERE u.id = %d AND b.organization_id = %d",
                    $entity_id,
                    $org_id
                ), ARRAY_A) ?: array();
            } elseif ($entity_type === 'building') {
                $entity_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$tables['buildings']} WHERE id = %d AND organization_id = %d",
                    $entity_id,
                    $org_id
                ), ARRAY_A) ?: array();
            }
        }

        $result = null;

        switch ($tool) {
            case 'description':
                $result = $ai->generate_description(array(
                    'name' => sanitize_text_field($_POST['property_name'] ?? $entity_data['name'] ?? ''),
                    'unit_type' => sanitize_text_field($_POST['unit_type'] ?? 'apartment'),
                    'bedrooms' => intval($_POST['bedrooms'] ?? $entity_data['bedrooms'] ?? 0),
                    'bathrooms' => floatval($_POST['bathrooms'] ?? $entity_data['bathrooms'] ?? 0),
                    'sqft' => intval($_POST['sqft'] ?? $entity_data['sqft'] ?? 0),
                    'rent' => floatval($_POST['rent'] ?? $entity_data['rent'] ?? 0),
                    'address' => sanitize_text_field($_POST['address'] ?? $entity_data['building_address'] ?? ''),
                    'features' => sanitize_textarea_field($_POST['features'] ?? $entity_data['amenities'] ?? ''),
                    'style' => sanitize_key($_POST['style'] ?? 'professional'),
                ), $org_id, $user_id);
                break;

            case 'marketing':
                $result = $ai->generate_marketing(array(
                    'name' => sanitize_text_field($_POST['property_name'] ?? $entity_data['name'] ?? ''),
                    'rent' => floatval($_POST['rent'] ?? $entity_data['rent'] ?? 0),
                    'bedrooms' => intval($_POST['bedrooms'] ?? $entity_data['bedrooms'] ?? 0),
                    'address' => sanitize_text_field($_POST['address'] ?? $entity_data['building_address'] ?? ''),
                    'highlights' => sanitize_textarea_field($_POST['highlights'] ?? ''),
                    'format' => sanitize_key($_POST['format'] ?? 'social'),
                ), $org_id, $user_id);
                break;

            case 'maintenance':
                $result = $ai->triage_maintenance(array(
                    'title' => sanitize_text_field($_POST['title'] ?? ''),
                    'description' => sanitize_textarea_field($_POST['description'] ?? ''),
                    'location' => sanitize_text_field($_POST['location'] ?? ''),
                ), $org_id, $user_id);
                break;

            case 'message':
                $result = $ai->draft_message(array(
                    'type' => sanitize_key($_POST['message_type'] ?? 'general'),
                    'tone' => sanitize_key($_POST['tone'] ?? 'professional'),
                    'tenant_name' => sanitize_text_field($_POST['tenant_name'] ?? ''),
                    'property' => sanitize_text_field($_POST['property'] ?? $entity_data['name'] ?? ''),
                    'context' => sanitize_textarea_field($_POST['context'] ?? ''),
                    'specific_details' => sanitize_textarea_field($_POST['specific_details'] ?? ''),
                ), $org_id, $user_id);
                break;

            case 'insights':
            case 'availability':
                $result = $ai->get_portfolio_insights($org_id, $user_id);
                break;

            default:
                wp_send_json_error(array('message' => __('Unknown tool.', 'rental-gates')));
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $response = array(
            'content' => $result['description'] ?? $result['marketing'] ?? $result['analysis'] ?? $result['message'] ?? $result['insights'] ?? '',
            'credits_used' => $result['credits_used'] ?? 1,
            'credits_remaining' => $result['credits_remaining'] ?? Rental_Gates_AI::get_remaining_credits($org_id),
            'tool' => $tool,
            'entity_id' => $entity_id,
            'entity_type' => $entity_type,
            'generated_at' => current_time('mysql'),
        );

        $response = array_merge($result, $response);

        if ($generation_id) {
            set_transient($idempotency_key, $response, 5 * MINUTE_IN_SECONDS);
        }

        wp_send_json_success($response);
    }

    public function handle_ai_credit_purchase()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_credit_purchase')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'rental-gates')));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in to purchase credits.', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found.', 'rental-gates')));
        }

        $pack_id = sanitize_key($_POST['pack_id'] ?? '');
        if (!$pack_id) {
            wp_send_json_error(array('message' => __('Invalid credit pack.', 'rental-gates')));
        }

        if (!class_exists('Rental_Gates_AI_Credits')) {
            wp_send_json_error(array('message' => __('AI Credits module not available.', 'rental-gates')));
        }

        $credits_manager = rg_ai_credits();
        $result = $credits_manager->create_purchase($org_id, $pack_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    public function handle_admin_credit_adjustment()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'admin_credit_adjustment')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'rental-gates')));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'rental-gates')));
        }

        $org_id = intval($_POST['org_id'] ?? 0);
        $amount = intval($_POST['amount'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        $credit_type = sanitize_key($_POST['credit_type'] ?? 'bonus');
        $action = sanitize_key($_POST['adjustment_action'] ?? 'add');

        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found.', 'rental-gates')));
        }

        if (!$amount) {
            wp_send_json_error(array('message' => __('Invalid amount.', 'rental-gates')));
        }

        if (!class_exists('Rental_Gates_AI_Credits')) {
            wp_send_json_error(array('message' => __('AI Credits module not available.', 'rental-gates')));
        }

        $credits_manager = rg_ai_credits();

        if ($action === 'deduct') {
            $amount = -abs($amount);
        }

        $result = $credits_manager->admin_adjust($org_id, $amount, $reason, $credit_type, get_current_user_id());

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $balance = $credits_manager->get_balance($org_id);

        wp_send_json_success(array(
            'message' => __('Credits adjusted successfully.', 'rental-gates'),
            'balance' => $balance,
        ));
    }
}
