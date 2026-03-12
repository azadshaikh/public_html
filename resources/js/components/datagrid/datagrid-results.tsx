import * as React from 'react';
import type { Key } from 'react';
import { DatagridActionMenu } from '@/components/datagrid/datagrid-action-menu';
import { renderCellByType } from '@/components/datagrid/datagrid-cell-renderers';
import { DatagridPagination } from '@/components/datagrid/datagrid-pagination';
import { SortIcon } from '@/components/datagrid/sort-icon';
import type {
    DatagridAction,
    DatagridBulkAction,
    DatagridColumn,
    DatagridProps,
} from '@/components/datagrid/types';
import { normalizeRowKey } from '@/components/datagrid/utils';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardHeader,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Combobox,
    ComboboxContent,
    ComboboxItem,
    ComboboxList,
    ComboboxTrigger,
    ComboboxValue,
} from '@/components/ui/combobox';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

type DatagridResultsProps<T> = {
    rows: DatagridProps<T>['rows'];
    empty: DatagridProps<T>['empty'];
    hasSelection: boolean;
    selectedRows: T[];
    bulkActions: DatagridBulkAction<T>[];
    clearSelection: () => void;
    view?: DatagridProps<T>['view'];
    renderCardHeader?: DatagridProps<T>['renderCardHeader'];
    renderCard?: DatagridProps<T>['renderCard'];
    rowActions?: (row: T) => DatagridAction[];
    isRowSelectable?: (row: T) => boolean;
    selectedKeySet: Set<string>;
    getRowKey: (row: T) => Key;
    toggleRowSelection: (row: T, checked: boolean) => void;
    allSelectableRowsSelected: boolean;
    someSelectableRowsSelected: boolean;
    toggleAllRows: (checked: boolean) => void;
    resolvedColumns: DatagridColumn<T>[];
    columns: DatagridColumn<T>[];
    sorting?: DatagridProps<T>['sorting'];
    handleSort: (column: DatagridColumn<T>) => void;
    perPage?: DatagridProps<T>['perPage'];
    handlePerPageChange: (value: string) => void;
    resolvedSummary?: string;
};

