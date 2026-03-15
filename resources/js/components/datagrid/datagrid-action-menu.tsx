import { Link, router } from '@inertiajs/react';
import { EllipsisVerticalIcon } from 'lucide-react';
import { useState } from 'react';
import type { DatagridAction } from '@/components/datagrid/types';
import { Button } from '@/components/ui/button';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

function executeAction(action: DatagridAction): void {
    if (action.href && action.method && action.method !== 'GET') {
        const method = action.method.toLowerCase() as
            | 'post'
            | 'put'
            | 'patch'
            | 'delete';
        router[method](action.href, {}, { preserveScroll: true });
        return;
    }

    if (action.onSelect) {
        action.onSelect();
        return;
    }

    if (action.href) {
        router.get(action.href);
    }
}

export function DatagridActionMenu({ actions }: { actions: DatagridAction[] }) {
    const [confirmAction, setConfirmAction] = useState<DatagridAction | null>(
        null,
    );

    const visibleActions = actions.filter((action) => !action.hidden);

    if (visibleActions.length === 0) {
        return null;
    }

    function handleAction(action: DatagridAction): void {
        if (action.confirm) {
            setConfirmAction(action);
            return;
        }
        executeAction(action);
    }

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        size="icon-comfortable"
                        className="rounded-full shadow-xs"
                        aria-label="Open row actions"
                    >
                        <EllipsisVerticalIcon />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-40">
                    {visibleActions.map((action) => {
                        if (action.href && !action.method && !action.confirm) {
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
                                    handleAction(action);
                                }}
                            >
                                {action.icon}
                                <span>{action.label}</span>
                            </DropdownMenuItem>
                        );
                    })}
                </DropdownMenuContent>
            </DropdownMenu>

            <ConfirmationDialog
                open={!!confirmAction}
                onOpenChange={(open) => !open && setConfirmAction(null)}
                title={confirmAction?.label ?? 'Confirm action'}
                description={confirmAction?.confirm}
                confirmLabel={confirmAction?.label ?? 'Continue'}
                tone={
                    confirmAction?.variant === 'destructive'
                        ? 'destructive'
                        : 'default'
                }
                onConfirm={() => {
                    if (confirmAction) {
                        executeAction(confirmAction);
                    }
                    setConfirmAction(null);
                }}
            />
        </>
    );
}
