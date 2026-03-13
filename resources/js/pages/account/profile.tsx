import { Transition } from '@headlessui/react';
import { SaveIcon, UploadIcon } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ChangeEvent, FormEvent } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Profile/ProfileController';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { formValidators } from '@/lib/forms';
import type { FormValidationRules } from '@/lib/forms';
import { profile as profileRoute } from '@/routes/app';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile',
        href: profileRoute(),
    },
];

type ProfilePageProps = {
    profile: {
        first_name: string;
        last_name: string;
        full_name: string;
        username: string;
        email: string;
        phone: string;
        avatar_url: string | null;
    };
    showUsername: boolean;
};

type ProfileFormData = {
    first_name: string;
    last_name: string;
    username: string;
    phone: string;
    avatar: File | null;
};

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

export default function Profile({ profile, showUsername }: ProfilePageProps) {
    const getInitials = useInitials();
    const fileInputRef = useRef<HTMLInputElement>(null);
    const temporaryPreviewUrlRef = useRef<string | null>(null);
    const [temporaryAvatarPreviewUrl, setTemporaryAvatarPreviewUrl] = useState<
        string | null
    >(null);
    const displayName =
        profile.full_name.trim() ||
        `${profile.first_name} ${profile.last_name}`.trim() ||
        profile.email;
    const avatarPreviewUrl = temporaryAvatarPreviewUrl ?? profile.avatar_url;
    const validationRules = useMemo<FormValidationRules<ProfileFormData>>(
        () => ({
            first_name: [formValidators.required('First name')],
            last_name: [formValidators.required('Last name')],
        }),
        [],
    );
    const form = useAppForm<ProfileFormData>({
        defaults: {
            first_name: profile.first_name,
            last_name: profile.last_name,
            username: profile.username,
            phone: profile.phone,
            avatar: null,
        },
        rememberKey: 'account.profile',
        dontRemember: ['avatar'],
        dirtyGuard: {
            enabled: true,
        },
        rules: validationRules,
    });

    useEffect(() => {
        return () => {
            if (temporaryPreviewUrlRef.current !== null) {
                URL.revokeObjectURL(temporaryPreviewUrlRef.current);
            }
        };
    }, []);

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

        form.submit(ProfileController.update(), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
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
        });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Profile"
            description="Update your profile details and email address."
        >
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <form
                    noValidate
                    className="flex flex-col gap-6"
                    onSubmit={handleSubmit}
                >
                    {form.dirtyGuardDialog}
                    <FormErrorSummary errors={form.errors} />

                    <Card className="rounded-[24px] py-6">
                        <CardHeader className="px-6">
                            <CardTitle>Personal details</CardTitle>
                        </CardHeader>

                        <CardContent className="px-6">
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('avatar') || undefined
                                    }
                                >
                                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                                        <Avatar className="size-20 overflow-hidden border border-border/70 bg-muted/40">
                                            <AvatarImage
                                                src={
                                                    avatarPreviewUrl ??
                                                    undefined
                                                }
                                                alt={displayName}
                                            />
                                            <AvatarFallback className="bg-muted text-xl font-medium text-muted-foreground">
                                                {getInitials(displayName)}
                                            </AvatarFallback>
                                        </Avatar>

                                        <div className="flex min-w-0 flex-1 flex-col gap-2">
                                            <div>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        fileInputRef.current?.click()
                                                    }
                                                >
                                                    <UploadIcon data-icon="inline-start" />
                                                    Upload photo
                                                </Button>
                                                <input
                                                    ref={fileInputRef}
                                                    id="avatar"
                                                    name="avatar"
                                                    type="file"
                                                    accept="image/jpeg,image/png,image/gif"
                                                    className="hidden"
                                                    onChange={
                                                        handleAvatarChange
                                                    }
                                                    onBlur={() =>
                                                        form.touch('avatar')
                                                    }
                                                />
                                            </div>

                                            <FieldDescription>
                                                Upload a profile picture (JPG,
                                                PNG, GIF)
                                            </FieldDescription>
                                            <FieldError>
                                                {form.error('avatar')}
                                            </FieldError>
                                        </div>
                                    </div>
                                </Field>

                                <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                    <Field
                                        data-invalid={
                                            form.invalid('first_name') ||
                                            undefined
                                        }
                                    >
                                        <RequiredLabel htmlFor="first_name">
                                            First Name
                                        </RequiredLabel>
                                        <Input
                                            id="first_name"
                                            name="first_name"
                                            value={form.data.first_name}
                                            onChange={(event) =>
                                                form.setField(
                                                    'first_name',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('first_name')
                                            }
                                            aria-invalid={
                                                form.invalid('first_name') ||
                                                undefined
                                            }
                                            autoComplete="given-name"
                                            placeholder="Enter first name"
                                            size="comfortable"
                                            autoFocus
                                        />
                                        <FieldError>
                                            {form.error('first_name')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('last_name') ||
                                            undefined
                                        }
                                    >
                                        <RequiredLabel htmlFor="last_name">
                                            Last Name
                                        </RequiredLabel>
                                        <Input
                                            id="last_name"
                                            name="last_name"
                                            value={form.data.last_name}
                                            onChange={(event) =>
                                                form.setField(
                                                    'last_name',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('last_name')
                                            }
                                            aria-invalid={
                                                form.invalid('last_name') ||
                                                undefined
                                            }
                                            autoComplete="family-name"
                                            placeholder="Enter last name"
                                            size="comfortable"
                                        />
                                        <FieldError>
                                            {form.error('last_name')}
                                        </FieldError>
                                    </Field>
                                </FieldGroup>

                                {showUsername ? (
                                    <Field
                                        data-invalid={
                                            form.invalid('username') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="username">
                                            Username
                                        </FieldLabel>
                                        <Input
                                            id="username"
                                            name="username"
                                            value={form.data.username}
                                            onChange={(event) =>
                                                form.setField(
                                                    'username',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('username')
                                            }
                                            aria-invalid={
                                                form.invalid('username') ||
                                                undefined
                                            }
                                            autoComplete="username"
                                            placeholder="Choose a unique username"
                                            size="comfortable"
                                        />
                                        <FieldError>
                                            {form.error('username')}
                                        </FieldError>
                                    </Field>
                                ) : null}

                                <Field data-disabled>
                                    <RequiredLabel htmlFor="email">
                                        Email Address
                                    </RequiredLabel>
                                    <Input
                                        id="email"
                                        defaultValue={profile.email}
                                        disabled
                                        size="comfortable"
                                        className="bg-muted/70 text-muted-foreground"
                                    />
                                    <FieldDescription>
                                        Contact support to change your email
                                        address.
                                    </FieldDescription>
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
                                        name="phone"
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
                                        autoComplete="tel"
                                        placeholder="Enter phone number"
                                        size="comfortable"
                                    />
                                    <FieldError>
                                        {form.error('phone')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-3">
                        <Button
                            type="submit"
                            size="lg"
                            className="h-11 w-full rounded-xl text-base font-semibold"
                            disabled={form.processing}
                            data-test="update-profile-button"
                        >
                            {form.processing ? (
                                <Spinner />
                            ) : (
                                <SaveIcon data-icon="inline-start" />
                            )}
                            {form.processing ? 'Updating...' : 'Update Profile'}
                        </Button>

                        <div className="flex min-h-5 items-center justify-center">
                            {form.isDirty && !form.processing ? (
                                <p className="text-center text-sm text-muted-foreground">
                                    You have unsaved changes.
                                </p>
                            ) : (
                                <Transition
                                    show={form.recentlySuccessful}
                                    enter="transition ease-out duration-200"
                                    enterFrom="opacity-0 translate-y-1"
                                    enterTo="opacity-100 translate-y-0"
                                    leave="transition ease-in duration-150"
                                    leaveFrom="opacity-100 translate-y-0"
                                    leaveTo="opacity-0 translate-y-1"
                                >
                                    <p className="text-center text-sm text-muted-foreground">
                                        Profile updated.
                                    </p>
                                </Transition>
                            )}
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
