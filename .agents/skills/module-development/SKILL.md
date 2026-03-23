---
name: module-development
description: "Creates and maintains self-contained modules in the internal module system. Use when working in modules/, creating new modules, adding module config files (navigation, abilities), module service providers, module database classes, factories, seeders, or module testing."
license: MIT
metadata:
  author: GitHub Copilot
---

# Module Development

## When to Apply

Activate this skill when:

- Creating a new module from scratch
- Adding features, controllers, models, or services to an existing module
- Working with module config files (`navigation.php`, `abilities.php`, `config.php`)
- Creating or updating module service providers
- Building module database classes (factories, seeders, migrations)
- Writing or running module tests
- Debugging module autoloading, route registration, or status issues
- Any work that touches the `modules/` directory

## Always Activate Alongside

- `laravel-inertia-crud-development` when the module includes CRUD resources
- `inertia-react-development` for module frontend pages and forms
- `inertia-form-system` for module form components
- `ziggy-development` for named route URL generation in module pages
- `tailwindcss-development` for module styling
- `datagrid` when building module index pages with tables
- `shadcn` when composing module UI from shadcn primitives

## Documentation First

Before implementing, use `search-docs`. Search for:

- `service provider boot register config`
- `middleware route groups`
- `inertia render pages`
- `factory seeder migration`

When the module exposes a standard CRUD, inspect or generate it through the scaffold workflow before hand-editing routes, navigation, or abilities.

For new modules, prefer `php artisan make:module-scaffold {Module} {Resource?} --write` so the manifest, provider, routes, abilities, navigation, database seeder, and directory structure all start in the canonical shape.

After module runtime changes, use `php artisan module:inspect {Module?}` or `php artisan module:inspect --json --fail-on-issues` to verify provider resolution, page-root paths, route files, navigation/abilities config, and database seeder metadata.

## Critical Rules

### Self-Containment

Modules MUST be fully self-contained. A user should be able to drop a module folder into `modules/` and enable it without touching any core files.

**NEVER:**

- Add module-specific PSR-4 entries to the root `composer.json`
- Hardcode module abilities in `app/Http/Middleware/HandleInertiaRequests.php`
- Hardcode module types in `resources/js/types/auth.ts`
- Add module-specific code to any file outside `modules/{Name}/`

If a module has Inertia pages with permission-gated UI, the module MUST define matching frontend abilities in `config/abilities.php`. Missing frontend ability declarations can make a module look partially broken even when backend permissions and routes are correct.

**INSTEAD use the aggregation systems:**

- Navigation → `config/navigation.php` (discovered by `NavigationAggregator`)
- Abilities → `config/abilities.php` (discovered by `AbilityAggregator`)
- Seeders → `database/seeders/DatabaseSeeder.php` (discovered by root `DatabaseSeeder::getModuleSeeders()`)
- Routes → `routes/web.php` (loaded by `ModuleServiceProvider::boot()`)
- Migrations → `database/migrations/` (loaded by `ModuleServiceProvider::boot()`)
- Config → `config/{slug}.php` (merged by `ModuleServiceProvider::register()`)
- Views → `resources/views/` (registered by `ModuleServiceProvider::boot()`)
- Translations → `lang/` (registered by `ModuleServiceProvider::boot()`)

### Autoloading

