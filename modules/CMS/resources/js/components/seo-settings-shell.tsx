import type { ReactNode } from 'react';
import SettingsLayout from '@/layouts/settings-layout';
import type { BreadcrumbItem } from '@/types';

type SeoSettingsShellProps = {
    children: ReactNode;
    breadcrumbs: BreadcrumbItem[];
    title: string;
    description?: string;
};

export default function SeoSettingsShell({
    children,
    breadcrumbs,
    title,
    description,
}: SeoSettingsShellProps) {
    return (
        <SettingsLayout
            settingsNav={[]}
            breadcrumbs={breadcrumbs}
            title={title}
            description={description}
            showRail={false}
        >
            {children}
        </SettingsLayout>
    );
}
