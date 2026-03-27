import { router, usePage } from '@inertiajs/react';
import { Clock3Icon, SearchIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { NavigationIcon } from '@/components/navigation-icon';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import { Kbd, KbdGroup } from '@/components/ui/kbd';
import { normalizeInertiaNavigationUrl } from '@/lib/inertia-session-reload.js';
import {
    flattenNavigationForQuickOpen,
    getQuickOpenResults,
    isQuickOpenShortcut,
    readRecentQuickOpenUrls,
    resolveRecentQuickOpenEntries,
} from '@/lib/quick-open.js';
import { cn } from '@/lib/utils';
import type { AuthenticatedSharedData } from '@/types';

type QuickOpenEntry = {
    id: string;
    label: string;
    description: string | null;
    url: string;
    normalizedUrl: string;
    method: 'delete' | 'get' | 'patch' | 'post' | 'put';
    icon?: string | null;
    sectionKey: string;
    sectionLabel: string;
    sectionArea: string;
    trail: string;
    aliases: string[];
    keywords: string[];
    priority: number;
    hardReload: boolean;
    target?: string | null;
    sidebarVisible: boolean;
    order: number;
    sectionOrder: number;
};

type QuickOpenResultItem = {
    entry: QuickOpenEntry;
    score: number;
};

type QuickOpenResultGroup = {
    key: string;
    label: string;
    entries: QuickOpenEntry[];
};

function sectionDescription(entry: QuickOpenEntry): string {
    if (entry.trail === '') {
        return entry.sectionLabel;
    }

    return `${entry.sectionLabel} / ${entry.trail}`;
}

function QuickOpenResult({
    entry,
    icon,
}: {
    entry: QuickOpenEntry;
    icon?: ReactNode;
}) {
    return (
        <div className="flex min-w-0 flex-1 items-center gap-3">
            <div className="flex size-8 shrink-0 items-center justify-center rounded-lg border border-border/70 bg-muted/40 text-muted-foreground">
                {icon ??
                    (entry.icon ? (
                        <NavigationIcon
                            svg={entry.icon}
                            className="[&_svg]:size-4 [&_svg]:stroke-[1.8]"
                        />
                    ) : (
                        <SearchIcon className="size-4" />
                    ))}
            </div>

            <div className="min-w-0 flex-1">
                <div className="truncate font-medium text-foreground">
                    {entry.label}
                </div>
                <div className="truncate text-xs text-muted-foreground">
                    {entry.description ?? sectionDescription(entry)}
                </div>
            </div>
        </div>
    );
}

export function QuickOpenDialog() {
    const page = usePage<AuthenticatedSharedData>();
    const { navigation } = page.props;
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const entries = flattenNavigationForQuickOpen(
        navigation,
    ) as QuickOpenEntry[];
    const recentUrls =
        typeof window === 'undefined'
            ? []
            : readRecentQuickOpenUrls(window.localStorage);
    const recentEntries =
        typeof window === 'undefined'
            ? []
            : (resolveRecentQuickOpenEntries(
                  entries,
                  recentUrls,
              ) as QuickOpenEntry[]);
    const filteredResults = getQuickOpenResults(
        entries,
        search,
        recentUrls,
    ) as QuickOpenResultItem[];

    const currentPageUrl = normalizeInertiaNavigationUrl(page.url);
    const showRecentEntries = search.trim() === '' && recentEntries.length > 0;
    const orderedSectionsMap = new Map<string, QuickOpenResultGroup>();

    filteredResults.forEach((result) => {
        const existingSection = orderedSectionsMap.get(
            result.entry.sectionKey,
        ) ?? {
            key: result.entry.sectionKey,
            label: result.entry.sectionLabel,
            entries: [],
        };

        existingSection.entries.push(result.entry);
        orderedSectionsMap.set(result.entry.sectionKey, existingSection);
    });

    const orderedSections = Array.from(orderedSectionsMap.values());

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (!isQuickOpenShortcut(event)) {
                return;
            }

            event.preventDefault();
            setSearch('');
            setOpen(true);
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        window.requestAnimationFrame(() => {
            const input = document.querySelector<HTMLInputElement>(
                '[data-slot="command-input"]',
            );

            input?.focus();
            input?.select();
        });
    }, [open]);

    function openDialog(): void {
        setSearch('');
        setOpen(true);
    }

    function handleOpenChange(nextOpen: boolean): void {
        if (!nextOpen) {
            setSearch('');
        }

        setOpen(nextOpen);
    }

    function openEntry(entry: QuickOpenEntry): void {
        setSearch('');
        setOpen(false);

        if (entry.normalizedUrl === currentPageUrl) {
            return;
        }

        if (entry.method !== 'get') {
            router.visit(entry.url, {
                method: entry.method,
            });

            return;
        }

        if (entry.target === '_blank') {
            window.open(entry.url, '_blank', 'noopener,noreferrer');

            return;
        }

        if (entry.hardReload) {
            window.location.assign(entry.url);

            return;
        }

        router.visit(entry.url);
    }

    return (
        <>
            <Button
                type="button"
                variant="outline"
                className="hidden min-w-72 justify-between rounded-full border border-border/80 bg-card px-4 text-muted-foreground shadow-sm hover:bg-muted lg:inline-flex"
                onClick={openDialog}
            >
                <span className="flex items-center gap-2">
                    <SearchIcon data-icon="inline-start" />
                    Quick open
                </span>
                <KbdGroup className="text-[11px]">
                    <Kbd>Ctrl</Kbd>
                    <Kbd>K</Kbd>
                </KbdGroup>
            </Button>

            <Button
                type="button"
                variant="outline"
                size="icon-lg"
                className="rounded-full border border-border/80 bg-card shadow-sm hover:bg-muted lg:hidden"
                onClick={openDialog}
            >
                <SearchIcon />
                <span className="sr-only">Open quick navigation</span>
            </Button>

            <CommandDialog
                open={open}
                onOpenChange={handleOpenChange}
                title="Quick open"
                description="Search recent links, navigation pages, and actions."
                className="max-w-2xl"
            >
                <Command shouldFilter={false}>
                    <CommandInput
                        value={search}
                        onValueChange={setSearch}
                        placeholder="Search pages and actions..."
                    />
                    <CommandList className="max-h-[24rem]">
                        {orderedSections.length === 0 ? (
                            <CommandEmpty>
                                No pages or actions match your search.
                            </CommandEmpty>
                        ) : null}

                        {showRecentEntries ? (
                            <>
                                <CommandGroup heading="Recent">
                                    {recentEntries.map((entry) => (
                                        <CommandItem
                                            key={`recent:${entry.id}`}
                                            value={`${entry.label} ${entry.sectionLabel}`}
                                            onSelect={() => openEntry(entry)}
                                        >
                                            <QuickOpenResult
                                                entry={entry}
                                                icon={
                                                    <Clock3Icon className="size-4" />
                                                }
                                            />
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                                <CommandSeparator />
                            </>
                        ) : null}

                        {orderedSections.map((section) => (
                            <CommandGroup
                                key={section.key}
                                heading={section.label}
                            >
                                {section.entries.map((entry) => (
                                    <CommandItem
                                        key={entry.id}
                                        value={`${entry.label} ${entry.sectionLabel}`}
                                        onSelect={() => openEntry(entry)}
                                        className={cn(
                                            entry.normalizedUrl ===
                                                currentPageUrl
                                                ? 'bg-muted/50'
                                                : undefined,
                                        )}
                                    >
                                        <QuickOpenResult entry={entry} />
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        ))}
                    </CommandList>
                </Command>
            </CommandDialog>
        </>
    );
}
