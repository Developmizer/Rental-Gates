<?php
/**
 * Automation Settings Section
 */
if (!defined('ABSPATH')) exit;

$org_id = Rental_Gates_Roles::get_organization_id();
if (!$org_id) {
    wp_redirect(home_url('/rental-gates/login'));
    exit;
}

// Check permissions
if (!current_user_can('rg_manage_settings') && !current_user_can('manage_options')) {
    echo '<div class="rg-error-message">' . __('You do not have permission to access this page.', 'rental-gates') . '</div>';
    return;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'save_automation_settings')) {
    global $wpdb;
    $tables = Rental_Gates_Database::get_table_names();
    
    $settings = array(
        'enabled' => isset($_POST['enabled']),
        'rent_reminder_enabled' => isset($_POST['rent_reminder_enabled']),
        'rent_reminder_days' => intval($_POST['rent_reminder_days'] ?? 3),
        'overdue_alerts_enabled' => isset($_POST['overdue_alerts_enabled']),
        'late_fees_enabled' => isset($_POST['late_fees_enabled']),
        'late_fee_grace_days' => intval($_POST['late_fee_grace_days'] ?? 5),
        'late_fee_type' => sanitize_text_field($_POST['late_fee_type'] ?? 'fixed'),
        'late_fee_amount' => floatval($_POST['late_fee_amount'] ?? 50),
        'late_fee_percent' => floatval($_POST['late_fee_percent'] ?? 5),
        'lease_expiry_alerts_enabled' => isset($_POST['lease_expiry_alerts_enabled']),
        'move_reminders_enabled' => isset($_POST['move_reminders_enabled']),
    );
    
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$tables['settings']} WHERE organization_id = %d AND setting_key = 'automation_settings'",
        $org_id
    ));
    
    if ($existing) {
        $wpdb->update($tables['settings'], array('setting_value' => wp_json_encode($settings)), array('id' => $existing));
    } else {
        $wpdb->insert($tables['settings'], array('organization_id' => $org_id, 'setting_key' => 'automation_settings', 'setting_value' => wp_json_encode($settings)));
    }
    
    $saved = true;
}

// Get current settings
global $wpdb;
$tables = Rental_Gates_Database::get_table_names();
$settings_json = $wpdb->get_var($wpdb->prepare(
    "SELECT setting_value FROM {$tables['settings']} WHERE organization_id = %d AND setting_key = 'automation_settings'",
    $org_id
));

$defaults = array(
    'enabled' => true,
    'rent_reminder_enabled' => true,
    'rent_reminder_days' => 3,
    'overdue_alerts_enabled' => true,
    'late_fees_enabled' => false,
    'late_fee_grace_days' => 5,
    'late_fee_type' => 'fixed',
    'late_fee_amount' => 50,
    'late_fee_percent' => 5,
    'lease_expiry_alerts_enabled' => true,
    'move_reminders_enabled' => true,
);

$settings = $settings_json ? array_merge($defaults, json_decode($settings_json, true) ?: array()) : $defaults;
?>

<div class="rg-automation-header">
    <h1><?php _e('Automation Settings', 'rental-gates'); ?></h1>
</div>

<?php if (!empty($saved)): ?>
<div class="rg-success-message">
    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <?php _e('Settings saved successfully!', 'rental-gates'); ?>
</div>
<?php endif; ?>

