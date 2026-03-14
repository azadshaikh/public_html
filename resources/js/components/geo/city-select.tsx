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

type CitySelectProps = {
    countryCode: string;
    stateCode: string;
    value: string;
    onChange: (code: string, name: string) => void;
    disabled?: boolean;
    placeholder?: string;
    className?: string;
    'aria-invalid'?: boolean;
};

export function CitySelect({
    countryCode,
    stateCode,
    value,
    onChange,
    disabled = false,
    placeholder = 'Select city...',
    className,
    'aria-invalid': ariaInvalid,
}: CitySelectProps) {
    const [items, setItems] = useState<GeoOption[]>([]);
    const [loadedKey, setLoadedKey] = useState('');
    const hasLocationContext = Boolean(stateCode || countryCode);
    const requestKey = useMemo(() => {
        if (!hasLocationContext) {
            return '';
        }

        return `${countryCode}::${stateCode}`;
    }, [countryCode, hasLocationContext, stateCode]);

    useEffect(() => {
        if (!requestKey) {
            return;
        }

        let cancelled = false;

        const params = new URLSearchParams();

        if (stateCode) {
            params.set('state_code', stateCode);
        }

        if (countryCode) {
            params.set('country_code', countryCode);
        }

        const url = `${route('app.ajax.geo.cities')}?${params.toString()}`;

        fetch(url)
            .then((res) => res.json())
            .then((data: { items: GeoOption[] }) => {
                if (cancelled) {
                    return;
                }

                setItems(data.items ?? []);
                setLoadedKey(requestKey);
            })
            .catch(() => {
                if (cancelled) {
                    return;
                }

                setItems([]);
                setLoadedKey(requestKey);
            });

        return () => {
            cancelled = true;
        };
    }, [countryCode, requestKey, stateCode]);

    const loading = Boolean(requestKey) && loadedKey !== requestKey;
    const availableItems = useMemo(() => {
        if (!requestKey || loadedKey !== requestKey) {
            return [];
        }

        return items;
    }, [items, loadedKey, requestKey]);

    const selectedItem = useMemo(
        () =>
            availableItems.find(
                (item) => item.value === value || item.label === value,
            ) ?? null,
        [availableItems, value],
    );

    const isDisabled = disabled || loading || !hasLocationContext;

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
                    !hasLocationContext
                        ? 'Select a country first'
                        : loading
                          ? 'Loading cities...'
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
                <ComboboxEmpty>No cities found.</ComboboxEmpty>
            </ComboboxContent>
        </Combobox>
    );
}
