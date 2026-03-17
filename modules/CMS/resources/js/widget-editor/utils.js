// Widget Editor Utilities

/**
 * Escape HTML to prevent XSS
 */
export function escapeHtml(text) {
    if (typeof text !== 'string') return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    };
    return text.replace(/[&<>"']/g, function (m) {
        return map[m];
    });
}

/**
 * Generate unique widget ID
 */
export function generateWidgetId() {
    return (
        'widget-' + Date.now() + '-' + Math.random().toString(36).substr(2, 6)
    );
}

/**
 * Debounce function for performance
 */
export function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle function for performance
 */
export function throttle(func, limit) {
    let inThrottle;
    return function () {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => (inThrottle = false), limit);
        }
    };
}

/**
 * Get widget type display name
 */
export function getWidgetDisplayName(type) {
    // Try to get from available widgets first
    if (window.availableWidgets && window.availableWidgets[type]) {
        return window.availableWidgets[type].name;
    }

    // Fallback to formatted type name
    return type
        .split(/[-_]/)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

/**
 * Get widget icon based on category or type
 */
export function getWidgetIcon(type) {
    // Get widget info from available widgets
    const widgetInfo = window.availableWidgets?.[type];
    const categoryIcons = window.widgetCategoryIcons || {
        content: 'ri-article-line',
        marketing: 'ri-megaphone-line',
        forms: 'ri-mail-line',
        social: 'ri-share-line',
        navigation: 'ri-map-pin-line',
        media: 'ri-image-line',
        widgets: 'ri-grid-line',
    };

    if (widgetInfo?.category && categoryIcons[widgetInfo.category]) {
        return categoryIcons[widgetInfo.category];
    }

    // Fallback for unknown widgets
    return 'bi-grid-3x3-gap';
}

/**
 * Get widget category display name
 */
export function getWidgetCategory(type) {
    const widgetInfo = window.availableWidgets?.[type];
    return widgetInfo?.category || 'widgets';
}

/**
 * Get widget description
 */
export function getWidgetDescription(type) {
    const widgetInfo = window.availableWidgets?.[type];
    return widgetInfo?.description || `${getWidgetDisplayName(type)} widget`;
}

/**
 * Validate widget settings based on manifest schema
 */
export function validateWidgetSettings(type, settings) {
    const widgetInfo = window.availableWidgets?.[type];

    if (!widgetInfo?.settings_schema) {
        return { valid: true };
    }

    const schema = widgetInfo.settings_schema;
    const errors = [];

    for (const [fieldName, fieldConfig] of Object.entries(schema)) {
        const value = settings[fieldName];

        // Check required fields
        if (
            fieldConfig.required &&
            (!value || (typeof value === 'string' && value.trim() === ''))
        ) {
            errors.push(`${fieldConfig.label || fieldName} is required`);
            continue;
        }

        // Skip validation if field is empty and not required
        if (!value || (typeof value === 'string' && value.trim() === '')) {
            continue;
        }

        // Type-specific validation
        switch (fieldConfig.type) {
            case 'text':
                if (typeof value !== 'string') {
                    errors.push(
                        `${fieldConfig.label || fieldName} must be text`,
                    );
                } else if (value.length > 255) {
                    errors.push(
                        `${fieldConfig.label || fieldName} cannot exceed 255 characters`,
                    );
                }
                break;

            case 'textarea':
                if (typeof value !== 'string') {
                    errors.push(
                        `${fieldConfig.label || fieldName} must be text`,
                    );
                } else if (value.length > 5000) {
                    errors.push(
                        `${fieldConfig.label || fieldName} cannot exceed 5000 characters`,
                    );
                }
                break;

            case 'url':
                if (typeof value !== 'string') {
                    errors.push(
                        `${fieldConfig.label || fieldName} must be a valid URL`,
                    );
                } else {
                    try {
                        new URL(value);
                    } catch {
                        errors.push(
                            `${fieldConfig.label || fieldName} must be a valid URL`,
                        );
                    }
                }
                break;

            case 'color':
                if (
                    typeof value !== 'string' ||
                    !/^#[0-9A-F]{6}$/i.test(value)
                ) {
                    errors.push(
                        `${fieldConfig.label || fieldName} must be a valid color`,
                    );
                }
                break;

            case 'select':
                if (
                    fieldConfig.options &&
                    !fieldConfig.options.hasOwnProperty(value)
                ) {
                    errors.push(
                        `${fieldConfig.label || fieldName} has an invalid value`,
                    );
                }
                break;

            case 'checkbox':
                if (typeof value !== 'boolean') {
                    errors.push(
                        `${fieldConfig.label || fieldName} must be true or false`,
                    );
                }
                break;
        }
    }

    return {
        valid: errors.length === 0,
        errors: errors,
    };
}

/**
 * Create default settings for widget type based on manifest
 */
export function getDefaultSettings(type) {
    const widgetInfo = window.availableWidgets?.[type];

    if (!widgetInfo?.settings_schema) {
        return {};
    }

    const defaults = {};
    for (const [fieldName, fieldConfig] of Object.entries(
        widgetInfo.settings_schema,
    )) {
        if (fieldConfig.default !== undefined) {
            defaults[fieldName] = fieldConfig.default;
        }
    }

    return defaults;
}

/**
 * Sanitize widget title
 */
export function sanitizeTitle(title) {
    return escapeHtml(title.trim().substring(0, 255));
}

/**
 * Check if drag and drop is supported (native HTML5)
 */
export function isDragDropSupported() {
    return 'draggable' in document.createElement('div');
}

/**
 * Show loading state
 */
export function showLoading(element, text = 'Loading...') {
    if (!element) return;

    element.style.position = 'relative';
    element.style.pointerEvents = 'none';

    const loader = document.createElement('div');
    loader.className = 'widget-loader';
    loader.innerHTML = `
        <div class="d-flex align-items-center justify-content-center h-100 position-absolute top-0 start-0 w-100 bg-white bg-opacity-75">
            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
            <span>${escapeHtml(text)}</span>
        </div>
    `;

    element.appendChild(loader);
}

/**
 * Hide loading state
 */
export function hideLoading(element) {
    if (!element) return;

    element.style.pointerEvents = '';
    const loader = element.querySelector('.widget-loader');
    if (loader) {
        loader.remove();
    }
}

/**
 * Get all available widget categories
 */
export function getAvailableCategories() {
    if (!window.availableWidgets) return [];

    const categories = new Set();
    Object.values(window.availableWidgets).forEach((widget) => {
        if (widget.category) {
            categories.add(widget.category);
        }
    });

    return Array.from(categories).sort();
}

/**
 * Get widgets by category
 */
export function getWidgetsByCategory(category) {
    if (!window.availableWidgets) return {};

    const filtered = {};
    Object.entries(window.availableWidgets).forEach(([type, widget]) => {
        if (widget.category === category) {
            filtered[type] = widget;
        }
    });

    return filtered;
}
