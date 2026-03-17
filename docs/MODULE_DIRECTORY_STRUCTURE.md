# Module Directory Structure

> This document provides the complete directory structure for all Astero modules.
> For the main architecture guide, see [MODULAR_SAAS_ARCHITECTURE.md](MODULAR_SAAS_ARCHITECTURE.md)

---

## 2026 Deployment Update (Authoritative)

The architecture now targets 4 separated deployments:

| Domain                   | Responsibility                                         | Active Modules                                                |
| ------------------------ | ------------------------------------------------------ | ------------------------------------------------------------- |
| `platform.astero.net.in` | Provisioning + infrastructure operations               | `Platform`                                                    |
| `pkg.astero.net.in`      | Release registry and Release API                       | `ReleaseManager`                                              |
| `my.astero.in`           | Onboarding, customers, billing, subscriptions, support | `Agency`, `Customers`, `Billing`, `Subscriptions`, `Helpdesk` |
| `astero.in`              | Marketing site, landing pages, blog                    | `CMS`                                                         |

Current modules present in repository:

- `Agency`
- `Billing`
- `CMS`
- `Customers`
- `Helpdesk`
- `Platform`
- `ReleaseManager`
- `Subscriptions`
- Internal/reference: `Demo`, `Todos`

Recommended `modules_statuses.json` per deployment:

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

Detailed sections below are preserved as the full structure blueprint, now updated with current deployment notes.

---

## Core App Directory

```
astero/
│
├── app/                               # 🏛️ CORE FRAMEWORK (Not a module)
│   ├── Contracts/                     # Contracts (ScaffoldServiceInterface)
│   │
│   ├── Scaffold/                      # 🔧 SCAFFOLD SYSTEM (CRUD Framework)
│   │   ├── ScaffoldDefinition.php     # Base definition class
│   │   ├── ScaffoldController.php     # Base CRUD controller
│   │   ├── ScaffoldRequest.php        # Base form request
│   │   ├── ScaffoldResource.php       # Base API resource
│   │   └── Builders/                  # Fluent builders
│   │       ├── Column.php
│   │       ├── Filter.php
│   │       ├── Action.php
│   │       └── StatusTab.php
│   │
│   ├── Helpers/
│   │   └── modules.php                # module_enabled() helper
│   │
│   ├── Models/
│   │   ├── User.php                   # Core user model
│   │   ├── Role.php                   # Roles (spatie/permission)
│   │   ├── Permission.php             # Permissions
│   │   └── Setting.php                # Key-value settings
│   │
│   ├── Services/
│   │   ├── SettingsService.php        # Settings management
│   │   └── ...
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Masters/
│   │   │       └── ModulesController.php  # 🔑 Module admin UI (existing)
│   │   ├── Middleware/
│   │   │   └── ...
│   │   ├── Requests/                  # Uses ScaffoldRequest
│   │   └── Resources/                 # Uses ScaffoldResource
│   │
│   ├── Traits/
│   │   └── ...
│   │
│   └── ...
│
├── config/
│   ├── modules.php                    # nwidart/laravel-modules config (existing)
│   └── ...
│
└── modules_statuses.json              # 🔑 Module enabled/disabled state (existing)
```

---

## Business Modules

All modules live in the `/modules` directory.

### Customers Module [IMPLEMENTED]

```
modules/Customers/                     # 👥 CUSTOMER MANAGEMENT (WHMCS-style)
├── app/
│   ├── Console/
│   ├── Contracts/
│   ├── Definitions/
│   ├── Enums/
│   ├── Helpers/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Jobs/
│   ├── Models/
│   │   ├── Customer.php               # Links to User, aggregates data
│   │   ├── CustomerContact.php        # Additional contacts for customer
│   │   └── ...
│   ├── Observers/
│   ├── Policies/
│   ├── Providers/
│   ├── Services/
│   │   └── CustomerService.php        # Aggregates data from enabled modules
│   └── Transformers/
├── database/
│   ├── migrations/
│   │   ├── ..._create_customers_table.php
│   │   ├── ..._create_customer_contacts_table.php
│   │   └── ..._add_unique_user_id_to_customers...
│   └── seeders/
├── config/
│   └── customer_groups.php            # Static groups if not editable
└── module.json
    # Tables: customers_customers, customers_customer_contacts
    # Uses: app/Models/Group (slug: "customer_groups"), app/Models/Notes
```

