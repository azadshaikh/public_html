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
            <img
                src={theme.screenshot}
                alt={`${theme.name} preview`}
                className="h-44 w-full rounded-xl object-cover ring-1 ring-border"
            />
        );
    }

    return (
        <div className="flex h-44 w-full items-center justify-center rounded-xl bg-muted ring-1 ring-border">
            <ImageIcon className="size-10 text-muted-foreground/50" />
        </div>
    );
}

function ThemeBadges({ theme }: { theme: ThemeListItem }) {
    return (
        <div className="flex flex-wrap gap-2">
            {theme.is_active ? <Badge variant="success">Active</Badge> : null}
            {theme.is_child ? <Badge variant="secondary">Child theme</Badge> : null}
            {theme.has_children ? (
                <Badge variant="outline">
                    {theme.child_count} {theme.child_count === 1 ? 'child' : 'children'}
                </Badge>
            ) : null}
            {theme.is_protected ? <Badge variant="outline">Protected</Badge> : null}
        </div>
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
                <div className="rounded-lg bg-muted p-2 text-muted-foreground">{icon}</div>
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
                <Button variant="outline" size="icon" aria-label={`More actions for ${theme.name}`}>
                    <MoreHorizontalIcon />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuLabel>Theme actions</DropdownMenuLabel>
                <DropdownMenuGroup>
                    <DropdownMenuItem asChild>
                        <a
                            href={route('cms.appearance.themes.editor.index', theme.directory)}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <CodeIcon />
                            Edit files
                        </a>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <a href={route('cms.appearance.themes.export', theme.directory)}>
                            <DownloadIcon />
                            Export theme
                        </a>
                    </DropdownMenuItem>
                </DropdownMenuGroup>

                {canAddThemes || canEditThemes || canDeleteThemes ? <DropdownMenuSeparator /> : null}

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

                    {!theme.is_active && !theme.is_protected && canDeleteThemes ? (
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
        <Card className="flex h-full flex-col overflow-hidden">
            <CardContent className="flex flex-1 flex-col gap-5 p-4">
                <ThemeScreenshot theme={theme} />

                <div className="flex flex-1 flex-col gap-4">
                    <div className="flex flex-col gap-3">
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0 space-y-1">
                                <h3 className="truncate text-lg font-semibold">{theme.name}</h3>
                                <p className="text-sm text-muted-foreground">
                                    Version {theme.version}
                                </p>
                            </div>
                            <ThemeActionMenu
                                theme={theme}
                                canAddThemes={canAddThemes}
                                canEditThemes={canEditThemes}
                                canDeleteThemes={canDeleteThemes}
                                onAction={onAction}
                            />
                        </div>

                        <ThemeBadges theme={theme} />

                        {theme.is_child && theme.parent ? (
                            <p className="text-sm text-muted-foreground">
                                Inherits from <span className="font-medium text-foreground">{theme.parent}</span>
                            </p>
                        ) : null}
                    </div>

                    <p className="line-clamp-3 text-sm text-muted-foreground">
                        {theme.description || 'No description provided for this theme.'}
                    </p>

                    <div className="flex flex-col gap-2 text-sm text-muted-foreground">
                        <p>
                            <span className="font-medium text-foreground">Author:</span>{' '}
                            {theme.author_uri ? (
                                <a
                                    href={theme.author_uri}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="underline underline-offset-4 hover:text-foreground"
                                >
                                    {theme.author || 'Unknown'}
                                </a>
                            ) : (
                                <span>{theme.author || 'Unknown'}</span>
                            )}
                        </p>

                        {theme.tags.length > 0 ? (
                            <div className="flex flex-wrap gap-2">
                                {theme.tags.slice(0, 4).map((tag) => (
                                    <Badge key={tag} variant="secondary">
                                        {tag}
                                    </Badge>
                                ))}
                            </div>
                        ) : null}

                        {theme.supports.length > 0 ? (
                            <div className="flex flex-wrap gap-2">
                                {theme.supports.slice(0, 3).map((support) => (
                                    <Badge key={support} variant="outline">
                                        {formatSupportLabel(support)}
                                    </Badge>
                                ))}
                            </div>
                        ) : null}
                    </div>
                </div>
            </CardContent>

            <CardFooter className="grid gap-2 border-t bg-muted/30 p-4 sm:grid-cols-[1fr_auto]">
                {theme.is_active ? (
                    <Button asChild>
                        <a
                            href={route('cms.appearance.themes.customizer.index')}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <BrushIcon data-icon="inline-start" />
                            Customize
                        </a>
                    </Button>
                ) : canEditThemes ? (
                    <Button onClick={() => onAction('activate', theme)}>
                        <CheckCircle2Icon data-icon="inline-start" />
                        Activate
                    </Button>
                ) : (
                    <Button variant="outline" disabled>
                        No edit access
                    </Button>
                )}

                <Button variant="outline" asChild>
                    <a
                        href={route('cms.appearance.themes.editor.index', theme.directory)}
                        target="_blank"
                        rel="noreferrer"
                    >
                        <CodeIcon data-icon="inline-start" />
                        Edit
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
            description: 'The current active theme will be replaced immediately.',
            confirmLabel: 'Activate theme',
            variant: 'default' as const,
            icon: <PaletteIcon className="size-5" />,
        };
    }

    if (action.type === 'create-child') {
        return {
            title: `Create a child theme from ${action.theme.name}?`,
            description: 'A new child theme will be generated automatically using this theme as its parent.',
            confirmLabel: 'Create child theme',
            variant: 'default' as const,
            icon: <GitBranchIcon className="size-5" />,
        };
    }

    if (action.type === 'detach') {
        return {
            title: `Make ${action.theme.name} standalone?`,
            description: 'Parent files will be copied into the child theme so it no longer inherits from its parent.',
            confirmLabel: 'Make standalone',
            variant: 'default' as const,
            icon: <UnplugIcon className="size-5" />,
        };
    }

    return {
        title: `Delete ${action.theme.name}?`,
        description: 'This permanently removes the theme files from disk. This action cannot be undone.',
        confirmLabel: 'Delete theme',
        variant: 'destructive' as const,
        icon: <Trash2Icon className="size-5" />,
    };
}

export default function ThemesIndex({
    themes,
    activeTheme,
    filters,
    statistics,
    availableSupports,
}: ThemeIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddThemes = page.props.auth.abilities.addThemes;
    const canEditThemes = page.props.auth.abilities.editThemes;
    const canDeleteThemes = page.props.auth.abilities.deleteThemes;
    const [searchValue, setSearchValue] = useState(filters.search);
    const [importOpen, setImportOpen] = useState(false);
    const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);
    const [actionProcessing, setActionProcessing] = useState(false);
    const importForm = useForm<ImportThemeFormData>({ theme_zip: null });

    const actionContent = useMemo(() => getActionContent(pendingAction), [pendingAction]);

    const updateListing = (next: { filter?: string; search?: string; supports?: string[] }) => {
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
        router.get(route('cms.appearance.themes.index'), {}, {
            preserveScroll: true,
            replace: true,
        });
    };

    const handleImportSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!importForm.data.theme_zip) {
            importForm.setError('theme_zip', 'Please select a ZIP file to import.');
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
            router.post(route('cms.appearance.themes.activate', pendingAction.theme.directory), {}, sharedCallbacks);
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
            router.post(route('cms.appearance.themes.detach', pendingAction.theme.directory), {}, sharedCallbacks);
            return;
        }

        router.delete(route('cms.appearance.themes.destroy', pendingAction.theme.directory), sharedCallbacks);
    };

    const hasFilters = Boolean(filters.search || filters.filter !== 'all' || filters.supports.length > 0);

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Themes"
            description="Manage the active storefront design, import new themes, and maintain child-theme workflows."
            headerActions={canAddThemes ? (
                <Button onClick={() => setImportOpen(true)}>
                    <UploadIcon data-icon="inline-start" />
                    Import Theme
                </Button>
            ) : undefined}
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <StatisticCard
                        title="Installed themes"
                        value={statistics.total}
                        description="All themes available on disk."
                        icon={<PaletteIcon className="size-5" />}
                    />
                    <StatisticCard
                        title="Active"
                        value={statistics.active}
                        description="Themes currently live or selected."
                        icon={<CheckCircle2Icon className="size-5" />}
                    />
                    <StatisticCard
                        title="Inactive"
                        value={statistics.inactive}
                        description="Installed themes ready to activate."
                        icon={<SparklesIcon className="size-5" />}
                    />
                    <StatisticCard
                        title="Child themes"
                        value={statistics.child}
                        description="Themes inheriting from a parent design."
                        icon={<GitBranchIcon className="size-5" />}
                    />
                    <StatisticCard
                        title="Protected"
                        value={statistics.protected}
                        description="System themes that cannot be deleted."
                        icon={<ShieldIcon className="size-5" />}
                    />
                </div>

                {activeTheme ? (
                    <Card className="overflow-hidden border-primary/40 bg-primary/5">
                        <CardContent className="grid gap-6 p-6 lg:grid-cols-[320px_1fr]">
                            <ThemeScreenshot theme={activeTheme} />

                            <div className="flex flex-col gap-5">
                                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div className="space-y-3">
                                        <div className="flex flex-wrap items-center gap-3">
                                            <div>
                                                <p className="text-sm font-medium text-primary">Currently live</p>
                                                <h2 className="text-2xl font-semibold tracking-tight">{activeTheme.name}</h2>
                                            </div>
                                            <ThemeBadges theme={activeTheme} />
                                        </div>

                                        <p className="max-w-2xl text-sm text-muted-foreground">
                                            {activeTheme.description || 'This theme is currently active and serving your storefront experience.'}
                                        </p>

                                        <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                                            <span>
                                                <span className="font-medium text-foreground">Version:</span> {activeTheme.version}
                                            </span>
                                            <span>
                                                <span className="font-medium text-foreground">Author:</span>{' '}
                                                {activeTheme.author_uri ? (
                                                    <a
                                                        href={activeTheme.author_uri}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="underline underline-offset-4 hover:text-foreground"
                                                    >
                                                        {activeTheme.author || 'Unknown'}
                                                    </a>
                                                ) : (
                                                    activeTheme.author || 'Unknown'
                                                )}
                                            </span>
                                            {activeTheme.parent ? (
                                                <span>
                                                    <span className="font-medium text-foreground">Parent:</span> {activeTheme.parent}
                                                </span>
                                            ) : null}
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-3">
                                        <Button asChild>
                                            <a
                                                href={route('cms.appearance.themes.customizer.index')}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <BrushIcon data-icon="inline-start" />
                                                Customize
                                            </a>
                                        </Button>
                                        <Button variant="outline" asChild>
                                            <a
                                                href={route('cms.appearance.themes.editor.index', activeTheme.directory)}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <CodeIcon data-icon="inline-start" />
                                                Edit files
                                            </a>
                                        </Button>
                                        <Button variant="outline" asChild>
                                            <a href={route('cms.appearance.themes.export', activeTheme.directory)}>
                                                <DownloadIcon data-icon="inline-start" />
                                                Export
                                            </a>
                                        </Button>
                                    </div>
                                </div>

                                {(activeTheme.tags.length > 0 || activeTheme.supports.length > 0) ? <Separator /> : null}

                                <div className="grid gap-4 md:grid-cols-2">
                                    {activeTheme.tags.length > 0 ? (
                                        <div className="flex flex-col gap-2">
                                            <p className="text-sm font-medium">Tags</p>
                                            <div className="flex flex-wrap gap-2">
                                                {activeTheme.tags.map((tag) => (
                                                    <Badge key={tag} variant="secondary">{tag}</Badge>
                                                ))}
                                            </div>
                                        </div>
                                    ) : null}

                                    {activeTheme.supports.length > 0 ? (
                                        <div className="flex flex-col gap-2">
                                            <p className="text-sm font-medium">Supported features</p>
                                            <div className="flex flex-wrap gap-2">
                                                {activeTheme.supports.map((support) => (
                                                    <Badge key={support} variant="outline">
                                                        {formatSupportLabel(support)}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ) : null}

                <Card>
                    <CardHeader className="gap-4">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <CardTitle>Browse installed themes</CardTitle>
                                <CardDescription>
                                    Search by theme name, description, author, or capabilities.
                                </CardDescription>
                            </div>

                            {hasFilters ? (
                                <Button variant="outline" onClick={clearFilters}>
                                    Clear filters
                                </Button>
                            ) : null}
                        </div>

                        <div className="flex flex-col gap-4">
                            <form onSubmit={submitSearch} className="flex flex-col gap-3 lg:flex-row">
                                <Input
                                    value={searchValue}
                                    onChange={(event) => setSearchValue(event.target.value)}
                                    placeholder="Search themes by name, author, or tag..."
                                    className="lg:max-w-md"
                                />
                                <div className="flex flex-wrap gap-3">
                                    <Button type="submit" variant="outline">
                                        <SearchIcon data-icon="inline-start" />
                                        Search
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => {
                                            setSearchValue('');
                                            updateListing({ search: '' });
                                        }}
                                        disabled={!searchValue}
                                    >
                                        Reset search
                                    </Button>
                                </div>
                            </form>

                            <div className="flex flex-col gap-3">
                                <ToggleGroup
                                    type="single"
                                    value={filters.filter === 'supports' ? 'all' : filters.filter}
                                    onValueChange={handleStatusFilterChange}
                                    variant="outline"
                                >
                                    <ToggleGroupItem value="all">All</ToggleGroupItem>
                                    <ToggleGroupItem value="active">Active</ToggleGroupItem>
                                    <ToggleGroupItem value="inactive">Inactive</ToggleGroupItem>
                                </ToggleGroup>

                                {availableSupports.length > 0 ? (
                                    <div className="flex flex-col gap-2">
                                        <p className="text-sm font-medium">Capability filters</p>
                                        <ToggleGroup
                                            type="multiple"
                                            value={filters.supports}
                                            onValueChange={handleSupportFilterChange}
                                            variant="outline"
                                            className="flex-wrap"
                                        >
                                            {availableSupports.map((support) => (
                                                <ToggleGroupItem key={support} value={support}>
                                                    {formatSupportLabel(support)}
                                                </ToggleGroupItem>
                                            ))}
                                        </ToggleGroup>
                                    </div>
                                ) : null}
                            </div>
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
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
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
                            Upload a ZIP archive containing a valid theme with a manifest.json file.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleImportSubmit} className="flex flex-col gap-5">
                        <Field>
                            <FieldLabel htmlFor="theme_zip">Theme ZIP file</FieldLabel>
                            <Input
                                id="theme_zip"
                                type="file"
                                accept=".zip"
                                onChange={(event) => {
                                    const file = event.currentTarget.files?.[0] ?? null;

                                    if (!file) {
                                        importForm.setData('theme_zip', null);
                                        return;
                                    }

                                    if (!file.name.toLowerCase().endsWith('.zip')) {
                                        importForm.setError('theme_zip', 'Please choose a ZIP file.');
                                        importForm.setData('theme_zip', null);
                                        event.currentTarget.value = '';
                                        return;
                                    }

                                    if (file.size > 10 * 1024 * 1024) {
                                        importForm.setError('theme_zip', 'Theme archives must be 10MB or smaller.');
                                        importForm.setData('theme_zip', null);
                                        event.currentTarget.value = '';
                                        return;
                                    }

                                    importForm.clearErrors('theme_zip');
                                    importForm.setData('theme_zip', file);
                                }}
                                aria-invalid={Boolean(importForm.errors.theme_zip)}
                            />
                            <FieldDescription>
                                Upload a ZIP file up to 10MB. The archive must contain a valid manifest.json.
                            </FieldDescription>
                            {importForm.errors.theme_zip ? (
                                <p className="text-sm text-destructive">{importForm.errors.theme_zip}</p>
                            ) : null}
                        </Field>

                        {importForm.progress ? (
                            <div className="flex flex-col gap-2">
                                <div className="flex items-center justify-between text-sm text-muted-foreground">
                                    <span>Uploading theme…</span>
                                    <span>{importForm.progress.percentage}%</span>
                                </div>
                                <Progress value={importForm.progress.percentage} />
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
                            <Button type="submit" disabled={importForm.processing}>
                                {importForm.processing ? <Spinner /> : <UploadIcon data-icon="inline-start" />}
                                Import theme
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog open={pendingAction !== null} onOpenChange={(open) => !open && setPendingAction(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogMedia>{actionContent.icon}</AlertDialogMedia>
                        <AlertDialogTitle>{actionContent.title}</AlertDialogTitle>
                        <AlertDialogDescription>{actionContent.description}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={actionProcessing}>Cancel</AlertDialogCancel>
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
