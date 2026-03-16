<div class="card">
    <div class="card-header mb-3">
        <div class="d-flex flex-column">
            <h5 class="card-title mb-1">{{ __('seo::seo.google_analytics_tracking') }}</h5>
            <p class="text-muted small mb-0">{{ __('seo::seo.google_analytics_tracking_info') }}</p>
        </div>
    </div>
    <form class="needs-validation" id="google_analytics-form"
        action="{{ route('cms.integrations.googleanalytics.update') }}"
        method="POST" novalidate>
        @csrf
        <input name="section" type="hidden" value="google_analytics">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col">
                    <div class="form-outline">
                        @php
                            $google_analytics_content = setting(
                                'seo_integrations_google_analytics',
                                '',
                            );
                        @endphp
                        <x-textarea-monaco mode="html">
                            <textarea class="form-control form-control-lg @if ($errors->has('google_analytics')) is-invalid @endif"
                                id="google_analytics" name="google_analytics" rows="15"
                                placeholder="{{ __('seo::seo.tracking_script_placeholder') }}">{{ $google_analytics_content }}</textarea>
                        </x-textarea-monaco>
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
