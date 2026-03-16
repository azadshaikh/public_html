/**
 * Menu Editor - Utility Functions
 */

/**
 * Escape HTML entities to prevent XSS
 */
export function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Get the depth level of a menu item
 */
export function getItemDepth(itemEl) {
    let depth = 0;
    let current = itemEl.parentElement;
    while (current && !current.matches('#menu-items')) {
        if (current.classList.contains('menu-children')) {
            depth++;
        }
        current = current.parentElement;
    }
    return depth;
}

/**
 * Type configurations for menu items
 */
export const TYPE_ICONS = {
    home: 'ri-home-line',
    page: 'ri-file-text-line',
    custom: 'ri-link',
    category: 'ri-folder-line',
    tag: 'ri-price-tag-3-line',
};

export const TYPE_BADGE_CLASSES = {
    home: 'bg-success-subtle text-success',
    page: 'bg-primary-subtle text-primary',
    category: 'bg-info-subtle text-info',
    tag: 'bg-warning-subtle text-warning',
    custom: 'bg-light text-dark',
};
