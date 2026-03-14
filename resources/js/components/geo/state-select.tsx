import { useEffect, useMemo, useState } from 'react';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';

type GeoOption = { value: string; label: string };

type StateSelectProps = {
    countryCode: string;
    value: string;
    onChange: (code: string, name: string) => void;
    disabled?: boolean;
    placeholder?: string;
    className?: string;
    'aria-invalid'?: boolean;
};

export function StateSelect({
    countryCode,
    value,
    onChange,
    disabled = false,
    placeholder = 'Select state...',
    className,
    'aria-invalid': ariaInvalid,
}: StateSelectProps) {
    const [items, setItems] = useState<GeoOption[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!countryCode) {
            return;
        }

        setLoading(true);

        const url =
            route('app.ajax.geo.states') +
            `?country_code=${encodeURIComponent(countryCode)}`;

        fetch(url)
            .then((res) => res.json())
            .then((data: { items: GeoOption[] }) => {
                setItems(data.items ?? []);
            })
            .catch(() => {
                setItems([]);
            })
            .finally(() => {
                setLoading(false);
            });
    }, [countryCode]);

    const availableItems = countryCode ? items : [];

    const selectedItem = useMemo(
        () =>
            availableItems.find(
                (item) =>
                    item.value === value || item.value.endsWith(`-${value}`),
            ) ?? null,
        [availableItems, value],
    );

    const isDisabled = disabled || loading || !countryCode;

    return (
        <Combobox
            items={availableItems}
            itemToStringLabel={(item) => item?.label ?? ''}
            value={selectedItem}
            autoHighlight
            onValueChange={(item) => {
                onChange(item?.value ?? '', item?.label ?? '');
            }}
            disabled={isDisabled}
        >
            <ComboboxInput
                className={className}
                placeholder={
                    !countryCode
                        ? 'Select a country first'
                        : loading
                          ? 'Loading states...'
                          : placeholder
                }
                showTrigger
                showClear
                disabled={isDisabled}
                aria-invalid={ariaInvalid}
            />
            <ComboboxContent>
                <ComboboxList>
                    {(item: GeoOption) => (
                        <ComboboxItem key={item.value} value={item}>
                            {item.label}
                        </ComboboxItem>
                    )}
                </ComboboxList>
                <ComboboxEmpty>No states found.</ComboboxEmpty>
            </ComboboxContent>
        </Combobox>
    );
}
