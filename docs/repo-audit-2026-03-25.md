# Repo Audit Report

Date: 2026-03-25

Scope: repo-wide audit using local inspection, Laravel/module diagnostics, TypeScript checking, and parallel subagents for backend, modules, frontend, and test coverage.

## Executive Summary

The repo has multiple confirmed, user-visible breakpoints:

- A state-changing cache clear endpoint is exposed as an unauthenticated `GET`.
- The Subscriptions and Agency modules have permission wiring drift that can block legitimate access and bypass intended authorization.
- The frontend is not green under strict TypeScript; several Platform, CMS, Agency, Auth, and ChatBot paths are currently not buildable.
- Tests miss several high-risk areas entirely, including Billing, social auth flows, agency webhooks, and CSRF protection.
- There is broad best-practice drift around inline validation, `request()->all()`, secret exposure through Inertia props/flashes, and environment/config handling.

## Verification Run

Commands used:

- `php artisan module:inspect --json --fail-on-issues`
- `php artisan scaffold:doctor`
- `pnpm types:check`
- `php artisan route:list -v --name=cache.clear`
- targeted `rg`, `nl`, and source inspection

Observed results:

- `module:inspect` returned no structural manifest/provider issues.
- `scaffold:doctor` returned 17 issues, including missing Agency and Subscriptions ability keys plus definitions/controllers that currently hard-fail when their option lists touch a live database.
- `pnpm types:check` failed with a large set of concrete frontend/compiler errors.

## Findings

### Critical

