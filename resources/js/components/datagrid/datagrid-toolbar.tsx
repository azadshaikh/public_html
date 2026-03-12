import {
    FilterIcon,
    LayoutGridIcon,
    Rows3Icon,
    SearchIcon,
} from 'lucide-react';
import * as React from 'react';
import type {
    DatagridFilter,
    DatagridHiddenFilter,
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
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
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
    const selectFilters = filters.filter(
        (filter): filter is DatagridSelectFilter => filter.type === 'select',
    );
    const hiddenFilters = filters.filter(
        (filter): filter is DatagridHiddenFilter => filter.type === 'hidden',
    );
    const activeSelectFilterCount = selectFilters.filter(
        (filter) => filter.value !== getFilterDefaultValue(filter),
    ).length;

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
            ...Object.fromEntries(
                searchFilters.map((filter) => [filter.name, filter.value]),
            ),
            ...Object.fromEntries(
                selectFilters.map((filter) => [
                    filter.name,
                    getFilterDefaultValue(filter),
                ]),
            ),
            page: 1,
        });

        setIsFilterSheetOpen(false);
    };

    return (
        <div className="flex flex-col gap-3 lg:gap-4">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                {tabs ? (
                    <Tabs
                        value={activeTabValue}
                        size="comfortable"
                        className="w-full md:w-auto"
                        orientation={isMobile ? 'vertical' : 'horizontal'}
                        onValueChange={onTabChange}
                    >
                        <TabsList>
                            {tabs.items.map((item) => (
                                <TabsTrigger
                                    key={item.value}
                                    value={item.value}
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
                    <div className="flex w-full flex-col gap-3 lg:w-auto lg:max-w-[42rem] lg:min-w-[24rem] lg:flex-row lg:items-center lg:justify-end">
                        {searchFilters.length > 0 ? (
                            <form
                                onSubmit={handleSearchSubmit}
                                className="flex w-full flex-col gap-3 lg:min-w-[21rem] lg:flex-row lg:items-center"
                            >
                                <DatagridHiddenInputs
                                    params={{
                                        ...sharedParams,
                                        ...Object.fromEntries(
                                            selectFilters.map((filter) => [
                                                filter.name,
                                                filter.value,
                                            ]),
                                        ),
                                    }}
                                />

                                {searchFilters.map((filter) => (
                                    <InputGroup
                                        key={filter.name}
                                        size="comfortable"
                                        className={cn(
                                            'w-full lg:min-w-[21rem]',
                                            filter.className,
                                        )}
                                    >
                                        <InputGroupAddon>
                                            <SearchIcon />
                                        </InputGroupAddon>
                                        <InputGroupInput
                                            ref={searchInputRef}
                                            name={filter.name}
                                            defaultValue={filter.value}
                                            placeholder={filter.placeholder}
                                            onChange={onSearchChange}
                                        />
                                    </InputGroup>
                                ))}
                            </form>
                        ) : null}

                        {selectFilters.length > 0 ? (
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
                                        {activeSelectFilterCount > 0 ? (
                                            <Badge
                                                variant="secondary"
                                                className="rounded-full px-1.5 py-0 text-[0.7rem]"
                                            >
                                                {activeSelectFilterCount}
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
                                                    ...Object.fromEntries(
                                                        searchFilters.map(
                                                            (filter) => [
                                                                filter.name,
                                                                filter.value,
                                                            ],
                                                        ),
                                                    ),
                                                }}
                                            />

                                            {selectFilters.map((filter) => (
                                                <div
                                                    key={filter.name}
                                                    className="space-y-2"
                                                >
                                                    <Label
                                                        htmlFor={`datagrid-filter-${filter.name}`}
                                                    >
                                                        {formatFilterLabel(
                                                            filter.name,
                                                        )}
                                                    </Label>
                                                    <NativeSelect
                                                        id={`datagrid-filter-${filter.name}`}
                                                        size="comfortable"
                                                        name={filter.name}
                                                        defaultValue={
                                                            filter.value
                                                        }
                                                        className={cn(
                                                            'w-full',
                                                            filter.className,
                                                        )}
                                                    >
                                                        {filter.options.map(
                                                            (option) => (
                                                                <NativeSelectOption
                                                                    key={`${filter.name}-${option.value}`}
                                                                    value={
                                                                        option.value
                                                                    }
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </NativeSelectOption>
                                                            ),
                                                        )}
                                                    </NativeSelect>
                                                </div>
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

function formatFilterLabel(name: string): string {
    return name
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
