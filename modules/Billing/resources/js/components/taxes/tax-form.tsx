import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type { BillingOption, TaxFormValues } from '../../types/billing';

type TaxFormProps = {
    mode: 'create' | 'edit';
    tax?: { id: number; name: string };
    initialValues: TaxFormValues;
    typeOptions: BillingOption[];
};

export default function TaxForm({
    mode,
    tax,
    initialValues,
    typeOptions,
}: TaxFormProps) {
    const form = useAppForm<TaxFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'billing.taxes.create'
                : `billing.taxes.edit.${tax?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            name: [formValidators.required('Name')],
            rate: [formValidators.required('Rate')],
            type: [formValidators.required('Type')],
        },
    });

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('app.billing.taxes.store')
            : route('app.billing.taxes.update', tax!.id);
    const submitLabel = mode === 'create' ? 'Create Tax' : 'Update Tax';

    function onSubmit(e: FormEvent) {
        e.preventDefault();
        form.submit(submitMethod, submitUrl, {
            successToast:
                mode === 'create'
                    ? 'Tax created successfully.'
                    : 'Tax updated successfully.',
            setDefaultsOnSuccess: true,
        });
    }

    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <FormErrorSummary errors={form.errors} />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Tax Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <FieldGroup cols={2}>
                                <Field>
                                    <FieldLabel>Name *</FieldLabel>
                                    <Input
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        placeholder="e.g. GST, VAT"
                                    />
                                    <FieldError>{form.errors.name}</FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel>Code</FieldLabel>
                                    <Input
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                        placeholder="e.g. GST18"
                                    />
                                </Field>
                            </FieldGroup>
                            <Field>
                                <FieldLabel>Description</FieldLabel>
                                <Textarea
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                    rows={2}
                                />
                            </Field>
                            <FieldGroup cols={2}>
                                <Field>
                                    <FieldLabel>Type *</FieldLabel>
                                    <NativeSelect
                                        value={form.data.type}
                                        onChange={(e) => form.setData('type', e.target.value)}
                                    >
                                        {typeOptions.map((opt) => (
                                            <NativeSelectOption key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>{form.errors.type}</FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel>Rate *</FieldLabel>
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.data.rate}
                                        onChange={(e) => form.setData('rate', e.target.value)}
                                        placeholder={form.data.type === 'percentage' ? 'e.g. 18.00' : 'e.g. 5.00'}
                                    />
                                    <FieldError>{form.errors.rate}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Location</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <FieldGroup cols={3}>
                                <Field>
                                    <FieldLabel>Country</FieldLabel>
                                    <Input
                                        value={form.data.country}
                                        onChange={(e) => form.setData('country', e.target.value)}
                                        placeholder="e.g. IN, US"
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel>State</FieldLabel>
                                    <Input
                                        value={form.data.state}
                                        onChange={(e) => form.setData('state', e.target.value)}
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel>Postal Code</FieldLabel>
                                    <Input
                                        value={form.data.postal_code}
                                        onChange={(e) => form.setData('postal_code', e.target.value)}
                                    />
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Validity Period</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <FieldGroup cols={2}>
                                <Field>
                                    <FieldLabel>Effective From</FieldLabel>
                                    <Input
                                        type="date"
                                        value={form.data.effective_from}
                                        onChange={(e) => form.setData('effective_from', e.target.value)}
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel>Effective To</FieldLabel>
                                    <Input
                                        type="date"
                                        value={form.data.effective_to}
                                        onChange={(e) => form.setData('effective_to', e.target.value)}
                                    />
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Settings</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Field>
                                <FieldLabel>Priority</FieldLabel>
                                <Input
                                    type="number"
                                    min="0"
                                    value={form.data.priority}
                                    onChange={(e) => form.setData('priority', e.target.value)}
                                />
                            </Field>
                            <Field className="flex items-center justify-between">
                                <FieldLabel>Compound Tax</FieldLabel>
                                <Switch
                                    checked={form.data.is_compound}
                                    onCheckedChange={(checked) => form.setData('is_compound', checked)}
                                />
                            </Field>
                            <Field className="flex items-center justify-between">
                                <FieldLabel>Active</FieldLabel>
                                <Switch
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) => form.setData('is_active', checked)}
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Button type="submit" className="w-full" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        {submitLabel}
                    </Button>
                </div>
            </div>
        </form>
    );
}
