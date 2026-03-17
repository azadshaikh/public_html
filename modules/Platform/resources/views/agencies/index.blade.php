{{-- Agencies Management --}}
<x-app-layout title="Agencies">

    {{-- Page Header --}}
    <x-page-header title="Agencies"
        description="Manage your client agencies and their configurations" layout="datagrid"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Add Agency',
                'icon' => 'ri-add-line',
                'variant' => 'btn-primary',
                'href' => route('platform.agencies.create'),
            ],
        ]" :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Agencies'],
        ]" />

    <div class="content-body">
        <x-datagrid
            :url="route('platform.agencies.data')"
            :bulk-action-url="route('platform.agencies.bulk-action')"
            :table-config="$config ?? []"
            :initial-data="$initialData ?? null"
            :empty-config="[
                'icon' => 'ri-building-4-line',
                'title' => 'No agencies found',
                'message' => 'No agencies match your search criteria.',
                'showAddButton' => true,
                'addButtonText' => 'Add Agency',
                'addButtonUrl' => route('platform.agencies.create'),
            ]"
        />
    </div>

    <x-script-loader :wrap="false" :scripts="['modules/Platform/resources/assets/js/datagrid-templates/common.js']" />

</x-app-layout>
