{{--
SEO Indexing Warning Component
Shows a warning when search engine indexing is disabled.
Can be used on dashboard and all SEO settings pages.
--}}
@if(auth()->user()->can('manage_seo_settings'))
    @php
        $searchEngineVisibility = setting('seo_search_engine_visibility', 'true');
        $isNoIndex = in_array($searchEngineVisibility, ['false', false, '0', 0], true);
    @endphp
    @if($isNoIndex)
        <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
            <div class="d-flex align-items-center justify-content-center bg-warning text-white me-3 rounded-circle" style="width: 40px; height: 40px; flex-shrink: 0;">
                <i class="ri-forbid-line fs-5"></i>
            </div>
            <div class="flex-grow-1">
                <strong>{{ __('seo::seo.search_engine_indexing_disabled') }}</strong>
                <div class="small">{{ __('seo::seo.search_engine_indexing_disabled_description', ['meta_tags' => 'noindex, nofollow']) }}</div>
            </div>
            <a href="{{ route('seo.settings.titlesmeta') }}" class="btn btn-warning btn-sm ms-3">
                <i class="ri-settings-3-line me-1"></i>{{ __('seo::seo.fix_now') }}
            </a>
        </div>
    @endif
@endif
