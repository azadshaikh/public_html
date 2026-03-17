# Modular SaaS Architecture (Odoo-Style)

A comprehensive architecture guide for building an Odoo-style modular SaaS platform where each module can operate independently as a standalone product or integrate with others to form a complete business suite.

---

## 2026 Architecture Update (Current Direction)

This section is the authoritative update for the current product rollout.

### Domain Topology

| Domain                   | Responsibility                                                     | Active Modules                                                |
| ------------------------ | ------------------------------------------------------------------ | ------------------------------------------------------------- |
| `platform.astero.net.in` | Centralized provisioning control plane — serves ALL agencies       | `Platform`                                                    |
| `pkg.astero.net.in`      | Release registry and release API for application/module updates    | `ReleaseManager`                                              |
| `my.astero.in`           | Astero's own agency: customer onboarding, billing, support         | `Agency`, `Customers`, `Billing`, `Subscriptions`, `Helpdesk` |
| `herofy.in`              | Agency deployment: Herofy brand (same codebase, own instance)      | `Agency`, `Customers`, `Billing`, `Subscriptions`, `Helpdesk` |
| `breederspot.com`        | Agency deployment: BreederSpot brand (same codebase, own instance) | `Agency`, `Customers`, `Billing`, `Subscriptions`, `Helpdesk` |
| `astero.in`              | Landing page + blog + marketing content                            | `CMS`                                                         |

> **Multi-Agency Model:** Every agency deployment is an independent Astero instance with its own database, branding, and customers. All agency instances communicate with the **same** `platform.astero.net.in` Platform API using their unique `AGENCY_SECRET_KEY`. The Platform tracks each agency as a `platform_agencies` record and scopes provisioned websites to the calling agency.

### Customer Journey (Multi-Agency)

The journey is identical regardless of which agency domain the customer signs up on:

```
┌──────────────┐     ┌──────────────────┐     ┌─────────────────────────┐
│  astero.in   │────►│  my.astero.in    │────►│  platform.astero.net.in  │
│  (marketing) │     │  herofy.in       │     │  (Platform API)         │
│              │     │  breederspot.com │     │                         │
└──────────────┘     └──────────────────┘     └─────────────────────────┘
     CMS                  Agency                     Platform
```

1. Visitor lands on agency marketing site (or `astero.in` → redirects to agency).
2. Visitor signs up on the agency domain (e.g., `my.astero.in`, `herofy.in`).
3. Customer completes onboarding wizard: account → plan → website details → checkout.
4. Agency backend calls **Platform Provisioning API** (`POST /api/platform/v1/websites`) on `platform.astero.net.in` with `X-Agency-Key` header.
5. Platform validates the agency key, creates the website record scoped to the calling agency, and dispatches the `WebsiteProvision` job.
6. Platform sends a **webhook callback** to the agency's configured webhook URL when provisioning completes (or fails).
7. Agency updates the customer's website status in its local database.
8. Provisioned websites pull releases from `pkg.astero.net.in` using Release API.

### Agency ↔ Platform Communication

```
 herofy.in  ──────────┐
 my.astero.in ─────────┼──► platform.astero.net.in
 breederspot.com ──────┘         │
                           X-Agency-Key header
                           (resolves to platform_agencies record)
```

- **Auth:** Each agency has a `secret_key` (encrypted) in `platform_agencies`. The plain key is injected into the agency's `.env` as `AGENCY_SECRET_KEY` during provisioning. The Platform API middleware validates the header and resolves the agency.
- **Scoping:** All API responses are automatically scoped to the authenticated agency — an agency can only see/manage its own websites.
- **Webhooks:** Platform posts status updates to the agency's `webhook_url` (dedicated column on `platform_agencies`, not metadata). Webhooks fire on all lifecycle events: provisioning success/failure, status changes, trash, and restore. Signed with HMAC-SHA256 using the agency's secret key.
- **No direct model coupling:** Agency module does NOT import any `Modules\Platform\*` classes. Communication is HTTP-only via `PlatformApiClient` service. Verified by architecture test.

### Module Profiles Per Deployment

Each deployment should maintain its own `modules_statuses.json` profile:

```json
// platform.astero.net.in
{
    "Platform": true,
    "ReleaseManager": false,
    "Agency": false,
    "Customers": false,
    "Billing": false,
    "Subscriptions": false,
    "Helpdesk": false,
    "CMS": false
}
```

```json
// pkg.astero.net.in
{
    "Platform": false,
    "ReleaseManager": true,
    "Agency": false,
    "Customers": false,
    "Billing": false,
    "Subscriptions": false,
    "Helpdesk": false,
    "CMS": false
}
```

```json
// my.astero.in
{
    "Platform": false,
    "ReleaseManager": false,
    "Agency": true,
    "Customers": true,
    "Billing": true,
    "Subscriptions": true,
    "Helpdesk": true,
    "CMS": false
}
```

```json
// astero.in
{
    "Platform": false,
    "ReleaseManager": false,
    "Agency": false,
    "Customers": false,
    "Billing": false,
    "Subscriptions": false,
    "Helpdesk": false,
    "CMS": true
}
```

### Current Implementation Status (from codebase)

- Release API is implemented at `GET /api/release-manager/v1/releases/latest-update/{type}/{packageIdentifier}`.
- Release API is protected by `release.api.key` middleware (`X-Release-Key`).
- Platform provisioning jobs and release sync jobs are implemented.
- ✅ **Platform Provisioning API** — Fully implemented. `AgencyApiKeyMiddleware` authenticates agencies via `X-Agency-Key` header. `WebsiteApiController` provides CRUD + status + restore endpoints under `/api/platform/v1/`. V1 `FormRequest` classes handle validation. See PRD: `docs/prd/platform-agency-provisioning-api-prd.md`.
- ✅ **Webhook Dispatch** — `SendAgencyWebhook` job dispatches HMAC-signed webhooks to `$agency->webhook_url` (dedicated column on `platform_agencies`) for all website lifecycle events: `website.provisioned`, `website.provision_failed`, `website.status_changed`, `website.deleted`, `website.restored`. Centralized via `SendAgencyWebhook::dispatchForWebsite()` static helper, called from `WebsiteProvision`, `WebsiteLifecycleService`, and `WebsiteService` (bulk actions).
- ✅ **Agency `PlatformApiClient`** — HTTP client service in Agency module. Reads `AGENCY_SECRET_KEY` + `PLATFORM_API_URL` from `config/agency.php`. Handles create, list, show, status change, and delete operations. SSL verification bypassed in local env.
- ✅ **Agency Webhook Receiver** — `WebhookController` at `POST /api/agency/v1/webhooks/platform` verifies HMAC signatures and updates local `agency_websites` records via `AgencyWebsite::syncFromWebhook()`.
- ✅ **Agency Decoupled from Platform** — Zero `Modules\Platform\*` imports. `Platform` removed from Agency `module.json` dependencies. Verified by architecture test.
- ✅ **Onboarding Wired to API** — `OnboardingController::processPayment()` calls `PlatformApiClient::createWebsite()`. Supports multi-website creation (onboarding session resets for new flows).
- `Platform\Models\Agency` has `secret_key` (encrypted) + `generateSecretKey()` + `plain_secret_key` accessor + `webhook_url` (dedicated column). The secret key is written to agency `.env` as `AGENCY_SECRET_KEY` during HestiaCP provisioning.
- 24 Pest tests (49 assertions) covering: `PlatformApiClientTest` (8), `WebhookControllerTest` (6), `AgencyWebsiteModelTest` (5), and others.
- `app/Helpers/modules.php` with `module_enabled()` helper is implemented.
- `ModuleAccessMiddleware` is implemented and enforces module gating per route.
- `config/navigation.php` supports full module-based and permission-based visibility using a `'module'` key per nav item.
- All currently-built modules (`Agency`, `Billing`, `CMS`, `Customers`, `Demo`, `Helpdesk`, `Platform`, `ReleaseManager`, `Subscriptions`, `Todos`) are enabled in this dev `modules_statuses.json`.

