<?php
/**
 * Feature Gate - Plan Limits & Module Access Enforcement
 * 
 * Enforces subscription plan limits and module access across the application.
 * 
 * @package RentalGates
 * @since 2.10.2
 */

if (!defined('ABSPATH'))
    exit;

class Rental_Gates_Feature_Gate
{

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Cached plans
     */
    private $plans = null;

    /**
     * Cached user plan
     */
    private $user_plan_cache = array();

    /**
     * Module definitions
     */
    private $module_definitions = array(
        'tenant_portal' => array('label' => 'Tenant Portal', 'description' => 'Self-service portal for tenants'),
        'online_payments' => array('label' => 'Online Payments', 'description' => 'Accept rent via card/bank'),
        'maintenance' => array('label' => 'Maintenance Tracking', 'description' => 'Work order management'),
        'lease_management' => array('label' => 'Lease Management', 'description' => 'Create & track leases'),
        'ai_screening' => array('label' => 'AI Tenant Screening', 'description' => 'AI-powered analysis'),
        'marketing_qr' => array('label' => 'Marketing & QR Codes', 'description' => 'Flyers and lead capture'),
        'vendor_management' => array('label' => 'Vendor Management', 'description' => 'Contractor management'),
        'chat_messaging' => array('label' => 'Chat & Messaging', 'description' => 'In-app messaging'),
        'api_access' => array('label' => 'API Access', 'description' => 'REST API integration'),
        'advanced_reports' => array('label' => 'Advanced Reports', 'description' => 'Analytics & exports'),
        'bulk_operations' => array('label' => 'Bulk Operations', 'description' => 'Mass import/export'),
        'white_label' => array('label' => 'White Label', 'description' => 'Custom branding'),
    );

