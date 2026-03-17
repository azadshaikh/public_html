import type { FilesIcon } from 'lucide-react';
import type {
    ThemeEditorFileNode,
    ThemeEditorPageProps,
} from '../../../../types/cms';

export type GenericResponse = {
    success?: boolean;
    message?: string;
    error?: string;
};

export type FileTreeResponse = {
    files: ThemeEditorFileNode[];
    isChildTheme: boolean;
    parentTheme: ThemeEditorPageProps['parentTheme'];
};

export type FileReadResponse = {
    content: string;
    path: string;
    size: number;
    modified: number;
    language: string;
    inherited: boolean;
    inheritedFrom: string | null;
};

export type SearchMatch = {
    path: string;
    line: number;
    column: number;
    text: string;
};

export type SearchGroup = {
    path: string;
    match_count: number;
    matches: SearchMatch[];
};

export type SearchResponse = {
    results: SearchGroup[];
    total_matches: number;
};

export type GitChange = {
    status: string;
    index_status: string;
    worktree_status: string;
    status_label: string;
    path: string;
    old_path: string | null;
    staged: boolean;
    unstaged: boolean;
};

export type GitStatusResponse = {
    changes: GitChange[];
    has_changes: boolean;
};

export type GitCommit = {
    hash: string;
    author_name: string;
    author_email: string;
    date: string;
    subject: string;
};

export type GitHistoryResponse = {
    success: boolean;
    commits: GitCommit[];
    has_more: boolean;
    next_skip: number;
};

export type EditorTab = {
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

export type NewEntityMode = 'file' | 'folder';

export type DeleteTarget = {
    type: 'file' | 'folder';
    path: string;
    protected: boolean;
};

export type UploadPayload = {
    file: File | null;
    path: string;
    overwrite: boolean;
};

export type SearchPayload = {
    query: string;
    case_sensitive: boolean;
    use_regex: boolean;
    max_results: number;
};

export type GitMutationPayload = {
    message?: string;
    mode?: string;
    paths?: string[];
};

export type SidebarView = 'explorer' | 'search' | 'source-control';

export type ActivityBarItem = {
    id: SidebarView;
    icon: typeof FilesIcon;
    label: string;
};
