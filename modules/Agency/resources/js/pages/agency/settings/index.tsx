import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type AgencySettingsPageProps = {
    section: 'general' | 'platform';
    settings: {
        free_subdomain: string;
        platform_api_url: string;
        has_agency_secret_key: boolean;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    {
        title: 'Agency Settings',
        href: route('agency.admin.settings.index', { section: 'general' }),
    },
];

export default function AgencySettings({
    section,
    settings,
}: AgencySettingsPageProps) {
    const generalForm = useForm({
        free_subdomain: settings.free_subdomain,
    });
    const platformForm = useForm({
        platform_api_url: settings.platform_api_url,
        agency_secret_key: '',
    });

    const submitGeneral = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        generalForm.post(route('agency.admin.settings.update-general'));
    };

    const submitPlatform = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        platformForm.post(route('agency.admin.settings.update-platform'));
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Agency Settings"
            description="Configure subdomain defaults and the platform integration used for provisioning."
        >
            <div className="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
                <Card className="h-fit">
                    <CardContent className="flex flex-col gap-2 pt-6">
                        <Button
                            asChild
                            variant={section === 'general' ? 'default' : 'ghost'}
                            className="justify-start"
                        >
                            <Link
                                href={route('agency.admin.settings.index', {
                                    section: 'general',
                                })}
                            >
                                General
                            </Link>
                        </Button>
                        <Button
                            asChild
                            variant={section === 'platform' ? 'default' : 'ghost'}
                            className="justify-start"
                        >
                            <Link
                                href={route('agency.admin.settings.index', {
                                    section: 'platform',
                                })}
                            >
                                Platform
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                {section === 'platform' ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Platform Integration</CardTitle>
                            <CardDescription>
                                These credentials connect the Agency module to the
                                provisioning platform.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form className="space-y-6" onSubmit={submitPlatform}>
                                <FormErrorSummary errors={platformForm.errors} />
                                <FieldGroup>
                                    <Field data-invalid={platformForm.errors.platform_api_url || undefined}>
                                        <FieldLabel htmlFor="platform_api_url">Platform API URL</FieldLabel>
                                        <Input
                                            id="platform_api_url"
                                            value={platformForm.data.platform_api_url}
                                            onChange={(event) =>
                                                platformForm.setData(
                                                    'platform_api_url',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="https://platform.example.com"
                                        />
                                        <FieldError>{platformForm.errors.platform_api_url}</FieldError>
                                    </Field>

                                    <Field data-invalid={platformForm.errors.agency_secret_key || undefined}>
                                        <FieldLabel htmlFor="agency_secret_key">Agency Secret Key</FieldLabel>
                                        <Input
                                            id="agency_secret_key"
                                            type="password"
                                            value={platformForm.data.agency_secret_key}
                                            onChange={(event) =>
                                                platformForm.setData(
                                                    'agency_secret_key',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder={
                                                settings.has_agency_secret_key
                                                    ? 'Leave blank to keep existing'
                                                    : 'Paste the issued secret key'
                                            }
                                        />
                                        <FieldError>{platformForm.errors.agency_secret_key}</FieldError>
                                    </Field>
                                </FieldGroup>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={platformForm.processing}>
                                        Save Platform Settings
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>General Settings</CardTitle>
                            <CardDescription>
                                Define the base domain used for free customer subdomains.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form className="space-y-6" onSubmit={submitGeneral}>
                                <FormErrorSummary errors={generalForm.errors} />
                                <FieldGroup>
                                    <Field data-invalid={generalForm.errors.free_subdomain || undefined}>
                                        <FieldLabel htmlFor="free_subdomain">Free Subdomain Domain</FieldLabel>
                                        <Input
                                            id="free_subdomain"
                                            value={generalForm.data.free_subdomain}
                                            onChange={(event) =>
                                                generalForm.setData(
                                                    'free_subdomain',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="sites.example.com"
                                        />
                                        <FieldError>{generalForm.errors.free_subdomain}</FieldError>
                                    </Field>
                                </FieldGroup>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={generalForm.processing}>
                                        Save General Settings
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
