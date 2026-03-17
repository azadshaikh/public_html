---
name: datagrid
description: Builds and configures datagrid (data table) views in this project. Use when creating index pages with tables, card grids, filters, tabs, sorting, pagination, row actions, bulk actions, or custom cell/card rendering.
---

# Datagrid

Use this skill when building or modifying index/listing pages that display paginated, filterable, sortable data in table or card grid views.

## File map

- `resources/js/components/datagrid/datagrid.tsx` — barrel export; import `Datagrid` and types from here.
- `resources/js/lib/scaffold-datagrid.ts` — shared adapter for scaffold-backed index pages; use it to derive filters, tabs, sorting, per-page state, and bulk actions from backend scaffold config.
- `resources/js/components/datagrid/types.ts` — all TypeScript types (`DatagridProps`, `DatagridColumn`, `DatagridAction`, `DatagridBulkAction`, `DatagridFilter`, `DatagridTab`, etc.).
- `resources/js/components/datagrid/datagrid-root.tsx` — main `<Datagrid>` component implementation.
- `resources/js/components/datagrid/datagrid-results.tsx` — table view, card grid view, compact card view, "Next Page" filler card, bulk action bar, confirmation dialogs.
- `resources/js/components/datagrid/datagrid-toolbar.tsx` — filter inputs, tab bar, submit button.
- `resources/js/components/datagrid/datagrid-filters.tsx` — individual filter components (search, select, date range, boolean, number, hidden).
- `resources/js/components/datagrid/datagrid-pagination.tsx` — pagination controls with per-page selector.
- `resources/js/components/datagrid/datagrid-action-menu.tsx` — row action dropdown with confirmation dialog support.
- `resources/js/components/datagrid/datagrid-cell-renderers.tsx` — built-in column type renderers (text, badge, boolean, currency, image, link, date).
- `resources/js/components/datagrid/sort-icon.tsx` — sort direction indicator.
- `resources/js/components/datagrid/utils.ts` — helper functions (param cleaning, form param collection, row key normalization).
- `resources/js/types/pagination.ts` — `PaginatedData<T>` type used by `rows` prop.

## Imports

Always import from the barrel:

```tsx
import { Datagrid } from '@/components/datagrid/datagrid';
import type {
    DatagridAction,
    DatagridBulkAction,
    DatagridColumn,
    DatagridFilter,
    DatagridTab,
} from '@/components/datagrid/datagrid';
```

For scaffold resources, also prefer the shared helpers from `@/lib/scaffold-datagrid` instead of hand-assembling query state, tabs, sorting, or bulk actions per page.

## Scaffold-backed golden path

For scaffold index pages, prefer this flow:

1. Treat `ScaffoldDefinition::toInertiaConfig()` as the backend source of truth.
2. Use `buildScaffoldDatagridState()` to derive filters, tabs, sorting, and per-page state.
3. Use `mapScaffoldRowActions()` to render row actions from backend action payloads.
4. Use `buildScaffoldBulkActions()` when scaffold actions should drive the bulk-action toolbar.

Use the shared scaffold helpers for datagrid state, but keep simple page-level navigation routes explicit with readable `route('...')` calls when those routes are fixed.

Do not invent parallel helpers like `buildDatagridColumns()` or `buildDatagridActions()` when the shared scaffold adapter already covers the page.

## Required props

| Prop        | Type                           | Purpose                                                                                   |
| ----------- | ------------------------------ | ----------------------------------------------------------------------------------------- |
| `action`    | `string`                       | URL the datagrid submits filter/sort/page changes to (typically `Controller.index().url`) |
| `rows`      | `PaginatedData<T>`             | Laravel paginated data from Inertia props                                                 |
| `columns`   | `DatagridColumn<T>[]`          | Column definitions for table view                                                         |
| `getRowKey` | `(row: T) => Key`              | Unique key extractor per row                                                              |
| `empty`     | `{ icon, title, description }` | Empty state content                                                                       |

For scaffold-backed pages, most of these props should come from `ScaffoldDefinition::toInertiaConfig()` plus the adapter in `resources/js/lib/scaffold-datagrid.ts`, not duplicated local page logic.

On the backend, keep common status, timestamp, filter, and destructive-action definitions explicit in each scaffold definition unless a resource-specific helper clearly improves readability.

