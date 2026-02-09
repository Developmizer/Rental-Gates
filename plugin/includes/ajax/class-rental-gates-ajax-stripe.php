<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handlers for Stripe & Subscriptions
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: stripe_setup_intent, stripe_save_payment_method, stripe_delete_payment_method,
 *          stripe_set_default_method, update_subscription_payment_method,
 *          stripe_create_payment_intent, stripe_session_status, stripe_connect,
 *          stripe_webhook, subscription_create, subscription_cancel, subscription_resume,
 *          plan_change, subscription_cancel_immediately, get_billing_usage,
 *          create_subscription_intent, activate_subscription_payment,
 *          download_subscription_invoice, view_subscription_invoice
 */
class Rental_Gates_Ajax_Stripe {

    public function __construct() {
        // Stripe AJAX handlers
        add_action('wp_ajax_rental_gates_stripe_setup_intent', array($this, 'handle_stripe_setup_intent'));
        add_action('wp_ajax_rental_gates_stripe_save_payment_method', array($this, 'handle_stripe_save_payment_method'));
        add_action('wp_ajax_rental_gates_stripe_delete_payment_method', array($this, 'handle_stripe_delete_payment_method'));
        add_action('wp_ajax_rental_gates_stripe_set_default_method', array($this, 'handle_stripe_set_default_method'));
        add_action('wp_ajax_rental_gates_update_subscription_payment_method', array($this, 'handle_update_subscription_payment_method'));
        add_action('wp_ajax_rental_gates_stripe_create_payment_intent', array($this, 'handle_stripe_create_payment_intent'));
        add_action('wp_ajax_rental_gates_stripe_session_status', array($this, 'handle_stripe_session_status'));
        add_action('wp_ajax_nopriv_rental_gates_stripe_session_status', array($this, 'handle_stripe_session_status'));
        add_action('wp_ajax_rental_gates_stripe_connect', array($this, 'handle_stripe_connect'));
        add_action('wp_ajax_nopriv_rental_gates_stripe_webhook', array($this, 'handle_stripe_webhook'));
        add_action('wp_ajax_rental_gates_stripe_webhook', array($this, 'handle_stripe_webhook'));

        // Subscription handlers
        add_action('wp_ajax_rental_gates_subscribe', array($this, 'handle_subscription_create'));
        add_action('wp_ajax_rental_gates_cancel_subscription', array($this, 'handle_subscription_cancel'));
        add_action('wp_ajax_rental_gates_resume_subscription', array($this, 'handle_subscription_resume'));
        add_action('wp_ajax_rental_gates_cancel_subscription_immediately', array($this, 'handle_subscription_cancel_immediately'));
        add_action('wp_ajax_rental_gates_change_plan', array($this, 'handle_plan_change'));
        add_action('wp_ajax_rental_gates_get_billing_usage', array($this, 'handle_get_billing_usage'));

        // Subscription checkout handlers (for registration flow)
        add_action('wp_ajax_rental_gates_create_subscription_intent', array($this, 'handle_create_subscription_intent'));
        add_action('wp_ajax_rental_gates_activate_subscription', array($this, 'handle_activate_subscription_payment'));

        // Subscription invoice handlers
        add_action('wp_ajax_rental_gates_download_subscription_invoice', array($this, 'handle_download_subscription_invoice'));
        add_action('wp_ajax_rental_gates_view_subscription_invoice', array($this, 'handle_view_subscription_invoice'));
        add_action('wp_ajax_nopriv_rental_gates_view_subscription_invoice', array($this, 'handle_view_subscription_invoice'));
    }

    private function get_org_id() {
        return Rental_Gates_Roles::get_organization_id();
    }

