<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handlers for Messages & Announcements
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: send_message, start_conversation, start_thread,
 *          get_new_messages, create_announcement, send_announcement
 */
class Rental_Gates_Ajax_Messages {

    public function __construct() {
        add_action('wp_ajax_rental_gates_send_message', array($this, 'handle_send_message'));
        add_action('wp_ajax_rental_gates_start_conversation', array($this, 'handle_start_conversation'));
        add_action('wp_ajax_rental_gates_start_thread', array($this, 'handle_start_thread'));
        add_action('wp_ajax_rental_gates_get_new_messages', array($this, 'handle_get_new_messages'));
        add_action('wp_ajax_rental_gates_create_announcement', array($this, 'handle_create_announcement'));
        add_action('wp_ajax_rental_gates_send_announcement', array($this, 'handle_send_announcement'));
    }

    public function handle_send_message()
    {
        $nonce_valid = wp_verify_nonce($_POST['message_nonce'] ?? '', 'rental_gates_message') ||
            wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce');

        if (!$nonce_valid) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Please log in', 'rental-gates'));
        }

        $thread_id = intval($_POST['thread_id'] ?? 0);
        $message_text = sanitize_textarea_field($_POST['message'] ?? $_POST['content'] ?? '');

        if (!$thread_id || empty($message_text)) {
            wp_send_json_error(__('Missing required fields', 'rental-gates'));
        }

        $current_user_id = get_current_user_id();
        $sender_id = $current_user_id;
        $sender_type = 'staff';

