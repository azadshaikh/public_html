{{-- DNS Records Management --}}
<x-app-layout title="DNS Records">

    @php
        $actions = [];

        if (($domain ?? null) && Auth::user()->can('add_domain_dns_records')) {
            $actions[] = [
                'type' => 'link',
                'label' => 'Add DNS Record',
                'icon' => 'ri-add-line',
                'variant' => 'btn-primary',
                'href' => route('platform.dns.create', ['domain_id' => $domain->id]),
                'class' => 'drawer-btn',
                'attributes' => [
                    'data-bs-toggle' => 'offcanvas',
                    'data-bs-target' => '#domain-drawer',
                    'up-follow' => 'false',
                ],
            ];
        }
    @endphp

    <x-page-header title="DNS Records"
        description="Manage domain DNS records" layout="datagrid"
        :actions="$actions"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'DNS Records'],
        ]" />

    <div class="content-body">
        @include('platform::dns_records._datagrid', ['domain' => $domain ?? null, 'config' => $config ?? []])
    </div>

    <x-drawer id="domain-drawer" />

</x-app-layout>
