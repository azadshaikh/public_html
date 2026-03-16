<x-app-layout :title="__('Edit Menu: ' . $menu->name)">
    <x-page-header title="Edit Menu: {{ $menu->name }}"
        description="Update menu settings, locations, and navigation items." :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Appearance', 'href' => route('cms.appearance.menus.index')],
            ['label' => 'Menus', 'href' => route('cms.appearance.menus.index')],
            ['label' => $menu->name, 'active' => true],
        ]" :actions="[
            [
                'type' => 'link',
                'label' => 'Back',
                'icon' => 'ri-arrow-left-s-line',
                'variant' => 'btn-outline-primary',
                'href' => route('cms.appearance.menus.index'),
            ],
        ]" />

    <x-script-loader :wrap="false" :styles="[
        'modules/CMS/resources/css/menu-edit.css',
    ]" />

    <div class="row">
        <div class="col-12">
            <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1055;"></div>

            <div class="card mb-4">
                <div class="card-header pb-2">
                    <h5 class="mb-1"><i class="ri-settings-3-line me-1"></i> Menu Settings</h5>
                    <p class="mb-0 text-muted small">Set menu identity and publishing behavior.</p>
                </div>
                <div class="card-body">
                    <form data-dirty-form id="menu-settings-form" method="POST"
                        action="{{ route('cms.appearance.menus.update', $menu) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" id="description" name="description"
                            value="{{ old('description', $menu->description) }}">
                        <div class="row g-3">
                            <div class="col-lg-5">
                                <label class="form-label mb-1" for="name">Menu Name <span class="text-danger">*</span></label>
                                <input class="form-control" id="name" name="name" type="text"
                                    value="{{ old('name', $menu->name) }}" required>
                                <div class="invalid-feedback" id="name-error"></div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <label class="form-label mb-1" for="location">Location</label>
                                <select class="form-select" id="location" name="location">
                                    <option value="">No location</option>
                                    @foreach ($locations as $key => $name)
                                        <option value="{{ $key }}"
                                            {{ old('location', $menu->location) == $key ? 'selected' : '' }}>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback" id="location-error"></div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label class="form-label mb-1" for="is_active">Status</label>
                                <select class="form-select" id="is_active" name="is_active" required>
                                    <option value="1" {{ old('is_active', $menu->is_active) ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ !old('is_active', $menu->is_active) ? 'selected' : '' }}>Inactive</option>
                                </select>
                                <div class="invalid-feedback" id="is_active-error"></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-8 mb-4">
                    <div class="card">
                        <div class="card-header mb-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h5 class="mb-0">
                                    <i class="ri-list-unordered me-1"></i> Menu Structure
                                    <span class="badge text-bg-primary rounded-pill ms-2"
                                        id="menu-items-count-structure">{{ $menu->allItems->count() }} items</span>
                                </h5>
                                <span class="badge bg-warning-subtle text-warning-emphasis" id="changes-indicator"
                                    style="display: none;">
                                    <i class="ri-pencil-line me-1"></i> Unsaved Changes
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="menu-builder" id="menu-builder">
                                @if ($menu->allItems->count() > 0)
                                    <div class="sortable-menu" id="menu-items">
                                        @foreach ($menu->allItems->sortBy('sort_order') as $item)
                                            @include('cms::menus.partials.menu-item', [
                                                'item' => $item,
                                            ])
                                        @endforeach
                                    </div>
                                @else
                                    <div class="rounded-3 bg-body-secondary border-2 border-dashed p-5 text-center"
                                        id="empty-state">
                                        <i class="ri-book-mark-line display-4 text-body-tertiary mb-3"></i>
                                        <h5 class="mb-1">Your menu is empty</h5>
                                        <p class="text-muted mb-0">Add items from the library panel and then arrange them here.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 mb-4">
                    <div class="card menu-library-panel">
                        <div class="card-header mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="ri-add-box-line me-1"></i> Item Library</h5>
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal"
                                    data-bs-target="#addItemModal">
                                    Advanced
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="accordion" id="item-library-accordion">
                                <div class="accordion-item add-item-section">
                                    <h2 class="accordion-header" id="heading-custom-link">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#collapse-custom-link" aria-expanded="true"
                                            aria-controls="collapse-custom-link">
                                            <i class="ri-link me-2"></i>Custom Link
                                        </button>
                                    </h2>
                                    <div id="collapse-custom-link" class="accordion-collapse collapse show"
                                        aria-labelledby="heading-custom-link">
                                        <div class="accordion-body">
                                            <form id="add-custom-item-form" novalidate data-no-unpoly>
                                                <div class="mb-3">
                                                    <label class="form-label small" for="custom-title">
                                                        Link Text <span class="text-danger">*</span>
                                                    </label>
                                                    <input class="form-control form-control-sm" id="custom-title" name="custom-title"
                                                        type="text" placeholder="e.g., About Us" required maxlength="255"
                                                        autocomplete="off">
                                                    <div class="invalid-feedback" id="custom-title-error">
                                                        Link text is required
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small" for="custom-url">URL</label>
                                                    <input class="form-control form-control-sm" id="custom-url" name="custom-url"
                                                        type="text" placeholder="https://example.com or /about"
                                                        maxlength="500" autocomplete="off">
                                                    <div class="form-text small">Leave empty for "#"</div>
                                                </div>
                                                <button class="btn btn-primary btn-sm w-100" type="submit">
                                                    <i class="ri-add-line me-1"></i> Add to Menu
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item add-item-section">
                                    <h2 class="accordion-header" id="heading-pages">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#collapse-pages" aria-expanded="false" aria-controls="collapse-pages">
                                            <i class="ri-file-text-line me-2"></i>Pages
                                            <span class="badge text-bg-secondary ms-2">{{ count($pages) + 1 }}</span>
                                        </button>
                                    </h2>
                                    <div id="collapse-pages" class="accordion-collapse collapse" aria-labelledby="heading-pages">
                                        <div class="accordion-body">
                                            <div class="mb-2">
                                                <input class="form-control form-control-sm search-filter" type="search"
                                                    placeholder="Search pages..." id="search-pages" data-list="#pages-list"
                                                    autocomplete="off">
                                            </div>
                                            <div class="add-items-list" id="pages-list">
                                                <button type="button" class="add-item-btn add-page-item"
                                                    data-type="home" data-url="/" data-title="Home">
                                                    <i class="ri-home-line"></i>
                                                    <span class="item-title">Home Page</span>
                                                    <i class="ri-add-circle-line add-icon"></i>
                                                </button>
                                                @foreach ($pages as $page)
                                                    <button type="button" class="add-item-btn add-page-item"
                                                        data-type="page" data-id="{{ $page->id }}"
                                                        data-url="{{ $page->permalink_url }}"
                                                        data-title="{{ $page->title }}">
                                                        <i class="ri-file-text-line"></i>
                                                        <span class="item-title">{{ $page->title }}</span>
                                                        <i class="ri-add-circle-line add-icon"></i>
                                                    </button>
                                                @endforeach
                                            </div>
                                            @if (count($pages) === 0)
                                                <div class="text-center text-muted py-3">
                                                    <i class="ri-file-warning-line d-block mb-1" style="font-size: 1.25rem;"></i>
                                                    <small>No pages available</small>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if (count($categories) > 0)
                                    <div class="accordion-item add-item-section">
                                        <h2 class="accordion-header" id="heading-categories">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#collapse-categories" aria-expanded="false"
                                                aria-controls="collapse-categories">
                                                <i class="ri-folder-line me-2"></i>Categories
                                                <span class="badge text-bg-secondary ms-2">{{ count($categories) }}</span>
                                            </button>
                                        </h2>
                                        <div id="collapse-categories" class="accordion-collapse collapse"
                                            aria-labelledby="heading-categories">
                                            <div class="accordion-body">
                                                <div class="mb-2">
                                                    <input class="form-control form-control-sm search-filter" type="search"
                                                        placeholder="Search categories..." id="search-categories"
                                                        data-list="#categories-list" autocomplete="off">
                                                </div>
                                                <div class="add-items-list" id="categories-list">
                                                    @foreach ($categories as $category)
                                                        <button type="button" class="add-item-btn add-page-item"
                                                            data-type="category" data-id="{{ $category->id }}"
                                                            data-url="{{ $category->permalink_url }}"
                                                            data-title="{{ $category->title }}">
                                                            <i class="ri-folder-line"></i>
                                                            <span class="item-title">{{ $category->title }}</span>
                                                            <i class="ri-add-circle-line add-icon"></i>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if (isset($tags) && count($tags) > 0)
                                    <div class="accordion-item add-item-section">
                                        <h2 class="accordion-header" id="heading-tags">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#collapse-tags" aria-expanded="false" aria-controls="collapse-tags">
                                                <i class="ri-price-tag-3-line me-2"></i>Tags
                                                <span class="badge text-bg-secondary ms-2">{{ count($tags) }}</span>
                                            </button>
                                        </h2>
                                        <div id="collapse-tags" class="accordion-collapse collapse" aria-labelledby="heading-tags">
                                            <div class="accordion-body">
                                                <div class="mb-2">
                                                    <input class="form-control form-control-sm search-filter" type="search"
                                                        placeholder="Search tags..." id="search-tags" data-list="#tags-list"
                                                        autocomplete="off">
                                                </div>
                                                <div class="add-items-list" id="tags-list">
                                                    @foreach ($tags as $tag)
                                                        <button type="button" class="add-item-btn add-page-item"
                                                            data-type="tag" data-id="{{ $tag->id }}"
                                                            data-url="{{ $tag->permalink_url ?? '/tag/' . $tag->slug }}"
                                                            data-title="{{ $tag->title }}">
                                                            <i class="ri-price-tag-3-line"></i>
                                                            <span class="item-title">{{ $tag->title }}</span>
                                                            <i class="ri-add-circle-line add-icon"></i>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="alert alert-light border mt-3 mb-0">
                                <strong class="d-block mb-1">Tips</strong>
                                <small class="text-muted">
                                    Drag items to reorder and nest. Use <kbd>Ctrl</kbd>+<kbd>S</kbd> to save quickly.
                                </small>
                            </div>

                            <div class="d-grid mt-3">
                                <button class="btn btn-primary btn-lg" id="save-entire-menu" type="button">
                                    <i class="ri-save-line me-2"></i>Save Menu
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card border-danger mt-3">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <h6 class="text-danger mb-1">
                                        <i class="ri-error-warning-line me-1"></i>Danger Zone
                                    </h6>
                                    <p class="text-muted small mb-0">Permanently delete this menu and all associated items.</p>
                                </div>
                                <button class="btn btn-outline-danger btn-sm" type="button" data-bs-toggle="modal"
                                    data-bs-target="#deleteMenuModal">
                                    <i class="ri-delete-bin-line me-1"></i>Delete Menu
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="menu-save-bar shadow-lg border-top" id="floating-save-bar" style="display: none;">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
                <div class="d-flex align-items-center text-warning-emphasis">
                    <i class="ri-pencil-line me-2"></i>
                    <span>You have unsaved menu changes.</span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm" id="discard-menu-changes" type="button">
                        Discard
                    </button>
                    <button class="btn btn-primary btn-sm" id="save-menu-floating" type="button">
                        <i class="ri-save-line me-1"></i>Save Menu
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Menu Confirmation Modal -->
    <div class="modal fade" id="deleteMenuModal" aria-labelledby="deleteMenuModalLabel" aria-hidden="true"
        tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteMenuModalLabel">
                        <i class="ri-error-warning-line text-danger me-2"></i>
                        Confirm Menu Deletion
                    </h5>
                    <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to permanently delete the menu "<strong>{{ $menu->name }}</strong>"?</p>
                    <div class="alert alert-danger">
                        <i class="ri-alert-line me-1"></i>
                        <strong>Warning:</strong> This will permanently delete the menu and all its associated items.
                        This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
                    <form class="d-inline" action="{{ route('cms.appearance.menus.destroy', $menu->id) }}"
                        method="POST">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger" type="submit">
                            <i class="ri-delete-bin-line me-1"></i> Delete This Menu
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Menu Item Modal -->
    <div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editItemModalLabel">
                        <i class="ri-pencil-line me-2"></i>Edit Menu Item
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="edit-item-form" novalidate>
                        @include('cms::menus.partials.item-form', [
                            'prefix' => 'edit',
                            'isEdit' => true,
                            'itemTypes' => $itemTypes,
                            'itemTargets' => $itemTargets,
                            'pages' => $pages,
                        ])
                    </form>
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <small class="text-danger" id="edit-modal-error-summary" style="display: none;">
                            <i class="ri-error-warning-line me-1"></i>Please fix the errors above.
                        </small>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="ri-close-line me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="save-item-btn">
                        <i class="ri-save-line me-1"></i>Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Menu Item Modal (Advanced) -->
    <div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addItemModalLabel">
                        <i class="ri-add-circle-line me-2"></i>Add Menu Item
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="add-item-form" novalidate>
                        @include('cms::menus.partials.item-form', [
                            'prefix' => 'add',
                            'isEdit' => false,
                            'itemTypes' => $itemTypes,
                            'itemTargets' => $itemTargets,
                            'pages' => $pages,
                        ])
                    </form>
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <small class="text-danger" id="add-modal-error-summary" style="display: none;">
                            <i class="ri-error-warning-line me-1"></i>Please fix the errors above.
                        </small>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="ri-close-line me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="add-item-btn">
                        <i class="ri-add-line me-1"></i>Add to Menu
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ri-error-warning-line text-danger me-2"></i>
                        Confirm Deletion
                    </h5>
                    <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0">
                            <i class="ri-alert-line text-warning" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-2">Are you sure you want to delete this menu item?</p>
                            <div class="alert alert-light border">
                                <strong id="delete-item-title">Menu Item Title</strong>
                                <br>
                                <small class="text-muted" id="delete-item-url">URL</small>
                            </div>
                            <p class="text-muted small mb-0">
                                <i class="ri-information-line me-1"></i>
                                This action cannot be undone. Any child menu items will also be deleted.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">
                        <i class="ri-close-line me-1"></i> Cancel
                    </button>
                    <button class="btn btn-danger" id="confirm-delete-btn" type="button">
                        <i class="ri-delete-bin-line me-1"></i> Delete Item
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Reorder Modal -->
    <div class="modal fade" id="reorderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0" id="reorder-item-title">Move Item</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <input type="hidden" id="reorder-item-id">
                    <div class="d-flex flex-column align-items-center gap-2">
                        <!-- Up -->
                        <button type="button" class="btn btn-outline-primary btn-lg reorder-btn" data-direction="up" title="Move Up">
                            <i class="ri-arrow-up-line"></i>
                        </button>
                        <!-- Left / Right -->
                        <div class="d-flex gap-3">
                            <button type="button" class="btn btn-outline-secondary btn-lg reorder-btn" data-direction="left" title="Outdent (Move Left)">
                                <i class="ri-arrow-left-line"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-lg reorder-btn" data-direction="right" title="Indent (Move Right)">
                                <i class="ri-arrow-right-line"></i>
                            </button>
                        </div>
                        <!-- Down -->
                        <button type="button" class="btn btn-outline-primary btn-lg reorder-btn" data-direction="down" title="Move Down">
                            <i class="ri-arrow-down-line"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script data-up-execute>
        window.menuUrl = "{{ route('cms.appearance.menus.save-all', $menu->id) }}";
        window.menuLocations = @json($locations);
        window.menuSettings = {
            supportsHierarchy: {{ Js::from($menuSettings['support_hierarchy'] ?? true) }},
            maxDepth: {{ Js::from($menuSettings['max_depth'] ?? 3) }}
        };
        @if (session('success'))
            window.sessionMessage = {
                type: 'success',
                message: @json(session('success'))
            };
        @elseif (session('error'))
            window.sessionMessage = {
                type: 'error',
                message: @json(session('error'))
            };
        @endif
    </script>
    <x-script-loader :wrap="false" :scripts="[
        'modules/CMS/resources/js/menu-editor/index.js',
    ]" />

</x-app-layout>
