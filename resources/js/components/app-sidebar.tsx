'use client';

import { usePage } from '@inertiajs/react';
import * as React from 'react';
import { AppSidebarBranding } from '@/components/app-sidebar-branding';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarRail,
} from '@/components/ui/sidebar';
import type { AuthenticatedSharedData } from '@/types';

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
    const page = usePage<AuthenticatedSharedData>();
    const { auth, navigation } = page.props;

    const contentSections = [
        ...navigation.top,
        ...navigation.cms,
        ...navigation.modules,
        ...navigation.bottom,
    ];

    return (
        <Sidebar collapsible="icon" {...props}>
            <SidebarHeader>
                <AppSidebarBranding />
            </SidebarHeader>
            <SidebarContent>
                <NavMain sections={contentSections} />
            </SidebarContent>
            <SidebarFooter>
                <NavUser user={auth.user} />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