The remaining sections in this document are retained as the extended blueprint reference (implemented + planned modules).

---

## Vision

Build a modular platform where:

- ✅ Each module = Standalone SaaS product
- ✅ Modules integrate seamlessly when used together
- ✅ Each customer gets their own instance (subdomain + database + code)
- ✅ Enable only the modules customer pays for
- ✅ Pivot from CMS SaaS → eCommerce SaaS → CRM SaaS → Full ERP
- ✅ White-label everything for agencies

---

## Deployment Model (Instance Per Customer)

Unlike multi-tenant SaaS where all customers share one database, Astero uses an **Instance Per Customer** model:

```

┌─────────────────────────────────────────────────────────────────────────────┐
│                           ASTERO MASTER SERVER                               │
│                    (HestiaCP manages all instances)                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Customer A: CRM SaaS                                                        │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  crm.customer-a.com                                                  │    │
│  │  ├── Own Hestia Account                                              │    │
│  │  ├── Own Database (customer_a_db)                                    │    │
│  │  ├── Own Code Instance                                               │    │
│  │  └── Enabled Modules: [CRM, Billing, Helpdesk]                       │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                                                              │
│  Customer B: Agency Reseller (Platform)                                      │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  app.agency-b.com                                                    │    │
│  │  ├── Own Hestia Account                                              │    │
│  │  ├── Own Database (agency_b_db)                                      │    │
│  │  ├── Own Code Instance                                               │    │
│  │  └── Enabled Modules: [Platform, CMS, Billing]                       │    │
│  │                                                                      │    │
│  │  Agency B's Customers (managed by Platform module):                  │    │
│  │  ├── client1.agency-b.com → Website on same/different server        │    │
│  │  ├── client2.agency-b.com → Website on same/different server        │    │
│  │  └── client3.agency-b.com → Website on same/different server        │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                                                              │
│  Customer C: eCommerce SaaS                                                  │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  store.customer-c.com                                                │    │
│  │  ├── Own Hestia Account                                              │    │
│  │  ├── Own Database (customer_c_db)                                    │    │
│  │  ├── Own Code Instance                                               │    │
│  │  └── Enabled Modules: [Catalog, eCommerce, Billing, Inventory]       │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Benefits of Instance Per Customer

| Benefit                 | Description                                                 |
| ----------------------- | ----------------------------------------------------------- |
| **Data Isolation**      | Each customer has their own database - no data leakage risk |
| **Custom Modules**      | Enable only what customer pays for                          |
| **Independent Scaling** | Scale individual instances as needed                        |
| **Custom Domains**      | Each customer uses their own domain/subdomain               |
| **Easy Backup/Restore** | Backup individual customer data easily                      |
| **White-Label Ready**   | Complete branding separation                                |
| **Compliance**          | Meet data residency requirements per customer               |

### Deployment Workflow

```

1. Customer signs up for CRM SaaS
   ↓
2. Provision new Hestia account (crm.customer-domain.com)
   ↓
3. Deploy Astero codebase
   ↓
4. Enable modules via artisan or update modules_statuses.json
   ↓
5. Run migrations for enabled modules only
   ↓
6. Customer accesses their private instance
```

---

## Architecture Overview

```

┌─────────────────────────────────────────────────────────────────────────────┐
│                         ASTERO INSTANCE (Per Customer)                       │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                        APP (Core Framework)                          │    │
│  │         Users, Auth, Roles, Permissions, Settings, API              │    │
│  │                   Base Classes, Shared Traits                        │    │
│  │                       (Lives in /app directory)                      │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                      │                                       │
│                    ┌─────────────────┴─────────────────┐                    │
│                    │   nwidart/laravel-modules         │                    │
│                    │   (Loads only enabled modules)    │                    │
│                    └─────────────────┬─────────────────┘                    │
│                                      │                                       │
│    ┌──────── ENABLED VIA modules_statuses.json ────────────────────────────┐│
│    │                                                                        ││
│    │   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐               ││
│    │   │    CRM      │◄──►│   BILLING   │◄──►│  HELPDESK   │               ││
│    │   │   MODULE    │    │   MODULE    │    │   MODULE    │               ││
│    │   └─────────────┘    └─────────────┘    └─────────────┘               ││
│    │                                                                        ││
│    │   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐               ││
│    │   │  DISABLED   │    │  DISABLED   │    │  DISABLED   │               ││
│    │   │  (Platform) │    │ (eCommerce) │    │    (CMS)    │               ││
│    │   └─────────────┘    └─────────────┘    └─────────────┘               ││
│    │                                                                        ││
│    └────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Note:** Platform module is a business module (not foundation). It's only enabled for Agency Reseller customers who need to manage websites, servers, and domains for their clients.

---

## Module Categories

### 1. App (Core Framework) - Lives in `/app` directory

The main Laravel application serves as the core framework that provides:

| Component               | Location                                | Purpose                              |
| ----------------------- | --------------------------------------- | ------------------------------------ |
| **Users & Auth**        | `app/Models/User.php`                   | User authentication & management     |
| **Roles & Permissions** | `app/Models/Role.php`, `Permission.php` | Access control                       |
| **Settings**            | `app/Services/SettingsService.php`      | Application settings                 |
| **Module Management**   | `Nwidart\Modules\Facades\Module`        | Module discovery via nwidart package |
| **Base Classes**        | `app/Http/Controllers/Base*.php`        | CRUD base controllers, services      |
| **Traits**              | `app/Traits/*.php`                      | Shared model traits                  |
| **API Foundation**      | `app/Http/Controllers/Api/`             | API base layer                       |

