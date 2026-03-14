import { Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    ArrowLeftIcon,
    CalendarIcon,
    FileTextIcon,
    MailIcon,
    PencilIcon,
    RefreshCwIcon,
    Trash2Icon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { EmailTemplateShowPageProps } from '@/types/email';

function DetailRow({
    label,
    value,
    icon,
}: {
    label: string;
    value: ReactNode;
    icon?: ReactNode;
}) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    return (
        <div className="flex items-start gap-3 py-2">
            {icon ? <span className="mt-0.5 text-muted-foreground">{icon}</span> : null}
            <div className="flex min-w-0 flex-col gap-0.5">
                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </span>
                <span className="text-sm text-foreground">{value}</span>
            </div>
        </div>
    );
}

export default function EmailTemplatesShow({
    emailTemplate,
}: EmailTemplateShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEditEmailTemplates = page.props.auth.abilities.editEmailTemplates;
    const canDeleteEmailTemplates =
        page.props.auth.abilities.deleteEmailTemplates;
    const canRestoreEmailTemplates =
        page.props.auth.abilities.restoreEmailTemplates;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        {
            title: 'Email Templates',
            href: route('app.masters.email.templates.index'),
        },
        {
            title: emailTemplate.name,
            href: route('app.masters.email.templates.show', emailTemplate.id),
        },
    ];

    const handleRestore = () => {
        if (!window.confirm(`Restore "${emailTemplate.name}"?`)) return;

        router.patch(
            route('app.masters.email.templates.restore', emailTemplate.id),
            {},
            { preserveScroll: true },
        );
    };

    const handleDelete = () => {
        if (!window.confirm(`Move "${emailTemplate.name}" to trash?`)) return;

        router.delete(
            route('app.masters.email.templates.destroy', emailTemplate.id),
            { preserveScroll: true },
        );
    };

    const handleForceDelete = () => {
        if (
            !window.confirm(
                `⚠️ Permanently delete "${emailTemplate.name}"? This cannot be undone!`,
            )
        )
            return;

        router.delete(
            route('app.masters.email.templates.force-delete', emailTemplate.id),
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={emailTemplate.name}
            description="Review the reusable content and delivery mapping for this template."
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('app.masters.email.templates.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>

                    {!emailTemplate.is_trashed && canEditEmailTemplates ? (
                        <Button asChild>
                            <Link
                                href={route(
                                    'app.masters.email.templates.edit',
                                    emailTemplate.id,
                                )}
                            >
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    ) : null}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-col gap-2">
                                <div className="flex flex-wrap items-center gap-3">
                                    <h2 className="text-xl font-semibold text-foreground">
                                        {emailTemplate.name}
                                    </h2>
                                    <Badge
                                        variant={
                                            (emailTemplate.status_badge as React.ComponentProps<typeof Badge>['variant']) ??
                                            'outline'
                                        }
                                    >
                                        {emailTemplate.status_label}
                                    </Badge>
                                    <Badge variant="secondary">
                                        {emailTemplate.is_raw ? 'Raw HTML' : 'Plain text'}
                                    </Badge>
                                    {emailTemplate.is_trashed ? (
                                        <Badge variant="destructive">
                                            Trashed
                                        </Badge>
                                    ) : null}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {emailTemplate.subject}
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {emailTemplate.is_trashed && canRestoreEmailTemplates ? (
                                    <>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={handleRestore}
                                        >
                                            <RefreshCwIcon data-icon="inline-start" />
                                            Restore
                                        </Button>
                                        {canDeleteEmailTemplates ? (
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={handleForceDelete}
                                            >
                                                <Trash2Icon data-icon="inline-start" />
                                                Delete Permanently
                                            </Button>
                                        ) : null}
                                    </>
                                ) : null}

                                {!emailTemplate.is_trashed && canDeleteEmailTemplates ? (
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={handleDelete}
                                    >
                                        <Trash2Icon data-icon="inline-start" />
                                        Trash
                                    </Button>
                                ) : null}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {emailTemplate.is_trashed ? (
                    <Alert variant="destructive">
                        <AlertTriangleIcon className="size-4" />
                        <AlertTitle>This template is in trash</AlertTitle>
                        <AlertDescription>
                            Restore it before using it in active email workflows again.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Message body</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="rounded-lg border bg-muted/20 p-4">
                                    <pre className="whitespace-pre-wrap break-words font-sans text-sm text-foreground">
                                        {emailTemplate.message}
                                    </pre>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="flex flex-col gap-6 lg:col-span-1">
                        <Card>
                            <CardHeader>
                                <CardTitle>Delivery details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="divide-y">
                                    <DetailRow
                                        label="Provider"
                                        value={emailTemplate.provider_name}
                                        icon={<MailIcon className="size-4" />}
                                    />
                                    <DetailRow
                                        label="Recipients"
                                        value={
                                            emailTemplate.send_to_list.length > 0 ? (
                                                <div className="flex flex-wrap gap-2">
                                                    {emailTemplate.send_to_list.map(
                                                        (recipient) => (
                                                            <Badge
                                                                key={recipient}
                                                                variant="outline"
                                                            >
                                                                {recipient}
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                            ) : (
                                                'No default recipients'
                                            )
                                        }
                                    />
                                    <DetailRow
                                        label="Created"
                                        value={emailTemplate.created_at_formatted}
                                        icon={<CalendarIcon className="size-4" />}
                                    />
                                    <DetailRow
                                        label="Updated"
                                        value={emailTemplate.updated_at_formatted}
                                    />
                                    <DetailRow
                                        label="Created by"
                                        value={emailTemplate.created_by_name}
                                    />
                                    <DetailRow
                                        label="Updated by"
                                        value={emailTemplate.updated_by_name}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Summary</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-start gap-3 rounded-lg border bg-muted/20 p-4">
                                    <FileTextIcon className="mt-0.5 size-4 text-muted-foreground" />
                                    <div className="space-y-1 text-sm text-muted-foreground">
                                        <p>{emailTemplate.template_info}</p>
                                        <p>
                                            {emailTemplate.is_raw
                                                ? 'This template stores raw HTML content.'
                                                : 'This template stores plain text content.'}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
