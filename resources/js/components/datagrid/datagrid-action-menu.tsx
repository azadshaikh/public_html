import { Link } from '@inertiajs/react';
import { MoreHorizontalIcon } from 'lucide-react';
import type { DatagridAction } from '@/components/datagrid/types';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export function DatagridActionMenu({ actions }: { actions: DatagridAction[] }) {
    if (actions.length === 0) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Open row actions"
                >
                    <MoreHorizontalIcon />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-40">
                {actions.map((action) => {
                    if (action.href) {
                        return (
                            <DropdownMenuItem
                                key={action.label}
                                asChild
                                disabled={action.disabled}
                                variant={action.variant}
                            >
                                <Link href={action.href} preserveScroll>
                                    {action.icon}
                                    <span>{action.label}</span>
                                </Link>
                            </DropdownMenuItem>
                        );
                    }

                    return (
                        <DropdownMenuItem
                            key={action.label}
                            disabled={action.disabled}
                            variant={action.variant}
                            onSelect={(event) => {
                                event.preventDefault();
                                action.onSelect?.();
                            }}
                        >
                            {action.icon}
                            <span>{action.label}</span>
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
