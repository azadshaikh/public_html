<x-app-layout :title="$page_title">
    <x-page-header
        title="{{ $page_title }}"
        description="Configure SEO settings for your CMS content including posts, pages, categories, tags, and authors."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'SEO', 'href' => route('seo.dashboard')],
            ['label' => $page_title, 'active' => true],
        ]"
    />

    <x-cms::seo-indexing-warning />

    <div class="row g-4">
        <!-- Vertical Pills Navigation -->
        <div class="col-lg-3">
            <div class="nav flex-column nav-pills gap-2" role="tablist">
                <button class="nav-link active d-flex align-items-center" data-section="general" type="button" role="tab">
                    <i class="ri-settings-3-line me-2"></i>
                    <span>{{ __('seo::seo.general') }}</span>
                </button>
                <button class="nav-link d-flex align-items-center" data-section="posts" type="button" role="tab">
                    <i class="ri-newspaper-line me-2"></i>
                    <span>{{ __('seo::seo.posts') }}</span>
                </button>
                <button class="nav-link d-flex align-items-center" data-section="pages" type="button" role="tab">
                    <i class="ri-file-text-line me-2"></i>
                    <span>{{ __('seo::seo.pages') }}</span>
                </button>
                <button class="nav-link d-flex align-items-center" data-section="categories" type="button" role="tab">
                    <i class="ri-archive-line me-2"></i>
                    <span>{{ __('seo::seo.categories') }}</span>
                </button>
                <button class="nav-link d-flex align-items-center" data-section="tags" type="button" role="tab">
                    <i class="ri-price-tag-3-line me-2"></i>
                    <span>{{ __('seo::seo.tags') }}</span>
                </button>
                <button class="nav-link d-flex align-items-center" data-section="authors" type="button" role="tab">
                    <i class="ri-account-pin-box-line me-2"></i>
                    <span>{{ __('seo::seo.authors') }}</span>
                </button>
                <button class="nav-link d-flex align-items-center" data-section="search" type="button" role="tab">
                    <i class="ri-search-line me-2"></i>
                    <span>{{ __('seo::seo.search') }}</span>
                </button>
                <button class="nav-link d-flex align-items-center" data-section="error_page" type="button" role="tab">
                    <i class="ri-error-warning-line me-2"></i>
                    <span>{{ __('seo::seo.error_page') }}</span>
                </button>
            </div>
        </div>

        <!-- Settings Forms Container -->
        <div class="col-lg-9">
            <div class="tab-content">
                <!-- General Settings Panel -->
                <div class="tab-pane fade show active" id="general" role="tabpanel">
                    @include('seo::settings.titlesmeta.partials.general')
                </div>
                <!-- Posts Settings Panel -->
                <div class="tab-pane fade" id="posts" role="tabpanel">
                    @include('seo::settings.titlesmeta.partials.posts')
                </div>
                <!-- Pages Settings Panel -->
                <div class="tab-pane fade" id="pages" role="tabpanel">
                    @include('seo::settings.titlesmeta.partials.pages')
                </div>
                <!-- Categories Settings Panel -->
                <div class="tab-pane fade" id="categories" role="tabpanel">
                    @include('seo::settings.titlesmeta.partials.categories')
                </div>
                <!-- Authors Settings Panel -->
                <div class="tab-pane fade" id="authors" role="tabpanel">
                    @include('seo::settings.titlesmeta.partials.authors')
                </div>
                <!-- Tags Settings Panel -->
                <div class="tab-pane fade" id="tags" role="tabpanel">
                    @include('seo::settings.titlesmeta.partials.tags')
                </div>
                <!-- Search Panel -->
                <div class="tab-pane fade" id="search" role="tabpanel">
                    @include('seo::settings.titlesmeta.partials.search')
                </div>
                <!-- Error Page Panel -->
                <div class="tab-pane fade" id="error_page" role="tabpanel">
                    @include('seo::settings.titlesmeta.partials.error_page')
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
        <script data-up-execute>
            // Define field labels for validation (optional)
            window.settingsFieldLabels = {
                'separator_character': 'Title Separator',
                'secondary_separator_character': 'Secondary Separator',
                'cms_base': 'CMS URL Prefix',
                'search_engine_visibility': 'Search Engine Visibility',
            };

            // Explicitly initialize section pills for this page
            if (window.initSectionPills) {
                window.initSectionPills();
            }
        </script>
    @endpush
</x-app-layout>
