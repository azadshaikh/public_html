{{-- Edit Website --}}
<x-app-layout title="Edit Website">

    @php
        $actions = [];

        if (Route::has('platform.websites.show')) {
            $actions[] = [
                'label' => 'Show',
                'href' => route('platform.websites.show', $website->id),
                'icon' => 'ri-eye-line',
                'variant' => 'btn-outline-primary'
            ];
        }

        $actions[] = [
            'label' => 'Back',
            'href' => route('platform.websites.index'),
            'icon' => 'ri-arrow-left-line',
            'variant' => 'btn-outline-secondary'
        ];
    @endphp

    <x-page-header title="Edit Website"
        description="Update website details" layout="form"
        :actions="$actions"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Websites', 'href' => route('platform.websites.index', 'all')],
            ['label' => '#' . $website->id, 'href' => Route::has('platform.websites.show') ? route('platform.websites.show', $website->id) : null],
            ['label' => 'Edit', 'active' => true],
        ]" />

    @if ($errors->any())
        @php
            $errorFields = array_keys($errors->toArray());
            $friendlyNames = [
                'name' => 'Website Name',
                'domain' => 'Domain',
                'type' => 'Website Type',
                'agency_id' => 'Agency',
                'server_id' => 'Server',
                'owner_id' => 'Owner',
                'primary_category_id' => 'Primary Category',
                'status' => 'Status',
            ];

            $friendlyFieldNames = array_map(fn ($field) => $friendlyNames[$field] ?? ucfirst(str_replace('_', ' ', $field)), $errorFields);
            $fieldCount = count($friendlyFieldNames);
            $errorSummary = $fieldCount === 1
                ? "Please check the {$friendlyFieldNames[0]} field."
                : 'Please check the following fields: ' . implode(', ', $friendlyFieldNames) . '.';
        @endphp
        <div class="alert alert-danger alert-dismissible fade rounded-4 show" role="alert">
            <div class="d-flex align-items-start">
                <div class="alert-icon me-3 flex-shrink-0">
                    <i class="ri-error-warning-fill" style="font-size: 1.25rem;"></i>
                </div>
                <div class="flex-grow-1">
                    <h5 class="fw-semibold mb-2">Validation Error!</h5>
                    <p class="mb-0">{{ $errorSummary }}</p>
                </div>
            </div>
            <button class="btn-close" data-bs-dismiss="alert" type="button" aria-label="Close"></button>
        </div>
    @endif

    <form data-dirty-form class="needs-validation" id="website-form" method="POST" action="{{ $formConfig['action'] ?? route('platform.websites.update', $website->id) }}" novalidate>
        @csrf
        @method('PUT')
        @include('platform::websites.form')
    </form>

</x-app-layout>
