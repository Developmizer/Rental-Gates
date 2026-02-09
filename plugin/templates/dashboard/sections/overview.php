<?php
/**
 * Dashboard Overview Section
 * Comprehensive dashboard with real-time stats, charts, and activity feed
 */
if (!defined('ABSPATH')) exit;

$org_id = Rental_Gates_Roles::get_organization_id();
if (!$org_id) {
    wp_redirect(home_url('/rental-gates/login'));
    exit;
}

// Gather all stats
$org_stats = Rental_Gates_Organization::get_stats($org_id);
$payment_stats = Rental_Gates_Payment::get_stats($org_id, 'month');
$lease_stats = Rental_Gates_Lease::get_stats($org_id);
$maintenance_stats = Rental_Gates_Maintenance::get_stats($org_id);
$vendor_stats = Rental_Gates_Vendor::get_stats($org_id);

// Get enhanced analytics
$period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
$financial_analytics = Rental_Gates_Analytics::get_financial_analytics($org_id, $period);
$occupancy_analytics = Rental_Gates_Analytics::get_occupancy_analytics($org_id);
$revenue_trend = Rental_Gates_Analytics::get_revenue_trend($org_id, 12);
$maintenance_analytics = Rental_Gates_Analytics::get_maintenance_analytics($org_id, $period);

// Get recent/urgent items
$overdue_payments = Rental_Gates_Payment::get_overdue($org_id);
$expiring_leases = Rental_Gates_Lease::get_for_organization($org_id, array(
    'status' => 'active',
    'expiring_days' => 60,
    'per_page' => 5,
));
$open_maintenance = Rental_Gates_Maintenance::get_for_organization($org_id, array(
    'status' => 'open',
    'orderby' => 'priority',
    'order' => 'DESC',
    'per_page' => 5,
));
$recent_applications = Rental_Gates_Application::get_for_organization($org_id, array(
    'status' => 'submitted',
    'per_page' => 5,
));

// Format revenue trend for chart (last 12 months)
$chart_labels = array();
$chart_data = array();
foreach ($revenue_trend as $row) {
    $chart_labels[] = $row['label'];
    $chart_data[] = $row['revenue'];
}

// Priority colors
$priority_colors = array(
    'emergency' => '#dc2626',
    'high' => '#f59e0b',
    'medium' => '#3b82f6',
    'low' => '#6b7280',
);
?>

<!-- Getting Started (show if no buildings) -->
<?php if ($org_stats['buildings'] === 0): ?>
<div class="rg-alert-banner rg-welcome-banner">
    <div>
        <h2 class="rg-welcome-title"><?php _e('Welcome to Rental Gates!', 'rental-gates'); ?></h2>
        <p class="rg-welcome-text">
            <?php _e('Get started by adding your first building. Simply click on the map to drop a pin and set your property location.', 'rental-gates'); ?>
        </p>
    </div>
    <a href="<?php echo home_url('/rental-gates/dashboard/buildings/add'); ?>" class="rg-btn rg-welcome-btn">
        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        <?php _e('Add Your First Building', 'rental-gates'); ?>
    </a>
</div>
<?php endif; ?>

<!-- Alert Banners -->
<?php if (!empty($overdue_payments)): ?>
<div class="rg-alert-banner danger">
    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="12" y1="8" x2="12" y2="12"></line>
        <line x1="12" y1="16" x2="12.01" y2="16"></line>
    </svg>
    <span><strong><?php echo count($overdue_payments); ?></strong> <?php echo _n('payment is overdue', 'payments are overdue', count($overdue_payments), 'rental-gates'); ?> totaling <strong>$<?php echo number_format($payment_stats['total_overdue'], 2); ?></strong></span>
    <a href="<?php echo home_url('/rental-gates/dashboard/payments?status=overdue'); ?>"><?php _e('View All', 'rental-gates'); ?> →</a>
</div>
<?php endif; ?>

