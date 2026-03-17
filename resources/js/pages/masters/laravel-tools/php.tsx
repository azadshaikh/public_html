import {
    BinaryIcon,
    BugIcon,
    CpuIcon,
    DatabaseIcon,
    GaugeIcon,
    PuzzleIcon,
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import {
    LaravelToolsNavigation,
    getLaravelToolsBreadcrumbs,
} from '@/pages/masters/laravel-tools/components/shared';
import type { LaravelToolsPhpPageProps } from '@/types/laravel-tools';

const summaryCards = [
    {
        key: 'php_version',
        label: 'PHP Version',
        description: 'Current runtime version.',
        icon: CpuIcon,
    },
    {
        key: 'sapi',
        label: 'SAPI',
        description: 'Execution interface used by PHP.',
        icon: BinaryIcon,
    },
    {
        key: 'memory_limit',
        label: 'Memory Limit',
        description: 'Configured memory cap.',
        icon: GaugeIcon,
    },
] as const;

export default function LaravelToolsPhp({
    summary,
    settingGroups,
    extensions,
    pdoDrivers,
}: LaravelToolsPhpPageProps) {
    return (
        <AppLayout
            breadcrumbs={getLaravelToolsBreadcrumbs('PHP Diagnostics')}
            title="PHP Diagnostics"
            description="Inspect runtime limits, ini settings, extensions, and PDO drivers for the active PHP environment."
        >
            <div className="flex flex-col gap-6">
                <LaravelToolsNavigation current="php" />

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {summaryCards.map((card) => {
                        const Icon = card.icon;

                        return (
                            <Card key={card.key}>
                                <CardHeader className="gap-3">
                                    <div className="flex items-center justify-between gap-3">
                                        <Badge variant="secondary">
                                            {card.label}
                                        </Badge>
                                        <span className="flex size-10 items-center justify-center rounded-full bg-muted text-foreground">
                                            <Icon />
                                        </span>
                                    </div>
                                    <CardTitle className="text-2xl">
                                        {summary[card.key]}
                                    </CardTitle>
                                    <CardDescription>
                                        {card.description}
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
                                        summary.opcache_enabled
                                            ? 'success'
                                            : 'warning'
                                    }
                                >
                                    OPcache
                                </Badge>
                                <span className="flex size-10 items-center justify-center rounded-full bg-muted text-foreground">
                                    <BugIcon />
                                </span>
                            </div>
                            <CardTitle className="text-2xl">
                                {summary.opcache_enabled
                                    ? 'Enabled'
                                    : 'Disabled'}
                            </CardTitle>
                            <CardDescription>
                                Status of bytecode caching for the current
                                runtime.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Runtime details</CardTitle>
                        <CardDescription>
                            Core ini details used by the PHP process serving
                            Laravel.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <RuntimeDetail
                            label="Loaded ini file"
                            value={summary.ini_file}
                        />
                        <RuntimeDetail
                            label="Max execution time"
                            value={summary.max_execution_time}
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(20rem,0.9fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>INI setting groups</CardTitle>
                            <CardDescription>
                                Review commonly-tuned values grouped by runtime
                                area.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6 xl:grid-cols-2">
                            {Object.entries(settingGroups).map(
                                ([group, values]) => (
                                    <div
                                        key={group}
                                        className="overflow-hidden rounded-xl border"
                                    >
                                        <div className="border-b bg-muted/40 px-4 py-3">
                                            <p className="font-medium text-foreground">
                                                {group}
                                            </p>
                                        </div>
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead className="w-1/2">
                                                        Setting
                                                    </TableHead>
                                                    <TableHead>Value</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {Object.entries(values).map(
                                                    ([key, value]) => (
                                                        <TableRow key={key}>
                                                            <TableCell className="font-mono text-xs text-foreground">
                                                                {key}
                                                            </TableCell>
                                                            <TableCell className="text-xs break-all text-muted-foreground">
                                                                {value}
                                                            </TableCell>
                                                        </TableRow>
                                                    ),
                                                )}
                                            </TableBody>
                                        </Table>
                                    </div>
                                ),
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>PDO drivers</CardTitle>
                                <CardDescription>
                                    Drivers available to PDO in this PHP build.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {pdoDrivers.length === 0 ? (
                                    <Empty className="border">
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <DatabaseIcon />
                                            </EmptyMedia>
                                            <EmptyTitle>
                                                No PDO drivers detected
                                            </EmptyTitle>
                                            <EmptyDescription>
                                                PHP did not report any PDO
                                                drivers for this runtime.
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                ) : (
                                    <div className="flex flex-wrap gap-2">
                                        {pdoDrivers.map((driver) => (
                                            <Badge key={driver} variant="info">
                                                {driver}
                                            </Badge>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Loaded extensions</CardTitle>
                                <CardDescription>
                                    {extensions.length} extensions reported by
                                    PHP.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 sm:grid-cols-2">
                                {extensions.map((extension) => (
                                    <div
                                        key={extension.name}
                                        className="rounded-xl border p-4"
                                    >
                                        <div className="mb-2 flex items-center gap-2">
                                            <span className="flex size-8 items-center justify-center rounded-full bg-muted text-foreground">
                                                <PuzzleIcon className="size-4" />
                                            </span>
                                            <p className="font-medium text-foreground">
                                                {extension.name}
                                            </p>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {extension.version}
                                        </p>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function RuntimeDetail({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border bg-muted/30 p-4">
            <p className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="font-medium break-all text-foreground">{value}</p>
        </div>
    );
}
