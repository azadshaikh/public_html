---
name: inertia-react-development
description: 'Develops Inertia.js v3 React applications. Use when creating React pages, forms, navigation, layout props, standalone HTTP requests, optimistic updates, instant visits, deferred props, polling, or other Inertia React v3 patterns.'
license: MIT
metadata:
    author: laravel
---

# Inertia React Development

## When to Apply

Activate this skill when:

- Creating or modifying React page components rendered by Inertia
- Building forms with `<Form>` or `useForm`
- Implementing navigation with `<Link>` or `router`
- Using Inertia v3 features such as `useHttp`, optimistic updates, layout props, instant visits, deferred props, `WhenVisible`, polling, prefetching, or partial reloads
- Updating older Inertia React code to v3 APIs

## Documentation

Always use `search-docs` for current Inertia v3 guidance.

Before drilling into individual topics, check the documentation index at `https://inertiajs.com/docs/llms.txt` to discover the available Inertia pages.

Useful topics:

- `inertia react v3 upgrade guide`
- `client-side setup`
- `server-side setup`
- `title and meta`
- `links`
- `manual visits`
- `forms`
- `validation`
- `remembering state`
- `file uploads`
- `http requests`
- `optimistic updates`
- `typescript`
- `http client interceptors`
- `createInertiaApp defaults config`
- `strict mode pages shorthand`
- `useHttp`
- `optimistic updates`
- `layout props`
- `instant visits`
- `deferred props`
- `partial reloads preserveErrors`

## Version Notes

- This project uses Inertia React v3.
- React 19+ is required.
- Axios is no longer required. Inertia v3 ships with a built-in XHR client and built-in interceptors.
- `@inertiajs/vite` is the preferred client setup for v3 and can auto-resolve pages and mount the app with a minimal `createInertiaApp()` entrypoint.
- Inertia v3 can opt into the standards-compliant `data-inertia` head attribute via `defaults.future.useDataInertiaHeadAttribute`.
- SSR works automatically in Vite development mode with `@inertiajs/vite`; a separate SSR server is not needed during normal dev.

### Project Inertia v3 Defaults

Use the project's current v3 rules as the baseline, not older v1/v2-era habits.

- New v3 features in active use include `useHttp`, optimistic updates, layout props, instant visits, and simplified Vite-based SSR behavior.
- Features carried forward and still valid include deferred props, polling, prefetching, prop merging, infinite scroll, and flash data.
- Prefer Inertia's `<Head>` through the shared app wrapper, `Link`, `<Form>`, `useForm`, `useHttp`, and `router` helpers instead of custom page plumbing.
- Use `useHttp` for standalone JSON or external requests that should not trigger navigation; prefer it over raw `fetch()` for app-owned endpoints when Inertia ergonomics are useful.
- Prefer built-in Inertia Precognition support for forms and `useForm` when real-time Laravel validation is needed.
- Prefer built-in optimistic updates only for small, reversible UI changes, and return the minimal changed subset for predictable rollback.
- Prefer `router.get/post/put/patch/delete/reload` over raw `router.visit()` when they better express intent.
- Use `router.push()` and `router.replace()` only for client-only navigation that should not hit the server.
- Use prop helpers like `router.replaceProp()`, `router.appendToProp()`, and `router.prependToProp()` for lightweight client-only prop updates.
- Cancel stale work with `router.cancelAll()` when needed.
- This project does not use `viewTransition`; do not add it to links, visits, or global defaults.
- When using deferred props, provide a visible empty or loading state.
- Do not assume all historical `future` flags are gone; follow the current docs for still-supported `defaults.future` options.

## Setup Guidance

### React Client Setup

For new or heavily refactored setup work, prefer the documented v3 stack:

- `react`
- `react-dom`
- `@vitejs/plugin-react`
- `@inertiajs/react`
- `@inertiajs/vite`

When `@inertiajs/vite` is used, a minimal entry file is valid:

```react
import { createInertiaApp } from '@inertiajs/react'

createInertiaApp()
```

The Vite plugin can generate the resolver automatically, searching `./pages` and `./Pages` by default. In this project, continue following the existing `resources/js/pages` convention unless the app has been intentionally reconfigured.

### `createInertiaApp()` Options

