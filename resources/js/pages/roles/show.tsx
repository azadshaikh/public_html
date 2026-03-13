import { Link, router } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    ArrowLeftIcon,
    CalendarIcon,
    CheckCircleIcon,
    InfoIcon,
    KeyRoundIcon,
    PencilIcon,
    RefreshCwIcon,
    ShieldAlertIcon,
    ShieldCheckIcon,
    ShieldIcon,
    StickyNoteIcon,
    Trash2Icon,
    UsersIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import { ResourceFeedbackAlerts } from '@/components/resource/resource-feedback-alerts';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/index';
import type { BreadcrumbItem } from '@/types';
import type { PermissionGroup, RolesShowPageProps } from '@/types/role';

// =========================================================================
// CONSTANTS
// =========================================================================

const STATUS_BADGE_VARIANT: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    Active: 'default',
    Inactive: 'secondary',
    Trashed: 'destructive',
};

// =========================================================================
// HELPER COMPONENTS
// =========================================================================

function DetailRow({
    label,
    value,
    icon,
}: {
    label: string;
    value: ReactNode;
    icon?: ReactNode;
}) {
    if (!value) return null;

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

function StatBox({
    label,
    value,
    icon,
}: {
    label: string;
    value: number;
    icon: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center gap-1 rounded-lg border bg-muted/30 px-4 py-3">
            <span className="text-muted-foreground">{icon}</span>
            <span className="text-2xl font-bold text-foreground">{value}</span>
            <span className="text-xs text-muted-foreground">{label}</span>
        </div>
    );
}

function PermissionGroupCard({ group }: { group: PermissionGroup }) {
    return (
        <Card className="h-full">
            <CardHeader className="bg-muted/30 py-3">
                <CardTitle className="flex items-center gap-2 text-sm capitalize">
                    <KeyRoundIcon className="size-4 text-primary" />
                    {group.label}
                    <Badge
                        variant="secondary"
                        className="ml-auto text-[0.65rem]"
                    >
                        {group.permissions.length}
                    </Badge>
                </CardTitle>
            </CardHeader>
            <CardContent className="pt-4">
                <div className="grid grid-cols-2 gap-2">
                    {group.permissions.map((permission) => (
                        <div
                            key={permission.id}
                            className="flex items-center gap-2"
                        >
                            <CheckCircleIcon className="size-3.5 shrink-0 text-green-500" />
                            <span className="text-sm">
                                {permission.display_name}
                            </span>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

// =========================================================================
// COMPONENT
// =========================================================================

export default function RolesShow({
    role,
    permissionGroups,
    status,
    error,
}: RolesShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Roles', href: RoleController.index() },
        { title: role.display_name, href: RoleController.show(role.id) },
    ];

    const handleRestore = () => {
        if (!window.confirm(`Restore "${role.display_name}"?`)) return;

        router.patch(
            RoleController.restore(role.id).url,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const handleDelete = () => {
        if (role.is_system) return;

        if (!window.confirm(`Move "${role.display_name}" to trash?`)) return;

        router.delete(RoleController.destroy(role.id).url, {
            preserveScroll: true,
        });
    };

    const handleForceDelete = () => {
        if (role.is_system) return;

        if (
            !window.confirm(
                `⚠️ Permanently delete "${role.display_name}"? This cannot be undone!`,
            )
        )
            return;

        router.delete(RoleController.forceDelete(role.id).url, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={role.display_name}
            description="View role details and permissions"
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={RoleController.index()}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>

                    {!role.is_trashed && (
                        <Button asChild>
                            <Link href={RoleController.edit(role.id)}>
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <ResourceFeedbackAlerts
                    status={status}
                    statusIcon={<ShieldCheckIcon />}
                    error={error}
                    errorIcon={<ShieldAlertIcon />}
                />

                {/* Role identity header */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-col gap-2">
                                <div className="flex flex-wrap items-center gap-3">
                                    <h2 className="text-xl font-semibold text-foreground">
                                        {role.display_name}
                                    </h2>
                                    <Badge
                                        variant={
                                            STATUS_BADGE_VARIANT[
                                                role.status_label
                                            ] ?? 'outline'
                                        }
                                    >
                                        {role.status_label}
                                    </Badge>
                                    {role.is_system && (
                                        <Badge
                                            variant="outline"
                                            className="border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-600 dark:bg-amber-950 dark:text-amber-400"
                                        >
                                            <ShieldIcon className="mr-0.5 size-3" />
                                            Protected
                                        </Badge>
                                    )}
                                    {role.is_trashed && (
                                        <Badge variant="destructive">
                                            Trashed
                                        </Badge>
                                    )}
                                </div>

                                <code className="w-fit rounded bg-muted px-1.5 py-0.5 text-[0.75rem] text-muted-foreground">
                                    {role.name}
                                </code>
                            </div>

                            {/* Action buttons */}
                            <div className="flex flex-wrap gap-2">
                                {role.is_trashed && (
                                    <>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={handleRestore}
                                        >
                                            <RefreshCwIcon data-icon="inline-start" />
                                            Restore
                                        </Button>
                                        {!role.is_system && (
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={handleForceDelete}
                                            >
                                                <Trash2Icon data-icon="inline-start" />
                                                Delete Permanently
                                            </Button>
                                        )}
                                    </>
                                )}

                                {!role.is_trashed && !role.is_system && (
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={handleDelete}
                                    >
                                        <Trash2Icon data-icon="inline-start" />
                                        Trash
                                    </Button>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Super User Protection Notice */}
                {role.is_system && (
                    <Alert>
                        <InfoIcon className="size-4" />
                        <AlertTitle>This is the Super User role</AlertTitle>
                        <AlertDescription>
                            This role cannot be deleted or deactivated as it is
                            required for system administration.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Trashed Warning Banner */}
                {role.is_trashed && (
                    <Alert variant="destructive">
                        <AlertTriangleIcon className="size-4" />
                        <AlertTitle>This role is in trash</AlertTitle>
                        <AlertDescription>
                            Trashed on{' '}
                            {role.trashed_at_formatted ?? 'unknown date'}.
                            Restore it to make it available again.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Main content grid */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Left Column: Details */}
                    <div className="flex flex-col gap-6 lg:col-span-1">
                        {/* Role Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Role Information</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="divide-y">
                                    <DetailRow
                                        label="Display Name"
                                        value={role.display_name}
                                        icon={
                                            <ShieldCheckIcon className="size-4" />
                                        }
                                    />
                                    <DetailRow
                                        label="System Name"
                                        value={
                                            <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                                {role.name}
                                            </code>
                                        }
                                    />
                                    <DetailRow
                                        label="Guard"
                                        value={
                                            <Badge variant="outline">
                                                {role.guard_name}
                                            </Badge>
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Statistics */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Statistics</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-3 gap-3">
                                    <StatBox
                                        label="Users"
                                        value={role.users_count}
                                        icon={<UsersIcon className="size-4" />}
                                    />
                                    <StatBox
                                        label="Permissions"
                                        value={role.permissions_count}
                                        icon={
                                            <KeyRoundIcon className="size-4" />
                                        }
                                    />
                                    <StatBox
                                        label="Notes"
                                        value={role.notes_count}
                                        icon={
                                            <StickyNoteIcon className="size-4" />
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Audit Log */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Audit Log</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="divide-y">
                                    <DetailRow
                                        label="Created"
                                        value={
                                            <>
                                                <span>
                                                    {role.created_at_formatted}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {' '}
                                                    by {role.created_by}
                                                </span>
                                            </>
                                        }
                                        icon={
                                            <CalendarIcon className="size-4" />
                                        }
                                    />
                                    <DetailRow
                                        label="Updated"
                                        value={
                                            <>
                                                <span>
                                                    {role.updated_at_formatted ??
                                                        'Never'}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {' '}
                                                    by {role.updated_by}
                                                </span>
                                            </>
                                        }
                                        icon={
                                            <CalendarIcon className="size-4" />
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column: Tabbed Content */}
                    <div className="lg:col-span-2">
                        <Tabs defaultValue="permissions">
                            <TabsList variant="line">
                                <TabsTrigger value="permissions">
                                    Permissions ({role.permissions_count})
                                </TabsTrigger>
                                <TabsTrigger value="notes">
                                    Notes ({role.notes_count})
                                </TabsTrigger>
                            </TabsList>

                            {/* Permissions Tab */}
                            <TabsContent value="permissions">
                                {permissionGroups.length > 0 ? (
                                    <div className="grid gap-4 md:grid-cols-2">
                                        {permissionGroups.map((group) => (
                                            <PermissionGroupCard
                                                key={group.group}
                                                group={group}
                                            />
                                        ))}
                                    </div>
                                ) : (
                                    <Card>
                                        <CardContent className="py-12 text-center">
                                            <KeyRoundIcon className="mx-auto mb-3 size-10 text-muted-foreground/25" />
                                            <p className="text-sm text-muted-foreground">
                                                No permissions assigned to this
                                                role.
                                            </p>
                                        </CardContent>
                                    </Card>
                                )}
                            </TabsContent>

                            {/* Notes Tab */}
                            <TabsContent value="notes">
                                <Card>
                                    <CardContent className="py-12 text-center">
                                        <StickyNoteIcon className="mx-auto mb-3 size-10 text-muted-foreground/25" />
                                        <p className="text-sm text-muted-foreground">
                                            Notes feature coming soon.
                                        </p>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
