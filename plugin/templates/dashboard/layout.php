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
    // Modern soft icons — 24×24 viewBox, stroke-width 1.5 for a light, refined look
    $s = 'xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"';
    $icons = array(
        'dashboard'     => '<svg ' . $s . '><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
        'building'      => '<svg ' . $s . '><path d="M3 21V5a2 2 0 0 1 2-2h6v18"/><path d="M11 3h8a2 2 0 0 1 2 2v16H11"/><path d="M7 8h1"/><path d="M7 12h1"/><path d="M7 16h1"/><path d="M15 8h1"/><path d="M15 12h1"/><path d="M15 16h1"/></svg>',
        'users'         => '<svg ' . $s . '><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'tenants'       => '<svg ' . $s . '><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'lease'         => '<svg ' . $s . '><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 13h4"/><path d="M10 17h4"/><path d="M10 9h1"/></svg>',
        'payments'      => '<svg ' . $s . '><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>',
        'maintenance'   => '<svg ' . $s . '><path d="M11.42 15.17 17.71 8.88a2.12 2.12 0 0 0-3-3l-6.29 6.29"/><path d="m7.13 19.87 4.29-4.7"/><path d="M14.84 4.93a5.43 5.43 0 0 1 4.23 4.23"/><path d="M4.93 14.84a5.43 5.43 0 0 0 4.23 4.23"/></svg>',
        'documents'     => '<svg ' . $s . '><path d="M20 20a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707Z"/><path d="M12 2v6a2 2 0 0 0 2 2h6"/></svg>',
        'reports'       => '<svg ' . $s . '><path d="M3 3v18h18"/><path d="M7 16l4-8 4 5 4-10"/></svg>',
        'marketing'     => '<svg ' . $s . '><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/><rect x="2" y="4" width="20" height="16" rx="2"/></svg>',
        'ai'            => '<svg ' . $s . '><path d="M12 3 L14 9 L20 9.5 L15.5 13.5 L17 20 L12 16.5 L7 20 L8.5 13.5 L4 9.5 L10 9 Z"/></svg>',
        'messages'      => '<svg ' . $s . '><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>',
        'notifications' => '<svg ' . $s . '><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>',
        'staff'         => '<svg ' . $s . '><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>',
        'settings'      => '<svg ' . $s . '><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>',
        'billing'       => '<svg ' . $s . '><path d="M4 2v20l4-2 4 2 4-2 4 2V2l-4 2-4-2-4 2Z"/><path d="M8 10h8"/><path d="M8 14h8"/></svg>',
        'announcements' => '<svg ' . $s . '><path d="m3 11 18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>',
        'logout'        => '<svg ' . $s . '><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'vendors'       => '<svg ' . $s . '><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
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
        <a href="<?php echo esc_url($dashboard_base); ?>" class="rg-sidebar-logo" aria-label="<?php esc_attr_e('Rental Gates Dashboard', 'rental-gates'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span>Rental Gates</span>
        </a>
        <button type="button" id="rg-sidebar-toggle" class="rg-sidebar-toggle" aria-label="<?php esc_attr_e('Toggle sidebar', 'rental-gates'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
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
                                <span class="rg-nav-icon" aria-hidden="true"><?php echo rg_sidebar_icon($item['icon']); ?></span>
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
        <?php
        // Generate user initials for the avatar
        $display_parts = explode(' ', trim($current_user->display_name));
        $user_initials = strtoupper(substr($display_parts[0] ?? '', 0, 1) . substr(end($display_parts), 0, 1));
        if (strlen($user_initials) < 2) $user_initials = strtoupper(substr($current_user->display_name, 0, 2));
        ?>
        <div class="rg-sidebar-user-block">
            <div class="rg-sidebar-avatar" aria-hidden="true"><?php echo esc_html($user_initials); ?></div>
            <div class="rg-sidebar-user-info">
                <span class="rg-sidebar-user-name"><?php echo esc_html($current_user->display_name); ?></span>
                <span class="rg-sidebar-user-role"><?php
                    if ($organization && is_object($organization) && !empty($organization->name)) {
                        echo esc_html($organization->name);
                    } else {
                        echo esc_html(ucfirst($dashboard_role));
                    }
                ?></span>
            </div>
        </div>
        <a href="<?php echo esc_url(wp_logout_url($dashboard_base)); ?>" class="rg-nav-link rg-nav-link--logout">
            <span class="rg-nav-icon" aria-hidden="true"><?php echo rg_sidebar_icon('logout'); ?></span>
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
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="4" y1="12" x2="20" y2="12"/>
                <line x1="4" y1="6" x2="20" y2="6"/>
                <line x1="4" y1="18" x2="20" y2="18"/>
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

    // Escape key closes mobile menu.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        }
    });
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
