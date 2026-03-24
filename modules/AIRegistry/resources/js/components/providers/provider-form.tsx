import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, BotIcon, SaveIcon } from 'lucide-react';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type {
    AIRegistryOption,
    AiProviderFormValues,
    AIRegistryRecordSummary,
} from '../../types/ai-registry';

type ProviderFormProps = {
    mode: 'create' | 'edit';
    provider?: AIRegistryRecordSummary;
    initialValues: AiProviderFormValues;
    capabilityOptions: AIRegistryOption[];
};

export default function ProviderForm({
    mode,
    provider,
    initialValues,
    capabilityOptions,
}: ProviderFormProps) {
    const form = useAppForm<AiProviderFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'ai-registry.providers.create'
                : `ai-registry.providers.edit.${provider?.id ?? 'new'}`,
        dirtyGuard: { enabled: true },
        rules: {
            slug: [formValidators.required('Provider slug')],
            name: [formValidators.required('Provider name')],
        },
    });

    const submitUrl =
        mode === 'create'
            ? route('ai-registry.providers.store')
            : route('ai-registry.providers.update', provider!.id);

    const toggleCapability = (value: string, checked: boolean) => {
        const nextValues = checked
            ? Array.from(new Set([...form.data.capabilities, value]))
            : form.data.capabilities.filter((item) => item !== value);

        form.setField('capabilities', nextValues);
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(mode === 'create' ? 'post' : 'put', submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: {
                title:
                    mode === 'create'
                        ? 'Provider created'
                        : 'Provider updated',
                description:
                    mode === 'create'
                        ? 'The AI provider has been created successfully.'
                        : 'The AI provider has been updated successfully.',
            },
        });
    };

    return (
        <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Provider profile</CardTitle>
                            <CardDescription>
                                Define the provider slug, reference links, and the AI capabilities it supports.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('name') || undefined}>
                                        <FieldLabel htmlFor="name">Provider name</FieldLabel>
                                        <Input
                                            id="name"
                                            value={form.data.name}
                                            onChange={(event) => form.setField('name', event.target.value)}
                                            onBlur={() => form.touch('name')}
                                            aria-invalid={form.invalid('name') || undefined}
                                        />
                                        <FieldError>{form.error('name')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('slug') || undefined}>
                                        <FieldLabel htmlFor="slug">Provider slug</FieldLabel>
                                        <Input
                                            id="slug"
                                            value={form.data.slug}
                                            onChange={(event) => form.setField('slug', event.target.value)}
                                            onBlur={() => form.touch('slug')}
                                            aria-invalid={form.invalid('slug') || undefined}
                                            placeholder="openai"
                                        />
                                        <FieldDescription>
                                            Use the canonical provider identifier expected by integrations.
                                        </FieldDescription>
                                        <FieldError>{form.error('slug')}</FieldError>
                                    </Field>
                                </div>

                                <Field data-invalid={form.invalid('docs_url') || undefined}>
                                    <FieldLabel htmlFor="docs_url">Documentation URL</FieldLabel>
                                    <Input
                                        id="docs_url"
                                        type="url"
                                        value={form.data.docs_url}
                                        onChange={(event) => form.setField('docs_url', event.target.value)}
                                        onBlur={() => form.touch('docs_url')}
                                        aria-invalid={form.invalid('docs_url') || undefined}
                                        placeholder="https://platform.openai.com/docs"
                                    />
                                    <FieldError>{form.error('docs_url')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('api_key_url') || undefined}>
                                    <FieldLabel htmlFor="api_key_url">API key setup URL</FieldLabel>
                                    <Input
                                        id="api_key_url"
                                        type="url"
                                        value={form.data.api_key_url}
                                        onChange={(event) => form.setField('api_key_url', event.target.value)}
                                        onBlur={() => form.touch('api_key_url')}
                                        aria-invalid={form.invalid('api_key_url') || undefined}
                                        placeholder="https://platform.openai.com/api-keys"
                                    />
                                    <FieldError>{form.error('api_key_url')}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Supported capabilities</CardTitle>
                            <CardDescription>
                                Expose the features downstream model sync and UI flows can rely on.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldSet>
                                <FieldLegend variant="label">Capabilities</FieldLegend>
                                <div className="grid gap-3 md:grid-cols-2">
                                    {capabilityOptions.map((option) => {
                                        const inputId = `provider-capability-${option.value}`;

                                        return (
                                            <Field key={String(option.value)} orientation="horizontal">
                                                <Checkbox
                                                    id={inputId}
                                                    checked={form.data.capabilities.includes(String(option.value))}
                                                    onCheckedChange={(checked) =>
                                                        toggleCapability(String(option.value), checked === true)
                                                    }
                                                />
                                                <FieldContent>
                                                    <FieldLabel htmlFor={inputId}>{option.label}</FieldLabel>
                                                </FieldContent>
                                            </Field>
                                        );
                                    })}
                                </div>
                            </FieldSet>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Lifecycle</CardTitle>
                            <CardDescription>
                                Control whether the provider is available for model assignment.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Field orientation="horizontal">
                                <FieldContent>
                                    <FieldLabel htmlFor="is_active">Active provider</FieldLabel>
                                    <FieldDescription>
                                        Inactive providers remain in the registry but are hidden from active model selections.
                                    </FieldDescription>
                                </FieldContent>
                                <Switch
                                    id="is_active"
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) => form.setField('is_active', Boolean(checked))}
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BotIcon data-icon="inline-start" />
                                Workflow
                            </CardTitle>
                            <CardDescription>
                                Save the provider now and add models afterwards from the model registry.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? <Spinner data-icon="inline-start" /> : <SaveIcon data-icon="inline-start" />}
                                {mode === 'create' ? 'Create provider' : 'Save provider'}
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={route('ai-registry.providers.index', { status: 'all' })}>
                                    <ArrowLeftIcon data-icon="inline-start" />
                                    Back to providers
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </form>
    );
}