@php
    // Read settings with the unified seo_integrations_ prefix
    $adsenseSettings = [
        'enabled' => filter_var(setting('seo_integrations_google_adsense_enabled', false), FILTER_VALIDATE_BOOLEAN),
        'code' => setting('seo_integrations_google_adsense_code', ''),
        'hide_for_logged_in' => filter_var(setting('seo_integrations_google_adsense_hide_for_logged_in', false), FILTER_VALIDATE_BOOLEAN),
        'hide_on_homepage' => filter_var(setting('seo_integrations_google_adsense_hide_on_homepage', false), FILTER_VALIDATE_BOOLEAN),
    ];

    // Read ads.txt content from file
    $adsFilePath = public_path('ads.txt');
    $adsTxtContent = file_exists($adsFilePath) ? file_get_contents($adsFilePath) : '';
@endphp

<div class="card">
    <div class="card-header">
        <div class="d-flex flex-column">
            <h5 class="card-title mb-1">{{ __('settings.google_adsense') }}</h5>
            <p class="text-muted small mb-0">Display Google AdSense ads on your website. Configure which pages show ads.</p>
        </div>
    </div>
    <form class="needs-validation" id="google_adsense-form"
        action="{{ route('cms.integrations.googleadsense.update') }}"
        method="POST" novalidate>
        @csrf
        <input name="section" type="hidden" value="google_adsense">
        <div class="card-body">
            <x-form-elements.switch-input layout="horizontal" class="form-group mb-3"
                id="google_adsense_enabled"
                name="google_adsense_enabled"
                value="1"
                label="{{ __('settings.enable_google_adsense') }}"
                labelclass="form-label text-muted"
                ischecked="{{ $adsenseSettings['enabled'] ? 1 : 0 }}" />

            <div id="adsense_config_div" style="display: {{ $adsenseSettings['enabled'] ? 'block' : 'none' }}">
                {{-- Ads.txt content --}}
                <x-form-elements.textarea class="form-group mb-3"
                    id="google_adsense_ads_txt"
                    name="google_adsense_ads_txt"
                    value="{{ $adsTxtContent }}"
                    label="{{ __('settings.google_adsense_text') }}"
                    labelclass="form-label"
                    inputclass="form-control"
                    placeholder="{{ __('settings.enter_google_adsense_text') }}"
                    rows="5"
                    infotext="Content for your ads.txt file. This will be saved to /ads.txt" />

                {{-- AdSense code --}}
                <div class="mb-3">
                    <label class="form-label" for="google_adsense_code">{{ __('settings.google_adsense_code') }}</label>
                    <x-textarea-monaco mode="html">
                        <textarea class="form-control @if ($errors->has('google_adsense_code')) is-invalid @endif"
                            id="google_adsense_code"
                            name="google_adsense_code"
                            rows="8"
                            placeholder="{{ __('settings.enter_google_adsense_code') }}">{{ $adsenseSettings['code'] }}</textarea>
                    </x-textarea-monaco>
                    <span class="form-text text-muted">Paste your Google AdSense script code here. It will be injected into the &lt;head&gt; of your pages.</span>
                </div>

                {{-- Conditional display options --}}
                <div class="border-top pt-3 mt-3">
                    <h6 class="text-muted mb-3">Display Options</h6>

                    <x-form-elements.switch-input layout="horizontal" class="form-group mb-3"
                        id="google_adsense_hide_for_logged_in"
                        name="google_adsense_hide_for_logged_in"
                        value="1"
                        label="Hide ads for logged-in users"
                        labelclass="form-label text-muted"
                        ischecked="{{ $adsenseSettings['hide_for_logged_in'] ? 1 : 0 }}" />

                    <x-form-elements.switch-input layout="horizontal" class="form-group mb-3"
                        id="google_adsense_hide_on_homepage"
                        name="google_adsense_hide_on_homepage"
                        value="1"
                        label="Hide ads on homepage"
                        labelclass="form-label text-muted"
                        ischecked="{{ $adsenseSettings['hide_on_homepage'] ? 1 : 0 }}" />
                </div>
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

@push('scripts')
    <script data-up-execute>
        (function initAdsenseSettings() {
            const form = document.getElementById('google_adsense-form');
            if (!form || form.dataset.adsenseInitialized === 'true') return;
            form.dataset.adsenseInitialized = 'true';

            const toggle = form.querySelector('#google_adsense_enabled');
            const configDiv = form.querySelector('#adsense_config_div');
            if (!toggle || !configDiv) return;

            function toggleAdsenseFields() {
                configDiv.style.display = toggle.checked ? 'block' : 'none';
            }

            toggle.addEventListener('change', toggleAdsenseFields);
        })();
    </script>
@endpush
