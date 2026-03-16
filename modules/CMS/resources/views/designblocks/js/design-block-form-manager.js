/**
 * Design Block Form Manager
 *
 * Consolidated JavaScript for managing design block form functionality.
 * Handles validation, submission, and user interactions for create/edit forms.
 */

/**
 * Design Block Form Validation Handler
 */
class DesignBlockFormManager {
    constructor() {
        this.form = document.getElementById('design-block-form');
        this.init();
    }

    init() {
        if (!this.form) return;

        this.setupFormValidation();
        this.setupSubmitButtons();
        this.setupEnterKeyHandling();
        this.setupSlugGeneration();
    }

    setupFormValidation() {
        this.form.addEventListener('submit', (e) => {
            if (!this.form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                this.showValidationErrorAlert();
            }
            this.form.classList.add('was-validated');
        });

        // Real-time validation
        const requiredInputs = this.form.querySelectorAll('[required]');
        requiredInputs.forEach((input) => {
            input.addEventListener('blur', () => this.validateField(input));
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

    showValidationErrorAlert() {
        const invalidFields = this.form.querySelectorAll(':invalid');
        const fieldLabels = {
            title: 'Title',
            slug: 'Slug',
            design_type: 'Design Type',
            block_type: 'Block Type',
            category_id: 'Category',
            design_system: 'Design System',
            status: 'Status',
        };

        const friendlyFieldNames = Array.from(invalidFields)
            .map((field) => fieldLabels[field.name] || field.name.replace(/_/g, ' ').trim())
            .filter((value, index, self) => self.indexOf(value) === index);

        if (friendlyFieldNames.length === 0) return;

        const errorSummary =
            friendlyFieldNames.length === 1
                ? `Please check the ${friendlyFieldNames[0]} field.`
                : `Please check the following fields: ${friendlyFieldNames.join(', ')}.`;

        this.showAlert('error', errorSummary);

        if (invalidFields.length > 0) {
            invalidFields[0].focus();
            invalidFields[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    showAlert(type, message) {
        const existingServerAlerts = document.querySelectorAll('.alert:not(.client-alert)');
        if (existingServerAlerts.length > 0) return;

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

    setupEnterKeyHandling() {
        // Add Enter key handling for better UX
        this.form.addEventListener('keydown', (e) => {
            // Only handle Enter key, not in textareas
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                // Check if we're in a textarea or content editor
                const isInTextarea = e.target.tagName === 'TEXTAREA';
                const isInContentEditor = e.target.closest('.editor-container, .ck-editor, .tinymce');

                if (isInTextarea || isInContentEditor) {
                    // Don't prevent default in textareas - let them handle line breaks
                    return;
                }

                e.preventDefault();

                // Visual feedback for Enter key press
                this.showEnterKeyFeedback();

                // Determine target button based on form type and current status
                let targetButton;
                const isEditForm = this.form.action.includes('/edit/');
                const statusField = this.form.querySelector('#status');
                const currentStatus = statusField ? statusField.value : 'draft';

                if (isEditForm) {
                    // For edit forms, use update button
                    targetButton = this.form.querySelector('#update-btn');
                } else {
                    // For create forms, default to Save as Draft
                    targetButton = this.form.querySelector('#save-draft-btn');
                }

                if (targetButton) {
                    targetButton.click();
                }
            }
        });
    }

    showEnterKeyFeedback() {
        // Create a subtle visual feedback for Enter key press
        const feedback = document.createElement('div');
        feedback.className = 'enter-key-feedback';
        feedback.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 123, 255, 0.9);
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;
        feedback.textContent = 'Submitting form...';

        document.body.appendChild(feedback);

        // Fade in
        setTimeout(() => (feedback.style.opacity = '1'), 10);

        // Fade out and remove
        setTimeout(() => {
            feedback.style.opacity = '0';
            setTimeout(() => feedback.remove(), 300);
        }, 1500);
    }

    setupSubmitButtons() {
        const submitButtons = this.form.querySelectorAll('button[type="submit"]');

        submitButtons.forEach((button) => {
            button.addEventListener('click', (e) => {
                // Always prevent default - we'll submit manually if valid
                e.preventDefault();

                // Prevent double submission
                if (button.disabled) {
                    return false;
                }

                // Validate form before proceeding
                if (!this.form.checkValidity()) {
                    e.stopPropagation();
                    this.form.classList.add('was-validated');
                    this.showValidationErrorAlert();
                    return false;
                }

                // Set the status from button's value
                const statusValue = button.getAttribute('value');
                const statusField = this.form.querySelector('#status');
                if (statusField && statusValue) {
                    statusField.value = statusValue;
                }

                // Show loading state
                const btnText = button.querySelector('.btn-text');
                const btnIcon = button.querySelector('i');
                if (btnText && btnIcon) {
                    button.disabled = true;
                    btnText.textContent = 'Processing...';
                    btnIcon.className = 'ri-hourglass-2-line-split me-2';
                }

                // Disable all submit buttons to prevent double submission
                submitButtons.forEach((btn) => (btn.disabled = true));

                // Submit the form via requestSubmit so Unpoly/validation submit handlers run.
                // NOTE: form.submit() bypasses submit events and triggers full reload,
                // which causes DirtyForms to show an "Unsaved changes" modal.
                if (typeof this.form.requestSubmit === 'function') {
                    this.form.requestSubmit(button);
                } else {
                    // Fallback for very old browsers
                    const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
                    const notPrevented = this.form.dispatchEvent(submitEvent);
                    if (notPrevented) {
                        this.form.submit();
                    }
                }
            });
        });
    }

    showAlert(type, message) {
        const existingServerAlerts = document.querySelectorAll('.alert:not(.client-alert)');
        if (existingServerAlerts.length > 0) return;

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

    setupEnterKeyHandling() {
        // Add Enter key handling for better UX
        this.form.addEventListener('keydown', (e) => {
            // Only handle Enter key, not in textareas
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                // Check if we're in a textarea or content editor
                const isInTextarea = e.target.tagName === 'TEXTAREA';
                const isInContentEditor = e.target.closest('.editor-container, .ck-editor, .tinymce');

                if (isInTextarea || isInContentEditor) {
                    // Don't prevent default in textareas - let them handle line breaks
                    return;
                }

                e.preventDefault();

                // Visual feedback for Enter key press
                this.showEnterKeyFeedback();

                // Determine target button based on form type and current status
                let targetButton;
                const isEditForm = this.form.action.includes('/edit/');
                const statusField = this.form.querySelector('#status');
                const currentStatus = statusField ? statusField.value : 'draft';

                if (isEditForm) {
                    // For edit forms, maintain current status
                    if (currentStatus === 'published') {
                        targetButton = this.form.querySelector('#update-btn');
                    } else {
                        targetButton = this.form.querySelector('#save-draft-btn');
                    }
                } else {
                    // For create forms, default to Save as Draft
                    targetButton = this.form.querySelector('#save-draft-btn');
                }

                if (targetButton) {
                    targetButton.click();
                }
            }
        });
    }

    showEnterKeyFeedback() {
        // Create a subtle visual feedback for Enter key press
        const feedback = document.createElement('div');
        feedback.className = 'enter-key-feedback';
        feedback.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 123, 255, 0.9);
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;
        feedback.textContent = 'Submitting form...';

        document.body.appendChild(feedback);

        // Fade in
        setTimeout(() => (feedback.style.opacity = '1'), 10);

        // Fade out and remove
        setTimeout(() => {
            feedback.style.opacity = '0';
            setTimeout(() => feedback.remove(), 300);
        }, 1500);
    }

    setupSubmitButtons() {
        // Do NOT manually submit the form.
        // Let Unpoly's AJAX form-handler handle submit (it marks the form clean on success).
        // Manual `form.submit()` bypasses submit events and triggers DirtyForms "Unsaved changes" modals.
        const submitButtons = this.form.querySelectorAll('button[type="submit"]');
        const statusField = this.form.querySelector('#status');

        // Ensure our hidden status mirrors whichever submit button was used.
        submitButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const statusValue = button.getAttribute('value');
                if (statusField && statusValue) {
                    statusField.value = statusValue;
                }
            });
        });

        // Also handle Enter-key submits / programmatic submits where possible.
        this.form.addEventListener('submit', (e) => {
            const submitter = e.submitter;
            if (!submitter || typeof submitter.getAttribute !== 'function') return;
            const statusValue = submitter.getAttribute('value');
            if (statusField && statusValue) {
                statusField.value = statusValue;
            }
        });
    }

