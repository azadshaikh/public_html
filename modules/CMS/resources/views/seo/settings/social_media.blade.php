<x-app-layout :title="$page_title">
    <x-page-header
        title="{{ $page_title }}"
        description="Configure global social media settings. Meta tags are automatically generated from content but can be customized per page."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'SEO', 'href' => route('seo.dashboard')],
            ['label' => 'Social Media', 'active' => true]
        ]"
    />

    <x-alert-container containerId="seo-social-media-alert-container" :showFlashMessages="false" :fieldLabels="[
        'open_graph_image' => 'Open Graph Image',
        'twitter_username' => 'X (Twitter) Username',
        'twitter_card_type' => 'X (Twitter) Card Type',
    ]" />

    <x-cms::seo-indexing-warning />

    <div class="row g-4">
        <!-- Vertical Pills Navigation -->
        <div class="col-lg-3">
            <div class="nav flex-column nav-pills gap-2" role="tablist">
                <button class="nav-link active d-flex align-items-center" data-section="social-media-settings-section" type="button" role="tab">
                    <i class="ri-share-line me-2"></i>
                    <span>{{ __('seo::seo.social_media') }}</span>
                </button>
            </div>
        </div>

        <!-- Settings Forms Container -->
        <div class="col-lg-9">
            <div class="tab-content">
                <!-- Social Media Settings Panel -->
                <div class="tab-pane fade show active" id="social-media-settings-section" role="tabpanel">
                    <div class="card">
                        <div class="card-header mb-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0">{{ __('seo::seo.social_media_settings') }}</h5>
                            </div>
                            <p class="text-muted small mt-2 mb-0">
                                Learn more:
                                <a href="https://ogp.me/" target="_blank" rel="noopener" class="text-decoration-none">Open Graph Protocol</a> ·
                                <a href="https://developers.facebook.com/docs/sharing/webmasters/" target="_blank" rel="noopener" class="text-decoration-none">Facebook Sharing</a> ·
                                <a href="https://developer.x.com/en/docs/x-for-websites/cards/overview/markup" target="_blank" rel="noopener" class="text-decoration-none">X/Twitter Cards</a>
                            </p>
                        </div>
                        <form class="needs-validation" id="social-media-form"
                            action="{{ route('seo.settings.socialmedia.update') }}"
                            method="POST" novalidate>
                            @csrf
                            <input name="section" type="hidden" value="social-media-settings-section">
                            <div class="card-body">
                                <!-- OpenGraph Default Image -->
                                <x-media-picker.image-field class="form-group mb-3" id="open_graph_image"
                                    name="open_graph_image"
                                    :value="$settings_data['seo_social_media_open_graph_image'] ?? ''"
                                    :valueUrl="!empty($settings_data['seo_social_media_open_graph_image']) ? get_media_url($settings_data['seo_social_media_open_graph_image']) : null"
                                    :previewUrl="!empty($settings_data['seo_social_media_open_graph_image']) ? get_media_url($settings_data['seo_social_media_open_graph_image']) : null"
                                    label="Open Graph Default Image"
                                    labelClass="form-label" />
                                <div class="form-text mb-3">Default image for social media sharing when no featured image is available. Recommended size: 1200×630px.</div>

                                <!-- X/Twitter Username -->
                                <x-form-elements.input class="form-group mb-3" id="twitter_username"
                                    name="twitter_username" labelclass="form-label"
                                    label="X (Twitter) Site Username" inputclass="form-control"
                                    placeholder="@username" :value="$settings_data['seo_social_media_twitter_username'] ?? ''" />
                                <div class="form-text mb-3">Your site's X (Twitter) username including the @ symbol. Used for twitter:site meta tag.</div>

                                <!-- X/Twitter Card Type -->
                                <x-form-elements.select class="form-group mb-3" id="twitter_card_type"
                                    name="twitter_card_type" labelclass="form-label"
                                    label="X (Twitter) Card Type" :options="json_encode(
                                        [
                                            [
                                                'label' => 'Summary Card with Large Image',
                                                'value' => 'summary_large_image',
                                            ],
                                            ['label' => 'Summary Card', 'value' => 'summary'],
                                        ],
                                        true,
                                    )" :value="$settings_data['seo_social_media_twitter_card_type'] ?? 'summary_large_image'" />
                                <div class="form-text mb-3">Choose how your content appears when shared on X (Twitter). Large image cards are recommended for better engagement.</div>
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

    <x-media-picker.media-modal />
    @push('scripts')
        <script data-up-execute>
            // Define field labels for validation
            window.settingsFieldLabels = {
                open_graph_image: 'Open Graph Image',
                twitter_username: 'X (Twitter) Username',
                twitter_card_type: 'X (Twitter) Card Type',
            };

            // Explicitly initialize section pills for this page
            if (window.initSectionPills) {
                window.initSectionPills();
            }
        </script>
    @endpush
</x-app-layout>
