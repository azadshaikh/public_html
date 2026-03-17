import { Link } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    LockKeyholeIcon,
    SaveIcon,
    UploadIcon,
    UserCogIcon,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ChangeEvent, FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { CountrySelect } from '@/components/geo/country-select';
import { StateSelect } from '@/components/geo/state-select';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useAppForm } from '@/hooks/use-app-form';
import { useInitials } from '@/hooks/use-initials';
import { formValidators } from '@/lib/forms';
import type {
    ManagedUserEditingTarget,
    ManagedUserFormValues,
    ManagedUserGenderOption,
    ManagedUserRoleOption,
    ManagedUserStatusOption,
} from '@/types/user-management';

type ManagedUserFormProps = {
    mode: 'create' | 'edit';
    user?: ManagedUserEditingTarget;
    initialValues: ManagedUserFormValues;
    availableRoles: ManagedUserRoleOption[];
    statusOptions: ManagedUserStatusOption[];
    genderOptions: ManagedUserGenderOption[];
};

function deriveManagedUserName(values: {
    first_name: string;
    last_name: string;
    username: string;
    email: string;
}): string {
    const fullName = `${values.first_name} ${values.last_name}`.trim();

    return fullName || values.username.trim() || values.email.trim();
}

function RequiredLabel({
    htmlFor,
    children,
}: {
    htmlFor: string;
    children: string;
}) {
    return (
        <FieldLabel htmlFor={htmlFor}>
            {children}
            <span className="text-destructive"> *</span>
        </FieldLabel>
    );
}

