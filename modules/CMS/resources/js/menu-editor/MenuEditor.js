/**
 * Menu Editor - Core Class
 */

import { DragDrop } from './DragDrop.js';
import { ItemRenderer } from './ItemRenderer.js';
import { Modals } from './Modals.js';
import { Toast } from './Toast.js';
import { escapeHtml, getItemDepth } from './utils.js';

export class MenuEditor {
    constructor(options = {}) {
        this.menuUrl = options.menuUrl || window.menuUrl;
        this.settings = options.settings || window.menuSettings || { supportsHierarchy: true, maxDepth: 3 };

        // State
        this.items = [];
        this.hasChanges = false;
        this.nextTempId = Date.now() * -1;
        this.pendingSave = false;
        this.itemToDelete = null;
        this.initialSettings = null;

        // Modules
        this.toast = new Toast();
        this.modals = new Modals();
        this.dragDrop = null;

        // DOM cache
        this.elements = {};

        this.init();
    }

    init() {
        this.cacheElements();
        this.initializeState();
        this.modals.init();
        this.initDragDrop();
        this.bindEvents();
        this.bindKeyboardShortcuts();

        // Store initial settings
        this.initialSettings = this.getCurrentSettings();

        // Show session message if any
        if (window.sessionMessage) {
            this.toast.show(window.sessionMessage.type, window.sessionMessage.message);
        }
    }

    // =========================================================================
    // DOM CACHING
    // =========================================================================

    cacheElements() {
        const selectors = {
            menuBuilder: '#menu-builder',
            menuItems: '#menu-items',
            nameInput: '#name',
            descriptionInput: '#description',
            locationInput: '#location',
            isActiveInput: '#is_active',
            saveButton: '#save-entire-menu',
            saveInlineButton: '#save-menu-inline',
            saveFloatingButton: '#save-menu-floating',
            discardChangesButton: '#discard-menu-changes',
            changesIndicator: '#changes-indicator',
            floatingSaveBar: '#floating-save-bar',
            customForm: '#add-custom-item-form',
            emptyState: '#empty-state',
            itemsCount: '#menu-items-count-structure',
        };

        Object.entries(selectors).forEach(([key, selector]) => {
            this.elements[key] = document.querySelector(selector);
        });
    }

    // =========================================================================
    // STATE MANAGEMENT
    // =========================================================================

    initializeState() {
        const existingItems = document.querySelectorAll('#menu-builder .menu-item');
        this.items = Array.from(existingItems).map((element) => this.parseItemFromDOM(element));
    }

    parseItemFromDOM(element) {
        return {
            id: parseInt(element.dataset.id),
            title: element.querySelector('.menu-item-title')?.textContent?.trim() || '',
            url: element.dataset.url || '',
            type: element.dataset.type || 'custom',
            is_active: element.dataset.isActive === '1',
            target: element.dataset.target || '_self',
            css_classes: element.dataset.cssClasses || '',
            description: element.dataset.description || '',
            object_id: element.dataset.objectId ? parseInt(element.dataset.objectId) : null,
            link_title: element.dataset.linkTitle || '',
            link_rel: element.dataset.linkRel || '',
            icon: element.dataset.icon || '',
            sort_order: 0,
            parent_id: null,
            isNew: false,
            isDeleted: false,
            isModified: false,
        };
    }

