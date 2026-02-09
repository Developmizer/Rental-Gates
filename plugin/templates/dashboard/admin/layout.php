<?php if (!defined('ABSPATH')) exit; ?>
<?php
/**
 * Site Admin Dashboard Template
 *
 * Sets role-specific navigation and includes the shared layout.
 */

$dashboard_role = 'admin';
$dashboard_base = '/rental-gates/admin';

$nav_groups = array(
    array(
        'label' => '',
        'items' => array(
            array('icon' => 'dashboard', 'label' => 'Overview',      'href' => '/rental-gates/admin',               'section' => ''),
            array('icon' => 'building',  'label' => 'Organizations', 'href' => '/rental-gates/admin/organizations', 'section' => 'organizations'),
            array('icon' => 'users',     'label' => 'Users',         'href' => '/rental-gates/admin/users',         'section' => 'users'),
            array('icon' => 'billing',   'label' => 'Subscriptions', 'href' => '/rental-gates/admin/subscriptions', 'section' => 'subscriptions'),
        ),
    ),
    array(
        'label' => 'Tools',
        'items' => array(
            array('icon' => 'maintenance', 'label' => 'Support', 'href' => '/rental-gates/admin/support', 'section' => 'support'),
            array('icon' => 'reports',     'label' => 'Reports', 'href' => '/rental-gates/admin/reports', 'section' => 'reports'),
        ),
    ),
    array(
        'label' => 'Account',
        'items' => array(
            array('icon' => 'settings', 'label' => 'Settings', 'href' => '/rental-gates/admin/settings', 'section' => 'settings'),
        ),
    ),
);

include RENTAL_GATES_PLUGIN_DIR . 'templates/dashboard/layout.php';
