import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import TicketForm from '../../../components/tickets/ticket-form';
import type { TicketEditPageProps } from '../../../types/helpdesk';

export default function TicketsEdit({
    ticket,
    ...props
}: TicketEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Tickets', href: route('helpdesk.tickets.index') },
        {
            title: ticket.ticket_number,
            href: route('helpdesk.tickets.show', ticket.id),
        },
        {
            title: 'Edit',
            href: route('helpdesk.tickets.edit', ticket.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${ticket.subject}`}
            description={`Ticket ${ticket.ticket_number}`}
            headerActions={
                <Button variant="outline" asChild>
                    <Link
                        href={route('helpdesk.tickets.show', ticket.id)}
                    >
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <TicketForm mode="edit" ticket={ticket} {...props} />
        </AppLayout>
    );
}
