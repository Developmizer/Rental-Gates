<?php
/**
 * Rental Gates Stripe Integration
 * 
 * Handles all Stripe payment processing including:
 * - Embedded Checkout for rent collection (in-site experience)
 * - Hosted Checkout fallback (redirect to Stripe)
 * - Connect for property owner payouts
 * - Customer and Payment Method management
 * - Webhooks for async events
 */
if (!defined('ABSPATH'))
    exit;

class Rental_Gates_Stripe
{

    /**
     * Stripe mode (test/live)
     */
    private static $mode;

    /**
     * API version
     */
    const API_VERSION = '2023-10-16';

    /**
     * Platform fee percentage (e.g., 2.5%)
     */
    const PLATFORM_FEE_PERCENT = 2.5;

    /**
     * Initialize
     */
    public static function init()
    {
        if (self::$mode === null) {
            self::$mode = get_option('rental_gates_stripe_mode', 'test');
        }
    }

    /**
     * Get publishable key
     * Checks both option name formats for compatibility
     */
    public static function get_publishable_key()
    {
        self::init();
        // Check short format first (dashboard settings), then long format (WP admin)
        $key = get_option('rental_gates_stripe_' . self::$mode . '_pk', '');
        if (empty($key)) {
            $key = get_option('rental_gates_stripe_' . self::$mode . '_publishable_key', '');
        }
        return $key;
    }

    /**
     * Get secret key - decrypts from storage.
     * Supports both encrypted (new) and plaintext (legacy) formats.
     */
    public static function get_secret_key()
    {
        self::init();

        // Try encrypted key first (new format)
        $encrypted_key = get_option('rental_gates_stripe_' . self::$mode . '_sk_encrypted', '');
        if (!empty($encrypted_key)) {
            $key = Rental_Gates_Security::decrypt($encrypted_key);
            if ($key !== false && !empty($key)) {
                return $key;
            }
        }

        // Fallback to plaintext (legacy) - and migrate it
        $key = get_option('rental_gates_stripe_' . self::$mode . '_sk', '');
        if (empty($key)) {
            $key = get_option('rental_gates_stripe_' . self::$mode . '_secret_key', '');
        }

        // Auto-migrate plaintext key to encrypted storage
        if (!empty($key)) {
            self::store_secret_key($key);
        }

        return $key;
    }

    /**
     * Store a secret key encrypted.
     * Removes the plaintext version after encryption.
     */
    public static function store_secret_key($key)
    {
        self::init();

        $encrypted = Rental_Gates_Security::encrypt($key);
        if ($encrypted !== false) {
            update_option('rental_gates_stripe_' . self::$mode . '_sk_encrypted', $encrypted);
            // Remove plaintext versions
            delete_option('rental_gates_stripe_' . self::$mode . '_sk');
            delete_option('rental_gates_stripe_' . self::$mode . '_secret_key');
        }
    }

    /**
     * Store the webhook secret encrypted.
     */
    public static function store_webhook_secret($secret)
    {
        $encrypted = Rental_Gates_Security::encrypt($secret);
        if ($encrypted !== false) {
            update_option('rental_gates_stripe_webhook_secret_encrypted', $encrypted);
            delete_option('rental_gates_stripe_webhook_secret');
        }
    }

    /**
     * Check if Stripe is configured
     */
    public static function is_configured()
    {
        return !empty(self::get_publishable_key()) && !empty(self::get_secret_key());
    }

    /**
     * Get current mode
     */
    public static function get_mode()
    {
        self::init();
        return self::$mode;
    }

    /**
     * Make API request to Stripe
     */
    public static function api_request($endpoint, $method = 'GET', $data = array())
    {
        $secret_key = self::get_secret_key();

        if (empty($secret_key)) {
            return new WP_Error('not_configured', __('Stripe is not configured. Please add API keys in WordPress settings.', 'rental-gates'));
        }

        $url = 'https://api.stripe.com/v1/' . ltrim($endpoint, '/');

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Stripe-Version' => self::API_VERSION,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'timeout' => 30,
        );