Prefer documented setup options instead of custom bootstrapping when they cover the use case.

- `strictMode: true` enables React Strict Mode.
- `pages: './AppPages'` changes the page directory.
- `pages` may also be an object with `path`, `extension`, `lazy`, and `transform`.
- `defaults` configures form, prefetch, and visit defaults.
- `defaults.future.useDataInertiaHeadAttribute` switches head tracking from `inertia` to `data-inertia`.
- `id` changes the app root element and must match the server-side `@inertia(...)` root id.

Example:

```react
createInertiaApp({
    strictMode: true,
    pages: {
        path: './pages',
        extension: '.tsx',
        lazy: true,
    },
    defaults: {
        form: {
            recentlySuccessfulDuration: 5000,
        },
        future: {
            useDataInertiaHeadAttribute: true,
        },
    },
})
```

### Runtime Configuration

Inertia v3 exposes a `config` instance for runtime configuration. Use it when behavior must change dynamically instead of hard-coding alternate form or prefetch behavior.

```react
import { config } from '@inertiajs/react'

config.set('form.recentlySuccessfulDuration', 1000)
config.set({
    'prefetch.cacheFor': '5m',
})

const duration = config.get('form.recentlySuccessfulDuration')
```

### Laravel Server Setup Notes

For Laravel apps:

- The root Blade view typically remains `resources/views/app.blade.php`.
- React projects should place `@viteReactRefresh` before `@vite(...)` in development.
- If the root element id changes, update both `@inertia('custom-id')` and the client-side `id` option.
- Publish and register `HandleInertiaRequests` middleware.
- In Laravel 12, append that middleware in `bootstrap/app.php`, not `app/Http/Kernel.php`.

### Manual Setup

If `@inertiajs/vite` is not being used, provide explicit `resolve` and `setup` callbacks to `createInertiaApp()`. Prefer `resolvePageComponent()` from the Laravel Vite helper when following Laravel's documented resolver pattern.

## Page Structure

### Page Components

React page components should live in `resources/js/pages` using the existing project conventions.

### Project Page Conventions

For authenticated application pages in this project, prefer the shared app shell instead of building page wrappers ad hoc.

- Use `AppLayout` for normal authenticated pages.
- Pass `breadcrumbs`, `title`, `description`, and optional `headerActions` into `AppLayout`.
- Let the layout render the page header through the shared shell instead of repeating page titles inside the body.
- Use `AccountLayout` only inside `AppLayout` for account/settings screens.
- Keep page body content focused on the feature content itself: filters, alerts, forms, tables, empty states, and detail sections.
- Use default control sizing for page actions, filter bars, datagrid toolbars, and form controls. Treat `sm`/`xs` variants as explicit density changes that should only be used when the UI truly needs tighter spacing; if that tradeoff is not obvious, confirm it with the user first.

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

### Titles and Meta

Prefer a shared app-level head wrapper for page titles and common metadata.

- Use Inertia's `<Head>` to manage page `<title>` and `<meta>` tags.
- Keep tags that should always exist in the Blade root template out of page-level `<Head>` blocks.
- Use `head-key` on duplicate-prone tags such as description metadata.
- Multiple `<Head>` instances are valid; layouts can provide defaults and pages can override them.
- In this project, `AppLayout` already sets page metadata through the shared `AppHead` wrapper. Do not add a page-level `<Head title="..." />` on pages that already pass `title` and `description` to `AppLayout` unless the page truly needs extra head tags beyond what the layout provides.

Example wrapper:

```react
import { Head } from '@inertiajs/react'

export default function AppHead({ title, children }) {
    return (
        <Head title={title}>
            <meta
                head-key="description"
                name="description"
                content="Default app description"
            />
            {children}
        </Head>
    )
}
```

Project rule of thumb:

- If the page uses `AppLayout` and already passes `title` / `description`, do not also add `<Head title="..." />`.
- If a page uses a different layout that does not own head metadata, then page-level `<Head>` may still be appropriate.

Example for this project:

```react
<AppLayout
    breadcrumbs={breadcrumbs}
    title="Roles"
    description="Manage user roles and permissions"
>
    <div className="flex flex-col gap-6">...</div>
</AppLayout>
```

