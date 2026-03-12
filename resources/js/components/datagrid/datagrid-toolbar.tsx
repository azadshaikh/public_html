import { Form } from '@inertiajs/react';
import {
    FilterIcon,
    LayoutGridIcon,
    Rows3Icon,
    SearchIcon,
} from 'lucide-react';
import * as React from 'react';
import type {
    DatagridFilter,
    DatagridProps,
    DatagridTab,
    DatagridViewMode,
} from '@/components/datagrid/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';

type DatagridToolbarProps = {
    action: string;
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
    onViewChange: (value: string) => void;
};

export function DatagridToolbar({
    action,
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
    onViewChange,
}: DatagridToolbarProps) {
    if (!tabs && !hasVisibleFilters) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 lg:gap-4">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                {tabs ? (
                    <Tabs value={activeTabValue} onValueChange={onTabChange}>
                        <TabsList className="h-auto flex-wrap">
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
                    <Form
                        action={action}
                        method="get"
                        options={{ preserveScroll: true }}
                        className="flex w-full flex-col gap-3 lg:w-auto lg:max-w-[42rem] lg:min-w-[24rem] lg:flex-row lg:items-center lg:justify-end"
                    >
                        {tabs && activeTabValue !== '' ? (
                            <input
                                type="hidden"
                                name={tabs.name}
                                value={activeTabValue}
                            />
                        ) : null}

                        {sorting ? (
                            <>
                                <input
                                    type="hidden"
                                    name={sortParamName}
                                    value={sorting.sort}
                                />
                                <input
                                    type="hidden"
                                    name={directionParamName}
                                    value={sorting.direction}
                                />
                            </>
                        ) : null}

                        {perPage ? (
                            <input
                                type="hidden"
                                name={perPageParamName}
                                value={String(perPage.value)}
                            />
                        ) : null}

                        {view ? (
                            <input
                                type="hidden"
                                name={viewParamName}
                                value={view.value}
                            />
                        ) : null}

                        {filters.map((filter) => {
                            if (filter.type === 'hidden') {
                                return (
                                    <input
                                        key={filter.name}
                                        type="hidden"
                                        name={filter.name}
                                        value={filter.value}
                                    />
                                );
                            }

                            if (filter.type === 'search') {
                                return (
                                    <InputGroup
                                        key={filter.name}
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
                                );
                            }

                            return (
                                <NativeSelect
                                    key={filter.name}
                                    name={filter.name}
                                    defaultValue={filter.value}
                                    className={cn(
                                        'w-full lg:min-w-44',
                                        filter.className,
                                    )}
                                >
                                    {filter.options.map((option) => (
                                        <NativeSelectOption
                                            key={`${filter.name}-${option.value}`}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                            );
                        })}

                        <Button
                            type="submit"
                            variant={submitButtonVariant}
                            size={submitButtonSize}
                            className="shrink-0"
                        >
                            <FilterIcon data-icon="inline-start" />
                            {submitLabel}
                        </Button>

                        {view && renderCard ? (
                            <ToggleGroup
                                type="single"
                                value={view.value}
                                variant="outline"
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
                    </Form>
                ) : null}
            </div>
        </div>
    );
}
