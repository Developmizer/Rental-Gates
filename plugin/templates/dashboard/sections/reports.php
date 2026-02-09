<?php
/**
 * Reports Section
 * 
 * Comprehensive reporting for property managers including:
 * - Financial Reports (Revenue, Collections, Overdue)
 * - Occupancy Reports (Vacancy, Turnover, Expirations)
 * - Maintenance Reports (Work Orders, Response Times, Costs)
 */
if (!defined('ABSPATH'))
    exit;

$org_id = $organization['id'] ?? 0;
if (!$org_id) {
    // Try to get org_id from roles if not in organization array
    $org_id = Rental_Gates_Roles::get_organization_id();
}

if (!$org_id) {
    echo '<div class="rg-empty-state"><p>' . __('Organization not found.', 'rental-gates') . '</p></div>';
    return;
}

global $wpdb;
$tables = Rental_Gates_Database::get_table_names();

// Get date range from query params (default: current month)
$period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));

// Calculate date range based on period
switch ($period) {
    case 'year':
        $start_date = "$year-01-01";
        $end_date = "$year-12-31";
        $period_label = $year;
        break;
    case 'quarter':
        $quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : ceil($month / 3);
        $start_month = (($quarter - 1) * 3) + 1;
        $end_month = $quarter * 3;
        $start_date = "$year-" . str_pad($start_month, 2, '0', STR_PAD_LEFT) . "-01";
        $end_date = date('Y-m-t', strtotime("$year-" . str_pad($end_month, 2, '0', STR_PAD_LEFT) . "-01"));
        $period_label = "Q$quarter $year";
        break;
    case 'month':
    default:
        $start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $end_date = date('Y-m-t', strtotime($start_date));
        $period_label = date_i18n('F Y', strtotime($start_date));
        break;
}

// Current report tab
$report_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'financial';

// Get enhanced analytics
$financial_analytics = Rental_Gates_Analytics::get_financial_analytics($org_id, $period, $start_date, $end_date);
$occupancy_analytics = Rental_Gates_Analytics::get_occupancy_analytics($org_id);
$maintenance_analytics = Rental_Gates_Analytics::get_maintenance_analytics($org_id, $period);

// ==========================================
// FINANCIAL DATA
// ==========================================

// Total revenue collected in period
$revenue_collected = $wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(amount_paid), 0) FROM {$tables['payments']} 
     WHERE organization_id = %d AND status = 'succeeded' 
     AND paid_at BETWEEN %s AND %s",
    $org_id,
    $start_date,
    $end_date . ' 23:59:59'
));

// Total billed in period
$total_billed = $wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM {$tables['payments']} 
     WHERE organization_id = %d 
     AND due_date BETWEEN %s AND %s",
    $org_id,
    $start_date,
    $end_date
));

// Collection rate
$collection_rate = $total_billed > 0 ? ($revenue_collected / $total_billed) * 100 : 0;

// Outstanding balance (all time)
$outstanding_balance = $wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(amount - amount_paid), 0) FROM {$tables['payments']} 
     WHERE organization_id = %d AND status IN ('pending', 'partially_paid')",
    $org_id
));

// Overdue amount
$overdue_amount = $wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(amount - amount_paid), 0) FROM {$tables['payments']} 
     WHERE organization_id = %d AND status IN ('pending', 'partially_paid') 
     AND due_date < CURDATE()",
    $org_id
));

// Revenue by building
$revenue_by_building = $wpdb->get_results($wpdb->prepare(
    "SELECT b.name as building_name, b.id as building_id,
            COALESCE(SUM(p.amount_paid), 0) as collected,
            COALESCE(SUM(p.amount), 0) as billed
     FROM {$tables['buildings']} b
     LEFT JOIN {$tables['units']} u ON b.id = u.building_id
     LEFT JOIN {$tables['leases']} l ON u.id = l.unit_id AND l.status = 'active'
     LEFT JOIN {$tables['payments']} p ON l.id = p.lease_id 
         AND p.due_date BETWEEN %s AND %s
     WHERE b.organization_id = %d
     GROUP BY b.id, b.name
     ORDER BY collected DESC",
    $start_date,
    $end_date,
    $org_id
), ARRAY_A);

// Monthly revenue trend (last 6 months)
$revenue_trend = $wpdb->get_results($wpdb->prepare(
    "SELECT DATE_FORMAT(paid_at, '%%Y-%%m') as month,
            SUM(amount_paid) as revenue
     FROM {$tables['payments']}
     WHERE organization_id = %d AND status = 'succeeded'
     AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(paid_at, '%%Y-%%m')
     ORDER BY month ASC",
    $org_id
), ARRAY_A);

// Revenue by type
$revenue_by_type = $wpdb->get_results($wpdb->prepare(
    "SELECT COALESCE(type, 'rent') as payment_type,
            SUM(amount_paid) as collected
     FROM {$tables['payments']}
     WHERE organization_id = %d AND status = 'succeeded'
     AND paid_at BETWEEN %s AND %s
     GROUP BY COALESCE(type, 'rent')
     ORDER BY collected DESC",
    $org_id,
    $start_date,
    $end_date . ' 23:59:59'
), ARRAY_A);

// ==========================================
// OCCUPANCY DATA
// ==========================================

// Total units
$total_units = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tables['units']} u
     JOIN {$tables['buildings']} b ON u.building_id = b.id
     WHERE b.organization_id = %d",
    $org_id
));

// Occupied units (units with active leases)
$occupied_units = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT u.id) FROM {$tables['units']} u
     JOIN {$tables['buildings']} b ON u.building_id = b.id
     JOIN {$tables['leases']} l ON u.id = l.unit_id AND l.status = 'active'
     WHERE b.organization_id = %d",
    $org_id
));

