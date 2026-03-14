```skill
---
name: ziggy-development
description: "Activates whenever referencing backend routes in frontend components. Use when generating URLs with Ziggy's route() function, working with named Laravel routes in TypeScript, or debugging route resolution."
license: MIT
metadata:
  author: GitHub Copilot
---

# Ziggy Development

## When to Apply

Activate whenever referencing backend routes in frontend components:
- Generating URLs with Ziggy's `route()` function
- Building `<Link href>`, `router.visit()`, or `form.submit()` targets
- Checking the current route for active-link styling
- Debugging route name resolution or parameter binding
- Working with route filtering, `safeRoute()`, or `hasRoute()`

## Documentation

Use `search-docs` for detailed Ziggy patterns and documentation.

## How Ziggy Works

Ziggy exposes Laravel named routes to the frontend via the `ZiggyRouteFilter` service in `resources/views/app.blade.php`. This service renders a JavaScript object containing **only the routes the current user is authorised to see**, based on their role and permissions.

The global `route()` function is then available in all TypeScript/JavaScript files without explicit imports.

### Route Definitions Lifecycle

- Route definitions are generated server-side by `ZiggyRouteFilter::render()` on full page loads.
- Routes are **filtered per user role**: super users get all routes, guests see only public routes, other users see routes matching their permissions.
- Inertia SPA navigation (XHR) does NOT re-render `<head>`, so route definitions stay fixed until the next full page load.
- When the admin slug or user role changes, Inertia asset versioning (includes role ID in the hash) forces a full page reload so routes are re-rendered with the updated set.
- Filtered route sets are cached per role ID for performance. Call `ZiggyRouteFilter::clearCache()` when permissions or route groups change.

### Route Groups (`config/ziggy.php`)

Route groups define which named routes are included for each access level:

| Group          | Access Level                      | Route Patterns                    |
|----------------|-----------------------------------|-----------------------------------|
| `public`       | Everyone (guests included)        | `login`, `register`, `password.*` |
| `authenticated`| All logged-in users               | `dashboard`, `app.profile.*`, `app.settings.*`, `app.media.*` |
| `users`        | Requires `view_users` permission  | `app.users.*`                     |
| `roles`        | Requires `view_roles` permission  | `app.roles.*`                     |
| `modules`      | Requires `manage_modules`         | `app.masters.modules.*`, `cms.*`  |
| `masters`      | Super user only                   | `app.masters.settings.*`, `app.masters.groups.*`, `app.masters.laravel-tools.*` |
| `logs`         | Super user only                   | `app.logs.*`, `log-viewer.*`      |
| `broadcast`    | Super user only                   | `app.notifications.broadcast.*`   |

### Safe Route Helpers (`@/lib/safe-route`)

When routes are filtered, a component may reference a route not present for the current user. Use these helpers:

```typescript
import { safeRoute, hasRoute } from '@/lib/safe-route';

// safeRoute — same API as route(), returns '/404' if missing
safeRoute('app.users.index')           // URL string or '/404'

// hasRoute — check existence before rendering
if (hasRoute('app.masters.settings.index')) {
    // render admin link
}
```

Use `safeRoute()` when a route might not exist for the user. Use `hasRoute()` to conditionally render UI elements. Regular `route()` is fine when the route is guaranteed to exist (e.g., inside permission-gated pages).

## Quick Reference

### Basic URL Generation

```typescript
// Generate a URL string from a named route
route('posts.index')                           // "/posts"
route('posts.show', { post: 1 })               // "/posts/1"
route('posts.show', 1)                         // "/posts/1" (single param shorthand)
route('app.users.edit', { user: 42 })          // "/admin/users/42/edit"
```

### Query Parameters

```typescript
// Append query parameters with _query
route('posts.index', { _query: { page: 2, sort: 'name' } })
// "/posts?page=2&sort=name"

