import {
    AlignCenterIcon,
    AlignJustifyIcon,
    AlignLeftIcon,
    AlignRightIcon,
    ArrowDownIcon,
    ArrowUpIcon,
    BoxIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    CopyIcon,
    EyeIcon,
    LayoutIcon,
    Layers3Icon,
    PaintbrushIcon,
    Trash2Icon,
    TypeIcon,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type { AstNodeId, AstNodeMap } from './core/ast-types';
import type {
    BuilderEditableElement,
    BuilderElementStyleValues,
} from './builder-dom';

type BuilderRightSidebarProps = {
    nodes: AstNodeMap;
    rootNodeId: AstNodeId;
    selectedNodeId: AstNodeId | null;
    selectedElement: BuilderEditableElement | null;
    onUpdateElementField: (
        field: 'id' | 'className',
        value: string,
    ) => void;
    onUpdateElementStyle: (
        field: keyof BuilderElementStyleValues,
        value: string,
    ) => void;
    onSelectNode: (nodeId: AstNodeId) => void;
    onMoveNode: (nodeId: AstNodeId, direction: 'up' | 'down') => void;
    onDuplicateNode: (nodeId: AstNodeId) => void;
    onDeleteNode: (nodeId: AstNodeId) => void;
};

type CollapsibleSectionProps = {
    title: string;
    icon?: React.ReactNode;
    children: React.ReactNode;
    defaultOpen?: boolean;
};

function CollapsibleSection({ title, icon, children, defaultOpen = false }: CollapsibleSectionProps) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <div className="border-b border-border/50">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider transition-colors hover:bg-muted/40"
            >
                {open ? <ChevronDownIcon className="size-3.5" /> : <ChevronRightIcon className="size-3.5" />}
                {icon}
                <span className="flex-1">{title}</span>
            </button>
            {open ? <div className="px-3 pb-3">{children}</div> : null}
        </div>
    );
}

type StyleInputRowProps = {
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    suffix?: string;
    type?: string;
};

function StyleInputRow({ label, value, onChange, placeholder, suffix, type = 'text' }: StyleInputRowProps) {
    return (
        <div className="flex items-center gap-2">
            <label className="w-20 shrink-0 text-[11px] text-muted-foreground">{label}</label>
            <div className="relative flex-1">
                <input
                    type={type}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    className="h-7 w-full rounded-md border border-border/70 bg-background px-2 text-xs outline-none transition-colors focus:border-primary/50 focus:ring-1 focus:ring-primary/20"
                />
                {suffix ? (
                    <span className="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 text-[10px] text-muted-foreground">
                        {suffix}
                    </span>
                ) : null}
            </div>
        </div>
    );
}

type ColorInputRowProps = {
    label: string;
    value: string;
    onChange: (value: string) => void;
};

function ColorInputRow({ label, value, onChange }: ColorInputRowProps) {
    return (
        <div className="flex items-center gap-2">
            <label className="w-20 shrink-0 text-[11px] text-muted-foreground">{label}</label>
            <div className="flex flex-1 items-center gap-1.5">
                <input
                    type="color"
                    value={value || '#000000'}
                    onChange={(e) => onChange(e.target.value)}
                    className="size-7 shrink-0 cursor-pointer rounded border border-border/70 bg-background p-0.5"
                />
                <input
                    type="text"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder="rgba(0, 0, 0, 0)"
                    className="h-7 w-full rounded-md border border-border/70 bg-background px-2 text-xs outline-none transition-colors focus:border-primary/50 focus:ring-1 focus:ring-primary/20"
                />
            </div>
        </div>
    );
}

export function BuilderRightSidebar({
    nodes,
    rootNodeId,
    selectedNodeId,
    selectedElement,
    onUpdateElementField,
    onUpdateElementStyle,
    onSelectNode,
    onMoveNode,
    onDuplicateNode,
    onDeleteNode,
}: BuilderRightSidebarProps) {
    return (
        <div className="flex h-full flex-col">
            <Tabs defaultValue="style" className="flex min-h-0 flex-1 flex-col">
                <TabsList className="grid w-full shrink-0 grid-cols-2 rounded-none border-b border-border/60" variant="line">
                    <TabsTrigger value="style" className="gap-1.5 text-xs">
                        <PaintbrushIcon className="size-3.5" />
                        Style
                    </TabsTrigger>
                    <TabsTrigger value="layers" className="gap-1.5 text-xs">
                        <Layers3Icon className="size-3.5" />
                        Structure
                    </TabsTrigger>
                </TabsList>

                <ScrollArea className="min-h-0 flex-1">
                    <TabsContent value="style" className="mt-0">
                        {selectedElement ? (
                            <StyleTab
                                selectedElement={selectedElement}
                                onUpdateElementField={onUpdateElementField}
                                onUpdateElementStyle={onUpdateElementStyle}
                            />
                        ) : (
                            <EmptySelectionState />
                        )}
                    </TabsContent>

                    <TabsContent value="layers" className="mt-0">
                        <StructureTab
                            nodes={nodes}
                            rootNodeId={rootNodeId}
                            selectedNodeId={selectedNodeId}
                            onSelectNode={onSelectNode}
                            onMoveNode={onMoveNode}
                            onDuplicateNode={onDuplicateNode}
                            onDeleteNode={onDeleteNode}
                        />
                    </TabsContent>
                </ScrollArea>
            </Tabs>
        </div>
    );
}

