<div class="card">
    <div class="card-header">
        <div class="d-flex align-items-center mb-3">
            <h5 class="card-title">{{ __('seo::seo.error_page_settings') }}</h5>
        </div>
    </div>
    <form class="needs-validation" id="general-form"
        action="{{ route('seo.settings.update', ['master_group' => 'cms', 'file_name' => 'error_page']) }}" method="POST"
        novalidate>
        @csrf
        <input name="section" type="hidden" value="error_page">

        <x-alert-container containerId="seo-titlesmeta-error-page-alert" :showFlashMessages="false" :fieldLabels="[
            'title_template' => 'Title Template',
            'description_template' => 'Meta Description',
        ]" />

        <div class="card-body">
            <!-- Title Template -->
            <x-form-elements.input layout="horizontal" class="mb-3" id="title_template" name="title_template"
                value="{{ $settings_data['seo_error_page_title_template'] ?? old('title_template') }}"
                divclass="form-group" label="Page Title Template" labelclass="form-label"
                inputclass="form-control" placeholder="Page Not Found %separator% %site_title%"
                infotext="Template for 404 error page titles. Use variables: %site_title%, %separator%" />

            <!-- page meta description -->
            <x-form-elements.textarea layout="horizontal" class="mb-3" id="description_template"
                name="description_template"
                value="{{ $settings_data['seo_error_page_description_template'] ?? old('description_template') }}"
                divclass="form-group" label="Meta Description Template" labelclass="form-label"
                inputclass="form-control" placeholder="The page you're looking for could not be found"
                infotext="Template for error page meta descriptions. Use variables: %site_title%. Max 160 characters recommended." />

            <!-- meta robots -->
            <x-form-elements.select layout="horizontal" class="mb-3" id="robots_default" name="robots_default"
                divclass="form-group" label="Default Robots Meta Tag" labelclass="form-label"
                inputclass="form-control" placeholder="Select default robots directive" :options="json_encode($meta_robots_options, true)"
                :value="isset($settings_data['seo_error_page_robots_default']) &&
                !empty($settings_data['seo_error_page_robots_default'])
                    ? $settings_data['seo_error_page_robots_default']
                    : old('robots_default')"
                infotext="Default indexing behavior for error pages. 'noindex, nofollow' is recommended to hide 404 pages from search results." />
        </div>
        <div class="card-footer d-flex justify-content-end bg-transparent py-3">
            <button class="btn btn-primary" type="submit">
                <i class="ri-save-line me-1"></i>
                <span class="btn-text">{{ __('settings.save_changes') }}</span>
            </button>
        </div>
    </form>
</div>
