/**
 * Drag-and-drop system for the AST builder.
 *
 * Follows the craft.js pattern: Native HTML5 drag events (dragstart, dragover,
 * drop, dragend) with no external library. Calculates drop position from
 * cursor coordinates relative to candidate elements.
 *
 * Two drag sources:
 *   1. Canvas drag — reorder/move existing nodes
 *   2. Panel drag  — insert new nodes from the component palette
 */

import type { AstNodeId, AstNodeMap, DropIndicator, DropPosition } from './ast-types';
import { CANVAS_TYPES, ROOT_NODE_ID } from './ast-types';
import { getDescendantIds, isAncestor } from './ast-helpers';
import { getAstIdFromElement, getContentArea, getElementByAstId } from './iframe-sync';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export type DragSource =
    | { kind: 'existing'; nodeId: AstNodeId }
    | { kind: 'new'; html: string; type: string; displayName: string };

export type DragDropCallbacks = {
    onDragStart: (source: DragSource) => void;
    onDragMove: (indicator: DropIndicator | null) => void;
    onDropExisting: (nodeId: AstNodeId, parentId: AstNodeId, index: number) => void;
    onDropNew: (html: string, parentId: AstNodeId, index: number, type: string, displayName: string) => void;
    onDragEnd: () => void;
};

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const DROP_ZONE_EDGE_RATIO = 0.25; // top/bottom 25% = before/after, middle 50% = inside (canvas)
const INDICATOR_HEIGHT = 3;

// ---------------------------------------------------------------------------
// Drop position calculator
// ---------------------------------------------------------------------------

export function computeDropPosition(
    cursorY: number,
    targetRect: DOMRect,
    targetNodeId: AstNodeId,
    nodes: AstNodeMap,
    source?: DragSource,
): DropPosition {
    const node = nodes[targetNodeId];

    if (!node) {
        return 'inside';
    }

    const relativeY = cursorY - targetRect.top;
    const ratio = relativeY / targetRect.height;

    // Sections can only be placed before/after (never inside another element)
    const isDraggingSection = source?.kind === 'new' && source.type === 'section';

    if (isDraggingSection) {
        return ratio < 0.5 ? 'before' : 'after';
    }

    // If target is a canvas container, allow inside drops in the center
    if (CANVAS_TYPES.has(node.type) && node.isCanvas) {
        if (ratio < DROP_ZONE_EDGE_RATIO) {
            return 'before';
        }

        if (ratio > 1 - DROP_ZONE_EDGE_RATIO) {
            return 'after';
        }

        return 'inside';
    }

    // Non-canvas: only before/after
    return ratio < 0.5 ? 'before' : 'after';
}

// ---------------------------------------------------------------------------
// Compute drop indicator rect
// ---------------------------------------------------------------------------

export function computeDropIndicatorRect(
    position: DropPosition,
    targetRect: DOMRect,
): { top: number; left: number; width: number; height: number } {
    switch (position) {
        case 'before':
            return {
                top: targetRect.top - INDICATOR_HEIGHT / 2,
                left: targetRect.left,
                width: targetRect.width,
                height: INDICATOR_HEIGHT,
            };
        case 'after':
            return {
                top: targetRect.bottom - INDICATOR_HEIGHT / 2,
                left: targetRect.left,
                width: targetRect.width,
                height: INDICATOR_HEIGHT,
            };
        case 'inside':
            return {
                top: targetRect.top,
                left: targetRect.left,
                width: targetRect.width,
                height: targetRect.height,
            };
    }
}

// ---------------------------------------------------------------------------
// Validate drop target
// ---------------------------------------------------------------------------

