import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
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

    // Inertia url is relative (e.g., /admin/settings/general), but href might be absolute.
    const pathname = url.split('?')[0];
    const activeSlug =
        settingsNav.find((item) => {
            const itemPath = item.href.replace(/^https?:\/\/[^/]+/, '');
            return pathname === itemPath || pathname.startsWith(`${itemPath}/`);
        })?.slug ?? settingsNav[0]?.slug;

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={title}
            description={description}
        >
            <Tabs
                value={activeSlug}
                orientation="vertical"
                className="flex w-full flex-col gap-6 md:flex-row md:items-start md:gap-8"
            >
                <TabsList className="w-full shrink-0 p-1.5 md:w-60 lg:w-72">
                    {settingsNav.map((item) => (
                        <TabsTrigger
                            key={item.slug}
                            value={item.slug}
                            asChild
                            className="px-4 py-2.5 text-[15px] leading-6 md:px-4 md:py-3"
                        >
                            <Link href={item.href} preserveScroll>
                                {item.label}
                            </Link>
                        </TabsTrigger>
                    ))}
                </TabsList>

                <div className="w-full min-w-0 flex-1">{children}</div>
            </Tabs>
        </AppLayout>
    );
}
