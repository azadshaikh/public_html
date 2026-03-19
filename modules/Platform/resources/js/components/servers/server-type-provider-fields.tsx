import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import type { PlatformOption, ServerFormValues } from '../../types/platform';

type ServerTypeProviderFieldsFormHandle = {
    data: ServerFormValues;
    setField: <K extends keyof ServerFormValues>(key: K, value: ServerFormValues[K]) => void;
    invalid: (key: keyof ServerFormValues) => boolean;
    error: (key: keyof ServerFormValues) => string | undefined;
};

type ServerTypeProviderFieldsProps = {
    form: ServerTypeProviderFieldsFormHandle;
    typeOptions: PlatformOption[];
    providerOptions: PlatformOption[];
};

export function ServerTypeProviderFields({
    form,
    typeOptions,
    providerOptions,
}: ServerTypeProviderFieldsProps) {
    return (
        <>
            <Field data-invalid={form.invalid('type') || undefined}>
                <FieldLabel htmlFor="server-type">Server type</FieldLabel>
                <NativeSelect
                    id="server-type"
                    name="type"
                    size="comfortable"
                    value={form.data.type || ''}
                    onChange={(event) => form.setField('type', event.target.value)}
                    aria-invalid={form.invalid('type') || undefined}
                    className="w-full"
                >
                    <NativeSelectOption value="">Select server type</NativeSelectOption>
                    {typeOptions.map((option) => (
                        <NativeSelectOption
                            key={String(option.value)}
                            value={String(option.value)}
                        >
                            {option.label}
                        </NativeSelectOption>
                    ))}
                </NativeSelect>
                <FieldError>{form.error('type')}</FieldError>
            </Field>

            <Field data-invalid={form.invalid('provider_id') || undefined}>
                <FieldLabel htmlFor="server-provider">Server provider</FieldLabel>
                <NativeSelect
                    id="server-provider"
                    name="provider_id"
                    size="comfortable"
                    value={form.data.provider_id || ''}
                    onChange={(event) => form.setField('provider_id', event.target.value)}
                    aria-invalid={form.invalid('provider_id') || undefined}
                    className="w-full"
                >
                    <NativeSelectOption value="">Select provider</NativeSelectOption>
                    {providerOptions.map((option) => (
                        <NativeSelectOption
                            key={String(option.value)}
                            value={String(option.value)}
                        >
                            {option.label}
                        </NativeSelectOption>
                    ))}
                </NativeSelect>
                <FieldError>{form.error('provider_id')}</FieldError>
            </Field>
        </>
    );
}
