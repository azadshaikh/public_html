import { useHttp } from '@inertiajs/react';
import {
    PlayIcon,
    RotateCcwIcon,
    TerminalSquareIcon,
    TimerResetIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';
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
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import {
    extractHttpErrorMessage,
    LaravelToolsNavigation,
    getLaravelToolsBreadcrumbs,
} from '@/pages/masters/laravel-tools/components/shared';
import type {
    ArtisanCommand,
    LaravelToolsArtisanPageProps,
} from '@/types/laravel-tools';

export default function LaravelToolsArtisan({
    commands,
}: LaravelToolsArtisanPageProps) {
    const [selectedCommand, setSelectedCommand] =
        useState<ArtisanCommand | null>(commands[0] ?? null);
    const [runningCommand, setRunningCommand] = useState<string | null>(null);
    const [output, setOutput] = useState('');
    const [lastDuration, setLastDuration] = useState<number | null>(null);

    const groupedCommands = useMemo(() => commands, [commands]);
    const artisanRequest = useHttp<
        { command: string },
        {
            success: boolean;
            message?: string;
            output?: string;
            duration?: number;
        }
    >({
        command: commands[0]?.name ?? '',
    });

    const runCommand = async (command: ArtisanCommand) => {
        artisanRequest.transform(() => ({
            command: command.name,
        }));
        setSelectedCommand(command);
        setRunningCommand(command.name);
        setOutput('');
        setLastDuration(null);

        try {
            const payload = await artisanRequest.post(
                route('app.masters.laravel-tools.artisan.run'),
            );

            if (!payload.success) {
                throw new Error(
                    payload.message || 'Unable to run the command.',
                );
            }

            setOutput(
                payload.output || 'Command completed without console output.',
            );
            setLastDuration(payload.duration ?? null);
            showAppToast({
                variant: 'success',
                title: 'Command completed',
                description: payload.message,
            });
        } catch (error) {
            const message = extractHttpErrorMessage(
                error,
                'Unable to run the command.',
            );

            setOutput(message);
            showAppToast({
                variant: 'error',
                title: 'Command failed',
                description: message,
            });
        } finally {
            setRunningCommand(null);
        }
    };

    return (
        <AppLayout
            breadcrumbs={getLaravelToolsBreadcrumbs('Artisan Runner')}
            title="Artisan Runner"
            description="Execute a curated set of safe Artisan commands and inspect the console output."
        >
            <div className="flex flex-col gap-6">
                <LaravelToolsNavigation current="artisan" />

                <Alert>
                    <TerminalSquareIcon data-icon="inline-start" />
                    <AlertTitle>Approved commands only</AlertTitle>
                    <AlertDescription>
                        This runner only exposes a small allow-list of
                        maintenance commands so critical workloads are not
                        triggered from the browser.
                    </AlertDescription>
                </Alert>

                <div className="grid gap-6 xl:grid-cols-[24rem_minmax(0,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Available commands</CardTitle>
                            <CardDescription>
                                Select a command to review what it does before
                                running it.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ScrollArea className="h-[38rem] pr-4">
                                <div className="flex flex-col gap-3">
                                    {groupedCommands.map((command) => {
                                        const isSelected =
                                            selectedCommand?.name ===
                                            command.name;
                                        const isRunning =
                                            runningCommand === command.name;

                                        return (
                                            <button
                                                key={command.name}
                                                type="button"
                                                onClick={() =>
                                                    setSelectedCommand(command)
                                                }
                                                className={`rounded-xl border p-4 text-left transition-colors ${
                                                    isSelected
                                                        ? 'border-primary bg-primary/5'
                                                        : 'hover:bg-muted/50'
                                                }`}
                                            >
                                                <div className="flex flex-col gap-3">
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div className="flex min-w-0 flex-col gap-1">
                                                            <p className="truncate font-medium text-foreground">
                                                                php artisan{' '}
                                                                {command.name}
                                                            </p>
                                                            <p className="text-sm text-muted-foreground">
                                                                {
                                                                    command.description
                                                                }
                                                            </p>
                                                        </div>
                                                        {isSelected ? (
                                                            <Badge variant="info">
                                                                Selected
                                                            </Badge>
                                                        ) : null}
                                                    </div>
                                                    <div className="flex justify-end">
                                                        <Button
                                                            type="button"
                                                            variant={
                                                                isSelected
                                                                    ? 'default'
                                                                    : 'outline'
                                                            }
                                                            onClick={(
                                                                event,
                                                            ) => {
                                                                event.stopPropagation();
                                                                void runCommand(
                                                                    command,
                                                                );
                                                            }}
                                                            disabled={
                                                                artisanRequest.processing
                                                            }
                                                        >
                                                            {isRunning ? (
                                                                <Spinner />
                                                            ) : (
                                                                <PlayIcon data-icon="inline-start" />
                                                            )}
                                                            Run
                                                        </Button>
                                                    </div>
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            </ScrollArea>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-3">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="flex flex-col gap-1">
                                    <CardTitle>Command output</CardTitle>
                                    <CardDescription>
                                        Review the latest execution result and
                                        copy it into troubleshooting notes if
                                        needed.
                                    </CardDescription>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {selectedCommand ? (
                                        <Badge variant="outline">
                                            {selectedCommand.name}
                                        </Badge>
                                    ) : null}
                                    {lastDuration !== null ? (
                                        <Badge variant="secondary">
                                            <TimerResetIcon data-icon="inline-start" />
                                            {lastDuration} ms
                                        </Badge>
                                    ) : null}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setOutput('');
                                            setLastDuration(null);
                                        }}
                                        disabled={output === ''}
                                    >
                                        <RotateCcwIcon data-icon="inline-start" />
                                        Clear
                                    </Button>
                                    <Button
                                        type="button"
                                        onClick={() =>
                                            selectedCommand
                                                ? void runCommand(
                                                      selectedCommand,
                                                  )
                                                : undefined
                                        }
                                        disabled={
                                            selectedCommand === null ||
                                            artisanRequest.processing
                                        }
                                    >
                                        {artisanRequest.processing ? (
                                            <Spinner />
                                        ) : (
                                            <PlayIcon data-icon="inline-start" />
                                        )}
                                        Run selected command
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {output === '' && !runningCommand ? (
                                <Empty className="min-h-[32rem] border">
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <TerminalSquareIcon />
                                        </EmptyMedia>
                                        <EmptyTitle>
                                            No command output yet
                                        </EmptyTitle>
                                        <EmptyDescription>
                                            Choose a command from the left and
                                            run it to inspect the result.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            ) : (
                                <div className="overflow-hidden rounded-xl border bg-slate-950 text-slate-100">
                                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3 text-sm">
                                        <div className="flex min-w-0 flex-col gap-1">
                                            <span className="text-xs font-medium tracking-wide text-slate-400 uppercase">
                                                Last command
                                            </span>
                                            <span className="truncate font-medium text-slate-100">
                                                {selectedCommand
                                                    ? `php artisan ${selectedCommand.name}`
                                                    : 'Waiting for selection'}
                                            </span>
                                        </div>
                                        {runningCommand ? (
                                            <Badge variant="warning">
                                                Running…
                                            </Badge>
                                        ) : (
                                            <Badge variant="success">
                                                Ready
                                            </Badge>
                                        )}
                                    </div>
                                    <ScrollArea className="h-[32rem] px-4 py-3">
                                        {runningCommand && output === '' ? (
                                            <div className="flex h-full items-center justify-center">
                                                <Spinner />
                                            </div>
                                        ) : (
                                            <pre className="font-mono text-sm leading-6 break-words whitespace-pre-wrap text-slate-100">
                                                {output}
                                            </pre>
                                        )}
                                    </ScrollArea>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
