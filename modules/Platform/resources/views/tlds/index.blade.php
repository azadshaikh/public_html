{{-- TLDs Management --}}
<x-app-layout title="TLDs">

    <x-page-header title="TLDs"
        description="Manage Top Level Domains" layout="datagrid"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Add TLD',
                'icon' => 'ri-add-line',
                'variant' => 'btn-primary',
                'href' => route('platform.tlds.create'),
                'permission' => 'add_tlds',
            ],
        ]" :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'TLDs'],
        ]" />

    <div class="content-body">
        <x-datagrid
            :url="route('platform.tlds.data')"
            :bulk-action-url="route('platform.tlds.bulk-action')"
            :table-config="$config ?? []"
            :initial-data="$initialData ?? null"
            :empty-config="[
                'icon' => 'ri-earth-line',
                'title' => 'No TLDs found',
                'message' => 'No TLDs match your search criteria.',
                'showAddButton' => true,
                'addButtonText' => 'Add TLD',
                'addButtonUrl' => route('platform.tlds.create'),
            ]"
        />
    </div>

    <x-script-loader :wrap="false" :scripts="['modules/Platform/resources/assets/js/datagrid-templates/common.js']" />

</x-app-layout>