// Vacancy rate
$vacancy_rate = $total_units > 0 ? (($total_units - $occupied_units) / $total_units) * 100 : 0;
$occupancy_rate = 100 - $vacancy_rate;

// Units by status
$units_by_status = $wpdb->get_results($wpdb->prepare(
    "SELECT u.availability as status, COUNT(*) as count
     FROM {$tables['units']} u
     JOIN {$tables['buildings']} b ON u.building_id = b.id
     WHERE b.organization_id = %d
     GROUP BY u.availability",
    $org_id
), ARRAY_A);

// Leases expiring soon (next 90 days)
$expiring_leases = $wpdb->get_results($wpdb->prepare(
    "SELECT l.*, u.name as unit_name, b.name as building_name,
            DATEDIFF(l.end_date, CURDATE()) as days_remaining
     FROM {$tables['leases']} l
     JOIN {$tables['units']} u ON l.unit_id = u.id
     JOIN {$tables['buildings']} b ON u.building_id = b.id
     WHERE l.organization_id = %d AND l.status = 'active'
     AND l.end_date IS NOT NULL
     AND l.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
     ORDER BY l.end_date ASC
     LIMIT 10",
    $org_id
), ARRAY_A);

// Occupancy by building
$occupancy_by_building = $wpdb->get_results($wpdb->prepare(
    "SELECT b.name as building_name, b.id as building_id,
            COUNT(DISTINCT u.id) as total_units,
            COUNT(DISTINCT CASE WHEN l.id IS NOT NULL THEN u.id END) as occupied_units
     FROM {$tables['buildings']} b
     LEFT JOIN {$tables['units']} u ON b.id = u.building_id
     LEFT JOIN {$tables['leases']} l ON u.id = l.unit_id AND l.status = 'active'
     WHERE b.organization_id = %d
     GROUP BY b.id, b.name
     ORDER BY b.name",
    $org_id
), ARRAY_A);

// New leases in period
$new_leases = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tables['leases']}
     WHERE organization_id = %d AND start_date BETWEEN %s AND %s",
    $org_id,
    $start_date,
    $end_date
));

// Ended leases in period
$ended_leases = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tables['leases']}
     WHERE organization_id = %d AND status = 'ended'
     AND end_date BETWEEN %s AND %s",
    $org_id,
    $start_date,
    $end_date
));

// ==========================================
// MAINTENANCE DATA
// ==========================================

// Work orders in period
$total_work_orders = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tables['work_orders']}
     WHERE organization_id = %d AND created_at BETWEEN %s AND %s",
    $org_id,
    $start_date,
    $end_date . ' 23:59:59'
));

// Open work orders (all time)
$open_work_orders = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tables['work_orders']}
     WHERE organization_id = %d AND status IN ('open', 'assigned', 'in_progress')",
    $org_id
));

// Completed work orders in period
$completed_work_orders = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tables['work_orders']}
     WHERE organization_id = %d AND status = 'completed'
     AND completed_at BETWEEN %s AND %s",
    $org_id,
    $start_date,
    $end_date . ' 23:59:59'
));

// Average resolution time (days)
$avg_resolution_time = $wpdb->get_var($wpdb->prepare(
    "SELECT AVG(DATEDIFF(completed_at, created_at))
     FROM {$tables['work_orders']}
     WHERE organization_id = %d AND status = 'completed'
     AND completed_at BETWEEN %s AND %s",
    $org_id,
    $start_date,
    $end_date . ' 23:59:59'
));

// Work orders by status
$wo_by_status = $wpdb->get_results($wpdb->prepare(
    "SELECT status, COUNT(*) as count
     FROM {$tables['work_orders']}
     WHERE organization_id = %d
     GROUP BY status",
    $org_id
), ARRAY_A);

// Work orders by category
$wo_by_category = $wpdb->get_results($wpdb->prepare(
    "SELECT category, COUNT(*) as count
     FROM {$tables['work_orders']}
     WHERE organization_id = %d AND created_at BETWEEN %s AND %s
     GROUP BY category
     ORDER BY count DESC",
    $org_id,
    $start_date,
    $end_date . ' 23:59:59'
), ARRAY_A);

// Work orders by priority
$wo_by_priority = $wpdb->get_results($wpdb->prepare(
    "SELECT priority, COUNT(*) as count
     FROM {$tables['work_orders']}
     WHERE organization_id = %d AND created_at BETWEEN %s AND %s
     GROUP BY priority",
    $org_id,
    $start_date,
    $end_date . ' 23:59:59'
), ARRAY_A);

// Emergency work orders
$emergency_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tables['work_orders']}
     WHERE organization_id = %d AND priority = 'emergency'
     AND status IN ('open', 'assigned', 'in_progress')",
    $org_id
));

// Maintenance costs in period
$maintenance_costs = $wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(final_cost), 0) FROM {$tables['work_orders']}
     WHERE organization_id = %d AND status = 'completed'
     AND completed_at BETWEEN %s AND %s",
    $org_id,
    $start_date,
    $end_date . ' 23:59:59'
));

// Work orders by building
$wo_by_building = $wpdb->get_results($wpdb->prepare(
    "SELECT b.name as building_name, b.id as building_id,
            COUNT(wo.id) as total_orders,
            SUM(CASE WHEN wo.status = 'completed' THEN 1 ELSE 0 END) as completed
     FROM {$tables['buildings']} b
     LEFT JOIN {$tables['work_orders']} wo ON b.id = wo.building_id
         AND wo.created_at BETWEEN %s AND %s
     WHERE b.organization_id = %d
     GROUP BY b.id, b.name
     ORDER BY total_orders DESC",
    $start_date,
    $end_date . ' 23:59:59',
    $org_id
), ARRAY_A);

