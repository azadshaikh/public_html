/**
 * Design Block DataGrid Manager
 *
 * Consolidated JavaScript for managing design block DataGrid functionality.
 * Handles templates, initialization, and data management for the design blocks index.
 */

const escapeHtml = (value) => window.DataGrid.escape(value);
const safeUrl = (value, fallback = '#') =>
    window.DataGrid.safeUrl(value, fallback);
const PLACEHOLDER_IMAGE_URL =
    window.Astero?.mediaPlaceholderUrl ||
    '/assets/images/placeholder-image.png';

/**
 * Design Block DataGrid Templates
 *
 * Template functions for rendering design block data in the DataGrid component.
 * Each template receives (value, row, column) parameters (v3 signature).
 */
const designBlockDataGridTemplates = {
    /**
     * Title display template with preview image and formatted layout.
     */
    title_display: (value, row, column) => {
        const editUrl = safeUrl(
            row.actions?.edit?.url || `#design-block-${row.id}`,
        );
        const title = row.title || 'Untitled Block';
        const previewImageUrl = safeUrl(
            row.preview_image_url || PLACEHOLDER_IMAGE_URL,
            PLACEHOLDER_IMAGE_URL,
        );
        const creatorName = row.creator_name || 'Unknown Creator';
        const viewUrl = row.actions?.view?.url
            ? safeUrl(row.actions.view.url, '')
            : '';

        return `
            <div class="d-flex align-items-center gap-3">
                <div class="ratio ratio-16x9 position-relative design-block-preview-container" style="width: 160px; flex-shrink: 0;">
                    <img src="${previewImageUrl}" class="rounded object-fit-cover w-100 h-100" alt="Block Preview">
                    ${
                        viewUrl
                            ? `<a href="${viewUrl}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="preview-overlay-icon position-absolute top-50 start-50 translate-middle text-white bg-dark bg-opacity-75 rounded-circle d-flex align-items-center justify-content-center text-decoration-none"
                        title="Preview block"
                        data-bs-toggle="tooltip"
                        style="width: 32px; height: 32px; opacity: 0; transition: opacity 0.2s;">
                        <i class="ri-eye-line"></i>
                    </a>`
                            : ''
                    }
                </div>
                <div class="flex-grow-1">
                    <a href="${editUrl}" class="text-decoration-none">
                        <h5 class="mb-1">${escapeHtml(title)}</h5>
                    </a>
                    <small class="text-muted d-block">Author: ${escapeHtml(creatorName)}</small>
                </div>
            </div>
        `;
    },

    /**
     * Status badge template with dynamic styling.
     */
    status_badge: (value, row, column) => {
        const statusMap = {
            published: 'success',
            draft: 'warning',
            trash: 'danger',
        };

        const variant = statusMap[row.status] || 'secondary';
        const label =
            row.status_label ||
            (row.status
                ? row.status.charAt(0).toUpperCase() + row.status.slice(1)
                : 'Unknown');

        return `<span class="badge text-bg-${variant}">${escapeHtml(label)}</span>`;
    },

    /**
     * Design type badge template.
     */
    design_type_badge: (value, row, column) => {
        const typeMap = {
            section: 'primary',
            block: 'info',
            component: 'success',
            template: 'warning',
        };

        const variant = typeMap[row.design_type] || 'secondary';
        const label = row.design_type
            ? row.design_type.charAt(0).toUpperCase() + row.design_type.slice(1)
            : 'Unknown';

        return `<span class="badge text-bg-${variant}">${escapeHtml(label)}</span>`;
    },

    /**
     * Category name display template.
     */
    category_name_display: (value, row, column) => {
        const categoryName = row.category_name || '';

        if (!categoryName || categoryName === 'Uncategorized') {
            return '<span class="text-muted">Uncategorized</span>';
        }

        return `<span class="text-dark">${escapeHtml(categoryName)}</span>`;
    },

    /**
     * Preview image thumbnail template.
     */
    preview_image_thumbnail: (value, row, column) => {
        const imageUrl = row.preview_image_url;

        if (!imageUrl) {
            return '<span class="text-muted">No Preview</span>';
        }

        return `
            <div class="position-relative" style="width: 40px; height: 40px;">
                <img src="${escapeHtml(imageUrl)}"
                     alt="Preview Image"
                     class="rounded border"
                     style="width: 100%; height: 100%; object-fit: cover;"
                     loading="lazy">
            </div>
        `;
    },

    /**
     * Date formatting template.
     */
    date_format: (value, row, column) => {
        const dateValue =
            column.field === 'created_at' ? row.created_at : row.updated_at;

        if (!dateValue) {
            return '<span class="text-muted">-</span>';
        }

        try {
            const date = new Date(dateValue);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        } catch (e) {
            return '<span class="text-muted">Invalid Date</span>';
        }
    },

    /**
     * Actions dropdown template.
     */
    actions_dropdown: (value, row, column) => {
        const actions = row.actions || {};
        let actionItems = '';

        // Define action order and styling
        const actionOrder = [
            'view',
            'edit',
            'delete',
            'restore',
            'force_delete',
        ];
        let hasDivider = false;
        const availableActions = Object.keys(actions);

        actionOrder.forEach((actionKey) => {
            const actionData = actions[actionKey];
            if (!actionData) return;

            // Add divider before destructive actions, but only if there are non-destructive actions above
            const hasNonDestructiveActions = availableActions.some((key) =>
                ['view', 'edit'].includes(key),
            );
            if (
                ['delete', 'restore', 'force_delete'].includes(actionKey) &&
                !hasDivider &&
                hasNonDestructiveActions
            ) {
                actionItems += '<li><hr class="dropdown-divider"></li>';
                hasDivider = true;
            }

            let itemClass = 'dropdown-item';
            let textClass = '';

            // Style based on action type
            if (actionKey === 'delete' || actionKey === 'force_delete') {
                textClass = ' text-danger';
            } else if (actionKey === 'restore') {
                textClass = ' text-success';
            }

            if (actionData.confirm) {
                // Confirmation actions
                const confirmData = actionData.confirm;
                const confirmAttr = `data-title="${escapeHtml(confirmData.title || 'Confirm Action')}"
                                     data-message="${escapeHtml(confirmData.message || 'Are you sure?')}"
                                     data-confirmButtonText="${escapeHtml(confirmData.button_text || 'Yes')}"
                                     data-loaderButtonText="${escapeHtml(confirmData.loader_text || 'Processing...')}"`;
                const methodAttr = actionData.method
                    ? `data-method="${escapeHtml(actionData.method)}"`
                    : '';

                actionItems += `
                    <li>
                        <a class="${itemClass}${textClass} confirmation-btn"
                           href="${safeUrl(actionData.url)}"
                           data-action="${escapeHtml(actionKey)}"
                           data-item-id="${escapeHtml(row.id)}"
                           ${methodAttr}
                           ${confirmAttr}>
                            <i class="${escapeHtml(actionData.icon)} me-2"></i>${escapeHtml(actionData.label)}
                        </a>
                    </li>
                `;
            } else {
                // Regular actions
                actionItems += `
                    <li>
                        <a class="${itemClass}${textClass}" href="${safeUrl(actionData.url)}">
                            <i class="${escapeHtml(actionData.icon)} me-2"></i>${escapeHtml(actionData.label)}
                        </a>
                    </li>
                `;
            }
        });

        return `
            <div class="dropdown dropstart">
                <button class="btn btn-sm btn-outline-secondary rounded-circle"
                        data-bs-toggle="dropdown"
                        data-bs-display="static"
                        type="button"
                        aria-expanded="false"
                        style="width: 1.875rem; height: 1.875rem;">
                    <i class="ri-more-2-line"></i>
                </button>
                <ul class="dropdown-menu">
                    ${actionItems}
                </ul>
            </div>
        `;
    },
};