### Agency Module [IMPLEMENTED]

```
modules/Agency/                        # 🏢 CUSTOMER PORTAL (SaaS Wrapper)
├── app/
│   ├── Definitions/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── OnboardingController.php        # Signup Flow + onboarding wizard
│   │   │   ├── DashboardController.php         # Customer Dashboard
│   │   │   ├── WebsiteController.php           # Customer websites
│   │   │   ├── SubscriptionController.php      # Customer subscriptions
│   │   │   ├── InvoiceController.php           # Customer invoices
│   │   │   └── TicketController.php            # Customer support tickets
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Providers/
│   └── Services/
├── resources/views/
│   ├── onboarding/                    # start, plans, website, checkout, wizard
│   ├── dashboard/
│   ├── websites/
│   ├── subscriptions/
│   ├── invoices/
│   └── tickets/
├── routes/
│   ├── web.php
│   └── api.php
└── module.json
    # dependencies: ["Platform", "Billing", "Customers", "Helpdesk"]
    # provides: ["customer-portal", "onboarding-flow"]
```

### Platform Module [IMPLEMENTED]

```
modules/Platform/                      # 🌐 INFRASTRUCTURE MODULE
├── app/
│   ├── Console/
│   ├── Definitions/
│   ├── Events/
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Requests/
│   ├── Models/
│   │   ├── Website.php
│   │   ├── Server.php
│   │   ├── Domain.php
│   │   ├── Tld.php
│   │   └── DomainDnsRecord.php
│   ├── Jobs/
│   │   ├── ServerProvision.php
│   │   ├── ServerUpdateReleases.php
│   │   ├── WebsiteProvision.php
│   │   └── WebsiteDelete.php
│   ├── Providers/
│   ├── Traits/
│   └── Services/
│       ├── WebsiteService.php
│       ├── WebsiteProvisioningService.php
│       ├── WebsiteLifecycleService.php
│       ├── ServerService.php
│       └── ServerSSHService.php
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   ├── web.php
│   └── api.php                        # currently minimal/empty for external provisioning API
└── module.json
    # Tables: platform_websites, platform_servers, platform_domains, etc.
    # Provisioning API for my -> console is pending implementation in routes/api.php
```

### Subscriptions Module [IMPLEMENTED]

```
modules/Subscriptions/                 # 🔄 SUBSCRIPTION MANAGEMENT (IMPLEMENTED)
├── app/
│   ├── Contracts/
│   │   ├── HasFeatures.php
│   │   ├── Subscribable.php
│   │   └── SubscriptionAggregator.php
│   ├── Definitions/
│   │   ├── PlanDefinition.php
│   │   └── SubscriptionDefinition.php
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Models/
│   │   ├── Plan.php
│   │   ├── PlanFeature.php
│   │   ├── Subscription.php
│   │   └── UsageRecord.php
│   ├── Providers/
│   │   ├── EventServiceProvider.php
│   │   ├── RouteServiceProvider.php
│   │   └── SubscriptionsServiceProvider.php
│   └── Services/
│       ├── PlanService.php
│       ├── SubscriptionScaffoldService.php
│       └── SubscriptionService.php
├── database/
│   ├── migrations/
│   │   ├── 2026_01_29_100001_create_subscriptions_plans_table.php
│   │   ├── 2026_01_29_100002_create_subscriptions_plan_features_table.php
│   │   ├── 2026_01_29_100003_create_subscriptions_subscriptions_table.php
│   │   └── 2026_01_29_100004_create_subscriptions_usage_records_table.php
│   └── seeders/
│       ├── PlanSeeder.php
│       └── SubscriptionsDatabaseSeeder.php
└── module.json
```

### Billing Module [IMPLEMENTED]

