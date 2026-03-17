{{-- Create Secret --}}
<x-app-layout title="Add Secret">

    <x-page-header title="Add Secret"
        description="Create a new secure credential" layout="form"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Secrets', 'href' => route('platform.secrets.index')],
            ['label' => 'Create', 'active' => true],
        ]"
        :actions="[
            [
                'label' => 'Back to Secrets',
                'href' => route('platform.secrets.index'),
                'icon' => 'ri-arrow-left-line',
                'variant' => 'btn-outline-secondary',
            ],
        ]" />

    @if ($errors->any())
        @php
            $errorFields = array_keys($errors->toArray());
            $friendlyNames = [
                'key' => 'Key',
                'type' => 'Type',
                'value' => 'Value',
                'secretable_type' => 'Model Type',
                'secretable_id' => 'Model ID',
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

    <form data-dirty-form class="needs-validation" id="secret-form" method="POST" action="{{ $formConfig['action'] ?? route('platform.secrets.store') }}" novalidate>
        @csrf
        @include('platform::secrets.form')
    </form>

</x-app-layout>
