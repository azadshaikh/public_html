@php
    // Check configuration status for each integration
    $integrationStatus = [
        // Webmaster Verification
        'webmaster_tools' => !empty(setting('seo_integrations_google_search_console'))
            || !empty(setting('seo_integrations_bing_webmaster'))
            || !empty(setting('seo_integrations_baidu_webmaster'))
            || !empty(setting('seo_integrations_yandex_verification'))
            || !empty(setting('seo_integrations_pinterest_verification'))
            || !empty(setting('seo_integrations_norton_verification')),

        // Analytics & Tracking
        'google_analytics' => !empty(setting('seo_integrations_google_analytics')),
        'google_tags' => !empty(setting('seo_integrations_google_tags')),
        'meta_pixel' => !empty(setting('seo_integrations_meta_pixel')),
        'microsoft_clarity' => !empty(setting('seo_integrations_ms_clarity')),

        // Advertising
        'google_adsense' => filter_var(setting('seo_integrations_google_adsense_enabled', false), FILTER_VALIDATE_BOOLEAN),

        // Custom Code
        'other' => !empty(setting('seo_integrations_other')),
    ];
@endphp

<x-app-layout :title="$page_title">
    <x-page-header
        title="{{ $page_title }}"
        description="Configure third-party integrations, tracking codes, and webmaster verification tools for your website."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'Integrations', 'href' => null]
        ]"
    />

    <x-alert-container containerId="seo-integrations-alert-container" :showFlashMessages="false" :fieldLabels="[
        'google_search_console' => 'Google Search Console',
        'bing_webmaster' => 'Bing Webmaster Tools',
        'baidu_webmaster' => 'Baidu Webmaster Tools',
        'yandex_verification' => 'Yandex Verification ID',
        'pinterest_verification' => 'Pinterest Verification ID',
        'norton_verification' => 'Norton Safe Web Verification ID',
        'custom_meta_tags' => 'Custom Webmaster Tags',
        'google_analytics' => 'Google Analytics Tracking Script',
        'google_tags' => 'Google Tag Manager Script',
        'meta_pixel' => 'Meta Pixel Code',
        'ms_clarity' => 'Microsoft Clarity Script',
        'other' => 'Custom Tags',
    ]" />

    <div class="row g-4">
        <!-- Vertical Pills Navigation -->
        <div class="col-lg-3">
            <div class="nav flex-column nav-pills gap-1" role="tablist">
                {{-- Webmaster Verification Category --}}
                <div class="nav-category text-muted small text-uppercase fw-semibold px-3 py-2 mt-0">
                    <i class="ri-verified-badge-line me-1"></i> Webmaster Verification
                </div>
                <button class="nav-link active d-flex align-items-center justify-content-between" data-section="webmaster_tools" type="button" role="tab">
                    <span class="d-flex align-items-center">
                        <i class="ri-checkbox-circle-line me-2"></i>
                        <span>{{ __('seo::seo.webmaster_tools') }}</span>
                    </span>
                    @if($integrationStatus['webmaster_tools'])
                        <span class="badge bg-success-subtle text-success rounded-pill">Active</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill">Not Set</span>
                    @endif
                </button>

                {{-- Analytics & Tracking Category --}}
                <div class="nav-category text-muted small text-uppercase fw-semibold px-3 py-2 mt-3">
                    <i class="ri-line-chart-line me-1"></i> Analytics & Tracking
                </div>
                <button class="nav-link d-flex align-items-center justify-content-between" data-section="google_analytics" type="button" role="tab">
                    <span class="d-flex align-items-center">
                        <i class="ri-google-line me-2"></i>
                        <span>{{ __('seo::seo.google_analytics') }}</span>
                    </span>
                    @if($integrationStatus['google_analytics'])
                        <span class="badge bg-success-subtle text-success rounded-pill">Active</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill">Not Set</span>
                    @endif
                </button>
                <button class="nav-link d-flex align-items-center justify-content-between" data-section="google_tags" type="button" role="tab">
                    <span class="d-flex align-items-center">
                        <i class="ri-price-tag-3-line me-2"></i>
                        <span>{{ __('seo::seo.google_tags') }}</span>
                    </span>
                    @if($integrationStatus['google_tags'])
                        <span class="badge bg-success-subtle text-success rounded-pill">Active</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill">Not Set</span>
                    @endif
                </button>
                <button class="nav-link d-flex align-items-center justify-content-between" data-section="meta_pixel" type="button" role="tab">
                    <span class="d-flex align-items-center">
                        <i class="ri-meta-line me-2"></i>
                        <span>{{ __('seo::seo.meta_pixel') }}</span>
                    </span>
                    @if($integrationStatus['meta_pixel'])
                        <span class="badge bg-success-subtle text-success rounded-pill">Active</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill">Not Set</span>
                    @endif
                </button>
                <button class="nav-link d-flex align-items-center justify-content-between" data-section="microsoft_clarity" type="button" role="tab">
                    <span class="d-flex align-items-center">
                        <i class="ri-microsoft-line me-2"></i>
                        <span>{{ __('seo::seo.microsoft_clarity') }}</span>
                    </span>
                    @if($integrationStatus['microsoft_clarity'])
                        <span class="badge bg-success-subtle text-success rounded-pill">Active</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill">Not Set</span>
                    @endif
                </button>

                {{-- Advertising Category --}}
                <div class="nav-category text-muted small text-uppercase fw-semibold px-3 py-2 mt-3">
                    <i class="ri-advertisement-line me-1"></i> Advertising
                </div>
                <button class="nav-link d-flex align-items-center justify-content-between" data-section="google_adsense" type="button" role="tab">
                    <span class="d-flex align-items-center">
                        <i class="ri-megaphone-line me-2"></i>
                        <span>{{ __('settings.google_adsense') }}</span>
                    </span>
                    @if($integrationStatus['google_adsense'])
                        <span class="badge bg-success-subtle text-success rounded-pill">Enabled</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill">Disabled</span>
                    @endif
                </button>

                {{-- Custom Code Category --}}
                <div class="nav-category text-muted small text-uppercase fw-semibold px-3 py-2 mt-3">
                    <i class="ri-code-s-slash-line me-1"></i> Custom Code (Head)
                </div>
                <button class="nav-link d-flex align-items-center justify-content-between" data-section="other" type="button" role="tab">
                    <span class="d-flex align-items-center">
                        <i class="ri-code-box-line me-2"></i>
                        <span>{{ __('seo::seo.other_misc') }}</span>
                    </span>
                    @if($integrationStatus['other'])
                        <span class="badge bg-success-subtle text-success rounded-pill">Active</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill">Not Set</span>
                    @endif
                </button>
            </div>
        </div>

        <!-- Settings Forms Container -->
        <div class="col-lg-9">
            <div class="tab-content">
                <!-- Webmaster Tools Panel -->
                <div class="tab-pane fade show active" id="webmaster_tools" role="tabpanel">
                    @include('seo::integrations.partials.webmaster_tools')
                </div>
                <!-- Google Analytics Panel -->
                <div class="tab-pane fade" id="google_analytics" role="tabpanel">
                    @include('seo::integrations.partials.google_analytics')
                </div>
                <!-- Google Tags Panel -->
                <div class="tab-pane fade" id="google_tags" role="tabpanel">
                    @include('seo::integrations.partials.google_tags')
                </div>
                <!-- Google Adsense Panel -->
                <div class="tab-pane fade" id="google_adsense" role="tabpanel">
                    @include('seo::integrations.partials.google_adsense')
                </div>
                <!-- Meta Pixel Panel -->
                <div class="tab-pane fade" id="meta_pixel" role="tabpanel">
                    @include('seo::integrations.partials.meta_pixel')
                </div>
                <!-- Microsoft Clarity Panel -->
                <div class="tab-pane fade" id="microsoft_clarity" role="tabpanel">
                    @include('seo::integrations.partials.microsoft_clarity')
                </div>
                <!-- Other Panel -->
                <div class="tab-pane fade" id="other" role="tabpanel">
                    @include('seo::integrations.partials.other')
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
        <script data-up-execute>
            // Define field labels for validation (optional)
            window.settingsFieldLabels = {
                google_search_console: 'Google Search Console',
                bing_webmaster: 'Bing Webmaster Tools',
                baidu_webmaster: 'Baidu Webmaster Tools',
                yandex_verification: 'Yandex Verification ID',
                pinterest_verification: 'Pinterest Verification ID',
                norton_verification: 'Norton Safe Web Verification ID',
                custom_meta_tags: 'Custom Webmaster Tags',
                google_analytics: 'Google Analytics Tracking Script',
                google_tags: 'Google Tag Manager Script',
                meta_pixel: 'Meta Pixel Code',
                ms_clarity: 'Microsoft Clarity Script',
                other: 'Custom Tags',
            };

            // Explicitly initialize section pills for this page
            if (window.initSectionPills) {
                window.initSectionPills();
            }
        </script>
    @endpush
</x-app-layout>
