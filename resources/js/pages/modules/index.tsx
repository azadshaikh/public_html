import { Link, router } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    PackageIcon,
    SearchIcon,
    ShieldAlertIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { showAppToast } from '@/components/forms/form-success-toast';
import { NavigationIcon } from '@/components/navigation-icon';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardTitle,
    CardFooter,
    CardHeader,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { suppressNextFlashToast } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, ManagedModule } from '@/types';

type ModuleManagementPageProps = {
    managedModules: ManagedModule[];
    indexUrl: string;
    updateUrl: string;
    error?: string;
};

const statusFilterOptions = [
    {
        label: 'All',
        value: 'all',
    },
    {
        label: 'Enabled',
        value: 'enabled',
    },
    {
        label: 'Disabled',
        value: 'disabled',
    },
] as const;

export default function ModulesIndex({
    managedModules,
    indexUrl,
    updateUrl,
    error,
}: ModuleManagementPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: route('dashboard'),
        },
        {
            title: 'Modules',
            href: indexUrl,
        },
    ];

    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<
        'all' | 'enabled' | 'disabled'
    >('all');
    const [processingModule, setProcessingModule] = useState<string | null>(
        null,
    );
    const [moduleStatuses, setModuleStatuses] = useState<
        Record<string, 'enabled' | 'disabled'>
    >(
        Object.fromEntries(
            managedModules.map((module) => [module.name, module.status]),
        ) as Record<string, 'enabled' | 'disabled'>,
    );

    const modules = useMemo(() => {
        return managedModules
            .map((module) => {
                const currentStatus =
                    moduleStatuses[module.name] ?? module.status;

                return {
                    ...module,
                    status: currentStatus,
                    enabled: currentStatus === 'enabled',
                };
            })
            .filter((module) => {
                if (statusFilter !== 'all' && module.status !== statusFilter) {
                    return false;
                }

                const query = search.trim().toLowerCase();

                if (query === '') {
                    return true;
                }

                return [
                    module.name,
                    module.version,
                    module.description,
                    module.author ?? '',
                ]
                    .join(' ')
                    .toLowerCase()
                    .includes(query);
            })
            .sort((left, right) => left.name.localeCompare(right.name));
    }, [managedModules, moduleStatuses, search, statusFilter]);

    const enabledCount = Object.values(moduleStatuses).filter(
        (value) => value === 'enabled',
    ).length;
    const disabledCount = managedModules.length - enabledCount;

    const overviewItems = [
        {
            label: 'Total modules',
            value: managedModules.length,
            valueClassName: 'text-foreground',
        },
        {
            label: 'Enabled',
            value: enabledCount,
            valueClassName:
                'text-[var(--success-foreground)] dark:text-[var(--success-dark-foreground)]',
        },
        {
            label: 'Disabled',
            value: disabledCount,
            valueClassName: 'text-rose-600 dark:text-rose-400',
        },
    ] as const;

    function updateModuleStatus(moduleName: string, enabled: boolean) {
        const nextStatuses: Record<string, 'enabled' | 'disabled'> = {
            ...moduleStatuses,
            [moduleName]: enabled ? 'enabled' : 'disabled',
        };

        setModuleStatuses(nextStatuses);
        setProcessingModule(moduleName);

        router.patch(
            updateUrl,
            {
                modules: nextStatuses,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    suppressNextFlashToast();
                    showAppToast({
                        title: enabled ? 'Module Enabled' : 'Module Disabled',
                        description: `${moduleName} module is ${enabled ? 'enabled' : 'disabled'}.`,
                    });
                },
                onError: () => {
                    setModuleStatuses(moduleStatuses);
                },
                onFinish: () => {
                    setProcessingModule(null);
                },
            },
        );
    }

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Manage Modules"
            description="Enable, disable, and browse local modules from one control center."
            headerActions={
                <div className="flex flex-wrap gap-3">
                    <Button asChild variant="outline">
                        <Link href={route('dashboard')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back to dashboard
                        </Link>
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <Card className="py-0 shadow-none">
                    <CardContent className="flex flex-col gap-4 py-4">
                        <div className="grid gap-3 sm:grid-cols-3">
                            {overviewItems.map((item) => (
                                <div
                                    key={item.label}
                                    className="flex items-center justify-between gap-3 rounded-lg border bg-muted/20 px-4 py-4"
                                >
                                    <span className="text-xs font-medium tracking-[0.14em] text-muted-foreground uppercase">
                                        {item.label}
                                    </span>
                                    <span
                                        className={cn(
                                            'text-2xl font-semibold tracking-tight',
                                            item.valueClassName,
                                        )}
                                    >
                                        {item.value}
                                    </span>
                                </div>
                            ))}
                        </div>

                        <div className="flex flex-col gap-4 border-t pt-4 xl:flex-row xl:items-center xl:justify-between">
                            <InputGroup className="h-9 w-full xl:max-w-md">
                                <InputGroupAddon>
                                    <SearchIcon />
                                </InputGroupAddon>
                                <InputGroupInput
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search modules by name, version, or description"
                                />
                            </InputGroup>

                            <div className="flex flex-col gap-3 xl:flex-row xl:items-center">
                                <ToggleGroup
                                    type="single"
                                    value={statusFilter}
                                    variant="outline"
                                    onValueChange={(value) =>
                                        setStatusFilter(
                                            (value === '' ? 'all' : value) as
                                                | 'all'
                                                | 'enabled'
                                                | 'disabled',
                                        )
                                    }
                                >
                                    {statusFilterOptions.map((option) => (
                                        <ToggleGroupItem
                                            key={option.value}
                                            value={option.value}
                                            className="h-9 px-2.5"
                                        >
                                            {option.label}
                                        </ToggleGroupItem>
                                    ))}
                                </ToggleGroup>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {error ? (
                    <Alert className="border-destructive/30 text-destructive dark:border-destructive/40">
                        <ShieldAlertIcon />
                        <AlertTitle>Unavailable</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                <section className="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                    {modules.map((module) => {
                        const isProcessing = processingModule === module.name;
                        const actionLabel = module.enabled
                            ? 'Disable'
                            : 'Enable';
                        const actionVariant = module.enabled
                            ? 'outline'
                            : 'default';

                        return (
                            <Card
                                key={module.name}
                                size="sm"
                                className="h-full py-0 shadow-none"
                            >
                                <CardHeader className="min-h-12 items-center border-b px-3 py-2">
                                    <div className="flex items-center gap-2.5">
                                        <div
                                            className={cn(
                                                'flex size-8 shrink-0 items-center justify-center rounded-xl border text-muted-foreground',
                                                module.enabled &&
                                                    'bg-secondary text-foreground',
                                                !module.enabled && 'bg-muted',
                                            )}
                                        >
                                            {module.icon ? (
                                                <NavigationIcon
                                                    svg={module.icon}
                                                    className="text-current [&_svg]:size-4"
                                                />
                                            ) : (
                                                <PackageIcon className="size-4" />
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <CardTitle className="truncate text-sm">
                                                {module.name}
                                            </CardTitle>
                                        </div>
                                    </div>
                                </CardHeader>

                                <CardContent className="flex min-h-0 flex-1 flex-col gap-2 px-3 py-2">
                                    <div className="flex flex-wrap gap-1.5">
                                        <Badge variant="outline">
                                            v{module.version}
                                        </Badge>
                                        <Badge
                                            variant={
                                                module.enabled
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                            className={cn(
                                                module.enabled
                                                    ? 'border-[var(--success-border)] bg-[var(--success-bg)] text-[var(--success-foreground)] dark:border-[var(--success-dark-border)] dark:bg-[var(--success-dark-bg)] dark:text-[var(--success-dark-foreground)]'
                                                    : 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300',
                                            )}
                                        >
                                            {module.enabled
                                                ? 'Enabled'
                                                : 'Disabled'}
                                        </Badge>
                                    </div>

                                    <p className="line-clamp-2 min-h-8 text-xs/5 text-muted-foreground">
                                        {module.description ||
                                            'No description available for this module yet.'}
                                    </p>

                                    <div className="min-h-5 text-xs text-muted-foreground">
                                        {module.author ? (
                                            <span>
                                                By{' '}
                                                {module.homepage ? (
                                                    <a
                                                        href={module.homepage}
                                                        target="_blank"
                                                        rel="noreferrer noopener"
                                                        className="font-medium text-foreground underline-offset-4 transition-colors hover:text-primary hover:underline"
                                                    >
                                                        {module.author}
                                                    </a>
                                                ) : (
                                                    <span className="font-medium text-foreground">
                                                        {module.author}
                                                    </span>
                                                )}
                                            </span>
                                        ) : (
                                            <span>Author not specified.</span>
                                        )}
                                    </div>
                                </CardContent>

                                <CardFooter className="min-h-12 items-end bg-transparent px-3 pt-0 pb-2">
                                    <Button
                                        variant={actionVariant}
                                        className="h-9 w-full"
                                        disabled={isProcessing}
                                        onClick={() =>
                                            updateModuleStatus(
                                                module.name,
                                                !module.enabled,
                                            )
                                        }
                                    >
                                        {isProcessing
                                            ? 'Updating...'
                                            : actionLabel}
                                    </Button>
                                </CardFooter>
                            </Card>
                        );
                    })}

                    {modules.length === 0 ? (
                        <Card className="md:col-span-2 lg:col-span-4">
                            <CardContent className="py-10">
                                <Empty className="border-none p-0">
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <SearchIcon />
                                        </EmptyMedia>
                                        <EmptyTitle>
                                            No modules matched the current
                                            filters
                                        </EmptyTitle>
                                        <EmptyDescription>
                                            Try a different search term or
                                            change the status filter.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            </CardContent>
                        </Card>
                    ) : null}
                </section>
            </div>
        </AppLayout>
    );
}
