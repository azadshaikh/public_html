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

### Titles and Meta

Prefer a shared app-level head wrapper for page titles and common metadata.

- Use Inertia's `<Head>` to manage page `<title>` and `<meta>` tags.
- Keep tags that should always exist in the Blade root template out of page-level `<Head>` blocks.
- Use `head-key` on duplicate-prone tags such as description metadata.
- Multiple `<Head>` instances are valid; layouts can provide defaults and pages can override them.

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

### Wayfinder Links

When Wayfinder is available, pass the returned object directly to `href`.

```react
import { Link } from '@inertiajs/react'
import { show } from '@/actions/App/Http/Controllers/UserController'

<Link href={show(1)}>View user</Link>
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
- `viewTransition` opts a link into the browser View Transitions API.
- Active links receive a `data-loading` attribute during in-flight requests.

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

For `router.get()`, explicitly set `preserveState` when a same-page visit should keep local state:

```react
router.get('/users', { search: 'John' }, { preserveState: true })
```

### Instant Visits

Instant visits render the target component immediately while page props load in the background.

```react
import { Link } from '@inertiajs/react'

<Link href="/dashboard" component="Dashboard">
    Dashboard
</Link>
```

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
- The old `future` config block is gone; those options are always enabled.
- Inertia packages are ESM-only; use `import`, not `require()`.

## Server-Side Notes

Server-side `Inertia::render()`, prop types like `Inertia::optional()` and `Inertia::defer()`, middleware, SSR, root templates, and error page rendering belong to the Inertia Laravel guidance.

## Common Pitfalls

- Using plain `<a>` tags for internal navigation
- Duplicating root-template `<head>` tags in page-level `<Head>` blocks
- Forgetting `head-key` on page-specific description or Open Graph tags that can appear more than once
- Repeating title boilerplate instead of using a small shared `AppHead` wrapper
- Using non-`GET` links as anchors instead of buttons
- Forgetting that Wayfinder objects can be passed directly to `Link href`
- Overlooking `replace`, `preserveState`, `preserveScroll`, `only`, or `viewTransition` when they improve UX
- Ignoring the `data-loading` attribute when styling loading navigation states
- Rebuilding page resolution manually when `@inertiajs/vite` already covers the setup
- Forgetting to keep the client `id` and server `@inertia(...)` root id in sync
- Registering `HandleInertiaRequests` in the wrong Laravel 12 location
- Skipping `@viteReactRefresh` in React development when touching the Blade root template
- Reintroducing Axios patterns when the built-in XHR client already covers the use case
- Using `router.cancel()` instead of `router.cancelAll()`
- Listening for `invalid` / `exception` instead of `httpException` / `networkError`
- Forgetting loading or empty states for deferred props
- Replacing existing content with skeletons during React deferred reloads when a smaller inline reloading indicator would be better
- Ignoring `preserveErrors` needs during partial reloads
- Using CommonJS `require()` imports with ESM-only Inertia packages
