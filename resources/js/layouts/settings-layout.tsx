import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
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

export default function SettingsLayout({
    children,
    settingsNav,
    breadcrumbs,
    title,
    description,
}: SettingsLayoutProps) {
    const { url } = usePage();

    return (
        <AppLayout breadcrumbs={breadcrumbs} title={title} description={description}>
            <div className="flex flex-col gap-6 md:flex-row">
                <nav className="w-full shrink-0 md:w-48 lg:w-56">
                    <ul className="flex flex-row gap-1 overflow-x-auto md:flex-col md:overflow-visible">
                        {settingsNav.map((item) => {
                            const isActive = url.startsWith(item.href);

                            return (
                                <li key={item.slug}>
                                    <Link
                                        href={item.href}
                                        preserveScroll
                                        className={cn(
                                            'block whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                            isActive
                                                ? 'bg-accent text-accent-foreground'
                                                : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                                        )}
                                    >
                                        {item.label}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </nav>

                <div className="min-w-0 flex-1">{children}</div>
            </div>
        </AppLayout>
    );
}