## Key optional props

| Prop                | Type                                     | Purpose                                                               |
| ------------------- | ---------------------------------------- | --------------------------------------------------------------------- |
| `filters`           | `DatagridFilter[]`                       | Filter controls (search, select, date_range, boolean, number, hidden) |
| `tabs`              | `{ name: string; items: DatagridTab[] }` | Status/category tab bar                                               |
| `rowActions`        | `(row: T) => DatagridAction[]`           | Per-row action menu                                                   |
| `bulkActions`       | `DatagridBulkAction<T>[]`                | Toolbar actions for selected rows                                     |
| `isRowSelectable`   | `(row: T) => boolean`                    | Control which rows are selectable                                     |
| `sorting`           | `{ sort, direction }`                    | Current sort state from filters                                       |
| `perPage`           | `{ value, options }`                     | Per-page selector configuration                                       |
| `view`              | `{ value, storageKey? }`                 | Table/cards toggle with localStorage persistence                      |
| `renderCardHeader`  | `(row: T) => ReactNode`                  | Card header content (e.g., avatar + name)                             |
| `renderCard`        | `(row: T) => ReactNode`                  | Card body content                                                     |
| `cardGridClassName` | `string`                                 | Custom grid classes for compact card layouts (e.g., media thumbnails) |
| `searchDebounceMs`  | `number`                                 | Search input debounce (default: 350ms)                                |

## Column definitions

```tsx
const columns: DatagridColumn<Item>[] = [
    {
        key: 'name',
        header: 'Name',
        sortable: true,
        sortKey: 'display_name', // optional: different DB column for sorting
        cell: (row) => <span className="font-medium">{row.name}</span>,
    },
    {
        key: 'status_label',
        header: 'Status',
        type: 'badge',
        badgeVariantKey: 'status_badge', // reads variant from row field (e.g., 'success', 'danger')
        sortable: true,
        sortKey: 'status',
    },
    {
        key: 'type',
        header: 'Type',
        type: 'badge',
        badgeVariants: { admin: 'info', user: 'secondary', guest: 'outline' }, // static map
        sortable: true,
    },
    {
        key: 'created_at',
        header: 'Created',
        type: 'date',
        sortable: true,
        cellClassName: 'text-muted-foreground',
    },
];
```

When `cell` is provided, it overrides the `type` renderer. Use `type` for simple auto-rendering.

The `cardLabel` property on a column supplies a label when rendering that column's data inside a card view.

### Badge variant resolution

Badge columns resolve their variant in this priority order:

1. **Per-row variant** (`badgeVariantKey`) — Best when the backend sends a variant per row (e.g., `status_badge` from `ScaffoldResource::getStatusFields()`). Point `badgeVariantKey` at the row field name.
2. **Static map** (`badgeVariants`) — Use when variant mapping is fixed and known at column definition time.
3. **Auto-fallback** — When neither is provided, `resolveBadgeVariant()` guesses from the cell value string (e.g., "active" → `success`, "banned" → `danger`, "pending" → `warning`).

Available Badge component variants: `default`, `secondary`, `success`, `warning`, `info`, `danger`, `destructive`, `outline`, `ghost`, `link`.

### Backend Column API for badges

In `ScaffoldDefinition` column definitions, use `Column::badgeVariants()` to configure badge variants from the backend:

```php
// From an enum with a badge() method — auto-reads variant from each case
Column::make('status')->badgeVariants(Status::class)->sortable()

// From an explicit array
Column::make('type')->badgeVariants(['admin' => 'info', 'user' => 'secondary'])->sortable()

// Plain badge without variant map (auto-fallback on frontend)
Column::make('priority')->badge()->sortable()
```

`badgeVariants()` automatically sets the column type to `'badge'`. The variant map is serialized in `toInertiaConfig()` and available on the frontend column as `badgeVariants`.

### ScaffoldResource status_badge

`ScaffoldResource::getStatusFields()` automatically sends a `status_badge` field per row by calling the enum's `badge()` method. This means **no manual mapping is needed** — just point your column's `badgeVariantKey` to `'status_badge'`.

## Filter definitions

