import { Link } from '@inertiajs/react';
import {
    BugIcon,
    DatabaseIcon,
    FlameIcon,
    GlobeIcon,
    ServerIcon,
    ShieldCheckIcon,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import {
    LaravelToolsNavigation,
    getLaravelToolsBreadcrumbs,
    laravelToolDefinitions,
} from '@/pages/masters/laravel-tools/components/shared';
import type { LaravelToolsDashboardPageProps } from '@/types/laravel-tools';

const highlightCards = [
    {
        key: 'php',
        label: 'PHP Version',
        description: 'Runtime currently serving the application.',
        valueKey: 'php_version' as const,
        icon: ServerIcon,
        tone: 'info' as const,
    },
    {
        key: 'laravel',
        label: 'Laravel Version',
        description: 'Framework version resolved from the application container.',
        valueKey: 'laravel_version' as const,
        icon: FlameIcon,
        tone: 'danger' as const,
    },
    {
        key: 'environment',
        label: 'Environment',
        description: 'Current app environment reported by Laravel.',
        valueKey: 'environment' as const,
        icon: GlobeIcon,
        tone: 'success' as const,
    },
] as const;

export default function LaravelToolsIndex({
    stats,
}: LaravelToolsDashboardPageProps) {
    return (
        <AppLayout
            breadcrumbs={getLaravelToolsBreadcrumbs()}
            title="Laravel Tools"
            description="Inspect runtime health, update application configuration, and run approved maintenance actions."
            headerActions={
                <Button asChild>
                    <Link href={route('app.masters.laravel-tools.env')}>
                        Open ENV Editor
                    </Link>
                </Button>
            }
        >
            <div className="flex flex-col gap-6">
                <LaravelToolsNavigation />

                <Alert>
                    <ShieldCheckIcon data-icon="inline-start" />
                    <AlertTitle>Super-user workspace</AlertTitle>
                    <AlertDescription>
                        Sensitive actions stay limited to protected keys, masked
                        values, and approved maintenance commands.
                    </AlertDescription>
                </Alert>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {highlightCards.map((item) => {
                        const Icon = item.icon;

                        return (
                            <Card key={item.key}>
                                <CardHeader className="gap-3">
                                    <div className="flex items-center justify-between gap-3">
                                        <Badge variant={item.tone}>
                                            {item.label}
                                        </Badge>
                                        <span className="flex size-10 items-center justify-center rounded-full bg-muted text-foreground">
                                            <Icon />
                                        </span>
                                    </div>
                                    <CardTitle className="text-2xl">
                                        {stats[item.valueKey]}
                                    </CardTitle>
                                    <CardDescription>
                                        {item.description}
                                    </CardDescription>
                                </CardHeader>
                            </Card>
                        );
                    })}

                    <Card>
                        <CardHeader className="gap-3">
                            <div className="flex items-center justify-between gap-3">
                                <Badge
                                    variant={
                                        stats.debug_mode ? 'warning' : 'success'
                                    }
                                >
                                    Debug Mode
                                </Badge>
                                <span className="flex size-10 items-center justify-center rounded-full bg-muted text-foreground">
                                    <BugIcon />
                                </span>
                            </div>
                            <CardTitle className="text-2xl">
                                {stats.debug_mode ? 'Enabled' : 'Disabled'}
                            </CardTitle>
                            <CardDescription>
                                Keep production hardened by ensuring debug mode is only enabled when needed.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1.5fr)_minmax(18rem,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Maintenance toolkit</CardTitle>
                            <CardDescription>
                                Each tool focuses on a single workflow so you can inspect, change, and validate safely.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {laravelToolDefinitions.map((tool) => {
                                const Icon = tool.icon;

                                return (
                                    <Card
                                        key={tool.key}
                                        className={tool.accentClassName}
                                    >
                                        <CardHeader className="gap-3">
                                            <span
                                                className={`flex size-11 items-center justify-center rounded-2xl bg-background/80 ${tool.iconClassName}`}
                                            >
                                                <Icon />
                                            </span>
                                            <div className="flex flex-col gap-1">
                                                <CardTitle className="text-base">
                                                    {tool.title}
                                                </CardTitle>
                                                <CardDescription className="text-sm text-foreground/75 dark:text-muted-foreground">
                                                    {tool.description}
                                                </CardDescription>
                                            </div>
                                        </CardHeader>
                                        <CardFooter>
                                            <Button asChild variant="outline">
                                                <Link href={tool.href}>
                                                    Open tool
                                                </Link>
                                            </Button>
                                        </CardFooter>
                                    </Card>
                                );
                            })}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>System profile</CardTitle>
                            <CardDescription>
                                Quick reference values pulled from the active Laravel runtime.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4 text-sm">
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                                <ProfileItem
                                    label="Cache driver"
                                    value={stats.cache_driver}
                                />
                                <ProfileItem
                                    label="Cache prefix"
                                    value={stats.cache_prefix}
                                />
                                <ProfileItem
                                    label="Session driver"
                                    value={stats.session_driver}
                                />
                                <ProfileItem
                                    label="Database"
                                    value={stats.database_connection}
                                    icon={<DatabaseIcon className="size-4" />}
                                />
                                <ProfileItem
                                    label="Timezone"
                                    value={stats.timezone}
                                />
                                <ProfileItem
                                    label="Locale"
                                    value={stats.locale}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function ProfileItem({
    label,
    value,
    icon,
}: {
    label: string;
    value: string;
    icon?: React.ReactNode;
}) {
    return (
        <div className="rounded-xl border bg-muted/30 p-4">
            <div className="mb-2 flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {icon}
                <span>{label}</span>
            </div>
            <p className="break-all text-sm font-medium text-foreground">
                {value}
            </p>
        </div>
    );
}