The `ModuleAutoloader` maps `Modules\{Name}\` namespace → `modules/{Name}/app/` and also maps database namespaces to the standard Laravel-style module database directories.

- Database factories go in `modules/{Name}/database/factories/`
- Database seeders go in `modules/{Name}/database/seeders/`
- Migrations stay in `modules/{Name}/database/migrations/` (they are loaded by path, not namespace)

This is the current hard requirement. Do not put factories or seeders under `app/Database/`.

`ModuleAutoloader` itself remains intentionally small, so prefer `module:inspect` when you need actionable diagnostics about missing provider classes, wrong namespaces, or incorrect module paths.

### Frontend Ability Gating

For module CRUD pages, create buttons, row actions, tabs, and other permission-gated UI often depend on `auth.abilities.{camelCaseKey}` values shared through Inertia.

Always verify all three layers match:

1. permission names seeded in the module permission seeder, such as `add_posts`
2. camelCase keys exposed in `config/abilities.php`, such as `addPosts`
3. frontend checks in React pages, such as `page.props.auth.abilities.addPosts`

If any one of these is missing or mismatched, the page may load correctly while important UI like page-header create buttons silently disappears.

## Module Directory Structure

```
modules/{Name}/
├── app/
│   ├── Definitions/{Model}Definition.php  ← Scaffold-based modules
│   ├── Http/
│   │   ├── Controllers/{Model}Controller.php
│   │   ├── Requests/{Model}Request.php
│   │   └── Resources/{Model}Resource.php
│   ├── Models/{Model}.php
│   ├── Providers/{Name}ServiceProvider.php
│   └── Services/{Model}Service.php        ← Scaffold-based modules
├── config/
│   ├── abilities.php                      ← Inertia ability declarations
│   ├── config.php                         ← module-specific config (optional)
│   └── navigation.php                     ← sidebar navigation items
├── database/
│   ├── factories/{Model}Factory.php
│   ├── migrations/
│   │   └── xxxx_xx_xx_xxxxxx_create_{table}_table.php
│   └── seeders/
│       ├── DatabaseSeeder.php            ← auto-discovered by root seeder
│       └── PermissionSeeder.php
├── lang/
│   ├── en/
│   │   └── messages.php
│   └── hi/
│       └── messages.php
├── resources/
│   └── js/
│       ├── components/{model}-form.tsx
│       ├── pages/{slug}/
│       │   ├── index.tsx
│       │   ├── create.tsx
│       │   ├── edit.tsx
│       │   └── show.tsx
│       └── types/{model}.ts
├── routes/
│   ├── web.php
│   └── api.php                            ← optional
├── tests/
│   └── Feature/{Model}CrudTest.php
├── composer.json                          ← optional module metadata
└── module.json                            ← REQUIRED manifest
```

## Module Manifest (`module.json`)

Every module MUST have a `module.json` at its root. Required fields: `name`, `namespace`, `provider`.

```json
{
    "name": "Todos",
    "slug": "todos",
    "version": "1.0.0",
    "description": "Task management module.",
    "namespace": "Modules\\Todos\\",
    "provider": "Modules\\Todos\\Providers\\TodosServiceProvider",
    "page_root": "resources/js/pages/todos",
    "route_files": {
        "web": "routes/web.php"
    },
    "abilities_path": "config/abilities.php",
    "navigation_path": "config/navigation.php",
    "database_seeder": "Modules\\Todos\\Database\\Seeders\\DatabaseSeeder"
}
```

| Field | Required | Description |
|-------|----------|-------------|
| `name` | Yes | PascalCase module name (matches directory name) |
| `slug` | No | URL-safe slug (auto-derived from directory name if omitted) |
| `version` | No | Semver string, defaults to `1.0.0` |
| `description` | No | Human-readable description |
| `namespace` | Yes | PHP namespace with trailing backslash |
| `provider` | Yes | FQCN of the module's service provider |
| `author` | No | Author name |
| `homepage` | No | URL |
| `icon` | No | Icon identifier |
| `page_root` | No | Relative path to the module Inertia page root; defaults to `resources/js/pages/{slug}` |
| `route_files` | No | Relative route files keyed by type, usually `{ "web": "routes/web.php" }` |
| `abilities_path` | No | Relative path to frontend ability declarations |
| `navigation_path` | No | Relative path to module navigation config |
| `database_seeder` | No | FQCN of the module database seeder |

Treat these optional fields as management and inspection metadata by default, not ordinary shared frontend runtime data. Shared module payloads for the app shell should stay limited to the runtime-safe descriptor used by Inertia page resolution, while `module:inspect`, module management screens, and other diagnostics can consume the richer manifest metadata.

## Module Status (`modules.json`)

The root `modules.json` file tracks enabled/disabled status per module:

```json
{
    "Todos": "enabled",
    "ChatBot": "enabled",
    "CMS": "enabled"
}
```

Use the module name (PascalCase, matching the `name` field in `module.json`) as the key.

In tests, do not rely on the implicit testing-manifest switch alone. Prefer `InteractsWithModuleManifest` helpers such as `withEnabledModules()`, `withDisabledModules()`, and `useModuleSandbox()` to make module state explicit.

## Service Provider

Every module needs a service provider extending `ModuleServiceProvider`. It only needs to implement `moduleSlug()`.

```php
<?php

