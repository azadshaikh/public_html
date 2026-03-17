{{-- Edit Provider --}}
<x-app-layout title="Edit Provider">
    @php
        $actions = [];

        if (Route::has('platform.providers.show')) {
            $actions[] = [
                'label' => 'Show',
                'href' => route('platform.providers.show', $provider->id),
                'icon' => 'ri-eye-line',
                'variant' => 'btn-outline-primary'
            ];
        }

        $actions[] = [
            'label' => 'Back',
            'href' => route('platform.providers.index'),
            'icon' => 'ri-arrow-left-line',
            'variant' => 'btn-outline-secondary'
        ];
    @endphp

    <x-page-header
        title="Edit Provider"
        description="Update {{ $provider->name }}"
        :actions="$actions"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Providers', 'href' => route('platform.providers.index')],
            ['label' => '#' . $provider->id, 'href' => Route::has('platform.providers.show') ? route('platform.providers.show', $provider->id) : null],
            ['label' => 'Edit', 'active' => true],
        ]">
    </x-page-header>

    <form data-dirty-form action="{{ $formConfig['action'] ?? route('platform.providers.update', $provider->id) }}" method="POST" class="needs-validation" novalidate>
        @csrf
        @method('PUT')
        @include('platform::providers.form')
    </form>
</x-app-layout>
