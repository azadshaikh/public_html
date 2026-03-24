import { Link, usePage } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import AppHead from '@/components/app-head';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';

type AgencyOnboardingMinimalLayoutProps = {
    children: ReactNode;
    title: string;
    description: string;
    backHref?: string;
    backLabel?: string;
    contentWidthClassName?: string;
    hideHeading?: boolean;
};

export default function AgencyOnboardingMinimalLayout({
    children,
    title,
    description,
    backHref,
    backLabel = 'Back',
    contentWidthClassName = 'max-w-3xl',
    hideHeading = false,
}: AgencyOnboardingMinimalLayoutProps) {
    const { appName, branding } = usePage<SharedData>().props;
    const iconUrl = branding?.icon?.trim() || '';

    return (
        <>
            <AppHead title={title} description={description} />

            <div className="min-h-svh bg-background text-foreground">
                <header className="px-6 py-5 sm:px-8 lg:px-10">
                    <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4">
                        <div className="flex min-w-0 items-center gap-3">
                            <div className="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                {iconUrl !== '' ? (
                                    <img src={iconUrl} alt={`${appName} icon`} className="size-full object-cover" />
                                ) : (
                                    <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                                )}
                            </div>

                            <p className="truncate text-sm font-semibold leading-tight">{appName}</p>
                        </div>

                        {backHref ? (
                            <Button asChild variant="outline">
                                <Link href={backHref}>
                                    <ArrowLeftIcon className="size-4" />
                                    {backLabel}
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                </header>

                <main className="px-6 pb-12 pt-6 sm:px-8 lg:px-10 lg:pt-10">
                    <div className={cn('mx-auto w-full', contentWidthClassName)}>
                        <div className={cn('space-y-10', hideHeading && 'space-y-0')}>
                            {hideHeading ? null : (
                                <div className="space-y-2.5 text-center">
                                    <h1 className="text-3xl font-medium tracking-[-0.035em] text-balance sm:text-4xl">
                                        {title}
                                    </h1>
                                    <p className="text-base font-normal text-muted-foreground">
                                        {description}
                                    </p>
                                </div>
                            )}

                            {children}
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}