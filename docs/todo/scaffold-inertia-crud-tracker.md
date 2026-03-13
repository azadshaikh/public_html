# Scaffold → Inertia CRUD Migration Tracker

## Status legend

- [ ] Not started
- [~] In progress
- [x] Completed
- [!] Blocked

## PRD

- [x] [docs/prds/scaffold-inertia-crud-prd.md](../prds/scaffold-inertia-crud-prd.md)

---

## Phase 1 — Dead code removal

- [x] Delete `Unpoly.php` (265 lines)
- [x] Delete `UnpolyMiddleware.php` (179 lines)
- [x] Delete `ResponseTrait.php` (42 lines)
- [x] Clean `CacheInvalidation.php` — remove `$clearUnpolyCache`, `flagUnpolyClearCache()`
- [x] Clean `NotFoundLogger.php` — remove `X-Up-Target` check
- [x] Clean `Action.php` — remove `$fullReload` + `fullReload()`
- [x] Clean `ScaffoldDefinition` — remove view methods, `toDataGridConfig()`, `toJson()`
- [x] Clean `Scaffoldable` trait — remove `getDataGridConfig()`
- [x] Clean `ScaffoldServiceInterface` — remove `getDataGridConfig()` signature
- [x] Clean `ScaffoldController` — remove `data()`, `buildDataGridResponse()`, Blade view returns
- [x] Remove `ResponseTrait` from 5 child controllers
- [x] Verify no remaining imports or middleware registrations reference deleted files

---

## Phase 2 — Refactor ScaffoldController for Inertia

### 2.1 ScaffoldController rewrite

- [x] Replace imports — remove `JsonResponse`, `View`; add `Inertia\Inertia`, `Inertia\Response`
- [x] Rewrite `index()` → `Inertia::render()` with paginated data + `getIndexViewData()`
- [x] Rewrite `create()` → `Inertia::render()` with `getFormViewData()`
- [x] Rewrite `store()` → `RedirectResponse` with flash status
- [x] Rewrite `show()` → `Inertia::render()` with `transformModelForShow()` + `getShowViewData()`
- [x] Rewrite `edit()` → `Inertia::render()` with `transformModelForEdit()` + `getFormViewData()`
- [x] Rewrite `update()` → `RedirectResponse` — remove `expectsJson()` branching
- [x] Rewrite `destroy(id)` → `RedirectResponse` — remove `Request` param
- [x] Rewrite `restore(id)` → `RedirectResponse` — remove `Request` param
- [x] Rewrite `forceDelete(id)` → `RedirectResponse` — remove `Request` param
- [x] Rewrite `bulkAction()` → `RedirectResponse` instead of `JsonResponse`
- [x] Add abstract `inertiaPage(): string`
- [x] Add `getIndexViewData()`, `getShowViewData()` hooks
- [x] Add `transformModelForShow()`, `transformModelForEdit()` hooks
- [x] Change `buildCreateSuccessMessage()` / `buildUpdateSuccessMessage()` return type to `string`
- [x] Run Pint

### 2.2 Add `inertiaPage()` to all child controllers

- [x] `UserController` → `'users'`
- [x] `RoleController` → `'roles'`
- [x] `QueueMonitorController` → `'masters/queue-monitor'`
- [x] `EmailProviderController` → `'masters/email/providers'`
- [x] `GroupController` → `'masters/groups'`
- [x] `AddressController` → `'masters/addresses'`
- [x] `EmailLogController` → `'masters/email/logs'`
- [x] `GroupItemController` → `'masters/group-items'`
- [x] `EmailTemplateController` → `'masters/email/templates'`
- [x] `ActivityLogController` → `'logs/activity-logs'`
- [x] `NotFoundLogController` → `'logs/not-found-logs'`
- [x] `LoginAttemptController` → `'logs/login-attempts'`

### 2.3 Fix incompatible method overrides in child controllers

- [x] `QueueMonitorController` — rewrite `index()`, `destroy()` for Inertia signatures
- [x] `ActivityLogController` — rewrite `index()` for Inertia signatures
- [x] `NotFoundLogController` — rewrite `index()`, `show()` for Inertia signatures
- [x] `LoginAttemptController` — rewrite `index()`, `show()` for Inertia signatures
- [x] `GroupItemController` — rewrite `index()`, `store()`, `update()`, `destroy()`, `restore()`, `forceDelete()`
- [x] `RoleController` — rewrite `create()`/`edit()` → `getFormViewData()`, `show()`, `destroy()`, `forceDelete()`, `bulkAction()`, remove `deletionBlockedResponse()`
- [x] `UserController` — rewrite `index()`, `show()`, `update()`, `destroy()`, `bulkAction()`, `suspend()`, `ban()`, `unban()`
- [x] Run Pint
- [x] Verify no regressions (94 passing tests unchanged, 41 pre-existing failures unrelated)

