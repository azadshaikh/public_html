import { Link, router, usePage } from '@inertiajs/react';
import {
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
    TicketIcon,
    Trash2Icon,
} from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type {
    DatagridAction,
    DatagridBulkAction,
    DatagridColumn,
} from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type {
    TicketIndexPageProps,
    TicketListItem,
} from '../../../types/helpdesk';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Tickets', href: route('helpdesk.tickets.index') },
];

export default function TicketsIndex({
    config,
    rows,
    filters,
    statistics,
}: TicketIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAdd = page.props.auth.abilities.addHelpdeskTickets;
    const canEdit = page.props.auth.abilities.editHelpdeskTickets;
    const canDelete = page.props.auth.abilities.deleteHelpdeskTickets;
    const canRestore = page.props.auth.abilities.restoreHelpdeskTickets;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } =
        buildScaffoldDatagridState(config, filters, statistics, {
            searchPlaceholder: 'Search tickets...',
        });

    const handleBulkAction = (
        action: string,
        selected: TicketListItem[],
        clearSelection: () => void,
    ) => {
        if (selected.length === 0) return;
        router.post(
            route('helpdesk.tickets.bulk-action'),
            {
                action,
                ids: selected.map((t) => t.id),
                status: currentStatus,
            },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<TicketListItem>[] = [
        {
            key: 'ticket_number',
            header: 'Ticket #',
            headerClassName: 'w-[130px]',
            cellClassName: 'w-[130px]',
            sortable: true,
            cell: (ticket) => (
                <Link
                    href={ticket.show_url}
                    className="font-mono text-sm font-medium text-foreground hover:underline"
                >
                    {ticket.ticket_number}
                </Link>
            ),
        },
        {
            key: 'subject',
            header: 'Subject',
            sortable: true,
            cell: (ticket) => (
                <Link
                    href={ticket.show_url}
                    className="flex min-w-0 flex-col gap-0.5 hover:opacity-80"
                >
                    <span className="font-medium text-foreground">
                        {ticket.subject}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {ticket.department_name} &middot;{' '}
                        {ticket.raised_by_name}
                    </span>
                </Link>
            ),
        },
        {
            key: 'assigned_to_name',
            header: 'Assigned To',
            headerClassName: 'w-[160px]',
            cellClassName: 'w-[160px] text-sm text-muted-foreground',
            sortable: true,
            sortKey: 'assigned_to',
        },
        {
            key: 'priority_label',
            header: 'Priority',
            headerClassName: 'w-[100px] text-center',
            cellClassName: 'w-[100px] text-center',
            type: 'badge',
            badgeVariantKey: 'priority_badge',
            sortable: true,
            sortKey: 'priority',
        },
        {
            key: 'status_label',
            header: 'Status',
            headerClassName: 'w-[110px] text-center',
            cellClassName: 'w-[110px] text-center',
            type: 'badge',
            badgeVariantKey: 'status_badge',
            sortable: true,
            sortKey: 'status',
        },
        {
            key: 'created_at_formatted',
            header: 'Created',
            headerClassName: 'w-[140px]',
            cellClassName: 'w-[140px] text-sm text-muted-foreground',
            sortable: true,
            sortKey: 'created_at',
        },
    ];

    const rowActions = (ticket: TicketListItem): DatagridAction[] => {
        if (ticket.is_trashed) {
            return [
                ...(canRestore
                    ? [
                          {
                              label: 'Restore',
                              icon: <RefreshCwIcon />,
                              href: route(
                                  'helpdesk.tickets.restore',
                                  ticket.id,
                              ),
                              method: 'PATCH' as const,
                              confirm: `Restore "${ticket.subject}"?`,
                          },
                      ]
                    : []),
                ...(canDelete
                    ? [
                          {
                              label: 'Delete Permanently',
                              icon: <Trash2Icon />,
                              href: route(
                                  'helpdesk.tickets.force-delete',
                                  ticket.id,
                              ),
                              method: 'DELETE' as const,
                              confirm: `⚠️ Permanently delete "${ticket.subject}"? This cannot be undone!`,
                              variant: 'destructive' as const,
                          },
                      ]
                    : []),
            ];
        }
        return [
            ...(canEdit
                ? [
                      {
                          label: 'Edit',
                          href: ticket.edit_url,
                          icon: <PencilIcon />,
                      },
                  ]
                : []),
            ...(canDelete
                ? [
                      {
                          label: 'Move to Trash',
                          href: route('helpdesk.tickets.destroy', ticket.id),
                          method: 'DELETE' as const,
                          confirm: `Move "${ticket.subject}" to trash?`,
                          icon: <Trash2Icon />,
                          variant: 'destructive' as const,
                      },
                  ]
                : []),
        ];
    };

    const bulkActions: DatagridBulkAction<TicketListItem>[] = [
        ...(canDelete
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected tickets to trash?',
                      onSelect: (
                          items: TicketListItem[],
                          clear: () => void,
                      ) => handleBulkAction('delete', items, clear),
                  },
              ]
            : []),
        ...(canRestore
            ? [
                  {
                      key: 'bulk-restore',
                      label: 'Restore',
                      icon: <RefreshCwIcon />,
                      confirm: 'Restore selected tickets?',
                      onSelect: (
                          items: TicketListItem[],
                          clear: () => void,
                      ) => handleBulkAction('restore', items, clear),
                  },
              ]
            : []),
        ...(canDelete
            ? [
                  {
                      key: 'bulk-force-delete',
                      label: 'Delete Permanently',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: '⚠️ Permanently delete selected tickets?',
                      onSelect: (
                          items: TicketListItem[],
                          clear: () => void,
                      ) => handleBulkAction('force_delete', items, clear),
                  },
              ]
            : []),
    ];

    const visibleBulkActions =
        currentStatus === 'trash'
            ? bulkActions.filter((a) => a.key !== 'bulk-delete')
            : bulkActions.filter((a) => a.key === 'bulk-delete');

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Tickets"
            description="Manage helpdesk tickets"
            headerActions={
                canAdd ? (
                    <Button asChild>
                        <Link href={route('helpdesk.tickets.create')}>
                            <PlusIcon data-icon="inline-start" />
                            New Ticket
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('helpdesk.tickets.index')}
                    rows={rows}
                    columns={columns}
                    scaffoldColumns={config.columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(ticket) => ticket.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={sorting}
                    perPage={perPage}
                    view={{
                        value:
                            (filters.view as 'table' | 'cards') ?? 'table',
                        storageKey: 'helpdesk-tickets-datagrid-view',
                    }}
                    renderCard={(ticket) => (
                        <div className="flex flex-col gap-3">
                            <div>
                                <Link
                                    href={ticket.show_url}
                                    className="font-mono text-xs text-muted-foreground"
                                >
                                    {ticket.ticket_number}
                                </Link>
                                <Link
                                    href={ticket.show_url}
                                    className="mt-0.5 block font-semibold text-foreground hover:underline"
                                >
                                    {ticket.subject}
                                </Link>
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {ticket.department_name} &middot;{' '}
                                {ticket.raised_by_name}
                            </div>
                            <div className="mt-auto flex flex-wrap items-center gap-2 pt-2">
                                <Badge
                                    variant={
                                        ticket.priority_badge as Parameters<
                                            typeof Badge
                                        >[0]['variant']
                                    }
                                >
                                    {ticket.priority_label}
                                </Badge>
                                <Badge
                                    variant={
                                        ticket.status_badge as Parameters<
                                            typeof Badge
                                        >[0]['variant']
                                    }
                                >
                                    {ticket.status_label}
                                </Badge>
                            </div>
                        </div>
                    )}
                    empty={{
                        icon: <TicketIcon />,
                        title: 'No tickets found',
                        description:
                            'Try a different filter or create a new ticket.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
