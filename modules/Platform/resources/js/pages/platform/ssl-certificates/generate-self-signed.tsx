import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, SaveIcon, SparklesIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { SelfSignedCertificateValues } from '../../../types/platform';

type SelfSignedCertificatePageProps = {
    domain: {
        id: number;
        name: string;
    };
    initialValues: SelfSignedCertificateValues;
};

export default function GenerateSelfSignedCertificate({
    domain,
    initialValues,
}: SelfSignedCertificatePageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Platform', href: route('platform.domains.index', { status: 'all' }) },
        { title: 'Domains', href: route('platform.domains.index', { status: 'all' }) },
        { title: domain.name, href: route('platform.domains.show', domain.id) },
        {
            title: 'Generate self-signed',
            href: route('platform.domains.ssl-certificates.generate-self-signed', domain.id),
        },
    ];

    const form = useAppForm<SelfSignedCertificateValues>({
        defaults: initialValues,
        rememberKey: `platform.ssl-certificates.self-signed.${domain.id}`,
        dirtyGuard: true,
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('platform.domains.ssl-certificates.generate-self-signed.store', domain.id), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: 'Self-signed certificate generated successfully.',
        });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Generate self-signed certificate for ${domain.name}`}
            description="Create a self-signed PEM certificate bundle for internal previews, staging, or isolated testing."
        >
            <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
                {form.dirtyGuardDialog}
                <FormErrorSummary errors={form.errors} minMessages={2} />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.95fr)]">
                    <div className="flex flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <SparklesIcon className="size-4 text-muted-foreground" />
                                    <CardTitle>Certificate subject</CardTitle>
                                </div>
                                <CardDescription>Define the certificate identity and included SAN entries.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FieldGroup>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <Field data-invalid={form.invalid('name') || undefined}>
                                            <FieldLabel htmlFor="name">Certificate name</FieldLabel>
                                            <Input id="name" value={form.data.name} onChange={(event) => form.setField('name', event.target.value)} onBlur={() => form.touch('name')} aria-invalid={form.invalid('name') || undefined} />
                                            <FieldError>{form.error('name')}</FieldError>
                                        </Field>
                                        <Field data-invalid={form.invalid('key_type') || undefined}>
                                            <FieldLabel htmlFor="key_type">Key type</FieldLabel>
                                            <Input id="key_type" value={form.data.key_type} onChange={(event) => form.setField('key_type', event.target.value)} onBlur={() => form.touch('key_type')} aria-invalid={form.invalid('key_type') || undefined} />
                                            <FieldDescription>Use rsa2048, rsa4096, ec256, or ec384.</FieldDescription>
                                            <FieldError>{form.error('key_type')}</FieldError>
                                        </Field>
                                    </div>

                                    <div className="grid gap-4 md:grid-cols-2">
                                        <Field data-invalid={form.invalid('common_name') || undefined}>
                                            <FieldLabel htmlFor="common_name">Common name</FieldLabel>
                                            <Input id="common_name" value={form.data.common_name} onChange={(event) => form.setField('common_name', event.target.value)} onBlur={() => form.touch('common_name')} aria-invalid={form.invalid('common_name') || undefined} />
                                            <FieldError>{form.error('common_name')}</FieldError>
                                        </Field>
                                        <Field data-invalid={form.invalid('validity_days') || undefined}>
                                            <FieldLabel htmlFor="validity_days">Validity days</FieldLabel>
                                            <Input id="validity_days" type="number" min="1" max="3650" value={form.data.validity_days} onChange={(event) => form.setField('validity_days', event.target.value)} onBlur={() => form.touch('validity_days')} aria-invalid={form.invalid('validity_days') || undefined} />
                                            <FieldError>{form.error('validity_days')}</FieldError>
                                        </Field>
                                    </div>

                                    <Field data-invalid={form.invalid('san_domains') || undefined}>
                                        <FieldLabel htmlFor="san_domains">SAN domains</FieldLabel>
                                        <Textarea id="san_domains" value={form.data.san_domains} onChange={(event) => form.setField('san_domains', event.target.value)} onBlur={() => form.touch('san_domains')} aria-invalid={form.invalid('san_domains') || undefined} rows={6} />
                                        <FieldDescription>Enter one hostname per line to include in the certificate SAN list.</FieldDescription>
                                        <FieldError>{form.error('san_domains')}</FieldError>
                                    </Field>
                                </FieldGroup>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="flex flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Organization details</CardTitle>
                                <CardDescription>Optional certificate subject fields used for issuer generation.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FieldGroup>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <Field data-invalid={form.invalid('country') || undefined}>
                                            <FieldLabel htmlFor="country">Country</FieldLabel>
                                            <Input id="country" value={form.data.country} onChange={(event) => form.setField('country', event.target.value)} onBlur={() => form.touch('country')} aria-invalid={form.invalid('country') || undefined} />
                                            <FieldError>{form.error('country')}</FieldError>
                                        </Field>
                                        <Field data-invalid={form.invalid('state') || undefined}>
                                            <FieldLabel htmlFor="state">State</FieldLabel>
                                            <Input id="state" value={form.data.state} onChange={(event) => form.setField('state', event.target.value)} onBlur={() => form.touch('state')} aria-invalid={form.invalid('state') || undefined} />
                                            <FieldError>{form.error('state')}</FieldError>
                                        </Field>
                                        <Field data-invalid={form.invalid('city') || undefined}>
                                            <FieldLabel htmlFor="city">City</FieldLabel>
                                            <Input id="city" value={form.data.city} onChange={(event) => form.setField('city', event.target.value)} onBlur={() => form.touch('city')} aria-invalid={form.invalid('city') || undefined} />
                                            <FieldError>{form.error('city')}</FieldError>
                                        </Field>
                                        <Field data-invalid={form.invalid('organization') || undefined}>
                                            <FieldLabel htmlFor="organization">Organization</FieldLabel>
                                            <Input id="organization" value={form.data.organization} onChange={(event) => form.setField('organization', event.target.value)} onBlur={() => form.touch('organization')} aria-invalid={form.invalid('organization') || undefined} />
                                            <FieldError>{form.error('organization')}</FieldError>
                                        </Field>
                                    </div>

                                    <Field data-invalid={form.invalid('org_unit') || undefined}>
                                        <FieldLabel htmlFor="org_unit">Organization unit</FieldLabel>
                                        <Input id="org_unit" value={form.data.org_unit} onChange={(event) => form.setField('org_unit', event.target.value)} onBlur={() => form.touch('org_unit')} aria-invalid={form.invalid('org_unit') || undefined} />
                                        <FieldError>{form.error('org_unit')}</FieldError>
                                    </Field>
                                </FieldGroup>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button variant="outline" asChild>
                        <Link href={route('platform.domains.show', domain.id)}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back to domain
                        </Link>
                    </Button>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? <Spinner data-icon="inline-start" /> : <SaveIcon data-icon="inline-start" />}
                        Generate certificate
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
