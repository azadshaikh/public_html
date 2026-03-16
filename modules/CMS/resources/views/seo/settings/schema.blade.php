<x-app-layout :title="$page_title">
    <x-page-header
        title="{{ $page_title }}"
        description="Configure schema markup settings to enhance your website's structured data for search engines."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'SEO', 'href' => route('seo.dashboard')],
            ['label' => 'Schema', 'active' => true]
        ]"
    />

    <x-alert-container containerId="seo-schema-alert-container" :showFlashMessages="false" :fieldLabels="[
        'enable_article_schema' => 'Article Schema',
        'enable_breadcrumb_schema' => __('seo::seo.breadcrumb_schema'),
    ]" />

    <x-cms::seo-indexing-warning />

    <div class="row g-4">
        <!-- Vertical Pills Navigation -->
        <div class="col-lg-3">
            <div class="nav flex-column nav-pills gap-2" role="tablist">
                <button class="nav-link active d-flex align-items-center" data-section="schema-settings-section" type="button" role="tab">
                    <i class="ri-code-s-slash-fill me-2"></i>
                    <span>{{ __('seo::seo.schema') }}</span>
                </button>
            </div>
        </div>

        <!-- Settings Forms Container -->
        <div class="col-lg-9">
            <div class="tab-content">
                <!-- General Settings Panel -->
                <div class="tab-pane fade show active" id="schema-settings-section" role="tabpanel">
                    <div class="card">
                        <div class="card-header mb-3">
                            <div class="d-flex align-items-center">
                                <h5 class="card-title">{{ __('seo::seo.schema_settings') }}</h5>
                            </div>
                        </div>
                        <form class="needs-validation" id="schema-form"
                            action="{{ route('seo.settings.schema.update') }}"
                            method="POST" novalidate>
                            @csrf
                            <input name="section" type="hidden" value="schema-settings-section">
                            <div class="card-body">
                                <x-form-elements.switch-input layout="horizontal" class="form-group mb-3" id="enable_article_schema"
                                    name="enable_article_schema" labelclass="fw-medium"
                                    label="Enable Article Schema" :value="1"
                                    ischecked="{{ ($settings_data['seo_enable_article_schema'] ?? old('enable_article_schema', 'false')) === 'true' ? 1 : 0 }}"
                                    infotext="Add Article structured data for blog posts. This helps search engines understand your content and can display rich results like author, publish date, and more." />

                                <x-form-elements.switch-input layout="horizontal" class="form-group mb-3" id="enable_breadcrumb_schema"
                                    name="enable_breadcrumb_schema" labelclass="fw-medium"
                                    label="Enable Breadcrumb Schema" :value="1"
                                    ischecked="{{ ($settings_data['seo_enable_breadcrumb_schema'] ?? old('enable_breadcrumb_schema', 'false')) === 'true' ? 1 : 0 }}"
                                    infotext="Add structured data for breadcrumb navigation. This helps search engines understand your site hierarchy and can display breadcrumbs in search results." />
                            </div>
                            <div class="card-footer d-flex justify-content-end bg-transparent py-3">
                                <button class="btn btn-primary" type="submit">
                                    <i class="ri-save-line me-1"></i>
                                    <span class="btn-text">{{ __('settings.save_changes') }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
        <script data-up-execute>
            // Define field labels for validation (optional)
            window.settingsFieldLabels = {
                enable_article_schema: 'Article Schema',
                enable_breadcrumb_schema: '{{ __("seo::seo.breadcrumb_schema") }}',
            };

            // Explicitly initialize section pills for this page
            if (window.initSectionPills) {
                window.initSectionPills();
            }
        </script>
    @endpush
</x-app-layout>
