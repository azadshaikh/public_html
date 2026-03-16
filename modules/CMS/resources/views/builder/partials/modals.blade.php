<!-- add section modal -->
<div class="modal fade" id="add-section-modal" role="dialog" aria-labelledby="add-section-modal" aria-hidden="true" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <ul class="nav nav-tabs border-0" id="box-elements-tabs" role="tablist">
                    <li class="nav-item component-tab">
                        <a class="nav-link active px-3" id="box-components-tab" data-bs-toggle="tab"
                            href="#box-components" role="tab" aria-controls="components"
                            aria-selected="true"><i class="ri-archive-drawer-line me-1"></i><small>Components</small></a>
                    </li>
                    <li class="nav-item blocks-tab">
                        <a class="nav-link px-3" id="box-blocks-tab" data-bs-toggle="tab" href="#box-blocks"
                            role="tab" aria-controls="blocks" aria-selected="false"><i
                                class="ri-file-copy-line me-1"></i><small>Blocks</small></a>
                    </li>
                    <li class="nav-item sections-tab">
                        <a class="nav-link px-3" id="box-sections-tab" data-bs-toggle="tab" href="#box-sections"
                            role="tab" aria-controls="sections" aria-selected="false"><i
                                class="ri-layout-top-line me-1"></i><small>Sections</small></a>
                    </li>
                </ul>

                <div class="ms-auto d-flex align-items-center">
                    <div class="small me-3">
                        <div class="form-check d-inline-block small me-2">
                            <input class="form-check-input" id="add-section-insert-mode-after"
                                name="add-section-insert-mode" type="radio" value="after" checked="checked">
                            <label class="form-check-label" for="add-section-insert-mode-after">After</label>
                        </div>
                        <div class="form-check d-inline-block small">
                            <input class="form-check-input" id="add-section-insert-mode-inside"
                                name="add-section-insert-mode" type="radio" value="inside">
                            <label class="form-check-label" for="add-section-insert-mode-inside">Inside</label>
                        </div>
                    </div>
                    <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
            </div>
            
            <div class="modal-body p-0">
                <div class="tab-content h-100">
                    <div class="tab-pane show active h-100" id="box-components" role="tabpanel"
                        aria-labelledby="components-tab">
                        <div class="search p-3 border-bottom">
                            <div class="expand float-end">
                                <button class="btn btn-sm btn-light" data-astero-action="expand" title="Expand All"><i
                                        class="ri-add-line"></i></button>
                                <button class="btn btn-sm btn-light" data-astero-action="collapse" title="Collapse all"><i
                                        class="ri-subtract-line"></i></button>
                            </div>
                            <div class="position-relative w-50">
                                <input class="form-control component-search" data-astero-action="search"
                                    data-astero-on="keyup" type="text" placeholder="Search components">
                                <button class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-decoration-none text-muted" 
                                    data-astero-action="clearSearch" style="right: 10px;">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                        </div>
                        <div class="p-4" style="min-height: 400px;">
                            <ul class="components-list clearfix" data-type="addbox">
                            </ul>
                        </div>
                    </div>
                    <div class="tab-pane h-100" id="box-blocks" role="tabpanel" aria-labelledby="blocks-tab">
                        <div class="search p-3 border-bottom">
                            <div class="expand float-end">
                                <button class="btn btn-sm btn-light" data-astero-action="expand" title="Expand All"><i
                                        class="ri-add-line"></i></button>
                                <button class="btn btn-sm btn-light" data-astero-action="collapse" title="Collapse all"><i
                                        class="ri-subtract-line"></i></button>
                            </div>
                            <div class="position-relative w-50">
                                <input class="form-control block-search" data-astero-action="search"
                                    data-astero-on="keyup" type="text" placeholder="Search blocks">
                                <button class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-decoration-none text-muted" 
                                    data-astero-action="clearSearch" style="right: 10px;">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                        </div>
                        <div class="p-4" style="min-height: 400px;">
                            <ul class="blocks-list clearfix" data-type="addbox">
                            </ul>
                        </div>
                    </div>
                    <div class="tab-pane h-100" id="box-sections" role="tabpanel" aria-labelledby="sections-tab">
                        <div class="search p-3 border-bottom">
                            <div class="expand float-end">
                                <button class="btn btn-sm btn-light" data-astero-action="expand" title="Expand All"><i
                                        class="ri-add-line"></i></button>
                                <button class="btn btn-sm btn-light" data-astero-action="collapse" title="Collapse all"><i
                                        class="ri-subtract-line"></i></button>
                            </div>
                            <div class="position-relative w-50">
                                <input class="form-control section-search" data-astero-action="search"
                                    data-astero-on="keyup" type="text" placeholder="Search sections">
                                <button class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-decoration-none text-muted" 
                                    data-astero-action="clearSearch" style="right: 10px;">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                        </div>
                        <div class="p-4" style="min-height: 400px;">
                            <ul class="sections-list clearfix" data-type="addbox">
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- code editor modal -->
<div class="modal modal-full fade" id="codeEditorModal" role="dialog" aria-labelledby="codeEditorModal" aria-hidden="true"
    tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <input name="file" type="hidden" value="">

            <div class="modal-header justify-content-between">
                <span class="modal-title"></span>
                <div class="float-end">
                    <button class="btn btn-light border" data-bs-dismiss="modal" type="button"><i
                            class="ri-close-line me-1"></i>Close</button>

                    <button class="btn btn-primary save-btn" title="Save changes">
                        <span class="loading d-none">
                            <i class="ri-save-line me-1"></i>
                            <span class="spinner-border spinner-border-sm align-middle" role="status"
                                aria-hidden="true">
                            </span>
                            <span>Saving </span> ... </span>

                        <span class="button-text">
                            <i class="ri-save-line me-1"></i> <span>Save changes</span>
                        </span>
                    </button>
                </div>
            </div>

            <div class="modal-body p-0">
                <x-textarea-monaco syntax="html" :height="520">
                    <textarea class="form-control h-100" rows="24"></textarea>
                </x-textarea-monaco>
            </div>

        </div>
    </div>
