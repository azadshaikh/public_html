/**
 * Overlay engine for the builder.
 *
 * Renders hover/selection boxes, section toolbars, drop indicators,
 * and insert buttons as an overlay layer positioned over the iframe.
 * This follows the craft.js pattern: overlays are in the parent document,
 * positioned by reading the iframe element rects.
 *
 * The overlay container sits on top of the iframe with pointer-events: none,
 * except for interactive elements (toolbar buttons, insert buttons).
 */

import type { AstNodeId, AstNodeMap, AstNodeType, DropIndicator } from './ast-types';
import { getElementByAstId } from './iframe-sync';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export type OverlayCallbacks = {
    onMoveNode: (nodeId: AstNodeId, direction: 'up' | 'down') => void;
    onDuplicateNode: (nodeId: AstNodeId) => void;
    onDeleteNode: (nodeId: AstNodeId) => void;
    onInsertAt: (parentId: AstNodeId, index: number) => void;
    onStartDrag: (nodeId: AstNodeId) => void;
    onSelect?: (nodeId: AstNodeId) => void;
    onViewCode?: (nodeId: AstNodeId) => void;
};

type OverlayState = {
    hoveredId: AstNodeId | null;
    selectedIds: AstNodeId[];
    dropIndicator: DropIndicator | null;
    nodes: AstNodeMap;
    rootNodeId: AstNodeId;
};

// ---------------------------------------------------------------------------
// Overlay class
// ---------------------------------------------------------------------------

export class BuilderOverlay {
    private container: HTMLDivElement;
    private hoverBox: HTMLDivElement;
    private selectBox: HTMLDivElement;
    private hoverLabel: HTMLDivElement;
    private selectLabel: HTMLDivElement;
    private toolbar: HTMLDivElement;
    private dropLine: HTMLDivElement;
    private insertButtons: HTMLDivElement[];
    private sectionBoundaries: HTMLDivElement[];
    private iframe: HTMLIFrameElement;
    private callbacks: OverlayCallbacks;
    private currentState: OverlayState;
    private animFrameId: number | null = null;
    private scrollRafId: number | null = null;
    private iframeScrollHandler: (() => void) | null = null;
    private iframeResizeHandler: (() => void) | null = null;
    private parentResizeObserver: ResizeObserver | null = null;

    constructor(
        parentElement: HTMLElement,
        iframe: HTMLIFrameElement,
        callbacks: OverlayCallbacks,
    ) {
        this.iframe = iframe;
        this.callbacks = callbacks;
        this.insertButtons = [];
        this.sectionBoundaries = [];
        this.currentState = {
            hoveredId: null,
            selectedIds: [],
            dropIndicator: null,
            nodes: {},
            rootNodeId: '',
        };

        // Create overlay container
        this.container = document.createElement('div');
        this.container.className = 'builder-overlay-root';
        this.container.style.cssText = 'position:absolute;inset:0;pointer-events:none;z-index:1000;';

        // Hover box
        this.hoverBox = this.createBox('builder-hover-box', '2px dashed rgba(59,130,246,0.6)');
        this.hoverLabel = this.createLabel('builder-hover-label', 'rgba(59,130,246,0.85)');
        this.hoverBox.appendChild(this.hoverLabel);

        // Selection box
        this.selectBox = this.createBox('builder-select-box', '2px solid rgba(59,130,246,0.85)');
        this.selectLabel = this.createLabel('builder-select-label', 'rgba(59,130,246,0.9)');
        this.selectBox.appendChild(this.selectLabel);

        // Toolbar
        this.toolbar = this.createToolbar();

        // Drop indicator
        this.dropLine = document.createElement('div');
        this.dropLine.className = 'builder-drop-indicator';
        this.dropLine.style.cssText = 'position:absolute;display:none;background:rgb(59,130,246);z-index:1010;pointer-events:none;border-radius:1px;transition:top 60ms ease,left 60ms ease,width 60ms ease;';

        this.container.append(this.hoverBox, this.selectBox, this.toolbar, this.dropLine);
        parentElement.appendChild(this.container);

        this.bindScrollListeners();
    }

    // ---------------------------------------------------------------------------
    // Scroll / resize listeners — re-render overlay when iframe content scrolls
    // ---------------------------------------------------------------------------

