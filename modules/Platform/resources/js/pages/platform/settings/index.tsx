import { SaveIcon, SettingsIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import { formValidators } from '@/lib/forms';
import type { BreadcrumbItem, SettingsNavItem } from '@/types';

type SelectOption = {
    value: string | number;
    label: string;
};

type PlatformSettingsValues = {
    trail_server_id: number | null;
    default_sub_domain: string;
    default_domain_ssl_key: string;
    default_domain_ssl_crt: string;
    default_ssl_expiry: string;
};

type PlatformSettingsPageProps = {
    initialValues: PlatformSettingsValues;
    serverOptions: SelectOption[];
    settingsNav: SettingsNavItem[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Platform', href: route('platform.websites.index', { status: 'all' }) },
    { title: 'Settings', href: route('platform.settings.index') },
];

export default function PlatformSettings({
    initialValues,
    serverOptions,
    settingsNav,
}: PlatformSettingsPageProps) {
    const form = useAppForm<PlatformSettingsValues>({
        rememberKey: 'platform.settings.general',
        defaults: initialValues,
        dirtyGuard: { enabled: true },
        rules: {
            trail_server_id: [formValidators.required('Trial server')],
            default_sub_domain: [formValidators.required('Trial domain')],
            default_domain_ssl_key: [formValidators.required('Domain SSL key')],
            default_domain_ssl_crt: [formValidators.required('Domain SSL certificate')],
            default_ssl_expiry: [formValidators.required('Default SSL expiry')],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('platform.settings.update'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Platform settings updated',
                description: 'The platform defaults have been saved.',
            },
        });
    };

    return (
        <SettingsLayout
            breadcrumbs={breadcrumbs}
            settingsNav={settingsNav}
            activeSlug="general"
            railLabel="Platform settings"
            title="Platform Settings"
            description="Configure trial provisioning defaults and the wildcard SSL material used by the platform."
        >
            <form className="space-y-6" onSubmit={handleSubmit} noValidate>
                {form.dirtyGuardDialog}
                <FormErrorSummary errors={form.errors} minMessages={2} />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <SettingsIcon data-icon="inline-start" />
                            General Settings
                        </CardTitle>
                        <CardDescription>
                            Choose the default trial server, base trial domain, and certificate material for generated domains.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <Field data-invalid={form.invalid('trail_server_id') || undefined}>
                            <FieldLabel htmlFor="trail_server_id">
                                Trial Server <span className="text-destructive">*</span>
                            </FieldLabel>
                            <NativeSelect
                                id="trail_server_id"
                                value={form.data.trail_server_id ?? ''}
                                onChange={(event) =>
                                    form.setField(
                                        'trail_server_id',
                                        event.target.value ? Number(event.target.value) : null,
                                    )
                                }
                                onBlur={() => form.touch('trail_server_id')}
                                aria-invalid={form.invalid('trail_server_id') || undefined}
                            >
                                <NativeSelectOption value="">Select a server</NativeSelectOption>
                                {serverOptions.map((option) => (
                                    <NativeSelectOption key={option.value} value={option.value}>
                                        {option.label}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <FieldError>{form.error('trail_server_id')}</FieldError>
                        </Field>

                        <Field data-invalid={form.invalid('default_sub_domain') || undefined}>
                            <FieldLabel htmlFor="default_sub_domain">
                                Trial Domain <span className="text-destructive">*</span>
                            </FieldLabel>
                            <Input
                                id="default_sub_domain"
                                value={form.data.default_sub_domain}
                                onChange={(event) =>
                                    form.setField('default_sub_domain', event.target.value)
                                }
                                onBlur={() => form.touch('default_sub_domain')}
                                aria-invalid={form.invalid('default_sub_domain') || undefined}
                                placeholder="trial.example.com"
                            />
                            <FieldError>{form.error('default_sub_domain')}</FieldError>
                        </Field>

                        <FieldGroup className="gap-6">
                            <Field data-invalid={form.invalid('default_domain_ssl_key') || undefined}>
                                <FieldLabel htmlFor="default_domain_ssl_key">
                                    Domain SSL Key <span className="text-destructive">*</span>
                                </FieldLabel>
                                <Textarea
                                    id="default_domain_ssl_key"
                                    rows={10}
                                    value={form.data.default_domain_ssl_key}
                                    onChange={(event) =>
                                        form.setField('default_domain_ssl_key', event.target.value)
                                    }
                                    onBlur={() => form.touch('default_domain_ssl_key')}
                                    aria-invalid={
                                        form.invalid('default_domain_ssl_key') || undefined
                                    }
                                    placeholder="-----BEGIN PRIVATE KEY-----"
                                />
                                <FieldError>{form.error('default_domain_ssl_key')}</FieldError>
                            </Field>

                            <Field data-invalid={form.invalid('default_domain_ssl_crt') || undefined}>
                                <FieldLabel htmlFor="default_domain_ssl_crt">
                                    Domain SSL Certificate <span className="text-destructive">*</span>
                                </FieldLabel>
                                <Textarea
                                    id="default_domain_ssl_crt"
                                    rows={10}
                                    value={form.data.default_domain_ssl_crt}
                                    onChange={(event) =>
                                        form.setField('default_domain_ssl_crt', event.target.value)
                                    }
                                    onBlur={() => form.touch('default_domain_ssl_crt')}
                                    aria-invalid={
                                        form.invalid('default_domain_ssl_crt') || undefined
                                    }
                                    placeholder="-----BEGIN CERTIFICATE-----"
                                />
                                <FieldError>{form.error('default_domain_ssl_crt')}</FieldError>
                            </Field>
                        </FieldGroup>

                        <Field data-invalid={form.invalid('default_ssl_expiry') || undefined}>
                            <FieldLabel htmlFor="default_ssl_expiry">
                                Default SSL Expiry <span className="text-destructive">*</span>
                            </FieldLabel>
                            <Input
                                id="default_ssl_expiry"
                                type="date"
                                value={form.data.default_ssl_expiry}
                                onChange={(event) =>
                                    form.setField('default_ssl_expiry', event.target.value)
                                }
                                onBlur={() => form.touch('default_ssl_expiry')}
                                aria-invalid={form.invalid('default_ssl_expiry') || undefined}
                            />
                            <FieldError>{form.error('default_ssl_expiry')}</FieldError>
                        </Field>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>
                                <SaveIcon data-icon="inline-start" />
                                Save Settings
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </SettingsLayout>
    );
}
