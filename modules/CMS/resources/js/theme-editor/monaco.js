import { debugLog } from './debug.js';
import { loadMonaco } from './monaco-loader.js';
import { contentStore, modelStore } from './stores.js';

export function createMonacoHandlers(state) {
    return {
        revealLineInEditor(lineNumber, column = 1) {
            if (!state.editor) return;

            const line = Math.max(1, Number(lineNumber) || 1);
            const col = Math.max(1, Number(column) || 1);

            try {
                state.editor.setPosition({ lineNumber: line, column: col });
                state.editor.revealLineInCenter(line);
                state.editor.focus();
            } catch (_) {
                // ignore
            }
        },
        async initMonaco() {
            debugLog('[ThemeEditor] initMonaco() starting...');
            try {
                state.monacoPromise = state.monacoPromise || loadMonaco();
                await state.monacoPromise;
                debugLog('[ThemeEditor] Monaco loaded');
                this.monacoReady = true;
                // Don't create editor immediately; monaco container is hidden until a tab is open.
                // We'll create it lazily when needed (first file open / tab switch).
                debugLog('[ThemeEditor] Monaco ready (editor will be created lazily)');
            } catch (error) {
                console.error('[ThemeEditor] Failed to load Monaco Editor:', error);
                this.showToast('Failed to load code editor', 'error');
            }
        },

        async ensureEditorReady() {
            if (state.editor) return;

            if (state.editorCreatePromise) {
                await state.editorCreatePromise;
                return;
            }

            state.editorCreatePromise = (async () => {
                // Ensure Monaco is loaded
                if (!this.monacoReady) {
                    await this.initMonaco();
                }
                if (!this.monacoReady) return;

                // Wait for DOM + layout so monaco container is visible
                await this.$nextTick();
                await new Promise(requestAnimationFrame);

                const container = this.$refs.monacoContainer;
                if (!container) {
                    console.warn('[ThemeEditor] monacoContainer ref not found!');
                    return;
                }

                for (let i = 0; i < 60; i++) {
                    if (container.offsetWidth > 0 && container.offsetHeight > 0) break;
                    await new Promise(requestAnimationFrame);
                }

                if (container.offsetWidth === 0 || container.offsetHeight === 0) {
                    console.warn('[ThemeEditor] monacoContainer still not visible; aborting editor create');
                    return;
                }

                if (state.editor) return;

                debugLog('[ThemeEditor] Creating Monaco editor...');
                state.editor = monaco.editor.create(container, {
                    value: '',
                    language: 'html',
                    theme: 'vs-dark',
                    automaticLayout: true,
                    minimap: { enabled: false },
                    fontSize: 14,
                    lineNumbers: 'on',
                    lineNumbersMinChars: 4,
                    lineDecorationsWidth: 6,
                    renderWhitespace: 'selection',
                    scrollBeyondLastLine: false,
                    wordWrap: 'on',
                    padding: { top: 12, bottom: 12 },
                    tabSize: 2,
                    insertSpaces: true,
                    detectIndentation: false,
                    // Features enabled for better coding experience
                    quickSuggestions: true,
                    suggestOnTriggerCharacters: true,
                    parameterHints: { enabled: true },
                    folding: true,
                    bracketPairColorization: { enabled: true },
                    formatOnType: true,
                    formatOnPaste: true,
                });

                // Ensure layout once mounted
                try {
                    setTimeout(() => state.editor && state.editor.layout(), 0);
                } catch (_) {}

                // Track cursor position
                state.editor.onDidChangeCursorPosition((e) => {
                    this.cursorLine = e.position.lineNumber;
                    this.cursorColumn = e.position.column;
                });

                // Track content changes - store in non-reactive object
                let contentChangeCount = 0;
                state.editor.onDidChangeModelContent(() => {
                    // Skip if we're programmatically setting value
                    if (this._isSettingValue) return;

                    if (this.activeTab) {
                        contentChangeCount++;
                        if (contentChangeCount % 10 === 1) {
                            debugLog('[ThemeEditor] Content changed (count:', contentChangeCount, ')');
                        }
                        contentStore.modified[this.activeTab] = state.editor.getValue();
                        this.currentFileDirty = this.isDirty(this.activeTab);
                        this.debouncedUpdateDirtyIndicators();
                    }
                });

                debugLog('[ThemeEditor] Editor created successfully');
            })().finally(() => {
                state.editorCreatePromise = null;
            });

            await state.editorCreatePromise;
        },

        getOrCreateModel(path, content, language) {
            if (!path) return null;

            const existing = modelStore.models[path];
            if (existing) return existing;

            const safeLanguage = (language || 'html').toString().trim().toLowerCase() || 'html';
            // Use a stable URI so Monaco can key model state. Avoid actual file:// to keep it simple.
            // Using inmemory URI to prevent Monaco from trying to load files via XHR itself
            const uri = monaco.Uri.parse(
                `inmemory://theme/${encodeURIComponent(this.themeDirectory)}/${encodeURIComponent(path)}`
            );
            const model = monaco.editor.createModel(content ?? '', safeLanguage, uri);
            modelStore.models[path] = model;
            return model;
        },

        // Backward-compatible method name (some older calls still use createEditor)
        async createEditor() {
            debugLog('[ThemeEditor] createEditor() called');
            await this.ensureEditorReady();
        },

        // Debounced version of updateDirtyIndicators
        debouncedUpdateDirtyIndicators() {
            if (state.dirtyUpdateTimer) {
                clearTimeout(state.dirtyUpdateTimer);
            }
            state.dirtyUpdateTimer = setTimeout(() => {
                this.updateDirtyIndicators(this.activeTab);
            }, 300);
        },

        // Manually update dirty indicators in DOM without Alpine reactivity
        updateDirtyIndicators(paths = null) {
            if (paths) {
                const list = Array.isArray(paths) ? paths : [paths];
                list.forEach((path) => {
                    if (!path) return;
                    const isDirty = this.isDirty(path);
                    const tabEl = document.querySelector(`[data-tab-path="${CSS.escape(path)}"] .editor-tab-modified`);
                    if (tabEl) {
                        tabEl.style.display = isDirty ? '' : 'none';
                    }
                    const treeItem = document.querySelector(`.file-tree-item[data-file-path="${CSS.escape(path)}"]`);
                    if (treeItem) {
                        const indicator = treeItem.querySelector('.editor-tab-modified');
                        if (indicator) {
                            indicator.style.display = isDirty ? '' : 'none';
                        }
                    }
                });
                return;
            }

            // Update tab dirty indicators
            this.tabs.forEach((tab) => {
                const isDirty = this.isDirty(tab.path);
                const tabEl = document.querySelector(`[data-tab-path="${CSS.escape(tab.path)}"] .editor-tab-modified`);
                if (tabEl) {
                    tabEl.style.display = isDirty ? '' : 'none';
                }
            });
            // Update file tree dirty indicators
            document.querySelectorAll('.file-tree-item[data-file-path]').forEach((el) => {
                const path = el.dataset.filePath;
                const indicator = el.querySelector('.editor-tab-modified');
                if (indicator) {
                    indicator.style.display = this.isDirty(path) ? '' : 'none';
                }
            });
        },

        createDiffEditor() {
            if (!this.$refs.diffContainer || state.diffEditor) return;

            state.diffEditor = monaco.editor.createDiffEditor(this.$refs.diffContainer, {
                theme: 'vs-dark',
                automaticLayout: true,
                readOnly: true,
                renderSideBySide: this.diffSideBySide ?? true,
            });
        },

        updateDiffViewMode() {
            if (!state.diffEditor) return;

            try {
                state.diffEditor.updateOptions({
                    renderSideBySide: this.diffSideBySide ?? true,
                });
            } catch (_) {
                // ignore
            }
        },

        disposeDiffModels() {
            if (state.diffEditor) {
                try {
                    state.diffEditor.setModel(null);
                } catch (_) {}
            }

            if (state.diffModels.original) {
                try {
                    state.diffModels.original.dispose();
                } catch (_) {}
                state.diffModels.original = null;
            }

            if (state.diffModels.modified) {
                try {
                    state.diffModels.modified.dispose();
                } catch (_) {}
                state.diffModels.modified = null;
            }
        },
    };
}
