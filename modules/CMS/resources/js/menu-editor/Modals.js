/**
 * Menu Editor - Modal Management
 */

export class Modals {
    constructor() {
        this.instances = {};
        this.elements = {
            edit: document.querySelector('#editItemModal'),
            add: document.querySelector('#addItemModal'),
            delete: document.querySelector('#deleteConfirmModal'),
        };
    }

    init() {
        if (typeof bootstrap === 'undefined') return;

        try {
            if (this.elements.edit) {
                this.instances.edit = new bootstrap.Modal(this.elements.edit);
                this.elements.edit.addEventListener('hidden.bs.modal', () => {
                    this.resetAccordion(this.elements.edit, 'edit');
                });
            }

            if (this.elements.add) {
                this.instances.add = new bootstrap.Modal(this.elements.add);
                this.elements.add.addEventListener('hidden.bs.modal', () => {
                    this.resetAccordion(this.elements.add, 'add');
                    this.clearAddForm();
                });
            }

            if (this.elements.delete) {
                this.instances.delete = new bootstrap.Modal(this.elements.delete);
            }
        } catch (error) {
            console.error('Modal initialization error:', error);
        }
    }

    resetAccordion(modal, prefix) {
        if (!modal) return;

        const accordion = modal.querySelector('.accordion');
        if (!accordion) return;

        const basicCollapseEl = accordion.querySelector(`#${prefix}-collapse-basic`);
        if (basicCollapseEl && typeof bootstrap !== 'undefined') {
            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(basicCollapseEl);
            bsCollapse.show();
        }
    }

    clearAddForm() {
        const form = document.querySelector('#add-item-form');
        if (form) form.reset();
    }

    show(name) {
        this.instances[name]?.show();
    }

    hide(name) {
        this.instances[name]?.hide();
    }

    get(name) {
        return this.instances[name];
    }
}
