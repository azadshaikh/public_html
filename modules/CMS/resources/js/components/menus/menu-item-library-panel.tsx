import { PlusIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Kbd, KbdGroup } from '@/components/ui/kbd';
import { ScrollArea } from '@/components/ui/scroll-area';
import { SearchInput } from '@/components/ui/search-input';
import { Separator } from '@/components/ui/separator';
import type { DraftMenuItem } from './menu-editor-types';

type LibraryItem = {
    id: number;
    title: string;
    slug: string;
};

type ItemLibraryPanelProps = {
    pages: LibraryItem[];
    categories: LibraryItem[];
    tags: LibraryItem[];
    currentItems: DraftMenuItem[];
    onAddItem: (
        item: Omit<DraftMenuItem, 'id' | 'parent_id' | 'sort_order'>,
    ) => void;
};

function useLibraryFilter<T extends { title: string }>(items: T[]) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        if (query.trim() === '') {
            return items;
        }

        return items.filter((item) =>
            item.title.toLowerCase().includes(query.toLowerCase()),
        );
    }, [items, query]);

    return { query, setQuery, filtered };
}

type LibrarySectionProps = {
    title: string;
    value: string;
    placeholder: string;
    items: LibraryItem[];
    totalCount: number;
    itemType: 'page' | 'category' | 'tag';
    query: string;
    onQueryChange: (value: string) => void;
    onAdd: (item: LibraryItem, type: 'page' | 'category' | 'tag') => void;
    countInMenu: (objectId: number, type: string) => number;
};

