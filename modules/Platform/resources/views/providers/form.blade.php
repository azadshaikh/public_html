{{-- Shared Provider Form --}}
@php
    $isEdit = isset($provider) && $provider->exists;
    $currentType = old('type', $provider->type ?? $selectedType ?? '');
    $currentVendor = old('vendor', $provider->vendor ?? 'manual');
    $currentStatus = old('status', $provider->status ?? 'active');
@endphp
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Provider Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Provider Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                            value="{{ old('name', $provider->name ?? '') }}" placeholder="e.g., Bunny CDN Production" required>
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please provide a name.</div>
                        @enderror
                        <small class="text-muted">A descriptive name to identify this provider</small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Account Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                            value="{{ old('email', $provider->email ?? '') }}" placeholder="e.g., account@example.com">
                        @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Email associated with the provider account</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="type" class="form-label">Provider Type <span class="text-danger">*</span></label>
                        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                            <option value="">Select Type</option>
                            @foreach($typeOptions ?? [] as $option)
                                <option value="{{ $option['value'] }}" {{ $currentType == $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please select a type.</div>
                        @enderror
                        <small class="text-muted">The category of this provider</small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="vendor" class="form-label">Vendor <span class="text-danger">*</span></label>
                        <select class="form-select @error('vendor') is-invalid @enderror" id="vendor" name="vendor" required>
                            <option value="">Select Vendor</option>
                            @foreach($vendorOptions ?? [] as $option)
                                <option value="{{ $option['value'] }}" {{ $currentVendor == $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('vendor')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please select a vendor.</div>
                        @enderror
                        <small class="text-muted">The service provider/integration</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">API Credentials</h6>
            </div>
            <div class="card-body">
                {{-- Bunny Credentials --}}
                <div id="bunny-credentials" class="credentials-section" style="{{ $currentVendor === 'bunny' ? '' : 'display: none;' }}">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <x-form-elements.password-input
                                id="credentials_bunny_api_key"
                                name="credentials[api_key]"
                                label="API Key"
                                labelclass="form-label"
                                inputclass="form-control"
                                :value="old('credentials.api_key')"
                                placeholder="{{ isset($provider->credentials['api_key']) && $provider->credentials['api_key'] ? '••••••••••••••••' : 'Enter Bunny API Key' }}"
                                infotext="Found in your Bunny.net account settings → API Keys{{ isset($provider->credentials['api_key']) && $provider->credentials['api_key'] ? ' (Leave empty to keep current)' : '' }}" />
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="credentials_bunny_account_id" class="form-label">Account ID</label>
                            <input type="text" class="form-control @error('credentials.account_id') is-invalid @enderror"
                                id="credentials_bunny_account_id" name="credentials[account_id]"
                                value="{{ old('credentials.account_id', $provider->credentials['account_id'] ?? '') }}"
                                placeholder="Optional: Bunny Account ID">
                            @error('credentials.account_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Cloudflare Credentials --}}
                <div id="cloudflare-credentials" class="credentials-section" style="{{ $currentVendor === 'cloudflare' ? '' : 'display: none;' }}">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <x-form-elements.password-input
                                id="credentials_cf_api_token"
                                name="credentials[api_token]"
                                label="API Token"
                                labelclass="form-label"
                                inputclass="form-control"
                                :value="old('credentials.api_token')"
                                placeholder="{{ isset($provider->credentials['api_token']) && $provider->credentials['api_token'] ? '••••••••••••••••' : 'Enter Cloudflare API Token' }}"
                                infotext="Create an API Token in Cloudflare Dashboard → My Profile → API Tokens{{ isset($provider->credentials['api_token']) && $provider->credentials['api_token'] ? ' (Leave empty to keep current)' : '' }}" />
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="credentials_cf_zone_id" class="form-label">Zone ID</label>
                            <input type="text" class="form-control @error('credentials.zone_id') is-invalid @enderror"
                                id="credentials_cf_zone_id" name="credentials[zone_id]"
                                value="{{ old('credentials.zone_id', $provider->credentials['zone_id'] ?? '') }}"
                                placeholder="Optional: Cloudflare Zone ID">
                            @error('credentials.zone_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Found in your domain's Overview page</small>
                        </div>
                    </div>
                </div>

                {{-- Hetzner/DigitalOcean Credentials --}}
                <div id="hetzner-credentials" class="credentials-section" style="{{ in_array($currentVendor, ['hetzner', 'digitalocean', 'linode', 'vultr']) ? '' : 'display: none;' }}">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <x-form-elements.password-input
                                id="credentials_server_api_token"
                                name="credentials[api_token]"
                                label="API Token"
                                labelclass="form-label"
                                inputclass="form-control"
                                :value="old('credentials.api_token')"
                                placeholder="{{ isset($provider->credentials['api_token']) && $provider->credentials['api_token'] ? '••••••••••••••••' : 'Enter API Token' }}"
                                infotext="API token from your provider's control panel{{ isset($provider->credentials['api_token']) && $provider->credentials['api_token'] ? ' (Leave empty to keep current)' : '' }}" />
                        </div>
                    </div>
                </div>

                {{-- Namecheap Credentials --}}
                <div id="namecheap-credentials" class="credentials-section" style="{{ $currentVendor === 'namecheap' ? '' : 'display: none;' }}">
                    <div class="alert alert-warning mb-0">
                        <i class="ri-time-line me-2"></i>
                        <strong>Pending Implementation</strong> - Namecheap API integration is not yet available. Please use manual provider for now.
                    </div>
                </div>

                {{-- GoDaddy Credentials --}}
                <div id="godaddy-credentials" class="credentials-section" style="{{ $currentVendor === 'godaddy' ? '' : 'display: none;' }}">
                    <div class="alert alert-warning mb-0">
                        <i class="ri-time-line me-2"></i>
                        <strong>Pending Implementation</strong> - GoDaddy API integration is not yet available. Please use manual provider for now.
                    </div>
                </div>

                {{-- Manual/Other Provider Notice --}}
                <div id="manual-credentials" class="credentials-section" style="{{ in_array($currentVendor, ['manual', 'other']) ? '' : 'display: none;' }}">
                    <div class="alert alert-info mb-0">
                        <i class="ri-information-line me-2"></i>
                        Manual providers don't require API credentials. Management must be done manually through the provider's interface.
                    </div>
                </div>
            </div>
        </div>

        @if($isEdit)
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Provider Usage</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar-sm rounded bg-primary-subtle">
                                    <span class="avatar-title text-primary fs-4">
                                        <i class="ri-global-line"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h4 class="mb-0">{{ $provider->websites()->count() }}</h4>
                                <p class="text-muted mb-0">Linked Websites</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3 mb-md-0">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar-sm rounded bg-success-subtle">
                                    <span class="avatar-title text-success fs-4">
                                        <i class="ri-earth-line"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h4 class="mb-0">{{ $provider->domains()->count() }}</h4>
                                <p class="text-muted mb-0">Linked Domains</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar-sm rounded bg-info-subtle">
                                    <span class="avatar-title text-info fs-4">
                                        <i class="ri-server-line"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h4 class="mb-0">{{ $provider->servers()->count() }}</h4>
                                <p class="text-muted mb-0">Linked Servers</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Status</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="status" class="form-label">Provider Status</label>
                    <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                        @foreach($statusOptions ?? [] as $option)
                            <option value="{{ $option['value'] }}" {{ $currentStatus == $option['value'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg" id="submit-btn">
                        <i class="{{ $formConfig['submitIcon'] ?? 'ri-check-line' }} me-1"></i>
                        <span class="btn-text">{{ $formConfig['submitText'] ?? 'Save' }}</span>
                    </button>
                    <a href="{{ $formConfig['cancelUrl'] ?? route('platform.providers.index') }}" class="btn btn-outline-secondary">
                        <i class="ri-close-line me-1"></i> Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script data-up-execute>
(() => {
    const typeSelect = document.getElementById('type');
    const vendorSelect = document.getElementById('vendor');
    const credentialSections = document.querySelectorAll('.credentials-section');
    if (!typeSelect || !vendorSelect) return;
    if (typeSelect.dataset.formInit === '1') return;
    typeSelect.dataset.formInit = '1';

    // Form submission loading state
    const form = document.querySelector('form.needs-validation');
    const submitBtn = document.getElementById('submit-btn');

    if (form && submitBtn) {
        if (form.dataset.submitInit !== '1') {
            form.dataset.submitInit = '1';

        const originalBtnText = submitBtn.querySelector('.btn-text')?.textContent || 'Save';
        const originalIconClass = submitBtn.querySelector('i')?.className || 'ri-check-line me-1';

        form.addEventListener('submit', function(e) {
            // Always show loading state first
            submitBtn.disabled = true;
            const btnText = submitBtn.querySelector('.btn-text');
            const btnIcon = submitBtn.querySelector('i');
            if (btnText) btnText.textContent = 'Processing...';
            if (btnIcon) btnIcon.className = 'ri-hourglass-2-line me-1';

            // If validation fails, reset button after a short delay
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                form.classList.add('was-validated');

                setTimeout(function() {
                    submitBtn.disabled = false;
                    if (btnText) btnText.textContent = originalBtnText;
                    if (btnIcon) btnIcon.className = originalIconClass;
                }, 100);
            }
        });
        }
    }

    // Vendor options by type from config
    @php
        $vendorsByType = collect(config('platform.provider.vendors', []))
            ->map(fn($v, $k) => ['key' => $k, 'label' => $v['label'], 'types' => $v['types']])
            ->values()
            ->toArray();
    @endphp
    const vendorsByType = @json($vendorsByType);

    // Function to show/hide and enable/disable credential sections
    function updateCredentialSections(selectedVendor) {
        // Hide all credential sections and disable their inputs
        credentialSections.forEach(section => {
            section.style.display = 'none';
            section.querySelectorAll('input, select, textarea').forEach(input => {
                input.disabled = true;
            });
        });

        // Show relevant credential section and enable its inputs
        let activeSection = null;
        switch(selectedVendor) {
            case 'bunny':
                activeSection = document.getElementById('bunny-credentials');
                break;
            case 'cloudflare':
                activeSection = document.getElementById('cloudflare-credentials');
                break;
            case 'hetzner':
            case 'digitalocean':
            case 'linode':
            case 'vultr':
                activeSection = document.getElementById('hetzner-credentials');
                break;
            case 'namecheap':
            case 'godaddy':
                activeSection = document.getElementById(selectedVendor + '-credentials');
                break;
            case 'manual':
            case 'other':
            default:
                activeSection = document.getElementById('manual-credentials');
                break;
        }

        if (activeSection) {
            activeSection.style.display = '';
            activeSection.querySelectorAll('input, select, textarea').forEach(input => {
                input.disabled = false;
            });
        }
    }

    // Initialize on page load
    updateCredentialSections(vendorSelect.value);

    typeSelect.addEventListener('change', function() {
        const selectedType = this.value;

        // Filter vendor options based on type
        vendorSelect.innerHTML = '<option value="">Select Vendor</option>';

        vendorsByType.forEach(vendor => {
            if (!selectedType || vendor.types.includes(selectedType)) {
                const option = document.createElement('option');
                option.value = vendor.key;
                option.textContent = vendor.label;
                vendorSelect.appendChild(option);
            }
        });

        // Reset credentials display
        credentialSections.forEach(section => section.style.display = 'none');
        updateCredentialSections('manual');
    });

    vendorSelect.addEventListener('change', function() {
        updateCredentialSections(this.value);
    });
})();
</script>
@endpush
