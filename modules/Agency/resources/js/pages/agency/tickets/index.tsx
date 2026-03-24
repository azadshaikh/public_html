import { Link } from '@inertiajs/react';
import { PlusIcon, TicketIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import type { BreadcrumbItem } from '@/types';
import type { ScaffoldIndexPageProps } from '@/types/scaffold';

type AgencyTicketRow = {
    id: number;
    subject: string;
    show_url: string;
    status: string;
    status_label: string;
    last_updated: string;
};

function statusVariant(status: string): 'success' | 'warning' | 'secondary' | 'danger' {
    switch (status.toLowerCase()) {
        case 'open':
            return 'success';
        case 'pending':
        case 'on_hold':
            return 'warning';
        case 'resolved':
        case 'closed':
            return 'secondary';
        default:
            return 'danger';
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Support', href: route('agency.tickets.index') },
];

export default function AgencyTicketsIndex({
    config,
    rows,
    filters,
    statistics,
}: ScaffoldIndexPageProps<AgencyTicketRow>) {
    const { gridFilters, perPage, sorting } = buildScaffoldDatagridState(
        config,
        filters,
        statistics,
        {
            searchPlaceholder: 'Search your tickets...',
        },
    );

    const columns: DatagridColumn<AgencyTicketRow>[] = [
        {
            key: 'subject',
            header: 'Subject',
            cell: (ticket) => (
                <Link
                    href={ticket.show_url}
                    className="font-medium text-foreground hover:text-primary"
                >
                    {ticket.subject}
                </Link>
            ),
        },
        {
            key: 'status_label',
            header: 'Status',
            cell: (ticket) => (
                <Badge variant={statusVariant(ticket.status)}>
                    {ticket.status_label}
                </Badge>
            ),
        },
        {
            key: 'last_updated',
            header: 'Last Updated',
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Support Tickets"
            description="Track and review the support requests opened for your account."
            headerActions={
                <Button asChild>
                    <Link href={route('agency.tickets.create')}>
                        <PlusIcon data-icon="inline-start" />
                        New Ticket
                    </Link>
                </Button>
            }
        >
            <Datagrid
                action={route('agency.tickets.index')}
                rows={rows}
                columns={columns}
                scaffoldColumns={config.columns}
                filters={gridFilters}
                getRowKey={(ticket) => ticket.id}
                sorting={sorting}
                perPage={perPage}
                empty={{
                    icon: <TicketIcon className="size-5" />,
                    title: 'No tickets found',
                    description:
                        'Open a new ticket when you need help with your websites, billing, or onboarding.',
                }}
            />
        </AppLayout>
    );
}
