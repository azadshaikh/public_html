import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, PencilIcon, RefreshCwIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { CreditShowPageProps } from '../../../types/billing';

function DetailRow({ label, value, icon }: { label: string; value: ReactNode; icon?: ReactNode }) {
    if (value === null || value === undefined || value === '') return null;
    return (
        <div className="flex items-start gap-3 py-2">
            {icon && <span className="mt-0.5 text-muted-foreground">{icon}</span>}
            <div className="flex min-w-0 flex-col gap-0.5">
                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{label}</span>
                <span className="text-sm text-foreground">{value}</span>
            </div>
        </div>
    );
}

export default function CreditsShow({ credit }: CreditShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEdit = page.props.auth.abilities.editCredits;
    const canDelete = page.props.auth.abilities.deleteCredits;
    const canRestore = page.props.auth.abilities.restoreCredits;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Billing' },
        { title: 'Credits', href: route('app.billing.credits.index') },
        { title: credit.credit_number, href: route('app.billing.credits.show', credit.id) },
    ];

    const handleRestore = () => {
        if (!window.confirm(`Restore credit "${credit.credit_number}"?`)) return;
        router.patch(route('app.billing.credits.restore', credit.id), {}, { preserveScroll: true });
    };

    const handleDelete = () => {
        if (!window.confirm(`Move credit "${credit.credit_number}" to trash?`)) return;
        router.delete(route('app.billing.credits.destroy', credit.id), { preserveScroll: true });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={credit.credit_number}
            description="Credit details"
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('app.billing.credits.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>
                    {credit.is_trashed && canRestore && (
                        <Button variant="outline" onClick={handleRestore}>
                            <RefreshCwIcon data-icon="inline-start" />
                            Restore
                        </Button>
                    )}
                    {!credit.is_trashed && canEdit && (
                        <Button asChild>
                            <Link href={route('app.billing.credits.edit', credit.id)}>
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {credit.is_trashed && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        This credit is in the trash.
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Credit Details</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow label="Credit Number" value={credit.credit_number} />
                                <DetailRow label="Reference" value={credit.reference} />
                                <DetailRow label="Customer" value={credit.customer_display} />
                                <DetailRow label="Amount" value={credit.formatted_amount} />
                                <DetailRow label="Amount Used" value={credit.amount_used !== undefined ? `${credit.currency} ${Number(credit.amount_used).toFixed(2)}` : null} />
                                <DetailRow label="Amount Remaining" value={credit.formatted_remaining} />
                                <DetailRow label="Currency" value={credit.currency} />
                                <DetailRow label="Expires At" value={credit.expires_at} />
                            </CardContent>
                        </Card>

                        {(credit.reason || credit.notes) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Additional Info</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {credit.reason && (
                                        <div>
                                            <h4 className="text-xs font-medium tracking-wide text-muted-foreground uppercase mb-1">Reason</h4>
                                            <p className="text-sm whitespace-pre-wrap">{credit.reason}</p>
                                        </div>
                                    )}
                                    {credit.notes && (
                                        <div>
                                            <h4 className="text-xs font-medium tracking-wide text-muted-foreground uppercase mb-1">Notes</h4>
                                            <p className="text-sm whitespace-pre-wrap">{credit.notes}</p>
                                        </div>
                                    )}
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
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Type</span>
                                    <div className="mt-1"><Badge variant={credit.type_badge as Parameters<typeof Badge>[0]['variant']}>{credit.type_label}</Badge></div>
                                </div>
                                <div>
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Status</span>
                                    <div className="mt-1"><Badge variant={credit.status_badge as Parameters<typeof Badge>[0]['variant']}>{credit.status_label}</Badge></div>
                                </div>
                                <DetailRow label="Created" value={credit.created_at_formatted} />
                                <DetailRow label="Updated" value={credit.updated_at_formatted} />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
