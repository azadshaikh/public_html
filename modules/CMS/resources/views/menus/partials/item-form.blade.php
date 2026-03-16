{{--
    Shared Menu Item Form Partial
    Used by both Add and Edit modals for consistency

    Variables:
    - $prefix: Form field prefix ('add' or 'edit')
    - $itemTypes: Array of available item types
    - $itemTargets: Array of available link targets
    - $pages: Collection of available pages
    - $isEdit: Boolean indicating if this is an edit form (optional, default false)
--}}

@php
    $prefix = $prefix ?? 'edit';
    $isEdit = $isEdit ?? false;
@endphp

<input type="hidden" id="{{ $prefix }}-item-id" name="item_id">

{{-- Hidden Type Field - Type is set programmatically --}}
<input type="hidden" id="{{ $prefix }}-type" name="type" value="custom">
<input type="hidden" id="{{ $prefix }}-object-id" name="object_id">

<div class="accordion" id="{{ $prefix }}ItemAccordion">
    {{-- Basic Information Section --}}
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button" type="button" data-bs-toggle="collapse"
                data-bs-target="#{{ $prefix }}-collapse-basic" aria-expanded="true">
                <i class="ri-file-text-line me-2"></i> Basic Information
            </button>
        </h2>
        <div id="{{ $prefix }}-collapse-basic" class="accordion-collapse collapse show">
            <div class="accordion-body">
                <div class="mb-3">
                    <label class="form-label" for="{{ $prefix }}-title">
                        Navigation Label <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="{{ $prefix }}-title" name="title"
                        placeholder="e.g., About Us" required maxlength="255" autocomplete="off">
                    <div class="form-text">The text displayed in the navigation menu.</div>
                    <div class="invalid-feedback" id="{{ $prefix }}-title-error">
                        Navigation label is required.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="{{ $prefix }}-url">URL</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ri-link"></i></span>
                        <input type="text" class="form-control" id="{{ $prefix }}-url" name="url"
                            placeholder="https://example.com or /about" maxlength="500" autocomplete="off">
                    </div>
                    <div class="form-text">
                        Enter a full URL (https://...) or relative path (/about). Leave empty for "#".
                    </div>
                    <div class="invalid-feedback" id="{{ $prefix }}-url-error"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Appearance Section --}}
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#{{ $prefix }}-collapse-appearance" aria-expanded="false">
                <i class="ri-palette-line me-2"></i> Appearance
            </button>
        </h2>
        <div id="{{ $prefix }}-collapse-appearance" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="mb-3">
                    <label class="form-label" for="{{ $prefix }}-icon">Icon Class</label>
                    <div class="input-group">
                        <span class="input-group-text" id="{{ $prefix }}-icon-preview">
                            <i class="ri-star-line"></i>
                        </span>
                        <input type="text" class="form-control" id="{{ $prefix }}-icon" name="icon"
                            placeholder="e.g., ri-home-line" maxlength="100" autocomplete="off">
                    </div>
                    <div class="form-text">
                        Enter a <a href="https://remixicon.com/" target="_blank" rel="noopener">Remix Icon</a> class.
                    </div>
                    <div class="invalid-feedback" id="{{ $prefix }}-icon-error"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="{{ $prefix }}-css-classes">CSS Classes</label>
                    <input type="text" class="form-control" id="{{ $prefix }}-css-classes" name="css_classes"
                        placeholder="e.g., highlight featured" maxlength="255" autocomplete="off">
                    <div class="form-text">Add custom CSS classes for styling.</div>
                    <div class="invalid-feedback" id="{{ $prefix }}-css-classes-error"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Link Behavior Section --}}
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#{{ $prefix }}-collapse-link" aria-expanded="false">
                <i class="ri-external-link-line me-2"></i> Link Behavior
            </button>
        </h2>
        <div id="{{ $prefix }}-collapse-link" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="mb-3">
                    <label class="form-label" for="{{ $prefix }}-target">Open Link In</label>
                    <select class="form-select" id="{{ $prefix }}-target" name="target">
                        @foreach ($itemTargets as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" id="{{ $prefix }}-target-error"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="{{ $prefix }}-link-title">
                        Title Attribute <span class="text-muted">(Tooltip)</span>
                    </label>
                    <input type="text" class="form-control" id="{{ $prefix }}-link-title" name="link_title"
                        placeholder="e.g., Learn more about us" maxlength="255" autocomplete="off">
                    <div class="form-text">Shows as tooltip on hover. Good for accessibility.</div>
                    <div class="invalid-feedback" id="{{ $prefix }}-link-title-error"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="{{ $prefix }}-link-rel">
                        Rel Attribute <span class="text-muted">(SEO)</span>
                    </label>
                    <input type="text" class="form-control" id="{{ $prefix }}-link-rel" name="link_rel"
                        placeholder="e.g., nofollow noopener" maxlength="100" autocomplete="off">
                    <div class="form-text">Common values: nofollow, noopener, noreferrer, sponsored</div>
                    <div class="invalid-feedback" id="{{ $prefix }}-link-rel-error"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Description Section --}}
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#{{ $prefix }}-collapse-description" aria-expanded="false">
                <i class="ri-text me-2"></i> Description
            </button>
        </h2>
        <div id="{{ $prefix }}-collapse-description" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="mb-0">
                    <label class="form-label" for="{{ $prefix }}-description">
                        Description <span class="text-muted">(Optional)</span>
                    </label>
                    <textarea class="form-control" id="{{ $prefix }}-description" name="description"
                        rows="3" placeholder="Brief description shown in some themes..." maxlength="500"></textarea>
                    <div class="d-flex justify-content-between mt-1">
                        <div class="form-text">Some themes display this as a subtitle or on hover.</div>
                        <small class="text-muted">
                            <span id="{{ $prefix }}-description-counter">0</span>/500
                        </small>
                    </div>
                    <div class="invalid-feedback" id="{{ $prefix }}-description-error"></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Status Toggle --}}
<div class="border rounded p-3 mt-3 bg-light">
    <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" id="{{ $prefix }}-is-active" name="is_active" checked>
        <label class="form-check-label" for="{{ $prefix }}-is-active">
            <strong>Visible in Menu</strong>
            <span class="d-block text-muted small">Hidden items won't appear in the navigation.</span>
        </label>
    </div>
</div>
