{{-- Providers Index --}}
<x-app-layout title="Providers">
    <x-page-header title="Providers"
        description="Manage your DNS, CDN, Server, and Domain Registrar providers" layout="datagrid"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Add Provider',
                'icon' => 'ri-add-line',
                'variant' => 'btn-primary',
                'href' => route('platform.providers.create'),
            ],
        ]"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Providers'],
        ]" />

    <div class="content-body">
        <x-datagrid
            :url="route('platform.providers.data')"
            :bulk-action-url="route('platform.providers.bulk-action')"
            :table-config="$config ?? []"
            :initial-data="$initialData ?? null"
            :empty-config="[
                'icon' => 'ri-cloud-line',
                'title' => 'No providers found',
                'message' => 'No providers match your search criteria.',
                'showAddButton' => true,
                'addButtonText' => 'Add Provider',
                'addButtonUrl' => route('platform.providers.create'),
            ]"
        />
    </div>

    <x-script-loader :wrap="false" :scripts="['modules/Platform/resources/assets/js/datagrid-templates/common.js']" />
</x-app-layout>

