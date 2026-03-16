@php
    $isEdit = $redirection->exists ?? false;
    $redirectTypeValue = (string) old('redirect_type', $redirection->redirect_type ?? '301');
    $urlTypeValue = old('url_type', $redirection->url_type ?? 'internal');
    $matchTypeValue = old('match_type', $redirection->match_type ?? 'exact');
    $statusValue = old('status', $redirection->status ?? 'active');
    $baseUrl = rtrim(config('app.url'), '/');
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        {{-- Redirect Rule Card --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">
                    Redirect Rule
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    {{-- Match Type --}}
                    <div class="col-12">
                        <label class="form-label required" for="match_type">
                            Matching Type
                            <i class="ri-information-line text-muted ms-1" data-bs-toggle="tooltip"
                               title="How the source URL should be matched against incoming requests"></i>
                        </label>
                        <select
                            class="form-select {{ $errors->has('match_type') ? 'is-invalid' : '' }}"
                            id="match_type"
                            name="match_type"
                            required
                        >
                            @foreach ($matchTypeOptions as $option)
                                <option
                                    value="{{ $option['value'] }}"
                                    {{ $matchTypeValue === $option['value'] ? 'selected' : '' }}
                                    data-description="{{ $option['description'] ?? '' }}"
                                >
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @if ($errors->has('match_type'))
                            <div class="invalid-feedback">{{ $errors->first('match_type') }}</div>
                        @endif
                        <div class="form-text" id="match-type-help">
                            <span class="match-help-exact" style="display: {{ $matchTypeValue === 'exact' ? 'inline' : 'none' }}">
                                <strong>Exact Match:</strong> URL must match exactly (e.g., <code>/old-page</code> matches only <code>/old-page</code>)
                            </span>
                            <span class="match-help-wildcard" style="display: {{ $matchTypeValue === 'wildcard' ? 'inline' : 'none' }}">
                                <strong>Wildcard:</strong> Use <code>*</code> for single segment, <code>**</code> for multiple (e.g., <code>/blog/*</code> matches <code>/blog/any-post</code>)
                            </span>
                            <span class="match-help-regex" style="display: {{ $matchTypeValue === 'regex' ? 'inline' : 'none' }}">
                                <strong>Regex:</strong> Use regular expressions. Captured groups can be used in target URL as <code>$1</code>, <code>$2</code>, etc.
                            </span>
                        </div>
                    </div>

                    {{-- Source URL --}}
                    <div class="col-12">
                        <label class="form-label required" for="source_url">
                            From URL <span class="text-muted fw-normal">(Source)</span>
                            <i class="ri-information-line text-muted ms-1" data-bs-toggle="tooltip"
                               title="The URL path that visitors will be redirected FROM"></i>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text source-url-prefix" id="source-url-domain" style="display: {{ $matchTypeValue === 'regex' ? 'none' : 'flex' }}">{{ $baseUrl }}</span>
                            <input
                                type="text"
                                class="form-control {{ $errors->has('source_url') ? 'is-invalid' : '' }}"
                                id="source_url"
                                name="source_url"
                                value="{{ old('source_url', $redirection->source_url ?? '') }}"
                                placeholder="{{ $matchTypeValue === 'regex' ? '^/old-(.+)$' : '/old-path' }}"
                                aria-describedby="source-url-domain source-url-help"
                                required
                            />
                            @if ($errors->has('source_url'))
                                <div class="invalid-feedback">{{ $errors->first('source_url') }}</div>
                            @endif
                        </div>
                        <div class="form-text" id="source-url-help">
                            The path that visitors will be redirected from
                        </div>
                    </div>

                    {{-- URL Type --}}
                    <div class="col-md-6">
                        <label class="form-label required" for="url_type">
                            Target Type
                            <i class="ri-information-line text-muted ms-1" data-bs-toggle="tooltip"
                               title="Whether the target is a page on this site or an external website"></i>
                        </label>
                        <select
                            class="form-select {{ $errors->has('url_type') ? 'is-invalid' : '' }}"
                            id="url_type"
                            name="url_type"
                            required
                        >
                            @foreach ($urlTypeOptions as $option)
                                <option value="{{ $option['value'] }}" {{ $urlTypeValue === $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @if ($errors->has('url_type'))
                            <div class="invalid-feedback">{{ $errors->first('url_type') }}</div>
                        @endif
                    </div>

                    {{-- Redirect Type --}}
                    <div class="col-md-6">
                        <label class="form-label required" for="redirect_type">
                            HTTP Status Code
                            <i class="ri-information-line text-muted ms-1" data-bs-toggle="tooltip"
                               title="The HTTP status code affects how search engines handle the redirect"></i>
                        </label>
                        <select
                            class="form-select {{ $errors->has('redirect_type') ? 'is-invalid' : '' }}"
                            id="redirect_type"
                            name="redirect_type"
                            required
                        >
                            @foreach ($redirectTypeOptions as $option)
                                <option
                                    value="{{ $option['value'] }}"
                                    {{ $redirectTypeValue === (string) $option['value'] ? 'selected' : '' }}
                                    data-description="{{ $option['description'] ?? '' }}"
                                >
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @if ($errors->has('redirect_type'))
                            <div class="invalid-feedback">{{ $errors->first('redirect_type') }}</div>
                        @endif
                    </div>

                    {{-- Redirect Type Help Text --}}
                    <div class="col-12">
                        <div class="alert alert-light border py-2 mb-0" id="redirect-type-help">
                            <small>
                                <span class="redirect-help-301" style="display: {{ $redirectTypeValue === '301' ? 'inline' : 'none' }}">
                                    <i class="ri-checkbox-circle-line text-success me-1"></i>
                                    <strong>301 Permanent:</strong> Search engines will transfer SEO value to the new URL. Use for permanent URL changes.
                                </span>
                                <span class="redirect-help-302" style="display: {{ $redirectTypeValue === '302' ? 'inline' : 'none' }}">
                                    <i class="ri-time-line text-warning me-1"></i>
                                    <strong>302 Temporary:</strong> Search engines keep the original URL indexed. Use for temporary changes (maintenance, A/B testing).
                                </span>
                                <span class="redirect-help-307" style="display: {{ $redirectTypeValue === '307' ? 'inline' : 'none' }}">
                                    <i class="ri-time-line text-warning me-1"></i>
                                    <strong>307 Temporary:</strong> Like 302 but strictly preserves the HTTP method (POST remains POST). Use for APIs.
                                </span>
                                <span class="redirect-help-308" style="display: {{ $redirectTypeValue === '308' ? 'inline' : 'none' }}">
                                    <i class="ri-checkbox-circle-line text-success me-1"></i>
                                    <strong>308 Permanent:</strong> Like 301 but strictly preserves the HTTP method. Use for APIs requiring POST redirects.
                                </span>
                            </small>
                        </div>
                    </div>

                    {{-- Target URL --}}
                    <div class="col-12">
                        <label class="form-label required" for="target_url">
                            To URL <span class="text-muted fw-normal">(Destination)</span>
                            <i class="ri-information-line text-muted ms-1" data-bs-toggle="tooltip"
                               title="The URL path that visitors will be redirected TO"></i>
                        </label>
                        <input
                            type="text"
                            class="form-control {{ $errors->has('target_url') ? 'is-invalid' : '' }}"
                            id="target_url"
                            name="target_url"
                            value="{{ old('target_url', $redirection->target_url ?? '') }}"
                            placeholder="{{ $urlTypeValue === 'internal' ? '/new-path' : 'https://example.com/page' }}"
                            aria-describedby="target-url-help"
                            required
                        />
                        @if ($errors->has('target_url'))
                            <div class="invalid-feedback">{{ $errors->first('target_url') }}</div>
                        @endif
                        <div class="form-text" id="target-url-help">
                            <span class="internal-help" style="display: {{ $urlTypeValue === 'internal' ? 'inline' : 'none' }}">
                                Enter an internal path (e.g., <code>/new-page</code>)
                                @if ($matchTypeValue !== 'exact')
                                    — Use <code>$1</code>, <code>$2</code> for captured groups
                                @endif
                            </span>
                            <span class="external-help" style="display: {{ $urlTypeValue === 'external' ? 'inline' : 'none' }}">
                                Enter a complete URL (e.g., <code>https://example.com</code>)
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="col-lg-4">
        {{-- Publish Card --}}
        <div class="card mb-4">
            <div class="card-header py-2">
                <h6 class="card-title">
                    Publish
                </h6>
            </div>
            <div class="card-body">
                {{-- Status --}}
                <div class="mb-3">
                    <label class="form-label required" for="status">Status</label>
                    <select
                        class="form-select {{ $errors->has('status') ? 'is-invalid' : '' }}"
                        id="status"
                        name="status"
                        required
                    >
                        @foreach ($statusOptions as $option)
                            <option value="{{ $option['value'] }}" {{ $statusValue === $option['value'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @if ($errors->has('status'))
                        <div class="invalid-feedback">{{ $errors->first('status') }}</div>
                    @endif
                </div>

                {{-- Expiration Date --}}
                <x-form-elements.datepicker
                    name="expires_at"
                    id="expires_at"
                    label="Expires"
                    mode="datetime"
                    :value="old('expires_at', $redirection->expires_at?->format('Y-m-d H:i'))"
                    placeholder="No expiration"
                    class="mb-3"
                />

                @if ($isEdit)
                    {{-- Stats --}}
                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Hits:</span>
                            <span class="badge bg-primary">{{ number_format($redirection->hits ?? 0) }}</span>
                        </div>
                        @if ($redirection->last_hit_at)
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Last Hit:</span>
                                <span class="text-muted small" title="{{ $redirection->last_hit_at->format('Y-m-d H:i:s') }}">
                                    {{ $redirection->last_hit_at->diffForHumans() }}
                                </span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Created:</span>
                            <span class="text-muted small" title="{{ $redirection->created_at->format('Y-m-d H:i:s') }}">
                                {{ $redirection->created_at->diffForHumans() }}
                            </span>
                        </div>
                    </div>
                @endif

                {{-- Action Buttons --}}
                <div class="d-grid gap-2 mt-4">
                    @if ($isEdit && $redirection->match_type === 'exact')
                        <a href="{{ $baseUrl . $redirection->source_url }}" target="_blank" class="btn btn-outline-secondary">
                            <i class="ri-external-link-line me-1"></i>Test Redirect
                        </a>
                    @endif

                    <button class="btn btn-primary" type="submit">
                        @if ($isEdit)
                            <i class="ri-save-line me-1"></i>Save Changes
                        @else
                            <i class="ri-check-line me-1"></i>Create Redirect
                        @endif
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ route('cms.redirections.index') }}">
                        Cancel
                    </a>
                </div>
            </div>
        </div>

        {{-- Notes Card --}}
        <div class="card">
            <div class="card-header py-2">
                <h6 class="card-title">
                    Internal Notes
                </h6>
            </div>
            <div class="card-body">
                <textarea
                    class="form-control {{ $errors->has('notes') ? 'is-invalid' : '' }}"
                    id="notes"
                    name="notes"
                    rows="4"
                    placeholder="Why was this redirect created? Any context for your team..."
                >{{ old('notes', $redirection->notes ?? '') }}</textarea>
                @if ($errors->has('notes'))
                    <div class="invalid-feedback">{{ $errors->first('notes') }}</div>
                @endif
                <div class="form-text">Private notes for your team</div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script data-up-execute>
const initializeRedirectionForm = (root = document) => {
    const scope = root instanceof HTMLElement ? root : document;
    const form = scope.querySelector('#seo-redirection-form') || document.querySelector('#seo-redirection-form');

    if (!form || form.dataset.jsInitialized === 'true') {
        return;
    }

    form.dataset.jsInitialized = 'true';

    const matchType = form.querySelector('#match_type');
    const urlType = form.querySelector('#url_type');
    const redirectType = form.querySelector('#redirect_type');
    const sourceUrlPrefix = form.querySelector('.source-url-prefix');
    const sourceUrlInput = form.querySelector('#source_url');
    const targetUrlInput = form.querySelector('#target_url');

    const updateMatchTypeUI = (value) => {
        form.querySelectorAll('[class^="match-help-"]').forEach(el => el.style.display = 'none');
        const helpEl = form.querySelector('.match-help-' + value);
        if (helpEl) helpEl.style.display = 'inline';

        if (sourceUrlPrefix) {
            sourceUrlPrefix.style.display = value === 'regex' ? 'none' : 'flex';
        }

        if (sourceUrlInput) {
            sourceUrlInput.placeholder = value === 'regex' ? '^/old-(.+)$' : '/old-path';
        }
    };

    const updateUrlTypeUI = (value) => {
        const internalHelp = form.querySelector('.internal-help');
        const externalHelp = form.querySelector('.external-help');

        if (internalHelp) {
            internalHelp.style.display = value === 'internal' ? 'inline' : 'none';
        }
        if (externalHelp) {
            externalHelp.style.display = value === 'external' ? 'inline' : 'none';
        }

        if (targetUrlInput) {
            targetUrlInput.placeholder = value === 'internal' ? '/new-path' : 'https://example.com/page';
        }
    };

    const updateRedirectTypeUI = (value) => {
        form.querySelectorAll('[class^="redirect-help-"]').forEach(el => el.style.display = 'none');
        const helpEl = form.querySelector('.redirect-help-' + value);
        if (helpEl) helpEl.style.display = 'inline';
    };

    matchType?.addEventListener('change', function() {
        updateMatchTypeUI(this.value);
    });

    urlType?.addEventListener('change', function() {
        updateUrlTypeUI(this.value);
    });

    redirectType?.addEventListener('change', function() {
        updateRedirectTypeUI(this.value);
    });

    if (matchType) {
        updateMatchTypeUI(matchType.value);
    }

    if (urlType) {
        updateUrlTypeUI(urlType.value);
    }

    if (redirectType) {
        updateRedirectTypeUI(redirectType.value);
    }

    if (window.bootstrap?.Tooltip) {
        const tooltipTriggerList = form.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));
    }
};

initializeRedirectionForm();
if (!window.__redirectionFormInitListener) {
    window.__redirectionFormInitListener = true;
    document.addEventListener('up:fragment:inserted', (event) => {
        initializeRedirectionForm(event.target);
    });
}
</script>
@endpush