// Combine route params and query params
route('posts.show', { post: 1, _query: { tab: 'comments' } })
// "/posts/1?tab=comments"
```

### Current Route Checks

```typescript
// Check if the current route matches
route().current('dashboard')          // true/false
route().current('app.users.*')       // wildcard matching
route().current('app.settings.*')    // true on any settings sub-route

// Get current route name
route().current()                     // "app.users.index"
```

### Route Parameters

```typescript
// Get parameters from the current route
route().params                        // { user: "42" }
```

## Ziggy + Inertia

### Link Navigation

```typescript
import { Link } from '@inertiajs/react';

<Link href={route('app.users.index')}>Users</Link>
<Link href={route('app.users.show', { user: user.id })}>View User</Link>
<Link href={route('app.users.create')}>Add User</Link>
```

### Router Visits

```typescript
import { router } from '@inertiajs/react';

router.visit(route('app.users.index'));
router.get(route('app.users.index'), { search: 'John' });
router.post(route('app.users.store'), formData);
router.put(route('app.users.update', { user: user.id }), formData);
router.patch(route('app.profile.update'), formData);
router.delete(route('app.users.destroy', { user: user.id }));
```

### useAppForm Submit

```typescript
form.submit('patch', route('app.profile.update'), {
    preserveScroll: true,
    setDefaultsOnSuccess: true,
    successToast: { title: 'Profile updated' },
});

form.submit('put', route('app.settings.update', 'general'), {
    preserveScroll: true,
    setDefaultsOnSuccess: true,
});
```

### Raw Inertia useForm

```typescript
const { post, put, patch, delete: destroy } = useForm({ name: '' });

post(route('app.users.store'));
put(route('app.users.update', { user: user.id }));
destroy(route('app.users.destroy', { user: user.id }));
```

### Inertia Form Component

```typescript
<Form action={route('app.users.store')} method="post">
    <input name="name" />
    <button type="submit">Create</button>
</Form>
```

### Breadcrumbs

```typescript
const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Users', href: route('app.users.index') },
    { title: user.name },
];
```

### Datagrid Action URLs

```typescript
<Datagrid
    action={route('app.users.index')}
    rowActions={(row) => [
        { label: 'View', href: route('app.users.show', { user: row.id }) },
        { label: 'Edit', href: route('app.users.edit', { user: row.id }) },
        {
            label: 'Delete',
            href: route('app.users.destroy', { user: row.id }),
            method: 'DELETE',
            confirm: 'Delete this user?',
            variant: 'destructive',
        },
    ]}
/>
```

## TypeScript Types

Ziggy's `route()` function is globally typed via `resources/js/types/global.d.ts` (or similar). The `route` function is available globally without imports — do not import it explicitly.

## Verification

1. Check that `ZiggyRouteFilter::render()` is called in `resources/views/app.blade.php`
2. Verify TypeScript recognizes `route()` — no import needed
3. Run `php artisan route:list` to confirm named routes exist
4. Run `pnpm build` to verify no TypeScript errors
5. Check that `config/ziggy.php` groups cover all needed routes

## Common Pitfalls

- Importing `route` explicitly — it's globally available via the Ziggy script tag
- Using hardcoded URLs instead of `route()` for internal navigation
- Forgetting that `route()` returns a string, not an object — pass it directly to `href`, `action`, or `router.visit()`
- Using wrong route parameter names — check `php artisan route:list` for parameter bindings
- Forgetting `_query` wrapper for query parameters (e.g., using `{ page: 2 }` instead of `{ _query: { page: 2 } }`)
- Relying on stale Ziggy route definitions after admin slug changes — the app handles this automatically via `Inertia::location()` and asset versioning
- Using `route().current()` without understanding that it checks against the Inertia page URL, not the browser URL
- Calling `route('app.masters.settings.index')` for a non-super user — the route won't exist in their Ziggy config. Use `safeRoute()` or `hasRoute()` when the route may be filtered out.
- Forgetting to add new route groups to `config/ziggy.php` when creating new CRUD resources
- Not calling `ZiggyRouteFilter::clearCache()` after modifying permission assignments or route groups
```