Not this:

```react
<AppLayout title="Roles" description="Manage user roles and permissions">
    <Head title="Roles" />
    ...
</AppLayout>
```

### Basic Page Example

```react
export default function UsersIndex({ users }) {
    return (
        <div>
            <h1>Users</h1>
            <ul>
                {users.map(user => <li key={user.id}>{user.name}</li>)}
            </ul>
        </div>
    )
}
```

### Resource Page Composition

For back-office resource pages in this project, prefer a predictable body order so future resource pages stay visually consistent.

Typical order:

1. filter section
2. flash/error alerts
3. registry table or main content
4. empty state when no rows exist

Use the shared resource primitives when they fit:

- `ResourceSectionCard`
- `ResourceFeedbackAlerts`
- `ResourceStatCard` when metrics are truly useful

Do not add summary cards by default. Only keep them when the metrics materially help the page.

Example:

```react
<div className="flex flex-col gap-6">
    <ResourceSectionCard
        title="Filter roles"
        description="Search by label, key, or description."
    >
        <Form {...RoleController.index.form()} method="get">
            ...
        </Form>
    </ResourceSectionCard>

    <ResourceFeedbackAlerts
        status={status}
        statusIcon={<ShieldCheckIcon />}
        error={error}
        errorIcon={<ShieldAlertIcon />}
    />

    <ResourceSectionCard
        title="Role registry"
        description="Manage user roles and permissions."
    >
        <Table>...</Table>
    </ResourceSectionCard>
</div>
```

## Client-Side Navigation

Use `<Link>` for client-side navigation instead of traditional `<a>` tags:

```react
import { Link, router } from '@inertiajs/react'

<Link href="/">Home</Link>
<Link href="/users">Users</Link>
<Link href={`/users/${user.id}`}>View User</Link>
```

### Method Visits

```react
import { Link } from '@inertiajs/react'

<Link href="/logout" method="post" as="button">
    Logout
</Link>
```

For non-`GET` visits, prefer button rendering for accessibility. Avoid creating `POST`/`PUT`/`PATCH`/`DELETE` anchor links.

### Ziggy Route Links

Use Ziggy's `route()` function to generate URL strings for navigation.

```react
import { Link } from '@inertiajs/react'

<Link href={route('app.users.show', { user: 1 })}>View user</Link>
```

Project convention:

- Use Ziggy's `route()` function for all internal navigation URLs.
- Pass `route()` strings directly to `Link href`, `<Form action>`, or `form.submit()`.
- Avoid hard-coded internal URLs when a named route exists.

Example:

```react
<Link href={route('app.users.create')}>Add User</Link>

<Form action={route('app.users.index')} method="get">
    ...
</Form>
```

### Prefetching

Prefetch pages to improve perceived performance:

```react
import { Link } from '@inertiajs/react'

<Link href="/users" prefetch>
    Users
</Link>
```

### Common Link Options

- `replace` replaces the current browser history entry instead of pushing a new one.
- `preserveState` preserves local page state.
- `preserveScroll` preserves the current scroll position.
- `only` requests a subset of props on partial reloads.
- Active links receive a `data-loading` attribute during in-flight requests.

### Instant Link Patterns

Use instant visits only when the target page can render with shared props and missing page-specific props.

- Explicit `component="Dashboard"` works on `Link` and `router.visit()`.
- When using `pageProps`, shared props are no longer carried automatically unless you merge them yourself.
- Prefer defensive rendering or placeholder content when intermediate props may be `undefined`.

### Programmatic Visits

```react
import { router } from '@inertiajs/react'

function handleClick() {
    router.visit('/users')
}

// Or with options
router.visit('/users', {
    method: 'post',
    data: { name: 'John' },
    onSuccess: () => console.log('Success!'),
})
```

Prefer the shortcut methods when they express intent more clearly:

```react
router.get('/users', { search: 'John' }, { replace: true })
router.post('/users', data)
router.put('/users/1', data)
router.patch('/users/1', data)
router.delete('/users/1')
router.reload({ only: ['users'] })
```

For `router.get()`, explicitly set `preserveState` when a same-page visit should keep local state:

```react
router.get('/users', { search: 'John' }, { preserveState: true })
```

### Manual Visit Options

