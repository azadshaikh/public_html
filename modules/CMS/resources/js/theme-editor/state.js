import { contentStore } from './stores.js';

export function createStateHandlers() {
    return {
        // Persist State
        saveState() {
            try {
                const modifiedContent = {};
                this.tabs.forEach((t) => {
                    if (this.isDirty(t.path)) {
                        modifiedContent[t.path] = contentStore.modified[t.path];
                    }
                });

                const state = {
                    sidebarWidth: this.sidebarWidth,
                    sidebarOpen: this.sidebarOpen,
                    activeTab: this.activeTab,
                    tabs: this.tabs.map((t) => t.path),
                    modifiedContent: modifiedContent,
                };
                localStorage.setItem(this.storageKey, JSON.stringify(state));
            } catch (e) {
                console.error('Failed to save state:', e);
            }
        },

        async restoreState() {
            try {
                const raw = localStorage.getItem(this.storageKey);
                if (!raw) return;

                const state = JSON.parse(raw);

                // Restore Layout
                if (state.sidebarWidth) this.sidebarWidth = state.sidebarWidth;
                if (typeof state.sidebarOpen === 'boolean' && window.innerWidth > 768) {
                    this.sidebarOpen = state.sidebarOpen;
                }

                // Restore Tabs
                if (Array.isArray(state.tabs)) {
                    for (const path of state.tabs) {
                        // Verify file exists in current tree
                        const file = this.flatTree.find((f) => f.path === path && f.type === 'file');
                        if (file) {
                            // Load sequentially to respect loading state check
                            await this.openFile(file, false);

                            // Restore modified content if exists
                            if (state.modifiedContent && state.modifiedContent[path] !== undefined) {
                                contentStore.modified[path] = state.modifiedContent[path];
                                // If this is the active tab and editor is open, update model (unlikely here as we haven't switched yet)
                            }
                        }
                    }
                }

                // Restore Active Tab
                if (state.activeTab) {
                    // Check if it's in the restored tabs
                    const tabExists = this.tabs.find((t) => t.path === state.activeTab);
                    if (tabExists) {
                        await this.switchTab(state.activeTab);
                    } else if (this.tabs.length > 0) {
                        await this.switchTab(this.tabs[this.tabs.length - 1].path);
                    }
                }

                // Update dirty indicators in UI
                this.updateDirtyIndicators();
            } catch (e) {
                console.error('Failed to restore state:', e);
            }
        },
    };
}
