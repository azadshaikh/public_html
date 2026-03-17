import { Link, usePage } from '@inertiajs/react';
import { PlusIcon, ShieldCheckIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState, mapScaffoldRowActions } from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { PlatformIndexPageProps, SslCertificateListItem } from '../../../types/platform';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    {
        title: 'Platform',
        href: route('platform.ssl-certificates.index', { status: 'all' }),
    },
    {
        title: 'SSL Certificates',
        href: route('platform.ssl-certificates.index', { status: 'all' }),
    },
];

export default function SslCertificatesIndex({
    config,
    rows,
    filters,
    statistics,
}: PlatformIndexPageProps<SslCertificateListItem>) {
    const page = usePage<AuthenticatedSharedData>();
    const canEditDomains = page.props.auth.abilities.editDomains;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics, {
        searchPlaceholder: 'Search certificates...',
        perPageOptions: [15, 25, 50, 100],
    });

    const columns: DatagridColumn<SslCertificateListItem>[] = [
        {
            key: 'name',
            header: 'Certificate',
            sortable: true,
            cell: (certificate) => (
                <div className="flex flex-col gap-1">
                    {certificate.show_url ? (
                        <Link
                            href={certificate.show_url}
                            className="font-medium text-foreground hover:text-primary"
                        >
                            {certificate.name}
                        </Link>
                    ) : (
                        <span className="font-medium text-foreground">
                            {certificate.name}
                        </span>
                    )}
                    {certificate.domain_name ? (
                        certificate.domain_url ? (
                            <Link
                                href={certificate.domain_url}
                                className="text-xs text-muted-foreground hover:text-primary"
                            >
                                {certificate.domain_name}
                            </Link>
                        ) : (
                            <span className="text-xs text-muted-foreground">
                                {certificate.domain_name}
                            </span>
                        )
                    ) : null}
                </div>
            ),
        },
        {
            key: 'certificate_authority',
            header: 'Authority',
            cell: (certificate) => (
                <Badge variant="secondary">
                    {certificate.certificate_authority}
                </Badge>
            ),
        },
        {
            key: 'expires_at',
            header: 'Expires',
            sortable: true,
        },
        {
            key: 'status_label',
            header: 'Status',
            cell: (certificate) => {
                const variant =
                    certificate.status_label === 'Expired'
                        ? 'destructive'
                        : certificate.status_label === 'Expiring Soon'
                          ? 'warning'
                          : 'success';

                return (
                    <Badge variant={variant}>{certificate.status_label}</Badge>
                );
            },
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="SSL Certificates"
            description="Review certificate health across all domains, including expiry windows and issuing authorities."
            headerActions={
                canEditDomains ? (
                    <Button asChild>
                        <Link
                            href={route('platform.domains.index', {
                                status: 'all',
                            })}
                        >
                            <PlusIcon data-icon="inline-start" />
                            Add certificate
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <Datagrid
                action={route('platform.ssl-certificates.index', {
                    status: currentStatus,
                })}
                rows={rows}
                columns={columns}
                filters={gridFilters}
                tabs={{ name: 'status', items: statusTabs }}
                getRowKey={(certificate) => certificate.id}
                rowActions={(certificate) => mapScaffoldRowActions(certificate.actions)}
                empty={{
                    icon: <ShieldCheckIcon className="size-5" />,
                    title: 'No certificates found',
                    description:
                        'Certificates attached to domains will appear here with expiry and status details.',
                }}
                sorting={sorting}
                perPage={perPage}
                title="Global certificate inventory"
                description="Use the domain links to inspect, replace, or generate certificates for specific domains."
            />
        </AppLayout>
    );
}
