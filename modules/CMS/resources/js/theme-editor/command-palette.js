/**
 * Command Palette
 *
 * Quick file opener with fuzzy search (Ctrl+P).
 */

/**
 * Create a command palette instance
 * @returns {Object} Command palette API
 */
export function createCommandPalette() {
    return {
        /**
         * Open command palette
         * @param {Object} component - Alpine component instance
         */
        openCommandPalette(component) {
            component.showCommandPalette = true;
            component.commandSearch = '';
            component.commandIndex = 0;
            component.$nextTick(() => component.$refs.commandInput?.focus());
        },

        /**
         * Close command palette
         * @param {Object} component - Alpine component instance
         */
        closeCommandPalette(component) {
            component.showCommandPalette = false;
            component.commandSearch = '';
        },

        /**
         * Filter files based on search query
         * @param {Array} flatFiles - Flat array of files
         * @param {string} searchQuery - Search query
         * @returns {Array} Filtered files (max 20)
         */
        filterFiles(flatFiles, searchQuery) {
            if (!searchQuery) return flatFiles.slice(0, 20);

            const search = searchQuery.toLowerCase();
            return flatFiles
                .filter((f) => f.name.toLowerCase().includes(search) || f.path.toLowerCase().includes(search))
                .slice(0, 20);
        },

        /**
         * Navigate through filtered results
         * @param {number} delta - Direction to navigate (-1 for up, +1 for down)
         * @param {Object} component - Alpine component instance
         */
        navigateCommand(delta, component) {
            const filteredFiles = this.filterFiles(component.flatFiles, component.commandSearch);
            component.commandIndex = Math.max(0, Math.min(filteredFiles.length - 1, component.commandIndex + delta));
        },

        /**
         * Open the selected file from filtered results
         * @param {Object} component - Alpine component instance
         * @param {Object} fileOps - File operations instance
         * @param {Object} editorManager - Editor manager instance
         * @param {Object} tabManager - Tab manager instance
         */
        async openSelectedCommand(component, fileOps, editorManager, tabManager) {
            const filteredFiles = this.filterFiles(component.flatFiles, component.commandSearch);
            const file = filteredFiles[component.commandIndex];
            if (file) {
                this.closeCommandPalette(component);
                await fileOps.openFile(file, component, editorManager, tabManager);
            }
        },
    };
}
