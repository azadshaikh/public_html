import { debugLog } from './debug.js';
import { contentStore, disposeModel, modelStore } from './stores.js';

const BLOCKED_EXTENSIONS = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar'];
const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'bmp'];

function isBlockedExtension(path) {
    const extension = path.split('.').pop().toLowerCase();
    return BLOCKED_EXTENSIONS.includes(extension);
}

function isImageFile(path) {
    const extension = path.split('.').pop().toLowerCase();
    return IMAGE_EXTENSIONS.includes(extension);
}

export function createFileOperations(state) {
    return {
        // Image Preview
        showImagePreview(file) {
            const assetUrl = `${window.location.origin}/themes/${this.themeDirectory}/${file.path}`;
            this.imagePreview = {
                show: true,
                path: file.path,
                name: file.name,
                url: assetUrl,
                protected: file.protected || false,
            };
        },

        closeImagePreview() {
            this.imagePreview.show = false;
        },

        async deleteImageFile() {
            if (!this.imagePreview.path) return;

            if (confirm(`Are you sure you want to delete "${this.imagePreview.name}"?`)) {
                try {
                    const url = `${this.baseUrl}${this.themeDirectory}/file/${encodeURIComponent(this.imagePreview.path)}`;
                    const response = await fetch(url, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });
                    const data = await response.json();

                    if (!response.ok) throw new Error(data.error);

                    this.showToast('File deleted successfully', 'success');
                    this.closeImagePreview();
                    await this.refreshFiles();
                } catch (error) {
                    this.showToast(error.message || 'Failed to delete file', 'error');
                }
            }
        },

        showRenameImageModal() {
            this.renameOldPath = this.imagePreview.path;
            this.renamePath = this.imagePreview.path;
            // Close image preview to avoid z-index conflicts
            this.closeImagePreview();
            this.showRenameModal = true;
            this.$nextTick(() => {
                const input = this.$refs.renameInput;
                if (input) {
                    input.focus();
                    // Select just the filename part, not the full path
                    const fullPath = this.renamePath;
                    const lastSlash = fullPath.lastIndexOf('/');
                    const filename = lastSlash >= 0 ? fullPath.substring(lastSlash + 1) : fullPath;
                    const lastDot = filename.lastIndexOf('.');

                    if (lastSlash >= 0 && lastDot > 0) {
                        // Select filename without extension, preserving the path
                        input.setSelectionRange(lastSlash + 1, lastSlash + 1 + lastDot);
                    } else if (lastSlash >= 0) {
                        // Select entire filename
                        input.setSelectionRange(lastSlash + 1, fullPath.length);
                    } else if (lastDot > 0) {
                        // No path, select filename without extension
                        input.setSelectionRange(0, lastDot);
                    } else {
                        // No path, no extension
                        input.select();
                    }
                }
            });
        },

        // File Operations
        async openFile(file, activate = true) {
            debugLog('[ThemeEditor] openFile called', { file, activate, editable: file.editable });

            // Prevent re-entrancy while loading a file
            if (this.loading) {
                debugLog('[ThemeEditor] Already loading; ignoring click');
                return;
            }

            // Check if it's an image file
            if (isImageFile(file.path)) {
                debugLog('[ThemeEditor] Image file detected, showing preview');
                this.showImagePreview(file);
                return;
            }

            if (!file.editable) {
                debugLog('[ThemeEditor] File not editable, showing toast');
                this.showToast('This file type cannot be edited', 'error');
                return;
            }

            // Check if already open
            const existingTab = this.tabs.find((t) => t.path === file.path);
            if (existingTab) {
                debugLog('[ThemeEditor] Tab already exists, switching');
                this.switchTab(file.path);
                return;
            }

            debugLog('[ThemeEditor] Starting file load...');
            this.loading = true;

            try {
                const url = `${this.baseUrl}${this.themeDirectory}/file/${encodeURIComponent(file.path)}`;
                debugLog('[ThemeEditor] Fetching:', url);

                const response = await fetch(url);
                debugLog('[ThemeEditor] Response received:', response.status);

                const data = await response.json();
                debugLog('[ThemeEditor] Data parsed, content length:', data.content?.length);

                if (!response.ok) throw new Error(data.error);

                // Add tab
                debugLog('[ThemeEditor] Adding tab...');
                this.tabs.push({
                    path: file.path,
                    name: file.name,
                    extension: file.extension,
                    language: data.language,
                    inherited: data.inherited || file.inherited || false,
                    inheritedFrom: data.inheritedFrom || file.inheritedFrom || null,
                });

                // Store content in non-reactive store
                debugLog('[ThemeEditor] Storing content in _contentStore...');
                contentStore.original[file.path] = data.content;
                contentStore.modified[file.path] = data.content;

                // Track if current file is inherited
                if (data.inherited) {
                    this.currentFileInherited = true;
                    this.currentFileInheritedFrom = data.inheritedFrom || file.inheritedFrom || '';
                } else {
                    this.currentFileInherited = false;
                    this.currentFileInheritedFrom = '';
                }

                // Defer tab activation until after loading=false and DOM updates.
                if (activate) {
                    this._pendingActivatePath = file.path;
                }
            } catch (error) {
                console.error('[ThemeEditor] Error:', error);
                this.showToast(error.message || 'Failed to open file', 'error');
            } finally {
                debugLog('[ThemeEditor] Setting loading = false');
                this.loading = false;

                const pathToActivate = this._pendingActivatePath;
                this._pendingActivatePath = null;
                if (pathToActivate) {
                    debugLog('[ThemeEditor] Deferring switchTab until DOM ready:', pathToActivate);
                    try {
                        await this.$nextTick();
                        await new Promise(requestAnimationFrame);
                        await this.switchTab(pathToActivate);
                    } catch (error) {
                        console.error('[ThemeEditor] Failed to switch tab after open:', error);
                        this.showToast('Failed to activate file tab', 'error');
                    }
                }
                this.saveState();
            }
        },

        async switchTab(path) {
            debugLog('[ThemeEditor] switchTab called:', path);

            if (this.activeTab === path) {
                debugLog('[ThemeEditor] Already active tab, skipping');
                return;
            }

            if (this._isSwitchingTab) {
                debugLog('[ThemeEditor] switchTab already in progress; skipping');
                return;
            }

            this._isSwitchingTab = true;

            try {
                // Save current content before switching
                if (this.activeTab && state.editor) {
                    debugLog('[ThemeEditor] Saving current tab content');
                    contentStore.modified[this.activeTab] = state.editor.getValue();
                }

                debugLog('[ThemeEditor] Setting activeTab...');
                this.activeTab = path;
                this.showDiff = false;

                const tab = this.tabs.find((t) => t.path === path);
                debugLog('[ThemeEditor] Found tab:', !!tab, 'Editor exists:', !!state.editor);

                if (tab) {
                    await this.ensureEditorReady();
                }

                if (tab && state.editor) {
                    const content = contentStore.modified[path] || '';
                    debugLog('[ThemeEditor] Preparing Monaco model, length:', content.length);

                    const model = this.getOrCreateModel(path, content, tab.language);
                    if (!model) {
                        console.warn('[ThemeEditor] Failed to create model for', path);
                        return;
                    }

                    // Keep non-reactive store in sync
                    contentStore.modified[path] = model.getValue();

                    debugLog('[ThemeEditor] Setting editor model...');
                    this._isSettingValue = true;
                    state.editor.setModel(model);
                    this._isSettingValue = false;

                    // Layout + focus after swap
                    try {
                        state.editor.layout();
                        state.editor.focus();
                    } catch (_) {}

                    this.currentLanguage = (tab.language || '').toString();
                    debugLog('[ThemeEditor] Loading revision count...');
                    this.loadRevisionCount(path);

                    // Update dirty state for the new tab
                    this.currentFileDirty = this.isDirty(path);

                    // Update inherited file state
                    this.currentFileInherited = tab.inherited || false;
                    this.currentFileInheritedFrom = tab.inheritedFrom || '';
                }
                debugLog('[ThemeEditor] switchTab done');
                this.saveState();
            } finally {
                this._isSwitchingTab = false;
            }
        },

        async closeTab(path) {
            if (this.isDirty(path)) {
                if (!confirm('You have unsaved changes. Are you sure you want to close this file?')) {
                    return;
                }
            }

            const index = this.tabs.findIndex((t) => t.path === path);
            if (index === -1) return;

            this.tabs.splice(index, 1);
            delete contentStore.original[path];
            delete contentStore.modified[path];

            this.saveState();

            // Dispose Monaco model for this tab to prevent memory growth
            disposeModel(path);

            // Switch to adjacent tab
            if (this.activeTab === path) {
                if (this.tabs.length > 0) {
                    const newIndex = Math.min(index, this.tabs.length - 1);
                    this.switchTab(this.tabs[newIndex].path);
                } else {
                    this.activeTab = null;
                    if (state.editor) {
                        this._isSettingValue = true;
                        // Detach model instead of setValue
                        state.editor.setModel(null);
                        this._isSettingValue = false;
                    }
                }
            }
        },

        closeOtherTabs(path) {
            const tabsToClose = this.tabs.filter((t) => t.path !== path);
            for (const tab of tabsToClose) {
                if (this.isDirty(tab.path)) continue;
                this.closeTab(tab.path);
            }
        },

        closeAllTabs() {
            const tabsToClose = [...this.tabs];
            for (const tab of tabsToClose) {
                if (this.isDirty(tab.path)) continue;
                this.closeTab(tab.path);
            }
        },

        async saveCurrentFile() {
            if (!this.activeTab || !this.isDirty(this.activeTab)) return;
            if (!state.editor) return;

            const content = state.editor.getValue();
            const path = this.activeTab;
            const wasInherited = this.currentFileInherited;

            try {
                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/file/${encodeURIComponent(path)}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ content }),
                });

                const data = await response.json();

                if (!response.ok) throw new Error(data.error);

                // Update original content
                contentStore.original[path] = content;
                this.currentFileDirty = false;
                this.updateDirtyIndicators(path);

                // If the file was inherited, it's now been created in the child theme
                if (wasInherited) {
                    // Update the tab
                    const tab = this.tabs.find((t) => t.path === path);
                    if (tab) {
                        tab.inherited = false;
                        tab.inheritedFrom = null;
                    }

                    // Update the flatTree item
                    const treeItem = this.flatTree.find((f) => f.path === path && f.type === 'file');
                    if (treeItem) {
                        treeItem.inherited = false;
                        treeItem.inheritedFrom = null;
                    }

                    // Update the files source data (recursive search)
                    this._updateFileInTree(this.files, path, { inherited: false, inheritedFrom: null });

                    // Clear inherited state
                    this.currentFileInherited = false;
                    this.currentFileInheritedFrom = '';
                }

                this.showToast('File saved successfully', 'success');
            } catch (error) {
                this.showToast(error.message || 'Failed to save file', 'error');
            }
        },

        /**
         * Recursively find and update a file in the tree
         */
        _updateFileInTree(items, path, updates) {
            for (const item of items) {
                if (item.path === path && item.type === 'file') {
                    Object.assign(item, updates);
                    return true;
                }
                if (item.children) {
                    if (this._updateFileInTree(item.children, path, updates)) {
                        return true;
                    }
                }
            }
            return false;
        },

        /**
         * Copy an inherited file from parent theme to child theme
         * This creates a local copy that can be edited and will override the parent file
         */
        async copyInheritedFile() {
            if (!this.activeTab || !this.currentFileInherited) {
                return;
            }

            const path = this.activeTab;
            const content = contentStore.modified[path] || '';

            this.savingFile = true;

            try {
                // Create the file in the child theme by using the create endpoint
                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/file`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        path: path,
                        content: content,
                    }),
                });

                const data = await response.json();

                if (!response.ok) throw new Error(data.error);

                // Update the tab to no longer be inherited
                const tab = this.tabs.find((t) => t.path === path);
                if (tab) {
                    tab.inherited = false;
                    tab.inheritedFrom = null;
                    tab.override = true;
                    tab.overrides = this.currentFileInheritedFrom;
                }

                // Update original content
                contentStore.original[path] = content;

                // Clear inherited state
                this.currentFileInherited = false;
                this.currentFileInheritedFrom = '';
                this.currentFileDirty = false;
                this.updateDirtyIndicators(path);

                // Refresh file tree to show the new file
                await this.refreshFiles();

                this.showToast('File copied to child theme successfully', 'success');
            } catch (error) {
                this.showToast(error.message || 'Failed to copy file', 'error');
            } finally {
                this.savingFile = false;
            }
        },

        async createNewFile() {
            // Validate path is not empty
            if (!this.newFilePath.trim()) {
                this.showToast('Please enter a file path', 'error');
                return;
            }

            // Block PHP files
            if (isBlockedExtension(this.newFilePath)) {
                this.showToast('Cannot create PHP files for security reasons', 'error');
                return;
            }

            try {
                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/file`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ path: this.newFilePath }),
                });

                const data = await response.json();

                if (!response.ok) throw new Error(data.error);

                this.showNewFileModal = false;
                this.newFilePath = '';

                await this.refreshFiles();

                // Open the new file
                this.openFile({
                    path: data.path,
                    name: data.path.split('/').pop(),
                    extension: data.path.split('.').pop(),
                    editable: true,
                });

                this.showToast('File created successfully', 'success');
            } catch (error) {
                this.showToast(error.message || 'Failed to create file', 'error');
            }
        },

        async createNewFolder() {
            if (!this.newFolderPath.trim()) return;

            try {
                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/folder`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ path: this.newFolderPath }),
                });

                const data = await response.json();

                if (!response.ok) throw new Error(data.error);

                this.showNewFolderModal = false;
                this.newFolderPath = '';

                await this.refreshFiles();

                this.showToast('Folder created successfully', 'success');
            } catch (error) {
                this.showToast(error.message || 'Failed to create folder', 'error');
            }
        },

        async deleteFile(path) {
            if (!confirm('Are you sure you want to delete this file?')) return;

            try {
                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/file/${encodeURIComponent(path)}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                const data = await response.json();

                if (!response.ok) throw new Error(data.error);

                // Close tab if open
                this.closeTab(path);

                await this.refreshFiles();

                this.showToast('File deleted successfully', 'success');
            } catch (error) {
                this.showToast(error.message || 'Failed to delete file', 'error');
            }
        },

        async deleteFolder(path) {
            if (!confirm('Are you sure you want to delete this folder and all its contents?')) return;

            try {
                const response = await fetch(
                    `${this.baseUrl}${this.themeDirectory}/folder/${encodeURIComponent(path)}`,
                    {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    }
                );

                const data = await response.json();

                if (!response.ok) throw new Error(data.error);

                await this.refreshFiles();

                this.showToast('Folder deleted successfully', 'success');
            } catch (error) {
                this.showToast(error.message || 'Failed to delete folder', 'error');
            }
        },

        async duplicateFile(path) {
            try {
                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/duplicate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ path: path }),
                });

                const data = await response.json();

                if (!response.ok) throw new Error(data.error);

                await this.refreshFiles();

                // Open the new file
                this.openFile({
                    path: data.new_path,
                    name: data.new_path.split('/').pop(),
                    extension: data.new_path.split('.').pop(),
                    editable: true,
                });

                this.showToast(`File duplicated as ${data.new_path.split('/').pop()}`, 'success');
            } catch (error) {
                this.showToast(error.message || 'Failed to duplicate file', 'error');
            }
        },

        async renameItem() {
            // Validate paths
            if (!this.renameOldPath || !this.renamePath || this.renameOldPath === this.renamePath) {
                this.showToast('Invalid rename operation', 'error');
                return;
            }

            if (!this.renamePath.trim()) {
                this.showToast('Please enter a new name', 'error');
                return;
            }

            // Block renaming to PHP files
            if (isBlockedExtension(this.renamePath)) {
                this.showToast('Cannot rename to PHP files for security reasons', 'error');
                return;
            }

            try {
                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/rename`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        old_path: this.renameOldPath,
                        new_path: this.renamePath,
                    }),
                });

                const data = await response.json();

                if (!response.ok) throw new Error(data.error);

                this.showRenameModal = false;
                const oldPath = this.renameOldPath;

                // Update tab if open
                const tab = this.tabs.find((t) => t.path === oldPath);
                if (tab) {
                    const content = contentStore.modified[oldPath];
                    const original = contentStore.original[oldPath];
                    const existingModel = modelStore.models[oldPath];
                    const existingModelContent = existingModel?.getValue?.();

                    delete contentStore.modified[oldPath];
                    delete contentStore.original[oldPath];
                    disposeModel(oldPath);

                    tab.path = data.new_path;
                    tab.name = data.new_path.split('/').pop();

                    contentStore.modified[data.new_path] = content;
                    contentStore.original[data.new_path] = original;

                    if (this.activeTab === oldPath) {
                        this.activeTab = data.new_path;
                        this.currentFileDirty = this.isDirty(data.new_path);
                        if (state.editor) {
                            const modelContent = contentStore.modified[data.new_path] ?? existingModelContent ?? '';
                            const model = this.getOrCreateModel(data.new_path, modelContent, tab.language);
                            if (model) {
                                this._isSettingValue = true;
                                state.editor.setModel(model);
                                this._isSettingValue = false;
                            }
                        }
                    }
                }

                await this.refreshFiles();
                this.updateDirtyIndicators([oldPath, data.new_path]);

                this.showToast('Renamed successfully', 'success');
            } catch (error) {
                this.showToast(error.message || 'Failed to rename', 'error');
            }
        },

        async refreshFiles() {
            if (this.refreshing) return;
            this.refreshing = true;

            try {
                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/files`);
                const data = await response.json();

                if (!response.ok) throw new Error(data.error);

                this.files = data.files;
                this.buildFlatTree();
                this.flattenFiles(this.files);

                // Small delay to ensure the spinner is visible long enough to be noticed
                await new Promise((r) => setTimeout(r, 500));
            } catch (error) {
                this.showToast('Failed to refresh files', 'error');
            } finally {
                this.refreshing = false;
            }
        },

        // Upload Methods
        handleFileDrop(event) {
            this.uploadDragOver = false;
            const files = Array.from(event.dataTransfer.files);
            this.addUploadFiles(files);
        },

        handleFileSelect(event) {
            const files = Array.from(event.target.files);
            this.addUploadFiles(files);
            event.target.value = ''; // Reset input
        },

        addUploadFiles(files) {
            for (const file of files) {
                // Block PHP files on client side
                if (isBlockedExtension(file.name)) {
                    this.showToast(`Cannot upload PHP files: ${file.name}`, 'error');
                    continue;
                }

                // Use relative path for folder uploads as unique key
                const fileKey = file.webkitRelativePath || file.name;

                // Avoid duplicates by checking unique key
                if (!this.uploadFiles.some((f) => (f.webkitRelativePath || f.name) === fileKey)) {
                    this.uploadFiles.push(file);
                }
            }
        },

        removeUploadFile(index) {
            this.uploadFiles.splice(index, 1);
        },

        async uploadAllFiles() {
            if (this.uploadFiles.length === 0 || this.uploading) return;

            this.uploading = true;
            let successCount = 0;
            let failCount = 0;

            try {
                for (const file of this.uploadFiles) {
                    const formData = new FormData();
                    formData.append('file', file);

                    // Build upload path
                    let uploadPath = this.uploadTargetPath || '';

                    // If file has a relative path (folder upload), add its directory
                    if (file.webkitRelativePath) {
                        // Get directory part from relative path (e.g., "folder/subfolder/file.txt" -> "folder/subfolder")
                        const pathParts = file.webkitRelativePath.split('/');
                        if (pathParts.length > 1) {
                            const fileDir = pathParts.slice(0, -1).join('/');
                            uploadPath = uploadPath ? `${uploadPath}/${fileDir}` : fileDir;
                        }
                    }

                    if (uploadPath) {
                        formData.append('path', uploadPath);
                    }

                    try {
                        const response = await fetch(`${this.baseUrl}${this.themeDirectory}/upload`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: formData,
                        });

                        const data = await response.json();

                        if (response.ok) {
                            successCount++;
                        } else {
                            failCount++;
                            this.showToast(data.error || `Failed to upload ${file.name}`, 'error');
                        }
                    } catch (error) {
                        failCount++;
                        this.showToast(`Failed to upload ${file.name}`, 'error');
                    }
                }

                if (successCount > 0) {
                    this.showToast(
                        `Uploaded ${successCount} file${successCount > 1 ? 's' : ''} successfully`,
                        'success'
                    );
                    await this.refreshFiles();
                }

                this.showUploadModal = false;
                this.uploadFiles = [];
                this.uploadTargetPath = '';
            } finally {
                this.uploading = false;
            }
        },
    };
}
