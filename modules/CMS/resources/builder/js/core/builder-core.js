/**
 * Astero Builder - Core Module
 *
 * Core builder initialization and essential operations.
 * Extended by separate modules:
 * - builder-utils.js     - Utility functions
 * - builder-html.js      - HTML get/set/save
 * - builder-inject.js    - CSS/JS injection
 * - builder-links.js     - Link protection
 * - builder-selection.js - Highlight & selection system
 * - builder-box.js       - Selection box & actions
 * - builder-dragdrop.js  - Sidebar drag-drop
 * - builder-panels.js    - Panel loading
 */

// Ensure the builder global namespace exists
const __ASTERO_GLOBAL__ = typeof globalThis !== 'undefined' ? globalThis : window;
__ASTERO_GLOBAL__.Astero = __ASTERO_GLOBAL__.Astero || {};
const Astero = __ASTERO_GLOBAL__.Astero;

// =============================================
// BUILDER CONFIGURATION
// =============================================

Astero.defaultComponent = '_base';
Astero.preservePropertySections = true;
Astero.dragIcon = 'icon';
Astero.dragElementStyle =
    'background:limegreen;width:100%;height:3px;border:1px solid limegreen;box-shadow:0px 0px 2px 1px rgba(0,0,0,0.14);overflow:hidden;';
Astero.dragHtml = '<div style="' + Astero.dragElementStyle + '"></div>';
Astero.baseUrl = window.ASTERO_BASE_URL || '/assets/builder/';
Astero.builderAssetsUrl = Astero.baseUrl.replace(/\/$/, '');
Astero.imgBaseUrl = Astero.baseUrl;

// =============================================
// BUILDER CORE
// =============================================

