{{-- Servers Management --}}
<x-app-layout title="Servers">

    {{-- Page Header --}}
    <x-page-header title="Servers"
        description="Manage your server infrastructure and hosting" layout="datagrid"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Add Server',
                'icon' => 'ri-add-line',
                'variant' => 'btn-primary',
                'href' => route('platform.servers.create'),
            ],
        ]" :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Servers'],
        ]" />

    <div class="content-body">
        <x-datagrid
            :url="route('platform.servers.data')"
            :bulk-action-url="route('platform.servers.bulk-action')"
            :table-config="$config ?? []"
            :initial-data="$initialData ?? null"
            :empty-config="[
                'icon' => 'ri-server-line',
                'title' => 'No servers found',
                'message' => 'No servers match your search criteria.',
                'showAddButton' => true,
                'addButtonText' => 'Add Server',
                'addButtonUrl' => route('platform.servers.create'),
            ]"
        />
    </div>

    <x-script-loader :wrap="false" :scripts="['modules/Platform/resources/assets/js/datagrid-templates/common.js']" />

</x-app-layout>
