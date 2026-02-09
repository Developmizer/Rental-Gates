<?php
/**
 * Shared Dashboard Shell Template
 *
 * Used by all role-specific dashboard templates. Expects these variables:
 *   $dashboard_role    - string: 'owner', 'staff', 'tenant', 'vendor', 'admin'
 *   $dashboard_base    - string: base URL like '/rental-gates/dashboard'
 *   $nav_items         - array of nav items (icon, label, href, section, badge?)
 *   $nav_groups        - array of groups (label, items[])
 *   $content_template  - string: path to the section template to include
 *   $organization      - object|null: current organization
 *   $current_page      - string: current section slug
 */
if (!defined('ABSPATH')) exit;

/**
 * Return inline SVG markup for a given icon name.
 *
 * All icons are 24x24 stroke-based SVGs.
 *
 * @param string $name Icon identifier.
 * @return string SVG markup.
 */
if (!function_exists('rg_sidebar_icon')) :
function rg_sidebar_icon($name) {
    $icons = array(
        'dashboard'     => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
        'building'      => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20"/><line x1="9" y1="6" x2="9" y2="6.01"/><line x1="15" y1="6" x2="15" y2="6.01"/><line x1="9" y1="10" x2="9" y2="10.01"/><line x1="15" y1="10" x2="15" y2="10.01"/><line x1="9" y1="14" x2="9" y2="14.01"/><line x1="15" y1="14" x2="15" y2="14.01"/><rect x="9" y="18" width="6" height="4"/></svg>',
        'users'         => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'tenants'       => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'lease'         => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
        'payments'      => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
        'maintenance'   => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
        'documents'     => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
        'reports'       => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'marketing'     => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/><path d="M3 11l2-2 2 2"/><path d="M17 11l2-2 2 2"/></svg>',
        'ai'            => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.09 6.26L20 10l-5.91 1.74L12 18l-2.09-6.26L4 10l5.91-1.74L12 2z"/><path d="M20 16l1.04 3.13L24 20l-2.96.87L20 24l-1.04-3.13L16 20l2.96-.87L20 16z"/></svg>',
        'messages'      => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        'notifications' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        'staff'         => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>',
        'settings'      => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1.08-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1.08 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1.08 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1.08z"/></svg>',
        'billing'       => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l4-2 4 2 4-2 4 2V2l-4 2-4-2-4 2z"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="16" y2="14"/></svg>',
        'announcements' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 10c0 3.866-7 11-7 11S5 13.866 5 10"/><path d="M21 3l-5 5"/><path d="M8 8L3 3"/><circle cx="12" cy="10" r="3"/></svg>',
        'logout'        => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'vendors'       => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
    );

    return isset($icons[$name]) ? $icons[$name] : '';
}
endif;

if (!function_exists('rg_generate_breadcrumbs')) :
/**
 * Generate breadcrumb trail from the dashboard base and current page.
 *
 * @param string $dashboard_base Base dashboard URL.
 * @param string $current_page   Current section slug.
 * @return array Array of ['label' => string, 'href' => string|null].
 */
function rg_generate_breadcrumbs($dashboard_base, $current_page) {
    $crumbs = array(
        array('label' => 'Dashboard', 'href' => esc_url($dashboard_base)),
    );

    if (empty($current_page) || $current_page === 'overview') {
        // On the dashboard home, make "Dashboard" the final (non-linked) crumb.
        $crumbs[0]['href'] = null;
        return $crumbs;
    }

    // Parse additional segments from the current request URI.
    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $base_path   = wp_parse_url($dashboard_base, PHP_URL_PATH);
    $relative    = $base_path ? str_replace($base_path, '', wp_parse_url($request_uri, PHP_URL_PATH)) : '';
    $segments    = array_values(array_filter(explode('/', trim($relative, '/'))));

    if (empty($segments)) {
        $segments = array($current_page);
    }

    $running_path = $dashboard_base;

    foreach ($segments as $index => $segment) {
        $running_path .= '/' . $segment;
        $is_last       = ($index === count($segments) - 1);

        // Determine a human-readable label.
        if (is_numeric($segment)) {
            // Numeric ID -- label as "Item #ID".
            $prev = isset($segments[$index - 1]) ? $segments[$index - 1] : '';
            $noun = $prev ? rtrim(ucfirst(str_replace('-', ' ', $prev)), 's') : 'Item';
            $label = $noun . ' #' . $segment;
        } else {
            $label = ucfirst(str_replace('-', ' ', $segment));
        }

        $crumbs[] = array(
            'label' => $label,
            'href'  => $is_last ? null : esc_url($running_path),
        );
    }

    return $crumbs;
}
endif;

