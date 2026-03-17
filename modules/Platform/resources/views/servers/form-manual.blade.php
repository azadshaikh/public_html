{{-- Manual Mode Form - Connect Existing HestiaCP Server --}}
@php
    $server = $server ?? new \Modules\Platform\Models\Server();
    $selectedProviderId = old('provider_id');
    if ($selectedProviderId === null && $server->exists) {
        $selectedProviderId = $server->provider?->id;
    }
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="ri-server-line me-1"></i> Connect Existing Server</h6>
                <small class="text-muted">Enter details for your existing HestiaCP server</small>
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
                        <label for="port" class="form-label">HestiaCP Port <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('port') is-invalid @enderror"
                            id="port" name="port"
                            value="{{ old('port', $server->port ?? '8443') }}" placeholder="8443"
                            required>
                        @error('port')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="invalid-feedback">Please provide a port number.</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
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
                <h6 class="mb-0"><i class="ri-key-2-line me-1"></i> HestiaCP API Credentials</h6>
                <small class="text-muted">Generate in Hestia Control Panel → Access Keys</small>
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
                    </div>
                    <div class="col-md-6 mb-3">
                        <x-form-elements.password-input
                            id="access_key_secret"
                            name="access_key_secret"
                            label="Secret Key"
                            labelclass="form-label"
                            inputclass="form-control"
                            :value="old('access_key_secret')"
                            placeholder="40-character Secret Key"
                            :extra-attributes="['required' => 'required']" />
                    </div>
                    <div class="col-12 mb-0">
                        @php
                            $releaseApiKeyValue = old('release_api_key', '');
                            $isEditMode = isset($server) && $server->exists;
                        @endphp
                        <x-form-elements.password-input
                            id="release_api_key"
                            name="release_api_key"
                            label="Release API Key"
                            labelclass="form-label"
                            inputclass="form-control"
                            :value="$releaseApiKeyValue"
                            placeholder="X-Release-Key used by a-sync-releases"
                            infotext="Optional. {{ $isEditMode ? 'Leave blank to keep current key.' : 'Leave blank to use RELEASE_API_KEY from the provisioning server environment.' }}" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Organization</h6>
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
                    <label for="provider_id" class="form-label">Server Provider <span class="text-danger">*</span></label>
                    <select class="form-select @error('provider_id') is-invalid @enderror" id="provider_id"
                        name="provider_id" required>
                        <option value="">Select Provider</option>
                        @foreach ($providerOptions as $option)
                            <option value="{{ $option['value'] }}"
                                {{ $selectedProviderId == $option['value'] ? 'selected' : '' }}>
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

        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" type="submit">
                        <i class="ri-link me-2"></i>
                        <span class="btn-text">Connect Server</span>
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ route('platform.servers.index') }}">
                        <i class="ri-arrow-left-line me-2"></i> Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
