# Phase 2: Architecture Modernization - Implementation Plan

**Project:** Rental Gates v2.41.0
**Date:** 2026-02-09
**Prerequisite:** Phase 1 (Security Hardening) completed
**Scope:** Monolith decomposition, autoloading, API consolidation, database hardening, model layer, test suite

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Current State Analysis](#2-current-state-analysis)
3. [WS-1: Monolith Decomposition](#ws-1-monolith-decomposition---extract-the-7809-line-main-file)
4. [WS-2: PSR-4 Autoloader](#ws-2-psr-4-autoloader)
5. [WS-3: Service Layer & API Consolidation](#ws-3-service-layer--api-consolidation)
6. [WS-4: Base Model & Model Layer Refactor](#ws-4-base-model--model-layer-refactor)
7. [WS-5: Database Migration Framework](#ws-5-database-migration-framework)
8. [WS-6: Fix Feature Gate & Cache Bugs](#ws-6-fix-feature-gate--cache-bugs)
9. [WS-7: Dead Code Removal](#ws-7-dead-code-removal)
10. [WS-8: PHPUnit Test Suite](#ws-8-phpunit-test-suite)
11. [Dependency Graph & Execution Order](#dependency-graph--execution-order)
12. [Testing Strategy](#testing-strategy)
13. [Rollout Plan](#rollout-plan)
14. [Risk Assessment](#risk-assessment)

---

## 1. Executive Summary

Phase 2 transforms the Rental Gates plugin from a monolithic, organically-grown codebase into a properly structured, testable, and maintainable architecture. This plan is based on a line-by-line analysis of every file involved.

### Key Metrics (Before -> After)

| Metric | Current | After Phase 2 |
|--------|---------|---------------|
| Main file (`rental-gates.php`) | 7,809 lines | ~480 lines |
| Files loaded per request | 64 (all) | 15-20 (on-demand) |
| AJAX/REST duplication | 38 duplicate pairs | 0 (unified service layer) |
| Model boilerplate duplication | ~40-50% per model | ~5% (base class) |
| Dead code | ~1,100 lines (Loader + Validation) | 0 |
| Functional test coverage | 0% | Core CRUD + auth + billing covered |
| Database migration safety | No transactions, no version gating | Transactional, version-gated |
| Feature gate correctness | Broken (wrong table names, wrong plan names) | Fixed and tested |
| Cache group flushing | Non-functional | Working |

### What This Plan Does NOT Do

- Does not change any public URL, REST endpoint path, or AJAX action name
- Does not change the database schema (that's Phase 5)
- Does not add new features
- Does not change the frontend (JS/CSS/templates)

---

## 2. Current State Analysis

### The Main File Problem

`rental-gates.php` is 7,809 lines. Here is the exact breakdown:

| Section | Lines | % of File |
|---------|-------|-----------|
| Constants & class shell | 1-100 | 1.3% |
| `load_dependencies()` - 64 require_once | 101-229 | 1.6% |
| `init_hooks()` - 114 AJAX registrations + WP hooks | 230-455 | 2.9% |
| DB upgrade methods | 456-600 | 1.8% |
| `init()`, `init_api()`, routing, enqueue | 601-900 | 3.8% |
| `wp_localize_script` / data output | 901-1100 | 2.6% |
| `enqueue_public_assets` / `enqueue_admin_assets` | 1100-1400 | 3.8% |
| Template routing (`handle_custom_routes`) | 1400-1680 | 3.6% |
| **AJAX handlers (85 functions)** | **1681-7750** | **78.5%** |
| Singleton bootstrap | 7751-7809 | 0.8% |

**78.5% of the file is AJAX handler implementations** - this is the extraction target.

### The Dual API Problem

There are 114 AJAX action registrations and 98 REST route registrations. Deep comparison reveals:

| Pattern | Count | Risk |
|---------|-------|------|
| AJAX handler has feature gate check, REST does not | 3+ (buildings, units, bulk) | **Billing bypass via REST** |
| AJAX fires no hooks, REST fires `do_action` | 4+ (maintenance, payments) | **Missed notifications via AJAX** |
| AJAX has org-ownership check, REST does not | 5+ (delete building, etc.) | **IDOR via REST** (fixed in Phase 1) |
| AJAX has extra business logic REST lacks | 3+ (payment amount_paid auto-fill) | **Data inconsistency** |
| Both call model directly with no shared layer | All pairs | **Guaranteed future drift** |

### The Model Layer Problem

22 model files with zero inheritance. Every model repeats:
- `private static $table_name` + `self::init()` pattern (~15 lines each)
- `format_*()` for JSON field deserialization (~20-40 lines each)
- `generate_unique_slug()` (~25 lines each, in 5 models)
- Pagination query building (~15 lines each)
- `WP_Error` return pattern
- Cache integration calls

Only `Organization::delete()` uses transactions. `Payment::generate_monthly_payments()` creates dozens of records with no transaction.

### Critical Bugs Found During Analysis

| Bug | Location | Impact |
|-----|----------|--------|
| Feature Gate plan names (`basic/silver/gold`) don't match DB seeds (`starter/professional/enterprise`) | `class-rental-gates-feature-gate.php` lines 118-187 vs `class-rental-gates-database.php` lines 1636-1749 | **Paying customers treated as free tier** |
| Feature Gate `limit_definitions` reference non-existent table `rg_organization_users` | `class-rental-gates-feature-gate.php` lines 56-58 | **Staff/tenant limits never enforced** |
| Feature Gate `check_role()` method called but doesn't exist | `rental-gates.php` lines 5975, 6784; `class-rental-gates-billing.php` lines 508, 541 | **Fatal error at runtime** |
| Cache `flush_group()` never records keys to flush | `class-rental-gates-cache.php` lines 50, 68-82 | **Group flush is a no-op** |
| Cache `remember()` can't distinguish `false` from cache miss | `class-rental-gates-cache.php` line 90 | **Repeated unnecessary queries** |
| 4 AI credit tables not tracked in `get_table_names()` | `class-rental-gates-database.php` | **Not cleaned up on uninstall** |
| DB migrations ignore `$from_version` parameter | `class-rental-gates-database.php` line 133 | **All migrations run unconditionally** |
| `Rental_Gates_Loader` instantiated but never used | `rental-gates.php` line 223 | Dead code, wasted memory |
| `Rental_Gates_Validation` loaded but never called | `rental-gates.php` line 209 | Dead code with stale enums |
| Automation file loaded twice | `rental-gates.php` lines 154, 174 | Wasted parse time (PHP handles it) |

---

## WS-1: Monolith Decomposition - Extract the 7,809-Line Main File

### Goal

Reduce `rental-gates.php` from 7,809 lines to ~480 lines by extracting all AJAX handlers into domain-specific classes.

### Target Structure

```
rental-gates.php                         (~480 lines - bootstrap only)
includes/
  ajax/
    class-rental-gates-ajax-buildings.php    (building + unit AJAX handlers)
    class-rental-gates-ajax-tenants.php      (tenant AJAX handlers)
    class-rental-gates-ajax-leases.php       (lease AJAX handlers)
    class-rental-gates-ajax-applications.php (application AJAX handlers)
    class-rental-gates-ajax-payments.php     (payment + invoice AJAX handlers)
    class-rental-gates-ajax-maintenance.php  (work order AJAX handlers)
    class-rental-gates-ajax-vendors.php      (vendor AJAX handlers)
    class-rental-gates-ajax-messages.php     (message + announcement AJAX handlers)
    class-rental-gates-ajax-leads.php        (lead/CRM AJAX handlers)
    class-rental-gates-ajax-documents.php    (document + staff AJAX handlers)
    class-rental-gates-ajax-stripe.php       (Stripe + subscription AJAX handlers)
    class-rental-gates-ajax-marketing.php    (QR, flyers, analytics AJAX handlers)
    class-rental-gates-ajax-portals.php      (tenant portal + vendor portal AJAX handlers)
    class-rental-gates-ajax-ai.php           (AI generation + credit AJAX handlers)
    class-rental-gates-ajax-public.php       (public inquiry, contact form, forgot password)
  class-rental-gates-enqueue.php             (asset enqueuing + wp_localize_script)
  class-rental-gates-routing.php             (rewrite rules + template routing)
```

### What Stays in `rental-gates.php`

Only the minimal bootstrap:

```php
<?php
/**
 * Plugin Name: Rental Gates
 * ... (plugin header)
 */

if (!defined('ABSPATH')) exit;

// Constants
define('RENTAL_GATES_VERSION', '2.41.0');
define('RENTAL_GATES_DB_VERSION', '2.15.2');
define('RENTAL_GATES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RENTAL_GATES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RENTAL_GATES_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('RENTAL_GATES_COMING_SOON_WINDOW', 30);

// AI Credit costs
define('RENTAL_GATES_AI_CREDIT_DESCRIPTION', 2);
define('RENTAL_GATES_AI_CREDIT_MAINTENANCE', 1);
define('RENTAL_GATES_AI_CREDIT_SCREENING', 10);
define('RENTAL_GATES_AI_CREDIT_MARKETING', 2);
define('RENTAL_GATES_AI_CREDIT_MESSAGE', 1);
define('RENTAL_GATES_AI_CREDIT_INSIGHTS', 5);

// Autoloader (WS-2)
require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-autoloader.php';
Rental_Gates_Autoloader::register();

final class Rental_Gates {
    private static $instance = null;

    // Component references
    public $database;
    public $roles;
    public $api;
    public $auth;
    public $admin;
    public $public;
    public $user_restrictions;
    public $map_service;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_core();
        $this->init_hooks();
    }

    private function init_core() {
        $this->database = new Rental_Gates_Database();
        $this->roles    = new Rental_Gates_Roles();
        $this->auth     = new Rental_Gates_Auth();
        $this->public   = new Rental_Gates_Public();

        if (is_admin()) {
            $this->admin = new Rental_Gates_Admin();
        }

        $this->user_restrictions = new Rental_Gates_User_Restrictions();
        rental_gates_pwa();
    }

    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, array('Rental_Gates_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('Rental_Gates_Deactivator', 'deactivate'));

        // Core WordPress hooks
        add_action('init', array($this, 'init'), 0);
        add_action('rest_api_init', array($this, 'init_api'));
        add_action('admin_init', array($this, 'check_db_upgrade'));

        // Routing (delegated)
        $routing = new Rental_Gates_Routing();
        $routing->register_hooks();

        // Assets (delegated)
        $enqueue = new Rental_Gates_Enqueue();
        $enqueue->register_hooks();

        // AJAX handlers (delegated - each class self-registers)
        $this->init_ajax_handlers();

        // Cron
        add_action('rental_gates_availability_cron', array($this, 'run_availability_automation'));
        add_action('rental_gates_ai_credits_reset', array($this, 'reset_ai_credits'));
        add_action('rental_gates_subscription_expiration', array($this, 'check_subscription_expirations'));

        // Misc filters
        add_filter('redirect_canonical', array($this, 'prevent_canonical_redirect'), 10, 2);
        add_filter('retrieve_password_message', array($this, 'customize_password_reset_email'), 10, 4);
    }

    private function init_ajax_handlers() {
        // Each handler class registers its own wp_ajax_ actions in its constructor
        new Rental_Gates_Ajax_Buildings();
        new Rental_Gates_Ajax_Tenants();
        new Rental_Gates_Ajax_Leases();
        new Rental_Gates_Ajax_Applications();
        new Rental_Gates_Ajax_Payments();
        new Rental_Gates_Ajax_Maintenance();
        new Rental_Gates_Ajax_Vendors();
        new Rental_Gates_Ajax_Messages();
        new Rental_Gates_Ajax_Leads();
        new Rental_Gates_Ajax_Documents();
        new Rental_Gates_Ajax_Stripe();
        new Rental_Gates_Ajax_Marketing();
        new Rental_Gates_Ajax_Portals();
        new Rental_Gates_Ajax_AI();
        new Rental_Gates_Ajax_Public();
    }

    // ... init(), init_api(), check_db_upgrade(), cron handlers,
    //     prevent_canonical_redirect(), customize_password_reset_email()
    //     (these stay - ~100 lines total)

    // Map service accessor
    public function get_map_service() { /* existing logic */ }

    private function __clone() {}
    public function __wakeup() { throw new Exception('Cannot unserialize singleton'); }
}

function rental_gates() { return Rental_Gates::instance(); }
rental_gates();
```

### AJAX Handler Class Pattern

Each extracted class follows this pattern:

```php
<?php
// File: includes/ajax/class-rental-gates-ajax-buildings.php

if (!defined('ABSPATH')) exit;

class Rental_Gates_Ajax_Buildings {

    public function __construct() {
        add_action('wp_ajax_rental_gates_save_building', array($this, 'handle_save_building'));
        add_action('wp_ajax_rental_gates_save_unit', array($this, 'handle_save_unit'));
        add_action('wp_ajax_rental_gates_delete_building', array($this, 'handle_delete_building'));
        add_action('wp_ajax_rental_gates_delete_unit', array($this, 'handle_delete_unit'));
        add_action('wp_ajax_rental_gates_bulk_add_units', array($this, 'handle_bulk_add_units'));
        add_action('wp_ajax_rental_gates_geocode', array($this, 'handle_geocode_request'));
        add_action('wp_ajax_rental_gates_save_settings', array($this, 'handle_save_settings'));
        add_action('wp_ajax_rental_gates_upload_image', array($this, 'handle_image_upload'));
    }

    // Move these methods verbatim from rental-gates.php:
    // handle_save_building()     - was at lines 1928-2019
    // handle_save_unit()         - was at lines 2059-2153
    // handle_delete_building()   - was at lines 2153-2189
    // handle_delete_unit()       - was at lines 2189-2223
    // handle_bulk_add_units()    - was at lines 2223-2311
    // handle_geocode_request()   - was at lines 1871-1927
    // handle_save_settings()     - was at lines 2311-2380
    // handle_image_upload()      - was at lines 1681-1805

    // Helper methods used by multiple handlers in this class
    private function get_org_id() {
        return Rental_Gates_Roles::get_organization_id();
    }

    private function verify_nonce() {
        check_ajax_referer('rental_gates_nonce', 'nonce');
    }
}
```

### Extraction Map (All 15 Classes)

| Class | Handlers Moved | Lines Moved | Source Lines in rental-gates.php |
|-------|---------------|-------------|--------------------------------|
| `Ajax_Buildings` | 8 handlers | ~550 | 1681-2380 |
| `Ajax_Tenants` | 4 handlers | ~180 | 2520-2700 |
| `Ajax_Leases` | 9 handlers | ~500 | 2700-3200 |
| `Ajax_Applications` | 4 handlers | ~200 | 3850-4050 |
| `Ajax_Payments` | 9 handlers | ~500 | 3200-3700 |
| `Ajax_Maintenance` | 6 handlers | ~350 | 3410-3760 |
| `Ajax_Vendors` | 6 handlers | ~300 | 3760-4060 |
| `Ajax_Messages` | 4 handlers | ~250 | 4490-4740 |
| `Ajax_Leads` | 6 handlers | ~250 | 4230-4490 |
| `Ajax_Documents` | 4 handlers | ~200 | 4740-4940 |
| `Ajax_Stripe` | 11 handlers | ~700 | 5620-6320 |
| `Ajax_Marketing` | 8 handlers | ~450 | 1805-1870, scattered |
| `Ajax_Portals` | 7 handlers | ~400 | 4940-5340 |
| `Ajax_AI` | 3 handlers | ~200 | 7450-7650 |
| `Ajax_Public` | 3 handlers | ~100 | scattered |
| **Total** | **85 handlers** | **~6,130** | |

### Additional Extractions

**`class-rental-gates-enqueue.php`** (~300 lines):
- `enqueue_public_assets()` - currently at lines ~1100-1250
- `enqueue_admin_assets()` - currently at lines ~1250-1400
- `output_rental_gates_data_early()` - currently at lines ~901-1100
- All `wp_localize_script` calls

**`class-rental-gates-routing.php`** (~280 lines):
- `add_rewrite_rules()` - currently at lines ~700-800
- `add_query_vars()` - currently at lines ~800-830
- `handle_custom_routes()` - currently at lines ~1400-1680
- `prevent_canonical_redirect()`

### Verification

After extraction, `rental-gates.php` contains only:
- Plugin header (15 lines)
- Constants (17 lines)
- Autoloader require (2 lines)
- `Rental_Gates` class: constructor, `init_core()`, `init_hooks()`, `init_ajax_handlers()`, `init()`, `init_api()`, `check_db_upgrade()`, 3 cron handlers, `get_map_service()`, singleton boilerplate (~400 lines)
- Bootstrap call (5 lines)

**Total: ~480 lines** (down from 7,809)

---

## WS-2: PSR-4 Autoloader

### Goal

Replace 64 hardcoded `require_once` calls with an autoloader that loads classes on-demand. This reduces per-request memory by only loading the ~15-20 classes actually used.

### Implementation

**New file: `includes/class-rental-gates-autoloader.php`**

```php
<?php
if (!defined('ABSPATH')) exit;

class Rental_Gates_Autoloader {

    /**
     * Class name => file path mapping.
     * WordPress doesn't follow PSR-4, so we use an explicit map.
     */
    private static $class_map = array(
        // Core
        'Rental_Gates_Database'             => 'includes/class-rental-gates-database.php',
        'Rental_Gates_Roles'                => 'includes/class-rental-gates-roles.php',
        'Rental_Gates_Activator'            => 'includes/class-rental-gates-activator.php',
        'Rental_Gates_Deactivator'          => 'includes/class-rental-gates-deactivator.php',

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

        // Services
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

        // Service layer (new in Phase 2)
        'Rental_Gates_Service_Buildings'    => 'includes/services/class-rental-gates-service-buildings.php',
        'Rental_Gates_Service_Payments'     => 'includes/services/class-rental-gates-service-payments.php',
        'Rental_Gates_Service_Maintenance'  => 'includes/services/class-rental-gates-service-maintenance.php',
    );

    /**
     * Register the autoloader.
     */
    public static function register() {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }

    /**
     * Autoload a class by name.
     */
    public static function autoload($class_name) {
        if (isset(self::$class_map[$class_name])) {
            $file = RENTAL_GATES_PLUGIN_DIR . self::$class_map[$class_name];
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
}
```

### Why a Class Map (Not PSR-4 Convention)

WordPress class naming (`Rental_Gates_Building`) doesn't map cleanly to PSR-4 directory conventions. A class map is:
- Explicit and debuggable (you can see exactly where every class loads from)
- Fast (`isset()` on an array is O(1))
- Compatible with WordPress coding standards
- No Composer dependency required

### Migration Steps

1. Create `class-rental-gates-autoloader.php`
2. In `rental-gates.php`, replace the entire `load_dependencies()` method with:
   ```php
   require_once RENTAL_GATES_PLUGIN_DIR . 'includes/class-rental-gates-autoloader.php';
   Rental_Gates_Autoloader::register();
   ```
3. Eagerly instantiate only the classes that register WordPress hooks in their constructors (listed in `init_core()` above)
4. All other classes load on first reference via `spl_autoload`

### What Loads Eagerly vs Lazily

| Load Timing | Classes | Reason |
|-------------|---------|--------|
| **Eager** (in constructor) | Database, Roles, Auth, Public, Admin, User_Restrictions, PWA, Routing, Enqueue, 15 Ajax_* classes | They register hooks in their constructors |
| **Lazy** (on first use) | All 22 Models, Email, PDF, Stripe, AI, AI_Credits, Cache, Security, Rate_Limit, Feature_Gate, Analytics, Map services | Only loaded when a request actually needs them |

---

## WS-3: Service Layer & API Consolidation

### Goal

Eliminate AJAX/REST behavioral drift by creating shared service classes that both APIs call. Start with the 3 domains that have the most dangerous divergence.

### Architecture

```
Frontend JS
    |                    |
    v                    v
AJAX Handler         REST API Handler
    |                    |
    +-----> Service <----+
               |
               v
             Model
```

### Priority Services (Phase 2 scope)

Only create service classes for domains where AJAX/REST divergence causes bugs or security issues.

#### Service 1: Building Service

**File: `includes/services/class-rental-gates-service-buildings.php`**

```php
<?php
if (!defined('ABSPATH')) exit;

class Rental_Gates_Service_Buildings {

    /**
     * Create a building with feature gate enforcement and proper sanitization.
     *
     * @param array $data     Building data
     * @param int   $org_id   Organization ID
     * @param int   $user_id  Acting user ID
     * @return array|WP_Error Created building or error
     */
    public static function create($data, $org_id, $user_id = null) {
        // Feature gate check (was only in AJAX, missing from REST)
        $gate_check = rg_can_create('buildings', 1, $org_id);
        if (!$gate_check['allowed']) {
            return new WP_Error('limit_reached', $gate_check['message'], array('upgrade' => true));
        }

        // Sanitize
        $clean = array(
            'organization_id' => $org_id,
            'name'            => sanitize_text_field($data['name'] ?? ''),
            'address'         => sanitize_text_field($data['address'] ?? ''),
            'city'            => sanitize_text_field($data['city'] ?? ''),
            'state'           => sanitize_text_field($data['state'] ?? ''),
            'zip'             => sanitize_text_field($data['zip'] ?? ''),
            'country'         => sanitize_text_field($data['country'] ?? 'US'),
            'description'     => sanitize_textarea_field($data['description'] ?? ''),
            'building_type'   => sanitize_text_field($data['building_type'] ?? 'residential'),
            'featured_image'  => esc_url_raw($data['featured_image'] ?? ''),
            'gallery'         => $data['gallery'] ?? array(),
        );

        // Geocode if coordinates provided
        if (!empty($data['latitude']) && !empty($data['longitude'])) {
            $coords = Rental_Gates_Security::sanitize_coordinates(
                $data['latitude'],
                $data['longitude']
            );
            if ($coords) {
                $clean['latitude'] = $coords['lat'];
                $clean['longitude'] = $coords['lng'];
            }
        }

        $result = Rental_Gates_Building::create($clean);

        if (!is_wp_error($result)) {
            // Fire action hook for notifications (was only in REST)
            do_action('rental_gates_building_created', $result, $org_id, $user_id);
        }

        return $result;
    }

    /**
     * Delete a building with org ownership verification.
     */
    public static function delete($building_id, $org_id) {
        // Verify ownership (was only in AJAX)
        $building = Rental_Gates_Building::get($building_id);
        if (!$building) {
            return new WP_Error('not_found', __('Building not found', 'rental-gates'));
        }
        if ((int) $building['organization_id'] !== (int) $org_id) {
            return new WP_Error('forbidden', __('Not found', 'rental-gates'));
        }

        return Rental_Gates_Building::delete($building_id);
    }
}
```

Both AJAX and REST handlers then call:
```php
// In AJAX handler:
$result = Rental_Gates_Service_Buildings::create($data, $org_id, get_current_user_id());

// In REST handler:
$result = Rental_Gates_Service_Buildings::create($data, $this->get_org_id(), get_current_user_id());
```

#### Service 2: Payment Service

Addresses the `amount_paid` auto-fill business logic that only exists in AJAX:

```php
class Rental_Gates_Service_Payments {

    public static function create($data, $org_id) {
        $clean = array(
            'organization_id' => $org_id,
            'lease_id'        => intval($data['lease_id'] ?? 0),
            'tenant_id'       => intval($data['tenant_id'] ?? 0),
            'amount'          => Rental_Gates_Security::sanitize_amount($data['amount'] ?? 0),
            'type'            => sanitize_text_field($data['type'] ?? 'rent'),
            'method'          => sanitize_text_field($data['method'] ?? 'other'),
            'status'          => sanitize_text_field($data['status'] ?? 'pending'),
            'due_date'        => Rental_Gates_Security::sanitize_date($data['due_date'] ?? ''),
            'notes'           => sanitize_textarea_field($data['notes'] ?? ''),
        );

        // Business logic: auto-fill amount_paid and paid_at for succeeded payments
        // (Previously only in AJAX handler, missing from REST)
        if ($clean['status'] === 'succeeded') {
            $clean['amount_paid'] = $clean['amount'];
            $clean['paid_at'] = current_time('mysql');
        }

        $result = Rental_Gates_Payment::create($clean);

        if (!is_wp_error($result)) {
            do_action('rental_gates_payment_created', $result, $org_id);
        }

        return $result;
    }
}
```

#### Service 3: Maintenance Service

Addresses the notification hook that only fires from REST:

```php
class Rental_Gates_Service_Maintenance {

    public static function create($data, $org_id, $user_id) {
        $clean = array(
            'organization_id'    => $org_id,
            'building_id'        => intval($data['building_id'] ?? 0),
            'unit_id'            => intval($data['unit_id'] ?? 0),
            'reported_by'        => $user_id,
            'title'              => sanitize_text_field($data['title'] ?? ''),
            'description'        => sanitize_textarea_field($data['description'] ?? ''),
            'category'           => sanitize_text_field($data['category'] ?? 'other'),
            'priority'           => sanitize_text_field($data['priority'] ?? 'medium'),
            'permission_to_enter' => !empty($data['permission_to_enter']) ? 1 : 0,
            'access_instructions' => sanitize_textarea_field($data['access_instructions'] ?? ''),
        );

        $result = Rental_Gates_Maintenance::create($clean);

        if (!is_wp_error($result)) {
            // Notification hook - now fires from BOTH AJAX and REST
            do_action('rental_gates_maintenance_created', $result, $org_id);
        }

        return $result;
    }
}
```

### Remaining Domains (Future Work)

The other 12 AJAX/REST pairs have less dangerous divergence. They can be consolidated into services incrementally. For Phase 2, we only consolidate the 3 critical ones above. The remaining handlers continue calling models directly but are now in their own clean files (from WS-1).

---

## WS-4: Base Model & Model Layer Refactor

### Goal

Eliminate ~40-50% structural duplication across 22 model files by introducing a base class.

### Implementation

**New file: `includes/models/class-rental-gates-base-model.php`**

```php
<?php
if (!defined('ABSPATH')) exit;

abstract class Rental_Gates_Base_Model {

    /**
     * Table name (without prefix). Set by each child class.
     */
    protected static $table_key = '';

    /**
     * Resolved full table name.
     */
    protected static $table_name_cache = array();

    /**
     * JSON fields that need decoding in format().
     */
    protected static $json_fields = array();

    /**
     * Integer fields that need casting in format().
     */
    protected static $int_fields = array('id', 'organization_id');

    /**
     * Float fields that need casting in format().
     */
    protected static $float_fields = array();

    /**
     * Get the fully qualified table name.
     */
    protected static function table() {
        $class = static::class;
        if (!isset(self::$table_name_cache[$class])) {
            $tables = Rental_Gates_Database::get_table_names();
            $key = static::$table_key;
            self::$table_name_cache[$class] = $tables[$key] ?? '';
        }
        return self::$table_name_cache[$class];
    }

    /**
     * Get a single record by ID.
     */
    public static function get($id) {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . static::table() . " WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ? static::format($row) : null;
    }

    /**
     * Delete a record by ID.
     */
    public static function delete($id) {
        global $wpdb;
        $result = $wpdb->delete(static::table(), array('id' => $id), array('%d'));
        if ($result === false) {
            return new WP_Error('delete_failed', __('Delete failed', 'rental-gates'));
        }
        return true;
    }

    /**
     * Paginated list for an organization.
     */
    public static function get_for_organization($org_id, $args = array()) {
        global $wpdb;

        $defaults = array(
            'page'     => 1,
            'per_page' => 20,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
            'search'   => '',
            'status'   => '',
        );
        $args = wp_parse_args($args, $defaults);

        // Whitelist orderby
        $allowed_orderby = array('id', 'created_at', 'updated_at', 'name', 'status', 'title');
        if (!in_array($args['orderby'], $allowed_orderby, true)) {
            $args['orderby'] = 'created_at';
        }
        $order = $args['order'] === 'ASC' ? 'ASC' : 'DESC';

        $table = static::table();
        $where = $wpdb->prepare("WHERE organization_id = %d", $org_id);

        if (!empty($args['status'])) {
            $where .= $wpdb->prepare(" AND status = %s", $args['status']);
        }

        // Search (child classes override get_search_columns())
        if (!empty($args['search'])) {
            $search_cols = static::get_search_columns();
            if (!empty($search_cols)) {
                $like = '%' . $wpdb->esc_like($args['search']) . '%';
                $search_clauses = array();
                foreach ($search_cols as $col) {
                    $search_clauses[] = $wpdb->prepare("{$col} LIKE %s", $like);
                }
                $where .= ' AND (' . implode(' OR ', $search_clauses) . ')';
            }
        }

        $offset = ($args['page'] - 1) * $args['per_page'];

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where}");
        $rows = $wpdb->get_results(
            "SELECT * FROM {$table} {$where} ORDER BY {$args['orderby']} {$order} LIMIT {$args['per_page']} OFFSET {$offset}",
            ARRAY_A
        );

        return array(
            'items' => array_map(array(static::class, 'format'), $rows ?: array()),
            'total' => (int) $total,
            'page'  => $args['page'],
            'pages' => ceil($total / $args['per_page']),
        );
    }

    /**
     * Columns to search in get_for_organization(). Override in child classes.
     */
    protected static function get_search_columns() {
        return array('name');
    }

    /**
     * Format a database row for API output.
     * Handles JSON decoding, type casting, and null defaults.
     */
    protected static function format($row) {
        if (!$row) return null;

        // Integer casting
        foreach (static::$int_fields as $field) {
            if (isset($row[$field])) {
                $row[$field] = (int) $row[$field];
            }
        }

        // Float casting
        foreach (static::$float_fields as $field) {
            if (isset($row[$field])) {
                $row[$field] = (float) $row[$field];
            }
        }

        // JSON decoding
        foreach (static::$json_fields as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $decoded = json_decode($row[$field], true);
                $row[$field] = is_array($decoded) ? $decoded : array();
            }
        }

        return $row;
    }

    /**
     * Generate a unique slug from a name.
     */
    protected static function generate_unique_slug($name, $exclude_id = null) {
        global $wpdb;
        $table = static::table();

        $base = sanitize_title($name);
        if (empty($base)) {
            $base = 'item-' . wp_rand(1000, 9999);
        }

        $slug = $base;
        $counter = 1;
        $max_attempts = 100;

        while ($counter <= $max_attempts) {
            $exclude_sql = $exclude_id
                ? $wpdb->prepare(" AND id != %d", $exclude_id)
                : '';

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE slug = %s {$exclude_sql} LIMIT 1",
                $slug
            ));

            if (!$exists) break;

            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Run a callback inside a database transaction.
     */
    protected static function transaction(callable $callback) {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            $result = $callback();
            if (is_wp_error($result)) {
                $wpdb->query('ROLLBACK');
                return $result;
            }
            $wpdb->query('COMMIT');
            return $result;
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('transaction_failed', $e->getMessage());
        }
    }
}
```

### Child Class Migration Example (Building)

```php
<?php
// BEFORE: ~466 lines with duplicated init, format, slug, pagination
// AFTER:  ~200 lines of actual business logic

class Rental_Gates_Building extends Rental_Gates_Base_Model {

    protected static $table_key = 'buildings';

    protected static $json_fields = array('gallery', 'amenities');
    protected static $int_fields  = array('id', 'organization_id', 'total_units');
    protected static $float_fields = array('latitude', 'longitude');

    protected static function get_search_columns() {
        return array('name', 'address', 'city');
    }

    public static function create($data) {
        global $wpdb;

        if (empty($data['name'])) {
            return new WP_Error('missing_name', __('Building name is required', 'rental-gates'));
        }

        $data['slug'] = self::generate_unique_slug($data['name']);
        // ... rest of create logic (unique to Building)
    }

    // get_by_slug(), get_for_map(), update() - business logic only
    // No more repeated init(), format_building(), generate_unique_slug()
}
```

### Migration Plan

1. Create `class-rental-gates-base-model.php` and add to autoloader
2. Migrate models one at a time, starting with the simplest:
   - **Wave 1:** Building, Unit, Document, Flyer, Announcement (simplest, fewest cross-references)
   - **Wave 2:** Tenant, Vendor, Lead, Campaign, Message
   - **Wave 3:** Lease, Application, Maintenance (complex relationships)
   - **Wave 4:** Payment, Invoice, Subscription, Organization (most complex, most cross-model coupling)
3. Each migration is a single commit that can be tested independently
4. Run existing `test_models()` health check after each migration to verify all methods still exist

---

## WS-5: Database Migration Framework

### Goal

Replace the broken migration system with a transactional, version-gated framework.

### Problems Being Fixed

1. `run_migrations()` in `class-rental-gates-database.php` ignores its `$from_version` parameter
2. All migration blocks run unconditionally on every version bump
3. No transactions - partial failure leaves inconsistent state
4. Version is updated even if migrations fail
5. 4 AI credit tables are duplicated and untracked
6. Migration logic split between `class-rental-gates-database.php` and `rental-gates.php`

### Implementation

**Consolidate all migrations into `class-rental-gates-database.php`.**

```php
/**
 * Run versioned migrations with transaction safety.
 *
 * @param string $from_version Version we're upgrading from
 */
public function run_migrations($from_version) {
    // Define all migrations with their target versions
    $migrations = array(
        '2.7.0'  => 'migrate_to_270',
        '2.7.3'  => 'migrate_to_273',
        '2.11.5' => 'migrate_to_2115',
        '2.15.0' => 'migrate_to_2150',
        '2.15.2' => 'migrate_to_2152',
        // Future migrations go here
    );

    foreach ($migrations as $version => $method) {
        // Only run migrations newer than current version
        if (version_compare($from_version, $version, '>=')) {
            continue;
        }

        // Run this migration in a transaction
        $success = $this->run_single_migration($version, $method);

        if (!$success) {
            // Stop at first failure - don't skip ahead
            Rental_Gates_Security::log_security_event('migration_failed', array(
                'version'      => $version,
                'from_version' => $from_version,
            ));
            return false;
        }

        // Update version after EACH successful migration
        update_option('rental_gates_db_version', $version);
    }

    return true;
}

/**
 * Run a single migration inside a transaction.
 */
private function run_single_migration($version, $method) {
    global $wpdb;

    if (!method_exists($this, $method)) {
        return false;
    }

    $wpdb->query('START TRANSACTION');

    try {
        $result = $this->$method();

        if ($result === false) {
            $wpdb->query('ROLLBACK');
            error_log("Rental Gates: Migration to {$version} failed - rolling back");
            return false;
        }

        $wpdb->query('COMMIT');
        return true;

    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        error_log("Rental Gates: Migration to {$version} exception: " . $e->getMessage());
        return false;
    }
}

/**
 * v2.15.0: Create AI credit tables.
 * Replaces the duplicate create_ai_credit_tables_if_needed().
 */
private function migrate_to_2150() {
    // Single source of truth for AI credit table creation
    // (removes duplicate from create_tables)
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    dbDelta("CREATE TABLE {$wpdb->prefix}rg_ai_credit_balances (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        organization_id bigint(20) unsigned NOT NULL,
        subscription_credits int NOT NULL DEFAULT 0,
        purchased_credits int NOT NULL DEFAULT 0,
        bonus_credits int NOT NULL DEFAULT 0,
        cycle_start datetime DEFAULT NULL,
        cycle_end datetime DEFAULT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY org_id (organization_id)
    ) $charset;");

    // ... other AI credit tables ...
    return true;
}
```

### Also: Register AI credit tables in `get_table_names()`

```php
// Add to the get_table_names() array:
'ai_credit_balances'     => $wpdb->prefix . 'rg_ai_credit_balances',
'ai_credit_transactions' => $wpdb->prefix . 'rg_ai_credit_transactions',
'ai_credit_packs'        => $wpdb->prefix . 'rg_ai_credit_packs',
'ai_credit_purchases'    => $wpdb->prefix . 'rg_ai_credit_purchases',
```

### Remove migration logic from `rental-gates.php`

Move `check_db_upgrade()`, `upgrade_to_270()`, `upgrade_to_273()`, `upgrade_to_2115()` into `class-rental-gates-database.php`. The main file's `check_db_upgrade()` becomes a one-line delegation:

```php
public function check_db_upgrade() {
    $this->database->check_version();
}
```

---

## WS-6: Fix Feature Gate & Cache Bugs

### Feature Gate Fixes

#### Fix 1: Plan Name Alignment

The Feature Gate defines plans as `free/basic/silver/gold`. The database seeds `free/starter/professional/enterprise`. Paying customers are treated as free tier.

**Solution:** Add plan name aliases in `get_org_plan()`:

```php
private function resolve_plan_slug($slug) {
    // Map database plan names to feature gate plan names
    $aliases = array(
        'starter'      => 'basic',
        'professional' => 'silver',
        'enterprise'   => 'gold',
    );
    return $aliases[$slug] ?? $slug;
}
```

Apply in `get_org_plan()`:
```php
$plan_id = $this->resolve_plan_slug($org->plan_id);
```

#### Fix 2: Correct Table Names in limit_definitions

```php
// BEFORE (broken - table doesn't exist):
'staff'   => array('table' => 'rg_organization_users', ...),
'tenants' => array('table' => 'rg_organization_users', ...),

// AFTER (correct table):
'staff'   => array('table' => 'rg_organization_members', 'column' => 'organization_id', 'role_filter' => array('staff')),
'tenants' => array('table' => 'rg_tenants', 'column' => 'organization_id'),
```

#### Fix 3: Add Missing `check_role()` Method

```php
/**
 * Check if current user has one of the specified roles.
 *
 * @param array $roles Allowed role slugs (e.g., ['owner', 'manager'])
 * @return bool
 */
public function check_role($roles = array()) {
    if (empty($roles)) return true;

    if (in_array('owner', $roles) && Rental_Gates_Roles::is_owner_or_manager()) {
        return true;
    }
    if (in_array('staff', $roles) && Rental_Gates_Roles::is_staff()) {
        return true;
    }
    if (in_array('admin', $roles) && Rental_Gates_Roles::is_site_admin()) {
        return true;
    }

    return false;
}
```

### Cache Fixes

#### Fix 1: `remember()` False Ambiguity

```php
// BEFORE (broken):
public static function remember($key, $group, $ttl, $callback) {
    $value = self::get($key, $group);
    if ($value !== false) {
        return $value;
    }
    // ...

// AFTER (correct):
public static function remember($key, $group, $ttl, $callback) {
    $found = false;
    $value = wp_cache_get(self::make_key($key), self::make_group($group), false, $found);
    if ($found) {
        return $value;
    }
    $value = call_user_func($callback);
    self::set($key, $group, $value, $ttl);
    return $value;
}
```

#### Fix 2: `flush_group()` Using Versioned Keys

Instead of tracking individual keys (which doesn't work), use a group version counter:

```php
/**
 * Flush a cache group by incrementing its version.
 * All existing keys become stale because they use the old version.
 */
public static function flush_group($group) {
    $version_key = 'rg_cache_version_' . $group;
    $version = intval(get_option($version_key, 0)) + 1;
    update_option($version_key, $version, false); // autoload=false
}

/**
 * Make a cache key that includes the group version.
 */
public static function make_key($key, $group = '') {
    $version = '';
    if ($group) {
        $version = '_v' . intval(get_option('rg_cache_version_' . $group, 0));
    }
    return 'rg_' . sanitize_key($key) . $version;
}
```

---

## WS-7: Dead Code Removal

### Files to Remove

| File | Lines | Reason |
|------|-------|--------|
| `includes/class-rental-gates-loader.php` | 97 | Instantiated but zero method calls anywhere in codebase |
| `includes/class-rental-gates-validation.php` | 433 | Zero calls anywhere; enums are stale vs. DB schema |

### Total: 530 lines of dead code removed.

### Steps

1. Remove `require_once` for both files from `load_dependencies()` (or remove from autoloader map)
2. Remove `$this->loader = new Rental_Gates_Loader()` from constructor
3. Delete both files
4. Remove from autoloader class map
5. Grep entire codebase to confirm zero remaining references

### Fix Duplicate Include

`class-rental-gates-automation.php` is loaded twice (lines 154 and 174). Remove the duplicate.

---

## WS-8: PHPUnit Test Suite

### Goal

Create a PHPUnit test infrastructure and write tests for the highest-risk areas.

### Test Infrastructure

**File: `tests/bootstrap.php`**

```php
<?php
/**
 * PHPUnit bootstrap file.
 * Uses the WordPress test library.
 */

// Path to WordPress test library (set via environment or default)
$_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

// Load WordPress test framework
require_once $_tests_dir . '/includes/functions.php';

// Load plugin
tests_add_filter('muplugins_loaded', function() {
    require dirname(__DIR__) . '/rental-gates.php';
});

require $_tests_dir . '/includes/bootstrap.php';
```

**File: `tests/phpunit.xml`**

```xml
<?xml version="1.0"?>
<phpunit bootstrap="bootstrap.php"
         colors="true"
         convertErrorsToExceptions="true"
         convertNoticesToExceptions="true"
         convertWarningsToExceptions="true">
    <testsuites>
        <testsuite name="unit">
            <directory suffix="Test.php">./unit</directory>
        </testsuite>
        <testsuite name="integration">
            <directory suffix="Test.php">./integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

### Test Files (Phase 2 Scope)

#### 1. Model CRUD Tests

**File: `tests/unit/Models/BuildingModelTest.php`**

```php
<?php
class BuildingModelTest extends WP_UnitTestCase {

    private $org_id;

    public function setUp(): void {
        parent::setUp();
        // Create a test organization
        $org = Rental_Gates_Organization::create(array(
            'name' => 'Test Org',
            'contact_email' => 'test@example.com',
        ));
        $this->org_id = $org['id'];
    }

    public function test_create_building() {
        $building = Rental_Gates_Building::create(array(
            'organization_id' => $this->org_id,
            'name' => 'Test Building',
            'address' => '123 Main St',
            'city' => 'Springfield',
        ));

        $this->assertIsArray($building);
        $this->assertEquals('Test Building', $building['name']);
        $this->assertNotEmpty($building['slug']);
    }

    public function test_create_building_requires_name() {
        $result = Rental_Gates_Building::create(array(
            'organization_id' => $this->org_id,
        ));

        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_get_building() {
        $created = Rental_Gates_Building::create(array(
            'organization_id' => $this->org_id,
            'name' => 'Fetch Test',
        ));
        $fetched = Rental_Gates_Building::get($created['id']);

        $this->assertEquals($created['id'], $fetched['id']);
        $this->assertEquals('Fetch Test', $fetched['name']);
    }

    public function test_unique_slug_generation() {
        Rental_Gates_Building::create(array(
            'organization_id' => $this->org_id,
            'name' => 'Same Name',
        ));
        $second = Rental_Gates_Building::create(array(
            'organization_id' => $this->org_id,
            'name' => 'Same Name',
        ));

        $this->assertStringStartsWith('same-name', $second['slug']);
        $this->assertNotEquals('same-name', $second['slug']); // Must be unique
    }

    public function test_delete_building() {
        $building = Rental_Gates_Building::create(array(
            'organization_id' => $this->org_id,
            'name' => 'To Delete',
        ));
        $result = Rental_Gates_Building::delete($building['id']);

        $this->assertTrue($result);
        $this->assertNull(Rental_Gates_Building::get($building['id']));
    }

    public function test_organization_scoping() {
        // Create building in org 1
        Rental_Gates_Building::create(array(
            'organization_id' => $this->org_id,
            'name' => 'Org 1 Building',
        ));

        // Create separate org
        $org2 = Rental_Gates_Organization::create(array(
            'name' => 'Org 2',
            'contact_email' => 'org2@example.com',
        ));

        // List buildings for org 2 - should be empty
        $result = Rental_Gates_Building::get_for_organization($org2['id']);
        $this->assertCount(0, $result['items']);
    }
}
```

#### 2. Feature Gate Tests

```php
class FeatureGateTest extends WP_UnitTestCase {

    public function test_plan_name_resolution() {
        $gate = Rental_Gates_Feature_Gate::get_instance();

        // Verify database plan names map correctly
        $this->assertNotNull($gate->get_plan_config('starter'));
        $this->assertNotNull($gate->get_plan_config('professional'));
        $this->assertNotNull($gate->get_plan_config('enterprise'));
    }

    public function test_free_plan_limits() {
        // Create org on free plan
        $org = Rental_Gates_Organization::create(array(
            'name' => 'Free Org',
            'contact_email' => 'free@test.com',
            'plan_id' => 'free',
        ));

        $gate = Rental_Gates_Feature_Gate::get_instance();
        $result = $gate->can_create('buildings', 1, $org['id']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('limit', $result);
    }

    public function test_check_role_method_exists() {
        $gate = Rental_Gates_Feature_Gate::get_instance();
        $this->assertTrue(method_exists($gate, 'check_role'));
    }
}
```

#### 3. Service Layer Tests

```php
class ServiceBuildingsTest extends WP_UnitTestCase {

    public function test_create_enforces_feature_gate() {
        // Create org on free plan with max buildings already created
        // ...
        $result = Rental_Gates_Service_Buildings::create(
            array('name' => 'Over Limit'),
            $org_id
        );
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('limit_reached', $result->get_error_code());
    }

    public function test_delete_verifies_ownership() {
        // Create building in Org A, try to delete from Org B context
        $result = Rental_Gates_Service_Buildings::delete($building_id, $other_org_id);
        $this->assertInstanceOf(WP_Error::class, $result);
    }
}
```

#### 4. Cache Tests

```php
class CacheTest extends WP_UnitTestCase {

    public function test_remember_caches_false_values() {
        $call_count = 0;
        $callback = function() use (&$call_count) {
            $call_count++;
            return false;
        };

        Rental_Gates_Cache::remember('test_key', 'test_group', 300, $callback);
        Rental_Gates_Cache::remember('test_key', 'test_group', 300, $callback);

        // Should only call the callback once (second call hits cache)
        $this->assertEquals(1, $call_count);
    }

    public function test_flush_group_invalidates_keys() {
        Rental_Gates_Cache::set('key1', 'group1', 'value1', 300);
        Rental_Gates_Cache::flush_group('group1');

        $found = false;
        wp_cache_get(Rental_Gates_Cache::make_key('key1', 'group1'), Rental_Gates_Cache::make_group('group1'), false, $found);
        $this->assertFalse($found);
    }
}
```

#### 5. Migration Tests

```php
class MigrationTest extends WP_UnitTestCase {

    public function test_migrations_are_version_gated() {
        // Set version to 2.7.3 - migrations below should not run
        update_option('rental_gates_db_version', '2.7.3');
        $db = new Rental_Gates_Database();
        // Verify migrate_to_270 and migrate_to_273 are skipped
        // Verify migrate_to_2150 runs
    }

    public function test_failed_migration_does_not_update_version() {
        // Simulate a migration failure
        // Verify the version option was NOT advanced
    }
}
```

---

## Dependency Graph & Execution Order

```
WS-7 (Dead Code Removal) ─── no dependencies, do first
         │
WS-2 (Autoloader) ────────── depends on knowing final file list
         │
WS-1 (Monolith Decomp) ───── depends on WS-2 (autoloader loads new files)
         │
WS-4 (Base Model) ────────── independent, can parallel with WS-1
         │
WS-3 (Service Layer) ─────── depends on WS-1 (services called from new AJAX classes)
         │
WS-5 (Migration Framework) ─ independent, can parallel with WS-1
         │
WS-6 (Bug Fixes) ─────────── independent, can parallel with WS-1
         │
WS-8 (Test Suite) ────────── depends on all above being stable
```

### Recommended Execution Order

| Step | Workstream | Can Parallel With |
|------|-----------|-------------------|
| 1 | WS-7: Dead Code Removal | - |
| 2 | WS-2: Autoloader | WS-5, WS-6 |
| 3 | WS-5: Migration Framework | WS-2, WS-6 |
| 4 | WS-6: Bug Fixes (Feature Gate, Cache) | WS-2, WS-5 |
| 5 | WS-1: Monolith Decomposition | WS-4 |
| 6 | WS-4: Base Model | WS-1 |
| 7 | WS-3: Service Layer | - |
| 8 | WS-8: Test Suite | - |

---

## Testing Strategy

### Per-Workstream Verification

| WS | Verification Method |
|----|-------------------|
| WS-1 | Every AJAX action must still fire correctly. Run existing `test_models()` health check. Manual test each portal. |
| WS-2 | `class_exists()` for every class in the map. Load time benchmark before/after. |
| WS-3 | Create building via AJAX, create via REST - verify identical behavior including feature gate and notifications. |
| WS-4 | Run `test_models()` after each model migration. Verify all model methods still exist via reflection. |
| WS-5 | Run a migration from version 0 (fresh install) and from each intermediate version. Verify tables match expected schema. |
| WS-6 | Feature Gate: verify paid plan limits are enforced. Cache: verify `remember()` with false value. |
| WS-7 | Grep for removed class names - zero references. |
| WS-8 | PHPUnit green on all suites. |

### Regression Checklist

- [ ] Dashboard loads for all 5 portal types
- [ ] Building CRUD via AJAX (dashboard forms)
- [ ] Building CRUD via REST API
- [ ] Tenant invitation flow
- [ ] Lease creation and activation
- [ ] Payment creation with auto-fill of amount_paid
- [ ] Work order creation with notification email
- [ ] Stripe checkout flow
- [ ] AI credit deduction
- [ ] Feature gate blocks building creation at plan limit
- [ ] Public map page loads
- [ ] Public building detail page loads
- [ ] Login / register / password reset flows
- [ ] Plugin activation on fresh WordPress install
- [ ] Plugin upgrade from v2.41.0 (migration runs correctly)

---

## Rollout Plan

### Stage 1: Foundation (WS-7, WS-2, WS-5, WS-6)

1. Remove dead code (WS-7) - zero-risk change
2. Create autoloader (WS-2) - keep `load_dependencies()` as fallback initially
3. Fix migration framework (WS-5) - backward compatible
4. Fix Feature Gate + Cache bugs (WS-6) - bug fixes only

**Validation:** Full portal walkthrough. Existing `class_exists` health checks pass.

### Stage 2: Decomposition (WS-1, WS-4)

1. Extract AJAX handlers one domain at a time (WS-1):
   - Start with lowest-risk: Marketing, Documents, Leads
   - Then medium-risk: Buildings, Tenants, Vendors, Messages
   - Then high-risk: Payments, Stripe, Leases
   - Each extraction is a single commit
2. Introduce Base Model (WS-4):
   - Migrate models in waves (simplest first)
   - Each model migration is a single commit

**Validation:** After each extraction commit, verify the affected AJAX actions work.

### Stage 3: Consolidation (WS-3, WS-8)

1. Create 3 service classes (WS-3)
2. Update AJAX + REST handlers to call services
3. Write PHPUnit tests (WS-8)

**Validation:** Full regression test. PHPUnit green.

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| AJAX handler stops working after extraction | Medium | High | Each handler is moved verbatim - no logic changes. Test each action immediately after move. |
| Autoloader misses a class | Low | High | Keep `load_dependencies()` fallback during development. Map is explicit and complete. |
| Base model `format()` changes output shape | Medium | Medium | Override `format()` in child classes that need custom behavior. Compare JSON output before/after. |
| Service layer changes AJAX behavior | Low | Medium | Services extract existing logic - they don't invent new logic. Both APIs converge to the same behavior. |
| Migration framework breaks on upgrade | Low | Critical | New framework is backward-compatible. Existing `check_db_upgrade` logic still runs. |
| Feature Gate plan fix changes billing behavior | Medium | High | The fix makes the system MORE correct (paying users get their plan's limits instead of free limits). |
| Dead code removal breaks something | Very Low | Low | Grep confirms zero references before deletion. |
| PHPUnit tests flaky in CI | Medium | Low | Tests use WP_UnitTestCase with proper setUp/tearDown and DB rollback. |

---

## Appendix: File Change Summary

### New Files (19)

```
includes/class-rental-gates-autoloader.php
includes/class-rental-gates-enqueue.php
includes/class-rental-gates-routing.php
includes/ajax/class-rental-gates-ajax-buildings.php
includes/ajax/class-rental-gates-ajax-tenants.php
includes/ajax/class-rental-gates-ajax-leases.php
includes/ajax/class-rental-gates-ajax-applications.php
includes/ajax/class-rental-gates-ajax-payments.php
includes/ajax/class-rental-gates-ajax-maintenance.php
includes/ajax/class-rental-gates-ajax-vendors.php
includes/ajax/class-rental-gates-ajax-messages.php
includes/ajax/class-rental-gates-ajax-leads.php
includes/ajax/class-rental-gates-ajax-documents.php
includes/ajax/class-rental-gates-ajax-stripe.php
includes/ajax/class-rental-gates-ajax-marketing.php
includes/ajax/class-rental-gates-ajax-portals.php
includes/ajax/class-rental-gates-ajax-ai.php
includes/ajax/class-rental-gates-ajax-public.php
includes/models/class-rental-gates-base-model.php
includes/services/class-rental-gates-service-buildings.php
includes/services/class-rental-gates-service-payments.php
includes/services/class-rental-gates-service-maintenance.php
tests/bootstrap.php
tests/phpunit.xml
tests/unit/Models/BuildingModelTest.php
tests/unit/FeatureGateTest.php
tests/unit/ServiceBuildingsTest.php
tests/unit/CacheTest.php
tests/unit/MigrationTest.php
```

### Modified Files (6)

```
rental-gates.php                                    (7,809 -> ~480 lines)
includes/class-rental-gates-database.php            (migration framework)
includes/class-rental-gates-feature-gate.php        (plan names, table names, check_role)
includes/class-rental-gates-cache.php               (remember, flush_group)
includes/models/class-rental-gates-building.php     (extends Base_Model)
includes/models/[20 other model files]              (extends Base_Model, wave migration)
```

### Deleted Files (2)

```
includes/class-rental-gates-loader.php              (dead code, 97 lines)
includes/class-rental-gates-validation.php          (dead code, 433 lines)
```

---

*This plan is based on line-by-line analysis of rental-gates.php (7,809 lines), class-rental-gates-rest-api.php (3,200+ lines), class-rental-gates-database.php (1,772 lines), class-rental-gates-feature-gate.php (812 lines), class-rental-gates-cache.php (269 lines), class-rental-gates-loader.php (97 lines), class-rental-gates-validation.php (433 lines), and 5 model files (3,192 lines combined).*