1. Unauthenticated cache-clearing endpoint allows any visitor to trigger a mutating server-side recache action.
   Evidence: [routes/web.php#L75](/home/xipone/web/one.xip.net.in/public_html/current/routes/web.php#L75), [app/Http/Controllers/DashboardController.php#L111](/home/xipone/web/one.xip.net.in/public_html/current/app/Http/Controllers/DashboardController.php#L111)
   Why it matters: this is both an authorization gap and incorrect HTTP semantics for a state-changing operation.

2. Subscriptions CRUD is wired to permission names that cannot exist on a clean install.
   Evidence: [modules/Subscriptions/app/Definitions/PlanDefinition.php#L22](/home/xipone/web/one.xip.net.in/public_html/current/modules/Subscriptions/app/Definitions/PlanDefinition.php#L22), [modules/Subscriptions/app/Definitions/SubscriptionDefinition.php#L23](/home/xipone/web/one.xip.net.in/public_html/current/modules/Subscriptions/app/Definitions/SubscriptionDefinition.php#L23), [modules/Subscriptions/database/seeders/PermissionSeeder.php#L19](/home/xipone/web/one.xip.net.in/public_html/current/modules/Subscriptions/database/seeders/PermissionSeeder.php#L19)
   Why it matters: scaffold middleware expects names like `view_subscriptions.plans`, but the seeded permissions are `view_plans` and `view_subscriptions`. CRUD access will drift or fail.

3. Frontend compilation is currently broken in multiple product areas.
   Evidence: `pnpm types:check`
   Highest-signal failures:
   - Platform module import drift and missing `Link` import: [modules/Platform/resources/js/pages/platform/agencies/components/agency-show-tabs.tsx#L42](/home/xipone/web/one.xip.net.in/public_html/current/modules/Platform/resources/js/pages/platform/agencies/components/agency-show-tabs.tsx#L42), [modules/Platform/resources/js/pages/platform/agencies/components/agency-show-tabs.tsx#L446](/home/xipone/web/one.xip.net.in/public_html/current/modules/Platform/resources/js/pages/platform/agencies/components/agency-show-tabs.tsx#L446)
   - CMS references nonexistent frontend symbols/paths: [modules/CMS/resources/js/components/theme-editor/editor-dialogs.tsx#L1](/home/xipone/web/one.xip.net.in/public_html/current/modules/CMS/resources/js/components/theme-editor/editor-dialogs.tsx#L1), [modules/CMS/resources/js/pages/cms/menus/create.tsx#L31](/home/xipone/web/one.xip.net.in/public_html/current/modules/CMS/resources/js/pages/cms/menus/create.tsx#L31), [modules/CMS/resources/js/pages/cms/menus/edit.tsx#L66](/home/xipone/web/one.xip.net.in/public_html/current/modules/CMS/resources/js/pages/cms/menus/edit.tsx#L66)
   Why it matters: strict TS is not a theoretical warning here; these are build blockers.

### High

4. Subscription lifecycle actions bypass their own granular permissions.
   Evidence: [modules/Subscriptions/app/Http/Controllers/SubscriptionController.php#L26](/home/xipone/web/one.xip.net.in/public_html/current/modules/Subscriptions/app/Http/Controllers/SubscriptionController.php#L26), [modules/Subscriptions/app/Http/Controllers/SubscriptionController.php#L35](/home/xipone/web/one.xip.net.in/public_html/current/modules/Subscriptions/app/Http/Controllers/SubscriptionController.php#L35), [modules/Subscriptions/app/Http/Controllers/SubscriptionController.php#L49](/home/xipone/web/one.xip.net.in/public_html/current/modules/Subscriptions/app/Http/Controllers/SubscriptionController.php#L49), [modules/Subscriptions/app/Http/Controllers/SubscriptionController.php#L63](/home/xipone/web/one.xip.net.in/public_html/current/modules/Subscriptions/app/Http/Controllers/SubscriptionController.php#L63), [modules/Subscriptions/config/abilities.php#L16](/home/xipone/web/one.xip.net.in/public_html/current/modules/Subscriptions/config/abilities.php#L16)
   Why it matters: users with general subscription access can hit `cancel`, `resume`, and `pause` without `cancel_subscriptions`, `resume_subscriptions`, or `pause_subscriptions`.

5. Agency declares and enforces permissions that it never seeds.
   Evidence: [modules/Agency/config/abilities.php#L4](/home/xipone/web/one.xip.net.in/public_html/current/modules/Agency/config/abilities.php#L4), [modules/Agency/config/navigation.php#L72](/home/xipone/web/one.xip.net.in/public_html/current/modules/Agency/config/navigation.php#L72), [modules/Agency/app/Http/Controllers/SettingsController.php#L22](/home/xipone/web/one.xip.net.in/public_html/current/modules/Agency/app/Http/Controllers/SettingsController.php#L22), [modules/Agency/app/Definitions/WebsiteManageDefinition.php#L27](/home/xipone/web/one.xip.net.in/public_html/current/modules/Agency/app/Definitions/WebsiteManageDefinition.php#L27), [modules/Agency/database/seeders/DatabaseSeeder.php](/home/xipone/web/one.xip.net.in/public_html/current/modules/Agency/database/seeders/DatabaseSeeder.php)
   Why it matters: fresh installs will have broken Agency settings/navigation and incomplete website management authorization.

6. Agency website-management custom actions are only role-gated, not permission-gated.
   Evidence: [modules/Agency/routes/web.php#L129](/home/xipone/web/one.xip.net.in/public_html/current/modules/Agency/routes/web.php#L129), [modules/Agency/app/Http/Controllers/WebsiteManageController.php#L41](/home/xipone/web/one.xip.net.in/public_html/current/modules/Agency/app/Http/Controllers/WebsiteManageController.php#L41), [modules/Agency/app/Http/Controllers/WebsiteManageController.php#L110](/home/xipone/web/one.xip.net.in/public_html/current/modules/Agency/app/Http/Controllers/WebsiteManageController.php#L110), [modules/Agency/app/Http/Controllers/WebsiteManageController.php#L144](/home/xipone/web/one.xip.net.in/public_html/current/modules/Agency/app/Http/Controllers/WebsiteManageController.php#L144), [modules/Agency/app/Http/Controllers/WebsiteManageController.php#L202](/home/xipone/web/one.xip.net.in/public_html/current/modules/Agency/app/Http/Controllers/WebsiteManageController.php#L202)
   Why it matters: custom lifecycle actions do not inherit scaffold permission protection automatically.

7. CSRF exemptions for media endpoints can drift from the actual admin slug when config is cached or customized.
   Evidence: [bootstrap/app.php#L39](/home/xipone/web/one.xip.net.in/public_html/current/bootstrap/app.php#L39), [bootstrap/app.php#L43](/home/xipone/web/one.xip.net.in/public_html/current/bootstrap/app.php#L43), [routes/web.php#L62](/home/xipone/web/one.xip.net.in/public_html/current/routes/web.php#L62), [routes/web.php#L519](/home/xipone/web/one.xip.net.in/public_html/current/routes/web.php#L519), [routes/web.php#L522](/home/xipone/web/one.xip.net.in/public_html/current/routes/web.php#L522)
   Why it matters: a cached-config deployment with a non-default admin slug can unexpectedly break uploads or metadata updates with CSRF failures.

8. Secrets are exposed in frontend props and flashed input instead of being masked or write-only.
   Evidence: [app/Http/Controllers/SettingsController.php#L126](/home/xipone/web/one.xip.net.in/public_html/current/app/Http/Controllers/SettingsController.php#L126), [app/Http/Controllers/SettingsController.php#L130](/home/xipone/web/one.xip.net.in/public_html/current/app/Http/Controllers/SettingsController.php#L130), [app/Http/Controllers/SettingsController.php#L250](/home/xipone/web/one.xip.net.in/public_html/current/app/Http/Controllers/SettingsController.php#L250), [app/Http/Controllers/SettingsController.php#L297](/home/xipone/web/one.xip.net.in/public_html/current/app/Http/Controllers/SettingsController.php#L297), [app/Http/Controllers/Masters/SettingsController.php#L139](/home/xipone/web/one.xip.net.in/public_html/current/app/Http/Controllers/Masters/SettingsController.php#L139), [app/Http/Controllers/Masters/SettingsController.php#L171](/home/xipone/web/one.xip.net.in/public_html/current/app/Http/Controllers/Masters/SettingsController.php#L171), [app/Http/Controllers/Masters/SettingsController.php#L180](/home/xipone/web/one.xip.net.in/public_html/current/app/Http/Controllers/Masters/SettingsController.php#L180)
   Why it matters: this increases blast radius for accidental disclosure in browser state, error flashes, and dev tooling.

9. Billing has no module tests despite payment-critical behavior.
   Evidence: [modules/Billing](/home/xipone/web/one.xip.net.in/public_html/current/modules/Billing), [modules/Billing/app/Http/Controllers/StripeWebhookController.php#L37](/home/xipone/web/one.xip.net.in/public_html/current/modules/Billing/app/Http/Controllers/StripeWebhookController.php#L37), [modules/Billing/app/Services/BillingService.php#L241](/home/xipone/web/one.xip.net.in/public_html/current/modules/Billing/app/Services/BillingService.php#L241)
   Why it matters: webhook verification, idempotency, refunds, renewal/cancellation logic, and coupon handling can regress silently.

10. The base test harness disables CSRF globally, so the suite cannot detect missing or broken CSRF protection.
    Evidence: [tests/TestCase.php#L10](/home/xipone/web/one.xip.net.in/public_html/current/tests/TestCase.php#L10), [tests/TestCase.php#L14](/home/xipone/web/one.xip.net.in/public_html/current/tests/TestCase.php#L14)
    Why it matters: production-only failures or missing CSRF middleware can pass the suite.

11. Platform’s “create website from order” flow points at a nonexistent module namespace.
    Evidence: [modules/Platform/app/Http/Controllers/WebsiteController.php#L93](/home/xipone/web/one.xip.net.in/public_html/current/modules/Platform/app/Http/Controllers/WebsiteController.php#L93), [modules/Platform/routes/web.php#L240](/home/xipone/web/one.xip.net.in/public_html/current/modules/Platform/routes/web.php#L240)
    Why it matters: the flow will fail even when the Orders module is installed.

12. Shared frontend helper contracts have drifted from call sites.
    Evidence: `pnpm types:check`
    Representative failures:
    - `useAppForm` contract drift: [resources/js/pages/auth/confirm-password.tsx](/home/xipone/web/one.xip.net.in/public_html/current/resources/js/pages/auth/confirm-password.tsx), [resources/js/pages/auth/reset-password.tsx](/home/xipone/web/one.xip.net.in/public_html/current/resources/js/pages/auth/reset-password.tsx), [resources/js/pages/auth/login.tsx](/home/xipone/web/one.xip.net.in/public_html/current/resources/js/pages/auth/login.tsx)
    - outdated `router.reload()` options: [modules/Platform/resources/js/pages/platform/agencies/components/show-shared.tsx#L197](/home/xipone/web/one.xip.net.in/public_html/current/modules/Platform/resources/js/pages/platform/agencies/components/show-shared.tsx#L197), [resources/js/pages/notifications/index.tsx#L99](/home/xipone/web/one.xip.net.in/public_html/current/resources/js/pages/notifications/index.tsx#L99)
    Why it matters: the codebase is still carrying assumptions from older/beta Inertia helper APIs.

13. `scaffold:doctor` is currently red because several scaffold definitions perform live database work during controller resolution and because ability contracts are incomplete.
    Evidence: `php artisan scaffold:doctor`
    Representative sources:
    - missing abilities: [modules/Agency/config/abilities.php#L4](/home/xipone/web/one.xip.net.in/public_html/current/modules/Agency/config/abilities.php#L4), [modules/Subscriptions/config/abilities.php#L4](/home/xipone/web/one.xip.net.in/public_html/current/modules/Subscriptions/config/abilities.php#L4)
    - DB-coupled filter/options logic: [modules/Platform/app/Definitions/AgencyDefinition.php#L110](/home/xipone/web/one.xip.net.in/public_html/current/modules/Platform/app/Definitions/AgencyDefinition.php#L110), [modules/Platform/app/Definitions/DomainDefinition.php#L86](/home/xipone/web/one.xip.net.in/public_html/current/modules/Platform/app/Definitions/DomainDefinition.php#L86), [modules/Platform/app/Definitions/ServerDefinition.php#L102](/home/xipone/web/one.xip.net.in/public_html/current/modules/Platform/app/Definitions/ServerDefinition.php#L102), [modules/Helpdesk/app/Definitions/DepartmentDefinition.php#L78](/home/xipone/web/one.xip.net.in/public_html/current/modules/Helpdesk/app/Definitions/DepartmentDefinition.php#L78), [app/Services/EmailLogService.php#L80](/home/xipone/web/one.xip.net.in/public_html/current/app/Services/EmailLogService.php#L80)
    Why it matters: the project’s own scaffold validation is not green, which usually means generator-backed CRUD will keep drifting.

### Medium

14. Raw exception messages are sent back to browsers in several flows.
    Evidence: [app/Http/Controllers/Auth/SocialLoginController.php#L151](/home/xipone/web/one.xip.net.in/public_html/current/app/Http/Controllers/Auth/SocialLoginController.php#L151), [app/Http/Controllers/Profile/ProfileController.php#L399](/home/xipone/web/one.xip.net.in/public_html/current/app/Http/Controllers/Profile/ProfileController.php#L399), [app/Http/Controllers/Profile/ProfileController.php#L457](/home/xipone/web/one.xip.net.in/public_html/current/app/Http/Controllers/Profile/ProfileController.php#L457), [app/Http/Controllers/QueueMonitor/QueueMonitorController.php#L140](/home/xipone/web/one.xip.net.in/public_html/current/app/Http/Controllers/QueueMonitor/QueueMonitorController.php#L140), [app/Http/Controllers/QueueMonitor/QueueMonitorController.php#L181](/home/xipone/web/one.xip.net.in/public_html/current/app/Http/Controllers/QueueMonitor/QueueMonitorController.php#L181), [app/Http/Controllers/UserController.php#L528](/home/xipone/web/one.xip.net.in/public_html/current/app/Http/Controllers/UserController.php#L528)
    Why it matters: provider, queue, and internal implementation details can leak to end users.

15. Social auth behavior is largely untested beyond page rendering.
    Evidence: [tests/Feature/Account/SocialLoginsPageTest.php#L15](/home/xipone/web/one.xip.net.in/public_html/current/tests/Feature/Account/SocialLoginsPageTest.php#L15), [app/Http/Controllers/Auth/SocialLoginController.php#L50](/home/xipone/web/one.xip.net.in/public_html/current/app/Http/Controllers/Auth/SocialLoginController.php#L50), [app/Services/SocialLoginService.php#L23](/home/xipone/web/one.xip.net.in/public_html/current/app/Services/SocialLoginService.php#L23)
    Why it matters: callback state, missing-email handling, linking, suspension checks, and avatar download failures can regress unnoticed.

16. Agency webhook delivery has no behavioral test coverage.
    Evidence: [modules/Platform/app/Jobs/SendAgencyWebhook.php#L50](/home/xipone/web/one.xip.net.in/public_html/current/modules/Platform/app/Jobs/SendAgencyWebhook.php#L50), [modules/Platform/app/Jobs/SendAgencyWebhook.php#L130](/home/xipone/web/one.xip.net.in/public_html/current/modules/Platform/app/Jobs/SendAgencyWebhook.php#L130)
    Why it matters: broken signatures, retry semantics, SSL behavior, or silent delivery failures will ship without a regression signal.

17. Test coverage leans too heavily on source-inspection tests instead of executable behavior.
    Evidence: [modules/Platform/tests/Unit/ServerProvisionHestiaInstallGuardTest.php#L11](/home/xipone/web/one.xip.net.in/public_html/current/modules/Platform/tests/Unit/ServerProvisionHestiaInstallGuardTest.php#L11), [modules/Platform/tests/Unit/WebsiteDeleteCleanupFlowTest.php#L11](/home/xipone/web/one.xip.net.in/public_html/current/modules/Platform/tests/Unit/WebsiteDeleteCleanupFlowTest.php#L11)
    Why it matters: harmless refactors can fail tests while runtime regressions still pass.

18. Widespread Laravel validation/data-handling best-practice drift remains in controllers.
    Evidence:
    - inline validation appears in 68 locations from `rg -- '$request->validate(' app modules`
    - representative example: [modules/Agency/app/Http/Controllers/TicketController.php#L53](/home/xipone/web/one.xip.net.in/public_html/current/modules/Agency/app/Http/Controllers/TicketController.php#L53)
    - raw request payload use: [modules/CMS/app/Http/Controllers/MenuController.php#L325](/home/xipone/web/one.xip.net.in/public_html/current/modules/CMS/app/Http/Controllers/MenuController.php#L325)
    Why it matters: the repo guidance calls for Form Requests and validated payloads instead of controller-local validation and blanket request access.

19. `env()` is still used outside configuration files.
    Evidence: [bootstrap/app.php#L39](/home/xipone/web/one.xip.net.in/public_html/current/bootstrap/app.php#L39), [app/Support/Debugbar/OpenStorageResolver.php#L17](/home/xipone/web/one.xip.net.in/public_html/current/app/Support/Debugbar/OpenStorageResolver.php#L17)
    Why it matters: this can drift from cached config behavior and violates the project’s own guidance.

20. `Notification::getTableColumns()` is MySQL-specific in a PostgreSQL-first app.
    Evidence: [app/Models/Notification.php#L214](/home/xipone/web/one.xip.net.in/public_html/current/app/Models/Notification.php#L214)
    Why it matters: `SHOW COLUMNS` will fail on PostgreSQL the moment this path is exercised.

## Best-Practice Alignment

Areas where the repo is currently out of line with the loaded Laravel best-practices guidance:

- Authorization: multiple custom endpoints are not protected at the same granularity as declared permissions.
- Validation: Form Request usage is inconsistent; many controllers still validate inline.
- Secrets handling: sensitive settings are passed through props and flashed input instead of masked/write-only handling.
- Configuration: `env()` is used outside config.
- Testing: high-risk flows and modules are untested; CSRF is disabled across the suite; many tests inspect source instead of behavior.
- Architecture: scaffold definitions and related services are doing live database work during structural validation, which makes the generator/doctor workflow brittle.
- Frontend Inertia usage: helper APIs have drifted from the installed beta versions, especially around `router.reload()` and `useAppForm`.

## Suggested Remediation Order

1. Lock down `cache.clear` immediately: require auth/authorization and move it to `POST`.
2. Fix module permission contracts first: Subscriptions prefixes, Agency seeders, and custom action authorization.
3. Get `pnpm types:check` green, starting with Platform import fixes, CMS broken imports/symbols, and auth helper drift.
4. Stop exposing secrets in props/flashes and normalize write-only settings flows.
5. Make `scaffold:doctor` green by removing DB-coupled option lookups from structural resolution paths and fixing missing ability declarations.
6. Add tests for Billing, social auth callbacks/linking, agency webhooks, and CSRF-sensitive flows.

## Notes

- This report is intentionally prioritized toward confirmed breakages and risk, not style-only nits.
- Subagent findings were used for backend, module, frontend, and test-coverage slices, then cross-checked locally where needed.
