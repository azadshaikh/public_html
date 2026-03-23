import { PlusIcon, Trash2Icon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type { PlanFormFeatureRow, PlanFormPriceRow, PlanFormValues, SubscriptionOption } from '../../types/subscriptions';

type PlanFormProps = {
    mode: 'create' | 'edit';
    plan?: { id: number; name: string };
    initialValues: PlanFormValues;
    billingCycleOptions: SubscriptionOption[];
    currencyOptions: SubscriptionOption[];
    featureTypeOptions: SubscriptionOption[];
};

const emptyPrice: PlanFormPriceRow = { billing_cycle: '', price: '', currency: 'USD', is_active: true, sort_order: 0 };
const emptyFeature: PlanFormFeatureRow = { code: '', name: '', description: '', type: 'boolean', value: '', sort_order: 0 };

export default function PlanForm({ mode, plan, initialValues, billingCycleOptions, currencyOptions, featureTypeOptions }: PlanFormProps) {
    const form = useAppForm<PlanFormValues & { prices_present: boolean; features_present: boolean }>({
        defaults: {
            ...initialValues,
            prices_present: true,
            features_present: true,
        },
        rememberKey: mode === 'create' ? 'subscriptions.plans.create' : `subscriptions.plans.edit.${plan?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            code: [formValidators.required('Code')],
            name: [formValidators.required('Name')],
        },
    });

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl = mode === 'create' ? route('subscriptions.plans.store') : route('subscriptions.plans.update', plan!.id);
    const submitLabel = mode === 'create' ? 'Create Plan' : 'Update Plan';

    function onSubmit(e: FormEvent) {
        e.preventDefault();
        form.submit(submitMethod, submitUrl, {
            successToast: mode === 'create' ? 'Plan created successfully.' : 'Plan updated successfully.',
            setDefaultsOnSuccess: true,
        });
    }

    // Price row management
    function addPrice() {
        form.setData('prices', [...form.data.prices, { ...emptyPrice, sort_order: form.data.prices.length }]);
    }

    function removePrice(index: number) {
        form.setData(
            'prices',
            form.data.prices.filter((_, i) => i !== index),
        );
    }

    function updatePrice<K extends keyof PlanFormPriceRow>(index: number, key: K, value: PlanFormPriceRow[K]) {
        const updated = [...form.data.prices];
        updated[index] = { ...updated[index], [key]: value };
        form.setData('prices', updated);
    }

    // Feature row management
    function addFeature() {
        form.setData('features', [...form.data.features, { ...emptyFeature, sort_order: form.data.features.length }]);
    }

    function removeFeature(index: number) {
        form.setData(
            'features',
            form.data.features.filter((_, i) => i !== index),
        );
    }

    function updateFeature<K extends keyof PlanFormFeatureRow>(index: number, key: K, value: PlanFormFeatureRow[K]) {
        const updated = [...form.data.features];
        updated[index] = { ...updated[index], [key]: value };
        form.setData('features', updated);
    }

    return (
        <form onSubmit={onSubmit} className="space-y-6">
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    {/* Plan Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Plan Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <Field>
                                    <FieldLabel>Code *</FieldLabel>
                                    <Input
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                        placeholder="e.g. starter"
                                    />
                                    <FieldError>{form.errors.code}</FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel>Name *</FieldLabel>
                                    <Input
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        placeholder="e.g. Starter Plan"
                                    />
                                    <FieldError>{form.errors.name}</FieldError>
                                </Field>
                            </div>
                            <Field>
                                <FieldLabel>Description</FieldLabel>
                                <Textarea
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                    rows={2}
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    {/* Pricing Tiers */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Pricing</CardTitle>
                            <Button type="button" variant="outline" size="sm" onClick={addPrice}>
                                <PlusIcon data-icon="inline-start" />
                                Add Price
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {form.data.prices.length === 0 && (
                                <p className="text-sm text-muted-foreground">No pricing tiers yet. Add a price to get started.</p>
                            )}
                            {form.data.prices.map((priceRow, idx) => (
                                <div key={idx} className="rounded-lg border bg-muted/30 p-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <span className="text-sm font-medium">Price #{idx + 1}</span>
                                        <Button type="button" variant="ghost" size="sm" onClick={() => removePrice(idx)}>
                                            <Trash2Icon className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <div className="grid grid-cols-3 gap-4">
                                        <Field>
                                            <FieldLabel>Billing Cycle</FieldLabel>
                                            <NativeSelect value={priceRow.billing_cycle} onChange={(e) => updatePrice(idx, 'billing_cycle', e.target.value)}>
                                                <NativeSelectOption value="">Select...</NativeSelectOption>
                                                {billingCycleOptions.map((opt) => (
                                                    <NativeSelectOption key={opt.value} value={String(opt.value)}>
                                                        {opt.label}
                                                    </NativeSelectOption>
                                                ))}
                                            </NativeSelect>
                                            <FieldError>{form.errors[`prices.${idx}.billing_cycle` as keyof typeof form.errors]}</FieldError>
                                        </Field>
                                        <Field>
                                            <FieldLabel>Price</FieldLabel>
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={priceRow.price}
                                                onChange={(e) => updatePrice(idx, 'price', e.target.value)}
                                                placeholder="0.00"
                                            />
                                            <FieldError>{form.errors[`prices.${idx}.price` as keyof typeof form.errors]}</FieldError>
                                        </Field>
                                        <Field>
                                            <FieldLabel>Currency</FieldLabel>
                                            <NativeSelect value={priceRow.currency} onChange={(e) => updatePrice(idx, 'currency', e.target.value)}>
                                                {currencyOptions.map((opt) => (
                                                    <NativeSelectOption key={opt.value} value={String(opt.value)}>
                                                        {opt.label}
                                                    </NativeSelectOption>
                                                ))}
                                            </NativeSelect>
                                        </Field>
                                    </div>
                                    <div className="mt-3 flex items-center gap-3">
                                        <Switch
                                            checked={priceRow.is_active}
                                            onCheckedChange={(checked) => updatePrice(idx, 'is_active', checked)}
                                        />
                                        <span className="text-sm">Active</span>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    {/* Features */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Features</CardTitle>
                            <Button type="button" variant="outline" size="sm" onClick={addFeature}>
                                <PlusIcon data-icon="inline-start" />
                                Add Feature
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {form.data.features.length === 0 && (
                                <p className="text-sm text-muted-foreground">No features defined. Add features to describe what this plan includes.</p>
                            )}
                            {form.data.features.map((feature, idx) => (
                                <div key={idx} className="rounded-lg border bg-muted/30 p-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <span className="text-sm font-medium">Feature #{idx + 1}</span>
                                        <Button type="button" variant="ghost" size="sm" onClick={() => removeFeature(idx)}>
                                            <Trash2Icon className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <Field>
                                            <FieldLabel>Code</FieldLabel>
                                            <Input value={feature.code} onChange={(e) => updateFeature(idx, 'code', e.target.value)} placeholder="e.g. max_users" />
                                            <FieldError>{form.errors[`features.${idx}.code` as keyof typeof form.errors]}</FieldError>
                                        </Field>
                                        <Field>
                                            <FieldLabel>Name</FieldLabel>
                                            <Input value={feature.name} onChange={(e) => updateFeature(idx, 'name', e.target.value)} placeholder="e.g. Maximum Users" />
                                            <FieldError>{form.errors[`features.${idx}.name` as keyof typeof form.errors]}</FieldError>
                                        </Field>
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <Field>
                                            <FieldLabel>Type</FieldLabel>
                                            <NativeSelect value={feature.type} onChange={(e) => updateFeature(idx, 'type', e.target.value)}>
                                                {featureTypeOptions.map((opt) => (
                                                    <NativeSelectOption key={opt.value} value={String(opt.value)}>
                                                        {opt.label}
                                                    </NativeSelectOption>
                                                ))}
                                            </NativeSelect>
                                            <FieldError>{form.errors[`features.${idx}.type` as keyof typeof form.errors]}</FieldError>
                                        </Field>
                                        {(feature.type === 'limit' || feature.type === 'value') && (
                                            <Field>
                                                <FieldLabel>Value</FieldLabel>
                                                <Input
                                                    value={feature.value}
                                                    onChange={(e) => updateFeature(idx, 'value', e.target.value)}
                                                    placeholder={feature.type === 'limit' ? 'e.g. 10' : 'e.g. Premium'}
                                                />
                                            </Field>
                                        )}
                                    </div>
                                    <Field>
                                        <FieldLabel>Description</FieldLabel>
                                        <Input value={feature.description} onChange={(e) => updateFeature(idx, 'description', e.target.value)} placeholder="Brief description" />
                                    </Field>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Settings</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <Field>
                                    <FieldLabel>Trial Days</FieldLabel>
                                    <Input
                                        type="number"
                                        min="0"
                                        max="365"
                                        value={form.data.trial_days}
                                        onChange={(e) => form.setData('trial_days', e.target.value)}
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel>Grace Days</FieldLabel>
                                    <Input
                                        type="number"
                                        min="0"
                                        max="90"
                                        value={form.data.grace_days}
                                        onChange={(e) => form.setData('grace_days', e.target.value)}
                                    />
                                </Field>
                            </div>
                            <Field>
                                <FieldLabel>Sort Order</FieldLabel>
                                <Input
                                    type="number"
                                    min="0"
                                    value={form.data.sort_order}
                                    onChange={(e) => form.setData('sort_order', e.target.value)}
                                />
                            </Field>
                            <div className="flex items-center justify-between gap-3 rounded-lg border p-3">
                                <div>
                                    <p className="text-sm font-medium">Popular</p>
                                    <p className="text-xs text-muted-foreground">Highlight as a recommended plan</p>
                                </div>
                                <Switch checked={form.data.is_popular} onCheckedChange={(checked) => form.setData('is_popular', checked)} />
                            </div>
                            <div className="flex items-center justify-between gap-3 rounded-lg border p-3">
                                <div>
                                    <p className="text-sm font-medium">Active</p>
                                    <p className="text-xs text-muted-foreground">Make this plan available for new subscriptions</p>
                                </div>
                                <Switch checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', checked)} />
                            </div>
                        </CardContent>
                    </Card>

                    <Button type="submit" className="w-full" disabled={form.processing}>
                        {form.processing && <Spinner data-icon="inline-start" />}
                        {submitLabel}
                    </Button>
                </div>
            </div>
        </form>
    );
}
