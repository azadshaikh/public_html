{{-- Global SSL Certificates Index Page --}}
<x-app-layout title="SSL Certificates">

    <x-page-header title="SSL Certificates"
        description="Manage SSL certificates across all domains" layout="datagrid"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'SSL Certificates'],
        ]" />

    <div class="content-body">
        <x-datagrid
            :url="route('platform.ssl-certificates.index', ['status' => $status])"
            :bulk-action-url="''"
            :table-config="$config ?? []"
            :initial-data="$initialData ?? null"
            :status-navigation="$statusNavigation ?? []"
            :empty-config="[
                'icon' => 'ri-shield-keyhole-line',
                'title' => 'No SSL Certificates',
                'message' => 'SSL certificates will appear here once added to domains.',
                'showAddButton' => false,
            ]"
            :enable-filters="false"
            :enable-bulk-actions="false"
        />
    </div>

    <x-script-loader :wrap="false" :scripts="['modules/Platform/resources/assets/js/datagrid-templates/common.js']" />

</x-app-layout>