### 2. Business Modules (Enable Per Instance) - Lives in `/modules` directory

Each customer instance enables only the modules they need:

| Module             | Standalone SaaS              | Description                                                   |
| ------------------ | ---------------------------- | ------------------------------------------------------------- |
| **Customers**      | Customer Management SaaS     | Customer profiles, contacts, aggregated view from all modules |
| **Platform**       | Agency Reseller SaaS         | Websites, Servers, Domains, DNS, SSL, HestiaCP integration    |
| **ReleaseManager** | Release Distribution SaaS    | Application/module release registry + secure release API      |
| **Subscriptions**  | Subscription Management SaaS | Plans, PlanFeatures, Trials, Usage Tracking                   |
| **Billing**        | Invoice/Payments SaaS        | Invoices, Payments, Credits, Refunds, Taxes                   |
| **CRM**            | CRM SaaS (like HubSpot)      | Contacts, Companies, Deals, Pipelines                         |
| **Sales**          | Sales Pipeline SaaS          | Quotes, Orders, Discounts                                     |
| **Catalog**        | Product Catalog              | Products, Services, Pricing                                   |
| **Inventory**      | Inventory Management         | Stock, Warehouses, Tracking                                   |
| **eCommerce**      | Shopify-like Store           | Stores, Carts, Checkout                                       |
| **CMS**            | Website Builder              | Pages, Posts, Menus, Themes, Forms                            |
| **Helpdesk**       | Support Ticket SaaS          | Tickets, SLA, Knowledge Base                                  |
| **Projects**       | Project Management           | Tasks, Time Tracking, Boards                                  |
| **HR**             | HR Management                | Employees, Leaves, Attendance                                 |
| **Marketing**      | Email/Marketing Automation   | Campaigns, Newsletters, Automation                            |
| **Accounting**     | Full Accounting              | Chart of Accounts, Journals                                   |
| **Purchases**      | Purchase Orders              | Vendors, Bills, POs                                           |
| **Appointments**   | Booking/Scheduling           | Calendars, Time Slots                                         |
| **Documents**      | Document Management          | Files, Signatures, Templates                                  |
| **Agency**         | Agency SaaS Product          | Landing pages, Signup wizard, Client Portal Wrapper           |
| **Todos**          | Internal Task Tracker        | Simple todo/task list (internal tool)                         |

### 3. Example Customer Configurations

| Customer Type        | Enabled Modules                                                | Use Case                                      |
| -------------------- | -------------------------------------------------------------- | --------------------------------------------- |
| **CRM Customer**     | Customers, CRM, Subscriptions, Billing, Helpdesk, Marketing    | Sales team management                         |
| **Agency Reseller**  | Customers, Platform, CMS, Subscriptions, Billing               | White-label website builder for their clients |
| **eCommerce Store**  | Customers, Catalog, eCommerce, Inventory, Billing, Shipping    | Online store                                  |
| **Service Business** | Customers, CRM, Appointments, Subscriptions, Billing, Projects | Consulting firm                               |
| **Full ERP**         | All modules                                                    | Complete business suite                       |

---

## App Directory Structure (Core Framework)

The main `app/` directory serves as the core framework. Here's the structure with focus on module integration:

```

app/
├── Console/
│   └── Commands/
│

├── Contracts/                          # Scaffold internals only
│   └── ScaffoldServiceInterface.php    # Used by Scaffold system
│
├── Helpers/
│   └── modules.php                     # module_enabled() helper
│
├── Http/
│   ├── Controllers/
│   │   ├── BaseCrudController.php      # Base CRUD controller
│   │   ├── BaseCmsController.php       # Base CMS controller
│   │   └── Masters/
│   │       └── ModulesController.php   # 🔑 MODULE MANAGEMENT (existing)
│   │
│   ├── Middleware/
│   │   ├── CheckModuleEnabled.php      # Verify module access
│   │   └── ...
│   │
│   ├── Requests/
│   │   └── BaseFormRequest.php
│   │
│   └── Resources/
│       └── BaseCrudResource.php
│
├── Models/
│   ├── User.php                        # Core user model
│   ├── Role.php                        # Spatie roles
│   ├── Permission.php                  # Spatie permissions
│   ├── Setting.php                     # Key-value settings
│   ├── Address.php                     # Polymorphic addresses
│   ├── Media.php                       # Media library
│   ├── Activity.php                    # Activity log
│   ├── Notes.php                       # 🔑 Polymorphic notes (noteable_type, noteable_id)
│   ├── Group.php                       # 🔑 Reusable groups (e.g., "customer_groups")
│   └── GroupItem.php                   # 🔑 Items within groups
│
├── Services/
│   ├── SettingsService.php             # Settings management
│   ├── BaseCrudService.php             # Base CRUD service
│   └── ...
│
├── Traits/
│   ├── AuditableTrait.php              # created_by, updated_by
│   ├── AddressableTrait.php            # Polymorphic addresses
│   └── HasMediaTrait.php               # Media attachments
│
├── View/
│   └── Components/
│       └── App/Notes.php               # 🔑 Reusable notes component

# Module status file (root)
modules_statuses.json                    # 🔑 ENABLED/DISABLED STATE
```

### Key Files for Module System

| File                                                 | Purpose                                         |
| ---------------------------------------------------- | ----------------------------------------------- |
| `modules_statuses.json`                              | JSON file storing module enabled/disabled state |
| `config/modules.php`                                 | nwidart/laravel-modules configuration           |
| `app/Http/Controllers/Masters/ModulesController.php` | Admin UI for managing modules                   |
| `app/Helpers/modules.php`                            | `module_enabled()` helper function              |
| `app/Http/Middleware/CheckModuleEnabled.php`         | Middleware to block disabled module routes      |

---

## Directory Structure

For complete module directory structure with all files and folders, see:
📁 **[MODULE_DIRECTORY_STRUCTURE.md](MODULE_DIRECTORY_STRUCTURE.md)**

### Quick Reference

| Location                | Purpose                                                             |
| ----------------------- | ------------------------------------------------------------------- |
| `app/`                  | Core framework (not a module) — base classes, shared models, traits |
| `app/Contracts/`        | Scaffold internals only: `ScaffoldServiceInterface`                 |
| `app/Models/`           | Shared models (User, Notes, Group, Address, etc.)                   |
| `app/Traits/`           | Reusable traits (AuditableTrait, AddressableTrait, etc.)            |
| `modules/`              | All business modules (nwidart/laravel-modules)                      |
| `modules_statuses.json` | Module enabled/disabled state                                       |

---

## Database Naming Convention

**IMPORTANT:** All module tables MUST be prefixed with the module name in lowercase.

