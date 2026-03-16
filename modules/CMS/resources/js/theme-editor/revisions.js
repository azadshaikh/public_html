import { contentStore, modelStore } from './stores.js';

export function createRevisionHandlers(state) {
    const encodePath = (path) => {
        return (path || '')
            .toString()
            .split('/')
            .map((segment) => encodeURIComponent(segment))
            .join('/');
    };

    const formatTimeAgo = (isoString) => {
        if (!isoString) return '';

        const date = new Date(isoString);
        const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
        if (!isFinite(seconds)) return '';

        if (seconds < 60) return 'just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h ago`;
        const days = Math.floor(hours / 24);
        return `${days}d ago`;
    };

    const normalizeHistory = (data) => {
        if (Array.isArray(data?.revisions)) {
            return data.revisions;
        }

        if (Array.isArray(data?.commits)) {
            return data.commits.map((c) => ({
                id: c.hash,
                label: c.subject,
                created_at: c.date,
                time_ago: formatTimeAgo(c.date),
                creator: c.author_name || c.author_email || 'Unknown',
                formatted_size: '',
                file_size: null,
                _type: 'git',
            }));
        }

        return [];
    };

    const uniqAppend = (existing, incoming) => {
        const seen = new Set((existing || []).map((r) => r?.id));
        const out = [...(existing || [])];
        for (const item of incoming || []) {
            if (!item || !item.id) continue;
            if (seen.has(item.id)) continue;
            seen.add(item.id);
            out.push(item);
        }
        return out;
    };

    const normalizeStatus = (data) => {
        if (Array.isArray(data?.changes)) {
            return data.changes;
        }

        return [];
    };

    return {
        // Revisions
        async loadRevisionCount(path) {
            try {
                // Use a slightly higher limit so UI can show `50+` style.
                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/git/history?limit=51&skip=0`);
                const data = await response.json().catch(() => ({}));

                if (response.ok) {
                    const list = normalizeHistory(data);
                    this.revisionCount = list.length;
                    this.historyHasMore = list.length >= 51;
                    this.historyMode = 'git';
                    return;
                }

                this.revisionCount = 0;
                this.historyHasMore = false;
            } catch (error) {
                // Silent fail
                this.revisionCount = 0;
                this.historyHasMore = false;
            }
        },

        async openRevisions() {
            this.showDiff = false;

            this.revisions = [];
            this.selectedRevision = null;
            this.selectedRevisionType = null;
            this.selectedRevisionMeta = null;
            this.selectedRevisionFiles = [];
            this.selectedRevisionFilePath = '';
            this.selectedRevisionFilesLoading = false;

            try {
                this.historyLoading = true;
                this.historyQuery = '';
                this.historySkip = 0;

                const response = await fetch(
                    `${this.baseUrl}${this.themeDirectory}/git/history?limit=${this.historyLimit}&skip=0`
                );
                const data = await response.json().catch(() => ({}));

                if (!response.ok) throw new Error(data?.error || data?.message || 'Request failed');

                const list = normalizeHistory(data);
                this.revisions = list;
                this.selectedRevision = null;
                this.selectedRevisionType = null;
                this.selectedRevisionMeta = null;
                this.selectedRevisionFiles = [];
                this.selectedRevisionFilePath = '';
                this.selectedRevisionFilesLoading = false;
                this.showRevisions = true;

                this.historyMode = 'git';
                this.historySkip = list.length;
                this.historyHasMore = list.length === this.historyLimit;
            } catch (error) {
                // Still open the panel so the user can see the empty state.
                this.revisions = [];
                this.selectedRevision = null;
                this.selectedRevisionType = null;
                this.selectedRevisionMeta = null;
                this.selectedRevisionFiles = [];
                this.selectedRevisionFilePath = '';
                this.selectedRevisionFilesLoading = false;
                this.showRevisions = true;
                this.historyMode = 'git';
                this.historyHasMore = false;
                this.showToast(error?.message || 'No history yet. Save the file to create the first commit.', 'error');
            } finally {
                this.historyLoading = false;
            }
        },

        async loadMoreRevisions() {
            if (this.historyLoading) return;
            if (this.historyMode !== 'git') return;
            if (!this.historyHasMore) return;

            try {
                this.historyLoading = true;

                const response = await fetch(
                    `${this.baseUrl}${this.themeDirectory}/git/history?limit=${this.historyLimit}&skip=${this.historySkip}`
                );
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data?.error || data?.message || 'Request failed');

                const list = normalizeHistory(data);
                this.revisions = uniqAppend(this.revisions, list);
                this.historySkip = this.revisions.length;
                this.historyHasMore = list.length === this.historyLimit;
            } catch (error) {
                this.showToast(error.message || 'Failed to load more history', 'error');
            } finally {
                this.historyLoading = false;
            }
        },

        closeRevisions() {
            this.showRevisions = false;
            this.showDiff = false;
            this.selectedRevision = null;
            this.selectedRevisionType = null;
            this.selectedRevisionMeta = null;
            this.selectedRevisionFiles = [];
            this.selectedRevisionFilePath = '';
            this.selectedRevisionFilesLoading = false;
        },

        async loadCommitStatus() {
            if (this.commitStatusLoading) return;

            try {
                this.commitStatusLoading = true;
                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/git/status`);
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(data?.error || data?.message || 'Failed to load status');
                }

                this.commitChanges = normalizeStatus(data);
                this.commitError = '';
            } catch (error) {
                this.commitChanges = [];
                this.commitError = error?.message || 'Failed to load status';
            } finally {
                this.commitStatusLoading = false;
            }
        },

        async stageChange(change) {
            return this.applyGitAction('stage', [change?.path]);
        },

        async unstageChange(change) {
            return this.applyGitAction('unstage', [change?.path]);
        },

        async discardChange(change) {
            return this.applyGitAction('discard', [change?.path]);
        },

        async applyGitAction(action, paths) {
            const normalizedPaths = (paths || []).map((p) => (p || '').toString()).filter(Boolean);
            if (normalizedPaths.length === 0) return;

            if (action === 'discard') {
                const confirmMessage =
                    normalizedPaths.length > 1
                        ? `Discard changes for ${normalizedPaths.length} files?`
                        : 'Discard changes for this file?';
                if (!confirm(confirmMessage)) {
                    return;
                }
            }

            try {
                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/git/${action}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ paths: normalizedPaths }),
                });

                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data?.error || data?.message || 'Action failed');

                if (action === 'discard') {
                    if (this.activeTab && normalizedPaths.includes(this.activeTab)) {
                        await this.refreshActiveTabFromDisk();
                    }
                }

                await this.loadCommitStatus();
                await this.refreshFiles();
            } catch (error) {
                const message = error?.message || 'Action failed';
                this.showToast(message, 'error');
            }
        },

        async stageAllChanges(paths) {
            return this.applyGitAction('stage', paths);
        },

        async unstageAllChanges(paths) {
            return this.applyGitAction('unstage', paths);
        },

        async discardAllChanges(paths) {
            return this.applyGitAction('discard', paths);
        },

        async commitWorkingTree() {
            if (this.commitLoading) return;

            const message = (this.commitMessage || '').trim();
            if (!message) {
                this.showToast('Commit message is required.', 'error');
                return;
            }

            if ((this.commitChanges || []).length === 0) {
                this.showToast('No changes to commit.', 'error');
                return;
            }

            try {
                this.commitLoading = true;

                const mode = (this.stagedChanges || []).length > 0 ? 'staged' : 'all';

                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/git/commit`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        message,
                        mode,
                    }),
                });

                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data?.error || data?.message || 'Commit failed');

                this.commitMessage = '';
                await this.loadCommitStatus();
                this.showToast('Commit created.', 'success');
            } catch (error) {
                this.showToast(error?.message || 'Commit failed', 'error');
            } finally {
                this.commitLoading = false;
            }
        },

        async openCommitsTab() {
            this.showRevisions = false;
            this.showDiff = false;
            this.sidebarView = 'sourceControl';
            await this.loadCommitStatus();
        },

        closeCommitsTab() {
            this.sidebarView = 'explorer';
        },

        async openWorkingTreeDiff(change, mode = null) {
            const path = (change?.path || '').toString();
            if (!path) return;

            try {
                const file = {
                    path: path,
                    name: path.split('/').pop() || path,
                    extension: path.includes('.') ? path.split('.').pop() : '',
                    editable: true,
                };

                const existingTab = this.tabs.find((t) => t.path === path);
                if (!existingTab) {
                    await this.openFile(file);
                }

                await this.switchTab(path);

                this.diffContext = 'working';
                this.diffWorkingChange = change;
                this.diffSourceMode = mode || (change?.staged ? 'staged' : 'unstaged');
                await this.viewWorkingTreeDiff();
            } catch (error) {
                this.showToast(error?.message || 'Failed to open diff', 'error');
            }
        },

        async viewWorkingTreeDiff() {
            if (!this.diffWorkingChange?.path) return;

            try {
                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/git/working-diff`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        path: this.diffWorkingChange.path,
                        mode: this.diffSourceMode,
                    }),
                });

                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data?.error || data?.message || 'Request failed');

                const originalContent = data.old_content ?? '';
                const modifiedContent = data.new_content ?? '';

                this.createDiffEditor();
                this.disposeDiffModels();

                const originalModel = monaco.editor.createModel(originalContent, this.currentLanguage);
                const modifiedModel = monaco.editor.createModel(modifiedContent, this.currentLanguage);

                state.diffModels.original = originalModel;
                state.diffModels.modified = modifiedModel;

                state.diffEditor.setModel({
                    original: originalModel,
                    modified: modifiedModel,
                });

                this.updateDiffViewMode();
                this.showDiff = true;
                this.showRevisions = false;
            } catch (error) {
                this.showToast(error?.message || 'Failed to load diff', 'error');
            }
        },

        async setWorkingDiffMode(mode) {
            this.diffSourceMode = mode;
            await this.viewWorkingTreeDiff();
        },

        async toggleCommitsTab() {
            if (this.sidebarView === 'sourceControl') {
                this.closeCommitsTab();
                return;
            }

            await this.openCommitsTab();
        },

        async selectRevision(revision) {
            const revisionId = revision?.id;
            if (!revisionId) return;

            this.selectedRevision = revisionId;
            this.selectedRevisionType = 'git';
            this.selectedRevisionMeta = revision || null;
            this.selectedRevisionFiles = [];
            this.selectedRevisionFilePath = '';

            await this.loadRevisionFiles(revisionId);
        },

        async loadRevisionFiles(revisionId) {
            if (!revisionId) return;

            try {
                this.selectedRevisionFilesLoading = true;

                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/git/commit/${revisionId}/files`);
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data?.error || data?.message || 'Failed to load commit files');

                this.selectedRevisionFiles = Array.isArray(data?.files) ? data.files : [];
            } catch (error) {
                this.selectedRevisionFiles = [];
                this.showToast(error?.message || 'Failed to load commit files', 'error');
            } finally {
                this.selectedRevisionFilesLoading = false;
            }
        },

        selectRevisionFile(file) {
            this.selectedRevisionFilePath = (file?.path || '').toString();
        },

        async refreshActiveTabFromDisk() {
            if (!this.activeTab) return;

            const tabPath = this.activeTab;

            try {
                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/file/${encodePath(tabPath)}`);
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data?.error || data?.message || 'Request failed');

                await this.ensureEditorReady();

                const currentLanguage = this.currentLanguage;

                this._isSettingValue = true;
                try {
                    const existingModel = modelStore?.models?.[tabPath];
                    if (existingModel && !existingModel.isDisposed?.()) {
                        existingModel.dispose();
                    }
                    if (modelStore?.models) {
                        delete modelStore.models[tabPath];
                    }

                    const model = this.getOrCreateModel(tabPath, data.content ?? '', currentLanguage);
                    state.editor.setModel(model);
                } finally {
                    this._isSettingValue = false;
                }

                contentStore.original[tabPath] = data.content ?? '';
                contentStore.modified[tabPath] = data.content ?? '';
                this.currentFileDirty = false;
                this.updateDirtyIndicators(tabPath);
            } catch (error) {
                this.showToast(error.message || 'Failed to refresh file after restore', 'error');
            }
        },

        async viewRevisionDiff() {
            if (!this.selectedRevision) return;
            if (!this.selectedRevisionFilePath) {
                this.showToast('Select a file to view diff.', 'error');
                return;
            }

            try {
                this.diffContext = 'revision';
                const response = await fetch(
                    `${this.baseUrl}${this.themeDirectory}/git/commit/${this.selectedRevision}/diff`,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({
                            path: this.selectedRevisionFilePath,
                        }),
                    }
                );
                const data = await response.json().catch(() => ({}));

                if (!response.ok) throw new Error(data?.error || data?.message || 'Request failed');

                const originalContent = data.old_content ?? '';
                const modifiedContent = data.new_content ?? '';

                // Create diff editor if not exists
                this.createDiffEditor();

                // Reset prior diff models to avoid leaking Monaco models
                this.disposeDiffModels();

                // Set diff content
                const diffLanguage = (this.currentLanguage || 'html').toString().trim().toLowerCase() || 'html';
                const originalModel = monaco.editor.createModel(originalContent, diffLanguage);
                const modifiedModel = monaco.editor.createModel(modifiedContent, diffLanguage);

                state.diffModels.original = originalModel;
                state.diffModels.modified = modifiedModel;

                state.diffEditor.setModel({
                    original: originalModel,
                    modified: modifiedModel,
                });

                this.updateDiffViewMode();
                this.showDiff = true;
                this.showRevisions = false;
            } catch (error) {
                this.showToast(error.message || 'Failed to load revision', 'error');
            }
        },

        async restoreRevision() {
            if (!this.selectedRevision) return;
            if (!this.selectedRevisionFilePath) {
                this.showToast('Select a file to restore.', 'error');
                return;
            }

            if (
                !confirm('Restore this version of the file? This will create a new commit with the restored content.')
            ) {
                return;
            }

            try {
                const response = await fetch(`${this.baseUrl}${this.themeDirectory}/git/restore`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        path: this.selectedRevisionFilePath,
                        commit_hash: this.selectedRevision,
                    }),
                });
                const data = await response.json();

                if (!response.ok) throw new Error(data?.error || data?.message || 'Request failed');

                await this.ensureEditorReady();

                // Replace the model instead of calling editor.setValue()
                // (setValue was the primary freeze hotspot during file switching).
                const tabPath = this.selectedRevisionFilePath;
                const currentLanguage = this.currentLanguage;

                this._isSettingValue = true;
                try {
                    const existingModel = modelStore?.models?.[tabPath];
                    if (existingModel && !existingModel.isDisposed?.()) {
                        existingModel.dispose();
                    }
                    if (modelStore?.models) {
                        delete modelStore.models[tabPath];
                    }

                    const model = this.getOrCreateModel(tabPath, data.content, currentLanguage);
                    state.editor.setModel(model);
                } finally {
                    this._isSettingValue = false;
                }

                if (this.activeTab === tabPath) {
                    contentStore.original[tabPath] = data.content;
                    contentStore.modified[tabPath] = data.content;
                    this.currentFileDirty = false;
                    this.updateDirtyIndicators(tabPath);
                } else {
                    this.refreshFiles();
                }

                this.closeRevisions();

                this.showToast('File restored successfully', 'success');
            } catch (error) {
                this.showToast(error.message || 'Failed to restore revision', 'error');
            }
        },
    };
}
