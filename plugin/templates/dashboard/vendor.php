<?php if (!defined('ABSPATH')) exit; ?>
<?php
/**
 * Vendor Dashboard Template
 *
 * Sets role-specific navigation and includes the shared layout.
 */

$dashboard_role = 'vendor';
$dashboard_base = '/rental-gates/vendor';

$nav_groups = array(
    array(
        'label' => '',
        'items' => array(
            array('icon' => 'dashboard',   'label' => 'Overview',     'href' => '/rental-gates/vendor',             'section' => ''),
            array('icon' => 'maintenance', 'label' => 'Work Orders',  'href' => '/rental-gates/vendor/work-orders', 'section' => 'work-orders'),
            array('icon' => 'documents',   'label' => 'Documents',    'href' => '/rental-gates/vendor/documents',   'section' => 'documents'),
        ),
    ),
    array(
        'label' => 'Communication',
        'items' => array(
            array('icon' => 'messages',      'label' => 'Messages',      'href' => '/rental-gates/vendor/messages',      'section' => 'messages'),
            array('icon' => 'notifications', 'label' => 'Notifications', 'href' => '/rental-gates/vendor/notifications', 'section' => 'notifications'),
        ),
    ),
    array(
        'label' => 'Account',
        'items' => array(
            array('icon' => 'settings', 'label' => 'Settings', 'href' => '/rental-gates/vendor/settings', 'section' => 'settings'),
        ),
    ),
);

include RENTAL_GATES_PLUGIN_DIR . 'templates/dashboard/layout.php';
