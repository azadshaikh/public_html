<div class="card">
    <div class="card-header mb-3">
        <div class="d-flex align-items-center">
            <h5 class="card-title">{{ __('seo::seo.webmaster_tools_settings') }}</h5>
        </div>
    </div>
    <form class="needs-validation" id="webmaster-tools-form"
        action="{{ route('cms.integrations.webmastertools.update') }}"
        method="POST" novalidate>
        @csrf
        <input name="section" type="hidden" value="webmaster_tools">
        <div class="card-body">

            <!-- Google Search Console -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label" for="google_search_console">Google Search Console</label>
                </div>
                <div class="col-md-9">
                    <div class="form-outline">
                        <x-textarea-monaco mode="html">
                            <textarea class="form-control @if ($errors->has('google_search_console')) is-invalid @endif"
                                id="google_search_console"
                                name="google_search_console"
                                rows="2"
                                placeholder="<meta name=&quot;google-site-verification&quot; content=&quot;your-code&quot; />">{{ setting('seo_integrations_google_search_console', '') }}</textarea>
                        </x-textarea-monaco>
                        <span class="form-text text-muted">Paste the full meta tag from <a href='https://search.google.com/search-console/welcome' target='_blank' rel='noopener'>Google Search Console</a>.</span>
                    </div>
                </div>
            </div>

            <!-- Bing Webmaster Tools -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label" for="bing_webmaster">Bing Webmaster Tools</label>
                </div>
                <div class="col-md-9">
                    <div class="form-outline">
                        <x-textarea-monaco mode="html">
                            <textarea class="form-control @if ($errors->has('bing_webmaster')) is-invalid @endif"
                                id="bing_webmaster"
                                name="bing_webmaster"
                                rows="2"
                                placeholder="<meta name=&quot;msvalidate.01&quot; content=&quot;your-code&quot; />">{{ setting('seo_integrations_bing_webmaster', '') }}</textarea>
                        </x-textarea-monaco>
                        <span class="form-text text-muted">Paste the full meta tag from <a href='https://www.bing.com/webmasters' target='_blank' rel='noopener'>Bing Webmaster Tools</a>.</span>
                    </div>
                </div>
            </div>

            <!-- Baidu Webmaster Tools -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label" for="baidu_webmaster">Baidu Webmaster Tools</label>
                </div>
                <div class="col-md-9">
                    <div class="form-outline">
                        <x-textarea-monaco mode="html">
                            <textarea class="form-control @if ($errors->has('baidu_webmaster')) is-invalid @endif"
                                id="baidu_webmaster"
                                name="baidu_webmaster"
                                rows="2"
                                placeholder="<meta name=&quot;baidu-site-verification&quot; content=&quot;your-code&quot; />">{{ setting('seo_integrations_baidu_webmaster', '') }}</textarea>
                        </x-textarea-monaco>
                        <span class="form-text text-muted">Paste the full meta tag from <a href='https://ziyuan.baidu.com/site/index' target='_blank' rel='noopener'>Baidu Webmaster</a>.</span>
                    </div>
                </div>
            </div>

            <!-- Yandex Verification -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label" for="yandex_verification">Yandex Webmaster</label>
                </div>
                <div class="col-md-9">
                    <div class="form-outline">
                        <x-textarea-monaco mode="html">
                            <textarea class="form-control @if ($errors->has('yandex_verification')) is-invalid @endif"
                                id="yandex_verification"
                                name="yandex_verification"
                                rows="2"
                                placeholder="<meta name=&quot;yandex-verification&quot; content=&quot;your-code&quot; />">{{ setting('seo_integrations_yandex_verification', '') }}</textarea>
                        </x-textarea-monaco>
                        <span class="form-text text-muted">Paste the full meta tag from <a href='https://webmaster.yandex.com/' target='_blank' rel='noopener'>Yandex Webmaster</a>.</span>
                    </div>
                </div>
            </div>

            <!-- Pinterest Verification -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label" for="pinterest_verification">Pinterest</label>
                </div>
                <div class="col-md-9">
                    <div class="form-outline">
                        <x-textarea-monaco mode="html">
                            <textarea class="form-control @if ($errors->has('pinterest_verification')) is-invalid @endif"
                                id="pinterest_verification"
                                name="pinterest_verification"
                                rows="2"
                                placeholder="<meta name=&quot;p:domain_verify&quot; content=&quot;your-code&quot; />">{{ setting('seo_integrations_pinterest_verification', '') }}</textarea>
                        </x-textarea-monaco>
                        <span class="form-text text-muted">Paste the full meta tag from <a href='https://www.pinterest.com/settings/claim' target='_blank' rel='noopener'>Pinterest</a>.</span>
                    </div>
                </div>
            </div>

            <!-- Norton Safe Web Verification -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label" for="norton_verification">Norton Safe Web</label>
                </div>
                <div class="col-md-9">
                    <div class="form-outline">
                        <x-textarea-monaco mode="html">
                            <textarea class="form-control @if ($errors->has('norton_verification')) is-invalid @endif"
                                id="norton_verification"
                                name="norton_verification"
                                rows="2"
                                placeholder="<meta name=&quot;norton-safeweb-site-verification&quot; content=&quot;your-code&quot; />">{{ setting('seo_integrations_norton_verification', '') }}</textarea>
                        </x-textarea-monaco>
                        <span class="form-text text-muted">Paste the full meta tag from <a href='https://safeweb.norton.com/help/site_owners' target='_blank' rel='noopener'>Norton Safe Web</a>.</span>
                    </div>
                </div>
            </div>

            <!-- Custom Webmaster Tags -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label" for="custom_meta_tags">Other Meta Tags</label>
                </div>
                <div class="col-md-9">
                    <div class="form-outline">
                        @php
                            $custom_meta_tags = setting('seo_integrations_custom_meta_tags', '');
                        @endphp
                        <x-textarea-monaco mode="html">
                            <textarea class="form-control @if ($errors->has('custom_meta_tags')) is-invalid @endif"
                                id="custom_meta_tags"
                                name="custom_meta_tags"
                                rows="4"
                                placeholder="<meta name=&quot;example&quot; content=&quot;value&quot; />">{{ $custom_meta_tags }}</textarea>
                        </x-textarea-monaco>
                        <span class="form-text text-muted">Paste any additional verification meta tags not listed above. One tag per line.</span>
                    </div>
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
