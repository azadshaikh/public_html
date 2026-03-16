/**
 * Menu Editor - Simple Native Drag & Drop
 *
 * A lightweight drag-and-drop implementation using native HTML5 API.
 * No external dependencies.
 */

import { getItemDepth } from './utils.js';

export class DragDrop {
    constructor(options = {}) {
        this.container = options.container;
        this.settings = options.settings || { supportsHierarchy: true, maxDepth: 3 };
        this.onReorder = options.onReorder || (() => {});

        // State
        this.draggedItem = null;
        this.placeholder = null;
        this.dropIndicator = null;
        this.lastDropTarget = null;
        this.dropPosition = null; // 'before', 'after', 'child'
        this.canDrag = false; // Track if drag started from handle

        this.init();
    }

    init() {
        if (!this.container) {
            console.warn('[DragDrop] No container provided');
            return;
        }

        console.log('[DragDrop] Initializing on container:', this.container);
        this.createDropIndicator();
        this.bindEvents();
    }

    createDropIndicator() {
        this.dropIndicator = document.createElement('div');
        this.dropIndicator.className = 'menu-drop-indicator';
        this.dropIndicator.style.cssText = `
            position: absolute;
            height: 3px;
            background: var(--bs-primary, #0d6efd);
            border-radius: 2px;
            pointer-events: none;
            z-index: 1000;
            display: none;
            transition: top 0.1s ease, left 0.1s ease, width 0.1s ease;
        `;
        document.body.appendChild(this.dropIndicator);
    }

    bindEvents() {
        // Track mousedown on drag handle to allow drag
        this.container.addEventListener('mousedown', (e) => {
            const dragHandle = e.target.closest('.menu-item-drag-handle');
            this.canDrag = !!dragHandle;
        });

        // Reset on mouseup
        document.addEventListener('mouseup', () => {
            this.canDrag = false;
        });

        // Use event delegation on container
        this.container.addEventListener('dragstart', this.handleDragStart.bind(this));
        this.container.addEventListener('dragend', this.handleDragEnd.bind(this));
        this.container.addEventListener('dragover', this.handleDragOver.bind(this));
        this.container.addEventListener('dragleave', this.handleDragLeave.bind(this));
        this.container.addEventListener('drop', this.handleDrop.bind(this));
    }

    handleDragStart(e) {
        const menuItem = e.target.closest('.menu-item');
        if (!menuItem) return;

        // Only allow drag if mousedown was on drag handle
        if (!this.canDrag) {
            e.preventDefault();
            return;
        }

        console.log('[DragDrop] Drag started:', menuItem.dataset.id);

        this.draggedItem = menuItem;
        menuItem.classList.add('is-dragging');

        // Set drag data
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', menuItem.dataset.id);

        // Create custom drag image
        const dragImage = this.createDragImage(menuItem);
        e.dataTransfer.setDragImage(dragImage, 20, 20);

        // Clean up drag image after a short delay
        setTimeout(() => dragImage.remove(), 0);
    }

    createDragImage(menuItem) {
        const title = menuItem.querySelector('.menu-item-title')?.textContent || 'Menu Item';
        const dragImage = document.createElement('div');
        dragImage.className = 'menu-drag-image';
        dragImage.style.cssText = `
            position: absolute;
            top: -9999px;
            left: -9999px;
            padding: 8px 16px;
            background: var(--bs-body-bg, #fff);
            border: 1px solid var(--bs-border-color, #dee2e6);
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-size: 14px;
            white-space: nowrap;
        `;
        dragImage.innerHTML = `<i class="ri-drag-move-2-line me-2"></i>${title}`;
        document.body.appendChild(dragImage);
        return dragImage;
    }

    handleDragEnd(e) {
        if (!this.draggedItem) return;

        this.draggedItem.classList.remove('is-dragging');
        this.hideDropIndicator();
        this.clearDropTargets();

        this.draggedItem = null;
        this.lastDropTarget = null;
        this.dropPosition = null;
    }

