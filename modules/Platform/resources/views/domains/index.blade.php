{{-- Domains Management Index Page --}}
<x-app-layout title="Domains Management">

    <x-page-header title="Domains Management"
        description="Manage domain registrations and DNS settings" layout="datagrid"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Add Domain',
                'icon' => 'ri-add-line',
                'variant' => 'btn-primary',
                'href' => route('platform.domains.create'),
            ],
        ]" :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Domains'],
        ]" />

    <div class="content-body">
        <x-datagrid
            :url="route('platform.domains.data')"
            :bulk-action-url="route('platform.domains.bulk-action')"
            :table-config="$config ?? []"
            :initial-data="$initialData ?? null"
            :empty-config="[
                'icon' => 'ri-global-line',
                'title' => 'No domains found',
                'message' => 'No domains match your search criteria.',
                'showAddButton' => true,
                'addButtonText' => 'Add Domain',
                'addButtonUrl' => route('platform.domains.create'),
            ]"
        />
    </div>

    <x-script-loader :wrap="false" :scripts="['modules/Platform/resources/assets/js/datagrid-templates/common.js']" />

</x-app-layout>

