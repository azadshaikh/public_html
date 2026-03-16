@php
    $backUrl = $page->type === 'post'
        ? route('cms.posts.edit', $page)
        : route('cms.pages.edit', $page);
@endphp

<div class="d-flex align-items-center border-bottom" id="top-panel">

    {{-- Left: Back + Title --}}
    <div class="d-flex align-items-center gap-2 me-3" style="min-width: 240px;">
        <a class="btn btn-sm btn-outline-secondary" href="{{ $backUrl }}" title="Back to editor">
            <i class="ri-arrow-left-line"></i>
            <span class="d-none d-lg-inline ms-1">Back</span>
        </a>
    </div>

    {{-- Main Actions - Centered --}}
    <div class="flex-grow-1 d-flex justify-content-center">
        <div class="d-flex align-items-center gap-1">

            {{-- Panels / Navigator --}}
            <div class="d-flex align-items-center">
                <button class="toolbar-btn-dark active" id="toggle-left-column-btn" data-astero-action="toggleLeftColumn"
                    data-bs-toggle="button" title="Toggle left panel">
                    <i class="ri-layout-left-line"></i>
                </button>
                <button class="toolbar-btn-dark active" id="toggle-right-column-btn"
                    data-astero-action="toggleRightColumn" data-bs-toggle="button" title="Toggle right panel">
                    <i class="ri-layout-right-line"></i>
                </button>
                <button class="toolbar-btn-dark" id="toggle-tree-list" data-bs-toggle="button"
                    data-astero-action="toggleTreeList" title="Toggle navigator" aria-pressed="false">
                    <i class="ri-stack-line"></i>
                </button>
            </div>

            <div class="vr mx-2 d-none d-md-block"></div>

            {{-- Viewport --}}
            <div class="d-none d-md-flex responsive-btns">
                <button class="toolbar-btn-dark" id="mobile-view" data-view="mobile" data-astero-action="viewport"
                    title="Mobile view">
                    <i class="ri-phone-line"></i>
                </button>
                <button class="toolbar-btn-dark" id="tablet-view" data-view="tablet" data-astero-action="viewport"
                    title="Tablet view">
                    <i class="ri-tablet-line"></i>
                </button>
                <button class="toolbar-btn-dark" id="desktop-view" data-view="" data-astero-action="viewport"
                    title="Desktop view">
                    <i class="ri-macbook-line"></i>
                </button>
            </div>

            {{-- Undo/Redo --}}
            <div class="d-none d-md-flex">
                <button class="toolbar-btn-dark" data-astero-action="undo" title="Undo (Ctrl+Z)">
                    <i class="ri-arrow-go-back-line"></i>
                </button>
                <button class="toolbar-btn-dark" data-astero-action="redo" title="Redo (Ctrl+Y)">
                    <i class="ri-arrow-go-forward-line"></i>
                </button>
            </div>

        </div>
    </div>

    {{-- Right Side --}}
    <div class="d-flex align-items-center">
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="{{ url($page->permalink_url) }}" title="View" target="_blank" rel="noopener noreferrer">
                <i class="ri-eye-line"></i>
                <span class="d-none d-lg-inline ms-1">View</span>
            </a>

            <a class="btn btn-sm btn-primary save-btn" id="save-btn" data-astero-action="saveAjax"
                data-v-astero-shortcut="ctrl+s" title="Save (Ctrl+S)">
                <span class="loading d-none">
                    <span class="spinner-border spinner-border-sm me-2" style="width: 14px; height: 14px;"></span>
                </span>
                <i class="ri-save-line"></i>
                <span class="d-none d-lg-inline ms-1">Save</span>
            </a>
        </div>
    </div>

</div>