---

## Phase 3 — ScaffoldDefinition: Inertia config export

- [x] Add `toInertiaConfig(): array` method to `ScaffoldDefinition`
- [x] Export `columns` — visible columns via `Column::toArray()` (key, label, type, sortable, searchable, width, align, template, meta)
- [x] Export `filters` — via `Filter::toArray()` (type, key, options, placeholder, multiple, dependsOn)
- [x] Export `actions` — authorized actions via `Action::toArray()` (key, label, icon, route, method, confirm, variant, scope, conditions)
- [x] Export `statusTabs` — via `StatusTab::toArray()` (key, label, value, icon, color, isDefault)
- [x] Export `settings` — perPage, defaultSort, defaultDirection, enableBulkActions, enableExport, hasNotes, entityName, entityPlural, routePrefix, statusField
- [x] Clean `Filter::toArray()` — removed Blade HTML rendering (`buildDateRangeDatepickerHtml()`, `renderHtml`)
- [x] Clean `Action::resolveForRow()` — removed orphaned `fullReload` reference
- [x] `Column::toArray()` — already clean, no changes needed
- [x] `StatusTab::toArray()` — already clean, no changes needed
- [x] Wire `toInertiaConfig()` into `ScaffoldController::index()` as `config` prop
- [x] Add tests for `toInertiaConfig()` output shape (11 tests, 60 assertions)
- [x] Run Pint

---

## Phase 4 — Extend React DataGrid component

### 4.1 Filter types

- [x] Add `dateRange` filter — `DatagridDateRangeFilter` type + `DatagridDateRangeFilterField` component (Calendar range picker with Popover, comma-separated ISO dates)
- [x] Add `boolean` filter — `DatagridBooleanFilter` type + `DatagridBooleanFilterField` component (All/Yes/No select)
- [x] Add `number` filter — `DatagridNumberFilter` type + `DatagridNumberFilterField` component (number input with min/max/step)
- [x] Add `multiple` prop on `DatagridSelectFilter` type (type-level support; multi-select UI deferred to Phase 5 if needed)
- [x] Extract filter field components into `datagrid-filters.tsx`

### 4.2 Column type rendering

- [x] Add `DatagridColumnType` union: `'text'` | `'badge'` | `'boolean'` | `'currency'` | `'image'` | `'link'` | `'date'`
- [x] Add `type` prop on `DatagridColumn<T>` — optional, defaults to `'text'`
- [x] Make `cell` callback optional on `DatagridColumn<T>` (was mandatory)
- [x] Create `datagrid-cell-renderers.tsx` with `renderCellByType()` — dispatches to text/badge/boolean/currency/image/link/date renderers
- [x] Smart badge variant mapping: active/published/verified→default, inactive/draft/pending→secondary, banned/deleted/failed→destructive
- [x] Wire fallback in `datagrid-results.tsx`: `column.cell ? column.cell(row) : renderCellByType(...)`

### 4.3 Action improvements

- [x] Add `method` prop on `DatagridAction` — `'GET'` | `'POST'` | `'PUT'` | `'PATCH'` | `'DELETE'`
- [x] Add `confirm` prop on `DatagridAction` — confirmation dialog message
- [x] Add `hidden` prop on `DatagridAction` — for status-based visibility (evaluated per-row by `rowActions()` callback)
- [x] Rewrite `datagrid-action-menu.tsx` — AlertDialog for confirm, Inertia `router[method]()` for non-GET methods, filter hidden actions
- [x] `showOnStatus` / `hideOnStatus` — handled at page level via `rowActions()` callback; `hidden` prop filters in action menu

### 4.4 Bulk action improvements

- [x] Add `confirm` prop on `DatagridBulkAction<T>` — confirmation dialog message
- [x] Wire AlertDialog for bulk action confirmation in `datagrid-results.tsx`
- [x] `select_all` across pages — deferred to Phase 5 (needs backend endpoint to return all IDs)

### 4.5 Soft-delete / trash support

