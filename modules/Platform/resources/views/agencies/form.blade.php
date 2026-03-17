{{-- Shared Agency Form --}}
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Agency Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12 mb-3">
                        <label for="name" class="form-label">Agency Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                            value="{{ old('name', $agency->name ?? '') }}" placeholder="Enter agency name" required>
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please provide an agency name.</div>
                        @enderror
                    </div>

                    <div class="col-12 mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                            value="{{ old('email', $agency->email ?? '') }}" placeholder="agency@example.com" required>
                        @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please provide a valid email address.</div>
                        @enderror
                    </div>
                    <div class="col-12 mb-0">
                        <label for="owner_id" class="form-label">Agency Owner <span class="text-danger">*</span></label>
                        <select class="form-select @error('owner_id') is-invalid @enderror" id="owner_id" name="owner_id" required>
                            <option value="">Select Owner</option>
                            @foreach($ownerOptions as $option)
                                <option value="{{ $option['value'] }}" {{ old('owner_id', $agency->owner_id ?? '') == $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('owner_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please select an agency owner.</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Branding (Only for Reseller Plans)</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <x-form-elements.input
                            id="branding_name"
                            name="branding_name"
                            label="Branding Name"
                            :required="false"
                            :value="old('branding_name', $agency->branding_name ?? '')"
                            placeholder="Enter branding name" />
                    </div>
                    <div class="col-12 mb-3">
                        <label for="branding_website" class="form-label">Branding Website</label>
                        <input type="url" class="form-control @error('branding_website') is-invalid @enderror" id="branding_website" name="branding_website"
                            value="{{ old('branding_website', $agency->branding_website ?? '') }}" placeholder="https://example.com">
                        @error('branding_website')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please provide a valid branding website URL.</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <x-form-elements.input
                            id="branding_logo"
                            name="branding_logo"
                            label="Branding Logo"
                            type="url"
                            :value="old('branding_logo', $agency->branding_logo ?? '')"
                            placeholder="https://example.com/logo.png" />
                    </div>
                    <div class="col-md-6 mb-3">
                        <x-form-elements.input
                            id="branding_icon"
                            name="branding_icon"
                            label="Branding Icon"
                            type="url"
                            :value="old('branding_icon', $agency->branding_icon ?? '')"
                            placeholder="https://example.com/icon.png" />
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Contact Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12 mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <div class="input-group">
                            <select class="form-select" id="phone_code" name="phone_code" style="max-width: 120px;">
                                @if (count($country_codes) > 0)
                                    @php
                                        $selected_phone_code = old('phone_code', $primaryAddress->phone_code ?? $default_phone_code ?? '');
                                    @endphp
                                    @foreach ($country_codes as $country)
                                        <option value="{{ $country['value'] }}"
                                            {{ $selected_phone_code == $country['value'] ? 'selected' : '' }}>
                                            {{ $country['label'] }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone"
                                value="{{ old('phone', $primaryAddress->phone ?? '') }}" placeholder="Enter phone number">
                            @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <x-form-elements.country-select
                            class="form-group"
                            id="country_code"
                            name="country_code"
                            label="Country"
                            labelclass="form-label"
                            inputclass="form-select"
                            value="{{ old('country_code', $primaryAddress->country_code ?? $default_country_code ?? '') }}"
                            placeholder="-- Select Country --" />
                    </div>
                    <div class="col-md-6 mb-3">
                        <x-form-elements.state-select
                            class="form-group"
                            id="state_code"
                            name="state_code"
                            label="State"
                            labelclass="form-label"
                            inputclass="form-select"
                            value="{{ old('state_code', $primaryAddress->state_code ?? '') }}"
                            placeholder="Choose country first"
                            :disabled="!old('country_code', $primaryAddress->country_code ?? $default_country_code ?? '')"
                            countryCodeField="country_code"
                            stateNameField="state"
                            :stateName="old('state', $primaryAddress->state ?? '')" />
                    </div>
                    <div class="col-md-6 mb-3">
                        <x-form-elements.city-select
                            class="form-group"
                            id="city"
                            name="city"
                            label="City"
                            labelclass="form-label"
                            inputclass="form-select"
                            value="{{ old('city', $primaryAddress->city ?? '') }}"
                            placeholder="Choose state first"
                            :disabled="!old('state_code', $primaryAddress->state_code ?? '')"
                            stateCodeField="state_code"
                            countryCodeField="country_code"
                            cityCodeField="city_code"
                            :cityCode="old('city_code', $primaryAddress->city_code ?? '')" />
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="zip" class="form-label">ZIP Code</label>
                        <input type="text" class="form-control @error('zip') is-invalid @enderror" id="zip" name="zip"
                            value="{{ old('zip', $primaryAddress->zip ?? '') }}" placeholder="Enter ZIP code">
                        @error('zip')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 mb-0">
                        <label for="address1" class="form-label">Address</label>
                        <textarea class="form-control @error('address1') is-invalid @enderror" id="address1" name="address1"
                            rows="3" placeholder="Enter full address">{{ old('address1', $primaryAddress->address1 ?? '') }}</textarea>
                        @error('address1')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Organization</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="plan" class="form-label">Agency Plan <span class="text-danger">*</span></label>
                    <select class="form-select @error('plan') is-invalid @enderror" id="plan" name="plan" required>
                        <option value="">Select Plan</option>
                        @foreach($planOptions ?? [] as $option)
                            <option value="{{ $option['value'] }}" {{ old('plan', $agency->plan ?? 'starter') == $option['value'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('plan')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @else
                    <div class="invalid-feedback">Please select an agency plan.</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="type" class="form-label">Agency Type <span class="text-danger">*</span></label>
                    <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                        <option value="">Select Type</option>
                        @foreach($typeOptions as $option)
                            <option value="{{ $option['value'] }}" {{ old('type', $agency->type ?? 'default') == $option['value'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('type')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @else
                    <div class="invalid-feedback">Please select an agency type.</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="website_id_prefix" class="form-label">Website ID Prefix <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        class="form-control @error('website_id_prefix') is-invalid @enderror"
                        id="website_id_prefix"
                        name="website_id_prefix"
                        value="{{ old('website_id_prefix', $agency->website_id_prefix ?? \Modules\Platform\Models\Agency::DEFAULT_WEBSITE_ID_PREFIX) }}"
                        placeholder="WS"
                        maxlength="10"
                        pattern="[A-Za-z0-9]+"
                        required
                    >
                    <small class="form-text text-muted">Example: <code>AS</code>, <code>BS</code>, <code>HF</code></small>
                    @error('website_id_prefix')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @else
                    <div class="invalid-feedback">Please provide an alphanumeric prefix.</div>
                    @enderror
                </div>

                <div class="mb-0">
                    <label for="website_id_zero_padding" class="form-label">Website ID Zero Padding <span class="text-danger">*</span></label>
                    <input
                        type="number"
                        class="form-control @error('website_id_zero_padding') is-invalid @enderror"
                        id="website_id_zero_padding"
                        name="website_id_zero_padding"
                        value="{{ old('website_id_zero_padding', $agency->website_id_zero_padding ?? \Modules\Platform\Models\Agency::DEFAULT_WEBSITE_ID_ZERO_PADDING) }}"
                        min="{{ \Modules\Platform\Models\Agency::MIN_WEBSITE_ID_ZERO_PADDING }}"
                        max="{{ \Modules\Platform\Models\Agency::MAX_WEBSITE_ID_ZERO_PADDING }}"
                        required
                    >
                    <small class="form-text text-muted">`5` with prefix <code>AS</code> generates IDs like <code>AS00001</code>.</small>
                    @error('website_id_zero_padding')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @else
                    <div class="invalid-feedback">Please provide a valid zero padding length.</div>
                    @enderror
                </div>
            </div>
        </div>

        @if(isset($agency) && $agency->exists)
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Agency Platform</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="agency_website_id" class="form-label">Linked Website</label>
                    <select class="form-select @error('agency_website_id') is-invalid @enderror" id="agency_website_id" name="agency_website_id">
                        <option value="">No website linked</option>
                        @if(isset($websiteOptions))
                            @foreach($websiteOptions as $option)
                                <option value="{{ $option['value'] }}" {{ old('agency_website_id', $agency->agency_website_id ?? '') == $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                    <small class="form-text text-muted">Link the agency's SaaS platform website (with is_agency flag) for authentication.</small>
                    @error('agency_website_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3" x-data="{ show: false, copied: false }">
                    <label class="form-label">Secret Key</label>
                    @if($agency->secret_key)
                        <div class="input-group">
                            <input
                                type="text"
                                class="form-control font-monospace bg-light"
                                :value="show ? '{{ addslashes($agency->plain_secret_key) }}' : '••••••••••••••••••••••••••••••••'"
                                readonly
                            />
                            <button type="button" class="btn btn-outline-secondary" @click="show = !show" title="Toggle visibility">
                                <i :class="show ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                @click="navigator.clipboard.writeText('{{ addslashes($agency->plain_secret_key) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                :title="copied ? 'Copied!' : 'Copy to clipboard'"
                            >
                                <i :class="copied ? 'ri-check-line text-success' : 'ri-file-copy-line'"></i>
                            </button>
                        </div>
                        <small class="form-text text-muted">This key is used by the agency instance to authenticate API requests to the platform.</small>
                    @else
                        <div class="alert alert-warning mb-0">
                            <i class="ri-lock-unlock-line me-2"></i>
                            <strong>Not Configured</strong><br>
                            <small>Link an agency website or regenerate manually to create a secret key.</small>
                        </div>
                    @endif
                </div>

                <div class="mb-3">
                    <a
                        href="{{ route('platform.agencies.regenerate-secret-key', $agency->id) }}"
                        class="btn btn-sm btn-outline-warning confirmation-btn"
                        data-title="Regenerate Secret Key"
                        data-method="POST"
                        data-message="This will generate a new secret key and invalidate the current one. The agency instance will need the new key to authenticate."
                        data-confirmButtonText="Regenerate"
                        data-loaderButtonText="Regenerating..."
                    >
                        <i class="ri-refresh-line me-1"></i> Regenerate Secret Key
                    </a>
                </div>

                <hr class="my-3">

                <div class="mb-0">
                    <x-form-elements.input
                        name="webhook_url"
                        label="Webhook URL"
                        type="url"
                        placeholder="https://agency-domain.com/api/agency/v1/webhooks/platform"
                        :value="old('webhook_url', $agency->webhook_url ?? '')"
                    />
                    <small class="form-text text-muted">
                        URL where the platform sends provisioning status updates (website.provisioned, website.failed, etc.)
                        <br>Format: <code>https://{agency-website-domain}/api/agency/v1/webhooks/platform</code>
                    </small>
                </div>
            </div>
        </div>
        @endif

        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Status</h6>
            </div>
            <div class="card-body">
                @if(isset($agency) && $agency->exists)
                    <div class="mb-0">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                            @foreach($statusOptions as $option)
                                <option value="{{ $option['value'] }}" {{ old('status', $agency->status ?? 'active') == $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @else
                    <input type="hidden" name="status" value="active">
                    <p class="text-muted mb-0">Status will be set to Active on creation.</p>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg" type="submit" id="submit-btn">
                        <i class="{{ $formConfig['submitIcon'] ?? 'ri-save-line' }} me-2"></i>
                        <span class="btn-text">{{ $formConfig['submitText'] ?? 'Save Agency' }}</span>
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ $formConfig['cancelUrl'] ?? route('platform.agencies.index') }}">
                        <i class="ri-arrow-left-line me-2"></i>
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script data-up-execute>
(() => {
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
})();
</script>
@endpush
