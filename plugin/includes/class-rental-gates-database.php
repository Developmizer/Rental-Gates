<?php
/**
 * Rental Gates Database Class
 * Handles database schema creation and migrations
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rental_Gates_Database
{

    /**
     * Database table names
     */
    public static function get_table_names()
    {
        global $wpdb;

        return array(
            // Core
            'organizations' => $wpdb->prefix . 'rg_organizations',
            'organization_members' => $wpdb->prefix . 'rg_organization_members',

            // Property
            'buildings' => $wpdb->prefix . 'rg_buildings',
            'units' => $wpdb->prefix . 'rg_units',

            // People
            'tenants' => $wpdb->prefix . 'rg_tenants',
            'vendors' => $wpdb->prefix . 'rg_vendors',

            // Leasing
            'applications' => $wpdb->prefix . 'rg_applications',
            'application_occupants' => $wpdb->prefix . 'rg_application_occupants',
            'leases' => $wpdb->prefix . 'rg_leases',
            'lease_tenants' => $wpdb->prefix . 'rg_lease_tenants',
            'rent_adjustments' => $wpdb->prefix . 'rg_rent_adjustments',
            'renewals' => $wpdb->prefix . 'rg_renewals',

            // CRM
            'leads' => $wpdb->prefix . 'rg_leads',
            'lead_interests' => $wpdb->prefix . 'rg_lead_interests',
            'lead_scores' => $wpdb->prefix . 'rg_lead_scores',

            // Maintenance
            'work_orders' => $wpdb->prefix . 'rg_work_orders',
            'work_order_notes' => $wpdb->prefix . 'rg_work_order_notes',
            'work_order_vendors' => $wpdb->prefix . 'rg_work_order_vendors',
            'scheduled_maintenance' => $wpdb->prefix . 'rg_scheduled_maintenance',

            // Payments
            'payments' => $wpdb->prefix . 'rg_payments',
            'payment_items' => $wpdb->prefix . 'rg_payment_items',
            'payment_plans' => $wpdb->prefix . 'rg_payment_plans',
            'payment_plan_items' => $wpdb->prefix . 'rg_payment_plan_items',
            'deposits' => $wpdb->prefix . 'rg_deposits',
            'deposit_deductions' => $wpdb->prefix . 'rg_deposit_deductions',
            'vendor_payouts' => $wpdb->prefix . 'rg_vendor_payouts',

            // Subscriptions & Plans
            'plans' => $wpdb->prefix . 'rg_plans',
            'subscriptions' => $wpdb->prefix . 'rg_subscriptions',
            'invoices' => $wpdb->prefix . 'rg_invoices',

            // Stripe
            'stripe_accounts' => $wpdb->prefix . 'rg_stripe_accounts',
            'payment_methods' => $wpdb->prefix . 'rg_payment_methods',

            // Documents
            'documents' => $wpdb->prefix . 'rg_documents',

            // Communications
            'messages' => $wpdb->prefix . 'rg_messages',
            'message_threads' => $wpdb->prefix . 'rg_message_threads',
            'announcements' => $wpdb->prefix . 'rg_announcements',
            'announcement_recipients' => $wpdb->prefix . 'rg_announcement_recipients',

            // Move-in/Move-out
            'move_checklists' => $wpdb->prefix . 'rg_move_checklists',
            'condition_reports' => $wpdb->prefix . 'rg_condition_reports',
            'condition_items' => $wpdb->prefix . 'rg_condition_items',

            // Marketing
            'flyers' => $wpdb->prefix . 'rg_flyers',
            'qr_codes' => $wpdb->prefix . 'rg_qr_codes',
            'qr_scans' => $wpdb->prefix . 'rg_qr_scans',
            'marketing_campaigns' => $wpdb->prefix . 'rg_marketing_campaigns',
            'marketing_automation_rules' => $wpdb->prefix . 'rg_marketing_automation_rules',

            // AI
            'ai_usage' => $wpdb->prefix . 'rg_ai_usage',
            'ai_screenings' => $wpdb->prefix . 'rg_ai_screenings',

            // AI Credits (previously untracked)
            'ai_credit_balances'     => $wpdb->prefix . 'rg_ai_credit_balances',
            'ai_credit_transactions' => $wpdb->prefix . 'rg_ai_credit_transactions',
            'ai_credit_packs'        => $wpdb->prefix . 'rg_ai_credit_packs',
            'ai_credit_purchases'    => $wpdb->prefix . 'rg_ai_credit_purchases',

            // System
            'magic_links' => $wpdb->prefix . 'rg_magic_links',
            'notifications' => $wpdb->prefix . 'rg_notifications',
            'notification_preferences' => $wpdb->prefix . 'rg_notification_preferences',
            'activity_log' => $wpdb->prefix . 'rg_activity_log',
            'settings' => $wpdb->prefix . 'rg_settings',
            'staff_permissions' => $wpdb->prefix . 'rg_staff_permissions',
        );
    }

    /**
     * Check database version and update if needed
     */
    public function check_version()
    {
        $installed_version = get_option('rental_gates_db_version', '0');

        // Skip if already at current version
        if (version_compare($installed_version, RENTAL_GATES_DB_VERSION, '>=')) {
            return;
        }

        // Only create tables on fresh install (no version) or major version change
        if (empty($installed_version) || $installed_version === '0') {
            $this->create_tables();
        }

        // Run any needed migrations (version updates happen per-migration)
        $success = $this->run_migrations($installed_version);

        // Only set final version if all migrations succeeded
        if ($success) {
            update_option('rental_gates_db_version', RENTAL_GATES_DB_VERSION);
        }
    }

    /**
     * Run versioned database migrations with transaction safety.
     *
     * Each migration only runs if the current DB version is older than
     * the migration's target version. Migrations run inside transactions
     * and the version is updated after EACH successful migration. If any
     * migration fails, execution stops (fail-stop behavior).
     *
     * @param string $from_version Version we're upgrading from
     * @return bool True if all migrations succeeded
     */
    private function run_migrations($from_version)
    {
        // Define all migrations with their target versions
        $migrations = array(
            '2.7.0'  => 'migrate_to_270',
            '2.7.3'  => 'migrate_to_273',
            '2.15.0' => 'migrate_to_2150',
            '2.15.2' => 'migrate_to_2152',
            '2.16.0' => 'migrate_to_2160',
        );

        foreach ($migrations as $version => $method) {
            // Only run migrations newer than current version
            if (version_compare($from_version, $version, '>=')) {
                continue;
            }

            $success = $this->run_single_migration($version, $method);

            if (!$success) {
                // Stop at first failure - don't skip ahead
                if (class_exists('Rental_Gates_Security')) {
                    Rental_Gates_Security::log_security_event('migration_failed', array(
                        'version'      => $version,
                        'from_version' => $from_version,
                    ));
                }
                Rental_Gates_Logger::error('db', 'Migration failed - stopping', array('version' => $version));
                return false;
            }

            // Update version after EACH successful migration
            update_option('rental_gates_db_version', $version);
        }

        return true;
    }

    /**
     * Run a single migration inside a transaction.
     *
     * @param string $version Target version
     * @param string $method  Method name to call
     * @return bool
     */
    private function run_single_migration($version, $method)
    {
        global $wpdb;

        if (!method_exists($this, $method)) {
            Rental_Gates_Logger::error('db', 'Migration method not found', array('method' => $method));
            return false;
        }

        $wpdb->query('START TRANSACTION');

        try {
            $result = $this->$method();

            if ($result === false) {
                $wpdb->query('ROLLBACK');
                Rental_Gates_Logger::error('db', 'Migration returned false - rolling back', array('version' => $version));
                return false;
            }

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            Rental_Gates_Logger::error('db', 'Migration exception', array('version' => $version, 'exception' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Migration: v2.7.0 - Add permissions column, update status enums
     */
    private function migrate_to_270()
    {
        global $wpdb;
        $tables = self::get_table_names();

        // Add permissions column to organization_members if not exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$tables['organization_members']} LIKE 'permissions'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$tables['organization_members']} ADD COLUMN permissions longtext DEFAULT NULL AFTER title");
        }

        // Update status enum to include 'pending'
        $wpdb->query("ALTER TABLE {$tables['organization_members']} MODIFY COLUMN status enum('active','invited','inactive','pending') DEFAULT 'active'");

        return true;
    }

    /**
     * Migration: v2.7.3 - Add subscription status, parent_payment_id
     */
    private function migrate_to_273()
    {
        global $wpdb;
        $tables = self::get_table_names();

        // Update subscriptions status enum
        $wpdb->query("ALTER TABLE {$tables['subscriptions']} MODIFY COLUMN status enum('pending_payment','active','cancelled','past_due','trialing','expired') DEFAULT 'active'");

        // Add parent_payment_id for late fee tracking
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$tables['payments']} LIKE 'parent_payment_id'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$tables['payments']} ADD COLUMN parent_payment_id bigint(20) UNSIGNED DEFAULT NULL AFTER paid_by_user_id");
            $wpdb->query("ALTER TABLE {$tables['payments']} ADD INDEX parent_payment_id (parent_payment_id)");
        }

        return true;
    }

    /**
     * Migration: v2.15.0 - Create AI credit tables + magic links
     */
    private function migrate_to_2150()
    {
        $this->create_ai_credit_tables_if_needed();
        $this->create_magic_links_table_if_needed();
        return true;
    }

    /**
     * Migration: v2.15.2 - Current version (no-op placeholder)
     */
    private function migrate_to_2152()
    {
        return true;
    }

    /**
     * Migration 2.16.0 - Performance indexes
     *
     * Adds missing indexes on columns frequently used in WHERE/JOIN/ORDER BY
     * across reports, Stripe webhooks, and subscription lifecycle queries.
     */
    private function migrate_to_2160()
    {
        global $wpdb;

        $indexes = array(
            // Critical: Stripe webhook deduplication
            array($wpdb->prefix . 'rg_payments', 'idx_stripe_pi', 'stripe_payment_intent_id'),
            array($wpdb->prefix . 'rg_ai_credit_purchases', 'idx_stripe_pi', 'stripe_payment_intent_id'),
            // Report queries: date-range payment lookups
            array($wpdb->prefix . 'rg_payments', 'idx_paid_at', 'paid_at'),
            // Composite: org + date for financial reports
            array($wpdb->prefix . 'rg_payments', 'idx_org_paid_at', 'organization_id, paid_at'),
            // Maintenance completion reports
            array($wpdb->prefix . 'rg_work_orders', 'idx_completed_at', 'completed_at'),
            array($wpdb->prefix . 'rg_work_orders', 'idx_org_completed_at', 'organization_id, completed_at'),
            // Active tenant queries (removed_at IS NULL)
            array($wpdb->prefix . 'rg_lease_tenants', 'idx_lease_removed', 'lease_id, removed_at'),
            // Subscription lifecycle
            array($wpdb->prefix . 'rg_subscriptions', 'idx_cancel_period_end', 'cancel_at_period_end'),
            // Payment method default lookup
            array($wpdb->prefix . 'rg_payment_methods', 'idx_is_default', 'is_default'),
            // Payment audit trail
            array($wpdb->prefix . 'rg_payments', 'idx_paid_by', 'paid_by_user_id'),
        );

        foreach ($indexes as $idx) {
            list($table, $name, $columns) = $idx;

            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
                continue;
            }

            $existing = $wpdb->get_results("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$name}'");
            if (!empty($existing)) {
                continue;
            }

            $wpdb->query("ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$columns})");
        }

        return true;
    }

    /**
     * Create magic_links table if it doesn't exist
     * Called during migration for existing installations
     */
    public function create_magic_links_table_if_needed()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'rg_magic_links';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            $sql = "CREATE TABLE {$table_name} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id bigint(20) UNSIGNED NOT NULL,
                token varchar(255) NOT NULL,
                expires_at datetime NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                used_at datetime DEFAULT NULL,
                ip_address varchar(100) DEFAULT NULL,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                UNIQUE KEY token (token),
                KEY expires_at (expires_at)
            ) $charset_collate;";
            dbDelta($sql);
            Rental_Gates_Logger::info('db', 'Created magic_links table');
        }
    }

    /**
     * Create AI Credit tables if they don't exist
     * Called both during migration and can be called manually
     */
    public function create_ai_credit_tables_if_needed()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // AI Credit Balances
        $table_name = $wpdb->prefix . 'rg_ai_credit_balances';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            $sql = "CREATE TABLE {$table_name} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                organization_id bigint(20) UNSIGNED NOT NULL,
                subscription_credits int DEFAULT 0,
                purchased_credits int DEFAULT 0,
                bonus_credits int DEFAULT 0,
                cycle_start datetime DEFAULT NULL,
                cycle_end datetime DEFAULT NULL,
                last_refresh datetime DEFAULT NULL,
                rollover_credits int DEFAULT 0,
                rollover_expires datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY organization_id (organization_id)
            ) $charset_collate;";
            dbDelta($sql);
            Rental_Gates_Logger::info('db', 'Created AI credit balances table');
        }

        // AI Credit Transactions
        $table_name = $wpdb->prefix . 'rg_ai_credit_transactions';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            $sql = "CREATE TABLE {$table_name} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                organization_id bigint(20) UNSIGNED NOT NULL,
                user_id bigint(20) UNSIGNED DEFAULT NULL,
                type enum('subscription_grant','purchase','bonus','admin_adjustment','usage','refund','expiry','rollover') NOT NULL,
                credits int NOT NULL,
                credit_type enum('subscription','purchased','bonus') DEFAULT 'subscription',
                balance_before int NOT NULL DEFAULT 0,
                balance_after int NOT NULL DEFAULT 0,
                reference_type varchar(50) DEFAULT NULL,
                reference_id varchar(100) DEFAULT NULL,
                description varchar(255) DEFAULT NULL,
                meta_data longtext DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY organization_id (organization_id),
                KEY type (type),
                KEY credit_type (credit_type),
                KEY created_at (created_at)
            ) $charset_collate;";
            dbDelta($sql);
            Rental_Gates_Logger::info('db', 'Created AI credit transactions table');
        }

        // AI Credit Packs
        $table_name = $wpdb->prefix . 'rg_ai_credit_packs';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            $sql = "CREATE TABLE {$table_name} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name varchar(100) NOT NULL,
                slug varchar(50) NOT NULL,
                description varchar(255) DEFAULT NULL,
                credits int NOT NULL,
                price decimal(10,2) NOT NULL,
                currency varchar(3) DEFAULT 'USD',
                stripe_price_id varchar(100) DEFAULT NULL,
                sort_order int DEFAULT 0,
                is_featured tinyint(1) DEFAULT 0,
                is_active tinyint(1) DEFAULT 1,
                badge_text varchar(50) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug)
            ) $charset_collate;";
            dbDelta($sql);
            Rental_Gates_Logger::info('db', 'Created AI credit packs table');
        }

        // AI Credit Purchases
        $table_name = $wpdb->prefix . 'rg_ai_credit_purchases';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            $sql = "CREATE TABLE {$table_name} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                organization_id bigint(20) UNSIGNED NOT NULL,
                user_id bigint(20) UNSIGNED NOT NULL,
                pack_id bigint(20) UNSIGNED DEFAULT NULL,
                credits int NOT NULL,
                amount decimal(10,2) DEFAULT 0,
                currency varchar(3) DEFAULT 'USD',
                status enum('pending','completed','failed','refunded') DEFAULT 'pending',
                stripe_payment_intent_id varchar(100) DEFAULT NULL,
                stripe_charge_id varchar(100) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                completed_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY organization_id (organization_id),
                KEY status (status)
            ) $charset_collate;";
            dbDelta($sql);
            Rental_Gates_Logger::info('db', 'Created AI credit purchases table');
        }
    }

    /**
     * Create all database tables
     */
    public function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $tables = self::get_table_names();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // ============================================
        // ORGANIZATIONS
        // ============================================

        $sql = "CREATE TABLE {$tables['organizations']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            owner_id bigint(20) UNSIGNED DEFAULT NULL,
            plan_id varchar(50) DEFAULT 'free',
            description text DEFAULT NULL,
            logo bigint(20) UNSIGNED DEFAULT NULL,
            cover_image bigint(20) UNSIGNED DEFAULT NULL,
            contact_email varchar(255) DEFAULT NULL,
            contact_phone varchar(50) DEFAULT NULL,
            website varchar(500) DEFAULT NULL,
            address text DEFAULT NULL,
            social_links longtext DEFAULT NULL,
            branding longtext DEFAULT NULL,
            map_provider enum('google','openstreetmap') DEFAULT 'google',
            default_language enum('en','ar') DEFAULT 'en',
            timezone varchar(100) DEFAULT 'America/New_York',
            currency varchar(3) DEFAULT 'USD',
            late_fee_grace_days int DEFAULT 5,
            late_fee_type enum('flat','percentage') DEFAULT 'flat',
            late_fee_amount decimal(10,2) DEFAULT 0,
            late_fee_max_cap decimal(10,2) DEFAULT NULL,
            late_fee_recurring tinyint(1) DEFAULT 0,
            late_fee_recurring_days int DEFAULT NULL,
            allow_partial_payments tinyint(1) DEFAULT 1,
            coming_soon_window_days int DEFAULT 30,
            renewal_notice_days int DEFAULT 60,
            move_in_notice_days int DEFAULT 7,
            status enum('active','suspended','cancelled') DEFAULT 'active',
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY owner_id (owner_id),
            KEY plan_id (plan_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Organization members (links users to organizations)
        $sql = "CREATE TABLE {$tables['organization_members']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            role enum('owner','manager','staff') NOT NULL,
            title varchar(255) DEFAULT NULL,
            permissions longtext DEFAULT NULL,
            is_primary_owner tinyint(1) DEFAULT 0,
            status enum('active','invited','inactive','pending') DEFAULT 'active',
            invited_at datetime DEFAULT NULL,
            joined_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY org_user (organization_id, user_id),
            KEY user_id (user_id),
            KEY role (role)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // BUILDINGS
        // ============================================

        $sql = "CREATE TABLE {$tables['buildings']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text DEFAULT NULL,
            latitude decimal(10,8) NOT NULL,
            longitude decimal(11,8) NOT NULL,
            derived_address text DEFAULT NULL,
            derived_city varchar(255) DEFAULT NULL,
            derived_state varchar(100) DEFAULT NULL,
            derived_zip varchar(20) DEFAULT NULL,
            derived_country varchar(100) DEFAULT NULL,
            featured_image bigint(20) UNSIGNED DEFAULT NULL,
            gallery longtext DEFAULT NULL,
            year_built int DEFAULT NULL,
            total_floors int DEFAULT NULL,
            amenities longtext DEFAULT NULL,
            status enum('active','inactive') DEFAULT 'active',
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            UNIQUE KEY org_slug (organization_id, slug),
            KEY status (status),
            KEY location (latitude, longitude)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // UNITS
        // ============================================

        $sql = "CREATE TABLE {$tables['units']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            building_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            unit_type varchar(50) DEFAULT NULL,
            description text DEFAULT NULL,
            rent_amount decimal(12,2) NOT NULL,
            deposit_amount decimal(12,2) DEFAULT NULL,
            availability enum('available','coming_soon','occupied','renewal_pending','unlisted') DEFAULT 'available',
            available_from date DEFAULT NULL,
            availability_override tinyint(1) DEFAULT 0,
            availability_override_reason text DEFAULT NULL,
            bedrooms int DEFAULT 0,
            bathrooms decimal(3,1) DEFAULT 0,
            living_rooms int DEFAULT 0,
            kitchens int DEFAULT 0,
            parking_spots int DEFAULT 0,
            square_footage int DEFAULT NULL,
            custom_counts longtext DEFAULT NULL,
            amenities longtext DEFAULT NULL,
            featured_image bigint(20) UNSIGNED DEFAULT NULL,
            gallery longtext DEFAULT NULL,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY building_id (building_id),
            UNIQUE KEY building_slug (building_id, slug),
            KEY availability (availability),
            KEY available_from (available_from),
            KEY rent_amount (rent_amount)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // TENANTS
        // ============================================

        $sql = "CREATE TABLE {$tables['tenants']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50) NOT NULL,
            preferred_contact enum('email','phone','text') DEFAULT 'email',
            emergency_contact_name varchar(200) DEFAULT NULL,
            emergency_contact_phone varchar(50) DEFAULT NULL,
            date_of_birth date DEFAULT NULL,
            id_document bigint(20) UNSIGNED DEFAULT NULL,
            notes text DEFAULT NULL,
            status enum('active','former','prospect') DEFAULT 'prospect',
            forwarding_address text DEFAULT NULL,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY user_id (user_id),
            KEY email (email),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // VENDORS
        // ============================================

        $sql = "CREATE TABLE {$tables['vendors']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            company_name varchar(255) NOT NULL,
            contact_name varchar(200) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50) NOT NULL,
            service_categories longtext DEFAULT NULL,
            service_buildings longtext DEFAULT NULL,
            hourly_rate decimal(10,2) DEFAULT NULL,
            notes text DEFAULT NULL,
            status enum('active','paused','inactive') DEFAULT 'active',
            stripe_account_id varchar(255) DEFAULT NULL,
            stripe_status enum('not_connected','pending','ready') DEFAULT 'not_connected',
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // APPLICATIONS
        // ============================================

        $sql = "CREATE TABLE {$tables['applications']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            unit_id bigint(20) UNSIGNED NOT NULL,
            lead_id bigint(20) UNSIGNED DEFAULT NULL,
            token varchar(100) NOT NULL,
            applicant_name varchar(200) NOT NULL,
            applicant_email varchar(255) NOT NULL,
            applicant_phone varchar(50) NOT NULL,
            current_address text DEFAULT NULL,
            employer varchar(255) DEFAULT NULL,
            income_range varchar(100) DEFAULT NULL,
            desired_move_in date DEFAULT NULL,
            notes text DEFAULT NULL,
            documents longtext DEFAULT NULL,
            status enum('invited','new','screening','approved','declined','withdrawn') DEFAULT 'invited',
            invited_at datetime DEFAULT NULL,
            submitted_at datetime DEFAULT NULL,
            decision_at datetime DEFAULT NULL,
            decision_by bigint(20) UNSIGNED DEFAULT NULL,
            decline_reason text DEFAULT NULL,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY unit_id (unit_id),
            KEY lead_id (lead_id),
            UNIQUE KEY token (token),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        // Application occupants
        $sql = "CREATE TABLE {$tables['application_occupants']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            application_id bigint(20) UNSIGNED NOT NULL,
            name varchar(200) NOT NULL,
            email varchar(255) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            relationship varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY application_id (application_id)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // LEASES
        // ============================================

        $sql = "CREATE TABLE {$tables['leases']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            unit_id bigint(20) UNSIGNED NOT NULL,
            application_id bigint(20) UNSIGNED DEFAULT NULL,
            previous_lease_id bigint(20) UNSIGNED DEFAULT NULL,
            start_date date NOT NULL,
            end_date date DEFAULT NULL,
            is_month_to_month tinyint(1) DEFAULT 0,
            notice_period_days int DEFAULT 30,
            rent_amount decimal(12,2) NOT NULL,
            deposit_amount decimal(12,2) DEFAULT NULL,
            billing_frequency enum('monthly','weekly','biweekly') DEFAULT 'monthly',
            billing_day int DEFAULT 1,
            status enum('draft','active','ending','ended','renewed') DEFAULT 'draft',
            signed_at datetime DEFAULT NULL,
            signed_document bigint(20) UNSIGNED DEFAULT NULL,
            documents longtext DEFAULT NULL,
            notes text DEFAULT NULL,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY unit_id (unit_id),
            KEY status (status),
            KEY start_date (start_date),
            KEY end_date (end_date)
        ) $charset_collate;";
        dbDelta($sql);

        // Lease tenants (links tenants to leases with role)
        $sql = "CREATE TABLE {$tables['lease_tenants']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            lease_id bigint(20) UNSIGNED NOT NULL,
            tenant_id bigint(20) UNSIGNED NOT NULL,
            role enum('primary','co_tenant','occupant') NOT NULL,
            added_at datetime DEFAULT CURRENT_TIMESTAMP,
            removed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY lease_id (lease_id),
            KEY tenant_id (tenant_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Rent adjustments
        $sql = "CREATE TABLE {$tables['rent_adjustments']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            lease_id bigint(20) UNSIGNED NOT NULL,
            previous_amount decimal(12,2) NOT NULL,
            new_amount decimal(12,2) NOT NULL,
            effective_date date NOT NULL,
            reason text DEFAULT NULL,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY lease_id (lease_id),
            KEY effective_date (effective_date)
        ) $charset_collate;";
        dbDelta($sql);

        // Renewals
        $sql = "CREATE TABLE {$tables['renewals']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            lease_id bigint(20) UNSIGNED NOT NULL,
            new_end_date date DEFAULT NULL,
            new_rent_amount decimal(12,2) NOT NULL,
            is_month_to_month tinyint(1) DEFAULT 0,
            terms text DEFAULT NULL,
            status enum('offered','accepted','declined','expired') DEFAULT 'offered',
            offered_at datetime DEFAULT CURRENT_TIMESTAMP,
            responded_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            new_lease_id bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY lease_id (lease_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // LEADS (CRM)
        // ============================================

        $sql = "CREATE TABLE {$tables['leads']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            name varchar(200) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50) NOT NULL,
            source enum('qr_building','qr_unit','map','profile','manual','referral') DEFAULT 'manual',
            source_id bigint(20) UNSIGNED DEFAULT NULL,
            stage enum('new','contacted','touring','applied','won','lost') DEFAULT 'new',
            lost_reason text DEFAULT NULL,
            notes text DEFAULT NULL,
            follow_up_date datetime DEFAULT NULL,
            assigned_to bigint(20) UNSIGNED DEFAULT NULL,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY stage (stage),
            KEY assigned_to (assigned_to),
            KEY source (source),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);

        // Lead interests (which units/buildings they're interested in)
        $sql = "CREATE TABLE {$tables['lead_interests']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) UNSIGNED NOT NULL,
            building_id bigint(20) UNSIGNED DEFAULT NULL,
            unit_id bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY lead_id (lead_id),
            KEY building_id (building_id),
            KEY unit_id (unit_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Lead scores (scoring history and breakdown)
        $sql = "CREATE TABLE {$tables['lead_scores']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) UNSIGNED NOT NULL,
            score int(11) NOT NULL DEFAULT 0,
            breakdown longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY lead_id (lead_id),
            KEY score (score),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // WORK ORDERS (MAINTENANCE)
        // ============================================

        $sql = "CREATE TABLE {$tables['work_orders']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            building_id bigint(20) UNSIGNED NOT NULL,
            unit_id bigint(20) UNSIGNED DEFAULT NULL,
            tenant_id bigint(20) UNSIGNED DEFAULT NULL,
            scheduled_maintenance_id bigint(20) UNSIGNED DEFAULT NULL,
            title varchar(255) NOT NULL,
            description text NOT NULL,
            category enum('plumbing','electrical','hvac','appliance','structural','pest','cleaning','general','other') DEFAULT 'other',
            priority enum('low','medium','high','emergency') DEFAULT 'medium',
            status enum('open','assigned','in_progress','on_hold','completed','cancelled','declined') DEFAULT 'open',
            cause enum('normal_wear','tenant_responsibility','external','unknown') DEFAULT NULL,
            permission_to_enter tinyint(1) DEFAULT 0,
            access_instructions text DEFAULT NULL,
            photos longtext DEFAULT NULL,
            cost_estimate decimal(12,2) DEFAULT NULL,
            final_cost decimal(12,2) DEFAULT NULL,
            scheduled_date datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            internal_notes text DEFAULT NULL,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY building_id (building_id),
            KEY unit_id (unit_id),
            KEY tenant_id (tenant_id),
            KEY status (status),
            KEY priority (priority),
            KEY category (category),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);

        // Work order notes
        $sql = "CREATE TABLE {$tables['work_order_notes']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            work_order_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            user_type enum('staff','tenant','vendor') NOT NULL,
            note text NOT NULL,
            is_internal tinyint(1) DEFAULT 0,
            attachments longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY work_order_id (work_order_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Work order vendor assignments
        $sql = "CREATE TABLE {$tables['work_order_vendors']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            work_order_id bigint(20) UNSIGNED NOT NULL,
            vendor_id bigint(20) UNSIGNED NOT NULL,
            status enum('assigned','accepted','declined','completed') DEFAULT 'assigned',
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            accepted_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            cost decimal(12,2) DEFAULT NULL,
            notes text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY work_order_id (work_order_id),
            KEY vendor_id (vendor_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        // Scheduled maintenance
        $sql = "CREATE TABLE {$tables['scheduled_maintenance']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            category enum('plumbing','electrical','hvac','appliance','structural','pest','cleaning','general','other') DEFAULT 'other',
            frequency enum('weekly','monthly','quarterly','semi_annual','annual','custom') DEFAULT 'monthly',
            frequency_custom_days int DEFAULT NULL,
            buildings longtext DEFAULT NULL,
            units longtext DEFAULT NULL,
            auto_assign_vendor_id bigint(20) UNSIGNED DEFAULT NULL,
            checklist longtext DEFAULT NULL,
            next_due_date date DEFAULT NULL,
            last_generated_at datetime DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY is_active (is_active),
            KEY next_due_date (next_due_date)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // SYSTEM & AUTH
        // ============================================

        $sql = "CREATE TABLE {$tables['magic_links']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            token varchar(255) NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            used_at datetime DEFAULT NULL,
            ip_address varchar(100) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            UNIQUE KEY token (token),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // PAYMENTS
        // ============================================

        $sql = "CREATE TABLE {$tables['payments']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            lease_id bigint(20) UNSIGNED DEFAULT NULL,
            tenant_id bigint(20) UNSIGNED DEFAULT NULL,
            paid_by_user_id bigint(20) UNSIGNED DEFAULT NULL,
            parent_payment_id bigint(20) UNSIGNED DEFAULT NULL,
            payment_number varchar(50) NOT NULL,
            type enum('rent','deposit','late_fee','damage','other','refund') DEFAULT 'rent',
            method enum('stripe_card','stripe_ach','cash','check','money_order','other','external') DEFAULT 'stripe_card',
            stripe_payment_intent_id varchar(255) DEFAULT NULL,
            stripe_charge_id varchar(255) DEFAULT NULL,
            payment_method_id bigint(20) UNSIGNED DEFAULT NULL,
            amount decimal(12,2) NOT NULL DEFAULT 0,
            amount_paid decimal(12,2) NOT NULL DEFAULT 0,
            platform_fee decimal(12,2) DEFAULT 0,
            stripe_fee decimal(12,2) DEFAULT 0,
            net_amount decimal(12,2) DEFAULT 0,
            currency varchar(3) DEFAULT 'USD',
            status enum('pending','processing','succeeded','partially_paid','failed','refunded','cancelled') DEFAULT 'pending',
            due_date date DEFAULT NULL,
            paid_at datetime DEFAULT NULL,
            period_start date DEFAULT NULL,
            period_end date DEFAULT NULL,
            description text DEFAULT NULL,
            notes text DEFAULT NULL,
            is_prorated tinyint(1) DEFAULT 0,
            proration_days int DEFAULT NULL,
            receipt_url varchar(500) DEFAULT NULL,
            failure_reason text DEFAULT NULL,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY payment_number (payment_number),
            KEY organization_id (organization_id),
            KEY lease_id (lease_id),
            KEY tenant_id (tenant_id),
            KEY status (status),
            KEY type (type),
            KEY due_date (due_date),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);

        // Payment items (line items)
        $sql = "CREATE TABLE {$tables['payment_items']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            payment_id bigint(20) UNSIGNED NOT NULL,
            description varchar(500) NOT NULL,
            type enum('rent','deposit','late_fee','damage','utility','credit','discount','other') DEFAULT 'other',
            amount decimal(12,2) NOT NULL DEFAULT 0,
            period_start date DEFAULT NULL,
            period_end date DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY payment_id (payment_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Payment plans
        $sql = "CREATE TABLE {$tables['payment_plans']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            lease_id bigint(20) UNSIGNED NOT NULL,
            total_amount decimal(12,2) NOT NULL,
            installments int NOT NULL,
            status enum('active','completed','cancelled','defaulted') DEFAULT 'active',
            late_fee_enabled tinyint(1) DEFAULT 0,
            late_fee_amount decimal(10,2) DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY lease_id (lease_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        // Payment plan items
        $sql = "CREATE TABLE {$tables['payment_plan_items']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            payment_plan_id bigint(20) UNSIGNED NOT NULL,
            amount decimal(12,2) NOT NULL,
            due_date date NOT NULL,
            status enum('pending','paid','overdue') DEFAULT 'pending',
            payment_id bigint(20) UNSIGNED DEFAULT NULL,
            paid_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY payment_plan_id (payment_plan_id),
            KEY due_date (due_date)
        ) $charset_collate;";
        dbDelta($sql);

        // Deposits
        $sql = "CREATE TABLE {$tables['deposits']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            lease_id bigint(20) UNSIGNED NOT NULL,
            amount decimal(12,2) NOT NULL,
            status enum('held','partially_refunded','refunded','forfeited') DEFAULT 'held',
            payment_id bigint(20) UNSIGNED DEFAULT NULL,
            refund_amount decimal(12,2) DEFAULT NULL,
            refund_payment_id bigint(20) UNSIGNED DEFAULT NULL,
            refund_method enum('stripe','external') DEFAULT NULL,
            reconciled_at datetime DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY lease_id (lease_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        // Deposit deductions
        $sql = "CREATE TABLE {$tables['deposit_deductions']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            deposit_id bigint(20) UNSIGNED NOT NULL,
            category enum('cleaning','repairs','unpaid_rent','key_replacement','other') NOT NULL,
            description text NOT NULL,
            amount decimal(12,2) NOT NULL,
            photos longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY deposit_id (deposit_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Vendor payouts
        $sql = "CREATE TABLE {$tables['vendor_payouts']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            vendor_id bigint(20) UNSIGNED NOT NULL,
            work_order_id bigint(20) UNSIGNED DEFAULT NULL,
            amount decimal(12,2) NOT NULL,
            status enum('pending','processing','completed','failed') DEFAULT 'pending',
            stripe_transfer_id varchar(255) DEFAULT NULL,
            paid_at datetime DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY vendor_id (vendor_id),
            KEY work_order_id (work_order_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // PLANS & SUBSCRIPTIONS
        // ============================================

        $sql = "CREATE TABLE {$tables['plans']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            slug varchar(50) NOT NULL,
            name varchar(100) NOT NULL,
            description text DEFAULT NULL,
            price_monthly decimal(10,2) DEFAULT 0,
            price_yearly decimal(10,2) DEFAULT 0,
            trial_days int DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            is_featured tinyint(1) DEFAULT 0,
            sort_order int DEFAULT 0,
            limits_buildings int DEFAULT 1,
            limits_units int DEFAULT 5,
            limits_staff int DEFAULT 0,
            limits_tenants int DEFAULT 5,
            limits_vendors int DEFAULT 0,
            limits_work_orders_monthly int DEFAULT -1,
            limits_applications_monthly int DEFAULT -1,
            limits_images_per_building int DEFAULT 5,
            limits_ai_credits_monthly int DEFAULT 0,
            feature_tenant_portal tinyint(1) DEFAULT 0,
            feature_maintenance tinyint(1) DEFAULT 0,
            feature_vendor_module tinyint(1) DEFAULT 0,
            feature_online_payments tinyint(1) DEFAULT 0,
            feature_advanced_reports tinyint(1) DEFAULT 0,
            feature_marketing_tools tinyint(1) DEFAULT 0,
            feature_ai_tools tinyint(1) DEFAULT 0,
            feature_api_access tinyint(1) DEFAULT 0,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) $charset_collate;";
        dbDelta($sql);

        // Subscriptions
        $sql = "CREATE TABLE {$tables['subscriptions']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            plan_id bigint(20) UNSIGNED DEFAULT NULL,
            plan_slug varchar(50) DEFAULT 'free',
            status enum('pending_payment','active','cancelled','past_due','trialing','expired') DEFAULT 'active',
            billing_cycle enum('monthly','yearly') DEFAULT 'monthly',
            current_period_start datetime DEFAULT NULL,
            current_period_end datetime DEFAULT NULL,
            trial_end datetime DEFAULT NULL,
            cancel_at_period_end tinyint(1) DEFAULT 0,
            cancelled_at datetime DEFAULT NULL,
            stripe_subscription_id varchar(255) DEFAULT NULL,
            stripe_customer_id varchar(255) DEFAULT NULL,
            amount decimal(10,2) DEFAULT 0,
            currency varchar(3) DEFAULT 'USD',
            ai_credits_used int DEFAULT 0,
            ai_credits_reset_at datetime DEFAULT NULL,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY organization_id (organization_id),
            KEY plan_id (plan_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        // Invoices
        $sql = "CREATE TABLE {$tables['invoices']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            subscription_id bigint(20) UNSIGNED DEFAULT NULL,
            invoice_number varchar(50) NOT NULL,
            amount decimal(12,2) NOT NULL,
            tax decimal(12,2) DEFAULT 0,
            total decimal(12,2) NOT NULL,
            currency varchar(3) DEFAULT 'USD',
            status enum('draft','pending','paid','failed','refunded') DEFAULT 'pending',
            due_date date DEFAULT NULL,
            paid_at datetime DEFAULT NULL,
            stripe_invoice_id varchar(255) DEFAULT NULL,
            pdf_url varchar(500) DEFAULT NULL,
            items longtext DEFAULT NULL,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY subscription_id (subscription_id),
            KEY status (status),
            UNIQUE KEY idx_invoice_number (invoice_number)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // STRIPE
        // ============================================

        $sql = "CREATE TABLE {$tables['stripe_accounts']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            stripe_account_id varchar(255) NOT NULL,
            account_type enum('express','standard') DEFAULT 'express',
            status enum('pending','active','restricted','disabled') DEFAULT 'pending',
            charges_enabled tinyint(1) DEFAULT 0,
            payouts_enabled tinyint(1) DEFAULT 0,
            details_submitted tinyint(1) DEFAULT 0,
            business_name varchar(255) DEFAULT NULL,
            country varchar(2) DEFAULT 'US',
            default_currency varchar(3) DEFAULT 'USD',
            meta_data longtext DEFAULT NULL,
            connected_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY organization_id (organization_id),
            KEY stripe_account_id (stripe_account_id)
        ) $charset_collate;";
        dbDelta($sql);

        $sql = "CREATE TABLE {$tables['payment_methods']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            stripe_payment_method_id varchar(255) NOT NULL,
            type enum('card','us_bank_account') DEFAULT 'card',
            card_brand varchar(50) DEFAULT NULL,
            card_last4 varchar(4) DEFAULT NULL,
            card_exp_month int DEFAULT NULL,
            card_exp_year int DEFAULT NULL,
            bank_name varchar(255) DEFAULT NULL,
            bank_last4 varchar(4) DEFAULT NULL,
            is_default tinyint(1) DEFAULT 0,
            is_verified tinyint(1) DEFAULT 1,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY stripe_payment_method_id (stripe_payment_method_id)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // DOCUMENTS
        // ============================================

        $sql = "CREATE TABLE {$tables['documents']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            attachment_id bigint(20) UNSIGNED NOT NULL,
            entity_type enum('building','unit','tenant','lease','application','work_order','vendor') NOT NULL,
            entity_id bigint(20) UNSIGNED NOT NULL,
            document_type varchar(100) DEFAULT NULL,
            title varchar(255) NOT NULL,
            description text DEFAULT NULL,
            file_url varchar(500) NOT NULL,
            file_name varchar(255) NOT NULL,
            file_size bigint(20) UNSIGNED DEFAULT NULL,
            mime_type varchar(100) DEFAULT NULL,
            is_private tinyint(1) DEFAULT 1,
            uploaded_by bigint(20) UNSIGNED DEFAULT NULL,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY entity_type_id (entity_type, entity_id),
            KEY uploaded_by (uploaded_by)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // COMMUNICATIONS
        // ============================================

        $sql = "CREATE TABLE {$tables['message_threads']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            participant_1_id bigint(20) UNSIGNED NOT NULL,
            participant_1_type enum('staff','tenant','vendor') NOT NULL,
            participant_2_id bigint(20) UNSIGNED NOT NULL,
            participant_2_type enum('staff','tenant','vendor') NOT NULL,
            work_order_id bigint(20) UNSIGNED DEFAULT NULL,
            last_message_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY participant_1 (participant_1_id, participant_1_type),
            KEY participant_2 (participant_2_id, participant_2_type),
            KEY work_order_id (work_order_id)
        ) $charset_collate;";
        dbDelta($sql);

        $sql = "CREATE TABLE {$tables['messages']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            thread_id bigint(20) UNSIGNED NOT NULL,
            sender_id bigint(20) UNSIGNED NOT NULL,
            sender_type enum('staff','tenant','vendor') NOT NULL,
            message text NOT NULL,
            attachments longtext DEFAULT NULL,
            read_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY thread_id (thread_id),
            KEY sender (sender_id, sender_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);

        $sql = "CREATE TABLE {$tables['announcements']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            audience_type enum('all','buildings','units') DEFAULT 'all',
            audience_ids longtext DEFAULT NULL,
            delivery enum('immediate','scheduled') DEFAULT 'immediate',
            scheduled_at datetime DEFAULT NULL,
            channels enum('in_app','email','both') DEFAULT 'both',
            sent_at datetime DEFAULT NULL,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY sent_at (sent_at)
        ) $charset_collate;";
        dbDelta($sql);

        $sql = "CREATE TABLE {$tables['announcement_recipients']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            announcement_id bigint(20) UNSIGNED NOT NULL,
            tenant_id bigint(20) UNSIGNED NOT NULL,
            read_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY announcement_id (announcement_id),
            KEY tenant_id (tenant_id)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // MOVE-IN / MOVE-OUT
        // ============================================

        $sql = "CREATE TABLE {$tables['move_checklists']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            lease_id bigint(20) UNSIGNED NOT NULL,
            type enum('move_in','move_out') NOT NULL,
            status enum('pending','in_progress','completed') DEFAULT 'pending',
            items longtext DEFAULT NULL,
            completed_items longtext DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            completed_by bigint(20) UNSIGNED DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY lease_id (lease_id),
            KEY type (type),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        $sql = "CREATE TABLE {$tables['condition_reports']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            unit_id bigint(20) UNSIGNED NOT NULL,
            lease_id bigint(20) UNSIGNED NOT NULL,
            type enum('move_in','move_out') NOT NULL,
            status enum('draft','pending_tenant','signed') DEFAULT 'draft',
            inspected_at datetime DEFAULT NULL,
            inspected_by bigint(20) UNSIGNED DEFAULT NULL,
            tenant_signed_at datetime DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY unit_id (unit_id),
            KEY lease_id (lease_id),
            KEY type (type)
        ) $charset_collate;";
        dbDelta($sql);

        $sql = "CREATE TABLE {$tables['condition_items']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            condition_report_id bigint(20) UNSIGNED NOT NULL,
            room varchar(100) NOT NULL,
            item varchar(255) DEFAULT NULL,
            item_condition enum('good','fair','poor') DEFAULT 'good',
            notes text DEFAULT NULL,
            photos longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY condition_report_id (condition_report_id)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // MARKETING
        // ============================================

        $sql = "CREATE TABLE {$tables['flyers']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            type varchar(50) NOT NULL DEFAULT 'unit',
            entity_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            template varchar(100) DEFAULT 'modern',
            title varchar(255) DEFAULT NULL,
            subtitle varchar(255) DEFAULT NULL,
            description text DEFAULT NULL,
            highlight_features text DEFAULT NULL,
            contact_info text DEFAULT NULL,
            qr_code_id bigint(20) UNSIGNED DEFAULT NULL,
            include_qr tinyint(1) DEFAULT 1,
            custom_colors longtext DEFAULT NULL,
            file_url varchar(500) DEFAULT NULL,
            thumbnail_url varchar(500) DEFAULT NULL,
            status varchar(50) DEFAULT 'draft',
            generated_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY type_entity (type, entity_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        $sql = "CREATE TABLE {$tables['qr_codes']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            type varchar(50) NOT NULL DEFAULT 'building',
            entity_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            code varchar(100) NOT NULL,
            destination_url varchar(500) DEFAULT NULL,
            label varchar(255) DEFAULT NULL,
            file_url varchar(500) DEFAULT NULL,
            scan_count int(11) UNSIGNED NOT NULL DEFAULT 0,
            last_scanned_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY type_entity (type, entity_id),
            UNIQUE KEY code (code)
        ) $charset_collate;";
        dbDelta($sql);

        $sql = "CREATE TABLE {$tables['qr_scans']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            qr_code_id bigint(20) UNSIGNED NOT NULL,
            organization_id bigint(20) UNSIGNED DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            referrer varchar(500) DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            device_type varchar(50) DEFAULT 'desktop',
            location_data longtext DEFAULT NULL,
            scanned_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY qr_code_id (qr_code_id),
            KEY organization_id (organization_id),
            KEY scanned_at (scanned_at),
            KEY device_type (device_type)
        ) $charset_collate;";
        dbDelta($sql);

        // Marketing campaigns
        $sql = "CREATE TABLE {$tables['marketing_campaigns']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            type varchar(50) NOT NULL DEFAULT 'multi_channel',
            status varchar(50) NOT NULL DEFAULT 'draft',
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            budget decimal(10,2) DEFAULT NULL,
            goal text DEFAULT NULL,
            description text DEFAULT NULL,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY status (status),
            KEY type (type),
            KEY start_date (start_date)
        ) $charset_collate;";
        dbDelta($sql);

        // Marketing automation rules
        $sql = "CREATE TABLE {$tables['marketing_automation_rules']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            trigger_type varchar(50) NOT NULL,
            trigger_conditions longtext DEFAULT NULL,
            action_type varchar(50) NOT NULL,
            action_data longtext DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY trigger_type (trigger_type),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // AI
        // ============================================

        $sql = "CREATE TABLE {$tables['ai_usage']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            tool enum('description','maintenance','screening','marketing','message','insights') NOT NULL,
            credits_used int NOT NULL,
            input_data longtext DEFAULT NULL,
            output_data longtext DEFAULT NULL,
            provider enum('openai','gemini') DEFAULT 'openai',
            model varchar(100) DEFAULT NULL,
            tokens_used int DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY user_id (user_id),
            KEY tool (tool),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);

        $sql = "CREATE TABLE {$tables['ai_screenings']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            application_id bigint(20) UNSIGNED NOT NULL,
            risk_score enum('low','medium','high') DEFAULT NULL,
            strength_factors longtext DEFAULT NULL,
            concern_factors longtext DEFAULT NULL,
            summary text DEFAULT NULL,
            recommendation enum('approve','review','decline') DEFAULT NULL,
            raw_response longtext DEFAULT NULL,
            credits_used int DEFAULT 0,
            screened_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY application_id (application_id)
        ) $charset_collate;";
        dbDelta($sql);

        // AI Credit Balances - tracks credit pools per organization
        $sql = "CREATE TABLE {$wpdb->prefix}rg_ai_credit_balances (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            subscription_credits int DEFAULT 0,
            purchased_credits int DEFAULT 0,
            bonus_credits int DEFAULT 0,
            cycle_start datetime DEFAULT NULL,
            cycle_end datetime DEFAULT NULL,
            last_refresh datetime DEFAULT NULL,
            rollover_credits int DEFAULT 0,
            rollover_expires datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY organization_id (organization_id)
        ) $charset_collate;";
        dbDelta($sql);

        // AI Credit Transactions - audit log of all credit movements
        $sql = "CREATE TABLE {$wpdb->prefix}rg_ai_credit_transactions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            type enum('subscription_grant','purchase','bonus','admin_adjustment','usage','refund','expiry','rollover') NOT NULL,
            credits int NOT NULL,
            credit_type enum('subscription','purchased','bonus') DEFAULT 'subscription',
            balance_before int NOT NULL DEFAULT 0,
            balance_after int NOT NULL DEFAULT 0,
            reference_type varchar(50) DEFAULT NULL,
            reference_id varchar(100) DEFAULT NULL,
            description varchar(255) DEFAULT NULL,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY type (type),
            KEY credit_type (credit_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);

        // AI Credit Packs - purchasable credit bundles
        $sql = "CREATE TABLE {$wpdb->prefix}rg_ai_credit_packs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(50) NOT NULL,
            description varchar(255) DEFAULT NULL,
            credits int NOT NULL,
            price decimal(10,2) NOT NULL,
            currency varchar(3) DEFAULT 'USD',
            stripe_price_id varchar(100) DEFAULT NULL,
            sort_order int DEFAULT 0,
            is_featured tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            badge_text varchar(50) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
        dbDelta($sql);

        // AI Credit Purchases - purchase history
        $sql = "CREATE TABLE {$wpdb->prefix}rg_ai_credit_purchases (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            pack_id bigint(20) UNSIGNED DEFAULT NULL,
            credits int NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) DEFAULT 'USD',
            status enum('pending','completed','failed','refunded') DEFAULT 'pending',
            stripe_payment_intent_id varchar(100) DEFAULT NULL,
            stripe_charge_id varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        // ============================================
        // SYSTEM
        // ============================================

        $sql = "CREATE TABLE {$tables['notifications']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            organization_id bigint(20) UNSIGNED DEFAULT NULL,
            type varchar(100) NOT NULL,
            title varchar(255) NOT NULL,
            message text DEFAULT NULL,
            action_url varchar(500) DEFAULT NULL,
            is_read tinyint(1) DEFAULT 0,
            read_at datetime DEFAULT NULL,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY organization_id (organization_id),
            KEY is_read (is_read),
            KEY type (type),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);

        $sql = "CREATE TABLE {$tables['notification_preferences']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            notification_type varchar(100) NOT NULL,
            channel enum('in_app','email','both','none') DEFAULT 'both',
            frequency enum('immediate','daily','weekly') DEFAULT 'immediate',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_type (user_id, notification_type)
        ) $charset_collate;";
        dbDelta($sql);

        $sql = "CREATE TABLE {$tables['activity_log']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            organization_id bigint(20) UNSIGNED DEFAULT NULL,
            action varchar(100) NOT NULL,
            entity_type varchar(50) DEFAULT NULL,
            entity_id bigint(20) UNSIGNED DEFAULT NULL,
            old_values longtext DEFAULT NULL,
            new_values longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(500) DEFAULT NULL,
            is_support_mode tinyint(1) DEFAULT 0,
            support_reason text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY organization_id (organization_id),
            KEY action (action),
            KEY entity_type_id (entity_type, entity_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);

        $sql = "CREATE TABLE {$tables['settings']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED DEFAULT NULL,
            setting_key varchar(100) NOT NULL,
            setting_value longtext DEFAULT NULL,
            is_global tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_setting (organization_id, setting_key),
            KEY is_global (is_global)
        ) $charset_collate;";
        dbDelta($sql);

        $sql = "CREATE TABLE {$tables['staff_permissions']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            module varchar(100) NOT NULL,
            permission_level enum('none','view','full') DEFAULT 'none',
            building_ids longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY user_id (user_id),
            UNIQUE KEY org_user_module (organization_id, user_id, module)
        ) $charset_collate;";
        dbDelta($sql);

        // Seed default plans
        $this->seed_default_plans($tables['plans']);
    }

    /**
     * Seed default subscription plans
     */
    private function seed_default_plans($table)
    {
        global $wpdb;

        // Check if plans already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($count > 0) {
            return;
        }

        $default_plans = array(
            array(
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'Get started with basic features',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'trial_days' => 0,
                'is_active' => 1,
                'is_featured' => 0,
                'sort_order' => 1,
                'limits_buildings' => 1,
                'limits_units' => 3,
                'limits_staff' => 0,
                'limits_tenants' => 3,
                'limits_vendors' => 0,
                'limits_work_orders_monthly' => 5,
                'limits_applications_monthly' => 3,
                'limits_images_per_building' => 3,
                'limits_ai_credits_monthly' => 0,
                'feature_tenant_portal' => 0,
                'feature_maintenance' => 0,
                'feature_vendor_module' => 0,
                'feature_online_payments' => 0,
                'feature_advanced_reports' => 0,
                'feature_marketing_tools' => 0,
                'feature_ai_tools' => 0,
                'feature_api_access' => 0,
            ),
            array(
                'slug' => 'starter',
                'name' => 'Starter',
                'description' => 'For small landlords (up to 10 units)',
                'price_monthly' => 19,
                'price_yearly' => 180, // $15/mo billed yearly
                'trial_days' => 14,
                'is_active' => 1,
                'is_featured' => 0,
                'sort_order' => 2,
                'limits_buildings' => 3,
                'limits_units' => 10,
                'limits_staff' => 1,
                'limits_tenants' => 10,
                'limits_vendors' => 3,
                'limits_work_orders_monthly' => -1,
                'limits_applications_monthly' => -1,
                'limits_images_per_building' => 10,
                'limits_ai_credits_monthly' => 20,
                'feature_tenant_portal' => 1,
                'feature_maintenance' => 1,
                'feature_vendor_module' => 0,
                'feature_online_payments' => 1,
                'feature_advanced_reports' => 0,
                'feature_marketing_tools' => 1,
                'feature_ai_tools' => 0,
                'feature_api_access' => 0,
            ),
            array(
                'slug' => 'professional',
                'name' => 'Professional',
                'description' => 'For growing businesses (up to 50 units)',
                'price_monthly' => 49,
                'price_yearly' => 468, // $39/mo billed yearly
                'trial_days' => 14,
                'is_active' => 1,
                'is_featured' => 1,
                'sort_order' => 3,
                'limits_buildings' => 10,
                'limits_units' => 50,
                'limits_staff' => 5,
                'limits_tenants' => 50,
                'limits_vendors' => 10,
                'limits_work_orders_monthly' => -1,
                'limits_applications_monthly' => -1,
                'limits_images_per_building' => 20,
                'limits_ai_credits_monthly' => 100,
                'feature_tenant_portal' => 1,
                'feature_maintenance' => 1,
                'feature_vendor_module' => 1,
                'feature_online_payments' => 1,
                'feature_advanced_reports' => 1,
                'feature_marketing_tools' => 1,
                'feature_ai_tools' => 1,
                'feature_api_access' => 0,
            ),
            array(
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'For large portfolios (unlimited units)',
                'price_monthly' => 149,
                'price_yearly' => 1428, // $119/mo billed yearly
                'trial_days' => 14,
                'is_active' => 1,
                'is_featured' => 0,
                'sort_order' => 4,
                'limits_buildings' => -1,
                'limits_units' => -1,
                'limits_staff' => -1,
                'limits_tenants' => -1,
                'limits_vendors' => -1,
                'limits_work_orders_monthly' => -1,
                'limits_applications_monthly' => -1,
                'limits_images_per_building' => -1,
                'limits_ai_credits_monthly' => 500,
                'feature_tenant_portal' => 1,
                'feature_maintenance' => 1,
                'feature_vendor_module' => 1,
                'feature_online_payments' => 1,
                'feature_advanced_reports' => 1,
                'feature_marketing_tools' => 1,
                'feature_ai_tools' => 1,
                'feature_api_access' => 1,
            ),
        );

        foreach ($default_plans as $plan) {
            $plan['created_at'] = current_time('mysql');
            $plan['updated_at'] = current_time('mysql');
            $wpdb->insert($table, $plan);
        }
    }

    /**
     * Drop all tables (for uninstall)
     */
    public static function drop_tables()
    {
        global $wpdb;

        $tables = self::get_table_names();

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }
}
