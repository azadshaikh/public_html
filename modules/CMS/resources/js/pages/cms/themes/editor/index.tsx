import { Link, useHttp, usePage } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    CheckCircle2Icon,
    ChevronsDownUpIcon,
    CodeIcon,
    CopyIcon,
    FileCodeIcon,
    FilesIcon,
    FolderIcon,
    GitBranchIcon,
    GitCommitHorizontalIcon,
    HistoryIcon,
    InfoIcon,
    MoreHorizontalIcon,
    PanelLeftCloseIcon,
    PanelLeftOpenIcon,
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
    SaveIcon,
    SearchIcon,
    SettingsIcon,
    Trash2Icon,
    UploadIcon,
    XIcon,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { MonacoEditor } from '@/components/code-editor/monaco-editor';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { AuthenticatedSharedData } from '@/types';
import {
    EditorContextMenu
    
} from '../../../../components/theme-editor/editor-context-menu';
import type {EditorContextMenuEntry} from '../../../../components/theme-editor/editor-context-menu';
import {
    CreateDialog,
    DeleteConfirmDialog,
    HistoryDialog,
    RenameDialog,
    UploadDialog,
} from '../../../../components/theme-editor/editor-dialogs';
import { FileTreeItem } from '../../../../components/theme-editor/file-tree-item';
import ThemeEditorLayout from '../../../../components/theme-editor/theme-editor-layout';
import type {
    ThemeEditorFileNode,
    ThemeEditorPageProps,
} from '../../../../types/cms';
import type {
    ActivityBarItem,
    DeleteTarget,
    EditorTab,
    FileReadResponse,
    FileTreeResponse,
    GenericResponse,
    GitChange,
    GitCommit,
    GitHistoryResponse,
    GitMutationPayload,
    GitStatusResponse,
    NewEntityMode,
    SearchGroup,
    SearchPayload,
    SearchResponse,
    SidebarView,
    UploadPayload,
} from './types';
import {
    findNodeByPath,
    formatBytes,
    getErrorMessage,
    getParentDirectory,
} from './utils';

const activityBarItems: ActivityBarItem[] = [
    { id: 'explorer', icon: FilesIcon, label: 'Explorer' },
    { id: 'search', icon: SearchIcon, label: 'Search' },
    { id: 'source-control', icon: GitBranchIcon, label: 'Source Control' },
];

