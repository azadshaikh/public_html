import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    ArrowLeftIcon,
    BotIcon,
    ClockIcon,
    ExternalLinkIcon,
    GlobeIcon,
    LinkIcon,
    MonitorIcon,
    SearchXIcon,
    Trash2Icon,
    UserIcon,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { NotFoundLogsShowPageProps } from '@/types/not-found-log';

function getTypeBadge(isSuspicious: boolean, isBot: boolean) {
    if (isSuspicious) {
        return (
            <Badge variant="destructive" className="inline-flex items-center">
                <AlertTriangleIcon className="mr-1 size-3.5" />
                Suspicious
            </Badge>
        );
    }
    if (isBot) {
        return (
            <Badge variant="secondary" className="inline-flex items-center">
                <BotIcon className="mr-1 size-3.5" />
                Bot
            </Badge>
        );
    }
    return (
        <Badge variant="default" className="inline-flex items-center">
            <UserIcon className="mr-1 size-3.5" />
            Human
        </Badge>
    );
}

export default function NotFoundLogShow({
    notFoundLog,
    recentUrlStats,
    recentIpStats,
}: NotFoundLogsShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: '404 Logs', href: route('app.logs.not-found-logs.index') },
        { title: notFoundLog.url.substring(0, 40), href: '#' },
    ];

    const detailRows: {
        label: string;
        icon: React.ReactNode;
        value: React.ReactNode;
    }[] = [
        {
            label: 'URL',
            icon: <LinkIcon className="size-4" />,
            value: (
                <span className="font-mono text-sm break-all">
                    {notFoundLog.url}
                </span>
            ),
        },
        ...(notFoundLog.full_url
            ? [
                  {
                      label: 'Full URL',
                      icon: <ExternalLinkIcon className="size-4" />,
                      value: (
                          <span className="font-mono text-sm break-all">
                              {notFoundLog.full_url}
                          </span>
                      ),
                  },
              ]
            : []),
        {
            label: 'Type',
            icon: <AlertTriangleIcon className="size-4" />,
            value: getTypeBadge(notFoundLog.is_suspicious, notFoundLog.is_bot),
        },
        {
            label: 'HTTP Method',
            icon: <GlobeIcon className="size-4" />,
            value: (
                <Badge variant="outline" className="font-mono text-xs">
                    {notFoundLog.method}
                </Badge>
            ),
        },
        {
            label: 'IP Address',
            icon: <GlobeIcon className="size-4" />,
            value: <span className="font-mono">{notFoundLog.ip_address}</span>,
        },
        {
            label: 'Date & Time',
            icon: <ClockIcon className="size-4" />,
            value: notFoundLog.created_at
                ? new Date(notFoundLog.created_at).toLocaleString()
                : '—',
        },
        ...(notFoundLog.referer
            ? [
                  {
                      label: 'Referer',
                      icon: <ExternalLinkIcon className="size-4" />,
                      value: (
                          <span className="text-sm break-all">
                              {notFoundLog.referer}
                          </span>
                      ),
                  },
              ]
            : []),
        ...(notFoundLog.user
            ? [
                  {
                      label: 'Logged-in User',
                      icon: <UserIcon className="size-4" />,
                      value: notFoundLog.user.name,
                  },
              ]
            : []),
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="404 Log Details"
            description={`Not-found error for ${notFoundLog.url}`}
        >
            <Head title={`404 Log — ${notFoundLog.url.substring(0, 50)}`} />

            <div className="mb-6">
                <Button variant="ghost" size="sm" asChild>
                    <Link href={route('app.logs.not-found-logs.index')}>
                        <ArrowLeftIcon className="mr-2 size-4" />
                        Back to 404 Logs
                    </Link>
                </Button>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Main Content */}
                <div className="flex flex-col gap-6 lg:col-span-2">
                    {/* 404 Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>404 Information</CardTitle>
                            <CardDescription>
                                Details about this page-not-found error
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="divide-y">
                                {detailRows.map((row) => (
                                    <div
                                        key={row.label}
                                        className="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                                    >
                                        <dt className="flex min-w-[140px] items-center gap-2 text-sm font-medium text-muted-foreground">
                                            {row.icon}
                                            {row.label}
                                        </dt>
                                        <dd className="text-sm text-foreground">
                                            {row.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>

                    {/* Browser / Device */}
                    {notFoundLog.user_agent && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MonitorIcon className="size-4" />
                                    Browser & Device
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm break-all text-muted-foreground">
                                    {notFoundLog.user_agent}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {/* Metadata */}
                    {notFoundLog.metadata &&
                        Object.keys(notFoundLog.metadata).length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Metadata</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <dl className="divide-y">
                                        {Object.entries(
                                            notFoundLog.metadata,
                                        ).map(([key, value]) => (
                                            <div
                                                key={key}
                                                className="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                                            >
                                                <dt className="min-w-[140px] text-sm font-medium text-muted-foreground">
                                                    {key}
                                                </dt>
                                                <dd className="font-mono text-sm break-all text-foreground">
                                                    {String(value)}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </CardContent>
                            </Card>
                        )}
                </div>

                {/* Sidebar */}
                <div className="flex flex-col gap-6">
                    {/* Recent Activity for URL */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <SearchXIcon className="size-4" />
                                Activity for URL
                            </CardTitle>
                            <CardDescription>
                                Recent 404 hits for this URL
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="space-y-3">
                                <StatRow
                                    label="Total Hits"
                                    value={recentUrlStats.total}
                                />
                                {recentUrlStats.suspicious !== undefined && (
                                    <StatRow
                                        label="Suspicious"
                                        value={recentUrlStats.suspicious}
                                        variant="danger"
                                    />
                                )}
                                {recentUrlStats.unique_ips !== undefined && (
                                    <StatRow
                                        label="Unique IPs"
                                        value={recentUrlStats.unique_ips}
                                    />
                                )}
                            </dl>
                        </CardContent>
                    </Card>

                    {/* Recent Activity for IP */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <GlobeIcon className="size-4" />
                                Activity for IP
                            </CardTitle>
                            <CardDescription>
                                Recent activity from{' '}
                                <span className="font-mono">
                                    {notFoundLog.ip_address}
                                </span>
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="space-y-3">
                                <StatRow
                                    label="404 Errors"
                                    value={recentIpStats.total}
                                />
                                {recentIpStats.suspicious !== undefined && (
                                    <StatRow
                                        label="Suspicious"
                                        value={recentIpStats.suspicious}
                                        variant="danger"
                                    />
                                )}
                                {recentIpStats.unique_urls !== undefined && (
                                    <StatRow
                                        label="Unique URLs"
                                        value={recentIpStats.unique_urls}
                                    />
                                )}
                            </dl>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Actions</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            <Button
                                variant="destructive"
                                className="w-full"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            'Delete this 404 log entry?',
                                        )
                                    ) {
                                        router.delete(
                                            route(
                                                'app.logs.not-found-logs.destroy',
                                                notFoundLog.id,
                                            ),
                                            {
                                                onSuccess: () =>
                                                    router.visit(
                                                        route(
                                                            'app.logs.not-found-logs.index',
                                                        ),
                                                    ),
                                            },
                                        );
                                    }
                                }}
                            >
                                <Trash2Icon className="mr-2 size-4" />
                                Delete Log Entry
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

// =========================================================================
// HELPER COMPONENTS
// =========================================================================

const STAT_VARIANT_CLASSES: Record<string, string> = {
    default: 'text-foreground',
    success: 'text-green-600 dark:text-green-400',
    warning: 'text-amber-600 dark:text-amber-400',
    danger: 'text-red-600 dark:text-red-400',
};

function StatRow({
    label,
    value,
    variant = 'default',
}: {
    label: string;
    value: number;
    variant?: string;
}) {
    return (
        <div className="flex items-center justify-between">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd
                className={`text-sm font-semibold ${STAT_VARIANT_CLASSES[variant] ?? STAT_VARIANT_CLASSES.default}`}
            >
                {value.toLocaleString()}
            </dd>
        </div>
    );
}
