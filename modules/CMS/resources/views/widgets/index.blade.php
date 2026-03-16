<x-app-layout :title="__('Widgets')">

    @php
        $actions = [
            [
                'type' => 'link',
                'label' => 'Theme Customizer',
                'icon' => 'ri-brush-line',
                'variant' => 'btn-outline-primary',
                'href' => route('cms.appearance.themes.customizer.index'),
            ],
        ];
    @endphp

    <x-page-header title="Widget Areas"
        description="Manage content for the widget-ready sections of your theme."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Appearance', 'href' => route('cms.appearance.themes.index')],
            ['label' => 'Widgets', 'active' => true],
        ]"
        :actions="$actions" />

    <div class="row">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button class="btn-close" data-bs-dismiss="alert" type="button"></button>
            </div>
        @endif

        <div class="row">
            @if (count($widgetAreas) > 0)
                @foreach ($widgetAreas as $area)
                    @php
                        $areaWidgets = $currentWidgets[$area['id']] ?? [];
                        $widgetCount = count($areaWidgets);
                    @endphp
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="card-title mb-1">{{ $area['name'] }}</h5>
                                        <p class="text-muted small mb-2">{{ $area['description'] }}</p>
                                    </div>
                                    <span
                                        class="badge rounded-pill {{ $widgetCount > 0 ? 'bg-primary' : 'text-bg-secondary' }}"
                                        style="font-size: 0.75rem;">
                                        {{ $widgetCount }} {{ Str::plural('widget', $widgetCount) }}
                                    </span>
                                </div>

                                @if ($widgetCount > 0)
                                    <ul class="list-group list-group-flush small flex-grow-1 mb-3">
                                        @foreach (array_slice($areaWidgets, 0, 4) as $widget)
                                            @php
                                                $widgetKey = $widget['type'];
                                                $widgetDetails = $availableWidgets[$widgetKey] ?? null;
                                                // Map common Remix defaults when missing entry data
                                                $defaultIcon = 'ri-puzzle-line';
                                                $widgetIcon = $widgetDetails['icon'] ?? $defaultIcon;
                                                // Normalize legacy icon identifiers to Remix defaults
                                                if (Str::contains($widgetIcon, 'puzzle')) {
                                                    $widgetIcon = 'ri-puzzle-line';
                                                } else {
                                                    $normalizedIcon = is_string($widgetIcon)
                                                        ? Str::of($widgetIcon)->trim()
                                                        : Str::of($defaultIcon);

                                                    $widgetIcon = $normalizedIcon
                                                        ->replaceMatches('/\\bbi\\s+bi-/', 'ri-')
                                                        ->replace('bi-', 'ri-')
                                                        ->replace('bi ', '')
                                                        ->replace('ri ', 'ri-')
                                                        ->replaceMatches('/\s+/', ' ')
                                                        ->trim()
                                                        ->toString();

                                                    if (!Str::contains($widgetIcon, 'ri-')) {
                                                        $widgetIcon = $defaultIcon;
                                                    }
                                                }
                                            @endphp
                                            <li
                                                class="list-group-item d-flex align-items-center bg-transparent px-0 py-2">
                                                <i class="{{ $widgetIcon }} text-primary fs-5 me-3"></i>
                                                <div class="flex-grow-1">
                                                    <div class="fw-medium text-truncate">
                                                        {{ $widget['title'] ?: 'Untitled Widget' }}</div>
                                                    <small
                                                        class="text-muted">{{ $widgetDetails['name'] ?? Str::title(str_replace(['-', '_'], ' ', $widgetKey)) }}</small>
                                                </div>
                                            </li>
                                        @endforeach
                                        @if ($widgetCount > 4)
                                            <li class="list-group-item text-muted bg-transparent px-0 py-1">...and
                                                {{ $widgetCount - 4 }} more.</li>
                                        @endif
                                    </ul>
                                @else
                                    <div class="text-muted my-auto text-center">
                                        <div class="mb-2"><i class="ri-inbox-line fs-2"></i></div>
                                        <p class="mb-0">This area is empty.</p>
                                    </div>
                                @endif

                                <div class="mt-auto text-end">
                                    <a class="btn btn-primary"
                                        href="{{ route('cms.appearance.widgets.edit', ['area_id' => $area['id']]) }}">
                                        <i class="ri-pencil-line me-1"></i> Manage Widgets
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="col-12">
                    <div class="alert alert-warning">
                        <h5 class="alert-heading">No Widget Areas Found</h5>
                        <p>The active theme does not define any widget areas. You can define them in your theme's
                            <code>config/config.json</code> file.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
