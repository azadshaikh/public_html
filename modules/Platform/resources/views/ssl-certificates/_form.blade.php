{{-- SSL Certificate Form Partial --}}

<div class="row g-4">
    <!-- Main Content Column -->
    <div class="col-lg-8">
        <!-- Certificate Information Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h6>Certificate Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Certificate Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                            value="{{ old('name', $certificateDetails['name'] ?? '') }}"
                            placeholder="e.g., Wildcard SSL 2025" required>
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please provide a certificate name.</div>
                        @enderror
                        <div class="form-text">A friendly name to identify this certificate.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="certificate_authority" class="form-label">Certificate Authority <span class="text-danger">*</span></label>
                        <select class="form-select @error('certificate_authority') is-invalid @enderror" id="certificate_authority" name="certificate_authority" required>
                            <option value="">Select Certificate Authority</option>
                            @foreach($certificateAuthorityOptions ?? [] as $option)
                            <option value="{{ $option['value'] }}" {{ old('certificate_authority', $certificateDetails['certificate_authority'] ?? 'letsencrypt') == $option['value'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                            @endforeach
                        </select>
                        @error('certificate_authority')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please select a Certificate Authority.</div>
                        @enderror
                        <div class="form-text">The CA that issued this certificate (used for renewal).</div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_wildcard" name="is_wildcard" value="1"
                                {{ old('is_wildcard', $certificateDetails['is_wildcard'] ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_wildcard">
                                Wildcard Certificate
                            </label>
                        </div>
                        <div class="form-text">Check if this is a wildcard certificate (*.{{ $domain->domain_name }}).</div>
                    </div>
                    <div class="col-12">
                        <label for="domains" class="form-label">Covered Domains</label>
                        <input type="text" class="form-control @error('domains') is-invalid @enderror" id="domains" name="domains"
                            value="{{ old('domains', is_array($certificateDetails['domains'] ?? null) ? implode(', ', $certificateDetails['domains']) : '') }}"
                            placeholder="{{ $domain->domain_name }}, *.{{ $domain->domain_name }}">
                        @error('domains')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Comma-separated list of domains covered by this certificate.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Certificate Keys Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h6>Certificate Files</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="private_key" class="form-label">
                            Private Key
                            @if(!isset($certificate) || !$certificate->exists)
                                <span class="text-danger">*</span>
                            @endif
                        </label>
                        <textarea class="form-control font-monospace @error('private_key') is-invalid @enderror" id="private_key" name="private_key"
                            rows="6" placeholder="-----BEGIN PRIVATE KEY-----
...
-----END PRIVATE KEY-----" {{ (!isset($certificate) || !$certificate->exists) ? 'required' : '' }}>{{ old('private_key', '') }}</textarea>
                        @error('private_key')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please provide the private key.</div>
                        @enderror
                        <div class="form-text">
                            Paste the private key in PEM format.
                            @if(isset($certificate) && $certificate->exists)
                                <span class="text-warning">Leave empty to keep the existing private key.</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="certificate" class="form-label">
                            Certificate
                            @if(!isset($certificate) || !$certificate->exists)
                                <span class="text-danger">*</span>
                            @endif
                        </label>
                        <textarea class="form-control font-monospace @error('certificate') is-invalid @enderror" id="certificate" name="certificate"
                            rows="6" placeholder="-----BEGIN CERTIFICATE-----
...
-----END CERTIFICATE-----" {{ (!isset($certificate) || !$certificate->exists) ? 'required' : '' }}>{{ old('certificate', '') }}</textarea>
                        @error('certificate')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please provide the certificate.</div>
                        @enderror
                        <div class="form-text">
                            Paste the server certificate in PEM format.
                            @if(isset($certificate) && $certificate->exists)
                                <span class="text-warning">Leave empty to keep the existing certificate.</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="ca_bundle" class="form-label">CA Bundle (Chain)</label>
                        <textarea class="form-control font-monospace @error('ca_bundle') is-invalid @enderror" id="ca_bundle" name="ca_bundle"
                            rows="6" placeholder="-----BEGIN CERTIFICATE-----
...
-----END CERTIFICATE-----">{{ old('ca_bundle', '') }}</textarea>
                        @error('ca_bundle')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Paste the intermediate/root CA certificates (optional, but recommended).</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dates Card (Optional) -->
        <div class="card mb-4">
            <div class="card-header">
                <h6>Certificate Dates (Optional)</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="issuer" class="form-label">Issuer</label>
                        <input type="text" class="form-control @error('issuer') is-invalid @enderror" id="issuer" name="issuer"
                            value="{{ old('issuer', $certificateDetails['issuer'] ?? '') }}"
                            placeholder="Auto-detected from certificate">
                        @error('issuer')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Will be auto-detected if certificate is valid.</div>
                    </div>
                    <div class="col-md-4">
                        <x-form-elements.datepicker
                            class="form-group"
                            id="issued_at"
                            name="issued_at"
                            label="Issue Date"
                            labelclass="form-label"
                            inputclass="form-control"
                            mode="date"
                            value="{{ old('issued_at', isset($certificateDetails['issued_at']) ? \Carbon\Carbon::parse($certificateDetails['issued_at'])->format('Y-m-d') : '') }}"
                            placeholder="Auto-detected" />
                    </div>
                    <div class="col-md-4">
                        <x-form-elements.datepicker
                            class="form-group"
                            id="expires_at"
                            name="expires_at"
                            label="Expiry Date"
                            labelclass="form-label"
                            inputclass="form-control"
                            mode="date"
                            value="{{ old('expires_at', isset($certificateDetails['expires_at']) ? \Carbon\Carbon::parse($certificateDetails['expires_at'])->format('Y-m-d') : '') }}"
                            placeholder="Auto-detected" />
                    </div>
                </div>
                <div class="alert alert-info mt-3 mb-0">
                    <i class="ri-information-line me-1"></i>
                    <strong>Tip:</strong> If you paste a valid certificate, the dates and issuer will be automatically extracted.
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Column -->
    <div class="col-lg-4">
        @if(isset($certificate) && $certificate->exists)
        <!-- Certificate Details Card (Edit Only) -->
        <div class="card mb-4">
            <div class="card-header">
                <h6>Certificate Details</h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    @if($certificateDetails['subject'] ?? null)
                    <dt class="col-sm-5">Subject:</dt>
                    <dd class="col-sm-7">{{ $certificateDetails['subject'] }}</dd>
                    @endif

                    @if($certificateDetails['serial_number'] ?? null)
                    <dt class="col-sm-5">Serial:</dt>
                    <dd class="col-sm-7 text-truncate" title="{{ $certificateDetails['serial_number'] }}">
                        {{ Str::limit($certificateDetails['serial_number'], 20) }}
                    </dd>
                    @endif

                    @if($certificateDetails['fingerprint'] ?? null)
                    <dt class="col-sm-5">Fingerprint:</dt>
                    <dd class="col-sm-7 text-truncate font-monospace small" title="{{ $certificateDetails['fingerprint'] }}">
                        {{ Str::limit($certificateDetails['fingerprint'], 20) }}
                    </dd>
                    @endif

                    <dt class="col-sm-5">Created:</dt>
                    <dd class="col-sm-7">{{ $certificate->created_at?->format('M d, Y g:i A') ?? 'N/A' }}</dd>

                    <dt class="col-sm-5">Updated:</dt>
                    <dd class="col-sm-7">{{ $certificate->updated_at?->format('M d, Y g:i A') ?? 'N/A' }}</dd>
                </dl>
            </div>
        </div>

        <!-- Download Links Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h6>Download Files</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('platform.domains.ssl-certificates.download-key', [$domain, $certificate->id]) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ri-key-line me-1"></i> Download Private Key
                    </a>
                    <a href="{{ route('platform.domains.ssl-certificates.download-cert', [$domain, $certificate->id]) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ri-file-shield-2-line me-1"></i> Download Certificate
                    </a>
                </div>
            </div>
        </div>
        @endif

        <!-- Submit Actions Card -->
        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg" type="submit" id="submit-btn">
                        <i class="{{ $formConfig['submitIcon'] ?? 'ri-save-line' }} me-2"></i>
                        <span class="btn-text">{{ $formConfig['submitText'] ?? 'Save Certificate' }}</span>
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ $formConfig['cancelUrl'] ?? route('platform.domains.show', $domain) }}">
                        <i class="ri-arrow-left-line me-2"></i>Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
