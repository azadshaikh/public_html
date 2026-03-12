# Scaffold → Inertia CRUD Migration PRD

## Summary

Migrate the existing Astero Scaffold CRUD system to work natively with Inertia v3 + React. This is a greenfield refactor — no backward compatibility, no dual Blade/JSON patterns. The Scaffold runtime is modified in-place to produce `Inertia::render()` responses and `RedirectResponse` flash messages instead of Blade views, JSON responses, and Unpoly fragments.

The end result is a working Users CRUD (index, create, edit, show) built on the refactored Scaffold, proving the pattern is reusable, followed by Roles CRUD as a second validation.

## Problem

The current Scaffold system (`ScaffoldController`, `ScaffoldDefinition`, `Scaffoldable` trait, services, definitions) is architecturally sound but wired to a dead rendering stack:

- Controllers return `view()` and `JsonResponse` with `expectsJson()` branching.
- Definitions resolve Blade view paths and produce jQuery DataGrid config arrays.
- The middleware layer references Unpoly request/response headers.
- The frontend transport layer (`ResponseTrait`, `Unpoly.php`, `UnpolyMiddleware.php`) is entirely unused.

None of this renders in the current Inertia + React app. No CRUD works at all. The scaffold business logic (query building, CRUD operations, bulk actions, hooks, fluent column/filter/action builders) is solid and should be preserved.

## Product goal

A fully functional Inertia-native CRUD foundation where:

- `ScaffoldController` renders Inertia pages and redirects with flash messages.
- `ScaffoldDefinition` exports column, filter, action, and status tab metadata as Inertia props.
- The React `DataGrid` component consumes scaffold metadata directly.
- Users CRUD is fully rebuilt as the template implementation.
- Roles CRUD validates the pattern is reusable for a second resource.

## Principles

1. **Greenfield** — no backward compatibility, no dual Blade/JSON patterns.
2. **Refactor in-place** — modify `ScaffoldController`, `ScaffoldDefinition`, etc. directly.
3. **Remove all dead code** — Unpoly, Blade view resolution, jQuery DataGrid config, legacy toast responses.
4. **Keep what works** — The `Scaffoldable` trait's query building, CRUD logic, bulk actions, and hook methods are solid. The `Column`, `Filter`, `Action`, `StatusTab` fluent builders are well-designed. The service/definition split is clean.

## Scope

### In scope

- Dead code removal from scaffold system and related files.
- `ScaffoldController` rewrite for Inertia responses.
- `ScaffoldDefinition` Inertia config export method.
- React `DataGrid` enhancements to consume scaffold metadata.
- Users CRUD backend + frontend rebuild.
- Roles CRUD migration as pattern validation.
- PHPUnit test coverage for scaffold and CRUD operations.

### Out of scope

- Migrating other CRUD modules beyond Users and Roles.
- Media upload/browse flows (separate Phase 7 track).
- Database schema changes or migration rewrites.
- Module navigation restructuring (already decided in Phase 5 of main tracker).
- New shared polymorphic systems (notes, addresses, activity — those are Phase 6).

## Architecture

### Backend flow

```
Controller (extends ScaffoldController)
  → calls service()->getData() for index queries
  → calls service()->create/update/delete for mutations
  → returns Inertia::render() with page props
  → redirects with flash('status', message) on mutations

ScaffoldDefinition
  → toInertiaConfig() exports columns/filters/actions/tabs as typed arrays
  → consumed by controller and passed as Inertia page props

Service (uses Scaffoldable trait)
  → unchanged query building, CRUD, bulk actions, hooks
```

### Frontend flow

```
React page (e.g. pages/users/index.tsx)
  → receives props from Inertia including config, data, filters
  → passes config to DataGrid component
  → DataGrid renders columns, filters, status tabs, actions from config
  → uses Inertia router.get() with preserveState for filter/sort/paginate
  → uses Inertia router.delete/post for mutations
```

### Key contracts

