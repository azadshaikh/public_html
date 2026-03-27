import { RefreshCwIcon, Trash2Icon } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Button } from '@/components/ui/button';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Spinner } from '@/components/ui/spinner';

type WebsiteLaravelLogData = {
    path: string;
    exists: boolean;
    size_bytes: number;
    modified_at: string | null;
    tail_lines: number;
    content: string;
};

type WebsiteLaravelLogTabProps = {
    websiteId: number;
    active: boolean;
    canManageLaravelLog: boolean;
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

export function WebsiteLaravelLogTab({
    websiteId,
    active,
    canManageLaravelLog,
}: WebsiteLaravelLogTabProps) {
    const [logData, setLogData] = useState<WebsiteLaravelLogData | null>(null);
    const [loading, setLoading] = useState(false);
    const [clearing, setClearing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [loadedOnce, setLoadedOnce] = useState(false);
    const [clearConfirmOpen, setClearConfirmOpen] = useState(false);
    const [lastFetchedAt, setLastFetchedAt] = useState<string | null>(null);

    const fetchLog = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(route('platform.websites.laravel-log.show', { website: websiteId }), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = (await response.json()) as {
                success?: boolean;
                message?: string;
                data?: WebsiteLaravelLogData;
            };

            if (!response.ok || !payload.success || !payload.data) {
                throw new Error(payload.message || 'Unable to load the website Laravel log.');
            }

            setLogData(payload.data);
            setLoadedOnce(true);
            setLastFetchedAt(new Date().toLocaleString());
        } catch (requestError) {
            const message = requestError instanceof Error ? requestError.message : 'Unable to load the website Laravel log.';
            setError(message);
        } finally {
            setLoading(false);
        }
    }, [websiteId]);

    useEffect(() => {
        if (active && !loadedOnce) {
            void fetchLog();
        }
    }, [active, fetchLog, loadedOnce]);

    async function clearLog() {
        setClearing(true);

        try {
            const response = await fetch(route('platform.websites.laravel-log.clear', { website: websiteId }), {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = (await response.json()) as {
                success?: boolean;
                message?: string;
            };

            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Unable to clear the website Laravel log.');
            }

            showAppToast({ message: payload.message || 'Website Laravel log cleared successfully.' });
            setClearConfirmOpen(false);
            await fetchLog();
        } catch (requestError) {
            const message = requestError instanceof Error ? requestError.message : 'Unable to clear the website Laravel log.';
            setError(message);
        } finally {
            setClearing(false);
        }
    }

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-medium text-foreground">Laravel Log</p>
                    <p className="text-xs text-muted-foreground">
                        Tailing the remote website application log from the active release.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" onClick={() => void fetchLog()} disabled={loading || clearing}>
                        {loading ? <Spinner size="sm" className="mr-2" /> : <RefreshCwIcon data-icon="inline-start" />}
                        Refresh
                    </Button>
                    {canManageLaravelLog ? (
                        <Button
                            variant="destructive"
                            onClick={() => setClearConfirmOpen(true)}
                            disabled={loading || clearing}
                        >
                            {clearing ? <Spinner size="sm" className="mr-2" /> : <Trash2Icon data-icon="inline-start" />}
                            Clear log
                        </Button>
                    ) : null}
                </div>
            </div>

            {logData ? (
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div className="rounded-lg border bg-muted/30 p-3">
                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">Path</p>
                        <p className="mt-0.5 break-all text-sm font-medium text-foreground">{logData.path || 'Unknown'}</p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-3">
                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">Status</p>
                        <p className="mt-0.5 text-sm font-medium text-foreground">{logData.exists ? 'Available' : 'Missing'}</p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-3">
                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">Size</p>
                        <p className="mt-0.5 text-sm font-medium text-foreground">{formatBytes(logData.size_bytes)}</p>
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-3">
                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">Modified</p>
                        <p className="mt-0.5 text-sm font-medium text-foreground">{logData.modified_at ?? 'Unknown'}</p>
                    </div>
                </div>
            ) : null}

            <div className="rounded-lg border bg-muted/20">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                    <p className="text-sm font-medium text-foreground">
                        {logData ? `Last ${logData.tail_lines} lines` : 'Log Output'}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {lastFetchedAt ? `Fetched ${lastFetchedAt}` : 'Not loaded yet'}
                    </p>
                </div>
                <div className="max-h-[34rem] overflow-auto p-4">
                    {loading && !logData ? (
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Spinner size="sm" />
                            Loading remote log...
                        </div>
                    ) : error ? (
                        <p className="text-sm text-destructive">{error}</p>
                    ) : !logData?.exists ? (
                        <p className="text-sm text-muted-foreground">The remote website Laravel log file does not exist yet.</p>
                    ) : logData.content === '' ? (
                        <p className="text-sm text-muted-foreground">The remote website Laravel log is currently empty.</p>
                    ) : (
                        <pre className="whitespace-pre-wrap break-words font-mono text-xs leading-5 text-foreground">
                            {logData.content}
                        </pre>
                    )}
                </div>
            </div>

            <ConfirmationDialog
                open={clearConfirmOpen}
                onOpenChange={setClearConfirmOpen}
                title="Clear Website Laravel Log"
                description="This truncates the remote Laravel log for this website. Existing log lines will be removed immediately."
                confirmLabel="Clear log"
                tone="destructive"
                confirmDisabled={clearing}
                onConfirm={() => void clearLog()}
                onCancel={() => setClearConfirmOpen(false)}
            />
        </div>
    );
}
