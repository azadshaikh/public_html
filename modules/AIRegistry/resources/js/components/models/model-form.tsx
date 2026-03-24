import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, SaveIcon, SparklesIcon } from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type {
    AIRegistryOption,
    AiModelFormValues,
    AIRegistryRecordSummary,
} from '../../types/ai-registry';

type ModelFormProps = {
    mode: 'create' | 'edit';
    model?: AIRegistryRecordSummary & { provider_name?: string };
    initialValues: AiModelFormValues;
    providerOptions: AIRegistryOption[];
    capabilityOptions: AIRegistryOption[];
    categoryOptions: AIRegistryOption[];
    inputModalityOptions: AIRegistryOption[];
    outputModalityOptions: AIRegistryOption[];
};

export default function ModelForm({
    mode,
    model,
    initialValues,
    providerOptions,
    capabilityOptions,
    categoryOptions,
    inputModalityOptions,
    outputModalityOptions,
}: ModelFormProps) {
    const form = useAppForm<AiModelFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'ai-registry.models.create'
                : `ai-registry.models.edit.${model?.id ?? 'new'}`,
        dirtyGuard: { enabled: true },
        rules: {
            provider_id: [formValidators.required('Provider')],
            slug: [formValidators.required('Model slug')],
            name: [formValidators.required('Model name')],
        },
    });

    const submitUrl =
        mode === 'create'
            ? route('ai-registry.models.store')
            : route('ai-registry.models.update', model!.id);

    const toggleArrayField = (
        key: 'capabilities' | 'categories' | 'input_modalities' | 'output_modalities',
        value: string,
        checked: boolean,
    ) => {
        const currentValues = form.data[key];
        const nextValues = checked
            ? Array.from(new Set([...currentValues, value]))
            : currentValues.filter((item) => item !== value);

        form.setField(key, nextValues);
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(mode === 'create' ? 'post' : 'put', submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: {
                title: mode === 'create' ? 'Model created' : 'Model updated',
                description:
                    mode === 'create'
                        ? 'The AI model has been created successfully.'
                        : 'The AI model has been updated successfully.',
            },
        });
    };

    return (
        <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(300px,1fr)]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Model profile</CardTitle>
                            <CardDescription>
                                Capture the provider mapping, canonical slug, and commercial metadata used across the registry.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('provider_id') || undefined}>
                                        <FieldLabel>Provider</FieldLabel>
                                        <Select
                                            value={form.data.provider_id || undefined}
                                            onValueChange={(value) => form.setField('provider_id', value)}
                                        >
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('provider_id') || undefined}>
                                                <SelectValue placeholder="Select provider" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {providerOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('provider_id')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('slug') || undefined}>
                                        <FieldLabel htmlFor="slug">Model slug</FieldLabel>
                                        <Input
                                            id="slug"
                                            value={form.data.slug}
                                            onChange={(event) => form.setField('slug', event.target.value)}
                                            onBlur={() => form.touch('slug')}
                                            aria-invalid={form.invalid('slug') || undefined}
                                            placeholder="gpt-4.1-mini"
                                        />
                                        <FieldError>{form.error('slug')}</FieldError>
                                    </Field>
                                </div>

                                <Field data-invalid={form.invalid('name') || undefined}>
                                    <FieldLabel htmlFor="name">Model name</FieldLabel>
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(event) => form.setField('name', event.target.value)}
                                        onBlur={() => form.touch('name')}
                                        aria-invalid={form.invalid('name') || undefined}
                                    />
                                    <FieldError>{form.error('name')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('description') || undefined}>
                                    <FieldLabel htmlFor="description">Description</FieldLabel>
                                    <Textarea
                                        id="description"
                                        value={form.data.description}
                                        onChange={(event) => form.setField('description', event.target.value)}
                                        onBlur={() => form.touch('description')}
                                        aria-invalid={form.invalid('description') || undefined}
                                        rows={4}
                                    />
                                    <FieldError>{form.error('description')}</FieldError>
                                </Field>

                                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    <Field data-invalid={form.invalid('context_window') || undefined}>
                                        <FieldLabel htmlFor="context_window">Context window</FieldLabel>
                                        <Input
                                            id="context_window"
                                            type="number"
                                            min="0"
                                            value={form.data.context_window}
                                            onChange={(event) => form.setField('context_window', event.target.value)}
                                            onBlur={() => form.touch('context_window')}
                                            aria-invalid={form.invalid('context_window') || undefined}
                                            placeholder="128000"
                                        />
                                        <FieldError>{form.error('context_window')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('max_output_tokens') || undefined}>
                                        <FieldLabel htmlFor="max_output_tokens">Max output</FieldLabel>
                                        <Input
                                            id="max_output_tokens"
                                            type="number"
                                            min="0"
                                            value={form.data.max_output_tokens}
                                            onChange={(event) => form.setField('max_output_tokens', event.target.value)}
                                            onBlur={() => form.touch('max_output_tokens')}
                                            aria-invalid={form.invalid('max_output_tokens') || undefined}
                                            placeholder="8192"
                                        />
                                        <FieldError>{form.error('max_output_tokens')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('input_cost_per_1m') || undefined}>
                                        <FieldLabel htmlFor="input_cost_per_1m">Input $ / 1M</FieldLabel>
                                        <Input
                                            id="input_cost_per_1m"
                                            type="number"
                                            min="0"
                                            step="0.0001"
                                            value={form.data.input_cost_per_1m}
                                            onChange={(event) => form.setField('input_cost_per_1m', event.target.value)}
                                            onBlur={() => form.touch('input_cost_per_1m')}
                                            aria-invalid={form.invalid('input_cost_per_1m') || undefined}
                                            placeholder="0.1500"
                                        />
                                        <FieldError>{form.error('input_cost_per_1m')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('output_cost_per_1m') || undefined}>
                                        <FieldLabel htmlFor="output_cost_per_1m">Output $ / 1M</FieldLabel>
                                        <Input
                                            id="output_cost_per_1m"
                                            type="number"
                                            min="0"
                                            step="0.0001"
                                            value={form.data.output_cost_per_1m}
                                            onChange={(event) => form.setField('output_cost_per_1m', event.target.value)}
                                            onBlur={() => form.touch('output_cost_per_1m')}
                                            aria-invalid={form.invalid('output_cost_per_1m') || undefined}
                                            placeholder="0.6000"
                                        />
                                        <FieldError>{form.error('output_cost_per_1m')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('tokenizer') || undefined}>
                                        <FieldLabel htmlFor="tokenizer">Tokenizer</FieldLabel>
                                        <Input
                                            id="tokenizer"
                                            value={form.data.tokenizer}
                                            onChange={(event) => form.setField('tokenizer', event.target.value)}
                                            onBlur={() => form.touch('tokenizer')}
                                            aria-invalid={form.invalid('tokenizer') || undefined}
                                            placeholder="cl100k_base"
                                        />
                                        <FieldError>{form.error('tokenizer')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('supported_parameters') || undefined}>
                                        <FieldLabel htmlFor="supported_parameters">Supported parameters</FieldLabel>
                                        <Input
                                            id="supported_parameters"
                                            value={form.data.supported_parameters}
                                            onChange={(event) => form.setField('supported_parameters', event.target.value)}
                                            onBlur={() => form.touch('supported_parameters')}
                                            aria-invalid={form.invalid('supported_parameters') || undefined}
                                            placeholder="temperature, top_p, max_tokens"
                                        />
                                        <FieldDescription>
                                            Provide a comma-separated list of runtime parameters the model accepts.
                                        </FieldDescription>
                                        <FieldError>{form.error('supported_parameters')}</FieldError>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Capabilities and classification</CardTitle>
                            <CardDescription>
                                Flag the workloads and content types this model can support.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-6 lg:grid-cols-2">
                                <FieldSet>
                                    <FieldLegend variant="label">Capabilities</FieldLegend>
                                    <div className="grid gap-3">
                                        {capabilityOptions.map((option) => {
                                            const inputId = `model-capability-${option.value}`;

                                            return (
                                                <Field key={String(option.value)} orientation="horizontal">
                                                    <Checkbox
                                                        id={inputId}
                                                        checked={form.data.capabilities.includes(String(option.value))}
                                                        onCheckedChange={(checked) =>
                                                            toggleArrayField('capabilities', String(option.value), checked === true)
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

                                <FieldSet>
                                    <FieldLegend variant="label">Categories</FieldLegend>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {categoryOptions.map((option) => {
                                            const inputId = `model-category-${option.value}`;

                                            return (
                                                <Field key={String(option.value)} orientation="horizontal">
                                                    <Checkbox
                                                        id={inputId}
                                                        checked={form.data.categories.includes(String(option.value))}
                                                        onCheckedChange={(checked) =>
                                                            toggleArrayField('categories', String(option.value), checked === true)
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
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Modality support</CardTitle>
                            <CardDescription>
                                Track which content types the model can accept and return.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <FieldSet>
                                <FieldLegend variant="label">Input modalities</FieldLegend>
                                <div className="grid gap-3">
                                    {inputModalityOptions.map((option) => {
                                        const inputId = `model-input-${option.value}`;

                                        return (
                                            <Field key={String(option.value)} orientation="horizontal">
                                                <Checkbox
                                                    id={inputId}
                                                    checked={form.data.input_modalities.includes(String(option.value))}
                                                    onCheckedChange={(checked) =>
                                                        toggleArrayField('input_modalities', String(option.value), checked === true)
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

                            <FieldSet>
                                <FieldLegend variant="label">Output modalities</FieldLegend>
                                <div className="grid gap-3">
                                    {outputModalityOptions.map((option) => {
                                        const inputId = `model-output-${option.value}`;

                                        return (
                                            <Field key={String(option.value)} orientation="horizontal">
                                                <Checkbox
                                                    id={inputId}
                                                    checked={form.data.output_modalities.includes(String(option.value))}
                                                    onCheckedChange={(checked) =>
                                                        toggleArrayField('output_modalities', String(option.value), checked === true)
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

                    <Card>
                        <CardHeader>
                            <CardTitle>Lifecycle</CardTitle>
                            <CardDescription>
                                Manage moderation metadata and whether this model is currently selectable.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <Field orientation="horizontal">
                                <FieldContent>
                                    <FieldLabel htmlFor="is_moderated">Moderated output</FieldLabel>
                                    <FieldDescription>
                                        Mark models that enforce provider-side moderation or policy screening.
                                    </FieldDescription>
                                </FieldContent>
                                <Switch
                                    id="is_moderated"
                                    checked={form.data.is_moderated}
                                    onCheckedChange={(checked) => form.setField('is_moderated', Boolean(checked))}
                                />
                            </Field>

                            <Field orientation="horizontal">
                                <FieldContent>
                                    <FieldLabel htmlFor="is_active">Active model</FieldLabel>
                                    <FieldDescription>
                                        Inactive models remain visible in the registry but drop out of active selection lists.
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
                                <SparklesIcon data-icon="inline-start" />
                                Workflow
                            </CardTitle>
                            <CardDescription>
                                Keep pricing and modality metadata current so downstream selectors can make accurate decisions.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? <Spinner data-icon="inline-start" /> : <SaveIcon data-icon="inline-start" />}
                                {mode === 'create' ? 'Create model' : 'Save model'}
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={route('ai-registry.models.index', { status: 'all' })}>
                                    <ArrowLeftIcon data-icon="inline-start" />
                                    Back to models
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </form>
    );
}