// Category labels
$category_labels = array(
    'plumbing' => __('Plumbing', 'rental-gates'),
    'electrical' => __('Electrical', 'rental-gates'),
    'hvac' => __('HVAC', 'rental-gates'),
    'appliance' => __('Appliance', 'rental-gates'),
    'structural' => __('Structural', 'rental-gates'),
    'pest' => __('Pest Control', 'rental-gates'),
    'cleaning' => __('Cleaning', 'rental-gates'),
    'general' => __('General', 'rental-gates'),
    'other' => __('Other', 'rental-gates'),
);

$status_labels = array(
    'available' => __('Available', 'rental-gates'),
    'occupied' => __('Occupied', 'rental-gates'),
    'coming_soon' => __('Coming Soon', 'rental-gates'),
    'unlisted' => __('Unlisted', 'rental-gates'),
);

$wo_status_labels = array(
    'open' => __('Open', 'rental-gates'),
    'assigned' => __('Assigned', 'rental-gates'),
    'in_progress' => __('In Progress', 'rental-gates'),
    'on_hold' => __('On Hold', 'rental-gates'),
    'completed' => __('Completed', 'rental-gates'),
    'cancelled' => __('Cancelled', 'rental-gates'),
    'declined' => __('Declined', 'rental-gates'),
);
?>