    setupSlugGeneration() {
        const titleInput = this.form.querySelector('#title');
        const slugInput = this.form.querySelector('#slug');

        if (!titleInput || !slugInput) return;

        // Only auto-generate slug for new blocks (not edit)
        const isEditForm = this.form.action.includes('/edit/');
        if (isEditForm) return;

        titleInput.addEventListener('input', () => {
            // Only generate if slug is empty or matches previous title
            const currentSlug = slugInput.value.trim();
            if (currentSlug === '' || this.isAutoGeneratedSlug(currentSlug)) {
                const generatedSlug = this.generateSlug(titleInput.value);
                slugInput.value = generatedSlug;
            }
        });
    }

    generateSlug(text) {
        return text
            .toString()
            .toLowerCase()
            .trim()
            .replace(/\s+/g, '-') // Replace spaces with -
            .replace(/[^\w\-]+/g, '') // Remove all non-word chars
            .replace(/\-\-+/g, '-') // Replace multiple - with single -
            .replace(/^-+/, '') // Trim - from start of text
            .replace(/-+$/, ''); // Trim - from end of text
    }

    isAutoGeneratedSlug(slug) {
        // Check if slug looks auto-generated (simple heuristic)
        return slug.match(/^[a-z0-9-]+$/) && !slug.includes('--');
    }
}

/**
 * Global initialization functions
 */
window.initializeDesignBlockForm = function () {
    const form = document.getElementById('design-block-form');
    if (form) {
        const manager = new DesignBlockFormManager();
        // Store instance for access from onclick handlers
        form._designBlockFormManager = manager;
        window.DesignBlockFormManager = manager;
    }
};

// Auto-initialize if DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        // Small delay to ensure Vite scripts are loaded
        setTimeout(() => {
            if (typeof window.initializeDesignBlockForm === 'function') {
                const form = document.getElementById('design-block-form');
                if (form) {
                    window.initializeDesignBlockForm();
                }
            }
        }, 100);
    });
} else {
    // DOM already loaded
    setTimeout(() => {
        if (typeof window.initializeDesignBlockForm === 'function') {
            const form = document.getElementById('design-block-form');
            if (form) {
                window.initializeDesignBlockForm();
            }
        }
    }, 100);
}

/**
 * Export for module usage
 */
export { DesignBlockFormManager };
