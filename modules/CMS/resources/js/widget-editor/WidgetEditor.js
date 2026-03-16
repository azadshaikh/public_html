/**
 * Widget Editor - Core Class
 * Consistent with Menu Editor architecture.
 * Uses native drag & drop, no external dependencies.
 */

import { DragDrop } from './DragDrop.js';
import { ItemRenderer } from './ItemRenderer.js';
import { Toast } from './Toast.js';
import { escapeHtml, generateWidgetId, getWidgetDisplayName, getDefaultSettings } from './utils.js';

export class WidgetEditor {
    constructor(options = {}) {
        this.widgetUrl = options.widgetUrl || window.widgetUrl;
        this.widgetAreas = options.widgetAreas || window.widgetAreas || [];
        this.availableWidgets = options.availableWidgets || window.availableWidgets || {};
        this.currentWidgets = options.currentWidgets || window.currentWidgets || {};

        // State
        this.widgets = new Map(); // areaId -> widgets[]
        this.originalState = new Map();
        this.hasChanges = false;
        this.pendingSave = false;
        this.currentEditWidget = null;

        // Modules
        this.toast = new Toast();
        this.dragDrop = null;

        // DOM cache
        this.elements = {};

        this.init();
    }

    init() {
        try {
            this.cacheElements();
            this.initializeState();
            this.bindEvents();
            this.bindKeyboardShortcuts();
            this.initDragDrop();

            // Show session message if any
            if (window.sessionMessage) {
                this.toast.show(window.sessionMessage.type, window.sessionMessage.message);
            }

            console.log('[WidgetEditor] Initialized successfully');
        } catch (error) {
            console.error('WidgetEditor initialization error:', error);
            this.toast.error('Failed to initialize widget editor. Please refresh the page.');
        }
    }

    // =========================================================================
    // DOM CACHING
    // =========================================================================

    cacheElements() {
        const selectors = {
            container: '#widget-editor-container',
            areaContainer: '.widget-area-container',
            saveButton: '#save-all-widgets',
            saveFloatingButton: '#save-widgets-floating',
            discardChangesButton: '#discard-widget-changes',
            changesIndicator: '#widget-changes-indicator',
            floatingSaveBar: '#floating-widget-save-bar',
            itemsCount: '#widget-items-count-structure',
        };

        Object.entries(selectors).forEach(([key, selector]) => {
            this.elements[key] = document.querySelector(selector);
        });
    }

    // =========================================================================
    // STATE MANAGEMENT
    // =========================================================================

    initializeState() {
        this.widgets.clear();
        this.originalState.clear();

        this.widgetAreas.forEach((area) => {
            const areaWidgets = (this.currentWidgets[area.id] || [])
                .filter((w) => w && w.id && w.type)
                .map((widget, index) => ({
                    id: widget.id,
                    type: widget.type,
                    title: widget.title || '',
                    settings: widget.settings || {},
                    position: widget.position !== undefined ? widget.position : index,
                    isNew: false,
                    isModified: false,
                    isDeleted: false,
                }));

            this.widgets.set(area.id, areaWidgets);
            this.originalState.set(area.id, JSON.stringify(areaWidgets));
        });

        this.hasChanges = false;
        const editingAreaId = this.widgetAreas[0]?.id;
        if (editingAreaId) {
            this.updateWidgetCount(editingAreaId);
        }
        this.updateChangesIndicator();
    }

    updateOriginalState() {
        // Update original state to match current state after successful save
        this.widgets.forEach((widgetList, areaId) => {
            const currentWidgets = widgetList
                .filter((w) => !w.isDeleted)
                .sort((a, b) => a.position - b.position)
                .map((w, index) => ({
                    ...w,
                    position: index,
                    isNew: false,
                    isModified: false,
                }));
            this.widgets.set(areaId, currentWidgets);
            this.originalState.set(areaId, JSON.stringify(currentWidgets));
        });
    }