```
modules/Billing/                       # 💰 INVOICES & PAYMENTS (IMPLEMENTED)
├── app/
│   ├── Contracts/
│   │   ├── Billable.php
│   │   ├── BillingAggregator.php
│   │   └── Invoiceable.php
│   ├── Definitions/
│   │   ├── CreditDefinition.php
│   │   ├── InvoiceDefinition.php
│   │   ├── PaymentDefinition.php
│   │   ├── RefundDefinition.php
│   │   ├── TaxDefinition.php
│   │   └── TransactionDefinition.php
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Models/
│   │   ├── Credit.php
│   │   ├── Invoice.php
│   │   ├── InvoiceItem.php
│   │   ├── Payment.php
│   │   ├── Refund.php
│   │   ├── Tax.php
│   │   └── Transaction.php
│   ├── Providers/
│   │   ├── BillingServiceProvider.php
│   │   ├── EventServiceProvider.php
│   │   └── RouteServiceProvider.php
│   └── Services/
│       ├── BillingService.php
│       ├── CreditService.php
│       ├── CurrencyService.php
│       ├── InvoiceService.php
│       ├── PaymentService.php
│       ├── RefundService.php
│       ├── TaxService.php
│       └── TransactionService.php
├── database/
│   ├── migrations/
│   │   ├── 2026_01_29_000001_create_billing_invoices_table.php
│   │   ├── 2026_01_29_000002_create_billing_invoice_items_table.php
│   │   ├── 2026_01_29_000003_create_billing_payments_table.php
│   │   ├── 2026_01_29_000004_create_billing_credits_table.php
│   │   ├── 2026_01_29_000005_create_billing_refunds_table.php
│   │   ├── 2026_01_29_000006_create_billing_taxes_table.php
│   │   └── 2026_01_29_000007_create_billing_transactions_table.php
│   └── seeders/
│       ├── BillingDatabaseSeeder.php
│       └── TaxSeeder.php
└── module.json
```

### Catalog Module [PLANNED]

```
modules/Catalog/                       # 📦 PRODUCTS & SERVICES CATALOG
├── app/
│   ├── Models/
│   │   ├── Product.php
│   │   ├── ProductVariant.php
│   │   ├── Service.php
│   │   ├── Category.php
│   │   ├── Attribute.php
│   │   ├── AttributeValue.php
│   │   ├── PriceList.php
│   │   └── DomainPricing.php          # TLD pricing
│   └── Contracts/
│       ├── Purchasable.php
│       └── HasPricing.php
├── database/
│   └── migrations/
│       ├── create_catalog_products_table.php
│       ├── create_catalog_categories_table.php
│       └── create_catalog_price_lists_table.php
└── module.json
    # dependencies: []
    # optional: ["Inventory", "eCommerce", "Sales"]
    # provides: ["products", "services", "categories", "pricing"]
    # Tables: catalog_products, catalog_categories, etc.
```

### CRM Module [PLANNED]

```
modules/CRM/                           # 👥 CUSTOMER RELATIONSHIP MANAGEMENT
├── app/
│   ├── Models/
│   │   ├── Contact.php
│   │   ├── Company.php
│   │   ├── Deal.php
│   │   ├── Pipeline.php
│   │   ├── Stage.php
│   │   ├── Activity.php               # CRM-specific activities (calls, meetings)
│   │   ├── Tag.php
│   │   ├── CustomField.php
│   │   └── Segment.php
│   ├── Contracts/
│   │   ├── HasContacts.php            # Models with contact relations
│   │   └── Trackable.php              # Activity tracking
│   └── Events/
│       ├── ContactCreated.php
│       ├── DealWon.php
│       └── DealLost.php
├── database/
│   └── migrations/
│       ├── create_crm_contacts_table.php
│       ├── create_crm_companies_table.php
│       ├── create_crm_deals_table.php
│       └── create_crm_pipelines_table.php
└── module.json
    # dependencies: []
    # optional: ["Customers", "Billing", "Sales", "Helpdesk"]
    # provides: ["contacts", "companies", "deals", "pipelines"]
    # Tables: crm_contacts, crm_companies, crm_deals, etc.
    # Uses: app/Models/Notes (polymorphic)
```

### Sales Module [PLANNED]

