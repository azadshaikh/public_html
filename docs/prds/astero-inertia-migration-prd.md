# Astero to Inertia Migration PRD

## Summary

This project will migrate the business capabilities of Astero into the current Laravel 12 + `Inertia` v3 + React application.

The migration target is the current application shell and custom module runtime, not Astero's old runtime. The goal is to preserve feature coverage while improving architecture, removing `Unpoly`-specific patterns, rebasing the backend schema around Astero's business model, and establishing stronger engineering guardrails through static analysis and automated refactoring.

## Problem

Astero is the real product codebase, but it was built around a different frontend interaction model and an older module runtime approach.

The current application has already validated that a custom module system can work well with `Inertia`/React. It also already has enough UI and platform components to support major migration work. What is missing is a structured migration plan that:

- preserves business features,
- avoids copying legacy interaction patterns blindly,
- keeps the current custom module runtime intact,
- intentionally rebases the database around Astero's real domain model,
- ports shared foundations before modules,
- and improves quality while migration is happening.

## Product goal

Create a single modern codebase that:

- retains Astero's full business capability,
- uses the current custom module runtime,
- uses `Inertia`/React instead of `Unpoly`,
- uses Astero-aligned backend models, migrations, and seed data where they represent real product behavior,
- supports gradual migration module by module,
- and steadily reduces technical debt during the move.

## Primary objectives

1. Migrate Astero shared application foundations before module work.
2. Rebuild feature delivery on `Inertia`/React instead of fragment-based `Unpoly` flows.
3. Rebase the application's database layer around Astero's real domain model while preserving the current custom module runtime.
4. Keep module migration incremental and testable.
5. Introduce `PHPStan`, `Larastan`, and `Rector` early so debt is visible and controlled.
6. Preserve feature parity where needed, while allowing cleaner refactors and consolidation.

## Non-goals

- Porting `Unpoly` request/response behavior directly.
- Copying Astero's old runtime wholesale.
- Keeping the current starter auth, demo data, or placeholder schema simply because it already exists.
- Preserving every implementation detail if a cleaner current design is better.
- Migrating all external packages at once before they are needed.
- Rebuilding legacy groups CRUD/features in the new app unless a concrete later need appears.
- Expanding the current local demo/testing modules as product features, because they are temporary placeholders that will be replaced by migrated Astero modules.

## Guiding principles

### 1. Migrate capability, not legacy transport

`Unpoly` patterns, partial swap middleware, and Blade-era SPA workarounds should be replaced by `Inertia`/React flows.

### 2. Keep the current module runtime

The current application's custom module runtime is the migration target. Astero modules should be adapted to it instead of reintroducing `nwidart/laravel-modules` as the core architecture.

The current local modules in this repository are only a testing harness for that runtime. They should not receive new product implementation effort unless a change is strictly required to support the migration platform itself.

### 3. Shared app first

Most Astero modules depend heavily on shared application layers such as traits, enums, support services, contracts, and common model patterns. Those must move before serious module migration starts.

### 4. Rebase the schema intentionally

The database should move toward Astero's actual product model, but not through a blind copy of legacy history. We should keep the current custom module migration loading and module seeding orchestration, then replace or rewrite application-level migrations and seeders so the target schema reflects the business domain we actually want to run.

### 5. Refactor when it improves the target

The objective is not a byte-for-byte port. If an Astero pattern is too Blade-centric, too `Unpoly`-specific, or structurally outdated, it should be redesigned for the new app.

### 6. Keep migration incremental

Every migration unit should be small enough to validate with tests, static analysis, and manual review.

## Target architecture

### Backend

- Laravel 12 remains the application framework.
- The current custom module runtime remains the module loading strategy.
- The current module migration loading and module seeding orchestration remain fixed platform contracts.
- Application-level migrations and seeders may be replaced, merged, or rewritten to match Astero's real domain model.
- The current starter auth stack is not a required target if Astero-aligned identity and authorization flows need a different implementation.
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

