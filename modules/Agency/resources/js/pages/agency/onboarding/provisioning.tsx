import { Link } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    CheckCircle2Icon,
    ExternalLinkIcon,
    LoaderCircleIcon,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import AgencyOnboardingLayout from '../../../components/agency-onboarding-layout';

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

    const handleConfirmDns = async () => {
        if (status.confirm_dns_url === undefined || status.confirm_dns_url === '') {
            return;
        }

        setIsConfirmingDns(true);
        setStatusMessage(null);

        try {
            const csrfToken =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content')
                ?? '';
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

    const isComplete = status.is_complete;
    const isFailed = status.is_failed;
    const isWaiting = status.is_waiting;
    const headline = status.headline || 'Preparing your website';
    const detail = status.detail || 'Provisioning is in progress.';
    const manageUrl = status.manage_url || route('agency.websites.index');
    const websitesUrl = status.websites_url || route('agency.websites.index');
    const supportUrl = status.support_url || route('agency.tickets.create');
    const steps = status.steps ?? [];
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

    const badgeVariantForStep = (stepStatus: string) => {
        return stepStatus === 'completed'
            ? 'success'
            : stepStatus === 'failed'
                ? 'danger'
                : stepStatus === 'in_progress'
                    ? 'info'
                    : 'outline';
    };

    return (
        <AgencyOnboardingLayout
            title="Provisioning"
            description={`Setting up ${website.domain}`}
            currentStep="provisioning"
            backHref={route('agency.websites.index')}
            backLabel="Exit Setup"
        >
            <div className="space-y-6">
                <Card className="rounded-[2rem] border-black/6 bg-white/92 shadow-[0_20px_80px_rgba(33,30,22,0.08)] dark:border-white/10 dark:bg-white/5 dark:shadow-none">
                    <CardHeader className="space-y-5 border-b border-black/6 pb-6 dark:border-white/10">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div className="flex items-center gap-4">
                                <div className="flex size-16 items-center justify-center rounded-full bg-muted">
                                    {isComplete ? (
                                        <CheckCircle2Icon className="size-8 text-[var(--success-foreground)] dark:text-[var(--success-dark-foreground)]" />
                                    ) : isFailed ? (
                                        <AlertTriangleIcon className="size-8 text-destructive" />
                                    ) : (
                                        <LoaderCircleIcon className="size-8 animate-spin text-primary" />
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant={isComplete ? 'success' : isFailed ? 'danger' : isWaiting ? 'warning' : 'info'}>
                                            {status.status_label}
                                        </Badge>
                                        <Badge variant="outline">{website.domain}</Badge>
                                    </div>
                                    <CardTitle className="text-3xl tracking-[-0.03em]">
                                        {headline}
                                    </CardTitle>
                                    <CardDescription className="max-w-3xl text-sm leading-6">
                                        {detail}
                                    </CardDescription>
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-3">
                                {isComplete ? (
                                    <>
                                        <Button asChild>
                                            <Link href={manageUrl}>
                                                Go to Website
                                                <ExternalLinkIcon className="size-4" />
                                            </Link>
                                        </Button>
                                        <Button asChild variant="outline">
                                            <Link href={websitesUrl}>All Websites</Link>
                                        </Button>
                                    </>
                                ) : isFailed ? (
                                    <>
                                        <Button asChild variant="destructive">
                                            <Link href={supportUrl}>Contact Support</Link>
                                        </Button>
                                        <Button asChild variant="outline">
                                            <Link href={websitesUrl}>All Websites</Link>
                                        </Button>
                                    </>
                                ) : (
                                    <Button asChild variant="outline">
                                        <Link href={websitesUrl}>Back to Websites</Link>
                                    </Button>
                                )}
                            </div>
                        </div>

                        <div className="space-y-3">
                            <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                                <span>
                                    {progress.completed_steps}
                                    /
                                    {progress.total_steps}
                                    {' '}
                                    provisioning stages completed
                                </span>
                                <span>
                                    {progress.percentage}
                                    %
                                </span>
                            </div>
                            <Progress value={progress.percentage} className="h-2" />
                        </div>
                    </CardHeader>
                </Card>

                {statusMessage !== null ? (
                    <div className="rounded-[1.5rem] border border-primary/20 bg-primary/5 px-5 py-4 text-sm leading-6 text-foreground">
                        {statusMessage}
                    </div>
                ) : null}

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="space-y-6">
                        {isWaiting ? (
                            <Card className="rounded-[2rem] border-black/6 bg-white/92 shadow-[0_20px_80px_rgba(33,30,22,0.08)] dark:border-white/10 dark:bg-white/5 dark:shadow-none">
                                <CardHeader className="space-y-2">
                                    <CardTitle className="text-xl tracking-[-0.02em]">
                                        DNS action required
                                    </CardTitle>
                                    <CardDescription className="text-sm leading-6">
                                        Complete the DNS steps below so the platform can finish
                                        connecting the website.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    {hasNameservers ? (
                                        <div className="space-y-3">
                                            <p className="text-sm font-medium">Update nameservers</p>
                                            <div className="space-y-3">
                                                {dnsInstructions?.nameservers?.map((nameserver) => (
                                                    <div
                                                        key={nameserver}
                                                        className="rounded-[1.25rem] border bg-background px-4 py-3 font-mono text-sm"
                                                    >
                                                        {nameserver}
                                                    </div>
                                                ))}
                                            </div>
                                            <p className="text-sm leading-6 text-muted-foreground">
                                                Nameserver changes may take up to 24 hours to
                                                propagate globally.
                                            </p>
                                        </div>
                                    ) : null}

                                    {hasDnsRecords ? (
                                        <div className="space-y-3">
                                            <p className="text-sm font-medium">
                                                Add these DNS records
                                            </p>
                                            <div className="overflow-hidden rounded-[1.5rem] border">
                                                <div className="grid grid-cols-[7rem_minmax(0,1fr)_minmax(0,1fr)] gap-3 border-b bg-muted/50 px-4 py-3 text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                                    <span>Type</span>
                                                    <span>Host</span>
                                                    <span>Value</span>
                                                </div>
                                                {dnsInstructions?.records?.map((record, index) => (
                                                    <div
                                                        key={`${record.type ?? 'record'}-${record.host ?? record.name ?? index}`}
                                                        className="grid grid-cols-[7rem_minmax(0,1fr)_minmax(0,1fr)] gap-3 border-b px-4 py-3 text-sm last:border-b-0"
                                                    >
                                                        <span className="font-medium">
                                                            {record.type ?? '—'}
                                                        </span>
                                                        <span className="break-all text-muted-foreground">
                                                            {record.host ?? record.name ?? '—'}
                                                        </span>
                                                        <span className="break-all text-muted-foreground">
                                                            {record.value ?? record.target ?? '—'}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                            {dnsInstructions?.note ? (
                                                <p className="text-sm leading-6 text-muted-foreground">
                                                    {dnsInstructions.note}
                                                </p>
                                            ) : null}
                                        </div>
                                    ) : null}

                                    {!hasNameservers && !hasDnsRecords ? (
                                        <div className="rounded-[1.5rem] border border-dashed px-4 py-3 text-sm leading-6 text-muted-foreground">
                                            DNS instructions will appear here as soon as the platform
                                            returns them.
                                        </div>
                                    ) : null}

                                    {status.dns_domain_not_registered ? (
                                        <div className="rounded-[1.5rem] border border-amber-200 bg-amber-100 px-4 py-3 text-sm leading-6 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/15 dark:text-amber-300">
                                            The domain does not appear to be registered in DNS yet.
                                            Register it first, then return here and continue.
                                        </div>
                                    ) : null}

                                    {status.dns_confirmed_by_user ? (
                                        <div className="rounded-[1.5rem] border border-primary/20 bg-primary/5 px-4 py-3 text-sm leading-6 text-foreground">
                                            DNS confirmation received. We are checking propagation
                                            automatically every 10 seconds.
                                        </div>
                                    ) : (
                                        <Button
                                            type="button"
                                            onClick={() => {
                                                void handleConfirmDns();
                                            }}
                                            disabled={isConfirmingDns}
                                        >
                                            {isConfirmingDns
                                                ? 'Confirming DNS Update...'
                                                : hasDnsRecords
                                                    ? 'I Added the DNS Records'
                                                    : 'I Updated the Nameservers'}
                                        </Button>
                                    )}

                                    {observedNameservers.length > 0 ? (
                                        <>
                                            <Separator />
                                            <div className="space-y-3">
                                                <p className="text-sm font-medium">
                                                    Currently observed nameservers
                                                </p>
                                                <div className="space-y-3">
                                                    {observedNameservers.map((nameserver) => (
                                                        <div
                                                            key={nameserver}
                                                            className="rounded-[1.25rem] border bg-background px-4 py-3 font-mono text-sm"
                                                        >
                                                            {nameserver}
                                                        </div>
                                                    ))}
                                                </div>
                                                <p className="text-sm leading-6 text-muted-foreground">
                                                    Verification check #
                                                    {status.dns_check_count ?? 0}
                                                </p>
                                            </div>
                                        </>
                                    ) : null}
                                </CardContent>
                            </Card>
                        ) : null}

                        <Card className="rounded-[2rem] border-black/6 bg-white/92 shadow-[0_20px_80px_rgba(33,30,22,0.08)] dark:border-white/10 dark:bg-white/5 dark:shadow-none">
                            <CardHeader className="space-y-2">
                                <CardTitle className="text-xl tracking-[-0.02em]">
                                    Provisioning timeline
                                </CardTitle>
                                <CardDescription className="text-sm leading-6">
                                    Each stage updates here automatically as the platform advances.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {steps.length > 0 ? (
                                    steps.map((step, index) => (
                                        <div key={step.key} className="flex gap-4">
                                            <div className="flex flex-col items-center">
                                                <div className="mt-1 size-3 rounded-full bg-primary/70" />
                                                {index < steps.length - 1 ? (
                                                    <div className="mt-2 h-full w-px bg-border" />
                                                ) : null}
                                            </div>

                                            <div className="min-w-0 space-y-2 pb-5">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-semibold">{step.title}</p>
                                                    <Badge variant={badgeVariantForStep(step.status)}>
                                                        {step.status_label}
                                                    </Badge>
                                                    {step.is_email_step ? (
                                                        <Badge variant="outline">Email</Badge>
                                                    ) : null}
                                                </div>
                                                <p className="text-sm leading-6 text-muted-foreground">
                                                    {step.message}
                                                </p>
                                                {step.updated_at_display ? (
                                                    <p className="text-xs text-muted-foreground">
                                                        Updated {step.updated_at_display}
                                                        {step.updated_at_ago
                                                            ? ` (${step.updated_at_ago})`
                                                            : ''}
                                                    </p>
                                                ) : null}
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm leading-6 text-muted-foreground">
                                        We are waiting for the first provisioning updates from the
                                        platform.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-4">
                        <Card className="rounded-[2rem] border-black/6 bg-white/88 dark:border-white/10 dark:bg-white/5">
                            <CardHeader className="space-y-2">
                                <CardTitle className="text-lg">Next actions</CardTitle>
                                <CardDescription>
                                    Keep this page open and let the system work through the launch.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {nextActions.length > 0 ? (
                                    nextActions.map((action) => (
                                        <div
                                            key={action}
                                            className="rounded-[1.25rem] border px-4 py-3 text-sm leading-6 text-muted-foreground"
                                        >
                                            {action}
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm leading-6 text-muted-foreground">
                                        No manual action is needed right now.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="rounded-[2rem] border-black/6 bg-white/88 dark:border-white/10 dark:bg-white/5">
                            <CardHeader className="space-y-2">
                                <CardTitle className="text-lg">Website</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm leading-6 text-muted-foreground">
                                <p>
                                    <span className="font-medium text-foreground">Name:</span>
                                    {' '}
                                    {website.name}
                                </p>
                                <p>
                                    <span className="font-medium text-foreground">Domain:</span>
                                    {' '}
                                    {website.domain}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AgencyOnboardingLayout>
    );
}
