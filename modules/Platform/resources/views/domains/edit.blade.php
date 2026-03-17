{{-- Edit Domain Form --}}
<x-app-layout title="Edit Domain">

    @php
        $actions = [];

        if (Route::has('platform.domains.show')) {
            $actions[] = [
                'label' => 'Show',
                'href' => route('platform.domains.show', $domain->id),
                'icon' => 'ri-eye-line',
                'variant' => 'btn-outline-primary'
            ];
        }

        $actions[] = [
            'label' => 'Back',
            'href' => route('platform.domains.index'),
            'icon' => 'ri-arrow-left-line',
            'variant' => 'btn-outline-secondary'
        ];
    @endphp

    {{-- Page Header --}}
    <x-page-header title="Edit Domain"
        description="Update domain registration details" layout="form"
        :actions="$actions"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Domains', 'href' => route('platform.domains.index')],
            ['label' => '#' . $domain->id, 'href' => Route::has('platform.domains.show') ? route('platform.domains.show', $domain->id) : null],
            ['label' => 'Edit', 'active' => true],
        ]" />

    @if ($errors->any())
        @php
            $errorFields = array_keys($errors->toArray());
            $fieldLabels = [
                'domain_name' => 'Domain Name',
                'customer_id' => 'Customer',
                'group_id' => 'Group',
                'registrar_id' => 'Registrar',
                'status' => 'Status',
            ];

            $friendlyFieldNames = array_map(function ($field) use ($fieldLabels) {
                return $fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            }, $errorFields);

            $fieldCount = count($friendlyFieldNames);
            $errorSummary = $fieldCount === 1
                ? "Please check the {$friendlyFieldNames[0]} field."
                : 'Please check the following fields: ' . implode(', ', $friendlyFieldNames) . '.';
        @endphp
        <div class="alert alert-danger alert-dismissible fade rounded-4 show" role="alert">
            <div class="d-flex align-items-start">
                <div class="alert-icon me-3 flex-shrink-0">
                    <i class="ri-error-warning-fill" style="font-size: 1.25rem;"></i>
                </div>
                <div class="flex-grow-1">
                    <h5 class="fw-semibold mb-2">Validation Error!</h5>
                    <p class="mb-0">{{ $errorSummary }}</p>
                </div>
            </div>
            <button class="btn-close" data-bs-dismiss="alert" type="button" aria-label="Close"></button>
        </div>
    @endif

    {{-- Domain Form --}}
    <form data-dirty-form class="needs-validation" id="domain-form" method="POST" action="{{ $formConfig['action'] ?? route('platform.domains.update', $domain->id) }}" novalidate>
        @csrf
        @method('PUT')

        @include('platform::domains.form')
    </form>

    {{-- JavaScript Assets --}}
    <script data-up-execute>
    /**
     * Domain Form JavaScript
     *
     * Handles form functionality for creating and editing domains.
     * Includes form validation and WHOIS data fetching.
     */

    class DomainFormManager {
        constructor() {
            this.form = document.getElementById('domain-form');
            this.isEdit = this.form?.querySelector('input[name="_method"]')?.value === 'PUT';

            this.init();
        }

        init() {
            if (!this.form) return;

            this.setupFormValidation();
            this.setupFormSubmission();
        }

        /**
         * Setup form validation.
         */
        setupFormValidation() {
            if (!this.form) return;

            // Bootstrap validation
            this.form.addEventListener('submit', (e) => {
                if (!this.form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.showValidationErrorAlert();
                }
                this.form.classList.add('was-validated');
            });

            // Real-time validation
            this.setupRealTimeValidation();
        }

        /**
         * Setup real-time validation for better UX.
         */
        setupRealTimeValidation() {
            const inputs = this.form?.querySelectorAll('input, select, textarea');
            inputs?.forEach(input => {
                input.addEventListener('blur', () => {
                    this.validateField(input);
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
         * Setup form submission with loading state.
         */
        setupFormSubmission() {
            if (!this.form) return;

            this.form.addEventListener('submit', (e) => {
                if (this.form.checkValidity()) {
                    const submitBtn = document.getElementById('submit-btn');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        const btnText = submitBtn.querySelector('.btn-text');
                        const btnIcon = submitBtn.querySelector('i');
                        if (btnText) btnText.textContent = 'Saving...';
                        if (btnIcon) btnIcon.className = 'ri-hourglass-2-line-split me-2';
                    }
                }
            });
        }

        /**
         * Show validation error alert.
         */
        showValidationErrorAlert() {
            const invalidFields = this.form.querySelectorAll(':invalid');
            const fieldLabels = {
                'domain_name': 'Domain Name',
                'customer_id': 'Customer',
                'group_id': 'Group',
                'registrar_id': 'Registrar',
                'status': 'Status',
            };

            const friendlyFieldNames = Array.from(invalidFields)
                .map(field => fieldLabels[field.name] || field.name.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()))
                .filter((value, index, self) => self.indexOf(value) === index);

            if (friendlyFieldNames.length === 0) return;

            const errorSummary = friendlyFieldNames.length === 1
                ? `Please check the ${friendlyFieldNames[0]} field.`
                : `Please check the following fields: ${friendlyFieldNames.join(', ')}.`;

            this.showAlert('error', errorSummary);

            if (invalidFields.length > 0) {
                invalidFields[0].focus();
            }
        }

        /**
         * Show alert message.
         */
        showAlert(type, message) {
            const existingAlerts = document.querySelectorAll('.client-alert');
            existingAlerts.forEach(alert => alert.remove());

            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const iconClass = type === 'success' ? 'ri-check-line' : 'ri-error-warning-fill';
            const alertTitle = type === 'success' ? 'Success!' : 'Validation Error!';

            const alert = document.createElement('div');
            alert.className = `alert ${alertClass} alert-dismissible fade rounded-4 show client-alert`;
            alert.setAttribute('role', 'alert');
            alert.innerHTML = `
                <div class="d-flex align-items-start">
                    <div class="alert-icon me-3 flex-shrink-0">
                        <i class="${iconClass}" style="font-size: 1.25rem;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="fw-semibold mb-2">${alertTitle}</h5>
                        <p class="mb-0">${message}</p>
                    </div>
                </div>
                <button class="btn-close" data-bs-dismiss="alert" type="button" aria-label="Close"></button>
            `;

            this.form.parentNode.insertBefore(alert, this.form);
        }
    }

    // Initialize (Unpoly-safe)
    (() => {
        const form = document.getElementById('domain-form');
        if (!form || form.dataset.formManagerInit === '1') return;
        form.dataset.formManagerInit = '1';
        new DomainFormManager();
    })();
    </script>

</x-app-layout>