<?php if ($lease_stats['expiring_soon'] > 0): ?>
<div class="rg-alert-banner">
    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="rg-text-warning">
        <circle cx="12" cy="12" r="10"></circle>
        <polyline points="12 6 12 12 16 14"></polyline>
    </svg>
    <span><strong><?php echo $lease_stats['expiring_soon']; ?></strong> <?php echo _n('lease expiring', 'leases expiring', $lease_stats['expiring_soon'], 'rental-gates'); ?> in the next 30 days</span>
    <a href="<?php echo home_url('/rental-gates/dashboard/leases?expiring=30'); ?>"><?php _e('Review', 'rental-gates'); ?> →</a>
</div>
<?php endif; ?>

<!-- Key Metrics -->
<div class="rg-dashboard-grid">
    <!-- Monthly Revenue -->
    <div class="rg-metric-card success">
        <div class="rg-metric-header">
            <div class="rg-metric-icon green">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <?php if ($financial_analytics['revenue_growth'] != 0): ?>
            <div class="rg-metric-trend <?php echo $financial_analytics['revenue_growth'] > 0 ? 'up' : 'down'; ?>">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <?php if ($financial_analytics['revenue_growth'] > 0): ?>
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                    <polyline points="17 6 23 6 23 12"></polyline>
                    <?php else: ?>
                    <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline>
                    <polyline points="17 18 23 18 23 12"></polyline>
                    <?php endif; ?>
                </svg>
                <?php echo abs($financial_analytics['revenue_growth']); ?>%
            </div>
            <?php endif; ?>
        </div>
        <div class="rg-metric-value success">$<?php echo number_format($financial_analytics['revenue_collected'], 0); ?></div>
        <div class="rg-metric-label"><?php printf(__('Revenue (%s)', 'rental-gates'), ucfirst($period)); ?></div>
    </div>
    
    <!-- Occupancy Rate -->
    <div class="rg-metric-card <?php echo ($occupancy_analytics['occupancy_rate'] >= 90) ? 'success' : (($occupancy_analytics['occupancy_rate'] >= 70) ? '' : 'warning'); ?>">
        <div class="rg-metric-header">
            <div class="rg-metric-icon <?php echo ($occupancy_analytics['occupancy_rate'] >= 90) ? 'green' : (($occupancy_analytics['occupancy_rate'] >= 70) ? 'blue' : 'yellow'); ?>">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
            </div>
        </div>
        <div class="rg-metric-value"><?php echo number_format($occupancy_analytics['occupancy_rate'], 1); ?>%</div>
        <div class="rg-metric-label"><?php _e('Occupancy Rate', 'rental-gates'); ?></div>
        <div class="rg-metric-sub-info">
            <?php printf(__('%d of %d units', 'rental-gates'), $occupancy_analytics['occupied'], $occupancy_analytics['total_units']); ?>
        </div>
    </div>
    
    <!-- Open Maintenance -->
    <div class="rg-metric-card <?php echo ($maintenance_stats['open'] + $maintenance_stats['in_progress'] > 10) ? 'warning' : ''; ?>">
        <div class="rg-metric-header">
            <div class="rg-metric-icon <?php echo ($maintenance_stats['emergency'] > 0) ? 'red' : 'yellow'; ?>">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                </svg>
            </div>
        </div>
        <div class="rg-metric-value <?php echo ($maintenance_stats['emergency'] > 0) ? 'danger' : ''; ?>"><?php echo $maintenance_stats['open'] + $maintenance_stats['in_progress']; ?></div>
        <div class="rg-metric-label"><?php _e('Open Work Orders', 'rental-gates'); ?></div>
        <?php if ($maintenance_stats['emergency'] > 0): ?>
            <div class="rg-metric-trend down">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <?php printf(__('%d emergency', 'rental-gates'), $maintenance_stats['emergency']); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Pending Payments -->
    <div class="rg-metric-card <?php echo ($payment_stats['count_overdue'] > 0) ? 'danger' : ''; ?>">
        <div class="rg-metric-header">
            <div class="rg-metric-icon <?php echo ($payment_stats['count_overdue'] > 0) ? 'red' : 'purple'; ?>">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                </svg>
            </div>
        </div>
        <div class="rg-metric-value <?php echo ($payment_stats['count_overdue'] > 0) ? 'danger' : ''; ?>">$<?php echo number_format($payment_stats['total_pending'], 0); ?></div>
        <div class="rg-metric-label"><?php _e('Pending Payments', 'rental-gates'); ?></div>
        <?php if ($payment_stats['count_overdue'] > 0): ?>
            <div class="rg-metric-trend down">
                <?php printf(__('%d overdue', 'rental-gates'), $payment_stats['count_overdue']); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Revenue Chart & Unit Breakdown -->