```
modules/Sales/                         # 📊 SALES & QUOTES
├── app/
│   ├── Models/
│   │   ├── Quote.php
│   │   ├── QuoteItem.php
│   │   ├── Order.php
│   │   ├── OrderItem.php
│   │   ├── Discount.php
│   │   ├── Coupon.php
│   │   ├── SalesTeam.php
│   │   └── Commission.php
│   ├── Contracts/
│   │   ├── Quotable.php
│   │   └── Orderable.php
│   └── Events/
│       ├── QuoteSent.php
│       ├── QuoteAccepted.php
│       ├── OrderPlaced.php
│       └── OrderCompleted.php
├── database/
│   └── migrations/
│       ├── create_sales_quotes_table.php
│       ├── create_sales_orders_table.php
│       └── create_sales_coupons_table.php
└── module.json
    # dependencies: ["Billing"]
    # optional: ["CRM", "Inventory", "Catalog"]
    # provides: ["quotes", "orders", "discounts"]
    # Tables: sales_quotes, sales_orders, sales_coupons, etc.
```

### Inventory Module [PLANNED]

```
modules/Inventory/                     # 📦 INVENTORY MANAGEMENT
├── app/
│   ├── Models/
│   │   ├── Warehouse.php
│   │   ├── Location.php
│   │   ├── StockMove.php
│   │   ├── StockLevel.php
│   │   ├── Lot.php
│   │   ├── SerialNumber.php
│   │   └── Adjustment.php
│   └── Events/
│       ├── StockLow.php
│       └── StockMoved.php
├── database/
│   └── migrations/
│       ├── create_inventory_warehouses_table.php
│       ├── create_inventory_stock_levels_table.php
│       └── create_inventory_stock_moves_table.php
└── module.json
    # dependencies: ["Catalog"]
    # optional: ["Sales", "Purchases", "eCommerce"]
    # provides: ["warehouses", "stock", "inventory"]
    # Tables: inventory_warehouses, inventory_stock_levels, etc.
```

### eCommerce Module [PLANNED]

```
modules/eCommerce/                     # 🛒 ONLINE STORE (Shopify-like)
├── app/
│   ├── Models/
│   │   ├── Store.php
│   │   ├── Cart.php
│   │   ├── CartItem.php
│   │   ├── Checkout.php
│   │   ├── Wishlist.php
│   │   ├── Review.php
│   │   ├── ShippingMethod.php
│   │   ├── ShippingZone.php
│   │   └── Storefront.php
│   ├── Contracts/
│   │   └── Shippable.php
│   └── Events/
│       ├── CartAbandoned.php
│       ├── CheckoutCompleted.php
│       └── ReviewPosted.php
├── database/
│   └── migrations/
│       ├── create_ecommerce_stores_table.php
│       ├── create_ecommerce_carts_table.php
│       └── create_ecommerce_reviews_table.php
└── module.json
    # dependencies: ["Catalog", "Billing", "Sales"]
    # optional: ["Inventory", "CMS", "Shipping"]
    # provides: ["stores", "carts", "checkout", "reviews"]
    # Tables: ecommerce_stores, ecommerce_carts, ecommerce_reviews, etc.
```

### CMS Module [IMPLEMENTED]

```
modules/CMS/                           # 📝 CONTENT MANAGEMENT
├── app/
│   ├── Models/
│   │   ├── Page.php
│   │   ├── Post.php
│   │   ├── Menu.php
│   │   ├── MenuItem.php
│   │   ├── Form.php
│   │   ├── FormSubmission.php
│   │   ├── Theme.php
│   │   └── Widget.php
│   └── Contracts/
│       ├── HasContent.php
│       └── Themeable.php
├── database/
│   └── migrations/
│       ├── create_cms_pages_table.php
│       ├── create_cms_posts_table.php
│       └── create_cms_forms_table.php
└── module.json
    # dependencies: ["Platform"]
    # optional: ["eCommerce", "SEO"]
    # provides: ["pages", "posts", "menus", "themes", "forms"]
    # Tables: cms_pages, cms_posts, cms_menus, cms_forms, etc.
```

### Helpdesk Module [IMPLEMENTED]

