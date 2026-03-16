<div class="card">
    <div class="card-header mb-3">
        <div class="d-flex align-items-center">
            <h5 class="card-title">{{ __('seo::seo.general_settings') }}</h5>
        </div>
    </div>
    <form class="needs-validation" id="titlesmeta-general-form"
        action="{{ route('seo.settings.general.update') }}" method="POST" novalidate>
        @csrf
        <input name="section" type="hidden" value="general">

        <x-alert-container containerId="seo-titlesmeta-general-alert" :showFlashMessages="false" :fieldLabels="[
            'separator_character' => 'Title Separator',
            'secondary_separator_character' => 'Secondary Separator',
            'cms_base' => 'CMS URL Prefix',
            'search_engine_visibility' => 'Search Engine Visibility',
        ]" />

        <div class="card-body">
            <x-form-elements.input layout="horizontal" class="mb-3" id="separator_character"
                name="separator_character"
                value="{{ old('separator_character', $settings_data['seo_separator_character'] ?? '') }}"
                divclass="form-group" label="Title Separator" labelclass="form-label"
                inputclass="form-control"
                placeholder="Enter separator character (e.g., | - · »)"
                infotext="Character used to separate page title components (e.g., 'Page Title | Site Name'). Common options: | (pipe), - (dash), · (bullet)" />

            <x-form-elements.input layout="horizontal" class="mb-3" id="secondary_separator_character"
                name="secondary_separator_character"
                value="{{ old('secondary_separator_character', $settings_data['seo_secondary_separator_character'] ?? '') }}"
                divclass="form-group" label="Secondary Separator" labelclass="form-label"
                inputclass="form-control"
                placeholder="Enter secondary separator (e.g., - · ,)"
                infotext="Optional secondary separator for additional title formatting and breadcrumb navigation" />

            <x-form-elements.input layout="horizontal" class="mb-3" id="cms_base" name="cms_base"
                value="{{ old('cms_base', $settings_data['seo_cms_base'] ?? '') }}"
                divclass="form-group" label="CMS URL Prefix" labelclass="form-label"
                inputclass="form-control"
                placeholder="Leave empty or enter prefix (e.g., blog, news)"
                infotext="Optional URL prefix for all CMS content. Example: 'blog' will make URLs like /blog/posts/my-article. Leave empty for no prefix." />

            <x-form-elements.select layout="horizontal" class="mb-3" id="url_extension"
                name="url_extension" divclass="form-group" label="URL Extension" labelclass="form-label"
                inputclass="form-control"
                placeholder="Select URL extension (optional)" :options="json_encode($url_extentions ?? [
                    ['label' => 'None', 'value' => ''],
                    ['label' => '/', 'value' => '/'],
                    ['label' => '.html', 'value' => '.html'],
                ], true)" :value="old('url_extension', $settings_data['seo_url_extension'] ?? '')"
                infotext="Optional file extension for all URLs (e.g., .html). This affects posts, pages, and archives. Most modern sites don't use extensions." />

            @php
                $searchEngineEnabled = in_array($settings_data['seo_search_engine_visibility'] ?? 'false', ['true', true, 1, '1'], true);
            @endphp
            <div class="{{ !$searchEngineEnabled ? 'bg-warning-subtle border border-warning rounded p-3 -mx-3' : '' }}">
                @if(!$searchEngineEnabled)
                    <div class="alert alert-warning d-flex align-items-center mb-3 py-2">
                        <i class="ri-error-warning-line fs-4 me-2"></i>
                        <div>
                            <strong>Warning:</strong> Search engines are currently blocked from indexing your website.
                            All pages will have <code>noindex, nofollow</code> meta tag.
                        </div>
                    </div>
                @endif
                <x-form-elements.switch-input layout="horizontal" class="form-group mb-0" id="search_engine_visibility"
                    name="search_engine_visibility" labelclass="fw-medium"
                    label="Search Engine Visibility" :value="1"
                    ischecked="{{ $searchEngineEnabled ? 1 : 0 }}"
                    infotext="Enable to allow search engines (Google, Bing, etc.) to crawl and index your website. Disable during development or for private sites." />
            </div>
        </div>

        <div class="card-footer d-flex justify-content-end bg-transparent py-3">
            <button class="btn btn-primary" type="submit">
                <i class="ri-save-line me-1"></i>
                <span class="btn-text">{{ __('settings.save_changes') }}</span>
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    function initGeneralFormRefresh() {
        const form = document.getElementById('titlesmeta-general-form');
        if (!form || form.dataset.refreshInitialized === 'true') return;
        form.dataset.refreshInitialized = 'true';

        form.addEventListener('unpoly:ajax:result', function(e) {
            if (e.detail && e.detail.success) {
                // Delay slightly to ensure toast is shown before refresh
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }
        });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGeneralFormRefresh);
    } else {
        initGeneralFormRefresh();
    }

    // Re-initialize on Unpoly fragment insertion (SPA navigation)
    document.addEventListener('up:fragment:inserted', function(event) {
        if (event.target?.querySelector?.('#titlesmeta-general-form')) {
            initGeneralFormRefresh();
        }
    });
})();
</script>
