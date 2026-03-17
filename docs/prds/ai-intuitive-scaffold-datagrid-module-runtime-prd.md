# AI-Intuitive Scaffold, Datagrid, and Module Runtime PRD

## Summary

Improve the scaffold CRUD foundation, datagrid contract, and custom module runtime so both humans and AI coding agents can follow one obvious path when creating or modifying features.

This is not a feature-delivery PRD. It is an architecture and developer-experience PRD focused on reducing ambiguity, shrinking convention drift, and making the system self-describing, verifiable, and easier to generate correctly.

The desired end state is a platform where:

- scaffold CRUD behavior is driven by a single canonical contract,
- datagrid backend and frontend types do not drift,
- module structure is explicit and validated,
- generators create the standard shape automatically,
- validators catch missing files and mismatched conventions early,
- and AI can succeed by following the system instead of inferring hidden rules.

## Implementation status — 2026-03-17

### Completed foundation slices

- `ScaffoldDefinition` now exposes executable introspection for expected page components, route names, permission names, ability maps, supporting file paths, and suggested CRUD test paths.
- `scaffold:doctor` validates discovered scaffold controllers against resolved Inertia page prefixes, registered route names, expected page files, module ability maps, and supporting scaffold files.
- Shared module-manifest test helpers now make module enablement state explicit in PHPUnit coverage.
- `scaffold:inspect` provides a debug command for resolved scaffold metadata so humans and AI can inspect the runtime contract without code archaeology.
- Shared frontend scaffold types now live in `resources/js/types/scaffold.ts` so module pages can alias one canonical client-side scaffold contract instead of redefining it per module.
- Shared scaffold-to-datagrid adapter utilities now live in `resources/js/lib/scaffold-datagrid.ts`, and Platform index pages now consume the derived datagrid state instead of rebuilding filters, tabs, sorting, and per-page config inline.
- CMS scaffold index pages and the ReleaseManager releases index now consume the shared derived datagrid state, shrinking repeated page-level filter, tab, sorting, and pagination glue across modules.

### Still pending from this PRD

- generator-driven CRUD/module scaffolding,
- adoption of the shared datagrid adapter pattern in the remaining custom CMS list pages that still need bespoke bulk/view behavior,
- golden-path example resource/module,
- broader contract tests around generated output.

### Revised next implementation slice

1. Finish the shared datagrid adapter rollout for the remaining bespoke CMS list pages, starting with `cms.posts.index`.
2. Create one golden-path scaffold resource/module that both `scaffold:doctor` and `scaffold:inspect` can validate.
3. Start the CRUD generator on top of the now-stable introspection, validation, and datagrid adapter APIs.

## Problem

The current system works, but too much of its behavior is implicit.

### Current friction points

1. `ScaffoldController` still depends on local controller knowledge such as service wiring and page-path decisions.
2. `ScaffoldDefinition` exports one config shape while the React datagrid maintains a separate frontend contract.
3. Module runtime conventions are spread across manifests, providers, file paths, tests, and skill docs instead of being enforced by one contract.
4. Important expectations such as Inertia page paths, ability keys, route names, seeder discovery, and module enabled-state behavior are not all validated together.
5. Test environment behavior differs from normal runtime through a separate test module manifest, which is useful but easy to miss.
6. The system has improved significantly, but it still rewards local code archaeology more than convention-guided implementation.

This increases the chance of incomplete or inconsistent work, especially when an AI agent is asked to add a new CRUD resource or migrate a module quickly.

## Product goal

Create an AI-intuitive application foundation where a new scaffold or module can be produced correctly with minimal guesswork and where the system itself explains, generates, and validates the expected implementation shape.

## Primary objectives

1. Establish a single source of truth for scaffold CRUD and datagrid configuration.
2. Make module runtime expectations explicit and machine-checkable.
3. Replace hidden conventions with generators, validators, and introspection helpers.
4. Reduce the number of places where a developer or AI has to manually infer page paths, abilities, routes, tests, and file locations.
5. Provide a clear golden path for future CRUD and module work.

## Non-goals

- Replacing the custom module runtime.
- Rewriting working business modules just for style.
- Introducing backward compatibility layers for removed patterns.
- Solving every documentation problem with prose alone.
- Building a generic external package; this work is for this application's architecture.

## Principles

### 1. One contract, many outputs

