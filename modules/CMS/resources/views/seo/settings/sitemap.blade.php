<x-app-layout :title="$page_title">
    <x-page-header
        title="{{ $page_title }}"
        description="Configure XML sitemap settings to help search engines discover and index your website content."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'SEO', 'href' => route('seo.dashboard')],
            ['label' => 'Sitemap', 'active' => true]
        ]"
    />

    <x-alert-container containerId="seo-sitemap-alert-container" :showFlashMessages="false" :fieldLabels="[
        'enabled' => 'Enable Sitemap',
        'posts_enabled' => 'Include Posts',
        'pages_enabled' => 'Include Pages',
        'categories_enabled' => 'Include Categories',
        'tags_enabled' => 'Include Tags',
        'authors_enabled' => 'Include Authors',
        'auto_regenerate' => 'Auto-Regenerate',
        'links_per_file' => 'Links Per File',
    ]" />

    <x-cms::seo-indexing-warning />

    <div class="row g-4">
        <!-- Settings Form -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Sitemap Settings</h5>
                </div>
                <form class="needs-validation" id="sitemap-form"
                    action="{{ route('seo.settings.sitemap.update') }}"
                    method="POST" novalidate>
                    @csrf
                    <div class="card-body mt-3">
                        <!-- Master Enable Toggle -->
                        <x-form-elements.switch-input
                            layout="horizontal"
                            class="form-group mb-4 pb-3 border-bottom"
                            id="enabled"
                            name="enabled"
                            labelclass="fw-medium"
                            label="Enable XML Sitemap"
                            :value="1"
                            :ischecked="$sitemapStatus['enabled'] ? 1 : 0"
                            infotext="Generate XML sitemaps for search engines to discover and index your content." />

                        <!-- Sitemap Settings Container (hidden when sitemap is disabled) -->
                        <div id="sitemap-settings-fields" style="{{ $sitemapStatus['enabled'] ? '' : 'display: none;' }}">
                            <!-- Content Types Section -->
                            <h6 class="text-uppercase text-muted mb-3">
                                <i class="ri-file-list-3-line me-1"></i> Content Types
                            </h6>
                            <div class="row mb-4">
                                @foreach($sitemapStatus['types'] as $type => $typeData)
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center justify-content-between p-3 border rounded bg-light-subtle">
                                        <div class="d-flex align-items-center">
                                            <i class="{{ $typeData['icon'] }} fs-5 me-2 text-primary"></i>
                                            <span class="fw-medium">{{ $typeData['label'] }}</span>
                                        </div>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox"
                                                id="{{ $type }}_enabled"
                                                name="{{ $type }}_enabled"
                                                value="1"
                                                {{ $typeData['enabled'] ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>

                            <!-- Advanced Settings Section -->
                            <h6 class="text-uppercase text-muted mb-3">
                                <i class="ri-settings-3-line me-1"></i> Advanced Settings
                            </h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <x-form-elements.switch-input
                                        layout="horizontal"
                                        class="form-group mb-3"
                                        id="auto_regenerate"
                                        name="auto_regenerate"
                                        labelclass="fw-medium"
                                        label="Auto-Regenerate on Changes"
                                        :value="1"
                                        :ischecked="setting('seo.sitemap.auto_regenerate', true) ? 1 : 0"
                                        infotext="Automatically regenerate sitemaps when content is created, updated, or deleted." />
                                </div>
                                <div class="col-md-6">
                                    <x-form-elements.input
                                        layout="horizontal"
                                        class="mb-3"
                                        id="links_per_file"
                                        name="links_per_file"
                                        divclass="form-group"
                                        labelclass="form-label"
                                        label="Links Per File"
                                        inputclass="form-control"
                                        type="number"
                                        min="100"
                                        max="50000"
                                        placeholder="1000"
                                        :value="setting('seo.sitemap.links_per_file', 1000)"
                                        infotext="Maximum URLs per sitemap file (100-50,000)." />
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
        </div>

        <!-- Status & Actions Sidebar -->
        <div class="col-lg-4">
            <!-- Status Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ri-information-line me-1"></i> Status
                    </h5>
                </div>
                <div class="card-body">
                    @if($sitemapStatus['enabled'])
                        <!-- Last Generated -->
                        <div class="mb-3">
                            <label class="text-muted small">Last Generated</label>
                            <p class="mb-0 fw-medium">
                                @if($sitemapStatus['last_generated_at'])
                                    <i class="ri-calendar-line me-1 text-success"></i>
                                    {{ \Carbon\Carbon::parse($sitemapStatus['last_generated_at'])->format('M d, Y h:i A') }}
                                @else
                                    <i class="ri-calendar-line me-1 text-warning"></i>
                                    Never generated
                                @endif
                            </p>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-grid gap-2">
                            <form action="{{ route('seo.sitemap.regenerate') }}" method="POST" id="regenerate-form" data-no-unpoly>
                                @csrf
                                <button type="submit" class="btn btn-primary w-100" id="regenerate-btn">
                                    <i class="ri-refresh-line me-1"></i>
                                    Regenerate Now
                                </button>
                            </form>
                            <a href="{{ route('sitemap') }}" target="_blank" class="btn btn-outline-secondary">
                                <i class="ri-external-link-line me-1"></i>
                                View Sitemap
                            </a>
                        </div>
                    @else
                        <div class="text-center py-3">
                            <i class="ri-file-forbid-line fs-1 text-muted"></i>
                            <p class="text-muted mt-2 mb-0">Sitemap is currently disabled</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Statistics Card -->
            @if($sitemapStatus['enabled'])
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ri-bar-chart-line me-1"></i> Statistics
                    </h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach($sitemapStatus['types'] as $type => $typeData)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <i class="{{ $typeData['icon'] }} me-2 text-muted"></i>
                                {{ $typeData['label'] }}
                            </span>
                            @if($typeData['enabled'])
                                <span class="badge bg-primary-subtle text-primary">{{ number_format($typeData['count']) }} URLs</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">Disabled</span>
                            @endif
                        </li>
                        @endforeach
                        <li class="list-group-item d-flex justify-content-between align-items-center bg-light">
                            <span class="fw-bold">Total</span>
                            <span class="badge bg-success-subtle text-success fw-bold">{{ number_format($sitemapStatus['total_urls']) }} URLs</span>
                        </li>
                    </ul>
                </div>
            </div>
            @endif

            <!-- Help & Documentation Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ri-question-line me-1"></i> About Sitemaps
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        An XML sitemap helps search engines like Google and Bing discover your website's pages faster. It lists your URLs and provides metadata like when content was last updated.
                    </p>
                    <p class="text-muted small mb-3">
                        <i class="ri-lightbulb-line text-warning me-1"></i>
                        <strong>Good to know:</strong> Sitemaps help discovery, but don't guarantee indexing. To control what gets indexed, configure your robots meta tags in Titles & Meta settings.
                    </p>

                    @if($sitemapStatus['enabled'])
                    <div class="bg-light rounded p-3 mb-3">
                        <label class="text-muted small d-block mb-1">Submit this URL to search engines:</label>
                        <div class="d-flex align-items-center gap-2">
                            <code class="text-break flex-grow-1">{{ url('/sitemap.xml') }}</code>
                            <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0" onclick="navigator.clipboard.writeText('{{ url('/sitemap.xml') }}'); this.innerHTML='<i class=\'ri-check-line\'></i>'; setTimeout(() => this.innerHTML='<i class=\'ri-file-copy-line\'></i>', 1500);">
                                <i class="ri-file-copy-line"></i>
                            </button>
                            <a href="{{ url('/sitemap.xml') }}" target="_blank" class="btn btn-sm btn-outline-secondary flex-shrink-0">
                                <i class="ri-external-link-line"></i>
                            </a>
                        </div>
                    </div>
                    @endif

                    <div class="alert alert-light border mb-0 py-2">
                        <i class="ri-eye-off-line text-secondary me-1"></i>
                        <small>Pages set to <code>noindex</code> or using custom canonical URLs are automatically excluded from sitemaps.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script data-up-execute>
            (function initSitemapSettings() {
                const form = document.getElementById('sitemap-form');
                if (!form || form.dataset.sitemapInitialized === 'true') return;
                form.dataset.sitemapInitialized = 'true';

                // Toggle sitemap settings visibility based on enable switch
                const enableSwitch = form.querySelector('#enabled');
                const sitemapFieldsContainer = form.querySelector('#sitemap-settings-fields');
                if (!enableSwitch || !sitemapFieldsContainer) return;

                // Function to toggle visibility
                function toggleSitemapFields() {
                    sitemapFieldsContainer.style.display = enableSwitch.checked ? 'block' : 'none';
                }

                // Initial state on page load
                toggleSitemapFields();

                // Listen for changes on the enable switch
                enableSwitch.addEventListener('change', toggleSitemapFields);

                // Regenerate button loading state
                const regenerateForm = document.getElementById('regenerate-form');
                const regenerateBtn = document.getElementById('regenerate-btn');

                if (regenerateForm && regenerateBtn) {
                    regenerateForm.addEventListener('submit', function() {
                        regenerateBtn.disabled = true;
                        regenerateBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';
                    });
                }
            })();
        </script>
    @endpush
</x-app-layout>
