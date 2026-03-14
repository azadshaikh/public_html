import { Link, router, usePage } from '@inertiajs/react';
import {
    CheckCircleIcon,
    EyeIcon,
    ListIcon,
    MailIcon,
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
    ServerIcon,
    SlashIcon,
    Trash2Icon,
} from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type {
    DatagridAction,
    DatagridBulkAction,
    DatagridColumn,
    DatagridFilter,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type {
    EmailProviderListItem,
    EmailProvidersIndexPageProps,
} from '@/types/email';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    {
        title: 'Email Providers',
        href: route('app.masters.email.providers.index'),
    },
];

export default function EmailProvidersIndex({
    emailProviders,
    statistics,
    filters,
}: EmailProvidersIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddEmailProviders = page.props.auth.abilities.addEmailProviders;
    const canEditEmailProviders = page.props.auth.abilities.editEmailProviders;
    const canDeleteEmailProviders =
        page.props.auth.abilities.deleteEmailProviders;
    const canRestoreEmailProviders =
        page.props.auth.abilities.restoreEmailProviders;

    const handleBulkAction = (
        action: string,
        selectedRows: EmailProviderListItem[],
        clearSelection: () => void,
    ) => {
        if (selectedRows.length === 0) {
            return;
        }

        router.post(
            route('app.masters.email.providers.bulk-action'),
            {
                action,
                ids: selectedRows.map((provider) => provider.id),
            },
            {
                preserveScroll: true,
                onSuccess: () => clearSelection(),
            },
        );
    };

    const gridFilters: DatagridFilter[] = [
        {
            type: 'search',
            name: 'search',
            value: filters.search,
            placeholder: 'Search providers...',
            className: 'lg:min-w-80',
        },
        {
            type: 'date_range',
            name: 'created_at',
            value: filters.created_at,
            label: 'Created',
        },
    ];

    const statusTabs: DatagridTab[] = [
        {
            label: 'All',
            value: 'all',
            count: statistics.total,
            active: filters.status === 'all',
            icon: <ListIcon />,
            countVariant: 'secondary',
        },
        {
            label: 'Active',
            value: 'active',
            count: statistics.active,
            active: filters.status === 'active',
            icon: <CheckCircleIcon />,
            countVariant: 'success',
        },
        {
            label: 'Inactive',
            value: 'inactive',
            count: statistics.inactive,
            active: filters.status === 'inactive',
            icon: <SlashIcon />,
            countVariant: 'warning',
        },
        {
            label: 'Trash',
            value: 'trash',
            count: statistics.trash,
            active: filters.status === 'trash',
            icon: <Trash2Icon />,
            countVariant: 'destructive',
        },
    ];

    const columns: DatagridColumn<EmailProviderListItem>[] = [
        {
            key: 'name',
            header: 'Provider',
            sortable: true,
            cell: (provider) => (
                <div className="flex min-w-0 flex-col gap-1">
                    <Link
                        href={provider.show_url}
                        className="truncate font-medium text-foreground hover:text-primary"
                    >
                        {provider.name}
                    </Link>
                    <span className="truncate text-sm text-muted-foreground">
                        {provider.sender_name || 'No sender name'} ·{' '}
                        {provider.sender_email || 'No sender email'}
                    </span>
                </div>
            ),
        },
        {
            key: 'smtp_host',
            header: 'SMTP Host',
            sortable: true,
        },
        {
            key: 'smtp_encryption',
            header: 'Encryption',
            sortable: true,
            cell: (provider) => provider.smtp_encryption || 'None',
        },
        {
            key: 'order',
            header: 'Order',
            sortable: true,
            headerClassName: 'w-24 text-center',
            cellClassName: 'w-24 text-center',
        },
        {
            key: 'status_label',
            header: 'Status',
            sortable: true,
            sortKey: 'status',
            type: 'badge',
            badgeVariantKey: 'status_badge',
        },
        {
            key: 'updated_at',
            header: 'Updated',
            sortable: true,
            type: 'date',
        },
    ];

    const rowActions = (provider: EmailProviderListItem): DatagridAction[] => {
        if (provider.is_trashed) {
            return [
                ...(canRestoreEmailProviders
                    ? [
                          {
                              label: 'Restore',
                              icon: <RefreshCwIcon />,
                              href: route(
                                  'app.masters.email.providers.restore',
                                  provider.id,
                              ),
                              method: 'PATCH' as const,
                              confirm: `Restore "${provider.name}"?`,
                          },
                      ]
                    : []),
                ...(canDeleteEmailProviders
                    ? [
                          {
                              label: 'Delete Permanently',
                              icon: <Trash2Icon />,
                              href: route(
                                  'app.masters.email.providers.force-delete',
                                  provider.id,
                              ),
                              method: 'DELETE' as const,
                              confirm: `⚠️ Permanently delete "${provider.name}"? This cannot be undone!`,
                              variant: 'destructive' as const,
                          },
                      ]
                    : []),
            ];
        }

        return [
            { label: 'View', href: provider.show_url, icon: <EyeIcon /> },
            ...(canEditEmailProviders
                ? [
                      {
                          label: 'Edit',
                          href: route(
                              'app.masters.email.providers.edit',
                              provider.id,
                          ),
                          icon: <PencilIcon />,
                      },
                  ]
                : []),
            ...(canDeleteEmailProviders
                ? [
                      {
                          label: 'Move to Trash',
                          href: route(
                              'app.masters.email.providers.destroy',
                              provider.id,
                          ),
                          method: 'DELETE' as const,
                          confirm: `Move "${provider.name}" to trash?`,
                          icon: <Trash2Icon />,
                          variant: 'destructive' as const,
                      },
                  ]
                : []),
        ];
    };

    const bulkActions: DatagridBulkAction<EmailProviderListItem>[] = [
        ...(canDeleteEmailProviders
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected providers to trash?',
                      onSelect: (
                          rows: EmailProviderListItem[],
                          clear: () => void,
                      ) => handleBulkAction('delete', rows, clear),
                  },
              ]
            : []),
        ...(canRestoreEmailProviders
            ? [
                  {
                      key: 'bulk-restore',
                      label: 'Restore',
                      icon: <RefreshCwIcon />,
                      confirm: 'Restore selected providers from trash?',
                      onSelect: (
                          rows: EmailProviderListItem[],
                          clear: () => void,
                      ) => handleBulkAction('restore', rows, clear),
                  },
              ]
            : []),
        ...(canDeleteEmailProviders
            ? [
                  {
                      key: 'bulk-force-delete',
                      label: 'Delete Permanently',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm:
                          '⚠️ Permanently delete selected providers? This cannot be undone!',
                      onSelect: (
                          rows: EmailProviderListItem[],
                          clear: () => void,
                      ) => handleBulkAction('force_delete', rows, clear),
                  },
              ]
            : []),
    ];

    const visibleBulkActions =
        filters.status === 'trash'
            ? bulkActions.filter((action) => action.key !== 'bulk-delete')
            : bulkActions.filter((action) => action.key === 'bulk-delete');

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Email Providers"
            description="Manage the SMTP connections your application can send mail through."
            headerActions={
                canAddEmailProviders ? (
                    <Button asChild>
                        <Link
                            href={route('app.masters.email.providers.create')}
                        >
                            <PlusIcon data-icon="inline-start" />
                            Add Provider
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('app.masters.email.providers.index')}
                    rows={emailProviders}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(provider) => provider.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={{
                        sort: filters.sort,
                        direction: filters.direction,
                    }}
                    perPage={{
                        value: filters.per_page,
                        options: [10, 25, 50, 100],
                    }}
                    view={{
                        value: filters.view,
                        storageKey: 'email-providers-datagrid-view',
                    }}
                    renderCard={(provider) => (
                        <div className="flex flex-col gap-4">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0 space-y-1">
                                    <Link
                                        href={provider.show_url}
                                        className="block truncate font-medium text-foreground hover:text-primary"
                                    >
                                        {provider.name}
                                    </Link>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {provider.sender_name ||
                                            'No sender name'}{' '}
                                        ·{' '}
                                        {provider.sender_email ||
                                            'No sender email'}
                                    </p>
                                </div>
                                <Badge
                                    variant={
                                        (provider.status_badge as React.ComponentProps<
                                            typeof Badge
                                        >['variant']) ?? 'outline'
                                    }
                                >
                                    {provider.status_label}
                                </Badge>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        SMTP
                                    </div>
                                    <div className="mt-1 flex items-center gap-2 text-sm text-foreground">
                                        <ServerIcon className="size-4 text-muted-foreground" />
                                        <span className="truncate">
                                            {provider.smtp_host || 'Not set'}
                                        </span>
                                    </div>
                                </div>
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Encryption
                                    </div>
                                    <div className="mt-1 text-sm text-foreground">
                                        {provider.smtp_encryption || 'None'}
                                    </div>
                                </div>
                            </div>

                            {provider.description ? (
                                <p className="line-clamp-2 text-sm text-muted-foreground">
                                    {provider.description}
                                </p>
                            ) : null}
                        </div>
                    )}
                    empty={{
                        icon: <MailIcon className="size-5" />,
                        title: 'No email providers found',
                        description:
                            'Create your first SMTP provider to start delivering messages.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