<form method="post">
    <?php wp_nonce_field('save_automation_settings'); ?>
    
    <div class="rg-settings-card">
        <div class="rg-master-toggle">
            <div class="rg-master-toggle-content">
                <div class="rg-master-toggle-info">
                    <h3><?php _e('Enable Automation', 'rental-gates'); ?></h3>
                    <p><?php _e('Turn on automated notifications and tasks for your organization', 'rental-gates'); ?></p>
                </div>
                <label class="rg-toggle">
                    <input type="checkbox" name="enabled" <?php checked($settings['enabled']); ?>>
                    <span class="rg-toggle-slider"></span>
                </label>
            </div>
        </div>
    </div>
    
    <div class="rg-settings-card">
        <div class="rg-settings-card-header">
            <h3 class="rg-settings-card-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?php _e('Rent Payment Automation', 'rental-gates'); ?>
            </h3>
        </div>
        <div class="rg-settings-card-body">
            <div class="rg-setting-row">
                <div class="rg-setting-info">
                    <div class="rg-setting-label"><?php _e('Rent Payment Reminders', 'rental-gates'); ?></div>
                    <div class="rg-setting-desc"><?php _e('Send automatic reminders to tenants before rent is due', 'rental-gates'); ?></div>
                    <div class="rg-setting-inline">
                        <label><?php _e('Days before due date:', 'rental-gates'); ?></label>
                        <input type="number" name="rent_reminder_days" value="<?php echo esc_attr($settings['rent_reminder_days']); ?>" min="1" max="14">
                    </div>
                </div>
                <div class="rg-setting-control">
                    <label class="rg-toggle">
                        <input type="checkbox" name="rent_reminder_enabled" <?php checked($settings['rent_reminder_enabled']); ?>>
                        <span class="rg-toggle-slider"></span>
                    </label>
                </div>
            </div>
            
            <div class="rg-setting-row">
                <div class="rg-setting-info">
                    <div class="rg-setting-label"><?php _e('Overdue Payment Alerts', 'rental-gates'); ?></div>
                    <div class="rg-setting-desc"><?php _e('Send alerts when payments become overdue (1, 3, 7, 14, 30 days)', 'rental-gates'); ?></div>
                </div>
                <div class="rg-setting-control">
                    <label class="rg-toggle">
                        <input type="checkbox" name="overdue_alerts_enabled" <?php checked($settings['overdue_alerts_enabled']); ?>>
                        <span class="rg-toggle-slider"></span>
                    </label>
                </div>
            </div>
            
            <div class="rg-setting-row">
                <div class="rg-setting-info">
                    <div class="rg-setting-label"><?php _e('Automatic Late Fees', 'rental-gates'); ?></div>
                    <div class="rg-setting-desc"><?php _e('Automatically add late fees to overdue payments', 'rental-gates'); ?></div>
                    <div class="rg-conditional-settings <?php echo $settings['late_fees_enabled'] ? 'active' : ''; ?>" id="lateFeeSettings">
                        <div class="rg-conditional-row">
                            <label><?php _e('Grace period:', 'rental-gates'); ?></label>
                            <input type="number" name="late_fee_grace_days" value="<?php echo esc_attr($settings['late_fee_grace_days']); ?>" min="0" max="30">
                            <span class="rg-conditional-hint"><?php _e('days after due date', 'rental-gates'); ?></span>
                        </div>
                        <div class="rg-conditional-row">
                            <label><?php _e('Fee type:', 'rental-gates'); ?></label>
                            <select name="late_fee_type" id="lateFeeType">
                                <option value="fixed" <?php selected($settings['late_fee_type'], 'fixed'); ?>><?php _e('Fixed Amount', 'rental-gates'); ?></option>
                                <option value="percent" <?php selected($settings['late_fee_type'], 'percent'); ?>><?php _e('Percentage of Rent', 'rental-gates'); ?></option>
                            </select>
                        </div>
                        <div class="rg-conditional-row" id="lateFeeFixed" style="<?php echo $settings['late_fee_type'] === 'percent' ? 'display:none;' : ''; ?>">
                            <label><?php _e('Fee amount:', 'rental-gates'); ?></label>
                            <span>$</span>
                            <input type="number" name="late_fee_amount" value="<?php echo esc_attr($settings['late_fee_amount']); ?>" min="0" step="0.01" class="rg-input-sm">
                        </div>
                        <div class="rg-conditional-row" id="lateFeePercent" style="<?php echo $settings['late_fee_type'] === 'fixed' ? 'display:none;' : ''; ?>">
                            <label><?php _e('Fee percentage:', 'rental-gates'); ?></label>
                            <input type="number" name="late_fee_percent" value="<?php echo esc_attr($settings['late_fee_percent']); ?>" min="0" max="25" step="0.1" class="rg-input-xs">
                            <span>%</span>
                        </div>
                    </div>
                </div>
                <div class="rg-setting-control">
                    <label class="rg-toggle">
                        <input type="checkbox" name="late_fees_enabled" id="lateFeesToggle" <?php checked($settings['late_fees_enabled']); ?>>
                        <span class="rg-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>
    
    <div class="rg-settings-card">
        <div class="rg-settings-card-header">
            <h3 class="rg-settings-card-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <?php _e('Lease Automation', 'rental-gates'); ?>
            </h3>
        </div>
        <div class="rg-settings-card-body">
            <div class="rg-setting-row">
                <div class="rg-setting-info">
                    <div class="rg-setting-label"><?php _e('Lease Expiration Alerts', 'rental-gates'); ?></div>
                    <div class="rg-setting-desc"><?php _e('Send alerts when leases are expiring (90, 60, 30, 14, 7 days before)', 'rental-gates'); ?></div>
                </div>
                <div class="rg-setting-control">
                    <label class="rg-toggle">
                        <input type="checkbox" name="lease_expiry_alerts_enabled" <?php checked($settings['lease_expiry_alerts_enabled']); ?>>
                        <span class="rg-toggle-slider"></span>
                    </label>
                </div>
            </div>
            
            <div class="rg-setting-row">
                <div class="rg-setting-info">
                    <div class="rg-setting-label"><?php _e('Move-In / Move-Out Reminders', 'rental-gates'); ?></div>
                    <div class="rg-setting-desc"><?php _e('Send reminders for upcoming move-in (3 days) and move-out (7 days) dates', 'rental-gates'); ?></div>
                </div>
                <div class="rg-setting-control">
                    <label class="rg-toggle">
                        <input type="checkbox" name="move_reminders_enabled" <?php checked($settings['move_reminders_enabled']); ?>>
                        <span class="rg-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>
    
    <div class="rg-settings-card rg-info-card">
        <div class="rg-settings-card-body">
            <svg width="24" height="24" fill="none" stroke="#2563eb" viewBox="0 0 24 24" class="rg-info-card-icon"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <div class="rg-info-card-title"><?php _e('How Automation Works', 'rental-gates'); ?></div>
                <div class="rg-info-card-text">
                    <?php _e('Automated tasks run daily at 8:00 AM. Notifications are sent both in-app and via email based on each user\'s notification preferences. Tenants can manage their notification settings from their portal.', 'rental-gates'); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="rg-settings-submit">
        <button type="submit" class="rg-btn rg-btn-primary">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <?php _e('Save Settings', 'rental-gates'); ?>
        </button>
    </div>
</form>

<script>
document.getElementById('lateFeesToggle').addEventListener('change', function() {
    document.getElementById('lateFeeSettings').classList.toggle('active', this.checked);
});
document.getElementById('lateFeeType').addEventListener('change', function() {
    const isFixed = this.value === 'fixed';
    document.getElementById('lateFeeFixed').style.display = isFixed ? 'flex' : 'none';
    document.getElementById('lateFeePercent').style.display = isFixed ? 'none' : 'flex';
});
</script>
