import type { ThemeEditorFileNode } from '../../../../types/cms';

export function formatBytes(bytes: number): string {
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

export function getErrorMessage(error: unknown, fallback: string): string {
    if (error && typeof error === 'object' && 'response' in error) {
        const response = (error as { response?: { data?: string } }).response;

        if (typeof response?.data === 'string') {
            try {
                const payload = JSON.parse(response.data) as {
                    message?: string;
                    error?: string;
                };

                if (
                    typeof payload.message === 'string' &&
                    payload.message !== ''
                ) {
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

export function getParentDirectory(path: string): string {
    const segments = path.split('/').filter(Boolean);

    if (segments.length <= 1) {
        return '';
    }

    return segments.slice(0, -1).join('/');
}

export function findNodeByPath(
    nodes: ThemeEditorFileNode[],
    path: string,
): ThemeEditorFileNode | null {
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

export function collectExpandablePaths(
    nodes: ThemeEditorFileNode[],
    depth = 0,
): string[] {
    return nodes.flatMap((node) => {
        if (node.type !== 'directory') {
            return [];
        }

        const self = depth < 2 ? [node.path] : [];

        return [
            ...self,
            ...collectExpandablePaths(node.children ?? [], depth + 1),
        ];
    });
}
