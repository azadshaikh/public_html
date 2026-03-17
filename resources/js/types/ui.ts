export type BadgeVariant =
    | 'default'
    | 'secondary'
    | 'success'
    | 'warning'
    | 'info'
    | 'danger'
    | 'destructive'
    | 'outline'
    | 'ghost'
    | 'link';
import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    title?: string;
    description?: string;
    headerActions?: ReactNode;
    contentClassName?: string;
};

export type AppVariant = 'header' | 'sidebar';

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
    maxWidthClassName?: string;
};
