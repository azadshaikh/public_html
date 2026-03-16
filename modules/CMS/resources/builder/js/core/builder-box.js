/**
 * Astero Builder - Action Box System
 *
 * Handles the selection box UI and action buttons:
 * - _initBox
 * - Resize handles
 * - Up/Down/Clone/Parent/Delete/Edit Code buttons
 * - Add section functionality
 */

// Extend Astero.Builder with box/action operations
Object.assign(Astero.Builder, {
    /**
     * Initialize selection box and action handlers
     */
    _initBox: function () {
        let self = Astero.Builder;

        // Move up button
        document.getElementById('up-btn')?.addEventListener('click', function (event) {
            event.preventDefault();
            self.moveNodeUp();
        });

        // Move down button
        document.getElementById('down-btn')?.addEventListener('click', function (event) {
            event.preventDefault();
            self.moveNodeDown();
        });

        // Clone button
        document.getElementById('clone-btn')?.addEventListener('click', function (event) {
            event.preventDefault();
            self.cloneNode();
        });

        // Parent button
        document.getElementById('parent-btn')?.addEventListener('click', function (event) {
            event.preventDefault();
            let node = self.selectedEl.parentElement;
            if (node) {
                self.selectNode(node);
                self.loadNodeComponent(node);
            }
        });

        // Delete button
        document.getElementById('delete-btn')?.addEventListener('click', async function (event) {
            event.preventDefault();

            // Determine element type for confirmation message
            let node = self.selectedEl;
            let elementType = 'element';
            if (node.tagName === 'SECTION') {
                elementType = 'section';
            } else if (node.classList.contains('row') || node.closest('.row')) {
                elementType = 'block';
            }

            // Show confirmation dialog
            const confirmed = await confirmDelete(elementType);
            if (!confirmed) {
                return;
            }

            document.getElementById('select-box').style.display = 'none';
            Astero.Undo.addMutation({
                type: 'childList',
                target: node.parentNode,
                removedNodes: [node],
                nextSibling: node.nextSibling,
            });
            node.remove();

            // Refresh the section list and component tree
            if (Astero.SectionList && Astero.SectionList.loadSections) {
                Astero.SectionList.loadSections();
            }
            if (Astero.TreeList && Astero.TreeList.loadComponents) {
                Astero.TreeList.loadComponents();
            }
        });

        // Edit code button
        document.getElementById('edit-code-btn')?.addEventListener('click', function (event) {
            event.preventDefault();
            self._openCodeEditor();
        });

        // Add section button
        self.bindAddSectionButton = function (btnId) {
            let addSectionElement = document.getElementById(btnId);
            addSectionElement?.addEventListener('click', function (event) {
                let addSectionModal = document.getElementById('add-section-modal');
                if (addSectionModal && typeof bootstrap !== 'undefined') {
                    // Show standard Bootstrap modal
                    bootstrap.Modal.getOrCreateInstance(addSectionModal).show();
                } else {
                    console.error('Bootstrap Modal is not available or #add-section-modal missing.');
                }

                event.stopImmediatePropagation();
                event.preventDefault(); // Ensure link doesn't navigate
                return false;
            });
        };

        self.bindAddSectionButton('add-section-btn');
        self.bindAddSectionButton('add-section-btn-selected');

        // Add section modal interaction
        document.getElementById('add-section-modal')?.addEventListener('click', async function (event) {
            // Handle clicking on list items (components, blocks, sections)
            let item = event.target.closest('li[data-type]');

            if (item) {
                event.preventDefault();
                event.stopPropagation(); // Stop bubbling immediately

                let type = item.getAttribute('data-type');
                let dragType = item.getAttribute('data-drag-type');
                let category = item.getAttribute('data-section');
                let html = '';

                try {
                    // Resolve HTML based on type
                    if (dragType === 'component') {
                        let component = Astero.Components.get(type);
                        if (component) html = component.html;
                    } else {
                        // Sections and Blocks from Registry
                        let cacheKey = (dragType === 'section' ? 'section:' : 'block:') + category;
                        let list = Astero.Registry.cache[cacheKey] || [];
                        let block = list.find((b) => b.slug === type || b.id === type);

                        // If not found in cache, force load the category as fallback
                        if (!block) {
                            await Astero.Registry.loadCategory(dragType, category);
                            list = Astero.Registry.cache[cacheKey] || [];
                            block = list.find((b) => b.slug === type || b.id === type);
                        }

                        if (block) {
                            html = await Astero.Registry.getBlockHtml(block);
                        }
                    }

                    if (!html) {
                        console.error('Could not resolve HTML for item:', type);
                        return;
                    }

                    // Determine insertion position and mode
                    let highlightEl = self.selectedEl || self.highlightEl;
                    let positionTarget = null;

                    // If it's a component, we might want to insert inside the selected element
                    // But if it's a block/section, we usually target the container/section
                    if (dragType === 'component') {
                        positionTarget = highlightEl;
                    } else {
                        // For sections/blocks, target the nearest container, section, header, or footer
                        positionTarget =
                            highlightEl?.tagName === 'SECTION' ||
                            highlightEl?.tagName === 'HEADER' ||
                            highlightEl?.tagName === 'FOOTER'
                                ? highlightEl
                                : highlightEl?.closest('section') ||
                                  highlightEl?.closest('header') ||
                                  highlightEl?.closest('footer') ||
                                  highlightEl?.closest('[data-astero-enabled]');
                    }

                    if (!positionTarget) {
                        // Fallback to appending to the main container if nothing selected
                        positionTarget =
                            self.frameDoc.body.querySelector('[data-astero-enabled]') || self.frameDoc.body;
                    }

                    let insertMode = 'after';
                    const radio = document.querySelector('input[name="add-section-insert-mode"]:checked');
                    if (radio) insertMode = radio.value;

                    // If target is the main container, force 'inside' (append) as 'after' might be outside valid area
                    if (positionTarget.hasAttribute('data-astero-enabled') || positionTarget === self.frameDoc.body) {
                        insertMode = 'inside';
                    }

                    // Create elements
                    let newElements = generateElements(html);
                    let newElement = newElements[0];

                    if (!newElement) return;

                    // If adding a SECTION from Registry, attach a human-friendly title so the
                    // builder's section navigator can display it (it prioritizes `title` / `data-section`).
                    if (dragType === 'section' && block && (block.name || block.title)) {
                        const sectionTitle = String(block.name || block.title).trim();

                        if (sectionTitle) {
                            if (!newElement.title) newElement.setAttribute('title', sectionTitle);
                            if (!newElement.dataset.section) newElement.setAttribute('data-section', sectionTitle);
                        }
                    }

                    // Perform Insertion
                    if (insertMode === 'inside') {
                        positionTarget.append(newElement);
                    } else {
                        // After
                        positionTarget.after(newElement);
                    }

                    // Initialize the component if applicable
                    let component = Astero.Components.matchNode(newElement);
                    if (component && component.init) {
                        component.init(newElement);
                    }

                    // Add to undo history
                    Astero.Undo.addMutation({
                        type: 'childList',
                        target: newElement.parentNode,
                        addedNodes: [newElement],
                        nextSibling: newElement.nextSibling,
                    });

                    // Close modal
                    const modalEl = document.getElementById('add-section-modal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    modal?.hide();

                    // Refresh lists and select the new element
                    if (Astero.TreeList && Astero.TreeList.loadComponents) Astero.TreeList.loadComponents();

                    // Select the new element
                    self.selectNode(newElement);
                    if (Astero.TreeList) Astero.TreeList.selectComponent(newElement);
                } catch (e) {
                    console.error('Error adding element:', e);
                }
                return;
            }

            // Legacy Action Support
            let target = event.target.closest('[data-action]');
            if (!target) return;

            let action = target.getAttribute('data-action');

            // Just handling the original logic if it ever fires
            if (action === 'add-before' || action === 'add-after') {
                let highlightEl = self.selectedEl || self.highlightEl;
                let positionTarget =
                    highlightEl.tagName === 'SECTION' ||
                    highlightEl.tagName === 'HEADER' ||
                    highlightEl.tagName === 'FOOTER'
                        ? highlightEl
                        : highlightEl.closest('section') ||
                          highlightEl.closest('header') ||
                          highlightEl.closest('footer') ||
                          highlightEl.closest('[data-astero-enabled]');

                if (!positionTarget) return;

                // Close the modal and show legacy panel? Or just rely on callback?
                // The legacy logic relied on 'sections' panel showing up.
                // We'll leave this as is just in case.

                const modalEl = document.getElementById('add-section-modal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                modal?.hide();
            }
        });
    },

    /**
     * Open code editor for selected element
     */
    _openCodeEditor: function () {
        let self = this;

        if (!self.selectedEl) return;

        let codeEditorEl = document.getElementById('astero-code-editor');
        let overlayEl = document.getElementById('astero-code-editor-overlay');

        // If the inline code editor elements don't exist, show a message
        if (!codeEditorEl || !overlayEl) {
            if (typeof displayToast !== 'undefined') {
                displayToast(
                    'bg-info',
                    'Code Editor',
                    'Use the Code Editor button in the bottom panel to edit HTML, CSS, and JavaScript.'
                );
            } else {
                alert('Use the Code Editor button in the bottom panel to edit HTML, CSS, and JavaScript.');
            }
            return;
        }

        let html = self.selectedEl.innerHTML;

        codeEditorEl.style.display = 'block';
        overlayEl.style.display = 'block';
        document.getElementById('highlight-box').style.display = 'none';
        document.getElementById('select-box').style.display = 'none';

        if (self.isCodeEditorReady) {
            self.codeEditor.setValue(html);
        } else {
            let textArea = document.getElementById('code-textarea');
            textArea.value = html;

            require.config({
                paths: { vs: window.assetUrl + '/libs/monaco-editor/min/vs' },
            });
            require(['vs/editor/editor.main'], function () {
                self.codeEditor = monaco.editor.create(document.getElementById('monaco-editor'), {
                    value: html,
                    language: 'html',
                    theme: 'vs-dark',
                    wordWrap: 'on',
                    lineNumbers: 'on',
                    automaticLayout: true,
                    minimap: { enabled: true },
                });
                self.isCodeEditorReady = true;
                textArea.style.display = 'none';
            });
        }

        self._initCodeEditorEvents();
    },

    /**
     * Initialize code editor event handlers
     */
    _initCodeEditorEvents: function () {
        let self = this;

        // Only bind once
        if (self.codeEditorEventsInit) return;
        self.codeEditorEventsInit = true;

        // Cancel button
        document.getElementById('code-editor-cancel')?.addEventListener('click', function () {
            self._closeCodeEditor();
        });

        // Close overlay click
        document.getElementById('astero-code-editor-overlay')?.addEventListener('click', function () {
            self._closeCodeEditor();
        });

        // Apply button
        document.getElementById('code-editor-apply')?.addEventListener('click', function () {
            let html;
            if (self.isCodeEditorReady && self.codeEditor) {
                html = self.codeEditor.getValue();
            } else {
                html = document.getElementById('code-textarea').value;
            }

            if (self.selectedEl) {
                let oldHtml = self.selectedEl.innerHTML;
                self.selectedEl.innerHTML = html;

                Astero.Undo.addMutation({
                    type: 'characterData',
                    target: self.selectedEl,
                    oldValue: oldHtml,
                    newValue: html,
                });

                Astero.TreeList.loadComponents();
            }

            self._closeCodeEditor();
        });

        // Copy button
        document.getElementById('code-editor-copy')?.addEventListener('click', function () {
            let html;
            if (self.isCodeEditorReady && self.codeEditor) {
                html = self.codeEditor.getValue();
            } else {
                html = document.getElementById('code-textarea').value;
            }

            navigator.clipboard.writeText(html).then(function () {
                let btn = document.getElementById('code-editor-copy');
                let originalText = btn.innerHTML;
                btn.innerHTML = '<i class="ri-check-line me-1"></i> Copied!';
                setTimeout(() => (btn.innerHTML = originalText), 2000);
            });
        });
    },

    /**
     * Close code editor
     */
    _closeCodeEditor: function () {
        document.getElementById('astero-code-editor')?.style &&
            (document.getElementById('astero-code-editor').style.display = 'none');
        document.getElementById('astero-code-editor-overlay')?.style &&
            (document.getElementById('astero-code-editor-overlay').style.display = 'none');
    },
});
