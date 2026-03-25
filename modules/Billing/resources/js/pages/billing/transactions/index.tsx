import { Link } from '@inertiajs/react';
import { ArrowRightLeftIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import type { BreadcrumbItem } from '@/types';
import type { TransactionIndexPageProps, TransactionListItem } from '../../../types/billing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing' },
    { title: 'Transactions', href: route('app.billing.transactions.index') },
];

export default function TransactionsIndex({ config, rows, filters, statistics }: TransactionIndexPageProps) {
    const { gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics ?? {}, {
        searchPlaceholder: 'Search transactions...',
    });

    const columns: DatagridColumn<TransactionListItem>[] = [
        {
            key: 'transaction_id',
            header: 'Transaction ID',
            sortable: true,
            cell: (txn) => (
                <Link href={txn.show_url} className="font-medium text-foreground hover:underline">
                    {txn.transaction_id}
                </Link>
            ),
        },
        {
            key: 'customer_display',
            header: 'Customer',
            headerClassName: 'w-[200px]',
            cellClassName: 'w-[200px] text-sm text-muted-foreground',
            sortable: true,
        },
        {
            key: 'source_display',
            header: 'Source',
            headerClassName: 'w-[160px]',
            cellClassName: 'w-[160px] text-sm text-muted-foreground',
        },
        {
            key: 'formatted_amount',
            header: 'Amount',
            headerClassName: 'w-[120px] text-right',
            cellClassName: 'w-[120px] text-right text-sm font-medium',
            sortable: true,
            sortKey: 'amount',
        },
        {
            key: 'type_label',
            header: 'Type',
            headerClassName: 'w-[120px] text-center',
            cellClassName: 'w-[120px] text-center',
            type: 'badge',
            badgeVariantKey: 'type_badge',
        },
        {
            key: 'status_label',
            header: 'Status',
            headerClassName: 'w-[120px] text-center',
            cellClassName: 'w-[120px] text-center',
            type: 'badge',
            badgeVariantKey: 'status_badge',
            sortable: true,
            sortKey: 'status',
        },
        {
            key: 'created_at',
            header: 'Date',
            headerClassName: 'w-[120px]',
            cellClassName: 'w-[120px] text-sm text-muted-foreground',
            sortable: true,
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Transactions"
            description="View billing transactions"
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('app.billing.transactions.index')}
                    rows={rows}
                    columns={columns}
                    scaffoldColumns={config.columns}
                    filters={gridFilters}
                    tabs={statusTabs.length > 0 ? { name: 'status', items: statusTabs } : undefined}
                    getRowKey={(i) => i.id}
                    sorting={sorting}
                    perPage={perPage}
                    renderCard={(txn) => (
                        <div className="flex flex-col gap-3">
                            <Link href={txn.show_url} className="font-semibold text-foreground hover:underline">
                                {txn.transaction_id}
                            </Link>
                            <div className="space-y-1 text-sm text-muted-foreground">
                                <div>{txn.customer_display}</div>
                                <div>{txn.source_display}</div>
                                <div className="font-medium text-foreground">{txn.formatted_amount}</div>
                            </div>
                            <div className="mt-auto flex flex-wrap items-center gap-2 pt-2">
                                <Badge variant={txn.type_badge as Parameters<typeof Badge>[0]['variant']}>{txn.type_label}</Badge>
                                <Badge variant={txn.status_badge as Parameters<typeof Badge>[0]['variant']}>{txn.status_label}</Badge>
                            </div>
                        </div>
                    )}
                    empty={{ icon: <ArrowRightLeftIcon />, title: 'No transactions found', description: 'Try a different filter.' }}
                />
            </div>
        </AppLayout>
    );
}
