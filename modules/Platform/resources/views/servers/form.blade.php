{{-- Shared Server Form --}}
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Server Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Server Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                            id="name" name="name"
                            value="{{ old('name', $server->name ?? '') }}"
                            placeholder="e.g., Production Server 1" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="invalid-feedback">Please provide a server name.</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="ip" class="form-label">IP Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('ip') is-invalid @enderror"
                            id="ip" name="ip" value="{{ old('ip', $server->ip ?? '') }}"
                            placeholder="e.g., 192.168.1.100" required>
                        @error('ip')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="invalid-feedback">Please provide an IP address.</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="port" class="form-label">Port <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('port') is-invalid @enderror"
                            id="port" name="port"
                            value="{{ old('port', $server->port ?? '8443') }}" placeholder="e.g., 8443"
                            required>
                        @error('port')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="invalid-feedback">Please provide a port number.</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-0">
                        <label for="fqdn" class="form-label">Server FQDN</label>
                        <input type="text" class="form-control @error('fqdn') is-invalid @enderror"
                            id="fqdn" name="fqdn"
                            value="{{ old('fqdn', $server->fqdn ?? '') }}"
                            placeholder="e.g., server.example.com">
                        @error('fqdn')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Authentication</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="access_key_id" class="form-label">Access Key ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('access_key_id') is-invalid @enderror"
                            id="access_key_id" name="access_key_id"
                            value="{{ old('access_key_id', $server->access_key_id ?? '') }}" placeholder="20-character Access Key ID"
                            required>
                        @error('access_key_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="invalid-feedback">Please provide an Access Key ID.</div>
                        @enderror
                        <small class="text-muted">Generated in Hestia Control Panel → Access Keys</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        @php
                            $isEdit = isset($server) && $server->exists;
                        @endphp
                        <label for="access_key_secret" class="form-label">
                            Secret Key @unless($isEdit)<span class="text-danger">*</span>@endunless
                        </label>
                        <x-form-elements.password-input
                            id="access_key_secret"
                            name="access_key_secret"
                            class="mb-0"
                            label="Secret Key"
                            labelclass="d-none"
                            inputclass="form-control"
                            :value="old('access_key_secret')"
                            placeholder="40-character Secret Key"
                            :extra-attributes="['required' => ! $isEdit]" />
                        <small class="text-muted">
                            Generated in Hestia Control Panel → Access Keys
                            @if ($isEdit)
                                · Leave blank to keep the current key
                            @endif
                        </small>
                    </div>
                    <div class="col-12 mb-0">
                        @php
                            $releaseApiKeyValue = old('release_api_key', '');
                        @endphp
                        <label for="release_api_key" class="form-label">
                            Release API Key
                        </label>
                        <x-form-elements.password-input
                            id="release_api_key"
                            name="release_api_key"
                            class="mb-0"
                            label="Release API Key"
                            labelclass="d-none"
                            inputclass="form-control"
                            :value="$releaseApiKeyValue"
                            placeholder="X-Release-Key used by a-sync-releases" />
                        <small class="text-muted">
                            Used by Hestia scripts to access the secured release API.
                            @if ($isEdit)
                                · Leave blank to keep the current key
                            @else
                                · Leave blank to use RELEASE_API_KEY from the provisioning server environment
                            @endif
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center mb-3"
                 data-bs-toggle="collapse" data-bs-target="#ssh-config-collapse" role="button">
                <h6 class="mb-0">
                    <i class="ri-terminal-box-line me-1"></i> SSH Configuration
                    <small class="text-muted ms-2">(Optional - for auto-provisioning)</small>
                </h6>
                <i class="ri-arrow-down-s-line"></i>
            </div>
            <div class="collapse {{ (old('ssh_private_key') || (isset($server) && $server->hasSshPrivateKey())) ? 'show' : '' }}" id="ssh-config-collapse">
                <div class="card-body">
                    <div class="alert alert-info small mb-3">
                        <i class="ri-information-line me-1"></i>
                        SSH credentials enable automatic server provisioning and script updates.
                        The SSH key must have root access to the server.
                    </div>
                    @php
                        $existingPublicKey = old('ssh_public_key', $server->ssh_public_key ?? '');
                    @endphp
                    <div class="row">
                        <div class="col-12 mb-3">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="generate-ssh-key-pair">
                                    <i class="ri-key-2-line me-1"></i> Generate New Key Pair
                                </button>
                                <small id="ssh-key-generate-result" class="text-muted">Generate a key pair to auto-fill SSH credentials.</small>
                            </div>
                        </div>
                        <div class="col-12 mb-3 d-none" id="ssh-authorize-command-wrapper">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="ssh_authorize_command" class="form-label mb-0">Authorize Command (run on server)</label>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="copy-ssh-authorize-command">
                                    <i class="ri-file-copy-line me-1"></i> Copy
                                </button>
                            </div>
                            <textarea class="form-control font-monospace bg-light" id="ssh_authorize_command" rows="3" readonly></textarea>
                            <small class="text-muted">Run this once on your server as root to authorize the generated public key.</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="ssh_port" class="form-label">SSH Port</label>
                            <input type="number" class="form-control @error('ssh_port') is-invalid @enderror"
                                id="ssh_port" name="ssh_port"
                                value="{{ old('ssh_port', $server->ssh_port ?? '22') }}"
                                placeholder="22">
                            @error('ssh_port')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-9 mb-3">
                            <label for="ssh_user" class="form-label">SSH User</label>
                            <input type="text" class="form-control @error('ssh_user') is-invalid @enderror"
                                id="ssh_user" name="ssh_user"
                                value="{{ old('ssh_user', $server->ssh_user ?? 'root') }}"
                                placeholder="root">
                            @error('ssh_user')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Must have sudo/root privileges</small>
                        </div>
                        <div class="col-12 mb-3">
                            <label for="ssh_public_key" class="form-label">SSH Public Key</label>
                            <textarea class="form-control font-monospace @error('ssh_public_key') is-invalid @enderror"
                                id="ssh_public_key" name="ssh_public_key" rows="3"
                                placeholder="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAA...">{{ $existingPublicKey }}</textarea>
                            @error('ssh_public_key')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Auto-filled when you generate a key pair, or paste an existing public key.</small>
                        </div>
                        <div class="col-12 mb-3">
                            @php
                                $hasSshKey = isset($server) && $server->exists && $server->hasSshPrivateKey();
                            @endphp
                            <label for="ssh_private_key" class="form-label">SSH Private Key</label>
                            <textarea class="form-control font-monospace @error('ssh_private_key') is-invalid @enderror"
                                id="ssh_private_key" name="ssh_private_key" rows="6"
                                placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;...&#10;-----END OPENSSH PRIVATE KEY-----">{{ old('ssh_private_key') }}</textarea>
                            @error('ssh_private_key')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">
                                Paste your private key (OpenSSH or PEM format). This will be encrypted in the database.
                                @if ($hasSshKey)
                                    · Leave blank to keep the current key
                                @endif
                            </small>
                        </div>
                        @if(isset($server) && $server->exists)
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-secondary" id="test-ssh-connection">
                                    <i class="ri-plug-line me-1"></i> Test Connection
                                </button>
                                <span id="ssh-test-result" class="ms-2"></span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Server Resources</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="server_cpu" class="form-label">CPU</label>
                        <input type="text" class="form-control @error('server_cpu') is-invalid @enderror"
                            id="server_cpu" name="server_cpu"
                            value="{{ old('server_cpu', $server->server_cpu ?? '') }}"
                            placeholder="e.g., Intel Xeon">
                        @error('server_cpu')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="server_ccore" class="form-label">CPU Cores</label>
                        <input type="number" class="form-control @error('server_ccore') is-invalid @enderror"
                            id="server_ccore" name="server_ccore"
                            value="{{ old('server_ccore', $server->server_ccore ?? '') }}" placeholder="e.g., 4">
                        @error('server_ccore')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="server_ram" class="form-label">RAM (MB)</label>
                        <input type="number" class="form-control @error('server_ram') is-invalid @enderror"
                            id="server_ram" name="server_ram"
                            value="{{ old('server_ram', $server->server_ram ?? '') }}" placeholder="e.g., 8192">
                        @error('server_ram')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="server_storage" class="form-label">Storage (GB)</label>
                        <input type="number" class="form-control @error('server_storage') is-invalid @enderror"
                            id="server_storage" name="server_storage"
                            value="{{ old('server_storage', $server->server_storage ?? '') }}"
                            placeholder="e.g., 100">
                        @error('server_storage')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-0">
                        <label for="server_os" class="form-label">Operating System</label>
                        <input type="text" class="form-control @error('server_os') is-invalid @enderror"
                            id="server_os" name="server_os" value="{{ old('server_os', $server->server_os ?? '') }}"
                            placeholder="e.g., Ubuntu 22.04">
                        @error('server_os')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-0">
                        <label for="max_domains" class="form-label">Maximum Domains (Soft Limit)</label>
                        <input type="number" class="form-control @error('max_domains') is-invalid @enderror"
                            id="max_domains" name="max_domains"
                            value="{{ old('max_domains', $server->max_domains ?? '') }}"
                            placeholder="e.g., 100" min="0">
                        @error('max_domains')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Optional capacity planning limit. Leave blank for unlimited.</small>
                    </div>
                </div>
            </div>
        </div>


        <div class="card">
            <div class="card-header">
                <h6 class="mb-3">Version Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="astero_version" class="form-label">Astero Version</label>
                        <input type="text" class="form-control @error('astero_version') is-invalid @enderror"
                            id="astero_version" name="astero_version"
                            value="{{ old('astero_version', $server->astero_version ?? '') }}" placeholder="e.g., v1.0.0">
                        @error('astero_version')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-0">
                        <label for="hestia_version" class="form-label">Hestia Version</label>
                        <input type="text" class="form-control @error('hestia_version') is-invalid @enderror"
                            id="hestia_version" name="hestia_version"
                            value="{{ old('hestia_version', $server->hestia_version ?? '') }}"
                            placeholder="e.g., 1.8.0">
                        @error('hestia_version')
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
                    <label for="type" class="form-label">Server Type <span class="text-danger">*</span></label>
                    <select class="form-select @error('type') is-invalid @enderror" id="type"
                        name="type" required>
                        <option value="">Select Type</option>
                        @foreach ($typeOptions as $option)
                            <option value="{{ $option['value'] }}"
                                {{ old('type', $server->type ?? '') == $option['value'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @else
                        <div class="invalid-feedback">Please select a server type.</div>
                    @enderror
                </div>

                <div class="mb-0">
                    <label for="provider_id" class="form-label">Server Provider <span
                            class="text-danger">*</span></label>
                    <select class="form-select @error('provider_id') is-invalid @enderror" id="provider_id"
                        name="provider_id" required>
                        <option value="">Select Provider</option>
                        @foreach ($providerOptions as $option)
                            <option value="{{ $option['value'] }}"
                                {{ old('provider_id', $server->provider?->id ?? '') == $option['value'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('provider_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @else
                        <div class="invalid-feedback">Please select a server provider.</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Location</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <x-form-elements.country-select
                        class="form-group"
                        id="location_country_code"
                        name="location_country_code"
                        label="Country"
                        labelclass="form-label"
                        inputclass="form-select"
                        value="{{ old('location_country_code', $server->location_country_code ?? '') }}"
                        placeholder="-- Select Country --" />
                    <x-form-elements.hidden-input
                        id="location_country"
                        name="location_country"
                        :value="old('location_country', $server->location_country ?? '')" />
                </div>
                <div class="mb-0">
                    <x-form-elements.city-select
                        class="form-group"
                        id="location_city"
                        name="location_city"
                        label="City"
                        labelclass="form-label"
                        inputclass="form-select"
                        value="{{ old('location_city', $server->location_city ?? '') }}"
                        placeholder="Choose country first"
                        :disabled="!old('location_country_code', $server->location_country_code ?? '')"
                        countryCodeField="location_country_code"
                        cityCodeField="location_city_code"
                        :cityCode="old('location_city_code', $server->location_city_code ?? '')" />
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-3">Status & Monitoring</h6>
            </div>
            <div class="card-body">
                @if (isset($server) && $server->exists)
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select @error('status') is-invalid @enderror" id="status"
                            name="status">
                            @foreach ($statusOptions as $option)
                                <option value="{{ $option['value'] }}"
                                    {{ old('status', $server->status ?? 'active') == $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @else
                    <p class="text-muted mb-3">Status will be set to Active on creation.</p>
                @endif

                <div class="mb-0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="monitoring" name="monitor" type="checkbox"
                            value="1" {{ old('monitor', $server->monitor ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="monitoring">
                            Enable Monitoring
                        </label>
                    </div>
                    <small class="text-muted">Track server health and performance metrics</small>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg" type="submit" id="submit-btn">
                        <i class="{{ $formConfig['submitIcon'] ?? 'ri-save-line' }} me-2"></i>
                        <span class="btn-text">{{ $formConfig['submitText'] ?? 'Save Server' }}</span>
                    </button>
                    <a class="btn btn-outline-secondary"
                        href="{{ $formConfig['cancelUrl'] ?? route('platform.servers.index') }}">
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

    // Sync country name when country code is selected
    (() => {
        const countrySelect = document.getElementById('location_country_code');
        const countryNameInput = document.getElementById('location_country');
        if (countrySelect && countrySelect.dataset.formInit === '1') return;
        if (countrySelect) countrySelect.dataset.formInit = '1';

        // Form submission loading state
        const form = document.querySelector('form.needs-validation');
        const submitBtn = document.getElementById('submit-btn');

        if (form && submitBtn) {
            if (form.dataset.submitInit !== '1') {
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
        }

        if (countrySelect && countryNameInput) {
            function updateCountryName() {
                // Get selected option text (country name)
                const instance = countrySelect.choicesInstance;
                if (instance) {
                    const selection = instance.getValue();
                    const label = Array.isArray(selection) && selection.length
                        ? selection[0].label
                        : (selection && selection.label ? selection.label : '');
                    countryNameInput.value = label;
                } else if (countrySelect.selectedOptions && countrySelect.selectedOptions[0]) {
                    countryNameInput.value = countrySelect.selectedOptions[0].textContent.trim();
                }
            }

            countrySelect.addEventListener('change', updateCountryName);

            // Set initial value if exists
            if (countrySelect.value) {
                updateCountryName();
            }
        }

        // Test SSH Connection button
        const testSshBtn = document.getElementById('test-ssh-connection');
        const sshTestResult = document.getElementById('ssh-test-result');
        const generateSshBtn = document.getElementById('generate-ssh-key-pair');
        const sshGenerateResult = document.getElementById('ssh-key-generate-result');
        const sshPrivateKeyInput = document.getElementById('ssh_private_key');
        const ipInput = document.getElementById('ip');
        const sshPortInput = document.getElementById('ssh_port');
        const sshUserInput = document.getElementById('ssh_user');
        const sshPublicKeyInput = document.getElementById('ssh_public_key');
        const sshAuthorizeWrapper = document.getElementById('ssh-authorize-command-wrapper');
        const sshAuthorizeCommandInput = document.getElementById('ssh_authorize_command');
        const copyAuthorizeCommandBtn = document.getElementById('copy-ssh-authorize-command');

        if (sshAuthorizeWrapper && sshPublicKeyInput && sshPublicKeyInput.value.trim() !== '') {
            sshAuthorizeWrapper.classList.remove('d-none');
        }

        if (generateSshBtn && !generateSshBtn.dataset.sshGenerateInit) {
            generateSshBtn.dataset.sshGenerateInit = '1';

            generateSshBtn.addEventListener('click', async function () {
                generateSshBtn.disabled = true;
                generateSshBtn.innerHTML = '<i class="ri-loader-4-line me-1 spin"></i> Generating...';

                if (sshGenerateResult) {
                    sshGenerateResult.className = 'text-muted';
                    sshGenerateResult.textContent = 'Creating new SSH key pair...';
                }

                try {
                    const response = await fetch('{{ route('platform.servers.generate-ssh-key') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Failed to generate SSH key pair');
                    }

                    if (sshPrivateKeyInput) {
                        sshPrivateKeyInput.value = data.private_key || '';
                    }

                    if (sshPublicKeyInput) {
                        sshPublicKeyInput.value = data.public_key || '';
                    }

                    if (sshAuthorizeCommandInput) {
                        sshAuthorizeCommandInput.value = data.command || '';
                    }

                    if (sshAuthorizeWrapper) {
                        sshAuthorizeWrapper.classList.remove('d-none');
                    }

                    if (sshGenerateResult) {
                        sshGenerateResult.className = 'text-success';
                        sshGenerateResult.textContent = 'New SSH key pair generated. Save server to persist it.';
                    }

                    if (sshTestResult) {
                        sshTestResult.innerHTML = '';
                    }
                } catch (error) {
                    if (sshGenerateResult) {
                        sshGenerateResult.className = 'text-danger';
                        sshGenerateResult.textContent = error.message || 'Failed to generate key pair';
                    }
                } finally {
                    generateSshBtn.disabled = false;
                    generateSshBtn.innerHTML = '<i class="ri-key-2-line me-1"></i> Generate New Key Pair';
                }
            });
        }

        if (copyAuthorizeCommandBtn && !copyAuthorizeCommandBtn.dataset.copyInit) {
            copyAuthorizeCommandBtn.dataset.copyInit = '1';

            const copyText = async (value, fallbackInput) => {
                const text = value || '';
                if (!text.trim()) {
                    return false;
                }

                const fallbackCopy = () => {
                    if (!fallbackInput) {
                        return false;
                    }

                    fallbackInput.focus();
                    fallbackInput.select();
                    fallbackInput.setSelectionRange(0, text.length);

                    try {
                        const copied = document.execCommand('copy');
                        fallbackInput.setSelectionRange(0, 0);

                        return copied;
                    } catch (e) {
                        return false;
                    }
                };

                if (navigator.clipboard?.writeText) {
                    try {
                        await navigator.clipboard.writeText(text);

                        return true;
                    } catch (e) {
                        return fallbackCopy();
                    }
                }

                return fallbackCopy();
            };

            copyAuthorizeCommandBtn.addEventListener('click', async function () {
                const command = sshAuthorizeCommandInput?.value || '';
                if (!command.trim()) {
                    return;
                }

                const showCopied = () => {
                    const original = copyAuthorizeCommandBtn.innerHTML;
                    copyAuthorizeCommandBtn.innerHTML = '<i class="ri-check-line me-1"></i> Copied';
                    setTimeout(() => {
                        copyAuthorizeCommandBtn.innerHTML = original;
                    }, 1200);
                };

                if (await copyText(command, sshAuthorizeCommandInput)) {
                    showCopied();
                }
            });
        }

        if (testSshBtn && !testSshBtn.dataset.sshInit) {
            testSshBtn.dataset.sshInit = '1';

            testSshBtn.addEventListener('click', async function() {
                const serverId = '{{ $server->id ?? "" }}';
                if (!serverId) return;

                const ip = ipInput?.value?.trim() || '';
                const sshPort = sshPortInput?.value?.trim() || '22';
                const sshUser = sshUserInput?.value?.trim() || 'root';
                const sshPrivateKey = sshPrivateKeyInput?.value || '';

                if (!ip || !sshPrivateKey.trim()) {
                    sshTestResult.innerHTML = '<span class="badge bg-danger"><i class="ri-close-line me-1"></i>Failed</span> <small class="text-danger">IP and SSH private key are required to test connection.</small>';
                    return;
                }

                testSshBtn.disabled = true;
                testSshBtn.innerHTML = '<i class="ri-loader-4-line me-1 spin"></i> Testing...';
                sshTestResult.innerHTML = '';

                try {
                    const response = await fetch(`{{ route('platform.servers.test-connection', ['server' => $server->id ?? 0]) }}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            ip: ip,
                            ssh_port: sshPort,
                            ssh_user: sshUser,
                            ssh_private_key: sshPrivateKey
                        })
                    });

                    const data = await response.json();

                    if (data.status === 'success') {
                        sshTestResult.innerHTML = '<span class="badge bg-success"><i class="ri-check-line me-1"></i>Connected</span>';
                        if (data.data?.os_info) {
                            sshTestResult.innerHTML += ' <small class="text-muted">' + data.data.os_info.substring(0, 60) + '</small>';
                        }
                    } else {
                        sshTestResult.innerHTML = '<span class="badge bg-danger"><i class="ri-close-line me-1"></i>Failed</span> <small class="text-danger">' + (data.message || 'Connection failed') + '</small>';
                    }
                } catch (error) {
                    sshTestResult.innerHTML = '<span class="badge bg-danger"><i class="ri-close-line me-1"></i>Error</span> <small class="text-danger">' + error.message + '</small>';
                } finally {
                    testSshBtn.disabled = false;
                    testSshBtn.innerHTML = '<i class="ri-plug-line me-1"></i> Test Connection';
                }
            });
        }
    })();
</script>
