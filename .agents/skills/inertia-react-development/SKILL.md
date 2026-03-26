---
name: inertia-react-development
description: "Develops Inertia.js v3 React applications in this project. Use when creating or modifying Inertia React pages, forms, navigation, layout metadata, `useHttp`, deferred props, optimistic updates, instant visits, polling, `WhenVisible`, or named-route usage from React."
license: MIT
metadata:
  author: laravel
---

# Inertia React Development

## When to Apply

Activate this skill when:

- Creating or modifying React page components rendered through Inertia
- Building forms with `<Form>`, `useForm`, or `useHttp`
- Implementing client-side navigation with `<Link>` or `router`
- Using Inertia v3 features such as deferred props, `WhenVisible`, optimistic updates, polling, instant visits, layout props, or partial reloads
- Updating older Inertia React code to current v3 APIs

## Documentation

Always use `search-docs` for current Inertia v3 guidance before changing implementation details.

Useful topic queries:

- `client-side setup`
- `title and meta`
- `links`
- `manual visits`
- `forms`
- `validation`
- `remembering state`
- `file uploads`
- `http requests`
- `optimistic updates`
- `layout props`
- `instant visits`
- `deferred props`
- `partial reloads preserveErrors`

## Current Project Status

Use the installed stable packages and current repo conventions as the baseline:

- `@inertiajs/react`: `3.0.0`
- `@inertiajs/vite`: `3.0.0`
- `inertiajs/inertia-laravel`: `^3.0.1`
- `react`: `^19.2.4`

Project defaults that matter:

- `resources/js/pages` is the page root
- `@inertiajs/vite` is in use
- the project does not use Inertia SSR (`config/inertia.php` keeps it disabled)
- the project does not use Inertia `viewTransition`
- Ziggy `route()` is the standard for internal URLs

## Page Structure

### Page Files

Place Inertia React pages in `resources/js/pages` unless the app is intentionally reconfigured.

### Layout Conventions

For authenticated application pages in this project:

- Use `AppLayout` for normal authenticated pages
- Use `AccountLayout` only inside `AppLayout` for account/settings screens
- Pass `breadcrumbs`, `title`, `description`, and optional `headerActions` into `AppLayout`
- Let the shared layout render page headers instead of duplicating titles inside page bodies
- Keep page bodies focused on feature content such as filters, forms, tables, detail sections, and empty states

Example:

```react
import { Link } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import AppLayout from '@/layouts/app-layout'

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Users', href: route('app.users.index') },
]

export default function UsersIndex() {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Users"
            description="Manage account access and profile details."
            headerActions={
                <Button asChild>
                    <Link href={route('app.users.create')}>Add User</Link>
                </Button>
            }
        >
            <div className="flex flex-col gap-6">...</div>
        </AppLayout>
    )
}
```

### Metadata Conventions

This project already has a shared `AppHead` wrapper and `AppLayout` metadata flow.

- Use Inertia `<Head>` for page metadata
- Keep persistent head defaults in the Blade root template
- Use `head-key` for duplicate-prone tags like `description`
- If a page already passes `title` and `description` to `AppLayout`, do not also add a redundant `<Head title="..." />`

### Shared Payload Rules

- Treat shared Inertia props as shell-safe runtime data, not as a substitute for page props
- Do not assume shared payloads include broad module metadata or a global permission dump
- Read only the specific `page.props.auth.abilities` keys the current page needs
- For shared module data, rely on runtime-safe fields such as `name`, `slug`, and `inertiaNamespace` unless the page is a management or inspection screen with richer props

### Shell And Navigation Rules

The authenticated shell includes a global quick-open dialog driven by backend navigation metadata.

- Prefer updating navigation metadata so the sidebar and quick-open stay in sync
- Avoid feature-local command lists unless the feature is intentionally separate from global navigation
- Search-only quick-open entries should generally come from navigation items with `sidebar_visible: false` plus a GET route

## Navigation

### Links

Use `<Link>` for internal navigation instead of plain anchors.

```react
import { Link } from '@inertiajs/react'

<Link href={route('app.users.index')}>Users</Link>
```

- Use Ziggy `route()` for internal URLs
- Pass the URL string directly to `Link href`, `<Form action>`, or router helpers
- Avoid hard-coded internal URLs when a named route exists