export default function ThemeEditorIndex({
    theme,
    themeDirectory,
    files,
    isChildTheme,
    parentTheme,
}: ThemeEditorPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEditThemes = Boolean(page.props.auth.abilities.editThemes);
    const canDeleteThemes = Boolean(page.props.auth.abilities.deleteThemes);

    const [tree, setTree] = useState<ThemeEditorFileNode[]>(files);
    const [expandedPaths, setExpandedPaths] = useState<Set<string>>(new Set());
    const [selectedPath, setSelectedPath] = useState<string | null>(null);
    const [openTabs, setOpenTabs] = useState<EditorTab[]>([]);
    const [activePath, setActivePath] = useState<string | null>(null);
    const [sidebarView, setSidebarView] = useState<SidebarView>('explorer');
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
    const activePathRef = useRef<string | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [searchOptions, setSearchOptions] = useState<Array<'case' | 'regex'>>(
        [],
    );
    const [searchResults, setSearchResults] = useState<SearchGroup[]>([]);
    const [searchTotal, setSearchTotal] = useState(0);
    const [gitChanges, setGitChanges] = useState<GitChange[]>([]);
    const [commitMessage, setCommitMessage] = useState('');
    const [newEntityMode, setNewEntityMode] = useState<NewEntityMode>('file');
    const [newEntityOpen, setNewEntityOpen] = useState(false);
    const [newEntityPath, setNewEntityPath] = useState('');
    const [renameOpen, setRenameOpen] = useState(false);
    const [renameSource, setRenameSource] =
        useState<ThemeEditorFileNode | null>(null);
    const [renamePath, setRenamePath] = useState('');
    const [uploadOpen, setUploadOpen] = useState(false);
    const [uploadTargetPath, setUploadTargetPath] = useState('');
    const [deleteTarget, setDeleteTarget] = useState<DeleteTarget | null>(null);
    const [historyOpen, setHistoryOpen] = useState(false);
    const [historyItems, setHistoryItems] = useState<GitCommit[]>([]);

    const treeRequest = useHttp<Record<string, never>, FileTreeResponse>({});
    const readRequest = useHttp<{ path: string }, FileReadResponse>({
        path: '',
    });
    const saveRequest = useHttp<
        { path: string; content: string; label?: string },
        GenericResponse
    >({
        path: '',
        content: '',
        label: '',
    });
    const createRequest = useHttp<
        { path: string; content?: string },
        GenericResponse
    >({
        path: '',
        content: '',
    });
    const uploadRequest = useHttp<UploadPayload, GenericResponse>({
        file: null,
        path: '',
        overwrite: false,
    });
    const renameRequest = useHttp<
        { old_path: string; new_path: string },
        GenericResponse
    >({
        old_path: '',
        new_path: '',
    });
    const duplicateRequest = useHttp<{ path: string }, GenericResponse>({
        path: '',
    });
    const deleteRequest = useHttp<Record<string, never>, GenericResponse>({});
    const searchRequest = useHttp<SearchPayload, SearchResponse>({
        query: '',
        case_sensitive: false,
        use_regex: false,
        max_results: 200,
    });
    const gitStatusRequest = useHttp<Record<string, never>, GitStatusResponse>(
        {},
    );
    const gitMutationRequest = useHttp<GitMutationPayload, GenericResponse>({});
    const historyRequest = useHttp<Record<string, never>, GitHistoryResponse>(
        {},
    );

    const activeTab = useMemo(
        () => openTabs.find((tab) => tab.path === activePath) ?? null,
        [activePath, openTabs],
    );

    const hasDirtyTabs = useMemo(
        () => openTabs.some((tab) => tab.content !== tab.originalContent),
        [openTabs],
    );

    const stagedChanges = useMemo(
        () => gitChanges.filter((change) => change.staged),
        [gitChanges],
    );
    const unstagedChanges = useMemo(
        () => gitChanges.filter((change) => change.unstaged),
        [gitChanges],
    );

    const selectedDirectory = useMemo(() => {
        if (!selectedPath) {
            return '';
        }

        const selectedNode = findNodeByPath(tree, selectedPath);
        if (selectedNode?.type === 'directory') {
            return selectedNode.path;
        }

        return getParentDirectory(selectedPath);
    }, [selectedPath, tree]);

    const toggleFolder = useCallback((path: string) => {
        setExpandedPaths((prev) => {
            const next = new Set(prev);
            if (next.has(path)) {
                next.delete(path);
            } else {
                next.add(path);
            }

            return next;
        });
    }, []);

    const refreshTree = useCallback(async () => {
        try {
            const payload = await treeRequest.get(
                route('cms.appearance.themes.editor.files', {
                    directory: themeDirectory,
                }),
            );
            setTree(payload.files ?? []);
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Refresh failed',
                description: getErrorMessage(
                    error,
                    'Unable to refresh the file tree.',
                ),
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [themeDirectory]);

    const refreshGitStatus = useCallback(async () => {
        try {
            const payload = await gitStatusRequest.get(
                route('cms.appearance.themes.editor.git.status', {
                    directory: themeDirectory,
                }),
            );
            setGitChanges(payload.changes ?? []);
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Source control failed',
                description: getErrorMessage(
                    error,
                    'Unable to load git status.',
                ),
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [themeDirectory]);

    const loadHistory = useCallback(async () => {
        try {
            const payload = await historyRequest.get(
                route('cms.appearance.themes.editor.git.history.all', {
                    directory: themeDirectory,
                }),
            );
            setHistoryItems(payload.commits ?? []);
            setHistoryOpen(true);
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'History failed',
                description: getErrorMessage(
                    error,
                    'Unable to load commit history.',
                ),
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [themeDirectory]);

    const openFile = useCallback(
        async (path: string) => {
            setSelectedPath(path);

            const existing = openTabs.find((tab) => tab.path === path);
            if (existing) {
                setActivePath(path);
                return;
            }

            try {
                readRequest.transform(() => ({ path }));
                const payload = await readRequest.post(
                    route('cms.appearance.themes.editor.file.read', {
                        directory: themeDirectory,
                    }),
                );

                const nextTab: EditorTab = {
                    path,
                    name: path.split('/').at(-1) ?? path,
                    content: payload.content,
                    originalContent: payload.content,
                    language: payload.language,
                    size: payload.size,
                    modified: payload.modified,
                    inherited: payload.inherited,
                    inheritedFrom: payload.inheritedFrom,
                };

                setOpenTabs((prev) => [...prev, nextTab]);
                setActivePath(path);
            } catch (error) {
                showAppToast({
                    variant: 'error',
                    title: 'Open failed',
                    description: getErrorMessage(
                        error,
                        'Unable to open the selected file.',
                    ),
                });
            }
             
        },
        [openTabs, themeDirectory],
    );

    const saveActiveTab = useCallback(async () => {
        if (!activeTab) {
            return;
        }

        try {
            saveRequest.transform(() => ({
                path: activeTab.path,
                content: activeTab.content,
                label: activeTab.path,
            }));
            const payload = await saveRequest.post(
                route('cms.appearance.themes.editor.file.save', {
                    directory: themeDirectory,
                }),
            );

            if (payload.success === false) {
                throw new Error(
                    payload.message || payload.error || 'Save failed.',
                );
            }

            setOpenTabs((prev) =>
                prev.map((tab) =>
                    tab.path === activeTab.path
                        ? {
                              ...tab,
                              originalContent: tab.content,
                              inherited: false,
                              inheritedFrom: null,
                              modified: Math.floor(Date.now() / 1000),
                          }
                        : tab,
                ),
            );

            await Promise.all([refreshTree(), refreshGitStatus()]);

            showAppToast({
                variant: 'success',
                title: 'File saved',
                description:
                    payload.message ||
                    `${activeTab.name} was saved successfully.`,
            });
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Save failed',
                description: getErrorMessage(
                    error,
                    'Unable to save the active file.',
                ),
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [activeTab, refreshGitStatus, refreshTree, themeDirectory]);

    const closeTab = useCallback(
        (path: string) => {
            const closingTab = openTabs.find((tab) => tab.path === path);
            if (
                closingTab &&
                closingTab.content !== closingTab.originalContent
            ) {
                const shouldClose = window.confirm(
                    `Discard unsaved changes in ${closingTab.name}?`,
                );
                if (!shouldClose) {
                    return;
                }
            }

            const nextTabs = openTabs.filter((tab) => tab.path !== path);
            setOpenTabs(nextTabs);

            if (activePath === path) {
                setActivePath(nextTabs.at(-1)?.path ?? null);
            }
        },
        [activePath, openTabs],
    );

    const closeOtherTabs = useCallback(
        (keepPath: string) => {
            const dirtyTabs = openTabs.filter(
                (tab) =>
                    tab.path !== keepPath &&
                    tab.content !== tab.originalContent,
            );
            if (dirtyTabs.length > 0) {
                const shouldClose = window.confirm(
                    `Discard unsaved changes in ${dirtyTabs.length} file(s)?`,
                );
                if (!shouldClose) {
                    return;
                }
            }

            setOpenTabs((prev) => prev.filter((tab) => tab.path === keepPath));
            setActivePath(keepPath);
        },
        [openTabs],
    );

    const closeAllTabs = useCallback(() => {
        const dirtyTabs = openTabs.filter(
            (tab) => tab.content !== tab.originalContent,
        );
        if (dirtyTabs.length > 0) {
            const shouldClose = window.confirm(
                `Discard unsaved changes in ${dirtyTabs.length} file(s)?`,
            );
            if (!shouldClose) {
                return;
            }
        }

        setOpenTabs([]);
        setActivePath(null);
    }, [openTabs]);

    const closeTabsToRight = useCallback(
        (path: string) => {
            const tabIndex = openTabs.findIndex((tab) => tab.path === path);
            const rightTabs = openTabs.slice(tabIndex + 1);
            const dirtyRight = rightTabs.filter(
                (tab) => tab.content !== tab.originalContent,
            );
            if (dirtyRight.length > 0) {
                const shouldClose = window.confirm(
                    `Discard unsaved changes in ${dirtyRight.length} file(s)?`,
                );
                if (!shouldClose) {
                    return;
                }
            }

            const kept = openTabs.slice(0, tabIndex + 1);
            setOpenTabs(kept);
            if (activePath && !kept.some((tab) => tab.path === activePath)) {
                setActivePath(kept.at(-1)?.path ?? null);
            }
        },
        [activePath, openTabs],
    );

    const copyPathToClipboard = useCallback((path: string) => {
        void navigator.clipboard.writeText(path);
        showAppToast({
            variant: 'success',
            title: 'Copied',
            description: 'Path copied to clipboard.',
        });
    }, []);

    useEffect(() => {
        activePathRef.current = activePath;
    }, [activePath]);

    const updateActiveTabContent = useCallback((value: string) => {
        const currentPath = activePathRef.current;
        if (!currentPath) {
            return;
        }

        setOpenTabs((prev) =>
            prev.map((tab) =>
                tab.path === currentPath ? { ...tab, content: value } : tab,
            ),
        );
    }, []);

    const runSearch = useCallback(async () => {
        if (searchQuery.trim() === '') {
            setSearchResults([]);
            setSearchTotal(0);
            return;
        }

        try {
            searchRequest.transform(() => ({
                query: searchQuery.trim(),
                case_sensitive: searchOptions.includes('case'),
                use_regex: searchOptions.includes('regex'),
                max_results: 200,
            }));

            const payload = await searchRequest.post(
                route('cms.appearance.themes.editor.search', {
                    directory: themeDirectory,
                }),
            );

            setSearchResults(payload.results ?? []);
            setSearchTotal(payload.total_matches ?? 0);
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Search failed',
                description: getErrorMessage(
                    error,
                    'Unable to search theme files.',
                ),
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchOptions, searchQuery, themeDirectory]);

    const runGitAction = useCallback(
        async (
            method: 'post' | 'delete',
            url: string,
            payload: GitMutationPayload = {},
            successTitle = 'Updated',
        ) => {
            try {
                gitMutationRequest.transform(() => payload);
                const response =
                    method === 'delete'
                        ? await gitMutationRequest.delete(url)
                        : await gitMutationRequest.post(url);

                if (response.success === false) {
                    throw new Error(
                        response.message || response.error || 'Action failed.',
                    );
                }

                await Promise.all([refreshGitStatus(), refreshTree()]);

                showAppToast({
                    variant: 'success',
                    title: successTitle,
                    description:
                        response.message ||
                        'The requested action completed successfully.',
                });
            } catch (error) {
                showAppToast({
                    variant: 'error',
                    title: 'Action failed',
                    description: getErrorMessage(
                        error,
                        'Unable to complete the requested action.',
                    ),
                });
            }
             
        },
        [refreshGitStatus, refreshTree],
    );

    const createEntity = async () => {
        if (newEntityPath.trim() === '') {
            return;
        }

        try {
            createRequest.transform(() => ({ path: newEntityPath.trim() }));
            const url =
                newEntityMode === 'file'
                    ? route('cms.appearance.themes.editor.file.create', {
                          directory: themeDirectory,
                      })
                    : route('cms.appearance.themes.editor.folder.create', {
                          directory: themeDirectory,
                      });
            const response = await createRequest.post(url);

            if (response.success === false) {
                throw new Error(
                    response.message || response.error || 'Creation failed.',
                );
            }

            setNewEntityOpen(false);
            setNewEntityPath('');
            await refreshTree();

            if (newEntityMode === 'file') {
                await openFile(newEntityPath.trim());
            }

            showAppToast({
                variant: 'success',
                title:
                    newEntityMode === 'file'
                        ? 'File created'
                        : 'Folder created',
                description: response.message || 'Created successfully.',
            });
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Creation failed',
                description: getErrorMessage(
                    error,
                    'Unable to create the requested item.',
                ),
            });
        }
    };

    const handleRename = async () => {
        if (!renameSource || renamePath.trim() === '') {
            return;
        }

        try {
            renameRequest.transform(() => ({
                old_path: renameSource.path,
                new_path: renamePath.trim(),
            }));
            const response = await renameRequest.post(
                route('cms.appearance.themes.editor.rename', {
                    directory: themeDirectory,
                }),
            );

            if (response.success === false) {
                throw new Error(
                    response.message || response.error || 'Rename failed.',
                );
            }

            const nextPath = renamePath.trim();
            setOpenTabs((prev) =>
                prev.map((tab) =>
                    tab.path === renameSource.path
                        ? {
                              ...tab,
                              path: nextPath,
                              name: nextPath.split('/').at(-1) ?? nextPath,
                          }
                        : tab,
                ),
            );
            if (activePath === renameSource.path) {
                setActivePath(nextPath);
            }
            if (selectedPath === renameSource.path) {
                setSelectedPath(nextPath);
            }

            setRenameOpen(false);
            setRenameSource(null);
            setRenamePath('');
            await refreshTree();

            showAppToast({
                variant: 'success',
                title: 'Renamed',
                description: response.message || 'Item renamed successfully.',
            });
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Rename failed',
                description: getErrorMessage(
                    error,
                    'Unable to rename the selected item.',
                ),
            });
        }
    };

    const handleDuplicate = async (path: string) => {
        try {
            duplicateRequest.transform(() => ({ path }));
            const response = await duplicateRequest.post(
                route('cms.appearance.themes.editor.duplicate', {
                    directory: themeDirectory,
                }),
            );

            if (response.success === false) {
                throw new Error(
                    response.message || response.error || 'Duplicate failed.',
                );
            }

            await refreshTree();

            showAppToast({
                variant: 'success',
                title: 'Duplicated',
                description:
                    response.message || 'File duplicated successfully.',
            });
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Duplicate failed',
                description: getErrorMessage(
                    error,
                    'Unable to duplicate the selected file.',
                ),
            });
        }
    };

    const confirmDelete = async () => {
        if (!deleteTarget) {
            return;
        }

        const url =
            deleteTarget.type === 'file'
                ? route('cms.appearance.themes.editor.file.delete', {
                      directory: themeDirectory,
                      path: deleteTarget.path,
                  })
                : route('cms.appearance.themes.editor.folder.delete', {
                      directory: themeDirectory,
                      path: deleteTarget.path,
                  });

        try {
            const response = await deleteRequest.delete(url);
            if (response.success === false) {
                throw new Error(
                    response.message || response.error || 'Delete failed.',
                );
            }

            if (deleteTarget.type === 'file') {
                closeTab(deleteTarget.path);
            }

            setDeleteTarget(null);
            await refreshTree();
            await refreshGitStatus();

            showAppToast({
                variant: 'success',
                title: 'Deleted',
                description: response.message || 'Item deleted successfully.',
            });
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Delete failed',
                description: getErrorMessage(
                    error,
                    'Unable to delete the selected item.',
                ),
            });
        }
    };

    const submitUpload = async () => {
        if (!uploadRequest.data.file) {
            return;
        }

        try {
            uploadRequest.transform(() => ({
                file: uploadRequest.data.file,
                path: uploadTargetPath.trim(),
                overwrite: uploadRequest.data.overwrite,
            }));
            const response = await uploadRequest.post(
                route('cms.appearance.themes.editor.upload', {
                    directory: themeDirectory,
                }),
            );

            if (response.success === false) {
                throw new Error(
                    response.message || response.error || 'Upload failed.',
                );
            }

            setUploadOpen(false);
            uploadRequest.reset();
            setUploadTargetPath('');
            await refreshTree();
            await refreshGitStatus();

            showAppToast({
                variant: 'success',
                title: 'Upload complete',
                description: response.message || 'File uploaded successfully.',
            });
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Upload failed',
                description: getErrorMessage(
                    error,
                    'Unable to upload the selected file.',
                ),
            });
        }
    };

    useEffect(() => {
        void refreshGitStatus();
    }, [refreshGitStatus]);

    useEffect(() => {
        const handleKeydown = (event: KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 's'
            ) {
                event.preventDefault();

                if (
                    !saveRequest.processing &&
                    activeTab &&
                    activeTab.content !== activeTab.originalContent
                ) {
                    void saveActiveTab();
                }
            }
        };

        window.addEventListener('keydown', handleKeydown);

        return () => {
            window.removeEventListener('keydown', handleKeydown);
        };
    }, [activeTab, saveActiveTab, saveRequest.processing]);

    useEffect(() => {
        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            if (!hasDirtyTabs) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            window.removeEventListener('beforeunload', handleBeforeUnload);
        };
    }, [hasDirtyTabs]);

    const toggleSidebar = useCallback(
        (view: SidebarView) => {
            if (sidebarView === view && !sidebarCollapsed) {
                setSidebarCollapsed(true);
            } else {
                setSidebarView(view);
                setSidebarCollapsed(false);
            }
        },
        [sidebarCollapsed, sidebarView],
    );

    const isDirtyTab = activeTab
        ? activeTab.content !== activeTab.originalContent
        : false;

    return (
        <ThemeEditorLayout
            title={`${theme.name} — Editor`}
            description="Theme file editor"
        >
            <TooltipProvider delayDuration={300}>
                {/* Title bar */}
                <div className="flex h-10 shrink-0 items-center justify-between border-b border-[#2b2b2b] bg-[#323233] px-2">
                    <div className="flex items-center gap-2">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button variant="ghost" size="icon-sm" asChild>
                                    <Link
                                        href={route(
                                            'cms.appearance.themes.index',
                                        )}
                                    >
                                        <ArrowLeftIcon className="size-4" />
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">
                                Back to themes
                            </TooltipContent>
                        </Tooltip>
                        <div className="flex items-center gap-2 text-sm">
                            <span className="font-medium">{theme.name}</span>
                            {theme.is_active ? (
                                <Badge
                                    variant="outline"
                                    className="text-[10px]"
                                >
                                    Active
                                </Badge>
                            ) : null}
                            {isChildTheme ? (
                                <Badge
                                    variant="secondary"
                                    className="text-[10px]"
                                >
                                    Child
                                </Badge>
                            ) : null}
                            <span className="text-muted-foreground">
                                v{theme.version}
                            </span>
                        </div>
                    </div>
                    <div className="flex items-center gap-1">
                        {isChildTheme && parentTheme ? (
                            <span className="mr-2 text-xs text-muted-foreground">
                                Child of <strong>{parentTheme.name}</strong>
                            </span>
                        ) : null}
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    onClick={() => void refreshTree()}
                                    disabled={treeRequest.processing}
                                >
                                    {treeRequest.processing ? (
                                        <Spinner className="size-4" />
                                    ) : (
                                        <RefreshCwIcon className="size-4" />
                                    )}
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">
                                Refresh file tree
                            </TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    onClick={() => void saveActiveTab()}
                                    disabled={
                                        !activeTab ||
                                        !isDirtyTab ||
                                        saveRequest.processing ||
                                        !canEditThemes
                                    }
                                >
                                    {saveRequest.processing ? (
                                        <Spinner className="size-4" />
                                    ) : (
                                        <SaveIcon className="size-4" />
                                    )}
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">
                                Save file (Ctrl+S)
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </div>

                {/* Main body */}
                <div className="flex min-h-0 flex-1">
                    {/* Activity bar */}
                    <div className="flex w-12 shrink-0 flex-col items-center gap-1 border-r border-[#2b2b2b] bg-[#181818] pt-2 pb-2">
                        {activityBarItems.map((item) => {
                            const Icon = item.icon;
                            const isActive =
                                sidebarView === item.id && !sidebarCollapsed;
                            return (
                                <Tooltip key={item.id}>
                                    <TooltipTrigger asChild>
                                        <button
                                            type="button"
                                            className={cn(
                                                'flex size-10 items-center justify-center rounded-md transition-colors',
                                                isActive
                                                    ? 'bg-accent text-accent-foreground'
                                                    : 'text-muted-foreground hover:bg-accent/50 hover:text-accent-foreground',
                                            )}
                                            onClick={() =>
                                                toggleSidebar(item.id)
                                            }
                                        >
                                            <Icon className="size-5" />
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent side="right">
                                        {item.label}
                                    </TooltipContent>
                                </Tooltip>
                            );
                        })}
                        <div className="flex-1" />
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button
                                    type="button"
                                    className="flex size-10 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent/50 hover:text-accent-foreground"
                                    onClick={() =>
                                        setSidebarCollapsed((prev) => !prev)
                                    }
                                >
                                    {sidebarCollapsed ? (
                                        <PanelLeftOpenIcon className="size-5" />
                                    ) : (
                                        <PanelLeftCloseIcon className="size-5" />
                                    )}
                                </button>
                            </TooltipTrigger>
                            <TooltipContent side="right">
                                {sidebarCollapsed
                                    ? 'Open sidebar'
                                    : 'Close sidebar'}
                            </TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button variant="ghost" size="icon-sm" asChild>
                                    <Link
                                        href={route(
                                            'cms.appearance.themes.index',
                                        )}
                                    >
                                        <SettingsIcon className="size-4" />
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="right">
                                Theme settings
                            </TooltipContent>
                        </Tooltip>
                    </div>

                    {/* Sidebar + Editor */}
                    <div className="flex min-h-0 min-w-0 flex-1 overflow-hidden">
                        {!sidebarCollapsed ? (
                            <div className="flex w-64 shrink-0 flex-col overflow-hidden border-r border-[#2b2b2b] bg-[#252526]">
                                {/* Side panel header */}
                                <div className="flex h-9 shrink-0 items-center justify-between border-b border-[#2b2b2b] px-3">
                                    <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        {sidebarView === 'explorer'
                                            ? 'Explorer'
                                            : sidebarView === 'search'
                                              ? 'Search'
                                              : 'Source Control'}
                                    </span>
                                    {sidebarView === 'explorer' ? (
                                        <div className="flex gap-0.5">
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <button
                                                        type="button"
                                                        className="flex size-6 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                                        onClick={() => {
                                                            setNewEntityMode(
                                                                'file',
                                                            );
                                                            setNewEntityPath(
                                                                selectedDirectory
                                                                    ? `${selectedDirectory}/`
                                                                    : '',
                                                            );
                                                            setNewEntityOpen(
                                                                true,
                                                            );
                                                        }}
                                                        disabled={
                                                            !canEditThemes
                                                        }
                                                    >
                                                        <PlusIcon className="size-4" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent side="bottom">
                                                    New file
                                                </TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <button
                                                        type="button"
                                                        className="flex size-6 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                                        onClick={() => {
                                                            setNewEntityMode(
                                                                'folder',
                                                            );
                                                            setNewEntityPath(
                                                                selectedDirectory
                                                                    ? `${selectedDirectory}/`
                                                                    : '',
                                                            );
                                                            setNewEntityOpen(
                                                                true,
                                                            );
                                                        }}
                                                        disabled={
                                                            !canEditThemes
                                                        }
                                                    >
                                                        <FolderIcon className="size-4" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent side="bottom">
                                                    New folder
                                                </TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <button
                                                        type="button"
                                                        className="flex size-6 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                                        onClick={() => {
                                                            setUploadTargetPath(
                                                                selectedDirectory,
                                                            );
                                                            setUploadOpen(true);
                                                        }}
                                                        disabled={
                                                            !canEditThemes
                                                        }
                                                    >
                                                        <UploadIcon className="size-4" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent side="bottom">
                                                    Upload file
                                                </TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <button
                                                        type="button"
                                                        className="flex size-6 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                                        onClick={() =>
                                                            setExpandedPaths(
                                                                new Set(),
                                                            )
                                                        }
                                                    >
                                                        <ChevronsDownUpIcon className="size-4" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent side="bottom">
                                                    Collapse all
                                                </TooltipContent>
                                            </Tooltip>
                                        </div>
                                    ) : null}
                                    {sidebarView === 'source-control' ? (
                                        <div className="flex gap-0.5">
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <button
                                                        type="button"
                                                        className="flex size-6 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                                        onClick={() =>
                                                            void refreshGitStatus()
                                                        }
                                                        disabled={
                                                            gitStatusRequest.processing
                                                        }
                                                    >
                                                        <RefreshCwIcon className="size-3.5" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent side="bottom">
                                                    Refresh
                                                </TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <button
                                                        type="button"
                                                        className="flex size-6 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                                        onClick={() =>
                                                            void loadHistory()
                                                        }
                                                        disabled={
                                                            historyRequest.processing
                                                        }
                                                    >
                                                        <HistoryIcon className="size-3.5" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent side="bottom">
                                                    History
                                                </TooltipContent>
                                            </Tooltip>
                                        </div>
                                    ) : null}
                                </div>

                                {/* Explorer */}
                                {sidebarView === 'explorer' ? (
                                    <ScrollArea className="min-h-0 flex-1">
                                        <div className="flex flex-col py-1">
                                            {tree.length > 0 ? (
                                                tree.map((node) => (
                                                    <FileTreeItem
                                                        key={node.path}
                                                        node={node}
                                                        depth={0}
                                                        expandedPaths={
                                                            expandedPaths
                                                        }
                                                        selectedPath={
                                                            selectedPath
                                                        }
                                                        activePath={activePath}
                                                        onToggle={toggleFolder}
                                                        onOpen={(path) =>
                                                            void openFile(path)
                                                        }
                                                        onSelect={
                                                            setSelectedPath
                                                        }
                                                        onCreateFile={(
                                                            path,
                                                        ) => {
                                                            setNewEntityMode(
                                                                'file',
                                                            );
                                                            setNewEntityPath(
                                                                path
                                                                    ? `${path}/`
                                                                    : '',
                                                            );
                                                            setNewEntityOpen(
                                                                true,
                                                            );
                                                        }}
                                                        onCreateFolder={(
                                                            path,
                                                        ) => {
                                                            setNewEntityMode(
                                                                'folder',
                                                            );
                                                            setNewEntityPath(
                                                                path
                                                                    ? `${path}/`
                                                                    : '',
                                                            );
                                                            setNewEntityOpen(
                                                                true,
                                                            );
                                                        }}
                                                        onUpload={(path) => {
                                                            setUploadTargetPath(
                                                                path,
                                                            );
                                                            setUploadOpen(true);
                                                        }}
                                                        onRename={(
                                                            nodeToRename,
                                                        ) => {
                                                            setRenameSource(
                                                                nodeToRename,
                                                            );
                                                            setRenamePath(
                                                                nodeToRename.path,
                                                            );
                                                            setRenameOpen(true);
                                                        }}
                                                        onDuplicate={(path) =>
                                                            void handleDuplicate(
                                                                path,
                                                            )
                                                        }
                                                        onDelete={
                                                            setDeleteTarget
                                                        }
                                                    />
                                                ))
                                            ) : (
                                                <div className="px-3 py-6 text-center text-xs text-muted-foreground">
                                                    No editable files.
                                                </div>
                                            )}
                                        </div>
                                    </ScrollArea>
                                ) : null}

                                {/* Search */}
                                {sidebarView === 'search' ? (
                                    <div className="flex min-h-0 flex-1 flex-col">
                                        <div className="flex flex-col gap-2 border-b px-3 py-2">
                                            <Input
                                                value={searchQuery}
                                                onChange={(event) =>
                                                    setSearchQuery(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Search in files…"
                                                onKeyDown={(event) => {
                                                    if (event.key === 'Enter') {
                                                        void runSearch();
                                                    }
                                                }}
                                                className="h-7 text-xs"
                                            />
                                            <div className="flex items-center justify-between">
                                                <ToggleGroup
                                                    type="multiple"
                                                    value={searchOptions}
                                                    onValueChange={(value) =>
                                                        setSearchOptions(
                                                            value as Array<
                                                                'case' | 'regex'
                                                            >,
                                                        )
                                                    }
                                                    variant="outline"
                                                >
                                                    <ToggleGroupItem
                                                        value="case"
                                                        className="h-6 px-2 text-[10px]"
                                                    >
                                                        Aa
                                                    </ToggleGroupItem>
                                                    <ToggleGroupItem
                                                        value="regex"
                                                        className="h-6 px-2 text-[10px]"
                                                    >
                                                        .*
                                                    </ToggleGroupItem>
                                                </ToggleGroup>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        void runSearch()
                                                    }
                                                    disabled={
                                                        searchRequest.processing ||
                                                        searchQuery.trim() ===
                                                            ''
                                                    }
                                                    className="h-6 px-2 text-xs"
                                                >
                                                    {searchRequest.processing ? (
                                                        <Spinner className="size-3" />
                                                    ) : (
                                                        'Search'
                                                    )}
                                                </Button>
                                            </div>
                                        </div>
                                        <ScrollArea className="min-h-0 flex-1">
                                            <div className="flex flex-col">
                                                {searchQuery.trim() !== '' ? (
                                                    <div className="border-b px-3 py-1.5 text-[11px] text-muted-foreground">
                                                        {searchTotal} results
                                                    </div>
                                                ) : null}
                                                {searchResults.map((group) => (
                                                    <div key={group.path}>
                                                        <div className="bg-muted/40 px-3 py-1 text-[11px] font-medium">
                                                            {group.path}{' '}
                                                            <span className="text-muted-foreground">
                                                                (
                                                                {
                                                                    group.match_count
                                                                }
                                                                )
                                                            </span>
                                                        </div>
                                                        {group.matches.map(
                                                            (match) => (
                                                                <button
                                                                    key={`${group.path}-${match.line}-${match.column}`}
                                                                    type="button"
                                                                    className="flex w-full items-baseline gap-2 px-3 py-1 text-left text-[11px] hover:bg-accent/50"
                                                                    onClick={() =>
                                                                        void openFile(
                                                                            match.path,
                                                                        )
                                                                    }
                                                                >
                                                                    <span className="shrink-0 text-muted-foreground">
                                                                        {
                                                                            match.line
                                                                        }
                                                                    </span>
                                                                    <span className="truncate font-mono">
                                                                        {
                                                                            match.text
                                                                        }
                                                                    </span>
                                                                </button>
                                                            ),
                                                        )}
                                                    </div>
                                                ))}
                                                {searchQuery.trim() === '' ? (
                                                    <div className="px-3 py-6 text-center text-xs text-muted-foreground">
                                                        Type to search across
                                                        theme files.
                                                    </div>
                                                ) : null}
                                            </div>
                                        </ScrollArea>
                                    </div>
                                ) : null}

                                {/* Source control */}
                                {sidebarView === 'source-control' ? (
                                    <ScrollArea className="min-h-0 flex-1">
                                        <div className="flex flex-col">
                                            <div className="border-b px-3 py-2">
                                                <Input
                                                    value={commitMessage}
                                                    onChange={(event) =>
                                                        setCommitMessage(
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="Message (press Enter to commit)"
                                                    className="h-7 text-xs"
                                                    onKeyDown={(event) => {
                                                        if (
                                                            event.key ===
                                                                'Enter' &&
                                                            commitMessage.trim() !==
                                                                '' &&
                                                            gitChanges.length >
                                                                0 &&
                                                            canEditThemes
                                                        ) {
                                                            void runGitAction(
                                                                'post',
                                                                route(
                                                                    'cms.appearance.themes.editor.git.commit',
                                                                    {
                                                                        directory:
                                                                            themeDirectory,
                                                                    },
                                                                ),
                                                                {
                                                                    message:
                                                                        commitMessage,
                                                                    mode:
                                                                        stagedChanges.length >
                                                                        0
                                                                            ? 'staged'
                                                                            : 'all',
                                                                },
                                                                'Commit created',
                                                            );
                                                        }
                                                    }}
                                                />
                                                <Button
                                                    size="sm"
                                                    className="mt-2 h-7 w-full text-xs"
                                                    onClick={() =>
                                                        void runGitAction(
                                                            'post',
                                                            route(
                                                                'cms.appearance.themes.editor.git.commit',
                                                                {
                                                                    directory:
                                                                        themeDirectory,
                                                                },
                                                            ),
                                                            {
                                                                message:
                                                                    commitMessage,
                                                                mode:
                                                                    stagedChanges.length >
                                                                    0
                                                                        ? 'staged'
                                                                        : 'all',
                                                            },
                                                            'Commit created',
                                                        )
                                                    }
                                                    disabled={
                                                        gitMutationRequest.processing ||
                                                        commitMessage.trim() ===
                                                            '' ||
                                                        gitChanges.length ===
                                                            0 ||
                                                        !canEditThemes
                                                    }
                                                >
                                                    {gitMutationRequest.processing ? (
                                                        <Spinner className="size-3" />
                                                    ) : (
                                                        <GitCommitHorizontalIcon className="size-3" />
                                                    )}
                                                    <span className="ml-1">
                                                        Commit
                                                    </span>
                                                </Button>
                                            </div>

                                            {/* Staged */}
                                            <div>
                                                <div className="flex items-center justify-between bg-muted/40 px-3 py-1 text-[11px] font-medium">
                                                    Staged Changes
                                                    <Badge
                                                        variant="outline"
                                                        className="h-4 px-1 text-[10px]"
                                                    >
                                                        {stagedChanges.length}
                                                    </Badge>
                                                </div>
                                                {stagedChanges.map((change) => (
                                                    <div
                                                        key={`staged-${change.path}`}
                                                        className="group flex items-center gap-1 px-3 py-0.5 text-[12px] hover:bg-accent/50"
                                                    >
                                                        <button
                                                            type="button"
                                                            className="min-w-0 flex-1 truncate text-left"
                                                            onClick={() =>
                                                                void openFile(
                                                                    change.path,
                                                                )
                                                            }
                                                        >
                                                            {change.path}
                                                        </button>
                                                        <span className="shrink-0 text-[10px] text-muted-foreground">
                                                            {
                                                                change.status_label
                                                            }
                                                        </span>
                                                        <button
                                                            type="button"
                                                            className="flex size-5 shrink-0 items-center justify-center rounded opacity-0 group-hover:opacity-100 hover:bg-accent"
                                                            onClick={() =>
                                                                void runGitAction(
                                                                    'post',
                                                                    route(
                                                                        'cms.appearance.themes.editor.git.unstage',
                                                                        {
                                                                            directory:
                                                                                themeDirectory,
                                                                        },
                                                                    ),
                                                                    {
                                                                        paths: [
                                                                            change.path,
                                                                        ],
                                                                    },
                                                                    'Changes unstaged',
                                                                )
                                                            }
                                                            disabled={
                                                                gitMutationRequest.processing ||
                                                                !canEditThemes
                                                            }
                                                        >
                                                            <XIcon className="size-3" />
                                                        </button>
                                                    </div>
                                                ))}
                                                {stagedChanges.length === 0 ? (
                                                    <div className="px-3 py-2 text-[11px] text-muted-foreground">
                                                        No staged changes
                                                    </div>
                                                ) : null}
                                            </div>

                                            {/* Unstaged */}
                                            <div>
                                                <div className="flex items-center justify-between bg-muted/40 px-3 py-1 text-[11px] font-medium">
                                                    Changes
                                                    <Badge
                                                        variant="outline"
                                                        className="h-4 px-1 text-[10px]"
                                                    >
                                                        {unstagedChanges.length}
                                                    </Badge>
                                                </div>
                                                {unstagedChanges.map(
                                                    (change) => (
                                                        <div
                                                            key={`unstaged-${change.path}`}
                                                            className="group flex items-center gap-1 px-3 py-0.5 text-[12px] hover:bg-accent/50"
                                                        >
                                                            <button
                                                                type="button"
                                                                className="min-w-0 flex-1 truncate text-left"
                                                                onClick={() =>
                                                                    void openFile(
                                                                        change.path,
                                                                    )
                                                                }
                                                            >
                                                                {change.path}
                                                            </button>
                                                            <span className="shrink-0 text-[10px] text-muted-foreground">
                                                                {
                                                                    change.status_label
                                                                }
                                                            </span>
                                                            <div className="flex shrink-0 gap-0.5 opacity-0 group-hover:opacity-100">
                                                                <button
                                                                    type="button"
                                                                    className="flex size-5 items-center justify-center rounded hover:bg-accent"
                                                                    onClick={() =>
                                                                        void runGitAction(
                                                                            'post',
                                                                            route(
                                                                                'cms.appearance.themes.editor.git.stage',
                                                                                {
                                                                                    directory:
                                                                                        themeDirectory,
                                                                                },
                                                                            ),
                                                                            {
                                                                                paths: [
                                                                                    change.path,
                                                                                ],
                                                                            },
                                                                            'Changes staged',
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        gitMutationRequest.processing ||
                                                                        !canEditThemes
                                                                    }
                                                                >
                                                                    <CheckCircle2Icon className="size-3" />
                                                                </button>
                                                                {canDeleteThemes ? (
                                                                    <button
                                                                        type="button"
                                                                        className="flex size-5 items-center justify-center rounded hover:bg-accent"
                                                                        onClick={() =>
                                                                            void runGitAction(
                                                                                'post',
                                                                                route(
                                                                                    'cms.appearance.themes.editor.git.discard',
                                                                                    {
                                                                                        directory:
                                                                                            themeDirectory,
                                                                                    },
                                                                                ),
                                                                                {
                                                                                    paths: [
                                                                                        change.path,
                                                                                    ],
                                                                                },
                                                                                'Changes discarded',
                                                                            )
                                                                        }
                                                                        disabled={
                                                                            gitMutationRequest.processing
                                                                        }
                                                                    >
                                                                        <Trash2Icon className="size-3" />
                                                                    </button>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                    ),
                                                )}
                                                {unstagedChanges.length ===
                                                0 ? (
                                                    <div className="px-3 py-2 text-[11px] text-muted-foreground">
                                                        No changes
                                                    </div>
                                                ) : null}
                                            </div>
                                        </div>
                                    </ScrollArea>
                                ) : null}
                            </div>
                        ) : null}

                        {/* Editor area */}
                        <div className="flex min-w-0 flex-1 flex-col">
                            {/* Tab bar */}
                            <div className="flex h-9 shrink-0 items-center border-b border-[#2b2b2b] bg-[#252526]">
                                <ScrollArea className="w-full whitespace-nowrap">
                                    <div className="flex items-center">
                                        {openTabs.map((tab, tabIndex) => {
                                            const isTabActive =
                                                tab.path === activePath;
                                            const isTabDirty =
                                                tab.content !==
                                                tab.originalContent;
                                            const tabContextItems: EditorContextMenuEntry[] =
                                                [
                                                    {
                                                        label: 'Close',
                                                        onSelect: () =>
                                                            closeTab(tab.path),
                                                    },
                                                    {
                                                        label: 'Close Others',
                                                        disabled:
                                                            openTabs.length <=
                                                            1,
                                                        onSelect: () =>
                                                            closeOtherTabs(
                                                                tab.path,
                                                            ),
                                                    },
                                                    {
                                                        label: 'Close to the Right',
                                                        disabled:
                                                            tabIndex >=
                                                            openTabs.length - 1,
                                                        onSelect: () =>
                                                            closeTabsToRight(
                                                                tab.path,
                                                            ),
                                                    },
                                                    {
                                                        label: 'Close All',
                                                        onSelect: () =>
                                                            closeAllTabs(),
                                                    },
                                                    { type: 'separator' },
                                                    {
                                                        label: 'Copy Path',
                                                        onSelect: () =>
                                                            copyPathToClipboard(
                                                                tab.path,
                                                            ),
                                                    },
                                                ];
                                            return (
                                                <EditorContextMenu
                                                    key={tab.path}
                                                    items={tabContextItems}
                                                >
                                                    <div
                                                        className={cn(
                                                            'group flex h-9 items-center gap-1 border-r border-[#2b2b2b] px-3 text-[13px]',
                                                            isTabActive
                                                                ? 'bg-[#1e1e1e] text-white'
                                                                : 'bg-[#2d2d2d] text-[#969696] hover:bg-[#2d2d2d]/80',
                                                        )}
                                                    >
                                                        <button
                                                            type="button"
                                                            className="flex items-center gap-1.5"
                                                            onClick={() =>
                                                                setActivePath(
                                                                    tab.path,
                                                                )
                                                            }
                                                        >
                                                            <FileCodeIcon className="size-3.5" />
                                                            <span className="max-w-40 truncate">
                                                                {tab.name}
                                                            </span>
                                                            {isTabDirty ? (
                                                                <span className="size-2 rounded-full bg-blue-500" />
                                                            ) : null}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="ml-1 flex size-5 items-center justify-center rounded opacity-0 group-hover:opacity-100 hover:bg-accent"
                                                            onClick={() =>
                                                                closeTab(
                                                                    tab.path,
                                                                )
                                                            }
                                                        >
                                                            <XIcon className="size-3" />
                                                        </button>
                                                    </div>
                                                </EditorContextMenu>
                                            );
                                        })}
                                    </div>
                                </ScrollArea>
                            </div>

                            {/* Editor content */}
                            {activeTab ? (
                                <div className="flex min-h-0 flex-1 flex-col">
                                    {/* Breadcrumb bar */}
                                    <div className="flex h-7 shrink-0 items-center gap-2 border-b border-[#2b2b2b] bg-[#1e1e1e] px-3 text-[11px] text-[#969696]">
                                        <span className="truncate">
                                            {activeTab.path}
                                        </span>
                                        {activeTab.inherited ? (
                                            <Badge
                                                variant="outline"
                                                className="h-4 px-1 text-[10px]"
                                            >
                                                Inherited from{' '}
                                                {activeTab.inheritedFrom}
                                            </Badge>
                                        ) : null}
                                        <div className="flex-1" />
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <button
                                                    type="button"
                                                    className="flex size-5 items-center justify-center rounded hover:bg-accent"
                                                >
                                                    <MoreHorizontalIcon className="size-3.5" />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    onSelect={() => {
                                                        setRenameSource(
                                                            findNodeByPath(
                                                                tree,
                                                                activeTab.path,
                                                            ),
                                                        );
                                                        setRenamePath(
                                                            activeTab.path,
                                                        );
                                                        setRenameOpen(true);
                                                    }}
                                                    disabled={!canEditThemes}
                                                >
                                                    <PencilIcon />
                                                    Rename
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        void handleDuplicate(
                                                            activeTab.path,
                                                        )
                                                    }
                                                    disabled={!canEditThemes}
                                                >
                                                    <CopyIcon />
                                                    Duplicate
                                                </DropdownMenuItem>
                                                {canDeleteThemes ? (
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        onSelect={() =>
                                                            setDeleteTarget({
                                                                type: 'file',
                                                                path: activeTab.path,
                                                                protected: false,
                                                            })
                                                        }
                                                    >
                                                        <Trash2Icon />
                                                        Delete
                                                    </DropdownMenuItem>
                                                ) : null}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>

                                    {activeTab.inherited ? (
                                        <div className="border-b px-3 py-2">
                                            <Alert>
                                                <InfoIcon />
                                                <AlertTitle>
                                                    Inherited file
                                                </AlertTitle>
                                                <AlertDescription>
                                                    This file currently comes
                                                    from{' '}
                                                    <strong>
                                                        {
                                                            activeTab.inheritedFrom
                                                        }
                                                    </strong>
                                                    . Saving it here creates a
                                                    theme-specific override.
                                                </AlertDescription>
                                            </Alert>
                                        </div>
                                    ) : null}

                                    <div className="min-h-0 flex-1">
                                        <MonacoEditor
                                            value={activeTab.content}
                                            onChange={updateActiveTabContent}
                                            language={activeTab.language}
                                            height="100%"
                                            className="h-full"
                                            editorClassName="h-full rounded-none border-0"
                                            textareaClassName="h-full"
                                            disabled={!canEditThemes}
                                        />
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-1 items-center justify-center">
                                    <div className="text-center text-muted-foreground">
                                        <CodeIcon className="mx-auto mb-3 size-12 opacity-20" />
                                        <p className="text-sm font-medium">
                                            No file open
                                        </p>
                                        <p className="mt-1 text-xs">
                                            Select a file from the explorer to
                                            start editing
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Status bar */}
                <div className="flex h-6 shrink-0 items-center justify-between border-t border-[#2b2b2b] bg-[#007acc] px-3 text-[11px] text-white">
                    <div className="flex items-center gap-3">
                        <span className="flex items-center gap-1">
                            <GitBranchIcon className="size-3" />
                            {theme.directory}
                        </span>
                        {gitChanges.length > 0 ? (
                            <span>
                                {gitChanges.length} change
                                {gitChanges.length !== 1 ? 's' : ''}
                            </span>
                        ) : null}
                    </div>
                    <div className="flex items-center gap-3">
                        {activeTab ? (
                            <>
                                <span>{activeTab.language}</span>
                                <span>{formatBytes(activeTab.size)}</span>
                            </>
                        ) : null}
                    </div>
                </div>
            </TooltipProvider>

            {/* Dialogs */}
            <CreateDialog
                open={newEntityOpen}
                onOpenChange={setNewEntityOpen}
                mode={newEntityMode}
                path={newEntityPath}
                onPathChange={setNewEntityPath}
                onSubmit={() => void createEntity()}
                processing={createRequest.processing}
                canEdit={canEditThemes}
            />

            <RenameDialog
                open={renameOpen}
                onOpenChange={setRenameOpen}
                path={renamePath}
                onPathChange={setRenamePath}
                onSubmit={() => void handleRename()}
                processing={renameRequest.processing}
                canEdit={canEditThemes}
            />

            <UploadDialog
                open={uploadOpen}
                onOpenChange={setUploadOpen}
                targetPath={uploadTargetPath}
                onTargetPathChange={setUploadTargetPath}
                uploadRequest={uploadRequest}
                onSubmit={() => void submitUpload()}
                canEdit={canEditThemes}
            />

            <DeleteConfirmDialog
                target={deleteTarget}
                onOpenChange={() => setDeleteTarget(null)}
                onConfirm={() => void confirmDelete()}
                processing={deleteRequest.processing}
                canDelete={canDeleteThemes}
            />

            <HistoryDialog
                open={historyOpen}
                onOpenChange={setHistoryOpen}
                themeName={theme.name}
                commits={historyItems}
            />
        </ThemeEditorLayout>
    );
}
