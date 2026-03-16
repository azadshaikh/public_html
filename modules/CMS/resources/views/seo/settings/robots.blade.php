<x-app-layout :title="$page_title">
    <x-page-header
        title="{{ $page_title }}"
        description="Configure robots.txt file to control how search engine crawlers access your website."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'SEO', 'href' => route('seo.dashboard')],
            ['label' => 'Robots.txt', 'active' => true]
        ]"
    />

    <x-alert-container containerId="seo-robots-alert-container" :showFlashMessages="false" :fieldLabels="[
        'robots_txt' => 'Robots.txt Content',
    ]" />

    <x-cms::seo-indexing-warning />

    <div class="row g-4">
        <!-- Vertical Pills Navigation -->
        <div class="col-lg-3">
            <div class="nav flex-column nav-pills gap-2" role="tablist">
                <button class="nav-link active d-flex align-items-center" data-section="robots-settings-section" type="button" role="tab">
                    <i class="ri-robot-line me-2"></i>
                    <span>{{ __('seo::seo.robots') }}</span>
                </button>
            </div>
        </div>

        <!-- Settings Forms Container -->
        <div class="col-lg-9">
            <div class="tab-content">
                <!-- General Settings Panel -->
                <div class="tab-pane fade show active" id="robots-settings-section" role="tabpanel">
                    <div class="card">
                        <div class="card-header mb-3">
                            <div class="d-flex align-items-center">
                                <h5 class="card-title">{{ __('seo::seo.robots_settings') }}</h5>
                            </div>
                        </div>
                        <form class="needs-validation" id="robots-form"
                            action="{{ route('seo.settings.robots.update') }}"
                            method="POST" novalidate>
                            @csrf
                            <input name="section" type="hidden" value="robots-settings-section">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="">
                                        <div class="form-outline">

                                            @php
                                                $robots_txt_content = '';
                                                if (isset($robots_txt) && !empty($robots_txt)) {
                                                    $robots_txt_content = $robots_txt;
                                                }
                                            @endphp
                                            <x-textarea-monaco syntax="plaintext">
                                                <textarea class="form-control form-control-lg @if ($errors->has('robots_txt')) is-invalid @endif"
                                                    id="robots_txt" name="robots_txt" rows="15">{{ $robots_txt_content }}</textarea>
                                            </x-textarea-monaco>

                                            <div class="form-text">
                                                Recommended: Ensure your sitemap URL is included in `robots.txt` for
                                                optimal crawling. Example:<br>
                                                Sitemap: {{ app_url() }}/sitemap.xml
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <p class="fw-medium">View Current `robots.txt`</p>
                                    </div>
                                    <div class="col-md-9">
                                        <a class="btn btn-flex btn-light-primary px-3 py-1"
                                            href="{{ url('/robots.txt') }}" target="_blank">
                                            <i class="ri-external-link-line"></i>
                                            <span class="d-flex flex-column align-items-center ms-2">
                                                <span class="fs fw-normal">Open `Robots.txt` In New Tab</span>
                                            </span>
                                        </a>
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
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
        <script data-up-execute>
            // Define field labels for validation (optional)
            window.settingsFieldLabels = {
                robots_txt: 'Robots.txt Content',
            };

            // Explicitly initialize section pills for this page
            if (window.initSectionPills) {
                window.initSectionPills();
            }
        </script>
    @endpush
</x-app-layout>
