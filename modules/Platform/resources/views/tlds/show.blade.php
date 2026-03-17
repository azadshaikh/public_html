{{-- Show TLD --}}
<x-app-layout :title="$page_title">
    @php
        $isActive = $tld->status === true;
        $isTrashed = !empty($tld->deleted_at);

        // Status configuration
        $statusConfig = $isActive
            ? ['icon' => 'ri-checkbox-circle-fill', 'color' => 'success', 'bg' => 'success-subtle']
            : ['icon' => 'ri-close-circle-fill', 'color' => 'secondary', 'bg' => 'secondary-subtle'];
    @endphp

    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'TLDs', 'href' => route('platform.tlds.index', 'all')],
            ['label' => '#' . $tld->id, 'active' => true],
        ];

        $actions = [];

        if (auth()->user()->can('edit_tlds')) {
             $actions[] = [
                'type' => 'link',
                'label' => 'Edit',
                'icon' => 'ri-pencil-line',
                'href' => route('platform.tlds.edit', $tld->id),
                'variant' => 'btn-outline-secondary'
            ];

            $moreActions = [];

            if (!$isTrashed) {
                 $moreActions[] = [
                    'label' => $isActive ? 'Deactivate' : 'Activate',
                    'icon' => $isActive ? 'ri-close-circle-line' : 'ri-checkbox-circle-line',
                    'action' => 'toggleStatus',
                    'entityType' => 'tld',
                    'entityId' => $tld->id,
                    'currentStatus' => $isActive ? 'active' : 'inactive',
                ];
            }

            if (auth()->user()->can('delete_tlds')) {
                if ($isTrashed) {
                    if (auth()->user()->can('restore_tlds')) {
                        $moreActions[] = [
                            'label' => 'Restore',
                            'icon' => 'ri-arrow-go-back-line',
                            'action' => 'restore',
                            'entityType' => 'tld',
                            'entityId' => $tld->id
                        ];
                    }
                    $moreActions[] = [
                        'label' => 'Delete Permanently',
                        'icon' => 'ri-delete-bin-line',
                        'action' => 'forceDelete',
                        'entityType' => 'tld',
                        'entityId' => $tld->id,
                        'class' => 'text-danger'
                    ];
                } else {
                    $moreActions[] = [
                        'label' => 'Delete',
                        'icon' => 'ri-delete-bin-line',
                        'action' => 'delete',
                        'entityType' => 'tld',
                        'entityId' => $tld->id,
                        'class' => 'text-danger'
                    ];
                }
            }

            if (!empty($moreActions)) {
                $actions[] = [
                    'type' => 'dropdown',
                    'label' => 'More Actions',
                    'icon' => 'ri-more-2-fill',
                    'variant' => 'btn-outline-secondary',
                    'items' => $moreActions
                ];
            }
        }
    @endphp

    <x-page-header
        :title="$tld->tld"
        description="TLD Details"
        :breadcrumbs="$breadcrumbs"
        :actions="$actions"
    />

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">TLD Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">TLD</label>
                            <p class="mb-0 fw-medium">{{ $tld->tld }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">WHOIS Server</label>
                            <p class="mb-0 fw-medium">{{ $tld->whois_server ?? '-' }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Pattern</label>
                            <p class="mb-0 fw-medium">{{ $tld->pattern ?? '-' }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Order</label>
                            <p class="mb-0 fw-medium">{{ $tld->tld_order ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Pricing & Settings</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Price</label>
                            <p class="mb-0 fw-medium">{{ $tld->price ? '$' . number_format($tld->price, 2) : '-' }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Sale Price</label>
                            <p class="mb-0 fw-medium">{{ $tld->sale_price ? '$' . number_format($tld->sale_price, 2) : '-' }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Affiliate Link</label>
                            @if($tld->affiliate_link)
                                <p class="mb-0">
                                    <a href="{{ $tld->affiliate_link }}" target="_blank" class="text-primary">
                                        <i class="ri-external-link-line me-1"></i>View Link
                                    </a>
                                </p>
                            @else
                                <p class="mb-0 fw-medium">-</p>
                            @endif
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Main TLD</label>
                            <p class="mb-0">
                                @if($tld->is_main)
                                    <span class="badge text-bg-success">
                                        <i class="ri-check-line me-1"></i>Yes
                                    </span>
                                @else
                                    <span class="badge text-bg-secondary">No</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Suggested</label>
                            <p class="mb-0">
                                @if($tld->is_suggested)
                                    <span class="badge text-bg-success">
                                        <i class="ri-check-line me-1"></i>Yes
                                    </span>
                                @else
                                    <span class="badge text-bg-secondary">No</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Status & Timeline</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Status</label>
                        <p class="mb-0">
                            <span class="badge text-bg-{{ $statusConfig['color'] }}">
                                <i class="{{ $statusConfig['icon'] }} me-1"></i>
                                {{ $isActive ? 'Active' : 'Inactive' }}
                            </span>
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Created</label>
                        <p class="mb-0 fw-medium">{{ $tld->created_at?->format('M d, Y H:i') }}</p>
                    </div>
                    @if($tld->updated_at && $tld->updated_at != $tld->created_at)
                        <div class="mb-3">
                            <label class="form-label text-muted small">Last Updated</label>
                            <p class="mb-0 fw-medium">{{ $tld->updated_at?->format('M d, Y H:i') }}</p>
                        </div>
                    @endif
                    @if($isTrashed)
                        <div class="mb-3">
                            <label class="form-label text-muted small">Deleted</label>
                            <p class="mb-0 fw-medium">{{ $tld->deleted_at?->format('M d, Y H:i') }}</p>
                        </div>
                    @endif
                </div>
            </div>

            @if($tld->metadata && count($tld->metadata) > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Additional Metadata</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            @foreach($tld->metadata as $key => $value)
                            <tr>
                                <td class="text-muted small">{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                                <td class="fw-medium">{{ is_array($value) ? json_encode($value) : $value }}</td>
                            </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Activity Timeline --}}
    @include('app.partials.activity-timeline', ['model' => $tld])

    <script data-up-execute>
    (() => {
        const moreActionsButton = document.querySelector('[data-bs-toggle="dropdown"]');
        if (!moreActionsButton || moreActionsButton.dataset.dropdownInit === '1') return;
        moreActionsButton.dataset.dropdownInit = '1';

        moreActionsButton.addEventListener('click', (e) => {
            e.preventDefault();
        });
    })();
    </script>
</x-app-layout>
