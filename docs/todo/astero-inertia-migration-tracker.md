# Astero to Inertia Migration Tracker

## Status legend

- [ ] Not started
- [~] In progress
- [x] Completed
- [!] Blocked

## Phase 0 — Alignment and guardrails

- [x] Confirm migration direction: current app remains target platform
- [x] Confirm `Unpoly` will not be migrated as-is
- [x] Confirm migration strategy: shared app first, then modules one by one
- [x] Confirm the custom module runtime remains the target runtime
- [x] Confirm module migration loading and root module seeding orchestration stay in place
- [x] Confirm current starter auth/schema choices are replaceable if they do not fit the Astero-aligned target
- [x] Review and approve PRD
- [x] Exclude legacy groups CRUD/features from current migration scope
- [x] Split media into a dedicated migration track
- [x] Insert a database baseline rebase before CRUD-by-CRUD migration
- [x] Freeze feature work on current local demo/testing modules except for migration-platform support
- [ ] Finalize module migration order

## Phase 1 — Engineering quality baseline

- [x] Add `PHPStan`
- [x] Add `Larastan`
- [x] Add `Rector`
- [x] Add composer scripts for static analysis and refactoring
- [x] Add initial static-analysis config with pragmatic ignores/baseline
- [x] Add CI/local check flow for Pint + PHPUnit + PHPStan
- [x] Run first analysis pass and capture initial debt snapshot

### Phase 1 snapshot

- [x] Initial `PHPStan` pass executed
- [x] Initial snapshot recorded: 57 errors at level 5
- [x] Triage snapshot into quick fixes vs deferred cleanup
- [x] Resolve initial level 5 `PHPStan` errors
- [x] Review and correct unsafe `Rector` changes after first run

## Phase 2 — Shared foundation inventory

- [x] Inventory Astero `app/Contracts`
- [x] Inventory Astero `app/Enums`
- [x] Inventory Astero `app/Traits`
- [x] Inventory Astero `app/Support`
- [x] Inventory Astero `app/Services`
- [x] Inventory shared models and polymorphic systems
- [x] Classify each item as port / refactor / drop / defer
- [x] Define dependency map from modules to shared foundation
- [x] Inventory and decide module navigation conventions early with shared foundation work
- [x] Explicitly mark legacy groups CRUD/features as dropped unless a real dependency appears

### Phase 2 findings

- Contracts inventory is small: `ScaffoldServiceInterface` should be refactored away into the new `Inertia` CRUD foundation, while `MonitoredJobContract` can stay deferred until queue-monitoring work is real.
- Enums split cleanly into three buckets: port/refactor later for shared behavior (`ActivityAction`, `NoteType`, `NoteVisibility`, notification enums), defer until their track starts (`MediaUploadErrorType`, `MonitorStatus`), and avoid blindly porting Astero's generic `Status` enum where the new app already prefers explicit booleans or domain-specific states.
- Traits and support classes confirm that Astero's most reusable shared systems are activity, addresses, metadata, notes, revisions, and cache helpers. `Support\Unpoly` is dropped, monitoring/media helpers are deferred, and scaffold helpers are refactor targets rather than direct ports.
- Shared model inventory shows the main polymorphic foundations are `Address`, `Note`, `ActivityLog`, `CustomMedia`, `Revision*`, and metadata/settings models. These remain shared-foundation candidates, but media still stays on its dedicated Phase 7 track.
- The first real module dependency map is now clear from `Customers`: it depends on shared user/role assignment, addresses, metadata, notes/activity, optional media attachment, and integration contracts for billing/subscriptions. Its legacy `App\Scaffold\*` stack, Blade views, and scaffold services should be rebuilt as `Inertia` patterns instead of ported.
- Customers also uses legacy navigation configuration in `tmp/astero/modules/Customers/config/navigation.php`, so navigation conventions should be decided during shared-foundation inventory instead of waiting for later UI implementation.
- Legacy groups CRUD remains dropped. `Customers` references `GroupItem`, but that should be replaced with cleaner module-owned enums or lookups unless a real cross-module dependency proves otherwise.

## Phase 3 — Database baseline rebase

- [ ] Inventory current application migrations and classify each as keep / rewrite / drop
- [ ] Inventory current application seeders and classify each as keep / rewrite / drop
- [ ] Inventory Astero migrations by business domain and dependency order
- [ ] Inventory Astero seeders by bootstrap / demo / import purpose
- [ ] Define the merged target baseline schema for the new app
- [ ] Define the merged target seeding order for the new app
- [ ] Preserve root `DatabaseSeeder` module orchestration while rewriting app-level seeding
- [ ] Preserve module migration loading in module service providers
- [ ] Have Astero migration/seeder files moved into the repo for reconciliation
- [ ] Make `php artisan migrate:fresh --seed` pass against the rebased schema

## Phase 4 — Identity and authorization foundation

- [x] Inventory Astero role/permission architecture, seeders, guards, and UI flows
- [~] Decide target authorization package/pattern for the Astero-aligned app
- [ ] Define role model scope and initial seed data for the rebased app
- [ ] Define permission naming convention for application and module features
- [ ] Reconcile existing roles/users CRUD with the rebased schema
- [ ] Reconcile permission assignment/editing flow with the rebased schema
- [ ] Reconcile user-to-role assignment flow where needed
- [ ] Wire middleware/policy usage into the rebased app
- [ ] Add tests for the finalized roles/permissions approach
- [ ] Run Pint, PHPUnit, and static analysis

