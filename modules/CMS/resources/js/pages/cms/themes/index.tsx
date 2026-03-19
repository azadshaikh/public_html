import { router, useForm, usePage } from '@inertiajs/react';
import {
    BrushIcon,
    CheckCircle2Icon,
    CodeIcon,
    DownloadIcon,
    GitBranchIcon,
    ImageIcon,
    MoreHorizontalIcon,
    PaletteIcon,
    SearchIcon,
    ShieldIcon,
    SparklesIcon,
    Trash2Icon,
    UnplugIcon,
    UploadIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { ThemeIndexPageProps, ThemeListItem } from '../../../types/cms';

type ImportThemeFormData = {
    theme_zip: File | null;
};

type PendingAction = {
    type: 'activate' | 'create-child' | 'detach' | 'delete';
    theme: ThemeListItem;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Appearance', href: route('cms.appearance.themes.index') },
    { title: 'Themes', href: route('cms.appearance.themes.index') },
];

function formatSupportLabel(value: string): string {
    return value
        .replaceAll(/[-_]+/g, ' ')
        .replace(/\b\w/g, (match) => match.toUpperCase());
}

function ThemeScreenshot({ theme }: { theme: ThemeListItem }) {
    if (theme.screenshot) {
        return (
            <div className="relative overflow-hidden rounded-t-xl bg-muted">
                <img
                    src={theme.screenshot}
                    alt={`${theme.name} preview`}
                    className="aspect-video w-full object-cover transition-transform duration-300 hover:scale-105"
                />
            </div>
        );
    }

    return (
        <div className="relative flex aspect-video w-full items-center justify-center rounded-t-xl bg-muted">
            <ImageIcon className="size-10 text-muted-foreground/50" />
        </div>
    );
}

function ThemeBadges({ theme }: { theme: ThemeListItem }) {
    return (
        <>
            {theme.is_active ? (
                <Badge variant="success" className="shadow-sm">
                    Active
                </Badge>
            ) : null}
            {theme.is_child ? (
                <Badge
                    variant="secondary"
                    className="bg-background/80 shadow-sm backdrop-blur-sm"
                >
                    Child
                </Badge>
            ) : null}
            {theme.is_protected ? (
                <Badge
                    variant="outline"
                    className="bg-background/80 shadow-sm backdrop-blur-sm"
                >
                    Protected
                </Badge>
            ) : null}
        </>
    );
}

function StatisticCard({
    title,
    value,
    description,
    icon,
}: {
    title: string;
    value: number;
    description: string;
    icon: React.ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4 pb-2">
                <div className="space-y-1">
                    <CardDescription>{title}</CardDescription>
                    <CardTitle className="text-2xl">{value}</CardTitle>
                </div>
                <div className="rounded-lg bg-muted p-2 text-muted-foreground">
                    {icon}
                </div>
            </CardHeader>
            <CardContent>
                <p className="text-sm text-muted-foreground">{description}</p>
            </CardContent>
        </Card>
    );
}

function ThemeActionMenu({
    theme,
    canAddThemes,
    canEditThemes,
    canDeleteThemes,
    onAction,
}: {
    theme: ThemeListItem;
    canAddThemes: boolean;
    canEditThemes: boolean;
    canDeleteThemes: boolean;
    onAction: (action: PendingAction['type'], theme: ThemeListItem) => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 text-muted-foreground hover:text-foreground"
                    aria-label={`More actions for ${theme.name}`}
                >
                    <MoreHorizontalIcon className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>Theme actions</DropdownMenuLabel>
                <DropdownMenuGroup>
                    <DropdownMenuItem asChild>
                        <a
                            href={route(
                                'cms.appearance.themes.editor.index',
                                theme.directory,
                            )}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <CodeIcon />
                            Edit files
                        </a>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <a
                            href={route(
                                'cms.appearance.themes.export',
                                theme.directory,
                            )}
                        >
                            <DownloadIcon />
                            Export theme
                        </a>
                    </DropdownMenuItem>
                </DropdownMenuGroup>

                {canAddThemes || canEditThemes || canDeleteThemes ? (
                    <DropdownMenuSeparator />
                ) : null}

                <DropdownMenuGroup>
                    {canAddThemes ? (
                        <DropdownMenuItem
                            onSelect={(event) => {
                                event.preventDefault();
                                onAction('create-child', theme);
                            }}
                        >
                            <GitBranchIcon />
                            Create child theme
                        </DropdownMenuItem>
                    ) : null}

                    {theme.is_child && canEditThemes ? (
                        <DropdownMenuItem
                            onSelect={(event) => {
                                event.preventDefault();
                                onAction('detach', theme);
                            }}
                        >
                            <UnplugIcon />
                            Make standalone
                        </DropdownMenuItem>
                    ) : null}

                    {!theme.is_active && canEditThemes ? (
                        <DropdownMenuItem
                            onSelect={(event) => {
                                event.preventDefault();
                                onAction('activate', theme);
                            }}
                        >
                            <CheckCircle2Icon />
                            Activate theme
                        </DropdownMenuItem>
                    ) : null}

                    {!theme.is_active &&
                    !theme.is_protected &&
                    canDeleteThemes ? (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={(event) => {
                                event.preventDefault();
                                onAction('delete', theme);
                            }}
                        >
                            <Trash2Icon />
                            Delete theme
                        </DropdownMenuItem>
                    ) : null}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function ThemeCard({
    theme,
    canAddThemes,
    canEditThemes,
    canDeleteThemes,
    onAction,
}: {
    theme: ThemeListItem;
    canAddThemes: boolean;
    canEditThemes: boolean;
    canDeleteThemes: boolean;
    onAction: (action: PendingAction['type'], theme: ThemeListItem) => void;
}) {
    return (
        <Card
            className={cn(
                'flex h-full flex-col overflow-hidden transition-all duration-200 hover:shadow-md',
                theme.is_active
                    ? 'border-success bg-success/5 ring-success/20 shadow-sm ring-1'
                    : 'border-border/50',
            )}
        >
            <ThemeScreenshot theme={theme} />
            <CardContent className="flex flex-1 flex-col gap-4 p-5">
                <div className="flex flex-1 flex-col gap-2">
                    <div className="flex items-start justify-between gap-2">
                        <div className="flex flex-col">
                            <h3 className="line-clamp-1 text-lg leading-tight font-semibold tracking-tight">
                                {theme.name}
                            </h3>
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                <span>v{theme.version}</span>
                                {theme.author && (
                                    <>
                                        <span className="size-1 rounded-full bg-muted-foreground/30" />
                                        <span className="line-clamp-1">
                                            By {theme.author}
                                        </span>
                                    </>
                                )}
                            </div>
                        </div>
                        <div className="-mt-1 -mr-2 shrink-0">
                            <ThemeActionMenu
                                theme={theme}
                                canAddThemes={canAddThemes}
                                canEditThemes={canEditThemes}
                                canDeleteThemes={canDeleteThemes}
                                onAction={onAction}
                            />
                        </div>
                    </div>

                    <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                        {theme.description || 'No description provided.'}
                    </p>

                    {theme.is_child && theme.parent ? (
                        <p className="mt-auto pt-2 text-xs text-muted-foreground">
                            Inherits from{' '}
                            <span className="font-medium text-foreground">
                                {theme.parent}
                            </span>
                        </p>
                    ) : null}
                </div>
            </CardContent>

            <CardFooter className="flex items-center gap-2 p-2 pt-2">
                {theme.is_active ? (
                    <Button asChild variant="success" className="flex-1">
                        <a
                            href={route(
                                'cms.appearance.themes.customizer.index',
                            )}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <BrushIcon className="mr-2 size-4" />
                            Customize
                        </a>
                    </Button>
                ) : canEditThemes ? (
                    <Button
                        onClick={() => onAction('activate', theme)}
                        variant="default"
                        className="flex-1"
                    >
                        <CheckCircle2Icon className="mr-2 size-4" />
                        Activate
                    </Button>
                ) : (
                    <Button variant="outline" disabled className="flex-1">
                        No access
                    </Button>
                )}

                <Button
                    variant="outline"
                    size="icon-lg"
                    asChild
                    title="Edit code"
                >
                    <a
                        href={route(
                            'cms.appearance.themes.editor.index',
                            theme.directory,
                        )}
                        target="_blank"
                        rel="noreferrer"
                    >
                        <CodeIcon className="size-4" />
                    </a>
                </Button>
            </CardFooter>
        </Card>
    );
}

function buildFilterQuery(filter: string, search: string, supports: string[]) {
    return {
        ...(search ? { search } : {}),
        ...(filter !== 'all' ? { filter } : {}),
        ...(filter === 'supports' && supports.length > 0 ? { supports } : {}),
    };
}

function getActionContent(action: PendingAction | null) {
    if (!action) {
        return {
            title: '',
            description: '',
            confirmLabel: 'Continue',
            variant: 'default' as const,
            icon: <SparklesIcon className="size-5" />,
        };
    }

    if (action.type === 'activate') {
        return {
            title: `Activate ${action.theme.name}?`,
            description:
                'The current active theme will be replaced immediately.',
            confirmLabel: 'Activate theme',
            variant: 'default' as const,
            icon: <PaletteIcon className="size-5" />,
        };
    }

    if (action.type === 'create-child') {
        return {
            title: `Create a child theme from ${action.theme.name}?`,
            description:
                'A new child theme will be generated automatically using this theme as its parent.',
            confirmLabel: 'Create child theme',
            variant: 'default' as const,
            icon: <GitBranchIcon className="size-5" />,
        };
    }

    if (action.type === 'detach') {
        return {
            title: `Make ${action.theme.name} standalone?`,
            description:
                'Parent files will be copied into the child theme so it no longer inherits from its parent.',
            confirmLabel: 'Make standalone',
            variant: 'default' as const,
            icon: <UnplugIcon className="size-5" />,
        };
    }

    return {
        title: `Delete ${action.theme.name}?`,
        description:
            'This permanently removes the theme files from disk. This action cannot be undone.',
        confirmLabel: 'Delete theme',
        variant: 'destructive' as const,
        icon: <Trash2Icon className="size-5" />,
    };
}

export default function ThemesIndex({
    themes,
    activeTheme,
    filters,
    availableSupports,
}: ThemeIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddThemes = page.props.auth.abilities.addThemes;
    const canEditThemes = page.props.auth.abilities.editThemes;
    const canDeleteThemes = page.props.auth.abilities.deleteThemes;
    const [searchValue, setSearchValue] = useState(filters.search);
    const [importOpen, setImportOpen] = useState(false);
    const [pendingAction, setPendingAction] = useState<PendingAction | null>(
        null,
    );
    const [actionProcessing, setActionProcessing] = useState(false);
    const importForm = useForm<ImportThemeFormData>({ theme_zip: null });

    const actionContent = useMemo(
        () => getActionContent(pendingAction),
        [pendingAction],
    );

    const updateListing = (next: {
        filter?: string;
        search?: string;
        supports?: string[];
    }) => {
        const nextFilter = next.filter ?? filters.filter ?? 'all';
        const nextSearch = next.search ?? searchValue;
        const nextSupports = next.supports ?? filters.supports;

        router.get(
            route('cms.appearance.themes.index'),
            buildFilterQuery(nextFilter, nextSearch.trim(), nextSupports),
            {
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const submitSearch = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        updateListing({ search: searchValue });
    };

    const handleStatusFilterChange = (value: string) => {
        updateListing({
            filter: value || 'all',
            supports: value === 'supports' ? filters.supports : [],
            search: searchValue,
        });
    };

    const handleSupportFilterChange = (values: string[]) => {
        updateListing({
            filter: values.length > 0 ? 'supports' : 'all',
            supports: values,
            search: searchValue,
        });
    };

    const clearFilters = () => {
        setSearchValue('');
        router.get(
            route('cms.appearance.themes.index'),
            {},
            {
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const handleImportSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!importForm.data.theme_zip) {
            importForm.setError(
                'theme_zip',
                'Please select a ZIP file to import.',
            );
            return;
        }

        importForm.post(route('cms.appearance.themes.import'), {
            preserveScroll: true,
            onSuccess: () => {
                setImportOpen(false);
                importForm.reset();
                importForm.clearErrors();
            },
        });
    };

    const openAction = (type: PendingAction['type'], theme: ThemeListItem) => {
        setPendingAction({ type, theme });
    };

    const runPendingAction = () => {
        if (!pendingAction) {
            return;
        }

        setActionProcessing(true);

        const sharedCallbacks = {
            preserveScroll: true,
            onFinish: () => {
                setActionProcessing(false);
                setPendingAction(null);
            },
        };

        if (pendingAction.type === 'activate') {
            router.post(
                route(
                    'cms.appearance.themes.activate',
                    pendingAction.theme.directory,
                ),
                {},
                sharedCallbacks,
            );
            return;
        }

        if (pendingAction.type === 'create-child') {
            router.post(
                route('cms.appearance.themes.create-child'),
                { parent_theme: pendingAction.theme.directory },
                sharedCallbacks,
            );
            return;
        }

        if (pendingAction.type === 'detach') {
            router.post(
                route(
                    'cms.appearance.themes.detach',
                    pendingAction.theme.directory,
                ),
                {},
                sharedCallbacks,
            );
            return;
        }

        router.delete(
            route(
                'cms.appearance.themes.destroy',
                pendingAction.theme.directory,
            ),
            sharedCallbacks,
        );
    };

    const hasFilters = Boolean(
        filters.search ||
        filters.filter !== 'all' ||
        filters.supports.length > 0,
    );

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Themes"
            description="Manage the active storefront design, import new themes, and maintain child-theme workflows."
            headerActions={
                canAddThemes ? (
                    <Button onClick={() => setImportOpen(true)}>
                        <UploadIcon data-icon="inline-start" />
                        Import Theme
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                {activeTheme ? (
                    <Card className="overflow-hidden border-primary/40 bg-primary/5">
                        <CardContent className="grid gap-6 p-6 lg:grid-cols-[320px_1fr]">
                            <ThemeScreenshot theme={activeTheme} />

                            <div className="flex flex-col gap-5">
                                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div className="space-y-3">
                                        <div className="flex flex-wrap items-center gap-3">
                                            <div>
                                                <p className="text-sm font-medium text-primary">
                                                    Currently live
                                                </p>
                                                <h2 className="text-2xl font-semibold tracking-tight">
                                                    {activeTheme.name}
                                                </h2>
                                            </div>
                                            <ThemeBadges theme={activeTheme} />
                                        </div>

                                        <p className="max-w-2xl text-sm text-muted-foreground">
                                            {activeTheme.description ||
                                                'This theme is currently active and serving your storefront experience.'}
                                        </p>

                                        <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                                            <span>
                                                <span className="font-medium text-foreground">
                                                    Version:
                                                </span>{' '}
                                                {activeTheme.version}
                                            </span>
                                            <span>
                                                <span className="font-medium text-foreground">
                                                    Author:
                                                </span>{' '}
                                                {activeTheme.author_uri ? (
                                                    <a
                                                        href={
                                                            activeTheme.author_uri
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="underline underline-offset-4 hover:text-foreground"
                                                    >
                                                        {activeTheme.author ||
                                                            'Unknown'}
                                                    </a>
                                                ) : (
                                                    activeTheme.author ||
                                                    'Unknown'
                                                )}
                                            </span>
                                            {activeTheme.parent ? (
                                                <span>
                                                    <span className="font-medium text-foreground">
                                                        Parent:
                                                    </span>{' '}
                                                    {activeTheme.parent}
                                                </span>
                                            ) : null}
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-3">
                                        <Button asChild>
                                            <a
                                                href={route(
                                                    'cms.appearance.themes.customizer.index',
                                                )}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <BrushIcon data-icon="inline-start" />
                                                Customize
                                            </a>
                                        </Button>
                                        <Button variant="outline" asChild>
                                            <a
                                                href={route(
                                                    'cms.appearance.themes.editor.index',
                                                    activeTheme.directory,
                                                )}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <CodeIcon data-icon="inline-start" />
                                                Edit files
                                            </a>
                                        </Button>
                                        <Button variant="outline" asChild>
                                            <a
                                                href={route(
                                                    'cms.appearance.themes.export',
                                                    activeTheme.directory,
                                                )}
                                            >
                                                <DownloadIcon data-icon="inline-start" />
                                                Export
                                            </a>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ) : null}

                <Card>
                    <CardHeader className="gap-4">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <CardTitle>Browse installed themes</CardTitle>
                                <CardDescription>
                                    Search by theme name, description, author,
                                    or capabilities.
                                </CardDescription>
                            </div>

                            {hasFilters ? (
                                <Button
                                    variant="outline"
                                    onClick={clearFilters}
                                >
                                    Clear filters
                                </Button>
                            ) : null}
                        </div>

                        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <form
                                onSubmit={submitSearch}
                                className="flex w-full flex-col gap-3 sm:flex-row sm:items-center lg:max-w-xl"
                            >
                                <Input
                                    value={searchValue}
                                    onChange={(event) =>
                                        setSearchValue(event.target.value)
                                    }
                                    placeholder="Search themes by name, author, or tag..."
                                    className="flex-1"
                                />
                                <div className="flex items-center gap-2">
                                    <Button type="submit" variant="secondary">
                                        <SearchIcon data-icon="inline-start" />
                                        Search
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => {
                                            setSearchValue('');
                                            updateListing({ search: '' });
                                        }}
                                        disabled={!searchValue}
                                        aria-label="Reset search"
                                    >
                                        <Trash2Icon />
                                    </Button>
                                </div>
                            </form>

                            <ToggleGroup
                                type="single"
                                value={
                                    filters.filter === 'supports'
                                        ? 'all'
                                        : filters.filter
                                }
                                onValueChange={handleStatusFilterChange}
                                variant="outline"
                            >
                                <ToggleGroupItem value="all">
                                    All
                                </ToggleGroupItem>
                                <ToggleGroupItem value="active">
                                    Active
                                </ToggleGroupItem>
                                <ToggleGroupItem value="inactive">
                                    Inactive
                                </ToggleGroupItem>
                            </ToggleGroup>
                        </div>
                    </CardHeader>
                </Card>

                {themes.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <PaletteIcon />
                            </EmptyMedia>
                            <EmptyTitle>No themes found</EmptyTitle>
                            <EmptyDescription>
                                {hasFilters
                                    ? 'No themes match the current search or filter combination.'
                                    : 'No themes are installed yet. Import one to get started.'}
                            </EmptyDescription>
                        </EmptyHeader>
                        {canAddThemes ? (
                            <Button onClick={() => setImportOpen(true)}>
                                <UploadIcon data-icon="inline-start" />
                                Import Theme
                            </Button>
                        ) : null}
                    </Empty>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {themes.map((theme) => (
                            <ThemeCard
                                key={theme.directory}
                                theme={theme}
                                canAddThemes={canAddThemes}
                                canEditThemes={canEditThemes}
                                canDeleteThemes={canDeleteThemes}
                                onAction={openAction}
                            />
                        ))}
                    </div>
                )}
            </div>

            <Dialog
                open={importOpen}
                onOpenChange={(open) => {
                    setImportOpen(open);
                    if (!open && !importForm.processing) {
                        importForm.reset();
                        importForm.clearErrors();
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Import theme</DialogTitle>
                        <DialogDescription>
                            Upload a ZIP archive containing a valid theme with a
                            manifest.json file.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={handleImportSubmit}
                        className="flex flex-col gap-5"
                    >
                        <Field>
                            <FieldLabel htmlFor="theme_zip">
                                Theme ZIP file
                            </FieldLabel>
                            <Input
                                id="theme_zip"
                                type="file"
                                accept=".zip"
                                onChange={(event) => {
                                    const file =
                                        event.currentTarget.files?.[0] ?? null;

                                    if (!file) {
                                        importForm.setData('theme_zip', null);
                                        return;
                                    }

                                    if (
                                        !file.name
                                            .toLowerCase()
                                            .endsWith('.zip')
                                    ) {
                                        importForm.setError(
                                            'theme_zip',
                                            'Please choose a ZIP file.',
                                        );
                                        importForm.setData('theme_zip', null);
                                        event.currentTarget.value = '';
                                        return;
                                    }

                                    if (file.size > 10 * 1024 * 1024) {
                                        importForm.setError(
                                            'theme_zip',
                                            'Theme archives must be 10MB or smaller.',
                                        );
                                        importForm.setData('theme_zip', null);
                                        event.currentTarget.value = '';
                                        return;
                                    }

                                    importForm.clearErrors('theme_zip');
                                    importForm.setData('theme_zip', file);
                                }}
                                aria-invalid={Boolean(
                                    importForm.errors.theme_zip,
                                )}
                            />
                            <FieldDescription>
                                Upload a ZIP file up to 10MB. The archive must
                                contain a valid manifest.json.
                            </FieldDescription>
                            {importForm.errors.theme_zip ? (
                                <p className="text-sm text-destructive">
                                    {importForm.errors.theme_zip}
                                </p>
                            ) : null}
                        </Field>

                        {importForm.progress ? (
                            <div className="flex flex-col gap-2">
                                <div className="flex items-center justify-between text-sm text-muted-foreground">
                                    <span>Uploading theme…</span>
                                    <span>
                                        {importForm.progress.percentage}%
                                    </span>
                                </div>
                                <Progress
                                    value={importForm.progress.percentage}
                                />
                            </div>
                        ) : null}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setImportOpen(false)}
                                disabled={importForm.processing}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={importForm.processing}
                            >
                                {importForm.processing ? (
                                    <Spinner />
                                ) : (
                                    <UploadIcon data-icon="inline-start" />
                                )}
                                Import theme
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog
                open={pendingAction !== null}
                onOpenChange={(open) => !open && setPendingAction(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogMedia>
                            {actionContent.icon}
                        </AlertDialogMedia>
                        <AlertDialogTitle>
                            {actionContent.title}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {actionContent.description}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={actionProcessing}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            variant={actionContent.variant}
                            disabled={actionProcessing}
                            onClick={runPendingAction}
                        >
                            {actionProcessing ? <Spinner /> : null}
                            {actionContent.confirmLabel}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
