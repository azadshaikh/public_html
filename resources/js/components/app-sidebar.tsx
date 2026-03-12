'use client';

import { usePage } from '@inertiajs/react';
import {
    AudioLinesIcon,
    GalleryVerticalEndIcon,
    TerminalIcon,
} from 'lucide-react';
import * as React from 'react';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarRail,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import type { AuthenticatedSharedData } from '@/types';

const teams = [
    {
        name: 'Acme Inc',
        logo: <GalleryVerticalEndIcon />,
        plan: 'Enterprise',
    },
    {
        name: 'Acme Corp.',
        logo: <AudioLinesIcon />,
        plan: 'Startup',
    },
    {
        name: 'Evil Corp.',
        logo: <TerminalIcon />,
        plan: 'Free',
    },
];

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
                <TeamSwitcher teams={teams} />
            </SidebarHeader>
            <SidebarContent>
                <NavMain sections={contentSections} />
            </SidebarContent>
            <SidebarFooter>
                <SidebarSeparator />
                <NavUser user={auth.user} />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
