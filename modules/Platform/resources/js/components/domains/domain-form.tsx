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
import { useAppForm } from '@/hooks/use-app-form';
import type { DomainFormValues, PlatformOption } from '../../types/platform';

type DomainFormProps = {
    mode: 'create' | 'edit';
    domain?: {
        id: number;
        name: string;
    };
    initialValues: DomainFormValues;
    typeOptions: PlatformOption[];
    agencyOptions: PlatformOption[];
    registrarOptions: PlatformOption[];
    statusOptions: PlatformOption[];
};

export default function DomainForm({
    mode,
    domain,
    initialValues,
    typeOptions,
    agencyOptions,
    registrarOptions,
    statusOptions,
}: DomainFormProps) {
    const form = useAppForm<DomainFormValues>({
        defaults: initialValues,
        rememberKey: mode === 'create' ? 'platform.domains.create' : `platform.domains.edit.${domain?.id ?? 'new'}`,
        dirtyGuard: true,
    });

    const submitUrl = mode === 'create' ? route('platform.domains.store') : route('platform.domains.update', domain!.id);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(mode === 'create' ? 'post' : 'put', submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: mode === 'create' ? 'Domain created successfully.' : 'Domain updated successfully.',
        });
    };

    return (
        <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.95fr)]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Domain profile</CardTitle>
                            <CardDescription>
                                Capture ownership, registrar routing, and lifecycle state for this domain.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('name') || undefined}>
                                        <FieldLabel htmlFor="name">Domain name</FieldLabel>
                                        <Input
                                            id="name"
                                            value={form.data.name}
                                            onChange={(event) => form.setField('name', event.target.value)}
                                            onBlur={() => form.touch('name')}
                                            aria-invalid={form.invalid('name') || undefined}
                                            placeholder="example.com"
                                        />
                                        <FieldError>{form.error('name')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('type') || undefined}>
                                        <FieldLabel>Domain type</FieldLabel>
                                        <Select value={form.data.type || undefined} onValueChange={(value) => form.setField('type', value)}>
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('type') || undefined}>
                                                <SelectValue placeholder="Select domain type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {typeOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('type')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('agency_id') || undefined}>
                                        <FieldLabel>Agency</FieldLabel>
                                        <Select
                                            value={form.data.agency_id || '__none__'}
                                            onValueChange={(value) => form.setField('agency_id', value === '__none__' ? '' : value)}
                                        >
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('agency_id') || undefined}>
                                                <SelectValue placeholder="Select agency" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="__none__">No agency</SelectItem>
                                                    {agencyOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('agency_id')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('status') || undefined}>
                                        <FieldLabel>Status</FieldLabel>
                                        <Select value={form.data.status || undefined} onValueChange={(value) => form.setField('status', value)}>
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('status') || undefined}>
                                                <SelectValue placeholder="Select status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {statusOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('status')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('registrar_id') || undefined}>
                                        <FieldLabel>Registrar</FieldLabel>
                                        <Select
                                            value={form.data.registrar_id || '__none__'}
                                            onValueChange={(value) => form.setField('registrar_id', value === '__none__' ? '' : value)}
                                        >
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('registrar_id') || undefined}>
                                                <SelectValue placeholder="Select registrar" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="__none__">No linked registrar</SelectItem>
                                                    {registrarOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('registrar_id')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('registrar_name') || undefined}>
                                        <FieldLabel htmlFor="registrar_name">Registrar name</FieldLabel>
                                        <Input
                                            id="registrar_name"
                                            value={form.data.registrar_name}
                                            onChange={(event) => form.setField('registrar_name', event.target.value)}
                                            onBlur={() => form.touch('registrar_name')}
                                            aria-invalid={form.invalid('registrar_name') || undefined}
                                        />
                                        <FieldDescription>
                                            Optional human-readable registrar label from WHOIS or manual entry.
                                        </FieldDescription>
                                        <FieldError>{form.error('registrar_name')}</FieldError>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Name servers and DNS</CardTitle>
                            <CardDescription>
                                Track name server assignments and any upstream DNS provider identifiers.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('domain_name_server_1') || undefined}>
                                        <FieldLabel htmlFor="domain_name_server_1">Name server 1</FieldLabel>
                                        <Input id="domain_name_server_1" value={form.data.domain_name_server_1} onChange={(event) => form.setField('domain_name_server_1', event.target.value)} onBlur={() => form.touch('domain_name_server_1')} aria-invalid={form.invalid('domain_name_server_1') || undefined} />
                                        <FieldError>{form.error('domain_name_server_1')}</FieldError>
                                    </Field>
                                    <Field data-invalid={form.invalid('domain_name_server_2') || undefined}>
                                        <FieldLabel htmlFor="domain_name_server_2">Name server 2</FieldLabel>
                                        <Input id="domain_name_server_2" value={form.data.domain_name_server_2} onChange={(event) => form.setField('domain_name_server_2', event.target.value)} onBlur={() => form.touch('domain_name_server_2')} aria-invalid={form.invalid('domain_name_server_2') || undefined} />
                                        <FieldError>{form.error('domain_name_server_2')}</FieldError>
                                    </Field>
                                    <Field data-invalid={form.invalid('domain_name_server_3') || undefined}>
                                        <FieldLabel htmlFor="domain_name_server_3">Name server 3</FieldLabel>
                                        <Input id="domain_name_server_3" value={form.data.domain_name_server_3} onChange={(event) => form.setField('domain_name_server_3', event.target.value)} onBlur={() => form.touch('domain_name_server_3')} aria-invalid={form.invalid('domain_name_server_3') || undefined} />
                                        <FieldError>{form.error('domain_name_server_3')}</FieldError>
                                    </Field>
                                    <Field data-invalid={form.invalid('domain_name_server_4') || undefined}>
                                        <FieldLabel htmlFor="domain_name_server_4">Name server 4</FieldLabel>
                                        <Input id="domain_name_server_4" value={form.data.domain_name_server_4} onChange={(event) => form.setField('domain_name_server_4', event.target.value)} onBlur={() => form.touch('domain_name_server_4')} aria-invalid={form.invalid('domain_name_server_4') || undefined} />
                                        <FieldError>{form.error('domain_name_server_4')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('dns_provider') || undefined}>
                                        <FieldLabel htmlFor="dns_provider">DNS provider</FieldLabel>
                                        <Input
                                            id="dns_provider"
                                            value={form.data.dns_provider}
                                            onChange={(event) => form.setField('dns_provider', event.target.value)}
                                            onBlur={() => form.touch('dns_provider')}
                                            aria-invalid={form.invalid('dns_provider') || undefined}
                                        />
                                        <FieldError>{form.error('dns_provider')}</FieldError>
                                    </Field>
                                    <Field data-invalid={form.invalid('dns_zone_id') || undefined}>
                                        <FieldLabel htmlFor="dns_zone_id">DNS zone ID</FieldLabel>
                                        <Input
                                            id="dns_zone_id"
                                            value={form.data.dns_zone_id}
                                            onChange={(event) => form.setField('dns_zone_id', event.target.value)}
                                            onBlur={() => form.touch('dns_zone_id')}
                                            aria-invalid={form.invalid('dns_zone_id') || undefined}
                                        />
                                        <FieldError>{form.error('dns_zone_id')}</FieldError>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>WHOIS dates</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field data-invalid={form.invalid('registered_date') || undefined}>
                                    <FieldLabel htmlFor="registered_date">Registered on</FieldLabel>
                                    <Input id="registered_date" type="date" value={form.data.registered_date} onChange={(event) => form.setField('registered_date', event.target.value)} onBlur={() => form.touch('registered_date')} aria-invalid={form.invalid('registered_date') || undefined} />
                                    <FieldError>{form.error('registered_date')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('expires_date') || undefined}>
                                    <FieldLabel htmlFor="expires_date">Expires on</FieldLabel>
                                    <Input id="expires_date" type="date" value={form.data.expires_date} onChange={(event) => form.setField('expires_date', event.target.value)} onBlur={() => form.touch('expires_date')} aria-invalid={form.invalid('expires_date') || undefined} />
                                    <FieldError>{form.error('expires_date')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('updated_date') || undefined}>
                                    <FieldLabel htmlFor="updated_date">Updated on</FieldLabel>
                                    <Input id="updated_date" type="date" value={form.data.updated_date} onChange={(event) => form.setField('updated_date', event.target.value)} onBlur={() => form.touch('updated_date')} aria-invalid={form.invalid('updated_date') || undefined} />
                                    <FieldError>{form.error('updated_date')}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button variant="outline" asChild>
                    <Link href={route('platform.domains.index', { status: 'all' })}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to domains
                    </Link>
                </Button>

                <Button type="submit" disabled={form.processing}>
                    {form.processing ? <Spinner data-icon="inline-start" /> : <SaveIcon data-icon="inline-start" />}
                    {mode === 'create' ? 'Create domain' : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}
