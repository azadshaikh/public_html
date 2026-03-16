{{-- Validation Alert --}}
<x-client-validation-alert />

<div class="row">
    {{-- Main Content --}}
    <div class="col-md-8 mb-3">
        <x-cms.content-form
            :model="$tag ?? null"
            modelName="tag"
            titlePlaceholder="Enter tag title"
            contentPlaceholder="Enter detailed description"
            :metaRobotsOptions="$metaRobotsOptions ?? []"
            :showContentLabel="false"
            :showExcerpt="true"
        />
    </div>

    {{-- Sidebar --}}
    <div class="col-md-4 mb-3">
        @php
            $isEdit = isset($tag) && $tag->exists;
            $statusValue = old('status', $isEdit ? $tag->status : ($defaults['status'] ?? 'draft'));
            $currentSlug = old('slug', isset($tag) ? $tag->slug : '');
            $baseUrl = rtrim(config('app.url'), '/');
            $actualPermalink = $isEdit ? url($tag->permalink_url) : null;

            // If ANY validation error exists, open "More Options"
            $hasAnyError = $errors->any();
        @endphp

        {{-- Featured Image Card --}}
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="card-title mb-0">
                    <i class="ri-image-line me-1"></i> Featured Image
                </h6>
            </div>
            <div class="card-body py-3">
                <x-media-picker.image-field class="form-group" id="feature_image"
                    name="feature_image"
                    :value="old('feature_image', isset($tag) ? $tag->feature_image_id : '')"
                    :valueUrl="isset($tag) ? get_media_url($tag->feature_image_id) : null"
                    :previewUrl="isset($tag) ? get_media_url($tag->feature_image_id) : null"
                    label="" labelClass="form-label" />
            </div>
        </div>

        {{-- Settings Card --}}
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="card-title mb-0">
                    <i class="ri-settings-3-line me-1"></i> Settings
                </h6>
            </div>
            <div class="card-body py-3">
                {{-- Status --}}
                <div class="mb-0">
                    <x-form-elements.select class="form-group" id="status" name="status"
                        :value="$statusValue"
                        label="Status" labelclass="form-label" placeholder="Select status"
                        :options="json_encode($statusOptions ?? [], true)" required />
                </div>
            </div>
        </div>

        {{-- More Options (collapsible) --}}
        <div class="card mb-3">
            <div class="card-header py-2">
                <a class="d-flex align-items-center justify-content-between text-decoration-none text-body {{ $hasAnyError ? '' : 'collapsed' }}"
                   data-bs-toggle="collapse"
                   href="#more-options-collapse"
                   role="button"
                   aria-expanded="{{ $hasAnyError ? 'true' : 'false' }}"
                   aria-controls="more-options-collapse">
                    <h6 class="card-title mb-0">
                        <i class="ri-more-line me-1"></i> More Options
                    </h6>
                    <i class="ri-arrow-down-s-line collapse-icon"></i>
                </a>
            </div>
            <div class="collapse {{ $hasAnyError ? 'show' : '' }}" id="more-options-collapse">
                <div class="card-body py-3">
                    {{-- Slug / Permalink --}}
                    <div class="mb-3" x-data="{
                        slug: '{{ $currentSlug }}',
                        baseUrl: '{{ $baseUrl }}',
                        preSlug: '{{ $preSlug ?? '/' }}',
                        get fullUrl() {
                            return this.baseUrl + this.preSlug + (this.slug || 'your-slug-here');
                        },
                        init() {
                            if (!{{ $isEdit ? 'true' : 'false' }}) {
                                const titleInput = document.getElementById('title');
                                if (titleInput) {
                                    titleInput.addEventListener('input', () => {
                                        this.slug = titleInput.value
                                            .toLowerCase()
                                            .trim()
                                            .replace(/[^a-z0-9\s-]/g, '')
                                            .replace(/\s+/g, '-')
                                            .replace(/-+/g, '-')
                                            .replace(/^-+/, '')
                                            .replace(/-+$/, '');
                                    });
                                }
                            }
                        }
                    }">
                        <label class="form-label" for="slug">Permalink</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted" style="font-size: 0.8125rem;">{{ $preSlug ?? '/' }}</span>
                            <input type="text"
                                class="form-control form-control-sm @error('slug') is-invalid @enderror"
                                id="slug"
                                name="slug"
                                x-model="slug"
                                placeholder="auto-generated-from-title"
                                style="font-size: 0.8125rem;" />
                        </div>
                        @if($isEdit)
                        <a href="{{ $actualPermalink }}" target="_blank" class="form-text small text-truncate d-flex align-items-center gap-1 text-decoration-none" style="max-width: 100%;">
                            <span class="text-truncate">{{ $actualPermalink }}</span>
                            <i class="ri-external-link-line flex-shrink-0"></i>
                        </a>
                        @else
                        <div class="form-text small text-truncate" x-text="fullUrl"></div>
                        @endif
                        @error('slug')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Template (only show if there are custom templates) --}}
                    @if(!empty($templateOptions) && count($templateOptions) > 1)
                    <div class="mb-3">
                        <x-form-elements.select class="form-group" id="template" name="template"
                            :value="old('template', isset($tag) ? $tag->template : '')"
                            label="Template" labelclass="form-label"
                            :options="json_encode($templateOptions ?? [], true)" />
                        <div class="form-text">Choose a different layout for this tag.</div>
                    </div>
                    @endif

                    {{-- Revision Info --}}
                    @if (isset($tag) && $tag->exists)
                        <div class="mt-3 pt-3 border-top">
                            <span class="fw-semibold fs-6 align-items-center d-flex cursor-pointer text-gray-700"
                                data-bs-toggle="modal" data-bs-target="#revision-modal" data-skip-ajax="true">
                                <i class="ri-history-line me-1"></i> {{ $tag->revisions_count }} Revisions
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Submit Button --}}
        <div class="d-grid mb-3">
            <button class="btn btn-primary btn-lg" type="submit" id="save-btn">
                <i class="ri-save-line me-2"></i>
                <span class="btn-text">
                    {{ isset($tag) && $tag->exists ? 'Save Changes' : 'Create Tag' }}
                </span>
            </button>
        </div>

        {{-- Danger Zone Card (only on edit) --}}
        @if (isset($tag) && $tag->exists)
            @can('delete_tags')
                <div class="card border-danger">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-danger mb-1">
                                    <i class="ri-error-warning-line me-1"></i>Danger Zone
                                </h6>
                                <p class="text-muted small mb-0">Move this tag to trash.</p>
                            </div>
                            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#trash-confirm-modal">
                                <i class="ri-delete-bin-line me-1"></i>Trash
                            </button>
                        </div>
                    </div>
                </div>
            @endcan
        @endif
    </div>
