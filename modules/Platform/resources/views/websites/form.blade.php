{{-- Shared Website Form --}}
@php
    $isEdit = isset($website) && $website->exists;
@endphp
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Website Information</h6>
            </div>
            <div class="card-body">
                @if (isset($order_id))
                    <input name="order_id" type="hidden" value="{{ $order_id ?? '' }}">
                    <input name="item_id" type="hidden" value="{{ $item_id ?? '' }}">
                @endif

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Website Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                            value="{{ old('name', $website->name ?? '') }}" placeholder="e.g., My Business Website" required>
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please provide a website name.</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="domain" class="form-label">Domain <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('domain') is-invalid @enderror" id="domain" name="domain"
                            value="{{ old('domain', $website->domain ?? '') }}" placeholder="e.g., example.com" {{ $isEdit ? 'readonly' : '' }} required>
                        @error('domain')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please provide a domain.</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="type" class="form-label">Website Type</label>
                        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type">
                            <option value="">Select Type</option>
                            @foreach($typeOptions as $option)
                                <option value="{{ $option['value'] }}" {{ old('type', $website->type ?? 'paid') == $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="plan" class="form-label">Website Plan</label>
                        <select class="form-select @error('plan') is-invalid @enderror" id="plan" name="plan">
                            <option value="">Select Plan</option>
                            @foreach($planOptions ?? [] as $option)
                                <option value="{{ $option['value'] }}" {{ old('plan', $website->plan ?? 'basic') == $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('plan')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="agency_id" class="form-label">Agency</label>
                        <select class="form-select @error('agency_id') is-invalid @enderror" id="agency_id" name="agency_id">
                            <option value="">-- No Agency --</option>
                            @foreach($agencyOptions as $option)
                                <option value="{{ $option['value'] }}" {{ old('agency_id', $website->agency_id ?? '') == $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>

                            @endforeach
                        </select>
                        @error('agency_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="server_id" class="form-label">Server <span class="text-danger">*</span></label>
                        <select class="form-select @error('server_id') is-invalid @enderror" id="server_id" name="server_id" required>
                            <option value="">Select Server</option>
                            @foreach($serverOptions as $option)
                                <option value="{{ $option['value'] }}" {{ old('server_id', $website->server_id ?? '') == $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('server_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please select a server.</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="dns_provider_id" class="form-label">DNS Provider <span class="text-danger">*</span></label>
                        <select class="form-select @error('dns_provider_id') is-invalid @enderror" id="dns_provider_id" name="dns_provider_id" required>
                            <option value="">Select DNS Provider</option>
                            @foreach($dnsProviderOptions ?? [] as $option)
                                <option value="{{ $option['value'] }}" {{ old('dns_provider_id', $website->dns_provider_id ?? '') == $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('dns_provider_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please select a DNS provider.</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="cdn_provider_id" class="form-label">CDN Provider <span class="text-danger">*</span></label>
                        <select class="form-select @error('cdn_provider_id') is-invalid @enderror" id="cdn_provider_id" name="cdn_provider_id" required>
                            <option value="">Select CDN Provider</option>
                            @foreach($cdnProviderOptions ?? [] as $option)
                                <option value="{{ $option['value'] }}" {{ old('cdn_provider_id', $website->cdn_provider_id ?? '') == $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('cdn_provider_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please select a CDN provider.</div>
                        @enderror
                    </div>

                </div>
            </div>
        </div>

        @php
            $nichesConfig = config('platform.website.niches', []);
            $selectedNiches = old('niches', $website->niches ?? []);
            // Ensure it's an array
            if (!is_array($selectedNiches)) {
                $selectedNiches = [];
            }

            $nichesOptions = collect($nichesConfig)
                ->map(fn ($niche, $key) => [
                    'value' => $key,
                    'label' => $niche['label'] ?? (string) $key,
                ])
                ->values()
                ->all();
        @endphp
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Niches & Classification</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12 mb-3">
                        <x-form-elements.select
                            id="niches"
                            name="niches[]"
                            label="Business Niches"
                            :options="$nichesOptions"
                            :value="$selectedNiches"
                            :extra-attributes="['multiple' => true]"
                            :choices-config="[
                                'removeItemButton' => true,
                                'searchEnabled' => true,
                                'placeholder' => true,
                                'placeholderValue' => 'Select one or more niches',
                            ]"
                            infotext="Select one or more niches"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Customer & Server</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    @if($isEdit)
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer</label>
                            @if($website->customer_info)
                                <div class="form-control-plaintext">
                                    <span class="fw-semibold">{{ $website->customer_info['name'] ?? 'N/A' }}</span>
                                    @if(!empty($website->customer_info['email']))
                                        <br><span class="text-muted small">{{ $website->customer_info['email'] }}</span>
                                    @endif
                                    @if(!empty($website->customer_info['ref']))
                                        <br><span class="text-muted small">Ref: {{ $website->customer_info['ref'] }}</span>
                                    @endif
                                </div>
                                <div class="form-text">Customer data is managed by the Agency module via API.</div>
                            @else
                                <div class="form-control-plaintext text-muted">No customer assigned</div>
                            @endif
                        </div>
                    @else
                        <div class="col-md-6 mb-3">
                            <label for="customer_name" class="form-label">Customer Name</label>
                            <input type="text" class="form-control @error('customer_name') is-invalid @enderror" id="customer_name" name="customer_name"
                                value="{{ old('customer_name') }}" placeholder="e.g., John Smith">
                            @error('customer_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Used for the Astero admin account owner name.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="customer_email" class="form-label">Customer Email</label>
                            <input type="email" class="form-control @error('customer_email') is-invalid @enderror" id="customer_email" name="customer_email"
                                value="{{ old('customer_email') }}" placeholder="e.g., customer@example.com">
                            @error('customer_email')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Used as the website admin login email.</div>
                        </div>
                    @endif
                    <div class="col-md-6 mb-0">
                        <label for="website_username" class="form-label website_username_label">Server Username</label>
                        <input type="text" class="form-control @error('website_username') is-invalid @enderror" id="website_username" name="website_username"
                            value="{{ old('website_username', $website->website_username ?? '') }}" placeholder="Enter server username">
                        @error('website_username')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @if (!$isEdit)
                        <div class="col-md-6 mb-0 align-content-center">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" id="skip_email" name="skip_email" type="checkbox" value="1">
                                <label class="form-check-label" for="skip_email">Don't send credentials email to customer</label>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>



        @if (!$isEdit)
        <div class="card">
            <div class="card-header">
                <h6 class="mb-3">Advanced Setup</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" id="only_add_data" name="only_add_data" type="checkbox" value="1">
                            <label class="form-check-label" for="only_add_data">Only Add Data?</label>
                        </div>
                        <small class="text-muted">Enable if the website already exists on the server</small>
                    </div>
                </div>
                <div class="only_add_data_field d-none">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="website_password" class="form-label">Server Password <span class="text-danger">*</span></label>
                            <x-form-elements.password-input
                                id="website_password"
                                name="website_password"
                                class="mb-0"
                                label="Server Password"
                                labelclass="d-none"
                                inputclass="form-control"
                                :value="''"
                                placeholder="Enter server password" />
                        </div>
                        @if (isset($provider) && $provider == 'ploi')
                            <div class="col-md-6 mb-3">
                                <label for="server_site_id" class="form-label">Server Site ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('server_site_id') is-invalid @enderror" id="server_site_id" name="server_site_id"
                                    value="{{ old('server_site_id') }}" placeholder="Enter server site ID">
                                @error('server_site_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif
                        <div class="col-md-6 mb-3">
                            <label for="db_name" class="form-label">Database Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('db_name') is-invalid @enderror" id="db_name" name="db_name"
                                value="{{ old('db_name') }}" placeholder="Enter database name">
                            @error('db_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="db_user_name" class="form-label">Database Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('db_user_name') is-invalid @enderror" id="db_user_name" name="db_user_name"
                                value="{{ old('db_user_name') }}" placeholder="Enter database username">
                            @error('db_user_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="db_password" class="form-label">Database Password <span class="text-danger">*</span></label>
                            <x-form-elements.password-input
                                id="db_password"
                                name="db_password"
                                class="mb-0"
                                label="Database Password"
                                labelclass="d-none"
                                inputclass="form-control"
                                :value="old('db_password')"
                                placeholder="Enter database password" />
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="super_user_email" class="form-label">Super User Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('super_user_email') is-invalid @enderror" id="super_user_email" name="super_user_email"
                                value="{{ old('super_user_email') }}" placeholder="Enter super user email">
                            @error('super_user_email')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="super_user_password" class="form-label">Super User Password <span class="text-danger">*</span></label>
                            <x-form-elements.password-input
                                id="super_user_password"
                                name="super_user_password"
                                class="mb-0"
                                label="Super User Password"
                                labelclass="d-none"
                                inputclass="form-control"
                                :value="old('super_user_password')"
                                placeholder="Enter super user password" />
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="uid" class="form-label">UID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('uid') is-invalid @enderror" id="uid" name="uid"
                                value="{{ old('uid') }}" placeholder="Enter UID">
                            @error('uid')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 mb-0">
                            <label for="secret_key" class="form-label">Secret Key <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('secret_key') is-invalid @enderror" id="secret_key" name="secret_key"
                                rows="2" placeholder="Enter secret key">{{ old('secret_key') }}</textarea>
                            @error('secret_key')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
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
                <h6 class="mb-3">Options</h6>
            </div>
            <div class="card-body">
                @if (!$isEdit)
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input @error('is_agency') is-invalid @enderror" id="is_agency" name="is_agency" type="checkbox" value="1"
                                {{ old('is_agency') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_agency">Is Agency Website?</label>
                        </div>
                        <small class="text-muted">Enable if this is the agency's SaaS platform website</small>
                        @error('is_agency')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                @endif
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="is_www" name="is_www" type="checkbox" value="1"
                            {{ old('is_www', $website->is_www ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_www">Use WWW?</label>
                    </div>
                    <small class="text-muted">Enable to use www subdomain as primary</small>
                </div>
                @if (!$isEdit)
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="skip_cdn" name="skip_cdn" type="checkbox" value="1"
                            {{ old('skip_cdn', $website->skip_cdn ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="skip_cdn">Skip CDN Setup?</label>
                    </div>
                    <small class="text-muted">Enable if CDN has been configured manually</small>
                </div>
                <div class="mb-0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="skip_dns" name="skip_dns" type="checkbox" value="1"
                            {{ old('skip_dns', $website->skip_dns ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="skip_dns">Skip DNS Setup?</label>
                    </div>
                    <small class="text-muted">Enable if DNS has been configured manually</small>
                </div>
                <div class="mt-3 mb-0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="skip_ssl_issue" name="skip_ssl_issue" type="checkbox" value="1"
                            {{ old('skip_ssl_issue', $website->skip_ssl_issue ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="skip_ssl_issue">Skip ACME SSL Issuance?</label>
                    </div>
                    <small class="text-muted">Enable for local/LAN sites. Origin SSL install will reuse a domain certificate when available, otherwise generate a self-signed certificate.</small>
                </div>
                @endif
            </div>
        </div>

        @if ($isEdit)
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Status</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="status" class="form-label">Website Status</label>
                    <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                        @foreach($statusOptions as $option)
                            <option value="{{ $option['value'] }}" {{ old('status', $website->status?->value ?? 'active') == $option['value'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-0">
                    <x-form-elements.datepicker
                        class="form-group"
                        id="expired_on"
                        name="expired_on"
                        label="Expiry Date"
                        labelclass="form-label"
                        inputclass="form-control"
                        mode="date"
                        value="{{ old('expired_on', $website->expired_on?->format('Y-m-d') ?? '') }}"
                        infotext="When set, website will expire on this date." />
                </div>
            </div>
        </div>
        @else
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Status</h6>
            </div>
            <div class="card-body">
                <p class="text-muted mb-0">Status will be set to Provisioning on creation.</p>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg" type="submit" id="submit-btn">
                        <i class="{{ $formConfig['submitIcon'] ?? 'ri-save-line' }} me-2"></i>
                        <span class="btn-text">{{ $formConfig['submitText'] ?? 'Save Website' }}</span>
                    </button>
                    @if ($isEdit)
                        <a class="btn btn-outline-primary" href="{{ route('platform.websites.show', $website->id) }}">
                            <i class="ri-eye-line me-2"></i>
                            Show Website
                        </a>
                    @endif
                    <a class="btn btn-outline-secondary" href="{{ $formConfig['cancelUrl'] ?? route('platform.websites.index', 'all') }}">
                        <i class="ri-arrow-left-line me-2"></i>
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script data-up-execute>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('ri-eye-line');
        icon.classList.add('ri-eye-off-line');
    } else {
        input.type = 'password';
        icon.classList.remove('ri-eye-off-line');
        icon.classList.add('ri-eye-line');
    }
}

(() => {
    // Form submission loading state
    const form = document.querySelector('form.needs-validation');
    const submitBtn = document.getElementById('submit-btn');

    if (form && submitBtn) {
        if (form.dataset.submitInit === '1') return;
        form.dataset.submitInit = '1';

        const originalBtnText = submitBtn.querySelector('.btn-text')?.textContent || 'Save';
        const originalIconClass = submitBtn.querySelector('i')?.className || 'ri-save-line me-2';

        form.addEventListener('submit', function(e) {
            // Always show loading state first
            submitBtn.disabled = true;
            const btnText = submitBtn.querySelector('.btn-text');
            const btnIcon = submitBtn.querySelector('i');
            if (btnText) btnText.textContent = 'Processing...';
            if (btnIcon) btnIcon.className = 'ri-hourglass-2-line me-2';

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

    // Domain lowercase transformer
    const domainInput = document.querySelector('#domain');
    if (domainInput) {
        domainInput.addEventListener("input", (event) => {
            event.target.value = event.target.value.toLowerCase().replace(/\s/g, '');
        });
    }



    // Only add data toggle
    const onlyAddDataCheckbox = document.getElementById('only_add_data');
    const onlyAddDataField = document.querySelector('.only_add_data_field');
    const usernameLabel = document.querySelector('.website_username_label');
    if (onlyAddDataCheckbox && onlyAddDataField) {
        onlyAddDataCheckbox.addEventListener('change', function() {
            onlyAddDataField.classList.toggle('d-none', !this.checked);
            if (usernameLabel) {
                usernameLabel.classList.toggle('required', this.checked);
            }
        });
    }
})();
</script>
