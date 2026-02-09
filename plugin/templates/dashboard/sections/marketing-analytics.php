<?php
/**
 * Marketing Analytics Dashboard
 * 
 * Comprehensive marketing analytics with conversion funnels, ROI tracking,
 * and performance metrics.
 * 
 * @version 1.0.0
 */
if (!defined('ABSPATH')) exit;

$org_id = Rental_Gates_Roles::get_organization_id();
if (!$org_id) {
    echo '<div class="rg-alert rg-alert-error">' . __('Organization not found', 'rental-gates') . '</div>';
    return;
}

// Get period from query string
$period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
$valid_periods = array('day', 'week', 'month', 'quarter', 'year');
if (!in_array($period, $valid_periods)) {
    $period = 'month';
}

// Get marketing analytics
$analytics = Rental_Gates_Analytics::get_marketing_analytics($org_id, $period);

// Get QR analytics
$qr_analytics = $analytics['qr_analytics'] ?? array();

// Get lead analytics
$total_leads = $analytics['total_leads'] ?? 0;
$funnel = $analytics['funnel'] ?? array();
$conversion_rates = $analytics['conversion_rates'] ?? array();
?>

<div class="mkt-analytics-header">
    <div>
        <h2 class="rg-page-title"><?php _e('Marketing Analytics', 'rental-gates'); ?></h2>
        <p class="rg-page-subtitle"><?php _e('Track your marketing performance and conversions', 'rental-gates'); ?></p>
    </div>
    
    <div class="mkt-period-selector">
        <?php foreach ($valid_periods as $p): ?>
        <a href="?period=<?php echo esc_attr($p); ?>" class="mkt-period-btn <?php echo $period === $p ? 'active' : ''; ?>">
            <?php echo esc_html(ucfirst($p)); ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Key Metrics -->
<div class="mkt-metrics-grid">
    <div class="mkt-metric-card">
        <div class="mkt-metric-label"><?php _e('QR Scans', 'rental-gates'); ?></div>
        <div class="mkt-metric-value"><?php echo number_format($funnel['scans'] ?? 0); ?></div>
        <?php if (!empty($analytics['lead_growth'])): ?>
        <div class="mkt-metric-change <?php echo $analytics['lead_growth'] >= 0 ? 'positive' : 'negative'; ?>">
            <?php echo $analytics['lead_growth'] >= 0 ? '↑' : '↓'; ?>
            <?php echo abs($analytics['lead_growth']); ?>% <?php _e('vs previous period', 'rental-gates'); ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="mkt-metric-card">
        <div class="mkt-metric-label"><?php _e('Leads Generated', 'rental-gates'); ?></div>
        <div class="mkt-metric-value"><?php echo number_format($total_leads); ?></div>
        <?php if (!empty($analytics['lead_growth'])): ?>
        <div class="mkt-metric-change <?php echo $analytics['lead_growth'] >= 0 ? 'positive' : 'negative'; ?>">
            <?php echo $analytics['lead_growth'] >= 0 ? '↑' : '↓'; ?>
            <?php echo abs($analytics['lead_growth']); ?>% <?php _e('vs previous period', 'rental-gates'); ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="mkt-metric-card">
        <div class="mkt-metric-label"><?php _e('Applications', 'rental-gates'); ?></div>
        <div class="mkt-metric-value"><?php echo number_format($funnel['applications'] ?? 0); ?></div>
        <div class="mkt-metric-change">
            <?php echo number_format($conversion_rates['lead_to_application'] ?? 0, 1); ?>% <?php _e('conversion rate', 'rental-gates'); ?>
        </div>
    </div>
    
    <div class="mkt-metric-card">
        <div class="mkt-metric-label"><?php _e('Leases Signed', 'rental-gates'); ?></div>
        <div class="mkt-metric-value"><?php echo number_format($funnel['leases'] ?? 0); ?></div>
        <div class="mkt-metric-change">
            <?php echo number_format($conversion_rates['overall'] ?? 0, 2); ?>% <?php _e('overall conversion', 'rental-gates'); ?>
        </div>
    </div>
</div>

