{{-- DNS Records DataGrid (embedded / reusable) --}}
@php
    $dnsUrl = isset($domain) && $domain
        ? route('platform.dns.data', ['domain_id' => $domain->id])
        : route('platform.dns.data');

    $dnsEmptyConfig = [
        'icon' => 'ri-server-line',
        'title' => 'No DNS records found',
        'message' => isset($domain) && $domain
            ? "No DNS records found for {$domain->domain_name}."
            : 'No DNS records match your search criteria.',
        'showAddButton' => false,
    ];
@endphp

<x-datagrid
    :url="$dnsUrl"
    :bulk-action-url="route('platform.dns.bulk-action')"
    :table-config="$config ?? []"
            :initial-data="$initialData ?? null"
    :empty-config="$dnsEmptyConfig"
    :show-status-tabs="false"
/>

<x-script-loader :wrap="false" :scripts="['modules/Platform/resources/assets/js/datagrid-templates/common.js']" />

