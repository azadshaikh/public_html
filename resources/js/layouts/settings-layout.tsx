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
        <AppLayout breadcrumbs={breadcrumbs} title={title} description={description}>
            <Tabs value={activeSlug} orientation="vertical" className="flex w-full flex-col md:flex-row md:items-start gap-6">
                <TabsList className="w-full shrink-0 md:w-48 lg:w-56">
                    {settingsNav.map((item) => (
                        <TabsTrigger key={item.slug} value={item.slug} asChild>
                            <Link href={item.href} preserveScroll>
                                {item.label}
                            </Link>
                        </TabsTrigger>
                    ))}
                </TabsList>

                <div className="min-w-0 flex-1 w-full">{children}</div>
            </Tabs>
        </AppLayout>
    );
}
