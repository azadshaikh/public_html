import {
    FilterIcon,
    LayoutGridIcon,
    Rows3Icon,
    SearchIcon,
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
            (filter) => filter.value !== getFilterDefaultValue(filter),
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
                    getFilterDefaultValue(filter),
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
                                'w-full',
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
                            >
                                <SheetTrigger asChild>
                                    <Button
                                        type="button"
                                        variant={submitButtonVariant}
                                        size={submitButtonSize}
                                        className="shrink-0"
                                    >
                                        <FilterIcon data-icon="inline-start" />
                                        {submitLabel}
                                        {activeSheetFilterCount > 0 ? (
                                            <Badge
                                                variant="secondary"
                                                className="rounded-full px-1.5 py-0 text-[0.7rem]"
                                            >
                                                {activeSheetFilterCount}
                                            </Badge>
                                        ) : null}
                                    </Button>
                                </SheetTrigger>

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
                                                />
                                            ))}

                                            {dateRangeFilters.map((filter) => (
                                                <DatagridDateRangeFilterField
                                                    key={filter.name}
                                                    filter={filter}
                                                />
                                            ))}

                                            {booleanFilters.map((filter) => (
                                                <DatagridBooleanFilterField
                                                    key={filter.name}
                                                    filter={filter}
                                                />
                                            ))}

                                            {numberFilters.map((filter) => (
                                                <DatagridNumberFilterField
                                                    key={filter.name}
                                                    filter={filter}
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
                                            Reset filters
                                        </Button>
                                        <Button
                                            type="submit"
                                            form={filterFormId}
                                            variant={submitButtonVariant}
                                            size={submitButtonSize}
                                        >
                                            Apply filters
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

function getFilterDefaultValue(filter: DatagridSelectFilter): string {
    return filter.options[0]?.value ?? '';
}
