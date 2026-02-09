<?php
/**
 * Site Admin Dashboard Layout
 * 
 * Full platform control panel for site administrators.
 */
if (!defined('ABSPATH')) exit;

// Ensure user is site admin or WP administrator
$can_access = current_user_can('rg_manage_platform') || current_user_can('administrator');
if (!$can_access) {
    wp_redirect(home_url('/rental-gates/login'));
    exit;
}

$current_user = wp_get_current_user();
$section = get_query_var('rental_gates_section', 'dashboard');

// Get platform stats with error handling
global $wpdb;

// Check if database class exists
if (!class_exists('Rental_Gates_Database')) {
    wp_die(__('Rental Gates Database class not found.', 'rental-gates'));
}

$tables = Rental_Gates_Database::get_table_names();

// Safe query function
function rg_safe_count($table, $where = '') {
    global $wpdb;
    $query = "SELECT COUNT(*) FROM {$table}";
    if ($where) $query .= " WHERE {$where}";
    $result = $wpdb->get_var($query);
    return $result !== null ? intval($result) : 0;
}

function rg_safe_sum($table, $column, $where = '') {
    global $wpdb;
    $query = "SELECT COALESCE(SUM({$column}), 0) FROM {$table}";
    if ($where) $query .= " WHERE {$where}";
    $result = $wpdb->get_var($query);
    return $result !== null ? floatval($result) : 0;
}

