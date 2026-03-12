import { CalendarIcon, XIcon } from 'lucide-react';
import * as React from 'react';
import type {
    DatagridBooleanFilter,
    DatagridDateRangeFilter,
    DatagridNumberFilter,
    DatagridSelectFilter,
} from '@/components/datagrid/types';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

function formatFilterLabel(name: string): string {
    return name
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

// =========================================================================
// SELECT FILTER
// =========================================================================

export function DatagridSelectFilterField({
    filter,
}: {
    filter: DatagridSelectFilter;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={`datagrid-filter-${filter.name}`}>
                {formatFilterLabel(filter.name)}
            </Label>
            <NativeSelect
                id={`datagrid-filter-${filter.name}`}
                size="comfortable"
                name={filter.name}
                defaultValue={filter.value}
                className={cn('w-full', filter.className)}
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
        </div>
    );
}

// =========================================================================
// DATE RANGE FILTER
// =========================================================================

function parseDateRangeValue(value: string): { from?: Date; to?: Date } {
    if (!value) return {};

    const [fromStr, toStr] = value.split(',');
    const from = fromStr ? new Date(fromStr) : undefined;
    const to = toStr ? new Date(toStr) : undefined;

    return {
        from: from && !isNaN(from.getTime()) ? from : undefined,
        to: to && !isNaN(to.getTime()) ? to : undefined,
    };
}

function formatDate(date: Date): string {
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function toISODateString(date: Date): string {
    return date.toISOString().split('T')[0];
}

export function DatagridDateRangeFilterField({
    filter,
}: {
    filter: DatagridDateRangeFilter;
}) {
    const [range, setRange] = React.useState(() =>
        parseDateRangeValue(filter.value),
    );
    const [isOpen, setIsOpen] = React.useState(false);

    const hiddenValue = React.useMemo(() => {
        if (!range.from) return '';
        const fromStr = toISODateString(range.from);
        const toStr = range.to ? toISODateString(range.to) : '';
        return toStr ? `${fromStr},${toStr}` : fromStr;
    }, [range]);

    const displayText = React.useMemo(() => {
        if (!range.from) return '';
        if (!range.to) return formatDate(range.from);
        return `${formatDate(range.from)} – ${formatDate(range.to)}`;
    }, [range]);

    const handleClear = (event: React.MouseEvent) => {
        event.stopPropagation();
        setRange({});
    };

    return (
        <div className="space-y-2">
            <Label>{filter.label ?? formatFilterLabel(filter.name)}</Label>
            <input type="hidden" name={filter.name} value={hiddenValue} />
            <Popover open={isOpen} onOpenChange={setIsOpen}>
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        size="comfortable"
                        className={cn(
                            'w-full justify-start text-left font-normal',
                            !range.from && 'text-muted-foreground',
                            filter.className,
                        )}
                    >
                        <CalendarIcon data-icon="inline-start" />
                        <span className="flex-1 truncate">
                            {displayText || 'Pick a date range'}
                        </span>
                        {range.from ? (
                            <XIcon
                                className="size-3.5 shrink-0 text-muted-foreground hover:text-foreground"
                                onClick={handleClear}
                            />
                        ) : null}
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                        mode="range"
                        selected={
                            range.from
                                ? { from: range.from, to: range.to }
                                : undefined
                        }
                        onSelect={(selected) => {
                            setRange({
                                from: selected?.from,
                                to: selected?.to,
                            });
                        }}
                        numberOfMonths={2}
                    />
                </PopoverContent>
            </Popover>
        </div>
    );
}

// =========================================================================
// BOOLEAN FILTER
// =========================================================================

export function DatagridBooleanFilterField({
    filter,
}: {
    filter: DatagridBooleanFilter;
}) {
    const trueLabel = filter.trueLabel ?? 'Yes';
    const falseLabel = filter.falseLabel ?? 'No';

    return (
        <div className="space-y-2">
            <Label htmlFor={`datagrid-filter-${filter.name}`}>
                {filter.label ?? formatFilterLabel(filter.name)}
            </Label>
            <NativeSelect
                id={`datagrid-filter-${filter.name}`}
                size="comfortable"
                name={filter.name}
                defaultValue={filter.value}
                className={cn('w-full', filter.className)}
            >
                <NativeSelectOption value="">All</NativeSelectOption>
                <NativeSelectOption value="1">{trueLabel}</NativeSelectOption>
                <NativeSelectOption value="0">{falseLabel}</NativeSelectOption>
            </NativeSelect>
        </div>
    );
}

// =========================================================================
// NUMBER FILTER
// =========================================================================

export function DatagridNumberFilterField({
    filter,
}: {
    filter: DatagridNumberFilter;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={`datagrid-filter-${filter.name}`}>
                {filter.label ?? formatFilterLabel(filter.name)}
            </Label>
            <Input
                id={`datagrid-filter-${filter.name}`}
                type="number"
                name={filter.name}
                defaultValue={filter.value}
                min={filter.min}
                max={filter.max}
                step={filter.step}
                placeholder={filter.placeholder ?? 'Enter a number...'}
                className={cn('w-full', filter.className)}
            />
        </div>
    );
}