    private bindScrollListeners(): void {
        const scheduleRender = () => {
            if (this.scrollRafId !== null) {
                return;
            }

            this.scrollRafId = requestAnimationFrame(() => {
                this.scrollRafId = null;
                this.render();
            });
        };

        this.iframeScrollHandler = scheduleRender;
        this.iframeResizeHandler = scheduleRender;

        // Listen for scroll/resize inside the iframe document
        const iframeDoc = this.iframe.contentDocument;

        if (iframeDoc) {
            iframeDoc.addEventListener('scroll', scheduleRender, { passive: true });
            const iframeWin = this.iframe.contentWindow;

            if (iframeWin) {
                iframeWin.addEventListener('resize', scheduleRender, { passive: true });
                iframeWin.addEventListener('scroll', scheduleRender, { passive: true });
            }
        }

        // Observe changes to the iframe element size (parent layout changes)
        this.parentResizeObserver = new ResizeObserver(scheduleRender);
        this.parentResizeObserver.observe(this.iframe);
    }

    private unbindScrollListeners(): void {
        const iframeDoc = this.iframe.contentDocument;

        if (iframeDoc && this.iframeScrollHandler) {
            iframeDoc.removeEventListener('scroll', this.iframeScrollHandler);
        }

        const iframeWin = this.iframe.contentWindow;

        if (iframeWin) {
            if (this.iframeResizeHandler) {
                iframeWin.removeEventListener('resize', this.iframeResizeHandler);
            }

            if (this.iframeScrollHandler) {
                iframeWin.removeEventListener('scroll', this.iframeScrollHandler);
            }
        }

        if (this.parentResizeObserver) {
            this.parentResizeObserver.disconnect();
            this.parentResizeObserver = null;
        }

        if (this.scrollRafId !== null) {
            cancelAnimationFrame(this.scrollRafId);
            this.scrollRafId = null;
        }
    }

    // ---------------------------------------------------------------------------
    // Update
    // ---------------------------------------------------------------------------

    update(state: OverlayState): void {
        this.currentState = state;

        if (this.animFrameId !== null) {
            cancelAnimationFrame(this.animFrameId);
        }

        this.animFrameId = requestAnimationFrame(() => {
            this.render();
            this.animFrameId = null;
        });
    }

    private render(): void {
        const iframeDoc = this.iframe.contentDocument;

        if (!iframeDoc) {
            this.hideAll();

            return;
        }

        const iframeRect = this.iframe.getBoundingClientRect();
        const containerRect = this.container.getBoundingClientRect();
        const offsetX = iframeRect.left - containerRect.left;
        const offsetY = iframeRect.top - containerRect.top;

        const { hoveredId, selectedIds, dropIndicator, nodes } = this.currentState;

        // Hover box
        if (hoveredId && !selectedIds.includes(hoveredId)) {
            this.positionBox(this.hoverBox, this.hoverLabel, hoveredId, iframeDoc, offsetX, offsetY, nodes);
        } else {
            this.hoverBox.style.display = 'none';
        }

        // Selection box
        if (selectedIds.length > 0) {
            const selectedId = selectedIds[0];
            this.positionBox(this.selectBox, this.selectLabel, selectedId, iframeDoc, offsetX, offsetY, nodes);
            this.positionToolbar(selectedId, iframeDoc, offsetX, offsetY);
        } else {
            this.selectBox.style.display = 'none';
            this.toolbar.style.display = 'none';
        }

        // Drop indicator
        if (dropIndicator) {
            this.dropLine.style.display = 'block';
            this.dropLine.style.top = `${dropIndicator.rect.top + offsetY}px`;
            this.dropLine.style.left = `${dropIndicator.rect.left + offsetX}px`;
            this.dropLine.style.width = `${dropIndicator.rect.width}px`;
            this.dropLine.style.height = `${dropIndicator.rect.height}px`;
            this.dropLine.style.background = dropIndicator.isValid ? 'rgb(59,130,246)' : 'rgb(239,68,68)';
        } else {
            this.dropLine.style.display = 'none';
        }

        // Insert buttons
        this.updateInsertButtons(iframeDoc, offsetX, offsetY);

        // Section boundary lines
        this.updateSectionBoundaries(iframeDoc, offsetX, offsetY);
    }