namespace Modules\{Name}\Providers;

use App\Modules\Support\ModuleServiceProvider;

class {Name}ServiceProvider extends ModuleServiceProvider
{
    protected function moduleSlug(): string
    {
        return '{slug}';
    }
}
```

The base `ModuleServiceProvider` automatically:

- Merges `config/{slug}.php` into the app config
- Registers `routes/web.php` with `['web', 'module.enabled:{slug}']` middleware
- Loads views from `resources/views/` under a `{slug}` namespace
- Loads translations from `lang/`
- Loads migrations from `database/migrations/`

Only override `register()` or `boot()` if the module needs additional bindings or setup beyond what the base provides.

## Route Registration

Module routes live in `modules/{Name}/routes/web.php`. The `ModuleServiceProvider` wraps them with `module.enabled:{slug}` middleware automatically, which returns a redirect when the module is disabled.

For scaffold-generated modules, route files can contain marker-managed generated sections. Preserve manual code outside those markers and prefer rerunning the scaffold workflow over manually rewriting generated route blocks.

Standard CRUD route structure:

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\{Name}\Http\Controllers\{Model}Controller;

Route::middleware(['auth', 'user.status', 'verified', 'profile.completed'])
    ->prefix(trim((string) config('app.admin_slug'), '/'))
    ->as('app.')
    ->group(function (): void {
        Route::prefix('{slug}')
            ->as('{slug}.')
            ->group(function (): void {
                // CRUD routes
                Route::post('/bulk-action', [{Model}Controller::class, 'bulkAction'])->name('bulk-action');
                Route::get('/create', [{Model}Controller::class, 'create'])->name('create');
                Route::post('/', [{Model}Controller::class, 'store'])->name('store');
                Route::get('/{model}', [{Model}Controller::class, 'show'])->name('show');
                Route::get('/{model}/edit', [{Model}Controller::class, 'edit'])->name('edit');
                Route::put('/{model}', [{Model}Controller::class, 'update'])->name('update');
                Route::delete('/{model}', [{Model}Controller::class, 'destroy'])->name('destroy');
                Route::delete('/{model}/force-delete', [{Model}Controller::class, 'forceDelete'])->name('force-delete');
                Route::patch('/{model}/restore', [{Model}Controller::class, 'restore'])->name('restore');

                // Index (must be last for optional status param)
                Route::get('/{status?}', [{Model}Controller::class, 'index'])->name('index');
            });
    });
```

Named routes follow the pattern `app.{slug}.{action}` (e.g., `app.todos.index`, `app.todos.create`).

When auditing older modules that still use manual registrations, use `php artisan scaffold:doctor --strict-legacy-registrations` to verify route/controller references, navigation route names, active patterns, and ability hooks still match the scaffold resource.

## Config Integration Points

### Navigation (`config/navigation.php`)

Discovered by `NavigationAggregator`. Returns a `sections` array with sidebar items.

If scaffold registration markers are present, treat the generated section as owned by the scaffold workflow. Keep any custom navigation entries outside that block.

```php
<?php

return [
    'sections' => [
        '{slug}_workspace' => [
            'label' => '{Name}',
            'weight' => 220,
            'area' => 'modules',
            'show_label' => true,
            'items' => [
                '{slug}_list' => [
                    'label' => '{Entity Plural}',
                    'route' => 'app.{slug}.index',
                    'icon' => '<svg>...</svg>',
                    'active_patterns' => ['app.{slug}.*'],
                ],
            ],
        ],
    ],
];
```

**Areas:** `top`, `cms`, `modules`, `bottom`. Module navigation typically uses `modules`.

**Weight** determines sort order within the area — lower values appear first.

### Abilities (`config/abilities.php`)

Discovered by `AbilityAggregator`. Maps camelCase frontend ability names to permission strings.

