/**
 * AsteroNote Builder Integration
 *
 * Provides inline text editing using AsteroNote Inline for the page builder.
 * This module creates a lightweight wrapper that integrates with the builder's
 * existing iframe-based editing system.
 */

import { createInlineEditor } from '../../../../../../resources/js/asteronote/asteronote-inline.js';
import '../../../../../../resources/css/asteronote-inline.css';

/**
 * Initialize AsteroNote Inline editor for the builder
 * This replaces the manual toolbar binding with AsteroNote's plugin system
 */
export function initBuilderInlineEditor() {
    const Astero = window.Astero || (window.Astero = {});
    const iframeEl =
        Astero.Builder?.iframe ||
        document.querySelector('#iframe-wrapper > iframe');

    // Create the inline editor instance
    const inlineEditor = createInlineEditor({
        // Custom toolbar for builder context
        toolbar: [
            'bold',
            'italic',
            'underline',
            'strikethrough',
            'separator',
            'align',
            'separator',
            'link',
            'separator',
            'foreColor',
            'backColor',
            'fontSize',
        ],
        // IMPORTANT: `#iframe-layer` has `pointer-events: none` in builder CSS.
        // Mount toolbar in document body so it can receive clicks.
        toolbarContainer: document.body,
        toolbarGap: 10,
        context: 'iframe',
        iframe: iframeEl,
    });

    // Store reference on Astero namespace
    Astero.InlineEditor = inlineEditor;

    console.log('[Builder] AsteroNote Inline editor initialized');

    return inlineEditor;
}

/**
 * Adapter class that bridges the builder's WysiwygEditor interface
 * with AsteroNote Inline editor
 */
export class BuilderInlineEditorAdapter {
    constructor(options = {}) {
        this.inlineEditor = null;
        this.isActive = false;
        this.element = null;
        this.oldValue = '';
        this.doc = null;
        this.options = options;
    }

    /**
     * Initialize the adapter with iframe document
     * @param {Document} doc - The iframe document
     */
    init(doc) {
        const Astero = window.Astero || (window.Astero = {});
        const iframeEl =
            this.options.iframe ||
            Astero.Builder?.iframe ||
            document.querySelector('#iframe-wrapper > iframe');

        this.doc = doc;

        // Initialize AsteroNote Inline if not already done
        if (!this.inlineEditor) {
            this.inlineEditor = createInlineEditor({
                toolbar: [
                    'bold',
                    'italic',
                    'underline',
                    'strikethrough',
                    'separator',
                    'align',
                    'separator',
                    'link',
                    'separator',
                    'foreColor',
                    'backColor',
                    'fontSize',
                ],
                // IMPORTANT: `#iframe-layer` has `pointer-events: none` in builder CSS.
                toolbarContainer: document.body,
                toolbarGap: 10,
                context: 'iframe',
                iframe: iframeEl,
                callbacks: {
                    onChange: (content) => {
                        // Enable save button when content changes
                        document
                            .querySelectorAll('#top-panel .save-btn')
                            .forEach((e) => e.removeAttribute('disabled'));
                    },
                },
            });

            // Listen for change events to integrate with Undo system
            this.inlineEditor.on('asteronote.inline.change', (data) => {
                if (data.element && Astero.Undo) {
                    Astero.Undo.addMutation({
                        type: 'characterData',
                        target: data.element,
                        oldValue: data.oldValue,
                        newValue: data.newValue,
                    });
                }
            });
        }

        // Handle Enter key within iframe for line breaks
        if (doc) {
            doc.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    let target = event.target.closest('[contenteditable]');
                    if (target) {
                        doc.execCommand('insertLineBreak');
                        event.preventDefault();
                    }
                }
            });
        }
    }

    /**
     * Start editing an element
     * @param {HTMLElement} element - Element to edit
     */
    edit(element) {
        if (!element) return;

        this.element = element;
        this.oldValue = element.innerHTML;
        this.isActive = true;

        // Use AsteroNote Inline to attach to element
        if (this.inlineEditor) {
            this.inlineEditor.attach(element);
        }
    }

    /**
     * Stop editing
     * @param {HTMLElement} element - Element to stop editing
     */
    destroy(element) {
        if (!element && this.element) {
            element = this.element;
        }

        if (!element) return;

        // Use AsteroNote Inline to detach
        if (this.inlineEditor) {
            this.inlineEditor.detach();
        }

        this.isActive = false;
        this.element = null;
    }
}

// ------------------------------------------------------------
// Replace legacy builder WYSIWYG with AsteroNote Inline
// ------------------------------------------------------------
(() => {
    const Astero = window.Astero || (window.Astero = {});
    if (Astero.__asteronoteInlineWysiwygInstalled) return;
    Astero.__asteronoteInlineWysiwygInstalled = true;

    const adapter = new BuilderInlineEditorAdapter();

    Astero.WysiwygEditor = {
        isActive: false,
        oldValue: '',
        doc: false,
        init: function (doc) {
            this.doc = doc;
            adapter.init(doc);
            return this;
        },
        edit: function (element) {
            adapter.edit(element);
            this.isActive = adapter.isActive;
            return element;
        },
        destroy: function (element) {
            adapter.destroy(element);
            this.isActive = adapter.isActive;
        },
        undo: function () {
            if (this.doc && typeof this.doc.execCommand === 'function') {
                this.doc.execCommand('undo', false, null);
            }
        },
        redo: function () {
            if (this.doc && typeof this.doc.execCommand === 'function') {
                this.doc.execCommand('redo', false, null);
            }
        },
    };
})();

// Export for use in builder
export default BuilderInlineEditorAdapter;