    private positionBox(
        box: HTMLDivElement,
        label: HTMLDivElement,
        nodeId: AstNodeId,
        iframeDoc: Document,
        offsetX: number,
        offsetY: number,
        nodes: AstNodeMap,
    ): void {
        const el = getElementByAstId(iframeDoc, nodeId);

        if (!el) {
            box.style.display = 'none';

            return;
        }

        const rect = el.getBoundingClientRect();

        box.style.display = 'block';
        box.style.top = `${rect.top + offsetY}px`;
        box.style.left = `${rect.left + offsetX}px`;
        box.style.width = `${rect.width}px`;
        box.style.height = `${rect.height}px`;

        // Label
        const node = nodes[nodeId];

        if (node) {
            label.textContent = node.displayName || node.type;
            label.style.display = 'block';
        }
    }

    private positionToolbar(
        nodeId: AstNodeId,
        iframeDoc: Document,
        offsetX: number,
        offsetY: number,
    ): void {
        const el = getElementByAstId(iframeDoc, nodeId);

        if (!el) {
            this.toolbar.style.display = 'none';

            return;
        }

        const rect = el.getBoundingClientRect();

        this.toolbar.style.display = 'flex';
        this.toolbar.style.top = `${Math.max(0, rect.top + offsetY - 30)}px`;
        this.toolbar.style.left = `${rect.right + offsetX - this.toolbar.offsetWidth}px`;

        // Update button states
        this.toolbar.dataset.nodeId = nodeId;

        const node = this.currentState.nodes[nodeId];

        if (node?.parentId) {
            const parent = this.currentState.nodes[node.parentId];

            if (parent) {
                const idx = parent.childIds.indexOf(nodeId);
                const upBtn = this.toolbar.querySelector<HTMLButtonElement>('[data-action="moveUp"]');
                const downBtn = this.toolbar.querySelector<HTMLButtonElement>('[data-action="moveDown"]');
                const isFirst = idx <= 0;
                const isLast = idx >= parent.childIds.length - 1;

                if (upBtn) {
                    upBtn.style.opacity = isFirst ? '0.35' : '1';
                    upBtn.style.pointerEvents = isFirst ? 'none' : 'auto';
                    upBtn.title = isFirst ? 'Already first child' : 'Move up';
                }

                if (downBtn) {
                    downBtn.style.opacity = isLast ? '0.35' : '1';
                    downBtn.style.pointerEvents = isLast ? 'none' : 'auto';
                    downBtn.title = isLast ? 'Already last child' : 'Move down';
                }
            }
        }
    }

    private updateInsertButtons(iframeDoc: Document, offsetX: number, offsetY: number): void {
        const { nodes, rootNodeId, selectedIds } = this.currentState;
        const root = nodes[rootNodeId];

        if (!root) {
            return;
        }

        // Only show insert buttons when something is selected or hovered
        const shouldShow = selectedIds.length > 0;

        // Remove old buttons
        for (const btn of this.insertButtons) {
            btn.remove();
        }

        this.insertButtons = [];

        if (!shouldShow) {
            return;
        }

        // Show insert buttons between top-level children of root
        const childIds = root.childIds;

        for (let i = 0; i <= childIds.length; i++) {
            const insertBtn = this.createInsertButton(rootNodeId, i);

            // Position between elements
            if (i < childIds.length) {
                const el = getElementByAstId(iframeDoc, childIds[i]);

                if (el) {
                    const rect = el.getBoundingClientRect();
                    insertBtn.style.top = `${rect.top + offsetY - 12}px`;
                    insertBtn.style.left = `${rect.left + offsetX}px`;
                    insertBtn.style.width = `${rect.width}px`;
                }
            } else if (childIds.length > 0) {
                const lastEl = getElementByAstId(iframeDoc, childIds[childIds.length - 1]);

                if (lastEl) {
                    const rect = lastEl.getBoundingClientRect();
                    insertBtn.style.top = `${rect.bottom + offsetY}px`;
                    insertBtn.style.left = `${rect.left + offsetX}px`;
                    insertBtn.style.width = `${rect.width}px`;
                }
            }

            this.container.appendChild(insertBtn);
            this.insertButtons.push(insertBtn);
        }
    }

    // ---------------------------------------------------------------------------
    // Element creation helpers
    // ---------------------------------------------------------------------------

    private createBox(className: string, border: string): HTMLDivElement {
        const box = document.createElement('div');
        box.className = className;
        box.style.cssText = `position:absolute;display:none;outline:${border};outline-offset:-1px;pointer-events:none;z-index:1005;transition:top 60ms ease,left 60ms ease,width 60ms ease,height 60ms ease;`;

        return box;
    }

