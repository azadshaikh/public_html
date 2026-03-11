import { AppContent } from '@/components/app-content';
import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
import type { AppLayoutProps } from '@/types';

export default function AppHeaderLayout({
    children,
    breadcrumbs,
    title,
    description,
    headerActions,
    contentClassName,
}: AppLayoutProps) {
    return (
        <AppShell variant="header">
            <AppHeader breadcrumbs={breadcrumbs} />
            <AppContent
                variant="header"
                breadcrumbs={breadcrumbs}
                title={title}
                description={description}
                headerActions={headerActions}
                contentClassName={contentClassName}
            >
                {children}
            </AppContent>
        </AppShell>
    );
}
