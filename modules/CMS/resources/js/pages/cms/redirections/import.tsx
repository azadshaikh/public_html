import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, DownloadIcon, UploadIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import AppLayout from '@/layouts/app-layout';
import { formValidators } from '@/lib/forms';
import type { BreadcrumbItem } from '@/types';
import type { RedirectionImportFormValues } from '../../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Redirections', href: route('cms.redirections.index') },
    { title: 'Import', href: route('cms.redirections.import.form') },
];

const emptyValues: RedirectionImportFormValues = {
    file: null,
    skip_duplicates: true,
    update_existing: false,
};

const csvColumns = [
    ['source_url', 'Required', 'The incoming path to match.', '/old-page'],
    ['target_url', 'Required', 'The destination path or URL.', '/new-page'],
    ['redirect_type', 'Optional', 'HTTP status code. Defaults to 301.', '301'],
    [
        'url_type',
        'Optional',
        'internal or external. Defaults to internal.',
        'internal',
    ],
    [
        'match_type',
        'Optional',
        'exact, wildcard, or regex. Defaults to exact.',
        'wildcard',
    ],
    ['status', 'Optional', 'active or inactive. Defaults to active.', 'active'],
    ['notes', 'Optional', 'Internal notes for your team.', 'Migration cleanup'],
    [
        'expires_at',
        'Optional',
        'ISO datetime for expiration.',
        '2026-12-31T23:59:59',
    ],
] as const;

export default function RedirectionsImport() {
    const form = useAppForm<RedirectionImportFormValues>({
        defaults: emptyValues,
        rememberKey: 'cms.redirections.import.form',
        dirtyGuard: { enabled: true },
        rules: {
            file: [formValidators.required('CSV file')],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('cms.redirections.import'), {
            preserveScroll: true,
            forceFormData: true,
        });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Import Redirections"
            description="Upload redirect rules from a CSV file and merge them into the current catalog."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('cms.redirections.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to Redirections
                    </Link>
                </Button>
            }
        >
            <form
                className="flex flex-col gap-6"
                onSubmit={handleSubmit}
                noValidate
            >
                {form.dirtyGuardDialog}
                <FormErrorSummary errors={form.errors} minMessages={1} />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Import from CSV</CardTitle>
                            <CardDescription>
                                Upload a CSV file up to 10MB. Existing redirects
                                can be skipped or updated.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <Field
                                data-invalid={form.invalid('file') || undefined}
                            >
                                <FieldLabel htmlFor="file">
                                    CSV file{' '}
                                    <span className="text-destructive">*</span>
                                </FieldLabel>
                                <Input
                                    id="file"
                                    type="file"
                                    accept=".csv,.txt"
                                    aria-invalid={
                                        form.invalid('file') || undefined
                                    }
                                    onChange={(event) =>
                                        form.setField(
                                            'file',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                    onBlur={() => form.touch('file')}
                                />
                                <FieldDescription>
                                    Use a header row with the column names shown
                                    below.
                                </FieldDescription>
                                <FieldError>{form.error('file')}</FieldError>
                            </Field>

                            <Field orientation="horizontal">
                                <Checkbox
                                    checked={form.data.skip_duplicates}
                                    onCheckedChange={(checked) =>
                                        form.setField(
                                            'skip_duplicates',
                                            checked === true,
                                        )
                                    }
                                />
                                <div className="flex flex-col gap-1">
                                    <FieldLabel>
                                        Skip duplicate source URLs
                                    </FieldLabel>
                                    <FieldDescription>
                                        Keep existing redirect rules unchanged
                                        when the same source path already
                                        exists.
                                    </FieldDescription>
                                </div>
                            </Field>

                            <Field orientation="horizontal">
                                <Checkbox
                                    checked={form.data.update_existing}
                                    onCheckedChange={(checked) =>
                                        form.setField(
                                            'update_existing',
                                            checked === true,
                                        )
                                    }
                                />
                                <div className="flex flex-col gap-1">
                                    <FieldLabel>
                                        Update existing redirects
                                    </FieldLabel>
                                    <FieldDescription>
                                        Overwrite matching source URLs with the
                                        new CSV data instead of skipping them.
                                    </FieldDescription>
                                </div>
                            </Field>
                        </CardContent>
                        <CardFooter className="flex flex-wrap justify-end gap-3">
                            <Button variant="outline" type="button" asChild>
                                <Link href={route('cms.redirections.index')}>
                                    Cancel
                                </Link>
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? (
                                    <Spinner />
                                ) : (
                                    <UploadIcon data-icon="inline-start" />
                                )}
                                Import redirects
                            </Button>
                        </CardFooter>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Need a template?</CardTitle>
                            <CardDescription>
                                Export the current redirects as a CSV and use it
                                as a starting point.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button variant="outline" asChild>
                                <a href={route('cms.redirections.export')}>
                                    <DownloadIcon data-icon="inline-start" />
                                    Download current CSV
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>CSV format</CardTitle>
                        <CardDescription>
                            The importer reads a header row followed by one
                            redirect per line.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-6">
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="min-w-full divide-y divide-border text-sm">
                                <thead className="bg-muted/40 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Column
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Required
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Description
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Example
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {csvColumns.map(
                                        ([
                                            column,
                                            required,
                                            description,
                                            example,
                                        ]) => (
                                            <tr key={column}>
                                                <td className="px-4 py-3 font-mono text-xs">
                                                    {column}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {required}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {description}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                                    {example}
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="rounded-xl border bg-muted/30 p-4">
                            <p className="mb-3 text-sm font-medium">
                                Example CSV
                            </p>
                            <pre className="overflow-x-auto text-xs leading-6 text-muted-foreground">
                                source_url,target_url,redirect_type,url_type,match_type,status
                                /old-page,/new-page,301,internal,exact,active
                                /blog/*,/articles/$1,301,internal,wildcard,active
                                /pricing,https://example.com/pricing,302,external,exact,active
                            </pre>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </AppLayout>
    );
}