    getCurrentSettings() {
        return {
            name: this.elements.nameInput?.value || '',
            location: this.elements.locationInput?.value || '',
            is_active: this.elements.isActiveInput?.value === '1',
            description: this.elements.descriptionInput?.value || '',
        };
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

    findItem(itemId) {
        return this.items.find((i) => i.id == itemId && !i.isDeleted);
    }

    getVisibleItems() {
        return this.items.filter((item) => !item.isDeleted);
    }

    updateItemCount() {
        if (this.elements.itemsCount) {
            const count = this.getVisibleItems().length;
            this.elements.itemsCount.textContent = `${count} item${count !== 1 ? 's' : ''}`;
        }
    }

    // =========================================================================
    // DRAG & DROP
    // =========================================================================

    initDragDrop() {
        const container = this.elements.menuItems || this.elements.menuBuilder;
        console.log('[MenuEditor] initDragDrop - menuItems:', this.elements.menuItems);
        console.log('[MenuEditor] initDragDrop - menuBuilder:', this.elements.menuBuilder);
        console.log('[MenuEditor] initDragDrop - using container:', container);

        if (!container) {
            console.warn('[MenuEditor] No container found for drag-drop');
            return;
        }

        this.dragDrop = new DragDrop({
            container,
            settings: this.settings,
            onReorder: () => {
                this.markAsChanged();
            },
        });
    }

    // =========================================================================
    // EVENT BINDING
    // =========================================================================

    bindEvents() {
        // Delegate click events on menu builder
        this.elements.menuBuilder?.addEventListener('click', (e) => this.handleMenuBuilderClick(e));

        // Custom item form
        if (this.elements.customForm) {
            this.elements.customForm.addEventListener('submit', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.handleAddCustomItem();
                return false;
            });
        }

        // Page/Category/Tag item buttons
        document.querySelectorAll('.add-page-item').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleAddPageItem(e);
            });
        });

        // Save buttons
        this.elements.saveButton?.addEventListener('click', () => this.saveMenu());
        this.elements.saveInlineButton?.addEventListener('click', () => this.saveMenu());
        this.elements.saveFloatingButton?.addEventListener('click', () => this.saveMenu());
        this.elements.discardChangesButton?.addEventListener('click', () => this.discardChanges());

        // Modal buttons
        document.querySelector('#save-item-btn')?.addEventListener('click', () => this.saveMenuItem());
        document.querySelector('#add-item-btn')?.addEventListener('click', () => this.handleAddFromModal());
        document.querySelector('#confirm-delete-btn')?.addEventListener('click', () => this.executeDelete());

        // Track settings changes
        ['name', 'location', 'is_active', 'description'].forEach((id) => {
            const input = document.querySelector(`#${id}`);
            if (!input) return;

            input.addEventListener('change', () => this.markAsChanged());
        });

        // Search filtering
        this.bindSearchFilters();

        // Inline editing
        this.elements.menuBuilder?.addEventListener('dblclick', (e) => this.handleInlineEdit(e));

        // Mobile reorder modal
        this.initReorderModal();
    }

    initReorderModal() {
        const reorderModal = document.querySelector('#reorderModal');
        if (!reorderModal || typeof bootstrap === 'undefined') return;

        this.reorderModal = new bootstrap.Modal(reorderModal);
        this.reorderModalEl = reorderModal;

        // Handle reorder button clicks
        reorderModal.querySelectorAll('.reorder-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (btn.disabled) return;

                const itemId = document.querySelector('#reorder-item-id').value;
                const direction = btn.dataset.direction;

                switch (direction) {
                    case 'up':
                        this.moveItem(itemId, 'up');
                        break;
                    case 'down':
                        this.moveItem(itemId, 'down');
                        break;
                    case 'left':
                        this.outdentItem(itemId);
                        break;
                    case 'right':
                        this.indentItem(itemId);
                        break;
                }

                // Close modal after action
                this.reorderModal?.hide();
            });
        });

        // Handle drag handle click on mobile (touch devices)
        this.elements.menuBuilder?.addEventListener('click', (e) => {
            const dragHandle = e.target.closest('.menu-item-drag-handle');
            if (!dragHandle) return;

            // Only open modal on touch devices (no pointer: fine)
            if (window.matchMedia('(pointer: coarse)').matches) {
                e.preventDefault();
                e.stopPropagation();
                this.openReorderModal(dragHandle.dataset.id);
            }
        });
    }

    openReorderModal(itemId) {
        const item = this.findItem(itemId);
        if (!item) return;

        document.querySelector('#reorder-item-id').value = itemId;
        this.updateReorderModalTitle(itemId);
        this.updateReorderButtonStates(itemId);
        this.reorderModal?.show();
    }

    updateReorderModalTitle(itemId) {
        const item = this.findItem(itemId);
        const titleEl = document.querySelector('#reorder-item-title');
        if (item && titleEl) {
            titleEl.textContent = item.title;
        }
    }

    updateReorderButtonStates(itemId) {
        const itemEl = document.querySelector(`.menu-item[data-id="${itemId}"]`);
        if (!itemEl || !this.reorderModalEl) return;

        const parent = itemEl.parentElement;
        const siblings = Array.from(parent.querySelectorAll(':scope > .menu-item'));
        const currentIndex = siblings.indexOf(itemEl);
        const isFirst = currentIndex === 0;
        const isLast = currentIndex === siblings.length - 1;
        const isAtRoot = parent.id === 'menu-items';
        const hasPrevSibling = itemEl.previousElementSibling?.classList.contains('menu-item');
        const currentDepth = getItemDepth(itemEl);
        const canIndent = hasPrevSibling && currentDepth < this.settings.maxDepth && this.settings.supportsHierarchy;

        // Update button states
        const upBtn = this.reorderModalEl.querySelector('[data-direction="up"]');
        const downBtn = this.reorderModalEl.querySelector('[data-direction="down"]');
        const leftBtn = this.reorderModalEl.querySelector('[data-direction="left"]');
        const rightBtn = this.reorderModalEl.querySelector('[data-direction="right"]');

        if (upBtn) upBtn.disabled = isFirst;
        if (downBtn) downBtn.disabled = isLast;
        if (leftBtn) leftBtn.disabled = isAtRoot;
        if (rightBtn) rightBtn.disabled = !canIndent;
    }

    bindSearchFilters() {
        // Bind all search inputs with data-list attribute
        document.querySelectorAll('.search-filter[data-list]').forEach((searchEl) => {
            const listEl = document.querySelector(searchEl.dataset.list);
            if (!listEl) return;

            searchEl.addEventListener('input', (e) => {
                const query = e.target.value.toLowerCase().trim();
                listEl.querySelectorAll('.add-item-btn').forEach((item) => {
                    const title = item.querySelector('.item-title')?.textContent.toLowerCase() || '';
                    item.style.display = title.includes(query) ? '' : 'none';
                });
            });
        });
    }

    bindKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                this.saveMenu();
            }
        });
    }

    // =========================================================================
    // EVENT HANDLERS
    // =========================================================================

    handleMenuBuilderClick(e) {
        const target = e.target.closest('button');
        if (!target) return;

        const itemEl = target.closest('.menu-item');
        if (!itemEl) return;

        const itemId = itemEl.dataset.id;

        if (target.classList.contains('edit-item')) {
            this.openEditModal(itemId);
        } else if (target.classList.contains('delete-item')) {
            this.showDeleteConfirmation(itemId);
        } else if (target.classList.contains('move-up')) {
            this.moveItem(itemId, 'up');
        } else if (target.classList.contains('move-down')) {
            this.moveItem(itemId, 'down');
        }
    }

    /**
     * Move item up or down within its parent container
     */
    moveItem(itemId, direction) {
        const itemEl = document.querySelector(`.menu-item[data-id="${itemId}"]`);
        if (!itemEl) return;

        const parent = itemEl.parentElement;
        const siblings = Array.from(parent.querySelectorAll(':scope > .menu-item'));
        const currentIndex = siblings.indexOf(itemEl);

        if (direction === 'up' && currentIndex > 0) {
            parent.insertBefore(itemEl, siblings[currentIndex - 1]);
            this.markAsChanged();
        } else if (direction === 'down' && currentIndex < siblings.length - 1) {
            parent.insertBefore(siblings[currentIndex + 1], itemEl);
            this.markAsChanged();
        }
    }

    /**
     * Indent item (make it a child of the previous sibling)
     */
    indentItem(itemId) {
        if (!this.settings.supportsHierarchy) {
            this.toast.warning('This menu does not support hierarchy.');
            return;
        }

        const itemEl = document.querySelector(`.menu-item[data-id="${itemId}"]`);
        if (!itemEl) return;

        // Find previous sibling to become parent
        const prevSibling = itemEl.previousElementSibling;
        if (!prevSibling || !prevSibling.classList.contains('menu-item')) {
            this.toast.info('Cannot indent: no item above to nest under.');
            return;
        }

        // Check depth limit
        const currentDepth = getItemDepth(itemEl);
        if (currentDepth >= this.settings.maxDepth) {
            this.toast.warning(`Maximum nesting depth (${this.settings.maxDepth}) reached.`);
            return;
        }

        // Get or create children container in previous sibling
        let childrenContainer = prevSibling.querySelector(':scope > .menu-children');
        if (!childrenContainer) {
            childrenContainer = document.createElement('div');
            childrenContainer.className = 'menu-children';
            childrenContainer.dataset.level = currentDepth + 1;
            prevSibling.appendChild(childrenContainer);
        }

        childrenContainer.appendChild(itemEl);
        this.markAsChanged();
    }

    /**
     * Outdent item (move it to parent's level)
     */
    outdentItem(itemId) {
        const itemEl = document.querySelector(`.menu-item[data-id="${itemId}"]`);
        if (!itemEl) return;

        const parentContainer = itemEl.parentElement;
        if (!parentContainer.classList.contains('menu-children')) {
            this.toast.info('Cannot outdent: item is already at the top level.');
            return;
        }

        const parentItem = parentContainer.closest('.menu-item');
        if (!parentItem) return;

        // Move after parent item
        const grandparent = parentItem.parentElement;
        grandparent.insertBefore(itemEl, parentItem.nextSibling);

        // Clean up empty children container
        if (parentContainer.children.length === 0) {
            parentContainer.remove();
        }

        this.markAsChanged();
    }

    handleAddCustomItem() {
        const titleInput = document.querySelector('#custom-title');
        const urlInput = document.querySelector('#custom-url');

        const title = titleInput?.value.trim();
        const url = urlInput?.value.trim() || '#';

        if (!title) {
            titleInput?.classList.add('is-invalid');
            return;
        }

        titleInput?.classList.remove('is-invalid');

        this.addItem({
            title,
            url,
            type: 'custom',
            target: '_self',
        });

        // Clear form
        if (titleInput) titleInput.value = '';
        if (urlInput) urlInput.value = '';
    }

    handleAddPageItem(e) {
        const btn = e.currentTarget;
        const itemData = {
            title: btn.dataset.title,
            url: btn.dataset.url || '#',
            type: btn.dataset.type,
            object_id: btn.dataset.id ? parseInt(btn.dataset.id) : null,
            target: '_self',
        };

        this.addItem(itemData);

        // Visual feedback - flash "added" state
        btn.classList.add('added');
        setTimeout(() => btn.classList.remove('added'), 600);
    }

    handleAddFromModal() {
        const form = document.querySelector('#add-item-form');
        if (!form) return;

        const title = form.querySelector('#add-title')?.value.trim();
        if (!title) {
            form.querySelector('#add-title')?.classList.add('is-invalid');
            return;
        }

        const itemData = {
            title,
            url: form.querySelector('#add-url')?.value.trim() || '#',
            type: form.querySelector('#add-type')?.value || 'custom',
            target: form.querySelector('#add-target')?.value || '_self',
            css_classes: form.querySelector('#add-css-classes')?.value.trim() || '',
            description: form.querySelector('#add-description')?.value.trim() || '',
            link_title: form.querySelector('#add-link-title')?.value.trim() || '',
            link_rel: form.querySelector('#add-link-rel')?.value.trim() || '',
            icon: form.querySelector('#add-icon')?.value.trim() || '',
            object_id: form.querySelector('#add-object-id')?.value || null,
            is_active: form.querySelector('#add-is-active')?.checked ?? true,
        };

        this.addItem(itemData);
        this.modals.hide('add');
    }

    handleInlineEdit(e) {
        const titleEl = e.target.closest('.menu-item-title');
        if (!titleEl) return;

        const itemEl = titleEl.closest('.menu-item');
        if (!itemEl) return;

        // Prevent if already editing
        if (titleEl.querySelector('input')) return;

        const itemId = itemEl.dataset.id;
        const currentTitle = titleEl.textContent.trim();

        // Create inline input
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control form-control-sm d-inline-block';
        input.style.width = '200px';
        input.value = currentTitle;

        // Replace title with input
        titleEl.textContent = '';
        titleEl.appendChild(input);
        input.focus();
        input.select();

        // Handle save/cancel
        const saveEdit = () => {
            const newTitle = input.value.trim();
            if (newTitle && newTitle !== currentTitle) {
                titleEl.textContent = newTitle;

                // Update state
                const item = this.findItem(itemId);
                if (item) {
                    item.title = newTitle;
                    item.isModified = true;
                    this.markAsChanged();
                }
            } else {
                titleEl.textContent = currentTitle;
            }
        };

        input.addEventListener('blur', saveEdit);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                input.blur();
            } else if (e.key === 'Escape') {
                input.value = currentTitle;
                input.blur();
            }
        });
    }

    // =========================================================================
    // ITEM OPERATIONS
    // =========================================================================

    addItem(itemData) {
        const newItem = {
            id: this.nextTempId--,
            title: itemData.title,
            url: itemData.url || '#',
            type: itemData.type || 'custom',
            target: itemData.target || '_self',
            css_classes: itemData.css_classes || '',
            description: itemData.description || '',
            object_id: itemData.object_id || null,
            link_title: itemData.link_title || '',
            link_rel: itemData.link_rel || '',
            icon: itemData.icon || '',
            is_active: itemData.is_active !== false,
            parent_id: null,
            sort_order: this.getVisibleItems().length,
            isNew: true,
            isDeleted: false,
            isModified: false,
        };

        this.items.push(newItem);
        this.renderNewItem(newItem);
        this.hideEmptyState();
        this.updateItemCount();
        this.markAsChanged();
        this.toast.success('Item added. Click "Save" to save changes.');
    }

    renderNewItem(item) {
        const html = ItemRenderer.render(item);

        // Ensure menu-items container exists
        let container = document.querySelector('#menu-items');
        if (!container) {
            container = document.createElement('div');
            container.id = 'menu-items';
            container.className = 'menu-items-container';
            this.elements.menuBuilder?.appendChild(container);
        }

        container.insertAdjacentHTML('beforeend', html);
    }

    // =========================================================================
    // EDIT MODAL
    // =========================================================================

    openEditModal(itemId) {
        const item = this.findItem(itemId);
        if (!item) {
            this.toast.error('Item not found');
            return;
        }

        const itemEl = document.querySelector(`.menu-item[data-id="${itemId}"]`);
        const form = document.querySelector('#edit-item-form');
        if (!form) return;

        // Populate form
        form.querySelector('#edit-item-id').value = itemId;
        form.querySelector('#edit-title').value =
            item.title || itemEl?.querySelector('.menu-item-title')?.textContent?.trim() || '';
        form.querySelector('#edit-url').value = item.url || itemEl?.dataset.url || '';
        form.querySelector('#edit-target').value = item.target || itemEl?.dataset.target || '_self';
        form.querySelector('#edit-css-classes').value = item.css_classes || itemEl?.dataset.cssClasses || '';
        form.querySelector('#edit-description').value = item.description || itemEl?.dataset.description || '';
        form.querySelector('#edit-link-title').value = item.link_title || itemEl?.dataset.linkTitle || '';
        form.querySelector('#edit-link-rel').value = item.link_rel || itemEl?.dataset.linkRel || '';
        form.querySelector('#edit-icon').value = item.icon || itemEl?.dataset.icon || '';
        form.querySelector('#edit-is-active').checked = item.is_active;

        // Update icon preview
        const iconPreview = form.querySelector('#edit-icon-preview i');
        if (iconPreview) {
            iconPreview.className = item.icon || 'ri-star-line';
        }

        this.modals.show('edit');
    }

    saveMenuItem() {
        const form = document.querySelector('#edit-item-form');
        if (!form) return;

        const itemId = form.querySelector('#edit-item-id').value;
        const title = form.querySelector('#edit-title').value.trim();

        if (!title) {
            form.querySelector('#edit-title').classList.add('is-invalid');
            return;
        }

        const formData = {
            title,
            url: form.querySelector('#edit-url').value.trim() || '#',
            target: form.querySelector('#edit-target').value,
            css_classes: form.querySelector('#edit-css-classes').value.trim(),
            description: form.querySelector('#edit-description').value.trim(),
            link_title: form.querySelector('#edit-link-title')?.value.trim() || '',
            link_rel: form.querySelector('#edit-link-rel')?.value.trim() || '',
            icon: form.querySelector('#edit-icon')?.value.trim() || '',
            is_active: form.querySelector('#edit-is-active').checked,
        };

        // Update state
        const item = this.findItem(itemId);
        if (item) {
            Object.assign(item, formData);
            item.isModified = true;
        }

        // Update DOM
        ItemRenderer.updateDOM(itemId, formData);

        this.modals.hide('edit');
        this.markAsChanged();
        this.toast.success('Item updated. Click "Save" to save changes.');
    }

    // =========================================================================
    // DELETE
    // =========================================================================

    showDeleteConfirmation(itemId) {
        const item = this.findItem(itemId);
        if (!item) return;

        this.itemToDelete = itemId;

        document.querySelector('#delete-item-title').textContent = item.title;
        document.querySelector('#delete-item-url').textContent = item.url || '#';

        this.modals.show('delete');
    }

    executeDelete() {
        if (!this.itemToDelete) return;

        const itemId = this.itemToDelete;

        // Mark item and children as deleted in state
        this.markItemDeleted(itemId);

        // Remove from DOM
        const itemEl = document.querySelector(`.menu-item[data-id="${itemId}"]`);
        if (itemEl) {
            itemEl.remove();
        }

        // Check if menu is now empty
        if (this.getVisibleItems().length === 0) {
            this.showEmptyState();
        }

        this.updateItemCount();
        this.modals.hide('delete');
        this.itemToDelete = null;
        this.markAsChanged();
        this.toast.success('Item removed. Click "Save" to save changes.');
    }

    markItemDeleted(itemId) {
        const itemEl = document.querySelector(`.menu-item[data-id="${itemId}"]`);
        if (itemEl) {
            const children = itemEl.querySelectorAll('.menu-item');
            children.forEach((child) => {
                const childId = child.dataset.id;
                const childItem = this.items.find((i) => i.id == childId);
                if (childItem) {
                    if (childItem.isNew) {
                        const idx = this.items.findIndex((i) => i.id == childId);
                        if (idx !== -1) this.items.splice(idx, 1);
                    } else {
                        childItem.isDeleted = true;
                    }
                }
            });
        }

        const item = this.items.find((i) => i.id == itemId);
        if (item) {
            if (item.isNew) {
                const idx = this.items.findIndex((i) => i.id == itemId);
                if (idx !== -1) this.items.splice(idx, 1);
            } else {
                item.isDeleted = true;
            }
        }
    }

    // =========================================================================
    // SAVE
    // =========================================================================

    async saveMenu() {
        if (this.pendingSave) {
            this.toast.info('Save in progress...');
            return;
        }

        if (!this.hasChanges) {
            this.toast.info('No changes to save');
            return;
        }

        // Validate menu name
        const name = this.elements.nameInput?.value.trim();
        if (!name) {
            this.elements.nameInput?.classList.add('is-invalid');
            this.toast.error('Menu name is required');
            return;
        }

        this.pendingSave = true;
        this.updateMenuOrder();

        const saveButtons = [
            this.elements.saveButton,
            this.elements.saveInlineButton,
            this.elements.saveFloatingButton,
        ].filter(Boolean);
        const originalButtonContent = new Map(saveButtons.map((button) => [button, button.innerHTML]));

        try {
            saveButtons.forEach((button) => {
                button.disabled = true;
                button.innerHTML = '<i class="ri-loader-4-line spin me-1"></i> Saving...';
            });

            const payload = this.buildSavePayload();
            const response = await this.saveToServer(payload);

            if (response.success) {
                this.reconcileSavedState(response.newItemIds || {});
                this.hasChanges = false;
                this.initialSettings = this.getCurrentSettings();
                this.updateChangesIndicator();
                this.toast.success(response.message || 'Menu saved successfully!');
            } else {
                throw new Error(response.message || 'Failed to save menu');
            }
        } catch (error) {
            console.error('Save error:', error);

            if (error.status === 422 && error.errors) {
                this.handleValidationErrors(error.errors);
            } else {
                this.toast.error(error.message || 'Failed to save menu');
            }
        } finally {
            this.pendingSave = false;
            saveButtons.forEach((button) => {
                button.disabled = false;
                button.innerHTML = originalButtonContent.get(button);
            });
        }
    }

    updateMenuOrder() {
        const processLevel = (container, parentId = null) => {
            const items = container.querySelectorAll(':scope > .menu-item');
            items.forEach((itemEl, index) => {
                const itemId = parseInt(itemEl.dataset.id, 10);
                const localItem = this.items.find((i) => i.id === itemId);

                if (localItem) {
                    localItem.sort_order = index;
                    localItem.parent_id = parentId;
                }

                // Process children
                const childrenContainer = itemEl.querySelector(':scope > .menu-children');
                if (childrenContainer) {
                    processLevel(childrenContainer, itemId);
                }
            });
        };

        const topLevel = document.querySelector('#menu-items');
        if (topLevel) {
            processLevel(topLevel);
        }
    }

    buildSavePayload() {
        return {
            settings: this.getCurrentSettings(),
            items: {
                new: this.items.filter((i) => i.isNew && !i.isDeleted),
                updated: this.items.filter((i) => i.isModified && !i.isNew && !i.isDeleted),
                deleted: this.items.filter((i) => i.isDeleted && !i.isNew),
                order: this.items
                    .filter((i) => !i.isDeleted)
                    .map((i) => ({
                        id: i.id,
                        sort_order: i.sort_order,
                        parent_id: i.parent_id || null,
                    })),
            },
        };
    }

    async saveToServer(payload) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        const response = await fetch(this.menuUrl, {
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
            error.errors = data.errors;
            throw error;
        }

        return data;
    }

    reconcileSavedState(newItemIds = {}) {
        const idMap = new Map();

        Object.entries(newItemIds).forEach(([tempId, newId]) => {
            const parsedTempId = parseInt(tempId, 10);
            const parsedNewId = parseInt(newId, 10);

            if (Number.isNaN(parsedTempId) || Number.isNaN(parsedNewId)) {
                return;
            }

            idMap.set(parsedTempId, parsedNewId);
        });

        // Update local item IDs and parent references for newly created items.
        this.items.forEach((item) => {
            if (idMap.has(item.id)) {
                item.id = idMap.get(item.id);
            }

            if (item.parent_id !== null && idMap.has(item.parent_id)) {
                item.parent_id = idMap.get(item.parent_id);
            }
        });

        // Update DOM data-id values used by edit/delete/drag interactions.
        idMap.forEach((newId, tempId) => {
            const tempIdAsString = String(tempId);
            const newIdAsString = String(newId);
            const itemEl = document.querySelector(`.menu-item[data-id="${tempIdAsString}"]`);

            if (!itemEl) {
                return;
            }

            itemEl.dataset.id = newIdAsString;
            itemEl.dataset.parentId =
                itemEl.dataset.parentId && idMap.has(parseInt(itemEl.dataset.parentId, 10))
                    ? String(idMap.get(parseInt(itemEl.dataset.parentId, 10)))
                    : itemEl.dataset.parentId;

            itemEl.querySelectorAll(`[data-id="${tempIdAsString}"]`).forEach((el) => {
                el.dataset.id = newIdAsString;
            });
        });

        // Remove persisted deletions and clear dirty flags after successful save.
        this.items = this.items
            .filter((item) => !item.isDeleted)
            .map((item) => ({
                ...item,
                isNew: false,
                isModified: false,
                isDeleted: false,
            }));

        this.updateItemCount();
    }

    handleValidationErrors(errors) {
        document.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));

        const fieldMap = {
            'settings.name': '#name',
            'settings.location': '#location',
            'settings.is_active': '#is_active',
            'settings.description': '#description',
        };

        Object.entries(errors).forEach(([key, messages]) => {
            const selector = fieldMap[key];
            if (selector) {
                const field = document.querySelector(selector);
                if (field) {
                    field.classList.add('is-invalid');
                    const feedback = field.nextElementSibling;
                    if (feedback?.classList.contains('invalid-feedback')) {
                        feedback.textContent = messages[0];
                    }
                }
            }
        });

        this.toast.error('Please fix the validation errors');
    }

    discardChanges() {
        if (!this.hasChanges) {
            this.toast.info('No changes to discard');
            return;
        }

        if (!confirm('Discard all unsaved menu changes?')) {
            return;
        }

        window.location.reload();
    }

    // =========================================================================
    // UI HELPERS
    // =========================================================================

    hideEmptyState() {
        if (this.elements.emptyState) {
            this.elements.emptyState.style.display = 'none';
        }
    }

    showEmptyState() {
        if (this.elements.emptyState) {
            this.elements.emptyState.style.display = 'block';
        } else if (this.elements.menuBuilder) {
            this.elements.menuBuilder.innerHTML = ItemRenderer.renderEmptyState();
            this.elements.emptyState = document.querySelector('#empty-state');
        }
    }
}
