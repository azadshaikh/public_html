import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import TicketForm from '../../../components/tickets/ticket-form';
import type { TicketCreatePageProps } from '../../../types/helpdesk';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Tickets', href: route('helpdesk.tickets.index') },
    { title: 'New Ticket', href: route('helpdesk.tickets.create') },
];

export default function TicketsCreate(props: TicketCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="New Ticket"
            description="Create a new helpdesk ticket"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('helpdesk.tickets.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <TicketForm mode="create" {...props} />
        </AppLayout>
    );
}
