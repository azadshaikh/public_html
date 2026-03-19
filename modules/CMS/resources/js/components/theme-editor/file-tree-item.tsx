import {
    ChevronDownIcon,
    ChevronRightIcon,
    CopyIcon,
    FileIcon,
    FileJsonIcon,
    FileTextIcon,
    FolderIcon,
    FolderOpenIcon,
    ImageIcon,
    PencilIcon,
    PlusIcon,
    Trash2Icon,
    UploadIcon,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ThemeEditorFileNode } from '../../../../types/cms';
import type { DeleteTarget } from '../../pages/cms/themes/editor/types';
import {
    EditorContextMenu
    
} from './editor-context-menu';
import type {EditorContextMenuEntry} from './editor-context-menu';

function TreeItemIcon({
    isDirectory,
    isExpanded,
    extension,
    name,
}: {
    isDirectory: boolean;
    isExpanded: boolean;
    extension?: string;
    name?: string;
}) {
    const base = 'size-4 shrink-0';

    if (isDirectory) {
        return isExpanded ? (
            <FolderOpenIcon className={cn(base, 'text-[#dcb67a]')} />
        ) : (
            <FolderIcon className={cn(base, 'text-[#dcb67a]')} />
        );
    }

    const ext = extension?.toLowerCase();
    const fileName = name?.toLowerCase() ?? '';

    // HTML / Twig / Blade templates
    if (
        ext === 'html' ||
        ext === 'htm' ||
        ext === 'twig' ||
        ext === 'blade' ||
        ext === 'xml' ||
        ext === 'svg'
    ) {
        return <FileTextIcon className={cn(base, 'text-[#e37933]')} />;
    }

    // CSS / SCSS / SASS / LESS
    if (ext === 'css') {
        return <FileTextIcon className={cn(base, 'text-[#519aba]')} />;
    }
    if (ext === 'scss' || ext === 'sass' || ext === 'less') {
        return <FileTextIcon className={cn(base, 'text-[#c76494]')} />;
    }

    // JavaScript
    if (ext === 'js' || ext === 'mjs' || ext === 'cjs' || ext === 'jsx') {
        return <FileTextIcon className={cn(base, 'text-[#cbcb41]')} />;
    }

    // TypeScript
    if (ext === 'ts' || ext === 'tsx') {
        return <FileTextIcon className={cn(base, 'text-[#519aba]')} />;
    }

    // JSON
    if (ext === 'json' || ext === 'jsonc') {
        return <FileJsonIcon className={cn(base, 'text-[#cbcb41]')} />;
    }

    // PHP
    if (ext === 'php') {
        return <FileTextIcon className={cn(base, 'text-[#a074c4]')} />;
    }

    // Markdown
    if (ext === 'md' || ext === 'mdx') {
        return <FileTextIcon className={cn(base, 'text-[#519aba]')} />;
    }

    // Images
    if (
        ext === 'png' ||
        ext === 'jpg' ||
        ext === 'jpeg' ||
        ext === 'gif' ||
        ext === 'webp' ||
        ext === 'ico' ||
        ext === 'bmp'
    ) {
        return <ImageIcon className={cn(base, 'text-[#a074c4]')} />;
    }

    // Fonts
    if (
        ext === 'woff' ||
        ext === 'woff2' ||
        ext === 'ttf' ||
        ext === 'otf' ||
        ext === 'eot'
    ) {
        return <FileIcon className={cn(base, 'text-[#e37933]')} />;
    }

    // YAML / TOML
    if (ext === 'yml' || ext === 'yaml' || ext === 'toml') {
        return <FileTextIcon className={cn(base, 'text-[#a074c4]')} />;
    }

    // Shell / Env
    if (ext === 'sh' || ext === 'bash' || ext === 'zsh' || ext === 'env') {
        return <FileTextIcon className={cn(base, 'text-[#89e051]')} />;
    }

    // Config files by name
    if (
        fileName === '.gitignore' ||
        fileName === '.editorconfig' ||
        fileName === '.htaccess'
    ) {
        return <FileIcon className={cn(base, 'text-[#6d8086]')} />;
    }

    // Text / Log
    if (ext === 'txt' || ext === 'log') {
        return <FileTextIcon className={cn(base, 'text-[#6d8086]')} />;
    }

    return <FileIcon className={cn(base, 'text-[#6d8086]')} />;
}

export type FileTreeItemProps = {
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
};

export function FileTreeItem({
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
}: FileTreeItemProps) {
    const isDirectory = node.type === 'directory';
    const isExpanded = expandedPaths.has(node.path);
    const isSelected = selectedPath === node.path;
    const isActive = activePath === node.path;

    const contextItems: EditorContextMenuEntry[] = isDirectory
        ? [
              {
                  label: 'New file',
                  icon: <PlusIcon />,
                  onSelect: () => onCreateFile(node.path),
              },
              {
                  label: 'New folder',
                  icon: <FolderIcon />,
                  onSelect: () => onCreateFolder(node.path),
              },
              {
                  label: 'Upload file',
                  icon: <UploadIcon />,
                  onSelect: () => onUpload(node.path),
              },
              ...(!node.protected
                  ? [
                        { type: 'separator' as const },
                        {
                            label: 'Rename',
                            icon: <PencilIcon />,
                            onSelect: () => onRename(node),
                        },
                        {
                            label: 'Delete',
                            icon: <Trash2Icon />,
                            variant: 'destructive' as const,
                            onSelect: () =>
                                onDelete({
                                    type: 'folder' as const,
                                    path: node.path,
                                    protected: false,
                                }),
                        },
                    ]
                  : []),
          ]
        : [
              {
                  label: 'Open file',
                  icon: <PencilIcon />,
                  onSelect: () => onOpen(node.path),
              },
              {
                  label: 'Duplicate',
                  icon: <CopyIcon />,
                  onSelect: () => onDuplicate(node.path),
              },
              ...(!node.protected
                  ? [
                        { type: 'separator' as const },
                        {
                            label: 'Rename',
                            icon: <PencilIcon />,
                            onSelect: () => onRename(node),
                        },
                        {
                            label: 'Delete',
                            icon: <Trash2Icon />,
                            variant: 'destructive' as const,
                            onSelect: () =>
                                onDelete({
                                    type: 'file' as const,
                                    path: node.path,
                                    protected: false,
                                }),
                        },
                    ]
                  : []),
          ];

    const content = (
        <EditorContextMenu items={contextItems}>
            <div
                className={cn(
                    'group flex min-w-0 items-center gap-1 py-0.5 pr-2 text-[13px]',
                    isActive
                        ? 'bg-[#04395e] text-white outline outline-1 outline-[#007fd4]'
                        : 'text-[#cccccc] hover:bg-[#2a2d2e]',
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
                        {isExpanded ? (
                            <ChevronDownIcon className="size-4" />
                        ) : (
                            <ChevronRightIcon className="size-4" />
                        )}
                    </button>
                ) : (
                    <span className="w-4 shrink-0" />
                )}

                <TreeItemIcon
                    isDirectory={isDirectory}
                    isExpanded={isExpanded}
                    extension={node.extension}
                    name={node.name}
                />

                <button
                    type="button"
                    className="flex min-w-0 flex-1 items-center gap-1.5 text-left"
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
                    {node.inherited ? (
                        <span className="text-[10px] text-muted-foreground">
                            inherited
                        </span>
                    ) : null}
                    {node.override ? (
                        <span className="text-[10px] text-blue-500">
                            override
                        </span>
                    ) : null}
                </button>
            </div>
        </EditorContextMenu>
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
