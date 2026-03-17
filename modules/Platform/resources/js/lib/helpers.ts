import { router } from '@inertiajs/react';
import {
    CheckCircleIcon,
    CircleIcon,
    ClockIcon,
    ExternalLinkIcon,
    GlobeIcon,
    HardDriveIcon,
    HourglassIcon,
    PauseCircleIcon,
    RefreshCwIcon,
    ServerCogIcon,
    ShieldAlertIcon,
    Trash2Icon,
} from 'lucide-react';
import React from 'react';
import type {
    DatagridAction,
    DatagridBulkAction,
    DatagridFilter,
    DatagridFilterOption,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import type {
    PlatformActionConfig,
    PlatformActionPayload,
    PlatformFilterState,
    PlatformStatusTabConfig,
} from '../types/platform';

const COLOR_MAP: Record<string, DatagridTab['countVariant']> = {
    primary: 'secondary',
    success: 'success',
    warning: 'warning',
    info: 'info',
    danger: 'danger',
    destructive: 'destructive',
    secondary: 'secondary',
};

const REMIX_TO_LUCIDE: Record<string, React.FC<any>> = {
    'ri-list-check': CircleIcon,
    'ri-checkbox-circle-line': CheckCircleIcon,
    'ri-delete-bin-line': Trash2Icon,
    'ri-error-warning-line': ShieldAlertIcon,
    'ri-close-circle-line': CircleIcon,
    'ri-time-line': ClockIcon,
    'ri-hourglass-line': HourglassIcon,
    'ri-pause-circle-line': PauseCircleIcon,
    'ri-refresh-line': RefreshCwIcon,
    'ri-global-line': GlobeIcon,
    'ri-server-line': HardDriveIcon,
    'ri-tools-line': ServerCogIcon,
    'ri-eye-line': ExternalLinkIcon,
};

export function mapStatusTab(
    tab: PlatformStatusTabConfig,
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
        active: currentStatus === value,
        icon: IconComponent
            ? React.createElement(IconComponent, { className: 'size-4' })
            : undefined,
        countVariant: COLOR_MAP[tab.color ?? 'secondary'] ?? 'secondary',
    };
}

export function mapFilters(
    configFilters: Array<Record<string, unknown>>,
    activeFilters: PlatformFilterState,
    searchPlaceholder = 'Search...',
): DatagridFilter[] {
    const filters: DatagridFilter[] = [
        {
            type: 'search',
            name: 'search',
            value: String(activeFilters.search ?? ''),
            placeholder: searchPlaceholder,
            className: 'lg:min-w-80',
        },
    ];

    if (!Array.isArray(configFilters)) {
        return filters;
    }

    for (const filter of configFilters) {
        if (filter.type === 'select') {
            filters.push({
                type: 'select',
                name: String(filter.key ?? ''),
                value: String(activeFilters[String(filter.key ?? '')] ?? ''),
                options: normalizeOptions(filter.options),
                multiple: Boolean(filter.multiple),
            });
        }
    }

    return filters;
}

export function mapRowActions(
    actions: Record<string, PlatformActionPayload> | undefined,
): DatagridAction[] {
    if (!actions) {
        return [];
    }

    return Object.values(actions)
        .filter((action) => !action.hidden)
        .map((action) => ({
            label: action.label,
            href: action.url,
            method: normalizeMethod(action.method),
            confirm: action.confirm,
            disabled: action.disabled,
            variant:
                action.variant === 'danger' || action.variant === 'destructive'
                    ? 'destructive'
                    : 'default',
        }));
}

export function buildBulkActions<T extends { id: number }>(
    actions: PlatformActionConfig[] | undefined,
    routePrefix: string,
    currentStatus: string,
): DatagridBulkAction<T>[] {
    if (!actions) {
        return [];
    }

    return actions
        .filter((action) => action.scope === 'both' || action.scope === 'bulk')
        .map((action) => ({
            key: action.key,
            label: action.label,
            variant:
                action.variant === 'danger' || action.variant === 'destructive'
                    ? 'destructive'
                    : 'default',
            confirm: action.confirmBulk ?? action.confirm,
            onSelect: (rows, clearSelection) => {
                router.post(
                    route(`${routePrefix}.bulk-action`),
                    {
                        action: action.key,
                        ids: rows.map((row) => row.id),
                        status: currentStatus,
                    },
                    {
                        preserveScroll: true,
                        onSuccess: () => clearSelection(),
                    },
                );
            },
        }));
}

function normalizeOptions(options: unknown): DatagridFilterOption[] {
    if (!Array.isArray(options)) {
        return [];
    }

    return options
        .map((option) => {
            if (!option || typeof option !== 'object') {
                return null;
            }

            return {
                value: String((option as { value?: string | number }).value ?? ''),
                label: String((option as { label?: string }).label ?? ''),
            } satisfies DatagridFilterOption;
        })
        .filter((option): option is DatagridFilterOption => option !== null);
}

function normalizeMethod(
    method: string | undefined,
): DatagridAction['method'] | undefined {
    if (!method) {
        return undefined;
    }

    const normalizedMethod = method.toUpperCase();

    if (
        normalizedMethod === 'GET' ||
        normalizedMethod === 'POST' ||
        normalizedMethod === 'PUT' ||
        normalizedMethod === 'PATCH' ||
        normalizedMethod === 'DELETE'
    ) {
        return normalizedMethod;
    }

    return undefined;
}
