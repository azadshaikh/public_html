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

export type CmsPageFormValues = {
    title: string;
    slug: string;
    summary: string;
    body: string;
    status: string;
    published_at: string;
    is_featured: boolean;
};

type CmsPageEditingTarget = {
    id: number;
    title: string;
} | null;

type ModuleMeta = {
    name: string;
    description: string;
};

type Option = {
    value: string;
    label: string;
};

type CmsPageFormProps = {
    mode: 'create' | 'edit';
    module: ModuleMeta;
    page: CmsPageEditingTarget;
    initialValues: CmsPageFormValues;
    options: {
        statusOptions: Option[];
    };
};

const slugify = (value: string) =>
    value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
const cmsIndexUrl = '/cms';
const cmsUpdateUrl = (id: number) => `/cms/${id}`;

export default function CmsPageForm({
    mode,
    module,
    page,
    initialValues,
    options,
}: CmsPageFormProps) {
    const form = useForm<CmsPageFormValues>(initialValues);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (page) {
            form.patch(cmsUpdateUrl(page.id), {
                preserveScroll: true,
            });

            return;
        }

        form.post(cmsIndexUrl, {
            preserveScroll: true,
        });
    };

    const handleTitleChange = (value: string) => {
        const derivedSlug = slugify(form.data.title);

        form.setData('title', value);

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
                            ? 'Create page'
                            : `Edit ${page?.title}`}
                    </CardTitle>
                    <CardDescription>{module.description}</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-6 lg:grid-cols-2">
                    <div className="space-y-5 lg:col-span-2">
                        <div className="grid gap-5 md:grid-cols-2">
                            <div className="space-y-2">
                                <label
                                    className="text-sm font-medium"
                                    htmlFor="title"
                                >
                                    Title
                                </label>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(event) =>
                                        handleTitleChange(event.target.value)
                                    }
                                />
                                <InputError message={form.errors.title} />
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

                        <div className="space-y-2">
                            <label
                                className="text-sm font-medium"
                                htmlFor="summary"
                            >
                                Summary
                            </label>
                            <Textarea
                                id="summary"
                                value={form.data.summary}
                                onChange={(event) =>
                                    form.setData('summary', event.target.value)
                                }
                                rows={3}
                            />
                            <InputError message={form.errors.summary} />
                        </div>

                        <div className="space-y-2">
                            <label
                                className="text-sm font-medium"
                                htmlFor="body"
                            >
                                Body
                            </label>
                            <Textarea
                                id="body"
                                value={form.data.body}
                                onChange={(event) =>
                                    form.setData('body', event.target.value)
                                }
                                rows={12}
                            />
                            <InputError message={form.errors.body} />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium" htmlFor="status">
                            Status
                        </label>
                        <NativeSelect
                            id="status"
                            className="w-full"
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData('status', event.target.value)
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

                    <div className="space-y-2">
                        <label
                            className="text-sm font-medium"
                            htmlFor="published_at"
                        >
                            Publish date
                        </label>
                        <Input
                            id="published_at"
                            type="date"
                            value={form.data.published_at}
                            onChange={(event) =>
                                form.setData('published_at', event.target.value)
                            }
                        />
                        <InputError message={form.errors.published_at} />
                    </div>

                    <div className="flex items-start gap-3 rounded-xl border p-4 lg:col-span-2">
                        <Checkbox
                            id="is_featured"
                            checked={form.data.is_featured}
                            onCheckedChange={(checked) =>
                                form.setData('is_featured', checked === true)
                            }
                        />
                        <div className="space-y-1">
                            <label
                                className="text-sm font-medium"
                                htmlFor="is_featured"
                            >
                                Feature on dashboard
                            </label>
                            <p className="text-sm text-muted-foreground">
                                Use featured pages for hero banners, landing
                                pages, or highlighted navigation blocks.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-3 lg:col-span-2">
                        <Button asChild variant="outline" type="button">
                            <Link href={cmsIndexUrl}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Saving...'
                                : mode === 'create'
                                  ? 'Create page'
                                  : 'Save changes'}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </form>
    );
}
