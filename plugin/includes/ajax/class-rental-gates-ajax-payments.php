<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handlers for Payments
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: create_payment, update_payment, delete_payment, mark_payment_paid,
 *          cancel_payment, record_manual_payment, sync_payment,
 *          generate_rent_payments, create_pending_payment,
 *          download_invoice, get_invoice, generate_invoice
 */
class Rental_Gates_Ajax_Payments {

    public function __construct() {
        add_action('wp_ajax_rental_gates_create_payment', array($this, 'handle_create_payment'));
        add_action('wp_ajax_rental_gates_update_payment', array($this, 'handle_update_payment'));
        add_action('wp_ajax_rental_gates_delete_payment', array($this, 'handle_delete_payment'));
        add_action('wp_ajax_rental_gates_mark_payment_paid', array($this, 'handle_mark_payment_paid'));
        add_action('wp_ajax_rental_gates_cancel_payment', array($this, 'handle_cancel_payment'));
        add_action('wp_ajax_rental_gates_record_manual_payment', array($this, 'handle_record_manual_payment'));
        add_action('wp_ajax_rental_gates_sync_payment', array($this, 'handle_sync_payment'));
        add_action('wp_ajax_rental_gates_generate_rent_payments', array($this, 'handle_generate_rent_payments'));
        add_action('wp_ajax_rental_gates_create_pending_payment', array($this, 'handle_create_pending_payment'));
        add_action('wp_ajax_rental_gates_download_invoice', array($this, 'handle_download_invoice'));
        add_action('wp_ajax_rental_gates_get_invoice', array($this, 'handle_get_invoice'));
        add_action('wp_ajax_rental_gates_generate_invoice', array($this, 'handle_generate_invoice'));
    }

    private function get_org_id() {
        return Rental_Gates_Roles::get_organization_id();
    }

    private function verify_nonce() {
        check_ajax_referer('rental_gates_nonce', 'nonce');
    }

