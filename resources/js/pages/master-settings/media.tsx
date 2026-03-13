import { SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import MasterSettingsController from '@/actions/App/Http/Controllers/Masters/SettingsController';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import type { BreadcrumbItem, SettingsNavItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Settings', href: MasterSettingsController.index() },
    { title: 'Media', href: MasterSettingsController.media() },
];

type MediaPageProps = {
    settings: {
        max_file_name_length: string;
        max_upload_size: string;
        allowed_file_types: string;
        image_optimization: boolean;
        image_quality: string;
        thumbnail_width: string;
        small_width: string;
        medium_width: string;
        large_width: string;
        xlarge_width: string;
        delete_trashed: boolean;
        delete_trashed_days: string;
    };
    settingsNav: SettingsNavItem[];
};

type MediaFormData = {
    max_file_name_length: string;
    max_upload_size: string;
    allowed_file_types: string;
    image_optimization: boolean;
    image_quality: string;
    thumbnail_width: string;
    small_width: string;
    medium_width: string;
    large_width: string;
    xlarge_width: string;
    delete_trashed: boolean;
    delete_trashed_days: string;
};

export default function Media({ settings, settingsNav }: MediaPageProps) {
    const form = useAppForm<MediaFormData>({
        defaults: {
            max_file_name_length: settings.max_file_name_length,
            max_upload_size: settings.max_upload_size,
            allowed_file_types: settings.allowed_file_types,
            image_optimization: settings.image_optimization,
            image_quality: settings.image_quality,
            thumbnail_width: settings.thumbnail_width,
            small_width: settings.small_width,
            medium_width: settings.medium_width,
            large_width: settings.large_width,
            xlarge_width: settings.xlarge_width,
            delete_trashed: settings.delete_trashed,
            delete_trashed_days: settings.delete_trashed_days,
        },
        rememberKey: 'master-settings.media',
        dirtyGuard: { enabled: true },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(MasterSettingsController.update('media'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Media settings updated',
                description: 'Your media settings have been saved successfully.',
            },
        });
    };

    return (
        <SettingsLayout settingsNav={settingsNav} breadcrumbs={breadcrumbs} title="Master Settings" description="Manage platform-level configuration.">
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <form noValidate className="flex flex-col gap-6" onSubmit={handleSubmit}>
                    {form.dirtyGuardDialog}
                    <FormErrorSummary errors={form.errors} minMessages={2} />

                    <Card>
                        <CardHeader>
                            <CardTitle>Upload Limits</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                    <Field data-invalid={form.invalid('max_file_name_length') || undefined}>
                                        <FieldLabel htmlFor="max_file_name_length">Max File Name Length</FieldLabel>
                                        <Input
                                            id="max_file_name_length"
                                            type="number"
                                            value={form.data.max_file_name_length}
                                            onChange={(e) => form.setField('max_file_name_length', e.target.value)}
                                            onBlur={() => form.touch('max_file_name_length')}
                                            aria-invalid={form.invalid('max_file_name_length') || undefined}
                                            placeholder="255"
                                            size="comfortable"
                                        />
                                        <FieldError>{form.error('max_file_name_length')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('max_upload_size') || undefined}>
                                        <FieldLabel htmlFor="max_upload_size">Max Upload Size (MB)</FieldLabel>
                                        <Input
                                            id="max_upload_size"
                                            type="number"
                                            value={form.data.max_upload_size}
                                            onChange={(e) => form.setField('max_upload_size', e.target.value)}
                                            onBlur={() => form.touch('max_upload_size')}
                                            aria-invalid={form.invalid('max_upload_size') || undefined}
                                            placeholder="10"
                                            size="comfortable"
                                        />
                                        <FieldError>{form.error('max_upload_size')}</FieldError>
                                    </Field>
                                </FieldGroup>

                                <Field data-invalid={form.invalid('allowed_file_types') || undefined}>
                                    <FieldLabel htmlFor="allowed_file_types">Allowed File Types</FieldLabel>
                                    <FieldDescription>Comma-separated list of allowed file extensions (e.g., jpg,png,gif,pdf).</FieldDescription>
                                    <Input
                                        id="allowed_file_types"
                                        value={form.data.allowed_file_types}
                                        onChange={(e) => form.setField('allowed_file_types', e.target.value)}
                                        onBlur={() => form.touch('allowed_file_types')}
                                        aria-invalid={form.invalid('allowed_file_types') || undefined}
                                        placeholder="jpg,png,gif,pdf,doc,docx,xls,xlsx"
                                        size="comfortable"
                                    />
                                    <FieldError>{form.error('allowed_file_types')}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Image Settings</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field>
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="space-y-1">
                                            <FieldLabel htmlFor="image_optimization">Image Optimization</FieldLabel>
                                            <FieldDescription>Automatically optimize uploaded images to reduce file size.</FieldDescription>
                                        </div>
                                        <Switch
                                            id="image_optimization"
                                            checked={form.data.image_optimization}
                                            onCheckedChange={(checked) => form.setField('image_optimization', checked === true)}
                                            size="comfortable"
                                        />
                                    </div>
                                </Field>

                                {form.data.image_optimization ? (
                                    <Field data-invalid={form.invalid('image_quality') || undefined}>
                                        <FieldLabel htmlFor="image_quality">Image Quality (%)</FieldLabel>
                                        <Input
                                            id="image_quality"
                                            type="number"
                                            min="1"
                                            max="100"
                                            value={form.data.image_quality}
                                            onChange={(e) => form.setField('image_quality', e.target.value)}
                                            onBlur={() => form.touch('image_quality')}
                                            aria-invalid={form.invalid('image_quality') || undefined}
                                            placeholder="80"
                                            size="comfortable"
                                        />
                                        <FieldError>{form.error('image_quality')}</FieldError>
                                    </Field>
                                ) : null}

                                <Separator />

                                <p className="text-sm font-medium">Image Dimensions (width in pixels)</p>

                                <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                    <Field data-invalid={form.invalid('thumbnail_width') || undefined}>
                                        <FieldLabel htmlFor="thumbnail_width">Thumbnail</FieldLabel>
                                        <Input
                                            id="thumbnail_width"
                                            type="number"
                                            value={form.data.thumbnail_width}
                                            onChange={(e) => form.setField('thumbnail_width', e.target.value)}
                                            onBlur={() => form.touch('thumbnail_width')}
                                            aria-invalid={form.invalid('thumbnail_width') || undefined}
                                            placeholder="150"
                                            size="comfortable"
                                        />
                                        <FieldError>{form.error('thumbnail_width')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('small_width') || undefined}>
                                        <FieldLabel htmlFor="small_width">Small</FieldLabel>
                                        <Input
                                            id="small_width"
                                            type="number"
                                            value={form.data.small_width}
                                            onChange={(e) => form.setField('small_width', e.target.value)}
                                            onBlur={() => form.touch('small_width')}
                                            aria-invalid={form.invalid('small_width') || undefined}
                                            placeholder="300"
                                            size="comfortable"
                                        />
                                        <FieldError>{form.error('small_width')}</FieldError>
                                    </Field>
                                </FieldGroup>

                                <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                    <Field data-invalid={form.invalid('medium_width') || undefined}>
                                        <FieldLabel htmlFor="medium_width">Medium</FieldLabel>
                                        <Input
                                            id="medium_width"
                                            type="number"
                                            value={form.data.medium_width}
                                            onChange={(e) => form.setField('medium_width', e.target.value)}
                                            onBlur={() => form.touch('medium_width')}
                                            aria-invalid={form.invalid('medium_width') || undefined}
                                            placeholder="600"
                                            size="comfortable"
                                        />
                                        <FieldError>{form.error('medium_width')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('large_width') || undefined}>
                                        <FieldLabel htmlFor="large_width">Large</FieldLabel>
                                        <Input
                                            id="large_width"
                                            type="number"
                                            value={form.data.large_width}
                                            onChange={(e) => form.setField('large_width', e.target.value)}
                                            onBlur={() => form.touch('large_width')}
                                            aria-invalid={form.invalid('large_width') || undefined}
                                            placeholder="1024"
                                            size="comfortable"
                                        />
                                        <FieldError>{form.error('large_width')}</FieldError>
                                    </Field>
                                </FieldGroup>

                                <Field data-invalid={form.invalid('xlarge_width') || undefined}>
                                    <FieldLabel htmlFor="xlarge_width">Extra Large</FieldLabel>
                                    <Input
                                        id="xlarge_width"
                                        type="number"
                                        value={form.data.xlarge_width}
                                        onChange={(e) => form.setField('xlarge_width', e.target.value)}
                                        onBlur={() => form.touch('xlarge_width')}
                                        aria-invalid={form.invalid('xlarge_width') || undefined}
                                        placeholder="1920"
                                        size="comfortable"
                                    />
                                    <FieldError>{form.error('xlarge_width')}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Trash Settings</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field>
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="space-y-1">
                                            <FieldLabel htmlFor="delete_trashed">Auto-Delete Trashed Media</FieldLabel>
                                            <FieldDescription>Automatically permanently delete trashed media after a set number of days.</FieldDescription>
                                        </div>
                                        <Switch
                                            id="delete_trashed"
                                            checked={form.data.delete_trashed}
                                            onCheckedChange={(checked) => form.setField('delete_trashed', checked === true)}
                                            size="comfortable"
                                        />
                                    </div>
                                </Field>

                                {form.data.delete_trashed ? (
                                    <Field data-invalid={form.invalid('delete_trashed_days') || undefined}>
                                        <FieldLabel htmlFor="delete_trashed_days">Days Before Deletion</FieldLabel>
                                        <Input
                                            id="delete_trashed_days"
                                            type="number"
                                            min="1"
                                            value={form.data.delete_trashed_days}
                                            onChange={(e) => form.setField('delete_trashed_days', e.target.value)}
                                            onBlur={() => form.touch('delete_trashed_days')}
                                            aria-invalid={form.invalid('delete_trashed_days') || undefined}
                                            placeholder="30"
                                            size="comfortable"
                                        />
                                        <FieldError>{form.error('delete_trashed_days')}</FieldError>
                                    </Field>
                                ) : null}
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? <Spinner /> : <SaveIcon data-icon="inline-start" />}
                        {form.processing ? 'Saving...' : 'Save Settings'}
                    </Button>
                </form>
            </div>
        </SettingsLayout>
    );
}
