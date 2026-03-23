import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, PencilIcon, RefreshCwIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { TaxShowDetail, TaxShowPageProps } from '../../../types/billing';

function DetailRow({ label, value }: { label: string; value: ReactNode }) {
    if (value === null || value === undefined || value === '') return null;
    return (
        <div className="flex items-start gap-3 py-2">
            <div className="flex min-w-0 flex-col gap-0.5">
                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{label}</span>
                <span className="text-sm text-foreground">{value}</span>
            </div>
        </div>
    );
}

export default function TaxesShow({ tax }: TaxShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEdit = page.props.auth.abilities.editTaxes;
    const canDelete = page.props.auth.abilities.deleteTaxes;
    const canRestore = page.props.auth.abilities.restoreTaxes;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Billing' },
        { title: 'Taxes', href: route('app.billing.taxes.index') },
        { title: tax.name, href: route('app.billing.taxes.show', tax.id) },
    ];

    const hasLocation = tax.country || tax.state || tax.postal_code;
    const hasValidity = tax.effective_from || tax.effective_to;

    const handleRestore = () => {
        if (!window.confirm(`Restore tax "${tax.name}"?`)) return;
        router.patch(route('app.billing.taxes.restore', tax.id), {}, { preserveScroll: true });
    };

    const handleDelete = () => {
        if (!window.confirm(`Move tax "${tax.name}" to trash?`)) return;
        router.delete(route('app.billing.taxes.destroy', tax.id), { preserveScroll: true });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={tax.name}
            description="Tax rate details"
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('app.billing.taxes.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>
                    {tax.is_trashed && canRestore && (
                        <Button variant="outline" onClick={handleRestore}>
                            <RefreshCwIcon data-icon="inline-start" />
                            Restore
                        </Button>
                    )}
                    {!tax.is_trashed && canEdit && (
                        <Button asChild>
                            <Link href={route('app.billing.taxes.edit', tax.id)}>
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {tax.is_trashed && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        This tax rate is in the trash.
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Tax Details</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow label="Name" value={tax.name} />
                                <DetailRow label="Code" value={tax.code} />
                                <DetailRow label="Description" value={tax.description} />
                                <DetailRow label="Type" value={tax.type} />
                                <DetailRow label="Rate" value={tax.formatted_rate} />
                                <DetailRow label="Compound" value={tax.is_compound ? 'Yes' : 'No'} />
                                <DetailRow label="Priority" value={tax.priority} />
                            </CardContent>
                        </Card>

                        {hasLocation && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Location</CardTitle>
                                </CardHeader>
                                <CardContent className="divide-y">
                                    <DetailRow label="Country" value={tax.country} />
                                    <DetailRow label="State" value={tax.state} />
                                    <DetailRow label="Postal Code" value={tax.postal_code} />
                                </CardContent>
                            </Card>
                        )}

                        {hasValidity && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Validity</CardTitle>
                                </CardHeader>
                                <CardContent className="divide-y">
                                    <DetailRow label="Effective From" value={tax.effective_from} />
                                    <DetailRow label="Effective To" value={tax.effective_to} />
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Status</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Status</span>
                                    <div className="mt-1"><Badge variant={tax.status_badge as Parameters<typeof Badge>[0]['variant']}>{tax.status_label}</Badge></div>
                                </div>
                                <DetailRow label="Created" value={tax.created_at_formatted} />
                                <DetailRow label="Updated" value={tax.updated_at_formatted} />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