- [x] No DataGrid component changes needed — fully supported via existing `tabs` (Trash tab with count) and `rowActions()` callback (conditionally return restore/force-delete actions with `method: 'POST'`/`method: 'DELETE'` + `confirm`)
- [x] Will be wired at page level in Phase 5 (Users CRUD)

### 4.6 Quality

- [x] ESLint pass on all DataGrid changes — clean

---

## Phase 5 — Users CRUD migration

### 5.1 Backend

- [x] Finalize `UserController` Inertia responses for index/create/edit/show
- [x] Fix `getFormViewData()` — return `initialValues` + `availableRoles` with correct shape
- [x] Fix `show()` — resolve UserResource to array via `toArray(request())` to avoid data wrapper
- [x] Add `UserService::getPaginatedUsers()` — paginated query with UserResource::collection()
- [x] Fix `UserService::applyFilters()` — support comma-separated `created_at` from DataGrid date_range filter
- [x] Fix flash messages — pass `session('status')` and `session('error')` in index/show Inertia responses
- [x] Fix `is_system` role derivation — no DB column; derive from superUserRoleId comparison
- [x] Verify `UserRequest` — no changes needed
- [x] Wayfinder routes already generated for user routes

### 5.2 Users index page (React)

- [x] Status tabs: All, Active, Pending (conditional), Suspended, Banned, Trash — with counts from statistics
- [x] Filters: search, role_id (select), email_verified (select), gender (select), created_at (date_range)
- [x] Columns: user info (Avatar + Link to show_url), email verified (badge), roles (badge list), status (badge with variant), created_at (human format)
- [x] Row actions: driven by backend `UserResource::getActions()` — show, edit, impersonate, suspend/ban/unban, delete/restore/force-delete
- [x] Bulk actions: delete, suspend, ban, unban, restore, force-delete — all with confirm dialogs
- [x] Card view with 3-column grid (Status/Verified/Registered) + role badges
- [x] Icon mapping from Remix Icons to Lucide icons
- [x] Status badge variant mapping (active→default, pending→outline, suspended→secondary, banned→destructive)

### 5.3 Users create/edit form (React)

- [x] Existing `managed-user-form.tsx` works correctly with fixed `getFormViewData()` shape
- [x] Backend now provides `initialValues` (name/email/active/roles/password) and `availableRoles` (id/name/display_name/is_system)

### 5.4 Users show page (React)

- [x] Identity header: Avatar, name, status badge, trashed badge, email, username, tagline, role badges
- [x] Action buttons: Back, Edit, Impersonate, Suspend/Ban/Unban, Restore, Delete/Force Delete
- [x] Tab — Overview: Personal Information card + Account Details card + Social Links card
- [x] Tab — Activity: Timeline-style activity log with causer name and human dates

### 5.5 Custom user actions

- [x] Verify email — POST via row action
- [x] Suspend — PATCH via action button + row action
- [x] Ban — PATCH via action button + row action
- [x] Unban — PATCH via action button + row action
- [x] Impersonate — fullReload via window.location.href
- [x] Bulk suspend/ban/unban — POST to bulkAction endpoint with confirm dialogs

### 5.6 Quality

- [x] Run Pint — pass
- [x] ESLint pass on user pages — clean
- [x] TypeScript errors — 0 across all modified files
- [x] PHPUnit tests — 34 tests, 229 assertions, all passing
    - Authentication/authorization (guest redirect, permission denied, admin access)
    - Index Inertia response (component, props shape, statistics, user resource shape)
    - Filters (search, role, email_verified, date_range, status tab, filter state preservation)
    - Show page (component, user data, activities, trashed user access)
    - Create/edit pages (component, initialValues, availableRoles)
    - Destroy (soft delete, super user protection)
    - Status actions (suspend, ban, unban, restore)
    - Bulk actions (delete, suspend, ban, unban, super user protection, validation)
    - Verify email action

### 5.7 Post-phase bug fixes

- [x] Fix `links.find is not a function` runtime error — `UserResource::collection()` serializes `links` as object (`{first, last, prev, next}`) with page links under `meta.links`; changed `UserService::getPaginatedUsers()` to use `$paginator->toArray()` format and replace only `data` key with resource-transformed items
- [x] Add compact pagination as system-wide backend default — `onEachSide(1)` added to `Scaffoldable::getData()` and `UserService::getPaginatedUsers()` so all scaffold controllers show 3 page links around active page instead of Laravel default 7
- [x] Revert frontend windowing code from `datagrid-pagination.tsx` — removed `windowPageLinks()` function, `DEFAULT_ON_EACH_SIDE` constant, `onEachSide` prop; pagination windowing is handled entirely by backend `onEachSide(1)`