Important `router.visit()` options in v3 include:

- `replace`
- `preserveState`
- `preserveScroll`
- `only` / `except`
- `headers`
- `forceFormData`
- `prefetch`
- `preserveErrors`
- `component`
- `pageProps`
- `onBefore`, `onStart`, `onProgress`, `onSuccess`, `onError`, `onHttpException`, `onNetworkError`, `onCancel`, `onFinish`

Prefer `router.reload()` when reloading the current page, since it preserves state and scroll automatically.

Use `replace`, `preserveState`, `preserveScroll`, `only`, `except`, and `preserveErrors` intentionally when they improve navigation UX.

When uploading files with Laravel, avoid `put`/`patch` uploads directly. Use `post` with `_method` spoofing if needed.

### Global Visit Options

You can configure global visit behavior through `defaults.visitOptions` in `createInertiaApp()`.

```react
createInertiaApp({
    defaults: {
        visitOptions: (href, options) => ({
            headers: {
                ...options.headers,
                'X-Custom-Header': 'value',
            },
        }),
    },
})
```

### Client-Side Visits And Prop Helpers

Use `router.push()` and `router.replace()` only for true client-side page updates where no server request should run. Ensure the route still exists server-side for refreshes.

For lightweight client-only prop updates, prefer:

- `router.replaceProp()`
- `router.appendToProp()`
- `router.prependToProp()`

These preserve state and scroll automatically.

### Instant Visits

Instant visits render the target component immediately while page props load in the background.

```react
import { Link } from '@inertiajs/react'

<Link href="/dashboard" component="Dashboard">
    Dashboard
</Link>
```

When using instant visits, pass the `component` prop explicitly.

```react
<Link href={route('app.posts.show', { post: 1 })} component="Posts/Show" instant>
    View post
</Link>
```

Project caution:

- Use instant visits only for pages that can render safely with shared props while page-specific props are still loading.
- Avoid instant visits when the intermediate render would be misleading or structurally incomplete.

### Visit Cancellation

Cancel in-flight requests with `router.cancelAll()`.

```react
router.cancelAll()
router.cancelAll({ prefetch: false })
```

For single-request cancellation, capture the cancel token from `onCancelToken()`.

### Per-Visit Callbacks

- Return `false` from `onBefore()` to cancel a visit.
- Return `false` from `onHttpException()` or `onNetworkError()` to suppress the matching global event.
- `onSuccess()` and `onError()` may return promises; `onFinish()` waits for them.

## Form Handling

Prefer the `<Form>` component unless existing code already uses `useForm`.

```react
import { Form } from '@inertiajs/react'

export default function CreateUser() {
    return (
        <Form action="/users" method="post">
            {({ errors, processing, wasSuccessful }) => (
                <>
                    <input type="text" name="name" />
                    {errors.name && <div>{errors.name}</div>}

                    <input type="email" name="email" />
                    {errors.email && <div>{errors.email}</div>}

                    <button type="submit" disabled={processing}>
                        {processing ? 'Creating...' : 'Create User'}
                    </button>

                    {wasSuccessful && <div>User created!</div>}
                </>
            )}
        </Form>
    )
}
```

### Choosing `<Form>` vs `useForm`

- Prefer `<Form>` for straightforward server-driven forms that can rely on `name` attributes and uncontrolled inputs.
- Prefer `useForm` when the UI needs controlled React state, imperative submission, remembered history state, client-side mutation, or cancellation.
- With React `<Form>`, use `defaultValue` and `defaultChecked` for initial values instead of controlled `value` state unless the UI truly requires control.

Project convention:

- Prefer `<Form>` for index filters and simple server-driven search/filter toolbars.
- Prefer `useForm` for richer edit/create screens like role assignment, password fields, checkbox collections, or other interactive forms.

Examples:

```react
// Good: simple index filtering
<Form {...RoleController.index.form()} method="get" options={{ preserveScroll: true }}>
    <input name="search" defaultValue={filters.search} />
</Form>

// Good: interactive managed user form
const form = useForm({
    name: initialValues.name,
    email: initialValues.email,
    roles: initialValues.roles,
    password: '',
})
```

### `<Form>` Patterns

