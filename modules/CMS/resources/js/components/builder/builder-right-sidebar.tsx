import {
    AlignCenterIcon,
    AlignJustifyIcon,
    AlignLeftIcon,
    AlignRightIcon,
    ArrowDownIcon,
    ArrowUpIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    CopyIcon,
    EyeIcon,
    Layers3Icon,
    PaintbrushIcon,
    Trash2Icon,
} from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type { BuilderEditableElement, BuilderElementStyleValues } from './builder-dom';
import type { AstNodeId, AstNodeMap } from './core/ast-types';

type BuilderRightSidebarProps = {
    nodes: AstNodeMap;
    rootNodeId: AstNodeId;
    selectedNodeId: AstNodeId | null;
    selectedElement: BuilderEditableElement | null;
    onUpdateElementField: (
        field: 'id' | 'className' | 'href' | 'textContent',
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

type StyleInputRowProps = {
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    suffix?: string;
    type?: string;
    containerClassName?: string;
};

type InspectorSectionProps = {
    value: string;
    title: string;
    summary?: string;
    children: ReactNode;
};

const FONT_WEIGHT_OPTIONS = [
    { value: '', label: 'Default' },
    { value: '100', label: 'Thin' },
    { value: '200', label: 'Extra Light' },
    { value: '300', label: 'Light' },
    { value: '400', label: 'Normal' },
    { value: '500', label: 'Medium' },
    { value: '600', label: 'Semi Bold' },
    { value: '700', label: 'Bold' },
    { value: '800', label: 'Extra Bold' },
    { value: '900', label: 'Black' },
];

function formatStyleValue(value: string, fallback = 'Default'): string {
    const trimmed = value.trim();

    return trimmed === '' ? fallback : trimmed;
}

function formatDimensionValue(value: string): string {
    const trimmed = value.trim();

    return trimmed === '' ? 'auto' : trimmed;
}

function formatLengthValue(value: string, fallback = '0px'): string {
    const trimmed = value.trim();

    return trimmed === '' ? fallback : trimmed;
}

function formatAlignmentLabel(value: string): string {
    switch (value) {
        case 'center':
            return 'Center';
        case 'right':
            return 'Right';
        case 'justify':
            return 'Justify';
        case 'left':
            return 'Left';
        default:
            return 'Default';
    }
}

function formatWeightLabel(value: string): string {
    return FONT_WEIGHT_OPTIONS.find((option) => option.value === value)?.label ?? 'Default';
}

function parseLengthValue(value: string): number {
    const match = value.match(/-?\d+(?:\.\d+)?/);

    return match ? Number.parseFloat(match[0]) : 0;
}

function parseOpacityValue(value: string): number {
    const numeric = Number.parseFloat(value);

    if (!Number.isFinite(numeric)) {
        return 100;
    }

    return Math.max(0, Math.min(100, Math.round(numeric * 100)));
}

function formatSpacingSummary(top: string, right: string, bottom: string, left: string): string {
    return [top, right, bottom, left]
        .map((value) => formatLengthValue(value))
        .join(' ');
}

function StyleInputRow({
    label,
    value,
    onChange,
    placeholder,
    suffix,
    type = 'text',
    containerClassName,
}: StyleInputRowProps) {
    return (
        <div className="flex items-center justify-between gap-3">
            <label className="text-[12px] font-medium text-foreground/80">{label}</label>
            <div className={cn('relative w-32 shrink-0', containerClassName)}>
                <input
                    type={type}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder={placeholder}
                    className="h-8 w-full rounded-lg border border-border/70 bg-background px-2.5 text-[12px] outline-none transition-colors focus:border-primary/50 focus:ring-1 focus:ring-primary/15"
                />
                {suffix ? (
                    <span className="pointer-events-none absolute top-1/2 right-2.5 -translate-y-1/2 text-[10px] text-muted-foreground">
                        {suffix}
                    </span>
                ) : null}
            </div>
        </div>
    );
}

function ColorStyleRow({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    const swatchColor = value.trim() === '' ? '#000000' : value;

    return (
        <div className="rounded-xl bg-muted/45 px-3 py-2.5">
            <div className="flex items-center gap-3">
                <span className="min-w-0 flex-1 text-[12px] font-medium text-foreground/80">{label}</span>
                <label className="relative size-5 shrink-0 overflow-hidden rounded-full border border-border/70 shadow-sm">
                    <span className="absolute inset-0" style={{ backgroundColor: swatchColor }} />
                    <input
                        type="color"
                        value={swatchColor}
                        onChange={(event) => onChange(event.target.value)}
                        className="absolute inset-0 cursor-pointer opacity-0"
                        aria-label={`${label} color`}
                    />
                </label>
                <input
                    type="text"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder="rgba(0, 0, 0, 1)"
                    className="h-auto w-36 border-0 bg-transparent p-0 text-right text-[12px] text-muted-foreground outline-none placeholder:text-muted-foreground/60"
                />
            </div>
        </div>
    );
}

function LengthSliderRow({
    label,
    value,
    onChange,
    min = 0,
    max = 200,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    min?: number;
    max?: number;
}) {
    const numericValue = parseLengthValue(value);

    return (
        <div className="rounded-xl bg-muted/45 px-3 py-2.5">
            <div className="flex items-center justify-between gap-3">
                <span className="text-[12px] font-medium text-foreground/80">{label}</span>
                <span className="text-[12px] text-muted-foreground">{formatLengthValue(value)}</span>
            </div>
            <input
                type="range"
                min={min}
                max={max}
                step="1"
                value={numericValue}
                onChange={(event) => onChange(`${event.target.value}px`)}
                className="mt-2 h-2 w-full cursor-pointer accent-primary"
            />
        </div>
    );
}

function OpacitySliderRow({
    value,
    onChange,
}: {
    value: string;
    onChange: (value: string) => void;
}) {
    const numericValue = parseOpacityValue(value);

    return (
        <div className="rounded-xl bg-muted/45 px-3 py-2.5">
            <div className="flex items-center justify-between gap-3">
                <span className="text-[12px] font-medium text-foreground/80">Opacity</span>
                <span className="text-[12px] text-muted-foreground">{numericValue}%</span>
            </div>
            <input
                type="range"
                min="0"
                max="100"
                step="1"
                value={numericValue}
                onChange={(event) => onChange(`${Number.parseInt(event.target.value, 10) / 100}`)}
                className="mt-2 h-2 w-full cursor-pointer accent-primary"
            />
        </div>
    );
}

function InspectorSection({ value, title, summary, children }: InspectorSectionProps) {
    return (
        <AccordionItem value={value} className="px-3">
            <AccordionTrigger className="py-3 text-sm hover:no-underline">
                <div className="flex min-w-0 flex-1 items-center gap-3 pr-3 text-left">
                    <span className="text-[13px] font-medium text-foreground">{title}</span>
                    {summary ? (
                        <span className="ml-auto truncate text-[12px] text-muted-foreground">
                            {summary}
                        </span>
                    ) : null}
                </div>
            </AccordionTrigger>
            <AccordionContent className="pb-3">
                <div className="flex flex-col gap-3">{children}</div>
            </AccordionContent>
        </AccordionItem>
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
    children: ReactNode;
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
    const attributeSummary = selectedElement.id.trim() || selectedElement.className.trim() || 'Element metadata';
    const dimensionsSummary = `${formatDimensionValue(selectedElement.styles.width)} x ${formatDimensionValue(selectedElement.styles.height)}`;
    const typographySummary = `${formatStyleValue(selectedElement.styles.fontSize, 'Auto')} · ${formatWeightLabel(selectedElement.styles.fontWeight)}`;
    const colorsSummary = [
        selectedElement.styles.backgroundColor.trim() !== '' ? 'Background' : null,
        selectedElement.styles.color.trim() !== '' ? 'Text' : null,
    ].filter(Boolean).join(' · ') || 'Default';
    const contentSummary = selectedElement.isLink
        ? selectedElement.href.trim() || formatStyleValue(selectedElement.textContent, 'Empty label')
        : selectedElement.tagName === 'button'
            ? formatStyleValue(selectedElement.textContent, 'Empty label')
            : '';
    const marginSummary = formatSpacingSummary(
        selectedElement.styles.marginTop,
        selectedElement.styles.marginRight,
        selectedElement.styles.marginBottom,
        selectedElement.styles.marginLeft,
    );
    const paddingSummary = formatSpacingSummary(
        selectedElement.styles.paddingTop,
        selectedElement.styles.paddingRight,
        selectedElement.styles.paddingBottom,
        selectedElement.styles.paddingLeft,
    );
    const decorationSummary = formatLengthValue(selectedElement.styles.borderRadius);
    const alignmentSummary = formatAlignmentLabel(selectedElement.styles.textAlign);

    return (
        <div className="flex flex-col">
            <Accordion
                type="multiple"
                defaultValue={['attributes', 'typography', 'colors']}
                className="w-full"
            >
                <InspectorSection value="attributes" title="Attributes" summary={attributeSummary}>
                    <StyleInputRow
                        label="ID"
                        value={selectedElement.id}
                        onChange={(value) => onUpdateElementField('id', value)}
                        placeholder="element-id"
                        containerClassName="w-36"
                    />
                    <StyleInputRow
                        label="Classes"
                        value={selectedElement.className}
                        onChange={(value) => onUpdateElementField('className', value)}
                        placeholder="class-name"
                        containerClassName="w-40"
                    />
                </InspectorSection>

                {selectedElement.isLink || selectedElement.tagName === 'button' ? (
                    <InspectorSection value="content" title="Content" summary={contentSummary}>
                        <StyleInputRow
                            label="Label"
                            value={selectedElement.textContent}
                            onChange={(value) => onUpdateElementField('textContent', value)}
                            placeholder="Button label"
                            containerClassName="w-40"
                        />
                        {selectedElement.isLink ? (
                            <StyleInputRow
                                label="URL"
                                value={selectedElement.href}
                                onChange={(value) => onUpdateElementField('href', value)}
                                placeholder="https://example.com"
                                containerClassName="w-40"
                            />
                        ) : null}
                    </InspectorSection>
                ) : null}

                <InspectorSection value="dimensions" title="Dimensions" summary={dimensionsSummary}>
                    <StyleInputRow
                        label="Width"
                        value={selectedElement.styles.width}
                        onChange={(value) => onUpdateElementStyle('width', value)}
                        placeholder="100%"
                    />
                    <StyleInputRow
                        label="Height"
                        value={selectedElement.styles.height}
                        onChange={(value) => onUpdateElementStyle('height', value)}
                        placeholder="auto"
                    />
                    <OpacitySliderRow
                        value={selectedElement.styles.opacity}
                        onChange={(value) => onUpdateElementStyle('opacity', value)}
                    />
                </InspectorSection>

                <InspectorSection value="typography" title="Typography" summary={typographySummary}>
                    <StyleInputRow
                        label="Font size"
                        value={selectedElement.styles.fontSize}
                        onChange={(value) => onUpdateElementStyle('fontSize', value)}
                        placeholder="16px"
                    />
                    <div className="flex items-center justify-between gap-3">
                        <label className="text-[12px] font-medium text-foreground/80">Weight</label>
                        <select
                            value={selectedElement.styles.fontWeight}
                            onChange={(event) => onUpdateElementStyle('fontWeight', event.target.value)}
                            className="h-8 w-36 rounded-lg border border-border/70 bg-background px-2.5 text-[12px] outline-none transition-colors focus:border-primary/50 focus:ring-1 focus:ring-primary/15"
                        >
                            {FONT_WEIGHT_OPTIONS.map((option) => (
                                <option key={option.label} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </InspectorSection>

                <InspectorSection value="colors" title="Colors" summary={colorsSummary}>
                    <ColorStyleRow
                        label="Background"
                        value={selectedElement.styles.backgroundColor}
                        onChange={(value) => onUpdateElementStyle('backgroundColor', value)}
                    />
                    <ColorStyleRow
                        label="Text"
                        value={selectedElement.styles.color}
                        onChange={(value) => onUpdateElementStyle('color', value)}
                    />
                </InspectorSection>

                <InspectorSection value="margin" title="Margin" summary={marginSummary}>
                    <div className="grid grid-cols-2 gap-2">
                        <LengthSliderRow
                            label="Top"
                            value={selectedElement.styles.marginTop}
                            onChange={(value) => onUpdateElementStyle('marginTop', value)}
                        />
                        <LengthSliderRow
                            label="Right"
                            value={selectedElement.styles.marginRight}
                            onChange={(value) => onUpdateElementStyle('marginRight', value)}
                        />
                        <LengthSliderRow
                            label="Bottom"
                            value={selectedElement.styles.marginBottom}
                            onChange={(value) => onUpdateElementStyle('marginBottom', value)}
                        />
                        <LengthSliderRow
                            label="Left"
                            value={selectedElement.styles.marginLeft}
                            onChange={(value) => onUpdateElementStyle('marginLeft', value)}
                        />
                    </div>
                </InspectorSection>

                <InspectorSection value="padding" title="Padding" summary={paddingSummary}>
                    <div className="grid grid-cols-2 gap-2">
                        <LengthSliderRow
                            label="Top"
                            value={selectedElement.styles.paddingTop}
                            onChange={(value) => onUpdateElementStyle('paddingTop', value)}
                        />
                        <LengthSliderRow
                            label="Right"
                            value={selectedElement.styles.paddingRight}
                            onChange={(value) => onUpdateElementStyle('paddingRight', value)}
                        />
                        <LengthSliderRow
                            label="Bottom"
                            value={selectedElement.styles.paddingBottom}
                            onChange={(value) => onUpdateElementStyle('paddingBottom', value)}
                        />
                        <LengthSliderRow
                            label="Left"
                            value={selectedElement.styles.paddingLeft}
                            onChange={(value) => onUpdateElementStyle('paddingLeft', value)}
                        />
                    </div>
                </InspectorSection>

                <InspectorSection value="decoration" title="Decoration" summary={decorationSummary}>
                    <LengthSliderRow
                        label="Radius"
                        value={selectedElement.styles.borderRadius}
                        onChange={(value) => onUpdateElementStyle('borderRadius', value)}
                        max={96}
                    />
                </InspectorSection>

                <InspectorSection value="alignment" title="Alignment" summary={alignmentSummary}>
                    <ToggleGroup
                        type="single"
                        size="sm"
                        value={selectedElement.styles.textAlign || 'left'}
                        onValueChange={(value) => {
                            if (value !== '') {
                                onUpdateElementStyle('textAlign', value);
                            }
                        }}
                        className="grid grid-cols-4 gap-1 rounded-xl bg-muted/45 p-1"
                    >
                        <ToggleGroupItem value="left" aria-label="Left" className="size-8 rounded-lg p-0">
                            <AlignLeftIcon className="size-3.5" />
                        </ToggleGroupItem>
                        <ToggleGroupItem value="center" aria-label="Center" className="size-8 rounded-lg p-0">
                            <AlignCenterIcon className="size-3.5" />
                        </ToggleGroupItem>
                        <ToggleGroupItem value="right" aria-label="Right" className="size-8 rounded-lg p-0">
                            <AlignRightIcon className="size-3.5" />
                        </ToggleGroupItem>
                        <ToggleGroupItem value="justify" aria-label="Justify" className="size-8 rounded-lg p-0">
                            <AlignJustifyIcon className="size-3.5" />
                        </ToggleGroupItem>
                    </ToggleGroup>
                </InspectorSection>
            </Accordion>
        </div>
    );
}

