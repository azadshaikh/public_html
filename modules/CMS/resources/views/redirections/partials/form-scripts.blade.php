@pushOnce('scripts')
    <script>
        (() => {
            'use strict';

            const initializeRedirectionForm = () => {
                const form = document.getElementById('seo-redirection-form');
                if (!form) {
                    return;
                }

                const urlTypeSelect = document.getElementById('url_type');
                const targetUrlInput = document.getElementById('target_url');
                const internalHelp = document.querySelector('.internal-help');
                const externalHelp = document.querySelector('.external-help');
                const sourceUrlInput = document.getElementById('source_url');
                const hasErrors = form.dataset.hasErrors === 'true';

                if (!urlTypeSelect || !targetUrlInput || !sourceUrlInput) {
                    return;
                }

                // Show validation errors if they exist on page load
                if (hasErrors) {
                    form.classList.add('was-validated');
                }

                // Function to show validation error alert
                const showValidationErrorAlert = (customErrors = []) => {
                    // Remove existing client-side alerts
                    const existingClientAlerts = document.querySelectorAll('.client-alert');
                    existingClientAlerts.forEach(alert => alert.remove());

                    // Get all invalid fields
                    const invalidFields = form.querySelectorAll(':invalid, .is-invalid');
                    const fieldLabels = {
                        'source_url': 'Source URL',
                        'target_url': 'Target URL',
                        'url_type': 'URL Type',
                        'redirect_type': 'Redirect Type',
                        'status': 'Status',
                    };

                    let friendlyFieldNames = Array.from(invalidFields)
                        .map(field => fieldLabels[field.name] || field.name.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()))
                        .filter((value, index, self) => self.indexOf(value) === index); // Remove duplicates

                    // Add custom errors
                    friendlyFieldNames = friendlyFieldNames.concat(customErrors);

                    if (friendlyFieldNames.length === 0) return;

                    const fieldCount = friendlyFieldNames.length;
                    const errorSummary = fieldCount === 1
                        ? `Please check the ${friendlyFieldNames[0]} field.`
                        : `Please check the following fields: ${friendlyFieldNames.join(', ')}.`;

                    const alert = document.createElement('div');
                    alert.className = 'alert alert-danger alert-dismissible fade rounded-4 show client-alert';
                    alert.setAttribute('role', 'alert');
                    alert.innerHTML = `
                        <div class="d-flex align-items-start">
                            <div class="alert-icon me-3 flex-shrink-0">
                                <i class="ri-error-warning-fill" style="font-size: 1.25rem;"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="fw-semibold mb-2">Validation Error!</h5>
                                <p class="mb-0">${errorSummary}</p>
                            </div>
                        </div>
                        <button class="btn-close" data-bs-dismiss="alert" type="button" aria-label="Close"></button>
                    `;

                    // Insert at the top of the page, before the form
                    form.parentNode.insertBefore(alert, form);

                    // Focus on first invalid field
                    if (invalidFields.length > 0) {
                        invalidFields[0].focus();
                    }
                };

                // Function to update URL type display
                const updateUrlTypeDisplay = (urlType) => {
                    const isInternal = urlType === 'internal';

                    // Update placeholder
                    targetUrlInput.placeholder = isInternal
                        ? '/new-path'
                        : 'https://example.com/page';

                    // Toggle help text
                    if (internalHelp) {
                        internalHelp.style.display = isInternal ? 'inline' : 'none';
                    }
                    if (externalHelp) {
                        externalHelp.style.display = isInternal ? 'none' : 'inline';
                    }

                    // Clear any previous validation errors
                    targetUrlInput.classList.remove('is-invalid');
                    const feedback = targetUrlInput.parentElement.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.style.display = 'none';
                    }
                };

                // Initialize display state based on current selection
                updateUrlTypeDisplay(urlTypeSelect.value);

                // Auto-add leading slash to source URL if missing
                sourceUrlInput.addEventListener('blur', () => {
                    let value = sourceUrlInput.value.trim();
                    if (value && !value.startsWith('/')) {
                        sourceUrlInput.value = '/' + value;
                    }
                });

                // Handle URL type change
                urlTypeSelect.addEventListener('change', (e) => {
                    updateUrlTypeDisplay(e.target.value);
                });

                // Form validation before submit
                form.addEventListener('submit', (e) => {
                    let isValid = true;
                    let customErrors = [];

                    // Check if form passes HTML5 validation
                    if (!form.checkValidity()) {
                        e.preventDefault();
                        e.stopPropagation();
                        isValid = false;
                    }

                    // Additional custom validation for URL formats
                    const urlType = urlTypeSelect.value;
                    const targetUrl = targetUrlInput.value.trim();
                    let sourceUrl = sourceUrlInput.value.trim();

                    // Validate source URL starts with /
                    if (sourceUrl && !sourceUrl.startsWith('/')) {
                        e.preventDefault();
                        e.stopPropagation();
                        sourceUrlInput.classList.add('is-invalid');
                        let sourceFeedback = sourceUrlInput.parentElement.querySelector('.invalid-feedback');
                        if (sourceFeedback) {
                            sourceFeedback.textContent = 'Source URL must start with /';
                            sourceFeedback.style.display = 'block';
                        }
                        isValid = false;
                    } else if (!sourceUrl) {
                        e.preventDefault();
                        e.stopPropagation();
                        sourceUrlInput.classList.add('is-invalid');
                        isValid = false;
                    }

                    // Validate target URL format based on type
                    if (targetUrl) {
                        let errorMessage = '';

                        if (urlType === 'internal') {
                            if (!targetUrl.startsWith('/')) {
                                errorMessage = 'Internal URLs must start with /';
                                customErrors.push('Target URL (must start with /)');
                            }
                        } else if (urlType === 'external') {
                            if (!targetUrl.match(/^https?:\/\//i)) {
                                errorMessage = 'External URLs must start with http:// or https://';
                                customErrors.push('Target URL (must start with http:// or https://)');
                            }
                        }

                        if (errorMessage) {
                            e.preventDefault();
                            e.stopPropagation();
                            targetUrlInput.classList.add('is-invalid');
                            let feedback = targetUrlInput.parentElement.querySelector('.invalid-feedback');
                            if (!feedback) {
                                feedback = document.createElement('div');
                                feedback.className = 'invalid-feedback';
                                targetUrlInput.parentElement.appendChild(feedback);
                            }
                            feedback.textContent = errorMessage;
                            feedback.style.display = 'block';
                            isValid = false;
                        }
                    }

                    form.classList.add('was-validated');

                    // Show validation error alert if there are errors
                    if (!isValid) {
                        showValidationErrorAlert(customErrors);
                    }
                });

                // Clear validation errors on input
                [sourceUrlInput, targetUrlInput].forEach(input => {
                    input.addEventListener('input', () => {
                        input.classList.remove('is-invalid');
                        const feedback = input.parentElement.querySelector('.invalid-feedback');
                        if (feedback) {
                            feedback.style.display = 'none';
                        }
                    });
                });

                // Add live validation on blur for better UX
                form.querySelectorAll('input[required], select[required], textarea[required]').forEach((field) => {
                    field.addEventListener('blur', () => {
                        if (form.classList.contains('was-validated')) {
                            if (field.checkValidity()) {
                                field.classList.remove('is-invalid');
                                field.classList.add('is-valid');
                            } else {
                                field.classList.remove('is-valid');
                                field.classList.add('is-invalid');
                            }
                        }
                    });
                });
            };

            // Initialize on DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeRedirectionForm);
            } else {
                initializeRedirectionForm();
            }
        })();
    </script>
@endPushOnce
