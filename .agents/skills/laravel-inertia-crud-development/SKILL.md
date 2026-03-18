---
name: laravel-inertia-crud-development
description: 'Builds Laravel 13 + Inertia React v3 CRUD flows for this project, including model scaffolding, Inertia pages, Ziggy routes, filters, uploads, sidebar links, and PHPUnit coverage.'
license: MIT
metadata:
    author: GitHub Copilot
---

# Laravel Inertia CRUD Development

## When to Apply

Activate this skill when:

- Creating a new CRUD in this Laravel 13 + Inertia React v3 project
- Expanding an existing resource with index, create, edit, show, and delete flows
- Building admin-style forms with many field types
- Adding filtering, sorting, pagination, badges, or sidebar navigation for a resource
- Handling file or image uploads in a CRUD form
- Writing tests for create, update, delete, filter, and validation behavior

## Always Activate Alongside

Use this skill together with the existing domain skills that match the work:

- `inertia-react-development` for page components, forms, and navigation
- `ziggy-development` for named route URL generation
- `tailwindcss-development` for layout and styling
- `shadcn` when composing UI from shadcn primitives

## Documentation First

Before implementing, use `search-docs` for project-specific docs such as:

- `resource controllers validation file uploads storage public files`
- `inertia forms file uploads validation`
- `pagination filtering sorting`
- `ziggy inertia react route generation`

Before changing an existing scaffold CRUD, inspect it first with `php artisan scaffold:inspect {resource} --json` so you can see its columns, filters, tabs, policy abilities, page paths, and registration metadata.

If the CRUD lives inside a new module shell, prefer `php artisan make:module-scaffold {Module} {Resource?} --write` instead of assembling the module manifest, provider, routes, abilities, navigation, and seeder files by hand.

If styling or component choices are involved, also use the relevant Tailwind and shadcn guidance.

## CRUD Workflow

Follow this order unless the existing codebase clearly uses a different flow.

1. Inspect sibling patterns and, for scaffold resources, run `scaffold:inspect`
2. Design the schema, field types, filters, and status tabs
3. Prefer `php artisan scaffold:generate` for new standard CRUD resources instead of hand-assembling files
4. If the resource belongs to a brand new module, create the module shell first with `php artisan make:module-scaffold`
5. Implement model fillables, casts, defaults, and option lists
6. Implement migration columns and indexes
7. Build factory and optional seeder data
8. Create a shared form request pattern when store and update rules overlap
9. Build or refine controller index/create/store/show/edit/update/destroy methods
10. Let scaffold registration targets handle generated route/navigation/ability sections where available; avoid hand-editing generated blocks
11. Build Inertia pages and shared form components
12. Update navigation or sidebar links outside generated blocks only when necessary
13. Write focused feature tests
14. Run `scaffold:doctor` after scaffold changes, then Pint, tests, and the relevant frontend validation

## Standard File Map

For a typical resource named `Thing`, prefer this structure:

- `app/Models/Thing.php`
- `database/migrations/*_create_things_table.php`
- `database/factories/ThingFactory.php`
- `database/seeders/ThingSeeder.php` when useful for demos
- `app/Http/Requests/Things/StoreThingRequest.php`
- `app/Http/Requests/Things/UpdateThingRequest.php`
- `app/Http/Requests/Things/ThingFormRequest.php` when store/update overlap
- `app/Http/Controllers/.../ThingController.php`
- `resources/js/pages/.../things/index.tsx`
- `resources/js/pages/.../things/create.tsx`
- `resources/js/pages/.../things/edit.tsx`
- `resources/js/pages/.../things/show.tsx`
- `resources/js/components/.../thing-form.tsx`
- `tests/Feature/.../ThingCrudTest.php`

Stay inside existing folders. Do not create new top-level folders without approval.

## Backend Rules

### Model

Add:

- `fillable`
- `casts()`
- resource option constants when forms need enumerated choices
- helper defaults for form payloads when the frontend needs predictable initial state

Prefer project-readable constants such as status, rating, category, or language maps.

### Migration

Use realistic columns and indexes.

Include:

- strings for labels and slugs
- text for long content
- numerics for prices, scores, counts, or budgets
- booleans for flags
- dates and times where useful
- JSON arrays for grouped multi-select data when appropriate
- nullable media path columns for uploads

### Validation

Prefer a shared abstract request when `store` and `update` mostly match.

Include:

- sanitization in `prepareForValidation()`
- slug normalization when relevant
- array sanitization for checkbox or multi-select payloads
- upload validation rules
- `Rule::unique(...)->ignore(...)` for updates

Expose helper methods for:

- validated attributes without uploads
- uploaded files
- remove-file flags

### Controller

For index pages, return:

- filters
- paginated resource rows
- stats or summaries when the page is dashboard-like
- option lists needed by filters or forms

For forms, return:

- `initialValues`
- current record summary when editing
- option lists

For scaffold-backed resources, prefer the shared scaffold contract from `ScaffoldDefinition::toInertiaConfig()` instead of manually rebuilding datagrid tabs, filters, sort metadata, and per-page defaults in each controller.

