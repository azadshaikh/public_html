<x-app-layout :title="$page_title">
    <x-page-header title="{{ $page_title }}"
        description="Configure platform servers, trial settings, and SSL certificates"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Settings', 'active' => true],
        ]" />

    <div class="row g-4">
        <!-- Settings Navigation Menu -->
        <div class="col-lg-3">
            @include('platform::settings.settings_nav')
        </div>

        <!-- Settings Forms Container -->
        <div class="col-lg-9">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="general">
                    @include('platform::settings.partials.general')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
