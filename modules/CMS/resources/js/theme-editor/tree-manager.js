/**
 * Tree Manager
 *
 * Manages the file tree structure, flattening, and visibility based on folder expansion.
 */

/**
 * Create a tree manager instance
 * @returns {Object} Tree manager API
 */
export function createTreeManager() {
    return {
        /**
         * Build flat tree structure for rendering
         * @param {Array} files - Hierarchical file structure
         * @returns {Array} Flat tree array with depth and parent info
         */
        buildFlatTree(files) {
            const flatTree = [];
            this._flattenTreeRecursive(files, 0, null, [], flatTree);
            return flatTree;
        },

        /**
         * Recursively flatten tree structure
         * @private
         */
        _flattenTreeRecursive(items, depth, parentPath, ancestors, result) {
            for (const item of items) {
                result.push({
                    ...item,
                    depth: depth,
                    parentPath: parentPath,
                    ancestors: [...ancestors],
                    visible: true, // Will be computed by updateTreeVisibility
                });
                if (item.children && item.children.length > 0) {
                    const newAncestors = parentPath ? [...ancestors, parentPath] : ancestors;
                    this._flattenTreeRecursive(
                        item.children,
                        depth + 1,
                        item.path,
                        item.path ? [...newAncestors, item.path] : newAncestors,
                        result
                    );
                }
            }
        },

        /**
         * Update visibility for all items based on expanded folders
         * @param {Array} flatTree - Flat tree array
         * @param {Object} expandedFolders - Object with folder paths as keys
         */
        updateTreeVisibility(flatTree, expandedFolders) {
            for (let i = 0; i < flatTree.length; i++) {
                const item = flatTree[i];
                if (item.depth === 0) {
                    item.visible = true;
                } else {
                    // Check if all ancestors are expanded
                    let isVisible = true;
                    let parentPath = item.parentPath;
                    while (parentPath && isVisible) {
                        if (!expandedFolders[parentPath]) {
                            isVisible = false;
                        }
                        const lastSlash = parentPath.lastIndexOf('/');
                        parentPath = lastSlash > 0 ? parentPath.substring(0, lastSlash) : null;
                    }
                    item.visible = isVisible;
                }
            }
        },

        /**
         * Flatten file tree to get only editable files
         * @param {Array} items - Hierarchical file structure
         * @param {Array} result - Accumulator array
         * @returns {Array} Flat array of editable files
         */
        flattenFiles(items, result = []) {
            for (const item of items) {
                if (item.type === 'file' && item.editable) {
                    result.push(item);
                }
                if (item.children) {
                    this.flattenFiles(item.children, result);
                }
            }
            return result;
        },

        /**
         * Toggle folder expansion state
         * @param {string} path - Folder path
         * @param {Object} expandedFolders - Expanded folders object
         * @returns {boolean} New expansion state
         */
        toggleFolder(path, expandedFolders) {
            if (expandedFolders[path]) {
                delete expandedFolders[path];
                return false;
            } else {
                expandedFolders[path] = true;
                return true;
            }
        },

        /**
         * Check if folder is expanded
         * @param {string} path - Folder path
         * @param {Object} expandedFolders - Expanded folders object
         * @returns {boolean}
         */
        isExpanded(path, expandedFolders) {
            return !!expandedFolders[path];
        },
    };
}
