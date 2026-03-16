<div id="bottom-panel">
    <div>
        <div class="breadcrumb-navigator px-2 d-flex align-items-center h-100" style="--bs-breadcrumb-divider: '>';">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="#">body</a></li>
                <li class="breadcrumb-item"><a href="#">section</a></li>
                <li class="breadcrumb-item"><a href="#">img</a></li>
            </ol>
        </div>
        <div class="btn-group" role="group">
            <div class="form-check mt-1" id="toggleEditorJsExecute" style="display:none">
                <input class="form-check-input" id="runjs" name="runjs" data-astero-action="toggleEditorJsExecute"
                    type="checkbox">
                <label class="form-check-label" for="runjs"><small>Run javascript code on edit</small></label>&ensp;
            </div>
            <!-- Code Editor Button (initially visible) -->
            <button class="btn btn-sm btn-light btn-sm" id="code-editor-btn" data-astero-action="toggleEditorTabs"
                title="Code editor" style="height: 37px; font-size: 0.8rem;">
                <i class="ri-code-s-slash-fill me-1"></i> <span class="btn-text">Code Editor</span>
            </button>
        </div>
        <!-- Editor Tabs (initially hidden) -->
        <div class="justify-content-between w-100" id="editor-tabs" style="display: none;">
            <div class="btn-group position-relative" role="group">
                <button class="btn btn-sm btn-outline-secondary" id="html-editor-btn" data-astero-action="activateHtmlEditor"
                    title="HTML editor for enabled content" style="border-radius: 0;">
                    <i class="ri-code-s-slash-fill me-1"></i> HTML
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="css-editor-btn" data-astero-action="activateCssEditor"
                    title="CSS editor" style="border-radius: 0;">
                    <i class="ri-code-s-slash-fill me-1"></i> CSS
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="js-editor-btn" data-astero-action="activateJsEditor"
                    title="JavaScript editor" style="border-radius: 0;">
                    <i class="ri-code-s-slash-fill me-1"></i> JS
                </button>
            </div>
            <div class="btn-group" role="group">
                <button class="btn btn-sm btn-outline-secondary" id="fullscreen-editor-btn" data-astero-action="toggleEditorFullscreen"
                    title="Toggle fullscreen">
                    <i class="ri-fullscreen-line"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="close-editor-btn" data-astero-action="closeEditor"
                    title="Close editor">
                    <i class="ri-close-line"></i>
                </button>
            </div>
        </div>
    </div>

    <div id="astero-code-editor">
        <x-textarea-monaco syntax="html" :height="420">
            <textarea class="form-control" rows="20"></textarea>
        </x-textarea-monaco>
    </div>
    <div id="astero-enabled-content-editor" style="display: none;">
        <x-textarea-monaco syntax="html" :height="360">
            <textarea class="form-control" id="enabled-content-editor" name="editable_content" rows="16"></textarea>
        </x-textarea-monaco>
    </div>
    <div id="astero-css-editor" style="display: none;">
        <x-textarea-monaco syntax="css" :height="360">
            <textarea class="form-control" id="css-editor" name="editable_css" rows="16"></textarea>
        </x-textarea-monaco>
    </div>
    <div id="astero-js-editor" style="display: none;">
        <x-textarea-monaco syntax="javascript" :height="360">
            <textarea class="form-control" id="js-editor" name="editable_js" rows="16"></textarea>
        </x-textarea-monaco>
    </div>
</div>