| Contract                                  | Returns                                |
| ----------------------------------------- | -------------------------------------- |
| `ScaffoldController::index()`             | `Inertia\Response \| RedirectResponse` |
| `ScaffoldController::create()`            | `Inertia\Response`                     |
| `ScaffoldController::store()`             | `RedirectResponse`                     |
| `ScaffoldController::show()`              | `Inertia\Response`                     |
| `ScaffoldController::edit()`              | `Inertia\Response`                     |
| `ScaffoldController::update()`            | `RedirectResponse`                     |
| `ScaffoldController::destroy(id)`         | `RedirectResponse`                     |
| `ScaffoldController::restore(id)`         | `RedirectResponse`                     |
| `ScaffoldController::forceDelete(id)`     | `RedirectResponse`                     |
| `ScaffoldController::bulkAction(Request)` | `RedirectResponse`                     |
| `ScaffoldDefinition::toInertiaConfig()`   | `array`                                |
| `ScaffoldController::inertiaPage()`       | `string` (abstract)                    |
| `ScaffoldController::service()`           | `ScaffoldServiceInterface` (abstract)  |

### Hook methods preserved

- `getFormViewData(Model)` — extra data for create/edit pages.
- `getIndexViewData(Request)` — extra data for index page.
- `getShowViewData(Model)` — extra data for show page.
- `transformModelForShow(Model)` — transform model before show render.
- `transformModelForEdit(Model)` — transform model before edit render.
- `handleCreationSideEffects(Model)` — post-create hook.
- `handleUpdateSideEffects(Model)` — post-update hook.
- `handleDeletionSideEffects(Model)` — post-delete hook.
- `capturePreviousValues(Model, array)` — capture values before update for activity logging.

## Phase breakdown

### Phase 1 — Dead code removal ✅

Remove everything that exists only for the old Blade/Unpoly/jQuery stack.

| Action                           | Target                                                                                                                              |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Delete `Unpoly.php`              | 265-line Unpoly request/response handler                                                                                            |
| Delete `UnpolyMiddleware.php`    | 179-line middleware                                                                                                                 |
| Delete `ResponseTrait.php`       | 42-line trait used by controllers                                                                                                   |
| Clean `CacheInvalidation.php`    | Remove `$clearUnpolyCache` params, `flagUnpolyClearCache()`                                                                         |
| Clean `NotFoundLogger.php`       | Remove 3-line `X-Up-Target` check                                                                                                   |
| Clean `Action.php`               | Remove `$fullReload` + `fullReload()`                                                                                               |
| Clean `ScaffoldDefinition`       | Remove `getIndexView()`, `getCreateView()`, `getEditView()`, `getShowView()`, `resolveViewPath()`, `toDataGridConfig()`, `toJson()` |
| Clean `Scaffoldable` trait       | Remove `getDataGridConfig()`                                                                                                        |
| Clean `ScaffoldServiceInterface` | Remove `getDataGridConfig()` signature                                                                                              |
| Clean `ScaffoldController`       | Remove `view()` calls, Blade return types, `buildDataGridResponse()`, `data()` endpoint                                             |
| Clean child controllers          | Remove `ResponseTrait` usage from 5 controllers                                                                                     |

### Phase 2 — Refactor ScaffoldController for Inertia ✅

Rewrite `ScaffoldController` so every method returns `Inertia::render()` or `RedirectResponse`.

| Item                         | Detail                                                                                          |
| ---------------------------- | ----------------------------------------------------------------------------------------------- |
| `index()`                    | `Inertia::render()` with paginated rows, filters, stats, config. No separate `data()` endpoint. |
| `create()`                   | `Inertia::render()` with `getFormViewData()`                                                    |
| `store()`                    | `RedirectResponse` with flash status message                                                    |
| `show()`                     | `Inertia::render()` with model + `getShowViewData()`                                            |
| `edit()`                     | `Inertia::render()` with model + `getFormViewData()`                                            |
| `update()`                   | `RedirectResponse` with flash status message                                                    |
| `destroy(id)`                | `RedirectResponse` — no `Request` param                                                         |
| `restore(id)`                | `RedirectResponse` — no `Request` param                                                         |
| `forceDelete(id)`            | `RedirectResponse` — no `Request` param                                                         |
| `bulkAction(Request)`        | `RedirectResponse` instead of `JsonResponse`                                                    |
| New abstract `inertiaPage()` | Returns page component path prefix (e.g. `'users'`)                                             |
| All 12 child controllers     | Add `inertiaPage()` implementation, fix incompatible overrides                                  |