    /**
     * Handle Stripe SetupIntent creation for adding payment methods
     */
    public function handle_stripe_setup_intent()
    {
        check_ajax_referer('rental_gates_stripe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not authenticated', 'rental-gates')));
        }

        $user_id = get_current_user_id();

        $setup_intent = Rental_Gates_Stripe::create_setup_intent($user_id);

        if (is_wp_error($setup_intent)) {
            wp_send_json_error(array('message' => $setup_intent->get_error_message()));
        }

        wp_send_json_success(array(
            'client_secret' => $setup_intent['client_secret'],
        ));
    }

    /**
     * Handle saving a payment method after SetupIntent completes
     */
    public function handle_stripe_save_payment_method()
    {
        check_ajax_referer('rental_gates_stripe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not authenticated', 'rental-gates')));
        }

        $payment_method_id = sanitize_text_field($_POST['payment_method_id'] ?? '');

        if (empty($payment_method_id)) {
            wp_send_json_error(array('message' => __('Payment method ID required', 'rental-gates')));
        }

        $user_id = get_current_user_id();

        // Get payment method details from Stripe
        $pm = Rental_Gates_Stripe::api_request('payment_methods/' . $payment_method_id);

        if (is_wp_error($pm)) {
            wp_send_json_error(array('message' => $pm->get_error_message()));
        }

        // Save to database
        $method_id = Rental_Gates_Stripe::save_payment_method($user_id, $pm);

        if (!$method_id) {
            wp_send_json_error(array('message' => __('Failed to save payment method', 'rental-gates')));
        }

        wp_send_json_success(array(
            'message' => __('Payment method added successfully', 'rental-gates'),
            'method_id' => $method_id,
        ));
    }

    /**
     * Handle deleting a payment method
     */
    public function handle_stripe_delete_payment_method()
    {
        check_ajax_referer('rental_gates_stripe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not authenticated', 'rental-gates')));
        }

        $method_id = intval($_POST['method_id'] ?? 0);

        if (!$method_id) {
            wp_send_json_error(array('message' => __('Invalid method ID', 'rental-gates')));
        }

        $result = Rental_Gates_Stripe::delete_payment_method(get_current_user_id(), $method_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Payment method deleted', 'rental-gates')));
    }

    /**
     * Handle setting default payment method
     */
    public function handle_stripe_set_default_method()
    {
        check_ajax_referer('rental_gates_stripe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not authenticated', 'rental-gates')));
        }

        $method_id = intval($_POST['method_id'] ?? 0);

        if (!$method_id) {
            wp_send_json_error(array('message' => __('Invalid method ID', 'rental-gates')));
        }

        Rental_Gates_Stripe::set_default_payment_method(get_current_user_id(), $method_id);

        wp_send_json_success(array('message' => __('Default payment method updated', 'rental-gates')));
    }

    /**
     * Handle updating subscription payment method
     */
    public function handle_update_subscription_payment_method()
    {
        check_ajax_referer('rental_gates_stripe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not authenticated', 'rental-gates')));
        }

        $payment_method_id = sanitize_text_field($_POST['payment_method_id'] ?? '');

        if (empty($payment_method_id)) {
            wp_send_json_error(array('message' => __('Payment method ID required', 'rental-gates')));
        }

        $org_id = rg_feature_gate()->get_user_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        // Check permissions (Owner only)
        if (!rg_feature_gate()->check_role(array('owner'))) {
            wp_send_json_error(array('message' => __('Only the organization owner can update the subscription payment method', 'rental-gates')));
        }

        // Get current subscription
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['subscriptions']}
             WHERE organization_id = %d
             AND (status IN ('active', 'trialing', 'past_due', 'unpaid', 'incomplete')
                  OR cancel_at_period_end = 1)
             ORDER BY created_at DESC LIMIT 1",
            $org_id
        ));

        if (!$subscription || empty($subscription->stripe_subscription_id)) {
            wp_send_json_error(array('message' => __('No active subscription found', 'rental-gates')));
        }

        // Get customer ID
        $customer_id = Rental_Gates_Billing::get_or_create_customer($org_id);
        if (is_wp_error($customer_id)) {
            wp_send_json_error(array('message' => $customer_id->get_error_message()));
        }

        // Attach payment method to customer if not already attached
        $attach_result = Rental_Gates_Stripe::attach_payment_method($payment_method_id, $customer_id);
        if (is_wp_error($attach_result) && strpos($attach_result->get_error_message(), 'already been attached') === false) {
            wp_send_json_error(array('message' => $attach_result->get_error_message()));
        }

        // Set as default payment method for customer
        Rental_Gates_Stripe::set_customer_invoice_payment_method($customer_id, $payment_method_id);

        // Update subscription's default payment method
        $update_result = Rental_Gates_Stripe::api_request(
            "subscriptions/{$subscription->stripe_subscription_id}",
            'POST',
            array(
                'default_payment_method' => $payment_method_id
            )
        );

        if (is_wp_error($update_result)) {
            wp_send_json_error(array('message' => $update_result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Subscription payment method updated successfully', 'rental-gates')
        ));
    }

    /**
     * Handle creating Checkout Session for rent payment (Embedded or Hosted)
     */
    public function handle_stripe_create_payment_intent()
    {
        check_ajax_referer('rental_gates_stripe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not authenticated', 'rental-gates')));
        }

        $payment_id = intval($_POST['payment_id'] ?? 0);
        $ui_mode = sanitize_text_field($_POST['ui_mode'] ?? 'embedded');

        if (!$payment_id) {
            wp_send_json_error(array('message' => __('Payment ID required', 'rental-gates')));
        }

        // Verify user has access to this payment
        $payment = Rental_Gates_Payment::get_with_details($payment_id);
        if (!$payment) {
            wp_send_json_error(array('message' => __('Payment not found', 'rental-gates')));
        }

        // Check user is the tenant or org member
        $user_id = get_current_user_id();
        $has_access = false;

        if ($payment['tenant_id']) {
            $tenant = Rental_Gates_Tenant::get($payment['tenant_id']);
            if ($tenant && $tenant['user_id'] == $user_id) {
                $has_access = true;
            }
        }

        if (!$has_access) {
            $org_id = Rental_Gates_Roles::get_organization_id();
            if ($org_id && $payment['organization_id'] == $org_id) {
                $has_access = true;
            }
        }

        if (!$has_access) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        // Create Checkout Session (embedded or hosted)
        $session = Rental_Gates_Stripe::create_checkout_session($payment_id, $ui_mode);

        if (is_wp_error($session)) {
            wp_send_json_error(array('message' => $session->get_error_message()));
        }

        $response = array(
            'session_id' => $session['id'],
        );

        // For embedded mode, return client_secret
        if ($ui_mode === 'embedded') {
            $response['client_secret'] = $session['client_secret'];
        } else {
            // For hosted mode, return redirect URL
            $response['redirect_url'] = $session['url'];
        }

        wp_send_json_success($response);
    }

    /**
     * Get Checkout Session status (for return page)
     */
    public function handle_stripe_session_status()
    {
        $session_id = sanitize_text_field($_GET['session_id'] ?? '');

        if (empty($session_id)) {
            wp_send_json_error(array('message' => __('Session ID required', 'rental-gates')));
        }

        $session = Rental_Gates_Stripe::get_checkout_session($session_id);

        if (is_wp_error($session)) {
            wp_send_json_error(array('message' => $session->get_error_message()));
        }

        wp_send_json_success(array(
            'status' => $session['status'],
            'payment_status' => $session['payment_status'],
            'customer_email' => $session['customer_details']['email'] ?? '',
            'amount_total' => $session['amount_total'] / 100,
            'payment_id' => $session['metadata']['payment_id'] ?? null,
        ));
    }

    /**
     * Handle Stripe Connect onboarding
     */
    public function handle_stripe_connect()
    {
        check_ajax_referer('rental_gates_stripe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not logged in', 'rental-gates')));
        }

        // Check for settings OR connect stripe capability
        if (!current_user_can('rg_manage_settings') && !current_user_can('rg_connect_stripe')) {
            wp_send_json_error(array('message' => __('Access denied. You need settings permission.', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found. Please ensure you are a member of an organization.', 'rental-gates')));
        }

        $action = sanitize_text_field($_POST['stripe_action'] ?? 'create');

        switch ($action) {
            case 'create':
                // Create Connect account
                $account = Rental_Gates_Stripe::create_connect_account($org_id);
                if (is_wp_error($account)) {
                    // If account exists, get onboarding link
                    if ($account->get_error_code() === 'exists') {
                        $link = Rental_Gates_Stripe::create_account_link($org_id);
                        if (!is_wp_error($link)) {
                            wp_send_json_success(array('redirect_url' => $link['url']));
                        }
                    }
                    wp_send_json_error(array('message' => $account->get_error_message()));
                }

                // Get onboarding link
                $link = Rental_Gates_Stripe::create_account_link($org_id);
                if (is_wp_error($link)) {
                    wp_send_json_error(array('message' => $link->get_error_message()));
                }

                wp_send_json_success(array('redirect_url' => $link['url']));
                break;

            case 'refresh':
                // Refresh account status
                $result = Rental_Gates_Stripe::refresh_connected_account($org_id);
                if (is_wp_error($result)) {
                    wp_send_json_error(array('message' => $result->get_error_message()));
                }
                wp_send_json_success(array('message' => __('Account status refreshed', 'rental-gates')));
                break;

            case 'dashboard':
                // Get Stripe dashboard link
                $link = Rental_Gates_Stripe::create_login_link($org_id);
                if (is_wp_error($link)) {
                    wp_send_json_error(array('message' => $link->get_error_message()));
                }
                wp_send_json_success(array('redirect_url' => $link['url']));
                break;

            case 'onboarding':
                // Resume onboarding
                $link = Rental_Gates_Stripe::create_account_link($org_id);
                if (is_wp_error($link)) {
                    wp_send_json_error(array('message' => $link->get_error_message()));
                }
                wp_send_json_success(array('redirect_url' => $link['url']));
                break;

            default:
                wp_send_json_error(array('message' => __('Invalid action', 'rental-gates')));
        }
    }

    /**
     * Handle Stripe webhooks
     */
    public function handle_stripe_webhook()
    {
        // Get raw payload
        $payload = file_get_contents('php://input');
        $signature = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';

        if (empty($payload) || empty($signature)) {
            status_header(400);
            exit('Invalid request');
        }

        $result = Rental_Gates_Stripe::handle_webhook($payload, $signature);

        if (is_wp_error($result)) {
            Rental_Gates_Logger::error('stripe', 'Webhook handling failed', array('error' => $result->get_error_message()));
            status_header(400);
            exit($result->get_error_message());
        }

        status_header(200);
        exit('OK');
    }

    /**
     * Handle subscription creation
     */
    public function handle_subscription_create()
    {
        check_ajax_referer('rental_gates_subscribe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in to subscribe', 'rental-gates')));
        }

        $plan_id = sanitize_key($_POST['plan_id'] ?? '');
        $payment_method_id = sanitize_text_field($_POST['payment_method_id'] ?? '');
        $billing_cycle = sanitize_text_field($_POST['billing_cycle'] ?? 'monthly');

        if (empty($plan_id)) {
            wp_send_json_error(array('message' => __('Please select a plan', 'rental-gates')));
        }

        // Get user's organization
        $org_id = rg_feature_gate()->get_user_org_id();

        if (!$org_id) {
            wp_send_json_error(array('message' => __('No organization found', 'rental-gates')));
        }

        // Use Rental_Gates_Billing class
        $result = Rental_Gates_Billing::subscribe($org_id, $plan_id, $payment_method_id, $billing_cycle);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Only if it's a paid subscription with result
        if (is_array($result) && isset($result['status'])) {
            // Handle 3D Secure / SCA
            if ($result['status'] === 'incomplete') {
                $payment_intent = $result['latest_invoice']['payment_intent'] ?? null;

                if ($payment_intent && in_array($payment_intent['status'], array('requires_action', 'requires_payment_method'))) {
                    wp_send_json_success(array(
                        'requires_action' => true,
                        'client_secret' => $payment_intent['client_secret'],
                        'subscription_id' => $result['id'],
                    ));
                }
            }
        }

        // Fire action for other plugins/integrations
        do_action('rental_gates_subscription_created', $org_id, $plan_id);

        wp_send_json_success(array(
            'message' => __('Subscription created successfully', 'rental-gates'),
            'redirect' => home_url('/rental-gates/dashboard/billing?subscribed=1'),
        ));
    }

    /**
     * Handle subscription cancellation
     */
    public function handle_subscription_cancel()
    {
        check_ajax_referer('rental_gates_cancel', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
        }

        $org_id = rg_feature_gate()->get_user_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('No organization found', 'rental-gates')));
        }

        // Use Billing Class
        $result = Rental_Gates_Billing::cancel_subscription($org_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Get end date for display
        global $wpdb;
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rg_subscriptions WHERE organization_id = %d AND status = 'active' LIMIT 1",
            $org_id
        ));

        $end_date = !empty($subscription->current_period_end)
            ? date_i18n(get_option('date_format'), strtotime($subscription->current_period_end))
            : __('the end of your billing period', 'rental-gates');

        wp_send_json_success(array(
            'message' => sprintf(
                __('Subscription will cancel on %s. You can resume anytime before then.', 'rental-gates'),
                $end_date
            ),
            'redirect' => home_url('/rental-gates/dashboard/billing?cancelled=1'),
            'cancel_date' => $end_date,
        ));
    }

    /**
     * Handle subscription resume (un-cancel)
     */
    public function handle_subscription_resume()
    {
        check_ajax_referer('rental_gates_resume', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
        }

        $org_id = rg_feature_gate()->get_user_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('No organization found', 'rental-gates')));
        }

        global $wpdb;

        // Get subscription that's set to cancel
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rg_subscriptions
             WHERE organization_id = %d AND status = 'active' AND cancel_at_period_end = 1
             LIMIT 1",
            $org_id
        ));

        if (!$subscription) {
            wp_send_json_error(array('message' => __('No subscription pending cancellation found', 'rental-gates')));
        }

        // Resume in Stripe
        if (!empty($subscription->stripe_subscription_id) && Rental_Gates_Stripe::is_configured()) {
            $resume_result = Rental_Gates_Stripe::resume_subscription($subscription->stripe_subscription_id);
            if (is_wp_error($resume_result)) {
                Rental_Gates_Logger::error('stripe', 'Resume subscription failed', array('stripe_subscription_id' => $subscription->stripe_subscription_id, 'error' => $resume_result->get_error_message()));
                wp_send_json_error(array('message' => $resume_result->get_error_message()));
            }
        }

        // Update local subscription
        $wpdb->update(
            $wpdb->prefix . 'rg_subscriptions',
            array(
                'cancel_at_period_end' => 0,
                'cancelled_at' => null,
            ),
            array('id' => $subscription->id),
            array('%d', '%s'),
            array('%d')
        );

        wp_send_json_success(array(
            'message' => __('Subscription resumed! Your plan will continue as normal.', 'rental-gates'),
            'redirect' => home_url('/rental-gates/dashboard/billing?resumed=1'),
        ));
    }

    /**
     * Handle plan change (upgrade/downgrade between paid plans)
     */
    public function handle_plan_change()
    {
        check_ajax_referer('rental_gates_change_plan', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
        }

        $new_plan_id = sanitize_key($_POST['plan_id'] ?? '');
        $payment_method_id = sanitize_text_field($_POST['payment_method_id'] ?? '');

        if (empty($new_plan_id)) {
            wp_send_json_error(array('message' => __('Please select a plan', 'rental-gates')));
        }

        $org_id = rg_feature_gate()->get_user_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('No organization found', 'rental-gates')));
        }

        // Use Billing Class
        $result = Rental_Gates_Billing::change_plan($org_id, $new_plan_id, $payment_method_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Handle scheduled downgrade response
        if (is_array($result) && isset($result['action']) && $result['action'] === 'downgrade_scheduled') {
            global $wpdb;
            $subscription = $result['subscription'];

            // Update subscription to track downgrade
            $wpdb->update(
                $wpdb->prefix . 'rg_subscriptions',
                array('downgrade_to_plan' => $new_plan_id),
                array('id' => $subscription->id),
                array('%s'),
                array('%d')
            );

            $end_date = !empty($subscription->current_period_end)
                ? date_i18n(get_option('date_format'), strtotime($subscription->current_period_end))
                : __('the end of your billing period', 'rental-gates');

            wp_send_json_success(array(
                'message' => sprintf(
                    __('Your plan will change to Free on %s. You\'ll keep your current features until then.', 'rental-gates'),
                    $end_date
                ),
                'redirect' => home_url('/rental-gates/dashboard/billing?downgrade_scheduled=1'),
                'scheduled_date' => $end_date,
                'keep_access' => true,
            ));
        }

        // Handle 3D Secure / SCA
        if (is_array($result) && isset($result['latest_invoice']['payment_intent'])) {
            $pi = $result['latest_invoice']['payment_intent'];
            if ($pi['status'] === 'requires_action') {
                wp_send_json_success(array(
                    'requires_action' => true,
                    'client_secret' => $pi['client_secret'],
                    'subscription_id' => $result['id'],
                ));
            }
        }

        // Success
        wp_send_json_success(array(
            'message' => __('Plan changed successfully!', 'rental-gates'),
            'redirect' => home_url('/rental-gates/dashboard/billing?changed=1'),
        ));
    }

    /**
     * Handle cancel subscription immediately (before period end)
     */
    public function handle_subscription_cancel_immediately()
    {
        check_ajax_referer('rental_gates_cancel_immediately', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
        }

        $org_id = rg_feature_gate()->get_user_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('No organization found', 'rental-gates')));
        }

        // Use Billing Class
        $result = Rental_Gates_Billing::cancel_subscription_immediately($org_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Subscription cancelled and downgraded to free plan.', 'rental-gates'),
            'redirect' => home_url('/rental-gates/dashboard/billing?cancelled=1'),
        ));
    }

    /**
     * Get billing usage data via AJAX
     */
    public function handle_get_billing_usage()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
        }

        $feature_gate = rg_feature_gate();
        $plan = $feature_gate->get_org_plan();
        $usage = $feature_gate->get_all_usage();
        $modules = $feature_gate->get_all_modules();

        wp_send_json_success(array(
            'plan' => array(
                'id' => $plan['id'] ?? 'free',
                'name' => $plan['name'] ?? 'Free',
                'price_monthly' => $plan['price_monthly'] ?? 0,
                'is_free' => !empty($plan['is_free']),
            ),
            'usage' => $usage,
            'modules' => $modules,
        ));
    }

    /**
     * Create Stripe Payment Intent for subscription checkout
     */
    public function handle_create_subscription_intent()
    {
        check_ajax_referer('rental_gates_subscription_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
        }

        $subscription_id = intval($_POST['subscription_id'] ?? 0);
        $plan_slug = sanitize_text_field($_POST['plan'] ?? '');
        $billing_cycle = sanitize_text_field($_POST['billing'] ?? 'monthly');

        if (!$subscription_id || !$plan_slug) {
            wp_send_json_error(array('message' => __('Invalid subscription data', 'rental-gates')));
        }

        // Get user's organization
        $user_id = get_current_user_id();
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $org_member = $wpdb->get_row($wpdb->prepare(
            "SELECT organization_id FROM {$tables['organization_members']} WHERE user_id = %d AND is_primary = 1",
            $user_id
        ));

        if (!$org_member) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        // Verify subscription belongs to this organization
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['subscriptions']} WHERE id = %d AND organization_id = %d",
            $subscription_id,
            $org_member->organization_id
        ), ARRAY_A);

        if (!$subscription) {
            wp_send_json_error(array('message' => __('Subscription not found', 'rental-gates')));
        }

        // Check Stripe configuration
        if (!Rental_Gates_Stripe::is_configured()) {
            wp_send_json_error(array('message' => __('Payment processing not configured. Please contact support.', 'rental-gates')));
        }

        // Plan prices
        $plan_prices = array(
            'starter' => array('monthly' => 19, 'yearly' => 180),
            'professional' => array('monthly' => 49, 'yearly' => 468),
            'enterprise' => array('monthly' => 149, 'yearly' => 1428),
        );

        if (!isset($plan_prices[$plan_slug])) {
            wp_send_json_error(array('message' => __('Invalid plan selected', 'rental-gates')));
        }

        $amount = $billing_cycle === 'yearly'
            ? $plan_prices[$plan_slug]['yearly']
            : $plan_prices[$plan_slug]['monthly'];

        $amount_cents = $amount * 100;

        // Get or create Stripe customer
        $customer_id = Rental_Gates_Stripe::get_or_create_customer($user_id);
        if (is_wp_error($customer_id)) {
            wp_send_json_error(array('message' => $customer_id->get_error_message()));
        }

        // Create payment intent
        $intent_data = array(
            'amount' => $amount_cents,
            'currency' => 'usd',
            'customer' => $customer_id,
            'metadata' => array(
                'subscription_id' => $subscription_id,
                'organization_id' => $org_member->organization_id,
                'plan_slug' => $plan_slug,
                'billing_cycle' => $billing_cycle,
                'type' => 'subscription_payment',
            ),
            'automatic_payment_methods' => array(
                'enabled' => 'true',
            ),
        );

        $payment_intent = Rental_Gates_Stripe::api_request('payment_intents', 'POST', $intent_data);

        if (is_wp_error($payment_intent)) {
            wp_send_json_error(array('message' => $payment_intent->get_error_message()));
        }

        // Store payment intent ID in subscription
        $wpdb->update(
            $tables['subscriptions'],
            array(
                'meta_data' => json_encode(array_merge(
                    json_decode($subscription['meta_data'] ?? '{}', true) ?: array(),
                    array('payment_intent_id' => $payment_intent['id'])
                )),
            ),
            array('id' => $subscription_id)
        );

        wp_send_json_success(array(
            'client_secret' => $payment_intent['client_secret'],
            'payment_intent_id' => $payment_intent['id'],
        ));
    }

    /**
     * Activate subscription after successful payment
     */
    public function handle_activate_subscription_payment()
    {
        check_ajax_referer('rental_gates_subscription_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
        }

        $subscription_id = intval($_POST['subscription_id'] ?? 0);
        $payment_intent_id = sanitize_text_field($_POST['payment_intent_id'] ?? '');

        if (!$subscription_id || !$payment_intent_id) {
            wp_send_json_error(array('message' => __('Invalid payment data', 'rental-gates')));
        }

        // Get user's organization
        $user_id = get_current_user_id();
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $org_member = $wpdb->get_row($wpdb->prepare(
            "SELECT organization_id FROM {$tables['organization_members']} WHERE user_id = %d AND is_primary = 1",
            $user_id
        ));

        if (!$org_member) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        // Verify subscription belongs to this organization
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['subscriptions']} WHERE id = %d AND organization_id = %d",
            $subscription_id,
            $org_member->organization_id
        ), ARRAY_A);

        if (!$subscription) {
            wp_send_json_error(array('message' => __('Subscription not found', 'rental-gates')));
        }

        // Verify payment intent status with Stripe
        if (Rental_Gates_Stripe::is_configured()) {
            $payment_intent = Rental_Gates_Stripe::get_payment_intent($payment_intent_id);

            if (is_wp_error($payment_intent)) {
                wp_send_json_error(array('message' => __('Could not verify payment', 'rental-gates')));
            }

            if ($payment_intent['status'] !== 'succeeded') {
                wp_send_json_error(array('message' => __('Payment has not been completed', 'rental-gates')));
            }
        }

        // Calculate subscription period
        $now = current_time('mysql');
        $billing_cycle = $subscription['billing_cycle'] ?? 'monthly';

        if ($billing_cycle === 'yearly') {
            $period_end = date('Y-m-d H:i:s', strtotime('+1 year'));
        } else {
            $period_end = date('Y-m-d H:i:s', strtotime('+1 month'));
        }

        // Activate subscription
        $wpdb->update(
            $tables['subscriptions'],
            array(
                'status' => 'active',
                'current_period_start' => $now,
                'current_period_end' => $period_end,
                'stripe_customer_id' => $payment_intent['customer'] ?? null,
                'meta_data' => json_encode(array_merge(
                    json_decode($subscription['meta_data'] ?? '{}', true) ?: array(),
                    array(
                        'payment_intent_id' => $payment_intent_id,
                        'activated_at' => $now,
                    )
                )),
            ),
            array('id' => $subscription_id)
        );

        // Update organization plan
        $wpdb->update(
            $tables['organizations'],
            array('plan_id' => $subscription['plan_slug']),
            array('id' => $org_member->organization_id)
        );

        // Create invoice record
        $invoice_number = 'INV-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $wpdb->insert($tables['invoices'], array(
            'organization_id' => $org_member->organization_id,
            'subscription_id' => $subscription_id,
            'invoice_number' => $invoice_number,
            'amount' => $subscription['amount'],
            'tax' => 0,
            'total' => $subscription['amount'],
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => $now,
            'created_at' => $now,
        ));

        // Send confirmation email
        if (class_exists('Rental_Gates_Email')) {
            $user = get_user_by('ID', $user_id);
            Rental_Gates_Email::send_subscription_activated($user, $subscription);
        }

        wp_send_json_success(array(
            'message' => __('Subscription activated successfully!', 'rental-gates'),
            'redirect' => home_url('/rental-gates/dashboard'),
        ));
    }

    /**
     * Handle subscription invoice download
     */
    public function handle_download_subscription_invoice()
    {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'download_subscription_invoice')) {
            wp_die(__('Security check failed', 'rental-gates'));
        }

        $invoice_id = intval($_GET['id'] ?? 0);
        $format = sanitize_text_field($_GET['format'] ?? 'html');

        if (!$invoice_id) {
            wp_die(__('Invalid invoice ID', 'rental-gates'));
        }

        $invoice = Rental_Gates_Subscription_Invoice::get($invoice_id);
        if (!$invoice) {
            wp_die(__('Invoice not found', 'rental-gates'));
        }

        // Check access
        $user_org_id = rg_feature_gate()->get_user_org_id();
        if (!$user_org_id || $user_org_id != $invoice['organization_id']) {
            if (!current_user_can('manage_options')) {
                wp_die(__('Access denied', 'rental-gates'));
            }
        }

        // Generate HTML
        $html = Rental_Gates_Subscription_Invoice::generate_html($invoice);
        $filename = 'Invoice-' . $invoice['invoice_number'];

        if ($format === 'html' || $format === 'print') {
            // Output HTML directly
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            exit;
        }

        if ($format === 'pdf') {
            // Try to generate PDF using wkhtmltopdf or similar
            $temp_html = tempnam(sys_get_temp_dir(), 'invoice_') . '.html';
            $temp_pdf = tempnam(sys_get_temp_dir(), 'invoice_') . '.pdf';

            file_put_contents($temp_html, $html);

            // Try wkhtmltopdf
            $wkhtmltopdf = '/usr/bin/wkhtmltopdf';
            if (!file_exists($wkhtmltopdf)) {
                $wkhtmltopdf = '/usr/local/bin/wkhtmltopdf';
            }

            if (file_exists($wkhtmltopdf)) {
                exec($wkhtmltopdf . ' --quiet --page-size A4 --margin-top 10mm --margin-bottom 10mm --margin-left 10mm --margin-right 10mm ' . escapeshellarg($temp_html) . ' ' . escapeshellarg($temp_pdf) . ' 2>&1', $output, $return_var);

                if ($return_var === 0 && file_exists($temp_pdf)) {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
                    header('Content-Length: ' . filesize($temp_pdf));
                    readfile($temp_pdf);
                    unlink($temp_html);
                    unlink($temp_pdf);
                    exit;
                }
            }

            // Fallback: output HTML for browser printing
            header('Content-Type: text/html; charset=utf-8');
            echo '<script>window.print(); setTimeout(function() { window.close(); }, 1000);</script>';
            echo $html;
            unlink($temp_html);
            exit;
        }

        if ($format === 'png') {
            // Try to generate PNG using wkhtmltoimage
            $temp_html = tempnam(sys_get_temp_dir(), 'invoice_') . '.html';
            $temp_png = tempnam(sys_get_temp_dir(), 'invoice_') . '.png';

            file_put_contents($temp_html, $html);

            $wkhtmltoimage = '/usr/bin/wkhtmltoimage';
            if (!file_exists($wkhtmltoimage)) {
                $wkhtmltoimage = '/usr/local/bin/wkhtmltoimage';
            }

            if (file_exists($wkhtmltoimage)) {
                exec($wkhtmltoimage . ' --quiet --width 800 --quality 100 ' . escapeshellarg($temp_html) . ' ' . escapeshellarg($temp_png) . ' 2>&1', $output, $return_var);

                if ($return_var === 0 && file_exists($temp_png)) {
                    header('Content-Type: image/png');
                    header('Content-Disposition: attachment; filename="' . $filename . '.png"');
                    header('Content-Length: ' . filesize($temp_png));
                    readfile($temp_png);
                    unlink($temp_html);
                    unlink($temp_png);
                    exit;
                }
            }

            // Fallback: tell user to use browser screenshot
            wp_die(__('PNG generation not available. Please use your browser\'s screenshot feature or print to PDF.', 'rental-gates'));
        }

        // Default: output HTML
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    /**
     * Handle subscription invoice view
     */
    public function handle_view_subscription_invoice()
    {
        $invoice_id = intval($_GET['id'] ?? 0);
        $token = sanitize_text_field($_GET['token'] ?? '');

        if (!$invoice_id) {
            wp_die(__('Invalid invoice ID', 'rental-gates'));
        }

        $invoice = Rental_Gates_Subscription_Invoice::get($invoice_id);
        if (!$invoice) {
            wp_die(__('Invoice not found', 'rental-gates'));
        }

        // Check access - either logged in user with matching org, or valid token
        $has_access = false;

        if (is_user_logged_in()) {
            $user_org_id = rg_feature_gate()->get_user_org_id();
            if ($user_org_id && $user_org_id == $invoice['organization_id']) {
                $has_access = true;
            }
            if (current_user_can('manage_options')) {
                $has_access = true;
            }
        }

        // Check token for public access
        if (!$has_access && $token) {
            $expected_token = wp_hash($invoice['id'] . $invoice['invoice_number'] . $invoice['created_at']);
            if (hash_equals($expected_token, $token)) {
                $has_access = true;
            }
        }

        if (!$has_access) {
            wp_die(__('Access denied', 'rental-gates'));
        }

        // Generate and output HTML
        $html = Rental_Gates_Subscription_Invoice::generate_html($invoice);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
}
