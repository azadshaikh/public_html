{{-- Create Provider --}}
<x-app-layout title="Create Provider">
    <x-page-header
        title="Create Provider"
        description="Add a new {{ $selectedType ? config('platform.provider.types.' . $selectedType . '.label', 'Provider') : 'Provider' }}"
        :actions="[
            [
                'label' => 'Back',
                'href' => route('platform.providers.index'),
                'icon' => 'ri-arrow-left-line',
                'variant' => 'btn-outline-secondary'
            ],
        ]"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Providers', 'href' => route('platform.providers.index')],
            ['label' => 'Create', 'active' => true],
        ]">
    </x-page-header>

    <form data-dirty-form action="{{ $formConfig['action'] ?? route('platform.providers.store') }}" method="POST" class="needs-validation" novalidate>
        @csrf
        @include('platform::providers.form')
    </form>
</x-app-layout>