---

## Phase 6 — Roles CRUD migration

### 6.1 Backend

- [x] Override `RoleController::index()` — status-based filtering, paginated roles with RoleResource
- [x] Add `RoleService::getPaginatedRoles()` — paginated query with `RoleResource` transformation
- [x] Add `RoleService::getGroupedPermissionsForRole()` — permissions grouped by module_slug
- [x] Override `RoleController::show()` — rich role detail with grouped permissions, audit info, statistics
- [x] Override `RoleController::getFormViewData()` — consistent initialValues pattern
- [x] Refine `RoleResource` — added `is_system`, `is_trashed`, `show_url`, `status_label`, `status_class`
- [x] Remove dead `/data` route from `web.php`
- [x] Verify routes and Wayfinder generation

### 6.2 Roles index page refinement

- [x] Replace scope tabs (System/Custom) with status tabs (All/Active/Inactive/Trash)
- [x] Add Show row action with link to role detail page
- [x] Add soft-delete support (Trash tab, restore/force-delete row actions)
- [x] Update bulk actions with trash-aware visibility (delete vs restore/force-delete)
- [x] Role name column now links to show page with System badge inline
- [x] Status column shows Active/Inactive/Trashed badge instead of System/Custom
- [x] Card view updated with show link and proper status badges

### 6.3 Roles show page (new)

- [x] Create `resources/js/pages/roles/show.tsx`
- [x] Identity header: display_name, status badge, Protected badge (super user), Trashed badge, description, system name
- [x] Action buttons: Back, Edit, Restore, Delete/Force Delete, Trash (context-aware)
- [x] Super User role protection notice (Alert)
- [x] Trashed warning banner (destructive Alert)
- [x] Left column: Role Information card, Statistics card (3-stat grid), Audit Log card
- [x] Right column: Permissions tab (grouped by module_slug, 2-column card grid with permission checkmarks), Notes tab (placeholder)
- [x] Types: added `RoleShowDetail`, `RolesShowPageProps` to `types/role.ts`

### 6.4 Roles create/edit form refinement

- [x] Reviewed — form handles name, display_name, description, permissions correctly
- [x] Status field not added (status managed via index page actions, not form)
- [x] Role form types (`RoleFormValues`, `RoleEditingTarget`) remain compatible

### 6.5 Quality

- [x] Run Pint — pass
- [x] ESLint pass on all role files
- [x] TypeScript — 0 errors in modified files
- [x] Frontend build — successful

---

## Phase 7 — Tests and quality (all CRUDs)

- [ ] ScaffoldController base tests — index pagination, filters, sorting
- [ ] ScaffoldController base tests — CRUD create, update, delete
- [ ] ScaffoldController base tests — bulk actions
- [x] UserController tests — all Inertia response assertions (done in Phase 5)
- [x] UserController tests — custom actions: suspend, ban, unban, verify email (done in Phase 5)
- [x] UserController tests — permission checks (done in Phase 5)
- [x] UserController tests — super-user protection (done in Phase 5)
- [x] UserController tests — status transitions (done in Phase 5)
- [x] UserController tests — bulk operations (done in Phase 5)
- [ ] RoleController tests — CRUD operations
- [ ] RoleController tests — permission checks
- [ ] RoleController tests — super-role protection (ID 1)
- [ ] RoleController tests — bulk actions with protection
- [ ] RoleController tests — show page with grouped permissions
- [ ] Fix pre-existing test failures across entire test suite
- [x] Run `vendor/bin/pint --dirty` (done in Phase 5)
- [ ] PHPStan / Larastan pass
- [x] ESLint pass on all frontend changes (done in Phase 5)
- [ ] Full test suite green

---

## Open decisions

- [x] How should `toInertiaConfig()` handle action visibility rules (permissions, status conditions)?
    - **Resolved:** Actions are filtered by `authorized()` (permission check) at export time. Status conditions (`showOnStatus`/`hideOnStatus`) are exported as `conditions` array for the frontend to evaluate per-row.
- [ ] Should DataGrid accept the full config object or decomposed props?
- [ ] Should Users show page use Inertia deferred props for tabs (activity, notes)?
- [ ] Avatar upload strategy — direct upload or media library integration?
