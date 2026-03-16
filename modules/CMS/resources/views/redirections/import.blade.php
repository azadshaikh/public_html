<x-app-layout title="Import Redirects">

    <x-page-header
        title="Import Redirects"
        description="Import redirects from a CSV file"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'Redirections', 'href' => route('cms.redirections.index')],
            ['label' => 'Import', 'active' => true],
        ]"
    />

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ri-upload-2-line me-2"></i>Import from CSV
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('cms.redirections.import') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-4">
                            <label class="form-label required" for="file">CSV File</label>
                            <input
                                type="file"
                                class="form-control {{ $errors->has('file') ? 'is-invalid' : '' }}"
                                id="file"
                                name="file"
                                accept=".csv,.txt"
                                required
                            />
                            @if ($errors->has('file'))
                                <div class="invalid-feedback">{{ $errors->first('file') }}</div>
                            @endif
                            <div class="form-text">
                                Upload a CSV file with redirect rules. Maximum file size: 10MB.
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="skip_duplicates"
                                    name="skip_duplicates"
                                    value="1"
                                    checked
                                />
                                <label class="form-check-label" for="skip_duplicates">
                                    Skip duplicate source URLs
                                </label>
                            </div>
                            <div class="form-text ms-4">
                                If a source URL already exists, skip it instead of showing an error.
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="update_existing"
                                    name="update_existing"
                                    value="1"
                                />
                                <label class="form-check-label" for="update_existing">
                                    Update existing redirects
                                </label>
                            </div>
                            <div class="form-text ms-4">
                                If a source URL already exists, update it with the new data from the CSV.
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('cms.redirections.index') }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-upload-2-line me-1"></i>
                                Import Redirects
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- CSV Format Help --}}
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ri-information-line me-2"></i>CSV Format
                    </h5>
                </div>
                <div class="card-body">
                    <p>Your CSV file should have the following columns:</p>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Column</th>
                                    <th>Required</th>
                                    <th>Description</th>
                                    <th>Example</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>source_url</code></td>
                                    <td><span class="badge bg-danger">Required</span></td>
                                    <td>The "from" URL path</td>
                                    <td><code>/old-page</code></td>
                                </tr>
                                <tr>
                                    <td><code>target_url</code></td>
                                    <td><span class="badge bg-danger">Required</span></td>
                                    <td>The "to" URL destination</td>
                                    <td><code>/new-page</code></td>
                                </tr>
                                <tr>
                                    <td><code>redirect_type</code></td>
                                    <td><span class="badge text-bg-secondary">Optional</span></td>
                                    <td>HTTP status code (default: 301)</td>
                                    <td><code>301</code>, <code>302</code>, <code>307</code>, <code>308</code></td>
                                </tr>
                                <tr>
                                    <td><code>url_type</code></td>
                                    <td><span class="badge text-bg-secondary">Optional</span></td>
                                    <td>Target type (default: internal)</td>
                                    <td><code>internal</code>, <code>external</code></td>
                                </tr>
                                <tr>
                                    <td><code>match_type</code></td>
                                    <td><span class="badge text-bg-secondary">Optional</span></td>
                                    <td>Matching type (default: exact)</td>
                                    <td><code>exact</code>, <code>wildcard</code>, <code>regex</code></td>
                                </tr>
                                <tr>
                                    <td><code>status</code></td>
                                    <td><span class="badge text-bg-secondary">Optional</span></td>
                                    <td>Active status (default: active)</td>
                                    <td><code>active</code>, <code>inactive</code></td>
                                </tr>
                                <tr>
                                    <td><code>notes</code></td>
                                    <td><span class="badge text-bg-secondary">Optional</span></td>
                                    <td>Internal notes</td>
                                    <td>Any text</td>
                                </tr>
                                <tr>
                                    <td><code>expires_at</code></td>
                                    <td><span class="badge text-bg-secondary">Optional</span></td>
                                    <td>Expiration date</td>
                                    <td><code>2025-12-31T23:59:59</code></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="mt-4">Example CSV:</h6>
                    <pre class="bg-light p-3 rounded"><code>source_url,target_url,redirect_type,url_type,match_type,status
/old-page,/new-page,301,internal,exact,active
/blog/*,/articles/$1,301,internal,wildcard,active
/products,https://shop.example.com,302,external,exact,active</code></pre>

                    <div class="mt-3">
                        <a href="{{ route('cms.redirections.export') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="ri-download-line me-1"></i>
                            Download Sample CSV (Export Current)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
