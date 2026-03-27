import { RefreshCwIcon, RotateCcwIcon, SaveIcon } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { MonacoEditor } from '@/components/code-editor/monaco-editor';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type WebsiteEnvData = {
    path: string;
    exists: boolean;
    size_bytes: number;
    modified_at: string | null;
    line_count: number;
    content: string;
};

type WebsiteEnvTabProps = {
    websiteId: number;
    active: boolean;
    canManageWebsiteEnv: boolean;
};

function formatBytes(bytes: number): string {
    if (bytes <= 0) {
        return '0 B';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function WebsiteEnvTab({
    websiteId,
    active,
    canManageWebsiteEnv,
}: WebsiteEnvTabProps) {
    const [envData, setEnvData] = useState<WebsiteEnvData | null>(null);
    const [content, setContent] = useState('');
    const [savedContent, setSavedContent] = useState('');
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [loadedOnce, setLoadedOnce] = useState(false);
    const [lastFetchedAt, setLastFetchedAt] = useState<string | null>(null);

    const hasChanges = content !== savedContent;
    const lineCount = useMemo(() => {
        if (content === '') {
            return 0;
        }

        return content.split(/\r\n|\n|\r/).length;
    }, [content]);

    const fetchEnv = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(
                route('platform.websites.env.show', { website: websiteId }),
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            const payload = (await response.json()) as {
                success?: boolean;
                message?: string;
                data?: WebsiteEnvData;
            };

            if (!response.ok || !payload.success || !payload.data) {
                throw new Error(
                    payload.message
                        || 'Unable to load the website environment file.',
                );
            }

            setEnvData(payload.data);
            setContent(payload.data.content);
            setSavedContent(payload.data.content);
            setLoadedOnce(true);
            setLastFetchedAt(new Date().toLocaleString());
        } catch (requestError) {
            const message =
                requestError instanceof Error
                    ? requestError.message
                    : 'Unable to load the website environment file.';
            setError(message);
        } finally {
            setLoading(false);
        }
    }, [websiteId]);

    useEffect(() => {
        if (active && !loadedOnce) {
            void fetchEnv();
        }
    }, [active, fetchEnv, loadedOnce]);

    const saveEnv = useCallback(async () => {
        setSaving(true);
        setError(null);

        try {
            const response = await fetch(
                route('platform.websites.env.update', { website: websiteId }),
                {
                    method: 'PUT',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ content }),
                },
            );

            const payload = (await response.json()) as {
                success?: boolean;
                message?: string;
            };

            if (!response.ok || !payload.success) {
                throw new Error(
                    payload.message
                        || 'Unable to update the website environment file.',
                );
            }

            showAppToast({
                variant: 'success',
                title: 'Website environment updated',
                description:
                    payload.message
                    || 'The shared website environment file was updated successfully.',
            });

            setSavedContent(content);
            await fetchEnv();
        } catch (requestError) {
            const message =
                requestError instanceof Error
                    ? requestError.message
                    : 'Unable to update the website environment file.';
            setError(message);
            showAppToast({
                variant: 'error',
                title: 'Save failed',
                description: message,
            });
        } finally {
            setSaving(false);
        }
    }, [content, fetchEnv, websiteId]);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-medium text-foreground">
                        Shared Environment
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Editing the shared <code>.env</code> file used by the
                        website releases. Save changes, then recache the
                        application if configuration is cached.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        variant="outline"
                        onClick={() => void fetchEnv()}
                        disabled={loading || saving}
                    >
                        {loading ? (
                            <Spinner size="sm" className="mr-2" />
                        ) : (
                            <RefreshCwIcon data-icon="inline-start" />
                        )}
                        Refresh
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => setContent(savedContent)}
                        disabled={!hasChanges || loading || saving}
                    >
                        <RotateCcwIcon data-icon="inline-start" />
                        Reset
                    </Button>
                    <Button
                        onClick={() => void saveEnv()}
                        disabled={
                            !canManageWebsiteEnv
                            || !hasChanges
                            || loading
                            || saving
                        }
                    >
                        {saving ? (
                            <Spinner size="sm" className="mr-2" />
                        ) : (
                            <SaveIcon data-icon="inline-start" />
                        )}
                        Save changes
                    </Button>
                </div>
            </div>

            {envData ? (
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div className="rounded-lg border bg-muted/30 p-3">
                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">
                            Path
                        </p>
                        <p className="mt-0.5 break-all text-sm font-medium text-foreground">
                            {envData.path || 'Unknown'}
                        </p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-3">
                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">
                            Status
                        </p>
                        <p className="mt-0.5 text-sm font-medium text-foreground">
                            {envData.exists ? 'Available' : 'Missing'}
                        </p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-3">
                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">
                            Size
                        </p>
                        <p className="mt-0.5 text-sm font-medium text-foreground">
                            {formatBytes(envData.size_bytes)}
                        </p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-3">
                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">
                            Modified
                        </p>
                        <p className="mt-0.5 text-sm font-medium text-foreground">
                            {envData.modified_at ?? 'Unknown'}
                        </p>
                    </div>
                </div>
            ) : null}

            <div className="rounded-lg border bg-muted/20">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                    <p className="text-sm font-medium text-foreground">
                        Shared <code>.env</code> editor
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {lastFetchedAt
                            ? `Fetched ${lastFetchedAt}`
                            : 'Not loaded yet'}
                    </p>
                </div>
                <div className="grid gap-3 border-b px-4 py-3 text-sm md:grid-cols-3">
                    <div>
                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">
                            Lines
                        </p>
                        <p className="mt-0.5 font-medium text-foreground">
                            {lineCount}
                        </p>
                    </div>
                    <div>
                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">
                            Save target
                        </p>
                        <p className="mt-0.5 font-medium text-foreground">
                            Shared release env
                        </p>
                    </div>
                    <div>
                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">
                            Access
                        </p>
                        <p className="mt-0.5 font-medium text-foreground">
                            {canManageWebsiteEnv ? 'Writable' : 'Read only'}
                        </p>
                    </div>
                </div>
                <div className="p-4">
                    {loading && !loadedOnce ? (
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Spinner size="sm" />
                            Loading shared environment file...
                        </div>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {error ? (
                                <p className="text-sm text-destructive">
                                    {error}
                                </p>
                            ) : null}
                            {!envData?.exists ? (
                                <p className="text-sm text-muted-foreground">
                                    The shared environment file does not exist
                                    yet. Saving below will create it.
                                </p>
                            ) : null}
                            <MonacoEditor
                                value={content}
                                onChange={setContent}
                                language="shell"
                                height={520}
                                disabled={!canManageWebsiteEnv}
                                placeholder="APP_NAME=Astero"
                                className="rounded-lg border"
                            />
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
