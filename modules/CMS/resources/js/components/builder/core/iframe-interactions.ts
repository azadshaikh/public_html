/**
 * Iframe interaction handler.
 *
 * Listens inside the iframe for:
 *   - mousemove  → hover events (ast node highlight)
 *   - click      → select events
 *   - keydown    → keyboard shortcuts (delete, esc, arrows)
 *   - links/forms → prevent navigation
 *
 * Maps DOM events to AST node IDs via data-ast-id attributes.
 * Replaces the old builder-preview-interactions.ts.
 */

import type { AstNodeId } from './ast-types';
import { ROOT_NODE_ID } from './ast-types';
import { getAstIdFromElement } from './iframe-sync';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export type IframeInteractionCallbacks = {
    onHover: (nodeId: AstNodeId | null) => void;
    onSelect: (nodeId: AstNodeId | null) => void;
    onDelete: (nodeId: AstNodeId) => void;
    onEscape: () => void;
    onMoveUp: (nodeId: AstNodeId) => void;
    onMoveDown: (nodeId: AstNodeId) => void;
    onUndo: () => void;
    onRedo: () => void;
    onSave: () => void;
};

// ---------------------------------------------------------------------------
// Interaction handler class
// ---------------------------------------------------------------------------

export class IframeInteractionHandler {
    private iframe: HTMLIFrameElement;
    private callbacks: IframeInteractionCallbacks;
    private selectedId: AstNodeId | null = null;
    private abortController: AbortController | null = null;

    constructor(iframe: HTMLIFrameElement, callbacks: IframeInteractionCallbacks) {
        this.iframe = iframe;
        this.callbacks = callbacks;
    }

    // ---------------------------------------------------------------------------
    // Bind / unbind
    // ---------------------------------------------------------------------------

    bind(): void {
        this.unbind();

        const iframeDoc = this.iframe.contentDocument;

        if (!iframeDoc) {
            return;
        }

        this.abortController = new AbortController();
        const { signal } = this.abortController;

        // Mousemove → hover
        iframeDoc.addEventListener('mousemove', this.handleMouseMove, { signal, passive: true });

        // Click → select
        iframeDoc.addEventListener('click', this.handleClick, { signal });

        // Keyboard shortcuts
        iframeDoc.addEventListener('keydown', this.handleKeyDown, { signal });

        // Protect links and forms
        iframeDoc.addEventListener('click', this.protectLinks, { signal, capture: true });
        iframeDoc.addEventListener('submit', this.protectForms, { signal, capture: true });
    }

    unbind(): void {
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }
    }

    // ---------------------------------------------------------------------------
    // State sync
    // ---------------------------------------------------------------------------

    setSelectedId(id: AstNodeId | null): void {
        this.selectedId = id;
    }

    // ---------------------------------------------------------------------------
    // Event handlers
    // ---------------------------------------------------------------------------

    private handleMouseMove = (e: MouseEvent): void => {
        const el = e.target as HTMLElement;
        const nodeId = getAstIdFromElement(el);

        // Don't hover the root node itself
        if (nodeId === ROOT_NODE_ID) {
            this.callbacks.onHover(null);

            return;
        }

        this.callbacks.onHover(nodeId);
    };

    private handleClick = (e: MouseEvent): void => {
        const el = e.target as HTMLElement;
        const nodeId = getAstIdFromElement(el);

        e.preventDefault();
        e.stopPropagation();

        if (!nodeId || nodeId === ROOT_NODE_ID) {
            this.callbacks.onSelect(null);

            return;
        }

        this.callbacks.onSelect(nodeId);
    };

    private handleKeyDown = (e: KeyboardEvent): void => {
        // Delete / Backspace → delete selected node
        if ((e.key === 'Delete' || e.key === 'Backspace') && this.selectedId) {
            e.preventDefault();
            this.callbacks.onDelete(this.selectedId);

            return;
        }

        // Escape → deselect
        if (e.key === 'Escape') {
            e.preventDefault();
            this.callbacks.onEscape();

            return;
        }

        // Arrow Up → move selected node up
        if (e.key === 'ArrowUp' && (e.metaKey || e.ctrlKey) && this.selectedId) {
            e.preventDefault();
            this.callbacks.onMoveUp(this.selectedId);

            return;
        }

        // Arrow Down → move selected node down
        if (e.key === 'ArrowDown' && (e.metaKey || e.ctrlKey) && this.selectedId) {
            e.preventDefault();
            this.callbacks.onMoveDown(this.selectedId);

            return;
        }

        // Undo / Redo / Save (Ctrl+Z, Ctrl+Y, Ctrl+S)
        const mod = e.ctrlKey || e.metaKey;

        if (mod && e.key.toLowerCase() === 'z' && !e.shiftKey) {
            e.preventDefault();
            this.callbacks.onUndo();

            return;
        }

        if (mod && e.key.toLowerCase() === 'y') {
            e.preventDefault();
            this.callbacks.onRedo();

            return;
        }

        if (mod && e.key.toLowerCase() === 's') {
            e.preventDefault();
            this.callbacks.onSave();

            return;
        }
    };

    private protectLinks = (e: MouseEvent): void => {
        const link = (e.target as HTMLElement).closest('a');

        if (link) {
            e.preventDefault();
        }
    };

    private protectForms = (e: Event): void => {
        e.preventDefault();
        e.stopPropagation();
    };

    // ---------------------------------------------------------------------------
    // Cleanup
    // ---------------------------------------------------------------------------

    destroy(): void {
        this.unbind();
    }
}
