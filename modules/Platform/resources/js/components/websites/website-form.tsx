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
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import type { PlatformOption, WebsiteFormValues } from '../../types/platform';

type WebsiteFormProps = {
    mode: 'create' | 'edit';
    website?: {
        id: number;
        name: string;
    };
    initialValues: WebsiteFormValues;
    serverOptions: PlatformOption[];
    agencyOptions: PlatformOption[];
    statusOptions: PlatformOption[];
    typeOptions: PlatformOption[];
    planOptions: PlatformOption[];
    dnsProviderOptions: PlatformOption[];
    cdnProviderOptions: PlatformOption[];
    order?: {
        id: number;
        reference?: string | null;
    } | null;
};

export default function WebsiteForm({
    mode,
    website,
    initialValues,
    serverOptions,
    agencyOptions,
    statusOptions,
    typeOptions,
    planOptions,
    dnsProviderOptions,
    cdnProviderOptions,
    order,
}: WebsiteFormProps) {
    const form = useAppForm<WebsiteFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'platform.websites.create'
                : `platform.websites.edit.${website?.id ?? 'new'}`,
        dirtyGuard: true,
    });

    const submitUrl =
        mode === 'create'
            ? route('platform.websites.store')
            : route('platform.websites.update', website!.id);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(mode === 'create' ? 'post' : 'put', submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast:
                mode === 'create'
                    ? 'Website created successfully.'
                    : 'Website updated successfully.',
        });
    };

    return (
        <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            {order ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Order context</CardTitle>
                        <CardDescription>
                            This website is being created from order #{order.id}{order.reference ? ` · ${order.reference}` : ''}.
                        </CardDescription>
                    </CardHeader>
                </Card>
            ) : null}

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Website profile</CardTitle>
                            <CardDescription>
                                Define the domain, provisioning target, and ownership context for the site.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('name') || undefined}>
                                        <FieldLabel htmlFor="name">Website name</FieldLabel>
                                        <Input
                                            id="name"
                                            value={form.data.name}
                                            onChange={(event) => form.setField('name', event.target.value)}
                                            onBlur={() => form.touch('name')}
                                            aria-invalid={form.invalid('name') || undefined}
                                        />
                                        <FieldError>{form.error('name')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('domain') || undefined}>
                                        <FieldLabel htmlFor="domain">Domain</FieldLabel>
                                        <Input
                                            id="domain"
                                            value={form.data.domain}
                                            onChange={(event) => form.setField('domain', event.target.value)}
                                            onBlur={() => form.touch('domain')}
                                            aria-invalid={form.invalid('domain') || undefined}
                                            placeholder="example.com"
                                        />
                                        <FieldError>{form.error('domain')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('type') || undefined}>
                                        <FieldLabel>Website type</FieldLabel>
                                        <Select value={form.data.type || undefined} onValueChange={(value) => form.setField('type', value)}>
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('type') || undefined}>
                                                <SelectValue placeholder="Select type" />
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
                                        <Select value={form.data.plan || undefined} onValueChange={(value) => form.setField('plan', value)}>
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
                                    <Field data-invalid={form.invalid('server_id') || undefined}>
                                        <FieldLabel>Server</FieldLabel>
                                        <Select value={form.data.server_id || undefined} onValueChange={(value) => form.setField('server_id', value)}>
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('server_id') || undefined}>
                                                <SelectValue placeholder="Select server" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {serverOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('server_id')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('agency_id') || undefined}>
                                        <FieldLabel>Agency</FieldLabel>
                                        <Select
                                            value={form.data.agency_id || '__none__'}
                                            onValueChange={(value) => form.setField('agency_id', value === '__none__' ? '' : value)}
                                        >
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('agency_id') || undefined}>
                                                <SelectValue placeholder="Select agency" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="__none__">No agency</SelectItem>
                                                    {agencyOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('agency_id')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('status') || undefined}>
                                        <FieldLabel>Status</FieldLabel>
                                        <Select value={form.data.status || undefined} onValueChange={(value) => form.setField('status', value)}>
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

                                    <Field data-invalid={form.invalid('expired_on') || undefined}>
                                        <FieldLabel htmlFor="expired_on">Expiry date</FieldLabel>
                                        <Input
                                            id="expired_on"
                                            type="date"
                                            value={form.data.expired_on}
                                            onChange={(event) => form.setField('expired_on', event.target.value)}
                                            onBlur={() => form.touch('expired_on')}
                                            aria-invalid={form.invalid('expired_on') || undefined}
                                        />
                                        <FieldError>{form.error('expired_on')}</FieldError>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Provider routing</CardTitle>
                            <CardDescription>
                                Pick the upstream DNS and CDN providers used during provisioning.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('dns_provider_id') || undefined}>
                                        <FieldLabel>DNS provider</FieldLabel>
                                        <Select
                                            value={form.data.dns_provider_id || undefined}
                                            onValueChange={(value) => form.setField('dns_provider_id', value)}
                                        >
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('dns_provider_id') || undefined}>
                                                <SelectValue placeholder="Select DNS provider" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {dnsProviderOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('dns_provider_id')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('cdn_provider_id') || undefined}>
                                        <FieldLabel>CDN provider</FieldLabel>
                                        <Select
                                            value={form.data.cdn_provider_id || undefined}
                                            onValueChange={(value) => form.setField('cdn_provider_id', value)}
                                        >
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('cdn_provider_id') || undefined}>
                                                <SelectValue placeholder="Select CDN provider" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {cdnProviderOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('cdn_provider_id')}</FieldError>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Provisioning identity</CardTitle>
                            <CardDescription>
                                Username and customer context used to create the provisioned site and any initial account metadata.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field data-invalid={form.invalid('website_username') || undefined}>
                                    <FieldLabel htmlFor="website_username">Website username</FieldLabel>
                                    <Input
                                        id="website_username"
                                        value={form.data.website_username}
                                        onChange={(event) => form.setField('website_username', event.target.value)}
                                        onBlur={() => form.touch('website_username')}
                                        aria-invalid={form.invalid('website_username') || undefined}
                                    />
                                    <FieldDescription>
                                        Optional custom username for the provisioned Hestia user. Leave blank to auto-generate.
                                    </FieldDescription>
                                    <FieldError>{form.error('website_username')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('owner_password') || undefined}>
                                    <FieldLabel htmlFor="owner_password">Owner password</FieldLabel>
                                    <Input
                                        id="owner_password"
                                        type="password"
                                        value={form.data.owner_password}
                                        onChange={(event) => form.setField('owner_password', event.target.value)}
                                        onBlur={() => form.touch('owner_password')}
                                        aria-invalid={form.invalid('owner_password') || undefined}
                                    />
                                    <FieldDescription>
                                        Optional initial password for generated website accounts.
                                    </FieldDescription>
                                    <FieldError>{form.error('owner_password')}</FieldError>
                                </Field>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('customer_name') || undefined}>
                                        <FieldLabel htmlFor="customer_name">Customer name</FieldLabel>
                                        <Input
                                            id="customer_name"
                                            value={form.data.customer_name}
                                            onChange={(event) => form.setField('customer_name', event.target.value)}
                                            onBlur={() => form.touch('customer_name')}
                                            aria-invalid={form.invalid('customer_name') || undefined}
                                        />
                                        <FieldError>{form.error('customer_name')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('customer_email') || undefined}>
                                        <FieldLabel htmlFor="customer_email">Customer email</FieldLabel>
                                        <Input
                                            id="customer_email"
                                            type="email"
                                            value={form.data.customer_email}
                                            onChange={(event) => form.setField('customer_email', event.target.value)}
                                            onBlur={() => form.touch('customer_email')}
                                            aria-invalid={form.invalid('customer_email') || undefined}
                                        />
                                        <FieldError>{form.error('customer_email')}</FieldError>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Flags</CardTitle>
                            <CardDescription>
                                Control how provisioning and integration steps behave for this website.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                {(
                                    [
                                        ['is_www', 'Use www host'],
                                        ['is_agency', 'Agency website'],
                                        ['skip_cdn', 'Skip CDN setup'],
                                        ['skip_dns', 'Skip DNS setup'],
                                        ['skip_ssl_issue', 'Skip SSL issuance'],
                                        ['skip_email', 'Skip email setup'],
                                    ] as const
                                ).map(([field, label]) => (
                                    <Field key={field} orientation="horizontal">
                                        <FieldLabel htmlFor={field}>{label}</FieldLabel>
                                        <Switch
                                            id={field}
                                            checked={form.data[field]}
                                            onCheckedChange={(checked) => form.setField(field, checked)}
                                        />
                                    </Field>
                                ))}
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button variant="outline" asChild>
                    <Link href={route('platform.websites.index', { status: 'all' })}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to websites
                    </Link>
                </Button>

                <Button type="submit" disabled={form.processing}>
                    {form.processing ? <Spinner data-icon="inline-start" /> : <SaveIcon data-icon="inline-start" />}
                    {mode === 'create' ? 'Create website' : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}