- Pass Ziggy `route()` URLs to `action`, or use the URL string directly.
- Use `transform` for lightweight payload shaping before submission.
- Use `disableWhileProcessing` to add the `inert` attribute during submission; pair it with classes such as `inert:opacity-60` when helpful.
- Prefer built-in reset helpers like `resetOnSuccess`, `resetOnError`, and `setDefaultsOnSuccess` over manual reset bookkeeping.
- Put partial reload controls such as `only`, `except`, and `reset` inside the `options` prop, since they configure the follow-up visit rather than the form payload.
- File uploads work automatically when the payload contains `File` objects; Inertia converts the request to `FormData` for you.
- Use `progress` from `<Form>`, `useForm`, or `useHttp` to render upload progress.
- With Laravel, do not send file uploads with `put` or `patch` directly. Prefer `post` plus `_method: 'put' | 'patch'` when method spoofing is needed.
- Use `forceFormData` on manual visits when a request should always be multipart, even before a file is present.

Example:

```react
<Form
    action={updateProfile()}
    method="put"
    disableWhileProcessing
    setDefaultsOnSuccess
    resetOnError={['password']}
    className="space-y-6 inert:pointer-events-none inert:opacity-60"
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

### `<Form>` Features

The `<Form>` component exposes helpers such as:

- `errors`
- `processing`
- `progress`
- `wasSuccessful`
- `recentlySuccessful`
- `reset`
- `clearErrors`
- `resetAndClearErrors`
- `submit`

It also supports helpers like:

- `resetOnError`
- `resetOnSuccess`
- `setDefaultsOnSuccess`
- `optimistic`

### `useForm`

Use `useForm` when you need programmatic control over state and submission.

```react
import { useForm } from '@inertiajs/react'

export default function CreateUser() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
    })

    function submit(e) {
        e.preventDefault()

        post('/users', {
            onSuccess: () => reset(),
        })
    }

    return (
        <form onSubmit={submit}>
            <input
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
            />

            {errors.name && <div>{errors.name}</div>}

            <button disabled={processing}>Create User</button>
        </form>
    )
}
```

Use a keyed form when draft state should survive history navigation:

```react
const form = useForm(`EditUser:${user.id}`, {
    name: user.name,
    email: user.email,
}).dontRemember('password')
```

Useful v3 `useForm` helpers include:

- `setDefaults()`
- `isDirty`
- `resetAndClearErrors()`
- `cancel()`
- `dontRemember()`
- `progress`
- `withPrecognition()`
- `validate()`, `touch()`, `touched()`, `invalid()`, and `valid()` when Precognition is enabled

### Form Context And Refs

- Use `useFormContext()` for nested child components that need parent `<Form>` state without prop drilling.
- In React, refs on `<Form>` expose form methods and state for programmatic access when slot props are not convenient.

### Laravel + Inertia Form Notes

- Let the server redirect after successful submissions instead of expecting JSON response handling.
- Rely on Inertia's built-in server-side validation flow instead of manual `422` parsing.
- For file uploads, let Inertia convert the payload to `FormData` automatically.
- Prefer Ziggy `route()` URLs with both `<Form>` and `useForm.submit()` instead of hard-coded URLs.
- Form helper errors are already scoped to the form instance, so error bags are usually unnecessary when using `<Form>` or `useForm`.

### Validation Notes

- Inertia validation for page visits is redirect-based, not `422`-driven.
- Display validation errors from form helpers or shared `page.props.errors`.
- For non-form-helper manual visits on pages with multiple forms, use `errorBag` to avoid field-name collisions.
- Inertia preserves component state by default for `post`, `put`, `patch`, and `delete`, so input repopulation usually comes for free after validation failures.
- Laravel adapters return only the first error per field by default unless all-errors behavior is enabled server-side.

### Precognition Notes

- Precognition support is built into Inertia v3 forms and `useForm`.
- Prefer built-in Precognition support over legacy client packages when real-time Laravel validation is needed.
- Validate a single field with `validate('field')`, or call `touch()` and then `validate()` to validate touched fields.

## Inertia v3 Features

### HTTP Requests

Use `useHttp` for standalone HTTP requests that should not trigger an Inertia page visit.

```react
import { useHttp } from '@inertiajs/react'