This phase also locks module navigation conventions early, because Astero modules already encode navigation structure in module config and route naming. We should inventory and normalize that before rebuilding module UX.

Initial Phase 2 findings:

- The Astero scaffold stack (`App\Scaffold\*`, `ScaffoldServiceInterface`, `Scaffoldable`, Blade scaffold resources) should not be ported directly. It is a refactor input for the later `Inertia` CRUD foundation.
- Shared systems most likely to survive are addresses, notes, activity logging, metadata, revisions, and a small set of notification concepts.
- Media-specific helpers and traits are still deferred to the dedicated media phase.
- `Support\Unpoly` is a drop item, not a migration candidate.
- Legacy groups CRUD remains out of scope; places where Astero uses `Group` or `GroupItem` should be redesigned unless a real shared dependency emerges.
- The first real module (`Customers`) depends mainly on user linking, addresses, metadata/notes/activity, and optional billing/subscription integration contracts, which makes it a good template module after the shared foundation is ready.

### Phase 3 — Database baseline rebase

Before broad CRUD migration begins, the application should adopt an Astero-aligned database baseline.

This includes:

- inventorying current application migrations and seeders,
- deciding which platform-level pieces remain because they support the current runtime,
- inventorying Astero migrations and seeders by domain,
- replacing or rewriting application-level migrations so the target schema reflects Astero's real product model,
- preserving the root `DatabaseSeeder` orchestration pattern for module seeders,
- preserving the module migration loading strategy in module service providers,
- and validating that a fresh install can build the rebased schema cleanly.

### Phase 4 — Identity and authorization foundation

Rebuild the authorization base early because most migrated application features depend on roles and permissions.

This includes:

- selecting the target authorization package/pattern,
- defining the initial role set,
- defining the permission naming convention,
- building roles CRUD and permission assignment UX,
- wiring role and permission checks into the new app,
- and seeding the minimum application-level roles and permissions needed before broader migration work.

Current starter auth choices are not preserved by default; they survive only if they still serve the Astero-aligned target application.

### Phase 5 — Inertia-native foundation

Convert the best Astero scaffold ideas into a cleaner Inertia-native module pattern.

This includes:

- CRUD page conventions,
- filters and table patterns,
- form patterns,
- page-level stats and actions,
- detail page patterns,
- shared layouts and module shell behavior.

Initial Phase 5 decisions:

- Module CRUD should be organized around explicit Laravel controllers, Form Requests, and `Inertia::render()` pages, with Wayfinder-generated helpers as the frontend contract.
- Route naming should stay resource-like and predictable so frontend pages can import named controller actions directly from `@/actions/...`.
- Each migrated module should use a standard page set in `resources/js/pages/<module>/`: `index.tsx`, `create.tsx`, `edit.tsx`, and additional detail pages only when the business flow truly needs them.
- Shared page payloads should be intentionally shaped for `Inertia`: index payloads provide filters, stats, rows, and option lists; form pages provide initial values plus option sets; status/error messages should flow through redirects and shared props instead of custom response fragments.
- Larger forms should live in extracted module components under `resources/js/components/<module>/`, while small one-off pages can stay inline.
- The existing application shell is the target layout model: shared breadcrumbs, title/description/header actions, sidebar navigation, and content spacing should flow through `AppLayout` rather than module-specific wrappers.
- Navigation conventions are now fixed early: each module gets one sidebar section entry with nested links for major CRUD flows, permission gating, and active-state detection based on route/namespace rather than module-local config files copied from Astero.
- Filters, stats, registries, alerts, and empty states should be built from shared React UI primitives and consistent Tailwind spacing, not legacy Blade partial composition.
- Astero scaffold concepts such as columns, filters, and status tabs may inspire shared React abstractions later, but the scaffold runtime itself is not part of the target architecture.
- The remaining implementation work in this phase is to extract reusable CRUD primitives from the already-built `roles` and `users` pages so the first real migrated module (`Customers`) can land on a stable pattern.

