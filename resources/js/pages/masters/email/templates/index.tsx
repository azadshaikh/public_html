import { Link, router, usePage } from '@inertiajs/react';
import {
    CheckCircleIcon,
    EyeIcon,
    FileTextIcon,
    ListIcon,
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
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
    EmailTemplateListItem,
    EmailTemplatesIndexPageProps,
} from '@/types/email';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    {
        title: 'Email Templates',
        href: route('app.masters.email.templates.index'),
    },
];

export default function EmailTemplatesIndex({
    emailTemplates,
    statistics,
    providerOptions,
    filters,
}: EmailTemplatesIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddEmailTemplates = page.props.auth.abilities.addEmailTemplates;
    const canEditEmailTemplates = page.props.auth.abilities.editEmailTemplates;
    const canDeleteEmailTemplates = page.props.auth.abilities.deleteEmailTemplates;
    const canRestoreEmailTemplates =
        page.props.auth.abilities.restoreEmailTemplates;

    const handleBulkAction = (
        action: string,
        selectedRows: EmailTemplateListItem[],
        clearSelection: () => void,
    ) => {
        if (selectedRows.length === 0) {
            return;
        }

        router.post(
            route('app.masters.email.templates.bulk-action'),
            {
                action,
                ids: selectedRows.map((template) => template.id),
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
            placeholder: 'Search templates...',
            className: 'lg:min-w-80',
        },
        {
            type: 'select',
            name: 'provider_id',
            value: filters.provider_id,
            options: [{ value: '', label: 'All providers' }, ...providerOptions],
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

    const columns: DatagridColumn<EmailTemplateListItem>[] = [
        {
            key: 'name',
            header: 'Template',
            sortable: true,
            cell: (template) => (
                <div className="flex min-w-0 flex-col gap-1">
                    <Link
                        href={template.show_url}
                        className="truncate font-medium text-foreground hover:text-primary"
                    >
                        {template.name}
                    </Link>
                    <span className="truncate text-sm text-muted-foreground">
                        {template.subject}
                    </span>
                </div>
            ),
        },
        {
            key: 'provider_name',
            header: 'Provider',
            sortable: true,
        },
        {
            key: 'is_raw',
            header: 'Format',
            sortable: true,
            cell: (template) => (template.is_raw ? 'HTML' : 'Text'),
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
            key: 'created_at',
            header: 'Created',
            sortable: true,
            type: 'date',
        },
    ];

    const rowActions = (
        template: EmailTemplateListItem,
    ): DatagridAction[] => {
        if (template.is_trashed) {
            return [
                ...(canRestoreEmailTemplates
                    ? [
                          {
                              label: 'Restore',
                              icon: <RefreshCwIcon />,
                              href: route(
                                  'app.masters.email.templates.restore',
                                  template.id,
                              ),
                              method: 'PATCH' as const,
                              confirm: `Restore "${template.name}"?`,
                          },
                      ]
                    : []),
                ...(canDeleteEmailTemplates
                    ? [
                          {
                              label: 'Delete Permanently',
                              icon: <Trash2Icon />,
                              href: route(
                                  'app.masters.email.templates.force-delete',
                                  template.id,
                              ),
                              method: 'DELETE' as const,
                              confirm: `⚠️ Permanently delete "${template.name}"? This cannot be undone!`,
                              variant: 'destructive' as const,
                          },
                      ]
                    : []),
            ];
        }

        return [
            { label: 'View', href: template.show_url, icon: <EyeIcon /> },
            ...(canEditEmailTemplates
                ? [
                      {
                          label: 'Edit',
                          href: route(
                              'app.masters.email.templates.edit',
                              template.id,
                          ),
                          icon: <PencilIcon />,
                      },
                  ]
                : []),
            ...(canDeleteEmailTemplates
                ? [
                      {
                          label: 'Move to Trash',
                          href: route(
                              'app.masters.email.templates.destroy',
                              template.id,
                          ),
                          method: 'DELETE' as const,
                          confirm: `Move "${template.name}" to trash?`,
                          icon: <Trash2Icon />,
                          variant: 'destructive' as const,
                      },
                  ]
                : []),
        ];
    };

    const bulkActions: DatagridBulkAction<EmailTemplateListItem>[] = [
        ...(canDeleteEmailTemplates
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected templates to trash?',
                      onSelect: (
                          rows: EmailTemplateListItem[],
                          clear: () => void,
                      ) =>
                          handleBulkAction('delete', rows, clear),
                  },
              ]
            : []),
        ...(canRestoreEmailTemplates
            ? [
                  {
                      key: 'bulk-restore',
                      label: 'Restore',
                      icon: <RefreshCwIcon />,
                      confirm: 'Restore selected templates from trash?',
                      onSelect: (
                          rows: EmailTemplateListItem[],
                          clear: () => void,
                      ) =>
                          handleBulkAction('restore', rows, clear),
                  },
              ]
            : []),
        ...(canDeleteEmailTemplates
            ? [
                  {
                      key: 'bulk-force-delete',
                      label: 'Delete Permanently',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm:
                          '⚠️ Permanently delete selected templates? This cannot be undone!',
                      onSelect: (
                          rows: EmailTemplateListItem[],
                          clear: () => void,
                      ) =>
                          handleBulkAction('force_delete', rows, clear),
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
            title="Email Templates"
            description="Manage reusable subjects, message bodies, and delivery mappings."
            headerActions={
                canAddEmailTemplates ? (
                    <Button asChild>
                        <Link href={route('app.masters.email.templates.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add Template
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('app.masters.email.templates.index')}
                    rows={emailTemplates}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(template) => template.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={{
                        sort: filters.sort,
                        direction: filters.direction,
                    }}
                    perPage={{ value: filters.per_page, options: [10, 25, 50, 100] }}
                    view={{
                        value: filters.view,
                        storageKey: 'email-templates-datagrid-view',
                    }}
                    renderCard={(template) => (
                        <div className="flex flex-col gap-4">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0 space-y-1">
                                    <Link
                                        href={template.show_url}
                                        className="block truncate font-medium text-foreground hover:text-primary"
                                    >
                                        {template.name}
                                    </Link>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {template.subject}
                                    </p>
                                </div>
                                <Badge
                                    variant={
                                        (template.status_badge as React.ComponentProps<typeof Badge>['variant']) ??
                                        'outline'
                                    }
                                >
                                    {template.status_label}
                                </Badge>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Provider
                                    </div>
                                    <div className="mt-1 text-sm text-foreground">
                                        {template.provider_name || 'Not assigned'}
                                    </div>
                                </div>
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Format
                                    </div>
                                    <div className="mt-1 text-sm text-foreground">
                                        {template.is_raw ? 'Raw HTML' : 'Plain text'}
                                    </div>
                                </div>
                            </div>

                            <p className="line-clamp-3 text-sm text-muted-foreground">
                                {template.message}
                            </p>
                        </div>
                    )}
                    empty={{
                        icon: <FileTextIcon className="size-5" />,
                        title: 'No email templates found',
                        description:
                            'Create a template so your application can send consistent messages.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
