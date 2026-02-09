<?php if (!defined('ABSPATH')) exit; ?>
<?php
/**
 * Staff Dashboard Template
 *
 * Sets role-specific navigation and includes the shared layout.
 */

$dashboard_role = 'staff';
$dashboard_base = '/rental-gates/staff';

$nav_groups = array(
    array(
        'label' => '',
        'items' => array(
            array('icon' => 'dashboard',   'label' => 'Overview',     'href' => '/rental-gates/staff',             'section' => ''),
            array('icon' => 'building',    'label' => 'Buildings',    'href' => '/rental-gates/staff/buildings',    'section' => 'buildings'),
            array('icon' => 'tenants',     'label' => 'Tenants',      'href' => '/rental-gates/staff/tenants',      'section' => 'tenants'),
            array('icon' => 'maintenance', 'label' => 'Maintenance',  'href' => '/rental-gates/staff/maintenance',  'section' => 'maintenance'),
        ),
    ),
    array(
        'label' => 'Communication',
        'items' => array(
            array('icon' => 'messages',      'label' => 'Messages',      'href' => '/rental-gates/staff/messages',      'section' => 'messages'),
            array('icon' => 'notifications', 'label' => 'Notifications', 'href' => '/rental-gates/staff/notifications', 'section' => 'notifications'),
        ),
    ),
    array(
        'label' => 'Account',
        'items' => array(
            array('icon' => 'settings', 'label' => 'Settings', 'href' => '/rental-gates/staff/settings', 'section' => 'settings'),
        ),
    ),
);

include RENTAL_GATES_PLUGIN_DIR . 'templates/dashboard/layout.php';
