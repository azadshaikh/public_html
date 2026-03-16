/**
 * Context Menu
 *
 * Right-click context menu handlers for files, folders, and tabs.
 */

/**
 * Create a context menu instance
 * @returns {Object} Context menu API
 */
export function createContextMenu() {
    return {
        /**
         * Show context menu for tree item
         * @param {Event} event - Mouse event
         * @param {Object} item - File or folder item
         * @param {Object} component - Alpine component instance
         */
        showTreeContextMenu(event, item, component) {
            component.contextMenu = {
                show: true,
                x: event.clientX,
                y: event.clientY,
                type: item ? (item.type === 'directory' ? 'folder' : 'file') : 'tree',
                item: item,
            };
        },

        /**
         * Show context menu for tab
         * @param {Event} event - Mouse event
         * @param {Object} tab - Tab object
         * @param {Object} component - Alpine component instance
         */
        showTabContextMenu(event, tab, component) {
            component.contextMenu = {
                show: true,
                x: event.clientX,
                y: event.clientY,
                type: 'tab',
                item: tab,
            };
        },

        /**
         * Close context menu
         * @param {Object} component - Alpine component instance
         */
        closeContextMenu(component) {
            component.contextMenu.show = false;
        },

        /**
         * Open file from context menu
         * @param {Object} component - Alpine component instance
         * @param {Object} fileOps - File operations instance
         * @param {Object} editorManager - Editor manager instance
         * @param {Object} tabManager - Tab manager instance
         */
        async contextMenuOpenFile(component, fileOps, editorManager, tabManager) {
            if (component.contextMenu.item) {
                await fileOps.openFile(component.contextMenu.item, component, editorManager, tabManager);
            }
            this.closeContextMenu(component);
        },

        /**
         * Show revisions from context menu
         * @param {Object} component - Alpine component instance
         * @param {Object} fileOps - File operations instance
         * @param {Object} editorManager - Editor manager instance
         * @param {Object} tabManager - Tab manager instance
         * @param {Object} revisionManager - Revision manager instance
         */
        async contextMenuShowRevisions(component, fileOps, editorManager, tabManager, revisionManager) {
            if (component.contextMenu.item) {
                // Open file first if not already open
                const tab = component.tabs.find((t) => t.path === component.contextMenu.item.path);
                if (!tab) {
                    await fileOps.openFile(component.contextMenu.item, component, editorManager, tabManager);
                    await revisionManager.openRevisions(component, fileOps);
                } else {
                    await tabManager.switchTab(component.contextMenu.item.path, component, editorManager);
                    await revisionManager.openRevisions(component, fileOps);
                }
            }
            this.closeContextMenu(component);
        },

        /**
         * Rename item from context menu
         * @param {Object} component - Alpine component instance
         */
        contextMenuRename(component) {
            if (component.contextMenu.item) {
                component.renameOldPath = component.contextMenu.item.path;
                component.renamePath = component.contextMenu.item.path;
                component.showRenameModal = true;
            }
            this.closeContextMenu(component);
        },

        /**
         * Delete file from context menu
         * @param {Object} component - Alpine component instance
         * @param {Object} fileOps - File operations instance
         * @param {Object} tabManager - Tab manager instance
         */
        async contextMenuDelete(component, fileOps, tabManager) {
            if (component.contextMenu.item) {
                await fileOps.deleteFile(component.contextMenu.item.path, component, tabManager);
            }
            this.closeContextMenu(component);
        },

        /**
         * Delete folder from context menu
         * @param {Object} component - Alpine component instance
         * @param {Object} fileOps - File operations instance
         */
        async contextMenuDeleteFolder(component, fileOps) {
            if (component.contextMenu.item) {
                await fileOps.deleteFolder(component.contextMenu.item.path, component);
            }
            this.closeContextMenu(component);
        },

        /**
         * Create new file in folder from context menu
         * @param {Object} component - Alpine component instance
         */
        contextMenuNewFileInFolder(component) {
            if (component.contextMenu.item) {
                component.newFilePath = component.contextMenu.item.path + '/';
                component.showNewFileModal = true;
            }
            this.closeContextMenu(component);
        },

        /**
         * Create new folder in folder from context menu
         * @param {Object} component - Alpine component instance
         */
        contextMenuNewFolderInFolder(component) {
            if (component.contextMenu.item) {
                component.newFolderPath = component.contextMenu.item.path + '/';
                component.showNewFolderModal = true;
            }
            this.closeContextMenu(component);
        },
    };
}
