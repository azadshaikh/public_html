{{-- Edit TLD --}}
<x-app-layout title="Edit TLD">

    @php
        $actions = [];

        if (Route::has('platform.tlds.show')) {
            $actions[] = [
                'label' => 'Show',
                'href' => route('platform.tlds.show', $tld->id),
                'icon' => 'ri-eye-line',
                'variant' => 'btn-outline-primary'
            ];
        }

        $actions[] = [
            'label' => 'Back',
            'href' => route('platform.tlds.index'),
            'icon' => 'ri-arrow-left-line',
            'variant' => 'btn-outline-secondary'
        ];
    @endphp

    <x-page-header title="Edit TLD"
        description="Edit TLD details" layout="form"
        :actions="$actions"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'TLDs', 'href' => route('platform.tlds.index', 'all')],
            ['label' => '#' . $tld->id, 'href' => Route::has('platform.tlds.show') ? route('platform.tlds.show', $tld->id) : null],
            ['label' => 'Edit', 'active' => true],
        ]" />

    <form data-dirty-form class="needs-validation" id="tld-form" method="POST" action="{{ route('platform.tlds.update', $tld->id) }}" novalidate>
        @csrf
        @method('PUT')
        @include('platform::tlds.form')
    </form>

</x-app-layout>
