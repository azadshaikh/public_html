# Settings Migration PRD

## Summary

Migrate the Astero settings system to Inertia v3 + React. The current app has two settings controllers (`SettingsController` and `Masters\SettingsController`) that still render Blade views which no longer exist. The data layer (model, caching, observer, helpers, recache job) is fully functional and does not need changes.

This migration converts both controllers to Inertia rendering and builds a modern settings UI using a sidebar-within-content layout where each settings section is its own Inertia page.

## Problem

- Settings controllers reference Blade views that no longer exist — settings pages are completely broken.
- The old UI used horizontal tabs within a single page — all sections loaded at once.
- No permission granularity existed beyond the single `manage_system_settings` permission.

## Product goal

A clean, modern settings experience where:

- Each settings section is a standalone Inertia page with its own URL.
- A persistent sidebar nav groups sections visually.
- System Settings require the `manage_system_settings` permission.
- Master Settings require the `super_user` role (no permission — role-only access).
- CMS-dependent sections appear/disappear based on CMS module activation.
- Module settings (ChatBot, Billing, Orders, etc.) can extend the settings UI when their modules are migrated.

## Architecture

### Settings areas

There are two settings areas with different access models:

| Area | URL prefix | Access | Purpose |
|------|-----------|--------|---------|
| **System Settings** | `/settings` | `manage_system_settings` permission | App-level configuration available to admins |
| **Master Settings** | `/master-settings` | `super_user` role only | Infrastructure/platform settings for super users only |

### System Settings sections

| # | Section | Slug | CMS-dependent? | Storage | Key fields |
|---|---------|------|----------------|---------|------------|
| 1 | General | `general` | Yes | DB (`seo` group) + `.env` (`APP_NAME`) | `site_title`, `tagline` |
| 2 | Localization | `localization` | No | DB (`localization_*`) | `language`, `date_format`, `time_format`, `timezone` |
| 3 | Registration | `registration` | No | DB (`registration_*`) | `enable_registration`, `default_role`, `require_email_verification`, `auto_approve` |
| 4 | Social Authentication | `social-authentication` | No | `.env` only | `enable_social_authentication`, Google/GitHub OAuth credentials |
| 5 | Site Access Protection | `site-access-protection` | Yes | DB (`site_access_protection_*`) | `mode_enabled`, `password`, `protection_message` |
| 6 | Maintenance Mode | `maintenance` | Yes | DB (`maintenance_*`) | `mode_enabled`, `maintenance_mode_type`, `message`, `title` |
| 7 | Coming Soon Mode | `coming-soon` | Yes | DB (`coming_soon_*`) | `enabled`, `description` |
| 8 | Development Mode | `development` | Yes | DB + `.env` (`CDN_CACHE_HEADERS`) | `mode_enabled` |
| 9 | Import/Export | `import-export` | No | N/A | Export/import settings as JSON |

**Removed from System Settings:**
- Business Details → deferred to Customer module
- Email Settings → moved to Master Settings
- Media Settings → already in Master Settings
- Storage Settings → already in Master Settings

### Master Settings sections

| # | Section | Slug | Storage | Key fields |
|---|---------|------|---------|------------|
| 1 | App Settings | `app` | `.env` (`HOMEPAGE_REDIRECT_*`) | `homepage_redirect_enabled`, `homepage_redirect_slug` |
| 2 | Branding | `branding` | `.env` (`BRANDING_*`) | `brand_name`, `brand_website`, `logo`, `icon` |
| 3 | Login Security | `login-security` | DB (`login_security_*`) + `.env` (`ADMIN_SLUG`) | `admin_login_url_slug`, `limit_login_attempts_enabled`, `limit_login_attempts`, `lockout_time` |
| 4 | Email | `email` | DB + `.env` (`MAIL_*`) | `email_driver`, SMTP config, from name/address |
| 5 | Storage | `storage` | `.env` (`STORAGE_DISK`, `AWS_*`, `FTP_*`) | `storage_driver`, driver-specific fields |
| 6 | Media | `media` | `.env` (`MEDIA_*`) | File size limits, image quality, dimensions, trash config |
| 7 | Debug | `debug` | `.env` (`APP_DEBUG`, `DEBUGBAR_*`) | `enable_debugging`, `enable_debugging_bar`, `enable_html_minification` |