```php
<?php

return [
    'view{Name}' => 'view_{slug}',
    'add{Name}' => 'add_{slug}',
    'edit{Name}' => 'edit_{slug}',
    'delete{Name}' => 'delete_{slug}',
    'restore{Name}' => 'restore_{slug}',
];
```

These abilities are automatically shared with the Inertia frontend under `auth.abilities` and resolved via `$user->can()`. The frontend `auth.ts` type uses `[key: string]: boolean` to accept dynamic module abilities.

If the module has CRUD index pages with `headerActions`, define the `add*` ability before considering the page complete. This is especially important for AppLayout page headers where the create action is commonly hidden behind a permission check.

Typical CRUD ability set:

- `view{Name}`
- `add{Name}`
- `edit{Name}`
- `delete{Name}`
- `restore{Name}`

Add any extra module-specific capabilities the frontend checks directly, such as `manage{Name}Settings`, `duplicate{Name}`, or preview/review abilities.

### Module Config (`config/config.php`)

Merged into Laravel config under the module slug. Access via `config('{slug}.key')`.

```php
<?php

return [
    'name' => '{Name}',
    'priorities' => [
        'low' => ['label' => 'Low', 'value' => 'low'],
        'high' => ['label' => 'High', 'value' => 'high'],
    ],
];
```

**Important:** The file must be named `config.php` but the config slug used for `mergeConfigFrom` is the module slug returned by `moduleSlug()`.

## Scaffold Integration

For CRUD modules, use the Scaffold system:

### ScaffoldDefinition

Extends `App\Scaffold\ScaffoldDefinition`. Defines columns, filters, status tabs, actions, and entity metadata.

```php
<?php

namespace Modules\{Name}\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;

class {Model}Definition extends ScaffoldDefinition
{
    protected string $routePrefix = 'app.{slug}';
    protected string $permissionPrefix = '{slug}';
    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return \Modules\{Name}\Models\{Model}::class;
    }

    public function getRequestClass(): ?string
    {
        return \Modules\{Name}\Http\Requests\{Model}Request::class;
    }

    public function columns(): array { /* ... */ }
    public function filters(): array { /* ... */ }
    public function statusTabs(): array { /* ... */ }
}
```

### ScaffoldController

Extends `App\Scaffold\ScaffoldController`. Provides `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `restore`, `forceDelete`, and `bulkAction` methods automatically.

```php
<?php

namespace Modules\{Name}\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Modules\{Name}\Services\{Model}Service;

class {Model}Controller extends ScaffoldController implements HasMiddleware
{
    public function __construct(private readonly {Model}Service $service) {}

    public static function middleware(): array
    {
        return (new {Model}Definition)->getMiddleware();
    }

    protected function service(): {Model}Service
    {
        return $this->service;
    }

    protected function inertiaPage(): string
    {
        return '{slug}';
    }

    protected function getFormViewData(Model $model): array
    {
        return [
            'initialValues' => [ /* form default values */ ],
            'statusOptions' => $this->service->getStatusOptions(),
            /* other option lists */
        ];
    }
}
```

**Key:** `inertiaPage()` returns the page directory name (e.g., `'todos'`). The page resolver looks for `modules/{Name}/resources/js/pages/{slug}/*.tsx` and resolves them by stripping the module path prefix. So `Inertia::render('todos/index')` resolves to `modules/Todos/resources/js/pages/todos/index.tsx`.

## Frontend Page Resolution

Module pages are auto-discovered via `import.meta.glob` in [resources/js/lib/inertia-page-resolver.ts](resources/js/lib/inertia-page-resolver.ts):

```typescript
const modulePages = import.meta.glob<InertiaPageModule>(
    '../../../modules/*/resources/js/pages/**/*.tsx',
);
```

### Runtime Module Filtering

All module pages are compiled at build time (Vite produces lazy-loaded chunks for each page). However, at runtime, only **enabled** module pages are registered in the page resolver.

During `createInertiaApp` setup in [resources/js/app.tsx](resources/js/app.tsx), `initModulePageFilter()` is called with the shared `modules` prop. This reads the list of enabled module names and rebuilds the page registry, excluding pages from disabled modules:

```typescript
// In app.tsx setup():
const sharedProps = props.initialPage.props;
initModulePageFilter(sharedProps.modules);
```

This means:
- **Disabled module chunks exist on disk** but are never loaded by the browser.
- **Module enable/disable is instant** — no rebuild required. The backend `module.enabled:{slug}` middleware is the authoritative access guard; the frontend filter is a clean UX layer that prevents stale page resolution errors.
- **On full page reload** (which Inertia forces via asset versioning when module status changes), the filter is re-initialized with the current enabled set.

The resolver normalizes module page paths by extracting the relative path after `resources/js/pages/`. So `modules/Todos/resources/js/pages/todos/index.tsx` becomes `todos/index`.

**Convention:** Place module pages at `modules/{Name}/resources/js/pages/{slug}/` where `{slug}` matches the `inertiaPage()` return value.

Module pages can import shared application components with the `@/` alias (which resolves to `resources/js/`):

```tsx
import AppLayout from '@/layouts/app-layout';
import { AppHead } from '@/components/app-head';
import { useAppForm } from '@/hooks/use-app-form';
```

## Database Classes

### Migrations

Place in `modules/{Name}/database/migrations/`. Auto-loaded by `ModuleServiceProvider::boot()`. Follow standard Laravel naming: `xxxx_xx_xx_xxxxxx_create_{table}_table.php`.

### Factories

Place in `modules/{Name}/database/factories/`. The namespace is `Modules\{Name}\Database\Factories`.

```php
<?php

