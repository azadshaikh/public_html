<div class="card">
    <div class="card-header mb-3">
        <div class="d-flex align-items-center">
            <h5 class="card-title">{{ __('seo::seo.authors_settings') }}</h5>
        </div>
    </div>
    <form class="needs-validation" id="general-form"
        action="{{ route('seo.settings.update', ['master_group' => 'cms', 'file_name' => 'authors']) }}" method="POST"
        novalidate>
        @csrf
        <input name="section" type="hidden" value="authors">

        <x-alert-container containerId="seo-titlesmeta-authors-alert" :showFlashMessages="false" :fieldLabels="[
            'permalink_base' => 'Author Base',
            'title_template' => 'Title Template',
            'description_template' => 'Meta Description Template',
        ]" />

        <div class="card-body">

            <x-form-elements.input layout="horizontal" class="mb-3" id="permalink_base" name="permalink_base"
                value="{{ $settings_data['seo_authors_permalink_base'] ?? old('permalink_base') }}"
                divclass="form-group" label="Authors URL Prefix" labelclass="form-label"
                inputclass="form-control" placeholder="author"
                infotext="URL path prefix for author pages. Example: 'author' will make URLs like /author/john-doe" />

            <!-- Title Template -->
            <x-form-elements.input layout="horizontal" class="mb-3" id="title_template" name="title_template"
                value="{{ $settings_data['seo_authors_title_template'] ?? old('title_template') }}"
                divclass="form-group" label="Page Title Template" labelclass="form-label"
                inputclass="form-control" placeholder="%title% %separator% %site_title%"
                infotext="Template for author page titles. Use variables: %title%, %site_title%, %separator%" />

            <!-- page meta description -->
            <x-form-elements.textarea layout="horizontal" class="mb-3" id="description_template"
                name="description_template"
                value="{{ $settings_data['seo_authors_description_template'] ?? old('description_template') }}"
                divclass="form-group" label="Meta Description Template" labelclass="form-label"
                inputclass="form-control" placeholder="Articles by %title% on %site_title%"
                infotext="Template for author meta descriptions. Use variables: %title%, %bio%, %site_title%. Max 160 characters recommended." />

            <!-- meta robots -->
            <x-form-elements.select layout="horizontal" class="mb-3" id="robots_default" name="robots_default"
                divclass="form-group" label="Default Robots Meta Tag" labelclass="form-label"
                inputclass="form-control" placeholder="Select default robots directive" :options="json_encode($meta_robots_options, true)"
                :value="isset($settings_data['seo_authors_robots_default']) &&
                !empty($settings_data['seo_authors_robots_default'])
                    ? $settings_data['seo_authors_robots_default']
                    : old('robots_default')"
                infotext="Default indexing behavior for author pages. 'index, follow' = allow indexing and link following." />

            <!-- Index pagination switch -->
            <x-form-elements.switch-input layout="horizontal" class="form-group mb-3" id="enable_pagination_indexing"
                name="enable_pagination_indexing" labelclass="fw-medium" label="Index Pagination Pages"
                :value="1"
                ischecked="{{ ($settings_data['seo_authors_enable_pagination_indexing'] ?? old('enable_pagination_indexing', 'false')) === 'true' ? 1 : 0 }}"
                infotext="Allow search engines to index paginated author pages (page 2, 3, etc.). Not recommended as it can cause duplicate content issues." />
        </div>
        <div class="card-footer d-flex justify-content-end bg-transparent py-3">
            <button class="btn btn-primary" type="submit">
                <i class="ri-save-line me-1"></i>
                <span class="btn-text">{{ __('settings.save_changes') }}</span>
            </button>
        </div>
    </form>
</div>
