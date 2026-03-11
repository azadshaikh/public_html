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
- [x] Review and approve PRD
- [x] Exclude legacy groups CRUD/features from current migration scope
- [x] Split media into a dedicated migration track
- [x] Prioritize roles/permissions before broader shared migration
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
- Shared model inventory shows the main polymorphic foundations are `Address`, `Note`, `ActivityLog`, `CustomMedia`, `Revision*`, and metadata/settings models. These remain shared-foundation candidates, but media still stays on its dedicated Phase 6 track.
- The first real module dependency map is now clear from `Customers`: it depends on shared user/role assignment, addresses, metadata, notes/activity, optional media attachment, and integration contracts for billing/subscriptions. Its legacy `App\Scaffold\*` stack, Blade views, and scaffold services should be rebuilt as `Inertia` patterns instead of ported.
- Customers also uses legacy navigation configuration in `tmp/astero/modules/Customers/config/navigation.php`, so navigation conventions should be decided during shared-foundation inventory instead of waiting for later UI implementation.
- Legacy groups CRUD remains dropped. `Customers` references `GroupItem`, but that should be replaced with cleaner module-owned enums or lookups unless a real cross-module dependency proves otherwise.

## Phase 3 — Roles and permissions foundation

- [x] Inventory Astero role/permission architecture, seeders, guards, and UI flows
- [x] Decide target authorization package/pattern for the new app
- [x] Define role model scope and initial seed data for the target app
- [x] Define permission naming convention for application and module features
- [x] Build roles CRUD in the current app
- [x] Build permission assignment/editing flow for roles
- [x] Add user-to-role assignment flow where needed
- [x] Wire middleware/policy usage into the current app
- [x] Add tests for roles/permissions CRUD and enforcement
- [x] Run Pint, PHPUnit, and static analysis

## Phase 4 — Inertia-native platform foundation

- [ ] Define target architecture for shared module-friendly CRUD patterns
- [ ] Decide what survives from Astero scaffold design
- [ ] Build Inertia-native CRUD foundation for forms, tables, filters, actions, stats, and detail pages
- [ ] Standardize route, request, resource, and page conventions for modules
- [ ] Define shared layout and module-shell conventions

## Phase 5 — Shared foundation migration

- [ ] Port core enums
- [ ] Port core contracts
- [ ] Port reusable traits
- [ ] Port reusable support helpers/services
- [ ] Port shared base models/polymorphic systems where still valid
- [ ] Add tests for migrated shared foundation
- [ ] Run Pint, PHPUnit, and static analysis

## Phase 6 — Media migration foundation

- [ ] Inventory Astero media model, usage patterns, and upload/browse flows
- [ ] Decide target media library/storage strategy for the new app
- [ ] Define Inertia-native media UX and attachment patterns
- [ ] Add tests for the chosen media foundation

## Phase 7 — Module migration template

- [ ] Use one small real module as migration template
- [ ] Produce module inventory template: models, migrations, routes, services, UI, tests, dependencies
- [ ] Define per-module acceptance checklist
- [ ] Define feature parity checklist versus Astero

## Phase 8 — Module migrations

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

## Phase 9 — Cross-cutting migration work

- [ ] Payments: introduce `Cashier` when billing/subscriptions work begins
- [ ] Notifications/activity logs: port only the patterns still needed
- [ ] Settings/notes/addresses: migrate only after shared usage is confirmed

## Phase 10 — Hardening and cutover

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
- [ ] Whether some modules should be merged or retired during migration
