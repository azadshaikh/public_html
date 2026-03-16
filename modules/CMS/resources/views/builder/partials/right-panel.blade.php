<div id="right-panel">
    <div class="component-properties">
        <ul class="nav nav-tabs nav-fill" id="properties-tabs" role="tablist">
            <li class="nav-item content-tab">
                <a class="nav-link active" data-bs-toggle="tab" href="#content-tab" role="tab"
                    aria-controls="components" aria-selected="true">
                    <i class="ri-folder-line"></i> <span>Content</span></a>
            </li>
            <li class="nav-item style-tab">
                <a class="nav-link" data-bs-toggle="tab" href="#style-tab" role="tab" aria-controls="blocks"
                    aria-selected="false">
                    <i class="ri-paint-brush-line"></i> <span>Style</span></a>
            </li>
            <li class="nav-item advanced-tab">
                <a class="nav-link" data-bs-toggle="tab" href="#advanced-tab" role="tab" aria-controls="blocks"
                    aria-selected="false">
                    <i class="ri-settings-3-line"></i> <span>Advanced</span></a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane show active" id="content-tab" data-section="content" role="tabpanel"
                aria-labelledby="content-tab">
                <div class="alert alert-dismissible fade show alert-light m-3" role="alert">
                    <button class="btn-close" data-bs-dismiss="alert" type="button" aria-label="Close"></button>
                    <strong>No selected element!</strong><br> Click on an element to edit.
                </div>
            </div>
            <div class="tab-pane show" id="style-tab" data-section="style" role="tabpanel" aria-labelledby="style-tab">
                <div class="border-bottom px-2 pb-2">
                    <div class="justify-content-end d-flex">
                        <select class="form-select w-50 form-select-sm" data-astero-action="setState" data-astero-on="change">
                            <option value=""> - State - </option>
                            <option value="hover">hover</option>
                            <option value="active">active</option>
                            <option value="nth-of-type(2n)">nth-of-type(2n)</option>
                        </select>
                        <button class="btn btn-outline-secondary btn-sm btn-icon-sm p-0 ms-2" data-astero-action="expandAllSettings" title="Expand All"><i class="ri-add-line"></i></button>
                        <button class="btn btn-outline-secondary btn-sm btn-icon-sm p-0 ms-1" data-astero-action="collapseAllSettings" title="Collapse All"><i class="ri-subtract-line"></i></button>
                    </div>
                </div>
            </div>
            <div class="tab-pane show" id="advanced-tab" data-section="advanced" role="tabpanel"
                aria-labelledby="advanced-tab">
            </div>
        </div>
    </div>
</div>


