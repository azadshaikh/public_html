import { Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    ArrowLeftIcon,
    CalendarIcon,
    KeyRoundIcon,
    MailIcon,
    PencilIcon,
    RefreshCwIcon,
    ServerIcon,
    Trash2Icon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { EmailProviderShowPageProps } from '@/types/email';

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
            {icon ? (
                <span className="mt-0.5 text-muted-foreground">{icon}</span>
            ) : null}
            <div className="flex min-w-0 flex-col gap-0.5">
                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </span>
                <span className="text-sm text-foreground">{value}</span>
            </div>
        </div>
    );
}

export default function EmailProvidersShow({
    emailProvider,
}: EmailProviderShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEditEmailProviders = page.props.auth.abilities.editEmailProviders;
    const canDeleteEmailProviders =
        page.props.auth.abilities.deleteEmailProviders;
    const canRestoreEmailProviders =
        page.props.auth.abilities.restoreEmailProviders;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        {
            title: 'Email Providers',
            href: route('app.masters.email.providers.index'),
        },
        {
            title: emailProvider.name,
            href: route('app.masters.email.providers.show', emailProvider.id),
        },
    ];

    const handleRestore = () => {
        if (!window.confirm(`Restore "${emailProvider.name}"?`)) return;

        router.patch(
            route('app.masters.email.providers.restore', emailProvider.id),
            {},
            { preserveScroll: true },
        );
    };

    const handleDelete = () => {
        if (!window.confirm(`Move "${emailProvider.name}" to trash?`)) return;

        router.delete(
            route('app.masters.email.providers.destroy', emailProvider.id),
            { preserveScroll: true },
        );
    };

    const handleForceDelete = () => {
        if (
            !window.confirm(
                `⚠️ Permanently delete "${emailProvider.name}"? This cannot be undone!`,
            )
        )
            return;

        router.delete(
            route('app.masters.email.providers.force-delete', emailProvider.id),
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={emailProvider.name}
            description="Review sender identity, SMTP settings, and operational status."
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('app.masters.email.providers.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>

                    {!emailProvider.is_trashed && canEditEmailProviders ? (
                        <Button asChild>
                            <Link
                                href={route(
                                    'app.masters.email.providers.edit',
                                    emailProvider.id,
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
                                        {emailProvider.name}
                                    </h2>
                                    <Badge
                                        variant={
                                            (emailProvider.status_badge as React.ComponentProps<
                                                typeof Badge
                                            >['variant']) ?? 'outline'
                                        }
                                    >
                                        {emailProvider.status_label}
                                    </Badge>
                                    {emailProvider.is_trashed ? (
                                        <Badge variant="destructive">
                                            Trashed
                                        </Badge>
                                    ) : null}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {emailProvider.sender_name ||
                                        'No sender name'}{' '}
                                    ·{' '}
                                    {emailProvider.sender_email ||
                                        'No sender email'}
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {emailProvider.is_trashed &&
                                canRestoreEmailProviders ? (
                                    <>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={handleRestore}
                                        >
                                            <RefreshCwIcon data-icon="inline-start" />
                                            Restore
                                        </Button>
                                        {canDeleteEmailProviders ? (
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

                                {!emailProvider.is_trashed &&
                                canDeleteEmailProviders ? (
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

                {emailProvider.is_trashed ? (
                    <Alert variant="destructive">
                        <AlertTriangleIcon className="size-4" />
                        <AlertTitle>This provider is in trash</AlertTitle>
                        <AlertDescription>
                            Restore it before assigning it to active workflows
                            again.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>SMTP configuration</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="divide-y">
                                    <DetailRow
                                        label="SMTP host"
                                        value={emailProvider.smtp_host}
                                        icon={<ServerIcon className="size-4" />}
                                    />
                                    <DetailRow
                                        label="SMTP user"
                                        value={emailProvider.smtp_user}
                                        icon={<MailIcon className="size-4" />}
                                    />
                                    <DetailRow
                                        label="SMTP port"
                                        value={emailProvider.smtp_port}
                                    />
                                    <DetailRow
                                        label="Encryption"
                                        value={
                                            emailProvider.smtp_encryption_label
                                        }
                                    />
                                    <DetailRow
                                        label="Password"
                                        value={
                                            emailProvider.has_smtp_password
                                                ? 'Configured'
                                                : 'Not configured'
                                        }
                                        icon={
                                            <KeyRoundIcon className="size-4" />
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Sender details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="divide-y">
                                    <DetailRow
                                        label="Sender name"
                                        value={emailProvider.sender_name}
                                        icon={<MailIcon className="size-4" />}
                                    />
                                    <DetailRow
                                        label="Sender email"
                                        value={emailProvider.sender_email}
                                    />
                                    <DetailRow
                                        label="Reply-to"
                                        value={emailProvider.reply_to}
                                    />
                                    <DetailRow
                                        label="BCC"
                                        value={emailProvider.bcc}
                                    />
                                    <DetailRow
                                        label="Signature"
                                        value={
                                            emailProvider.signature ? (
                                                <pre className="font-sans text-sm whitespace-pre-wrap">
                                                    {emailProvider.signature}
                                                </pre>
                                            ) : null
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="flex flex-col gap-6 lg:col-span-1">
                        <Card>
                            <CardHeader>
                                <CardTitle>Metadata</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="divide-y">
                                    <DetailRow
                                        label="Sort order"
                                        value={emailProvider.order}
                                    />
                                    <DetailRow
                                        label="Created"
                                        value={
                                            emailProvider.created_at_formatted
                                        }
                                        icon={
                                            <CalendarIcon className="size-4" />
                                        }
                                    />
                                    <DetailRow
                                        label="Updated"
                                        value={
                                            emailProvider.updated_at_formatted
                                        }
                                    />
                                    <DetailRow
                                        label="Created by"
                                        value={emailProvider.created_by_name}
                                    />
                                    <DetailRow
                                        label="Updated by"
                                        value={emailProvider.updated_by_name}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {emailProvider.description ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Description</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                        {emailProvider.description}
                                    </p>
                                </CardContent>
                            </Card>
                        ) : null}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
