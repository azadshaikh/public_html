import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, SaveIcon } from 'lucide-react';
import { CitySelect } from '@/components/geo/city-select';
import { CountrySelect } from '@/components/geo/country-select';
import { StateSelect } from '@/components/geo/state-select';
import { MediaPickerUrlInput } from '@/components/media/media-picker-url-input';
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
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { useAppForm } from '@/hooks/use-app-form';
import type { AgencyFormValues, PlatformMediaPickerPageProps, PlatformOption } from '../../types/platform';

type AgencyFormInstance = ReturnType<typeof useAppForm<AgencyFormValues>>;

type SelectFieldProps = {
    label: string;
    placeholder: string;
    value: string;
    options: PlatformOption[];
    error?: string;
    invalid: boolean;
    onChange: (value: string) => void;
    noneOptionLabel?: string;
    description?: string;
};

type AgencyFormMainSectionsProps = PlatformMediaPickerPageProps & {
    form: AgencyFormInstance;
    typeOptions: PlatformOption[];
    ownerOptions: PlatformOption[];
    planOptions: PlatformOption[];
    phoneCodeOptions: PlatformOption[];
    pickerAction: string;
};

type AgencyFormSidebarProps = {
    form: AgencyFormInstance;
    mode: 'create' | 'edit';
    statusOptions: PlatformOption[];
    websiteOptions: PlatformOption[];
    cancelUrl: string;
    canLinkAgencyWebsite: boolean;
    websiteIdExample: string;
};

export function SelectField({
    label,
    placeholder,
    value,
    options,
    error,
    invalid,
    onChange,
    noneOptionLabel,
    description,
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
            {description ? (
                <FieldDescription>{description}</FieldDescription>
            ) : null}
            <FieldError>{error}</FieldError>
        </Field>
    );
}

export function buildWebsiteIdExample(
    prefix: string,
    zeroPadding: string,
): string {
    const normalizedPrefix =
        prefix.trim().toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10) ||
        'WS';
    const padding = Number.parseInt(zeroPadding, 10);
    const safePadding = Number.isFinite(padding)
        ? Math.min(Math.max(padding, 1), 10)
        : 5;

    return `${normalizedPrefix}${String(1).padStart(safePadding, '0')}`;
}

