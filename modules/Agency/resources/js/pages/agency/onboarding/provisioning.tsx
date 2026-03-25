import { Link } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    CheckCircle2Icon,
    CopyIcon,
    ExternalLinkIcon,
    LoaderCircleIcon,
    SearchCheckIcon,
    ServerIcon,
} from 'lucide-react';
import { useEffect, useState, type ReactElement } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AgencyOnboardingMinimalLayout from '../../../components/agency-onboarding-minimal-layout';

type ProvisioningPageProps = {
    website: {
        id: number;
        name: string;
        domain: string;
    };
    statusData: Record<string, unknown>;
    statusUrl: string;
};

type ProvisioningStep = {
    key: string;
    title: string;
    description: string;
    status: string;
    status_label: string;
    message: string;
    updated_at_display?: string | null;
    updated_at_ago?: string | null;
    is_email_step?: boolean;
};

type ProvisioningProgress = {
    total_steps: number;
    completed_steps: number;
    failed_steps: number;
    in_progress_steps: number;
    pending_steps: number;
    percentage: number;
};

type DnsInstructionRecord = {
    type?: string;
    host?: string;
    name?: string;
    value?: string;
    target?: string;
};

type DnsInstructions = {
    mode?: string;
    nameservers?: string[];
    records?: DnsInstructionRecord[];
    note?: string;
};

type ProvisioningStatusData = {
    status: string;
    status_label: string;
    headline: string;
    detail: string;
    is_complete: boolean;
    is_failed: boolean;
    is_waiting: boolean;
    next_actions?: string[];
    manage_url?: string;
    websites_url?: string;
    support_url?: string;
    confirm_dns_url?: string;
    dns_instructions?: DnsInstructions | null;
    dns_confirmed_by_user?: boolean;
    dns_check_count?: number;
    dns_check_result?: {
        observed_ns?: string[];
    } | null;
    dns_domain_not_registered?: boolean;
    progress?: ProvisioningProgress;
    steps?: ProvisioningStep[];
};

const loadingMessages = [
    'Preparing your website',
    'Setting up hosting',
    'Configuring your domain',
    'Installing website files',
    'Setting up SSL certificate',
    'Running security checks',
    'Almost there',
];

function ProgressRail(): ReactElement {
    return (
        <div className="mt-7 h-[3px] w-52 overflow-hidden rounded-full bg-border/80">
            <div className="h-full w-1/2 animate-[provisioning-slide_1.4s_ease-in-out_infinite] rounded-full bg-primary" />
        </div>
    );
}

function StatusIcon({
    variant,
}: {
    variant: 'loading' | 'success' | 'danger' | 'warning';
}): ReactElement {
    const icon =
        variant === 'success' ? (
            <CheckCircle2Icon className="size-8 text-[var(--success-foreground)] dark:text-[var(--success-dark-foreground)]" />
        ) : variant === 'danger' ? (
            <AlertTriangleIcon className="size-8 text-destructive" />
        ) : variant === 'warning' ? (
            <ServerIcon className="size-8 text-amber-600 dark:text-amber-400" />
        ) : (
            <LoaderCircleIcon className="size-8 animate-spin text-primary" />
        );

    const tone =
        variant === 'success'
            ? 'bg-[var(--success-background)] dark:bg-[var(--success-dark-background)]'
            : variant === 'danger'
                ? 'bg-destructive/10'
                : variant === 'warning'
                    ? 'bg-amber-100 dark:bg-amber-500/15'
                    : 'bg-muted';

    return <div className={`inline-flex size-14 items-center justify-center rounded-full ${tone}`}>{icon}</div>;
}

