import { Link, router } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    ClockIcon,
    CodeIcon,
    GlobeIcon,
    HistoryIcon,
    MonitorIcon,
    Trash2Icon,
    UserIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { ResourceFeedbackAlerts } from '@/components/resource/resource-feedback-alerts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ActivityLogsShowPageProps } from '@/types/activity-log';

function DetailRow({
    label,
    value,
    icon,
}: {
    label: string;
    value: ReactNode;
    icon?: ReactNode;
}) {
    if (!value) return null;

    return (
        <div className="flex items-start gap-3 py-2">
            {icon && (
                <span className="mt-0.5 text-muted-foreground">{icon}</span>
            )}
            <div className="flex min-w-0 flex-col gap-0.5">
                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </span>
                <span className="text-sm text-foreground">{value}</span>
            </div>
        </div>
    );
}

export default function ActivityLogsShow({
    activityLog,
    changes_summary,
    status,
    error,
}: ActivityLogsShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        {
            title: 'Activity Logs',
            href: route('app.logs.activity-logs.index'),
        },
        {
            title: 'Log Details',
            href: route('app.logs.activity-logs.show', activityLog.id),
        },
    ];

    const handleDelete = () => {
        if (!window.confirm('Move this activity log to trash?')) return;
        router.delete(route('app.logs.activity-logs.destroy', activityLog.id), {
            preserveScroll: true,
        });
    };

    const eventLabel = activityLog.event
        ? activityLog.event
              .replace(/_/g, ' ')
              .replace(/\b\w/g, (c) => c.toUpperCase())
        : 'Unknown';

    const entity = activityLog.properties?.entity as string | undefined;
    const module = activityLog.properties?.module as string | undefined;
    const requestMethod = activityLog.properties?.request_method as
        | string
        | undefined;

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Activity Log Details"
            description="View detailed information about this activity log entry"
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('app.logs.activity-logs.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <ResourceFeedbackAlerts
                    status={status}
                    statusIcon={<HistoryIcon />}
                    error={error}
                    errorIcon={<HistoryIcon />}
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Left: Main content */}
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        {/* Activity Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Activity Information</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <DetailRow
                                        label="Action"
                                        value={
                                            <Badge variant="outline">
                                                {eventLabel}
                                            </Badge>
                                        }
                                        icon={
                                            <HistoryIcon className="size-4" />
                                        }
                                    />
                                    <DetailRow
                                        label="Performed By"
                                        value={activityLog.causer_name}
                                        icon={<UserIcon className="size-4" />}
                                    />
                                    <DetailRow
                                        label="Model"
                                        value={
                                            activityLog.subject_type
                                                ? activityLog.subject_type
                                                      .split('\\')
                                                      .pop()
                                                      ?.replace(
                                                          /([A-Z])/g,
                                                          ' $1',
                                                      )
                                                      .trim()
                                                : 'N/A'
                                        }
                                    />
                                    <DetailRow
                                        label="Entity"
                                        value={entity ?? 'N/A'}
                                    />
                                    <DetailRow
                                        label="Module"
                                        value={module ?? 'N/A'}
                                    />
                                    <DetailRow
                                        label="Description"
                                        value={activityLog.description}
                                    />
                                    <DetailRow
                                        label="Subject"
                                        value={activityLog.subject_display}
                                    />
                                    <DetailRow
                                        label="Date & Time"
                                        value={
                                            <>
                                                {
                                                    activityLog.created_at_formatted
                                                }
                                                <span className="ml-1 text-xs text-muted-foreground">
                                                    ({activityLog.time_ago})
                                                </span>
                                            </>
                                        }
                                        icon={<ClockIcon className="size-4" />}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Changes Made */}
                        {changes_summary &&
                            Object.keys(changes_summary).length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Changes Made</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-sm">
                                                <thead>
                                                    <tr className="border-b">
                                                        <th className="pb-2 text-left font-medium text-muted-foreground">
                                                            Field
                                                        </th>
                                                        <th className="pb-2 text-left font-medium text-muted-foreground">
                                                            Previous Value
                                                        </th>
                                                        <th className="pb-2 text-left font-medium text-muted-foreground">
                                                            New Value
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {Object.entries(
                                                        changes_summary,
                                                    ).map(([field, change]) => (
                                                        <tr
                                                            key={field}
                                                            className="border-b last:border-0"
                                                        >
                                                            <td className="py-2 font-medium">
                                                                {field
                                                                    .replace(
                                                                        /_/g,
                                                                        ' ',
                                                                    )
                                                                    .replace(
                                                                        /\b\w/g,
                                                                        (c) =>
                                                                            c.toUpperCase(),
                                                                    )}
                                                            </td>
                                                            <td className="py-2">
                                                                <Badge
                                                                    variant="destructive"
                                                                    className="font-normal"
                                                                >
                                                                    {change.from ??
                                                                        'N/A'}
                                                                </Badge>
                                                            </td>
                                                            <td className="py-2">
                                                                <Badge
                                                                    variant="default"
                                                                    className="font-normal"
                                                                >
                                                                    {change.to ??
                                                                        'N/A'}
                                                                </Badge>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                        {/* Technical Details */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Technical Details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="divide-y">
                                    <DetailRow
                                        label="Log ID"
                                        value={
                                            <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                                {activityLog.id}
                                            </code>
                                        }
                                        icon={<CodeIcon className="size-4" />}
                                    />
                                    {activityLog.ip_address && (
                                        <DetailRow
                                            label="IP Address"
                                            value={
                                                <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                                    {activityLog.ip_address}
                                                </code>
                                            }
                                            icon={
                                                <GlobeIcon className="size-4" />
                                            }
                                        />
                                    )}
                                    {requestMethod && (
                                        <DetailRow
                                            label="Request Method"
                                            value={
                                                <Badge variant="secondary">
                                                    {requestMethod.toUpperCase()}
                                                </Badge>
                                            }
                                        />
                                    )}
                                    {activityLog.browser && (
                                        <DetailRow
                                            label="Browser"
                                            value={activityLog.browser}
                                            icon={
                                                <MonitorIcon className="size-4" />
                                            }
                                        />
                                    )}
                                    {activityLog.request_url && (
                                        <DetailRow
                                            label="Request URL"
                                            value={
                                                <span className="text-xs break-all">
                                                    {activityLog.request_url}
                                                </span>
                                            }
                                        />
                                    )}
                                    <DetailRow
                                        label="Log Channel"
                                        value={
                                            activityLog.log_name ?? 'default'
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right: Actions sidebar */}
                    <div className="flex flex-col gap-6 lg:col-span-1">
                        <Card className="sticky top-4">
                            <CardHeader>
                                <CardTitle>Actions</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-col gap-2">
                                    <Button
                                        variant="destructive"
                                        className="w-full"
                                        onClick={handleDelete}
                                    >
                                        <Trash2Icon data-icon="inline-start" />
                                        Delete Log
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