<style>
    /* Report Layout */
    .rg-report-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 16px;
    }

    .rg-report-title h1 {
        margin: 0 0 4px 0;
        font-size: 24px;
        font-weight: 700;
        color: #111827;
    }

    .rg-report-title p {
        margin: 0;
        color: #6b7280;
        font-size: 14px;
    }

    .rg-report-filters {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }

    .rg-report-filters select {
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        background: #fff;
    }

    .rg-report-filters .rg-btn {
        padding: 8px 16px;
    }

    /* Report Tabs */
    .rg-report-tabs {
        display: flex;
        gap: 4px;
        margin-bottom: 24px;
        border-bottom: 1px solid #e5e7eb;
    }

    .rg-report-tab {
        padding: 12px 20px;
        font-size: 14px;
        font-weight: 500;
        color: #6b7280;
        text-decoration: none;
        border-bottom: 2px solid transparent;
        margin-bottom: -1px;
        transition: all 0.2s;
    }

    .rg-report-tab:hover {
        color: #111827;
    }

    .rg-report-tab.active {
        color: #3b82f6;
        border-bottom-color: #3b82f6;
    }

    /* Metric Cards */
    .rg-metrics-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .rg-metric-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 20px;
    }

    .rg-metric-card.highlight {
        background: linear-gradient(135deg, #eff6ff 0%, #fff 100%);
        border-color: #3b82f6;
    }

    .rg-metric-card.success {
        background: linear-gradient(135deg, #ecfdf5 0%, #fff 100%);
        border-color: #10b981;
    }

    .rg-metric-card.warning {
        background: linear-gradient(135deg, #fffbeb 0%, #fff 100%);
        border-color: #f59e0b;
    }

    .rg-metric-card.danger {
        background: linear-gradient(135deg, #fef2f2 0%, #fff 100%);
        border-color: #ef4444;
    }

    .rg-metric-label {
        font-size: 13px;
        color: #6b7280;
        margin-bottom: 8px;
    }

    .rg-metric-value {
        font-size: 28px;
        font-weight: 700;
        color: #111827;
    }

    .rg-metric-value.success {
        color: #059669;
    }

    .rg-metric-value.warning {
        color: #d97706;
    }

    .rg-metric-value.danger {
        color: #dc2626;
    }

    .rg-metric-sub {
        font-size: 12px;
        color: #9ca3af;
        margin-top: 4px;
    }

    /* Charts */
    .rg-charts-row {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }

    .rg-chart-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 20px;
    }

    .rg-chart-title {
        font-size: 16px;
        font-weight: 600;
        color: #111827;
        margin: 0 0 16px 0;
    }

    .rg-chart-container {
        position: relative;
        height: 250px;
    }

    /* Tables */
    .rg-report-table {
        width: 100%;
        border-collapse: collapse;
    }

    .rg-report-table th {
        text-align: left;
        padding: 12px 16px;
        background: #f9fafb;
        font-weight: 600;
        font-size: 13px;
        color: #374151;
        border-bottom: 1px solid #e5e7eb;
    }

    .rg-report-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #f3f4f6;
        font-size: 14px;
    }

    .rg-report-table tr:hover td {
        background: #f9fafb;
    }

    .rg-report-table .text-right {
        text-align: right;
    }

    .rg-report-table .text-center {
        text-align: center;
    }

    /* Progress bars */
    .rg-progress-bar {
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
    }

    .rg-progress-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.3s;
    }

    .rg-progress-fill.blue {
        background: #3b82f6;
    }

    .rg-progress-fill.green {
        background: #10b981;
    }

    .rg-progress-fill.yellow {
        background: #f59e0b;
    }

    .rg-progress-fill.red {
        background: #ef4444;
    }

    /* Badge */
    .rg-badge {
        display: inline-flex;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }

    .rg-badge-blue {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .rg-badge-green {
        background: #d1fae5;
        color: #065f46;
    }

    .rg-badge-yellow {
        background: #fef3c7;
        color: #92400e;
    }

    .rg-badge-red {
        background: #fee2e2;
        color: #991b1b;
    }

    .rg-badge-gray {
        background: #f3f4f6;
        color: #4b5563;
    }

    /* Donut Chart */
    .rg-donut-chart {
        display: flex;
        align-items: center;
        gap: 24px;
    }

    .rg-donut-svg {
        width: 140px;
        height: 140px;
    }

    .rg-donut-legend {
        flex: 1;
    }

    .rg-donut-legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 0;
        font-size: 13px;
    }

    .rg-donut-legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
    }

    .rg-donut-legend-value {
        margin-left: auto;
        font-weight: 600;
    }

    /* List items */
    .rg-expiring-list {}

    .rg-expiring-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f3f4f6;
    }

    .rg-expiring-item:last-child {
        border-bottom: none;
    }

    .rg-expiring-info {}

    .rg-expiring-title {
        font-weight: 500;
        color: #111827;
    }

    .rg-expiring-meta {
        font-size: 13px;
        color: #6b7280;
    }

    /* Export buttons */
    .rg-export-actions {
        display: flex;
        gap: 8px;
    }

    .rg-btn-export {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 13px;
        color: #374151;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
    }

    .rg-btn-export:hover {
        background: #f9fafb;
        border-color: #9ca3af;
    }

    .rg-btn-export svg {
        width: 16px;
        height: 16px;
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .rg-metrics-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .rg-charts-row {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .rg-metrics-grid {
            grid-template-columns: 1fr;
        }

        .rg-report-header {
            flex-direction: column;
        }

        .rg-report-tabs {
            overflow-x: auto;
        }
    }
</style>

<div class="rg-report-header">
    <div class="rg-report-title">
        <h1><?php _e('Reports', 'rental-gates'); ?></h1>
        <p><?php printf(__('Showing data for %s', 'rental-gates'), esc_html($period_label)); ?></p>
    </div>

    <form class="rg-report-filters" method="get">
        <input type="hidden" name="rental_gates_page" value="dashboard">
        <input type="hidden" name="rental_gates_section" value="reports">
        <input type="hidden" name="tab" value="<?php echo esc_attr($report_tab); ?>">

        <select name="period" onchange="this.form.submit()">
            <option value="month" <?php selected($period, 'month'); ?>><?php _e('Monthly', 'rental-gates'); ?></option>
            <option value="quarter" <?php selected($period, 'quarter'); ?>><?php _e('Quarterly', 'rental-gates'); ?>
            </option>
            <option value="year" <?php selected($period, 'year'); ?>><?php _e('Yearly', 'rental-gates'); ?></option>
        </select>

        <?php if ($period === 'month'): ?>
            <select name="month" onchange="this.form.submit()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php selected($month, $m); ?>>
                        <?php echo date_i18n('F', mktime(0, 0, 0, $m, 1)); ?>
                    </option>
                <?php endfor; ?>
            </select>
        <?php elseif ($period === 'quarter'): ?>
            <select name="quarter" onchange="this.form.submit()">
                <?php for ($q = 1; $q <= 4; $q++): ?>
                    <option value="<?php echo $q; ?>" <?php selected(isset($_GET['quarter']) ? intval($_GET['quarter']) : ceil($month / 3), $q); ?>>
                        Q<?php echo $q; ?>
                    </option>
                <?php endfor; ?>
            </select>
        <?php endif; ?>

        <select name="year" onchange="this.form.submit()">
            <?php for ($y = intval(date('Y')); $y >= intval(date('Y')) - 5; $y--): ?>
                <option value="<?php echo $y; ?>" <?php selected($year, $y); ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>

        <div class="rg-export-actions">
            <button onclick="exportReport('csv')" class="rg-btn-export">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
                CSV
            </button>
            <button onclick="exportReport('pdf')" class="rg-btn-export">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="16" y1="13" x2="8" y2="13" />
                    <line x1="16" y1="17" x2="8" y2="17" />
                    <polyline points="10 9 9 9 8 9" />
                </svg>
                PDF
            </button>
        </div>
    </form>
</div>

<!-- Report Tabs -->
<div class="rg-report-tabs">
    <a href="<?php echo add_query_arg('tab', 'financial'); ?>"
        class="rg-report-tab <?php echo $report_tab === 'financial' ? 'active' : ''; ?>">
        <?php _e('Financial', 'rental-gates'); ?>
    </a>
    <a href="<?php echo add_query_arg('tab', 'occupancy'); ?>"
        class="rg-report-tab <?php echo $report_tab === 'occupancy' ? 'active' : ''; ?>">
        <?php _e('Occupancy', 'rental-gates'); ?>
    </a>
    <a href="<?php echo add_query_arg('tab', 'maintenance'); ?>"
        class="rg-report-tab <?php echo $report_tab === 'maintenance' ? 'active' : ''; ?>">
        <?php _e('Maintenance', 'rental-gates'); ?>
    </a>
</div>

<?php if ($report_tab === 'financial'): ?>
    <!-- ==================== FINANCIAL REPORT ==================== -->

    <!-- Key Metrics -->
    <div class="rg-metrics-grid">
        <div class="rg-metric-card success">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                <div class="rg-metric-label"><?php _e('Revenue Collected', 'rental-gates'); ?></div>
                <?php if ($financial_analytics['revenue_growth'] != 0): ?>
                <div style="font-size: 12px; color: <?php echo $financial_analytics['revenue_growth'] > 0 ? '#059669' : '#dc2626'; ?>; font-weight: 600;">
                    <?php echo $financial_analytics['revenue_growth'] > 0 ? '↑' : '↓'; ?> <?php echo abs($financial_analytics['revenue_growth']); ?>%
                </div>
                <?php endif; ?>
            </div>
            <div class="rg-metric-value success">$<?php echo number_format($financial_analytics['revenue_collected'], 2); ?></div>
            <div class="rg-metric-sub"><?php echo esc_html($period_label); ?></div>
        </div>

        <div class="rg-metric-card">
            <div class="rg-metric-label"><?php _e('Total Billed', 'rental-gates'); ?></div>
            <div class="rg-metric-value">$<?php echo number_format($financial_analytics['total_billed'], 2); ?></div>
            <div class="rg-metric-sub"><?php echo esc_html($period_label); ?></div>
        </div>

        <div
            class="rg-metric-card <?php echo $financial_analytics['collection_rate'] >= 90 ? 'success' : ($financial_analytics['collection_rate'] >= 70 ? 'warning' : 'danger'); ?>">
            <div class="rg-metric-label"><?php _e('Collection Rate', 'rental-gates'); ?></div>
            <div
                class="rg-metric-value <?php echo $financial_analytics['collection_rate'] >= 90 ? 'success' : ($financial_analytics['collection_rate'] >= 70 ? 'warning' : 'danger'); ?>">
                <?php echo number_format($financial_analytics['collection_rate'], 1); ?>%
            </div>
            <div class="rg-metric-sub"><?php _e('of billed amount', 'rental-gates'); ?></div>
        </div>

        <div class="rg-metric-card <?php echo $financial_analytics['overdue_amount'] > 0 ? 'danger' : ''; ?>">
            <div class="rg-metric-label"><?php _e('Overdue Amount', 'rental-gates'); ?></div>
            <div class="rg-metric-value <?php echo $financial_analytics['overdue_amount'] > 0 ? 'danger' : ''; ?>">
                $<?php echo number_format($financial_analytics['overdue_amount'], 2); ?>
            </div>
            <div class="rg-metric-sub"><?php _e('past due date', 'rental-gates'); ?></div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="rg-charts-row">
        <div class="rg-chart-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 class="rg-chart-title" style="margin: 0;"><?php _e('Revenue Trend (12 Months)', 'rental-gates'); ?></h3>
                <button onclick="exportChart('revenueTrendChart', 'revenue-trend')" class="rg-btn-export" style="padding: 6px 12px; font-size: 12px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="7 10 12 15 17 10" />
                        <line x1="12" y1="15" x2="12" y2="3" />
                    </svg>
                    <?php _e('Export', 'rental-gates'); ?>
                </button>
            </div>
            <div class="rg-chart-container" style="height: 280px;">
                <canvas id="revenueTrendChart"></canvas>
            </div>
        </div>

        <div class="rg-chart-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 class="rg-chart-title" style="margin: 0;"><?php _e('Revenue by Type', 'rental-gates'); ?></h3>
                <button onclick="exportChart('revenueByTypeChart', 'revenue-by-type')" class="rg-btn-export" style="padding: 6px 12px; font-size: 12px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="7 10 12 15 17 10" />
                        <line x1="12" y1="15" x2="12" y2="3" />
                    </svg>
                    <?php _e('Export', 'rental-gates'); ?>
                </button>
            </div>
            <div class="rg-chart-container" style="height: 280px;">
                <canvas id="revenueByTypeChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Revenue by Building -->
    <div class="rg-chart-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 class="rg-chart-title" style="margin: 0;"><?php _e('Revenue by Building', 'rental-gates'); ?></h3>
            <div style="display: flex; gap: 8px;">
                <button onclick="exportTable('revenueByBuildingTable')" class="rg-btn-export" style="padding: 6px 12px; font-size: 12px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="7 10 12 15 17 10" />
                        <line x1="12" y1="15" x2="12" y2="3" />
                    </svg>
                    <?php _e('CSV', 'rental-gates'); ?>
                </button>
            </div>
        </div>
        <?php if (!empty($financial_analytics['revenue_by_building'])): ?>
        <div class="rg-chart-container" style="height: 300px; margin-bottom: 20px;">
            <canvas id="revenueByBuildingChart"></canvas>
        </div>
        <?php endif; ?>
        <table class="rg-report-table" id="revenueByBuildingTable">
            <thead>
                <tr>
                    <th><?php _e('Building', 'rental-gates'); ?></th>
                    <th class="text-right"><?php _e('Billed', 'rental-gates'); ?></th>
                    <th class="text-right"><?php _e('Collected', 'rental-gates'); ?></th>
                    <th style="width: 200px;"><?php _e('Collection Rate', 'rental-gates'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($financial_analytics['revenue_by_building'] as $building):
                    $rate = $building['billed'] > 0 ? ($building['collected'] / $building['billed']) * 100 : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($building['building_name']); ?></strong></td>
                        <td class="text-right">$<?php echo number_format($building['billed'], 2); ?></td>
                        <td class="text-right">$<?php echo number_format($building['collected'], 2); ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="rg-progress-bar" style="flex: 1;">
                                    <div class="rg-progress-fill <?php echo $rate >= 90 ? 'green' : ($rate >= 70 ? 'yellow' : 'red'); ?>"
                                        style="width: <?php echo min(100, $rate); ?>%;"></div>
                                </div>
                                <span style="font-size: 13px; font-weight: 500;"><?php echo number_format($rate, 0); ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') return;
            
            // Revenue Trend Chart (12 months)
            const revenueTrend = <?php echo wp_json_encode(Rental_Gates_Analytics::get_revenue_trend($org_id, 12)); ?>;
            const trendLabels = revenueTrend.map(d => d.label);
            const trendValues = revenueTrend.map(d => d.revenue);

            new Chart(document.getElementById('revenueTrendChart'), {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: '<?php echo esc_js(__('Revenue', 'rental-gates')); ?>',
                        data: trendValues,
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderColor: 'rgb(37, 99, 235)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
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
                            ticks: {
                                callback: function (value) {
                                    return '$' + value.toLocaleString();
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            
            // Revenue by Type (Donut Chart)
            const revenueByType = <?php echo wp_json_encode($financial_analytics['revenue_by_type']); ?>;
            const typeLabels = revenueByType.map(t => t.payment_type.charAt(0).toUpperCase() + t.payment_type.slice(1));
            const typeValues = revenueByType.map(t => parseFloat(t.collected));
            const typeColors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#6366f1', '#14b8a6', '#f97316'];
            
            new Chart(document.getElementById('revenueByTypeChart'), {
                type: 'doughnut',
                data: {
                    labels: typeLabels,
                    datasets: [{
                        data: typeValues,
                        backgroundColor: typeColors.slice(0, typeValues.length),
                        borderWidth: 2,
                        borderColor: '#fff',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: { size: 12 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': $' + value.toLocaleString() + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            
            // Revenue by Building Chart
            <?php if (!empty($financial_analytics['revenue_by_building'])): ?>
            const revenueByBuilding = <?php echo wp_json_encode($financial_analytics['revenue_by_building']); ?>;
            const buildingLabels = revenueByBuilding.map(b => b.building_name || '<?php _e('Unnamed Building', 'rental-gates'); ?>');
            const buildingValues = revenueByBuilding.map(b => parseFloat(b.collected));
            
            new Chart(document.getElementById('revenueByBuildingChart'), {
                type: 'bar',
                data: {
                    labels: buildingLabels,
                    datasets: [{
                        label: '<?php echo esc_js(__('Revenue Collected', 'rental-gates')); ?>',
                        data: buildingValues,
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
                        legend: { display: false },
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
            <?php endif; ?>
        });
        
        // Export functions
        function exportChart(canvasId, filename) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;
            const url = canvas.toDataURL('image/png');
            const link = document.createElement('a');
            link.download = filename + '-' + new Date().toISOString().split('T')[0] + '.png';
            link.href = url;
            link.click();
        }
        
        function exportTable(tableId) {
            const table = document.getElementById(tableId);
            if (!table) return;
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [];
                const cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/"/g, '""');
                    row.push('"' + data + '"');
                }
                
                csv.push(row.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', tableId + '-' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        function exportReport(format) {
            const period = '<?php echo esc_js($period); ?>';
            const year = '<?php echo esc_js($year); ?>';
            const month = '<?php echo esc_js($month); ?>';
            const tab = '<?php echo esc_js($report_tab); ?>';
            
            const url = new URL(window.location.href);
            url.searchParams.set('export', format);
            url.searchParams.set('period', period);
            url.searchParams.set('year', year);
            url.searchParams.set('month', month);
            url.searchParams.set('tab', tab);
            
            window.location.href = url.toString();
        }
    </script>

<?php elseif ($report_tab === 'occupancy'): ?>
    <!-- ==================== OCCUPANCY REPORT ==================== -->

    <!-- Key Metrics -->
    <div class="rg-metrics-grid">
        <div class="rg-metric-card highlight">
            <div class="rg-metric-label"><?php _e('Occupancy Rate', 'rental-gates'); ?></div>
            <div class="rg-metric-value"><?php echo number_format($occupancy_analytics['occupancy_rate'], 1); ?>%</div>
            <div class="rg-metric-sub"><?php echo $occupancy_analytics['occupied']; ?> / <?php echo $occupancy_analytics['total_units']; ?>
                <?php _e('units', 'rental-gates'); ?></div>
        </div>

        <div class="rg-metric-card">
            <div class="rg-metric-label"><?php _e('Vacancy Rate', 'rental-gates'); ?></div>
            <div class="rg-metric-value"><?php echo number_format($occupancy_analytics['vacancy_rate'], 1); ?>%</div>
            <div class="rg-metric-sub"><?php echo $occupancy_analytics['available'] + $occupancy_analytics['coming_soon']; ?> <?php _e('available units', 'rental-gates'); ?></div>
        </div>

        <div class="rg-metric-card success">
            <div class="rg-metric-label"><?php _e('New Leases', 'rental-gates'); ?></div>
            <div class="rg-metric-value success"><?php echo $new_leases; ?></div>
            <div class="rg-metric-sub"><?php echo esc_html($period_label); ?></div>
        </div>

        <div class="rg-metric-card <?php echo $occupancy_analytics['avg_days_vacant'] > 30 ? 'warning' : ''; ?>">
            <div class="rg-metric-label"><?php _e('Avg Days Vacant', 'rental-gates'); ?></div>
            <div class="rg-metric-value <?php echo $occupancy_analytics['avg_days_vacant'] > 30 ? 'warning' : ''; ?>">
                <?php echo number_format($occupancy_analytics['avg_days_vacant'], 0); ?></div>
            <div class="rg-metric-sub"><?php _e('last 12 months', 'rental-gates'); ?></div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="rg-charts-row">
        <div class="rg-chart-card">
            <h3 class="rg-chart-title"><?php _e('Occupancy by Building', 'rental-gates'); ?></h3>
            <table class="rg-report-table">
                <thead>
                    <tr>
                        <th><?php _e('Building', 'rental-gates'); ?></th>
                        <th class="text-center"><?php _e('Total', 'rental-gates'); ?></th>
                        <th class="text-center"><?php _e('Occupied', 'rental-gates'); ?></th>
                        <th class="text-center"><?php _e('Vacant', 'rental-gates'); ?></th>
                        <th style="width: 180px;"><?php _e('Occupancy', 'rental-gates'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($occupancy_by_building as $building):
                        $occ_rate = $building['total_units'] > 0 ? ($building['occupied_units'] / $building['total_units']) * 100 : 0;
                        $vacant = $building['total_units'] - $building['occupied_units'];
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($building['building_name']); ?></strong></td>
                            <td class="text-center"><?php echo $building['total_units']; ?></td>
                            <td class="text-center"><?php echo $building['occupied_units']; ?></td>
                            <td class="text-center"><?php echo $vacant; ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="rg-progress-bar" style="flex: 1;">
                                        <div class="rg-progress-fill <?php echo $occ_rate >= 90 ? 'green' : ($occ_rate >= 70 ? 'blue' : 'yellow'); ?>"
                                            style="width: <?php echo $occ_rate; ?>%;"></div>
                                    </div>
                                    <span
                                        style="font-size: 13px; font-weight: 500;"><?php echo number_format($occ_rate, 0); ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="rg-chart-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 class="rg-chart-title" style="margin: 0;"><?php _e('Units by Status', 'rental-gates'); ?></h3>
                <button onclick="exportChart('unitsByStatusChart', 'units-by-status')" class="rg-btn-export" style="padding: 6px 12px; font-size: 12px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="7 10 12 15 17 10" />
                        <line x1="12" y1="15" x2="12" y2="3" />
                    </svg>
                    <?php _e('Export', 'rental-gates'); ?>
                </button>
            </div>
            <div class="rg-chart-container" style="height: 280px;">
                <canvas id="unitsByStatusChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Chart === 'undefined') return;
            
            // Units by Status Chart
            const unitsData = {
                occupied: <?php echo $occupancy_analytics['occupied']; ?>,
                available: <?php echo $occupancy_analytics['available']; ?>,
                coming_soon: <?php echo $occupancy_analytics['coming_soon']; ?>,
                unlisted: <?php echo $occupancy_analytics['unlisted']; ?>,
            };
            
            const statusLabels = ['<?php _e('Occupied', 'rental-gates'); ?>', '<?php _e('Available', 'rental-gates'); ?>', '<?php _e('Coming Soon', 'rental-gates'); ?>', '<?php _e('Unlisted', 'rental-gates'); ?>'];
            const statusValues = [unitsData.occupied, unitsData.available, unitsData.coming_soon, unitsData.unlisted];
            const statusColors = ['#3b82f6', '#10b981', '#f59e0b', '#6b7280'];
            
            new Chart(document.getElementById('unitsByStatusChart'), {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusValues,
                        backgroundColor: statusColors,
                        borderWidth: 2,
                        borderColor: '#fff',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: { size: 12 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>

    <!-- Expiring Leases -->
    <?php if (!empty($expiring_leases)): ?>
        <div class="rg-chart-card">
            <h3 class="rg-chart-title"><?php _e('Leases Expiring Soon', 'rental-gates'); ?></h3>
            <table class="rg-report-table">
                <thead>
                    <tr>
                        <th><?php _e('Unit', 'rental-gates'); ?></th>
                        <th><?php _e('Building', 'rental-gates'); ?></th>
                        <th><?php _e('End Date', 'rental-gates'); ?></th>
                        <th class="text-center"><?php _e('Days Left', 'rental-gates'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expiring_leases as $lease): ?>
                        <tr>
                            <td><strong><?php echo esc_html($lease['unit_name']); ?></strong></td>
                            <td><?php echo esc_html($lease['building_name']); ?></td>
                            <td><?php echo date_i18n('M j, Y', strtotime($lease['end_date'])); ?></td>
                            <td class="text-center">
                                <?php if ($lease['days_remaining'] <= 14): ?>
                                    <span class="rg-badge rg-badge-red"><?php echo $lease['days_remaining']; ?>
                                        <?php _e('days', 'rental-gates'); ?></span>
                                <?php elseif ($lease['days_remaining'] <= 30): ?>
                                    <span class="rg-badge rg-badge-yellow"><?php echo $lease['days_remaining']; ?>
                                        <?php _e('days', 'rental-gates'); ?></span>
                                <?php else: ?>
                                    <span class="rg-badge rg-badge-gray"><?php echo $lease['days_remaining']; ?>
                                        <?php _e('days', 'rental-gates'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <a href="<?php echo home_url('/rental-gates/dashboard/leases/' . $lease['id']); ?>"
                                    style="color: #3b82f6; font-size: 13px;">
                                    <?php _e('View', 'rental-gates'); ?> →
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php elseif ($report_tab === 'maintenance'): ?>
    <!-- ==================== MAINTENANCE REPORT ==================== -->

    <!-- Key Metrics -->
    <div class="rg-metrics-grid">
        <div class="rg-metric-card">
            <div class="rg-metric-label"><?php _e('Work Orders', 'rental-gates'); ?></div>
            <div class="rg-metric-value"><?php echo $maintenance_analytics['total_work_orders']; ?></div>
            <div class="rg-metric-sub"><?php echo esc_html($period_label); ?></div>
        </div>

        <?php
        $open_count = 0;
        foreach ($maintenance_analytics['by_status'] as $status) {
            if (in_array($status['status'], array('open', 'assigned', 'in_progress'))) {
                $open_count += intval($status['count']);
            }
        }
        ?>
        <div class="rg-metric-card <?php echo $open_count > 10 ? 'warning' : ''; ?>">
            <div class="rg-metric-label"><?php _e('Open', 'rental-gates'); ?></div>
            <div class="rg-metric-value <?php echo $open_count > 10 ? 'warning' : ''; ?>">
                <?php echo $open_count; ?></div>
            <div class="rg-metric-sub"><?php _e('currently active', 'rental-gates'); ?></div>
        </div>

        <?php
        $completed_count = 0;
        foreach ($maintenance_analytics['by_status'] as $status) {
            if ($status['status'] === 'completed') {
                $completed_count = intval($status['count']);
                break;
            }
        }
        ?>
        <div class="rg-metric-card success">
            <div class="rg-metric-label"><?php _e('Completed', 'rental-gates'); ?></div>
            <div class="rg-metric-value success"><?php echo $completed_count; ?></div>
            <div class="rg-metric-sub"><?php echo esc_html($period_label); ?></div>
        </div>

        <div class="rg-metric-card">
            <div class="rg-metric-label"><?php _e('Avg Response Time', 'rental-gates'); ?></div>
            <div class="rg-metric-value"><?php echo number_format($maintenance_analytics['avg_response_time_hours'], 1); ?>h</div>
            <div class="rg-metric-sub"><?php _e('from creation', 'rental-gates'); ?></div>
        </div>

        <div class="rg-metric-card">
            <div class="rg-metric-label"><?php _e('Avg Completion', 'rental-gates'); ?></div>
            <div class="rg-metric-value"><?php echo number_format($maintenance_analytics['avg_completion_time_days'], 1); ?> <?php _e('days', 'rental-gates'); ?></div>
            <div class="rg-metric-sub"><?php _e('time to complete', 'rental-gates'); ?></div>
        </div>

        <div class="rg-metric-card <?php echo $emergency_count > 0 ? 'danger' : ''; ?>">
            <div class="rg-metric-label"><?php _e('Emergencies', 'rental-gates'); ?></div>
            <div class="rg-metric-value <?php echo $emergency_count > 0 ? 'danger' : ''; ?>"><?php echo $emergency_count; ?>
            </div>
            <div class="rg-metric-sub"><?php _e('open emergency tickets', 'rental-gates'); ?></div>
        </div>
    </div>

    <!-- Additional Metrics -->
    <div class="rg-metrics-grid" style="margin-bottom: 24px;">
        <div class="rg-metric-card">
            <div class="rg-metric-label"><?php _e('Avg. Resolution Time', 'rental-gates'); ?></div>
            <div class="rg-metric-value"><?php echo $avg_resolution_time ? number_format($avg_resolution_time, 1) : '—'; ?>
            </div>
            <div class="rg-metric-sub"><?php _e('days', 'rental-gates'); ?></div>
        </div>

        <div class="rg-metric-card">
            <div class="rg-metric-label"><?php _e('Maintenance Costs', 'rental-gates'); ?></div>
            <div class="rg-metric-value">$<?php echo number_format((float) $maintenance_costs, 0); ?></div>
            <div class="rg-metric-sub"><?php echo esc_html($period_label); ?></div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="rg-charts-row">
        <div class="rg-chart-card">
            <h3 class="rg-chart-title"><?php _e('Work Orders by Category', 'rental-gates'); ?></h3>
            <div class="rg-chart-container">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>

        <div class="rg-chart-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 class="rg-chart-title" style="margin: 0;"><?php _e('By Status', 'rental-gates'); ?></h3>
                <button onclick="exportChart('woStatusChart', 'work-orders-by-status')" class="rg-btn-export" style="padding: 6px 12px; font-size: 12px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="7 10 12 15 17 10" />
                        <line x1="12" y1="15" x2="12" y2="3" />
                    </svg>
                    <?php _e('Export', 'rental-gates'); ?>
                </button>
            </div>
            <div class="rg-chart-container" style="height: 280px;">
                <canvas id="woStatusChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Work Orders by Building -->
    <div class="rg-chart-card">
        <h3 class="rg-chart-title"><?php _e('Work Orders by Building', 'rental-gates'); ?></h3>
        <table class="rg-report-table">
            <thead>
                <tr>
                    <th><?php _e('Building', 'rental-gates'); ?></th>
                    <th class="text-center"><?php _e('Total', 'rental-gates'); ?></th>
                    <th class="text-center"><?php _e('Completed', 'rental-gates'); ?></th>
                    <th style="width: 180px;"><?php _e('Completion Rate', 'rental-gates'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($wo_by_building as $building):
                    $comp_rate = $building['total_orders'] > 0 ? ($building['completed'] / $building['total_orders']) * 100 : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($building['building_name']); ?></strong></td>
                        <td class="text-center"><?php echo $building['total_orders']; ?></td>
                        <td class="text-center"><?php echo $building['completed']; ?></td>
                        <td>
                            <?php if ($building['total_orders'] > 0): ?>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="rg-progress-bar" style="flex: 1;">
                                        <div class="rg-progress-fill green" style="width: <?php echo $comp_rate; ?>%;"></div>
                                    </div>
                                    <span
                                        style="font-size: 13px; font-weight: 500;"><?php echo number_format($comp_rate, 0); ?>%</span>
                                </div>
                            <?php else: ?>
                                <span style="color: #9ca3af;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') return;
            
            // Category Chart
            const categoryData = <?php echo wp_json_encode($wo_by_category); ?>;
            const categoryLabels = <?php echo wp_json_encode($category_labels); ?>;

            new Chart(document.getElementById('categoryChart'), {
                type: 'bar',
                data: {
                    labels: categoryData.map(d => categoryLabels[d.category] || d.category),
                    datasets: [{
                        label: '<?php echo esc_js(__('Work Orders', 'rental-gates')); ?>',
                        data: categoryData.map(d => parseInt(d.count)),
                        backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#6366f1', '#14b8a6', '#f97316', '#6b7280'],
                        borderRadius: 6,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                        }
                    },
                    scales: {
                        x: { 
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            
            // Work Orders by Status Chart
            const woByStatus = <?php echo wp_json_encode($maintenance_analytics['by_status']); ?>;
            const woStatusLabels = <?php echo wp_json_encode($wo_status_labels); ?>;
            const woStatusColors = {
                'open': '#3b82f6',
                'assigned': '#8b5cf6',
                'in_progress': '#f59e0b',
                'on_hold': '#6b7280',
                'completed': '#10b981',
                'cancelled': '#9ca3af',
                'declined': '#ef4444',
            };
            
            const woStatusLabelsArray = woByStatus.map(s => woStatusLabels[s.status] || s.status.charAt(0).toUpperCase() + s.status.slice(1));
            const woStatusValues = woByStatus.map(s => parseInt(s.count));
            const woStatusColorsArray = woByStatus.map(s => woStatusColors[s.status] || '#6b7280');
            
            new Chart(document.getElementById('woStatusChart'), {
                type: 'doughnut',
                data: {
                    labels: woStatusLabelsArray,
                    datasets: [{
                        data: woStatusValues,
                        backgroundColor: woStatusColorsArray,
                        borderWidth: 2,
                        borderColor: '#fff',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: { size: 12 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>

<?php endif; ?>