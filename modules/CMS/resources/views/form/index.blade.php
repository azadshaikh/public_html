<x-app-layout title="Forms Management">
    <x-page-header
        title="Forms Management"
        description="Manage contact forms and form submissions for your CMS"
        layout="datagrid"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'Forms'],
        ]"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Add Form',
                'href' => route('cms.form.create'),
                'icon' => 'ri-add-line',
                'variant' => 'btn-primary',
            ],
        ]"
    />

    <x-datagrid
        aria-label="Forms table"
        :url="route('cms.form.data')"
        :bulk-action-url="route('cms.form.bulk-action')"
        :table-config="$config"
        :initial-data="$initialData ?? null"
        :search="request('search')"
        :empty-config="[
            'icon' => 'ri-file-line-text',
            'title' => 'No Forms Found',
            'message' => 'No forms have been created yet. Start by creating your first form.',
            'showAddButton' => true,
            'addButtonText' => 'Create Form',
            'addButtonUrl' => route('cms.form.create'),
        ]"
    />
</x-app-layout>
