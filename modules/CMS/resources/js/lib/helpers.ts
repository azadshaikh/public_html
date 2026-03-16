import type { DatagridTab } from '@/components/datagrid/datagrid';
import type { StatusTabConfig } from '../types/cms';

const COLOR_MAP: Record<string, DatagridTab['countVariant']> = {
    primary: 'secondary',
    success: 'success',
    warning: 'warning',
    info: 'info',
    danger: 'danger',
    secondary: 'secondary',
};

/**
 * Map a scaffold StatusTabConfig to a Datagrid tab definition.
 */
export function mapStatusTab(
    tab: StatusTabConfig,
    statistics: Record<string, number>,
    currentStatus: string,
): DatagridTab {
    const value = tab.value ?? tab.key;
    const count = value === 'all' ? statistics.total : (statistics[value] ?? 0);

    return {
        label: tab.label,
        value,
        count,
        active: currentStatus === value,
        countVariant: COLOR_MAP[tab.color ?? 'secondary'] ?? 'secondary',
    };
}
