<?php
/**
 * Owner/PM Payments Management Page
 * 
 * Comprehensive payment tracking with:
 * - Transaction details
 * - Manual payment recording
 * - Fee breakdown
 * - Receipt links
 */
if (!defined('ABSPATH')) exit;

$org_id = Rental_Gates_Roles::get_organization_id();
if (!$org_id) {
    wp_redirect(home_url('/rental-gates/login'));
    exit;
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// Get payments
$args = array(
    'status' => $status_filter ?: null,
    'type' => $type_filter ?: null,
    'search' => $search ?: null,
    'orderby' => 'created_at',
    'order' => 'DESC',
    'limit' => 100,
);

$payments = Rental_Gates_Payment::get_for_organization($org_id, $args);
if (!is_array($payments)) {
    $payments = array();
}
$stats = Rental_Gates_Payment::get_stats($org_id);
if (!is_array($stats)) {
    $stats = array(
        'total_collected' => 0,
        'total_pending' => 0,
        'total_overdue' => 0,
        'count_succeeded' => 0,
        'count_pending' => 0,
        'count_overdue' => 0,
    );
}

// Get active leases for manual payment - using lease_tenants junction table
$tables = Rental_Gates_Database::get_table_names();
global $wpdb;

// First get all active leases
$active_leases = $wpdb->get_results($wpdb->prepare(
    "SELECT l.id, l.rent_amount, l.unit_id, l.organization_id,
            u.name as unit_name, u.name as unit_number,
            b.name as building_name
     FROM {$tables['leases']} l
     LEFT JOIN {$tables['units']} u ON l.unit_id = u.id
     LEFT JOIN {$tables['buildings']} b ON u.building_id = b.id
     WHERE l.organization_id = %d AND l.status = 'active'
     ORDER BY u.name",
    $org_id
), ARRAY_A);

// Now get tenants for each lease
foreach ($active_leases as &$lease) {
    $tenant = $wpdb->get_row($wpdb->prepare(
        "SELECT t.id as tenant_id, t.first_name, t.last_name, t.email as tenant_email
         FROM {$tables['tenants']} t
         JOIN {$tables['lease_tenants']} lt ON t.id = lt.tenant_id
         WHERE lt.lease_id = %d AND lt.removed_at IS NULL
         ORDER BY FIELD(lt.role, 'primary', 'co_tenant', 'occupant'), lt.id ASC
         LIMIT 1",
        $lease['id']
    ), ARRAY_A);
    
    if ($tenant) {
        $lease['tenant_id'] = $tenant['tenant_id'];
        $lease['first_name'] = $tenant['first_name'];
        $lease['last_name'] = $tenant['last_name'];
        $lease['tenant_email'] = $tenant['tenant_email'];
    } else {
        $lease['tenant_id'] = null;
        $lease['first_name'] = '';
        $lease['last_name'] = '';
        $lease['tenant_email'] = '';
    }
}
unset($lease); // Break the reference

// Status config
$status_config = array(
    'pending' => array('label' => __('Pending', 'rental-gates'), 'color' => '#f59e0b', 'bg' => '#fef3c7'),
    'processing' => array('label' => __('Processing', 'rental-gates'), 'color' => '#3b82f6', 'bg' => '#dbeafe'),
    'succeeded' => array('label' => __('Paid', 'rental-gates'), 'color' => '#10b981', 'bg' => '#d1fae5'),
    'partially_paid' => array('label' => __('Partial', 'rental-gates'), 'color' => '#8b5cf6', 'bg' => '#ede9fe'),
    'failed' => array('label' => __('Failed', 'rental-gates'), 'color' => '#ef4444', 'bg' => '#fee2e2'),
    'refunded' => array('label' => __('Refunded', 'rental-gates'), 'color' => '#6b7280', 'bg' => '#f3f4f6'),
    'cancelled' => array('label' => __('Cancelled', 'rental-gates'), 'color' => '#6b7280', 'bg' => '#f3f4f6'),
);

// Payment methods for display
$method_labels = array(
    'stripe' => 'Stripe',
    'stripe_visa' => 'Visa',
    'stripe_mastercard' => 'Mastercard',
    'stripe_amex' => 'Amex',
    'stripe_discover' => 'Discover',
    'stripe_card' => 'Card',
    'cash' => 'Cash',
    'check' => 'Check',
    'bank_transfer' => 'Bank Transfer',
    'money_order' => 'Money Order',
    'other' => 'Other',
);
?>

<!-- Page Header -->
<div class="pm-header">
    <h1><?php _e('Payments', 'rental-gates'); ?></h1>
    <div class="pm-header-actions">
        <button type="button" class="rg-btn rg-btn-primary" onclick="openRecordPaymentModal()">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 5v14M5 12h14"/>
            </svg>
            <?php _e('Record Payment', 'rental-gates'); ?>
        </button>
        <button type="button" class="rg-btn rg-btn-secondary" onclick="openCreatePendingModal()">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                <line x1="1" y1="10" x2="23" y2="10"/>
            </svg>
            <?php _e('Create Charge', 'rental-gates'); ?>
        </button>
        <button type="button" class="rg-btn rg-btn-outline" onclick="openGenerateRentModal()">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
            </svg>
            <?php _e('Generate Rent', 'rental-gates'); ?>
        </button>
    </div>
</div>

<!-- Stats -->
<div class="pm-stats-row">
    <div class="pm-stat-card success">
        <div class="pm-stat-value success">$<?php echo number_format($stats['total_collected'], 2); ?></div>
        <div class="pm-stat-label"><?php _e('Total Collected', 'rental-gates'); ?></div>
        <div class="pm-stat-count"><?php printf(_n('%d payment', '%d payments', $stats['count_succeeded'], 'rental-gates'), $stats['count_succeeded']); ?></div>
    </div>
    <div class="pm-stat-card warning">
        <div class="pm-stat-value warning">$<?php echo number_format($stats['total_pending'], 2); ?></div>
        <div class="pm-stat-label"><?php _e('Pending', 'rental-gates'); ?></div>
        <div class="pm-stat-count"><?php printf(_n('%d payment', '%d payments', $stats['count_pending'], 'rental-gates'), $stats['count_pending']); ?></div>
    </div>
    <div class="pm-stat-card danger">
        <div class="pm-stat-value danger">$<?php echo number_format($stats['total_overdue'], 2); ?></div>
        <div class="pm-stat-label"><?php _e('Overdue', 'rental-gates'); ?></div>
        <div class="pm-stat-count"><?php printf(_n('%d payment', '%d payments', $stats['count_overdue'], 'rental-gates'), $stats['count_overdue']); ?></div>
    </div>
    <div class="pm-stat-card">
        <div class="pm-stat-value"><?php echo count($payments); ?></div>
        <div class="pm-stat-label"><?php _e('Total Records', 'rental-gates'); ?></div>
    </div>
</div>

<!-- Filters -->
<form method="get" class="pm-filters">
    <input type="hidden" name="section" value="payments">
    <div class="pm-search">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
        </svg>
        <input type="text" name="search" placeholder="<?php _e('Search tenant, unit, or payment #...', 'rental-gates'); ?>" value="<?php echo esc_attr($search); ?>">
    </div>
    <select name="status" class="pm-select" onchange="this.form.submit()">
        <option value=""><?php _e('All Status', 'rental-gates'); ?></option>
        <?php foreach ($status_config as $key => $cfg): ?>
            <option value="<?php echo $key; ?>" <?php selected($status_filter, $key); ?>><?php echo $cfg['label']; ?></option>
        <?php endforeach; ?>
    </select>
    <select name="type" class="pm-select" onchange="this.form.submit()">
        <option value=""><?php _e('All Types', 'rental-gates'); ?></option>
        <option value="rent" <?php selected($type_filter, 'rent'); ?>><?php _e('Rent', 'rental-gates'); ?></option>
        <option value="deposit" <?php selected($type_filter, 'deposit'); ?>><?php _e('Deposit', 'rental-gates'); ?></option>
        <option value="late_fee" <?php selected($type_filter, 'late_fee'); ?>><?php _e('Late Fee', 'rental-gates'); ?></option>
        <option value="utility" <?php selected($type_filter, 'utility'); ?>><?php _e('Utility', 'rental-gates'); ?></option>
        <option value="other" <?php selected($type_filter, 'other'); ?>><?php _e('Other', 'rental-gates'); ?></option>
    </select>
    <button type="submit" class="rg-btn rg-btn-secondary"><?php _e('Filter', 'rental-gates'); ?></button>
</form>

<!-- Payments Table -->
<div class="pm-table-container">
    <?php if (empty($payments)): ?>
        <div class="pm-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                <line x1="1" y1="10" x2="23" y2="10"/>
            </svg>
            <p><?php _e('No payments found', 'rental-gates'); ?></p>
        </div>
    <?php else: ?>
        <table class="pm-table">
            <thead>
                <tr>
                    <th><?php _e('Payment', 'rental-gates'); ?></th>
                    <th><?php _e('Tenant', 'rental-gates'); ?></th>
                    <th><?php _e('Amount', 'rental-gates'); ?></th>
                    <th><?php _e('Method', 'rental-gates'); ?></th>
                    <th><?php _e('Status', 'rental-gates'); ?></th>
                    <th><?php _e('Date', 'rental-gates'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): 
                    $status = $status_config[$payment['status']] ?? $status_config['pending'];
                    $is_overdue = in_array($payment['status'], array('pending', 'partially_paid', 'failed')) && $payment['due_date'] && strtotime($payment['due_date']) < time();
                    $method_label = $method_labels[$payment['method']] ?? ucfirst($payment['method'] ?? 'N/A');
                    $initials = strtoupper(substr($payment['tenant_first_name'] ?? 'T', 0, 1) . substr($payment['tenant_last_name'] ?? 'T', 0, 1));
                    
                    // Get Stripe details from meta
                    $meta = is_string($payment['meta_data']) ? json_decode($payment['meta_data'], true) : ($payment['meta_data'] ?: array());
                    $stripe_details = $meta['stripe_details'] ?? array();
                    $card_last4 = $stripe_details['card_last4'] ?? '';
                    $receipt_url = $stripe_details['receipt_url'] ?? '';
                ?>
                    <tr onclick="openPaymentDetail(<?php echo $payment['id']; ?>)" data-payment='<?php echo esc_attr(wp_json_encode($payment)); ?>'>
                        <td>
                            <div class="pm-payment-info">
                                <strong><?php echo esc_html($payment['payment_number']); ?></strong>
                                <span><?php echo esc_html(ucfirst($payment['type'] ?? 'rent')); ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="pm-tenant-info">
                                <div class="pm-tenant-avatar"><?php echo $initials; ?></div>
                                <div class="pm-tenant-details">
                                    <strong><?php echo esc_html(($payment['tenant_first_name'] ?? '') . ' ' . ($payment['tenant_last_name'] ?? '')); ?></strong>
                                    <span><?php echo esc_html(($payment['unit_number'] ?? '') . ' - ' . ($payment['building_name'] ?? '')); ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="pm-amount <?php echo $payment['status'] === 'succeeded' ? 'success' : ($payment['status'] === 'failed' ? 'failed' : 'pending'); ?>">
                                $<?php echo number_format($payment['amount'], 2); ?>
                            </div>
                            <?php if ($payment['amount_paid'] > 0 && $payment['amount_paid'] < $payment['amount']): ?>
                                <div class="pm-partial-paid">
                                    <?php printf(__('Paid: $%s', 'rental-gates'), number_format($payment['amount_paid'], 2)); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="pm-method">
                                <?php if (strpos($payment['method'] ?? '', 'stripe') !== false): ?>
                                    <i class="fab fa-cc-stripe" style="color: #635bff;" aria-hidden="true"></i>
                                <?php elseif ($payment['method'] === 'cash'): ?>
                                    <i class="fas fa-money-bill-wave" style="color: #10b981;" aria-hidden="true"></i>
                                <?php elseif ($payment['method'] === 'check'): ?>
                                    <i class="fas fa-money-check" style="color: #3b82f6;" aria-hidden="true"></i>
                                <?php else: ?>
                                    <i class="fas fa-credit-card" style="color: var(--rg-gray-400);" aria-hidden="true"></i>
                                <?php endif; ?>
                                <?php echo esc_html($method_label); ?>
                                <?php if ($card_last4): ?>
                                    <span style="color: var(--rg-gray-400);">•••• <?php echo esc_html($card_last4); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="pm-status" style="background: <?php echo $status['bg']; ?>; color: <?php echo $status['color']; ?>;">
                                <span class="dot" style="background: <?php echo $status['color']; ?>;"></span>
                                <?php echo $status['label']; ?>
                            </span>
                            <?php if ($is_overdue): ?>
                                <span class="pm-overdue"><?php _e('OVERDUE', 'rental-gates'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="pm-date-display">
                                <?php if ($payment['paid_at']): ?>
                                    <div class="pm-date-primary"><?php echo date_i18n('M j, Y', strtotime($payment['paid_at'])); ?></div>
                                    <div class="pm-date-time"><?php echo date_i18n('g:i a', strtotime($payment['paid_at'])); ?></div>
                                <?php elseif ($payment['due_date']): ?>
                                    <div class="pm-date-due"><?php _e('Due:', 'rental-gates'); ?> <?php echo date_i18n('M j', strtotime($payment['due_date'])); ?></div>
                                <?php else: ?>
                                    <div class="pm-date-empty">—</div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="pm-actions" onclick="event.stopPropagation();">
                                <?php 
                                // Check for internal receipt/invoice
                                $internal_invoice = Rental_Gates_Invoice::get_by_payment($payment['id']);
                                if ($internal_invoice): ?>
                                    <a href="<?php echo home_url('/rental-gates/dashboard/invoice?id=' . $internal_invoice['id']); ?>" title="<?php _e('View Receipt', 'rental-gates'); ?>">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                            <polyline points="14 2 14 8 20 8"/>
                                        </svg>
                                    </a>
                                <?php elseif ($receipt_url): ?>
                                    <a href="<?php echo esc_url($receipt_url); ?>" target="_blank" title="<?php _e('View Stripe Receipt', 'rental-gates'); ?>">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                            <polyline points="14 2 14 8 20 8"/>
                                        </svg>
                                    </a>
                                <?php elseif ($payment['status'] === 'succeeded'): ?>
                                    <button type="button" onclick="generateInvoice(<?php echo $payment['id']; ?>)" title="<?php _e('Generate Receipt', 'rental-gates'); ?>">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M12 5v14M5 12h14"/>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                                <button type="button" onclick="openPaymentDetail(<?php echo $payment['id']; ?>)" title="<?php _e('View Details', 'rental-gates'); ?>">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Record Payment Modal (already paid) -->
<div class="pm-modal-overlay" id="record-payment-modal" role="dialog" aria-modal="true" aria-labelledby="record-payment-title">
    <div class="pm-modal">
        <div class="pm-modal-header">
            <h2 id="record-payment-title"><?php _e('Record Received Payment', 'rental-gates'); ?></h2>
            <button type="button" class="pm-modal-close" onclick="closeModal('record-payment-modal')" aria-label="<?php esc_attr_e('Close', 'rental-gates'); ?>">&times;</button>
        </div>
        <form id="record-payment-form">
            <div class="pm-modal-body">
                <p class="pm-info-callout success">
                    <strong><?php _e('Recording a payment already received', 'rental-gates'); ?></strong> -
                    <?php _e('Use this for cash, check, or other offline payments.', 'rental-gates'); ?>
                </p>
                
                <div class="pm-form-group">
                    <label><?php _e('Tenant / Unit', 'rental-gates'); ?> *</label>
                    <select name="lease_id" id="record-lease-select" required>
                        <option value=""><?php _e('Select tenant...', 'rental-gates'); ?></option>
                        <?php foreach ($active_leases as $lease): 
                            $tenant_name = trim(($lease['first_name'] ?? '') . ' ' . ($lease['last_name'] ?? ''));
                            if (empty($tenant_name)) {
                                $tenant_name = __('(No tenant assigned)', 'rental-gates');
                            }
                            $unit_display = $lease['unit_number'] ?: ($lease['unit_name'] ?: __('Unit', 'rental-gates'));
                            $building_display = $lease['building_name'] ?: __('Building', 'rental-gates');
                            $display_text = sprintf('%s - %s (%s)', $tenant_name, $unit_display, $building_display);
                        ?>
                            <option value="<?php echo esc_attr($lease['id']); ?>" 
                                    data-rent="<?php echo esc_attr($lease['rent_amount'] ?? 0); ?>"
                                    data-tenant="<?php echo esc_attr($tenant_name); ?>"
                                    data-unit="<?php echo esc_attr($unit_display); ?>">
                                <?php echo esc_html($display_text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($active_leases)): ?>
                        <p class="pm-error-text">
                            <?php _e('No active leases found. Please create a lease first.', 'rental-gates'); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="pm-form-row">
                    <div class="pm-form-group">
                        <label><?php _e('Amount Received', 'rental-gates'); ?> *</label>
                        <input type="number" name="amount" id="record-amount" step="0.01" min="0.01" required placeholder="0.00">
                    </div>
                    <div class="pm-form-group">
                        <label><?php _e('Payment Type', 'rental-gates'); ?></label>
                        <select name="type">
                            <option value="rent"><?php _e('Rent', 'rental-gates'); ?></option>
                            <option value="deposit"><?php _e('Security Deposit', 'rental-gates'); ?></option>
                            <option value="late_fee"><?php _e('Late Fee', 'rental-gates'); ?></option>
                            <option value="damage"><?php _e('Damage', 'rental-gates'); ?></option>
                            <option value="other"><?php _e('Other', 'rental-gates'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="pm-form-row">
                    <div class="pm-form-group">
                        <label><?php _e('Payment Method', 'rental-gates'); ?> *</label>
                        <select name="method" required>
                            <option value="cash"><?php _e('Cash', 'rental-gates'); ?></option>
                            <option value="check"><?php _e('Check', 'rental-gates'); ?></option>
                            <option value="money_order"><?php _e('Money Order', 'rental-gates'); ?></option>
                            <option value="external"><?php _e('External Transfer', 'rental-gates'); ?></option>
                            <option value="other"><?php _e('Other', 'rental-gates'); ?></option>
                        </select>
                    </div>
                    <div class="pm-form-group">
                        <label><?php _e('Date Received', 'rental-gates'); ?></label>
                        <input type="date" name="paid_at" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="pm-form-group">
                    <label><?php _e('Reference / Check Number', 'rental-gates'); ?></label>
                    <input type="text" name="reference" placeholder="<?php _e('Optional reference number', 'rental-gates'); ?>">
                </div>
                
                <div class="pm-form-group">
                    <label><?php _e('Notes', 'rental-gates'); ?></label>
                    <textarea name="notes" placeholder="<?php _e('Optional notes about this payment', 'rental-gates'); ?>"></textarea>
                </div>
            </div>
            <div class="pm-modal-footer">
                <button type="button" class="rg-btn rg-btn-secondary" onclick="closeModal('record-payment-modal')"><?php _e('Cancel', 'rental-gates'); ?></button>
                <button type="submit" class="rg-btn rg-btn-primary"><?php _e('Record Payment', 'rental-gates'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Create Pending Payment Modal -->
<div class="pm-modal-overlay" id="create-pending-modal" role="dialog" aria-modal="true" aria-labelledby="create-pending-title">
    <div class="pm-modal">
        <div class="pm-modal-header">
            <h2 id="create-pending-title"><?php _e('Create Pending Charge', 'rental-gates'); ?></h2>
            <button type="button" class="pm-modal-close" onclick="closeModal('create-pending-modal')" aria-label="<?php esc_attr_e('Close', 'rental-gates'); ?>">&times;</button>
        </div>
        <form id="create-pending-form">
            <div class="pm-modal-body">
                <p class="pm-info-callout warning">
                    <strong><?php _e('Creating a charge for tenant to pay', 'rental-gates'); ?></strong> -
                    <?php _e('This will appear in the tenant portal for online payment.', 'rental-gates'); ?>
                </p>
                
                <div class="pm-form-group">
                    <label><?php _e('Tenant / Unit', 'rental-gates'); ?> *</label>
                    <select name="lease_id" id="pending-lease-select" required>
                        <option value=""><?php _e('Select tenant...', 'rental-gates'); ?></option>
                        <?php foreach ($active_leases as $lease): 
                            $tenant_name = trim(($lease['first_name'] ?? '') . ' ' . ($lease['last_name'] ?? ''));
                            if (empty($tenant_name)) {
                                $tenant_name = __('(No tenant assigned)', 'rental-gates');
                            }
                            $unit_display = $lease['unit_number'] ?: ($lease['unit_name'] ?: __('Unit', 'rental-gates'));
                            $building_display = $lease['building_name'] ?: __('Building', 'rental-gates');
                            $display_text = sprintf('%s - %s (%s)', $tenant_name, $unit_display, $building_display);
                        ?>
                            <option value="<?php echo esc_attr($lease['id']); ?>" 
                                    data-rent="<?php echo esc_attr($lease['rent_amount'] ?? 0); ?>"
                                    data-tenant="<?php echo esc_attr($tenant_name); ?>"
                                    data-unit="<?php echo esc_attr($unit_display); ?>"
                                    data-building="<?php echo esc_attr($building_display); ?>">
                                <?php echo esc_html($display_text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($active_leases)): ?>
                        <p class="pm-error-text">
                            <?php _e('No active leases found. Please create a lease first.', 'rental-gates'); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Selected tenant info display -->
                <div id="pending-tenant-info" class="pm-tenant-preview">
                    <div class="pm-tenant-preview-inner">
                        <div id="pending-tenant-avatar" class="pm-tenant-preview-avatar"></div>
                        <div>
                            <div id="pending-tenant-name" class="pm-tenant-preview-name"></div>
                            <div id="pending-tenant-unit" class="pm-tenant-preview-unit"></div>
                        </div>
                    </div>
                </div>
                
                <div class="pm-form-row">
                    <div class="pm-form-group">
                        <label><?php _e('Amount', 'rental-gates'); ?> *</label>
                        <div class="pm-amount-input-wrap">
                            <span class="pm-currency-symbol">$</span>
                            <input type="number" name="amount" id="pending-amount" step="0.01" min="0.01" required placeholder="0.00">
                        </div>
                    </div>
                    <div class="pm-form-group">
                        <label><?php _e('Charge Type', 'rental-gates'); ?></label>
                        <select name="type" id="pending-type">
                            <option value="rent"><?php _e('Rent', 'rental-gates'); ?></option>
                            <option value="deposit"><?php _e('Security Deposit', 'rental-gates'); ?></option>
                            <option value="late_fee"><?php _e('Late Fee', 'rental-gates'); ?></option>
                            <option value="damage"><?php _e('Damage', 'rental-gates'); ?></option>
                            <option value="other"><?php _e('Other', 'rental-gates'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="pm-form-group">
                    <label><?php _e('Due Date', 'rental-gates'); ?></label>
                    <input type="date" name="due_date" id="pending-due-date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                    <p class="pm-helper-text">
                        <?php _e('Leave empty for no specific due date. Defaults to 7 days from now.', 'rental-gates'); ?>
                    </p>
                </div>
                
                <div class="pm-form-group">
                    <label><?php _e('Description', 'rental-gates'); ?></label>
                    <input type="text" name="description" id="pending-description" placeholder="<?php _e('e.g., January 2025 Rent, Security Deposit, Late Fee', 'rental-gates'); ?>">
                </div>
                
                <div class="pm-form-group">
                    <label><?php _e('Notes', 'rental-gates'); ?></label>
                    <textarea name="notes" placeholder="<?php _e('Optional internal notes (not visible to tenant)', 'rental-gates'); ?>" rows="2"></textarea>
                </div>
            </div>
            <div class="pm-modal-footer">
                <button type="button" class="rg-btn rg-btn-secondary" onclick="closeModal('create-pending-modal')"><?php _e('Cancel', 'rental-gates'); ?></button>
                <button type="submit" class="rg-btn rg-btn-primary" id="create-pending-btn"><?php _e('Create Charge', 'rental-gates'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Generate Monthly Rent Modal -->
<div class="pm-modal-overlay" id="generate-rent-modal" role="dialog" aria-modal="true" aria-labelledby="generate-rent-title">
    <div class="pm-modal" style="max-width: 420px;">
        <div class="pm-modal-header">
            <h2 id="generate-rent-title"><?php _e('Generate Monthly Rent Charges', 'rental-gates'); ?></h2>
            <button type="button" class="pm-modal-close" onclick="closeModal('generate-rent-modal')" aria-label="<?php esc_attr_e('Close', 'rental-gates'); ?>">&times;</button>
        </div>
        <form id="generate-rent-form">
            <div class="pm-modal-body">
                <p class="pm-info-callout info">
                    <?php _e('This will create pending rent charges for all active leases for the selected month. Existing charges will be skipped.', 'rental-gates'); ?>
                </p>

                <div class="pm-form-group">
                    <label><?php _e('Month', 'rental-gates'); ?></label>
                    <input type="month" name="for_month" value="<?php echo date('Y-m'); ?>" required>
                </div>

                <div class="pm-generate-summary">
                    <div class="pm-generate-summary-row">
                        <span><?php _e('Active Leases:', 'rental-gates'); ?></span>
                        <strong><?php echo count($active_leases); ?></strong>
                    </div>
                    <div class="pm-generate-summary-hint">
                        <?php _e('Rent charges will be created based on each lease\'s rent amount and due date.', 'rental-gates'); ?>
                    </div>
                </div>
            </div>
            <div class="pm-modal-footer">
                <button type="button" class="rg-btn rg-btn-secondary" onclick="closeModal('generate-rent-modal')"><?php _e('Cancel', 'rental-gates'); ?></button>
                <button type="submit" class="rg-btn rg-btn-primary" id="generate-rent-btn"><?php _e('Generate Charges', 'rental-gates'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Payment Detail Modal -->
<div class="pm-modal-overlay" id="payment-detail-modal" role="dialog" aria-modal="true" aria-labelledby="payment-detail-title">
    <div class="pm-modal">
        <div class="pm-modal-header">
            <h2 id="payment-detail-title"><?php _e('Payment Details', 'rental-gates'); ?></h2>
            <button type="button" class="pm-modal-close" onclick="closeModal('payment-detail-modal')" aria-label="<?php esc_attr_e('Close', 'rental-gates'); ?>">&times;</button>
        </div>
        <div class="pm-modal-body" id="payment-detail-content">
            <!-- Filled by JavaScript -->
        </div>
        <div class="pm-modal-footer">
            <button type="button" class="rg-btn rg-btn-secondary" onclick="closeModal('payment-detail-modal')"><?php _e('Close', 'rental-gates'); ?></button>
        </div>
    </div>
</div>

<script>
// Payment data cache
const paymentsData = <?php echo Rental_Gates_Security::json_for_script(array_column($payments, null, 'id')); ?>;
const statusConfig = <?php echo Rental_Gates_Security::json_for_script($status_config); ?>;
const methodLabels = <?php echo Rental_Gates_Security::json_for_script($method_labels); ?>;
function openRecordPaymentModal() {
    document.getElementById('record-payment-modal').classList.add('active');
    document.getElementById('record-payment-form').reset();
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function openPaymentDetail(paymentId) {
    const payment = paymentsData[paymentId];
    if (!payment) return;
    
    const status = statusConfig[payment.status] || statusConfig.pending;
    const methodLabel = methodLabels[payment.method] || (payment.method || 'N/A');
    const meta = typeof payment.meta_data === 'string' ? JSON.parse(payment.meta_data || '{}') : (payment.meta_data || {});
    const stripeDetails = meta.stripe_details || {};
    
    let html = `
        <div class="pm-detail-row">
            <span class="pm-detail-label"><?php _e('Payment Number', 'rental-gates'); ?></span>
            <span class="pm-detail-value">${payment.payment_number || '—'}</span>
        </div>
        <div class="pm-detail-row">
            <span class="pm-detail-label"><?php _e('Status', 'rental-gates'); ?></span>
            <span class="pm-detail-value">
                <span class="pm-status" style="background: ${status.bg}; color: ${status.color};">
                    <span class="dot" style="background: ${status.color};"></span>
                    ${status.label}
                </span>
            </span>
        </div>
        <div class="pm-detail-row">
            <span class="pm-detail-label"><?php _e('Tenant', 'rental-gates'); ?></span>
            <span class="pm-detail-value">${payment.tenant_first_name || ''} ${payment.tenant_last_name || ''}</span>
        </div>
        <div class="pm-detail-row">
            <span class="pm-detail-label"><?php _e('Unit', 'rental-gates'); ?></span>
            <span class="pm-detail-value">${payment.unit_number || ''} - ${payment.building_name || ''}</span>
        </div>
        <div class="pm-detail-row">
            <span class="pm-detail-label"><?php _e('Type', 'rental-gates'); ?></span>
            <span class="pm-detail-value">${payment.type ? payment.type.charAt(0).toUpperCase() + payment.type.slice(1) : 'Rent'}</span>
        </div>
        <div class="pm-detail-row">
            <span class="pm-detail-label"><?php _e('Method', 'rental-gates'); ?></span>
            <span class="pm-detail-value">${methodLabel}${stripeDetails.card_last4 ? ' •••• ' + stripeDetails.card_last4 : ''}</span>
        </div>
        <div class="pm-detail-row">
            <span class="pm-detail-label"><?php _e('Due Date', 'rental-gates'); ?></span>
            <span class="pm-detail-value">${payment.due_date ? new Date(payment.due_date).toLocaleDateString() : '—'}</span>
        </div>
        <div class="pm-detail-row">
            <span class="pm-detail-label"><?php _e('Paid Date', 'rental-gates'); ?></span>
            <span class="pm-detail-value">${payment.paid_at ? new Date(payment.paid_at).toLocaleString() : '—'}</span>
        </div>
    `;
    
    // Fee breakdown for Stripe payments
    if (payment.stripe_payment_intent_id) {
        const amount = parseFloat(payment.amount) || 0;
        const stripeFee = parseFloat(payment.stripe_fee) || 0;
        const platformFee = parseFloat(payment.platform_fee) || 0;
        const netAmount = parseFloat(payment.net_amount) || (amount - stripeFee - platformFee);
        
        html += `
            <div class="pm-fee-breakdown">
                <h4 style="margin: 0 0 12px; font-size: 14px; font-weight: 600;"><?php _e('Fee Breakdown', 'rental-gates'); ?></h4>
                <div class="pm-fee-row">
                    <span><?php _e('Gross Amount', 'rental-gates'); ?></span>
                    <span>$${amount.toFixed(2)}</span>
                </div>
                <div class="pm-fee-row">
                    <span><?php _e('Stripe Fee', 'rental-gates'); ?></span>
                    <span style="color: #ef4444;">-$${stripeFee.toFixed(2)}</span>
                </div>
                <div class="pm-fee-row">
                    <span><?php _e('Platform Fee (2.5%)', 'rental-gates'); ?></span>
                    <span style="color: #ef4444;">-$${platformFee.toFixed(2)}</span>
                </div>
                <div class="pm-fee-row total">
                    <span><?php _e('Net Amount', 'rental-gates'); ?></span>
                    <span style="color: #10b981;">$${netAmount.toFixed(2)}</span>
                </div>
            </div>
        `;
    }
    
    // Transaction IDs
    if (payment.stripe_payment_intent_id || payment.stripe_charge_id) {
        html += `<div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--rg-gray-200);">
            <h4 style="margin: 0 0 12px; font-size: 14px; font-weight: 600;"><?php _e('Transaction IDs', 'rental-gates'); ?></h4>`;
        
        if (payment.stripe_payment_intent_id) {
            html += `<div class="pm-detail-row">
                <span class="pm-detail-label"><?php _e('Payment Intent', 'rental-gates'); ?></span>
                <span class="pm-detail-value" style="font-family: monospace; font-size: 12px;">${payment.stripe_payment_intent_id}</span>
            </div>`;
        }
        if (payment.stripe_charge_id) {
            html += `<div class="pm-detail-row">
                <span class="pm-detail-label"><?php _e('Charge ID', 'rental-gates'); ?></span>
                <span class="pm-detail-value" style="font-family: monospace; font-size: 12px;">${payment.stripe_charge_id}</span>
            </div>`;
        }
        html += `</div>`;
    }
    
    // Receipt link
    if (stripeDetails.receipt_url) {
        html += `<div style="margin-top: 20px; text-align: center;">
            <a href="${stripeDetails.receipt_url}" target="_blank" class="rg-btn rg-btn-secondary" style="display: inline-flex; align-items: center; gap: 8px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
                <?php _e('View Stripe Receipt', 'rental-gates'); ?>
            </a>
        </div>`;
    }
    
    // Notes
    if (payment.notes) {
        html += `<div style="margin-top: 20px; padding: 12px; background: var(--rg-gray-50); border-radius: 8px;">
            <strong style="font-size: 13px; color: var(--rg-gray-600);"><?php _e('Notes:', 'rental-gates'); ?></strong>
            <p style="margin: 8px 0 0; font-size: 14px; color: var(--rg-gray-700);">${payment.notes}</p>
        </div>`;
    }
    
    document.getElementById('payment-detail-content').innerHTML = html;
    document.getElementById('payment-detail-modal').classList.add('active');
}

// Record payment form submission
document.getElementById('record-payment-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<?php _e('Recording...', 'rental-gates'); ?>';
    
    const formData = new FormData(this);
    formData.append('action', 'rental_gates_record_manual_payment');
    formData.append('nonce', '<?php echo wp_create_nonce('rental_gates_nonce'); ?>');
    
    try {
        const response = await fetch(rentalGatesData.ajaxUrl, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            alert('<?php _e('Payment recorded successfully!', 'rental-gates'); ?>');
            window.location.reload();
        } else {
            alert(result.data?.message || '<?php _e('Failed to record payment', 'rental-gates'); ?>');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('<?php _e('An error occurred', 'rental-gates'); ?>');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

// Create Pending Payment form submission
document.getElementById('create-pending-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<?php _e('Creating...', 'rental-gates'); ?>';
    
    const formData = new FormData(this);
    formData.append('action', 'rental_gates_create_pending_payment');
    formData.append('nonce', '<?php echo wp_create_nonce('rental_gates_nonce'); ?>');
    
    try {
        const response = await fetch(rentalGatesData.ajaxUrl, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            alert('<?php _e('Pending charge created successfully! Tenant will see it in their portal.', 'rental-gates'); ?>');
            window.location.reload();
        } else {
            alert(result.data?.message || '<?php _e('Failed to create charge', 'rental-gates'); ?>');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('<?php _e('An error occurred', 'rental-gates'); ?>');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

// Generate Rent form submission
document.getElementById('generate-rent-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('generate-rent-btn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<?php _e('Generating...', 'rental-gates'); ?>';
    
    const formData = new FormData(this);
    formData.append('action', 'rental_gates_generate_rent_payments');
    formData.append('nonce', '<?php echo wp_create_nonce('rental_gates_nonce'); ?>');
    
    try {
        const response = await fetch(rentalGatesData.ajaxUrl, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            alert(result.data?.message || '<?php _e('Rent charges generated!', 'rental-gates'); ?>');
            window.location.reload();
        } else {
            alert(result.data?.message || '<?php _e('Failed to generate charges', 'rental-gates'); ?>');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('<?php _e('An error occurred', 'rental-gates'); ?>');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

// Modal openers
function openCreatePendingModal() {
    document.getElementById('create-pending-modal').classList.add('active');
    document.getElementById('create-pending-form').reset();
}

function openGenerateRentModal() {
    document.getElementById('generate-rent-modal').classList.add('active');
    document.getElementById('generate-rent-form').reset();
}

// Auto-fill rent amount when lease is selected (Record Payment)
document.getElementById('record-lease-select').addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    const rent = option.dataset.rent;
    if (rent) {
        document.getElementById('record-amount').value = rent;
    }
});

// Auto-fill rent amount when lease is selected (Create Pending)
document.getElementById('pending-lease-select').addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    const rent = option.dataset.rent;
    const tenant = option.dataset.tenant;
    const unit = option.dataset.unit;
    const building = option.dataset.building;
    const infoBox = document.getElementById('pending-tenant-info');
    
    if (this.value && tenant) {
        // Show tenant info
        const initials = tenant.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
        document.getElementById('pending-tenant-avatar').textContent = initials || '?';
        document.getElementById('pending-tenant-name').textContent = tenant;
        document.getElementById('pending-tenant-unit').textContent = unit + ' - ' + building;
        infoBox.classList.add('visible');

        // Auto-fill amount
        if (rent && parseFloat(rent) > 0) {
            document.getElementById('pending-amount').value = rent;
        }
    } else {
        infoBox.classList.remove('visible');
    }
});

// Auto-update description based on type
document.getElementById('pending-type').addEventListener('change', function() {
    const descInput = document.getElementById('pending-description');
    const leaseSelect = document.getElementById('pending-lease-select');
    const selectedOption = leaseSelect.options[leaseSelect.selectedIndex];
    const unit = selectedOption?.dataset?.unit || 'Unit';
    
    const typeLabels = {
        'rent': '<?php _e('Rent', 'rental-gates'); ?>',
        'deposit': '<?php _e('Security Deposit', 'rental-gates'); ?>',
        'late_fee': '<?php _e('Late Fee', 'rental-gates'); ?>',
        'damage': '<?php _e('Damage Charge', 'rental-gates'); ?>',
        'other': '<?php _e('Charge', 'rental-gates'); ?>'
    };
    
    // Only auto-fill if description is empty or matches a previous auto-fill
    if (!descInput.value || descInput.dataset.autoFilled) {
        const now = new Date();
        const monthYear = now.toLocaleString('default', { month: 'long', year: 'numeric' });
        
        if (this.value === 'rent') {
            descInput.value = `${monthYear} ${typeLabels[this.value]} - ${unit}`;
        } else {
            descInput.value = `${typeLabels[this.value]} - ${unit}`;
        }
        descInput.dataset.autoFilled = 'true';
    }
});

// Clear auto-fill flag when user manually edits
document.getElementById('pending-description').addEventListener('input', function() {
    this.dataset.autoFilled = '';
});

// Close modal on backdrop click
document.querySelectorAll('.pm-modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.pm-modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
});

// Generate Invoice/Receipt for a payment
async function generateInvoice(paymentId) {
    if (!confirm('<?php _e('Generate a receipt for this payment?', 'rental-gates'); ?>')) {
        return;
    }
    
    try {
        const response = await fetch(rentalGatesData.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'rental_gates_generate_invoice',
                nonce: '<?php echo wp_create_nonce('rental_gates_nonce'); ?>',
                payment_id: paymentId,
                type: 'receipt'
            })
        });
        
        const result = await response.json();
        
        if (result.success && result.data.invoice) {
            // Redirect to view the invoice
            window.location.href = '<?php echo home_url('/rental-gates/dashboard/invoice?id='); ?>' + result.data.invoice.id;
        } else {
            alert(result.data?.message || '<?php _e('Failed to generate receipt', 'rental-gates'); ?>');
        }
    } catch (error) {
        console.error('Error generating invoice:', error);
        alert('<?php _e('An error occurred', 'rental-gates'); ?>');
    }
}
</script>
