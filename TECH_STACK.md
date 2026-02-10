# Rental Gates - Technical Architecture

> **Version:** 2.44.0
> **Platform:** WordPress Plugin (Multi-tenant SaaS)
> **Last Updated:** February 2026

---

## Table of Contents

1. [Overview](#overview)
2. [Tech Stack Summary](#tech-stack-summary)
3. [WordPress Integration](#wordpress-integration)
4. [Application Architecture](#application-architecture)
5. [Standalone Page Rendering](#standalone-page-rendering)
6. [Authentication & Authorization](#authentication--authorization)
7. [Database Schema](#database-schema)
8. [API Layer](#api-layer)
9. [Frontend Architecture](#frontend-architecture)
10. [Payment Processing](#payment-processing)
11. [AI Integration](#ai-integration)
12. [Maps & Geocoding](#maps--geocoding)
13. [Email System](#email-system)
14. [Background Jobs & Automation](#background-jobs--automation)
15. [Security](#security)
16. [File Structure](#file-structure)

---

## Overview

Rental Gates is a full-featured property management SaaS platform built as a WordPress plugin. It provides a complete standalone web application experience — dashboards, public listings, auth flows, payment processing — while leveraging WordPress only as the hosting runtime and infrastructure layer. The end user never interacts with the WordPress admin; they use a fully custom UI.

**Business Model:** Multi-tenant SaaS. Property owners subscribe to plans (via Stripe), manage buildings/units/tenants, collect rent online, and automate maintenance workflows. Tenants, staff, and vendors each have their own portal.

---

## Tech Stack Summary

| Layer | Technology |
|---|---|
| **Runtime** | PHP 7.4+ on WordPress 5.9+ |
| **Database** | MySQL/MariaDB (55+ custom tables via `$wpdb`) |
| **Frontend** | Vanilla JS + Server-side PHP templates (no framework) |
| **CSS** | Custom design system with CSS custom properties (`rg-*` namespace) |
| **Fonts** | Inter + Plus Jakarta Sans (Google Fonts) |
| **Payments** | Stripe (Connect, Checkout, Webhooks, Subscriptions) |
| **AI** | OpenAI (GPT-4o-mini) + Google Gemini (1.5 Flash) |
| **Maps** | Google Maps API + OpenStreetMap/Nominatim (provider-agnostic) |
| **Email** | WordPress `wp_mail()` with 30+ PHP-based templates |
| **PDF** | Server-side HTML-to-PDF generation |
| **QR Codes** | Server-side QR generation with scan analytics |
| **Caching** | WordPress Transients API (group-based, version-aware) |
| **Cron** | WordPress WP-Cron (hourly + daily scheduled events) |
| **PWA** | Service worker + manifest (offline support) |

---

## WordPress Integration

### What WordPress Provides

Rental Gates uses WordPress as an **infrastructure layer**, not a CMS. Specifically:

| WordPress Feature | How Rental Gates Uses It |
|---|---|
| **User system** (`wp_users`) | All authentication, session management, password hashing |
| **Roles & capabilities** | 7 custom roles with granular capability-based permissions |
| **Rewrite API** | Maps `/rental-gates/*` URLs to the plugin's custom routing |
| **REST API** | 98 custom endpoints under `rental-gates/v1/` namespace |
| **AJAX handlers** | 114 `wp_ajax_*` handlers for form submissions |
| **`wp_enqueue_scripts`** | CSS/JS asset loading with version-based cache busting |
| **Transients API** | Application-level caching (organizations, stats, maps) |
| **`wp_mail()`** | Email delivery (delegates to host SMTP) |
| **Media Library** | Image uploads for buildings, units, documents |
| **WP-Cron** | Hourly availability automation, daily AI credit resets |
| **Nonces** | CSRF protection on all forms and AJAX calls |
| **Options API** | Plugin settings, Stripe keys, API configurations |

### What WordPress Does NOT Provide

- No WordPress themes — pages render their own complete HTML documents
- No WordPress admin UI — all management happens in the custom dashboards
- No posts, pages, comments, or categories — purely custom data in custom tables
- No Gutenberg/block editor — all templates are vanilla PHP

---

## Application Architecture

### Plugin Bootstrap

```
rental-gates.php (monolith, ~8000 lines)
├── define('RENTAL_GATES_VERSION', '2.44.0')
├── define('RENTAL_GATES_PLUGIN_DIR', ...)
├── define('RENTAL_GATES_PLUGIN_URL', ...)
│
├── load_dependencies()          # require_once for ~40 class files
│   ├── class-rental-gates-database.php
│   ├── class-rental-gates-roles.php
│   ├── class-rental-gates-auth.php
│   ├── class-rental-gates-security.php
│   ├── class-rental-gates-stripe.php
│   ├── class-rental-gates-ai.php
│   ├── class-rental-gates-email.php
│   ├── class-rental-gates-cache.php
│   ├── class-rental-gates-logger.php
│   ├── class-rental-gates-analytics.php
│   ├── class-rental-gates-pdf.php
│   ├── class-rental-gates-rate-limit.php
│   ├── class-rental-gates-routing.php
│   ├── class-rental-gates-enqueue.php
│   ├── api/class-rental-gates-rest-api.php
│   ├── subscription/class-rental-gates-billing.php
│   ├── subscription/class-rental-gates-webhook-handler.php
│   ├── maps/class-rental-gates-map-service.php
│   ├── maps/class-rental-gates-google-maps.php
│   ├── maps/class-rental-gates-openstreetmap.php
│   ├── public/class-rental-gates-public.php
│   ├── public/class-rental-gates-qr.php
│   └── models/ (23 model classes)
│
├── init_hooks()                 # Registers all WordPress hooks
│   ├── template_redirect → handle_custom_routes() [priority 1]
│   ├── wp_enqueue_scripts → enqueue_public_assets()
│   ├── rest_api_init → init_api()
│   ├── 114 × wp_ajax_* handlers
│   └── WP-Cron schedules
│
└── activate() / deactivate()    # DB table creation, role setup, cron scheduling
```

### Dual System Architecture

The codebase has two parallel systems:

1. **Monolith** (`rental-gates.php`): The ~8000-line main plugin file contains the complete application — routing, enqueue, AJAX handlers, and all business logic. This is what actually runs in production.

2. **Refactored Classes** (`plugin/includes/`): Modular extractions of routing, enqueue, etc. The monolith's `load_dependencies()` loads these files, but the monolith also contains its own duplicate inline versions of some functionality.

> **Important:** Changes to refactored classes must also be mirrored in the monolith, or they won't take effect. The monolith's hooks fire first and can shadow the refactored classes.

### Model Layer

23 model classes in `includes/models/` provide an Active Record-like pattern:

```php
class Rental_Gates_Building extends Rental_Gates_Base_Model {
    protected static $table_key = 'buildings';

    public static function get($id) { ... }
    public static function create($data) { ... }
    public static function update($id, $data) { ... }
    public static function delete($id) { ... }
    public static function list($filters) { ... }
}
```

Models: Organization, Building, Unit, Tenant, Lease, Payment, Application, Lead, LeadScoring, Maintenance, Vendor, Document, Message, Announcement, Notification, Flyer, Campaign, MarketingConversion, Invoice, Subscription, Plan, AIUsage.

---

## Standalone Page Rendering

This is the core architectural innovation. Rental Gates renders **complete standalone HTML pages** that bypass the WordPress theme entirely.

### How It Works

```
Browser Request: GET /rental-gates/dashboard/buildings/42/edit
         │
         ▼
┌─ WordPress Core ─────────────────────────────┐
│                                               │
│  1. wp_enqueue_scripts fires                  │
│     → Rental_Gates_Enqueue::enqueue_public()  │
│     → is_rental_gates_page() matches URI      │
│     → Enqueues: rental-gates.css              │
│                 components.css                │
│                 mobile.css                    │
│                 rental-gates.js               │
│                 (+ conditional JS modules)    │
│                                               │
│  2. template_redirect fires [priority 1]      │
│     → Rental_Gates_Routing::handle_routes()   │
└───────────────┬───────────────────────────────┘
                │
                ▼
┌─ Custom Routing ─────────────────────────────┐
│                                               │
│  3. Regex: /rental-gates/(dashboard)/...      │
│     → $page = 'owner-dashboard'               │
│                                               │
│  4. check_role(['rental_gates_owner'])         │
│     → Pass or redirect to /rental-gates/login │
│                                               │
│  5. load_template('dashboard/owner')           │
│     → Resolves: templates/dashboard/owner/     │
│                 layout.php                    │
│     → Sets $current_page = 'buildings/42/edit'│
│     → Sets $content_template =                │
│         sections/buildings-form.php           │
│         ($_GET['id'] = 42)                    │
│                                               │
│  6. include layout.php → exit;                │
│     (WordPress theme never renders)           │
└───────────────────────────────────────────────┘
```

### The Key: `exit;` After Template Include

After the routing class includes the template file, it calls `exit;`. This **prevents WordPress from continuing** to its normal theme rendering. The browser receives a complete HTML document:

```php
// In layout.php:
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?></title>
    <?php wp_head(); ?>   <!-- Outputs enqueued CSS/JS -->
</head>
<body>
    <!-- Full custom UI: sidebar, topbar, content -->
    <aside class="rg-sidebar">...</aside>
    <main class="rg-main">
        <?php include $content_template; ?>
    </main>
    <?php wp_footer(); ?> <!-- Outputs enqueued JS -->
</body>
</html>
```

`wp_head()` and `wp_footer()` are the only WordPress hooks that fire inside the template. They output the CSS and JS that was enqueued during the `wp_enqueue_scripts` phase.

### Page Types

| URL Pattern | Template | Description |
|---|---|---|
| `/rental-gates/` | `public/map.php` | Public property map |
| `/rental-gates/b/{slug}` | `public/building.php` | Public building detail |
| `/rental-gates/listings/{slug}` | `public/unit.php` | Public unit listing |
| `/rental-gates/login` | `auth/login.php` | Login page |
| `/rental-gates/register` | `auth/register.php` | Registration page |
| `/rental-gates/dashboard/...` | `dashboard/owner/layout.php` | Owner dashboard |
| `/rental-gates/admin/...` | `dashboard/admin/layout.php` | Site admin dashboard |
| `/rental-gates/staff/...` | `dashboard/staff/layout.php` | Staff portal |
| `/rental-gates/tenant/...` | `dashboard/tenant/layout.php` | Tenant portal |
| `/rental-gates/vendor/...` | `dashboard/vendor/layout.php` | Vendor portal |
| `/rental-gates/pricing` | `pricing.php` | Pricing page |
| `/rental-gates/about` | `public/about.php` | About page |
| `/rental-gates/contact` | `public/contact.php` | Contact page |
| `/rental-gates/faq` | `public/faq.php` | FAQ page |
| `/rental-gates/privacy` | `public/privacy.php` | Privacy policy |
| `/rental-gates/terms` | `public/terms.php` | Terms of service |
| `/rental-gates/apply` | `public/apply.php` | Rental application |

### Dashboard Layout Architecture

Two patterns exist:

**Shared Layout** (Owner + Admin):
```php
// owner/layout.php — ~100 lines
$dashboard_role = 'owner';
$dashboard_base = home_url('/rental-gates/dashboard');
$nav_groups = array( /* navigation config */ );
include RENTAL_GATES_PLUGIN_DIR . 'templates/dashboard/layout.php';
```

The shared `layout.php` renders sidebar, topbar, breadcrumbs, and includes `$content_template` in the main content area. All styling comes from external CSS loaded via `wp_head()`.

**Self-contained Portals** (Staff, Tenant, Vendor):
Each has its own monolithic layout with inline `<style>` blocks and different class prefixes:
- Staff: `rg-*` prefix, loads from `staff/sections/`
- Tenant: `tp-*` prefix, loads from `tenant/sections/`
- Vendor: `vp-*` prefix, loads from `vendor/sections/`

---

## Authentication & Authorization

### Authentication Flow

```
Login Page (/rental-gates/login)
│
├── Form submit → wp_ajax_nopriv_rental_gates_login
│   ├── Rate limiting (5 attempts / 5 minutes)
│   ├── wp_authenticate($email, $password)
│   ├── wp_set_auth_cookie($user_id, $remember)
│   └── JSON response with redirect URL
│
├── Magic Link Login
│   ├── User requests link → stored in rg_magic_links table
│   ├── Email sent with unique token
│   └── /rental-gates/magic-login?token=xxx → auto-login
│
└── Post-Login Redirect (role-based)
    ├── rental_gates_owner → /rental-gates/dashboard
    ├── rental_gates_site_admin → /rental-gates/admin
    ├── rental_gates_staff → /rental-gates/staff
    ├── rental_gates_tenant → /rental-gates/tenant
    └── rental_gates_vendor → /rental-gates/vendor
```

### Registration Flow

```
Register Page (/rental-gates/register)
│
├── REST API: POST /wp-json/rental-gates/v1/auth/register
│   ├── Rate limiting (3 registrations / hour)
│   ├── Input validation & sanitization
│   ├── wp_create_user() → assigns rental_gates_owner role
│   ├── Creates Organization record
│   ├── Creates Stripe customer
│   ├── Sends welcome email
│   └── Auto-login via wp_set_auth_cookie()
```

### Roles & Capabilities

7 custom WordPress roles:

| Role | Capabilities | Portal |
|---|---|---|
| `rental_gates_site_admin` | Full platform management, all organizations | `/admin` |
| `rental_gates_owner` | Full organization control, billing, AI tools | `/dashboard` |
| `rental_gates_manager` | Same as owner (property manager alias) | `/dashboard` |
| `rental_gates_staff` | Subset: buildings, tenants, leases, maintenance | `/staff` |
| `rental_gates_tenant` | View lease, make payments, submit maintenance | `/tenant` |
| `rental_gates_vendor` | View/manage assigned work orders | `/vendor` |
| `rental_gates_lead` | No dashboard access (lead tracking only) | N/A |

**Permission enforcement:**
- Route level: `check_role()` in routing class before template load
- API level: `current_user_can('rg_manage_buildings')` on each REST endpoint
- Template level: Conditional rendering based on capabilities

**Multi-tenancy:** All data is scoped by `organization_id`. The `Rental_Gates_Roles::get_organization_id()` helper resolves the current user's org from the `rg_organization_members` table.

---

## Database Schema

55+ custom tables with `wp_rg_*` prefix. Key table groups:

### Core
| Table | Purpose |
|---|---|
| `rg_organizations` | Multi-tenant organizations (property management companies) |
| `rg_organization_members` | User ↔ organization mapping with roles |
| `rg_settings` | Per-organization settings (key-value) |
| `rg_activity_log` | Audit trail for all actions |
| `rg_staff_permissions` | Granular staff permission overrides |

### Property
| Table | Purpose |
|---|---|
| `rg_buildings` | Properties with address, geocoding, amenities |
| `rg_units` | Units within buildings (rent, beds, baths, status) |

### People
| Table | Purpose |
|---|---|
| `rg_tenants` | Tenant profiles linked to WordPress users |
| `rg_vendors` | Vendor companies with service categories |
| `rg_leads` | Prospective tenant leads |
| `rg_lead_interests` | Lead ↔ unit interest mapping |
| `rg_lead_scores` | AI-calculated lead scoring data |

### Leasing
| Table | Purpose |
|---|---|
| `rg_leases` | Lease agreements (dates, rent, terms, status) |
| `rg_lease_tenants` | Many-to-many lease ↔ tenant mapping |
| `rg_rent_adjustments` | Rent changes during lease term |
| `rg_renewals` | Lease renewal offers and tracking |
| `rg_applications` | Rental applications with screening data |
| `rg_application_occupants` | Additional occupants on applications |

### Financial
| Table | Purpose |
|---|---|
| `rg_payments` | Rent payments (amount, status, Stripe PI) |
| `rg_payment_items` | Line items per payment |
| `rg_payment_plans` | Installment payment plans |
| `rg_payment_plan_items` | Individual installments |
| `rg_deposits` | Security deposits |
| `rg_deposit_deductions` | Deductions from deposits at move-out |
| `rg_vendor_payouts` | Payments to vendors for work orders |

### Maintenance
| Table | Purpose |
|---|---|
| `rg_work_orders` | Maintenance requests / work orders |
| `rg_work_order_notes` | Notes and updates on work orders |
| `rg_work_order_vendors` | Vendor assignments to work orders |
| `rg_scheduled_maintenance` | Recurring maintenance schedules |

### Subscription (SaaS Billing)
| Table | Purpose |
|---|---|
| `rg_plans` | Subscription plans (features, limits, pricing) |
| `rg_subscriptions` | Organization subscriptions |
| `rg_invoices` | Platform invoices |
| `rg_stripe_accounts` | Stripe Connect accounts for owners |
| `rg_payment_methods` | Saved payment methods |

### Communication
| Table | Purpose |
|---|---|
| `rg_messages` | Direct messages |
| `rg_message_threads` | Message threads |
| `rg_announcements` | Broadcast announcements |
| `rg_announcement_recipients` | Announcement delivery tracking |
| `rg_notifications` | In-app notifications |
| `rg_notification_preferences` | Per-user notification settings |

### Documents & Marketing
| Table | Purpose |
|---|---|
| `rg_documents` | Uploaded documents (leases, receipts, etc.) |
| `rg_flyers` | Marketing flyer templates |
| `rg_qr_codes` | Generated QR codes |
| `rg_qr_scans` | QR code scan analytics |
| `rg_marketing_campaigns` | Marketing campaigns |
| `rg_marketing_automation_rules` | Automation trigger rules |
| `rg_move_checklists` | Move-in/out checklists |
| `rg_condition_reports` | Unit condition reports |
| `rg_condition_items` | Individual condition report items |

### AI
| Table | Purpose |
|---|---|
| `rg_ai_usage` | AI API call tracking |
| `rg_ai_screenings` | AI tenant screening results |
| `rg_ai_credit_balances` | Per-organization AI credit balance |
| `rg_ai_credit_transactions` | Credit usage/purchase history |
| `rg_ai_credit_packs` | Available credit pack definitions |
| `rg_ai_credit_purchases` | Stripe purchases of credit packs |

### Auth
| Table | Purpose |
|---|---|
| `rg_magic_links` | Passwordless login tokens |

---

## API Layer

### REST API

98 endpoints registered under `rental-gates/v1/` namespace:

```
Authentication:
  POST   /auth/login
  POST   /auth/register
  POST   /auth/logout
  GET    /auth/me
  POST   /auth/password/reset

Organizations:
  GET    /organizations
  GET    /organizations/{id}
  GET    /organizations/{id}/stats
  GET    /organizations/{id}/members

Properties:
  GET|POST    /buildings
  GET|PUT|DEL /buildings/{id}
  GET         /buildings/{id}/units
  GET|POST    /units
  GET|PUT|DEL /units/{id}
  GET         /units/{id}/availability

People:
  GET|POST    /tenants
  GET|PUT|DEL /tenants/{id}
  POST        /tenants/{id}/invite
  GET         /tenants/{id}/leases
  GET|POST    /vendors
  GET|PUT|DEL /vendors/{id}
  POST        /vendors/{id}/invite

Leasing:
  GET|POST    /leases
  GET|PUT|DEL /leases/{id}
  POST        /leases/{id}/activate
  POST        /leases/{id}/terminate
  POST        /leases/{id}/renew
  GET|POST    /applications
  GET|PUT|DEL /applications/{id}
  POST        /applications/{id}/approve
  POST        /applications/{id}/decline

Financial:
  GET|POST    /payments
  GET|PUT     /payments/{id}
  POST        /payments/{id}/refund
  POST        /payments/checkout

Maintenance:
  GET|POST    /work-orders
  GET|PUT|DEL /work-orders/{id}
  POST        /work-orders/{id}/notes
  POST        /work-orders/{id}/assign

Communication:
  GET|POST    /messages
  GET         /messages/threads
  GET         /messages/threads/{id}
  POST        /messages/{id}/read
  GET|POST    /announcements
  GET|PUT|DEL /announcements/{id}
  GET         /notifications
  GET         /notifications/unread-count

Documents:
  GET|POST    /documents
  GET|DEL     /documents/{id}

Payments (Stripe):
  POST        /stripe/webhook
```

All endpoints enforce nonce verification (`X-WP-Nonce` header) and capability-based authorization.

### AJAX Handlers

114 `wp_ajax_*` handlers for dashboard form submissions. These use WordPress's admin-ajax.php endpoint with nonce verification:

```php
// Template form:
<form id="building-form">
    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('rental_gates_nonce'); ?>">
    ...
</form>

// JS submission:
fetch(rentalGatesData.ajaxUrl, {
    method: 'POST',
    body: new FormData(form) // includes action=rental_gates_save_building
})

// PHP handler:
public function handle_save_building() {
    Rental_Gates_Security::verify_ajax_nonce($_POST['nonce']);
    // ... process and respond with JSON
}
```

Key AJAX actions: `save_building`, `save_unit`, `create_tenant`, `create_lease`, `upload_image`, `generate_qr`, `create_flyer`, `geocode`, `save_settings`, and 100+ more.

---

## Frontend Architecture

### CSS Design System

Three stylesheets loaded in order:

1. **`rental-gates.css`** — Design tokens and layout primitives
   ```css
   :root {
       /* Colors */
       --rg-primary: #2563eb;
       --rg-primary-dark: #1d4ed8;
       --gray-50 through --gray-900: /* Tailwind-inspired scale */

       /* Z-index scale */
       --rg-z-base: 1;
       --rg-z-dropdown: 50;
       --rg-z-sticky: 100;
       --rg-z-overlay: 1000;
       --rg-z-modal: 9999;
       --rg-z-toast: 10000;

       /* Typography */
       --rg-font-sans: 'Inter', -apple-system, sans-serif;
       --rg-font-display: 'Plus Jakarta Sans', var(--rg-font-sans);
   }
   ```
   Plus: layout classes (`.rg-sidebar`, `.rg-topbar`, `.rg-main`, `.rg-content`), sidebar navigation, responsive breakpoints.

2. **`components.css`** — 48 component sections
   - Cards, buttons, badges, tables, forms, modals, stats, empty states
   - Priority labels, textareas, utility classes
   - Public template utilities
   - Responsive breakpoints: 1200px, 1024px, 768px, 640px

3. **`mobile.css`** — Mobile-specific overrides (touch targets, bottom nav, safe areas)

All classes use the `rg-` namespace (except tenant `tp-*` and vendor `vp-*` portals).

### JavaScript Architecture

Namespace extension pattern — modules attach to `window.RentalGates` before the main entry:

```
Load Order:
1. rental-gates-modal.js   → RentalGates.initModal()
2. rental-gates-toast.js   → RentalGates.toast(message, type)
3. rental-gates-media.js   → RentalGates.initMediaLibrary()  (conditional)
4. rental-gates-qr.js      → RentalGates.initQR()            (conditional)
5. rental-gates.js          → Main entry, initializes all
6. pwa.js / service-worker.js → PWA registration
```

**Conditional loading:** Media and QR modules are only enqueued on sections that need them (buildings, marketing, settings).

**Guard pattern:** The main entry guards optional modules:
```js
if (typeof this.initMediaLibrary === 'function') {
    this.initMediaLibrary();
}
```

**Toast notifications** replace all `alert()` calls:
```js
RentalGates.toast('Building saved successfully', 'success');
RentalGates.toast('Failed to delete', 'error');
```

### Template Data Bridge

Server-to-client data is passed via `wp_localize_script`:
```php
wp_localize_script('rental-gates', 'rentalGatesData', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'restUrl' => rest_url('rental-gates/v1/'),
    'nonce'   => wp_create_nonce('rental_gates_nonce'),
    'restNonce' => wp_create_nonce('wp_rest'),
    'userId'  => get_current_user_id(),
    'pluginUrl' => RENTAL_GATES_PLUGIN_URL,
    'section' => $section,
    'mapProvider' => get_option('rental_gates_map_provider', 'osm'),
    // ... more config
));
```

---

## Payment Processing

### Dual Stripe Integration

The plugin handles two distinct payment flows:

**1. SaaS Billing (Platform ← Owner)**
Property owners pay for their Rental Gates subscription.

```
Owner → Stripe Checkout → Platform receives payment
                        → Subscription created/renewed
                        → Features unlocked based on plan
```

Managed by `Rental_Gates_Billing` class:
- Plans with monthly/yearly pricing
- Setup intents for saved payment methods
- Subscription lifecycle (create, upgrade, downgrade, cancel)
- Invoice generation

**2. Rent Collection (Tenant → Owner)**
Tenants pay rent to property owners via Stripe Connect.

```
Tenant → Stripe Embedded/Hosted Checkout → Owner's connected Stripe account
                                          → Platform takes 2.5% fee
                                          → Payment recorded in rg_payments
```

Managed by `Rental_Gates_Stripe` class:
- Stripe Connect onboarding for property owners
- Embedded Checkout (in-page) or Hosted Checkout (redirect)
- Payment intent creation with application fees
- Refund processing
- Customer and payment method management

### Webhook Processing

```
Stripe → POST /wp-json/rental-gates/v1/stripe/webhook
       → Signature verification (encrypted webhook secret)
       → Event routing:
           payment_intent.succeeded → Record payment, send receipt email
           invoice.paid             → Update subscription status
           customer.subscription.*  → Handle plan changes
           checkout.session.completed → Complete payment flow
```

---

## AI Integration

Dual-provider AI with credit-based usage tracking:

| Provider | Model | Use Case |
|---|---|---|
| OpenAI | `gpt-4o-mini` | Primary provider |
| Google Gemini | `gemini-1.5-flash` | Alternative provider |

### AI Features

- **Tenant Screening:** AI-assisted application review and scoring
- **Lead Scoring:** Predictive scoring of prospective tenant leads
- **Marketing Copy:** AI-generated listing descriptions and marketing content
- **Maintenance Triage:** AI-assisted work order categorization and priority
- **General AI Tools:** Dashboard-integrated AI assistant

### Credit System

Organizations purchase AI credit packs (via Stripe). Each AI call deducts credits. Credits reset daily via WP-Cron. Usage tracked in `rg_ai_usage`, `rg_ai_credit_balances`, and `rg_ai_credit_transactions`.

---

## Maps & Geocoding

Provider-agnostic abstraction via `Rental_Gates_Map_Service`:

```php
abstract class Rental_Gates_Map_Service {
    abstract public function geocode($address);           // Address → lat/lng
    abstract public function reverse_geocode($lat, $lng); // lat/lng → address
    abstract public function get_js_config();              // Frontend map config
}
```

**Implementations:**
- `Rental_Gates_Google_Maps` — Google Maps API (requires API key)
- `Rental_Gates_OpenStreetMap` — Nominatim + Leaflet (free, no API key)

Configurable per-organization via settings. The public map page (`/rental-gates/`) renders an interactive map with all buildings/units.

---

## Email System

30+ transactional email templates using PHP-based rendering:

### Template Hierarchy
```
1. Theme override:  {theme}/rental-gates/emails/{template}.php
2. Plugin template: templates/emails/{template}.php
3. Generic fallback: templates/emails/generic.php
```

### Email Categories

| Category | Templates |
|---|---|
| **Account** | welcome, password_reset, magic_link, tenant/staff/vendor_invitation |
| **Applications** | application_received, application_approved, application_declined |
| **Leases** | lease_created, lease_signed, lease_ending, renewal_offer |
| **Payments** | payment_receipt, payment_reminder, payment_overdue, payment_failed |
| **Maintenance** | maintenance_created, maintenance_assigned, maintenance_update, maintenance_completed, maintenance_survey |
| **Communication** | message_received, announcement |
| **Marketing** | lead_inquiry, lead_followup |
| **Subscription** | subscription_confirmed |

---

## Background Jobs & Automation

### WP-Cron Scheduled Events

| Event | Frequency | Purpose |
|---|---|---|
| `rental_gates_availability_cron` | Hourly | Auto-update unit availability based on lease dates |
| `rental_gates_ai_credits_reset` | Daily | Reset daily AI credit allocations |
| `rental_gates_subscription_expiration` | Daily | Check and handle expired subscriptions |

### Availability Automation

The hourly cron runs `run_availability_automation()`:
- Checks all active leases for upcoming move-ins/outs
- Automatically marks units as occupied/available based on lease dates
- Updates unit listing status on the public map

---

## Security

### Input Validation
- `Rental_Gates_Security::verify_ajax_nonce()` — CSRF on all AJAX
- `Rental_Gates_Security::verify_rest_nonce()` — CSRF on all REST calls
- `Rental_Gates_Security::sanitize_phone()` — Input sanitization helpers
- `Rental_Gates_Security::json_for_script()` — XSS-safe JSON embedding in templates
- `Rental_Gates_Security::encrypt()` / `decrypt()` — Encrypted storage for API keys

### Rate Limiting

`Rental_Gates_Rate_Limit` enforces per-IP limits:

| Endpoint | Limit | Window |
|---|---|---|
| Login | 5 requests | 5 minutes |
| Registration | 3 requests | 1 hour |
| Password Reset | 3 requests | 2 minutes |
| General API | 100 requests | 1 minute |
| Search | 30 requests | 1 minute |
| Upload | 20 requests | 1 minute |

### Caching

`Rental_Gates_Cache` wraps WordPress transients with:
- Group-based key prefixing (`rg_org`, `rg_bld`, `rg_unit`, etc.)
- Version-aware invalidation
- Default 1-hour expiration

---

## File Structure

```
rental-gates/                          # Plugin root (351 files)
├── rental-gates.php                   # Main plugin file (~8000 lines)
│
├── assets/
│   ├── css/
│   │   ├── rental-gates.css           # Design system tokens & layout
│   │   ├── components.css             # 48 component sections
│   │   └── mobile.css                 # Mobile-specific styles
│   ├── js/
│   │   ├── rental-gates.js            # Main entry point
│   │   ├── rental-gates-modal.js      # Modal system
│   │   ├── rental-gates-toast.js      # Toast notifications
│   │   ├── rental-gates-media.js      # WordPress media library integration
│   │   ├── rental-gates-qr.js         # QR code features
│   │   ├── pwa.js                     # PWA registration
│   │   └── service-worker.js          # Offline support
│   └── images/                        # Static assets
│
├── includes/
│   ├── class-rental-gates-*.php       # Core classes (~20 files)
│   ├── api/
│   │   └── class-rental-gates-rest-api.php  # 98 REST endpoints
│   ├── models/
│   │   ├── class-rental-gates-base-model.php
│   │   └── class-rental-gates-*.php   # 23 model classes
│   ├── subscription/
│   │   ├── class-rental-gates-billing.php
│   │   ├── class-rental-gates-subscription-invoice.php
│   │   └── class-rental-gates-webhook-handler.php
│   ├── maps/
│   │   ├── class-rental-gates-map-service.php
│   │   ├── class-rental-gates-google-maps.php
│   │   └── class-rental-gates-openstreetmap.php
│   └── public/
│       ├── class-rental-gates-public.php
│       └── class-rental-gates-qr.php
│
├── templates/
│   ├── auth/
│   │   ├── login.php                  # Standalone login page
│   │   ├── register.php               # Registration page
│   │   ├── reset-password.php         # Password reset
│   │   └── checkout.php               # Subscription checkout
│   │
│   ├── dashboard/
│   │   ├── layout.php                 # Shared dashboard shell
│   │   ├── owner/layout.php           # Owner nav config → includes layout.php
│   │   ├── admin/
│   │   │   ├── layout.php             # Admin nav config → includes layout.php
│   │   │   └── sections/              # 12 admin section templates
│   │   ├── staff/
│   │   │   ├── layout.php             # Self-contained staff layout
│   │   │   └── sections/              # 11 staff section templates
│   │   ├── tenant/
│   │   │   ├── layout.php             # Self-contained tenant layout
│   │   │   └── sections/              # 6 tenant section templates
│   │   ├── vendor/
│   │   │   ├── layout.php             # Self-contained vendor layout
│   │   │   └── sections/              # 4 vendor section templates
│   │   ├── sections/                  # 35 owner/admin section templates
│   │   └── partials/                  # Reusable widgets
│   │
│   ├── public/
│   │   ├── map.php                    # Interactive property map
│   │   ├── building.php               # Building detail page
│   │   ├── unit.php                   # Unit listing page
│   │   ├── about.php, faq.php, etc.   # Static pages
│   │   └── landing*.php               # Landing page variants
│   │
│   ├── emails/                        # 30 email templates
│   └── flyers/                        # Marketing flyer templates
│
└── languages/                         # i18n files
```
