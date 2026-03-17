{{-- Secrets Management --}}
<x-app-layout title="Secrets">

    <x-page-header title="Secrets"
        description="Manage your secure credentials and keys" layout="datagrid"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Add Secret',
                'icon' => 'ri-add-line',
                'variant' => 'btn-primary',
                'href' => route('platform.secrets.create'),
            ],
        ]" :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Secrets'],
        ]" />

    <div class="content-body">
        <x-datagrid
            :url="route('platform.secrets.data')"
            :bulk-action-url="route('platform.secrets.bulk-action')"
            :table-config="$config ?? []"
            :initial-data="$initialData ?? null"
            :empty-config="[
                'icon' => 'ri-key-line',
                'title' => 'No secrets found',
                'message' => 'No secrets match your search criteria.',
                'showAddButton' => true,
                'addButtonText' => 'Add Secret',
                'addButtonUrl' => route('platform.secrets.create'),
            ]"
        />
    </div>

    <x-script-loader :wrap="false" :scripts="['modules/Platform/resources/assets/js/datagrid-templates/common.js']" />

</x-app-layout>
