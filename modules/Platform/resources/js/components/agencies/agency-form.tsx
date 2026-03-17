import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import type { AgencyFormValues, PlatformOption } from '../../types/platform';

type AgencyFormProps = {
    mode: 'create' | 'edit';
    agency?: {
        id: number;
        name: string;
    };
    initialValues: AgencyFormValues;
    typeOptions: PlatformOption[];
    ownerOptions: PlatformOption[];
    planOptions: PlatformOption[];
    statusOptions: PlatformOption[];
    websiteOptions: PlatformOption[];
    defaultCountryCode: string;
    defaultPhoneCode: string;
};

export default function AgencyForm({
    mode,
    agency,
    initialValues,
    typeOptions,
    ownerOptions,
    planOptions,
    statusOptions,
    websiteOptions,
    defaultCountryCode,
    defaultPhoneCode,
}: AgencyFormProps) {
    const form = useAppForm<AgencyFormValues>({
        defaults: {
            ...initialValues,
            country_code: initialValues.country_code || defaultCountryCode,
            phone_code: initialValues.phone_code || defaultPhoneCode,
        },
        rememberKey:
            mode === 'create'
                ? 'platform.agencies.create'
                : `platform.agencies.edit.${agency?.id ?? 'new'}`,
        dirtyGuard: true,
    });

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('platform.agencies.store')
            : route('platform.agencies.update', agency!.id);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast:
                mode === 'create'
                    ? 'Agency created successfully.'
                    : 'Agency updated successfully.',
        });
    };

    return (
        <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Agency profile</CardTitle>
                            <CardDescription>
                                Configure the account identity, ownership, and lifecycle state.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('name') || undefined}>
                                        <FieldLabel htmlFor="name">Agency name</FieldLabel>
                                        <Input
                                            id="name"
                                            value={form.data.name}
                                            onChange={(event) => form.setField('name', event.target.value)}
                                            onBlur={() => form.touch('name')}
                                            aria-invalid={form.invalid('name') || undefined}
                                        />
                                        <FieldError>{form.error('name')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('email') || undefined}>
                                        <FieldLabel htmlFor="email">Primary email</FieldLabel>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={form.data.email}
                                            onChange={(event) => form.setField('email', event.target.value)}
                                            onBlur={() => form.touch('email')}
                                            aria-invalid={form.invalid('email') || undefined}
                                        />
                                        <FieldError>{form.error('email')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('type') || undefined}>
                                        <FieldLabel>Type</FieldLabel>
                                        <Select
                                            value={form.data.type || undefined}
                                            onValueChange={(value) => form.setField('type', value)}
                                        >
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('type') || undefined}>
                                                <SelectValue placeholder="Select agency type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {typeOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('type')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('plan') || undefined}>
                                        <FieldLabel>Plan</FieldLabel>
                                        <Select
                                            value={form.data.plan || undefined}
                                            onValueChange={(value) => form.setField('plan', value)}
                                        >
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('plan') || undefined}>
                                                <SelectValue placeholder="Select plan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {planOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('plan')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('owner_id') || undefined}>
                                        <FieldLabel>Owner</FieldLabel>
                                        <Select
                                            value={form.data.owner_id || undefined}
                                            onValueChange={(value) => form.setField('owner_id', value)}
                                        >
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('owner_id') || undefined}>
                                                <SelectValue placeholder="Select owner" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {ownerOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('owner_id')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('status') || undefined}>
                                        <FieldLabel>Status</FieldLabel>
                                        <Select
                                            value={form.data.status || undefined}
                                            onValueChange={(value) => form.setField('status', value)}
                                        >
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('status') || undefined}>
                                                <SelectValue placeholder="Select status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {statusOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('status')}</FieldError>
                                    </Field>
                                </div>

                                <Field data-invalid={form.invalid('agency_website_id') || undefined}>
                                    <FieldLabel>Agency website</FieldLabel>
                                    <Select
                                        value={form.data.agency_website_id || '__none__'}
                                        onValueChange={(value) =>
                                            form.setField(
                                                'agency_website_id',
                                                value === '__none__' ? '' : value,
                                            )
                                        }
                                    >
                                        <SelectTrigger className="w-full" aria-invalid={form.invalid('agency_website_id') || undefined}>
                                            <SelectValue placeholder="Select agency website" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="__none__">No linked website</SelectItem>
                                                {websiteOptions.map((option) => (
                                                    <SelectItem key={String(option.value)} value={String(option.value)}>
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <FieldDescription>
                                        Available after the agency has a designated platform website.
                                    </FieldDescription>
                                    <FieldError>{form.error('agency_website_id')}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Branding</CardTitle>
                            <CardDescription>
                                Set the branding metadata pushed to agency-managed websites.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field data-invalid={form.invalid('branding_name') || undefined}>
                                    <FieldLabel htmlFor="branding_name">Branding name</FieldLabel>
                                    <Input
                                        id="branding_name"
                                        value={form.data.branding_name}
                                        onChange={(event) => form.setField('branding_name', event.target.value)}
                                        onBlur={() => form.touch('branding_name')}
                                        aria-invalid={form.invalid('branding_name') || undefined}
                                    />
                                    <FieldError>{form.error('branding_name')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('branding_website') || undefined}>
                                    <FieldLabel htmlFor="branding_website">Brand website</FieldLabel>
                                    <Input
                                        id="branding_website"
                                        value={form.data.branding_website}
                                        onChange={(event) => form.setField('branding_website', event.target.value)}
                                        onBlur={() => form.touch('branding_website')}
                                        aria-invalid={form.invalid('branding_website') || undefined}
                                        placeholder="https://example.com"
                                    />
                                    <FieldError>{form.error('branding_website')}</FieldError>
                                </Field>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('branding_logo') || undefined}>
                                        <FieldLabel htmlFor="branding_logo">Logo URL</FieldLabel>
                                        <Input
                                            id="branding_logo"
                                            value={form.data.branding_logo}
                                            onChange={(event) => form.setField('branding_logo', event.target.value)}
                                            onBlur={() => form.touch('branding_logo')}
                                            aria-invalid={form.invalid('branding_logo') || undefined}
                                        />
                                        <FieldError>{form.error('branding_logo')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('branding_icon') || undefined}>
                                        <FieldLabel htmlFor="branding_icon">Icon URL</FieldLabel>
                                        <Input
                                            id="branding_icon"
                                            value={form.data.branding_icon}
                                            onChange={(event) => form.setField('branding_icon', event.target.value)}
                                            onBlur={() => form.touch('branding_icon')}
                                            aria-invalid={form.invalid('branding_icon') || undefined}
                                        />
                                        <FieldError>{form.error('branding_icon')}</FieldError>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Routing and webhooks</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('website_id_prefix') || undefined}>
                                        <FieldLabel htmlFor="website_id_prefix">Website ID prefix</FieldLabel>
                                        <Input
                                            id="website_id_prefix"
                                            value={form.data.website_id_prefix}
                                            onChange={(event) => form.setField('website_id_prefix', event.target.value)}
                                            onBlur={() => form.touch('website_id_prefix')}
                                            aria-invalid={form.invalid('website_id_prefix') || undefined}
                                        />
                                        <FieldError>{form.error('website_id_prefix')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('website_id_zero_padding') || undefined}>
                                        <FieldLabel htmlFor="website_id_zero_padding">Zero padding</FieldLabel>
                                        <Input
                                            id="website_id_zero_padding"
                                            type="number"
                                            min={1}
                                            max={10}
                                            value={form.data.website_id_zero_padding}
                                            onChange={(event) =>
                                                form.setField('website_id_zero_padding', event.target.value)
                                            }
                                            onBlur={() => form.touch('website_id_zero_padding')}
                                            aria-invalid={form.invalid('website_id_zero_padding') || undefined}
                                        />
                                        <FieldError>{form.error('website_id_zero_padding')}</FieldError>
                                    </Field>
                                </div>

                                <Field data-invalid={form.invalid('webhook_url') || undefined}>
                                    <FieldLabel htmlFor="webhook_url">Webhook URL</FieldLabel>
                                    <Input
                                        id="webhook_url"
                                        value={form.data.webhook_url}
                                        onChange={(event) => form.setField('webhook_url', event.target.value)}
                                        onBlur={() => form.touch('webhook_url')}
                                        aria-invalid={form.invalid('webhook_url') || undefined}
                                        placeholder="https://agency.example.com/api/platform/webhook"
                                    />
                                    <FieldError>{form.error('webhook_url')}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Primary contact</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('phone_code') || undefined}>
                                        <FieldLabel htmlFor="phone_code">Phone code</FieldLabel>
                                        <Input
                                            id="phone_code"
                                            value={form.data.phone_code}
                                            onChange={(event) => form.setField('phone_code', event.target.value)}
                                            onBlur={() => form.touch('phone_code')}
                                            aria-invalid={form.invalid('phone_code') || undefined}
                                        />
                                        <FieldError>{form.error('phone_code')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('phone') || undefined}>
                                        <FieldLabel htmlFor="phone">Phone number</FieldLabel>
                                        <Input
                                            id="phone"
                                            value={form.data.phone}
                                            onChange={(event) => form.setField('phone', event.target.value)}
                                            onBlur={() => form.touch('phone')}
                                            aria-invalid={form.invalid('phone') || undefined}
                                        />
                                        <FieldError>{form.error('phone')}</FieldError>
                                    </Field>
                                </div>

                                <Field data-invalid={form.invalid('address1') || undefined}>
                                    <FieldLabel htmlFor="address1">Street address</FieldLabel>
                                    <Input
                                        id="address1"
                                        value={form.data.address1}
                                        onChange={(event) => form.setField('address1', event.target.value)}
                                        onBlur={() => form.touch('address1')}
                                        aria-invalid={form.invalid('address1') || undefined}
                                    />
                                    <FieldError>{form.error('address1')}</FieldError>
                                </Field>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('city') || undefined}>
                                        <FieldLabel htmlFor="city">City</FieldLabel>
                                        <Input
                                            id="city"
                                            value={form.data.city}
                                            onChange={(event) => form.setField('city', event.target.value)}
                                            onBlur={() => form.touch('city')}
                                            aria-invalid={form.invalid('city') || undefined}
                                        />
                                        <FieldError>{form.error('city')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('zip') || undefined}>
                                        <FieldLabel htmlFor="zip">ZIP / postal code</FieldLabel>
                                        <Input
                                            id="zip"
                                            value={form.data.zip}
                                            onChange={(event) => form.setField('zip', event.target.value)}
                                            onBlur={() => form.touch('zip')}
                                            aria-invalid={form.invalid('zip') || undefined}
                                        />
                                        <FieldError>{form.error('zip')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('state_code') || undefined}>
                                        <FieldLabel htmlFor="state_code">State code</FieldLabel>
                                        <Input
                                            id="state_code"
                                            value={form.data.state_code}
                                            onChange={(event) => form.setField('state_code', event.target.value)}
                                            onBlur={() => form.touch('state_code')}
                                            aria-invalid={form.invalid('state_code') || undefined}
                                        />
                                        <FieldError>{form.error('state_code')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('country_code') || undefined}>
                                        <FieldLabel htmlFor="country_code">Country code</FieldLabel>
                                        <Input
                                            id="country_code"
                                            value={form.data.country_code}
                                            onChange={(event) => form.setField('country_code', event.target.value)}
                                            onBlur={() => form.touch('country_code')}
                                            aria-invalid={form.invalid('country_code') || undefined}
                                        />
                                        <FieldError>{form.error('country_code')}</FieldError>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button variant="outline" asChild>
                    <Link href={route('platform.agencies.index', { status: 'all' })}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to agencies
                    </Link>
                </Button>

                <Button type="submit" disabled={form.processing}>
                    {form.processing ? <Spinner data-icon="inline-start" /> : <SaveIcon data-icon="inline-start" />}
                    {mode === 'create' ? 'Create agency' : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}
