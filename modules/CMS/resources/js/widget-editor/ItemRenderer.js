/**
 * Widget Editor - Item Rendering
 * Consistent with Menu Editor ItemRenderer pattern.
 */

import { escapeHtml, getWidgetIcon, getWidgetDisplayName } from './utils.js';

export class ItemRenderer {
    /**
     * Render a new widget item HTML
     */
    static render(widget) {
        const widgetInfo = window.availableWidgets?.[widget.type];
        const displayName = getWidgetDisplayName(widget.type);
        const icon = getWidgetIcon(widget.type);
        const category = widgetInfo?.category || '';

        return `
            <div class="widget-item" data-widget-id="${widget.id}" data-widget-key="${widget.type}" draggable="true">
                <div class="widget-item-content">
                    <div class="widget-drag-handle" title="Drag to reorder" data-id="${widget.id}">
                        <i class="ri-draggable"></i>
                    </div>

                    <div class="widget-item-info flex-grow-1">
                        <div class="widget-item-main-line">
                            <span class="widget-title">${escapeHtml(widget.title || 'Untitled Widget')}</span>
                        </div>
                        <small class="text-muted d-block text-truncate">
                            <i class="${icon} me-1"></i>${displayName}
                            ${category ? `<span class="badge text-bg-secondary ms-1">${escapeHtml(category.charAt(0).toUpperCase() + category.slice(1))}</span>` : ''}
                        </small>
                    </div>

                    <div class="widget-item-actions">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-light-primary edit-widget"
                                data-id="${widget.id}" title="Edit">
                                <i class="ri-pencil-line"></i>
                            </button>
                            <button type="button" class="btn btn-light-danger remove-widget"
                                data-id="${widget.id}" title="Remove">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Update an existing widget's DOM representation
     */
    static updateDOM(widgetId, data) {
        const widgetEl = document.querySelector(
            `.widget-item[data-widget-id="${widgetId}"]`,
        );
        if (!widgetEl) return;

        // Update title
        const titleEl = widgetEl.querySelector('.widget-title');
        if (titleEl) titleEl.textContent = data.title || 'Untitled Widget';
    }

    /**
     * Render empty state
     */
    static renderEmptyState() {
        return `
            <div class="empty-area-message" id="empty-state">
                <i class="ri-layout-grid-line display-4 text-body-tertiary mb-3"></i>
                <h5 class="mb-1">No widgets in this area</h5>
                <p class="text-muted mb-0">Add widgets from the panel on the right.</p>
            </div>
        `;
    }
}