### Module Settings (future extension)

Each module contributes its own settings sections that appear when the module is active:

| Module | Sections | Permission |
|--------|----------|------------|
| ChatBot | General, Provider, Tools | `manage_chatbot_settings` |
| Billing | Invoice, Stripe | `manage_billing_settings` |
| Orders | Order Number | `manage_orders_settings` |
| Helpdesk | Prefix | `manage_helpdesk_settings` |
| Platform | General | `manage_platform_settings` |
| Agency | General, Platform | `manage_agency_settings` |
| CMS | SEO (7 sections), Integrations (7 sections), Default Pages | CMS permissions |

These will be built when their respective modules are migrated. The settings layout supports dynamic section registration via a config-driven convention.

## Frontend architecture

### SettingsLayout component

A shared layout component used by all settings pages:

```
┌─────────────────────────────────────────────────────┐
│ AppLayout (breadcrumbs, title, description)         │
│ ┌─────────────┬───────────────────────────────────┐ │
│ │ Sidebar Nav │ Content Area                      │ │
│ │             │                                   │ │
│ │ ● General   │  ┌─────────────────────────────┐  │ │
│ │ ● Local...  │  │ Card                        │  │ │
│ │ ● Regis...  │  │  Form fields for section    │  │ │
│ │ ● Social..  │  │                             │  │ │
│ │             │  │  [Save Settings]             │  │ │
│ │ CMS         │  └─────────────────────────────┘  │ │
│ │ ● Site...   │                                   │ │
│ │ ● Maint..   │                                   │ │
│ │ ● Coming..  │                                   │ │
│ │ ● Dev...    │                                   │ │
│ │             │                                   │ │
│ │ ● Import..  │                                   │ │
│ └─────────────┴───────────────────────────────────┘ │
└─────────────────────────────────────────────────────┘
```

- Left sidebar renders navigation links grouped by category.
- CMS-dependent sections are hidden when CMS module is inactive.
- On mobile, the sidebar collapses or stacks above content.
- Active section is highlighted based on current URL.

### Form pattern

Each settings page uses `useAppForm` with:
- `rememberKey` for draft persistence (e.g. `'settings.localization'`)
- `dirtyGuard` for unsaved changes protection
- Field-level validation where meaningful
- `successToast` on save for confirmation
- `setDefaultsOnSuccess: true` to clear dirty state after save

### Routing

Each section is a separate Inertia route:

```
GET  /settings                     → redirect to first visible section
GET  /settings/localization        → localization form
PUT  /settings/localization        → save localization
GET  /settings/registration        → registration form
PUT  /settings/registration        → save registration
...etc

GET  /master-settings              → redirect to first section
GET  /master-settings/app          → app settings form
PUT  /master-settings/app          → save app settings
GET  /master-settings/email        → email settings form
PUT  /master-settings/email        → save email settings
POST /master-settings/email/test   → send test email
POST /master-settings/storage/test → test storage connection
...etc

POST /settings/export              → export settings JSON
POST /settings/import              → import settings JSON
```

### Controller refactoring approach

Rather than the monolithic `index()` that loads all settings at once, each section gets its own controller method that returns only the data that section needs. The `update()` method stays mostly the same — it already dispatches by meta group.

**System Settings controller:**
- One method per section: `localization()`, `registration()`, `socialAuthentication()`, etc.
- Each returns `Inertia::render('settings/<section>', [...])` with section-specific data + sidebar nav config.
- `update()` preserved with `processMetaGroupSettings()` logic.
- New `index()` redirects to the first visible section.

**Master Settings controller:**
- Same pattern: `app()`, `branding()`, `loginSecurity()`, `email()`, `storage()`, `media()`, `debug()`.
- Authorization: `abort_unless(Auth::user()->isSuperUser(), 403)` — no permission check, role-based only.
- `update()` preserved with handler-based dispatch.

### Authorization

| Area | Check |
|------|-------|
| System Settings | `manage_system_settings` permission (already exists in PermissionSeeder) |
| Master Settings | `User::isSuperUser()` role check (no permission needed) |