/**
 * Design Block DataGrid Manager Class
 *
 * Manages the initialization and configuration of the Design Blocks DataGrid.
 */
class DesignBlockDataGridManager {
    constructor() {
        this.dataGrid = null;
        this.templates = designBlockDataGridTemplates;
        this.urlFragment = '/cms/designblock/data';
    }

    findDataGridInstance() {
        const instances = window.dataGridInstances
            ? Object.values(window.dataGridInstances)
            : [];
        if (instances.length === 1) {
            return instances[0];
        }

        const matchedByUrl = instances.find((instance) => {
            const url = instance?.config?.get?.('url');
            return typeof url === 'string' && url.includes(this.urlFragment);
        });
        if (matchedByUrl) {
            return matchedByUrl;
        }

        const containers = document.querySelectorAll('[data-datagrid]');
        if (containers.length === 1 && containers[0]?._datagrid) {
            return containers[0]._datagrid;
        }

        return null;
    }

    /**
     * Wait for DataGrid to initialize (v3 API uses dataGridInstances).
     */
    async waitForDataGrid(attempts = 10, interval = 200) {
        return new Promise((resolve) => {
            let tries = 0;
            const checkInterval = setInterval(() => {
                tries++;
                const instance = this.findDataGridInstance();
                if (instance) {
                    clearInterval(checkInterval);
                    resolve(instance);
                } else if (tries >= attempts) {
                    clearInterval(checkInterval);
                    resolve(null);
                }
            }, interval);
        });
    }

    /**
     * Initialize the DataGrid with templates.
     */
    async initialize() {
        try {
            const dg = await this.waitForDataGrid();
            if (dg) {
                this.dataGrid = dg;

                // Register templates
                if (
                    dg.renderer &&
                    typeof dg.renderer.registerTemplate === 'function'
                ) {
                    Object.entries(this.templates).forEach(([name, fn]) => {
                        dg.renderer.registerTemplate(name, fn);
                    });
                }

                const currentData = dg.state?.get?.('data');
                if (currentData && dg.renderer?.render) {
                    dg.renderer.render(currentData);
                }
            }
        } catch (error) {
            console.error('Design Block DataGrid initialization error:', error);
        }
    }
}

/**
 * Global initialization function for design blocks index
 */
window.initializeDesignBlocksDataGrid = function () {
    const manager = new DesignBlockDataGridManager();
    manager.initialize();
};

// Auto-initialize if DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        window.initializeDesignBlocksDataGrid,
    );
} else {
    window.initializeDesignBlocksDataGrid();
}
