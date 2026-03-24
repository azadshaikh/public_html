import { Link, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon, LifeBuoyIcon, RocketIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import AppHead from '@/components/app-head';
import { AppThemeToggle } from '@/components/app-theme-toggle';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';

type AgencyOnboardingStepKey = 'domain' | 'plans' | 'checkout' | 'provisioning';

type AgencyOnboardingLayoutProps = {
    children: ReactNode;
    title: string;
    description: string;
    currentStep: AgencyOnboardingStepKey;
    backHref?: string;
    backLabel?: string;
};

const steps = [
    {
        key: 'domain',
        label: 'Choose Domain',
        description: 'Decide where the new website will live.',
    },
    {
        key: 'plans',
        label: 'Select Plan',
        description: 'Pick the launch package and billing cadence.',
    },
    {
        key: 'checkout',
        label: 'Review Order',
        description: 'Confirm the setup details before provisioning starts.',
    },
    {
        key: 'provisioning',
        label: 'Provision Website',
        description: 'Track setup progress until the website is ready.',
    },
] as const;

export default function AgencyOnboardingLayout({
    children,
    title,
    description,
    currentStep,
    backHref,
    backLabel = 'Back',
}: AgencyOnboardingLayoutProps) {
    const { appName, branding } = usePage<SharedData>().props;
    const brandName = branding?.name?.trim() || appName;
    const logoUrl = branding?.logo?.trim() || branding?.icon?.trim() || '';
    const currentStepIndex = Math.max(steps.findIndex((step) => step.key === currentStep), 0);
    const websitesHref = route().has('agency.websites.index')
        ? route('agency.websites.index')
        : route('dashboard');
    const supportHref = route().has('agency.tickets.create')
        ? route('agency.tickets.create')
        : websitesHref;

    return (
        <>
            <AppHead title={title} description={description} />

            <div className="min-h-svh bg-[linear-gradient(180deg,#f7f2e6_0%,#fcfaf4_18%,#ffffff_38%,#ffffff_100%)] text-foreground dark:bg-[linear-gradient(180deg,#0f1112_0%,#111416_18%,#151819_38%,#171a1c_100%)]">
                <header className="sticky top-0 z-20 border-b border-black/6 bg-white/85 backdrop-blur dark:border-white/10 dark:bg-[#111416]/85">
                    <div className="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-6 py-4 sm:px-8 lg:px-10">
                        <Link href={websitesHref} className="flex min-w-0 items-center gap-3 text-foreground">
                            <div className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-[1.15rem] border border-black/8 bg-white shadow-[0_12px_40px_rgba(25,22,16,0.08)] dark:border-white/10 dark:bg-white/5 dark:shadow-none">
                                {logoUrl !== '' ? (
                                    <img
                                        src={logoUrl}
                                        alt={brandName}
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <span className="text-lg font-semibold">
                                        {brandName.charAt(0)}
                                    </span>
                                )}
                            </div>

                            <div className="min-w-0">
                                <p className="text-[0.68rem] font-semibold tracking-[0.24em] text-muted-foreground uppercase">
                                    Agency Setup
                                </p>
                                <p className="truncate text-base font-semibold">{brandName}</p>
                            </div>
                        </Link>

                        <div className="flex items-center gap-3">
                            <AppThemeToggle />

                            {backHref ? (
                                <Button asChild variant="outline">
                                    <Link href={backHref}>
                                        <ArrowLeftIcon className="size-4" />
                                        {backLabel}
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-7xl px-6 py-8 sm:px-8 lg:px-10 lg:py-10">
                    <div className="grid gap-8 xl:grid-cols-[18rem_minmax(0,1fr)] xl:gap-12">
                        <aside className="hidden xl:block">
                            <div className="sticky top-28 space-y-5">
                                <div className="space-y-3">
                                    <Badge variant="outline" className="rounded-full px-3 py-1">
                                        Step {currentStepIndex + 1} of {steps.length}
                                    </Badge>
                                    <div className="space-y-2">
                                        <h2 className="text-2xl font-semibold tracking-[-0.03em]">
                                            Launch a new client website
                                        </h2>
                                        <p className="text-sm leading-6 text-muted-foreground">
                                            Move through the setup in order. You can come back to
                                            websites at any point without losing your progress.
                                        </p>
                                    </div>
                                </div>

                                <div className="rounded-[2rem] border border-black/6 bg-white/92 p-5 shadow-[0_20px_80px_rgba(33,30,22,0.08)] backdrop-blur dark:border-white/10 dark:bg-white/5 dark:shadow-none">
                                    <div className="space-y-4">
                                        {steps.map((step, index) => {
                                            const isComplete = index < currentStepIndex;
                                            const isCurrent = index === currentStepIndex;

                                            return (
                                                <div key={step.key} className="flex gap-3">
                                                    <div className="flex flex-col items-center">
                                                        <div
                                                            className={cn(
                                                                'flex size-8 items-center justify-center rounded-full border text-xs font-semibold',
                                                                isComplete
                                                                    ? 'border-[var(--success-border)] bg-[var(--success-bg)] text-[var(--success-foreground)] dark:border-[var(--success-dark-border)] dark:bg-[var(--success-dark-bg)] dark:text-[var(--success-dark-foreground)]'
                                                                    : isCurrent
                                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                                        : 'border-border bg-muted text-muted-foreground',
                                                            )}
                                                        >
                                                            {isComplete ? (
                                                                <CheckIcon className="size-4" />
                                                            ) : (
                                                                index + 1
                                                            )}
                                                        </div>

                                                        {index < steps.length - 1 ? (
                                                            <div
                                                                className={cn(
                                                                    'mt-2 h-10 w-px',
                                                                    isComplete
                                                                        ? 'bg-primary/30'
                                                                        : 'bg-border',
                                                                )}
                                                            />
                                                        ) : null}
                                                    </div>

                                                    <div className="min-w-0 space-y-1 pb-6">
                                                        <p
                                                            className={cn(
                                                                'text-sm font-semibold',
                                                                isCurrent
                                                                    ? 'text-foreground'
                                                                    : 'text-muted-foreground',
                                                            )}
                                                        >
                                                            {step.label}
                                                        </p>
                                                        <p className="text-sm leading-6 text-muted-foreground">
                                                            {step.description}
                                                        </p>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                <div className="rounded-[2rem] border border-black/6 bg-white/88 p-5 backdrop-blur dark:border-white/10 dark:bg-white/5">
                                    <div className="flex items-start gap-3">
                                        <div className="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                            <LifeBuoyIcon className="size-5" />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="font-semibold">Need help during setup?</p>
                                            <p className="text-sm leading-6 text-muted-foreground">
                                                Open a support ticket and we will help you finish the
                                                launch without restarting the flow.
                                            </p>
                                            <Button asChild variant="outline">
                                                <Link href={supportHref}>Contact Support</Link>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </aside>

                        <section className="min-w-0 space-y-6">
                            <div className="space-y-4 rounded-[2rem] border border-black/6 bg-white/82 p-5 shadow-[0_16px_60px_rgba(33,30,22,0.06)] backdrop-blur dark:border-white/10 dark:bg-white/5 dark:shadow-none xl:hidden">
                                <div className="flex items-center justify-between gap-4">
                                    <div>
                                        <p className="text-xs font-semibold tracking-[0.24em] text-muted-foreground uppercase">
                                            Agency Setup
                                        </p>
                                        <p className="mt-1 text-sm font-semibold">
                                            Step {currentStepIndex + 1} of {steps.length}
                                        </p>
                                    </div>

                                    <Badge variant="outline" className="rounded-full px-3 py-1">
                                        {steps[currentStepIndex]?.label ?? title}
                                    </Badge>
                                </div>

                                <Progress
                                    value={((currentStepIndex + 1) / steps.length) * 100}
                                    className="h-1.5"
                                />

                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    {steps.map((step, index) => (
                                        <div key={step.key} className="space-y-1">
                                            <p
                                                className={cn(
                                                    'text-xs font-semibold uppercase',
                                                    index <= currentStepIndex
                                                        ? 'text-foreground'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {index + 1}. {step.label}
                                            </p>
                                            <p className="text-xs leading-5 text-muted-foreground">
                                                {step.description}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="space-y-3">
                                <Badge variant="outline" className="rounded-full px-3 py-1">
                                    <RocketIcon className="size-3.5" />
                                    Website Launch Flow
                                </Badge>
                                <div className="space-y-2">
                                    <h1 className="text-4xl font-semibold tracking-[-0.04em] text-balance">
                                        {title}
                                    </h1>
                                    <p className="max-w-3xl text-base leading-7 text-muted-foreground">
                                        {description}
                                    </p>
                                </div>
                            </div>

                            {children}
                        </section>
                    </div>
                </main>
            </div>
        </>
    );
}
