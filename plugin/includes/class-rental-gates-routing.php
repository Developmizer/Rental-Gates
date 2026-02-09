<?php
if (!defined('ABSPATH')) exit;

/**
 * URL Routing Handler
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: custom_routes, page routing, template loading, dashboard sections
 */
class Rental_Gates_Routing {

    public function __construct() {
        add_action('template_redirect', array($this, 'handle_custom_routes'));
    }

    /**
     * Handle custom routes - main URL router
     */
    public function handle_custom_routes()
    {
        $page = null;
        $request_uri = isset($_SERVER['REQUEST_URI']) ? urldecode($_SERVER['REQUEST_URI']) : '';

        if (preg_match('#^/rental-gates/(.*)#', parse_url($request_uri, PHP_URL_PATH), $matches)) {
            $path = trim($matches[1], '/');
            $path_parts = explode('/', $path);

            if (empty($path) || $path === 'map') {
                $page = 'map';
            } elseif ($path_parts[0] === 'dashboard') {
                $page = 'owner-dashboard';
            } elseif ($path_parts[0] === 'staff') {
                $page = 'staff-dashboard';
            } elseif ($path_parts[0] === 'tenant') {
                $page = 'tenant-portal';
            } elseif ($path_parts[0] === 'vendor') {
                $page = 'vendor-portal';
            } elseif ($path_parts[0] === 'admin') {
                $page = 'site-admin';
            } elseif ($path_parts[0] === 'login') {
                $page = 'login';
            } elseif ($path_parts[0] === 'register') {
                $page = 'register';
            } elseif ($path_parts[0] === 'reset-password') {
                $page = 'reset-password';
            } elseif ($path_parts[0] === 'checkout') {
                $page = 'checkout';
            } elseif ($path_parts[0] === 'pricing') {
                $page = 'pricing';
            } elseif ($path_parts[0] === 'about') {
                $page = 'about';
            } elseif ($path_parts[0] === 'contact') {
                $page = 'contact';
            } elseif ($path_parts[0] === 'faq') {
                $page = 'faq';
            } elseif ($path_parts[0] === 'privacy') {
                $page = 'privacy';
            } elseif ($path_parts[0] === 'terms') {
                $page = 'terms';
            } elseif ($path_parts[0] === 'apply') {
                $page = 'apply';
            } elseif ($path_parts[0] === 'building' || $path_parts[0] === 'b') {
                $page = 'building';
            } elseif ($path_parts[0] === 'listings') {
                $page = 'unit';
            } elseif ($path_parts[0] === 'profile') {
                $page = 'org-profile';
            }
        }

        if (!$page) {
            $page = get_query_var('rental_gates_page');
        }

        if (!$page) {
            return;
        }

        header('X-Rental-Gates-Page: ' . $page);

        switch ($page) {
            case 'map':
                $this->load_template('public/map');
                break;
            case 'building':
                $this->load_template('public/building');
                break;
            case 'unit':
                $this->load_template('public/unit');
                break;
            case 'org-profile':
                $this->load_template('public/org-profile');
                break;
            case 'owner-dashboard':
                if (!$this->check_role(array('rental_gates_owner', 'rental_gates_manager'))) {
                    wp_redirect(home_url('/rental-gates/login'));
                    exit;
                }
                $this->load_template('dashboard/owner');
                break;
            case 'staff-dashboard':
                if (!$this->check_role(array('rental_gates_staff'))) {
                    wp_redirect(home_url('/rental-gates/login'));
                    exit;
                }
                $this->load_template('dashboard/staff');
                break;
            case 'tenant-portal':
                if (!$this->check_role(array('rental_gates_tenant'))) {
                    wp_redirect(home_url('/rental-gates/login'));
                    exit;
                }
                $this->load_template('dashboard/tenant');
                break;
            case 'vendor-portal':
                if (!$this->check_role(array('rental_gates_vendor'))) {
                    wp_redirect(home_url('/rental-gates/login'));
                    exit;
                }
                $this->load_template('dashboard/vendor');
                break;
            case 'site-admin':
                if (!$this->check_role(array('rental_gates_site_admin'))) {
                    wp_redirect(home_url('/rental-gates/login'));
                    exit;
                }
                $this->load_template('dashboard/admin/layout');
                break;
            case 'login':
                if (is_user_logged_in()) {
                    $this->redirect_to_dashboard();
                }
                $this->load_template('auth/login');
                break;
            case 'register':
                if (is_user_logged_in()) {
                    $this->redirect_to_dashboard();
                }
                $this->load_template('auth/register');
                break;
            case 'reset-password':
                $this->load_template('auth/reset-password');
                break;
            case 'checkout':
                if (!is_user_logged_in()) {
                    wp_redirect(home_url('/rental-gates/register'));
                    exit;
                }
                $this->load_template('auth/checkout');
                break;
            case 'pricing':
                $this->load_template('pricing');
                break;
            case 'about':
                $this->load_template('public/about');
                break;
            case 'contact':
                $this->load_template('public/contact');
                break;
            case 'faq':
                $this->load_template('public/faq');
                break;
            case 'privacy':
                $this->load_template('public/privacy');
                break;
            case 'terms':
                $this->load_template('public/terms');
                break;
            case 'apply':
                $this->load_template('public/apply');
                break;
        }

        exit;
    }

