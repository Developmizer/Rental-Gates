<?php
/**
 * Plugin Name: Rental Gates
 * Plugin URI: https://developmizer.com/rental-gates
 * Description: All-in-one rental discovery and property management platform. Multi-tenant SaaS with organization-based dashboards, AI screening, and dynamic availability.
 * Version: 2.40.0
 * Author: Developmizer
 * Author URI: https://developmizer.com
 * Text Domain: rental-gates
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('RENTAL_GATES_VERSION', '2.41.0');
define('RENTAL_GATES_DB_VERSION', '2.15.2');
define('RENTAL_GATES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RENTAL_GATES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RENTAL_GATES_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Coming Soon window (days)
define('RENTAL_GATES_COMING_SOON_WINDOW', 30);

// AI Credit costs
define('RENTAL_GATES_AI_CREDIT_DESCRIPTION', 2);
define('RENTAL_GATES_AI_CREDIT_MAINTENANCE', 1);
define('RENTAL_GATES_AI_CREDIT_SCREENING', 10);
define('RENTAL_GATES_AI_CREDIT_MARKETING', 2);
define('RENTAL_GATES_AI_CREDIT_MESSAGE', 1);
define('RENTAL_GATES_AI_CREDIT_INSIGHTS', 5);

/**
 * Main Rental Gates Plugin Class
 */
final class Rental_Gates
{

    /**
     * Single instance of the plugin
     */
    private static $instance = null;

    /**
     * Plugin components
     */
    public $loader;
    public $roles;
    public $database;
    public $api;
    public $automation;
    public $notifications;
    public $admin;
    public $user_restrictions;
    public $auth;
    public $map_service;
    public $public;

