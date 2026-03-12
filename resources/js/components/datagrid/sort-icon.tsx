import { ArrowDownIcon, ArrowUpDownIcon, ArrowUpIcon } from 'lucide-react';

export function SortIcon({
    active,
    direction,
}: {
    active: boolean;
    direction?: 'asc' | 'desc';
}) {
    if (!active) {
        return <ArrowUpDownIcon className="size-3.5" />;
    }

    if (direction === 'desc') {
        return <ArrowDownIcon className="size-3.5" />;
    }

    return <ArrowUpIcon className="size-3.5" />;
}