export default function Search() {
    const { data, setData, get, processing } = useHttp({
        query: '',
    })

    function search(e) {
        setData('query', e.target.value)
        get('/api/search', {
            onSuccess: (response) => {
                console.log(response)
            },
        })
    }

    return (
        <>
            <input value={data.query} onChange={search} />
            {processing && <div>Searching...</div>}
        </>
    )
}
```

Prefer `useHttp` over raw `fetch()` for app-owned JSON endpoints when you want Inertia-style request ergonomics without navigation.

`useHttp` is especially useful for:

- modal or sidebar data fetches
- background refreshes of JSON endpoints
- multi-step setup flows that read or mutate non-Inertia endpoints
- uploads with progress indicators
- requests that benefit from remembered state, validation errors, cancellation, or Precognition without a page visit

Useful `useHttp` capabilities in v3:

- `response` for the latest parsed JSON payload
- `cancel()` for aborting in-flight requests
- `withAllErrors()` for array-based validation messages
- remembered state via `useHttp('Key', data)` plus `dontRemember()` for sensitive fields
- `progress` for upload UI
- `optimistic()` for request-local optimistic updates
- `withPrecognition()` plus `validate()`, `touch()`, `touched()`, `valid()`, and `invalid()`

Project guidance:

- Prefer `useHttp` for modal fetches, background refreshes, wizard-like setup flows, upload side panels, or JSON mutations that should stay outside the page-visit lifecycle.
- Prefer normal Inertia visits for full page transitions and standard create/edit/update/delete resource flows.

Example:

```react
const search = useHttp('SearchFilters', {
    query: '',
}).dontRemember('token')

async function runSearch() {
    const response = await search.get('/api/search')
    console.log(response)
}
```

If the application needs global request behavior, prefer Inertia's built-in `http.onRequest()`, `http.onResponse()`, and `http.onError()` interceptors instead of introducing Axios patterns.

For uploads through `useHttp`, file payloads are automatically sent as `multipart/form-data`, and `progress` can be used for upload indicators.

### HTTP Client Configuration

The built-in XHR client is the default in v3.

- Prefer the default client unless the app explicitly needs Axios or a custom adapter.
- Use `http.onRequest()`, `http.onResponse()`, and `http.onError()` interceptors for global request behavior.
- Each interceptor registration returns a cleanup function.

```react
import { http } from '@inertiajs/react'

const removeRequestHandler = http.onRequest((config) => {
    config.headers['X-Custom-Header'] = 'value'

    return config
})

const removeResponseHandler = http.onResponse((response) => {
    return response
})

const removeErrorHandler = http.onError((error) => {
    console.error(error)
})

removeRequestHandler()
removeResponseHandler()
removeErrorHandler()
```

If a custom Axios instance is truly required, wire it through the documented `http` adapter instead of mixing old Axios patterns throughout the codebase.

### Optimistic Updates

Optimistic updates apply UI changes immediately and roll back automatically on failure.

```react
import { router } from '@inertiajs/react'

function like(post) {
    router.optimistic((props) => ({
        post: {
            ...props.post,
            likes: props.post.likes + 1,
        },
    })).post(`/posts/${post.id}/like`)
}
```

    Optimistic updates also work with `<Form>`.

```react
import { Form } from '@inertiajs/react'

<Form
    action="/todos"
    method="post"
    optimistic={(props, data) => ({
        todos: [...props.todos, { id: Date.now(), name: data.name, done: false }],
    })}
>
    <input type="text" name="name" />
    <button type="submit">Add Todo</button>
</Form>
```

Use optimistic updates when:

- the interaction is lightweight and reversible
- the user benefits from immediate feedback
- the optimistic value can be represented as a shallow partial update

Rules of thumb:

- `router.optimistic()` updates current page props
- `<Form optimistic={...}>` receives both current props and submitted form data
- `useForm().optimistic()` updates current page props before submit
- `useHttp().optimistic()` updates the hook's own `data`, not page props
- Return only the keys that should change; Inertia shallow-merges the result and snapshots touched keys for rollback

Prefer optimistic updates for counters, toggles, lightweight list inserts, and similar interactions. Avoid them when the optimistic state would be difficult to roll back cleanly or when the server may return materially different structure.

### Layout Props

Use layout props to share dynamic data between pages and persistent layouts.

```react
import { useLayoutProps } from '@inertiajs/react'

