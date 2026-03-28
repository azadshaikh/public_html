import {
    ArrowDownIcon,
    ArrowLeftIcon,
    ArrowRightIcon,
    ArrowUpIcon,
    GripVerticalIcon,
    PencilIcon,
    Trash2Icon,
} from 'lucide-react';
import type { DragEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { DraftMenuItem, RenderItem } from './menu-editor-types';
import {
    getItemDepth,
    getSubtreeMaxDepth,
} from './menu-editor-utils';

type MenuItemRowProps = {
    renderItem: RenderItem;
    allItems: DraftMenuItem[];
    menuId: number;
    maxDepth: number;
    isDraggedOver: 'before' | 'after' | null;
    isDragging: boolean;
    onDragStart: (event: DragEvent<HTMLButtonElement>, id: number) => void;
    onDragOver: (event: DragEvent<HTMLDivElement>, id: number) => void;
    onDrop: (event: DragEvent<HTMLDivElement>) => void;
    onDragEnd: () => void;
    onMoveUp: (id: number) => void;
    onMoveDown: (id: number) => void;
    onIndent: (id: number) => void;
    onOutdent: (id: number) => void;
    onEdit: (item: DraftMenuItem) => void;
    onDelete: (id: number) => void;
};

export function MenuItemRow({
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
        .filter((candidate) => candidate.parent_id === item.parent_id)
        .sort((left, right) => left.sort_order - right.sort_order);
    const siblingIndex = siblings.findIndex((candidate) => candidate.id === item.id);
    const canMoveUp = siblingIndex > 0;
    const canMoveDown = siblingIndex < siblings.length - 1;
    const canOutdent = item.parent_id !== menuId;
    const hasPreviousSibling = siblingIndex > 0;
    const previousSibling = hasPreviousSibling ? siblings[siblingIndex - 1] : null;
    const previousSiblingDepth = previousSibling
        ? getItemDepth(allItems, previousSibling.id, menuId)
        : 0;
    const subtreeHeight =
        getSubtreeMaxDepth(allItems, item.id, menuId) -
        getItemDepth(allItems, item.id, menuId);
    const canIndent =
        hasPreviousSibling && previousSiblingDepth + 1 + subtreeHeight < maxDepth;

    return (
        <div className="relative" style={{ paddingLeft: `${depth * 24}px` }}>
            {isDraggedOver === 'before' ? (
                <div
                    className="absolute top-0 right-0 left-0 h-0.5 rounded bg-primary"
                    style={{ zIndex: 10 }}
                />
            ) : null}
            {isDraggedOver === 'after' ? (
                <div
                    className="absolute right-0 bottom-0 left-0 h-0.5 rounded bg-primary"
                    style={{ zIndex: 10 }}
                />
            ) : null}
            <div
                className={cn(
                    'group rounded-xl border bg-card p-4 shadow-xs transition-all',
                    isDragging ? 'scale-[0.99] opacity-45' : 'opacity-100',
                    isDraggedOver ? 'bg-muted/30' : '',
                )}
                onDragOver={(event) => onDragOver(event, item.id)}
                onDrop={onDrop}
            >
                <div className="flex items-start gap-3">
                    <div className="flex items-center gap-2 self-stretch">
                        <button
                            type="button"
                            draggable
                            onDragStart={(event) => onDragStart(event, item.id)}
                            onDragEnd={onDragEnd}
                            className="mt-0.5 rounded-lg border bg-muted/40 p-2 text-muted-foreground transition hover:bg-accent hover:text-foreground active:cursor-grabbing"
                            aria-label="Drag to reorder"
                            title="Drag to reorder"
                        >
                            <GripVerticalIcon className="size-4" />
                        </button>
                    </div>

                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="truncate text-sm font-medium">
                                {item.title}
                            </p>
                            <Badge variant="outline">{item.type}</Badge>
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
