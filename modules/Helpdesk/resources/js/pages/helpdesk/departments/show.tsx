import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    BuildingIcon,
    PencilIcon,
    RefreshCwIcon,
    TicketIcon,
    Trash2Icon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { DepartmentShowPageProps } from '../../../types/helpdesk';

function DetailRow({
    label,
    value,
    icon,
}: {
    label: string;
    value: ReactNode;
    icon?: ReactNode;
}) {
    if (value === null || value === undefined || value === '') return null;

    return (
        <div className="flex items-start gap-3 py-2">
            {icon && (
                <span className="mt-0.5 text-muted-foreground">{icon}</span>
            )}
            <div className="flex min-w-0 flex-col gap-0.5">
                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </span>
                <span className="text-sm text-foreground">{value}</span>
            </div>
        </div>
    );
}

function StatCard({
    label,
    value,
    icon,
}: {
    label: string;
    value: number;
    icon: ReactNode;
}) {
    return (
        <Card>
            <CardContent className="flex items-center gap-4 p-4">
                <div className="flex size-10 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                    {icon}
                </div>
                <div>
                    <div className="text-2xl font-bold">{value}</div>
                    <div className="text-sm text-muted-foreground">{label}</div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function DepartmentsShow({
    department,
    statistics,
}: DepartmentShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEdit =
        page.props.auth.abilities.editHelpdeskDepartments;
    const canDelete =
        page.props.auth.abilities.deleteHelpdeskDepartments;
    const canRestore =
        page.props.auth.abilities.restoreHelpdeskDepartments;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Departments', href: route('helpdesk.departments.index') },
        {
            title: department.name,
            href: route('helpdesk.departments.show', department.id),
        },
    ];

    const handleRestore = () => {
        if (!window.confirm(`Restore "${department.name}"?`)) return;
        router.patch(
            route('helpdesk.departments.restore', department.id),
            {},
            { preserveScroll: true },
        );
    };

    const handleDelete = () => {
        if (!window.confirm(`Move "${department.name}" to trash?`)) return;
        router.delete(
            route('helpdesk.departments.destroy', department.id),
            { preserveScroll: true },
        );
    };

    const handleForceDelete = () => {
        if (
            !window.confirm(
                `⚠️ Permanently delete "${department.name}"? This cannot be undone!`,
            )
        )
            return;
        router.delete(
            route('helpdesk.departments.force-delete', department.id),
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={department.name}
            description="Department details"
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('helpdesk.departments.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>

                    {department.is_trashed && canRestore && (
                        <Button variant="outline" onClick={handleRestore}>
                            <RefreshCwIcon data-icon="inline-start" />
                            Restore
                        </Button>
                    )}

                    {!department.is_trashed && canEdit && (
                        <Button asChild>
                            <Link
                                href={route(
                                    'helpdesk.departments.edit',
                                    department.id,
                                )}
                            >
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {department.is_trashed && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        This department is in the trash.
                        {department.deleted_at &&
                            ` Deleted on ${department.deleted_at}.`}
                    </div>
                )}

                {/* Statistics */}
                <div className="grid gap-4 sm:grid-cols-2">
                    <StatCard
                        label="Total Tickets"
                        value={statistics.tickets}
                        icon={<TicketIcon className="size-5" />}
                    />
                    <StatCard
                        label="Open Tickets"
                        value={statistics.open_tickets}
                        icon={<TicketIcon className="size-5" />}
                    />
                </div>

                {/* Details */}
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Department Details</CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y">
                            <DetailRow
                                label="Name"
                                value={department.name}
                                icon={
                                    <BuildingIcon className="size-4" />
                                }
                            />
                            <DetailRow
                                label="Description"
                                value={department.description || '—'}
                            />
                            <DetailRow
                                label="Department Head"
                                value={department.department_head_name}
                            />
                            <DetailRow
                                label="Created"
                                value={department.created_at}
                            />
                            <DetailRow
                                label="Last Updated"
                                value={department.updated_at}
                            />
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Status</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div>
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Visibility
                                    </span>
                                    <div className="mt-1">
                                        <Badge
                                            variant={
                                                department.visibility ===
                                                'public'
                                                    ? 'info'
                                                    : 'secondary'
                                            }
                                        >
                                            {department.visibility_label}
                                        </Badge>
                                    </div>
                                </div>
                                <div>
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Status
                                    </span>
                                    <div className="mt-1">
                                        <Badge
                                            variant={
                                                department.is_trashed
                                                    ? 'destructive'
                                                    : department.status ===
                                                        'active'
                                                      ? 'success'
                                                      : 'secondary'
                                            }
                                        >
                                            {department.is_trashed
                                                ? 'Trashed'
                                                : department.status_label}
                                        </Badge>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Danger zone */}
                        {((!department.is_trashed && canDelete) ||
                            (department.is_trashed && canDelete)) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-destructive">
                                        Danger Zone
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {!department.is_trashed && canDelete && (
                                        <Button
                                            variant="outline"
                                            className="w-full"
                                            onClick={handleDelete}
                                        >
                                            <Trash2Icon data-icon="inline-start" />
                                            Move to Trash
                                        </Button>
                                    )}
                                    {department.is_trashed && canDelete && (
                                        <Button
                                            variant="destructive"
                                            className="w-full"
                                            onClick={handleForceDelete}
                                        >
                                            <Trash2Icon data-icon="inline-start" />
                                            Delete Permanently
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
