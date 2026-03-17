import {
    CheckCircleIcon,
    CircleIcon,
    ClockIcon,
    FileIcon,
    GlobeIcon,
    HourglassIcon,
    LayoutTemplateIcon,
    ListIcon,
    MenuIcon,
    PauseCircleIcon,
    Trash2Icon,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    buildScaffoldDatagridState,
    mapScaffoldFilters,
    mapScaffoldStatusTab,
} from '@/lib/scaffold-datagrid';
import type {
    ScaffoldFilterConfig,
    ScaffoldFilterState,
    ScaffoldStatusTabConfig,
} from '@/types/scaffold';

const CMS_ICON_MAP: Record<string, LucideIcon> = {
    'ri-list-check': ListIcon,
    'ri-checkbox-circle-line': CheckCircleIcon,
    'ri-file-line': FileIcon,
    'ri-delete-bin-line': Trash2Icon,
    'ri-pause-circle-line': PauseCircleIcon,
    'ri-checkbox-blank-circle-line': CircleIcon,
    'ri-layout-3-line': LayoutTemplateIcon,
    'ri-menu-line': MenuIcon,
    'ri-global-line': GlobeIcon,
    'ri-time-line': ClockIcon,
    'ri-hourglass-line': HourglassIcon,
};

export function mapStatusTab(
    tab: ScaffoldStatusTabConfig,
    statistics: Record<string, number>,
    currentStatus: string,
) {
    return mapScaffoldStatusTab(tab, statistics, currentStatus, CMS_ICON_MAP);
}

export function mapFilters(
    configFilters: ScaffoldFilterConfig[],
    activeFilters: ScaffoldFilterState,
    searchPlaceholder = 'Search...',
) {
    return mapScaffoldFilters(configFilters, activeFilters, {
        searchPlaceholder,
    });
}

export function buildDatagridState(
    config: {
        filters: ScaffoldFilterConfig[];
        statusTabs: ScaffoldStatusTabConfig[];
        settings: {
            perPage: number;
            defaultSort: string | null;
            defaultDirection: 'asc' | 'desc';
        };
    },
    filters: ScaffoldFilterState,
    statistics: Record<string, number>,
    searchPlaceholder: string,
) {
    return buildScaffoldDatagridState(config, filters, statistics, {
        searchPlaceholder,
        iconMap: CMS_ICON_MAP,
    });
}
