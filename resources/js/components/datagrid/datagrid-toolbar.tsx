import {
    FilterIcon,
    LayoutGridIcon,
    Rows3Icon,
    SearchIcon,
    XIcon,
} from 'lucide-react';
import * as React from 'react';
import {
    DatagridBooleanFilterField,
    DatagridDateRangeFilterField,
    DatagridNumberFilterField,
    DatagridSelectFilterField,
} from '@/components/datagrid/datagrid-filters';
import type {
    DatagridBooleanFilter,
    DatagridDateRangeFilter,
    DatagridFilter,
    DatagridHiddenFilter,
    DatagridNumberFilter,
    DatagridProps,
    DatagridSearchFilter,
    DatagridSelectFilter,
    DatagridTab,
    DatagridViewMode,
} from '@/components/datagrid/types';
import { collectFormParams } from '@/components/datagrid/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';

type DatagridToolbarProps = {
    tabs?: {
        name: string;
        items: DatagridTab[];
    };
    activeTabValue: string;
    hasVisibleFilters: boolean;
    filters: DatagridFilter[];
    sorting?: DatagridProps<unknown>['sorting'];
    perPage?: DatagridProps<unknown>['perPage'];
    view?: DatagridProps<unknown>['view'];
    renderCard?: (row: unknown) => React.ReactNode;
    sortParamName: string;
    directionParamName: string;
    perPageParamName: string;
    viewParamName: string;
    submitLabel: string;
    submitButtonVariant: NonNullable<
        DatagridProps<unknown>['submitButtonVariant']
    >;
    submitButtonSize: NonNullable<DatagridProps<unknown>['submitButtonSize']>;
    searchInputRef: React.RefObject<HTMLInputElement | null>;
    onTabChange: (value: string) => void;
    onSearchChange: (event: React.ChangeEvent<HTMLInputElement>) => void;
    onFilterSubmit: (
        params: Record<string, string | number | null | undefined>,
    ) => void;
    onViewChange: (value: string) => void;
};

