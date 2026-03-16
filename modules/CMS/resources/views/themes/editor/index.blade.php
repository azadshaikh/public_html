<x-editor-layout>
    <x-slot:title>{{ $theme['name'] }} - Theme Editor</x-slot:title>

    @if($isChildTheme ?? false)
        <x-slot:subtitle>
            <i class="ri-git-branch-line"></i>
            <span>Child of <strong>{{ $parentTheme['name'] ?? 'Unknown' }}</strong></span>
        </x-slot:subtitle>
    @endif

    <x-slot:headerLeft>
        <a href="{{ route('cms.appearance.themes.index') }}" class="editor-back-btn">
            <i class="ri-arrow-left-line"></i>
            <span>Back</span>
        </a>
    </x-slot:headerLeft>

    {{-- Main Editor Component --}}
    @php
        // Generate base URL by creating a route with placeholder and removing it
        $editorBaseUrl = str_replace('__THEME__', '', route('cms.appearance.themes.editor.index', ['directory' => '__THEME__']));
    @endphp
    <div class="editor-main"
        x-data="themeEditor(@js($themeDirectory), @js($files), @js($editorBaseUrl), @js($isChildTheme ?? false), @js($parentTheme))"
        x-on:keydown.window="handleKeydown($event)"
        @click.away="closeContextMenu()">

        {{-- Teleported Header Actions --}}
        <template x-teleport=".editor-titlebar-left">
            <button type="button" class="editor-mobile-toggle" @click="toggleSidebar()" style="order: -1; margin-right: 8px;">
                <i class="ri-menu-2-line"></i>
            </button>
        </template>

        {{-- Teleported Header Actions --}}
        <template x-teleport=".editor-titlebar-right">
            <div style="display: flex; gap: 8px; align-items: center; -webkit-app-region: no-drag;">
                <button type="button" class="editor-btn editor-btn-secondary editor-btn-icon"
                    @click="openCommandPalette()" title="Quick Open (Ctrl+P)">
                    <i class="ri-search-line"></i>
                </button>
                <button type="button" class="editor-btn editor-btn-secondary"
                    @click="showNewFileModal = true" title="New File">
                    <i class="ri-add-line"></i>
                    <span>New File</span>
                </button>
                <button type="button" class="editor-btn"
                    @click="saveCurrentFile()"
                    :disabled="!activeTab || !currentFileDirty"
                    title="Save (Ctrl+S)">
                    <i class="ri-save-line"></i>
                    <span>Save</span>
                </button>
            </div>
        </template>

        {{-- Mobile Overlay --}}
        <div class="editor-overlay" :class="{ 'visible': sidebarOpen && window.innerWidth <= 768 }" @click="sidebarOpen = false"></div>

        {{-- Activity Bar --}}
        <div class="editor-activitybar">
            <button type="button" class="editor-activity-btn"
                :class="{ 'active': sidebarView === 'explorer' }"
                @click="setSidebarView('explorer')" title="Explorer">
                <i class="ri-file-list-2-line"></i>
            </button>
            <button type="button" class="editor-activity-btn"
                :class="{ 'active': sidebarView === 'search' }"
                @click="setSidebarView('search')" title="Search">
                <i class="ri-search-line"></i>
            </button>
            <button type="button" class="editor-activity-btn"
                :class="{ 'active': sidebarView === 'sourceControl' }"
                @click="setSidebarView('sourceControl')" title="Source Control">
                <i class="ri-git-branch-line"></i>
            </button>
        </div>

        {{-- Sidebar --}}
        <aside class="editor-sidebar" :style="{ width: sidebarWidth + 'px' }" :class="{ 'open': sidebarOpen }">
            <div class="editor-sidebar-header">
                <span x-text="sidebarView === 'search' ? 'SEARCH' : (sidebarView === 'sourceControl' ? 'SOURCE CONTROL' : 'EXPLORER')"></span>
                <div class="editor-sidebar-actions" x-show="sidebarView === 'explorer'">
                    <button type="button" class="editor-sidebar-btn" @click="showNewFileModal = true" title="New File">
                        <i class="ri-file-add-line"></i>
                    </button>
                    <button type="button" class="editor-sidebar-btn" @click="showNewFolderModal = true" title="New Folder">
                        <i class="ri-folder-add-line"></i>
                    </button>
                    <button type="button" class="editor-sidebar-btn" @click="showUploadModal = true" title="Upload File">
                        <i class="ri-upload-2-line"></i>
                    </button>
                    <button type="button" class="editor-sidebar-btn" @click="collapseAllFolders()" title="Collapse All Folders">
                        <i class="ri-folder-reduce-line"></i>
                    </button>
                    <button type="button" class="editor-sidebar-btn" @click="refreshFiles()" title="Refresh" :disabled="refreshing">
                        <i :class="refreshing ? 'ri-loader-4-line spin' : 'ri-refresh-line'"></i>
                    </button>
                </div>
                <div class="editor-sidebar-actions" x-show="sidebarView === 'sourceControl'">
                    <button type="button" class="editor-sidebar-btn" @click="loadCommitStatus()" title="Refresh" :disabled="commitStatusLoading">
                        <i :class="commitStatusLoading ? 'ri-loader-4-line spin' : 'ri-refresh-line'"></i>
                    </button>
                    <button type="button" class="editor-sidebar-btn" @click="openRevisions()" title="History" :disabled="historyLoading">
                        <i class="ri-history-line"></i>
                    </button>
                </div>
                <div class="editor-sidebar-actions" x-show="sidebarView === 'search'">
                    <button type="button" class="editor-sidebar-btn" @click="runSidebarSearch()" title="Refresh" :disabled="sidebarSearchLoading || !sidebarSearchQuery">
                        <i :class="sidebarSearchLoading ? 'ri-loader-4-line spin' : 'ri-refresh-line'"></i>
                    </button>
                    <button type="button" class="editor-sidebar-btn" @click="collapseAllSearchGroups()" title="Collapse All">
                        <i class="ri-subtract-line"></i>
                    </button>
                    <button type="button" class="editor-sidebar-btn" @click="expandAllSearchGroups()" title="Expand All">
                        <i class="ri-add-line"></i>
                    </button>
                </div>
            </div>

            <div class="editor-sidebar-content" x-show="sidebarView === 'explorer'">
                <div class="editor-file-tree" @contextmenu.prevent="showTreeContextMenu($event, null)">
                    <template x-for="item in flatTree" :key="item.path">
                        <div x-show="item.visible">
                            {{-- Directory Item --}}
                            <template x-if="item.type === 'directory'">
                                <div class="file-tree-item"
                                    :class="{ 'inherited': item.inherited }"
                                    :style="{ '--depth': item.depth }"
                                    @click="toggleFolder(item.path)"
                                    @contextmenu.prevent.stop="showTreeContextMenu($event, item)"
                                    :title="item.inherited ? 'Inherited from ' + item.inheritedFrom : ''">
                                    <i class="file-tree-icon folder" :class="isExpanded(item.path) ? 'ri-folder-open-line' : 'ri-folder-line'"></i>
                                    <span class="file-tree-name" x-text="item.name"></span>
                                    <span x-show="item.inherited" class="file-tree-badge inherited" title="Inherited from parent">
                                        <i class="ri-arrow-up-line"></i>
                                    </span>
                                </div>
                            </template>
                            {{-- File Item --}}
                            <template x-if="item.type === 'file'">
                                <div class="file-tree-item"
                                    :class="{ 'selected': activeTab === item.path, 'inherited': item.inherited, 'override': item.override }"
                                    :style="{ '--depth': item.depth }"
                                    :data-file-path="item.path"
                                    @click="openFile(item)"
                                    @contextmenu.prevent.stop="showTreeContextMenu($event, item)"
                                    :title="item.inherited ? 'Inherited from ' + item.inheritedFrom : (item.override ? 'Overrides ' + item.overrides : '')">
                                    <i class="file-tree-icon" :class="getFileIconClass(item.extension)"></i>
                                    <span class="file-tree-name" x-text="item.name"></span>
                                    <span x-show="item.inherited" class="file-tree-badge inherited" title="Inherited from parent">
                                        <i class="ri-arrow-up-line"></i>
                                    </span>
                                    <span x-show="item.override" class="file-tree-badge override" title="Overrides parent file">
                                        <i class="ri-edit-line"></i>
                                    </span>
                                    <span class="editor-tab-modified" style="display: none;"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <div class="editor-sidebar-content" x-show="sidebarView === 'search'" x-cloak>
                <div class="editor-sidebar-search">
                    <i class="ri-search-line"></i>
                    <input type="text" placeholder="Search in files" x-model="sidebarSearchQuery" @input="queueSidebarSearch()" x-ref="sidebarSearchInput" />
                    <div class="editor-sidebar-search-tools">
                        <button type="button" class="editor-sidebar-toggle"
                            :class="{ 'active': sidebarSearchCaseSensitive }"
                            @click="sidebarSearchCaseSensitive = !sidebarSearchCaseSensitive; queueSidebarSearch()" title="Match Case">
                            Aa
                        </button>
                        <button type="button" class="editor-sidebar-toggle"
                            :class="{ 'active': sidebarSearchRegex }"
                            @click="sidebarSearchRegex = !sidebarSearchRegex; queueSidebarSearch()" title="Use Regular Expression">
                            .*
                        </button>
                        <button type="button" class="editor-sidebar-clear" x-show="sidebarSearchQuery" @click="sidebarSearchQuery = ''; queueSidebarSearch()" title="Clear">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                </div>
                <div class="editor-sidebar-search-results">
                    <div class="editor-search-summary" x-show="sidebarSearchQuery">
                        <span x-text="sidebarSearchLoading ? 'Searching…' : (sidebarSearchTotal + ' results')"></span>
                    </div>
                    <div class="editor-commit-empty" x-show="!sidebarSearchQuery">
                        <i class="ri-search-line"></i>
                        <span>Type to search in files.</span>
                    </div>
                    <div class="editor-commit-error" x-show="sidebarSearchError" x-text="sidebarSearchError"></div>
                    <div class="editor-commit-empty" x-show="sidebarSearchQuery && !sidebarSearchLoading && sidebarSearchResults.length === 0 && !sidebarSearchError">
                        <i class="ri-close-circle-line"></i>
                        <span>No matches found.</span>
                    </div>

                    <template x-for="file in sidebarSearchResults" :key="file.path">
                        <div class="editor-search-group">
                            <div class="editor-search-group-header" @click="toggleSearchGroup(file.path)">
                                <i class="editor-search-chevron" :class="isSearchGroupCollapsed(file.path) ? 'ri-arrow-right-s-line' : 'ri-arrow-down-s-line'"></i>
                                <i class="editor-search-file-icon file-tree-icon" :class="getFileIconClass((file.path || '').includes('.') ? (file.path || '').split('.').pop() : '')"></i>
                                <span class="editor-search-group-path" x-text="file.path"></span>
                                <span class="editor-search-group-count" x-text="file.match_count"></span>
                            </div>
                            <div class="editor-search-group-body" x-show="!isSearchGroupCollapsed(file.path)" x-cloak>
                                <template x-for="match in file.matches" :key="match.path + '-' + match.line + '-' + match.column">
                                    <div class="editor-search-match" @click="openSearchResult(match)">
                                        <span class="editor-search-line" x-text="match.line"></span>
                                        <span class="editor-search-snippet" x-text="match.text"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="editor-sidebar-content" x-show="sidebarView === 'sourceControl'" x-cloak>
                <div class="editor-commit-view">
                    <div class="editor-commit-header">
                        <div class="editor-commit-title">
                            <i class="ri-git-commit-line"></i>
                            <span>Commits</span>
                        </div>
                        <div class="editor-commit-actions">
                            <button type="button" class="editor-btn editor-btn-secondary" @click="loadCommitStatus()" :disabled="commitStatusLoading">
                                <i class="ri-refresh-line"></i>
                                <span x-text="commitStatusLoading ? 'Refreshing' : 'Refresh'"></span>
                            </button>
                            <button type="button" class="editor-btn editor-btn-secondary" @click="openRevisions()" :disabled="historyLoading">
                                <i class="ri-history-line"></i>
                                <span>History</span>
                            </button>
                        </div>
                    </div>

                    <div class="editor-commit-body">
                        <div class="editor-commit-section">
                            <div class="editor-commit-section-title">
                                <span>Staged Changes</span>
                                <div class="editor-commit-section-actions">
                                    <button type="button" class="editor-commit-section-btn"
                                        @click="unstageAllChanges(getPathsFromChanges(stagedChanges))"
                                        :disabled="stagedChanges.length === 0">
                                        <i class="ri-subtract-line"></i>
                                        <span>Unstage All</span>
                                    </button>
                                </div>
                            </div>

                            <div class="editor-commit-empty" x-show="stagedChanges.length === 0 && !commitStatusLoading">
                                <i class="ri-checkbox-circle-line"></i>
                                <span>No staged changes.</span>
                            </div>

                            <div class="editor-commit-error" x-show="commitError" x-text="commitError"></div>

                            <template x-for="group in groupChangesByFolder(stagedChanges)" :key="group.folder">
                                <div class="editor-commit-group">
                                    <div class="editor-commit-group-header">
                                        <span class="editor-commit-group-label" x-text="group.folder"></span>
                                        <div class="editor-commit-group-actions">
                                            <button type="button" class="editor-commit-action-btn" title="Unstage folder" @click.stop="unstageAllChanges(getPathsFromChanges(group.items))">
                                                <i class="ri-subtract-line"></i>
                                            </button>
                                            <button type="button" class="editor-commit-action-btn" title="Discard folder" @click.stop="discardAllChanges(getPathsFromChanges(group.items))">
                                                <i class="ri-delete-bin-6-line"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <template x-for="change in group.items" :key="change.path">
                                        <div class="editor-commit-item" @click="openWorkingTreeDiff(change, 'staged')">
                                            <span class="editor-commit-status" x-text="change.status_label || 'Modified'"></span>
                                            <span class="editor-commit-path" x-text="change.path"></span>
                                            <span class="editor-commit-old-path" x-show="change.old_path" x-text="'→ ' + change.old_path"></span>
                                            <div class="editor-commit-actions-inline">
                                                <button type="button" class="editor-commit-action-btn" title="Unstage" @click.stop="unstageChange(change)">
                                                    <i class="ri-subtract-line"></i>
                                                </button>
                                                <button type="button" class="editor-commit-action-btn" title="Discard" @click.stop="discardChange(change)">
                                                    <i class="ri-delete-bin-6-line"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <div class="editor-commit-section">
                            <div class="editor-commit-section-title">
                                <span>Unstaged Changes</span>
                                <div class="editor-commit-section-actions">
                                    <button type="button" class="editor-commit-section-btn"
                                        @click="stageAllChanges(getPathsFromChanges(unstagedChanges))"
                                        :disabled="unstagedChanges.length === 0">
                                        <i class="ri-add-line"></i>
                                        <span>Stage All</span>
                                    </button>
                                    <button type="button" class="editor-commit-section-btn"
                                        @click="discardAllChanges(getPathsFromChanges(unstagedChanges))"
                                        :disabled="unstagedChanges.length === 0">
                                        <i class="ri-delete-bin-6-line"></i>
                                        <span>Discard All</span>
                                    </button>
                                </div>
                            </div>

                            <div class="editor-commit-empty" x-show="unstagedChanges.length === 0 && !commitStatusLoading">
                                <i class="ri-checkbox-circle-line"></i>
                                <span>No unstaged changes.</span>
                            </div>

                            <template x-for="group in groupChangesByFolder(unstagedChanges)" :key="group.folder">
                                <div class="editor-commit-group">
                                    <div class="editor-commit-group-header">
                                        <span class="editor-commit-group-label" x-text="group.folder"></span>
                                        <div class="editor-commit-group-actions">
                                            <button type="button" class="editor-commit-action-btn" title="Stage folder" @click.stop="stageAllChanges(getPathsFromChanges(group.items))">
                                                <i class="ri-add-line"></i>
                                            </button>
                                            <button type="button" class="editor-commit-action-btn" title="Discard folder" @click.stop="discardAllChanges(getPathsFromChanges(group.items))">
                                                <i class="ri-delete-bin-6-line"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <template x-for="change in group.items" :key="change.path">
                                        <div class="editor-commit-item" @click="openWorkingTreeDiff(change, 'unstaged')">
                                            <span class="editor-commit-status" x-text="change.status_label || 'Modified'"></span>
                                            <span class="editor-commit-path" x-text="change.path"></span>
                                            <span class="editor-commit-old-path" x-show="change.old_path" x-text="'→ ' + change.old_path"></span>
                                            <div class="editor-commit-actions-inline">
                                                <button type="button" class="editor-commit-action-btn" title="Stage" @click.stop="stageChange(change)">
                                                    <i class="ri-add-line"></i>
                                                </button>
                                                <button type="button" class="editor-commit-action-btn" title="Discard" @click.stop="discardChange(change)">
                                                    <i class="ri-delete-bin-6-line"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <div class="editor-commit-section">
                            <div class="editor-commit-section-title">Commit Message</div>
                            <input type="text"
                                class="editor-modal-input"
                                placeholder="Describe your changes..."
                                x-model="commitMessage"
                                :disabled="commitLoading" />
                            <button type="button" class="editor-btn" style="margin-top: 10px;"
                                @click="commitWorkingTree()"
                                :disabled="commitLoading || commitStatusLoading || commitChanges.length === 0">
                                <i class="ri-check-line"></i>
                                <span x-text="commitLoading ? 'Committing…' : (stagedChanges.length > 0 ? 'Commit Staged Changes' : 'Commit Changes')"></span>
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </aside>

        {{-- Resize Handle --}}
        <div class="editor-resize-handle"
            @mousedown="startResize($event)"
            :class="{ 'resizing': isResizing }"></div>

        {{-- Editor Container --}}
        <div class="editor-container">
            {{-- Tab Bar --}}
            <div class="editor-tabbar" x-show="tabs.length > 0">
                <template x-for="tab in tabs" :key="tab.path">
                    <div class="editor-tab"
                        :class="{ 'active': activeTab === tab.path, 'inherited': tab.inherited }"
                        :data-tab-path="tab.path"
                        @click="switchTab(tab.path)"
                        @mousedown.middle.prevent="closeTab(tab.path)"
                        @contextmenu.prevent="showTabContextMenu($event, tab)">
                        <i class="editor-tab-icon" :class="getFileIconClass(tab.extension)"></i>
                        <span class="editor-tab-name" x-text="tab.name"></span>
                        <span x-show="tab.inherited" class="editor-tab-inherited" :title="'From ' + tab.inheritedFrom">
                            <i class="ri-arrow-up-line"></i>
                        </span>
                        <span class="editor-tab-modified" style="display: none;"></span>
                        <button type="button" class="editor-tab-close" @click.stop="closeTab(tab.path)">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                </template>
            </div>

            {{-- Editor Area --}}
            <div class="editor-area">
                {{-- Inherited File Notice --}}
                <div class="editor-inherited-notice" x-show="currentFileInherited" x-cloak>
                    <div class="editor-inherited-notice-content">
                        <i class="ri-information-line"></i>
                        <span>This file is inherited from <strong x-text="currentFileInheritedFrom"></strong>.</span>
                        <span>Editing will create a copy in the child theme.</span>
                    </div>
                    <button type="button" class="editor-btn editor-btn-sm" @click="copyInheritedFile()" :disabled="savingFile">
                        <i class="ri-file-copy-line"></i>
                        <span>Copy to Child Theme</span>
                    </button>
                </div>

                {{-- Empty State --}}
                <div class="editor-empty" x-show="tabs.length === 0">
                    <i class="ri-code-box-line editor-empty-icon"></i>
                    <div class="editor-empty-title">Theme Editor</div>
                    <div class="editor-empty-hint">
                        Select a file from the explorer to start editing<br>
                        or press <kbd>Ctrl</kbd> + <kbd>P</kbd> to quick open
                    </div>
                </div>

                {{-- Loading State --}}
                <div class="editor-loading" x-show="loading">
                    <div class="editor-spinner"></div>
                </div>

                {{-- Monaco Editor --}}
                <div class="monaco-container"
                    x-show="tabs.length > 0 && !loading && !showDiff"
                    x-ref="monacoContainer"></div>

                {{-- Diff Editor --}}
                <div x-show="showDiff">
                    <div class="editor-diff-toolbar">
                        <div class="editor-diff-toolbar-title">
                            <i class="ri-git-commit-line"></i>
                            <span x-text="diffContext === 'working' ? 'Working Tree Diff' : 'Comparing with Selected Commit'"></span>
                        </div>
                        <div class="editor-diff-toolbar-actions">
                            <template x-if="diffContext === 'working' && diffWorkingChange?.staged && diffWorkingChange?.unstaged">
                                <div class="editor-diff-mode-toggle">
                                    <button type="button" class="editor-btn editor-btn-secondary" :class="{ 'active': diffSourceMode === 'staged' }" @click="setWorkingDiffMode('staged')">
                                        Staged
                                    </button>
                                    <button type="button" class="editor-btn editor-btn-secondary" :class="{ 'active': diffSourceMode === 'unstaged' }" @click="setWorkingDiffMode('unstaged')">
                                        Unstaged
                                    </button>
                                </div>
                            </template>
                            <button type="button" class="editor-btn editor-btn-secondary editor-btn-icon" @click="diffSideBySide = !diffSideBySide; updateDiffViewMode()" :title="diffSideBySide ? 'Switch to Inline' : 'Switch to Split'">
                                <i :class="diffSideBySide ? 'ri-layout-left-line' : 'ri-layout-top-line'"></i>
                            </button>
                            <button type="button" class="editor-btn" @click="restoreRevision()">
                                <i class="ri-arrow-go-back-line"></i>
                                <span>Restore This Commit</span>
                            </button>
                            <button type="button" class="editor-btn editor-btn-secondary editor-btn-icon" style="margin-left: 8px;" @click="showDiff = false; showRevisions = true" title="Close">
                                <i class="ri-close-line"></i>
                            </button>
                        </div>
                    </div>
                    <div class="editor-diff-container" x-ref="diffContainer"></div>
                </div>
            </div>
        </div>

        {{-- Revisions Panel --}}
        <div class="editor-overlay" :class="{ 'visible': showRevisions }" @click="closeRevisions()"></div>
        <aside class="editor-revisions-panel" :class="{ 'open': showRevisions }">
            <div class="editor-revisions-header">
                <span class="editor-revisions-title">
                    <i class="ri-history-line"></i>
                    History
                </span>
                <button type="button" class="editor-sidebar-btn" @click="closeRevisions()">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <div style="padding: 10px 12px; border-bottom: 1px solid rgba(0,0,0,0.06);">
                <input type="text"
                    class="editor-modal-input"
                    style="width: 100%; margin: 0;"
                    placeholder="Search history..."
                    x-model="historyQuery" />
            </div>

            <div style="padding: 10px 12px; border-bottom: 1px solid rgba(0,0,0,0.06);" x-show="selectedRevision">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                    <div style="font-size: 12px; line-height: 1.4;">
                        <div style="font-weight: 600;" x-text="selectedRevisionMeta?.label || 'Commit'"></div>
                        <div style="opacity: 0.7;">
                            <span x-text="(selectedRevision || '').toString().slice(0, 7)"></span>
                            <span>•</span>
                            <span x-text="selectedRevisionMeta?.creator || 'Unknown'"></span>
                            <span>•</span>
                            <span x-text="selectedRevisionMeta?.time_ago || ''"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="editor-revisions-list">
                <template x-if="revisions.length === 0">
                    <div class="editor-empty" style="padding: 40px 20px;">
                        <i class="ri-history-line" style="font-size: 32px; opacity: 0.3;"></i>
                        <div style="margin-top: 12px; font-size: 13px;">No history yet</div>
                    </div>
                </template>
                <template x-for="revision in filteredRevisions" :key="revision.id">
                    <div class="editor-revision-item"
                        :class="{ 'selected': selectedRevision === revision.id }"
                        @click="selectRevision(revision)">
                        <div class="editor-revision-meta">
                            <span class="editor-revision-time" x-text="revision.time_ago"></span>
                            <span class="editor-revision-author" style="margin-left: 8px;">
                                <i class="ri-user-line"></i>
                                <span x-text="revision.creator"></span>
                            </span>
                            <span class="editor-revision-size" x-show="revision.formatted_size" x-text="revision.formatted_size"></span>
                        </div>
                        <div class="editor-revision-label" x-show="revision.label" x-text="revision.label"></div>
                    </div>
                </template>
            </div>
            <div style="padding: 10px 12px; border-top: 1px solid rgba(0,0,0,0.06);" x-show="selectedRevision">
                <div class="editor-commit-section-title" style="margin-bottom: 8px;">Files in commit</div>
                <template x-if="selectedRevisionFilesLoading">
                    <div class="editor-empty" style="padding: 12px 0;">
                        <i class="ri-loader-4-line spin"></i>
                        <span style="margin-left: 8px; font-size: 12px;">Loading files…</span>
                    </div>
                </template>
                <template x-if="!selectedRevisionFilesLoading && selectedRevisionFiles.length === 0">
                    <div class="editor-empty" style="padding: 12px 0;">
                        <span style="font-size: 12px;">No files found for this commit.</span>
                    </div>
                </template>
                <template x-for="file in selectedRevisionFiles" :key="file.path + (file.old_path || '')">
                    <div class="editor-commit-item"
                        :class="{ 'selected': selectedRevisionFilePath === file.path }"
                        @click="selectRevisionFile(file)">
                        <span class="editor-commit-status" x-text="file.status || 'M'"></span>
                        <span class="editor-commit-path" x-text="file.path"></span>
                        <span class="editor-commit-old-path" x-show="file.old_path" x-text="'→ ' + file.old_path"></span>
                    </div>
                </template>
            </div>
            <div class="editor-revisions-actions" x-show="selectedRevision && selectedRevisionFilePath">
                <button type="button" class="editor-btn editor-btn-secondary" style="flex: 1" @click="viewRevisionDiff()">
                    <i class="ri-git-commit-line"></i>
                    View Diff
                </button>
                <button type="button" class="editor-btn" style="flex: 1" @click="restoreRevision()" :disabled="!selectedRevisionFilePath">
                    <i class="ri-arrow-go-back-line"></i>
                    Restore File
                </button>
            </div>

            <div class="editor-revisions-actions" x-show="historyHasMore" style="border-top: 1px solid rgba(0,0,0,0.06);">
                <button type="button" class="editor-btn editor-btn-secondary" style="flex: 1" @click="loadMoreRevisions()" :disabled="historyLoading">
                    <i class="ri-more-2-fill"></i>
                    <span x-text="historyLoading ? 'Loading...' : 'Load more'"></span>
                </button>
            </div>
        </aside>

        {{-- Command Palette --}}
        <div class="command-palette" :class="{ 'open': showCommandPalette }">
            <input type="text"
                class="command-palette-input"
                placeholder="Search files..."
                x-ref="commandInput"
                x-model="commandSearch"
                @keydown.escape="closeCommandPalette()"
                @keydown.enter="openSelectedCommand()"
                @keydown.arrow-down.prevent="navigateCommand(1)"
                @keydown.arrow-up.prevent="navigateCommand(-1)">
            <div class="command-palette-results">
                <template x-for="(file, index) in filteredFiles" :key="file.path">
                    <div class="command-palette-item"
                        :class="{ 'selected': commandIndex === index }"
                        @click="openFileFromPalette(file)"
                        @mouseenter="commandIndex = index">
                        <i class="command-palette-item-icon" :class="getFileIconClass(file.extension)"></i>
                        <span x-text="file.name"></span>
                        <span class="command-palette-item-path" x-text="getParentPath(file.path)"></span>
                    </div>
                </template>
            </div>
        </div>

        {{-- New File Modal --}}
        <div class="editor-overlay" :class="{ 'visible': showNewFileModal }" @click="showNewFileModal = false"></div>
        <div class="editor-modal" :class="{ 'open': showNewFileModal }">
            <div class="editor-modal-title">Create New File</div>
            <input type="text"
                class="editor-modal-input"
                placeholder="Enter file path (e.g., templates/custom.tpl)"
                x-model="newFilePath"
                x-ref="newFileInput"
                @keydown.enter="createNewFile()"
                @keydown.escape="showNewFileModal = false">
            <div class="editor-modal-actions">
                <button type="button" class="editor-btn editor-btn-secondary" @click="showNewFileModal = false">Cancel</button>
                <button type="button" class="editor-btn" @click="createNewFile()">Create</button>
            </div>
        </div>

        {{-- New Folder Modal --}}
        <div class="editor-overlay" :class="{ 'visible': showNewFolderModal }" @click="showNewFolderModal = false"></div>
        <div class="editor-modal" :class="{ 'open': showNewFolderModal }">
            <div class="editor-modal-title">Create New Folder</div>
            <input type="text"
                class="editor-modal-input"
                placeholder="Enter folder path (e.g., templates/partials)"
                x-model="newFolderPath"
                x-ref="newFolderInput"
                @keydown.enter="createNewFolder()"
                @keydown.escape="showNewFolderModal = false">
            <div class="editor-modal-actions">
                <button type="button" class="editor-btn editor-btn-secondary" @click="showNewFolderModal = false">Cancel</button>
                <button type="button" class="editor-btn" @click="createNewFolder()">Create</button>
            </div>
        </div>

        {{-- Rename Modal --}}
        <div class="editor-overlay" :class="{ 'visible': showRenameModal }" @click="showRenameModal = false"></div>
        <div class="editor-modal" :class="{ 'open': showRenameModal }">
            <div class="editor-modal-title">Rename</div>
            <input type="text"
                class="editor-modal-input"
                placeholder="Enter new name"
                x-model="renamePath"
                x-ref="renameInput"
                @keydown.enter="renameItem()"
                @keydown.escape="showRenameModal = false">
            <div class="editor-modal-actions">
                <button type="button" class="editor-btn editor-btn-secondary" @click="showRenameModal = false">Cancel</button>
                <button type="button" class="editor-btn" @click="renameItem()">Rename</button>
            </div>
        </div>

        {{-- Image Preview Modal --}}
        <div class="editor-overlay" :class="{ 'visible': imagePreview.show }" @click="closeImagePreview()"></div>
        <div class="editor-modal" :class="{ 'open': imagePreview.show }" style="max-width: 90vw; max-height: 90vh;" @keydown.escape.window="if (imagePreview.show) closeImagePreview()">
            <div class="editor-modal-title" style="display: flex; align-items: center; justify-content: space-between;">
                <span x-text="imagePreview.name"></span>
                <button type="button" class="editor-sidebar-btn" @click="closeImagePreview()" title="Close">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div style="padding: 20px; text-align: center; max-height: calc(90vh - 150px); overflow: auto;">
                <img :src="imagePreview.url" :alt="imagePreview.name" style="max-width: 100%; max-height: calc(90vh - 200px); object-fit: contain; border-radius: 4px;">
            </div>
            <div class="editor-modal-actions">
                <button type="button" class="editor-btn editor-btn-secondary" @click="showRenameImageModal()">
                    <i class="ri-edit-line"></i>
                    Rename
                </button>
                <button type="button" class="editor-btn editor-btn-secondary" style="background: var(--bs-danger); border-color: var(--bs-danger); color: white;" @click="deleteImageFile()" x-show="!imagePreview.protected">
                    <i class="ri-delete-bin-line"></i>
                    Delete
                </button>
            </div>
        </div>

        {{-- Upload Modal --}}
        <div class="editor-overlay" :class="{ 'visible': showUploadModal }" @click="showUploadModal = false; uploadFiles = []"></div>
        <div class="editor-modal" :class="{ 'open': showUploadModal }" style="min-width: 450px;" @keydown.escape.window="if (showUploadModal) { showUploadModal = false; uploadFiles = []; }">
            <div class="editor-modal-title">Upload Files</div>
            <div class="upload-dropzone"
                @click="$refs.uploadInput.click()"
                @dragover.prevent="uploadDragOver = true"
                @dragleave="uploadDragOver = false"
                @drop.prevent="handleFileDrop($event)"
                :class="{ 'dragover': uploadDragOver }">
                <input type="file" x-ref="uploadInput" @change="handleFileSelect($event)" style="display: none;" multiple>
                <input type="file" x-ref="uploadFolderInput" @change="handleFileSelect($event)" style="display: none;" webkitdirectory multiple>
                <i class="ri-upload-cloud-2-line" style="font-size: 48px; opacity: 0.5;"></i>
                <div style="margin-top: 12px;">Drag files here or click to browse</div>
                <div style="margin-top: 8px;">
                    <a href="#" @click.stop.prevent="$refs.uploadFolderInput.click()" style="color: var(--editor-accent); font-size: 12px;">Upload entire folder</a>
                </div>
                <div style="font-size: 11px; color: var(--editor-text-muted); margin-top: 8px;">PHP files are not allowed</div>
            </div>
            <div x-show="uploadFiles.length > 0" style="margin-top: 16px; max-height: 150px; overflow-y: auto;">
                <template x-for="(file, index) in uploadFiles" :key="index">
                    <div class="upload-file-item">
                        <span x-text="file.webkitRelativePath || file.name" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></span>
                        <button type="button" @click="removeUploadFile(index)" class="editor-sidebar-btn" style="opacity: 0.7;">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                </template>
            </div>
            <div style="margin-top: 16px;">
                <label style="font-size: 12px; color: var(--editor-text-muted); display: block; margin-bottom: 6px;">Upload to folder:</label>
                <select class="editor-modal-input" x-model="uploadTargetPath" style="width: 100%;">
                    <option value="">/ (root)</option>
                    <template x-for="folder in getFolderList()" :key="folder.path">
                        <option :value="folder.path" x-text="'/' + folder.path"></option>
                    </template>
                </select>
            </div>
            <div class="editor-modal-actions mt-3">
                <button type="button" class="editor-btn editor-btn-secondary" @click="showUploadModal = false; uploadFiles = []">Cancel</button>
                <button type="button" class="editor-btn" @click="uploadAllFiles()" :disabled="uploadFiles.length === 0 || uploading">
                    <span x-show="!uploading">Upload</span>
                    <span x-show="uploading"><i class="ri-loader-4-line spin"></i> Uploading...</span>
                </button>
            </div>
        </div>

        {{-- Context Menu --}}
        <div class="context-menu"
            :class="{ 'open': contextMenu.show }"
            :style="{ top: contextMenu.y + 'px', left: contextMenu.x + 'px' }"
            @click.away="closeContextMenu()">
            <template x-if="contextMenu.type === 'file'">
                <div>
                    <div class="context-menu-item" @click="contextMenuOpenFile()">
                        <i class="context-menu-icon" :class="isImageFile(contextMenu.item?.path) ? 'ri-image-line' : 'ri-file-line'"></i>
                        <span x-text="isImageFile(contextMenu.item?.path) ? 'View Image' : 'Open'"></span>
                    </div>
                    <div class="context-menu-item" @click="contextMenuShowRevisions()" x-show="!isImageFile(contextMenu.item?.path)">
                        <i class="context-menu-icon ri-history-line"></i>
                        Show History
                    </div>
                    <div class="context-menu-item" @click="contextMenuDuplicate()">
                        <i class="context-menu-icon ri-file-copy-line"></i>
                        Duplicate
                    </div>
                    <div class="context-menu-separator"></div>
                    <div class="context-menu-item" @click="contextMenuRename()">
                        <i class="context-menu-icon ri-edit-line"></i>
                        Rename
                    </div>
                    <div class="context-menu-item danger" @click="contextMenuDelete()" x-show="!contextMenu.item?.protected">
                        <i class="context-menu-icon ri-delete-bin-line"></i>
                        Delete
                    </div>
                </div>
            </template>
            <template x-if="contextMenu.type === 'folder'">
                <div>
                    <div class="context-menu-item" @click="contextMenuNewFileInFolder()">
                        <i class="context-menu-icon ri-file-add-line"></i>
                        New File
                    </div>
                    <div class="context-menu-item" @click="contextMenuNewFolderInFolder()">
                        <i class="context-menu-icon ri-folder-add-line"></i>
                        New Folder
                    </div>
                    <div class="context-menu-separator"></div>
                    <div class="context-menu-item" @click="contextMenuRename()">
                        <i class="context-menu-icon ri-edit-line"></i>
                        Rename
                    </div>
                    <div class="context-menu-item danger" @click="contextMenuDeleteFolder()">
                        <i class="context-menu-icon ri-delete-bin-line"></i>
                        Delete Folder
                    </div>
                </div>
            </template>
            <template x-if="contextMenu.type === 'tree'">
                <div>
                    <div class="context-menu-item" @click="showNewFileModal = true; closeContextMenu()">
                        <i class="context-menu-icon ri-file-add-line"></i>
                        New File
                    </div>
                    <div class="context-menu-item" @click="showNewFolderModal = true; closeContextMenu()">
                        <i class="context-menu-icon ri-folder-add-line"></i>
                        New Folder
                    </div>
                    <div class="context-menu-separator"></div>
                    <div class="context-menu-item" @click="refreshFiles(); closeContextMenu()">
                        <i class="context-menu-icon ri-refresh-line"></i>
                        Refresh
                    </div>
                </div>
            </template>
            <template x-if="contextMenu.type === 'tab'">
                <div>
                    <div class="context-menu-item" @click="closeTab(contextMenu.item.path); closeContextMenu()">
                        <i class="context-menu-icon ri-close-line"></i>
                        Close
                    </div>
                    <div class="context-menu-item" @click="closeOtherTabs(contextMenu.item.path); closeContextMenu()">
                        <i class="context-menu-icon ri-close-circle-line"></i>
                        Close Others
                    </div>
                    <div class="context-menu-item" @click="closeAllTabs(); closeContextMenu()">
                        <i class="context-menu-icon ri-checkbox-blank-circle-line"></i>
                        Close All
                    </div>
                    <div class="context-menu-separator"></div>
                    <div class="context-menu-item" @click="contextMenuShowRevisions()">
                        <i class="context-menu-icon ri-history-line"></i>
                        Show History
                    </div>
                </div>
            </template>
            <template x-if="contextMenu.type === 'commit'">
                <div>
                    <div class="context-menu-item" @click="contextMenuCloseCommitTab()">
                        <i class="context-menu-icon ri-close-line"></i>
                        Close
                    </div>
                    <div class="context-menu-item" @click="contextMenuCloseOtherTabsFromCommit()">
                        <i class="context-menu-icon ri-close-circle-line"></i>
                        Close Others
                    </div>
                    <div class="context-menu-item" @click="contextMenuCloseAllTabsFromCommit()">
                        <i class="context-menu-icon ri-checkbox-blank-circle-line"></i>
                        Close All
                    </div>
                </div>
            </template>
        </div>

        {{-- Teleported Status Bar Items --}}
        <template x-teleport=".editor-statusbar-left">
            <div style="display: contents">
                <div class="editor-statusbar-item">
                    <i class="ri-git-branch-line"></i>
                    <span>{{ $themeDirectory }}</span>
                </div>
                <div class="editor-statusbar-item" x-show="activeTab" x-text="activeTab"></div>
            </div>
        </template>

        <template x-teleport=".editor-statusbar-right">
            <div style="display: contents">
                <div class="editor-statusbar-item" x-show="activeTab">
                    <span x-text="'Ln ' + cursorLine + ', Col ' + cursorColumn"></span>
                </div>
                <div class="editor-statusbar-item" x-show="activeTab" x-text="currentLanguage.toUpperCase()"></div>
                <div class="editor-statusbar-item clickable" x-show="activeTab" @click="toggleCommitsTab()">
                    <i class="ri-history-line"></i>
                    <span>History</span>
                </div>
            </div>
        </template>
    </div>
</x-editor-layout>
