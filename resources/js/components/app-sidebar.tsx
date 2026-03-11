'use client';

import { usePage } from '@inertiajs/react';
import {
    GalleryVerticalEndIcon,
    AudioLinesIcon,
    TerminalIcon,
    TerminalSquareIcon,
    BotIcon,
    ClapperboardIcon,
    PackageIcon,
    CheckSquareIcon,
    FileTextIcon,
    PlusIcon,
    ShieldCheckIcon,
} from 'lucide-react';
import * as React from 'react';
import MovieController from '@/actions/App/Http/Controllers/Demo/MovieController';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarRail,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes/index';
import type { AuthenticatedSharedData } from '@/types';

const moduleCrudItems = {
    cms: [
        { title: 'All pages', suffix: '' },
        { title: 'New page', suffix: '/create' },
    ],
    chatbot: [
        { title: 'Prompt templates', suffix: '' },
        { title: 'New prompt', suffix: '/create' },
    ],
    todos: [
        { title: 'All tasks', suffix: '' },
        { title: 'New task', suffix: '/create' },
    ],
} as const;

const moduleIcons = {
    cms: <FileTextIcon />,
    chatbot: <BotIcon />,
    todos: <CheckSquareIcon />,
} as const;

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
    const { auth } = page.props;
    const currentComponent = page.component;
    const currentUrl = page.url;
    const canManageModules = auth.abilities.manageModules;
    const canViewRoles = auth.abilities.viewRoles;
    const canAddRoles = auth.abilities.addRoles;
    const canViewUsers = auth.abilities.viewUsers;
    const isDashboardPage = currentComponent === 'dashboard';
    const isMoviesDemoPage = currentComponent.startsWith('demo/movies/');
    const isModulesPage =
        currentComponent.startsWith('modules/') ||
        currentUrl.startsWith('/modules');
    const isRolesPage =
        currentComponent.startsWith('roles/') ||
        currentUrl.startsWith('/roles');
    const isUsersPage =
        currentComponent.startsWith('users/') ||
        currentUrl.startsWith('/users');
    const moduleItems = page.props.modules?.items ?? [];

    const moduleNavItems = moduleItems.map((module) => {
        const slug = module.slug as keyof typeof moduleCrudItems;
        const children = (
            moduleCrudItems[slug] ?? [{ title: 'Overview', suffix: '' }]
        ).map((item) => ({
            title: item.title,
            url: `${module.url}${item.suffix}`,
            icon: item.suffix === '/create' ? <PlusIcon /> : undefined,
            isActive: currentUrl === `${module.url}${item.suffix}`,
        }));

        return {
            title: module.name,
            icon: moduleIcons[slug] ?? <PackageIcon />,
            isActive:
                currentUrl.startsWith(module.url) ||
                currentComponent.startsWith(module.inertiaNamespace),
            items: children,
        };
    });

    const navMain = [
        {
            title: 'Dashboard',
            url: dashboard().url,
            component: 'dashboard',
            icon: <TerminalSquareIcon />,
            isActive: isDashboardPage,
        },
        ...(canManageModules
            ? [
                  {
                      title: 'Modules',
                      url: '/modules',
                      icon: <PackageIcon />,
                      isActive: isModulesPage,
                  },
              ]
            : []),
        ...(canViewRoles
            ? [
                  {
                      title: 'Access control',
                      icon: <ShieldCheckIcon />,
                      isActive: isRolesPage,
                      items: [
                          {
                              title: 'Roles',
                              url: RoleController.index().url,
                              isActive:
                                  currentUrl === RoleController.index().url,
                          },
                          ...(canViewUsers
                              ? [
                                    {
                                        title: 'Users',
                                        url: '/users',
                                        isActive:
                                            currentUrl === '/users' ||
                                            isUsersPage,
                                    },
                                ]
                              : []),
                          ...(canAddRoles
                              ? [
                                    {
                                        title: 'New role',
                                        url: RoleController.create().url,
                                        icon: <PlusIcon />,
                                        isActive:
                                            currentUrl ===
                                            RoleController.create().url,
                                    },
                                ]
                              : []),
                      ],
                  },
              ]
            : []),
        ...moduleNavItems,
        {
            title: 'Movies demo',
            icon: <ClapperboardIcon />,
            isActive: isMoviesDemoPage,
            items: [
                {
                    title: 'Browse movies',
                    url: MovieController.index().url,
                    isActive: currentUrl === MovieController.index().url,
                },
                {
                    title: 'Create movie',
                    url: MovieController.create().url,
                    icon: <PlusIcon />,
                    isActive: currentUrl === MovieController.create().url,
                },
            ],
        },
    ];

    return (
        <Sidebar collapsible="icon" {...props}>
            <SidebarHeader>
                <TeamSwitcher teams={teams} />
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={navMain} />
            </SidebarContent>
            <SidebarFooter>
                <NavUser user={auth.user} />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