export function DatagridResults<T>({
    rows,
    empty,
    hasSelection,
    selectedRows,
    bulkActions,
    clearSelection,
    view,
    renderCardHeader,
    renderCard,
    rowActions,
    isRowSelectable,
    selectedKeySet,
    getRowKey,
    toggleRowSelection,
    allSelectableRowsSelected,
    someSelectableRowsSelected,
    toggleAllRows,
    resolvedColumns,
    columns,
    sorting,
    handleSort,
    perPage,
    handlePerPageChange,
    resolvedSummary,
}: DatagridResultsProps<T>) {
    const [confirmBulkAction, setConfirmBulkAction] =
        React.useState<DatagridBulkAction<T> | null>(null);
    const perPageItems = React.useMemo(
        () =>
            perPage?.options.map((option) => ({
                label: String(option),
                value: String(option),
            })) ?? [],
        [perPage?.options],
    );
    const selectedPerPageItem = React.useMemo(
        () =>
            perPageItems.find(
                (item) => item.value === String(perPage?.value ?? ''),
            ) ?? null,
        [perPage?.value, perPageItems],
    );

    return (
        <Card className="mt-2 py-0">
            <CardContent className="p-0">
                {hasSelection && selectedRows.length > 0 ? (
                    <div className="flex flex-col gap-3 border-b bg-muted/40 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="text-sm text-foreground">
                            {selectedRows.length} selected
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {bulkActions.map((bulkAction) => {
                                const disabled =
                                    typeof bulkAction.disabled === 'function'
                                        ? bulkAction.disabled(selectedRows)
                                        : bulkAction.disabled;

                                return (
                                    <Button
                                        key={bulkAction.key}
                                        type="button"
                                        size="sm"
                                        variant={
                                            bulkAction.variant ?? 'outline'
                                        }
                                        disabled={disabled}
                                        onClick={() => {
                                            if (bulkAction.confirm) {
                                                setConfirmBulkAction(
                                                    bulkAction,
                                                );
                                                return;
                                            }
                                            bulkAction.onSelect(
                                                selectedRows,
                                                clearSelection,
                                            );
                                        }}
                                    >
                                        {bulkAction.icon}
                                        {bulkAction.label}
                                    </Button>
                                );
                            })}
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={clearSelection}
                            >
                                Clear
                            </Button>
                        </div>
                    </div>
                ) : null}

                <AlertDialog
                    open={!!confirmBulkAction}
                    onOpenChange={(open) => !open && setConfirmBulkAction(null)}
                >
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                            <AlertDialogDescription>
                                {confirmBulkAction?.confirm}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                variant={
                                    confirmBulkAction?.variant === 'destructive'
                                        ? 'destructive'
                                        : 'default'
                                }
                                onClick={() => {
                                    if (confirmBulkAction) {
                                        confirmBulkAction.onSelect(
                                            selectedRows,
                                            clearSelection,
                                        );
                                    }
                                    setConfirmBulkAction(null);
                                }}
                            >
                                Confirm
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                {rows.data.length === 0 ? (
                    <div className="p-4">
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    {empty.icon}
                                </EmptyMedia>
                                <EmptyTitle>{empty.title}</EmptyTitle>
                                <EmptyDescription>
                                    {empty.description}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    </div>
                ) : view?.value === 'cards' && renderCard ? (
                    <div className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
                        {rows.data.map((row) => {
                            const rowKey = normalizeRowKey(getRowKey(row));
                            const actions =
                                rowActions?.(row).filter(
                                    (rowAction) => !rowAction.hidden,
                                ) ?? [];
                            const rowSelectable = isRowSelectable
                                ? isRowSelectable(row)
                                : true;
                            const isSelected = selectedKeySet.has(rowKey);

                            return (
                                <Card
                                    key={rowKey}
                                    className={cn(
                                        'overflow-hidden',
                                        isSelected &&
                                            'border-primary/40 bg-primary/5',
                                    )}
                                >
                                    {renderCardHeader ? (
                                        <>
                                            <CardHeader className="border-b pt-4">
                                                <div className="flex min-w-0 items-start gap-3">
                                                    {hasSelection ? (
                                                        <Checkbox
                                                            checked={isSelected}
                                                            disabled={
                                                                !rowSelectable
                                                            }
                                                            aria-label="Select row"
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                toggleRowSelection(
                                                                    row,
                                                                    checked ===
                                                                        true,
                                                                )
                                                            }
                                                        />
                                                    ) : null}
                                                    <div className="min-w-0 flex-1">
                                                        {renderCardHeader(row)}
                                                    </div>
                                                </div>
                                                {actions.length > 0 ? (
                                                    <CardAction>
                                                        <DatagridActionMenu
                                                            actions={actions}
                                                        />
                                                    </CardAction>
                                                ) : null}
                                            </CardHeader>
                                            <CardContent className="flex flex-col gap-4 p-4">
                                                {renderCard(row)}
                                            </CardContent>
                                        </>
                                    ) : (
                                        <CardContent className="flex flex-col gap-4 p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                {hasSelection ? (
                                                    <Checkbox
                                                        checked={isSelected}
                                                        disabled={
                                                            !rowSelectable
                                                        }
                                                        aria-label="Select row"
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggleRowSelection(
                                                                row,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                ) : (
                                                    <div />
                                                )}
                                                {actions.length > 0 ? (
                                                    <DatagridActionMenu
                                                        actions={actions}
                                                    />
                                                ) : null}
                                            </div>
                                            {renderCard(row)}
                                        </CardContent>
                                    )}
                                </Card>
                            );
                        })}
                    </div>
                ) : (
                    <Table className="table-auto">
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                {hasSelection ? (
                                    <TableHead
                                        className="w-12 cursor-pointer px-4"
                                        onClick={(e) => {
                                            if (
                                                (
                                                    e.target as HTMLElement
                                                ).closest(
                                                    'button[role="checkbox"]',
                                                )
                                            ) {
                                                return;
                                            }
                                            toggleAllRows(
                                                !allSelectableRowsSelected,
                                            );
                                        }}
                                    >
                                        <Checkbox
                                            checked={
                                                allSelectableRowsSelected
                                                    ? true
                                                    : someSelectableRowsSelected
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            aria-label="Select all rows"
                                            onCheckedChange={(checked) =>
                                                toggleAllRows(checked === true)
                                            }
                                        />
                                    </TableHead>
                                ) : null}

                                {resolvedColumns.map((column) => {
                                    const sortKey =
                                        column.sortKey ?? column.key;
                                    const isSorted = sorting?.sort === sortKey;
                                    const headerLabel =
                                        column.header.toUpperCase();

                                    return (
                                        <TableHead
                                            key={column.key}
                                            className={cn(
                                                'h-11 px-4 pt-1 align-middle text-[0.72rem] font-bold tracking-[0.03em] text-muted-foreground uppercase',
                                                column.headerClassName,
                                            )}
                                        >
                                            {column.sortable ? (
                                                <button
                                                    type="button"
                                                    className="inline-flex h-full items-center gap-1.5 text-left leading-none transition-colors hover:text-foreground"
                                                    onClick={() =>
                                                        handleSort(column)
                                                    }
                                                >
                                                    <span className="leading-none">
                                                        {headerLabel}
                                                    </span>
                                                    <SortIcon
                                                        active={isSorted}
                                                        direction={
                                                            isSorted
                                                                ? sorting?.direction
                                                                : undefined
                                                        }
                                                    />
                                                </button>
                                            ) : (
                                                <span className="inline-flex h-full items-center leading-none">
                                                    {headerLabel}
                                                </span>
                                            )}
                                        </TableHead>
                                    );
                                })}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.data.map((row) => {
                                const rowKey = normalizeRowKey(getRowKey(row));
                                const actions = rowActions?.(row).filter(
                                    (rowAction) => !rowAction.hidden,
                                );
                                const rowSelectable = isRowSelectable
                                    ? isRowSelectable(row)
                                    : true;
                                const isSelected = selectedKeySet.has(rowKey);

                                return (
                                    <TableRow
                                        key={rowKey}
                                        data-state={
                                            isSelected ? 'selected' : undefined
                                        }
                                    >
                                        {hasSelection ? (
                                            <TableCell
                                                className={cn(
                                                    'px-4 py-3 align-top',
                                                    rowSelectable &&
                                                        'cursor-pointer',
                                                )}
                                                onClick={(e) => {
                                                    if (
                                                        !rowSelectable ||
                                                        (
                                                            e.target as HTMLElement
                                                        ).closest(
                                                            'button[role="checkbox"]',
                                                        )
                                                    ) {
                                                        return;
                                                    }
                                                    toggleRowSelection(
                                                        row,
                                                        !isSelected,
                                                    );
                                                }}
                                            >
                                                <Checkbox
                                                    checked={isSelected}
                                                    disabled={!rowSelectable}
                                                    aria-label="Select row"
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleRowSelection(
                                                            row,
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                            </TableCell>
                                        ) : null}

                                        {columns.map((column) => (
                                            <TableCell
                                                key={`${rowKey}-${column.key}`}
                                                className={cn(
                                                    'px-4 py-3 align-top whitespace-normal',
                                                    column.cellClassName,
                                                )}
                                            >
                                                {column.cell
                                                    ? column.cell(row)
                                                    : renderCellByType({
                                                          value: (
                                                              row as Record<
                                                                  string,
                                                                  unknown
                                                              >
                                                          )[column.key],
                                                          type:
                                                              column.type ??
                                                              'text',
                                                      })}
                                            </TableCell>
                                        ))}
                                        {rowActions ? (
                                            <TableCell className="px-4 py-3 text-right align-top">
                                                <DatagridActionMenu
                                                    actions={actions ?? []}
                                                />
                                            </TableCell>
                                        ) : null}
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                )}

                {rows.data.length > 0 ? (
                    <div className="flex flex-col items-center gap-4 border-t px-4 pt-4 pb-4 text-center sm:min-h-11 sm:flex-row sm:justify-between sm:gap-4 sm:py-0 sm:text-left">
                        <div className="flex w-full flex-col items-center gap-3 text-xs text-muted-foreground sm:w-auto sm:flex-row sm:flex-nowrap sm:gap-3">
                            {perPage ? (
                                <div className="flex shrink-0 items-center gap-2">
                                    <span>Per page:</span>
                                    <Combobox
                                        items={perPageItems}
                                        itemToStringLabel={(item) => item.label}
                                        value={selectedPerPageItem}
                                        onValueChange={(item) => {
                                            if (!item) {
                                                return;
                                            }

                                            handlePerPageChange(item.value);
                                        }}
                                    >
                                        <ComboboxTrigger
                                            render={
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="w-20 justify-between px-2 font-normal"
                                                />
                                            }
                                        >
                                            <ComboboxValue />
                                        </ComboboxTrigger>
                                        <ComboboxContent>
                                            <ComboboxList>
                                                {perPageItems.map((item) => (
                                                    <ComboboxItem
                                                        key={`per-page-${item.value}`}
                                                        value={item}
                                                    >
                                                        {item.label}
                                                    </ComboboxItem>
                                                ))}
                                            </ComboboxList>
                                        </ComboboxContent>
                                    </Combobox>
                                </div>
                            ) : null}

                            <div className="leading-5 sm:whitespace-nowrap">
                                {resolvedSummary}
                            </div>
                        </div>

                        <DatagridPagination links={rows.links} />
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}
