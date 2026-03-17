<x-app-layout :title="$page_title">
    @php
        $isActive = $agency->status === 'active';
        $isTrashed = !empty($agency->deleted_at);

        // Status configuration
        $statusConfig = match($agency->status) {
            'active' => ['icon' => 'ri-checkbox-circle-fill', 'color' => 'success', 'bg' => 'success-subtle'],
            'inactive' => ['icon' => 'ri-close-circle-fill', 'color' => 'secondary', 'bg' => 'secondary-subtle'],
            default => ['icon' => 'ri-question-fill', 'color' => 'secondary', 'bg' => 'secondary-subtle'],
        };

        // Get work address for agencies (agencies use 'work' type)
        $primaryAddress = $agency->getAddressByType('work') ?? $agency->getPrimaryAddress();
    @endphp

    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Agencies', 'href' => route('platform.agencies.index')],
            ['label' => '#' . $agency->id, 'active' => true],
        ];

        $actions = [];

        if ($agency->branding_website) {
            $actions[] = [
                'type' => 'link',
                'label' => 'Visit Website',
                'icon' => 'ri-external-link-line',
                'variant' => 'btn-outline-primary',
                'href' => $agency->branding_website,
                'target' => '_blank'
            ];
        }

        if (auth()->user()->can('edit_agencies')) {
             $actions[] = [
                'type' => 'link',
                'label' => 'Edit',
                'icon' => 'ri-pencil-line',
                'href' => route('platform.agencies.edit', $agency->id),
                'variant' => 'btn-outline-secondary'
            ];

            $moreActions = [];

            if (!$isTrashed) {
                 $moreActions[] = [
                    'label' => 'Delete Agency',
                    'icon' => 'ri-delete-bin-line',
                    'href' => route('platform.agencies.destroy', $agency->id),
                    'class' => 'text-danger confirmation-btn',
                     'attributes' => [
                        'data-title' => 'Trash Agency',
                        'data-method' => 'DELETE',
                        'data-message' => 'Are you sure you want to trash this agency?',
                        'data-confirmButtonText' => 'Trash',
                        'data-confirmButtonClass' => 'btn-danger',
                        'data-loaderButtonText' => 'Trashing...',
                    ]
                ];
            }

            if (!empty($moreActions)) {
                $actions[] = [
                    'type' => 'dropdown',
                    'label' => 'More',
                    'icon' => 'ri-more-fill',
                    'variant' => 'btn-outline-secondary',
                    'items' => $moreActions
                ];
            }
        }

        $actions[] = [
            'type' => 'link',
            'label' => 'Back',
            'icon' => 'ri-arrow-left-line',
            'href' => route('platform.agencies.index'),
            'variant' => 'btn-outline-secondary'
        ];
    @endphp

    <x-page-header :breadcrumbs="$breadcrumbs" :actions="$actions" title="{{ ucwords($agency->name) }}">
        <x-slot:custom_title>
            <div class="d-flex align-items-center gap-3">
                <span class="h3 mb-0">{{ ucwords($agency->name) }}</span>
                <span class="badge bg-{{ $statusConfig['bg'] }} text-{{ $statusConfig['color'] }} fs-6">
                    <i class="{{ $statusConfig['icon'] }} me-1"></i>{{ ucfirst($agency->status) }}
                </span>
            </div>
        </x-slot:custom_title>
        <x-slot:description>
            Manage your Agency profile, settings, and associated resources.
        </x-slot:description>
    </x-page-header>

    {{-- Trashed Warning Banner --}}
    @if($isTrashed)
        <div class="alert alert-warning d-flex align-items-center mb-4">
            <div class="d-flex align-items-center justify-content-center bg-warning bg-opacity-25 me-3" style="width: 48px; height: 48px; border-radius: 50%;">
                <i class="ri-delete-bin-line fs-4 text-warning"></i>
            </div>
            <div class="flex-grow-1">
                <div class="fw-semibold">This agency is in trash</div>
                <div class="small text-muted">Trashed on {{ $agency->deleted_at->format('M d, Y \a\t h:i A') }}</div>
            </div>
            @can('restore_agencies')
                <a class="btn btn-warning confirmation-btn"
                    data-title="Restore Agency"
                    data-method="PATCH"
                    data-message="Are you sure you want to restore this agency?"
                    data-confirmButtonText="Restore"
                    data-loaderButtonText="Restoring..."
                    href="{{ route('platform.agencies.restore', $agency->id) }}">
                    <i class="ri-refresh-line me-1"></i>Restore
                </a>
            @endcan
        </div>
    @endif

    <style>
        .agency-command-center .ag-panel {
            border-color: rgba(var(--bs-secondary-rgb), 0.18);
        }

        .ag-hero {
            background: radial-gradient(circle at 0 0, rgba(var(--bs-primary-rgb), 0.1), transparent 42%),
                radial-gradient(circle at 100% 0, rgba(var(--bs-info-rgb), 0.08), transparent 48%);
        }

        .ag-health-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            padding: 0.3rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid rgba(var(--bs-secondary-rgb), 0.2);
        }

        .ag-metric-box {
            border: 1px solid rgba(var(--bs-secondary-rgb), 0.18);
            border-radius: 0.65rem;
            padding: 0.75rem;
            background-color: rgba(var(--bs-light-rgb), 0.65);
        }

        .ag-metric-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--bs-secondary-color);
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .ag-metric-value {
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .ag-action-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem;
        }

        .ag-action-grid .btn {
            justify-content: center;
        }

        .ag-ops-divider {
            border-top: 1px dashed rgba(var(--bs-secondary-rgb), 0.3);
            margin-top: 0.25rem;
            padding-top: 0.9rem;
        }

        .ag-ops-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .ag-ops-meter {
            width: 100%;
            height: 6px;
        }
    </style>

    @php
        $websitesCount = isset($agency_websites) ? count($agency_websites) : ($agency->websites?->count() ?? 0);
        $serversCount = $agency->servers?->count() ?? 0;
        $dnsProvidersCount = $agency->dnsProviders?->count() ?? 0;
        $cdnProvidersCount = $agency->cdnProviders?->count() ?? 0;
        $providersCount = $dnsProvidersCount + $cdnProvidersCount;

        $planConfig = config('astero.agency_plans.' . $agency->plan);
        $planLabel = $planConfig['label'] ?? ucfirst($agency->plan ?? 'None');
        $planLimit = isset($planConfig['websites']) ? (int) $planConfig['websites'] : null;
        $planUsagePercent = $planLimit && $planLimit > 0 ? (int) round(min(100, ($websitesCount / $planLimit) * 100)) : null;
        $planUsageColor = $planUsagePercent !== null && $planUsagePercent > 85 ? 'warning' : 'info';

        $brandingHost = $agency->branding_website
            ? (parse_url($agency->branding_website, PHP_URL_HOST) ?: $agency->branding_website)
            : null;

        $statusChipClass = 'bg-' . $statusConfig['bg'] . ' text-' . $statusConfig['color'];
        $primaryServer = $agency->servers?->firstWhere('pivot.is_primary', true);
        $primaryDnsProvider = $agency->dnsProviders?->firstWhere('pivot.is_primary', true);
        $primaryCdnProvider = $agency->cdnProviders?->firstWhere('pivot.is_primary', true);
        $defaultTab = 'general';
    @endphp

    <div class="agency-command-center mb-4">
        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card ag-panel ag-hero h-100 border">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <div class="small text-muted text-uppercase fw-semibold mb-2">Command Center</div>
                                <div class="h4 mb-1">{{ ucwords($agency->name) }}</div>
                                @if($brandingHost)
                                    <a href="{{ $agency->branding_website }}" target="_blank" class="text-decoration-none fw-semibold">
                                        {{ $brandingHost }}
                                    </a>
                                @else
                                    <span class="text-muted">No branded website configured</span>
                                @endif
                            </div>
                            <div class="text-end">
                                <div class="small text-muted">Agency ID</div>
                                <div class="fw-semibold font-monospace">{{ $agency->uid }}</div>
                                <div class="small text-muted mt-1">
                                    Updated: {{ $agency->updated_at?->diffForHumans() ?? '--' }}
                                </div>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-sm-6 col-lg-3">
                                <div class="ag-metric-box h-100">
                                    <div class="ag-metric-label">Plan</div>
                                    <div class="ag-metric-value">{{ $planLabel }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="ag-metric-box h-100">
                                    <div class="ag-metric-label">Type</div>
                                    <div class="ag-metric-value">{{ ucfirst($agency->type ?? '--') }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="ag-metric-box h-100">
                                    <div class="ag-metric-label">Websites</div>
                                    <div class="ag-metric-value">{{ $websitesCount }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="ag-metric-box h-100">
                                    <div class="ag-metric-label">Providers</div>
                                    <div class="ag-metric-value">{{ $providersCount }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="ag-health-chip {{ $statusChipClass }}">
                                <i class="{{ $statusConfig['icon'] }}"></i>
                                {{ ucfirst($agency->status) }}
                            </span>
                            <span class="ag-health-chip {{ $agency->isWhitelabel() ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">
                                <i class="{{ $agency->isWhitelabel() ? 'ri-paint-fill' : 'ri-paint-line' }}"></i>
                                Branding: {{ $agency->isWhitelabel() ? 'Whitelabel Ready' : 'Plan Restricted' }}
                            </span>
                            <span class="ag-health-chip {{ $agency->agencyWebsite ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                <i class="ri-global-line"></i>
                                Agency Site: {{ $agency->agencyWebsite ? 'Connected' : 'Not Linked' }}
                            </span>
                            <span class="ag-health-chip {{ $agency->secret_key ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">
                                <i class="{{ $agency->secret_key ? 'ri-lock-fill' : 'ri-lock-unlock-line' }}"></i>
                                Secret Key: {{ $agency->secret_key ? 'Configured' : 'Missing' }}
                            </span>
                            <span class="ag-health-chip {{ $agency->webhook_url ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">
                                <i class="{{ $agency->webhook_url ? 'ri-webhook-fill' : 'ri-webhook-line' }}"></i>
                                Webhook: {{ $agency->webhook_url ? 'Configured' : 'Missing' }}
                            </span>
                        </div>

                        <div class="row g-3 small">
                            <div class="col-md-6">
                                <div class="text-muted text-uppercase fw-semibold small mb-2">Ownership</div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Owner</span>
                                    <span class="fw-semibold">
                                        @if($agency->owner)
                                            @if(Route::has('app.users.show'))
                                                <a href="{{ route('app.users.show', $agency->owner->id) }}" target="_blank" class="text-decoration-none">
                                                    {{ $agency->owner->name }}
                                                </a>
                                            @else
                                                {{ $agency->owner->name }}
                                            @endif
                                        @else
                                            Not assigned
                                        @endif
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Email</span>
                                    <span class="fw-semibold">{{ $agency->email ?? '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Phone</span>
                                    <span class="fw-semibold">
                                        @if($primaryAddress?->phone)
                                            {{ $primaryAddress->phone_code ? '+' . $primaryAddress->phone_code : '' }} {{ $primaryAddress->phone }}
                                        @else
                                            --
                                        @endif
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Created</span>
                                    <span class="fw-semibold">{{ $agency->created_at?->format('M d, Y') ?? '--' }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted text-uppercase fw-semibold small mb-2">Infrastructure</div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Primary Server</span>
                                    <span class="fw-semibold">
                                        @if($primaryServer)
                                            <a href="{{ route('platform.servers.show', $primaryServer->id) }}" target="_blank" class="text-decoration-none">
                                                {{ $primaryServer->name }}
                                            </a>
                                        @else
                                            --
                                        @endif
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Primary DNS</span>
                                    <span class="fw-semibold">{{ $primaryDnsProvider?->name ?? '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Primary CDN</span>
                                    <span class="fw-semibold">{{ $primaryCdnProvider?->name ?? '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Agency Platform</span>
                                    <span class="fw-semibold">
                                        @if($agency->agencyWebsite)
                                            <a href="{{ route('platform.websites.show', $agency->agencyWebsite->id) }}" target="_blank" class="text-decoration-none">
                                                {{ $agency->agencyWebsite->name }}
                                            </a>
                                        @else
                                            Not linked
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card ag-panel h-100 border">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-flashlight-line me-1"></i> Operations
                        </h5>
                    </div>
                    <div class="card-body d-flex flex-column gap-3">
                        <div class="small text-muted">
                            High-impact actions for profile management, infrastructure links, and agency lifecycle.
                        </div>

                        @if(!$isTrashed)
                            <div class="ag-action-grid">
                                @if($agency->branding_website)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ $agency->branding_website }}" target="_blank">
                                        <i class="ri-external-link-line me-1"></i>Website
                                    </a>
                                @endif

                                @if($agency->agencyWebsite)
                                    <a class="btn btn-sm btn-outline-info" href="{{ route('platform.websites.show', $agency->agencyWebsite->id) }}" target="_blank">
                                        <i class="ri-global-line me-1"></i>Agency Site
                                    </a>
                                @endif

                                @can('edit_agencies')
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('platform.agencies.edit', $agency->id) }}">
                                        <i class="ri-pencil-line me-1"></i>Edit
                                    </a>

                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('platform.agencies.show', $agency->id) }}?section=servers">
                                        <i class="ri-server-line me-1"></i>Servers
                                    </a>

                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('platform.agencies.show', $agency->id) }}?section=providers">
                                        <i class="ri-cloud-line me-1"></i>Providers
                                    </a>
                                @endcan

                                @can('delete_agencies')
                                    <a class="btn btn-sm btn-outline-danger confirmation-btn"
                                        data-title="Move to Trash"
                                        data-method="DELETE"
                                        data-message="Move this agency to trash? You can restore it later."
                                        data-confirmButtonText="Move to Trash"
                                        data-loaderButtonText="Moving..."
                                        href="{{ route('platform.agencies.destroy', $agency->id) }}">
                                        <i class="ri-delete-bin-line me-1"></i>Trash
                                    </a>
                                @endcan
                            </div>
                        @else
                            <div class="alert alert-warning mb-0 small">
                                <i class="ri-alert-line me-1"></i>
                                This agency is in trash. Use the restore action from the warning banner.
                            </div>
                        @endif

                        <div class="ag-ops-divider">
                            <div class="small text-muted text-uppercase fw-semibold mb-2">Capacity</div>
                            <div class="ag-ops-row mb-2">
                                <span class="small text-muted">Websites</span>
                                <span class="badge {{ $planUsagePercent !== null ? 'bg-' . $planUsageColor . '-subtle text-' . $planUsageColor : 'bg-secondary-subtle text-secondary' }}">
                                    @if($planLimit && $planLimit > 0)
                                        {{ $websitesCount }}/{{ $planLimit }} ({{ $planUsagePercent }}%)
                                    @else
                                        {{ $websitesCount }} total
                                    @endif
                                </span>
                            </div>
                            @if($planUsagePercent !== null)
                                <div class="progress ag-ops-meter mb-3">
                                    <div class="progress-bar bg-{{ $planUsageColor }}" style="width: {{ $planUsagePercent }}%;"></div>
                                </div>
                            @endif

                            <div class="ag-ops-row mb-2">
                                <span class="small text-muted">Servers</span>
                                <span class="badge bg-info-subtle text-info">{{ $serversCount }}</span>
                            </div>
                            <div class="ag-ops-row">
                                <span class="small text-muted">CDN/DNS Providers</span>
                                <span class="badge bg-primary-subtle text-primary">{{ $providersCount }}</span>
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
            ['name' => 'websites', 'label' => 'Websites', 'icon' => 'ri-global-line', 'count' => $websitesCount],
            ['name' => 'servers', 'label' => 'Servers', 'icon' => 'ri-server-line', 'count' => $serversCount],
            ['name' => 'providers', 'label' => 'CDN/DNS', 'icon' => 'ri-cloud-line', 'count' => $providersCount],
            ['name' => 'notes', 'label' => 'Notes', 'icon' => 'ri-sticky-note-line'],
            ['name' => 'metadata', 'label' => 'Metadata', 'icon' => 'ri-code-s-slash-line'],
            ['name' => 'activity', 'label' => 'Activity', 'icon' => 'ri-pulse-line'],
        ];
    @endphp

    <x-tabs param="section" :active="$activeTab" :tabs="$tabs" class="border shadow-none">
        <x-slot:general>
            <div class="row">
                {{-- Contact & Address Card --}}
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="ri-contacts-line me-1"></i> Contact & Address
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Email</span>
                                    <span class="fw-semibold">
                                        @if($agency->email)
                                            <a href="mailto:{{ $agency->email }}" class="text-decoration-none">{{ $agency->email }}</a>
                                        @else
                                            --
                                        @endif
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Phone</span>
                                    <span class="fw-semibold">
                                        @if($primaryAddress?->phone)
                                            {{ $primaryAddress->phone_code ? '+' . $primaryAddress->phone_code : '' }} {{ $primaryAddress->phone }}
                                        @else
                                            --
                                        @endif
                                    </span>
                                </div>
                                @if($primaryAddress)
                                    <hr class="my-2">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Street</span>
                                        <span class="fw-semibold">{{ $primaryAddress->address1 ?? '--' }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">City</span>
                                        <span class="fw-semibold">{{ $primaryAddress->city ?? '--' }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">State</span>
                                        <span class="fw-semibold">{{ $primaryAddress->state ?? '--' }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Country</span>
                                        <span class="fw-semibold">{{ $primaryAddress->country ?? '--' }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">ZIP Code</span>
                                        <span class="fw-semibold font-monospace">{{ $primaryAddress->zip ?? '--' }}</span>
                                    </div>
                                @else
                                    <hr class="my-2">
                                    <div class="text-center text-muted py-3">
                                        <i class="ri-map-pin-line fs-4 mb-2 d-block opacity-50"></i>
                                        No address on file
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Branding Card --}}
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="ri-palette-line me-1"></i> Branding
                            </h5>
                        </div>
                        <div class="card-body">
                            @php
                                $hasBranding = $agency->branding_logo || $agency->branding_icon || $agency->branding_name || $agency->branding_website;
                            @endphp
                            @if(!$agency->isWhitelabel() && $hasBranding)
                                <div class="alert alert-warning py-2 mb-3 small d-flex align-items-center">
                                    <i class="ri-alert-line me-2"></i>
                                    <span>Branding active but requires <strong>Reseller</strong> plan.</span>
                                </div>
                            @endif
                            @if($agency->branding_name)
                                <div class="mb-3">
                                    <span class="text-muted small">Brand Name:</span>
                                    <span class="fw-semibold ms-1">{{ $agency->branding_name }}</span>
                                </div>
                            @endif
                            <div class="d-flex align-items-center gap-3 mb-3">
                                @if($agency->branding_logo)
                                    <img src="{{ $agency->branding_logo }}" alt="Logo" class="rounded shadow-sm" style="max-height: 60px; max-width: 150px; object-fit: contain;">
                                @else
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                        <i class="ri-image-line fs-4 text-muted"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                @if($agency->branding_icon)
                                    <img src="{{ $agency->branding_icon }}" alt="Icon" class="rounded shadow-sm" style="width: 32px; height: 32px; object-fit: contain;" title="Icon">
                                @endif
                            </div>
                            @if($agency->branding_website)
                                <div class="mt-3">
                                    <span class="text-muted small">Website:</span>
                                    <a href="{{ $agency->branding_website }}" target="_blank" class="ms-1 text-decoration-none">{{ $agency->branding_website }} <i class="ri-external-link-line small"></i></a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Secret Key Card --}}
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="ri-key-2-line me-1"></i> Secret Key
                            </h5>
                        </div>
                        <div class="card-body" x-data="{ show: false, copied: false }">
                            @if($agency->secret_key)
                                <div class="input-group mb-2">
                                    <input
                                        type="text"
                                        class="form-control form-control-sm font-monospace bg-light"
                                        :value="show ? '{{ addslashes($agency->plain_secret_key) }}' : '••••••••••••••••••••••••••••••••'"
                                        readonly
                                    />
                                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="show = !show" title="Toggle visibility">
                                        <i :class="show ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        @click="navigator.clipboard.writeText('{{ addslashes($agency->plain_secret_key) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                        :title="copied ? 'Copied!' : 'Copy to clipboard'"
                                    >
                                        <i :class="copied ? 'ri-check-line text-success' : 'ri-file-copy-line'"></i>
                                    </button>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="badge bg-success-subtle text-success"><i class="ri-lock-fill me-1"></i>Active</span>
                                    @can('edit_agencies')
                                        <a
                                            href="{{ route('platform.agencies.regenerate-secret-key', $agency->id) }}"
                                            class="btn btn-sm btn-outline-warning confirmation-btn"
                                            data-title="Regenerate Secret Key"
                                            data-method="POST"
                                            data-message="This will generate a new secret key and invalidate the current one. The agency instance will need the new key to authenticate."
                                            data-confirmButtonText="Regenerate"
                                            data-loaderButtonText="Regenerating..."
                                        >
                                            <i class="ri-refresh-line me-1"></i> Regenerate
                                        </a>
                                    @endcan
                                </div>
                            @else
                                <div class="text-center py-3">
                                    <i class="ri-lock-unlock-line fs-3 text-warning mb-2 d-block"></i>
                                    <p class="text-muted small mb-2">No secret key configured.</p>
                                    @can('edit_agencies')
                                        <a
                                            href="{{ route('platform.agencies.regenerate-secret-key', $agency->id) }}"
                                            class="btn btn-sm btn-outline-primary confirmation-btn"
                                            data-title="Generate Secret Key"
                                            data-method="POST"
                                            data-message="Generate a new secret key for this agency? The key will be used for agency-to-platform API authentication."
                                            data-confirmButtonText="Generate"
                                            data-loaderButtonText="Generating..."
                                        >
                                            <i class="ri-key-2-line me-1"></i> Generate Key
                                        </a>
                                    @endcan
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Webhook URL Card --}}
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="ri-webhook-line me-1"></i> Webhook URL
                            </h5>
                        </div>
                        <div class="card-body" x-data="{ copied: false }">
                            @if($agency->webhook_url)
                                <div class="input-group mb-2">
                                    <input
                                        type="text"
                                        class="form-control form-control-sm font-monospace bg-light"
                                        value="{{ $agency->webhook_url }}"
                                        readonly
                                    />
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        @click="navigator.clipboard.writeText('{{ addslashes($agency->webhook_url) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                        :title="copied ? 'Copied!' : 'Copy to clipboard'"
                                    >
                                        <i :class="copied ? 'ri-check-line text-success' : 'ri-file-copy-line'"></i>
                                    </button>
                                </div>
                                <span class="badge bg-success-subtle text-success"><i class="ri-check-line me-1"></i>Configured</span>
                                <small class="text-muted d-block mt-2">Provisioning status updates are sent to this URL.</small>
                            @else
                                <div class="text-center py-3">
                                    <i class="ri-webhook-line fs-3 text-warning mb-2 d-block"></i>
                                    <p class="text-muted small mb-0">No webhook URL configured. Set one in the edit form to receive provisioning status updates.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Timestamps Card --}}
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header mb-3">
                            <h6 class="card-title mb-0">Timestamps</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Created</span>
                                    <span class="fw-semibold">{{ $agency->created_at ? $agency->created_at->format('M d, Y') : '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Last Updated</span>
                                    <span class="fw-semibold">{{ $agency->updated_at ? $agency->updated_at->diffForHumans() : '--' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-slot:general>

        <x-slot:websites>
            <h6 class="mb-4">Websites</h6>
            @if(isset($agency_websites) && count($agency_websites) > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Website</th>
                                <th>Domain</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($agency_websites as $website)
                                <tr>
                                    <td>
                                        <a href="{{ route('platform.websites.show', $website->id) }}" class="fw-semibold text-decoration-none">
                                            {{ $website->name }}
                                        </a>
                                    </td>
                                    <td class="small text-muted font-monospace">{{ $website->domain }}</td>
                                    <td>{!! $website->status_badge !!}</td>
                                    <td class="small text-muted">{{ $website->created_at?->format('M d, Y') }}</td>
                                    <td>
                                        <a href="{{ route('platform.websites.show', $website->id) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="ri-global-line fs-1 mb-3 d-block opacity-50"></i>
                    <p class="mb-0">No websites found for this agency.</p>
                </div>
            @endif
        </x-slot:websites>

        <x-slot:servers>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="mb-0">Associated Servers</h6>
                @can('edit_agencies')
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#manageServersModal">
                        <i class="ri-settings-3-line me-1"></i>Manage Servers
                    </button>
                @endcan
            </div>
            @if($agency->servers->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Server Name</th>
                                <th>IP Address</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Primary</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($agency->servers as $server)
                                <tr>
                                    <td>
                                        <a href="{{ route('platform.servers.show', $server->id) }}" class="text-decoration-none fw-semibold">
                                            {{ $server->name }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="font-monospace small">{{ $server->ip ?? '--' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-{{ $server->type_color }}">
                                            {{ $server->type_label }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-{{ $server->status === 'active' ? 'success' : 'secondary' }}">
                                            {{ ucfirst($server->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($server->pivot->is_primary)
                                            <span class="badge text-bg-primary">
                                                <i class="ri-star-fill me-1"></i>Primary
                                            </span>
                                        @else
                                            @can('edit_agencies')
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setPrimaryServer({{ $server->id }})">
                                                    <i class="ri-star-line me-1"></i>Set Primary
                                                </button>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endcan
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('platform.servers.show', $server->id) }}" class="btn btn-sm btn-outline-primary me-1">
                                            <i class="ri-eye-line me-1"></i>View
                                        </a>
                                        @can('edit_agencies')
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeServer({{ $server->id }}, '{{ $server->name }}')">
                                                <i class="ri-close-line"></i>
                                            </button>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="ri-server-line fs-1 mb-3 d-block opacity-50"></i>
                    <p class="mb-0">No servers associated with this agency.</p>
                    @can('edit_agencies')
                        <button type="button" class="btn btn-sm btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#manageServersModal">
                            <i class="ri-add-line me-1"></i>Add Servers
                        </button>
                    @else
                        <p class="small">Servers can be associated from the Server details page.</p>
                    @endcan
                </div>
            @endif
        </x-slot:servers>

        <x-slot:providers>
            {{-- DNS Providers Section --}}
            <div class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="mb-0"><i class="ri-server-line me-2"></i>DNS Providers</h6>
                    @can('edit_agencies')
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#manageDnsProvidersModal">
                            <i class="ri-settings-3-line me-1"></i>Manage DNS Providers
                        </button>
                    @endcan
                </div>
                @if($agency->dnsProviders->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Provider Name</th>
                                    <th>Vendor</th>
                                    <th>Status</th>
                                    <th>Primary</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($agency->dnsProviders as $provider)
                                    <tr>
                                        <td>
                                            <a href="{{ route('platform.providers.show', $provider->id) }}" class="text-decoration-none fw-semibold">
                                                {{ $provider->name }}
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge text-bg-{{ $provider->vendor_color }}">{{ $provider->vendor_label }}</span>
                                        </td>
                                        <td>
                                            <span class="badge text-bg-{{ $provider->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($provider->status) }}</span>
                                        </td>
                                        <td>
                                            @if($provider->pivot->is_primary)
                                                <span class="badge text-bg-primary"><i class="ri-star-fill me-1"></i>Primary</span>
                                            @else
                                                @can('edit_agencies')
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setPrimaryDnsProvider({{ $provider->id }})">
                                                        <i class="ri-star-line me-1"></i>Set Primary
                                                    </button>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endcan
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('platform.providers.show', $provider->id) }}" class="btn btn-sm btn-outline-primary me-1">
                                                <i class="ri-eye-line me-1"></i>View
                                            </a>
                                            @can('edit_agencies')
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeProvider({{ $provider->id }}, '{{ $provider->name }}', 'DNS')">
                                                    <i class="ri-close-line"></i>
                                                </button>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 text-muted bg-light rounded">
                        <i class="ri-server-line fs-2 mb-2 d-block opacity-50"></i>
                        <p class="mb-0">No DNS providers associated with this agency.</p>
                        @can('edit_agencies')
                            <button type="button" class="btn btn-sm btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#manageDnsProvidersModal">
                                <i class="ri-add-line me-1"></i>Add DNS Providers
                            </button>
                        @endcan
                    </div>
                @endif
            </div>

            {{-- CDN Providers Section --}}
            <div>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="mb-0"><i class="ri-speed-line me-2"></i>CDN Providers</h6>
                    @can('edit_agencies')
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#manageCdnProvidersModal">
                            <i class="ri-settings-3-line me-1"></i>Manage CDN Providers
                        </button>
                    @endcan
                </div>
                @if($agency->cdnProviders->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Provider Name</th>
                                    <th>Vendor</th>
                                    <th>Status</th>
                                    <th>Primary</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($agency->cdnProviders as $provider)
                                    <tr>
                                        <td>
                                            <a href="{{ route('platform.providers.show', $provider->id) }}" class="text-decoration-none fw-semibold">
                                                {{ $provider->name }}
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge text-bg-{{ $provider->vendor_color }}">{{ $provider->vendor_label }}</span>
                                        </td>
                                        <td>
                                            <span class="badge text-bg-{{ $provider->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($provider->status) }}</span>
                                        </td>
                                        <td>
                                            @if($provider->pivot->is_primary)
                                                <span class="badge text-bg-primary"><i class="ri-star-fill me-1"></i>Primary</span>
                                            @else
                                                @can('edit_agencies')
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setPrimaryCdnProvider({{ $provider->id }})">
                                                        <i class="ri-star-line me-1"></i>Set Primary
                                                    </button>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endcan
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('platform.providers.show', $provider->id) }}" class="btn btn-sm btn-outline-primary me-1">
                                                <i class="ri-eye-line me-1"></i>View
                                            </a>
                                            @can('edit_agencies')
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeProvider({{ $provider->id }}, '{{ $provider->name }}', 'CDN')">
                                                    <i class="ri-close-line"></i>
                                                </button>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 text-muted bg-light rounded">
                        <i class="ri-speed-line fs-2 mb-2 d-block opacity-50"></i>
                        <p class="mb-0">No CDN providers associated with this agency.</p>
                        @can('edit_agencies')
                            <button type="button" class="btn btn-sm btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#manageCdnProvidersModal">
                                <i class="ri-add-line me-1"></i>Add CDN Providers
                            </button>
                        @endcan
                    </div>
                @endif
            </div>
        </x-slot:providers>

        <x-slot:notes>
            <x-app.notes :model="$agency" />
        </x-slot:notes>

        <x-slot:metadata>
            @php
                $metadata = $agency->metadata ?? [];
            @endphp
            @if(!empty($metadata))
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 30%;">Key</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($metadata as $key => $value)
                                <tr>
                                    <td class="fw-medium text-muted">{{ $key }}</td>
                                    <td>
                                        @if(is_array($value) || is_object($value))
                                            <pre class="mb-0 bg-light p-2 rounded small" style="max-height: 200px; overflow-y: auto;"><code>{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                        @elseif(is_bool($value))
                                            <span class="badge text-bg-{{ $value ? 'success' : 'secondary' }}">{{ $value ? 'Yes' : 'No' }}</span>
                                        @elseif(is_null($value))
                                            <span class="text-muted fst-italic">null</span>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <details>
                        <summary class="text-muted small cursor-pointer">View Raw JSON</summary>
                        <pre class="mt-2 bg-light p-3 rounded small" style="max-height: 400px; overflow-y: auto;"><code>{{ json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                    </details>
                </div>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="ri-database-2-line fs-1 mb-3 d-block opacity-50"></i>
                    <p class="mb-0">No metadata stored for this agency.</p>
                </div>
            @endif
        </x-slot:metadata>

        <x-slot:activity>
            @if(isset($agency_activities) && count($agency_activities) > 0)
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
                            @foreach($agency_activities as $activity)
                                @php
                                    $eventClass = match($activity->event) {
                                        'created', 'create' => 'success',
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
                                        <span class="badge bg-{{ $eventClass }}-subtle text-{{ $eventClass }}">
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
                @if(count($agency_activities) >= 50)
                    <div class="text-center mt-3">
                        <a href="{{ route('app.logs.activity-logs.index', ['subject_type' => 'Modules\\Platform\\Models\\Agency', 'subject_id' => $agency->id]) }}" class="btn btn-sm btn-outline-primary">View All Activities</a>
                    </div>
                @endif
            @else
                <div class="text-center py-5 text-muted">
                    <i class="ri-pulse-line fs-1 mb-3 d-block opacity-50"></i>
                    <p class="mb-0">No activity logs found for this agency.</p>
                </div>
            @endif
        </x-slot:activity>
    </x-tabs>

    <x-drawer id="agency-drawer" />

    @push('scripts')
        <script data-up-execute>
            (() => {
                const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

                const bindFormOnce = (formId, handler) => {
                    const form = document.getElementById(formId);
                    if (!form || form.dataset.bound === '1') return;
                    form.dataset.bound = '1';
                    form.addEventListener('submit', handler);
                };

                window.removeServer = async (serverId, serverName) => {
                    if (!confirm(`Are you sure you want to remove "${serverName}" from this agency?`)) return;

                    try {
                        const response = await fetch(
                            `{{ route('platform.agencies.servers.detach', ['agency' => $agency->id, 'server' => '__SERVER_ID__']) }}`.replace('__SERVER_ID__', serverId),
                            {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': csrf(),
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                },
                            }
                        );

                        const data = await response.json();
                        if (data.success) {
                            window.location.reload();
                            return;
                        }
                        alert(data.message || 'Failed to remove server');
                    } catch (error) {
                        console.error('Error removing server:', error);
                        alert('An error occurred while removing the server');
                    }
                };

                window.setPrimaryServer = async (serverId) => {
                    if (!confirm('Set this server as primary for this agency?')) return;

                    try {
                        const response = await fetch(
                            `{{ route('platform.agencies.servers.set-primary', ['agency' => $agency->id, 'server' => '__SERVER_ID__']) }}`.replace('__SERVER_ID__', serverId),
                            {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': csrf(),
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                },
                            }
                        );

                        const data = await response.json();
                        if (data.success) {
                            window.location.reload();
                            return;
                        }
                        alert(data.message || 'Failed to set primary server');
                    } catch (error) {
                        console.error('Error setting primary server:', error);
                        alert('An error occurred while setting the primary server');
                    }
                };

                window.removeProvider = async (providerId, providerName, providerType) => {
                    if (!confirm(`Are you sure you want to remove "${providerName}" (${providerType}) from this agency?`)) return;

                    try {
                        const response = await fetch(
                            `{{ route('platform.agencies.providers.detach', ['agency' => $agency->id, 'provider' => '__PROVIDER_ID__']) }}`.replace('__PROVIDER_ID__', providerId),
                            {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': csrf(),
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                },
                            }
                        );

                        const data = await response.json();
                        if (data.success) {
                            window.location.reload();
                            return;
                        }
                        alert(data.message || 'Failed to remove provider');
                    } catch (error) {
                        console.error('Error removing provider:', error);
                        alert('An error occurred while removing the provider');
                    }
                };

                window.setPrimaryDnsProvider = async (providerId) => {
                    if (!confirm('Set this DNS provider as primary for this agency?')) return;

                    try {
                        const response = await fetch(
                            `{{ route('platform.agencies.dns-providers.set-primary', ['agency' => $agency->id, 'provider' => '__PROVIDER_ID__']) }}`.replace('__PROVIDER_ID__', providerId),
                            {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': csrf(),
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                },
                            }
                        );

                        const data = await response.json();
                        if (data.success) {
                            window.location.reload();
                            return;
                        }
                        alert(data.message || 'Failed to set primary DNS provider');
                    } catch (error) {
                        console.error('Error setting primary DNS provider:', error);
                        alert('An error occurred while setting the primary DNS provider');
                    }
                };

                window.setPrimaryCdnProvider = async (providerId) => {
                    if (!confirm('Set this CDN provider as primary for this agency?')) return;

                    try {
                        const response = await fetch(
                            `{{ route('platform.agencies.cdn-providers.set-primary', ['agency' => $agency->id, 'provider' => '__PROVIDER_ID__']) }}`.replace('__PROVIDER_ID__', providerId),
                            {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': csrf(),
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                },
                            }
                        );

                        const data = await response.json();
                        if (data.success) {
                            window.location.reload();
                            return;
                        }
                        alert(data.message || 'Failed to set primary CDN provider');
                    } catch (error) {
                        console.error('Error setting primary CDN provider:', error);
                        alert('An error occurred while setting the primary CDN provider');
                    }
                };

                bindFormOnce('manageServersForm', async (e) => {
                    e.preventDefault();

                    const selectedServers = Array.from(document.getElementById('server_ids')?.selectedOptions ?? []).map((opt) => opt.value);
                    const primaryServerId = document.getElementById('primary_server_id')?.value || '';

                    try {
                        const response = await fetch(`{{ route('platform.agencies.servers.attach', ['agency' => $agency->id]) }}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf(),
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                server_ids: selectedServers,
                                primary_server_id: primaryServerId,
                            }),
                        });

                        const data = await response.json();
                        if (data.success) {
                            window.location.reload();
                            return;
                        }
                        alert(data.message || 'Failed to update servers');
                    } catch (error) {
                        console.error('Error updating servers:', error);
                        alert('An error occurred while updating servers');
                    }
                });

                bindFormOnce('manageDnsProvidersForm', async (e) => {
                    e.preventDefault();

                    const selectedProviders = Array.from(document.getElementById('dns_provider_ids')?.selectedOptions ?? []).map((opt) => opt.value);
                    const primaryProviderId = document.getElementById('primary_dns_provider_id')?.value || '';

                    try {
                        const response = await fetch(`{{ route('platform.agencies.dns-providers.attach', ['agency' => $agency->id]) }}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf(),
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                provider_ids: selectedProviders,
                                primary_provider_id: primaryProviderId,
                            }),
                        });

                        const data = await response.json();
                        if (data.success) {
                            window.location.reload();
                            return;
                        }
                        alert(data.message || 'Failed to update DNS providers');
                    } catch (error) {
                        console.error('Error updating DNS providers:', error);
                        alert('An error occurred while updating DNS providers');
                    }
                });

                bindFormOnce('manageCdnProvidersForm', async (e) => {
                    e.preventDefault();

                    const selectedProviders = Array.from(document.getElementById('cdn_provider_ids')?.selectedOptions ?? []).map((opt) => opt.value);
                    const primaryProviderId = document.getElementById('primary_cdn_provider_id')?.value || '';

                    try {
                        const response = await fetch(`{{ route('platform.agencies.cdn-providers.attach', ['agency' => $agency->id]) }}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf(),
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                provider_ids: selectedProviders,
                                primary_provider_id: primaryProviderId,
                            }),
                        });

                        const data = await response.json();
                        if (data.success) {
                            window.location.reload();
                            return;
                        }
                        alert(data.message || 'Failed to update CDN providers');
                    } catch (error) {
                        console.error('Error updating CDN providers:', error);
                        alert('An error occurred while updating CDN providers');
                    }
                });
            })();
        </script>
    @endpush

    {{-- Manage Servers Modal --}}
    @can('edit_agencies')
    <div class="modal fade" id="manageServersModal" tabindex="-1" aria-labelledby="manageServersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="manageServersModalLabel">Manage Agency Servers</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="manageServersForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="server_ids" class="form-label">Select Servers</label>
                            <select class="form-select" id="server_ids" name="server_ids[]" multiple size="8">
                                @foreach(\Modules\Platform\Models\Server::orderBy('name')->get() as $server)
                                    <option value="{{ $server->id }}"
                                        {{ $agency->servers->contains($server->id) ? 'selected' : '' }}>
                                        {{ $server->name }} ({{ $server->ip }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple servers</small>
                        </div>

                        <div class="mb-3">
                            <label for="primary_server_id" class="form-label">Primary Server</label>
                            <select class="form-select" id="primary_server_id" name="primary_server_id">
                                <option value="">None</option>
                                @foreach(\Modules\Platform\Models\Server::orderBy('name')->get() as $server)
                                    <option value="{{ $server->id }}"
                                        {{ $agency->servers->contains(function($s) use ($server) { return $s->id === $server->id && $s->pivot->is_primary; }) ? 'selected' : '' }}>
                                        {{ $server->name }} ({{ $server->ip }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Primary server must be selected in the servers list above</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-save-line me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Manage DNS Providers Modal --}}
    <div class="modal fade" id="manageDnsProvidersModal" tabindex="-1" aria-labelledby="manageDnsProvidersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="manageDnsProvidersModalLabel"><i class="ri-server-line me-2"></i>Manage DNS Providers</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="manageDnsProvidersForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="dns_provider_ids" class="form-label">Select DNS Providers</label>
                            <select class="form-select" id="dns_provider_ids" name="dns_provider_ids[]" multiple size="8">
                                @foreach(\Modules\Platform\Models\Provider::active()->dns()->orderBy('name')->get() as $provider)
                                    <option value="{{ $provider->id }}"
                                        {{ $agency->dnsProviders->contains($provider->id) ? 'selected' : '' }}>
                                        {{ $provider->name }} ({{ $provider->vendor_label }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple providers</small>
                        </div>

                        <div class="mb-3">
                            <label for="primary_dns_provider_id" class="form-label">Primary DNS Provider</label>
                            <select class="form-select" id="primary_dns_provider_id" name="primary_dns_provider_id">
                                <option value="">None</option>
                                @foreach(\Modules\Platform\Models\Provider::active()->dns()->orderBy('name')->get() as $provider)
                                    <option value="{{ $provider->id }}"
                                        {{ $agency->dnsProviders->contains(function($p) use ($provider) { return $p->id === $provider->id && $p->pivot->is_primary; }) ? 'selected' : '' }}>
                                        {{ $provider->name }} ({{ $provider->vendor_label }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Primary provider must be selected in the list above</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-save-line me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>



    {{-- Manage CDN Providers Modal --}}
    <div class="modal fade" id="manageCdnProvidersModal" tabindex="-1" aria-labelledby="manageCdnProvidersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="manageCdnProvidersModalLabel"><i class="ri-speed-line me-2"></i>Manage CDN Providers</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="manageCdnProvidersForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="cdn_provider_ids" class="form-label">Select CDN Providers</label>
                            <select class="form-select" id="cdn_provider_ids" name="cdn_provider_ids[]" multiple size="8">
                                @foreach(\Modules\Platform\Models\Provider::active()->cdn()->orderBy('name')->get() as $provider)
                                    <option value="{{ $provider->id }}"
                                        {{ $agency->cdnProviders->contains($provider->id) ? 'selected' : '' }}>
                                        {{ $provider->name }} ({{ $provider->vendor_label }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple providers</small>
                        </div>

                        <div class="mb-3">
                            <label for="primary_cdn_provider_id" class="form-label">Primary CDN Provider</label>
                            <select class="form-select" id="primary_cdn_provider_id" name="primary_cdn_provider_id">
                                <option value="">None</option>
                                @foreach(\Modules\Platform\Models\Provider::active()->cdn()->orderBy('name')->get() as $provider)
                                    <option value="{{ $provider->id }}"
                                        {{ $agency->cdnProviders->contains(function($p) use ($provider) { return $p->id === $provider->id && $p->pivot->is_primary; }) ? 'selected' : '' }}>
                                        {{ $provider->name }} ({{ $provider->vendor_label }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Primary provider must be selected in the list above</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-save-line me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @endcan
</x-app-layout>
