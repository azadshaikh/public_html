<div class="card">
    <div class="card-header mb-3">
        <div class="d-flex flex-column">
            <h5 class="card-title mb-1">{{ __('seo::seo.meta_pixel_integration') }}</h5>
            <p class="text-muted small mb-0">{{ __('seo::seo.meta_pixel_info') }}</p>
        </div>
    </div>
    <form class="needs-validation" id="meta_pixel-form"
        action="{{ route('cms.integrations.metapixel.update') }}"
        method="POST" novalidate>
        @csrf
        <input name="section" type="hidden" value="meta_pixel">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col">
                    <div class="form-outline">
                        @php
                            $meta_pixel_content = setting(
                                'seo_integrations_meta_pixel',
                                '',
                            );
                        @endphp
                        <x-textarea-monaco mode="html">
                            <textarea class="form-control form-control-lg @if ($errors->has('meta_pixel')) is-invalid @endif"
                                id="meta_pixel" name="meta_pixel" rows="15" placeholder="{{ __('seo::seo.meta_pixel_placeholder') }}">{{ $meta_pixel_content }}</textarea>
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
