import { DownloadIcon, UploadIcon } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import SettingsController from '@/actions/App/Http/Controllers/SettingsController';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import SettingsLayout from '@/layouts/settings-layout';
import type { BreadcrumbItem, SettingsNavItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: SettingsController.index() },
    { title: 'Import / Export', href: SettingsController.importExport() },
];

type ImportExportPageProps = {
    settingsNav: SettingsNavItem[];
};

export default function ImportExport({ settingsNav }: ImportExportPageProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [isExporting, setIsExporting] = useState(false);
    const [isImporting, setIsImporting] = useState(false);
    const [importError, setImportError] = useState<string | null>(null);

    const handleExport = async () => {
        setIsExporting(true);

        try {
            const response = await fetch(SettingsController.exportSettings.url(), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ export_options: 'all' }),
            });

            const data = await response.json();

            if (data.status === 'success' && data.jsondata) {
                const blob = new Blob([data.jsondata], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `settings-export-${new Date().toISOString().slice(0, 10)}.json`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);

                showAppToast({
                    title: 'Settings exported',
                    description: 'Your settings have been exported successfully.',
                });
            }
        } catch {
            showAppToast({
                variant: 'error',
                title: 'Export failed',
                description: 'An error occurred while exporting settings.',
            });
        } finally {
            setIsExporting(false);
        }
    };

    const handleImport = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setImportError(null);

        const file = fileInputRef.current?.files?.[0];

        if (!file) {
            setImportError('Please select a JSON file to import.');
            return;
        }

        if (!file.name.endsWith('.json')) {
            setImportError('Please upload a valid JSON file.');
            return;
        }

        setIsImporting(true);

        try {
            const formData = new FormData();
            formData.append('import_file', file);

            const response = await fetch(SettingsController.importSettings.url(), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                    Accept: 'application/json',
                },
                body: formData,
            });

            const data = await response.json();

            if (data.status === 1) {
                showAppToast({
                    title: 'Settings imported',
                    description: data.message ?? 'Settings have been imported successfully.',
                });

                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            } else {
                setImportError(data.message ?? 'Import failed. Please check the file and try again.');
            }
        } catch {
            setImportError('An error occurred while importing settings.');
        } finally {
            setIsImporting(false);
        }
    };

    return (
        <SettingsLayout settingsNav={settingsNav} breadcrumbs={breadcrumbs} title="Settings" description="Manage your application settings.">
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Export Settings</CardTitle>
                    </CardHeader>

                    <CardContent>
                        <FieldGroup>
                            <FieldDescription>
                                Download all settings as a JSON file. This file can be used to restore settings or migrate them to another instance.
                            </FieldDescription>

                            <Button type="button" variant="outline" onClick={handleExport} disabled={isExporting}>
                                {isExporting ? <Spinner /> : <DownloadIcon data-icon="inline-start" />}
                                {isExporting ? 'Exporting...' : 'Export Settings'}
                            </Button>
                        </FieldGroup>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Import Settings</CardTitle>
                    </CardHeader>

                    <CardContent>
                        <form noValidate onSubmit={handleImport}>
                            <FieldGroup>
                                <FieldDescription>
                                    Upload a JSON settings file to restore or apply settings. This will overwrite any matching existing settings.
                                </FieldDescription>

                                <Field data-invalid={importError ? true : undefined}>
                                    <FieldLabel htmlFor="import_file">Settings File</FieldLabel>
                                    <Input
                                        ref={fileInputRef}
                                        id="import_file"
                                        type="file"
                                        accept=".json"
                                        size="comfortable"
                                    />
                                    {importError ? <FieldError>{importError}</FieldError> : null}
                                </Field>

                                <Button type="submit" variant="outline" disabled={isImporting}>
                                    {isImporting ? <Spinner /> : <UploadIcon data-icon="inline-start" />}
                                    {isImporting ? 'Importing...' : 'Import Settings'}
                                </Button>
                            </FieldGroup>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