$stats = array(
    'organizations' => rg_safe_count($tables['organizations']),
    'users' => 0, // Will calculate below
    'buildings' => rg_safe_count($tables['buildings']),
    'units' => rg_safe_count($tables['units']),
    'tenants' => rg_safe_count($tables['tenants']),
    'active_leases' => rg_safe_count($tables['leases'], "status = 'active'"),
    'payments_total' => rg_safe_sum($tables['payments'], 'amount', "status = 'completed'"),
    'mrr' => 0,
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Site Admin', 'rental-gates'); ?> - Rental Gates</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <?php wp_head(); ?>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --info: #3b82f6;
            --info-light: #dbeafe;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --sidebar-width: 260px;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; 
            background: var(--gray-100);
            color: var(--gray-900);
        }
        
        /* Layout */
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .admin-sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1e1b4b 0%, #312e81 100%);
            color: #fff;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 100;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #fff;
        }
        
        .sidebar-brand-icon {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .sidebar-brand-text {
            font-size: 18px;
            font-weight: 700;
        }
        
        .sidebar-brand-badge {
            font-size: 10px;
            background: var(--warning);
            color: var(--gray-900);
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
            margin-left: 4px;
        }
        
        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
        }
        
        .nav-section {
            margin-bottom: 24px;
        }
        
        .nav-section-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: rgba(255,255,255,0.5);
            padding: 0 12px;
            margin-bottom: 8px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            margin-bottom: 2px;
        }
        
        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        
        .nav-item.active {
            background: rgba(255,255,255,0.15);
            color: #fff;
        }
        
        .nav-item svg {
            width: 20px;
            height: 20px;
            opacity: 0.8;
        }
        
        .nav-item.active svg {
            opacity: 1;
        }
        
        .nav-item-badge {
            margin-left: auto;
            background: var(--danger);
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
        }
        
        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px;
            border-radius: 8px;
            text-decoration: none;
            color: rgba(255,255,255,0.8);
            transition: background 0.2s;
        }
        
        .sidebar-user:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar-user-avatar {
            width: 36px;
            height: 36px;
            background: var(--primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            color: #fff;
        }
        
        .sidebar-user-info {
            flex: 1;
            min-width: 0;
        }
        
        .sidebar-user-name {
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .sidebar-user-role {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }
        
        /* Main Content */
        .admin-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        /* Header */
        .admin-header {
            background: #fff;
            border-bottom: 1px solid var(--gray-200);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        
        .header-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--gray-900);
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .header-btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
        }
        
        .header-btn-secondary:hover {
            background: var(--gray-200);
        }
        
        .header-btn-primary {
            background: var(--primary);
            color: #fff;
        }
        
        .header-btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .header-btn svg {
            width: 18px;
            height: 18px;
        }
        
        /* Content */
        .admin-content {
            padding: 32px;
        }
        
        /* Cards */
        .card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .card-body {
            padding: 24px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            padding: 24px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-icon svg {
            width: 24px;
            height: 24px;
        }
        
        .stat-icon.primary { background: var(--info-light); color: var(--info); }
        .stat-icon.success { background: var(--success-light); color: var(--success); }
        .stat-icon.warning { background: var(--warning-light); color: var(--warning); }
        .stat-icon.danger { background: var(--danger-light); color: var(--danger); }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--gray-500);
        }
        
        .stat-change {
            font-size: 12px;
            font-weight: 500;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .stat-change.positive { color: var(--success); }
        .stat-change.negative { color: var(--danger); }
        
        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .data-table th {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-500);
            background: var(--gray-50);
        }
        
        .data-table td {
            font-size: 14px;
            color: var(--gray-700);
        }
        
        .data-table tbody tr:hover {
            background: var(--gray-50);
        }
        
        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success { background: var(--success-light); color: #065f46; }
        .badge-warning { background: var(--warning-light); color: #92400e; }
        .badge-danger { background: var(--danger-light); color: #991b1b; }
        .badge-info { background: var(--info-light); color: #1e40af; }
        .badge-gray { background: var(--gray-100); color: var(--gray-600); }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        
        .btn-secondary { background: var(--gray-100); color: var(--gray-700); }
        .btn-secondary:hover { background: var(--gray-200); }
        
        .btn-success { background: var(--success); color: #fff; }
        .btn-success:hover { background: #059669; }
        
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-300);
            color: var(--gray-700);
        }
        
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .btn svg {
            width: 18px;
            height: 18px;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 6px;
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 14px;
            color: var(--gray-900);
            background: #fff;
            transition: all 0.2s;
        }
        
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .form-hint {
            font-size: 12px;
            color: var(--gray-500);
            margin-top: 4px;
        }
        
        /* Grid */
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
        
        /* Utilities */
        .mb-4 { margin-bottom: 16px; }
        .mb-6 { margin-bottom: 24px; }
        .mb-8 { margin-bottom: 32px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-muted { color: var(--gray-500); }
        .text-success { color: var(--success); }
        .text-danger { color: var(--danger); }
        .font-mono { font-family: ui-monospace, monospace; }
        
        /* Alert */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .alert-info { background: var(--info-light); color: #1e40af; }
        .alert-success { background: var(--success-light); color: #065f46; }
        .alert-warning { background: var(--warning-light); color: #92400e; }
        .alert-danger { background: var(--danger-light); color: #991b1b; }
        
        .alert svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 24px;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: #fff;
            border-radius: 16px;
            width: 100%;
            max-width: 560px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .modal-close {
            background: none;
            border: none;
            padding: 8px;
            cursor: pointer;
            color: var(--gray-400);
            border-radius: 8px;
        }
        
        .modal-close:hover {
            background: var(--gray-100);
            color: var(--gray-600);
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 24px;
        }
        
        .tab {
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-500);
            text-decoration: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            transition: all 0.2s;
        }
        
        .tab:hover {
            color: var(--gray-700);
        }
        
        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        /* Search */
        .search-box {
            position: relative;
            max-width: 320px;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 14px;
        }
        
        .search-box svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: var(--gray-400);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 24px;
            color: var(--gray-500);
        }
        
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            color: var(--gray-300);
        }
        
        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
        }
        
        .empty-state p {
            font-size: 14px;
            margin-bottom: 24px;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            
            .admin-sidebar.open {
                transform: translateX(0);
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .stats-grid { grid-template-columns: 1fr; }
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <a href="<?php echo home_url('/rental-gates/admin'); ?>" class="sidebar-brand">
                    <div class="sidebar-brand-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div>
                        <span class="sidebar-brand-text">Rental Gates</span>
                        <span class="sidebar-brand-badge">ADMIN</span>
                    </div>
                </a>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title"><?php _e('Overview', 'rental-gates'); ?></div>
                    <a href="<?php echo home_url('/rental-gates/admin'); ?>" class="nav-item <?php echo $section === 'dashboard' ? 'active' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        <?php _e('Dashboard', 'rental-gates'); ?>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title"><?php _e('Platform', 'rental-gates'); ?></div>
                    <a href="<?php echo home_url('/rental-gates/admin/organizations'); ?>" class="nav-item <?php echo $section === 'organizations' ? 'active' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <?php _e('Organizations', 'rental-gates'); ?>
                        <span class="nav-item-badge"><?php echo number_format($stats['organizations']); ?></span>
                    </a>
                    <a href="<?php echo home_url('/rental-gates/admin/users'); ?>" class="nav-item <?php echo $section === 'users' ? 'active' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <?php _e('Users', 'rental-gates'); ?>
                    </a>
                    <a href="<?php echo home_url('/rental-gates/admin/plans'); ?>" class="nav-item <?php echo $section === 'plans' ? 'active' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        <?php _e('Plans & Billing', 'rental-gates'); ?>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title"><?php _e('Configuration', 'rental-gates'); ?></div>
                    <a href="<?php echo home_url('/rental-gates/admin/settings'); ?>" class="nav-item <?php echo $section === 'settings' ? 'active' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <?php _e('Settings', 'rental-gates'); ?>
                    </a>
                    <a href="<?php echo home_url('/rental-gates/admin/email-templates'); ?>" class="nav-item <?php echo $section === 'email-templates' ? 'active' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <?php _e('Email Templates', 'rental-gates'); ?>
                    </a>
                    <a href="<?php echo home_url('/rental-gates/admin/integrations'); ?>" class="nav-item <?php echo $section === 'integrations' ? 'active' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>
                        <?php _e('Integrations', 'rental-gates'); ?>
                    </a>
                    <a href="<?php echo home_url('/rental-gates/admin/features'); ?>" class="nav-item <?php echo $section === 'features' ? 'active' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        <?php _e('Feature Flags', 'rental-gates'); ?>
                    </a>
                    <a href="<?php echo home_url('/rental-gates/admin/pwa'); ?>" class="nav-item <?php echo $section === 'pwa' ? 'active' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <?php _e('PWA Settings', 'rental-gates'); ?>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title"><?php _e('Monitoring', 'rental-gates'); ?></div>
                    <a href="<?php echo home_url('/rental-gates/admin/activity'); ?>" class="nav-item <?php echo $section === 'activity' ? 'active' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <?php _e('Activity Log', 'rental-gates'); ?>
                    </a>
                    <a href="<?php echo home_url('/rental-gates/admin/reports'); ?>" class="nav-item <?php echo $section === 'reports' ? 'active' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        <?php _e('Reports', 'rental-gates'); ?>
                    </a>
                    <a href="<?php echo home_url('/rental-gates/admin/system'); ?>" class="nav-item <?php echo $section === 'system' ? 'active' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                        <?php _e('System Health', 'rental-gates'); ?>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title"><?php _e('Support', 'rental-gates'); ?></div>
                    <a href="<?php echo home_url('/rental-gates/admin/support'); ?>" class="nav-item <?php echo $section === 'support' ? 'active' : ''; ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <?php _e('Support Tools', 'rental-gates'); ?>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <a href="<?php echo home_url('/rental-gates/admin/profile'); ?>" class="sidebar-user">
                    <div class="sidebar-user-avatar">
                        <?php echo strtoupper(substr($current_user->display_name, 0, 1)); ?>
                    </div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name"><?php echo esc_html($current_user->display_name); ?></div>
                        <div class="sidebar-user-role"><?php _e('Site Administrator', 'rental-gates'); ?></div>
                    </div>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="admin-main">
            <?php
            // Load section - use directory relative to this file
            $sections_dir = dirname(__FILE__) . '/sections/';
            $section_file = $sections_dir . $section . '.php';
            if (file_exists($section_file)) {
                include $section_file;
            } else {
                include $sections_dir . 'dashboard.php';
            }
            ?>
        </main>
    </div>
    
    <?php wp_footer(); ?>
    
    <script>
    // Close dropdowns on click outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('open'));
        }
    });
    
    // Modal functions
    function openModal(id) {
        document.getElementById(id).classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
    </script>
</body>
</html>
