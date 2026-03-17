<x-app-layout :title="$page_title">
    @php
        $isTrashed = !empty($domain->deleted_at);

        // Status configuration
        $statusConfig = match($domain->status) {
            'active' => ['icon' => 'ri-checkbox-circle-fill', 'color' => 'success', 'bg' => 'success-subtle'],
            'inactive' => ['icon' => 'ri-close-circle-fill', 'color' => 'secondary', 'bg' => 'secondary-subtle'],
            'expired' => ['icon' => 'ri-error-warning-fill', 'color' => 'danger', 'bg' => 'danger-subtle'],
            'pending' => ['icon' => 'ri-time-fill', 'color' => 'warning', 'bg' => 'warning-subtle'],
            default => ['icon' => 'ri-question-fill', 'color' => 'secondary', 'bg' => 'secondary-subtle'],
        };

        // Calculate days until expiry
        $daysUntilExpiry = null;
        $expiryWarning = false;
        if ($domain->expiry_date) {
            $daysUntilExpiry = (int) round(now()->diffInDays($domain->expiry_date, false));
            $expiryWarning = $daysUntilExpiry <= 30 && $daysUntilExpiry >= 0;
        }
    @endphp

    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Domains', 'href' => route('platform.domains.index')],
            ['label' => '#' . $domain->id, 'active' => true],
        ];

        $actions = [
            [
                'type' => 'link',
                'label' => 'Visit Site',
                'icon' => 'ri-external-link-line',
                'variant' => 'btn-outline-primary',
                'href' => 'https://' . $domain->domain_name,
                'target' => '_blank',
            ],
        ];

        if (auth()->user()?->can('edit_domains')) {
            $actions[] = [
                'type' => 'link',
                'label' => 'Edit',
                'icon' => 'ri-pencil-line',
                'href' => route('platform.domains.edit', $domain->id),
                'variant' => 'btn-outline-secondary',
            ];

            $actions[] = [
                'type' => 'dropdown',
                'label' => 'More',
                'icon' => 'ri-more-fill',
                'variant' => 'btn-outline-secondary',
                'items' => [
                    [
                        'label' => 'Refresh WHOIS',
                        'icon' => 'ri-refresh-line',
                        'href' => route('platform.domains.refresh-whois', $domain->id),
                        'class' => 'confirmation-btn',
                        'attributes' => [
                            'data-method' => 'POST',
                            'data-title' => 'Refresh WHOIS',
                            'data-message' => 'Fetch the latest WHOIS details and update this domain?',
                            'data-confirmButtonText' => 'Refresh',
                            'data-loaderButtonText' => 'Refreshing...',
                        ],
                    ],
                ],
            ];
        }

        $actions[] = [
            'type' => 'link',
            'label' => 'Back',
            'icon' => 'ri-arrow-left-line',
            'href' => route('platform.domains.index'),
            'variant' => 'btn-outline-secondary',
        ];
    @endphp

    <x-page-header :breadcrumbs="$breadcrumbs" :actions="$actions" title="{{ $domain->domain_name }}">
        <x-slot:custom_title>
            <div class="d-flex align-items-center gap-3">
                <span class="h3 mb-0">{{ $domain->domain_name }}</span>
                <span class="badge bg-{{ $statusConfig['bg'] }} text-{{ $statusConfig['color'] }} fs-6">
                    <i class="{{ $statusConfig['icon'] }} me-1"></i>{{ ucfirst($domain->status) }}
                </span>
            </div>
        </x-slot:custom_title>
        <x-slot:description>
            View and manage domain registration details, DNS records, and SSL certificates.
        </x-slot:description>
    </x-page-header>

    {{-- Trashed Warning Banner --}}
    @if($isTrashed)
        <div class="alert alert-warning d-flex align-items-center mb-4">
            <div class="d-flex align-items-center justify-content-center bg-warning bg-opacity-25 me-3" style="width: 48px; height: 48px; border-radius: 50%;">
                <i class="ri-delete-bin-line fs-4 text-warning"></i>
            </div>
            <div class="flex-grow-1">
                <div class="fw-semibold">This domain is in trash</div>
                <div class="small text-muted">Trashed on {{ $domain->deleted_at->format('M d, Y \a\t h:i A') }}</div>
            </div>
            @can('restore_domains')
                <a class="btn btn-warning confirmation-btn"
                    data-title="Restore Domain"
                    data-method="PATCH"
                    data-message="Are you sure you want to restore this domain?"
                    data-confirmButtonText="Restore"
                    data-loaderButtonText="Restoring..."
                    href="{{ route('platform.domains.restore', $domain->id) }}">
                    <i class="ri-refresh-line me-1"></i>Restore
                </a>
            @endcan
        </div>
    @endif

    {{-- Expiry Warning Banner --}}
    @if($expiryWarning && !$isTrashed)
        <div class="alert alert-danger d-flex align-items-center mb-4">
            <div class="d-flex align-items-center justify-content-center bg-danger bg-opacity-25 me-3" style="width: 48px; height: 48px; border-radius: 50%;">
                <i class="ri-error-warning-line fs-4 text-danger"></i>
            </div>
            <div class="flex-grow-1">
                <div class="fw-semibold">Domain Expiring Soon!</div>
                <div class="small text-muted">
                    @if($daysUntilExpiry == 0)
                        This domain expires today!
                    @elseif($daysUntilExpiry == 1)
                        This domain expires tomorrow.
                    @else
                        This domain will expire in {{ $daysUntilExpiry }} days ({{ $domain->expiry_date->format('M d, Y') }}).
                    @endif
                </div>
            </div>
        </div>
    @endif

    <style>
        .domain-command-center .dm-panel {
            border-color: rgba(var(--bs-secondary-rgb), 0.18);
        }

        .dm-hero {
            background: radial-gradient(circle at 0 0, rgba(var(--bs-primary-rgb), 0.1), transparent 42%),
                radial-gradient(circle at 100% 0, rgba(var(--bs-info-rgb), 0.08), transparent 48%);
        }

        .dm-health-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            padding: 0.3rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid rgba(var(--bs-secondary-rgb), 0.2);
        }

        .dm-metric-box {
            border: 1px solid rgba(var(--bs-secondary-rgb), 0.18);
            border-radius: 0.65rem;
            padding: 0.75rem;
            background-color: rgba(var(--bs-light-rgb), 0.65);
        }

        .dm-metric-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--bs-secondary-color);
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .dm-metric-value {
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .dm-action-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem;
        }

        .dm-action-grid .btn {
            justify-content: center;
        }

        .dm-ops-divider {
            border-top: 1px dashed rgba(var(--bs-secondary-rgb), 0.3);
            margin-top: 0.25rem;
            padding-top: 0.9rem;
        }

        .dm-ops-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .dm-ops-meter {
            width: 100%;
            height: 6px;
        }
    </style>

    @php
        $defaultTab = 'general';
        $sslCount = isset($sslCertificates) ? $sslCertificates->count() : 0;
        $dnsRecordsCount = $domain->dnsRecords?->count() ?? 0;
        $tld = \Illuminate\Support\Str::afterLast($domain->domain_name, '.');
        $registrarName = $domain->registrar?->name ?? $domain->registrar_name;
        $statusChipClass = 'bg-' . $statusConfig['bg'] . ' text-' . $statusConfig['color'];
        $expiryPercent = null;

        if ($domain->registered_date && $domain->expiry_date && $domain->registered_date->lt($domain->expiry_date)) {
            $totalLifetimeDays = max(1, $domain->registered_date->diffInDays($domain->expiry_date));
            $remainingDays = max(0, $daysUntilExpiry ?? 0);
            $expiryPercent = (int) round(min(100, ($remainingDays / $totalLifetimeDays) * 100));
        }
    @endphp

    <div class="domain-command-center mb-4">
        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card dm-panel dm-hero h-100 border">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <div class="small text-muted text-uppercase fw-semibold mb-2">Command Center</div>
                                <div class="h4 mb-1">{{ $domain->domain_name }}</div>
                                <a href="https://{{ $domain->domain_name }}" target="_blank" class="text-decoration-none fw-semibold">
                                    https://{{ $domain->domain_name }}
                                </a>
                            </div>
                            <div class="text-end">
                                <div class="small text-muted">TLD</div>
                                <div class="fw-semibold font-monospace">.{{ $tld ?: '--' }}</div>
                                <div class="small text-muted mt-1">
                                    Updated: {{ $domain->updated_date?->format('M d, Y') ?? '--' }}
                                </div>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-sm-6 col-lg-3">
                                <div class="dm-metric-box h-100">
                                    <div class="dm-metric-label">DNS Records</div>
                                    <div class="dm-metric-value">{{ $dnsRecordsCount }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="dm-metric-box h-100">
                                    <div class="dm-metric-label">SSL Certificates</div>
                                    <div class="dm-metric-value">{{ $sslCount }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="dm-metric-box h-100">
                                    <div class="dm-metric-label">Auto Renew</div>
                                    <div class="dm-metric-value">{{ $domain->auto_renew ? 'Enabled' : 'Disabled' }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="dm-metric-box h-100">
                                    <div class="dm-metric-label">Expiry</div>
                                    <div class="dm-metric-value">
                                        @if($domain->expiry_date)
                                            {{ $domain->expiry_date->format('M d, Y') }}
                                        @else
                                            --
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="dm-health-chip {{ $statusChipClass }}">
                                <i class="{{ $statusConfig['icon'] }}"></i>
                                {{ ucfirst($domain->status) }}
                            </span>
                            <span class="dm-health-chip {{ $domain->auto_renew ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                <i class="ri-refresh-line"></i>
                                Auto Renew: {{ $domain->auto_renew ? 'On' : 'Off' }}
                            </span>
                            <span class="dm-health-chip {{ $domain->privacy_protection ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">
                                <i class="{{ $domain->privacy_protection ? 'ri-shield-check-line' : 'ri-eye-line' }}"></i>
                                Privacy: {{ $domain->privacy_protection ? 'Protected' : 'Public' }}
                            </span>
                            <span class="dm-health-chip {{ $domain->domain_lock ? 'bg-warning-subtle text-warning' : 'bg-secondary-subtle text-secondary' }}">
                                <i class="{{ $domain->domain_lock ? 'ri-lock-line' : 'ri-lock-unlock-line' }}"></i>
                                Lock: {{ $domain->domain_lock ? 'Locked' : 'Unlocked' }}
                            </span>
                        </div>

                        <div class="row g-3 small">
                            <div class="col-md-6">
                                <div class="text-muted text-uppercase fw-semibold small mb-2">Ownership</div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Agency</span>
                                    <span class="fw-semibold">{{ $domain->agency->name ?? 'Not assigned' }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Registrar</span>
                                    <span class="fw-semibold">{{ $registrarName ?: 'Not assigned' }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Registered</span>
                                    <span class="fw-semibold">{{ $domain->registered_date ? $domain->registered_date->format('M d, Y') : '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Created</span>
                                    <span class="fw-semibold">{{ $domain->created_at ? $domain->created_at->format('M d, Y') : '--' }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted text-uppercase fw-semibold small mb-2">Infrastructure</div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">DNS Zone</span>
                                    <span class="fw-semibold">{{ $domain->dns_zone_id ?? '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">NS 1</span>
                                    <span class="fw-semibold font-monospace">{{ $domain->name_server_1 ?? '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">NS 2</span>
                                    <span class="fw-semibold font-monospace">{{ $domain->name_server_2 ?? '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">SSL Certificates</span>
                                    <span class="fw-semibold">{{ $sslCount }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card dm-panel h-100 border">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-flashlight-line me-1"></i> Operations
                        </h5>
                    </div>
                    <div class="card-body d-flex flex-column gap-3">
                        <div class="small text-muted">
                            High-impact actions for domain lifecycle, WHOIS refresh, and SSL/DNS management.
                        </div>

                        @if(!$isTrashed)
                            <div class="dm-action-grid">
                                <a class="btn btn-sm btn-outline-primary" href="https://{{ $domain->domain_name }}" target="_blank">
                                    <i class="ri-external-link-line me-1"></i>Visit
                                </a>

                                @can('edit_domains')
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('platform.domains.edit', $domain->id) }}">
                                        <i class="ri-pencil-line me-1"></i>Edit
                                    </a>

                                    <a class="btn btn-sm btn-outline-info confirmation-btn"
                                        data-method="POST"
                                        data-title="Refresh WHOIS"
                                        data-message="Fetch the latest WHOIS details and update this domain?"
                                        data-confirmButtonText="Refresh"
                                        data-loaderButtonText="Refreshing..."
                                        href="{{ route('platform.domains.refresh-whois', $domain->id) }}">
                                        <i class="ri-refresh-line me-1"></i>WHOIS
                                    </a>
                                @endcan

                                @can('view_domain_dns_records')
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('platform.domains.show', $domain->id) }}?section=dns">
                                        <i class="ri-server-line me-1"></i>DNS
                                    </a>
                                @endcan

                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('platform.domains.show', $domain->id) }}?section=ssl">
                                    <i class="ri-shield-keyhole-line me-1"></i>SSL
                                </a>

                                @can('delete_domains')
                                    <a class="btn btn-sm btn-outline-danger confirmation-btn"
                                        data-title="Trash Domain"
                                        data-method="DELETE"
                                        data-message="Are you sure you want to trash this domain?"
                                        data-confirmButtonText="Trash"
                                        data-loaderButtonText="Trashing..."
                                        href="{{ route('platform.domains.destroy', $domain->id) }}">
                                        <i class="ri-delete-bin-line me-1"></i>Trash
                                    </a>
                                @endcan
                            </div>
                        @else
                            <div class="alert alert-warning mb-0 small">
                                <i class="ri-alert-line me-1"></i>
                                This domain is in trash. Restore it first to continue operations.
                            </div>
                        @endif

                        <div class="dm-ops-divider">
                            <div class="small text-muted text-uppercase fw-semibold mb-2">Renewal Window</div>
                            <div class="dm-ops-row mb-2">
                                <span class="small text-muted">Time to expiry</span>
                                <span class="badge {{ $expiryWarning ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary' }}">
                                    @if($daysUntilExpiry === null)
                                        --
                                    @elseif($daysUntilExpiry < 0)
                                        Expired
                                    @else
                                        {{ $daysUntilExpiry }}d
                                    @endif
                                </span>
                            </div>
                            @if($expiryPercent !== null)
                                <div class="progress dm-ops-meter mb-3">
                                    <div class="progress-bar {{ $expiryWarning ? 'bg-danger' : 'bg-success' }}" style="width: {{ $expiryPercent }}%;"></div>
                                </div>
                            @endif
                            <div class="dm-ops-row mb-2">
                                <span class="small text-muted">DNS Records</span>
                                <span class="badge bg-primary-subtle text-primary">{{ $dnsRecordsCount }}</span>
                            </div>
                            <div class="dm-ops-row">
                                <span class="small text-muted">SSL Certificates</span>
                                <span class="badge bg-info-subtle text-info">{{ $sslCount }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabbed Content --}}
    @php
        $activeTab = request()->query('section', $defaultTab);
        $tabs = [
            ['name' => 'general', 'label' => 'General', 'icon' => 'ri-settings-3-line'],
        ];

        if (auth()->user()?->can('view_domain_dns_records')) {
            $tabs[] = ['name' => 'dns', 'label' => 'DNS', 'icon' => 'ri-server-line'];
        }

        $tabs[] = ['name' => 'ssl', 'label' => 'SSL', 'icon' => 'ri-shield-keyhole-line', 'count' => $sslCount];
        $tabs[] = ['name' => 'notes', 'label' => 'Notes', 'icon' => 'ri-sticky-note-line'];
        $tabs[] = ['name' => 'activity', 'label' => 'Activity', 'icon' => 'ri-pulse-line'];
    @endphp

    <x-tabs param="section" :active="$activeTab" :tabs="$tabs" class="border shadow-none">
        <x-slot:general>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header mb-3">
                            <h6 class="card-title mb-0">Name Servers</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">NS 1</span>
                                    <span class="fw-semibold font-monospace">{{ $domain->name_server_1 ?? '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">NS 2</span>
                                    <span class="fw-semibold font-monospace">{{ $domain->name_server_2 ?? '--' }}</span>
                                </div>
                                @if($domain->name_server_3)
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">NS 3</span>
                                        <span class="fw-semibold font-monospace">{{ $domain->name_server_3 }}</span>
                                    </div>
                                @endif
                                @if($domain->name_server_4)
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">NS 4</span>
                                        <span class="fw-semibold font-monospace">{{ $domain->name_server_4 }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header mb-3">
                            <h6 class="card-title mb-0">Timestamps</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Created</span>
                                    <span class="fw-semibold">{{ $domain->created_at ? $domain->created_at->format('M d, Y') : '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Last Updated</span>
                                    <span class="fw-semibold">{{ $domain->updated_at ? $domain->updated_at->diffForHumans() : '--' }}</span>
                                </div>
                                @if($domain->createdBy)
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Created By</span>
                                        <span class="fw-semibold">{{ $domain->createdBy?->full_name ?? $domain->createdBy?->name ?? '--' }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-slot:general>

        @if(auth()->user()?->can('view_domain_dns_records'))
            <x-slot:dns>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">DNS Records</h6>
                    @can('add_domain_dns_records')
                        <a class="btn btn-primary drawer-btn" data-bs-toggle="offcanvas"
                            data-bs-target="#domain-drawer"
                            up-follow="false"
                            href="{{ route('platform.dns.create', ['domain_id' => $domain->id]) }}">
                            <i class="ri-add-line me-1"></i>Add DNS Record
                        </a>
                    @endcan
                </div>
                @include('platform::dns_records._datagrid', ['domain' => $domain, 'config' => $dnsRecordsConfig ?? []])
            </x-slot:dns>
        @endif

        <x-slot:ssl>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">SSL Certificates</h6>
                @can('edit_domains')
                    <a class="btn btn-primary" href="{{ route('platform.domains.ssl-certificates.create', $domain) }}">
                        <i class="ri-add-line me-1"></i>Add Certificate
                    </a>
                @endcan
            </div>

            @if(isset($sslCertificates) && $sslCertificates->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>CA</th>
                                <th>Expires</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $caLabels = [
                                    'letsencrypt' => "Let's Encrypt",
                                    'zerossl' => 'ZeroSSL',
                                    'google' => 'Google',
                                    'custom' => 'Custom',
                                ];
                            @endphp
                            @foreach($sslCertificates as $cert)
                                <tr>
                                    <td>
                                        <a href="{{ route('platform.domains.ssl-certificates.show', [$domain, $cert['id']]) }}" class="text-decoration-none fw-semibold">
                                            {{ $cert['name'] }}
                                        </a>
                                        @if($cert['is_wildcard'])
                                            <span class="badge bg-info ms-1">Wildcard</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($cert['is_wildcard'])
                                            <span class="text-muted">*.{{ $domain->domain_name }}</span>
                                        @else
                                            <span class="text-muted">Single</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            {{ $caLabels[$cert['certificate_authority']] ?? $cert['certificate_authority'] ?? 'Unknown' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($cert['expires_at'])
                                            @php
                                                $expiresAt = \Carbon\Carbon::parse($cert['expires_at']);
                                            @endphp
                                            <span class="@if($cert['is_expired']) text-danger @elseif($cert['is_expiring_soon']) text-warning @else text-muted @endif">
                                                {{ $expiresAt->format('M d, Y') }}
                                            </span>
                                        @else
                                            <span class="text-muted">--</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($cert['is_expired'])
                                            <span class="badge bg-danger">Expired</span>
                                        @elseif($cert['is_expiring_soon'])
                                            <span class="badge bg-warning text-dark">Expiring</span>
                                        @else
                                            <span class="badge bg-success">Active</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('platform.domains.ssl-certificates.show', [$domain, $cert['id']]) }}" class="btn btn-outline-secondary" title="View">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            @can('edit_domains')
                                                <a href="{{ route('platform.domains.ssl-certificates.edit', [$domain, $cert['id']]) }}" class="btn btn-outline-secondary" title="Edit">
                                                    <i class="ri-pencil-line"></i>
                                                </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="ri-shield-keyhole-line fs-1 mb-3 d-block opacity-50"></i>
                    <p class="mb-2">No SSL certificates found for this domain.</p>
                    @can('edit_domains')
                        <a href="{{ route('platform.domains.ssl-certificates.create', $domain) }}" class="btn btn-primary btn-sm">
                            <i class="ri-add-line me-1"></i>Add Certificate
                        </a>
                    @endcan
                </div>
            @endif
        </x-slot:ssl>

        <x-slot:notes>
            <x-app.notes :model="$domain" />
        </x-slot:notes>

        <x-slot:activity>
            @if(isset($activities) && count($activities) > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Event</th>
                                <th>Description</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activities as $activity)
                                @php
                                    $eventClass = match($activity->event) {
                                        'created', 'create', 'activated' => 'success',
                                        'updated', 'update', 'restored' => 'primary',
                                        'deleted', 'delete' => 'danger',
                                        default => 'secondary'
                                    };
                                    $eventIcon = match($activity->event) {
                                        'created', 'create' => 'ri-add-line',
                                        'updated', 'update' => 'ri-edit-line',
                                        'deleted', 'delete' => 'ri-delete-bin-line',
                                        'restored' => 'ri-refresh-line',
                                        default => 'ri-information-line'
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <div class="text-muted small">{{ app_date_time_format($activity->created_at, 'date') }}</div>
                                        <div class="text-muted small">{{ app_date_time_format($activity->created_at, 'time') }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $eventClass }}">
                                            <i class="{{ $eventIcon }} me-1"></i>{{ ucfirst(str_replace('_', ' ', $activity->event)) }}
                                        </span>
                                    </td>
                                    <td class="small text-muted">{{ $activity->description }}</td>
                                    <td>
                                        @if($activity->causer)
                                            <span class="text-muted">{{ $activity->causer->full_name }}</span>
                                        @else
                                            <span class="text-muted">System</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if(count($activities) >= 50)
                    <div class="text-center mt-3">
                        <a href="{{ route('app.logs.activity-logs.index', ['subject_type' => 'Modules\\Platform\\Models\\Domain', 'subject_id' => $domain->id]) }}" class="btn btn-sm btn-outline-primary">View All Activities</a>
                    </div>
                @endif
            @else
                <div class="text-center py-5 text-muted">
                    <i class="ri-pulse-line fs-1 mb-3 d-block opacity-50"></i>
                    <p class="mb-0">No activity logs found for this domain.</p>
                </div>
            @endif
        </x-slot:activity>
    </x-tabs>

    <x-drawer id="domain-drawer" />
</x-app-layout>