```
modules/Helpdesk/                      # 🎫 SUPPORT TICKETS (IMPLEMENTED)
├── app/
│   ├── Definitions/
│   │   ├── DepartmentDefinition.php
│   │   └── TicketDefinition.php
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Models/
│   │   ├── Department.php
│   │   ├── Ticket.php
│   │   └── TicketReplies.php
│   ├── Providers/
│   │   ├── EventServiceProvider.php
│   │   ├── HelpdeskServiceProvider.php
│   │   └── RouteServiceProvider.php
│   └── Services/
│       ├── DepartmentService.php
│       └── TicketService.php
├── database/
│   ├── migrations/
│   │   ├── 1988_04_13_000001_create_helpdesk_departments_table.php
│   │   ├── 1988_04_13_000002_create_helpdesk_tickets_table.php
│   │   ├── 1988_04_13_000003_add_attachments_field_to_helpdesk_tickets_table.php
│   │   ├── 1988_04_13_000004_create_helpdesk_tickets_replies_table.php
│   │   └── 1988_04_13_000005_fix_helpdesk_ticket_audit_defaults.php
│   └── seeders/
│       ├── HelpdeskDatabaseSeeder.php
│       ├── HelpdeskTicketDepartmentSeeder.php
│       └── PermissionSeeder.php
└── module.json
```

### Projects Module [PLANNED]

```
modules/Projects/                      # 📋 PROJECT MANAGEMENT
├── app/
│   ├── Models/
│   │   ├── Project.php
│   │   ├── Task.php
│   │   ├── TaskList.php
│   │   ├── Milestone.php
│   │   ├── TimeEntry.php
│   │   ├── Sprint.php
│   │   └── Board.php
│   └── Events/
│       ├── TaskCompleted.php
│       ├── MilestoneReached.php
│       └── ProjectCompleted.php
├── database/
│   └── migrations/
│       ├── create_projects_projects_table.php
│       ├── create_projects_tasks_table.php
│       └── create_projects_time_entries_table.php
└── module.json
    # dependencies: []
    # optional: ["CRM", "HR", "Billing"]
    # provides: ["projects", "tasks", "time-tracking"]
    # Tables: projects_projects, projects_tasks, etc.
```

### HR Module [PLANNED]

```
modules/HR/                            # 👔 HUMAN RESOURCES
├── app/
│   ├── Models/
│   │   ├── Employee.php
│   │   ├── Department.php
│   │   ├── Position.php
│   │   ├── LeaveRequest.php
│   │   ├── LeaveType.php
│   │   ├── Attendance.php
│   │   ├── Expense.php
│   │   └── Contract.php
│   └── Events/
│       ├── EmployeeHired.php
│       ├── LeaveApproved.php
│       └── ExpenseSubmitted.php
├── database/
│   └── migrations/
│       ├── create_hr_employees_table.php
│       ├── create_hr_departments_table.php
│       └── create_hr_leave_requests_table.php
└── module.json
    # dependencies: []
    # optional: ["Payroll", "Projects", "Recruitment"]
    # provides: ["employees", "departments", "leaves", "attendance"]
    # Tables: hr_employees, hr_departments, hr_leave_requests, etc.
```

### Marketing Module [PLANNED]

```
modules/Marketing/                     # 📧 MARKETING AUTOMATION
├── app/
│   ├── Models/
│   │   ├── Campaign.php
│   │   ├── EmailTemplate.php
│   │   ├── Automation.php
│   │   ├── AutomationStep.php
│   │   ├── Newsletter.php
│   │   ├── Subscriber.php
│   │   ├── SocialPost.php
│   │   └── UTMLink.php
│   └── Events/
│       ├── EmailOpened.php
│       ├── LinkClicked.php
│       └── CampaignCompleted.php
├── database/
│   └── migrations/
│       ├── create_marketing_campaigns_table.php
│       ├── create_marketing_subscribers_table.php
│       └── create_marketing_automations_table.php
└── module.json
    # dependencies: []
    # optional: ["CRM", "CMS", "eCommerce"]
    # provides: ["campaigns", "email-templates", "automation"]
    # Tables: marketing_campaigns, marketing_subscribers, etc.
```

### Accounting Module [PLANNED]

