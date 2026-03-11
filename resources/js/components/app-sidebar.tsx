'use client';

import { usePage } from '@inertiajs/react';
import {
  GalleryVerticalEndIcon,
  AudioLinesIcon,
  TerminalIcon,
  TerminalSquareIcon,
  BotIcon,
  BookOpenIcon,
  BadgeCheckIcon,
  FrameIcon,
  PieChartIcon,
  MapIcon,
  ClapperboardIcon,
  PackageIcon,
  CheckSquareIcon,
  FileTextIcon,
  PlusIcon,
} from 'lucide-react';
import * as React from 'react';

import MovieController from '@/actions/App/Http/Controllers/Demo/MovieController';
import { NavMain } from '@/components/nav-main';
import { NavProjects } from '@/components/nav-projects';
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

// This is sample data.
const data = {
  teams: [
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
  ],
  navMain: [
    {
      title: 'Dashboard',
      url: dashboard().url,
      component: 'dashboard',
      icon: <TerminalSquareIcon />,
      isActive: true,
    },
    {
      title: 'Platform',
      url: '#',
      icon: <TerminalSquareIcon />,
      isActive: false,
      items: [
        {
          title: 'History',
          url: '#',
        },
        {
          title: 'Starred',
          url: '#',
        },
        {
          title: 'Account',
          url: '#',
        },
      ],
    },
    {
      title: 'Models',
      url: '#',
      icon: <BotIcon />,
      items: [
        {
          title: 'Genesis',
          url: '#',
        },
        {
          title: 'Explorer',
          url: '#',
        },
        {
          title: 'Quantum',
          url: '#',
        },
      ],
    },
    {
      title: 'Documentation',
      url: '#',
      icon: <BookOpenIcon />,
      items: [
        {
          title: 'Introduction',
          url: '#',
        },
        {
          title: 'Get Started',
          url: '#',
        },
        {
          title: 'Tutorials',
          url: '#',
        },
        {
          title: 'Changelog',
          url: '#',
        },
      ],
    },
    {
      title: 'Account',
      url: '#',
      icon: <BadgeCheckIcon />,
      items: [
        {
          title: 'General',
          url: '#',
        },
        {
          title: 'Team',
          url: '#',
        },
        {
          title: 'Billing',
          url: '#',
        },
        {
          title: 'Limits',
          url: '#',
        },
      ],
    },
  ],
  projects: [
    {
      name: 'Design Engineering',
      url: '#',
      icon: <FrameIcon />,
    },
    {
      name: 'Sales & Marketing',
      url: '#',
      icon: <PieChartIcon />,
    },
    {
      name: 'Travel',
      url: '#',
      icon: <MapIcon />,
    },
  ],
};

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const page = usePage<AuthenticatedSharedData>();
  const { auth } = page.props;
  const currentComponent = page.component;
  const currentUrl = page.url;
  const isDashboardPage = currentComponent === 'dashboard';
  const isMoviesDemoPage = currentComponent.startsWith('demo/movies/');
  const isModulesPage =
    currentComponent.startsWith('modules/') ||
    currentUrl.startsWith('/modules');
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
    {
      title: 'Manage modules',
      url: '/modules',
      icon: <PackageIcon />,
      isActive: isModulesPage,
    },
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
    ...data.navMain.slice(1),
  ];

  return (
    <Sidebar collapsible="icon" {...props}>
      <SidebarHeader>
        <TeamSwitcher teams={data.teams} />
      </SidebarHeader>
      <SidebarContent>
        <NavMain items={navMain} />
        <NavProjects projects={data.projects} />
      </SidebarContent>
      <SidebarFooter>
        <NavUser user={auth.user} />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  );
}