### Table Naming Rule

```
{module_name}_{table_name}
```

### Examples

| Module        | Table Name                                                  |
| ------------- | ----------------------------------------------------------- |
| Billing       | `billing_invoices`, `billing_payments`, `billing_taxes`     |
| CRM           | `crm_contacts`, `crm_companies`, `crm_deals`                |
| Sales         | `sales_quotes`, `sales_orders`, `sales_coupons`             |
| Customers     | `customers_customers`, `customers_customer_contacts`        |
| Platform      | `platform_websites`, `platform_servers`, `platform_domains` |
| Subscriptions | `subscriptions_plans`, `subscriptions_subscriptions`        |
| Inventory     | `inventory_warehouses`, `inventory_stock_levels`            |
| eCommerce     | `ecommerce_stores`, `ecommerce_carts`                       |
| CMS           | `cms_pages`, `cms_posts`, `cms_menus`                       |
| Helpdesk      | `helpdesk_tickets`, `helpdesk_slas`                         |

### Migration Example

```php
// modules/Billing/database/migrations/2024_01_01_000001_create_billing_invoices_table.php
Schema::create('billing_invoices', function (Blueprint $table) {
    $table->id();
    $table->string('number')->unique();
    $table->foreignId('customer_id')->nullable()->constrained('customers_customers');
    $table->decimal('total', 10, 2);
    $table->string('status');
    $table->timestamps();
    $table->softDeletes();
});
```

### Model Example

```php
// modules/Billing/app/Models/Invoice.php
namespace Modules\Billing\Models;

class Invoice extends Model
{
    protected $table = 'billing_invoices';

    // ...
}
```

### Why Prefix?

1. **Avoids conflicts** - Modules won't clash with each other or core tables
2. **Easy identification** - Quickly know which module owns a table
3. **Clean uninstalls** - Easy to drop all tables when removing a module
4. **Database organization** - Tables grouped together alphabetically by module

---

## Shared Patterns (IMPORTANT)

Modules should **NOT** create their own tables for common functionality. Use these centralized patterns:

### 1. Notes System (Polymorphic)

Use the central `notes` table with `noteable_type` and `noteable_id`:

```php
// ❌ DON'T create module-specific note tables
// modules/CRM/database/migrations/create_crm_notes_table.php  // WRONG!

// ✅ DO use the central notes system
// In your model view, use the Notes component:
<x-app.notes
    :noteable-type="get_class($customer)"
    :noteable-id="$customer->id"
    :return-url="url()->current()"
/>

// The Notes model (app/Models/Notes.php) already handles:
// - noteable_type (e.g., "Modules\Customers\Models\Customer")
// - noteable_id (e.g., 123)
// - module_title, note_content, created_by, etc.
```

### 2. Groups System (Reusable)

Use the central `groups` and `group_items` tables for categorization:

```php
// ❌ DON'T create module-specific group tables
// modules/Customers/database/migrations/create_customer_groups_table.php  // WRONG!

// ✅ DO use the central groups system
// 1. Create a group with slug "customer_groups" via seeder or admin UI
Group::create(['name' => 'Customer Groups', 'slug' => 'customer_groups']);

// 2. Add items to the group
GroupItem::create([
    'group_id' => $group->id,
    'name' => 'VIP',
    'slug' => 'vip',
]);

// 3. In your model, reference group_item_id
Schema::table('customers', function (Blueprint $table) {
    $table->foreignId('group_item_id')->nullable()->constrained('group_items');
});

// 4. Usage
$customer->groupItem; // Returns the GroupItem (e.g., "VIP", "Wholesale")
GroupItem::byGroup('customer_groups')->get(); // Get all customer groups
```

### 3. Config for Static Options

If users should NOT edit options, use config files:

```php
// ❌ DON'T use database for static, non-editable options
// modules/Platform/database/seeders/WebsiteStatusSeeder.php  // WRONG!

// ✅ DO use config for static options
// modules/Platform/config/platform.php
return [
    'website_statuses' => [
        'pending' => 'Pending',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'deleted' => 'Deleted',
    ],
    'server_types' => [
        'hestia' => 'HestiaCP',
        'cpanel' => 'cPanel',
        'plesk' => 'Plesk',
    ],
];

// Usage
config('platform.website_statuses');
// Or better, use Enums for type safety
```

### 4. Addresses System (Polymorphic)

Use the `AddressableTrait` for models that need addresses:

```php
// ❌ DON'T create module-specific address tables
// modules/CRM/database/migrations/create_crm_contact_addresses_table.php  // WRONG!

// ✅ DO use the central addresses system
// app/Traits/AddressableTrait.php

// 1. Add trait to your model
use App\Traits\AddressableTrait;

class Customer extends Model
{
    use AddressableTrait;

    // Define the polymorphic relationship
    public function addresses(): MorphMany
    {
        return $this->morphMany(\App\Models\Address::class, 'addressable');
    }
}

// 2. Save a single address
$customer->saveAddress([
    'first_name' => 'John',
    'last_name' => 'Doe',
    'address1' => '123 Main St',
    'city' => 'New York',
    'state' => 'NY',
    'country' => 'USA',
    'zip' => '10001',
    'phone' => '+1234567890',
], 'primary', true);

// 3. Save multiple address types
$customer->saveAddresses([
    ['type' => 'primary', 'address1' => '123 Main St', 'city' => 'NYC'],
    ['type' => 'billing', 'address1' => '456 Payment Ave', 'city' => 'LA'],
    ['type' => 'shipping', 'address1' => '789 Delivery Blvd', 'city' => 'SF'],
]);

// 4. Retrieve addresses
$customer->addresses;                           // All addresses
$customer->getAddressByType('billing');        // Specific type
$customer->addresses()->where('is_primary', true)->first();

// The Address model (app/Models/Address.php) handles:
// - addressable_type (e.g., "Modules\Customers\Models\Customer")
// - addressable_id (e.g., 123)
// - type (primary, billing, shipping, contact, support, technical)
// - address1, address2, city, state, country, zip, phone, etc.
```

### 5. When to Use Each Pattern

| Pattern                     | Use When                          | Example                                   |
| --------------------------- | --------------------------------- | ----------------------------------------- |
| **Notes (polymorphic)**     | Any model needs admin notes       | Customer notes, Order notes, Ticket notes |
| **Addresses (polymorphic)** | Models need address data          | Customer addresses, Vendor addresses      |
| **Groups (database)**       | User can add/edit categories      | Customer groups, Product categories       |
| **Config (file)**           | Static, developer-defined options | Statuses, Types, Priorities               |
| **Enums (PHP)**             | Finite set of typed values        | Status::Active, Priority::High            |

---

## Module Communication Pattern

Modules communicate via two mechanisms depending on the boundary:

