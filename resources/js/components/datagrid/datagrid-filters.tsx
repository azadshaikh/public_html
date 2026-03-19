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
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MultiSelectCombobox } from '@/components/ui/multi-select-combobox';
import type { MultiSelectComboboxOption } from '@/components/ui/multi-select-combobox';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

type DatagridComboboxOption = {
    value: string;
    label: string;
};

function formatFilterLabel(name: string): string {
    return name
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function parseSelectValues(value: string): string[] {
    return value
        .split(',')
        .map((entry) => entry.trim())
        .filter((entry) => entry !== '');
}

function resolveSelectedOption(
    options: DatagridComboboxOption[],
    value: string,
): DatagridComboboxOption | null {
    return options.find((option) => option.value === value) ?? null;
}

function DatagridComboboxField({
    id,
    name,
    label,
    value,
    options,
    placeholder,
    className,
    onChange,
}: {
    id: string;
    name: string;
    label: string;
    value: string;
    options: DatagridComboboxOption[];
    placeholder: string;
    className?: string;
    onChange?: (value: string) => void;
}) {
    const [selectedOption, setSelectedOption] =
        React.useState<DatagridComboboxOption | null>(() =>
            resolveSelectedOption(options, value),
        );

    React.useEffect(() => {
        setSelectedOption(resolveSelectedOption(options, value));
    }, [options, value]);

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <div className={cn('w-full', className)}>
                <Combobox
                    items={options}
                    value={selectedOption}
                    autoHighlight
                    itemToStringLabel={(option) => option?.label ?? ''}
                    itemToStringValue={(option) => option?.value ?? ''}
                    onValueChange={(option) => {
                        setSelectedOption(option ?? null);
                        onChange?.(option?.value ?? '');
                    }}
                >
                    <ComboboxInput
                        id={id}
                        placeholder={placeholder}
                        className="w-full"
                        showClear={selectedOption !== null}
                    />
                    <ComboboxContent>
                        <ComboboxEmpty>No results found.</ComboboxEmpty>
                        <ComboboxList>
                            {(option: DatagridComboboxOption) => (
                                <ComboboxItem
                                    key={`${name}-${option.value}`}
                                    value={option}
                                >
                                    {option.label}
                                </ComboboxItem>
                            )}
                        </ComboboxList>
                    </ComboboxContent>
                </Combobox>
            </div>
            <input
                type="hidden"
                name={name}
                value={selectedOption?.value ?? ''}
            />
        </div>
    );
}

// =========================================================================
// SELECT FILTER
// =========================================================================

export function DatagridSelectFilterField({
    filter,
    onChange,
}: {
    filter: DatagridSelectFilter;
    onChange?: (name: string, value: string) => void;
}) {
    const [selectedValues, setSelectedValues] = React.useState<string[]>(() =>
        parseSelectValues(filter.value),
    );

    React.useEffect(() => {
        setSelectedValues(parseSelectValues(filter.value));
    }, [filter.value]);

    if (filter.multiple) {
        const options: MultiSelectComboboxOption<string>[] = filter.options.map(
            (option) => ({
                value: option.value,
                label: option.label,
            }),
        );

        return (
            <div className="space-y-2">
                <Label htmlFor={`datagrid-filter-${filter.name}`}>
                    {filter.label ?? formatFilterLabel(filter.name)}
                </Label>
                <div className={cn('w-full', filter.className)}>
                    <MultiSelectCombobox
                        id={`datagrid-filter-${filter.name}`}
                        value={selectedValues}
                        options={options}
                        onValueChange={(next) => {
                            setSelectedValues(next);
                            onChange?.(filter.name, next.join(','));
                        }}
                        placeholder={filter.placeholder ?? 'Select options'}
                    />
                </div>
                {selectedValues.map((value) => (
                    <input
                        key={`${filter.name}-${value}`}
                        type="hidden"
                        name={filter.name}
                        value={value}
                    />
                ))}
            </div>
        );
    }

    return (
        <DatagridComboboxField
            id={`datagrid-filter-${filter.name}`}
            name={filter.name}
            label={filter.label ?? formatFilterLabel(filter.name)}
            value={filter.value}
            options={filter.options}
            placeholder={filter.placeholder ?? 'All'}
            className={filter.className}
            onChange={(next) => onChange?.(filter.name, next)}
        />
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
    onChange,
}: {
    filter: DatagridDateRangeFilter;
    onChange?: (name: string, value: string) => void;
}) {
    const [range, setRange] = React.useState(() =>
        parseDateRangeValue(filter.value),
    );
    const [isOpen, setIsOpen] = React.useState(false);

    const displayText = React.useMemo(() => {
        if (!range.from) return '';
        if (!range.to) return formatDate(range.from);
        return `${formatDate(range.from)} – ${formatDate(range.to)}`;
    }, [range]);

    const handleClear = (event: React.MouseEvent) => {
        event.stopPropagation();
        event.preventDefault();
        setRange({});
        onChange?.(filter.name, '');
    };

    const handleOpenChange = (open: boolean) => {
        setIsOpen(open);

        if (!open && range.from && range.to) {
            onChange?.(
                filter.name,
                `${toISODateString(range.from)},${toISODateString(range.to)}`,
            );
        }
    };

    return (
        <div className="space-y-2">
            <Label>{filter.label ?? formatFilterLabel(filter.name)}</Label>
            <input
                type="hidden"
                name={`${filter.name}_from`}
                value={range.from ? toISODateString(range.from) : ''}
            />
            <input
                type="hidden"
                name={`${filter.name}_to`}
                value={range.to ? toISODateString(range.to) : ''}
            />
            <Popover open={isOpen} onOpenChange={handleOpenChange}>
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
    onChange,
}: {
    filter: DatagridBooleanFilter;
    onChange?: (name: string, value: string) => void;
}) {
    const trueLabel = filter.trueLabel ?? 'Yes';
    const falseLabel = filter.falseLabel ?? 'No';

    return (
        <DatagridComboboxField
            id={`datagrid-filter-${filter.name}`}
            name={filter.name}
            label={filter.label ?? formatFilterLabel(filter.name)}
            value={filter.value}
            options={[
                { value: '1', label: trueLabel },
                { value: '0', label: falseLabel },
            ]}
            placeholder="All"
            className={filter.className}
            onChange={(next) => onChange?.(filter.name, next)}
        />
    );
}

// =========================================================================
// NUMBER FILTER
// =========================================================================

export function DatagridNumberFilterField({
    filter,
    onChange,
}: {
    filter: DatagridNumberFilter;
    onChange?: (name: string, value: string) => void;
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
                onChange={(e) => onChange?.(filter.name, e.target.value)}
            />
        </div>
    );
}
