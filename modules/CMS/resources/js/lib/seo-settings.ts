import type { BreadcrumbItem } from '@/types';

export function getSeoSettingsBreadcrumbs(title: string): BreadcrumbItem[] {
    return [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'SEO', href: route('seo.dashboard') },
        { title, href: '#' },
    ];
}
