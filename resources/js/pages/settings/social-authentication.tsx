import { SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
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
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import type { BreadcrumbItem, SettingsNavItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: route('app.settings.index') },
    {
        title: 'Social Authentication',
        href: route('app.settings.social-authentication'),
    },
];

type SocialAuthPageProps = {
    settings: {
        enable_social_authentication: boolean;
        enable_google_authentication: boolean;
        google_client_id: string;
        google_client_secret: string;
        enable_github_authentication: boolean;
        github_client_id: string;
        github_client_secret: string;
    };
    settingsNav: SettingsNavItem[];
};

type SocialAuthFormData = {
    enable_social_authentication: boolean;
    enable_google_authentication: boolean;
    google_client_id: string;
    google_client_secret: string;
    enable_github_authentication: boolean;
    github_client_id: string;
    github_client_secret: string;
};

export default function SocialAuthentication({
    settings,
    settingsNav,
}: SocialAuthPageProps) {
    const form = useAppForm<SocialAuthFormData>({
        defaults: {
            enable_social_authentication: settings.enable_social_authentication,
            enable_google_authentication: settings.enable_google_authentication,
            google_client_id: settings.google_client_id,
            google_client_secret: settings.google_client_secret,
            enable_github_authentication: settings.enable_github_authentication,
            github_client_id: settings.github_client_id,
            github_client_secret: settings.github_client_secret,
        },
        dontRemember: ['google_client_secret', 'github_client_secret'],
        rememberKey: 'settings.social-authentication',
        dirtyGuard: { enabled: true },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(
            'put',
            route('app.settings.update', 'social_authentication'),
            {
                preserveScroll: true,
                setDefaultsOnSuccess: true,
                successToast: {
                    title: 'Social authentication settings updated',
                    description:
                        'Your social authentication settings have been saved successfully.',
                },
            },
        );
    };

    return (
        <SettingsLayout
            settingsNav={settingsNav}
            breadcrumbs={breadcrumbs}
            title="Settings"
            description="Manage your application settings."
        >
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <form
                    noValidate
                    className="flex flex-col gap-6"
                    onSubmit={handleSubmit}
                >
                    {form.dirtyGuardDialog}
                    <FormErrorSummary errors={form.errors} minMessages={2} />

                    <Card>
                        <CardHeader>
                            <CardTitle>Social Authentication</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field>
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="space-y-1">
                                            <FieldLabel htmlFor="enable_social_authentication">
                                                Enable Social Authentication
                                            </FieldLabel>
                                            <FieldDescription>
                                                Allow users to sign in with
                                                social providers.
                                            </FieldDescription>
                                        </div>
                                        <Switch
                                            id="enable_social_authentication"
                                            checked={
                                                form.data
                                                    .enable_social_authentication
                                            }
                                            onCheckedChange={(checked) =>
                                                form.setField(
                                                    'enable_social_authentication',
                                                    checked === true,
                                                )
                                            }
                                            size="comfortable"
                                        />
                                    </div>
                                </Field>

                                {form.data.enable_social_authentication ? (
                                    <>
                                        <Separator />

                                        <Field>
                                            <div className="flex items-center justify-between gap-4">
                                                <div className="space-y-1">
                                                    <FieldLabel htmlFor="enable_google_authentication">
                                                        Google
                                                    </FieldLabel>
                                                    <FieldDescription>
                                                        Enable Google OAuth
                                                        sign-in.
                                                    </FieldDescription>
                                                </div>
                                                <Switch
                                                    id="enable_google_authentication"
                                                    checked={
                                                        form.data
                                                            .enable_google_authentication
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        form.setField(
                                                            'enable_google_authentication',
                                                            checked === true,
                                                        )
                                                    }
                                                    size="comfortable"
                                                />
                                            </div>
                                        </Field>

                                        {form.data
                                            .enable_google_authentication ? (
                                            <FieldGroup className="rounded-lg border bg-muted/30 p-4">
                                                <Field
                                                    data-invalid={
                                                        form.invalid(
                                                            'google_client_id',
                                                        ) || undefined
                                                    }
                                                >
                                                    <FieldLabel htmlFor="google_client_id">
                                                        Google Client ID
                                                    </FieldLabel>
                                                    <Input
                                                        id="google_client_id"
                                                        value={
                                                            form.data
                                                                .google_client_id
                                                        }
                                                        onChange={(e) =>
                                                            form.setField(
                                                                'google_client_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                        onBlur={() =>
                                                            form.touch(
                                                                'google_client_id',
                                                            )
                                                        }
                                                        aria-invalid={
                                                            form.invalid(
                                                                'google_client_id',
                                                            ) || undefined
                                                        }
                                                        placeholder="Enter Google Client ID"
                                                        size="comfortable"
                                                    />
                                                    <FieldError>
                                                        {form.error(
                                                            'google_client_id',
                                                        )}
                                                    </FieldError>
                                                </Field>

                                                <Field
                                                    data-invalid={
                                                        form.invalid(
                                                            'google_client_secret',
                                                        ) || undefined
                                                    }
                                                >
                                                    <FieldLabel htmlFor="google_client_secret">
                                                        Google Client Secret
                                                    </FieldLabel>
                                                    <Input
                                                        id="google_client_secret"
                                                        type="password"
                                                        value={
                                                            form.data
                                                                .google_client_secret
                                                        }
                                                        onChange={(e) =>
                                                            form.setField(
                                                                'google_client_secret',
                                                                e.target.value,
                                                            )
                                                        }
                                                        onBlur={() =>
                                                            form.touch(
                                                                'google_client_secret',
                                                            )
                                                        }
                                                        aria-invalid={
                                                            form.invalid(
                                                                'google_client_secret',
                                                            ) || undefined
                                                        }
                                                        placeholder="Enter Google Client Secret"
                                                        size="comfortable"
                                                    />
                                                    <FieldError>
                                                        {form.error(
                                                            'google_client_secret',
                                                        )}
                                                    </FieldError>
                                                </Field>
                                            </FieldGroup>
                                        ) : null}

                                        <Separator />

                                        <Field>
                                            <div className="flex items-center justify-between gap-4">
                                                <div className="space-y-1">
                                                    <FieldLabel htmlFor="enable_github_authentication">
                                                        GitHub
                                                    </FieldLabel>
                                                    <FieldDescription>
                                                        Enable GitHub OAuth
                                                        sign-in.
                                                    </FieldDescription>
                                                </div>
                                                <Switch
                                                    id="enable_github_authentication"
                                                    checked={
                                                        form.data
                                                            .enable_github_authentication
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        form.setField(
                                                            'enable_github_authentication',
                                                            checked === true,
                                                        )
                                                    }
                                                    size="comfortable"
                                                />
                                            </div>
                                        </Field>

                                        {form.data
                                            .enable_github_authentication ? (
                                            <FieldGroup className="rounded-lg border bg-muted/30 p-4">
                                                <Field
                                                    data-invalid={
                                                        form.invalid(
                                                            'github_client_id',
                                                        ) || undefined
                                                    }
                                                >
                                                    <FieldLabel htmlFor="github_client_id">
                                                        GitHub Client ID
                                                    </FieldLabel>
                                                    <Input
                                                        id="github_client_id"
                                                        value={
                                                            form.data
                                                                .github_client_id
                                                        }
                                                        onChange={(e) =>
                                                            form.setField(
                                                                'github_client_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                        onBlur={() =>
                                                            form.touch(
                                                                'github_client_id',
                                                            )
                                                        }
                                                        aria-invalid={
                                                            form.invalid(
                                                                'github_client_id',
                                                            ) || undefined
                                                        }
                                                        placeholder="Enter GitHub Client ID"
                                                        size="comfortable"
                                                    />
                                                    <FieldError>
                                                        {form.error(
                                                            'github_client_id',
                                                        )}
                                                    </FieldError>
                                                </Field>

                                                <Field
                                                    data-invalid={
                                                        form.invalid(
                                                            'github_client_secret',
                                                        ) || undefined
                                                    }
                                                >
                                                    <FieldLabel htmlFor="github_client_secret">
                                                        GitHub Client Secret
                                                    </FieldLabel>
                                                    <Input
                                                        id="github_client_secret"
                                                        type="password"
                                                        value={
                                                            form.data
                                                                .github_client_secret
                                                        }
                                                        onChange={(e) =>
                                                            form.setField(
                                                                'github_client_secret',
                                                                e.target.value,
                                                            )
                                                        }
                                                        onBlur={() =>
                                                            form.touch(
                                                                'github_client_secret',
                                                            )
                                                        }
                                                        aria-invalid={
                                                            form.invalid(
                                                                'github_client_secret',
                                                            ) || undefined
                                                        }
                                                        placeholder="Enter GitHub Client Secret"
                                                        size="comfortable"
                                                    />
                                                    <FieldError>
                                                        {form.error(
                                                            'github_client_secret',
                                                        )}
                                                    </FieldError>
                                                </Field>
                                            </FieldGroup>
                                        ) : null}
                                    </>
                                ) : null}
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? (
                            <Spinner />
                        ) : (
                            <SaveIcon data-icon="inline-start" />
                        )}
                        {form.processing ? 'Saving...' : 'Save Settings'}
                    </Button>
                </form>
            </div>
        </SettingsLayout>
    );
}