    private createLabel(className: string, bg: string): HTMLDivElement {
        const label = document.createElement('div');
        label.className = className;
        label.style.cssText = `position:absolute;top:-1px;left:8px;transform:translateY(-100%);display:none;background:${bg};color:#fff;font-size:10px;font-family:system-ui,-apple-system,sans-serif;font-weight:600;line-height:1;padding:3px 8px;border-radius:4px 4px 0 0;white-space:nowrap;pointer-events:none;`;

        return label;
    }

    private createToolbar(): HTMLDivElement {
        const toolbar = document.createElement('div');
        toolbar.className = 'builder-ast-toolbar';
        toolbar.style.cssText = 'position:absolute;display:none;align-items:center;gap:2px;background:rgba(59,130,246,0.9);padding:2px 4px;border-radius:4px;z-index:1010;font-family:system-ui,-apple-system,sans-serif;pointer-events:auto;';

        const svgAttrs = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

        const icons: Record<string, string> = {
            drag: `<svg ${svgAttrs} style="width:14px;height:14px"><circle cx="9" cy="12" r="1"/><circle cx="9" cy="5" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="19" r="1"/></svg>`,
            moveUp: `<svg ${svgAttrs} style="width:14px;height:14px"><path d="m5 12 7-7 7 7"/><path d="M12 19V5"/></svg>`,
            moveDown: `<svg ${svgAttrs} style="width:14px;height:14px"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>`,
            code: `<svg ${svgAttrs} style="width:14px;height:14px"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>`,
            duplicate: `<svg ${svgAttrs} style="width:14px;height:14px"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>`,
            remove: `<svg ${svgAttrs} style="width:14px;height:14px"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>`,
        };

        const btnStyle = 'display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;padding:0;margin:0;border:none;border-radius:3px;background:transparent;color:rgba(255,255,255,0.85);cursor:pointer;';

        const makeBtn = (action: string, title: string, icon: string): string =>
            `<button data-action="${action}" title="${title}" style="${btnStyle}">${icon}</button>`;

        const sep = '<div style="width:1px;height:16px;background:rgba(255,255,255,0.3);margin:0 2px;"></div>';

        toolbar.innerHTML = [
            makeBtn('drag', 'Drag to reorder', icons.drag),
            sep,
            makeBtn('moveUp', 'Move up', icons.moveUp),
            makeBtn('moveDown', 'Move down', icons.moveDown),
            sep,
            makeBtn('code', 'View code', icons.code),
            makeBtn('duplicate', 'Duplicate', icons.duplicate),
            makeBtn('remove', 'Delete', icons.remove),
        ].join('');

        // Make drag button draggable for HTML5 drag-and-drop
        const dragBtn = toolbar.querySelector<HTMLButtonElement>('[data-action="drag"]');

        if (dragBtn) {
            dragBtn.draggable = true;
            dragBtn.style.cursor = 'grab';
        }

        // Event delegation — mousedown for button actions (excluding drag)
        toolbar.addEventListener('mousedown', (e) => {
            const target = (e.target as HTMLElement).closest<HTMLButtonElement>('button');

            if (!target) {
                return;
            }

            // Let the drag button handle its own dragstart event
            if (target.dataset.action === 'drag') {
                return;
            }

            e.stopPropagation();
            e.preventDefault();

            const nodeId = toolbar.dataset.nodeId;

            if (!nodeId) {
                return;
            }

            switch (target.dataset.action) {
                case 'moveUp':
                    this.callbacks.onMoveNode(nodeId, 'up');
                    break;
                case 'moveDown':
                    this.callbacks.onMoveNode(nodeId, 'down');
                    break;
                case 'code':
                    this.callbacks.onViewCode?.(nodeId);
                    break;
                case 'duplicate':
                    this.callbacks.onDuplicateNode(nodeId);
                    break;
                case 'remove':
                    this.callbacks.onDeleteNode(nodeId);
                    break;
            }
        });

        // Drag-start handler for the drag button (HTML5 native drag)
        toolbar.addEventListener('dragstart', (e) => {
            const target = (e.target as HTMLElement).closest<HTMLButtonElement>('button[data-action="drag"]');

            if (!target) {
                return;
            }

            const nodeId = toolbar.dataset.nodeId;

            if (!nodeId) {
                return;
            }

            e.dataTransfer?.setData('text/plain', nodeId);

            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
            }

            this.callbacks.onStartDrag(nodeId);
        });

