<div id="left-panel">
    <div>

        <div class="drag-elements">
            <div class="header">
                <ul class="nav nav-tabs nav-fill" id="elements-tabs" role="tablist">
                    <li class="nav-item sections-tab">
                        <a class="nav-link active" id="sections-tab" data-bs-toggle="tab" href="#sections" title="Sections"
                            role="tab" aria-controls="sections" aria-selected="true">
                            <i class="ri-stack-line"></i>
                        </a>
                    </li>
                    <li class="nav-item component-tab">
                        <a class="nav-link" id="components-tab" data-bs-toggle="tab" href="#components-tabs"
                            title="Components" role="tab" aria-controls="components" aria-selected="false">
                            <i class="ri-archive-drawer-fill"></i>
                        </a>
                    </li>
                    <li class="nav-item component-properties-tab d-none">
                        <a class="nav-link" id="properties-tab" data-bs-toggle="tab" href="#properties"
                            title="Properties" role="tab" aria-controls="properties" aria-selected="false">
                            <i class="ri-settings-3-line"></i>
                        </a>
                    </li>
                    <li class="nav-item component-configuration-tab">
                        <a class="nav-link" id="configuration-tab" data-bs-toggle="tab" href="#configuration"
                            title="Styles" role="tab" aria-controls="configuration" aria-selected="false">
                            <i class="ri-magic-line"></i>
                        </a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane show active sections" id="sections" role="tabpanel"
                        aria-labelledby="sections-tab">
                        <ul class="nav nav-tabs nav-fill sections-tabs" id="sections-tabs" role="tablist">
                            <li class="nav-item content-tab">
                                <a class="nav-link active" data-bs-toggle="tab" href="#sections-new-tab" role="tab"
                                    aria-controls="components" aria-selected="false">
                                    <i class="ri-folder-line"></i>
                                    <div><span>Sections</span></div>
                                </a>
                            </li>
                            <li class="nav-item style-tab">
                                <a class="nav-link" data-bs-toggle="tab" href="#sections-list" role="tab"
                                    aria-controls="sections" aria-selected="true">
                                    <i class="ri-file-text-line"></i>
                                    <div><span>Page Sections</span></div>
                                </a>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane" id="sections-list" data-section="style" role="tabpanel"
                                aria-labelledby="style-tab">
                                <div class="drag-elements-sidepane sidepane">
                                    <div>
                                        <div class="sections-container p-4">
                                            <div class="section-item" draggable="true">
                                                <div class="controls">
                                                    <div class="handle"></div>
                                                    <div class="info">
                                                        <div class="name">&nbsp;
                                                            <div class="type">&nbsp;</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="section-item" draggable="true">
                                                <div class="controls">
                                                    <div class="handle"></div>
                                                    <div class="info">
                                                        <div class="name">&nbsp;
                                                            <div class="type">&nbsp;</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="section-item" draggable="true">
                                                <div class="controls">
                                                    <div class="handle"></div>
                                                    <div class="info">
                                                        <div class="name">&nbsp;
                                                            <div class="type">&nbsp;</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane show active" id="sections-new-tab" data-section="content"
                                role="tabpanel" aria-labelledby="content-tab">
                                <div class="search">
                                    <div class="expand">
                                        <button class="text-sm" data-astero-action="expand" title="Expand All"><i
                                                class="ri-add-line"></i></button>
                                        <button data-astero-action="collapse" title="Collapse all"><i
                                                class="ri-subtract-line"></i></button>
                                    </div>
                                    <input class="form-control section-search" data-astero-action="search"
                                        data-astero-on="keyup" type="text" placeholder="Search sections">
                                    <button class="clear-backspace" data-astero-action="clearSearch"
                                        title="Clear search">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                                <div class="drag-elements-sidepane sidepane">
                                    <div class="block-preview"><img src="" style="display:none"></div>
                                    <div>
                                        <ul class="sections-list clearfix" data-type="leftpanel">
                                            <li class="sections-loading-placeholder">
                                                <div class="skeleton-loader">
                                                    <div class="skeleton-header"></div>
                                                    <div class="skeleton-header"></div>
                                                    <div class="skeleton-header"></div>
                                                    <div class="skeleton-header"></div>
                                                    <div class="skeleton-header"></div>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane show" id="components-tabs" role="tabpanel"
                        aria-labelledby="components-tab">
                        <ul class="nav nav-tabs nav-fill sections-tabs" role="tablist">
                            <li class="nav-item components-tab">
                                <a class="nav-link active" data-bs-toggle="tab" href="#components" role="tab"
                                    aria-controls="components" aria-selected="true">
                                    <i class="ri-archive-drawer-line"></i>
                                    <div><span>Components</span></div>
                                </a>
                            </li>
                            <li class="nav-item blocks-tab">
                                <a class="nav-link" data-bs-toggle="tab" href="#blocks" role="tab"
                                    aria-controls="components" aria-selected="false">
                                    <i class="ri-window-line"></i>
                                    <div><span>Blocks</span></div>
                                </a>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane show active components" id="components" data-section="components"
                                role="tabpanel" aria-labelledby="components-tab">
                                <div class="search">
                                    <div class="expand">
                                        <button class="text-sm" data-astero-action="expand" title="Expand All"><i
                                                class="ri-add-line"></i></button>
                                        <button data-astero-action="collapse" title="Collapse all"><i
                                                class="ri-subtract-line"></i></button>
                                    </div>
                                    <input class="form-control component-search" data-astero-action="search"
                                        data-astero-on="keyup" type="text" placeholder="Search components">
                                    <button class="clear-backspace" data-astero-action="clearSearch">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                                <div class="drag-elements-sidepane sidepane">
                                    <div>
                                        <ul class="components-list clearfix" data-type="leftpanel">
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane blocks" id="blocks" data-section="content" role="tabpanel"
                                aria-labelledby="content-tab">
                                <div class="search">
                                    <div class="expand">
                                        <button class="text-sm" data-astero-action="expand" title="Expand All"><i
                                                class="ri-add-line"></i></button>
                                        <button data-astero-action="collapse" title="Collapse all"><i
                                                class="ri-subtract-line"></i></button>
                                    </div>
                                    <input class="form-control block-search" data-astero-action="search"
                                        data-astero-on="keyup" type="text" placeholder="Search blocks">
                                    <button class="clear-backspace" data-astero-action="clearSearch">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                                <div class="drag-elements-sidepane sidepane">
                                    <div class="block-preview"><img src=""></div>
                                    <div>
                                        <ul class="blocks-list clearfix" data-type="leftpanel">
                                            <li class="blocks-loading-placeholder">
                                                <div class="skeleton-loader">
                                                    <div class="skeleton-header"></div>
                                                    <div class="skeleton-header"></div>
                                                    <div class="skeleton-header"></div>
                                                    <div class="skeleton-header"></div>
                                                    <div class="skeleton-header"></div>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane" id="properties" role="tabpanel" aria-labelledby="properties-tab">
                        <div class="component-properties-sidepane">
                            <div>
                                <div class="component-properties">
                                    <ul class="nav nav-tabs nav-fill" id="properties-left-panel-tabs" role="tablist">
                                        <li class="nav-item content-tab">
                                            <a class="nav-link content-tab active" data-bs-toggle="tab"
                                                href="#content-left-panel-tab" role="tab"
                                                aria-controls="components" aria-selected="true">
                                                <i class="ri-folder-line"></i> <span>Content</span>
                                            </a>
                                        </li>
                                        <li class="nav-item style-tab">
                                            <a class="nav-link" data-bs-toggle="tab" href="#style-left-panel-tab"
                                                role="tab" aria-controls="style" aria-selected="false">
                                                <i class="ri-palette-fill"></i> <span>Style</span></a>
                                        </li>
                                        <li class="nav-item advanced-tab">
                                            <a class="nav-link" data-bs-toggle="tab" href="#advanced-left-panel-tab"
                                                role="tab" aria-controls="advanced" aria-selected="false">
                                                <i class="ri-settings-3-fill"></i> <span>Advanced</span></a>
                                        </li>
                                    </ul>
                                    <div class="tab-content" data-offset="20">
                                        <div class="tab-pane show active" id="content-left-panel-tab"
                                            data-section="content" role="tabpanel" aria-labelledby="content-tab">
                                            <div class="alert alert-dismissible fade show alert-light m-3"
                                                role="alert" style="">
                                                <button class="btn-close" data-bs-dismiss="alert" type="button"
                                                    aria-label="Close"></button>
                                                <strong>No selected element!</strong><br> Click on an element to edit.
                                            </div>
                                        </div>
                                        <div class="tab-pane show" id="style-left-panel-tab" data-section="style"
                                            role="tabpanel" aria-labelledby="style-tab">
                                            <div class="px-2 pb-2">
                                                <div class="justify-content-end d-flex">
                                                    <select class="form-select w-50" data-astero-action="setState"
                                                        data-astero-on="change">
                                                        <option value=""> - State - </option>
                                                        <option value="hover">hover</option>
                                                        <option value="active">active</option>
                                                        <option value="nth-of-type(2n)">nth-of-type(2n)</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="tab-pane show" id="advanced-left-panel-tab"
                                            data-section="advanced" role="tabpanel" aria-labelledby="advanced-tab">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane" id="configuration" role="tabpanel" aria-labelledby="configuration-tab">
                        <div class="px-3 py-2">
                            <div class="alert alert-info">
                                Comming Soon...
                            </div>
                        </div>
                    </div><!-- end configuration tab -->
                </div>
            </div>
        </div>
    </div>
</div>
