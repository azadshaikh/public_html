import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';

export type PromptTemplateFormValues = {
    name: string;
    slug: string;
    purpose: string;
    model: string;
    tone: string;
    system_prompt: string;
    notes: string;
    status: string;
    is_default: boolean;
};

type PromptEditingTarget = {
    id: number;
    name: string;
} | null;

type ModuleMeta = {
    name: string;
    description: string;
};

type Option = {
    value: string;
    label: string;
};

type PromptTemplateFormProps = {
    mode: 'create' | 'edit';
    module: ModuleMeta;
    prompt: PromptEditingTarget;
    initialValues: PromptTemplateFormValues;
    options: {
        statusOptions: Option[];
        toneOptions: Option[];
    };
};

const slugify = (value: string) =>
    value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
const chatbotIndexUrl = '/chatbot';
const chatbotUpdateUrl = (id: number) => `/chatbot/${id}`;

export default function PromptTemplateForm({
    mode,
    module,
    prompt,
    initialValues,
    options,
}: PromptTemplateFormProps) {
    const form = useForm<PromptTemplateFormValues>(initialValues);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (prompt) {
            form.patch(chatbotUpdateUrl(prompt.id), {
                preserveScroll: true,
            });

            return;
        }

        form.post(chatbotIndexUrl, {
            preserveScroll: true,
        });
    };

    const handleNameChange = (value: string) => {
        const derivedSlug = slugify(form.data.name);

        form.setData('name', value);

        if (form.data.slug === '' || form.data.slug === derivedSlug) {
            form.setData('slug', slugify(value));
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>
                        {mode === 'create'
                            ? 'Create prompt template'
                            : `Edit ${prompt?.name}`}
                    </CardTitle>
                    <CardDescription>{module.description}</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-6 lg:grid-cols-2">
                    <div className="space-y-5 lg:col-span-2">
                        <div className="grid gap-5 md:grid-cols-2">
                            <div className="space-y-2">
                                <label
                                    className="text-sm font-medium"
                                    htmlFor="name"
                                >
                                    Name
                                </label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        handleNameChange(event.target.value)
                                    }
                                />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="space-y-2">
                                <label
                                    className="text-sm font-medium"
                                    htmlFor="slug"
                                >
                                    Slug
                                </label>
                                <Input
                                    id="slug"
                                    value={form.data.slug}
                                    onChange={(event) =>
                                        form.setData(
                                            'slug',
                                            slugify(event.target.value),
                                        )
                                    }
                                />
                                <InputError message={form.errors.slug} />
                            </div>
                        </div>

                        <div className="grid gap-5 md:grid-cols-2">
                            <div className="space-y-2">
                                <label
                                    className="text-sm font-medium"
                                    htmlFor="purpose"
                                >
                                    Purpose
                                </label>
                                <Input
                                    id="purpose"
                                    value={form.data.purpose}
                                    onChange={(event) =>
                                        form.setData(
                                            'purpose',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.purpose} />
                            </div>
                            <div className="space-y-2">
                                <label
                                    className="text-sm font-medium"
                                    htmlFor="model"
                                >
                                    Model
                                </label>
                                <Input
                                    id="model"
                                    value={form.data.model}
                                    onChange={(event) =>
                                        form.setData(
                                            'model',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.model} />
                            </div>
                        </div>

                        <div className="grid gap-5 md:grid-cols-2">
                            <div className="space-y-2">
                                <label
                                    className="text-sm font-medium"
                                    htmlFor="tone"
                                >
                                    Tone
                                </label>
                                <NativeSelect
                                    id="tone"
                                    className="w-full"
                                    value={form.data.tone}
                                    onChange={(event) =>
                                        form.setData('tone', event.target.value)
                                    }
                                >
                                    {options.toneOptions.map((option) => (
                                        <NativeSelectOption
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <InputError message={form.errors.tone} />
                            </div>
                            <div className="space-y-2">
                                <label
                                    className="text-sm font-medium"
                                    htmlFor="status"
                                >
                                    Status
                                </label>
                                <NativeSelect
                                    id="status"
                                    className="w-full"
                                    value={form.data.status}
                                    onChange={(event) =>
                                        form.setData(
                                            'status',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {options.statusOptions.map((option) => (
                                        <NativeSelectOption
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <InputError message={form.errors.status} />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <label
                                className="text-sm font-medium"
                                htmlFor="system_prompt"
                            >
                                System prompt
                            </label>
                            <Textarea
                                id="system_prompt"
                                value={form.data.system_prompt}
                                onChange={(event) =>
                                    form.setData(
                                        'system_prompt',
                                        event.target.value,
                                    )
                                }
                                rows={12}
                            />
                            <InputError message={form.errors.system_prompt} />
                        </div>

                        <div className="space-y-2">
                            <label
                                className="text-sm font-medium"
                                htmlFor="notes"
                            >
                                Notes
                            </label>
                            <Textarea
                                id="notes"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                                rows={4}
                            />
                            <InputError message={form.errors.notes} />
                        </div>
                    </div>

                    <div className="flex items-start gap-3 rounded-xl border p-4 lg:col-span-2">
                        <Checkbox
                            id="is_default"
                            checked={form.data.is_default}
                            onCheckedChange={(checked) =>
                                form.setData('is_default', checked === true)
                            }
                        />
                        <div className="space-y-1">
                            <label
                                className="text-sm font-medium"
                                htmlFor="is_default"
                            >
                                Set as default template
                            </label>
                            <p className="text-sm text-muted-foreground">
                                Default templates are a good starting point for
                                assistants, support bots, or agent presets.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-3 lg:col-span-2">
                        <Button asChild variant="outline" type="button">
                            <Link href={chatbotIndexUrl}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Saving...'
                                : mode === 'create'
                                  ? 'Create template'
                                  : 'Save changes'}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </form>
    );
}
