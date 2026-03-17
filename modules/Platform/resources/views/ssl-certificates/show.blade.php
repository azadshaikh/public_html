{{-- SSL Certificate Show/Details Page --}}
<x-app-layout title="SSL Certificate Details - {{ $domain->domain_name }}">

    {{-- Page Header --}}
    <x-page-header title="{{ $certificateDetails['name'] }}"
        description="SSL certificate details for {{ $domain->domain_name }}" layout="form"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Edit Certificate',
                'icon' => 'ri-pencil-line',
                'variant' => 'btn-primary',
                'href' => route('platform.domains.ssl-certificates.edit', [$domain, $certificate->id]),
            ],
            [
                'type' => 'link',
                'label' => 'Back to Domain',
                'icon' => 'ri-arrow-left-line',
                'variant' => 'btn-outline-secondary',
                'href' => route('platform.domains.show', $domain),
            ],
        ]" :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Domains', 'href' => route('platform.domains.index')],
            ['label' => '#' . $domain->id, 'href' => route('platform.domains.show', $domain)],
            ['label' => '#' . $certificate->id, 'active' => true],
        ]" />

    <div class="row g-4">
        <!-- Main Content Column -->
        <div class="col-lg-8">
            <!-- Certificate Status Card -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center mb-3 mb-3">
                    <h6>Certificate Status</h6>
                    @if($certificateDetails['is_expired'])
                        <span class="badge bg-danger">Expired</span>
                    @elseif($certificateDetails['is_expiring_soon'])
                        <span class="badge bg-warning text-dark">Expiring Soon</span>
                    @else
                        <span class="badge bg-success">Active</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($certificateDetails['is_expired'])
                        <div class="alert alert-danger mb-0">
                            <i class="ri-error-warning-line me-1"></i>
                            <strong>This certificate has expired!</strong> Please renew or replace it to avoid service interruptions.
                        </div>
                    @elseif($certificateDetails['is_expiring_soon'])
                        <div class="alert alert-warning mb-0">
                            <i class="ri-alert-line me-1"></i>
                            <strong>This certificate is expiring soon!</strong> It will expire in {{ $certificateDetails['days_until_expiry'] }} days.
                        </div>
                    @else
                        <div class="alert alert-success mb-0">
                            <i class="ri-shield-check-line me-1"></i>
                            This certificate is valid and active.
                            @if($certificateDetails['days_until_expiry'])
                                It will expire in {{ $certificateDetails['days_until_expiry'] }} days.
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Certificate Information Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6>Certificate Information</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Certificate Name:</dt>
                        <dd class="col-sm-8">{{ $certificateDetails['name'] }}</dd>

                        <dt class="col-sm-4">Type:</dt>
                        <dd class="col-sm-8">
                            @if($certificateDetails['is_wildcard'])
                                <span class="badge bg-info">Wildcard</span> *.{{ $domain->domain_name }}
                            @else
                                <span class="badge text-bg-secondary">Single Domain</span>
                            @endif
                        </dd>

                        @php
                            $caLabels = [
                                'letsencrypt' => "Let's Encrypt",
                                'zerossl' => 'ZeroSSL',
                                'buypass' => 'Buypass',
                                'google' => 'Google Trust Services',
                                'custom' => 'Custom/Manual',
                            ];
                        @endphp
                        <dt class="col-sm-4">Certificate Authority:</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-light text-dark">
                                {{ $caLabels[$certificateDetails['certificate_authority']] ?? $certificateDetails['certificate_authority'] ?? 'Unknown' }}
                            </span>
                        </dd>

                        @if($certificateDetails['issuer'])
                        <dt class="col-sm-4">Issuer:</dt>
                        <dd class="col-sm-8">{{ $certificateDetails['issuer'] }}</dd>
                        @endif

                        @if($certificateDetails['subject'])
                        <dt class="col-sm-4">Subject (CN):</dt>
                        <dd class="col-sm-8">{{ $certificateDetails['subject'] }}</dd>
                        @endif

                        @if(!empty($certificateDetails['domains']))
                        <dt class="col-sm-4">Covered Domains:</dt>
                        <dd class="col-sm-8">
                            @foreach($certificateDetails['domains'] as $coveredDomain)
                                <span class="badge bg-light text-dark me-1">{{ $coveredDomain }}</span>
                            @endforeach
                        </dd>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Validity Dates Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6>Validity Period</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        @if($certificateDetails['issued_at'])
                        <dt class="col-sm-4">Issued On:</dt>
                        <dd class="col-sm-8">{{ \Carbon\Carbon::parse($certificateDetails['issued_at'])->format('F d, Y \a\t g:i A') }}</dd>
                        @endif

                        @if($certificateDetails['expires_at'])
                        <dt class="col-sm-4">Expires On:</dt>
                        <dd class="col-sm-8">
                            <span class="@if($certificateDetails['is_expired']) text-danger @elseif($certificateDetails['is_expiring_soon']) text-warning @endif">
                                {{ \Carbon\Carbon::parse($certificateDetails['expires_at'])->format('F d, Y \a\t g:i A') }}
                            </span>
                        </dd>
                        @endif

                        @if($certificateDetails['days_until_expiry'] !== null)
                        <dt class="col-sm-4">Days Remaining:</dt>
                        <dd class="col-sm-8">
                            @if($certificateDetails['is_expired'])
                                <span class="text-danger">Expired {{ abs($certificateDetails['days_until_expiry']) }} days ago</span>
                            @else
                                <span class="@if($certificateDetails['is_expiring_soon']) text-warning @else text-success @endif">
                                    {{ $certificateDetails['days_until_expiry'] }} days
                                </span>
                            @endif
                        </dd>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Technical Details Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6>Technical Details</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        @if($certificateDetails['serial_number'])
                        <dt class="col-sm-4">Serial Number:</dt>
                        <dd class="col-sm-8 font-monospace text-break">{{ $certificateDetails['serial_number'] }}</dd>
                        @endif

                        @if($certificateDetails['fingerprint'])
                        <dt class="col-sm-4">SHA-256 Fingerprint:</dt>
                        <dd class="col-sm-8 font-monospace text-break small">{{ $certificateDetails['fingerprint'] }}</dd>
                        @endif

                        <dt class="col-sm-4">Created:</dt>
                        <dd class="col-sm-8">{{ $certificate->created_at?->format('F d, Y \a\t g:i A') }}</dd>

                        <dt class="col-sm-4">Last Updated:</dt>
                        <dd class="col-sm-8">{{ $certificate->updated_at?->format('F d, Y \a\t g:i A') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Sidebar Column -->
        <div class="col-lg-4">
            <!-- Quick Actions Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('platform.domains.ssl-certificates.edit', [$domain, $certificate->id]) }}" class="btn btn-primary">
                            <i class="ri-pencil-line me-1"></i> Edit Certificate
                        </a>
                        <a href="{{ route('platform.domains.ssl-certificates.download-key', [$domain, $certificate->id]) }}" class="btn btn-outline-secondary">
                            <i class="ri-key-line me-1"></i> Download Private Key
                        </a>
                        <a href="{{ route('platform.domains.ssl-certificates.download-cert', [$domain, $certificate->id]) }}" class="btn btn-outline-secondary">
                            <i class="ri-file-shield-2-line me-1"></i> Download Certificate
                        </a>
                    </div>
                </div>
            </div>

            <!-- Domain Info Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6>Domain</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Domain:</dt>
                        <dd class="col-sm-7">
                            <a href="{{ route('platform.domains.edit', $domain) }}">{{ $domain->domain_name }}</a>
                        </dd>
                    </dl>
                </div>
            </div>

            <!-- Danger Zone Card -->
            <div class="card border-danger p-0 pb-3">
                <div class="card-header bg-danger text-white pt-2 pb-1">
                    <h6>Danger Zone</h6>
                </div>
                <div class="card-body pt-3">
                    <p class="text-muted small mb-3">Deleting a certificate cannot be undone. Make sure it is not in use before deletion.</p>
                    <form action="{{ route('platform.domains.ssl-certificates.destroy', [$domain, $certificate->id]) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this certificate? This action cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="ri-delete-bin-line me-1"></i> Delete Certificate
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
