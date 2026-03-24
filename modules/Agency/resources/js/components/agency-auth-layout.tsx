import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppHead from '@/components/app-head';
import { AppThemeToggle } from '@/components/app-theme-toggle';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';

type AgencyAuthLayoutProps = {
    children: ReactNode;
    title: string;
    description: string;
    eyebrow: string;
    headline: string;
    supportingText: string;
    bottomPrompt: ReactNode;
};

const featureItems = [
    {
        title: 'Visual drag-and-drop builder',
        description:
            'Design polished pages fast without leaving the browser.',
    },
    {
        title: 'Custom domain and SSL',
        description:
            'Launch on your own domain with secure HTTPS already handled.',
    },
    {
        title: 'Managed hosting included',
        description:
            'Provisioning, updates, and infrastructure stay off your plate.',
    },
    {
        title: 'SEO and analytics ready',
        description:
            'Ship a site that can be found, measured, and improved.',
    },
];

export default function AgencyAuthLayout({
    children,
    title,
    description,
    eyebrow,
    headline,
    supportingText,
    bottomPrompt,
}: AgencyAuthLayoutProps) {
    const { appName, branding } = usePage<SharedData>().props;
    const brandName = branding?.name?.trim() || appName;
    const logoUrl = branding?.logo?.trim() || branding?.icon?.trim() || '';

    return (
        <>
            <AppHead title={title} description={description} />

            <div className="relative min-h-svh bg-[linear-gradient(180deg,#f5efe3_0%,#fffdf8_30%,#ffffff_100%)] text-foreground dark:bg-[linear-gradient(180deg,#0f1112_0%,#111416_30%,#151819_100%)]">
                <div className="absolute top-6 right-6 z-20">
                    <AppThemeToggle />
                </div>

                <div className="grid min-h-svh lg:grid-cols-[1.15fr_0.85fr]">
                    <section className="relative hidden overflow-hidden border-r border-black/5 bg-[radial-gradient(circle_at_top_left,#f9d784_0%,rgba(249,215,132,0.32)_22%,transparent_45%),linear-gradient(160deg,#121212_0%,#191919_55%,#232323_100%)] px-10 py-12 text-white lg:flex lg:flex-col">
                        <div className="absolute inset-0 bg-[linear-gradient(135deg,transparent_0%,rgba(255,255,255,0.05)_35%,transparent_70%)]" />

                        <div className="relative flex items-center gap-3">
                            <div className="flex size-11 items-center justify-center overflow-hidden rounded-2xl border border-white/15 bg-white/10 shadow-[0_18px_60px_rgba(0,0,0,0.25)] backdrop-blur">
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
                            <div>
                                <p className="text-sm font-semibold tracking-[0.24em] text-white/55 uppercase">
                                    {eyebrow}
                                </p>
                                <p className="text-base font-medium text-white/90">
                                    {brandName}
                                </p>
                            </div>
                        </div>

                        <div className="relative mt-auto mb-auto max-w-xl space-y-8">
                            <div className="space-y-4">
                                <h1 className="max-w-lg text-5xl leading-[1.05] font-semibold tracking-[-0.04em] text-balance">
                                    {headline}
                                </h1>
                                <p className="max-w-xl text-base leading-7 text-white/68">
                                    {supportingText}
                                </p>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                {featureItems.map((feature) => (
                                    <div
                                        key={feature.title}
                                        className="rounded-3xl border border-white/10 bg-white/6 p-5 backdrop-blur-sm"
                                    >
                                        <div className="mb-4 size-3 rounded-full bg-[#f9d784]" />
                                        <h2 className="text-base font-semibold text-white">
                                            {feature.title}
                                        </h2>
                                        <p className="mt-2 text-sm leading-6 text-white/62">
                                            {feature.description}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <p className="relative text-sm text-white/40">
                            Build, launch, and support customer sites from one place.
                        </p>
                    </section>

                    <section className="flex items-center justify-center px-6 py-16 sm:px-10 lg:px-14">
                        <div className="w-full max-w-md space-y-8">
                            <div className="space-y-4 text-center">
                                <Link
                                    href="/"
                                    className="mx-auto flex w-fit flex-col items-center gap-3"
                                >
                                    <div className="flex size-14 items-center justify-center overflow-hidden rounded-[1.25rem] border border-black/8 bg-white shadow-[0_18px_60px_rgba(19,18,14,0.08)] dark:border-white/10 dark:bg-white/5 dark:shadow-none">
                                        {logoUrl !== '' ? (
                                            <img
                                                src={logoUrl}
                                                alt={brandName}
                                                className="size-full object-cover"
                                            />
                                        ) : (
                                            <span className="text-xl font-semibold">
                                                {brandName.charAt(0)}
                                            </span>
                                        )}
                                    </div>
                                </Link>
                                <div className="space-y-2">
                                    <h2 className="text-3xl font-semibold tracking-[-0.03em] text-balance">
                                        {title}
                                    </h2>
                                    <p className="text-sm leading-6 text-muted-foreground">
                                        {description}
                                    </p>
                                </div>
                            </div>

                            <div
                                className={cn(
                                    'rounded-[2rem] border border-black/6 bg-white/92 p-6 shadow-[0_20px_80px_rgba(33,30,22,0.09)] backdrop-blur',
                                    'dark:border-white/10 dark:bg-white/5 dark:shadow-none',
                                )}
                            >
                                {children}
                            </div>

                            <div className="text-center text-sm text-muted-foreground">
                                {bottomPrompt}
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}