If you override a scaffold-backed `index()` action for custom statistics, filter options, or alternate row payloads, keep the scaffold contract intact by still returning:

```php
'config' => $this->service()->getScaffoldDefinition()->toInertiaConfig(),
```

On the frontend, if the index page supplies custom `DatagridColumn[]`, pass `scaffoldColumns={config.columns}` into `<Datagrid>` so widths and scaffold-only columns stay aligned with the backend definition.

Keep readable named route calls explicit in feature pages when the route names are stable. Do not replace clear `route('app.resource.action')` calls with config/meta indirection unless the route truly needs to be dynamic.

For uploads:

- store on the `public` disk unless the app pattern says otherwise
- delete replaced files
- support explicit removal without replacement when the UI needs it

## Frontend Rules

### Page Composition

Prefer:

- a shared form component for create and edit
- separate `index`, `create`, `edit`, and `show` pages
- `AppHead` and breadcrumbs on each page
- `AppLayout` or the project’s normal layout wrapper
- explicit named `route('...')` calls in page and form components when they improve readability

### Inertia Forms

Use `useForm` when the form contains:

- files
- toggles or checkbox arrays
- controlled live previews
- client-side state transformations before submit

Use Ziggy `route()` URLs with `form.submit(method, url)` or `Link href={route('...')}` patterns.

Do not force client-only component navigation for server-rendered CRUD pages unless the target page can safely render without server props.

### Form Inputs

Prefer a rich mix of:

- text inputs
- textareas
- numeric inputs
- date/time inputs
- select inputs
- grouped checkboxes
- toggle groups for small option sets
- switches for booleans
- file inputs with previews

Use existing shadcn form primitives where available.

For feature-specific forms, prefer explicit props such as `initialValues`, option lists, and record summaries. Use scaffold form metadata only when you are intentionally building a reusable metadata-driven form UI.

### Lists and Detail Pages

For index pages, prefer:

- filter bar
- stats cards when useful
- table or card list
- pagination
- badges for statuses and flags
- empty state

For scaffold index pages, derive the datagrid state from `resources/js/lib/scaffold-datagrid.ts` so tabs, filters, sorting, bulk actions, and pagination stay aligned with the backend definition.

When the page intentionally uses custom columns or custom cards, keep display logic in the React page but keep layout metadata in the scaffold definition. Width should be owned by `Column::width()` and consumed through `config.columns`, not duplicated by hard-coded page widths.

For common backend datagrid patterns, prefer explicit `Column`, `Filter`, `StatusTab`, and `Action` definitions in the resource so generated and hand-written CRUDs stay easy to read.

For show pages, prefer:

- quick facts
- grouped metadata sections
- media previews
- outbound links when URL fields exist

## Navigation Rules

If the new CRUD is discoverable in the app, update the sidebar or navigation.

If the resource was generated with scaffold registration markers, keep route, navigation, and module ability edits inside the scaffold workflow. Preserve manual code outside the generated marker blocks.

When adding sidebar links:

- use Ziggy `route()` URLs for link targets
- keep active-state logic based on the current Inertia page name
- do not hardcode a link as permanently active
- avoid passing a `component` prop to `Link` unless an instant/client-only transition is intentional and safe

## Upload Rules

For media uploads:

- store file paths, not raw URLs
- resolve display URLs from the disk when returning props
- support replacing existing files cleanly
- add tests for create, replace, remove, and delete cleanup

If previews are needed in React:

- use object URLs carefully
- revoke object URLs in cleanup
- avoid effect patterns that trigger React compiler warnings

## Testing Checklist

Every CRUD should have focused feature coverage for at least:

- guests redirected away when protected
- authenticated index access
- filtering or searching
- successful create
- successful update
- successful delete
- validation failure on a key field such as slug uniqueness
- upload persistence and cleanup when uploads exist

If SQLite test driver is unavailable in the environment, note that and use the configured working database only when necessary.

## Verification Steps

After implementation:

1. Run `php artisan scaffold:doctor` after scaffold CRUD changes; use `--strict-legacy-registrations` when auditing older manual registrations
2. Run `vendor/bin/pint --dirty --format agent` if PHP changed
3. Run the smallest relevant `php artisan test --compact ...`
4. Run `pnpm build` when frontend pages or shared scaffold/datagird types changed
5. Confirm the CRUD is reachable from the intended navigation

## Common Pitfalls

- Using hardcoded URLs instead of Ziggy `route()` for named routes
- Returning incomplete Inertia props for index pages
- Using client-only navigation patterns that skip required server props
- Forgetting to delete replaced uploads
- Omitting tests for file cleanup
- Adding new dependencies without approval
- Creating overly simple forms when the goal is to exercise many input types

## Good Default CRUD Shape

When the user asks for a demo CRUD, default to:

- realistic mixed field types
- multiple filter controls
- paginated index view
- create, edit, show, delete flows
- media upload support
- seeded sample records
- sidebar navigation entry when appropriate
- end-to-end feature coverage for the resource