export default function ManagedUserForm({
    mode,
    user,
    initialValues,
    availableRoles,
    statusOptions,
    genderOptions,
}: ManagedUserFormProps) {
    const getInitials = useInitials();
    const fileInputRef = useRef<HTMLInputElement>(null);
    const temporaryPreviewUrlRef = useRef<string | null>(null);
    const [temporaryAvatarPreviewUrl, setTemporaryAvatarPreviewUrl] = useState<
        string | null
    >(null);

    const form = useAppForm<ManagedUserFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create' ? 'users.create.form' : `users.edit.${user?.id}`,
        dontRemember: ['avatar'],
        dirtyGuard: { enabled: true },
        rules: {
            email: [
                formValidators.required('Email address'),
                formValidators.email(),
            ],
            username: [
                (value) => {
                    if (typeof value !== 'string' || value.trim() === '') {
                        return undefined;
                    }

                    return /^[a-zA-Z0-9_-]+$/.test(value)
                        ? undefined
                        : 'Username can only contain letters, numbers, underscores, and hyphens.';
                },
            ],
            status: [formValidators.required('Status')],
            password:
                mode === 'create'
                    ? [
                          formValidators.required('Password'),
                          formValidators.minLength('Password', 8),
                      ]
                    : [
                          (value) => {
                              if (
                                  typeof value !== 'string' ||
                                  value.length === 0
                              ) {
                                  return undefined;
                              }

                              return value.length >= 8
                                  ? undefined
                                  : 'Password must be at least 8 characters.';
                          },
                      ],
            password_confirmation: [
                (value, data) => {
                    const password = data.password.trim();
                    const confirmation =
                        typeof value === 'string' ? value.trim() : '';

                    if (password === '') {
                        return undefined;
                    }

                    if (confirmation === '') {
                        return 'Password confirmation is required.';
                    }

                    return password === confirmation
                        ? undefined
                        : 'Password confirmation does not match.';
                },
            ],
        },
    });

    const displayName = useMemo(
        () =>
            deriveManagedUserName({
                first_name: form.data.first_name,
                last_name: form.data.last_name,
                username: form.data.username,
                email: form.data.email,
            }),
        [
            form.data.email,
            form.data.first_name,
            form.data.last_name,
            form.data.username,
        ],
    );

    const avatarPreviewUrl =
        temporaryAvatarPreviewUrl ?? user?.avatar_url ?? null;

    useEffect(() => {
        return () => {
            if (temporaryPreviewUrlRef.current !== null) {
                URL.revokeObjectURL(temporaryPreviewUrlRef.current);
            }
        };
    }, []);

    const syncDerivedName = (
        nextValues: Partial<
            Pick<
                ManagedUserFormValues,
                'first_name' | 'last_name' | 'username' | 'email'
            >
        >,
    ) => {
        form.setField(
            'name',
            deriveManagedUserName({
                first_name: nextValues.first_name ?? form.data.first_name,
                last_name: nextValues.last_name ?? form.data.last_name,
                username: nextValues.username ?? form.data.username,
                email: nextValues.email ?? form.data.email,
            }),
        );
    };

    const toggleRole = (roleId: number, checked: boolean) => {
        if (checked) {
            if (form.data.roles.includes(roleId)) {
                return;
            }

            form.setField('roles', [...form.data.roles, roleId]);

            return;
        }

        form.setField(
            'roles',
            form.data.roles.filter((currentRoleId) => currentRoleId !== roleId),
        );
    };

    const handleAvatarChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.currentTarget.files?.[0] ?? null;

        if (temporaryPreviewUrlRef.current !== null) {
            URL.revokeObjectURL(temporaryPreviewUrlRef.current);
            temporaryPreviewUrlRef.current = null;
        }

        if (file === null) {
            setTemporaryAvatarPreviewUrl(null);
            form.setField('avatar', null);

            return;
        }

        const nextPreviewUrl = URL.createObjectURL(file);
        temporaryPreviewUrlRef.current = nextPreviewUrl;
        setTemporaryAvatarPreviewUrl(nextPreviewUrl);
        form.setField('avatar', file);
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(
            mode === 'create' ? 'post' : 'put',
            mode === 'create'
                ? route('app.users.store')
                : route('app.users.update', user!.id),
            {
                preserveScroll: true,
                setDefaultsOnSuccess: mode === 'edit',
                successToast: {
                    title: mode === 'create' ? 'User created' : 'User updated',
                    description:
                        mode === 'create'
                            ? 'The managed account has been created successfully.'
                            : 'The managed account has been updated successfully.',
                },
                onSuccess: () => {
                    if (temporaryPreviewUrlRef.current !== null) {
                        URL.revokeObjectURL(temporaryPreviewUrlRef.current);
                        temporaryPreviewUrlRef.current = null;
                    }

                    setTemporaryAvatarPreviewUrl(null);
                    form.setField('avatar', null);
                    form.setDefaults('avatar', null);

                    if (fileInputRef.current !== null) {
                        fileInputRef.current.value = '';
                    }
                },
            },
        );
    };

    return (
        <form
            noValidate
            className="flex flex-col gap-6"
            onSubmit={handleSubmit}
        >
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Basic Information</CardTitle>
                            <CardDescription>
                                Add core profile details and sign-in identity
                                for this account.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                <Field
                                    data-invalid={
                                        form.invalid('first_name') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="first_name">
                                        First Name
                                    </FieldLabel>
                                    <Input
                                        id="first_name"
                                        value={form.data.first_name}
                                        onChange={(event) => {
                                            form.setField(
                                                'first_name',
                                                event.target.value,
                                            );
                                            syncDerivedName({
                                                first_name: event.target.value,
                                            });
                                        }}
                                        onBlur={() => form.touch('first_name')}
                                        aria-invalid={
                                            form.invalid('first_name') ||
                                            undefined
                                        }
                                        placeholder="Enter first name"
                                    />
                                    <FieldDescription>
                                        User&apos;s first name.
                                    </FieldDescription>
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
                                        Last Name
                                    </FieldLabel>
                                    <Input
                                        id="last_name"
                                        value={form.data.last_name}
                                        onChange={(event) => {
                                            form.setField(
                                                'last_name',
                                                event.target.value,
                                            );
                                            syncDerivedName({
                                                last_name: event.target.value,
                                            });
                                        }}
                                        onBlur={() => form.touch('last_name')}
                                        aria-invalid={
                                            form.invalid('last_name') ||
                                            undefined
                                        }
                                        placeholder="Enter last name"
                                    />
                                    <FieldDescription>
                                        User&apos;s last name.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('last_name')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>

                            <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                <Field
                                    data-invalid={
                                        form.invalid('email') || undefined
                                    }
                                >
                                    <RequiredLabel htmlFor="email">
                                        Email Address
                                    </RequiredLabel>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={form.data.email}
                                        onChange={(event) => {
                                            form.setField(
                                                'email',
                                                event.target.value,
                                            );
                                            syncDerivedName({
                                                email: event.target.value,
                                            });
                                        }}
                                        onBlur={() => form.touch('email')}
                                        aria-invalid={
                                            form.invalid('email') || undefined
                                        }
                                        placeholder="user@example.com"
                                    />
                                    <FieldDescription>
                                        Primary email address for login and
                                        communications.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('email')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('username') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="username">
                                        Username
                                    </FieldLabel>
                                    <Input
                                        id="username"
                                        value={form.data.username}
                                        onChange={(event) => {
                                            form.setField(
                                                'username',
                                                event.target.value,
                                            );
                                            syncDerivedName({
                                                username: event.target.value,
                                            });
                                        }}
                                        onBlur={() => form.touch('username')}
                                        aria-invalid={
                                            form.invalid('username') ||
                                            undefined
                                        }
                                        placeholder="Enter username"
                                    />
                                    <FieldDescription>
                                        Unique username for login (letters,
                                        numbers, underscores, and hyphens only).
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('username')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Contact Information</CardTitle>
                            <CardDescription>
                                Capture the user&apos;s address and contact
                                details.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('address1') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="address1">
                                        Address Line 1
                                    </FieldLabel>
                                    <Input
                                        id="address1"
                                        value={form.data.address1}
                                        onChange={(event) =>
                                            form.setField(
                                                'address1',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('address1')}
                                        aria-invalid={
                                            form.invalid('address1') ||
                                            undefined
                                        }
                                        placeholder="Enter street address"
                                    />
                                    <FieldDescription>
                                        Street address for the user.
                                    </FieldDescription>
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
                                        Address Line 2
                                    </FieldLabel>
                                    <Input
                                        id="address2"
                                        value={form.data.address2}
                                        onChange={(event) =>
                                            form.setField(
                                                'address2',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('address2')}
                                        aria-invalid={
                                            form.invalid('address2') ||
                                            undefined
                                        }
                                        placeholder="Apartment, suite, unit, building, floor, etc."
                                    />
                                    <FieldDescription>
                                        Additional address details.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('address2')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>

                            <FieldGroup className="lg:grid lg:grid-cols-3 lg:gap-6">
                                <Field
                                    data-invalid={
                                        form.invalid('city') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="city">City</FieldLabel>
                                    <Input
                                        id="city"
                                        value={form.data.city}
                                        onChange={(event) => {
                                            form.setField(
                                                'city',
                                                event.target.value,
                                            );
                                            form.setField('city_code', '');
                                        }}
                                        onBlur={() => form.touch('city')}
                                        aria-invalid={
                                            form.invalid('city') || undefined
                                        }
                                        placeholder="Enter city"
                                    />
                                    <FieldDescription>
                                        City of residence.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('city')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('state_code') || undefined
                                    }
                                >
                                    <FieldLabel>State/Province</FieldLabel>
                                    <StateSelect
                                        countryCode={form.data.country_code}
                                        value={form.data.state_code}
                                        onChange={(code, name) => {
                                            form.setField('state_code', code);
                                            form.setField('state', name);
                                        }}
                                        aria-invalid={
                                            form.invalid('state_code') ||
                                            undefined
                                        }
                                        className="w-full"
                                        placeholder="Select state"
                                    />
                                    <FieldDescription>
                                        State or province.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('state_code')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('zip') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="zip">
                                        Postcode
                                    </FieldLabel>
                                    <Input
                                        id="zip"
                                        value={form.data.zip}
                                        onChange={(event) =>
                                            form.setField(
                                                'zip',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('zip')}
                                        aria-invalid={
                                            form.invalid('zip') || undefined
                                        }
                                        placeholder="Enter postcode"
                                    />
                                    <FieldDescription>
                                        ZIP or postal code.
                                    </FieldDescription>
                                    <FieldError>{form.error('zip')}</FieldError>
                                </Field>
                            </FieldGroup>

                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('country_code') ||
                                        undefined
                                    }
                                >
                                    <FieldLabel>Country</FieldLabel>
                                    <CountrySelect
                                        value={form.data.country_code}
                                        onChange={(code, name) => {
                                            form.setField('country_code', code);
                                            form.setField('country', name);
                                            form.setField('state_code', '');
                                            form.setField('state', '');
                                        }}
                                        aria-invalid={
                                            form.invalid('country_code') ||
                                            undefined
                                        }
                                        className="w-full"
                                        placeholder="Select country"
                                    />
                                    <FieldDescription>
                                        Country of residence.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('country_code')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('phone') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="phone">
                                        Phone Number
                                    </FieldLabel>
                                    <Input
                                        id="phone"
                                        type="tel"
                                        value={form.data.phone}
                                        onChange={(event) =>
                                            form.setField(
                                                'phone',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('phone')}
                                        aria-invalid={
                                            form.invalid('phone') || undefined
                                        }
                                        placeholder="Enter phone number"
                                    />
                                    <FieldDescription>
                                        Phone number for contact.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('phone')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Personal Information</CardTitle>
                            <CardDescription>
                                Add optional personal details for the profile.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                <Field
                                    data-invalid={
                                        form.invalid('birth_date') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="birth_date">
                                        Date of Birth
                                    </FieldLabel>
                                    <Input
                                        id="birth_date"
                                        type="date"
                                        value={form.data.birth_date}
                                        onChange={(event) =>
                                            form.setField(
                                                'birth_date',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('birth_date')}
                                        aria-invalid={
                                            form.invalid('birth_date') ||
                                            undefined
                                        }
                                    />
                                    <FieldError>
                                        {form.error('birth_date')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('gender') || undefined
                                    }
                                >
                                    <FieldLabel>Gender</FieldLabel>
                                    <Select
                                        value={form.data.gender || undefined}
                                        onValueChange={(value) =>
                                            form.setField(
                                                'gender',
                                                value as ManagedUserFormValues['gender'],
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            className="w-full"
                                            aria-invalid={
                                                form.invalid('gender') ||
                                                undefined
                                            }
                                        >
                                            <SelectValue placeholder="Select Gender" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {genderOptions
                                                    .filter(
                                                        (option) =>
                                                            option.value !== '',
                                                    )
                                                    .map((option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <FieldDescription>
                                        Gender selection.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('gender')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Profile Information</CardTitle>
                            <CardDescription>
                                Add optional profile copy shown elsewhere in the
                                application.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <Field
                                data-invalid={
                                    form.invalid('tagline') || undefined
                                }
                            >
                                <FieldLabel htmlFor="tagline">
                                    Tagline
                                </FieldLabel>
                                <Input
                                    id="tagline"
                                    value={form.data.tagline}
                                    onChange={(event) =>
                                        form.setField(
                                            'tagline',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('tagline')}
                                    aria-invalid={
                                        form.invalid('tagline') || undefined
                                    }
                                    placeholder="Enter a short tagline"
                                />
                                <FieldDescription>
                                    A short tagline or headline for the profile.
                                </FieldDescription>
                                <FieldError>{form.error('tagline')}</FieldError>
                            </Field>

                            <Field
                                data-invalid={form.invalid('bio') || undefined}
                            >
                                <FieldLabel htmlFor="bio">Bio</FieldLabel>
                                <Textarea
                                    id="bio"
                                    rows={4}
                                    value={form.data.bio}
                                    onChange={(event) =>
                                        form.setField('bio', event.target.value)
                                    }
                                    onBlur={() => form.touch('bio')}
                                    aria-invalid={
                                        form.invalid('bio') || undefined
                                    }
                                    placeholder="Tell us about yourself"
                                />
                                <FieldDescription>
                                    A brief biography or description.
                                </FieldDescription>
                                <FieldError>{form.error('bio')}</FieldError>
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Social Links</CardTitle>
                            <CardDescription>
                                Add optional public profile links.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                <Field
                                    data-invalid={
                                        form.invalid('website_url') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="website_url">
                                        Website URL
                                    </FieldLabel>
                                    <Input
                                        id="website_url"
                                        type="url"
                                        value={form.data.website_url}
                                        onChange={(event) =>
                                            form.setField(
                                                'website_url',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('website_url')}
                                        aria-invalid={
                                            form.invalid('website_url') ||
                                            undefined
                                        }
                                        placeholder="https://example.com"
                                    />
                                    <FieldDescription>
                                        Personal or professional website URL.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('website_url')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('twitter_url') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="twitter_url">
                                        X (Twitter) URL
                                    </FieldLabel>
                                    <Input
                                        id="twitter_url"
                                        type="url"
                                        value={form.data.twitter_url}
                                        onChange={(event) =>
                                            form.setField(
                                                'twitter_url',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('twitter_url')}
                                        aria-invalid={
                                            form.invalid('twitter_url') ||
                                            undefined
                                        }
                                        placeholder="https://x.com/username"
                                    />
                                    <FieldDescription>
                                        X (formerly Twitter) profile URL.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('twitter_url')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>

                            <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                <Field
                                    data-invalid={
                                        form.invalid('facebook_url') ||
                                        undefined
                                    }
                                >
                                    <FieldLabel htmlFor="facebook_url">
                                        Facebook URL
                                    </FieldLabel>
                                    <Input
                                        id="facebook_url"
                                        type="url"
                                        value={form.data.facebook_url}
                                        onChange={(event) =>
                                            form.setField(
                                                'facebook_url',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            form.touch('facebook_url')
                                        }
                                        aria-invalid={
                                            form.invalid('facebook_url') ||
                                            undefined
                                        }
                                        placeholder="https://facebook.com/username"
                                    />
                                    <FieldError>
                                        {form.error('facebook_url')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('instagram_url') ||
                                        undefined
                                    }
                                >
                                    <FieldLabel htmlFor="instagram_url">
                                        Instagram URL
                                    </FieldLabel>
                                    <Input
                                        id="instagram_url"
                                        type="url"
                                        value={form.data.instagram_url}
                                        onChange={(event) =>
                                            form.setField(
                                                'instagram_url',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            form.touch('instagram_url')
                                        }
                                        aria-invalid={
                                            form.invalid('instagram_url') ||
                                            undefined
                                        }
                                        placeholder="https://instagram.com/username"
                                    />
                                    <FieldError>
                                        {form.error('instagram_url')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>

                            <Field
                                data-invalid={
                                    form.invalid('linkedin_url') || undefined
                                }
                            >
                                <FieldLabel htmlFor="linkedin_url">
                                    LinkedIn URL
                                </FieldLabel>
                                <Input
                                    id="linkedin_url"
                                    type="url"
                                    value={form.data.linkedin_url}
                                    onChange={(event) =>
                                        form.setField(
                                            'linkedin_url',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('linkedin_url')}
                                    aria-invalid={
                                        form.invalid('linkedin_url') ||
                                        undefined
                                    }
                                    placeholder="https://linkedin.com/in/username"
                                />
                                <FieldError>
                                    {form.error('linkedin_url')}
                                </FieldError>
                            </Field>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Account Settings</CardTitle>
                            <CardDescription>
                                Set the initial account state and credentials.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <Field
                                data-invalid={
                                    form.invalid('status') || undefined
                                }
                            >
                                <RequiredLabel htmlFor="status">
                                    Status
                                </RequiredLabel>
                                <Select
                                    value={form.data.status}
                                    onValueChange={(value) =>
                                        form.setField(
                                            'status',
                                            value as ManagedUserFormValues['status'],
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="status"
                                        className="w-full"
                                        aria-invalid={
                                            form.invalid('status') || undefined
                                        }
                                    >
                                        <SelectValue placeholder="Choose status..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {statusOptions.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <FieldDescription>
                                    {mode === 'create'
                                        ? 'Initial account status.'
                                        : 'Current account status.'}
                                </FieldDescription>
                                <FieldError>{form.error('status')}</FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('password') || undefined
                                }
                            >
                                <RequiredLabel htmlFor="password">
                                    {mode === 'create'
                                        ? 'Password'
                                        : 'New Password'}
                                </RequiredLabel>
                                <Input
                                    id="password"
                                    type="password"
                                    value={form.data.password}
                                    onChange={(event) =>
                                        form.setField(
                                            'password',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('password')}
                                    aria-invalid={
                                        form.invalid('password') || undefined
                                    }
                                    autoComplete="new-password"
                                    placeholder={
                                        mode === 'create'
                                            ? 'Enter password'
                                            : 'Leave blank to keep current password'
                                    }
                                />
                                <FieldDescription>
                                    {mode === 'create'
                                        ? 'Minimum 8 characters long.'
                                        : 'Leave blank to keep the current password. Minimum 8 characters if changing.'}
                                </FieldDescription>
                                <FieldError>
                                    {form.error('password')}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('password_confirmation') ||
                                    undefined
                                }
                            >
                                <RequiredLabel htmlFor="password_confirmation">
                                    Confirm Password
                                </RequiredLabel>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={form.data.password_confirmation}
                                    onChange={(event) =>
                                        form.setField(
                                            'password_confirmation',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() =>
                                        form.touch('password_confirmation')
                                    }
                                    aria-invalid={
                                        form.invalid('password_confirmation') ||
                                        undefined
                                    }
                                    autoComplete="new-password"
                                    placeholder="Confirm password"
                                />
                                <FieldDescription>
                                    Re-enter the password for confirmation.
                                </FieldDescription>
                                <FieldError>
                                    {form.error('password_confirmation')}
                                </FieldError>
                            </Field>

                            {user ? (
                                <div className="rounded-xl border border-dashed bg-muted/20 p-4 text-sm text-muted-foreground">
                                    <div className="flex items-center gap-2 font-medium text-foreground">
                                        <UserCogIcon />
                                        Account summary
                                    </div>
                                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <div className="text-xs tracking-[0.14em] uppercase">
                                                Verified email
                                            </div>
                                            <div className="mt-1 font-medium text-foreground">
                                                {user.email_verified_at
                                                    ? 'Yes'
                                                    : 'No'}
                                            </div>
                                        </div>
                                        <div>
                                            <div className="text-xs tracking-[0.14em] uppercase">
                                                Assigned roles
                                            </div>
                                            <div className="mt-1 font-medium text-foreground">
                                                {form.data.roles.length}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="rounded-xl border border-dashed bg-muted/20 p-4 text-sm text-muted-foreground">
                                    <div className="flex items-center gap-2 font-medium text-foreground">
                                        <LockKeyholeIcon />
                                        Provisioning note
                                    </div>
                                    <p className="mt-3">
                                        New accounts start with an unverified
                                        email address.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Profile Picture</CardTitle>
                            <CardDescription>
                                Upload a profile picture for the user.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Field
                                data-invalid={
                                    form.invalid('avatar') || undefined
                                }
                            >
                                <div className="flex flex-col items-center gap-4 text-center">
                                    <Avatar className="size-24 overflow-hidden border border-dashed border-border/70 bg-muted/40">
                                        <AvatarImage
                                            src={avatarPreviewUrl ?? undefined}
                                            alt={displayName || 'User avatar'}
                                        />
                                        <AvatarFallback className="bg-muted text-lg font-medium text-muted-foreground">
                                            {getInitials(displayName || 'User')}
                                        </AvatarFallback>
                                    </Avatar>

                                    <div className="flex flex-col items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                fileInputRef.current?.click()
                                            }
                                        >
                                            <UploadIcon data-icon="inline-start" />
                                            Select Profile Picture
                                        </Button>

                                        <input
                                            ref={fileInputRef}
                                            id="avatar"
                                            name="avatar"
                                            type="file"
                                            accept="image/jpeg,image/png,image/gif"
                                            className="hidden"
                                            onChange={handleAvatarChange}
                                            onBlur={() => form.touch('avatar')}
                                        />

                                        <FieldDescription>
                                            Upload a profile picture (JPG, PNG,
                                            GIF).
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('avatar')}
                                        </FieldError>
                                    </div>
                                </div>
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>User Roles</CardTitle>
                            <CardDescription>
                                Assign one or more roles for this user.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field>
                                    <FieldSet>
                                        <FieldLegend>Assign Roles</FieldLegend>
                                        <FieldDescription>
                                            Select one or more roles for this
                                            user.
                                        </FieldDescription>
                                        <div className="grid gap-3 rounded-xl border p-3">
                                            {availableRoles.map(
                                                (roleOption) => {
                                                    const checked =
                                                        form.data.roles.includes(
                                                            roleOption.id,
                                                        );

                                                    return (
                                                        <label
                                                            key={roleOption.id}
                                                            className="flex gap-3 rounded-lg border px-3 py-2 transition-colors hover:bg-muted/30"
                                                        >
                                                            <Checkbox
                                                                checked={
                                                                    checked
                                                                }
                                                                onCheckedChange={(
                                                                    value,
                                                                ) =>
                                                                    toggleRole(
                                                                        roleOption.id,
                                                                        value ===
                                                                            true,
                                                                    )
                                                                }
                                                                className="mt-0.5"
                                                            />
                                                            <div className="min-w-0 flex-1 text-left">
                                                                <div className="flex flex-wrap items-center gap-2">
                                                                    <span className="font-medium text-foreground">
                                                                        {
                                                                            roleOption.display_name
                                                                        }
                                                                    </span>
                                                                    {roleOption.is_system ? (
                                                                        <Badge variant="secondary">
                                                                            System
                                                                        </Badge>
                                                                    ) : null}
                                                                </div>
                                                                <div className="text-xs text-muted-foreground">
                                                                    {
                                                                        roleOption.name
                                                                    }
                                                                </div>
                                                            </div>
                                                        </label>
                                                    );
                                                },
                                            )}
                                        </div>
                                    </FieldSet>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Actions</CardTitle>
                            <CardDescription>
                                Save the user record or return to the user list.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col gap-3">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing ? (
                                        <Spinner />
                                    ) : (
                                        <SaveIcon data-icon="inline-start" />
                                    )}
                                    {form.processing
                                        ? mode === 'create'
                                            ? 'Creating...'
                                            : 'Saving...'
                                        : mode === 'create'
                                          ? 'Create User'
                                          : 'Update User'}
                                </Button>

                                <Button asChild variant="outline">
                                    <Link href={route('app.users.index')}>
                                        <ArrowLeftIcon data-icon="inline-start" />
                                        Cancel
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </form>
    );
}
