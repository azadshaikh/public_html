import {
    ActivityIcon,
    FolderKanbanIcon,
    MailCheckIcon,
    ShieldCheckIcon,
    UsersIcon,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/index';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

type DashboardPageProps = {
    summary: {
        totalUsers: number;
        activeUsers: number;
        verifiedUsers: number;
        totalRoles: number;
        customRoles: number;
        totalMedia: number | null;
        imageMedia: number | null;
        recentActivityCount: number;
        activeActors: number;
    };
    verificationRate: number;
    mediaUsage: {
        usedSizeReadable: string;
        maxSizeReadable: string;
        remainingReadable: string | null;
        percentageUsed: number;
    } | null;
    recentUsers: Array<{
        id: number;
        name: string;
        email: string | null;
        status: string;
        joinedAt: string | null;
    }>;
    recentActivities: Array<{
        id: number;
        title: string;
        meta: string;
    }>;
};

function formatNumber(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.NumberFormat().format(value);
}

export default function Dashboard({
    summary,
    verificationRate,
    mediaUsage,
    recentUsers,
    recentActivities,
}: DashboardPageProps) {
    const stats = [
        {
            title: 'Total users',
            value: formatNumber(summary.totalUsers),
            change: `${formatNumber(summary.activeUsers)} active accounts`,
            icon: UsersIcon,
        },
        {
            title: 'Verified users',
            value: formatNumber(summary.verifiedUsers),
            change: `${verificationRate}% verification rate`,
            icon: MailCheckIcon,
        },
        {
            title: 'Roles',
            value: formatNumber(summary.totalRoles),
            change: `${formatNumber(summary.customRoles)} custom roles`,
            icon: ShieldCheckIcon,
        },
        {
            title: 'Recent activity',
            value: formatNumber(summary.recentActivityCount),
            change: `${formatNumber(summary.activeActors)} active actors in 30 days`,
            icon: ActivityIcon,
        },
    ] as const;

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Dashboard"
            description="Track core application health, account activity, and operational readiness from one place."
        >
            <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                {stats.map((stat) => {
                    const Icon = stat.icon;

                    return (
                        <Card key={stat.title}>
                            <CardHeader>
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <CardDescription>
                                            {stat.title}
                                        </CardDescription>
                                        <CardTitle className="mt-2 text-3xl">
                                            {stat.value}
                                        </CardTitle>
                                    </div>
                                    <div className="rounded-xl border bg-muted/50 p-2">
                                        <Icon className="size-5 text-primary" />
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="text-sm text-muted-foreground">
                                    {stat.change}
                                </div>
                            </CardContent>
                        </Card>
                    );
                })}
            </section>

            <section className="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Account health</CardTitle>
                        <CardDescription>
                            Key operational signals for users, roles, and media.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="space-y-2">
                            <div className="flex items-center justify-between text-sm">
                                <span className="font-medium">
                                    Email verification
                                </span>
                                <span className="text-muted-foreground">
                                    {verificationRate}%
                                </span>
                            </div>
                            <Progress
                                value={verificationRate}
                                className="h-2"
                            />
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between text-sm">
                                <span className="font-medium">
                                    Active users share
                                </span>
                                <span className="text-muted-foreground">
                                    {summary.totalUsers > 0
                                        ? Math.round(
                                              (summary.activeUsers /
                                                  summary.totalUsers) *
                                                  100,
                                          )
                                        : 0}
                                    %
                                </span>
                            </div>
                            <Progress
                                value={
                                    summary.totalUsers > 0
                                        ? Math.round(
                                              (summary.activeUsers /
                                                  summary.totalUsers) *
                                                  100,
                                          )
                                        : 0
                                }
                                className="h-2"
                            />
                        </div>

                        {mediaUsage ? (
                            <div className="space-y-2">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="font-medium">
                                        Media storage usage
                                    </span>
                                    <span className="text-muted-foreground">
                                        {mediaUsage.percentageUsed}%
                                    </span>
                                </div>
                                <Progress
                                    value={Math.min(
                                        mediaUsage.percentageUsed,
                                        100,
                                    )}
                                    className="h-2"
                                />
                                <div className="flex flex-wrap justify-between gap-2 text-xs text-muted-foreground">
                                    <span>
                                        Used: {mediaUsage.usedSizeReadable}
                                    </span>
                                    <span>
                                        Limit: {mediaUsage.maxSizeReadable}
                                    </span>
                                </div>
                            </div>
                        ) : null}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Media capacity</CardTitle>
                        <CardDescription>
                            Current storage status for the application library.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        <div className="rounded-xl border bg-muted/30 p-4">
                            <div className="text-sm text-muted-foreground">
                                Media items
                            </div>
                            <div className="mt-2 text-2xl font-semibold">
                                {formatNumber(summary.totalMedia)}
                            </div>
                        </div>
                        <div className="rounded-xl border bg-muted/30 p-4">
                            <div className="text-sm text-muted-foreground">
                                Image assets
                            </div>
                            <div className="mt-2 text-2xl font-semibold">
                                {formatNumber(summary.imageMedia)}
                            </div>
                        </div>
                        <div className="rounded-xl border bg-muted/30 p-4">
                            <div className="text-sm text-muted-foreground">
                                Remaining storage
                            </div>
                            <div className="mt-2 text-lg font-semibold">
                                {mediaUsage?.remainingReadable ?? 'Unavailable'}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </section>

            <section className="grid gap-4 xl:grid-cols-[1.45fr_0.8fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Newest accounts</CardTitle>
                        <CardDescription>
                            Recently created users across the application.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recentUsers.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Joined
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentUsers.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell className="font-medium">
                                                {user.name}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {user.email ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {user.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground">
                                                {user.joinedAt ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <UsersIcon />
                                    </EmptyMedia>
                                    <EmptyTitle>No recent users</EmptyTitle>
                                    <EmptyDescription>
                                        Newly created accounts will appear here.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent activity</CardTitle>
                        <CardDescription>
                            Latest changes recorded by the application.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {recentActivities.length > 0 ? (
                            recentActivities.map((activity, index) => (
                                <div key={activity.id}>
                                    <div className="rounded-xl border bg-muted/30 p-4">
                                        <p className="font-medium">
                                            {activity.title}
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {activity.meta}
                                        </p>
                                    </div>
                                    {index !== recentActivities.length - 1 ? (
                                        <Separator className="my-3" />
                                    ) : null}
                                </div>
                            ))
                        ) : (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <FolderKanbanIcon />
                                    </EmptyMedia>
                                    <EmptyTitle>No activity yet</EmptyTitle>
                                    <EmptyDescription>
                                        Logged events will appear here after
                                        users start interacting with the app.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        )}
                    </CardContent>
                </Card>
            </section>
        </AppLayout>
    );
}
