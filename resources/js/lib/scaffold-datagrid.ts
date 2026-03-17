import { router } from '@inertiajs/react';
import {
    CheckCircleIcon,
    CircleIcon,
    ClockIcon,
    CopyIcon,
    ExternalLinkIcon,
    FileIcon,
    FileTextIcon,
    GlobeIcon,
    HardDriveIcon,
    HourglassIcon,
    LayoutTemplateIcon,
    ListChecksIcon,
    MenuIcon,
    PauseCircleIcon,
    PencilIcon,
    RefreshCwIcon,
    RocketIcon,
    ServerCogIcon,
    ShieldAlertIcon,
    Trash2Icon,
    XCircleIcon,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { createElement } from 'react';
import type { ReactNode } from 'react';
import type {
    DatagridAction,
    DatagridBulkAction,
    DatagridFilter,
    DatagridFilterOption,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import type {
    ScaffoldActionConfig,
    ScaffoldFilterConfig,
    ScaffoldFilterState,
    ScaffoldInertiaConfig,
    ScaffoldRowActionPayload,
    ScaffoldStatusTabConfig,
} from '@/types/scaffold';

type IconMap = Record<string, LucideIcon>;

type BuildDatagridStateOptions = {
    searchPlaceholder?: string;
    searchClassName?: string;
    perPageOptions?: number[];
    statusParamName?: string;
    sortParamName?: string;
    directionParamName?: string;
    perPageParamName?: string;
    includeSearch?: boolean;
    iconMap?: IconMap;
};

type BuildBulkActionOptions<T> = {
    routePrefix: string;
    currentStatus: string;
    getRowId?: (row: T) => string | number;
    iconMap?: IconMap;
    extraPayload?: Record<string, unknown>;
};

const DEFAULT_COLOR_MAP: Record<string, DatagridTab['countVariant']> = {
    primary: 'secondary',
    success: 'success',
    warning: 'warning',
    info: 'info',
    danger: 'danger',
    destructive: 'destructive',
    secondary: 'secondary',
};

const DEFAULT_ICON_MAP: IconMap = {
    'ri-list-check': ListChecksIcon,
    'ri-checkbox-circle-line': CheckCircleIcon,
    'ri-checkbox-blank-circle-line': CircleIcon,
    'ri-delete-bin-line': Trash2Icon,
    'ri-delete-bin-fill': Trash2Icon,
    'ri-error-warning-line': ShieldAlertIcon,
    'ri-close-circle-line': XCircleIcon,
    'ri-time-line': ClockIcon,
    'ri-hourglass-line': HourglassIcon,
    'ri-pause-circle-line': PauseCircleIcon,
    'ri-refresh-line': RefreshCwIcon,
    'ri-loop-right-line': RefreshCwIcon,
    'ri-global-line': GlobeIcon,
    'ri-server-line': HardDriveIcon,
    'ri-tools-line': ServerCogIcon,
    'ri-eye-line': ExternalLinkIcon,
    'ri-pencil-line': PencilIcon,
    'ri-file-copy-line': CopyIcon,
    'ri-file-line': FileIcon,
    'ri-layout-3-line': LayoutTemplateIcon,
    'ri-menu-line': MenuIcon,
    ListCheck: ListChecksIcon,
    Rocket: RocketIcon,
    FileText: FileTextIcon,
    XCircle: XCircleIcon,
    Trash2: Trash2Icon,
};

export function mapScaffoldStatusTab(
    tab: ScaffoldStatusTabConfig,
    statistics: Record<string, number>,
    currentStatus: string,
    iconMap: IconMap = {},
): DatagridTab {
    const value = tab.value ?? tab.key;
    const count = value === 'all' ? undefined : (statistics[value] ?? 0);

    return {
        label: tab.label,
        value,
        count,
        active: currentStatus === value,
        icon: resolveIcon(tab.icon, iconMap),
        countVariant: DEFAULT_COLOR_MAP[tab.color ?? 'secondary'] ?? 'secondary',
    };
}

export function mapScaffoldFilters(
    configFilters: ScaffoldFilterConfig[] | undefined,
    activeFilters: Record<string, unknown>,
    options: Pick<BuildDatagridStateOptions, 'searchPlaceholder' | 'searchClassName' | 'includeSearch'> = {},
): DatagridFilter[] {
    const filters: DatagridFilter[] = [];

    if (options.includeSearch !== false) {
        filters.push({
            type: 'search',
            name: 'search',
            value: normalizeString(activeFilters.search),
            placeholder: options.searchPlaceholder ?? 'Search...',
            className: options.searchClassName ?? 'lg:min-w-80',
        });
    }

    if (!Array.isArray(configFilters)) {
        return filters;
    }

    for (const filter of configFilters) {
        const key = String(filter.key ?? '');

        if (key === '' || filter.type === 'search') {
            continue;
        }

        if (filter.type === 'select') {
            filters.push({
                type: 'select',
                name: key,
                value: normalizeString(activeFilters[key]),
                options: normalizeOptions(filter.options),
                multiple: Boolean(filter.multiple),
            });

            continue;
        }

        if (filter.type === 'date_range') {
            filters.push({
                type: 'date_range',
                name: key,
                label: filter.label,
                value: normalizeString(activeFilters[key]),
            });

            continue;
        }

        if (filter.type === 'boolean') {
            const normalizedOptions = normalizeOptionRecord(filter.options);
            filters.push({
                type: 'boolean',
                name: key,
                label: filter.label,
                value: normalizeString(activeFilters[key]),
                trueLabel: normalizedOptions['1'] ?? 'Yes',
                falseLabel: normalizedOptions['0'] ?? 'No',
            });

            continue;
        }

        if (filter.type === 'number') {
            filters.push({
                type: 'number',
                name: key,
                label: filter.label,
                value: normalizeString(activeFilters[key]),
                placeholder: filter.placeholder,
                min: typeof filter.min === 'number' ? filter.min : undefined,
                max: typeof filter.max === 'number' ? filter.max : undefined,
                step: typeof filter.step === 'number' ? filter.step : undefined,
            });

            continue;
        }

        if (filter.type === 'hidden') {
            filters.push({
                type: 'hidden',
                name: key,
                value: normalizeString(activeFilters[key]),
            });
        }
    }

    return filters;
}

export function mapScaffoldRowActions(
    actions: Record<string, ScaffoldRowActionPayload> | ScaffoldRowActionPayload[] | undefined,
    iconMap: IconMap = {},
): DatagridAction[] {
    if (!actions) {
        return [];
    }

    const values = Array.isArray(actions) ? actions : Object.values(actions);

    return values
        .filter((action) => !action.hidden)
        .map((action) => ({
            label: action.label,
            href: action.url,
            icon: resolveIcon(action.icon, iconMap),
            method: normalizeMethod(action.method),
            confirm: action.confirm,
            disabled: action.disabled,
            variant: isDestructiveVariant(action.variant) ? 'destructive' : 'default',
        }));
}

export function buildScaffoldBulkActions<T extends { id: string | number }>(
    actions: ScaffoldActionConfig[] | undefined,
    options: BuildBulkActionOptions<T>,
): DatagridBulkAction<T>[] {
    if (!Array.isArray(actions)) {
        return [];
    }

    return actions
        .filter((action) => action.scope === 'both' || action.scope === 'bulk')
        .filter((action) => matchesStatusCondition(action.conditions?.status, options.currentStatus))
        .map((action) => ({
            key: action.key,
            label: action.label,
            icon: resolveIcon(action.icon, options.iconMap),
            variant: isDestructiveVariant(action.variant) ? 'destructive' : 'default',
            confirm: action.confirmBulk ?? action.confirm,
            onSelect: (rows, clearSelection) => {
                router.post(
                    route(`${options.routePrefix}.bulk-action`),
                    {
                        action: action.key,
                        ids: rows.map((row) => (options.getRowId ? options.getRowId(row) : row.id)),
                        status: options.currentStatus,
                        ...options.extraPayload,
                    },
                    {
                        preserveScroll: true,
                        onSuccess: () => clearSelection(),
                    },
                );
            },
        }));
}

export function buildScaffoldDatagridState(
    config: Pick<ScaffoldInertiaConfig, 'filters' | 'statusTabs' | 'settings'>,
    filters: ScaffoldFilterState | Record<string, unknown>,
    statistics: Record<string, number>,
    options: BuildDatagridStateOptions = {},
): {
    currentStatus: string;
    gridFilters: DatagridFilter[];
    statusTabs: DatagridTab[];
    sorting: { sort: string; direction: 'asc' | 'desc' };
    perPage: { value: number; options: number[]; paramName: string };
} {
    const statusParamName = options.statusParamName ?? 'status';
    const sortParamName = options.sortParamName ?? 'sort';
    const directionParamName = options.directionParamName ?? 'direction';
    const perPageParamName = options.perPageParamName ?? 'per_page';
    const currentStatus = normalizeString(filters[statusParamName]) || 'all';

    return {
        currentStatus,
        gridFilters: mapScaffoldFilters(config.filters, filters, options),
        statusTabs: (config.statusTabs ?? []).map((tab) =>
            mapScaffoldStatusTab(tab, statistics, currentStatus, options.iconMap),
        ),
        sorting: {
            sort: normalizeString(filters[sortParamName]) || config.settings.defaultSort || 'created_at',
            direction: normalizeDirection(filters[directionParamName] ?? config.settings.defaultDirection),
        },
        perPage: {
            value: normalizeNumber(filters[perPageParamName], config.settings.perPage),
            options: options.perPageOptions ?? [10, 25, 50, 100],
            paramName: perPageParamName,
        },
    };
}

function resolveIcon(icon: string | null | undefined, iconMap: IconMap = {}): ReactNode | undefined {
    if (!icon) {
        return undefined;
    }

    const IconComponent = iconMap[icon] ?? DEFAULT_ICON_MAP[icon];

    if (!IconComponent) {
        return undefined;
    }

    return createElement(IconComponent, { className: 'size-4' });
}

function matchesStatusCondition(
    condition: [string, string | string[]] | undefined,
    currentStatus: string,
): boolean {
    if (!condition) {
        return true;
    }

    const [operator, rawValue] = condition;
    const values = Array.isArray(rawValue) ? rawValue : [rawValue];

    switch (operator) {
        case '=':
            return currentStatus === values[0];
        case '!=':
            return currentStatus !== values[0];
        case 'in':
            return values.includes(currentStatus);
        case 'not_in':
            return !values.includes(currentStatus);
        default:
            return true;
    }
}

function normalizeOptions(options: ScaffoldFilterConfig['options']): DatagridFilterOption[] {
    if (Array.isArray(options)) {
        return options
            .map((option) => {
                if (!option || typeof option !== 'object') {
                    return null;
                }

                return {
                    value: String(option.value ?? ''),
                    label: String(option.label ?? ''),
                } satisfies DatagridFilterOption;
            })
            .filter((option): option is DatagridFilterOption => option !== null && option.value !== '' && option.label !== '');
    }

    if (!options || typeof options !== 'object') {
        return [];
    }

    return Object.entries(options)
        .map(([value, label]) => ({
            value,
            label: String(label ?? value),
        }));
}

function normalizeOptionRecord(options: ScaffoldFilterConfig['options']): Record<string, string> {
    if (!options || typeof options !== 'object' || Array.isArray(options)) {
        return {};
    }

    return Object.fromEntries(
        Object.entries(options).map(([key, value]) => [key, String(value ?? '')]),
    );
}

function normalizeMethod(method: string | undefined): DatagridAction['method'] | undefined {
    if (!method) {
        return undefined;
    }

    const normalizedMethod = method.toUpperCase();

    if (normalizedMethod === 'GET' || normalizedMethod === 'POST' || normalizedMethod === 'PUT' || normalizedMethod === 'PATCH' || normalizedMethod === 'DELETE') {
        return normalizedMethod;
    }

    return undefined;
}

function normalizeString(value: unknown): string {
    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return '';
}

function normalizeDirection(value: unknown): 'asc' | 'desc' {
    return String(value).toLowerCase() === 'asc' ? 'asc' : 'desc';
}

function normalizeNumber(value: unknown, fallback: number): number {
    const normalizedValue = Number(value);

    if (Number.isFinite(normalizedValue) && normalizedValue > 0) {
        return normalizedValue;
    }

    return fallback;
}

function isDestructiveVariant(variant: string | undefined): boolean {
    return variant === 'danger' || variant === 'destructive';
}