export default function AgencyOnboardingProvisioning({
    website,
    statusData,
    statusUrl,
}: ProvisioningPageProps) {
    const [status, setStatus] = useState<ProvisioningStatusData>(
        statusData as ProvisioningStatusData,
    );
    const [statusMessage, setStatusMessage] = useState<string | null>(null);
    const [isConfirmingDns, setIsConfirmingDns] = useState(false);
    const [loadingMessageIndex, setLoadingMessageIndex] = useState(0);
    const [copiedValue, setCopiedValue] = useState<string | null>(null);

    useEffect(() => {
        if (status.is_complete || status.is_failed) {
            return;
        }

        const timer = window.setInterval(() => {
            void (async () => {
                const response = await fetch(statusUrl, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    return;
                }

                const payload = (await response.json()) as ProvisioningStatusData;
                setStatus(payload);
            })();
        }, 10_000);

        return () => {
            window.clearInterval(timer);
        };
    }, [status.is_complete, status.is_failed, statusUrl]);

    useEffect(() => {
        if (status.is_complete || status.is_failed || status.is_waiting) {
            return;
        }

        const timer = window.setInterval(() => {
            setLoadingMessageIndex((currentIndex) => {
                return (currentIndex + 1) % loadingMessages.length;
            });
        }, 3_500);

        return () => {
            window.clearInterval(timer);
        };
    }, [status.is_complete, status.is_failed, status.is_waiting]);

    useEffect(() => {
        if (copiedValue === null) {
            return;
        }

        const timer = window.setTimeout(() => {
            setCopiedValue(null);
        }, 2_000);

        return () => {
            window.clearTimeout(timer);
        };
    }, [copiedValue]);

    const handleConfirmDns = async (): Promise<void> => {
        if (status.confirm_dns_url === undefined || status.confirm_dns_url === '') {
            return;
        }

        setIsConfirmingDns(true);
        setStatusMessage(null);

        try {
            const csrfToken =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ?? '';
            const response = await fetch(status.confirm_dns_url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = (await response.json()) as { message?: string };

            if (!response.ok) {
                setStatusMessage(
                    payload.message ?? 'Unable to confirm DNS update. Please try again.',
                );

                return;
            }

            setStatusMessage(
                payload.message
                    ?? 'DNS confirmation received. Verification checks will begin shortly.',
            );
            setStatus((currentStatus) => ({
                ...currentStatus,
                dns_confirmed_by_user: true,
            }));

            const refreshedStatusResponse = await fetch(statusUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (refreshedStatusResponse.ok) {
                const refreshedStatus =
                    (await refreshedStatusResponse.json()) as ProvisioningStatusData;
                setStatus(refreshedStatus);
            }
        } catch {
            setStatusMessage('Unable to confirm DNS update. Please try again.');
        } finally {
            setIsConfirmingDns(false);
        }
    };

    const handleCopy = async (value: string): Promise<void> => {
        try {
            await navigator.clipboard.writeText(value);
            setCopiedValue(value);
        } catch {
            setCopiedValue(null);
        }
    };

    const isComplete = status.is_complete;
    const isFailed = status.is_failed;
    const isWaiting = status.is_waiting;
    const headline =
        isWaiting || isComplete || isFailed
            ? status.headline
            : loadingMessages[loadingMessageIndex] ?? 'Preparing your website';
    const detail = status.detail || 'Provisioning is in progress.';
    const manageUrl = status.manage_url || route('agency.websites.index');
    const websitesUrl = status.websites_url || route('agency.websites.index');
    const supportUrl = status.support_url || route('agency.tickets.create');
    const nextActions = status.next_actions ?? [];
    const progress = status.progress ?? {
        total_steps: 0,
        completed_steps: 0,
        failed_steps: 0,
        in_progress_steps: 0,
        pending_steps: 0,
        percentage: 0,
    };
    const dnsInstructions = status.dns_instructions ?? null;
    const observedNameservers = status.dns_check_result?.observed_ns ?? [];
    const hasNameservers =
        dnsInstructions !== null
        && Array.isArray(dnsInstructions.nameservers)
        && dnsInstructions.nameservers.length > 0;
    const hasDnsRecords =
        dnsInstructions !== null
        && Array.isArray(dnsInstructions.records)
        && dnsInstructions.records.length > 0;
    const dnsConfirmationLabel = hasDnsRecords
        ? "I've Added My DNS Records"
        : "I've Updated My Nameservers";

    return (
        <AgencyOnboardingMinimalLayout
            title="Provisioning"
            description={`Setting up ${website.domain}`}
            contentWidthClassName="max-w-3xl"
            hideHeading
        >
            <style>{`
                @keyframes provisioning-slide {
                    0% { transform: translateX(-100%); }
                    100% { transform: translateX(200%); }
                }
            `}</style>

            <div className="flex min-h-[calc(100svh-10rem)] items-center justify-center">
                <div className="w-full space-y-8 text-center">
                    <div className="mx-auto flex max-w-2xl flex-col items-center">
                        {isComplete ? (
                            <StatusIcon variant="success" />
                        ) : isFailed ? (
                            <StatusIcon variant="danger" />
                        ) : isWaiting ? (
                            <StatusIcon variant="warning" />
                        ) : (
                            <StatusIcon variant="loading" />
                        )}

                        <div className="mt-6 space-y-3">
                            <div className="flex justify-center">
                                <Badge variant={isComplete ? 'success' : isFailed ? 'danger' : isWaiting ? 'warning' : 'info'}>
                                    {status.status_label}
                                </Badge>
                            </div>

                            <h1 className="text-3xl font-medium tracking-[-0.035em] text-balance sm:text-[2.65rem]">
                                {headline}
                            </h1>

                            <p className="mx-auto max-w-xl text-sm leading-7 text-muted-foreground sm:text-base">
                                {detail}
                            </p>
                        </div>

                        {!isComplete && !isFailed && !isWaiting ? <ProgressRail /> : null}

                        {!isComplete && !isFailed && !isWaiting ? (
                            <p className="mt-4 text-sm text-muted-foreground">
                                {progress.completed_steps > 0 && progress.total_steps > 0
                                    ? `${progress.completed_steps} of ${progress.total_steps} setup steps completed`
                                    : 'This usually takes a few minutes. Keep this page open while we refresh the latest status.'}
                            </p>
                        ) : null}
                    </div>

                    {statusMessage !== null ? (
                        <div className="mx-auto max-w-2xl rounded-2xl border border-primary/15 bg-primary/5 px-4 py-3 text-sm leading-6 text-foreground">
                            {statusMessage}
                        </div>
                    ) : null}

                    {isWaiting ? (
                        <div className="mx-auto max-w-2xl space-y-6 text-left">
                            <div className="rounded-3xl border border-border/80 bg-card/70 p-6 shadow-none backdrop-blur-sm">
                                <div className="flex flex-wrap items-start justify-between gap-4 border-b border-border/70 pb-5">
                                    <div className="space-y-1">
                                        <h2 className="text-lg font-medium tracking-[-0.02em]">
                                            DNS instructions
                                        </h2>
                                        <p className="text-sm leading-6 text-muted-foreground">
                                            Complete the DNS update below, then come back here and start verification.
                                        </p>
                                    </div>

                                    <div className="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
                                        {website.domain}
                                    </div>
                                </div>

                                <div className="mt-5 space-y-5">
                                    {hasNameservers ? (
                                        <div className="space-y-3">
                                            <p className="text-sm font-medium text-foreground">
                                                Update your nameservers to:
                                            </p>
                                            <div className="space-y-2.5">
                                                {dnsInstructions?.nameservers?.map((nameserver) => (
                                                    <div
                                                        key={nameserver}
                                                        className="flex items-center justify-between gap-3 rounded-2xl border bg-background px-4 py-3"
                                                    >
                                                        <span className="min-w-0 break-all font-mono text-sm text-foreground">
                                                            {nameserver}
                                                        </span>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="shrink-0 rounded-lg"
                                                            onClick={() => {
                                                                void handleCopy(nameserver);
                                                            }}
                                                        >
                                                            <CopyIcon className="size-4" />
                                                        </Button>
                                                    </div>
                                                ))}
                                            </div>
                                            <p className="text-sm leading-6 text-muted-foreground">
                                                Nameserver changes can take up to 24 hours to propagate globally.
                                            </p>
                                        </div>
                                    ) : null}

                                    {hasDnsRecords ? (
                                        <div className="space-y-3">
                                            <p className="text-sm font-medium text-foreground">
                                                Add these DNS records:
                                            </p>
                                            <div className="overflow-hidden rounded-2xl border border-border/80">
                                                <div className="grid grid-cols-[5rem_minmax(0,1fr)_minmax(0,1.35fr)] gap-3 border-b border-border/70 bg-muted/50 px-4 py-3 text-[11px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                                    <span>Type</span>
                                                    <span>Host</span>
                                                    <span>Value</span>
                                                </div>
                                                {dnsInstructions?.records?.map((record, index) => {
                                                    const hostValue = record.host ?? record.name ?? '—';
                                                    const targetValue = record.value ?? record.target ?? '—';

                                                    return (
                                                        <div
                                                            key={`${record.type ?? 'record'}-${hostValue}-${index}`}
                                                            className="grid grid-cols-[5rem_minmax(0,1fr)_minmax(0,1.35fr)] gap-3 border-b border-border/60 px-4 py-3 text-sm last:border-b-0"
                                                        >
                                                            <span className="font-medium text-foreground">
                                                                {record.type ?? '—'}
                                                            </span>
                                                            <button
                                                                type="button"
                                                                className="flex min-w-0 items-center gap-2 text-left text-muted-foreground"
                                                                onClick={() => {
                                                                    void handleCopy(hostValue);
                                                                }}
                                                            >
                                                                <span className="break-all">{hostValue}</span>
                                                                <CopyIcon className="size-3.5 shrink-0" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                className="flex min-w-0 items-center gap-2 text-left text-muted-foreground"
                                                                onClick={() => {
                                                                    void handleCopy(targetValue);
                                                                }}
                                                            >
                                                                <span className="break-all">{targetValue}</span>
                                                                <CopyIcon className="size-3.5 shrink-0" />
                                                            </button>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                            {dnsInstructions?.note ? (
                                                <p className="text-sm leading-6 text-muted-foreground">
                                                    {dnsInstructions.note}
                                                </p>
                                            ) : null}
                                        </div>
                                    ) : null}

                                    {!hasNameservers && !hasDnsRecords ? (
                                        <div className="rounded-2xl border border-dashed border-border/80 px-4 py-3 text-sm leading-6 text-muted-foreground">
                                            DNS instructions will appear here as soon as the platform returns them.
                                        </div>
                                    ) : null}

                                    {copiedValue !== null ? (
                                        <p className="text-sm text-primary">
                                            Copied {copiedValue}.
                                        </p>
                                    ) : null}

                                    {status.dns_domain_not_registered ? (
                                        <div className="rounded-2xl border border-amber-200 bg-amber-100 px-4 py-3 text-sm leading-6 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/15 dark:text-amber-300">
                                            The domain does not appear to be registered yet. Register it first, then return here and continue.
                                        </div>
                                    ) : null}

                                    {status.dns_confirmed_by_user ? (
                                        <div className="space-y-4 rounded-2xl border border-primary/15 bg-primary/5 px-4 py-4">
                                            <div className="flex items-center gap-3 text-sm text-foreground">
                                                <LoaderCircleIcon className="size-4 animate-spin text-primary" />
                                                <span>
                                                    Verifying DNS propagation. Check #{status.dns_check_count ?? 0}.
                                                </span>
                                            </div>

                                            {observedNameservers.length > 0 ? (
                                                <div className="space-y-3">
                                                    <p className="text-sm font-medium text-foreground">
                                                        Currently observed nameservers
                                                    </p>
                                                    <div className="space-y-2">
                                                        {observedNameservers.map((nameserver) => (
                                                            <div
                                                                key={nameserver}
                                                                className="flex items-center gap-3 rounded-2xl border bg-background px-4 py-3"
                                                            >
                                                                <SearchCheckIcon className="size-4 shrink-0 text-muted-foreground" />
                                                                <span className="break-all font-mono text-sm text-muted-foreground">
                                                                    {nameserver}
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            ) : null}

                                            <p className="text-sm leading-6 text-muted-foreground">
                                                We will keep checking automatically every 10 seconds until DNS is verified.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            <p className="text-sm leading-6 text-muted-foreground">
                                                After you finish the DNS update, start verification and we will watch for the change automatically.
                                            </p>
                                            <Button
                                                type="button"
                                                size="xl"
                                                className="w-full rounded-lg bg-primary text-primary-foreground hover:bg-primary/90"
                                                onClick={() => {
                                                    void handleConfirmDns();
                                                }}
                                                disabled={isConfirmingDns}
                                            >
                                                {isConfirmingDns ? 'Starting Verification...' : dnsConfirmationLabel}
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-3 text-center">
                                <Button asChild variant="outline" className="rounded-lg">
                                    <Link href={supportUrl}>Need Help?</Link>
                                </Button>
                                {nextActions.length > 0 ? (
                                    <div className="space-y-1 text-sm text-muted-foreground">
                                        {nextActions.map((action) => (
                                            <p key={action}>{action}</p>
                                        ))}
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    ) : null}

                    {isComplete ? (
                        <div className="space-y-6">
                            <div className="flex flex-wrap justify-center gap-3">
                                <Button asChild className="rounded-lg bg-primary text-primary-foreground hover:bg-primary/90">
                                    <Link href={manageUrl}>
                                        Go to Website
                                        <ExternalLinkIcon className="size-4" />
                                    </Link>
                                </Button>
                                <Button asChild variant="outline" className="rounded-lg">
                                    <Link href={websitesUrl}>All Websites</Link>
                                </Button>
                            </div>

                            {nextActions.length > 0 ? (
                                <div className="mx-auto max-w-xl space-y-2 text-sm leading-6 text-muted-foreground">
                                    {nextActions.map((action) => (
                                        <p key={action}>{action}</p>
                                    ))}
                                </div>
                            ) : null}
                        </div>
                    ) : null}

                    {isFailed ? (
                        <div className="space-y-6">
                            <div className="flex flex-wrap justify-center gap-3">
                                <Button asChild variant="outline" className="rounded-lg border-destructive/25 text-destructive hover:bg-destructive/5 hover:text-destructive">
                                    <Link href={supportUrl}>Contact Support</Link>
                                </Button>
                                <Button asChild variant="outline" className="rounded-lg">
                                    <Link href={websitesUrl}>All Websites</Link>
                                </Button>
                            </div>

                            {nextActions.length > 0 ? (
                                <div className="mx-auto max-w-xl space-y-2 text-sm leading-6 text-muted-foreground">
                                    {nextActions.map((action) => (
                                        <p key={action}>{action}</p>
                                    ))}
                                </div>
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </div>
        </AgencyOnboardingMinimalLayout>
    );
}