<div class="rg-two-col">
    <!-- Revenue Chart -->
    <div class="rg-card">
        <div class="rg-card-header">
            <h2 class="rg-card-title"><?php _e('Revenue Trend', 'rental-gates'); ?></h2>
            <div class="rg-card-header-actions">
                <select id="periodSelector" class="rg-form-select compact">
                    <option value="last_7_days" <?php selected($period, 'last_7_days'); ?>><?php _e('Last 7 Days', 'rental-gates'); ?></option>
                    <option value="last_30_days" <?php selected($period, 'last_30_days'); ?>><?php _e('Last 30 Days', 'rental-gates'); ?></option>
                    <option value="month" <?php selected($period, 'month'); ?>><?php _e('This Month', 'rental-gates'); ?></option>
                    <option value="quarter" <?php selected($period, 'quarter'); ?>><?php _e('This Quarter', 'rental-gates'); ?></option>
                    <option value="year" <?php selected($period, 'year'); ?>><?php _e('This Year', 'rental-gates'); ?></option>
                </select>
            </div>
        </div>
        <div class="rg-card-body">
            <div class="rg-chart-container">
                <canvas id="revenueChart" aria-label="<?php esc_attr_e('Revenue trend chart', 'rental-gates'); ?>" role="img"></canvas>
            </div>
        </div>
        <div class="rg-card-footer start">
            <button onclick="exportChart('revenueChart', 'revenue-trend')" class="rg-btn rg-btn-outline rg-btn-sm">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <?php _e('Export', 'rental-gates'); ?>
            </button>
        </div>
    </div>

    <!-- Unit Status Breakdown -->
    <div class="rg-card">
        <div class="rg-card-header">
            <h2 class="rg-card-title"><?php _e('Unit Status', 'rental-gates'); ?></h2>
            <a href="<?php echo home_url('/rental-gates/dashboard/buildings'); ?>" class="rg-card-link"><?php _e('View All', 'rental-gates'); ?></a>
        </div>
        <div class="rg-card-body">
            <?php
            $units = $org_stats['units'];
            $total_units = max(1, $units['total']);
            ?>
            <div class="rg-donut-chart">
                <svg class="rg-donut-svg" viewBox="0 0 36 36" role="img" aria-label="<?php printf(esc_attr__('Unit status: %d occupied, %d available, %d coming soon, %d unlisted', 'rental-gates'), $units['occupied'], $units['available'], $units['coming_soon'], $units['unlisted']); ?>">
                    <?php
                    $segments = array(
                        array('value' => $units['occupied'], 'color' => '#2563eb', 'label' => 'Occupied'),
                        array('value' => $units['available'], 'color' => '#10b981', 'label' => 'Available'),
                        array('value' => $units['coming_soon'], 'color' => '#f59e0b', 'label' => 'Coming Soon'),
                        array('value' => $units['unlisted'], 'color' => '#6b7280', 'label' => 'Unlisted'),
                    );
                    $offset = 25;
                    foreach ($segments as $seg):
                        if ($seg['value'] <= 0) continue;
                        $pct = ($seg['value'] / $total_units) * 100;
                    ?>
                        <circle cx="18" cy="18" r="15.91549430918954" fill="transparent" stroke="<?php echo $seg['color']; ?>" stroke-width="3" stroke-dasharray="<?php echo $pct; ?> <?php echo 100 - $pct; ?>" stroke-dashoffset="<?php echo $offset; ?>"></circle>
                    <?php
                        $offset -= $pct;
                    endforeach;
                    ?>
                    <text x="18" y="18" text-anchor="middle" dy=".3em" style="font-size: 8px; font-weight: 700; fill: var(--rg-gray-900);"><?php echo $units['total']; ?></text>
                    <text x="18" y="22" text-anchor="middle" style="font-size: 3px; fill: var(--rg-gray-500);">units</text>
                </svg>
                <div class="rg-donut-legend">
                    <div class="rg-legend-item">
                        <span class="rg-legend-dot occupied"></span>
                        <span><?php _e('Occupied', 'rental-gates'); ?></span>
                        <span class="rg-legend-value"><?php echo $units['occupied']; ?></span>
                    </div>
                    <div class="rg-legend-item">
                        <span class="rg-legend-dot available"></span>
                        <span><?php _e('Available', 'rental-gates'); ?></span>
                        <span class="rg-legend-value"><?php echo $units['available']; ?></span>
                    </div>
                    <div class="rg-legend-item">
                        <span class="rg-legend-dot coming-soon"></span>
                        <span><?php _e('Coming Soon', 'rental-gates'); ?></span>
                        <span class="rg-legend-value"><?php echo $units['coming_soon']; ?></span>
                    </div>
                    <div class="rg-legend-item">
                        <span class="rg-legend-dot unlisted"></span>
                        <span><?php _e('Unlisted', 'rental-gates'); ?></span>
                        <span class="rg-legend-value"><?php echo $units['unlisted']; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Revenue by Building (full-width, outside two-col) -->