    /**
     * Handle create payment AJAX request
     */
    public function handle_create_payment()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_payments') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(__('No organization found', 'rental-gates'));
        }

        $data = array(
            'organization_id' => $org_id,
            'lease_id' => intval($_POST['lease_id'] ?? 0),
            'tenant_id' => intval($_POST['tenant_id'] ?? 0),
            'amount' => floatval($_POST['amount'] ?? 0),
            'type' => sanitize_text_field($_POST['type'] ?? 'rent'),
            'status' => sanitize_text_field($_POST['status'] ?? 'pending'),
            'due_date' => sanitize_text_field($_POST['due_date'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        $result = Rental_Gates_Payment::create($data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle update payment AJAX request
     */
    public function handle_update_payment()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_payments') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $payment_id = intval($_POST['payment_id'] ?? 0);
        if (!$payment_id) {
            wp_send_json_error(__('Invalid payment ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $payment = Rental_Gates_Payment::get($payment_id);

        if (!$payment || $payment['organization_id'] != $org_id) {
            wp_send_json_error(__('Payment not found or access denied', 'rental-gates'));
        }

        $data = array(
            'amount' => floatval($_POST['amount'] ?? $payment['amount']),
            'status' => sanitize_text_field($_POST['status'] ?? $payment['status']),
            'due_date' => sanitize_text_field($_POST['due_date'] ?? $payment['due_date']),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? $payment['notes']),
        );

        $result = Rental_Gates_Payment::update($payment_id, $data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle delete payment AJAX request
     */
    public function handle_delete_payment()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_payments') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $payment_id = intval($_POST['payment_id'] ?? 0);
        if (!$payment_id) {
            wp_send_json_error(__('Invalid payment ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $payment = Rental_Gates_Payment::get($payment_id);

        if (!$payment || $payment['organization_id'] != $org_id) {
            wp_send_json_error(__('Payment not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Payment::delete($payment_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('deleted' => true));
    }

    /**
     * Handle mark payment as paid
     */
    public function handle_mark_payment_paid()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_payments') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $payment_id = intval($_POST['payment_id'] ?? 0);
        if (!$payment_id) {
            wp_send_json_error(__('Invalid payment ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $payment = Rental_Gates_Payment::get($payment_id);

        if (!$payment || $payment['organization_id'] != $org_id) {
            wp_send_json_error(__('Payment not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Payment::mark_paid($payment_id, array(
            'method' => sanitize_text_field($_POST['method'] ?? 'manual'),
            'reference' => sanitize_text_field($_POST['reference'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        ));

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle cancel payment
     */
    public function handle_cancel_payment()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_payments') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $payment_id = intval($_POST['payment_id'] ?? 0);
        if (!$payment_id) {
            wp_send_json_error(__('Invalid payment ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $payment = Rental_Gates_Payment::get($payment_id);

        if (!$payment || $payment['organization_id'] != $org_id) {
            wp_send_json_error(__('Payment not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Payment::cancel($payment_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('cancelled' => true));
    }

    /**
     * Handle manual payment recording
     */
    public function handle_record_manual_payment()
    {
        try {
            check_ajax_referer('rental_gates_nonce', 'nonce');

            if (!is_user_logged_in() || !current_user_can('rg_manage_payments')) {
                wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
            }

            $org_id = Rental_Gates_Roles::get_organization_id();
            if (!$org_id) {
                wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
            }

            // Validate inputs
            $lease_id = intval($_POST['lease_id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            $type = sanitize_text_field($_POST['type'] ?? 'rent');
            $method = sanitize_text_field($_POST['method'] ?? 'cash');
            $paid_at = sanitize_text_field($_POST['paid_at'] ?? '');
            $reference = sanitize_text_field($_POST['reference'] ?? '');
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');
            $due_date = sanitize_text_field($_POST['due_date'] ?? '');

            if (!$lease_id || $amount <= 0) {
                wp_send_json_error(array('message' => __('Please select a tenant and enter a valid amount', 'rental-gates')));
            }

            // Get lease with details including tenants
            $lease = Rental_Gates_Lease::get_with_details($lease_id);
            if (!$lease || $lease['organization_id'] != $org_id) {
                wp_send_json_error(array('message' => __('Lease not found or access denied', 'rental-gates')));
            }

            // Get primary tenant from lease_tenants
            $tenant_id = null;
            if (!empty($lease['tenants']) && is_array($lease['tenants'])) {
                // Find primary tenant first (role field from lease_tenants)
                foreach ($lease['tenants'] as $t) {
                    if (isset($t['role']) && $t['role'] === 'primary') {
                        $tenant_id = $t['tenant_id'] ?? $t['id'] ?? null;
                        break;
                    }
                }
                // If no primary, use first tenant
                if (!$tenant_id && isset($lease['tenants'][0])) {
                    $tenant_id = $lease['tenants'][0]['tenant_id'] ?? $lease['tenants'][0]['id'] ?? null;
                }
            }

            if (!$tenant_id) {
                wp_send_json_error(array('message' => __('No tenant found for this lease. Please add a tenant to the lease first.', 'rental-gates')));
            }

            // Create payment record
            $payment_data = array(
                'organization_id' => $org_id,
                'lease_id' => $lease_id,
                'tenant_id' => $tenant_id,
                'amount' => $amount,
                'amount_paid' => $amount,
                'type' => $type,
                'status' => 'succeeded',
                'method' => $method,
                'currency' => 'USD',
                'due_date' => $due_date ?: null,
                'paid_at' => $paid_at ? $paid_at . ' ' . current_time('H:i:s') : current_time('mysql'),
                'notes' => $notes,
                'net_amount' => $amount, // No fees for manual payments
                'description' => sprintf(
                    __('%s payment for %s', 'rental-gates'),
                    ucfirst($type),
                    $lease['unit_name'] ?? $lease['unit_number'] ?? 'Unit'
                ),
                'meta_data' => json_encode(array(
                    'manual_entry' => true,
                    'recorded_by' => get_current_user_id(),
                    'recorded_at' => current_time('mysql'),
                    'reference' => $reference,
                )),
            );

            $result = Rental_Gates_Payment::create($payment_data);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }

            // Clear cache
            if (class_exists('Rental_Gates_Cache')) {
                Rental_Gates_Cache::delete_stats($org_id, 'payments');
            }

            // Get payment ID
            $payment_id = is_array($result) ? $result['id'] : $result;

            // Generate receipt automatically for manual payments
            if (class_exists('Rental_Gates_Invoice')) {
                try {
                    Rental_Gates_Invoice::create_from_payment($payment_id, 'receipt');
                } catch (Exception $e) {
                    Rental_Gates_Logger::error('payments', 'Receipt generation failed', array('payment_id' => $payment_id, 'exception' => $e->getMessage()));
                }
            }

            // Notify tenant
            $tenant = Rental_Gates_Tenant::get($tenant_id);
            if ($tenant && !empty($tenant['user_id'])) {
                Rental_Gates_Notification::send(
                    $tenant['user_id'],
                    'payment',
                    __('Payment Recorded', 'rental-gates'),
                    sprintf(__('A payment of $%.2f has been recorded for your account.', 'rental-gates'), $amount),
                    home_url('/rental-gates/tenant/payments')
                );
            }

            wp_send_json_success(array(
                'message' => __('Payment recorded successfully', 'rental-gates'),
                'payment_id' => $payment_id,
            ));
        } catch (Exception $e) {
            Rental_Gates_Logger::error('payments', 'Record manual payment failed', array('exception' => $e->getMessage()));
            wp_send_json_error(array('message' => __('An error occurred while recording the payment', 'rental-gates')));
        }
    }

    /**
     * Handle payment sync from Stripe session
     */
    public function handle_sync_payment()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not logged in', 'rental-gates')));
        }

        $session_id = sanitize_text_field($_POST['session_id'] ?? '');

        if (empty($session_id)) {
            wp_send_json_error(array('message' => __('Session ID required', 'rental-gates')));
        }

        $result = Rental_Gates_Stripe::sync_payment_from_session($session_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handle generating monthly rent payments for all active leases
     */
    public function handle_generate_rent_payments()
    {
        try {
            check_ajax_referer('rental_gates_nonce', 'nonce');

            if (!is_user_logged_in() || !current_user_can('rg_manage_payments')) {
                wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
            }

            $org_id = Rental_Gates_Roles::get_organization_id();
            if (!$org_id) {
                wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
            }

            $for_month = sanitize_text_field($_POST['for_month'] ?? '');
            if (!$for_month) {
                $for_month = date('Y-m');
            }

            $result = Rental_Gates_Payment::generate_monthly_payments($org_id, $for_month);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }

            // Clear cache
            if (class_exists('Rental_Gates_Cache')) {
                Rental_Gates_Cache::delete_stats($org_id, 'payments');
            }

            wp_send_json_success(array(
                'message' => sprintf(
                    __('Generated %d payments, skipped %d (already exist)', 'rental-gates'),
                    $result['generated'],
                    $result['skipped']
                ),
                'generated' => $result['generated'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'] ?? array(),
            ));
        } catch (Exception $e) {
            Rental_Gates_Logger::error('payments', 'Generate rent payments failed', array('exception' => $e->getMessage()));
            wp_send_json_error(array('message' => __('An error occurred while generating payments', 'rental-gates')));
        }
    }

    /**
     * Handle creating a single pending payment for a lease
     */
    public function handle_create_pending_payment()
    {
        try {
            check_ajax_referer('rental_gates_nonce', 'nonce');

            if (!is_user_logged_in() || !current_user_can('rg_manage_payments')) {
                wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
            }

            $org_id = Rental_Gates_Roles::get_organization_id();
            if (!$org_id) {
                wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
            }

            $lease_id = intval($_POST['lease_id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            $type = sanitize_text_field($_POST['type'] ?? 'rent');
            $due_date = sanitize_text_field($_POST['due_date'] ?? '');
            $description = sanitize_text_field($_POST['description'] ?? '');
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');

            if (!$lease_id) {
                wp_send_json_error(array('message' => __('Please select a tenant/lease', 'rental-gates')));
            }

            // Get lease details
            $lease = Rental_Gates_Lease::get_with_details($lease_id);
            if (!$lease || $lease['organization_id'] != $org_id) {
                wp_send_json_error(array('message' => __('Lease not found', 'rental-gates')));
            }

            // Use rent amount if not specified
            if ($amount <= 0) {
                $amount = floatval($lease['rent_amount']);
            }

            // Get primary tenant from lease_tenants
            $tenant_id = null;
            if (!empty($lease['tenants']) && is_array($lease['tenants'])) {
                foreach ($lease['tenants'] as $t) {
                    // Check for primary role (role field from lease_tenants)
                    if (isset($t['role']) && $t['role'] === 'primary') {
                        $tenant_id = $t['tenant_id'] ?? $t['id'] ?? null;
                        break;
                    }
                }
                // If no primary found, use first tenant
                if (!$tenant_id && isset($lease['tenants'][0])) {
                    $tenant_id = $lease['tenants'][0]['tenant_id'] ?? $lease['tenants'][0]['id'] ?? null;
                }
            }

            if (!$tenant_id) {
                wp_send_json_error(array('message' => __('No tenant found for this lease. Please add a tenant to the lease first.', 'rental-gates')));
            }

            // Calculate period if due_date provided
            $period_start = null;
            $period_end = null;
            if ($due_date && $type === 'rent') {
                $period_start = date('Y-m-01', strtotime($due_date));
                $period_end = date('Y-m-t', strtotime($due_date));
            }

            // Create payment
            $payment_data = array(
                'organization_id' => $org_id,
                'lease_id' => $lease_id,
                'tenant_id' => $tenant_id,
                'amount' => $amount,
                'type' => $type,
                'status' => 'pending',
                'due_date' => $due_date ?: null,
                'period_start' => $period_start,
                'period_end' => $period_end,
                'notes' => $notes,
                'description' => $description ?: sprintf(
                    __('%s for %s', 'rental-gates'),
                    ucfirst($type),
                    $lease['unit_name'] ?? 'Unit'
                ),
            );

            $result = Rental_Gates_Payment::create($payment_data);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }

            // Clear cache
            if (class_exists('Rental_Gates_Cache')) {
                Rental_Gates_Cache::delete_stats($org_id, 'payments');
            }

            // Notify tenant about pending payment
            $tenant = Rental_Gates_Tenant::get($tenant_id);
            if ($tenant && !empty($tenant['user_id'])) {
                Rental_Gates_Notification::send(
                    $tenant['user_id'],
                    'payment',
                    __('Payment Due', 'rental-gates'),
                    sprintf(
                        __('A payment of $%.2f is due%s.', 'rental-gates'),
                        $amount,
                        $due_date ? ' on ' . date_i18n('F j, Y', strtotime($due_date)) : ''
                    ),
                    home_url('/rental-gates/tenant/payments')
                );
            }

            $payment_id = is_array($result) ? $result['id'] : $result;

            wp_send_json_success(array(
                'message' => __('Payment created successfully', 'rental-gates'),
                'payment_id' => $payment_id,
            ));
        } catch (Exception $e) {
            Rental_Gates_Logger::error('payments', 'Create pending payment failed', array('exception' => $e->getMessage()));
            wp_send_json_error(array('message' => __('An error occurred while creating the payment', 'rental-gates')));
        }
    }

    /**
     * Handle download invoice request
     */
    public function handle_download_invoice()
    {
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'download_invoice')) {
            wp_die(__('Security check failed', 'rental-gates'));
        }

        if (!is_user_logged_in()) {
            wp_die(__('Please log in', 'rental-gates'));
        }

        $invoice_id = intval($_GET['id'] ?? 0);
        $format = sanitize_text_field($_GET['format'] ?? 'pdf');

        $invoice = Rental_Gates_Invoice::get($invoice_id);
        if (!$invoice) {
            wp_die(__('Invoice not found', 'rental-gates'));
        }

        // Security check
        $current_user_id = get_current_user_id();
        $has_access = false;

        $user_org_id = Rental_Gates_Roles::get_organization_id();
        if ($user_org_id && $user_org_id == $invoice['organization_id']) {
            $has_access = true;
        }

        if (!$has_access && $invoice['tenant_id']) {
            $tenant = Rental_Gates_Tenant::get($invoice['tenant_id']);
            if ($tenant && $tenant['user_id'] == $current_user_id) {
                $has_access = true;
            }
        }

        if (!$has_access) {
            wp_die(__('Access denied', 'rental-gates'));
        }

        $html = $invoice['html_content'] ?: Rental_Gates_Invoice::generate_html($invoice);
        $filename = ($invoice['type'] === 'receipt' ? 'Receipt' : 'Invoice') . '-' . $invoice['invoice_number'];

        if ($format === 'html') {
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.html"');
            echo $html;
            exit;
        }

        // PDF generation using wkhtmltopdf or browser print
        if ($format === 'pdf') {
            // Try wkhtmltopdf if available
            $wkhtmltopdf = '/usr/bin/wkhtmltopdf';
            if (file_exists($wkhtmltopdf)) {
                $temp_html = tempnam(sys_get_temp_dir(), 'invoice_') . '.html';
                $temp_pdf = tempnam(sys_get_temp_dir(), 'invoice_') . '.pdf';

                file_put_contents($temp_html, $html);

                $cmd = escapeshellcmd($wkhtmltopdf) . ' --page-size A4 --margin-top 10mm --margin-bottom 10mm --margin-left 10mm --margin-right 10mm ' .
                    escapeshellarg($temp_html) . ' ' . escapeshellarg($temp_pdf) . ' 2>&1';
                exec($cmd, $output, $return);

                if ($return === 0 && file_exists($temp_pdf)) {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
                    readfile($temp_pdf);
                    unlink($temp_html);
                    unlink($temp_pdf);
                    exit;
                }

                @unlink($temp_html);
                @unlink($temp_pdf);
            }

            // Fallback: serve HTML with print instructions
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><title>' . esc_html($filename) . '</title>';
            echo '<style>body{font-family:sans-serif;padding:20px;}</style>';
            echo '</head><body>';
            echo '<div style="background:#fef3c7;border:1px solid #f59e0b;padding:16px;border-radius:8px;margin-bottom:20px;">';
            echo '<strong>' . __('PDF Generation', 'rental-gates') . '</strong><br>';
            echo __('Press Ctrl+P (or Cmd+P on Mac) and select "Save as PDF" to download this document as PDF.', 'rental-gates');
            echo '</div>';
            echo $html;
            echo '<script>window.print();</script>';
            echo '</body></html>';
            exit;
        }

        wp_die(__('Invalid format', 'rental-gates'));
    }

    /**
     * Handle get invoice AJAX request
     */
    public function handle_get_invoice()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
        }

        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        $payment_id = intval($_POST['payment_id'] ?? 0);

        $invoice = null;
        if ($invoice_id) {
            $invoice = Rental_Gates_Invoice::get($invoice_id);
        } elseif ($payment_id) {
            $invoice = Rental_Gates_Invoice::get_by_payment($payment_id);
        }

        if (!$invoice) {
            wp_send_json_error(array('message' => __('Invoice not found', 'rental-gates')));
        }

        // Security check
        $current_user_id = get_current_user_id();
        $has_access = false;

        $user_org_id = Rental_Gates_Roles::get_organization_id();
        if ($user_org_id && $user_org_id == $invoice['organization_id']) {
            $has_access = true;
        }

        if (!$has_access && $invoice['tenant_id']) {
            $tenant = Rental_Gates_Tenant::get($invoice['tenant_id']);
            if ($tenant && $tenant['user_id'] == $current_user_id) {
                $has_access = true;
            }
        }

        if (!$has_access) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        wp_send_json_success(array(
            'invoice' => $invoice,
            'view_url' => home_url('/rental-gates/dashboard/invoice?id=' . $invoice['id']),
        ));
    }

    /**
     * Handle generate invoice AJAX request
     */
    public function handle_generate_invoice()
    {
        try {
            check_ajax_referer('rental_gates_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
            }

            $payment_id = intval($_POST['payment_id'] ?? 0);
            $type = sanitize_text_field($_POST['type'] ?? 'invoice');

            if (!$payment_id) {
                wp_send_json_error(array('message' => __('Payment ID required', 'rental-gates')));
            }

            // Verify access
            $payment = Rental_Gates_Payment::get($payment_id);
            if (!$payment) {
                wp_send_json_error(array('message' => __('Payment not found', 'rental-gates')));
            }

            $user_org_id = Rental_Gates_Roles::get_organization_id();
            if (!$user_org_id || $user_org_id != $payment['organization_id']) {
                wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
            }

            // Check if invoice already exists
            $existing = Rental_Gates_Invoice::get_by_payment($payment_id);
            if ($existing) {
                wp_send_json_success(array(
                    'invoice' => $existing,
                    'message' => __('Invoice already exists', 'rental-gates'),
                ));
                return;
            }

            // Generate invoice
            $invoice = Rental_Gates_Invoice::create_from_payment($payment_id, $type);

            if (is_wp_error($invoice)) {
                wp_send_json_error(array('message' => $invoice->get_error_message()));
            }

            wp_send_json_success(array(
                'invoice' => $invoice,
                'message' => __('Invoice generated successfully', 'rental-gates'),
            ));
        } catch (Exception $e) {
            Rental_Gates_Logger::error('payments', 'Generate invoice failed', array('exception' => $e->getMessage()));
            wp_send_json_error(array('message' => __('An error occurred while generating the invoice', 'rental-gates')));
        }
    }
}
