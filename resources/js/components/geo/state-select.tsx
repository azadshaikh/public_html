import { useHttp } from '@inertiajs/react';
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
    const statesRequest = useHttp<Record<string, never>, { items?: GeoOption[] }>(
        {},
    );
    const loading = statesRequest.processing;

    useEffect(() => {
        if (!countryCode) {
            return;
        }

        const url =
            route('app.ajax.geo.states') +
            `?country_code=${encodeURIComponent(countryCode)}`;

        void statesRequest
            .get(url)
            .then((payload) => {
                setItems(payload.items ?? []);
            })
            .catch(() => {
                setItems([]);
            });

        return () => {
            statesRequest.cancel();
        };
    }, [countryCode, statesRequest]);

    const availableItems = useMemo(
        () => (countryCode ? items : []),
        [countryCode, items],
    );

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
