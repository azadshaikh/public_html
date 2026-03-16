<div class="row">
    {{-- Basic Information --}}
    <div class="col-12">
        <div class="card border-0 bg-light">
            <div class="card-header bg-transparent border-0 py-3">
                <h5>
                    <i class="ri-information-line me-2"></i>Basic Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <div class="mb-3">
                            <label for="title" class="form-label">Form Title <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control @error('title') is-invalid @enderror"
                                   id="title"
                                   name="title"
                                   value="{{ old('title', $form->title ?? '') }}"
                                   required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                @if(isset($form) && $form->shortcode)
                    {{-- Form Shortcode Display --}}
                    <div class="mb-3">
                        <label class="form-label">Form Shortcode</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="[form id=&quot;{{ $form->shortcode }}&quot;]" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard(this.previousElementSibling)">
                                <i class="ri-clipboard-line"></i> Copy
                            </button>
                        </div>
                        <div class="form-text">Use this shortcode to display the form on your pages.</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Form Content --}}
    <div class="col-12">
        <div class="card border-0 bg-light mt-3">
            <div class="card-header bg-transparent border-0 py-3">
                <h5>
                    <i class="ri-code-line-square me-2"></i>Form Content
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="html" class="form-label">Form HTML <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('html') is-invalid @enderror"
                              id="html"
                              name="html"
                              rows="15"
                              required>{{ old('html', isset($form) ? $form->html : '') }}</textarea>
                    <div class="form-text">Enter the HTML code for your form. Use standard HTML form elements.</div>
                    @error('html')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="css" class="form-label">Custom CSS</label>
                    <textarea class="form-control @error('css') is-invalid @enderror"
                              id="css"
                              name="css"
                              rows="10">{{ old('css', isset($form) ? $form->css : '') }}</textarea>
                    <div class="form-text">Optional: Add custom CSS styles for your form.</div>
                    @error('css')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Form Configuration --}}
    <div class="col-12">
        <div class="card border-0 bg-light mt-3">
            <div class="card-header bg-transparent border-0 py-3">
                <h5>
                    <i class="ri-settings-3-line me-2"></i>Form Configuration
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="store_in_database" class="form-label">Submission Storage</label>
                            <select class="form-select @error('store_in_database') is-invalid @enderror"
                                    id="store_in_database"
                                    name="store_in_database">
                                <option value="true" {{ old('store_in_database', $form->store_in_database ?? true) ? 'selected' : '' }}>
                                    Yes, store in database
                                </option>
                                <option value="false" {{ !old('store_in_database', $form->store_in_database ?? true) ? 'selected' : '' }}>
                                    No, do not store
                                </option>
                            </select>
                            @error('store_in_database')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Confirmation Settings --}}
                <div class="mb-3">
                    <label class="form-label">Confirmation Settings</label>
                    <div class="row">
                        <div class="col-md-6">
                            <select class="form-select @error('confirmation_type') is-invalid @enderror"
                                    id="confirmation_type"
                                    name="confirmation_type">
                                <option value="">Select Confirmation Type</option>
                                <option value="message" {{ old('confirmation_type', $form->confirmations['type'] ?? '') == 'message' ? 'selected' : '' }}>
                                    Show Message
                                </option>
                                <option value="redirect" {{ old('confirmation_type', $form->confirmations['type'] ?? '') == 'redirect' ? 'selected' : '' }}>
                                    Redirect to URL
                                </option>
                            </select>
                            @error('confirmation_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6" id="confirmation-message-field" style="display: none;">
                            <input type="text"
                                   class="form-control @error('confirmation_message') is-invalid @enderror"
                                   id="confirmation_message"
                                   name="confirmation_message"
                                   placeholder="Success message"
                                   value="{{ old('confirmation_message', $form->confirmations['message'] ?? '') }}">
                            @error('confirmation_message')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6" id="confirmation-redirect-field" style="display: none;">
                            <input type="url"
                                   class="form-control @error('redirect_url') is-invalid @enderror"
                                   id="redirect_url"
                                   name="redirect_url"
                                   placeholder="https://example.com/thank-you"
                                   value="{{ old('redirect_url', $form->confirmations['redirect'] ?? '') }}">
                            @error('redirect_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Form Actions --}}
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between gap-2">
                    @php
                        $isEdit = isset($form) && $form->exists;
                    @endphp

                    @if ($isEdit)
                        {{-- Status Info (for edit form) --}}
                        <div class="d-flex align-items-center">
                            <span class="fw-semibold fs-6 text-gray-700">Status:</span> {!! $form->status_badge !!}
                        </div>
                    @else
                        <div></div>
                    @endif

                    {{-- Hidden Status Field --}}
                    <input id="status" name="status" type="hidden"
                        value="{{ old('status', $form->status ?? 'draft') }}">

                    {{-- Action Buttons --}}
                    <div class="d-flex gap-2">
                        @if ($isEdit)
                            @if ($form->status == 'published')
                                <button class="btn btn-outline-primary" type="submit" name="status" value="draft" id="save-draft-btn">
                                    <i class="ri-file-line me-2"></i>
                                    <span class="btn-text">Save as Draft</span>
                                </button>
                                <button class="btn btn-primary" type="submit" name="status" value="published" id="update-btn">
                                    <i class="ri-checkbox-circle-line me-2"></i>
                                    <span class="btn-text">Update</span>
                                </button>
                            @else
                                <button class="btn btn-outline-primary" type="submit" name="status" value="draft" id="save-draft-btn">
                                    <i class="ri-file-line me-2"></i>
                                    <span class="btn-text">Save as Draft</span>
                                </button>
                                <button class="btn btn-primary" type="submit" name="status" value="published" id="publish-btn">
                                    <i class="ri-global-line me-2"></i>
                                    <span class="btn-text">Publish</span>
                                </button>
                            @endif
                        @else
                            <button class="btn btn-outline-primary" type="submit" name="status" value="draft" id="save-draft-btn">
                                <i class="ri-file-line me-2"></i>
                                <span class="btn-text">Save as Draft</span>
                            </button>
                            <button class="btn btn-primary" type="submit" name="status" value="published" id="publish-btn">
                                <i class="ri-global-line me-2"></i>
                                <span class="btn-text">Publish</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
