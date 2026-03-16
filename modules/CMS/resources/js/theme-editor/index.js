import { createMonacoHandlers } from './monaco.js';
import { createTreeHandlers } from './tree.js';
import { createFileOperations } from './file-operations.js';
import { createRevisionHandlers } from './revisions.js';
import { createUiHandlers } from './ui.js';
import { createStateHandlers } from './state.js';
import { debugLog } from './debug.js';

function createThemeEditor(themeDirectory, initialFiles, baseUrl, isChildTheme = false, parentTheme = null) {
    // Private non-reactive state
    // Storing complex objects here avoids Alpine proxying them, which causes major performance issues
    const privateState = {
        editor: null,
        diffEditor: null,
        monacoPromise: null,
        editorCreatePromise: null,
        dirtyUpdateTimer: null,
        diffModels: { original: null, modified: null },
    };

    return {
        // State
        themeDirectory: themeDirectory,
        baseUrl: baseUrl,
        files: initialFiles,
        flatFiles: [],
        flatTree: [],
        tabs: [],
        activeTab: null,
        // Content is stored in non-reactive _contentStore to prevent cascading re-renders
        // originalContent and modifiedContent are now just getters
        loading: false,
        sidebarWidth: 260,
        isResizing: false,
        // Use object instead of Set for Alpine reactivity
        expandedFolders: { templates: true, layouts: true, assets: true, config: true },

        // Child theme properties
        isChildTheme: isChildTheme,
        parentTheme: parentTheme,
        currentFileInherited: false,
        currentFileInheritedFrom: '',
        savingFile: false,

        // Cursor info
        cursorLine: 1,
        cursorColumn: 1,
        currentLanguage: '',
        revisionCount: 0,

        // Monaco state (public flags only)
        monacoReady: false,
        _isSettingValue: false, // Flag to skip content change handler during setValue
        _pendingActivatePath: null,
        _createEditorAttempts: 0,
        _isSwitchingTab: false,
        currentFileDirty: false,
        refreshing: false,
        sidebarOpen: window.innerWidth > 768,

        // Revisions
        showRevisions: false,
        revisions: [],
        selectedRevision: null,
        selectedRevisionType: null, // 'git' | 'legacy' | null
        selectedRevisionMeta: null,
        selectedRevisionFiles: [],
        selectedRevisionFilePath: '',
        selectedRevisionFilesLoading: false,
        showDiff: false,

        // Commits tab (working tree)
        sidebarView: 'explorer',
        sidebarSearchQuery: '',
        sidebarSearchResults: [],
        sidebarSearchTotal: 0,
        sidebarSearchLoading: false,
        sidebarSearchError: '',
        sidebarSearchCaseSensitive: false,
        sidebarSearchRegex: false,
        sidebarSearchCollapsed: {},
        _sidebarSearchTimer: null,
        commitChanges: [],
        commitMessage: '',
        commitLoading: false,
        commitStatusLoading: false,
        commitError: '',
        diffSideBySide: true,
        diffContext: 'revision',
        diffSourceMode: 'unstaged',
        diffWorkingChange: null,

        // Multi-file restore (git)
        showRestoreCommitModal: false,
        restoreCommitLoading: false,
        restoreCommitFiles: [],
        restoreCommitSelectedPaths: [],
        restoreCommitSelectAll: true,

        // History UI (Git incremental rollout)
        historyMode: 'auto', // 'git' | 'legacy' | 'auto'
        historyQuery: '',
        historyLimit: 50,
        historySkip: 0,
        historyHasMore: false,
        historyLoading: false,

        get stagedChanges() {
            return (this.commitChanges || []).filter((change) => change?.staged);
        },

        get unstagedChanges() {
            return (this.commitChanges || []).filter((change) => change?.unstaged);
        },

        get filteredRevisions() {
            const query = (this.historyQuery || '').trim().toLowerCase();
            if (!query) return this.revisions;

            return (this.revisions || []).filter((r) => {
                const haystack = [r?.label, r?.creator, r?.time_ago].filter(Boolean).join(' ').toLowerCase();
                return haystack.includes(query);
            });
        },

        // Command Palette
        showCommandPalette: false,
        commandSearch: '',
        commandIndex: 0,

        // Modals
        showNewFileModal: false,
        showNewFolderModal: false,
        showRenameModal: false,
        showUploadModal: false,
        newFilePath: '',
        newFolderPath: '',
        renamePath: '',
        renameOldPath: '',
        uploadFiles: [],
        uploadDragOver: false,
        uploading: false,
        uploadTargetPath: '',

        // Image Preview
        imagePreview: {
            show: false,
            path: '',
            name: '',
            url: '',
            protected: false,
        },

        // Context Menu
        contextMenu: {
            show: false,
            x: 0,
            y: 0,
            type: null,
            item: null,
        },

        // Storage Key
        get storageKey() {
            return `theme_editor_state_${this.themeDirectory}`;
        },

        // Initialize
        init() {
            debugLog('[ThemeEditor] init() starting...');
            debugLog('[ThemeEditor] files:', this.files?.length, 'items');

            this.buildFlatTree();
            debugLog('[ThemeEditor] flatTree built:', this.flatTree?.length, 'items');

            this.flattenFiles(this.files);
            debugLog('[ThemeEditor] flatFiles built:', this.flatFiles?.length, 'items');

            this.restoreState();

            this.initMonaco();
            debugLog('[ThemeEditor] initMonaco called');

            // Watch for modal opens to focus inputs
            this.$watch('showNewFileModal', (value) => {
                if (value) this.$nextTick(() => this.$refs.newFileInput?.focus());
            });
            this.$watch('showNewFolderModal', (value) => {
                if (value) this.$nextTick(() => this.$refs.newFolderInput?.focus());
            });
            this.$watch('showRenameModal', (value) => {
                if (value) this.$nextTick(() => this.$refs.renameInput?.focus());
            });

            // Warn before leaving with unsaved changes
            window.addEventListener('beforeunload', (e) => {
                if (this.hasUnsavedChanges()) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },

        ...createTreeHandlers(),
        ...createMonacoHandlers(privateState),
        ...createFileOperations(privateState),
        ...createRevisionHandlers(privateState),
        ...createUiHandlers(),
        ...createStateHandlers(),
        get filteredFiles() {
            return this.getFilteredFiles();
        },
    };
}

let themeEditorRegistered = false;

export function registerThemeEditor() {
    if (themeEditorRegistered) return;

    const register = () => {
        if (themeEditorRegistered || typeof Alpine === 'undefined') return;
        Alpine.data('themeEditor', createThemeEditor);
        themeEditorRegistered = true;
    };

    document.addEventListener('alpine:init', register);

    if (typeof Alpine !== 'undefined') {
        register();
    }
}

registerThemeEditor();
