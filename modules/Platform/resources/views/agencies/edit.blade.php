{{-- Edit Agency --}}
<x-app-layout title="Edit Agency">

    @php
        $actions = [];

        if (Route::has('platform.agencies.show')) {
            $actions[] = [
                'type' => 'link',
                'label' => 'Show',
                'icon' => 'ri-eye-line',
                'href' => route('platform.agencies.show', $agency->id),
                'variant' => 'btn-outline-primary'
            ];
        }

        $actions[] = [
            'type' => 'link',
            'label' => 'Back',
            'icon' => 'ri-arrow-left-line',
            'href' => route('platform.agencies.index'),
            'variant' => 'btn-outline-secondary'
        ];
    @endphp

    <x-page-header title="Edit Agency"
        description="Update agency information and settings" layout="form"
        :actions="$actions"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Agencies', 'href' => route('platform.agencies.index')],
            ['label' => '#' . $agency->id, 'href' => Route::has('platform.agencies.show') ? route('platform.agencies.show', $agency->id) : null],
            ['label' => 'Edit', 'active' => true],
        ]" />

    @if ($errors->any())
        @php
            $errorFields = array_keys($errors->toArray());
            $friendlyNames = [
                'name' => 'Agency Name',
                'website' => 'Website',
                'group' => 'Group',
                'owner_id' => 'Owner',
                'email' => 'Email',
                'mobile' => 'Mobile',
                'logo_id' => 'Logo',
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

    <form data-dirty-form class="needs-validation" id="agency-form" method="POST" action="{{ $formConfig['action'] ?? route('platform.agencies.update', $agency->id) }}" novalidate>
        @csrf
        @method('PUT')
        <input name="id" type="hidden" value="{{ $agency->id }}">
        @include('platform::agencies.form')
    </form>

    <x-media-picker.media-modal />
</x-app-layout>
