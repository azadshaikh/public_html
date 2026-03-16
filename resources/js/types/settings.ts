import type { LucideIcon } from 'lucide-react';

export type SettingsNavItem = {
    slug: string;
    label: string;
    href: string;
    icon?: LucideIcon;
};

export type SelectOption = {
    value: string;
    label: string;
};
