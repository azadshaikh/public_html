<x-app-layout :title="__('Themes')">

    @php
        $actions = [];
        $actions[] = [
            'type' => 'button',
            'label' => 'Import Theme',
            'icon' => 'ri-upload-cloud-line',
            'variant' => 'btn-outline-secondary',
            'href' => route('cms.appearance.themes.import'),
            'attributes' => [
                'data-bs-toggle' => 'modal',
                'data-bs-target' => '#importModal',
            ],
        ];
        // Get active theme from all themes (before filtering), so it always shows
        $activeTheme = collect($themes)->first(function($t) {
            // Find by is_active OR by checking the repository
            return $t['is_active'] ?? false;
        }) ?? \Modules\CMS\Models\Theme::getActiveTheme();
    @endphp
    <x-page-header title="Themes"
        description="Manage the active theme and explore installed designs."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Appearance', 'href' => route('cms.appearance.themes.index')],
            ['label' => 'Themes', 'active' => true],
        ]"
        :actions="$actions" />

    <!-- Active Theme Card -->
    @if($activeTheme)
    <div class="card mb-4 border-primary">
        <div class="card-body p-3">
            <div class="row align-items-center">
                <div class="col-auto">
                    @if ($activeTheme['screenshot'])
                        <img src="{{ $activeTheme['screenshot'] }}" alt="{{ $activeTheme['name'] }}"
                            class="rounded" style="height: 80px; width: 120px; object-fit: cover;">
                    @else
                        <div class="bg-light rounded d-flex align-items-center justify-content-center"
                            style="height: 80px; width: 120px;">
                            <i class="ri-image-line fa-2x text-muted"></i>
                        </div>
                    @endif
                </div>
                <div class="col">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h5 class="mb-0">{{ $activeTheme['name'] }}</h5>
                        <span class="badge bg-primary">Active</span>
                        @if (\Modules\CMS\Models\Theme::isChildTheme($activeTheme['directory']))
                            <span class="badge bg-info-subtle text-info">
                                <i class="ri-git-branch-line"></i> Child of {{ \Modules\CMS\Models\Theme::getParentTheme($activeTheme['directory']) }}
                            </span>
                        @endif
                    </div>
                    <p class="text-muted mb-1 small">{{ $activeTheme['description'] }}</p>
                    <div class="d-flex align-items-center gap-3 text-muted small">
                        <span><strong>Version:</strong> {{ $activeTheme['version'] }}</span>
                        <span><strong>Author:</strong>
                            @if ($activeTheme['author_uri'])
                                <a href="{{ $activeTheme['author_uri'] }}" target="_blank">{{ $activeTheme['author'] }}</a>
                            @else
                                {{ $activeTheme['author'] }}
                            @endif
                        </span>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="btn-group btn-group-sm">
                        <a class="btn btn-info" href="{{ route('cms.appearance.themes.customizer.index') }}" target="_blank">
                            <i class="ri-brush-line me-1"></i>Customize
                        </a>
                        <a class="btn btn-outline-secondary" href="{{ route('cms.appearance.themes.editor.index', $activeTheme['directory']) }}" target="_blank">
                            <i class="ri-code-line me-1"></i>Edit
                        </a>
                        <a class="btn btn-outline-secondary" href="{{ route('cms.appearance.themes.export', $activeTheme['directory']) }}" up-follow="false">
                            <i class="ri-download-line me-1"></i>Export
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Search and Filter -->
    <div class="row mb-4">
        <div class="col-md-6">
            <form class="d-flex" method="GET" up-target="[up-main]">
                <input class="form-control me-2" name="search" type="text" value="{{ $search }}"
                    placeholder="Search themes...">
                @if($filter)
                    <input type="hidden" name="filter" value="{{ $filter }}">
                @endif
                <button class="btn btn-outline-secondary" type="submit">
                    <i class="ri-search-line"></i>
                </button>
            </form>
        </div>
        <div class="col-md-6">
            <div class="d-flex justify-content-end">
                <div class="btn-group" role="group">
                    <a class="btn btn-outline-secondary {{ !$filter ? 'active' : '' }}"
                        href="{{ route('cms.appearance.themes.index') }}">
                        All
                    </a>
                    <a class="btn btn-outline-secondary {{ $filter === 'active' ? 'active' : '' }}"
                        href="{{ route('cms.appearance.themes.index', ['filter' => 'active']) }}">
                        Active
                    </a>
                    <a class="btn btn-outline-secondary {{ $filter === 'inactive' ? 'active' : '' }}"
                        href="{{ route('cms.appearance.themes.index', ['filter' => 'inactive']) }}">
                        Inactive
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Themes Grid -->
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xxl-5 g-3">
        @forelse($themes as $themeKey => $theme)
            <div class="col">
                <div class="card theme-card p-0 h-100 {{ $theme['is_active'] ? 'border-primary' : '' }}">
                    @if ($theme['screenshot'])
                        <img class="card-img-top" src="{{ $theme['screenshot'] }}" alt="{{ $theme['name'] }} Screenshot"
                            style="height: 140px; object-fit: cover;">
                    @else
                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center"
                            style="height: 140px;">
                            <i class="ri-image-line fa-2x text-muted"></i>
                        </div>
                    @endif

                    <div class="card-body p-2">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="card-title mb-0 text-truncate" style="max-width: 120px;">
                                <a class="text-decoration-none{{ $theme['is_active'] ? ' text-primary fw-semibold' : ' text-dark' }}"
                                    href="{{ route('cms.appearance.themes.editor.index', $theme['directory']) }}"
                                    title="{{ $theme['name'] }}">{{ $theme['name'] }}</a>
                            </h6>
                            <div class="d-flex flex-wrap gap-1 flex-shrink-0">
                                @if ($theme['is_active'])
                                    <span class="badge bg-primary" style="font-size: 0.65rem;">Active</span>
                                @endif
                                @if (\Modules\CMS\Models\Theme::isChildTheme($theme['directory']))
                                    <span class="badge bg-info-subtle text-info" style="font-size: 0.65rem;" title="Child of {{ \Modules\CMS\Models\Theme::getParentTheme($theme['directory']) }}">
                                        <i class="ri-git-branch-line"></i> Child
                                    </span>
                                @endif
                                @if (\Modules\CMS\Models\Theme::hasChildThemes($theme['directory']))
                                    @php $childCount = count(\Modules\CMS\Models\Theme::getChildThemes($theme['directory'])); @endphp
                                    <span class="badge bg-warning-subtle text-warning" style="font-size: 0.65rem;" title="Has {{ $childCount }} child theme(s)">
                                        <i class="ri-parent-line"></i> {{ $childCount }} {{ Str::plural('child', $childCount) }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if (\Modules\CMS\Models\Theme::isChildTheme($theme['directory']))
                            <p class="small text-info mb-1" style="font-size: 0.7rem;">
                                <i class="ri-arrow-up-line"></i> Inherits from: <strong>{{ \Modules\CMS\Models\Theme::getParentTheme($theme['directory']) }}</strong>
                            </p>
                        @endif

                        <p class="card-text text-muted mb-1 small text-truncate-2" style="font-size: 0.75rem; line-height: 1.3;">{{ $theme['description'] }}</p>

                        <div class="theme-meta" style="font-size: 0.7rem;">
                            <small class="text-muted">
                                <strong>Version:</strong> {{ $theme['version'] }}<br>
                                <strong>Author:</strong>
                                @if ($theme['author_uri'])
                                    <a href="{{ $theme['author_uri'] }}" target="_blank">{{ $theme['author'] }}</a>
                                @else
                                    {{ $theme['author'] }}
                                @endif
                            </small>
                        </div>

                        @if (is_array($theme['tags']) && count($theme['tags']) > 0)
                            <div class="theme-tags mt-1">
                                @foreach (array_slice($theme['tags'], 0, 3) as $tag)
                                    <span class="badge bg-light text-dark" style="font-size: 0.6rem;">{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="card-footer bg-transparent p-2">
                        @if ($theme['is_active'])
                            <div class="btn-group btn-group-sm w-100" role="group">
                                <a class="btn btn-info" target="_blank"
                                    href="{{ route('cms.appearance.themes.customizer.index') }}"
                                    title="Customize">
                                    <i class="ri-brush-line"></i><span class="d-none d-xl-inline ms-1">Customize</span>
                                </a>
                                <a class="btn btn-outline-secondary" target="_blank"
                                    href="{{ route('cms.appearance.themes.editor.index', $theme['directory']) }}"
                                    title="Edit Files">
                                    <i class="ri-code-line"></i><span class="d-none d-xl-inline ms-1">Edit</span>
                                </a>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="ri-more-2-fill"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <form method="POST" action="{{ route('cms.appearance.themes.create-child') }}"
                                                onsubmit="return confirm('Create a child theme based on this theme?');">
                                                @csrf
                                                <input type="hidden" name="parent_theme" value="{{ $theme['directory'] }}">
                                                <button class="dropdown-item" type="submit">
                                                    <i class="ri-git-branch-line me-2"></i>Create Child Theme
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('cms.appearance.themes.export', $theme['directory']) }}" up-follow="false">
                                                <i class="ri-download-line me-2"></i>Export
                                            </a>
                                        </li>
                                        @if (\Modules\CMS\Models\Theme::isChildTheme($theme['directory']))
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="{{ route('cms.appearance.themes.detach', $theme['directory']) }}"
                                                    onsubmit="return confirm('This will copy all parent theme files to this theme and make it standalone. Continue?');">
                                                    @csrf
                                                    <button class="dropdown-item text-warning" type="submit">
                                                        <i class="ri-link-unlink me-2"></i>Make Standalone
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        @else
                            <div class="d-flex gap-1">
                                <form class="flex-fill" method="POST"
                                    action="{{ route('cms.appearance.themes.activate', $theme['directory']) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-success w-100" type="submit">
                                        <i class="ri-checkbox-circle-line"></i><span class="d-none d-xl-inline ms-1">Activate</span>
                                    </button>
                                </form>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="ri-more-2-fill"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" target="_blank"
                                                href="{{ route('cms.appearance.themes.editor.index', $theme['directory']) }}">
                                                <i class="ri-code-line me-2"></i>Edit Files
                                            </a>
                                        </li>
                                        <li>
                                            <form method="POST" action="{{ route('cms.appearance.themes.create-child') }}"
                                                onsubmit="return confirm('Create a child theme based on this theme?');">
                                                @csrf
                                                <input type="hidden" name="parent_theme" value="{{ $theme['directory'] }}">
                                                <button class="dropdown-item" type="submit">
                                                    <i class="ri-git-branch-line me-2"></i>Create Child Theme
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('cms.appearance.themes.export', $theme['directory']) }}" up-follow="false">
                                                <i class="ri-download-line me-2"></i>Export
                                            </a>
                                        </li>
                                        @if (\Modules\CMS\Models\Theme::isChildTheme($theme['directory']))
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="{{ route('cms.appearance.themes.detach', $theme['directory']) }}"
                                                    onsubmit="return confirm('This will copy all parent theme files to this theme and make it standalone. Continue?');">
                                                    @csrf
                                                    <button class="dropdown-item text-warning" type="submit">
                                                        <i class="ri-link-unlink me-2"></i>Make Standalone
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                        @if (!\Modules\CMS\Models\Theme::isProtectedTheme($theme['directory']))
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="{{ route('cms.appearance.themes.destroy', $theme['directory']) }}"
                                                    onsubmit="return confirm('Are you sure you want to delete this theme? This cannot be undone.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="dropdown-item text-danger" type="submit">
                                                        <i class="ri-delete-bin-line me-2"></i>Delete
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="py-5 text-center">
                    <i class="ri-palette-line fa-4x text-muted mb-3"></i>
                    <h3>No themes found</h3>
                    <p class="text-muted">
                        @if ($search)
                            No themes match your search criteria.
                            <a href="{{ route('cms.appearance.themes.index') }}">Clear search</a>
                        @else
                            No themes are available. You can import a theme.
                        @endif
                    </p>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Import Theme Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" data-no-unpoly>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ri-upload-cloud-line me-2"></i>Import Theme
                    </h5>
                    <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
                </div>
                <form method="POST" action="{{ route('cms.appearance.themes.import') }}"
                    enctype="multipart/form-data" data-no-unpoly id="importThemeForm">
                    @csrf
                    <div class="modal-body">
                        <!-- Error Alert -->
                        <div class="alert alert-danger d-none" id="importErrorAlert">
                            <i class="ri-error-warning-line me-2"></i>
                            <span id="importErrorMessage"></span>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="theme_zip">
                                <i class="ri-file-zip-line me-1"></i>Theme File (ZIP)
                            </label>
                            <input class="form-control" id="theme_zip" name="theme_zip" type="file" accept=".zip"
                                required>
                            <div class="form-text">
                                <i class="ri-information-line me-1"></i>
                                Upload a ZIP file containing a valid theme with manifest.json. Max size: 10MB.
                            </div>
                        </div>

                        <!-- Upload Progress -->
                        <div class="d-none" id="uploadProgress">
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">Uploading...</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-primary btn-sm" type="submit" id="importSubmitBtn">
                            <i class="ri-upload-2-line me-1"></i>Import Theme
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .theme-card {
            height: 100%;
            font-size: 0.875rem;
        }

        .theme-card.border-primary {
            border-width: 2px;
        }

        .theme-card .card-body {
            padding: 0.75rem;
        }

        .theme-card .card-footer {
            padding: 0.5rem 0.75rem;
        }

        .theme-card .card-title {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .theme-meta {
            font-size: 0.75rem;
            line-height: 1.3;
        }

        .theme-tags .badge {
            margin-right: 0.15rem;
            margin-bottom: 0.15rem;
            font-size: 0.65rem;
            padding: 0.2em 0.4em;
        }

        .btn-group .btn {
            flex: 1;
        }

        .card-footer .d-flex {
            gap: 0.35rem;
        }

        .card-footer .d-flex .btn {
            flex: 1;
        }

        .card-footer .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .card-footer .dropdown-toggle::after {
            display: none;
        }

        .card-footer .dropdown-menu {
            font-size: 0.8rem;
        }

        @media (max-width: 1400px) {
            .theme-card .card-body {
                padding: 0.5rem;
            }
        }

        @media (max-width: 768px) {
            .btn-group {
                flex-direction: column;
            }

            .btn-group .btn {
                border-radius: 0.375rem !important;
                margin-bottom: 0.25rem;
            }

            .btn-group .btn:last-child {
                margin-bottom: 0;
            }

            .card-footer .d-flex {
                flex-direction: column;
            }

            .card-footer .d-flex .btn {
                margin-bottom: 0.25rem;
            }

            .card-footer .d-flex .btn:last-child {
                margin-bottom: 0;
            }
        }
    </style>

    @pushOnce('scripts')
        <script data-up-execute>
            // Auto-submit search form on enter
            document.querySelector('input[name="search"]')?.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.target.closest('form').submit();
                }
            });

            // Confirm theme activation
            document.querySelectorAll('form[action*="activate"]').forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!confirm(
                            'Are you sure you want to activate this theme? The current theme will be deactivated.'
                        )) {
                        e.preventDefault();
                    }
                });
            });

            // Import form validation and UX
            const importForm = document.getElementById('importThemeForm');
            const importFileInput = document.getElementById('theme_zip');
            const importErrorAlert = document.getElementById('importErrorAlert');
            const importErrorMessage = document.getElementById('importErrorMessage');
            const uploadProgress = document.getElementById('uploadProgress');
            const importSubmitBtn = document.getElementById('importSubmitBtn');

            if (importFileInput) {
                importFileInput.addEventListener('change', function() {
                    // Hide previous errors
                    importErrorAlert?.classList.add('d-none');

                    const file = this.files[0];
                    if (!file) return;

                    // Validate file type
                    if (!file.name.toLowerCase().endsWith('.zip')) {
                        showImportError('Please select a valid ZIP file.');
                        this.value = '';
                        return;
                    }

                    // Validate file size (10MB max)
                    const maxSize = 10 * 1024 * 1024;
                    if (file.size > maxSize) {
                        showImportError('File size exceeds 10MB limit. Please select a smaller file.');
                        this.value = '';
                        return;
                    }
                });
            }

            if (importForm) {
                importForm.addEventListener('submit', function(e) {
                    const file = importFileInput?.files[0];
                    if (!file) {
                        e.preventDefault();
                        showImportError('Please select a theme ZIP file.');
                        return;
                    }

                    // Show loading state
                    if (importSubmitBtn) {
                        importSubmitBtn.disabled = true;
                        importSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importing...';
                    }
                    uploadProgress?.classList.remove('d-none');

                    // Simulate progress for better UX (actual upload happens via form submit)
                    let progress = 0;
                    const progressBar = uploadProgress?.querySelector('.progress-bar');
                    const progressInterval = setInterval(() => {
                        progress += Math.random() * 15;
                        if (progress > 90) progress = 90;
                        if (progressBar) progressBar.style.width = progress + '%';
                    }, 200);

                    // Store interval ID to clear later if needed
                    importForm.dataset.progressInterval = progressInterval;
                });
            }

            function showImportError(message) {
                if (importErrorAlert && importErrorMessage) {
                    importErrorMessage.textContent = message;
                    importErrorAlert.classList.remove('d-none');
                }
            }

            // Reset modal state when closed
            document.getElementById('importModal')?.addEventListener('hidden.bs.modal', function() {
                importErrorAlert?.classList.add('d-none');
                uploadProgress?.classList.add('d-none');
                if (importSubmitBtn) {
                    importSubmitBtn.disabled = false;
                    importSubmitBtn.innerHTML = '<i class="ri-upload-2-line me-1"></i>Import Theme';
                }
                if (importFileInput) importFileInput.value = '';

                // Clear progress interval if exists
                if (importForm?.dataset.progressInterval) {
                    clearInterval(parseInt(importForm.dataset.progressInterval));
                }
            });
        </script>
    @endPushOnce</x-app-layout>