export function AgencyFormMainSections({
    form,
    typeOptions,
    ownerOptions,
    planOptions,
    phoneCodeOptions,
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerStatistics = null,
    pickerAction,
}: AgencyFormMainSectionsProps) {
    return (
        <div className="flex flex-col gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>Agency profile</CardTitle>
                    <CardDescription>
                        Capture the core account identity and ownership details
                        used across the platform.
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
                                    placeholder="Acme Studio"
                                    onChange={(event) =>
                                        form.setField('name', event.target.value)
                                    }
                                    onBlur={() => form.touch('name')}
                                    aria-invalid={form.invalid('name') || undefined}
                                />
                                <FieldDescription>
                                    Internal account name shown in operations,
                                    billing, and website assignment flows.
                                </FieldDescription>
                                <FieldError>{form.error('name')}</FieldError>
                            </Field>

                            <Field
                                data-invalid={form.invalid('email') || undefined}
                            >
                                <FieldLabel htmlFor="email">
                                    Primary email
                                </FieldLabel>
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    placeholder="agency@example.com"
                                    onChange={(event) =>
                                        form.setField('email', event.target.value)
                                    }
                                    onBlur={() => form.touch('email')}
                                    aria-invalid={
                                        form.invalid('email') || undefined
                                    }
                                />
                                <FieldDescription>
                                    Primary contact used for agency communication
                                    and onboarding.
                                </FieldDescription>
                                <FieldError>{form.error('email')}</FieldError>
                            </Field>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <SelectField
                                label="Type"
                                placeholder="Select agency type"
                                value={form.data.type}
                                options={typeOptions}
                                error={form.error('type')}
                                invalid={form.invalid('type')}
                                onChange={(value) => form.setField('type', value)}
                            />

                            <SelectField
                                label="Plan"
                                placeholder="Select plan"
                                value={form.data.plan}
                                options={planOptions}
                                error={form.error('plan')}
                                invalid={form.invalid('plan')}
                                onChange={(value) => form.setField('plan', value)}
                                description="White-label branding and webhook flows depend on the agency plan."
                            />
                        </div>

                        <SelectField
                            label="Owner"
                            placeholder="Select owner"
                            value={form.data.owner_id}
                            options={ownerOptions}
                            error={form.error('owner_id')}
                            invalid={form.invalid('owner_id')}
                            onChange={(value) => form.setField('owner_id', value)}
                        />
                    </FieldGroup>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Branding</CardTitle>
                    <CardDescription>
                        Set the branding metadata pushed to agency-managed
                        websites and reseller surfaces.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <FieldGroup>
                        <Field
                            data-invalid={
                                form.invalid('branding_name') || undefined
                            }
                        >
                            <FieldLabel htmlFor="branding_name">
                                Branding name
                            </FieldLabel>
                            <Input
                                id="branding_name"
                                value={form.data.branding_name}
                                placeholder="Acme Cloud"
                                onChange={(event) =>
                                    form.setField(
                                        'branding_name',
                                        event.target.value,
                                    )
                                }
                                onBlur={() => form.touch('branding_name')}
                                aria-invalid={
                                    form.invalid('branding_name') || undefined
                                }
                            />
                            <FieldDescription>
                                Brand label exposed to agency-managed websites
                                and webhook consumers.
                            </FieldDescription>
                            <FieldError>
                                {form.error('branding_name')}
                            </FieldError>
                        </Field>

                        <Field
                            data-invalid={
                                form.invalid('branding_website') || undefined
                            }
                        >
                            <FieldLabel htmlFor="branding_website">
                                Brand website
                            </FieldLabel>
                            <Input
                                id="branding_website"
                                value={form.data.branding_website}
                                onChange={(event) =>
                                    form.setField(
                                        'branding_website',
                                        event.target.value,
                                    )
                                }
                                onBlur={() => form.touch('branding_website')}
                                aria-invalid={
                                    form.invalid('branding_website') || undefined
                                }
                                placeholder="https://example.com"
                            />
                            <FieldDescription>
                                Public website for the agency brand or reseller
                                storefront.
                            </FieldDescription>
                            <FieldError>
                                {form.error('branding_website')}
                            </FieldError>
                        </Field>

                        <div className="grid gap-4 md:grid-cols-2">
                            <Field
                                data-invalid={
                                    form.invalid('branding_logo') || undefined
                                }
                            >
                                <FieldLabel htmlFor="branding_logo">
                                    Logo URL
                                </FieldLabel>
                                <MediaPickerUrlInput
                                    id="branding_logo"
                                    value={form.data.branding_logo}
                                    onChange={(value) =>
                                        form.setField('branding_logo', value)
                                    }
                                    onBlur={() => form.touch('branding_logo')}
                                    aria-invalid={
                                        form.invalid('branding_logo') ||
                                        undefined
                                    }
                                    placeholder="https://example.com/logo.svg"
                                    pickerMedia={pickerMedia}
                                    pickerFilters={pickerFilters}
                                    uploadSettings={uploadSettings}
                                    pickerStatistics={pickerStatistics}
                                    pickerAction={pickerAction}
                                    dialogTitle="Select agency logo"
                                    pickerButtonLabel="Select agency logo from media library"
                                    clearButtonLabel="Clear agency logo"
                                    showThumbnailPreview
                                    thumbnailAlt="Agency logo preview"
                                />
                                <FieldError>
                                    {form.error('branding_logo')}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('branding_icon') || undefined
                                }
                            >
                                <FieldLabel htmlFor="branding_icon">
                                    Icon URL
                                </FieldLabel>
                                <MediaPickerUrlInput
                                    id="branding_icon"
                                    value={form.data.branding_icon}
                                    onChange={(value) =>
                                        form.setField('branding_icon', value)
                                    }
                                    onBlur={() => form.touch('branding_icon')}
                                    aria-invalid={
                                        form.invalid('branding_icon') ||
                                        undefined
                                    }
                                    placeholder="https://example.com/icon.svg"
                                    pickerMedia={pickerMedia}
                                    pickerFilters={pickerFilters}
                                    uploadSettings={uploadSettings}
                                    pickerStatistics={pickerStatistics}
                                    pickerAction={pickerAction}
                                    dialogTitle="Select agency icon"
                                    pickerButtonLabel="Select agency icon from media library"
                                    clearButtonLabel="Clear agency icon"
                                    showThumbnailPreview
                                    thumbnailAlt="Agency icon preview"
                                />
                                <FieldError>
                                    {form.error('branding_icon')}
                                </FieldError>
                            </Field>
                        </div>
                    </FieldGroup>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Primary contact</CardTitle>
                    <CardDescription>
                        Use the structured geo controls so agency contact details
                        stay consistent with the rest of the platform.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <FieldGroup>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field
                                data-invalid={
                                    form.invalid('phone_code') || undefined
                                }
                            >
                                <FieldLabel htmlFor="phone_code">
                                    Phone code
                                </FieldLabel>
                                <NativeSelect
                                    id="phone_code"
                                    name="phone_code"
                                    size="comfortable"
                                    value={form.data.phone_code}
                                    onChange={(event) =>
                                        form.setField(
                                            'phone_code',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={
                                        form.invalid('phone_code') || undefined
                                    }
                                    className="w-full"
                                >
                                    <NativeSelectOption value="">
                                        Select phone code
                                    </NativeSelectOption>
                                    {phoneCodeOptions.map((option) => (
                                        <NativeSelectOption
                                            key={`${option.label}-${option.value}`}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <FieldError>{form.error('phone_code')}</FieldError>
                            </Field>

                            <Field
                                data-invalid={form.invalid('phone') || undefined}
                            >
                                <FieldLabel htmlFor="phone">
                                    Phone number
                                </FieldLabel>
                                <Input
                                    id="phone"
                                    value={form.data.phone}
                                    placeholder="9876543210"
                                    onChange={(event) =>
                                        form.setField('phone', event.target.value)
                                    }
                                    onBlur={() => form.touch('phone')}
                                    aria-invalid={
                                        form.invalid('phone') || undefined
                                    }
                                />
                                <FieldError>{form.error('phone')}</FieldError>
                            </Field>
                        </div>

                        <Field
                            data-invalid={
                                form.invalid('address1') || undefined
                            }
                        >
                            <FieldLabel htmlFor="address1">
                                Street address
                            </FieldLabel>
                            <Textarea
                                id="address1"
                                value={form.data.address1}
                                rows={4}
                                placeholder="Enter the primary billing or business address"
                                onChange={(event) =>
                                    form.setField(
                                        'address1',
                                        event.target.value,
                                    )
                                }
                                onBlur={() => form.touch('address1')}
                                aria-invalid={
                                    form.invalid('address1') || undefined
                                }
                            />
                            <FieldError>{form.error('address1')}</FieldError>
                        </Field>

                        <div className="grid gap-4 md:grid-cols-2">
                            <Field
                                data-invalid={
                                    form.invalid('country_code') || undefined
                                }
                            >
                                <FieldLabel htmlFor="country_code">
                                    Country
                                </FieldLabel>
                                <CountrySelect
                                    value={form.data.country_code}
                                    onChange={(code, name) => {
                                        const countryChanged =
                                            code !== form.data.country_code;

                                        form.setField('country_code', code);
                                        form.setField('country', name);

                                        if (countryChanged) {
                                            form.setField('state_code', '');
                                            form.setField('state', '');
                                            form.setField('city_code', '');
                                            form.setField('city', '');
                                        }
                                    }}
                                    className="w-full"
                                    aria-invalid={
                                        form.invalid('country_code') ||
                                        undefined
                                    }
                                />
                                <FieldError>
                                    {form.error('country_code')}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('state_code') || undefined
                                }
                            >
                                <FieldLabel htmlFor="state_code">
                                    State / region
                                </FieldLabel>
                                <StateSelect
                                    countryCode={form.data.country_code}
                                    value={form.data.state_code}
                                    onChange={(code, name) => {
                                        const stateChanged =
                                            code !== form.data.state_code;

                                        form.setField('state_code', code);
                                        form.setField('state', name);

                                        if (stateChanged) {
                                            form.setField('city_code', '');
                                            form.setField('city', '');
                                        }
                                    }}
                                    className="w-full"
                                    aria-invalid={
                                        form.invalid('state_code') || undefined
                                    }
                                />
                                <FieldError>{form.error('state_code')}</FieldError>
                            </Field>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <Field
                                data-invalid={form.invalid('city') || undefined}
                            >
                                <FieldLabel htmlFor="city">City</FieldLabel>
                                <CitySelect
                                    countryCode={form.data.country_code}
                                    stateCode={form.data.state_code}
                                    value={form.data.city}
                                    onChange={(code, name) => {
                                        form.setField('city_code', code);
                                        form.setField('city', name);
                                    }}
                                    className="w-full"
                                    aria-invalid={
                                        form.invalid('city') || undefined
                                    }
                                />
                                <FieldError>{form.error('city')}</FieldError>
                            </Field>

                            <Field
                                data-invalid={form.invalid('zip') || undefined}
                            >
                                <FieldLabel htmlFor="zip">
                                    ZIP / postal code
                                </FieldLabel>
                                <Input
                                    id="zip"
                                    value={form.data.zip}
                                    onChange={(event) =>
                                        form.setField('zip', event.target.value)
                                    }
                                    onBlur={() => form.touch('zip')}
                                    aria-invalid={form.invalid('zip') || undefined}
                                />
                                <FieldError>{form.error('zip')}</FieldError>
                            </Field>
                        </div>
                    </FieldGroup>
                </CardContent>
            </Card>
        </div>
    );
}

export function AgencyFormSidebar({
    form,
    mode,
    statusOptions,
    websiteOptions,
    cancelUrl,
    canLinkAgencyWebsite,
    websiteIdExample,
}: AgencyFormSidebarProps) {
    return (
        <div className="flex flex-col gap-6 xl:sticky xl:top-6 xl:self-start">
            <Card>
                <CardHeader>
                    <CardTitle>Agency platform</CardTitle>
                    <CardDescription>
                        Review the defaults that will shape website IDs, SaaS
                        platform linkage, and webhook routing.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <FieldGroup>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-1">
                            <Field
                                data-invalid={
                                    form.invalid('website_id_prefix') ||
                                    undefined
                                }
                            >
                                <FieldLabel htmlFor="website_id_prefix">
                                    Website ID prefix
                                </FieldLabel>
                                <Input
                                    id="website_id_prefix"
                                    value={form.data.website_id_prefix}
                                    placeholder="WS"
                                    onChange={(event) =>
                                        form.setField(
                                            'website_id_prefix',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() =>
                                        form.touch('website_id_prefix')
                                    }
                                    aria-invalid={
                                        form.invalid('website_id_prefix') ||
                                        undefined
                                    }
                                />
                                <FieldDescription>
                                    Short alphanumeric prefix used for generated
                                    website IDs.
                                </FieldDescription>
                                <FieldError>
                                    {form.error('website_id_prefix')}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('website_id_zero_padding') ||
                                    undefined
                                }
                            >
                                <FieldLabel htmlFor="website_id_zero_padding">
                                    Zero padding
                                </FieldLabel>
                                <Input
                                    id="website_id_zero_padding"
                                    type="number"
                                    min={1}
                                    max={10}
                                    value={form.data.website_id_zero_padding}
                                    onChange={(event) =>
                                        form.setField(
                                            'website_id_zero_padding',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() =>
                                        form.touch(
                                            'website_id_zero_padding',
                                        )
                                    }
                                    aria-invalid={
                                        form.invalid('website_id_zero_padding') ||
                                        undefined
                                    }
                                />
                                <FieldDescription>
                                    Website ID format preview:{' '}
                                    <span className="font-medium text-foreground">
                                        {websiteIdExample}
                                    </span>
                                </FieldDescription>
                                <FieldError>
                                    {form.error('website_id_zero_padding')}
                                </FieldError>
                            </Field>
                        </div>

                        {canLinkAgencyWebsite ? (
                            <SelectField
                                label="Agency website"
                                placeholder="Select agency website"
                                value={form.data.agency_website_id}
                                options={websiteOptions}
                                error={form.error('agency_website_id')}
                                invalid={form.invalid('agency_website_id')}
                                onChange={(value) =>
                                    form.setField('agency_website_id', value)
                                }
                                noneOptionLabel="No linked website"
                                description="Secret key is generated automatically when an agency website is linked or changed."
                            />
                        ) : (
                            <div className="rounded-xl border border-dashed border-border/70 bg-muted/20 p-4 text-sm leading-6 text-muted-foreground">
                                Save the agency first to link its SaaS platform
                                website and enable the shared API secret.
                            </div>
                        )}

                        <Field
                            data-invalid={
                                form.invalid('webhook_url') || undefined
                            }
                        >
                            <FieldLabel htmlFor="webhook_url">
                                Webhook URL
                            </FieldLabel>
                            <Input
                                id="webhook_url"
                                value={form.data.webhook_url}
                                onChange={(event) =>
                                    form.setField(
                                        'webhook_url',
                                        event.target.value,
                                    )
                                }
                                onBlur={() => form.touch('webhook_url')}
                                aria-invalid={
                                    form.invalid('webhook_url') || undefined
                                }
                                placeholder="https://agency.example.com/api/agency/v1/webhooks/platform"
                            />
                            <FieldDescription>
                                Provisioning updates and lifecycle events are
                                posted to this endpoint.
                            </FieldDescription>
                            <FieldError>{form.error('webhook_url')}</FieldError>
                        </Field>
                    </FieldGroup>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Status</CardTitle>
                    <CardDescription>
                        Control whether the agency is active for operational
                        use.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <FieldGroup>
                        {mode === 'create' ? (
                            <div className="rounded-xl border border-border/70 bg-muted/20 p-4 text-sm leading-6 text-muted-foreground">
                                New agencies start as{' '}
                                <span className="font-medium text-foreground">
                                    Active
                                </span>
                                . You can adjust the status after the initial
                                record is created.
                            </div>
                        ) : (
                            <SelectField
                                label="Status"
                                placeholder="Select status"
                                value={form.data.status}
                                options={statusOptions}
                                error={form.error('status')}
                                invalid={form.invalid('status')}
                                onChange={(value) =>
                                    form.setField('status', value)
                                }
                            />
                        )}

                        <Field
                            orientation="horizontal"
                            className="items-start justify-between gap-4 rounded-lg border p-3"
                        >
                            <FieldContent>
                                <FieldLabel>White-label readiness</FieldLabel>
                                <FieldDescription>
                                    Configure branding, webhook URL, and agency
                                    website before onboarding reseller traffic.
                                </FieldDescription>
                            </FieldContent>
                            <span className="rounded-full border bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
                                {form.data.plan || 'starter'}
                            </span>
                        </Field>
                    </FieldGroup>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>
                        {mode === 'create' ? 'Create agency' : 'Save changes'}
                    </CardTitle>
                    <CardDescription>
                        Review the setup and submit when everything looks
                        correct.
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
                        {mode === 'create' ? 'Create agency' : 'Save changes'}
                    </Button>

                    <Button variant="outline" asChild className="w-full">
                        <Link href={cancelUrl}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Cancel
                        </Link>
                    </Button>
                </CardFooter>
            </Card>
        </div>
    );
}
