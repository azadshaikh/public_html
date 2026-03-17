import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import type { PlatformOption, SslCertificateFormValues } from '../../types/platform';

type SslCertificateFormProps = {
    mode: 'create' | 'edit';
    domain: {
        id: number;
        name: string;
    };
    certificate?: {
        id: number;
        name: string;
    };
    initialValues: SslCertificateFormValues;
    certificateAuthorityOptions: PlatformOption[];
};

export default function SslCertificateForm({
    mode,
    domain,
    certificate,
    initialValues,
    certificateAuthorityOptions,
}: SslCertificateFormProps) {
    const form = useAppForm<SslCertificateFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? `platform.ssl-certificates.create.${domain.id}`
                : `platform.ssl-certificates.edit.${certificate?.id ?? 'new'}`,
        dirtyGuard: true,
    });

    const submitUrl =
        mode === 'create'
            ? route('platform.domains.ssl-certificates.store', domain.id)
            : route('platform.domains.ssl-certificates.update', [domain.id, certificate!.id]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(mode === 'create' ? 'post' : 'put', submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast:
                mode === 'create'
                    ? 'SSL certificate created successfully.'
                    : 'SSL certificate updated successfully.',
        });
    };

    return (
        <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Certificate profile</CardTitle>
                            <CardDescription>
                                Manage the authority, covered domains, and expiry details for this certificate.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('name') || undefined}>
                                        <FieldLabel htmlFor="name">Certificate name</FieldLabel>
                                        <Input
                                            id="name"
                                            value={form.data.name}
                                            onChange={(event) => form.setField('name', event.target.value)}
                                            onBlur={() => form.touch('name')}
                                            aria-invalid={form.invalid('name') || undefined}
                                        />
                                        <FieldError>{form.error('name')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('certificate_authority') || undefined}>
                                        <FieldLabel>Certificate authority</FieldLabel>
                                        <Select
                                            value={form.data.certificate_authority || undefined}
                                            onValueChange={(value) => form.setField('certificate_authority', value)}
                                        >
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('certificate_authority') || undefined}>
                                                <SelectValue placeholder="Select authority" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {certificateAuthorityOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('certificate_authority')}</FieldError>
                                    </Field>
                                </div>

                                <Field orientation="horizontal">
                                    <FieldLabel htmlFor="is_wildcard">Wildcard certificate</FieldLabel>
                                    <FieldDescription>Mark when the certificate covers subdomains through a wildcard SAN.</FieldDescription>
                                    <Switch id="is_wildcard" checked={form.data.is_wildcard} onCheckedChange={(checked) => form.setField('is_wildcard', checked)} />
                                </Field>

                                <Field data-invalid={form.invalid('domains') || undefined}>
                                    <FieldLabel htmlFor="domains">Covered domains</FieldLabel>
                                    <Textarea
                                        id="domains"
                                        value={form.data.domains}
                                        onChange={(event) => form.setField('domains', event.target.value)}
                                        onBlur={() => form.touch('domains')}
                                        aria-invalid={form.invalid('domains') || undefined}
                                        rows={4}
                                        placeholder="example.com, *.example.com"
                                    />
                                    <FieldDescription>
                                        Enter a comma-separated list of domains included in the certificate.
                                    </FieldDescription>
                                    <FieldError>{form.error('domains')}</FieldError>
                                </Field>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('issuer') || undefined}>
                                        <FieldLabel htmlFor="issuer">Issuer</FieldLabel>
                                        <Input
                                            id="issuer"
                                            value={form.data.issuer}
                                            onChange={(event) => form.setField('issuer', event.target.value)}
                                            onBlur={() => form.touch('issuer')}
                                            aria-invalid={form.invalid('issuer') || undefined}
                                        />
                                        <FieldError>{form.error('issuer')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('issued_at') || undefined}>
                                        <FieldLabel htmlFor="issued_at">Issued on</FieldLabel>
                                        <Input
                                            id="issued_at"
                                            type="date"
                                            value={form.data.issued_at}
                                            onChange={(event) => form.setField('issued_at', event.target.value)}
                                            onBlur={() => form.touch('issued_at')}
                                            aria-invalid={form.invalid('issued_at') || undefined}
                                        />
                                        <FieldError>{form.error('issued_at')}</FieldError>
                                    </Field>
                                </div>

                                <Field data-invalid={form.invalid('expires_at') || undefined}>
                                    <FieldLabel htmlFor="expires_at">Expires on</FieldLabel>
                                    <Input
                                        id="expires_at"
                                        type="date"
                                        value={form.data.expires_at}
                                        onChange={(event) => form.setField('expires_at', event.target.value)}
                                        onBlur={() => form.touch('expires_at')}
                                        aria-invalid={form.invalid('expires_at') || undefined}
                                    />
                                    <FieldError>{form.error('expires_at')}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>PEM material</CardTitle>
                            <CardDescription>
                                Store the private key, certificate, and optional chain bundle in PEM format.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field data-invalid={form.invalid('private_key') || undefined}>
                                    <FieldLabel htmlFor="private_key">
                                        {mode === 'create' ? 'Private key' : 'Private key (optional)'}
                                    </FieldLabel>
                                    <Textarea
                                        id="private_key"
                                        value={form.data.private_key}
                                        onChange={(event) => form.setField('private_key', event.target.value)}
                                        onBlur={() => form.touch('private_key')}
                                        aria-invalid={form.invalid('private_key') || undefined}
                                        rows={10}
                                    />
                                    <FieldError>{form.error('private_key')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('certificate') || undefined}>
                                    <FieldLabel htmlFor="certificate">
                                        {mode === 'create' ? 'Certificate' : 'Certificate (optional)'}
                                    </FieldLabel>
                                    <Textarea
                                        id="certificate"
                                        value={form.data.certificate}
                                        onChange={(event) => form.setField('certificate', event.target.value)}
                                        onBlur={() => form.touch('certificate')}
                                        aria-invalid={form.invalid('certificate') || undefined}
                                        rows={10}
                                    />
                                    <FieldError>{form.error('certificate')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('ca_bundle') || undefined}>
                                    <FieldLabel htmlFor="ca_bundle">CA bundle</FieldLabel>
                                    <Textarea
                                        id="ca_bundle"
                                        value={form.data.ca_bundle}
                                        onChange={(event) => form.setField('ca_bundle', event.target.value)}
                                        onBlur={() => form.touch('ca_bundle')}
                                        aria-invalid={form.invalid('ca_bundle') || undefined}
                                        rows={8}
                                    />
                                    <FieldError>{form.error('ca_bundle')}</FieldError>
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
                    {mode === 'create' ? 'Create certificate' : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}