    /**
     * Limit definitions with database mapping
     */
    private $limit_definitions = array(
        'buildings' => array('table' => 'rg_buildings', 'label' => 'Buildings'),
        'units' => array('table' => 'rg_units', 'label' => 'Units'),
        'staff' => array('table' => 'rg_organization_members', 'label' => 'Staff Members', 'column' => 'organization_id', 'role_filter' => array('staff', 'property_manager')),
        'vendors' => array('table' => 'rg_vendors', 'label' => 'Vendors'),
        'tenants' => array('table' => 'rg_tenants', 'label' => 'Tenants', 'column' => 'organization_id'),
        'storage_gb' => array('table' => null, 'label' => 'Storage (GB)'), // Calculated separately
    );

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->load_plans();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // AJAX handlers for limit checks
        add_action('wp_ajax_rg_check_limit', array($this, 'ajax_check_limit'));
        add_action('wp_ajax_rg_get_usage', array($this, 'ajax_get_usage'));
        add_action('wp_ajax_rg_check_module', array($this, 'ajax_check_module'));
    }

    /**
     * Load plans from database
     */
    private function load_plans()
    {
        $default_plans = $this->get_default_plans();
        $this->plans = get_option('rental_gates_plans', $default_plans);

        if (!is_array($this->plans) || empty($this->plans)) {
            $this->plans = $default_plans;
        }

        // Fix stale Gold price (Migration)
        if (isset($this->plans['gold']) && $this->plans['gold']['price_monthly'] == 199) {
            $this->plans['gold']['price_monthly'] = 499;
            $this->plans['gold']['price_yearly'] = 4790;
            update_option('rental_gates_plans', $this->plans);
        }
    }

    /**
     * Get default plans
     */
    private function get_default_plans()
    {
        return array(
            'free' => array(
                'id' => 'free',
                'name' => 'Free',
                'description' => 'Get started with basic features',
                'is_free' => true,
                'is_active' => true,
                'is_hidden' => false,
                'price_monthly' => 0,
                'price_yearly' => 0,  // Total annual price
                'sort_order' => 0,
                'is_featured' => false,
                'limits' => array('buildings' => 1, 'units' => 3, 'staff' => 0, 'vendors' => 0, 'tenants' => 5, 'storage_gb' => 1),
                'modules' => array('tenant_portal' => true, 'maintenance' => true, 'lease_management' => true),
                'custom_features' => array('Community support'),
                'cta_text' => 'Get Started Free',
                'cta_style' => 'outline',
            ),
            'basic' => array(
                'id' => 'basic',
                'name' => 'Basic',
                'description' => 'Perfect for small landlords',
                'is_free' => false,
                'is_active' => true,
                'is_hidden' => false,
                'price_monthly' => 29,
                'price_yearly' => 278,  // Total annual (~$23/mo, ~20% off)
                'sort_order' => 1,
                'is_featured' => false,
                'limits' => array('buildings' => 5, 'units' => 15, 'staff' => 1, 'vendors' => 5, 'tenants' => 30, 'storage_gb' => 5),
                'modules' => array('tenant_portal' => true, 'online_payments' => true, 'maintenance' => true, 'lease_management' => true),
                'custom_features' => array('Email support'),
                'cta_text' => 'Start Free Trial',
                'cta_style' => 'outline',
            ),
            'silver' => array(
                'id' => 'silver',
                'name' => 'Silver',
                'description' => 'For growing property businesses',
                'is_free' => false,
                'is_active' => true,
                'is_hidden' => false,
                'price_monthly' => 79,
                'price_yearly' => 758,  // Total annual (~$63/mo, ~20% off)
                'sort_order' => 2,
                'is_featured' => true,
                'limits' => array('buildings' => 25, 'units' => 75, 'staff' => 5, 'vendors' => 25, 'tenants' => 150, 'storage_gb' => 25),
                'modules' => array('tenant_portal' => true, 'online_payments' => true, 'maintenance' => true, 'lease_management' => true, 'ai_screening' => true, 'marketing_qr' => true, 'vendor_management' => true, 'chat_messaging' => true, 'advanced_reports' => true, 'bulk_operations' => true),
                'custom_features' => array('Priority support', 'Phone support'),
                'cta_text' => 'Start Free Trial',
                'cta_style' => 'primary',
            ),
            'gold' => array(
                'id' => 'gold',
                'name' => 'Gold',
                'description' => 'Enterprise-grade for large portfolios',
                'is_free' => false,
                'is_active' => true,
                'is_hidden' => false,
                'price_monthly' => 499,
                'price_yearly' => 4790,  // Total annual (~$399/mo, ~20% off)
                'sort_order' => 3,
                'is_featured' => false,
                'limits' => array('buildings' => -1, 'units' => -1, 'staff' => -1, 'vendors' => -1, 'tenants' => -1, 'storage_gb' => 100),
                'modules' => array('tenant_portal' => true, 'online_payments' => true, 'maintenance' => true, 'lease_management' => true, 'ai_screening' => true, 'marketing_qr' => true, 'vendor_management' => true, 'chat_messaging' => true, 'api_access' => true, 'advanced_reports' => true, 'bulk_operations' => true, 'white_label' => true),
                'custom_features' => array('Dedicated account manager', 'Custom integrations', 'SLA guarantee', '24/7 support'),
                'cta_text' => 'Contact Sales',
                'cta_style' => 'outline',
            ),
        );
    }

    /**
     * Resolve database plan slugs to feature gate plan slugs.
     * The DB seeds use 'starter/professional/enterprise' but feature gate
     * defines 'basic/silver/gold'. This bridges the mismatch.
     *
     * @param string $slug Plan slug from database
     * @return string Resolved plan slug for feature gate lookup
     */
    private function resolve_plan_slug($slug)
    {
        $aliases = array(
            'starter'      => 'basic',
            'professional' => 'silver',
            'enterprise'   => 'gold',
        );
        return isset($aliases[$slug]) ? $aliases[$slug] : $slug;
    }

    /**
     * Get the plan config for a given plan slug (resolves aliases).
     *
     * @param string $slug Plan slug (accepts both DB and feature gate names)
     * @return array|null Plan config or null if not found
     */
    public function get_plan_config($slug)
    {
        $resolved = $this->resolve_plan_slug($slug);
        return isset($this->plans[$resolved]) ? $this->plans[$resolved] : null;
    }

    /**
     * Check if the current user has one of the specified roles.
     *
     * @param array $roles Allowed role slugs (e.g., ['owner', 'manager'])
     * @return bool
     */
    public function check_role($roles = array())
    {
        if (empty($roles)) {
            return true;
        }

        if (!class_exists('Rental_Gates_Roles')) {
            return false;
        }

        foreach ($roles as $role) {
            switch ($role) {
                case 'owner':
                case 'manager':
                    if (Rental_Gates_Roles::is_owner_or_manager()) {
                        return true;
                    }
                    break;
                case 'staff':
                    if (Rental_Gates_Roles::is_staff()) {
                        return true;
                    }
                    break;
                case 'admin':
                case 'site_admin':
                    if (Rental_Gates_Roles::is_site_admin()) {
                        return true;
                    }
                    break;
                case 'tenant':
                    if (Rental_Gates_Roles::is_tenant()) {
                        return true;
                    }
                    break;
                case 'vendor':
                    if (Rental_Gates_Roles::is_vendor()) {
                        return true;
                    }
                    break;
            }
        }

        return false;
    }

    /**
     * Get all available plans (public method)
     *
     * @return array All plans with fallback to defaults
     */
    public function get_all_plans()
    {
        return $this->plans;
    }

    /**
     * Get user's organization ID
     * 
     * Uses Rental_Gates_Roles for consistent organization lookup
     */
    public function get_user_org_id($user_id = null)
    {
        // Use the existing Roles class method for consistency
        if (class_exists('Rental_Gates_Roles')) {
            return Rental_Gates_Roles::get_organization_id($user_id);
        }

        // Fallback if Roles class not loaded
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return null;
        }

        // Check organization_members table
        $org_id = $wpdb->get_var($wpdb->prepare(
            "SELECT organization_id FROM {$wpdb->prefix}rg_organization_members 
             WHERE user_id = %d AND status = 'active' 
             LIMIT 1",
            $user_id
        ));

        if ($org_id) {
            return intval($org_id);
        }

        // Check if user owns an organization
        $org_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rg_organizations WHERE owner_id = %d LIMIT 1",
            $user_id
        ));

        return $org_id ? intval($org_id) : null;
    }

    /**
     * Get organization's current plan
     */
    public function get_org_plan($org_id = null)
    {
        global $wpdb;

        if (!$org_id) {
            $org_id = $this->get_user_org_id();
        }

        if (!$org_id) {
            return $this->plans['free'] ?? reset($this->plans);
        }

        // Check cache
        if (isset($this->user_plan_cache[$org_id])) {
            return $this->user_plan_cache[$org_id];
        }

        // Get plan from organization
        $plan_id = $wpdb->get_var($wpdb->prepare(
            "SELECT plan_id FROM {$wpdb->prefix}rg_organizations WHERE id = %d",
            $org_id
        ));

        // Resolve database plan names to feature gate plan names
        $plan_id = $this->resolve_plan_slug($plan_id);

        // Default to free plan if not set or invalid
        if (!$plan_id || !isset($this->plans[$plan_id])) {
            $plan_id = 'free';
        }

        $plan = $this->plans[$plan_id] ?? $this->plans['free'] ?? reset($this->plans);

        // Cache result
        $this->user_plan_cache[$org_id] = $plan;

        return $plan;
    }

    /**
     * Get current usage for a resource type
     */
    public function get_usage($resource, $org_id = null)
    {
        global $wpdb;

        if (!$org_id) {
            $org_id = $this->get_user_org_id();
        }

        if (!$org_id || !isset($this->limit_definitions[$resource])) {
            return 0;
        }

        $def = $this->limit_definitions[$resource];

        // Special handling for storage
        if ($resource === 'storage_gb') {
            return $this->calculate_storage_usage($org_id);
        }

        $table = $wpdb->prefix . $def['table'];

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }

        // Build query based on resource type
        if (isset($def['role_filter'])) {
            // For staff/tenants, filter by role
            $roles = "'" . implode("','", $def['role_filter']) . "'";
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE organization_id = %d AND role IN ($roles)",
                $org_id
            ));
        } else {
            // Standard count
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE organization_id = %d",
                $org_id
            ));
        }

        return intval($count);
    }

    /**
     * Calculate storage usage in GB
     */
    private function calculate_storage_usage($org_id)
    {
        global $wpdb;

        // Get all attachment IDs associated with this organization
        // This is a simplified calculation - in production, track actual file sizes
        $upload_dir = wp_upload_dir();
        $org_path = $upload_dir['basedir'] . '/rental-gates/' . $org_id;

        if (!is_dir($org_path)) {
            return 0;
        }

        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($org_path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $size += $file->getSize();
        }

        // Convert to GB
        return round($size / (1024 * 1024 * 1024), 2);
    }

    /**
     * Get limit for a resource type
     */
    public function get_limit($resource, $org_id = null)
    {
        $plan = $this->get_org_plan($org_id);
        return $plan['limits'][$resource] ?? 0;
    }

    /**
     * Check if user can create more of a resource
     * 
     * @param string $resource Resource type (buildings, units, staff, vendors, tenants)
     * @param int $count Number to create (default 1)
     * @param int|null $org_id Organization ID (default current user's org)
     * @return array ['allowed' => bool, 'current' => int, 'limit' => int, 'message' => string]
     */
    public function can_create($resource, $count = 1, $org_id = null)
    {
        if (!$org_id) {
            $org_id = $this->get_user_org_id();
        }

        $plan = $this->get_org_plan($org_id);
        $limit = $plan['limits'][$resource] ?? 0;
        $current = $this->get_usage($resource, $org_id);
        $label = $this->limit_definitions[$resource]['label'] ?? $resource;

        // -1 means unlimited
        if ($limit === -1) {
            return array(
                'allowed' => true,
                'current' => $current,
                'limit' => -1,
                'remaining' => -1,
                'message' => '',
            );
        }

        // 0 means disabled
        if ($limit === 0) {
            return array(
                'allowed' => false,
                'current' => $current,
                'limit' => 0,
                'remaining' => 0,
                'message' => sprintf(
                    __('%s are not available on your current plan. Please upgrade to access this feature.', 'rental-gates'),
                    $label
                ),
            );
        }

        $remaining = $limit - $current;
        $allowed = ($current + $count) <= $limit;

        $result = array(
            'allowed' => $allowed,
            'current' => $current,
            'limit' => $limit,
            'remaining' => max(0, $remaining),
            'message' => '',
        );

        if (!$allowed) {
            if ($count === 1) {
                $result['message'] = sprintf(
                    __('You\'ve reached your limit of %d %s. Please upgrade your plan to add more.', 'rental-gates'),
                    $limit,
                    strtolower($label)
                );
            } else {
                $result['message'] = sprintf(
                    __('You can only add %d more %s. Please upgrade your plan for higher limits.', 'rental-gates'),
                    $remaining,
                    strtolower($label)
                );
            }
        }

        return $result;
    }

    /**
     * Check if a module is enabled for the user's plan
     * 
     * @param string $module Module key
     * @param int|null $org_id Organization ID
     * @return array ['enabled' => bool, 'message' => string]
     */
    public function can_access_module($module, $org_id = null)
    {
        $plan = $this->get_org_plan($org_id);
        $enabled = !empty($plan['modules'][$module]);
        $label = $this->module_definitions[$module]['label'] ?? $module;

        return array(
            'enabled' => $enabled,
            'module' => $module,
            'label' => $label,
            'message' => $enabled ? '' : sprintf(
                __('%s is not available on your current plan. Please upgrade to access this feature.', 'rental-gates'),
                $label
            ),
        );
    }

    /**
     * Get all usage stats for an organization
     */
    public function get_all_usage($org_id = null)
    {
        if (!$org_id) {
            $org_id = $this->get_user_org_id();
        }

        $plan = $this->get_org_plan($org_id);
        $usage = array();

        foreach ($this->limit_definitions as $resource => $def) {
            $current = $this->get_usage($resource, $org_id);
            $limit = $plan['limits'][$resource] ?? 0;

            $usage[$resource] = array(
                'label' => $def['label'],
                'current' => $current,
                'limit' => $limit,
                'remaining' => $limit === -1 ? -1 : max(0, $limit - $current),
                'percentage' => $limit > 0 ? min(100, round(($current / $limit) * 100)) : ($limit === -1 ? 0 : 100),
                'unlimited' => $limit === -1,
                'disabled' => $limit === 0,
            );
        }

        return $usage;
    }

    /**
     * Get all module access for an organization
     */
    public function get_all_modules($org_id = null)
    {
        $plan = $this->get_org_plan($org_id);
        $modules = array();

        foreach ($this->module_definitions as $key => $def) {
            $modules[$key] = array(
                'key' => $key,
                'label' => $def['label'],
                'description' => $def['description'],
                'enabled' => !empty($plan['modules'][$key]),
            );
        }

        return $modules;
    }

    /**
     * Get plan comparison data
     */
    public function get_plan_comparison()
    {
        $comparison = array();

        foreach ($this->plans as $plan_id => $plan) {
            $comparison[$plan_id] = array(
                'id' => $plan_id,
                'name' => $plan['name'],
                'price_monthly' => $plan['price_monthly'] ?? 0,
                'price_yearly' => $plan['price_yearly'] ?? $plan['price_monthly'] ?? 0,
                'is_free' => !empty($plan['is_free']),
                'limits' => $plan['limits'] ?? array(),
                'modules' => $plan['modules'] ?? array(),
            );
        }

        return $comparison;
    }

    /**
     * AJAX: Check if user can create a resource
     */
    public function ajax_check_limit()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        $resource = sanitize_key($_POST['resource'] ?? '');
        $count = intval($_POST['count'] ?? 1);

        if (!$resource) {
            wp_send_json_error(array('message' => __('Invalid resource type', 'rental-gates')));
        }

        $result = $this->can_create($resource, $count);
        wp_send_json_success($result);
    }

    /**
     * AJAX: Get current usage
     */
    public function ajax_get_usage()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        $usage = $this->get_all_usage();
        $modules = $this->get_all_modules();
        $plan = $this->get_org_plan();

        wp_send_json_success(array(
            'plan' => array(
                'id' => $plan['id'] ?? 'free',
                'name' => $plan['name'] ?? 'Free',
            ),
            'usage' => $usage,
            'modules' => $modules,
        ));
    }

    /**
     * AJAX: Check module access
     */
    public function ajax_check_module()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        $module = sanitize_key($_POST['module'] ?? '');

        if (!$module) {
            wp_send_json_error(array('message' => __('Invalid module', 'rental-gates')));
        }

        $result = $this->can_access_module($module);
        wp_send_json_success($result);
    }

    /**
     * Render upgrade prompt
     */
    public function render_upgrade_prompt($message = '', $show_plans = false)
    {
        $plan = $this->get_org_plan();
        $upgrade_url = home_url('/rental-gates/dashboard/billing');

        ob_start();
        ?>
        <div class="rg-upgrade-prompt">
            <div class="rg-upgrade-icon">
                <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <h3 class="rg-upgrade-title"><?php _e('Upgrade Required', 'rental-gates'); ?></h3>
            <?php if ($message): ?>
                <p class="rg-upgrade-message"><?php echo esc_html($message); ?></p>
            <?php endif; ?>
            <p class="rg-upgrade-plan">
                <?php printf(__('Current plan: <strong>%s</strong>', 'rental-gates'), esc_html($plan['name'])); ?>
            </p>
            <a href="<?php echo esc_url($upgrade_url); ?>" class="btn btn-primary">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
                <?php _e('Upgrade Plan', 'rental-gates'); ?>
            </a>
        </div>
        <style>
            .rg-upgrade-prompt {
                text-align: center;
                padding: 48px 24px;
                background: linear-gradient(135deg, #fef3c7, #fef9c3);
                border: 2px dashed #f59e0b;
                border-radius: 16px;
                margin: 24px 0;
            }

            .rg-upgrade-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 80px;
                height: 80px;
                background: #fff;
                border-radius: 50%;
                margin-bottom: 16px;
                color: #f59e0b;
                box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
            }

            .rg-upgrade-title {
                font-size: 20px;
                font-weight: 600;
                color: #92400e;
                margin: 0 0 8px;
            }

            .rg-upgrade-message {
                font-size: 15px;
                color: #a16207;
                margin: 0 0 8px;
                max-width: 400px;
                margin-left: auto;
                margin-right: auto;
            }

            .rg-upgrade-plan {
                font-size: 14px;
                color: #a16207;
                margin: 0 0 20px;
            }

            .rg-upgrade-prompt .btn-primary {
                background: #f59e0b;
                border-color: #f59e0b;
            }

            .rg-upgrade-prompt .btn-primary:hover {
                background: #d97706;
                border-color: #d97706;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Render usage bar
     */
    public function render_usage_bar($resource, $compact = false)
    {
        $org_id = $this->get_user_org_id();
        $plan = $this->get_org_plan($org_id);
        $current = $this->get_usage($resource, $org_id);
        $limit = $plan['limits'][$resource] ?? 0;
        $label = $this->limit_definitions[$resource]['label'] ?? $resource;

        if ($limit === -1) {
            $percentage = 0;
            $status = 'unlimited';
            $text = __('Unlimited', 'rental-gates');
        } elseif ($limit === 0) {
            $percentage = 100;
            $status = 'disabled';
            $text = __('Not available', 'rental-gates');
        } else {
            $percentage = min(100, round(($current / $limit) * 100));
            $status = $percentage >= 90 ? 'critical' : ($percentage >= 70 ? 'warning' : 'good');
            $text = sprintf('%d / %d', $current, $limit);
        }

        ob_start();
        ?>
        <div class="rg-usage-bar <?php echo $compact ? 'compact' : ''; ?>" data-resource="<?php echo esc_attr($resource); ?>">
            <?php if (!$compact): ?>
                <div class="rg-usage-header">
                    <span class="rg-usage-label"><?php echo esc_html($label); ?></span>
                    <span class="rg-usage-text"><?php echo esc_html($text); ?></span>
                </div>
            <?php endif; ?>
            <div class="rg-usage-track">
                <div class="rg-usage-fill <?php echo esc_attr($status); ?>" style="width: <?php echo $percentage; ?>%"></div>
            </div>
            <?php if ($compact): ?>
                <span class="rg-usage-text-compact"><?php echo esc_html($text); ?></span>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get upgrade suggestions based on current usage
     */
    public function get_upgrade_suggestions($org_id = null)
    {
        $plan = $this->get_org_plan($org_id);
        $usage = $this->get_all_usage($org_id);
        $suggestions = array();

        // Find resources near limit
        foreach ($usage as $resource => $data) {
            if ($data['percentage'] >= 80 && !$data['unlimited']) {
                $suggestions[] = array(
                    'type' => 'limit',
                    'resource' => $resource,
                    'label' => $data['label'],
                    'current' => $data['current'],
                    'limit' => $data['limit'],
                    'percentage' => $data['percentage'],
                );
            }
        }

        return $suggestions;
    }
}

/**
 * Helper function to get Feature Gate instance
 */
function rg_feature_gate()
{
    return Rental_Gates_Feature_Gate::get_instance();
}

/**
 * Helper function to check if user can create a resource
 */
function rg_can_create($resource, $count = 1, $org_id = null)
{
    return rg_feature_gate()->can_create($resource, $count, $org_id);
}

/**
 * Helper function to check module access
 */
function rg_can_access_module($module, $org_id = null)
{
    return rg_feature_gate()->can_access_module($module, $org_id);
}

/**
 * Helper function to check if module is enabled (simple boolean)
 */
function rg_module_enabled($module, $org_id = null)
{
    $result = rg_feature_gate()->can_access_module($module, $org_id);
    return $result['enabled'];
}

/**
 * Helper function to render upgrade prompt
 */
function rg_upgrade_prompt($message = '')
{
    return rg_feature_gate()->render_upgrade_prompt($message);
}

/**
 * Helper function to render usage bar
 */
function rg_usage_bar($resource, $compact = false)
{
    return rg_feature_gate()->render_usage_bar($resource, $compact);
}

/**
 * Helper function to get all available plans
 */
function rg_get_all_plans()
{
    return rg_feature_gate()->get_all_plans();
}
