import { Link, useHttp, usePage } from '@inertiajs/react';
import {
    AlertCircleIcon,
    ArrowLeftIcon,
    CheckCircle2Icon,
    ChevronDownIcon,
    ChevronRightIcon,
    CodeIcon,
    CopyIcon,
    FileCodeIcon,
    FileIcon,
    FolderIcon,
    FolderOpenIcon,
    GitBranchIcon,
    GitCommitHorizontalIcon,
    HistoryIcon,
    InfoIcon,
    MoreHorizontalIcon,
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
    SaveIcon,
    SearchIcon,
    Trash2Icon,
    UploadIcon,
    XIcon,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { MonacoEditor } from '@/components/code-editor/monaco-editor';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type {
    ThemeEditorFileNode,
    ThemeEditorPageProps,
} from '../../../../types/cms';

type GenericResponse = {
    success?: boolean;
    message?: string;
    error?: string;
};

type FileTreeResponse = {
    files: ThemeEditorFileNode[];
    isChildTheme: boolean;
    parentTheme: ThemeEditorPageProps['parentTheme'];
};

type FileReadResponse = {
    content: string;
    path: string;
    size: number;
    modified: number;
    language: string;
    inherited: boolean;
    inheritedFrom: string | null;
};

type SearchMatch = {
    path: string;
    line: number;
    column: number;
    text: string;
};

type SearchGroup = {
    path: string;
    match_count: number;
    matches: SearchMatch[];
};

type SearchResponse = {
    results: SearchGroup[];
    total_matches: number;
};

type GitChange = {
    status: string;
    index_status: string;
    worktree_status: string;
    status_label: string;
    path: string;
    old_path: string | null;
    staged: boolean;
    unstaged: boolean;
};

type GitStatusResponse = {
    changes: GitChange[];
    has_changes: boolean;
};

type GitCommit = {
    hash: string;
    author_name: string;
    author_email: string;
    date: string;
    subject: string;
};

type GitHistoryResponse = {
    success: boolean;
    commits: GitCommit[];
    has_more: boolean;
    next_skip: number;
};

type EditorTab = {
    path: string;
    name: string;
    content: string;
    originalContent: string;
    language: string;
    size: number;
    modified: number;
    inherited: boolean;
    inheritedFrom: string | null;
};

type NewEntityMode = 'file' | 'folder';

type DeleteTarget = {
    type: 'file' | 'folder';
    path: string;
    protected: boolean;
};

type UploadPayload = {
    file: File | null;
    path: string;
    overwrite: boolean;
};

type SearchPayload = {
    query: string;
    case_sensitive: boolean;
    use_regex: boolean;
    max_results: number;
};

type GitMutationPayload = {
    message?: string;
    mode?: string;
    paths?: string[];
};

const breadcrumbsBase: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Appearance', href: route('cms.appearance.themes.index') },
    { title: 'Themes', href: route('cms.appearance.themes.index') },
];

