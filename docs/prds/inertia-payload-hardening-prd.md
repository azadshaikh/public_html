# Inertia Payload Hardening PRD

## Summary

Reduce unnecessary and sensitive data sent through Inertia responses by introducing explicit payload contracts for shared shell props, scaffold index rows, and datagrid action/config ownership.

This is a greenfield refactor. The goal is not to preserve permissive or legacy-friendly payload shapes. The goal is to make the browser receive only what it needs, prevent internal implementation details from leaking into page responses, and give future CRUD work a clear, enforceable path.

The desired end state is a platform where:

- shared Inertia props contain only shell-safe runtime data,
- module runtime data sent to the browser excludes internal paths and diagnostic metadata,
- scaffold index rows are lean and explicit instead of merge-all by default,
- datagrid pages do not pay for both backend and frontend action definitions at the same time,
- and future CRUDs follow one obvious payload contract.

## PRD status

- Status: In progress
- Priority: High
- Owner: Platform architecture / CRUD foundation
- Started: 2026-03-18
- Current phase: Phase 5 in progress

## Status tracker

| Track | Status | Notes |
| --- | --- | --- |
| PRD and implementation plan | Complete | Initial plan approved and captured here |
| Shared props audit | Complete | Main leak and bloat areas identified |
| Shared props refactor | Complete | Shared user payload is leaner, modules are split into runtime-safe versus management metadata, and shared abilities are now route-scoped |
| Scaffold row contract redesign | Complete | `ScaffoldResource` now supports explicit raw-attribute allowlists and list-focused serialization hooks |
| Datagrid action ownership cleanup | Complete | Standard pages now consume shared scaffold helpers for backend row/bulk actions and empty-state payloads, while custom pages can opt out cleanly |
| CMS posts pilot migration | Complete | Posts index rows are lean and the page now opts out of redundant backend action and empty-state payloads |
| Platform standard-page pilot | Complete | Agencies and servers now validate the backend-driven scaffold contract, including row actions, bulk actions, and empty-state CTA payloads |
| Tests and regression guards | In progress | Shared-payload leakage, route-scoped ability coverage, lean CMS post-row regression checks, pilot payload-budget guards, and Phase 5 standard-page rollout checks are in place |
| AGENTS and skill updates | Complete | `AGENTS.md` plus the datagrid, Inertia React, Laravel Inertia CRUD, and module-development skills now codify the finalized payload contract |

## Problem

Current Inertia responses are sending too much data and, in a few places, data that should not be exposed to ordinary browser pages.

### Current issues

1. Shared middleware props are broader than necessary.
   - `auth` includes a large flat ability map.
   - `modules` includes internal metadata better suited for management or diagnostics.
   - some shared user fields are not used by the UI.

2. Shared module payloads leak internal implementation details.
   - provider class names
   - filesystem paths
   - route file locations
   - abilities config paths
   - navigation config paths
   - seeder paths/classes

3. Scaffold index rows are serialized too broadly.
   - `ScaffoldResource` starts from all model attributes and then layers formatted dates, computed labels, audit fields, soft-delete fields, custom fields, and actions.
   - this produces unnecessary raw fields, duplicate display aliases, and heavy row payloads.

4. Datagrid pages do not consistently use one contract.
   - some pages consume backend scaffold config and backend actions,
   - some pages rebuild actions and empty states entirely in React,
   - and some currently pay for both.

5. There is no explicit payload distinction between:
   - shell-safe shared runtime data,
   - feature-level page data,
   - and management/diagnostic metadata.

## Product goal

Create a lean, public-safe Inertia payload architecture where:

- shared props are limited to data the app shell genuinely needs on most authenticated pages,
- feature pages receive only their own required capabilities and display data,
- internal implementation metadata is never shipped to ordinary browser pages,
- scaffold resources define explicit list/index payloads,
- and datagrid pages choose either backend-driven or custom-driven actions, never both.

## Principles

1. **Greenfield first**
   - No compatibility layers unless a migration step truly requires one.

2. **Explicit contracts over permissive serialization**
   - If a page needs a field, that field should be intentionally included.
   - If a page does not need a field, it should not be present.

3. **Public-safe shared data only**
   - Shared props must be safe to expose to any authenticated browser session that can load the shell.

