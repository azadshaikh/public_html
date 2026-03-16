import {
    DownloadIcon,
    FileArchiveIcon,
    FileJsonIcon,
    SaveIcon,
    UploadIcon,
} from 'lucide-react';
import { useState } from 'react';
import type { ChangeEvent, FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import { getSeoSettingsBreadcrumbs, getSeoSettingsNav } from '../../../lib/seo-settings';
import type { ImportExportPageProps } from '../../../types/seo';

export default function SeoImportExportPage({ seoGroups }: ImportExportPageProps) {
    const [exporting, setExporting] = useState(false);
    const form = useAppForm<{ import_file: File | null }>({
        defaults: { import_file: null },
        rememberKey: 'seo.settings.import-export',
        dontRemember: ['import_file'],
        rules: {
            import_file: [
                (value) => (value ? undefined : 'A JSON export file is required.'),
            ],
        },
    });

    const handleExport = async () => {
        setExporting(true);

        try {
            const response = await fetch(route('seo.settings.export'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({}),
            });

            const result = (await response.json()) as {
                status: string;
                message: string;
                jsondata?: string;
            };

            if (!response.ok || result.status !== 'success' || !result.jsondata) {
                throw new Error(result.message || 'Export failed.');
            }

            const blob = new Blob([result.jsondata], {
                type: 'application/json;charset=utf-8',
            });
            const objectUrl = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = 'seo-settings.json';
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(objectUrl);

            showAppToast({
                title: 'SEO settings exported',
                description: 'Your JSON backup download has started.',
            });
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Export failed',
                description:
                    error instanceof Error
                        ? error.message
                        : 'An unexpected error occurred while exporting settings.',
            });
        } finally {
            setExporting(false);
        }
    };

    const handleImportChange = (event: ChangeEvent<HTMLInputElement>) => {
        form.setField('import_file', event.target.files?.[0] ?? null);
        form.touch('import_file');
    };

    const handleImport = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('seo.settings.import'), {
            preserveScroll: true,
            forceFormData: true,
            successToast: {
                title: 'SEO settings imported',
                description: 'The uploaded backup file was processed successfully.',
            },
        });
    };

    return (
        <SettingsLayout
            settingsNav={getSeoSettingsNav()}
            breadcrumbs={getSeoSettingsBreadcrumbs('Import & Export')}
            title="Import & Export"
            description="Back up the current SEO configuration and restore it when you need to migrate or recover settings."
            activeSlug="importexport"
            railLabel="SEO settings"
        >
            <div className="flex flex-col gap-6">
                <Alert>
                    <FileArchiveIcon className="size-4" />
                    <AlertTitle>Backup before major changes</AlertTitle>
                    <AlertDescription>
                        Exporting gives you a portable JSON snapshot of your SEO configuration, including sitemap and robots data.
                    </AlertDescription>
                </Alert>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <DownloadIcon className="size-4 text-muted-foreground" />
                                <CardTitle>Export settings</CardTitle>
                            </div>
                            <CardDescription>
                                Download all SEO-related settings as a JSON file for safe keeping.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4 text-sm text-muted-foreground">
                            <p>
                                The export includes {seoGroups.length} SEO-related setting groups currently stored in the database.
                            </p>
                            <div className="rounded-xl border bg-muted/30 p-4">
                                <div className="mb-2 font-medium text-foreground">Included groups</div>
                                <div className="flex flex-wrap gap-2">
                                    {seoGroups.map((group) => (
                                        <span
                                            key={group}
                                            className="rounded-full border bg-background px-3 py-1 text-xs text-foreground"
                                        >
                                            {group}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        </CardContent>
                        <CardFooter>
                            <Button type="button" onClick={handleExport} disabled={exporting}>
                                {exporting ? (
                                    <Spinner className="mr-2 size-4" />
                                ) : (
                                    <SaveIcon data-icon="inline-start" />
                                )}
                                Export SEO settings
                            </Button>
                        </CardFooter>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <UploadIcon className="size-4 text-muted-foreground" />
                                <CardTitle>Import settings</CardTitle>
                            </div>
                            <CardDescription>
                                Upload a previously exported JSON file to restore settings.
                            </CardDescription>
                        </CardHeader>
                        <form onSubmit={handleImport} noValidate>
                            {form.dirtyGuardDialog}
                            <CardContent className="flex flex-col gap-4">
                                <FormErrorSummary errors={form.errors} minMessages={2} />
                                <Field data-invalid={form.invalid('import_file') || undefined}>
                                    <FieldLabel htmlFor="import_file">JSON backup file</FieldLabel>
                                    <Input
                                        id="import_file"
                                        type="file"
                                        accept=".json,application/json"
                                        onChange={handleImportChange}
                                        aria-invalid={form.invalid('import_file') || undefined}
                                    />
                                    <FieldDescription>
                                        Only files exported from this SEO settings area should be imported.
                                    </FieldDescription>
                                    <FieldError>{form.error('import_file')}</FieldError>
                                </Field>
                                <Alert>
                                    <FileJsonIcon className="size-4" />
                                    <AlertTitle>Import notes</AlertTitle>
                                    <AlertDescription>
                                        Existing SEO keys are updated in place. Missing keys remain unchanged.
                                    </AlertDescription>
                                </Alert>
                            </CardContent>
                            <CardFooter>
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? (
                                        <Spinner className="mr-2 size-4" />
                                    ) : (
                                        <UploadIcon data-icon="inline-start" />
                                    )}
                                    Import SEO settings
                                </Button>
                            </CardFooter>
                        </form>
                    </Card>
                </div>
            </div>
        </SettingsLayout>
    );
}
