import { ArrowDownIcon, ArrowUpIcon, ChevronDownIcon, ChevronRightIcon, CopyIcon, Layers3Icon, Trash2Icon, type LucideIcon } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { AstNodeId, AstNodeMap } from './core/ast-types';

type StructureTabProps = {
    nodes: AstNodeMap;
    rootNodeId: AstNodeId;
    selectedNodeId: AstNodeId | null;
    onSelectNode: (nodeId: AstNodeId) => void;
    onMoveNode: (nodeId: AstNodeId, direction: 'up' | 'down') => void;
    onDuplicateNode: (nodeId: AstNodeId) => void;
    onDeleteNode: (nodeId: AstNodeId) => void;
};

export function StructureTab({
    nodes,
    rootNodeId,
    selectedNodeId,
    onSelectNode,
    onMoveNode,
    onDuplicateNode,
    onDeleteNode,
}: StructureTabProps) {
    const [collapsedIds, setCollapsedIds] = useState<Record<string, boolean>>({});
    const root = nodes[rootNodeId];

    useEffect(() => {
        if (!selectedNodeId) {
            return;
        }

        const selectedNode = nodes[selectedNodeId];

        if (!selectedNode) {
            return;
        }

        setCollapsedIds((current) => {
            const next = { ...current };
            let changed = false;
            let parentId = selectedNode.parentId;

            while (parentId && parentId !== rootNodeId) {
                if ((next[parentId] ?? true) !== false) {
                    next[parentId] = false;
                    changed = true;
                }

                parentId = nodes[parentId]?.parentId ?? null;
            }

            return changed ? next : current;
        });
    }, [nodes, rootNodeId, selectedNodeId]);

    if (!root || root.childIds.length === 0) {
        return (
            <div className="px-4 py-12 text-center">
                <Layers3Icon className="mx-auto mb-3 size-6 text-muted-foreground/50" />
                <p className="text-sm font-medium text-muted-foreground">No structure yet</p>
                <p className="mt-1 text-xs text-muted-foreground/70">
                    Add a section from the left sidebar to start building the page structure.
                </p>
            </div>
        );
    }

    const toggleCollapsed = (nodeId: AstNodeId) => {
        setCollapsedIds((current) => ({
            ...current,
            [nodeId]: !(current[nodeId] ?? true),
        }));
    };

    return (
        <div className="flex flex-col p-1.5">
            <div className="flex flex-col gap-0.5">
                {root.childIds.map((childId) => (
                    <StructureTreeNode
                        key={childId}
                        nodeId={childId}
                        depth={0}
                        nodes={nodes}
                        selectedNodeId={selectedNodeId}
                        collapsedIds={collapsedIds}
                        onToggleCollapsed={toggleCollapsed}
                        onSelectNode={onSelectNode}
                        onMoveNode={onMoveNode}
                        onDuplicateNode={onDuplicateNode}
                        onDeleteNode={onDeleteNode}
                    />
                ))}
            </div>
        </div>
    );
}

