import {
    BarChart3Icon,
    Code2Icon,
    FingerprintIcon,
    MegaphoneIcon,
    ScanSearchIcon,
    SearchCheckIcon,
    TagsIcon,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { BreadcrumbItem } from '@/types';
import type { IntegrationSectionKey } from '../../../../types/cms';

export const integrationsBreadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'CMS', href: route('cms.posts.index', 'all') },
    { title: 'Integrations', href: route('cms.integrations.index') },
];

export type SectionMeta = {
    key: IntegrationSectionKey;
    title: string;
    description: string;
    category: string;
    icon: LucideIcon;
    emptyLabel: string;
};

export const integrationSections: SectionMeta[] = [
    {
        key: 'webmaster_tools',
        title: 'Webmaster verification',
        description:
            'Add verification tags from search engines and webmaster platforms.',
        category: 'Verification',
        icon: SearchCheckIcon,
        emptyLabel: 'Not set',
    },
    {
        key: 'google_analytics',
        title: 'Google Analytics',
        description:
            'Inject your Google Analytics tracking script into the site head.',
        category: 'Analytics',
        icon: BarChart3Icon,
        emptyLabel: 'Not set',
    },
    {
        key: 'google_tags',
        title: 'Google Tag Manager',
        description: 'Add your Google Tag Manager script and head snippet.',
        category: 'Analytics',
        icon: TagsIcon,
        emptyLabel: 'Not set',
    },
    {
        key: 'meta_pixel',
        title: 'Meta Pixel',
        description:
            'Configure Meta Pixel for ad conversion and campaign attribution.',
        category: 'Analytics',
        icon: FingerprintIcon,
        emptyLabel: 'Not set',
    },
    {
        key: 'microsoft_clarity',
        title: 'Microsoft Clarity',
        description:
            'Enable Microsoft Clarity session replay and heatmap tracking.',
        category: 'Analytics',
        icon: ScanSearchIcon,
        emptyLabel: 'Not set',
    },
    {
        key: 'google_adsense',
        title: 'Google AdSense',
        description:
            'Control Google AdSense scripts, ads.txt content, and display rules.',
        category: 'Advertising',
        icon: MegaphoneIcon,
        emptyLabel: 'Disabled',
    },
    {
        key: 'other',
        title: 'Custom head code',
        description: 'Add other script tags, meta tags, or custom head markup.',
        category: 'Custom code',
        icon: Code2Icon,
        emptyLabel: 'Not set',
    },
];
