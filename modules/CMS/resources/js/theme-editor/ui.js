import { contentStore } from './stores.js';

export function createUiHandlers() {
    return {
        // Command Palette
        openCommandPalette() {
            this.showCommandPalette = true;
            this.commandSearch = '';
            this.commandIndex = 0;
            this.$nextTick(() => this.$refs.commandInput?.focus());
        },

        closeCommandPalette() {
            this.showCommandPalette = false;
            this.commandSearch = '';
        },

        getFilteredFiles() {
            if (!this.commandSearch) return this.flatFiles.slice(0, 20);

            const search = this.commandSearch.toLowerCase();
            return this.flatFiles
                .filter((f) => f.name.toLowerCase().includes(search) || f.path.toLowerCase().includes(search))
                .slice(0, 20);
        },

        navigateCommand(delta) {
            this.commandIndex = Math.max(0, Math.min(this.getFilteredFiles().length - 1, this.commandIndex + delta));
        },

        openSelectedCommand() {
            const file = this.getFilteredFiles()[this.commandIndex];
            if (file) {
                this.openFileFromPalette(file);
            }
        },

        openFileFromPalette(file) {
            this.closeCommandPalette();
            this.openFile(file);
        },

        async setSidebarView(view) {
            this.sidebarView = view;

            if (view === 'sourceControl') {
                await this.loadCommitStatus();
            }

            if (view === 'search' && this.sidebarSearchQuery) {
                this.queueSidebarSearch();
            }

            if (view === 'search') {
                this.$nextTick(() => this.$refs.sidebarSearchInput?.focus());
            }
        },

        queueSidebarSearch() {
            if (this._sidebarSearchTimer) {
                clearTimeout(this._sidebarSearchTimer);
            }

            const query = (this.sidebarSearchQuery || '').trim();
            if (!query) {
                this.sidebarSearchResults = [];
                this.sidebarSearchTotal = 0;
                this.sidebarSearchError = '';
                return;
            }

            this._sidebarSearchTimer = setTimeout(() => {
                this.runSidebarSearch();
            }, 250);
        },

        async runSidebarSearch() {
            const query = (this.sidebarSearchQuery || '').trim();
            if (!query) return;

            this.sidebarSearchLoading = true;
            this.sidebarSearchError = '';

            try {
                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/search`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        query,
                        case_sensitive: this.sidebarSearchCaseSensitive,
                        use_regex: this.sidebarSearchRegex,
                    }),
                });

                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data?.error || data?.message || 'Search failed');

                this.sidebarSearchResults = data?.results || [];
                this.sidebarSearchTotal = data?.total_matches || 0;
                this.sidebarSearchCollapsed = {};
            } catch (error) {
                this.sidebarSearchError = error?.message || 'Search failed';
                this.sidebarSearchResults = [];
                this.sidebarSearchTotal = 0;
            } finally {
                this.sidebarSearchLoading = false;
            }
        },

        isSearchGroupCollapsed(path) {
            return Boolean(this.sidebarSearchCollapsed?.[path]);
        },

        toggleSearchGroup(path) {
            if (!path) return;
            this.sidebarSearchCollapsed = {
                ...this.sidebarSearchCollapsed,
                [path]: !this.sidebarSearchCollapsed?.[path],
            };
        },

        collapseAllSearchGroups() {
            const collapsed = {};
            (this.sidebarSearchResults || []).forEach((result) => {
                if (result?.path) {
                    collapsed[result.path] = true;
                }
            });
            this.sidebarSearchCollapsed = collapsed;
        },

        expandAllSearchGroups() {
            this.sidebarSearchCollapsed = {};
        },

        async openSearchResult(match) {
            const path = (match?.path || '').toString();
            if (!path) return;

            try {
                const file = {
                    path,
                    name: path.split('/').pop() || path,
                    extension: path.includes('.') ? path.split('.').pop() : '',
                    editable: true,
                };

                await this.openFile(file);
                await this.ensureEditorReady();
                await this.$nextTick();
                await new Promise(requestAnimationFrame);

                const line = Number(match?.line || 1);
                const column = Number(match?.column || 1);
                if (this.revealLineInEditor) {
                    this.revealLineInEditor(line, column);
                }
            } catch (error) {
                this.showToast(error?.message || 'Failed to open search result', 'error');
            }
        },

        groupChangesByFolder(changes) {
            const groups = new Map();

            (changes || []).forEach((change) => {
                const path = (change?.path || '').toString();
                if (!path) return;
                const folder = path.includes('/') ? path.split('/').slice(0, -1).join('/') : '.';
                if (!groups.has(folder)) {
                    groups.set(folder, []);
                }
                groups.get(folder).push(change);
            });

            return Array.from(groups.entries()).map(([folder, items]) => ({
                folder,
                items,
            }));
        },

        getPathsFromChanges(changes) {
            return (changes || []).map((change) => (change?.path || '').toString()).filter(Boolean);
        },

        // Context Menu
        showTreeContextMenu(event, item) {
            this.contextMenu = {
                show: true,
                x: event.clientX,
                y: event.clientY,
                type: item ? (item.type === 'directory' ? 'folder' : 'file') : 'tree',
                item: item,
            };
            this.$nextTick(() => this.adjustContextMenuPosition());
        },

        showTabContextMenu(event, tab) {
            this.contextMenu = {
                show: true,
                x: event.clientX,
                y: event.clientY,
                type: 'tab',
                item: tab,
            };
            this.$nextTick(() => this.adjustContextMenuPosition());
        },

        showCommitTabContextMenu(event) {
            this.contextMenu = {
                show: true,
                x: event.clientX,
                y: event.clientY,
                type: 'commit',
                item: null,
            };
            this.$nextTick(() => this.adjustContextMenuPosition());
        },

        closeContextMenu() {
            this.contextMenu.show = false;
        },

        contextMenuCloseCommitTab() {
            this.closeCommitsTab();
            this.closeContextMenu();
        },

        contextMenuCloseOtherTabsFromCommit() {
            if (this.activeTab) {
                this.closeOtherTabs(this.activeTab);
            }
            this.closeContextMenu();
        },

        contextMenuCloseAllTabsFromCommit() {
            this.closeAllTabs();
            this.closeCommitsTab();
            this.closeContextMenu();
        },

        adjustContextMenuPosition() {
            const menu = document.querySelector('.context-menu');
            if (!menu) return;

            const menuRect = menu.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;

            // Adjust horizontal position if menu goes off-screen
            if (this.contextMenu.x + menuRect.width > viewportWidth) {
                this.contextMenu.x = viewportWidth - menuRect.width - 5;
            }

            // Adjust vertical position if menu goes off-screen
            if (this.contextMenu.y + menuRect.height > viewportHeight) {
                this.contextMenu.y = viewportHeight - menuRect.height - 5;
            }

            // Ensure menu doesn't go off the left or top edge
            if (this.contextMenu.x < 0) this.contextMenu.x = 5;
            if (this.contextMenu.y < 0) this.contextMenu.y = 5;
        },

        contextMenuOpenFile() {
            if (this.contextMenu.item) {
                this.openFile(this.contextMenu.item);
            }
            this.closeContextMenu();
        },

        async contextMenuShowRevisions() {
            if (this.contextMenu.item) {
                // Open file first if not already open
                const tab = this.tabs.find((t) => t.path === this.contextMenu.item.path);
                if (!tab) {
                    await this.openFile(this.contextMenu.item);
                    await this.openRevisions();
                } else {
                    await this.switchTab(this.contextMenu.item.path);
                    await this.openRevisions();
                }
            }
            this.closeContextMenu();
        },

        contextMenuRename() {
            if (this.contextMenu.item) {
                this.renameOldPath = this.contextMenu.item.path;
                this.renamePath = this.contextMenu.item.path;
                this.showRenameModal = true;
            }
            this.closeContextMenu();
        },

        contextMenuDelete() {
            if (this.contextMenu.item) {
                this.deleteFile(this.contextMenu.item.path);
            }
            this.closeContextMenu();
        },

        contextMenuDuplicate() {
            if (this.contextMenu.item) {
                this.duplicateFile(this.contextMenu.item.path);
            }
            this.closeContextMenu();
        },

        contextMenuDeleteFolder() {
            if (this.contextMenu.item) {
                this.deleteFolder(this.contextMenu.item.path);
            }
            this.closeContextMenu();
        },

        contextMenuNewFileInFolder() {
            if (this.contextMenu.item) {
                this.newFilePath = this.contextMenu.item.path + '/';
                this.showNewFileModal = true;
            }
            this.closeContextMenu();
        },

        contextMenuNewFolderInFolder() {
            if (this.contextMenu.item) {
                this.newFolderPath = this.contextMenu.item.path + '/';
                this.showNewFolderModal = true;
            }
            this.closeContextMenu();
        },

        // Helpers
        isDirty(path) {
            return contentStore.original[path] !== contentStore.modified[path];
        },

        hasUnsavedChanges() {
            return Object.keys(contentStore.original).some((path) => this.isDirty(path));
        },

        isExpanded(path) {
            return !!this.expandedFolders[path];
        },

        toggleFolder(path) {
            if (this.expandedFolders[path]) {
                delete this.expandedFolders[path];
            } else {
                this.expandedFolders[path] = true;
            }
            // Recalculate visibility only when folders change
            this.updateTreeVisibility();
        },

        collapseAllFolders() {
            this.expandedFolders = {};
            this.updateTreeVisibility();
            this.saveState();
        },

        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
            this.saveState();
        },

        getDepth(path) {
            return (path.match(/\//g) || []).length;
        },

        getParentPath(path) {
            const parts = path.split('/');
            parts.pop();
            return parts.join('/') || '/';
        },

        getFileIconClass(extension) {
            const icons = {
                twig: 'ri-leaf-line file-twig',
                tpl: 'ri-html5-line file-tpl',
                html: 'ri-html5-line file-tpl',
                css: 'ri-css3-line file-css',
                scss: 'ri-css3-line file-scss',
                sass: 'ri-css3-line file-scss',
                js: 'ri-javascript-line file-js',
                json: 'ri-braces-line file-json',
                md: 'ri-markdown-line file-md',
                txt: 'ri-file-text-line',
                xml: 'ri-code-line',
                // Image files
                svg: 'ri-file-image-line file-image',
                jpg: 'ri-image-2-line file-image',
                jpeg: 'ri-image-2-line file-image',
                png: 'ri-image-2-line file-image',
                gif: 'ri-gallery-line file-image',
                webp: 'ri-image-2-line file-image',
                ico: 'ri-star-smile-line file-image',
                bmp: 'ri-image-2-line file-image',
            };
            return icons[extension] || 'ri-file-line';
        },

        isImageFile(path) {
            if (!path) return false;
            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'bmp'];
            const extension = path.split('.').pop().toLowerCase();
            return imageExtensions.includes(extension);
        },

        // Keyboard shortcuts
        handleKeydown(event) {
            // Ctrl+S - Save
            if ((event.ctrlKey || event.metaKey) && event.key === 's') {
                event.preventDefault();
                this.saveCurrentFile();
            }

            // Ctrl+P - Command Palette
            if ((event.ctrlKey || event.metaKey) && event.key === 'p') {
                event.preventDefault();
                this.openCommandPalette();
            }

            // Ctrl+W - Close Tab
            if ((event.ctrlKey || event.metaKey) && event.key === 'w') {
                event.preventDefault();
                if (this.activeTab) {
                    this.closeTab(this.activeTab);
                }
            }

            // Escape - Close panels
            if (event.key === 'Escape') {
                if (this.showDiff) {
                    this.showDiff = false;
                } else if (this.showRevisions) {
                    this.closeRevisions();
                } else if (this.showCommandPalette) {
                    this.closeCommandPalette();
                }
                this.closeContextMenu();
            }
        },

        // Resize sidebar
        startResize(event) {
            this.isResizing = true;
            const startX = event.clientX;
            const startWidth = this.sidebarWidth;

            const onMouseMove = (e) => {
                const delta = e.clientX - startX;
                this.sidebarWidth = Math.max(180, Math.min(400, startWidth + delta));
            };

            const onMouseUp = () => {
                this.isResizing = false;
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
                this.saveState();
            };

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        },

        // Toast notifications
        showToast(message, type = 'success') {
            // Use requestAnimationFrame to ensure DOM is ready
            requestAnimationFrame(() => {
                const container = document.getElementById('toast-container');
                if (!container) {
                    // Fallback: create container if missing
                    console.warn('Toast container not found, message:', message);
                    return;
                }

                const toast = document.createElement('div');
                toast.className = `editor-toast ${type}`;
                toast.innerHTML = `
                    <i class="ri-${type === 'success' ? 'check' : 'error-warning'}-line"></i>
                    <span>${message}</span>
                `;
                container.appendChild(toast);

                // Add show class for animation
                requestAnimationFrame(() => {
                    toast.classList.add('show');
                });

                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }, 3500);
            });
        },
    };
}