namespace Modules\{Name}\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\{Name}\Models\{Model};

class {Model}Factory extends Factory
{
    protected $model = {Model}::class;

    public function definition(): array
    {
        return [ /* ... */ ];
    }
}
```

The model must reference the factory explicitly since the module namespace differs from the standard Laravel factory convention:

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class {Model} extends Model
{
    use HasFactory;

    protected static function newFactory(): \Modules\{Name}\Database\Factories\{Model}Factory
    {
        return \Modules\{Name}\Database\Factories\{Model}Factory::new();
    }
}
```

### Seeders

The module's `DatabaseSeeder` is at `modules/{Name}/database/seeders/DatabaseSeeder.php`. It is auto-discovered by the root `DatabaseSeeder::getModuleSeeders()` which looks for `{namespace}Database\Seeders\DatabaseSeeder` in each enabled module.

```php
<?php

namespace Modules\{Name}\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
        ]);
    }
}
```

### Permission Seeder

Modules that use permissions should include a `PermissionSeeder`:

```php
<?php

namespace Modules\{Name}\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'view_{slug}',
            'add_{slug}',
            'edit_{slug}',
            'delete_{slug}',
            'restore_{slug}',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission],
                ['guard_name' => 'web'],
            );
        }
    }
}
```

## Module Creation Workflow

1. Create the module directory: `modules/{Name}/`
2. Create `module.json` with name, namespace, and provider
3. Create the service provider in `app/Providers/{Name}ServiceProvider.php`
4. Create the model in `app/Models/{Model}.php`
5. Create the migration in `database/migrations/`
6. Create the factory in `database/factories/{Model}Factory.php`
7. Create seeders in `database/seeders/`
8. Create the ScaffoldDefinition in `app/Definitions/{Model}Definition.php`
9. Create the service in `app/Services/{Model}Service.php`
10. Create the form request in `app/Http/Requests/{Model}Request.php`
11. Create the controller in `app/Http/Controllers/{Model}Controller.php`
12. Create routes in `routes/web.php`
13. Create `config/navigation.php` for sidebar navigation
14. Create `config/abilities.php` for frontend abilities
15. Create `config/config.php` for module-specific config (optional)
16. Create frontend pages in `resources/js/pages/{slug}/`
17. Create frontend types in `resources/js/types/{model}.ts`
18. Create frontend form component in `resources/js/components/{model}-form.tsx`
19. Add `"{Name}": "enabled"` to root `modules.json`
20. Write feature tests in `tests/Feature/`
21. Run `vendor/bin/pint --dirty --format agent`
22. Run tests: `php artisan test --compact --filter={Model}CrudTest`

## Module Completion Checklist

Before considering a module CRUD flow complete, verify:

