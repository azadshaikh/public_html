function displayToast(bg, title, message, id = 'top-toast') {
    const toastBody = document.querySelector(
        '#' + id + ' .toast-body .message',
    );
    const header = document.querySelector('#' + id + ' .toast-header');
    const toast = document.querySelector('#' + id + ' .toast');
    if (!toastBody || !header || !toast) return;

    toastBody.textContent = String(message ?? '');
    toastBody.style.whiteSpace = 'pre-line';
    header.classList.remove('bg-danger', 'bg-success', 'bg-info', 'bg-warning');
    header.classList.add(bg);
    const titleEl = header.querySelector('strong');
    if (titleEl) {
        titleEl.textContent = title;
    }
    toast.classList.add('show');
    delay(() => toast.classList.remove('show'), 5000);
}

// Expose displayToast globally for other modules
window.displayToast = displayToast;

Astero.Gui = {
    init: function () {
        document
            .querySelectorAll('[data-astero-action]')
            .forEach(function (el, i) {
                const on = el.dataset.asteroOn ?? 'click';
                const actionName = el.dataset.asteroAction;
                if (typeof Astero.Gui[actionName] === 'function') {
                    el.addEventListener(
                        on,
                        Astero.Gui[actionName].bind(Astero.Gui),
                    );
                } else {
                    console.error(
                        `Action ${actionName} not found in Astero.Gui`,
                    );
                }
            });

        this.shortcuts();
        this.initEditorButtons();
    },

    initEditorButtons: function () {
        // Initialize editor buttons state
        let codeEditorBtn = document.getElementById('code-editor-btn');
        let htmlEditorBtn = document.getElementById('html-editor-btn');
        let cssEditorBtn = document.getElementById('css-editor-btn');
        let jsEditorBtn = document.getElementById('js-editor-btn');

        if (codeEditorBtn) codeEditorBtn.classList.remove('active');
        if (htmlEditorBtn) htmlEditorBtn.classList.remove('active');
        if (cssEditorBtn) cssEditorBtn.classList.remove('active');
        if (jsEditorBtn) jsEditorBtn.classList.remove('active');

        // Ensure all editors are hidden initially
        let codeEditor = document.getElementById('astero-code-editor');
        let htmlEditor = document.getElementById(
            'astero-enabled-content-editor',
        );
        let cssEditor = document.getElementById('astero-css-editor');
        let jsEditor = document.getElementById('astero-js-editor');

        if (codeEditor) codeEditor.style.display = 'none';
        if (htmlEditor) htmlEditor.style.display = 'none';
        if (cssEditor) cssEditor.style.display = 'none';
        if (jsEditor) jsEditor.style.display = 'none';
    },

    shortcuts: function () {
        let self = this;

        const handleShortcuts = function (e) {
            if (e.ctrlKey) {
                switch (e.key) {
                    case 's':
                        e.preventDefault();
                        const btn = document.querySelector('.save-btn');
                        const url = btn?.dataset?.asteroUrl;
                        self.saveAjax(null, url, btn);
                        return;
                    case 'z':
                        e.preventDefault();
                        self.undo();
                        return;
                    case 'Z':
                    case 'y':
                        e.preventDefault();
                        self.redo();
                        return;
                    case 'L':
                        e.preventDefault();
                        self.toggleTreeList();
                        return;
                    case 'e':
                        e.preventDefault();
                        self.toggleEditor();
                        return;
                }
            }
        };

        // Handle shortcuts from main window and iframe
        document.addEventListener('keydown', handleShortcuts);
        window.addEventListener('astero.iframe.loaded', () => {
            Astero.Builder.frameBody?.addEventListener(
                'keydown',
                handleShortcuts,
            );
        });
    },

    undo: function () {
        if (Astero.WysiwygEditor.isActive) {
            Astero.WysiwygEditor.undo();
        } else {
            Astero.Undo.undo();
        }
        Astero.Builder.selectNode();
    },

    redo: function () {
        if (Astero.WysiwygEditor.isActive) {
            Astero.WysiwygEditor.redo();
        } else {
            Astero.Undo.redo();
        }
        Astero.Builder.selectNode();
    },

    //show modal with html content — REMOVED (dead code, not used for saving)

    // Post HTML content via AJAX to save to database
    // Overridden in builder.blade.php for Laravel integration
    saveAjax: function (event, saveUrl = null, saveBtn = null) {
        console.warn(
            'Astero.Gui.saveAjax: No save handler configured. Override this in your blade template.',
        );
    },

    viewport: function (event) {
        const element = event ? event.currentTarget : this;
        document
            .getElementById('canvas')
            .setAttribute('class', element.dataset.view);
        document.getElementById('iframe1').removeAttribute('style');
        document
            .querySelectorAll('.responsive-btns .active')
            .forEach((e) => e.classList.remove('active'));
        if (element.dataset.view) element.classList.add('active');
    },

    toggleEditor: function () {
        let asteroBuilder = document.getElementById('astero-builder');
        let codeEditor = document.getElementById('astero-code-editor');
        let codeEditorBtn = document.getElementById('code-editor-btn');
        let toggleJsExecute = document.getElementById('toggleEditorJsExecute');
        let breadcrumb = document.querySelector(
            '.breadcrumb-navigator .breadcrumb',
        );

        if (!asteroBuilder || !codeEditor || !codeEditorBtn) {
            return;
        }

        let isExpanding = !asteroBuilder.classList.contains(
            'bottom-panel-expand',
        );

        // If any tab editor (HTML/CSS/JS) is active, close it and switch to code editor
        const tabEditors = [
            {
                panel: 'astero-enabled-content-editor',
                btn: 'html-editor-btn',
                obj: Astero.EnabledContentEditor,
            },
            {
                panel: 'astero-css-editor',
                btn: 'css-editor-btn',
                obj: Astero.CssEditor,
            },
            {
                panel: 'astero-js-editor',
                btn: 'js-editor-btn',
                obj: Astero.JsEditor,
            },
        ];

        for (const editor of tabEditors) {
            const panelEl = document.getElementById(editor.panel);
            if (panelEl && panelEl.style.display === 'block') {
                panelEl.style.display = 'none';
                if (editor.obj?.isActive && editor.obj.destroy)
                    editor.obj.destroy();
                document.getElementById(editor.btn)?.classList.remove('active');

                // Open code editor
                if (!asteroBuilder.classList.contains('bottom-panel-expand')) {
                    asteroBuilder.classList.add('bottom-panel-expand');
                    breadcrumb?.classList.add('d-none');
                }
                codeEditor.style.display = 'block';
                toggleJsExecute?.classList.remove('d-none');
                codeEditorBtn.classList.add('active');
                if (!Astero.CodeEditor.isActive) Astero.CodeEditor.toggle();
                return;
            }
        }

        // Normal code editor toggle
        if (isExpanding) {
            asteroBuilder.classList.add('bottom-panel-expand');
            toggleJsExecute?.classList.remove('d-none');
            breadcrumb?.classList.add('d-none');
            codeEditor.style.display = 'block';
            codeEditorBtn.classList.add('active');
        } else {
            asteroBuilder.classList.remove('bottom-panel-expand');
            toggleJsExecute?.classList.add('d-none');
            breadcrumb?.classList.remove('d-none');
            codeEditor.style.display = 'none';
            codeEditorBtn.classList.remove('active');
        }

        Astero.CodeEditor.toggle();
    },

    toggleEditorJsExecute: function () {
        Astero.Builder.runJsOnSetHtml = this.checked;
    },

    toggleEditorTabs: function () {
        let asteroBuilder = document.getElementById('astero-builder');
        let codeEditorBtn = document.getElementById('code-editor-btn');
        let editorTabs = document.getElementById('editor-tabs');
        let breadcrumb = document.querySelector('.breadcrumb-navigator');
        if (!asteroBuilder || !codeEditorBtn || !editorTabs) return;

        // Check if editor tabs are currently visible
        let isTabsVisible = editorTabs && editorTabs.style.display !== 'none';

        if (!isTabsVisible) {
            // Show editor tabs and hide the main code editor button
            if (editorTabs) {
                editorTabs.style.display = 'flex';
            }
            if (codeEditorBtn) {
                codeEditorBtn.style.display = 'none';
            }

            // Expand the bottom panel
            if (!asteroBuilder.classList.contains('bottom-panel-expand')) {
                asteroBuilder.classList.add('bottom-panel-expand');
            }
            breadcrumb?.classList.add('d-none');

            // Activate HTML editor by default
            this.activateHtmlEditor();
        } else {
            // Hide editor tabs and show the main code editor button
            if (editorTabs) {
                editorTabs.style.display = 'none';
            }
            if (codeEditorBtn) {
                codeEditorBtn.style.display = 'inline-block';
            }

            // Close all editors
            this.closeAllEditors();
        }
    },

    closeEditor: function () {
        // Hide editor tabs and show the main code editor button
        let editorTabs = document.getElementById('editor-tabs');
        let codeEditorBtn = document.getElementById('code-editor-btn');

        if (editorTabs) {
            editorTabs.style.display = 'none';
        }
        if (codeEditorBtn) {
            codeEditorBtn.style.display = 'inline-block';
        }

        // Exit fullscreen mode if active
        let asteroBuilder = document.getElementById('astero-builder');
        if (
            asteroBuilder &&
            asteroBuilder.classList.contains('editor-fullscreen')
        ) {
            asteroBuilder.classList.remove('editor-fullscreen');
            let fullscreenBtn = document.getElementById(
                'fullscreen-editor-btn',
            );
            let icon = fullscreenBtn?.querySelector('i');
            if (icon) {
                icon.className = 'ri-fullscreen-line';
            }
        }

        // Close all editors
        this.closeAllEditors();
    },

    activateHtmlEditor: function () {
        this._activateEditorTab('html');
    },

    activateCssEditor: function () {
        this._activateEditorTab('css');
    },

    activateJsEditor: function () {
        this._activateEditorTab('js');
    },

    /**
     * Shared helper to activate a specific editor tab (html, css, js)
     */
    _activateEditorTab: function (type) {
        const editors = {
            html: {
                btn: document.getElementById('html-editor-btn'),
                panel: document.getElementById('astero-enabled-content-editor'),
                editorObj:
                    typeof Astero.EnabledContentEditor !== 'undefined'
                        ? Astero.EnabledContentEditor
                        : null,
                showJsToggle: false,
            },
            css: {
                btn: document.getElementById('css-editor-btn'),
                panel: document.getElementById('astero-css-editor'),
                editorObj:
                    typeof Astero.CssEditor !== 'undefined'
                        ? Astero.CssEditor
                        : null,
                showJsToggle: false,
            },
            js: {
                btn: document.getElementById('js-editor-btn'),
                panel: document.getElementById('astero-js-editor'),
                editorObj:
                    typeof Astero.JsEditor !== 'undefined'
                        ? Astero.JsEditor
                        : null,
                showJsToggle: true,
            },
        };

        let toggleJsExecute = document.getElementById('toggleEditorJsExecute');

        // Deactivate all editors
        for (const [key, config] of Object.entries(editors)) {
            if (config.btn) config.btn.classList.remove('active');
            if (config.panel) config.panel.style.display = 'none';
        }

        // Activate the requested editor
        const target = editors[type];
        if (!target) return;

        if (target.panel) target.panel.style.display = 'block';
        if (target.btn) target.btn.classList.add('active');

        // JS execute toggle visibility
        if (target.showJsToggle) {
            toggleJsExecute?.classList.remove('d-none');
        } else {
            toggleJsExecute?.classList.add('d-none');
        }

        // Initialize editor if needed
        if (target.editorObj) {
            if (!target.editorObj.isActive) {
                target.editorObj.init();
            } else if (target.editorObj.refresh) {
                target.editorObj.refresh();
            }
        }
    },

    closeAllEditors: function () {
        let asteroBuilder = document.getElementById('astero-builder');
        let codeEditor = document.getElementById('astero-code-editor');
        let htmlEditor = document.getElementById(
            'astero-enabled-content-editor',
        );
        let cssEditor = document.getElementById('astero-css-editor');
        let jsEditor = document.getElementById('astero-js-editor');
        let toggleJsExecute = document.getElementById('toggleEditorJsExecute');
        let breadcrumb = document.querySelector('.breadcrumb-navigator');

        // Remove bottom panel expansion
        asteroBuilder?.classList.remove('bottom-panel-expand');

        // Hide all editors
        if (codeEditor) codeEditor.style.display = 'none';
        if (htmlEditor) htmlEditor.style.display = 'none';
        if (cssEditor) cssEditor.style.display = 'none';
        if (jsEditor) jsEditor.style.display = 'none';

        // Show breadcrumb
        breadcrumb?.classList.remove('d-none');

        // Hide JS execute toggle
        toggleJsExecute?.classList.add('d-none');

        // Destroy active editors
        if (Astero.CodeEditor && Astero.CodeEditor.isActive) {
            Astero.CodeEditor.destroy();
        }
        if (
            typeof Astero.EnabledContentEditor !== 'undefined' &&
            Astero.EnabledContentEditor.isActive
        ) {
            Astero.EnabledContentEditor.destroy();
        }
        if (Astero.CssEditor && Astero.CssEditor.destroy) {
            Astero.CssEditor.destroy();
        }

        if (Astero.JsEditor && Astero.JsEditor.destroy) {
            Astero.JsEditor.destroy();
        }

        // Remove active classes from all buttons
        let codeEditorBtn = document.getElementById('code-editor-btn');
        let htmlEditorBtn = document.getElementById('html-editor-btn');
        let cssEditorBtn = document.getElementById('css-editor-btn');
        let jsEditorBtn = document.getElementById('js-editor-btn');

        if (codeEditorBtn) codeEditorBtn.classList.remove('active');
        if (htmlEditorBtn) htmlEditorBtn.classList.remove('active');
        if (cssEditorBtn) cssEditorBtn.classList.remove('active');
        if (jsEditorBtn) jsEditorBtn.classList.remove('active');
    },

    search: function (e) {
        let element = e ? e.target : this;
        let searchText = (element.value || '').toLowerCase();
        let searchContainer = element.closest('.tab-pane');
        let targetList;

        // Find the appropriate list based on which search input is being used
        if (element.classList.contains('section-search')) {
            targetList = searchContainer.querySelector('.sections-list');
        } else if (element.classList.contains('component-search')) {
            targetList = searchContainer.querySelector('.components-list');
        } else if (element.classList.contains('block-search')) {
            targetList = searchContainer.querySelector('.blocks-list');
        }

        if (!targetList) return;

        // Auto-expand or collapse headers based on search
        if (searchText !== '') {
            // Expand all headers when searching
            searchContainer
                .querySelectorAll('input.header_check[type="checkbox"]')
                .forEach((checkbox) => (checkbox.checked = true));
        } else {
            // Collapse all headers when search is cleared
            searchContainer
                .querySelectorAll('input.header_check[type="checkbox"]')
                .forEach((checkbox) => (checkbox.checked = false));
        }

        // Search through all items in the list
        targetList.querySelectorAll('li[data-search]').forEach(function (el) {
            let parentHeader = el.closest('li.header');
            let isMatch = el.dataset.search.indexOf(searchText) > -1;

            if (searchText === '') {
                // Show all items when search is empty
                el.style.display = '';
                if (parentHeader) {
                    parentHeader.style.display = '';
                }
            } else {
                // Show/hide based on search match
                el.style.display = isMatch ? '' : 'none';

                // Show parent header if any child matches
                if (parentHeader && isMatch) {
                    parentHeader.style.display = '';
                }
            }
        });

        // Hide empty headers when searching
        if (searchText !== '') {
            targetList.querySelectorAll('li.header').forEach(function (header) {
                let hasVisibleChildren = header.querySelector(
                    'ol li[data-search][style=""], ol li[data-search]:not([style*="display: none"])',
                );
                header.style.display = hasVisibleChildren ? '' : 'none';
            });
        }
    },

    clearSearch: function (e) {
        let element = e ? e.target : this;
        let searchContainer = element.closest('.search'); // Or .tab-pane if structure differs
        if (!searchContainer) searchContainer = element.closest('.tab-pane'); // Fallback

        if (!searchContainer) return;

        let input = searchContainer.querySelector(
            "input[data-astero-action='search']",
        );
        if (input) {
            input.value = '';
            input.dispatchEvent(
                new KeyboardEvent('keyup', {
                    bubbles: true,
                    cancelable: true,
                }),
            );
        }
    },

    expandAllSettings: function () {
        document
            .querySelectorAll('#right-panel .tab-pane.active .header_check')
            .forEach((el) => (el.checked = true));
    },

    collapseAllSettings: function () {
        document
            .querySelectorAll('#right-panel .tab-pane.active .header_check')
            .forEach((el) => (el.checked = false));
    },

    expand: function (e) {
        let element = e ? e.target : this;
        let searchContainer = element.closest('.tab-pane');
        if (searchContainer) {
            searchContainer
                .querySelectorAll('input.header_check[type="checkbox"]')
                .forEach((checkbox) => (checkbox.checked = true));
        }
    },

    collapse: function (e) {
        let element = e ? e.target : this;
        let searchContainer = element.closest('.tab-pane');
        if (searchContainer) {
            searchContainer
                .querySelectorAll('input.header_check[type="checkbox"]')
                .forEach((checkbox) => (checkbox.checked = false));
        }
    },

    //layout
    togglePanel: function (panel, cssVar) {
        panel = document.querySelector(panel);
        let body = document.querySelector('body');
        let prevValue = getComputedStyle(body).getPropertyValue(cssVar);
        let visible = false;

        if (prevValue !== '0px') {
            panel.dataset.layoutToggle = prevValue;
            body.style.setProperty(cssVar, '0px');
            panel.style.display = 'none';
            visible = false;
        } else {
            prevValue = panel.dataset.layoutToggle;
            body.style.setProperty(cssVar, '');
            panel.style.display = '';
            visible = true;
        }

        return visible;
    },

    toggleLeftColumn: function () {
        Astero.Gui.togglePanel('#left-panel', '--builder-left-panel-width');
    },

    toggleRightColumn: function (rightColumnEnabled = null) {
        rightColumnEnabled = Astero.Gui.togglePanel(
            '#right-panel',
            '--builder-right-panel-width',
        );

        document
            .getElementById('astero-builder')
            .classList.toggle('no-right-panel');
        document
            .querySelector('.component-properties-tab')
            .classList.toggle('d-none');

        Astero.Components.componentPropertiesElement =
            (rightColumnEnabled ? '#right-panel' : '#left-panel #properties') +
            ' .component-properties';
        let componentTab = document.querySelector('#components-tab');

        if (document.getElementById('properties').offsetParent) {
            const bsTab = bootstrap.Tab.getOrCreateInstance(componentTab);
            componentTab.style.display = '';
            bsTab.show();
        }
    },

    toggleTreeList: function () {
        let treeList = document.getElementById('tree-list');
        let toggleButton = document.getElementById('toggle-tree-list');
        let wasHidden = treeList.classList.contains('d-none');

        treeList.classList.toggle('d-none');

        // Explicitly set the active state of the button based on panel visibility
        if (treeList.classList.contains('d-none')) {
            toggleButton.classList.remove('active');
            toggleButton.setAttribute('aria-pressed', 'false');
        } else {
            toggleButton.classList.add('active');
            toggleButton.setAttribute('aria-pressed', 'true');
            // If navigator was hidden and is now visible, load components
            if (
                wasHidden &&
                Astero.TreeList &&
                Astero.TreeList.loadComponents
            ) {
                Astero.TreeList.loadComponents();
            }
        }
    },

    treeListRight: function () {
        let treeList = document.getElementById('tree-list');
        let btnIcon = document.querySelector(
            "[data-astero-action='treeListRight'] i",
        );
        if (treeList.style.height) {
            treeList.style.height = '';
            treeList.style.right = '';
            treeList.style.top = '';
            treeList.style.left = '';
            treeList.style.width = '';
            btnIcon.className = 'bi-stop';
        } else {
            treeList.style.height = '100vh';
            treeList.style.height = 'calc(100vh - 35px)';
            treeList.style.right = '0';
            treeList.style.top = '35px';
            treeList.style.left = 'auto';
            treeList.style.width = '300px';
            btnIcon.className = 'bi-trash3';
        }
    },

    setState: function () {
        Astero.StyleManager.setState(this.value);
        Astero.Builder.reloadComponent();
    },

    toggleEditorFullscreen: function () {
        let asteroBuilder = document.getElementById('astero-builder');
        let fullscreenBtn = document.getElementById('fullscreen-editor-btn');
        let icon = fullscreenBtn?.querySelector('i');

        // Toggle the fullscreen class on the builder
        asteroBuilder.classList.toggle('editor-fullscreen');

        // Update button icon
        if (icon) {
            if (asteroBuilder.classList.contains('editor-fullscreen')) {
                icon.className = 'ri-fullscreen-exit-line';
            } else {
                icon.className = 'ri-fullscreen-line';
            }
        }

        // Trigger Monaco layout update for all editors
        document
            .querySelectorAll('#bottom-panel textarea')
            .forEach((textarea) => {
                if (textarea.monacoEditor) {
                    setTimeout(() => textarea.monacoEditor.layout(), 100);
                }
            });
    },
};
