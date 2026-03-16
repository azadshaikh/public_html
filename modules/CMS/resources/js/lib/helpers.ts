import {
    CheckCircleIcon,
    HourglassIcon,
    ClockIcon,
    GlobeIcon,
    CircleIcon,
    FileIcon,
    LayoutTemplateIcon,
    ListIcon,
    MenuIcon,
    PauseCircleIcon,
    Trash2Icon,
} from 'lucide-react';
import React from 'react';
import type {
    DatagridFilter,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import type { StatusTabConfig } from '../types/cms';

const COLOR_MAP: Record<string, DatagridTab['countVariant']> = {
    primary: 'secondary',
    success: 'success',
    warning: 'warning',
    info: 'info',
    danger: 'danger',
    secondary: 'secondary',
};

const REMIX_TO_LUCIDE: Record<string, React.FC<any>> = {
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

/**
 * Map a scaffold StatusTabConfig to a Datagrid tab definition.
 */
export function mapStatusTab(
    tab: StatusTabConfig,
    statistics: Record<string, number>,
    currentStatus: string,
): DatagridTab {
    const value = tab.value ?? tab.key;
    const count = value === 'all' ? undefined : (statistics[value] ?? 0);
    const IconComponent = tab.icon ? REMIX_TO_LUCIDE[tab.icon] : undefined;

    return {
        label: tab.label,
        value,
        count,
        icon: IconComponent
            ? React.createElement(IconComponent, { className: 'size-4' })
            : undefined,
        active: currentStatus === value,
        countVariant: COLOR_MAP[tab.color ?? 'secondary'] ?? 'secondary',
    };
}

export function mapFilters(
    configFilters: Record<string, any>[],
    activeFilters: Record<string, any>,
    searchPlaceholder: string = 'Search...',
): DatagridFilter[] {
    const filters: DatagridFilter[] = [];

    filters.push({
        type: 'search',
        name: 'search',
        value: activeFilters.search ?? '',
        placeholder: searchPlaceholder,
        className: 'lg:min-w-80',
    });

    if (!configFilters || !Array.isArray(configFilters)) {
        return filters;
    }

    for (const f of configFilters) {
        if (f.type === 'select') {
            filters.push({
                type: 'select',
                name: f.key,
                value: activeFilters[f.key] ?? '',
                options: f.options ?? [],
                multiple: f.multiple,
            });
        } else if (f.type === 'date_range') {
            filters.push({
                type: 'date_range',
                name: f.key,
                label: f.label,
                value: activeFilters[f.key] ?? '',
            });
        } else if (f.type === 'boolean') {
            filters.push({
                type: 'boolean',
                name: f.key,
                label: f.label,
                value: activeFilters[f.key] ?? '',
                trueLabel: f.options?.['1'] ?? 'Yes',
                falseLabel: f.options?.['0'] ?? 'No',
            });
        } else if (f.type === 'number') {
            filters.push({
                type: 'number',
                name: f.key,
                label: f.label,
                value: activeFilters[f.key] ?? '',
                placeholder: f.placeholder,
            });
        }
    }

    return filters;
}
