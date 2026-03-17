Astero.WysiwygEditor = {
    isActive: false,
    oldValue: '',
    doc: false,

    editorSetStyle: function (tag, style = {}, toggle = false) {
        let iframeWindow = Astero.Builder.iframe.contentWindow;
        let selection = iframeWindow.getSelection();
        let element = this.element;
        let range;

        if (!tag) {
            tag = 'span';
        }

        if (selection.rangeCount > 0) {
            //check if the whole text is inside an existing node to use the node directly
            if (
                (selection.baseNode &&
                    selection.baseNode.nextSibling == null &&
                    selection.baseNode.previousSibling == null &&
                    selection.anchorOffset == 0 &&
                    selection.focusOffset == selection.baseNode.length) ||
                selection.anchorOffset == selection.focusOffset
            ) {
                element = selection.baseNode.parentNode;
            } else {
                element = iframeWindow.document.createElement(tag);
                range = selection.getRangeAt(0);
                range.surroundContents(element);
                range.selectNodeContents(element.childNodes[0], 0);
            }
        }

        if (element && style) {
            for (const name in style) {
                if (
                    !style[name] ||
                    (toggle && element.style.getPropertyValue(name))
                ) {
                    element.style.removeProperty(name);
                } else {
                    element.style.setProperty(name, style[name]);
                }
            }
        }

        //if edited text is an empty span remove the span
        if (
            element.tagName == 'SPAN' &&
            element.style.length == 0 &&
            element.attributes.length <= 1
        ) {
            let textNode = iframeWindow.document.createTextNode(
                element.innerText,
            );
            element.replaceWith(textNode);
            element = textNode;

            range = iframeWindow.document.createRange();
            range.selectNodeContents(element);
            selection.removeAllRanges();
            selection.addRange(range);
        }

        //select link element to edit link etc
        if (tag == 'a') {
            Astero.Builder.selectNode(element);
            Astero.Builder.loadNodeComponent(element);
        }
        return element;
    },

    init: function (doc) {
        if (!doc) {
            return;
        }

        this.doc = doc;
        let self = this;

        const bind = (id, eventName, handler) => {
            const el = document.getElementById(id);
            if (!el) return null;
            el.addEventListener(eventName, handler);
            return el;
        };

        bind('bold-btn', 'click', function (e) {
            //doc.execCommand('bold',false,null);
            //self.editorSetStyle("b", {"font-weight" : "bold"}, true);
            self.editorSetStyle(false, { 'font-weight': 'bold' }, true);
            e.preventDefault();
            return false;
        });

        bind('italic-btn', 'click', function (e) {
            //doc.execCommand('italic',false,null);
            //self.editorSetStyle("i", {"font-style" : "italic"}, true);
            self.editorSetStyle(false, { 'font-style': 'italic' }, true);
            e.preventDefault();
            return false;
        });

        bind('underline-btn', 'click', function (e) {
            //doc.execCommand('underline',false,null);
            //self.editorSetStyle("u", {"text-decoration" : "underline"}, true);
            self.editorSetStyle(
                false,
                { 'text-decoration': 'underline' },
                true,
            );
            e.preventDefault();
            return false;
        });

        bind('strike-btn', 'click', function (e) {
            //doc.execCommand('strikeThrough',false,null);
            //self.editorSetStyle("strike",  {"text-decoration" : "line-through"}, true);
            self.editorSetStyle(
                false,
                { 'text-decoration': 'line-through' },
                true,
            );
            e.preventDefault();
            return false;
        });

        bind('link-btn', 'click', function (e) {
            //doc.execCommand('createLink',false,"#");
            self.editorSetStyle('a');
            e.preventDefault();
            return false;
        });

        bind('fore-color', 'change', function (e) {
            //doc.execCommand('foreColor',false,this.value);
            self.editorSetStyle(false, { color: this.value });
            e.preventDefault();
            return false;
        });

        bind('back-color', 'change', function (e) {
            //doc.execCommand('hiliteColor',false,this.value);
            self.editorSetStyle(false, { 'background-color': this.value });
            e.preventDefault();
            return false;
        });

        const fontSize = bind('font-size', 'change', function (e) {
            //doc.execCommand('fontSize',false,this.value);
            self.editorSetStyle(false, { 'font-size': this.value });
            e.preventDefault();
            return false;
        });

        if (fontSize) {
            let sizes = "<option value=''> - Font size - </option>";
            for (let i = 1; i <= 128; i++) {
                sizes += "<option value='" + i + "px'>" + i + '</option>';
            }
            fontSize.innerHTML = sizes;
        }

        bind('font-family', 'change', function (e) {
            let option = this.options[this.selectedIndex];
            let element = self.editorSetStyle(false, {
                'font-family': this.value,
            });
            Astero.FontsManager.addFont(
                option?.dataset?.provider,
                this.value,
                element,
            );
            //doc.execCommand('fontName',false,this.value);
            e.preventDefault();
            return false;
        });

        bind('justify-btn', 'click', function (e) {
            //let command = "justify" + this.dataset.value;
            //doc.execCommand(command,false,"#");
            const value =
                e.currentTarget?.dataset?.value || e.target?.dataset?.value;
            if (value) {
                self.editorSetStyle(false, { 'text-align': value });
            }
            e.preventDefault();
            return false;
        });

        doc.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                let target = event.target.closest('[contenteditable]');
                if (target) {
                    doc.execCommand('insertLineBreak');
                    event.preventDefault();
                }
            }
        });
    },

    undo: function (element) {
        this.doc.execCommand('undo', false, null);
    },

    redo: function (element) {
        this.doc.execCommand('redo', false, null);
    },

    edit: function (element) {
        if (!element) return;
        element.setAttribute('contenteditable', true);
        element.setAttribute('spellcheck', 'false');
        element.removeAttribute('spellcheckker');
        const wysiwyg = document.getElementById('wysiwyg-editor');
        if (wysiwyg) {
            wysiwyg.style.display = 'flex';
        }

        this.element = element;
        this.isActive = true;
        this.oldValue = element.innerHTML;

        const toHexColor = (value) => {
            if (!value) return '';
            const trimmed = String(value).trim();
            if (!trimmed) return '';
            const lowered = trimmed.toLowerCase();
            if (
                ['transparent', 'inherit', 'initial', 'unset'].includes(lowered)
            )
                return '';
            if (
                typeof ColorInput !== 'undefined' &&
                typeof ColorInput.rgb2hex === 'function'
            ) {
                return ColorInput.rgb2hex(trimmed);
            }
            const rgb = trimmed.match(
                /^rgba?[\s+]?\([\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?/i,
            );
            if (rgb && rgb.length === 4) {
                return (
                    '#' +
                    ('0' + parseInt(rgb[1], 10).toString(16)).slice(-2) +
                    ('0' + parseInt(rgb[2], 10).toString(16)).slice(-2) +
                    ('0' + parseInt(rgb[3], 10).toString(16)).slice(-2)
                );
            }
            return trimmed;
        };

        const fontFamily = document.getElementById('font-family');
        if (fontFamily) {
            fontFamily.value = Astero.StyleManager.getStyle(
                element,
                'font-family',
            );
        }
        const foreColor = document.getElementById('fore-color');
        if (foreColor) {
            // Color inputs only accept hex.
            foreColor.value = toHexColor(
                Astero.StyleManager.getStyle(element, 'color'),
            );
        }
        const backColor = document.getElementById('back-color');
        if (backColor) {
            // Color inputs only accept hex.
            backColor.value = toHexColor(
                Astero.StyleManager.getStyle(element, 'background-color'),
            );
        }
        element.focus();
    },

    destroy: function (element) {
        if (!element) return;
        element.removeAttribute('contenteditable');
        element.removeAttribute('spellcheck');

        const wysiwyg = document.getElementById('wysiwyg-editor');
        if (wysiwyg) {
            wysiwyg.style.display = 'none';
        }
        this.isActive = false;

        let node = this.element;
        if (node) {
            Astero.Undo.addMutation({
                type: 'characterData',
                target: node,
                oldValue: this.oldValue,
                newValue: node.innerHTML,
            });
        }
    },
};

Astero.ModalCodeEditor = {
    modal: false,
    editor: false,

    init: function (modal = false, editor = false) {
        if (modal) {
            this.modal = modal;
        } else {
            this.modal = document.getElementById('codeEditorModal');
        }
        if (!this.modal) {
            return false;
        }
        if (editor) {
            this.editor = editor;
        } else {
            this.editor = this.modal.querySelector('textarea');
        }
        if (!this.editor) {
            return false;
        }

        let self = this;

        this.modal
            .querySelector('.save-btn')
            .addEventListener('click', function (event) {
                window.dispatchEvent(
                    new CustomEvent('astero.ModalCodeEditor.save', {
                        detail: self.getValue(),
                    }),
                );
                self.hide();
                return false;
            });
    },

    show: function (value) {
        if (!this.modal) {
            this.init();
        }
        if (!this.modal) {
            return;
        }

        const bsModal = bootstrap.Modal.getOrCreateInstance(this.modal);
        return bsModal.show();
    },

    hide: function (value) {
        if (!this.modal) {
            return;
        }
        const bsModal = bootstrap.Modal.getOrCreateInstance(this.modal);
        return bsModal.hide();
    },

    getValue: function () {
        return this.editor.value;
    },

    setValue: function (value) {
        if (!this.modal) {
            this.init();
        }
        //enable save button
        document
            .querySelectorAll('#top-panel .save-btn')
            .forEach((e) => e.removeAttribute('disabled'));
        this.editor.value = value;
        if (this.editor.monacoEditor) {
            this.editor.monacoEditor.setValue(value);
        }
    },
};

Astero.CodeEditor = {
    isActive: false,
    oldValue: '',
    doc: false,
    textarea: false,

    init: function (doc) {
        this.textarea = document.querySelector('#astero-code-editor textarea');
        if (!this.textarea) {
            this.isActive = false;
            return;
        }
        let html = Astero.Builder.getHtml();
        this.textarea.value = html;
        if (this.textarea.monacoEditor) {
            this.textarea.monacoEditor.setValue(html);
        }

        this.textarea.addEventListener('input', () => {
            const value = this.textarea.value;
            delay(() => Astero.Builder.setHtml(value), 500);
        });

        //load code on document changes
        Astero.Builder.frameBody?.addEventListener('astero.undo.add', () =>
            Astero.CodeEditor.setValue(),
        );
        Astero.Builder.frameBody?.addEventListener('astero.undo.restore', () =>
            Astero.CodeEditor.setValue(),
        );

        //load code when a new url is loaded
        Astero.Builder.documentFrame?.addEventListener('load', () =>
            Astero.CodeEditor.setValue(),
        );

        this.isActive = true;
    },

    setValue: function (value) {
        if (this.isActive && this.textarea) {
            let html = Astero.Builder.getHtml();
            this.textarea.value = html;
            if (this.textarea.monacoEditor) {
                this.textarea.monacoEditor.setValue(html);
            }
        }
    },

    destroy: function (element) {
        //this.isActive = false;
    },

    toggle: function () {
        if (this.isActive != true) {
            this.isActive = true;
            return this.init();
        }
        this.isActive = false;
        this.destroy();
    },
};

Astero.CssEditor = {
    isActive: false,
    oldValue: '',
    doc: false,
    textarea: false,

    init: function (doc) {
        if (this.isActive && this.textarea) {
            // Already initialized, just load current CSS
            this.setValue(Astero.StyleManager.getCss());
            return;
        }

        this.textarea = document.getElementById('css-editor');
        if (!this.textarea) {
            console.error('CSS editor textarea not found');
            return;
        }

        // Load current CSS content
        let currentCss = Astero.StyleManager.getCss();
        this.textarea.value = currentCss;
        if (this.textarea.monacoEditor) {
            this.textarea.monacoEditor.setValue(currentCss);
        }
        let self = this;

        // Listen to Monaco's change event
        this.textarea.addEventListener('monaco:change', (e) => {
            delay(() => {
                const value = e.detail?.value || self.textarea.value;
                Astero.StyleManager.setCss(value);
                document
                    .querySelectorAll('#top-panel .save-btn')
                    .forEach((e) => e.removeAttribute('disabled'));
            }, 500);
        });

        // Fallback for textarea without Monaco
        this.textarea.addEventListener('input', () => {
            if (!self.textarea.monacoEditor) {
                delay(() => {
                    Astero.StyleManager.setCss(self.textarea.value);
                    document
                        .querySelectorAll('#top-panel .save-btn')
                        .forEach((e) => e.removeAttribute('disabled'));
                }, 500);
            }
        });

        this.isActive = true;
    },

    getValue: function () {
        return this.textarea.value;
    },

    setValue: function (value) {
        if (this.textarea) {
            this.textarea.value = value || '';
            if (this.textarea.monacoEditor) {
                this.textarea.monacoEditor.setValue(this.textarea.value);
            }
        }
    },

    destroy: function () {
        this.isActive = false;
        // Also remove active class
        let cssEditorBtn = document.getElementById('css-editor-btn');
        if (cssEditorBtn) {
            cssEditorBtn.classList.remove('active');
        }
    },

    toggle: function () {
        if (this.isActive != true) {
            this.isActive = true;
            return this.init();
        }
        this.isActive = false;
        this.destroy();
    },

    refresh: function () {
        if (this.isActive && this.textarea) {
            let currentCss = Astero.StyleManager.getCss();
            console.log(
                'CSS Editor (basic): Refreshing CSS content:',
                currentCss ? currentCss.length + ' characters' : 'empty',
            );
            this.setValue(currentCss);
        }
    },
};

Astero.EnabledContentEditor = {
    isActive: false,
    oldValue: '',
    doc: false,
    textarea: false,

    init: function (doc) {
        console.log('EnabledContentEditor basic init called');
        this.textarea = document.querySelector(
            '#astero-enabled-content-editor textarea',
        );
        if (!this.textarea) {
            console.error(
                'EnabledContentEditor: textarea not found at #astero-enabled-content-editor textarea',
            );
            return false;
        }

        // Pre-format content before setting it
        let formattedContent = this.formatHtml(this.getEnabledContent());
        this.textarea.value = formattedContent;

        if (this.textarea.monacoEditor) {
            this.textarea.monacoEditor.setValue(formattedContent);
        }

        let self = this;

        // Listen to Monaco's change event (dispatched by monaco-loader)
        this.textarea.addEventListener('monaco:change', (e) => {
            delay(() => {
                const value = e.detail?.value || self.textarea.value;
                self.setEnabledContent(value);
            }, 500);
        });

        // Fallback for textarea without Monaco
        this.textarea.addEventListener('input', (e) => {
            if (!self.textarea.monacoEditor) {
                delay(() => self.setEnabledContent(self.textarea.value), 500);
            }
        });

        // Load content on document changes
        Astero.Builder.frameBody?.addEventListener('astero.undo.add', () =>
            self.setValue(),
        );
        Astero.Builder.frameBody?.addEventListener('astero.undo.restore', () =>
            self.setValue(),
        );

        // Load content when a new url is loaded
        Astero.Builder.documentFrame?.addEventListener('load', () =>
            self.setValue(),
        );

        this.isActive = true;
    },

    getEnabledContent: function () {
        let doc = window.FrameDocument;
        if (!doc) return '';

        // Find element with data-astero-enabled attribute
        let enabledElement = doc.querySelector('[data-astero-enabled]');
        if (!enabledElement) {
            return '<!-- No elements with data-astero-enabled attribute found -->';
        }

        return enabledElement.innerHTML;
    },

    /**
     * Simple HTML formatter - adds proper indentation to HTML
     */
    formatHtml: function (html) {
        if (!html) return '';

        // Remove existing whitespace between tags
        html = html.replace(/>\s+</g, '><').trim();

        let formatted = '';
        let indent = 0;
        const indentStr = '  '; // 2 spaces

        // Self-closing and inline tags that shouldn't add newlines
        const inlineTags = [
            'br',
            'hr',
            'img',
            'input',
            'meta',
            'link',
            'source',
            'track',
            'wbr',
        ];
        // const preserveInlineTags = ['span', 'a', 'strong', 'em', 'b', 'i', 'u', 'small', 'mark', 'del', 'ins', 'sub', 'sup'];

        // Split by tags while keeping the tags
        const parts = html
            .split(/(<[^>]+>)/g)
            .filter((part) => part.length > 0);

        for (let i = 0; i < parts.length; i++) {
            const part = parts[i].trim();
            if (!part) continue;

            if (part.startsWith('</')) {
                // Closing tag - decrease indent first
                indent = Math.max(0, indent - 1);
                formatted += indentStr.repeat(indent) + part + '\n';
            } else if (part.startsWith('<')) {
                // Opening tag or self-closing
                const tagMatch = part.match(/^<(\/?)?(\w+)/);
                const tagName = tagMatch ? tagMatch[2].toLowerCase() : '';
                const isSelfClosing =
                    part.endsWith('/>') || inlineTags.includes(tagName);

                formatted += indentStr.repeat(indent) + part + '\n';

                // Increase indent for opening tags (not self-closing)
                if (!isSelfClosing && !part.startsWith('<!')) {
                    indent++;
                }
            } else {
                // Text content
                if (part.trim()) {
                    formatted += indentStr.repeat(indent) + part.trim() + '\n';
                }
            }
        }

        return formatted.trim();
    },

    setEnabledContent: function (htmlContent) {
        let doc = window.FrameDocument;
        if (!doc) return;

        try {
            // Find element with data-astero-enabled in the original document
            let enabledElements = doc.querySelector('[data-astero-enabled]');

            if (!enabledElements) {
                console.warn('No enabled element found to update');
                return;
            }

            // Update each enabled element with its corresponding content
            enabledElements.innerHTML = htmlContent;

            // Enable save button
            document
                .querySelectorAll('#top-panel .save-btn')
                .forEach((e) => e.removeAttribute('disabled'));

            // Trigger change event
            window.dispatchEvent(
                new CustomEvent('astero.EnabledContentEditor.change', {
                    detail: htmlContent,
                }),
            );
        } catch (error) {
            console.error('EnabledContentEditor: Error setting content', error);
        }
    },

    setValue: function (value) {
        if (this.isActive && this.textarea) {
            let content = value || this.getEnabledContent();
            this.textarea.value = this.formatHtml(content);
            if (this.textarea.monacoEditor) {
                this.textarea.monacoEditor.setValue(this.textarea.value);
            }
        }
    },

    getValue: function () {
        return this.textarea ? this.textarea.value : '';
    },

    destroy: function () {
        this.isActive = false;
        // Also hide the UI and remove active class
        let htmlEditor = document.getElementById(
            'astero-enabled-content-editor',
        );
        let htmlEditorBtn = document.getElementById('html-editor-btn');
        if (htmlEditor) {
            htmlEditor.style.display = 'none';
        }
        if (htmlEditorBtn) {
            htmlEditorBtn.classList.remove('active');
        }
    },

    toggle: function () {
        console.log(
            'EnabledContentEditor.toggle called, isActive:',
            this.isActive,
        );
        if (this.isActive !== true) {
            this.isActive = true;
            return this.init();
        }
        this.isActive = false;
        this.destroy();
    },

    // Helper method to get a preview of enabled areas without full HTML
    getEnabledAreasInfo: function () {
        let doc = window.FrameDocument;
        if (!doc) return [];

        let enabledElements = doc.querySelector('[data-astero-enabled]');
        if (!enabledElements) return [];
        let info = [];

        info.push({
            tagName: enabledElements.tagName.toLowerCase(),
            className: enabledElements.className || '',
            id: enabledElements.id || '',
            childrenCount: enabledElements.children.length,
            textLength: enabledElements.textContent.trim().length,
        });

        return info;
    },

    // Method to highlight enabled areas in the iframe
    highlightEnabledAreas: function (highlight = true) {
        let doc = window.FrameDocument;
        if (!doc) return;

        let enabledElements = doc.querySelector('[data-astero-enabled]');
        if (!enabledElements) return;
        if (highlight) {
            enabledElements.style.outline = '2px dashed #007bff';
            enabledElements.style.outlineOffset = '2px';
            enabledElements.setAttribute('data-astero-highlighted', 'true');
        } else {
            enabledElements.style.outline = '';
            enabledElements.style.outlineOffset = '';
            enabledElements.removeAttribute('data-astero-highlighted');
        }
    },
};

Astero.JsEditor = {
    isActive: false,
    oldValue: '',
    doc: false,
    textarea: false,

    init: function (doc) {
        // console.log("JS Editor (basic): Initializing...");
        this.textarea = document.getElementById('js-editor');
        // console.log("JS Editor (basic): Textarea found:", this.textarea ? "Yes" : "No");
        if (!this.textarea) {
            console.error('JavaScript editor textarea not found');
            return;
        }

        // Load current JS content
        let currentJs = Astero.ScriptManager.getJs();
        // console.log("JS Editor (basic): Loading JS content:", currentJs ? currentJs.length + " characters" : "empty");
        this.textarea.value = currentJs;
        if (this.textarea.monacoEditor) {
            this.textarea.monacoEditor.setValue(currentJs);
        }

        let self = this;

        // Listen to Monaco's change event
        this.textarea.addEventListener('monaco:change', (e) => {
            delay(() => {
                const value = e.detail?.value || self.textarea.value;
                Astero.ScriptManager.setJs(value);
                document
                    .querySelectorAll('#top-panel .save-btn')
                    .forEach((e) => e.removeAttribute('disabled'));
            }, 500);
        });

        // Fallback for textarea without Monaco
        this.textarea.addEventListener('input', () => {
            if (!self.textarea.monacoEditor) {
                delay(() => {
                    Astero.ScriptManager.setJs(self.textarea.value);
                    document
                        .querySelectorAll('#top-panel .save-btn')
                        .forEach((e) => e.removeAttribute('disabled'));
                }, 500);
            }
        });

        this.isActive = true;
    },

    getValue: function () {
        return this.textarea.value;
    },

    setValue: function (value) {
        // console.log("JsEditor: editor Setting value", value);
        if (this.textarea) {
            this.textarea.value = value || '';
            if (this.textarea.monacoEditor) {
                this.textarea.monacoEditor.setValue(this.textarea.value);
            }
        }
        // Update Astero.ScriptManager.jsContainer
        if (Astero.ScriptManager.jsContainer) {
            Astero.ScriptManager.jsContainer.innerHTML =
                value || this.getValue();
        }
        // Update scriptDiv if it exists
        let scriptDiv = this.doc
            ? this.doc.querySelector('div[id="pagebuilder-scripts"]')
            : null;
        if (scriptDiv) {
            // console.log("JsEditor: Updating scriptDiv content");
            scriptDiv.innerHTML = value || this.getValue();
        }
    },

    destroy: function () {
        this.isActive = false;
        // Also remove active class
        let jsEditorBtn = document.getElementById('js-editor-btn');
        if (jsEditorBtn) {
            jsEditorBtn.classList.remove('active');
        }
    },

    toggle: function () {
        if (this.isActive != true) {
            this.isActive = true;
            return this.init();
        }
        this.isActive = false;
        this.destroy();
    },

    refresh: function () {
        if (this.isActive && this.textarea) {
            let currentJs = Astero.ScriptManager.getJs();
            console.log(
                'JS Editor (basic): Refreshing JS content:',
                currentJs ? currentJs.length + ' characters' : 'empty',
            );
            this.setValue(currentJs);
        }
    },
};