function EmptySelectionState() {
    return (
        <div className="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center">
            <div className="rounded-full bg-muted/50 p-3">
                <EyeIcon className="size-6 text-muted-foreground/50" />
            </div>
            <p className="text-sm font-medium text-muted-foreground">No element selected</p>
            <p className="text-xs text-muted-foreground/70">
                Select an item in the structure or click an element in the preview to inspect and edit it.
            </p>
        </div>
    );
}

function StructureTab({
    nodes,
    rootNodeId,
    selectedNodeId,
    onSelectNode,
    onMoveNode,
    onDuplicateNode,
    onDeleteNode,
}: {
    nodes: AstNodeMap;
    rootNodeId: AstNodeId;
    selectedNodeId: AstNodeId | null;
    onSelectNode: (nodeId: AstNodeId) => void;
    onMoveNode: (nodeId: AstNodeId, direction: 'up' | 'down') => void;
    onDuplicateNode: (nodeId: AstNodeId) => void;
    onDeleteNode: (nodeId: AstNodeId) => void;
}) {
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
                    isSelected
                        ? 'bg-accent text-accent-foreground'
                        : 'hover:bg-muted/50',
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
                    {hasChildren ? (
                        isCollapsed ? <ChevronRightIcon className="size-3.5" /> : <ChevronDownIcon className="size-3.5" />
                    ) : null}
                </button>

                <button
                    type="button"
                    onClick={() => onSelectNode(nodeId)}
                    className="min-w-0 flex-1 truncate py-1.5 text-left text-[12px] leading-4"
                >
                    <span className={cn(
                        'text-foreground/85',
                        isSelected ? 'font-semibold text-foreground' : 'font-medium',
                    )}>
                        {label}
                    </span>
                </button>

                <div className="pointer-events-none flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:pointer-events-auto group-hover:opacity-100">
                    <StructureActionButton
                        title={canMoveUp ? 'Move up' : 'Already first sibling'}
                        disabled={!canMoveUp}
                        onClick={() => onMoveNode(nodeId, 'up')}
                    >
                        <ArrowUpIcon className="size-3.5" />
                    </StructureActionButton>
                    <StructureActionButton
                        title={canMoveDown ? 'Move down' : 'Already last sibling'}
                        disabled={!canMoveDown}
                        onClick={() => onMoveNode(nodeId, 'down')}
                    >
                        <ArrowDownIcon className="size-3.5" />
                    </StructureActionButton>
                    <StructureActionButton title="Duplicate" onClick={() => onDuplicateNode(nodeId)}>
                        <CopyIcon className="size-3.5" />
                    </StructureActionButton>
                    <StructureActionButton title="Delete" variant="danger" onClick={() => onDeleteNode(nodeId)}>
                        <Trash2Icon className="size-3.5" />
                    </StructureActionButton>
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
    children,
    title,
    disabled,
    variant = 'default',
    onClick,
}: {
    children: React.ReactNode;
    title: string;
    disabled?: boolean;
    variant?: 'default' | 'danger';
    onClick: () => void;
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
            {children}
        </button>
    );
}

