# Phase 1: Security Hardening - Implementation Plan

**Project:** Rental Gates v2.41.0
**Date:** 2026-02-09
**Scope:** All 7 Critical + 14 High severity security findings
**Estimated Workstreams:** 10 parallel tracks, 18 implementation tasks

---

## Table of Contents

1. [Plan Overview & Priorities](#1-plan-overview--priorities)
2. [WS-1: CSRF Protection System](#ws-1-csrf-protection-system)
3. [WS-2: IDOR Protection & Ownership Verification](#ws-2-idor-protection--ownership-verification)
4. [WS-3: IP Detection & Rate Limiting Hardening](#ws-3-ip-detection--rate-limiting-hardening)
5. [WS-4: Stripe & Webhook Security](#ws-4-stripe--webhook-security)
6. [WS-5: Cryptographic Fixes](#ws-5-cryptographic-fixes)
7. [WS-6: XSS Prevention (Frontend)](#ws-6-xss-prevention-frontend)
8. [WS-7: XSS Prevention (Backend/Templates)](#ws-7-xss-prevention-backendtemplates)
9. [WS-8: AI Credit Race Condition Fix](#ws-8-ai-credit-race-condition-fix)
10. [WS-9: Input Validation & File Upload Hardening](#ws-9-input-validation--file-upload-hardening)
11. [WS-10: Session Security & Sensitive Data](#ws-10-session-security--sensitive-data)
12. [Testing Strategy](#testing-strategy)
13. [Rollout Plan](#rollout-plan)
14. [Risk Assessment](#risk-assessment)

---

## 1. Plan Overview & Priorities

### Execution Order (by risk and dependency)

| Priority | Workstream | Findings Addressed | Risk if Delayed |
|----------|------------|-------------------|-----------------|
| P0 | WS-4: Stripe & Webhook Security | SEC-05, SEC-06, SEC-07 | Financial fraud, PCI violation |
| P0 | WS-2: IDOR Protection | SEC-08 | Cross-tenant data breach |
| P0 | WS-1: CSRF Protection | SEC-01 | Account takeover via CSRF |
| P1 | WS-5: Cryptographic Fixes | SEC-09, SEC-10 | Token forgery, data exposure |
| P1 | WS-3: IP Detection Hardening | SEC-02 | Rate limit bypass, brute force |
| P1 | WS-8: AI Credit Race Condition | SEC-12 | Credit theft |
| P1 | WS-6: XSS Prevention (Frontend) | SEC-14 | Session hijacking |
| P1 | WS-7: XSS Prevention (Backend) | SEC-13, SEC-15, SEC-16 | Stored XSS, code execution |
| P2 | WS-9: Input Validation Hardening | SEC-03, SEC-04, SEC-17, SEC-18 | Spam, file upload attacks |
| P2 | WS-10: Session & Logging Security | SEC-07, SEC-11 | Information disclosure |

### Dependency Graph

```
WS-5 (Crypto) ──> WS-4 (Stripe encryption depends on fixed encrypt/decrypt)
WS-3 (IP)     ──> WS-1 (Rate limiting must be solid before CSRF goes live)
All others are independent and can be developed in parallel.
```

### Guiding Principles

1. **Zero breaking changes** - All fixes must be backward-compatible with existing client installations
2. **Defense in depth** - Layer multiple controls (CSRF + IDOR + rate limiting)
3. **Fail closed** - When security checks fail, deny access (don't fallback to permissive)
4. **Existing patterns** - Use WordPress APIs and existing plugin conventions wherever possible
5. **Testable** - Every change must be verifiable without a full WordPress environment

---

## WS-1: CSRF Protection System

### Problem

The `check_logged_in` permission callback (used by 20+ endpoints) only verifies `is_user_logged_in()` without any nonce verification. Meanwhile, `check_staff_permission` and `check_owner_permission` already verify nonces correctly.

**Affected Endpoints (all using `check_logged_in`):**

| Method | Route | Action |
|--------|-------|--------|
| POST | `/work-orders` | Create maintenance request |
| GET | `/work-orders/{id}` | View work order |
| GET/POST | `/work-orders/{id}/notes` | Read/add notes |
| GET | `/payments/{id}` | View payment |
| POST | `/payments/checkout` | Create checkout session |
| GET/POST | `/documents` | List/upload documents |
| GET | `/documents/{id}` | View document |
| GET/POST | `/messages` | List/send messages |
| GET | `/messages/threads` | List threads |
| GET | `/messages/threads/{id}` | Read thread |
| POST | `/messages/{id}/read` | Mark read |
| GET | `/announcements` | List announcements |
| GET | `/announcements/{id}` | View announcement |
| GET | `/notifications` | List notifications |
| GET | `/notifications/unread-count` | Get count |
| POST | `/notifications/{id}/read` | Mark read |
| POST | `/notifications/mark-all-read` | Mark all read |
| GET/PUT | `/profile` | View/update profile |
| PUT | `/profile/password` | Change password |

### Solution

**Replace `check_logged_in` with a new `check_authenticated` method that verifies both authentication AND nonce.**

#### Implementation

**File: `includes/api/class-rental-gates-rest-api.php`**

```php
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
```

Then update all route registrations that currently reference `check_logged_in`:

```php
// BEFORE (vulnerable):
'permission_callback' => array($this, 'check_logged_in'),

// AFTER (secure):
'permission_callback' => array($this, 'check_authenticated'),
```

**Complete list of changes in `register_routes` methods:**

1. `register_maintenance_routes()` - Lines 390, 398, 416, 420
2. `register_payment_routes()` - Lines 491, 509
3. `register_document_routes()` - Lines 519, 530
4. `register_message_routes()` - Lines 553, 558, 565, 571, 576
5. `register_announcement_routes()` - Lines 587, 600
6. `register_notification_routes()` - Lines 620, 626, 632, 638
7. `register_profile_routes()` - Lines 866, 870, 878

**Keep `check_logged_in` for backward compatibility** but add a deprecation notice:

```php
/**
 * @deprecated Use check_authenticated() instead
 */
public function check_logged_in($request)
{
    return is_user_logged_in();
}
```

#### Frontend Nonce Delivery

Verify that the existing JavaScript sends the `X-WP-Nonce` header. Based on the current code analysis, the frontend REST API calls in `rental-gates.js` use the `RentalGates.api()` helper. We need to ensure it sends the nonce:

**File: `assets/js/rental-gates.js`** - Verify in the `api()` method:

```javascript
api: function(endpoint, method, data, callbacks) {
    callbacks = callbacks || {};

    $.ajax({
        url: rentalGatesData.apiUrl + endpoint,
        method: method || 'GET',
        data: data ? JSON.stringify(data) : undefined,
        contentType: 'application/json',
        beforeSend: function(xhr) {
            // CRITICAL: This must be present
            xhr.setRequestHeader('X-WP-Nonce', rentalGatesData.nonce);
        },
        // ...
    });
}
```

If `rentalGatesData.nonce` is not already being localized, ensure it is in the PHP enqueue:

```php
wp_localize_script('rental-gates', 'rentalGatesData', array(
    'apiUrl' => rest_url('rental-gates/v1/'),
    'nonce'  => wp_create_nonce('wp_rest'),
    // ... other data
));
```

#### Verification Checklist

- [ ] All 20+ endpoints with `check_logged_in` updated to `check_authenticated`
- [ ] Frontend sends `X-WP-Nonce` header on all API calls
- [ ] Tenant portal API calls include nonce
- [ ] Vendor portal API calls include nonce
- [ ] AJAX handlers remain unchanged (they already use `check_ajax_referer`)
- [ ] Public endpoints (`__return_true`) remain unchanged (they're intentionally open)

---

## WS-2: IDOR Protection & Ownership Verification

### Problem

Endpoints protected by role-based permission callbacks (`check_staff_permission`, `check_owner_permission`) verify the user's *role* but not whether the requested entity belongs to the user's *organization*. A staff member from Organization A can access tenants, units, payments, and messages from Organization B by guessing IDs.

**Proof of concept:**
```
User from Org #1 requests: GET /rental-gates/v1/tenants/42
If tenant #42 belongs to Org #2, the data is still returned.
```

### Solution

**Create a centralized ownership verification helper and apply it to every entity retrieval endpoint.**

#### Implementation

**File: `includes/api/class-rental-gates-rest-api.php`**

Add a private helper method:

```php
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

    // Check that the table key exists
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
        // Log the attempted cross-org access
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

    // Join child -> parent to get org_id
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
```

#### Endpoints to Protect

Apply `verify_org_ownership()` at the **start** of each handler:

| Handler | Entity Type | Ownership Column | Method |
|---------|------------|-----------------|--------|
| `get_building($request)` | `buildings` | `organization_id` | Direct |
| `get_building_units($request)` | `buildings` | `organization_id` | Direct |
| `get_unit($request)` | `units` | via `buildings` | Via parent |
| `get_tenant($request)` | `tenants` | `organization_id` | Direct |
| `get_tenant_leases($request)` | `tenants` | `organization_id` | Direct |
| `get_lease($request)` | `leases` | `organization_id` | Direct |
| `get_application($request)` | `applications` | `organization_id` | Direct |
| `get_work_order($request)` | `work_orders` | `organization_id` | Direct |
| `get_work_order_notes($request)` | `work_orders` | `organization_id` | Check parent |
| `get_payment($request)` | `payments` | `organization_id` | Direct |
| `get_document($request)` | `documents` | `organization_id` | Direct |
| `get_thread_messages($request)` | `message_threads` | `organization_id` | Direct |
| `get_announcement($request)` | `announcements` | `organization_id` | Direct |
| `get_vendor($request)` | `vendors` | `organization_id` | Direct |
| `get_lead($request)` | `leads` | `organization_id` | Direct |
| `get_qr_code($request)` | `qr_codes` | `organization_id` | Direct |

**Example transformation for `get_tenant`:**

```php
// BEFORE (vulnerable):
public function get_tenant($request)
{
    $tenant = Rental_Gates_Tenant::get(intval($request->get_param('id')));
    if (!$tenant) {
        return self::error(__('Tenant not found', 'rental-gates'), 'not_found', 404);
    }
    return self::success($tenant);
}

// AFTER (secure):
public function get_tenant($request)
{
    $id = intval($request->get_param('id'));

    // Verify this tenant belongs to the user's organization
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
```

**Example for unit (via parent building):**

```php
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
```

#### List Endpoints (Already Scoped)

List endpoints like `get_tenants`, `get_buildings`, `get_payments` already pass `$this->get_org_id()` to the model's list method, which scopes the query by `WHERE organization_id = %d`. These are safe but should be audited to confirm the scoping is consistent across all list handlers.

#### Special Cases: Tenant & Vendor Portal Endpoints

Tenants and vendors have a narrower scope - they can only access their own data. For tenant-accessible endpoints (`check_logged_in` routes like work orders and messages), add an additional check:

```php
/**
 * For tenant/vendor portal: verify the logged-in user owns or is related to the entity.
 */
private function verify_user_entity_access($entity_type, $entity_id)
{
    $user_id = get_current_user_id();

    // If user is staff/owner/manager, use org ownership check
    if (Rental_Gates_Roles::is_owner_or_manager() || Rental_Gates_Roles::is_staff()) {
        return $this->verify_org_ownership($entity_type, $entity_id);
    }

    // For tenants: verify they are the tenant on the work order / lease / payment
    if (Rental_Gates_Roles::is_tenant()) {
        return $this->verify_tenant_access($entity_type, $entity_id, $user_id);
    }

    // For vendors: verify they are assigned to the work order
    if (Rental_Gates_Roles::is_vendor()) {
        return $this->verify_vendor_access($entity_type, $entity_id, $user_id);
    }

    return self::error(__('Access denied', 'rental-gates'), 'forbidden', 403);
}
```

---

## WS-3: IP Detection & Rate Limiting Hardening

### Problem

`Rental_Gates_Security::get_client_ip()` trusts spoofable headers (`HTTP_X_FORWARDED_FOR`, `HTTP_X_REAL_IP`, `HTTP_CF_CONNECTING_IP`) before `REMOTE_ADDR`. An attacker can send a fake `X-Forwarded-For: 1.2.3.4` header and bypass all rate limiting, login brute-force protection, and IP blocking.

### Solution

**Trust only `REMOTE_ADDR` by default. Add a configuration option for sites behind a known reverse proxy (Cloudflare, nginx, AWS ALB).**

#### Implementation

**File: `includes/class-rental-gates-security.php`**

```php
/**
 * Get client IP address - secure implementation.
 *
 * By default only trusts REMOTE_ADDR. When a trusted proxy is configured
 * (Cloudflare, load balancer), it will also check proxy-set headers but
 * ONLY after verifying the request actually came from the proxy.
 *
 * @return string Client IP address
 */
public static function get_client_ip()
{
    $proxy_mode = get_option('rental_gates_proxy_mode', 'none');

    switch ($proxy_mode) {
        case 'cloudflare':
            // Only trust CF-Connecting-IP if request came from a Cloudflare IP
            if (self::is_cloudflare_ip($_SERVER['REMOTE_ADDR'] ?? '')) {
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
            break;

        case 'trusted_proxy':
            // Trust X-Forwarded-For only if REMOTE_ADDR is in the trusted list
            $trusted_proxies = array_filter(array_map(
                'trim',
                explode(',', get_option('rental_gates_trusted_proxies', ''))
            ));

            if (in_array($_SERVER['REMOTE_ADDR'] ?? '', $trusted_proxies, true)) {
                // Take the first (leftmost = original client) IP from XFF
                $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
                if ($xff) {
                    $ips = array_map('trim', explode(',', $xff));
                    $client_ip = $ips[0];
                    if (filter_var($client_ip, FILTER_VALIDATE_IP)) {
                        return $client_ip;
                    }
                }

                // Fallback to X-Real-IP
                $real_ip = $_SERVER['HTTP_X_REAL_IP'] ?? '';
                if (filter_var($real_ip, FILTER_VALIDATE_IP)) {
                    return $real_ip;
                }
            }
            break;

        case 'none':
        default:
            // Direct connection - only trust REMOTE_ADDR
            break;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Check if an IP is in Cloudflare's published IP ranges.
 * Cached for 24 hours.
 *
 * @param string $ip IP address to check
 * @return bool
 */
private static function is_cloudflare_ip($ip)
{
    // Cloudflare IPv4 ranges (updated periodically)
    // Source: https://www.cloudflare.com/ips-v4
    $cf_ranges = get_transient('rental_gates_cf_ip_ranges');

    if ($cf_ranges === false) {
        // Hardcoded fallback ranges (update periodically)
        $cf_ranges = array(
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22',
            '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
            '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22',
            '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        );

        // Try to fetch fresh list
        $response = wp_remote_get('https://www.cloudflare.com/ips-v4', array('timeout' => 5));
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            $fresh_ranges = array_filter(array_map('trim', explode("\n", $body)));
            if (count($fresh_ranges) > 5) {
                $cf_ranges = $fresh_ranges;
            }
        }

        set_transient('rental_gates_cf_ip_ranges', $cf_ranges, DAY_IN_SECONDS);
    }

    $ip_long = ip2long($ip);
    if ($ip_long === false) {
        return false;
    }

    foreach ($cf_ranges as $range) {
        list($subnet, $bits) = explode('/', $range);
        $subnet_long = ip2long($subnet);
        $mask = -1 << (32 - (int) $bits);
        if (($ip_long & $mask) === ($subnet_long & $mask)) {
            return true;
        }
    }

    return false;
}
```

#### Admin Setting

Add to the WordPress admin settings page (or the plugin's settings API):

```php
// In register_settings or settings_routes handler:
'rental_gates_proxy_mode' => array(
    'type'    => 'select',
    'options' => array(
        'none'          => 'Direct connection (no proxy)',
        'cloudflare'    => 'Behind Cloudflare',
        'trusted_proxy' => 'Behind custom reverse proxy',
    ),
    'default' => 'none',
),
'rental_gates_trusted_proxies' => array(
    'type'        => 'text',
    'description' => 'Comma-separated list of trusted proxy IPs (only used with "custom reverse proxy" mode)',
    'default'     => '',
),
```

#### Rate Limiter Enhancement

Additionally, add a secondary identifier to rate limiting that combines IP + user agent fingerprint as a defense-in-depth measure:

**File: `includes/class-rental-gates-rate-limit.php`**

```php
private static function get_key($endpoint, $identifier = null) {
    if ($identifier === null) {
        $ip = Rental_Gates_Security::get_client_ip();
        // Add user-agent hash as secondary signal (not primary - can be spoofed too)
        $ua_hash = substr(md5($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 8);
        $identifier = $ip . '_' . $ua_hash;
    }

    return 'rg_rate_' . $endpoint . '_' . md5($identifier);
}
```

---

## WS-4: Stripe & Webhook Security

### Problem A: Stripe Secret Key in Plaintext (SEC-05)

The Stripe secret key is stored in `wp_options` as plain text. Database exposure = financial compromise.

### Problem B: Unverified Webhook Fallback (SEC-06)

When `Stripe\Webhook` class isn't available, the webhook handler falls back to `json_decode($payload)` without any signature verification. An attacker can forge any webhook event.

### Problem C: Sensitive Data Logging (SEC-07)

Payment amounts, Stripe account IDs, and financial data are logged via `error_log()`.

### Solution

#### Part A: Encrypt Stripe Keys at Rest

**Dependency:** Requires WS-5 (Cryptographic Fixes) to be completed first so the encrypt/decrypt functions are secure.

**File: `includes/class-rental-gates-stripe.php`**

```php
/**
 * Get secret key - decrypts from storage.
 * Supports both encrypted (new) and plaintext (legacy) formats.
 */
public static function get_secret_key()
{
    self::init();

    // Try encrypted key first (new format)
    $encrypted_key = get_option('rental_gates_stripe_' . self::$mode . '_sk_encrypted', '');
    if (!empty($encrypted_key)) {
        $key = Rental_Gates_Security::decrypt($encrypted_key);
        if ($key !== false && !empty($key)) {
            return $key;
        }
    }

    // Fallback to plaintext (legacy) - and migrate it
    $key = get_option('rental_gates_stripe_' . self::$mode . '_sk', '');
    if (empty($key)) {
        $key = get_option('rental_gates_stripe_' . self::$mode . '_secret_key', '');
    }

    // Auto-migrate plaintext key to encrypted storage
    if (!empty($key)) {
        self::store_secret_key($key);
    }

    return $key;
}

/**
 * Store a secret key encrypted.
 * Removes the plaintext version after encryption.
 */
public static function store_secret_key($key)
{
    self::init();

    $encrypted = Rental_Gates_Security::encrypt($key);
    if ($encrypted !== false) {
        update_option('rental_gates_stripe_' . self::$mode . '_sk_encrypted', $encrypted);
        // Remove plaintext versions
        delete_option('rental_gates_stripe_' . self::$mode . '_sk');
        delete_option('rental_gates_stripe_' . self::$mode . '_secret_key');
    }
}

/**
 * Store the webhook secret encrypted.
 */
public static function store_webhook_secret($secret)
{
    $encrypted = Rental_Gates_Security::encrypt($secret);
    if ($encrypted !== false) {
        update_option('rental_gates_stripe_webhook_secret_encrypted', $encrypted);
        delete_option('rental_gates_stripe_webhook_secret');
    }
}
```

#### Part B: Remove Unverified Webhook Fallback

**File: `includes/subscription/class-rental-gates-webhook-handler.php`**

```php
public static function handle_request($request)
{
    $payload = $request->get_body();
    $sig_header = $request->get_header('stripe-signature');

    // Get webhook secret (try encrypted first, then legacy)
    $webhook_secret = '';
    $encrypted_secret = get_option('rental_gates_stripe_webhook_secret_encrypted', '');
    if (!empty($encrypted_secret)) {
        $webhook_secret = Rental_Gates_Security::decrypt($encrypted_secret);
    }
    if (empty($webhook_secret)) {
        $webhook_secret = get_option('rental_gates_stripe_webhook_secret', '');
        // Auto-migrate if found
        if (!empty($webhook_secret)) {
            Rental_Gates_Stripe::store_webhook_secret($webhook_secret);
        }
    }

    // SECURITY: Webhook secret MUST be configured
    if (empty($webhook_secret)) {
        Rental_Gates_Security::log_security_event('webhook_unconfigured', array(
            'ip' => Rental_Gates_Security::get_client_ip(),
        ));
        return new WP_REST_Response(
            array('error' => 'Webhook secret not configured'),
            500
        );
    }

    // SECURITY: Signature header MUST be present
    if (empty($sig_header)) {
        Rental_Gates_Security::log_security_event('webhook_missing_signature', array(
            'ip' => Rental_Gates_Security::get_client_ip(),
        ));
        return new WP_REST_Response(array('error' => 'Missing signature'), 400);
    }

    // SECURITY: Stripe SDK MUST be available for signature verification
    if (!class_exists('Stripe\\Webhook')) {
        Rental_Gates_Security::log_security_event('webhook_sdk_missing', array(
            'ip' => Rental_Gates_Security::get_client_ip(),
        ));
        // FAIL CLOSED - never process unverified webhooks
        return new WP_REST_Response(
            array('error' => 'Stripe SDK not available for signature verification'),
            500
        );
    }

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $sig_header,
            $webhook_secret
        );
    } catch (\UnexpectedValueException $e) {
        return new WP_REST_Response(array('error' => 'Invalid payload'), 400);
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        Rental_Gates_Security::log_security_event('webhook_invalid_signature', array(
            'ip' => Rental_Gates_Security::get_client_ip(),
        ));
        return new WP_REST_Response(array('error' => 'Invalid signature'), 400);
    }

    // Idempotency: check if we've already processed this event
    if (self::is_event_processed($event->id)) {
        return new WP_REST_Response(array('received' => true, 'duplicate' => true), 200);
    }

    // Process the event
    $result = self::dispatch_event($event);

    // Mark event as processed
    self::mark_event_processed($event->id, $event->type);

    return new WP_REST_Response(array('received' => true), 200);
}

/**
 * Check if a webhook event has already been processed (idempotency).
 */
private static function is_event_processed($event_id)
{
    global $wpdb;
    $tables = Rental_Gates_Database::get_table_names();

    // Use activity_log to track processed events
    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$tables['activity_log']}
         WHERE action = 'webhook_processed'
         AND new_values LIKE %s
         AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
        '%' . $wpdb->esc_like($event_id) . '%'
    ));
}

/**
 * Record that a webhook event was processed.
 */
private static function mark_event_processed($event_id, $event_type)
{
    global $wpdb;
    $tables = Rental_Gates_Database::get_table_names();

    $wpdb->insert(
        $tables['activity_log'],
        array(
            'user_id'     => 0,
            'action'      => 'webhook_processed',
            'entity_type' => 'stripe_webhook',
            'new_values'  => wp_json_encode(array(
                'event_id'   => $event_id,
                'event_type' => $event_type,
            )),
            'ip_address'  => Rental_Gates_Security::get_client_ip(),
            'created_at'  => current_time('mysql'),
        )
    );
}

/**
 * Dispatch event to appropriate handler.
 */
private static function dispatch_event($event)
{
    switch ($event->type) {
        case 'invoice.paid':
            return self::handle_invoice_paid($event->data->object);
        case 'invoice.payment_failed':
            return self::handle_invoice_payment_failed($event->data->object);
        case 'customer.subscription.updated':
            return self::handle_subscription_updated($event->data->object);
        case 'customer.subscription.deleted':
            return self::handle_subscription_deleted($event->data->object);
        default:
            // Log unhandled event types for debugging
            return null;
    }
}
```

#### Part C: Redact Sensitive Data from Logs

**File: `includes/class-rental-gates-stripe.php`** - Replace all `error_log()` calls:

```php
// BEFORE:
error_log('Rental Gates Stripe API Error [' . $code . ']: ' . $error_message . ' (endpoint: ' . $endpoint . ')');

// AFTER:
error_log('Rental Gates Stripe API Error [' . $code . ']: ' . $error_message . ' (endpoint: ' . self::redact_endpoint($endpoint) . ')');

// Add redaction helper:
private static function redact_endpoint($endpoint)
{
    // Strip any query parameters that might contain sensitive data
    $parts = explode('?', $endpoint, 2);
    return $parts[0];
}
```

Remove logging of financial data entirely from `handle_subscription_updated()`:

```php
// BEFORE:
error_log(sprintf(
    'Rental Gates Webhook - Subscription updated: stripe_id=%s, status=%s, billing_cycle=%s',
    $subscription->id,
    $subscription->status,
    $billing_cycle ?? 'not_determined'
));

// AFTER: Remove this log entirely or use the security audit log:
Rental_Gates_Security::log_security_event('webhook_subscription_updated', array(
    'status'        => $subscription->status,
    'billing_cycle' => $billing_cycle ?? 'not_determined',
));
```

---

## WS-5: Cryptographic Fixes

### Problem A: Weak Encryption (SEC-09)

AES-256-CBC is used with `wp_salt('auth')` directly as the key (variable length, not 32 bytes). No ciphertext authentication (vulnerable to padding oracle attacks).

### Problem B: Token Hashing (SEC-10)

`hash('sha256', $token . wp_salt())` is vulnerable to length extension attacks. Should use HMAC.

### Solution

**File: `includes/class-rental-gates-security.php`**

#### Fix Encryption (Encrypt-then-MAC)

```php
/**
 * Encrypt sensitive data using AES-256-CBC with HMAC authentication.
 *
 * Format: base64(IV + ciphertext + HMAC)
 * - IV: 16 bytes (AES block size)
 * - HMAC: 32 bytes (SHA-256)
 * - Ciphertext: variable length
 *
 * @param string $data Plaintext to encrypt
 * @return string|false Base64-encoded encrypted data, or false on failure
 */
public static function encrypt($data)
{
    if (empty($data)) {
        return false;
    }

    // Derive a proper 32-byte key from the WordPress salt
    $key = hash('sha256', wp_salt('auth'), true); // true = raw binary output

    // Generate random IV
    $iv = random_bytes(16);

    // Encrypt
    $ciphertext = openssl_encrypt(
        $data,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($ciphertext === false) {
        return false;
    }

    // Authenticate: HMAC-SHA256 over IV + ciphertext (encrypt-then-MAC)
    $hmac_key = hash('sha256', wp_salt('secure_auth'), true);
    $hmac = hash_hmac('sha256', $iv . $ciphertext, $hmac_key, true);

    // Format: IV (16) + ciphertext (variable) + HMAC (32)
    return base64_encode($iv . $ciphertext . $hmac);
}

/**
 * Decrypt data encrypted with encrypt().
 * Verifies HMAC before decryption to prevent padding oracle attacks.
 *
 * @param string $data Base64-encoded encrypted data
 * @return string|false Decrypted plaintext, or false on failure
 */
public static function decrypt($data)
{
    if (empty($data)) {
        return false;
    }

    $raw = base64_decode($data, true);
    if ($raw === false) {
        return false;
    }

    // Minimum length: 16 (IV) + 16 (min ciphertext) + 32 (HMAC) = 64
    if (strlen($raw) < 64) {
        // Try legacy format (IV + ciphertext, no HMAC)
        return self::decrypt_legacy($data);
    }

    // Extract components
    $iv = substr($raw, 0, 16);
    $hmac = substr($raw, -32);
    $ciphertext = substr($raw, 16, -32);

    // Verify HMAC FIRST (before any decryption attempt)
    $hmac_key = hash('sha256', wp_salt('secure_auth'), true);
    $expected_hmac = hash_hmac('sha256', $iv . $ciphertext, $hmac_key, true);

    if (!hash_equals($expected_hmac, $hmac)) {
        // HMAC mismatch - possible tampering or legacy format
        return self::decrypt_legacy($data);
    }

    // Derive key
    $key = hash('sha256', wp_salt('auth'), true);

    // Decrypt
    $plaintext = openssl_decrypt(
        $ciphertext,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    return $plaintext !== false ? $plaintext : false;
}

/**
 * Decrypt data in the legacy format (pre-security-hardening).
 * Used for backward compatibility during migration.
 *
 * @param string $data Base64-encoded legacy encrypted data
 * @return string|false
 */
private static function decrypt_legacy($data)
{
    $raw = base64_decode($data, true);
    if ($raw === false || strlen($raw) < 17) {
        return false;
    }

    // Legacy format: IV (16) + ciphertext
    $iv = substr($raw, 0, 16);
    $ciphertext = substr($raw, 16);

    // Legacy used wp_salt('auth') directly (not hashed)
    $key_legacy = wp_salt('auth');
    $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key_legacy, OPENSSL_RAW_DATA, $iv);

    if ($plaintext !== false) {
        return $plaintext;
    }

    // Also try with hashed key (in case of partial migration)
    $key_new = hash('sha256', wp_salt('auth'), true);
    return openssl_decrypt($ciphertext, 'AES-256-CBC', $key_new, OPENSSL_RAW_DATA, $iv);
}
```

#### Fix Token Hashing

```php
/**
 * Hash a token using HMAC-SHA256 (replacing vulnerable hash + concatenation).
 *
 * @param string $token Raw token to hash
 * @return string Hex-encoded HMAC
 */
public static function hash_token($token)
{
    return hash_hmac('sha256', $token, wp_salt());
}

/**
 * Verify a token against its HMAC hash.
 * Supports both new HMAC format and legacy hash format for migration.
 *
 * @param string $token Raw token
 * @param string $hash  Stored hash to verify against
 * @return bool
 */
public static function verify_token($token, $hash)
{
    // Try new HMAC format first
    if (hash_equals($hash, hash_hmac('sha256', $token, wp_salt()))) {
        return true;
    }

    // Fallback: legacy format (hash + concatenation)
    if (hash_equals($hash, hash('sha256', $token . wp_salt()))) {
        return true;
    }

    return false;
}
```

---

## WS-6: XSS Prevention (Frontend)

### Problem

`assets/js/rental-gates.js` inserts user-controlled data into the DOM via `.html()` and template literals without escaping.

**Vulnerable Sinks:**
- Line 92-94: `$modal.find('.rg-modal-title').html(settings.title)` - Modal title
- Line 288-294: `showToast()` - `${message}` injected raw into template
- Line 513: Gallery preview `.html()` with image URLs

### Solution

**Add an `escapeHtml` utility and apply it consistently.**

**File: `assets/js/rental-gates.js`**

```javascript
// Add at the top of the RentalGates namespace:

/**
 * Escape HTML special characters to prevent XSS.
 * @param {string} str - Raw string to escape
 * @returns {string} HTML-safe string
 */
escapeHtml: function(str) {
    if (typeof str !== 'string') return '';
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
},
```

#### Fix Modal System (Lines 92-94)

```javascript
// BEFORE:
$modal.find('.rg-modal-title').html(settings.title);
$modal.find('.rg-modal-body').html(settings.body);
$modal.find('.rg-modal-footer').html(settings.footer);

// AFTER:
// Title is always plain text - use .text()
$modal.find('.rg-modal-title').text(settings.title);
// Body and footer may contain HTML form elements - keep .html() but
// callers must sanitize their input. Document this requirement.
$modal.find('.rg-modal-body').html(settings.body);
$modal.find('.rg-modal-footer').html(settings.footer);
```

#### Fix Toast System (Lines 288-294)

```javascript
// BEFORE:
const $toast = $(`
    <div class="rg-toast rg-toast-${type}">
        <span class="rg-toast-icon">${icons[type]}</span>
        <span class="rg-toast-message">${message}</span>
        <button type="button" class="rg-toast-close">&times;</button>
    </div>
`);

// AFTER:
const $toast = $('<div>', {
    class: 'rg-toast rg-toast-' + RentalGates.escapeHtml(type)
}).append(
    $('<span>', { class: 'rg-toast-icon' }).html(icons[type] || icons['info']),
    $('<span>', { class: 'rg-toast-message' }).text(message),
    $('<button>', { type: 'button', class: 'rg-toast-close' }).html('&times;')
);
```

#### Fix Gallery Preview (Line 513)

```javascript
// BEFORE:
$preview.html(`
    <img src="${attachment.url}" alt="">
    <button type="button" class="rg-remove-image">&times;</button>
`);

// AFTER:
$preview.empty().append(
    $('<img>', { src: attachment.url, alt: '' }),
    $('<button>', { type: 'button', class: 'rg-remove-image' }).html('&times;')
);
$preview.addClass('has-image');
```

#### Fix PWA Notifications (`assets/js/pwa.js`)

Apply the same escapeHtml pattern to all `innerHTML` assignments:

```javascript
// BEFORE:
notification.innerHTML = `<svg>...</svg> ${count} item(s) synced`;

// AFTER:
notification.textContent = '';
// Add SVG via DOM manipulation
const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
// ... set SVG attributes ...
notification.appendChild(svg);
notification.appendChild(document.createTextNode(` ${count} item(s) synced`));
```

---

## WS-7: XSS Prevention (Backend/Templates)

### Problem A: `extract($data)` in Email Templates (SEC-13)

`extract()` before `include` can overwrite local variables (`$template_file`, `$data`, etc.).

### Problem B: `json_encode()` vs `wp_json_encode()` (SEC-15)

`json_encode()` doesn't escape `</script>`, allowing script context breakout.

### Problem C: Raw `$_GET` (SEC-16)

`$_GET['qr']` used without sanitization.

### Solution

#### Part A: Remove `extract()` from Email System

**File: `includes/class-rental-gates-email.php` (line 192)**

```php
// BEFORE:
extract($data);
ob_start();
include $template_file;
$content = ob_get_clean();

// AFTER:
// Pass $data explicitly - templates access it as $data['key']
$email_data = $data; // Isolate to prevent naming conflicts
ob_start();
include $template_file;
$content = ob_get_clean();
```

Then update all 31 email templates to use `$email_data['variable']` instead of `$variable`:

```php
// BEFORE (in template):
<h1>Hello <?php echo esc_html($tenant_name); ?></h1>

// AFTER (in template):
<h1>Hello <?php echo esc_html($email_data['tenant_name'] ?? ''); ?></h1>
```

#### Part B: Replace All `json_encode()` with `wp_json_encode()` in Templates

**Files affected (from grep results):**

| File | Lines | Count |
|------|-------|-------|
| `templates/public/unit.php` | 469 | 1 |
| `templates/dashboard/sections/buildings-form.php` | 952 | 1 |
| `templates/dashboard/sections/payments.php` | 686-688 | 3 |
| `templates/dashboard/sections/lease-form.php` | 333-334 | 2 |
| `templates/dashboard/sections/marketing-analytics.php` | 349-351 | 3 |
| `templates/dashboard/sections/documents.php` | 357-384 | 7 |
| `templates/dashboard/sections/unit-form.php` | 837 | 1 |
| `templates/dashboard/sections/reports.php` | 968-1523 | 4+ |

**Global find-and-replace:**

```php
// BEFORE (in templates):
<?php echo json_encode($data); ?>

// AFTER:
<?php echo wp_json_encode($data); ?>
```

`wp_json_encode()` automatically adds `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` which escapes `<`, `>`, `&`, `'`, and `"`.

#### Part C: Sanitize `$_GET['qr']`

**File: `templates/public/unit.php` (line ~68)**

```php
// BEFORE:
if (!empty($_GET['qr'])) {
    Rental_Gates_QR::track_scan($_GET['qr'], $unit_data);
}

// AFTER:
if (!empty($_GET['qr'])) {
    $qr_code = sanitize_text_field(wp_unslash($_GET['qr']));
    Rental_Gates_QR::track_scan($qr_code, $unit_data);
}
```

---

## WS-8: AI Credit Race Condition Fix

### Problem

In `class-rental-gates-ai-credits.php`, the `deduct()` method:
1. Reads balance via `get_balance()` (SELECT)
2. Checks if balance >= amount (PHP)
3. Updates balance (UPDATE)

Between steps 1 and 3, another concurrent request can read the same balance and both succeed, spending credits that don't exist.

### Solution

**Use an atomic UPDATE with a WHERE clause that acts as both check and deduction in a single query.**

**File: `includes/class-rental-gates-ai-credits.php`**

```php
/**
 * Deduct credits atomically to prevent race conditions.
 *
 * Uses a single UPDATE...WHERE query that only succeeds if
 * sufficient credits exist, eliminating the TOCTOU window.
 *
 * @param int    $org_id       Organization ID
 * @param int    $amount       Credits to deduct
 * @param string $tool         Tool name (for logging)
 * @param int    $reference_id Related entity ID (optional)
 * @return true|WP_Error
 */
public function deduct($org_id, $amount, $tool, $reference_id = null)
{
    global $wpdb;

    if (!$org_id) {
        return new WP_Error('no_org', __('Organization not found', 'rental-gates'));
    }

    if ($amount <= 0) {
        return new WP_Error('invalid_amount', __('Invalid credit amount', 'rental-gates'));
    }

    $balance_table = $wpdb->prefix . 'rg_ai_credit_balances';
    $tables = Rental_Gates_Database::get_table_names();

    // Use a transaction with row-level locking
    $wpdb->query('START TRANSACTION');

    try {
        // Lock the row and get current balance
        $balance = $wpdb->get_row($wpdb->prepare(
            "SELECT subscription_credits, bonus_credits, purchased_credits
             FROM {$balance_table}
             WHERE organization_id = %d
             FOR UPDATE",
            $org_id
        ));

        if (!$balance) {
            // Initialize balance if it doesn't exist
            $this->initialize_balance($org_id);
            $balance = $wpdb->get_row($wpdb->prepare(
                "SELECT subscription_credits, bonus_credits, purchased_credits
                 FROM {$balance_table}
                 WHERE organization_id = %d
                 FOR UPDATE",
                $org_id
            ));

            if (!$balance) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('balance_error', __('Could not initialize credit balance', 'rental-gates'));
            }
        }

        $total = intval($balance->subscription_credits)
               + intval($balance->bonus_credits)
               + intval($balance->purchased_credits);

        if ($total < $amount) {
            $wpdb->query('ROLLBACK');
            return new WP_Error(
                'insufficient_credits',
                sprintf(
                    __('Not enough AI credits. You need %d credit(s), but have %d.', 'rental-gates'),
                    $amount,
                    $total
                ),
                array('required' => $amount, 'available' => $total)
            );
        }

        // Calculate deductions (subscription first, then bonus, then purchased)
        $remaining = $amount;
        $sub_deduct = min($remaining, intval($balance->subscription_credits));
        $remaining -= $sub_deduct;

        $bonus_deduct = min($remaining, intval($balance->bonus_credits));
        $remaining -= $bonus_deduct;

        $purchased_deduct = min($remaining, intval($balance->purchased_credits));

        // Atomic update within the transaction (row is still locked)
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$balance_table} SET
                subscription_credits = subscription_credits - %d,
                bonus_credits = bonus_credits - %d,
                purchased_credits = purchased_credits - %d,
                updated_at = NOW()
             WHERE organization_id = %d
             AND subscription_credits >= %d
             AND bonus_credits >= %d
             AND purchased_credits >= %d",
            $sub_deduct,
            $bonus_deduct,
            $purchased_deduct,
            $org_id,
            $sub_deduct,
            $bonus_deduct,
            $purchased_deduct
        ));

        if ($updated === false || $updated === 0) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('deduction_failed', __('Credit deduction failed. Please try again.', 'rental-gates'));
        }

        // Log the transaction
        $transaction_table = $wpdb->prefix . 'rg_ai_credit_transactions';
        $txn_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$transaction_table}'");

        if ($txn_table_exists) {
            $wpdb->insert(
                $transaction_table,
                array(
                    'organization_id'  => $org_id,
                    'user_id'          => get_current_user_id(),
                    'type'             => 'deduction',
                    'amount'           => -$amount,
                    'tool'             => $tool,
                    'reference_id'     => $reference_id,
                    'balance_after'    => $total - $amount,
                    'description'      => sprintf('AI %s usage', $tool),
                    'created_at'       => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%s')
            );
        }

        // Also log to ai_usage table for backward compatibility
        if (isset($tables['ai_usage'])) {
            $wpdb->insert(
                $tables['ai_usage'],
                array(
                    'organization_id' => $org_id,
                    'user_id'         => get_current_user_id(),
                    'tool'            => $tool,
                    'credits_used'    => $amount,
                    'reference_id'    => $reference_id,
                    'created_at'      => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%d', '%d', '%s')
            );
        }

        $wpdb->query('COMMIT');
        return true;

    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('deduction_error', $e->getMessage());
    }
}
```

---

## WS-9: Input Validation & File Upload Hardening

### Problem A: Unauthenticated Organization Creation (SEC-03)

`POST /organizations` has `__return_true` permission callback.

### Problem B: Unauthenticated Application Spam (SEC-04)

`POST /applications` is fully open.

### Problem C: File Upload Extension Bypass (SEC-17)

MIME is validated but file extension is not.

### Problem D: Unwhitelisted `orderby` (SEC-18)

SQL `ORDER BY` accepts any column name from user input.

### Solution

#### Part A: Protect Organization Creation

```php
// In register_organization_routes():
array(
    'methods' => 'POST',
    'callback' => array($this, 'create_organization'),
    // Only authenticated users can create organizations
    'permission_callback' => array($this, 'check_authenticated'),
),
```

The `create_organization` handler should also rate-limit:

```php
public function create_organization($request)
{
    // Rate limit org creation: 3 per hour per user
    $rate_check = Rental_Gates_Rate_Limit::check('org_creation', 'user_' . get_current_user_id());
    if (is_wp_error($rate_check)) {
        return self::error($rate_check->get_error_message(), 'rate_limited', 429);
    }
    // ... existing logic
}
```

Add rate limit config:
```php
'org_creation' => array('limit' => 3, 'window' => 3600),
```

#### Part B: Add Honeypot & Rate Limiting to Applications

```php
public function create_application($request)
{
    // Rate limit: 5 applications per hour per IP
    $rate_check = Rental_Gates_Rate_Limit::check('public_application');
    if (is_wp_error($rate_check)) {
        return self::error($rate_check->get_error_message(), 'rate_limited', 429);
    }

    // Honeypot check - the frontend includes a hidden field that should be empty
    $honeypot = $request->get_param('website_url'); // or similar trap field
    if (!empty($honeypot)) {
        // Bot detected - return success to avoid revealing the trap
        return self::success(array('id' => 0), __('Application submitted', 'rental-gates'), 201);
    }

    // Time-based check - form must have been loaded for at least 3 seconds
    $form_loaded_at = intval($request->get_param('_form_ts'));
    if ($form_loaded_at > 0 && (time() - $form_loaded_at) < 3) {
        return self::success(array('id' => 0), __('Application submitted', 'rental-gates'), 201);
    }

    // ... existing validation and processing
}
```

Add rate limit config:
```php
'public_application' => array('limit' => 5, 'window' => 3600),
```

#### Part C: File Extension Validation

**File: `includes/class-rental-gates-security.php`**

```php
public static function validate_file_upload($file, $allowed_types = null, $max_size = null)
{
    // ... existing validation code ...

    // AFTER MIME check, ADD extension validation:

    // Extension whitelist matching allowed MIME types
    $mime_to_extensions = array(
        'image/jpeg' => array('jpg', 'jpeg'),
        'image/png'  => array('png'),
        'image/gif'  => array('gif'),
        'image/webp' => array('webp'),
        'application/pdf' => array('pdf'),
        'application/msword' => array('doc'),
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => array('docx'),
    );

    // Get file extension
    $filename = $file['name'] ?? '';
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // Build allowed extensions from allowed MIME types
    $allowed_extensions = array();
    foreach ($allowed_types as $mime) {
        if (isset($mime_to_extensions[$mime])) {
            $allowed_extensions = array_merge($allowed_extensions, $mime_to_extensions[$mime]);
        }
    }

    if (!in_array($extension, $allowed_extensions, true)) {
        $errors[] = __('File extension is not allowed.', 'rental-gates');
    }

    // Double-extension check (e.g., "image.php.jpg")
    $basename = pathinfo($filename, PATHINFO_FILENAME);
    $dangerous_extensions = array('php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'phar', 'cgi', 'pl', 'py', 'sh', 'bash');
    foreach ($dangerous_extensions as $ext) {
        if (stripos($basename, '.' . $ext) !== false) {
            $errors[] = __('File name contains a disallowed extension.', 'rental-gates');
            break;
        }
    }

    return empty($errors) ? true : $errors;
}
```

#### Part D: Whitelist `orderby` Parameter

**File: `includes/api/class-rental-gates-rest-api.php`**

```php
public static function get_pagination_args($request)
{
    // Whitelist of allowed orderby columns
    $allowed_orderby = array(
        'id', 'created_at', 'updated_at', 'name', 'title',
        'status', 'email', 'amount', 'date', 'due_date',
        'first_name', 'last_name', 'rent_amount', 'priority',
    );

    $orderby = sanitize_text_field($request->get_param('orderby') ?: 'created_at');
    if (!in_array($orderby, $allowed_orderby, true)) {
        $orderby = 'created_at'; // Safe default
    }

    return array(
        'page'     => max(1, intval($request->get_param('page') ?: 1)),
        'per_page' => min(100, max(1, intval($request->get_param('per_page') ?: 20))),
        'orderby'  => $orderby,
        'order'    => strtoupper($request->get_param('order')) === 'ASC' ? 'ASC' : 'DESC',
        'search'   => sanitize_text_field($request->get_param('search') ?: ''),
    );
}
```

---

## WS-10: Session Security & Sensitive Data

### Problem A: Support Mode Uses PHP Sessions (SEC-11)

PHP sessions are unreliable in WordPress (especially with object caching, REST API, and load balancers).

### Problem B: No Timeout on Support Mode

An admin can impersonate a user indefinitely.

### Solution

**Replace PHP sessions with WordPress user meta (transient-like behavior).**

**File: `includes/class-rental-gates-security.php`**

```php
/**
 * Support mode timeout in seconds (30 minutes)
 */
const SUPPORT_MODE_TIMEOUT = 1800;

/**
 * Check if current admin is in support mode.
 * Uses WordPress user meta instead of PHP sessions.
 */
public static function is_support_mode()
{
    $admin_id = get_current_user_id();
    if (!$admin_id) {
        return false;
    }

    $support_data = get_user_meta($admin_id, '_rg_support_mode', true);
    if (empty($support_data)) {
        return false;
    }

    // Check timeout
    $started = strtotime($support_data['started_at'] ?? '');
    if (!$started || (time() - $started) > self::SUPPORT_MODE_TIMEOUT) {
        // Expired - auto-exit
        self::exit_support_mode();
        return false;
    }

    return true;
}

/**
 * Get support mode details from user meta.
 */
public static function get_support_mode_details()
{
    if (!self::is_support_mode()) {
        return null;
    }

    $admin_id = get_current_user_id();
    return get_user_meta($admin_id, '_rg_support_mode', true);
}

/**
 * Enter support mode using user meta.
 */
public static function enter_support_mode($admin_user_id, $target_user_id, $organization_id, $reason)
{
    if (!Rental_Gates_Roles::is_site_admin($admin_user_id)) {
        return new WP_Error('unauthorized', __('Only site admins can use support mode.', 'rental-gates'));
    }

    if (empty($reason)) {
        return new WP_Error('missing_reason', __('Support mode requires a reason.', 'rental-gates'));
    }

    $support_data = array(
        'admin_user_id'     => $admin_user_id,
        'viewing_as_user_id' => $target_user_id,
        'organization_id'   => $organization_id,
        'reason'            => sanitize_text_field($reason),
        'started_at'        => current_time('mysql'),
    );

    update_user_meta($admin_user_id, '_rg_support_mode', $support_data);

    // Log support mode entry
    self::log_security_event('support_mode_enter', $support_data);

    return true;
}

/**
 * Exit support mode.
 */
public static function exit_support_mode()
{
    $admin_id = get_current_user_id();
    if (!$admin_id) {
        return;
    }

    $details = get_user_meta($admin_id, '_rg_support_mode', true);
    if (!empty($details)) {
        self::log_security_event('support_mode_exit', $details);
    }

    delete_user_meta($admin_id, '_rg_support_mode');
}
```

---

## Testing Strategy

### Unit Testing (Per Workstream)

| Workstream | Test Approach |
|------------|---------------|
| WS-1: CSRF | Send requests without nonce, verify 401/403. Send with valid nonce, verify success. |
| WS-2: IDOR | Create 2 orgs. User from Org A requests entity from Org B. Verify 404 returned. |
| WS-3: IP | Send requests with spoofed X-Forwarded-For. Verify rate limit uses REMOTE_ADDR. |
| WS-4: Stripe | Send unsigned webhook. Verify 400/500. Send with valid signature. Verify 200. |
| WS-5: Crypto | Encrypt → decrypt roundtrip. Tamper with ciphertext → verify decrypt fails. Test legacy data migration. |
| WS-6: XSS (JS) | Insert `<script>alert(1)</script>` in building name. Verify it's escaped in toast/modal. |
| WS-7: XSS (PHP) | Insert `</script><script>alert(1)` in data. Verify wp_json_encode escapes it. |
| WS-8: Credits | Send 10 concurrent AI requests with 5 credits remaining. Verify exactly 5 succeed. |
| WS-9: Uploads | Upload `shell.php` with image MIME. Verify rejection. Upload with double extension. Verify rejection. |
| WS-10: Session | Enter support mode. Wait 31 minutes. Verify auto-exit. |

### Integration Testing

1. **Full registration flow** - Register, get nonce, make authenticated API call
2. **Cross-org isolation** - Create 2 organizations, verify complete data isolation
3. **Stripe webhook cycle** - Simulate invoice.paid with signed payload, verify subscription activates
4. **AI credit exhaustion** - Use all credits, verify next request fails gracefully
5. **Rate limiting** - Send requests beyond limit, verify 429 response with Retry-After header

### Regression Testing

- All existing AJAX handlers continue to work (they have their own nonce checking)
- Public endpoints (map, building, unit pages) continue to work without auth
- Stripe checkout flow completes successfully
- Email templates render correctly without `extract()`
- Legacy encrypted data can still be decrypted after crypto upgrade

---

## Rollout Plan

### Stage 1: Internal Testing (Days 1-3)

1. Apply all changes to a staging environment
2. Run full test suite
3. Manual testing of all 5 portals (admin, owner, staff, tenant, vendor)
4. Verify Stripe test mode webhooks
5. Load test AI credit concurrent deductions

### Stage 2: Canary Release (Days 4-5)

1. Deploy to 1-2 production sites with feature flags
2. Monitor error logs for regressions
3. Verify existing user sessions continue to work
4. Check that automatic Stripe key migration succeeds

### Stage 3: Full Rollout (Day 6+)

1. Deploy to all sites via plugin update
2. Monitor for 48 hours
3. Auto-migration of plaintext Stripe keys to encrypted storage
4. Verify legacy token formats still validate during transition period

### Rollback Plan

Each workstream is independent. If a specific fix causes issues:

1. **WS-1 (CSRF):** Revert `check_authenticated` to `check_logged_in`
2. **WS-4 (Stripe):** Encrypted keys have plaintext fallback
3. **WS-5 (Crypto):** `decrypt_legacy()` handles old format
4. **WS-7 (Templates):** `wp_json_encode()` is drop-in compatible with `json_encode()`

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Nonce check breaks tenant portal | Medium | High | Test tenant portal thoroughly. Ensure nonce is passed in all JS API calls. |
| IDOR check breaks site admin features | Low | High | Site admin bypass is built into `verify_org_ownership`. |
| Stripe key migration loses keys | Low | Critical | Plaintext fallback reads old format. Migration only deletes after successful encryption. |
| Crypto changes break existing encrypted data | Low | High | `decrypt_legacy()` handles both old and new formats. |
| Credit locking causes deadlocks | Low | Medium | Transaction timeout is implicit. Row-level lock released on COMMIT/ROLLBACK. |
| `wp_json_encode` breaks JSON in frontend | Very Low | Medium | It's a superset of `json_encode` flags. All valid JSON remains valid. |
| File upload extension check too strict | Low | Low | Extension list matches MIME whitelist exactly. Can be extended via parameter. |

---

*This plan addresses all 7 Critical and 14 High severity findings from the security audit. Each workstream is designed to be implemented, tested, and deployed independently.*