Astero.Builder = {
    component: {},
    dragMoveMutation: false,
    runJsOnSetHtml: false,
    highlightEnabled: false,
    selectPadding: 0,
    leftPanelWidth: 275,
    ignoreClasses: ['clearfix', 'masonry', 'has-shadow'],

    /**
     * Initialize the builder
     */
    init: function (url, callback) {
        let self = this;

        self.loadControlGroups();
        self.loadBlockGroups();
        self.loadSectionGroups();

        self.selectedEl = null;
        self.highlightEl = null;
        self.initCallback = callback;

        self.documentFrame = document.querySelector('#iframe-wrapper > iframe');
        self.canvas = document.getElementById('canvas');

        self._loadIframe(url + (url.indexOf('?') > -1 ? '&r=' : '?r=') + Math.random());
        self._initBox();

        self.dragElement = null;
        self.highlightEnabled = true;
        self.leftPanelWidth = document.getElementById('left-panel').clientWidth;
    },

    /**
     * Load a URL in the iframe
     */
    loadUrl: function (url, callback) {
        let self = this;
        document.getElementById('select-box').style.display = 'none';
        self.initCallback = callback;
        if (Astero.Builder.iframe.src != url) Astero.Builder.iframe.src = url;
    },

    /**
     * Load the iframe with the given URL
     */
    _loadIframe: function (url) {
        let self = this;
        self.iframe = this.documentFrame;
        self.iframe.src = url;

        return this.documentFrame.addEventListener('load', function () {
            window.FrameWindow = self.iframe.contentWindow;
            window.FrameDocument = self.iframe.contentWindow.document;

            let highlightBox = document.getElementById('highlight-box');
            let SelectBox = document.getElementById('select-box');

            highlightBox.style.display = 'none';

            // Warn before leaving with unsaved changes
            window.FrameWindow.addEventListener('beforeunload', function (event) {
                if (Astero.Undo.undoIndex >= 0) {
                    let dialogText = 'You have unsaved changes';
                    event.returnValue = dialogText;
                    return dialogText;
                }
            });

            // Show loading message when unloading
            window.FrameWindow.addEventListener('unload', function (event) {
                document.querySelector('.loading-message').classList.add('active');
                Astero.Undo.reset();
            });

            // Prevent accidental link clicks when editing text
            window.FrameDocument.addEventListener('click', function (event) {
                if (Astero.WysiwygEditor.isActive && event.target.closest('a')) {
                    event.preventDefault();
                    return false;
                }
            });

            // Update select box position on scroll/resize
            const selectBoxPosition = function (event) {
                let pos;
                let target = self.selectedEl;

                highlightBox.style.display = 'none';

                if (target) {
                    pos = offset(target);
                    SelectBox.style.top = pos.top - (self.frameDoc.scrollTop ?? 0) - self.selectPadding + 'px';
                    SelectBox.style.left = pos.left - (self.frameDoc.scrollLeft ?? 0) - self.selectPadding + 'px';
                    SelectBox.style.width = (target.offsetWidth ?? target.clientWidth) + self.selectPadding * 2 + 'px';
                    SelectBox.style.height =
                        (target.offsetHeight ?? target.clientHeight) + self.selectPadding * 2 + 'px';
                }
            };

            window.FrameWindow.addEventListener('scroll', selectBoxPosition);
            window.FrameWindow.addEventListener('resize', selectBoxPosition);

            // Initialize editor systems
            Astero.WysiwygEditor.init(window.FrameDocument);
            Astero.StyleManager.init(window.FrameDocument);
            Astero.ScriptManager.init(window.FrameDocument);
            Astero.ColorPaletteManager.init(window.FrameDocument);

            if (self.initCallback) self.initCallback();
            return self._frameLoaded();
        });
    },

    /**
     * Called after iframe is fully loaded
     */
    _frameLoaded: function () {
        let self = Astero.Builder;

        self.frameDoc = window.FrameDocument;
        self.frameHtml = window.FrameDocument.querySelector('html');
        self.frameBody = window.FrameDocument.querySelector('body');
        self.frameHead = window.FrameDocument.querySelector('head');

        // Insert editor helpers CSS
        if (!self.frameHead.querySelector('link[data-astero-helpers]')) {
            const iframeHelpersCssUrl = window.ASTERO_IFRAME_HELPERS_CSS_URL;
            if (iframeHelpersCssUrl) {
                self.frameHead.append(
                    generateElements(
                        '<link data-astero-helpers href="' + iframeHelpersCssUrl + '" rel="stylesheet">'
                    )[0]
                );
            }
        }

        // Initialize subsystems (methods from other modules)
        self._initHighlight();
        self._initLinkProtection();

        window.dispatchEvent(new CustomEvent('astero.iframe.loaded', { detail: self.frameDoc }));
        document.querySelector('.loading-message').classList.remove('active');

        // Enable save button only if changes are made
        let setSaveButtonState = function (e) {
            if (Astero.Undo.hasChanges()) {
                document.querySelectorAll('#top-panel .save-btn').forEach((e) => e.removeAttribute('disabled'));
            } else {
                document.querySelectorAll('#top-panel .save-btn').forEach((e) => e.setAttribute('disabled', 'true'));
            }
        };

        Astero.Builder.frameBody.addEventListener('astero.undo.add', setSaveButtonState);
        Astero.Builder.frameBody.addEventListener('astero.undo.restore', setSaveButtonState);
    },

    /**
     * Get element type for display
     */
    _getElementType: function (el) {
        let componentName = '';
        let componentAttribute = '';

        if (el.attributes) {
            for (let j = 0; j < el.attributes.length; j++) {
                let nodeName = el.attributes[j].nodeName;

                if (nodeName.indexOf('data-component') > -1) {
                    componentName = nodeName.replace('data-component-', '');
                    return [componentName, 'component'];
                }

                if (nodeName.indexOf('data-v-component-') > -1) {
                    componentName = nodeName.replace('data-v-component-', '');
                    return [componentName, 'component'];
                }

                if (nodeName.indexOf('data-v-') > -1) {
                    componentAttribute =
                        (componentAttribute ? componentAttribute + ' - ' : '') + nodeName.replace('data-v-', '') + ' ';
                }
            }
        }

        if (componentAttribute != '') return [componentAttribute, 'attribute'];

        if (el.id) {
            componentName = '#' + el.id;
        } else {
            componentName = el.classList && el.classList.length ? '.' + el.classList[0] : '';
        }

        return [componentName, el.tagName];
    },

    /**
     * Load component panel for the selected node
     */
    loadNodeComponent: function (node) {
        const data = Astero.Components.matchNode(node);
        let component;

        if (data) component = data.type;
        else component = Astero.defaultComponent;

        Astero.component = Astero.Components.get(component);
        Astero.Components.render(component);
        this.selectedComponent = component;

        // Show properties tab if visible
        let propertiesTab = document.querySelector('.component-properties-tab a');
        if (propertiesTab.offsetParent) {
            propertiesTab.style.display = '';
            const bsTab = bootstrap.Tab.getOrCreateInstance(propertiesTab);
            bsTab.show();
        }
    },

    /**
     * Reload current component panel
     */
    reloadComponent: function () {
        Astero.Components.render(this.selectedComponent);
    },

    /**
     * Move selected node up
     */
    moveNodeUp: function (node) {
        if (!node) node = Astero.Builder.selectedEl;

        const oldParent = node.parentNode;
        const oldNextSibling = node.nextSibling;
        const next = node.previousElementSibling;

        if (!next) return;

        next.before(node);

        Astero.Builder.selectNode(node);

        Astero.Undo.addMutation({
            type: 'move',
            target: node,
            oldParent: oldParent,
            newParent: node.parentNode,
            oldNextSibling: oldNextSibling,
            newNextSibling: node.nextSibling,
        });
    },

    /**
     * Move selected node down
     */
    moveNodeDown: function (node) {
        if (!node) node = Astero.Builder.selectedEl;

        const oldParent = node.parentNode;
        const oldNextSibling = node.nextSibling;
        const next = node.nextElementSibling;

        if (!next) return;

        next.after(node);

        Astero.Builder.selectNode(node);

        Astero.Undo.addMutation({
            type: 'move',
            target: node,
            oldParent: oldParent,
            newParent: node.parentNode,
            oldNextSibling: oldNextSibling,
            newNextSibling: node.nextSibling,
        });
    },

    /**
     * Clone selected node
     */
    cloneNode: function (node) {
        if (!node) node = Astero.Builder.selectedEl;

        const clone = node.cloneNode(true);
        node.after(clone);
        node.click();

        Astero.Undo.addMutation({
            type: 'childList',
            target: node.parentNode,
            addedNodes: [clone],
            nextSibling: node.nextSibling,
        });
    },

    /**
     * Select a node in the builder
     */
    selectNode: function (node) {
        let SelectBox = document.getElementById('select-box');

        if (!node) {
            SelectBox.style.display = 'none';
            return;
        }

        let self = this;
        let SelectActions = document.getElementById('select-actions');
        let AddSectionBtn = document.getElementById('add-section-btn');
        let AddSectionBtnSelected = document.getElementById('add-section-btn-selected');
        let elementType = this._getElementType(node);

        // Close text editor if switching nodes
        if (self.texteditEl && self.selectedEl != node) {
            Astero.WysiwygEditor.destroy(self.texteditEl);
            self.selectPadding = 0;
            SelectBox.classList.remove('text-edit');
            SelectActions.style.display = '';
            self.texteditEl = null;
        }

        // Hide actions for body element
        if (elementType[1] == 'BODY') {
            SelectActions.style.display = 'none';
            AddSectionBtn.style.display = 'none';
            if (AddSectionBtnSelected) AddSectionBtnSelected.style.display = 'none';
        } else {
            SelectActions.style.display = '';
            AddSectionBtn.style.display = '';
            if (AddSectionBtnSelected) AddSectionBtnSelected.style.display = '';
        }

        let target = node;
        self.selectedEl = target;

        try {
            let pos = offset(target);
            let top = pos.top - (self.frameDoc.scrollTop ?? 0) - self.selectPadding;

            SelectBox.style.top = top + 'px';
            SelectBox.style.left = pos.left - (self.frameDoc.scrollLeft ?? 0) - self.selectPadding + 'px';
            SelectBox.style.width = (target.offsetWidth ?? target.clientWidth) + self.selectPadding * 2 + 'px';
            SelectBox.style.height = (target.offsetHeight ?? target.clientHeight) + self.selectPadding * 2 + 'px';
            SelectBox.style.display = 'block';

            // Move actions toolbar to bottom if no space on top
            if (top < 30) {
                SelectActions.style.top = 'unset';
                SelectActions.style.bottom = '-25px';
            } else {
                SelectActions.style.top = '';
                SelectActions.style.bottom = '';
            }

            Astero.Breadcrumb.loadBreadcrumb(target);
        } catch (err) {
            console.log(err);
            return false;
        }

        document.querySelector('#highlight-name .type').innerHTML = elementType[0];
        document.querySelector('#highlight-name .name').innerHTML = elementType[1];
    },
};
