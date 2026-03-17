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
import type { PlatformOption, SecretFormValues } from '../../types/platform';

type SecretFormProps = {
    mode: 'create' | 'edit';
    secret?: {
        id: number;
        key: string;
    };
    initialValues: SecretFormValues;
    typeOptions: PlatformOption[];
    secretableTypeOptions: PlatformOption[];
};

export default function SecretForm({
    mode,
    secret,
    initialValues,
    typeOptions,
    secretableTypeOptions,
}: SecretFormProps) {
    const form = useAppForm<SecretFormValues>({
        defaults: initialValues,
        rememberKey: mode === 'create' ? 'platform.secrets.create' : `platform.secrets.edit.${secret?.id ?? 'new'}`,
        dirtyGuard: true,
    });

    const submitUrl = mode === 'create' ? route('platform.secrets.store') : route('platform.secrets.update', secret!.id);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(mode === 'create' ? 'post' : 'put', submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: mode === 'create' ? 'Secret created successfully.' : 'Secret updated successfully.',
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
                            <CardTitle>Secret record</CardTitle>
                            <CardDescription>
                                Attach a secret to a specific platform entity and control its lifecycle.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('secretable_type') || undefined}>
                                        <FieldLabel>Entity type</FieldLabel>
                                        <Select
                                            value={form.data.secretable_type || undefined}
                                            onValueChange={(value) => form.setField('secretable_type', value)}
                                        >
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('secretable_type') || undefined}>
                                                <SelectValue placeholder="Select entity type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {secretableTypeOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('secretable_type')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('secretable_id') || undefined}>
                                        <FieldLabel htmlFor="secretable_id">Entity ID</FieldLabel>
                                        <Input
                                            id="secretable_id"
                                            type="number"
                                            min="1"
                                            value={form.data.secretable_id}
                                            onChange={(event) => form.setField('secretable_id', event.target.value)}
                                            onBlur={() => form.touch('secretable_id')}
                                            aria-invalid={form.invalid('secretable_id') || undefined}
                                        />
                                        <FieldDescription>
                                            Enter the numeric ID of the selected domain, website, agency, server, or provider.
                                        </FieldDescription>
                                        <FieldError>{form.error('secretable_id')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('key') || undefined}>
                                        <FieldLabel htmlFor="key">Secret key</FieldLabel>
                                        <Input
                                            id="key"
                                            value={form.data.key}
                                            onChange={(event) => form.setField('key', event.target.value)}
                                            onBlur={() => form.touch('key')}
                                            aria-invalid={form.invalid('key') || undefined}
                                        />
                                        <FieldError>{form.error('key')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('username') || undefined}>
                                        <FieldLabel htmlFor="username">Username</FieldLabel>
                                        <Input
                                            id="username"
                                            value={form.data.username}
                                            onChange={(event) => form.setField('username', event.target.value)}
                                            onBlur={() => form.touch('username')}
                                            aria-invalid={form.invalid('username') || undefined}
                                        />
                                        <FieldError>{form.error('username')}</FieldError>
                                    </Field>
                                </div>

                                <Field data-invalid={form.invalid('type') || undefined}>
                                    <FieldLabel>Secret type</FieldLabel>
                                    <Select value={form.data.type || undefined} onValueChange={(value) => form.setField('type', value)}>
                                        <SelectTrigger className="w-full" aria-invalid={form.invalid('type') || undefined}>
                                            <SelectValue placeholder="Select secret type" />
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

                                <Field data-invalid={form.invalid('value') || undefined}>
                                    <FieldLabel htmlFor="value">
                                        {mode === 'create' ? 'Secret value' : 'Secret value (leave blank to keep current)'}
                                    </FieldLabel>
                                    <Textarea
                                        id="value"
                                        value={form.data.value}
                                        onChange={(event) => form.setField('value', event.target.value)}
                                        onBlur={() => form.touch('value')}
                                        aria-invalid={form.invalid('value') || undefined}
                                        rows={8}
                                    />
                                    <FieldError>{form.error('value')}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Availability</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field orientation="horizontal">
                                    <FieldLabel htmlFor="is_active">Active</FieldLabel>
                                    <FieldDescription>Inactive secrets stay stored but should not be used for automation.</FieldDescription>
                                    <Switch id="is_active" checked={form.data.is_active} onCheckedChange={(checked) => form.setField('is_active', checked)} />
                                </Field>

                                <Field data-invalid={form.invalid('expires_at') || undefined}>
                                    <FieldLabel htmlFor="expires_at">Expires at</FieldLabel>
                                    <Input
                                        id="expires_at"
                                        type="datetime-local"
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
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button variant="outline" asChild>
                    <Link href={route('platform.secrets.index', { status: 'all' })}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to secrets
                    </Link>
                </Button>

                <Button type="submit" disabled={form.processing}>
                    {form.processing ? <Spinner data-icon="inline-start" /> : <SaveIcon data-icon="inline-start" />}
                    {mode === 'create' ? 'Create secret' : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}