        $user = wp_get_current_user();
        if (in_array('rental_gates_tenant', $user->roles)) {
            $sender_type = 'tenant';
            global $wpdb;
            $tables = Rental_Gates_Database::get_table_names();
            $tenant_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tables['tenants']} WHERE user_id = %d AND status = 'active' LIMIT 1",
                $current_user_id
            ));
            if ($tenant_id) {
                $sender_id = intval($tenant_id);
            }
        } elseif (in_array('rental_gates_vendor', $user->roles)) {
            $sender_type = 'vendor';
        }

        if (!empty($_POST['sender_type'])) {
            $sender_type = sanitize_text_field($_POST['sender_type']);
            if ($sender_type === 'tenant') {
                global $wpdb;
                $tables = Rental_Gates_Database::get_table_names();
                $tenant_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$tables['tenants']} WHERE user_id = %d AND status = 'active' LIMIT 1",
                    $current_user_id
                ));
                if ($tenant_id) {
                    $sender_id = intval($tenant_id);
                }
            }
        }

        $result = Rental_Gates_Message::send($thread_id, $sender_id, $sender_type, $message_text);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        if (is_array($result)) {
            $result['content'] = $result['message'];
        }

        wp_send_json_success(array('message' => $result));
    }

    public function handle_start_conversation()
    {
        if (!wp_verify_nonce($_POST['message_nonce'] ?? '', 'rental_gates_message')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Please log in', 'rental-gates'));
        }

        $contact_id = intval($_POST['contact_id'] ?? 0);
        $contact_type = sanitize_text_field($_POST['contact_type'] ?? '');

        if (!$contact_id || !in_array($contact_type, array('staff', 'tenant', 'vendor'))) {
            wp_send_json_error(__('Invalid contact', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(__('Organization not found', 'rental-gates'));
        }

        $current_user_id = get_current_user_id();
        $current_user_type = 'staff';

        $thread = Rental_Gates_Message::get_or_create_thread(
            $org_id,
            $current_user_id,
            $current_user_type,
            $contact_id,
            $contact_type
        );

        if (!$thread) {
            wp_send_json_error(__('Failed to create conversation', 'rental-gates'));
        }

        wp_send_json_success(array('thread_id' => $thread['id']));
    }

    public function handle_start_thread()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Please log in', 'rental-gates'));
        }

        $recipient_id = intval($_POST['recipient_id'] ?? 0);
        $recipient_type = sanitize_text_field($_POST['recipient_type'] ?? 'staff');
        $sender_type = sanitize_text_field($_POST['sender_type'] ?? 'staff');

        if (!$recipient_id) {
            wp_send_json_error(__('Invalid recipient', 'rental-gates'));
        }

        $current_user_id = get_current_user_id();
        $sender_id = $current_user_id;

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        if ($sender_type === 'tenant') {
            $tenant = $wpdb->get_row($wpdb->prepare(
                "SELECT id, organization_id FROM {$tables['tenants']} WHERE user_id = %d AND status = 'active' LIMIT 1",
                $current_user_id
            ), ARRAY_A);

            if (!$tenant) {
                wp_send_json_error(__('Tenant record not found', 'rental-gates'));
            }

            $sender_id = $tenant['id'];
            $org_id = $tenant['organization_id'];
        } else {
            $org_id = Rental_Gates_Roles::get_organization_id();
        }

        if (!$org_id) {
            wp_send_json_error(__('Organization not found', 'rental-gates'));
        }

        $thread = Rental_Gates_Message::get_or_create_thread(
            $org_id,
            $sender_id,
            $sender_type,
            $recipient_id,
            $recipient_type
        );

        if (!$thread) {
            wp_send_json_error(__('Failed to create conversation', 'rental-gates'));
        }

        wp_send_json_success(array('thread_id' => $thread['id']));
    }

    public function handle_get_new_messages()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Please log in', 'rental-gates'));
        }

        $thread_id = intval($_POST['thread_id'] ?? 0);
        $after_id = intval($_POST['after_id'] ?? 0);

        if (!$thread_id) {
            wp_send_json_error(__('Invalid thread', 'rental-gates'));
        }

        $thread = Rental_Gates_Message::get_thread($thread_id);
        if (!$thread) {
            wp_send_json_error(__('Thread not found', 'rental-gates'));
        }

        $current_user_id = get_current_user_id();
        $current_user_type = 'staff';

        $user = wp_get_current_user();
        if (in_array('rental_gates_tenant', $user->roles)) {
            $current_user_type = 'tenant';
            global $wpdb;
            $tables = Rental_Gates_Database::get_table_names();
            $tenant_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tables['tenants']} WHERE user_id = %d AND status = 'active' LIMIT 1",
                $current_user_id
            ));
            if ($tenant_id) {
                $current_user_id = $tenant_id;
            }
        }

        $is_participant = (
            ($thread['participant_1_id'] == $current_user_id && $thread['participant_1_type'] == $current_user_type) ||
            ($thread['participant_2_id'] == $current_user_id && $thread['participant_2_type'] == $current_user_type)
        );

        if (!$is_participant) {
            wp_send_json_error(__('Access denied', 'rental-gates'));
        }

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['messages']} 
             WHERE thread_id = %d AND id > %d 
             ORDER BY created_at ASC 
             LIMIT 50",
            $thread_id,
            $after_id
        ), ARRAY_A);

        foreach ($messages as &$msg) {
            $sender = Rental_Gates_Message::get_participant_info($msg['sender_id'], $msg['sender_type']);
            $msg['sender_name'] = $sender['name'] ?? 'Unknown';
            $msg['content'] = $msg['message'];
            $msg['time_formatted'] = date_i18n('M j, g:i a', strtotime($msg['created_at']));
        }

        Rental_Gates_Message::mark_as_read($thread_id, $current_user_id, $current_user_type);

        wp_send_json_success(array('messages' => $messages));
    }

    public function handle_create_announcement()
    {
        if (!wp_verify_nonce($_POST['announcement_nonce'] ?? '', 'rental_gates_announcement')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_communications') && !Rental_Gates_Roles::is_owner_or_manager()) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(__('Organization not found', 'rental-gates'));
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $audience_type = sanitize_text_field($_POST['audience_type'] ?? 'all');
        $audience_ids = isset($_POST['audience_ids']) ? array_map('intval', $_POST['audience_ids']) : null;
        $channels = sanitize_text_field($_POST['channels'] ?? 'both');
        $delivery = sanitize_text_field($_POST['delivery'] ?? 'immediate');
        $scheduled_at = sanitize_text_field($_POST['scheduled_at'] ?? '');

        if (empty($title) || empty($message)) {
            wp_send_json_error(__('Title and message are required', 'rental-gates'));
        }

        $data = array(
            'organization_id' => $org_id,
            'title' => $title,
            'message' => $message,
            'audience_type' => $audience_type,
            'audience_ids' => $audience_ids,
            'channels' => $channels,
            'delivery' => $delivery,
            'scheduled_at' => $delivery === 'scheduled' && $scheduled_at ? $scheduled_at : null,
        );

        $result = Rental_Gates_Announcement::create($data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('announcement' => $result));
    }

    public function handle_send_announcement()
    {
        if (!wp_verify_nonce($_POST['announcement_nonce'] ?? '', 'rental_gates_announcement')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_communications') && !Rental_Gates_Roles::is_owner_or_manager()) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $announcement_id = intval($_POST['announcement_id'] ?? 0);

        if (!$announcement_id) {
            wp_send_json_error(__('Invalid announcement', 'rental-gates'));
        }

        $result = Rental_Gates_Announcement::send($announcement_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }
}
