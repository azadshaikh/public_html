import { Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    BadgeAlertIcon,
    BellRingIcon,
    BrushIcon,
    BugIcon,
    FolderKanbanIcon,
    GlobeIcon,
    HardDriveIcon,
    ImageIcon,
    LanguagesIcon,
    LockKeyholeIcon,
    LogInIcon,
    MailIcon,
    ShieldCheckIcon,
    SparklesIcon,
    WrenchIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, SettingsNavItem } from '@/types';

type SettingsLayoutProps = {
    children: ReactNode;
    settingsNav: SettingsNavItem[];
    breadcrumbs: BreadcrumbItem[];
    title?: string;
    description?: string;
};

const settingsIcons: Record<string, LucideIcon> = {
    app: FolderKanbanIcon,
    branding: BrushIcon,
    'coming-soon': SparklesIcon,
    debug: BugIcon,
    development: WrenchIcon,
    email: MailIcon,
    general: WrenchIcon,
    localization: LanguagesIcon,
    'login-security': LogInIcon,
    maintenance: BadgeAlertIcon,
    media: ImageIcon,
    registration: GlobeIcon,
    'site-access-protection': ShieldCheckIcon,
    'social-authentication': LockKeyholeIcon,
    storage: HardDriveIcon,
};

export default function SettingsLayout({
    children,
    settingsNav,
    breadcrumbs,
    title,
    description,
}: SettingsLayoutProps) {
    const { url } = usePage();
    const pathname = url.split('?')[0];
    const activeSlug =
        settingsNav.find((item) => {
            const itemPath = item.href.replace(/^https?:\/\/[^/]+/, '');
            return pathname === itemPath || pathname.startsWith(`${itemPath}/`);
        })?.slug ?? settingsNav[0]?.slug;

    const railLabel = pathname.includes('/master-settings/')
        ? 'Platform settings'
        : 'Application settings';

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={title}
            description={description}
        >
            <div className="flex w-full flex-col gap-6 lg:grid lg:grid-cols-[248px_minmax(0,1fr)] lg:items-start lg:gap-8 xl:grid-cols-[264px_minmax(0,1fr)]">
                <aside className="w-full lg:sticky lg:top-24">
                    <div className="rounded-xl border border-border/70 bg-muted/60 p-2.5">
                        <div className="px-2.5 pt-1.5 pb-2.5">
                            <p className="text-[10px] font-medium tracking-[0.12em] text-muted-foreground/70 uppercase">
                                {railLabel}
                            </p>
                        </div>

                        <nav className="grid gap-1" aria-label={railLabel}>
                            {settingsNav.map((item) => {
                                const Icon =
                                    settingsIcons[item.slug] ?? BellRingIcon;

                                return (
                                    <Button
                                        key={item.slug}
                                        size="sm"
                                        variant="ghost"
                                        asChild
                                        className={cn(
                                            'h-auto w-full min-w-0 rounded-[min(var(--radius-md),12px)] justify-start px-2.5 py-2 text-sm leading-5 text-foreground/70 hover:bg-background/80 hover:text-foreground',
                                            item.slug === activeSlug &&
                                                'bg-background text-foreground font-medium shadow-xs',
                                        )}
                                    >
                                        <Link href={item.href} preserveScroll>
                                            <Icon
                                                className={cn(
                                                    'size-4 text-foreground/65',
                                                    item.slug === activeSlug &&
                                                        'text-foreground',
                                                )}
                                            />
                                            <span className="min-w-0 truncate">
                                                {item.label}
                                            </span>
                                        </Link>
                                    </Button>
                                );
                            })}
                        </nav>
                    </div>
                </aside>

                <div className="w-full min-w-0 flex-1">{children}</div>
            </div>
        </AppLayout>
    );
}
