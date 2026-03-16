<x-app-layout title="Design Blocks">
    {{-- Page Header --}}
    <x-page-header
        title="Design Blocks"
        description="Manage reusable design components and blocks"
        layout="datagrid"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
                ['label' => 'CMS'],
            ['label' => 'Design Blocks', 'active' => true],
        ]"
        :actions="[
            [
                'label' => 'Add Design Block',
                'href' => route('cms.designblock.create'),
                'icon' => 'ri-add-line',
                'variant' => 'btn-primary',
            ],
        ]"
    />

    <x-datagrid
        aria-label="Design Blocks table"
        :url="route('cms.designblock.data', ['status' => request()->route('status') ?? 'all'])"
        :bulk-action-url="route('cms.designblock.bulk-action')"
        :table-config="$config"
        :initial-data="$initialData ?? null"
        :search="request('search')"
        :empty-config="[
            'icon' => 'ri-grid-line',
            'title' => 'No Design Blocks Found',
            'message' => 'No design blocks have been created yet. Start by creating your first design block.',
            'showAddButton' => true,
            'addButtonText' => 'Create Design Block',
            'addButtonUrl' => route('cms.designblock.create'),
        ]"
    />
</x-app-layout>