<?php if (!empty($financial_analytics['revenue_by_building'])): ?>
<div class="rg-card rg-section-gap">
    <div class="rg-card-header">
        <h2 class="rg-card-title"><?php _e('Revenue by Building', 'rental-gates'); ?></h2>
    </div>
    <div class="rg-card-body">
        <div class="rg-chart-container h-250">
            <canvas id="revenueByBuildingChart" aria-label="<?php esc_attr_e('Revenue by building chart', 'rental-gates'); ?>" role="img"></canvas>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Three Column: Maintenance, Applications, Leases -->
<div class="rg-three-col">
    <!-- Open Maintenance -->
    <div class="rg-card">
        <div class="rg-card-header">
            <h2 class="rg-card-title"><?php _e('Open Work Orders', 'rental-gates'); ?></h2>
            <a href="<?php echo home_url('/rental-gates/dashboard/maintenance'); ?>" class="rg-card-link"><?php _e('View All', 'rental-gates'); ?></a>
        </div>
        <div class="rg-card-body flush">
            <?php if (empty($open_maintenance)): ?>
                <div class="rg-empty-state">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <p><?php _e('No open work orders', 'rental-gates'); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($open_maintenance as $wo): ?>
                    <div class="rg-list-item">
                        <div class="rg-list-item-main">
                            <div class="rg-list-item-title">
                                <span class="rg-priority-dot" style="background: <?php echo $priority_colors[$wo['priority']] ?? '#6b7280'; ?>;"></span>
                                <a href="<?php echo home_url('/rental-gates/dashboard/maintenance/' . $wo['id']); ?>"><?php echo esc_html($wo['title']); ?></a>
                            </div>
                            <div class="rg-list-item-meta">
                                <?php echo esc_html($wo['building_name'] ?? ''); ?>
                                <?php if ($wo['unit_name']): ?> • <?php echo esc_html($wo['unit_name']); ?><?php endif; ?>
                            </div>
                        </div>
                        <span class="rg-badge <?php echo ($wo['priority'] === 'emergency') ? 'red' : (($wo['priority'] === 'high') ? 'yellow' : 'gray'); ?>">
                            <?php echo ucfirst($wo['priority']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Pending Applications -->
    <div class="rg-card">
        <div class="rg-card-header">
            <h2 class="rg-card-title"><?php _e('Pending Applications', 'rental-gates'); ?></h2>
            <a href="<?php echo home_url('/rental-gates/dashboard/applications'); ?>" class="rg-card-link"><?php _e('View All', 'rental-gates'); ?></a>
        </div>
        <div class="rg-card-body flush">
            <?php if (empty($recent_applications['items'])): ?>
                <div class="rg-empty-state">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                    </svg>
                    <p><?php _e('No pending applications', 'rental-gates'); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_applications['items'] as $app): ?>
                    <div class="rg-list-item">
                        <div class="rg-list-item-main">
                            <div class="rg-list-item-title">
                                <a href="<?php echo home_url('/rental-gates/dashboard/applications/' . $app['id']); ?>"><?php echo esc_html($app['applicant_name']); ?></a>
                            </div>
                            <div class="rg-list-item-meta">
                                <?php echo esc_html($app['unit_name'] ?? 'Unit'); ?> • <?php echo date_i18n('M j', strtotime($app['submitted_at'] ?? $app['created_at'])); ?>
                            </div>
                        </div>
                        <span class="rg-badge blue"><?php _e('Review', 'rental-gates'); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Expiring Leases -->
    <div class="rg-card">
        <div class="rg-card-header">
            <h2 class="rg-card-title"><?php _e('Expiring Soon', 'rental-gates'); ?></h2>
            <a href="<?php echo home_url('/rental-gates/dashboard/leases'); ?>" class="rg-card-link"><?php _e('View All', 'rental-gates'); ?></a>
        </div>
        <div class="rg-card-body flush">
            <?php if (empty($expiring_leases['items'])): ?>
                <div class="rg-empty-state">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <p><?php _e('No leases expiring soon', 'rental-gates'); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($expiring_leases['items'] as $lease): 
                    $days_left = ceil((strtotime($lease['end_date']) - time()) / 86400);
                ?>
                    <div class="rg-list-item">
                        <div class="rg-list-item-main">
                            <div class="rg-list-item-title">
                                <a href="<?php echo home_url('/rental-gates/dashboard/leases/' . $lease['id']); ?>"><?php echo esc_html($lease['tenant_name'] ?? 'Lease #' . $lease['id']); ?></a>
                            </div>
                            <div class="rg-list-item-meta">
                                <?php echo esc_html($lease['unit_name'] ?? ''); ?> • <?php echo date_i18n('M j, Y', strtotime($lease['end_date'])); ?>
                            </div>
                        </div>
                        <span class="rg-badge <?php echo ($days_left <= 14) ? 'red' : (($days_left <= 30) ? 'yellow' : 'gray'); ?>">
                            <?php echo $days_left; ?>d
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="rg-card rg-section-gap">
    <div class="rg-card-header">
        <h2 class="rg-card-title"><?php _e('Quick Actions', 'rental-gates'); ?></h2>
    </div>
    <div class="rg-card-body">
        <div class="rg-quick-actions">
            <a href="<?php echo home_url('/rental-gates/dashboard/buildings/add'); ?>" class="rg-quick-action">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <span><?php _e('Add Building', 'rental-gates'); ?></span>
            </a>
            <a href="<?php echo home_url('/rental-gates/dashboard/tenants/add'); ?>" class="rg-quick-action">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="8.5" cy="7" r="4"></circle>
                    <line x1="20" y1="8" x2="20" y2="14"></line>
                    <line x1="23" y1="11" x2="17" y2="11"></line>
                </svg>
                <span><?php _e('Add Tenant', 'rental-gates'); ?></span>
            </a>
            <a href="<?php echo home_url('/rental-gates/dashboard/maintenance/add'); ?>" class="rg-quick-action">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                </svg>
                <span><?php _e('Work Order', 'rental-gates'); ?></span>
            </a>
            <a href="<?php echo home_url('/rental-gates/dashboard/payments/add'); ?>" class="rg-quick-action">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
                <span><?php _e('Record Payment', 'rental-gates'); ?></span>
            </a>
        </div>
    </div>
