{{-- Generate Self-Signed Certificate --}}
<x-app-layout title="Generate Self-Signed Certificate - {{ $domain->domain_name }}">

    {{-- Page Header --}}
    <x-page-header title="Generate Self-Signed Certificate"
        description="Create a self-signed SSL certificate for {{ $domain->domain_name }}" layout="form"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Domains', 'href' => route('platform.domains.index')],
            ['label' => '#' . $domain->id, 'href' => route('platform.domains.show', $domain)],
            ['label' => 'Generate Certificate', 'active' => true],
        ]"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Add Existing Certificate',
                'icon' => 'ri-add-line',
                'variant' => 'btn-outline-primary',
                'href' => route('platform.domains.ssl-certificates.create', $domain),
            ],
            [
                'type' => 'link',
                'label' => 'Back to Domain',
                'icon' => 'ri-arrow-left-line',
                'variant' => 'btn-outline-secondary',
                'href' => route('platform.domains.show', $domain),
            ],
        ]" />

    {{-- Validation errors --}}
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade rounded-4 show mb-4" role="alert">
            <div class="d-flex align-items-start">
                <div class="alert-icon me-3 flex-shrink-0">
                    <i class="ri-error-warning-fill" style="font-size: 1.25rem;"></i>
                </div>
                <div class="flex-grow-1">
                    <h5 class="fw-semibold mb-2">Please fix the following errors</h5>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button class="btn-close" data-bs-dismiss="alert" type="button" aria-label="Close"></button>
        </div>
    @endif

    <form data-dirty-form method="POST"
        action="{{ route('platform.domains.ssl-certificates.generate-self-signed.store', $domain) }}"
        x-data="selfSignedGenerator()"
        novalidate>
        @csrf

        <div class="row g-4">

            {{-- === LEFT COLUMN === --}}
            <div class="col-lg-8">

                {{-- Warning Banner --}}
                <div class="alert alert-warning d-flex align-items-start gap-3 rounded-4 mb-4">
                    <i class="ri-shield-keyhole-line fs-4 flex-shrink-0 mt-1"></i>
                    <div>
                        <h6 class="fw-semibold mb-1">For internal / development use only</h6>
                        <p class="mb-0 small">Self-signed certificates are not trusted by browsers and will trigger security warnings for end users. Use them for local development, private networks, staging environments, or testing — <strong>not for public-facing production sites</strong>.</p>
                    </div>
                </div>

                {{-- Certificate Identity --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="ri-id-card-line me-2 text-primary"></i>Certificate Identity
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="name" class="form-label fw-medium">Friendly Name <span class="text-danger">*</span></label>
                                <input type="text"
                                    class="form-control @error('name') is-invalid @enderror"
                                    id="name" name="name"
                                    value="{{ old('name', $defaultName) }}"
                                    required maxlength="255"
                                    placeholder="e.g., Dev Self-Signed 2025">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">A label to identify this certificate in the dashboard.</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Key Configuration --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="ri-key-2-line me-2 text-primary"></i>Key &amp; Validity
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">

                            {{-- Key Type --}}
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Key Type <span class="text-danger">*</span></label>
                                <div class="d-flex flex-column gap-2" x-model="keyType">
                                    @php
                                        $keyTypes = [
                                            ['value' => 'rsa2048', 'label' => 'RSA 2048-bit', 'description' => 'Standard, widely compatible', 'icon' => 'ri-lock-line', 'badge' => 'Recommended'],
                                            ['value' => 'rsa4096', 'label' => 'RSA 4096-bit', 'description' => 'Stronger but slower to generate', 'icon' => 'ri-lock-2-line', 'badge' => null],
                                            ['value' => 'ec256', 'label' => 'ECDSA P-256', 'description' => 'Fast &amp; compact, modern stack', 'icon' => 'ri-artboard-line', 'badge' => null],
                                            ['value' => 'ec384', 'label' => 'ECDSA P-384', 'description' => 'High-security elliptic curve', 'icon' => 'ri-artboard-2-line', 'badge' => null],
                                        ];
                                    @endphp
                                    @foreach($keyTypes as $kt)
                                        <label class="d-flex align-items-center gap-3 p-3 border rounded-3 cursor-pointer"
                                            :class="keyType === '{{ $kt['value'] }}' ? 'border-primary bg-primary bg-opacity-10' : 'border-secondary-subtle'">
                                            <input type="radio" class="form-check-input mt-0 flex-shrink-0"
                                                name="key_type" value="{{ $kt['value'] }}"
                                                x-model="keyType"
                                                {{ old('key_type', 'rsa2048') === $kt['value'] ? 'checked' : '' }}>
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="fw-medium small">{{ $kt['label'] }}</span>
                                                    @if($kt['badge'])
                                                        <span class="badge bg-success-subtle text-success" style="font-size:0.65rem;">{{ $kt['badge'] }}</span>
                                                    @endif
                                                </div>
                                                <div class="text-muted" style="font-size:0.78rem;">{!! $kt['description'] !!}</div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                                @error('key_type')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Validity --}}
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Validity Period <span class="text-danger">*</span></label>
                                <div class="d-flex flex-column gap-2">
                                    @php
                                        $validityOptions = [
                                            ['days' => 30, 'label' => '30 days', 'note' => 'Short-lived'],
                                            ['days' => 90, 'label' => '90 days', 'note' => 'ACME-style'],
                                            ['days' => 180, 'label' => '6 months', 'note' => ''],
                                            ['days' => 365, 'label' => '1 year', 'note' => 'Standard'],
                                            ['days' => 730, 'label' => '2 years', 'note' => ''],
                                            ['days' => 3650, 'label' => '10 years', 'note' => 'Dev / internal'],
                                        ];
                                    @endphp
                                    @foreach($validityOptions as $vo)
                                        <label class="d-flex align-items-center gap-3 p-2 px-3 border rounded-3 cursor-pointer"
                                            :class="validityDays == {{ $vo['days'] }} ? 'border-primary bg-primary bg-opacity-10' : 'border-secondary-subtle'">
                                            <input type="radio" class="form-check-input mt-0 flex-shrink-0"
                                                name="validity_days" value="{{ $vo['days'] }}"
                                                x-model.number="validityDays"
                                                {{ old('validity_days', 365) == $vo['days'] ? 'checked' : '' }}>
                                            <span class="fw-medium small">{{ $vo['label'] }}</span>
                                            @if($vo['note'])
                                                <span class="text-muted ms-auto" style="font-size:0.75rem;">{{ $vo['note'] }}</span>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                                @error('validity_days')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror

                                <div class="mt-3 p-3 bg-body-secondary rounded-3">
                                    <div class="d-flex align-items-center gap-2 text-muted small">
                                        <i class="ri-calendar-check-line"></i>
                                        <span>Expires:
                                            <strong x-text="expiryDate"></strong>
                                        </span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- Subject Distinguished Name --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="ri-building-line me-2 text-primary"></i>Subject Distinguished Name
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="common_name" class="form-label fw-medium">
                                    Common Name (CN) <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                    class="form-control @error('common_name') is-invalid @enderror"
                                    id="common_name" name="common_name"
                                    value="{{ old('common_name', $domain->domain_name) }}"
                                    required maxlength="255"
                                    placeholder="{{ $domain->domain_name }}">
                                @error('common_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Usually the primary domain name (CN field of the certificate).</div>
                            </div>
                            <div class="col-md-4">
                                <label for="country" class="form-label fw-medium">Country (C)</label>
                                <input type="text"
                                    class="form-control @error('country') is-invalid @enderror"
                                    id="country" name="country"
                                    value="{{ old('country', 'US') }}"
                                    maxlength="2" placeholder="US"
                                    style="text-transform:uppercase;"
                                    oninput="this.value=this.value.toUpperCase()">
                                @error('country')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">ISO 3166-1 alpha-2 (2 chars).</div>
                            </div>
                            <div class="col-md-4">
                                <label for="state" class="form-label fw-medium">State / Province (ST)</label>
                                <input type="text"
                                    class="form-control @error('state') is-invalid @enderror"
                                    id="state" name="state"
                                    value="{{ old('state') }}"
                                    maxlength="128" placeholder="California">
                                @error('state')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="city" class="form-label fw-medium">City / Locality (L)</label>
                                <input type="text"
                                    class="form-control @error('city') is-invalid @enderror"
                                    id="city" name="city"
                                    value="{{ old('city') }}"
                                    maxlength="128" placeholder="San Francisco">
                                @error('city')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="organization" class="form-label fw-medium">Organization (O)</label>
                                <input type="text"
                                    class="form-control @error('organization') is-invalid @enderror"
                                    id="organization" name="organization"
                                    value="{{ old('organization') }}"
                                    maxlength="128" placeholder="My Company, Inc.">
                                @error('organization')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="org_unit" class="form-label fw-medium">Organizational Unit (OU)</label>
                                <input type="text"
                                    class="form-control @error('org_unit') is-invalid @enderror"
                                    id="org_unit" name="org_unit"
                                    value="{{ old('org_unit') }}"
                                    maxlength="128" placeholder="IT Department">
                                @error('org_unit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Subject Alternative Names --}}
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="ri-global-line me-2 text-primary"></i>Subject Alternative Names (SANs)
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                            @click="resetSans">
                            <i class="ri-refresh-line me-1"></i>Reset to default
                        </button>
                    </div>
                    <div class="card-body">
                        <textarea
                            class="form-control font-monospace @error('san_domains') is-invalid @enderror"
                            id="san_domains" name="san_domains"
                            rows="4"
                            placeholder="{{ $domain->domain_name }}&#10;*.{{ $domain->domain_name }}"
                            x-model="sanDomains">{{ old('san_domains', $defaultSans) }}</textarea>
                        @error('san_domains')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">One domain per line (or comma-separated). Wildcard and IP entries are supported.</div>

                        {{-- SAN Preview --}}
                        <div class="mt-3">
                            <div class="d-flex flex-wrap gap-2" x-show="parsedSans.length > 0">
                                <template x-for="(san, i) in parsedSans" :key="i">
                                    <span class="badge bg-secondary-subtle text-secondary border"
                                        style="font-family:monospace; font-size:0.8rem;"
                                        x-text="san"></span>
                                </template>
                            </div>
                            <div class="text-muted small" x-show="parsedSans.length === 0">
                                <i class="ri-information-line me-1"></i>No SANs configured — only CN will be used.
                            </div>
                        </div>
                    </div>
                </div>

            </div>{{-- /LEFT COLUMN --}}

            {{-- === RIGHT COLUMN (Sidebar) === --}}
            <div class="col-lg-4">

                {{-- Summary Card --}}
                <div class="card mb-4 border-primary border-opacity-50">
                    <div class="card-header bg-primary bg-opacity-10 border-primary border-opacity-25">
                        <h6 class="mb-0 text-primary">
                            <i class="ri-eye-line me-2"></i>Certificate Preview
                        </h6>
                    </div>
                    <div class="card-body">
                        <dl class="row row-cols-1 g-2 mb-0 small">
                            <div class="col">
                                <dt class="text-muted fw-normal mb-0">Domain</dt>
                                <dd class="fw-medium font-monospace mb-0">{{ $domain->domain_name }}</dd>
                            </div>
                            <div class="col">
                                <dt class="text-muted fw-normal mb-0">Key Type</dt>
                                <dd class="fw-medium mb-0" x-text="keyTypeLabel"></dd>
                            </div>
                            <div class="col">
                                <dt class="text-muted fw-normal mb-0">Validity</dt>
                                <dd class="fw-medium mb-0" x-text="validityLabel"></dd>
                            </div>
                            <div class="col">
                                <dt class="text-muted fw-normal mb-0">Issued</dt>
                                <dd class="fw-medium mb-0">{{ now()->format('M d, Y') }}</dd>
                            </div>
                            <div class="col">
                                <dt class="text-muted fw-normal mb-0">Expires</dt>
                                <dd class="fw-medium mb-0" x-text="expiryDate"></dd>
                            </div>
                            <div class="col">
                                <dt class="text-muted fw-normal mb-0">SANs</dt>
                                <dd class="fw-medium mb-0" x-text="parsedSans.length + ' entr' + (parsedSans.length === 1 ? 'y' : 'ies')"></dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- Trust Notice --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="ri-shield-line me-2"></i>What you'll get</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0 small d-flex flex-column gap-2">
                            <li class="d-flex gap-2">
                                <i class="ri-check-line text-success flex-shrink-0 mt-1"></i>
                                RSA/EC private key, securely encrypted at rest
                            </li>
                            <li class="d-flex gap-2">
                                <i class="ri-check-line text-success flex-shrink-0 mt-1"></i>
                                x.509 v3 certificate with SAN extension
                            </li>
                            <li class="d-flex gap-2">
                                <i class="ri-check-line text-success flex-shrink-0 mt-1"></i>
                                SHA-256 signature algorithm
                            </li>
                            <li class="d-flex gap-2">
                                <i class="ri-check-line text-success flex-shrink-0 mt-1"></i>
                                Downloadable .key / .crt files
                            </li>
                            <li class="d-flex gap-2">
                                <i class="ri-close-line text-danger flex-shrink-0 mt-1"></i>
                                <span>Not trusted by browsers — <em>no CA chain</em></span>
                            </li>
                        </ul>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="card">
                    <div class="card-body d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ri-shield-keyhole-line me-2"></i>Generate Certificate
                        </button>
                        <a href="{{ route('platform.domains.show', $domain) }}" class="btn btn-outline-secondary w-100">
                            <i class="ri-arrow-left-line me-2"></i>Cancel
                        </a>
                    </div>
                </div>

            </div>{{-- /RIGHT COLUMN --}}

        </div>{{-- /row --}}

    </form>

    <script data-up-execute>
        function selfSignedGenerator() {
            const defaultSans = @json($defaultSans);

            return {
                keyType: '{{ old('key_type', 'rsa2048') }}',
                validityDays: {{ old('validity_days', 365) }},
                sanDomains: `{{ old('san_domains', $defaultSans) }}`,

                get keyTypeLabel() {
                    const labels = {
                        rsa2048: 'RSA 2048-bit',
                        rsa4096: 'RSA 4096-bit',
                        ec256: 'ECDSA P-256',
                        ec384: 'ECDSA P-384',
                    };
                    return labels[this.keyType] ?? this.keyType;
                },

                get validityLabel() {
                    const days = this.validityDays;
                    if (days < 365) return days + ' days';
                    if (days === 365) return '1 year (365 days)';
                    if (days === 730) return '2 years (730 days)';
                    if (days === 3650) return '10 years';
                    return days + ' days';
                },

                get expiryDate() {
                    const d = new Date();
                    d.setDate(d.getDate() + parseInt(this.validityDays, 10));
                    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                },

                get parsedSans() {
                    if (!this.sanDomains.trim()) return [];
                    return [...new Set(
                        this.sanDomains
                            .split(/[\n,]/)
                            .map(s => s.trim())
                            .filter(Boolean)
                    )];
                },

                resetSans() {
                    this.sanDomains = defaultSans;
                },
            };
        }
    </script>

</x-app-layout>
