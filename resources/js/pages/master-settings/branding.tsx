import { SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { MediaPickerUrlInput } from '@/components/media/media-picker-url-input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import { formValidators } from '@/lib/forms';
import type { BreadcrumbItem, SettingsNavItem } from '@/types';
import type {
    MediaListItem,
    MediaPickerFilters,
    UploadSettings,
} from '@/types/media';
import type { PaginatedData } from '@/types/pagination';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Settings', href: route('app.masters.settings.index') },
    { title: 'Branding', href: route('app.masters.settings.branding') },
];

type BrandingPageProps = {
    settings: {
        brand_name: string;
        brand_website: string;
        logo: string;
        icon: string;
    };
    settingsNav: SettingsNavItem[];
    pickerMedia: PaginatedData<MediaListItem> | null;
    pickerFilters: MediaPickerFilters | null;
    uploadSettings: UploadSettings | null;
    pickerStatistics?: {
        total: number;
        trash: number;
    } | null;
};

type BrandingFormData = {
    brand_name: string;
    brand_website: string;
    logo: string;
    icon: string;
};

export default function Branding({
    settings,
    settingsNav,
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerStatistics = null,
}: BrandingPageProps) {
    const form = useAppForm<BrandingFormData>({
        defaults: {
            brand_name: settings.brand_name,
            brand_website: settings.brand_website,
            logo: settings.logo,
            icon: settings.icon,
        },
        rememberKey: 'master-settings.branding',
        dirtyGuard: { enabled: true },
        rules: {
            brand_name: [formValidators.required('Brand name')],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('put', route('app.masters.settings.update', 'branding'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Branding settings updated',
                description:
                    'Your branding settings have been saved successfully.',
            },
        });
    };

    const pickerAction = route('app.masters.settings.branding');

    return (
        <SettingsLayout
            settingsNav={settingsNav}
            breadcrumbs={breadcrumbs}
            title="Master Settings"
            description="Manage platform-level configuration."
        >
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <form
                    noValidate
                    className="flex flex-col gap-6"
                    onSubmit={handleSubmit}
                >
                    {form.dirtyGuardDialog}
                    <FormErrorSummary errors={form.errors} minMessages={2} />

                    <Card>
                        <CardHeader>
                            <CardTitle>Branding</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('brand_name') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="brand_name">
                                        Brand Name{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <Input
                                        id="brand_name"
                                        value={form.data.brand_name}
                                        onChange={(e) =>
                                            form.setField(
                                                'brand_name',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('brand_name')}
                                        aria-invalid={
                                            form.invalid('brand_name') ||
                                            undefined
                                        }
                                        placeholder="Enter brand name"
                                        size="comfortable"
                                        autoFocus
                                    />
                                    <FieldError>
                                        {form.error('brand_name')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('brand_website') ||
                                        undefined
                                    }
                                >
                                    <FieldLabel htmlFor="brand_website">
                                        Brand Website
                                    </FieldLabel>
                                    <Input
                                        id="brand_website"
                                        type="url"
                                        value={form.data.brand_website}
                                        onChange={(e) =>
                                            form.setField(
                                                'brand_website',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            form.touch('brand_website')
                                        }
                                        aria-invalid={
                                            form.invalid('brand_website') ||
                                            undefined
                                        }
                                        placeholder="https://example.com"
                                        size="comfortable"
                                    />
                                    <FieldError>
                                        {form.error('brand_website')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('logo') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="logo">
                                        Logo URL
                                    </FieldLabel>
                                    <MediaPickerUrlInput
                                        id="logo"
                                        type="url"
                                        value={form.data.logo}
                                        onChange={(value) =>
                                            form.setField('logo', value)
                                        }
                                        onBlur={() => form.touch('logo')}
                                        aria-invalid={
                                            form.invalid('logo') || undefined
                                        }
                                        placeholder="Enter logo URL or path"
                                        pickerMedia={pickerMedia}
                                        pickerFilters={pickerFilters}
                                        uploadSettings={uploadSettings}
                                        pickerStatistics={pickerStatistics}
                                        pickerAction={pickerAction}
                                        dialogTitle="Select Logo"
                                        pickerButtonLabel="Select logo from media library"
                                        clearButtonLabel="Clear logo URL"
                                        showThumbnailPreview
                                        thumbnailAlt="Logo preview"
                                    />
                                    <FieldError>
                                        {form.error('logo')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('icon') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="icon">
                                        Icon URL
                                    </FieldLabel>
                                    <MediaPickerUrlInput
                                        id="icon"
                                        type="url"
                                        value={form.data.icon}
                                        onChange={(value) =>
                                            form.setField('icon', value)
                                        }
                                        onBlur={() => form.touch('icon')}
                                        aria-invalid={
                                            form.invalid('icon') || undefined
                                        }
                                        placeholder="Enter icon URL or path"
                                        pickerMedia={pickerMedia}
                                        pickerFilters={pickerFilters}
                                        uploadSettings={uploadSettings}
                                        pickerStatistics={pickerStatistics}
                                        pickerAction={pickerAction}
                                        dialogTitle="Select Icon"
                                        pickerButtonLabel="Select icon from media library"
                                        clearButtonLabel="Clear icon URL"
                                        showThumbnailPreview
                                        thumbnailAlt="Icon preview"
                                    />
                                    <FieldError>
                                        {form.error('icon')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? (
                            <Spinner />
                        ) : (
                            <SaveIcon data-icon="inline-start" />
                        )}
                        {form.processing ? 'Saving...' : 'Save Settings'}
                    </Button>
                </form>
            </div>
        </SettingsLayout>
    );
}