### Phase 3 — ScaffoldDefinition: Inertia config export ✅

Added `toInertiaConfig()` that produces props the React DataGrid consumes directly.

| Export       | Shape                                                                                                                                                     |
| ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `columns`    | Visible columns via `Column::toArray()` — `key`, `label`, `type`, `sortable`, `searchable`, `width`, `align`, `template`, `meta`                          |
| `filters`    | All filters via `Filter::toArray()` — `key`, `label`, `type`, `options`, `placeholder`, `multiple`, `dependsOn`                                           |
| `actions`    | Authorized actions via `Action::toArray()` — `key`, `label`, `icon`, `route`, `method`, `confirm`, `variant`, `scope`, `conditions`                       |
| `statusTabs` | All tabs via `StatusTab::toArray()` — `key`, `label`, `value`, `icon`, `color`, `isDefault`                                                               |
| `settings`   | `perPage`, `defaultSort`, `defaultDirection`, `enableBulkActions`, `enableExport`, `hasNotes`, `entityName`, `entityPlural`, `routePrefix`, `statusField` |

Cleanup performed:

- Removed Blade HTML rendering from `Filter::toArray()` (`buildDateRangeDatepickerHtml()`, `renderHtml`)
- Removed orphaned `fullReload` reference from `Action::resolveForRow()`
- `Column::toArray()` and `StatusTab::toArray()` were already clean

Wired into `ScaffoldController::index()` as `config` prop. Tested with 11 tests / 60 assertions.

### Phase 4 — Extend React DataGrid component ✅

Bridge the gap between current DataGrid support and what Scaffold provides.

| Area             | Changes                                                                                                                         |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| Filter types     | Added `dateRange` (Calendar range picker), `boolean` (All/Yes/No), `number` (min/max/step). `multiple` on select at type level. |
| Column rendering | Added `type` prop with 7 built-in renderers: text, badge, boolean, currency, image, link, date. `cell` callback now optional.   |
| Actions          | Added `confirm` (AlertDialog), `method` (Inertia router visit), `hidden` prop. Status visibility via `rowActions()` callback.   |
| Bulk actions     | Added `confirm` (AlertDialog). Cross-page `select_all` deferred to Phase 5 (needs backend ID endpoint).                         |
| Soft-delete      | No DataGrid changes needed — supported via existing tabs + rowActions with method/confirm. Wired at page level in Phase 5.      |

**Files created:** `datagrid-filters.tsx` (4 filter field components), `datagrid-cell-renderers.tsx` (7 type renderers with smart badge variant mapping).
**Files modified:** `types.ts` (5 new types, optional `cell`, `method`/`confirm`/`hidden` on actions), `datagrid.tsx` (barrel exports), `datagrid-toolbar.tsx` (refactored to use extracted filter components), `datagrid-results.tsx` (cell renderer fallback + bulk confirm dialog), `datagrid-action-menu.tsx` (AlertDialog + Inertia router method support).

### Phase 5 — Users CRUD migration

Rebuild Users CRUD on the refactored Scaffold with full Inertia React pages.

#### 5.1 Backend

- `UserController` — already extends refactored `ScaffoldController`, already returns Inertia responses.
- `UserDefinition` — keep existing columns/filters/actions/statusTabs, wire `toInertiaConfig()`.
- `UserService` — keep as-is.
- `UserRequest` — keep as-is.
- Routes — simplify, remove `data()` endpoint, keep custom action routes.

#### 5.2 Users index page

- Status tabs: All, Active, Pending (conditional), Suspended, Banned, Trash.
- Filters: search, role, email verification, gender, date range.
- Columns: user info (avatar + name + email), email verified, roles, status, created at.
- Row actions: show, edit, impersonate, suspend/ban/unban (status-conditional), delete/restore/force-delete.
- Bulk actions: delete, suspend, ban, unban, restore, force-delete.
- Registration settings summary bar.