```tsx
const filters: DatagridFilter[] = [
    // Search — always debounced, auto-submits
    {
        type: 'search',
        name: 'search',
        value: filters.search,
        placeholder: 'Search users...',
        className: 'lg:min-w-80',
    },
    // Select dropdown
    {
        type: 'select',
        name: 'role',
        value: filters.role,
        options: roles.map((r) => ({ value: String(r.id), label: r.name })),
        multiple: false,
    },
    // Date range
    {
        type: 'date_range',
        name: 'created_at',
        value: filters.created_at,
        label: 'Created Date',
    },
    // Boolean toggle
    {
        type: 'boolean',
        name: 'verified',
        value: filters.verified,
        label: 'Verified',
        trueLabel: 'Yes',
        falseLabel: 'No',
    },
    // Hidden (for tab-driven state)
    {
        type: 'hidden',
        name: 'status',
        value: filters.status,
    },
];
```

Non-search filters require the user to click a submit button (`submitLabel` prop) or are auto-submitted depending on the filter type.

## Status tabs

```tsx
const tabs: DatagridTab[] = [
    {
        label: 'All',
        value: 'all',
        count: statistics.total,
        active: filters.status === 'all',
        icon: <ListIcon />,
        countVariant: 'secondary',
    },
    {
        label: 'Active',
        value: 'active',
        count: statistics.active,
        active: filters.status === 'active',
        icon: <CheckCircleIcon />,
        countVariant: 'success',
    },
    {
        label: 'Suspended',
        value: 'suspended',
        count: statistics.suspended,
        active: filters.status === 'suspended',
        icon: <PauseCircleIcon />,
        countVariant: 'warning',
    },
    {
        label: 'Banned',
        value: 'banned',
        count: statistics.banned,
        active: filters.status === 'banned',
        icon: <BanIcon />,
        countVariant: 'danger',
    },
    {
        label: 'Trash',
        value: 'trash',
        count: statistics.trash,
        active: filters.status === 'trash',
        icon: <Trash2Icon />,
        countVariant: 'destructive',
    },
];
```

`countVariant` supports all Badge variants: `default`, `secondary`, `success`, `warning`, `info`, `danger`, `destructive`, `outline`. Use semantically meaningful colors — `success` for active, `warning` for suspended/pending, `danger` for banned/failed, `destructive` for trash.

Pass as `tabs={{ name: 'status', items: tabs }}` — `name` is the query parameter name.

## Row actions

Row actions appear in a dropdown menu per row. They support navigation, mutations, and confirmations.

```tsx
const rowActions = (item: Item): DatagridAction[] => [
    // Navigation (GET link)
    { label: 'View', icon: <EyeIcon />, href: item.show_url },
    { label: 'Edit', icon: <PencilIcon />, href: item.edit_url },
    // Mutation with confirmation
    {
        label: 'Delete',
        icon: <Trash2Icon />,
        href: Controller.destroy(item.id).url,
        method: 'DELETE',
        confirm: 'Are you sure you want to delete this item?',
        variant: 'destructive',
    },
    // Conditionally hidden
    {
        label: 'Restore',
        icon: <RefreshCwIcon />,
        href: Controller.restore(item.id).url,
        method: 'PATCH',
        confirm: 'Restore this item?',
        hidden: !item.is_trashed,
    },
];
```

### Backend-driven row actions

When the backend provides row actions per item (via `ScaffoldDefinition`), map them:

```tsx
function mapBackendAction(action: RowAction): DatagridAction {
    if (action.method === 'GET') {
        return {
            label: action.label,
            icon: ICON_MAP[action.icon],
            href: action.url,
        };
    }
    return {
        label: action.label,
        icon: ICON_MAP[action.icon],
        href: action.url,
        method: action.method,
        confirm: action.confirm,
        variant: action.variant === 'danger' ? 'destructive' : 'default',
    };
}

const rowActions = (item: Item): DatagridAction[] =>
    Object.values(item.actions).map(mapBackendAction);
```

## Bulk actions

Bulk actions operate on selected rows and appear in a toolbar when rows are selected.

