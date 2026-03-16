<x-app-layout :title="$page_title">
    <x-page-header
        title="{!! $page_title !!}"
        description="Backup and restore your SEO settings. Export all configurations to a file or import previously saved settings."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'SEO', 'href' => route('seo.dashboard')],
            ['label' => 'Import & Export', 'active' => true],
        ]" />

    {{-- Success/Error Messages --}}
    @if (session('alert-type'))
        <div class="row">
            <div class="col-12">
                <div class="alert alert-{{ session('alert-type') === 'success' ? 'success' : 'danger' }} alert-dismissible fade rounded-4 show mb-4"
                    role="alert">
                    <div class="d-flex align-items-start">
                        <div class="alert-icon me-3 flex-shrink-0">
                            <i class="bi {{ session('alert-type') === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' }}"
                                style="font-size: 1.25rem;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="fw-semibold mb-2">{{ session('alert-type') === 'success' ? 'Success!' : 'Error!' }}
                            </h5>
                            <p class="mb-0">{{ session('message') }}</p>
                        </div>
                    </div>
                    <button class="btn-close" data-bs-dismiss="alert" type="button" aria-label="Close"></button>
                </div>
            </div>
        </div>
    @endif

    {{-- Validation Errors --}}
    @if ($errors->any())
        <div class="row">
            <div class="col-12">
                <div class="alert alert-danger alert-dismissible fade rounded-4 show mb-4" role="alert">
                    <div class="d-flex align-items-start">
                        <div class="alert-icon me-3 flex-shrink-0">
                            <i class="ri-error-warning-fill" style="font-size: 1.25rem;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="fw-semibold mb-2">Validation Error!</h5>
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <button class="btn-close" data-bs-dismiss="alert" type="button" aria-label="Close"></button>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">{!! __('seo::seo.import_export') !!}</h5>
                </div>
                <div class="card-body">
                    <!-- Export Section -->
                    <div class="row">
                        <div class="col-12">
                            <span class="fw-bold mb-1">{{ __('seo::seo.export_seo_settings') }}</span>
                            <hr class="border-primary border-1 border-top my-1 border-dashed">
                        </div>
                        <div class="col-12">
                            <p class="text-muted mb-2">
                                {{ __('seo::seo.export_description') }}
                            </p>
                            @if (!empty($seo_groups))
                                <div class="alert alert-info">
                                    <strong>{{ __('seo::seo.settings_included') }}:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>General SEO settings (site title, meta tags)</li>
                                        <li>Titles & Meta templates for posts, pages, categories</li>
                                        <li>Local SEO & business information</li>
                                        <li>Social media integration settings</li>
                                        <li>Schema markup configuration</li>
                                        <li>Sitemap & robots.txt settings</li>
                                        <li>SEO integrations (Analytics, Tags, etc.)</li>
                                    </ul>
                                </div>
                            @endif
                        </div>
                        <div class="col-12 mt-3 text-end">
                            <button class="btn btn-primary" id="export-btn" type="button"
                                onclick="exportSeoSettings()">
                                <span class="btn-text">{{ __('seo::seo.export') }}</span>
                                <span class="spinner-border spinner-border-sm ms-2 d-none" role="status"
                                    aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Import Section -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <span class="fw-bold mb-1">{{ __('seo::seo.import_seo_settings') }}</span>
                            <hr class="border-primary border-1 border-top my-1 border-dashed">
                        </div>
                        <div class="col-12">
                            <p class="text-muted mb-2">
                                {{ __('seo::seo.import_description') }}
                            </p>
                            <form action="{{ route('seo.settings.import') }}" id="import-form" method="post"
                                enctype="multipart/form-data" onsubmit="handleImportSubmit(event)">
                                @csrf
                                <div class="d-flex mt-2">
                                    <div class="file-input-wrapper w-100">
                                        <div class="form-group">
                                            <input class="form-control" id="import_file" name="import_file" type="file"
                                                accept=".json" required>
                                        </div>
                                    </div>
                                    <div class="button-wrapper ms-2">
                                        <button class="btn btn-primary" id="import-btn" type="submit">
                                            <span class="btn-text">{{ __('seo::seo.import') }}</span>
                                            <span class="spinner-border spinner-border-sm ms-2 d-none" role="status"
                                                aria-hidden="true"></span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function exportSeoSettings() {
                const exportBtn = document.getElementById('export-btn');
                const btnText = exportBtn.querySelector('.btn-text');
                const spinner = exportBtn.querySelector('.spinner-border');

                jQuery.ajax({
                    type: 'post',
                    url: '{{ route('seo.settings.export') }}',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    dataType: 'JSON',
                    beforeSend: function() {
                        exportBtn.disabled = true;
                        btnText.textContent = '{{ __('seo::seo.exporting') }}';
                        spinner.classList.remove('d-none');
                    },
                    success: function(data) {
                        if (data.status == 'success') {
                            // Create download link
                            const downloadAnchorNode = document.createElement('a');
                            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(data.jsondata);
                            downloadAnchorNode.setAttribute("href", dataStr);
                            downloadAnchorNode.setAttribute("download", "seo-settings.json");
                            document.body.appendChild(downloadAnchorNode);
                            downloadAnchorNode.click();
                            downloadAnchorNode.remove();
                        } else {
                            alert(data.message || 'Export failed. Please try again.');
                        }
                    },
                    error: function(xhr) {
                        let message = 'An error occurred while exporting settings.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        alert(message);
                    },
                    complete: function() {
                        exportBtn.disabled = false;
                        btnText.textContent = '{{ __('seo::seo.export') }}';
                        spinner.classList.add('d-none');
                    }
                });
            }

            function handleImportSubmit(event) {
                const importBtn = document.getElementById('import-btn');
                const btnText = importBtn.querySelector('.btn-text');
                const spinner = importBtn.querySelector('.spinner-border');
                const fileInput = document.getElementById('import_file');

                // Check if file is selected
                if (!fileInput.files.length) {
                    event.preventDefault();
                    alert('Please select a JSON file to import.');
                    return false;
                }

                // Show loading state
                importBtn.disabled = true;
                btnText.textContent = '{{ __('seo::seo.importing') }}';
                spinner.classList.remove('d-none');

                // Form will submit normally
                return true;
            }
        </script>
    @endpush
</x-app-layout>