export function isValidDropTarget(
    source: DragSource,
    targetNodeId: AstNodeId,
    position: DropPosition,
    nodes: AstNodeMap,
): boolean {
    // --- Section-type drags: must resolve to a direct child of ROOT ---
    if (source.kind === 'new' && source.type === 'section') {
        if (position === 'inside') {
            // Sections are only allowed as direct children of ROOT
            return targetNodeId === ROOT_NODE_ID;
        }

        // For before/after: walk up to find the top-level section ancestor
        // so the resolved parent is ROOT.
        const resolved = resolveDropTarget(targetNodeId, position, nodes);

        return !!resolved && resolved.parentId === ROOT_NODE_ID;
    }

    // Can always drop new blocks
    if (source.kind === 'new') {
        if (position === 'inside') {
            const target = nodes[targetNodeId];

            return !!target && CANVAS_TYPES.has(target.type) && target.isCanvas;
        }

        return true;
    }

    // Existing node: prevent dropping onto itself or its own descendants
    const draggedId = source.nodeId;

    if (targetNodeId === draggedId) {
        return false;
    }

    if (isAncestor(nodes, draggedId, targetNodeId)) {
        return false;
    }

    if (position === 'inside') {
        const target = nodes[targetNodeId];

        return !!target && CANVAS_TYPES.has(target.type) && target.isCanvas;
    }

    return true;
}

// ---------------------------------------------------------------------------
// Resolve final parent + index from position
// ---------------------------------------------------------------------------

export function resolveDropTarget(
    targetNodeId: AstNodeId,
    position: DropPosition,
    nodes: AstNodeMap,
): { parentId: AstNodeId; index: number } | null {
    const target = nodes[targetNodeId];

    if (!target) {
        return null;
    }

    if (position === 'inside') {
        return { parentId: targetNodeId, index: (target.childIds ?? []).length };
    }

    // before/after: insert relative to target in its parent
    if (!target.parentId) {
        return null;
    }

    const parent = nodes[target.parentId];

    if (!parent) {
        return null;
    }

    const idx = parent.childIds.indexOf(targetNodeId);

    if (idx === -1) {
        return null;
    }

    return {
        parentId: target.parentId,
        index: position === 'before' ? idx : idx + 1,
    };
}

// ---------------------------------------------------------------------------
// DragDrop controller class
// ---------------------------------------------------------------------------

/**
 * Walk up from `nodeId` to find the direct child of ROOT.
 * Returns `nodeId` itself if it IS already a direct child of ROOT.
 */
function findTopLevelAncestor(nodeId: AstNodeId, nodes: AstNodeMap): AstNodeId | null {
    let current = nodeId;

    while (current) {
        const node = nodes[current];

        if (!node) {
            return null;
        }

        if (node.parentId === ROOT_NODE_ID) {
            return current;
        }

        if (!node.parentId) {
            return null;
        }

        current = node.parentId;
    }

    return null;
}

export class BuilderDragDrop {
    private iframe: HTMLIFrameElement;
    private callbacks: DragDropCallbacks;
    private nodes: AstNodeMap = {};
    private activeDrag: DragSource | null = null;
    private boundDragOver: ((e: DragEvent) => void) | null = null;
    private boundDrop: ((e: DragEvent) => void) | null = null;
    private boundDragEnd: ((e: DragEvent) => void) | null = null;
    private boundDragLeave: ((e: DragEvent) => void) | null = null;

    /**
     * Most recent indicator (may be null when cursor leaves elements).
     */
    private lastIndicator: DropIndicator | null = null;

    /**
     * Most recent *valid* indicator — never cleared by null updates.
     * Used as a fallback when the browser fires dragleave/null before
     * the drop event reaches us (common with cross-document iframe DnD).
     */
    private lastValidIndicator: DropIndicator | null = null;

    /**
     * Whether performDrop already executed for the current drag session.
     * Prevents double-drops from both `drop` and `dragend` handlers.
     */
    private dropExecuted = false;

    constructor(iframe: HTMLIFrameElement, callbacks: DragDropCallbacks) {
        this.iframe = iframe;
        this.callbacks = callbacks;
    }

    // ---------------------------------------------------------------------------
    // State sync
    // ---------------------------------------------------------------------------

    updateNodes(nodes: AstNodeMap): void {
        this.nodes = nodes;
    }

    // ---------------------------------------------------------------------------
    // Start drag from canvas (existing node)
    // ---------------------------------------------------------------------------

