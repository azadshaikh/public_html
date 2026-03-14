import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    BanIcon,
    CheckCircleIcon,
    ClockIcon,
    GlobeIcon,
    MailIcon,
    MonitorIcon,
    ShieldIcon,
    Trash2Icon,
    UserIcon,
    XCircleIcon,
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
import type { LoginAttemptsShowPageProps } from '@/types/login-attempt';

const STATUS_BADGE_VARIANT: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    success: 'default',
    failed: 'destructive',
    blocked: 'secondary',
    cleared: 'outline',
};

const STATUS_ICONS: Record<string, React.ReactNode> = {
    success: <CheckCircleIcon className="mr-1 size-3.5" />,
    failed: <XCircleIcon className="mr-1 size-3.5" />,
    blocked: <BanIcon className="mr-1 size-3.5" />,
};

export default function LoginAttemptShow({
    loginAttempt,
    recentEmailStats,
    recentIpStats,
}: LoginAttemptsShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        {
            title: 'Login Attempts',
            href: route('app.logs.login-attempts.index'),
        },
        { title: loginAttempt.email, href: '#' },
    ];

    const statusVariant =
        STATUS_BADGE_VARIANT[loginAttempt.status] ?? 'outline';

    const detailRows: {
        label: string;
        icon: React.ReactNode;
        value: React.ReactNode;
    }[] = [
        {
            label: 'Status',
            icon: <ShieldIcon className="size-4" />,
            value: (
                <Badge
                    variant={statusVariant}
                    className="inline-flex items-center"
                >
                    {STATUS_ICONS[loginAttempt.status]}
                    {loginAttempt.status.charAt(0).toUpperCase() +
                        loginAttempt.status.slice(1)}
                </Badge>
            ),
        },
        {
            label: 'Email',
            icon: <MailIcon className="size-4" />,
            value: loginAttempt.email,
        },
        {
            label: 'IP Address',
            icon: <GlobeIcon className="size-4" />,
            value: <span className="font-mono">{loginAttempt.ip_address}</span>,
        },
        {
            label: 'Date & Time',
            icon: <ClockIcon className="size-4" />,
            value: loginAttempt.created_at
                ? new Date(loginAttempt.created_at).toLocaleString()
                : '—',
        },
        ...(loginAttempt.failure_reason
            ? [
                  {
                      label: 'Failure Reason',
                      icon: <XCircleIcon className="size-4" />,
                      value: (
                          <Badge variant="destructive">
                              {loginAttempt.failure_reason}
                          </Badge>
                      ),
                  },
              ]
            : []),
        ...(loginAttempt.user
            ? [
                  {
                      label: 'Associated User',
                      icon: <UserIcon className="size-4" />,
                      value: loginAttempt.user.name,
                  },
              ]
            : []),
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Login Attempt Details"
            description={`Login attempt for ${loginAttempt.email}`}
        >
            <Head title={`Login Attempt — ${loginAttempt.email}`} />

            <div className="mb-6">
                <Button variant="ghost" size="sm" asChild>
                    <Link href={route('app.logs.login-attempts.index')}>
                        <ArrowLeftIcon className="mr-2 size-4" />
                        Back to Login Attempts
                    </Link>
                </Button>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Main Content */}
                <div className="flex flex-col gap-6 lg:col-span-2">
                    {/* Login Attempt Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Login Information</CardTitle>
                            <CardDescription>
                                Details about this login attempt
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
                    {loginAttempt.user_agent && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MonitorIcon className="size-4" />
                                    Browser & Device
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm break-all text-muted-foreground">
                                    {loginAttempt.user_agent}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {/* Metadata */}
                    {loginAttempt.metadata &&
                        Object.keys(loginAttempt.metadata).length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Metadata</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <dl className="divide-y">
                                        {Object.entries(
                                            loginAttempt.metadata,
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
                    {/* Recent Activity for Email */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <MailIcon className="size-4" />
                                Activity for Email
                            </CardTitle>
                            <CardDescription>
                                Recent login attempts for{' '}
                                <span className="font-medium">
                                    {loginAttempt.email}
                                </span>
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="space-y-3">
                                <StatRow
                                    label="Total Attempts"
                                    value={recentEmailStats.total}
                                />
                                {recentEmailStats.success !== undefined && (
                                    <StatRow
                                        label="Successful"
                                        value={recentEmailStats.success}
                                        variant="success"
                                    />
                                )}
                                {recentEmailStats.failed !== undefined && (
                                    <StatRow
                                        label="Failed"
                                        value={recentEmailStats.failed}
                                        variant="danger"
                                    />
                                )}
                                {recentEmailStats.blocked !== undefined && (
                                    <StatRow
                                        label="Blocked"
                                        value={recentEmailStats.blocked}
                                        variant="warning"
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
                                    {loginAttempt.ip_address}
                                </span>
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="space-y-3">
                                <StatRow
                                    label="Total Attempts"
                                    value={recentIpStats.total}
                                />
                                {recentIpStats.unique_emails !== undefined && (
                                    <StatRow
                                        label="Unique Emails"
                                        value={recentIpStats.unique_emails}
                                    />
                                )}
                                {recentIpStats.failed !== undefined && (
                                    <StatRow
                                        label="Failed"
                                        value={recentIpStats.failed}
                                        variant="danger"
                                    />
                                )}
                                {recentIpStats.blocked !== undefined && (
                                    <StatRow
                                        label="Blocked"
                                        value={recentIpStats.blocked}
                                        variant="warning"
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
                                            'Delete this login attempt?',
                                        )
                                    ) {
                                        router.delete(
                                            route(
                                                'app.logs.login-attempts.destroy',
                                                loginAttempt.id,
                                            ),
                                            {
                                                onSuccess: () =>
                                                    router.visit(
                                                        route(
                                                            'app.logs.login-attempts.index',
                                                        ),
                                                    ),
                                            },
                                        );
                                    }
                                }}
                            >
                                <Trash2Icon className="mr-2 size-4" />
                                Delete Attempt
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
