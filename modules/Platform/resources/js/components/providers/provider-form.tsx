import { Link, useHttp } from '@inertiajs/react';
import { ArrowLeftIcon, SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
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
import { useAppForm } from '@/hooks/use-app-form';
import type {
    PlatformOption,
    ProviderCredentialValues,
    ProviderFormValues,
} from '../../types/platform';

type ProviderFormProps = {
    mode: 'create' | 'edit';
    provider?: {
        id: number;
        name: string;
    };
    initialValues: ProviderFormValues;
    typeOptions: PlatformOption[];
    vendorOptions: PlatformOption[];
    statusOptions: PlatformOption[];
};

function credentialError(
    errors: Record<string, unknown>,
    key: keyof ProviderCredentialValues,
): string | undefined {
    const value = errors[`credentials.${key}`];

    return typeof value === 'string' ? value : undefined;
}

export default function ProviderForm({
    mode,
    provider,
    initialValues,
    typeOptions,
    vendorOptions,
    statusOptions,
}: ProviderFormProps) {
    const form = useAppForm<ProviderFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'platform.providers.create'
                : `platform.providers.edit.${provider?.id ?? 'new'}`,
        dirtyGuard: true,
    });
    const [vendorOptionsByType, setVendorOptionsByType] = useState<
        Record<string, PlatformOption[]>
    >({});
    const vendorRequest = useHttp<
        Record<string, never>,
        {
            success?: boolean;
            vendors?: PlatformOption[];
        }
    >({});

    const availableVendorOptions = form.data.type
        ? (vendorOptionsByType[form.data.type] ??
          (form.data.type === initialValues.type ? vendorOptions : []))
        : vendorOptions;

    useEffect(() => {
        let isMounted = true;
        const selectedType = form.data.type;

        if (!selectedType || vendorOptionsByType[selectedType]) {
            return () => {
                isMounted = false;
            };
        }

        const controller = new AbortController();

        void vendorRequest
            .get(route('platform.providers.api.vendors', { type: selectedType }), {
                headers: { Accept: 'application/json' },
                onCancelToken: (cancelToken) => {
                    controller.signal.addEventListener('abort', () => {
                        cancelToken.cancel();
                    });
                },
            })
            .then((payload) => {
                if (!isMounted || !Array.isArray(payload.vendors)) {
                    return;
                }

                setVendorOptionsByType((current) => ({
                    ...current,
                    [selectedType]: payload.vendors!,
                }));

                if (
                    form.data.vendor &&
                    !payload.vendors.some(
                        (option) =>
                            String(option.value) === String(form.data.vendor),
                    )
                ) {
                    form.setField('vendor', '');
                }
            })
            .catch(() => undefined);

        return () => {
            isMounted = false;
            controller.abort();
            vendorRequest.cancel();
        };
    }, [form, form.data.type, vendorOptionsByType, vendorRequest]);

    const setCredential = (
        key: keyof ProviderCredentialValues,
        value: string,
    ) => {
        form.setField('credentials', {
            ...form.data.credentials,
            [key]: value,
        });
    };

    const submitUrl =
        mode === 'create'
            ? route('platform.providers.store')
            : route('platform.providers.update', provider!.id);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(mode === 'create' ? 'post' : 'put', submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast:
                mode === 'create'
                    ? 'Provider created successfully.'
                    : 'Provider updated successfully.',
        });
    };

    return (
        <form
            className="flex flex-col gap-6"
            onSubmit={handleSubmit}
            noValidate
        >
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Provider profile</CardTitle>
                            <CardDescription>
                                Define the upstream service, vendor, and
                                lifecycle state for this account.
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
                                            Provider name
                                        </FieldLabel>
                                        <Input
                                            id="name"
                                            value={form.data.name}
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
                                        <FieldError>
                                            {form.error('name')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('email') || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="email">
                                            Contact email
                                        </FieldLabel>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={form.data.email}
                                            onChange={(event) =>
                                                form.setField(
                                                    'email',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() => form.touch('email')}
                                            aria-invalid={
                                                form.invalid('email') ||
                                                undefined
                                            }
                                        />
                                        <FieldError>
                                            {form.error('email')}
                                        </FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-3">
                                    <Field
                                        data-invalid={
                                            form.invalid('type') || undefined
                                        }
                                    >
                                        <FieldLabel>Provider type</FieldLabel>
                                        <Select
                                            value={form.data.type || undefined}
                                            onValueChange={(value) =>
                                                form.setField('type', value)
                                            }
                                        >
                                            <SelectTrigger
                                                className="w-full"
                                                aria-invalid={
                                                    form.invalid('type') ||
                                                    undefined
                                                }
                                            >
                                                <SelectValue placeholder="Select type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {typeOptions.map(
                                                        (option) => (
                                                            <SelectItem
                                                                key={String(
                                                                    option.value,
                                                                )}
                                                                value={String(
                                                                    option.value,
                                                                )}
                                                            >
                                                                {option.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>
                                            {form.error('type')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('vendor') || undefined
                                        }
                                    >
                                        <FieldLabel>Vendor</FieldLabel>
                                        <Select
                                            value={
                                                form.data.vendor || undefined
                                            }
                                            onValueChange={(value) =>
                                                form.setField('vendor', value)
                                            }
                                        >
                                            <SelectTrigger
                                                className="w-full"
                                                aria-invalid={
                                                    form.invalid('vendor') ||
                                                    undefined
                                                }
                                            >
                                                <SelectValue placeholder="Select vendor" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {availableVendorOptions.map(
                                                        (option) => (
                                                            <SelectItem
                                                                key={String(
                                                                    option.value,
                                                                )}
                                                                value={String(
                                                                    option.value,
                                                                )}
                                                            >
                                                                {option.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldDescription>
                                            Vendor options update when you
                                            change the provider type.
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('vendor')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('status') || undefined
                                        }
                                    >
                                        <FieldLabel>Status</FieldLabel>
                                        <Select
                                            value={
                                                form.data.status || undefined
                                            }
                                            onValueChange={(value) =>
                                                form.setField('status', value)
                                            }
                                        >
                                            <SelectTrigger
                                                className="w-full"
                                                aria-invalid={
                                                    form.invalid('status') ||
                                                    undefined
                                                }
                                            >
                                                <SelectValue placeholder="Select status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {statusOptions.map(
                                                        (option) => (
                                                            <SelectItem
                                                                key={String(
                                                                    option.value,
                                                                )}
                                                                value={String(
                                                                    option.value,
                                                                )}
                                                            >
                                                                {option.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>
                                            {form.error('status')}
                                        </FieldError>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Credentials</CardTitle>
                            <CardDescription>
                                Save the vendor API keys and identifiers needed
                                for sync and provisioning tasks.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field
                                        data-invalid={
                                            Boolean(
                                                credentialError(
                                                    form.errors,
                                                    'api_key',
                                                ),
                                            ) || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="credentials_api_key">
                                            API key
                                        </FieldLabel>
                                        <Input
                                            id="credentials_api_key"
                                            value={
                                                form.data.credentials.api_key
                                            }
                                            onChange={(event) =>
                                                setCredential(
                                                    'api_key',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={
                                                Boolean(
                                                    credentialError(
                                                        form.errors,
                                                        'api_key',
                                                    ),
                                                ) || undefined
                                            }
                                        />
                                        <FieldError>
                                            {credentialError(
                                                form.errors,
                                                'api_key',
                                            )}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            Boolean(
                                                credentialError(
                                                    form.errors,
                                                    'api_token',
                                                ),
                                            ) || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="credentials_api_token">
                                            API token
                                        </FieldLabel>
                                        <Input
                                            id="credentials_api_token"
                                            value={
                                                form.data.credentials.api_token
                                            }
                                            onChange={(event) =>
                                                setCredential(
                                                    'api_token',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={
                                                Boolean(
                                                    credentialError(
                                                        form.errors,
                                                        'api_token',
                                                    ),
                                                ) || undefined
                                            }
                                        />
                                        <FieldError>
                                            {credentialError(
                                                form.errors,
                                                'api_token',
                                            )}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            Boolean(
                                                credentialError(
                                                    form.errors,
                                                    'api_secret',
                                                ),
                                            ) || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="credentials_api_secret">
                                            API secret
                                        </FieldLabel>
                                        <Input
                                            id="credentials_api_secret"
                                            type="password"
                                            value={
                                                form.data.credentials.api_secret
                                            }
                                            onChange={(event) =>
                                                setCredential(
                                                    'api_secret',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={
                                                Boolean(
                                                    credentialError(
                                                        form.errors,
                                                        'api_secret',
                                                    ),
                                                ) || undefined
                                            }
                                        />
                                        <FieldError>
                                            {credentialError(
                                                form.errors,
                                                'api_secret',
                                            )}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            Boolean(
                                                credentialError(
                                                    form.errors,
                                                    'account_id',
                                                ),
                                            ) || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="credentials_account_id">
                                            Account ID
                                        </FieldLabel>
                                        <Input
                                            id="credentials_account_id"
                                            value={
                                                form.data.credentials.account_id
                                            }
                                            onChange={(event) =>
                                                setCredential(
                                                    'account_id',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={
                                                Boolean(
                                                    credentialError(
                                                        form.errors,
                                                        'account_id',
                                                    ),
                                                ) || undefined
                                            }
                                        />
                                        <FieldError>
                                            {credentialError(
                                                form.errors,
                                                'account_id',
                                            )}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            Boolean(
                                                credentialError(
                                                    form.errors,
                                                    'api_user',
                                                ),
                                            ) || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="credentials_api_user">
                                            API user
                                        </FieldLabel>
                                        <Input
                                            id="credentials_api_user"
                                            value={
                                                form.data.credentials.api_user
                                            }
                                            onChange={(event) =>
                                                setCredential(
                                                    'api_user',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={
                                                Boolean(
                                                    credentialError(
                                                        form.errors,
                                                        'api_user',
                                                    ),
                                                ) || undefined
                                            }
                                        />
                                        <FieldError>
                                            {credentialError(
                                                form.errors,
                                                'api_user',
                                            )}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            Boolean(
                                                credentialError(
                                                    form.errors,
                                                    'username',
                                                ),
                                            ) || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="credentials_username">
                                            Username
                                        </FieldLabel>
                                        <Input
                                            id="credentials_username"
                                            value={
                                                form.data.credentials.username
                                            }
                                            onChange={(event) =>
                                                setCredential(
                                                    'username',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={
                                                Boolean(
                                                    credentialError(
                                                        form.errors,
                                                        'username',
                                                    ),
                                                ) || undefined
                                            }
                                        />
                                        <FieldError>
                                            {credentialError(
                                                form.errors,
                                                'username',
                                            )}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            Boolean(
                                                credentialError(
                                                    form.errors,
                                                    'zone_id',
                                                ),
                                            ) || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="credentials_zone_id">
                                            Zone ID
                                        </FieldLabel>
                                        <Input
                                            id="credentials_zone_id"
                                            value={
                                                form.data.credentials.zone_id
                                            }
                                            onChange={(event) =>
                                                setCredential(
                                                    'zone_id',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={
                                                Boolean(
                                                    credentialError(
                                                        form.errors,
                                                        'zone_id',
                                                    ),
                                                ) || undefined
                                            }
                                        />
                                        <FieldError>
                                            {credentialError(
                                                form.errors,
                                                'zone_id',
                                            )}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            Boolean(
                                                credentialError(
                                                    form.errors,
                                                    'client_ip',
                                                ),
                                            ) || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="credentials_client_ip">
                                            Client IP
                                        </FieldLabel>
                                        <Input
                                            id="credentials_client_ip"
                                            value={
                                                form.data.credentials.client_ip
                                            }
                                            onChange={(event) =>
                                                setCredential(
                                                    'client_ip',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={
                                                Boolean(
                                                    credentialError(
                                                        form.errors,
                                                        'client_ip',
                                                    ),
                                                ) || undefined
                                            }
                                        />
                                        <FieldError>
                                            {credentialError(
                                                form.errors,
                                                'client_ip',
                                            )}
                                        </FieldError>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button variant="outline" asChild>
                    <Link
                        href={route('platform.providers.index', {
                            status: 'all',
                        })}
                    >
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to providers
                    </Link>
                </Button>

                <Button type="submit" disabled={form.processing}>
                    {form.processing ? (
                        <Spinner data-icon="inline-start" />
                    ) : (
                        <SaveIcon data-icon="inline-start" />
                    )}
                    {mode === 'create' ? 'Create provider' : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}
