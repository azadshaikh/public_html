<div class="card">
    <div class="card-header mb-3">
        <div class="d-flex flex-column">
            <h5 class="card-title mb-1">{{ __('seo::seo.microsoft_clarity_integration') }}</h5>
            <p class="text-muted small mb-0">{{ __('seo::seo.microsoft_clarity_info') }}</p>
        </div>
    </div>
    <form class="needs-validation" id="microsoft_clarity-form"
        action="{{ route('cms.integrations.microsoftclarity.update') }}"
        method="POST" novalidate>
        @csrf
        <input name="section" type="hidden" value="microsoft_clarity">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col">
                    <div class="form-outline">
                        @php
                            $microsoft_clarity_content = setting(
                                'seo_integrations_ms_clarity',
                                '',
                            );
                        @endphp
                        <x-textarea-monaco mode="html">
                            <textarea class="form-control form-control-lg @if ($errors->has('ms_clarity')) is-invalid @endif"
                                id="ms_clarity" name="ms_clarity" rows="15" placeholder="{{ __('seo::seo.microsoft_clarity_placeholder') }}">{{ $microsoft_clarity_content }}</textarea>
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
