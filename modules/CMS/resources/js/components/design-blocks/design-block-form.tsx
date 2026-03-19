'use client';

import { router } from '@inertiajs/react';
import { Settings2Icon } from 'lucide-react';
import type { FormEvent } from 'react';
import { MonacoEditor } from '@/components/code-editor/monaco-editor';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type {
    CmsOption,
    DesignBlockEditDetail,
    DesignBlockFormValues,
} from '../../types/cms';
import { CmsDangerZoneCard } from '../shared/cms-danger-zone-card';
import { CmsStickyFormFooter } from '../shared/cms-sticky-form-footer';
import { CmsTabSections } from '../shared/cms-tab-sections';

type DesignBlockFormProps = {
    mode: 'create' | 'edit';
    initialValues?: DesignBlockFormValues;
    statusOptions: CmsOption[];
    designTypeOptions: CmsOption[];
    blockTypeOptions: CmsOption[];
    categoryOptions: CmsOption[];
    designSystemOptions: CmsOption[];
    designBlock?: DesignBlockEditDetail;
};

const emptyValues: DesignBlockFormValues = {
    title: '',
    slug: '',
    description: '',
    html: '',
    css: '',
    scripts: '',
    preview_image_url: '',
    design_type: 'section',
    block_type: 'static',
    design_system: 'bootstrap',
    category_id: 'hero',
    status: 'draft',
};

function RequiredLabel({
    htmlFor,
    children,
}: {
    htmlFor?: string;
    children: string;
}) {
    return (
        <FieldLabel htmlFor={htmlFor}>
            {children} <span className="text-destructive">*</span>
        </FieldLabel>
    );
}