export function DatagridToolbar({
    tabs,
    activeTabValue,
    hasVisibleFilters,
    filters,
    sorting,
    perPage,
    view,
    renderCard,
    sortParamName,
    directionParamName,
    perPageParamName,
    viewParamName,
    submitLabel,
    submitButtonVariant,
    submitButtonSize,
    searchInputRef,
    onTabChange,
    onSearchChange,
    onFilterSubmit,
    onViewChange,
}: DatagridToolbarProps) {
    const [isFilterSheetOpen, setIsFilterSheetOpen] = React.useState(false);
    const filterFormId = React.useId();
    const isMobile = useIsMobile();

    const searchFilters = filters.filter(
        (filter): filter is DatagridSearchFilter => filter.type === 'search',
    );
    const primarySearchFilter = searchFilters[0] ?? null;
    const advancedSearchFilters = searchFilters.slice(1);
    const selectFilters = filters.filter(
        (filter): filter is DatagridSelectFilter => filter.type === 'select',
    );
    const dateRangeFilters = filters.filter(
        (filter): filter is DatagridDateRangeFilter =>
            filter.type === 'date_range',
    );
    const booleanFilters = filters.filter(
        (filter): filter is DatagridBooleanFilter => filter.type === 'boolean',
    );
    const numberFilters = filters.filter(
        (filter): filter is DatagridNumberFilter => filter.type === 'number',
    );
    const hiddenFilters = filters.filter(
        (filter): filter is DatagridHiddenFilter => filter.type === 'hidden',
    );

    const sheetFilterCount =
        advancedSearchFilters.length +
        selectFilters.length +
        dateRangeFilters.length +
        booleanFilters.length +
        numberFilters.length;

    const activeSheetFilterCount =
        advancedSearchFilters.filter((filter) => filter.value !== '').length +
        selectFilters.filter(
            (filter) => filter.value !== getFilterDefaultValue(),
        ).length +
        dateRangeFilters.filter((filter) => filter.value !== '').length +
        booleanFilters.filter((filter) => filter.value !== '').length +
        numberFilters.filter((filter) => filter.value !== '').length;

    if (!tabs && !hasVisibleFilters) {
        return null;
    }

    const sharedParams = buildSharedParams({
        tabs,
        activeTabValue,
        sorting,
        perPage,
        view,
        hiddenFilters,
        sortParamName,
        directionParamName,
        perPageParamName,
        viewParamName,
    });

    const handleSearchSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        onFilterSubmit({
            ...collectFormParams(event.currentTarget),
            page: 1,
        });
    };

    const handleFilterSheetSubmit = (
        event: React.FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        onFilterSubmit({
            ...collectFormParams(event.currentTarget),
            page: 1,
        });

        setIsFilterSheetOpen(false);
    };

    const handleFilterChange = (name: string, value: string) => {
        const formElement = document.getElementById(filterFormId) as HTMLFormElement | null;
        const baseParams: Record<string, string | number | null | undefined> = {
            ...sharedParams,
            ...(primarySearchFilter
                ? { [primarySearchFilter.name]: primarySearchFilter.value }
                : {}),
        };

        if (formElement) {
            const formParams = collectFormParams(formElement);

            Object.assign(baseParams, formParams);
        }

        if (name.endsWith('_from') || name.endsWith('_to')) {
            baseParams[name] = value;
        } else {
            const isDateRange = dateRangeFilters.some((f) => f.name === name);

            if (isDateRange && value) {
                const [from = '', to = ''] = value.split(',');

                baseParams[`${name}_from`] = from;
                baseParams[`${name}_to`] = to;
            } else if (isDateRange) {
                baseParams[`${name}_from`] = '';
                baseParams[`${name}_to`] = '';
            } else {
                baseParams[name] = value;
            }
        }

        onFilterSubmit({
            ...baseParams,
            page: 1,
        });
    };

    const handleResetFilters = () => {
        onFilterSubmit({
            ...sharedParams,
            ...(primarySearchFilter
                ? { [primarySearchFilter.name]: primarySearchFilter.value }
                : {}),
            ...Object.fromEntries(
                advancedSearchFilters.map((filter) => [filter.name, '']),
            ),
            ...Object.fromEntries(
                selectFilters.map((filter) => [
                    filter.name,
                    getFilterDefaultValue(),
                ]),
            ),
            ...Object.fromEntries(
                dateRangeFilters.map((filter) => [filter.name, '']),
            ),
            ...Object.fromEntries(
                booleanFilters.map((filter) => [filter.name, '']),
            ),
            ...Object.fromEntries(
                numberFilters.map((filter) => [filter.name, '']),
            ),
            page: 1,
        });

        setIsFilterSheetOpen(false);
    };

    return (
        <div className="flex flex-col gap-3 lg:gap-4">
            <div className="flex min-w-0 flex-col gap-3 xl:flex-row xl:items-start xl:gap-4">
                {tabs ? (
                    <Tabs
                        value={activeTabValue}
                        size="comfortable"
                        className="min-w-0 flex-1"
                        orientation={isMobile ? 'vertical' : 'horizontal'}
                        onValueChange={onTabChange}
                    >
                        <TabsList
                            className={cn(
                                'w-full md:w-fit',
                                !isMobile &&
                                    'min-w-0 justify-start overflow-x-auto pr-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
                            )}
                        >
                            {tabs.items.map((item) => (
                                <TabsTrigger
                                    key={item.value}
                                    value={item.value}
                                    className={cn(!isMobile && 'shrink-0')}
                                >
                                    {item.icon}
                                    <span>{item.label}</span>
                                    {typeof item.count === 'number' ? (
                                        <Badge
                                            variant={
                                                item.countVariant ?? 'secondary'
                                            }
                                            className="rounded-full px-1.5 py-0 text-[0.7rem]"
                                        >
                                            {item.count}
                                        </Badge>
                                    ) : null}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </Tabs>
                ) : (
                    <div />
                )}

                {hasVisibleFilters ? (
                    <div className="flex w-full min-w-0 flex-col gap-3 md:flex-row md:items-center md:justify-end xl:w-auto xl:max-w-[29rem] xl:flex-none">
                        {searchFilters.length > 0 ? (
                            <form
                                onSubmit={handleSearchSubmit}
                                className="flex min-w-0 flex-1 flex-col gap-3 md:flex-row md:items-center md:justify-end"
                            >
                                <DatagridHiddenInputs
                                    params={{
                                        ...sharedParams,
                                        ...Object.fromEntries(
                                            advancedSearchFilters.map(
                                                (filter) => [
                                                    filter.name,
                                                    filter.value,
                                                ],
                                            ),
                                        ),
                                        ...Object.fromEntries(
                                            selectFilters.map((filter) => [
                                                filter.name,
                                                filter.value,
                                            ]),
                                        ),
                                        ...buildDateRangeParams(
                                            dateRangeFilters,
                                        ),
                                        ...Object.fromEntries(
                                            booleanFilters.map((filter) => [
                                                filter.name,
                                                filter.value,
                                            ]),
                                        ),
                                        ...Object.fromEntries(
                                            numberFilters.map((filter) => [
                                                filter.name,
                                                filter.value,
                                            ]),
                                        ),
                                    }}
                                />

                                {primarySearchFilter ? (
                                    <InputGroup
                                        key={primarySearchFilter.name}
                                        size="comfortable"
                                        className={cn(
                                            primarySearchFilter.className,
                                            'w-full min-w-0 md:flex-1 md:basis-0 xl:w-[14.5rem] xl:min-w-[14.5rem] xl:flex-none xl:basis-auto 2xl:w-[15.5rem] 2xl:min-w-[15.5rem]',
                                        )}
                                    >
                                        <InputGroupAddon>
                                            <SearchIcon />
                                        </InputGroupAddon>
                                        <InputGroupInput
                                            ref={searchInputRef}
                                            name={primarySearchFilter.name}
                                            defaultValue={
                                                primarySearchFilter.value
                                            }
                                            placeholder={
                                                primarySearchFilter.placeholder
                                            }
                                            onChange={onSearchChange}
                                        />
                                    </InputGroup>
                                ) : null}
                            </form>
                        ) : null}

                        {sheetFilterCount > 0 ? (
                            <Sheet
                                open={isFilterSheetOpen}
                                onOpenChange={setIsFilterSheetOpen}
                                modal={false}
                            >
                                <div className="relative shrink-0">
                                    <SheetTrigger asChild>
                                        <Button
                                            type="button"
                                            variant={activeSheetFilterCount > 0 ? 'default' : submitButtonVariant}
                                            size={submitButtonSize}
                                        >
                                            <FilterIcon data-icon="inline-start" />
                                            {activeSheetFilterCount > 0
                                                ? `Filtered (${activeSheetFilterCount})`
                                                : submitLabel}
                                        </Button>
                                    </SheetTrigger>
                                    {activeSheetFilterCount > 0 ? (
                                        <button
                                            type="button"
                                            className="absolute -right-2 -top-2 flex size-5 items-center justify-center rounded-full bg-destructive shadow-sm transition-opacity hover:opacity-80"
                                            onClick={handleResetFilters}
                                        >
                                            <XIcon className="size-3 text-white" strokeWidth={3} />
                                        </button>
                                    ) : null}
                                </div>

                                <SheetContent
                                    side="right"
                                    className="w-full gap-0 sm:max-w-md"
                                >
                                    <SheetHeader className="border-b">
                                        <SheetTitle>{submitLabel}</SheetTitle>
                                        <SheetDescription>
                                            Apply the available datagrid
                                            filters.
                                        </SheetDescription>
                                    </SheetHeader>

                                    <DatagridActiveFilters
                                        advancedSearchFilters={advancedSearchFilters}
                                        selectFilters={selectFilters}
                                        dateRangeFilters={dateRangeFilters}
                                        booleanFilters={booleanFilters}
                                        numberFilters={numberFilters}
                                        onRemove={(name, value) => handleFilterChange(name, value)}
                                    />

                                    <form
                                        id={filterFormId}
                                        onSubmit={handleFilterSheetSubmit}
                                        className="flex min-h-0 flex-1 flex-col"
                                    >
                                        <div className="flex-1 space-y-5 overflow-y-auto px-4 py-4">
                                            <DatagridHiddenInputs
                                                params={{
                                                    ...sharedParams,
                                                    ...(primarySearchFilter
                                                        ? {
                                                              [primarySearchFilter.name]:
                                                                  primarySearchFilter.value,
                                                          }
                                                        : {}),
                                                }}
                                            />

                                            {advancedSearchFilters.map(
                                                (filter) => (
                                                    <div
                                                        key={filter.name}
                                                        className="space-y-2"
                                                    >
                                                        <div className="text-sm font-medium text-foreground">
                                                            {filter.placeholder}
                                                        </div>
                                                        <InputGroup size="comfortable">
                                                            <InputGroupAddon>
                                                                <SearchIcon />
                                                            </InputGroupAddon>
                                                            <InputGroupInput
                                                                name={
                                                                    filter.name
                                                                }
                                                                defaultValue={
                                                                    filter.value
                                                                }
                                                                placeholder={
                                                                    filter.placeholder
                                                                }
                                                            />
                                                        </InputGroup>
                                                    </div>
                                                ),
                                            )}

                                            {selectFilters.map((filter) => (
                                                <DatagridSelectFilterField
                                                    key={filter.name}
                                                    filter={filter}
                                                    onChange={handleFilterChange}
                                                />
                                            ))}

                                            {dateRangeFilters.map((filter) => (
                                                <DatagridDateRangeFilterField
                                                    key={filter.name}
                                                    filter={filter}
                                                    onChange={handleFilterChange}
                                                />
                                            ))}

                                            {booleanFilters.map((filter) => (
                                                <DatagridBooleanFilterField
                                                    key={filter.name}
                                                    filter={filter}
                                                    onChange={handleFilterChange}
                                                />
                                            ))}

                                            {numberFilters.map((filter) => (
                                                <DatagridNumberFilterField
                                                    key={filter.name}
                                                    filter={filter}
                                                    onChange={handleFilterChange}
                                                />
                                            ))}
                                        </div>
                                    </form>

                                    <SheetFooter className="border-t sm:flex-row sm:justify-between">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={handleResetFilters}
                                        >
                                            Clear All
                                        </Button>
                                        <Button
                                            type="submit"
                                            form={filterFormId}
                                        >
                                            <FilterIcon data-icon="inline-start" />
                                            Apply Filters
                                        </Button>
                                    </SheetFooter>
                                </SheetContent>
                            </Sheet>
                        ) : null}

                        {view && renderCard ? (
                            <ToggleGroup
                                type="single"
                                value={view.value}
                                variant="outline"
                                size="comfortable"
                                onValueChange={(value) => {
                                    if (
                                        value === 'table' ||
                                        value === 'cards'
                                    ) {
                                        onViewChange(value as DatagridViewMode);
                                    }
                                }}
                            >
                                <ToggleGroupItem
                                    value="table"
                                    aria-label="Table view"
                                >
                                    <Rows3Icon />
                                </ToggleGroupItem>
                                <ToggleGroupItem
                                    value="cards"
                                    aria-label="Cards view"
                                >
                                    <LayoutGridIcon />
                                </ToggleGroupItem>
                            </ToggleGroup>
                        ) : null}
                    </div>
                ) : null}
            </div>
        </div>
    );
}

function DatagridHiddenInputs({
    params,
}: {
    params: Record<string, string | number | null | undefined>;
}) {
    return Object.entries(params).map(([name, value]) => {
        if (value === null || value === undefined) {
            return null;
        }

        return (
            <input key={name} type="hidden" name={name} value={String(value)} />
        );
    });
}

function DatagridActiveFilters({
    advancedSearchFilters,
    selectFilters,
    dateRangeFilters,
    booleanFilters,
    numberFilters,
    onRemove,
}: {
    advancedSearchFilters: DatagridSearchFilter[];
    selectFilters: DatagridSelectFilter[];
    dateRangeFilters: DatagridDateRangeFilter[];
    booleanFilters: DatagridBooleanFilter[];
    numberFilters: DatagridNumberFilter[];
    onRemove: (name: string, value: string) => void;
}) {
    const items: { key: string; label: string; onRemove: () => void }[] = [];

    for (const filter of advancedSearchFilters) {
        if (filter.value !== '') {
            items.push({
                key: filter.name,
                label: `${filter.placeholder}: ${filter.value}`,
                onRemove: () => onRemove(filter.name, ''),
            });
        }
    }

    for (const filter of selectFilters) {
        if (filter.value === '' || filter.value === getFilterDefaultValue()) {
            continue;
        }

        const filterLabel = filter.label ?? filter.placeholder ?? filter.name;
        const selectedValues = filter.value.split(',').filter(Boolean);
        const selectedLabels = selectedValues
            .map((v) => filter.options.find((o) => o.value === v)?.label ?? v)
            .join(', ');

        items.push({
            key: filter.name,
            label: `${filterLabel}: ${selectedLabels}`,
            onRemove: () => onRemove(filter.name, ''),
        });
    }

    for (const filter of dateRangeFilters) {
        if (filter.value === '') {
            continue;
        }

        const filterLabel = filter.label ?? filter.name;
        const [from = '', to = ''] = filter.value.split(',');

        items.push({
            key: filter.name,
            label: `${filterLabel}: ${from} to ${to}`,
            onRemove: () => onRemove(filter.name, ''),
        });
    }

    for (const filter of booleanFilters) {
        if (filter.value === '') {
            continue;
        }

        const filterLabel = filter.label ?? filter.name;
        const valueLabel =
            filter.value === '1'
                ? (filter.trueLabel ?? 'Yes')
                : (filter.falseLabel ?? 'No');

        items.push({
            key: filter.name,
            label: `${filterLabel}: ${valueLabel}`,
            onRemove: () => onRemove(filter.name, ''),
        });
    }

    for (const filter of numberFilters) {
        if (filter.value === '') {
            continue;
        }

        const filterLabel = filter.label ?? filter.placeholder ?? filter.name;

        items.push({
            key: filter.name,
            label: `${filterLabel}: ${filter.value}`,
            onRemove: () => onRemove(filter.name, ''),
        });
    }

    if (items.length === 0) {
        return null;
    }

    return (
        <div className="border-b px-4 py-3">
            <p className="mb-2 text-xs font-medium text-muted-foreground">
                Active Filters:
            </p>
            <div className="flex flex-wrap gap-1.5">
                {items.map((item) => (
                    <Badge
                        key={item.key}
                        variant="secondary"
                        className="gap-1 pr-1"
                    >
                        {item.label}
                        <button
                            type="button"
                            className="ml-0.5 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                            onClick={item.onRemove}
                        >
                            <XIcon className="size-3" />
                        </button>
                    </Badge>
                ))}
            </div>
        </div>
    );
}

function buildDateRangeParams(
    filters: DatagridDateRangeFilter[],
): Record<string, string> {
    return Object.fromEntries(
        filters.flatMap((filter) => {
            const [from = '', to = ''] = filter.value.split(',');

            return [
                [`${filter.name}_from`, from],
                [`${filter.name}_to`, to],
            ];
        }),
    );
}

function buildSharedParams({
    tabs,
    activeTabValue,
    sorting,
    perPage,
    view,
    hiddenFilters,
    sortParamName,
    directionParamName,
    perPageParamName,
    viewParamName,
}: {
    tabs?: {
        name: string;
        items: DatagridTab[];
    };
    activeTabValue: string;
    sorting?: DatagridProps<unknown>['sorting'];
    perPage?: DatagridProps<unknown>['perPage'];
    view?: DatagridProps<unknown>['view'];
    hiddenFilters: DatagridHiddenFilter[];
    sortParamName: string;
    directionParamName: string;
    perPageParamName: string;
    viewParamName: string;
}): Record<string, string | number | null | undefined> {
    return {
        ...(tabs && activeTabValue !== ''
            ? { [tabs.name]: activeTabValue }
            : {}),
        ...(sorting
            ? {
                  [sortParamName]: sorting.sort,
                  [directionParamName]: sorting.direction,
              }
            : {}),
        ...(perPage ? { [perPageParamName]: perPage.value } : {}),
        ...(view ? { [viewParamName]: view.value } : {}),
        ...Object.fromEntries(
            hiddenFilters.map((filter) => [filter.name, filter.value]),
        ),
    };
}

function getFilterDefaultValue(): string {
    return '';
}
