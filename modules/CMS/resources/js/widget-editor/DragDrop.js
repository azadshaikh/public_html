/**
 * Widget Editor - Simple Native Drag & Drop
 *
 * A lightweight drag-and-drop implementation using native HTML5 API.
 * No external dependencies (SortableJS removed).
 * Consistent with Menu Editor DragDrop pattern.
 */

export class DragDrop {
    constructor(options = {}) {
        this.container = options.container;
        this.onReorder = options.onReorder || (() => {});

        // State
        this.draggedItem = null;
        this.dropIndicator = null;
        this.lastDropTarget = null;
        this.dropPosition = null; // 'before', 'after'
        this.canDrag = false; // Track if drag started from handle

        this.init();
    }

    init() {
        if (!this.container) {
            console.warn('[WidgetDragDrop] No container provided');
            return;
        }

        console.log('[WidgetDragDrop] Initializing on container:', this.container);
        this.createDropIndicator();
        this.bindEvents();
    }

    createDropIndicator() {
        this.dropIndicator = document.createElement('div');
        this.dropIndicator.className = 'widget-drop-indicator';
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
            const dragHandle = e.target.closest('.widget-drag-handle');
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
        const widgetItem = e.target.closest('.widget-item');
        if (!widgetItem) return;

        // Only allow drag if mousedown was on drag handle
        if (!this.canDrag) {
            e.preventDefault();
            return;
        }

        console.log('[WidgetDragDrop] Drag started:', widgetItem.dataset.widgetId);

        this.draggedItem = widgetItem;
        widgetItem.classList.add('is-dragging');

        // Set drag data
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', widgetItem.dataset.widgetId);

        // Create custom drag image
        const dragImage = this.createDragImage(widgetItem);
        e.dataTransfer.setDragImage(dragImage, 20, 20);

        // Clean up drag image after a short delay
        setTimeout(() => dragImage.remove(), 0);
    }

    createDragImage(widgetItem) {
        const title =
            widgetItem.querySelector('.widget-title')?.textContent ||
            widgetItem.querySelector('.fw-medium')?.textContent ||
            'Widget';
        const dragImage = document.createElement('div');
        dragImage.className = 'widget-drag-image';
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

        const widgetItem = e.target.closest('.widget-item');
        if (!widgetItem || widgetItem === this.draggedItem) {
            this.hideDropIndicator();
            return;
        }

        // Calculate drop position based on mouse position
        const rect = widgetItem.getBoundingClientRect();
        const mouseY = e.clientY;
        const itemHeight = rect.height;
        const relativeY = mouseY - rect.top;

        // Divide item into zones: top 50% = before, bottom 50% = after
        const position = relativeY < itemHeight * 0.5 ? 'before' : 'after';

        this.showDropIndicator(widgetItem, position);
        this.lastDropTarget = widgetItem;
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
        }
    }

    showDropIndicator(targetItem, position) {
        const rect = targetItem.getBoundingClientRect();

        let top;
        const left = rect.left;
        const width = rect.width;

        if (position === 'before') {
            top = rect.top - 2;
        } else {
            top = rect.bottom - 1;
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
            el.classList.remove('drop-target', 'drop-before', 'drop-after');
        });
    }

    destroy() {
        this.dropIndicator?.remove();
    }
}