For non-`GET` visits, prefer button rendering for accessibility:

```react
<Link href={route('logout')} method="post" as="button">
    Logout
</Link>
```

### Router Visits

Prefer intent-specific router helpers when they fit:

```react
router.get(route('app.users.index'), { search: 'John' }, { preserveState: true })
router.post(route('app.users.store'), data)
router.patch(route('app.users.update', user.id), data)
router.delete(route('app.users.destroy', user.id))
router.reload({ only: ['users'] })
```

Use `router.visit()` when you genuinely need its extra options:

- `replace`
- `preserveState`
- `preserveScroll`
- `only` / `except`
- `preserveErrors`
- `headers`
- `forceFormData`
- `component`
- `pageProps`
- `onBefore`, `onStart`, `onProgress`, `onSuccess`, `onError`, `onHttpException`, `onNetworkError`, `onCancel`, `onFinish`

Guidelines:

- Prefer `router.reload()` for current-page refreshes
- Use `router.push()` / `router.replace()` only for client-only page updates that must not hit the server
- Use `router.replaceProp()`, `router.appendToProp()`, and `router.prependToProp()` for lightweight client-only prop updates
- Cancel stale work with `router.cancelAll()`

### Instant Visits

Instant visits are allowed, but use them carefully.

- Use them only when the target page can safely render with shared props while page-specific props are loading
- Avoid them when the intermediate render would be misleading or structurally incomplete
- If you pass `pageProps`, re-check whether required shared props still exist

## Forms

### Choosing `<Form>` vs `useForm`

- Prefer `<Form>` for straightforward server-driven forms, filters, and search toolbars
- Prefer `useForm` when the UI needs controlled state, imperative submission, remembered drafts, cancellation, or complex client-side interaction
- With React `<Form>`, prefer `name` plus `defaultValue` / `defaultChecked` over controlled `value` state unless control is truly required

Examples:

```react
// Good: simple filter form
<Form action={route('app.roles.index')} method="get" options={{ preserveScroll: true }}>
    <input name="search" defaultValue={filters.search} />
</Form>

// Good: richer interactive form
const form = useForm({
    name: user.name,
    email: user.email,
    roles: user.roles,
    password: '',
})
```

### `<Form>` Rules

- Pass Ziggy `route()` URLs when the route is named
- Use `transform` for small payload shaping
- Use `disableWhileProcessing` when duplicate submits or clicks are a risk
- Prefer built-in reset helpers like `resetOnSuccess`, `resetOnError`, and `setDefaultsOnSuccess`
- Put visit controls like `only`, `except`, and `reset` inside the `options` prop
- Use `progress` for upload UI
- Let Inertia convert requests to `FormData` automatically when files are present
- With Laravel, prefer `post` plus `_method: 'put' | 'patch'` for multipart updates instead of direct `put` / `patch` uploads

Example:

```react
<Form
    action={route('app.profile.update')}
    method="post"
    disableWhileProcessing
    setDefaultsOnSuccess
    resetOnError={['password']}
    options={{ preserveScroll: true }}
>
    {({ processing, errors, recentlySuccessful }) => (
        <>
            <input name="name" defaultValue={user.name} />
            {errors.name && <p>{errors.name}</p>}
            <button disabled={processing}>Save</button>
            {recentlySuccessful && <p>Saved.</p>}
        </>
    )}
</Form>
```

### `useForm` Rules

- Use a keyed `useForm()` instance when draft state should survive history navigation
- Exclude sensitive fields with `dontRemember()`
- Prefer `route()` URLs with `form.submit()` helpers
- Form helper errors are already scoped; only reach for `errorBag` on manual multi-form visits

Example:

```react
const form = useForm(`EditUser:${user.id}`, {
    name: user.name,
    email: user.email,
    password: '',
}).dontRemember('password')
```

Useful helpers to remember:

- `setDefaults()`
- `isDirty`
- `resetAndClearErrors()`
- `cancel()`
- `progress`
- `withPrecognition()`
- `validate()`, `touch()`, `touched()`, `invalid()`, `valid()`

### Validation And Submission Rules

- Inertia validation for page visits is redirect-based, not `422` JSON-driven
- Let the server redirect after successful submissions
- Display validation errors from form helpers or shared `page.props.errors`
- Inertia preserves component state by default for `post`, `put`, `patch`, and `delete`, so failed submissions usually repopulate inputs automatically
- Prefer built-in Precognition support over legacy client packages when real-time Laravel validation is needed

