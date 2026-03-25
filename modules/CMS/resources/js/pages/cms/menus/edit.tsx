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
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { DragEvent, FormEvent } from 'react';
import { showAppToast } from '@/components/forms/form-success-toast';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Kbd, KbdGroup } from '@/components/ui/kbd';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { ScrollArea } from '@/components/ui/scroll-area';
import { SearchInput } from '@/components/ui/search-input';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { CmsSaveFooter } from '../../../components/shared/cms-save-footer';
import type { MenuEditPageProps } from '../../../types/cms';

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
    details?: string;
    errors?: Record<string, string[]>;
    newItemIds: Record<string, number>;
};

// ----------------------------------------------------------------
// Utility functions
// ----------------------------------------------------------------

function buildRenderOrder(
    items: DraftMenuItem[],
    parentId: number,
    depth = 0,
): RenderItem[] {
    return items
        .filter((i) => i.parent_id === parentId)
        .sort((a, b) => a.sort_order - b.sort_order)
        .flatMap((item) => [
            { item, depth },
            ...buildRenderOrder(items, item.id, depth + 1),
        ]);
}

function getItemDepth(
    items: DraftMenuItem[],
    itemId: number,
    menuId: number,
): number {
    const item = items.find((i) => i.id === itemId);
    if (!item || item.parent_id === menuId) return 0;
    return 1 + getItemDepth(items, item.parent_id, menuId);
}

function getSubtreeMaxDepth(
    items: DraftMenuItem[],
    itemId: number,
    menuId: number,
): number {
    const baseDepth = getItemDepth(items, itemId, menuId);
    const children = items.filter((i) => i.parent_id === itemId);
    if (children.length === 0) return baseDepth;
    return Math.max(
        ...children.map((c) => getSubtreeMaxDepth(items, c.id, menuId)),
    );
}

function isDescendant(
    items: DraftMenuItem[],
    ancestorId: number,
    targetId: number,
): boolean {
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
    const insertAt =
        position === 'before' ? Math.max(0, targetIdx) : targetIdx + 1;
    siblings.splice(insertAt, 0, dragged);
    siblings.forEach((item, idx) => {
        item.sort_order = idx;
    });

    return [...items];
}