// ---------------------------------------------------------------------------
// Prepare template variables
// ---------------------------------------------------------------------------
$current_user    = wp_get_current_user();
$section_label   = ucfirst(str_replace('-', ' ', $current_page ?: 'Dashboard'));
$page_title      = esc_html($section_label . ' - Rental Gates Dashboard');
$breadcrumbs     = rg_generate_breadcrumbs($dashboard_base, $current_page);
$notification_count = 0;
if (function_exists('rg_get_unread_notification_count')) {
    $notification_count = (int) rg_get_unread_notification_count($current_user->ID);
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('rg-dashboard rg-dashboard--' . esc_attr($dashboard_role)); ?>>

<a href="#rg-main-content" class="rg-skip-link"><?php esc_html_e('Skip to content', 'rental-gates'); ?></a>

<!-- Sidebar -->
<aside id="rg-sidebar" class="rg-sidebar" role="navigation" aria-label="<?php esc_attr_e('Dashboard navigation', 'rental-gates'); ?>">

    <!-- Sidebar Header -->
    <div class="rg-sidebar-header">
        <a href="<?php echo esc_url($dashboard_base); ?>" class="rg-sidebar-logo">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span>Rental Gates</span>
        </a>
        <button type="button" id="rg-sidebar-toggle" class="rg-sidebar-toggle" aria-label="<?php esc_attr_e('Toggle sidebar', 'rental-gates'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
        </button>
    </div>

    <!-- Sidebar Navigation -->
    <nav class="rg-sidebar-nav">
        <?php foreach ($nav_groups as $group) : ?>
            <div class="rg-nav-group">
                <?php if (!empty($group['label'])) : ?>
                    <span class="rg-nav-group-label"><?php echo esc_html($group['label']); ?></span>
                <?php endif; ?>
                <ul class="rg-nav-list">
                    <?php foreach ($group['items'] as $item) :
                        $is_active = ($current_page === $item['section']);
                    ?>
                        <li class="rg-nav-item">
                            <a href="<?php echo esc_url($item['href']); ?>"
                               class="rg-nav-link<?php echo $is_active ? ' active' : ''; ?>"
                               <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                                <span class="rg-nav-icon"><?php echo rg_sidebar_icon($item['icon']); ?></span>
                                <span class="rg-nav-label"><?php echo esc_html($item['label']); ?></span>
                                <?php if (!empty($item['badge'])) : ?>
                                    <span class="rg-nav-badge"><?php echo (int) $item['badge']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </nav>

    <!-- Sidebar Footer -->
    <div class="rg-sidebar-footer">
        <?php if ($organization) : ?>
            <div class="rg-sidebar-org" title="<?php echo esc_attr(is_object($organization) ? $organization->name : ''); ?>">
                <span><?php echo esc_html(is_object($organization) ? $organization->name : ''); ?></span>
            </div>
        <?php endif; ?>
        <div class="rg-sidebar-user">
            <span class="rg-sidebar-user-name"><?php echo esc_html($current_user->display_name); ?></span>
            <span class="rg-sidebar-user-role"><?php echo esc_html(ucfirst($dashboard_role)); ?></span>
        </div>
        <a href="<?php echo esc_url(wp_logout_url($dashboard_base)); ?>" class="rg-nav-link rg-nav-link--logout">
            <span class="rg-nav-icon"><?php echo rg_sidebar_icon('logout'); ?></span>
            <span class="rg-nav-label"><?php esc_html_e('Log Out', 'rental-gates'); ?></span>
        </a>
    </div>

</aside>

<!-- Mobile Overlay -->
<div id="rg-sidebar-overlay" class="rg-sidebar-overlay"></div>

<!-- Main Wrapper -->
<div class="rg-main">

    <!-- Topbar -->
    <header class="rg-topbar" role="banner">
        <button type="button" id="rg-mobile-menu-btn" class="rg-mobile-menu-btn" aria-label="<?php esc_attr_e('Open menu', 'rental-gates'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <nav class="rg-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'rental-gates'); ?>">
            <ol class="rg-breadcrumb-list">
                <?php foreach ($breadcrumbs as $i => $crumb) : ?>
                    <li class="rg-breadcrumb-item">
                        <?php if ($crumb['href']) : ?>
                            <a href="<?php echo esc_url($crumb['href']); ?>"><?php echo esc_html($crumb['label']); ?></a>
                            <span class="rg-breadcrumb-separator" aria-hidden="true">/</span>
                        <?php else : ?>
                            <span class="rg-breadcrumb-current" aria-current="page"><?php echo esc_html($crumb['label']); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <div class="rg-topbar-actions">
            <a href="<?php echo esc_url($dashboard_base . '/notifications'); ?>" class="rg-notification-badge" aria-label="<?php esc_attr_e('Notifications', 'rental-gates'); ?>">
                <?php echo rg_sidebar_icon('notifications'); ?>
                <?php if ($notification_count > 0) : ?>
                    <span class="rg-nav-badge"><?php echo (int) $notification_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo esc_url($dashboard_base . '/settings'); ?>" class="rg-user-menu">
                <?php echo get_avatar($current_user->ID, 32, '', '', array('class' => 'rg-user-avatar')); ?>
                <span class="rg-nav-label"><?php echo esc_html($current_user->display_name); ?></span>
            </a>
        </div>
    </header>

    <!-- Content Area -->
    <main id="rg-main-content" class="rg-content" role="main">
        <?php
        if ($content_template && file_exists($content_template)) {
            include $content_template;
        }
        ?>
    </main>

</div><!-- .rg-main -->

<script>
(function () {
    var STORAGE_KEY = 'rg_sidebar_collapsed';
    var sidebar     = document.getElementById('rg-sidebar');
    var toggle      = document.getElementById('rg-sidebar-toggle');
    var mobileBtn   = document.getElementById('rg-mobile-menu-btn');
    var overlay     = document.getElementById('rg-sidebar-overlay');

    // Restore collapsed state from localStorage.
    if (localStorage.getItem(STORAGE_KEY) === '1') {
        sidebar.classList.add('collapsed');
    }

    // Desktop toggle.
    if (toggle) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem(STORAGE_KEY, sidebar.classList.contains('collapsed') ? '1' : '0');
        });
    }

    // Mobile menu open.
    if (mobileBtn) {
        mobileBtn.addEventListener('click', function () {
            sidebar.classList.add('open');
            overlay.classList.add('active');
        });
    }

    // Overlay click closes mobile menu.
    if (overlay) {
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
