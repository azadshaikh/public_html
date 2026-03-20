import {
    ArrowDownIcon,
    ArrowUpIcon,
    ChevronRightIcon,
    CopyIcon,
    Trash2Icon,
} from 'lucide-react';
import type { BuilderCanvasItem } from '../../types/cms';
import type { BuilderEditableElement } from './builder-dom';
import { cn } from '@/lib/utils';

type BuilderCanvasToolbarProps = {
    canvasItems: BuilderCanvasItem[];
    selectedItemId: string | null;
    selectedElement: BuilderEditableElement | null;
    onSelectItem: (uid: string) => void;
    onMoveSelectedItem: (direction: 'up' | 'down') => void;
    onDuplicateSelectedItem: () => void;
    onRemoveSelectedItem: () => void;
};

export function BuilderCanvasToolbar({
    canvasItems,
    selectedItemId,
    selectedElement,
    onSelectItem,
    onMoveSelectedItem,
    onDuplicateSelectedItem,
    onRemoveSelectedItem,
}: BuilderCanvasToolbarProps) {
    if (canvasItems.length === 0) {
        return null;
    }

    const selectedItem = canvasItems.find((item) => item.uid === selectedItemId);
    const selectedIndex = canvasItems.findIndex((item) => item.uid === selectedItemId);

    return (
        <div className="flex h-[35px] items-center gap-1 border-t border-border/60 bg-background/95 px-2 backdrop-blur">
            {/* Breadcrumb-style item navigation */}
            <div className="flex min-w-0 flex-1 items-center gap-0.5 overflow-x-auto scrollbar-none">
                {canvasItems.map((item, index) => {
                    const isSelected = item.uid === selectedItemId;

                    return (
                        <span key={item.uid} className="flex shrink-0 items-center">
                            {index > 0 ? (
                                <ChevronRightIcon className="mx-0.5 size-3 text-muted-foreground/50" />
                            ) : null}
                            <button
                                type="button"
                                onClick={() => onSelectItem(item.uid)}
                                className={cn(
                                    'max-w-[120px] truncate rounded px-1.5 py-0.5 text-[11px] transition',
                                    isSelected
                                        ? 'bg-primary/10 font-semibold text-primary'
                                        : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                                )}
                            >
                                {item.label}
                            </button>
                        </span>
                    );
                })}

                {/* Element path indicator */}
                {selectedElement ? (
                    <span className="flex shrink-0 items-center">
                        <ChevronRightIcon className="mx-0.5 size-3 text-muted-foreground/50" />
                        <span className="rounded bg-orange-500/10 px-1.5 py-0.5 text-[11px] font-medium text-orange-600 dark:text-orange-400">
                            {selectedElement.tagName}
                            {selectedElement.className ? (
                                <span className="ml-0.5 text-orange-500/70 dark:text-orange-400/60">
                                    .{selectedElement.className.split(' ')[0]}
                                </span>
                            ) : null}
                        </span>
                    </span>
                ) : null}
            </div>

            {/* Actions for selected item */}
            {selectedItem ? (
                <div className="flex shrink-0 items-center gap-0.5 border-l border-border/50 pl-2">
                    <ToolbarButton
                        title="Move up"
                        disabled={selectedIndex <= 0}
                        onClick={() => onMoveSelectedItem('up')}
                    >
                        <ArrowUpIcon className="size-3.5" />
                    </ToolbarButton>
                    <ToolbarButton
                        title="Move down"
                        disabled={selectedIndex >= canvasItems.length - 1}
                        onClick={() => onMoveSelectedItem('down')}
                    >
                        <ArrowDownIcon className="size-3.5" />
                    </ToolbarButton>
                    <ToolbarButton title="Duplicate" onClick={onDuplicateSelectedItem}>
                        <CopyIcon className="size-3.5" />
                    </ToolbarButton>
                    <ToolbarButton title="Remove" onClick={onRemoveSelectedItem} variant="danger">
                        <Trash2Icon className="size-3.5" />
                    </ToolbarButton>
                </div>
            ) : null}
        </div>
    );
}

type ToolbarButtonProps = {
    children: React.ReactNode;
    title: string;
    disabled?: boolean;
    variant?: 'default' | 'danger';
    onClick: () => void;
};

function ToolbarButton({ children, title, disabled, variant = 'default', onClick }: ToolbarButtonProps) {
    return (
        <button
            type="button"
            title={title}
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'inline-flex size-6 items-center justify-center rounded transition disabled:opacity-30',
                variant === 'danger'
                    ? 'text-muted-foreground hover:bg-destructive/10 hover:text-destructive'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
            )}
        >
            {children}
        </button>
    );
}