    markAsChanged() {
        this.hasChanges = true;
        this.updateChangesIndicator();
    }

    updateChangesIndicator() {
        if (this.elements.changesIndicator) {
            this.elements.changesIndicator.style.display = this.hasChanges ? 'inline-block' : 'none';
        }

        if (this.elements.floatingSaveBar) {
            this.elements.floatingSaveBar.style.display = this.hasChanges ? 'block' : 'none';
        }
    }

    getAreaWidgets(areaId) {
        return this.widgets.get(areaId)?.filter((w) => !w.isDeleted) || [];
    }

    findWidget(widgetId) {
        for (const [areaId, widgets] of this.widgets) {
            const widget = widgets.find((w) => w.id === widgetId && !w.isDeleted);
            if (widget) return { widget, areaId };
        }
        return null;
    }

    getAreaName(areaId) {
        const area = this.widgetAreas.find((a) => a.id === areaId);
        return area?.name || areaId;
    }

    updateWidgetCount(areaId) {
        const count = this.getAreaWidgets(areaId).length;
        if (this.elements.itemsCount) {
            this.elements.itemsCount.textContent = `${count} item${count !== 1 ? 's' : ''}`;
            return;
        }

        const fallbackCountEl = document.querySelector(`[data-area-id="${areaId}"] .widget-count`);
        if (fallbackCountEl) {
            fallbackCountEl.textContent = count;
        }
    }

    // =========================================================================
    // DRAG & DROP
    // =========================================================================

    initDragDrop() {
        // Always query fresh
        const container = document.querySelector('.widget-area-container[data-area-id]');
        if (!container) {
            console.warn('[WidgetEditor] No widget area container found for drag-drop');
            return;
        }

        // Update cached reference
        this.elements.areaContainer = container;
        console.log('[WidgetEditor] initDragDrop - container found:', container.getAttribute('data-area-id'));

        this.dragDrop = new DragDrop({
            container,
            onReorder: () => {
                this.updateWidgetOrder();
                this.markAsChanged();
            },
        });
    }

    updateWidgetOrder() {
        const container = document.querySelector('.widget-area-container[data-area-id]');
        if (!container) return;

        const areaId = container.getAttribute('data-area-id');
        const widgets = this.widgets.get(areaId);
        if (!widgets) return;

        const widgetElements = container.querySelectorAll('.widget-item');
        widgetElements.forEach((el, index) => {
            const widgetId = el.dataset.widgetId;
            const widget = widgets.find((w) => w.id === widgetId);
            if (widget) {
                widget.position = index;
            }
        });
    }

    // =========================================================================
    // EVENT BINDING
    // =========================================================================