function moveItemUp(
    prevItems: DraftMenuItem[],
    itemId: number,
): DraftMenuItem[] {
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

function moveItemDown(
    prevItems: DraftMenuItem[],
    itemId: number,
): DraftMenuItem[] {
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
    const subtreeHeight =
        getSubtreeMaxDepth(items, itemId, menuId) -
        getItemDepth(items, itemId, menuId);
    if (newParentDepth + 1 + subtreeHeight >= maxDepth) return prevItems;

    item.parent_id = newParent.id;
    siblings
        .filter((s) => s.id !== itemId)
        .forEach((s, i) => {
            s.sort_order = i;
        });

    const newParentChildren = items
        .filter((i) => i.parent_id === newParent.id && i.id !== itemId)
        .sort((a, b) => a.sort_order - b.sort_order);
    item.sort_order = newParentChildren.length;

    return [...items];
}

function outdentItem(
    prevItems: DraftMenuItem[],
    itemId: number,
    menuId: number,
): DraftMenuItem[] {
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

function collectDescendantIds(
    items: DraftMenuItem[],
    itemId: number,
): number[] {
    const children = items.filter((i) => i.parent_id === itemId);
    return children.flatMap((c) => [
        c.id,
        ...collectDescendantIds(items, c.id),
    ]);
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
            return (
                <SearchIcon className="size-3.5 shrink-0 text-muted-foreground" />
            );
        default:
            return (
                <LinkIcon className="size-3.5 shrink-0 text-muted-foreground" />
            );
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
    onDragStart: (e: DragEvent<HTMLButtonElement>, id: number) => void;
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
    const prevSiblingDepth = prevSibling
        ? getItemDepth(allItems, prevSibling.id, menuId)
        : 0;
    const subtreeHeight =
        getSubtreeMaxDepth(allItems, item.id, menuId) -
        getItemDepth(allItems, item.id, menuId);
    const canIndent =
        hasPreviousSibling && prevSiblingDepth + 1 + subtreeHeight < maxDepth;

    return (
        <div className="relative" style={{ paddingLeft: `${depth * 24}px` }}>
            {isDraggedOver === 'before' && (
                <div
                    className="absolute top-0 right-0 left-0 h-0.5 rounded bg-primary"
                    style={{ zIndex: 10 }}
                />
            )}
            {isDraggedOver === 'after' && (
                <div
                    className="absolute right-0 bottom-0 left-0 h-0.5 rounded bg-primary"
                    style={{ zIndex: 10 }}
                />
            )}
            <div
                className={cn(
                    'group rounded-xl border bg-card p-4 shadow-xs transition-all',
                    isDragging ? 'scale-[0.99] opacity-45' : 'opacity-100',
                    isDraggedOver ? 'bg-muted/30' : '',
                )}
                onDragOver={(e) => onDragOver(e, item.id)}
                onDrop={(e) => onDrop(e)}
            >
                <div className="flex items-start gap-3">
                    <div className="flex items-center gap-2 self-stretch">
                        <button
                            type="button"
                            draggable
                            onDragStart={(e) => onDragStart(e, item.id)}
                            onDragEnd={onDragEnd}
                            className="mt-0.5 rounded-lg border bg-muted/40 p-2 text-muted-foreground transition hover:bg-accent hover:text-foreground active:cursor-grabbing"
                            aria-label="Drag to reorder"
                            title="Drag to reorder"
                        >
                            <GripVerticalIcon className="size-4" />
                        </button>
                    </div>

                    <div
                        className="min-w-0 flex-1"
                    >
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="truncate text-sm font-medium">
                                {item.title}
                            </p>
                            <Badge variant="outline">
                                {item.type}
                            </Badge>
                            {!item.is_active ? (
                                <Badge variant="secondary">Inactive</Badge>
                            ) : null}
                        </div>
                        {item.url ? (
                            <p className="mt-1 truncate text-sm text-muted-foreground">
                                {item.url}
                            </p>
                        ) : null}
                    </div>

                    <div className="flex shrink-0 items-center gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="sm:hidden"
                            disabled={!canMoveUp}
                            onClick={() => onMoveUp(item.id)}
                            aria-label="Move up"
                        >
                            <ArrowUpIcon />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="sm:hidden"
                            disabled={!canMoveDown}
                            onClick={() => onMoveDown(item.id)}
                            aria-label="Move down"
                        >
                            <ArrowDownIcon />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            disabled={!canOutdent}
                            onClick={() => onOutdent(item.id)}
                            aria-label="Outdent"
                            title="Outdent"
                        >
                            <ArrowLeftIcon />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            disabled={!canIndent}
                            onClick={() => onIndent(item.id)}
                            aria-label="Indent"
                            title="Indent"
                        >
                            <ArrowRightIcon />
                        </Button>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => onEdit(item)}
                            aria-label="Edit item"
                        >
                            <PencilIcon />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-destructive hover:text-destructive"
                            onClick={() => onDelete(item.id)}
                            aria-label="Delete item"
                        >
                            <Trash2Icon />
                        </Button>
                    </div>
                </div>
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

function ItemEditSheet({
    open,
    onOpenChange,
    item,
    itemTypes,
    itemTargets,
    onSave,
}: ItemEditSheetProps) {
    const [draft, setDraft] = useState<DraftMenuItem | null>(null);

    useEffect(() => {
        if (item) setDraft({ ...item });
    }, [item]);

    if (!draft) return null;

    const isInternalLinkedItem = ['page', 'category', 'tag'].includes(
        draft.type,
    );

    const set = <K extends keyof DraftMenuItem>(
        key: K,
        value: DraftMenuItem[K],
    ) => {
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
            <SheetContent
                side="right"
                className="flex w-full flex-col sm:max-w-md"
            >
                <SheetHeader className="px-6 pt-6 pb-4">
                    <SheetTitle>Edit Menu Item</SheetTitle>
                    <SheetDescription>
                        Update the properties of this navigation item.
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-6 py-5">
                    <FieldGroup>
                        <form
                            noValidate
                            onSubmit={handleSave}
                        >
                            <Accordion
                                type="multiple"
                                defaultValue={['basic', 'appearance', 'behavior']}
                            >
                                {/* Basic */}
                                <AccordionItem value="basic">
                                    <AccordionTrigger>Basic</AccordionTrigger>
                                    <AccordionContent className="flex flex-col gap-4 !pt-2">
                                        <Field>
                                            <FieldLabel htmlFor="item-title">
                                                Label{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </FieldLabel>
                                            <Input
                                                id="item-title"
                                                value={draft.title}
                                                onChange={(e) =>
                                                    set('title', e.target.value)
                                                }
                                                placeholder="Navigation label"
                                            />
                                        </Field>

                                        <Field>
                                            <FieldLabel htmlFor="item-url">
                                                URL
                                            </FieldLabel>
                                            <Input
                                                id="item-url"
                                                value={draft.url}
                                                onChange={(e) =>
                                                    set('url', e.target.value)
                                                }
                                                placeholder="https://example.com or /path"
                                                readOnly={isInternalLinkedItem}
                                                disabled={isInternalLinkedItem}
                                            />
                                            {isInternalLinkedItem ? (
                                                <FieldDescription>
                                                    This URL is managed by the
                                                    linked content and updates
                                                    automatically when its slug
                                                    changes.
                                                </FieldDescription>
                                            ) : null}
                                        </Field>

                                        <Field>
                                            <FieldLabel htmlFor="item-type">
                                                Type
                                            </FieldLabel>
                                            <NativeSelect
                                                id="item-type"
                                                value={draft.type}
                                                onChange={(e) =>
                                                    set('type', e.target.value)
                                                }
                                            >
                                                {Object.entries(itemTypes).map(
                                                    ([value, label]) => (
                                                        <NativeSelectOption
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {label}
                                                        </NativeSelectOption>
                                                    ),
                                                )}
                                            </NativeSelect>
                                        </Field>

                                        <Field orientation="horizontal">
                                            <Switch
                                                checked={draft.is_active}
                                                onCheckedChange={(checked) =>
                                                    set('is_active', checked)
                                                }
                                            />
                                            <div className="flex flex-col gap-1">
                                                <FieldLabel>Active</FieldLabel>
                                                <FieldDescription>
                                                    Inactive items are hidden on the
                                                    front end.
                                                </FieldDescription>
                                            </div>
                                        </Field>
                                    </AccordionContent>
                                </AccordionItem>

                                {/* Appearance */}
                                <AccordionItem value="appearance">
                                    <AccordionTrigger>Appearance</AccordionTrigger>
                                    <AccordionContent className="flex flex-col gap-4 !pt-2">
                                        <Field>
                                            <FieldLabel htmlFor="item-icon">
                                                Icon Class
                                            </FieldLabel>
                                            <Input
                                                id="item-icon"
                                                value={draft.icon}
                                                onChange={(e) =>
                                                    set('icon', e.target.value)
                                                }
                                                placeholder="e.g. fa-home or bi-house"
                                            />
                                            <FieldDescription>
                                                CSS class(es) for an icon library.
                                            </FieldDescription>
                                        </Field>

                                        <Field>
                                            <FieldLabel htmlFor="item-css">
                                                CSS Classes
                                            </FieldLabel>
                                            <Input
                                                id="item-css"
                                                value={draft.css_classes}
                                                onChange={(e) =>
                                                    set('css_classes', e.target.value)
                                                }
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
                                            <FieldLabel htmlFor="item-target">
                                                Open In
                                            </FieldLabel>
                                            <NativeSelect
                                                id="item-target"
                                                value={draft.target}
                                                onChange={(e) =>
                                                    set('target', e.target.value)
                                                }
                                            >
                                                {Object.entries(itemTargets).map(
                                                    ([value, label]) => (
                                                        <NativeSelectOption
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {label}
                                                        </NativeSelectOption>
                                                    ),
                                                )}
                                            </NativeSelect>
                                        </Field>

                                        <Field>
                                            <FieldLabel htmlFor="item-link-title">
                                                Title Attribute
                                            </FieldLabel>
                                            <Input
                                                id="item-link-title"
                                                value={draft.link_title}
                                                onChange={(e) =>
                                                    set('link_title', e.target.value)
                                                }
                                                placeholder="Tooltip / title="
                                            />
                                        </Field>

                                        <Field>
                                            <FieldLabel htmlFor="item-link-rel">
                                                Rel Attribute
                                            </FieldLabel>
                                            <Input
                                                id="item-link-rel"
                                                value={draft.link_rel}
                                                onChange={(e) =>
                                                    set('link_rel', e.target.value)
                                                }
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
                                            <FieldLabel htmlFor="item-description">
                                                Description
                                            </FieldLabel>
                                            <Textarea
                                                id="item-description"
                                                value={draft.description}
                                                onChange={(e) =>
                                                    set('description', e.target.value)
                                                }
                                                rows={3}
                                                placeholder="Optional description shown in some themes."
                                            />
                                        </Field>
                                    </AccordionContent>
                                </AccordionItem>
                            </Accordion>
                        </form>
                    </FieldGroup>
                </div>

                <SheetFooter className="px-6 pt-4 pb-6">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
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
                        <SaveIcon data-icon="inline-start" />
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
                ? items.filter((i) =>
                    i.title.toLowerCase().includes(query.toLowerCase()),
                )
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
    onAddItem: (
        item: Omit<DraftMenuItem, 'id' | 'parent_id' | 'sort_order'>,
    ) => void;
};

function ItemLibraryPanel({
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
        currentItems.filter((i) => i.object_id === objectId && i.type === type)
            .length;

    const totalAvailable = pages.length + categories.length + tags.length;

    return (
        <Card className="sticky top-4 flex max-h-[calc(100vh-7rem)] min-h-0 flex-col overflow-hidden">
            <CardHeader className="pb-0">
                <div className="flex items-center gap-2">
                    <CardTitle className="text-base">Add Items</CardTitle>
                    <Badge variant="secondary">
                        {totalAvailable}
                    </Badge>
                </div>
            </CardHeader>

            <CardContent className="flex min-h-0 flex-1 flex-col gap-4 p-4 pt-3">
                <ScrollArea className="min-h-0 flex-1">
                    <div className="flex flex-col gap-4 pr-1">
                        <Accordion type="multiple" defaultValue={['custom', 'pages']} className="flex flex-col gap-3">
                            {/* Custom Link */}
                            <AccordionItem value="custom" className="overflow-hidden rounded-xl border">
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
                                                onChange={(e) =>
                                                    setCustomUrl(e.target.value)
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
                                                onChange={(e) =>
                                                    setCustomTitle(e.target.value)
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

                            {/* Pages */}
                            {pages.length > 0 && (
                                <AccordionItem value="pages" className="overflow-hidden rounded-xl border">
                                    <AccordionTrigger className="px-4 py-3 text-left text-sm font-medium hover:bg-muted/20 hover:no-underline">
                                        <div className="flex min-w-0 flex-1 items-center gap-3">
                                            <div className="min-w-0 flex-1"><p>Pages</p></div>
                                            <Badge variant="outline">{pages.length}</Badge>
                                        </div>
                                    </AccordionTrigger>
                                    <AccordionContent>
                                        <div className="flex flex-col gap-3 p-4">
                                            <SearchInput
                                                value={pagesFilter.query}
                                                onChange={pagesFilter.setQuery}
                                                size="comfortable"
                                                placeholder="Search pages…"
                                                containerClassName="w-full"
                                            />
                                            <div className="flex flex-col gap-2">
                                                {pagesFilter.filtered.length === 0 ? (
                                                    <p className="py-4 text-center text-xs text-muted-foreground">
                                                        No pages found.
                                                    </p>
                                                ) : (
                                                    pagesFilter.filtered.map((page) => {
                                                        const count = countInMenu(
                                                            page.id,
                                                            'page',
                                                        );
                                                        return (
                                                            <button
                                                                key={page.id}
                                                                type="button"
                                                                onClick={() =>
                                                                    addContentItem(
                                                                        page,
                                                                        'page',
                                                                    )
                                                                }
                                                                className="group w-full rounded-xl border bg-background p-4 text-left transition hover:border-primary/40 hover:bg-accent/30"
                                                            >
                                                                <div className="flex items-start gap-3">
                                                                    <div className="flex-1 space-y-1">
                                                                        <div className="flex items-center gap-2">
                                                                            <p className="font-medium">{page.title}</p>
                                                                            {count > 0 ? (
                                                                                <Badge variant="secondary">Used {count}×</Badge>
                                                                            ) : null}
                                                                        </div>
                                                                        <p className="line-clamp-2 text-sm text-muted-foreground">{page.slug}</p>
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
                            )}

                            {/* Categories */}
                            {categories.length > 0 && (
                                <AccordionItem
                                    value="categories"
                                    className="overflow-hidden rounded-xl border"
                                >
                                    <AccordionTrigger className="px-4 py-3 text-left text-sm font-medium hover:bg-muted/20 hover:no-underline">
                                        <div className="flex min-w-0 flex-1 items-center gap-3">
                                            <div className="min-w-0 flex-1"><p>Categories</p></div>
                                            <Badge variant="outline">{categories.length}</Badge>
                                        </div>
                                    </AccordionTrigger>
                                    <AccordionContent>
                                        <div className="flex flex-col gap-3 p-4">
                                            <SearchInput
                                                value={categoriesFilter.query}
                                                onChange={categoriesFilter.setQuery}
                                                size="comfortable"
                                                placeholder="Search categories…"
                                                containerClassName="w-full"
                                            />
                                            <div className="flex flex-col gap-2">
                                                {categoriesFilter.filtered.length ===
                                                    0 ? (
                                                    <p className="py-4 text-center text-xs text-muted-foreground">
                                                        No categories found.
                                                    </p>
                                                ) : (
                                                    categoriesFilter.filtered.map(
                                                        (cat) => {
                                                            const count = countInMenu(
                                                                cat.id,
                                                                'category',
                                                            );
                                                            return (
                                                                <button
                                                                    key={cat.id}
                                                                    type="button"
                                                                    onClick={() =>
                                                                        addContentItem(
                                                                            cat,
                                                                            'category',
                                                                        )
                                                                    }
                                                                    className="group w-full rounded-xl border bg-background p-4 text-left transition hover:border-primary/40 hover:bg-accent/30"
                                                                >
                                                                    <div className="flex items-start gap-3">
                                                                        <div className="flex-1 space-y-1">
                                                                            <div className="flex items-center gap-2">
                                                                                <p className="font-medium">{cat.title}</p>
                                                                                {count > 0 ? (
                                                                                    <Badge variant="secondary">Used {count}×</Badge>
                                                                                ) : null}
                                                                            </div>
                                                                            <p className="line-clamp-2 text-sm text-muted-foreground">{cat.slug}</p>
                                                                        </div>
                                                                        <div className="rounded-lg border bg-muted/40 p-2 text-primary transition group-hover:border-primary/40 group-hover:bg-primary group-hover:text-primary-foreground">
                                                                            <PlusIcon className="size-4" />
                                                                        </div>
                                                                    </div>
                                                                </button>
                                                            );
                                                        },
                                                    )
                                                )}
                                            </div>
                                        </div>
                                    </AccordionContent>
                                </AccordionItem>
                            )}

                            {/* Tags */}
                            {tags.length > 0 && (
                                <AccordionItem value="tags" className="overflow-hidden rounded-xl border">
                                    <AccordionTrigger className="px-4 py-3 text-left text-sm font-medium hover:bg-muted/20 hover:no-underline">
                                        <div className="flex min-w-0 flex-1 items-center gap-3">
                                            <div className="min-w-0 flex-1"><p>Tags</p></div>
                                            <Badge variant="outline">{tags.length}</Badge>
                                        </div>
                                    </AccordionTrigger>
                                    <AccordionContent>
                                        <div className="flex flex-col gap-3 p-4">
                                            <SearchInput
                                                value={tagsFilter.query}
                                                onChange={tagsFilter.setQuery}
                                                size="comfortable"
                                                placeholder="Search tags…"
                                                containerClassName="w-full"
                                            />
                                            <div className="flex flex-col gap-2">
                                                {tagsFilter.filtered.length === 0 ? (
                                                    <p className="py-4 text-center text-xs text-muted-foreground">
                                                        No tags found.
                                                    </p>
                                                ) : (
                                                    tagsFilter.filtered.map((tag) => {
                                                        const count = countInMenu(
                                                            tag.id,
                                                            'tag',
                                                        );
                                                        return (
                                                            <button
                                                                key={tag.id}
                                                                type="button"
                                                                onClick={() =>
                                                                    addContentItem(
                                                                        tag,
                                                                        'tag',
                                                                    )
                                                                }
                                                                className="group w-full rounded-xl border bg-background p-4 text-left transition hover:border-primary/40 hover:bg-accent/30"
                                                            >
                                                                <div className="flex items-start gap-3">
                                                                    <div className="flex-1 space-y-1">
                                                                        <div className="flex items-center gap-2">
                                                                            <p className="font-medium">{tag.title}</p>
                                                                            {count > 0 ? (
                                                                                <Badge variant="secondary">Used {count}×</Badge>
                                                                            ) : null}
                                                                        </div>
                                                                        <p className="line-clamp-2 text-sm text-muted-foreground">{tag.slug}</p>
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
                            )}
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
        {
            title: menu.name,
            href: route('cms.appearance.menus.edit', { menu: menu.id }),
        },
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
    const [dropTarget, setDropTarget] = useState<{
        id: number;
        position: 'before' | 'after';
    } | null>(null);

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
        return () =>
            window.removeEventListener('beforeunload', handleBeforeUnload);
    }, [isDirty]);

    // ---- Computed ----
    const renderOrder = useMemo(
        () => buildRenderOrder(items, menu.id),
        [items, menu.id],
    );

    // ---- Settings helpers ----
    const updateSettings = useCallback(
        <K extends keyof MenuSettings>(key: K, value: MenuSettings[K]) => {
            setSettings((prev) => ({ ...prev, [key]: value }));
            setIsDirty(true);
        },
        [],
    );

    // ---- Item management ----
    const markDirty = useCallback(() => setIsDirty(true), []);

    const addItem = useCallback(
        (overrides: Omit<DraftMenuItem, 'id' | 'parent_id' | 'sort_order'>) => {
            const topLevelItems = items.filter((i) => i.parent_id === menu.id);
            const maxSortOrder = topLevelItems.reduce(
                (max, i) => Math.max(max, i.sort_order),
                -1,
            );
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
                const remaining = prev.filter(
                    (i) => !allToDelete.includes(i.id),
                );
                // Re-index siblings
                const parentId =
                    prev.find((i) => i.id === itemId)?.parent_id ?? menu.id;
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
        setItems((prev) =>
            prev.map((i) => (i.id === updated.id ? { ...updated } : i)),
        );
        setIsDirty(true);
    }, []);

    // ---- DnD handlers ----
    const handleDragStart = useCallback(
        (e: DragEvent<HTMLButtonElement>, id: number) => {
            e.dataTransfer.effectAllowed = 'move';
            setDraggedId(id);
        },
        [],
    );

    const handleDragOver = useCallback(
        (e: DragEvent<HTMLDivElement>, id: number) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (!draggedId || draggedId === id) return;

            const rect = (
                e.currentTarget as HTMLDivElement
            ).getBoundingClientRect();
            const midY = rect.top + rect.height / 2;
            const position: 'before' | 'after' =
                e.clientY < midY ? 'before' : 'after';

            setDropTarget((prev) =>
                prev?.id === id && prev.position === position
                    ? prev
                    : { id, position },
            );
        },
        [draggedId],
    );

    const handleDrop = useCallback(
        (e: DragEvent<HTMLDivElement>) => {
            e.preventDefault();
            if (!draggedId || !dropTarget) return;
            setItems((prev) =>
                applyDrop(prev, draggedId, dropTarget.id, dropTarget.position),
            );
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
                throw new Error(response.details || response.message || 'Save failed.');
            }

            // Remap temp IDs to real IDs
            if (
                response.newItemIds &&
                Object.keys(response.newItemIds).length > 0
            ) {
                const idMap = new Map<number, number>();
                for (const [tempIdStr, realId] of Object.entries(
                    response.newItemIds,
                )) {
                    idMap.set(parseInt(tempIdStr, 10), realId);
                }

                setItems((prev) =>
                    prev.map((item) => {
                        const newId =
                            item.id < 0
                                ? (idMap.get(item.id) ?? item.id)
                                : item.id;
                        const newParentId =
                            item.parent_id < 0
                                ? (idMap.get(item.parent_id) ?? item.parent_id)
                                : item.parent_id;
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
                error instanceof Error
                    ? error.message
                    : 'An unexpected error occurred.';
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
            if (
                (e.ctrlKey || e.metaKey) &&
                e.key === 's' &&
                isDirty &&
                !saveRequest.processing
            ) {
                e.preventDefault();
                handleSave();
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [handleSave, isDirty, saveRequest.processing]);

    const handleDiscardChanges = useCallback(() => {
        setItems(menu.all_items.map((i) => ({ ...i })));
        setDeletedIds([]);
        setSettings({
            name: menu.name,
            location: menu.location ?? '',
            is_active: menu.is_active,
            description: menu.description ?? '',
        });
        setIsDirty(false);
        setSheetOpen(false);
        setEditingItem(null);
    }, [menu]);

    const isSaving = saveRequest.processing;
    const showUnsavedChangesStatus = isDirty && !isSaving;
    const footerStatusText = isSaving
        ? 'Saving changes...'
        : showUnsavedChangesStatus
            ? 'You have unsaved menu changes.'
            : 'All changes saved.';

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit Menu: ${menu.name}`}
            description="Build your navigation menu structure by adding and reordering items."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('cms.appearance.menus.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <div className="flex flex-1 flex-col gap-6 pb-20">
                {/* Settings Card */}
                <Card>
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
                                    onChange={(e) =>
                                        updateSettings('name', e.target.value)
                                    }
                                    placeholder="Menu name"
                                />
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="menu-location">
                                    Location
                                </FieldLabel>
                                <NativeSelect
                                    id="menu-location"
                                    value={settings.location}
                                    onChange={(e) =>
                                        updateSettings('location', e.target.value)
                                    }
                                >
                                    <NativeSelectOption value="">
                                        — None —
                                    </NativeSelectOption>
                                    {locationOptions.map((opt) => (
                                        <NativeSelectOption
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                            </Field>

                            <Field
                                orientation="horizontal"
                                className="items-start pt-6"
                            >
                                <Switch
                                    checked={settings.is_active}
                                    onCheckedChange={(checked) =>
                                        updateSettings('is_active', checked)
                                    }
                                />
                                <div className="flex flex-col gap-0.5">
                                    <FieldLabel>Active</FieldLabel>
                                    <FieldDescription>
                                        Visible on the front end.
                                    </FieldDescription>
                                </div>
                            </Field>

                        </div>
                    </CardContent>
                </Card>

                {/* Builder + Library */}
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    {/* Left: Menu Structure */}
                    <Card className="flex min-h-[540px] flex-col">
                        <CardHeader className="pb-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <CardTitle className="text-base">
                                    Menu Structure
                                </CardTitle>
                                <Badge variant="outline">
                                    {items.length}{' '}
                                    {items.length === 1 ? 'item' : 'items'}
                                </Badge>
                                {isDirty ? (
                                    <Badge variant="warning">
                                        Unsaved changes
                                    </Badge>
                                ) : null}
                            </div>
                            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                <span>
                                    Drag items to reorder. Use the arrow
                                    buttons to adjust hierarchy.
                                </span>
                                <KbdGroup>
                                    <Kbd>Ctrl</Kbd>
                                    <span>+</span>
                                    <Kbd>S</Kbd>
                                </KbdGroup>
                            </div>
                        </CardHeader>
                        <CardContent className="flex flex-1 flex-col gap-1 p-3">
                            {renderOrder.length === 0 ? (
                                <Empty className="flex-1 rounded-2xl border border-dashed bg-muted/10 py-16">
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <LinkIcon />
                                        </EmptyMedia>
                                        <EmptyTitle>
                                            No items in this menu
                                        </EmptyTitle>
                                        <EmptyDescription>
                                            Start with the library on the right
                                            to add pages, categories, tags, and
                                            custom links.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
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
                                            dropTarget?.id === item.id
                                                ? dropTarget.position
                                                : null
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

                <CmsSaveFooter
                    statusText={footerStatusText}
                    showStatusIcon={showUnsavedChangesStatus}
                    isProcessing={isSaving}
                    secondaryAction={{
                        label: 'Discard Changes',
                        onClick: handleDiscardChanges,
                        disabled: !isDirty || isSaving,
                    }}
                    primaryAction={{
                        label: 'Save Menu',
                        submit: false,
                        onClick: handleSave,
                        disabled: !isDirty || isSaving,
                    }}
                />
            </div>

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
