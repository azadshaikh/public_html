{{-- Create TLD --}}
<x-app-layout title="Add TLD">

    <x-page-header title="Add TLD"
        description="Add a new TLD to your platform" layout="form"
        :actions="[
            [
                'label' => 'Back',
                'href' => route('platform.tlds.index'),
                'icon' => 'ri-arrow-left-line',
                'variant' => 'btn-outline-secondary'
            ],
        ]"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'TLDs', 'href' => route('platform.tlds.index', 'all')],
            ['label' => 'Create', 'active' => true],
        ]" />

    <form data-dirty-form class="needs-validation" id="tld-form" method="POST" action="{{ route('platform.tlds.store') }}" novalidate>
        @csrf
        @include('platform::tlds.form')
    </form>

</x-app-layout>