</div>

<!-- export html modal-->
<div class="modal fade" id="textarea-modal" role="dialog" aria-labelledby="textarea-modal" aria-hidden="true"
    tabindex="-1">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <p class="modal-title text-primary"><i class="ri-save-line bi-lg me-1"></i> Export html</p>
                <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close">
                    <!-- span aria-hidden="true"><small><i class="ri-close-line"></i></small></span -->
                </button>
            </div>
            <div class="modal-body">

                <x-textarea-monaco syntax="html" :height="500">
                    <textarea class="form-control" rows="25" cols="150"></textarea>
                </x-textarea-monaco>

            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-lg" data-bs-dismiss="modal" type="button"><i
                        class="ri-close-line me-1"></i> Close</button>
            </div>
        </div>
    </div>
</div>

<!-- message modal-->
<div class="modal fade" id="message-modal" role="dialog" tabindex="-1">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <p class="modal-title text-primary"><i class="ri-chat-1-line"></i> Astero</p>
                <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close">
                    <!-- span aria-hidden="true"><small><i class="ri-close-line"></i></small></span -->
                </button>
            </div>
            <div class="modal-body">
                <p>Page was successfully saved!.</p>
            </div>
            <div class="modal-footer">
                <!-- <button class="btn btn-primary" type="button">Ok</button> -->
                <button class="btn btn-secondary btn-lg" data-bs-dismiss="modal" type="button"><i
                        class="ri-close-line me-1"></i> Close</button>
            </div>
        </div>
    </div>
</div>

<!-- save toast -->
<div class="toast-container position-fixed bottom-0 end-0 mb-3 me-3" id="top-toast">
    <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header text-white">
            <strong class="me-auto">Page save</strong>
            <!-- <small class="badge bg-success">status</small> -->
            <button class="btn-close px-2 text-white" data-bs-dismiss="toast" type="button"
                aria-label="Close"></button>
        </div>
        <div class="toast-body">
            <div class="flex-grow-1">
                <div class="message">Elements saved!
                    <div>Template backup was saved!</div>
                    <div>Template was saved!</div>
                </div>
                <!--
   <div><a class="btn btn-success btn-icon btn-sm w-100 mt-2" href="">View page</a></div>
   -->
            </div>
        </div>
    </div>
</div>