## Phase 5 — Inertia-native platform foundation

- [x] Define target architecture for shared module-friendly CRUD patterns
- [x] Decide what survives from Astero scaffold design
- [~] Build Inertia-native CRUD foundation for forms, tables, filters, actions, stats, and detail pages → [scaffold-inertia-crud-tracker.md](scaffold-inertia-crud-tracker.md)
- [x] Standardize route, request, resource, and page conventions for modules
- [x] Define shared layout and module-shell conventions

### Phase 5 decisions

- Backend routes stay Laravel-first and Wayfinder-backed: each migrated module should expose named web routes, use controller actions instead of ad hoc closures, and regenerate typed helpers for frontend links and forms.
- Server responses should prefer a consistent pattern: index pages return filter state, lightweight stats, list rows, and option sets; create/edit pages return focused form payloads; destructive actions redirect with flash status/error messages.
- Validation should stay in dedicated Form Request classes, not inline controller validation. Request classes should normalize booleans, enums, and IDs in `prepareForValidation()` so React pages receive predictable shapes.
- Frontend page structure should follow the current `roles` and `users` pattern: `index.tsx`, `create.tsx`, and `edit.tsx` pages under `resources/js/pages/<module>/`, with larger forms extracted into `resources/js/components/<module>/`.
- Page chrome should continue using `AppLayout`, shared breadcrumbs, page title/description, and optional `headerActions`. The layout shell is already established through `AppShell`, `AppSidebar`, `AppContent`, and `AppPageHeader`.
- Module navigation should plug into one shared sidebar model: one top-level module node, child entries for index/create flows where needed, permission-gated visibility, and active-state detection from both URL and Inertia component namespace.
- `Inertia` forms should prefer Wayfinder form objects or `useForm` submit actions. Use `Link` for internal navigation, `router.*` for destructive or imperative actions, and shared flash/error alerts near the top of each page.
- Index pages should use a consistent content order: stats row, filter card, flash/error alerts, then registry/detail table card. Empty states should use the shared `Empty` primitives.
- Astero scaffold artifacts that survive conceptually are filters, columns, status tabs, stats, and action groupings. They should survive only as UX ideas, not as direct ports of `App\Scaffold\*`, Blade partials, or scaffold service contracts.
- Initial shared resource primitives are now extracted from `roles` and `users`: reusable stat cards, section cards, and feedback alerts under `resources/js/components/resource/`.
- Shared CRUD foundation work is still pending implementation. When built, it should extract repeated patterns from current `roles` and `users` pages into reusable `Inertia`/React primitives instead of reproducing Astero's scaffold runtime.

## Phase 6 — Shared foundation migration

- [ ] Port core enums
- [ ] Port core contracts
- [ ] Port reusable traits
- [ ] Port reusable support helpers/services
- [ ] Port shared base models/polymorphic systems where still valid
- [ ] Add tests for migrated shared foundation
- [ ] Run Pint, PHPUnit, and static analysis

## Phase 7 — Media migration foundation

- [ ] Inventory Astero media model, usage patterns, and upload/browse flows
- [ ] Decide target media library/storage strategy for the new app
- [ ] Define Inertia-native media UX and attachment patterns
- [ ] Add tests for the chosen media foundation

## Phase 8 — Module migration template

- [ ] Use one small real module as migration template
- [ ] Produce module inventory template: models, migrations, routes, services, UI, tests, dependencies
- [ ] Define per-module acceptance checklist
- [ ] Define feature parity checklist versus Astero

## Phase 9 — Module migrations

Note: the modules currently present in this repository remain test/demo fixtures only. Do not expand them with new product behavior while planning or executing Astero module migrations.

### Recommended order

- [ ] `Customers`
- [ ] `Subscriptions`
- [ ] `Billing`
- [ ] `Orders` or merge into `Billing` if cleaner
- [ ] `Agency`
- [ ] `ReleaseManager`
- [ ] `Platform`
- [ ] `CMS`
- [ ] `Helpdesk`
- [ ] Remaining support/internal modules

## Phase 10 — Cross-cutting migration work

- [ ] Payments: introduce `Cashier` when billing/subscriptions work begins
- [ ] Notifications/activity logs: port only the patterns still needed
- [ ] Settings/notes/addresses: migrate only after shared usage is confirmed

## Phase 11 — Hardening and cutover

- [ ] Run targeted regression tests per migrated module
- [ ] Run full test suite
- [ ] Run full static analysis
- [ ] Run frontend production build
- [ ] Verify navigation and UX consistency across migrated modules
- [ ] Decommission superseded code paths

## Open decisions

- [ ] Keep `Orders` as separate module or absorb into `Billing`
- [ ] Migrate Astero scaffold classes directly or rebuild cleaner Inertia-native equivalents
- [ ] Which shared systems move early: notes, addresses, settings
- [ ] Which current application migrations survive the database baseline rebase
- [ ] Which current seeders survive the database baseline rebase
- [ ] Whether some modules should be merged or retired during migration