function StyleTab({
    selectedElement,
    onUpdateElementField,
    onUpdateElementStyle,
}: {
    selectedElement: BuilderEditableElement;
    onUpdateElementField: BuilderRightSidebarProps['onUpdateElementField'];
    onUpdateElementStyle: BuilderRightSidebarProps['onUpdateElementStyle'];
}) {
    return (
        <div className="flex flex-col">
            <CollapsibleSection title="Attributes" defaultOpen>
                <div className="flex flex-col gap-2">
                    <StyleInputRow
                        label="ID"
                        value={selectedElement.id}
                        onChange={(v) => onUpdateElementField('id', v)}
                        placeholder="element-id"
                    />
                    <StyleInputRow
                        label="Classes"
                        value={selectedElement.className}
                        onChange={(v) => onUpdateElementField('className', v)}
                        placeholder="class-name"
                    />
                </div>
            </CollapsibleSection>

            <CollapsibleSection title="Display" icon={<LayoutIcon className="size-3" />} defaultOpen>
                <div className="flex flex-col gap-2">
                    <div className="flex items-center gap-2">
                        <label className="w-20 shrink-0 text-[11px] text-muted-foreground">Opacity</label>
                        <input
                            type="range"
                            min="0"
                            max="1"
                            step="0.01"
                            defaultValue="1"
                            className="h-1.5 flex-1 cursor-pointer accent-primary"
                        />
                    </div>
                </div>
            </CollapsibleSection>

            <CollapsibleSection title="Typography" icon={<TypeIcon className="size-3" />} defaultOpen>
                <div className="flex flex-col gap-2">
                    <div className="flex items-center gap-2">
                        <label className="w-20 shrink-0 text-[11px] text-muted-foreground">Align</label>
                        <ToggleGroup
                            type="single"
                            size="sm"
                            value={selectedElement.styles.textAlign || 'left'}
                            onValueChange={(value) => {
                                if (value !== '') {
                                    onUpdateElementStyle('textAlign', value);
                                }
                            }}
                            className="gap-0.5"
                        >
                            <ToggleGroupItem value="left" aria-label="Left" className="size-7 p-0">
                                <AlignLeftIcon className="size-3.5" />
                            </ToggleGroupItem>
                            <ToggleGroupItem value="center" aria-label="Center" className="size-7 p-0">
                                <AlignCenterIcon className="size-3.5" />
                            </ToggleGroupItem>
                            <ToggleGroupItem value="right" aria-label="Right" className="size-7 p-0">
                                <AlignRightIcon className="size-3.5" />
                            </ToggleGroupItem>
                            <ToggleGroupItem value="justify" aria-label="Justify" className="size-7 p-0">
                                <AlignJustifyIcon className="size-3.5" />
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>
                    <StyleInputRow
                        label="Font size"
                        value={selectedElement.styles.fontSize}
                        onChange={(v) => onUpdateElementStyle('fontSize', v)}
                        placeholder="16"
                        suffix="px"
                    />
                    <div className="flex items-center gap-2">
                        <label className="w-20 shrink-0 text-[11px] text-muted-foreground">Weight</label>
                        <select
                            value={selectedElement.styles.fontWeight || ''}
                            onChange={(e) => onUpdateElementStyle('fontWeight', e.target.value)}
                            className="h-7 w-full rounded-md border border-border/70 bg-background px-2 text-xs outline-none transition-colors focus:border-primary/50"
                        >
                            <option value="">Default</option>
                            <option value="100">Thin</option>
                            <option value="200">Extra Light</option>
                            <option value="300">Light</option>
                            <option value="400">Normal</option>
                            <option value="500">Medium</option>
                            <option value="600">Semi Bold</option>
                            <option value="700">Bold</option>
                            <option value="800">Extra Bold</option>
                            <option value="900">Black</option>
                        </select>
                    </div>
                    <ColorInputRow
                        label="Text color"
                        value={selectedElement.styles.color}
                        onChange={(v) => onUpdateElementStyle('color', v)}
                    />
                </div>
            </CollapsibleSection>

            <CollapsibleSection title="Background" icon={<PaintbrushIcon className="size-3" />}>
                <div className="flex flex-col gap-2">
                    <ColorInputRow
                        label="Color"
                        value={selectedElement.styles.backgroundColor}
                        onChange={(v) => onUpdateElementStyle('backgroundColor', v)}
                    />
                </div>
            </CollapsibleSection>

            <CollapsibleSection title="Spacing" icon={<BoxIcon className="size-3" />}>
                <div className="flex flex-col gap-1.5">
                    <p className="mb-1 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Padding</p>
                    <div className="grid grid-cols-2 gap-1.5">
                        <StyleInputRow
                            label="Top"
                            value={selectedElement.styles.paddingTop}
                            onChange={(v) => onUpdateElementStyle('paddingTop', v)}
                            placeholder="0"
                            suffix="px"
                        />
                        <StyleInputRow
                            label="Right"
                            value={selectedElement.styles.paddingRight}
                            onChange={(v) => onUpdateElementStyle('paddingRight', v)}
                            placeholder="0"
                            suffix="px"
                        />
                        <StyleInputRow
                            label="Bottom"
                            value={selectedElement.styles.paddingBottom}
                            onChange={(v) => onUpdateElementStyle('paddingBottom', v)}
                            placeholder="0"
                            suffix="px"
                        />
                        <StyleInputRow
                            label="Left"
                            value={selectedElement.styles.paddingLeft}
                            onChange={(v) => onUpdateElementStyle('paddingLeft', v)}
                            placeholder="0"
                            suffix="px"
                        />
                    </div>
                    <p className="mt-2 mb-1 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Margin</p>
                    <div className="grid grid-cols-2 gap-1.5">
                        <StyleInputRow
                            label="Top"
                            value={selectedElement.styles.marginTop}
                            onChange={(v) => onUpdateElementStyle('marginTop', v)}
                            placeholder="0"
                            suffix="px"
                        />
                        <StyleInputRow
                            label="Bottom"
                            value={selectedElement.styles.marginBottom}
                            onChange={(v) => onUpdateElementStyle('marginBottom', v)}
                            placeholder="0"
                            suffix="px"
                        />
                    </div>
                </div>
            </CollapsibleSection>

            <CollapsibleSection title="Border">
                <div className="flex flex-col gap-2">
                    <StyleInputRow
                        label="Radius"
                        value={selectedElement.styles.borderRadius}
                        onChange={(v) => onUpdateElementStyle('borderRadius', v)}
                        placeholder="0"
                        suffix="px"
                    />
                </div>
            </CollapsibleSection>
        </div>
    );
}

