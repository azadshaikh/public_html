import {
    BotIcon,
    BracesIcon,
    DownloadIcon,
    MapPinnedIcon,
    ScanSearchIcon,
    Share2Icon,
    TagsIcon,
} from 'lucide-react';
import type { BreadcrumbItem, SettingsNavItem } from '@/types';

export function getSeoSettingsNav(): SettingsNavItem[] {
    return [
        {
            slug: 'titlesmeta',
            label: 'Titles & Meta',
            href: route('seo.settings.titlesmeta'),
            icon: TagsIcon,
        },
        {
            slug: 'localseo',
            label: 'Local SEO',
            href: route('seo.settings.localseo'),
            icon: MapPinnedIcon,
        },
        {
            slug: 'socialmedia',
            label: 'Social Media',
            href: route('seo.settings.socialmedia'),
            icon: Share2Icon,
        },
        {
            slug: 'schema',
            label: 'Schema',
            href: route('seo.settings.schema'),
            icon: BracesIcon,
        },
        {
            slug: 'sitemap',
            label: 'Sitemap',
            href: route('seo.settings.sitemap'),
            icon: ScanSearchIcon,
        },
        {
            slug: 'robots',
            label: 'Robots.txt',
            href: route('seo.settings.robots'),
            icon: BotIcon,
        },
        {
            slug: 'importexport',
            label: 'Import & Export',
            href: route('seo.settings.importexport'),
            icon: DownloadIcon,
        },
    ];
}

export function getSeoSettingsBreadcrumbs(title: string): BreadcrumbItem[] {
    return [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'SEO', href: route('seo.dashboard') },
        { title, href: '#' },
    ];
}
