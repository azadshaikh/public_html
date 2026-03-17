{{-- Show Provider --}}
<x-app-layout title="{{ $provider->name }}">
    <x-page-header
        title="{{ $provider->name }}"
        description="{{ $provider->type_label }} - {{ $provider->vendor_label }}"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Edit',
                'icon' => 'ri-pencil-line',
                'variant' => 'btn-primary',
                'href' => route('platform.providers.edit', $provider->id),
            ],
        ]"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Providers', 'href' => route('platform.providers.index')],
            ['label' => '#' . $provider->id, 'active' => true],
        ]">
    </x-page-header>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Provider Details</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Name</label>
                            <p class="mb-0 fw-medium">{{ $provider->name }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Email</label>
                            <p class="mb-0 fw-medium">{{ $provider->email ?? '-' }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Type</label>
                            <p class="mb-0">
                                <span class="badge text-bg-{{ $provider->type_color }}">
                                    <i class="{{ $provider->type_icon }} me-1"></i>
                                    {{ $provider->type_label }}
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Vendor</label>
                            <p class="mb-0">
                                <span class="badge text-bg-{{ $provider->vendor_color }}">
                                    {{ $provider->vendor_label }}
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Status</label>
                            <p class="mb-0">
                                <span class="badge text-bg-{{ $provider->status_color }}">
                                    {{ $provider->status_label }}
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Created</label>
                            <p class="mb-0 fw-medium">{{ $provider->created_at?->format('M d, Y H:i') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Linked Resources --}}
            @if($provider->websites->count() > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Linked Websites ({{ $provider->websites->count() }})</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Domain</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($provider->websites->take(10) as $website)
                                @php
                                    $websiteStatus = $website->status instanceof \BackedEnum
                                        ? $website->status->value
                                        : (string) $website->status;
                                @endphp
                                <tr>
                                    <td>{{ $website->name }}</td>
                                    <td>{{ $website->domain }}</td>
                                    <td><span class="badge text-bg-{{ $websiteStatus === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($websiteStatus) }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('platform.websites.show', $website->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @if($provider->domains->count() > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Linked Domains ({{ $provider->domains->count() }})</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Domain</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($provider->domains->take(10) as $domain)
                                <tr>
                                    <td>{{ $domain->name }}</td>
                                    <td><span class="badge text-bg-{{ $domain->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($domain->status) }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('platform.domains.show', $domain->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @if($provider->servers->count() > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Linked Servers ({{ $provider->servers->count() }})</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>IP</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($provider->servers->take(10) as $server)
                                <tr>
                                    <td>{{ $server->name }}</td>
                                    <td>{{ $server->ip }}</td>
                                    <td><span class="badge text-bg-{{ $server->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($server->status) }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('platform.servers.show', $server->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Usage Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Websites</span>
                        <span class="badge text-bg-primary rounded-pill">{{ $provider->websites->count() }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Domains</span>
                        <span class="badge text-bg-success rounded-pill">{{ $provider->domains->count() }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Servers</span>
                        <span class="badge text-bg-info rounded-pill">{{ $provider->servers->count() }}</span>
                    </div>
                </div>
            </div>

            @if($provider->metadata && ($provider->getMetadata('balance') || $provider->getMetadata('account_name')))
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Account Info</h6>
                </div>
                <div class="card-body">
                    @if($provider->getMetadata('account_name'))
                    <div class="mb-3">
                        <label class="form-label text-muted small">Account Name</label>
                        <p class="mb-0">{{ $provider->getMetadata('account_name') }}</p>
                    </div>
                    @endif
                    @if($provider->getMetadata('balance') !== null)
                    <div class="mb-3">
                        <label class="form-label text-muted small">Balance</label>
                        <p class="mb-0 fw-bold text-success">${{ number_format($provider->getMetadata('balance'), 2) }}</p>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Audit Info</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Created By</label>
                        <p class="mb-0">{{ $provider->createdBy?->name ?? 'System' }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Created At</label>
                        <p class="mb-0">{{ $provider->created_at?->format('M d, Y H:i') }}</p>
                    </div>
                    @if($provider->updated_at && $provider->updated_at != $provider->created_at)
                    <div class="mb-3">
                        <label class="form-label text-muted small">Updated By</label>
                        <p class="mb-0">{{ $provider->updatedBy?->name ?? 'System' }}</p>
                    </div>
                    <div>
                        <label class="form-label text-muted small">Updated At</label>
                        <p class="mb-0">{{ $provider->updated_at?->format('M d, Y H:i') }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
