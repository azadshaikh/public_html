import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
    dnsModeOptions: PlatformOption[];
    dnsProviderOptions: PlatformOption[];
    cdnProviderOptions: PlatformOption[];
    order?: {
        id: number;
        reference?: string | null;
    } | null;
};

type SelectFieldProps = {
    label: string;
    placeholder: string;
    value: string;
    options: PlatformOption[];
    error?: string;
    invalid: boolean;
    onChange: (value: string) => void;
    noneOptionLabel?: string;
};

const optionFields = [
    {
        field: 'is_agency',
        label: 'Is agency website?',
        description: "Enable if this is the agency's SaaS platform website.",
    },
    {
        field: 'is_www',
        label: 'Use WWW?',
        description: 'Enable to use the www subdomain as primary.',
    },
    {
        field: 'skip_cdn',
        label: 'Skip CDN setup?',
        description: 'Enable if CDN has already been configured manually.',
    },
    {
        field: 'skip_dns',
        label: 'Skip DNS setup?',
        description: 'Enable if DNS has already been configured manually.',
    },
    {
        field: 'skip_ssl_issue',
        label: 'Skip ACME SSL issuance?',
        description:
            'Enable for local or LAN sites. The platform will reuse an available certificate or generate a self-signed certificate when needed.',
    },
    {
        field: 'skip_email',
        label: 'Skip email setup?',
        description: 'Disable mailbox setup during initial provisioning.',
    },
] as const;