    private function check_role($roles)
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        foreach ($roles as $role) {
            if (in_array($role, (array) $user->roles)) {
                return true;
            }
        }

        if (in_array('rental_gates_site_admin', (array) $user->roles) || in_array('administrator', (array) $user->roles)) {
            return true;
        }

        return false;
    }

    private function redirect_to_dashboard()
    {
        $user = wp_get_current_user();

        if (in_array('administrator', (array) $user->roles)) {
            wp_redirect(home_url('/rental-gates/dashboard'));
        } elseif (in_array('rental_gates_site_admin', (array) $user->roles)) {
            wp_redirect(home_url('/rental-gates/admin'));
        } elseif (array_intersect(array('rental_gates_owner', 'rental_gates_manager'), (array) $user->roles)) {
            wp_redirect(home_url('/rental-gates/dashboard'));
        } elseif (in_array('rental_gates_staff', (array) $user->roles)) {
            wp_redirect(home_url('/rental-gates/staff'));
        } elseif (in_array('rental_gates_tenant', (array) $user->roles)) {
            wp_redirect(home_url('/rental-gates/tenant'));
        } elseif (in_array('rental_gates_vendor', (array) $user->roles)) {
            wp_redirect(home_url('/rental-gates/vendor'));
        } else {
            wp_redirect(home_url('/rental-gates/map'));
        }
        exit;
    }

    private function load_template($template)
    {
        $template_path = RENTAL_GATES_PLUGIN_DIR . 'templates/' . $template . '.php';

        if (!file_exists($template_path)) {
            $template_path = RENTAL_GATES_PLUGIN_DIR . 'templates/' . $template . '/layout.php';
        }

        if (file_exists($template_path)) {
            $current_page = 'dashboard';
            if (isset($_SERVER['REQUEST_URI'])) {
                $uri = urldecode($_SERVER['REQUEST_URI']);
                if (preg_match('#/rental-gates/dashboard/([^?]+)#', $uri, $matches)) {
                    $current_page = trim($matches[1], '/');
                }
            }

            if ($current_page === 'dashboard' || empty($current_page)) {
                $query_section = get_query_var('rental_gates_section');
                if (!empty($query_section)) {
                    $current_page = $query_section;
                }
            }

            $organization = null;
            $page_title = __('Dashboard', 'rental-gates');
            $content_template = null;

            if (is_user_logged_in() && class_exists('Rental_Gates_Roles')) {
                try {
                    $org_id = Rental_Gates_Roles::get_organization_id();
                    if ($org_id && class_exists('Rental_Gates_Organization')) {
                        $organization = Rental_Gates_Organization::get($org_id);
                    }
                } catch (Exception $e) {
                    error_log('Rental Gates: Error getting organization: ' . $e->getMessage());
                    $organization = null;
                }
            }

            if (strpos($template, 'dashboard/') === 0) {
                $content_template = $this->get_dashboard_content_template($current_page);
            }

            ob_start();
            try {
                include $template_path;
                ob_end_flush();
            } catch (Exception $e) {
                ob_end_clean();
                error_log('Rental Gates: Template error: ' . $e->getMessage());
                wp_die('Error loading template: ' . esc_html($e->getMessage()));
            }
            exit;
        } else {
            error_log('Rental Gates: Template not found: ' . $template . ' (tried: ' . $template_path . ')');
            wp_die(__('Template not found: ', 'rental-gates') . esc_html($template));
        }
    }

    private function get_dashboard_content_template($section)
    {
        $sections_dir = RENTAL_GATES_PLUGIN_DIR . 'templates/dashboard/sections/';

        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = urldecode($_SERVER['REQUEST_URI']);
            if (preg_match('#/rental-gates/dashboard/([^?]+)#', $uri, $matches)) {
                $section = trim($matches[1], '/');
            }
        }

        $parts = explode('/', trim($section, '/'));
        $base = $parts[0] ?? 'dashboard';

        switch ($base) {
            case 'dashboard':
            case '':
                return $sections_dir . 'overview.php';

            case 'buildings':
                if (count($parts) === 1) {
                    return $sections_dir . 'buildings.php';
                } elseif ($parts[1] === 'add') {
                    $_GET['id'] = 0;
                    return $sections_dir . 'buildings-form.php';
                } elseif (is_numeric($parts[1])) {
                    $_GET['id'] = intval($parts[1]);
                    if (count($parts) === 2) {
                        return $sections_dir . 'building-detail.php';
                    } elseif (isset($parts[2]) && $parts[2] === 'edit') {
                        return $sections_dir . 'buildings-form.php';
                    } elseif (isset($parts[2]) && $parts[2] === 'units') {
                        $_GET['building_id'] = intval($parts[1]);
                        if (isset($parts[3]) && $parts[3] === 'add') {
                            $_GET['unit_id'] = 0;
                            return $sections_dir . 'unit-form.php';
                        } elseif (isset($parts[3]) && is_numeric($parts[3])) {
                            $_GET['unit_id'] = intval($parts[3]);
                            if (isset($parts[4]) && $parts[4] === 'edit') {
                                return $sections_dir . 'unit-form.php';
                            }
                            return $sections_dir . 'unit-detail.php';
                        }
                    }
                }
                return $sections_dir . 'buildings.php';

            case 'tenants':
                if (count($parts) === 1) {
                    return $sections_dir . 'tenants.php';
                } elseif ($parts[1] === 'add') {
                    return $sections_dir . 'tenant-form.php';
                } elseif (is_numeric($parts[1])) {
                    if (count($parts) === 2) {
                        return $sections_dir . 'tenant-detail.php';
                    } elseif (isset($parts[2]) && $parts[2] === 'edit') {
                        return $sections_dir . 'tenant-form.php';
                    }
                }
                return $sections_dir . 'tenants.php';

            case 'leases':
                if (count($parts) === 1) {
                    return $sections_dir . 'leases.php';
                } elseif ($parts[1] === 'add') {
                    return $sections_dir . 'lease-form.php';
                } elseif (is_numeric($parts[1])) {
                    if (count($parts) === 2) {
                        return $sections_dir . 'lease-detail.php';
                    } elseif (isset($parts[2]) && $parts[2] === 'edit') {
                        return $sections_dir . 'lease-form.php';
                    }
                }
                return $sections_dir . 'leases.php';

            case 'applications':
                if (count($parts) === 1) {
                    return $sections_dir . 'applications.php';
                } elseif (is_numeric($parts[1])) {
                    return $sections_dir . 'application-detail.php';
                }
                return $sections_dir . 'applications.php';

            case 'payments':
                if (count($parts) === 1) {
                    return $sections_dir . 'payments.php';
                } elseif ($parts[1] === 'add') {
                    return $sections_dir . 'payment-form.php';
                } elseif ($parts[1] === 'return') {
                    return $sections_dir . 'payment-return.php';
                } elseif (is_numeric($parts[1])) {
                    if (isset($parts[2]) && $parts[2] === 'edit') {
                        return $sections_dir . 'payment-form.php';
                    }
                    return $sections_dir . 'payment-detail.php';
                }
                return $sections_dir . 'payments.php';

            case 'invoice':
                return $sections_dir . 'invoice-view.php';

            case 'maintenance':
                if (count($parts) === 1) {
                    return $sections_dir . 'maintenance.php';
                } elseif ($parts[1] === 'add') {
                    return $sections_dir . 'maintenance-form.php';
                } elseif (is_numeric($parts[1])) {
                    if (isset($parts[2]) && $parts[2] === 'edit') {
                        return $sections_dir . 'maintenance-form.php';
                    }
                    return $sections_dir . 'maintenance-detail.php';
                }
                return $sections_dir . 'maintenance.php';

            case 'vendors':
                if (count($parts) === 1) {
                    return $sections_dir . 'vendors.php';
                } elseif ($parts[1] === 'add') {
                    return $sections_dir . 'vendor-form.php';
                } elseif (is_numeric($parts[1])) {
                    if (isset($parts[2]) && $parts[2] === 'edit') {
                        return $sections_dir . 'vendor-form.php';
                    }
                    return $sections_dir . 'vendor-detail.php';
                }
                return $sections_dir . 'vendors.php';

            case 'leads':
                if (count($parts) === 1) {
                    return $sections_dir . 'leads.php';
                } elseif (is_numeric($parts[1])) {
                    return $sections_dir . 'lead-detail.php';
                }
                return $sections_dir . 'leads.php';

            case 'marketing':
                return $sections_dir . 'marketing.php';
            case 'marketing-analytics':
                return $sections_dir . 'marketing-analytics.php';
            case 'reports':
                return $sections_dir . 'reports.php';
            case 'documents':
                return $sections_dir . 'documents.php';
            case 'notifications':
                return $sections_dir . 'notifications.php';
            case 'billing':
                return $sections_dir . 'billing.php';
            case 'automation-settings':
                return $sections_dir . 'automation-settings.php';
            case 'ai-tools':
                return $sections_dir . 'ai-tools.php';
            case 'messages':
                return $sections_dir . 'messages.php';
            case 'announcements':
                return $sections_dir . 'announcements.php';
            case 'settings':
                return $sections_dir . 'settings.php';
            case 'staff':
                if (count($parts) === 1) {
                    return $sections_dir . 'staff.php';
                } elseif ($parts[1] === 'add') {
                    return $sections_dir . 'staff-form.php';
                } elseif (is_numeric($parts[1])) {
                    $_GET['staff_id'] = intval($parts[1]);
                    if (count($parts) === 2) {
                        return $sections_dir . 'staff-detail.php';
                    } elseif (isset($parts[2]) && $parts[2] === 'edit') {
                        return $sections_dir . 'staff-form.php';
                    }
                }
                return $sections_dir . 'staff.php';

            default:
                return $sections_dir . 'overview.php';
        }
    }
}
