<div class="card">
    <div class="card-header mb-3">
        <div class="d-flex align-items-center">
            <h5 class="card-title">{{ __('seo::seo.posts_settings') }}</h5>
        </div>
    </div>
    <form class="needs-validation" id="general-form"
        action="{{ route('seo.settings.update', ['master_group' => 'cms', 'file_name' => 'posts']) }}" method="POST"
        novalidate>
        @csrf
        <input name="section" type="hidden" value="posts">

        <x-alert-container containerId="seo-titlesmeta-posts-alert" :showFlashMessages="false" :fieldLabels="[
            'permalink_base' => 'Post Base',
            'title_template' => 'Title Template',
            'description_template' => 'Meta Description Template',
        ]" />

        <div class="card-body">

            <!-- Post Base -->
            <x-form-elements.input layout="horizontal" class="mb-3" id="permalink_base" name="permalink_base"
                value="{{ $settings_data['seo_posts_permalink_base'] ?? old('permalink_base') }}"
                divclass="form-group" label="Posts URL Prefix" labelclass="form-label"
                inputclass="form-control" placeholder="e.g., blog, articles, news"
                infotext="URL prefix for all posts. Example: 'blog' will make URLs like /blog/my-post. Leave empty for no prefix." />

            <!-- Title Template -->
            <x-form-elements.input layout="horizontal" class="mb-3" id="title_template" name="title_template"
                value="{{ $settings_data['seo_posts_title_template'] ?? old('title_template') }}"
                divclass="form-group" label="Page Title Template" labelclass="form-label"
                inputclass="form-control" placeholder="%title% %separator% %site_title%"
                infotext="Template for browser title. Use variables: %title%, %site_title%, %separator%, %category%, %author%. Example: '%title% %separator% %site_title%'" />

            <!-- page meta description -->
            <x-form-elements.textarea layout="horizontal" class="mb-3" id="description_template"
                name="description_template"
                value="{{ $settings_data['seo_posts_description_template'] ?? old('description_template') }}"
                divclass="form-group" label="Meta Description Template" labelclass="form-label"
                inputclass="form-control" placeholder="%excerpt%"
                infotext="Template for meta description shown in search results. Use variables: %excerpt%, %post_content%, %category%, %author%. Max 160 characters recommended." />

            <!-- meta robots -->
            <x-form-elements.select layout="horizontal" class="mb-3" id="robots_default" name="robots_default"
                divclass="form-group" label="Default Robots Meta Tag" labelclass="form-label"
                inputclass="form-control" placeholder="Select indexing behavior" :options="json_encode($meta_robots_options, true)"
                :value="isset($settings_data['seo_posts_robots_default']) &&
                !empty($settings_data['seo_posts_robots_default'])
                    ? $settings_data['seo_posts_robots_default']
                    : old('robots_default')"
                infotext="Controls how search engines crawl posts. 'index, follow' = allow indexing and link following (recommended for public content)" />

            <!-- permalink structure -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label"
                        for="permalink_structure">URL Structure</label>
                </div>
                <div class="col-md-9">
                    <div class="form-check mb-2">
                        <input class="form-check-input me-1" id="post-permalink-2" name="permalink_structure"
                            type="radio" value="%year%/%monthnum%/%day%/%postname%"
                            {{ isset($settings_data['seo_posts_permalink_structure']) && $settings_data['seo_posts_permalink_structure'] == '%year%/%monthnum%/%day%/%postname%' ? 'checked' : '' }} />
                        <label for="post-permalink-2"> Date with post name -
                            <mark>/2020/06/22/sample-post{{ !empty($settings->url_extension) ? $settings->url_extension : '' }}</mark></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input me-1" id="post-permalink-3" name="permalink_structure"
                            type="radio" value="%year%/%monthnum%/%postname%"
                            {{ isset($settings_data['seo_posts_permalink_structure']) && $settings_data['seo_posts_permalink_structure'] == '%year%/%monthnum%/%postname%' ? 'checked' : '' }} />
                        <label for="post-permalink-3"> Month with post name -
                            <mark>/2020/06/sample-post{{ !empty($settings->url_extension) ? $settings->url_extension : '' }}</mark></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input me-1" id="post-permalink-4" name="permalink_structure"
                            type="radio" value="%post_id%"
                            {{ isset($settings_data['seo_posts_permalink_structure']) && $settings_data['seo_posts_permalink_structure'] == '%post_id%' ? 'checked' : '' }} />
                        <label for="post-permalink-4"> Numeric ID only -
                            <mark>/123{{ !empty($settings->url_extension) ? $settings->url_extension : '' }}</mark></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input me-1" id="post-permalink-5" name="permalink_structure"
                            type="radio" value="%postname%"
                            {{ isset($settings_data['seo_posts_permalink_structure']) && $settings_data['seo_posts_permalink_structure'] == '%postname%' ? 'checked' : '' }} />
                        <label for="post-permalink-5"> Post name only (recommended) -
                            <mark>/sample-post{{ !empty($settings->url_extension) ? $settings->url_extension : '' }}</mark></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input me-1" id="post-permalink-6" name="permalink_structure"
                            type="radio" value="%category%/%postname%"
                            {{ isset($settings_data['seo_posts_permalink_structure']) && $settings_data['seo_posts_permalink_structure'] == '%category%/%postname%' ? 'checked' : '' }} />
                        <label for="post-permalink-6"> Category with post name -
                            <mark>/category/sample-post{{ !empty($settings->url_extension) ? $settings->url_extension : '' }}</mark></label>
                    </div>
                    <small class="text-muted">Choose how post URLs are structured. Simple structures (post name only) are generally better for SEO.</small>
                </div>
            </div>

            <!-- multiple categories switch -->
            <x-form-elements.switch-input layout="horizontal" class="form-group mb-3" id="enable_multiple_categories"
                name="enable_multiple_categories" labelclass="fw-medium"
                label="Allow Multiple Categories Per Post" :value="1"
                ischecked="{{ ($settings_data['seo_posts_enable_multiple_categories'] ?? old('enable_multiple_categories', 'false')) === 'true' ? 1 : 0 }}"
                infotext="Enable to allow assigning multiple categories to a single post. Disable to limit posts to one category each." />

            <!-- Index pagination switch -->
            <x-form-elements.switch-input layout="horizontal" class="form-group mb-3" id="enable_pagination_indexing"
                name="enable_pagination_indexing" labelclass="fw-medium" label="Index Pagination Pages"
                :value="1"
                ischecked="{{ ($settings_data['seo_posts_enable_pagination_indexing'] ?? old('enable_pagination_indexing', 'false')) === 'true' ? 1 : 0 }}"
                infotext="Allow search engines to index paginated pages (page 2, 3, etc.). Not recommended as it can cause duplicate content issues." />

        </div>
        <div class="card-footer d-flex justify-content-end bg-transparent py-3">
            <button class="btn btn-primary" type="submit">
                <i class="ri-save-line me-1"></i>
                <span class="btn-text">{{ __('settings.save_changes') }}</span>
            </button>
        </div>
    </form>
</div>
