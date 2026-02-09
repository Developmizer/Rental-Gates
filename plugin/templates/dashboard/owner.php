<?php if (!defined('ABSPATH')) exit; ?>
<?php
/**
 * Owner/Manager Dashboard Template
 *
 * Sets role-specific navigation and includes the shared layout.
 */

$dashboard_role = 'owner';
$dashboard_base = '/rental-gates/dashboard';

$nav_groups = array(
    array(
        'label' => '',
        'items' => array(
            array('icon' => 'dashboard',   'label' => 'Overview',     'href' => '/rental-gates/dashboard',             'section' => ''),
            array('icon' => 'building',    'label' => 'Buildings',    'href' => '/rental-gates/dashboard/buildings',    'section' => 'buildings'),
            array('icon' => 'tenants',     'label' => 'Tenants',      'href' => '/rental-gates/dashboard/tenants',      'section' => 'tenants'),
            array('icon' => 'lease',       'label' => 'Leases',       'href' => '/rental-gates/dashboard/leases',       'section' => 'leases'),
            array('icon' => 'payments',    'label' => 'Payments',     'href' => '/rental-gates/dashboard/payments',     'section' => 'payments'),
            array('icon' => 'maintenance', 'label' => 'Maintenance',  'href' => '/rental-gates/dashboard/maintenance',  'section' => 'maintenance'),
        ),
    ),
    array(
        'label' => 'Management',
        'items' => array(
            array('icon' => 'documents', 'label' => 'Documents', 'href' => '/rental-gates/dashboard/documents', 'section' => 'documents'),
            array('icon' => 'reports',   'label' => 'Reports',   'href' => '/rental-gates/dashboard/reports',   'section' => 'reports'),
            array('icon' => 'marketing', 'label' => 'Marketing', 'href' => '/rental-gates/dashboard/marketing', 'section' => 'marketing'),
            array('icon' => 'ai',        'label' => 'AI Tools',  'href' => '/rental-gates/dashboard/ai-tools',  'section' => 'ai-tools'),
            array('icon' => 'staff',     'label' => 'Staff',     'href' => '/rental-gates/dashboard/staff',     'section' => 'staff'),
        ),
    ),
    array(
        'label' => 'Communication',
        'items' => array(
            array('icon' => 'messages',      'label' => 'Messages',      'href' => '/rental-gates/dashboard/messages',      'section' => 'messages'),
            array('icon' => 'announcements', 'label' => 'Announcements', 'href' => '/rental-gates/dashboard/announcements', 'section' => 'announcements'),
            array('icon' => 'notifications', 'label' => 'Notifications', 'href' => '/rental-gates/dashboard/notifications', 'section' => 'notifications'),
        ),
    ),
    array(
        'label' => 'Account',
        'items' => array(
            array('icon' => 'billing',  'label' => 'Billing',  'href' => '/rental-gates/dashboard/billing',  'section' => 'billing'),
            array('icon' => 'settings', 'label' => 'Settings', 'href' => '/rental-gates/dashboard/settings', 'section' => 'settings'),
        ),
    ),
);

include RENTAL_GATES_PLUGIN_DIR . 'templates/dashboard/layout.php';
