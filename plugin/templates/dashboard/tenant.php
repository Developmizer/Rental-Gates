<?php if (!defined('ABSPATH')) exit; ?>
<?php
/**
 * Tenant Dashboard Template
 *
 * Sets role-specific navigation and includes the shared layout.
 */

$dashboard_role = 'tenant';
$dashboard_base = '/rental-gates/tenant';

$nav_groups = array(
    array(
        'label' => '',
        'items' => array(
            array('icon' => 'dashboard',   'label' => 'Overview',     'href' => '/rental-gates/tenant',             'section' => ''),
            array('icon' => 'lease',       'label' => 'My Lease',     'href' => '/rental-gates/tenant/lease',       'section' => 'lease'),
            array('icon' => 'payments',    'label' => 'Payments',     'href' => '/rental-gates/tenant/payments',    'section' => 'payments'),
            array('icon' => 'maintenance', 'label' => 'Maintenance',  'href' => '/rental-gates/tenant/maintenance', 'section' => 'maintenance'),
        ),
    ),
    array(
        'label' => 'Resources',
        'items' => array(
            array('icon' => 'documents',     'label' => 'Documents',     'href' => '/rental-gates/tenant/documents',     'section' => 'documents'),
            array('icon' => 'messages',      'label' => 'Messages',      'href' => '/rental-gates/tenant/messages',      'section' => 'messages'),
            array('icon' => 'notifications', 'label' => 'Notifications', 'href' => '/rental-gates/tenant/notifications', 'section' => 'notifications'),
        ),
    ),
    array(
        'label' => 'Account',
        'items' => array(
            array('icon' => 'settings', 'label' => 'Settings', 'href' => '/rental-gates/tenant/settings', 'section' => 'settings'),
        ),
    ),
);

include RENTAL_GATES_PLUGIN_DIR . 'templates/dashboard/layout.php';