    startCanvasDrag(nodeId: AstNodeId): void {
        this.dropExecuted = false;
        this.lastValidIndicator = null;
        this.activeDrag = { kind: 'existing', nodeId };
        this.callbacks.onDragStart(this.activeDrag);
        this.bindIframeListeners();
    }

    // ---------------------------------------------------------------------------
    // Start drag from component panel (new component)
    // ---------------------------------------------------------------------------

    startPanelDrag(html: string, type: string, displayName: string): void {
        this.dropExecuted = false;
        this.lastValidIndicator = null;
        this.activeDrag = { kind: 'new', html, type, displayName };
        this.callbacks.onDragStart(this.activeDrag);
        this.bindIframeListeners();
    }

    // ---------------------------------------------------------------------------
    // Programmatic drop computation (for mouseMove-based approach)
    // ---------------------------------------------------------------------------

    handleDragMove(clientX: number, clientY: number): void {
        if (!this.activeDrag) {
            return;
        }

        const iframeDoc = this.iframe.contentDocument;

        if (!iframeDoc) {
            return;
        }

        // Convert parent coordinates to iframe coordinates
        const iframeRect = this.iframe.getBoundingClientRect();
        const iframeX = clientX - iframeRect.left;
        const iframeY = clientY - iframeRect.top;

        // Find element under cursor in iframe
        const el = iframeDoc.elementFromPoint(iframeX, iframeY);

        if (!el) {
            this.callbacks.onDragMove(null);

            return;
        }

        const nodeId = getAstIdFromElement(el as HTMLElement);

        if (!nodeId || nodeId === ROOT_NODE_ID) {
            // Over the content area but not a specific node — drop as last child of root
            const contentArea = getContentArea(iframeDoc);

            if (contentArea && contentArea.contains(el)) {
                const root = this.nodes[ROOT_NODE_ID];
                const rect = contentArea.getBoundingClientRect();
                const indicator: DropIndicator = {
                    targetId: ROOT_NODE_ID,
                    position: 'inside',
                    rect: { top: rect.bottom - 2, left: rect.left, width: rect.width, height: INDICATOR_HEIGHT },
                    isValid: true,
                    parentId: ROOT_NODE_ID,
                    index: root ? root.childIds.length : 0,
                };
                this.callbacks.onDragMove(indicator);
            } else {
                this.callbacks.onDragMove(null);
            }

            return;
        }

        const targetEl = getElementByAstId(iframeDoc, nodeId);

        if (!targetEl) {
            this.callbacks.onDragMove(null);

            return;
        }

        const targetRect = targetEl.getBoundingClientRect();
        const position = computeDropPosition(iframeY, targetRect, nodeId, this.nodes, this.activeDrag);
        const isValid = isValidDropTarget(this.activeDrag, nodeId, position, this.nodes);

        // For section drags over deeply nested elements: redirect to the
        // top-level section ancestor so the indicator shows at the section
        // boundary and the drop resolves to ROOT as parent.
        const isDraggingSection = this.activeDrag.kind === 'new' && this.activeDrag.type === 'section';

        if (isDraggingSection && !isValid) {
            const topLevel = findTopLevelAncestor(nodeId, this.nodes);

            if (topLevel) {
                const topEl = getElementByAstId(iframeDoc, topLevel);

                if (topEl) {
                    const topRect = topEl.getBoundingClientRect();
                    const topPos = computeDropPosition(iframeY, topRect, topLevel, this.nodes, this.activeDrag);
                    const topResolved = resolveDropTarget(topLevel, topPos, this.nodes);
                    const topIndicator: DropIndicator = {
                        targetId: topLevel,
                        position: topPos,
                        rect: computeDropIndicatorRect(topPos, topRect),
                        isValid: !!topResolved && topResolved.parentId === ROOT_NODE_ID,
                        parentId: topResolved?.parentId ?? null,
                        index: topResolved?.index ?? 0,
                    };
                    this.callbacks.onDragMove(topIndicator);

                    return;
                }
            }
        }

        const resolved = resolveDropTarget(nodeId, position, this.nodes);

        const indicator: DropIndicator = {
            targetId: nodeId,
            position,
            rect: computeDropIndicatorRect(position, targetRect),
            isValid: isValid && resolved !== null,
            parentId: resolved?.parentId ?? null,
            index: resolved?.index ?? 0,
        };

        this.callbacks.onDragMove(indicator);
    }

