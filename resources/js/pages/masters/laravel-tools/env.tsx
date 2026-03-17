import { useHttp } from '@inertiajs/react';
import {
    HistoryIcon,
    RefreshCwIcon,
    RotateCcwIcon,
    SaveIcon,
    ShieldAlertIcon,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { MonacoEditor } from '@/components/code-editor/monaco-editor';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import {
    extractHttpErrorMessage,
    LaravelToolsNavigation,
    formatBytes,
    getLaravelToolsBreadcrumbs,
} from '@/pages/masters/laravel-tools/components/shared';
import type {
    EnvBackup,
    LaravelToolsEnvPageProps,
} from '@/types/laravel-tools';

export default function LaravelToolsEnv({
    envContent,
    protectedKeys,
    backups: initialBackups,
}: LaravelToolsEnvPageProps) {
    const [content, setContent] = useState(envContent);
    const [savedContent, setSavedContent] = useState(envContent);
    const [backups, setBackups] = useState<EnvBackup[]>(initialBackups);
    const [restoringBackup, setRestoringBackup] = useState<string | null>(null);
    const saveRequest = useHttp<
        { content: string },
        { success: boolean; message?: string }
    >({
        content: envContent,
    });
    const backupsRequest = useHttp<
        Record<string, never>,
        { success: boolean; backups?: EnvBackup[]; message?: string }
    >({});
    const restoreRequest = useHttp<
        { backup: string },
        { success: boolean; message?: string; content?: string }
    >({
        backup: '',
    });

    useEffect(() => {
        setContent(envContent);
        setSavedContent(envContent);
    }, [envContent]);

    useEffect(() => {
        setBackups(initialBackups);
    }, [initialBackups]);

    const hasChanges = content !== savedContent;
    const lineCount = useMemo(() => content.split(/\r?\n/).length, [content]);

    const refreshBackups = useCallback(async () => {
        try {
            const payload = await backupsRequest.get(
                route('app.masters.laravel-tools.env.backups'),
            );

            if (!payload.success) {
                throw new Error(payload.message || 'Unable to load backups.');
            }

            setBackups(payload.backups ?? []);
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Refresh failed',
                description: extractHttpErrorMessage(
                    error,
                    'Unable to load recent backups.',
                ),
            });
        }
    }, [backupsRequest]);

    const saveContent = useCallback(async () => {
        try {
            saveRequest.transform(() => ({
                content,
            }));
            const payload = await saveRequest.put(
                route('app.masters.laravel-tools.env.update'),
            );

            if (!payload.success) {
                throw new Error(
                    payload.message || 'Unable to save the ENV file.',
                );
            }

            showAppToast({
                variant: 'success',
                title: 'ENV file updated',
                description: payload.message,
            });

            setSavedContent(content);
            await refreshBackups();
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Save failed',
                description: extractHttpErrorMessage(
                    error,
                    'Unable to save the ENV file.',
                ),
            });
        }
    }, [content, refreshBackups, saveRequest]);

    useEffect(() => {
        const handleKeydown = (event: KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 's'
            ) {
                event.preventDefault();

                if (!saveRequest.processing && hasChanges) {
                    void saveContent();
                }
            }
        };

        window.addEventListener('keydown', handleKeydown);

        return () => {
            window.removeEventListener('keydown', handleKeydown);
        };
    }, [hasChanges, saveContent, saveRequest.processing]);

    const restoreBackup = async (backupName: string) => {
        restoreRequest.transform(() => ({
            backup: backupName,
        }));
        setRestoringBackup(backupName);

        try {
            const payload = await restoreRequest.post(
                route('app.masters.laravel-tools.env.restore'),
            );

            if (!payload.success || typeof payload.content !== 'string') {
                throw new Error(
                    payload.message || 'Unable to restore the selected backup.',
                );
            }

            setContent(payload.content);
            setSavedContent(payload.content);
            showAppToast({
                variant: 'success',
                title: 'Backup restored',
                description: payload.message,
            });
            await refreshBackups();
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Restore failed',
                description: extractHttpErrorMessage(
                    error,
                    'Unable to restore the selected backup.',
                ),
            });
        } finally {
            setRestoringBackup(null);
        }
    };

    return (
        <AppLayout
            breadcrumbs={getLaravelToolsBreadcrumbs('ENV Editor')}
            title="ENV Editor"
            description="Edit environment variables with guardrails for protected keys and recent backups."
            headerActions={
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setContent(savedContent)}
                        disabled={!hasChanges || saveRequest.processing}
                    >
                        <RotateCcwIcon data-icon="inline-start" />
                        Reset
                    </Button>
                    <Button
                        type="button"
                        onClick={() => void saveContent()}
                        disabled={!hasChanges || saveRequest.processing}
                    >
                        {saveRequest.processing ? (
                            <Spinner />
                        ) : (
                            <SaveIcon data-icon="inline-start" />
                        )}
                        Save changes
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <LaravelToolsNavigation current="env" />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.75fr)_22rem]">
                    <div className="flex flex-col gap-6">
                        <Alert>
                            <ShieldAlertIcon data-icon="inline-start" />
                            <AlertTitle>
                                Protected variables stay locked
                            </AlertTitle>
                            <AlertDescription>
                                Changes to {protectedKeys.join(', ')} are
                                blocked. Every successful save creates a fresh
                                backup first.
                            </AlertDescription>
                        </Alert>

                        <Card>
                            <CardHeader className="gap-3">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="flex flex-col gap-1">
                                        <CardTitle>
                                            .env configuration
                                        </CardTitle>
                                        <CardDescription>
                                            Use <strong>Ctrl/Cmd + S</strong> to
                                            save. Monaco loads on demand and
                                            falls back to a plain textarea if
                                            the editor cannot initialize.
                                        </CardDescription>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge
                                            variant={
                                                hasChanges
                                                    ? 'warning'
                                                    : 'secondary'
                                            }
                                        >
                                            {hasChanges
                                                ? 'Unsaved changes'
                                                : 'Saved'}
                                        </Badge>
                                        <Badge variant="outline">
                                            {lineCount} lines
                                        </Badge>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <MonacoEditor
                                    value={content}
                                    onChange={setContent}
                                    language="shell"
                                    height="34rem"
                                    options={{
                                        wordWrap: 'off',
                                        renderLineHighlight: 'line',
                                    }}
                                />
                                <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                                    <p>
                                        Save creates an automatic restore point
                                        in storage/backups/env.
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setContent(savedContent)
                                            }
                                            disabled={
                                                !hasChanges ||
                                                saveRequest.processing
                                            }
                                        >
                                            <RotateCcwIcon data-icon="inline-start" />
                                            Reset to last loaded state
                                        </Button>
                                        <Button
                                            type="button"
                                            onClick={() => void saveContent()}
                                            disabled={
                                                !hasChanges ||
                                                saveRequest.processing
                                            }
                                        >
                                            {saveRequest.processing ? (
                                                <Spinner />
                                            ) : (
                                                <SaveIcon data-icon="inline-start" />
                                            )}
                                            Save file
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="flex flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Protected keys</CardTitle>
                                <CardDescription>
                                    These variables cannot be modified from this
                                    interface.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-wrap gap-2">
                                {protectedKeys.map((key) => (
                                    <Badge key={key} variant="danger">
                                        {key}
                                    </Badge>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="gap-3">
                                <div className="flex items-center justify-between gap-3">
                                    <div className="flex flex-col gap-1">
                                        <CardTitle>Recent backups</CardTitle>
                                        <CardDescription>
                                            Restore a previous snapshot if you
                                            need to roll back.
                                        </CardDescription>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => void refreshBackups()}
                                        disabled={backupsRequest.processing}
                                    >
                                        {backupsRequest.processing ? (
                                            <Spinner />
                                        ) : (
                                            <RefreshCwIcon data-icon="inline-start" />
                                        )}
                                        Refresh
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {backups.length === 0 ? (
                                    <Empty>
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <HistoryIcon />
                                            </EmptyMedia>
                                            <EmptyTitle>
                                                No backups yet
                                            </EmptyTitle>
                                            <EmptyDescription>
                                                Save the ENV file once to create
                                                the first restore point.
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                ) : (
                                    <ScrollArea className="max-h-[28rem] pr-4">
                                        <div className="flex flex-col gap-3">
                                            {backups.map((backup, index) => (
                                                <div
                                                    key={backup.name}
                                                    className="flex flex-col gap-3 rounded-xl border p-4"
                                                >
                                                    <div className="flex flex-col gap-1">
                                                        <p className="truncate text-sm font-medium text-foreground">
                                                            {backup.name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {backup.date} ·{' '}
                                                            {formatBytes(
                                                                backup.size,
                                                            )}
                                                        </p>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            void restoreBackup(
                                                                backup.name,
                                                            )
                                                        }
                                                        disabled={
                                                            restoringBackup ===
                                                            backup.name
                                                        }
                                                    >
                                                        {restoringBackup ===
                                                        backup.name ? (
                                                            <Spinner />
                                                        ) : (
                                                            <HistoryIcon data-icon="inline-start" />
                                                        )}
                                                        Restore backup
                                                    </Button>
                                                    {index <
                                                    backups.length - 1 ? (
                                                        <Separator />
                                                    ) : null}
                                                </div>
                                            ))}
                                        </div>
                                    </ScrollArea>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
