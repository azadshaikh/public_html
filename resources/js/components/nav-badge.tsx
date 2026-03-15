import type { ComponentProps } from 'react';
import { Badge } from '@/components/ui/badge';
import type { NavigationBadge } from '@/types';

type BadgeVariant = ComponentProps<typeof Badge>['variant'];

type BadgeColorConfig = {
    variant: BadgeVariant;
    className?: string;
};

const colorMap: Record<string, BadgeColorConfig> = {
    success: {
        variant: 'success',
    },
    primary: { variant: 'default' },
    warning: {
        variant: 'outline',
        className:
            'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-400',
    },
    danger: { variant: 'destructive' },
    info: {
        variant: 'outline',
        className:
            'border-cyan-300 bg-cyan-50 text-cyan-700 dark:border-cyan-700 dark:bg-cyan-950/50 dark:text-cyan-400',
    },
    secondary: { variant: 'secondary' },
};

export function NavBadge({ badge }: { badge: NavigationBadge }) {
    if (!badge.value) {
        return null;
    }

    const config = colorMap[badge.color] ?? colorMap.secondary;

    return (
        <Badge
            variant={config.variant}
            className={`ml-auto group-data-[collapsible=icon]:hidden${config.className ? ` ${config.className}` : ''}`}
        >
            {badge.value}
        </Badge>
    );
}
