<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles Stripe Webhooks
 */
class Rental_Gates_Webhook_Handler
{

    /**
     * Handle incoming webhook request
     */
    public static function handle_request($request)
    {
        $payload = $request->get_body();
        $sig_header = $request->get_header('stripe-signature');

        // Get webhook secret (try encrypted first, then legacy)
        $webhook_secret = '';
        $encrypted_secret = get_option('rental_gates_stripe_webhook_secret_encrypted', '');
        if (!empty($encrypted_secret)) {
            $webhook_secret = Rental_Gates_Security::decrypt($encrypted_secret);
        }
        if (empty($webhook_secret)) {
            $webhook_secret = get_option('rental_gates_stripe_webhook_secret', '');
            // Auto-migrate if found
            if (!empty($webhook_secret)) {
                Rental_Gates_Stripe::store_webhook_secret($webhook_secret);
            }
        }

        // SECURITY: Webhook secret MUST be configured
        if (empty($webhook_secret)) {
            Rental_Gates_Security::log_security_event('webhook_unconfigured', array(
                'ip' => Rental_Gates_Security::get_client_ip(),
            ));
            return new WP_REST_Response(
                array('error' => 'Webhook secret not configured'),
                500
            );
        }

        // SECURITY: Signature header MUST be present
        if (empty($sig_header)) {
            Rental_Gates_Security::log_security_event('webhook_missing_signature', array(
                'ip' => Rental_Gates_Security::get_client_ip(),
            ));
            return new WP_REST_Response(array('error' => 'Missing signature'), 400);
        }

        // SECURITY: Stripe SDK MUST be available for signature verification
        if (!class_exists('Stripe\\Webhook')) {
            Rental_Gates_Security::log_security_event('webhook_sdk_missing', array(
                'ip' => Rental_Gates_Security::get_client_ip(),
            ));
            // FAIL CLOSED - never process unverified webhooks
            return new WP_REST_Response(
                array('error' => 'Stripe SDK not available for signature verification'),
                500
            );
        }

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $webhook_secret
            );
        } catch (\UnexpectedValueException $e) {
            return new WP_REST_Response(array('error' => 'Invalid payload'), 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Rental_Gates_Security::log_security_event('webhook_invalid_signature', array(
                'ip' => Rental_Gates_Security::get_client_ip(),
            ));
            return new WP_REST_Response(array('error' => 'Invalid signature'), 400);
        }

        // Idempotency: check if we've already processed this event
        if (self::is_event_processed($event->id)) {
            return new WP_REST_Response(array('received' => true, 'duplicate' => true), 200);
        }

        // Handle specific events
        switch ($event->type) {
            case 'invoice.paid':
                self::handle_invoice_paid($event->data->object);
                break;
            case 'invoice.payment_failed':
                self::handle_invoice_payment_failed($event->data->object);
                break;
            case 'customer.subscription.updated':
                self::handle_subscription_updated($event->data->object);
                break;
            case 'customer.subscription.deleted':
                self::handle_subscription_deleted($event->data->object);
                break;
        }

        // Mark event as processed
        self::mark_event_processed($event->id, $event->type);

        return new WP_REST_Response(array('received' => true), 200);
    }

    /**
     * Check if a webhook event has already been processed (idempotency).
     */
    private static function is_event_processed($event_id)
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['activity_log']}
             WHERE action = 'webhook_processed'
             AND new_values LIKE %s
             AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            '%' . $wpdb->esc_like($event_id) . '%'
        ));
    }

    /**
     * Record that a webhook event was processed.
     */
    private static function mark_event_processed($event_id, $event_type)
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $wpdb->insert(
            $tables['activity_log'],
            array(
                'user_id'     => 0,
                'action'      => 'webhook_processed',
                'entity_type' => 'stripe_webhook',
                'new_values'  => wp_json_encode(array(
                    'event_id'   => $event_id,
                    'event_type' => $event_type,
                )),
                'ip_address'  => Rental_Gates_Security::get_client_ip(),
                'created_at'  => current_time('mysql'),
            )
        );
    }

    /**
     * Handle invoice.paid
     */
    private static function handle_invoice_paid($invoice)
    {
        if (empty($invoice->subscription))
            return;

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        // Find subscription by stripe ID
        $sub = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['subscriptions']} WHERE stripe_subscription_id = %s",
            $invoice->subscription
        ));

        if (!$sub)
            return;

        // Update status to active if it was past due
        if ($sub->status !== 'active') {
            $wpdb->update(
                $tables['subscriptions'],
                array('status' => 'active'),
                array('id' => $sub->id),
                array('%s'),
                array('%d')
            );
        }

        // Create Invoice Record
        if (class_exists('Rental_Gates_Subscription_Invoice')) {
            // Get Plan
            $plan_id = $sub->plan_id;
            $plans = rg_get_all_plans();
            $plan = isset($plans[$plan_id]) ? $plans[$plan_id] : array('name' => 'Unknown Plan', 'price_monthly' => 0);

            // Prepare Subscription Array
            $sub_array = (array) $sub;

            // Prepare Invoice Array (convert from object)
            $invoice_array = json_decode(json_encode($invoice), true);

            Rental_Gates_Subscription_Invoice::create_from_subscription(
                $sub->organization_id,
                $sub_array,
                $plan,
                $invoice_array
            );
        }
    }

    /**
     * Handle invoice.payment_failed
     */
    private static function handle_invoice_payment_failed($invoice)
    {
        if (empty($invoice->subscription))
            return;

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        // Find subscription
        $sub = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['subscriptions']} WHERE stripe_subscription_id = %s",
            $invoice->subscription
        ));

        if (!$sub)
            return;

        // Update status to past_due
        $wpdb->update(
            $tables['subscriptions'],
            array('status' => 'past_due'),
            array('id' => $sub->id),
            array('%s'),
            array('%d')
        );

        // TODO: Send email notification to user
    }

    /**
     * Handle customer.subscription.updated
     */
    private static function handle_subscription_updated($subscription)
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $sub_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['subscriptions']} WHERE stripe_subscription_id = %s",
            $subscription->id
        ));

        if (!$sub_record)
            return;

        // Determine billing cycle from Stripe subscription
        // Stripe stores interval in various locations depending on API version
        $billing_cycle = null;
        
        // Path 1: Direct plan object (older API responses)
        if (isset($subscription->plan->interval)) {
            $billing_cycle = ($subscription->plan->interval === 'year') ? 'yearly' : 'monthly';
        }
        // Path 2: items.data[0].plan.interval (subscription items)
        elseif (isset($subscription->items->data[0]->plan->interval)) {
            $billing_cycle = ($subscription->items->data[0]->plan->interval === 'year') ? 'yearly' : 'monthly';
        }
        // Path 3: items.data[0].price.recurring.interval (price-based)
        elseif (isset($subscription->items->data[0]->price->recurring->interval)) {
            $billing_cycle = ($subscription->items->data[0]->price->recurring->interval === 'year') ? 'yearly' : 'monthly';
        }

        // Get amount from Stripe - try multiple paths
        $amount = null;
        
        // Path 1: Direct plan amount
        if (isset($subscription->plan->amount)) {
            $amount = $subscription->plan->amount / 100;
        }
        // Path 2: items.data[0].plan.amount
        elseif (isset($subscription->items->data[0]->plan->amount)) {
            $amount = $subscription->items->data[0]->plan->amount / 100;
        }
        // Path 3: items.data[0].price.unit_amount
        elseif (isset($subscription->items->data[0]->price->unit_amount)) {
            $amount = $subscription->items->data[0]->price->unit_amount / 100;
        }

        // Sync local data
        $update_data = array(
            'status' => $subscription->status,
            'current_period_start' => date('Y-m-d H:i:s', $subscription->current_period_start),
            'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
            'cancel_at_period_end' => $subscription->cancel_at_period_end ? 1 : 0,
            'updated_at' => current_time('mysql'),
        );

        // Only update billing_cycle if we determined it from Stripe
        if ($billing_cycle !== null) {
            $update_data['billing_cycle'] = $billing_cycle;
        }

        // Only update amount if we got it from Stripe
        if ($amount !== null) {
            $update_data['amount'] = $amount;
        }

        // Handle trial end date
        if (!empty($subscription->trial_end)) {
            $update_data['trial_end'] = date('Y-m-d H:i:s', $subscription->trial_end);
        }

        $wpdb->update(
            $tables['subscriptions'],
            $update_data,
            array('id' => $sub_record->id)
        );

        // Log for debugging
        error_log(sprintf(
            'Rental Gates Webhook - Subscription updated: stripe_id=%s, status=%s, billing_cycle=%s',
            $subscription->id,
            $subscription->status,
            $billing_cycle ?? 'not_determined'
        ));
    }

    /**
     * Handle customer.subscription.deleted
     */
    private static function handle_subscription_deleted($subscription)
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $sub_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['subscriptions']} WHERE stripe_subscription_id = %s",
            $subscription->id
        ));

        if (!$sub_record)
            return;

        // Mark as cancelled
        $wpdb->update(
            $tables['subscriptions'],
            array(
                'status' => 'cancelled',
                'cancelled_at' => current_time('mysql'),
                'cancel_at_period_end' => 0
            ),
            array('id' => $sub_record->id)
        );

        // Revert org to free plan
        Rental_Gates_Billing::update_org_plan($sub_record->organization_id, 'free');
    }
}
