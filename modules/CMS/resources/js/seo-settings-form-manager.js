/**
 * SEO Settings Form Validation Manager
 *
 * Handles form validation for SEO settings pages with real-time validation
 * and comprehensive error summary display.
 *
 * Usage:
 * 1. Include this script in your SEO settings page
 * 2. Add 'needs-validation' class to your forms
 * 3. Define field labels in a global variable: window.settingsFieldLabels
 *
 * Example:
 * <script>
 *     window.settingsFieldLabels = {
 *         'site_title': 'Site Title',
 *     };
 * </script>
 * <script src="{{ Vite::asset('modules/CMS/resources/js/seo-settings-form-manager.js') }}"></script>
 */

class SettingsFormManager {
    constructor(fieldLabels = {}) {
        this.forms = document.querySelectorAll('.needs-validation');
        this.fieldLabels = fieldLabels;
        this.init();
    }

    init() {
        if (!this.forms || this.forms.length === 0) return;

        this.forms.forEach((form) => {
            this.setupFormValidation(form);
            this.setupRealTimeValidation(form);
            this.setupSubmitButtonState(form);
        });
    }

    /**
     * Setup form validation with error summary.
     */
    setupFormValidation(form) {
        form.addEventListener('submit', (e) => {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();

                // Show validation error alert
                this.showValidationErrorAlert(form);
            }
            form.classList.add('was-validated');
        });
    }

    /**
     * Setup real-time validation for better UX.
     */
    setupRealTimeValidation(form) {
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach((input) => {
            input.addEventListener('blur', () => {
                this.validateField(input);
            });

            // Clear validation on input
            input.addEventListener('input', () => {
                if (input.classList.contains('is-invalid')) {
                    this.validateField(input);
                }
            });
        });
    }

    /**
     * Validate individual field.
     */
    validateField(field) {
        if (!field.checkValidity()) {
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
        } else {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        }
    }

    /**
     * Setup submit button loading state.
     */
    setupSubmitButtonState(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (!submitBtn) return;

        const btnIcon = submitBtn.querySelector('i');
        const btnText = submitBtn.querySelector('.btn-text') || submitBtn.childNodes[1];

        if (!btnIcon || !btnText) return;

        const originalText = btnText.textContent?.trim() || 'Save Changes';
        const originalIcon = btnIcon.className;

        form.addEventListener('submit', function (e) {
            // Check if form is valid
            if (!form.checkValidity()) {
                return;
            }

            // Form is valid, show loading state
            submitBtn.disabled = true;
            if (btnText.textContent) {
                btnText.textContent = ' Processing...';
            }
            btnIcon.className = 'ri-hourglass-2-line-split me-1';
        });

        // Reset button state when validation fails
        form.addEventListener(
            'invalid',
            function () {
                submitBtn.disabled = false;
                if (btnText.textContent) {
                    btnText.textContent = ' ' + originalText;
                }
                btnIcon.className = originalIcon;
            },
            true
        );
    }

    /**
     * Show validation error alert with field summary.
     */
    showValidationErrorAlert(form) {
        // Remove any existing client-side alerts
        const existingClientAlerts = document.querySelectorAll('.client-alert');
        existingClientAlerts.forEach((alert) => alert.remove());

        // Get all invalid fields
        const invalidFields = form.querySelectorAll(':invalid');
        const friendlyFieldNames = Array.from(invalidFields)
            .map(
                (field) =>
                    this.fieldLabels[field.name] ||
                    field.name.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())
            )
            .filter((value, index, self) => self.indexOf(value) === index); // Remove duplicates

        if (friendlyFieldNames.length === 0) return;

        const fieldCount = friendlyFieldNames.length;
        const errorSummary =
            fieldCount === 1
                ? `Please check the ${friendlyFieldNames[0]} field.`
                : `Please check the following fields: ${friendlyFieldNames.join(', ')}.`;

        this.showAlert('error', errorSummary);

        // Focus on first invalid field
        if (invalidFields.length > 0) {
            invalidFields[0].focus();

            // Scroll to the invalid field smoothly
            invalidFields[0].scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
        }
    }

    /**
     * Show alert message.
     */
    showAlert(type, message) {
        // Don't show client-side alerts if server-side alerts are already present
        const existingServerAlerts = document.querySelectorAll('#alert-container .alert:not(.client-alert)');
        if (existingServerAlerts.length > 0) {
            return;
        }

        // Remove any existing client-side alerts
        const existingClientAlerts = document.querySelectorAll('.client-alert');
        existingClientAlerts.forEach((alert) => alert.remove());

        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const iconClass = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
        const alertTitle = type === 'success' ? 'Success!' : 'Validation Error!';

        const alert = document.createElement('div');
        alert.className = `alert ${alertClass} alert-dismissible fade rounded-4 show mb-4 client-alert`;
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

        // Insert in the dedicated alert container
        const alertContainer = document.getElementById('alert-container');
        if (alertContainer) {
            alertContainer.appendChild(alert);

            // Scroll to alert
            alertContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}

/**
 * Setup custom tab navigation for settings
 */
function setupTabNavigation() {
    const tabElements = document.querySelectorAll('[data-bs-toggle="tab"], [data-tab-target]');

    tabElements.forEach((tabElement) => {
        tabElement.addEventListener('click', (event) => {
            event.preventDefault();

            // Get target from either href or data-tab-target
            let targetId = event.currentTarget.getAttribute('href');
            if (!targetId || targetId === '#') {
                const tabTarget = event.currentTarget.getAttribute('data-tab-target');
                if (tabTarget) {
                    targetId = `#${tabTarget}`;
                }
            }

            const targetPane = document.querySelector(targetId);

            if (!targetPane) return;

            const tabContainer = targetPane.closest('.tab-content');
            const currentActiveTab = tabContainer.querySelector('.tab-pane.show.active');

            // Toggle tab visibility
            if (currentActiveTab) {
                currentActiveTab.classList.remove('show', 'active');
            }
            targetPane.classList.add('show', 'active');

            // Update navigation menu active state
            document.querySelectorAll('.nav-item').forEach((navItem) => {
                navItem.classList.remove('active');
            });
            event.currentTarget.closest('.nav-item').classList.add('active');
        });
    });
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Use global field labels if available
    const fieldLabels = window.settingsFieldLabels || {};
    new SettingsFormManager(fieldLabels);

    // Setup tab navigation
    setupTabNavigation();
});
