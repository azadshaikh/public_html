import { usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';

export function AppFooter() {
    const { appName, appVersion, branding } = usePage<SharedData>().props;
    const brandName = branding.name.trim() || appName;
    const brandWebsite = branding.website.trim();
    const currentYear = new Date().getFullYear();

    return (
        <footer className="border-t border-sidebar-border/60 bg-background/95">
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-2 px-4 py-3 text-sm text-muted-foreground sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span>{`© ${currentYear} ${brandName}.`}</span>
                    <span className="hidden text-sidebar-border lg:inline">|</span>
                    <span>Powered by</span>
                    {brandWebsite !== '' ? (
                        <a
                            href={brandWebsite}
                            target="_blank"
                            rel="noreferrer noopener"
                            className="font-semibold text-foreground transition-colors hover:text-foreground/80"
                        >
                            {brandName}
                        </a>
                    ) : (
                        <span className="font-semibold text-foreground">
                            {brandName}
                        </span>
                    )}
                </div>

                <div className="text-sm text-muted-foreground">
                    Version {appVersion}
                </div>
            </div>
        </footer>
    );
}