    handleDragOver(e) {
        if (!this.draggedItem) return;

        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        const menuItem = e.target.closest('.menu-item');
        if (!menuItem || menuItem === this.draggedItem) {
            this.hideDropIndicator();
            return;
        }

        // Don't allow dropping on descendants
        if (this.draggedItem.contains(menuItem)) {
            this.hideDropIndicator();
            return;
        }

        // Calculate drop position based on mouse position
        const rect = menuItem.getBoundingClientRect();
        const mouseY = e.clientY;
        const itemHeight = rect.height;
        const relativeY = mouseY - rect.top;

        // Divide item into zones: top 25% = before, bottom 25% = after, middle 50% = child
        let position;
        if (relativeY < itemHeight * 0.25) {
            position = 'before';
        } else if (relativeY > itemHeight * 0.75) {
            position = 'after';
        } else if (this.settings.supportsHierarchy) {
            // Check depth limit for nesting
            const targetDepth = getItemDepth(menuItem) + 1;
            if (targetDepth <= this.settings.maxDepth) {
                position = 'child';
            } else {
                position = 'after';
            }
        } else {
            position = 'after';
        }

        this.showDropIndicator(menuItem, position);
        this.lastDropTarget = menuItem;
        this.dropPosition = position;
    }

    handleDragLeave(e) {
        // Only hide if leaving the container entirely
        if (!this.container.contains(e.relatedTarget)) {
            this.hideDropIndicator();
        }
    }

    handleDrop(e) {
        e.preventDefault();

        if (!this.draggedItem || !this.lastDropTarget || !this.dropPosition) {
            return;
        }

        const targetItem = this.lastDropTarget;
        const position = this.dropPosition;

        // Perform the move
        this.moveItem(this.draggedItem, targetItem, position);

        // Notify parent
        this.onReorder();

        // Cleanup
        this.hideDropIndicator();
        this.clearDropTargets();
    }

    moveItem(draggedItem, targetItem, position) {
        switch (position) {
            case 'before':
                targetItem.parentElement.insertBefore(draggedItem, targetItem);
                break;

            case 'after':
                targetItem.parentElement.insertBefore(draggedItem, targetItem.nextSibling);
                break;

            case 'child':
                // Get or create children container
                let childrenContainer = targetItem.querySelector(':scope > .menu-children');
                if (!childrenContainer) {
                    childrenContainer = document.createElement('div');
                    childrenContainer.className = 'menu-children';
                    childrenContainer.dataset.level = getItemDepth(targetItem) + 1;
                    targetItem.appendChild(childrenContainer);
                }
                childrenContainer.appendChild(draggedItem);
                break;
        }

        // Clean up empty children containers
        this.cleanupEmptyContainers();
    }

    cleanupEmptyContainers() {
        this.container.querySelectorAll('.menu-children:empty').forEach((el) => el.remove());
    }

    showDropIndicator(targetItem, position) {
        const rect = targetItem.getBoundingClientRect();
        const contentEl = targetItem.querySelector('.menu-item-content');
        const contentRect = contentEl ? contentEl.getBoundingClientRect() : rect;

        let top, left, width;

        switch (position) {
            case 'before':
                top = rect.top - 2;
                left = contentRect.left;
                width = contentRect.width;
                break;

            case 'after':
                top = rect.bottom - 1;
                left = contentRect.left;
                width = contentRect.width;
                break;

            case 'child':
                top = rect.bottom - 1;
                left = contentRect.left + 24; // Indent for child
                width = contentRect.width - 24;
                break;
        }

        this.dropIndicator.style.top = `${top + window.scrollY}px`;
        this.dropIndicator.style.left = `${left}px`;
        this.dropIndicator.style.width = `${width}px`;
        this.dropIndicator.style.display = 'block';

        // Add visual feedback to target
        this.clearDropTargets();
        targetItem.classList.add('drop-target', `drop-${position}`);
    }

    hideDropIndicator() {
        this.dropIndicator.style.display = 'none';
    }

    clearDropTargets() {
        this.container.querySelectorAll('.drop-target').forEach((el) => {
            el.classList.remove('drop-target', 'drop-before', 'drop-after', 'drop-child');
        });
    }

    destroy() {
        this.dropIndicator?.remove();
    }
}