## Standalone HTTP Requests

Use `useHttp` for JSON or non-page-visit requests that should stay outside the Inertia navigation lifecycle.

Prefer it over raw `fetch()` for app-owned endpoints when you want:

- remembered state
- validation ergonomics
- cancellation
- upload progress
- optimistic updates
- Precognition support

Use it for things like modal fetches, background refreshes, wizard steps, or side-panel actions. Prefer normal Inertia visits for full-page CRUD flows.

Example:

```react
const search = useHttp('SearchFilters', {
    query: '',
}).dontRemember('token')

async function runSearch() {
    const response = await search.get(route('app.search.index'))
    console.log(response)
}
```

If the app needs global request behavior, use Inertia's built-in HTTP interceptors instead of reintroducing Axios patterns.

## Key v3 Features

### Optimistic Updates

Use optimistic updates for small, reversible changes only.

- Return only the minimal changed subset
- Prefer them for toggles, counters, lightweight inserts, and similar interactions
- Avoid them when rollback would be confusing or the server may materially reshape the data

### Layout Props

Layout props are for persistent layouts only.

- Use `useLayoutProps`, `setLayoutProps()`, `setLayoutPropsFor()`, and `resetLayoutProps()` only when the layout instance persists across visits
- Do not use layout props with wrapper layouts that remount each visit, such as pages that simply render `AppLayout` as a normal child component
- Do not use layout props as a replacement for shared page props

### Deferred Props

When using deferred props:

- provide a visible loading or empty state
- assume existing content may stay visible during reloads
- use `WhenVisible` for below-the-fold deferred content when appropriate

### Polling And Partial Reloads

- Use polling for lightweight status refreshes
- Prefer `router.reload({ only: [...] })` when only a subset of props needs refreshing
- Use `preserveErrors` when partial reloads should keep validation feedback visible

## Setup Notes

### Client Setup

`@inertiajs/vite` is the preferred setup in this project.

- Keep page resolution aligned with `resources/js/pages`
- Keep the client root id aligned with the server-side `@inertia(...)` root id if it is customized
- Use the built-in XHR client and interceptors unless there is a strong reason not to

### Laravel Setup

For Laravel-side setup in this project:

- the root Blade view is typically `resources/views/app.blade.php`
- React development should keep `@viteReactRefresh` before `@vite(...)`
- `HandleInertiaRequests` belongs in `bootstrap/app.php`, not `app/Http/Kernel.php`
- server-side prop helpers such as `Inertia::optional()`, `Inertia::defer()`, and `Inertia::merge()` belong to the Laravel-side guidance

### SSR And View Transitions

- This project does not use Inertia SSR; do not add SSR or hydration-specific setup unless explicitly asked
- This project does not use Inertia `viewTransition`; do not add it to links, visits, or global defaults

## Breaking Changes To Remember

- `router.cancel()` was replaced by `router.cancelAll()`
- visit callbacks are `onHttpException` and `onNetworkError`, not the old names
- Axios is no longer the default HTTP client
- Inertia packages are ESM-only; use `import`, not `require()`
- `Inertia::lazy()` / `LazyProp` was removed; use `Inertia::optional()` instead

## Common Pitfalls

- Using plain `<a>` tags for internal navigation
- Duplicating page titles and metadata that `AppLayout` already handles
- Hard-coding internal URLs instead of using Ziggy `route()`
- Treating shared props as a place to dump broad feature data or every permission in the app
- Using `useLayoutProps` with non-persistent wrapper layouts
- Reintroducing Axios or raw `fetch()` for app-owned flows that fit `useHttp`
- Using non-`GET` links as anchors instead of buttons
- Forgetting `disableWhileProcessing` or reset helpers on forms that need them
- Sending multipart updates with direct `put` / `patch` requests instead of method-spoofed `post`
- Re-implementing `422` parsing instead of using Inertia's redirect-based validation flow
- Forgetting `router.cancelAll()` during navigation-heavy interactions
- Using instant visits on pages that cannot render safely without page-specific props
- Omitting visible loading states for deferred props
- Adding `viewTransition` or SSR setup in this project even though both are intentionally disabled
