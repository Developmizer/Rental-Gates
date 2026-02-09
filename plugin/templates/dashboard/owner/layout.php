<?php if (!defined('ABSPATH')) exit; ?>
<?php
/**
 * Owner Dashboard Template
 *
 * Sets role-specific navigation and includes the shared layout.
 * Replaces the legacy monolithic layout with 558 lines of inline CSS.
 */

$dashboard_role = 'owner';
$dashboard_base = home_url('/rental-gates/dashboard');

// Build notification badge count
$notification_count = 0;
if (class_exists('Rental_Gates_Notification')) {
    $notification_count = (int) Rental_Gates_Notification::get_unread_count(get_current_user_id());
}

// Build message badge count
$messages_badge = 0;
if (class_exists('Rental_Gates_Message') && class_exists('Rental_Gates_Roles')) {
    $header_org_id = Rental_Gates_Roles::get_organization_id();
    if ($header_org_id) {
        $messages_badge = (int) Rental_Gates_Message::get_unread_count($header_org_id, get_current_user_id(), 'staff');
    }
}

$nav_groups = array(
    array(
        'label' => '',
        'items' => array(
            array('icon' => 'dashboard', 'label' => __('Dashboard', 'rental-gates'), 'href' => $dashboard_base, 'section' => ''),
        ),
    ),
    array(
        'label' => __('Property', 'rental-gates'),
        'items' => array(
            array('icon' => 'building', 'label' => __('Buildings & Units', 'rental-gates'), 'href' => $dashboard_base . '/buildings', 'section' => 'buildings'),
        ),
    ),
    array(
        'label' => __('Tenants', 'rental-gates'),
        'items' => array(
            array('icon' => 'tenants', 'label' => __('Tenants', 'rental-gates'), 'href' => $dashboard_base . '/tenants', 'section' => 'tenants'),
            array('icon' => 'lease', 'label' => __('Leases', 'rental-gates'), 'href' => $dashboard_base . '/leases', 'section' => 'leases'),
        ),
    ),
    array(
        'label' => __('Financial', 'rental-gates'),
        'items' => array(
            array('icon' => 'payments', 'label' => __('Payments', 'rental-gates'), 'href' => $dashboard_base . '/payments', 'section' => 'payments'),
        ),
    ),
    array(
        'label' => __('Operations', 'rental-gates'),
        'items' => array(
            array('icon' => 'maintenance', 'label' => __('Maintenance', 'rental-gates'), 'href' => $dashboard_base . '/maintenance', 'section' => 'maintenance'),
            array('icon' => 'vendors', 'label' => __('Vendors', 'rental-gates'), 'href' => $dashboard_base . '/vendors', 'section' => 'vendors'),
        ),
    ),
    array(
        'label' => __('Team', 'rental-gates'),
        'items' => array(
            array('icon' => 'staff', 'label' => __('Staff Members', 'rental-gates'), 'href' => $dashboard_base . '/staff', 'section' => 'staff'),
        ),
    ),
    array(
        'label' => __('Tools', 'rental-gates'),
        'items' => array(
            array('icon' => 'marketing', 'label' => __('Marketing', 'rental-gates'), 'href' => $dashboard_base . '/marketing', 'section' => 'marketing'),
            array('icon' => 'reports', 'label' => __('Reports', 'rental-gates'), 'href' => $dashboard_base . '/reports', 'section' => 'reports'),
            array('icon' => 'documents', 'label' => __('Documents', 'rental-gates'), 'href' => $dashboard_base . '/documents', 'section' => 'documents'),
            array('icon' => 'ai', 'label' => __('AI Tools', 'rental-gates'), 'href' => $dashboard_base . '/ai-tools', 'section' => 'ai-tools'),
            array('icon' => 'messages', 'label' => __('Messages', 'rental-gates'), 'href' => $dashboard_base . '/messages', 'section' => 'messages', 'badge' => $messages_badge),
        ),
    ),
    array(
        'label' => __('System', 'rental-gates'),
        'items' => array(
            array('icon' => 'billing', 'label' => __('Billing', 'rental-gates'), 'href' => $dashboard_base . '/billing', 'section' => 'billing'),
            array('icon' => 'notifications', 'label' => __('Notifications', 'rental-gates'), 'href' => $dashboard_base . '/notifications', 'section' => 'notifications', 'badge' => $notification_count),
            array('icon' => 'settings', 'label' => __('Settings', 'rental-gates'), 'href' => $dashboard_base . '/settings', 'section' => 'settings'),
        ),
    ),
);

include RENTAL_GATES_PLUGIN_DIR . 'templates/dashboard/layout.php';
