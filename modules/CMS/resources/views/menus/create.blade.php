<x-app-layout :title="__('Create Menu')">
    <x-page-header title="Create Menu"
        description="Define a new navigation menu and optionally assign it to a theme location."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Appearance', 'href' => route('cms.appearance.menus.index')],
            ['label' => 'Menus', 'href' => route('cms.appearance.menus.index')],
            ['label' => 'Create', 'active' => true],
        ]"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Back',
                'icon' => 'ri-arrow-left-s-line',
                'variant' => 'btn-outline-primary',
                'href' => route('cms.appearance.menus.index'),
            ],
        ]" />

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <h6><i class="ri-error-warning-fill me-1"></i>Please fix the following errors:</h6>
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button class="btn-close" data-bs-dismiss="alert" type="button"></button>
                    </div>
                @endif

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Menu Details Form -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="ri-list-unordered-ul me-1"></i> Menu Details</h5>
                            </div>
                            <div class="card-body">
                                <form data-dirty-form id="create-menu-form" method="POST"
                                    action="{{ route('cms.appearance.menus.store') }}">
                                    @csrf

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label" for="name">Menu Name <span
                                                        class="text-danger">*</span></label>
                                                <input class="form-control @error('name') is-invalid @enderror"
                                                    id="name" name="name" type="text"
                                                    value="{{ old('name') }}" placeholder="e.g. Main Navigation"
                                                    required>
                                                @error('name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                                <div class="form-text">A descriptive name for your menu</div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label" for="location">Menu Location</label>
                                                @if (count($locations) > 0)
                                                    <select class="form-select @error('location') is-invalid @enderror"
                                                        id="location" name="location">
                                                        <option value="">Select a location (optional)</option>
                                                        @foreach ($locations as $key => $name)
                                                            <option value="{{ $key }}"
                                                                {{ old('location', request('location')) == $key ? 'selected' : '' }}>
                                                                {{ $name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <div class="form-text">Where this menu will appear on your site
                                                    </div>
                                                @else
                                                    <input name="location" type="hidden" value="">
                                                    <div class="alert alert-warning border-warning mb-2 p-2">
                                                        <small>
                                                            <i class="ri-error-warning-fill me-1"></i>
                                                            <strong>No locations available:</strong> Your current theme
                                                            doesn't define menu locations.
                                                            This menu will be created but won't display on your website.
                                                        </small>
                                                    </div>
                                                    <div class="form-text">
                                                        Configure menu locations in your theme's
                                                        <code>config.json</code> file
                                                    </div>
                                                @endif
                                                @error('location')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="description">Description</label>
                                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                                            rows="3" placeholder="Optional description for this menu">{{ old('description') }}</textarea>
                                        @error('description')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">Help text to remember the purpose of this menu</div>
                                    </div>

                                    <div class="d-flex justify-content-end gap-2">
                                        <a class="btn btn-outline-secondary"
                                            href="{{ route('cms.appearance.menus.index') }}">
                                            Cancel
                                        </a>
                                        <button class="btn btn-primary" type="submit">
                                            <i class="ri-check-line me-1"></i> Create Menu
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Menu Guide -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="ri-information-line me-1"></i> Menu Guide</h5>
                            </div>
                            <div class="card-body">
                                <h6>What is a menu?</h6>
                                <p class="small text-muted mb-3">
                                    Menus are collections of links that help visitors navigate your website. You can
                                    create different menus for different areas of your site.
                                </p>

                                <h6>Next Steps</h6>
                                <p class="small text-muted mb-0">
                                    After creating your menu, you'll be able to add menu items, organize them with drag
                                    & drop, and configure their settings.
                                </p>
                            </div>
                        </div>

                        <!-- Location Status -->
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="ri-map-pin-line me-1"></i> Location Status</h5>
                            </div>
                            <div class="card-body">
                                @if (count($locations) > 0)
                                    <ul class="list-group list-group-flush">
                                        <li
                                            class="list-group-item d-none d-sm-flex justify-content-between align-items-center px-0">
                                            <span class="text-muted small text-uppercase">Location Name</span>
                                            <span class="text-muted small text-uppercase">Status</span>
                                        </li>
                                        @foreach ($locations as $key => $name)
                                            @php
                                                $assignedMenu = $assignedMenus->get($key);
                                            @endphp
                                            <li
                                                class="list-group-item d-block d-sm-flex justify-content-sm-between align-items-sm-center px-0">
                                                <span class="h6 mb-sm-0 d-block mb-2">{{ $name }}</span>
                                                @if ($assignedMenu)
                                                    <div
                                                        class="d-flex align-items-center justify-content-between justify-content-sm-end">
                                                        <a class="text-body text-decoration-none text-truncate me-2"
                                                            href="{{ route('cms.appearance.menus.edit', $assignedMenu->id) }}"
                                                            title="{{ $assignedMenu->name }}">
                                                            {{ $assignedMenu->name }}
                                                        </a>
                                                        <span
                                                            class="badge bg-success-subtle text-success-emphasis rounded-pill flex-shrink-0">
                                                            <i class="ri-check-line"></i> Assigned
                                                        </span>
                                                    </div>
                                                @else
                                                    <span
                                                        class="badge bg-warning-subtle text-warning-emphasis rounded-pill">
                                                        <i class="ri-error-warning-fill"></i> Available
                                                    </span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <div class="py-3 text-center">
                                        <i class="ri-map-pin-line text-muted mb-2" style="font-size: 2rem;"></i>
                                        <p class="text-muted small mb-0">
                                            No menu locations defined in the current theme.
                                        </p>
                                        <p class="text-muted small">
                                            You can still create a menu and use it later.
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script data-up-execute>
            // Form validation
            document.getElementById('create-menu-form').addEventListener('submit', function(e) {
                const name = document.getElementById('name').value.trim();

                if (!name) {
                    e.preventDefault();
                    alert('Please enter a menu name.');
                    document.getElementById('name').focus();
                    return false;
                }

                if (name.length < 3) {
                    e.preventDefault();
                    alert('Menu name must be at least 3 characters long.');
                    document.getElementById('name').focus();
                    return false;
                }
            });

            // Auto-focus on name field
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('name').focus();
            });
        </script>
    @endpush
</x-app-layout>