export default function Layout({ children }) {
    const { title, showSidebar } = useLayoutProps({
        title: 'My App',
        showSidebar: true,
    })

    return (
        <>
            <header>{title}</header>
            {showSidebar && <aside>Sidebar</aside>}
            <main>{children}</main>
        </>
    )
}
```

Pages can set layout props directly:

```react
import { setLayoutProps } from '@inertiajs/react'

export default function Dashboard() {
    setLayoutProps({
        title: 'Dashboard',
        showSidebar: false,
    })

    return <h1>Dashboard</h1>
}
```

Only use `useLayoutProps`, `setLayoutProps()`, or `setLayoutPropsFor()` with persistent layouts. If a page simply renders a layout wrapper as a normal child component, that layout is recreated on each visit and layout-prop persistence does not apply.

Persistent layout guidance:

- `Page.layout = Layout` keeps the layout instance alive between visits.
- Use arrays for nested persistent layouts.
- Use `createInertiaApp({ layout })` or the `resolve` callback for default layouts when that improves consistency.
- Use named layouts and `setLayoutPropsFor()` only when multiple persistent layout layers truly need separate dynamic props.
- Use `resetLayoutProps()` to manually clear dynamic layout props when needed.

Prefer layout props for dynamic layout concerns like titles, active sections, or sidebar state. Do not use them as a replacement for shared page props.

Project caution:

- Only use `useLayoutProps`, `setLayoutProps()`, `setLayoutPropsFor()`, or `resetLayoutProps()` with persistent layouts.
- Do not use layout props with plain wrapper layouts that remount every visit, such as pages that simply render `AppLayout` as a normal component.

### View Transitions

This project does not use Inertia view transitions.

- Do not add `viewTransition` to `Link` or `router.visit()` calls.
- Do not enable global transition defaults through `defaults.visitOptions`.
- Prefer standard Inertia navigation behavior in this codebase.

### Default Layouts

In v3, `createInertiaApp` supports default layout configuration. Prefer project conventions if this is already defined centrally.

### Deferred Props

Deferred props should include a visible empty or loading state. In React v3, deferred content no longer falls back again during partial reloads; existing content stays visible while reloading.

```react
export default function UsersIndex({ users }) {
    return (
        <div>
            <h1>Users</h1>
            {!users ? (
                <div className="animate-pulse">
                    <div className="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                    <div className="h-4 bg-gray-200 rounded w-1/2"></div>
                </div>
            ) : (
                <ul>
                    {users.map(user => (
                        <li key={user.id}>{user.name}</li>
                    ))}
                </ul>
            )}
        </div>
    )
}
```

### WhenVisible

Use `WhenVisible` for below-the-fold deferred data.

```react
import { WhenVisible } from '@inertiajs/react'

export default function Dashboard({ stats }) {
    return (
        <WhenVisible
            data="stats"
            fallback={<div className="animate-pulse">Loading stats...</div>}
        >
            {({ fetching }) => (
                <div>
                    <p>Total Users: {stats.total_users}</p>
                    {fetching && <span>Refreshing...</span>}
                </div>
            )}
        </WhenVisible>
    )
}
```

### Polling

Automatically refresh data at intervals:

```react
import { router } from '@inertiajs/react'
import { useEffect } from 'react'

