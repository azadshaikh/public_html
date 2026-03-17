import {
    CircleIcon,
    ExternalLinkIcon,
    GlobeIcon,
    HardDriveIcon,
    ServerCogIcon,
    ShieldAlertIcon,
    Trash2Icon,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    buildScaffoldBulkActions,
    buildScaffoldDatagridState,
    mapScaffoldFilters,
    mapScaffoldRowActions,
    mapScaffoldStatusTab,
} from '@/lib/scaffold-datagrid';
import type {
    ScaffoldActionConfig,
    ScaffoldFilterConfig,
    ScaffoldFilterState,
    ScaffoldRowActionPayload,
    ScaffoldStatusTabConfig,
} from '@/types/scaffold';

const PLATFORM_ICON_MAP: Record<string, LucideIcon> = {
    'ri-list-check': CircleIcon,
    'ri-delete-bin-line': Trash2Icon,
    'ri-error-warning-line': ShieldAlertIcon,
    'ri-global-line': GlobeIcon,
    'ri-server-line': HardDriveIcon,
    'ri-tools-line': ServerCogIcon,
    'ri-eye-line': ExternalLinkIcon,
};

export function mapStatusTab(
    tab: ScaffoldStatusTabConfig,
    statistics: Record<string, number>,
    currentStatus: string,
) {
    return mapScaffoldStatusTab(tab, statistics, currentStatus, PLATFORM_ICON_MAP);
}

export function mapFilters(
    configFilters: ScaffoldFilterConfig[],
    activeFilters: ScaffoldFilterState,
    searchPlaceholder = 'Search...',
) {
    return mapScaffoldFilters(configFilters, activeFilters, { searchPlaceholder });
}

export function mapRowActions(
    actions: Record<string, ScaffoldRowActionPayload> | ScaffoldRowActionPayload[] | undefined,
) {
    return mapScaffoldRowActions(actions, PLATFORM_ICON_MAP);
}

export function buildBulkActions<T extends { id: number | string }>(
    actions: ScaffoldActionConfig[] | undefined,
    routePrefix: string,
    currentStatus: string,
) {
    return buildScaffoldBulkActions<T>(actions, {
        routePrefix,
        currentStatus,
        iconMap: PLATFORM_ICON_MAP,
    });
}

export function buildDatagridState(
    config: {
        filters: ScaffoldFilterConfig[];
        statusTabs: ScaffoldStatusTabConfig[];
        settings: { perPage: number; defaultSort: string | null; defaultDirection: 'asc' | 'desc' };
    },
    filters: ScaffoldFilterState,
    statistics: Record<string, number>,
    searchPlaceholder: string,
) {
    return buildScaffoldDatagridState(config, filters, statistics, {
        searchPlaceholder,
        perPageOptions: [15, 25, 50, 100],
        iconMap: PLATFORM_ICON_MAP,
    });
}