#### 5.3 Users create/edit form

Two-column layout:

**Main column:**

- Basic Info — first_name, last_name, email, username
- Contact — address1, address2, city, state, zip, country, phone
- Personal — birth_date, gender
- Profile — tagline, bio
- Social Links — website, twitter, facebook, instagram, linkedin

**Sidebar:**

- Account Settings — status (enum select), password/confirmation
- Avatar — image upload/preview
- Roles — multi-select checkboxes
- Details (edit only) — created_at, updated_at, last_login (read-only)

#### 5.4 Users show page

- Command center: avatar, status badge, identity info, lifecycle dates.
- Operations panel: action buttons (edit, impersonate, verify email, approve, suspend, ban).
- Tabbed content: General (roles, addresses, social, bio), Notes, Metadata, Activity log.

#### 5.5 Custom user actions

- Verify email, approve, suspend, ban, unban — Inertia POST/PATCH visits.
- Impersonate / stop impersonating — full page reload.
- Bulk suspend/ban/unban.

### Phase 6 — Tests and quality

| Area                    | Detail                                                                          |
| ----------------------- | ------------------------------------------------------------------------------- |
| ScaffoldController base | Index pagination/filters/sorting, CRUD operations, bulk actions                 |
| UserController          | All Inertia responses, custom actions, permission checks, super-user protection |
| Status transitions      | All status changes, bulk operations                                             |
| Formatting              | `vendor/bin/pint --dirty`                                                       |
| Static analysis         | PHPStan / Larastan                                                              |
| Frontend lint           | ESLint                                                                          |

### Phase 7 — Roles CRUD migration

Apply the same pattern to validate the scaffold template works for a second resource. Roles is simpler (fewer custom actions) and proves the pattern is reusable.

## What stays unchanged

| System                                   | Notes                                                                                          |
| ---------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `Scaffoldable` trait                     | Query building, CRUD operations, bulk actions, all hooks (minus removed `getDataGridConfig()`) |
| `Column`, `Filter`, `StatusTab` builders | Fluent APIs preserved, `toArray()` output modernized                                           |
| `Action` builder                         | Remove `fullReload`, keep everything else                                                      |
| `ScaffoldRequest`                        | `uniqueRule()`, `isUpdate()`, `enumRule()` helpers                                             |
| `ScaffoldResource`                       | Model-to-array transformation                                                                  |
| All 13 definitions                       | CRUD config stays correct                                                                      |
| All 12 services                          | Business logic unchanged                                                                       |
| `ScaffoldServiceInterface`               | Clean contract (minus removed `getDataGridConfig()`)                                           |

## Success criteria

1. `ScaffoldController` renders all CRUD views via `Inertia::render()` with no Blade or JSON fallbacks.
2. `ScaffoldDefinition::toInertiaConfig()` produces a typed array consumable by the React DataGrid.
3. The React DataGrid renders columns, filters, status tabs, and actions from scaffold config alone.
4. Users index, create, edit, and show pages are fully functional in the browser.
5. All User custom actions (verify, approve, suspend, ban, impersonate) work via Inertia visits.
6. Roles CRUD works using the same scaffold pattern with minimal controller code.
7. PHPUnit tests pass for scaffold base, Users, and Roles.
8. Pint, PHPStan, and ESLint pass.

## Risks

1. DataGrid component may need significant extension to handle all scaffold metadata types.
2. Users CRUD has many edge cases (super-user protection, impersonation, conditional status actions) that may surface gaps in the scaffold hooks.
3. Some definitions may have column/filter configs that assume Blade rendering and need adjustment.

## Mitigations

- Start with the scaffold backend (Phases 1-2) before touching frontend.
- Export config metadata (Phase 3) before extending DataGrid (Phase 4).
- Build Users first as the most complex case — if it works, simpler modules will follow.
- Keep the `cell()` callback escape hatch on DataGrid so pages can override any column rendering.

## Reference tracker

Execution is tracked in [docs/todo/scaffold-inertia-crud-tracker.md](../todo/scaffold-inertia-crud-tracker.md).
