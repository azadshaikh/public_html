<x-customizer-layout :title="__('Theme Customizer')" >

    <x-slot:headerLeftActions>
        <button class="btn btn-sm btn-outline-secondary d-none d-lg-inline-flex" id="toggle-sidebar-desktop-btn" type="button" title="Toggle Sidebar">
            <i class="ri-sidebar-fold-line"></i>
        </button>
    </x-slot:headerLeftActions>

    <x-slot:headerActions>
        <!-- Refresh - always visible -->
        <button class="btn btn-sm btn-outline-secondary" id="refresh-preview" type="button" title="Refresh Preview">
            <i class="ri-refresh-line"></i>
        </button>

        <!-- Mobile Settings Button -->
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="show-sidebar-btn-header" type="button" title="Settings">
            <i class="ri-settings-3-line"></i>
        </button>

        <!-- Device Preview - hidden on mobile -->
        <div class="btn-group d-none d-md-inline-flex" role="group">
            <button class="btn btn-sm btn-outline-secondary device-preview active"
                data-device="desktop" type="button" title="Desktop View">
                <i class="ri-computer-line"></i>
            </button>
            <button class="btn btn-sm btn-outline-secondary device-preview"
                data-device="tablet" type="button" title="Tablet View">
                <i class="ri-tablet-line"></i>
            </button>
            <button class="btn btn-sm btn-outline-secondary device-preview"
                data-device="mobile" type="button" title="Mobile View">
                <i class="ri-smartphone-line"></i>
            </button>
        </div>

        <!-- More Actions Dropdown - Mobile -->
        <div class="dropdown d-md-none">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="ri-more-2-fill"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><button class="dropdown-item" id="reset-btn-mobile" type="button"><i class="ri-arrow-go-back-line me-2"></i>Reset</button></li>
            </ul>
        </div>

        <!-- Desktop Actions -->
        <div class="vr mx-1 d-none d-md-block"></div>
        <button class="btn btn-sm btn-outline-secondary d-none d-md-inline-flex" id="reset-btn" type="button">
            <i class="ri-arrow-go-back-line"></i>
            <span class="ms-1">Reset</span>
        </button>

        <!-- Save Button - always visible -->
        <button class="btn btn-sm btn-primary" id="save-btn" type="button">
            <i class="ri-save-line"></i>
            <span class="d-none d-sm-inline ms-1">Save</span>
        </button>
    </x-slot:headerActions>

    <div class="theme-customizer" id="theme-customizer">
        <div class="customizer-content">
            <div class="customizer-flex-container">
                <!-- Left Panel: Customizer Settings -->
                <div class="customizer-panel" id="customizer-sidebar">
                    <div class="panel-header d-lg-none">
                        <h6 class="mb-0 fw-semibold">Theme Settings: <small class="text-body-secondary">{{ $activeTheme['name'] ?? 'Current Theme' }}</small></h6>
                        <button class="btn btn-sm btn-outline-secondary" id="toggle-sidebar-btn" type="button">
                            <i class="ri-close-line"></i> Close
                        </button>
                    </div>

                    <!-- Theme Info Header (hidden on mobile - shown in panel-header instead) -->
                    <div class="theme-info-header d-none d-lg-block">
                        <h6 class="mb-0 fw-semibold">Theme Settings: <small class="text-body-secondary">{{ $activeTheme['name'] ?? 'Current Theme' }}</small></h6>
                    </div>

                    <div class="panel-inner">
                        <form id="customizer-form">
                            @csrf
                            <div class="accordion" id="customizerAccordion">
                                @foreach ($settings['sections'] ?? [] as $sectionId => $section)
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading-{{ $sectionId }}">
                                            <button
                                                class="accordion-button {{ $loop->first ? '' : 'collapsed' }}"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#section-{{ $sectionId }}"
                                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                                aria-controls="section-{{ $sectionId }}">
                                                <div class="d-flex flex-column align-items-start">
                                                    <span class="fw-semibold">{{ $section['title'] }}</span>
                                                    @if (isset($section['helper_text']) || isset($section['description']))
                                                        <small class="text-body-secondary mt-1">{{ $section['helper_text'] ?? $section['description'] }}</small>
                                                    @endif
                                                </div>
                                            </button>
                                        </h2>
                                        <div class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                                            id="section-{{ $sectionId }}"
                                            aria-labelledby="heading-{{ $sectionId }}"
                                            data-bs-parent="#customizerAccordion">
                                            <div class="accordion-body">
                                                @foreach ($section['settings'] ?? [] as $settingId => $setting)
                                                    <div class="mb-3">
                                                        <label class="form-label fw-medium" for="{{ $settingId }}">
                                                            {{ $setting['label'] }}
                                                        </label>

                                                        @if ($setting['type'] === 'color')
                                                            <input
                                                                class="form-control form-control-color w-100 customizer-input"
                                                                id="{{ $settingId }}"
                                                                name="{{ $settingId }}"
                                                                type="color"
                                                                value="{{ $currentValues[$settingId] ?? ($setting['default'] ?? '#000000') }}">

                                                        @elseif ($setting['type'] === 'select')
                                                            <select
                                                                class="form-select customizer-input"
                                                                id="{{ $settingId }}"
                                                                name="{{ $settingId }}">
                                                                @foreach ($setting['options'] ?? [] as $optionValue => $optionLabel)
                                                                    <option value="{{ $optionValue }}"
                                                                        {{ ($currentValues[$settingId] ?? ($setting['default'] ?? '')) == $optionValue ? 'selected' : '' }}>
                                                                        {{ $optionLabel }}
                                                                    </option>
                                                                @endforeach
                                                            </select>

                                                        @elseif ($setting['type'] === 'textarea')
                                                            <textarea
                                                                class="form-control customizer-input"
                                                                id="{{ $settingId }}"
                                                                name="{{ $settingId }}"
                                                                rows="{{ $setting['rows'] ?? 3 }}"
                                                                placeholder="{{ $setting['placeholder'] ?? '' }}">{{ $currentValues[$settingId] ?? ($setting['default'] ?? '') }}</textarea>

                                                        @elseif ($setting['type'] === 'checkbox')
                                                            <div class="form-check form-switch">
                                                                <input type="hidden" name="{{ $settingId }}" value="false">
                                                                <input
                                                                    class="form-check-input customizer-input"
                                                                    id="{{ $settingId }}"
                                                                    name="{{ $settingId }}"
                                                                    type="checkbox"
                                                                    value="true"
                                                                    {{ ($currentValues[$settingId] ?? ($setting['default'] ?? false)) ? 'checked' : '' }}>
                                                                <label class="form-check-label" for="{{ $settingId }}">
                                                                    Enable
                                                                </label>
                                                            </div>

                                                        @elseif ($setting['type'] === 'image')
                                                            <div class="image-upload-wrapper">
                                                                <input
                                                                    class="customizer-input"
                                                                    id="{{ $settingId }}"
                                                                    name="{{ $settingId }}"
                                                                    type="hidden"
                                                                    value="{{ $currentValues[$settingId] ?? ($setting['default'] ?? '') }}">
                                                                <div class="image-preview border rounded p-3 bg-body-secondary text-center cursor-pointer"
                                                                    id="preview-{{ $settingId }}"
                                                                    onclick="selectImage('{{ $settingId }}')"
                                                                    style="cursor: pointer;"
                                                                    title="Click to select image">
                                                                    @if ($currentValues[$settingId] ?? ($setting['default'] ?? ''))
                                                                        <img src="{{ $currentValues[$settingId] ?? $setting['default'] }}"
                                                                            alt="Preview"
                                                                            class="img-fluid rounded"
                                                                            style="max-height: 150px;">
                                                                    @else
                                                                        <div class="text-body-secondary py-3">
                                                                            <i class="ri-image-add-line" style="font-size: 2.5rem;"></i>
                                                                            <p class="mb-0 mt-2 small">Click to select image</p>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                                <div class="d-flex gap-2 mt-2">
                                                                    <button
                                                                        class="btn btn-outline-primary btn-sm flex-fill"
                                                                        type="button"
                                                                        onclick="selectImage('{{ $settingId }}')">
                                                                        <i class="ri-upload-line me-1"></i> Choose Image
                                                                    </button>
                                                                    <button
                                                                        class="btn btn-outline-danger btn-sm"
                                                                        type="button"
                                                                        id="remove-{{ $settingId }}"
                                                                        onclick="removeImage('{{ $settingId }}')"
                                                                        style="{{ ($currentValues[$settingId] ?? ($setting['default'] ?? '')) ? '' : 'display: none;' }}">
                                                                        <i class="ri-delete-bin-line"></i>
                                                                    </button>
                                                                </div>
                                                            </div>

                                                        @elseif ($setting['type'] === 'code_editor')
                                                            @php
                                                                // Decode base64 stored value for the hidden input
                                                                $storedValue = $currentValues[$settingId] ?? ($setting['default'] ?? '');
                                                                $decodedValue = '';
                                                                if (!empty($storedValue)) {
                                                                    $decoded = base64_decode($storedValue, true);
                                                                    $decodedValue = ($decoded !== false) ? $decoded : $storedValue;
                                                                }
                                                            @endphp
                                                            <div class="code-editor-wrapper">
                                                                <input
                                                                    class="customizer-input"
                                                                    id="{{ $settingId }}"
                                                                    name="{{ $settingId }}"
                                                                    type="hidden"
                                                                    value="{{ $decodedValue }}">
                                                                <button
                                                                    class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-between"
                                                                    type="button"
                                                                    onclick="openCodeEditorModal(@js($settingId), @js($setting['label']), @js($setting['language'] ?? 'plaintext'), document.getElementById(@js($settingId)).value)">
                                                                    <span>
                                                                        <i class="ri-code-line me-2"></i>
                                                                        @if ($setting['language'] === 'css')
                                                                            Edit CSS
                                                                        @elseif ($setting['language'] === 'javascript')
                                                                            Edit JavaScript
                                                                        @else
                                                                            Edit Code
                                                                        @endif
                                                                    </span>
                                                                    <span class="badge bg-secondary-subtle text-secondary" id="badge-{{ $settingId }}">
                                                                        {{ strlen($decodedValue) > 0 ? 'Has code' : 'Empty' }}
                                                                    </span>
                                                                </button>
                                                            </div>

                                                        @else
                                                            <input
                                                                class="form-control customizer-input"
                                                                id="{{ $settingId }}"
                                                                name="{{ $settingId }}"
                                                                type="{{ $setting['type'] ?? 'text' }}"
                                                                value="{{ $currentValues[$settingId] ?? ($setting['default'] ?? '') }}"
                                                                placeholder="{{ $setting['placeholder'] ?? '' }}">
                                                        @endif

                                                        @if (isset($setting['helper_text']) || isset($setting['description']))
                                                            <div class="form-text">{{ $setting['helper_text'] ?? $setting['description'] }}</div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                {{-- Built-in Custom Code Section (Platform Feature) --}}
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading-custom-code">
                                        <button
                                            class="accordion-button collapsed"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#section-custom-code"
                                            aria-expanded="false"
                                            aria-controls="section-custom-code">
                                            <div class="d-flex flex-column align-items-start">
                                                <span class="fw-semibold">Custom Code</span>
                                                <small class="text-body-secondary mt-1">Add custom CSS and JavaScript code</small>
                                            </div>
                                        </button>
                                    </h2>
                                    <div class="accordion-collapse collapse"
                                        id="section-custom-code"
                                        aria-labelledby="heading-custom-code"
                                        data-bs-parent="#customizerAccordion">
                                        <div class="accordion-body">
                                            {{-- Custom CSS --}}
                                            <div class="mb-3">
                                                <label class="form-label fw-medium" for="custom_css">
                                                    Custom CSS
                                                </label>
                                                @php
                                                    $customCssStored = $currentValues['custom_css'] ?? '';
                                                    $customCssDecoded = '';
                                                    if (!empty($customCssStored)) {
                                                        $decoded = base64_decode($customCssStored, true);
                                                        $customCssDecoded = ($decoded !== false) ? $decoded : $customCssStored;
                                                    }
                                                @endphp
                                                <div class="code-editor-wrapper">
                                                    <input
                                                        class="customizer-input"
                                                        id="custom_css"
                                                        name="custom_css"
                                                        type="hidden"
                                                        value="{{ $customCssDecoded }}">
                                                    <button
                                                        class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-between"
                                                        type="button"
                                                        onclick="openCodeEditorModal(@js('custom_css'), @js('Custom CSS'), @js('css'), document.getElementById(@js('custom_css')).value)">
                                                        <span>
                                                            <i class="ri-code-line me-2"></i>
                                                            Edit CSS
                                                        </span>
                                                        <span class="badge bg-secondary-subtle text-secondary" id="badge-custom_css">
                                                            {{ strlen($customCssDecoded) > 0 ? 'Has code' : 'Empty' }}
                                                        </span>
                                                    </button>
                                                </div>
                                                <div class="form-text">Add custom CSS styles. Injected in &lt;head&gt;.</div>
                                            </div>

                                            {{-- Custom JavaScript --}}
                                            <div class="mb-3">
                                                <label class="form-label fw-medium" for="custom_js">
                                                    Custom JavaScript
                                                </label>
                                                @php
                                                    $customJsStored = $currentValues['custom_js'] ?? '';
                                                    $customJsDecoded = '';
                                                    if (!empty($customJsStored)) {
                                                        $decoded = base64_decode($customJsStored, true);
                                                        $customJsDecoded = ($decoded !== false) ? $decoded : $customJsStored;
                                                    }
                                                @endphp
                                                <div class="code-editor-wrapper">
                                                    <input
                                                        class="customizer-input"
                                                        id="custom_js"
                                                        name="custom_js"
                                                        type="hidden"
                                                        value="{{ $customJsDecoded }}">
                                                    <button
                                                        class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-between"
                                                        type="button"
                                                        onclick="openCodeEditorModal(@js('custom_js'), @js('Custom JavaScript'), @js('javascript'), document.getElementById(@js('custom_js')).value)">
                                                        <span>
                                                            <i class="ri-code-line me-2"></i>
                                                            Edit JavaScript
                                                        </span>
                                                        <span class="badge bg-secondary-subtle text-secondary" id="badge-custom_js">
                                                            {{ strlen($customJsDecoded) > 0 ? 'Has code' : 'Empty' }}
                                                        </span>
                                                    </button>
                                                </div>
                                                <div class="form-text">Add custom JavaScript code. Injected before &lt;/body&gt;.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Panel: Live Preview -->
                <div class="preview-panel">
                    <div class="preview-container" id="preview-container">
                        <div class="preview-wrapper">
                            <div class="preview-device-frame">
                                <iframe id="preview-iframe"
                                    src="{{ $previewUrl }}?customizer_preview=1"
                                    frameborder="0"></iframe>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <!-- Media Picker Modal -->
    <x-media-picker.media-modal />

    <!-- Code Editor Modal -->
    <x-code-editor-modal />

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">
                        <i class="ri-upload-line me-2"></i>Import Theme Settings
                    </h5>
                    <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="import-form" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="settings_file">Settings File <span class="text-danger">*</span></label>
                            <input class="form-control" id="settings_file" name="settings_file" type="file"
                                accept=".json" required>
                            <div class="form-text">Upload a JSON file containing theme settings.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-primary" id="import-confirm-btn" type="button">
                        <i class="ri-check-line me-1"></i>Import Settings
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
        <style>
            .theme-customizer {
                height: 100%;
                width: 100%;
                display: flex;
                flex-direction: column;
            }

            .customizer-content {
                height: 100%;
                flex: 1;
                display: flex;
                flex-direction: column;
            }

            .customizer-flex-container {
                --sidebar-width: 350px;
                display: grid;
                grid-template-columns: minmax(0, var(--sidebar-width)) minmax(0, 1fr);
                height: 100%;
                width: 100%;
                transition: grid-template-columns 0.3s ease;
            }

            .customizer-flex-container.sidebar-collapsed {
                --sidebar-width: 0px;
            }

            /* Left Panel */
            .customizer-panel {
                background: var(--bs-secondary-bg);
                border-right: 1px solid var(--bs-border-color);
                height: 100%;
                min-width: 0;
                overflow-y: auto;
                overflow-x: hidden;
                transition: transform 0.3s ease, opacity 0.2s ease;
                will-change: transform;
            }

            @media (min-width: 992px) {
                .customizer-panel.collapsed {
                    transform: translateX(-100%);
                    opacity: 0;
                    pointer-events: none;
                    border-right: none;
                }
            }

            /* Toggle button icon rotation */
            #toggle-sidebar-desktop-btn i {
                transition: transform 0.3s ease;
            }

            #toggle-sidebar-desktop-btn.collapsed i {
                transform: rotate(180deg);
            }

            .panel-header {
                padding: 0.75rem 1rem;
                background: var(--bs-body-bg);
                border-bottom: 1px solid var(--bs-border-color);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.5rem;
            }

            .panel-header h6 {
                font-size: 0.9rem;
            }

            /* Theme Info Header */
            .theme-info-header {
                padding: 0.75rem 1.5rem;
            }

            .theme-info-header h6 {
                font-size: 0.9rem;
            }

            .panel-inner {
                padding: 0.25rem;
            }

            .accordion-item {
                background: var(--bs-body-bg);
                border: none;
                margin-bottom: 0.5rem;
                border-radius: 8px !important;
                overflow: hidden;
                box-shadow: 0 1px 3px var(--bs-border-color-translucent);
            }

            .accordion-button {
                background: var(--bs-body-bg);
                padding: 1rem 1.25rem;
                font-size: 0.95rem;
            }

            .accordion-button:not(.collapsed) {
                background: var(--bs-secondary-bg);
                color: inherit;
                box-shadow: none;
            }

            .accordion-button:focus {
                box-shadow: none;
                border-color: transparent;
            }

            .accordion-body {
                padding: 1.25rem;
            }

            /* Form Controls */
            .form-control-color {
                height: 45px;
                padding: 0.25rem;
            }

            .form-check-input:checked {
                background-color: #0d6efd;
                border-color: #0d6efd;
            }

            .image-upload-wrapper .image-preview {
                min-height: 120px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            /* Right Panel */
            .preview-panel {
                background: var(--bs-tertiary-bg);
                height: 100%;
                flex: 1;
                display: flex;
                flex-direction: column;
                min-width: 0; /* Allow flex item to shrink below content size */
                position: relative;
            }

            .mobile-settings-btn {
                position: absolute;
                top: 1rem;
                left: 1rem;
                z-index: 10;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
                background: var(--bs-body-bg);
            }

            .mobile-settings-btn:hover {
                background: var(--bs-secondary-bg);
            }

            .preview-container {
                flex: 1;
                position: relative;
                overflow: auto;
                background: var(--bs-tertiary-bg);
                display: flex;
                align-items: stretch;
                justify-content: stretch;
                padding: 0.5rem;
            }

            .preview-wrapper {
                width: 100%;
                height: 100%;
                position: relative;
            }

            .preview-wrapper iframe {
                width: 100%;
                height: 100%;
                border: none;
                background: var(--bs-body-bg);
                display: block;
            }

            .preview-device-frame {
                width: 100%;
                height: 100%;
                padding: 1rem;
                border: 1px solid var(--bs-border-color);
                border-radius: 24px;
                background: var(--bs-body-bg);
                box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
                box-sizing: border-box;
                display: flex;
            }

            .preview-device-frame iframe {
                width: 100%;
                height: 100%;
                border-radius: 16px;
                background: var(--bs-body-bg);
                box-shadow: inset 0 0 0 1px var(--bs-border-color-translucent);
            }

            /* Device Preview Modes */
            .preview-container.tablet-view {
                padding: 2rem;
                justify-content: center;
                align-items: flex-start;
            }

            .preview-container.tablet-view .preview-wrapper {
                margin: 0 auto;
            }

            .preview-container.tablet-view .preview-device-frame {
                padding: 1.25rem;
                border-radius: 28px;
            }

            .preview-container.tablet-view .preview-device-frame iframe {
                border-radius: 18px;
            }

            .preview-container.mobile-view {
                padding: 2rem;
                justify-content: center;
                align-items: flex-start;
            }

            .preview-container.mobile-view .preview-wrapper {
                margin: 0 auto;
            }

            .preview-container.mobile-view .preview-device-frame {
                padding: 0.85rem;
                border-radius: 32px;
            }

            .preview-container.mobile-view .preview-device-frame iframe {
                border-radius: 24px;
            }

            .device-preview.active {
                background: var(--bs-primary);
                color: var(--bs-white);
                border-color: var(--bs-primary);
            }

            /* Loading State */
            .preview-container.loading::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 40px;
                height: 40px;
                margin: -20px 0 0 -20px;
                border: 4px solid var(--bs-border-color);
                border-top: 4px solid var(--bs-primary);
                border-radius: 50%;
                animation: spin 1s linear infinite;
                z-index: 10;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            /* Responsive */
            @media (max-width: 991px) {
                .theme-customizer {
                    height: 100%;
                    min-height: 100%;
                }

                .customizer-flex-container {
                    display: flex;
                    flex-direction: column;
                    height: 100%;
                }

                .customizer-panel {
                    transform: none !important;
                    opacity: 1 !important;
                    pointer-events: auto !important;
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 90%;
                    max-width: 400px;
                    height: 100vh;
                    flex: 0 0 auto;
                    z-index: 1050;
                    margin-left: -100%;
                    border-right: none;
                    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
                    min-width: unset;
                    transition: margin-left 0.3s ease;
                }

                .customizer-panel.show {
                    margin-left: 0;
                }

                .preview-panel {
                    flex: 1;
                    height: 100%;
                    min-height: 0;
                }

                /* Full-width preview on mobile - no device frame */
                .preview-container {
                    padding: 0;
                }

                .preview-wrapper {
                    border-radius: 0;
                }

                .preview-device-frame {
                    border: none;
                    border-radius: 0;
                    padding: 0;
                    box-shadow: none;
                }

                .preview-device-frame iframe {
                    border-radius: 0;
                }
            }

            @media (max-width: 576px) {
                .preview-controls {
                    flex-wrap: wrap;
                }

                .panel-inner {
                    padding: 1rem;
                }

                .customizer-panel {
                    width: 100%;
                    max-width: 100%;
                }
            }

            /* Sidebar overlay for mobile */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(2px);
                z-index: 1040;
            }

            .sidebar-overlay.show {
                display: block;
            }

            @media (min-width: 992px) {
                .panel-header {
                    display: none;
                }
            }
        </style>
    @endpush

    <script data-up-execute>
        // Set up routes for JavaScript
        window.customizerRoutes = {
            update: '{{ route('cms.appearance.themes.customizer.update') }}',
            previewCss: '{{ route('cms.appearance.themes.customizer.preview-css') }}',
            reset: '{{ route('cms.appearance.themes.customizer.reset') }}',
            export: '{{ route('cms.appearance.themes.customizer.export') }}',
            import: '{{ route('cms.appearance.themes.customizer.import') }}'
        };
        window.customizerAuth = {
            loginUrl: '{{ route('login') }}'
        };

        // Inject customizer_preview parameter into all iframe navigation.
        // This script can run multiple times under Unpoly; guard against duplicate listeners.
        if (!window.__customizerPreviewInterceptorSetup) {
            window.__customizerPreviewInterceptorSetup = true;

            const iframe = document.getElementById('preview-iframe');

            if (iframe) {
                iframe.addEventListener('load', function() {
                    try {
                        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

                        iframeDoc.addEventListener('click', function(e) {
                            const target = e.target.closest('a');
                            if (target && target.href) {
                                const url = new URL(target.href);
                                if (url.hostname === window.location.hostname) {
                                    url.searchParams.set('customizer_preview', '1');
                                    target.href = url.toString();
                                }
                            }
                        });

                        iframeDoc.addEventListener('submit', function(e) {
                            const form = e.target;
                            if (form && form.action) {
                                const url = new URL(form.action);
                                if (url.hostname === window.location.hostname) {
                                    url.searchParams.set('customizer_preview', '1');
                                    form.action = url.toString();
                                }
                            }
                        });
                    } catch {
                        // Cross-origin iframe - can't access content
                        console.log('Cannot intercept iframe navigation (cross-origin)');
                    }
                });
            }
        }
    </script>
    @vite('modules/CMS/resources/js/theme-customizer.js')
</x-customizer-layout>