- `config/navigation.php` exists and uses valid named routes
- `config/abilities.php` exists and includes all frontend ability keys the pages use
- `inertiaPage()` values match the actual directory names under `resources/js/pages/`
- every referenced page exists: `index`, `create`, `edit`, `show` unless intentionally omitted
- every index page with a create action checks a real frontend ability key
- module seeders and factories live under `app/Database/`
- the module is enabled in `modules.json`
- focused feature tests cover migration or CRUD expectations

## Testing

### Test Location

Module tests live in `modules/{Name}/tests/Feature/`. PHPUnit discovers them automatically.

### Test Namespace

Use the module namespace: `namespace Modules\{Name}\Tests\Feature;`

### Factory Usage

When creating models in tests, use the factory:

```php
$todo = Todo::factory()->create(['title' => 'Test']);
```

### Permission Setup

Tests for permission-protected modules should seed permissions and assign them:

```php
protected function setUp(): void
{
    parent::setUp();

    $this->seed(PermissionSeeder::class);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'view_{slug}', 'add_{slug}', 'edit_{slug}',
        'delete_{slug}', 'restore_{slug}',
    ]);
}
```

### Test Coverage

Every module should have tests for:

- Guest redirect (unauthenticated access)
- Authenticated index access
- Create flow (form + store)
- Update flow (edit + update)
- Delete flow (soft delete, force delete, restore if applicable)
- Validation failure
- Permission enforcement (user without permissions cannot access)
- Filtering/searching on index page
- Frontend ability declarations required by permission-gated page header actions or other module UI

### Verification for Inertia CRUD Modules

When a module uses Inertia CRUD pages, also verify:

- `create`, `edit`, and `show` page component paths match controller render targets
- index-page `headerActions` render for users with the expected permission
- no page is referenced by `inertiaPage()` or custom controller `Inertia::render(...)` calls without an actual `.tsx` file

## Common Pitfalls

- **Adding module entries to root `composer.json`** — the `ModuleAutoloader` handles all autoloading; PSR-4 entries in `composer.json` are never needed for modules.
- **Putting factories/seeders in `app/Database/`** — module database classes now live in `modules/{Name}/database/factories/` and `modules/{Name}/database/seeders/`.
- **Hardcoding module abilities in `HandleInertiaRequests.php`** — use `config/abilities.php` and the `AbilityAggregator` instead.
- **Forgetting `config/abilities.php` entirely** — backend permissions may exist and navigation may work, but frontend permission-gated UI like page-header create buttons will be hidden.
- **Using permission names in React instead of camelCase ability keys** — frontend pages read `auth.abilities.addPosts`, not raw permission slugs like `add_posts`.
- **Creating `config/abilities.php` but omitting keys used by the page** — if the page checks `addWebsites` and the module only exposes `viewWebsites`, the create button will not render.
- **Hardcoding module ability types in `auth.ts`** — the `[key: string]: boolean` index signature handles all module abilities dynamically.
- **Using `config.php` filename but expecting a different config key** — `ModuleServiceProvider::register()` merges `config/{slug}.php` only (where `{slug}` is the return value of `moduleSlug()`). The file MUST be named `config.php` but the config is accessible via `config('{slug}.key')`.
- **Forgetting `module.enabled` middleware** — routes not wrapped by `ModuleServiceProvider` need explicit `module.enabled:{slug}` middleware to check if the module is enabled.
- **Wrong `inertiaPage()` return** — must match the directory name under `resources/js/pages/`. The page resolver strips the module path and uses only the relative page path.
- **Assuming a module is fully migrated because controllers compile** — also confirm every referenced Inertia page exists and every permission-gated action has a matching frontend ability declaration.
- **Missing `newFactory()` override on model** — module models cannot rely on Laravel's default factory resolution. Always implement `protected static function newFactory()`.
- **Creating a module without adding it to `modules.json`** — the module won't be discovered as enabled.
- **Trying to exclude disabled modules at build time** — all module pages are compiled by Vite regardless of enabled/disabled status (they produce lazy-loaded chunks). Runtime filtering via `initModulePageFilter()` prevents disabled module pages from being registered in the page resolver. Do NOT try to conditionally exclude modules from the Vite build — it would require a rebuild every time a module is toggled.