<!-- Conversion Funnel -->
<div class="mkt-funnel">
    <h3 class="mkt-funnel-title"><?php _e('Conversion Funnel', 'rental-gates'); ?></h3>
    <div class="mkt-funnel-steps">
        <?php
        $max_value = max($funnel['scans'] ?? 1, $funnel['leads'] ?? 1, $funnel['applications'] ?? 1, $funnel['leases'] ?? 1);
        $steps = array(
            array('label' => __('QR Scans', 'rental-gates'), 'value' => $funnel['scans'] ?? 0, 'rate' => '100%'),
            array('label' => __('Leads', 'rental-gates'), 'value' => $funnel['leads'] ?? 0, 'rate' => number_format($conversion_rates['scan_to_lead'] ?? 0, 1) . '%'),
            array('label' => __('Applications', 'rental-gates'), 'value' => $funnel['applications'] ?? 0, 'rate' => number_format($conversion_rates['lead_to_application'] ?? 0, 1) . '%'),
            array('label' => __('Leases', 'rental-gates'), 'value' => $funnel['leases'] ?? 0, 'rate' => number_format($conversion_rates['application_to_lease'] ?? 0, 1) . '%'),
        );
        
        foreach ($steps as $step):
            $percentage = $max_value > 0 ? ($step['value'] / $max_value) * 100 : 0;
        ?>
        <div class="mkt-funnel-step">
            <div class="mkt-funnel-step-label"><?php echo esc_html($step['label']); ?></div>
            <div class="mkt-funnel-step-bar">
                <div class="mkt-funnel-step-fill" style="width: <?php echo $percentage; ?>%;">
                    <?php if ($step['value'] > 0): ?>
                        <?php echo number_format($step['value']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mkt-funnel-step-value"><?php echo number_format($step['value']); ?></div>
            <div class="mkt-funnel-step-rate"><?php echo esc_html($step['rate']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Charts Grid -->
<div class="mkt-charts-grid">
    <!-- Leads by Source -->
    <div class="mkt-chart-card">
        <h4 class="mkt-chart-title"><?php _e('Leads by Source', 'rental-gates'); ?></h4>
        <div class="mkt-chart-container">
            <canvas id="leadsBySourceChart"></canvas>
        </div>
    </div>
    
    <!-- Leads by Stage -->
    <div class="mkt-chart-card">
        <h4 class="mkt-chart-title"><?php _e('Leads by Stage', 'rental-gates'); ?></h4>
        <div class="mkt-chart-container">
            <canvas id="leadsByStageChart"></canvas>
        </div>
    </div>
    
    <!-- Daily Trend -->
    <div class="mkt-chart-card mkt-chart-full">
        <h4 class="mkt-chart-title"><?php _e('Daily Lead Trend', 'rental-gates'); ?></h4>
        <div class="mkt-chart-container" style="height: 250px;">
            <canvas id="dailyLeadsChart"></canvas>
        </div>
    </div>
</div>

<script>
// Chart.js data
const leadsBySourceData = <?php echo Rental_Gates_Security::json_for_script($analytics['leads_by_source'] ?? array()); ?>;
const leadsByStageData = <?php echo Rental_Gates_Security::json_for_script($analytics['leads_by_stage'] ?? array()); ?>;
const dailyLeadsData = <?php echo Rental_Gates_Security::json_for_script($analytics['daily_leads'] ?? array()); ?>;

// Initialize charts when Chart.js is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded');
        return;
    }
    
    // Leads by Source Chart
    const sourceCtx = document.getElementById('leadsBySourceChart');
    if (sourceCtx) {
        const sourceLabels = leadsBySourceData.map(item => {
            const sources = {
                'qr_building': '<?php _e('QR Building', 'rental-gates'); ?>',
                'qr_unit': '<?php _e('QR Unit', 'rental-gates'); ?>',
                'map': '<?php _e('Map', 'rental-gates'); ?>',
                'profile': '<?php _e('Profile', 'rental-gates'); ?>',
                'manual': '<?php _e('Manual', 'rental-gates'); ?>',
                'referral': '<?php _e('Referral', 'rental-gates'); ?>'
            };
            return sources[item.source] || item.source;
        });
        new Chart(sourceCtx, {
            type: 'doughnut',
            data: {
                labels: sourceLabels,
                datasets: [{
                    data: leadsBySourceData.map(item => item.count),
                    backgroundColor: [
                        '#3b82f6', '#8b5cf6', '#f59e0b', '#10b981', '#ef4444', '#06b6d4'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Leads by Stage Chart
    const stageCtx = document.getElementById('leadsByStageChart');
    if (stageCtx) {
        const stageLabels = leadsByStageData.map(item => {
            const stages = {
                'new': '<?php _e('New', 'rental-gates'); ?>',
                'contacted': '<?php _e('Contacted', 'rental-gates'); ?>',
                'touring': '<?php _e('Touring', 'rental-gates'); ?>',
                'applied': '<?php _e('Applied', 'rental-gates'); ?>',
                'won': '<?php _e('Won', 'rental-gates'); ?>',
                'lost': '<?php _e('Lost', 'rental-gates'); ?>'
            };
            return stages[item.stage] || item.stage;
        });
        new Chart(stageCtx, {
            type: 'bar',
            data: {
                labels: stageLabels,
                datasets: [{
                    label: '<?php _e('Leads', 'rental-gates'); ?>',
                    data: leadsByStageData.map(item => item.count),
                    backgroundColor: '#3b82f6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    // Daily Leads Trend
    const dailyCtx = document.getElementById('dailyLeadsChart');
    if (dailyCtx && dailyLeadsData.length > 0) {
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyLeadsData.map(item => item.date),
                datasets: [{
                    label: '<?php _e('Leads', 'rental-gates'); ?>',
                    data: dailyLeadsData.map(item => item.count),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
});
</script>