| Boundary                              | Mechanism                  |
| ------------------------------------- | -------------------------- |
| Same instance (modules on one server) | Laravel Events + Listeners |
| Cross-server (e.g. Platform → Agency) | HTTP APIs                  |

### 1. Events & Listeners (within instance)

Modules emit events that other modules can listen to:

```php
// Sales module emits event
event(new OrderPlaced($order));

// Billing module listens and creates invoice
class CreateInvoiceOnOrderPlaced
{
    public function handle(OrderPlaced $event)
    {
        if (module_enabled('Billing')) {
            app(InvoiceService::class)->createFromOrder($event->order);
        }
    }
}

// Inventory module listens and reserves stock
class ReserveStockOnOrderPlaced
{
    public function handle(OrderPlaced $event)
    {
        if (module_enabled('Inventory')) {
            app(StockService::class)->reserve($event->order->items);
        }
    }
}

// CRM module listens and logs activity
class LogActivityOnOrderPlaced
{
    public function handle(OrderPlaced $event)
    {
        if (module_enabled('CRM')) {
            app(ActivityService::class)->log('order_placed', $event->order);
        }
    }
}
```

### 3. Module Management (Using nwidart/laravel-modules)

Astero uses **nwidart/laravel-modules** for all module management. No custom module registry needed.

#### Existing Features (Already Implemented)

| Feature                | How It Works                                                 |
| ---------------------- | ------------------------------------------------------------ |
| **Module Discovery**   | `Module::all()` - Auto-discovers modules in `/modules`       |
| **Enable/Disable**     | `$module->enable()` / `$module->disable()`                   |
| **Status Check**       | `$module->isEnabled()`                                       |
| **Status Persistence** | `modules_statuses.json` stores enabled/disabled state        |
| **Module Info**        | `$module->get('version')`, `$module->getDescription()`, etc. |
| **Migrations**         | `php artisan module:migrate {Module}`                        |
| **Seeders**            | `php artisan module:seed {Module}`                           |
| **Cache Clear**        | Automatic `optimize:clear` on module changes                 |

#### modules_statuses.json

```json
{
    "CMS": false,
    "Platform": true,
    "Billing": true,
    "CRM": false,
    "Helpdesk": false
}
```

This file is the single source of truth for which modules are enabled.

#### ModulesController (app/Http/Controllers/Masters/ModulesController.php)

Already provides:

```php
// List all modules
Module::all();

// Enable/disable module
$module = Module::find('CRM');
$module->enable();   // Enables + runs migrations + seeders
$module->disable();  // Disables module

// Check if enabled
$module->isEnabled();

// Get module metadata from module.json
$module->get('version');
$module->get('category');
$module->get('keywords');
$module->getDescription();
$module->getPriority();
```

#### Helper Function

```php
// app/Helpers/modules.php
use Nwidart\Modules\Facades\Module;

if (!function_exists('module_enabled')) {
    function module_enabled(string $module): bool
    {
        $mod = Module::find($module);
        return $mod && $mod->isEnabled();
    }
}
```

#### Usage in Code

```php
// Check if module is enabled before showing menu/routes
if (module_enabled('CRM')) {
    // Show CRM menu items
}

// In Blade views
@if(module_enabled('Billing'))
    <x-nav-item route="billing.invoices.index" label="Invoices" />
@endif

// In middleware
if (!module_enabled($module)) {
    abort(404, 'Module not available');
}
```

#### Artisan Commands (Built-in)

```bash
# List all modules
php artisan module:list

# Enable a module
php artisan module:enable CRM

# Disable a module
php artisan module:disable CRM

# Run module migrations
php artisan module:migrate CRM

# Run module seeders
php artisan module:seed CRM

# Create new module
php artisan module:make Inventory

# Generate module components
php artisan module:make-controller ProductController Catalog
php artisan module:make-model Product Catalog
php artisan module:make-migration create_products_table Catalog
```

### 4. Module Dependencies in module.json

```json
// Sales/module.json
{
    "name": "Sales",
    "alias": "sales",
    "description": "Sales pipeline, quotes, and orders",
    "version": "1.0.0",
    "dependencies": {
        "required": ["Core", "Billing"],
        "optional": ["CRM", "Inventory", "Catalog"]
    },
    "provides": ["quotes", "orders", "discounts", "coupons"],
    "listens": ["inventory.stock_updated", "crm.contact_created"],
    "emits": ["sales.quote_sent", "sales.order_placed", "sales.order_completed"]
}
```

---

## Module Configuration (Using nwidart/laravel-modules)

Each module has a `module.json` that defines its metadata:

```json
// modules/CRM/module.json
{
    "name": "CRM",
    "alias": "crm",
    "description": "Customer Relationship Management - Contacts, Companies, Deals, Pipelines",
    "keywords": ["crm", "contacts", "deals", "pipeline", "sales"],
    "priority": 5,
    "version": "1.0.0",
    "author": "AsteroDigital",
    "homepage": "https://asterodigital.com",
    "category": "Business",
    "icon": "<i class='ri-contacts-line'></i>",
    "providers": ["Modules\\CRM\\Providers\\CRMServiceProvider"],
    "files": [],
    "dependencies": [],
    "optional": ["Billing", "Sales", "Helpdesk"]
}
```

### Module Status Management

The `modules_statuses.json` file in the project root controls which modules are active:

```json
{
    "Platform": true,
    "Billing": true,
    "Subscriptions": true,
    "CMS": true,
    "Helpdesk": true,
    "eCommerce": false
}
```

### Deploying Customer Instance

Customer instance deployment is handled by the **Platform module** using HestiaCP custom scripts located in `hestia/bin/`. These scripts automate account creation, code deployment, database setup, and module configuration. See Platform module documentation for detailed deployment workflows.

### Admin UI Module Management

The existing ModulesController (`app/Http/Controllers/Masters/ModulesController.php`) provides:

- `/masters/modules` - List all modules with enable/disable toggles
- `/masters/modules/api/list` - JSON API for module listing
- `/masters/modules/api/{module}/toggle` - Toggle module status
- `/masters/modules/api/bulk-update` - Bulk enable/disable

When a module is enabled via UI:

1. `$module->enable()` is called
2. Migrations run automatically
3. Seeders run automatically
4. Cache is cleared

---

## Cross-App APIs (Current + Pending)

### Release API (Implemented)

- Route file: `modules/ReleaseManager/routes/api.php`
- Endpoint: `GET /api/release-manager/v1/releases/latest-update/{type}/{packageIdentifier}`
- Middleware: `release.api.key`
- Security header: `X-Release-Key`
- Controller: `modules/ReleaseManager/app/Http/Controllers/Api/V1/ReleaseController.php`

### Provisioning API — Agency → Platform (✅ Implemented)

