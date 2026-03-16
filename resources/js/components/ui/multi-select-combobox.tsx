'use client';

import { useMemo } from 'react';
import {
    Combobox,
    ComboboxChip,
    ComboboxChips,
    ComboboxChipsInput,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxItem,
    ComboboxList,
    ComboboxValue,
    useComboboxAnchor,
} from '@/components/ui/combobox';
import { cn } from '@/lib/utils';

type MultiSelectComboboxValue = string | number;
type MultiSelectComboboxSize = 'sm' | 'default' | 'comfortable';

const sizeClasses: Record<
    MultiSelectComboboxSize,
    {
        chips: string;
        chip: string;
        input: string;
        removeButtonSize: 'icon-xs' | 'icon-sm';
    }
> = {
    sm: {
        chips: 'min-h-7 gap-1 px-2.5 py-1',
        chip: 'h-5 px-1.5 text-xs',
        input: 'min-w-16 flex-1 basis-24 text-xs leading-4',
        removeButtonSize: 'icon-xs',
    },
    default: {
        chips: 'min-h-8 gap-1 px-2.5 py-1',
        chip: 'h-6 px-1.5 text-xs',
        input: 'min-w-16 flex-1 basis-24 text-sm leading-5',
        removeButtonSize: 'icon-xs',
    },
    comfortable: {
        chips: 'min-h-9 gap-1.5 px-3 py-1.5',
        chip: 'h-6 rounded-md px-2 text-xs',
        input: 'min-w-16 flex-1 basis-28 text-sm leading-5',
        removeButtonSize: 'icon-xs',
    },
};

export type MultiSelectComboboxOption<
    T extends MultiSelectComboboxValue = MultiSelectComboboxValue,
> = {
    value: T;
    label: string;
    disabled?: boolean;
    description?: string;
};

type MultiSelectComboboxProps<
    T extends MultiSelectComboboxValue = MultiSelectComboboxValue,
> = {
    id?: string;
    value: T[];
    options: MultiSelectComboboxOption<T>[];
    onValueChange: (value: T[]) => void;
    onBlur?: () => void;
    placeholder?: string;
    emptyMessage?: string;
    disabled?: boolean;
    size?: MultiSelectComboboxSize;
    'aria-invalid'?: boolean;
};

export function MultiSelectCombobox<
    T extends MultiSelectComboboxValue = MultiSelectComboboxValue,
>({
    id,
    value,
    options,
    onValueChange,
    onBlur,
    placeholder = 'Select options',
    emptyMessage = 'No results found.',
    disabled = false,
    size = 'comfortable',
    'aria-invalid': ariaInvalid,
}: MultiSelectComboboxProps<T>) {
    const anchor = useComboboxAnchor();
    const selectedOptions = useMemo(() => {
        const selectedValueSet = new Set(value.map((item) => String(item)));

        return options.filter((option) => selectedValueSet.has(String(option.value)));
    }, [options, value]);
    const resolvedSizeClasses = sizeClasses[size];

    return (
        <Combobox
            items={options}
            multiple
            autoHighlight
            disabled={disabled}
            value={selectedOptions}
            itemToStringLabel={(item) => item?.label ?? ''}
            itemToStringValue={(item) =>
                item
                    ? [item.label, String(item.value), item.description ?? '']
                          .join(' ')
                          .trim()
                    : ''
            }
            onValueChange={(items) => {
                onValueChange(items.map((item) => item.value));
            }}
        >
            <ComboboxChips
                ref={anchor}
                className={cn(resolvedSizeClasses.chips)}
            >
                <ComboboxValue>
                    {(items: MultiSelectComboboxOption<T>[]) => (
                        <>
                            {items.map((item) => (
                                <ComboboxChip
                                    key={String(item.value)}
                                    className={cn(resolvedSizeClasses.chip)}
                                    removeButtonSize={
                                        resolvedSizeClasses.removeButtonSize
                                    }
                                >
                                    {item.label}
                                </ComboboxChip>
                            ))}
                            <ComboboxChipsInput
                                id={id}
                                aria-invalid={ariaInvalid}
                                disabled={disabled}
                                onBlur={onBlur}
                                className={cn(
                                    'placeholder:text-muted-foreground',
                                    resolvedSizeClasses.input,
                                )}
                                placeholder={items.length === 0 ? placeholder : undefined}
                            />
                        </>
                    )}
                </ComboboxValue>
            </ComboboxChips>
            <ComboboxContent anchor={anchor}>
                <ComboboxEmpty>{emptyMessage}</ComboboxEmpty>
                <ComboboxList>
                    {(option: MultiSelectComboboxOption<T>) => (
                        <ComboboxItem
                            key={`${id ?? 'multi-select-combobox'}-${String(option.value)}`}
                            value={option}
                            disabled={option.disabled}
                        >
                            <div className="flex flex-col gap-0.5">
                                <span>{option.label}</span>
                                {option.description ? (
                                    <span className="text-xs text-muted-foreground">
                                        {option.description}
                                    </span>
                                ) : null}
                            </div>
                        </ComboboxItem>
                    )}
                </ComboboxList>
            </ComboboxContent>
        </Combobox>
    );
}