function StructureTreeNode({
    nodeId,
    depth,
    nodes,
    selectedNodeId,
    collapsedIds,
    onToggleCollapsed,
    onSelectNode,
    onMoveNode,
    onDuplicateNode,
    onDeleteNode,
}: {
    nodeId: AstNodeId;
    depth: number;
    nodes: AstNodeMap;
    selectedNodeId: AstNodeId | null;
    collapsedIds: Record<string, boolean>;
    onToggleCollapsed: (nodeId: AstNodeId) => void;
    onSelectNode: (nodeId: AstNodeId) => void;
    onMoveNode: (nodeId: AstNodeId, direction: 'up' | 'down') => void;
    onDuplicateNode: (nodeId: AstNodeId) => void;
    onDeleteNode: (nodeId: AstNodeId) => void;
}) {
    const node = nodes[nodeId];

    if (!node) {
        return null;
    }

    const hasChildren = node.childIds.length > 0;
    const isCollapsed = collapsedIds[nodeId] ?? hasChildren;
    const isSelected = selectedNodeId === nodeId;
    const parent = node.parentId ? nodes[node.parentId] : null;
    const siblingIds = parent?.childIds ?? [];
    const index = siblingIds.indexOf(nodeId);
    const canMoveUp = index > 0;
    const canMoveDown = index >= 0 && index < siblingIds.length - 1;
    const label = node.displayName || node.type;
    const isSection = node.type === 'section';

    return (
        <div className="flex flex-col">
            <div
                className={cn(
                    'group relative flex items-center gap-0.5 rounded-md px-1 transition-colors',
                    isSelected ? 'bg-accent text-accent-foreground' : 'hover:bg-muted/50',
                    isSection && depth === 0 && 'border border-border',
                )}
                style={{ paddingLeft: depth * 12 }}
            >
                <button
                    type="button"
                    onClick={() => hasChildren && onToggleCollapsed(nodeId)}
                    className={cn(
                        'inline-flex size-6 shrink-0 items-center justify-center rounded text-muted-foreground',
                        hasChildren ? 'hover:text-foreground' : 'invisible',
                    )}
                    aria-label={hasChildren ? (isCollapsed ? 'Expand structure item' : 'Collapse structure item') : 'No child items'}
                >
                    {hasChildren ? (isCollapsed ? <ChevronRightIcon className="size-3.5" /> : <ChevronDownIcon className="size-3.5" />) : null}
                </button>

                <button
                    type="button"
                    onClick={() => onSelectNode(nodeId)}
                    className="min-w-0 flex-1 truncate py-1.5 text-left text-[12px] leading-4"
                >
                    <span className={cn('text-foreground/85', isSelected ? 'font-semibold text-foreground' : 'font-medium')}>
                        {label}
                    </span>
                </button>

                <div className="pointer-events-none flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:pointer-events-auto group-hover:opacity-100">
                    <StructureActionButton
                        title={canMoveUp ? 'Move up' : 'Already first sibling'}
                        disabled={!canMoveUp}
                        onClick={() => onMoveNode(nodeId, 'up')}
                        icon={ArrowUpIcon}
                    />
                    <StructureActionButton
                        title={canMoveDown ? 'Move down' : 'Already last sibling'}
                        disabled={!canMoveDown}
                        onClick={() => onMoveNode(nodeId, 'down')}
                        icon={ArrowDownIcon}
                    />
                    <StructureActionButton title="Duplicate" onClick={() => onDuplicateNode(nodeId)} icon={CopyIcon} />
                    <StructureActionButton title="Delete" variant="danger" onClick={() => onDeleteNode(nodeId)} icon={Trash2Icon} />
                </div>
            </div>

            {hasChildren && !isCollapsed ? (
                <div className="flex flex-col">
                    {node.childIds.map((childId) => (
                        <StructureTreeNode
                            key={childId}
                            nodeId={childId}
                            depth={depth + 1}
                            nodes={nodes}
                            selectedNodeId={selectedNodeId}
                            collapsedIds={collapsedIds}
                            onToggleCollapsed={onToggleCollapsed}
                            onSelectNode={onSelectNode}
                            onMoveNode={onMoveNode}
                            onDuplicateNode={onDuplicateNode}
                            onDeleteNode={onDeleteNode}
                        />
                    ))}
                </div>
            ) : null}
        </div>
    );
}

function StructureActionButton({
    title,
    disabled,
    variant = 'default',
    onClick,
    icon: Icon,
}: {
    title: string;
    disabled?: boolean;
    variant?: 'default' | 'danger';
    onClick: () => void;
    icon: LucideIcon;
}) {
    return (
        <button
            type="button"
            title={title}
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'inline-flex size-5 items-center justify-center rounded transition disabled:cursor-not-allowed disabled:opacity-30',
                variant === 'danger'
                    ? 'text-muted-foreground hover:bg-destructive/10 hover:text-destructive'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
            )}
        >
            <Icon className="size-3.5" />
        </button>
    );
}