<x-app-layout :title="$page_title">
    @php
        $isActive = $server->status === 'active';
        $isTrashed = !empty($server->deleted_at);
        $creationMode = (string) ($server->getMetadata('creation_mode') ?? 'manual');
        $isProvisionModeServer = $creationMode === 'provision'
            || in_array((string) $server->provisioning_status, [
                \Modules\Platform\Models\Server::PROVISIONING_STATUS_PENDING,
                \Modules\Platform\Models\Server::PROVISIONING_STATUS_PROVISIONING,
                \Modules\Platform\Models\Server::PROVISIONING_STATUS_FAILED,
            ], true)
            || !empty($server->getMetadata('provisioning_steps'));

        // Status configuration
        $statusConfig = match($server->status) {
            'active' => ['icon' => 'ri-checkbox-circle-fill', 'color' => 'success', 'bg' => 'success-subtle'],
            'provisioning' => ['icon' => 'ri-loader-4-line', 'color' => 'info', 'bg' => 'info-subtle'],
            'failed' => ['icon' => 'ri-error-warning-fill', 'color' => 'danger', 'bg' => 'danger-subtle'],
            'inactive' => ['icon' => 'ri-close-circle-fill', 'color' => 'secondary', 'bg' => 'secondary-subtle'],
            'maintenance' => ['icon' => 'ri-tools-fill', 'color' => 'warning', 'bg' => 'warning-subtle'],
            default => ['icon' => 'ri-question-fill', 'color' => 'secondary', 'bg' => 'secondary-subtle'],
        };

        $fqdnLink = $server->fqdn
            ? (\Illuminate\Support\Str::startsWith($server->fqdn, ['http://', 'https://'])
                ? $server->fqdn
                : 'https://'.$server->fqdn)
            : null;
    @endphp

    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Servers', 'href' => route('platform.servers.index')],
            ['label' => '#' . $server->id, 'active' => true],
        ];

        $actions = [];

        if (auth()->user()->can('edit_servers')) {
            $actions[] = [
                'type' => 'link',
                'label' => 'Edit',
                'icon' => 'ri-pencil-line',
                'href' => route('platform.servers.edit', $server->id),
                'variant' => 'btn-outline-secondary'
            ];

            $moreActions = [];

            if ($server->fqdn) {
                 // Ensure we have a clean domain without protocol for constructing the HestiaCP URL
                $plainDomain = preg_replace('#^https?://#', '', $server->fqdn);
                $hestiaPort = $server->port ?: 8443;
                $manageUrl = 'https://' . $plainDomain . ':' . $hestiaPort;

                $moreActions[] = [
                    'label' => 'Manage',
                    'icon' => 'ri-external-link-line',
                    'href' => $manageUrl,
                    'target' => '_blank',
                ];
            }

            if ($isActive) {
                 $moreActions[] = [
                    'label' => 'Sync Server',
                    'icon' => 'ri-refresh-line',
                    'href' => route('platform.servers.sync-server', $server->id),
                    'class' => 'confirmation-btn',
                    'attributes' => [
                        'data-title' => 'Sync Server',
                        'data-method' => 'POST',
                        'data-message' => 'Fetch the current Astero version and server info from Hestia?',
                        'data-confirmButtonText' => 'Sync',
                        'data-loaderButtonText' => 'Syncing...',
                    ]
                ];
                $moreActions[] = [
                    'label' => 'Update Releases',
                    'icon' => 'ri-download-line',
                    'href' => route('platform.servers.update-releases', $server->id),
                    'class' => 'confirmation-btn',
                    'attributes' => [
                        'data-title' => 'Update Releases',
                        'data-method' => 'POST',
                        'data-message' => 'Download the latest release from central server to this server\'s local repository?',
                        'data-confirmButtonText' => 'Update',
                        'data-loaderButtonText' => 'Updating...',
                    ]
                ];

                // Update Scripts - requires SSH credentials
                if ($server->hasSshCredentials()) {
                    $moreActions[] = [
                        'label' => 'Update Scripts',
                        'icon' => 'ri-upload-cloud-line',
                        'href' => route('platform.servers.update-scripts', $server->id),
                        'class' => 'confirmation-btn',
                        'attributes' => [
                            'data-title' => 'Update Scripts',
                            'data-method' => 'POST',
                            'data-message' => 'Upload the latest Astero scripts and templates to this server via SSH?',
                            'data-confirmButtonText' => 'Update',
                            'data-loaderButtonText' => 'Updating...',
                        ]
                    ];
                }

                // Setup ACME - only when not configured and SSH available
                if (!$server->acme_configured && $server->hasSshCredentials()) {
                    $moreActions[] = [
                        'label' => 'Setup ACME (SSL)',
                        'icon' => 'ri-shield-check-line',
                        'href' => route('platform.servers.setup-acme', $server->id),
                        'class' => 'confirmation-btn',
                        'attributes' => [
                            'data-title' => 'Setup ACME (SSL)',
                            'data-method' => 'POST',
                            'data-message' => 'Install acme.sh and configure SSL certificate automation on this server? This takes about 30 seconds.',
                            'data-confirmButtonText' => 'Setup',
                            'data-loaderButtonText' => 'Setting up...',
                        ]
                    ];
                }
            }

            // Provision Server - for servers that can be provisioned
            if ($isProvisionModeServer && $server->canProvision() && $server->hasSshCredentials()) {
                $moreActions[] = [
                    'label' => 'Provision Server',
                    'icon' => 'ri-server-line',
                    'href' => route('platform.servers.provision', $server->id),
                    'class' => 'confirmation-btn text-primary',
                    'attributes' => [
                        'data-title' => 'Provision Server',
                        'data-method' => 'POST',
                        'data-message' => 'This will install HestiaCP and Astero scripts on this server. This process takes 15-30 minutes. Continue?',
                        'data-confirmButtonText' => 'Start Provisioning',
                        'data-loaderButtonText' => 'Starting...',
                    ]
                ];
            }

            if (!$isTrashed) {
                if (!empty($moreActions)) {
                    $moreActions[] = ['type' => 'divider'];
                }

                 $moreActions[] = [
                    'label' => 'Delete Server',
                    'icon' => 'ri-delete-bin-line',
                    'href' => route('platform.servers.destroy', $server->id),
                    'class' => 'text-danger confirmation-btn',
                     'attributes' => [
                        'data-title' => 'Trash Server',
                        'data-method' => 'DELETE',
                        'data-message' => 'Are you sure you want to trash this server?',
                        'data-confirmButtonText' => 'Trash',
                        'data-confirmButtonClass' => 'btn-danger', // Optional styling if supported
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
            'href' => route('platform.servers.index'),
            'variant' => 'btn-outline-secondary'
        ];
    @endphp

    <x-page-header :breadcrumbs="$breadcrumbs" :actions="$actions" title="{{ $server->name }}">
        <x-slot:custom_title>
            <div class="d-flex align-items-center gap-3">
                <span class="h3 mb-0">{{ $server->name }}</span>
                <span class="badge bg-{{ $statusConfig['bg'] }} text-{{ $statusConfig['color'] }} fs-6">
                    <i class="{{ $statusConfig['icon'] }} me-1"></i>{{ ucfirst($server->status) }}
                </span>
            </div>
        </x-slot:custom_title>
        <x-slot:description>
            Manage your server configuration, resources, and software versions.
        </x-slot:description>
    </x-page-header>

    {{-- Trashed Warning Banner --}}
    @if($isTrashed)
        <div class="alert alert-warning d-flex align-items-center mb-4">
            <div class="d-flex align-items-center justify-content-center bg-warning bg-opacity-25 me-3" style="width: 48px; height: 48px; border-radius: 50%;">
                <i class="ri-delete-bin-line fs-4 text-warning"></i>
            </div>
            <div class="flex-grow-1">
                <div class="fw-semibold">This server is in trash</div>
                <div class="small text-muted">Trashed on {{ $server->deleted_at->format('M d, Y \a\t h:i A') }}</div>
            </div>
            @can('restore_servers')
                <a class="btn btn-warning confirmation-btn"
                    data-title="Restore Server"
                    data-method="PATCH"
                    data-message="Are you sure you want to restore this server?"
                    data-confirmButtonText="Restore"
                    data-loaderButtonText="Restoring..."
                    href="{{ route('platform.servers.restore', $server->id) }}">
                    <i class="ri-refresh-line me-1"></i>Restore
                </a>
            @endcan
        </div>
    @endif

    <style>
        .server-command-center .sv-panel {
            border-color: rgba(var(--bs-secondary-rgb), 0.18);
        }

        .sv-hero {
            background: radial-gradient(circle at 0 0, rgba(var(--bs-primary-rgb), 0.09), transparent 42%),
                radial-gradient(circle at 100% 0, rgba(var(--bs-info-rgb), 0.1), transparent 48%);
        }

        .sv-health-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            padding: 0.3rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid rgba(var(--bs-secondary-rgb), 0.2);
        }

        .sv-metric-box {
            border: 1px solid rgba(var(--bs-secondary-rgb), 0.18);
            border-radius: 0.65rem;
            padding: 0.75rem;
            background-color: rgba(var(--bs-light-rgb), 0.65);
        }

        .sv-metric-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--bs-secondary-color);
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .sv-metric-value {
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .sv-action-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem;
        }

        .sv-action-grid .btn {
            justify-content: center;
        }

        .sv-ops-divider {
            border-top: 1px dashed rgba(var(--bs-secondary-rgb), 0.3);
            margin-top: 0.25rem;
            padding-top: 0.9rem;
        }

        .sv-ops-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .sv-capacity-meter {
            width: 100%;
            height: 6px;
        }
    </style>

    @php
        $serverProvider = $server->getProvider(\Modules\Platform\Models\Provider::TYPE_SERVER);
        $lastSyncedAt = $server->getMetadata('last_synced_at');
        $hasSshCredentials = $server->hasSshCredentials();

        $ramTotalMb = (float) ($server->server_ram ?? 0);
        $ramUsedMb = (float) ($server->getMetadata('server_ram_used') ?? 0);
        $ramPercent = $ramTotalMb > 0 ? (int) round(min(100, ($ramUsedMb / $ramTotalMb) * 100)) : 0;
        $ramColor = $ramPercent > 90 ? 'danger' : ($ramPercent > 75 ? 'warning' : 'success');
        $ramUsageLabel = $ramTotalMb > 0
            ? round($ramUsedMb / 1024, 1) . ' / ' . round($ramTotalMb / 1024, 1) . ' GB'
            : '--';

        $storageTotalGb = (float) ($server->server_storage ?? 0);
        $storageUsedGb = (float) ($server->getMetadata('server_storage_used') ?? 0);
        $storagePercent = $storageTotalGb > 0 ? (int) round(min(100, ($storageUsedGb / $storageTotalGb) * 100)) : 0;
        $storageColor = $storagePercent > 90 ? 'danger' : ($storagePercent > 75 ? 'warning' : 'success');
        $storageUsageLabel = $storageTotalGb > 0 ? ($storageUsedGb ?: 0) . ' / ' . $storageTotalGb . ' GB' : '--';

        $domainsTotal = (int) ($server->max_domains ?? 0);
        $domainsUsed = (int) ($server->current_domains ?? 0);
        $domainsPercent = $domainsTotal > 0 ? (int) round(min(100, ($domainsUsed / $domainsTotal) * 100)) : null;
        $domainsUsageLabel = $domainsTotal > 0 ? number_format($domainsUsed) . ' / ' . number_format($domainsTotal) : number_format($domainsUsed) . ' active';

        $provisioningStatus = (string) ($server->provisioning_status ?? '');
        $statusChipClass = 'bg-' . $statusConfig['bg'] . ' text-' . $statusConfig['color'];
        $defaultTab = 'general';

        $hestiaManageUrl = null;
        if ($server->fqdn) {
            $plainDomain = preg_replace('#^https?://#', '', $server->fqdn);
            $hestiaManageUrl = 'https://' . $plainDomain . ':' . ($server->port ?: 8443);
        }
    @endphp

    <div class="server-command-center mb-4">
        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card sv-panel sv-hero h-100 border">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <div class="small text-muted text-uppercase fw-semibold mb-2">Command Center</div>
                                <div class="h4 mb-1">{{ $server->name }}</div>
                                @if($server->fqdn)
                                    <a href="{{ $fqdnLink }}" target="_blank" class="text-decoration-none fw-semibold">
                                        {{ $server->fqdn }}
                                    </a>
                                @else
                                    <span class="text-muted">No hostname configured</span>
                                @endif
                            </div>
                            <div class="text-end">
                                <div class="small text-muted">Server ID</div>
                                <div class="fw-semibold font-monospace">{{ $server->uid ?? $server->id }}</div>
                                <div class="small text-muted mt-1">
                                    Last sync:
                                    @if($lastSyncedAt)
                                        {{ \Carbon\Carbon::parse($lastSyncedAt)->diffForHumans() }}
                                    @else
                                        Never
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-sm-6 col-lg-3">
                                <div class="sv-metric-box h-100">
                                    <div class="sv-metric-label">CPU</div>
                                    <div class="sv-metric-value">
                                        @if($server->server_ccore)
                                            {{ $server->server_ccore }} Cores
                                        @else
                                            --
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="sv-metric-box h-100">
                                    <div class="sv-metric-label">RAM</div>
                                    <div class="sv-metric-value">{{ $ramUsageLabel }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="sv-metric-box h-100">
                                    <div class="sv-metric-label">Storage</div>
                                    <div class="sv-metric-value">{{ $storageUsageLabel }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="sv-metric-box h-100">
                                    <div class="sv-metric-label">Domains</div>
                                    <div class="sv-metric-value">{{ $domainsUsageLabel }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="sv-health-chip {{ $statusChipClass }}">
                                <i class="{{ $statusConfig['icon'] }}"></i>
                                {{ ucfirst($server->status) }}
                            </span>
                            <span class="sv-health-chip {{ $hasSshCredentials ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">
                                <i class="{{ $hasSshCredentials ? 'ri-key-2-line' : 'ri-key-line' }}"></i>
                                SSH: {{ $hasSshCredentials ? 'Configured' : 'Missing' }}
                            </span>
                            <span class="sv-health-chip {{ $domainsPercent !== null && $domainsPercent > 85 ? 'bg-warning-subtle text-warning' : 'bg-info-subtle text-info' }}">
                                <i class="ri-global-line"></i>
                                Domain usage: {{ $domainsPercent !== null ? $domainsPercent . '%' : 'Unlimited' }}
                            </span>
                            <span class="sv-health-chip {{ $server->acme_configured ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                <i class="{{ $server->acme_configured ? 'ri-shield-check-line' : 'ri-shield-cross-line' }}"></i>
                                SSL: {{ $server->acme_configured ? 'ACME Ready' : 'Not Configured' }}
                            </span>
                        </div>

                        <div class="row g-3 small">
                            <div class="col-md-6">
                                <div class="text-muted text-uppercase fw-semibold small mb-2">Infrastructure</div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">IP</span>
                                    <span class="fw-semibold font-monospace">{{ $server->ip ?? '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Port</span>
                                    <span class="fw-semibold font-monospace">{{ $server->port ?? '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Provider</span>
                                    <span class="fw-semibold">
                                        @if($serverProvider)
                                            <a href="{{ route('platform.providers.show', $serverProvider->id) }}" class="text-decoration-none">
                                                {{ $serverProvider->name }}
                                            </a>
                                        @else
                                            --
                                        @endif
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Location</span>
                                    <span class="fw-semibold">{{ $server->location_label ?? '--' }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted text-uppercase fw-semibold small mb-2">Software</div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Operating System</span>
                                    <span class="fw-semibold">{{ $server->server_os ?? '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Hestia CP</span>
                                    <span class="fw-semibold">{{ $server->hestia_version ? 'v' . $server->hestia_version : '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Astero</span>
                                    <span class="fw-semibold">{{ $server->astero_version ? 'v' . $server->astero_version : '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">ACME (SSL)</span>
                                    <span class="fw-semibold">
                                        @if($server->acme_configured)
                                            <span class="text-success"><i class="ri-check-line"></i> Configured</span>
                                        @else
                                            <span class="text-danger"><i class="ri-close-line"></i> Not setup</span>
                                        @endif
                                    </span>
                                </div>
                                @if($server->acme_email)
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">ACME Email</span>
                                        <span class="fw-semibold">{{ $server->acme_email }}</span>
                                    </div>
                                @endif
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Uptime</span>
                                    <span class="fw-semibold">{{ $server->getMetadata('server_uptime') ?: '--' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card sv-panel h-100 border">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-flashlight-line me-1"></i> Operations
                        </h5>
                    </div>
                    <div class="card-body d-flex flex-column gap-3">
                        <div class="small text-muted">
                            High-impact actions for synchronization and server maintenance.
                        </div>

                        @if(!$isTrashed)
                            <div class="sv-action-grid">
                                @if($hestiaManageUrl)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ $hestiaManageUrl }}" target="_blank">
                                        <i class="ri-external-link-line me-1"></i>Manage
                                    </a>
                                @endif

                                @can('edit_servers')
                                    @if($isActive)
                                        <a class="btn btn-sm btn-outline-info confirmation-btn"
                                            data-title="Sync Server"
                                            data-method="POST"
                                            data-message="Fetch the latest server information from Hestia?"
                                            data-confirmButtonText="Sync"
                                            data-loaderButtonText="Syncing..."
                                            href="{{ route('platform.servers.sync-server', $server->id) }}">
                                            <i class="ri-refresh-line me-1"></i>Sync
                                        </a>

                                        <a class="btn btn-sm btn-outline-secondary confirmation-btn"
                                            data-title="Update Releases"
                                            data-method="POST"
                                            data-message="Download the latest release package to this server?"
                                            data-confirmButtonText="Update"
                                            data-loaderButtonText="Updating..."
                                            href="{{ route('platform.servers.update-releases', $server->id) }}">
                                            <i class="ri-download-line me-1"></i>Releases
                                        </a>

                                        @if($hasSshCredentials)
                                            <a class="btn btn-sm btn-outline-primary confirmation-btn"
                                                data-title="Update Scripts"
                                                data-method="POST"
                                                data-message="Upload the latest Astero scripts and templates via SSH?"
                                                data-confirmButtonText="Update"
                                                data-loaderButtonText="Updating..."
                                                href="{{ route('platform.servers.update-scripts', $server->id) }}">
                                                <i class="ri-upload-cloud-line me-1"></i>Scripts
                                            </a>
                                        @endif
                                    @endif

                                    @if($isProvisionModeServer && $server->canProvision() && $hasSshCredentials)
                                        <a class="btn btn-sm btn-primary confirmation-btn"
                                            data-title="Provision Server"
                                            data-method="POST"
                                            data-message="Install HestiaCP and Astero scripts on this server? This can take 15-30 minutes."
                                            data-confirmButtonText="Start Provisioning"
                                            data-loaderButtonText="Starting..."
                                            href="{{ route('platform.servers.provision', $server->id) }}">
                                            <i class="ri-server-line me-1"></i>Provision
                                        </a>
                                    @endif

                                    @if($isActive)
                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#optimizationToolModal">
                                            <i class="ri-speed-up-line me-1"></i>Optimize
                                        </button>
                                    @endif

                                    @if($isActive && $hasSshCredentials && !$server->acme_configured)
                                        <a class="btn btn-sm btn-outline-success confirmation-btn"
                                            data-title="Setup ACME (SSL)"
                                            data-method="POST"
                                            data-message="Install acme.sh and configure SSL certificate automation on this server? This takes about 30 seconds."
                                            data-confirmButtonText="Setup"
                                            data-loaderButtonText="Setting up..."
                                            href="{{ route('platform.servers.setup-acme', $server->id) }}">
                                            <i class="ri-shield-check-line me-1"></i>Setup ACME
                                        </a>
                                    @endif
                                @endcan
                            </div>
                        @else
                            <div class="alert alert-warning mb-0 small">
                                <i class="ri-alert-line me-1"></i>
                                This server is in trash. Restore it first to run operations.
                            </div>
                        @endif

                        <div class="sv-ops-divider">
                            <div class="small text-muted text-uppercase fw-semibold mb-2">Capacity</div>
                            <div class="sv-ops-row mb-2">
                                <span class="small text-muted">RAM</span>
                                <span class="badge bg-{{ $ramColor }}-subtle text-{{ $ramColor }}">{{ $ramPercent }}%</span>
                            </div>
                            <div class="progress sv-capacity-meter mb-3">
                                <div class="progress-bar bg-{{ $ramColor }}" style="width: {{ $ramPercent }}%;"></div>
                            </div>

                            <div class="sv-ops-row mb-2">
                                <span class="small text-muted">Storage</span>
                                <span class="badge bg-{{ $storageColor }}-subtle text-{{ $storageColor }}">{{ $storagePercent }}%</span>
                            </div>
                            <div class="progress sv-capacity-meter mb-3">
                                <div class="progress-bar bg-{{ $storageColor }}" style="width: {{ $storagePercent }}%;"></div>
                            </div>

                            <div class="sv-ops-row">
                                <span class="small text-muted">Domains</span>
                                <span class="badge {{ $domainsPercent !== null && $domainsPercent > 85 ? 'bg-warning-subtle text-warning' : 'bg-info-subtle text-info' }}">
                                    {{ $domainsPercent !== null ? $domainsPercent . '%' : 'Unlimited' }}
                                </span>
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
        $websitesCount = (int) ($websiteCounts['total'] ?? 0);
        $agenciesCount = $server->agencies?->count() ?? 0;
        $secretsCount = isset($secrets) ? $secrets->count() : 0;
        $showProvisioningTab = $isProvisionModeServer;
        if ($showProvisioningTab && $provisioningStatus === \Modules\Platform\Models\Server::PROVISIONING_STATUS_PROVISIONING) {
            $defaultTab = 'provisioning';
        }

        $tabs = [
            ['name' => 'general', 'label' => 'General', 'icon' => 'ri-settings-3-line'],
        ];

        // Add secrets tab if there are any secrets
        if ($secretsCount > 0) {
            $tabs[] = ['name' => 'secrets', 'label' => 'Secrets', 'icon' => 'ri-key-2-line', 'count' => $secretsCount];
        }

        $tabs[] = ['name' => 'websites', 'label' => 'Websites', 'icon' => 'ri-global-line', 'count' => $websitesCount];
        $tabs[] = ['name' => 'agencies', 'label' => 'Agencies', 'icon' => 'ri-building-line', 'count' => $agenciesCount];

        // Add provisioning tab only for provision-mode servers
        if ($showProvisioningTab) {
            $tabs[] = ['name' => 'provisioning', 'label' => 'Provisioning', 'icon' => 'ri-server-line'];
        }

        $tabs = array_merge($tabs, [
            ['name' => 'notes', 'label' => 'Notes', 'icon' => 'ri-sticky-note-line'],
            ['name' => 'metadata', 'label' => 'Metadata', 'icon' => 'ri-code-s-slash-line'],
            ['name' => 'activity', 'label' => 'Activity', 'icon' => 'ri-pulse-line'],
        ]);
    @endphp

    <x-tabs param="section" :active="$activeTab" :tabs="$tabs" class="border shadow-none">
        <x-slot:general>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header mb-3">
                            <h6 class="card-title mb-0">Credentials</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Access Key ID</span>
                                    <span class="fw-semibold font-monospace">{{ $server->access_key_id ?? '--' }}</span>
                                </div>
                                <div class="d-flex flex-column gap-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">Secret Key</span>
                                        @if(($canRevealSshKeyPair ?? false) && !empty($server->access_key_secret))
                                            <div class="d-flex align-items-center gap-1">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    id="revealAccessKeySecretBtn"
                                                    data-reveal-url="{{ route('platform.servers.access-key-secret.reveal', ['server' => $server->id]) }}">
                                                    <i class="ri-eye-line me-1"></i>Reveal
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary p-1" id="copyAccessKeySecretBtn" title="Copy secret key" style="display:none">
                                                    <i class="ri-file-copy-line fs-5"></i>
                                                </button>
                                            </div>
                                        @else
                                            <span class="text-muted small fst-italic">Hidden for security</span>
                                        @endif
                                    </div>
                                    <div id="accessKeySecretField" class="d-none">
                                        <input type="text" class="form-control form-control-sm font-monospace" id="accessKeySecretValue" readonly />
                                    </div>
                                </div>

                                @if(($canRevealSshKeyPair ?? false) && $server->hasSshCredentials())
                                    <div class="border-top pt-3 mt-2">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">SSH Key Pair</span>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-secondary"
                                                id="revealSshPairBtn"
                                                data-reveal-url="{{ route('platform.servers.ssh-key-pair.reveal', ['server' => $server->id]) }}">
                                                <i class="ri-eye-line me-1"></i>Reveal
                                            </button>
                                        </div>

                                        <div id="sshPairDetails" class="d-none">
                                            <div class="mb-2">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="small text-muted">Public Key</span>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary p-1 copy-btn" title="Copy public key" data-copy-target="#sshPublicKeyField">
                                                        <i class="ri-file-copy-line fs-5"></i>
                                                    </button>
                                                </div>
                                                <textarea id="sshPublicKeyField" class="form-control form-control-sm font-monospace" rows="3" readonly></textarea>
                                            </div>

                                            <div class="mb-2">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="small text-muted">Authorize Command (run on server)</span>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary p-1 copy-btn" title="Copy authorize command" data-copy-target="#sshAuthorizeCommandField">
                                                        <i class="ri-file-copy-line fs-5"></i>
                                                    </button>
                                                </div>
                                                <textarea id="sshAuthorizeCommandField" class="form-control form-control-sm font-monospace" rows="3" readonly></textarea>
                                            </div>

                                            <div>
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="small text-muted">Private Key</span>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary p-1 copy-btn" title="Copy private key" data-copy-target="#sshPrivateKeyField">
                                                        <i class="ri-file-copy-line fs-5"></i>
                                                    </button>
                                                </div>
                                                <textarea id="sshPrivateKeyField" class="form-control form-control-sm font-monospace" rows="5" readonly></textarea>
                                            </div>
                                        </div>
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
                                    <span class="fw-semibold">{{ $server->created_at ? $server->created_at->format('M d, Y') : '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Last Updated</span>
                                    <span class="fw-semibold">{{ $server->updated_at ? $server->updated_at->diffForHumans() : '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Last Synced</span>
                                    <span class="fw-semibold">
                                        @if($server->getMetadata('last_synced_at'))
                                            {{ \Carbon\Carbon::parse($server->getMetadata('last_synced_at'))->diffForHumans() }}
                                        @else
                                            <span class="text-muted">Never</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-slot:general>

        {{-- Secrets Tab --}}
        @if(isset($secrets) && $secrets->count() > 0)
        <x-slot:secrets>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Key</th>
                            <th>Username</th>
                            <th>Password</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($secrets as $secret)
                            <tr>
                                <td>
                                    <code class="bg-light px-2 py-1 rounded small">{{ $secret->key }}</code>
                                </td>
                                <td>
                                    @if($secret->username)
                                        <div class="d-flex align-items-center gap-2">
                                            <code class="bg-light px-2 py-1 rounded small">{{ $secret->username }}</code>
                                            <button class="btn btn-sm btn-outline-secondary p-1 copy-btn text-decoration-none" title="Copy username" data-copy-value="{{ $secret->username }}">
                                                <i class="ri-file-copy-line fs-5"></i>
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2" data-secret-id="{{ $secret->id }}" data-reveal-url="{{ route('platform.servers.secrets.reveal', ['server' => $server->id, 'secret' => $secret->id]) }}">
                                        <code class="bg-light px-2 py-1 rounded small password-field" data-hidden="true">••••••••</code>
                                        <button class="btn btn-sm btn-outline-secondary p-1 toggle-password text-decoration-none" title="Show/Hide">
                                            <i class="ri-eye-line fs-5"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary p-1 copy-btn text-decoration-none" title="Copy password" data-secret-id="{{ $secret->id }}" data-reveal-url="{{ route('platform.servers.secrets.reveal', ['server' => $server->id, 'secret' => $secret->id]) }}">
                                            <i class="ri-file-copy-line fs-5"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-slot:secrets>
        @endif

        <x-slot:websites>
            <div id="websites-tab" class="related-tab" data-endpoint="{{ route('platform.servers.websites', $server->id) }}">
                <div class="d-flex align-items-center justify-content-center py-4" data-role="loading">
                    <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                    <span>Loading websites...</span>
                </div>

                <div class="d-none" data-role="content">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-secondary">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Domain</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3" data-role="pagination"></div>
                </div>

                <div class="alert alert-warning d-none mt-3" role="alert" data-role="empty">
                    No websites hosted on this server.
                </div>

                <div class="alert alert-danger d-none mt-3 d-flex align-items-center justify-content-between" role="alert" data-role="error">
                    <div>Unable to load websites right now.</div>
                    <button type="button" class="btn btn-sm btn-outline-light" data-role="retry">Retry</button>
                </div>
            </div>
        </x-slot:websites>

        <x-slot:agencies>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="mb-0">Associated Agencies</h6>
                @can('edit_servers')
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#manageAgenciesModal">
                        <i class="ri-add-line me-1"></i>Manage Agencies
                    </button>
                @endcan
            </div>

            <div id="agencies-list">
                @if($server->agencies->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Agency Name</th>
                                    <th>Status</th>
                                    <th>Primary</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($server->agencies as $agency)
                                    <tr data-agency-id="{{ $agency->id }}">
                                        <td>
                                            <a href="{{ route('platform.agencies.show', $agency->id) }}" class="text-decoration-none fw-semibold">
                                                {{ $agency->name }}
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge text-bg-{{ $agency->status === 'active' ? 'success' : 'secondary' }}">
                                                {{ ucfirst($agency->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($agency->pivot->is_primary)
                                                <span class="badge text-bg-primary">
                                                    <i class="ri-star-fill me-1"></i>Primary
                                                </span>
                                            @else
                                                @can('edit_servers')
                                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-set-primary"
                                                        data-agency-id="{{ $agency->id }}"
                                                        data-agency-name="{{ $agency->name }}">
                                                        Set as Primary
                                                    </button>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endcan
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @can('edit_servers')
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-agency"
                                                    data-agency-id="{{ $agency->id }}"
                                                    data-agency-name="{{ $agency->name }}">
                                                    <i class="ri-close-line"></i> Remove
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
                        <i class="ri-building-line fs-1 mb-3 d-block opacity-50"></i>
                        <p class="mb-0">No agencies associated with this server.</p>
                        @can('edit_servers')
                            <button type="button" class="btn btn-sm btn-outline-primary mt-3" data-bs-toggle="modal" data-bs-target="#manageAgenciesModal">
                                <i class="ri-add-line me-1"></i>Add Agencies
                            </button>
                        @endcan
                    </div>
                @endif
            </div>
        </x-slot:agencies>

        @if($showProvisioningTab)
        <x-slot:provisioning>
            <div x-data="serverProvisioningSteps" x-init="init()" id="server-provisioning-steps">
                {{-- Header with Status and Actions --}}
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <h6 class="mb-0">Provisioning Steps</h6>
                        <span class="badge"
                              :class="{
                                  'bg-secondary-subtle text-secondary': status === 'pending',
                                  'bg-info-subtle text-info': status === 'provisioning',
                                  'bg-success-subtle text-success': status === 'ready',
                                  'bg-danger-subtle text-danger': status === 'failed'
                              }">
                            <template x-if="status === 'provisioning'">
                                <span class="spinner-border spinner-border-sm me-1" style="width: 0.75rem; height: 0.75rem;"></span>
                            </template>
                            <span x-text="statusLabel">{{ $server->provisioning_status_label }}</span>
                        </span>
                    </div>
                    <div class="d-flex gap-2">
                        @can('edit_servers')
                            {{-- Show Start Provisioning button when can provision --}}
                            <template x-if="(status === 'pending' || status === 'failed') && {{ $server->hasSshCredentials() ? 'true' : 'false' }}">
                                <button type="button" class="btn btn-sm btn-primary"
                                    :disabled="loading"
                                    @click="startProvisioning()">
                                    <template x-if="loading && currentAction === 'all'">
                                        <span class="spinner-border spinner-border-sm me-1"></span>
                                    </template>
                                    <i class="ri-play-circle-line me-1" x-show="!(loading && currentAction === 'all')"></i>
                                    <span x-text="loading && currentAction === 'all' ? 'Starting...' : 'Start Provisioning'"></span>
                                </button>
                            </template>
                            {{-- Show Retry button only when failed --}}
                            <template x-if="status === 'failed'">
                                <button type="button" class="btn btn-sm btn-warning"
                                    :disabled="loading"
                                    @click="retryProvisioning()">
                                    <template x-if="loading && currentAction === 'retry'">
                                        <span class="spinner-border spinner-border-sm me-1"></span>
                                    </template>
                                    <i class="ri-refresh-line me-1" x-show="!(loading && currentAction === 'retry')"></i>
                                    Retry Failed Steps
                                </button>
                            </template>
                            <template x-if="status === 'provisioning'">
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                    :disabled="loading"
                                    @click="stopProvisioning()">
                                    <template x-if="loading && currentAction === 'stop'">
                                        <span class="spinner-border spinner-border-sm me-1"></span>
                                    </template>
                                    <i class="ri-stop-circle-line me-1" x-show="!(loading && currentAction === 'stop')"></i>
                                    <span x-text="loading && currentAction === 'stop' ? 'Stopping...' : 'Stop Provisioning'"></span>
                                </button>
                            </template>
                            {{-- Show Reprovision button when ready (already provisioned) --}}
                            <template x-if="status === 'ready' && {{ $server->hasSshCredentials() ? 'true' : 'false' }}">
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                    :disabled="loading"
                                    @click="reprovisionServer()">
                                    <template x-if="loading && currentAction === 'reprovision'">
                                        <span class="spinner-border spinner-border-sm me-1"></span>
                                    </template>
                                    <i class="ri-restart-line me-1" x-show="!(loading && currentAction === 'reprovision')"></i>
                                    <span x-text="loading && currentAction === 'reprovision' ? 'Starting...' : 'Reprovision'"></span>
                                </button>
                            </template>
                        @endcan
                    </div>
                </div>

                {{-- Progress Bar --}}
                <template x-if="status === 'provisioning' || progress > 0">
                    <div class="progress mb-4" style="height: 8px;">
                        <div class="progress-bar progress-bar-striped"
                             :class="status === 'provisioning' ? 'progress-bar-animated bg-info' : (status === 'failed' ? 'bg-danger' : 'bg-success')"
                             role="progressbar"
                             :style="`width: ${progress}%`"
                             :aria-valuenow="progress"
                             aria-valuemin="0"
                             aria-valuemax="100">
                        </div>
                    </div>
                </template>

                {{-- Steps Timeline --}}
                <div class="provisioning-steps">
                    <template x-for="(config, stepKey) in stepsConfig" :key="stepKey">
                        <div class="step-item d-flex align-items-start gap-3 mb-3 p-3 rounded border"
                             :class="{
                                 'border-success bg-success-subtle': getStepStatus(stepKey) === 'completed',
                                 'border-info bg-info-subtle': getStepStatus(stepKey) === 'running',
                                 'border-warning bg-warning-subtle': getStepStatus(stepKey) === 'skipped',
                                 'border-danger bg-danger-subtle': getStepStatus(stepKey) === 'failed',
                                 'border-secondary': getStepStatus(stepKey) === 'pending'
                             }">
                            {{-- Step Icon --}}
                            <div class="step-icon d-flex align-items-center justify-content-center rounded-circle"
                                 :class="{
                                     'bg-success text-white': getStepStatus(stepKey) === 'completed',
                                     'bg-info text-white': getStepStatus(stepKey) === 'running',
                                     'bg-warning text-dark': getStepStatus(stepKey) === 'skipped',
                                     'bg-danger text-white': getStepStatus(stepKey) === 'failed',
                                     'bg-secondary-subtle text-secondary': getStepStatus(stepKey) === 'pending'
                                 }"
                                 style="width: 40px; height: 40px; flex-shrink: 0;">
                                <template x-if="getStepStatus(stepKey) === 'running'">
                                    <span class="spinner-border spinner-border-sm"></span>
                                </template>
                                <template x-if="getStepStatus(stepKey) === 'completed'">
                                    <i class="ri-check-line fs-5"></i>
                                </template>
                                <template x-if="getStepStatus(stepKey) === 'failed'">
                                    <i class="ri-close-line fs-5"></i>
                                </template>
                                <template x-if="getStepStatus(stepKey) === 'skipped'">
                                    <i class="ri-skip-forward-line fs-5"></i>
                                </template>
                                <template x-if="getStepStatus(stepKey) === 'pending'">
                                    <i :class="config.icon + ' fs-5'"></i>
                                </template>
                            </div>

                            {{-- Step Content --}}
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1" x-text="config.title"></h6>
                                        <p class="text-muted small mb-0" x-text="config.description"></p>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge"
                                              :class="{
                                                  'bg-success-subtle text-success': getStepStatus(stepKey) === 'completed',
                                                  'bg-info-subtle text-info': getStepStatus(stepKey) === 'running',
                                                  'bg-warning-subtle text-warning': getStepStatus(stepKey) === 'skipped',
                                                  'bg-danger-subtle text-danger': getStepStatus(stepKey) === 'failed',
                                                  'bg-secondary-subtle text-secondary': getStepStatus(stepKey) === 'pending'
                                              }"
                                              x-text="getStepStatus(stepKey).charAt(0).toUpperCase() + getStepStatus(stepKey).slice(1)">
                                        </span>
                                    </div>
                                </div>

                                {{-- Step Details (when completed or failed) --}}
                                <template x-if="stepsData[stepKey] && (stepsData[stepKey].completed_at || stepsData[stepKey].data)">
                                    <div class="mt-2 small">
                                        <template x-if="stepsData[stepKey].completed_at">
                                            <span class="text-muted">
                                                <i class="ri-time-line me-1"></i>
                                                <span x-text="formatDate(stepsData[stepKey].completed_at)"></span>
                                            </span>
                                        </template>
                                        <template x-if="stepsData[stepKey].data && getStepStatus(stepKey) === 'failed'">
                                            <div class="text-danger mt-1">
                                                <i class="ri-error-warning-line me-1"></i>
                                                <span x-text="stepsData[stepKey].data.error || 'Step failed'"></span>
                                            </div>
                                        </template>
                                        <template x-if="stepsData[stepKey].data && getStepStatus(stepKey) === 'skipped'">
                                            <div class="text-warning mt-1">
                                                <i class="ri-information-line me-1"></i>
                                                <span x-text="stepsData[stepKey].data.reason || 'Step skipped'"></span>
                                            </div>
                                        </template>
                                        <template x-if="stepsData[stepKey].data && getStepStatus(stepKey) === 'completed' && stepsData[stepKey].data.summary">
                                            <div class="text-success mt-1">
                                                <i class="ri-check-line me-1"></i>
                                                <span x-text="stepsData[stepKey].data.summary"></span>
                                            </div>
                                        </template>
                                        <template x-if="stepsData[stepKey].data && getStepStatus(stepKey) === 'completed' && getStepOutput(stepKey)">
                                            <details class="mt-2">
                                                <summary class="text-muted">Output</summary>
                                                <pre class="mb-0 mt-2 p-2 bg-light border rounded" style="white-space: pre-wrap;" x-text="getStepOutput(stepKey)"></pre>
                                            </details>
                                        </template>
                                    </div>
                                </template>

                                {{-- Execute Button for individual steps --}}
                                @can('edit_servers')
                                <template x-if="canExecuteStep(stepKey)">
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            :disabled="loading"
                                            @click="executeStep(stepKey)">
                                            <template x-if="loading && currentAction === stepKey">
                                                <span class="spinner-border spinner-border-sm me-1"></span>
                                            </template>
                                            <i class="ri-play-line me-1" x-show="!(loading && currentAction === stepKey)"></i>
                                            Execute
                                        </button>
                                    </div>
                                </template>
                                @endcan
                            </div>
                        </div>
                    </template>
                </div>

                {{-- No SSH Credentials Warning --}}
                @if(!$server->hasSshCredentials())
                <div class="alert alert-warning d-flex align-items-center mt-4">
                    <i class="ri-key-line fs-4 me-3"></i>
                    <div>
                        <strong>SSH Credentials Required</strong>
                        <p class="mb-0 small">This server does not have SSH credentials configured. Provisioning requires SSH access.</p>
                    </div>
                </div>
                @endif
            </div>

            <script data-up-execute>
                (() => {
                    // Idempotent across Unpoly re-exec (avoid double Alpine.data registration)
                    window.__asteroAlpineData = window.__asteroAlpineData || {};
                    if (window.__asteroAlpineData.serverProvisioningSteps) {
                        return;
                    }

                    const initProvisioningTree = () => {
                        const el = document.getElementById('server-provisioning-steps');
                        if (!el) {
                            return;
                        }

                        // Alpine v3 doesn't auto-init newly injected HTML.
                        if (typeof Alpine !== 'undefined' && Alpine.initTree && !el._x_dataStack) {
                            Alpine.initTree(el);
                        }
                    };

                    const registerComponent = () => {
                        window.__asteroAlpineData = window.__asteroAlpineData || {};
                        if (window.__asteroAlpineData.serverProvisioningSteps) {
                            initProvisioningTree();
                            return;
                        }
                        Alpine.data('serverProvisioningSteps', () => ({
                            stepsConfig: @json($provisioningSteps ?? []),
                            stepsData: @json($provisioningStepsData ?? []),
                            status: '{{ $server->provisioning_status }}',
                            statusLabel: '{{ $server->provisioning_status_label }}',
                            progress: {{ $progressPercent ?? 0 }},
                            loading: false,
                            currentAction: null,
                            pollInterval: null,

                            get isProvisioning() {
                                return this.status === 'provisioning';
                            },

                            init() {
                                if (this.isProvisioning) {
                                    this.startPolling();
                                }
                            },

                            startPolling() {
                                if (this.pollInterval) return;

                                // Define the poll function
                                const poll = () => {
                                    fetch('{{ route('platform.servers.show', $server->id) }}?json=1', {
                                        headers: { 'Accept': 'application/json' }
                                    })
                                    .then(res => res.json())
                                    .then(data => {
                                        this.stepsData = data.provisioning_steps || {};
                                        this.status = data.provisioning_status;
                                        this.progress = data.progress_percent;
                                        this.updateStatusLabel();

                                        if (data.provisioning_status !== 'provisioning') {
                                            this.stopPolling();
                                            // Reload to update the full page state
                                            setTimeout(() => window.location.reload(), 1000);
                                        }
                                    })
                                    .catch(err => console.error('Polling error:', err));
                                };

                                // Poll immediately, then every 3 seconds
                                poll();
                                this.pollInterval = setInterval(poll, 3000);
                            },

                            stopPolling() {
                                if (this.pollInterval) {
                                    clearInterval(this.pollInterval);
                                    this.pollInterval = null;
                                }
                            },

                            updateStatusLabel() {
                                const labels = {
                                    'pending': 'Pending',
                                    'provisioning': 'Provisioning',
                                    'ready': 'Ready',
                                    'failed': 'Failed'
                                };
                                this.statusLabel = labels[this.status] || this.status;
                            },

                            getStepStatus(stepKey) {
                                return this.stepsData[stepKey]?.status || 'pending';
                            },

                            getStepOutput(stepKey) {
                                const stepData = this.stepsData[stepKey]?.data || {};
                                return stepData.log_tail || stepData.output_tail || '';
                            },

                            canExecuteStep(stepKey) {
                                const status = this.getStepStatus(stepKey);
                                // Allow execution of failed or pending steps
                                const executableSteps = ['ssh_connection', 'hestia_check', 'hestia_install', 'server_reboot', 'scripts_upload', 'server_setup', 'release_api_key', 'access_key', 'verification', 'update_releases', 'server_sync'];
                                return executableSteps.includes(stepKey) && ['pending', 'failed'].includes(status);
                            },

                            startProvisioning() {
                                this.executeAction('all', '{{ route('platform.servers.execute.step', ['server' => $server->id, 'step' => 'all']) }}');
                            },

                            retryProvisioning() {
                                this.executeAction('retry', '{{ route('platform.servers.retry-provisioning', $server->id) }}');
                            },

                            stopProvisioning() {
                                if (!confirm('This will force-stop provisioning and mark the run as failed. You can retry later. Continue?')) {
                                    return;
                                }

                                this.executeAction('stop', '{{ route('platform.servers.stop-provisioning', $server->id) }}');
                            },

                            reprovisionServer() {
                                if (!confirm('This will reset ALL provisioning steps and run the full provisioning process again. Continue?')) {
                                    return;
                                }
                                this.executeAction('reprovision', '{{ route('platform.servers.reprovision', $server->id) }}');
                            },

                            executeStep(stepKey) {
                                const url = '{{ route('platform.servers.execute.step', ['server' => $server->id, 'step' => 'STEP_PLACEHOLDER']) }}'.replace('STEP_PLACEHOLDER', stepKey);
                                this.executeAction(stepKey, url);
                            },

                            executeAction(action, url) {
                                this.loading = true;
                                this.currentAction = action;

                                fetch(url, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                                    }
                                })
                                .then(res => res.json())
                                .then(data => {
                                    if (data.status === 'success') {
                                        window.ToastSystem?.show({ type: 'success', message: data.message });

                                        if (action === 'all' || action === 'retry' || action === 'reprovision') {
                                            this.status = 'provisioning';
                                            this.updateStatusLabel();
                                            this.startPolling(); // Polling will handle refresh
                                            return; // Don't call refreshSteps since polling does it
                                        }

                                        if (action === 'stop') {
                                            this.stopPolling();
                                            this.status = 'failed';
                                            this.updateStatusLabel();
                                            this.refreshSteps();
                                            return;
                                        }
                                    } else {
                                        window.ToastSystem?.show({ type: 'error', message: data.message || 'Action failed' });
                                    }
                                    // Refresh steps for individual actions or errors
                                    this.refreshSteps();
                                })
                                .catch(err => {
                                    console.error('Action error:', err);
                                    window.ToastSystem?.show({ type: 'error', message: 'An error occurred' });
                                    // Refresh steps even on network error to ensure UI is in sync
                                    this.refreshSteps();
                                })
                                .finally(() => {
                                    this.loading = false;
                                    this.currentAction = null;
                                });
                            },

                            refreshSteps() {
                                fetch('{{ route('platform.servers.show', $server->id) }}?json=1', {
                                    headers: { 'Accept': 'application/json' }
                                })
                                .then(res => res.json())
                                .then(data => {
                                    this.stepsData = data.provisioning_steps || {};
                                    this.status = data.provisioning_status;
                                    this.progress = data.progress_percent;
                                    this.updateStatusLabel();
                                });
                            },

                            formatDate(dateStr) {
                                if (!dateStr) return '--';
                                const date = new Date(dateStr);
                                return date.toLocaleString('en-US', {
                                    month: 'short',
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });
                            }
                        }));

                        window.__asteroAlpineData.serverProvisioningSteps = true;

                        initProvisioningTree();
                    };

                    // Register immediately if Alpine is ready, otherwise wait
                    if (window.Alpine) {
                        registerComponent();
                    } else {
                        document.addEventListener('alpine:init', registerComponent);
                    }
                })();
            </script>
        </x-slot:provisioning>
        @endif

        <x-slot:notes>
            <x-app.notes :model="$server" />
        </x-slot:notes>

        <x-slot:metadata>
            @php
                $metadata = $server->metadata ?? [];
                $sensitiveMetadataKeys = [
                    'release_api_key',
                    'temp_admin_password',
                ];
                $redactedMetadata = collect($metadata)->map(function ($value, $key) use ($sensitiveMetadataKeys) {
                    if (in_array($key, $sensitiveMetadataKeys, true)) {
                        return '[REDACTED]';
                    }

                    return $value;
                })->all();
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
                            @foreach($redactedMetadata as $key => $value)
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
                        <pre class="mt-2 bg-light p-3 rounded small" style="max-height: 400px; overflow-y: auto;"><code>{{ json_encode($redactedMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                    </details>
                </div>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="ri-database-2-line fs-1 mb-3 d-block opacity-50"></i>
                    <p class="mb-0">No metadata stored for this server.</p>
                </div>
            @endif
        </x-slot:metadata>

        <x-slot:activity>
            @if(isset($server_activities) && count($server_activities) > 0)
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
                            @foreach($server_activities as $activity)
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
                @if(count($server_activities) >= 50)
                    <div class="text-center mt-3">
                        <a href="{{ route('app.logs.activity-logs.index', ['subject_type' => 'Modules\\Platform\\Models\\Server', 'subject_id' => $server->id]) }}" class="btn btn-sm btn-outline-primary">View All Activities</a>
                    </div>
                @endif
            @else
                <div class="text-center py-5 text-muted">
                    <i class="ri-pulse-line fs-1 mb-3 d-block opacity-50"></i>
                    <p class="mb-0">No activity logs found for this server.</p>
                </div>
            @endif
        </x-slot:activity>
    </x-tabs>

    <x-drawer id="server-drawer" />

    @push('scripts')
        <script data-up-execute>
            (() => {
                const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
                const activeSection = new URLSearchParams(window.location.search).get('section') || 'general';

                const bindFormOnce = (formId, handler) => {
                    const form = document.getElementById(formId);
                    if (!form || form.dataset.bound === '1') return;
                    form.dataset.bound = '1';
                    form.addEventListener('submit', handler);
                };

                const initRelatedTab = (containerId, options = {}) => {
                    const container = document.getElementById(containerId);
                    if (!container || container.dataset.bound === '1') return;
                    container.dataset.bound = '1';

                    const endpoint = container.dataset.endpoint;
                    const loadingEl = container.querySelector('[data-role="loading"]');
                    const contentEl = container.querySelector('[data-role="content"]');
                    const emptyEl = container.querySelector('[data-role="empty"]');
                    const errorEl = container.querySelector('[data-role="error"]');
                    const retryBtn = container.querySelector('[data-role="retry"]');
                    const tbody = container.querySelector('tbody');
                    const paginationEl = container.querySelector('[data-role="pagination"]');

                    const setView = (view) => {
                        [loadingEl, contentEl, emptyEl, errorEl].forEach((el) => el?.classList.add('d-none'));
                        if (view === 'loading') loadingEl?.classList.remove('d-none');
                        if (view === 'content') contentEl?.classList.remove('d-none');
                        if (view === 'empty') emptyEl?.classList.remove('d-none');
                        if (view === 'error') errorEl?.classList.remove('d-none');
                    };

                    const renderPagination = (meta) => {
                        if (!paginationEl) return;
                        paginationEl.innerHTML = '';

                        const info = document.createElement('div');
                        info.className = 'small text-muted';
                        info.textContent = `Showing ${meta.from ?? 0}-${meta.to ?? 0} of ${meta.total ?? 0}`;

                        const nav = document.createElement('div');
                        nav.className = 'btn-group';

                        const prev = document.createElement('button');
                        prev.className = 'btn btn-outline-secondary btn-sm';
                        prev.textContent = 'Prev';
                        prev.disabled = !!meta.on_first_page;
                        prev.dataset.page = Math.max(1, (meta.current_page ?? 1) - 1);

                        const next = document.createElement('button');
                        next.className = 'btn btn-outline-secondary btn-sm';
                        next.textContent = 'Next';
                        next.disabled = !meta.has_more_pages;
                        next.dataset.page = Math.min(meta.last_page ?? 1, (meta.current_page ?? 1) + 1);

                        nav.append(prev, next);
                        paginationEl.append(info, nav);
                    };

                    const renderRows = (items) => {
                        if (!tbody) return;
                        tbody.innerHTML = '';

                        items.forEach((item) => {
                            const tr = document.createElement('tr');

                            const idTd = document.createElement('td');
                            const idSpan = document.createElement('span');
                            idSpan.className = 'text-muted font-monospace small';
                            idSpan.textContent = item.uid || '—';
                            idTd.appendChild(idSpan);

                            const nameTd = document.createElement('td');
                            nameTd.textContent = item.name || '--';

                            const domainTd = document.createElement('td');
                            if (item.urls?.domain) {
                                const link = document.createElement('a');
                                link.href = item.urls.domain;
                                link.target = '_blank';
                                link.rel = 'noopener';
                                link.className = 'text-decoration-none';
                                link.textContent = item.domain;
                                const icon = document.createElement('i');
                                icon.className = 'ri-external-link-line small ms-1';
                                link.appendChild(icon);
                                domainTd.appendChild(link);
                            } else {
                                const span = document.createElement('span');
                                span.className = 'text-muted';
                                span.textContent = item.domain || '--';
                                domainTd.appendChild(span);
                            }

                            const statusTd = document.createElement('td');
                            const statusBadge = document.createElement('span');
                            const color = item.status?.color || 'secondary';
                            statusBadge.className = `badge bg-${color}-subtle text-${color}`;
                            statusBadge.textContent = item.status?.label || (item.status?.value ?? 'Unknown');
                            statusTd.appendChild(statusBadge);

                            const createdTd = document.createElement('td');
                            createdTd.className = 'small text-muted';
                            createdTd.textContent = item.created_at || '--';

                            const actionsTd = document.createElement('td');
                            actionsTd.className = 'text-end';
                            const viewLink = document.createElement('a');
                            viewLink.className = 'btn btn-sm btn-outline-primary';
                            viewLink.href = item.urls?.show ?? '#';
                            viewLink.textContent = 'View';
                            actionsTd.appendChild(viewLink);

                            tr.append(idTd, nameTd, domainTd, statusTd, createdTd, actionsTd);
                            tbody.appendChild(tr);
                        });
                    };

                    const load = (page = 1) => {
                        if (!endpoint) return;
                        setView('loading');

                        fetch(`${endpoint}?page=${page}`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        })
                            .then((response) => {
                                if (!response.ok) throw new Error('Network error');
                                return response.json();
                            })
                            .then((json) => {
                                const items = json.data?.items ?? [];
                                const pagination = json.data?.pagination ?? {};

                                if (!items.length) {
                                    setView('empty');
                                    paginationEl && (paginationEl.innerHTML = '');
                                    return;
                                }

                                renderRows(items);
                                renderPagination(pagination);
                                container.dataset.loaded = 'true';
                                setView('content');
                            })
                            .catch(() => {
                                setView('error');
                            });
                    };

                    paginationEl?.addEventListener('click', (event) => {
                        const btn = event.target.closest('[data-page]');
                        if (!btn) return;
                        event.preventDefault();
                        load(Number(btn.dataset.page || 1));
                    });

                    retryBtn?.addEventListener('click', () => load());

                    const tabToggle = options.section ? document.querySelector(`[data-tab-toggle="${options.section}"]`) : null;
                    if (tabToggle && tabToggle.dataset.bound !== '1') {
                        tabToggle.dataset.bound = '1';
                        tabToggle.addEventListener('click', () => {
                            if (!container.dataset.loaded) load();
                        });
                    }

                    if (activeSection === options.section) {
                        load();
                    } else {
                        setView('empty');
                        // Keep default empty state hidden until loaded.
                        [loadingEl, contentEl, emptyEl, errorEl].forEach((el) => el?.classList.add('d-none'));
                    }
                };

                initRelatedTab('websites-tab', { section: 'websites' });

                const root = document.documentElement;
                if (root.dataset.serverShowBound !== '1') {
                    root.dataset.serverShowBound = '1';

                    document.addEventListener('click', async (e) => {
                        const removeBtn = e.target.closest('.btn-remove-agency');
                        if (!removeBtn) return;

                        const agencyId = removeBtn.dataset.agencyId;
                        const agencyName = removeBtn.dataset.agencyName;
                        if (!confirm(`Remove ${agencyName} from this server?`)) return;

                        try {
                            const response = await fetch(
                                `{{ route('platform.servers.agencies.detach', ['server' => $server->id, 'agency' => '__AGENCY_ID__']) }}`.replace('__AGENCY_ID__', agencyId),
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
                            alert(data.message || 'Failed to remove agency');
                        } catch (error) {
                            console.error('Error removing agency:', error);
                            alert('An error occurred while removing the agency');
                        }
                    });

                    document.addEventListener('click', async (e) => {
                        const setPrimaryBtn = e.target.closest('.btn-set-primary');
                        if (!setPrimaryBtn) return;

                        const agencyId = setPrimaryBtn.dataset.agencyId;
                        const agencyName = setPrimaryBtn.dataset.agencyName;
                        if (!confirm(`Set ${agencyName} as primary agency for this server?`)) return;

                        try {
                            const response = await fetch(
                                `{{ route('platform.servers.agencies.set-primary', ['server' => $server->id, 'agency' => '__AGENCY_ID__']) }}`.replace('__AGENCY_ID__', agencyId),
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
                            alert(data.message || 'Failed to set primary agency');
                        } catch (error) {
                            console.error('Error setting primary agency:', error);
                            alert('An error occurred while setting primary agency');
                        }
                    });
                }

                bindFormOnce('manageAgenciesForm', async (e) => {
                    e.preventDefault();

                    const selectedAgencies = Array.from(document.getElementById('agency_ids')?.selectedOptions ?? []).map((opt) => opt.value);
                    const primaryAgencyId = document.getElementById('primary_agency_id')?.value || '';

                    try {
                        const response = await fetch(`{{ route('platform.servers.agencies.attach', ['server' => $server->id]) }}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf(),
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                agency_ids: selectedAgencies,
                                primary_agency_id: primaryAgencyId,
                            }),
                        });

                        const data = await response.json();
                        if (data.success) {
                            window.location.reload();
                            return;
                        }
                        alert(data.message || 'Failed to update agencies');
                    } catch (error) {
                        console.error('Error updating agencies:', error);
                        alert('An error occurred while updating agencies');
                    }
                });

                // On-demand secret reveal (avoid embedding decrypted values in HTML)
                const revealedSecrets = new Map();
                let revealedSshKeyPair = null;
                const passwordGate = window.PlatformSecretPasswordGate?.create('secretPasswordModal');

                async function requestSecretPassword(contextLabel = 'Reveal Secret') {
                    if (!passwordGate?.requestPassword) {
                        return null;
                    }

                    return passwordGate.requestPassword(contextLabel);
                }

                async function fetchRevealedSecretValue(revealUrl) {
                    if (!revealUrl) return null;
                    if (revealedSecrets.has(revealUrl)) return revealedSecrets.get(revealUrl);

                    const password = await requestSecretPassword('Reveal Secret');
                    if (!password) {
                        return null;
                    }

                    const response = await fetch(revealUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf(),
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ password }),
                    });

                    if (!response.ok) {
                        const errorJson = await response.json().catch(() => null);
                        const passwordMessage = errorJson?.errors?.password?.[0];
                        const fallbackMessage = errorJson?.message || `Failed to reveal secret (${response.status})`;
                        throw new Error(passwordMessage || fallbackMessage);
                    }

                    const data = await response.json();
                    const value = data?.value ?? null;
                    revealedSecrets.set(revealUrl, value);

                    return value;
                }

                async function fetchRevealedSshKeyPair(revealUrl) {
                    if (!revealUrl) return null;
                    const password = await requestSecretPassword('Reveal SSH Key Pair');

                    if (!password) {
                        return null;
                    }

                    const response = await fetch(revealUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf(),
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ password }),
                    });

                    if (!response.ok) {
                        const errorJson = await response.json().catch(() => null);
                        const passwordMessage = errorJson?.errors?.password?.[0];
                        const fallbackMessage = errorJson?.message || `Failed to reveal SSH key pair (${response.status})`;
                        throw new Error(passwordMessage || fallbackMessage);
                    }

                    const data = await response.json();
                    revealedSshKeyPair = {
                        publicKey: data?.public_key ?? '',
                        privateKey: data?.private_key ?? '',
                        authorizeCommand: data?.authorize_command ?? '',
                    };

                    return revealedSshKeyPair;
                }

                const revealAccessKeySecretBtn = document.getElementById('revealAccessKeySecretBtn');
                if (revealAccessKeySecretBtn) {
                    let accessKeySecretRevealed = false;
                    const valueField = document.getElementById('accessKeySecretValue');
                    const copyBtn = document.getElementById('copyAccessKeySecretBtn');

                    // Attach copy listener once (outside reveal handler to prevent duplicates)
                    if (copyBtn) {
                        copyBtn.addEventListener('click', async () => {
                            const val = valueField?.value || '';
                            if (val) {
                                await navigator.clipboard.writeText(val);
                                if (window.ToastSystem) {
                                    window.ToastSystem.show({ type: 'success', message: 'Secret key copied to clipboard' });
                                }
                            }
                        });
                    }

                    revealAccessKeySecretBtn.addEventListener('click', async function () {
                        const fieldWrapper = document.getElementById('accessKeySecretField');

                        // If already revealed, just toggle field visibility
                        if (accessKeySecretRevealed) {
                            const isHidden = fieldWrapper?.classList.contains('d-none');
                            if (isHidden) {
                                fieldWrapper?.classList.remove('d-none');
                                this.innerHTML = '<i class="ri-eye-off-line me-1"></i>Hide';
                                if (copyBtn) { copyBtn.style.display = ''; }
                            } else {
                                fieldWrapper?.classList.add('d-none');
                                this.innerHTML = '<i class="ri-eye-line me-1"></i>Reveal';
                                if (copyBtn) { copyBtn.style.display = 'none'; }
                            }
                            return;
                        }

                        try {
                            const revealUrl = this.dataset.revealUrl;
                            const value = await fetchRevealedSecretValue(revealUrl);

                            if (value === null) {
                                return;
                            }

                            if (valueField) {
                                valueField.value = value;
                            }

                            if (fieldWrapper) {
                                fieldWrapper.classList.remove('d-none');
                            }

                            if (copyBtn) {
                                copyBtn.style.display = '';
                            }

                            accessKeySecretRevealed = true;
                            this.innerHTML = '<i class="ri-eye-off-line me-1"></i>Hide';

                            if (window.ToastSystem) {
                                window.ToastSystem.show({ type: 'success', message: 'Secret key revealed successfully' });
                            }
                        } catch (error) {
                            console.error('Error revealing secret key:', error);
                            if (window.ToastSystem) {
                                window.ToastSystem.show({ type: 'error', message: error.message || 'Failed to reveal secret key' });
                            }
                        }
                    });
                }

                const revealSshPairBtn = document.getElementById('revealSshPairBtn');
                if (revealSshPairBtn) {
                    revealSshPairBtn.addEventListener('click', async function () {
                        try {
                            const revealUrl = this.dataset.revealUrl;
                            const sshPair = await fetchRevealedSshKeyPair(revealUrl);

                            if (!sshPair) {
                                return;
                            }

                            const publicField = document.getElementById('sshPublicKeyField');
                            const authorizeCommandField = document.getElementById('sshAuthorizeCommandField');
                            const privateField = document.getElementById('sshPrivateKeyField');
                            const details = document.getElementById('sshPairDetails');

                            if (publicField) {
                                publicField.value = sshPair.publicKey || '';
                            }

                            if (privateField) {
                                privateField.value = sshPair.privateKey || '';
                            }

                            if (authorizeCommandField) {
                                authorizeCommandField.value = sshPair.authorizeCommand || '';
                            }

                            if (details) {
                                details.classList.remove('d-none');
                            }

                            if (window.ToastSystem) {
                                window.ToastSystem.show({ type: 'success', message: 'SSH key pair revealed successfully' });
                            }
                        } catch (error) {
                            console.error('Error revealing SSH key pair:', error);
                            if (window.ToastSystem) {
                                window.ToastSystem.show({ type: 'error', message: error.message || 'Failed to reveal SSH key pair' });
                            }
                        }
                    });
                }

                // Password toggle functionality for secrets tab
                document.querySelectorAll('.toggle-password').forEach(btn => {
                    if (btn.dataset.bound === '1') return;
                    btn.dataset.bound = '1';
                    btn.addEventListener('click', async function() {
                        const row = this.closest('td');
                        const field = row.querySelector('.password-field');
                        const icon = this.querySelector('i');

                        const container = this.closest('[data-reveal-url]') || row.querySelector('[data-reveal-url]');
                        const revealUrl = container?.dataset?.revealUrl;

                        if (field.dataset.hidden === 'true') {
                            try {
                                const secretValue = await fetchRevealedSecretValue(revealUrl);
                                if (secretValue === null) {
                                    return;
                                }

                                field.textContent = secretValue ?? 'N/A';
                                field.dataset.hidden = 'false';
                                icon.className = 'ri-eye-off-line fs-5';
                            } catch (error) {
                                console.error('Error revealing secret:', error);
                                if (window.ToastSystem) {
                                    window.ToastSystem.show({ type: 'error', message: 'Failed to reveal secret' });
                                }
                            }
                        } else {
                            field.textContent = '••••••••';
                            field.dataset.hidden = 'true';
                            icon.className = 'ri-eye-line fs-5';
                        }
                    });
                });

                // Copy button functionality for secrets tab
                document.querySelectorAll('.copy-btn').forEach(btn => {
                    if (btn.dataset.bound === '1') return;
                    btn.dataset.bound = '1';
                    btn.addEventListener('click', async function() {
                        let value = this.getAttribute('data-copy-value');
                        const icon = this.querySelector('i');

                        if (!value && this.dataset.copyTarget) {
                            const field = document.querySelector(this.dataset.copyTarget);
                            value = field ? (field.value || field.textContent || '') : '';
                        }

                        // Password copy uses on-demand reveal endpoint
                        if (!value && this.dataset.revealUrl) {
                            try {
                                value = await fetchRevealedSecretValue(this.dataset.revealUrl);
                            } catch (error) {
                                console.error('Error revealing secret:', error);
                                if (window.ToastSystem) {
                                    window.ToastSystem.show({ type: 'error', message: 'Failed to copy secret' });
                                }
                                return;
                            }
                        }

                        if (value) {
                            navigator.clipboard.writeText(value).then(() => {
                                icon.className = 'ri-check-line fs-5 text-success';
                                setTimeout(() => {
                                    icon.className = 'ri-file-copy-line fs-5';
                                }, 1500);

                                if (window.ToastSystem) {
                                    window.ToastSystem.show({ type: 'success', message: 'Copied to clipboard!', delay: 3000 });
                                }
                            }).catch(err => {
                                console.error('Failed to copy:', err);
                                if (window.ToastSystem) {
                                    window.ToastSystem.show({ type: 'error', message: 'Failed to copy' });
                                }
                            });
                        }
                    });
                });
            })();
        </script>
    @endpush

    <x-security.secret-password-gate />

    {{-- Manage Agencies Modal --}}
    @can('edit_servers')
    <div class="modal fade" id="manageAgenciesModal" tabindex="-1" aria-labelledby="manageAgenciesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="manageAgenciesModalLabel">Manage Server Agencies</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="manageAgenciesForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="agency_ids" class="form-label">Select Agencies</label>
                            <select class="form-select" id="agency_ids" name="agency_ids[]" multiple size="8">
                                @foreach(\Modules\Platform\Models\Agency::orderBy('name')->get() as $agency)
                                    <option value="{{ $agency->id }}"
                                        {{ $server->agencies->contains($agency->id) ? 'selected' : '' }}>
                                        {{ $agency->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple agencies</small>
                        </div>

                        <div class="mb-3">
                            <label for="primary_agency_id" class="form-label">Primary Agency</label>
                            <select class="form-select" id="primary_agency_id" name="primary_agency_id">
                                <option value="">None</option>
                                @foreach(\Modules\Platform\Models\Agency::orderBy('name')->get() as $agency)
                                    <option value="{{ $agency->id }}"
                                        {{ $server->agencies->contains(function($a) use ($agency) { return $a->id === $agency->id && $a->pivot->is_primary; }) ? 'selected' : '' }}>
                                        {{ $agency->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Primary agency must be selected in the agencies list above</small>
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

    {{-- Optimization Tool Modal --}}
    @if($server->status === 'active')
        @include('platform::servers._optimization-tool-modal')
    @endif
</x-app-layout>