    bindEvents() {
        // Delegate click events on the main container (always visible)
        const mainContainer = document.querySelector('#widget-editor-container');
        if (mainContainer) {
            mainContainer.addEventListener('click', (e) => this.handleContainerClick(e));
        }

        // Available widget add buttons
        document.querySelectorAll('.add-widget-btn').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleAddWidget(e);
            });
        });

        // Save button
        this.elements.saveButton?.addEventListener('click', () => this.saveWidgets());
        this.elements.saveFloatingButton?.addEventListener('click', () => this.saveWidgets());
        this.elements.discardChangesButton?.addEventListener('click', () => this.discardChanges());

        // Initialize modals
        this.initReorderModal();
        this.initEditModal();
        this.initDeleteModal();
    }

    initReorderModal() {
        const reorderModal = document.querySelector('#widgetReorderModal');
        if (!reorderModal || typeof bootstrap === 'undefined') return;

        this.reorderModal = new bootstrap.Modal(reorderModal);
        this.reorderModalEl = reorderModal;

        // Handle reorder button clicks
        reorderModal.querySelectorAll('.reorder-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (btn.disabled) return;

                const widgetId = document.querySelector('#reorder-widget-id').value;
                const direction = btn.dataset.direction;

                if (direction === 'up') {
                    this.moveWidget(widgetId, 'up');
                } else if (direction === 'down') {
                    this.moveWidget(widgetId, 'down');
                }

                // Update button states
                this.updateReorderButtonStates(widgetId);
            });
        });

        // Handle drag handle click on mobile (touch devices)
        this.elements.areaContainer?.addEventListener('click', (e) => {
            const dragHandle = e.target.closest('.widget-drag-handle');
            if (!dragHandle) return;

            // Only open modal on touch devices (no pointer: fine)
            if (window.matchMedia('(pointer: coarse)').matches) {
                e.preventDefault();
                e.stopPropagation();
                const widgetItem = dragHandle.closest('.widget-item');
                if (widgetItem) {
                    this.openReorderModal(widgetItem.dataset.widgetId);
                }
            }
        });
    }

    openReorderModal(widgetId) {
        const result = this.findWidget(widgetId);
        if (!result) return;

        document.querySelector('#reorder-widget-id').value = widgetId;
        this.updateReorderModalTitle(widgetId);
        this.updateReorderButtonStates(widgetId);
        this.reorderModal?.show();
    }

    updateReorderModalTitle(widgetId) {
        const result = this.findWidget(widgetId);
        const titleEl = document.querySelector('#reorder-widget-title');
        if (result && titleEl) {
            titleEl.textContent = result.widget.title || 'Widget';
        }
    }

    updateReorderButtonStates(widgetId) {
        const widgetEl = document.querySelector(`.widget-item[data-widget-id="${widgetId}"]`);
        if (!widgetEl || !this.reorderModalEl) return;

        const parent = widgetEl.parentElement;
        const siblings = Array.from(parent.querySelectorAll(':scope > .widget-item'));
        const currentIndex = siblings.indexOf(widgetEl);
        const isFirst = currentIndex === 0;
        const isLast = currentIndex === siblings.length - 1;

        const upBtn = this.reorderModalEl.querySelector('[data-direction="up"]');
        const downBtn = this.reorderModalEl.querySelector('[data-direction="down"]');

        if (upBtn) upBtn.disabled = isFirst;
        if (downBtn) downBtn.disabled = isLast;
    }

    bindKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+S to save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                this.saveWidgets();
            }
        });
    }

    // =========================================================================
    // EVENT HANDLERS
    // =========================================================================

    handleContainerClick(e) {
        const target = e.target.closest('button');
        if (!target) return;

        const widgetEl = target.closest('.widget-item');
        if (!widgetEl) return;

        const widgetId = widgetEl.dataset.widgetId;

        if (target.classList.contains('edit-widget')) {
            this.openEditModal(widgetId);
        } else if (target.classList.contains('remove-widget')) {
            this.showRemoveConfirmation(widgetId);
        }
    }

    handleAddWidget(e) {
        const btn = e.currentTarget;
        const widgetKey = btn.dataset.widgetKey;

        // Always query fresh - don't rely on cached reference
        const areaContainer = document.querySelector('.widget-area-container[data-area-id]');
        const areaId = areaContainer?.getAttribute('data-area-id');

        console.log('[WidgetEditor] handleAddWidget:', widgetKey, areaId, areaContainer);

        if (!widgetKey || !areaId) {
            console.error('[WidgetEditor] Missing widgetKey or areaId:', widgetKey, areaId);
            this.toast.error('Invalid widget configuration');
            return;
        }

        const widgetInfo = this.availableWidgets[widgetKey];
        if (!widgetInfo) {
            console.error(
                '[WidgetEditor] Widget type not found:',
                widgetKey,
                'Available:',
                Object.keys(this.availableWidgets)
            );
            this.toast.error(`Widget type '${widgetKey}' is not available`);
            return;
        }

        // Update cached reference if it was missing
        if (!this.elements.areaContainer) {
            this.elements.areaContainer = areaContainer;
        }

        this.addWidget(areaId, widgetKey);

        // Visual feedback - flash "added" state
        btn.classList.add('added');
        setTimeout(() => btn.classList.remove('added'), 600);
    }

    moveWidget(widgetId, direction) {
        const widgetEl = document.querySelector(`.widget-item[data-widget-id="${widgetId}"]`);
        if (!widgetEl) return;

        const parent = widgetEl.parentElement;
        const siblings = Array.from(parent.querySelectorAll(':scope > .widget-item'));
        const currentIndex = siblings.indexOf(widgetEl);

        if (direction === 'up' && currentIndex > 0) {
            parent.insertBefore(widgetEl, siblings[currentIndex - 1]);
            this.updateWidgetOrder();
            this.markAsChanged();
        } else if (direction === 'down' && currentIndex < siblings.length - 1) {
            parent.insertBefore(siblings[currentIndex + 1], widgetEl);
            this.updateWidgetOrder();
            this.markAsChanged();
        }
    }

    // =========================================================================
    // WIDGET OPERATIONS
    // =========================================================================

    addWidget(areaId, widgetKey) {
        const widgetInfo = this.availableWidgets[widgetKey];
        const defaultSettings = getDefaultSettings(widgetKey);

        const newWidget = {
            id: generateWidgetId(),
            type: widgetKey,
            title: `New ${widgetInfo.name}`,
            settings: { ...defaultSettings },
            position: this.getAreaWidgets(areaId).length,
            isNew: true,
            isModified: false,
            isDeleted: false,
        };

        const areaWidgets = this.widgets.get(areaId) || [];
        areaWidgets.push(newWidget);
        this.widgets.set(areaId, areaWidgets);

        this.renderNewWidget(areaId, newWidget);
        this.hideEmptyState(areaId);
        this.updateWidgetCount(areaId);
        this.markAsChanged();
        this.toast.success(`${widgetInfo.name} added. Click "Save" to save changes.`);

        // Auto-open edit modal for new widget
        setTimeout(() => this.openEditModal(newWidget.id), 100);
    }

    renderNewWidget(areaId, widget) {
        const container = this.elements.areaContainer;
        if (!container) return;

        const html = ItemRenderer.render(widget);
        container.insertAdjacentHTML('beforeend', html);
    }

    // =========================================================================
    // EDIT MODAL
    // =========================================================================

    initEditModal() {
        const modal = document.querySelector('#widgetEditModal');
        if (!modal || typeof bootstrap === 'undefined') return;

        this.editModal = new bootstrap.Modal(modal);
        this.editModalEl = modal;

        // Handle form submission
        const form = modal.querySelector('#widgetEditForm');
        form?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleEditFormSubmit();
        });
    }

    initDeleteModal() {
        const modal = document.querySelector('#widgetDeleteModal');
        if (!modal || typeof bootstrap === 'undefined') return;

        this.deleteModal = new bootstrap.Modal(modal);
        this.deleteModalEl = modal;

        // Handle confirm delete button
        const confirmBtn = modal.querySelector('#confirm-delete-widget');
        confirmBtn?.addEventListener('click', () => {
            const widgetId = document.querySelector('#delete-widget-id')?.value;
            if (widgetId) {
                this.removeWidget(widgetId);
                this.deleteModal.hide();
            }
        });
    }

    openEditModal(widgetId) {
        const result = this.findWidget(widgetId);
        if (!result) {
            this.toast.error('Widget not found');
            return;
        }

        this.currentEditWidget = result;
        this.populateEditModal(result.widget, result.areaId);

        if (this.editModal) {
            this.editModal.show();
        }
    }

    populateEditModal(widget, areaId) {
        // Set hidden fields
        document.querySelector('#edit-widget-id').value = widget.id;
        document.querySelector('#edit-area-id').value = areaId;

        // Set title
        const titleField = document.querySelector('#widget-title');
        if (titleField) {
            titleField.value = widget.title || '';
            titleField.classList.remove('is-invalid');
        }

        // Update modal title
        const widgetInfo = this.availableWidgets[widget.type];
        const modalTitle = document.querySelector('#widgetEditModalLabel');
        if (modalTitle) {
            modalTitle.textContent = `Edit ${widgetInfo?.name || 'Widget'}`;
        }

        // Generate settings form
        const settingsContainer = document.querySelector('#widget-settings-container');
        if (settingsContainer) {
            settingsContainer.innerHTML = this.generateSettingsForm(widget.type, widget.settings);
        }
    }

    generateSettingsForm(type, settings = {}) {
        const widgetInfo = this.availableWidgets[type];

        if (!widgetInfo?.settings_schema) {
            return '<p class="text-muted">No additional settings for this widget.</p>';
        }

        let formHTML = '';
        const schema = widgetInfo.settings_schema;

        Object.entries(schema).forEach(([fieldName, fieldConfig]) => {
            // Skip title field (handled separately)
            if (fieldName === 'title') return;

            const fieldId = `widget-${fieldName}`;
            const value = settings[fieldName] !== undefined ? settings[fieldName] : fieldConfig.default || '';
            const required = fieldConfig.required ? 'required' : '';
            const requiredStar = fieldConfig.required ? '<span class="text-danger">*</span>' : '';
            const helpText = fieldConfig.description
                ? `<div class="form-text">${escapeHtml(fieldConfig.description)}</div>`
                : '';

            switch (fieldConfig.type) {
                case 'text':
                    formHTML += `
                        <div class="mb-3">
                            <label for="${fieldId}" class="form-label">${escapeHtml(fieldConfig.label)} ${requiredStar}</label>
                            <input type="text" class="form-control" id="${fieldId}" data-setting="${fieldName}" value="${escapeHtml(String(value))}" ${required}>
                            <div class="invalid-feedback" id="${fieldId}-error"></div>
                            ${helpText}
                        </div>
                    `;
                    break;

                case 'textarea':
                    formHTML += `
                        <div class="mb-3">
                            <label for="${fieldId}" class="form-label">${escapeHtml(fieldConfig.label)} ${requiredStar}</label>
                            <textarea class="form-control" id="${fieldId}" data-setting="${fieldName}" rows="4" ${required}>${escapeHtml(String(value))}</textarea>
                            <div class="invalid-feedback" id="${fieldId}-error"></div>
                            ${helpText}
                        </div>
                    `;
                    break;

                case 'color':
                    formHTML += `
                        <div class="mb-3">
                            <label for="${fieldId}" class="form-label">${escapeHtml(fieldConfig.label)} ${requiredStar}</label>
                            <input type="color" class="form-control form-control-color" id="${fieldId}" data-setting="${fieldName}" value="${escapeHtml(String(value) || '#000000')}" ${required}>
                            <div class="invalid-feedback" id="${fieldId}-error"></div>
                            ${helpText}
                        </div>
                    `;
                    break;

                case 'select':
                    let optionsHTML = '';
                    if (fieldConfig.options) {
                        Object.entries(fieldConfig.options).forEach(([optionValue, optionLabel]) => {
                            const selected = String(value) === optionValue ? 'selected' : '';
                            optionsHTML += `<option value="${escapeHtml(optionValue)}" ${selected}>${escapeHtml(optionLabel)}</option>`;
                        });
                    }
                    formHTML += `
                        <div class="mb-3">
                            <label for="${fieldId}" class="form-label">${escapeHtml(fieldConfig.label)} ${requiredStar}</label>
                            <select class="form-control" id="${fieldId}" data-setting="${fieldName}" ${required}>
                                ${optionsHTML}
                            </select>
                            <div class="invalid-feedback" id="${fieldId}-error"></div>
                            ${helpText}
                        </div>
                    `;
                    break;

                case 'checkbox':
                    const checked = value ? 'checked' : '';
                    formHTML += `
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="${fieldId}" data-setting="${fieldName}" data-type="checkbox" ${checked}>
                                <label class="form-check-label" for="${fieldId}">
                                    ${escapeHtml(fieldConfig.label)}
                                </label>
                            </div>
                            <div class="invalid-feedback d-block" id="${fieldId}-error"></div>
                            ${helpText}
                        </div>
                    `;
                    break;

                case 'url':
                    formHTML += `
                        <div class="mb-3">
                            <label for="${fieldId}" class="form-label">${escapeHtml(fieldConfig.label)} ${requiredStar}</label>
                            <input type="url" class="form-control" id="${fieldId}" data-setting="${fieldName}" value="${escapeHtml(String(value))}" ${required} placeholder="https://example.com">
                            <div class="invalid-feedback" id="${fieldId}-error"></div>
                            ${helpText}
                        </div>
                    `;
                    break;

                case 'number':
                    formHTML += `
                        <div class="mb-3">
                            <label for="${fieldId}" class="form-label">${escapeHtml(fieldConfig.label)} ${requiredStar}</label>
                            <input type="number" class="form-control" id="${fieldId}" data-setting="${fieldName}" value="${escapeHtml(String(value))}" ${required} ${fieldConfig.min !== undefined ? `min="${fieldConfig.min}"` : ''} ${fieldConfig.max !== undefined ? `max="${fieldConfig.max}"` : ''}>
                            <div class="invalid-feedback" id="${fieldId}-error"></div>
                            ${helpText}
                        </div>
                    `;
                    break;

                default:
                    formHTML += `
                        <div class="mb-3">
                            <label for="${fieldId}" class="form-label">${escapeHtml(fieldConfig.label)} ${requiredStar}</label>
                            <input type="text" class="form-control" id="${fieldId}" data-setting="${fieldName}" value="${escapeHtml(String(value))}" ${required}>
                            <div class="invalid-feedback" id="${fieldId}-error"></div>
                            ${helpText}
                        </div>
                    `;
                    break;
            }
        });

        return formHTML || '<p class="text-muted">No additional settings for this widget.</p>';
    }

    handleEditFormSubmit() {
        const widgetId = document.querySelector('#edit-widget-id')?.value;
        const titleField = document.querySelector('#widget-title');
        const title = titleField?.value?.trim();

        if (!title) {
            titleField?.classList.add('is-invalid');
            document.querySelector('#widget-title-error').textContent = 'Title is required';
            return;
        }

        titleField?.classList.remove('is-invalid');

        // Collect settings from form
        const settings = {};
        const settingsContainer = document.querySelector('#widget-settings-container');
        settingsContainer?.querySelectorAll('[data-setting]').forEach((input) => {
            const settingName = input.dataset.setting;
            if (input.dataset.type === 'checkbox' || input.type === 'checkbox') {
                settings[settingName] = input.checked;
            } else {
                settings[settingName] = input.value;
            }
        });

        this.saveWidgetSettings(widgetId, title, settings);
        this.editModal?.hide();
    }

    saveWidgetSettings(widgetId, title, settings) {
        const result = this.findWidget(widgetId);
        if (!result) {
            this.toast.error('Widget not found');
            return false;
        }

        result.widget.title = title;
        result.widget.settings = { ...result.widget.settings, ...settings };
        result.widget.isModified = true;

        // Update DOM
        ItemRenderer.updateDOM(widgetId, result.widget);

        this.markAsChanged();
        this.toast.success('Widget updated. Click "Save" to save changes.');
        return true;
    }

    // =========================================================================
    // REMOVE
    // =========================================================================

    showRemoveConfirmation(widgetId) {
        const result = this.findWidget(widgetId);
        if (!result) return;

        // Set widget info in modal
        document.querySelector('#delete-widget-id').value = widgetId;
        document.querySelector('#delete-widget-title').textContent = result.widget.title || 'this widget';

        // Show modal
        if (this.deleteModal) {
            this.deleteModal.show();
        }
    }

    removeWidget(widgetId) {
        const result = this.findWidget(widgetId);
        if (!result) return;

        const { widget, areaId } = result;

        // Mark as deleted or remove from state
        if (widget.isNew) {
            const areaWidgets = this.widgets.get(areaId);
            const idx = areaWidgets.findIndex((w) => w.id === widgetId);
            if (idx !== -1) areaWidgets.splice(idx, 1);
        } else {
            widget.isDeleted = true;
        }

        // Remove from DOM
        const widgetEl = document.querySelector(`.widget-item[data-widget-id="${widgetId}"]`);
        if (widgetEl) {
            widgetEl.remove();
        }

        // Check if area is now empty
        if (this.getAreaWidgets(areaId).length === 0) {
            this.showEmptyState(areaId);
        }

        this.updateWidgetCount(areaId);
        this.markAsChanged();
        this.toast.success('Widget removed. Click "Save" to save changes.');
    }

    // =========================================================================
    // SAVE
    // =========================================================================

    async saveWidgets() {
        if (this.pendingSave) {
            this.toast.info('Save in progress...');
            return;
        }

        if (!this.hasChanges) {
            this.toast.info('No changes to save');
            return;
        }

        this.pendingSave = true;

        const saveButtons = [this.elements.saveButton, this.elements.saveFloatingButton].filter(Boolean);
        const originalButtonContent = new Map(saveButtons.map((button) => [button, button.innerHTML]));

        try {
            saveButtons.forEach((button) => {
                button.disabled = true;
                button.innerHTML = '<i class="ri-loader-4-line spin me-1"></i> Saving...';
            });

            const payload = this.buildSavePayload();
            const response = await this.saveToServer(payload);

            if (response.success) {
                this.hasChanges = false;
                this.updateChangesIndicator();
                this.updateOriginalState();
                this.toast.success(response.message || 'Widgets saved successfully!');
            } else {
                throw new Error(response.message || 'Failed to save widgets');
            }
        } catch (error) {
            console.error('Save error:', error);
            this.toast.error(error.message || 'Failed to save widgets');
        } finally {
            this.pendingSave = false;
            saveButtons.forEach((button) => {
                button.disabled = false;
                button.innerHTML = originalButtonContent.get(button);
            });
        }
    }

    buildSavePayload() {
        const widgets = {};

        this.widgets.forEach((widgetList, areaId) => {
            widgets[areaId] = widgetList
                .filter((w) => !w.isDeleted)
                .sort((a, b) => a.position - b.position) // Sort by position to preserve drag-drop order
                .map((w, index) => ({
                    id: w.isNew ? null : w.id,
                    type: w.type,
                    title: w.title,
                    settings: w.settings,
                    position: index,
                }));
        });

        return { widgets };
    }

    async saveToServer(payload) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        const response = await fetch(this.widgetUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (!response.ok) {
            const error = new Error(data.message || 'Server error');
            error.status = response.status;
            throw error;
        }

        return data;
    }

    discardChanges() {
        if (!this.hasChanges) {
            this.toast.info('No changes to discard');
            return;
        }

        if (!confirm('Discard all unsaved widget changes?')) {
            return;
        }

        window.location.reload();
    }

    // =========================================================================
    // UI HELPERS
    // =========================================================================

    hideEmptyState(areaId) {
        const container = document.querySelector(`[data-area-id="${areaId}"]`);
        const emptyState = container?.querySelector('.empty-area-message');
        if (emptyState) {
            emptyState.style.display = 'none';
        }
    }

    showEmptyState(areaId) {
        const container = document.querySelector(`[data-area-id="${areaId}"]`);
        if (!container) return;

        let emptyState = container.querySelector('.empty-area-message');
        if (emptyState) {
            emptyState.style.display = 'block';
        } else {
            container.innerHTML = ItemRenderer.renderEmptyState();
        }
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    hasUnsavedChanges() {
        return this.hasChanges;
    }

    destroy() {
        this.dragDrop?.destroy();
        if (window.widgetEditor === this) {
            delete window.widgetEditor;
        }
    }
}