        if (!empty($data) && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = self::build_nested_query($data);
        } elseif (!empty($data) && $method === 'GET') {
            $url = add_query_arg($data, $url);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            Rental_Gates_Logger::error('stripe', 'API request failed', array('error' => $response->get_error_message()));
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 400) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : __('Stripe API error', 'rental-gates');
            $error_code = isset($body['error']['code']) ? $body['error']['code'] : 'api_error';
            // Log error without sensitive endpoint data (strip query params)
            $safe_endpoint = explode('?', $endpoint, 2)[0];
            Rental_Gates_Logger::error('stripe', 'API error response', array('http_code' => $code, 'error' => $error_message, 'endpoint' => $safe_endpoint));
            return new WP_Error($error_code, $error_message, $body);
        }

        return $body;
    }

    /**
     * Build nested query string for Stripe API (handles arrays properly)
     */
    private static function build_nested_query($data, $prefix = '')
    {
        $result = array();

        foreach ($data as $key => $value) {
            $new_key = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value)) {
                $result[] = self::build_nested_query($value, $new_key);
            } else {
                // Handle null values by converting to empty string
                $result[] = urlencode($new_key) . '=' . urlencode($value ?? '');
            }
        }

        return implode('&', $result);
    }

    // =========================================
    // CUSTOMERS
    // =========================================

    /**
     * Create or get Stripe customer for a user
     */
    public static function get_or_create_customer($user_id)
    {
        $stripe_customer_id = get_user_meta($user_id, '_stripe_customer_id', true);

        if (!empty($stripe_customer_id)) {
            // Verify customer still exists
            $customer = self::api_request('customers/' . $stripe_customer_id);
            if (!is_wp_error($customer) && !isset($customer['deleted'])) {
                return $stripe_customer_id;
            }
        }

        // Create new customer
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('invalid_user', __('User not found', 'rental-gates'));
        }

        $customer_data = array(
            'email' => $user->user_email,
            'name' => $user->display_name,
            'metadata' => array(
                'wp_user_id' => $user_id,
                'source' => 'rental_gates',
            ),
        );

        $customer = self::api_request('customers', 'POST', $customer_data);

        if (is_wp_error($customer)) {
            return $customer;
        }

        // Save customer ID
        update_user_meta($user_id, '_stripe_customer_id', $customer['id']);

        return $customer['id'];
    }

    /**
     * Get customer details
     */
    public static function get_customer($customer_id)
    {
        return self::api_request('customers/' . $customer_id);
    }

    // =========================================
    // CHECKOUT SESSIONS (Primary Payment Method)
    // =========================================

    /**
     * Create Embedded Checkout Session for rent payment
     * This keeps the entire checkout flow on your website
     */
    public static function create_checkout_session($payment_id, $ui_mode = 'embedded')
    {
        $payment = Rental_Gates_Payment::get_with_details($payment_id);

        if (!$payment) {
            return new WP_Error('not_found', __('Payment not found', 'rental-gates'));
        }

        if (!in_array($payment['status'], array('pending', 'partially_paid', 'failed'))) {
            return new WP_Error('invalid_status', __('Payment cannot be processed', 'rental-gates'));
        }

        // Get tenant's Stripe customer
        $customer_id = null;
        if ($payment['tenant_id']) {
            $tenant = Rental_Gates_Tenant::get($payment['tenant_id']);
            if ($tenant && $tenant['user_id']) {
                $customer_id = self::get_or_create_customer($tenant['user_id']);
                if (is_wp_error($customer_id)) {
                    $customer_id = null; // Continue without customer
                }
            }
        }

        // Calculate amount (in cents)
        $amount_to_pay = $payment['amount'] - $payment['amount_paid'];
        $amount_cents = round($amount_to_pay * 100);

        // Calculate platform fee
        $platform_fee_cents = round($amount_cents * (self::PLATFORM_FEE_PERCENT / 100));

        // Build session data
        $session_data = array(
            'mode' => 'payment',
            'line_items' => array(
                array(
                    'price_data' => array(
                        'currency' => strtolower($payment['currency']),
                        'product_data' => array(
                            'name' => sprintf(__('Rent Payment - %s', 'rental-gates'), $payment['payment_number']),
                            'description' => $payment['description'] ?: sprintf(__('Payment for %s', 'rental-gates'), $payment['unit_name'] ?? 'Unit'),
                        ),
                        'unit_amount' => $amount_cents,
                    ),
                    'quantity' => 1,
                ),
            ),
            'payment_intent_data' => array(
                'metadata' => array(
                    'payment_id' => $payment_id,
                    'payment_number' => $payment['payment_number'],
                    'organization_id' => $payment['organization_id'],
                    'lease_id' => $payment['lease_id'],
                    'tenant_id' => $payment['tenant_id'],
                    'source' => 'rental_gates_checkout',
                ),
            ),
            'metadata' => array(
                'payment_id' => $payment_id,
                'payment_number' => $payment['payment_number'],
            ),
        );

        // Add customer if available
        if ($customer_id) {
            $session_data['customer'] = $customer_id;
        } else {
            $session_data['customer_creation'] = 'always';
        }

        // Check if organization has connected Stripe account
        $connected_account = self::get_connected_account($payment['organization_id']);

        // Log for debugging
        Rental_Gates_Logger::debug('stripe', 'Creating checkout session', array('payment_id' => $payment_id));
        Rental_Gates_Logger::debug('stripe', 'Platform fee calculated', array('platform_fee_cents' => $platform_fee_cents));
        Rental_Gates_Logger::debug('stripe', 'Connected account lookup', array('connected_account' => $connected_account ? $connected_account : 'none'));

        if ($connected_account && !empty($connected_account['stripe_account_id']) && $connected_account['charges_enabled']) {
            // Use Stripe Connect - funds go to connected account, we collect fee
            $session_data['payment_intent_data']['transfer_data'] = array(
                'destination' => $connected_account['stripe_account_id'],
            );
            $session_data['payment_intent_data']['application_fee_amount'] = $platform_fee_cents;
            Rental_Gates_Logger::debug('stripe', 'Using Connect destination', array('stripe_account_id' => $connected_account['stripe_account_id']));
        } else {
            // No Connect - store fee info in metadata for manual reconciliation
            $session_data['payment_intent_data']['metadata']['platform_fee_cents'] = $platform_fee_cents;
            $session_data['payment_intent_data']['metadata']['platform_fee_percent'] = self::PLATFORM_FEE_PERCENT;
            Rental_Gates_Logger::debug('stripe', 'No Connect account, fee stored in metadata only');
        }

        // Determine correct return path based on user type
        // Check if user is a tenant (has tenant record linked to user_id) or owner/PM
        $return_base = '/rental-gates/dashboard/payments';
        $current_user_id = get_current_user_id();
        if ($current_user_id) {
            global $wpdb;
            $tables = Rental_Gates_Database::get_table_names();
            $is_tenant = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['tenants']} WHERE user_id = %d AND status = 'active'",
                $current_user_id
            ));
            if ($is_tenant > 0) {
                $return_base = '/rental-gates/tenant/payments';
            }
        }

        // Set UI mode specific options
        if ($ui_mode === 'embedded') {
            $session_data['ui_mode'] = 'embedded';
            $session_data['return_url'] = add_query_arg(array(
                'session_id' => '{CHECKOUT_SESSION_ID}',
                'payment_id' => $payment_id,
            ), home_url($return_base));
        } else {
            // Hosted checkout (redirect mode)
            $session_data['success_url'] = add_query_arg(array(
                'session_id' => '{CHECKOUT_SESSION_ID}',
                'payment_id' => $payment_id,
                'status' => 'success',
            ), home_url($return_base));
            $session_data['cancel_url'] = add_query_arg(array(
                'payment_id' => $payment_id,
                'status' => 'cancelled',
            ), home_url($return_base));
        }

        $session = self::api_request('checkout/sessions', 'POST', $session_data);

        if (is_wp_error($session)) {
            return $session;
        }

        // Update payment with session ID
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();
        $wpdb->update(
            $tables['payments'],
            array(
                'meta_data' => json_encode(array_merge(
                    $payment['meta_data'] ?? array(),
                    array('checkout_session_id' => $session['id'])
                )),
            ),
            array('id' => $payment_id)
        );

        return $session;
    }

    /**
     * Get Checkout Session status
     */
    public static function get_checkout_session($session_id, $expand = array())
    {
        $endpoint = 'checkout/sessions/' . $session_id;
        if (!empty($expand)) {
            $endpoint .= '?expand[]=' . implode('&expand[]=', $expand);
        }
        return self::api_request($endpoint);
    }

    /**
     * Sync payment status from Stripe Checkout Session
     * This is a fallback for when webhooks don't fire
     */
    public static function sync_payment_from_session($session_id)
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        // Get session with expanded payment intent
        $session = self::get_checkout_session($session_id, array('payment_intent', 'payment_intent.latest_charge'));

        if (is_wp_error($session)) {
            Rental_Gates_Logger::error('stripe', 'Failed to get session', array('session_id' => $session_id, 'error' => $session->get_error_message()));
            return $session;
        }

        // Get payment_id from metadata
        $payment_id = $session['metadata']['payment_id'] ?? null;

        if (!$payment_id) {
            Rental_Gates_Logger::warning('stripe', 'No payment_id in session metadata', array('session_id' => $session_id));
            return new WP_Error('no_payment_id', __('Payment ID not found in session', 'rental-gates'));
        }

        // Check current payment status
        $payment = Rental_Gates_Payment::get($payment_id);
        if (!$payment) {
            return new WP_Error('not_found', __('Payment not found', 'rental-gates'));
        }

        // If already succeeded, skip
        if ($payment['status'] === 'succeeded') {
            return array('status' => 'already_synced', 'payment_id' => $payment_id);
        }

        // Only sync if session is complete and paid
        if ($session['status'] !== 'complete') {
            return array('status' => 'session_not_complete', 'session_status' => $session['status']);
        }

        if ($session['payment_status'] !== 'paid') {
            // Update to processing for async payments
            if ($session['payment_status'] === 'unpaid') {
                $wpdb->update(
                    $tables['payments'],
                    array('status' => 'processing'),
                    array('id' => $payment_id)
                );
            }
            return array('status' => 'payment_not_complete', 'payment_status' => $session['payment_status']);
        }

        // Payment is complete - extract details
        // IMPORTANT: Fallback to payment.amount if session.amount_total is 0 or missing
        $amount_paid = isset($session['amount_total']) && $session['amount_total'] > 0
            ? $session['amount_total'] / 100
            : $payment['amount'];

        // Validate amount - must be greater than 0
        if ($amount_paid <= 0) {
            Rental_Gates_Logger::warning('stripe', 'Invalid amount_paid calculated, using payment amount', array('payment_id' => $payment_id, 'fallback_amount' => $payment['amount']));
            $amount_paid = $payment['amount'];
        }

        $payment_intent_id = '';
        $charge_id = '';
        $stripe_fee = 0;
        $receipt_url = '';
        $card_brand = '';
        $card_last4 = '';
        $payment_method_type = 'card';

        // Get payment intent details
        if (isset($session['payment_intent'])) {
            $intent = is_array($session['payment_intent']) ? $session['payment_intent'] : self::api_request('payment_intents/' . $session['payment_intent']);

            if (!is_wp_error($intent)) {
                $payment_intent_id = $intent['id'];

                // Get charge details
                $charge = null;
                if (isset($intent['latest_charge'])) {
                    $charge = is_array($intent['latest_charge']) ? $intent['latest_charge'] : self::api_request('charges/' . $intent['latest_charge']);
                }

                if ($charge && !is_wp_error($charge)) {
                    $charge_id = $charge['id'];
                    $receipt_url = $charge['receipt_url'] ?? '';

                    // Get payment method details
                    if (isset($charge['payment_method_details'])) {
                        $pm_details = $charge['payment_method_details'];
                        $payment_method_type = $pm_details['type'] ?? 'card';

                        if ($payment_method_type === 'card' && isset($pm_details['card'])) {
                            $card_brand = $pm_details['card']['brand'] ?? '';
                            $card_last4 = $pm_details['card']['last4'] ?? '';
                        }
                    }

                    // Get Stripe fee from balance transaction
                    if (isset($charge['balance_transaction'])) {
                        $balance = self::api_request('balance_transactions/' . $charge['balance_transaction']);
                        if (!is_wp_error($balance)) {
                            $stripe_fee = $balance['fee'] / 100;
                        }
                    }
                }
            }
        }

        $platform_fee = $amount_paid * (self::PLATFORM_FEE_PERCENT / 100);
        $net_amount = $amount_paid - $platform_fee - $stripe_fee;

        // Build method string
        $method = 'stripe';
        if ($card_brand && $card_last4) {
            $method = 'stripe_' . strtolower($card_brand);
        }

        // Update payment record
        $update_data = array(
            'status' => 'succeeded',
            'amount_paid' => $amount_paid,
            'stripe_payment_intent_id' => $payment_intent_id,
            'stripe_charge_id' => $charge_id,
            'stripe_fee' => $stripe_fee,
            'platform_fee' => $platform_fee,
            'net_amount' => $net_amount,
            'method' => $method,
            'paid_at' => current_time('mysql'),
        );

        // Store additional details in meta_data
        // meta_data may already be decoded as array by format_payment()
        if (is_array($payment['meta_data'])) {
            $existing_meta = $payment['meta_data'];
        } else {
            $existing_meta = json_decode($payment['meta_data'] ?? '{}', true) ?: array();
        }
        $existing_meta['stripe_details'] = array(
            'receipt_url' => $receipt_url,
            'card_brand' => $card_brand,
            'card_last4' => $card_last4,
            'payment_method_type' => $payment_method_type,
            'checkout_session_id' => $session_id,
            'customer_email' => $session['customer_details']['email'] ?? '',
        );
        $update_data['meta_data'] = json_encode($existing_meta);

        $result = $wpdb->update(
            $tables['payments'],
            $update_data,
            array('id' => $payment_id)
        );

        if ($result === false) {
            Rental_Gates_Logger::error('stripe', 'Failed to update payment', array('payment_id' => $payment_id, 'db_error' => $wpdb->last_error));
            return new WP_Error('db_error', __('Failed to update payment', 'rental-gates'));
        }

        // Clear cache
        Rental_Gates_Cache::delete('payment_' . $payment_id);
        if ($payment['organization_id']) {
            Rental_Gates_Cache::delete('payments_org_' . $payment['organization_id']);
            // Clear stats cache for dashboard updates
            Rental_Gates_Cache::delete_stats($payment['organization_id'], 'payments');
            Rental_Gates_Cache::delete_stats($payment['organization_id'], 'dashboard');
        }
        if ($payment['tenant_id']) {
            Rental_Gates_Cache::delete('payments_tenant_' . $payment['tenant_id']);
        }

        // Generate receipt automatically
        if (class_exists('Rental_Gates_Invoice')) {
            try {
                Rental_Gates_Invoice::create_from_payment($payment_id, 'receipt');
            } catch (Exception $e) {
                Rental_Gates_Logger::error('stripe', 'Failed to create receipt', array('payment_id' => $payment_id, 'error' => $e->getMessage()));
            }
        }

        // Send receipt email if not already sent
        try {
            Rental_Gates_Email::send_payment_receipt($payment_id);
        } catch (Exception $e) {
            Rental_Gates_Logger::error('stripe', 'Failed to send receipt email', array('payment_id' => $payment_id, 'error' => $e->getMessage()));
        }

        // Create notification - update to use tenant portal URL
        $payment = Rental_Gates_Payment::get_with_details($payment_id);
        if ($payment && $payment['tenant_id']) {
            $tenant = Rental_Gates_Tenant::get($payment['tenant_id']);
            if ($tenant && $tenant['user_id']) {
                Rental_Gates_Notification::send(
                    $tenant['user_id'],
                    'payment',
                    __('Payment Successful', 'rental-gates'),
                    sprintf(__('Your payment of $%.2f has been processed successfully.', 'rental-gates'), $amount_paid),
                    home_url('/rental-gates/tenant/payments')
                );
            }
        }

        return array(
            'status' => 'synced',
            'payment_id' => $payment_id,
            'amount' => $amount_paid,
            'receipt_url' => $receipt_url,
        );
    }

    /**
     * Expire a Checkout Session
     */
    public static function expire_checkout_session($session_id)
    {
        return self::api_request('checkout/sessions/' . $session_id . '/expire', 'POST');
    }

    // =========================================
    // PAYMENT METHODS (For saved cards)
    // =========================================

    /**
     * Create SetupIntent for adding payment method
     */
    public static function create_setup_intent($user_id)
    {
        $customer_id = self::get_or_create_customer($user_id);

        if (is_wp_error($customer_id)) {
            return $customer_id;
        }

        $data = array(
            'customer' => $customer_id,
            'payment_method_types' => array('card', 'us_bank_account'),
            'metadata' => array(
                'wp_user_id' => $user_id,
            ),
        );

        return self::api_request('setup_intents', 'POST', $data);
    }

    /**
     * Attach payment method to customer
     */
    public static function attach_payment_method($payment_method_id, $customer_id)
    {
        return self::api_request('payment_methods/' . $payment_method_id . '/attach', 'POST', array(
            'customer' => $customer_id,
        ));
    }

    /**
     * Detach payment method
     */
    public static function detach_payment_method($payment_method_id)
    {
        return self::api_request('payment_methods/' . $payment_method_id . '/detach', 'POST');
    }

    /**
     * List customer payment methods
     */
    public static function list_payment_methods($customer_id, $type = 'card')
    {
        return self::api_request('payment_methods', 'GET', array(
            'customer' => $customer_id,
            'type' => $type,
        ));
    }

    /**
     * Save payment method to database
     */
    public static function save_payment_method($user_id, $payment_method_data)
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $data = array(
            'user_id' => $user_id,
            'stripe_payment_method_id' => $payment_method_data['id'],
            'type' => $payment_method_data['type'],
            'is_default' => 0,
            'is_verified' => 1,
            'created_at' => current_time('mysql'),
        );

        if ($payment_method_data['type'] === 'card' && isset($payment_method_data['card'])) {
            $card = $payment_method_data['card'];
            $data['card_brand'] = $card['brand'];
            $data['card_last4'] = $card['last4'];
            $data['card_exp_month'] = $card['exp_month'];
            $data['card_exp_year'] = $card['exp_year'];
        } elseif ($payment_method_data['type'] === 'us_bank_account' && isset($payment_method_data['us_bank_account'])) {
            $bank = $payment_method_data['us_bank_account'];
            $data['bank_name'] = $bank['bank_name'];
            $data['bank_last4'] = $bank['last4'];
            $data['is_verified'] = ($bank['status'] ?? '') === 'verified' ? 1 : 0;
        }

        // Check if this is the first payment method
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['payment_methods']} WHERE user_id = %d",
            $user_id
        ));

        if ($count == 0) {
            $data['is_default'] = 1;
        }

        $wpdb->insert($tables['payment_methods'], $data);

        return $wpdb->insert_id;
    }

    /**
     * Get user's saved payment methods
     */
    public static function get_user_payment_methods($user_id)
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['payment_methods']} 
             WHERE user_id = %d 
             ORDER BY is_default DESC, created_at DESC",
            $user_id
        ), ARRAY_A);
    }

    /**
     * Delete payment method
     */
    public static function delete_payment_method($user_id, $method_id)
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        // Get the method
        $method = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['payment_methods']} WHERE id = %d AND user_id = %d",
            $method_id,
            $user_id
        ), ARRAY_A);

        if (!$method) {
            return new WP_Error('not_found', __('Payment method not found', 'rental-gates'));
        }

        // Check if this payment method is being used by an active subscription
        // Get user's organization (if feature gate is available)
        $org_id = null;
        if (function_exists('rg_feature_gate')) {
            $feature_gate = rg_feature_gate();
            if ($feature_gate) {
                $org_id = $feature_gate->get_user_org_id();
            }
        }
        
        if ($org_id) {
            $subscription = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$tables['subscriptions']} 
                 WHERE organization_id = %d 
                 AND status IN ('active', 'trialing', 'past_due', 'unpaid', 'incomplete', 'pending_payment')
                 ORDER BY created_at DESC LIMIT 1",
                $org_id
            ));
            
            if ($subscription && !empty($subscription->stripe_subscription_id)) {
                // Get subscription from Stripe to check default payment method
                $stripe_sub = self::api_request(
                    "subscriptions/{$subscription->stripe_subscription_id}",
                    'GET'
                );
                
                if (!is_wp_error($stripe_sub)) {
                    $sub_pm_id = $stripe_sub['default_payment_method'] ?? $stripe_sub['default_source'] ?? null;
                    if ($sub_pm_id === $method['stripe_payment_method_id']) {
                        return new WP_Error('in_use', __('This payment method is currently being used by your subscription. Please update your subscription to use a different payment method first.', 'rental-gates'));
                    }
                }
            }
        }

        // Detach from Stripe
        $detach_result = self::detach_payment_method($method['stripe_payment_method_id']);
        if (is_wp_error($detach_result)) {
            // Log but don't fail - payment method might already be detached
            Rental_Gates_Logger::error('stripe', 'Failed to detach payment method', array('payment_method_id' => $method['stripe_payment_method_id'], 'error' => $detach_result->get_error_message()));
        }

        // Delete from database
        $wpdb->delete($tables['payment_methods'], array('id' => $method_id), array('%d'));

        // If this was default, set another as default
        if ($method['is_default']) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$tables['payment_methods']} 
                 SET is_default = 1 
                 WHERE user_id = %d 
                 ORDER BY created_at ASC 
                 LIMIT 1",
                $user_id
            ));
        }

        return true;
    }

    /**
     * Set default payment method
     */
    public static function set_default_payment_method($user_id, $method_id)
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        // Get the payment method
        $method = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['payment_methods']} WHERE id = %d AND user_id = %d",
            $method_id,
            $user_id
        ), ARRAY_A);

        if (!$method) {
            return new WP_Error('not_found', __('Payment method not found', 'rental-gates'));
        }

        // Remove default from all
        $wpdb->update(
            $tables['payment_methods'],
            array('is_default' => 0),
            array('user_id' => $user_id)
        );

        // Set new default
        $wpdb->update(
            $tables['payment_methods'],
            array('is_default' => 1),
            array('id' => $method_id, 'user_id' => $user_id)
        );

        // Also update Stripe customer's default payment method
        $customer_id = self::get_or_create_customer($user_id);
        if (!is_wp_error($customer_id)) {
            self::set_customer_invoice_payment_method($customer_id, $method['stripe_payment_method_id']);
        }

        return true;
    }

    // =========================================
    // STRIPE CONNECT (Property Owner Payouts)
    // =========================================

    /**
     * Create Connect account for organization
     */
    public static function create_connect_account($org_id)
    {
        $organization = Rental_Gates_Organization::get($org_id);

        if (!$organization) {
            return new WP_Error('not_found', __('Organization not found', 'rental-gates'));
        }

        // Check if already has account
        $existing = self::get_connected_account($org_id);
        if ($existing) {
            return new WP_Error('exists', __('Organization already has a connected account', 'rental-gates'));
        }

        // Get primary owner from organization_members table
        $owner_email = '';
        $members = Rental_Gates_Organization::get_members($org_id);
        if ($members) {
            foreach ($members as $member) {
                if (!empty($member['is_primary_owner'])) {
                    $owner_email = $member['user_email'] ?? '';
                    break;
                }
            }
            // If no primary owner found, use first member
            if (empty($owner_email) && !empty($members[0]['user_email'])) {
                $owner_email = $members[0]['user_email'];
            }
        }

        // Fallback to current user
        if (empty($owner_email)) {
            $current_user = wp_get_current_user();
            $owner_email = $current_user->user_email ?? '';
        }

        $account_data = array(
            'type' => 'express',
            'country' => 'US',
            'email' => $owner_email,
            'capabilities' => array(
                'card_payments' => array('requested' => 'true'),
                'transfers' => array('requested' => 'true'),
            ),
            'business_type' => 'individual',
            'metadata' => array(
                'organization_id' => $org_id,
                'source' => 'rental_gates',
            ),
        );

        $account = self::api_request('accounts', 'POST', $account_data);

        if (is_wp_error($account)) {
            return $account;
        }

        // Save to database
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $wpdb->insert($tables['stripe_accounts'], array(
            'organization_id' => $org_id,
            'stripe_account_id' => $account['id'],
            'account_type' => 'express',
            'status' => 'pending',
            'charges_enabled' => $account['charges_enabled'] ? 1 : 0,
            'payouts_enabled' => $account['payouts_enabled'] ? 1 : 0,
            'details_submitted' => $account['details_submitted'] ? 1 : 0,
            'country' => $account['country'],
            'default_currency' => $account['default_currency'] ?? 'usd',
            'created_at' => current_time('mysql'),
        ));

        return $account;
    }

    /**
     * Create account link for onboarding
     */
    public static function create_account_link($org_id, $return_url = null, $refresh_url = null)
    {
        $account = self::get_connected_account($org_id);

        if (!$account) {
            return new WP_Error('not_found', __('No connected account found', 'rental-gates'));
        }

        if (!$return_url) {
            $return_url = home_url('/rental-gates/dashboard/settings?stripe_return=1');
        }

        if (!$refresh_url) {
            $refresh_url = home_url('/rental-gates/dashboard/settings?stripe_refresh=1');
        }

        $link_data = array(
            'account' => $account['stripe_account_id'],
            'refresh_url' => $refresh_url,
            'return_url' => $return_url,
            'type' => 'account_onboarding',
        );

        return self::api_request('account_links', 'POST', $link_data);
    }

    /**
     * Create login link for existing connected account
     */
    public static function create_login_link($org_id)
    {
        $account = self::get_connected_account($org_id);

        if (!$account) {
            return new WP_Error('not_found', __('No connected account found', 'rental-gates'));
        }

        return self::api_request('accounts/' . $account['stripe_account_id'] . '/login_links', 'POST');
    }

    /**
     * Get connected account for organization
     */
    public static function get_connected_account($org_id)
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['stripe_accounts']} WHERE organization_id = %d",
            $org_id
        ), ARRAY_A);
    }

    /**
     * Update connected account status from Stripe
     */
    public static function refresh_connected_account($org_id)
    {
        $local = self::get_connected_account($org_id);

        if (!$local) {
            return new WP_Error('not_found', __('No connected account found', 'rental-gates'));
        }

        $account = self::api_request('accounts/' . $local['stripe_account_id']);

        if (is_wp_error($account)) {
            return $account;
        }

        // Determine status
        $status = 'pending';
        if ($account['charges_enabled'] && $account['payouts_enabled']) {
            $status = 'active';
        } elseif (isset($account['requirements']['disabled_reason'])) {
            $status = 'restricted';
        }

        // Update database
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $wpdb->update(
            $tables['stripe_accounts'],
            array(
                'status' => $status,
                'charges_enabled' => $account['charges_enabled'] ? 1 : 0,
                'payouts_enabled' => $account['payouts_enabled'] ? 1 : 0,
                'details_submitted' => $account['details_submitted'] ? 1 : 0,
                'business_name' => $account['business_profile']['name'] ?? null,
                'updated_at' => current_time('mysql'),
            ),
            array('organization_id' => $org_id)
        );

        return $account;
    }

    // =========================================
    // REFUNDS
    // =========================================

    /**
     * Create refund
     */
    public static function create_refund($payment_id, $amount = null, $reason = '')
    {
        $payment = Rental_Gates_Payment::get($payment_id);

        if (!$payment) {
            return new WP_Error('not_found', __('Payment not found', 'rental-gates'));
        }

        if (empty($payment['stripe_payment_intent_id'])) {
            return new WP_Error('no_intent', __('Payment has no Stripe payment intent', 'rental-gates'));
        }

        $refund_data = array(
            'payment_intent' => $payment['stripe_payment_intent_id'],
            'metadata' => array(
                'payment_id' => $payment_id,
                'reason' => $reason,
            ),
        );

        if ($amount !== null) {
            $refund_data['amount'] = round($amount * 100);
        }

        $refund = self::api_request('refunds', 'POST', $refund_data);

        if (is_wp_error($refund)) {
            return $refund;
        }

        // Update payment status
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $refund_amount = $refund['amount'] / 100;
        $new_amount_paid = $payment['amount_paid'] - $refund_amount;

        $wpdb->update(
            $tables['payments'],
            array(
                'amount_paid' => max(0, $new_amount_paid),
                'status' => $new_amount_paid <= 0 ? 'refunded' : 'partially_paid',
                'notes' => $payment['notes'] . "\n" . sprintf(__('Refunded $%.2f on %s', 'rental-gates'), $refund_amount, current_time('mysql')),
            ),
            array('id' => $payment_id)
        );

        return $refund;
    }

    // =========================================
    // SUBSCRIPTIONS
    // =========================================



    // =========================================
    // AI CREDIT PURCHASES
    // =========================================

    /**
     * Create a checkout session for AI credit pack purchase
     * 
     * @param int $org_id Organization ID
     * @param array $pack Credit pack details
     * @return array|WP_Error Checkout session data or error
     */
    public static function create_credit_purchase_session($org_id, $pack)
    {
        if (!self::is_configured()) {
            return new WP_Error('stripe_not_configured', __('Stripe is not configured', 'rental-gates'));
        }

        $user_id = get_current_user_id();

        // Get or create Stripe customer
        $customer_id = self::get_or_create_customer($user_id);
        if (is_wp_error($customer_id)) {
            return $customer_id;
        }

        // Create line item for credit pack
        $line_items = array(
            array(
                'price_data' => array(
                    'currency' => strtolower($pack['currency'] ?? 'usd'),
                    'product_data' => array(
                        'name' => sprintf(__('%s - %d AI Credits', 'rental-gates'), $pack['name'], $pack['credits']),
                        'description' => __('AI credits for property management tools', 'rental-gates'),
                    ),
                    'unit_amount' => intval($pack['price'] * 100), // Convert to cents
                ),
                'quantity' => 1,
            ),
        );

        // Build checkout session params
        $success_url = home_url('/rental-gates/dashboard/ai-tools?purchase=success&session_id={CHECKOUT_SESSION_ID}');
        $cancel_url = home_url('/rental-gates/dashboard/ai-tools?purchase=cancelled');

        $params = array(
            'customer' => $customer_id,
            'payment_method_types' => array('card'),
            'line_items' => $line_items,
            'mode' => 'payment',
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'metadata' => array(
                'type' => 'ai_credit_purchase',
                'organization_id' => $org_id,
                'pack_id' => $pack['id'] ?? $pack['slug'],
                'credits' => $pack['credits'],
                'user_id' => $user_id,
            ),
        );

        $response = self::api_request('checkout/sessions', 'POST', $params);

        if (is_wp_error($response)) {
            return $response;
        }

        return array(
            'session_id' => $response['id'],
            'url' => $response['url'],
        );
    }

    /**
     * Complete AI credit purchase after successful checkout
     * 
     * @param string $session_id Stripe checkout session ID
     * @return bool|WP_Error
     */
    public static function complete_credit_purchase_from_session($session_id)
    {
        $session = self::get_checkout_session($session_id);

        if (is_wp_error($session)) {
            return $session;
        }

        // Verify this is a credit purchase
        if (empty($session['metadata']['type']) || $session['metadata']['type'] !== 'ai_credit_purchase') {
            return new WP_Error('invalid_session', __('Invalid checkout session', 'rental-gates'));
        }

        // Check payment status
        if ($session['payment_status'] !== 'paid') {
            return new WP_Error('payment_not_complete', __('Payment not completed', 'rental-gates'));
        }

        // Check if already processed (idempotency)
        global $wpdb;
        $already_processed = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rg_ai_credit_purchases WHERE stripe_payment_intent_id = %s",
            $session['payment_intent']
        ));

        if ($already_processed) {
            return true; // Already processed, return success
        }

        // Grant credits
        $org_id = intval($session['metadata']['organization_id']);
        $credits = intval($session['metadata']['credits']);
        $pack_id = $session['metadata']['pack_id'];

        if (!class_exists('Rental_Gates_AI_Credits')) {
            return new WP_Error('credits_unavailable', __('AI Credits module not available', 'rental-gates'));
        }

        $credits_manager = rg_ai_credits();

        // Record the purchase
        $result = $credits_manager->complete_purchase($org_id, $credits, $session['payment_intent']);

        if ($result) {
            Rental_Gates_Logger::info('stripe', 'AI credit purchase completed', array('credits' => $credits, 'organization_id' => $org_id, 'session_id' => $session_id));
        }

        return $result;
    }

    // =========================================
    // WEBHOOKS
    // =========================================

    /**
     * Handle webhook event
     */
    public static function handle_webhook($payload, $signature)
    {
        $webhook_secret = get_option('rental_gates_stripe_webhook_secret', '');

        if (empty($webhook_secret)) {
            return new WP_Error('no_secret', __('Webhook secret not configured', 'rental-gates'));
        }

        // Verify signature
        $timestamp = null;
        $signatures = array();

        // Parse signature header
        $parts = explode(',', $signature);
        foreach ($parts as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) {
                if ($kv[0] === 't') {
                    $timestamp = $kv[1];
                } elseif ($kv[0] === 'v1') {
                    $signatures[] = $kv[1];
                }
            }
        }

        if (!$timestamp || empty($signatures)) {
            return new WP_Error('invalid_signature', __('Invalid webhook signature', 'rental-gates'));
        }

        // Verify timestamp (allow 5 minutes tolerance)
        if (abs(time() - $timestamp) > 300) {
            return new WP_Error('timestamp_expired', __('Webhook timestamp expired', 'rental-gates'));
        }

        // Compute expected signature
        $signed_payload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signed_payload, $webhook_secret);

        $valid = false;
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            return new WP_Error('signature_mismatch', __('Webhook signature verification failed', 'rental-gates'));
        }

        // Parse event
        $event = json_decode($payload, true);

        if (!$event || !isset($event['type'])) {
            return new WP_Error('invalid_payload', __('Invalid webhook payload', 'rental-gates'));
        }

        // Handle event types
        switch ($event['type']) {
            case 'checkout.session.completed':
                return self::handle_checkout_completed($event['data']['object']);

            case 'checkout.session.async_payment_succeeded':
                return self::handle_checkout_async_succeeded($event['data']['object']);

            case 'checkout.session.async_payment_failed':
                return self::handle_checkout_async_failed($event['data']['object']);

            case 'payment_intent.succeeded':
                return self::handle_payment_succeeded($event['data']['object']);

            case 'payment_intent.payment_failed':
                return self::handle_payment_failed($event['data']['object']);

            case 'account.updated':
                return self::handle_account_updated($event['data']['object']);

            case 'setup_intent.succeeded':
                return self::handle_setup_intent_succeeded($event['data']['object']);

            case 'customer.subscription.updated':
                return self::handle_subscription_updated($event['data']['object']);

            case 'customer.subscription.deleted':
                return self::handle_subscription_deleted($event['data']['object']);

            case 'invoice.paid':
                return self::handle_invoice_paid($event['data']['object']);

            case 'invoice.payment_failed':
                return self::handle_invoice_payment_failed($event['data']['object']);

            default:
                // Log unhandled events
                Rental_Gates_Logger::warning('stripe', 'Unhandled webhook event', array('event_type' => $event['type']));
                return true;
        }
    }

    /**
     * Handle subscription updated webhook
     */
    private static function handle_subscription_updated($subscription)
    {
        global $wpdb;

        $stripe_subscription_id = $subscription['id'];

        // Find our subscription record
        $local_subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rg_subscriptions WHERE stripe_subscription_id = %s",
            $stripe_subscription_id
        ));

        if (!$local_subscription) {
            Rental_Gates_Logger::warning('stripe', 'Subscription not found for webhook', array('stripe_subscription_id' => $stripe_subscription_id));
            return true;
        }

        // Map Stripe status to our status
        $status_map = array(
            'active' => 'active',
            'trialing' => 'trialing',
            'past_due' => 'past_due',
            'canceled' => 'cancelled',
            'unpaid' => 'past_due',
            'incomplete' => 'pending',
            'incomplete_expired' => 'expired',
        );

        $new_status = $status_map[$subscription['status']] ?? 'active';

        // Update local subscription
        $update_data = array(
            'status' => $new_status,
            'cancel_at_period_end' => $subscription['cancel_at_period_end'] ? 1 : 0,
            'current_period_start' => date('Y-m-d H:i:s', $subscription['current_period_start']),
            'current_period_end' => date('Y-m-d H:i:s', $subscription['current_period_end']),
            'updated_at' => current_time('mysql'),
        );

        // If cancelled, record the cancellation
        if ($subscription['canceled_at']) {
            $update_data['cancelled_at'] = date('Y-m-d H:i:s', $subscription['canceled_at']);
        }

        $wpdb->update(
            $wpdb->prefix . 'rg_subscriptions',
            $update_data,
            array('id' => $local_subscription->id),
            array('%s', '%d', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        Rental_Gates_Logger::info('stripe', 'Subscription updated via webhook', array('subscription_id' => $local_subscription->id, 'new_status' => $new_status));

        return true;
    }

    /**
     * Handle subscription deleted webhook
     */
    private static function handle_subscription_deleted($subscription)
    {
        global $wpdb;

        $stripe_subscription_id = $subscription['id'];

        // Find our subscription record
        $local_subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rg_subscriptions WHERE stripe_subscription_id = %s",
            $stripe_subscription_id
        ));

        if (!$local_subscription) {
            return true;
        }

        // Update to cancelled/expired
        $wpdb->update(
            $wpdb->prefix . 'rg_subscriptions',
            array(
                'status' => 'expired',
                'cancelled_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $local_subscription->id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        // Downgrade organization to free plan
        $wpdb->update(
            $wpdb->prefix . 'rg_organizations',
            array('plan_id' => 'free'),
            array('id' => $local_subscription->organization_id),
            array('%s'),
            array('%d')
        );

        Rental_Gates_Logger::info('stripe', 'Subscription deleted via webhook, org downgraded to free', array('organization_id' => $local_subscription->organization_id));

        return true;
    }

    /**
     * Handle invoice paid webhook (for renewals)
     */
    private static function handle_invoice_paid($invoice)
    {
        global $wpdb;

        // Only process subscription invoices
        if (empty($invoice['subscription'])) {
            return true;
        }

        $stripe_subscription_id = $invoice['subscription'];

        // Find our subscription record
        $local_subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rg_subscriptions WHERE stripe_subscription_id = %s",
            $stripe_subscription_id
        ));

        if (!$local_subscription) {
            return true;
        }

        // Check if this is a renewal (not the first invoice)
        $is_renewal = !empty($invoice['billing_reason']) && $invoice['billing_reason'] === 'subscription_cycle';

        // Update subscription dates if this is a renewal
        if (!empty($invoice['lines']['data'][0]['period'])) {
            $period = $invoice['lines']['data'][0]['period'];
            $wpdb->update(
                $wpdb->prefix . 'rg_subscriptions',
                array(
                    'status' => 'active',
                    'current_period_start' => date('Y-m-d H:i:s', $period['start']),
                    'current_period_end' => date('Y-m-d H:i:s', $period['end']),
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $local_subscription->id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );

            // Grant AI credits on renewal
            if ($is_renewal && class_exists('Rental_Gates_AI_Credits')) {
                $plans = function_exists('rg_feature_gate') ? rg_feature_gate()->get_all_plans() : get_option('rental_gates_plans', array());
                $plan_slug = $local_subscription->plan_slug ?? $local_subscription->plan_id ?? 'free';
                $plan = $plans[$plan_slug] ?? array();
                $plan_credits = $plan['limits']['ai_credits'] ?? 0;

                if ($plan_credits > 0) {
                    $credits_manager = rg_ai_credits();
                    $cycle_end = date('Y-m-d H:i:s', $period['end']);
                    $credits_manager->refresh_subscription($local_subscription->organization_id, $plan_credits, $cycle_end);
                    Rental_Gates_Logger::info('stripe', 'Granted AI credits on renewal', array('credits' => $plan_credits, 'organization_id' => $local_subscription->organization_id));
                }

                // Fire renewal action
                do_action('rental_gates_subscription_renewed', $local_subscription->organization_id, $local_subscription);
            }
        }

        // Create invoice record for this renewal
        if (class_exists('Rental_Gates_Subscription_Invoice')) {
            $plans = rg_feature_gate() ? rg_feature_gate()->get_all_plans() : array();
            $plan = $plans[$local_subscription->plan_id] ?? array('name' => 'Subscription');

            $subscription_data = array(
                'id' => $local_subscription->id,
                'stripe_subscription_id' => $stripe_subscription_id,
                'billing_cycle' => $local_subscription->billing_cycle ?? 'monthly',
                'current_period_start' => $invoice['lines']['data'][0]['period']['start'] ?? time(),
                'current_period_end' => $invoice['lines']['data'][0]['period']['end'] ?? time(),
                'currency' => strtoupper($invoice['currency']),
            );

            Rental_Gates_Subscription_Invoice::create_from_subscription(
                $local_subscription->organization_id,
                $subscription_data,
                $plan,
                $invoice
            );
        }

        Rental_Gates_Logger::info('stripe', 'Invoice paid via webhook', array('subscription_id' => $local_subscription->id));

        return true;
    }

    /**
     * Handle invoice payment failed webhook
     */
    private static function handle_invoice_payment_failed($invoice)
    {
        global $wpdb;

        // Only process subscription invoices
        if (empty($invoice['subscription'])) {
            return true;
        }

        $stripe_subscription_id = $invoice['subscription'];

        // Find our subscription record
        $local_subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rg_subscriptions WHERE stripe_subscription_id = %s",
            $stripe_subscription_id
        ));

        if (!$local_subscription) {
            return true;
        }

        // Update subscription status to past_due
        $wpdb->update(
            $wpdb->prefix . 'rg_subscriptions',
            array(
                'status' => 'past_due',
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $local_subscription->id),
            array('%s', '%s'),
            array('%d')
        );

        Rental_Gates_Logger::warning('stripe', 'Invoice payment failed via webhook', array('subscription_id' => $local_subscription->id));

        // TODO: Send email notification to organization owner

        return true;
    }

    /**
     * Handle checkout.session.completed (Embedded Checkout success)
     */
    private static function handle_checkout_completed($session)
    {
        global $wpdb;

        // Check if this is an AI credit purchase
        if (!empty($session['metadata']['type']) && $session['metadata']['type'] === 'ai_credit_purchase') {
            return self::complete_credit_purchase_from_session($session['id']);
        }

        $tables = Rental_Gates_Database::get_table_names();

        $payment_id = $session['metadata']['payment_id'] ?? null;

        if (!$payment_id) {
            Rental_Gates_Logger::warning('stripe', 'No payment_id in checkout session', array('session_id' => $session['id']));
            return false;
        }

        // Get current payment to check status and get existing meta
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['payments']} WHERE id = %d",
            $payment_id
        ), ARRAY_A);

        if (!$payment) {
            Rental_Gates_Logger::error('stripe', 'Payment not found', array('payment_id' => $payment_id));
            return false;
        }

        // Skip if already succeeded (avoid duplicate processing)
        if ($payment['status'] === 'succeeded') {
            Rental_Gates_Logger::debug('stripe', 'Payment already succeeded, skipping', array('payment_id' => $payment_id));
            return true;
        }

        // Check payment status
        if ($session['payment_status'] === 'paid') {
            // Payment complete - update immediately
            $amount_paid = $session['amount_total'] / 100;

            // Get payment intent ID if available
            $payment_intent_id = $session['payment_intent'] ?? '';

            // Initialize variables for payment details
            $stripe_fee = 0;
            $charge_id = '';
            $receipt_url = '';
            $card_brand = '';
            $card_last4 = '';
            $payment_method_type = 'card';
            $platform_fee = $amount_paid * (self::PLATFORM_FEE_PERCENT / 100);

            // Try to get actual Stripe fee and card details from payment intent
            if ($payment_intent_id) {
                $intent = self::api_request('payment_intents/' . $payment_intent_id);
                if (!is_wp_error($intent) && !empty($intent['latest_charge'])) {
                    $charge = self::api_request('charges/' . $intent['latest_charge']);
                    if (!is_wp_error($charge)) {
                        $charge_id = $charge['id'];
                        $receipt_url = $charge['receipt_url'] ?? '';

                        // Get payment method details
                        if (isset($charge['payment_method_details'])) {
                            $pm_details = $charge['payment_method_details'];
                            $payment_method_type = $pm_details['type'] ?? 'card';

                            if ($payment_method_type === 'card' && isset($pm_details['card'])) {
                                $card_brand = $pm_details['card']['brand'] ?? '';
                                $card_last4 = $pm_details['card']['last4'] ?? '';
                            }
                        }

                        // Get Stripe fee from balance transaction
                        if (isset($charge['balance_transaction'])) {
                            $balance = self::api_request('balance_transactions/' . $charge['balance_transaction']);
                            if (!is_wp_error($balance)) {
                                $stripe_fee = $balance['fee'] / 100;
                            }
                        }
                    }
                }
            }

            $net_amount = $amount_paid - $platform_fee - $stripe_fee;

            // Build method string
            $method = 'stripe';
            if ($card_brand) {
                $method = 'stripe_' . strtolower($card_brand);
            }

            // Update meta_data with Stripe details
            $existing_meta = json_decode($payment['meta_data'] ?? '{}', true) ?: array();
            $existing_meta['stripe_details'] = array(
                'receipt_url' => $receipt_url,
                'card_brand' => $card_brand,
                'card_last4' => $card_last4,
                'payment_method_type' => $payment_method_type,
                'checkout_session_id' => $session['id'],
                'customer_email' => $session['customer_details']['email'] ?? '',
            );

            // Update payment
            $wpdb->update(
                $tables['payments'],
                array(
                    'status' => 'succeeded',
                    'amount_paid' => $amount_paid,
                    'stripe_payment_intent_id' => $payment_intent_id,
                    'stripe_charge_id' => $charge_id,
                    'stripe_fee' => $stripe_fee,
                    'platform_fee' => $platform_fee,
                    'net_amount' => $net_amount,
                    'method' => $method,
                    'paid_at' => current_time('mysql'),
                    'meta_data' => json_encode($existing_meta),
                ),
                array('id' => $payment_id)
            );

            // Clear any cache
            if (class_exists('Rental_Gates_Cache')) {
                Rental_Gates_Cache::delete('payment_' . $payment_id);
            }

            // Send receipt email
            Rental_Gates_Email::send_payment_receipt($payment_id);

            // Create notification
            $payment = Rental_Gates_Payment::get_with_details($payment_id);
            if ($payment && $payment['tenant_id']) {
                $tenant = Rental_Gates_Tenant::get($payment['tenant_id']);
                if ($tenant && $tenant['user_id']) {
                    Rental_Gates_Notification::send(
                        $tenant['user_id'],
                        'payment',
                        __('Payment Successful', 'rental-gates'),
                        sprintf(__('Your payment of $%.2f has been processed successfully.', 'rental-gates'), $amount_paid),
                        home_url('/rental-gates/dashboard/payments')
                    );
                }
            }

            Rental_Gates_Logger::info('stripe', 'Payment marked as succeeded via webhook', array('payment_id' => $payment_id));
        } elseif ($session['payment_status'] === 'unpaid') {
            // Payment is pending (async payment method like ACH)
            $wpdb->update(
                $tables['payments'],
                array(
                    'status' => 'processing',
                    'stripe_payment_intent_id' => $session['payment_intent'] ?? '',
                ),
                array('id' => $payment_id)
            );
        }

        return true;
    }

    /**
     * Handle async payment success (for bank transfers, etc.)
     */
    private static function handle_checkout_async_succeeded($session)
    {
        return self::handle_checkout_completed($session);
    }

    /**
     * Handle async payment failure
     */
    private static function handle_checkout_async_failed($session)
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $payment_id = $session['metadata']['payment_id'] ?? null;

        if (!$payment_id) {
            return false;
        }

        // Update payment status
        $wpdb->update(
            $tables['payments'],
            array(
                'status' => 'failed',
                'notes' => __('Async payment failed', 'rental-gates'),
            ),
            array('id' => $payment_id)
        );

        // Notify tenant
        $payment = Rental_Gates_Payment::get_with_details($payment_id);
        if ($payment && $payment['tenant_id']) {
            $tenant = Rental_Gates_Tenant::get($payment['tenant_id']);
            if ($tenant && $tenant['user_id']) {
                Rental_Gates_Notification::send(
                    $tenant['user_id'],
                    'payment',
                    __('Payment Failed', 'rental-gates'),
                    __('Your payment could not be processed. Please try again with a different payment method.', 'rental-gates'),
                    home_url('/rental-gates/dashboard/payments')
                );
            }
        }

        return true;
    }

    /**
     * Handle successful payment intent (legacy/direct)
     */
    private static function handle_payment_succeeded($intent)
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $payment_id = $intent['metadata']['payment_id'] ?? null;

        if (!$payment_id) {
            // Try to find by intent ID
            $payment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tables['payments']} WHERE stripe_payment_intent_id = %s",
                $intent['id']
            ));
        }

        if (!$payment_id) {
            return false;
        }

        // Check if already processed
        $payment = Rental_Gates_Payment::get($payment_id);
        if ($payment && $payment['status'] === 'succeeded') {
            return true; // Already processed
        }

        $amount_paid = $intent['amount_received'] / 100;

        // Get charge ID
        $charge_id = '';
        if (!empty($intent['latest_charge'])) {
            $charge_id = $intent['latest_charge'];
        }

        // Calculate fees
        $stripe_fee = 0;
        if ($charge_id) {
            $charge = self::api_request('charges/' . $charge_id);
            if (!is_wp_error($charge) && isset($charge['balance_transaction'])) {
                $balance = self::api_request('balance_transactions/' . $charge['balance_transaction']);
                if (!is_wp_error($balance)) {
                    $stripe_fee = $balance['fee'] / 100;
                }
            }
        }

        $platform_fee = $amount_paid * (self::PLATFORM_FEE_PERCENT / 100);
        $net_amount = $amount_paid - $platform_fee - $stripe_fee;

        // Update payment
        $wpdb->update(
            $tables['payments'],
            array(
                'status' => 'succeeded',
                'amount_paid' => $amount_paid,
                'stripe_charge_id' => $charge_id,
                'stripe_fee' => $stripe_fee,
                'platform_fee' => $platform_fee,
                'net_amount' => $net_amount,
                'method' => 'stripe_card',
                'paid_at' => current_time('mysql'),
            ),
            array('id' => $payment_id)
        );

        // Send receipt email
        Rental_Gates_Email::send_payment_receipt($payment_id);

        return true;
    }

    /**
     * Handle failed payment
     */
    private static function handle_payment_failed($intent)
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $payment_id = $intent['metadata']['payment_id'] ?? null;

        if (!$payment_id) {
            $payment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tables['payments']} WHERE stripe_payment_intent_id = %s",
                $intent['id']
            ));
        }

        if (!$payment_id) {
            return false;
        }

        $error_message = $intent['last_payment_error']['message'] ?? __('Payment failed', 'rental-gates');

        // Update payment
        $wpdb->update(
            $tables['payments'],
            array(
                'status' => 'failed',
                'notes' => $error_message,
            ),
            array('id' => $payment_id)
        );

        return true;
    }

    /**
     * Handle Connect account update
     */
    private static function handle_account_updated($account)
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $org_id = $account['metadata']['organization_id'] ?? null;

        if (!$org_id) {
            // Find by account ID
            $org_id = $wpdb->get_var($wpdb->prepare(
                "SELECT organization_id FROM {$tables['stripe_accounts']} WHERE stripe_account_id = %s",
                $account['id']
            ));
        }

        if (!$org_id) {
            return false;
        }

        // Refresh the account status
        self::refresh_connected_account($org_id);

        return true;
    }

    /**
     * Handle setup intent succeeded (payment method added)
     */
    private static function handle_setup_intent_succeeded($setup_intent)
    {
        $user_id = $setup_intent['metadata']['wp_user_id'] ?? null;

        if (!$user_id) {
            return false;
        }

        // Get payment method details
        $pm = self::api_request('payment_methods/' . $setup_intent['payment_method']);

        if (is_wp_error($pm)) {
            return false;
        }

        // Save to database
        self::save_payment_method($user_id, $pm);

        return true;
    }

    // =========================================
    // UTILITIES
    // =========================================

    /**
     * Format amount for display
     */
    public static function format_amount($amount, $currency = 'USD')
    {
        $symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'CA$',
            'AUD' => 'A$',
        );

        $symbol = $symbols[strtoupper($currency)] ?? $currency . ' ';

        return $symbol . number_format($amount, 2);
    }

    /**
     * Get card brand icon class
     */
    public static function get_card_brand_icon($brand)
    {
        $brands = array(
            'visa' => 'fab fa-cc-visa',
            'mastercard' => 'fab fa-cc-mastercard',
            'amex' => 'fab fa-cc-amex',
            'discover' => 'fab fa-cc-discover',
            'diners' => 'fab fa-cc-diners-club',
            'jcb' => 'fab fa-cc-jcb',
        );

        return $brands[strtolower($brand)] ?? 'fas fa-credit-card';
    }

    /**
     * Check if payment method is expiring soon
     */
    public static function is_card_expiring($exp_month, $exp_year)
    {
        $exp_date = mktime(0, 0, 0, $exp_month + 1, 1, $exp_year);
        $warning_date = strtotime('+2 months');

        return $exp_date <= $warning_date;
    }

    // =========================================
    // SUBSCRIPTIONS (SaaS Plan Management)
    // =========================================

    /**
     * Create a Stripe Product
     */
    public static function create_product($name, $description = '')
    {
        return self::api_request('products', 'POST', array(
            'name' => $name,
            'description' => $description,
            'metadata' => array(
                'source' => 'rental_gates',
            ),
        ));
    }

    /**
     * Create a recurring Price for a Product
     */
    public static function create_price($product_id, $amount_cents, $currency = 'usd', $interval = 'month')
    {
        return self::api_request('prices', 'POST', array(
            'product' => $product_id,
            'unit_amount' => $amount_cents,
            'currency' => strtolower($currency),
            'recurring' => array(
                'interval' => $interval,
            ),
        ));
    }

    /**
     * Set default payment method for customer in Stripe
     * (Different from set_default_payment_method which updates local DB)
     */
    public static function set_customer_invoice_payment_method($customer_id, $payment_method_id)
    {
        return self::api_request('customers/' . $customer_id, 'POST', array(
            'invoice_settings' => array(
                'default_payment_method' => $payment_method_id,
            ),
        ));
    }

    /**
     * Create a subscription
     */
    public static function create_subscription($customer_id, $price_id, $payment_method_id = null, $metadata = array())
    {
        $data = array(
            'customer' => $customer_id,
            'items' => array(
                array('price' => $price_id),
            ),
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => array(
                'payment_method_types' => array('card'),
                'save_default_payment_method' => 'on_subscription',
            ),
            'expand' => array('latest_invoice.payment_intent'),
        );

        // If payment method provided, set it as default for this subscription
        if (!empty($payment_method_id)) {
            $data['default_payment_method'] = $payment_method_id;
        }

        if (!empty($metadata)) {
            $data['metadata'] = $metadata;
        }

        return self::api_request('subscriptions', 'POST', $data);
    }

    /**
     * Confirm a payment intent
     */
    public static function confirm_payment_intent($payment_intent_id, $payment_method_id = null)
    {
        $data = array();
        if (!empty($payment_method_id)) {
            $data['payment_method'] = $payment_method_id;
        }
        return self::api_request('payment_intents/' . $payment_intent_id . '/confirm', 'POST', $data);
    }

    /**
     * Get payment intent
     */
    public static function get_payment_intent($payment_intent_id)
    {
        return self::api_request('payment_intents/' . $payment_intent_id);
    }

    /**
     * Get subscription
     */
    public static function get_subscription($subscription_id)
    {
        return self::api_request('subscriptions/' . $subscription_id);
    }

    /**
     * Update subscription (e.g., cancel at period end)
     */
    public static function update_subscription($subscription_id, $data)
    {
        return self::api_request('subscriptions/' . $subscription_id, 'POST', $data);
    }

    /**
     * Cancel subscription at period end
     */
    public static function cancel_subscription($subscription_id)
    {
        return self::update_subscription($subscription_id, array(
            'cancel_at_period_end' => 'true',
        ));
    }

    /**
     * Resume a subscription that was set to cancel at period end
     */
    public static function resume_subscription($subscription_id)
    {
        return self::update_subscription($subscription_id, array(
            'cancel_at_period_end' => 'false',
        ));
    }

    /**
     * Cancel subscription immediately
     */
    public static function cancel_subscription_immediately($subscription_id)
    {
        return self::api_request('subscriptions/' . $subscription_id, 'DELETE');
    }

    /**
     * Change subscription plan (upgrade/downgrade)
     * 
     * @param string $subscription_id Stripe subscription ID
     * @param string $new_price_id New Stripe price ID
     * @param string $proration_behavior 'create_prorations', 'none', 'always_invoice'
     * @return array|WP_Error
     */
    public static function change_subscription_plan($subscription_id, $new_price_id, $proration_behavior = 'create_prorations')
    {
        // First, get current subscription to find subscription item ID
        $subscription = self::get_subscription($subscription_id);

        if (is_wp_error($subscription)) {
            return $subscription;
        }

        if (empty($subscription['items']['data'][0]['id'])) {
            return new \WP_Error('no_subscription_item', __('No subscription item found', 'rental-gates'));
        }

        $item_id = $subscription['items']['data'][0]['id'];

        // Update the subscription with the new price
        return self::update_subscription($subscription_id, array(
            'items' => array(
                array(
                    'id' => $item_id,
                    'price' => $new_price_id,
                ),
            ),
            'proration_behavior' => $proration_behavior,
            'expand' => array('latest_invoice.payment_intent'),
        ));
    }

    /**
     * Preview upcoming invoice (for showing proration amounts)
     */
    public static function preview_subscription_change($customer_id, $subscription_id, $new_price_id)
    {
        // Get current subscription item
        $subscription = self::get_subscription($subscription_id);

        if (is_wp_error($subscription)) {
            return $subscription;
        }

        if (empty($subscription['items']['data'][0]['id'])) {
            return new \WP_Error('no_subscription_item', __('No subscription item found', 'rental-gates'));
        }

        $item_id = $subscription['items']['data'][0]['id'];

        return self::api_request('invoices/upcoming', 'GET', array(
            'customer' => $customer_id,
            'subscription' => $subscription_id,
            'subscription_items' => array(
                array(
                    'id' => $item_id,
                    'price' => $new_price_id,
                ),
            ),
            'subscription_proration_behavior' => 'create_prorations',
        ));
    }

    /**
     * Get or create price for a plan
     * 
     * @param string $plan_id Plan identifier
     * @param array $plan Plan configuration with price_monthly and price_yearly
     * @param string $billing_cycle 'monthly' or 'yearly'
     * @return string|WP_Error Price ID or error
     */
    public static function get_or_create_plan_price($plan_id, $plan, $billing_cycle = 'monthly')
    {
        // Use different cache keys for monthly vs yearly prices
        $price_key = 'rental_gates_stripe_price_' . $plan_id . '_' . $billing_cycle;

        // Determine correct amount and interval based on billing cycle
        $is_yearly = $billing_cycle === 'yearly';
        $amount = $is_yearly
            ? floatval($plan['price_yearly'] ?? ($plan['price_monthly'] ?? 0) * 12)
            : floatval($plan['price_monthly'] ?? 0);
        $interval = $is_yearly ? 'year' : 'month';
        $amount_cents = intval($amount * 100);

        // Check if we already have a price ID stored for this billing cycle
        $price_id = get_option($price_key);

        if (!empty($price_id)) {
            // Verify price exists and amount matches
            $price = self::api_request('prices/' . $price_id);
            if (!is_wp_error($price) && !empty($price['active'])) {
                // Check if amount and interval match
                if (
                    $price['unit_amount'] === $amount_cents &&
                    isset($price['recurring']['interval']) &&
                    $price['recurring']['interval'] === $interval
                ) {
                    return $price_id;
                }
            }
        }

        // Create new product and price
        $product_name = 'Rental Gates - ' . ($plan['name'] ?? ucfirst($plan_id));
        if ($is_yearly) {
            $product_name .= ' (Yearly)';
        }

        $product = self::create_product(
            $product_name,
            $plan['description'] ?? ''
        );

        if (is_wp_error($product)) {
            return $product;
        }

        $price = self::create_price($product['id'], $amount_cents, 'usd', $interval);

        if (is_wp_error($price)) {
            return $price;
        }

        // Save price ID for future use
        update_option($price_key, $price['id']);

        return $price['id'];
    }
}