```tsx
const handleBulkAction = (
    action: string,
    selectedRows: Item[],
    clearSelection: () => void,
) => {
    router.post(
        Controller.bulkAction().url,
        { action, ids: selectedRows.map((r) => r.id) },
        { preserveScroll: true, onSuccess: () => clearSelection() },
    );
};

const bulkActions: DatagridBulkAction<Item>[] = [
    {
        key: 'bulk-delete',
        label: 'Move to Trash',
        icon: <Trash2Icon />,
        variant: 'destructive',
        confirm: 'Move selected items to trash?',
        onSelect: (rows, clear) => handleBulkAction('delete', rows, clear),
    },
];
```

Use `disabled` (boolean or function) to conditionally disable bulk actions. Use `confirm` to show a confirmation dialog before executing.

## Success feedback

Row and bulk actions that use `router.post/put/patch/delete` with `preserveScroll: true` get **automatic Sonner toast feedback** from the backend's flash messages. The global `initFlashToasts()` listener (wired in `app.tsx`) intercepts flash data on every Inertia navigation.

Backend controllers should use `->with('success', 'Message')` or `->with('error', 'Message')` on redirects. No additional frontend code is needed for action success feedback.

Do NOT use `ResourceFeedbackAlerts` for new pages. Flash messages are now handled globally via Sonner toasts.

## Card view

### Default card grid (with header)

Provide `renderCardHeader` and `renderCard` for a standard card layout with 2–3 columns:

```tsx
<Datagrid
    // ...
    view={{ value: filters.view, storageKey: 'items-view' }}
    renderCardHeader={(item) => (
        <>
            <Avatar className="size-10">
                <AvatarImage src={item.avatar} />
                <AvatarFallback>{item.initials}</AvatarFallback>
            </Avatar>
            <div className="min-w-0 flex-1">
                <div className="truncate font-medium">{item.name}</div>
                <div className="text-muted-foreground truncate">
                    {item.email}
                </div>
            </div>
        </>
    )}
    renderCard={(item) => (
        <div className="grid gap-3 sm:grid-cols-3">
            {/* card body content */}
        </div>
    )}
/>
```

The default card grid is `md:grid-cols-2 xl:grid-cols-3` with `gap-3`.

### Compact card grid (thumbnails)

For media-style thumbnails, use `cardGridClassName` with only `renderCard` (no `renderCardHeader`):

```tsx
<Datagrid
    // ...
    cardGridClassName="grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8 gap-3 p-3"
    renderCard={(item) => (
        <button type="button" className="relative aspect-square w-full">
            <img src={item.thumbnail} className="size-full object-cover" />
            <span className="absolute bottom-1 right-1 rounded bg-black/60 px-1 text-xs text-white">
                {item.extension}
            </span>
        </button>
    )}
/>
```

When `cardGridClassName` is set, cards render with zero padding (`gap-0 py-0`). Otherwise default cards use `gap-3 py-3`.

### "Next Page" filler card

When the last row of a card grid has empty slots, a "Next Page" navigation card automatically appears to fill the gap. This works for both default and compact grids. It uses a `ResizeObserver` to detect the actual CSS grid column count.

## View toggle

Enable table/cards toggle by providing a `view` prop:

```tsx
view={{
    value: filters.view, // 'table' | 'cards'
    storageKey: 'users-datagrid-view', // persist preference in localStorage
}}
```

## Sorting

```tsx
sorting={{
    sort: filters.sort,
    direction: filters.direction,
    sortParamName: 'sort',       // default
    directionParamName: 'direction', // default
}}
```

Columns with `sortable: true` get clickable headers. Use `sortKey` when the sort URL parameter differs from the column `key`.

## Per-page

```tsx
perPage={{
    value: filters.per_page,
    options: [10, 25, 50, 100],
    paramName: 'per_page', // default
}}
```

## Backend pattern

Backend controllers typically follow this pattern:

```php
// In controller index method:
return Inertia::render('resources/index', [
    'items' => $query->paginate($perPage),
    'filters' => $request->only(['search', 'status', 'sort', 'direction', 'per_page', 'view']),
    'statistics' => [...],
]);
```

The `action` prop on the Datagrid should point to this same controller action URL so filter/sort/page submissions reload the page with updated data.

## Rules

