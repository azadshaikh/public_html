/**
 * Tab Manager
 *
 * Manages editor tabs lifecycle including switching, closing, and dirty state tracking.
 */

import * as StateManager from './state-manager.js';
import { debugLog } from './debug.js';

/**
 * Create a tab manager instance
 * @returns {Object} Tab manager API
 */
export function createTabManager() {
    return {
        /**
         * Switch to a different tab
         * @param {string} path - File path to switch to
         * @param {Object} component - Alpine component instance
         * @param {Object} editorManager - Editor manager instance
         * @returns {Promise<void>}
         */
        async switchTab(path, component, editorManager) {
            debugLog('[ThemeEditor] switchTab called:', path);

            if (component.activeTab === path) {
                debugLog('[ThemeEditor] Already active tab, skipping');
                return;
            }

            if (component._isSwitchingTab) {
                debugLog('[ThemeEditor] switchTab already in progress; skipping');
                return;
            }

            component._isSwitchingTab = true;

            try {
                const editor = editorManager.getEditor();

                // Save current content before switching
                if (component.activeTab && editor) {
                    debugLog('[ThemeEditor] Saving current tab content');
                    StateManager.setModifiedContent(component.activeTab, editor.getValue());
                }

                debugLog('[ThemeEditor] Setting activeTab...');
                component.activeTab = path;
                component.showDiff = false;

                const tab = component.tabs.find((t) => t.path === path);
                debugLog('[ThemeEditor] Found tab:', !!tab, 'Editor exists:', !!editor);

                if (tab) {
                    await editorManager.ensureEditorReady(component);
                }

                const editorInstance = editorManager.getEditor();
                if (tab && editorInstance) {
                    const content = StateManager.getModifiedContent(path) || '';
                    debugLog('[ThemeEditor] Preparing Monaco model, length:', content.length);

                    const model = editorManager.getOrCreateModel(path, content, tab.language, component.themeDirectory);
                    if (!model) {
                        console.warn('[ThemeEditor] Failed to create model for', path);
                        return;
                    }

                    // Keep non-reactive store in sync
                    StateManager.setModifiedContent(path, model.getValue());

                    debugLog('[ThemeEditor] Setting editor model...');
                    component._isSettingValue = true;
                    editorInstance.setModel(model);
                    component._isSettingValue = false;

                    // Layout + focus after swap
                    try {
                        editorInstance.layout();
                        editorInstance.focus();
                    } catch (_) {}

                    component.currentLanguage = (tab.language || '').toString();
                    debugLog('[ThemeEditor] Loading revision count...');

                    // Update dirty state for the new tab
                    component.currentFileDirty = StateManager.isDirty(path);
                }
                debugLog('[ThemeEditor] switchTab done');
                component.saveState();
            } finally {
                component._isSwitchingTab = false;
            }
        },

        /**
         * Close a tab
         * @param {string} path - File path to close
         * @param {Object} component - Alpine component instance
         * @param {Object} editorManager - Editor manager instance (can be null)
         */
        async closeTab(path, component, editorManager) {
            if (StateManager.isDirty(path)) {
                if (!confirm('You have unsaved changes. Are you sure you want to close this file?')) {
                    return;
                }
            }

            const index = component.tabs.findIndex((t) => t.path === path);
            if (index === -1) return;

            component.tabs.splice(index, 1);
            StateManager.deleteContent(path);
            StateManager.deleteModel(path);

            component.saveState();

            // Switch to adjacent tab
            if (component.activeTab === path) {
                if (component.tabs.length > 0) {
                    const newIndex = Math.min(index, component.tabs.length - 1);
                    if (editorManager) {
                        await this.switchTab(component.tabs[newIndex].path, component, editorManager);
                    } else {
                        component.activeTab = component.tabs[newIndex].path;
                    }
                } else {
                    component.activeTab = null;
                    if (editorManager) {
                        const editor = editorManager.getEditor();
                        if (editor) {
                            component._isSettingValue = true;
                            editor.setModel(null);
                            component._isSettingValue = false;
                        }
                    }
                }
            }
        },

        /**
         * Close all tabs except one
         * @param {string} path - File path to keep open
         * @param {Object} component - Alpine component instance
         */
        closeOtherTabs(path, component) {
            const tabsToClose = component.tabs.filter((t) => t.path !== path);
            for (const tab of tabsToClose) {
                if (StateManager.isDirty(tab.path)) continue;
                this.closeTab(tab.path, component, null);
            }
        },

        /**
         * Close all tabs
         * @param {Object} component - Alpine component instance
         */
        closeAllTabs(component) {
            const tabsToClose = [...component.tabs];
            for (const tab of tabsToClose) {
                if (StateManager.isDirty(tab.path)) continue;
                this.closeTab(tab.path, component, null);
            }
        },
    };
}