Multiple agency deployments (`my.astero.in`, `herofy.in`, `breederspot.com`, ...) communicate with a **single** Platform instance at `platform.astero.net.in`.

**Authentication:** `X-Agency-Key` header → `AgencyApiKeyMiddleware` validates against `platform_agencies.secret_key` → resolves `Agency` model → binds to request.

**Endpoints (all under `/api/platform/v1/`):**

| Method   | Endpoint                     | Purpose                                | FormRequest             |
| -------- | ---------------------------- | -------------------------------------- | ----------------------- |
| `POST`   | `/websites`                  | Create website + dispatch provisioning | `CreateWebsiteRequest`  |
| `GET`    | `/websites`                  | List websites for calling agency       | —                       |
| `GET`    | `/websites/{siteId}`         | Get website status/details             | —                       |
| `PATCH`  | `/websites/{siteId}/status`  | Suspend/unsuspend/expire               | `ChangeStatusRequest`   |
| `DELETE` | `/websites/{siteId}`         | Trash/destroy website                  | `DestroyWebsiteRequest` |
| `POST`   | `/websites/{siteId}/restore` | Restore trashed website                | `RestoreWebsiteRequest` |

**Note:** `CreateWebsiteRequest` does not require `status` (controller hardcodes `provisioning`) or `type` (defaults to `paid`). The controller sets `skip_dns => true` and `skip_cdn => true` for agency-provisioned websites.

**Agency-Side Client:** `PlatformApiClient` service in Agency module — reads `AGENCY_SECRET_KEY` + `PLATFORM_API_URL` from `config/agency.php`. No direct Platform model imports. Uses `withoutVerifying()` in local environment for self-signed certificates.

### Provisioning API — Platform → Agency Webhooks (✅ Implemented)

Platform dispatches `SendAgencyWebhook` jobs for **all** website lifecycle events, not just provisioning:

| Event                      | Trigger                          | Dispatched From                                    |
| -------------------------- | -------------------------------- | -------------------------------------------------- |
| `website.provisioned`      | Provisioning succeeds            | `WebsiteProvision` job                             |
| `website.provision_failed` | Provisioning fails               | `WebsiteProvision` job                             |
| `website.status_changed`   | Suspend, activate, expire        | `WebsiteLifecycleService`, `WebsiteService` (bulk) |
| `website.deleted`          | Trash via destroy or bulk delete | `WebsiteLifecycleService`, `WebsiteService` (bulk) |
| `website.restored`         | Restore from trash               | `WebsiteLifecycleService`, `WebsiteService` (bulk) |

**Key implementation details:**

- `webhook_url` is a **dedicated column** on `platform_agencies` (not metadata). `varchar(500)`, nullable.
- `SendAgencyWebhook::dispatchForWebsite(Website $website, string $event, array $extra)` — centralized static helper.
- Webhook payload includes: `event`, `site_id`, `domain`, `status`, `timestamp`, plus event-specific fields (e.g., `previous_status` for status changes).
- Signed with HMAC-SHA256 using agency's plain secret key. Signature sent in `X-Webhook-Signature` header.
- Retry: 3 attempts with `[10, 60, 300]` second backoff.
- Agency receives webhooks at `POST /api/agency/v1/webhooks/platform`, verifies HMAC, updates local `agency_websites` records via `syncFromWebhook()`.

**Webhook payload example:**

```json
{
    "event": "website.status_changed",
    "site_id": "ws0000345",
    "status": "suspended",
    "previous_status": "active",
    "domain": "client.example.com",
    "timestamp": "2026-02-19T12:00:00Z"
}
```

---

## Database Schema Considerations

Since each customer has their own database, schema is straightforward:

```php
// No tenant_id needed - entire database belongs to one customer
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->string('number')->unique();
    $table->foreignId('customer_id')->nullable()->constrained('users');
    $table->decimal('total', 10, 2);
    $table->string('status');
    $table->timestamp('due_date')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

// Migrations run only for enabled modules
// php artisan migrate runs migrations from:
// - database/migrations (core)
// - modules/{EnabledModule}/database/migrations (each enabled module)
```

### Migration Command

```bash
# When deploying a new customer instance
php artisan migrate  # Runs core + enabled module migrations only

# If customer upgrades to add a new module
# 1. Enable the module via artisan
php artisan module:enable Projects
# 2. Run migrations for the new module
php artisan module:migrate Projects
# 3. Run seeders if needed
php artisan module:seed Projects
```

---

## SaaS Product Offerings

Each product is a different module combination deployed as a separate instance:

### Product 1: Agency SaaS (Website Builder)

```json
// modules_statuses.json
{
    "Agency": true,
    "Platform": true,
    "Billing": true,
    "Helpdesk": true,
    "CMS": false
}
```

```

Customer: Digital Agency white-labeling the builder for their clients
Instance: app.agency-name.com
- Agency module controls the landing page and signup flow
- Platform module handles the heavy lifting (sites, domains)
- Billing module handles subscription to the Agency Plan
```

### Product 2: CRM SaaS

```json
// modules_statuses.json
{
    "CRM": true,
    "Billing": true,
    "Helpdesk": true,
    "Marketing": true,
    "Platform": false,
    "CMS": false
}
```

```

Customer: Sales team needing contact/deal management
Instance: crm.company-name.com
```

### Product 3: eCommerce SaaS (Shopify-like)

```json
// modules_statuses.json
{
    "Catalog": true,
    "eCommerce": true,
    "Inventory": true,
    "Billing": true,
    "CMS": true,
    "Platform": false,
    "CRM": false
}
```

```

Customer: Online store owner
Instance: store.brand-name.com
```

### Product 4: Project Management SaaS

```json
// modules_statuses.json
{
    "Projects": true,
    "Helpdesk": true,
    "Billing": true,
    "HR": true,
    "Platform": false,
    "CRM": false
}
```

```

Customer: Agency managing client projects
Instance: pm.agency-name.com
```

### Product 5: Full ERP

```json
// modules_statuses.json
{
    "CRM": true,
    "Sales": true,
    "Catalog": true,
    "Inventory": true,
    "Billing": true,
    "Accounting": true,
    "HR": true,
    "Projects": true,
    "Helpdesk": true
}
```

```

Customer: Business needing complete suite
Instance: erp.company-name.com
```

---

## Pros & Cons Analysis

### ✅ PROS

| Advantage                   | Description                                                 |
| --------------------------- | ----------------------------------------------------------- |
| **Complete Data Isolation** | Each customer has own database - zero data leakage risk     |
| **True White-Label**        | Each instance is completely separate with own branding      |
| **Custom Module Mix**       | Enable exactly what customer needs and pays for             |
| **Independent Scaling**     | Scale each customer's resources independently               |
| **Easy Compliance**         | Meet data residency requirements (host in specific regions) |
| **Simple Backups**          | Backup/restore individual customer easily                   |
| **No Noisy Neighbors**      | One customer's load doesn't affect others                   |
| **Parallel Development**    | Teams can work on different modules independently           |
| **Easy Testing**            | Test modules in isolation                                   |
| **Revenue Optimization**    | Charge per module per instance                              |

