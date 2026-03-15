import type { ReactNode } from 'react';
import type { DatagridColumnType } from '@/components/datagrid/types';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type CellRendererProps = {
    value: unknown;
    type: DatagridColumnType;
    /** Pre-resolved badge variant from row data (via badgeVariantKey) */
    badgeVariant?: string;
    /** Static map of value → badge variant name */
    badgeVariants?: Record<string, string>;
};

/**
 * Render a cell value using a built-in type renderer.
 *
 * Used as fallback when no `cell` callback is provided on a column.
 */
export function renderCellByType({
    value,
    type,
    badgeVariant,
    badgeVariants,
}: CellRendererProps): ReactNode {
    switch (type) {
        case 'badge':
            return renderBadge(value, badgeVariant, badgeVariants);
        case 'boolean':
            return renderBoolean(value);
        case 'currency':
            return renderCurrency(value);
        case 'image':
            return renderImage(value);
        case 'link':
            return renderLink(value);
        case 'date':
            return renderDate(value);
        case 'text':
        default:
            return renderText(value);
    }
}

function renderText(value: unknown): ReactNode {
    if (value === null || value === undefined) {
        return <span className="text-muted-foreground">—</span>;
    }

    return <span>{String(value)}</span>;
}

function renderBadge(
    value: unknown,
    badgeVariant?: string,
    badgeVariants?: Record<string, string>,
): ReactNode {
    if (value === null || value === undefined || value === '') {
        return <span className="text-muted-foreground">—</span>;
    }

    const label = String(value);
    const variant = resolveBadgeVariant(label, badgeVariant, badgeVariants);

    return <Badge variant={variant}>{label}</Badge>;
}

function renderBoolean(value: unknown): ReactNode {
    const isTruthy =
        value === true ||
        value === 1 ||
        value === '1' ||
        value === 'yes' ||
        value === 'true';

    return (
        <span
            className={cn(
                'inline-flex size-2.5 rounded-full',
                isTruthy
                    ? 'bg-[var(--success-foreground)] dark:bg-[var(--success-dark-foreground)]'
                    : 'bg-muted-foreground/30',
            )}
            aria-label={isTruthy ? 'Yes' : 'No'}
        />
    );
}

function renderCurrency(value: unknown): ReactNode {
    if (value === null || value === undefined) {
        return <span className="text-muted-foreground">—</span>;
    }

    const numValue =
        typeof value === 'number' ? value : parseFloat(String(value));

    if (isNaN(numValue)) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span className="tabular-nums">
            {new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
            }).format(numValue)}
        </span>
    );
}

function renderImage(value: unknown): ReactNode {
    if (!value || typeof value !== 'string') {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <img
            src={value}
            alt=""
            className="size-10 rounded object-cover"
            loading="lazy"
        />
    );
}

function renderLink(value: unknown): ReactNode {
    if (!value) {
        return <span className="text-muted-foreground">—</span>;
    }

    const text = String(value);

    return (
        <a
            href={text}
            className="text-primary underline-offset-4 hover:underline"
            target="_blank"
            rel="noopener noreferrer"
        >
            {text}
        </a>
    );
}

function renderDate(value: unknown): ReactNode {
    if (value === null || value === undefined || value === '') {
        return <span className="text-muted-foreground">—</span>;
    }

    const date = new Date(String(value));

    if (isNaN(date.getTime())) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <time dateTime={date.toISOString()} className="whitespace-nowrap">
            {date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
            })}
        </time>
    );
}

/**
 * Resolve the badge variant for a cell value.
 *
 * Priority: badgeVariant (per-row) > badgeVariants map > automatic fallback.
 */
function resolveBadgeVariant(
    label: string,
    badgeVariant?: string,
    badgeVariants?: Record<string, string>,
): string {
    // 1. Pre-resolved variant from row data (via badgeVariantKey)
    if (badgeVariant) {
        return badgeVariant;
    }

    // 2. Static map lookup (via badgeVariants on column)
    const lower = label.toLowerCase();
    if (badgeVariants) {
        return badgeVariants[lower] ?? badgeVariants[label] ?? 'outline';
    }

    // 3. Automatic fallback based on common status labels
    if (
        [
            'active',
            'published',
            'approved',
            'verified',
            'success',
            'completed',
        ].includes(lower)
    ) {
        return 'success';
    }
    if (
        [
            'pending',
            'under_review',
            'under review',
            'unverified',
            'maintenance',
            'suspended',
        ].includes(lower)
    ) {
        return 'warning';
    }
    if (['draft', 'deploying'].includes(lower)) {
        return 'info';
    }
    if (
        [
            'inactive',
            'archived',
            'expired',
            'cancelled',
            'rolled_back',
        ].includes(lower)
    ) {
        return 'secondary';
    }
    if (
        ['banned', 'deleted', 'failed', 'error', 'rejected', 'locked'].includes(
            lower,
        )
    ) {
        return 'danger';
    }

    return 'outline';
}
