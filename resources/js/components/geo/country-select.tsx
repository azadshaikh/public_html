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

type CountrySelectProps = {
    value: string;
    onChange: (code: string, name: string) => void;
    disabled?: boolean;
    placeholder?: string;
    className?: string;
    'aria-invalid'?: boolean;
};

export function CountrySelect({
    value,
    onChange,
    disabled = false,
    placeholder = 'Select country...',
    className,
    'aria-invalid': ariaInvalid,
}: CountrySelectProps) {
    const [items, setItems] = useState<GeoOption[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (items.length > 0) {
            return;
        }

        setLoading(true);

        fetch(route('app.ajax.geo.countries'))
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
        // Only run once on mount
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const selectedItem = useMemo(
        () => items.find((item) => item.value === value) ?? null,
        [items, value],
    );

    return (
        <Combobox
            items={items}
            itemToStringLabel={(item) => item?.label ?? ''}
            value={selectedItem}
            autoHighlight
            onValueChange={(item) => {
                onChange(item?.value ?? '', item?.label ?? '');
            }}
            disabled={disabled || loading}
        >
            <ComboboxInput
                className={className}
                placeholder={loading ? 'Loading countries...' : placeholder}
                showTrigger
                showClear
                disabled={disabled || loading}
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
                <ComboboxEmpty>No countries found.</ComboboxEmpty>
            </ComboboxContent>
        </Combobox>
    );
}
