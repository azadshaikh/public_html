import { debugLog } from './debug.js';
/**
 * Monaco Editor Manager
 *
 * Manages Monaco editor instances, models, and editor setup.
 */

import { loadMonaco, isMonacoLoaded } from './monaco-loader.js';
import * as StateManager from './state-manager.js';

/**
 * Create an editor manager instance
 * @returns {Object} Editor manager API
 */
export function createEditorManager() {
    let _editor = null;
    let _diffEditor = null;
    let _monacoPromise = null;
    let _editorCreatePromise = null;
    let _dirtyUpdateTimer = null;

    return {
        /**
         * Ensure Monaco editor is created and ready
         * @param {Object} component - Alpine component instance
         * @returns {Promise<void>}
         */
        async ensureEditorReady(component) {
            if (_editor) return;

            if (_editorCreatePromise) {
                await _editorCreatePromise;
                return;
            }

            _editorCreatePromise = (async () => {
                // Ensure Monaco is loaded
                if (!component.monacoReady) {
                    _monacoPromise = _monacoPromise || loadMonaco();
                    await _monacoPromise;
                    component.monacoReady = true;
                }
                if (!component.monacoReady) return;

                // Wait for DOM + layout so monaco container is visible
                await component.$nextTick();
                await new Promise(requestAnimationFrame);

                const container = component.$refs.monacoContainer;
                if (!container) {
                    console.warn('[ThemeEditor] monacoContainer ref not found!');
                    return;
                }

                // Wait for container to be visible
                for (let i = 0; i < 60; i++) {
                    if (container.offsetWidth > 0 && container.offsetHeight > 0) break;
                    await new Promise(requestAnimationFrame);
                }

                if (container.offsetWidth === 0 || container.offsetHeight === 0) {
                    console.warn('[ThemeEditor] monacoContainer still not visible; aborting editor create');
                    return;
                }

                if (_editor) return;

                debugLog('[ThemeEditor] Creating Monaco editor...');
                _editor = monaco.editor.create(container, {
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
                    setTimeout(() => _editor && _editor.layout(), 0);
                } catch (_) {}

                this.updateCursorInfo(component);
                this.trackContentChanges(component);

                debugLog('[ThemeEditor] Editor created successfully');
            })().finally(() => {
                _editorCreatePromise = null;
            });

            await _editorCreatePromise;
        },

        /**
         * Set up cursor position tracking
         * @param {Object} component - Alpine component instance
         */
        updateCursorInfo(component) {
            if (!_editor) return;

            _editor.onDidChangeCursorPosition((e) => {
                component.cursorLine = e.position.lineNumber;
                component.cursorColumn = e.position.column;
            });
        },

        /**
         * Set up content change tracking
         * @param {Object} component - Alpine component instance
         */
        trackContentChanges(component) {
            if (!_editor) return;

            let contentChangeCount = 0;
            _editor.onDidChangeModelContent(() => {
                // Skip if we're programmatically setting value
                if (component._isSettingValue) return;

                if (component.activeTab) {
                    contentChangeCount++;
                    if (contentChangeCount % 10 === 1) {
                        debugLog('[ThemeEditor] Content changed (count:', contentChangeCount, ')');
                    }
                    StateManager.setModifiedContent(component.activeTab, _editor.getValue());
                    component.currentFileDirty = StateManager.isDirty(component.activeTab);
                    this.debouncedUpdateDirtyIndicators(component);
                }
            });
        },

        /**
         * Get or create Monaco model for a file
         * @param {string} path - File path
         * @param {string} content - File content
         * @param {string} language - Language mode
         * @param {string} themeDirectory - Theme directory name
         * @returns {Object|null} Monaco model
         */
        getOrCreateModel(path, content, language, themeDirectory) {
            if (!path) return null;

            const existing = StateManager.getModel(path);
            if (existing) return existing;

            const safeLanguage = (language || 'html').toString().trim().toLowerCase() || 'html';
            const uri = monaco.Uri.parse(
                `inmemory://theme/${encodeURIComponent(themeDirectory)}/${encodeURIComponent(path)}`
            );
            const model = monaco.editor.createModel(content ?? '', safeLanguage, uri);
            StateManager.setModel(path, model);
            return model;
        },

        /**
         * Create diff editor for revision comparison
         * @param {HTMLElement} container - Container element
         */
        createDiffEditor(container) {
            if (!container || _diffEditor) return;

            _diffEditor = monaco.editor.createDiffEditor(container, {
                theme: 'vs-dark',
                automaticLayout: true,
                readOnly: true,
                renderSideBySide: true,
            });
        },

        /**
         * Debounced version of updateDirtyIndicators
         * @param {Object} component - Alpine component instance
         */
        debouncedUpdateDirtyIndicators(component) {
            if (_dirtyUpdateTimer) {
                clearTimeout(_dirtyUpdateTimer);
            }
            _dirtyUpdateTimer = setTimeout(() => {
                this.updateDirtyIndicators(component);
            }, 300);
        },

        /**
         * Manually update dirty indicators in DOM without Alpine reactivity
         * @param {Object} component - Alpine component instance
         */
        updateDirtyIndicators(component) {
            // Update tab dirty indicators
            component.tabs.forEach((tab) => {
                const isDirty = StateManager.isDirty(tab.path);
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
                    indicator.style.display = StateManager.isDirty(path) ? '' : 'none';
                }
            });
        },

        /**
         * Get main editor instance
         * @returns {Object|null}
         */
        getEditor() {
            return _editor;
        },

        /**
         * Get diff editor instance
         * @returns {Object|null}
         */
        getDiffEditor() {
            return _diffEditor;
        },
    };
}
