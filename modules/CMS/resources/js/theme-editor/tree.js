export function createTreeHandlers() {
    return {
        // Build flat tree structure for rendering
        buildFlatTree() {
            this.flatTree = [];
            this._flattenTreeRecursive(this.files, 0, null, []);
            this.updateTreeVisibility();
        },

        _flattenTreeRecursive(items, depth, parentPath, ancestors) {
            for (const item of items) {
                this.flatTree.push({
                    ...item,
                    depth: depth,
                    parentPath: parentPath,
                    ancestors: [...ancestors], // Store all ancestor paths
                    visible: true, // Will be computed
                });
                if (item.children && item.children.length > 0) {
                    const newAncestors = parentPath ? [...ancestors, parentPath] : ancestors;
                    this._flattenTreeRecursive(
                        item.children,
                        depth + 1,
                        item.path,
                        item.path ? [...newAncestors, item.path] : newAncestors
                    );
                }
            }
        },

        // Update visibility for all items - called only when folders are toggled
        updateTreeVisibility() {
            for (let i = 0; i < this.flatTree.length; i++) {
                const item = this.flatTree[i];
                if (item.depth === 0) {
                    item.visible = true;
                } else {
                    // Check if all ancestors are expanded
                    let isVisible = true;
                    let parentPath = item.parentPath;
                    while (parentPath && isVisible) {
                        if (!this.expandedFolders[parentPath]) {
                            isVisible = false;
                        }
                        const lastSlash = parentPath.lastIndexOf('/');
                        parentPath = lastSlash > 0 ? parentPath.substring(0, lastSlash) : null;
                    }
                    item.visible = isVisible;
                }
            }
        },

        // Flatten file tree for search (editable files only)
        flattenFiles(items, result = []) {
            for (const item of items) {
                if (item.type === 'file' && item.editable) {
                    result.push(item);
                }
                if (item.children) {
                    this.flattenFiles(item.children, result);
                }
            }
            this.flatFiles = result;
            return result;
        },

        // Get list of all folders for upload folder selector
        getFolderList() {
            const folders = [];
            const collectFolders = (items) => {
                for (const item of items) {
                    if (item.type === 'directory') {
                        folders.push({ path: item.path, name: item.name });
                        if (item.children) {
                            collectFolders(item.children);
                        }
                    }
                }
            };
            collectFolders(this.files);
            return folders.sort((a, b) => a.path.localeCompare(b.path));
        },
    };
}
