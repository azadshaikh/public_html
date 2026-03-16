<x-app-layout title="Menus">
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Appearance', 'href' => route('cms.appearance.themes.index')],
            ['label' => 'Menus', 'active' => true],
        ];

        $actions = [
            [
                'type' => 'link',
                'label' => 'Create Menu',
                'icon' => 'ri-add-line',
                'variant' => 'btn-primary',
                'href' => route('cms.appearance.menus.create'),
            ],
        ];

        $assignmentCollection = collect($locationAssignments ?? []);
        $assignedLocationsCount = $assignmentCollection->where('status', 'assigned')->count();
        $unassignedAssignments = $assignmentCollection->where('status', 'unassigned')->values();
        $nextUnassigned = $unassignedAssignments->first();
        $locationPreview = $assignmentCollection->take(4);
        $totalMenuCount = $menus->count();

        $meta = [];
        if (count($locations) > 0) {
            $meta[] = [
                'icon' => 'ri-map-pin-line',
                'text' => count($locations).' locations',
            ];
            $meta[] = [
                'icon' => 'ri-checkbox-circle-line',
                'text' => $assignedLocationsCount.' assigned',
            ];
        }
        $meta[] = [
            'icon' => 'ri-menu-line',
            'text' => $totalMenuCount.' menus',
        ];
    @endphp

    <x-page-header title="Menus" description="Manage navigation menus."
        layout="datagrid" :breadcrumbs="$breadcrumbs" :actions="$actions" :meta="$meta" />

    @if (count($locations) === 0)
        <div class="alert alert-warning mb-3" role="alert">
            This theme does not define menu locations yet. You can still create menus.
        </div>
    @elseif ($unassignedAssignments->isNotEmpty())
        <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3" role="alert">
            <span>
                {{ $unassignedAssignments->count() }}
                {{ \Illuminate\Support\Str::plural('location', $unassignedAssignments->count()) }}
                unassigned.
            </span>
            @if ($nextUnassigned)
                <a class="btn btn-sm btn-outline-secondary"
                    href="{{ route('cms.appearance.menus.create', ['location' => $nextUnassigned['key']]) }}"
                    up-follow up-target="[up-main]">
                    Assign Next
                </a>
            @endif
        </div>
    @endif

    @if (count($locations) > 0)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Location Snapshot</h6>
                <span class="badge bg-secondary-subtle text-secondary">
                    {{ $assignedLocationsCount }}/{{ count($locations) }} assigned
                </span>
            </div>
            <div class="card-body py-2">
                <div class="list-group list-group-flush">
                    @foreach ($locationPreview as $assignment)
                        @php
                            $assignedMenu = $assignment['menu'];
                        @endphp
                        <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold">{{ $assignment['label'] }}</div>
                                <div class="small text-muted">
                                    {{ $assignedMenu ? $assignedMenu->name : 'Unassigned' }}
                                </div>
                            </div>
                            @if ($assignedMenu)
                                <a class="btn btn-sm btn-outline-primary"
                                    href="{{ route('cms.appearance.menus.edit', $assignedMenu) }}"
                                    up-follow up-target="[up-main]">
                                    Edit
                                </a>
                            @else
                                <a class="btn btn-sm btn-outline-secondary"
                                    href="{{ route('cms.appearance.menus.create', ['location' => $assignment['key']]) }}"
                                    up-follow up-target="[up-main]">
                                    Assign
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="content-body">
        <x-datagrid
            :url="route('cms.appearance.menus.data')"
            :bulk-action-url="route('cms.appearance.menus.bulk-action')"
            :table-config="$config ?? []"
            :initial-data="$initialData ?? null"
            :empty-config="[
                'icon' => 'ri-compasses-2-line',
                'title' => 'No menus yet',
                'message' => 'Create your first menu to start building navigation.',
                'showAddButton' => true,
                'addButtonText' => 'Create Menu',
                'addButtonUrl' => route('cms.appearance.menus.create'),
            ]"
        />
    </div>

    <script data-up-execute>
        const escapeHtml = (v) => window.DataGrid.escape(v);
        const safeUrl = (v, f) => window.DataGrid.safeUrl(v, f);

        window.DataGrid.registerTemplate('menu_title', function(value, row) {
            const name = escapeHtml(row.name || 'Untitled Menu');
            const editUrl = safeUrl(row.edit_url, '#');
            const descriptionText = row.description ? escapeHtml(row.description) : '';
            const description = descriptionText
                ? `<div class="text-muted small text-truncate" style="max-width: 200px;">${descriptionText}</div>`
                : '';

            return `
                <div class="d-flex flex-column">
                    <a href="${editUrl}" class="fw-semibold text-decoration-none" up-follow up-target="[up-main]">${name}</a>
                    ${description}
                </div>
            `;
        });

        window.DataGrid.registerTemplate('menu_location', function(value, row) {
            const location = row.location_label || row.location;
            if (!location) {
                return '<span class="text-muted small">Not assigned</span>';
            }
            return `<span class="badge bg-primary-subtle text-primary">${escapeHtml(location)}</span>`;
        });

        window.DataGrid.registerTemplate('active_status', function(value, row) {
            const isActive = row.is_active;
            const label = isActive ? 'Active' : 'Inactive';
            const cssClass = isActive ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary';
            return `<span class="badge ${cssClass}">${label}</span>`;
        });
    </script>
</x-app-layout>