export default function Dashboard({ stats }) {
    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ only: ['stats'] })
        }, 5000)

        return () => clearInterval(interval)
    }, [])

    return (
        <div>
            <h1>Dashboard</h1>
            <div>Active Users: {stats.activeUsers}</div>
        </div>
    )
}
```

### Partial Reloads

Use `only` and `except` to refresh only what changed. In v3, nested dot-notation is supported.

```react
router.reload({ only: ['auth.notifications'] })
```

Use `preserveErrors` when partial reloads should keep validation errors visible.

## v3 Breaking Changes

- `router.cancel()` was replaced by `router.cancelAll()`.
- Global events changed:
    - `invalid` → `httpException`
    - `exception` → `networkError`
- Per-visit callbacks changed to `onHttpException` and `onNetworkError`.
- Axios is no longer the default HTTP client.
- Some historical `future` behavior is now standard, but current docs still expose supported `defaults.future` flags such as `useDataInertiaHeadAttribute`.
- Inertia packages are ESM-only; use `import`, not `require()`.

## Server-Side Notes

Server-side `Inertia::render()`, prop types like `Inertia::optional()` and `Inertia::defer()`, middleware, SSR, root templates, and error page rendering belong to the Inertia Laravel guidance.

Important v3 server-side reminders to keep in mind while working on client pages:

- `Inertia::lazy()` / `LazyProp` was removed; use `Inertia::optional()` instead.
- Prop helpers such as `Inertia::optional()`, `Inertia::defer()`, and `Inertia::merge()` work inside nested arrays using dot notation.

## Common Pitfalls

- Using plain `<a>` tags for internal navigation
- Duplicating root-template `<head>` tags in page-level `<Head>` blocks
- Adding redundant `<Head title="..." />` blocks on `AppLayout` pages that already pass metadata to the shared layout wrapper
- Forgetting `head-key` on page-specific description or Open Graph tags that can appear more than once
- Repeating title boilerplate instead of using a small shared `AppHead` wrapper
- Repeating page header markup inside page bodies instead of using `AppLayout` with `title`, `description`, and `headerActions`
- Controlling `<Form>` inputs unnecessarily when `name` plus `defaultValue` / `defaultChecked` is enough
- Forgetting `disableWhileProcessing` when duplicate submissions or clicks are possible
- Manually resetting fields when `resetOnSuccess`, `resetOnError`, or `setDefaultsOnSuccess` already fit the workflow
- Using `useForm` without a key when draft state should survive history navigation
- Storing sensitive remembered form fields instead of excluding them with `dontRemember()`
- Re-implementing validation error parsing instead of using Inertia's built-in redirect and error handling
- Using non-`GET` links as anchors instead of buttons
- Forgetting that Ziggy `route()` can generate URLs for all named routes — use it instead of hardcoding paths
- Forgetting that `router.reload()` already preserves scroll and state
- Using instant visits for pages that cannot render safely without page-specific props
- Passing `pageProps` to instant visits without re-merging required shared props
- Forgetting `router.cancelAll()` when cancelling stale visits during navigation-heavy interactions
- Using `router.push()` / `router.replace()` as a substitute for real server visits when the page still needs fresh backend data
- Overlooking `replace`, `preserveState`, `preserveScroll`, or `only` when they improve UX
- Ignoring the `data-loading` attribute when styling loading navigation states
- Rebuilding page resolution manually when `@inertiajs/vite` already covers the setup
- Forgetting to keep the client `id` and server `@inertia(...)` root id in sync
- Registering `HandleInertiaRequests` in the wrong Laravel 12 location
- Skipping `@viteReactRefresh` in React development when touching the Blade root template
- Reintroducing Axios patterns when the built-in XHR client already covers the use case
- Using raw `fetch()` for app-owned standalone requests when `useHttp` would provide better cancellation, error, progress, and remember-state ergonomics
- Using `router` visits for requests that should stay outside the Inertia page lifecycle
- Applying optimistic updates to complex state that cannot be safely or clearly rolled back
- Returning full objects instead of the minimal changed subset from optimistic callbacks
- Sending file uploads through `put` or `patch` in Laravel instead of method-spoofed `post` requests when multipart limitations apply
- Using error bags with `<Form>` or `useForm` when helper-scoped errors already solve the problem
- Reaching for layout props in non-persistent wrapper layouts where plain props or shared data are more appropriate
- Adding `viewTransition` in this project even though the effect is intentionally not used
- Using `router.cancel()` instead of `router.cancelAll()`
- Listening for `invalid` / `exception` instead of `httpException` / `networkError`
- Forgetting loading or empty states for deferred props
- Replacing existing content with skeletons during React deferred reloads when a smaller inline reloading indicator would be better
- Ignoring `preserveErrors` needs during partial reloads
- Using CommonJS `require()` imports with ESM-only Inertia packages
- Hard-coding internal app URLs when a named route exists for `route()`
- Treating every resource page as if it needs summary stat cards, even when the metrics add noise instead of clarity
