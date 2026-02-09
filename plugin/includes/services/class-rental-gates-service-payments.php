<?php
/**
 * Payment Service
 *
 * Shared business logic for payment operations, called by both
 * AJAX and REST handlers. Eliminates behavioral drift between the two APIs.
 *
 * Key fixes applied:
 * - amount_paid auto-fill for succeeded payments (was only in AJAX)
 * - Notification hooks fire from both APIs
 *
 * @package RentalGates
 * @since 2.42.0
 */

if (!defined('ABSPATH')) exit;

class Rental_Gates_Service_Payments {

    /**
     * Create a payment with unified business logic.
     *
     * @param array $data   Payment data
     * @param int   $org_id Organization ID
     * @return array|WP_Error Created payment or error
     */
    public static function create($data, $org_id) {
        $clean = array(
            'organization_id' => $org_id,
            'lease_id'        => intval($data['lease_id'] ?? 0),
            'tenant_id'       => intval($data['tenant_id'] ?? 0),
            'amount'          => floatval($data['amount'] ?? 0),
            'type'            => sanitize_text_field($data['type'] ?? 'rent'),
            'method'          => sanitize_text_field($data['method'] ?? 'other'),
            'status'          => sanitize_text_field($data['status'] ?? 'pending'),
            'due_date'        => sanitize_text_field($data['due_date'] ?? ''),
            'period_start'    => sanitize_text_field($data['period_start'] ?? ''),
            'period_end'      => sanitize_text_field($data['period_end'] ?? ''),
            'description'     => sanitize_textarea_field($data['description'] ?? ''),
            'notes'           => sanitize_textarea_field($data['notes'] ?? ''),
        );

        // Business logic: auto-fill amount_paid and paid_at for succeeded payments
        // (Previously only in AJAX handler, missing from REST)
        if ($clean['status'] === 'succeeded') {
            $clean['amount_paid'] = $clean['amount'];
            $clean['paid_at'] = current_time('mysql');
        }

        $result = Rental_Gates_Payment::create($clean);

        if (!is_wp_error($result)) {
            do_action('rental_gates_payment_created', $result, $org_id);
        }

        return $result;
    }

    /**
     * Update a payment with unified business logic.
     *
     * @param int   $payment_id Payment ID
     * @param array $data       Updated fields
     * @param int   $org_id     Organization ID
     * @return array|WP_Error
     */
    public static function update($payment_id, $data, $org_id) {
        // Auto-fill amount_paid when marking as succeeded
        if (isset($data['status']) && $data['status'] === 'succeeded') {
            if (empty($data['amount_paid'])) {
                $existing = Rental_Gates_Payment::get($payment_id);
                if ($existing) {
                    $data['amount_paid'] = $existing['amount'];
                }
            }
            if (empty($data['paid_at'])) {
                $data['paid_at'] = current_time('mysql');
            }
        }

        $result = Rental_Gates_Payment::update($payment_id, $data);

        if (!is_wp_error($result)) {
            do_action('rental_gates_payment_received', $result, $org_id);
        }

        return $result;
    }

    /**
     * Refund a payment.
     *
     * @param int    $payment_id Payment ID
     * @param float  $amount     Refund amount
     * @param string $reason     Reason for refund
     * @param int    $org_id     Organization ID
     * @return array|WP_Error
     */
    public static function refund($payment_id, $amount, $reason, $org_id) {
        $result = Rental_Gates_Payment::refund($payment_id, $amount, $reason);

        if (!is_wp_error($result)) {
            do_action('rental_gates_payment_refunded', $result, $org_id);
        }

        return $result;
    }
}
