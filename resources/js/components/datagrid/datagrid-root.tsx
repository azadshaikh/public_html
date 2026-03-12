import { router } from '@inertiajs/react';
import * as React from 'react';
import { DatagridResults } from '@/components/datagrid/datagrid-results';
import { DatagridToolbar } from '@/components/datagrid/datagrid-toolbar';
import type {
    DatagridColumn,
    DatagridProps,
} from '@/components/datagrid/types';
import {
    cleanParams,
    collectFormParams,
    normalizeRowKey,
} from '@/components/datagrid/utils';
import { CardDescription, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export function Datagrid<T>({
    action,
    rows,
    columns,
    filters = [],
    tabs,
    getRowKey,
    rowActions,
    bulkActions = [],
    isRowSelectable,
    empty,
    sorting,
    perPage,
    view,
    renderCard,
    summary,
    submitLabel = 'Apply',
    submitButtonVariant = 'outline',
    submitButtonSize = 'comfortable',
    title,
    description,
    className,
    searchDebounceMs = 350,
}: DatagridProps<T>) {
    const searchInputRef = React.useRef<HTMLInputElement | null>(null);
    const searchTimeoutRef = React.useRef<number | null>(null);
    const storedViewAppliedRef = React.useRef(false);
    const [selectedKeys, setSelectedKeys] = React.useState<string[]>([]);

    const visit = React.useCallback(
        (params: Record<string, string | number | null | undefined>) => {
            router.get(action, cleanParams(params), {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            });
        },
        [action],
    );

    const sortParamName = sorting?.sortParamName ?? 'sort';
    const directionParamName = sorting?.directionParamName ?? 'direction';
    const perPageParamName = perPage?.paramName ?? 'per_page';
    const viewParamName = view?.paramName ?? 'view';
    const activeTabValue = tabs?.items.find((item) => item.active)?.value ?? '';
    const currentParams = React.useMemo(() => {
        const params = Object.fromEntries(
            filters.map((filter) => [filter.name, filter.value]),
        ) as Record<string, string>;

        if (tabs && activeTabValue !== '') {
            params[tabs.name] = activeTabValue;
        }

        if (sorting) {
            params[sortParamName] = sorting.sort;
            params[directionParamName] = sorting.direction;
        }

        if (perPage) {
            params[perPageParamName] = String(perPage.value);
        }

        if (view) {
            params[viewParamName] = view.value;
        }

        return params;
    }, [
        activeTabValue,
        directionParamName,
        filters,
        perPage,
        perPageParamName,
        sortParamName,
        sorting,
        tabs,
        view,
        viewParamName,
    ]);

    const hasVisibleFilters = filters.some(
        (filter) => filter.type !== 'hidden',
    );
    const hasToolbar = Boolean(tabs || hasVisibleFilters || perPage || view);
    const hasSelection = bulkActions.length > 0;
    const resolvedColumns = React.useMemo(() => {
        const mappedColumns = [...columns];

        if (rowActions) {
            mappedColumns.push({
                key: '__actions',
                header: 'Actions',
                headerClassName: 'w-14 text-right',
                cellClassName: 'w-14 text-right',
                cell: () => null,
            });
        }

        return mappedColumns;
    }, [columns, rowActions]) as DatagridColumn<T>[];

    const selectableRows = React.useMemo(
        () =>
            rows.data.filter((row) =>
                isRowSelectable ? isRowSelectable(row) : true,
            ),
        [isRowSelectable, rows.data],
    );
    const selectableRowKeys = React.useMemo(
        () => selectableRows.map((row) => normalizeRowKey(getRowKey(row))),
        [getRowKey, selectableRows],
    );
    const selectedKeySet = React.useMemo(
        () => new Set(selectedKeys),
        [selectedKeys],
    );
    const selectedRows = React.useMemo(
        () =>
            rows.data.filter((row) =>
                selectedKeySet.has(normalizeRowKey(getRowKey(row))),
            ),
        [getRowKey, rows.data, selectedKeySet],
    );
    const allSelectableRowsSelected =
        selectableRowKeys.length > 0 &&
        selectableRowKeys.every((key) => selectedKeySet.has(key));
    const someSelectableRowsSelected =
        !allSelectableRowsSelected &&
        selectableRowKeys.some((key) => selectedKeySet.has(key));
    const resolvedSummary =
        summary ??
        (rows.total > 0
            ? `Showing ${rows.from ?? 0} to ${rows.to ?? 0} of ${rows.total} results`
            : undefined);

    React.useEffect(() => {
        setSelectedKeys([]);
    }, [rows.data]);

    React.useEffect(() => {
        return () => {
            if (searchTimeoutRef.current !== null) {
                window.clearTimeout(searchTimeoutRef.current);
            }
        };
    }, []);

    React.useEffect(() => {
        if (!view?.storageKey || storedViewAppliedRef.current) {
            return;
        }

        if (typeof window === 'undefined') {
            return;
        }

        storedViewAppliedRef.current = true;

        const url = new URL(window.location.href);

        if (url.searchParams.has(viewParamName)) {
            return;
        }

        const storedView = window.localStorage.getItem(view.storageKey);

        if (
            storedView === null ||
            storedView === '' ||
            storedView === view.value ||
            (storedView !== 'table' && storedView !== 'cards')
        ) {
            return;
        }

        visit({
            ...currentParams,
            [viewParamName]: storedView,
            page: 1,
        });
    }, [currentParams, view, viewParamName, visit]);

    React.useEffect(() => {
        const searchFilter = filters.find((filter) => filter.type === 'search');

        if (!searchFilter) {
            return;
        }

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key !== '/') {
                return;
            }

            const activeElement = document.activeElement;

            if (
                activeElement instanceof HTMLInputElement ||
                activeElement instanceof HTMLTextAreaElement ||
                activeElement instanceof HTMLSelectElement ||
                activeElement instanceof HTMLButtonElement ||
                activeElement?.getAttribute('contenteditable') === 'true'
            ) {
                return;
            }

            event.preventDefault();
            searchInputRef.current?.focus();
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => {
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [filters]);

    const handleTabChange = (value: string) => {
        visit({
            ...currentParams,
            [tabs!.name]: value,
            page: 1,
        });
    };

    const handleSort = (column: DatagridColumn<T>) => {
        if (!sorting || !column.sortable) {
            return;
        }

        const sortKey = column.sortKey ?? column.key;
        const nextDirection =
            sorting.sort === sortKey && sorting.direction === 'asc'
                ? 'desc'
                : 'asc';

        visit({
            ...currentParams,
            [sortParamName]: sortKey,
            [directionParamName]: nextDirection,
            page: 1,
        });
    };

    const handlePerPageChange = (value: string) => {
        visit({
            ...currentParams,
            [perPageParamName]: value,
            page: 1,
        });
    };

    const handleViewChange = (value: string) => {
        if (value !== 'table' && value !== 'cards') {
            return;
        }

        if (view?.storageKey && typeof window !== 'undefined') {
            window.localStorage.setItem(view.storageKey, value);
        }

        visit({
            ...currentParams,
            [viewParamName]: value,
            page: 1,
        });
    };

    const handleSearchChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const form = event.currentTarget.form;

        if (form === null) {
            return;
        }

        if (searchTimeoutRef.current !== null) {
            window.clearTimeout(searchTimeoutRef.current);
        }

        searchTimeoutRef.current = window.setTimeout(() => {
            visit({
                ...collectFormParams(form),
                page: 1,
            });
        }, searchDebounceMs);
    };

    const toggleRowSelection = (row: T, checked: boolean) => {
        const key = normalizeRowKey(getRowKey(row));

        setSelectedKeys((currentKeys) => {
            if (checked) {
                return currentKeys.includes(key)
                    ? currentKeys
                    : [...currentKeys, key];
            }

            return currentKeys.filter((currentKey) => currentKey !== key);
        });
    };

    const toggleAllRows = (checked: boolean) => {
        setSelectedKeys(checked ? selectableRowKeys : []);
    };

    return (
        <div className={cn('flex flex-col gap-4', className)}>
            {title || description ? (
                <div className="flex flex-col gap-1">
                    {title ? <CardTitle>{title}</CardTitle> : null}
                    {description ? (
                        <CardDescription>{description}</CardDescription>
                    ) : null}
                </div>
            ) : null}

            {hasToolbar ? (
                <DatagridToolbar
                    tabs={tabs}
                    activeTabValue={activeTabValue}
                    hasVisibleFilters={hasVisibleFilters}
                    filters={filters}
                    sorting={sorting}
                    perPage={perPage}
                    view={view}
                    renderCard={
                        renderCard as
                            | ((row: unknown) => React.ReactNode)
                            | undefined
                    }
                    sortParamName={sortParamName}
                    directionParamName={directionParamName}
                    perPageParamName={perPageParamName}
                    viewParamName={viewParamName}
                    submitLabel={submitLabel}
                    submitButtonVariant={submitButtonVariant}
                    submitButtonSize={submitButtonSize}
                    searchInputRef={searchInputRef}
                    onTabChange={handleTabChange}
                    onSearchChange={handleSearchChange}
                    onFilterSubmit={visit}
                    onViewChange={handleViewChange}
                />
            ) : null}

            <DatagridResults
                rows={rows}
                empty={empty}
                hasSelection={hasSelection}
                selectedRows={selectedRows}
                bulkActions={bulkActions}
                setSelectedKeys={setSelectedKeys}
                view={view}
                renderCard={renderCard}
                rowActions={rowActions}
                isRowSelectable={isRowSelectable}
                selectedKeySet={selectedKeySet}
                getRowKey={getRowKey}
                toggleRowSelection={toggleRowSelection}
                allSelectableRowsSelected={allSelectableRowsSelected}
                someSelectableRowsSelected={someSelectableRowsSelected}
                toggleAllRows={toggleAllRows}
                resolvedColumns={resolvedColumns}
                columns={columns}
                sorting={sorting}
                handleSort={handleSort}
                perPage={perPage}
                handlePerPageChange={handlePerPageChange}
                resolvedSummary={resolvedSummary}
            />
        </div>
    );
}