- Always import from `@/components/datagrid/datagrid`, not internal component files.
- Use Ziggy `route()` URLs for `action` and row action URLs.
- Provide `getRowKey` that returns a truly unique identifier (usually `row.id`).
- Always provide an `empty` state with an appropriate icon, title, and description.
- Use `preserveScroll: true` on all bulk action `router.post/put/delete` calls.
- Call `clearSelection()` in `onSuccess` of bulk actions to reset checkbox state.
- Use `isRowSelectable` to prevent selection of protected rows (e.g., system roles, super users).
- Conditionally filter `bulkActions` based on the current tab (e.g., hide "Delete" on trash tab, show "Restore" only on trash tab).
- For backend-driven actions, map `action.variant === 'danger'` to `variant: 'destructive'`.
- For badge columns, prefer `badgeVariantKey: 'status_badge'` when the backend sends per-row variants (via `ScaffoldResource::getStatusFields()`) over hardcoding variant maps in the page.
- Use `badgeVariants` (static map) on a column only when the variant mapping is page-specific and not derivable from the backend enum.
- Do NOT create hardcoded `STATUS_BADGE_VARIANT` constants in pages — use `badgeVariantKey` or `badgeVariants` on the column definition instead.
- Use semantically meaningful `countVariant` colors on status tabs to match the badge colors used in the status column (e.g., `success` for Active, `warning` for Suspended/Pending, `danger` for Banned/Failed, `destructive` for Trash).
- Use `hidden: true` on actions that should not appear for certain row states.
- Set `storageKey` on `view` to persist table/cards preference across sessions.
- Default `perPage.options` are `[10, 25, 50, 100]` for standard resources; use `[24, 48, 96]` for media/thumbnail grids.
- Do NOT use `ResourceFeedbackAlerts` on datagrid pages. Flash messages are handled globally by Sonner toasts.

## Full page example

```tsx
import { Link, router } from '@inertiajs/react';
import {
    EyeIcon,
    PencilIcon,
    PlusIcon,
    ShieldCheckIcon,
    Trash2Icon,
} from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type {
    DatagridBulkAction,
    DatagridColumn,
    DatagridFilter,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';

export default function ThingsIndex({ things, filters, statistics }: Props) {
    const columns: DatagridColumn<Thing>[] = [
        {
            key: 'name',
            header: 'Name',
            sortable: true,
            cell: (row) => <span className="font-medium">{row.name}</span>,
        },
        {
            key: 'status_label',
            header: 'Status',
            type: 'badge',
            badgeVariantKey: 'status_badge',
            sortable: true,
            sortKey: 'status',
        },
        { key: 'created_at', header: 'Created', type: 'date', sortable: true },
    ];

    const gridFilters: DatagridFilter[] = [
        {
            type: 'search',
            name: 'search',
            value: filters.search,
            placeholder: 'Search...',
        },
    ];

    const rowActions = (item: Thing) => [
        { label: 'View', icon: <EyeIcon />, href: route('app.things.show', { thing: item.id }) },
        { label: 'Edit', icon: <PencilIcon />, href: route('app.things.edit', { thing: item.id }) },
        {
            label: 'Delete',
            icon: <Trash2Icon />,
            href: route('app.things.destroy', { thing: item.id }),
            method: 'DELETE' as const,
            confirm: 'Delete this item?',
            variant: 'destructive' as const,
        },
    ];

    const handleBulkAction = (
        action: string,
        rows: Thing[],
        clear: () => void,
    ) => {
        router.post(
            route('app.things.bulk-action'),
            { action, ids: rows.map((r) => r.id) },
            { preserveScroll: true, onSuccess: () => clear() },
        );
    };

    return (
        <AppLayout
            title="Things"
            headerActions={
                <Button asChild>
                    <Link href={route('app.things.create')}>
                        <PlusIcon /> New
                    </Link>
                </Button>
            }
        >
            <Datagrid
                action={route('app.things.index')}
                rows={things}
                columns={columns}
                filters={gridFilters}
                getRowKey={(row) => row.id}
                rowActions={rowActions}
                sorting={{ sort: filters.sort, direction: filters.direction }}
                perPage={{
                    value: filters.per_page,
                    options: [10, 25, 50, 100],
                }}
                view={{ value: filters.view, storageKey: 'things-view' }}
                empty={{
                    icon: <ShieldCheckIcon />,
                    title: 'No things found',
                    description: 'Create the first thing.',
                }}
            />
        </AppLayout>
    );
}
```
