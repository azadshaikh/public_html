# Astero to Inertia Migration PRD

## Summary

This project will migrate the business capabilities of Astero into the current Laravel 12 + `Inertia` v3 + React application.

The migration target is the current application, not Astero's old runtime. The goal is to preserve feature coverage while improving architecture, removing `Unpoly`-specific patterns, and establishing stronger engineering guardrails through static analysis and automated refactoring.

## Problem

Astero is the real product codebase, but it was built around a different frontend interaction model and an older module runtime approach.

The current application has already validated that a module system can work well with `Inertia`/React. What is missing is a structured migration plan that:

- preserves business features,
- avoids copying legacy interaction patterns blindly,
- ports shared foundations before modules,
- and improves quality while migration is happening.

## Product goal

Create a single modern codebase that:

- retains Astero's full business capability,
- uses the current custom module runtime,
- uses `Inertia`/React instead of `Unpoly`,
- supports gradual migration module by module,
- and steadily reduces technical debt during the move.

## Primary objectives

1. Migrate Astero shared application foundations before module work.
2. Rebuild feature delivery on `Inertia`/React instead of fragment-based `Unpoly` flows.
3. Keep module migration incremental and testable.
4. Introduce `PHPStan`, `Larastan`, and `Rector` early so debt is visible and controlled.
5. Preserve feature parity where needed, while allowing cleaner refactors and consolidation.

## Non-goals

- Porting `Unpoly` request/response behavior directly.
- Copying Astero's old runtime wholesale.
- Preserving every implementation detail if a cleaner current design is better.
- Migrating all external packages at once before they are needed.
- Rebuilding legacy groups CRUD/features in the new app unless a concrete later need appears.

## Guiding principles

### 1. Migrate capability, not legacy transport

`Unpoly` patterns, partial swap middleware, and Blade-era SPA workarounds should be replaced by `Inertia`/React flows.

### 2. Keep the current module runtime

The current application's module runtime is the migration target. Astero modules should be adapted to it instead of reintroducing `nwidart/laravel-modules` as the core architecture.

### 3. Shared app first

Most Astero modules depend heavily on shared application layers such as traits, enums, support services, contracts, and common model patterns. Those must move before serious module migration starts.

### 4. Refactor when it improves the target

The objective is not a byte-for-byte port. If an Astero pattern is too Blade-centric, too `Unpoly`-specific, or structurally outdated, it should be redesigned for the new app.

### 5. Keep migration incremental

Every migration unit should be small enough to validate with tests, static analysis, and manual review.

## Target architecture

### Backend

- Laravel 12 remains the application framework.
- The current custom module runtime remains the module loading strategy.
- Shared domain abstractions from Astero are ported selectively into `app/`.
- Module code lives under `modules/` and follows the current runtime manifest/provider conventions.

### Frontend

- `Inertia` v3 + React is the only SPA/navigation model.
- New module pages are built as React pages and shared UI components.
- Astero Blade/`Unpoly` screens are translated into server-driven `Inertia` responses and typed page props.

### Quality layer

- `Pint` stays as formatter.
- `PHPStan` + `Larastan` provide static analysis.
- `Rector` supports safe iterative modernization.
- PHPUnit remains the test standard for the target app.

## Migration strategy

### Phase 1 — Quality baseline

Add analysis and refactoring tooling first so migration work does not increase hidden debt.

Deliverables:

- `PHPStan`
- `Larastan`
- `Rector`
- composer scripts and team workflow
- first debt snapshot

### Phase 2 — Shared foundation inventory

Audit Astero shared application code and classify items into:

- port as-is,
- refactor while porting,
- defer,
- drop.

Primary areas:

- `Contracts`
- `Enums`
- `Traits`
- `Support`
- `Services`
- shared model systems such as notes, addresses, settings, activity

### Phase 3 — Roles and permissions foundation

Rebuild the authorization base early because most migrated application features depend on roles and permissions.

This includes:

- selecting the target authorization package/pattern,
- defining the initial role set,
- defining the permission naming convention,
- building roles CRUD and permission assignment UX,
- wiring role and permission checks into the new app,
- and seeding the minimum application-level roles and permissions needed before broader migration work.

### Phase 4 — Inertia-native foundation

Convert the best Astero scaffold ideas into a cleaner Inertia-native module pattern.

This includes:

- CRUD page conventions,
- filters and table patterns,
- form patterns,
- page-level stats and actions,
- detail page patterns,
- shared layouts and module shell behavior.

### Phase 5 — Shared foundation migration

Port the shared foundations required by the first real modules.

### Phase 6 — Media migration foundation

Treat media as a dedicated migration track instead of a side task.

This includes:

- evaluating Astero's old media behavior and data model,
- deciding the target storage and library strategy for the new app,
- defining upload, browse, attach, and cleanup flows for `Inertia`/React,
- and migrating media only when the new foundation is explicit and testable.

### Phase 7 — Module migration template

Use one smaller real module to establish a repeatable migration template.

Recommended first real module: `Customers`.

Reason:

- real business value,
- meaningful CRUD patterns,
- moderate dependency surface,
- lower risk than `Platform` or `CMS`.

### Phase 8 — Module-by-module migration

Recommended order:

1. `Customers`
2. `Subscriptions`
3. `Billing`
4. `Orders` or merge into `Billing` if cleaner
5. `Agency`
6. `ReleaseManager`
7. `Platform`
8. `CMS`
9. `Helpdesk`
10. remaining support/internal modules

## Module migration workflow

Every module should follow the same flow:

1. inventory features and dependencies,
2. identify required shared foundations,
3. port/refactor missing shared pieces,
4. migrate models, requests, services, controllers, routes, and pages,
5. rebuild the UX in `Inertia`/React,
6. add or port tests,
7. run `Pint`, PHPUnit, and static analysis,
8. close gaps before moving to the next module.

## Success criteria

The migration is successful when:

- the current application becomes the single active platform,
- Astero module features are available in the new app,
- `Unpoly` is fully retired from migrated flows,
- module architecture is consistent under the current runtime,
- static analysis is part of normal development,
- and migrated features are covered by tests.

## Risks

1. Shared foundations may be more entangled than expected.
2. Large modules like `Platform` and `CMS` may expose missing architectural decisions late.
3. Some Astero packages may pull in complexity that should be redesigned instead of copied.
4. A direct scaffold port may preserve too much old behavior if not intentionally redesigned.

## Mitigations

- Start with tooling and inventory.
- Port shared layers before business modules.
- Use one real module to validate the migration template early.
- Introduce third-party packages only when a migrated feature actually needs them.
- Keep a task tracker with explicit open decisions and blockers.

## Initial decisions already made

- `SpaLab` is excluded from this migration.
- `Unpoly` will not be migrated directly.
- The current Inertia-based application is the destination.
- Migration will proceed app-first, then module-by-module.
- Roles and permissions CRUD will be implemented before broader shared/common migration.
- Groups CRUD/features are excluded from the current migration scope.
- Media migration will run as a separate dedicated step, not as incidental shared-foundation work.

## Reference tracker

Execution is tracked in [docs/todo/astero-inertia-migration-tracker.md](docs/todo/astero-inertia-migration-tracker.md).
