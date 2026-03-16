'use client';

import { Link, useHttp } from '@inertiajs/react';
import {
    ArrowDownIcon,
    ArrowLeftIcon,
    ArrowRightIcon,
    ArrowUpIcon,
    FolderIcon,
    GripVerticalIcon,
    HomeIcon,
    LinkIcon,
    PencilIcon,
    PlusIcon,
    SaveIcon,
    SearchIcon,
    TagIcon,
    Trash2Icon,
} from 'lucide-react';
import {
    type DragEvent,
    type FormEvent,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { MenuEditPageProps } from '../../types/cms';

// ----------------------------------------------------------------
// Types
// ----------------------------------------------------------------

type DraftMenuItem = {
    id: number;
    parent_id: number;
    title: string;
    url: string;
    type: string;
    target: string;
    icon: string;
    css_classes: string;
    link_title: string;
    link_rel: string;
    description: string;
    object_id: number | null;
    sort_order: number;
    is_active: boolean;
};

type MenuSettings = {
    name: string;
    location: string;
    is_active: boolean;
    description: string;
};

type RenderItem = {
    item: DraftMenuItem;
    depth: number;
};

type SavePayload = {
    settings: MenuSettings;
    items: {
        new: DraftMenuItem[];
        updated: DraftMenuItem[];
        deleted: { id: number }[];
        order: { id: number; parent_id: number; sort_order: number }[];
    };
};

type SaveResponse = {
    success: boolean;
    message: string;
    newItemIds: Record<string, number>;
};

// ----------------------------------------------------------------
// Utility functions
// ----------------------------------------------------------------

function buildRenderOrder(items: DraftMenuItem[], parentId: number, depth = 0): RenderItem[] {
    return items
        .filter((i) => i.parent_id === parentId)
        .sort((a, b) => a.sort_order - b.sort_order)
        .flatMap((item) => [{ item, depth }, ...buildRenderOrder(items, item.id, depth + 1)]);
}

function getItemDepth(items: DraftMenuItem[], itemId: number, menuId: number): number {
    const item = items.find((i) => i.id === itemId);
    if (!item || item.parent_id === menuId) return 0;
    return 1 + getItemDepth(items, item.parent_id, menuId);
}

function getSubtreeMaxDepth(items: DraftMenuItem[], itemId: number, menuId: number): number {
    const baseDepth = getItemDepth(items, itemId, menuId);
    const children = items.filter((i) => i.parent_id === itemId);
    if (children.length === 0) return baseDepth;
    return Math.max(...children.map((c) => getSubtreeMaxDepth(items, c.id, menuId)));
}

function isDescendant(items: DraftMenuItem[], ancestorId: number, targetId: number): boolean {
    const target = items.find((i) => i.id === targetId);
    if (!target) return false;
    if (target.parent_id === ancestorId) return true;
    return isDescendant(items, ancestorId, target.parent_id);
}

function applyDrop(
    prevItems: DraftMenuItem[],
    draggedId: number,
    targetId: number,
    position: 'before' | 'after',
): DraftMenuItem[] {
    if (draggedId === targetId) return prevItems;
    if (isDescendant(prevItems, draggedId, targetId)) return prevItems;

    const items = prevItems.map((i) => ({ ...i }));
    const dragged = items.find((i) => i.id === draggedId)!;
    const target = items.find((i) => i.id === targetId)!;
    if (!dragged || !target) return prevItems;

    const oldParentId = dragged.parent_id;
    dragged.parent_id = target.parent_id;

    if (oldParentId !== target.parent_id) {
        items
            .filter((i) => i.parent_id === oldParentId && i.id !== draggedId)
            .sort((a, b) => a.sort_order - b.sort_order)
            .forEach((item, idx) => {
                item.sort_order = idx;
            });
    }

    const siblings = items
        .filter((i) => i.parent_id === target.parent_id && i.id !== draggedId)
        .sort((a, b) => a.sort_order - b.sort_order);

    const targetIdx = siblings.findIndex((i) => i.id === targetId);
    const insertAt = position === 'before' ? Math.max(0, targetIdx) : targetIdx + 1;
    siblings.splice(insertAt, 0, dragged);
    siblings.forEach((item, idx) => {
        item.sort_order = idx;
    });

    return [...items];
}

function moveItemUp(prevItems: DraftMenuItem[], itemId: number): DraftMenuItem[] {
    const items = prevItems.map((i) => ({ ...i }));
    const item = items.find((i) => i.id === itemId)!;
    if (!item) return prevItems;
    const siblings = items
        .filter((i) => i.parent_id === item.parent_id)
        .sort((a, b) => a.sort_order - b.sort_order);
    const idx = siblings.findIndex((i) => i.id === itemId);
    if (idx <= 0) return prevItems;
    const prev = siblings[idx - 1];
    const tmp = prev.sort_order;
    prev.sort_order = item.sort_order;
    item.sort_order = tmp;
    return [...items];
}

function moveItemDown(prevItems: DraftMenuItem[], itemId: number): DraftMenuItem[] {
    const items = prevItems.map((i) => ({ ...i }));
    const item = items.find((i) => i.id === itemId)!;
    if (!item) return prevItems;
    const siblings = items
        .filter((i) => i.parent_id === item.parent_id)
        .sort((a, b) => a.sort_order - b.sort_order);
    const idx = siblings.findIndex((i) => i.id === itemId);
    if (idx >= siblings.length - 1) return prevItems;
    const next = siblings[idx + 1];
    const tmp = next.sort_order;
    next.sort_order = item.sort_order;
    item.sort_order = tmp;
    return [...items];
}

function indentItem(
    prevItems: DraftMenuItem[],
    itemId: number,
    menuId: number,
    maxDepth: number,
): DraftMenuItem[] {
    const items = prevItems.map((i) => ({ ...i }));
    const item = items.find((i) => i.id === itemId)!;
    if (!item) return prevItems;

    const siblings = items
        .filter((i) => i.parent_id === item.parent_id)
        .sort((a, b) => a.sort_order - b.sort_order);
    const idx = siblings.findIndex((i) => i.id === itemId);
    if (idx <= 0) return prevItems;

    const newParent = siblings[idx - 1];
    const newParentDepth = getItemDepth(items, newParent.id, menuId);
    const subtreeHeight = getSubtreeMaxDepth(items, itemId, menuId) - getItemDepth(items, itemId, menuId);
    if (newParentDepth + 1 + subtreeHeight >= maxDepth) return prevItems;

    item.parent_id = newParent.id;
    siblings.filter((s) => s.id !== itemId).forEach((s, i) => {
        s.sort_order = i;
    });

    const newParentChildren = items
        .filter((i) => i.parent_id === newParent.id && i.id !== itemId)
        .sort((a, b) => a.sort_order - b.sort_order);
    item.sort_order = newParentChildren.length;

    return [...items];
}

function outdentItem(prevItems: DraftMenuItem[], itemId: number, menuId: number): DraftMenuItem[] {
    const items = prevItems.map((i) => ({ ...i }));
    const item = items.find((i) => i.id === itemId)!;
    if (!item || item.parent_id === menuId) return prevItems;

    const parent = items.find((i) => i.id === item.parent_id);
    if (!parent) return prevItems;

    const grandParentId = parent.parent_id;

    const oldSiblings = items
        .filter((i) => i.parent_id === item.parent_id && i.id !== itemId)
        .sort((a, b) => a.sort_order - b.sort_order);
    oldSiblings.forEach((s, i) => {
        s.sort_order = i;
    });

    const grandParentChildren = items
        .filter((i) => i.parent_id === grandParentId && i.id !== itemId)
        .sort((a, b) => a.sort_order - b.sort_order);
    const parentIdx = grandParentChildren.findIndex((i) => i.id === parent.id);
    grandParentChildren.splice(parentIdx + 1, 0, item);
    grandParentChildren.forEach((c, i) => {
        c.sort_order = i;
    });
    item.parent_id = grandParentId;

    return [...items];
}

function collectDescendantIds(items: DraftMenuItem[], itemId: number): number[] {
    const children = items.filter((i) => i.parent_id === itemId);
    return children.flatMap((c) => [c.id, ...collectDescendantIds(items, c.id)]);
}

function getTypeIcon(type: string) {
    switch (type) {
        case 'page':
            return <FolderIcon className="size-3.5 shrink-0 text-blue-500" />;
        case 'category':
            return <FolderIcon className="size-3.5 shrink-0 text-amber-500" />;
        case 'tag':
            return <TagIcon className="size-3.5 shrink-0 text-green-500" />;
        case 'home':
            return <HomeIcon className="size-3.5 shrink-0 text-purple-500" />;
        case 'search':
            return <SearchIcon className="size-3.5 shrink-0 text-muted-foreground" />;
        default:
            return <LinkIcon className="size-3.5 shrink-0 text-muted-foreground" />;
    }
}

// ----------------------------------------------------------------
// Menu Item Row
// ----------------------------------------------------------------

type MenuItemRowProps = {
    renderItem: RenderItem;
    allItems: DraftMenuItem[];
    menuId: number;
    maxDepth: number;
    isDraggedOver: 'before' | 'after' | null;
    isDragging: boolean;
    onDragStart: (e: DragEvent<HTMLDivElement>, id: number) => void;
    onDragOver: (e: DragEvent<HTMLDivElement>, id: number) => void;
    onDrop: (e: DragEvent<HTMLDivElement>) => void;
    onDragEnd: () => void;
    onMoveUp: (id: number) => void;
    onMoveDown: (id: number) => void;
    onIndent: (id: number) => void;
    onOutdent: (id: number) => void;
    onEdit: (item: DraftMenuItem) => void;
    onDelete: (id: number) => void;
};

function MenuItemRow({
    renderItem,
    allItems,
    menuId,
    maxDepth,
    isDraggedOver,
    isDragging,
    onDragStart,
    onDragOver,
    onDrop,
    onDragEnd,
    onMoveUp,
    onMoveDown,
    onIndent,
    onOutdent,
    onEdit,
    onDelete,
}: MenuItemRowProps) {
    const { item, depth } = renderItem;

    const siblings = allItems
        .filter((i) => i.parent_id === item.parent_id)
        .sort((a, b) => a.sort_order - b.sort_order);
    const siblingIdx = siblings.findIndex((i) => i.id === item.id);
    const canMoveUp = siblingIdx > 0;
    const canMoveDown = siblingIdx < siblings.length - 1;
    const canOutdent = item.parent_id !== menuId;
    const hasPreviousSibling = siblingIdx > 0;
    const prevSibling = hasPreviousSibling ? siblings[siblingIdx - 1] : null;
    const prevSiblingDepth = prevSibling ? getItemDepth(allItems, prevSibling.id, menuId) : 0;
    const subtreeHeight =
        getSubtreeMaxDepth(allItems, item.id, menuId) - getItemDepth(allItems, item.id, menuId);
    const canIndent = hasPreviousSibling && prevSiblingDepth + 1 + subtreeHeight < maxDepth;

    return (
        <div
            className="relative"
            style={{ paddingLeft: `${depth * 24}px` }}
        >
            {isDraggedOver === 'before' && (
                <div className="absolute top-0 right-0 left-0 h-0.5 rounded bg-primary" style={{ zIndex: 10 }} />
            )}
            {isDraggedOver === 'after' && (
                <div className="absolute right-0 bottom-0 left-0 h-0.5 rounded bg-primary" style={{ zIndex: 10 }} />
            )}
            <div
                draggable
                onDragStart={(e) => onDragStart(e, item.id)}
                onDragOver={(e) => onDragOver(e, item.id)}
                onDrop={(e) => onDrop(e)}
                onDragEnd={onDragEnd}
                className={[
                    'flex items-center gap-2 rounded-lg border px-2 py-1.5 text-sm transition-colors',
                    isDragging ? 'opacity-40' : '',
                    isDraggedOver ? 'bg-muted/50' : 'bg-card hover:bg-muted/30',
                ]
                    .filter(Boolean)
                    .join(' ')}
            >
                <GripVerticalIcon className="size-4 shrink-0 cursor-grab text-muted-foreground active:cursor-grabbing" />

                {getTypeIcon(item.type)}

                <span className="min-w-0 flex-1 truncate font-medium leading-tight">
                    {item.title}
                </span>

                {item.url && (
                    <span className="hidden max-w-[160px] truncate text-xs text-muted-foreground lg:block">
                        {item.url}
                    </span>
                )}

                {!item.is_active && (
                    <Badge variant="secondary" className="hidden shrink-0 text-[10px] sm:inline-flex">
                        Inactive
                    </Badge>
                )}

                {/* Reorder / structure controls */}
                <div className="flex shrink-0 items-center gap-0.5">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        disabled={!canMoveUp}
                        onClick={() => onMoveUp(item.id)}
                        title="Move up"
                    >
                        <ArrowUpIcon className="size-3.5" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        disabled={!canMoveDown}
                        onClick={() => onMoveDown(item.id)}
                        title="Move down"
                    >
                        <ArrowDownIcon className="size-3.5" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        disabled={!canOutdent}
                        onClick={() => onOutdent(item.id)}
                        title="Outdent"
                    >
                        <ArrowLeftIcon className="size-3.5" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        disabled={!canIndent}
                        onClick={() => onIndent(item.id)}
                        title="Indent"
                    >
                        <ArrowRightIcon className="size-3.5" />
                    </Button>
                </div>

                <Separator orientation="vertical" className="h-5" />

                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    onClick={() => onEdit(item)}
                    title="Edit item"
                >
                    <PencilIcon className="size-3.5" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    className="text-destructive hover:text-destructive"
                    onClick={() => onDelete(item.id)}
                    title="Delete item"
                >
                    <Trash2Icon className="size-3.5" />
                </Button>
            </div>
        </div>
    );
}

// ----------------------------------------------------------------
// Item Edit Sheet
// ----------------------------------------------------------------

type ItemEditSheetProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: DraftMenuItem | null;
    itemTypes: Record<string, string>;
    itemTargets: Record<string, string>;
    onSave: (updated: DraftMenuItem) => void;
};

