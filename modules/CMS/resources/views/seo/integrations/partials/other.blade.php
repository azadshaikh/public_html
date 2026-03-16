<div class="card">
    <div class="card-header mb-3">
        <div class="d-flex flex-column">
            <h5 class="card-title mb-1">{{ __('seo::seo.other_misc_integration') }}</h5>
            <p class="text-muted small mb-0">Add custom scripts, tracking codes, or meta tags. These will be injected into the <code>&lt;head&gt;</code> section of your pages.</p>
        </div>
    </div>
    <form class="needs-validation" id="other-form"
        action="{{ route('cms.integrations.other.update') }}"
        method="POST" novalidate>
        @csrf
        <input name="section" type="hidden" value="other">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col">
                    <div class="form-outline">
                        @php
                            $other_misc_content = setting('seo_integrations_other', '');
                        @endphp
                        <x-textarea-monaco mode="html">
                            <textarea class="form-control form-control-lg @if ($errors->has('other')) is-invalid @endif"
                                id="other" name="other" rows="15" placeholder="{{ __('seo::seo.other_misc_placeholder') }}">{{ $other_misc_content }}</textarea>
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
