import { Link, router, usePage } from '@inertiajs/react';
import {
    ClipboardListIcon,
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
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
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import { mapStatusTab } from '../../../lib/helpers';
import type { FormIndexPageProps, FormListItem } from '../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Forms', href: route('cms.form.index') },
];

export default function FormsIndex({ config, rows, filters, statistics }: FormIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddForms = page.props.auth.abilities.addCmsForms;
    const canEditForms = page.props.auth.abilities.editCmsForms;
    const canDeleteForms = page.props.auth.abilities.deleteCmsForms;
    const canRestoreForms = page.props.auth.abilities.restoreCmsForms;

    const handleBulkAction = (action: string, selected: FormListItem[], clearSelection: () => void) => {
        if (selected.length === 0) return;
        router.post(route('cms.form.bulk-action'), { action, ids: selected.map((f) => f.id), status: filters.status }, { preserveScroll: true, onSuccess: () => clearSelection() });
    };

    const gridFilters: DatagridFilter[] = [
        { type: 'search', name: 'search', value: filters.search, placeholder: 'Search forms...', className: 'lg:min-w-80' },
    ];

    const statusTabs: DatagridTab[] = config.statusTabs.map((tab) => mapStatusTab(tab, statistics, filters.status));

    const columns: DatagridColumn<FormListItem>[] = [
        {
            key: 'title', header: 'Form', sortable: true,
            cell: (form) => (
                <Link href={form.show_url} className="font-medium text-foreground hover:opacity-80">
                    {form.title}
                </Link>
            ),
        },
        { key: 'template_label', header: 'Template', headerClassName: 'w-32 text-center', cellClassName: 'w-32 text-center', type: 'badge', sortable: true, sortKey: 'template' },
        { key: 'submissions_count', header: 'Submissions', headerClassName: 'w-28 text-right', cellClassName: 'w-28 text-right tabular-nums text-sm text-muted-foreground', sortable: true },
        { key: 'conversion_rate_display', header: 'Conversion', headerClassName: 'w-28 text-right', cellClassName: 'w-28 text-right tabular-nums text-sm text-muted-foreground', sortable: true, sortKey: 'conversion_rate' },
        { key: 'is_active_label', header: 'Active', headerClassName: 'w-24 text-center', cellClassName: 'w-24 text-center', type: 'badge', sortable: true, sortKey: 'is_active' },
        { key: 'status_label', header: 'Status', headerClassName: 'w-28 text-center', cellClassName: 'w-28 text-center', type: 'badge', sortable: true, sortKey: 'status' },
        { key: 'created_at', header: 'Created', headerClassName: 'w-32', cellClassName: 'w-32 text-sm text-muted-foreground', sortable: true },
    ];

    const rowActions = (form: FormListItem): DatagridAction[] => {
        if (form.is_trashed) {
            return [
                ...(canRestoreForms ? [{ label: 'Restore', icon: <RefreshCwIcon />, href: route('cms.form.restore', form.id), method: 'PATCH' as const, confirm: `Restore "${form.title}"?` }] : []),
                ...(canDeleteForms ? [{ label: 'Delete Permanently', icon: <Trash2Icon />, href: route('cms.form.force-delete', form.id), method: 'DELETE' as const, confirm: `⚠️ Permanently delete "${form.title}"?`, variant: 'destructive' as const }] : []),
            ];
        }
        return [
            ...(canEditForms ? [{ label: 'Edit', href: route('cms.form.edit', form.id), icon: <PencilIcon /> }] : []),
            ...(canDeleteForms ? [{ label: 'Move to Trash', href: route('cms.form.destroy', form.id), method: 'DELETE' as const, confirm: `Move "${form.title}" to trash?`, icon: <Trash2Icon />, variant: 'destructive' as const }] : []),
        ];
    };

    const bulkActions: DatagridBulkAction<FormListItem>[] = [
        ...(canDeleteForms ? [{ key: 'bulk-delete', label: 'Move to Trash', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: 'Move selected forms to trash?', onSelect: (items: FormListItem[], clear: () => void) => handleBulkAction('delete', items, clear) }] : []),
        ...(canRestoreForms ? [{ key: 'bulk-restore', label: 'Restore', icon: <RefreshCwIcon />, confirm: 'Restore selected forms?', onSelect: (items: FormListItem[], clear: () => void) => handleBulkAction('restore', items, clear) }] : []),
        ...(canDeleteForms ? [{ key: 'bulk-force-delete', label: 'Delete Permanently', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: '⚠️ Permanently delete selected forms?', onSelect: (items: FormListItem[], clear: () => void) => handleBulkAction('force_delete', items, clear) }] : []),
    ];

    const visibleBulkActions = filters.status === 'trash'
        ? bulkActions.filter((a) => a.key !== 'bulk-delete')
        : bulkActions.filter((a) => a.key === 'bulk-delete');

    return (
        <AppLayout breadcrumbs={breadcrumbs} title="Forms" description="Manage contact and submission forms"
            headerActions={canAddForms ? (<Button asChild><Link href={route('cms.form.create')}><PlusIcon data-icon="inline-start" />Add Form</Link></Button>) : undefined}>
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('cms.form.index')}
                    rows={rows}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(form) => form.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={{ sort: filters.sort, direction: filters.direction }}
                    perPage={{ value: filters.per_page, options: [10, 25, 50, 100] }}
                    empty={{ icon: <ClipboardListIcon />, title: 'No forms found', description: 'Try a different filter or create the first form.' }}
                />
            </div>
        </AppLayout>
    );
}
