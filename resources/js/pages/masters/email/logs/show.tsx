import { Link } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    CalendarIcon,
    FileTextIcon,
    MailIcon,
    TriangleAlertIcon,
    UserIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EmailLogShowPageProps } from '@/types/email';

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

export default function EmailLogsShow({ emailLog }: EmailLogShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Email Logs', href: route('app.masters.email.logs.index') },
        {
            title: `Email #${emailLog.id}`,
            href: route('app.masters.email.logs.show', emailLog.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={emailLog.subject}
            description="Inspect the recipients, content, and delivery status for this sent email."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.masters.email.logs.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <div className="flex flex-col gap-6">
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="space-y-2">
                                <div className="flex flex-wrap items-center gap-3">
                                    <h2 className="text-xl font-semibold text-foreground">
                                        {emailLog.subject}
                                    </h2>
                                    <Badge
                                        variant={
                                            (emailLog.status_badge as React.ComponentProps<
                                                typeof Badge
                                            >['variant']) ?? 'outline'
                                        }
                                    >
                                        {emailLog.status_label}
                                    </Badge>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {emailLog.provider_name ||
                                        'Unknown provider'}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Message body</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="rounded-lg border bg-muted/20 p-4">
                                    <pre className="font-sans text-sm break-words whitespace-pre-wrap text-foreground">
                                        {emailLog.body || 'No body stored.'}
                                    </pre>
                                </div>
                            </CardContent>
                        </Card>

                        {emailLog.context &&
                        Object.keys(emailLog.context).length > 0 ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Context</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="rounded-lg border bg-muted/20 p-4">
                                        <pre className="overflow-x-auto text-sm break-words whitespace-pre-wrap text-foreground">
                                            {JSON.stringify(
                                                emailLog.context,
                                                null,
                                                2,
                                            )}
                                        </pre>
                                    </div>
                                </CardContent>
                            </Card>
                        ) : null}
                    </div>

                    <div className="flex flex-col gap-6 lg:col-span-1">
                        <Card>
                            <CardHeader>
                                <CardTitle>Delivery details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="divide-y">
                                    <DetailRow
                                        label="Template"
                                        value={emailLog.template_name}
                                        icon={
                                            <FileTextIcon className="size-4" />
                                        }
                                    />
                                    <DetailRow
                                        label="Provider"
                                        value={emailLog.provider_name}
                                        icon={<MailIcon className="size-4" />}
                                    />
                                    <DetailRow
                                        label="Recipients"
                                        value={
                                            emailLog.recipients.length > 0 ? (
                                                <div className="flex flex-wrap gap-2">
                                                    {emailLog.recipients.map(
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
                                                'No recipients recorded'
                                            )
                                        }
                                    />
                                    <DetailRow
                                        label="Sent at"
                                        value={emailLog.sent_at_formatted}
                                        icon={
                                            <CalendarIcon className="size-4" />
                                        }
                                    />
                                    <DetailRow
                                        label="Logged at"
                                        value={emailLog.created_at_formatted}
                                    />
                                    <DetailRow
                                        label="Sender"
                                        value={emailLog.sender_name}
                                        icon={<UserIcon className="size-4" />}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {emailLog.error_message ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Failure details</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-start gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
                                        <TriangleAlertIcon className="mt-0.5 size-4 shrink-0" />
                                        <span>{emailLog.error_message}</span>
                                    </div>
                                </CardContent>
                            </Card>
                        ) : null}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
