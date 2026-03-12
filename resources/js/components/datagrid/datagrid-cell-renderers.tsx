import type { ReactNode } from 'react';
import type { DatagridColumnType } from '@/components/datagrid/types';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type CellRendererProps = {
    value: unknown;
    type: DatagridColumnType;
};

/**
 * Render a cell value using a built-in type renderer.
 *
 * Used as fallback when no `cell` callback is provided on a column.
 */
export function renderCellByType({
    value,
    type,
}: CellRendererProps): ReactNode {
    switch (type) {
        case 'badge':
            return renderBadge(value);
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

function renderBadge(value: unknown): ReactNode {
    if (value === null || value === undefined || value === '') {
        return <span className="text-muted-foreground">—</span>;
    }

    const label = String(value);
    const variant = getBadgeVariant(label);

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
                isTruthy ? 'bg-emerald-500' : 'bg-muted-foreground/30',
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
 * Map common status labels to badge variants.
 */
function getBadgeVariant(
    label: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    const lower = label.toLowerCase();

    if (
        lower === 'active' ||
        lower === 'published' ||
        lower === 'verified' ||
        lower === 'approved' ||
        lower === 'success'
    ) {
        return 'default';
    }

    if (
        lower === 'inactive' ||
        lower === 'draft' ||
        lower === 'pending' ||
        lower === 'suspended' ||
        lower === 'warning'
    ) {
        return 'secondary';
    }

    if (
        lower === 'banned' ||
        lower === 'deleted' ||
        lower === 'failed' ||
        lower === 'error' ||
        lower === 'rejected'
    ) {
        return 'destructive';
    }

    return 'outline';
}
