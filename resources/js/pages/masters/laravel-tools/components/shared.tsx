import { HttpResponseError } from '@inertiajs/core';
import { Link } from '@inertiajs/react';
import {
    BugIcon,
    FileCode2Icon,
    FileSearchIcon,
    Settings2Icon,
    TerminalSquareIcon,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';
import type {
    LaravelConfigValue,
    LaravelToolKey,
} from '@/types/laravel-tools';

type ToolDefinition = {
    key: LaravelToolKey;
    title: string;
    description: string;
    href: string;
    icon: LucideIcon;
    accentClassName: string;
    iconClassName: string;
};

export const laravelToolDefinitions: ToolDefinition[] = [
    {
        key: 'env',
        title: 'ENV Editor',
        description:
            'Review environment variables, save updates safely, and restore recent backups.',
        href: route('app.masters.laravel-tools.env'),
        icon: FileCode2Icon,
        accentClassName:
            'border-emerald-200/70 bg-emerald-50/70 dark:border-emerald-500/20 dark:bg-emerald-500/10',
        iconClassName: 'text-emerald-600 dark:text-emerald-300',
    },
    {
        key: 'artisan',
        title: 'Artisan Runner',
        description:
            'Run approved maintenance commands and inspect console output without leaving the browser.',
        href: route('app.masters.laravel-tools.artisan'),
        icon: TerminalSquareIcon,
        accentClassName:
            'border-amber-200/70 bg-amber-50/70 dark:border-amber-500/20 dark:bg-amber-500/10',
        iconClassName: 'text-amber-600 dark:text-amber-300',
    },
    {
        key: 'config',
        title: 'Config Browser',
        description:
            'Inspect resolved configuration values with sensitive keys masked before display.',
        href: route('app.masters.laravel-tools.config'),
        icon: Settings2Icon,
        accentClassName:
            'border-sky-200/70 bg-sky-50/70 dark:border-sky-500/20 dark:bg-sky-500/10',
        iconClassName: 'text-sky-600 dark:text-sky-300',
    },
    {
        key: 'routes',
        title: 'Route List',
        description:
            'Search registered routes, HTTP methods, controllers, and middleware stacks.',
        href: route('app.masters.laravel-tools.routes'),
        icon: FileSearchIcon,
        accentClassName:
            'border-violet-200/70 bg-violet-50/70 dark:border-violet-500/20 dark:bg-violet-500/10',
        iconClassName: 'text-violet-600 dark:text-violet-300',
    },
    {
        key: 'php',
        title: 'PHP Diagnostics',
        description:
            'Review runtime limits, ini groups, extensions, and driver support in one place.',
        href: route('app.masters.laravel-tools.php'),
        icon: BugIcon,
        accentClassName:
            'border-slate-200/70 bg-slate-50/70 dark:border-slate-500/20 dark:bg-slate-500/10',
        iconClassName: 'text-slate-700 dark:text-slate-200',
    },
];

export function getLaravelToolsBreadcrumbs(
    currentTitle?: string,
): BreadcrumbItem[] {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        {
            title: 'Laravel Tools',
            href: route('app.masters.laravel-tools.index'),
        },
    ];

    if (currentTitle && currentTitle !== 'Laravel Tools') {
        breadcrumbs.push({ title: currentTitle, href: '#' });
    }

    return breadcrumbs;
}

export function LaravelToolsNavigation({
    current,
}: {
    current?: LaravelToolKey;
}) {
    return (
        <Card>
            <CardContent className="flex flex-col gap-4 pt-6">
                <div className="flex flex-col gap-1">
                    <p className="text-sm font-medium text-foreground">
                        Tool navigation
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Move between diagnostics, maintenance, and configuration views.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant={!current ? 'default' : 'outline'}>
                        <Link href={route('app.masters.laravel-tools.index')}>
                            Overview
                        </Link>
                    </Button>
                    {laravelToolDefinitions.map((tool) => (
                        <Button
                            key={tool.key}
                            asChild
                            variant={current === tool.key ? 'default' : 'outline'}
                        >
                            <Link href={tool.href}>{tool.title}</Link>
                        </Button>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

export function extractHttpErrorMessage(
    error: unknown,
    fallback: string,
): string {
    if (error instanceof HttpResponseError) {
        try {
            const payload = JSON.parse(error.response.data) as {
                message?: string;
            };

            if (typeof payload.message === 'string' && payload.message !== '') {
                return payload.message;
            }
        } catch {
            return error.message || fallback;
        }
    }

    if (error instanceof Error && error.message !== '') {
        return error.message;
    }

    return fallback;
}

export function formatBytes(bytes: number): string {
    if (bytes <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const exponent = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );
    const value = bytes / 1024 ** exponent;

    return `${value.toFixed(value >= 10 || exponent === 0 ? 0 : 1)} ${units[exponent]}`;
}

export type FlattenedConfigEntry = {
    key: string;
    value: string;
    type: 'string' | 'number' | 'boolean' | 'null' | 'array' | 'object';
    masked: boolean;
};

export function flattenConfigEntries(
    value: LaravelConfigValue,
    prefix = '',
): FlattenedConfigEntry[] {
    if (Array.isArray(value)) {
        if (value.length === 0) {
            return [
                {
                    key: prefix || '(root)',
                    value: '[]',
                    type: 'array',
                    masked: false,
                },
            ];
        }

        return value.flatMap((item, index) =>
            flattenConfigEntries(item, prefix ? `${prefix}.${index}` : `${index}`),
        );
    }

    if (value !== null && typeof value === 'object') {
        const entries = Object.entries(value);

        if (entries.length === 0) {
            return [
                {
                    key: prefix || '(root)',
                    value: '{}',
                    type: 'object',
                    masked: false,
                },
            ];
        }

        return entries.flatMap(([key, item]) =>
            flattenConfigEntries(item, prefix ? `${prefix}.${key}` : key),
        );
    }

    return [
        {
            key: prefix || '(root)',
            value: formatConfigValue(value),
            type: value === null ? 'null' : (typeof value as FlattenedConfigEntry['type']),
            masked: value === '********',
        },
    ];
}

export function formatConfigValue(value: LaravelConfigValue): string {
    if (value === null) {
        return 'null';
    }

    if (typeof value === 'boolean') {
        return value ? 'true' : 'false';
    }

    if (typeof value === 'string') {
        return value === '' ? '(empty string)' : value;
    }

    if (typeof value === 'number') {
        return String(value);
    }

    if (Array.isArray(value)) {
        return value.length === 0 ? '[]' : JSON.stringify(value);
    }

    return JSON.stringify(value);
}

export function logBadgeVariant(color: string):
    | 'danger'
    | 'warning'
    | 'info'
    | 'secondary'
    | 'outline' {
    if (color === 'danger') {
        return 'danger';
    }

    if (color === 'warning') {
        return 'warning';
    }

    if (color === 'info') {
        return 'info';
    }

    if (color === 'secondary') {
        return 'secondary';
    }

    return 'outline';
}