</div>

<x-media-picker.media-modal mediaconversion="small" />

@if (isset($tag) && !empty($tag))
    <x-revisions-modal :modeltitle="$tag->title" :revisions="$tag->revisionHistory" :preview="$tag->type" />

    {{-- Trash Confirm Modal --}}
    @can('delete_tags')
        <div class="modal fade" id="trash-confirm-modal" tabindex="-1" aria-labelledby="trash-confirm-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title d-flex align-items-center gap-2" id="trash-confirm-modal-label">
                            <i class="ri-delete-bin-line text-danger" style="font-size: 1.5rem;"></i>
                            Move to Trash
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Are you sure you want to move <strong>"{{ $tag->title }}"</strong> to trash?</p>
                        <p class="text-muted small mb-0 mt-2">You can restore this tag later from the trash.</p>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="document.getElementById('trash-tag-form')?.requestSubmit();">
                            <i class="ri-delete-bin-line me-1"></i>Move to Trash
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endif

@push('scripts')
    <script data-up-execute>
        // Open "More Options" when any field inside it has a validation error (client-side)
        (function() {
            const moreOptionsCollapse = document.getElementById('more-options-collapse');
            if (!moreOptionsCollapse) return;

            const form = moreOptionsCollapse.closest('form');
            if (!form) return;

            function openMoreOptions() {
                if (moreOptionsCollapse.classList.contains('show')) return;

                try {
                    if (window.bootstrap?.Collapse) {
                        const collapse = bootstrap.Collapse.getOrCreateInstance(moreOptionsCollapse, { toggle: false });
                        collapse.show();
                    } else {
                        moreOptionsCollapse.classList.add('show');
                        const toggle = document.querySelector('[href="#more-options-collapse"]');
                        if (toggle) {
                            toggle.classList.remove('collapsed');
                            toggle.setAttribute('aria-expanded', 'true');
                        }
                    }
                } catch (e) {
                    moreOptionsCollapse.classList.add('show');
                }
            }

            form.addEventListener('submit', function(e) {
                const invalidFields = moreOptionsCollapse.querySelectorAll(':invalid');
                if (invalidFields.length > 0 && !moreOptionsCollapse.classList.contains('show')) {
                    e.preventDefault();
                    openMoreOptions();
                    setTimeout(() => {
                        invalidFields[0].reportValidity();
                    }, 350);
                }
            }, true);

            moreOptionsCollapse.addEventListener('invalid', function(e) {
                openMoreOptions();
            }, true);
        })();
    </script>
@endpush

@push('styles')
<style>
    .collapse-icon {
        transition: transform 0.2s ease;
    }
    [aria-expanded="true"] .collapse-icon {
        transform: rotate(180deg);
    }
</style>
@endpush
