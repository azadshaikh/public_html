import * as React from 'react';
import AppHead from '@/components/app-head';
import { AppPageHeader } from '@/components/app-page-header';
import { AppTopbar } from '@/components/app-topbar';
import { SidebarInset } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { AppVariant } from '@/types';

type Props = React.ComponentProps<'main'> & {
    variant?: AppVariant;
    breadcrumbs?: BreadcrumbItem[];
    title?: string;
    description?: string;
    headerActions?: React.ReactNode;
    contentClassName?: string;
};

export function AppContent({
    variant = 'sidebar',
    breadcrumbs = [],
    title,
    description,
    headerActions,
    contentClassName,
    children,
    className,
    ...props
}: Props) {
    const pageHeader = (
        <AppPageHeader
            breadcrumbs={breadcrumbs}
            title={title}
            description={description}
            actions={headerActions}
        />
    );

    if (variant === 'sidebar') {
        return (
            <SidebarInset
                className={cn('h-svh overflow-hidden bg-muted/20', className)}
                {...props}
            >
                <AppHead title={title} description={description} />
                <AppTopbar />

                <div className="flex-1 overflow-y-scroll [scrollbar-gutter:stable]">
                    <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8 px-4 py-6 sm:px-6 lg:px-8">
                        {pageHeader}

                        <div
                            className={cn(
                                'flex flex-1 flex-col gap-6',
                                contentClassName,
                            )}
                        >
                            {children}
                        </div>
                    </div>
                </div>
            </SidebarInset>
        );
    }

    return (
        <main
            className={cn(
                'mx-auto flex h-svh w-full max-w-7xl flex-1 flex-col gap-8 overflow-y-scroll px-4 py-6 [scrollbar-gutter:stable] sm:px-6 lg:px-8',
                className,
            )}
            {...props}
        >
            <AppHead title={title} description={description} />
            {pageHeader}

            <div className={cn('flex flex-1 flex-col gap-6', contentClassName)}>
                {children}
            </div>
        </main>
    );
}
