/**
 * Astero Builder - Selection & Highlight System
 *
 * Handles element highlighting and selection in the iframe:
 * - _initHighlight
 * - Highlight move/up/click/dblclick handlers
 */

// Extend Astero.Builder with selection operations
Object.assign(Astero.Builder, {
    /**
     * Initialize highlight system for element selection
     */
    _initHighlight: function () {
        let self = Astero.Builder;

        // Mouse move handler for highlighting
        let highlightMove = function (event) {
            if (
                self.highlightEnabled == true &&
                event.target &&
                isElement(event.target)
            ) {
                // Opt-in editing model: only highlight elements within data-astero-enabled areas
                if (!event.target.closest('[data-astero-enabled]')) {
                    document.getElementById('highlight-box').style.display =
                        'none';
                    return;
                }

                let target = event.target;
                self.highlightEl = target;
                let pos = offset(target);
                let height = target.offsetHeight;
                let width = target.offsetWidth;

                self._handleHighlightMove(event, pos, width, height);
            }
        };

        self.frameBody.addEventListener('mousemove', highlightMove);

        // Mouse up handler
        let highlightUp = function (event) {
            document
                .querySelectorAll('#section-actions, #highlight-name')
                .forEach((el) => (el.style.display = ''));
        };

        self.frameBody.addEventListener('mouseup', highlightUp);

        // Double click handler for text editing
        let highlightDbClick = function (event) {
            if (!Astero.WysiwygEditor.isActive) {
                self._handleTextEdit(event);
            }
        };

        self.frameBody.addEventListener('dblclick', highlightDbClick);

        // Click handler for element selection
        let highlightClick = function (event) {
            self._handleElementClick(event);
        };

        self.frameBody.addEventListener('click', highlightClick);
    },

    /**
     * Handle normal highlight move (no drag/resize)
     */
    _handleHighlightMove: function (event, pos, width, height) {
        let self = this;

        if (
            Astero.WysiwygEditor.isActive &&
            self.texteditEl.contains(event.target)
        ) {
            return true;
        }

        let highlightBox = document.getElementById('highlight-box');

        highlightBox.setAttribute(
            'style',
            `top:${pos.top - (self.frameDoc.scrollTop ?? 0)}px;
            left:${pos.left - (self.frameDoc.scrollLeft ?? 0)}px;
            width:${width}px;
            height:${height}px;
            display:${event.target.hasAttribute('contenteditable') ? 'none' : 'block'};`,
        );

        if (height < 50) {
            document.getElementById('section-actions').classList.add('outside');
        } else {
            document
                .getElementById('section-actions')
                .classList.remove('outside');
        }

        let elementType = self._getElementType(event.target);
        document.querySelector('#highlight-name .type').innerHTML =
            elementType[0];
        document.querySelector('#highlight-name .name').innerHTML =
            elementType[1];
    },

    /**
     * Handle text editing on double click
     */
    _handleTextEdit: function (event) {
        let self = this;
        self.selectPadding = 10;
        self.texteditEl = event.target;

        Astero.WysiwygEditor.edit(self.texteditEl);

        let _updateSelectBox = function () {
            if (!self.texteditEl || !self.selectedEl) return;
            let pos = offset(self.selectedEl);
            let SelectBox = document.getElementById('select-box');

            SelectBox.style.top =
                pos.top -
                (self.frameDoc.scrollTop ?? 0) -
                self.selectPadding +
                'px';
            SelectBox.style.left =
                pos.left -
                (self.frameDoc.scrollLeft ?? 0) -
                self.selectPadding +
                'px';
            SelectBox.style.width =
                self.texteditEl.offsetWidth + self.selectPadding * 2 + 'px';
            SelectBox.style.height =
                self.texteditEl.offsetHeight + self.selectPadding * 2 + 'px';
            SelectBox.style.display = 'block';
        };

        // Remove previous listeners if any
        if (self._textEditCleanup) {
            self._textEditCleanup();
        }

        const el = self.texteditEl;
        el.addEventListener('blur', _updateSelectBox);
        el.addEventListener('keyup', _updateSelectBox);
        el.addEventListener('paste', _updateSelectBox);
        el.addEventListener('input', _updateSelectBox);

        // Store cleanup function to remove listeners later
        self._textEditCleanup = function () {
            el.removeEventListener('blur', _updateSelectBox);
            el.removeEventListener('keyup', _updateSelectBox);
            el.removeEventListener('paste', _updateSelectBox);
            el.removeEventListener('input', _updateSelectBox);
            self._textEditCleanup = null;
        };

        _updateSelectBox();

        document.getElementById('select-box').classList.add('text-edit');
        document.getElementById('select-actions').style.display = 'none';
        document.getElementById('highlight-box').style.display = 'none';
    },

    /**
     * Handle element click for selection
     */
    _handleElementClick: function (event) {
        let self = this;

        if (Astero.Builder.highlightEnabled == false) return;

        let element = event.target;

        // Handle WYSIWYG editor closure
        if (Astero.WysiwygEditor.isActive) {
            if (!self.texteditEl || !self.texteditEl.contains(event.target)) {
                if (self._textEditCleanup) self._textEditCleanup();
                Astero.WysiwygEditor.destroy(self.texteditEl);
                document
                    .getElementById('select-box')
                    .classList.remove('text-edit');
                document.getElementById('select-actions').style.display = '';
                document.getElementById('highlight-box').style.display = '';
                self.texteditEl = null;
            } else {
                return;
            }
        }

        // Opt-in editing model
        if (!element.closest('[data-astero-enabled]')) {
            if (self.selectedEl) {
                self.selectedEl.classList.remove('highlighted');
                self.selectedEl = null;
                if (Astero.ElementsPanel && Astero.ElementsPanel.clear) {
                    Astero.ElementsPanel.clear();
                }
                document.getElementById('select-box').style.display = 'none';
            }
            return;
        }

        if (element.hasAttribute('data-astero-no-highlight')) return;

        if (self.selectedEl) {
            self.selectedEl.classList.remove('highlighted');
            self.selectedEl = null;
        }

        if (element) {
            self.selectNode(element);
            Astero.TreeList.selectComponent(element);
            self.loadNodeComponent(element);

            if (document.getElementById('add-section-box')) {
                document.getElementById('add-section-box').style.display =
                    'none';
            }
            event.preventDefault();
            return false;
        }
    },
});