function formatBytes(bytes: number): string {
    if (bytes <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return `${size.toFixed(size >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

function getErrorMessage(error: unknown, fallback: string): string {
    if (error && typeof error === 'object' && 'response' in error) {
        const response = (error as { response?: { data?: string } }).response;

        if (typeof response?.data === 'string') {
            try {
                const payload = JSON.parse(response.data) as {
                    message?: string;
                    error?: string;
                };

                if (typeof payload.message === 'string' && payload.message !== '') {
                    return payload.message;
                }

                if (typeof payload.error === 'string' && payload.error !== '') {
                    return payload.error;
                }
            } catch {
                return fallback;
            }
        }
    }

    if (error instanceof Error && error.message !== '') {
        return error.message;
    }

    return fallback;
}

function getParentDirectory(path: string): string {
    const segments = path.split('/').filter(Boolean);

    if (segments.length <= 1) {
        return '';
    }

    return segments.slice(0, -1).join('/');
}

function findNodeByPath(nodes: ThemeEditorFileNode[], path: string): ThemeEditorFileNode | null {
    for (const node of nodes) {
        if (node.path === path) {
            return node;
        }

        if (node.type === 'directory' && node.children) {
            const found = findNodeByPath(node.children, path);
            if (found) {
                return found;
            }
        }
    }

    return null;
}

function collectExpandablePaths(nodes: ThemeEditorFileNode[], depth = 0): string[] {
    return nodes.flatMap((node) => {
        if (node.type !== 'directory') {
            return [];
        }

        const self = depth < 2 ? [node.path] : [];

        return [...self, ...collectExpandablePaths(node.children ?? [], depth + 1)];
    });
}

function TreeItemIcon({
    isDirectory,
    isExpanded,
    extension,
}: {
    isDirectory: boolean;
    isExpanded: boolean;
    extension?: string;
}) {
    if (isDirectory) {
        return isExpanded ? <FolderOpenIcon className="size-4 shrink-0" /> : <FolderIcon className="size-4 shrink-0" />;
    }

    if (extension === 'twig' || extension === 'html' || extension === 'xml') {
        return <FileCodeIcon className="size-4 shrink-0" />;
    }

    if (extension === 'css' || extension === 'scss' || extension === 'sass') {
        return <CodeIcon className="size-4 shrink-0" />;
    }

    if (extension === 'js' || extension === 'json' || extension === 'ts') {
        return <CodeIcon className="size-4 shrink-0" />;
    }

    return <FileIcon className="size-4 shrink-0" />;
}

function SidebarSectionTitle({ title, action }: { title: string; action?: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-3 border-b px-3 py-2">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{title}</p>
            {action}
        </div>
    );
}

function FileTreeItem({
    node,
    depth,
    expandedPaths,
    selectedPath,
    activePath,
    onToggle,
    onOpen,
    onSelect,
    onCreateFile,
    onCreateFolder,
    onUpload,
    onRename,
    onDuplicate,
    onDelete,
}: {
    node: ThemeEditorFileNode;
    depth: number;
    expandedPaths: Set<string>;
    selectedPath: string | null;
    activePath: string | null;
    onToggle: (path: string) => void;
    onOpen: (path: string) => void;
    onSelect: (path: string) => void;
    onCreateFile: (path: string) => void;
    onCreateFolder: (path: string) => void;
    onUpload: (path: string) => void;
    onRename: (node: ThemeEditorFileNode) => void;
    onDuplicate: (path: string) => void;
    onDelete: (target: DeleteTarget) => void;
}) {
    const isDirectory = node.type === 'directory';
    const isExpanded = expandedPaths.has(node.path);
    const isSelected = selectedPath === node.path;
    const isActive = activePath === node.path;

    const content = (
        <div
            className={cn(
                'group flex min-w-0 items-center gap-2 rounded-md px-2 py-1.5 text-sm',
                isSelected || isActive ? 'bg-muted text-foreground' : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
            )}
            style={{ paddingLeft: `${depth * 16 + 8}px` }}
        >
            {isDirectory ? (
                <button
                    type="button"
                    className="flex shrink-0 items-center text-muted-foreground"
                    onClick={(event) => {
                        event.stopPropagation();
                        onToggle(node.path);
                    }}
                >
                    {isExpanded ? <ChevronDownIcon className="size-4" /> : <ChevronRightIcon className="size-4" />}
                </button>
            ) : (
                <span className="w-4 shrink-0" />
            )}

            <TreeItemIcon isDirectory={isDirectory} isExpanded={isExpanded} extension={node.extension} />

            <button
                type="button"
                className="flex min-w-0 flex-1 items-center gap-2 text-left"
                onClick={() => {
                    onSelect(node.path);
                    if (isDirectory) {
                        onToggle(node.path);
                    } else {
                        onOpen(node.path);
                    }
                }}
            >
                <span className="truncate">{node.name}</span>
                {node.inherited ? <Badge variant="outline">Inherited</Badge> : null}
                {node.override ? <Badge variant="secondary">Override</Badge> : null}
                {node.protected ? <Badge variant="outline">Protected</Badge> : null}
            </button>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" className="opacity-0 group-hover:opacity-100">
                        <MoreHorizontalIcon />
                        <span className="sr-only">Open actions</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        {isDirectory ? (
                            <>
                                <DropdownMenuItem onSelect={() => onCreateFile(node.path)}>
                                    <PlusIcon />
                                    New file
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => onCreateFolder(node.path)}>
                                    <FolderIcon />
                                    New folder
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => onUpload(node.path)}>
                                    <UploadIcon />
                                    Upload file
                                </DropdownMenuItem>
                            </>
                        ) : (
                            <>
                                <DropdownMenuItem onSelect={() => onOpen(node.path)}>
                                    <PencilIcon />
                                    Open file
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => onDuplicate(node.path)}>
                                    <CopyIcon />
                                    Duplicate
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        {!node.protected ? (
                            <DropdownMenuItem onSelect={() => onRename(node)}>
                                <PencilIcon />
                                Rename
                            </DropdownMenuItem>
                        ) : null}
                        {!node.protected ? (
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() =>
                                    onDelete({
                                        type: isDirectory ? 'folder' : 'file',
                                        path: node.path,
                                        protected: Boolean(node.protected),
                                    })
                                }
                            >
                                <Trash2Icon />
                                Delete
                            </DropdownMenuItem>
                        ) : null}
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );

    if (!isDirectory) {
        return content;
    }

    return (
        <div className="flex flex-col">
            {content}
            {isExpanded && node.children?.length ? (
                <div className="flex flex-col">
                    {node.children.map((child) => (
                        <FileTreeItem
                            key={child.path}
                            node={child}
                            depth={depth + 1}
                            expandedPaths={expandedPaths}
                            selectedPath={selectedPath}
                            activePath={activePath}
                            onToggle={onToggle}
                            onOpen={onOpen}
                            onSelect={onSelect}
                            onCreateFile={onCreateFile}
                            onCreateFolder={onCreateFolder}
                            onUpload={onUpload}
                            onRename={onRename}
                            onDuplicate={onDuplicate}
                            onDelete={onDelete}
                        />
                    ))}
                </div>
            ) : null}
        </div>
    );
}

export default function ThemeEditorIndex({
    theme,
    themeDirectory,
    files,
    isChildTheme,
    parentTheme,
}: ThemeEditorPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEditThemes = page.props.auth.abilities.editThemes;
    const canDeleteThemes = page.props.auth.abilities.deleteThemes;

    const breadcrumbs: BreadcrumbItem[] = [
        ...breadcrumbsBase,
        { title: theme.name, href: route('cms.appearance.themes.editor.index', { directory: themeDirectory }) },
    ];

    const [tree, setTree] = useState<ThemeEditorFileNode[]>(files);
    const [expandedPaths, setExpandedPaths] = useState<Set<string>>(() => new Set(collectExpandablePaths(files)));
    const [selectedPath, setSelectedPath] = useState<string | null>(null);
    const [openTabs, setOpenTabs] = useState<EditorTab[]>([]);
    const [activePath, setActivePath] = useState<string | null>(null);
    const [sidebarView, setSidebarView] = useState<'explorer' | 'search' | 'source-control'>('explorer');
    const [searchQuery, setSearchQuery] = useState('');
    const [searchOptions, setSearchOptions] = useState<Array<'case' | 'regex'>>([]);
    const [searchResults, setSearchResults] = useState<SearchGroup[]>([]);
    const [searchTotal, setSearchTotal] = useState(0);
    const [gitChanges, setGitChanges] = useState<GitChange[]>([]);
    const [commitMessage, setCommitMessage] = useState('');
    const [newEntityMode, setNewEntityMode] = useState<NewEntityMode>('file');
    const [newEntityOpen, setNewEntityOpen] = useState(false);
    const [newEntityPath, setNewEntityPath] = useState('');
    const [renameOpen, setRenameOpen] = useState(false);
    const [renameSource, setRenameSource] = useState<ThemeEditorFileNode | null>(null);
    const [renamePath, setRenamePath] = useState('');
    const [uploadOpen, setUploadOpen] = useState(false);
    const [uploadTargetPath, setUploadTargetPath] = useState('');
    const [deleteTarget, setDeleteTarget] = useState<DeleteTarget | null>(null);
    const [historyOpen, setHistoryOpen] = useState(false);
    const [historyItems, setHistoryItems] = useState<GitCommit[]>([]);

    const treeRequest = useHttp<Record<string, never>, FileTreeResponse>({});
    const readRequest = useHttp<Record<string, never>, FileReadResponse>({});
    const saveRequest = useHttp<{ content: string; label?: string }, GenericResponse>({
        content: '',
        label: '',
    });
    const createRequest = useHttp<{ path: string; content?: string }, GenericResponse>({
        path: '',
        content: '',
    });
    const uploadRequest = useHttp<UploadPayload, GenericResponse>({
        file: null,
        path: '',
        overwrite: false,
    });
    const renameRequest = useHttp<{ old_path: string; new_path: string }, GenericResponse>({
        old_path: '',
        new_path: '',
    });
    const duplicateRequest = useHttp<{ path: string }, GenericResponse>({ path: '' });
    const deleteRequest = useHttp<Record<string, never>, GenericResponse>({});
    const searchRequest = useHttp<SearchPayload, SearchResponse>({
        query: '',
        case_sensitive: false,
        use_regex: false,
        max_results: 200,
    });
    const gitStatusRequest = useHttp<Record<string, never>, GitStatusResponse>({});
    const gitMutationRequest = useHttp<GitMutationPayload, GenericResponse>({});
    const historyRequest = useHttp<Record<string, never>, GitHistoryResponse>({});

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
                route('cms.appearance.themes.editor.files', { directory: themeDirectory }),
            );
            setTree(payload.files ?? []);
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Refresh failed',
                description: getErrorMessage(error, 'Unable to refresh the file tree.'),
            });
        }
    }, [themeDirectory, treeRequest]);

    const refreshGitStatus = useCallback(async () => {
        try {
            const payload = await gitStatusRequest.get(
                route('cms.appearance.themes.editor.git.status', { directory: themeDirectory }),
            );
            setGitChanges(payload.changes ?? []);
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Source control failed',
                description: getErrorMessage(error, 'Unable to load git status.'),
            });
        }
    }, [gitStatusRequest, themeDirectory]);

    const loadHistory = useCallback(async () => {
        try {
            const payload = await historyRequest.get(
                route('cms.appearance.themes.editor.git.history.all', { directory: themeDirectory }),
            );
            setHistoryItems(payload.commits ?? []);
            setHistoryOpen(true);
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'History failed',
                description: getErrorMessage(error, 'Unable to load commit history.'),
            });
        }
    }, [historyRequest, themeDirectory]);

    const openFile = useCallback(async (path: string) => {
        setSelectedPath(path);

        const existing = openTabs.find((tab) => tab.path === path);
        if (existing) {
            setActivePath(path);
            return;
        }

        try {
            const payload = await readRequest.get(
                route('cms.appearance.themes.editor.file.read', {
                    directory: themeDirectory,
                    path,
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
                description: getErrorMessage(error, 'Unable to open the selected file.'),
            });
        }
    }, [openTabs, readRequest, themeDirectory]);

    const saveActiveTab = useCallback(async () => {
        if (!activeTab) {
            return;
        }

        try {
            saveRequest.transform(() => ({
                content: activeTab.content,
                label: activeTab.path,
            }));

            const payload = await saveRequest.put(
                route('cms.appearance.themes.editor.file.save', {
                    directory: themeDirectory,
                    path: activeTab.path,
                }),
            );

            if (payload.success === false) {
                throw new Error(payload.message || payload.error || 'Save failed.');
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
                description: payload.message || `${activeTab.name} was saved successfully.`,
            });
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Save failed',
                description: getErrorMessage(error, 'Unable to save the active file.'),
            });
        }
    }, [activeTab, refreshGitStatus, refreshTree, saveRequest, themeDirectory]);

    const closeTab = useCallback((path: string) => {
        const closingTab = openTabs.find((tab) => tab.path === path);
        if (closingTab && closingTab.content !== closingTab.originalContent) {
            const shouldClose = window.confirm(`Discard unsaved changes in ${closingTab.name}?`);
            if (!shouldClose) {
                return;
            }
        }

        const nextTabs = openTabs.filter((tab) => tab.path !== path);
        setOpenTabs(nextTabs);

        if (activePath === path) {
            setActivePath(nextTabs.at(-1)?.path ?? null);
        }
    }, [activePath, openTabs]);

    const updateActiveTabContent = (value: string) => {
        if (!activeTab) {
            return;
        }

        setOpenTabs((prev) =>
            prev.map((tab) => (tab.path === activeTab.path ? { ...tab, content: value } : tab)),
        );
    };

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
                route('cms.appearance.themes.editor.search', { directory: themeDirectory }),
            );

            setSearchResults(payload.results ?? []);
            setSearchTotal(payload.total_matches ?? 0);
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Search failed',
                description: getErrorMessage(error, 'Unable to search theme files.'),
            });
        }
    }, [searchOptions, searchQuery, searchRequest, themeDirectory]);

    const runGitAction = useCallback(async (method: 'post' | 'delete', url: string, payload: GitMutationPayload = {}, successTitle = 'Updated') => {
        try {
            gitMutationRequest.transform(() => payload);
            const response = method === 'delete'
                ? await gitMutationRequest.delete(url)
                : await gitMutationRequest.post(url);

            if (response.success === false) {
                throw new Error(response.message || response.error || 'Action failed.');
            }

            await Promise.all([refreshGitStatus(), refreshTree()]);

            showAppToast({
                variant: 'success',
                title: successTitle,
                description: response.message || 'The requested action completed successfully.',
            });
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Action failed',
                description: getErrorMessage(error, 'Unable to complete the requested action.'),
            });
        }
    }, [gitMutationRequest, refreshGitStatus, refreshTree]);

    const createEntity = async () => {
        if (newEntityPath.trim() === '') {
            return;
        }

        try {
            createRequest.transform(() => ({ path: newEntityPath.trim() }));
            const url = newEntityMode === 'file'
                ? route('cms.appearance.themes.editor.file.create', { directory: themeDirectory })
                : route('cms.appearance.themes.editor.folder.create', { directory: themeDirectory });
            const response = await createRequest.post(url);

            if (response.success === false) {
                throw new Error(response.message || response.error || 'Creation failed.');
            }

            setNewEntityOpen(false);
            setNewEntityPath('');
            await refreshTree();

            if (newEntityMode === 'file') {
                await openFile(newEntityPath.trim());
            }

            showAppToast({
                variant: 'success',
                title: newEntityMode === 'file' ? 'File created' : 'Folder created',
                description: response.message || 'Created successfully.',
            });
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Creation failed',
                description: getErrorMessage(error, 'Unable to create the requested item.'),
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
                route('cms.appearance.themes.editor.rename', { directory: themeDirectory }),
            );

            if (response.success === false) {
                throw new Error(response.message || response.error || 'Rename failed.');
            }

            const nextPath = renamePath.trim();
            setOpenTabs((prev) =>
                prev.map((tab) =>
                    tab.path === renameSource.path
                        ? { ...tab, path: nextPath, name: nextPath.split('/').at(-1) ?? nextPath }
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
                description: getErrorMessage(error, 'Unable to rename the selected item.'),
            });
        }
    };

    const handleDuplicate = async (path: string) => {
        try {
            duplicateRequest.transform(() => ({ path }));
            const response = await duplicateRequest.post(
                route('cms.appearance.themes.editor.duplicate', { directory: themeDirectory }),
            );

            if (response.success === false) {
                throw new Error(response.message || response.error || 'Duplicate failed.');
            }

            await refreshTree();

            showAppToast({
                variant: 'success',
                title: 'Duplicated',
                description: response.message || 'File duplicated successfully.',
            });
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Duplicate failed',
                description: getErrorMessage(error, 'Unable to duplicate the selected file.'),
            });
        }
    };

    const confirmDelete = async () => {
        if (!deleteTarget) {
            return;
        }

        const url = deleteTarget.type === 'file'
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
                throw new Error(response.message || response.error || 'Delete failed.');
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
                description: getErrorMessage(error, 'Unable to delete the selected item.'),
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
                route('cms.appearance.themes.editor.upload', { directory: themeDirectory }),
            );

            if (response.success === false) {
                throw new Error(response.message || response.error || 'Upload failed.');
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
                description: getErrorMessage(error, 'Unable to upload the selected file.'),
            });
        }
    };

    useEffect(() => {
        void refreshGitStatus();
    }, [refreshGitStatus]);

    useEffect(() => {
        const handleKeydown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
                event.preventDefault();

                if (!saveRequest.processing && activeTab && activeTab.content !== activeTab.originalContent) {
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

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`${theme.name} editor`}
            description="Edit theme files, search the codebase, and manage git-backed changes."
            headerActions={
                <div className="flex flex-wrap gap-3">
                    <Button variant="outline" asChild>
                        <Link href={route('cms.appearance.themes.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back to themes
                        </Link>
                    </Button>
                    <Button variant="outline" onClick={() => void refreshTree()} disabled={treeRequest.processing}>
                        {treeRequest.processing ? <Spinner /> : <RefreshCwIcon data-icon="inline-start" />}
                        Refresh
                    </Button>
                    <Button onClick={() => void saveActiveTab()} disabled={!activeTab || activeTab.content === activeTab.originalContent || saveRequest.processing || !canEditThemes}>
                        {saveRequest.processing ? <Spinner /> : <SaveIcon data-icon="inline-start" />}
                        Save file
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-4">
                {isChildTheme && parentTheme ? (
                    <Alert>
                        <GitBranchIcon />
                        <AlertTitle>Child theme editor</AlertTitle>
                        <AlertDescription>
                            You are editing a child theme of <strong>{parentTheme.name}</strong>. Inherited files can be opened directly, and saving them creates a local override.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <Card className="overflow-hidden">
                    <CardHeader className="border-b pb-4">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <CardTitle>{theme.name}</CardTitle>
                                    {theme.is_active ? <Badge variant="success">Active</Badge> : null}
                                    {isChildTheme ? <Badge variant="secondary">Child theme</Badge> : null}
                                </div>
                                <CardDescription>{theme.description || 'No description available for this theme.'}</CardDescription>
                            </div>
                            <div className="flex flex-wrap gap-2 text-sm text-muted-foreground">
                                <Badge variant="outline">v{theme.version}</Badge>
                                <Badge variant="outline">{theme.author || 'Unknown author'}</Badge>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="grid min-h-[70vh] grid-cols-1 xl:grid-cols-[360px_minmax(0,1fr)]">
                            <div className="border-b xl:border-r xl:border-b-0">
                                <div className="flex h-full flex-col bg-muted/20">
                                    <div className="border-b p-3">
                                        <ToggleGroup
                                            type="single"
                                            value={sidebarView}
                                            onValueChange={(value) => {
                                                if (value === 'explorer' || value === 'search' || value === 'source-control') {
                                                    setSidebarView(value);
                                                }
                                            }}
                                            variant="outline"
                                        >
                                            <ToggleGroupItem value="explorer">Explorer</ToggleGroupItem>
                                            <ToggleGroupItem value="search">Search</ToggleGroupItem>
                                            <ToggleGroupItem value="source-control">Git</ToggleGroupItem>
                                        </ToggleGroup>
                                    </div>

                                    {sidebarView === 'explorer' ? (
                                        <>
                                            <SidebarSectionTitle
                                                title="Explorer"
                                                action={
                                                    <div className="flex gap-1">
                                                        <Button variant="ghost" onClick={() => { setNewEntityMode('file'); setNewEntityPath(selectedDirectory ? `${selectedDirectory}/` : ''); setNewEntityOpen(true); }} disabled={!canEditThemes}>
                                                            <PlusIcon />
                                                            <span className="sr-only">New file</span>
                                                        </Button>
                                                        <Button variant="ghost" onClick={() => { setNewEntityMode('folder'); setNewEntityPath(selectedDirectory ? `${selectedDirectory}/` : ''); setNewEntityOpen(true); }} disabled={!canEditThemes}>
                                                            <FolderIcon />
                                                            <span className="sr-only">New folder</span>
                                                        </Button>
                                                        <Button variant="ghost" onClick={() => { setUploadTargetPath(selectedDirectory); setUploadOpen(true); }} disabled={!canEditThemes}>
                                                            <UploadIcon />
                                                            <span className="sr-only">Upload</span>
                                                        </Button>
                                                    </div>
                                                }
                                            />
                                            <ScrollArea className="flex-1">
                                                <div className="flex flex-col p-2">
                                                    {tree.length > 0 ? (
                                                        tree.map((node) => (
                                                            <FileTreeItem
                                                                key={node.path}
                                                                node={node}
                                                                depth={0}
                                                                expandedPaths={expandedPaths}
                                                                selectedPath={selectedPath}
                                                                activePath={activePath}
                                                                onToggle={toggleFolder}
                                                                onOpen={(path) => void openFile(path)}
                                                                onSelect={setSelectedPath}
                                                                onCreateFile={(path) => { setNewEntityMode('file'); setNewEntityPath(path ? `${path}/` : ''); setNewEntityOpen(true); }}
                                                                onCreateFolder={(path) => { setNewEntityMode('folder'); setNewEntityPath(path ? `${path}/` : ''); setNewEntityOpen(true); }}
                                                                onUpload={(path) => { setUploadTargetPath(path); setUploadOpen(true); }}
                                                                onRename={(nodeToRename) => { setRenameSource(nodeToRename); setRenamePath(nodeToRename.path); setRenameOpen(true); }}
                                                                onDuplicate={(path) => void handleDuplicate(path)}
                                                                onDelete={setDeleteTarget}
                                                            />
                                                        ))
                                                    ) : (
                                                        <Empty className="border-0">
                                                            <EmptyHeader>
                                                                <EmptyMedia variant="icon"><FolderIcon /></EmptyMedia>
                                                                <EmptyTitle>No files found</EmptyTitle>
                                                                <EmptyDescription>This theme currently has no editable files.</EmptyDescription>
                                                            </EmptyHeader>
                                                        </Empty>
                                                    )}
                                                </div>
                                            </ScrollArea>
                                        </>
                                    ) : null}

                                    {sidebarView === 'search' ? (
                                        <>
                                            <SidebarSectionTitle title="Search" />
                                            <div className="flex flex-col gap-3 border-b p-3">
                                                <Input
                                                    value={searchQuery}
                                                    onChange={(event) => setSearchQuery(event.target.value)}
                                                    placeholder="Search in files"
                                                />
                                                <div className="flex items-center justify-between gap-3">
                                                    <ToggleGroup
                                                        type="multiple"
                                                        value={searchOptions}
                                                        onValueChange={(value) => setSearchOptions(value as Array<'case' | 'regex'>)}
                                                        variant="outline"
                                                    >
                                                        <ToggleGroupItem value="case">Case</ToggleGroupItem>
                                                        <ToggleGroupItem value="regex">Regex</ToggleGroupItem>
                                                    </ToggleGroup>
                                                    <Button onClick={() => void runSearch()} disabled={searchRequest.processing || searchQuery.trim() === ''}>
                                                        {searchRequest.processing ? <Spinner /> : <SearchIcon data-icon="inline-start" />}
                                                        Search
                                                    </Button>
                                                </div>
                                            </div>
                                            <ScrollArea className="flex-1">
                                                <div className="flex flex-col gap-4 p-3">
                                                    {searchQuery.trim() === '' ? (
                                                        <Empty className="border-0">
                                                            <EmptyHeader>
                                                                <EmptyMedia variant="icon"><SearchIcon /></EmptyMedia>
                                                                <EmptyTitle>Search theme files</EmptyTitle>
                                                                <EmptyDescription>Use plain text or regex search across editable files.</EmptyDescription>
                                                            </EmptyHeader>
                                                        </Empty>
                                                    ) : null}
                                                    {searchQuery.trim() !== '' ? (
                                                        <p className="text-sm text-muted-foreground">{searchTotal} matches</p>
                                                    ) : null}
                                                    {searchResults.map((group) => (
                                                        <div key={group.path} className="rounded-lg border bg-background">
                                                            <div className="border-b px-3 py-2 text-sm font-medium">{group.path}</div>
                                                            <div className="flex flex-col">
                                                                {group.matches.map((match) => (
                                                                    <button
                                                                        key={`${group.path}-${match.line}-${match.column}`}
                                                                        type="button"
                                                                        className="flex flex-col gap-1 px-3 py-2 text-left text-sm hover:bg-muted"
                                                                        onClick={() => void openFile(match.path)}
                                                                    >
                                                                        <span className="text-xs text-muted-foreground">Line {match.line}</span>
                                                                        <span className="truncate font-mono text-xs">{match.text}</span>
                                                                    </button>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </ScrollArea>
                                        </>
                                    ) : null}

                                    {sidebarView === 'source-control' ? (
                                        <>
                                            <SidebarSectionTitle
                                                title="Source control"
                                                action={
                                                    <div className="flex gap-1">
                                                        <Button variant="ghost" onClick={() => void refreshGitStatus()} disabled={gitStatusRequest.processing}>
                                                            <RefreshCwIcon />
                                                            <span className="sr-only">Refresh git status</span>
                                                        </Button>
                                                        <Button variant="ghost" onClick={() => void loadHistory()} disabled={historyRequest.processing}>
                                                            <HistoryIcon />
                                                            <span className="sr-only">Open history</span>
                                                        </Button>
                                                    </div>
                                                }
                                            />
                                            <ScrollArea className="flex-1">
                                                <div className="flex flex-col gap-4 p-3">
                                                    <div className="rounded-lg border bg-background p-3">
                                                        <Field>
                                                            <FieldLabel htmlFor="commit-message">Commit message</FieldLabel>
                                                            <Input
                                                                id="commit-message"
                                                                value={commitMessage}
                                                                onChange={(event) => setCommitMessage(event.target.value)}
                                                                placeholder="Describe your changes"
                                                            />
                                                            <FieldDescription>Only staged changes are committed when files are already staged.</FieldDescription>
                                                        </Field>
                                                        <div className="mt-3 flex gap-2">
                                                            <Button
                                                                onClick={() => void runGitAction(
                                                                    'post',
                                                                    route('cms.appearance.themes.editor.git.commit', { directory: themeDirectory }),
                                                                    {
                                                                        message: commitMessage,
                                                                        mode: stagedChanges.length > 0 ? 'staged' : 'all',
                                                                    },
                                                                    'Commit created',
                                                                )}
                                                                disabled={gitMutationRequest.processing || commitMessage.trim() === '' || gitChanges.length === 0 || !canEditThemes}
                                                            >
                                                                {gitMutationRequest.processing ? <Spinner /> : <GitCommitHorizontalIcon data-icon="inline-start" />}
                                                                Commit changes
                                                            </Button>
                                                        </div>
                                                    </div>

                                                    <div className="flex flex-col gap-3">
                                                        <div className="rounded-lg border bg-background">
                                                            <div className="border-b px-3 py-2 text-sm font-medium">Staged changes</div>
                                                            {stagedChanges.length === 0 ? (
                                                                <p className="px-3 py-4 text-sm text-muted-foreground">No staged changes.</p>
                                                            ) : (
                                                                stagedChanges.map((change) => (
                                                                    <div key={`staged-${change.path}`} className="flex items-center gap-3 border-b px-3 py-2 last:border-b-0">
                                                                        <button type="button" className="min-w-0 flex-1 text-left" onClick={() => void openFile(change.path)}>
                                                                            <p className="truncate text-sm font-medium">{change.path}</p>
                                                                            <p className="text-xs text-muted-foreground">{change.status_label}</p>
                                                                        </button>
                                                                        <Button
                                                                            variant="ghost"
                                                                            onClick={() => void runGitAction('post', route('cms.appearance.themes.editor.git.unstage', { directory: themeDirectory }), { paths: [change.path] }, 'Changes unstaged')}
                                                                            disabled={gitMutationRequest.processing || !canEditThemes}
                                                                        >
                                                                            <XIcon />
                                                                            <span className="sr-only">Unstage</span>
                                                                        </Button>
                                                                    </div>
                                                                ))
                                                            )}
                                                        </div>

                                                        <div className="rounded-lg border bg-background">
                                                            <div className="border-b px-3 py-2 text-sm font-medium">Unstaged changes</div>
                                                            {unstagedChanges.length === 0 ? (
                                                                <p className="px-3 py-4 text-sm text-muted-foreground">No unstaged changes.</p>
                                                            ) : (
                                                                unstagedChanges.map((change) => (
                                                                    <div key={`unstaged-${change.path}`} className="flex items-center gap-3 border-b px-3 py-2 last:border-b-0">
                                                                        <button type="button" className="min-w-0 flex-1 text-left" onClick={() => void openFile(change.path)}>
                                                                            <p className="truncate text-sm font-medium">{change.path}</p>
                                                                            <p className="text-xs text-muted-foreground">{change.status_label}</p>
                                                                        </button>
                                                                        <div className="flex gap-1">
                                                                            <Button
                                                                                variant="ghost"
                                                                                onClick={() => void runGitAction('post', route('cms.appearance.themes.editor.git.stage', { directory: themeDirectory }), { paths: [change.path] }, 'Changes staged')}
                                                                                disabled={gitMutationRequest.processing || !canEditThemes}
                                                                            >
                                                                                <CheckCircle2Icon />
                                                                                <span className="sr-only">Stage</span>
                                                                            </Button>
                                                                            {canDeleteThemes ? (
                                                                                <Button
                                                                                    variant="ghost"
                                                                                    onClick={() => void runGitAction('post', route('cms.appearance.themes.editor.git.discard', { directory: themeDirectory }), { paths: [change.path] }, 'Changes discarded')}
                                                                                    disabled={gitMutationRequest.processing}
                                                                                >
                                                                                    <Trash2Icon />
                                                                                    <span className="sr-only">Discard</span>
                                                                                </Button>
                                                                            ) : null}
                                                                        </div>
                                                                    </div>
                                                                ))
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </ScrollArea>
                                        </>
                                    ) : null}
                                </div>
                            </div>

                            <div>
                                <div className="flex h-full flex-col">
                                    <div className="border-b bg-muted/20">
                                        <ScrollArea className="w-full whitespace-nowrap">
                                            <div className="flex min-h-12 items-center gap-1 px-2 py-1">
                                                {openTabs.length === 0 ? (
                                                    <p className="px-2 text-sm text-muted-foreground">Open a file from the explorer to start editing.</p>
                                                ) : (
                                                    openTabs.map((tab) => {
                                                        const isActive = tab.path === activePath;
                                                        const isDirty = tab.content !== tab.originalContent;

                                                        return (
                                                            <div
                                                                key={tab.path}
                                                                className={cn(
                                                                    'flex items-center gap-2 rounded-md border px-3 py-2',
                                                                    isActive ? 'border-border bg-background shadow-sm' : 'border-transparent bg-transparent text-muted-foreground hover:bg-muted',
                                                                )}
                                                            >
                                                                <button
                                                                    type="button"
                                                                    className="flex items-center gap-2"
                                                                    onClick={() => setActivePath(tab.path)}
                                                                >
                                                                    <FileCodeIcon className="size-4" />
                                                                    <span className="max-w-44 truncate text-sm">{tab.name}</span>
                                                                    {isDirty ? <span className="size-2 rounded-full bg-primary" /> : null}
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    className="text-muted-foreground hover:text-foreground"
                                                                    onClick={() => closeTab(tab.path)}
                                                                >
                                                                    <XIcon className="size-4" />
                                                                </button>
                                                            </div>
                                                        );
                                                    })
                                                )}
                                            </div>
                                        </ScrollArea>
                                    </div>

                                    {activeTab ? (
                                        <div className="flex min-h-0 flex-1 flex-col">
                                            <div className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                                                <div className="flex min-w-0 flex-col gap-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <h3 className="truncate text-sm font-medium">{activeTab.path}</h3>
                                                        {activeTab.inherited ? <Badge variant="outline">Inherited from {activeTab.inheritedFrom}</Badge> : null}
                                                    </div>
                                                    <div className="flex flex-wrap gap-3 text-xs text-muted-foreground">
                                                        <span>{formatBytes(activeTab.size)}</span>
                                                        <span>Language: {activeTab.language}</span>
                                                        <span>Updated: {new Date(activeTab.modified * 1000).toLocaleString()}</span>
                                                    </div>
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    <Button variant="outline" onClick={() => { setRenameSource(findNodeByPath(tree, activeTab.path)); setRenamePath(activeTab.path); setRenameOpen(true); }} disabled={!canEditThemes}>
                                                        <PencilIcon data-icon="inline-start" />
                                                        Rename
                                                    </Button>
                                                    <Button variant="outline" onClick={() => void handleDuplicate(activeTab.path)} disabled={!canEditThemes}>
                                                        <CopyIcon data-icon="inline-start" />
                                                        Duplicate
                                                    </Button>
                                                    {canDeleteThemes ? (
                                                        <Button variant="outline" onClick={() => setDeleteTarget({ type: 'file', path: activeTab.path, protected: false })}>
                                                            <Trash2Icon data-icon="inline-start" />
                                                            Delete
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            </div>

                                            {activeTab.inherited ? (
                                                <div className="border-b px-4 py-3">
                                                    <Alert>
                                                        <InfoIcon />
                                                        <AlertTitle>Inherited file</AlertTitle>
                                                        <AlertDescription>
                                                            This file currently comes from <strong>{activeTab.inheritedFrom}</strong>. Saving it here creates a theme-specific override.
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
                                                    editorClassName="h-full"
                                                    textareaClassName="h-full"
                                                    disabled={!canEditThemes}
                                                />
                                            </div>
                                        </div>
                                    ) : (
                                        <Empty className="m-6 flex-1">
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon"><CodeIcon /></EmptyMedia>
                                                <EmptyTitle>No file selected</EmptyTitle>
                                                <EmptyDescription>Open a theme file from the explorer, search panel, or source control list.</EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    )}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={newEntityOpen} onOpenChange={setNewEntityOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{newEntityMode === 'file' ? 'Create new file' : 'Create new folder'}</DialogTitle>
                        <DialogDescription>
                            Enter the full relative path. Use folders in the path if you want to nest the new item.
                        </DialogDescription>
                    </DialogHeader>
                    <Field>
                        <FieldLabel htmlFor="new-entity-path">Relative path</FieldLabel>
                        <Input id="new-entity-path" value={newEntityPath} onChange={(event) => setNewEntityPath(event.target.value)} placeholder={newEntityMode === 'file' ? 'templates/home.twig' : 'assets/images'} />
                        <FieldDescription>
                            {newEntityMode === 'file' ? 'Supported files: twig, css, js, json, md, html, xml, scss.' : 'Protected folders cannot be deleted later.'}
                        </FieldDescription>
                    </Field>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setNewEntityOpen(false)}>Cancel</Button>
                        <Button onClick={() => void createEntity()} disabled={createRequest.processing || !canEditThemes || newEntityPath.trim() === ''}>
                            {createRequest.processing ? <Spinner /> : <PlusIcon data-icon="inline-start" />}
                            Create
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={renameOpen} onOpenChange={setRenameOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Rename item</DialogTitle>
                        <DialogDescription>Update the full relative path for the selected file or folder.</DialogDescription>
                    </DialogHeader>
                    <Field>
                        <FieldLabel htmlFor="rename-path">New path</FieldLabel>
                        <Input id="rename-path" value={renamePath} onChange={(event) => setRenamePath(event.target.value)} />
                    </Field>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setRenameOpen(false)}>Cancel</Button>
                        <Button onClick={() => void handleRename()} disabled={renameRequest.processing || !canEditThemes || renamePath.trim() === ''}>
                            {renameRequest.processing ? <Spinner /> : <PencilIcon data-icon="inline-start" />}
                            Rename
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={uploadOpen} onOpenChange={setUploadOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Upload file</DialogTitle>
                        <DialogDescription>Upload a file into the current theme directory structure.</DialogDescription>
                    </DialogHeader>
                    <div className="flex flex-col gap-4">
                        <Field>
                            <FieldLabel htmlFor="upload-path">Target folder</FieldLabel>
                            <Input id="upload-path" value={uploadTargetPath} onChange={(event) => setUploadTargetPath(event.target.value)} placeholder="assets/images" />
                            <FieldDescription>Leave blank to upload to the theme root.</FieldDescription>
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="upload-file">File</FieldLabel>
                            <Input
                                id="upload-file"
                                type="file"
                                onChange={(event) => {
                                    uploadRequest.setData('file', event.currentTarget.files?.[0] ?? null);
                                }}
                            />
                            <FieldDescription>Maximum upload size is 10MB.</FieldDescription>
                        </Field>
                        {uploadRequest.progress ? (
                            <div className="flex flex-col gap-2">
                                <div className="flex items-center justify-between text-sm text-muted-foreground">
                                    <span>Uploading…</span>
                                    <span>{uploadRequest.progress.percentage}%</span>
                                </div>
                                <Progress value={uploadRequest.progress.percentage} />
                            </div>
                        ) : null}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setUploadOpen(false)}>Cancel</Button>
                        <Button onClick={() => void submitUpload()} disabled={uploadRequest.processing || uploadRequest.data.file === null || !canEditThemes}>
                            {uploadRequest.processing ? <Spinner /> : <UploadIcon data-icon="inline-start" />}
                            Upload
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogMedia><AlertCircleIcon className="size-5" /></AlertDialogMedia>
                        <AlertDialogTitle>Delete {deleteTarget?.type ?? 'item'}?</AlertDialogTitle>
                        <AlertDialogDescription>
                            {deleteTarget ? `This will permanently remove ${deleteTarget.path}. This action cannot be undone.` : 'This action cannot be undone.'}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={deleteRequest.processing}>Cancel</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" disabled={deleteRequest.processing || !canDeleteThemes} onClick={() => void confirmDelete()}>
                            {deleteRequest.processing ? <Spinner /> : null}
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <Dialog open={historyOpen} onOpenChange={setHistoryOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Theme history</DialogTitle>
                        <DialogDescription>Recent git commits for {theme.name}.</DialogDescription>
                    </DialogHeader>
                    <ScrollArea className="max-h-[60vh]">
                        <div className="flex flex-col gap-3 pr-4">
                            {historyItems.length === 0 ? (
                                <Empty className="border-0 px-0">
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon"><HistoryIcon /></EmptyMedia>
                                        <EmptyTitle>No commits yet</EmptyTitle>
                                        <EmptyDescription>Once changes are committed, they will appear here.</EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            ) : (
                                historyItems.map((commit) => (
                                    <div key={commit.hash} className="rounded-lg border p-3">
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="space-y-1">
                                                <p className="font-medium">{commit.subject}</p>
                                                <p className="text-xs text-muted-foreground">{commit.author_name} • {new Date(commit.date).toLocaleString()}</p>
                                            </div>
                                            <Badge variant="outline">{commit.hash.slice(0, 7)}</Badge>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </ScrollArea>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setHistoryOpen(false)}>Close</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
