<x-app-layout :title="$page_title">
    @php
        $isTrashed = !empty($website->deleted_at);
        $isActive = $website->status === \Modules\Platform\Enums\WebsiteStatus::Active;
        $isProvisioning = $website->status === \Modules\Platform\Enums\WebsiteStatus::Provisioning;
        $isSuspended = $website->status === \Modules\Platform\Enums\WebsiteStatus::Suspended;
        $isExpired = $website->status === \Modules\Platform\Enums\WebsiteStatus::Expired;
        $isFailed = $website->status === \Modules\Platform\Enums\WebsiteStatus::Failed;
        $isDeleted = $website->status === \Modules\Platform\Enums\WebsiteStatus::Deleted;
        $hasUpdate = $website->hasUpdateAvailable();

        // Status configuration
        $statusConfig = match($website->status) {
            \Modules\Platform\Enums\WebsiteStatus::Active => ['icon' => 'ri-checkbox-circle-fill', 'color' => 'success', 'bg' => 'success-subtle'],
            \Modules\Platform\Enums\WebsiteStatus::Suspended => ['icon' => 'ri-pause-circle-fill', 'color' => 'warning', 'bg' => 'warning-subtle'],
            \Modules\Platform\Enums\WebsiteStatus::Expired => ['icon' => 'ri-time-fill', 'color' => 'danger', 'bg' => 'danger-subtle'],
            \Modules\Platform\Enums\WebsiteStatus::Failed => ['icon' => 'ri-error-warning-fill', 'color' => 'white', 'bg' => 'danger'],
            \Modules\Platform\Enums\WebsiteStatus::Trash => ['icon' => 'ri-delete-bin-line', 'color' => 'white', 'bg' => 'danger'],
            \Modules\Platform\Enums\WebsiteStatus::Deleted => ['icon' => 'ri-server-line', 'color' => 'white', 'bg' => 'danger'],
            \Modules\Platform\Enums\WebsiteStatus::Provisioning => ['icon' => 'ri-loader-4-fill', 'color' => 'info', 'bg' => 'info-subtle'],
            default => ['icon' => 'ri-question-fill', 'color' => 'secondary', 'bg' => 'secondary-subtle'],
        };

        // Determine default active tab - show provision tab for provisioning/failed status
        $defaultTab = ($isProvisioning || $isFailed) ? 'provision' : 'general';
    @endphp

    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Websites', 'href' => route('platform.websites.index', 'all')],
            ['label' => '#' . $website->id, 'active' => true],
        ];

        $actions = [];

        if ($isActive && $website->admin_slug) {
            $actions[] = [
                'type' => 'link',
                'label' => 'Open Admin',
                'icon' => 'ri-external-link-line',
                'href' => 'https://' . $website->domain . '/' . $website->admin_slug,
                'target' => '_blank',
                'variant' => 'btn-outline-primary'
            ];
        }

        if ($isFailed && auth()->user()->can('edit_websites')) {
            $actions[] = [
                'type' => 'link',
                'label' => 'Retry Provision',
                'icon' => 'ri-refresh-line',
                'href' => route('platform.websites.retry-provision', $website->id),
                'class' => 'confirmation-btn',
                'variant' => 'btn-warning',
                'attributes' => [
                    'data-title' => 'Retry Provisioning',
                    'data-method' => 'POST',
                    'data-message' => 'This will retry the provisioning process for this website. Failed and pending steps will be retried.',
                    'data-confirmButtonText' => 'Retry',
                    'data-confirmButtonClass' => 'btn-warning',
                    'data-loaderButtonText' => 'Retrying...',
                    'data-redirect' => route('platform.websites.show', $website->id),
                ]
            ];
        }

        if (auth()->user()->can('edit_websites')) {
             $actions[] = [
                'type' => 'link',
                'label' => 'Edit',
                'icon' => 'ri-pencil-line',
                'href' => route('platform.websites.edit', $website->id),
                'variant' => 'btn-outline-secondary'
            ];

            $moreActions = [];

            if (!$isTrashed) {
                 $moreActions[] = [
                    'label' => 'Delete Website',
                    'icon' => 'ri-delete-bin-line',
                    'href' => route('platform.websites.destroy', $website->id),
                    'class' => 'text-danger confirmation-btn',
                     'attributes' => [
                        'data-title' => 'Trash Website',
                        'data-method' => 'DELETE',
                        'data-message' => 'Are you sure you want to trash this website?',
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
            'href' => route('platform.websites.index', 'all'),
            'variant' => 'btn-outline-secondary'
        ];
    @endphp

    <x-page-header :breadcrumbs="$breadcrumbs" :actions="$actions" title="{{ $website->name }}">
        <x-slot:custom_title>
            <div class="d-flex align-items-center gap-3">
                <span class="h3 mb-0">{{ $website->name }}</span>
                <span class="badge bg-{{ $statusConfig['bg'] }} text-{{ $statusConfig['color'] }} fs-6">
                    <i class="{{ $statusConfig['icon'] }} me-1"></i>{{ $website->status->label() }}
                </span>
            </div>
        </x-slot:custom_title>
        <x-slot:description>
            Manage your website configuration, settings, and provisioning status.
        </x-slot:description>
    </x-page-header>

    {{-- Update Available Banner --}}
    @if($hasUpdate && $isActive)
        <div class="alert alert-primary d-flex align-items-center justify-content-between mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="d-flex align-items-center text-white">
                <div class="d-flex align-items-center justify-content-center bg-white bg-opacity-25 me-3" style="width: 48px; height: 48px; border-radius: 50%;">
                    <i class="ri-download-cloud-2-line fs-4"></i>
                </div>
                <div>
                    <div class="fw-semibold">Update Available</div>
                    <div class="small opacity-75">
                        v{{ $website->astero_version }} → v{{ $website->server_version }}
                    </div>
                </div>
            </div>
            <a class="btn btn-light confirmation-btn"
                data-title="Update Website"
                data-method="POST"
                data-message="Update from v{{ $website->astero_version }} to v{{ $website->server_version }}? This may take a few minutes."
                data-confirmButtonText="Update Now"
                data-loaderButtonText="Updating..."
                href="{{ route('platform.websites.update-version', $website->id) }}">
                <i class="ri-refresh-line me-1"></i>Update Now
            </a>
        </div>
    @endif

    {{-- Trashed Warning Banner --}}
    @if($isTrashed && !$isDeleted)
        <div class="alert alert-danger d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-3 mb-4">
            <div class="d-flex align-items-center">
                <i class="ri-delete-bin-line fs-4 me-3 flex-shrink-0"></i>
                <div>
                    <div class="fw-semibold">This website is in trash</div>
                    <div class="small">Restore it to make changes or remove from server.</div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @can('restore_websites')
                    <a class="btn btn-success confirmation-btn flex-grow-1 flex-md-grow-0"
                        data-title="Restore Website"
                        data-method="PATCH"
                        data-message="Restore this website from trash?"
                        data-confirmButtonText="Restore"
                        data-loaderButtonText="Restoring..."
                        href="{{ route('platform.websites.restore', $website->id) }}">
                        <i class="ri-arrow-go-back-line me-1"></i>Restore
                    </a>
                @endcan
                @can('delete_websites')
                    <a class="btn btn-outline-danger confirmation-btn flex-grow-1 flex-md-grow-0"
                        data-title="Remove from Server"
                        data-method="POST"
                        data-message="⚠️ This will delete the Hestia user, files and database from the server. The website record will be kept for historical tracking."
                        data-confirmButtonText="Remove from Server"
                        data-loaderButtonText="Removing..."
                        href="{{ route('platform.websites.remove-from-server', $website->id) }}">
                        <i class="ri-server-line me-1"></i>Remove from Server
                    </a>
                    <a class="btn btn-danger confirmation-btn flex-grow-1 flex-md-grow-0"
                        data-title="Delete Permanently"
                        data-method="DELETE"
                        data-message="⚠️ This will permanently delete the website record and cannot be undone. Make sure the server data has been removed first."
                        data-confirmButtonText="Delete Permanently"
                        data-loaderButtonText="Deleting..."
                        href="{{ route('platform.websites.force-delete', $website->id) }}">
                        <i class="ri-delete-bin-fill me-1"></i>Delete Permanently
                    </a>
                @endcan
            </div>
        </div>
    @endif

    {{-- Deleted (Server Removed) Warning Banner --}}
    @if($isDeleted)
        <div class="alert alert-danger d-flex align-items-center justify-content-between mb-4">
            <div class="d-flex align-items-center">
                <i class="ri-server-line fs-4 me-3"></i>
                <div>
                    <div class="fw-semibold">Server data has been removed</div>
                    <div class="small">This website's server files have been deleted. You can re-provision it or permanently delete the record.</div>
                </div>
            </div>
            <div class="d-flex gap-2">
                @can('edit_websites')
                    <a class="btn btn-primary confirmation-btn"
                        data-title="Re-provision Website"
                        data-method="POST"
                        data-message="Create a fresh Hestia user and server files for this website? This will start the provisioning process again."
                        data-confirmButtonText="Re-provision"
                        data-loaderButtonText="Starting..."
                        href="{{ route('platform.websites.reprovision', $website->id) }}">
                        <i class="ri-refresh-line me-1"></i>Re-provision
                    </a>
                @endcan
                @can('delete_websites')
                    <a class="btn btn-danger confirmation-btn"
                        data-title="Delete Permanently"
                        data-method="DELETE"
                        data-message="⚠️ This will permanently delete this website record from the database. This cannot be undone!"
                        data-confirmButtonText="Delete Forever"
                        data-loaderButtonText="Deleting..."
                        href="{{ route('platform.websites.force-delete', $website->id) }}">
                        <i class="ri-delete-bin-7-line me-1"></i>Delete Forever
                    </a>
                @endcan
            </div>
        </div>
    @endif

    <style>
        .website-command-center .ws-panel {
            border-color: rgba(var(--bs-secondary-rgb), 0.18);
        }

        .ws-hero {
            background: radial-gradient(circle at 0 0, rgba(var(--bs-primary-rgb), 0.1), transparent 42%),
                radial-gradient(circle at 100% 0, rgba(var(--bs-info-rgb), 0.08), transparent 48%);
        }

        .ws-health-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            padding: 0.3rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid rgba(var(--bs-secondary-rgb), 0.2);
        }

        .ws-metric-box {
            border: 1px solid rgba(var(--bs-secondary-rgb), 0.18);
            border-radius: 0.65rem;
            padding: 0.75rem;
            background-color: rgba(var(--bs-light-rgb), 0.65);
        }

        .ws-metric-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--bs-secondary-color);
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .ws-metric-value {
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .ws-action-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem;
        }

        .ws-action-grid .btn {
            justify-content: center;
        }

        .ws-ops-divider {
            border-top: 1px dashed rgba(var(--bs-secondary-rgb), 0.3);
            margin-top: 0.25rem;
            padding-top: 0.9rem;
        }

        .ws-ops-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .ws-ops-scale-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.35rem;
        }

        .ws-queue-meter {
            width: 96px;
            height: 4px;
        }

    </style>

    @php
        $diskUsageBytes = (int) $website->getMetadata('disk_usage_bytes', 0);
        $diskUsageFormatted = $diskUsageBytes > 0
            ? \Illuminate\Support\Number::fileSize($diskUsageBytes, precision: 2)
            : '--';

        $lastSyncedAt = $website->getMetadata('last_synced_at');

        $queueStatus = (string) $website->getMetadata('queue_worker_status', 'unknown');
        $queueRunning = (int) $website->getMetadata('queue_worker_running_count', 0);
        $queueTotal = (int) $website->getMetadata('queue_worker_total_count', 0);
        $queueShowCounts = $queueTotal > 0 && ! in_array($queueStatus, ['not_configured', 'not_installed'], true);
        $queuePercent = $queueTotal > 0 ? min(100, (int) round(($queueRunning / $queueTotal) * 100)) : 0;
        $queueConfig = match($queueStatus) {
            'running' => ['class' => 'bg-success-subtle text-success', 'icon' => 'ri-checkbox-circle-fill'],
            'degraded' => ['class' => 'bg-warning-subtle text-warning', 'icon' => 'ri-loader-4-line', 'label' => 'Degraded'],
            'starting' => ['class' => 'bg-info-subtle text-info', 'icon' => 'ri-loader-4-line', 'label' => 'Starting'],
            'stopped' => ['class' => 'bg-warning-subtle text-warning', 'icon' => 'ri-pause-circle-fill'],
            'error' => ['class' => 'bg-danger-subtle text-danger', 'icon' => 'ri-error-warning-fill'],
            'not_running' => ['class' => 'bg-warning-subtle text-warning', 'icon' => 'ri-pause-circle-fill', 'label' => 'Not Running'],
            'not_configured' => ['class' => 'bg-secondary-subtle text-secondary', 'icon' => 'ri-settings-3-line', 'label' => 'Not Configured'],
            'not_installed' => ['class' => 'bg-danger-subtle text-danger', 'icon' => 'ri-alert-fill', 'label' => 'Not Installed'],
            'unknown' => ['class' => 'bg-secondary-subtle text-secondary', 'icon' => 'ri-question-line', 'label' => 'Unknown'],
            default => ['class' => 'bg-secondary-subtle text-secondary', 'icon' => 'ri-question-line', 'label' => ucfirst(str_replace('_', ' ', $queueStatus))],
        };
        $queueLabel = $queueConfig['label'] ?? ucfirst(str_replace('_', ' ', $queueStatus));

        $cronStatus = (string) $website->getMetadata('cron_status', 'unknown');
        $cronConfig = match($cronStatus) {
            'active' => ['class' => 'bg-success-subtle text-success', 'icon' => 'ri-checkbox-circle-fill', 'label' => 'Active'],
            'suspended' => ['class' => 'bg-warning-subtle text-warning', 'icon' => 'ri-pause-circle-fill', 'label' => 'Suspended'],
            'not_configured' => ['class' => 'bg-secondary-subtle text-secondary', 'icon' => 'ri-settings-3-line', 'label' => 'Not Configured'],
            default => ['class' => 'bg-secondary-subtle text-secondary', 'icon' => 'ri-question-line', 'label' => ucfirst(str_replace('_', ' ', $cronStatus))],
        };
    @endphp

    <div class="website-command-center mb-4">
        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card ws-panel ws-hero h-100 border">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <div class="small text-muted text-uppercase fw-semibold mb-2">Command Center</div>
                                <div class="h4 mb-1">{{ $website->name }}</div>
                                <a href="https://{{ $website->domain }}" target="_blank" class="text-decoration-none fw-semibold">
                                    {{ $website->domain }}
                                </a>
                            </div>
                            <div class="text-end">
                                <div class="small text-muted">Website ID</div>
                                <div class="fw-semibold font-monospace">{{ $website->uid }}</div>
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
                                <div class="ws-metric-box h-100">
                                    <div class="ws-metric-label">Plan</div>
                                    <div class="ws-metric-value">{{ config("astero.website_plans.{$website->plan}.label", ucfirst($website->plan ?? '--')) }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="ws-metric-box h-100">
                                    <div class="ws-metric-label">Type</div>
                                    <div class="ws-metric-value">{!! strip_tags($website->type_badge) !!}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="ws-metric-box h-100">
                                    <div class="ws-metric-label">Disk</div>
                                    <div class="ws-metric-value">{{ $diskUsageFormatted }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="ws-metric-box h-100">
                                    <div class="ws-metric-label">Astero</div>
                                    <div class="ws-metric-value">
                                        @if($website->astero_version)
                                            v{{ $website->astero_version }}
                                        @else
                                            --
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="ws-health-chip {{ $queueConfig['class'] }}">
                                <i class="{{ $queueConfig['icon'] }}"></i>
                                Queue: {{ $queueLabel }}@if($queueShowCounts) ({{ $queueRunning }}/{{ $queueTotal }})@endif
                            </span>
                            <span class="ws-health-chip {{ $cronConfig['class'] }}">
                                <i class="{{ $cronConfig['icon'] }}"></i>
                                Cron: {{ $cronConfig['label'] }}
                            </span>
                            <span class="ws-health-chip {{ $isActive ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">
                                <i class="{{ $statusConfig['icon'] }}"></i>
                                {{ $website->status->label() }}
                            </span>
                        </div>

                        <div class="row g-3 small">
                            <div class="col-md-6">
                                <div class="text-muted text-uppercase fw-semibold small mb-2">Infrastructure</div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Server</span>
                                    <span class="fw-semibold">
                                        @if($website->server)
                                            <a href="{{ route('platform.servers.show', $website->server->id) }}" target="_blank" class="text-decoration-none">
                                                {{ $website->server->name ?? $website->server->fqdn ?? '--' }}
                                            </a>
                                        @else
                                            --
                                        @endif
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">IP</span>
                                    <span class="fw-semibold font-monospace">{{ $website->server?->ip ?? '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">DNS</span>
                                    <span class="fw-semibold">{{ $website->dnsProvider?->name ?? '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">CDN</span>
                                    <span class="fw-semibold">{{ $website->cdnProvider?->name ?? '--' }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted text-uppercase fw-semibold small mb-2">Ownership</div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Customer</span>
                                    <span class="fw-semibold">
                                        @if($website->customer_info)
                                            {{ $website->customer_info['name'] ?? $website->customer_info['email'] ?? $website->customer_info['ref'] ?? 'Unknown' }}
                                        @else
                                            Not assigned
                                        @endif
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Agency</span>
                                    <span class="fw-semibold">
                                        @if($website->agency)
                                            <a href="{{ route('platform.agencies.show', $website->agency->id) }}" target="_blank" class="text-decoration-none">
                                                {{ $website->agency->name }}
                                            </a>
                                        @else
                                            Not assigned
                                        @endif
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Pull Zone ID</span>
                                    <span class="fw-semibold font-monospace">{{ $pullzoneId ?? '--' }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Expiry</span>
                                    <span class="fw-semibold">
                                        @if($website->expired_on)
                                            {{ $website->expired_on->format('M d, Y') }}
                                        @else
                                            --
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card ws-panel h-100 border">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-flashlight-line me-1"></i> Operations
                        </h5>
                    </div>
                    <div class="card-body d-flex flex-column gap-3">
                        <div class="small text-muted">
                            High-impact actions for sync, state transitions, and lifecycle operations.
                        </div>

                        @if(!$isTrashed)
                            <div class="ws-action-grid">
                                @can('edit_websites')
                                    @if($website->server_id)
                                        <a class="btn btn-sm btn-outline-info confirmation-btn"
                                            data-title="Sync Website"
                                            data-method="POST"
                                            data-message="Fetch latest information from server?"
                                            data-confirmButtonText="Sync"
                                            data-loaderButtonText="Syncing..."
                                            href="{{ route('platform.websites.sync-website', $website->id) }}">
                                            <i class="ri-refresh-line me-1"></i>Sync
                                        </a>
                                    @endif

                                    @if($isActive && $website->server_id)
                                        <a class="btn btn-sm btn-outline-primary confirmation-btn"
                                            data-title="Recache Application"
                                            data-method="POST"
                                            data-message="Run astero:recache on this website? This clears and rebuilds app caches."
                                            data-confirmButtonText="Recache"
                                            data-loaderButtonText="Recaching..."
                                            href="{{ route('platform.websites.recache-application', $website->id) }}">
                                            <i class="ri-database-2-line me-1"></i>Recache
                                        </a>
                                    @endif
                                @endcan

                                @if($isActive && $website->admin_slug)
                                    <a class="btn btn-sm btn-outline-primary"
                                        target="_blank"
                                        href="https://{{ $website->domain }}/{{ $website->admin_slug }}">
                                        <i class="ri-external-link-line me-1"></i>Admin
                                    </a>
                                @endif

                                @can('change_websites_status')
                                    @if($isSuspended || $isExpired || $isProvisioning)
                                        <a class="btn btn-sm btn-success confirmation-btn"
                                            data-title="Activate Website"
                                            data-method="POST"
                                            data-message="Activate this website and make it publicly accessible?"
                                            data-confirmButtonText="Activate"
                                            data-loaderButtonText="Activating..."
                                            href="{{ route('platform.websites.update-status', [$website->id, 'active']) }}">
                                            <i class="ri-play-circle-line me-1"></i>Activate
                                        </a>
                                    @endif

                                    @if($isActive)
                                        <a class="btn btn-sm btn-outline-warning confirmation-btn"
                                            data-title="Suspend Website"
                                            data-method="POST"
                                            data-message="Suspend this website? It will become temporarily inaccessible."
                                            data-confirmButtonText="Suspend"
                                            data-loaderButtonText="Suspending..."
                                            href="{{ route('platform.websites.update-status', [$website->id, 'suspended']) }}">
                                            <i class="ri-pause-circle-line me-1"></i>Suspend
                                        </a>
                                    @endif

                                    @if($isActive || $isSuspended)
                                        <a class="btn btn-sm btn-outline-secondary confirmation-btn"
                                            data-title="Expire Website"
                                            data-method="POST"
                                            data-message="Mark this website as expired?"
                                            data-confirmButtonText="Expire"
                                            data-loaderButtonText="Expiring..."
                                            href="{{ route('platform.websites.update-status', [$website->id, 'expired']) }}">
                                            <i class="ri-time-line me-1"></i>Expire
                                        </a>
                                    @endif
                                @endcan

                                @can('delete_websites')
                                    <a class="btn btn-sm btn-outline-danger confirmation-btn"
                                        data-title="Move to Trash"
                                        data-method="DELETE"
                                        data-message="Move this website to trash? You can restore it later."
                                        data-confirmButtonText="Move to Trash"
                                        data-loaderButtonText="Moving..."
                                        href="{{ route('platform.websites.destroy', $website->id) }}">
                                        <i class="ri-delete-bin-line me-1"></i>Trash
                                    </a>
                                @endcan
                            </div>
                        @else
                            <div class="alert alert-warning mb-0 small">
                                <i class="ri-alert-line me-1"></i>
                                This website is in trash mode. Use restore/remove actions from the warning banner.
                            </div>
                        @endif

                        @if($hasUpdate && $isActive)
                            <a class="btn btn-primary confirmation-btn"
                                data-title="Update Website"
                                data-method="POST"
                                data-message="Update from v{{ $website->astero_version }} to v{{ $website->server_version }}? This may take a few minutes."
                                data-confirmButtonText="Update Now"
                                data-loaderButtonText="Updating..."
                                href="{{ route('platform.websites.update-version', $website->id) }}">
                                <i class="ri-download-cloud-2-line me-1"></i>Update to v{{ $website->server_version }}
                            </a>
                        @endif
                        <div class="ws-ops-divider">
                            <div class="small text-muted text-uppercase fw-semibold mb-2">Queue Workers</div>
                            <div class="ws-ops-row mb-2">
                                <span class="small text-muted">Current status</span>
                                <span class="badge {{ $queueConfig['class'] }}">
                                    <i class="{{ $queueConfig['icon'] }} me-1"></i>{{ $queueLabel }}@if($queueShowCounts) ({{ $queueRunning }}/{{ $queueTotal }})@endif
                                </span>
                            </div>
                            @if($queueShowCounts)
                                <div class="progress ws-queue-meter ms-auto mb-2" role="progressbar" aria-label="Queue worker health" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $queuePercent }}">
                                    <div class="progress-bar {{ $queueRunning === $queueTotal ? 'bg-success' : 'bg-warning' }}" style="width: {{ $queuePercent }}%;"></div>
                                </div>
                            @endif

                            @if($queueStatus === 'not_configured' && $isActive)
                                <a class="btn btn-sm btn-outline-primary w-100 confirmation-btn"
                                    data-title="Setup Queue Workers"
                                    data-method="POST"
                                    data-message="Setup Supervisor queue workers for this website?"
                                    data-confirmButtonText="Setup"
                                    data-loaderButtonText="Setting up..."
                                    href="{{ route('platform.websites.setup-queue-worker', $website->id) }}">
                                    <i class="ri-add-line me-1"></i>Setup Queue Workers
                                </a>
                            @elseif($isActive && !in_array($queueStatus, ['not_configured', 'not_installed'], true) && $website->server_id)
                                <div class="small text-muted mb-2">Scale workers</div>
                                <div class="ws-ops-scale-grid">
                                    @foreach([1, 2, 3, 4] as $count)
                                        <a class="btn btn-sm {{ $queueTotal == $count ? 'btn-primary' : 'btn-outline-secondary' }} confirmation-btn"
                                            data-title="Scale Queue Workers"
                                            data-method="POST"
                                            data-message="Change queue workers from {{ $queueTotal }} to {{ $count }}?"
                                            data-confirmButtonText="Scale"
                                            data-loaderButtonText="Scaling..."
                                            href="{{ route('platform.websites.scale-queue-worker', ['website' => $website->id, 'count' => $count]) }}">
                                            {{ $count }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs Section --}}
    @php
        $activeTab = request()->query('section', $defaultTab);
        $secretsCount = isset($secrets) ? count($secrets) : 0;

        $tabs = [
            ['name' => 'general', 'label' => 'General', 'icon' => 'ri-information-line'],
        ];

        if ($secretsCount > 0) {
            $tabs[] = ['name' => 'secrets', 'label' => 'Secrets', 'icon' => 'ri-key-2-line', 'count' => $secretsCount];
        }

        $tabs = array_merge($tabs, [
            ['name' => 'provision', 'label' => 'Provision', 'icon' => 'ri-list-check-2'],
            ['name' => 'updates', 'label' => 'Updates', 'icon' => 'ri-history-line'],
            ['name' => 'notes', 'label' => 'Notes', 'icon' => 'ri-sticky-note-line'],
            ['name' => 'metadata', 'label' => 'Metadata', 'icon' => 'ri-code-s-slash-line'],
            ['name' => 'activity', 'label' => 'Activity', 'icon' => 'ri-pulse-line'],
        ]);
    @endphp

    <x-tabs param="section" :active="$activeTab" :tabs="$tabs" class="border shadow-none">
        <x-slot:general>
            <div class="row">
                                <div class="col-md-6 mb-3">
                            <h6 class="text-muted text-uppercase small fw-semibold mb-3">Business Niches</h6>
                            <div class="d-flex flex-wrap gap-2">
                                @if(!empty($website->niches))
                                    @foreach($website->getNichesLabels() as $nicheLabel)
                                        <span class="badge bg-primary-subtle text-primary">{{ $nicheLabel }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted">--</span>
                                @endif
                            </div>
                                </div>
                                <div class="col-md-6 mb-3">
                            <h6 class="text-muted text-uppercase small fw-semibold mb-3">Timestamps</h6>
                                            <div class="d-flex flex-column gap-2">
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-muted">Created</span>
                                    <span class="fw-medium">{{ $website->created_at?->format('M d, Y') ?? '--' }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Updated</span>
                                    <span class="fw-medium">{{ $website->updated_at?->diffForHumans() ?? '--' }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-muted">Last Synced</span>
                                    <span class="fw-medium">
                                                        @if($website->getMetadata('last_synced_at'))
                                                            {{ \Carbon\Carbon::parse($website->getMetadata('last_synced_at'))->diffForHumans() }}
                                                        @else
                                                            <span class="text-muted">Never</span>
                                                        @endif
                                                    </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
        </x-slot:general>


        {{-- Secrets Tab --}}
        @if(isset($secrets) && count($secrets) > 0)
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
                                                <div class="d-flex align-items-center gap-2" data-secret-id="{{ $secret->id }}" data-reveal-url="{{ route('platform.websites.secrets.reveal', ['website' => $website->id, 'secret' => $secret->id]) }}">
                                                    <code class="bg-light px-2 py-1 rounded small password-field" data-hidden="true">••••••••</code>
                                                    <button class="btn btn-sm btn-outline-secondary p-1 toggle-password text-decoration-none" title="Show/Hide">
                                                        <i class="ri-eye-line fs-5"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary p-1 copy-btn text-decoration-none" title="Copy password" data-secret-id="{{ $secret->id }}" data-reveal-url="{{ route('platform.websites.secrets.reveal', ['website' => $website->id, 'secret' => $secret->id]) }}">
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

        {{-- Provision Tab --}}
        <x-slot:provision>
                <div
                            x-data="{
                                loading: false,
                                stepLoading: {},
                                polling: false,
                                progress: 0,
                                stepConfigs: {{ json_encode($website_steps) }},
                                stepData: {{ json_encode($website_steps_data) }},
                                status: '{{ $website->status->value }}',
                                routes: {
                                    execute: '{{ route('platform.websites.execute.step', ['website' => $website->id, 'step' => 'STEP_PLACEHOLDER']) }}',
                                    revert: '{{ route('platform.websites.revert.step', ['website' => $website->id, 'step' => 'STEP_PLACEHOLDER']) }}'
                                },
                                csrfToken: '{{ csrf_token() }}',
                                init() {
                                    this.calculateProgress();
                                    if (this.status === 'provisioning') {
                                        this.startPolling();
                                    }
                                },
                                reloadToSection() {
                                    const url = new URL(window.location.href);
                                    url.searchParams.set('section', 'provision');
                                    window.location.href = url.toString();
                                },
                                calculateProgress() {
                                    let total = Object.keys(this.stepConfigs).length;
                                    let done = 0;
                                    // stepData might be an object or array depending on PHP serialization/Alpine casting
                                    // Ensure we handle it correctly.
                                    Object.values(this.stepData).forEach(step => {
                                         if(step && step.status === 'done') done++;
                                    });
                                    this.progress = total > 0 ? Math.round((done / total) * 100) : 0;
                                },
                                startPolling() {
                                    this.polling = true;
                                    this.pollInterval = setInterval(() => {
                                        fetch('{{ route('platform.websites.show', $website->id) }}?json=1')
                                            .then(r => r.json())
                                            .then(data => {
                                                this.stepData = data.website_steps_data;
                                                this.progress = data.percentage;
                                                this.status = data.current_status;

                                                if (data.current_status !== 'provisioning') {
                                                    clearInterval(this.pollInterval);
                                                    this.polling = false;
                                                    window.location.reload();
                                                }
                                            });
                                    }, 3000);
                                },
                                getStatusConfig(status) {
                                     const configs = {
                                         'done': { bg: 'success-subtle', text: 'success', icon: 'ri-check-line' },
                                         'failed': { bg: 'danger-subtle', text: 'danger', icon: 'ri-close-line' },
                                         'reverted': { bg: 'info-subtle', text: 'info', icon: 'ri-arrow-go-back-line' },
                                         'pending': { bg: 'warning-subtle', text: 'warning', icon: 'ri-time-line' }
                                     };
                                     return configs[status] || configs['pending'];
                                },
                                formatDate(dateString) {
                                    if (!dateString) return '--';
                                    const date = new Date(dateString);
                                    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                                },
                                executeStep(url, stepKey) {
                                    this.stepLoading = { ...this.stepLoading, [stepKey]: true };
                                    fetch(url, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': this.csrfToken,
                                            'X-Requested-With': 'XMLHttpRequest'
                                        },
                                        body: JSON.stringify({})
                                    })
                                        .then(response => response.json())
                                        .then(data => {
                                            const isSuccess = data.status === 'success' || data.status === 1;
                                            if (window.ToastSystem) {
                                                window.ToastSystem.show({
                                                    type: data.toast_type || (isSuccess ? 'success' : 'error'),
                                                    message: data.message || 'Operation completed',
                                                });
                                            }
                                            // Always reload the section to show updated step status
                                            setTimeout(() => {
                                                this.reloadToSection();
                                            }, 1200);
                                        })
                                .catch(() => window.ToastSystem?.show({ type: 'error', message: 'Unexpected error' }))
                                .finally(() => this.stepLoading = { ...this.stepLoading, [stepKey]: false });
                                },
                                executeAll(url) {
                                    this.loading = true;
                                    fetch(url, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': this.csrfToken,
                                            'X-Requested-With': 'XMLHttpRequest'
                                        },
                                        body: JSON.stringify({})
                                    })
                                        .then(response => response.json())
                                        .then(data => {
                                            const isSuccess = data.status === 'success' || data.status === 1;
                                            if (window.ToastSystem) {
                                                window.ToastSystem.show({
                                                    type: data.toast_type || (isSuccess ? 'success' : 'error'),
                                                    message: data.message || 'Operation completed',
                                                });
                                            }
                                            // Always reload the section to show updated status
                                            setTimeout(() => this.reloadToSection(), 1200);
                                        })
                                .catch(() => window.ToastSystem?.show({ type: 'error', message: 'Unexpected error' }))
                                .finally(() => this.loading = false);
                                }
                            }">

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="mb-0">Provisioning Steps</h6>
                        <div>
                             <!-- Global Actions (Run All / Revert All) -->
                             <!-- Note: Accessing stepData properties dynamically needs care -->
                             <template x-if="stepData && stepData['create_user'] && stepData['create_user'].status == 'done'">
                                <button class="btn btn-sm btn-outline-warning" :disabled="loading"
                                    @click="executeAll('{{ route('platform.websites.revert.step', ['website' => $website->id, 'step' => 'all']) }}')">
                                    <template x-if="loading"><span class="spinner-border spinner-border-sm me-1"></span></template>
                                    <i class="ri-arrow-go-back-line me-1"></i>Revert All
                                </button>
                             </template>
                             <template x-if="!stepData || !stepData['create_user'] || stepData['create_user'].status != 'done'">
                                <button class="btn btn-sm btn-primary" :disabled="loading"
                                    @click="executeAll('{{ route('platform.websites.execute.step', ['website' => $website->id, 'step' => 'all']) }}')">
                                    <template x-if="loading"><span class="spinner-border spinner-border-sm me-1"></span></template>
                                    <i class="ri-play-circle-line me-1"></i>Run All
                                </button>
                             </template>
                        </div>
                    </div>

                    <!-- Progress Bar - Only show during provisioning -->
                    @if($isProvisioning)
                    <div class="progress mb-4" style="height: 10px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar"
                             :style="`width: ${progress}%`" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
                        </div>
                    </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 280px">Step</th>
                                    <th style="width: 100px">Status</th>
                                    <th>Message</th>
                                    <th style="width: 150px">Time</th>
                                    <th style="width: 80px" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(step, stepKey) in stepConfigs" :key="stepKey">
                                    <tr>
                                        <td>
                                            <div class="fw-medium" x-text="step.title"></div>
                                            <div class="text-muted small" x-text="step.info"></div>
                                        </td>
                                        <td>
                                            <template x-if="stepData && stepData[stepKey]">
                                                <span :class="`badge bg-${getStatusConfig(stepData[stepKey].status).bg} text-${getStatusConfig(stepData[stepKey].status).text}`">
                                                    <i :class="`${getStatusConfig(stepData[stepKey].status).icon} me-1`"></i>
                                                    <span x-text="stepData[stepKey].status.charAt(0).toUpperCase() + stepData[stepKey].status.slice(1)"></span>
                                                </span>
                                            </template>
                                            <template x-if="!stepData || !stepData[stepKey]">
                                                 <span class="badge bg-warning-subtle text-warning"><i class="ri-time-line me-1"></i>Pending</span>
                                            </template>
                                        </td>
                                        <td class="small text-muted" x-text="stepData && stepData[stepKey] ? stepData[stepKey].meta_value : ''"></td>
                                        <td class="small">
                                            <span x-text="stepData && stepData[stepKey] ? formatDate(stepData[stepKey].updated_at) : '--'"></span>
                                        </td>
                                        <td class="text-center">
                                            <template x-if="step.command">
                                                <div>
                                                    <template x-if="stepData && stepData[stepKey] && stepData[stepKey].status === 'done'">
                                                        <button class="btn btn-sm btn-outline-warning" title="Revert"
                                                            :disabled="stepLoading['revert-' + stepKey]"
                                                            @click="executeStep(routes.revert.replace('STEP_PLACEHOLDER', stepKey), 'revert-' + stepKey)">
                                                            <template x-if="stepLoading['revert-' + stepKey]">
                                                                <span class="spinner-border spinner-border-sm"></span>
                                                            </template>
                                                            <template x-if="!stepLoading['revert-' + stepKey]">
                                                                <i class="ri-arrow-go-back-line"></i>
                                                            </template>
                                                        </button>
                                                    </template>
                                                    <template x-if="!stepData || !stepData[stepKey] || stepData[stepKey].status !== 'done'">
                                                        <button class="btn btn-sm btn-outline-primary" title="Execute"
                                                            :disabled="stepLoading['exec-' + stepKey]"
                                                            @click="executeStep(routes.execute.replace('STEP_PLACEHOLDER', stepKey), 'exec-' + stepKey)">
                                                            <template x-if="stepLoading['exec-' + stepKey]">
                                                                <span class="spinner-border spinner-border-sm"></span>
                                                            </template>
                                                            <template x-if="!stepLoading['exec-' + stepKey]">
                                                                <i class="ri-play-line"></i>
                                                            </template>
                                                        </button>
                                                    </template>
                                                </div>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
        </x-slot:provision>

        {{-- Update History Tab --}}
        <x-slot:updates>
                    <h6 class="mb-4">Update History</h6>

                    @if(isset($website_update_data) && count($website_update_data) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Version Change</th>
                                        <th>Status</th>
                                        <th>Message</th>
                                        <th>Updated By</th>
                                        <th>Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                    @foreach($website_update_data->reverse() as $update)
                                                            @php
                                                                $update_data = json_decode($update->meta_value);
                                            $updateStatus = match($update->status) {
                                                'done' => ['class' => 'bg-success-subtle text-success', 'icon' => 'ri-check-line', 'label' => 'Success'],
                                                'failed' => ['class' => 'bg-danger-subtle text-danger', 'icon' => 'ri-close-line', 'label' => 'Failed'],
                                                'reverted' => ['class' => 'bg-info-subtle text-info', 'icon' => 'ri-arrow-go-back-line', 'label' => 'Reverted'],
                                                default => ['class' => 'bg-warning-subtle text-warning', 'icon' => 'ri-loader-4-line', 'label' => 'In Progress']
                                            };
                                                            @endphp
                                        <tr class="{{ $update->status === 'failed' ? 'table-danger' : '' }}">
                                            <td class="text-muted small">#{{ $update->id ?? '-' }}</td>
                                                                <td>
                                                @if(isset($update_data->old_version) && isset($update_data->new_version))
                                                    <span class="badge text-bg-secondary-subtle text-secondary">v{{ $update_data->old_version }}</span>
                                                    <i class="ri-arrow-right-s-line mx-1 text-muted"></i>
                                                    <span class="badge {{ $update->status === 'done' ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary' }}">v{{ $update_data->new_version }}</span>
                                                                    @else
                                                    <span class="text-muted">-</span>
                                                                    @endif
                                                                </td>
                                            <td>
                                                <span class="badge {{ $updateStatus['class'] }}">
                                                    <i class="{{ $updateStatus['icon'] }} me-1"></i>{{ $updateStatus['label'] }}
                                                </span>
                                                                </td>
                                            <td class="small {{ $update->status === 'failed' ? 'text-danger' : 'text-muted' }}" style="max-width: 300px;">
                                                @if($update->status === 'failed' && isset($update_data->message))
                                                    <i class="ri-error-warning-line me-1"></i>
                                                    <span title="{{ $update_data->message }}">{{ Str::limit($update_data->message, 80) }}</span>
                                                @elseif(isset($update_data->message))
                                                    {{ $update_data->message }}
                                                @else
                                                    -
                                                @endif
                                                                </td>
                                            <td class="small">
                                                @if($update->owner)
                                                    {{ $update->owner->name }}
                                                    @else
                                                    <span class="text-muted">System</span>
                                                    @endif
                                            </td>
                                            <td class="small text-muted">
                                                <div>{{ $update->updated_at?->format('M d, Y') ?? '-' }}</div>
                                                <div class="text-muted small">{{ $update->updated_at?->format('H:i') ?? '' }}</div>
                                            </td>
                                        </tr>
                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                    @else
                        <div class="text-center py-5 text-muted">
                            <i class="ri-history-line fs-1 mb-3 d-block opacity-50"></i>
                            <p class="mb-0">No updates have been performed yet.</p>
                                    </div>
                    @endif
        </x-slot:updates>

        {{-- Notes Tab --}}
        <x-slot:notes>
            <x-app.notes :model="$website" />
        </x-slot:notes>

        {{-- Metadata Tab --}}
        <x-slot:metadata>

                    @php
                        $metadata = $website->metadata ?? [];
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
                            <p class="mb-0">No metadata stored for this website.</p>
                        </div>
                    @endif
        </x-slot:metadata>

        {{-- Activity Tab --}}
        <x-slot:activity>
                    <h6 class="mb-4">Activity Logs</h6>

                    @if(isset($activities) && count($activities) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 180px;">Date</th>
                                        <th>Event</th>
                                        <th>Description</th>
                                        <th>User</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($activities as $activity)
                                        @php
                                            $eventConfig = match($activity->event) {
                                                'created', 'create' => ['bg' => 'success', 'icon' => 'ri-add-line'],
                                                'updated', 'update' => ['bg' => 'primary', 'icon' => 'ri-edit-line'],
                                                'deleted', 'delete' => ['bg' => 'danger', 'icon' => 'ri-delete-bin-line'],
                                                'restored', 'restore' => ['bg' => 'info', 'icon' => 'ri-refresh-line'],
                                                'provision_failed' => ['bg' => 'danger', 'icon' => 'ri-error-warning-line'],
                                                'expired' => ['bg' => 'warning', 'icon' => 'ri-time-line'],
                                                'suspended' => ['bg' => 'warning', 'icon' => 'ri-pause-circle-line'],
                                                'unsuspended', 'Unsuspend' => ['bg' => 'success', 'icon' => 'ri-play-circle-line'],
                                                default => ['bg' => 'secondary', 'icon' => 'ri-information-line']
                                            };
                                        @endphp
                                        <tr>
                                            <td class="small text-muted">
                                                <div>{{ $activity->created_at->format('M d, Y') }}</div>
                                                <div class="text-muted small">{{ $activity->created_at->format('H:i:s') }}</div>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $eventConfig['bg'] }}">
                                                    <i class="{{ $eventConfig['icon'] }} me-1"></i>{{ ucfirst($activity->event ?? 'action') }}
                                                </span>
                                            </td>
                                            <td class="small" style="max-width: 400px;">
                                                {{ Str::limit($activity->description, 100) }}
                                            </td>
                                            <td class="small">
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
                                <a href="{{ route('app.logs.activity-logs.index', ['subject_type' => 'Modules\Platform\Models\Website', 'subject_id' => $website->id]) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="ri-external-link-line me-1"></i> View All Activities
                                </a>
                                </div>
                        @endif
                    @else
                        <div class="text-center py-5 text-muted">
                            <i class="ri-pulse-line fs-1 mb-3 d-block opacity-50"></i>
                            <p class="mb-0">No activity logs found for this website.</p>
                                    </div>
                    @endif
        </x-slot:activity>
    </x-tabs>

    <x-drawer id="website-drawer" />
    <x-security.secret-password-gate />

    <script data-up-execute>
        const initWebsiteShow = () => {
                const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

                // On-demand secret reveal (avoid embedding decrypted values in HTML)
                const revealedSecrets = new Map();
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

                // Password toggle functionality
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

                // Copy button functionality
                document.querySelectorAll('.copy-btn').forEach(btn => {
                    if (btn.dataset.bound === '1') return;
                    btn.dataset.bound = '1';

                    btn.addEventListener('click', async function() {
                        let value = this.getAttribute('data-copy-value');
                        const icon = this.querySelector('i');

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
                                // Change icon temporarily to indicate success
                                icon.className = 'ri-check-line fs-5 text-success';
                                setTimeout(() => {
                                    icon.className = 'ri-file-copy-line fs-5';
                                }, 1500);

                                // Show toast if available
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
        };

        initWebsiteShow();
    </script>
</x-app-layout>
