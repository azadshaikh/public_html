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
import type { DomainDnsRecordFormValues, PlatformOption } from '../../types/platform';

type DnsRecordFormProps = {
    mode: 'create' | 'edit';
    domain: {
        id: number;
        name: string;
    };
    record?: {
        id: number;
        name: string;
        type_label?: string | null;
    };
    initialValues: DomainDnsRecordFormValues;
    typeOptions: PlatformOption[];
    ttlOptions: PlatformOption[];
};

function selectedTypeLabel(typeOptions: PlatformOption[], value: string): string | null {
    const match = typeOptions.find((option) => String(option.value) === value);

    return match ? String(match.label) : null;
}

export default function DnsRecordForm({
    mode,
    domain,
    record,
    initialValues,
    typeOptions,
    ttlOptions,
}: DnsRecordFormProps) {
    const form = useAppForm<DomainDnsRecordFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? `platform.dns.create.${domain.id}`
                : `platform.dns.edit.${record?.id ?? 'new'}`,
        dirtyGuard: true,
    });

    const submitUrl =
        mode === 'create'
            ? route('platform.dns.store')
            : route('platform.dns.update', record!.id);

    const currentTypeLabel = selectedTypeLabel(typeOptions, form.data.type);
    const showsPriority = currentTypeLabel === 'MX' || currentTypeLabel === 'SRV';
    const showsSrvFields = currentTypeLabel === 'SRV';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(mode === 'create' ? 'post' : 'put', submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast:
                mode === 'create'
                    ? 'DNS record created successfully.'
                    : 'DNS record updated successfully.',
        });
    };

    return (
        <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Record details</CardTitle>
                            <CardDescription>
                                Configure the host, record type, destination value, and TTL for {domain.name}.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field>
                                        <FieldLabel htmlFor="domain_name">Domain</FieldLabel>
                                        <Input id="domain_name" value={domain.name} readOnly disabled />
                                    </Field>

                                    <Field data-invalid={form.invalid('name') || undefined}>
                                        <FieldLabel htmlFor="name">Host name</FieldLabel>
                                        <Input
                                            id="name"
                                            value={form.data.name}
                                            onChange={(event) => form.setField('name', event.target.value)}
                                            onBlur={() => form.touch('name')}
                                            aria-invalid={form.invalid('name') || undefined}
                                            placeholder="@ or www"
                                        />
                                        <FieldDescription>
                                            Use @ for the zone apex, or enter a subdomain label.
                                        </FieldDescription>
                                        <FieldError>{form.error('name')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('type') || undefined}>
                                        <FieldLabel>Record type</FieldLabel>
                                        <Select value={form.data.type || undefined} onValueChange={(value) => form.setField('type', value)}>
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('type') || undefined}>
                                                <SelectValue placeholder="Select record type" />
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

                                    <Field data-invalid={form.invalid('ttl') || undefined}>
                                        <FieldLabel>TTL</FieldLabel>
                                        <Select value={form.data.ttl || undefined} onValueChange={(value) => form.setField('ttl', value)}>
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('ttl') || undefined}>
                                                <SelectValue placeholder="Select TTL" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {ttlOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('ttl')}</FieldError>
                                    </Field>
                                </div>

                                <Field data-invalid={form.invalid('value') || undefined}>
                                    <FieldLabel htmlFor="value">Record value</FieldLabel>
                                    <Textarea
                                        id="value"
                                        value={form.data.value}
                                        onChange={(event) => form.setField('value', event.target.value)}
                                        onBlur={() => form.touch('value')}
                                        aria-invalid={form.invalid('value') || undefined}
                                        rows={5}
                                        placeholder="Target hostname, IP address, or policy value"
                                    />
                                    <FieldError>{form.error('value')}</FieldError>
                                </Field>

                                {showsPriority ? (
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <Field data-invalid={form.invalid('priority') || undefined}>
                                            <FieldLabel htmlFor="priority">Priority</FieldLabel>
                                            <Input
                                                id="priority"
                                                type="number"
                                                value={form.data.priority}
                                                onChange={(event) => form.setField('priority', event.target.value)}
                                                onBlur={() => form.touch('priority')}
                                                aria-invalid={form.invalid('priority') || undefined}
                                            />
                                            <FieldError>{form.error('priority')}</FieldError>
                                        </Field>

                                        {showsSrvFields ? (
                                            <>
                                                <Field data-invalid={form.invalid('weight') || undefined}>
                                                    <FieldLabel htmlFor="weight">Weight</FieldLabel>
                                                    <Input
                                                        id="weight"
                                                        type="number"
                                                        value={form.data.weight}
                                                        onChange={(event) => form.setField('weight', event.target.value)}
                                                        onBlur={() => form.touch('weight')}
                                                        aria-invalid={form.invalid('weight') || undefined}
                                                    />
                                                    <FieldError>{form.error('weight')}</FieldError>
                                                </Field>

                                                <Field data-invalid={form.invalid('port') || undefined}>
                                                    <FieldLabel htmlFor="port">Port</FieldLabel>
                                                    <Input
                                                        id="port"
                                                        type="number"
                                                        value={form.data.port}
                                                        onChange={(event) => form.setField('port', event.target.value)}
                                                        onBlur={() => form.touch('port')}
                                                        aria-invalid={form.invalid('port') || undefined}
                                                    />
                                                    <FieldError>{form.error('port')}</FieldError>
                                                </Field>
                                            </>
                                        ) : null}
                                    </div>
                                ) : null}
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Provider references</CardTitle>
                            <CardDescription>
                                Optional upstream IDs can help map the record back to an external DNS provider.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field data-invalid={form.invalid('record_id') || undefined}>
                                    <FieldLabel htmlFor="record_id">Provider record ID</FieldLabel>
                                    <Input
                                        id="record_id"
                                        value={form.data.record_id}
                                        onChange={(event) => form.setField('record_id', event.target.value)}
                                        onBlur={() => form.touch('record_id')}
                                        aria-invalid={form.invalid('record_id') || undefined}
                                    />
                                    <FieldError>{form.error('record_id')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('zone_id') || undefined}>
                                    <FieldLabel htmlFor="zone_id">Provider zone ID</FieldLabel>
                                    <Input
                                        id="zone_id"
                                        value={form.data.zone_id}
                                        onChange={(event) => form.setField('zone_id', event.target.value)}
                                        onBlur={() => form.touch('zone_id')}
                                        aria-invalid={form.invalid('zone_id') || undefined}
                                    />
                                    <FieldError>{form.error('zone_id')}</FieldError>
                                </Field>

                                <Field orientation="horizontal" className="items-start justify-between gap-4 rounded-lg border p-4">
                                    <div className="space-y-1">
                                        <FieldLabel htmlFor="disabled">Disable record</FieldLabel>
                                        <FieldDescription>
                                            Keep the record in the system without publishing it upstream.
                                        </FieldDescription>
                                        <FieldError>{form.error('disabled')}</FieldError>
                                    </div>
                                    <Switch id="disabled" checked={form.data.disabled} onCheckedChange={(checked) => form.setField('disabled', checked)} />
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Actions</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <Button type="submit" disabled={form.processing} className="w-full">
                                {form.processing ? <Spinner className="size-4" /> : <SaveIcon data-icon="inline-start" />}
                                {mode === 'create' ? 'Create record' : 'Save changes'}
                            </Button>
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('platform.dns.index', { status: 'all', domain_id: domain.id })}>
                                    <ArrowLeftIcon data-icon="inline-start" />
                                    Back to records
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </form>
    );
}