        return toolbar;
    }

    private createInsertButton(parentId: AstNodeId, index: number): HTMLDivElement {
        const container = document.createElement('div');
        container.style.cssText = 'position:absolute;display:flex;align-items:center;justify-content:center;height:24px;z-index:1008;pointer-events:auto;opacity:0;transition:opacity 150ms;';

        container.addEventListener('mouseenter', () => {
            container.style.opacity = '1';
        });
        container.addEventListener('mouseleave', () => {
            container.style.opacity = '0';
        });

        // Line
        const line = document.createElement('div');
        line.style.cssText = 'position:absolute;top:50%;left:18px;right:18px;height:1px;background:rgba(59,130,246,0.4);pointer-events:none;';
        container.appendChild(line);

        // Button
        const btn = document.createElement('button');
        btn.style.cssText = 'position:relative;display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;padding:0;margin:0;border:2px solid rgba(59,130,246,0.6);border-radius:50%;background:#fff;color:rgb(59,130,246);cursor:pointer;font-size:16px;line-height:1;transition:background-color 100ms,border-color 100ms,transform 100ms;';
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px"><path d="M5 12h14"/><path d="M12 5v14"/></svg>';
        btn.title = 'Add section here';

        btn.addEventListener('mouseenter', () => {
            btn.style.background = 'rgb(59,130,246)';
            btn.style.borderColor = 'rgb(59,130,246)';
            btn.style.color = '#fff';
            btn.style.transform = 'scale(1.15)';
        });
        btn.addEventListener('mouseleave', () => {
            btn.style.background = '#fff';
            btn.style.borderColor = 'rgba(59,130,246,0.6)';
            btn.style.color = 'rgb(59,130,246)';
            btn.style.transform = 'scale(1)';
        });

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
            this.callbacks.onInsertAt(parentId, index);
        });

        container.appendChild(btn);

        return container;
    }

    private updateSectionBoundaries(iframeDoc: Document, offsetX: number, offsetY: number): void {
        const { nodes, rootNodeId } = this.currentState;
        const root = nodes[rootNodeId];

        // Remove old boundaries
        for (const el of this.sectionBoundaries) {
            el.remove();
        }

        this.sectionBoundaries = [];

        if (!root) {
            return;
        }

        const childIds = root.childIds;

        for (const childId of childIds) {
            const el = getElementByAstId(iframeDoc, childId);

            if (!el) {
                continue;
            }

            const rect = el.getBoundingClientRect();
            const boundary = document.createElement('div');
            boundary.className = 'builder-section-boundary';
            boundary.style.cssText = `position:absolute;pointer-events:none;z-index:999;outline:1px dashed rgba(148,163,184,0.45);outline-offset:-1px;`;
            boundary.style.top = `${rect.top + offsetY}px`;
            boundary.style.left = `${rect.left + offsetX}px`;
            boundary.style.width = `${rect.width}px`;
            boundary.style.height = `${rect.height}px`;

            // Section label
            const node = nodes[childId];

            if (node) {
                const label = document.createElement('div');
                label.style.cssText = 'position:absolute;top:0;right:0;background:rgba(148,163,184,0.35);color:rgba(71,85,105,0.8);font-size:9px;font-family:system-ui,-apple-system,sans-serif;font-weight:500;line-height:1;padding:2px 6px;border-radius:0 0 0 4px;white-space:nowrap;pointer-events:auto;cursor:pointer;';
                label.textContent = node.displayName || node.type;
                label.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.callbacks.onSelect?.(childId);
                });
                boundary.appendChild(label);
            }

            this.container.appendChild(boundary);
            this.sectionBoundaries.push(boundary);
        }
    }

    // ---------------------------------------------------------------------------
    // Visibility
    // ---------------------------------------------------------------------------

    private hideAll(): void {
        this.hoverBox.style.display = 'none';
        this.selectBox.style.display = 'none';
        this.toolbar.style.display = 'none';
        this.dropLine.style.display = 'none';
    }

    // ---------------------------------------------------------------------------
    // Cleanup
    // ---------------------------------------------------------------------------

    destroy(): void {
        if (this.animFrameId !== null) {
            cancelAnimationFrame(this.animFrameId);
        }

        this.unbindScrollListeners();

        for (const btn of this.insertButtons) {
            btn.remove();
        }

        for (const el of this.sectionBoundaries) {
            el.remove();
        }

        this.container.remove();
    }
}
