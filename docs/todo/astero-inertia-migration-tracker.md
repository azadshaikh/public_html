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

- [ ] Inventory Astero `app/Contracts`
- [ ] Inventory Astero `app/Enums`
- [ ] Inventory Astero `app/Traits`
- [ ] Inventory Astero `app/Support`
- [ ] Inventory Astero `app/Services`
- [ ] Inventory shared models and polymorphic systems
- [ ] Classify each item as port / refactor / drop / defer
- [ ] Define dependency map from modules to shared foundation

## Phase 3 — Inertia-native platform foundation

- [ ] Define target architecture for shared module-friendly CRUD patterns
- [ ] Decide what survives from Astero scaffold design
- [ ] Build Inertia-native CRUD foundation for forms, tables, filters, actions, stats, and detail pages
- [ ] Standardize route, request, resource, and page conventions for modules
- [ ] Define shared layout/navigation/module-shell conventions

## Phase 4 — Shared foundation migration

- [ ] Port core enums
- [ ] Port core contracts
- [ ] Port reusable traits
- [ ] Port reusable support helpers/services
- [ ] Port shared base models/polymorphic systems where still valid
- [ ] Port shared auth/permission patterns that still fit the new app
- [ ] Add tests for migrated shared foundation
- [ ] Run Pint, PHPUnit, and static analysis

## Phase 5 — Module migration template

- [ ] Use one small real module as migration template
- [ ] Produce module inventory template: models, migrations, routes, services, UI, tests, dependencies
- [ ] Define per-module acceptance checklist
- [ ] Define feature parity checklist versus Astero

## Phase 6 — Module migrations

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

## Phase 7 — Cross-cutting migration work

- [ ] Payments: introduce `Cashier` when billing/subscriptions work begins
- [ ] Media: evaluate old media stack versus new target design
- [ ] Permissions: normalize authorization model across migrated modules
- [ ] Notifications/activity logs: port only the patterns still needed
- [ ] Settings/groups/notes/addresses: migrate only after shared usage is confirmed

## Phase 8 — Hardening and cutover

- [ ] Run targeted regression tests per migrated module
- [ ] Run full test suite
- [ ] Run full static analysis
- [ ] Run frontend production build
- [ ] Verify navigation and UX consistency across migrated modules
- [ ] Decommission superseded code paths

## Open decisions

- [ ] Keep `Orders` as separate module or absorb into `Billing`
- [ ] Migrate Astero scaffold classes directly or rebuild cleaner Inertia-native equivalents
- [ ] Which shared systems move early: notes, addresses, groups, media, settings
- [ ] Whether some modules should be merged or retired during migration
