/**
 * CMS CRUD Managers
 * Shared DataGrid and form managers for posts, pages, categories, and tags.
 */

(() => {
    'use strict';

    const escapeHtml = (value) => window.DataGrid.escape(value);
    const safeUrl = (value, fallback = '#') => window.DataGrid.safeUrl(value, fallback);

    const ENTITY_ALIASES = {
        post: 'Post',
        page: 'Page',
        category: 'Category',
        tag: 'Tag',
        menu: 'Menu',
        redirection: 'Redirection',
    };

    const PLACEHOLDER_IMAGE_URL = window.Astero?.mediaPlaceholderUrl || '/assets/images/placeholder-image.png';

    /**
     * Template helpers
     * All templates use v3 signature: (value, row, column)
     */
    const templateHelpers = {
        createTitleDisplayTemplate({ entityLabel, showAuthor = true }) {
            const fallbackTitle = `Untitled ${entityLabel}`;
            const fallbackAuthor = 'Unknown Author';
            const imageAlt = `${entityLabel} Image`;

            return (value, row, column) => {
                const editUrl = safeUrl(row.actions?.edit?.url || `#${entityLabel.toLowerCase()}-${row.id}`);
                const title = row.title || fallbackTitle;
                const featuredImageUrl = safeUrl(
                    row.featured_image_url || PLACEHOLDER_IMAGE_URL,
                    PLACEHOLDER_IMAGE_URL
                );
                const authorName = row.author_name || fallbackAuthor;
                const viewUrl = safeUrl(row.actions?.view?.url || row.permalink_url, '');

                return `
                    <div class="d-flex align-items-center gap-3" style="min-width: 400px;">
                        <div class="ratio ratio-16x9 position-relative cms-image-preview-container" style="width: 120px; flex-shrink: 0;">
                            <a href="${editUrl}" class="d-block w-100 h-100">
                                <img src="${featuredImageUrl}"
                                     class="rounded object-fit-cover w-100 h-100 shadow-sm"
                                     alt="${escapeHtml(imageAlt)}">
                            </a>
                            ${
                                viewUrl
                                    ? `<a href="${viewUrl}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="preview-overlay-icon position-absolute top-50 start-50 translate-middle text-white bg-dark bg-opacity-75 rounded-circle d-flex align-items-center justify-content-center text-decoration-none"
                                title="Preview ${escapeHtml(entityLabel.toLowerCase())}"
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
                            ${showAuthor ? `<small class="text-muted d-block">Author: ${escapeHtml(authorName)}</small>` : ''}
                        </div>
                    </div>
                `;
            };
        },
        statusBadgeTemplate(value, row, column) {
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
        categoriesListTemplate(value, row, column) {
            const categories = row.categories || [];

            if (categories.length === 0) {
                return '<span class="text-muted small">No categories</span>';
            }

            const badges = categories
                .map((category) => {
                    const categoryName = category.title || 'Untitled';
                    return `<span class="badge bg-primary-subtle text-primary me-1 mb-1">${escapeHtml(categoryName)}</span>`;
                })
                .join('');

            return `<div class="d-flex flex-wrap gap-1">${badges}</div>`;
        },
        parentNameTemplate(value, row, column) {
            const parentName = row.parent_name || '';

            if (!parentName) {
                return '<span class="text-muted">No Parent</span>';
            }

            return `<span class="text-dark">${escapeHtml(parentName)}</span>`;
        },
        featuredImageTemplate({ entityLabel }) {
            const alt = `${entityLabel} Image`;
            return (value, row, column) => {
                const imageUrl = row.featured_image_url;

                if (!imageUrl) {
                    return '<span class="text-muted">No Image</span>';
                }

                return `
                    <div class="position-relative" style="width: 40px; height: 40px;">
                        <img src="${safeUrl(imageUrl, PLACEHOLDER_IMAGE_URL)}"
                             alt="${escapeHtml(alt)}"
                             class="rounded border"
                             style="width: 100%; height: 100%; object-fit: cover;"
                             loading="lazy">
                    </div>
                `;
            };
        },
        publishedDateTemplate(value, row, column) {
            const context = row.published_date_context ?? null;
            const status = (row.status || '').toLowerCase();

            const fallbackLabel =
                status === 'published' ? 'Published' : status === 'scheduled' ? 'Scheduled' : 'Last Modified';

            const label = context?.label || fallbackLabel;

            const displayValue =
                context?.display ||
                row.published_date ||
                row.published_at_formatted ||
                row.published_at ||
                row.updated_at;

            if (!displayValue || displayValue === '-') {
                return '<span class="text-muted">-</span>';
            }

            const timestamp = context?.timestamp || null;
            const detailContent = timestamp
                ? `<time datetime="${escapeHtml(timestamp)}" class="text-muted small">${escapeHtml(displayValue)}</time>`
                : `<span class="text-muted small">${escapeHtml(displayValue)}</span>`;

            return `
                <div class="d-flex flex-column gap-1">
                    <span class="fw-semibold text-dark">${escapeHtml(label)}</span>
                    ${detailContent}
                </div>
            `;
        },
        dateFormatTemplate(value, row, column) {
            const dateValue = row[column.key];
            if (!dateValue) {
                return '--';
            }

            try {
                const date = new Date(dateValue);
                return date.toISOString().split('T')[0];
            } catch (error) {
                return '--';
            }
        },
        countBadgeTemplate({ keys, label = 'posts' }) {
            return (value, row, column) => {
                const countValue = keys.reduce((acc, key) => acc ?? row[key], null);
                const count = typeof countValue === 'number' ? countValue : parseInt(countValue || 0, 10);
                const safeCount = Number.isFinite(count) ? count : 0;
                const badgeClass = safeCount > 0 ? 'bg-primary-subtle text-primary' : 'text-bg-secondary';

                return `<span class="badge ${badgeClass}">${safeCount} ${escapeHtml(label)}</span>`;
            };
        },
        actionsDropdownTemplate(value, row, column) {
            const actions = row.actions || {};
            let actionItems = '';

            const actionOrder = ['view', 'edit', 'delete', 'restore', 'force_delete'];
            let hasDivider = false;
            const availableActions = Object.keys(actions);

            actionOrder.forEach((actionKey) => {
                const actionData = actions[actionKey];
                if (!actionData) return;

                const hasNonDestructiveActions = availableActions.some((key) => ['view', 'edit'].includes(key));
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

                if (actionKey === 'delete' || actionKey === 'force_delete') {
                    textClass = ' text-danger';
                } else if (actionKey === 'restore') {
                    textClass = ' text-success';
                }

                if (actionData.confirm) {
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

    const menuTemplates = {
        menu_title_display: (value, row, column) => {
            const name = row.name || 'Untitled Menu';
            const description = row.description ? escapeHtml(row.description) : 'No description provided.';
            const editUrl = safeUrl(row.actions?.edit?.url || '#');
            const previewItems = Array.isArray(row.items_preview) ? row.items_preview : [];

            const previewHtml = previewItems.length
                ? `<div class="d-flex flex-wrap gap-1 mt-2">${previewItems
                      .map((preview) => {
                          const title = preview?.title || 'Menu Item';
                          return `<span class="badge bg-light text-muted border">${escapeHtml(title)}</span>`;
                      })
                      .join('')}</div>`
                : '';

            return `
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="${editUrl}" class="text-decoration-none fw-semibold">
                            ${escapeHtml(name)}
                        </a>
                        ${row.slug ? `<code class="text-muted small">/${escapeHtml(row.slug)}</code>` : ''}
                    </div>
                    <p class="mb-0 text-muted small">${description}</p>
                    ${previewHtml}
                </div>
            `;
        },
        menu_location_display: (value, row, column) => {
            const label = row.location_label || 'Unassigned';
            const badgeClass = row.location_label ? 'bg-info-subtle text-info' : 'text-bg-warning';

            return `<span class="badge ${badgeClass}">${escapeHtml(label)}</span>`;
        },
        menu_items_badge: (value, row, column) => {
            const count = Number(row.items_count || 0);
            const label = count === 1 ? 'item' : 'items';

            return `<span class="badge bg-primary-subtle text-primary">${count} ${label}</span>`;
        },
        menu_status_badge: (value, row, column) => {
            const isActive = row.status === 'active';
            const badgeClass = isActive ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning';
            const label = row.status_label || (isActive ? 'Active' : 'Inactive');

            return `<span class="badge ${badgeClass}">${escapeHtml(label)}</span>`;
        },
        menu_updated_at: (value, row, column) => {
            if (!row.updated_at) {
                return '<span class="text-muted">—</span>';
            }

            let exactDate = '';
            try {
                const date = new Date(row.updated_at);
                exactDate = date.toLocaleString();
            } catch (error) {
                exactDate = row.updated_at;
            }

            const relative = row.updated_at_for_humans || '';

            return `
                <div class="d-flex flex-column">
                    <span class="fw-medium">${escapeHtml(relative) || '-'}</span>
                    <small class="text-muted">${escapeHtml(exactDate)}</small>
                </div>
            `;
        },
        actions_dropdown: templateHelpers.actionsDropdownTemplate,
    };

    const resolveUrl = (value) => {
        if (!value) {
            return null;
        }

        if (/^https?:\/\//i.test(value)) {
            return value;
        }

        if (value.startsWith('/')) {
            return value;
        }

        return `/${value.replace(/^\/+/, '')}`;
    };

    const redirectionTemplates = {
        redirect_rule: (value, row, column) => {
            const sourceUrl = row.source_url || '';
            const targetUrl = row.target_url || '';

            if (!sourceUrl) {
                return '<span class="text-muted">No source URL</span>';
            }

            const sourceHref = resolveUrl(sourceUrl);
            const targetHref = resolveUrl(targetUrl);
            const displaySourceUrl = escapeHtml(sourceUrl);
            const displayTargetUrl = escapeHtml(targetUrl);
            const editUrl = safeUrl(row.actions?.edit?.url || '#');

            const sourceLink = `<a href="${editUrl}"
                   class="text-decoration-none fw-semibold text-dark">
                    ${displaySourceUrl}
                </a>`;

            const externalIcon = sourceHref
                ? `<a href="${escapeHtml(sourceHref)}"
                       class="text-muted"
                       target="_blank"
                       rel="noopener"
                       title="Open URL in new tab">
                        <i class="ri-external-link-line"></i>
                    </a>`
                : '';

            const targetLink = targetHref
                ? `<a href="${escapeHtml(targetHref)}"
                       class="text-decoration-none text-primary"
                       target="_blank"
                       rel="noopener">
                        ${displayTargetUrl}
                        <i class="ri-external-link-line ms-1 small"></i>
                    </a>`
                : `<span class="text-muted">${displayTargetUrl}</span>`;

            return `
                <div class="d-flex flex-column gap-1" style="min-width: 320px;">
                    <div class="d-flex align-items-center gap-2">
                        ${sourceLink}
                        ${externalIcon}
                    </div>
                    <div class="d-flex align-items-center gap-2 small">
                        <i class="ri-corner-down-right-line text-muted"></i>
                        <span class="text-muted">Redirects to:</span>
                        ${targetLink}
                    </div>
                </div>
            `;
        },
        source_url: (value, row, column) => {
            const url = row.source_url || '';
            if (!url) {
                return '<span class="text-muted">—</span>';
            }

            const href = resolveUrl(url);
            const displayUrl = escapeHtml(url);

            if (!href) {
                return `<span class="text-muted">${displayUrl}</span>`;
            }

            return `
                <div class="d-flex align-items-center gap-2">
                    <a href="${escapeHtml(href)}"
                       class="text-decoration-none fw-semibold"
                       target="_blank"
                       rel="noopener">
                        ${displayUrl}
                    </a>
                    <i class="ri-external-link-line text-muted small"></i>
                </div>
            `;
        },
        target_url: (value, row, column) => {
            const url = row.target_url || '';
            if (!url) {
                return '<span class="text-muted">—</span>';
            }

            const href = resolveUrl(url);
            const displayUrl = escapeHtml(url);

            if (!href) {
                return displayUrl;
            }

            return `
                <div class="d-flex align-items-center gap-2">
                    <a href="${escapeHtml(href)}"
                       class="text-decoration-none"
                       target="_blank"
                       rel="noopener">
                        ${displayUrl}
                    </a>
                    <i class="ri-external-link-line text-muted small"></i>
                </div>
            `;
        },
        redirect_type: (value, row, column) => {
            const code = row.redirect_type ? String(row.redirect_type) : '—';
            const label = row.redirect_type_label ? row.redirect_type_label.replace(/^\d+\s*/, '').trim() : '';

            const description = label || 'HTTP Redirect';

            return `
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill text-bg-secondary fw-semibold">${escapeHtml(code)}</span>
                    <span class="text-muted small">${escapeHtml(description)}</span>
                </div>
            `;
        },
        url_type: (value, row, column) => {
            const urlTypeValue = (row.url_type || '').toLowerCase();
            const label =
                row.url_type_label ||
                (urlTypeValue ? urlTypeValue.charAt(0).toUpperCase() + urlTypeValue.slice(1) : 'Unknown');

            const variant = urlTypeValue === 'external' ? 'warning' : 'primary';
            const badgeClass = `badge rounded-pill bg-${variant}-subtle text-${variant}`;

            return `<span class="${badgeClass}">${escapeHtml(label)}</span>`;
        },
        status_badge: (value, row, column) => {
            const status = (row.status || '').toLowerCase();
            const label = row.status_label || (status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unknown');
            const variant = status === 'active' ? 'success' : status === 'inactive' ? 'secondary' : 'danger';

            return `<span class="badge text-bg-${variant}">${escapeHtml(label)}</span>`;
        },
        created_at: (value, row, column) => {
            if (!row.created_at) {
                return '<span class="text-muted">—</span>';
            }

            try {
                const date = new Date(row.created_at);
                return date.toLocaleDateString();
            } catch (error) {
                return '<span class="text-muted">—</span>';
            }
        },
        actions_dropdown: templateHelpers.actionsDropdownTemplate,
    };

    /**
     * DataGrid configuration per entity
     */
    const CMS_CRUD_DATA_GRID_CONFIG = {
        post: {
            templates: {
                title_display: templateHelpers.createTitleDisplayTemplate({ entityLabel: 'Post' }),
                status_badge: templateHelpers.statusBadgeTemplate,
                categories_list: templateHelpers.categoriesListTemplate,
                parent_name_display: templateHelpers.parentNameTemplate,
                featured_image_thumbnail: templateHelpers.featuredImageTemplate({ entityLabel: 'Post' }),
                published_date_format: templateHelpers.publishedDateTemplate,
                actions_dropdown: templateHelpers.actionsDropdownTemplate,
            },
        },
        page: {
            templates: {
                title_display: templateHelpers.createTitleDisplayTemplate({ entityLabel: 'Page' }),
                status_badge: templateHelpers.statusBadgeTemplate,
                parent_name_display: templateHelpers.parentNameTemplate,
                featured_image_thumbnail: templateHelpers.featuredImageTemplate({ entityLabel: 'Page' }),
                published_date_format: templateHelpers.publishedDateTemplate,
                actions_dropdown: templateHelpers.actionsDropdownTemplate,
            },
        },
        category: {
            templates: {
                title_display: templateHelpers.createTitleDisplayTemplate({
                    entityLabel: 'Category',
                    showAuthor: false,
                }),
                status_badge: templateHelpers.statusBadgeTemplate,
                posts_count_badge: templateHelpers.countBadgeTemplate({ keys: ['posts_count', 'category_post_count'] }),
                category_post_count_badge: templateHelpers.countBadgeTemplate({ keys: ['category_post_count'] }),
                parent_name_display: templateHelpers.parentNameTemplate,
                featured_image_thumbnail: templateHelpers.featuredImageTemplate({ entityLabel: 'Category' }),
                date_format: templateHelpers.dateFormatTemplate,
                published_date_format: templateHelpers.publishedDateTemplate,
                actions_dropdown: templateHelpers.actionsDropdownTemplate,
            },
        },
        tag: {
            templates: {
                title_display: templateHelpers.createTitleDisplayTemplate({ entityLabel: 'Tag', showAuthor: false }),
                status_badge: templateHelpers.statusBadgeTemplate,
                tag_post_count_badge: templateHelpers.countBadgeTemplate({ keys: ['tag_post_count'] }),
                featured_image_thumbnail: templateHelpers.featuredImageTemplate({ entityLabel: 'Tag' }),
                date_format: templateHelpers.dateFormatTemplate,
                published_date_format: templateHelpers.publishedDateTemplate,
                actions_dropdown: templateHelpers.actionsDropdownTemplate,
            },
        },
        menu: {
            templates: menuTemplates,
        },
        redirection: {
            templates: redirectionTemplates,
        },
    };

    /**
     * Expose template collections for compatibility
     */
    Object.entries(CMS_CRUD_DATA_GRID_CONFIG).forEach(([entity, config]) => {
        const templateKey = `${entity}DataGridTemplates`;
        window[templateKey] = config.templates;
    });

    class CmsCrudDataGridManager {
        constructor(entity, options = {}) {
            this.entity = entity;
            const alias = ENTITY_ALIASES[entity] || entity;
            const baseConfig = CMS_CRUD_DATA_GRID_CONFIG[entity] || {};

            this.config = {
                dataGridIndex: 0,
                containerId: null,
                urlFragment:
                    {
                        post: '/cms/posts/data',
                        page: '/cms/pages/data',
                        category: '/cms/categories/data',
                        tag: '/cms/tags/data',
                        menu: '/cms/appearance/menus/data',
                        redirection: '/cms/redirections/data',
                    }[entity] || null,
                ...baseConfig,
                ...options,
            };

            this.templates = {
                ...(baseConfig.templates || {}),
                ...(options.templates || {}),
            };

            this.alias = alias;
            this.dataGrid = null;

            // Expose manager reference map
            if (!window.CmsCrud) {
                window.CmsCrud = {};
            }
            if (!window.CmsCrud.dataGridManagers) {
                window.CmsCrud.dataGridManagers = {};
            }
        }

        findDataGridInstance() {
            // v3 API: window.dataGridInstances is an object keyed by container ID
            const instances = window.dataGridInstances || {};
            const allInstances = Object.values(instances);
            if (allInstances.length === 1) {
                return allInstances[0];
            }

            if (this.config.containerId) {
                // Direct lookup by container ID
                if (instances[this.config.containerId]) {
                    return instances[this.config.containerId];
                }
                // Fallback: search through instances
                const match = Object.values(instances).find((instance) => {
                    const container = instance?.container || instance?.el || null;
                    const element = container?.nodeType ? container : container?.[0] || null;
                    if (!element) {
                        return false;
                    }
                    const candidateId = element.id || element.getAttribute?.('id') || '';
                    return candidateId === this.config.containerId;
                });
                if (match) {
                    return match;
                }
            }

            if (this.config.urlFragment) {
                const urlMatch = allInstances.find((instance) => {
                    const url = instance?.config?.get?.('url');
                    return typeof url === 'string' && url.includes(this.config.urlFragment);
                });
                if (urlMatch) {
                    return urlMatch;
                }
            }

            const containers = document.querySelectorAll('[data-datagrid]');
            if (containers.length === 1 && containers[0]?._datagrid) {
                return containers[0]._datagrid;
            }

            return allInstances[this.config.dataGridIndex] || allInstances[0] || null;
        }

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
                    window.CmsCrud.dataGridManagers[this.entity] = this;
                    window[`${this.alias}DataGridManager`] = this;
                }
            } catch (error) {
                console.error(`${this.alias} DataGrid initialization error:`, error);
            }
        }
    }

    /**
     * Form configuration helpers
     */
    const validators = {
        multiSelectNotEmpty(selector, message) {
            const validator = (manager) => {
                const field = manager.form.querySelector(selector);
                if (!field) {
                    return true;
                }

                const hasSelection =
                    (field.selectedOptions && field.selectedOptions.length > 0) ||
                    (Array.isArray(field.value) && field.value.length > 0);

                if (!hasSelection) {
                    field.setCustomValidity(message);
                    field.classList.add('is-invalid');
                    field.classList.remove('is-valid');
                    return false;
                }

                field.setCustomValidity('');
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
                return true;
            };

            validator.attach = (manager) => {
                const field = manager.form.querySelector(selector);
                if (!field) {
                    return;
                }
                const handler = () => validator(manager);
                field.addEventListener('change', handler);
                field.addEventListener('blur', handler);
            };

            return validator;
        },
    };

    const CMS_CRUD_FORM_CONFIG = {
        post: {
            formId: 'post-form',
            fieldLabels: {
                title: 'Post Title',
                slug: 'Slug',
                status: 'Status',
                'categories[]': 'Categories',
                categories: 'Categories',
            },
            statusFieldSelector: '#status',
            enterKey: {
                enabled: false,
                skipEditable: true,
                createButtonSelector: '#save-draft-btn',
                editButtonResolver(manager) {
                    const status = manager.getStatusValue();
                    if (status === 'published') {
                        return manager.form.querySelector('#update-btn') || manager.form.querySelector('#publish-btn');
                    }
                    return manager.form.querySelector('#save-draft-btn');
                },
            },
            validators: [validators.multiSelectNotEmpty('#categories', 'At least one category is required.')],
        },
        page: {
            formId: 'page-form',
            fieldLabels: {
                title: 'Page Title',
                slug: 'Slug',
                status: 'Status',
                parent: 'Parent Page',
            },
            statusFieldSelector: '#status',
            enterKey: {
                enabled: false,
                skipEditable: true,
                createButtonSelector: '#save-draft-btn',
                editButtonResolver(manager) {
                    const status = manager.getStatusValue();
                    if (status === 'published') {
                        return manager.form.querySelector('#update-btn') || manager.form.querySelector('#publish-btn');
                    }
                    return manager.form.querySelector('#save-draft-btn');
                },
            },
        },
        category: {
            formId: 'category-form',
            fieldLabels: {
                title: 'Category Title',
                slug: 'Slug',
                status: 'Status',
                parent: 'Parent Category',
            },
            statusFieldSelector: '#status',
            enterKey: {
                enabled: false,
                skipEditable: true,
                createButtonSelector: '#save-draft-btn',
                editButtonResolver(manager) {
                    const status = manager.getStatusValue();
                    if (status === 'published') {
                        return manager.form.querySelector('#update-btn');
                    }
                    return manager.form.querySelector('#save-draft-btn');
                },
            },
        },
        tag: {
            formId: 'tag-form',
            fieldLabels: {
                title: 'Tag Title',
                slug: 'Slug',
                status: 'Status',
            },
            statusFieldSelector: '#status',
            enterKey: {
                enabled: false,
                skipEditable: true,
                createButtonSelector: '#save-draft-btn',
                editButtonResolver(manager) {
                    const status = manager.getStatusValue();
                    if (status === 'published') {
                        return manager.form.querySelector('#update-btn');
                    }
                    return manager.form.querySelector('#save-draft-btn');
                },
            },
        },
    };

    class CmsCrudFormManager {
        constructor(entity, options = {}) {
            this.entity = entity;
            const alias = ENTITY_ALIASES[entity] || entity;
            const baseConfig = CMS_CRUD_FORM_CONFIG[entity] || {};

            this.config = {
                statusFieldSelector: '#status',
                fieldLabels: {},
                validators: [],
                mode: options.mode || null,
                ...baseConfig,
                ...options,
            };

            this.alias = alias;
            this.mode = this.config.mode;
            this.validators = Array.isArray(this.config.validators) ? this.config.validators : [];
            this.form = document.getElementById(this.config.formId);

            if (!window.CmsCrud) {
                window.CmsCrud = {};
            }
            if (!window.CmsCrud.formManagers) {
                window.CmsCrud.formManagers = {};
            }
        }

        init() {
            if (!this.form) {
                return;
            }

            this.setupFormValidation();
            this.setupSubmitButtons();
            this.setupEnterKeyHandling();
            this.attachValidatorHooks();

            if (typeof this.config.onInit === 'function') {
                this.config.onInit(this);
            }

            this.registerInstance();
        }

        registerInstance() {
            window.CmsCrud.formManagers[this.entity] = this;
            window[`${this.alias}FormManager`] = this;
            this.form._cmsCrudFormManager = this;
            this.form[`_${this.entity}FormManager`] = this;
        }

        attachValidatorHooks() {
            this.validators.forEach((validator) => {
                if (typeof validator.attach === 'function') {
                    validator.attach(this);
                }
            });
        }

        setupFormValidation() {
            this.form.addEventListener('submit', (event) => {
                const customValid = this.runValidators();

                if (!customValid || !this.form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.showValidationErrorAlert();
                }

                this.form.classList.add('was-validated');
            });

            const requiredInputs = this.form.querySelectorAll('[required]');
            requiredInputs.forEach((input) => {
                input.addEventListener('blur', () => this.validateField(input));
            });
        }

        setupSubmitButtons() {
            const submitButtons = this.form.querySelectorAll('button[type="submit"]');

            submitButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();

                    if (button.disabled) {
                        return;
                    }

                    const customValid = this.runValidators();
                    if (!customValid || !this.form.checkValidity()) {
                        event.stopPropagation();
                        this.form.classList.add('was-validated');
                        this.showValidationErrorAlert();
                        return;
                    }

                    this.setStatusFromButton(button);
                    this.showLoadingState(button, submitButtons);
                    this.form.submit();
                });
            });
        }

        setupEnterKeyHandling() {
            const enterKeyConfig = this.config.enterKey || {};
            if (enterKeyConfig.enabled === false) {
                return;
            }

            this.form.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' || event.shiftKey || event.ctrlKey || event.altKey) {
                    return;
                }

                const activeElement = document.activeElement;
                if (enterKeyConfig.skipEditable !== false && this.isEditableField(activeElement)) {
                    return;
                }

                event.preventDefault();
                this.showEnterKeyFeedback();
                const targetButton = this.getEnterKeyTargetButton();
                if (targetButton) {
                    targetButton.click();
                }
            });
        }

        runValidators() {
            if (this.validators.length === 0) {
                return true;
            }

            return this.validators.every((validator) => {
                try {
                    return validator(this) !== false;
                } catch (error) {
                    console.error(`${this.alias} form validator error:`, error);
                    return false;
                }
            });
        }

        validateField(field) {
            if (!field.checkValidity()) {
                field.classList.add('is-invalid');
                field.classList.remove('is-valid');
            } else {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
            }
        }

        isEditableField(element) {
            if (!element) {
                return false;
            }

            return (
                element.tagName === 'TEXTAREA' ||
                element.contentEditable === 'true' ||
                element.classList.contains('note-editable') ||
                element.classList.contains('ql-editor') ||
                element.classList.contains('tox-edit-area') ||
                element.closest('.note-editable') ||
                element.closest('.ql-editor') ||
                element.closest('.tox-edit-area')
            );
        }

        getEnterKeyTargetButton() {
            const enterKeyConfig = this.config.enterKey || {};
            if (typeof enterKeyConfig.getTargetButton === 'function') {
                return enterKeyConfig.getTargetButton(this);
            }

            if (!this.isEditMode() && enterKeyConfig.createButtonSelector) {
                return this.form.querySelector(enterKeyConfig.createButtonSelector);
            }

            if (this.isEditMode()) {
                if (typeof enterKeyConfig.editButtonResolver === 'function') {
                    return enterKeyConfig.editButtonResolver(this);
                }

                if (enterKeyConfig.editButtonSelector) {
                    return this.form.querySelector(enterKeyConfig.editButtonSelector);
                }
            }

            return this.form.querySelector('button[type="submit"]');
        }

        isEditMode() {
            if (this.mode) {
                return this.mode === 'edit';
            }

            const action = this.form.getAttribute('action') || '';
            return action.includes('/edit') || action.includes('/update');
        }

        getStatusField() {
            return this.form.querySelector(this.config.statusFieldSelector || '#status');
        }

        getStatusValue() {
            const statusField = this.getStatusField();
            return statusField ? statusField.value : 'draft';
        }

        setStatusFromButton(button) {
            const statusValue = button.getAttribute('value');
            const statusField = this.getStatusField();
            if (statusField && statusValue) {
                statusField.value = statusValue;
            }
        }

        showLoadingState(button, submitButtons) {
            const btnText = button.querySelector('.btn-text');
            const btnIcon = button.querySelector('i');
            if (btnText && btnIcon) {
                button.disabled = true;
                btnText.textContent = 'Processing...';
                btnIcon.className = 'ri-hourglass-2-line-split me-2';
            }

            submitButtons.forEach((btn) => (btn.disabled = true));
        }

        showValidationErrorAlert() {
            const invalidFields = this.form.querySelectorAll(':invalid');
            const fieldLabels = this.config.fieldLabels || {};

            const friendlyFieldNames = Array.from(invalidFields)
                .map(
                    (field) =>
                        fieldLabels[field.name] || fieldLabels[field.id] || field.name.replace(/[_\[\]]+/g, ' ').trim()
                )
                .filter((value, index, self) => value && self.indexOf(value) === index);

            if (friendlyFieldNames.length === 0) {
                return;
            }

            const errorSummary =
                friendlyFieldNames.length === 1
                    ? `Please check the ${friendlyFieldNames[0]} field.`
                    : `Please check the following fields: ${friendlyFieldNames.join(', ')}.`;

            this.showAlert('error', errorSummary);

            if (invalidFields.length > 0) {
                const firstInvalid = invalidFields[0];
                firstInvalid.focus();
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        showAlert(type, message) {
            const existingServerAlerts = document.querySelectorAll('.alert:not(.client-alert)');
            if (existingServerAlerts.length > 0) {
                return;
            }

            const existingClientAlerts = document.querySelectorAll('.client-alert');
            existingClientAlerts.forEach((alert) => alert.remove());

            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const iconClass = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
            const alertTitle = type === 'success' ? 'Success!' : 'Validation Error!';

            const alert = document.createElement('div');
            alert.className = `alert ${alertClass} alert-dismissible fade rounded-4 show client-alert`;
            alert.setAttribute('role', 'alert');
            alert.innerHTML = `
                <div class="d-flex align-items-start">
                    <div class="alert-icon me-3 flex-shrink-0">
                        <i class="bi ${iconClass}" style="font-size: 1.25rem;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="fw-semibold mb-2">${alertTitle}</h5>
                        <p class="mb-0">${message}</p>
                    </div>
                </div>
                <button class="btn-close" data-bs-dismiss="alert" type="button" aria-label="Close"></button>
            `;

            this.form.parentNode.insertBefore(alert, this.form);
            alert.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        showEnterKeyFeedback() {
            const targetButton = this.getEnterKeyTargetButton();
            if (targetButton) {
                targetButton.style.transform = 'scale(0.98)';
                targetButton.style.transition = 'transform 0.1s ease';
                setTimeout(() => {
                    targetButton.style.transform = '';
                }, 120);
            }
        }
    }

    /**
     * Global namespace exposure
     */
    window.CmsCrud = window.CmsCrud || {};

    window.CmsCrud.dataGridConfigs = CMS_CRUD_DATA_GRID_CONFIG;
    window.CmsCrud.formConfigs = CMS_CRUD_FORM_CONFIG;

    window.CmsCrud.initDataGrid = function initDataGrid(entity, options = {}) {
        const manager = new CmsCrudDataGridManager(entity, options);
        manager.initialize();
        return manager;
    };

    window.CmsCrud.initForm = function initForm(entity, options = {}) {
        const manager = new CmsCrudFormManager(entity, options);
        if (manager.form) {
            manager.init();
        } else {
            console.warn(`CMS CRUD form not found for entity "${entity}" using form ID "${manager.config?.formId}"`);
        }
        return manager;
    };
})();