function ItemEditSheet({ open, onOpenChange, item, itemTypes, itemTargets, onSave }: ItemEditSheetProps) {
    const [draft, setDraft] = useState<DraftMenuItem | null>(null);

    useEffect(() => {
        if (item) setDraft({ ...item });
    }, [item]);

    if (!draft) return null;

    const set = <K extends keyof DraftMenuItem>(key: K, value: DraftMenuItem[K]) => {
        setDraft((prev) => (prev ? { ...prev, [key]: value } : prev));
    };

    const handleSave = (e: FormEvent) => {
        e.preventDefault();
        if (!draft.title.trim()) return;
        onSave(draft);
        onOpenChange(false);
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="w-full sm:max-w-lg overflow-y-auto">
                <SheetHeader>
                    <SheetTitle>Edit Menu Item</SheetTitle>
                    <SheetDescription>Update the properties of this navigation item.</SheetDescription>
                </SheetHeader>

                <form noValidate onSubmit={handleSave} className="flex flex-col gap-5 px-4 py-2">
                    <Accordion type="multiple" defaultValue={['basic', 'appearance', 'behavior']}>
                        {/* Basic */}
                        <AccordionItem value="basic">
                            <AccordionTrigger>Basic</AccordionTrigger>
                            <AccordionContent className="flex flex-col gap-4 !pt-2">
                                <Field>
                                    <FieldLabel htmlFor="item-title">
                                        Label <span className="text-destructive">*</span>
                                    </FieldLabel>
                                    <Input
                                        id="item-title"
                                        value={draft.title}
                                        onChange={(e) => set('title', e.target.value)}
                                        placeholder="Navigation label"
                                    />
                                </Field>

                                <Field>
                                    <FieldLabel htmlFor="item-url">URL</FieldLabel>
                                    <Input
                                        id="item-url"
                                        value={draft.url}
                                        onChange={(e) => set('url', e.target.value)}
                                        placeholder="https://example.com or /path"
                                    />
                                </Field>

                                <Field>
                                    <FieldLabel htmlFor="item-type">Type</FieldLabel>
                                    <NativeSelect
                                        id="item-type"
                                        value={draft.type}
                                        onChange={(e) => set('type', e.target.value)}
                                    >
                                        {Object.entries(itemTypes).map(([value, label]) => (
                                            <NativeSelectOption key={value} value={value}>
                                                {label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                </Field>

                                <Field orientation="horizontal">
                                    <Switch
                                        checked={draft.is_active}
                                        onCheckedChange={(checked) => set('is_active', checked)}
                                    />
                                    <div className="flex flex-col gap-1">
                                        <FieldLabel>Active</FieldLabel>
                                        <FieldDescription>Inactive items are hidden on the front end.</FieldDescription>
                                    </div>
                                </Field>
                            </AccordionContent>
                        </AccordionItem>

                        {/* Appearance */}
                        <AccordionItem value="appearance">
                            <AccordionTrigger>Appearance</AccordionTrigger>
                            <AccordionContent className="flex flex-col gap-4 !pt-2">
                                <Field>
                                    <FieldLabel htmlFor="item-icon">Icon Class</FieldLabel>
                                    <Input
                                        id="item-icon"
                                        value={draft.icon}
                                        onChange={(e) => set('icon', e.target.value)}
                                        placeholder="e.g. fa-home or bi-house"
                                    />
                                    <FieldDescription>CSS class(es) for an icon library.</FieldDescription>
                                </Field>

                                <Field>
                                    <FieldLabel htmlFor="item-css">CSS Classes</FieldLabel>
                                    <Input
                                        id="item-css"
                                        value={draft.css_classes}
                                        onChange={(e) => set('css_classes', e.target.value)}
                                        placeholder="Extra classes for the link element"
                                    />
                                </Field>
                            </AccordionContent>
                        </AccordionItem>

                        {/* Link Behavior */}
                        <AccordionItem value="behavior">
                            <AccordionTrigger>Link Behavior</AccordionTrigger>
                            <AccordionContent className="flex flex-col gap-4 !pt-2">
                                <Field>
                                    <FieldLabel htmlFor="item-target">Open In</FieldLabel>
                                    <NativeSelect
                                        id="item-target"
                                        value={draft.target}
                                        onChange={(e) => set('target', e.target.value)}
                                    >
                                        {Object.entries(itemTargets).map(([value, label]) => (
                                            <NativeSelectOption key={value} value={value}>
                                                {label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                </Field>

                                <Field>
                                    <FieldLabel htmlFor="item-link-title">Title Attribute</FieldLabel>
                                    <Input
                                        id="item-link-title"
                                        value={draft.link_title}
                                        onChange={(e) => set('link_title', e.target.value)}
                                        placeholder="Tooltip / title="
                                    />
                                </Field>

                                <Field>
                                    <FieldLabel htmlFor="item-link-rel">Rel Attribute</FieldLabel>
                                    <Input
                                        id="item-link-rel"
                                        value={draft.link_rel}
                                        onChange={(e) => set('link_rel', e.target.value)}
                                        placeholder="e.g. noopener nofollow"
                                    />
                                </Field>
                            </AccordionContent>
                        </AccordionItem>

                        {/* Advanced */}
                        <AccordionItem value="advanced">
                            <AccordionTrigger>Advanced</AccordionTrigger>
                            <AccordionContent className="flex flex-col gap-4 !pt-2">
                                <Field>
                                    <FieldLabel htmlFor="item-description">Description</FieldLabel>
                                    <Textarea
                                        id="item-description"
                                        value={draft.description}
                                        onChange={(e) => set('description', e.target.value)}
                                        rows={3}
                                        placeholder="Optional description shown in some themes."
                                    />
                                </Field>
                            </AccordionContent>
                        </AccordionItem>
                    </Accordion>
                </form>

                <SheetFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        disabled={!draft.title.trim()}
                        onClick={() => {
                            if (!draft.title.trim()) return;
                            onSave(draft);
                            onOpenChange(false);
                        }}
                    >
                        Apply Changes
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}

// ----------------------------------------------------------------
// Library search filter hook
// ----------------------------------------------------------------

function useLibraryFilter<T extends { title: string }>(items: T[]) {
    const [query, setQuery] = useState('');
    const filtered = useMemo(
        () =>
            query.trim()
                ? items.filter((i) => i.title.toLowerCase().includes(query.toLowerCase()))
                : items,
        [items, query],
    );
    return { query, setQuery, filtered };
}

// ----------------------------------------------------------------
// Item Library Panel
// ----------------------------------------------------------------

type ItemLibraryPanelProps = {
    menuId: number;
    pages: { id: number; title: string; slug: string }[];
    categories: { id: number; title: string; slug: string }[];
    tags: { id: number; title: string; slug: string }[];
    currentItems: DraftMenuItem[];
    onAddItem: (item: Omit<DraftMenuItem, 'id' | 'parent_id' | 'sort_order'>) => void;
};

function ItemLibraryPanel({ pages, categories, tags, currentItems, onAddItem }: ItemLibraryPanelProps) {
    const [customTitle, setCustomTitle] = useState('');
    const [customUrl, setCustomUrl] = useState('');

    const pagesFilter = useLibraryFilter(pages);
    const categoriesFilter = useLibraryFilter(categories);
    const tagsFilter = useLibraryFilter(tags);

    const handleAddCustom = (e: FormEvent) => {
        e.preventDefault();
        if (!customTitle.trim()) return;
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
        contentItem: { id: number; title: string; slug: string },
        type: 'page' | 'category' | 'tag',
    ) => {
        onAddItem({
            title: contentItem.title,
            url: '',
            type,
            target: '_self',
            icon: '',
            css_classes: '',
            link_title: '',
            link_rel: '',
            description: '',
            object_id: contentItem.id,
            is_active: true,
        });
    };

    const countInMenu = (objectId: number, type: string) =>
        currentItems.filter((i) => i.object_id === objectId && i.type === type).length;

    return (
        <Card className="flex flex-col">
            <CardHeader className="pb-3">
                <CardTitle className="text-base">Add Items</CardTitle>
                <CardDescription>Click items below to add them to the menu.</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-0 overflow-y-auto p-0">
                <Accordion type="multiple" defaultValue={['custom', 'pages']}>
                    {/* Custom Link */}
                    <AccordionItem value="custom" className="border-b px-4">
                        <AccordionTrigger className="text-sm font-medium">Custom Link</AccordionTrigger>
                        <AccordionContent>
                            <form noValidate onSubmit={handleAddCustom} className="flex flex-col gap-3">
                                <Field>
                                    <FieldLabel htmlFor="custom-url">URL</FieldLabel>
                                    <Input
                                        id="custom-url"
                                        value={customUrl}
                                        onChange={(e) => setCustomUrl(e.target.value)}
                                        placeholder="https:// or /path"
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="custom-title">
                                        Link Text <span className="text-destructive">*</span>
                                    </FieldLabel>
                                    <Input
                                        id="custom-title"
                                        value={customTitle}
                                        onChange={(e) => setCustomTitle(e.target.value)}
                                        placeholder="Navigation label"
                                    />
                                </Field>
                                <Button type="submit" className="w-full" disabled={!customTitle.trim()}>
                                    <PlusIcon className="size-4" />
                                    Add to Menu
                                </Button>
                            </form>
                        </AccordionContent>
                    </AccordionItem>

                    {/* Pages */}
                    {pages.length > 0 && (
                        <AccordionItem value="pages" className="border-b px-4">
                            <AccordionTrigger className="text-sm font-medium">Pages</AccordionTrigger>
                            <AccordionContent>
                                <div className="flex flex-col gap-2">
                                    <div className="relative">
                                        <SearchIcon className="absolute top-2.5 left-2.5 size-3.5 text-muted-foreground" />
                                        <Input
                                            className="h-8 pl-8 text-sm"
                                            placeholder="Search pages…"
                                            value={pagesFilter.query}
                                            onChange={(e) => pagesFilter.setQuery(e.target.value)}
                                        />
                                    </div>
                                    <div className="max-h-52 overflow-y-auto">
                                        {pagesFilter.filtered.length === 0 ? (
                                            <p className="py-4 text-center text-xs text-muted-foreground">No pages found.</p>
                                        ) : (
                                            pagesFilter.filtered.map((page) => {
                                                const count = countInMenu(page.id, 'page');
                                                return (
                                                    <button
                                                        key={page.id}
                                                        type="button"
                                                        onClick={() => addContentItem(page, 'page')}
                                                        className="flex w-full items-center gap-2 rounded px-1.5 py-1.5 text-left text-sm hover:bg-muted/60"
                                                    >
                                                        <FolderIcon className="size-3.5 shrink-0 text-blue-500" />
                                                        <span className="min-w-0 flex-1 truncate">{page.title}</span>
                                                        {count > 0 && (
                                                            <Badge variant="secondary" className="text-[10px]">
                                                                ×{count}
                                                            </Badge>
                                                        )}
                                                    </button>
                                                );
                                            })
                                        )}
                                    </div>
                                </div>
                            </AccordionContent>
                        </AccordionItem>
                    )}

                    {/* Categories */}
                    {categories.length > 0 && (
                        <AccordionItem value="categories" className="border-b px-4">
                            <AccordionTrigger className="text-sm font-medium">Categories</AccordionTrigger>
                            <AccordionContent>
                                <div className="flex flex-col gap-2">
                                    <div className="relative">
                                        <SearchIcon className="absolute top-2.5 left-2.5 size-3.5 text-muted-foreground" />
                                        <Input
                                            className="h-8 pl-8 text-sm"
                                            placeholder="Search categories…"
                                            value={categoriesFilter.query}
                                            onChange={(e) => categoriesFilter.setQuery(e.target.value)}
                                        />
                                    </div>
                                    <div className="max-h-52 overflow-y-auto">
                                        {categoriesFilter.filtered.length === 0 ? (
                                            <p className="py-4 text-center text-xs text-muted-foreground">No categories found.</p>
                                        ) : (
                                            categoriesFilter.filtered.map((cat) => {
                                                const count = countInMenu(cat.id, 'category');
                                                return (
                                                    <button
                                                        key={cat.id}
                                                        type="button"
                                                        onClick={() => addContentItem(cat, 'category')}
                                                        className="flex w-full items-center gap-2 rounded px-1.5 py-1.5 text-left text-sm hover:bg-muted/60"
                                                    >
                                                        <FolderIcon className="size-3.5 shrink-0 text-amber-500" />
                                                        <span className="min-w-0 flex-1 truncate">{cat.title}</span>
                                                        {count > 0 && (
                                                            <Badge variant="secondary" className="text-[10px]">
                                                                ×{count}
                                                            </Badge>
                                                        )}
                                                    </button>
                                                );
                                            })
                                        )}
                                    </div>
                                </div>
                            </AccordionContent>
                        </AccordionItem>
                    )}

                    {/* Tags */}
                    {tags.length > 0 && (
                        <AccordionItem value="tags" className="px-4">
                            <AccordionTrigger className="text-sm font-medium">Tags</AccordionTrigger>
                            <AccordionContent>
                                <div className="flex flex-col gap-2">
                                    <div className="relative">
                                        <SearchIcon className="absolute top-2.5 left-2.5 size-3.5 text-muted-foreground" />
                                        <Input
                                            className="h-8 pl-8 text-sm"
                                            placeholder="Search tags…"
                                            value={tagsFilter.query}
                                            onChange={(e) => tagsFilter.setQuery(e.target.value)}
                                        />
                                    </div>
                                    <div className="max-h-52 overflow-y-auto">
                                        {tagsFilter.filtered.length === 0 ? (
                                            <p className="py-4 text-center text-xs text-muted-foreground">No tags found.</p>
                                        ) : (
                                            tagsFilter.filtered.map((tag) => {
                                                const count = countInMenu(tag.id, 'tag');
                                                return (
                                                    <button
                                                        key={tag.id}
                                                        type="button"
                                                        onClick={() => addContentItem(tag, 'tag')}
                                                        className="flex w-full items-center gap-2 rounded px-1.5 py-1.5 text-left text-sm hover:bg-muted/60"
                                                    >
                                                        <TagIcon className="size-3.5 shrink-0 text-green-500" />
                                                        <span className="min-w-0 flex-1 truncate">{tag.title}</span>
                                                        {count > 0 && (
                                                            <Badge variant="secondary" className="text-[10px]">
                                                                ×{count}
                                                            </Badge>
                                                        )}
                                                    </button>
                                                );
                                            })
                                        )}
                                    </div>
                                </div>
                            </AccordionContent>
                        </AccordionItem>
                    )}
                </Accordion>
            </CardContent>
        </Card>
    );
}

// ----------------------------------------------------------------
// Main Page Component
// ----------------------------------------------------------------

export default function MenusEdit({
    menu,
    pages,
    categories,
    tags,
    itemTypes,
    itemTargets,
    locations,
    menuSettings,
    statusOptions: _statusOptions,
    locationOptions,
}: MenuEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Menus', href: route('cms.appearance.menus.index') },
        { title: menu.name, href: route('cms.appearance.menus.edit', { menu: menu.id }) },
    ];

    const maxDepth = (menuSettings?.max_depth as number | undefined) ?? 3;
    const nextTempId = useRef(-1);

    // ---- State ----
    const [items, setItems] = useState<DraftMenuItem[]>(() =>
        menu.all_items.map((i) => ({
            id: i.id,
            parent_id: i.parent_id,
            title: i.title,
            url: i.url,
            type: i.type,
            target: i.target,
            icon: i.icon,
            css_classes: i.css_classes,
            link_title: i.link_title,
            link_rel: i.link_rel,
            description: i.description,
            object_id: i.object_id,
            sort_order: i.sort_order,
            is_active: i.is_active,
        })),
    );

    const [deletedIds, setDeletedIds] = useState<number[]>([]);
    const [isDirty, setIsDirty] = useState(false);

    const [settings, setSettings] = useState<MenuSettings>({
        name: menu.name,
        location: menu.location ?? '',
        is_active: menu.is_active,
        description: menu.description ?? '',
    });

    // DnD state
    const [draggedId, setDraggedId] = useState<number | null>(null);
    const [dropTarget, setDropTarget] = useState<{ id: number; position: 'before' | 'after' } | null>(null);

    // Sheet state
    const [editingItem, setEditingItem] = useState<DraftMenuItem | null>(null);
    const [sheetOpen, setSheetOpen] = useState(false);

    // Save request
    const saveRequest = useHttp<SavePayload, SaveResponse>({
        settings,
        items: { new: [], updated: [], deleted: [], order: [] },
    });

    // ---- Dirty guard ----
    useEffect(() => {
        const handleBeforeUnload = (e: BeforeUnloadEvent) => {
            if (isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', handleBeforeUnload);
        return () => window.removeEventListener('beforeunload', handleBeforeUnload);
    }, [isDirty]);

    // ---- Computed ----
    const renderOrder = useMemo(() => buildRenderOrder(items, menu.id), [items, menu.id]);

    // ---- Settings helpers ----
    const updateSettings = useCallback(<K extends keyof MenuSettings>(key: K, value: MenuSettings[K]) => {
        setSettings((prev) => ({ ...prev, [key]: value }));
        setIsDirty(true);
    }, []);

    // ---- Item management ----
    const markDirty = useCallback(() => setIsDirty(true), []);

    const addItem = useCallback(
        (overrides: Omit<DraftMenuItem, 'id' | 'parent_id' | 'sort_order'>) => {
            const topLevelItems = items.filter((i) => i.parent_id === menu.id);
            const maxSortOrder = topLevelItems.reduce((max, i) => Math.max(max, i.sort_order), -1);
            const tempId = nextTempId.current--;
            const newItem: DraftMenuItem = {
                ...overrides,
                id: tempId,
                parent_id: menu.id,
                sort_order: maxSortOrder + 1,
            };
            setItems((prev) => [...prev, newItem]);
            setIsDirty(true);
        },
        [items, menu.id],
    );

    const deleteItem = useCallback(
        (itemId: number) => {
            const descendantIds = collectDescendantIds(items, itemId);
            const allToDelete = [itemId, ...descendantIds];

            // Only track server-side IDs for deletion
            const serverIds = allToDelete.filter((id) => id > 0);
            if (serverIds.length > 0) {
                setDeletedIds((prev) => [...prev, ...serverIds]);
            }

            setItems((prev) => {
                const remaining = prev.filter((i) => !allToDelete.includes(i.id));
                // Re-index siblings
                const parentId = prev.find((i) => i.id === itemId)?.parent_id ?? menu.id;
                const siblings = remaining
                    .filter((i) => i.parent_id === parentId)
                    .sort((a, b) => a.sort_order - b.sort_order);
                siblings.forEach((s, idx) => {
                    s.sort_order = idx;
                });
                return [...remaining];
            });
            setIsDirty(true);
        },
        [items, menu.id],
    );

    const updateItem = useCallback((updated: DraftMenuItem) => {
        setItems((prev) => prev.map((i) => (i.id === updated.id ? { ...updated } : i)));
        setIsDirty(true);
    }, []);

    // ---- DnD handlers ----
    const handleDragStart = useCallback((e: DragEvent<HTMLDivElement>, id: number) => {
        e.dataTransfer.effectAllowed = 'move';
        setDraggedId(id);
    }, []);

    const handleDragOver = useCallback(
        (e: DragEvent<HTMLDivElement>, id: number) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (!draggedId || draggedId === id) return;

            const rect = (e.currentTarget as HTMLDivElement).getBoundingClientRect();
            const midY = rect.top + rect.height / 2;
            const position: 'before' | 'after' = e.clientY < midY ? 'before' : 'after';

            setDropTarget((prev) => (prev?.id === id && prev.position === position ? prev : { id, position }));
        },
        [draggedId],
    );

    const handleDrop = useCallback(
        (e: DragEvent<HTMLDivElement>) => {
            e.preventDefault();
            if (!draggedId || !dropTarget) return;
            setItems((prev) => applyDrop(prev, draggedId, dropTarget.id, dropTarget.position));
            setIsDirty(true);
            setDraggedId(null);
            setDropTarget(null);
        },
        [draggedId, dropTarget],
    );

    const handleDragEnd = useCallback(() => {
        setDraggedId(null);
        setDropTarget(null);
    }, []);

    // ---- Keyboard reorder handlers ----
    const handleMoveUp = useCallback(
        (id: number) => {
            setItems((prev) => moveItemUp(prev, id));
            markDirty();
        },
        [markDirty],
    );

    const handleMoveDown = useCallback(
        (id: number) => {
            setItems((prev) => moveItemDown(prev, id));
            markDirty();
        },
        [markDirty],
    );

    const handleIndent = useCallback(
        (id: number) => {
            setItems((prev) => indentItem(prev, id, menu.id, maxDepth));
            markDirty();
        },
        [menu.id, maxDepth, markDirty],
    );

    const handleOutdent = useCallback(
        (id: number) => {
            setItems((prev) => outdentItem(prev, id, menu.id));
            markDirty();
        },
        [menu.id, markDirty],
    );

    // ---- Edit item ----
    const handleEditItem = useCallback((item: DraftMenuItem) => {
        setEditingItem(item);
        setSheetOpen(true);
    }, []);

    // ---- Save ----
    const handleSave = useCallback(async () => {
        const currentItems = items;
        const payload: SavePayload = {
            settings,
            items: {
                new: currentItems.filter((i) => i.id < 0),
                updated: currentItems.filter((i) => i.id > 0),
                deleted: deletedIds.map((id) => ({ id })),
                order: currentItems.map((i) => ({
                    id: i.id,
                    parent_id: i.parent_id,
                    sort_order: i.sort_order,
                })),
            },
        };

        try {
            saveRequest.transform(() => payload);
            const response = await saveRequest.post(
                route('cms.appearance.menus.save-all', { menu: menu.id }),
            );

            if (!response.success) {
                throw new Error(response.message || 'Save failed.');
            }

            // Remap temp IDs to real IDs
            if (response.newItemIds && Object.keys(response.newItemIds).length > 0) {
                const idMap = new Map<number, number>();
                for (const [tempIdStr, realId] of Object.entries(response.newItemIds)) {
                    idMap.set(parseInt(tempIdStr, 10), realId);
                }

                setItems((prev) =>
                    prev.map((item) => {
                        const newId = item.id < 0 ? (idMap.get(item.id) ?? item.id) : item.id;
                        const newParentId =
                            item.parent_id < 0 ? (idMap.get(item.parent_id) ?? item.parent_id) : item.parent_id;
                        return { ...item, id: newId, parent_id: newParentId };
                    }),
                );
            }

            setDeletedIds([]);
            setIsDirty(false);

            showAppToast({
                variant: 'success',
                title: 'Menu saved',
                description: response.message,
            });
        } catch (error) {
            const message =
                error instanceof Error ? error.message : 'An unexpected error occurred.';
            showAppToast({
                variant: 'error',
                title: 'Save failed',
                description: message,
            });
        }
    }, [items, settings, deletedIds, menu.id, saveRequest]);

    // ---- Keyboard shortcut Ctrl+S ----
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's' && isDirty && !saveRequest.processing) {
                e.preventDefault();
                handleSave();
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [handleSave, isDirty, saveRequest.processing]);

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit Menu: ${menu.name}`}
            description="Build your navigation menu structure by adding and reordering items."
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('cms.appearance.menus.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back to Menus
                        </Link>
                    </Button>
                    <Button
                        onClick={handleSave}
                        disabled={!isDirty || saveRequest.processing}
                    >
                        {saveRequest.processing ? <Spinner /> : <SaveIcon className="size-4" />}
                        Save Menu
                    </Button>
                </div>
            }
        >
            {/* Settings Card */}
            <Card className="mb-6">
                <CardHeader className="pb-4">
                    <CardTitle className="text-base">Menu Settings</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Field className="lg:col-span-2">
                            <FieldLabel htmlFor="menu-name">
                                Name <span className="text-destructive">*</span>
                            </FieldLabel>
                            <Input
                                id="menu-name"
                                value={settings.name}
                                onChange={(e) => updateSettings('name', e.target.value)}
                                placeholder="Menu name"
                            />
                        </Field>

                        <Field>
                            <FieldLabel htmlFor="menu-location">Location</FieldLabel>
                            <NativeSelect
                                id="menu-location"
                                value={settings.location}
                                onChange={(e) => updateSettings('location', e.target.value)}
                            >
                                <NativeSelectOption value="">— None —</NativeSelectOption>
                                {locationOptions.map((opt) => (
                                    <NativeSelectOption key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        </Field>

                        <Field orientation="horizontal" className="items-start pt-6">
                            <Switch
                                checked={settings.is_active}
                                onCheckedChange={(checked) => updateSettings('is_active', checked)}
                            />
                            <div className="flex flex-col gap-0.5">
                                <FieldLabel>Active</FieldLabel>
                                <FieldDescription>Visible on the front end.</FieldDescription>
                            </div>
                        </Field>

                        <Field className="sm:col-span-2 lg:col-span-4">
                            <FieldLabel htmlFor="menu-description">Description</FieldLabel>
                            <Textarea
                                id="menu-description"
                                value={settings.description}
                                onChange={(e) => updateSettings('description', e.target.value)}
                                rows={2}
                                placeholder="Optional notes about this menu…"
                            />
                        </Field>
                    </div>
                </CardContent>
            </Card>

            {/* Builder + Library */}
            <div className="grid gap-6 xl:grid-cols-[1fr_380px]">
                {/* Left: Menu Structure */}
                <Card className="flex min-h-[400px] flex-col">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">Menu Structure</CardTitle>
                        <CardDescription>
                            Drag items to reorder. Use the arrow buttons to adjust hierarchy.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-1 flex-col gap-1 p-3">
                        {renderOrder.length === 0 ? (
                            <div className="flex flex-1 flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-16 text-center text-muted-foreground">
                                <LinkIcon className="size-8 opacity-40" />
                                <p className="text-sm font-medium">No items yet</p>
                                <p className="text-xs">Add items from the panel on the right.</p>
                            </div>
                        ) : (
                            renderOrder.map(({ item, depth }) => (
                                <MenuItemRow
                                    key={item.id}
                                    renderItem={{ item, depth }}
                                    allItems={items}
                                    menuId={menu.id}
                                    maxDepth={maxDepth}
                                    isDragging={draggedId === item.id}
                                    isDraggedOver={
                                        dropTarget?.id === item.id ? dropTarget.position : null
                                    }
                                    onDragStart={handleDragStart}
                                    onDragOver={handleDragOver}
                                    onDrop={handleDrop}
                                    onDragEnd={handleDragEnd}
                                    onMoveUp={handleMoveUp}
                                    onMoveDown={handleMoveDown}
                                    onIndent={handleIndent}
                                    onOutdent={handleOutdent}
                                    onEdit={handleEditItem}
                                    onDelete={deleteItem}
                                />
                            ))
                        )}
                    </CardContent>
                </Card>

                {/* Right: Item Library */}
                <ItemLibraryPanel
                    menuId={menu.id}
                    pages={pages}
                    categories={categories}
                    tags={tags}
                    currentItems={items}
                    onAddItem={addItem}
                />
            </div>

            {/* Floating Save Bar */}
            {isDirty && (
                <div className="fixed right-0 bottom-0 left-0 z-40 border-t bg-background/95 px-4 py-3 shadow-lg backdrop-blur supports-backdrop-filter:bg-background/80">
                    <div className="mx-auto flex max-w-screen-2xl items-center justify-between gap-4">
                        <p className="text-sm text-muted-foreground">
                            You have unsaved changes.{' '}
                            <kbd className="hidden rounded border px-1 text-xs sm:inline">Ctrl+S</kbd>
                        </p>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setItems(
                                        menu.all_items.map((i) => ({ ...i })),
                                    );
                                    setDeletedIds([]);
                                    setSettings({
                                        name: menu.name,
                                        location: menu.location ?? '',
                                        is_active: menu.is_active,
                                        description: menu.description ?? '',
                                    });
                                    setIsDirty(false);
                                }}
                                disabled={saveRequest.processing}
                            >
                                Discard Changes
                            </Button>
                            <Button
                                size="sm"
                                onClick={handleSave}
                                disabled={saveRequest.processing}
                            >
                                {saveRequest.processing ? <Spinner /> : <SaveIcon className="size-4" />}
                                Save Menu
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Item Edit Sheet */}
            <ItemEditSheet
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                item={editingItem}
                itemTypes={itemTypes}
                itemTargets={itemTargets}
                onSave={updateItem}
            />
        </AppLayout>
    );
}