```
modules/Accounting/                    # 📒 FULL ACCOUNTING
├── app/
│   ├── Models/
│   │   ├── Account.php
│   │   ├── JournalEntry.php
│   │   ├── JournalLine.php
│   │   ├── FiscalYear.php
│   │   ├── BankAccount.php
│   │   ├── BankTransaction.php
│   │   ├── Reconciliation.php
│   │   └── Budget.php
│   └── Events/
│       ├── JournalPosted.php
│       └── YearClosed.php
├── database/
│   └── migrations/
│       ├── create_accounting_accounts_table.php
│       ├── create_accounting_journal_entries_table.php
│       └── create_accounting_bank_accounts_table.php
└── module.json
    # dependencies: ["Billing"]
    # optional: ["Inventory", "Payroll", "Purchases"]
    # provides: ["chart-of-accounts", "journals", "reconciliation"]
    # Tables: accounting_accounts, accounting_journal_entries, etc.
```

### Purchases Module [PLANNED]

```
modules/Purchases/                     # 🛍️ PURCHASE ORDERS
├── app/
│   ├── Models/
│   │   ├── Vendor.php
│   │   ├── PurchaseOrder.php
│   │   ├── PurchaseOrderItem.php
│   │   ├── Bill.php
│   │   ├── BillItem.php
│   │   └── VendorPayment.php
│   └── Events/
│       ├── POCreated.php
│       ├── POReceived.php
│       └── BillPaid.php
├── database/
│   └── migrations/
│       ├── create_purchases_vendors_table.php
│       ├── create_purchases_purchase_orders_table.php
│       └── create_purchases_bills_table.php
└── module.json
    # dependencies: ["Billing", "Catalog"]
    # optional: ["Inventory", "Accounting"]
    # provides: ["vendors", "purchase-orders", "bills"]
    # Tables: purchases_vendors, purchases_purchase_orders, etc.
```

### Appointments Module [PLANNED]

```
modules/Appointments/                  # 📅 BOOKING & SCHEDULING
├── app/
│   ├── Models/
│   │   ├── Appointment.php
│   │   ├── Calendar.php
│   │   ├── TimeSlot.php
│   │   ├── BookingPage.php
│   │   ├── Resource.php
│   │   └── Reminder.php
│   └── Events/
│       ├── AppointmentBooked.php
│       ├── AppointmentCancelled.php
│       └── ReminderSent.php
├── database/
│   └── migrations/
│       ├── create_appointments_appointments_table.php
│       ├── create_appointments_calendars_table.php
│       └── create_appointments_time_slots_table.php
└── module.json
    # dependencies: []
    # optional: ["CRM", "CMS", "Billing"]
    # provides: ["appointments", "calendars", "booking-pages"]
    # Tables: appointments_appointments, appointments_calendars, etc.
```

### Documents Module [PLANNED]

```
modules/Documents/                     # 📄 DOCUMENT MANAGEMENT
├── app/
│   ├── Models/
│   │   ├── Document.php
│   │   ├── Folder.php
│   │   ├── DocumentVersion.php
│   │   ├── Signature.php
│   │   ├── Template.php
│   │   └── Share.php
│   └── Events/
│       ├── DocumentUploaded.php
│       ├── DocumentSigned.php
│       └── DocumentShared.php
├── database/
│   └── migrations/
│       ├── create_documents_documents_table.php
│       ├── create_documents_folders_table.php
│       └── create_documents_signatures_table.php
└── module.json
    # dependencies: []
    # optional: ["CRM", "HR", "Sales"]
    # provides: ["documents", "folders", "signatures", "templates"]
    # Tables: documents_documents, documents_folders, etc.
```

### Reporting Module [PLANNED]

```
modules/Reporting/                     # 📊 ANALYTICS & REPORTS
├── app/
│   ├── Models/
│   │   ├── Report.php
│   │   ├── Dashboard.php
│   │   ├── Widget.php
│   │   ├── SavedFilter.php
│   │   └── ExportJob.php
│   └── Services/
│       ├── ReportBuilder.php
│       └── DashboardService.php
├── database/
│   └── migrations/
│       ├── create_reporting_reports_table.php
│       ├── create_reporting_dashboards_table.php
│       └── create_reporting_widgets_table.php
└── module.json
    # dependencies: []
    # provides: ["reports", "dashboards", "analytics"]
    # Tables: reporting_reports, reporting_dashboards, etc.
```