The system should define CRUD and datagrid behavior once, then derive backend props, frontend types, generated files, and validation from that contract.

### 2. Convention must be executable

A convention is not complete until the app can generate it or validate it.

### 3. Prefer derivation over duplication

If page paths, route names, permission keys, and datagrid metadata can be derived from shared inputs, they should not be manually repeated.

### 4. Remove dead and misleading paths

Anything that no longer represents the real runtime should be removed so AI does not follow stale patterns.

### 5. Optimize for the next module, not just the current one

The system should make the next CRUD or module cheaper and safer to build.

## Scope

### In scope

- Scaffold runtime ergonomics.
- Datagrid contract unification.
- Module runtime introspection and validation.
- Code generation for standard module CRUD scaffolds.
- Testing and guardrails for convention compliance.
- Documentation that reflects the enforced architecture.

### Out of scope

- Full migration of every existing module to new helper APIs in one pass.
- UI redesign unrelated to scaffold/datagrid/module ergonomics.
- Replacing Inertia, React, Laravel, or the custom module runtime.

## Success criteria

A new module CRUD resource is considered easy for AI to follow when:

- a generator can produce the standard file set,
- a validator can catch missing pages, ability mismatches, and route inconsistencies,
- the frontend consumes typed scaffold config without parallel manual mapping,
- and the implementation pattern can be learned from one canonical example.

## Opportunity sizing

### Big wins

These are the highest-impact items and should drive the roadmap.

#### 1. Single canonical scaffold contract

Create a single canonical contract for scaffold resources that defines:

- entity naming,
- route naming,
- permission naming,
- page path resolution,
- datagrid columns,
- datagrid filters,
- datagrid actions,
- form metadata,
- and expected supporting files.

This should become the primary source for backend and frontend behavior.

#### 2. `scaffold:doctor` validation command

Add a validator that audits scaffold resources and modules for:

- missing Inertia pages,
- missing frontend ability keys,
- route mismatches,
- missing tests,
- missing module config files,
- missing seeders/factories,
- and datagrid contract drift.

The command should fail loudly and explain the exact fix.

#### 3. Full module CRUD generator

Add a generator that creates the full standard implementation for a module CRUD resource, including:

- definition,
- service,
- controller,
- form request,
- API/inertia resource if needed,
- React pages,
- form component,
- abilities config,
- navigation config,
- seeders,
- factory,
- and baseline PHPUnit tests.

#### 4. Backend-to-frontend typed scaffold export

Make `ScaffoldDefinition` the true source of typed datagrid config and generate or enforce matching TS types so frontend pages do not maintain a parallel handwritten contract.

#### 5. Fully derivable page and route conventions

Remove manual decision points where possible, especially around page path resolution and standard route names.

### Medium wins

#### 1. Scaffold introspection helpers

Add methods such as:

- `expectedPagePaths()`
- `expectedAbilityKeys()`
- `expectedRoutes()`
- `expectedFiles()`
- `expectedTests()`

These should support both generators and validators.

#### 2. Explicit module metadata improvements

Extend module metadata and/or runtime helpers so modules can describe their important contracts more explicitly.

#### 3. Remove stale scaffold concepts

Remove old or misleading concepts that no longer represent the real runtime, such as references to patterns that are no longer used.

#### 4. Standard test helpers for module state

Provide helpers for enabling/disabling modules in tests so the active module set is explicit and less surprising.

#### 5. Datagrid adapter utilities

Provide shared adapters/builders for turning scaffold config into frontend datagrid definitions with less custom page glue code.

#### 6. Golden-path reference module

Maintain one intentionally clean example module that demonstrates the exact recommended pattern end to end.

### Small wins

#### 1. Better inline docs and examples

Add concise file-level examples to scaffold and module runtime classes.

#### 2. AI checklists

Add short implementation checklists to scaffold/module docs.

#### 3. More descriptive naming

Tighten ambiguous API names where clearer naming would reduce guesswork.

#### 4. Debug utilities

Provide a dump/debug command or screen for resolved scaffold and module metadata.

#### 5. Reusable common datagrid primitives

Add shared helpers for common patterns such as status badge columns, search/status filter bundles, and standard destructive actions.

## Proposed architecture changes

## 1. Canonical scaffold contract

Introduce a canonical scaffold metadata layer that becomes the source for:

- Inertia config export,
- frontend datagrid types,
- expected page paths,
- expected route names,
- expected permission keys,
- and generation/validation logic.

This can be implemented by extending `ScaffoldDefinition` rather than inventing a second abstraction.

## 2. Scaffold introspection API

Add explicit methods to the scaffold system for discovery and diagnostics.

Example targets:

- `toInertiaConfig()`
- `expectedFiles()`
- `expectedRoutes()`
- `expectedPermissions()`
- `expectedAbilityKeys()`
- `expectedPageComponentPrefix()`

## 3. Scaffold doctor command

A dedicated command should validate all scaffold resources and optionally all modules.

Suggested checks:

- controller → page path consistency,
- definition → route consistency,
- definition → permission consistency,
- module abilities file contains keys used by pages,
- referenced page files exist,
- generated config shape matches frontend expectations,
- tests exist for CRUD resources,
- module seeders/factories are in expected locations.

## 4. Generator-driven CRUD/module creation

Add a generator that creates the standard file graph from one input.

Suggested command family:

- `php artisan make:module-crud`
- optionally `php artisan scaffold:generate`

The output should align with the doctor command's expectations.

## 5. Stronger datagrid contract alignment

Reduce or eliminate handwritten duplication between:

- backend scaffold config,
- frontend datagrid types,
- and page-level glue code.

Where possible, page code should compose shared helpers rather than rebuild the same mappings.

## 6. Better module test ergonomics

Replace implicit test-environment module behavior with explicit helpers and fixtures so tests declare the module state they depend on.

## Phased implementation plan

### Phase 1 — Contract and cleanup

Deliverables:

- inventory current scaffold/datagrid/module contracts,
- remove stale concepts that no longer reflect runtime,
- define the canonical scaffold contract shape,
- document derived naming/path rules.

### Phase 2 — Introspection and validation

Deliverables:

- scaffold introspection helpers,
- module/scaffold validator command,
- clearer runtime diagnostics,
- baseline convention test coverage.

### Phase 3 — Generation

Deliverables:

- module CRUD generator,
- generated baseline tests,
- generated page/form/config skeletons,
- generator documentation.

### Phase 4 — Datagrid contract unification

Deliverables:

- typed export alignment between PHP and TS,
- reduced page-level mapping boilerplate,
- shared datagrid adapters/primitives.

### Phase 5 — Golden-path rollout

Deliverables:

- one reference module/resource updated to the final standard,
- docs refreshed to point to that implementation,
- remaining module upgrades prioritized incrementally.

## Deliverables

### Required deliverables

- Canonical scaffold contract design.
- `scaffold:doctor` command.
- CRUD/module generator.
- Datagrid contract unification plan and first implementation slice.
- Updated module/scaffold documentation.
- Golden-path example implementation.

### Recommended supporting deliverables

- Test helpers for module state.
- Debug/inspection command for scaffold/module metadata.
- Snapshot or contract tests for `toInertiaConfig()` output.

## Risks

### 1. Overengineering the contract

If the canonical contract becomes too abstract, it may become harder to use than the current conventions.

Mitigation: extend current scaffold patterns rather than replacing them wholesale.

### 2. Generator drift

Generators can become stale if not validated.

Mitigation: the doctor command and golden-path tests must validate generated output.

### 3. Incremental adoption complexity

Existing modules will not all move at once.

Mitigation: introduce the new architecture as the preferred standard, then migrate incrementally.

### 4. Frontend/backend type drift during transition

A partial rollout may temporarily add overlap.

Mitigation: prioritize contract tests and typed adapters early.

## Acceptance criteria

This PRD is complete when implementation planning can proceed with clear milestones and the team agrees on the following target outcomes:

1. A new scaffold CRUD resource can be generated from a single command.
2. A validation command can identify missing files, mismatched abilities, missing pages, and route drift.
3. Scaffold backend config and datagrid frontend config are aligned through a shared contract.
4. Module runtime expectations are explicit and testable.
5. One canonical example shows the full standard.

## Recommended first implementation slice

The first practical slice should be:

1. define the canonical scaffold contract extensions,
2. build `scaffold:doctor`,
3. add introspection helpers for expected files/routes/abilities,
4. create one golden-path generated CRUD example,
5. then build the full generator.

This gives fast architectural feedback before a larger generation rollout.
