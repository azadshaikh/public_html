{{-- Validation Alert --}}
<x-client-validation-alert />

<div class="row">
    {{-- Main Content --}}
    <div class="col-md-8 mb-3">
        <x-cms.content-form
            :model="$post ?? null"
            modelName="post"
            titlePlaceholder="Enter post title"
            contentPlaceholder="Enter detailed description"
            :metaRobotsOptions="$metaRobotsOptions ?? []"
            :showContentLabel="false"
            :showExcerpt="true"
        >
            <x-slot:contentFooter>
                @if (isset($post) && $post->exists)
                    <div class="text-muted small mt-2">
                        <i class="ri-time-line me-1"></i>
                        Last updated {{ $post->updated_at->diffForHumans() }}
                        <span title="{{ $post->updated_at->format('M d, Y \a\t h:i A') }}">({{ $post->updated_at->format('M d, Y, h:i A') }})</span>
                    </div>
                @endif
            </x-slot:contentFooter>
        </x-cms.content-form>
    </div>

    {{-- Sidebar --}}
    <div class="col-md-4 mb-3">
        @php
            $isEdit = isset($post) && $post->exists;
            $statusValue = old('status', $isEdit ? $post->status : ($defaults['status'] ?? 'draft'));
            $visibilityValue = old('visibility', $isEdit ? $post->visibility : 'public');
            $currentSlug = old('slug', isset($post) ? $post->slug : '');
            $baseUrl = rtrim(config('app.url'), '/');
            $actualPermalink = $isEdit ? url($post->permalink_url) : null;

            $publishedAtFormatted = null;
            if (old('published_at')) {
                $publishedAtFormatted = old('published_at');
            } elseif (isset($post) && $post->published_at) {
                $publishedAtFormatted = app_date_time_format($post->published_at, 'datetime');
            }
            $showPublishAt = in_array($statusValue, ['scheduled', 'published']);

            $showPasswordFields = $visibilityValue === 'password';
            $hasExistingPassword = isset($post) && $post->isPasswordProtected();

            // Super users should not be pre-selected on create, regular users should be pre-selected
            $isSuperUser = auth()->user()->isSuperUser();
            $defaultAuthorId = $isSuperUser ? null : auth()->id();
            $authorValue = old('author_id', $isEdit ? $post->author_id : $defaultAuthorId);

            // If ANY validation error exists, open "More Options" so user can see all fields
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
                    :value="old('feature_image', isset($post) ? $post->feature_image_id : '')"
                    :valueUrl="isset($post) ? get_media_url($post->feature_image_id) : null"
                    :previewUrl="isset($post) ? get_media_url($post->feature_image_id) : null"
                    label="" labelClass="form-label" />
            </div>
        </div>

        {{-- Publish Settings Card --}}
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="card-title mb-0">
                    <i class="ri-settings-3-line me-1"></i> Publish Settings
                </h6>
            </div>
            <div class="card-body py-3">
                {{-- Status & Visibility Row --}}
                <div class="row">
                    <div class="col-6 mb-3">
                        <x-form-elements.select class="form-group" id="status" name="status"
                            :value="$statusValue"
                            label="Status" labelclass="form-label" placeholder="Select status"
                            :options="json_encode($statusOptions ?? [], true)" required />
                    </div>
                    <div class="col-6 mb-3">
                        <x-form-elements.select class="form-group" id="visibility" name="visibility"
                            :value="$visibilityValue"
                            label="Visibility" labelclass="form-label" placeholder="Select visibility"
                            :options="json_encode($visibilityOptions ?? [], true)" />
                    </div>
                </div>

                {{-- Publish At (shown for scheduled/published) --}}
                <div class="mb-3" id="publish-at-container" style="{{ $showPublishAt ? '' : 'display: none;' }}">
                    @php
                        $publishAtHelpText = 'Select the publish date and time.';
                        if ($statusValue === 'scheduled') {
                            $publishAtHelpText = 'Select a future date and time for scheduling.';
                        } elseif ($statusValue === 'published') {
                            $publishAtHelpText = 'Select the publish date and time (current or past).';
                        }
                        $hasPublishedAtError = $errors->has('published_at') || $errors->has('published_at.date') || $errors->has('published_at.datetime');
                    @endphp

                    <x-form-elements.datepicker
                        class="form-group"
                        id="published_at"
                        name="published_at"
                        :value="$publishedAtFormatted"
                        mode="datetime"
                        label="Publish At"
                        labelclass="form-label"
                        placeholder="Select publish date/time" />

                    @if(!$hasPublishedAtError)
                        <div class="form-text" id="publish-at-help">{{ $publishAtHelpText }}</div>
                    @endif
                </div>

                {{-- Categories --}}
                <div class="mb-3">
                    <x-form-elements.select class="form-group" id="categories" name="categories[]"
                        :value="old('categories', isset($post) && $post->categories ? $post->categories->pluck('id')->toArray() : [])"
                        label="Categories" labelclass="form-label" placeholder="Select categories"
                        :options="json_encode($categoryOptions ?? [], true)" multiple="true" required />
                </div>

                {{-- Tags --}}
                <div class="mb-0">
                    <x-form-elements.select class="form-group" id="tags" name="tags[]"
                        :value="old('tags', isset($post) && $post->tags ? $post->tags->pluck('id')->toArray() : [])"
                        label="Tags" labelclass="form-label" placeholder="Select tags (optional)"
                        :options="json_encode($tagOptions ?? [], true)"
                        :extra-attributes="['multiple' => true]"
                        :choices-config="['removeItemButton' => true]" />
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
                    {{-- Featured Toggle --}}
                    <div class="mb-3">
                        <input type="hidden" name="is_featured" value="0">
                        <x-form-elements.switch-input
                            class="form-group"
                            id="is_featured"
                            name="is_featured"
                            label="Featured Post"
                            labelclass="form-label"
                            :value="1"
                            ischecked="{{ old('is_featured', $isEdit ? (int) ($post->is_featured ?? 0) : 0) ? 1 : 0 }}"
                            infotext="Featured posts appear first (sticky)."
                        />
                    </div>

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

                    {{-- Author --}}
                    <div class="mb-3">
                        <x-form-elements.select class="form-group" id="author_id" name="author_id"
                            :value="$authorValue"
                            label="Author" labelclass="form-label" placeholder="Select author"
                            :options="$authorOptions ?? []" required />
                    </div>

                    {{-- Template (only show if there are custom templates) --}}
                    @if(!empty($templateOptions) && count($templateOptions) > 1)
                    <div class="mb-3">
                        <x-form-elements.select class="form-group" id="template" name="template"
                            :value="old('template', isset($post) ? $post->template : '')"
                            label="Template" labelclass="form-label"
                            :options="json_encode($templateOptions ?? [], true)" />
                        <div class="form-text">Choose a different layout for this post.</div>
                    </div>
                    @endif

                    {{-- Password Protection Fields (shown when visibility is password) --}}
                    <div class="mb-3" id="password-protection-container" style="{{ $showPasswordFields ? '' : 'display: none;' }}">
                        <x-form-elements.password-input
                            class="form-group"
                            id="post_password"
                            name="post_password"
                            label="{{ __('general.post_password') }}"
                            labelclass="form-label"
                            placeholder="{{ $hasExistingPassword ? __('Leave blank to keep current password') : __('Enter password') }}"
                            inputclass="form-control" />
                        <div class="form-text">
                            @if($hasExistingPassword)
                                {{ __('Leave blank to keep the current password, or enter a new one to change it.') }}
                            @else
                                {{ __('Set a password that visitors must enter to view this content.') }}
                            @endif
                        </div>
                    </div>

                    <div id="password-hint-container" style="{{ $showPasswordFields ? '' : 'display: none;' }}">
                        <x-form-elements.input
                            class="form-group"
                            id="password_hint"
                            name="password_hint"
                            :value="old('password_hint', isset($post) ? $post->password_hint : '')"
                            label="{{ __('general.password_hint') }}"
                            labelclass="form-label"
                            placeholder="Optional hint for visitors"
                            inputclass="form-control" />
                        <div class="form-text">{{ __('general.password_hint_help') }}</div>
                    </div>

                    {{-- Revision Info --}}
                    @if (isset($post) && $post->exists)
                        <div class="mt-3 pt-3 border-top">
                            <span class="fw-semibold fs-6 align-items-center d-flex cursor-pointer text-gray-700"
                                data-bs-toggle="modal" data-bs-target="#revision-modal" data-skip-ajax="true">
                                <i class="ri-history-line me-1"></i> {{ $post->revisions_count }} Revisions
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
                    {{ isset($post) && $post->exists ? 'Save Changes' : 'Create Post' }}
                </span>
            </button>
        </div>

        {{-- Danger Zone Card (only on edit) --}}
        @if (isset($post) && $post->exists)
            @can('delete_posts')
                <div class="card border-danger">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-danger mb-1">
                                    <i class="ri-error-warning-line me-1"></i>Danger Zone
                                </h6>
                                <p class="text-muted small mb-0">Move this post to trash.</p>
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