### ❌ CONS

| Disadvantage              | Description                                | Mitigation                                          |
| ------------------------- | ------------------------------------------ | --------------------------------------------------- |
| **More Server Resources** | Each customer needs own instance           | Use lightweight containers, optimize resource usage |
| **Deployment Complexity** | Managing many instances                    | Automate with HestiaCP API + deployment scripts     |
| **Update Rollout**        | Updates need to be pushed to all instances | Build auto-update mechanism or managed updates      |
| **Cross-Module Testing**  | Integration testing is harder              | Create integration test suites                      |
| **Code Duplication**      | Same code on many servers                  | Use Git-based deployment, single source             |
| **Monitoring Overhead**   | Monitor many instances                     | Centralized monitoring (health checks, alerts)      |
| **Initial Complexity**    | More upfront architecture work             | Worth it for long-term gains                        |

---

## Implementation Roadmap

### Phase 1: Foundation (Current State + Improvements)

```

Week 1-2:
├── ✅ App core (users, roles, permissions) - Implemented
├── ✅ Platform module (websites, servers, domains, DNS, SSL, secrets) - Implemented
├── ✅ ReleaseManager module (release registry + secure release API) - Implemented
├── ✅ Module management via nwidart/laravel-modules - Implemented
├── ✅ ModulesController for admin UI - Implemented
├── ✅ modules_statuses.json for enable/disable - Implemented
├── ✅ app/Contracts directory (ScaffoldServiceInterface) - Implemented
├── ✅ app/Helpers/modules.php with module_enabled() helper - Implemented
├── ✅ Module access middleware (ModuleAccessMiddleware) - Implemented
├── ✅ config/navigation.php with module-based visibility ('module' key) - Implemented
├── 🔄 Finalize per-domain deployment profiles (`console` / `pkg` / `my` / `marketing`) - In progress
└── ✅ Cross-app provisioning API (`my` -> `console`) - Fully implemented (API + webhooks + client + onboarding)
```

### Phase 2: Core Business Modules

```

Week 3-6:
├── ✅ Customers module (Customer, CustomerContact) - Implemented
├── ✅ Subscriptions module (Plan, PlanFeature, Subscription, UsageRecord) - Implemented
├── ✅ Billing module (Invoice, InvoiceItem, Payment, Credit, Refund, Tax, Transaction) - Implemented
├── ✅ Agency module (landing pages, signup flow, definitions/services) - Partially implemented (no models yet)
├── 📦 Catalog module (products, services, pricing) - NOT STARTED
├── 📊 Sales module (quotes, orders) - NOT STARTED
└── 📊 CRM module (contacts, companies, pipelines) - NOT STARTED
```

### Phase 3: Advanced Modules

```

Week 7-12:
├── ✅ Helpdesk module (Ticket, TicketReplies, Department, SLA) - Implemented
├── ✅ CMS module (CmsPost, CmsPostTerm, DesignBlock, Form, FormSubmission, Menu, MenuItem, Redirection, Theme, ThemeDeployment) - Implemented
├── 🛒 eCommerce module (stores, carts, checkout) - NOT STARTED
├── 📦 Inventory module (stock, warehouses) - NOT STARTED
└── 📋 Projects module (tasks, time tracking) - NOT STARTED
```

### Phase 4: Enterprise Modules

```

Week 13+:
├── 📒 Accounting module
├── 👔 HR module
├── 📧 Marketing module
└── 📊 Reporting module
```

### Phase 5: Automation & Scaling

```

├── 🚀 Auto-provisioning new customer instances via HestiaCP API
├── 🔄 Auto-update mechanism for all instances
├── 📊 Centralized monitoring dashboard
└── 💳 Billing integration for module-based pricing
```

---

## Navigation Structure

### Dynamic Menu Based on Enabled Modules

```php
// Core/config/navigation.php

return [
    'main' => [
        [
            'label' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'ri-dashboard-line',
            'module' => null,  // Always show
        ],
        [
            'label' => 'Websites',
            'route' => 'platform.websites.index',
            'icon' => 'ri-global-line',
            'module' => 'Platform',
        ],
        [
            'label' => 'CRM',
            'icon' => 'ri-contacts-line',
            'module' => 'CRM',
            'children' => [
                ['label' => 'Contacts', 'route' => 'crm.contacts.index'],
                ['label' => 'Companies', 'route' => 'crm.companies.index'],
                ['label' => 'Deals', 'route' => 'crm.deals.index'],
            ],
        ],
        [
            'label' => 'Billing',
            'icon' => 'ri-bank-card-line',
            'module' => 'Billing',
            'children' => [
                ['label' => 'Invoices', 'route' => 'billing.invoices.index'],
                ['label' => 'Subscriptions', 'route' => 'billing.subscriptions.index'],
                ['label' => 'Plans', 'route' => 'billing.plans.index'],
            ],
        ],
        // ... more modules
    ],
];

// Navigation renders only enabled modules
@foreach(config('navigation.main') as $item)
    @if(!$item['module'] || module_enabled($item['module']))
        // Render menu item
    @endif
@endforeach
```

---

## Key Files to Create/Update

### In App (Core Framework)

| File                                                 | Purpose                          | Status         |
| ---------------------------------------------------- | -------------------------------- | -------------- |
| `app/Contracts/ScaffoldServiceInterface.php`         | Scaffold service contract        | ✅ Implemented |
| `app/Http/Middleware/ModuleAccessMiddleware.php`     | Block access to disabled modules | ✅ Implemented |
| `app/Helpers/modules.php`                            | `module_enabled()` helper        | ✅ Implemented |
| `config/modules.php`                                 | nwidart/laravel-modules config   | ✅ Exists      |
| `config/navigation.php`                              | Module-aware navigation config   | ✅ Implemented |
| `modules_statuses.json`                              | Module enabled/disabled state    | ✅ Exists      |
| `app/Http/Controllers/Masters/ModulesController.php` | Module management UI             | ✅ Exists      |

### In Each Module

| File                  | Purpose                                                 |
| --------------------- | ------------------------------------------------------- |
| `module.json`         | Module metadata (name, version, category, dependencies) |
| `app/Contracts/*.php` | Module-specific interfaces (optional)                   |
| `app/Events/*.php`    | Domain events                                           |
| `app/Listeners/*.php` | React to other module events                            |

### Deployment Scripts

