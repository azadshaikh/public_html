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
    const [loadedCountryCode, setLoadedCountryCode] = useState('');

    useEffect(() => {
        if (!countryCode) {
            return;
        }

        let cancelled = false;
        const controller = new AbortController();
        const url =
            route('app.ajax.geo.states') +
            `?country_code=${encodeURIComponent(countryCode)}`;

        void fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('Failed to load states.');
                }

                const payload = (await response.json()) as {
                    items?: GeoOption[];
                };

                if (cancelled) {
                    return;
                }

                setItems(payload.items ?? []);
                setLoadedCountryCode(countryCode);
            })
            .catch(() => {
                if (cancelled || controller.signal.aborted) {
                    return;
                }

                setItems([]);
                setLoadedCountryCode(countryCode);
            });

        return () => {
            cancelled = true;
            controller.abort();
        };
    }, [countryCode]);

    const availableItems = useMemo(
        () => (countryCode && loadedCountryCode === countryCode ? items : []),
        [countryCode, items, loadedCountryCode],
    );

    const selectedItem = useMemo(
        () =>
            availableItems.find(
                (item) =>
                    item.value === value || item.value.endsWith(`-${value}`),
            ) ?? null,
        [availableItems, value],
    );

    const loading = Boolean(countryCode) && loadedCountryCode !== countryCode;
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