function SelectField({
    label,
    placeholder,
    value,
    options,
    error,
    invalid,
    onChange,
    noneOptionLabel,
}: SelectFieldProps) {
    const selectValue = value || (noneOptionLabel ? '__none__' : undefined);

    return (
        <Field data-invalid={invalid || undefined}>
            <FieldLabel>{label}</FieldLabel>
            <Select
                value={selectValue}
                onValueChange={(nextValue) =>
                    onChange(
                        noneOptionLabel && nextValue === '__none__'
                            ? ''
                            : nextValue,
                    )
                }
            >
                <SelectTrigger
                    className="w-full"
                    aria-invalid={invalid || undefined}
                >
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        {noneOptionLabel ? (
                            <SelectItem value="__none__">
                                {noneOptionLabel}
                            </SelectItem>
                        ) : null}
                        {options.map((option) => (
                            <SelectItem
                                key={String(option.value)}
                                value={String(option.value)}
                            >
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                </SelectContent>
            </Select>
            <FieldError>{error}</FieldError>
        </Field>
    );
}

export default function WebsiteForm({
    mode,
    website,
    initialValues,
    serverOptions,
    agencyOptions,
    statusOptions,
    typeOptions,
    planOptions,
    dnsModeOptions,
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

    const cancelUrl =
        mode === 'create'
            ? route('platform.websites.index', { status: 'all' })
            : route('platform.websites.show', website!.id);

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
        <form
            className="mx-auto flex w-full max-w-6xl flex-col gap-6"
            onSubmit={handleSubmit}
            noValidate
        >
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            {order ? (
                <Card size="sm">
                    <CardHeader>
                        <CardTitle>Order context</CardTitle>
                        <CardDescription>
                            This website is being created from order #{order.id}
                            {order.reference ? ` · ${order.reference}` : ''}.
                        </CardDescription>
                    </CardHeader>
                </Card>
            ) : null}

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Website information</CardTitle>
                            <CardDescription>
                                Define the domain, plan, and infrastructure
                                routing for this website.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field
                                        data-invalid={
                                            form.invalid('name') || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="name">
                                            Website name
                                        </FieldLabel>
                                        <Input
                                            id="name"
                                            value={form.data.name}
                                            placeholder="My Business Website"
                                            onChange={(event) =>
                                                form.setField(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() => form.touch('name')}
                                            aria-invalid={
                                                form.invalid('name') ||
                                                undefined
                                            }
                                        />
                                        <FieldDescription>
                                            Internal label used across the
                                            platform and operations views.
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('name')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('domain') || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="domain">
                                            Domain
                                        </FieldLabel>
                                        <Input
                                            id="domain"
                                            value={form.data.domain}
                                            placeholder="example.com"
                                            onChange={(event) =>
                                                form.setField(
                                                    'domain',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() => form.touch('domain')}
                                            aria-invalid={
                                                form.invalid('domain') ||
                                                undefined
                                            }
                                        />
                                        <FieldDescription>
                                            Primary domain that will be
                                            provisioned for the site.
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('domain')}
                                        </FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <SelectField
                                        label="Website type"
                                        placeholder="Select type"
                                        value={form.data.type}
                                        options={typeOptions}
                                        error={form.error('type')}
                                        invalid={form.invalid('type')}
                                        onChange={(value) =>
                                            form.setField('type', value)
                                        }
                                    />

                                    <SelectField
                                        label="Website plan"
                                        placeholder="Select plan"
                                        value={form.data.plan}
                                        options={planOptions}
                                        error={form.error('plan')}
                                        invalid={form.invalid('plan')}
                                        onChange={(value) =>
                                            form.setField('plan', value)
                                        }
                                    />
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <SelectField
                                        label="DNS mode"
                                        placeholder="Select DNS mode"
                                        value={form.data.dns_mode}
                                        options={dnsModeOptions}
                                        error={form.error('dns_mode')}
                                        invalid={form.invalid('dns_mode')}
                                        onChange={(value) =>
                                            form.setField('dns_mode', value)
                                        }
                                    />

                                    <SelectField
                                        label="Agency"
                                        placeholder="Select agency"
                                        value={form.data.agency_id}
                                        options={agencyOptions}
                                        error={form.error('agency_id')}
                                        invalid={form.invalid('agency_id')}
                                        onChange={(value) =>
                                            form.setField('agency_id', value)
                                        }
                                        noneOptionLabel="-- No agency --"
                                    />

                                    <SelectField
                                        label="Server"
                                        placeholder="Select server"
                                        value={form.data.server_id}
                                        options={serverOptions}
                                        error={form.error('server_id')}
                                        invalid={form.invalid('server_id')}
                                        onChange={(value) =>
                                            form.setField('server_id', value)
                                        }
                                    />
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <SelectField
                                        label="DNS provider"
                                        placeholder="Select DNS provider"
                                        value={form.data.dns_provider_id}
                                        options={dnsProviderOptions}
                                        error={form.error('dns_provider_id')}
                                        invalid={form.invalid(
                                            'dns_provider_id',
                                        )}
                                        onChange={(value) =>
                                            form.setField(
                                                'dns_provider_id',
                                                value,
                                            )
                                        }
                                    />

                                    <SelectField
                                        label="CDN provider"
                                        placeholder="Select CDN provider"
                                        value={form.data.cdn_provider_id}
                                        options={cdnProviderOptions}
                                        error={form.error('cdn_provider_id')}
                                        invalid={form.invalid(
                                            'cdn_provider_id',
                                        )}
                                        onChange={(value) =>
                                            form.setField(
                                                'cdn_provider_id',
                                                value,
                                            )
                                        }
                                    />
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Customer &amp; server</CardTitle>
                            <CardDescription>
                                Capture the customer contact and the account
                                identity used during provisioning.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field
                                        data-invalid={
                                            form.invalid('customer_name') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="customer_name">
                                            Customer name
                                        </FieldLabel>
                                        <Input
                                            id="customer_name"
                                            value={form.data.customer_name}
                                            placeholder="John Smith"
                                            onChange={(event) =>
                                                form.setField(
                                                    'customer_name',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('customer_name')
                                            }
                                            aria-invalid={
                                                form.invalid('customer_name') ||
                                                undefined
                                            }
                                        />
                                        <FieldDescription>
                                            Used for the first admin account and
                                            customer records.
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('customer_name')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('customer_email') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="customer_email">
                                            Customer email
                                        </FieldLabel>
                                        <Input
                                            id="customer_email"
                                            type="email"
                                            value={form.data.customer_email}
                                            placeholder="customer@example.com"
                                            onChange={(event) =>
                                                form.setField(
                                                    'customer_email',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('customer_email')
                                            }
                                            aria-invalid={
                                                form.invalid(
                                                    'customer_email',
                                                ) || undefined
                                            }
                                        />
                                        <FieldDescription>
                                            Used for the website owner login and
                                            platform notifications.
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('customer_email')}
                                        </FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field
                                        data-invalid={
                                            form.invalid('website_username') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="website_username">
                                            Server username
                                        </FieldLabel>
                                        <Input
                                            id="website_username"
                                            value={form.data.website_username}
                                            placeholder="Enter server username"
                                            onChange={(event) =>
                                                form.setField(
                                                    'website_username',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('website_username')
                                            }
                                            aria-invalid={
                                                form.invalid('website_username') ||
                                                undefined
                                            }
                                        />
                                        <FieldDescription>
                                            Leave blank to let the platform
                                            generate a username automatically.
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('website_username')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('owner_password') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="owner_password">
                                            Initial account password
                                        </FieldLabel>
                                        <Input
                                            id="owner_password"
                                            type="password"
                                            value={form.data.owner_password}
                                            onChange={(event) =>
                                                form.setField(
                                                    'owner_password',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('owner_password')
                                            }
                                            aria-invalid={
                                                form.invalid('owner_password') ||
                                                undefined
                                            }
                                        />
                                        <FieldDescription>
                                            Optional password for the generated
                                            website owner account.
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('owner_password')}
                                        </FieldError>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6 xl:sticky xl:top-6 xl:self-start">
                    <Card>
                        <CardHeader>
                            <CardTitle>Options</CardTitle>
                            <CardDescription>
                                Control how provisioning behaves for this
                                website.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                {optionFields.map(
                                    ({ field, label, description }) => (
                                        <Field
                                            key={field}
                                            orientation="horizontal"
                                            className="items-start justify-between gap-4 rounded-lg border p-3"
                                        >
                                            <FieldContent>
                                                <FieldLabel htmlFor={field}>
                                                    {label}
                                                </FieldLabel>
                                                <FieldDescription>
                                                    {description}
                                                </FieldDescription>
                                            </FieldContent>
                                            <Switch
                                                id={field}
                                                checked={form.data[field]}
                                                onCheckedChange={(checked) =>
                                                    form.setField(
                                                        field,
                                                        checked,
                                                    )
                                                }
                                            />
                                        </Field>
                                    ),
                                )}
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Status</CardTitle>
                            <CardDescription>
                                Review lifecycle settings before the website is
                                created or updated.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <SelectField
                                    label="Status"
                                    placeholder="Provisioning"
                                    value={form.data.status}
                                    options={statusOptions}
                                    error={form.error('status')}
                                    invalid={form.invalid('status')}
                                    onChange={(value) =>
                                        form.setField('status', value)
                                    }
                                />

                                <Field
                                    data-invalid={
                                        form.invalid('expired_on') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="expired_on">
                                        Expiry date
                                    </FieldLabel>
                                    <Input
                                        id="expired_on"
                                        type="date"
                                        value={form.data.expired_on}
                                        onChange={(event) =>
                                            form.setField(
                                                'expired_on',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('expired_on')}
                                        aria-invalid={
                                            form.invalid('expired_on') ||
                                            undefined
                                        }
                                    />
                                    <FieldDescription>
                                        New websites usually start in
                                        provisioning until setup completes.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('expired_on')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {mode === 'create'
                                    ? 'Save website'
                                    : 'Update website'}
                            </CardTitle>
                            <CardDescription>
                                Review the setup and submit when everything
                                looks correct.
                            </CardDescription>
                        </CardHeader>
                        <CardFooter className="flex flex-col gap-3">
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="w-full"
                            >
                                {form.processing ? (
                                    <Spinner data-icon="inline-start" />
                                ) : (
                                    <SaveIcon data-icon="inline-start" />
                                )}
                                {mode === 'create'
                                    ? 'Save website'
                                    : 'Save changes'}
                            </Button>

                            <Button
                                variant="outline"
                                asChild
                                className="w-full"
                            >
                                <Link href={cancelUrl}>
                                    <ArrowLeftIcon data-icon="inline-start" />
                                    Cancel
                                </Link>
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </form>
    );
}
