{{-- Websites Management --}}
<x-app-layout title="Websites">

    <x-page-header title="Websites"
        description="Manage your websites and hosting" layout="datagrid"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Add Website',
                'icon' => 'ri-add-line',
                'variant' => 'btn-primary',
                'href' => route('platform.websites.create'),
                'permission' => 'add_websites',
            ],
        ]" :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Websites'],
        ]" />

    <div class="content-body">
        <x-datagrid
            :url="route('platform.websites.data')"
            :bulk-action-url="route('platform.websites.bulk-action')"
            :table-config="$config ?? []"
            :initial-data="$initialData ?? null"
            :empty-config="[
                'icon' => 'ri-global-line',
                'title' => 'No websites found',
                'message' => 'No websites match your search criteria.',
                'showAddButton' => true,
                'addButtonText' => 'Add Website',
                'addButtonUrl' => route('platform.websites.create'),
            ]"
        />
    </div>

    <x-script-loader :wrap="false" :scripts="['modules/Platform/resources/assets/js/datagrid-templates/common.js']" />

</x-app-layout>

