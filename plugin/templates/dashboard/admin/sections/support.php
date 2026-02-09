<?php
/**
 * Site Admin - Support Tools Section
 * 
 * Diagnostic tools, user impersonation, and support utilities.
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$tables = Rental_Gates_Database::get_table_names();

$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'impersonate';

// Handle impersonation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['support_nonce'])) {
    if (wp_verify_nonce($_POST['support_nonce'], 'admin_support')) {
        $action = sanitize_text_field($_POST['action_type'] ?? '');
        
        if ($action === 'impersonate') {
            $user_id = intval($_POST['user_id']);
            $user = get_user_by('ID', $user_id);
            
            if ($user && !in_array('administrator', $user->roles)) {
                // Store original user
                update_user_meta(get_current_user_id(), '_rg_impersonating', $user_id);
                update_user_meta(get_current_user_id(), '_rg_impersonate_start', current_time('mysql'));
                
                // Log the action
                $wpdb->insert($tables['activity_log'], array(
                    'user_id' => get_current_user_id(),
                    'action' => 'user_impersonation_start',
                    'entity_type' => 'user',
                    'entity_id' => $user_id,
                    'details' => wp_json_encode(array('target_user' => $user->user_email)),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'created_at' => current_time('mysql'),
                ));
                
                // Switch to user
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);
                
                // Redirect to their dashboard
                wp_redirect(home_url('/rental-gates/dashboard'));
                exit;
            }
        }
        
        if ($action === 'clear_cache') {
            // Clear transients
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rg_%' OR option_name LIKE '_transient_timeout_rg_%'");
            wp_redirect(add_query_arg(array('tab' => 'tools', 'cleared' => '1'), home_url('/rental-gates/admin/support')));
            exit;
        }
        
        if ($action === 'reset_roles') {
            // Re-create roles
            if (class_exists('Rental_Gates_Roles')) {
                Rental_Gates_Roles::create_roles();
            }
            wp_redirect(add_query_arg(array('tab' => 'tools', 'roles_reset' => '1'), home_url('/rental-gates/admin/support')));
            exit;
        }
        
        if ($action === 'upgrade_database') {
            // Force database upgrade
            if (class_exists('Rental_Gates_Database')) {
                Rental_Gates_Database::create_tables();
            }
            wp_redirect(add_query_arg(array('tab' => 'tools', 'db_upgraded' => '1'), home_url('/rental-gates/admin/support')));
            exit;
        }
    }
}

// Search users for impersonation
$search_results = array();
if (isset($_GET['user_search']) && !empty($_GET['user_search'])) {
    $search = sanitize_text_field($_GET['user_search']);
    $search_results = $wpdb->get_results($wpdb->prepare(
        "SELECT u.ID, u.user_email, u.display_name,
                (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = '{$wpdb->prefix}capabilities' LIMIT 1) as caps
         FROM {$wpdb->users} u
         WHERE (u.user_email LIKE %s OR u.display_name LIKE %s)
         AND u.ID != %d
         LIMIT 20",
        '%' . $wpdb->esc_like($search) . '%',
        '%' . $wpdb->esc_like($search) . '%',
        get_current_user_id()
    ), ARRAY_A);
    
    // Parse roles
    foreach ($search_results as &$user) {
        $caps = maybe_unserialize($user['caps']);
        $user['roles'] = array();
        if (is_array($caps)) {
            foreach ($caps as $cap => $active) {
                if ($active) {
                    $user['roles'][] = $cap;
                }
            }
        }
    }
}

// Get impersonation log
$impersonation_log = $wpdb->get_results(
    "SELECT a.*, u.display_name as admin_name
     FROM {$tables['activity_log']} a
     LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
     WHERE a.action LIKE 'user_impersonation%'
     ORDER BY a.created_at DESC
     LIMIT 20",
    ARRAY_A
) ?: array();
?>

<header class="admin-header">
    <h1 class="header-title"><?php _e('Support Tools', 'rental-gates'); ?></h1>
</header>

<div class="admin-content">
    <?php if (isset($_GET['cleared'])): ?>
    <div class="alert alert-success mb-6">
        <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/></svg>
        <?php _e('Cache cleared successfully.', 'rental-gates'); ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['roles_reset'])): ?>
    <div class="alert alert-success mb-6">
        <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/></svg>
        <?php _e('Roles and capabilities reset successfully.', 'rental-gates'); ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['db_upgraded'])): ?>
    <div class="alert alert-success mb-6">
        <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/></svg>
        <?php _e('Database upgraded successfully.', 'rental-gates'); ?>
    </div>
    <?php endif; ?>
    
    <!-- Tabs -->
    <div class="tabs">
        <a href="<?php echo home_url('/rental-gates/admin/support?tab=impersonate'); ?>" class="tab <?php echo $current_tab === 'impersonate' ? 'active' : ''; ?>">
            <?php _e('User Impersonation', 'rental-gates'); ?>
        </a>
        <a href="<?php echo home_url('/rental-gates/admin/support?tab=tools'); ?>" class="tab <?php echo $current_tab === 'tools' ? 'active' : ''; ?>">
            <?php _e('Diagnostic Tools', 'rental-gates'); ?>
        </a>
        <a href="<?php echo home_url('/rental-gates/admin/support?tab=log'); ?>" class="tab <?php echo $current_tab === 'log' ? 'active' : ''; ?>">
            <?php _e('Impersonation Log', 'rental-gates'); ?>
        </a>
    </div>
    
    <?php if ($current_tab === 'impersonate'): ?>
    <!-- User Impersonation -->
    <div class="alert alert-warning mb-6">
        <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <div>
            <strong><?php _e('Use with caution', 'rental-gates'); ?></strong><br>
            <?php _e('User impersonation allows you to log in as another user for support purposes. All impersonation sessions are logged.', 'rental-gates'); ?>
        </div>
    </div>
    
    <div class="card mb-6">
        <div class="card-header">
            <h2 class="card-title"><?php _e('Search Users', 'rental-gates'); ?></h2>
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo home_url('/rental-gates/admin/support'); ?>" class="rg-flex rg-gap-3">
                <input type="hidden" name="tab" value="impersonate">
                <div class="search-box rg-flex-1 rg-max-w-sm">
                    <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="user_search" value="<?php echo esc_attr($_GET['user_search'] ?? ''); ?>" placeholder="<?php esc_attr_e('Search by email or name...', 'rental-gates'); ?>">
                </div>
                <button type="submit" class="btn btn-primary"><?php _e('Search', 'rental-gates'); ?></button>
            </form>
        </div>
    </div>
    
    <?php if (!empty($search_results)): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?php _e('Search Results', 'rental-gates'); ?></h2>
        </div>
        <div class="card-body flush">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php _e('User', 'rental-gates'); ?></th>
                        <th><?php _e('Roles', 'rental-gates'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($search_results as $user): 
                        $is_admin = in_array('administrator', $user['roles']);
                    ?>
                    <tr>
                        <td>
                            <div class="rg-font-medium"><?php echo esc_html($user['display_name']); ?></div>
                            <div class="rg-text-secondary"><?php echo esc_html($user['user_email']); ?></div>
                        </td>
                        <td>
                            <?php foreach ($user['roles'] as $role): ?>
                            <span class="badge badge-<?php echo $role === 'administrator' ? 'danger' : 'gray'; ?> rg-mr-1">
                                <?php echo esc_html(ucwords(str_replace('_', ' ', $role))); ?>
                            </span>
                            <?php endforeach; ?>
                        </td>
                        <td class="text-right">
                            <?php if ($is_admin): ?>
                            <span class="text-muted"><?php _e('Cannot impersonate admins', 'rental-gates'); ?></span>
                            <?php else: ?>
                            <form method="post" action="" class="rg-inline">
                                <?php wp_nonce_field('admin_support', 'support_nonce'); ?>
                                <input type="hidden" name="action_type" value="impersonate">
                                <input type="hidden" name="user_id" value="<?php echo $user['ID']; ?>">
                                <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('<?php echo esc_js(__('Log in as this user?', 'rental-gates')); ?>');">
                                    <?php _e('Impersonate', 'rental-gates'); ?>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif (isset($_GET['user_search'])): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <p><?php _e('No users found matching your search.', 'rental-gates'); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php elseif ($current_tab === 'tools'): ?>
    <!-- Diagnostic Tools -->
    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?php _e('Cache Management', 'rental-gates'); ?></h2>
            </div>
            <div class="card-body">
                <p class="rg-tool-desc">
                    <?php _e('Clear all cached data and transients used by Rental Gates.', 'rental-gates'); ?>
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field('admin_support', 'support_nonce'); ?>
                    <input type="hidden" name="action_type" value="clear_cache">
                    <button type="submit" class="btn btn-secondary"><?php _e('Clear Cache', 'rental-gates'); ?></button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?php _e('Roles & Capabilities', 'rental-gates'); ?></h2>
            </div>
            <div class="card-body">
                <p class="rg-tool-desc">
                    <?php _e('Re-create all Rental Gates roles and capabilities.', 'rental-gates'); ?>
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field('admin_support', 'support_nonce'); ?>
                    <input type="hidden" name="action_type" value="reset_roles">
                    <button type="submit" class="btn btn-secondary"><?php _e('Reset Roles', 'rental-gates'); ?></button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?php _e('Database Upgrade', 'rental-gates'); ?></h2>
            </div>
            <div class="card-body">
                <p class="rg-tool-desc">
                    <?php _e('Force database schema upgrade. Safe to run multiple times.', 'rental-gates'); ?>
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field('admin_support', 'support_nonce'); ?>
                    <input type="hidden" name="action_type" value="upgrade_database">
                    <button type="submit" class="btn btn-secondary"><?php _e('Upgrade Database', 'rental-gates'); ?></button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?php _e('Debug Information', 'rental-gates'); ?></h2>
            </div>
            <div class="card-body">
                <p class="rg-tool-desc">
                    <?php _e('Copy debug info for support tickets.', 'rental-gates'); ?>
                </p>
                <?php
                $debug_info = array(
                    'Plugin Version' => defined('RENTAL_GATES_VERSION') ? RENTAL_GATES_VERSION : 'Unknown',
                    'WordPress' => get_bloginfo('version'),
                    'PHP' => phpversion(),
                    'MySQL' => $wpdb->db_version(),
                    'Site URL' => home_url(),
                    'Active Theme' => wp_get_theme()->get('Name'),
                );
                $debug_text = '';
                foreach ($debug_info as $label => $value) {
                    $debug_text .= "{$label}: {$value}\n";
                }
                ?>
                <textarea class="form-textarea font-mono" rows="6" readonly onclick="this.select()"><?php echo esc_textarea($debug_text); ?></textarea>
            </div>
        </div>
    </div>
    
    <?php elseif ($current_tab === 'log'): ?>
    <!-- Impersonation Log -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?php _e('Impersonation Log', 'rental-gates'); ?></h2>
        </div>
        <div class="card-body flush">
            <?php if (empty($impersonation_log)): ?>
            <div class="empty-state">
                <p><?php _e('No impersonation sessions recorded.', 'rental-gates'); ?></p>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?php _e('Time', 'rental-gates'); ?></th>
                        <th><?php _e('Admin', 'rental-gates'); ?></th>
                        <th><?php _e('Action', 'rental-gates'); ?></th>
                        <th><?php _e('Target User', 'rental-gates'); ?></th>
                        <th><?php _e('IP Address', 'rental-gates'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($impersonation_log as $log): 
                        $details = json_decode($log['details'], true);
                    ?>
                    <tr>
                        <td class="text-muted rg-nowrap">
                            <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                        </td>
                        <td class="rg-font-medium"><?php echo esc_html($log['admin_name'] ?? 'Unknown'); ?></td>
                        <td>
                            <span class="badge badge-<?php echo strpos($log['action'], 'start') !== false ? 'warning' : 'success'; ?>">
                                <?php echo strpos($log['action'], 'start') !== false ? __('Started', 'rental-gates') : __('Ended', 'rental-gates'); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($details['target_user'] ?? 'N/A'); ?></td>
                        <td class="text-muted font-mono rg-text-xs"><?php echo esc_html($log['ip_address']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
