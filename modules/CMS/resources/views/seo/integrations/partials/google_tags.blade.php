<div class="card">
    <div class="card-header mb-3">
        <div class="d-flex flex-column">
            <h5 class="card-title mb-1">{{ __('seo::seo.google_tag_manager') }}</h5>
            <p class="text-muted small mb-0">{{ __('seo::seo.google_tags_info') }}</p>
        </div>
    </div>
    <form class="needs-validation" id="google_tags-form"
        action="{{ route('cms.integrations.googletags.update') }}"
        method="POST" novalidate>
        @csrf
        <input name="section" type="hidden" value="google_tags">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col">
                    <div class="form-outline">
                        @php
                            $google_tags_content = setting(
                                'seo_integrations_google_tags',
                                '',
                            );
                        @endphp
                        <x-textarea-monaco mode="html">
                            <textarea class="form-control form-control-lg @if ($errors->has('google_tags')) is-invalid @endif"
                                id="google_tags" name="google_tags" rows="15" placeholder="{{ __('seo::seo.google_tags_placeholder') }}">{{ $google_tags_content }}</textarea>
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