</div>

<!-- Portfolio Summary (if multiple buildings) -->
<?php if ($org_stats['buildings'] > 0): ?>
<div class="rg-card rg-mt-6">
    <div class="rg-card-header">
        <h2 class="rg-card-title"><?php _e('Portfolio Summary', 'rental-gates'); ?></h2>
    </div>
    <div class="rg-card-body">
        <div class="rg-portfolio-stats">
            <div>
                <div class="rg-portfolio-stat-value"><?php echo $org_stats['buildings']; ?></div>
                <div class="rg-portfolio-stat-label"><?php _e('Buildings', 'rental-gates'); ?></div>
            </div>
            <div>
                <div class="rg-portfolio-stat-value"><?php echo $units['total']; ?></div>
                <div class="rg-portfolio-stat-label"><?php _e('Units', 'rental-gates'); ?></div>
            </div>
            <div>
                <div class="rg-portfolio-stat-value"><?php echo $org_stats['tenants']; ?></div>
                <div class="rg-portfolio-stat-label"><?php _e('Tenants', 'rental-gates'); ?></div>
            </div>
            <div>
                <div class="rg-portfolio-stat-value"><?php echo $lease_stats['active']; ?></div>
                <div class="rg-portfolio-stat-label"><?php _e('Active Leases', 'rental-gates'); ?></div>
            </div>
            <div>
                <div class="rg-portfolio-stat-value success">$<?php echo number_format($lease_stats['monthly_revenue'], 0); ?></div>
                <div class="rg-portfolio-stat-label"><?php _e('Monthly Revenue', 'rental-gates'); ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('revenueChart');
    if (!ctx || typeof Chart === 'undefined') return;
    
    const chartData = {
        labels: <?php echo Rental_Gates_Security::json_for_script($chart_labels); ?>,
        datasets: [{
            label: '<?php echo esc_js(__('Revenue', 'rental-gates')); ?>',
            data: <?php echo Rental_Gates_Security::json_for_script($chart_data); ?>,
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            borderColor: 'rgb(37, 99, 235)',
            borderWidth: 2,
            borderRadius: 6,
            fill: true,
            tension: 0.4,
        }]
    };
    
    const revenueChart = new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index',
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: { size: 14, weight: '600' },
                    bodyFont: { size: 13 },
                    callbacks: {
                        label: function(context) {
                            return '$' + context.raw.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        },
                        font: { size: 11 }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: { size: 11 }
                    }
                }
            }
        }
    });
    
    // Period selector
    const periodSelector = document.getElementById('periodSelector');
    if (periodSelector) {
        periodSelector.addEventListener('change', function() {
            const period = this.value;
            window.location.href = window.location.pathname + '?period=' + period;
        });
    }
    
    // Revenue by Building Chart
    <?php if (!empty($financial_analytics['revenue_by_building'])): ?>
    const buildingCtx = document.getElementById('revenueByBuildingChart');
    if (buildingCtx) {
        const buildingData = <?php echo Rental_Gates_Security::json_for_script($financial_analytics['revenue_by_building']); ?>;
        new Chart(buildingCtx, {
            type: 'bar',
            data: {
                labels: buildingData.map(b => b.building_name || '<?php _e('Unnamed Building', 'rental-gates'); ?>'),
                datasets: [{
                    label: '<?php echo esc_js(__('Revenue Collected', 'rental-gates')); ?>',
                    data: buildingData.map(b => parseFloat(b.collected)),
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: 'rgb(16, 185, 129)',
                    borderWidth: 1,
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '$' + context.raw.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>
});

// Export chart function
function exportChart(canvasId, filename) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    
    const url = canvas.toDataURL('image/png');
    const link = document.createElement('a');
    link.download = filename + '-' + new Date().toISOString().split('T')[0] + '.png';
    link.href = url;
    link.click();
}
</script>
