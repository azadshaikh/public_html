/**
 * Menu Editor - Item Rendering
 */

import { escapeHtml, TYPE_ICONS, TYPE_BADGE_CLASSES } from './utils.js';

export class ItemRenderer {
    /**
     * Render a new menu item HTML
     */
    static render(item) {
        const badgeClass = TYPE_BADGE_CLASSES[item.type] || 'bg-light text-dark';

        return `
            <div class="menu-item" data-id="${item.id}" data-type="${item.type}"
                 data-url="${escapeHtml(item.url)}" data-target="${item.target}"
                 data-is-active="${item.is_active ? '1' : '0'}" data-css-classes="${escapeHtml(item.css_classes || '')}"
                 data-description="" data-object-id="${item.object_id || ''}"
                 data-link-title="${escapeHtml(item.link_title || '')}" data-link-rel="${escapeHtml(item.link_rel || '')}"
                 data-icon="${escapeHtml(item.icon || '')}"
                 draggable="true">
                <div class="menu-item-content">
                    <div class="menu-item-drag-handle" title="Drag to reorder">
                        <i class="ri-draggable"></i>
                    </div>

                    ${item.icon ? `<div class="menu-item-icon"><i class="${escapeHtml(item.icon)}"></i></div>` : ''}

                    <div class="menu-item-info flex-grow-1">
                        <div class="menu-item-main-line">
                            <span class="menu-item-title">${escapeHtml(item.title)}</span>
                            <div class="menu-item-badges d-none d-md-flex">
                                <span class="badge ${badgeClass} menu-item-type">${item.type}</span>
                                ${item.target === '_blank' ? '<span class="badge bg-info-subtle text-info menu-badge-external" title="Opens in new tab"><i class="ri-external-link-line"></i></span>' : ''}
                                ${!item.is_active ? '<span class="badge bg-warning-subtle text-warning menu-badge-hidden"><i class="ri-eye-off-line me-1"></i>Hidden</span>' : ''}
                            </div>
                        </div>
                    </div>

                    <div class="menu-item-actions">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-light-primary edit-item"
                                data-id="${item.id}" title="Edit">
                                <i class="ri-pencil-line"></i>
                            </button>
                            <button type="button" class="btn btn-light-danger delete-item"
                                data-id="${item.id}" title="Delete">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Update an existing item's DOM representation
     */
    static updateDOM(itemId, data) {
        const itemEl = document.querySelector(`.menu-item[data-id="${itemId}"]`);
        if (!itemEl) return;

        // Update title
        const titleEl = itemEl.querySelector('.menu-item-title');
        if (titleEl) titleEl.textContent = data.title;

        // Update data attributes
        itemEl.dataset.url = data.url;
        itemEl.dataset.target = data.target;
        itemEl.dataset.cssClasses = data.css_classes || '';
        itemEl.dataset.description = data.description || '';
        itemEl.dataset.linkTitle = data.link_title || '';
        itemEl.dataset.linkRel = data.link_rel || '';
        itemEl.dataset.icon = data.icon || '';
        itemEl.dataset.isActive = data.is_active ? '1' : '0';

        // Update icon if present
        const iconContainer = itemEl.querySelector('.menu-item-icon');
        if (data.icon) {
            if (iconContainer) {
                iconContainer.querySelector('i').className = data.icon;
            } else {
                const newIconHtml = `<div class="menu-item-icon"><i class="${escapeHtml(data.icon)}"></i></div>`;
                const dragHandle = itemEl.querySelector('.menu-item-drag-handle');
                if (dragHandle) {
                    dragHandle.insertAdjacentHTML('afterend', newIconHtml);
                }
            }
        } else if (iconContainer) {
            iconContainer.remove();
        }

        // Update active state
        itemEl.classList.toggle('menu-item-inactive', !data.is_active);

        // Update badges
        this.updateBadges(itemEl, data);
    }

    /**
     * Update item badges (external link, hidden)
     */
    static updateBadges(itemEl, data) {
        const badgesContainer = itemEl.querySelector('.menu-item-badges');
        if (!badgesContainer) return;

        // External link badge
        let externalBadge = badgesContainer.querySelector('.menu-badge-external');
        if (data.target === '_blank') {
            if (!externalBadge) {
                badgesContainer.insertAdjacentHTML(
                    'beforeend',
                    '<span class="badge bg-info-subtle text-info menu-badge-external" title="Opens in new tab"><i class="ri-external-link-line"></i></span>'
                );
            }
        } else if (externalBadge) {
            externalBadge.remove();
        }

        // Hidden badge
        let hiddenBadge = badgesContainer.querySelector('.menu-badge-hidden');
        if (!data.is_active) {
            if (!hiddenBadge) {
                badgesContainer.insertAdjacentHTML(
                    'beforeend',
                    '<span class="badge bg-warning-subtle text-warning menu-badge-hidden"><i class="ri-eye-off-line me-1"></i>Hidden</span>'
                );
            }
        } else if (hiddenBadge) {
            hiddenBadge.remove();
        }
    }

    /**
     * Render empty state
     */
    static renderEmptyState() {
        return `
            <div class="rounded-3 bg-body-secondary border-2 border-dashed p-5 text-center" id="empty-state">
                <i class="ri-book-mark-line display-4 text-body-tertiary mb-3"></i>
                <h5 class="mb-1">Your menu is empty</h5>
                <p class="text-muted mb-0">Start by adding items from the panel on the right, or drag and drop to reorder.</p>
            </div>
        `;
    }
}