export default function DesignBlockForm({
    mode,
    initialValues,
    statusOptions,
    designTypeOptions,
    categoryOptions,
    designSystemOptions,
    designBlock,
}: DesignBlockFormProps) {
    const form = useAppForm<DesignBlockFormValues>({
        defaults: initialValues || emptyValues,
        rememberKey:
            mode === 'create'
                ? 'cms.designblocks.create.form'
                : `cms.designblocks.edit.${designBlock?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            title: [formValidators.required('Title')],
            design_type: [formValidators.required('Design type')],
            category_id: [formValidators.required('Category')],
            design_system: [formValidators.required('Design system')],
            status: [formValidators.required('Status')],
        },
    });

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('cms.designblock.store')
            : route('cms.designblock.update', designBlock!.id);

    const submitLabel =
        mode === 'create' ? 'Create Design Block' : 'Save Changes';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: {
                title:
                    mode === 'create'
                        ? 'Design block created'
                        : 'Design block updated',
                description:
                    mode === 'create'
                        ? 'The design block has been created successfully.'
                        : 'The design block has been updated successfully.',
            },
        });
    };

    const handleDelete = () => {
        if (!designBlock) {
            return;
        }

        if (!window.confirm(`Move "${designBlock.title}" to trash?`)) {
            return;
        }

        router.delete(route('cms.designblock.destroy', designBlock.id), {
            preserveScroll: true,
        });
    };

    return (
        <form
            className="flex flex-col gap-6 pb-20"
            onSubmit={handleSubmit}
            noValidate
        >
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="flex flex-col gap-6">
                    <Field data-invalid={form.invalid('title') || undefined}>
                        <RequiredLabel htmlFor="title">Title</RequiredLabel>
                        <Input
                            id="title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setField('title', event.target.value)
                            }
                            onBlur={() => form.touch('title')}
                            aria-invalid={form.invalid('title') || undefined}
                            placeholder="Enter design block title"
                        />
                        <FieldError>{form.error('title')}</FieldError>
                    </Field>

                    <Field
                        data-invalid={form.invalid('description') || undefined}
                    >
                        <FieldLabel htmlFor="description">Description</FieldLabel>
                        <Textarea
                            id="description"
                            rows={3}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setField('description', event.target.value)
                            }
                            onBlur={() => form.touch('description')}
                            aria-invalid={form.invalid('description') || undefined}
                            placeholder="Brief description of this block's purpose and usage"
                        />
                        <FieldError>{form.error('description')}</FieldError>
                    </Field>

                    <CmsTabSections
                        defaultValue="html"
                        tabs={[
                            {
                                value: 'html',
                                label: 'HTML',
                                content: (
                                    <Field
                                        data-invalid={
                                            form.invalid('html') || undefined
                                        }
                                    >
                                        <MonacoEditor
                                            name="html"
                                            language="html"
                                            height={400}
                                            value={form.data.html}
                                            onChange={(value) =>
                                                form.setField('html', value)
                                            }
                                            onBlur={() => form.touch('html')}
                                            placeholder="Enter HTML markup"
                                        />
                                        <FieldError>
                                            {form.error('html')}
                                        </FieldError>
                                    </Field>
                                ),
                            },
                            {
                                value: 'css',
                                label: 'CSS',
                                content: (
                                    <Field
                                        data-invalid={
                                            form.invalid('css') || undefined
                                        }
                                    >
                                        <MonacoEditor
                                            name="css"
                                            language="css"
                                            height={400}
                                            value={form.data.css}
                                            onChange={(value) =>
                                                form.setField('css', value)
                                            }
                                            onBlur={() => form.touch('css')}
                                            placeholder="Enter custom CSS styles"
                                        />
                                        <FieldDescription>
                                            Scoped styles for this block.
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('css')}
                                        </FieldError>
                                    </Field>
                                ),
                            },
                            {
                                value: 'scripts',
                                label: 'Scripts',
                                content: (
                                    <Field
                                        data-invalid={
                                            form.invalid('scripts') || undefined
                                        }
                                    >
                                        <MonacoEditor
                                            name="scripts"
                                            language="javascript"
                                            height={400}
                                            value={form.data.scripts}
                                            onChange={(value) =>
                                                form.setField('scripts', value)
                                            }
                                            onBlur={() =>
                                                form.touch('scripts')
                                            }
                                            placeholder="Enter JavaScript for this block"
                                        />
                                        <FieldDescription>
                                            JavaScript executed when this block
                                            is rendered.
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('scripts')}
                                        </FieldError>
                                    </Field>
                                ),
                            },
                        ]}
                    />

                    {designBlock ? (
                        <div className="text-sm text-muted-foreground">
                            Last updated {designBlock.updated_at_human ?? 'recently'}
                            {designBlock.updated_at_formatted
                                ? ` (${designBlock.updated_at_formatted})`
                                : ''}
                        </div>
                    ) : null}
                </div>

                <div className="flex flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Settings2Icon className="size-4 text-muted-foreground" />
                                <CardTitle>Classification</CardTitle>
                            </div>
                            <CardDescription>
                                Categorise this block by type, category, and
                                design system.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <Field
                                data-invalid={
                                    form.invalid('design_type') || undefined
                                }
                            >
                                <RequiredLabel htmlFor="design_type">
                                    Design type
                                </RequiredLabel>
                                <NativeSelect
                                    id="design_type"
                                    className="w-full"
                                    value={form.data.design_type}
                                    onChange={(event) =>
                                        form.setField(
                                            'design_type',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('design_type')}
                                    aria-invalid={
                                        form.invalid('design_type') || undefined
                                    }
                                >
                                    {designTypeOptions.map((option) => (
                                        <NativeSelectOption
                                            key={String(option.value)}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <FieldError>
                                    {form.error('design_type')}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('category_id') || undefined
                                }
                            >
                                <RequiredLabel htmlFor="category_id">
                                    Category
                                </RequiredLabel>
                                <NativeSelect
                                    id="category_id"
                                    className="w-full"
                                    value={form.data.category_id}
                                    onChange={(event) =>
                                        form.setField(
                                            'category_id',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('category_id')}
                                    aria-invalid={
                                        form.invalid('category_id') || undefined
                                    }
                                >
                                    {categoryOptions.map((option) => (
                                        <NativeSelectOption
                                            key={String(option.value)}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <FieldError>
                                    {form.error('category_id')}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('design_system') || undefined
                                }
                            >
                                <RequiredLabel htmlFor="design_system">
                                    Design system
                                </RequiredLabel>
                                <NativeSelect
                                    id="design_system"
                                    className="w-full"
                                    value={form.data.design_system}
                                    onChange={(event) =>
                                        form.setField(
                                            'design_system',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('design_system')}
                                    aria-invalid={
                                        form.invalid('design_system') ||
                                        undefined
                                    }
                                >
                                    {designSystemOptions.map((option) => (
                                        <NativeSelectOption
                                            key={String(option.value)}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <FieldError>
                                    {form.error('design_system')}
                                </FieldError>
                            </Field>

                            {/* hidden block_type — always static */}
                            <input
                                type="hidden"
                                name="block_type"
                                value="static"
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Preview image</CardTitle>
                            <CardDescription>
                                URL of a screenshot or preview image for this
                                block.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Field
                                data-invalid={
                                    form.invalid('preview_image_url') ||
                                    undefined
                                }
                            >
                                <FieldLabel htmlFor="preview_image_url">
                                    Image URL
                                </FieldLabel>
                                <Input
                                    id="preview_image_url"
                                    type="url"
                                    value={form.data.preview_image_url}
                                    onChange={(event) =>
                                        form.setField(
                                            'preview_image_url',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() =>
                                        form.touch('preview_image_url')
                                    }
                                    aria-invalid={
                                        form.invalid('preview_image_url') ||
                                        undefined
                                    }
                                    placeholder="https://example.com/preview.png"
                                />
                                <FieldError>
                                    {form.error('preview_image_url')}
                                </FieldError>
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Publish settings</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <Field
                                data-invalid={
                                    form.invalid('status') || undefined
                                }
                            >
                                <RequiredLabel htmlFor="status">
                                    Status
                                </RequiredLabel>
                                <NativeSelect
                                    id="status"
                                    className="w-full"
                                    value={form.data.status}
                                    onChange={(event) =>
                                        form.setField(
                                            'status',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('status')}
                                    aria-invalid={
                                        form.invalid('status') || undefined
                                    }
                                >
                                    {statusOptions.map((option) => (
                                        <NativeSelectOption
                                            key={String(option.value)}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <FieldError>{form.error('status')}</FieldError>
                            </Field>

                            <Field
                                data-invalid={form.invalid('slug') || undefined}
                            >
                                <FieldLabel htmlFor="slug">Slug</FieldLabel>
                                <Input
                                    id="slug"
                                    value={form.data.slug}
                                    onChange={(event) =>
                                        form.setField(
                                            'slug',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('slug')}
                                    aria-invalid={
                                        form.invalid('slug') || undefined
                                    }
                                    placeholder="optional-block-identifier"
                                />
                                <FieldDescription>
                                    Optional unique identifier for referencing
                                    this block programmatically.
                                </FieldDescription>
                                <FieldError>{form.error('slug')}</FieldError>
                            </Field>
                        </CardContent>
                    </Card>

                    <CmsDangerZoneCard
                        show={mode === 'edit' && Boolean(designBlock)}
                        description="Move this design block to trash. You can restore it later."
                        onDelete={handleDelete}
                    />
                </div>
            </div>

            <CmsStickyFormFooter
                backHref={route('cms.designblock.index')}
                backLabel="Back to Design Blocks"
                submitLabel={submitLabel}
                isCreate={mode === 'create'}
                isDirty={form.isDirty}
                isProcessing={form.processing}
            />
        </form>
    );
}
