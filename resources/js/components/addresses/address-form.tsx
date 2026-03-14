import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, MapPinIcon, SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { CitySelect } from '@/components/geo/city-select';
import { CountrySelect } from '@/components/geo/country-select';
import { StateSelect } from '@/components/geo/state-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAppForm } from '@/hooks/use-app-form';
import type {
    AddressEditTarget,
    AddressFormValues,
    AddressTypeOption,
} from '@/types/address';

type AddressFormProps = {
    mode: 'create' | 'edit';
    address?: AddressEditTarget;
    initialValues: AddressFormValues;
    typeOptions: AddressTypeOption[];
};

export default function AddressForm({
    mode,
    address,
    initialValues,
    typeOptions,
}: AddressFormProps) {
    const form = useAppForm<AddressFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'addresses.create'
                : `addresses.edit.${address?.id ?? 'new'}`,
        dirtyGuard: true,
    });

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('app.masters.addresses.store')
            : route('app.masters.addresses.update', address!.id);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast:
                mode === 'create' ? 'Address created.' : 'Address saved.',
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
                {/* ── Contact & Type ─────────────────────────────────── */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <MapPinIcon className="size-4 text-muted-foreground" />
                            <CardTitle>Contact details</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <FieldGroup>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    data-invalid={
                                        form.invalid('first_name') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="first_name">
                                        First name
                                    </FieldLabel>
                                    <Input
                                        id="first_name"
                                        value={form.data.first_name}
                                        onChange={(e) =>
                                            form.setField(
                                                'first_name',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('first_name')}
                                        aria-invalid={
                                            form.invalid('first_name') ||
                                            undefined
                                        }
                                    />
                                    <FieldError>
                                        {form.error('first_name')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('last_name') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="last_name">
                                        Last name
                                    </FieldLabel>
                                    <Input
                                        id="last_name"
                                        value={form.data.last_name}
                                        onChange={(e) =>
                                            form.setField(
                                                'last_name',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('last_name')}
                                        aria-invalid={
                                            form.invalid('last_name') ||
                                            undefined
                                        }
                                    />
                                    <FieldError>
                                        {form.error('last_name')}
                                    </FieldError>
                                </Field>
                            </div>

                            <Field
                                data-invalid={
                                    form.invalid('company') || undefined
                                }
                            >
                                <FieldLabel htmlFor="company">
                                    Company
                                </FieldLabel>
                                <Input
                                    id="company"
                                    value={form.data.company}
                                    onChange={(e) =>
                                        form.setField('company', e.target.value)
                                    }
                                    onBlur={() => form.touch('company')}
                                    aria-invalid={
                                        form.invalid('company') || undefined
                                    }
                                />
                                <FieldError>{form.error('company')}</FieldError>
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    data-invalid={
                                        form.invalid('phone_code') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="phone_code">
                                        Phone code
                                    </FieldLabel>
                                    <Input
                                        id="phone_code"
                                        placeholder="+1"
                                        value={form.data.phone_code}
                                        onChange={(e) =>
                                            form.setField(
                                                'phone_code',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('phone_code')}
                                        aria-invalid={
                                            form.invalid('phone_code') ||
                                            undefined
                                        }
                                    />
                                    <FieldError>
                                        {form.error('phone_code')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('phone') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="phone">
                                        Phone
                                    </FieldLabel>
                                    <Input
                                        id="phone"
                                        type="tel"
                                        value={form.data.phone}
                                        onChange={(e) =>
                                            form.setField(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('phone')}
                                        aria-invalid={
                                            form.invalid('phone') || undefined
                                        }
                                    />
                                    <FieldError>
                                        {form.error('phone')}
                                    </FieldError>
                                </Field>
                            </div>

                            <Field
                                data-invalid={form.invalid('type') || undefined}
                            >
                                <FieldSet>
                                    <FieldLegend>Address type</FieldLegend>
                                    <FieldDescription>
                                        Choose how this address should be used.
                                    </FieldDescription>
                                    <ToggleGroup
                                        type="single"
                                        value={form.data.type}
                                        onValueChange={(value) => {
                                            if (value === '') {
                                                return;
                                            }

                                            form.setField('type', value);
                                        }}
                                        variant="outline"
                                        className="w-full flex-wrap"
                                        aria-invalid={
                                            form.invalid('type') || undefined
                                        }
                                    >
                                        {typeOptions.map((option) => (
                                            <ToggleGroupItem
                                                key={option.value}
                                                value={option.value}
                                                className="min-w-[8rem] flex-1"
                                            >
                                                {option.label}
                                            </ToggleGroupItem>
                                        ))}
                                    </ToggleGroup>
                                </FieldSet>
                                <FieldError>{form.error('type')}</FieldError>
                            </Field>

                            <div className="flex flex-col gap-4 pt-2">
                                <Field orientation="horizontal">
                                    <FieldLabel htmlFor="is_primary">
                                        Primary address
                                    </FieldLabel>
                                    <FieldDescription>
                                        Mark as the default address.
                                    </FieldDescription>
                                    <Switch
                                        id="is_primary"
                                        checked={form.data.is_primary}
                                        onCheckedChange={(checked) =>
                                            form.setField('is_primary', checked)
                                        }
                                    />
                                </Field>

                                <Field orientation="horizontal">
                                    <FieldLabel htmlFor="is_verified">
                                        Verified
                                    </FieldLabel>
                                    <FieldDescription>
                                        Mark as a verified address.
                                    </FieldDescription>
                                    <Switch
                                        id="is_verified"
                                        checked={form.data.is_verified}
                                        onCheckedChange={(checked) =>
                                            form.setField(
                                                'is_verified',
                                                checked,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </FieldGroup>
                    </CardContent>
                </Card>

                {/* ── Location ───────────────────────────────────────── */}
                <Card>
                    <CardHeader>
                        <CardTitle>Location</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <FieldGroup>
                            <Field
                                data-invalid={
                                    form.invalid('address1') || undefined
                                }
                            >
                                <FieldLabel htmlFor="address1">
                                    Street address <span aria-hidden>*</span>
                                </FieldLabel>
                                <Input
                                    id="address1"
                                    value={form.data.address1}
                                    onChange={(e) =>
                                        form.setField(
                                            'address1',
                                            e.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('address1')}
                                    aria-invalid={
                                        form.invalid('address1') || undefined
                                    }
                                />
                                <FieldError>
                                    {form.error('address1')}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('address2') || undefined
                                }
                            >
                                <FieldLabel htmlFor="address2">
                                    Address line 2
                                </FieldLabel>
                                <Input
                                    id="address2"
                                    value={form.data.address2}
                                    onChange={(e) =>
                                        form.setField(
                                            'address2',
                                            e.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('address2')}
                                    aria-invalid={
                                        form.invalid('address2') || undefined
                                    }
                                />
                                <FieldError>
                                    {form.error('address2')}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('address3') || undefined
                                }
                            >
                                <FieldLabel htmlFor="address3">
                                    Landmark / address line 3
                                </FieldLabel>
                                <Input
                                    id="address3"
                                    value={form.data.address3}
                                    onChange={(e) =>
                                        form.setField(
                                            'address3',
                                            e.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('address3')}
                                    aria-invalid={
                                        form.invalid('address3') || undefined
                                    }
                                />
                                <FieldError>
                                    {form.error('address3')}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('country_code') || undefined
                                }
                            >
                                <FieldLabel>
                                    Country <span aria-hidden>*</span>
                                </FieldLabel>
                                <CountrySelect
                                    value={form.data.country_code}
                                    onChange={(code, name) => {
                                        form.setField('country_code', code);
                                        form.setField('country', name);
                                        // Reset dependent fields
                                        form.setField('state_code', '');
                                        form.setField('state', '');
                                        form.setField('city_code', '');
                                        form.setField('city', '');
                                    }}
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
                                <FieldLabel>State / Province</FieldLabel>
                                <StateSelect
                                    countryCode={form.data.country_code}
                                    value={form.data.state_code}
                                    onChange={(code, name) => {
                                        form.setField('state_code', code);
                                        form.setField('state', name);
                                        // Reset dependent city
                                        form.setField('city_code', '');
                                        form.setField('city', '');
                                    }}
                                    aria-invalid={
                                        form.invalid('state_code') || undefined
                                    }
                                />
                                <FieldError>
                                    {form.error('state_code')}
                                </FieldError>
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    data-invalid={
                                        form.invalid('city') || undefined
                                    }
                                >
                                    <FieldLabel>
                                        City <span aria-hidden>*</span>
                                    </FieldLabel>
                                    <CitySelect
                                        countryCode={form.data.country_code}
                                        stateCode={form.data.state_code}
                                        value={
                                            form.data.city_code ||
                                            form.data.city
                                        }
                                        onChange={(code, name) => {
                                            form.setField('city_code', code);
                                            form.setField('city', name);
                                        }}
                                        aria-invalid={
                                            form.invalid('city') || undefined
                                        }
                                    />
                                    <FieldError>
                                        {form.error('city')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('zip') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="zip">
                                        Postal code
                                    </FieldLabel>
                                    <Input
                                        id="zip"
                                        value={form.data.zip}
                                        onChange={(e) =>
                                            form.setField('zip', e.target.value)
                                        }
                                        onBlur={() => form.touch('zip')}
                                        aria-invalid={
                                            form.invalid('zip') || undefined
                                        }
                                    />
                                    <FieldError>{form.error('zip')}</FieldError>
                                </Field>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    data-invalid={
                                        form.invalid('latitude') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="latitude">
                                        Latitude
                                    </FieldLabel>
                                    <Input
                                        id="latitude"
                                        type="number"
                                        step="any"
                                        min="-90"
                                        max="90"
                                        value={form.data.latitude}
                                        onChange={(e) =>
                                            form.setField(
                                                'latitude',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('latitude')}
                                        aria-invalid={
                                            form.invalid('latitude') ||
                                            undefined
                                        }
                                    />
                                    <FieldError>
                                        {form.error('latitude')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('longitude') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="longitude">
                                        Longitude
                                    </FieldLabel>
                                    <Input
                                        id="longitude"
                                        type="number"
                                        step="any"
                                        min="-180"
                                        max="180"
                                        value={form.data.longitude}
                                        onChange={(e) =>
                                            form.setField(
                                                'longitude',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('longitude')}
                                        aria-invalid={
                                            form.invalid('longitude') ||
                                            undefined
                                        }
                                    />
                                    <FieldError>
                                        {form.error('longitude')}
                                    </FieldError>
                                </Field>
                            </div>
                        </FieldGroup>
                    </CardContent>
                </Card>
            </div>

            {/* ── Actions ──────────────────────────────────────────── */}
            <div className="flex items-center justify-between gap-4">
                <Button type="button" variant="outline" asChild>
                    <Link href={route('app.masters.addresses.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to addresses
                    </Link>
                </Button>

                <Button type="submit" disabled={form.processing}>
                    {form.processing ? (
                        <Spinner />
                    ) : (
                        <SaveIcon data-icon="inline-start" />
                    )}
                    {form.processing
                        ? 'Saving...'
                        : mode === 'create'
                          ? 'Create address'
                          : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}