    /**
     * Called when a drop or dragend fires. Attempts to place the dragged
     * item using the best available indicator.
     */
    handleDrop(): void {
        this.performDrop();
    }

    /**
     * Called when the drag is explicitly cancelled (e.g. Escape key,
     * dragging completely off-screen). Does NOT attempt a drop.
     */
    cancelDrag(): void {
        this.activeDrag = null;
        this.lastIndicator = null;
        this.lastValidIndicator = null;
        this.dropExecuted = false;
        this.unbindIframeListeners();
        this.callbacks.onDragEnd();
    }

    endDrag(): void {
        this.activeDrag = null;
        this.lastIndicator = null;
        this.lastValidIndicator = null;
        this.dropExecuted = false;
        this.unbindIframeListeners();
        this.callbacks.onDragEnd();
    }

    isActive(): boolean {
        return this.activeDrag !== null;
    }

    // ---------------------------------------------------------------------------
    // iframe event bindings (for HTML5 native drag within iframe)
    // ---------------------------------------------------------------------------

    private bindIframeListeners(): void {
        const iframeDoc = this.iframe.contentDocument;

        if (!iframeDoc) {
            return;
        }

        this.boundDragOver = (e: DragEvent) => {
            e.preventDefault();

            if (e.dataTransfer) {
                e.dataTransfer.dropEffect = 'move';
            }

            // Use iframe-relative coordinates
            this.handleDragMoveInIframe(e.clientX, e.clientY);
        };

        this.boundDrop = (e: DragEvent) => {
            e.preventDefault();
            e.stopPropagation();
            this.performDrop();
        };

        this.boundDragEnd = () => {
            // dragend in iframe — attempt drop with last valid indicator
            this.performDrop();
        };

        this.boundDragLeave = (e: DragEvent) => {
            // Only clear indicator if leaving the iframe entirely
            if (!e.relatedTarget || !iframeDoc.contains(e.relatedTarget as Node)) {
                this.callbacks.onDragMove(null);
            }
        };

        iframeDoc.addEventListener('dragover', this.boundDragOver);
        iframeDoc.addEventListener('drop', this.boundDrop);
        iframeDoc.addEventListener('dragend', this.boundDragEnd);
        iframeDoc.addEventListener('dragleave', this.boundDragLeave);
    }

    private unbindIframeListeners(): void {
        const iframeDoc = this.iframe.contentDocument;

        if (!iframeDoc) {
            return;
        }

        if (this.boundDragOver) {
            iframeDoc.removeEventListener('dragover', this.boundDragOver);
        }

        if (this.boundDrop) {
            iframeDoc.removeEventListener('drop', this.boundDrop);
        }

        if (this.boundDragEnd) {
            iframeDoc.removeEventListener('dragend', this.boundDragEnd);
        }

        if (this.boundDragLeave) {
            iframeDoc.removeEventListener('dragleave', this.boundDragLeave);
        }

        this.boundDragOver = null;
        this.boundDrop = null;
        this.boundDragEnd = null;
        this.boundDragLeave = null;
    }

