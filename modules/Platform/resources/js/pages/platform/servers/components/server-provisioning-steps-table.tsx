import { router } from '@inertiajs/react';
import { CheckCircleIcon, PlayCircleIcon, RefreshCwIcon } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { usePageVisibility } from '@/hooks/use-page-visibility';
import { cn } from '@/lib/utils';
import type { ProvisioningRunTimestamps, ServerProvisioningStep } from '../../../types/platform';
import { formatStatusLabel, statusBadgeVariant, STEP_STATUS_VARIANT } from './show-shared';

const PROVISIONING_POLL_INTERVAL_MS = 10_000;
const PROVISIONING_POLL_INTERVAL_LABEL = 'every 10 seconds';

type ServerProvisioningStepsTableProps = {
    serverId: number;
    steps: ServerProvisioningStep[];
    provisioningRun: ProvisioningRunTimestamps;
    provisioningStatus: string | null;
};

export function ServerProvisioningStepsTable({
    serverId,
    steps,
    provisioningRun,
    provisioningStatus,
}: ServerProvisioningStepsTableProps) {
    const isPageVisible = usePageVisibility();
    const [currentSteps, setCurrentSteps] = useState(steps);
    const [currentRun, setCurrentRun] = useState(provisioningRun);
    const [currentStatus, setCurrentStatus] = useState<string | null>(provisioningStatus);
    const [progressPercent, setProgressPercent] = useState(() => {
        const total = steps.length;
        const completed = steps.filter((step) => step.status === 'done').length;

        return total > 0 ? Math.round((completed / total) * 100) : 0;
    });
    const [isPolling, setIsPolling] = useState(provisioningStatus === 'provisioning');
    const [lastUpdatedLabel, setLastUpdatedLabel] = useState<string | null>(null);
    const [activeActionKey, setActiveActionKey] = useState<string | null>(null);
    const [pollAttemptCount, setPollAttemptCount] = useState(0);
    const [lastPollError, setLastPollError] = useState<string | null>(null);
    const [lastResponseStatus, setLastResponseStatus] = useState<number | null>(null);
    const stepsRef = useRef(steps);
    const statusRef = useRef<string | null>(provisioningStatus);
    const completionReloadedRef = useRef(false);
    const shouldShowDebugState = provisioningStatus === 'provisioning'
        || provisioningStatus === 'failed'
        || currentStatus === 'provisioning'
        || currentStatus === 'failed';
    const pollingUrl = route('platform.servers.provisioning-status', { server: serverId });

    useEffect(() => {
        const total = steps.length;
        const completed = steps.filter((step) => step.status === 'done').length;

        setCurrentSteps(steps);
        setCurrentRun(provisioningRun);
        setCurrentStatus(provisioningStatus);
        setProgressPercent(total > 0 ? Math.round((completed / total) * 100) : 0);
        setIsPolling(provisioningStatus === 'provisioning');
        setPollAttemptCount(0);
        setLastPollError(null);
        setLastResponseStatus(null);
        stepsRef.current = steps;
        statusRef.current = provisioningStatus;
        completionReloadedRef.current = false;
    }, [provisioningRun, provisioningStatus, steps]);

    const refreshProvisioningState = useCallback(async (): Promise<boolean> => {
        setPollAttemptCount((count) => count + 1);

        const response = await fetch(pollingUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        setLastResponseStatus(response.status);

        if (!response.ok) {
            throw new Error('Unable to refresh provisioning status.');
        }

        const payload = (await response.json()) as {
            provisioning_steps?: ServerProvisioningStep[];
            provisioning_run?: ProvisioningRunTimestamps;
            progress_percent?: number;
            current_status?: string | null;
        };

        const nextSteps = Array.isArray(payload.provisioning_steps) ? payload.provisioning_steps : stepsRef.current;
        const nextRun = payload.provisioning_run ?? currentRun;
        const nextStatus = typeof payload.current_status === 'string' ? payload.current_status : null;
        const total = nextSteps.length;
        const completed = nextSteps.filter((step) => step.status === 'done').length;
        const nextProgress = typeof payload.progress_percent === 'number'
            ? payload.progress_percent
            : (total > 0 ? Math.round((completed / total) * 100) : 0);
        const previousStatus = statusRef.current;

        setCurrentSteps(nextSteps);
        setCurrentRun(nextRun);
        setCurrentStatus(nextStatus);
        setProgressPercent(nextProgress);
        setLastPollError(null);
        stepsRef.current = nextSteps;
        setLastUpdatedLabel(new Date().toLocaleTimeString([], {
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
        }));
        statusRef.current = nextStatus;

        if (nextStatus === 'provisioning') {
            setIsPolling(true);

            return true;
        }

        setIsPolling(false);

        if (
            previousStatus === 'provisioning'
            && nextStatus !== 'provisioning'
            && !completionReloadedRef.current
        ) {
            completionReloadedRef.current = true;
            router.reload();
        }

        return false;
    }, [currentRun, pollingUrl]);

    useEffect(() => {
        if (!isPolling || !isPageVisible) {
            return;
        }

        let active = true;
        let timeoutId: number | null = null;

        const scheduleNextPoll = () => {
            timeoutId = window.setTimeout(() => {
                void pollProvisioningState();
            }, PROVISIONING_POLL_INTERVAL_MS);
        };

        const pollProvisioningState = async () => {
            try {
                const shouldContinuePolling = await refreshProvisioningState();

                if (active && shouldContinuePolling) {
                    scheduleNextPoll();
                }
            } catch (error) {
                if (active) {
                    const message = error instanceof Error ? error.message : 'Unknown polling error.';

                    setLastPollError(message);
                    setIsPolling(false);
                }
            }
        };

        scheduleNextPoll();

        return () => {
            active = false;
            if (timeoutId !== null) {
                window.clearTimeout(timeoutId);
            }
        };
    }, [isPageVisible, isPolling, refreshProvisioningState]);

    async function runStepAction(url: string, actionKey: string, successTitle: string): Promise<void> {
        setActiveActionKey(actionKey);

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({}),
            });

            const payload = (await response.json()) as { status?: string; message?: string };

            if (!response.ok || payload.status !== 'success') {
                throw new Error(payload.message || 'Operation failed.');
            }

            showAppToast({
                variant: 'success',
                title: successTitle,
                description: payload.message,
            });

            await refreshProvisioningState();
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: error instanceof Error ? error.message : 'Operation failed.',
            });
        } finally {
            setActiveActionKey(null);
        }
    }

    function executeStep(stepKey: string) {
        void runStepAction(
            route('platform.servers.execute.step', { server: serverId, step: stepKey }),
            stepKey,
            'Step executed successfully.',
        );
    }

    function executeAll() {
        void runStepAction(
            route('platform.servers.execute.step', { server: serverId, step: 'all' }),
            'all',
            'Provisioning run started.',
        );
        setIsPolling(true);
    }

    function refreshNow() {
        void refreshProvisioningState().catch((error) => {
            showAppToast({
                variant: 'error',
                title: error instanceof Error ? error.message : 'Unable to refresh provisioning state.',
            });
        });
    }

    const totalSteps = currentSteps.length;
    const doneSteps = currentSteps.filter((step) => step.status === 'done').length;
    const failedSteps = currentSteps.filter((step) => step.status === 'failed').length;

    return (
        <div className="flex flex-col gap-4">
            <div className="rounded-xl border bg-muted/20 p-4">
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div className="flex flex-col gap-1">
                            <h3 className="text-sm font-semibold">Provisioning Steps</h3>
                            <p className="text-sm text-muted-foreground">
                                {isPolling
                                    ? `Auto-updating ${PROVISIONING_POLL_INTERVAL_LABEL} while provisioning is running.`
                                    : 'Manual retries and refreshes are available for each step.'}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {isPolling ? (
                                <Badge variant="info" className="gap-1.5">
                                    <Spinner className="size-3.5" />
                                    Live updates
                                </Badge>
                            ) : null}
                            {lastUpdatedLabel ? (
                                <span className="text-xs text-muted-foreground">
                                    Last checked at {lastUpdatedLabel}
                                </span>
                            ) : null}
                            <Button variant="outline" size="sm" disabled={activeActionKey !== null} onClick={refreshNow}>
                                <RefreshCwIcon data-icon="inline-start" />
                                Refresh
                            </Button>
                        </div>
                    </div>

                    <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
                        <div className="flex flex-col gap-2">
                            {currentRun.started_at || currentRun.completed_at ? (
                                <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                    {currentRun.started_at ? <span>Started: {currentRun.started_at}</span> : null}
                                    {currentRun.completed_at ? <span>Completed: {currentRun.completed_at}</span> : null}
                                </div>
                            ) : null}
                            <div className="flex items-center justify-between text-xs text-muted-foreground">
                                <span>{doneSteps} of {totalSteps} completed</span>
                                <span>{progressPercent}%</span>
                            </div>
                            <div className="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    className={cn(
                                        'h-full rounded-full transition-all',
                                        isPolling ? 'bg-emerald-500/90' : failedSteps > 0 ? 'bg-amber-500' : 'bg-emerald-500',
                                    )}
                                    style={{ width: `${progressPercent}%` }}
                                />
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant={statusBadgeVariant(currentStatus)}>
                                {formatStatusLabel(currentStatus)}
                            </Badge>
                            {failedSteps > 0 ? <Badge variant="warning">{failedSteps} failed</Badge> : null}
                        </div>
                    </div>

                    {shouldShowDebugState ? (
                        <div className="rounded-lg border border-dashed bg-background/70 p-3 text-xs">
                            <div className="grid gap-2 md:grid-cols-2">
                                <div>
                                    <span className="text-muted-foreground">Attempts:</span>{' '}
                                    <span className="font-medium">{pollAttemptCount}</span>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">Last response:</span>{' '}
                                    <span className="font-medium">{lastResponseStatus ?? 'not requested yet'}</span>
                                </div>
                                <div className="md:col-span-2">
                                    <span className="text-muted-foreground">Last error:</span>{' '}
                                    <span className={cn(lastPollError ? 'font-medium text-destructive' : 'font-medium')}>
                                        {lastPollError ?? 'none'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    ) : null}

                    <div className="flex items-center justify-between gap-3">
                        <div className="text-xs text-muted-foreground">
                            Start a full run or execute individual steps without leaving the page.
                        </div>
                        <Button size="sm" disabled={activeActionKey !== null} onClick={executeAll}>
                            <PlayCircleIcon data-icon="inline-start" />
                            Run All
                        </Button>
                    </div>
                </div>
            </div>

            {currentSteps.length === 0 ? (
                <p className="text-sm text-muted-foreground">No provisioning steps configured.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left">
                                <th className="w-64 min-w-64 pb-2 pr-4 font-medium text-muted-foreground">Step</th>
                                <th className="pb-2 pr-4 font-medium text-muted-foreground">Status</th>
                                <th className="pb-2 pr-4 font-medium text-muted-foreground">Message</th>
                                <th className="pb-2 text-center font-medium text-muted-foreground">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {currentSteps.map((step) => (
                                <tr key={step.key} className="border-b last:border-0">
                                    <td className="w-50 min-w-50 py-3 pr-4 align-top">
                                        <p className="font-medium">{step.title}</p>
                                        {step.description ? (
                                            <p className="text-xs text-muted-foreground">{step.description}</p>
                                        ) : null}
                                        {step.started_at || step.completed_at ? (
                                            <div className="mt-1 flex flex-col gap-0.5 text-[11px] text-muted-foreground">
                                                {step.started_at ? <span>Started: {step.started_at}</span> : null}
                                                {step.completed_at ? <span>Completed: {step.completed_at}</span> : null}
                                            </div>
                                        ) : null}
                                    </td>
                                    <td className="py-3 pr-4 align-top">
                                        <Badge variant={STEP_STATUS_VARIANT[step.status] ?? 'secondary'}>
                                            {step.status === 'done' ? <CheckCircleIcon data-icon="inline-start" /> : null}
                                            {formatStatusLabel(step.status)}
                                        </Badge>
                                    </td>
                                    <td className="py-3 pr-4 align-top text-muted-foreground">
                                        <pre className="w-[600px] max-w-[600px] max-h-[550px] overflow-auto whitespace-pre-wrap break-words text-sm">{step.message ?? ''}</pre>
                                    </td>
                                    <td className="py-3 align-top text-center">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            disabled={activeActionKey !== null || isPolling}
                                            onClick={() => executeStep(step.key)}
                                        >
                                            {activeActionKey === step.key ? (
                                                <Spinner className="size-3.5" />
                                            ) : (
                                                <PlayCircleIcon data-icon="inline-start" />
                                            )}
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
