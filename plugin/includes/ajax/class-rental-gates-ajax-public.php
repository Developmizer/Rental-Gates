<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handlers for Public (no-auth) Actions
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: contact_form, public_inquiry, forgot_password
 */
class Rental_Gates_Ajax_Public {

    public function __construct() {
        add_action('wp_ajax_nopriv_rental_gates_contact_form', array($this, 'handle_contact_form'));
        add_action('wp_ajax_rental_gates_contact_form', array($this, 'handle_contact_form'));
        add_action('wp_ajax_nopriv_rental_gates_public_inquiry', array($this, 'handle_public_inquiry'));
        add_action('wp_ajax_rental_gates_public_inquiry', array($this, 'handle_public_inquiry'));
        add_action('wp_ajax_nopriv_rental_gates_forgot_password', array($this, 'handle_forgot_password'));
        add_action('wp_ajax_rental_gates_forgot_password', array($this, 'handle_forgot_password'));
    }

    public function handle_contact_form()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_contact')) {
            wp_send_json_error(__('Security check failed. Please refresh and try again.', 'rental-gates'));
        }

        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $company = sanitize_text_field($_POST['company'] ?? '');
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            wp_send_json_error(__('Please fill in all required fields.', 'rental-gates'));
        }

        if (!is_email($email)) {
            wp_send_json_error(__('Please enter a valid email address.', 'rental-gates'));
        }

        $rate_key = 'rg_contact_' . md5(Rental_Gates_Security::get_client_ip());
        $rate_count = get_transient($rate_key);
        if ($rate_count && $rate_count >= 5) {
            wp_send_json_error(__('Too many requests. Please try again later.', 'rental-gates'));
        }
        set_transient($rate_key, ($rate_count ? $rate_count + 1 : 1), HOUR_IN_SECONDS);

        $subject_labels = array(
            'sales' => __('Sales Inquiry', 'rental-gates'),
            'support' => __('Technical Support', 'rental-gates'),
            'billing' => __('Billing Question', 'rental-gates'),
            'partnership' => __('Partnership Opportunity', 'rental-gates'),
            'feedback' => __('Product Feedback', 'rental-gates'),
            'other' => __('Other', 'rental-gates'),
        );
        $subject_label = $subject_labels[$subject] ?? $subject;

        $platform_name = get_option('rental_gates_platform_name', 'Rental Gates');
        $admin_email = get_option('rental_gates_support_email', get_option('admin_email'));

        $email_subject = sprintf('[%s] %s: %s', $platform_name, $subject_label, $name);

        $email_body = sprintf(
            "New contact form submission\n\n" .
            "Name: %s\nEmail: %s\nPhone: %s\nCompany: %s\nSubject: %s\n\nMessage:\n%s\n\n---\nIP Address: %s\nSubmitted: %s",
            $name, $email, $phone ?: 'Not provided', $company ?: 'Not provided',
            $subject_label, $message, Rental_Gates_Security::get_client_ip(), current_time('mysql')
        );

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $name . ' <' . $email . '>',
        );

        $sent = wp_mail($admin_email, $email_subject, $email_body, $headers);

        if ($sent) {
            wp_send_json_success(__('Thank you! Your message has been sent successfully.', 'rental-gates'));
        } else {
            wp_send_json_error(__('Failed to send message. Please try again or email us directly.', 'rental-gates'));
        }
    }

    public function handle_public_inquiry()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_public')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');

        $org_id = intval($_POST['organization_id'] ?? 0);
        $building_id = intval($_POST['building_id'] ?? 0);
        $unit_id = intval($_POST['unit_id'] ?? 0);
        $source = sanitize_text_field($_POST['source'] ?? 'profile');
        $source_id = intval($_POST['source_id'] ?? 0);

        if (empty($first_name) || empty($email) || empty($org_id)) {
            wp_send_json_error(array('message' => __('Please fill in all required fields', 'rental-gates')));
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address', 'rental-gates')));
        }

        $organization = Rental_Gates_Organization::get($org_id);
        if (!$organization) {
            wp_send_json_error(array('message' => __('Invalid organization', 'rental-gates')));
        }

        $lead_data = array(
            'organization_id' => $org_id,
            'name' => trim($first_name . ' ' . $last_name),
            'email' => $email,
            'phone' => $phone,
            'source' => in_array($source, array('qr_building', 'qr_unit', 'map', 'profile')) ? $source : 'profile',
            'source_id' => $source_id ?: null,
            'notes' => $message,
            'meta_data' => array(
                'inquiry' => true,
                'inquiry_time' => current_time('mysql'),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'referer' => esc_url_raw($_SERVER['HTTP_REFERER'] ?? ''),
            ),
        );

        if ($building_id) {
            $lead_data['building_id'] = $building_id;
        }
        if ($unit_id) {
            $lead_data['unit_id'] = $unit_id;
        }

        $lead_id = Rental_Gates_Lead::create($lead_data);

        if (is_wp_error($lead_id)) {
            if ($lead_id->get_error_code() === 'duplicate_lead') {
                wp_send_json_success(array(
                    'message' => __('Thank you! We\'ll be in touch soon.', 'rental-gates'),
                    'existing' => true,
                ));
            }
            wp_send_json_error(array('message' => $lead_id->get_error_message()));
        }

        $this->send_inquiry_notification($org_id, $lead_data, $building_id, $unit_id);

        wp_send_json_success(array(
            'message' => __('Thank you for your inquiry! We\'ll be in touch soon.', 'rental-gates'),
            'lead_id' => $lead_id,
        ));
    }

    private function send_inquiry_notification($org_id, $lead_data, $building_id = 0, $unit_id = 0)
    {
        $organization = Rental_Gates_Organization::get($org_id);
        if (!$organization || empty($organization['contact_email'])) {
            return;
        }

        $property_name = '';
        if ($unit_id) {
            $unit = Rental_Gates_Unit::get($unit_id);
            $building = $unit ? Rental_Gates_Building::get($unit['building_id']) : null;
            $property_name = $unit ? ($unit['name'] . ' at ' . ($building['name'] ?? '')) : '';
        } elseif ($building_id) {
            $building = Rental_Gates_Building::get($building_id);
            $property_name = $building ? $building['name'] : '';
        }

        $subject = sprintf(__('New Inquiry: %s', 'rental-gates'), $lead_data['name']);

        $message = sprintf(
            __("You have a new inquiry from your property listing.\n\nName: %s\nEmail: %s\nPhone: %s\nProperty: %s\nSource: %s\n\nMessage:\n%s\n\nView this lead in your dashboard:\n%s", 'rental-gates'),
            $lead_data['name'], $lead_data['email'],
            $lead_data['phone'] ?: 'Not provided',
            $property_name ?: 'General inquiry',
            ucfirst(str_replace('_', ' ', $lead_data['source'])),
            $lead_data['notes'] ?: 'No message provided',
            home_url('/rental-gates/dashboard/leads')
        );

        wp_mail($organization['contact_email'], $subject, $message);
    }

    public function handle_forgot_password()
    {
        $email = sanitize_email($_POST['email'] ?? '');

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'rental-gates')));
        }

        $user = get_user_by('email', $email);

        if ($user) {
            $key = get_password_reset_key($user);

            if (!is_wp_error($key)) {
                $reset_url = add_query_arg(array(
                    'key' => $key,
                    'login' => rawurlencode($user->user_login),
                ), home_url('/rental-gates/reset-password/'));

                Rental_Gates_Email::send($email, 'password_reset', array(
                    'user_name' => $user->display_name ?: $user->user_login,
                    'reset_url' => $reset_url,
                    'preheader' => __('Reset your password to regain access to your account.', 'rental-gates'),
                ));

                global $wpdb;
                $tables = Rental_Gates_Database::get_table_names();
                $wpdb->insert(
                    $tables['activity_log'],
                    array(
                        'user_id' => $user->ID,
                        'action' => 'password_reset_requested',
                        'entity_type' => 'user',
                        'entity_id' => $user->ID,
                        'ip_address' => Rental_Gates_Security::get_client_ip(),
                        'created_at' => current_time('mysql'),
                    )
                );
            }
        }

        wp_send_json_success(array(
            'message' => __('If an account exists with that email, you will receive a password reset link shortly.', 'rental-gates')
        ));
    }
}