    private handleDragMoveInIframe(iframeX: number, iframeY: number): void {
        if (!this.activeDrag) {
            return;
        }

        const iframeDoc = this.iframe.contentDocument;

        if (!iframeDoc) {
            return;
        }

        const el = iframeDoc.elementFromPoint(iframeX, iframeY);

        if (!el) {
            this.callbacks.onDragMove(null);

            return;
        }

        const nodeId = getAstIdFromElement(el as HTMLElement);

        if (!nodeId || nodeId === ROOT_NODE_ID) {
            const contentArea = getContentArea(iframeDoc);

            if (contentArea && contentArea.contains(el)) {
                const root = this.nodes[ROOT_NODE_ID];
                const rect = contentArea.getBoundingClientRect();
                const indicator: DropIndicator = {
                    targetId: ROOT_NODE_ID,
                    position: 'inside',
                    rect: { top: rect.bottom - 2, left: rect.left, width: rect.width, height: INDICATOR_HEIGHT },
                    isValid: true,
                    parentId: ROOT_NODE_ID,
                    index: root ? root.childIds.length : 0,
                };
                this.callbacks.onDragMove(indicator);
            } else {
                this.callbacks.onDragMove(null);
            }

            return;
        }

        const targetEl = getElementByAstId(iframeDoc, nodeId);

        if (!targetEl) {
            this.callbacks.onDragMove(null);

            return;
        }

        const targetRect = targetEl.getBoundingClientRect();
        const position = computeDropPosition(iframeY, targetRect, nodeId, this.nodes, this.activeDrag);
        const isValid = isValidDropTarget(this.activeDrag, nodeId, position, this.nodes);

        // For section drags: redirect to top-level section ancestor
        const isDraggingSection = this.activeDrag.kind === 'new' && this.activeDrag.type === 'section';

        if (isDraggingSection && !isValid) {
            const topLevel = findTopLevelAncestor(nodeId, this.nodes);

            if (topLevel) {
                const topEl = getElementByAstId(iframeDoc, topLevel);

                if (topEl) {
                    const topRect = topEl.getBoundingClientRect();
                    const topPos = computeDropPosition(iframeY, topRect, topLevel, this.nodes, this.activeDrag);
                    const topResolved = resolveDropTarget(topLevel, topPos, this.nodes);
                    const topIndicator: DropIndicator = {
                        targetId: topLevel,
                        position: topPos,
                        rect: computeDropIndicatorRect(topPos, topRect),
                        isValid: !!topResolved && topResolved.parentId === ROOT_NODE_ID,
                        parentId: topResolved?.parentId ?? null,
                        index: topResolved?.index ?? 0,
                    };
                    this.callbacks.onDragMove(topIndicator);

                    return;
                }
            }
        }

        const resolved = resolveDropTarget(nodeId, position, this.nodes);

        const indicator: DropIndicator = {
            targetId: nodeId,
            position,
            rect: computeDropIndicatorRect(position, targetRect),
            isValid: isValid && resolved !== null,
            parentId: resolved?.parentId ?? null,
            index: resolved?.index ?? 0,
        };

        this.callbacks.onDragMove(indicator);
    }

    /**
     * Store last computed indicator (used by overlays for position updates).
     */
    setLastIndicator(indicator: DropIndicator | null): void {
        this.lastIndicator = indicator;

        // Preserve last valid indicator so it survives null updates from
        // dragleave events that fire just before the drag ends.
        if (indicator && indicator.isValid && indicator.parentId) {
            this.lastValidIndicator = indicator;
        }
    }

    private performDrop(): void {
        // Guard against double-execution (both drop + dragend may fire)
        if (this.dropExecuted) {
            return;
        }

        if (!this.activeDrag) {
            this.endDrag();

            return;
        }

        // Use lastIndicator if valid, otherwise fall back to lastValidIndicator
        const indicator = (this.lastIndicator?.isValid && this.lastIndicator?.parentId)
            ? this.lastIndicator
            : this.lastValidIndicator;

        if (!indicator || !indicator.isValid || !indicator.parentId) {
            this.endDrag();

            return;
        }

        this.dropExecuted = true;
        const { parentId, index } = indicator;

        if (this.activeDrag.kind === 'existing') {
            this.callbacks.onDropExisting(this.activeDrag.nodeId, parentId, index);
        } else {
            this.callbacks.onDropNew(this.activeDrag.html, parentId, index, this.activeDrag.type, this.activeDrag.displayName);
        }

        this.endDrag();
    }

    // ---------------------------------------------------------------------------
    // Cleanup
    // ---------------------------------------------------------------------------

    destroy(): void {
        this.unbindIframeListeners();
        this.activeDrag = null;
    }
}
