import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavigationBadge = {
    value: string | null;
    color: string;
};

export type NavigationItemAttributeValue = boolean | number | string | null;

export type NavigationItem = {
    key: string;
    label: string;
    url: string;
    icon?: string | null;
    active: boolean;
    active_patterns: Array<string | Record<string, unknown>>;
    badge?: NavigationBadge | null;
    target?: string | null;
    hard_reload?: boolean;
    default_open?: boolean;
    attributes?: Record<string, NavigationItemAttributeValue>;
    children: NavigationItem[];
    hasChildren: boolean;
};

export type NavigationSection = {
    key: string;
    label: string;
    weight: number;
    type: 'app' | 'module';
    area: 'top' | 'cms' | 'modules' | 'bottom';
    show_label: boolean;
    items: NavigationItem[];
};

export type NavigationByArea = {
    top: NavigationSection[];
    cms: NavigationSection[];
    modules: NavigationSection[];
    bottom: NavigationSection[];
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    component?: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
};
