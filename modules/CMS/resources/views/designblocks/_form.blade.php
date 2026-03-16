@php
    $isEdit = isset($designBlock) && $designBlock?->exists;
    $previewImageValue = old('preview_image_url', $designBlock->preview_image_url ?? '');
    $previewImageResolvedUrl = null;
    if (! empty($previewImageValue)) {
        if (str_starts_with((string) $previewImageValue, 'http')) {
            $previewImageResolvedUrl = (string) $previewImageValue;
        } elseif (function_exists('get_media_url')) {
            $previewImageResolvedUrl = get_media_url($previewImageValue);
        } else {
            $previewImageResolvedUrl = asset('storage/'.ltrim((string) $previewImageValue, '/'));
        }
    }
@endphp

{{-- Validation Alert --}}
<x-client-validation-alert />

<div class="row">
    {{-- Main Content --}}
    <div class="col-md-8 mb-3">
        {{-- Basic Information Card --}}
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="card-title mb-0">
                    <i class="ri-information-line me-1"></i> Basic Information
                </h6>
            </div>
            <div class="card-body py-3">
                {{-- Title --}}
                <div class="mb-0">
                    <x-form-elements.input
                        class="form-group"
                        id="title"
                        name="title"
                        type="text"
                        value="{{ old('title', $designBlock->title ?? '') }}"
                        label="Title"
                        labelclass="form-label"
                        placeholder="Enter design block title"
                        :extraAttributes="['required' => 'required']" />
                </div>
            </div>
        </div>

        {{-- HTML Content Card --}}
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="card-title mb-0">
                    <i class="ri-html5-line me-1"></i> HTML Content
                </h6>
            </div>
            <div class="card-body py-3">
                <x-textarea-monaco language="html" :height="350">
                    <textarea
                        class="form-control @error('html') is-invalid @enderror"
                        id="html"
                        name="html"
                        placeholder="Enter HTML markup"
                        rows="18">{{ old('html', $designBlock->html ?? '') }}</textarea>
                </x-textarea-monaco>
                @error('html')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="col-md-4 mb-3">
        {{-- Classification Card --}}
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="card-title mb-0">
                    <i class="ri-settings-3-line me-1"></i> Classification
                </h6>
            </div>
            <div class="card-body py-3">
                {{-- Design Type --}}
                <input type="hidden" name="block_type" value="static">
                <div class="mb-3">
                        <x-form-elements.select
                            class="form-group"
                            id="design_type"
                            name="design_type"
                            value="{{ old('design_type', $designBlock->design_type ?? $defaults['design_type'] ?? 'section') }}"
                            label="Design Type"
                            labelclass="form-label"
                            :options="json_encode(array_map(fn($item) => ['value' => $item['value'], 'label' => $item['label']], config('cms.design_types')))"
                            required="true" />
                </div>

                {{-- Category --}}
                <div class="mb-3">
                    <x-form-elements.select
                        class="form-group"
                        id="category_id"
                        name="category_id"
                        value="{{ old('category_id', $designBlock->category_id ?? $defaults['category_id'] ?? 'hero') }}"
                        label="Category"
                        labelclass="form-label"
                        :options="json_encode(array_map(fn($item) => ['value' => $item['value'], 'label' => $item['label']], config('cms.categories')))"
                        required="true" />
                </div>

                {{-- Design System --}}
                <div class="mb-0">
                    <x-form-elements.select
                        class="form-group"
                        id="design_system"
                        name="design_system"
                        value="{{ old('design_system', $designBlock->design_system ?? $defaults['design_system'] ?? 'bootstrap') }}"
                        label="Design System"
                        labelclass="form-label"
                        :options="json_encode(array_map(fn($item) => ['value' => $item['value'], 'label' => $item['label']], config('cms.design_systems')))"
                        required="true" />
                </div>
            </div>
        </div>

        {{-- Preview Image --}}
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="card-title mb-0">
                    <i class="ri-image-line me-1"></i> Preview Image
                </h6>
            </div>
            <div class="card-body py-3">
                <x-media-picker.image-field
                    class="form-group"
                    id="preview_image_url"
                    name="preview_image_url"
                    :value="$previewImageValue"
                    :valueUrl="$previewImageResolvedUrl"
                    :previewUrl="$previewImageResolvedUrl"
                    label=""
                    labelClass="form-label" />
            </div>
        </div>

        {{-- Hidden Status Field --}}
        <input id="status" name="status" type="hidden" value="{{ old('status', $designBlock->status ?? 'draft') }}">

        {{-- Submit Buttons --}}
        <div class="d-flex gap-2 mb-3">
            <button class="btn btn-outline-primary flex-grow-1" type="submit" name="status" value="draft" id="save-draft-btn">
                <i class="ri-file-line me-1"></i>
                <span class="btn-text">Save Draft</span>
            </button>
            <button class="btn btn-primary flex-grow-1" type="submit" name="status" value="published" id="publish-btn">
                <i class="ri-global-line me-1"></i>
                <span class="btn-text">{{ $isEdit && ($designBlock->status ?? '') == 'published' ? 'Update' : 'Publish' }}</span>
            </button>
        </div>

        {{-- Status Badge (for edit) --}}
        @if ($isEdit)
            <div class="text-center mb-3">
                <span class="text-muted small">Current Status:</span> {!! $designBlock->status_badge !!}
            </div>
        @endif

        {{-- Danger Zone Card (only on edit) --}}
        @if ($isEdit)
            @can('delete_design_blocks')
                <div class="card border-danger">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-danger mb-1">
                                    <i class="ri-error-warning-line me-1"></i>Danger Zone
                                </h6>
                                <p class="text-muted small mb-0">Delete this design block.</p>
                            </div>
                            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#delete-confirm-modal">
                                <i class="ri-delete-bin-line me-1"></i>Delete
                            </button>
                        </div>
                    </div>
                </div>
            @endcan
        @endif
    </div>
</div>

{{-- Delete Confirm Modal --}}
@if ($isEdit)
    @can('delete_design_blocks')
        <div class="modal fade" id="delete-confirm-modal" tabindex="-1" aria-labelledby="delete-confirm-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title d-flex align-items-center gap-2" id="delete-confirm-modal-label">
                            <i class="ri-delete-bin-line text-danger" style="font-size: 1.5rem;"></i>
                            Delete Design Block
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Are you sure you want to delete <strong>"{{ $designBlock->title }}"</strong>?</p>
                        <p class="text-muted small mb-0 mt-2">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="document.getElementById('delete-design-block-form')?.requestSubmit();">
                            <i class="ri-delete-bin-line me-1"></i>Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endif

@push('scripts')
    <script data-up-execute>
        // Prevent form submission when pressing Enter inside Monaco editor
        (function() {
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.target.closest('.monaco-editor')) {
                    e.stopPropagation();
                }
            }, true);
        })();
    </script>
@endpush

