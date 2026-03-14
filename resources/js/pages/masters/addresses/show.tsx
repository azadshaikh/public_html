import { Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    ArrowLeftIcon,
    BuildingIcon,
    CalendarIcon,
    MapPinIcon,
    PencilIcon,
    PhoneIcon,
    RefreshCwIcon,
    Trash2Icon,
    UserIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { AddressShowPageProps } from '@/types/address';

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

// =========================================================================
// COMPONENT
// =========================================================================

export default function AddressesShow({ address }: AddressShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEditAddresses = page.props.auth.abilities.editAddresses;
    const canDeleteAddresses = page.props.auth.abilities.deleteAddresses;
    const canRestoreAddresses = page.props.auth.abilities.restoreAddresses;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Addresses', href: route('app.masters.addresses.index') },
        {
            title: address.full_name ?? `Address #${address.id}`,
            href: address.show_url,
        },
    ];

    const handleRestore = () => {
        if (!window.confirm('Restore this address?')) return;

        router.patch(route('app.masters.addresses.restore', address.id), {}, { preserveScroll: true });
    };

    const handleDelete = () => {
        if (!window.confirm('Move this address to trash?')) return;

        router.delete(route('app.masters.addresses.destroy', address.id), { preserveScroll: true });
    };

    const handleForceDelete = () => {
        if (!window.confirm('⚠️ Permanently delete this address? This cannot be undone!')) return;

        router.delete(route('app.masters.addresses.force-delete', address.id), { preserveScroll: true });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={address.full_name ?? `Address #${address.id}`}
            description="View address details"
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('app.masters.addresses.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>

                    {!address.is_trashed && canEditAddresses && (
                        <Button asChild>
                            <Link href={address.edit_url}>
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {/* Identity header */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-col gap-2">
                                <div className="flex flex-wrap items-center gap-3">
                                    <h2 className="text-xl font-semibold text-foreground">
                                        {address.full_name ?? address.address1}
                                    </h2>
                                    <Badge
                                        variant={
                                            (address.type_class as React.ComponentProps<typeof Badge>['variant']) ??
                                            'outline'
                                        }
                                    >
                                        {address.type_label}
                                    </Badge>
                                    {address.is_primary && (
                                        <Badge
                                            variant={
                                                (address.primary_class as React.ComponentProps<typeof Badge>['variant']) ??
                                                'secondary'
                                            }
                                        >
                                            {address.primary_label}
                                        </Badge>
                                    )}
                                    {address.is_trashed && (
                                        <Badge variant="destructive">
                                            Trashed
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {address.formatted_address}
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {address.is_trashed && canRestoreAddresses && (
                                    <>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={handleRestore}
                                        >
                                            <RefreshCwIcon data-icon="inline-start" />
                                            Restore
                                        </Button>
                                        {canDeleteAddresses && (
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

                                {!address.is_trashed && canDeleteAddresses && (
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

                {/* Trashed Warning Banner */}
                {address.is_trashed && (
                    <Alert variant="destructive">
                        <AlertTriangleIcon className="size-4" />
                        <AlertTitle>This address is in trash</AlertTitle>
                        <AlertDescription>
                            Restore it to make it available again.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Main content */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Left Column */}
                    <div className="flex flex-col gap-6 lg:col-span-1">
                        {/* Contact Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Contact</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="divide-y">
                                    <DetailRow
                                        label="Full Name"
                                        value={address.full_name}
                                        icon={<UserIcon className="size-4" />}
                                    />
                                    <DetailRow
                                        label="Company"
                                        value={address.company}
                                        icon={<BuildingIcon className="size-4" />}
                                    />
                                    <DetailRow
                                        label="Phone"
                                        value={
                                            address.phone
                                                ? `${address.phone_code ? `+${address.phone_code} ` : ''}${address.phone}`
                                                : null
                                        }
                                        icon={<PhoneIcon className="size-4" />}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Location Details */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Location</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="divide-y">
                                    <DetailRow
                                        label="Address Line 1"
                                        value={address.address1}
                                        icon={<MapPinIcon className="size-4" />}
                                    />
                                    <DetailRow
                                        label="Address Line 2"
                                        value={address.address2}
                                    />
                                    <DetailRow
                                        label="Landmark"
                                        value={address.address3}
                                    />
                                    <DetailRow
                                        label="City"
                                        value={address.city}
                                    />
                                    <DetailRow
                                        label="State"
                                        value={
                                            address.state
                                                ? `${address.state}${address.state_code ? ` (${address.state_code})` : ''}`
                                                : null
                                        }
                                    />
                                    <DetailRow
                                        label="Country"
                                        value={
                                            address.country_name
                                                ? `${address.country_name} (${address.country_code})`
                                                : address.country_code
                                        }
                                    />
                                    <DetailRow
                                        label="ZIP / Postal Code"
                                        value={address.zip}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column */}
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        {/* Flags */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                    <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                        <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Type
                                        </div>
                                        <div className="mt-1">
                                            <Badge
                                                variant={
                                                    (address.type_class as React.ComponentProps<typeof Badge>['variant']) ??
                                                    'outline'
                                                }
                                            >
                                                {address.type_label}
                                            </Badge>
                                        </div>
                                    </div>
                                    <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                        <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Primary
                                        </div>
                                        <div className="mt-1">
                                            <Badge
                                                variant={
                                                    (address.primary_class as React.ComponentProps<typeof Badge>['variant']) ??
                                                    'outline'
                                                }
                                            >
                                                {address.primary_label}
                                            </Badge>
                                        </div>
                                    </div>
                                    <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                        <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Verified
                                        </div>
                                        <div className="mt-1">
                                            <Badge
                                                variant={
                                                    (address.verified_class as React.ComponentProps<typeof Badge>['variant']) ??
                                                    'outline'
                                                }
                                            >
                                                {address.verified_label}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Addressable */}
                        {address.addressable_type && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Linked To</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="divide-y">
                                        <DetailRow
                                            label="Entity Type"
                                            value={
                                                <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                                    {address.addressable_type}
                                                </code>
                                            }
                                        />
                                        <DetailRow
                                            label="Entity"
                                            value={address.addressable_label ?? `#${address.addressable_id}`}
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Coordinates */}
                        {address.has_coordinates && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Coordinates</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="divide-y">
                                        <DetailRow
                                            label="Latitude"
                                            value={address.latitude}
                                        />
                                        <DetailRow
                                            label="Longitude"
                                            value={address.longitude}
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Audit */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Audit</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="divide-y">
                                    <DetailRow
                                        label="Created"
                                        value={address.created_at_formatted ?? address.created_at}
                                        icon={<CalendarIcon className="size-4" />}
                                    />
                                    <DetailRow
                                        label="Last Updated"
                                        value={address.updated_at_formatted ?? address.updated_at}
                                        icon={<CalendarIcon className="size-4" />}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