| File                            | Purpose                                     | Status                                                                                                                                                                |
| ------------------------------- | ------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `scripts/provision-instance.sh` | Create new customer Hestia account + deploy | ⏸ Deliberately deferred — provisioning is handled by the Platform API (`WebsiteApiController`) + `hestia/bin/` shell scripts; a standalone bash wrapper is not needed |
| `scripts/configure-modules.sh`  | Enable/disable modules for customer         | ⏸ Deliberately deferred — module profiles are managed per-deployment via `modules_statuses.json`; no runtime toggle script is required                                |
| `scripts/create-release.sh`     | Create a new release package                | ✅ Exists                                                                                                                                                             |
| `scripts/astero-update.sh`      | Update script for existing instances        | ✅ Exists                                                                                                                                                             |
| `scripts/generate-ssl-cert.sh`  | SSL certificate generation helper           | ✅ Exists                                                                                                                                                             |
| `hestia/bin/`                   | HestiaCP provisioning shell scripts         | ✅ Exists                                                                                                                                                             |

---

## Comparison with Competitors

| Feature              | Astero (Proposed) | Odoo | Salesforce | HubSpot |
| -------------------- | ----------------- | ---- | ---------- | ------- |
| Modular Architecture | ✅                | ✅   | ✅         | ⚠️      |
| Self-Hosted Option   | ✅                | ✅   | ❌         | ❌      |
| White-Label          | ✅                | ⚠️   | ❌         | ❌      |
| Website Builder      | ✅                | ⚠️   | ❌         | ✅      |
| Hosting Integration  | ✅                | ❌   | ❌         | ❌      |
| Open Source          | ✅                | ✅   | ❌         | ❌      |
| Per-Module Pricing   | ✅                | ✅   | ✅         | ⚠️      |

---

## Conclusion

This architecture positions Astero to:

1. **Start focused** - CMS SaaS with billing
2. **Grow modularly** - Add eCommerce, CRM, etc.
3. **Pivot easily** - Offer different SaaS products with same codebase
4. **Scale per customer** - Each customer gets dedicated resources
5. **Compete effectively** - Match Odoo/Salesforce flexibility
6. **White-label completely** - Each instance is fully brandable

The key is building the **App's contract system** (`app/Contracts/`) and using **nwidart/laravel-modules** properly from the start. Everything else builds on that foundation.

### Architecture Summary

```

┌─────────────────────────────────────────────────────────────┐
│                    EACH CUSTOMER INSTANCE                    │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌───────────────────────────────────────────────────────┐  │
│  │                      /app                              │  │
│  │            (Core Framework - Always Loaded)            │  │
│  ├───────────────────────────────────────────────────────┤  │
│  │  • Users, Auth, Roles, Permissions                    │  │
│  │  • Module Management via nwidart/laravel-modules      │  │
│  │  • Base Classes (BaseCrudController, BaseCrudService) │  │
│  │  • Shared Traits (AuditableTrait)                     │  │
│  │  • Core Events & Listeners                            │  │
│  │  • API Foundation                                      │  │
│  └───────────────────────────────────────────────────────┘  │
│                              │                               │
│                              │ Loads enabled modules         │
│                              │ (modules_statuses.json)       │
│                              ▼                               │
│  ┌───────────────────────────────────────────────────────┐  │
│  │                      /modules                          │  │
│  │           (Only Enabled Modules Loaded)                │  │
│  ├───────────────────────────────────────────────────────┤  │
│  │  CRM SaaS: [CRM, Billing, Helpdesk, Marketing]        │  │
│  │  Agency:   [Platform, CMS, Billing]                    │  │
│  │  eCommerce:[Catalog, eCommerce, Inventory, Billing]   │  │
│  │  Full ERP: [All Modules]                               │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                              │
│  📁 Own Database   📁 Own Codebase   🌐 Own Domain          │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Instance Per Customer vs Multi-Tenant

| Aspect         | Instance Per Customer (Astero) | Multi-Tenant                |
| -------------- | ------------------------------ | --------------------------- |
| Data Isolation | ✅ Complete (own DB)           | ⚠️ Shared DB with tenant_id |
| Customization  | ✅ Full control                | ⚠️ Limited                  |
| Scaling        | ✅ Per customer                | ⚠️ Shared resources         |
| Resource Usage | ⚠️ More servers                | ✅ Efficient                |
| Deployment     | ⚠️ More instances              | ✅ Single deployment        |
| Updates        | ⚠️ Roll out to all             | ✅ Update once              |
| White-Label    | ✅ Complete                    | ⚠️ Theme-based              |
| Compliance     | ✅ Easy                        | ⚠️ Complex                  |

---

## Next Steps

1. [x] Create `app/Contracts/` directory (`ScaffoldServiceInterface` exists)
2. [x] Create `app/Helpers/modules.php` with `module_enabled()` helper
3. [x] Create `app/Http/Middleware/ModuleAccessMiddleware.php`
4. [x] Update navigation to use `module_enabled()` checks (`config/navigation.php` supports `'module'` key)
5. [x] Build dynamic navigation based on enabled modules
6. [x] **Platform Provisioning API** — `AgencyApiKeyMiddleware` + `WebsiteApiController` + webhook callback (see PRD: `docs/prd/platform-agency-provisioning-api-prd.md`)
7. [x] **Agency `PlatformApiClient`** — HTTP client service replacing direct Platform model imports
8. [x] **Agency onboarding wiring** — connect `processPayment()` to `PlatformApiClient::createWebsite()` (multi-website support)
9. [x] **Decouple Agency from Platform** — removed all `Modules\Platform\*` imports, removed `Platform` from Agency `module.json` dependencies
       9a. [x] **Webhook lifecycle dispatch** — `SendAgencyWebhook::dispatchForWebsite()` called from `WebsiteLifecycleService`, `WebsiteService`, and `WebsiteProvision` for all events
       9b. [x] **`webhook_url` dedicated column** — promoted from metadata to `platform_agencies.webhook_url` column with edit form + show page display
10. ~~Create `scripts/provision-instance.sh`~~ — superseded; provisioning is handled via the Platform API + `hestia/bin/` scripts
11. ~~Create `scripts/configure-modules.sh`~~ — superseded; module state is managed via `modules_statuses.json` per deployment profile
12. [ ] Add models/migrations to Agency module (currently Definitions/Services only)
13. [ ] Start Catalog module (products, services, pricing)
14. [ ] Add CRM module base (contacts, companies, deals, pipelines)
15. [ ] Finalize per-domain `modules_statuses.json` profiles for each deployment

---

## Related Documents

- [Billing Module Organization](BILLING_MODULE_ORGANIZATION.md)
- [CRUD Controller Guide](CRUD_MIGRATION_TO_BASECONTROLLER_GUIDE.md)
- [DataGrid Implementation](datagrid-implementation-progress.md)
