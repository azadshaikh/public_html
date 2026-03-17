import {
    CheckCircleIcon,
    CircleIcon,
    ClockIcon,
    FileTextIcon,
    ListChecksIcon,
    RocketIcon,
    Trash2Icon,
    XCircleIcon,
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

const RELEASE_MANAGER_ICON_MAP: Record<string, LucideIcon> = {
    'ri-list-check': ListChecksIcon,
    'ri-checkbox-circle-line': CheckCircleIcon,
    'ri-file-line': FileTextIcon,
    'ri-delete-bin-line': Trash2Icon,
    'ri-checkbox-blank-circle-line': CircleIcon,
    'ri-time-line': ClockIcon,
    ListCheck: ListChecksIcon,
    Rocket: RocketIcon,
    FileText: FileTextIcon,
    XCircle: XCircleIcon,
    Trash2: Trash2Icon,
};

export function mapStatusTab(
    tab: ScaffoldStatusTabConfig,
    statistics: Record<string, number>,
    currentStatus: string,
) {
    return mapScaffoldStatusTab(
        tab,
        statistics,
        currentStatus,
        RELEASE_MANAGER_ICON_MAP,
    );
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
        iconMap: RELEASE_MANAGER_ICON_MAP,
    });
}

export function releaseRouteParams(
    type: string,
    params: Record<string, string | number | undefined> = {},
) {
    return {
        type,
        ...params,
    };
}
