import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { SharedData } from '@/types';

export function AppSidebarBranding() {
    const { appName, branding, modules } = usePage<SharedData>().props;
    const iconUrl = branding.icon.trim();
    const isCmsEnabled = modules.items.some((m) => m.slug === 'cms');

    const content = (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                {iconUrl !== '' ? (
                    <img
                        src={iconUrl}
                        alt={`${branding.name.trim() || appName} icon`}
                        className="size-full object-cover"
                    />
                ) : (
                    <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                )}
            </div>
            <div className="grid min-w-0 flex-1 text-left text-sm">
                <span className="truncate leading-tight font-semibold">
                    {appName}
                </span>
            </div>
        </>
    );

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton asChild size="lg" tooltip={appName}>
                    {isCmsEnabled ? (
                        <a href="/" target="_blank" rel="noreferrer">
                            {content}
                        </a>
                    ) : (
                        <Link href={route('dashboard')} prefetch>
                            {content}
                        </Link>
                    )}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