The navigation config already gates the sidebar entries:
- Settings: `'permission' => 'manage_system_settings'`
- Master Settings: `'permission' => 'manage_master_settings'` → change to role-based gating

## Implementation phases

### Phase 1 — Backend foundation

1. Refactor `SettingsController`:
   - Change `index()` to redirect to first visible section.
   - Add per-section methods returning Inertia responses.
   - Keep `update()`, `sendTestMail()`, `exportSettings()`, `importSettings()`.
   - Remove Blade view references.

2. Refactor `Masters\SettingsController`:
   - Change `settings()` to redirect to first section.
   - Add per-section methods returning Inertia responses.
   - Add `email()` section (moved from System Settings).
   - Change authorization from `manage_system_settings` permission to `isSuperUser()` check.
   - Move `sendTestMail()` here (from System Settings since Email is now in Master Settings).
   - Keep `update()` and `testStorageConnection()`.
   - Remove Blade view references.

3. Create per-section FormRequest classes for validation.

4. Update routes in `web.php` for per-section routing.

5. Update navigation config for new route names.

### Phase 2 — Frontend foundation

6. Create `SettingsLayout` component with sidebar nav.
7. Create `SettingsNav` component (vertical link list with grouping + active state).
8. Create shared helpers: `SettingsCard`, `EnvFieldNotice` (info badge for `.env`-stored fields).

### Phase 3 — System Settings pages

Build each section as a React page under `resources/js/pages/settings/`:

9. `localization.tsx` — language, date format, time format, timezone selects
10. `registration.tsx` — enable switch, role select, verification/approval toggles
11. `social-authentication.tsx` — provider enable switches + OAuth credential inputs
12. `general.tsx` — CMS-dependent: site title, tagline
13. `site-access-protection.tsx` — CMS-dependent: enable, password, message
14. `maintenance.tsx` — CMS-dependent: enable, type select, title, message
15. `coming-soon.tsx` — CMS-dependent: enable, description
16. `development.tsx` — CMS-dependent: CDN cache toggle
17. `import-export.tsx` — export button + import file upload

### Phase 4 — Master Settings pages

Build each section under `resources/js/pages/master-settings/`:

18. `app.tsx` — homepage redirect toggle + slug
19. `branding.tsx` — name, website, logo/icon upload
20. `login-security.tsx` — admin slug, rate limiting config
21. `email.tsx` — driver select, SMTP config, from name/address, test email button
22. `storage.tsx` — driver select, provider config, test connection button
23. `media.tsx` — file limits, image quality, dimensions, trash config
24. `debug.tsx` — debug toggles

### Phase 5 — Tests

25. PHPUnit tests for each System Settings section (show + update + validation).
26. PHPUnit tests for each Master Settings section (show + update + super_user enforcement).
27. Test CMS-dependent section visibility.
28. Test import/export flow.
29. Test send-test-mail flow.
30. Test storage connection test flow.

### Phase 6 — Cleanup

31. Run Pint on all modified PHP files.
32. Run ESLint + TypeScript checks.
33. Remove dead Blade view references.
34. Update Wayfinder-generated routes.

## Scope summary

| Category | Count |
|----------|-------|
| Controllers refactored | 2 |
| React pages | ~16 |
| Layout components | 2 (SettingsLayout, SettingsNav) |
| FormRequest classes | ~9 |
| Test classes | ~16 |
| Route updates | ~30 routes |

## Non-goals

- Migrating module-specific settings (ChatBot, Billing, etc.) — those come with their modules.
- Business Details section — deferred to Customer module.
- Database schema changes — the `settings` table is already correct.
- Changing the settings storage/caching architecture — it works well.

## Success criteria

- All system settings sections render and save correctly via Inertia.
- All master settings sections render and save correctly via Inertia.
- Master Settings are only accessible to super users.
- System Settings respect `manage_system_settings` permission.
- CMS-dependent sections hide when CMS module is inactive.
- No Blade view references remain in settings controllers.
- All settings changes trigger proper cache invalidation and recache.
- Test coverage for all sections.