4. **One source of truth per concern**
   - Standard scaffold pages should default to backend-driven datagrid config and actions.
   - Custom pages may opt out, but they should not receive duplicate scaffold payloads.

5. **Validation must enforce the architecture**
   - Response-shape tests and regression guards are part of the design, not follow-up polish.

## Scope

### In scope

- `HandleInertiaRequests` shared payload redesign
- module shared runtime payload redesign
- shared ability payload redesign
- scaffold row serialization redesign for index pages
- datagrid action and empty-state contract cleanup
- CMS posts as first pilot page
- Platform scaffold-heavy pages as standard-path validation
- feature tests for leakage prevention and payload contracts
- follow-up AGENTS and skill updates after implementation settles

### Out of scope

- redesigning the sidebar navigation information architecture
- replacing the custom module runtime
- a full rewrite of all CRUD pages in one pass
- unrelated UI refresh work
- preserving old payload shapes for compatibility reasons

## Architecture direction

## 1. Shared payload tiers

Introduce three explicit payload tiers.

### Tier A: Shared shell props

Only data needed by the authenticated shell on most pages:

- app name
- branding used by shell components
- minimal authenticated user identity
- impersonation state when active
- sidebar open state
- flash messages
- navigation
- minimal module runtime descriptor needed for page resolution or shell branching

### Tier B: Page props

Feature-specific payloads that belong to the page itself:

- resource abilities/capabilities
- feature-specific stats
- option lists
- index filters
- datagrid rows
- empty-state config where used

### Tier C: Management or diagnostic props

Internal metadata used only by admin/inspection screens:

- provider class names
- filesystem paths
- route file paths
- module config paths
- seeder metadata
- other implementation details not needed by ordinary pages

These must not be present in ordinary shared browser payloads.

## 2. Lean scaffold index payloads

Redesign scaffold row serialization so index payloads are explicit.

Recommended direction:

- add index/list payload profiling or field allowlists to `ScaffoldResource`
- allow index-specific eager-load hooks
- keep edit/show payloads separate and richer where needed
- remove duplicate row aliases unless the frontend actually consumes them

The backend should stop assuming every index page needs every model attribute and every formatted derivative.

## 3. Datagrid action ownership

Standard scaffold pages should converge on one backend-driven contract:

- `config.columns`
- `config.filters`
- `config.actions`
- `statusTabs`
- scaffold-derived bulk actions and row actions

Custom-rich pages may opt out, but opting out must also stop backend serialization of redundant action payloads and unused empty-state/config fragments.

## 4. Minimal module runtime descriptors

Split module metadata into:

- runtime descriptor for ordinary shared frontend use
- management descriptor for module inspection/admin pages

Runtime descriptor should be limited to fields genuinely used by the frontend boot/runtime.

## Proposed implementation phases

### Phase 1: Shared props hardening

Refactor shared middleware payloads first because they affect every page.

Targets:

- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Modules/ModuleManager.php`
- `app/Modules/Support/ModuleManifest.php`
- `app/Helpers/AbilityAggregator.php`

Goals:

- remove unused shared user fields
- trim global ability payloads
- split module runtime data from management metadata
- keep only public-safe shared data in ordinary browser responses

### Phase 2: Scaffold row contract redesign

Targets:

- `app/Scaffold/ScaffoldResource.php`
- `app/Traits/Scaffoldable.php`
- high-traffic scaffold resources starting with CMS posts

Goals:

- define explicit index-visible fields
- define index-specific eager loads where needed
- avoid merge-all model payloads for list pages
- eliminate duplicate display aliases where possible

### Phase 3: Datagrid contract cleanup

Targets:

- `app/Scaffold/Action.php`
- `resources/js/lib/scaffold-datagrid.ts`
- pilot pages in CMS and Platform

Goals:

- standard scaffold pages use backend-driven action metadata
- custom pages can opt out cleanly
- redundant row action payloads and empty-state props are removed where unused

### Phase 4: Pilot migrations

Pilot 1:

- CMS posts index as the custom-rich page pilot

Pilot 2:

- one or two Platform scaffold-heavy pages as the standard golden-path pilot

Goals:

- prove the contract for both custom and standard pages
- measure payload reductions
- verify no UX regressions

### Phase 5: Rollout and guardrails

Targets:

- remaining scaffold-backed CRUDs
- repo guidance and skill docs
- response-shape regression tests

Goals:

- remove redundant props during each migration
- update implementation guidance
- add leakage-prevention tests

## Implementation todos

### Track A: Shared props hardening

- [x] Audit all fields currently returned from `HandleInertiaRequests::share()`
- [x] Define the allowed shared shell payload contract
- [x] Remove unused `auth.user` timestamps and other unused shell fields
- [x] Replace broad flat shared abilities with a minimal global contract plus route-scoped shared capabilities
- [x] Split module shared data into runtime-safe and management-only payloads
- [x] Remove internal paths and provider metadata from shared frontend module payloads
- [x] Update frontend shared types to match the lean shared payload
- [x] Add feature tests asserting sensitive module metadata is absent from ordinary page responses

### Track B: Scaffold row payload redesign

- [x] Add explicit index/list payload profiling or field allowlists to `ScaffoldResource`
- [x] Add index-specific eager-load hooks or equivalent query controls where needed
- [x] Define the first lean resource contract for CMS posts
- [x] Remove unused raw content/blob/meta fields from CMS posts index rows
- [x] Remove duplicate display fields from posts rows where the page does not consume them
- [x] Add feature tests asserting heavy fields are absent from CMS posts index payloads

### Track C: Datagrid and action ownership

- [x] Define standard scaffold-page contract versus custom-page opt-out contract
- [x] Refine scaffold datagrid helpers to support the chosen standard action flow cleanly
- [x] Add backend opt-out so custom pages can skip redundant row action payloads
- [x] Add backend opt-out so custom pages can skip unused empty-state payloads where appropriate
- [x] Migrate CMS posts to the custom-page contract
- [x] Migrate one or two Platform pages to the standard scaffold contract

### Track D: Rollout and enforcement

- [ ] Classify remaining datagrid pages as standard or custom
- [ ] Migrate remaining scaffold pages resource by resource
- [ ] Add regression tests around shared prop exposure
- [ ] Add regression tests around lean scaffold row payloads
- [x] Update `AGENTS.md` with the finalized payload contract rules
- [x] Update CRUD/datagrid skills with the finalized payload contract rules

## Risks

1. Shared abilities are currently assumed in many pages.
   - Moving them page-local requires deliberate migration of page prop types and capability checks.

2. Some datagrid pages rely on mixed patterns.
   - Converting them without defining standard versus custom contracts first will create more drift.

3. `ScaffoldResource` is central infrastructure.
   - Its refactor should be introduced carefully and validated with pilot pages before broad rollout.

4. Navigation remains globally shared.
   - This is acceptable for now, but it should not be used as a reason to keep other broad shared payloads.

## Success criteria

The work is successful when:

- ordinary shared Inertia responses no longer expose internal module paths or management metadata,
- shared payloads contain only shell-safe runtime data,
- CMS posts index payload size is materially reduced,
- pilot Platform pages validate the standard scaffold-driven contract,
- custom pages stop paying for duplicate backend action/config payloads,
- and future CRUD guidance reflects the new contract.

## Verification plan

1. Capture and enforce pilot page payload budgets once each pilot contract is stable.
2. Compare payload size and key sets when migrating new pages onto either the standard or custom path.
3. Add feature tests for absent sensitive fields in ordinary page responses.
4. Add feature tests for absent heavy row fields in lean index payloads.
5. Run focused Laravel tests for middleware, CMS posts, and pilot Platform pages.
6. Run focused frontend validation for touched shared types, datagrid helpers, and pilot pages.
7. Manually verify table view, card view, filters, bulk actions, and sidebar behavior.

## Initial execution order

1. Implement Track A first.
2. Pilot Track B on CMS posts immediately after Track A stabilizes.
3. Implement Track C in parallel once the CMS posts row contract is clear.
4. Use Platform pages to validate the standard backend-driven datagrid contract.
5. Roll out Track D after both pilot paths are stable.

## Working notes

- CMS posts is the right first pilot because it currently exposes all three pain points at once: oversized shared props, oversized index rows, and duplicated datagrid action ownership.
- Platform scaffold-heavy pages are the right second pilot because they are the closest current examples of a backend-driven datagrid path.
- This PRD intentionally chooses a larger architectural refactor over incremental patching because the app is greenfield and should converge now instead of preserving broad payload contracts.
- First implementation slice completed on 2026-03-18:
   - removed shared `auth.user` timestamp fields from Inertia runtime payloads,
   - split module manifest output into lean runtime shared data versus rich management data,
   - updated shared frontend module/auth types,
   - added focused regression tests proving ordinary shared payloads omit internal module metadata while the module management page still receives the rich descriptor.
- Phase 1 completed on 2026-03-18:
   - shared `auth.abilities` is now scoped to the current route family instead of shipping the broad flat set on every response,
   - the scoped ability contract is validated across dashboard, roles, users, masters, CMS, Todos, Platform, and ReleaseManager page families,
   - the next implementation target is Phase 2: lean scaffold index row contracts.
- Phase 2 completed on 2026-03-18:
   - `ScaffoldResource` now supports explicit raw-attribute allowlists while preserving legacy broad serialization by default,
   - `Scaffoldable` now exposes a dedicated list-query eager-load hook so list pages can diverge cleanly when needed,
   - CMS posts now uses an explicit lean row contract that omits raw content, SEO/meta blobs, duplicate display aliases, and detailed soft-delete timestamps,
   - CMS posts regression coverage now asserts those heavy fields stay out of the index payload.
- Phase 3 started on 2026-03-18:
   - `ScaffoldDefinition` can now suppress backend action config for custom-managed datagrid pages,
   - `Scaffoldable` can now suppress empty-state payloads when the page owns that UI contract in React,
   - CMS posts now opts out of backend `config.actions`, row `actions`, and `empty_state_config`,
   - Platform scaffold pages remain on the backend-driven action path and were revalidated as the standard reference.
- Phase 3 completed on 2026-03-18:
   - shared scaffold datagrid helpers now cover the full standard-path contract, including backend row actions, bulk actions, and empty-state payload mapping,
   - the shared datagrid empty state now renders backend CTA actions when present,
   - Platform agencies and servers were migrated to the helper-backed standard contract,
   - focused regression coverage now proves the custom CMS posts contract and the standard Platform scaffold contract can coexist without duplicating payload ownership.
- Phase 4 completed on 2026-03-18:
   - the CMS posts custom-path pilot now has an explicit payload budget guard covering both the page-level feature fragment and the serialized row payload,
   - the Platform agencies and servers standard-path pilots now have explicit payload budget guards covering backend-driven config, rows, filters, statistics, and empty-state payloads,
   - Phase 4 is now treated as pilot verification and measurement rather than additional pilot migrations, because the named custom and standard pilot pages were already migrated during Phases 2 and 3.
- Phase 5 started on 2026-03-18:
   - the remaining Platform datagrid index pages were classified as standard-path pages and migrated onto the shared scaffold action and empty-state helper flow,
   - Phase 5 regression coverage was expanded so the standard Platform contract now covers providers, secrets, websites, TLDs, domains, and DNS records in addition to agencies and servers,
   - the remaining rollout work is now concentrated in the custom-page classification for non-Platform indexes, broader lean-row guards, and repository guidance updates.
- Phase 5 guidance update completed on 2026-03-19:
   - `AGENTS.md` now states the payload-hardening rules explicitly: shell-safe shared props only, runtime-only shared module descriptors, route-scoped shared abilities, explicit scaffold list payloads, and single-owner datagrid action/empty-state contracts,
   - the `datagrid`, `inertia-react-development`, `laravel-inertia-crud-development`, and `module-development` skills were updated to reinforce the same contract during future CRUD and Inertia work,
   - the stale `module-development` guidance that treated rich module manifest fields as ordinary shared runtime metadata was corrected.
- Follow-up hardening fixes completed on 2026-03-19:
   - scaffold empty-state create CTAs are now authorized on the backend before being sent to the page payload,
   - the shared module runtime descriptor was narrowed further to the fields actually used by frontend boot and Inertia page resolution: `name`, `slug`, and `inertiaNamespace`,
   - focused unit coverage now protects the empty-state authorization contract and the lean shared module descriptor contract.