@if (isset($post) && !empty($post))
    <x-revisions-modal :modeltitle="$post->title" :revisions="$post->revisionHistory" :preview="$post->type" />

    {{-- Trash Confirm Modal --}}
    @can('delete_posts')
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
                        <p class="mb-0">Are you sure you want to move <strong>"{{ $post->title }}"</strong> to trash?</p>
                        <p class="text-muted small mb-0 mt-2">You can restore this post later from the trash.</p>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="document.getElementById('trash-post-form')?.requestSubmit();">
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

            // Check for invalid fields inside collapsed section on form submit
            form.addEventListener('submit', function(e) {
                const invalidFields = moreOptionsCollapse.querySelectorAll(':invalid');
                if (invalidFields.length > 0 && !moreOptionsCollapse.classList.contains('show')) {
                    e.preventDefault();
                    openMoreOptions();

                    // Wait for collapse animation, then re-trigger validation
                    setTimeout(() => {
                        invalidFields[0].reportValidity();
                    }, 350);
                }
            }, true);

            // Also listen for 'invalid' event on fields inside More Options
            moreOptionsCollapse.addEventListener('invalid', function(e) {
                openMoreOptions();
            }, true);
        })();

        // Status-based Publish At field visibility and date constraints
        (function() {
            const statusSelect = document.getElementById('status');
            const publishAtContainer = document.getElementById('publish-at-container');
            const publishAtInput = document.getElementById('published_at');
            const publishAtHelp = document.getElementById('publish-at-help');

            if (!statusSelect || !publishAtContainer || !publishAtInput) return;

            function updatePublishAtField() {
                const status = statusSelect.value;
                const showField = status === 'scheduled' || status === 'published';

                const hasServerError =
                    publishAtInput.classList.contains('is-invalid') ||
                    !!document.getElementById('published_at-feedback');

                if (publishAtHelp) {
                    publishAtHelp.style.display = hasServerError ? 'none' : '';
                }

                publishAtContainer.style.display = showField ? '' : 'none';

                if (!showField) {
                    publishAtInput.value = '';
                    return;
                }

                if (status === 'scheduled') {
                    if (publishAtHelp && !hasServerError) {
                        publishAtHelp.textContent = 'Select a future date and time for scheduling.';
                    }
                } else if (status === 'published') {
                    if (publishAtHelp && !hasServerError) {
                        publishAtHelp.textContent = 'Select the publish date and time (current or past).';
                    }
                }

                if (publishAtInput._flatpickr) {
                    const fp = publishAtInput._flatpickr;
                    if (status === 'scheduled') {
                        fp.set('minDate', 'today');
                        fp.set('maxDate', null);
                    } else if (status === 'published') {
                        fp.set('minDate', null);
                        fp.set('maxDate', 'today');
                    }
                }
            }

            statusSelect.addEventListener('change', updatePublishAtField);
            updatePublishAtField();
        })();

        // Visibility-based Password Protection fields visibility
        (function() {
            const visibilitySelect = document.getElementById('visibility');
            const passwordContainer = document.getElementById('password-protection-container');
            const passwordHintContainer = document.getElementById('password-hint-container');
            const passwordInput = document.getElementById('post_password');
            const moreOptionsCollapse = document.getElementById('more-options-collapse');

            if (!visibilitySelect || !passwordContainer || !passwordHintContainer) return;

            function updatePasswordFields() {
                const visibility = visibilitySelect.value;
                const showFields = visibility === 'password';

                passwordContainer.style.display = showFields ? '' : 'none';
                passwordHintContainer.style.display = showFields ? '' : 'none';

                // Auto-expand "More Options" when password visibility is selected
                if (showFields && moreOptionsCollapse && !moreOptionsCollapse.classList.contains('show')) {
                    try {
                        if (window.bootstrap?.Collapse) {
                            const collapse = bootstrap.Collapse.getOrCreateInstance(moreOptionsCollapse, { toggle: false });
                            collapse.show();
                        }
                    } catch (e) {
                        moreOptionsCollapse.classList.add('show');
                    }
                }

                if (!showFields && passwordInput) {
                    passwordInput.value = '';
                }
            }

            visibilitySelect.addEventListener('change', updatePasswordFields);
            updatePasswordFields();
        })();
    </script>
@endpush

@push('styles')
<style>
    /* Rotate collapse icon */
    .collapse-icon {
        transition: transform 0.2s ease;
    }
    [aria-expanded="true"] .collapse-icon {
        transform: rotate(180deg);
    }
</style>
@endpush