### Phase 6 — Shared foundation migration

Port the shared foundations required by the first real modules.

### Phase 7 — Media migration foundation

Treat media as a dedicated migration track instead of a side task.

This includes:

- evaluating Astero's old media behavior and data model,
- deciding the target storage and library strategy for the new app,
- defining upload, browse, attach, and cleanup flows for `Inertia`/React,
- and migrating media only when the new foundation is explicit and testable.

### Phase 8 — Module migration template

Use one smaller real module to establish a repeatable migration template.

Recommended first real module: `Customers`.

Reason:

- real business value,
- meaningful CRUD patterns,
- moderate dependency surface,
- lower risk than `Platform` or `CMS`.

### Phase 9 — Module-by-module migration

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
2. identify required shared foundations and schema dependencies,
3. port/refactor missing shared pieces,
4. align or extend the rebased schema where the module needs it,
5. migrate models, requests, services, controllers, routes, and pages,
6. rebuild the UX in `Inertia`/React,
7. add or port tests,
8. run `Pint`, PHPUnit, and static analysis,
9. close gaps before moving to the next module.

## Immediate plan of action

1. Freeze the current application-level migration and seeder set as a reference point.
2. Inventory Astero migrations and seeders by business domain.
3. Decide the keep / rewrite / drop list for the current project's migrations and seeders.
4. Have the Astero files moved into the repository in a staging area or directly into their target locations.
5. Rework the application's migration baseline so it matches Astero's product model while preserving the current custom module runtime contracts.
6. Rework the root seeder so application seeders and module seeders still execute in the correct order.
7. Run a clean `migrate:fresh --seed` cycle and fix all runtime, schema, and seeding issues.
8. Start CRUD migration one resource at a time, beginning with the first agreed module.

## Success criteria

The migration is successful when:

- the current application becomes the single active platform,
- Astero module features are available in the new app,
- `Unpoly` is fully retired from migrated flows,
- module architecture is consistent under the current runtime,
- the database baseline reflects Astero's real product model rather than placeholder starter schema,
- static analysis is part of normal development,
- and migrated features are covered by tests.

## Risks

1. Shared foundations may be more entangled than expected.
2. Large modules like `Platform` and `CMS` may expose missing architectural decisions late.
3. Some Astero packages may pull in complexity that should be redesigned instead of copied.
4. A direct scaffold port may preserve too much old behavior if not intentionally redesigned.
5. A rushed schema reset may break module bootstrapping if root seeding and module migration loading contracts are not preserved.

## Mitigations

- Start with tooling and inventory.
- Rebase the database intentionally instead of copying every historical migration blindly.
- Preserve the current custom module runtime, module migration loading, and root module seeding orchestration while rewriting application-level schema.
- Port shared layers before business modules.
- Use one real module to validate the migration template early.
- Introduce third-party packages only when a migrated feature actually needs them.
- Keep a task tracker with explicit open decisions and blockers.

## Initial decisions already made

- `SpaLab` is excluded from this migration.
- `Unpoly` will not be migrated directly.
- The current Inertia-based application is the destination.
- Migration will proceed app-first, with a database baseline rebase before CRUD-by-CRUD module migration.
- The current custom module runtime remains the platform contract.
- The current module migration loading and root module seeding orchestration remain the platform contract.
- The current starter auth stack, demo seeders, and placeholder application schema are not architectural commitments.
- Groups CRUD/features are excluded from the current migration scope.
- Media migration will run as a separate dedicated step, not as incidental shared-foundation work.
- The current local modules are temporary testing/demo modules and will be replaced by migrated Astero modules, so feature work should target migration foundations and real Astero replacements instead of extending those placeholders.

## Reference tracker

Execution is tracked in [docs/todo/astero-inertia-migration-tracker.md](docs/todo/astero-inertia-migration-tracker.md).
