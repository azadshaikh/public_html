import { router } from '@inertiajs/react';
import { ChevronRightIcon } from 'lucide-react';
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
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
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
import type { BadgeVariant } from '@/types/ui';

function buildColumnWidthStyle(
    width?: string | number,
): React.CSSProperties | undefined {
    if (typeof width === 'number') {
        const pixelWidth = `${width}px`;

        return {
            width: pixelWidth,
            minWidth: pixelWidth,
            maxWidth: pixelWidth,
        };
    }

    if (typeof width !== 'string') {
        return undefined;
    }

    const trimmedWidth = width.trim();

    if (trimmedWidth === '') {
        return undefined;
    }

    return {
        width: trimmedWidth,
        minWidth: trimmedWidth,
        maxWidth: trimmedWidth,
    };
}

type DatagridResultsProps<T> = {
    rows: DatagridProps<T>['rows'];
    empty: DatagridProps<T>['empty'];
    hasSelection: boolean;
    selectionColumnWidth: string;
    selectedRows: T[];
    bulkActions: DatagridBulkAction<T>[];
    clearSelection: () => void;
    view?: DatagridProps<T>['view'];
    renderCardHeader?: DatagridProps<T>['renderCardHeader'];
    renderCard?: DatagridProps<T>['renderCard'];
    cardGridClassName?: string;
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
    selectionColumnWidth,
    selectedRows,
    bulkActions,
    clearSelection,
    view,
    renderCardHeader,
    renderCard,
    cardGridClassName,
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
    const selectionColumnStyle = React.useMemo(
        () => buildColumnWidthStyle(selectionColumnWidth),
        [selectionColumnWidth],
    );
    const actionColumnStyle = React.useMemo(
        () =>
            buildColumnWidthStyle(
                resolvedColumns.find((column) => column.key === '__actions')
                    ?.width,
            ),
        [resolvedColumns],
    );
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

                <ConfirmationDialog
                    open={!!confirmBulkAction}
                    onOpenChange={(open) => !open && setConfirmBulkAction(null)}
                    title={confirmBulkAction?.label}
                    description={confirmBulkAction?.confirm}
                    confirmLabel={confirmBulkAction?.label}
                    tone={
                        confirmBulkAction?.variant === 'destructive'
                            ? 'destructive'
                            : 'default'
                    }
                    onConfirm={() => {
                        if (confirmBulkAction) {
                            confirmBulkAction.onSelect(
                                selectedRows,
                                clearSelection,
                            );
                        }
                    }}
                />

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
                    <CardGridView
                        rows={rows}
                        getRowKey={getRowKey}
                        rowActions={rowActions}
                        isRowSelectable={isRowSelectable}
                        selectedKeySet={selectedKeySet}
                        toggleRowSelection={toggleRowSelection}
                        hasSelection={hasSelection}
                        renderCardHeader={renderCardHeader}
                        renderCard={renderCard}
                        cardGridClassName={cardGridClassName}
                    />
                ) : (
                    <Table className="table-auto">
                        <colgroup>
                            {hasSelection ? (
                                <col style={selectionColumnStyle} />
                            ) : null}
                            {resolvedColumns.map((column) => (
                                <col
                                    key={`col-${column.key}`}
                                    style={buildColumnWidthStyle(column.width)}
                                />
                            ))}
                        </colgroup>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                {hasSelection ? (
                                    <TableHead
                                        className="cursor-pointer px-4"
                                        style={selectionColumnStyle}
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
                                            style={buildColumnWidthStyle(
                                                column.width,
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
                                                    'px-4 py-3 align-middle',
                                                    rowSelectable &&
                                                        'cursor-pointer',
                                                )}
                                                style={selectionColumnStyle}
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
                                                    'px-4 py-3 align-middle whitespace-normal',
                                                    column.cellClassName,
                                                )}
                                                style={buildColumnWidthStyle(
                                                    column.width,
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
                                                          badgeVariant:
                                                              column.badgeVariantKey
                                                                  ? ((
                                                                        row as Record<
                                                                            string,
                                                                            | BadgeVariant
                                                                            | undefined
                                                                        >
                                                                    )[
                                                                        column
                                                                            .badgeVariantKey
                                                                    ] ??
                                                                    undefined)
                                                                  : undefined,
                                                          badgeVariants:
                                                              column.badgeVariants,
                                                      })}
                                            </TableCell>
                                        ))}
                                        {rowActions ? (
                                            <TableCell
                                                className="px-4 py-3 text-right align-middle"
                                                style={actionColumnStyle}
                                            >
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

// ─── Card Grid View (with column-aware "Next Page" filler) ───────────────

type CardGridViewProps<T> = {
    rows: DatagridResultsProps<T>['rows'];
    getRowKey: DatagridResultsProps<T>['getRowKey'];
    rowActions?: DatagridResultsProps<T>['rowActions'];
    isRowSelectable?: DatagridResultsProps<T>['isRowSelectable'];
    selectedKeySet: DatagridResultsProps<T>['selectedKeySet'];
    toggleRowSelection: DatagridResultsProps<T>['toggleRowSelection'];
    hasSelection: boolean;
    renderCardHeader?: DatagridResultsProps<T>['renderCardHeader'];
    renderCard: NonNullable<DatagridResultsProps<T>['renderCard']>;
    cardGridClassName?: string;
};

function CardGridView<T>({
    rows,
    getRowKey,
    rowActions,
    isRowSelectable,
    selectedKeySet,
    toggleRowSelection,
    hasSelection,
    renderCardHeader,
    renderCard,
    cardGridClassName,
}: CardGridViewProps<T>) {
    const gridRef = React.useRef<HTMLDivElement>(null);
    const [colCount, setColCount] = React.useState(1);

    React.useEffect(() => {
        const el = gridRef.current;
        if (!el) return;

        const detectColumns = () => {
            const style = getComputedStyle(el);
            const cols = style.gridTemplateColumns.split(' ').length;
            setColCount(cols);
        };

        detectColumns();

        const observer = new ResizeObserver(detectColumns);
        observer.observe(el);
        return () => observer.disconnect();
    }, []);

    const itemCount = rows.data.length;
    const hasEmptySlots = itemCount > 0 && itemCount % colCount !== 0;
    const showNextCard = !!rows.next_page_url && hasEmptySlots;

    return (
        <div
            ref={gridRef}
            className={cn(
                'grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3',
                cardGridClassName,
            )}
        >
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
                            'group relative overflow-hidden border border-border/70 bg-linear-to-br from-background via-background to-muted/20 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md',
                            'gap-0 py-0',
                            isSelected &&
                                'border-primary/40 bg-primary/5 ring-2 ring-primary',
                        )}
                    >
                        <div className="pointer-events-none absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-primary/35 to-transparent" />
                        {renderCardHeader ? (
                            <>
                                <CardHeader className="relative border-b border-border/60 bg-linear-to-r from-muted/40 via-background to-background px-4 py-4">
                                    <div className="flex min-w-0 items-start gap-3">
                                        {hasSelection ? (
                                            <Checkbox
                                                checked={isSelected}
                                                disabled={!rowSelectable}
                                                aria-label="Select row"
                                                onCheckedChange={(checked) =>
                                                    toggleRowSelection(
                                                        row,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                        ) : null}
                                        <div className="min-w-0 flex-1 pt-0.5">
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
                                <CardContent className="p-4">
                                    {renderCard(row)}
                                </CardContent>
                            </>
                        ) : cardGridClassName ? (
                            <CardContent className="relative flex flex-1 p-0">
                                <div className="absolute top-1.5 left-1.5 z-10">
                                    {hasSelection ? (
                                        <Checkbox
                                            checked={isSelected}
                                            disabled={!rowSelectable}
                                            aria-label="Select row"
                                            className="border-white/70 bg-black/30 data-[state=checked]:border-primary data-[state=checked]:bg-primary"
                                            onCheckedChange={(checked) =>
                                                toggleRowSelection(
                                                    row,
                                                    checked === true,
                                                )
                                            }
                                        />
                                    ) : null}
                                </div>
                                {actions.length > 0 ? (
                                    <div className="absolute top-1 right-1 z-10">
                                        <DatagridActionMenu actions={actions} />
                                    </div>
                                ) : null}
                                {renderCard(row)}
                            </CardContent>
                        ) : (
                            <CardContent className="p-0">
                                <div className="flex items-center justify-between gap-3 border-b border-border/60 bg-linear-to-r from-muted/40 via-background to-background px-4 py-3">
                                    <div className="flex items-center gap-3">
                                        {hasSelection ? (
                                            <Checkbox
                                                checked={isSelected}
                                                disabled={!rowSelectable}
                                                aria-label="Select row"
                                                onCheckedChange={(checked) =>
                                                    toggleRowSelection(
                                                        row,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                        ) : null}
                                        <span className="text-[0.68rem] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                            Overview
                                        </span>
                                    </div>
                                    {actions.length > 0 ? (
                                        <DatagridActionMenu actions={actions} />
                                    ) : null}
                                </div>
                                <div className="p-4">{renderCard(row)}</div>
                            </CardContent>
                        )}
                    </Card>
                );
            })}

            {/* "Next page" filler — only when column count leaves empty slots */}
            {showNextCard ? (
                <Card
                    className={cn(
                        'overflow-hidden border border-dashed border-border/70 bg-linear-to-br from-background via-background to-muted/20 shadow-sm',
                        'gap-0 py-0',
                    )}
                >
                    <CardContent
                        className={cn(
                            'flex flex-1 items-center justify-center',
                            cardGridClassName ? 'aspect-square p-0' : 'p-6',
                        )}
                    >
                        <button
                            type="button"
                            onClick={() =>
                                router.visit(rows.next_page_url!, {
                                    preserveScroll: true,
                                    preserveState: true,
                                })
                            }
                            className="flex size-full flex-col items-center justify-center gap-2 text-muted-foreground transition-colors hover:text-foreground"
                        >
                            <ChevronRightIcon className="size-6" />
                            <span className="text-xs font-medium">
                                Next Page
                            </span>
                        </button>
                    </CardContent>
                </Card>
            ) : null}
        </div>
    );
}
