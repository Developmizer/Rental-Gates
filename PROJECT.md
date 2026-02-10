# Rental Gates - Project Description

> **Version:** 2.44.0 | **Platform:** WordPress Plugin (Multi-tenant SaaS) | **Last Updated:** February 2026

Rental Gates is a comprehensive property management SaaS platform built as a WordPress plugin. It delivers a full-featured web application — owner dashboards, tenant/staff/vendor portals, public listings, online payments, AI tools, and marketing automation — while leveraging WordPress purely as the hosting runtime. End users never see WordPress; they interact with a standalone, custom-branded experience.

**Business Model:** Multi-tenant SaaS with tiered subscription plans. Property owners/managers subscribe to manage buildings, units, tenants, leases, and finances. Tenants pay rent online. Staff coordinate operations. Vendors handle maintenance. The platform earns revenue from subscriptions and a 2.5% fee on rent payments processed through Stripe Connect.

---

## Table of Contents

1. [Platform Overview](#platform-overview)
2. [User Roles & Portals](#user-roles--portals)
3. [Owner Dashboard Modules](#owner-dashboard-modules)
4. [Tenant Portal](#tenant-portal)
5. [Staff Portal](#staff-portal)
6. [Vendor Portal](#vendor-portal)
7. [Site Admin Dashboard](#site-admin-dashboard)
8. [Public-Facing Pages](#public-facing-pages)
9. [Payment Processing](#payment-processing)
10. [AI-Powered Features](#ai-powered-features)
11. [Marketing & Lead Management](#marketing--lead-management)
12. [Communication System](#communication-system)
13. [Automation & Scheduling](#automation--scheduling)
14. [Reporting & Analytics](#reporting--analytics)
15. [Document Management](#document-management)
16. [Maps & Geocoding](#maps--geocoding)
17. [Subscription & Billing](#subscription--billing)
18. [Email System](#email-system)
19. [Security & Access Control](#security--access-control)
20. [Progressive Web App (PWA)](#progressive-web-app-pwa)
21. [Data Model Summary](#data-model-summary)

---

## Platform Overview

### What Rental Gates Does

Rental Gates manages the complete property management lifecycle:

```
Property Setup → Tenant Acquisition → Lease Management → Rent Collection → Maintenance → Renewals
     |                  |                    |                  |               |            |
 Buildings &      Public listings,      Draft, activate,    Stripe online   Work orders,  Renewal
 Units with       QR codes, lead        terminate, renew    + manual cash/  vendor        offers &
 amenities,       capture, AI-scored    leases with multi-  check tracking  assignment,   auto-alerts
 photos, maps     applications          tenant support      + late fees     cost tracking
```

### Who Uses It

| Stakeholder | What They Do | Portal |
|---|---|---|
| **Property Owner/Manager** | Full control: buildings, units, tenants, leases, payments, maintenance, marketing, AI tools, billing | Owner Dashboard |
| **Staff Member** | Day-to-day operations: buildings, tenants, maintenance, messages (permission-gated) | Staff Portal |
| **Tenant** | View lease, pay rent online, submit maintenance requests, receive announcements | Tenant Portal |
| **Vendor** | View assigned work orders, update status, communicate with management | Vendor Portal |
| **Site Admin** | Platform-wide administration, user impersonation, diagnostics | Admin Dashboard |
| **Prospective Tenant** | Browse listings on public map, view building/unit details, submit applications | Public Pages |

### Key Metrics

- **55+ database tables** for complete data isolation per organization
- **98 REST API endpoints** under `rental-gates/v1/`
- **114 AJAX handlers** for dashboard interactions
- **30+ email templates** for transactional messaging
- **351 files** in the full plugin package

---

## User Roles & Portals

Rental Gates defines 7 custom WordPress roles, each with its own portal and capability set:

### Role Hierarchy

```
Site Admin ─────────────────────────── /rental-gates/admin
    │
    ├── Owner / Property Manager ───── /rental-gates/dashboard
    │       │
    │       ├── Staff ──────────────── /rental-gates/staff
    │       │
    │       ├── Tenant ─────────────── /rental-gates/tenant
    │       │
    │       └── Vendor ─────────────── /rental-gates/vendor
    │
    └── Lead (no portal access) ────── tracked in CRM only
```

| Role | Key Capabilities | Access Level |
|---|---|---|
| `rental_gates_site_admin` | Full platform control, all organizations, user impersonation | Everything |
| `rental_gates_owner` | Full organization management, billing, AI tools, Stripe Connect | Own organization |
| `rental_gates_manager` | Same as owner (property manager alias) | Own organization |
| `rental_gates_staff` | Buildings, tenants, leases, maintenance, messages (permission-gated) | Assigned scope |
| `rental_gates_tenant` | View lease, make payments, submit maintenance, view documents | Own lease/unit |
| `rental_gates_vendor` | View/manage assigned work orders, upload documents | Assigned work orders |
| `rental_gates_lead` | No portal access | CRM tracking only |

### Multi-Tenancy

All data is scoped by `organization_id`. When a user logs in, the system resolves their organization from the `rg_organization_members` table. Users only see data belonging to their organization. Staff see a further-restricted subset based on their individual permission grants.

---

## Owner Dashboard Modules

The owner dashboard (`/rental-gates/dashboard`) is the primary management interface with 20+ modules organized into logical groups.

### Dashboard Home

**URL:** `/rental-gates/dashboard`

The landing page provides a real-time overview of the entire portfolio:

- **Revenue metrics** — Monthly revenue with trend analysis, collection rate
- **Occupancy metrics** — Current occupancy rate, vacancy count
- **Open maintenance** — Active work orders requiring attention
- **Pending payments** — Payments due or overdue
- **Expiring leases** — Leases ending within 30/60/90 days
- **Revenue by building** — Chart comparing building performance
- **Recent activity** — Timeline of recent actions across the organization
- **Quick actions** — Create building, add tenant, new lease, record payment

---

### Buildings & Units

**URL:** `/rental-gates/dashboard/buildings`

Complete property portfolio management.

#### Buildings
- **List view** with building cards showing unit count, occupancy rate, and revenue
- **Add/edit buildings** with name, description, year built, total floors
- **Geocoding** — Automatic address resolution from coordinates (or reverse) via Google Maps or OpenStreetMap
- **Amenities** — Configurable amenity tags (parking, laundry, elevator, pool, gym, etc.)
- **Photo gallery** — Multiple images per building via WordPress Media Library
- **QR code generation** — Unique QR codes linking to the public building page with scan tracking
- **Building detail page** — Deep view with unit list, financial summary, maintenance history

#### Units
- **Unit management** within each building (name, type, floor, description)
- **Pricing** — Rent amount and deposit amount per unit
- **Specifications** — Bedrooms, bathrooms, living rooms, kitchens, parking spots, square footage
- **Availability state machine** — 5 states with automated transitions:
  ```
  available ──→ occupied (lease activated)
  occupied ──→ renewal_pending (lease expiring)
  renewal_pending ──→ occupied (renewed) or available (ended)
  available ──→ coming_soon (within configurable window)
  any ──→ unlisted (manually hidden)
  ```
- **Unit amenities** — Unit-specific amenity tags separate from building amenities
- **Photo gallery** — Per-unit image galleries
- **QR code generation** — Unit-specific QR codes for marketing
- **Unit detail page** — Lease history, payment history, maintenance history, documents

---

### Tenants

**URL:** `/rental-gates/dashboard/tenants`

Tenant relationship management throughout the lifecycle.

- **Tenant list** — Searchable, filterable directory of all tenants
- **Add/create tenants** — Name, email, phone, date of birth, emergency contacts
- **Contact preferences** — Email, phone, or text preferred contact method
- **Status tracking** — Active, Former, or Prospect
- **Portal invitations** — Send email invitations for tenants to access the Tenant Portal
- **User account linking** — Connect tenant records to WordPress user accounts
- **Tenant detail page** — Complete history: leases, payments, maintenance requests, documents, messages
- **Duplicate detection** — Prevents creating duplicate tenants with the same email

---

### Leases

**URL:** `/rental-gates/dashboard/leases`

Full lease lifecycle management from draft to renewal.

- **Lease list** — All leases with status filters (draft, active, ending, ended, renewed)
- **Create leases** — Associate unit, tenant(s), dates, rent amount, deposit
- **Multi-tenant leases** — Multiple tenants per lease via junction table
- **Lease terms** — Start/end dates, month-to-month option, notice period, billing frequency (monthly/weekly/biweekly), billing day
- **Status workflow:**
  ```
  Draft ──→ Active ──→ Ending ──→ Ended
                │                    │
                └── Renewed ─────────┘
  ```
- **Activation** — Triggers unit status change, welcome notifications, first payment scheduling
- **Termination** — Early termination with reason tracking, unit availability update
- **Renewals** — Create renewal leases linked to the previous lease, send renewal offers
- **Overlap detection** — Prevents creating overlapping leases for the same unit
- **Rent adjustments** — Track rent changes during the lease term with effective dates
- **Lease detail page** — Full view with payment history, documents, tenant information

---

### Payments

**URL:** `/rental-gates/dashboard/payments`

Comprehensive rent collection and financial tracking.

- **Payment list** — All payments with status filters and date range search
- **Manual payment recording** — Cash, check, money order, or external payment entry
- **Online payments** — Stripe-powered card and ACH bank transfer payments
- **Create pending charges** — Generate payment requests for specific tenants
- **Bulk rent generation** — Auto-generate monthly rent charges for all active leases on a configurable billing day
- **Payment details** — Amount, type, method, Stripe references, fee breakdown
- **Payment types** — Rent, deposit, late fee, damage, other, refund
- **Payment methods** — Stripe card, Stripe ACH, cash, check, money order, other, external
- **Payment statuses** — Pending, processing, succeeded, partially paid, failed, refunded, cancelled
- **Auto-generated payment numbers** — Format: `PAY23XXXXX`
- **Pro-rated payments** — Automatic proration calculation for partial-month leases
- **Refund processing** — Full or partial refunds through Stripe
- **Fee tracking** — Platform fee (2.5%), Stripe processing fee, and net amount calculations
- **Receipt/invoice generation** — PDF receipts and invoices with line items
- **Overdue tracking** — Automatic overdue detection with escalating notifications
- **Payment plans** — Installment payment plans with scheduled items

---

### Maintenance

**URL:** `/rental-gates/dashboard/maintenance`

Facilities management and work order tracking.

- **Work order list** — All maintenance requests with priority and status filters
- **Create work orders** — Title, description, building, unit, tenant, category, priority
- **Categories** — Plumbing, electrical, HVAC, appliance, structural, pest, cleaning, general, other
- **Priority levels** — Emergency, high, medium, low (with visual indicators)
- **Status workflow:**
  ```
  Open ──→ Assigned ──→ In Progress ──→ Completed
    │                                       │
    └── Cancelled ──────────────────────────┘
  ```
- **Vendor assignment** — Assign one or more vendors to a work order
- **Note threads** — Internal and external notes with timestamps and author tracking
- **Cost tracking** — Estimated and actual costs per work order
- **Permission to enter** — Tenant-granted entry permission flag
- **Access instructions** — Special access notes for vendors
- **Photo attachments** — Before/after photos on work orders
- **Scheduled maintenance** — Recurring maintenance schedules (preventive maintenance)
- **Work order detail page** — Full timeline of status changes, notes, vendor assignments, costs
- **Post-completion survey** — Automatic satisfaction survey sent to tenants after completion

---

### Vendors

**URL:** `/rental-gates/dashboard/vendors`

Third-party service provider management.

- **Vendor list** — Searchable directory of all vendors
- **Add/edit vendors** — Company name, contact person, email, phone, hourly rate
- **Service categories** — 13 categories: plumbing, electrical, HVAC, appliance repair, carpentry, painting, landscaping, cleaning, pest control, roofing, flooring, security, general
- **Building access** — Assign vendors to specific buildings they can service
- **Vendor status** — Active, paused, inactive
- **Portal invitations** — Email invitations for vendors to access the Vendor Portal
- **Stripe Connect** — Vendor payout setup with onboarding status tracking (not connected, pending, ready)
- **Work order history** — Full history of all work orders assigned to each vendor
- **Vendor detail page** — Contact info, service capabilities, work order history, payout status

---

### Staff Management

**URL:** `/rental-gates/dashboard/staff`

Team administration with granular permission control.

- **Staff list** — All staff members with roles and status
- **Add/invite staff** — Send email invitations to join the organization
- **Permission system** — Granular control over what each staff member can access:
  - Buildings & units (view/edit)
  - Tenants (view/edit)
  - Leases (view/edit)
  - Payments (view/edit)
  - Maintenance (view/edit)
  - Messages (view/send)
  - Reports (view)
- **Staff detail page** — Contact info, permissions, activity log

---

### Applications

**URL:** `/rental-gates/dashboard/applications`

Rental application processing and screening.

- **Application list** — All submitted applications with status filters
- **Application review** — Detailed view of applicant information
- **Status workflow:**
  ```
  Invited ──→ New (submitted) ──→ Screening ──→ Approved ──→ Lease Created
                                      │
                                      └──→ Declined
                                      └──→ Withdrawn
  ```
- **Applicant details** — Name, email, phone, current address, employer, income range, desired move-in date
- **Additional occupants** — Track all occupants on the application
- **Token-based access** — Unique 32-character tokens for applicant self-service
- **Lead conversion** — Applications link back to leads for funnel tracking
- **AI screening** — Optional AI-assisted application review and scoring (costs 3 AI credits)
- **Approve/decline actions** — One-click actions with automatic email notifications

---

### Messages

**URL:** `/rental-gates/dashboard/messages`

Direct messaging between all portal users.

- **Message threads** — Threaded conversations with any user in the organization
- **Participants** — Owner ↔ tenant, owner ↔ staff, owner ↔ vendor, and work-order-specific threads
- **Unread counts** — Badge indicators on navigation
- **Real-time updates** — Message notification system
- **Work order context** — Messages can be linked to specific work orders for contextual communication

---

### Notifications

**URL:** `/rental-gates/dashboard/notifications`

In-app notification center with 25+ notification types.

- **Notification feed** — Chronological list of all notifications
- **Unread badge** — Navigation badge showing unread count
- **Notification types:**
  - Lease events (created, activated, expiring, terminated, renewed)
  - Payment events (received, overdue, failed, refunded)
  - Maintenance events (new request, assigned, updated, completed)
  - Application events (submitted, approved, declined)
  - Message received
  - Announcement posted
  - System alerts
- **Action URLs** — Each notification links to the relevant detail page
- **Notification preferences** — Per-user control over which notifications to receive via email vs. in-app
- **Auto-cleanup** — Notifications older than 90 days are automatically purged

---

### Settings

**URL:** `/rental-gates/dashboard/settings`

Organization-level configuration.

- **General settings** — Organization name, logo, contact information, timezone, currency
- **Address & location** — Organization address with geocoding
- **Social links** — Website, Facebook, Instagram, Twitter, LinkedIn
- **Branding** — Logo, colors, custom branding options
- **Map provider** — Choose between Google Maps (requires API key) or OpenStreetMap (free)
- **Late fee configuration** — Grace period days, fee type (fixed or percentage), fee amount
- **Payment settings** — Allow partial payments, default billing day
- **Lease settings** — Coming-soon window days, renewal notice days, move-in notice days
- **Notification preferences** — Email notification toggles per event type
- **Stripe Connect setup** — Connect property owner's Stripe account for receiving rent payments
- **API keys** — Manage Google Maps API key, AI provider API keys

---

## Tenant Portal

**URL:** `/rental-gates/tenant`

A dedicated self-service portal for tenants. Uses the `tp-*` CSS class prefix.

### Tenant Dashboard Home
- Current lease summary (unit, building, rent amount, lease dates)
- Upcoming payment due date and amount
- Open maintenance requests
- Recent announcements

### My Lease
- View active lease details (dates, terms, rent amount)
- Lease document downloads
- Renewal status
- Lease history

### Payments
- **Payment history** — All past payments with receipts
- **Make a payment** — Online rent payment via Stripe (card or ACH)
- **Upcoming charges** — View pending/future charges
- **Payment receipts** — Download PDF receipts
- **Auto-pay** — Saved payment method management

### Maintenance
- **Submit requests** — Create new maintenance requests with description, category, priority, and photos
- **Track requests** — View status and updates on submitted requests
- **Permission to enter** — Grant or revoke entry permission
- **Communication** — Add notes to active work orders

### Documents
- View and download shared documents (lease agreements, receipts, notices)
- Upload requested documents

### Messages
- Direct messaging with property management
- Message history and threads

### Notifications
- In-app notification feed
- Announcement viewing

### Settings
- Personal profile updates
- Password change
- Contact preferences
- Notification preferences

---

## Staff Portal

**URL:** `/rental-gates/staff`

An operations-focused portal for staff members. Uses the `rg-*` CSS class prefix. Feature access is controlled by per-staff permission grants.

### Staff Dashboard Home
- Assigned task overview
- Recent activity relevant to permissions
- Quick action buttons

### Available Modules (Permission-Dependent)
- **Buildings** — View and manage properties (if buildings permission granted)
- **Tenants** — Manage tenant relationships (if tenants permission granted)
- **Leases** — View and edit leases (if leases permission granted)
- **Payments** — View and record payments (if payments permission granted)
- **Maintenance** — Manage work orders (if maintenance permission granted)
- **Messages** — Communicate with tenants and vendors
- **Notifications** — Activity feed

### Settings
- Personal profile management
- Password change
- Notification preferences

---

## Vendor Portal

**URL:** `/rental-gates/vendor`

A focused portal for third-party service providers. Uses the `vp-*` CSS class prefix.

### Vendor Dashboard Home
- Assigned work order count
- Pending vs. completed work orders
- Recent activity

### Work Orders
- **View assigned work orders** — See all work orders assigned to the vendor
- **Work order details** — Building, unit, description, priority, photos
- **Status updates** — Mark work orders as in progress or completed
- **Add notes** — Post updates and communication on work orders
- **Upload photos** — Before/after documentation

### Documents
- Upload invoices and completion documentation
- View shared documents

### Messages
- Direct messaging with property management
- Work-order-specific message threads

### Notifications
- Work order assignment alerts
- Status change notifications

### Settings
- Company profile management
- Contact information updates
- Stripe payout configuration

---

## Site Admin Dashboard

**URL:** `/rental-gates/admin`

Platform-level administration for site administrators.

### Organizations Management
- View all organizations on the platform
- Organization details and statistics
- Subscription status monitoring

### User Management
- **User impersonation** — Search and impersonate any non-admin user for support purposes
- Impersonation activity logging for audit trail

### Support Tools
- **Cache management** — Clear application caches
- **Role repair** — Reset WordPress roles and capabilities
- **Database upgrades** — Run pending database migrations
- **Debug export** — Export diagnostic information for troubleshooting

### AI Credit Management
- View organization credit balances
- Manual credit adjustments (add/deduct)
- Credit transaction history

### Platform Statistics
- Total organizations, users, buildings, units
- Revenue metrics across the platform
- Active subscription counts

---

## Public-Facing Pages

No authentication required. These pages drive tenant acquisition and public presence.

### Interactive Property Map

**URL:** `/rental-gates/`

- Full-screen interactive map showing all buildings with available units
- Provider-agnostic: Google Maps or OpenStreetMap/Leaflet based on configuration
- Building markers with popup previews (name, photo, unit count, rent range)
- Click-through to building detail pages
- Responsive design for mobile browsing

### Building Detail Page

**URL:** `/rental-gates/b/{slug}`

- Building photos and gallery
- Description and amenities
- Available units grid with rent amounts and specifications
- Location map
- "I'm Interested" lead capture form
- QR code scan tracking (records source for marketing analytics)

### Unit Listing Page

**URL:** `/rental-gates/listings/{slug}`

- Unit photos and gallery
- Rent amount and deposit
- Specifications (bedrooms, bathrooms, square footage, parking)
- Unit-specific amenities
- Building amenities (inherited)
- Availability status
- "Apply Now" button (links to application form)
- Lead capture integration

### Rental Application

**URL:** `/rental-gates/apply`

- Multi-step application form
- Applicant personal information
- Employment and income details
- Additional occupant information
- Unit preference selection
- Token-based access for returning to in-progress applications

### Organization Profile

**URL:** `/rental-gates/profile`

- Company information and branding
- Portfolio overview
- Contact information

### Marketing & Informational Pages

| Page | URL | Purpose |
|---|---|---|
| Pricing | `/rental-gates/pricing` | Subscription plan comparison |
| About | `/rental-gates/about` | Platform information |
| Contact | `/rental-gates/contact` | Contact form |
| FAQ | `/rental-gates/faq` | Frequently asked questions |
| Privacy Policy | `/rental-gates/privacy` | Privacy policy |
| Terms of Service | `/rental-gates/terms` | Terms and conditions |

### Authentication Pages

| Page | URL | Purpose |
|---|---|---|
| Login | `/rental-gates/login` | Email/password login with magic link option |
| Register | `/rental-gates/register` | Owner registration with auto-org creation |
| Reset Password | `/rental-gates/reset-password` | Password recovery |
| Checkout | `/rental-gates/checkout` | Subscription payment after registration |

---

## Payment Processing

Rental Gates implements a **dual Stripe integration** handling two distinct payment flows.

### Flow 1: SaaS Billing (Platform <-- Owner)

Property owners pay for their Rental Gates subscription.

```
Owner → Stripe Checkout → Platform receives payment
                        → Subscription activated/renewed
                        → Features unlocked based on plan tier
```

- **Plan management** — Free, Starter, Professional, Enterprise tiers
- **Monthly and yearly billing** — Configurable per plan
- **Saved payment methods** — Stripe setup intents for card-on-file
- **Subscription lifecycle** — Create, upgrade, downgrade, cancel, reactivate
- **Invoice history** — All platform invoices with PDF download
- **Webhook processing** — Real-time subscription status updates

### Flow 2: Rent Collection (Tenant --> Owner)

Tenants pay rent to property owners via Stripe Connect.

```
Tenant → Stripe Checkout (embedded or hosted)
       → Payment goes to owner's connected Stripe account
       → Platform takes 2.5% application fee
       → Payment recorded in rg_payments table
       → Receipt email sent to tenant
```

- **Stripe Connect onboarding** — Guided setup for property owners to connect their Stripe accounts
- **Embedded checkout** — In-page payment experience without redirect
- **Hosted checkout** — Redirect-based fallback for broader compatibility
- **Payment methods** — Credit/debit cards and ACH bank transfers
- **Application fees** — Automatic 2.5% platform fee on each transaction
- **Refund processing** — Full or partial refunds through owner's connected account
- **Customer management** — Saved payment methods for returning tenants

### Automated Financial Features

- **Bulk rent generation** — Auto-create monthly charges for all active leases
- **Payment reminders** — Configurable reminders X days before due date
- **Overdue detection** — Automatic past-due flagging with escalating notifications
- **Late fee automation** — Auto-apply late fees after configurable grace period (fixed amount or percentage)
- **Pro-rated calculations** — Automatic proration for partial-month leases
- **Invoice/receipt PDF** — Server-side PDF generation with line items, property info, and branding

---

## AI-Powered Features

**URL:** `/rental-gates/dashboard/ai-tools`

Dual-provider AI integration with credit-based usage tracking.

### AI Providers

| Provider | Model | Role |
|---|---|---|
| OpenAI | GPT-4o-mini | Primary provider |
| Google Gemini | Gemini 1.5 Flash | Alternative provider |

### AI Capabilities

| Feature | Credits | Description |
|---|---|---|
| **Property Descriptions** | 1 | Generate compelling listing descriptions for buildings and units |
| **Marketing Copy** | 1 | Create marketing content for campaigns and flyers |
| **Maintenance Triage** | 1 | AI-assisted categorization and priority assessment for work orders |
| **Message Drafting** | 1 | Suggested replies for tenant/vendor communications |
| **Insights Generation** | 2 | AI-powered analysis of portfolio data and trends |
| **Tenant Screening** | 3 | AI-assisted application review with scoring and recommendations |

### Credit System

- **Subscription allocation** — Each plan tier includes monthly AI credits
- **Credit packs** — Purchase additional credits via Stripe
- **Balance types** — Subscription credits, purchased credits, bonus credits
- **Daily resets** — Subscription credits refresh via daily cron job
- **Usage tracking** — Per-call tracking with provider, model, tokens used, and cost
- **Admin management** — Site admins can manually adjust credit balances

---

## Marketing & Lead Management

### Lead CRM

**URL:** `/rental-gates/dashboard/leads`

Full lead pipeline management for prospective tenants.

- **Lead capture** — Automatic lead creation from:
  - QR code scans (building or unit codes)
  - Public map interactions
  - Building page "I'm Interested" forms
  - Unit listing inquiries
  - Manual entry
  - Referrals
- **Lead stages** — Pipeline tracking:
  ```
  New → Contacted → Touring → Applied → Won (lease signed)
                                  │
                                  └→ Lost
  ```
- **Lead scoring** — AI-powered scoring based on:
  - Source quality (5-25 points)
  - Engagement level (5-60 points) — email opens, clicks, property views
  - Property interest (10-20 points) — number and type of properties viewed
  - Demographics (5-10 points)
  - Recency (10-15 points) — how recently the lead engaged
- **Interest tracking** — Which buildings and units each lead is interested in
- **Follow-up dates** — Scheduled follow-up reminders
- **Staff assignment** — Assign leads to specific staff members
- **Auto-deduplication** — Prevents duplicate leads with the same email
- **Lead detail page** — Full interaction history, scoring breakdown, interest map

### QR Code System

Generate and track QR codes for offline-to-online marketing.

- **QR code types** — Building, unit, organization, custom URL
- **Sizes** — Small (150px), medium (300px), large (500px), print (1000px)
- **Scan tracking** — Every scan recorded with timestamp, source, and device info
- **Lead attribution** — Scans automatically create leads tagged with the QR source
- **Bulk generation** — Generate QR codes for all buildings or units at once
- **Download** — PNG download for print materials

### Marketing Campaigns

**URL:** `/rental-gates/dashboard/marketing`

- **Campaign types** — QR, flyer, email, social media, multi-channel
- **Campaign management** — Name, type, status, dates, budget, goal tracking
- **Status workflow** — Draft, active, paused, completed, cancelled
- **Budget tracking** — Campaign spending against allocated budget

### Flyer Generation

- **4 templates** — Modern, classic, minimal, bold
- **Template types** — Building flyers and unit flyers
- **Auto-populated content** — Property details, photos, QR codes pulled automatically
- **PDF generation** — Server-side PDF rendering for print
- **QR code integration** — Each flyer embeds a trackable QR code

### Marketing Analytics

**URL:** `/rental-gates/dashboard/marketing-analytics`

- **Conversion funnel** — QR scans → leads → applications → leases
- **Leads by source** — Chart showing which acquisition channels perform best
- **Leads by stage** — Pipeline distribution visualization
- **Daily trends** — Lead generation over time
- **Period filtering** — Day, week, month, quarter, year views
- **ROI tracking** — Campaign cost vs. conversion value

### Marketing Automation

- **Automated follow-ups** — Configurable drip sequences for new leads
- **Lead nurture campaigns** — Scheduled email sequences based on lead stage
- **Trigger-based rules** — "When lead reaches X stage, do Y action"
- **Conversion tracking** — Full attribution from first touch to signed lease

---

## Communication System

### Direct Messaging

- **Thread-based** — Persistent conversation threads between any two users
- **Participant types** — Owner/manager, staff, tenant, vendor
- **Work order context** — Messages can be scoped to a specific work order
- **Unread tracking** — Per-user unread counts with navigation badges
- **Cross-portal** — Messages visible in the sender's and recipient's respective portals

### Announcements

- **Broadcast messaging** — Send announcements to groups of users
- **Audience targeting:**
  - All users in the organization
  - Specific buildings
  - Specific units
  - Tenants only
  - Staff only
  - Vendors only
- **Priority levels** — Low, normal, high, urgent
- **Delivery options** — Immediate or scheduled
- **Channels** — In-app only, email only, or both
- **Delivery tracking** — Track which recipients have viewed the announcement

### Notifications

- **25+ notification types** covering all system events
- **In-app feed** — Chronological notification center in every portal
- **Email notifications** — Configurable per notification type per user
- **Preference management** — Users control which notifications they receive and how
- **Auto-cleanup** — 90-day retention policy

---

## Automation & Scheduling

### Daily Automated Tasks (8:00 AM)

| Task | Description |
|---|---|
| **Auto-generate rent charges** | Create pending payment records for all active leases on the configured billing day |
| **Payment reminders** | Send email reminders X days before rent is due (configurable, default 3 days) |
| **Overdue alerts** | Send escalating overdue notices at 1, 3, 7, 14, and 30 days past due |
| **Late fee generation** | Auto-apply late fees after the grace period (fixed amount or percentage of rent) |
| **Lease expiration alerts** | Send alerts at 90, 60, 30, 14, and 7 days before lease expiration |
| **Move-in/move-out reminders** | Notify tenants and staff of upcoming move dates |
| **AI credit resets** | Refresh monthly subscription credit allocations |
| **Notification cleanup** | Purge notifications older than 90 days |

### Hourly Automated Tasks

| Task | Description |
|---|---|
| **Email queue processing** | Process queued emails for batch delivery |
| **Digest compilation** | Compile notification digests |
| **Subscription checks** | Check for expired subscriptions and enforce limits |
| **Unit availability updates** | Auto-update unit availability states based on lease dates |
| **Temp file cleanup** | Clean up temporary files (weekly cadence) |

### Event-Driven Automation

| Trigger Event | Automated Actions |
|---|---|
| **Lease activated** | Update unit to occupied, send tenant welcome email, schedule first payment |
| **Lease terminated** | Update unit to available, schedule move-out reminder |
| **Lease expiring** | Send renewal offer to tenant, notify owner |
| **Payment received** | Send receipt email, update lease balance |
| **Payment failed** | Notify tenant, retry logic |
| **Payment overdue** | Calculate late fee, send escalation emails |
| **Maintenance created** | Notify owner/manager, auto-assign vendor if rules configured |
| **Maintenance updated** | Notify all stakeholders (tenant, vendor, owner) |
| **Maintenance completed** | Send satisfaction survey to tenant |
| **Application submitted** | Notify owner, run AI screening if enabled, update lead stage |

---

## Reporting & Analytics

**URL:** `/rental-gates/dashboard/reports`

### Financial Reports

- **Revenue collected** — Total rent and other payments received
- **Total billed** — All charges generated
- **Collection rate** — Percentage of billed amount actually collected
- **Overdue amounts** — Outstanding past-due balances
- **Revenue trend** — 12-month revenue chart
- **Revenue by type** — Breakdown by rent, deposit, late fee, damage, other
- **Revenue by building** — Per-property financial performance comparison
- **Export options** — CSV and PDF export

### Occupancy Reports

- **Occupancy rate** — Percentage of units currently occupied
- **Vacancy rate** — Percentage of units available
- **New leases** — Leases activated in the reporting period
- **Average days vacant** — Mean vacancy duration
- **Units by status** — Distribution across available/occupied/coming_soon/unlisted
- **Occupancy by building** — Per-property occupancy comparison
- **Expiring leases** — Leases ending in the next 90 days

### Maintenance Reports

- **Total work orders** — Count in reporting period
- **Open work orders** — Currently unresolved
- **Completion rate** — Percentage of work orders completed
- **Average response time** — Time from creation to first assignment
- **Average resolution time** — Time from creation to completion
- **Maintenance costs** — Total and per-work-order cost analysis
- **Work orders by category** — Distribution across plumbing, electrical, HVAC, etc.
- **Work orders by status** — Pipeline view of work order states
- **Work orders by building** — Per-property maintenance burden
- **Emergency tracking** — Count and response time for emergency requests

---

## Document Management

**URL:** `/rental-gates/dashboard/documents`

Centralized document storage and organization.

### Document Types (20+)

| Category | Types |
|---|---|
| **Leasing** | Lease agreement, lease amendment, lease addendum, renewal notice |
| **Financial** | Receipt, invoice, payment plan, deposit record |
| **Tenant** | Tenant ID, proof of income, references, insurance, pet agreement |
| **Property** | Inspection report, condition report, floor plan, photo |
| **Legal** | Eviction notice, legal correspondence, court filing |
| **Maintenance** | Work order documentation, vendor invoice, completion photos |
| **Other** | General, custom |

### Features

- **Entity association** — Attach documents to buildings, units, tenants, leases, applications, work orders, or vendors
- **Privacy controls** — Documents can be marked as private (owner/staff only) or shared (visible to associated users)
- **WordPress Media Library integration** — Uses WordPress's built-in file handling for uploads
- **File preview** — In-browser preview for images and PDFs
- **Download** — Direct file downloads
- **Storage analytics** — Track storage usage per organization
- **Search and filter** — Find documents by type, entity, date, or keyword

---

## Maps & Geocoding

Provider-agnostic mapping system for property visualization and address management.

### Supported Providers

| Provider | API Key Required | Map Library | Geocoding |
|---|---|---|---|
| **Google Maps** | Yes | Google Maps JS API | Google Geocoding API |
| **OpenStreetMap** | No | Leaflet.js | Nominatim |

### Features

- **Forward geocoding** — Convert addresses to latitude/longitude coordinates
- **Reverse geocoding** — Convert coordinates to human-readable addresses
- **Interactive map** — Public property map with building markers and popups
- **Building location** — Automatic geocoding when saving building coordinates
- **Per-organization configuration** — Each organization chooses their preferred map provider
- **Fallback support** — Graceful degradation if map API is unavailable

---

## Subscription & Billing

**URL:** `/rental-gates/dashboard/billing`

### Plan Tiers

4 subscription tiers with progressive feature unlocking:

| Feature | Free | Starter | Professional | Enterprise |
|---|---|---|---|---|
| Buildings | Limited | More | More | Unlimited |
| Units | Limited | More | More | Unlimited |
| Staff | Limited | More | More | Unlimited |
| Tenants | Limited | More | More | Unlimited |
| Tenant Portal | -- | Yes | Yes | Yes |
| Online Payments | -- | Yes | Yes | Yes |
| Maintenance Tracking | -- | Yes | Yes | Yes |
| Lease Management | -- | Yes | Yes | Yes |
| AI Screening | -- | -- | Yes | Yes |
| Marketing & QR | -- | -- | Yes | Yes |
| Vendor Management | -- | -- | Yes | Yes |
| Messaging | -- | -- | Yes | Yes |
| API Access | -- | -- | -- | Yes |
| Advanced Reports | -- | -- | -- | Yes |
| Bulk Operations | -- | -- | -- | Yes |
| White Label | -- | -- | -- | Yes |
| AI Credits/month | Small | Medium | Large | Maximum |

### 12 Feature Modules (Gated by Plan)

1. **Tenant Portal** — Self-service tenant access
2. **Online Payments** — Stripe payment processing
3. **Maintenance Tracking** — Work order system
4. **Lease Management** — Full lease lifecycle
5. **AI Tenant Screening** — AI-powered application review
6. **Marketing & QR Codes** — Lead generation tools
7. **Vendor Management** — Third-party provider coordination
8. **Chat & Messaging** — Direct messaging system
9. **API Access** — REST API for integrations
10. **Advanced Reports** — Enhanced reporting and analytics
11. **Bulk Operations** — Mass actions on buildings, units, leases
12. **White Label** — Custom branding and domain

### Resource Limits

Each plan enforces hard limits on:
- Number of buildings
- Number of units
- Number of staff members
- Number of vendors
- Number of tenants
- Storage (GB)

Pre-action limit checks prevent exceeding plan limits with upgrade prompts.

### Billing Features

- **Plan selection** — Compare and choose plans
- **Payment methods** — Add/remove credit cards
- **Subscription management** — Upgrade, downgrade, cancel
- **Invoice history** — View and download past invoices as PDF
- **Stripe integration** — Secure payment processing via Stripe Checkout

---

## Email System

30+ transactional email templates with theme override support.

### Template Hierarchy

```
1. Theme override:  {theme}/rental-gates/emails/{template}.php
2. Plugin template: templates/emails/{template}.php
3. Generic fallback: templates/emails/generic.php
```

### Email Categories

| Category | Templates | Description |
|---|---|---|
| **Account** | welcome, password_reset, magic_link, tenant_invitation, staff_invitation, vendor_invitation | User lifecycle emails |
| **Applications** | application_received, application_approved, application_declined | Application flow notifications |
| **Leases** | lease_created, lease_signed, lease_ending, renewal_offer | Lease lifecycle alerts |
| **Payments** | payment_receipt, payment_reminder, payment_overdue, payment_failed | Financial notifications |
| **Maintenance** | maintenance_created, maintenance_assigned, maintenance_update, maintenance_completed, maintenance_survey | Work order lifecycle |
| **Vendors** | vendor_invitation, vendor_assignment, vendor_reminder | Vendor-specific notifications |
| **Communication** | message_received, announcement | Messaging notifications |
| **Marketing** | lead_inquiry, lead_followup | Lead nurture emails |
| **System** | subscription_confirmed, generic | Platform and custom emails |

### Email Features

- **HTML templates** — Styled, branded email templates
- **WordPress wp_mail()** — Delegates to server SMTP configuration
- **Queue processing** — Batch email sending via hourly cron
- **Preference-based delivery** — Respects user notification preferences
- **Dynamic content** — Templates populated with recipient, property, and transaction data

---

## Security & Access Control

### Authentication

- **Email/password login** — Standard WordPress authentication
- **Magic link login** — Passwordless login via emailed one-time tokens (stored in `rg_magic_links` table)
- **Rate limiting** — 5 login attempts per 5 minutes per IP
- **Registration limits** — 3 registrations per hour per IP
- **Role-based redirect** — After login, users are redirected to their role-specific portal
- **Session management** — WordPress cookie-based sessions with configurable "remember me"

### Authorization

- **Route-level** — `check_role()` enforced before every template load
- **API-level** — `current_user_can()` checks on every REST endpoint
- **Template-level** — Conditional rendering based on user capabilities
- **Data-level** — All queries scoped by `organization_id` for multi-tenant isolation
- **Staff permissions** — Granular per-staff permission grants via `rg_staff_permissions` table

### Input Protection

- **CSRF** — WordPress nonces on all forms and API calls
- **XSS** — `esc_html()`, `esc_attr()`, `esc_url()` output escaping; `json_for_script()` for safe JSON embedding
- **SQL injection** — `$wpdb->prepare()` for all database queries
- **Input sanitization** — `sanitize_text_field()`, `sanitize_email()`, `sanitize_phone()` on all inputs

### Rate Limiting

| Endpoint Type | Limit | Window |
|---|---|---|
| Login | 5 requests | 5 minutes |
| Registration | 3 requests | 1 hour |
| Password Reset | 3 requests | 2 minutes |
| General API | 100 requests | 1 minute |
| Search | 30 requests | 1 minute |
| Upload | 20 requests | 1 minute |

### Encryption

- **API key storage** — Stripe keys and other secrets encrypted at rest with `openssl_encrypt()`
- **Auto-migration** — Plaintext keys automatically encrypted on first access

### Caching

- **WordPress Transients** — Application-level caching with group-based keys
- **Groups** — `rg_org`, `rg_bld`, `rg_unit`, `rg_stats`, etc.
- **Version-aware invalidation** — Cache entries versioned for controlled expiry
- **Default TTL** — 1 hour

---

## Progressive Web App (PWA)

- **Service worker** — Offline support with asset caching
- **Web app manifest** — Installable on mobile devices
- **PWA registration** — Automatic service worker registration on all pages
- **Offline fallback** — Cached pages served when network is unavailable

---

## Data Model Summary

### Entity Counts

| Category | Count |
|---|---|
| **Database tables** | 55+ |
| **Model classes** | 24 |
| **REST API endpoints** | 98 |
| **AJAX handlers** | 114 |
| **Email templates** | 30+ |
| **Dashboard sections** | 35+ (owner), 12 (admin), 11 (staff), 6+ (tenant), 4+ (vendor) |
| **Notification types** | 25+ |
| **Document types** | 20+ |
| **Cron jobs** | 7+ |

### Entity Relationship Overview

```
Organization
├── Buildings
│   ├── Units
│   │   ├── Leases
│   │   │   ├── Tenants (many-to-many via lease_tenants)
│   │   │   ├── Payments
│   │   │   └── Documents
│   │   ├── Applications
│   │   └── QR Codes
│   ├── Work Orders
│   │   ├── Vendors (many-to-many via work_order_vendors)
│   │   ├── Notes
│   │   └── Messages
│   └── Documents
├── Leads
│   ├── Lead Interests (buildings/units)
│   ├── Lead Scores
│   └── Applications
├── Campaigns
│   ├── Flyers
│   ├── QR Codes
│   └── Conversions
├── Messages & Threads
├── Announcements
├── Notifications
├── Subscriptions & Invoices
├── AI Usage & Credits
├── Settings
├── Activity Log
└── Members (users with roles)
```

---

*For technical architecture details including code patterns, file structure, and implementation specifics, see [TECH_STACK.md](./TECH_STACK.md).*
