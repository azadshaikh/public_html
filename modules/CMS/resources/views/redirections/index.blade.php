<x-app-layout title="Redirections">
    @php
        $currentStatus = request()->input('status') ?? request()->route('status') ?? 'all';
    @endphp

    <x-page-header
        title="Redirections"
        description="Manage URL redirect rules for your site"
        layout="datagrid"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'Redirections'],
        ]"
        :actions="[
            [
                'type' => 'dropdown',
                'label' => 'Import/Export',
                'icon' => 'ri-download-upload-line',
                'variant' => 'btn-outline-secondary',
                'items' => [
                    [
                        'type' => 'link',
                        'label' => 'Import from CSV',
                        'icon' => 'ri-upload-2-line',
                        'href' => route('cms.redirections.import.form'),
                    ],
                    [
                        'type' => 'link',
                        'label' => 'Export to CSV',
                        'icon' => 'ri-download-line',
                        'href' => route('cms.redirections.export', ['status' => $currentStatus]),
                        'up-follow' => 'false',
                    ],
                ],
            ],
            [
                'type' => 'link',
                'label' => 'Create',
                'icon' => 'ri-add-line',
                'variant' => 'btn-primary',
                'href' => route('cms.redirections.create'),
            ],
        ]"
    />

    <x-datagrid
        aria-label="SEO redirections table"
        :url="route('cms.redirections.data')"
        :bulk-action-url="route('cms.redirections.bulk-action')"
        :table-config="$config"
        :initial-data="$initialData ?? null"
        :search="request('search')"
        :empty-config="[
            'icon' => 'ri-shuffle-line',
            'title' => 'No Redirections Found',
            'message' => 'No SEO redirections have been created yet. Start by adding your first redirection.',
            'showAddButton' => true,
            'addButtonText' => 'Create Redirection',
            'addButtonUrl' => route('cms.redirections.create'),
        ]"
    />
</x-app-layout>