### ReleaseManager Module [IMPLEMENTED] (Utility)

```
modules/ReleaseManager/                # 🚀 RELEASE MANAGEMENT
├── app/
│   ├── Definitions/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ReleaseController.php
│   │   │   └── Api/V1/ReleaseController.php
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Models/
│   ├── Providers/
│   └── Services/
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   ├── web.php
│   └── api.php
├── module.json
    # Utility module for release versions and secure distribution
    # API endpoint: /api/release-manager/v1/releases/latest-update/{type}/{packageIdentifier}
    # Protected by: release.api.key middleware (X-Release-Key)
```

---

## Cross-App Integration Paths

### Implemented Now

- `pkg.astero.net.in` release API endpoint is served by ReleaseManager:
    - `GET /api/release-manager/v1/releases/latest-update/{type}/{packageIdentifier}`
- Release API key validation middleware:
    - `app/Http/Middleware/EnsureReleaseApiKey.php`
- Platform provisioning and release update jobs are implemented in:
    - `modules/Platform/app/Jobs/ServerProvision.php`
    - `modules/Platform/app/Jobs/WebsiteProvision.php`
    - `modules/Platform/app/Jobs/ServerUpdateReleases.php`

### Pending

- External provisioning API for `my.astero.in -> platform.astero.net.in`
  should be added in `modules/Platform/routes/api.php` with API controllers.

---

## Table Naming Convention

**IMPORTANT:** All module tables MUST be prefixed with the module name in lowercase:

| Module       | Table Examples                                              |
| ------------ | ----------------------------------------------------------- |
| Customers    | `customers_customers`, `customers_customer_contacts`        |
| Platform     | `platform_websites`, `platform_servers`, `platform_domains` |
| Billing      | `billing_invoices`, `billing_payments`, `billing_taxes`     |
| CRM          | `crm_contacts`, `crm_companies`, `crm_deals`                |
| Sales        | `sales_quotes`, `sales_orders`, `sales_coupons`             |
| Inventory    | `inventory_warehouses`, `inventory_stock_levels`            |
| eCommerce    | `ecommerce_stores`, `ecommerce_carts`, `ecommerce_reviews`  |
| CMS          | `cms_pages`, `cms_posts`, `cms_menus`, `cms_forms`          |
| Helpdesk     | `helpdesk_tickets`, `helpdesk_slas`                         |
| Projects     | `projects_projects`, `projects_tasks`                       |
| HR           | `hr_employees`, `hr_departments`, `hr_leave_requests`       |
| Marketing    | `marketing_campaigns`, `marketing_subscribers`              |
| Accounting   | `accounting_accounts`, `accounting_journal_entries`         |
| Purchases    | `purchases_vendors`, `purchases_purchase_orders`            |
| Appointments | `appointments_appointments`, `appointments_calendars`       |
| Documents    | `documents_documents`, `documents_folders`                  |
| Reporting    | `reporting_reports`, `reporting_dashboards`                 |

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
})->comment('Stores all invoices generated by the billing module');
```

### Table & Column Comments

**Always add comments** to tables and columns to describe their purpose. Comments are metadata stored in the database schema.

```php
// Table comment
Schema::create('billing_invoices', function (Blueprint $table) {
    // ...
})->comment('Stores all invoices generated by the billing module');

// Column comments
$table->string('status')->comment('Invoice status: draft, sent, paid, overdue, cancelled');
$table->decimal('total', 10, 2)->comment('Total amount including taxes');
$table->json('metadata')->nullable()->comment('Additional invoice data as JSON');
$table->foreignId('customer_id')->nullable()
    ->constrained('customers_customers')
    ->comment('Reference to the customer who owns this invoice');
```

**Benefits:**

- Self-documenting database schema
- Helps developers understand columns without reading code
- Visible in database tools (phpMyAdmin, TablePlus, etc.)
- Useful for complex or non-obvious columns

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
