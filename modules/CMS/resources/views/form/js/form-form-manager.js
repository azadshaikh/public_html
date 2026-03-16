/**
 * Form Form Manager
 *
 * Consolidated JavaScript for managing form form functionality.
 * Handles validation, submission, and user interactions for create/edit forms.
 */

/**
 * Form Form Validation Handler
 */
class FormFormManager {
    constructor(form = null) {
        this.form = form || document.getElementById('form-form');
        this.init();
    }

    init() {
        if (!this.form) return;

        this.setupFormValidation();
        this.setupSubmitButtons();
        this.setupEnterKeyHandling();
        this.setupSlugGeneration();
        this.setupConfirmationTypeToggle();
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
            html: 'Form HTML',
            status: 'Status',
            confirmation_type: 'Confirmation Type',
            confirmation_message: 'Confirmation Message',
            redirect_url: 'Redirect URL',
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
        const existingServerAlerts = Array.from(document.querySelectorAll('.alert:not(.client-alert)')).filter(
            (alert) => alert.id !== 'client-validation-alert'
        );
        if (existingServerAlerts.length > 0) return;

        const clientAlert = document.getElementById('client-validation-alert');
        const clientMessage = document.getElementById('client-validation-message');
        if (clientAlert && clientMessage) {
            clientMessage.textContent = message;
            clientAlert.classList.remove('d-none');
            clientAlert.classList.add('show');
            clientAlert.scrollIntoView({ behavior: 'smooth', block: 'start' });
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

                // Determine target button based on form type
                const submitButton = this.form.querySelector('#submit-btn');
                if (submitButton) {
                    submitButton.click();
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
                // Prevent double submission
                if (button.disabled) {
                    e.preventDefault();
                    return false;
                }

                // Validate form before proceeding
                if (!this.form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.form.classList.add('was-validated');
                    this.showValidationErrorAlert();
                    return false;
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
            });
        });
    }

    setupSlugGeneration() {
        const titleInput = this.form.querySelector('#title');
        const slugInput = this.form.querySelector('#slug');

        if (!titleInput) return;

        // Only auto-generate slug for new forms (not edit)
        const isEditForm = this.form.dataset.isEdit === 'true';
        if (isEditForm) return;

        titleInput.addEventListener('input', () => {
            // Slug generation is optional for forms - only if slug field exists and is empty
            if (slugInput) {
                const currentSlug = slugInput.value.trim();
                if (currentSlug === '' || this.isAutoGeneratedSlug(currentSlug)) {
                    const generatedSlug = this.generateSlug(titleInput.value);
                    slugInput.value = generatedSlug;
                }
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

    setupConfirmationTypeToggle() {
        const confirmationType = this.form.querySelector('select[name="confirmation_type"]');
        const messageField = document.getElementById('confirmation-message-field');
        const redirectField = document.getElementById('confirmation-redirect-field');

        if (!confirmationType || !messageField || !redirectField) return;

        const toggleFields = () => {
            const value = confirmationType.value;
            messageField.style.display = 'none';
            redirectField.style.display = 'none';

            if (value === 'message') {
                messageField.style.display = 'block';
            } else if (value === 'redirect') {
                redirectField.style.display = 'block';
            }
        };

        // Initial state
        toggleFields();

        // Listen for changes
        confirmationType.addEventListener('change', toggleFields);
    }
}

/**
 * Global utility function for copying shortcode
 */
window.copyToClipboard = function (element) {
    element.select();
    element.setSelectionRange(0, 99999);
    document.execCommand('copy');

    const button = element.nextElementSibling;
    const originalHTML = button.innerHTML;

    button.innerHTML = '<i class="ri-check-line"></i> Copied!';
    setTimeout(() => {
        button.innerHTML = originalHTML;
    }, 2000);
};

/**
 * Global initialization functions
 */
window.initializeFormForm = function (root = document) {
    const scope = root instanceof HTMLElement ? root : document;
    const form = scope.querySelector('#form-form') || document.getElementById('form-form');

    if (!form || form.dataset.jsInitialized === 'true') {
        return;
    }

    form.dataset.jsInitialized = 'true';

    const manager = new FormFormManager(form);
    form._formFormManager = manager;
    window.FormFormManager = manager;
};

/**
 * Export for module usage
 */
export { FormFormManager };