function LibrarySection({
    title,
    value,
    placeholder,
    items,
    totalCount,
    itemType,
    query,
    onQueryChange,
    onAdd,
    countInMenu,
}: LibrarySectionProps) {
    return (
        <AccordionItem value={value} className="overflow-hidden rounded-xl border">
            <AccordionTrigger className="px-4 py-3 text-left text-sm font-medium hover:bg-muted/20 hover:no-underline">
                <div className="flex min-w-0 flex-1 items-center gap-3">
                    <div className="min-w-0 flex-1">
                        <p>{title}</p>
                    </div>
                    <Badge variant="outline">{totalCount}</Badge>
                </div>
            </AccordionTrigger>
            <AccordionContent>
                <div className="flex flex-col gap-3 p-4">
                    <SearchInput
                        value={query}
                        onChange={onQueryChange}
                        size="comfortable"
                        placeholder={placeholder}
                        containerClassName="w-full"
                    />
                    <div className="flex flex-col gap-2">
                        {items.length === 0 ? (
                            <p className="py-4 text-center text-xs text-muted-foreground">
                                No {value} found.
                            </p>
                        ) : (
                            items.map((item) => {
                                const count = countInMenu(item.id, itemType);

                                return (
                                    <button
                                        key={item.id}
                                        type="button"
                                        onClick={() => onAdd(item, itemType)}
                                        className="group w-full rounded-xl border bg-background p-4 text-left transition hover:border-primary/40 hover:bg-accent/30"
                                    >
                                        <div className="flex items-start gap-3">
                                            <div className="flex-1 space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium">
                                                        {item.title}
                                                    </p>
                                                    {count > 0 ? (
                                                        <Badge variant="secondary">
                                                            Used {count}×
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                                <p className="line-clamp-2 text-sm text-muted-foreground">
                                                    {item.slug}
                                                </p>
                                            </div>
                                            <div className="rounded-lg border bg-muted/40 p-2 text-primary transition group-hover:border-primary/40 group-hover:bg-primary group-hover:text-primary-foreground">
                                                <PlusIcon className="size-4" />
                                            </div>
                                        </div>
                                    </button>
                                );
                            })
                        )}
                    </div>
                </div>
            </AccordionContent>
        </AccordionItem>
    );
}

export function MenuItemLibraryPanel({
    pages,
    categories,
    tags,
    currentItems,
    onAddItem,
}: ItemLibraryPanelProps) {
    const [customTitle, setCustomTitle] = useState('');
    const [customUrl, setCustomUrl] = useState('');

    const pagesFilter = useLibraryFilter(pages);
    const categoriesFilter = useLibraryFilter(categories);
    const tagsFilter = useLibraryFilter(tags);

    const handleAddCustom = (event: FormEvent) => {
        event.preventDefault();
        if (!customTitle.trim()) {
            return;
        }

        onAddItem({
            title: customTitle.trim(),
            url: customUrl.trim() || '#',
            type: 'custom',
            target: '_self',
            icon: '',
            css_classes: '',
            link_title: '',
            link_rel: '',
            description: '',
            object_id: null,
            is_active: true,
        });
        setCustomTitle('');
        setCustomUrl('');
    };

    const addContentItem = (
        item: LibraryItem,
        type: 'page' | 'category' | 'tag',
    ) => {
        onAddItem({
            title: item.title,
            url: '',
            type,
            target: '_self',
            icon: '',
            css_classes: '',
            link_title: '',
            link_rel: '',
            description: '',
            object_id: item.id,
            is_active: true,
        });
    };

    const countInMenu = (objectId: number, type: string) =>
        currentItems.filter(
            (item) => item.object_id === objectId && item.type === type,
        ).length;

    const totalAvailable = pages.length + categories.length + tags.length;

    return (
        <Card className="sticky top-4 flex max-h-[calc(100vh-7rem)] min-h-0 flex-col overflow-hidden">
            <CardHeader className="pb-0">
                <div className="flex items-center gap-2">
                    <CardTitle className="text-base">Add Items</CardTitle>
                    <Badge variant="secondary">{totalAvailable}</Badge>
                </div>
            </CardHeader>

            <CardContent className="flex min-h-0 flex-1 flex-col gap-4 p-4 pt-3">
                <ScrollArea className="min-h-0 flex-1">
                    <div className="flex flex-col gap-4 pr-1">
                        <Accordion
                            type="multiple"
                            defaultValue={['custom', 'pages']}
                            className="flex flex-col gap-3"
                        >
                            <AccordionItem
                                value="custom"
                                className="overflow-hidden rounded-xl border"
                            >
                                <AccordionTrigger className="px-4 py-3 text-left text-sm font-medium hover:bg-muted/20 hover:no-underline">
                                    Custom Link
                                </AccordionTrigger>
                                <AccordionContent>
                                    <form
                                        noValidate
                                        onSubmit={handleAddCustom}
                                        className="flex flex-col gap-3 p-4"
                                    >
                                        <Field>
                                            <FieldLabel htmlFor="custom-url">
                                                URL
                                            </FieldLabel>
                                            <Input
                                                id="custom-url"
                                                value={customUrl}
                                                onChange={(event) =>
                                                    setCustomUrl(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="https:// or /path"
                                            />
                                        </Field>
                                        <Field>
                                            <FieldLabel htmlFor="custom-title">
                                                Link Text{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </FieldLabel>
                                            <Input
                                                id="custom-title"
                                                value={customTitle}
                                                onChange={(event) =>
                                                    setCustomTitle(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Navigation label"
                                            />
                                        </Field>
                                        <Button
                                            type="submit"
                                            className="w-full"
                                            disabled={!customTitle.trim()}
                                        >
                                            <PlusIcon className="size-4" />
                                            Add to Menu
                                        </Button>
                                    </form>
                                </AccordionContent>
                            </AccordionItem>

                            <LibrarySection
                                title="Pages"
                                value="pages"
                                placeholder="Search pages…"
                                items={pagesFilter.filtered}
                                totalCount={pages.length}
                                itemType="page"
                                query={pagesFilter.query}
                                onQueryChange={pagesFilter.setQuery}
                                onAdd={addContentItem}
                                countInMenu={countInMenu}
                            />

                            <LibrarySection
                                title="Categories"
                                value="categories"
                                placeholder="Search categories…"
                                items={categoriesFilter.filtered}
                                totalCount={categories.length}
                                itemType="category"
                                query={categoriesFilter.query}
                                onQueryChange={categoriesFilter.setQuery}
                                onAdd={addContentItem}
                                countInMenu={countInMenu}
                            />

                            <LibrarySection
                                title="Tags"
                                value="tags"
                                placeholder="Search tags…"
                                items={tagsFilter.filtered}
                                totalCount={tags.length}
                                itemType="tag"
                                query={tagsFilter.query}
                                onQueryChange={tagsFilter.setQuery}
                                onAdd={addContentItem}
                                countInMenu={countInMenu}
                            />
                        </Accordion>
                    </div>
                </ScrollArea>
            </CardContent>

            <Separator />

            <div className="p-4 text-sm text-muted-foreground">
                <div className="flex flex-wrap items-center gap-2">
                    <span>Save anytime with</span>
                    <KbdGroup>
                        <Kbd>Ctrl</Kbd>
                        <span>+</span>
                        <Kbd>S</Kbd>
                    </KbdGroup>
                </div>
            </div>
        </Card>
    );
}
