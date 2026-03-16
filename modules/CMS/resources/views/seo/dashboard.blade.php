<x-app-layout :title="$page_title">
    <x-page-header
        title="{{ $page_title }}"
        description="Overview of your SEO settings and search engine visibility status."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'SEO']
        ]"
    />

    <div class="row g-4">
        {{-- Search Engine Visibility Status Card --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm {{ $searchEngineEnabled ? 'bg-success-subtle' : 'bg-danger-subtle' }}">
                <div class="card-body py-4">
                    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0 {{ $searchEngineEnabled ? 'bg-success text-white' : 'bg-danger text-white' }}" style="width: 48px; height: 48px; min-width: 48px;">
                                <i class="{{ $searchEngineEnabled ? 'ri-search-eye-line' : 'ri-eye-off-line' }} fs-4"></i>
                            </div>
                            <div>
                                <h5 class="mb-1 {{ $searchEngineEnabled ? 'text-success' : 'text-danger' }}">
                                    @if($searchEngineEnabled)
                                        Search Engines Can Index Your Site
                                    @else
                                        Search Engines Are Blocked
                                    @endif
                                </h5>
                                <p class="mb-0 text-muted small">
                                    @if($searchEngineEnabled)
                                        Your website is visible to Google, Bing, and other search engines.
                                    @else
                                        All pages have <code>noindex, nofollow</code> meta tags.
                                    @endif
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('seo.settings.titlesmeta') }}?section=general" class="btn {{ $searchEngineEnabled ? 'btn-outline-success' : 'btn-danger' }} flex-shrink-0">
                            <i class="ri-settings-3-line me-1"></i>
                            {{ $searchEngineEnabled ? 'Manage' : 'Enable Indexing' }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Links --}}
        <div class="col-12">
            <h5 class="mb-3">SEO Settings</h5>
        </div>

        @foreach($quickLinks as $link)
            <div class="col-md-6 col-lg-4">
                <a href="{{ route($link['route']) }}" class="card h-100 text-decoration-none hover-shadow transition-all">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3">
                            <div class="d-flex align-items-center justify-content-center rounded bg-{{ $link['color'] }}-subtle text-{{ $link['color'] }}" style="width: 45px; height: 45px; flex-shrink: 0;">
                                <i class="{{ $link['icon'] }} fs-5"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 text-body">{{ $link['label'] }}</h6>
                                <p class="mb-0 text-muted small">{{ $link['description'] }}</p>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    @push('styles')
    <style>
        .hover-shadow:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
            transform: translateY(-2px);
        }
        .transition-all {
            transition: all 0.2s ease-in-out;
        }
    </style>
    @endpush
</x-app-layout>
