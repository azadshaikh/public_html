{{-- Shared Secret Form --}}
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Secret Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="key" class="form-label">Key <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('key') is-invalid @enderror"
                            id="key" name="key"
                            value="{{ old('key', $secret->key ?? '') }}"
                            placeholder="e.g., api_token, db_password" required>
                        @error('key')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="invalid-feedback">Please provide a key name.</div>
                        @enderror
                        <small class="text-muted">Unique identifier for this secret</small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label">Username/Email</label>
                        <input type="text" class="form-control @error('username') is-invalid @enderror"
                            id="username" name="username"
                            value="{{ old('username', $secret->username ?? '') }}"
                            placeholder="e.g., admin@example.com">
                        @error('username')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Optional username or email for this credential</small>
                    </div>

                                        <div class="col-md-6 mb-3">
                        <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                            <option value="">Select Type</option>
                            @foreach ($typeOptions as $option)
                                <option value="{{ $option['value'] }}"
                                    {{ old('type', $secret->type ?? '') == $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="invalid-feedback">Please select a secret type.</div>
                        @enderror
                    </div>

                    <div class="col-12 mb-3">
                        <label for="value" class="form-label">Value @if(!isset($secret) || !$secret->exists)<span class="text-danger">*</span>@endif</label>
                        <div class="input-group">
                            <textarea class="form-control @error('value') is-invalid @enderror"
                                id="value" name="value" rows="4"
                                placeholder="Enter secret value">{{ old('value') }}</textarea>
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleValueVisibility()">
                                <i class="ri-eye-line" id="value-toggle-icon"></i>
                            </button>
                        </div>
                        @error('value')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        @if(isset($secret) && $secret->exists)
                            <small class="text-muted">Leave blank to keep existing value</small>
                        @endif
                    </div>

                    @if(isset($secret) && $secret->exists && $secret->metadata)
                    <div class="col-12 mb-3">
                        <label for="metadata_display" class="form-label">Metadata</label>
                        <textarea class="form-control font-monospace" id="metadata_display" rows="6" readonly>{{ json_encode($secret->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea>
                        <small class="text-muted">Read-only view of associated metadata</small>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h6 class="mb-3">Related Entity</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="secretable_type" class="form-label">Model Type <span class="text-danger">*</span></label>
                        <select class="form-select @error('secretable_type') is-invalid @enderror" id="secretable_type" name="secretable_type" required>
                            <option value="">Select Model Type</option>
                            @foreach (($secretableTypeOptions ?? []) as $option)
                                <option value="{{ $option['value'] }}"
                                    {{ old('secretable_type', $secret->secretable_type ?? '') == $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('secretable_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="invalid-feedback">Please select a model type.</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="secretable_id" class="form-label">Model ID <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('secretable_id') is-invalid @enderror"
                            id="secretable_id" name="secretable_id"
                            value="{{ old('secretable_id', $secret->secretable_id ?? '') }}"
                            placeholder="e.g., 1" required>
                        @error('secretable_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="invalid-feedback">Please provide the model ID.</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Status & Expiry</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="is_active" name="is_active" type="checkbox"
                            value="1" {{ old('is_active', $secret->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">
                            Active
                        </label>
                    </div>
                    <small class="text-muted">Inactive secrets cannot be retrieved via API</small>
                </div>

                <div class="mb-0">
                    <x-form-elements.datepicker
                        name="expires_at"
                        label="Expiration Date"
                        mode="datetime"
                        :value="old('expires_at', isset($secret) && $secret->expires_at ? $secret->expires_at->format('Y-m-d H:i') : '')"
                        placeholder="dd/mm/yyyy, --:--"
                        infotext="Optional. Leave blank for no expiration." />
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg" type="submit" id="submit-btn">
                        <i class="{{ $formConfig['submitIcon'] ?? 'ri-save-line' }} me-2"></i>
                        <span class="btn-text">{{ $formConfig['submitText'] ?? 'Save Secret' }}</span>
                    </button>
                    <a class="btn btn-outline-secondary"
                        href="{{ $formConfig['cancelUrl'] ?? route('platform.secrets.index') }}">
                        <i class="ri-arrow-left-line me-2"></i>
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script data-up-execute>
    function toggleValueVisibility() {
        const textarea = document.getElementById('value');
        const icon = document.getElementById('value-toggle-icon');

        if (textarea.style.webkitTextSecurity === 'disc') {
            textarea.style.webkitTextSecurity = 'none';
            icon.classList.remove('ri-eye-line');
            icon.classList.add('ri-eye-off-line');
        } else {
            textarea.style.webkitTextSecurity = 'disc';
            icon.classList.remove('ri-eye-off-line');
            icon.classList.add('ri-eye-line');
        }
    }

    // Initialize with masked value and form submission loading state
    (() => {
        const textarea = document.getElementById('value');
        if (textarea) {
            textarea.style.webkitTextSecurity = 'disc';
        }

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
    })();
</script>
