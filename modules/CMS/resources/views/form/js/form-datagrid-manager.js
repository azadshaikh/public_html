/**
 * Form DataGrid Manager
 *
 * Consolidated JavaScript for managing form DataGrid functionality.
 * Handles templates, initialization, and data management for the forms index.
 */

const escapeHtml = (value) => window.DataGrid.escape(value);
const safeUrl = (value, fallback = '#') => window.DataGrid.safeUrl(value, fallback);

/**
 * Form DataGrid Templates
 *
 * Template functions for rendering form data in the DataGrid component.
 * Each template receives (value, row, column) parameters (v3 signature).
 */
const formDataGridTemplates = {
    /**
     * Title display template with formatted layout.
     */
    title_html: (value, row, column) => {
        const editUrl = safeUrl(row.actions?.edit?.url || `#form-${row.id}`);
        const title = row.title || 'Untitled Form';
        const shortcode = row.shortcode || 'N/A';

        return `
            <div class="d-flex align-items-center gap-3">
                <div class="flex-grow-1">
                    <a href="${editUrl}" class="text-decoration-none">
                        <h5>${escapeHtml(title)}</h5>
                    </a>
                    <small class="text-muted">Shortcode: <code>${escapeHtml(shortcode)}</code></small>
                </div>
            </div>
        `;
    },

    /**
     * Template badge template.
     */
    template_badge: (value, row, column) => {
        const templateMap = {
            default: { label: 'Blank Form', color: 'secondary' },
            contact: { label: 'Contact', color: 'primary' },
            newsletter: { label: 'Newsletter', color: 'info' },
            registration: { label: 'Registration', color: 'success' },
            feedback: { label: 'Feedback', color: 'warning' },
            survey: { label: 'Survey', color: 'info' },
            quote: { label: 'Quote', color: 'primary' },
            booking: { label: 'Booking', color: 'success' },
            payment: { label: 'Payment', color: 'danger' },
        };

        const template = row.template || 'default';
        const templateInfo = templateMap[template] || { label: template, color: 'secondary' };

        return `<span class="badge bg-${templateInfo.color}-subtle text-${templateInfo.color}">${escapeHtml(templateInfo.label)}</span>`;
    },

    /**
     * Submissions count template with link.
     */
    submissions_count: (value, row, column) => {
        const count = row.submissions_count || 0;
        const unread = row.unread_count || 0;

        return `
            <div class="text-center">
                <span class="badge bg-primary">${count}</span>
                ${unread > 0 ? `<span class="badge bg-warning ms-1">${unread} new</span>` : ''}
            </div>
        `;
    },

    /**
     * Conversion rate template.
     */
    conversion_rate: (value, row, column) => {
        const rate = parseFloat(row.conversion_rate || 0);
        const views = row.views_count || 0;

        if (views === 0) {
            return '<span class="text-muted">--</span>';
        }

        let colorClass = 'text-muted';
        if (rate >= 10) colorClass = 'text-success';
        else if (rate >= 5) colorClass = 'text-warning';
        else if (rate > 0) colorClass = 'text-danger';

        return `<span class="${colorClass} fw-bold">${rate.toFixed(1)}%</span>`;
    },

    /**
     * Active status badge template.
     */
    is_active_badge: (value, row, column) => {
        const isActive = row.is_active;

        if (isActive) {
            return '<span class="badge bg-success-subtle text-success"><i class="ri-checkbox-circle-line me-1"></i>Active</span>';
        }

        return '<span class="badge bg-danger-subtle text-danger"><i class="ri-close-circle-line me-1"></i>Inactive</span>';
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
            row.status_label || (row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : 'Unknown');

        return `<span class="badge text-bg-${variant}">${escapeHtml(label)}</span>`;
    },

    /**
     * Date formatting template.
     */
    date_format: (value, row, column) => {
        const dateValue = row[column.key];
        if (!dateValue) return '--';

        try {
            const date = new Date(dateValue);
            return date.toISOString().split('T')[0]; // Returns YYYY-MM-DD
        } catch (e) {
            return '--';
        }
    },

    /**
     * Actions dropdown template.
     */
    actions_dropdown: (value, row, column) => {
        const actions = row.actions || {};
        let actionItems = '';

        // Define action order and styling
        const actionOrder = ['edit', 'delete', 'restore', 'force_delete'];
        let hasDivider = false;
        const availableActions = Object.keys(actions);

        actionOrder.forEach((actionKey) => {
            const actionData = actions[actionKey];
            if (!actionData) return;

            // Add divider before destructive actions, but only if there are non-destructive actions above
            const hasNonDestructiveActions = availableActions.some((key) => ['edit'].includes(key));
            if (['delete', 'restore', 'force_delete'].includes(actionKey) && !hasDivider && hasNonDestructiveActions) {
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
                const methodAttr = actionData.method ? `data-method="${escapeHtml(actionData.method)}"` : '';

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
 * Form DataGrid Manager Class
 *
 * Manages the initialization and configuration of the Forms DataGrid.
 */
class FormDataGridManager {
    constructor() {
        this.dataGrid = null;
        this.templates = formDataGridTemplates;
        this.urlFragment = '/cms/form/data';
    }

    findDataGridInstance() {
        const instances = window.dataGridInstances ? Object.values(window.dataGridInstances) : [];
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
                if (dg.renderer && typeof dg.renderer.registerTemplate === 'function') {
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
            console.error('Form DataGrid initialization error:', error);
        }
    }
}

/**
 * Global initialization function for forms index
 */
window.initializeFormsDataGrid = function () {
    const manager = new FormDataGridManager();
    manager.initialize();
};

// Auto-initialize if DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.initializeFormsDataGrid);
} else {
    window.initializeFormsDataGrid();
}

/**
 * Export for module usage
 */
export { FormDataGridManager, formDataGridTemplates };
