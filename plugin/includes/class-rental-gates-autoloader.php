<?php
/**
 * Class-Map Autoloader
 *
 * Replaces 64 hardcoded require_once calls with on-demand loading.
 * Uses an explicit class map (WordPress naming doesn't follow PSR-4).
 *
 * @package RentalGates
 * @since 2.42.0
 */

if (!defined('ABSPATH')) exit;

class Rental_Gates_Autoloader {

    /**
     * Class name => file path mapping (relative to plugin root).
     */
    private static $class_map = array(
        // Core
        'Rental_Gates_Database'             => 'includes/class-rental-gates-database.php',
        'Rental_Gates_Roles'                => 'includes/class-rental-gates-roles.php',
        'Rental_Gates_Activator'            => 'includes/class-rental-gates-activator.php',
        'Rental_Gates_Deactivator'          => 'includes/class-rental-gates-deactivator.php',

        // Logging & Monitoring
        'Rental_Gates_Logger'               => 'includes/class-rental-gates-logger.php',
        'Rental_Gates_Health'               => 'includes/class-rental-gates-health.php',

        // Security & Performance
        'Rental_Gates_Security'             => 'includes/class-rental-gates-security.php',
        'Rental_Gates_Cache'                => 'includes/class-rental-gates-cache.php',
        'Rental_Gates_Rate_Limit'           => 'includes/class-rental-gates-rate-limit.php',
        'Rental_Gates_Feature_Gate'         => 'includes/class-rental-gates-feature-gate.php',
        'Rental_Gates_Pricing'              => 'includes/class-rental-gates-pricing.php',

        // Maps
        'Rental_Gates_Map_Service'          => 'includes/maps/class-rental-gates-map-service.php',
        'Rental_Gates_Google_Maps'          => 'includes/maps/class-rental-gates-google-maps.php',
        'Rental_Gates_OpenStreetMap'        => 'includes/maps/class-rental-gates-openstreetmap.php',

        // Base Model (new in Phase 2)
        'Rental_Gates_Base_Model'           => 'includes/models/class-rental-gates-base-model.php',

        // Models (22 files)
        'Rental_Gates_Organization'         => 'includes/models/class-rental-gates-organization.php',
        'Rental_Gates_Building'             => 'includes/models/class-rental-gates-building.php',
        'Rental_Gates_Unit'                 => 'includes/models/class-rental-gates-unit.php',
        'Rental_Gates_Tenant'               => 'includes/models/class-rental-gates-tenant.php',
        'Rental_Gates_Lease'                => 'includes/models/class-rental-gates-lease.php',
        'Rental_Gates_Application'          => 'includes/models/class-rental-gates-application.php',
        'Rental_Gates_Lead'                 => 'includes/models/class-rental-gates-lead.php',
        'Rental_Gates_Lead_Scoring'         => 'includes/models/class-rental-gates-lead-scoring.php',
        'Rental_Gates_Marketing_Conversion' => 'includes/models/class-rental-gates-marketing-conversion.php',
        'Rental_Gates_Campaign'             => 'includes/models/class-rental-gates-campaign.php',
        'Rental_Gates_Maintenance'          => 'includes/models/class-rental-gates-maintenance.php',
        'Rental_Gates_Vendor'               => 'includes/models/class-rental-gates-vendor.php',
        'Rental_Gates_Payment'              => 'includes/models/class-rental-gates-payment.php',
        'Rental_Gates_Invoice'              => 'includes/models/class-rental-gates-invoice.php',
        'Rental_Gates_Plan'                 => 'includes/models/class-rental-gates-plan.php',
        'Rental_Gates_Subscription'         => 'includes/models/class-rental-gates-subscription.php',
        'Rental_Gates_Document'             => 'includes/models/class-rental-gates-document.php',
        'Rental_Gates_Notification'         => 'includes/models/class-rental-gates-notification.php',
        'Rental_Gates_Announcement'         => 'includes/models/class-rental-gates-announcement.php',
        'Rental_Gates_Message'              => 'includes/models/class-rental-gates-message.php',
        'Rental_Gates_AI_Usage'             => 'includes/models/class-rental-gates-ai-usage.php',
        'Rental_Gates_Flyer'                => 'includes/models/class-rental-gates-flyer.php',

        // Services (new in Phase 2)
        'Rental_Gates_Service_Buildings'    => 'includes/services/class-rental-gates-service-buildings.php',
        'Rental_Gates_Service_Payments'     => 'includes/services/class-rental-gates-service-payments.php',
        'Rental_Gates_Service_Maintenance'  => 'includes/services/class-rental-gates-service-maintenance.php',

        // Services (existing)
        'Rental_Gates_Email'                => 'includes/class-rental-gates-email.php',
        'Rental_Gates_PDF'                  => 'includes/class-rental-gates-pdf.php',
        'Rental_Gates_Stripe'               => 'includes/class-rental-gates-stripe.php',
        'Rental_Gates_AI'                   => 'includes/class-rental-gates-ai.php',
        'Rental_Gates_AI_Credits'           => 'includes/class-rental-gates-ai-credits.php',
        'Rental_Gates_Image_Optimizer'      => 'includes/class-rental-gates-image-optimizer.php',
        'Rental_Gates_Analytics'            => 'includes/class-rental-gates-analytics.php',

        // API
        'Rental_Gates_REST_API'             => 'includes/api/class-rental-gates-rest-api.php',
        'Rental_Gates_Form_Helper'          => 'includes/api/class-rental-gates-form-helper.php',

        // AJAX handler classes (new in Phase 2)
        'Rental_Gates_Ajax_Buildings'       => 'includes/ajax/class-rental-gates-ajax-buildings.php',
        'Rental_Gates_Ajax_Tenants'         => 'includes/ajax/class-rental-gates-ajax-tenants.php',
        'Rental_Gates_Ajax_Leases'          => 'includes/ajax/class-rental-gates-ajax-leases.php',
        'Rental_Gates_Ajax_Applications'    => 'includes/ajax/class-rental-gates-ajax-applications.php',
        'Rental_Gates_Ajax_Payments'        => 'includes/ajax/class-rental-gates-ajax-payments.php',
        'Rental_Gates_Ajax_Maintenance'     => 'includes/ajax/class-rental-gates-ajax-maintenance.php',
        'Rental_Gates_Ajax_Vendors'         => 'includes/ajax/class-rental-gates-ajax-vendors.php',
        'Rental_Gates_Ajax_Messages'        => 'includes/ajax/class-rental-gates-ajax-messages.php',
        'Rental_Gates_Ajax_Leads'           => 'includes/ajax/class-rental-gates-ajax-leads.php',
        'Rental_Gates_Ajax_Documents'       => 'includes/ajax/class-rental-gates-ajax-documents.php',
        'Rental_Gates_Ajax_Stripe'          => 'includes/ajax/class-rental-gates-ajax-stripe.php',
        'Rental_Gates_Ajax_Marketing'       => 'includes/ajax/class-rental-gates-ajax-marketing.php',
        'Rental_Gates_Ajax_Portals'         => 'includes/ajax/class-rental-gates-ajax-portals.php',
        'Rental_Gates_Ajax_AI'              => 'includes/ajax/class-rental-gates-ajax-ai.php',
        'Rental_Gates_Ajax_Public'          => 'includes/ajax/class-rental-gates-ajax-public.php',

        // Automation
        'Rental_Gates_Automation'           => 'includes/automation/class-rental-gates-automation.php',
        'Rental_Gates_Availability_Engine'  => 'includes/automation/class-rental-gates-availability-engine.php',
        'Rental_Gates_Marketing_Automation' => 'includes/automation/class-rental-gates-marketing-automation.php',

        // Subscription
        'Rental_Gates_Plans'                => 'includes/subscription/class-rental-gates-plans.php',
        'Rental_Gates_Billing'              => 'includes/subscription/class-rental-gates-billing.php',
        'Rental_Gates_Webhook_Handler'      => 'includes/subscription/class-rental-gates-webhook-handler.php',
        'Rental_Gates_Subscription_Invoice' => 'includes/subscription/class-rental-gates-subscription-invoice.php',

        // Other
        'Rental_Gates_Public'               => 'includes/public/class-rental-gates-public.php',
        'Rental_Gates_QR'                   => 'includes/public/class-rental-gates-qr.php',
        'Rental_Gates_Shortcodes'           => 'includes/class-rental-gates-shortcodes.php',
        'Rental_Gates_Dashboard'            => 'includes/dashboard/class-rental-gates-dashboard.php',
        'Rental_Gates_Admin'                => 'includes/admin/class-rental-gates-admin.php',
        'Rental_Gates_Auth'                 => 'includes/class-rental-gates-auth.php',
        'Rental_Gates_User_Restrictions'    => 'includes/class-rental-gates-user-restrictions.php',
        'Rental_Gates_PWA'                  => 'includes/class-rental-gates-pwa.php',
        'Rental_Gates_Enqueue'              => 'includes/class-rental-gates-enqueue.php',
        'Rental_Gates_Routing'              => 'includes/class-rental-gates-routing.php',
        'Rental_Gates_Tests'                => 'includes/class-rental-gates-tests.php',

        // Integrations
        'Rental_Gates_Email_Marketing'      => 'includes/integrations/class-rental-gates-email-marketing.php',
        'Rental_Gates_Social_Media'         => 'includes/integrations/class-rental-gates-social-media.php',

        // NOTE: Rental_Gates_Loader and Rental_Gates_Validation intentionally
        // excluded - they are dead code removed in Phase 2 WS-7.
    );

    /**
     * Register the autoloader with spl_autoload.
     */
    public static function register() {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }

    /**
     * Autoload a class by name.
     *
     * @param string $class_name Fully qualified class name
     */
    public static function autoload($class_name) {
        if (isset(self::$class_map[$class_name])) {
            $file = RENTAL_GATES_PLUGIN_DIR . self::$class_map[$class_name];
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    /**
     * Get the class map (for debugging/testing).
     *
     * @return array
     */
    public static function get_class_map() {
        return self::$class_map;
    }
}