    /**
     * Get single instance
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserializing
     */
    public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton');
    }

    /**
     * Load required files
     */
    private function load_dependencies()
    {
        // Core
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-loader.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-database.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-roles.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-activator.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-deactivator.php';

        // Security & Performance
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-security.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-cache.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-rate-limit.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-feature-gate.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-pricing.php';

        // Map Services
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/maps/class-rental-gates-map-service.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/maps/class-rental-gates-google-maps.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/maps/class-rental-gates-openstreetmap.php';

        // Models
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-organization.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-building.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-unit.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-tenant.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-lease.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-application.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-lead.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-lead-scoring.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-marketing-conversion.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-campaign.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-maintenance.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-vendor.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-payment.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-invoice.php';

        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-plan.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-subscription.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-document.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-notification.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-announcement.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-message.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-ai-usage.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/models/class-rental-gates-flyer.php';

        // Email System
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-email.php';

        // Automation
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/automation/class-rental-gates-automation.php';

        // Image Optimizer
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-image-optimizer.php';

        // PDF Generator
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-pdf.php';

        // Stripe Integration
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-stripe.php';

        // AI Integration
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-ai.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-ai-credits.php';

        // API
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/api/class-rental-gates-rest-api.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/api/class-rental-gates-form-helper.php';

        // Automation
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/automation/class-rental-gates-automation.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/automation/class-rental-gates-availability-engine.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/automation/class-rental-gates-marketing-automation.php';
        
        // Integrations
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/integrations/class-rental-gates-email-marketing.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/integrations/class-rental-gates-social-media.php';

        // Public
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/public/class-rental-gates-public.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/public/class-rental-gates-qr.php';

        // Shortcodes
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-shortcodes.php';

        // Dashboards
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/dashboard/class-rental-gates-dashboard.php';

        // Subscription
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/subscription/class-rental-gates-plans.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/subscription/class-rental-gates-billing.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/subscription/class-rental-gates-webhook-handler.php';
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/subscription/class-rental-gates-subscription-invoice.php';


        // Admin
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/admin/class-rental-gates-admin.php';

        // User Restrictions (toolbar, redirects)
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-user-restrictions.php';

        // Auth Handler (registration, login)
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-auth.php';

        // Validation utilities
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-validation.php';

        // PWA (Progressive Web App) support
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-pwa.php';

        // Analytics helper
        require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-analytics.php';

        // Testing utilities (admin only)
        if (is_admin()) {
            require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-tests.php';
        }

        // Initialize core components
        $this->loader = new Rental_Gates_Loader();
        $this->database = new Rental_Gates_Database();
        $this->roles = new Rental_Gates_Roles();

        // Initialize PWA
        rental_gates_pwa();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Activation/Deactivation
        register_activation_hook(__FILE__, array('Rental_Gates_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('Rental_Gates_Deactivator', 'deactivate'));

        // Database upgrade check
        add_action('admin_init', array($this, 'check_db_upgrade'));

        // Initialize admin menu
        if (is_admin()) {
            $this->admin = new Rental_Gates_Admin();
        }

        // Initialize user restrictions
        $this->user_restrictions = new Rental_Gates_User_Restrictions();

        // Initialize auth handler
        $this->auth = new Rental_Gates_Auth();

        // Initialize shortcodes
        Rental_Gates_Shortcodes::init();

        // Initialize Public
        $this->public = new Rental_Gates_Public();
        add_action('init', array($this->public, 'add_rewrite_rules'));
        add_filter('query_vars', array($this->public, 'add_query_vars'));
        add_action('template_redirect', array($this->public, 'template_redirect'));

        // Init
        add_action('init', array($this, 'init'), 0);
        add_action('rest_api_init', array($this, 'init_api'));

        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Output data early in wp_head for dashboard templates
        add_action('wp_head', array($this, 'output_rental_gates_data_early'), 1);
        add_action('wp_head', array($this, 'output_preconnect_hints'), 0);

        // Rewrite rules
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_custom_routes'), 1);

        // Prevent WordPress canonical redirects for our URLs
        add_filter('redirect_canonical', array($this, 'prevent_canonical_redirect'), 10, 2);

        // Customize password reset URL in emails
        add_filter('retrieve_password_message', array($this, 'customize_password_reset_email'), 10, 4);

        // Cron for automation
        add_action('rental_gates_availability_cron', array($this, 'run_availability_automation'));
        add_action('rental_gates_ai_credits_reset', array($this, 'reset_ai_credits'));
        add_action('rental_gates_subscription_expiration', array($this, 'check_subscription_expirations'));

        // AJAX handlers
        add_action('wp_ajax_rental_gates_upload_image', array($this, 'handle_image_upload'));
        add_action('wp_ajax_rental_gates_generate_qr', array($this, 'handle_qr_generation'));
        add_action('wp_ajax_rental_gates_get_qr', array($this, 'handle_get_qr'));
        add_action('wp_ajax_rental_gates_bulk_generate_qr', array($this, 'handle_bulk_generate_qr'));
        add_action('wp_ajax_rental_gates_qr_analytics', array($this, 'handle_qr_analytics'));
        add_action('wp_ajax_rental_gates_delete_qr', array($this, 'handle_delete_qr'));
        add_action('wp_ajax_rental_gates_create_flyer', array($this, 'handle_create_flyer'));
        add_action('wp_ajax_rental_gates_regenerate_flyer', array($this, 'handle_regenerate_flyer'));
        add_action('wp_ajax_rental_gates_flyer_preview', array($this, 'handle_flyer_preview'));
        add_action('wp_ajax_rental_gates_calculate_lead_score', array($this, 'handle_calculate_lead_score'));
        add_action('wp_ajax_rental_gates_get_marketing_analytics', array($this, 'handle_get_marketing_analytics'));
        add_action('wp_ajax_rental_gates_geocode', array($this, 'handle_geocode_request'));
        add_action('wp_ajax_rental_gates_save_building', array($this, 'handle_save_building'));
        add_action('wp_ajax_rental_gates_save_unit', array($this, 'handle_save_unit'));
        add_action('wp_ajax_rental_gates_delete_building', array($this, 'handle_delete_building'));
        add_action('wp_ajax_rental_gates_delete_unit', array($this, 'handle_delete_unit'));
        add_action('wp_ajax_rental_gates_bulk_add_units', array($this, 'handle_bulk_add_units'));
        add_action('wp_ajax_rental_gates_save_settings', array($this, 'handle_save_settings'));

        // Tenant AJAX handlers
        add_action('wp_ajax_rental_gates_create_tenant', array($this, 'handle_create_tenant'));
        add_action('wp_ajax_rental_gates_update_tenant', array($this, 'handle_update_tenant'));
        add_action('wp_ajax_rental_gates_delete_tenant', array($this, 'handle_delete_tenant'));
        add_action('wp_ajax_rental_gates_invite_tenant', array($this, 'handle_invite_tenant'));

        // Lease AJAX handlers
        add_action('wp_ajax_rental_gates_create_lease', array($this, 'handle_create_lease'));
        add_action('wp_ajax_rental_gates_update_lease', array($this, 'handle_update_lease'));
        add_action('wp_ajax_rental_gates_delete_lease', array($this, 'handle_delete_lease'));
        add_action('wp_ajax_rental_gates_activate_lease', array($this, 'handle_activate_lease'));
        add_action('wp_ajax_rental_gates_end_lease', array($this, 'handle_end_lease'));
        add_action('wp_ajax_rental_gates_terminate_lease', array($this, 'handle_terminate_lease'));
        add_action('wp_ajax_rental_gates_renew_lease', array($this, 'handle_renew_lease'));
        add_action('wp_ajax_rental_gates_add_lease_tenant', array($this, 'handle_add_lease_tenant'));
        add_action('wp_ajax_rental_gates_remove_lease_tenant', array($this, 'handle_remove_lease_tenant'));

        // Application AJAX handlers
        add_action('wp_ajax_rental_gates_approve_application', array($this, 'handle_approve_application'));
        add_action('wp_ajax_rental_gates_decline_application', array($this, 'handle_decline_application'));
        add_action('wp_ajax_rental_gates_screen_application', array($this, 'handle_screen_application'));
        add_action('wp_ajax_rental_gates_delete_application', array($this, 'handle_delete_application'));

        // Payment AJAX handlers
        add_action('wp_ajax_rental_gates_create_payment', array($this, 'handle_create_payment'));
        add_action('wp_ajax_rental_gates_update_payment', array($this, 'handle_update_payment'));
        add_action('wp_ajax_rental_gates_delete_payment', array($this, 'handle_delete_payment'));
        add_action('wp_ajax_rental_gates_mark_payment_paid', array($this, 'handle_mark_payment_paid'));
        add_action('wp_ajax_rental_gates_cancel_payment', array($this, 'handle_cancel_payment'));

        // Maintenance AJAX handlers
        add_action('wp_ajax_rental_gates_create_maintenance', array($this, 'handle_create_maintenance'));
        add_action('wp_ajax_rental_gates_update_maintenance', array($this, 'handle_update_maintenance'));
        add_action('wp_ajax_rental_gates_delete_maintenance', array($this, 'handle_delete_maintenance'));
        add_action('wp_ajax_rental_gates_update_maintenance_status', array($this, 'handle_update_maintenance_status'));
        add_action('wp_ajax_rental_gates_complete_maintenance', array($this, 'handle_complete_maintenance'));
        add_action('wp_ajax_rental_gates_add_maintenance_note', array($this, 'handle_add_maintenance_note'));

        // Vendor AJAX handlers
        add_action('wp_ajax_rental_gates_create_vendor', array($this, 'handle_create_vendor'));
        add_action('wp_ajax_rental_gates_update_vendor', array($this, 'handle_update_vendor'));
        add_action('wp_ajax_rental_gates_delete_vendor', array($this, 'handle_delete_vendor'));
        add_action('wp_ajax_rental_gates_assign_vendor', array($this, 'handle_assign_vendor'));
        add_action('wp_ajax_rental_gates_remove_vendor_assignment', array($this, 'handle_remove_vendor_assignment'));
        add_action('wp_ajax_rental_gates_get_vendors_for_category', array($this, 'handle_get_vendors_for_category'));

        // Tenant Portal AJAX handlers
        add_action('wp_ajax_rental_gates_tenant_create_maintenance', array($this, 'handle_tenant_create_maintenance'));
        add_action('wp_ajax_rental_gates_tenant_delete_maintenance', array($this, 'handle_tenant_delete_maintenance'));
        add_action('wp_ajax_rental_gates_tenant_maintenance_feedback', array($this, 'handle_tenant_maintenance_feedback'));
        add_action('wp_ajax_rental_gates_tenant_add_note', array($this, 'handle_tenant_add_note'));
        add_action('wp_ajax_rental_gates_tenant_update_profile', array($this, 'handle_tenant_update_profile'));

        // Vendor Portal AJAX handlers
        add_action('wp_ajax_rental_gates_vendor_update_assignment', array($this, 'handle_vendor_update_assignment'));
        add_action('wp_ajax_rental_gates_vendor_add_note', array($this, 'handle_vendor_add_note'));

        // Note: Subscription AJAX handlers are registered below (lines ~420) to use class methods
        // Do not duplicate registration here to avoid conflicts
        add_action('wp_ajax_rental_gates_invite_vendor', array($this, 'handle_invite_vendor'));

        // Document AJAX handlers
        add_action('wp_ajax_rental_gates_upload_document', array($this, 'handle_upload_document'));
        add_action('wp_ajax_rental_gates_delete_document', array($this, 'handle_delete_document'));

        // Staff AJAX handlers
        add_action('wp_ajax_rental_gates_save_staff', array($this, 'handle_save_staff'));
        add_action('wp_ajax_rental_gates_remove_staff', array($this, 'handle_remove_staff'));

        // Message AJAX handlers
        add_action('wp_ajax_rental_gates_send_message', array($this, 'handle_send_message'));
        add_action('wp_ajax_rental_gates_start_conversation', array($this, 'handle_start_conversation'));
        add_action('wp_ajax_rental_gates_start_thread', array($this, 'handle_start_thread'));
        add_action('wp_ajax_rental_gates_get_new_messages', array($this, 'handle_get_new_messages'));

        // Lead/CRM AJAX handlers
        add_action('wp_ajax_rental_gates_create_lead', array($this, 'handle_create_lead'));
        add_action('wp_ajax_rental_gates_get_lead', array($this, 'handle_get_lead'));
        add_action('wp_ajax_rental_gates_update_lead', array($this, 'handle_update_lead'));
        add_action('wp_ajax_rental_gates_delete_lead', array($this, 'handle_delete_lead'));
        add_action('wp_ajax_rental_gates_add_lead_note', array($this, 'handle_add_lead_note'));
        add_action('wp_ajax_rental_gates_add_lead_interest', array($this, 'handle_add_lead_interest'));

        // Announcement AJAX handlers
        add_action('wp_ajax_rental_gates_create_announcement', array($this, 'handle_create_announcement'));
        add_action('wp_ajax_rental_gates_send_announcement', array($this, 'handle_send_announcement'));

        // Payment management
        add_action('wp_ajax_rental_gates_record_manual_payment', array($this, 'handle_record_manual_payment'));
        add_action('wp_ajax_rental_gates_sync_payment', array($this, 'handle_sync_payment'));
        add_action('wp_ajax_rental_gates_generate_rent_payments', array($this, 'handle_generate_rent_payments'));
        add_action('wp_ajax_rental_gates_create_pending_payment', array($this, 'handle_create_pending_payment'));

        // Stripe AJAX handlers
        add_action('wp_ajax_rental_gates_stripe_setup_intent', array($this, 'handle_stripe_setup_intent'));
        add_action('wp_ajax_rental_gates_stripe_save_payment_method', array($this, 'handle_stripe_save_payment_method'));
        add_action('wp_ajax_rental_gates_stripe_delete_payment_method', array($this, 'handle_stripe_delete_payment_method'));
        add_action('wp_ajax_rental_gates_stripe_set_default_method', array($this, 'handle_stripe_set_default_method'));
        add_action('wp_ajax_rental_gates_update_subscription_payment_method', array($this, 'handle_update_subscription_payment_method'));
        add_action('wp_ajax_rental_gates_stripe_create_payment_intent', array($this, 'handle_stripe_create_payment_intent'));
        add_action('wp_ajax_rental_gates_stripe_session_status', array($this, 'handle_stripe_session_status'));
        add_action('wp_ajax_nopriv_rental_gates_stripe_session_status', array($this, 'handle_stripe_session_status'));
        add_action('wp_ajax_rental_gates_stripe_connect', array($this, 'handle_stripe_connect'));
        add_action('wp_ajax_nopriv_rental_gates_stripe_webhook', array($this, 'handle_stripe_webhook'));
        add_action('wp_ajax_rental_gates_stripe_webhook', array($this, 'handle_stripe_webhook'));

        // Public inquiry handler (for lead capture)
        add_action('wp_ajax_rental_gates_public_inquiry', array($this, 'handle_public_inquiry'));
        add_action('wp_ajax_nopriv_rental_gates_public_inquiry', array($this, 'handle_public_inquiry'));

        // Forgot password handler (public)
        add_action('wp_ajax_nopriv_rental_gates_forgot_password', array($this, 'handle_forgot_password'));
        add_action('wp_ajax_rental_gates_forgot_password', array($this, 'handle_forgot_password'));

        // Invoice handlers
        add_action('wp_ajax_rental_gates_download_invoice', array($this, 'handle_download_invoice'));
        add_action('wp_ajax_rental_gates_get_invoice', array($this, 'handle_get_invoice'));
        add_action('wp_ajax_rental_gates_generate_invoice', array($this, 'handle_generate_invoice'));
        add_action('wp_ajax_rental_gates_download_subscription_invoice', array($this, 'handle_download_subscription_invoice'));
        add_action('wp_ajax_rental_gates_view_subscription_invoice', array($this, 'handle_view_subscription_invoice'));
        add_action('wp_ajax_nopriv_rental_gates_view_subscription_invoice', array($this, 'handle_view_subscription_invoice'));

        // Subscription handlers
        add_action('wp_ajax_rental_gates_subscribe', array($this, 'handle_subscription_create'));
        add_action('wp_ajax_rental_gates_cancel_subscription', array($this, 'handle_subscription_cancel'));
        add_action('wp_ajax_rental_gates_resume_subscription', array($this, 'handle_subscription_resume'));
        add_action('wp_ajax_rental_gates_cancel_subscription_immediately', array($this, 'handle_subscription_cancel_immediately'));
        add_action('wp_ajax_rental_gates_change_plan', array($this, 'handle_plan_change'));
        add_action('wp_ajax_rental_gates_get_billing_usage', array($this, 'handle_get_billing_usage'));

        // Subscription checkout handlers (for registration flow)
        add_action('wp_ajax_rental_gates_create_subscription_intent', array($this, 'handle_create_subscription_intent'));
        add_action('wp_ajax_rental_gates_activate_subscription', array($this, 'handle_activate_subscription_payment'));

        // Contact form handler (public)
        add_action('wp_ajax_rental_gates_contact_form', array($this, 'handle_contact_form'));
        add_action('wp_ajax_nopriv_rental_gates_contact_form', array($this, 'handle_contact_form'));

        // AI Credit purchase handlers
        add_action('wp_ajax_rg_purchase_ai_credits', array($this, 'handle_ai_credit_purchase'));
        add_action('wp_ajax_rg_admin_adjust_credits', array($this, 'handle_admin_credit_adjustment'));
        add_action('wp_ajax_rg_ai_generate', array($this, 'handle_ai_generate'));

        // Report Export handler (init hook for early processing)
        add_action('init', array($this, 'handle_report_export'));
    }

    /**
     * Check and perform database upgrades
     */
    public function check_db_upgrade()
    {
        // Only run once per request
        static $ran = false;
        if ($ran)
            return;
        $ran = true;

        $current_version = get_option('rental_gates_db_version', '0');

        // Already at current version - skip
        if (version_compare($current_version, RENTAL_GATES_DB_VERSION, '>=')) {
            return;
        }

        // v2.7.0 - Add marketing columns
        if (version_compare($current_version, '2.7.0', '<')) {
            $this->upgrade_to_270();
        }

        // v2.7.3 - Fix QR duplicates and scan_count
        if (version_compare($current_version, '2.7.3', '<')) {
            $this->upgrade_to_273();
        }

        // v2.11.5 - Add downgrade_to_plan column for subscription scheduling
        if (version_compare($current_version, '2.11.5', '<')) {
            $this->upgrade_to_2115();
        }

        // Update to current version so this doesn't run again
        update_option('rental_gates_db_version', RENTAL_GATES_DB_VERSION);
    }

    /**
     * Upgrade database to v2.7.3 - Fix QR duplicates
     */
    private function upgrade_to_273()
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        // Clean up duplicate QR codes - keep only the oldest one per type+entity_id
        $wpdb->query("
            DELETE q1 FROM {$tables['qr_codes']} q1
            INNER JOIN {$tables['qr_codes']} q2
            WHERE q1.type = q2.type 
            AND q1.entity_id = q2.entity_id 
            AND q1.id > q2.id
        ");

        // Recalculate scan_count from qr_scans table
        $wpdb->query("
            UPDATE {$tables['qr_codes']} qc
            SET scan_count = (
                SELECT COUNT(*) FROM {$tables['qr_scans']} qs WHERE qs.qr_code_id = qc.id
            )
        ");

        // Update last_scanned_at from qr_scans
        $wpdb->query("
            UPDATE {$tables['qr_codes']} qc
            SET last_scanned_at = (
                SELECT MAX(scanned_at) FROM {$tables['qr_scans']} qs WHERE qs.qr_code_id = qc.id
            )
            WHERE EXISTS (SELECT 1 FROM {$tables['qr_scans']} qs WHERE qs.qr_code_id = qc.id)
        ");

        error_log('Rental Gates: Database upgraded to v2.7.3 - QR duplicates cleaned');
    }

    /**
     * Upgrade database to v2.11.5 schema - Add downgrade_to_plan column
     */
    private function upgrade_to_2115()
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        // Add downgrade_to_plan column to subscriptions table if it doesn't exist
        $sub_columns = $wpdb->get_col("DESCRIBE {$tables['subscriptions']}", 0);

        if (!in_array('downgrade_to_plan', $sub_columns)) {
            $wpdb->query("ALTER TABLE {$tables['subscriptions']} ADD COLUMN downgrade_to_plan varchar(50) DEFAULT NULL AFTER cancel_at_period_end");
        }

        error_log('Rental Gates: Database upgraded to v2.11.5 - Added downgrade_to_plan column');
    }

    /**
     * Upgrade database to v2.7.0 schema
     */
    private function upgrade_to_270()
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        // Add columns to qr_codes table if they don't exist
        $qr_columns = $wpdb->get_col("DESCRIBE {$tables['qr_codes']}", 0);

        if (!in_array('destination_url', $qr_columns)) {
            $wpdb->query("ALTER TABLE {$tables['qr_codes']} ADD COLUMN destination_url varchar(500) DEFAULT NULL AFTER code");
        }
        if (!in_array('label', $qr_columns)) {
            $wpdb->query("ALTER TABLE {$tables['qr_codes']} ADD COLUMN label varchar(255) DEFAULT NULL AFTER destination_url");
        }
        if (!in_array('scan_count', $qr_columns)) {
            $wpdb->query("ALTER TABLE {$tables['qr_codes']} ADD COLUMN scan_count int(11) UNSIGNED NOT NULL DEFAULT 0 AFTER file_url");
        }
        if (!in_array('last_scanned_at', $qr_columns)) {
            $wpdb->query("ALTER TABLE {$tables['qr_codes']} ADD COLUMN last_scanned_at datetime DEFAULT NULL AFTER scan_count");
        }

        // Convert type column from ENUM to VARCHAR if needed
        $type_column_info = $wpdb->get_row("SHOW COLUMNS FROM {$tables['qr_codes']} LIKE 'type'", ARRAY_A);
        if ($type_column_info && strpos($type_column_info['Type'], 'enum') !== false) {
            $wpdb->query("ALTER TABLE {$tables['qr_codes']} MODIFY COLUMN type varchar(50) NOT NULL DEFAULT 'building'");
        }

        // Clean up duplicate QR codes - keep only the oldest one per type+entity_id
        $wpdb->query("
            DELETE q1 FROM {$tables['qr_codes']} q1
            INNER JOIN {$tables['qr_codes']} q2
            WHERE q1.type = q2.type 
            AND q1.entity_id = q2.entity_id 
            AND q1.id > q2.id
        ");

        // Add columns to qr_scans table if they don't exist
        $scan_columns = $wpdb->get_col("DESCRIBE {$tables['qr_scans']}", 0);

        if (!in_array('organization_id', $scan_columns)) {
            $wpdb->query("ALTER TABLE {$tables['qr_scans']} ADD COLUMN organization_id bigint(20) UNSIGNED DEFAULT NULL AFTER qr_code_id");
        }
        if (!in_array('country', $scan_columns)) {
            $wpdb->query("ALTER TABLE {$tables['qr_scans']} ADD COLUMN country varchar(100) DEFAULT NULL AFTER referrer");
        }
        if (!in_array('device_type', $scan_columns)) {
            $wpdb->query("ALTER TABLE {$tables['qr_scans']} ADD COLUMN device_type varchar(50) DEFAULT 'desktop' AFTER country");
        }

        // Add columns to flyers table if they don't exist
        $flyer_columns = $wpdb->get_col("DESCRIBE {$tables['flyers']}", 0);

        if (!in_array('type', $flyer_columns)) {
            $wpdb->query("ALTER TABLE {$tables['flyers']} ADD COLUMN type varchar(50) NOT NULL DEFAULT 'unit' AFTER organization_id");
        }
        if (!in_array('entity_id', $flyer_columns)) {
            $wpdb->query("ALTER TABLE {$tables['flyers']} ADD COLUMN entity_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER type");
        }
        if (!in_array('title', $flyer_columns)) {
            $wpdb->query("ALTER TABLE {$tables['flyers']} ADD COLUMN title varchar(255) DEFAULT NULL AFTER template");
        }
        if (!in_array('subtitle', $flyer_columns)) {
            $wpdb->query("ALTER TABLE {$tables['flyers']} ADD COLUMN subtitle varchar(255) DEFAULT NULL AFTER title");
        }
        if (!in_array('description', $flyer_columns)) {
            $wpdb->query("ALTER TABLE {$tables['flyers']} ADD COLUMN description text DEFAULT NULL AFTER subtitle");
        }
        if (!in_array('highlight_features', $flyer_columns)) {
            $wpdb->query("ALTER TABLE {$tables['flyers']} ADD COLUMN highlight_features text DEFAULT NULL AFTER description");
        }
        if (!in_array('contact_info', $flyer_columns)) {
            $wpdb->query("ALTER TABLE {$tables['flyers']} ADD COLUMN contact_info text DEFAULT NULL AFTER highlight_features");
        }
        if (!in_array('qr_code_id', $flyer_columns)) {
            $wpdb->query("ALTER TABLE {$tables['flyers']} ADD COLUMN qr_code_id bigint(20) UNSIGNED DEFAULT NULL AFTER contact_info");
        }
        if (!in_array('include_qr', $flyer_columns)) {
            $wpdb->query("ALTER TABLE {$tables['flyers']} ADD COLUMN include_qr tinyint(1) DEFAULT 1 AFTER qr_code_id");
        }
        if (!in_array('custom_colors', $flyer_columns)) {
            $wpdb->query("ALTER TABLE {$tables['flyers']} ADD COLUMN custom_colors longtext DEFAULT NULL AFTER include_qr");
        }
        if (!in_array('thumbnail_url', $flyer_columns)) {
            $wpdb->query("ALTER TABLE {$tables['flyers']} ADD COLUMN thumbnail_url varchar(500) DEFAULT NULL AFTER file_url");
        }
        if (!in_array('status', $flyer_columns)) {
            $wpdb->query("ALTER TABLE {$tables['flyers']} ADD COLUMN status varchar(50) DEFAULT 'draft' AFTER thumbnail_url");
        }

        // Migrate unit_id to entity_id if needed
        if (in_array('unit_id', $flyer_columns) && in_array('entity_id', $flyer_columns)) {
            $wpdb->query("UPDATE {$tables['flyers']} SET entity_id = unit_id WHERE entity_id = 0 AND unit_id > 0");
        }

        error_log('Rental Gates: Database upgraded to v2.7.0');
    }

    /**
     * Initialize plugin
     */
    public function init()
    {
        // Load text domain
        load_plugin_textdomain('rental-gates', false, dirname(RENTAL_GATES_PLUGIN_BASENAME) . '/languages');

        // Check for database updates
        $this->database->check_version();

        // Check for schema upgrades (v2.7.0+ columns)
        $this->check_db_upgrade();

        // Flush rewrite rules if version changed
        $this->maybe_flush_rewrite_rules();

        // Initialize map service
        $this->init_map_service();

        // Initialize feature gate
        rg_feature_gate();

        // Initialize automation
        $this->automation = new Rental_Gates_Automation();

        // Schedule cron jobs
        if (!wp_next_scheduled('rental_gates_availability_cron')) {
            wp_schedule_event(time(), 'hourly', 'rental_gates_availability_cron');
        }
        if (!wp_next_scheduled('rental_gates_ai_credits_reset')) {
            wp_schedule_event(time(), 'daily', 'rental_gates_ai_credits_reset');
        }
    }

    /**
     * Initialize map service based on settings
     */
    private function init_map_service()
    {
        $provider = get_option('rental_gates_map_provider', 'google');

        if ($provider === 'openstreetmap') {
            $this->map_service = new Rental_Gates_OpenStreetMap();
        } else {
            $this->map_service = new Rental_Gates_Google_Maps();
        }
    }

    /**
     * Get map service instance
     */
    public function get_map_service()
    {
        if (!$this->map_service) {
            $this->init_map_service();
        }
        return $this->map_service;
    }

    /**
     * Get map configuration for frontend
     */
    public function get_map_config()
    {
        $map_service = $this->get_map_service();
        $provider = get_option('rental_gates_map_provider', 'google');

        $config = array(
            'provider' => $provider,
            'defaultCenter' => array(
                'lat' => floatval(get_option('rental_gates_default_lat', 40.7128)),
                'lng' => floatval(get_option('rental_gates_default_lng', -74.0060)),
            ),
            'defaultZoom' => intval(get_option('rental_gates_default_zoom', 12)),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rental_gates_map'),
            'strings' => array(
                'loading' => __('Loading...', 'rental-gates'),
                'noResults' => __('No properties found in this area', 'rental-gates'),
                'searchPlaceholder' => __('Search address or city...', 'rental-gates'),
                'viewDetails' => __('View Details', 'rental-gates'),
                'available' => __('Available', 'rental-gates'),
                'comingSoon' => __('Coming Soon', 'rental-gates'),
                'bedrooms' => __('Bedrooms', 'rental-gates'),
                'bathrooms' => __('Bathrooms', 'rental-gates'),
            ),
        );

        // Add provider-specific config
        if ($map_service) {
            $config['providerConfig'] = $map_service->get_js_config();
        }

        return $config;
    }

    /**
     * Flush rewrite rules if version has changed
     */
    private function maybe_flush_rewrite_rules()
    {
        $stored_version = get_option('rental_gates_version');

        if ($stored_version !== RENTAL_GATES_VERSION) {
            flush_rewrite_rules();
            update_option('rental_gates_version', RENTAL_GATES_VERSION);
        }
    }

    /**
     * Initialize REST API
     */
    public function init_api()
    {
        try {
            if (class_exists('Rental_Gates_REST_API')) {
                $this->api = new Rental_Gates_REST_API();
                $this->api->register_routes();
            }
        } catch (Exception $e) {
            error_log('Rental Gates: Failed to initialize REST API: ' . $e->getMessage());
        }
    }

    /**
     * Add rewrite rules for custom pages
     */
    public function add_rewrite_rules()
    {
        // Check if we need to flush rewrites (version change)
        $stored_version = get_option('rental_gates_rewrite_version', '0');
        if (version_compare($stored_version, RENTAL_GATES_VERSION, '<')) {
            // Schedule flush for end of init
            add_action('init', function () {
                flush_rewrite_rules(false);
                update_option('rental_gates_rewrite_version', RENTAL_GATES_VERSION);
            }, 999);
        }

        // Public map
        add_rewrite_rule('^rental-gates/map/?$', 'index.php?rental_gates_page=map', 'top');

        // Public building/unit pages (QR destination)
        add_rewrite_rule('^rental-gates/b/([a-zA-Z0-9-]+)/?$', 'index.php?rental_gates_page=building&rental_gates_building_slug=$matches[1]', 'top');
        add_rewrite_rule('^rental-gates/building/([a-zA-Z0-9-]+)/?$', 'index.php?rental_gates_page=building&rental_gates_building_slug=$matches[1]', 'top');
        add_rewrite_rule('^rental-gates/listings/([a-zA-Z0-9-]+)/([a-zA-Z0-9-]+)/?$', 'index.php?rental_gates_page=unit&rental_gates_building_slug=$matches[1]&rental_gates_unit_slug=$matches[2]', 'top');

        // Public organization profile
        add_rewrite_rule('^rental-gates/profile/([a-zA-Z0-9-]+)/?$', 'index.php?rental_gates_page=org-profile&rental_gates_org_slug=$matches[1]', 'top');

        // Owner/Manager Dashboard
        add_rewrite_rule('^rental-gates/dashboard/?$', 'index.php?rental_gates_page=owner-dashboard', 'top');
        add_rewrite_rule('^rental-gates/dashboard/(.+)/?$', 'index.php?rental_gates_page=owner-dashboard&rental_gates_section=$matches[1]', 'top');

        // Staff Dashboard
        add_rewrite_rule('^rental-gates/staff/?$', 'index.php?rental_gates_page=staff-dashboard', 'top');
        add_rewrite_rule('^rental-gates/staff/(.+)/?$', 'index.php?rental_gates_page=staff-dashboard&rental_gates_section=$matches[1]', 'top');

        // Tenant Portal
        add_rewrite_rule('^rental-gates/tenant/?$', 'index.php?rental_gates_page=tenant-portal', 'top');
        add_rewrite_rule('^rental-gates/tenant/(.+)/?$', 'index.php?rental_gates_page=tenant-portal&rental_gates_section=$matches[1]', 'top');

        // Vendor Portal
        add_rewrite_rule('^rental-gates/vendor/?$', 'index.php?rental_gates_page=vendor-portal', 'top');
        add_rewrite_rule('^rental-gates/vendor/(.+)/?$', 'index.php?rental_gates_page=vendor-portal&rental_gates_section=$matches[1]', 'top');

        // Site Admin
        add_rewrite_rule('^rental-gates/admin/?$', 'index.php?rental_gates_page=site-admin', 'top');
        add_rewrite_rule('^rental-gates/admin/(.+)/?$', 'index.php?rental_gates_page=site-admin&rental_gates_section=$matches[1]', 'top');

        // Login/Register
        add_rewrite_rule('^rental-gates/login/?$', 'index.php?rental_gates_page=login', 'top');
        add_rewrite_rule('^rental-gates/register/?$', 'index.php?rental_gates_page=register', 'top');
        add_rewrite_rule('^rental-gates/reset-password/?$', 'index.php?rental_gates_page=reset-password', 'top');
        add_rewrite_rule('^rental-gates/checkout/?$', 'index.php?rental_gates_page=checkout', 'top');

        // Pricing page
        add_rewrite_rule('^rental-gates/pricing/?$', 'index.php?rental_gates_page=pricing', 'top');

        // Public pages
        add_rewrite_rule('^rental-gates/about/?$', 'index.php?rental_gates_page=about', 'top');
        add_rewrite_rule('^rental-gates/contact/?$', 'index.php?rental_gates_page=contact', 'top');
        add_rewrite_rule('^rental-gates/faq/?$', 'index.php?rental_gates_page=faq', 'top');
        add_rewrite_rule('^rental-gates/privacy/?$', 'index.php?rental_gates_page=privacy', 'top');
        add_rewrite_rule('^rental-gates/terms/?$', 'index.php?rental_gates_page=terms', 'top');

        // Application form (public) - with building/unit slugs
        add_rewrite_rule('^rental-gates/apply/([a-zA-Z0-9-]+)/([a-zA-Z0-9-]+)/?$', 'index.php?rental_gates_page=apply&rental_gates_building_slug=$matches[1]&rental_gates_unit_slug=$matches[2]', 'top');

        // Application form (by token - for invited applications)
        add_rewrite_rule('^rental-gates/apply/([a-zA-Z0-9-]+)/?$', 'index.php?rental_gates_page=apply&rental_gates_token=$matches[1]', 'top');
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars)
    {
        $vars[] = 'rental_gates_page';
        $vars[] = 'rental_gates_building_slug';
        $vars[] = 'rental_gates_unit_slug';
        $vars[] = 'rental_gates_org_slug';
        $vars[] = 'rental_gates_section';
        $vars[] = 'rental_gates_token';
        return $vars;
    }

    /**
     * Prevent WordPress canonical redirects for rental-gates URLs
     */
    public function prevent_canonical_redirect($redirect_url, $requested_url)
    {
        // Check if this is a rental-gates URL
        if (strpos($requested_url, '/rental-gates/') !== false) {
            // Return false to prevent redirect
            return false;
        }
        return $redirect_url;
    }

    /**
     * Handle custom routes
     */
    public function handle_custom_routes()
    {
        // Parse URL directly - don't rely on WordPress rewrite rules
        $page = null;
        $request_uri = isset($_SERVER['REQUEST_URI']) ? urldecode($_SERVER['REQUEST_URI']) : '';

        // Check if this is a rental-gates URL
        if (preg_match('#^/rental-gates/(.*)#', parse_url($request_uri, PHP_URL_PATH), $matches)) {
            $path = trim($matches[1], '/');
            $path_parts = explode('/', $path);

            // Determine the page type from URL structure
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

        // FALLBACK: Try WordPress query var if URL parsing didn't match
        if (!$page) {
            $page = get_query_var('rental_gates_page');
        }

        if (!$page) {
            return;
        }

        // Set headers for SPA-like behavior
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
                if (!$this->check_role(['rental_gates_owner', 'rental_gates_manager'])) {
                    wp_redirect(home_url('/rental-gates/login'));
                    exit;
                }
                $this->load_template('dashboard/owner');
                break;

            case 'staff-dashboard':
                if (!$this->check_role(['rental_gates_staff'])) {
                    wp_redirect(home_url('/rental-gates/login'));
                    exit;
                }
                $this->load_template('dashboard/staff');
                break;

            case 'tenant-portal':
                if (!$this->check_role(['rental_gates_tenant'])) {
                    wp_redirect(home_url('/rental-gates/login'));
                    exit;
                }
                $this->load_template('dashboard/tenant');
                break;

            case 'vendor-portal':
                if (!$this->check_role(['rental_gates_vendor'])) {
                    wp_redirect(home_url('/rental-gates/login'));
                    exit;
                }
                $this->load_template('dashboard/vendor');
                break;

            case 'site-admin':
                if (!$this->check_role(['rental_gates_site_admin'])) {
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
                // Handle form submission
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rental_gates_do_reset_password') {
                    $this->handle_reset_password_submit();
                }
                $this->load_template('auth/reset-password');
                break;

            case 'checkout':
                // Must be logged in to access checkout
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

    /**
     * Check if current user has required role
     */
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

        // Site admin can access everything
        if (in_array('rental_gates_site_admin', (array) $user->roles) || in_array('administrator', (array) $user->roles)) {
            return true;
        }

        return false;
    }

    /**
     * Redirect user to appropriate dashboard
     */
    private function redirect_to_dashboard()
    {
        $user = wp_get_current_user();

        // For administrators, redirect to owner dashboard (they can manage properties)
        if (in_array('administrator', (array) $user->roles)) {
            wp_redirect(home_url('/rental-gates/dashboard'));
        } elseif (in_array('rental_gates_site_admin', (array) $user->roles)) {
            wp_redirect(home_url('/rental-gates/admin'));
        } elseif (array_intersect(['rental_gates_owner', 'rental_gates_manager'], (array) $user->roles)) {
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

    /**
     * Load template
     */
    private function load_template($template)
    {
        // Check for direct template
        $template_path = RENTAL_GATES_PLUGIN_DIR . 'templates/' . $template . '.php';

        // If not found, check for layout.php in subdirectory
        if (!file_exists($template_path)) {
            $template_path = RENTAL_GATES_PLUGIN_DIR . 'templates/' . $template . '/layout.php';
        }

        if (file_exists($template_path)) {
            // Parse current section from URL for any dashboard role
            $current_page = '';
            if (isset($_SERVER['REQUEST_URI'])) {
                $uri = urldecode($_SERVER['REQUEST_URI']);
                // Match all dashboard role URL patterns
                if (preg_match('#/rental-gates/(?:dashboard|staff|tenant|vendor|admin)/([^?]+)#', $uri, $matches)) {
                    $current_page = trim($matches[1], '/');
                }
            }

            // FALLBACK: Only use query var if URL parsing failed
            if (empty($current_page)) {
                $query_section = get_query_var('rental_gates_section');
                if (!empty($query_section)) {
                    $current_page = $query_section;
                }
            }

            $organization = null;
            $page_title = __('Dashboard', 'rental-gates');
            $content_template = null;

            // Get organization for logged-in users
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

            // Determine content template based on dashboard role
            if (strpos($template, 'dashboard/') === 0) {
                // Determine role-specific sections directory
                $sections_dir = RENTAL_GATES_PLUGIN_DIR . 'templates/dashboard/sections/';
                if (strpos($template, 'dashboard/staff') === 0) {
                    $sections_dir = RENTAL_GATES_PLUGIN_DIR . 'templates/dashboard/staff/sections/';
                } elseif (strpos($template, 'dashboard/tenant') === 0) {
                    $sections_dir = RENTAL_GATES_PLUGIN_DIR . 'templates/dashboard/tenant/sections/';
                } elseif (strpos($template, 'dashboard/vendor') === 0) {
                    $sections_dir = RENTAL_GATES_PLUGIN_DIR . 'templates/dashboard/vendor/sections/';
                } elseif (strpos($template, 'dashboard/admin') === 0) {
                    $sections_dir = RENTAL_GATES_PLUGIN_DIR . 'templates/dashboard/admin/sections/';
                }
                $content_template = $this->get_dashboard_content_template($current_page, $sections_dir);
            }

            // Buffer output to catch any errors
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
            // Log the error for debugging
            error_log('Rental Gates: Template not found: ' . $template . ' (tried: ' . $template_path . ')');
            wp_die(__('Template not found: ', 'rental-gates') . esc_html($template));
        }
    }

    /**
     * Get dashboard content template based on section
     */
    private function get_dashboard_content_template($section, $sections_dir = null)
    {
        if (!$sections_dir) {
            $sections_dir = RENTAL_GATES_PLUGIN_DIR . 'templates/dashboard/sections/';
        }

        // Parse the section for special routes
        // Format: buildings, buildings/add, buildings/{id}, buildings/{id}/edit,
        //         buildings/{id}/units/add, buildings/{id}/units/{unit_id}, etc.

        // PRIMARY: Always parse from REQUEST_URI for reliability
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = urldecode($_SERVER['REQUEST_URI']);
            // Extract section from any dashboard role URL pattern
            if (preg_match('#/rental-gates/(?:dashboard|staff|tenant|vendor|admin)/([^?]+)#', $uri, $matches)) {
                $section = trim($matches[1], '/');
            }
        }

        $parts = explode('/', trim($section, '/'));
        $base = $parts[0] ?? 'dashboard';

        // Route mapping
        switch ($base) {
            case 'dashboard':
            case '':
                // Owner uses overview.php, other roles use dashboard.php
                if (file_exists($sections_dir . 'overview.php')) {
                    return $sections_dir . 'overview.php';
                }
                return $sections_dir . 'dashboard.php';

            case 'buildings':
                if (count($parts) === 1) {
                    // /buildings - list
                    return $sections_dir . 'buildings.php';
                } elseif ($parts[1] === 'add') {
                    // /buildings/add - add form
                    $_GET['id'] = 0;
                    return $sections_dir . 'buildings-form.php';
                } elseif (is_numeric($parts[1])) {
                    $building_id = intval($parts[1]);
                    $_GET['id'] = $building_id;

                    if (count($parts) === 2) {
                        // /buildings/{id} - detail view
                        return $sections_dir . 'building-detail.php';
                    } elseif (isset($parts[2]) && $parts[2] === 'edit') {
                        // /buildings/{id}/edit - edit form
                        return $sections_dir . 'buildings-form.php';
                    } elseif (isset($parts[2]) && $parts[2] === 'units') {
                        $_GET['building_id'] = $building_id;

                        if (isset($parts[3]) && $parts[3] === 'add') {
                            // /buildings/{id}/units/add - add unit
                            $_GET['unit_id'] = 0;
                            return $sections_dir . 'unit-form.php';
                        } elseif (isset($parts[3]) && is_numeric($parts[3])) {
                            $_GET['unit_id'] = intval($parts[3]);

                            if (isset($parts[4]) && $parts[4] === 'edit') {
                                // /buildings/{id}/units/{unit_id}/edit
                                return $sections_dir . 'unit-form.php';
                            } else {
                                // /buildings/{id}/units/{unit_id} - unit detail
                                return $sections_dir . 'unit-detail.php';
                            }
                        }
                    }
                }
                // Default to buildings list
                return $sections_dir . 'buildings.php';

            case 'tenants':
                if (count($parts) === 1) {
                    // /tenants - list
                    return $sections_dir . 'tenants.php';
                } elseif ($parts[1] === 'add') {
                    // /tenants/add - add form
                    return $sections_dir . 'tenant-form.php';
                } elseif (is_numeric($parts[1])) {
                    $tenant_id = intval($parts[1]);

                    if (count($parts) === 2) {
                        // /tenants/{id} - detail view
                        return $sections_dir . 'tenant-detail.php';
                    } elseif (isset($parts[2]) && $parts[2] === 'edit') {
                        // /tenants/{id}/edit - edit form
                        return $sections_dir . 'tenant-form.php';
                    }
                }
                // Default to tenants list
                return $sections_dir . 'tenants.php';

            case 'leases':
                if (count($parts) === 1) {
                    // /leases - list
                    return $sections_dir . 'leases.php';
                } elseif ($parts[1] === 'add') {
                    // /leases/add - add form
                    return $sections_dir . 'lease-form.php';
                } elseif (is_numeric($parts[1])) {
                    $lease_id = intval($parts[1]);

                    if (count($parts) === 2) {
                        // /leases/{id} - detail view
                        return $sections_dir . 'lease-detail.php';
                    } elseif (isset($parts[2]) && $parts[2] === 'edit') {
                        // /leases/{id}/edit - edit form
                        return $sections_dir . 'lease-form.php';
                    }
                }
                // Default to leases list
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
                    // Stripe Checkout return page
                    return $sections_dir . 'payment-return.php';
                } elseif (is_numeric($parts[1])) {
                    if (isset($parts[2]) && $parts[2] === 'edit') {
                        return $sections_dir . 'payment-form.php';
                    }
                    return $sections_dir . 'payment-detail.php';
                }
                return $sections_dir . 'payments.php';

            case 'invoice':
                // Invoice/Receipt viewer
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

            case 'debug-leases':
                // Admin-only diagnostic tool
                return $sections_dir . 'debug-leases.php';

            case 'staff':
                if (count($parts) === 1) {
                    // /staff - list
                    return $sections_dir . 'staff.php';
                } elseif ($parts[1] === 'add') {
                    // /staff/add - add form
                    return $sections_dir . 'staff-form.php';
                } elseif (is_numeric($parts[1])) {
                    $staff_id = intval($parts[1]);
                    $_GET['staff_id'] = $staff_id;

                    if (count($parts) === 2) {
                        // /staff/{id} - detail view
                        return $sections_dir . 'staff-detail.php';
                    } elseif (isset($parts[2]) && $parts[2] === 'edit') {
                        // /staff/{id}/edit - edit form
                        return $sections_dir . 'staff-form.php';
                    }
                }
                return $sections_dir . 'staff.php';

            default:
                // For non-owner roles, try the section name directly as a file
                $direct_file = $sections_dir . $base . '.php';
                if (file_exists($direct_file)) {
                    return $direct_file;
                }
                // Fall back to overview/dashboard
                if (file_exists($sections_dir . 'overview.php')) {
                    return $sections_dir . 'overview.php';
                }
                return $sections_dir . 'dashboard.php';
        }
    }

    /**
     * Enqueue public assets
     */
    public function enqueue_public_assets()
    {
        // Only load on Rental Gates pages (check both query var and URL)
        $is_rental_gates_page = get_query_var('rental_gates_page');
        if (!$is_rental_gates_page) {
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            if (strpos($request_uri, '/rental-gates/') !== false) {
                $is_rental_gates_page = true;
            }
        }
        if (!$is_rental_gates_page) {
            return;
        }

        $section = $this->get_enqueue_section();

        // WordPress Media Library: only on form/upload pages
        if (is_user_logged_in() && $this->needs_media_library($section)) {
            wp_enqueue_media();
        }

        // CSS
        wp_enqueue_style(
            'rental-gates-main',
            RENTAL_GATES_PLUGIN_URL . 'assets/css/rental-gates.css',
            array(),
            RENTAL_GATES_VERSION
        );

        wp_enqueue_style(
            'rental-gates-components',
            RENTAL_GATES_PLUGIN_URL . 'assets/css/components.css',
            array('rental-gates-main'),
            RENTAL_GATES_VERSION
        );

        // Google Fonts
        wp_enqueue_style(
            'rental-gates-fonts',
            'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap',
            array(),
            RENTAL_GATES_VERSION
        );

        // Chart.js for analytics (load on dashboard pages)
        if (in_array($section, array('overview', 'reports', 'billing', 'marketing-analytics', ''), true)) {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                array(),
                '4.4.1',
                true
            );
        }

        // JS domain modules (loaded before main entry point)
        // Core modules: modal + toast always needed (small, used everywhere)
        wp_enqueue_script(
            'rental-gates-modal',
            RENTAL_GATES_PLUGIN_URL . 'assets/js/rental-gates-modal.js',
            array('jquery'),
            RENTAL_GATES_VERSION,
            true
        );
        wp_enqueue_script(
            'rental-gates-toast',
            RENTAL_GATES_PLUGIN_URL . 'assets/js/rental-gates-toast.js',
            array('jquery'),
            RENTAL_GATES_VERSION,
            true
        );

        $app_deps = array('jquery', 'rental-gates-modal', 'rental-gates-toast');

        // Media module: only on pages with image upload
        if ($this->needs_media_library($section)) {
            wp_enqueue_script(
                'rental-gates-media',
                RENTAL_GATES_PLUGIN_URL . 'assets/js/rental-gates-media.js',
                array('jquery'),
                RENTAL_GATES_VERSION,
                true
            );
            $app_deps[] = 'rental-gates-media';
        }

        // QR module: only on pages with QR features
        if ($this->needs_qr($section)) {
            wp_enqueue_script(
                'rental-gates-qr',
                RENTAL_GATES_PLUGIN_URL . 'assets/js/rental-gates-qr.js',
                array('jquery'),
                RENTAL_GATES_VERSION,
                true
            );
            $app_deps[] = 'rental-gates-qr';
        }

        // Main entry point
        wp_enqueue_script(
            'rental-gates-app',
            RENTAL_GATES_PLUGIN_URL . 'assets/js/rental-gates.js',
            $app_deps,
            RENTAL_GATES_VERSION,
            true
        );

        // Map scripts based on provider
        if ($this->needs_maps($section)) {
            $map_provider = get_option('rental_gates_map_provider', 'google');
            if ($map_provider === 'google') {
                $api_key = get_option('rental_gates_google_maps_api_key', '');
                if ($api_key) {
                    wp_enqueue_script(
                        'google-maps',
                        'https://maps.googleapis.com/maps/api/js?key=' . $api_key . '&libraries=places',
                        array(),
                        null,
                        true
                    );
                }
            } else {
                wp_enqueue_style(
                    'leaflet',
                    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
                    array(),
                    '1.9.4'
                );
                wp_enqueue_script(
                    'leaflet',
                    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
                    array(),
                    '1.9.4',
                    true
                );
            }
        }

        // Localize script
        wp_localize_script('rental-gates-app', 'rentalGatesData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'apiBase' => rest_url('rental-gates/v1'),
            'restUrl' => rest_url('rental-gates/v1/'),
            'nonce' => wp_create_nonce('rental_gates_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'homeUrl' => home_url(),
            'pluginUrl' => RENTAL_GATES_PLUGIN_URL,
            'isLoggedIn' => is_user_logged_in(),
            'currentUser' => $this->get_current_user_data(),
            'mapProvider' => get_option('rental_gates_map_provider', 'google'),
            'googleMapsApiKey' => get_option('rental_gates_google_maps_api_key', ''),
            'defaultLang' => get_option('rental_gates_default_language', 'en'),
            'isRTL' => is_rtl(),
            'i18n' => $this->get_i18n_strings(),
        ));
    }

    /**
     * Output rental gates data early in wp_head
     */
    public function output_rental_gates_data_early()
    {
        // Check both query var and direct URL
        $is_rental_gates_page = get_query_var('rental_gates_page');

        // Also check URL directly for cases where rewrite rules might not match
        if (!$is_rental_gates_page) {
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            if (strpos($request_uri, '/rental-gates/') !== false) {
                $is_rental_gates_page = true;
            }
        }

        if (!$is_rental_gates_page) {
            return;
        }

        $data = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'apiBase' => rest_url('rental-gates/v1'),
            'restUrl' => rest_url('rental-gates/v1/'),
            'nonce' => wp_create_nonce('rental_gates_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'homeUrl' => home_url(),
            'pluginUrl' => RENTAL_GATES_PLUGIN_URL,
            'isLoggedIn' => is_user_logged_in(),
            'mapProvider' => get_option('rental_gates_map_provider', 'google'),
            'pwaEnabled' => rental_gates_pwa()->is_enabled(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
        );

        echo '<script id="rental-gates-data">window.rentalGatesData = ' . Rental_Gates_Security::json_for_script($data) . '; var ajaxurl = "' . esc_js(admin_url('admin-ajax.php')) . '";</script>' . "\n";
    }

    /**
     * Output resource hints (preconnect) for external domains.
     */
    public function output_preconnect_hints()
    {
        $is_rental_gates_page = get_query_var('rental_gates_page');
        if (!$is_rental_gates_page) {
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            if (strpos($request_uri, '/rental-gates/') === false) {
                return;
            }
        }
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'rental-gates') === false) {
            return;
        }

        wp_enqueue_style(
            'rental-gates-admin',
            RENTAL_GATES_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            RENTAL_GATES_VERSION
        );

        wp_enqueue_script(
            'rental-gates-admin',
            RENTAL_GATES_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            RENTAL_GATES_VERSION,
            true
        );
    }

    /**
     * Get current user data for JS
     */
    private function get_current_user_data()
    {
        if (!is_user_logged_in()) {
            return null;
        }

        $user = wp_get_current_user();
        $org_id = Rental_Gates_Roles::get_organization_id();

        return array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'roles' => $user->roles,
            'avatar' => get_avatar_url($user->ID),
            'organizationId' => $org_id,
        );
    }

    /**
     * Get i18n strings for JS
     */
    private function get_i18n_strings()
    {
        return array(
            // General
            'loading' => __('Loading...', 'rental-gates'),
            'error' => __('An error occurred', 'rental-gates'),
            'success' => __('Success', 'rental-gates'),
            'confirm' => __('Are you sure?', 'rental-gates'),
            'cancel' => __('Cancel', 'rental-gates'),
            'save' => __('Save', 'rental-gates'),
            'delete' => __('Delete', 'rental-gates'),
            'edit' => __('Edit', 'rental-gates'),
            'view' => __('View', 'rental-gates'),
            'add' => __('Add', 'rental-gates'),
            'search' => __('Search...', 'rental-gates'),
            'noResults' => __('No results found', 'rental-gates'),

            // Availability states
            'available' => __('Available', 'rental-gates'),
            'comingSoon' => __('Coming Soon', 'rental-gates'),
            'occupied' => __('Occupied', 'rental-gates'),
            'renewalPending' => __('Renewal Pending', 'rental-gates'),
            'unlisted' => __('Unlisted', 'rental-gates'),

            // Map
            'pinLocation' => __('Click on the map to pin location', 'rental-gates'),
            'addressDerived' => __('Address derived from map pin', 'rental-gates'),

            // Delete confirmation modal
            'deleteConfirmTitle' => __('Confirm Delete', 'rental-gates'),
            'deleteConfirmMessage' => __('Are you sure you want to delete this item? This action cannot be undone.', 'rental-gates'),
            'deleting' => __('Deleting...', 'rental-gates'),
            'deleted' => __('Successfully deleted', 'rental-gates'),

            // Media library
            'selectImage' => __('Select Image', 'rental-gates'),
            'selectImages' => __('Select Images', 'rental-gates'),
            'useImage' => __('Use this image', 'rental-gates'),
            'useImages' => __('Use these images', 'rental-gates'),
            'removeImage' => __('Remove image', 'rental-gates'),
            'uploadImage' => __('Upload Image', 'rental-gates'),
            'addPhotos' => __('Add Photos', 'rental-gates'),

            // QR Code
            'generateQR' => __('Generate QR Code', 'rental-gates'),
            'qrGenerated' => __('QR code generated successfully', 'rental-gates'),
            'downloadPNG' => __('Download PNG', 'rental-gates'),
            'scans' => __('Scans', 'rental-gates'),
            'lastScan' => __('Last Scan', 'rental-gates'),

            // Toast messages
            'networkError' => __('Network error occurred', 'rental-gates'),
            'saveSuccess' => __('Changes saved successfully', 'rental-gates'),
            'saveFailed' => __('Failed to save changes', 'rental-gates'),
        );
    }

    // --------------------------------------------------
    // Section-aware lazy loading helpers
    // --------------------------------------------------

    /**
     * Detect current dashboard section from URL.
     */
    private function get_enqueue_section() {
        $uri = isset($_SERVER['REQUEST_URI']) ? urldecode($_SERVER['REQUEST_URI']) : '';
        // Match section in any dashboard role URL
        if (preg_match('#/rental-gates/(?:dashboard|staff|tenant|vendor|admin)/([^/?]+)#', $uri, $m)) {
            return $m[1];
        }
        // Top-level page (map, login, etc.)
        if (preg_match('#/rental-gates/([^/?]+)#', $uri, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * Does the current URL point to a form/edit/add sub-route?
     */
    private function is_form_page() {
        $uri = isset($_SERVER['REQUEST_URI']) ? urldecode($_SERVER['REQUEST_URI']) : '';
        return (bool) preg_match('#/(add|edit|new)(/|$|\?)#', $uri);
    }

    /**
     * Does this section need the WordPress Media Library?
     */
    private function needs_media_library($section) {
        $media_sections = array('documents', 'settings', 'marketing');
        if (in_array($section, $media_sections, true)) {
            return true;
        }
        $form_sections = array('buildings', 'tenants', 'leases', 'maintenance', 'staff', 'payments', 'vendors');
        if (in_array($section, $form_sections, true) && $this->is_form_page()) {
            return true;
        }
        return false;
    }

    /**
     * Does this section need map scripts?
     */
    private function needs_maps($section) {
        $map_sections = array('map', 'building', 'b', 'listings');
        if (in_array($section, $map_sections, true)) {
            return true;
        }
        if (in_array($section, array('buildings'), true) && $this->is_form_page()) {
            return true;
        }
        return false;
    }

    /**
     * Does this section need QR code features?
     */
    private function needs_qr($section) {
        $qr_sections = array('buildings', 'marketing', 'settings');
        return in_array($section, $qr_sections, true);
    }

    /**
     * Handle image upload via AJAX
     */
    public function handle_image_upload()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        if (!isset($_FILES['file'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'rental-gates')));
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('file', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }

        // Optimize uploaded image
        if (class_exists('Rental_Gates_Image_Optimizer')) {
            $optimized = Rental_Gates_Image_Optimizer::optimize_attachment($attachment_id);
            if (is_wp_error($optimized)) {
                error_log('Rental Gates: Image optimization failed: ' . $optimized->get_error_message());
            }
        }

        wp_send_json_success(array(
            'id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
            'thumbnail' => wp_get_attachment_image_url($attachment_id, 'medium'),
        ));
    }

    /**
     * Handle QR code generation
     */
    public function handle_qr_generation()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        $type = sanitize_text_field($_POST['type'] ?? 'building');
        $id = intval($_POST['entity_id'] ?? $_POST['id'] ?? 0);
        $size = sanitize_text_field($_POST['size'] ?? 'medium');

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid ID', 'rental-gates')));
        }

        $qr = new Rental_Gates_QR();

        if ($type === 'unit') {
            $result = $qr->generate_for_unit($id, $size);
        } elseif ($type === 'organization') {
            $result = $qr->generate_for_organization($id, $size);
        } else {
            $result = $qr->generate_for_building($id, $size);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handle get QR code AJAX request
     */
    public function handle_get_qr()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        $qr_id = intval($_POST['qr_id'] ?? 0);
        $size = sanitize_text_field($_POST['size'] ?? 'medium');

        if (!$qr_id) {
            wp_send_json_error(array('message' => __('Invalid QR ID', 'rental-gates')));
        }

        $qr = Rental_Gates_QR::get($qr_id);

        if (!$qr) {
            wp_send_json_error(array('message' => __('QR code not found', 'rental-gates')));
        }

        // Get with requested size
        $sizes = array('small' => 150, 'medium' => 300, 'large' => 500, 'print' => 1000);
        $size_px = $sizes[$size] ?? 300;
        $qr['qr_image'] = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size_px . 'x' . $size_px . '&data=' . urlencode($qr['url']);

        wp_send_json_success($qr);
    }

    /**
     * Handle bulk QR code generation
     */
    public function handle_bulk_generate_qr()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!current_user_can('rg_manage_marketing') && !current_user_can('rg_manage_buildings') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $include_buildings = !empty($_POST['include_buildings']);
        $include_units = !empty($_POST['include_units']);
        $size = sanitize_text_field($_POST['size'] ?? 'medium');

        $qr = new Rental_Gates_QR();
        $results = array();

        if ($include_buildings) {
            $building_results = $qr->generate_all_buildings($org_id, $size);
            $results = array_merge($results, $building_results);
        }

        if ($include_units) {
            $unit_results = $qr->generate_all_units($org_id, $size);
            $results = array_merge($results, $unit_results);
        }

        wp_send_json_success(array(
            'count' => count($results),
            'items' => $results
        ));
    }

    /**
     * Handle QR analytics request
     */
    public function handle_qr_analytics()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        $qr_id = intval($_POST['qr_id'] ?? 0);
        $days = intval($_POST['days'] ?? 30);

        if (!$qr_id) {
            wp_send_json_error(array('message' => __('Invalid QR ID', 'rental-gates')));
        }

        $analytics = Rental_Gates_QR::get_scan_analytics($qr_id, $days);

        wp_send_json_success($analytics);
    }

    /**
     * Handle QR code deletion
     */
    public function handle_delete_qr()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!current_user_can('rg_manage_marketing') && !current_user_can('rg_manage_buildings') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $qr_id = intval($_POST['qr_id'] ?? 0);

        if (!$qr_id) {
            wp_send_json_error(array('message' => __('Invalid QR ID', 'rental-gates')));
        }

        // Verify QR belongs to user's organization
        $org_id = Rental_Gates_Roles::get_organization_id();
        $qr = Rental_Gates_QR::get($qr_id);

        if (!$qr || $qr['organization_id'] != $org_id) {
            wp_send_json_error(array('message' => __('QR code not found', 'rental-gates')));
        }

        $result = Rental_Gates_QR::delete($qr_id);

        if ($result) {
            wp_send_json_success(array('message' => __('QR code deleted successfully', 'rental-gates')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete QR code', 'rental-gates')));
        }
    }

    /**
     * Handle flyer creation
     */
    public function handle_create_flyer()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!current_user_can('rg_manage_marketing') && !current_user_can('rg_manage_buildings') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $unit_id = intval($_POST['unit_id'] ?? 0);
        $template = sanitize_text_field($_POST['template'] ?? 'modern');
        $include_qr = !empty($_POST['include_qr']);
        $title = sanitize_text_field($_POST['title'] ?? '');

        if (!$unit_id) {
            wp_send_json_error(array('message' => __('Please select a unit', 'rental-gates')));
        }

        // Generate flyer
        $result = Rental_Gates_Flyer::create(array(
            'organization_id' => $org_id,
            'type' => 'unit',
            'entity_id' => $unit_id,
            'template' => $template,
            'include_qr' => $include_qr,
            'title' => $title
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handle flyer regeneration
     */
    public function handle_regenerate_flyer()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        $flyer_id = intval($_POST['flyer_id'] ?? 0);

        if (!$flyer_id) {
            wp_send_json_error(array('message' => __('Invalid flyer ID', 'rental-gates')));
        }

        $result = Rental_Gates_Flyer::regenerate($flyer_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handle save building AJAX request
     */
    public function handle_save_building()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['building_nonce'] ?? '', 'rental_gates_building')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        // Check permissions - use correct capability name
        if (!current_user_can('rg_manage_buildings') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        // Validate required fields
        $lat = floatval($_POST['latitude'] ?? 0);
        $lng = floatval($_POST['longitude'] ?? 0);
        $address = sanitize_text_field($_POST['derived_address'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');

        if (!$lat || !$lng) {
            wp_send_json_error(__('Please place a pin on the map', 'rental-gates'));
        }

        if (empty($name)) {
            wp_send_json_error(__('Building name is required', 'rental-gates'));
        }

        // Get user's organization (will auto-create for admins if needed)
        $user_org_id = Rental_Gates_Roles::get_organization_id();

        if (!$user_org_id) {
            wp_send_json_error(__('No organization found. Please contact support.', 'rental-gates'));
        }

        // Use user's organization ID (ignore what was passed in form for security)
        $org_id = $user_org_id;

        // Check feature gate for new buildings
        $building_id = intval($_POST['building_id'] ?? 0);
        if (!$building_id) {
            // Creating new building - check limits
            $limit_check = rg_can_create('buildings', 1, $org_id);
            if (!$limit_check['allowed']) {
                wp_send_json_error(array(
                    'message' => $limit_check['message'],
                    'limit_reached' => true,
                    'current' => $limit_check['current'],
                    'limit' => $limit_check['limit'],
                ));
            }
        }

        // Handle amenities
        $amenities = isset($_POST['amenities']) ? array_map('sanitize_text_field', $_POST['amenities']) : array();

        // Prepare data
        $data = array(
            'organization_id' => $org_id,
            'name' => $name,
            'slug' => sanitize_title($name),
            'status' => sanitize_text_field($_POST['status'] ?? 'active'),
            'latitude' => $lat,
            'longitude' => $lng,
            'derived_address' => $address,
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'gallery' => $_POST['gallery'] ?? '[]', // Already JSON string from frontend
            'amenities' => $amenities, // Pass as array, model will encode
        );

        if ($building_id) {
            // Verify building belongs to user's organization before updating
            $existing = Rental_Gates_Building::get($building_id);
            if (!$existing || $existing['organization_id'] != $org_id) {
                wp_send_json_error(__('Building not found or access denied', 'rental-gates'));
            }

            // Update existing
            $result = Rental_Gates_Building::update($building_id, $data);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
        } else {
            // Create new
            $result = Rental_Gates_Building::create($data);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            // Result is the building array, get ID from it
            $building_id = is_array($result) ? $result['id'] : $result;
        }

        wp_send_json_success(array('id' => $building_id));
    }

    /**
     * Handle save unit AJAX request
     */
    public function handle_save_unit()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['unit_nonce'] ?? '', 'rental_gates_unit')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        // Check permissions - use correct capability name
        if (!current_user_can('rg_manage_units') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        // Validate required fields
        $building_id = intval($_POST['building_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $rent_amount = floatval($_POST['rent_amount'] ?? 0);

        if (!$building_id) {
            wp_send_json_error(__('Building is required', 'rental-gates'));
        }

        if (empty($name)) {
            wp_send_json_error(__('Unit name is required', 'rental-gates'));
        }

        // Get user's organization
        $user_org_id = Rental_Gates_Roles::get_organization_id();
        if (!$user_org_id) {
            wp_send_json_error(__('No organization found. Please contact support.', 'rental-gates'));
        }

        // Check feature gate for new units
        $unit_id = intval($_POST['unit_id'] ?? 0);
        if (!$unit_id) {
            // Creating new unit - check limits
            $limit_check = rg_can_create('units', 1, $user_org_id);
            if (!$limit_check['allowed']) {
                wp_send_json_error(array(
                    'message' => $limit_check['message'],
                    'limit_reached' => true,
                    'current' => $limit_check['current'],
                    'limit' => $limit_check['limit'],
                ));
            }
        }

        // Verify building belongs to user's organization
        $building = Rental_Gates_Building::get($building_id);
        if (!$building || $building['organization_id'] != $user_org_id) {
            wp_send_json_error(__('Building not found or access denied', 'rental-gates'));
        }

        // Handle amenities
        $amenities = isset($_POST['amenities']) ? array_map('sanitize_text_field', $_POST['amenities']) : array();

        // Prepare data
        $data = array(
            'organization_id' => $user_org_id,
            'building_id' => $building_id,
            'name' => $name,
            'slug' => sanitize_title($name),
            'unit_type' => sanitize_text_field($_POST['unit_type'] ?? ''),
            'rent_amount' => $rent_amount,
            'deposit_amount' => floatval($_POST['deposit_amount'] ?? 0),
            'bedrooms' => intval($_POST['bedrooms'] ?? 0),
            'bathrooms' => intval($_POST['bathrooms'] ?? 0),
            'living_rooms' => intval($_POST['living_rooms'] ?? 0),
            'kitchens' => intval($_POST['kitchens'] ?? 0),
            'parking_spots' => intval($_POST['parking_spots'] ?? 0),
            'square_footage' => intval($_POST['square_footage'] ?? 0),
            'availability' => sanitize_text_field($_POST['availability'] ?? 'available'),
            'available_from' => sanitize_text_field($_POST['available_from'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'gallery' => $_POST['gallery'] ?? '[]',
            'amenities' => $amenities, // Pass as array, model will encode
        );

        if ($unit_id) {
            // Verify unit belongs to user's organization before updating
            $existing = Rental_Gates_Unit::get($unit_id);
            if (!$existing || $existing['organization_id'] != $user_org_id) {
                wp_send_json_error(__('Unit not found or access denied', 'rental-gates'));
            }

            // Update existing
            $result = Rental_Gates_Unit::update($unit_id, $data);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
        } else {
            // Create new
            $result = Rental_Gates_Unit::create($data);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            // Result is the unit array, get ID from it
            $unit_id = is_array($result) ? $result['id'] : $result;
        }

        wp_send_json_success(array('id' => $unit_id));
    }

    /**
     * Handle geocode request (reverse geocoding from pin)
     */
    public function handle_geocode_request()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        $lat = floatval($_POST['lat'] ?? 0);
        $lng = floatval($_POST['lng'] ?? 0);

        if (!$lat || !$lng) {
            wp_send_json_error(array('message' => __('Invalid coordinates', 'rental-gates')));
        }

        $map_service = $this->get_map_service();
        $result = $map_service->reverse_geocode($lat, $lng);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handle delete building AJAX request
     */
    public function handle_delete_building()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        // Check permissions
        if (!current_user_can('rg_manage_buildings') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $building_id = intval($_POST['building_id'] ?? 0);
        if (!$building_id) {
            wp_send_json_error(__('Invalid building ID', 'rental-gates'));
        }

        // Get user's organization
        $user_org_id = Rental_Gates_Roles::get_organization_id();
        if (!$user_org_id) {
            wp_send_json_error(__('No organization found', 'rental-gates'));
        }

        // Verify building belongs to user's organization
        $building = Rental_Gates_Building::get($building_id);
        if (!$building || $building['organization_id'] != $user_org_id) {
            wp_send_json_error(__('Building not found or access denied', 'rental-gates'));
        }

        // Delete building
        $result = Rental_Gates_Building::delete($building_id);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('deleted' => true));
    }

    /**
     * Handle delete unit AJAX request
     */
    public function handle_delete_unit()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        // Check permissions
        if (!current_user_can('rg_manage_units') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $unit_id = intval($_POST['unit_id'] ?? 0);
        if (!$unit_id) {
            wp_send_json_error(__('Invalid unit ID', 'rental-gates'));
        }

        // Get user's organization
        $user_org_id = Rental_Gates_Roles::get_organization_id();
        if (!$user_org_id) {
            wp_send_json_error(__('No organization found', 'rental-gates'));
        }

        // Verify unit belongs to user's organization
        $unit = Rental_Gates_Unit::get($unit_id);
        if (!$unit || $unit['organization_id'] != $user_org_id) {
            wp_send_json_error(__('Unit not found or access denied', 'rental-gates'));
        }

        // Delete unit
        $result = Rental_Gates_Unit::delete($unit_id);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('deleted' => true, 'building_id' => $unit['building_id']));
    }

    /**
     * Handle bulk add units AJAX request
     */
    public function handle_bulk_add_units()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_bulk_units')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        // Check permissions
        if (!current_user_can('rg_manage_units') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $building_id = intval($_POST['building_id'] ?? 0);
        $prefix = sanitize_text_field($_POST['prefix'] ?? '');
        $start = intval($_POST['start'] ?? 1);
        $end = intval($_POST['end'] ?? 1);

        // Validate range
        $count = $end - $start + 1;
        if ($count <= 0 || $count > 100) {
            wp_send_json_error(__('Invalid unit range (1-100 units)', 'rental-gates'));
        }

        // Get user's organization
        $user_org_id = Rental_Gates_Roles::get_organization_id();
        if (!$user_org_id) {
            wp_send_json_error(__('No organization found', 'rental-gates'));
        }

        // Check feature gate - bulk operations module
        $module_check = rg_can_access_module('bulk_operations', $user_org_id);
        if (!$module_check['enabled']) {
            wp_send_json_error(array(
                'message' => $module_check['message'],
                'module_disabled' => true,
            ));
        }

        // Check feature gate - unit limits
        $limit_check = rg_can_create('units', $count, $user_org_id);
        if (!$limit_check['allowed']) {
            wp_send_json_error(array(
                'message' => $limit_check['message'],
                'limit_reached' => true,
                'current' => $limit_check['current'],
                'limit' => $limit_check['limit'],
                'remaining' => $limit_check['remaining'],
            ));
        }

        // Verify building belongs to user's organization
        $building = Rental_Gates_Building::get($building_id);
        if (!$building || $building['organization_id'] != $user_org_id) {
            wp_send_json_error(__('Building not found or access denied', 'rental-gates'));
        }

        // Prepare base unit data
        $base_data = array(
            'building_id' => $building_id,
            'bedrooms' => intval($_POST['bedrooms'] ?? 1),
            'bathrooms' => floatval($_POST['bathrooms'] ?? 1),
            'rent_amount' => floatval($_POST['rent'] ?? 0),
            'square_feet' => intval($_POST['sqft'] ?? 0),
            'availability' => sanitize_text_field($_POST['availability'] ?? 'available'),
            'status' => 'active',
        );

        $created = 0;
        $errors = array();
        $separator = $prefix ? ' ' : '';

        for ($i = $start; $i <= $end; $i++) {
            $unit_number = $prefix . $separator . $i;

            $data = array_merge($base_data, array(
                'unit_number' => $unit_number,
                'slug' => sanitize_title($building['slug'] . '-' . $unit_number),
            ));

            $result = Rental_Gates_Unit::create($data);
            if (is_wp_error($result)) {
                $errors[] = $unit_number . ': ' . $result->get_error_message();
            } else {
                $created++;
            }
        }

        if ($created === 0) {
            wp_send_json_error(__('Failed to create any units', 'rental-gates'));
        }

        $message = sprintf(__('%d units created', 'rental-gates'), $created);
        if (!empty($errors)) {
            $message .= '. ' . sprintf(__('%d errors', 'rental-gates'), count($errors));
        }

        wp_send_json_success(array(
            'created' => $created,
            'errors' => $errors,
            'message' => $message,
        ));
    }

    /**
     * Handle save settings AJAX request
     */
    public function handle_save_settings()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['settings_nonce'] ?? '', 'rental_gates_settings')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        // Check permissions - only owners/admins can edit settings
        if (!current_user_can('rg_manage_settings') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        // Get user's organization
        $user_org_id = Rental_Gates_Roles::get_organization_id();
        if (!$user_org_id) {
            wp_send_json_error(__('No organization found', 'rental-gates'));
        }

        // Collect settings data
        $data = array(
            'name' => sanitize_text_field($_POST['org_name'] ?? ''),
            'contact_email' => sanitize_email($_POST['contact_email'] ?? ''),
            'contact_phone' => sanitize_text_field($_POST['contact_phone'] ?? ''),
            'website' => esc_url_raw($_POST['website'] ?? ''),
            'address' => sanitize_textarea_field($_POST['address'] ?? ''),
            'timezone' => sanitize_text_field($_POST['timezone'] ?? 'America/New_York'),
            'currency' => sanitize_text_field($_POST['currency'] ?? 'USD'),
            'late_fee_grace_days' => intval($_POST['late_fee_grace_days'] ?? 5),
            'late_fee_type' => in_array($_POST['late_fee_type'] ?? '', array('flat', 'percentage')) ? $_POST['late_fee_type'] : 'flat',
            'late_fee_amount' => floatval($_POST['late_fee_amount'] ?? 0),
            'allow_partial_payments' => isset($_POST['allow_partial_payments']) ? 1 : 0,
            'coming_soon_window_days' => intval($_POST['coming_soon_window_days'] ?? 30),
            'renewal_notice_days' => intval($_POST['renewal_notice_days'] ?? 60),
        );

        // Update organization
        $result = Rental_Gates_Organization::update($user_org_id, $data);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Save map provider setting (global option)
        $map_provider = in_array($_POST['map_provider'] ?? '', array('google', 'openstreetmap')) ? $_POST['map_provider'] : 'google';
        update_option('rental_gates_map_provider', $map_provider);

        // Save Google Maps API key if provided
        if (!empty($_POST['google_maps_api_key'])) {
            update_option('rental_gates_google_maps_api_key', sanitize_text_field($_POST['google_maps_api_key']));
        }

        wp_send_json_success(array('message' => __('Settings saved successfully', 'rental-gates')));
    }

    /**
     * Run availability automation
     */
    public function run_availability_automation()
    {
        $engine = new Rental_Gates_Availability_Engine();
        $engine->process_all();
    }

    /**
     * Run notifications
     */
    /**
     * Reset AI credits monthly
     */
    public function reset_ai_credits()
    {
        if (class_exists('Rental_Gates_AI_Usage')) {
            Rental_Gates_AI_Usage::reset_monthly_credits();
        }
    }

    /**
     * Check for expired subscriptions and handle them
     * Runs hourly via cron
     */
    public function check_subscription_expirations()
    {
        global $wpdb;

        // Find subscriptions that should be expired
        // 1. Status is active, cancel_at_period_end is true, current_period_end has passed
        $expired_subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, o.plan_id as org_plan_id 
             FROM {$wpdb->prefix}rg_subscriptions s
             LEFT JOIN {$wpdb->prefix}rg_organizations o ON s.organization_id = o.id
             WHERE s.status = 'active' 
             AND s.cancel_at_period_end = 1 
             AND s.current_period_end < %s",
            current_time('mysql')
        ));

        foreach ($expired_subscriptions as $subscription) {
            // Determine target plan - check if a specific downgrade plan was set
            $target_plan = !empty($subscription->downgrade_to_plan) ? $subscription->downgrade_to_plan : 'free';

            // Update subscription status to expired
            $wpdb->update(
                $wpdb->prefix . 'rg_subscriptions',
                array(
                    'status' => 'expired',
                    'downgrade_to_plan' => null, // Clear the downgrade target
                ),
                array('id' => $subscription->id),
                array('%s', '%s'),
                array('%d')
            );

            // Downgrade organization to target plan
            $wpdb->update(
                $wpdb->prefix . 'rg_organizations',
                array('plan_id' => $target_plan),
                array('id' => $subscription->organization_id),
                array('%s'),
                array('%d')
            );

            // Log the expiration
            error_log(sprintf(
                'Rental Gates - Subscription %d expired for organization %d. Downgraded to %s plan.',
                $subscription->id,
                $subscription->organization_id,
                $target_plan
            ));

            // TODO: Send notification email to organization admin
        }

        // Also check for subscriptions that haven't been synced with Stripe recently
        // This handles edge cases where webhook might have failed
        if (Rental_Gates_Stripe::is_configured()) {
            $active_subscriptions = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}rg_subscriptions 
                 WHERE status = 'active' 
                 AND stripe_subscription_id IS NOT NULL
                 AND updated_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
                 LIMIT 10"
            );

            foreach ($active_subscriptions as $subscription) {
                // Sync with Stripe
                $stripe_sub = Rental_Gates_Stripe::get_subscription($subscription->stripe_subscription_id);

                if (is_wp_error($stripe_sub)) {
                    continue;
                }

                // Update local record with Stripe data
                $update_data = array(
                    'current_period_start' => date('Y-m-d H:i:s', $stripe_sub['current_period_start']),
                    'current_period_end' => date('Y-m-d H:i:s', $stripe_sub['current_period_end']),
                    'cancel_at_period_end' => !empty($stripe_sub['cancel_at_period_end']) ? 1 : 0,
                );

                // Handle Stripe status mapping
                $stripe_status = $stripe_sub['status'] ?? 'unknown';
                if ($stripe_status === 'canceled') {
                    $update_data['status'] = 'cancelled';
                } elseif (in_array($stripe_status, array('active', 'trialing'))) {
                    $update_data['status'] = $stripe_status === 'trialing' ? 'trialing' : 'active';
                } elseif ($stripe_status === 'past_due') {
                    $update_data['status'] = 'past_due';
                }

                $wpdb->update(
                    $wpdb->prefix . 'rg_subscriptions',
                    $update_data,
                    array('id' => $subscription->id)
                );
            }
        }
    }

    /**
     * Handle create tenant AJAX request
     */
    public function handle_create_tenant()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_tenants') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(__('No organization found', 'rental-gates'));
        }

        $data = array(
            'organization_id' => $org_id,
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'preferred_contact' => sanitize_text_field($_POST['preferred_contact'] ?? 'email'),
            'date_of_birth' => sanitize_text_field($_POST['date_of_birth'] ?? ''),
            'emergency_contact_name' => sanitize_text_field($_POST['emergency_contact_name'] ?? ''),
            'emergency_contact_phone' => sanitize_text_field($_POST['emergency_contact_phone'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'prospect'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        $result = Rental_Gates_Tenant::create($data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle update tenant AJAX request
     */
    public function handle_update_tenant()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_tenants') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        if (!$tenant_id) {
            wp_send_json_error(__('Invalid tenant ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $tenant = Rental_Gates_Tenant::get($tenant_id);

        if (!$tenant || $tenant['organization_id'] !== $org_id) {
            wp_send_json_error(__('Tenant not found or access denied', 'rental-gates'));
        }

        $data = array(
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'preferred_contact' => sanitize_text_field($_POST['preferred_contact'] ?? 'email'),
            'date_of_birth' => sanitize_text_field($_POST['date_of_birth'] ?? ''),
            'emergency_contact_name' => sanitize_text_field($_POST['emergency_contact_name'] ?? ''),
            'emergency_contact_phone' => sanitize_text_field($_POST['emergency_contact_phone'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        $result = Rental_Gates_Tenant::update($tenant_id, $data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle delete tenant AJAX request
     */
    public function handle_delete_tenant()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_tenants') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        if (!$tenant_id) {
            wp_send_json_error(__('Invalid tenant ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $tenant = Rental_Gates_Tenant::get($tenant_id);

        if (!$tenant || $tenant['organization_id'] !== $org_id) {
            wp_send_json_error(__('Tenant not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Tenant::delete($tenant_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('deleted' => true));
    }

    /**
     * Handle invite tenant to portal AJAX request
     */
    public function handle_invite_tenant()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_tenants') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        if (!$tenant_id) {
            wp_send_json_error(__('Invalid tenant ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $tenant = Rental_Gates_Tenant::get($tenant_id);

        if (!$tenant || $tenant['organization_id'] !== $org_id) {
            wp_send_json_error(__('Tenant not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Tenant::invite_to_portal($tenant_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle create lease AJAX request
     */
    public function handle_create_lease()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_leases') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(__('No organization found', 'rental-gates'));
        }

        // Parse tenants from JSON
        $tenants = array();
        if (!empty($_POST['tenants_json'])) {
            $tenants_data = json_decode(stripslashes($_POST['tenants_json']), true);
            if (is_array($tenants_data)) {
                foreach ($tenants_data as $t) {
                    $tenants[] = array(
                        'tenant_id' => intval($t['tenant_id']),
                        'role' => sanitize_text_field($t['role'] ?? 'primary'),
                    );
                }
            }
        }

        $data = array(
            'organization_id' => $org_id,
            'unit_id' => intval($_POST['unit_id'] ?? 0),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date' => sanitize_text_field($_POST['end_date'] ?? ''),
            'is_month_to_month' => !empty($_POST['is_month_to_month']),
            'notice_period_days' => intval($_POST['notice_period_days'] ?? 30),
            'rent_amount' => floatval($_POST['rent_amount'] ?? 0),
            'deposit_amount' => floatval($_POST['deposit_amount'] ?? 0),
            'billing_frequency' => sanitize_text_field($_POST['billing_frequency'] ?? 'monthly'),
            'billing_day' => intval($_POST['billing_day'] ?? 1),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'status' => 'draft',
            'tenants' => $tenants,
        );

        $result = Rental_Gates_Lease::create($data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle update lease AJAX request
     */
    public function handle_update_lease()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_leases') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $lease_id = intval($_POST['lease_id'] ?? 0);
        if (!$lease_id) {
            wp_send_json_error(__('Invalid lease ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $lease = Rental_Gates_Lease::get($lease_id);

        if (!$lease || $lease['organization_id'] !== $org_id) {
            wp_send_json_error(__('Lease not found or access denied', 'rental-gates'));
        }

        if ($lease['status'] !== 'draft') {
            wp_send_json_error(__('Only draft leases can be edited', 'rental-gates'));
        }

        $data = array(
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date' => sanitize_text_field($_POST['end_date'] ?? ''),
            'is_month_to_month' => !empty($_POST['is_month_to_month']),
            'notice_period_days' => intval($_POST['notice_period_days'] ?? 30),
            'rent_amount' => floatval($_POST['rent_amount'] ?? 0),
            'deposit_amount' => floatval($_POST['deposit_amount'] ?? 0),
            'billing_frequency' => sanitize_text_field($_POST['billing_frequency'] ?? 'monthly'),
            'billing_day' => intval($_POST['billing_day'] ?? 1),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        $result = Rental_Gates_Lease::update($lease_id, $data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Update tenants if provided
        if (!empty($_POST['tenants_json'])) {
            $tenants_data = json_decode(stripslashes($_POST['tenants_json']), true);
            if (is_array($tenants_data)) {
                // Get current tenants
                $current_tenants = Rental_Gates_Lease::get_tenants($lease_id);
                $current_tenant_ids = array_column($current_tenants, 'tenant_id');
                $new_tenant_ids = array_column($tenants_data, 'tenant_id');

                // Remove tenants not in new list
                foreach ($current_tenant_ids as $tenant_id) {
                    if (!in_array($tenant_id, $new_tenant_ids)) {
                        Rental_Gates_Lease::remove_tenant($lease_id, $tenant_id);
                    }
                }

                // Add new tenants
                foreach ($tenants_data as $t) {
                    $tenant_id = intval($t['tenant_id']);
                    if (!in_array($tenant_id, $current_tenant_ids)) {
                        Rental_Gates_Lease::add_tenant($lease_id, $tenant_id, sanitize_text_field($t['role'] ?? 'primary'));
                    }
                }
            }
        }

        wp_send_json_success($result);
    }

    /**
     * Handle delete lease AJAX request
     */
    public function handle_delete_lease()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_leases') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $lease_id = intval($_POST['lease_id'] ?? 0);
        if (!$lease_id) {
            wp_send_json_error(array('message' => __('Invalid lease ID', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $lease = Rental_Gates_Lease::get($lease_id);

        if (!$lease || $lease['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Lease not found or access denied', 'rental-gates')));
        }

        // Only allow deleting draft leases
        if ($lease['status'] !== 'draft') {
            wp_send_json_error(array('message' => __('Only draft leases can be deleted', 'rental-gates')));
        }

        $result = Rental_Gates_Lease::delete($lease_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('deleted' => true));
    }

    /**
     * Handle activate lease AJAX request
     */
    public function handle_activate_lease()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_leases') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $lease_id = intval($_POST['lease_id'] ?? 0);
        if (!$lease_id) {
            wp_send_json_error(array('message' => __('Invalid lease ID', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $lease = Rental_Gates_Lease::get($lease_id);

        if (!$lease || $lease['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Lease not found or access denied', 'rental-gates')));
        }

        $result = Rental_Gates_Lease::activate($lease_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handle end lease AJAX request
     */
    public function handle_end_lease()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_leases') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $lease_id = intval($_POST['lease_id'] ?? 0);
        if (!$lease_id) {
            wp_send_json_error(__('Invalid lease ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $lease = Rental_Gates_Lease::get($lease_id);

        if (!$lease || $lease['organization_id'] !== $org_id) {
            wp_send_json_error(__('Lease not found or access denied', 'rental-gates'));
        }

        $end_date = !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
        $result = Rental_Gates_Lease::end_lease($lease_id, $end_date);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle terminate lease AJAX request
     */
    public function handle_terminate_lease()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_leases') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $lease_id = intval($_POST['lease_id'] ?? 0);
        if (!$lease_id) {
            wp_send_json_error(array('message' => __('Invalid lease ID', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $lease = Rental_Gates_Lease::get($lease_id);

        if (!$lease || $lease['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Lease not found or access denied', 'rental-gates')));
        }

        $termination_date = sanitize_text_field($_POST['termination_date'] ?? '');
        $termination_reason = sanitize_text_field($_POST['termination_reason'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        $result = Rental_Gates_Lease::terminate($lease_id, $termination_date, $termination_reason, $notes);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handle renew lease AJAX request
     */
    public function handle_renew_lease()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_leases') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $lease_id = intval($_POST['lease_id'] ?? 0);
        if (!$lease_id) {
            wp_send_json_error(array('message' => __('Invalid lease ID', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $lease = Rental_Gates_Lease::get($lease_id);

        if (!$lease || $lease['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Lease not found or access denied', 'rental-gates')));
        }

        $new_end_date = sanitize_text_field($_POST['new_end_date'] ?? '');
        $new_rent_amount = !empty($_POST['new_rent_amount']) ? floatval($_POST['new_rent_amount']) : null;

        if (empty($new_end_date)) {
            wp_send_json_error(array('message' => __('New end date is required', 'rental-gates')));
        }

        $result = Rental_Gates_Lease::renew($lease_id, $new_end_date, $new_rent_amount);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handle add tenant to lease AJAX request
     */
    public function handle_add_lease_tenant()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_leases') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $lease_id = intval($_POST['lease_id'] ?? 0);
        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        $role = sanitize_text_field($_POST['role'] ?? 'primary');

        if (!$lease_id || !$tenant_id) {
            wp_send_json_error(__('Invalid lease or tenant ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $lease = Rental_Gates_Lease::get($lease_id);

        if (!$lease || $lease['organization_id'] !== $org_id) {
            wp_send_json_error(__('Lease not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Lease::add_tenant($lease_id, $tenant_id, $role);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('added' => true));
    }

    /**
     * Handle remove tenant from lease AJAX request
     */
    public function handle_remove_lease_tenant()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_leases') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $lease_id = intval($_POST['lease_id'] ?? 0);
        $tenant_id = intval($_POST['tenant_id'] ?? 0);

        if (!$lease_id || !$tenant_id) {
            wp_send_json_error(__('Invalid lease or tenant ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $lease = Rental_Gates_Lease::get($lease_id);

        if (!$lease || $lease['organization_id'] !== $org_id) {
            wp_send_json_error(__('Lease not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Lease::remove_tenant($lease_id, $tenant_id);

        if (!$result) {
            wp_send_json_error(__('Error removing tenant', 'rental-gates'));
        }

        wp_send_json_success(array('removed' => true));
    }

    /**
     * Handle approve application AJAX request
     */
    public function handle_approve_application()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_applications') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $application_id = intval($_POST['application_id'] ?? 0);
        if (!$application_id) {
            wp_send_json_error(__('Invalid application ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $application = Rental_Gates_Application::get($application_id);

        if (!$application || $application['organization_id'] !== $org_id) {
            wp_send_json_error(__('Application not found or access denied', 'rental-gates'));
        }

        $create_tenant = !empty($_POST['create_tenant']);
        $result = Rental_Gates_Application::approve($application_id, $create_tenant);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle decline application AJAX request
     */
    public function handle_decline_application()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_applications') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $application_id = intval($_POST['application_id'] ?? 0);
        if (!$application_id) {
            wp_send_json_error(__('Invalid application ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $application = Rental_Gates_Application::get($application_id);

        if (!$application || $application['organization_id'] !== $org_id) {
            wp_send_json_error(__('Application not found or access denied', 'rental-gates'));
        }

        $reason = sanitize_textarea_field($_POST['reason'] ?? '');
        $result = Rental_Gates_Application::decline($application_id, $reason);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle screen application AJAX request
     */
    public function handle_screen_application()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_applications') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $application_id = intval($_POST['application_id'] ?? 0);
        if (!$application_id) {
            wp_send_json_error(__('Invalid application ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $application = Rental_Gates_Application::get($application_id);

        if (!$application || $application['organization_id'] !== $org_id) {
            wp_send_json_error(__('Application not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Application::start_screening($application_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle delete application AJAX request
     */
    public function handle_delete_application()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_applications') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $application_id = intval($_POST['application_id'] ?? 0);
        if (!$application_id) {
            wp_send_json_error(__('Invalid application ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $application = Rental_Gates_Application::get($application_id);

        if (!$application || $application['organization_id'] !== $org_id) {
            wp_send_json_error(__('Application not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Application::delete($application_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('deleted' => true));
    }

    /**
     * Handle create payment AJAX request
     */
    public function handle_create_payment()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_payments') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(__('Organization not found', 'rental-gates'));
        }

        $data = array(
            'organization_id' => $org_id,
            'lease_id' => intval($_POST['lease_id'] ?? 0) ?: null,
            'tenant_id' => intval($_POST['tenant_id'] ?? 0) ?: null,
            'type' => sanitize_text_field($_POST['type'] ?? 'rent'),
            'method' => sanitize_text_field($_POST['method'] ?? 'other'),
            'amount' => floatval($_POST['amount'] ?? 0),
            'status' => sanitize_text_field($_POST['status'] ?? 'pending'),
            'due_date' => sanitize_text_field($_POST['due_date'] ?? ''),
            'paid_at' => sanitize_text_field($_POST['paid_at'] ?? ''),
            'period_start' => sanitize_text_field($_POST['period_start'] ?? ''),
            'period_end' => sanitize_text_field($_POST['period_end'] ?? ''),
            'description' => sanitize_text_field($_POST['description'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        // Set amount_paid if status is succeeded
        if ($data['status'] === 'succeeded') {
            $data['amount_paid'] = $data['amount'];
            if (empty($data['paid_at'])) {
                $data['paid_at'] = current_time('mysql');
            }
        }

        $result = Rental_Gates_Payment::create($data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('payment' => $result));
    }

    /**
     * Handle update payment AJAX request
     */
    public function handle_update_payment()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_payments') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $payment_id = intval($_POST['payment_id'] ?? 0);
        if (!$payment_id) {
            wp_send_json_error(__('Invalid payment ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $payment = Rental_Gates_Payment::get($payment_id);

        if (!$payment || $payment['organization_id'] !== $org_id) {
            wp_send_json_error(__('Payment not found or access denied', 'rental-gates'));
        }

        $data = array(
            'type' => sanitize_text_field($_POST['type'] ?? ''),
            'method' => sanitize_text_field($_POST['method'] ?? ''),
            'amount' => floatval($_POST['amount'] ?? 0),
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'due_date' => sanitize_text_field($_POST['due_date'] ?? ''),
            'paid_at' => sanitize_text_field($_POST['paid_at'] ?? ''),
            'period_start' => sanitize_text_field($_POST['period_start'] ?? ''),
            'period_end' => sanitize_text_field($_POST['period_end'] ?? ''),
            'description' => sanitize_text_field($_POST['description'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        $result = Rental_Gates_Payment::update($payment_id, $data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('payment' => $result));
    }

    /**
     * Handle delete payment AJAX request
     */
    public function handle_delete_payment()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_payments') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $payment_id = intval($_POST['payment_id'] ?? 0);
        if (!$payment_id) {
            wp_send_json_error(__('Invalid payment ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $payment = Rental_Gates_Payment::get($payment_id);

        if (!$payment || $payment['organization_id'] !== $org_id) {
            wp_send_json_error(__('Payment not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Payment::delete($payment_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('deleted' => true));
    }

    /**
     * Handle mark payment paid AJAX request
     */
    public function handle_mark_payment_paid()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_payments') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $payment_id = intval($_POST['payment_id'] ?? 0);
        if (!$payment_id) {
            wp_send_json_error(__('Invalid payment ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $payment = Rental_Gates_Payment::get($payment_id);

        if (!$payment || $payment['organization_id'] !== $org_id) {
            wp_send_json_error(__('Payment not found or access denied', 'rental-gates'));
        }

        $method = sanitize_text_field($_POST['method'] ?? '');
        $notes = sanitize_text_field($_POST['notes'] ?? '');

        $result = Rental_Gates_Payment::mark_paid($payment_id, $method ?: null, $notes);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('payment' => $result));
    }

    /**
     * Handle cancel payment AJAX request
     */
    public function handle_cancel_payment()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_payments') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $payment_id = intval($_POST['payment_id'] ?? 0);
        if (!$payment_id) {
            wp_send_json_error(__('Invalid payment ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $payment = Rental_Gates_Payment::get($payment_id);

        if (!$payment || $payment['organization_id'] !== $org_id) {
            wp_send_json_error(__('Payment not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Payment::cancel($payment_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('payment' => $result));
    }

    /**
     * Handle create maintenance AJAX request
     */
    public function handle_create_maintenance()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_maintenance') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(__('Organization not found', 'rental-gates'));
        }

        $data = array(
            'organization_id' => $org_id,
            'building_id' => intval($_POST['building_id'] ?? 0),
            'unit_id' => intval($_POST['unit_id'] ?? 0) ?: null,
            'tenant_id' => intval($_POST['tenant_id'] ?? 0) ?: null,
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? 'general'),
            'priority' => sanitize_text_field($_POST['priority'] ?? 'medium'),
            'permission_to_enter' => !empty($_POST['permission_to_enter']),
            'access_instructions' => sanitize_textarea_field($_POST['access_instructions'] ?? ''),
            'cost_estimate' => floatval($_POST['cost_estimate'] ?? 0) ?: null,
            'scheduled_date' => sanitize_text_field($_POST['scheduled_date'] ?? ''),
            'internal_notes' => sanitize_textarea_field($_POST['internal_notes'] ?? ''),
        );

        $result = Rental_Gates_Maintenance::create($data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('work_order' => $result));
    }

    /**
     * Handle update maintenance AJAX request
     */
    public function handle_update_maintenance()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_maintenance') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $work_order_id = intval($_POST['work_order_id'] ?? 0);
        if (!$work_order_id) {
            wp_send_json_error(__('Invalid work order ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $work_order = Rental_Gates_Maintenance::get($work_order_id);

        if (!$work_order || $work_order['organization_id'] !== $org_id) {
            wp_send_json_error(__('Work order not found or access denied', 'rental-gates'));
        }

        $data = array(
            'building_id' => intval($_POST['building_id'] ?? 0),
            'unit_id' => intval($_POST['unit_id'] ?? 0) ?: null,
            'tenant_id' => intval($_POST['tenant_id'] ?? 0) ?: null,
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? ''),
            'priority' => sanitize_text_field($_POST['priority'] ?? ''),
            'permission_to_enter' => !empty($_POST['permission_to_enter']),
            'access_instructions' => sanitize_textarea_field($_POST['access_instructions'] ?? ''),
            'cost_estimate' => floatval($_POST['cost_estimate'] ?? 0) ?: null,
            'scheduled_date' => sanitize_text_field($_POST['scheduled_date'] ?? ''),
            'internal_notes' => sanitize_textarea_field($_POST['internal_notes'] ?? ''),
        );

        $result = Rental_Gates_Maintenance::update($work_order_id, $data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('work_order' => $result));
    }

    /**
     * Handle delete maintenance AJAX request
     */
    public function handle_delete_maintenance()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_maintenance') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $work_order_id = intval($_POST['work_order_id'] ?? 0);
        if (!$work_order_id) {
            wp_send_json_error(__('Invalid work order ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $work_order = Rental_Gates_Maintenance::get($work_order_id);

        if (!$work_order || $work_order['organization_id'] !== $org_id) {
            wp_send_json_error(__('Work order not found or access denied', 'rental-gates'));
        }

        $result = Rental_Gates_Maintenance::delete($work_order_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('deleted' => true));
    }

    /**
     * Handle update maintenance status AJAX request
     */
    public function handle_update_maintenance_status()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_maintenance') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $work_order_id = intval($_POST['work_order_id'] ?? 0);
        if (!$work_order_id) {
            wp_send_json_error(__('Invalid work order ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $work_order = Rental_Gates_Maintenance::get($work_order_id);

        if (!$work_order || $work_order['organization_id'] !== $org_id) {
            wp_send_json_error(__('Work order not found or access denied', 'rental-gates'));
        }

        $status = sanitize_text_field($_POST['status'] ?? '');
        $result = Rental_Gates_Maintenance::update_status($work_order_id, $status);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('work_order' => $result));
    }

    /**
     * Handle complete maintenance AJAX request
     */
    public function handle_complete_maintenance()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_maintenance') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $work_order_id = intval($_POST['work_order_id'] ?? 0);
        if (!$work_order_id) {
            wp_send_json_error(__('Invalid work order ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $work_order = Rental_Gates_Maintenance::get($work_order_id);

        if (!$work_order || $work_order['organization_id'] !== $org_id) {
            wp_send_json_error(__('Work order not found or access denied', 'rental-gates'));
        }

        $final_cost = floatval($_POST['final_cost'] ?? 0) ?: null;
        $cause = sanitize_text_field($_POST['cause'] ?? '') ?: null;

        $result = Rental_Gates_Maintenance::complete($work_order_id, $final_cost, $cause);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('work_order' => $result));
    }

    /**
     * Handle add maintenance note AJAX request
     */
    public function handle_add_maintenance_note()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_maintenance') && !current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $work_order_id = intval($_POST['work_order_id'] ?? 0);
        if (!$work_order_id) {
            wp_send_json_error(__('Invalid work order ID', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $work_order = Rental_Gates_Maintenance::get($work_order_id);

        if (!$work_order || $work_order['organization_id'] !== $org_id) {
            wp_send_json_error(__('Work order not found or access denied', 'rental-gates'));
        }

        $note = sanitize_textarea_field($_POST['note'] ?? '');
        if (empty($note)) {
            wp_send_json_error(__('Note cannot be empty', 'rental-gates'));
        }

        $is_internal = !empty($_POST['is_internal']);
        $user_id = get_current_user_id();

        $result = Rental_Gates_Maintenance::add_note($work_order_id, $user_id, 'staff', $note, $is_internal);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('note_id' => $result));
    }

    // ==========================================
    // VENDOR AJAX HANDLERS
    // ==========================================

    /**
     * Handle create vendor
     */
    public function handle_create_vendor()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_vendors') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $data = array(
            'organization_id' => $org_id,
            'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
            'contact_name' => sanitize_text_field($_POST['contact_name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'hourly_rate' => !empty($_POST['hourly_rate']) ? floatval($_POST['hourly_rate']) : null,
            'service_categories' => isset($_POST['service_categories']) ? array_map('sanitize_text_field', $_POST['service_categories']) : array(),
            'service_buildings' => isset($_POST['service_buildings']) ? array_map('intval', $_POST['service_buildings']) : array(),
            'status' => sanitize_text_field($_POST['status'] ?? 'active'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        $result = Rental_Gates_Vendor::create($data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('vendor_id' => $result));
    }

    /**
     * Handle update vendor
     */
    public function handle_update_vendor()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_vendors') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $vendor_id = intval($_POST['vendor_id'] ?? 0);
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor ID', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $vendor = Rental_Gates_Vendor::get($vendor_id);

        if (!$vendor || $vendor['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Vendor not found or access denied', 'rental-gates')));
        }

        $data = array(
            'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
            'contact_name' => sanitize_text_field($_POST['contact_name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'hourly_rate' => isset($_POST['hourly_rate']) && $_POST['hourly_rate'] !== '' ? floatval($_POST['hourly_rate']) : null,
            'service_categories' => isset($_POST['service_categories']) ? array_map('sanitize_text_field', $_POST['service_categories']) : array(),
            'service_buildings' => isset($_POST['service_buildings']) ? array_map('intval', $_POST['service_buildings']) : array(),
            'status' => sanitize_text_field($_POST['status'] ?? 'active'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        $result = Rental_Gates_Vendor::update($vendor_id, $data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('vendor_id' => $vendor_id));
    }

    /**
     * Handle delete vendor
     */
    public function handle_delete_vendor()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_vendors') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $vendor_id = intval($_POST['vendor_id'] ?? 0);
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor ID', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $vendor = Rental_Gates_Vendor::get($vendor_id);

        if (!$vendor || $vendor['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Vendor not found or access denied', 'rental-gates')));
        }

        $result = Rental_Gates_Vendor::delete($vendor_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success();
    }

    /**
     * Handle assign vendor to work order
     */
    public function handle_assign_vendor()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_maintenance') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $vendor_id = intval($_POST['vendor_id'] ?? 0);
        $work_order_id = intval($_POST['work_order_id'] ?? 0);

        if (!$vendor_id || !$work_order_id) {
            wp_send_json_error(array('message' => __('Invalid vendor or work order ID', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();

        // Verify work order belongs to org
        $work_order = Rental_Gates_Maintenance::get($work_order_id);
        if (!$work_order || $work_order['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Work order not found', 'rental-gates')));
        }

        // Verify vendor belongs to org
        $vendor = Rental_Gates_Vendor::get($vendor_id);
        if (!$vendor || $vendor['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Vendor not found', 'rental-gates')));
        }

        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $result = Rental_Gates_Vendor::assign_to_work_order($vendor_id, $work_order_id, $notes);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('assignment_id' => $result));
    }

    /**
     * Handle remove vendor from work order
     */
    public function handle_remove_vendor_assignment()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_maintenance') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $vendor_id = intval($_POST['vendor_id'] ?? 0);
        $work_order_id = intval($_POST['work_order_id'] ?? 0);

        if (!$vendor_id || !$work_order_id) {
            wp_send_json_error(array('message' => __('Invalid vendor or work order ID', 'rental-gates')));
        }

        $result = Rental_Gates_Vendor::remove_from_work_order($vendor_id, $work_order_id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to remove vendor assignment', 'rental-gates')));
        }

        wp_send_json_success();
    }

    /**
     * Handle get vendors for category (for work order assignment dropdown)
     */
    public function handle_get_vendors_for_category()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $category = sanitize_text_field($_POST['category'] ?? '');
        $building_id = intval($_POST['building_id'] ?? 0);

        $vendors = Rental_Gates_Vendor::get_by_category($org_id, $category, $building_id ?: null);

        wp_send_json_success(array('vendors' => $vendors));
    }

    // ==========================================
    // TENANT PORTAL AJAX HANDLERS
    // ==========================================

    /**
     * Handle tenant creating maintenance request
     */
    public function handle_tenant_create_maintenance()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        $org_id = intval($_POST['organization_id'] ?? 0);

        if (!$tenant_id || !$org_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        // Verify tenant belongs to current user
        $tenant = Rental_Gates_Tenant::get($tenant_id);
        if (!$tenant || $tenant['user_id'] != get_current_user_id()) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        $data = array(
            'organization_id' => $org_id,
            'tenant_id' => $tenant_id,
            'building_id' => intval($_POST['building_id'] ?? 0) ?: null,
            'unit_id' => intval($_POST['unit_id'] ?? 0) ?: null,
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? 'other'),
            'priority' => sanitize_text_field($_POST['priority'] ?? 'medium'),
            'status' => 'open',
            'source' => 'tenant_portal',
            'permission_to_enter' => !empty($_POST['permission_to_enter']),
        );

        // Handle photo uploads
        if (!empty($_FILES['photos']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $uploaded_photos = array();
            $files = $_FILES['photos'];

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = array(
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i],
                    );

                    $_FILES['upload_file'] = $file;
                    $attachment_id = media_handle_upload('upload_file', 0);

                    if (!is_wp_error($attachment_id)) {
                        $uploaded_photos[] = wp_get_attachment_url($attachment_id);
                    }
                }
            }

            if (!empty($uploaded_photos)) {
                $data['photos'] = $uploaded_photos;
            }
        }

        $result = Rental_Gates_Maintenance::create($data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('work_order_id' => $result));
    }

    /**
     * Handle tenant deleting maintenance request
     */
    public function handle_tenant_delete_maintenance()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $request_id = intval($_POST['request_id'] ?? 0);
        $tenant_id = intval($_POST['tenant_id'] ?? 0);

        if (!$request_id || !$tenant_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        // Verify tenant belongs to current user
        $tenant = Rental_Gates_Tenant::get($tenant_id);
        if (!$tenant || $tenant['user_id'] != get_current_user_id()) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        // Get work order
        $work_order = Rental_Gates_Maintenance::get($request_id);
        if (!$work_order || $work_order['tenant_id'] != $tenant_id) {
            wp_send_json_error(array('message' => __('Request not found', 'rental-gates')));
        }

        // Only allow deleting open/assigned requests
        if (!in_array($work_order['status'], array('open', 'assigned'))) {
            wp_send_json_error(array('message' => __('Cannot delete requests that are already in progress', 'rental-gates')));
        }

        $result = Rental_Gates_Maintenance::delete($request_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Request deleted successfully', 'rental-gates')));
    }

    /**
     * Handle tenant submitting feedback on completed maintenance
     */
    public function handle_tenant_maintenance_feedback()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $request_id = intval($_POST['request_id'] ?? 0);
        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        $feedback = sanitize_text_field($_POST['feedback'] ?? '');

        if (!$request_id || !$tenant_id || !$feedback) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        // Verify tenant belongs to current user
        $tenant = Rental_Gates_Tenant::get($tenant_id);
        if (!$tenant || $tenant['user_id'] != get_current_user_id()) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        // Get work order
        $work_order = Rental_Gates_Maintenance::get($request_id);
        if (!$work_order || $work_order['tenant_id'] != $tenant_id) {
            wp_send_json_error(array('message' => __('Request not found', 'rental-gates')));
        }

        // Update meta_data with feedback
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $meta = json_decode($work_order['meta_data'] ?? '{}', true);
        $meta['tenant_feedback'] = $feedback;
        $meta['tenant_feedback_at'] = current_time('mysql');

        $wpdb->update(
            $tables['work_orders'],
            array('meta_data' => wp_json_encode($meta)),
            array('id' => $request_id),
            array('%s'),
            array('%d')
        );

        // If not satisfied, reopen the request
        if ($feedback === 'not_satisfied') {
            $wpdb->update(
                $tables['work_orders'],
                array('status' => 'open'),
                array('id' => $request_id),
                array('%s'),
                array('%d')
            );

            // Add a system note
            Rental_Gates_Maintenance::add_note(
                $request_id,
                $tenant['user_id'],
                'tenant',
                __('Tenant reported issue is not fully resolved. Request reopened.', 'rental-gates'),
                false
            );
        } else {
            // Add a positive note
            Rental_Gates_Maintenance::add_note(
                $request_id,
                $tenant['user_id'],
                'tenant',
                __('Tenant confirmed issue has been resolved.', 'rental-gates'),
                false
            );
        }

        wp_send_json_success(array('message' => __('Feedback submitted', 'rental-gates')));
    }

    /**
     * Handle tenant adding a note to maintenance request
     */
    public function handle_tenant_add_note()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $request_id = intval($_POST['request_id'] ?? 0);
        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        $note = sanitize_textarea_field($_POST['note'] ?? '');

        if (!$request_id || !$tenant_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        // Verify tenant belongs to current user
        $tenant = Rental_Gates_Tenant::get($tenant_id);
        if (!$tenant || $tenant['user_id'] != get_current_user_id()) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        // Get work order
        $work_order = Rental_Gates_Maintenance::get($request_id);
        if (!$work_order || $work_order['tenant_id'] != $tenant_id) {
            wp_send_json_error(array('message' => __('Request not found', 'rental-gates')));
        }

        // Handle attachments
        $attachments = array();
        if (!empty($_FILES['attachments']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $files = $_FILES['attachments'];

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = array(
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i],
                    );

                    $_FILES['upload_file'] = $file;
                    $attachment_id = media_handle_upload('upload_file', 0);

                    if (!is_wp_error($attachment_id)) {
                        $attachments[] = wp_get_attachment_url($attachment_id);
                    }
                }
            }
        }

        // Add the note
        if (empty($note) && empty($attachments)) {
            wp_send_json_error(array('message' => __('Please provide a comment or attachment', 'rental-gates')));
        }

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $wpdb->insert(
            $tables['work_order_notes'],
            array(
                'work_order_id' => $request_id,
                'user_id' => $tenant['user_id'],
                'user_type' => 'tenant',
                'note' => $note ?: __('Added photos', 'rental-gates'),
                'is_internal' => 0,
                'attachments' => !empty($attachments) ? wp_json_encode($attachments) : null,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s', '%d', '%s', '%s')
        );

        wp_send_json_success(array('message' => __('Comment added', 'rental-gates')));
    }

    /**
     * Handle tenant updating profile
     */
    public function handle_tenant_update_profile()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        if (!$tenant_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        // Verify tenant belongs to current user
        $tenant = Rental_Gates_Tenant::get($tenant_id);
        if (!$tenant || $tenant['user_id'] != get_current_user_id()) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        // Build meta data
        $meta_data = $tenant['meta_data'] ?? array();

        // Emergency contact
        $meta_data['emergency_contact'] = array(
            'name' => sanitize_text_field($_POST['emergency_name'] ?? ''),
            'relationship' => sanitize_text_field($_POST['emergency_relationship'] ?? ''),
            'phone' => sanitize_text_field($_POST['emergency_phone'] ?? ''),
            'email' => sanitize_email($_POST['emergency_email'] ?? ''),
        );

        // Vehicle
        $meta_data['vehicle'] = array(
            'make' => sanitize_text_field($_POST['vehicle_make'] ?? ''),
            'model' => sanitize_text_field($_POST['vehicle_model'] ?? ''),
            'color' => sanitize_text_field($_POST['vehicle_color'] ?? ''),
            'plate' => sanitize_text_field($_POST['vehicle_plate'] ?? ''),
        );

        // Communication preferences
        $meta_data['communication_prefs'] = array(
            'email_reminders' => !empty($_POST['pref_email_reminders']),
            'email_maintenance' => !empty($_POST['pref_email_maintenance']),
            'email_announcements' => !empty($_POST['pref_email_announcements']),
        );

        $update_data = array(
            'phone' => sanitize_text_field($_POST['phone'] ?? $tenant['phone']),
            'meta_data' => $meta_data,
        );

        $result = Rental_Gates_Tenant::update($tenant_id, $update_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success();
    }

    /**
     * Handle creating a new lead
     */
    public function handle_create_lead()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $data = array(
            'organization_id' => $org_id,
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'source' => sanitize_text_field($_POST['source'] ?? 'manual'),
            'assigned_to' => intval($_POST['assigned_to'] ?? 0) ?: null,
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'follow_up_date' => !empty($_POST['follow_up_date']) ? sanitize_text_field($_POST['follow_up_date']) : null,
            'building_id' => intval($_POST['building_id'] ?? 0) ?: null,
        );

        $result = Rental_Gates_Lead::create($data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('lead_id' => $result));
    }

    /**
     * Handle getting a single lead
     */
    public function handle_get_lead()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $lead_id = intval($_POST['lead_id'] ?? 0);
        if (!$lead_id) {
            wp_send_json_error(array('message' => __('Invalid lead ID', 'rental-gates')));
        }

        $lead = Rental_Gates_Lead::get_with_details($lead_id);

        if (!$lead || $lead['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Lead not found', 'rental-gates')));
        }

        wp_send_json_success(array('lead' => $lead));
    }

    /**
     * Handle updating a lead
     */
    public function handle_update_lead()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $lead_id = intval($_POST['lead_id'] ?? 0);
        if (!$lead_id) {
            wp_send_json_error(array('message' => __('Invalid lead ID', 'rental-gates')));
        }

        $lead = Rental_Gates_Lead::get($lead_id);
        if (!$lead || $lead['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Lead not found', 'rental-gates')));
        }

        $data = array();

        if (isset($_POST['name']))
            $data['name'] = sanitize_text_field($_POST['name']);
        if (isset($_POST['email']))
            $data['email'] = sanitize_email($_POST['email']);
        if (isset($_POST['phone']))
            $data['phone'] = sanitize_text_field($_POST['phone']);
        if (isset($_POST['stage']))
            $data['stage'] = sanitize_text_field($_POST['stage']);
        if (isset($_POST['assigned_to']))
            $data['assigned_to'] = intval($_POST['assigned_to']) ?: null;
        if (isset($_POST['follow_up_date']))
            $data['follow_up_date'] = $_POST['follow_up_date'] ?: null;
        if (isset($_POST['notes']))
            $data['notes'] = sanitize_textarea_field($_POST['notes']);
        if (isset($_POST['lost_reason']))
            $data['lost_reason'] = sanitize_textarea_field($_POST['lost_reason']);

        $result = Rental_Gates_Lead::update($lead_id, $data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('lead' => $result));
    }

    /**
     * Handle deleting a lead
     */
    public function handle_delete_lead()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $lead_id = intval($_POST['lead_id'] ?? 0);
        if (!$lead_id) {
            wp_send_json_error(array('message' => __('Invalid lead ID', 'rental-gates')));
        }

        $lead = Rental_Gates_Lead::get($lead_id);
        if (!$lead || $lead['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Lead not found', 'rental-gates')));
        }

        $result = Rental_Gates_Lead::delete($lead_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success();
    }

    /**
     * Handle adding a note to a lead
     */
    public function handle_add_lead_note()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $lead_id = intval($_POST['lead_id'] ?? 0);
        $note = sanitize_textarea_field($_POST['note'] ?? '');

        if (!$lead_id || !$note) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        $lead = Rental_Gates_Lead::get($lead_id);
        if (!$lead || $lead['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Lead not found', 'rental-gates')));
        }

        $result = Rental_Gates_Lead::add_note($lead_id, $note);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('lead' => $result));
    }

    /**
     * Handle adding interest to a lead
     */
    public function handle_add_lead_interest()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $lead_id = intval($_POST['lead_id'] ?? 0);
        $building_id = intval($_POST['building_id'] ?? 0);
        $unit_id = intval($_POST['unit_id'] ?? 0);

        if (!$lead_id || (!$building_id && !$unit_id)) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        $lead = Rental_Gates_Lead::get($lead_id);
        if (!$lead || $lead['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Lead not found', 'rental-gates')));
        }

        $result = Rental_Gates_Lead::add_interest($lead_id, $building_id ?: null, $unit_id ?: null);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to add interest', 'rental-gates')));
        }

        wp_send_json_success(array('interest_id' => $result));
    }

    /**
     * Handle public inquiry (lead capture from public pages)
     * This is accessible without login for public visitors
     */
    public function handle_public_inquiry()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_public')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        // Get and validate required fields
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');

        $org_id = intval($_POST['organization_id'] ?? 0);
        $building_id = intval($_POST['building_id'] ?? 0);
        $unit_id = intval($_POST['unit_id'] ?? 0);
        $source = sanitize_text_field($_POST['source'] ?? 'profile');
        $source_id = intval($_POST['source_id'] ?? 0);

        // Validate required fields
        if (empty($first_name) || empty($email) || empty($org_id)) {
            wp_send_json_error(array('message' => __('Please fill in all required fields', 'rental-gates')));
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address', 'rental-gates')));
        }

        // Verify organization exists
        $organization = Rental_Gates_Organization::get($org_id);
        if (!$organization) {
            wp_send_json_error(array('message' => __('Invalid organization', 'rental-gates')));
        }

        // Create the lead
        $lead_data = array(
            'organization_id' => $org_id,
            'name' => trim($first_name . ' ' . $last_name),
            'email' => $email,
            'phone' => $phone,
            'source' => in_array($source, array('qr_building', 'qr_unit', 'map', 'profile')) ? $source : 'profile',
            'source_id' => $source_id ?: null,
            'notes' => $message,
            'meta_data' => array(
                'inquiry' => true,
                'inquiry_time' => current_time('mysql'),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            ),
        );

        // Add interest if building or unit specified
        if ($building_id) {
            $lead_data['building_id'] = $building_id;
        }
        if ($unit_id) {
            $lead_data['unit_id'] = $unit_id;
        }

        $lead_id = Rental_Gates_Lead::create($lead_data);

        if (is_wp_error($lead_id)) {
            // If it's a duplicate error, that's actually OK - lead already exists
            if ($lead_id->get_error_code() === 'duplicate_lead') {
                // Get existing lead and add interest
                wp_send_json_success(array(
                    'message' => __('Thank you! We\'ll be in touch soon.', 'rental-gates'),
                    'existing' => true,
                ));
            }
            wp_send_json_error(array('message' => $lead_id->get_error_message()));
        }

        // Send notification email to organization
        $this->send_inquiry_notification($org_id, $lead_data, $building_id, $unit_id);

        wp_send_json_success(array(
            'message' => __('Thank you for your inquiry! We\'ll be in touch soon.', 'rental-gates'),
            'lead_id' => $lead_id,
        ));
    }

    /**
     * Send email notification for new inquiry
     */
    private function send_inquiry_notification($org_id, $lead_data, $building_id = 0, $unit_id = 0)
    {
        $organization = Rental_Gates_Organization::get($org_id);
        if (!$organization || empty($organization['contact_email'])) {
            return;
        }

        $property_name = '';
        if ($unit_id) {
            $unit = Rental_Gates_Unit::get($unit_id);
            $building = $unit ? Rental_Gates_Building::get($unit['building_id']) : null;
            $property_name = $unit ? ($unit['name'] . ' at ' . ($building['name'] ?? '')) : '';
        } elseif ($building_id) {
            $building = Rental_Gates_Building::get($building_id);
            $property_name = $building ? $building['name'] : '';
        }

        $subject = sprintf(__('New Inquiry: %s', 'rental-gates'), $lead_data['name']);

        $message = sprintf(
            __("You have a new inquiry from your property listing.\n\n" .
                "Name: %s\n" .
                "Email: %s\n" .
                "Phone: %s\n" .
                "Property: %s\n" .
                "Source: %s\n\n" .
                "Message:\n%s\n\n" .
                "View this lead in your dashboard:\n%s", 'rental-gates'),
            $lead_data['name'],
            $lead_data['email'],
            $lead_data['phone'] ?: 'Not provided',
            $property_name ?: 'General inquiry',
            ucfirst(str_replace('_', ' ', $lead_data['source'])),
            $lead_data['notes'] ?: 'No message provided',
            home_url('/rental-gates/dashboard/leads')
        );

        wp_mail($organization['contact_email'], $subject, $message);
    }

    /**
     * Handle vendor updating assignment status
     */
    public function handle_vendor_update_assignment()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['status'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $cost = floatval($_POST['cost'] ?? 0);
        $work_order_id = intval($_POST['work_order_id'] ?? 0);

        if (!$assignment_id || !in_array($new_status, array('accepted', 'declined', 'completed'))) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        // Verify vendor owns this assignment
        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT wov.*, v.user_id FROM {$tables['work_order_vendors']} wov
             JOIN {$tables['vendors']} v ON wov.vendor_id = v.id
             WHERE wov.id = %d",
            $assignment_id
        ), ARRAY_A);

        if (!$assignment || $assignment['user_id'] != get_current_user_id()) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        // Update assignment
        $update_data = array('status' => $new_status);
        if ($notes) {
            $update_data['notes'] = $notes;
        }
        if ($cost > 0) {
            $update_data['actual_cost'] = $cost;
        }

        $result = $wpdb->update(
            $tables['work_order_vendors'],
            $update_data,
            array('id' => $assignment_id)
        );

        // If marking complete, also update work order status
        if ($new_status === 'completed' && $work_order_id) {
            $wpdb->update(
                $tables['work_orders'],
                array(
                    'status' => 'completed',
                    'completed_at' => current_time('mysql'),
                    'actual_cost' => $cost > 0 ? $cost : null,
                ),
                array('id' => $work_order_id)
            );
        }

        // If accepting, update work order to in_progress
        if ($new_status === 'accepted' && $work_order_id) {
            $wpdb->update(
                $tables['work_orders'],
                array('status' => 'in_progress'),
                array('id' => $assignment['work_order_id'])
            );
        }

        wp_send_json_success();
    }

    /**
     * Handle vendor adding note to work order
     */
    public function handle_vendor_add_note()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $work_order_id = intval($_POST['work_order_id'] ?? 0);
        $content = sanitize_textarea_field($_POST['content'] ?? '');

        if (!$work_order_id || !$content) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        // Verify vendor has access to this work order
        $has_access = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['work_order_vendors']} wov
             JOIN {$tables['vendors']} v ON wov.vendor_id = v.id
             WHERE wov.work_order_id = %d AND v.user_id = %d",
            $work_order_id,
            get_current_user_id()
        ));

        if (!$has_access) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        // Add note
        $result = $wpdb->insert(
            $tables['work_order_notes'],
            array(
                'work_order_id' => $work_order_id,
                'user_id' => get_current_user_id(),
                'content' => $content,
                'is_internal' => 0,
                'created_at' => current_time('mysql'),
            )
        );

        if ($result === false) {
            wp_send_json_error(array('message' => __('Error adding note', 'rental-gates')));
        }

        wp_send_json_success();
    }

    /**
     * Handle inviting vendor to portal
     */
    public function handle_invite_vendor()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_vendors')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $vendor_id = intval($_POST['vendor_id'] ?? 0);
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor ID', 'rental-gates')));
        }

        // Verify vendor belongs to user's organization
        $org_id = Rental_Gates_Roles::get_organization_id();
        $vendor = Rental_Gates_Vendor::get($vendor_id);

        if (!$vendor || $vendor['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Vendor not found', 'rental-gates')));
        }

        // Send invitation
        $result = Rental_Gates_Vendor::invite_to_portal($vendor_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('vendor' => $result));
    }

    /**
     * Handle document upload
     */
    public function handle_upload_document()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_buildings') && !current_user_can('rg_manage_tenants') && !Rental_Gates_Roles::is_owner_or_manager()) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        if (empty($_FILES['document'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $entity_type = sanitize_key($_POST['entity_type'] ?? '');
        $entity_id = intval($_POST['entity_id'] ?? 0);

        if (!$entity_type || !$entity_id) {
            wp_send_json_error(array('message' => __('Please select an entity to associate the document with', 'rental-gates')));
        }

        // Verify entity belongs to organization
        $valid = false;
        switch ($entity_type) {
            case 'building':
                $entity = Rental_Gates_Building::get($entity_id);
                $valid = $entity && $entity['organization_id'] === $org_id;
                break;
            case 'unit':
                $entity = Rental_Gates_Unit::get($entity_id);
                if ($entity) {
                    $building = Rental_Gates_Building::get($entity['building_id']);
                    $valid = $building && $building['organization_id'] === $org_id;
                }
                break;
            case 'tenant':
                $entity = Rental_Gates_Tenant::get($entity_id);
                $valid = $entity && $entity['organization_id'] === $org_id;
                break;
            case 'lease':
                $entity = Rental_Gates_Lease::get($entity_id);
                $valid = $entity && $entity['organization_id'] === $org_id;
                break;
            case 'vendor':
                $entity = Rental_Gates_Vendor::get($entity_id);
                $valid = $entity && $entity['organization_id'] === $org_id;
                break;
            case 'application':
                $entity = Rental_Gates_Application::get($entity_id);
                $valid = $entity && $entity['organization_id'] === $org_id;
                break;
            case 'work_order':
                $entity = Rental_Gates_Maintenance::get($entity_id);
                $valid = $entity && $entity['organization_id'] === $org_id;
                break;
        }

        if (!$valid) {
            wp_send_json_error(array('message' => __('Invalid entity', 'rental-gates')));
        }

        $doc_data = array(
            'organization_id' => $org_id,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'document_type' => sanitize_key($_POST['document_type'] ?? 'other'),
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'is_private' => 1,
        );

        $result = Rental_Gates_Document::upload($_FILES['document'], $doc_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('document' => $result));
    }

    /**
     * Handle document deletion
     */
    public function handle_delete_document()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_buildings') && !current_user_can('rg_manage_tenants') && !Rental_Gates_Roles::is_owner_or_manager()) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $document_id = intval($_POST['document_id'] ?? 0);
        if (!$document_id) {
            wp_send_json_error(array('message' => __('Invalid document ID', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $document = Rental_Gates_Document::get($document_id);

        if (!$document || $document['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Document not found', 'rental-gates')));
        }

        $result = Rental_Gates_Document::delete($document_id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to delete document', 'rental-gates')));
        }

        wp_send_json_success();
    }

    /**
     * Customize password reset email to use our custom page
     */
    public function customize_password_reset_email($message, $key, $user_login, $user_data)
    {
        // Build our custom reset URL
        $reset_url = home_url('/rental-gates/reset-password/') . '?' . http_build_query(array(
            'key' => $key,
            'login' => rawurlencode($user_login),
        ));

        // Create custom message
        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        $message = __('Hello,', 'rental-gates') . "\r\n\r\n";
        $message .= sprintf(__('Someone has requested a password reset for your account on %s.', 'rental-gates'), $site_name) . "\r\n\r\n";
        $message .= __('If you did not request this, you can safely ignore this email.', 'rental-gates') . "\r\n\r\n";
        $message .= __('To reset your password, click the link below:', 'rental-gates') . "\r\n\r\n";
        $message .= $reset_url . "\r\n\r\n";
        $message .= __('This link will expire in 24 hours.', 'rental-gates') . "\r\n\r\n";
        $message .= sprintf(__('Thanks,', 'rental-gates')) . "\r\n";
        $message .= $site_name . "\r\n";

        return $message;
    }

    /**
     * Handle password reset form submission
     */
    private function handle_reset_password_submit()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['reset_nonce'] ?? '', 'rental_gates_reset_password')) {
            wp_safe_redirect(home_url('/rental-gates/reset-password/?error=security'));
            exit;
        }

        $key = sanitize_text_field($_POST['key'] ?? '');
        $login = sanitize_text_field($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // Validate
        if (empty($key) || empty($login) || empty($password)) {
            wp_safe_redirect(home_url('/rental-gates/reset-password/?error=missing&key=' . urlencode($key) . '&login=' . urlencode($login)));
            exit;
        }

        if (strlen($password) < 8) {
            wp_safe_redirect(home_url('/rental-gates/reset-password/?error=short&key=' . urlencode($key) . '&login=' . urlencode($login)));
            exit;
        }

        if ($password !== $confirm) {
            wp_safe_redirect(home_url('/rental-gates/reset-password/?error=mismatch&key=' . urlencode($key) . '&login=' . urlencode($login)));
            exit;
        }

        // Verify reset key
        $user = check_password_reset_key($key, $login);

        if (is_wp_error($user)) {
            wp_safe_redirect(home_url('/rental-gates/reset-password/?error=invalid'));
            exit;
        }

        // Reset password
        reset_password($user, $password);

        // Log the password reset
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();
        $wpdb->insert(
            $tables['activity_log'],
            array(
                'user_id' => $user->ID,
                'action' => 'password_reset',
                'entity_type' => 'user',
                'entity_id' => $user->ID,
                'ip_address' => Rental_Gates_Security::get_client_ip(),
                'created_at' => current_time('mysql'),
            )
        );

        // Redirect to success page
        wp_safe_redirect(home_url('/rental-gates/reset-password/?success=1'));
        exit;
    }

    /**
     * Handle forgot password AJAX request
     */
    public function handle_forgot_password()
    {
        $email = sanitize_email($_POST['email'] ?? '');

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'rental-gates')));
        }

        // Get user by email
        $user = get_user_by('email', $email);

        // Always return success message to prevent email enumeration
        // But only send email if user exists
        if ($user) {
            // Generate reset key
            $key = get_password_reset_key($user);

            if (!is_wp_error($key)) {
                // Build reset URL
                $reset_url = add_query_arg(array(
                    'key' => $key,
                    'login' => rawurlencode($user->user_login),
                ), home_url('/rental-gates/reset-password/'));

                // Send password reset email
                Rental_Gates_Email::send($email, 'password_reset', array(
                    'user_name' => $user->display_name ?: $user->user_login,
                    'reset_url' => $reset_url,
                    'preheader' => __('Reset your password to regain access to your account.', 'rental-gates'),
                ));

                // Log the request
                global $wpdb;
                $tables = Rental_Gates_Database::get_table_names();
                $wpdb->insert(
                    $tables['activity_log'],
                    array(
                        'user_id' => $user->ID,
                        'action' => 'password_reset_requested',
                        'entity_type' => 'user',
                        'entity_id' => $user->ID,
                        'ip_address' => Rental_Gates_Security::get_client_ip(),
                        'created_at' => current_time('mysql'),
                    )
                );
            }
        }

        // Always return success to prevent email enumeration
        wp_send_json_success(array(
            'message' => __('If an account exists with that email, you will receive a password reset link shortly.', 'rental-gates')
        ));
    }

    /**
     * Handle save staff (invite or update)
     */
    public function handle_save_staff()
    {
        if (!wp_verify_nonce($_POST['staff_nonce'] ?? '', 'rental_gates_staff_form')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_staff')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();
        $org_id = Rental_Gates_Roles::get_organization_id();

        // Validate org_id
        if (!$org_id) {
            error_log('Rental Gates: No org_id found for user ' . get_current_user_id() . ' when saving staff');
            wp_send_json_error(__('Organization not found. Please contact support.', 'rental-gates'));
        }

        // Get permissions
        $permissions = array();
        if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
            foreach ($_POST['permissions'] as $module => $level) {
                $module = sanitize_key($module);
                $level = sanitize_key($level);
                if (in_array($level, array('none', 'view', 'edit', 'full'))) {
                    $permissions[$module] = $level;
                }
            }
        }
        $permissions_json = wp_json_encode($permissions);

        // Check if editing existing staff
        $staff_id = intval($_POST['staff_id'] ?? 0);

        if ($staff_id) {
            // Update existing staff
            $staff = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$tables['organization_members']} WHERE id = %d AND organization_id = %d AND role = 'staff'",
                $staff_id,
                $org_id
            ), ARRAY_A);

            if (!$staff) {
                wp_send_json_error(__('Staff member not found', 'rental-gates'));
            }

            $status = sanitize_text_field($_POST['status'] ?? 'active');
            if (!in_array($status, array('active', 'inactive', 'pending'))) {
                $status = 'active';
            }

            $wpdb->update(
                $tables['organization_members'],
                array(
                    'permissions' => $permissions_json,
                    'status' => $status,
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $staff_id),
                array('%s', '%s', '%s'),
                array('%d')
            );

            wp_send_json_success(array('message' => __('Staff updated successfully', 'rental-gates')));
        } else {
            // Invite new staff
            $email = sanitize_email($_POST['email'] ?? '');
            $display_name = sanitize_text_field($_POST['display_name'] ?? '');
            $phone = sanitize_text_field($_POST['phone'] ?? '');
            $job_title = sanitize_text_field($_POST['job_title'] ?? '');

            if (empty($email) || !is_email($email)) {
                wp_send_json_error(__('Please enter a valid email address', 'rental-gates'));
            }

            if (empty($display_name)) {
                wp_send_json_error(__('Please enter the staff member\'s name', 'rental-gates'));
            }

            // Check if email already exists in org
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT om.id FROM {$tables['organization_members']} om
                 JOIN {$wpdb->users} u ON om.user_id = u.ID
                 WHERE om.organization_id = %d AND u.user_email = %s",
                $org_id,
                $email
            ));

            if ($existing) {
                wp_send_json_error(__('This email is already associated with a member of your organization', 'rental-gates'));
            }

            // Check if user exists or create new
            $user = get_user_by('email', $email);

            if (!$user) {
                // Create new user
                $username = sanitize_user(current(explode('@', $email)), true);
                $counter = 1;
                $original_username = $username;
                while (username_exists($username)) {
                    $username = $original_username . $counter;
                    $counter++;
                }

                $password = wp_generate_password(12, true);
                $user_id = wp_create_user($username, $password, $email);

                if (is_wp_error($user_id)) {
                    wp_send_json_error($user_id->get_error_message());
                }

                // Update user meta
                wp_update_user(array(
                    'ID' => $user_id,
                    'display_name' => $display_name,
                    'first_name' => explode(' ', $display_name)[0],
                    'last_name' => implode(' ', array_slice(explode(' ', $display_name), 1)),
                ));

                if ($phone) {
                    update_user_meta($user_id, 'phone', $phone);
                }
                if ($job_title) {
                    update_user_meta($user_id, 'job_title', $job_title);
                }

                // Assign staff role
                $user = new WP_User($user_id);
                $user->set_role('rental_gates_staff');
            } else {
                $user_id = $user->ID;
                // Add staff role if not already a staff
                if (!in_array('rental_gates_staff', $user->roles)) {
                    $user->add_role('rental_gates_staff');
                }
            }

            // Add to organization_members
            $insert_result = $wpdb->insert(
                $tables['organization_members'],
                array(
                    'organization_id' => $org_id,
                    'user_id' => $user_id,
                    'role' => 'staff',
                    'permissions' => $permissions_json,
                    'status' => 'pending',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
            );

            if ($insert_result === false) {
                error_log('Rental Gates: Failed to insert staff member. DB Error: ' . $wpdb->last_error);
                wp_send_json_error(__('Failed to add staff member. Please try again.', 'rental-gates'));
            }

            // Log success
            error_log('Rental Gates: Staff member added successfully. ID: ' . $wpdb->insert_id . ', Org: ' . $org_id . ', User: ' . $user_id);

            // Send invitation email
            $org = Rental_Gates_Organization::get($org_id);
            $org_name = $org['name'] ?? __('Your Property Management Company', 'rental-gates');

            $subject = sprintf(__('You\'ve been invited to join %s', 'rental-gates'), $org_name);

            $login_url = home_url('/rental-gates/login');
            $message = sprintf(
                __("Hello %s,\n\nYou've been invited to join %s as a staff member.\n\nYou can access the staff portal by logging in at:\n%s\n\nIf this is your first time, use your email (%s) and click 'Forgot Password' to set up your account.\n\nBest regards,\n%s", 'rental-gates'),
                $display_name,
                $org_name,
                $login_url,
                $email,
                $org_name
            );

            wp_mail($email, $subject, $message);

            wp_send_json_success(array('message' => __('Invitation sent successfully', 'rental-gates')));
        }
    }

    /**
     * Handle remove staff
     */
    public function handle_remove_staff()
    {
        if (!wp_verify_nonce($_POST['staff_nonce'] ?? '', 'rental_gates_staff_form')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_staff')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();
        $org_id = Rental_Gates_Roles::get_organization_id();

        $staff_id = intval($_POST['staff_id'] ?? 0);

        if (!$staff_id) {
            wp_send_json_error(__('Invalid staff ID', 'rental-gates'));
        }

        // Verify staff belongs to this org
        $staff = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['organization_members']} WHERE id = %d AND organization_id = %d AND role = 'staff'",
            $staff_id,
            $org_id
        ), ARRAY_A);

        if (!$staff) {
            wp_send_json_error(__('Staff member not found', 'rental-gates'));
        }

        // Remove from organization
        $wpdb->delete(
            $tables['organization_members'],
            array('id' => $staff_id),
            array('%d')
        );

        // Optionally remove staff role from user if not in any other org
        $other_orgs = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['organization_members']} WHERE user_id = %d AND role = 'staff'",
            $staff['user_id']
        ));

        if ($other_orgs == 0) {
            $user = new WP_User($staff['user_id']);
            $user->remove_role('rental_gates_staff');
        }

        wp_send_json_success(array('message' => __('Staff member removed successfully', 'rental-gates')));
    }

    /**
     * Handle send message
     */
    public function handle_send_message()
    {
        // Accept multiple nonce formats for flexibility
        $nonce_valid = wp_verify_nonce($_POST['message_nonce'] ?? '', 'rental_gates_message') ||
            wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce');

        if (!$nonce_valid) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Please log in', 'rental-gates'));
        }

        $thread_id = intval($_POST['thread_id'] ?? 0);
        $message_text = sanitize_textarea_field($_POST['message'] ?? $_POST['content'] ?? '');

        if (!$thread_id || empty($message_text)) {
            wp_send_json_error(__('Missing required fields', 'rental-gates'));
        }

        $current_user_id = get_current_user_id();
        $sender_id = $current_user_id;
        $sender_type = 'staff'; // Default for owner/PM

        // Detect user type from roles
        $user = wp_get_current_user();
        if (in_array('rental_gates_tenant', $user->roles)) {
            $sender_type = 'tenant';
            // Get tenant ID (messages use tenant table ID, not WP user ID)
            global $wpdb;
            $tables = Rental_Gates_Database::get_table_names();
            $tenant_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tables['tenants']} WHERE user_id = %d AND status = 'active' LIMIT 1",
                $current_user_id
            ));
            if ($tenant_id) {
                $sender_id = intval($tenant_id);
            }
        } elseif (in_array('rental_gates_vendor', $user->roles)) {
            $sender_type = 'vendor';
        }

        // Override with explicitly passed sender_type (for tenant portal)
        if (!empty($_POST['sender_type'])) {
            $sender_type = sanitize_text_field($_POST['sender_type']);
            if ($sender_type === 'tenant') {
                global $wpdb;
                $tables = Rental_Gates_Database::get_table_names();
                $tenant_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$tables['tenants']} WHERE user_id = %d AND status = 'active' LIMIT 1",
                    $current_user_id
                ));
                if ($tenant_id) {
                    $sender_id = intval($tenant_id);
                }
            }
        }

        $result = Rental_Gates_Message::send($thread_id, $sender_id, $sender_type, $message_text);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Return the message with content alias for JS compatibility
        if (is_array($result)) {
            $result['content'] = $result['message'];
        }

        wp_send_json_success(array('message' => $result));
    }

    /**
     * Handle start conversation
     */
    public function handle_start_conversation()
    {
        if (!wp_verify_nonce($_POST['message_nonce'] ?? '', 'rental_gates_message')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Please log in', 'rental-gates'));
        }

        $contact_id = intval($_POST['contact_id'] ?? 0);
        $contact_type = sanitize_text_field($_POST['contact_type'] ?? '');

        if (!$contact_id || !in_array($contact_type, array('staff', 'tenant', 'vendor'))) {
            wp_send_json_error(__('Invalid contact', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(__('Organization not found', 'rental-gates'));
        }

        $current_user_id = get_current_user_id();
        $current_user_type = 'staff';

        $thread = Rental_Gates_Message::get_or_create_thread(
            $org_id,
            $current_user_id,
            $current_user_type,
            $contact_id,
            $contact_type
        );

        if (!$thread) {
            wp_send_json_error(__('Failed to create conversation', 'rental-gates'));
        }

        wp_send_json_success(array('thread_id' => $thread['id']));
    }

    /**
     * Handle start thread (tenant-friendly version)
     */
    public function handle_start_thread()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Please log in', 'rental-gates'));
        }

        $recipient_id = intval($_POST['recipient_id'] ?? 0);
        $recipient_type = sanitize_text_field($_POST['recipient_type'] ?? 'staff');
        $sender_type = sanitize_text_field($_POST['sender_type'] ?? 'staff');

        if (!$recipient_id) {
            wp_send_json_error(__('Invalid recipient', 'rental-gates'));
        }

        $current_user_id = get_current_user_id();
        $sender_id = $current_user_id;

        // Get org_id based on sender type
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        if ($sender_type === 'tenant') {
            // Get tenant record and org_id
            $tenant = $wpdb->get_row($wpdb->prepare(
                "SELECT id, organization_id FROM {$tables['tenants']} WHERE user_id = %d AND status = 'active' LIMIT 1",
                $current_user_id
            ), ARRAY_A);

            if (!$tenant) {
                wp_send_json_error(__('Tenant record not found', 'rental-gates'));
            }

            $sender_id = $tenant['id'];
            $org_id = $tenant['organization_id'];
        } else {
            $org_id = Rental_Gates_Roles::get_organization_id();
        }

        if (!$org_id) {
            wp_send_json_error(__('Organization not found', 'rental-gates'));
        }

        $thread = Rental_Gates_Message::get_or_create_thread(
            $org_id,
            $sender_id,
            $sender_type,
            $recipient_id,
            $recipient_type
        );

        if (!$thread) {
            wp_send_json_error(__('Failed to create conversation', 'rental-gates'));
        }

        wp_send_json_success(array('thread_id' => $thread['id']));
    }

    /**
     * Handle get new messages (for polling)
     */
    public function handle_get_new_messages()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Please log in', 'rental-gates'));
        }

        $thread_id = intval($_POST['thread_id'] ?? 0);
        $after_id = intval($_POST['after_id'] ?? 0);

        if (!$thread_id) {
            wp_send_json_error(__('Invalid thread', 'rental-gates'));
        }

        // Verify user has access to this thread
        $thread = Rental_Gates_Message::get_thread($thread_id);
        if (!$thread) {
            wp_send_json_error(__('Thread not found', 'rental-gates'));
        }

        // Get current user info
        $current_user_id = get_current_user_id();
        $current_user_type = 'staff';

        $user = wp_get_current_user();
        if (in_array('rental_gates_tenant', $user->roles)) {
            $current_user_type = 'tenant';
            // Get tenant ID
            global $wpdb;
            $tables = Rental_Gates_Database::get_table_names();
            $tenant_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tables['tenants']} WHERE user_id = %d AND status = 'active' LIMIT 1",
                $current_user_id
            ));
            if ($tenant_id) {
                $current_user_id = $tenant_id;
            }
        }

        // Verify user is participant
        $is_participant = (
            ($thread['participant_1_id'] == $current_user_id && $thread['participant_1_type'] == $current_user_type) ||
            ($thread['participant_2_id'] == $current_user_id && $thread['participant_2_type'] == $current_user_type)
        );

        if (!$is_participant) {
            wp_send_json_error(__('Access denied', 'rental-gates'));
        }

        // Get new messages after the given ID
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['messages']} 
             WHERE thread_id = %d AND id > %d 
             ORDER BY created_at ASC 
             LIMIT 50",
            $thread_id,
            $after_id
        ), ARRAY_A);

        // Enrich with sender info
        foreach ($messages as &$msg) {
            $sender = Rental_Gates_Message::get_participant_info($msg['sender_id'], $msg['sender_type']);
            $msg['sender_name'] = $sender['name'] ?? 'Unknown';
            $msg['content'] = $msg['message']; // Alias
            $msg['time_formatted'] = date_i18n('M j, g:i a', strtotime($msg['created_at']));
        }

        // Mark as read
        Rental_Gates_Message::mark_as_read($thread_id, $current_user_id, $current_user_type);

        wp_send_json_success(array('messages' => $messages));
    }

    /**
     * Handle create announcement
     */
    public function handle_create_announcement()
    {
        if (!wp_verify_nonce($_POST['announcement_nonce'] ?? '', 'rental_gates_announcement')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        // Check for communications capability (announcements fall under communications)
        if (!current_user_can('rg_manage_communications') && !Rental_Gates_Roles::is_owner_or_manager()) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(__('Organization not found', 'rental-gates'));
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $audience_type = sanitize_text_field($_POST['audience_type'] ?? 'all');
        $audience_ids = isset($_POST['audience_ids']) ? array_map('intval', $_POST['audience_ids']) : null;
        $channels = sanitize_text_field($_POST['channels'] ?? 'both');
        $delivery = sanitize_text_field($_POST['delivery'] ?? 'immediate');
        $scheduled_at = sanitize_text_field($_POST['scheduled_at'] ?? '');

        if (empty($title) || empty($message)) {
            wp_send_json_error(__('Title and message are required', 'rental-gates'));
        }

        $data = array(
            'organization_id' => $org_id,
            'title' => $title,
            'message' => $message,
            'audience_type' => $audience_type,
            'audience_ids' => $audience_ids,
            'channels' => $channels,
            'delivery' => $delivery,
            'scheduled_at' => $delivery === 'scheduled' && $scheduled_at ? $scheduled_at : null,
        );

        $result = Rental_Gates_Announcement::create($data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('announcement' => $result));
    }

    /**
     * Handle send announcement
     */
    public function handle_send_announcement()
    {
        if (!wp_verify_nonce($_POST['announcement_nonce'] ?? '', 'rental_gates_announcement')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        // Check for communications capability (announcements fall under communications)
        if (!current_user_can('rg_manage_communications') && !Rental_Gates_Roles::is_owner_or_manager()) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        $announcement_id = intval($_POST['announcement_id'] ?? 0);

        if (!$announcement_id) {
            wp_send_json_error(__('Invalid announcement', 'rental-gates'));
        }

        $result = Rental_Gates_Announcement::send($announcement_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle report CSV export
     */
    public function handle_report_export()
    {
        // Check if this is a report export request
        if (!isset($_GET['export']) || !in_array($_GET['export'], array('csv', 'pdf'))) {
            return;
        }
        
        $format = sanitize_text_field($_GET['export']);

        // Check if on reports page
        $page = get_query_var('rental_gates_page');
        $section = get_query_var('rental_gates_section');

        if ($page !== 'dashboard' || $section !== 'reports') {
            return;
        }

        // Verify user has access
        if (!is_user_logged_in() || !current_user_can('rg_view_reports')) {
            wp_die(__('Access denied', 'rental-gates'));
        }

        // Get organization
        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_die(__('Organization not found', 'rental-gates'));
        }

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        // Get parameters
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'financial';
        $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
        $year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
        $month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));

        // Calculate date range
        switch ($period) {
            case 'year':
                $start_date = "$year-01-01";
                $end_date = "$year-12-31";
                $filename_period = $year;
                break;
            case 'quarter':
                $quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : ceil($month / 3);
                $start_month = (($quarter - 1) * 3) + 1;
                $end_month = $quarter * 3;
                $start_date = "$year-" . str_pad($start_month, 2, '0', STR_PAD_LEFT) . "-01";
                $end_date = date('Y-m-t', strtotime("$year-" . str_pad($end_month, 2, '0', STR_PAD_LEFT) . "-01"));
                $filename_period = "Q{$quarter}-{$year}";
                break;
            default:
                $start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
                $end_date = date('Y-m-t', strtotime($start_date));
                $filename_period = date('M-Y', strtotime($start_date));
                break;
        }

        // Generate data based on tab
        $report_data = array();
        $filename = "rental-gates-{$tab}-report-{$filename_period}";

        switch ($tab) {
            case 'financial':
                $report_data[] = array('Building', 'Billed', 'Collected', 'Collection Rate');

                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT b.name as building_name,
                            COALESCE(SUM(p.amount), 0) as billed,
                            COALESCE(SUM(p.amount_paid), 0) as collected
                     FROM {$tables['buildings']} b
                     LEFT JOIN {$tables['units']} u ON b.id = u.building_id
                     LEFT JOIN {$tables['leases']} l ON u.id = l.unit_id
                     LEFT JOIN {$tables['payments']} p ON l.id = p.lease_id
                         AND p.due_date BETWEEN %s AND %s
                     WHERE b.organization_id = %d
                     GROUP BY b.id, b.name
                     ORDER BY b.name",
                    $start_date,
                    $end_date,
                    $org_id
                ), ARRAY_A);

                foreach ($results as $row) {
                    $rate = $row['billed'] > 0 ? round(($row['collected'] / $row['billed']) * 100, 1) . '%' : '0%';
                    $report_data[] = array(
                        $row['building_name'],
                        '$' . number_format($row['billed'], 2),
                        '$' . number_format($row['collected'], 2),
                        $rate
                    );
                }
                break;

            case 'occupancy':
                $report_data[] = array('Building', 'Total Units', 'Occupied', 'Vacant', 'Occupancy Rate');

                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT b.name as building_name,
                            COUNT(DISTINCT u.id) as total_units,
                            COUNT(DISTINCT CASE WHEN l.id IS NOT NULL THEN u.id END) as occupied
                     FROM {$tables['buildings']} b
                     LEFT JOIN {$tables['units']} u ON b.id = u.building_id
                     LEFT JOIN {$tables['leases']} l ON u.id = l.unit_id AND l.status = 'active'
                     WHERE b.organization_id = %d
                     GROUP BY b.id, b.name
                     ORDER BY b.name",
                    $org_id
                ), ARRAY_A);

                foreach ($results as $row) {
                    $vacant = $row['total_units'] - $row['occupied'];
                    $rate = $row['total_units'] > 0 ? round(($row['occupied'] / $row['total_units']) * 100, 1) . '%' : '0%';
                    $report_data[] = array(
                        $row['building_name'],
                        $row['total_units'],
                        $row['occupied'],
                        $vacant,
                        $rate
                    );
                }
                break;

            case 'maintenance':
                $report_data[] = array('Building', 'Total Work Orders', 'Completed', 'Completion Rate');

                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT b.name as building_name,
                            COUNT(wo.id) as total_orders,
                            SUM(CASE WHEN wo.status = 'completed' THEN 1 ELSE 0 END) as completed
                     FROM {$tables['buildings']} b
                     LEFT JOIN {$tables['work_orders']} wo ON b.id = wo.building_id
                         AND wo.created_at BETWEEN %s AND %s
                     WHERE b.organization_id = %d
                     GROUP BY b.id, b.name
                     ORDER BY b.name",
                    $start_date,
                    $end_date . ' 23:59:59',
                    $org_id
                ), ARRAY_A);

                foreach ($results as $row) {
                    $rate = $row['total_orders'] > 0 ? round(($row['completed'] / $row['total_orders']) * 100, 1) . '%' : 'N/A';
                    $report_data[] = array(
                        $row['building_name'],
                        $row['total_orders'],
                        $row['completed'],
                        $rate
                    );
                }
                break;
        }

        // Output based on format
        if ($format === 'csv') {
            // Output CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');

            // Add BOM for Excel UTF-8 compatibility
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            foreach ($report_data as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
            exit;
        } elseif ($format === 'pdf') {
            // Generate HTML for PDF
            $org = Rental_Gates_Organization::get($org_id);
            $tab_labels = array(
                'financial' => __('Financial Report', 'rental-gates'),
                'occupancy' => __('Occupancy Report', 'rental-gates'),
                'maintenance' => __('Maintenance Report', 'rental-gates'),
            );
            
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
            $html .= '<title>' . esc_html($tab_labels[$tab] ?? ucfirst($tab)) . ' - ' . esc_html($filename_period) . '</title>';
            $html .= '<style>
                body { font-family: Arial, sans-serif; padding: 20px; color: #333; }
                h1 { color: #111827; margin-bottom: 10px; }
                .meta { color: #6b7280; margin-bottom: 30px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background: #f3f4f6; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #e5e7eb; }
                td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
                tr:hover { background: #f9fafb; }
                @media print { body { padding: 10px; } }
            </style></head><body>';
            $html .= '<h1>' . esc_html($tab_labels[$tab] ?? ucfirst($tab)) . '</h1>';
            $html .= '<div class="meta">';
            $html .= '<strong>' . __('Organization', 'rental-gates') . ':</strong> ' . esc_html($org['name'] ?? '') . '<br>';
            $html .= '<strong>' . __('Period', 'rental-gates') . ':</strong> ' . esc_html($filename_period) . '<br>';
            $html .= '<strong>' . __('Generated', 'rental-gates') . ':</strong> ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'));
            $html .= '</div>';
            $html .= '<table>';
            
            if (!empty($report_data)) {
                $header = array_shift($report_data);
                $html .= '<thead><tr>';
                foreach ($header as $col) {
                    $html .= '<th>' . esc_html($col) . '</th>';
                }
                $html .= '</tr></thead><tbody>';
                
                foreach ($report_data as $row) {
                    $html .= '<tr>';
                    foreach ($row as $col) {
                        $html .= '<td>' . esc_html($col) . '</td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</tbody>';
            }
            
            $html .= '</table>';
            $html .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 12px;">';
            $html .= __('Generated by Rental Gates', 'rental-gates');
            $html .= '</div>';
            $html .= '</body></html>';
            
            // Output HTML with print instructions for PDF
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            echo '<script>window.print();</script>';
            exit;
        }
    }

    // =========================================
    // STRIPE HANDLERS
    // =========================================

    /**
     * Handle Stripe SetupIntent creation for adding payment methods
     */
    public function handle_stripe_setup_intent()
    {
        check_ajax_referer('rental_gates_stripe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not authenticated', 'rental-gates')));
        }

        $user_id = get_current_user_id();

        $setup_intent = Rental_Gates_Stripe::create_setup_intent($user_id);

        if (is_wp_error($setup_intent)) {
            wp_send_json_error(array('message' => $setup_intent->get_error_message()));
        }

        wp_send_json_success(array(
            'client_secret' => $setup_intent['client_secret'],
        ));
    }

    /**
     * Handle saving a payment method after SetupIntent completes
     */
    public function handle_stripe_save_payment_method()
    {
        check_ajax_referer('rental_gates_stripe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not authenticated', 'rental-gates')));
        }

        $payment_method_id = sanitize_text_field($_POST['payment_method_id'] ?? '');

        if (empty($payment_method_id)) {
            wp_send_json_error(array('message' => __('Payment method ID required', 'rental-gates')));
        }

        $user_id = get_current_user_id();

        // Get payment method details from Stripe
        $pm = Rental_Gates_Stripe::api_request('payment_methods/' . $payment_method_id);

        if (is_wp_error($pm)) {
            wp_send_json_error(array('message' => $pm->get_error_message()));
        }

        // Save to database
        $method_id = Rental_Gates_Stripe::save_payment_method($user_id, $pm);

        if (!$method_id) {
            wp_send_json_error(array('message' => __('Failed to save payment method', 'rental-gates')));
        }

        wp_send_json_success(array(
            'message' => __('Payment method added successfully', 'rental-gates'),
            'method_id' => $method_id,
        ));
    }

    /**
     * Handle deleting a payment method
     */
    public function handle_stripe_delete_payment_method()
    {
        check_ajax_referer('rental_gates_stripe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not authenticated', 'rental-gates')));
        }

        $method_id = intval($_POST['method_id'] ?? 0);

        if (!$method_id) {
            wp_send_json_error(array('message' => __('Invalid method ID', 'rental-gates')));
        }

        $result = Rental_Gates_Stripe::delete_payment_method(get_current_user_id(), $method_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Payment method deleted', 'rental-gates')));
    }

    /**
     * Handle setting default payment method
     */
    public function handle_stripe_set_default_method()
    {
        check_ajax_referer('rental_gates_stripe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not authenticated', 'rental-gates')));
        }

        $method_id = intval($_POST['method_id'] ?? 0);

        if (!$method_id) {
            wp_send_json_error(array('message' => __('Invalid method ID', 'rental-gates')));
        }

        Rental_Gates_Stripe::set_default_payment_method(get_current_user_id(), $method_id);

        wp_send_json_success(array('message' => __('Default payment method updated', 'rental-gates')));
    }

    /**
     * Handle updating subscription payment method
     */
    public function handle_update_subscription_payment_method()
    {
        check_ajax_referer('rental_gates_stripe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not authenticated', 'rental-gates')));
        }

        $payment_method_id = sanitize_text_field($_POST['payment_method_id'] ?? '');

        if (empty($payment_method_id)) {
            wp_send_json_error(array('message' => __('Payment method ID required', 'rental-gates')));
        }

        $org_id = rg_feature_gate()->get_user_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        // Check permissions (Owner only)
        if (!rg_feature_gate()->check_role(array('owner'))) {
            wp_send_json_error(array('message' => __('Only the organization owner can update the subscription payment method', 'rental-gates')));
        }

        // Get current subscription
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['subscriptions']} 
             WHERE organization_id = %d 
             AND (status IN ('active', 'trialing', 'past_due', 'unpaid', 'incomplete') 
                  OR cancel_at_period_end = 1)
             ORDER BY created_at DESC LIMIT 1",
            $org_id
        ));

        if (!$subscription || empty($subscription->stripe_subscription_id)) {
            wp_send_json_error(array('message' => __('No active subscription found', 'rental-gates')));
        }

        // Get customer ID
        $customer_id = Rental_Gates_Billing::get_or_create_customer($org_id);
        if (is_wp_error($customer_id)) {
            wp_send_json_error(array('message' => $customer_id->get_error_message()));
        }

        // Attach payment method to customer if not already attached
        $attach_result = Rental_Gates_Stripe::attach_payment_method($payment_method_id, $customer_id);
        if (is_wp_error($attach_result) && strpos($attach_result->get_error_message(), 'already been attached') === false) {
            wp_send_json_error(array('message' => $attach_result->get_error_message()));
        }

        // Set as default payment method for customer
        Rental_Gates_Stripe::set_customer_invoice_payment_method($customer_id, $payment_method_id);

        // Update subscription's default payment method
        $update_result = Rental_Gates_Stripe::api_request(
            "subscriptions/{$subscription->stripe_subscription_id}",
            'POST',
            array(
                'default_payment_method' => $payment_method_id
            )
        );

        if (is_wp_error($update_result)) {
            wp_send_json_error(array('message' => $update_result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Subscription payment method updated successfully', 'rental-gates')
        ));
    }

    /**
     * Handle creating Checkout Session for rent payment (Embedded or Hosted)
     */
    public function handle_stripe_create_payment_intent()
    {
        check_ajax_referer('rental_gates_stripe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not authenticated', 'rental-gates')));
        }

        $payment_id = intval($_POST['payment_id'] ?? 0);
        $ui_mode = sanitize_text_field($_POST['ui_mode'] ?? 'embedded');

        if (!$payment_id) {
            wp_send_json_error(array('message' => __('Payment ID required', 'rental-gates')));
        }

        // Verify user has access to this payment
        $payment = Rental_Gates_Payment::get_with_details($payment_id);
        if (!$payment) {
            wp_send_json_error(array('message' => __('Payment not found', 'rental-gates')));
        }

        // Check user is the tenant or org member
        $user_id = get_current_user_id();
        $has_access = false;

        if ($payment['tenant_id']) {
            $tenant = Rental_Gates_Tenant::get($payment['tenant_id']);
            if ($tenant && $tenant['user_id'] == $user_id) {
                $has_access = true;
            }
        }

        if (!$has_access) {
            $org_id = Rental_Gates_Roles::get_organization_id();
            if ($org_id && $payment['organization_id'] == $org_id) {
                $has_access = true;
            }
        }

        if (!$has_access) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        // Create Checkout Session (embedded or hosted)
        $session = Rental_Gates_Stripe::create_checkout_session($payment_id, $ui_mode);

        if (is_wp_error($session)) {
            wp_send_json_error(array('message' => $session->get_error_message()));
        }

        $response = array(
            'session_id' => $session['id'],
        );

        // For embedded mode, return client_secret
        if ($ui_mode === 'embedded') {
            $response['client_secret'] = $session['client_secret'];
        } else {
            // For hosted mode, return redirect URL
            $response['redirect_url'] = $session['url'];
        }

        wp_send_json_success($response);
    }

    /**
     * Get Checkout Session status (for return page)
     */
    public function handle_stripe_session_status()
    {
        $session_id = sanitize_text_field($_GET['session_id'] ?? '');

        if (empty($session_id)) {
            wp_send_json_error(array('message' => __('Session ID required', 'rental-gates')));
        }

        $session = Rental_Gates_Stripe::get_checkout_session($session_id);

        if (is_wp_error($session)) {
            wp_send_json_error(array('message' => $session->get_error_message()));
        }

        wp_send_json_success(array(
            'status' => $session['status'],
            'payment_status' => $session['payment_status'],
            'customer_email' => $session['customer_details']['email'] ?? '',
            'amount_total' => $session['amount_total'] / 100,
            'payment_id' => $session['metadata']['payment_id'] ?? null,
        ));
    }

    /**
     * Handle Stripe Connect onboarding
     */
    public function handle_stripe_connect()
    {
        check_ajax_referer('rental_gates_stripe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not logged in', 'rental-gates')));
        }

        // Check for settings OR connect stripe capability
        if (!current_user_can('rg_manage_settings') && !current_user_can('rg_connect_stripe')) {
            wp_send_json_error(array('message' => __('Access denied. You need settings permission.', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found. Please ensure you are a member of an organization.', 'rental-gates')));
        }

        $action = sanitize_text_field($_POST['stripe_action'] ?? 'create');

        switch ($action) {
            case 'create':
                // Create Connect account
                $account = Rental_Gates_Stripe::create_connect_account($org_id);
                if (is_wp_error($account)) {
                    // If account exists, get onboarding link
                    if ($account->get_error_code() === 'exists') {
                        $link = Rental_Gates_Stripe::create_account_link($org_id);
                        if (!is_wp_error($link)) {
                            wp_send_json_success(array('redirect_url' => $link['url']));
                        }
                    }
                    wp_send_json_error(array('message' => $account->get_error_message()));
                }

                // Get onboarding link
                $link = Rental_Gates_Stripe::create_account_link($org_id);
                if (is_wp_error($link)) {
                    wp_send_json_error(array('message' => $link->get_error_message()));
                }

                wp_send_json_success(array('redirect_url' => $link['url']));
                break;

            case 'refresh':
                // Refresh account status
                $result = Rental_Gates_Stripe::refresh_connected_account($org_id);
                if (is_wp_error($result)) {
                    wp_send_json_error(array('message' => $result->get_error_message()));
                }
                wp_send_json_success(array('message' => __('Account status refreshed', 'rental-gates')));
                break;

            case 'dashboard':
                // Get Stripe dashboard link
                $link = Rental_Gates_Stripe::create_login_link($org_id);
                if (is_wp_error($link)) {
                    wp_send_json_error(array('message' => $link->get_error_message()));
                }
                wp_send_json_success(array('redirect_url' => $link['url']));
                break;

            case 'onboarding':
                // Resume onboarding
                $link = Rental_Gates_Stripe::create_account_link($org_id);
                if (is_wp_error($link)) {
                    wp_send_json_error(array('message' => $link->get_error_message()));
                }
                wp_send_json_success(array('redirect_url' => $link['url']));
                break;

            default:
                wp_send_json_error(array('message' => __('Invalid action', 'rental-gates')));
        }
    }

    /**
     * Handle Stripe webhooks
     */
    public function handle_stripe_webhook()
    {
        // Get raw payload
        $payload = file_get_contents('php://input');
        $signature = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';

        if (empty($payload) || empty($signature)) {
            status_header(400);
            exit('Invalid request');
        }

        $result = Rental_Gates_Stripe::handle_webhook($payload, $signature);

        if (is_wp_error($result)) {
            error_log('Rental Gates Stripe Webhook Error: ' . $result->get_error_message());
            status_header(400);
            exit($result->get_error_message());
        }

        status_header(200);
        exit('OK');
    }

    /**
     * Handle manual payment recording
     */
    public function handle_record_manual_payment()
    {
        try {
            check_ajax_referer('rental_gates_nonce', 'nonce');

            if (!is_user_logged_in() || !current_user_can('rg_manage_payments')) {
                wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
            }

            $org_id = Rental_Gates_Roles::get_organization_id();
            if (!$org_id) {
                wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
            }

            // Validate inputs
            $lease_id = intval($_POST['lease_id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            $type = sanitize_text_field($_POST['type'] ?? 'rent');
            $method = sanitize_text_field($_POST['method'] ?? 'cash');
            $paid_at = sanitize_text_field($_POST['paid_at'] ?? '');
            $reference = sanitize_text_field($_POST['reference'] ?? '');
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');
            $due_date = sanitize_text_field($_POST['due_date'] ?? '');

            if (!$lease_id || $amount <= 0) {
                wp_send_json_error(array('message' => __('Please select a tenant and enter a valid amount', 'rental-gates')));
            }

            // Get lease with details including tenants
            $lease = Rental_Gates_Lease::get_with_details($lease_id);
            if (!$lease || $lease['organization_id'] != $org_id) {
                wp_send_json_error(array('message' => __('Lease not found or access denied', 'rental-gates')));
            }

            // Get primary tenant from lease_tenants
            $tenant_id = null;
            if (!empty($lease['tenants']) && is_array($lease['tenants'])) {
                // Find primary tenant first (role field from lease_tenants)
                foreach ($lease['tenants'] as $t) {
                    if (isset($t['role']) && $t['role'] === 'primary') {
                        $tenant_id = $t['tenant_id'] ?? $t['id'] ?? null;
                        break;
                    }
                }
                // If no primary, use first tenant
                if (!$tenant_id && isset($lease['tenants'][0])) {
                    $tenant_id = $lease['tenants'][0]['tenant_id'] ?? $lease['tenants'][0]['id'] ?? null;
                }
            }

            if (!$tenant_id) {
                wp_send_json_error(array('message' => __('No tenant found for this lease. Please add a tenant to the lease first.', 'rental-gates')));
            }

            // Create payment record
            $payment_data = array(
                'organization_id' => $org_id,
                'lease_id' => $lease_id,
                'tenant_id' => $tenant_id,
                'amount' => $amount,
                'amount_paid' => $amount,
                'type' => $type,
                'status' => 'succeeded',
                'method' => $method,
                'currency' => 'USD',
                'due_date' => $due_date ?: null,
                'paid_at' => $paid_at ? $paid_at . ' ' . current_time('H:i:s') : current_time('mysql'),
                'notes' => $notes,
                'net_amount' => $amount, // No fees for manual payments
                'description' => sprintf(
                    __('%s payment for %s', 'rental-gates'),
                    ucfirst($type),
                    $lease['unit_name'] ?? $lease['unit_number'] ?? 'Unit'
                ),
                'meta_data' => json_encode(array(
                    'manual_entry' => true,
                    'recorded_by' => get_current_user_id(),
                    'recorded_at' => current_time('mysql'),
                    'reference' => $reference,
                )),
            );

            $result = Rental_Gates_Payment::create($payment_data);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }

            // Clear cache
            if (class_exists('Rental_Gates_Cache')) {
                Rental_Gates_Cache::delete_stats($org_id, 'payments');
            }

            // Get payment ID
            $payment_id = is_array($result) ? $result['id'] : $result;

            // Generate receipt automatically for manual payments
            if (class_exists('Rental_Gates_Invoice')) {
                try {
                    Rental_Gates_Invoice::create_from_payment($payment_id, 'receipt');
                } catch (Exception $e) {
                    error_log('Rental Gates - Receipt generation error: ' . $e->getMessage());
                }
            }

            // Notify tenant
            $tenant = Rental_Gates_Tenant::get($tenant_id);
            if ($tenant && !empty($tenant['user_id'])) {
                Rental_Gates_Notification::send(
                    $tenant['user_id'],
                    'payment',
                    __('Payment Recorded', 'rental-gates'),
                    sprintf(__('A payment of $%.2f has been recorded for your account.', 'rental-gates'), $amount),
                    home_url('/rental-gates/tenant/payments')
                );
            }

            wp_send_json_success(array(
                'message' => __('Payment recorded successfully', 'rental-gates'),
                'payment_id' => $payment_id,
            ));
        } catch (Exception $e) {
            error_log('Rental Gates - Record Manual Payment Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('An error occurred while recording the payment', 'rental-gates')));
        }
    }

    /**
     * Handle payment sync from Stripe session
     */
    public function handle_sync_payment()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Not logged in', 'rental-gates')));
        }

        $session_id = sanitize_text_field($_POST['session_id'] ?? '');

        if (empty($session_id)) {
            wp_send_json_error(array('message' => __('Session ID required', 'rental-gates')));
        }

        $result = Rental_Gates_Stripe::sync_payment_from_session($session_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handle generating monthly rent payments for all active leases
     */
    public function handle_generate_rent_payments()
    {
        try {
            check_ajax_referer('rental_gates_nonce', 'nonce');

            if (!is_user_logged_in() || !current_user_can('rg_manage_payments')) {
                wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
            }

            $org_id = Rental_Gates_Roles::get_organization_id();
            if (!$org_id) {
                wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
            }

            $for_month = sanitize_text_field($_POST['for_month'] ?? '');
            if (!$for_month) {
                $for_month = date('Y-m');
            }

            $result = Rental_Gates_Payment::generate_monthly_payments($org_id, $for_month);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }

            // Clear cache
            if (class_exists('Rental_Gates_Cache')) {
                Rental_Gates_Cache::delete_stats($org_id, 'payments');
            }

            wp_send_json_success(array(
                'message' => sprintf(
                    __('Generated %d payments, skipped %d (already exist)', 'rental-gates'),
                    $result['generated'],
                    $result['skipped']
                ),
                'generated' => $result['generated'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'] ?? array(),
            ));
        } catch (Exception $e) {
            error_log('Rental Gates - Generate Rent Payments Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('An error occurred while generating payments', 'rental-gates')));
        }
    }

    /**
     * Handle creating a single pending payment for a lease
     */
    public function handle_create_pending_payment()
    {
        try {
            check_ajax_referer('rental_gates_nonce', 'nonce');

            if (!is_user_logged_in() || !current_user_can('rg_manage_payments')) {
                wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
            }

            $org_id = Rental_Gates_Roles::get_organization_id();
            if (!$org_id) {
                wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
            }

            $lease_id = intval($_POST['lease_id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            $type = sanitize_text_field($_POST['type'] ?? 'rent');
            $due_date = sanitize_text_field($_POST['due_date'] ?? '');
            $description = sanitize_text_field($_POST['description'] ?? '');
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');

            if (!$lease_id) {
                wp_send_json_error(array('message' => __('Please select a tenant/lease', 'rental-gates')));
            }

            // Get lease details
            $lease = Rental_Gates_Lease::get_with_details($lease_id);
            if (!$lease || $lease['organization_id'] != $org_id) {
                wp_send_json_error(array('message' => __('Lease not found', 'rental-gates')));
            }

            // Use rent amount if not specified
            if ($amount <= 0) {
                $amount = floatval($lease['rent_amount']);
            }

            // Get primary tenant from lease_tenants
            $tenant_id = null;
            if (!empty($lease['tenants']) && is_array($lease['tenants'])) {
                foreach ($lease['tenants'] as $t) {
                    // Check for primary role (role field from lease_tenants)
                    if (isset($t['role']) && $t['role'] === 'primary') {
                        $tenant_id = $t['tenant_id'] ?? $t['id'] ?? null;
                        break;
                    }
                }
                // If no primary found, use first tenant
                if (!$tenant_id && isset($lease['tenants'][0])) {
                    $tenant_id = $lease['tenants'][0]['tenant_id'] ?? $lease['tenants'][0]['id'] ?? null;
                }
            }

            if (!$tenant_id) {
                wp_send_json_error(array('message' => __('No tenant found for this lease. Please add a tenant to the lease first.', 'rental-gates')));
            }

            // Calculate period if due_date provided
            $period_start = null;
            $period_end = null;
            if ($due_date && $type === 'rent') {
                $period_start = date('Y-m-01', strtotime($due_date));
                $period_end = date('Y-m-t', strtotime($due_date));
            }

            // Create payment
            $payment_data = array(
                'organization_id' => $org_id,
                'lease_id' => $lease_id,
                'tenant_id' => $tenant_id,
                'amount' => $amount,
                'type' => $type,
                'status' => 'pending',
                'due_date' => $due_date ?: null,
                'period_start' => $period_start,
                'period_end' => $period_end,
                'notes' => $notes,
                'description' => $description ?: sprintf(
                    __('%s for %s', 'rental-gates'),
                    ucfirst($type),
                    $lease['unit_name'] ?? 'Unit'
                ),
            );

            $result = Rental_Gates_Payment::create($payment_data);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }

            // Clear cache
            if (class_exists('Rental_Gates_Cache')) {
                Rental_Gates_Cache::delete_stats($org_id, 'payments');
            }

            // Notify tenant about pending payment
            $tenant = Rental_Gates_Tenant::get($tenant_id);
            if ($tenant && !empty($tenant['user_id'])) {
                Rental_Gates_Notification::send(
                    $tenant['user_id'],
                    'payment',
                    __('Payment Due', 'rental-gates'),
                    sprintf(
                        __('A payment of $%.2f is due%s.', 'rental-gates'),
                        $amount,
                        $due_date ? ' on ' . date_i18n('F j, Y', strtotime($due_date)) : ''
                    ),
                    home_url('/rental-gates/tenant/payments')
                );
            }

            $payment_id = is_array($result) ? $result['id'] : $result;

            wp_send_json_success(array(
                'message' => __('Payment created successfully', 'rental-gates'),
                'payment_id' => $payment_id,
            ));
        } catch (Exception $e) {
            error_log('Rental Gates - Create Pending Payment Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('An error occurred while creating the payment', 'rental-gates')));
        }
    }

    /**
     * Handle download invoice request
     */
    public function handle_download_invoice()
    {
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'download_invoice')) {
            wp_die(__('Security check failed', 'rental-gates'));
        }

        if (!is_user_logged_in()) {
            wp_die(__('Please log in', 'rental-gates'));
        }

        $invoice_id = intval($_GET['id'] ?? 0);
        $format = sanitize_text_field($_GET['format'] ?? 'pdf');

        $invoice = Rental_Gates_Invoice::get($invoice_id);
        if (!$invoice) {
            wp_die(__('Invoice not found', 'rental-gates'));
        }

        // Security check
        $current_user_id = get_current_user_id();
        $has_access = false;

        $user_org_id = Rental_Gates_Roles::get_organization_id();
        if ($user_org_id && $user_org_id == $invoice['organization_id']) {
            $has_access = true;
        }

        if (!$has_access && $invoice['tenant_id']) {
            $tenant = Rental_Gates_Tenant::get($invoice['tenant_id']);
            if ($tenant && $tenant['user_id'] == $current_user_id) {
                $has_access = true;
            }
        }

        if (!$has_access) {
            wp_die(__('Access denied', 'rental-gates'));
        }

        $html = $invoice['html_content'] ?: Rental_Gates_Invoice::generate_html($invoice);
        $filename = ($invoice['type'] === 'receipt' ? 'Receipt' : 'Invoice') . '-' . $invoice['invoice_number'];

        if ($format === 'html') {
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.html"');
            echo $html;
            exit;
        }

        // PDF generation using wkhtmltopdf or browser print
        if ($format === 'pdf') {
            // Try wkhtmltopdf if available
            $wkhtmltopdf = '/usr/bin/wkhtmltopdf';
            if (file_exists($wkhtmltopdf)) {
                $temp_html = tempnam(sys_get_temp_dir(), 'invoice_') . '.html';
                $temp_pdf = tempnam(sys_get_temp_dir(), 'invoice_') . '.pdf';

                file_put_contents($temp_html, $html);

                $cmd = escapeshellcmd($wkhtmltopdf) . ' --page-size A4 --margin-top 10mm --margin-bottom 10mm --margin-left 10mm --margin-right 10mm ' .
                    escapeshellarg($temp_html) . ' ' . escapeshellarg($temp_pdf) . ' 2>&1';
                exec($cmd, $output, $return);

                if ($return === 0 && file_exists($temp_pdf)) {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
                    readfile($temp_pdf);
                    unlink($temp_html);
                    unlink($temp_pdf);
                    exit;
                }

                @unlink($temp_html);
                @unlink($temp_pdf);
            }

            // Fallback: serve HTML with print instructions
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><title>' . esc_html($filename) . '</title>';
            echo '<style>body{font-family:sans-serif;padding:20px;}</style>';
            echo '</head><body>';
            echo '<div style="background:#fef3c7;border:1px solid #f59e0b;padding:16px;border-radius:8px;margin-bottom:20px;">';
            echo '<strong>' . __('PDF Generation', 'rental-gates') . '</strong><br>';
            echo __('Press Ctrl+P (or Cmd+P on Mac) and select "Save as PDF" to download this document as PDF.', 'rental-gates');
            echo '</div>';
            echo $html;
            echo '<script>window.print();</script>';
            echo '</body></html>';
            exit;
        }

        wp_die(__('Invalid format', 'rental-gates'));
    }

    /**
     * Handle get invoice AJAX request
     */
    public function handle_get_invoice()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
        }

        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        $payment_id = intval($_POST['payment_id'] ?? 0);

        $invoice = null;
        if ($invoice_id) {
            $invoice = Rental_Gates_Invoice::get($invoice_id);
        } elseif ($payment_id) {
            $invoice = Rental_Gates_Invoice::get_by_payment($payment_id);
        }

        if (!$invoice) {
            wp_send_json_error(array('message' => __('Invoice not found', 'rental-gates')));
        }

        // Security check
        $current_user_id = get_current_user_id();
        $has_access = false;

        $user_org_id = Rental_Gates_Roles::get_organization_id();
        if ($user_org_id && $user_org_id == $invoice['organization_id']) {
            $has_access = true;
        }

        if (!$has_access && $invoice['tenant_id']) {
            $tenant = Rental_Gates_Tenant::get($invoice['tenant_id']);
            if ($tenant && $tenant['user_id'] == $current_user_id) {
                $has_access = true;
            }
        }

        if (!$has_access) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        wp_send_json_success(array(
            'invoice' => $invoice,
            'view_url' => home_url('/rental-gates/dashboard/invoice?id=' . $invoice['id']),
        ));
    }

    /**
     * Handle generate invoice AJAX request
     */
    public function handle_generate_invoice()
    {
        try {
            check_ajax_referer('rental_gates_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
            }

            $payment_id = intval($_POST['payment_id'] ?? 0);
            $type = sanitize_text_field($_POST['type'] ?? 'invoice');

            if (!$payment_id) {
                wp_send_json_error(array('message' => __('Payment ID required', 'rental-gates')));
            }

            // Verify access
            $payment = Rental_Gates_Payment::get($payment_id);
            if (!$payment) {
                wp_send_json_error(array('message' => __('Payment not found', 'rental-gates')));
            }

            $user_org_id = Rental_Gates_Roles::get_organization_id();
            if (!$user_org_id || $user_org_id != $payment['organization_id']) {
                wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
            }

            // Check if invoice already exists
            $existing = Rental_Gates_Invoice::get_by_payment($payment_id);
            if ($existing) {
                wp_send_json_success(array(
                    'invoice' => $existing,
                    'message' => __('Invoice already exists', 'rental-gates'),
                ));
                return;
            }

            // Generate invoice
            $invoice = Rental_Gates_Invoice::create_from_payment($payment_id, $type);

            if (is_wp_error($invoice)) {
                wp_send_json_error(array('message' => $invoice->get_error_message()));
            }

            wp_send_json_success(array(
                'invoice' => $invoice,
                'message' => __('Invoice generated successfully', 'rental-gates'),
            ));
        } catch (Exception $e) {
            error_log('Rental Gates - Generate Invoice Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('An error occurred while generating the invoice', 'rental-gates')));
        }
    }

    /**
     * Handle subscription invoice download
     */
    public function handle_download_subscription_invoice()
    {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'download_subscription_invoice')) {
            wp_die(__('Security check failed', 'rental-gates'));
        }

        $invoice_id = intval($_GET['id'] ?? 0);
        $format = sanitize_text_field($_GET['format'] ?? 'html');

        if (!$invoice_id) {
            wp_die(__('Invalid invoice ID', 'rental-gates'));
        }

        $invoice = Rental_Gates_Subscription_Invoice::get($invoice_id);
        if (!$invoice) {
            wp_die(__('Invoice not found', 'rental-gates'));
        }

        // Check access
        $user_org_id = rg_feature_gate()->get_user_org_id();
        if (!$user_org_id || $user_org_id != $invoice['organization_id']) {
            if (!current_user_can('manage_options')) {
                wp_die(__('Access denied', 'rental-gates'));
            }
        }

        // Generate HTML
        $html = Rental_Gates_Subscription_Invoice::generate_html($invoice);
        $filename = 'Invoice-' . $invoice['invoice_number'];

        if ($format === 'html' || $format === 'print') {
            // Output HTML directly
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            exit;
        }

        if ($format === 'pdf') {
            // Try to generate PDF using wkhtmltopdf or similar
            $temp_html = tempnam(sys_get_temp_dir(), 'invoice_') . '.html';
            $temp_pdf = tempnam(sys_get_temp_dir(), 'invoice_') . '.pdf';

            file_put_contents($temp_html, $html);

            // Try wkhtmltopdf
            $wkhtmltopdf = '/usr/bin/wkhtmltopdf';
            if (!file_exists($wkhtmltopdf)) {
                $wkhtmltopdf = '/usr/local/bin/wkhtmltopdf';
            }

            if (file_exists($wkhtmltopdf)) {
                exec($wkhtmltopdf . ' --quiet --page-size A4 --margin-top 10mm --margin-bottom 10mm --margin-left 10mm --margin-right 10mm ' . escapeshellarg($temp_html) . ' ' . escapeshellarg($temp_pdf) . ' 2>&1', $output, $return_var);

                if ($return_var === 0 && file_exists($temp_pdf)) {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
                    header('Content-Length: ' . filesize($temp_pdf));
                    readfile($temp_pdf);
                    unlink($temp_html);
                    unlink($temp_pdf);
                    exit;
                }
            }

            // Fallback: output HTML for browser printing
            header('Content-Type: text/html; charset=utf-8');
            echo '<script>window.print(); setTimeout(function() { window.close(); }, 1000);</script>';
            echo $html;
            unlink($temp_html);
            exit;
        }

        if ($format === 'png') {
            // Try to generate PNG using wkhtmltoimage
            $temp_html = tempnam(sys_get_temp_dir(), 'invoice_') . '.html';
            $temp_png = tempnam(sys_get_temp_dir(), 'invoice_') . '.png';

            file_put_contents($temp_html, $html);

            $wkhtmltoimage = '/usr/bin/wkhtmltoimage';
            if (!file_exists($wkhtmltoimage)) {
                $wkhtmltoimage = '/usr/local/bin/wkhtmltoimage';
            }

            if (file_exists($wkhtmltoimage)) {
                exec($wkhtmltoimage . ' --quiet --width 800 --quality 100 ' . escapeshellarg($temp_html) . ' ' . escapeshellarg($temp_png) . ' 2>&1', $output, $return_var);

                if ($return_var === 0 && file_exists($temp_png)) {
                    header('Content-Type: image/png');
                    header('Content-Disposition: attachment; filename="' . $filename . '.png"');
                    header('Content-Length: ' . filesize($temp_png));
                    readfile($temp_png);
                    unlink($temp_html);
                    unlink($temp_png);
                    exit;
                }
            }

            // Fallback: tell user to use browser screenshot
            wp_die(__('PNG generation not available. Please use your browser\'s screenshot feature or print to PDF.', 'rental-gates'));
        }

        // Default: output HTML
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    /**
     * Handle subscription invoice view
     */
    public function handle_view_subscription_invoice()
    {
        $invoice_id = intval($_GET['id'] ?? 0);
        $token = sanitize_text_field($_GET['token'] ?? '');

        if (!$invoice_id) {
            wp_die(__('Invalid invoice ID', 'rental-gates'));
        }

        $invoice = Rental_Gates_Subscription_Invoice::get($invoice_id);
        if (!$invoice) {
            wp_die(__('Invoice not found', 'rental-gates'));
        }

        // Check access - either logged in user with matching org, or valid token
        $has_access = false;

        if (is_user_logged_in()) {
            $user_org_id = rg_feature_gate()->get_user_org_id();
            if ($user_org_id && $user_org_id == $invoice['organization_id']) {
                $has_access = true;
            }
            if (current_user_can('manage_options')) {
                $has_access = true;
            }
        }

        // Check token for public access
        if (!$has_access && $token) {
            $expected_token = wp_hash($invoice['id'] . $invoice['invoice_number'] . $invoice['created_at']);
            if (hash_equals($expected_token, $token)) {
                $has_access = true;
            }
        }

        if (!$has_access) {
            wp_die(__('Access denied', 'rental-gates'));
        }

        // Generate and output HTML
        $html = Rental_Gates_Subscription_Invoice::generate_html($invoice);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    /**
     * Handle subscription creation
     */
    public function handle_subscription_create()
    {
        check_ajax_referer('rental_gates_subscribe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in to subscribe', 'rental-gates')));
        }

        $plan_id = sanitize_key($_POST['plan_id'] ?? '');
        $payment_method_id = sanitize_text_field($_POST['payment_method_id'] ?? '');
        $billing_cycle = sanitize_text_field($_POST['billing_cycle'] ?? 'monthly');

        if (empty($plan_id)) {
            wp_send_json_error(array('message' => __('Please select a plan', 'rental-gates')));
        }

        // Get user's organization
        $org_id = rg_feature_gate()->get_user_org_id();

        if (!$org_id) {
            wp_send_json_error(array('message' => __('No organization found', 'rental-gates')));
        }

        // Use Rental_Gates_Billing class
        $result = Rental_Gates_Billing::subscribe($org_id, $plan_id, $payment_method_id, $billing_cycle);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Only if it's a paid subscription with result
        if (is_array($result) && isset($result['status'])) {
            // Handle 3D Secure / SCA
            if ($result['status'] === 'incomplete') {
                $payment_intent = $result['latest_invoice']['payment_intent'] ?? null;

                if ($payment_intent && in_array($payment_intent['status'], array('requires_action', 'requires_payment_method'))) {
                    wp_send_json_success(array(
                        'requires_action' => true,
                        'client_secret' => $payment_intent['client_secret'],
                        'subscription_id' => $result['id'],
                    ));
                }
            }
        }

        // Fire action for other plugins/integrations
        do_action('rental_gates_subscription_created', $org_id, $plan_id);

        wp_send_json_success(array(
            'message' => __('Subscription created successfully', 'rental-gates'),
            'redirect' => home_url('/rental-gates/dashboard/billing?subscribed=1'),
        ));
    }

    /**
     * Handle subscription cancellation
     */
    public function handle_subscription_cancel()
    {
        check_ajax_referer('rental_gates_cancel', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
        }

        $org_id = rg_feature_gate()->get_user_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('No organization found', 'rental-gates')));
        }

        // Use Billing Class
        $result = Rental_Gates_Billing::cancel_subscription($org_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Get end date for display
        global $wpdb;
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rg_subscriptions WHERE organization_id = %d AND status = 'active' LIMIT 1",
            $org_id
        ));

        $end_date = !empty($subscription->current_period_end)
            ? date_i18n(get_option('date_format'), strtotime($subscription->current_period_end))
            : __('the end of your billing period', 'rental-gates');

        wp_send_json_success(array(
            'message' => sprintf(
                __('Subscription will cancel on %s. You can resume anytime before then.', 'rental-gates'),
                $end_date
            ),
            'redirect' => home_url('/rental-gates/dashboard/billing?cancelled=1'),
            'cancel_date' => $end_date,
        ));
    }

    /**
     * Handle subscription resume (un-cancel)
     */
    public function handle_subscription_resume()
    {
        check_ajax_referer('rental_gates_resume', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
        }

        $org_id = rg_feature_gate()->get_user_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('No organization found', 'rental-gates')));
        }

        global $wpdb;

        // Get subscription that's set to cancel
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rg_subscriptions 
             WHERE organization_id = %d AND status = 'active' AND cancel_at_period_end = 1 
             LIMIT 1",
            $org_id
        ));

        if (!$subscription) {
            wp_send_json_error(array('message' => __('No subscription pending cancellation found', 'rental-gates')));
        }

        // Resume in Stripe
        if (!empty($subscription->stripe_subscription_id) && Rental_Gates_Stripe::is_configured()) {
            $resume_result = Rental_Gates_Stripe::resume_subscription($subscription->stripe_subscription_id);
            if (is_wp_error($resume_result)) {
                error_log('Rental Gates - Resume Subscription Error: ' . $resume_result->get_error_message());
                wp_send_json_error(array('message' => $resume_result->get_error_message()));
            }
        }

        // Update local subscription
        $wpdb->update(
            $wpdb->prefix . 'rg_subscriptions',
            array(
                'cancel_at_period_end' => 0,
                'cancelled_at' => null,
            ),
            array('id' => $subscription->id),
            array('%d', '%s'),
            array('%d')
        );

        wp_send_json_success(array(
            'message' => __('Subscription resumed! Your plan will continue as normal.', 'rental-gates'),
            'redirect' => home_url('/rental-gates/dashboard/billing?resumed=1'),
        ));
    }

    /**
     * Handle plan change (upgrade/downgrade between paid plans)
     */
    public function handle_plan_change()
    {
        check_ajax_referer('rental_gates_change_plan', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
        }

        $new_plan_id = sanitize_key($_POST['plan_id'] ?? '');
        $payment_method_id = sanitize_text_field($_POST['payment_method_id'] ?? '');

        if (empty($new_plan_id)) {
            wp_send_json_error(array('message' => __('Please select a plan', 'rental-gates')));
        }

        $org_id = rg_feature_gate()->get_user_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('No organization found', 'rental-gates')));
        }

        // Use Billing Class
        $result = Rental_Gates_Billing::change_plan($org_id, $new_plan_id, $payment_method_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Handle scheduled downgrade response
        if (is_array($result) && isset($result['action']) && $result['action'] === 'downgrade_scheduled') {
            global $wpdb;
            $subscription = $result['subscription'];

            // Update subscription to track downgrade
            $wpdb->update(
                $wpdb->prefix . 'rg_subscriptions',
                array('downgrade_to_plan' => $new_plan_id),
                array('id' => $subscription->id),
                array('%s'),
                array('%d')
            );

            $end_date = !empty($subscription->current_period_end)
                ? date_i18n(get_option('date_format'), strtotime($subscription->current_period_end))
                : __('the end of your billing period', 'rental-gates');

            wp_send_json_success(array(
                'message' => sprintf(
                    __('Your plan will change to Free on %s. You\'ll keep your current features until then.', 'rental-gates'),
                    $end_date
                ),
                'redirect' => home_url('/rental-gates/dashboard/billing?downgrade_scheduled=1'),
                'scheduled_date' => $end_date,
                'keep_access' => true,
            ));
        }

        // Handle 3D Secure / SCA
        if (is_array($result) && isset($result['latest_invoice']['payment_intent'])) {
            $pi = $result['latest_invoice']['payment_intent'];
            if ($pi['status'] === 'requires_action') {
                wp_send_json_success(array(
                    'requires_action' => true,
                    'client_secret' => $pi['client_secret'],
                    'subscription_id' => $result['id'],
                ));
            }
        }

        // Success
        wp_send_json_success(array(
            'message' => __('Plan changed successfully!', 'rental-gates'),
            'redirect' => home_url('/rental-gates/dashboard/billing?changed=1'),
        ));
    }

    /**
     * Handle cancel subscription immediately (before period end)
     */
    public function handle_subscription_cancel_immediately()
    {
        check_ajax_referer('rental_gates_cancel_immediately', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
        }

        $org_id = rg_feature_gate()->get_user_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('No organization found', 'rental-gates')));
        }

        // Use Billing Class
        $result = Rental_Gates_Billing::cancel_subscription_immediately($org_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Subscription cancelled and downgraded to free plan.', 'rental-gates'),
            'redirect' => home_url('/rental-gates/dashboard/billing?cancelled=1'),
        ));
    }

    /**
     * Get billing usage data via AJAX
     */
    public function handle_get_billing_usage()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
        }

        $feature_gate = rg_feature_gate();
        $plan = $feature_gate->get_org_plan();
        $usage = $feature_gate->get_all_usage();
        $modules = $feature_gate->get_all_modules();

        wp_send_json_success(array(
            'plan' => array(
                'id' => $plan['id'] ?? 'free',
                'name' => $plan['name'] ?? 'Free',
                'price_monthly' => $plan['price_monthly'] ?? 0,
                'is_free' => !empty($plan['is_free']),
            ),
            'usage' => $usage,
            'modules' => $modules,
        ));
    }

    /**
     * Create Stripe Payment Intent for subscription checkout
     */
    public function handle_create_subscription_intent()
    {
        check_ajax_referer('rental_gates_subscription_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
        }

        $subscription_id = intval($_POST['subscription_id'] ?? 0);
        $plan_slug = sanitize_text_field($_POST['plan'] ?? '');
        $billing_cycle = sanitize_text_field($_POST['billing'] ?? 'monthly');

        if (!$subscription_id || !$plan_slug) {
            wp_send_json_error(array('message' => __('Invalid subscription data', 'rental-gates')));
        }

        // Get user's organization
        $user_id = get_current_user_id();
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $org_member = $wpdb->get_row($wpdb->prepare(
            "SELECT organization_id FROM {$tables['organization_members']} WHERE user_id = %d AND is_primary = 1",
            $user_id
        ));

        if (!$org_member) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        // Verify subscription belongs to this organization
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['subscriptions']} WHERE id = %d AND organization_id = %d",
            $subscription_id,
            $org_member->organization_id
        ), ARRAY_A);

        if (!$subscription) {
            wp_send_json_error(array('message' => __('Subscription not found', 'rental-gates')));
        }

        // Check Stripe configuration
        if (!Rental_Gates_Stripe::is_configured()) {
            wp_send_json_error(array('message' => __('Payment processing not configured. Please contact support.', 'rental-gates')));
        }

        // Plan prices
        $plan_prices = array(
            'starter' => array('monthly' => 19, 'yearly' => 180),
            'professional' => array('monthly' => 49, 'yearly' => 468),
            'enterprise' => array('monthly' => 149, 'yearly' => 1428),
        );

        if (!isset($plan_prices[$plan_slug])) {
            wp_send_json_error(array('message' => __('Invalid plan selected', 'rental-gates')));
        }

        $amount = $billing_cycle === 'yearly'
            ? $plan_prices[$plan_slug]['yearly']
            : $plan_prices[$plan_slug]['monthly'];

        $amount_cents = $amount * 100;

        // Get or create Stripe customer
        $customer_id = Rental_Gates_Stripe::get_or_create_customer($user_id);
        if (is_wp_error($customer_id)) {
            wp_send_json_error(array('message' => $customer_id->get_error_message()));
        }

        // Create payment intent
        $intent_data = array(
            'amount' => $amount_cents,
            'currency' => 'usd',
            'customer' => $customer_id,
            'metadata' => array(
                'subscription_id' => $subscription_id,
                'organization_id' => $org_member->organization_id,
                'plan_slug' => $plan_slug,
                'billing_cycle' => $billing_cycle,
                'type' => 'subscription_payment',
            ),
            'automatic_payment_methods' => array(
                'enabled' => 'true',
            ),
        );

        $payment_intent = Rental_Gates_Stripe::api_request('payment_intents', 'POST', $intent_data);

        if (is_wp_error($payment_intent)) {
            wp_send_json_error(array('message' => $payment_intent->get_error_message()));
        }

        // Store payment intent ID in subscription
        $wpdb->update(
            $tables['subscriptions'],
            array(
                'meta_data' => json_encode(array_merge(
                    json_decode($subscription['meta_data'] ?? '{}', true) ?: array(),
                    array('payment_intent_id' => $payment_intent['id'])
                )),
            ),
            array('id' => $subscription_id)
        );

        wp_send_json_success(array(
            'client_secret' => $payment_intent['client_secret'],
            'payment_intent_id' => $payment_intent['id'],
        ));
    }

    /**
     * Activate subscription after successful payment
     */
    public function handle_activate_subscription_payment()
    {
        check_ajax_referer('rental_gates_subscription_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in', 'rental-gates')));
        }

        $subscription_id = intval($_POST['subscription_id'] ?? 0);
        $payment_intent_id = sanitize_text_field($_POST['payment_intent_id'] ?? '');

        if (!$subscription_id || !$payment_intent_id) {
            wp_send_json_error(array('message' => __('Invalid payment data', 'rental-gates')));
        }

        // Get user's organization
        $user_id = get_current_user_id();
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $org_member = $wpdb->get_row($wpdb->prepare(
            "SELECT organization_id FROM {$tables['organization_members']} WHERE user_id = %d AND is_primary = 1",
            $user_id
        ));

        if (!$org_member) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        // Verify subscription belongs to this organization
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['subscriptions']} WHERE id = %d AND organization_id = %d",
            $subscription_id,
            $org_member->organization_id
        ), ARRAY_A);

        if (!$subscription) {
            wp_send_json_error(array('message' => __('Subscription not found', 'rental-gates')));
        }

        // Verify payment intent status with Stripe
        if (Rental_Gates_Stripe::is_configured()) {
            $payment_intent = Rental_Gates_Stripe::get_payment_intent($payment_intent_id);

            if (is_wp_error($payment_intent)) {
                wp_send_json_error(array('message' => __('Could not verify payment', 'rental-gates')));
            }

            if ($payment_intent['status'] !== 'succeeded') {
                wp_send_json_error(array('message' => __('Payment has not been completed', 'rental-gates')));
            }
        }

        // Calculate subscription period
        $now = current_time('mysql');
        $billing_cycle = $subscription['billing_cycle'] ?? 'monthly';

        if ($billing_cycle === 'yearly') {
            $period_end = date('Y-m-d H:i:s', strtotime('+1 year'));
        } else {
            $period_end = date('Y-m-d H:i:s', strtotime('+1 month'));
        }

        // Activate subscription
        $wpdb->update(
            $tables['subscriptions'],
            array(
                'status' => 'active',
                'current_period_start' => $now,
                'current_period_end' => $period_end,
                'stripe_customer_id' => $payment_intent['customer'] ?? null,
                'meta_data' => json_encode(array_merge(
                    json_decode($subscription['meta_data'] ?? '{}', true) ?: array(),
                    array(
                        'payment_intent_id' => $payment_intent_id,
                        'activated_at' => $now,
                    )
                )),
            ),
            array('id' => $subscription_id)
        );

        // Update organization plan
        $wpdb->update(
            $tables['organizations'],
            array('plan_id' => $subscription['plan_slug']),
            array('id' => $org_member->organization_id)
        );

        // Create invoice record
        $invoice_number = 'INV-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $wpdb->insert($tables['invoices'], array(
            'organization_id' => $org_member->organization_id,
            'subscription_id' => $subscription_id,
            'invoice_number' => $invoice_number,
            'amount' => $subscription['amount'],
            'tax' => 0,
            'total' => $subscription['amount'],
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => $now,
            'created_at' => $now,
        ));

        // Send confirmation email
        if (class_exists('Rental_Gates_Email')) {
            $user = get_user_by('ID', $user_id);
            Rental_Gates_Email::send_subscription_activated($user, $subscription);
        }

        wp_send_json_success(array(
            'message' => __('Subscription activated successfully!', 'rental-gates'),
            'redirect' => home_url('/rental-gates/dashboard'),
        ));
    }

    /**
     * Handle AI credit pack purchase
     */
    public function handle_ai_credit_purchase()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_credit_purchase')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'rental-gates')));
        }

        // Check user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in to purchase credits.', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found.', 'rental-gates')));
        }

        // Get pack details
        $pack_id = sanitize_key($_POST['pack_id'] ?? '');
        if (!$pack_id) {
            wp_send_json_error(array('message' => __('Invalid credit pack.', 'rental-gates')));
        }

        if (!class_exists('Rental_Gates_AI_Credits')) {
            wp_send_json_error(array('message' => __('AI Credits module not available.', 'rental-gates')));
        }

        $credits_manager = rg_ai_credits();
        $result = $credits_manager->create_purchase($org_id, $pack_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handle admin credit adjustment
     */
    public function handle_admin_credit_adjustment()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'admin_credit_adjustment')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'rental-gates')));
        }

        // Check admin permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'rental-gates')));
        }

        $org_id = intval($_POST['org_id'] ?? 0);
        $amount = intval($_POST['amount'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        $credit_type = sanitize_key($_POST['credit_type'] ?? 'bonus');
        $action = sanitize_key($_POST['adjustment_action'] ?? 'add');

        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found.', 'rental-gates')));
        }

        if (!$amount) {
            wp_send_json_error(array('message' => __('Invalid amount.', 'rental-gates')));
        }

        if (!class_exists('Rental_Gates_AI_Credits')) {
            wp_send_json_error(array('message' => __('AI Credits module not available.', 'rental-gates')));
        }

        $credits_manager = rg_ai_credits();

        // Make amount negative for deductions
        if ($action === 'deduct') {
            $amount = -abs($amount);
        }

        $result = $credits_manager->admin_adjust($org_id, $amount, $reason, $credit_type, get_current_user_id());

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Get updated balance
        $balance = $credits_manager->get_balance($org_id);

        wp_send_json_success(array(
            'message' => __('Credits adjusted successfully.', 'rental-gates'),
            'balance' => $balance,
        ));
    }

    /**
     * Handle AI content generation via AJAX
     * Unified endpoint for all AI tools
     */
    public function handle_ai_generate()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rg_ai_generate')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'rental-gates')));
        }

        // Check user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in to use AI tools.', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $user_id = get_current_user_id();

        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found.', 'rental-gates')));
        }

        // Check AI access
        if (!class_exists('Rental_Gates_AI')) {
            wp_send_json_error(array('message' => __('AI module not available.', 'rental-gates')));
        }

        if (!Rental_Gates_AI::org_has_access($org_id)) {
            wp_send_json_error(array('message' => __('Your plan does not include AI tools.', 'rental-gates')));
        }

        $ai = rental_gates_ai();
        if (!$ai->is_configured()) {
            wp_send_json_error(array('message' => __('AI provider not configured.', 'rental-gates')));
        }

        // Get tool and generation ID (for idempotency)
        $tool = sanitize_key($_POST['tool'] ?? '');
        $generation_id = sanitize_text_field($_POST['generation_id'] ?? '');

        if (!$tool) {
            wp_send_json_error(array('message' => __('Invalid tool specified.', 'rental-gates')));
        }

        // Check for duplicate generation (idempotency)
        $idempotency_key = 'ai_gen_' . $org_id . '_' . $generation_id;
        $cached_result = get_transient($idempotency_key);
        if ($cached_result !== false) {
            // Return cached result to prevent double-charges
            wp_send_json_success($cached_result);
        }

        // Get entity data if provided
        $entity_id = intval($_POST['entity_id'] ?? 0);
        $entity_type = sanitize_key($_POST['entity_type'] ?? '');
        $entity_data = array();

        if ($entity_id && $entity_type) {
            global $wpdb;
            $tables = Rental_Gates_Database::get_table_names();

            if ($entity_type === 'unit') {
                $entity_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT u.*, b.name as building_name, b.address as building_address
                     FROM {$tables['units']} u
                     JOIN {$tables['buildings']} b ON u.building_id = b.id
                     WHERE u.id = %d AND b.organization_id = %d",
                    $entity_id,
                    $org_id
                ), ARRAY_A) ?: array();
            } elseif ($entity_type === 'building') {
                $entity_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$tables['buildings']} WHERE id = %d AND organization_id = %d",
                    $entity_id,
                    $org_id
                ), ARRAY_A) ?: array();
            }
        }

        // Execute tool-specific generation
        $result = null;

        switch ($tool) {
            case 'description':
                $result = $ai->generate_description(array(
                    'name' => sanitize_text_field($_POST['property_name'] ?? $entity_data['name'] ?? ''),
                    'unit_type' => sanitize_text_field($_POST['unit_type'] ?? 'apartment'),
                    'bedrooms' => intval($_POST['bedrooms'] ?? $entity_data['bedrooms'] ?? 0),
                    'bathrooms' => floatval($_POST['bathrooms'] ?? $entity_data['bathrooms'] ?? 0),
                    'sqft' => intval($_POST['sqft'] ?? $entity_data['sqft'] ?? 0),
                    'rent' => floatval($_POST['rent'] ?? $entity_data['rent'] ?? 0),
                    'address' => sanitize_text_field($_POST['address'] ?? $entity_data['building_address'] ?? ''),
                    'features' => sanitize_textarea_field($_POST['features'] ?? $entity_data['amenities'] ?? ''),
                    'style' => sanitize_key($_POST['style'] ?? 'professional'),
                ), $org_id, $user_id);
                break;

            case 'marketing':
                $result = $ai->generate_marketing(array(
                    'name' => sanitize_text_field($_POST['property_name'] ?? $entity_data['name'] ?? ''),
                    'rent' => floatval($_POST['rent'] ?? $entity_data['rent'] ?? 0),
                    'bedrooms' => intval($_POST['bedrooms'] ?? $entity_data['bedrooms'] ?? 0),
                    'address' => sanitize_text_field($_POST['address'] ?? $entity_data['building_address'] ?? ''),
                    'highlights' => sanitize_textarea_field($_POST['highlights'] ?? ''),
                    'format' => sanitize_key($_POST['format'] ?? 'social'),
                ), $org_id, $user_id);
                break;

            case 'maintenance':
                $result = $ai->triage_maintenance(array(
                    'title' => sanitize_text_field($_POST['title'] ?? ''),
                    'description' => sanitize_textarea_field($_POST['description'] ?? ''),
                    'location' => sanitize_text_field($_POST['location'] ?? ''),
                ), $org_id, $user_id);
                break;

            case 'message':
                $result = $ai->draft_message(array(
                    'type' => sanitize_key($_POST['message_type'] ?? 'general'),
                    'tone' => sanitize_key($_POST['tone'] ?? 'professional'),
                    'tenant_name' => sanitize_text_field($_POST['tenant_name'] ?? ''),
                    'property' => sanitize_text_field($_POST['property'] ?? $entity_data['name'] ?? ''),
                    'context' => sanitize_textarea_field($_POST['context'] ?? ''),
                    'specific_details' => sanitize_textarea_field($_POST['specific_details'] ?? ''),
                ), $org_id, $user_id);
                break;

            case 'insights':
            case 'availability':
                $result = $ai->get_portfolio_insights($org_id, $user_id);
                break;

            default:
                wp_send_json_error(array('message' => __('Unknown tool.', 'rental-gates')));
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Normalize the result
        $response = array(
            'content' => $result['description'] ?? $result['marketing'] ?? $result['analysis'] ?? $result['message'] ?? $result['insights'] ?? '',
            'credits_used' => $result['credits_used'] ?? 1,
            'credits_remaining' => $result['credits_remaining'] ?? Rental_Gates_AI::get_remaining_credits($org_id),
            'tool' => $tool,
            'entity_id' => $entity_id,
            'entity_type' => $entity_type,
            'generated_at' => current_time('mysql'),
        );

        // Merge all result fields
        $response = array_merge($result, $response);

        // Cache the result for idempotency (5 minutes)
        if ($generation_id) {
            set_transient($idempotency_key, $response, 5 * MINUTE_IN_SECONDS);
        }

        wp_send_json_success($response);
    }

    /**
     * Handle contact form submission
     */
    public function handle_contact_form()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_contact')) {
            wp_send_json_error(__('Security check failed. Please refresh and try again.', 'rental-gates'));
        }

        // Sanitize inputs
        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $company = sanitize_text_field($_POST['company'] ?? '');
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');

        // Validate required fields
        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            wp_send_json_error(__('Please fill in all required fields.', 'rental-gates'));
        }

        if (!is_email($email)) {
            wp_send_json_error(__('Please enter a valid email address.', 'rental-gates'));
        }

        // Rate limiting - simple implementation using transients
        $rate_key = 'rg_contact_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        $rate_count = get_transient($rate_key);
        if ($rate_count && $rate_count >= 5) {
            wp_send_json_error(__('Too many requests. Please try again later.', 'rental-gates'));
        }
        set_transient($rate_key, ($rate_count ? $rate_count + 1 : 1), HOUR_IN_SECONDS);

        // Subject labels
        $subject_labels = array(
            'sales' => __('Sales Inquiry', 'rental-gates'),
            'support' => __('Technical Support', 'rental-gates'),
            'billing' => __('Billing Question', 'rental-gates'),
            'partnership' => __('Partnership Opportunity', 'rental-gates'),
            'feedback' => __('Product Feedback', 'rental-gates'),
            'other' => __('Other', 'rental-gates'),
        );
        $subject_label = $subject_labels[$subject] ?? $subject;

        // Build email
        $platform_name = get_option('rental_gates_platform_name', 'Rental Gates');
        $admin_email = get_option('rental_gates_support_email', get_option('admin_email'));

        $email_subject = sprintf('[%s] %s: %s', $platform_name, $subject_label, $name);

        $email_body = sprintf(
            "New contact form submission\n\n" .
            "Name: %s\n" .
            "Email: %s\n" .
            "Phone: %s\n" .
            "Company: %s\n" .
            "Subject: %s\n\n" .
            "Message:\n%s\n\n" .
            "---\n" .
            "IP Address: %s\n" .
            "Submitted: %s",
            $name,
            $email,
            $phone ?: 'Not provided',
            $company ?: 'Not provided',
            $subject_label,
            $message,
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            current_time('mysql')
        );

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $name . ' <' . $email . '>',
        );

        // Send email
        $sent = wp_mail($admin_email, $email_subject, $email_body, $headers);

        if ($sent) {
            // Log submission
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[Rental Gates] Contact form submitted by %s <%s> - Subject: %s', $name, $email, $subject_label));
            }

            wp_send_json_success(__('Thank you! Your message has been sent successfully.', 'rental-gates'));
        } else {
            wp_send_json_error(__('Failed to send message. Please try again or email us directly.', 'rental-gates'));
        }
    }
}

/**
 * Returns the main instance of Rental_Gates
 */
function rental_gates()
{
    return Rental_Gates::instance();
}

// Initialize
rental_gates();
