@php
    $editingArea = $widgetAreas[0] ?? null;
    $widgetCategoryIcons = [
        'content' => 'ri-article-line',
        'marketing' => 'ri-megaphone-line',
        'forms' => 'ri-mail-line',
        'social' => 'ri-share-line',
        'navigation' => 'ri-map-pin-line',
        'media' => 'ri-image-line',
        'widgets' => 'ri-grid-line',
    ];
@endphp
<x-app-layout :title="__('Edit Widget Area: ' . ($editingArea['name'] ?? 'Unknown'))">
    @php
        $areaWidgets = $currentWidgets[$editingArea['id']] ?? [];
    @endphp
    <x-page-header title="Editing : {{ $editingArea['name'] ?? 'Unknown' }}"
        description="Configure widget content and layout for this theme area."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Appearance', 'href' => route('cms.appearance.themes.index')],
            ['label' => 'Widgets', 'href' => route('cms.appearance.widgets.index')],
            ['label' => $editingArea['name'] ?? 'Widget Area', 'active' => true],
        ]"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Back',
                'icon' => 'ri-arrow-left-line',
                'variant' => 'btn-outline-primary',
                'href' => route('cms.appearance.widgets.index'),
            ],
        ]" />

    <x-script-loader :wrap="false" :styles="['modules/CMS/resources/css/widget-edit.css']" />

    <script data-up-execute>
        window.widgetCategoryIcons = window.widgetCategoryIcons ?? @json($widgetCategoryIcons);
    </script>

    <div id="widget-editor-container">
        <div class="row">
            <div class="col-xl-8 mb-4">
                <div class="card widget-area-section">
                    <div class="card-header mb-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="mb-0">
                                <i class="ri-layout-grid-line me-1"></i> Widget Structure
                                <span class="badge text-bg-primary rounded-pill ms-2"
                                    id="widget-items-count-structure">{{ count($areaWidgets) }} items</span>
                            </h5>
                            <span class="badge bg-warning-subtle text-warning-emphasis widget-changes-indicator"
                                id="widget-changes-indicator" style="display: none;">
                                <i class="ri-pencil-line me-1"></i> Unsaved Changes
                            </span>
                        </div>
                        @if ($editingArea['description'] ?? false)
                            <p class="mb-0 mt-2 text-muted small">{{ $editingArea['description'] }}</p>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="widget-area-container" data-area-id="{{ $editingArea['id'] }}">
                            @if (count($areaWidgets) > 0)
                                @foreach ($areaWidgets as $widget)
                                    @php
                                        $widgetInfo = $availableWidgets[$widget['type']] ?? null;
                                        $widgetName =
                                            $widgetInfo['name'] ??
                                            ucwords(str_replace(['-', '_'], ' ', $widget['type']));
                                        $icon = $widgetCategoryIcons[$widgetInfo['category'] ?? 'widgets'] ?? 'ri-grid-line';
                                    @endphp
                                    <div class="widget-item" data-widget-id="{{ $widget['id'] }}"
                                        data-widget-key="{{ $widget['type'] }}" draggable="true">
                                        <div class="widget-item-content">
                                            <div class="widget-drag-handle" title="Drag to reorder" data-id="{{ $widget['id'] }}">
                                                <i class="ri-draggable"></i>
                                            </div>

                                            <div class="widget-item-info flex-grow-1">
                                                <div class="widget-item-main-line">
                                                    <span class="widget-title">{{ $widget['title'] ?: 'Untitled Widget' }}</span>
                                                </div>
                                                <small class="text-muted d-block text-truncate">
                                                    <i class="{{ $icon }} me-1"></i>{{ $widgetName }}
                                                    @if (isset($widgetInfo['category']))
                                                        <span class="badge text-bg-secondary ms-1">{{ ucfirst($widgetInfo['category']) }}</span>
                                                    @endif
                                                </small>
                                            </div>

                                            <div class="widget-item-actions">
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-light-primary edit-widget"
                                                        data-id="{{ $widget['id'] }}" title="Edit">
                                                        <i class="ri-pencil-line"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-light-danger remove-widget"
                                                        data-id="{{ $widget['id'] }}" title="Remove">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="empty-area-message">
                                    <i class="ri-layout-grid-line display-4 text-body-tertiary mb-3"></i>
                                    <h5 class="mb-1">No widgets in this area</h5>
                                    <p class="text-muted mb-0">Add widgets from the panel on the right.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 mb-4">
                <div class="card sticky-top widget-library-panel" style="top: 1rem;">
                    <div class="card-header mb-3">
                        <h5 class="mb-0"><i class="ri-puzzle-line me-1"></i> Widget Library</h5>
                    </div>
                    <div class="card-body available-widgets-container">
                        @php
                            $groupedWidgets = collect($availableWidgets)->groupBy('category', true);
                            $categoryIcons = [
                                'content' => 'ri-file-list-3-line',
                                'marketing' => 'ri-megaphone-line',
                                'forms' => 'ri-mail-line',
                                'social' => 'ri-share-line',
                                'navigation' => 'ri-compass-line',
                                'media' => 'ri-image-line',
                                'widgets' => 'ri-grid-line',
                            ];
                        @endphp
                        <div class="accordion" id="widget-library-accordion">
                            @foreach ($groupedWidgets as $category => $widgets)
                                <div class="accordion-item add-item-section">
                                    <h2 class="accordion-header" id="heading-widget-{{ $category }}">
                                        <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#collapse-widget-{{ $category }}"
                                            aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                            aria-controls="collapse-widget-{{ $category }}">
                                            <i class="{{ $categoryIcons[$category] ?? 'ri-layout-column-line' }} me-2"></i>
                                            {{ ucfirst($category) }}
                                            <span class="badge text-bg-secondary ms-2">{{ count($widgets) }}</span>
                                        </button>
                                    </h2>
                                    <div id="collapse-widget-{{ $category }}"
                                        class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                                        aria-labelledby="heading-widget-{{ $category }}">
                                        <div class="accordion-body">
                                            <div class="add-items-list">
                                                @foreach ($widgets as $key => $widget)
                                                    <button type="button" class="add-item-btn add-widget-btn"
                                                        data-widget-key="{{ $key }}"
                                                        data-widget-name="{{ $widget['name'] }}">
                                                        <i class="{{ $categoryIcons[$widget['category'] ?? 'widgets'] ?? 'ri-puzzle-line' }}"></i>
                                                        <span class="item-title">{{ $widget['name'] }}</span>
                                                        <i class="ri-add-circle-line add-icon"></i>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if (count($availableWidgets) === 0)
                            <div class="text-center text-muted py-4">
                                <i class="ri-error-warning-line display-4 text-body-tertiary mb-3"></i>
                                <h5 class="mb-1">No widgets available</h5>
                                <p class="text-muted mb-0">Check your theme's widgets folder.</p>
                            </div>
                        @endif

                        <div class="alert alert-light border mt-3 mb-0">
                            <strong class="d-block mb-1">Tips</strong>
                            <small class="text-muted">
                                Drag widgets to reorder. Use <kbd>Ctrl</kbd>+<kbd>S</kbd> to save quickly.
                            </small>
                        </div>

                        <div class="d-grid mt-3">
                            <button class="btn btn-primary btn-lg" id="save-all-widgets" type="button">
                                <i class="ri-save-line me-2"></i>Save Widgets
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="widget-save-bar shadow-lg border-top" id="floating-widget-save-bar" style="display: none;">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
                <div class="d-flex align-items-center text-warning-emphasis">
                    <i class="ri-pencil-line me-2"></i>
                    <span>You have unsaved widget changes.</span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm" id="discard-widget-changes" type="button">
                        Discard
                    </button>
                    <button class="btn btn-primary btn-sm" id="save-widgets-floating" type="button">
                        <i class="ri-save-line me-1"></i>Save Widgets
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Reorder Modal -->
    <div class="modal fade" id="widgetReorderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0" id="reorder-widget-title">Move Widget</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <input type="hidden" id="reorder-widget-id">
                    <div class="d-flex flex-column align-items-center gap-2">
                        <!-- Up -->
                        <button type="button" class="btn btn-outline-primary btn-lg reorder-btn" data-direction="up" title="Move Up">
                            <i class="ri-arrow-up-line"></i>
                        </button>
                        <!-- Down -->
                        <button type="button" class="btn btn-outline-primary btn-lg reorder-btn" data-direction="down" title="Move Down">
                            <i class="ri-arrow-down-line"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Widget Modal -->
    <div class="modal fade" id="widgetEditModal" tabindex="-1" aria-labelledby="widgetEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="widgetEditModalLabel">Edit Widget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="widgetEditForm">
                    <div class="modal-body">
                        <input type="hidden" id="edit-widget-id">
                        <input type="hidden" id="edit-area-id">

                        <!-- Title Field (Always Present) -->
                        <div class="mb-3">
                            <label for="widget-title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="widget-title" name="title" required>
                            <div class="invalid-feedback" id="widget-title-error"></div>
                        </div>

                        <!-- Dynamic Settings Container -->
                        <div id="widget-settings-container">
                            <!-- Settings fields will be dynamically inserted here -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="save-widget-btn">
                            <i class="ri-save-line me-1"></i> Save Widget
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Widget Confirmation Modal -->
    <div class="modal fade" id="widgetDeleteModal" tabindex="-1" aria-labelledby="widgetDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="widgetDeleteModalLabel">Remove Widget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="delete-widget-id">
                    <p class="mb-0">Are you sure you want to remove "<strong id="delete-widget-title">this widget</strong>"?</p>
                    <p class="text-muted small mb-0 mt-2">This action can be undone by not saving changes.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-delete-widget">
                        <i class="ri-delete-bin-line me-1"></i> Remove
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1055;"></div>

    <script data-up-execute>
        window.widgetUrl = "{{ route('cms.appearance.widgets.save-all') }}";
        window.widgetAreas = @json($widgetAreas);
        window.availableWidgets = @json($availableWidgets);
        window.currentWidgets = @json($currentWidgets);
    </script>
    <x-script-loader :wrap="false" :scripts="[
        'modules/CMS/resources/js/widget-editor/index.js',
    ]" />
</x-app-layout>
