<?php
/**
 * Rental Gates REST API Class
 * Complete REST API with all model endpoints wired
 * 
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rental_Gates_REST_API
{

    /**
     * API Namespace
     */
    const NAMESPACE = 'rental-gates/v1';

    /**
     * Constructor
     */
    public function __construct()
    {
        add_filter('rest_pre_dispatch', array('Rental_Gates_Rate_Limit', 'middleware'), 10, 3);
    }

    /**
     * Register all routes
     */
    public function register_routes()
    {
        $this->register_auth_routes();
        $this->register_organization_routes();
        $this->register_building_routes();
        $this->register_unit_routes();
        $this->register_tenant_routes();
        $this->register_lease_routes();
        $this->register_application_routes();
        $this->register_maintenance_routes();
        $this->register_vendor_routes();
        $this->register_payment_routes();
        $this->register_document_routes();
        $this->register_message_routes();
        $this->register_announcement_routes();
        $this->register_notification_routes();
        $this->register_lead_routes();
        $this->register_qr_routes();
        $this->register_pdf_routes();
        $this->register_public_routes();
        $this->register_search_routes();
        $this->register_profile_routes();
        $this->register_settings_routes();
        $this->register_report_routes();
        $this->register_ai_routes();
        $this->register_test_routes();
        $this->register_webhook_routes();
    }

    // ==========================================
    // ROUTE REGISTRATION
    // ==========================================

    private function register_webhook_routes()
    {
        register_rest_route(self::NAMESPACE , '/stripe/webhook', array(
            'methods' => 'POST',
            'callback' => array('Rental_Gates_Webhook_Handler', 'handle_request'),
            'permission_callback' => '__return_true', // Stripe sends without auth headers usually
        ));
    }

    private function register_auth_routes()
    {
        register_rest_route(self::NAMESPACE , '/auth/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_login'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE , '/auth/register', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_register'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE , '/auth/logout', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_logout'),
            'permission_callback' => array($this, 'check_authenticated'),
        ));

        register_rest_route(self::NAMESPACE , '/auth/me', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_current_user'),
            'permission_callback' => array($this, 'check_authenticated'),
        ));

        register_rest_route(self::NAMESPACE , '/auth/password/reset', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_password_reset'),
            'permission_callback' => '__return_true',
        ));
    }

    private function register_organization_routes()
    {
        register_rest_route(self::NAMESPACE , '/organizations', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_organizations'),
                'permission_callback' => array($this, 'check_site_admin'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_organization'),
                'permission_callback' => array($this, 'check_authenticated'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/organizations/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_organization'),
                'permission_callback' => array($this, 'check_org_access'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_organization'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_organization'),
                'permission_callback' => array($this, 'check_site_admin'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/organizations/(?P<id>\d+)/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_organization_stats'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        register_rest_route(self::NAMESPACE , '/organizations/(?P<id>\d+)/members', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_organization_members'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'add_organization_member'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
        ));
    }

    private function register_building_routes()
    {
        register_rest_route(self::NAMESPACE , '/buildings', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_buildings'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_building'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/buildings/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_building'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_building'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_building'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/buildings/(?P<id>\d+)/units', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_building_units'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));
    }

    private function register_unit_routes()
    {
        register_rest_route(self::NAMESPACE , '/units', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_units'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_unit'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/units/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_unit'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_unit'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_unit'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/units/(?P<id>\d+)/availability', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_unit_availability'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));
    }

    private function register_tenant_routes()
    {
        register_rest_route(self::NAMESPACE , '/tenants', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_tenants'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_tenant'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/tenants/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_tenant'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_tenant'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_tenant'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/tenants/(?P<id>\d+)/invite', array(
            'methods' => 'POST',
            'callback' => array($this, 'invite_tenant_to_portal'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        register_rest_route(self::NAMESPACE , '/tenants/(?P<id>\d+)/leases', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_tenant_leases'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));
    }

    private function register_lease_routes()
    {
        register_rest_route(self::NAMESPACE , '/leases', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_leases'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_lease'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/leases/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_lease'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_lease'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_lease'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/leases/(?P<id>\d+)/activate', array(
            'methods' => 'POST',
            'callback' => array($this, 'activate_lease'),
            'permission_callback' => array($this, 'check_owner_permission'),
        ));

        register_rest_route(self::NAMESPACE , '/leases/(?P<id>\d+)/terminate', array(
            'methods' => 'POST',
            'callback' => array($this, 'terminate_lease'),
            'permission_callback' => array($this, 'check_owner_permission'),
        ));

        register_rest_route(self::NAMESPACE , '/leases/(?P<id>\d+)/renew', array(
            'methods' => 'POST',
            'callback' => array($this, 'renew_lease'),
            'permission_callback' => array($this, 'check_owner_permission'),
        ));
    }

    private function register_application_routes()
    {
        register_rest_route(self::NAMESPACE , '/applications', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_applications'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_application'),
                'permission_callback' => '__return_true',
            ),
        ));

        register_rest_route(self::NAMESPACE , '/applications/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_application'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_application'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/applications/(?P<id>\d+)/approve', array(
            'methods' => 'POST',
            'callback' => array($this, 'approve_application'),
            'permission_callback' => array($this, 'check_owner_permission'),
        ));

        register_rest_route(self::NAMESPACE , '/applications/(?P<id>\d+)/decline', array(
            'methods' => 'POST',
            'callback' => array($this, 'decline_application'),
            'permission_callback' => array($this, 'check_owner_permission'),
        ));
    }

    private function register_maintenance_routes()
    {
        register_rest_route(self::NAMESPACE , '/work-orders', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_work_orders'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_work_order'),
                'permission_callback' => array($this, 'check_authenticated'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/work-orders/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_work_order'),
                'permission_callback' => array($this, 'check_authenticated'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_work_order'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_work_order'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/work-orders/(?P<id>\d+)/notes', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_work_order_notes'),
                'permission_callback' => array($this, 'check_authenticated'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'add_work_order_note'),
                'permission_callback' => array($this, 'check_authenticated'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/work-orders/(?P<id>\d+)/assign', array(
            'methods' => 'POST',
            'callback' => array($this, 'assign_work_order_vendor'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));
    }

    private function register_vendor_routes()
    {
        register_rest_route(self::NAMESPACE , '/vendors', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_vendors'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_vendor'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/vendors/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_vendor'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_vendor'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_vendor'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/vendors/(?P<id>\d+)/invite', array(
            'methods' => 'POST',
            'callback' => array($this, 'invite_vendor_to_portal'),
            'permission_callback' => array($this, 'check_owner_permission'),
        ));
    }

    private function register_payment_routes()
    {
        register_rest_route(self::NAMESPACE , '/payments', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_payments'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_payment'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/payments/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_payment'),
                'permission_callback' => array($this, 'check_authenticated'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_payment'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/payments/(?P<id>\d+)/refund', array(
            'methods' => 'POST',
            'callback' => array($this, 'refund_payment'),
            'permission_callback' => array($this, 'check_owner_permission'),
        ));

        register_rest_route(self::NAMESPACE , '/payments/checkout', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_checkout_session'),
            'permission_callback' => array($this, 'check_authenticated'),
        ));
    }

    private function register_document_routes()
    {
        register_rest_route(self::NAMESPACE , '/documents', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_documents'),
                'permission_callback' => array($this, 'check_authenticated'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_document'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/documents/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_document'),
                'permission_callback' => array($this, 'check_authenticated'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_document'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_document'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
        ));
    }

    private function register_message_routes()
    {
        register_rest_route(self::NAMESPACE , '/messages', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_messages'),
                'permission_callback' => array($this, 'check_authenticated'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'send_message'),
                'permission_callback' => array($this, 'check_authenticated'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/messages/threads', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_message_threads'),
            'permission_callback' => array($this, 'check_authenticated'),
        ));

        register_rest_route(self::NAMESPACE , '/messages/threads/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_thread_messages'),
            'permission_callback' => array($this, 'check_authenticated'),
        ));

        register_rest_route(self::NAMESPACE , '/messages/(?P<id>\d+)/read', array(
            'methods' => 'POST',
            'callback' => array($this, 'mark_message_read'),
            'permission_callback' => array($this, 'check_authenticated'),
        ));
    }

    private function register_announcement_routes()
    {
        register_rest_route(self::NAMESPACE , '/announcements', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_announcements'),
                'permission_callback' => array($this, 'check_authenticated'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_announcement'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/announcements/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_announcement'),
                'permission_callback' => array($this, 'check_authenticated'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_announcement'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_announcement'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
        ));
    }

    private function register_notification_routes()
    {
        register_rest_route(self::NAMESPACE , '/notifications', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_notifications'),
            'permission_callback' => array($this, 'check_authenticated'),
        ));

        register_rest_route(self::NAMESPACE , '/notifications/unread-count', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_unread_notification_count'),
            'permission_callback' => array($this, 'check_authenticated'),
        ));

        register_rest_route(self::NAMESPACE , '/notifications/(?P<id>\d+)/read', array(
            'methods' => 'POST',
            'callback' => array($this, 'mark_notification_read'),
            'permission_callback' => array($this, 'check_authenticated'),
        ));

        register_rest_route(self::NAMESPACE , '/notifications/mark-all-read', array(
            'methods' => 'POST',
            'callback' => array($this, 'mark_all_notifications_read'),
            'permission_callback' => array($this, 'check_authenticated'),
        ));
    }

    private function register_lead_routes()
    {
        register_rest_route(self::NAMESPACE , '/leads', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_leads'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_lead'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/leads/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_lead_stats'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        register_rest_route(self::NAMESPACE , '/leads/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_lead'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_lead'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_lead'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/leads/(?P<id>\d+)/stage', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_lead_stage'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        register_rest_route(self::NAMESPACE , '/leads/(?P<id>\d+)/note', array(
            'methods' => 'POST',
            'callback' => array($this, 'add_lead_note'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        register_rest_route(self::NAMESPACE , '/leads/(?P<id>\d+)/interests', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_lead_interests'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'add_lead_interest'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/leads/(?P<id>\d+)/convert', array(
            'methods' => 'POST',
            'callback' => array($this, 'convert_lead_to_application'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));
    }

    private function register_qr_routes()
    {
        // List all QR codes
        register_rest_route(self::NAMESPACE , '/qr-codes', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_qr_codes'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_qr_code'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
        ));

        // Generate for building
        register_rest_route(self::NAMESPACE , '/qr-codes/building/(?P<id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_qr_for_building'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Generate for unit
        register_rest_route(self::NAMESPACE , '/qr-codes/unit/(?P<id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_qr_for_unit'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Bulk generate
        register_rest_route(self::NAMESPACE , '/qr-codes/bulk', array(
            'methods' => 'POST',
            'callback' => array($this, 'bulk_generate_qr_codes'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Get single QR code with analytics
        register_rest_route(self::NAMESPACE , '/qr-codes/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_qr_code'),
                'permission_callback' => array($this, 'check_staff_permission'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_qr_code'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
        ));

        // Get QR code analytics
        register_rest_route(self::NAMESPACE , '/qr-codes/(?P<id>\d+)/analytics', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_qr_analytics'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Public scan endpoint (no auth required)
        register_rest_route(self::NAMESPACE , '/qr-codes/scan/(?P<code>[a-zA-Z0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_qr_scan'),
            'permission_callback' => '__return_true',
        ));
    }

    private function register_pdf_routes()
    {
        // Generate lease PDF
        register_rest_route(self::NAMESPACE , '/pdf/lease/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'generate_lease_pdf'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Generate payment receipt PDF
        register_rest_route(self::NAMESPACE , '/pdf/receipt/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'generate_receipt_pdf'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Generate invoice PDF
        register_rest_route(self::NAMESPACE , '/pdf/invoice', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_invoice_pdf'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Generate work order PDF
        register_rest_route(self::NAMESPACE , '/pdf/work-order/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'generate_work_order_pdf'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Generate property report PDF
        register_rest_route(self::NAMESPACE , '/pdf/report', array(
            'methods' => 'GET',
            'callback' => array($this, 'generate_report_pdf'),
            'permission_callback' => array($this, 'check_owner_permission'),
        ));

        // Generate unit flyer PDF
        register_rest_route(self::NAMESPACE , '/pdf/flyer/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'generate_flyer_pdf'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));
    }

    private function register_public_routes()
    {
        register_rest_route(self::NAMESPACE , '/public/map', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_public_map_data'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE , '/public/buildings/(?P<slug>[a-z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_public_building'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE , '/public/units/(?P<building_slug>[a-z0-9-]+)/(?P<unit_slug>[a-z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_public_unit'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE , '/public/inquiry', array(
            'methods' => 'POST',
            'callback' => array($this, 'submit_inquiry'),
            'permission_callback' => '__return_true',
        ));
    }

    private function register_search_routes()
    {
        register_rest_route(self::NAMESPACE , '/search', array(
            'methods' => 'GET',
            'callback' => array($this, 'global_search'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));
    }

    private function register_profile_routes()
    {
        register_rest_route(self::NAMESPACE , '/profile', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_profile'),
                'permission_callback' => array($this, 'check_authenticated'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_profile'),
                'permission_callback' => array($this, 'check_authenticated'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/profile/password', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_password'),
            'permission_callback' => array($this, 'check_authenticated'),
        ));
    }

    private function register_settings_routes()
    {
        register_rest_route(self::NAMESPACE , '/settings', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_settings'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_settings'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
        ));

        register_rest_route(self::NAMESPACE , '/settings/automation', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_automation_settings'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_automation_settings'),
                'permission_callback' => array($this, 'check_owner_permission'),
            ),
        ));
    }

    private function register_report_routes()
    {
        register_rest_route(self::NAMESPACE , '/reports/dashboard', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_dashboard_stats'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        register_rest_route(self::NAMESPACE , '/reports/financial', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_financial_report'),
            'permission_callback' => array($this, 'check_owner_permission'),
        ));

        register_rest_route(self::NAMESPACE , '/reports/occupancy', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_occupancy_report'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));
    }

    private function register_ai_routes()
    {
        // Get AI credits/usage
        register_rest_route(self::NAMESPACE , '/ai/credits', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_ai_credits'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Generate property description
        register_rest_route(self::NAMESPACE , '/ai/generate-description', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_ai_description'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Generate marketing copy
        register_rest_route(self::NAMESPACE , '/ai/generate-marketing', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_ai_marketing'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Triage maintenance request
        register_rest_route(self::NAMESPACE , '/ai/triage-maintenance', array(
            'methods' => 'POST',
            'callback' => array($this, 'triage_maintenance'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Draft message
        register_rest_route(self::NAMESPACE , '/ai/draft-message', array(
            'methods' => 'POST',
            'callback' => array($this, 'draft_ai_message'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Screen applicant
        register_rest_route(self::NAMESPACE , '/ai/screen-applicant', array(
            'methods' => 'POST',
            'callback' => array($this, 'screen_applicant'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Portfolio insights
        register_rest_route(self::NAMESPACE , '/ai/portfolio-insights', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_portfolio_insights'),
            'permission_callback' => array($this, 'check_owner_permission'),
        ));

        // AI usage history
        register_rest_route(self::NAMESPACE , '/ai/history', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_ai_history'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Analyze lease terms
        register_rest_route(self::NAMESPACE , '/ai/analyze-lease', array(
            'methods' => 'POST',
            'callback' => array($this, 'analyze_lease_terms'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Generate email content
        register_rest_route(self::NAMESPACE , '/ai/generate-email', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_ai_email'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));

        // Suggest rent price
        register_rest_route(self::NAMESPACE , '/ai/suggest-rent', array(
            'methods' => 'POST',
            'callback' => array($this, 'suggest_rent_price'),
            'permission_callback' => array($this, 'check_staff_permission'),
        ));
    }

    private function register_test_routes()
    {
        // Run system tests (site admin only)
        register_rest_route(self::NAMESPACE , '/tests/run', array(
            'methods' => 'POST',
            'callback' => array($this, 'run_system_tests'),
            'permission_callback' => array($this, 'check_site_admin'),
        ));

        // Health check
        register_rest_route(self::NAMESPACE , '/tests/health', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_health_check'),
            'permission_callback' => array($this, 'check_site_admin'),
        ));

        // Database integrity check
        register_rest_route(self::NAMESPACE , '/tests/integrity', array(
            'methods' => 'GET',
            'callback' => array($this, 'check_db_integrity'),
            'permission_callback' => array($this, 'check_site_admin'),
        ));
    }

    // ==========================================
    // PERMISSION CALLBACKS
    // ==========================================

    /**
     * @deprecated Use check_authenticated() instead
     */
    public function check_logged_in($request)
    {
        return is_user_logged_in();
    }

    /**
     * Check that user is logged in AND request has valid nonce.
     * Replaces the old check_logged_in which skipped nonce verification.
     */
    public function check_authenticated($request)
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $nonce_check = Rental_Gates_Security::verify_rest_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }

        return true;
    }

    public function check_site_admin($request)
    {
        return Rental_Gates_Roles::is_site_admin();
    }

    public function check_owner_permission($request)
    {
        $nonce_check = Rental_Gates_Security::verify_rest_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        return Rental_Gates_Roles::is_owner_or_manager();
    }

    public function check_staff_permission($request)
    {
        $nonce_check = Rental_Gates_Security::verify_rest_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        return Rental_Gates_Roles::is_owner_or_manager() || Rental_Gates_Roles::is_staff();
    }

    public function check_org_access($request)
    {
        $nonce_check = Rental_Gates_Security::verify_rest_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }

        $org_id = $request->get_param('id');
        $user_org_id = Rental_Gates_Roles::get_organization_id();

        if (Rental_Gates_Roles::is_site_admin()) {
            return true;
        }

        return $user_org_id && $user_org_id == $org_id;
    }

    // ==========================================
    // OWNERSHIP VERIFICATION (IDOR Protection)
    // ==========================================

    /**
     * Verify that an entity belongs to the current user's organization.
     *
     * @param string $entity_type  Model table key (e.g., 'tenants', 'buildings')
     * @param int    $entity_id    The entity's primary key
     * @param string $org_column   Column name for organization_id (default: 'organization_id')
     * @return true|WP_REST_Response True if owned, error response if not
     */
    private function verify_org_ownership($entity_type, $entity_id, $org_column = 'organization_id')
    {
        // Site admins can access any organization's data
        if (Rental_Gates_Roles::is_site_admin()) {
            return true;
        }

        $user_org_id = $this->get_org_id();
        if (!$user_org_id) {
            return self::error(
                __('Organization context required', 'rental-gates'),
                'no_organization',
                403
            );
        }

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        if (!isset($tables[$entity_type])) {
            return self::error(
                __('Invalid entity type', 'rental-gates'),
                'invalid_entity',
                500
            );
        }

        $entity_org_id = $wpdb->get_var($wpdb->prepare(
            "SELECT {$org_column} FROM {$tables[$entity_type]} WHERE id = %d",
            $entity_id
        ));

        if ($entity_org_id === null) {
            return self::error(
                __('Not found', 'rental-gates'),
                'not_found',
                404
            );
        }

        if ((int) $entity_org_id !== (int) $user_org_id) {
            Rental_Gates_Security::log_security_event('idor_attempt', array(
                'entity_type' => $entity_type,
                'entity_id'   => $entity_id,
                'entity_org'  => $entity_org_id,
                'user_org'    => $user_org_id,
            ));

            // Return 404 (not 403) to avoid confirming the entity exists
            return self::error(
                __('Not found', 'rental-gates'),
                'not_found',
                404
            );
        }

        return true;
    }

    /**
     * Verify ownership for entities that reference org through a parent.
     * E.g., a unit belongs to a building which belongs to an org.
     *
     * @param string $entity_type   Child table key (e.g., 'units')
     * @param int    $entity_id     Child entity ID
     * @param string $parent_type   Parent table key (e.g., 'buildings')
     * @param string $parent_fk     Foreign key column in child (e.g., 'building_id')
     * @return true|WP_REST_Response
     */
    private function verify_org_ownership_via_parent($entity_type, $entity_id, $parent_type, $parent_fk)
    {
        if (Rental_Gates_Roles::is_site_admin()) {
            return true;
        }

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $user_org_id = $this->get_org_id();
        if (!$user_org_id) {
            return self::error(__('Organization context required', 'rental-gates'), 'no_organization', 403);
        }

        $entity_org_id = $wpdb->get_var($wpdb->prepare(
            "SELECT p.organization_id
             FROM {$tables[$entity_type]} c
             JOIN {$tables[$parent_type]} p ON c.{$parent_fk} = p.id
             WHERE c.id = %d",
            $entity_id
        ));

        if ($entity_org_id === null) {
            return self::error(__('Not found', 'rental-gates'), 'not_found', 404);
        }

        if ((int) $entity_org_id !== (int) $user_org_id) {
            Rental_Gates_Security::log_security_event('idor_attempt', array(
                'entity_type' => $entity_type,
                'entity_id'   => $entity_id,
                'entity_org'  => $entity_org_id,
                'user_org'    => $user_org_id,
            ));
            return self::error(__('Not found', 'rental-gates'), 'not_found', 404);
        }

        return true;
    }

    // ==========================================
    // RESPONSE HELPERS
    // ==========================================

    public static function success($data = null, $message = '', $status = 200)
    {
        $response = array('success' => true);
        if ($message)
            $response['message'] = $message;
        if ($data !== null)
            $response['data'] = $data;
        return new WP_REST_Response($response, $status);
    }

    public static function error($message, $code = 'error', $status = 400, $data = null)
    {
        $response = array('success' => false, 'code' => $code, 'message' => $message);
        if ($data !== null)
            $response['data'] = $data;
        return new WP_REST_Response($response, $status);
    }

    public static function get_pagination_args($request)
    {
        // Whitelist of allowed orderby columns to prevent SQL injection
        $allowed_orderby = array(
            'id', 'created_at', 'updated_at', 'name', 'title',
            'status', 'email', 'amount', 'date', 'due_date',
            'first_name', 'last_name', 'rent_amount', 'priority',
        );

        $orderby = sanitize_text_field($request->get_param('orderby') ?: 'created_at');
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'created_at';
        }

        return array(
            'page'     => max(1, intval($request->get_param('page') ?: 1)),
            'per_page' => min(100, max(1, intval($request->get_param('per_page') ?: 20))),
            'orderby'  => $orderby,
            'order'    => strtoupper($request->get_param('order')) === 'ASC' ? 'ASC' : 'DESC',
            'search'   => sanitize_text_field($request->get_param('search') ?: ''),
        );
    }

    public static function paginated_response($items, $total, $args)
    {
        $response = self::success($items);
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', ceil($total / $args['per_page']));
        $response->header('X-WP-Page', $args['page']);
        return $response;
    }

    private function get_org_id()
    {
        return Rental_Gates_Roles::get_organization_id();
    }

    private function extract_data($request, $fields)
    {
        $data = array();
        foreach ($fields as $field) {
            if ($request->has_param($field)) {
                $data[$field] = $request->get_param($field);
            }
        }
        return $data;
    }

    // ==========================================
    // AUTH HANDLERS
    // ==========================================

    public function handle_login($request)
    {
        $email = sanitize_email($request->get_param('email'));
        $password = $request->get_param('password');
        $remember = (bool) $request->get_param('remember');

        if (empty($email) || empty($password)) {
            return self::error(__('Email and password are required', 'rental-gates'), 'missing_credentials', 400);
        }

        $user = wp_authenticate($email, $password);

        if (is_wp_error($user)) {
            return self::error(__('Invalid email or password', 'rental-gates'), 'invalid_credentials', 401);
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember);

        return self::success(array(
            'user' => array(
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'roles' => $user->roles,
            ),
            'redirect_url' => Rental_Gates_Roles::get_dashboard_url($user->ID),
        ), __('Login successful', 'rental-gates'));
    }

    public function handle_register($request)
    {
        if (class_exists('Rental_Gates_Auth')) {
            return Rental_Gates_Auth::process_registration($request);
        }
        return self::error(__('Registration is not available', 'rental-gates'), 'unavailable', 503);
    }

    public function handle_logout($request)
    {
        wp_logout();
        return self::success(null, __('Logged out successfully', 'rental-gates'));
    }

    public function get_current_user($request)
    {
        $user = wp_get_current_user();
        $org_id = $this->get_org_id();

        $data = array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'roles' => $user->roles,
            'avatar' => get_avatar_url($user->ID),
            'organization_id' => $org_id,
            'capabilities' => Rental_Gates_Roles::get_user_capabilities(),
        );

        if ($org_id) {
            $data['organization'] = Rental_Gates_Organization::get($org_id);
        }

        return self::success($data);
    }

    public function handle_password_reset($request)
    {
        $email = sanitize_email($request->get_param('email'));

        if (empty($email)) {
            return self::error(__('Email is required', 'rental-gates'), 'missing_email', 400);
        }

        $user = get_user_by('email', $email);
        if ($user) {
            retrieve_password($email);
        }

        return self::success(null, __('If an account exists with this email, a password reset link has been sent.', 'rental-gates'));
    }

    // ==========================================
    // ORGANIZATION HANDLERS
    // ==========================================

    public function get_organizations($request)
    {
        $args = self::get_pagination_args($request);
        $args['status'] = sanitize_text_field($request->get_param('status'));

        $result = Rental_Gates_Organization::get_all($args);
        return self::paginated_response($result['items'], $result['total'], $args);
    }

    public function create_organization($request)
    {
        // Rate limit org creation: 3 per hour per user
        $rate_check = Rental_Gates_Rate_Limit::check('org_creation', 'user_' . get_current_user_id());
        if (is_wp_error($rate_check)) {
            return self::error($rate_check->get_error_message(), 'rate_limited', 429);
        }

        $data = $this->extract_data($request, array('name', 'contact_email', 'contact_phone'));
        $data['contact_phone'] = Rental_Gates_Security::sanitize_phone($data['contact_phone'] ?? '');

        $result = Rental_Gates_Organization::create($data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Organization created', 'rental-gates'), 201);
    }

    public function get_organization($request)
    {
        $org = Rental_Gates_Organization::get(intval($request->get_param('id')));

        if (!$org) {
            return self::error(__('Organization not found', 'rental-gates'), 'not_found', 404);
        }

        return self::success($org);
    }

    public function update_organization($request)
    {
        $id = intval($request->get_param('id'));

        $fields = array(
            'name',
            'description',
            'contact_email',
            'contact_phone',
            'website',
            'address',
            'social_links',
            'branding',
            'map_provider',
            'default_language',
            'timezone',
            'currency',
            'late_fee_grace_days',
            'late_fee_type',
            'late_fee_amount',
            'allow_partial_payments'
        );

        $data = $this->extract_data($request, $fields);
        $result = Rental_Gates_Organization::update($id, $data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Organization updated', 'rental-gates'));
    }

    public function delete_organization($request)
    {
        $result = Rental_Gates_Organization::delete(intval($request->get_param('id')));

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(null, __('Organization deleted', 'rental-gates'));
    }

    public function get_organization_stats($request)
    {
        return self::success(Rental_Gates_Organization::get_stats(intval($request->get_param('id'))));
    }

    public function get_organization_members($request)
    {
        $id = intval($request->get_param('id'));
        $role = sanitize_text_field($request->get_param('role'));
        return self::success(Rental_Gates_Organization::get_members($id, $role));
    }

    public function add_organization_member($request)
    {
        $org_id = intval($request->get_param('id'));
        $email = sanitize_email($request->get_param('email'));
        $role = sanitize_text_field($request->get_param('role') ?: 'staff');

        $user = get_user_by('email', $email);

        if (!$user) {
            $password = wp_generate_password(16);
            $user_id = wp_create_user($email, $password, $email);

            if (is_wp_error($user_id)) {
                return self::error($user_id->get_error_message(), 'user_creation_failed', 400);
            }

            wp_new_user_notification($user_id, null, 'user');
            $user = get_user_by('ID', $user_id);
        }

        $result = Rental_Gates_Organization::add_member($org_id, $user->ID, $role);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(null, __('Member added successfully', 'rental-gates'), 201);
    }

    // ==========================================
    // BUILDING HANDLERS
    // ==========================================

    public function get_buildings($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $args = self::get_pagination_args($request);
        $args['status'] = sanitize_text_field($request->get_param('status') ?: 'active');

        $result = Rental_Gates_Building::get_for_organization($org_id, $args);
        return self::paginated_response($result['items'], $result['total'], $args);
    }

    public function create_building($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $fields = array(
            'name',
            'description',
            'latitude',
            'longitude',
            'derived_address',
            'year_built',
            'total_floors',
            'amenities',
            'gallery',
            'featured_image',
            'status'
        );

        $data = $this->extract_data($request, $fields);
        $data['organization_id'] = $org_id;

        $result = Rental_Gates_Building::create($data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Building created', 'rental-gates'), 201);
    }

    public function get_building($request)
    {
        $building = Rental_Gates_Building::get(intval($request->get_param('id')));

        if (!$building) {
            return self::error(__('Building not found', 'rental-gates'), 'not_found', 404);
        }

        $org_id = $this->get_org_id();
        if ($building['organization_id'] != $org_id && !Rental_Gates_Roles::is_site_admin()) {
            return self::error(__('Access denied', 'rental-gates'), 'forbidden', 403);
        }

        return self::success($building);
    }

    public function update_building($request)
    {
        $id = intval($request->get_param('id'));

        $fields = array(
            'name',
            'description',
            'latitude',
            'longitude',
            'derived_address',
            'year_built',
            'total_floors',
            'amenities',
            'gallery',
            'featured_image',
            'status'
        );

        $data = $this->extract_data($request, $fields);
        $result = Rental_Gates_Building::update($id, $data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Building updated', 'rental-gates'));
    }

    public function delete_building($request)
    {
        $result = Rental_Gates_Building::delete(intval($request->get_param('id')));

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(null, __('Building deleted', 'rental-gates'));
    }

    public function get_building_units($request)
    {
        $building_id = intval($request->get_param('id'));
        $availability = sanitize_text_field($request->get_param('availability'));

        $args = array();
        if ($availability) {
            $args['availability'] = $availability;
        }

        $units = Rental_Gates_Unit::get_for_building($building_id, $args);
        return self::success($units);
    }

    // ==========================================
    // UNIT HANDLERS
    // ==========================================

    public function get_units($request)
    {
        $building_id = intval($request->get_param('building_id'));
        $availability = sanitize_text_field($request->get_param('availability'));

        if ($building_id) {
            $args = array();
            if ($availability) {
                $args['availability'] = $availability;
            }
            $units = Rental_Gates_Unit::get_for_building($building_id, $args);
            return self::success($units);
        }

        // Get all units for organization
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        // Get all buildings for this org, then get units
        $buildings = Rental_Gates_Building::get_for_organization($org_id, array('limit' => 1000));
        $all_units = array();

        foreach ($buildings['items'] as $building) {
            $args = array();
            if ($availability) {
                $args['availability'] = $availability;
            }
            $units = Rental_Gates_Unit::get_for_building($building['id'], $args);
            foreach ($units as $unit) {
                $unit['building_name'] = $building['name'];
                $all_units[] = $unit;
            }
        }

        return self::success($all_units);
    }

    public function create_unit($request)
    {
        $fields = array(
            'building_id',
            'name',
            'unit_type',
            'description',
            'rent_amount',
            'deposit_amount',
            'availability',
            'available_from',
            'bedrooms',
            'bathrooms',
            'living_rooms',
            'kitchens',
            'parking_spots',
            'square_footage',
            'amenities',
            'gallery',
            'featured_image'
        );

        $data = $this->extract_data($request, $fields);
        $result = Rental_Gates_Unit::create($data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Unit created', 'rental-gates'), 201);
    }

    public function get_unit($request)
    {
        $id = intval($request->get_param('id'));

        $ownership = $this->verify_org_ownership_via_parent('units', $id, 'buildings', 'building_id');
        if ($ownership !== true) {
            return $ownership;
        }

        $unit = Rental_Gates_Unit::get($id);
        if (!$unit) {
            return self::error(__('Unit not found', 'rental-gates'), 'not_found', 404);
        }

        return self::success($unit);
    }

    public function update_unit($request)
    {
        $id = intval($request->get_param('id'));

        $fields = array(
            'name',
            'unit_type',
            'description',
            'rent_amount',
            'deposit_amount',
            'availability',
            'available_from',
            'bedrooms',
            'bathrooms',
            'living_rooms',
            'kitchens',
            'parking_spots',
            'square_footage',
            'amenities',
            'gallery',
            'featured_image'
        );

        $data = $this->extract_data($request, $fields);
        $result = Rental_Gates_Unit::update($id, $data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Unit updated', 'rental-gates'));
    }

    public function delete_unit($request)
    {
        $result = Rental_Gates_Unit::delete(intval($request->get_param('id')));

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(null, __('Unit deleted', 'rental-gates'));
    }

    public function update_unit_availability($request)
    {
        $id = intval($request->get_param('id'));
        $state = sanitize_text_field($request->get_param('availability'));
        $reason = sanitize_text_field($request->get_param('reason') ?: '');

        $result = Rental_Gates_Unit::set_availability($id, $state, $reason);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Availability updated', 'rental-gates'));
    }

    // ==========================================
    // TENANT HANDLERS
    // ==========================================

    public function get_tenants($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $args = self::get_pagination_args($request);
        $args['status'] = sanitize_text_field($request->get_param('status'));

        $tenants = Rental_Gates_Tenant::get_for_organization($org_id, $args);
        $total = Rental_Gates_Tenant::count_for_organization($org_id, $args['status']);

        return self::paginated_response($tenants, $total, $args);
    }

    public function create_tenant($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $fields = array(
            'first_name',
            'last_name',
            'email',
            'phone',
            'preferred_contact',
            'emergency_contact_name',
            'emergency_contact_phone',
            'date_of_birth',
            'notes',
            'status'
        );

        $data = $this->extract_data($request, $fields);
        $data['organization_id'] = $org_id;

        $result = Rental_Gates_Tenant::create($data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Tenant created', 'rental-gates'), 201);
    }

    public function get_tenant($request)
    {
        $id = intval($request->get_param('id'));

        $ownership = $this->verify_org_ownership('tenants', $id);
        if ($ownership !== true) {
            return $ownership;
        }

        $tenant = Rental_Gates_Tenant::get($id);
        if (!$tenant) {
            return self::error(__('Tenant not found', 'rental-gates'), 'not_found', 404);
        }

        return self::success($tenant);
    }

    public function update_tenant($request)
    {
        $id = intval($request->get_param('id'));

        $fields = array(
            'first_name',
            'last_name',
            'email',
            'phone',
            'preferred_contact',
            'emergency_contact_name',
            'emergency_contact_phone',
            'date_of_birth',
            'notes',
            'status'
        );

        $data = $this->extract_data($request, $fields);
        $result = Rental_Gates_Tenant::update($id, $data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Tenant updated', 'rental-gates'));
    }

    public function delete_tenant($request)
    {
        $result = Rental_Gates_Tenant::delete(intval($request->get_param('id')));

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(null, __('Tenant deleted', 'rental-gates'));
    }

    public function invite_tenant_to_portal($request)
    {
        $result = Rental_Gates_Tenant::invite_to_portal(intval($request->get_param('id')));

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Invitation sent', 'rental-gates'));
    }

    public function get_tenant_leases($request)
    {
        $id = intval($request->get_param('id'));

        $ownership = $this->verify_org_ownership('tenants', $id);
        if ($ownership !== true) {
            return $ownership;
        }

        $leases = Rental_Gates_Lease::get_for_tenant($id, array('include_ended' => true));
        return self::success($leases);
    }

    // ==========================================
    // LEASE HANDLERS
    // ==========================================

    public function get_leases($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $args = self::get_pagination_args($request);
        $args['status'] = sanitize_text_field($request->get_param('status'));
        $args['unit_id'] = intval($request->get_param('unit_id'));

        $result = Rental_Gates_Lease::get_for_organization($org_id, $args);
        return self::paginated_response($result['items'], $result['total'], $args);
    }

    public function create_lease($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $fields = array(
            'unit_id',
            'application_id',
            'start_date',
            'end_date',
            'is_month_to_month',
            'notice_period_days',
            'rent_amount',
            'deposit_amount',
            'billing_frequency',
            'billing_day',
            'status',
            'notes',
            'tenants'
        );

        $data = $this->extract_data($request, $fields);
        $data['organization_id'] = $org_id;

        $result = Rental_Gates_Lease::create($data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Lease created', 'rental-gates'), 201);
    }

    public function get_lease($request)
    {
        $id = intval($request->get_param('id'));

        $ownership = $this->verify_org_ownership('leases', $id);
        if ($ownership !== true) {
            return $ownership;
        }

        $lease = Rental_Gates_Lease::get_with_details($id);
        if (!$lease) {
            return self::error(__('Lease not found', 'rental-gates'), 'not_found', 404);
        }

        return self::success($lease);
    }

    public function update_lease($request)
    {
        $id = intval($request->get_param('id'));

        $fields = array(
            'start_date',
            'end_date',
            'is_month_to_month',
            'notice_period_days',
            'rent_amount',
            'deposit_amount',
            'billing_frequency',
            'billing_day',
            'status',
            'notes'
        );

        $data = $this->extract_data($request, $fields);
        $result = Rental_Gates_Lease::update($id, $data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Lease updated', 'rental-gates'));
    }

    public function delete_lease($request)
    {
        $result = Rental_Gates_Lease::delete(intval($request->get_param('id')));

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(null, __('Lease deleted', 'rental-gates'));
    }

    public function activate_lease($request)
    {
        $result = Rental_Gates_Lease::activate(intval($request->get_param('id')));

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Lease activated', 'rental-gates'));
    }

    public function terminate_lease($request)
    {
        $id = intval($request->get_param('id'));
        $date = sanitize_text_field($request->get_param('termination_date'));
        $reason = sanitize_text_field($request->get_param('reason') ?: '');

        $result = Rental_Gates_Lease::terminate($id, $date, $reason);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Lease terminated', 'rental-gates'));
    }

    public function renew_lease($request)
    {
        $id = intval($request->get_param('id'));
        $new_end_date = sanitize_text_field($request->get_param('new_end_date'));
        $new_rent = $request->has_param('new_rent_amount') ? floatval($request->get_param('new_rent_amount')) : null;

        $result = Rental_Gates_Lease::renew($id, $new_end_date, $new_rent);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Lease renewed', 'rental-gates'));
    }

    // ==========================================
    // APPLICATION HANDLERS
    // ==========================================

    public function get_applications($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $args = self::get_pagination_args($request);
        $args['status'] = sanitize_text_field($request->get_param('status'));
        $args['unit_id'] = intval($request->get_param('unit_id'));

        $result = Rental_Gates_Application::get_for_organization($org_id, $args);
        return self::paginated_response($result['items'], $result['total'], $args);
    }

    public function create_application($request)
    {
        // Rate limit: 5 applications per hour per IP
        $rate_check = Rental_Gates_Rate_Limit::check('public_application');
        if (is_wp_error($rate_check)) {
            return self::error($rate_check->get_error_message(), 'rate_limited', 429);
        }

        // Honeypot check - hidden field that should be empty
        $honeypot = $request->get_param('website_url');
        if (!empty($honeypot)) {
            return self::success(array('id' => 0), __('Application submitted', 'rental-gates'), 201);
        }

        // Time-based check - form must have been loaded for at least 3 seconds
        $form_loaded_at = intval($request->get_param('_form_ts'));
        if ($form_loaded_at > 0 && (time() - $form_loaded_at) < 3) {
            return self::success(array('id' => 0), __('Application submitted', 'rental-gates'), 201);
        }

        $fields = array(
            'organization_id',
            'unit_id',
            'applicant_name',
            'applicant_email',
            'applicant_phone',
            'current_address',
            'employer',
            'income_range',
            'desired_move_in',
            'notes',
            'occupants'
        );

        $data = $this->extract_data($request, $fields);
        $result = Rental_Gates_Application::create($data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Application submitted', 'rental-gates'), 201);
    }

    public function get_application($request)
    {
        $id = intval($request->get_param('id'));

        $ownership = $this->verify_org_ownership('applications', $id);
        if ($ownership !== true) {
            return $ownership;
        }

        $application = Rental_Gates_Application::get_with_details($id);
        if (!$application) {
            return self::error(__('Application not found', 'rental-gates'), 'not_found', 404);
        }

        return self::success($application);
    }

    public function update_application($request)
    {
        $id = intval($request->get_param('id'));

        $fields = array('status', 'notes', 'internal_notes');
        $data = $this->extract_data($request, $fields);

        $result = Rental_Gates_Application::update($id, $data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Application updated', 'rental-gates'));
    }

    public function approve_application($request)
    {
        $result = Rental_Gates_Application::approve(intval($request->get_param('id')));

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Application approved', 'rental-gates'));
    }

    public function decline_application($request)
    {
        $id = intval($request->get_param('id'));
        $reason = sanitize_text_field($request->get_param('reason') ?: '');

        $result = Rental_Gates_Application::decline($id, $reason);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Application declined', 'rental-gates'));
    }

    // ==========================================
    // MAINTENANCE HANDLERS
    // ==========================================

    public function get_work_orders($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $args = self::get_pagination_args($request);
        $args['status'] = sanitize_text_field($request->get_param('status'));
        $args['priority'] = sanitize_text_field($request->get_param('priority'));
        $args['building_id'] = intval($request->get_param('building_id'));

        $result = Rental_Gates_Maintenance::get_for_organization($org_id, $args);
        return self::paginated_response($result['items'], $result['total'], $args);
    }

    public function create_work_order($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $fields = array(
            'building_id',
            'unit_id',
            'tenant_id',
            'title',
            'description',
            'category',
            'priority',
            'permission_to_enter',
            'access_instructions',
            'photos',
            'scheduled_date'
        );

        $data = $this->extract_data($request, $fields);
        $data['organization_id'] = $org_id;

        $result = Rental_Gates_Maintenance::create($data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        do_action('rental_gates_maintenance_created', $result, $org_id);

        return self::success($result, __('Work order created', 'rental-gates'), 201);
    }

    public function get_work_order($request)
    {
        $id = intval($request->get_param('id'));

        $ownership = $this->verify_org_ownership('work_orders', $id);
        if ($ownership !== true) {
            return $ownership;
        }

        $work_order = Rental_Gates_Maintenance::get_with_details($id);
        if (!$work_order) {
            return self::error(__('Work order not found', 'rental-gates'), 'not_found', 404);
        }

        return self::success($work_order);
    }

    public function update_work_order($request)
    {
        $id = intval($request->get_param('id'));

        $fields = array(
            'title',
            'description',
            'category',
            'priority',
            'status',
            'permission_to_enter',
            'access_instructions',
            'cost_estimate',
            'actual_cost',
            'scheduled_date',
            'completed_at',
            'internal_notes'
        );

        $data = $this->extract_data($request, $fields);
        $old_data = Rental_Gates_Maintenance::get($id);
        $result = Rental_Gates_Maintenance::update($id, $data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        do_action('rental_gates_maintenance_updated', $result, $old_data, $this->get_org_id());

        return self::success($result, __('Work order updated', 'rental-gates'));
    }

    public function delete_work_order($request)
    {
        $result = Rental_Gates_Maintenance::delete(intval($request->get_param('id')));

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(null, __('Work order deleted', 'rental-gates'));
    }

    public function get_work_order_notes($request)
    {
        $id = intval($request->get_param('id'));

        $ownership = $this->verify_org_ownership('work_orders', $id);
        if ($ownership !== true) {
            return $ownership;
        }

        $notes = Rental_Gates_Maintenance::get_notes($id);
        return self::success($notes);
    }

    public function add_work_order_note($request)
    {
        $id = intval($request->get_param('id'));
        $note = sanitize_textarea_field($request->get_param('note'));
        $is_internal = (bool) $request->get_param('is_internal');

        $result = Rental_Gates_Maintenance::add_note($id, $note, get_current_user_id(), $is_internal);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Note added', 'rental-gates'), 201);
    }

    public function assign_work_order_vendor($request)
    {
        $id = intval($request->get_param('id'));
        $vendor_id = intval($request->get_param('vendor_id'));

        $result = Rental_Gates_Maintenance::assign_vendor($id, $vendor_id);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Vendor assigned', 'rental-gates'));
    }

    // ==========================================
    // VENDOR HANDLERS
    // ==========================================

    public function get_vendors($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $args = self::get_pagination_args($request);
        $args['status'] = sanitize_text_field($request->get_param('status'));
        $args['category'] = sanitize_text_field($request->get_param('category'));

        $result = Rental_Gates_Vendor::get_for_organization($org_id, $args);
        return self::paginated_response($result['items'], $result['total'], $args);
    }

    public function create_vendor($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $fields = array(
            'company_name',
            'contact_name',
            'email',
            'phone',
            'service_categories',
            'service_buildings',
            'hourly_rate',
            'notes',
            'status'
        );

        $data = $this->extract_data($request, $fields);
        $data['organization_id'] = $org_id;

        $result = Rental_Gates_Vendor::create($data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Vendor created', 'rental-gates'), 201);
    }

    public function get_vendor($request)
    {
        $id = intval($request->get_param('id'));

        $ownership = $this->verify_org_ownership('vendors', $id);
        if ($ownership !== true) {
            return $ownership;
        }

        $vendor = Rental_Gates_Vendor::get($id);
        if (!$vendor) {
            return self::error(__('Vendor not found', 'rental-gates'), 'not_found', 404);
        }

        return self::success($vendor);
    }

    public function update_vendor($request)
    {
        $id = intval($request->get_param('id'));

        $fields = array(
            'company_name',
            'contact_name',
            'email',
            'phone',
            'service_categories',
            'service_buildings',
            'hourly_rate',
            'notes',
            'status'
        );

        $data = $this->extract_data($request, $fields);
        $result = Rental_Gates_Vendor::update($id, $data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Vendor updated', 'rental-gates'));
    }

    public function delete_vendor($request)
    {
        $result = Rental_Gates_Vendor::delete(intval($request->get_param('id')));

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(null, __('Vendor deleted', 'rental-gates'));
    }

    public function invite_vendor_to_portal($request)
    {
        $result = Rental_Gates_Vendor::invite_to_portal(intval($request->get_param('id')));

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Invitation sent', 'rental-gates'));
    }

    // ==========================================
    // PAYMENT HANDLERS
    // ==========================================

    public function get_payments($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $args = self::get_pagination_args($request);
        $args['status'] = sanitize_text_field($request->get_param('status'));
        $args['type'] = sanitize_text_field($request->get_param('type'));
        $args['tenant_id'] = intval($request->get_param('tenant_id'));
        $args['lease_id'] = intval($request->get_param('lease_id'));

        $result = Rental_Gates_Payment::get_for_organization($org_id, $args);
        return self::paginated_response($result['items'], $result['total'], $args);
    }

    public function create_payment($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $fields = array(
            'lease_id',
            'tenant_id',
            'type',
            'method',
            'amount',
            'due_date',
            'period_start',
            'period_end',
            'description',
            'notes'
        );

        $data = $this->extract_data($request, $fields);
        $data['organization_id'] = $org_id;

        $result = Rental_Gates_Payment::create($data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Payment recorded', 'rental-gates'), 201);
    }

    public function get_payment($request)
    {
        $id = intval($request->get_param('id'));

        $ownership = $this->verify_org_ownership('payments', $id);
        if ($ownership !== true) {
            return $ownership;
        }

        $payment = Rental_Gates_Payment::get_with_details($id);
        if (!$payment) {
            return self::error(__('Payment not found', 'rental-gates'), 'not_found', 404);
        }

        return self::success($payment);
    }

    public function update_payment($request)
    {
        $id = intval($request->get_param('id'));

        $fields = array('status', 'amount_paid', 'paid_at', 'notes');
        $data = $this->extract_data($request, $fields);

        $result = Rental_Gates_Payment::update($id, $data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        do_action('rental_gates_payment_received', $result, $this->get_org_id());

        return self::success($result, __('Payment updated', 'rental-gates'));
    }

    public function refund_payment($request)
    {
        $id = intval($request->get_param('id'));
        $amount = floatval($request->get_param('amount'));
        $reason = sanitize_text_field($request->get_param('reason') ?: '');

        $result = Rental_Gates_Payment::refund($id, $amount, $reason);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Payment refunded', 'rental-gates'));
    }

    public function create_checkout_session($request)
    {
        $payment_id = intval($request->get_param('payment_id'));
        $return_url = esc_url_raw($request->get_param('return_url'));

        $result = Rental_Gates_Stripe::create_checkout_session($payment_id, $return_url);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result);
    }

    // ==========================================
    // DOCUMENT HANDLERS
    // ==========================================

    public function get_documents($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $args = self::get_pagination_args($request);
        $args['category'] = sanitize_text_field($request->get_param('category'));
        $args['entity_type'] = sanitize_text_field($request->get_param('entity_type'));
        $args['entity_id'] = intval($request->get_param('entity_id'));

        $result = Rental_Gates_Document::get_for_organization($org_id, $args);
        return self::paginated_response($result['items'], $result['total'], $args);
    }

    public function create_document($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $fields = array(
            'name',
            'category',
            'entity_type',
            'entity_id',
            'file_path',
            'file_type',
            'file_size',
            'description',
            'is_template'
        );

        $data = $this->extract_data($request, $fields);
        $data['organization_id'] = $org_id;
        $data['uploaded_by'] = get_current_user_id();

        $result = Rental_Gates_Document::create($data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Document uploaded', 'rental-gates'), 201);
    }

    public function get_document($request)
    {
        $id = intval($request->get_param('id'));

        $ownership = $this->verify_org_ownership('documents', $id);
        if ($ownership !== true) {
            return $ownership;
        }

        $document = Rental_Gates_Document::get($id);
        if (!$document) {
            return self::error(__('Document not found', 'rental-gates'), 'not_found', 404);
        }

        return self::success($document);
    }

    public function update_document($request)
    {
        $id = intval($request->get_param('id'));

        $fields = array('name', 'category', 'description');
        $data = $this->extract_data($request, $fields);

        $result = Rental_Gates_Document::update($id, $data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Document updated', 'rental-gates'));
    }

    public function delete_document($request)
    {
        $result = Rental_Gates_Document::delete(intval($request->get_param('id')));

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(null, __('Document deleted', 'rental-gates'));
    }

    // ==========================================
    // MESSAGE HANDLERS
    // ==========================================

    public function get_messages($request)
    {
        $user_id = get_current_user_id();
        $args = self::get_pagination_args($request);

        $result = Rental_Gates_Message::get_for_user($user_id, $args);
        return self::paginated_response($result['items'], $result['total'], $args);
    }

    public function send_message($request)
    {
        $data = array(
            'sender_id' => get_current_user_id(),
            'recipient_id' => intval($request->get_param('recipient_id')),
            'thread_id' => intval($request->get_param('thread_id')),
            'subject' => sanitize_text_field($request->get_param('subject')),
            'content' => wp_kses_post($request->get_param('content')),
            'organization_id' => $this->get_org_id(),
        );

        $result = Rental_Gates_Message::send($data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Message sent', 'rental-gates'), 201);
    }

    public function get_message_threads($request)
    {
        $user_id = get_current_user_id();
        $threads = Rental_Gates_Message::get_threads($user_id);
        return self::success($threads);
    }

    public function get_thread_messages($request)
    {
        $thread_id = intval($request->get_param('id'));

        $ownership = $this->verify_org_ownership('message_threads', $thread_id);
        if ($ownership !== true) {
            return $ownership;
        }

        $messages = Rental_Gates_Message::get_thread_messages($thread_id);
        return self::success($messages);
    }

    public function mark_message_read($request)
    {
        $result = Rental_Gates_Message::mark_read(intval($request->get_param('id')));

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(null, __('Message marked as read', 'rental-gates'));
    }

    // ==========================================
    // ANNOUNCEMENT HANDLERS
    // ==========================================

    public function get_announcements($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $args = self::get_pagination_args($request);
        $args['status'] = sanitize_text_field($request->get_param('status'));

        $result = Rental_Gates_Announcement::get_for_organization($org_id, $args);
        return self::paginated_response($result['items'], $result['total'], $args);
    }

    public function create_announcement($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $fields = array(
            'title',
            'content',
            'type',
            'priority',
            'audience',
            'building_ids',
            'scheduled_at',
            'expires_at'
        );

        $data = $this->extract_data($request, $fields);
        $data['organization_id'] = $org_id;
        $data['created_by'] = get_current_user_id();

        $result = Rental_Gates_Announcement::create($data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Announcement created', 'rental-gates'), 201);
    }

    public function get_announcement($request)
    {
        $id = intval($request->get_param('id'));

        $ownership = $this->verify_org_ownership('announcements', $id);
        if ($ownership !== true) {
            return $ownership;
        }

        $announcement = Rental_Gates_Announcement::get($id);
        if (!$announcement) {
            return self::error(__('Announcement not found', 'rental-gates'), 'not_found', 404);
        }

        return self::success($announcement);
    }

    public function update_announcement($request)
    {
        $id = intval($request->get_param('id'));

        $fields = array(
            'title',
            'content',
            'type',
            'priority',
            'audience',
            'building_ids',
            'status',
            'scheduled_at',
            'expires_at'
        );

        $data = $this->extract_data($request, $fields);
        $result = Rental_Gates_Announcement::update($id, $data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Announcement updated', 'rental-gates'));
    }

    public function delete_announcement($request)
    {
        $result = Rental_Gates_Announcement::delete(intval($request->get_param('id')));

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(null, __('Announcement deleted', 'rental-gates'));
    }

    // ==========================================
    // NOTIFICATION HANDLERS
    // ==========================================

    public function get_notifications($request)
    {
        $user_id = get_current_user_id();
        $args = self::get_pagination_args($request);
        $args['unread_only'] = (bool) $request->get_param('unread_only');

        $result = Rental_Gates_Notification::get_for_user($user_id, $args);
        return self::paginated_response($result['items'], $result['total'], $args);
    }

    public function get_unread_notification_count($request)
    {
        $count = Rental_Gates_Notification::get_unread_count(get_current_user_id());
        return self::success(array('count' => $count));
    }

    public function mark_notification_read($request)
    {
        $result = Rental_Gates_Notification::mark_read(intval($request->get_param('id')));

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(null, __('Notification marked as read', 'rental-gates'));
    }

    public function mark_all_notifications_read($request)
    {
        $result = Rental_Gates_Notification::mark_all_read(get_current_user_id());

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(null, __('All notifications marked as read', 'rental-gates'));
    }

    // ==========================================
    // LEAD HANDLERS
    // ==========================================

    public function get_leads($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $args = self::get_pagination_args($request);
        $args['stage'] = sanitize_text_field($request->get_param('stage'));
        $args['source'] = sanitize_text_field($request->get_param('source'));
        $args['assigned_to'] = intval($request->get_param('assigned_to'));
        $args['search'] = sanitize_text_field($request->get_param('search'));
        $args['follow_up_due'] = (bool) $request->get_param('follow_up_due');

        $leads = Rental_Gates_Lead::get_for_organization($org_id, $args);
        $total = count($leads); // Lead model returns all matching, pagination handled internally

        return self::paginated_response($leads, $total, $args);
    }

    public function create_lead($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $fields = array(
            'name',
            'email',
            'phone',
            'source',
            'source_id',
            'notes',
            'follow_up_date',
            'assigned_to',
            'building_id',
            'unit_id'
        );

        $data = $this->extract_data($request, $fields);
        $data['organization_id'] = $org_id;

        $result = Rental_Gates_Lead::create($data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        // Get the created lead
        $lead = Rental_Gates_Lead::get($result);

        return self::success($lead, __('Lead created', 'rental-gates'), 201);
    }

    public function get_lead($request)
    {
        $id = intval($request->get_param('id'));

        $ownership = $this->verify_org_ownership('leads', $id);
        if ($ownership !== true) {
            return $ownership;
        }

        $lead = Rental_Gates_Lead::get_with_details($id);
        if (!$lead) {
            return self::error(__('Lead not found', 'rental-gates'), 'not_found', 404);
        }

        return self::success($lead);
    }

    public function update_lead($request)
    {
        $id = intval($request->get_param('id'));

        $fields = array(
            'name',
            'email',
            'phone',
            'stage',
            'notes',
            'follow_up_date',
            'assigned_to',
            'lost_reason'
        );

        $data = $this->extract_data($request, $fields);
        $result = Rental_Gates_Lead::update($id, $data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Lead updated', 'rental-gates'));
    }

    public function delete_lead($request)
    {
        $result = Rental_Gates_Lead::delete(intval($request->get_param('id')));

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(null, __('Lead deleted', 'rental-gates'));
    }

    public function update_lead_stage($request)
    {
        $id = intval($request->get_param('id'));
        $stage = sanitize_text_field($request->get_param('stage'));
        $lost_reason = sanitize_text_field($request->get_param('lost_reason') ?: '');

        $result = Rental_Gates_Lead::update_stage($id, $stage, $lost_reason);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Lead stage updated', 'rental-gates'));
    }

    public function add_lead_note($request)
    {
        $id = intval($request->get_param('id'));
        $note = sanitize_textarea_field($request->get_param('note'));

        if (empty($note)) {
            return self::error(__('Note is required', 'rental-gates'), 'missing_note', 400);
        }

        $result = Rental_Gates_Lead::add_note($id, $note);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Note added', 'rental-gates'));
    }

    public function get_lead_interests($request)
    {
        $interests = Rental_Gates_Lead::get_interests(intval($request->get_param('id')));
        return self::success($interests);
    }

    public function add_lead_interest($request)
    {
        $id = intval($request->get_param('id'));
        $building_id = intval($request->get_param('building_id')) ?: null;
        $unit_id = intval($request->get_param('unit_id')) ?: null;

        if (!$building_id && !$unit_id) {
            return self::error(__('Building or unit is required', 'rental-gates'), 'missing_target', 400);
        }

        $result = Rental_Gates_Lead::add_interest($id, $building_id, $unit_id);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(Rental_Gates_Lead::get_interests($id), __('Interest added', 'rental-gates'));
    }

    public function get_lead_stats($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $stage_counts = Rental_Gates_Lead::get_stage_counts($org_id);
        $follow_ups_due = Rental_Gates_Lead::get_follow_ups_due($org_id);

        return self::success(array(
            'stage_counts' => $stage_counts,
            'total' => array_sum($stage_counts),
            'follow_ups_due' => count($follow_ups_due),
            'stages' => Rental_Gates_Lead::get_stages(),
            'sources' => Rental_Gates_Lead::get_sources(),
        ));
    }

    public function convert_lead_to_application($request)
    {
        $id = intval($request->get_param('id'));
        $unit_id = intval($request->get_param('unit_id'));

        $lead = Rental_Gates_Lead::get($id);
        if (!$lead) {
            return self::error(__('Lead not found', 'rental-gates'), 'not_found', 404);
        }

        // Create application from lead data
        $app_data = array(
            'organization_id' => $lead['organization_id'],
            'unit_id' => $unit_id,
            'applicant_name' => $lead['name'],
            'applicant_email' => $lead['email'],
            'applicant_phone' => $lead['phone'],
            'notes' => sprintf(__('Converted from lead #%d', 'rental-gates'), $id),
            'status' => 'new',
        );

        $app_result = Rental_Gates_Application::create($app_data);

        if (is_wp_error($app_result)) {
            return self::error($app_result->get_error_message(), $app_result->get_error_code(), 400);
        }

        // Update lead stage to applied
        Rental_Gates_Lead::update_stage($id, 'applied');

        return self::success(array(
            'application_id' => $app_result,
            'lead' => Rental_Gates_Lead::get($id),
        ), __('Lead converted to application', 'rental-gates'));
    }

    // ==========================================
    // QR CODE HANDLERS
    // ==========================================

    public function get_qr_codes($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $args = array(
            'page' => intval($request->get_param('page')) ?: 1,
            'per_page' => intval($request->get_param('per_page')) ?: 20,
            'type' => sanitize_text_field($request->get_param('type')),
        );

        $result = Rental_Gates_QR::get_for_organization($org_id, $args);

        return self::success(array(
            'items' => $result['items'],
            'total' => $result['total'],
            'pages' => $result['pages'],
        ));
    }

    public function create_qr_code($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $type = sanitize_text_field($request->get_param('type'));
        $entity_id = intval($request->get_param('entity_id'));
        $size = sanitize_text_field($request->get_param('size')) ?: 'medium';
        $url = esc_url_raw($request->get_param('url'));
        $label = sanitize_text_field($request->get_param('label'));

        $qr = new Rental_Gates_QR();

        if ($type === 'custom' && !empty($url)) {
            $result = $qr->generate_custom($url, $org_id, $label, $size);
        } elseif ($type === 'building' && $entity_id) {
            $result = $qr->generate_for_building($entity_id, $size);
        } elseif ($type === 'unit' && $entity_id) {
            $result = $qr->generate_for_unit($entity_id, $size);
        } elseif ($type === 'organization') {
            $result = $qr->generate_for_organization($org_id, $size);
        } else {
            return self::error(__('Invalid type or missing parameters', 'rental-gates'), 'invalid_params', 400);
        }

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('QR code generated', 'rental-gates'), 201);
    }

    public function generate_qr_for_building($request)
    {
        $id = intval($request->get_param('id'));
        $size = sanitize_text_field($request->get_param('size')) ?: 'medium';

        $qr = new Rental_Gates_QR();
        $result = $qr->generate_for_building($id, $size);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('QR code generated for building', 'rental-gates'));
    }

    public function generate_qr_for_unit($request)
    {
        $id = intval($request->get_param('id'));
        $size = sanitize_text_field($request->get_param('size')) ?: 'medium';

        $qr = new Rental_Gates_QR();
        $result = $qr->generate_for_unit($id, $size);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('QR code generated for unit', 'rental-gates'));
    }

    public function bulk_generate_qr_codes($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $type = sanitize_text_field($request->get_param('type'));
        $size = sanitize_text_field($request->get_param('size')) ?: 'medium';
        $items = $request->get_param('items');

        $qr = new Rental_Gates_QR();

        if ($type === 'all_buildings') {
            $result = $qr->generate_all_buildings($org_id, $size);
        } elseif ($type === 'all_units') {
            $result = $qr->generate_all_units($org_id, $size);
        } elseif (is_array($items) && !empty($items)) {
            $result = $qr->bulk_generate($items, $size);
        } else {
            return self::error(__('Invalid bulk generation type', 'rental-gates'), 'invalid_type', 400);
        }

        return self::success(array(
            'generated' => count($result),
            'items' => $result,
        ), sprintf(__('%d QR codes generated', 'rental-gates'), count($result)));
    }

    public function get_qr_code($request)
    {
        $id = intval($request->get_param('id'));

        $ownership = $this->verify_org_ownership('qr_codes', $id);
        if ($ownership !== true) {
            return $ownership;
        }

        $qr = Rental_Gates_QR::get($id);
        if (!$qr) {
            return self::error(__('QR code not found', 'rental-gates'), 'not_found', 404);
        }

        // Get analytics
        $qr['analytics'] = Rental_Gates_QR::get_scan_analytics($id);

        return self::success($qr);
    }

    public function delete_qr_code($request)
    {
        $id = intval($request->get_param('id'));

        $result = Rental_Gates_QR::delete($id);

        if (!$result) {
            return self::error(__('Failed to delete QR code', 'rental-gates'), 'delete_failed', 400);
        }

        return self::success(null, __('QR code deleted', 'rental-gates'));
    }

    public function get_qr_analytics($request)
    {
        $id = intval($request->get_param('id'));
        $days = intval($request->get_param('days')) ?: 30;

        $analytics = Rental_Gates_QR::get_scan_analytics($id, $days);

        return self::success($analytics);
    }

    public function handle_qr_scan($request)
    {
        $code = sanitize_text_field($request->get_param('code'));

        $qr = Rental_Gates_QR::get_by_code($code);

        if (!$qr) {
            return self::error(__('Invalid QR code', 'rental-gates'), 'not_found', 404);
        }

        // Track the scan
        Rental_Gates_QR::track_scan($code, $qr['type'], $qr['entity_id']);

        // Create lead if visitor data provided
        $name = sanitize_text_field($request->get_param('name'));
        $email = sanitize_email($request->get_param('email'));
        $phone = sanitize_text_field($request->get_param('phone'));

        $lead_id = null;
        if ($email) {
            $lead_data = array(
                'organization_id' => $qr['organization_id'],
                'name' => $name ?: __('QR Visitor', 'rental-gates'),
                'email' => $email,
                'phone' => $phone,
                'source' => $qr['type'] === 'building' ? 'qr_building' : 'qr_unit',
                'source_id' => $qr['entity_id'],
            );

            if ($qr['type'] === 'building') {
                $lead_data['building_id'] = $qr['entity_id'];
            } elseif ($qr['type'] === 'unit') {
                $lead_data['unit_id'] = $qr['entity_id'];
            }

            $lead_id = Rental_Gates_Lead::create($lead_data);
        }

        return self::success(array(
            'redirect_url' => $qr['url'],
            'type' => $qr['type'],
            'entity_id' => $qr['entity_id'],
            'lead_id' => is_wp_error($lead_id) ? null : $lead_id,
        ));
    }

    // ==========================================
    // PDF HANDLERS
    // ==========================================

    public function generate_lease_pdf($request)
    {
        $id = intval($request->get_param('id'));

        $pdf = rental_gates_pdf();
        $result = $pdf->generate_lease($id);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Lease PDF generated', 'rental-gates'));
    }

    public function generate_receipt_pdf($request)
    {
        $id = intval($request->get_param('id'));

        $pdf = rental_gates_pdf();
        $result = $pdf->generate_receipt($id);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Receipt PDF generated', 'rental-gates'));
    }

    public function generate_invoice_pdf($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $invoice_data = array(
            'organization_id' => $org_id,
            'tenant_id' => intval($request->get_param('tenant_id')),
            'invoice_number' => sanitize_text_field($request->get_param('invoice_number')),
            'invoice_date' => sanitize_text_field($request->get_param('invoice_date')) ?: current_time('mysql'),
            'due_date' => sanitize_text_field($request->get_param('due_date')),
            'items' => $request->get_param('items') ?: array(),
            'total' => floatval($request->get_param('total')),
        );

        $pdf = rental_gates_pdf();
        $result = $pdf->generate_invoice($invoice_data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Invoice PDF generated', 'rental-gates'));
    }

    public function generate_work_order_pdf($request)
    {
        $id = intval($request->get_param('id'));

        $pdf = rental_gates_pdf();
        $result = $pdf->generate_work_order($id);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Work order PDF generated', 'rental-gates'));
    }

    public function generate_report_pdf($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $pdf = rental_gates_pdf();
        $result = $pdf->generate_property_report($org_id);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Property report generated', 'rental-gates'));
    }

    public function generate_flyer_pdf($request)
    {
        $id = intval($request->get_param('id'));
        $template = sanitize_text_field($request->get_param('template')) ?: 'modern';

        $pdf = rental_gates_pdf();
        $result = $pdf->generate_flyer($id, $template);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Flyer PDF generated', 'rental-gates'));
    }

    // ==========================================
    // PUBLIC HANDLERS
    // ==========================================

    public function get_public_map_data($request)
    {
        $bounds = $request->has_param('bounds') ? $request->get_param('bounds') : null;
        $buildings = Rental_Gates_Building::get_for_map($bounds);
        return self::success(array('buildings' => $buildings));
    }

    public function get_public_building($request)
    {
        $slug = sanitize_text_field($request->get_param('slug'));
        $building = Rental_Gates_Building::get_by_slug($slug);

        if (!$building || $building['status'] !== 'active') {
            return self::error(__('Building not found', 'rental-gates'), 'not_found', 404);
        }

        $units = Rental_Gates_Unit::get_for_building($building['id']);
        $available_units = array_filter($units, function ($u) {
            return in_array($u['availability'], array('available', 'coming_soon'));
        });

        return self::success(array(
            'building' => $building,
            'units' => array_values($available_units),
        ));
    }

    public function get_public_unit($request)
    {
        $building_slug = sanitize_text_field($request->get_param('building_slug'));
        $unit_slug = sanitize_text_field($request->get_param('unit_slug'));

        $unit = Rental_Gates_Unit::get_by_slug($unit_slug, $building_slug);

        if (!$unit || !in_array($unit['availability'], array('available', 'coming_soon'))) {
            return self::error(__('Unit not found', 'rental-gates'), 'not_found', 404);
        }

        $building = Rental_Gates_Building::get($unit['building_id']);

        return self::success(array('unit' => $unit, 'building' => $building));
    }

    public function submit_inquiry($request)
    {
        $rate_check = Rental_Gates_Rate_Limit::check('public_inquiry');
        if (is_wp_error($rate_check)) {
            return self::error($rate_check->get_error_message(), 'rate_limited', 429);
        }

        $data = array(
            'organization_id' => intval($request->get_param('organization_id')),
            'unit_id' => intval($request->get_param('unit_id')),
            'applicant_name' => sanitize_text_field($request->get_param('name')),
            'applicant_email' => sanitize_email($request->get_param('email')),
            'applicant_phone' => sanitize_text_field($request->get_param('phone')),
            'notes' => sanitize_textarea_field($request->get_param('message')),
            'status' => 'new',
        );

        $result = Rental_Gates_Application::create($data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        do_action('rental_gates_application_submitted', $result, $data['organization_id']);

        return self::success(null, __('Inquiry submitted successfully', 'rental-gates'), 201);
    }

    // ==========================================
    // SEARCH HANDLER
    // ==========================================

    public function global_search($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $query = sanitize_text_field($request->get_param('q'));
        $type = sanitize_text_field($request->get_param('type'));

        if (strlen($query) < 2) {
            return self::success(array('results' => array()));
        }

        $results = array();

        if (!$type || $type === 'buildings') {
            $buildings = Rental_Gates_Building::get_for_organization($org_id, array('search' => $query, 'per_page' => 5));
            foreach ($buildings['items'] as $b) {
                $results[] = array('type' => 'building', 'id' => $b['id'], 'title' => $b['name'], 'subtitle' => $b['derived_address']);
            }
        }

        if (!$type || $type === 'tenants') {
            $tenants = Rental_Gates_Tenant::get_for_organization($org_id, array('search' => $query, 'limit' => 5));
            foreach ($tenants as $t) {
                $results[] = array('type' => 'tenant', 'id' => $t['id'], 'title' => $t['full_name'], 'subtitle' => $t['email']);
            }
        }

        return self::success(array('results' => $results, 'query' => $query));
    }

    // ==========================================
    // PROFILE HANDLERS
    // ==========================================

    public function get_profile($request)
    {
        $user = wp_get_current_user();

        return self::success(array(
            'id' => $user->ID,
            'email' => $user->user_email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'display_name' => $user->display_name,
            'avatar' => get_avatar_url($user->ID),
        ));
    }

    public function update_profile($request)
    {
        $user_id = get_current_user_id();
        $data = array('ID' => $user_id);

        if ($request->has_param('first_name'))
            $data['first_name'] = sanitize_text_field($request->get_param('first_name'));
        if ($request->has_param('last_name'))
            $data['last_name'] = sanitize_text_field($request->get_param('last_name'));
        if ($request->has_param('display_name'))
            $data['display_name'] = sanitize_text_field($request->get_param('display_name'));

        $result = wp_update_user($data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(null, __('Profile updated', 'rental-gates'));
    }

    public function update_password($request)
    {
        $user_id = get_current_user_id();
        $current = $request->get_param('current_password');
        $new = $request->get_param('new_password');

        $user = get_user_by('ID', $user_id);

        if (!wp_check_password($current, $user->user_pass, $user_id)) {
            return self::error(__('Current password is incorrect', 'rental-gates'), 'invalid_password', 400);
        }

        wp_set_password($new, $user_id);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        return self::success(null, __('Password updated', 'rental-gates'));
    }

    // ==========================================
    // SETTINGS HANDLERS
    // ==========================================

    public function get_settings($request)
    {
        $org_id = $this->get_org_id();
        $org = Rental_Gates_Organization::get($org_id);

        return self::success(array(
            'organization' => $org,
            'stripe_configured' => Rental_Gates_Stripe::is_configured(),
            'stripe_mode' => Rental_Gates_Stripe::get_mode(),
        ));
    }

    public function update_settings($request)
    {
        $org_id = $this->get_org_id();

        $fields = array(
            'name',
            'description',
            'contact_email',
            'contact_phone',
            'website',
            'address',
            'timezone',
            'currency',
            'late_fee_grace_days',
            'late_fee_type',
            'late_fee_amount',
            'allow_partial_payments'
        );

        $data = $this->extract_data($request, $fields);
        $result = Rental_Gates_Organization::update($org_id, $data);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result, __('Settings updated', 'rental-gates'));
    }

    public function get_automation_settings($request)
    {
        $org_id = $this->get_org_id();
        $settings = get_option('rental_gates_automation_' . $org_id, array());
        return self::success($settings);
    }

    public function update_automation_settings($request)
    {
        $org_id = $this->get_org_id();

        $settings = array(
            'enabled' => (bool) $request->get_param('enabled'),
            'auto_generate_rent' => (bool) $request->get_param('auto_generate_rent'),
            'rent_reminder_enabled' => (bool) $request->get_param('rent_reminder_enabled'),
            'rent_reminder_days' => intval($request->get_param('rent_reminder_days') ?: 5),
            'overdue_alerts_enabled' => (bool) $request->get_param('overdue_alerts_enabled'),
            'late_fees_enabled' => (bool) $request->get_param('late_fees_enabled'),
            'lease_expiry_alerts_enabled' => (bool) $request->get_param('lease_expiry_alerts_enabled'),
            'lease_expiry_days' => intval($request->get_param('lease_expiry_days') ?: 60),
            'move_reminders_enabled' => (bool) $request->get_param('move_reminders_enabled'),
        );

        update_option('rental_gates_automation_' . $org_id, $settings);

        return self::success($settings, __('Automation settings updated', 'rental-gates'));
    }

    // ==========================================
    // REPORT HANDLERS
    // ==========================================

    public function get_dashboard_stats($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        return self::success(array(
            'organization' => Rental_Gates_Organization::get_stats($org_id),
            'tenants' => Rental_Gates_Tenant::get_stats($org_id),
            'leases' => Rental_Gates_Lease::get_stats($org_id),
            'payments' => Rental_Gates_Payment::get_stats($org_id),
            'maintenance' => Rental_Gates_Maintenance::get_stats($org_id),
        ));
    }

    public function get_financial_report($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $start_date = sanitize_text_field($request->get_param('start_date') ?: date('Y-m-01'));
        $end_date = sanitize_text_field($request->get_param('end_date') ?: date('Y-m-t'));

        $report = Rental_Gates_Payment::get_financial_report($org_id, $start_date, $end_date);
        return self::success($report);
    }

    public function get_occupancy_report($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $stats = Rental_Gates_Organization::get_stats($org_id);

        $occupancy_rate = 0;
        if ($stats['total_units'] > 0) {
            $occupancy_rate = round(($stats['occupied_units'] / $stats['total_units']) * 100, 1);
        }

        return self::success(array(
            'total_units' => $stats['total_units'],
            'occupied_units' => $stats['occupied_units'],
            'available_units' => $stats['available_units'],
            'occupancy_rate' => $occupancy_rate,
        ));
    }

    // ==========================================
    // AI HANDLERS
    // ==========================================

    public function get_ai_credits($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $stats = Rental_Gates_AI::get_usage_stats($org_id);
        return self::success($stats);
    }

    public function generate_ai_description($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        if (!Rental_Gates_AI::org_has_access($org_id)) {
            return self::error(__('AI tools not available on your plan', 'rental-gates'), 'no_access', 403);
        }

        $ai = rental_gates_ai();
        $result = $ai->generate_description(array(
            'name' => sanitize_text_field($request->get_param('name') ?? ''),
            'unit_type' => sanitize_text_field($request->get_param('unit_type') ?? ''),
            'bedrooms' => intval($request->get_param('bedrooms') ?? 0),
            'bathrooms' => floatval($request->get_param('bathrooms') ?? 0),
            'sqft' => intval($request->get_param('sqft') ?? 0),
            'rent' => floatval($request->get_param('rent') ?? 0),
            'address' => sanitize_text_field($request->get_param('address') ?? ''),
            'features' => sanitize_textarea_field($request->get_param('features') ?? ''),
            'style' => sanitize_key($request->get_param('style') ?? 'professional'),
        ), $org_id);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result);
    }

    public function generate_ai_marketing($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        if (!Rental_Gates_AI::org_has_access($org_id)) {
            return self::error(__('AI tools not available on your plan', 'rental-gates'), 'no_access', 403);
        }

        $ai = rental_gates_ai();
        $result = $ai->generate_marketing(array(
            'name' => sanitize_text_field($request->get_param('name') ?? ''),
            'rent' => floatval($request->get_param('rent') ?? 0),
            'bedrooms' => intval($request->get_param('bedrooms') ?? 0),
            'address' => sanitize_text_field($request->get_param('address') ?? ''),
            'highlights' => sanitize_textarea_field($request->get_param('highlights') ?? ''),
            'format' => sanitize_key($request->get_param('format') ?? 'flyer'),
        ), $org_id);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result);
    }

    public function triage_maintenance($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        if (!Rental_Gates_AI::org_has_access($org_id)) {
            return self::error(__('AI tools not available on your plan', 'rental-gates'), 'no_access', 403);
        }

        $ai = rental_gates_ai();
        $result = $ai->triage_maintenance(array(
            'title' => sanitize_text_field($request->get_param('title') ?? ''),
            'description' => sanitize_textarea_field($request->get_param('description') ?? ''),
            'location' => sanitize_text_field($request->get_param('location') ?? ''),
        ), $org_id);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result);
    }

    public function draft_ai_message($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        if (!Rental_Gates_AI::org_has_access($org_id)) {
            return self::error(__('AI tools not available on your plan', 'rental-gates'), 'no_access', 403);
        }

        $ai = rental_gates_ai();
        $result = $ai->draft_message(array(
            'type' => sanitize_key($request->get_param('type') ?? 'general'),
            'tone' => sanitize_key($request->get_param('tone') ?? 'professional'),
            'tenant_name' => sanitize_text_field($request->get_param('tenant_name') ?? ''),
            'property' => sanitize_text_field($request->get_param('property') ?? ''),
            'context' => sanitize_textarea_field($request->get_param('context') ?? ''),
            'specific_details' => sanitize_textarea_field($request->get_param('specific_details') ?? ''),
        ), $org_id);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result);
    }

    public function screen_applicant($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        if (!Rental_Gates_AI::org_has_access($org_id)) {
            return self::error(__('AI tools not available on your plan', 'rental-gates'), 'no_access', 403);
        }

        $application_id = intval($request->get_param('application_id'));
        if (!$application_id) {
            return self::error(__('Application ID required', 'rental-gates'), 'missing_param', 400);
        }

        $ai = rental_gates_ai();
        $result = $ai->screen_applicant($application_id, $org_id);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result);
    }

    public function get_portfolio_insights($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        if (!Rental_Gates_AI::org_has_access($org_id)) {
            return self::error(__('AI tools not available on your plan', 'rental-gates'), 'no_access', 403);
        }

        $ai = rental_gates_ai();
        $result = $ai->get_portfolio_insights($org_id);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success($result);
    }

    public function get_ai_history($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        $limit = intval($request->get_param('limit') ?? 20);
        $tool = sanitize_key($request->get_param('tool') ?? '');

        $history = Rental_Gates_AI::get_history($org_id, min($limit, 100), $tool ?: null);

        return self::success(array('history' => $history));
    }

    public function analyze_lease_terms($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        if (!Rental_Gates_AI::org_has_access($org_id)) {
            return self::error(__('AI tools not available on your plan', 'rental-gates'), 'no_access', 403);
        }

        $ai = rental_gates_ai();

        $lease_data = array(
            'rent_amount' => floatval($request->get_param('rent_amount') ?? 0),
            'deposit_amount' => floatval($request->get_param('deposit_amount') ?? 0),
            'lease_term' => intval($request->get_param('lease_term') ?? 12),
            'start_date' => sanitize_text_field($request->get_param('start_date') ?? ''),
            'pet_policy' => sanitize_text_field($request->get_param('pet_policy') ?? ''),
            'utilities_included' => $request->get_param('utilities_included') ?? array(),
            'special_terms' => sanitize_textarea_field($request->get_param('special_terms') ?? ''),
        );

        $prompt = "Analyze these lease terms and provide feedback:\n\n";
        $prompt .= "Monthly Rent: $" . number_format($lease_data['rent_amount']) . "\n";
        $prompt .= "Security Deposit: $" . number_format($lease_data['deposit_amount']) . "\n";
        $prompt .= "Lease Term: " . $lease_data['lease_term'] . " months\n";
        if ($lease_data['pet_policy'])
            $prompt .= "Pet Policy: " . $lease_data['pet_policy'] . "\n";
        if ($lease_data['special_terms'])
            $prompt .= "Special Terms: " . $lease_data['special_terms'] . "\n";

        $prompt .= "\nProvide: 1) Fairness assessment, 2) Potential issues, 3) Suggestions for improvement. Keep response concise.";

        $result = $ai->call($prompt, "You are a real estate attorney reviewing lease terms. Be practical and balanced.", 500);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(array(
            'analysis' => $result['content'],
            'credits_used' => 1,
        ));
    }

    public function generate_ai_email($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        if (!Rental_Gates_AI::org_has_access($org_id)) {
            return self::error(__('AI tools not available on your plan', 'rental-gates'), 'no_access', 403);
        }

        $ai = rental_gates_ai();

        $type = sanitize_key($request->get_param('type') ?? 'general');
        $context = sanitize_textarea_field($request->get_param('context') ?? '');
        $recipient_name = sanitize_text_field($request->get_param('recipient_name') ?? '');
        $tone = sanitize_key($request->get_param('tone') ?? 'professional');

        $email_types = array(
            'welcome' => 'a warm welcome email for a new tenant',
            'late_rent' => 'a polite but firm late rent reminder',
            'lease_renewal' => 'a lease renewal offer',
            'maintenance_update' => 'a maintenance status update',
            'move_out' => 'move-out instructions and procedures',
            'general' => 'a general property management communication',
        );

        $prompt = "Write " . ($email_types[$type] ?? $email_types['general']) . ".\n\n";
        if ($recipient_name)
            $prompt .= "Recipient: " . $recipient_name . "\n";
        if ($context)
            $prompt .= "Context: " . $context . "\n";
        $prompt .= "Tone: " . ucfirst($tone) . "\n";
        $prompt .= "\nProvide subject line and email body. Keep it concise and professional.";

        $result = $ai->call($prompt, "You are a professional property manager. Write clear, effective emails.", 600);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(array(
            'email' => $result['content'],
            'type' => $type,
            'credits_used' => 1,
        ));
    }

    public function suggest_rent_price($request)
    {
        $org_id = $this->get_org_id();
        if (!$org_id) {
            return self::error(__('Organization not found', 'rental-gates'), 'no_org', 400);
        }

        if (!Rental_Gates_AI::org_has_access($org_id)) {
            return self::error(__('AI tools not available on your plan', 'rental-gates'), 'no_access', 403);
        }

        $ai = rental_gates_ai();

        $unit_data = array(
            'bedrooms' => intval($request->get_param('bedrooms') ?? 1),
            'bathrooms' => floatval($request->get_param('bathrooms') ?? 1),
            'sqft' => intval($request->get_param('sqft') ?? 0),
            'location' => sanitize_text_field($request->get_param('location') ?? ''),
            'amenities' => $request->get_param('amenities') ?? array(),
            'current_rent' => floatval($request->get_param('current_rent') ?? 0),
            'unit_type' => sanitize_text_field($request->get_param('unit_type') ?? 'apartment'),
        );

        $prompt = "Suggest a competitive rent price for this property:\n\n";
        $prompt .= "Type: " . ucfirst($unit_data['unit_type']) . "\n";
        $prompt .= "Bedrooms: " . $unit_data['bedrooms'] . "\n";
        $prompt .= "Bathrooms: " . $unit_data['bathrooms'] . "\n";
        if ($unit_data['sqft'])
            $prompt .= "Size: " . number_format($unit_data['sqft']) . " sq ft\n";
        if ($unit_data['location'])
            $prompt .= "Location: " . $unit_data['location'] . "\n";
        if (!empty($unit_data['amenities']))
            $prompt .= "Amenities: " . implode(', ', $unit_data['amenities']) . "\n";
        if ($unit_data['current_rent'])
            $prompt .= "Current Rent: $" . number_format($unit_data['current_rent']) . "\n";

        $prompt .= "\nProvide: 1) Suggested price range, 2) Key factors, 3) Tips to maximize rent. Be specific with numbers.";

        $result = $ai->call($prompt, "You are a real estate market analyst. Provide data-driven rent pricing advice.", 500);

        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), $result->get_error_code(), 400);
        }

        return self::success(array(
            'suggestion' => $result['content'],
            'credits_used' => 1,
        ));
    }

    // ==========================================
    // TEST HANDLERS
    // ==========================================

    public function run_system_tests($request)
    {
        if (!class_exists('Rental_Gates_Tests')) {
            return self::error(__('Testing class not available', 'rental-gates'), 'not_available', 400);
        }

        $results = Rental_Gates_Tests::run_all();

        return self::success($results, __('System tests completed', 'rental-gates'));
    }

    public function get_health_check($request)
    {
        if (!class_exists('Rental_Gates_Tests')) {
            return self::error(__('Testing class not available', 'rental-gates'), 'not_available', 400);
        }

        $health = Rental_Gates_Tests::health_check();

        return self::success($health);
    }

    public function check_db_integrity($request)
    {
        if (!class_exists('Rental_Gates_Tests')) {
            return self::error(__('Testing class not available', 'rental-gates'), 'not_available', 400);
        }

        $integrity = Rental_Gates_Tests::check_integrity();

        return self::success($integrity);
    }
}
