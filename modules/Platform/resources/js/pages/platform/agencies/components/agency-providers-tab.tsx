import { Link } from '@inertiajs/react';
import { ShieldCheckIcon, Trash2Icon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { AgencyProviderItem, AgencyShowData } from '../../../../types/platform';
import {
    AssociationManagementDialog,
    mergeManagementOptions,
} from './agency-association-management-dialog';
import type { ManagementOption } from './agency-association-management-dialog';
import type { ConfirmState } from './show-shared';
import {
    formatRelationStatus,
    requestJson,
    useAgencyRelationAction,
} from './show-shared';

type AgencyProvidersTabProps = {
    agency: AgencyShowData;
    dnsProviders: AgencyProviderItem[];
    cdnProviders: AgencyProviderItem[];
    canEdit: boolean;
    openConfirm: (
        title: string,
        description: string,
        confirmLabel: string,
        action: () => void,
        tone?: ConfirmState['tone'],
    ) => void;
};

type ProviderOptionsResponse = {
    success: boolean;
    providers: AgencyProviderItem[];
};

function toProviderOption(provider: AgencyProviderItem): ManagementOption {
    return {
        value: provider.id,
        label: provider.vendor_label
            ? `${provider.name} (${provider.vendor_label})`
            : provider.name,
        description: provider.status_label ?? undefined,
    };
}

function AgencyProviderSection({
    agency,
    title,
    description,
    manageLabel,
    emptyMessage,
    items,
    canEdit,
    availableRouteName,
    attachRouteName,
    setPrimaryRouteName,
    providerKind,
    openConfirm,
}: {
    agency: AgencyShowData;
    title: string;
    description: string;
    manageLabel: string;
    emptyMessage: string;
    items: AgencyProviderItem[];
    canEdit: boolean;
    availableRouteName:
        | 'platform.agencies.available-dns-providers'
        | 'platform.agencies.available-cdn-providers';
    attachRouteName:
        | 'platform.agencies.dns-providers.attach'
        | 'platform.agencies.cdn-providers.attach';
    setPrimaryRouteName:
        | 'platform.agencies.dns-providers.set-primary'
        | 'platform.agencies.cdn-providers.set-primary';
    providerKind: 'DNS' | 'CDN';
    openConfirm: AgencyProvidersTabProps['openConfirm'];
}) {
    const relationAction = useAgencyRelationAction();
    const [manageOpen, setManageOpen] = useState(false);
    const [loadingOptions, setLoadingOptions] = useState(false);
    const [availableProviders, setAvailableProviders] = useState<
        AgencyProviderItem[]
    >([]);
    const [selectedProviderIds, setSelectedProviderIds] = useState<number[]>([]);
    const [primaryProviderId, setPrimaryProviderId] = useState('');

    useEffect(() => {
        if (!manageOpen) {
            return;
        }

        if (!canEdit || agency.is_trashed) {
            return;
        }

        const controller = new AbortController();

        void requestJson<ProviderOptionsResponse>(route(availableRouteName, agency.id), {
            signal: controller.signal,
        })
            .then((payload) => {
                setAvailableProviders(payload.providers);
            })
            .catch((error) => {
                if (controller.signal.aborted) {
                    return;
                }

                showAppToast({
                    variant: 'error',
                    title:
                        error instanceof Error
                            ? error.message
                            : `Failed to load available ${providerKind} providers.`,
                });
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoadingOptions(false);
                }
            });

        return () => controller.abort();
    }, [
        agency.id,
        agency.is_trashed,
        availableRouteName,
        canEdit,
        items,
        manageOpen,
        providerKind,
    ]);

    const providerOptions = mergeManagementOptions(
        items.map(toProviderOption),
        availableProviders.map(toProviderOption),
    );

    function handleManageOpenChange(open: boolean): void {
        if (open) {
            const selectedIds = items.map((item) => item.id);
            const currentPrimary =
                items.find((item) => item.is_primary)?.id ?? null;

            setSelectedProviderIds(selectedIds);
            setPrimaryProviderId(currentPrimary ? String(currentPrimary) : '');
            setLoadingOptions(canEdit && !agency.is_trashed);
        }

        setManageOpen(open);
    }

    function handleSelectedProviderIdsChange(values: number[]): void {
        setSelectedProviderIds(values);

        if (primaryProviderId && !values.includes(Number(primaryProviderId))) {
            setPrimaryProviderId('');
        }
    }

    function saveProviders(): void {
        void relationAction.perform('POST', route(attachRouteName, agency.id), {
            body: {
                provider_ids: selectedProviderIds,
                primary_provider_id: primaryProviderId
                    ? Number(primaryProviderId)
                    : null,
            },
            reloadOnly: ['agency', 'dnsProviders', 'cdnProviders', 'activities'],
            onSuccess: () => setManageOpen(false),
        });
    }

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <ShieldCheckIcon className="size-4 text-muted-foreground" />
                            <CardTitle>{title}</CardTitle>
                        </div>
                        <CardDescription className="mt-2">
                            {description}
                        </CardDescription>
                    </div>
                    {canEdit && !agency.is_trashed ? (
                        <Button type="button" onClick={() => setManageOpen(true)}>
                            <ShieldCheckIcon data-icon="inline-start" />
                            {manageLabel}
                        </Button>
                    ) : null}
                </div>
            </CardHeader>
            <CardContent>
                {items.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-lg border border-dashed px-4 py-10 text-center">
                        <ShieldCheckIcon className="mb-3 size-8 text-muted-foreground/60" />
                        <p className="text-sm text-muted-foreground">
                            {emptyMessage}
                        </p>
                        {canEdit && !agency.is_trashed ? (
                            <Button
                                type="button"
                                className="mt-4"
                                onClick={() => setManageOpen(true)}
                            >
                                {manageLabel}
                            </Button>
                        ) : null}
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Provider Name</TableHead>
                                <TableHead>Vendor</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Primary</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.map((provider) => (
                                <TableRow key={provider.id}>
                                    <TableCell className="font-medium">
                                        {provider.href ? (
                                            <Link
                                                href={provider.href}
                                                className="text-foreground hover:text-primary"
                                            >
                                                {provider.name}
                                            </Link>
                                        ) : (
                                            provider.name
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">
                                            {provider.vendor_label ??
                                                provider.vendor ??
                                                'Unknown'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                provider.status === 'active'
                                                    ? 'success'
                                                    : 'secondary'
                                            }
                                        >
                                            {provider.status_label ??
                                                formatRelationStatus(provider.status)}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {provider.is_primary ? (
                                            <Badge variant="info">Primary</Badge>
                                        ) : canEdit && !agency.is_trashed ? (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={relationAction.processing}
                                                onClick={() =>
                                                    openConfirm(
                                                        `Set Primary ${providerKind} Provider`,
                                                        `Set "${provider.name}" as the primary ${providerKind} provider for this agency?`,
                                                        'Set Primary',
                                                        () =>
                                                            void relationAction.perform(
                                                                'POST',
                                                                route(setPrimaryRouteName, {
                                                                    agency: agency.id,
                                                                    provider: provider.id,
                                                                }),
                                                                {
                                                                    reloadOnly: [
                                                                        'agency',
                                                                        'dnsProviders',
                                                                        'cdnProviders',
                                                                        'activities',
                                                                    ],
                                                                },
                                                            ),
                                                    )
                                                }
                                            >
                                                Set Primary
                                            </Button>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            {provider.href ? (
                                                <Button variant="outline" asChild>
                                                    <Link href={provider.href}>View</Link>
                                                </Button>
                                            ) : null}
                                            {canEdit && !agency.is_trashed ? (
                                                <Button
                                                    variant="outline"
                                                    className="border-destructive/30 text-destructive hover:bg-destructive/10"
                                                    disabled={relationAction.processing}
                                                    onClick={() =>
                                                        openConfirm(
                                                            `Remove ${providerKind} Provider`,
                                                            `Remove "${provider.name}" from this agency?`,
                                                            'Remove',
                                                            () =>
                                                                void relationAction.perform(
                                                                    'DELETE',
                                                                    route(
                                                                        'platform.agencies.providers.detach',
                                                                        {
                                                                            agency: agency.id,
                                                                            provider: provider.id,
                                                                        },
                                                                    ),
                                                                    {
                                                                        reloadOnly: [
                                                                            'agency',
                                                                            'dnsProviders',
                                                                            'cdnProviders',
                                                                            'activities',
                                                                        ],
                                                                    },
                                                                ),
                                                            'destructive',
                                                        )
                                                    }
                                                >
                                                    <Trash2Icon className="size-4" />
                                                </Button>
                                            ) : null}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>

            <AssociationManagementDialog
                open={manageOpen}
                onOpenChange={handleManageOpenChange}
                title={`Manage ${providerKind} Providers`}
                description={`Select the ${providerKind} providers available to this agency and choose the primary provider.`}
                selectionLabel={`Select ${providerKind} Providers`}
                selectionHelp={`Choose one or more ${providerKind} providers to associate with this agency.`}
                primaryLabel={`Primary ${providerKind} Provider`}
                primaryHelp="The primary provider must also be selected in the list above."
                options={providerOptions}
                selectedValues={selectedProviderIds}
                onSelectedValuesChange={handleSelectedProviderIdsChange}
                primaryValue={primaryProviderId}
                onPrimaryValueChange={setPrimaryProviderId}
                loading={loadingOptions}
                saving={relationAction.processing}
                onSave={saveProviders}
            />
        </Card>
    );
}

export function AgencyProvidersTab({
    agency,
    dnsProviders,
    cdnProviders,
    canEdit,
    openConfirm,
}: AgencyProvidersTabProps) {
    return (
        <div className="grid gap-6 xl:grid-cols-2">
            <AgencyProviderSection
                agency={agency}
                title="DNS Providers"
                description="Providers available for managed DNS automation."
                manageLabel="Manage DNS Providers"
                emptyMessage="No DNS providers are attached to this agency."
                items={dnsProviders}
                canEdit={canEdit}
                availableRouteName="platform.agencies.available-dns-providers"
                attachRouteName="platform.agencies.dns-providers.attach"
                setPrimaryRouteName="platform.agencies.dns-providers.set-primary"
                providerKind="DNS"
                openConfirm={openConfirm}
            />
            <AgencyProviderSection
                agency={agency}
                title="CDN Providers"
                description="Providers available for edge caching and delivery."
                manageLabel="Manage CDN Providers"
                emptyMessage="No CDN providers are attached to this agency."
                items={cdnProviders}
                canEdit={canEdit}
                availableRouteName="platform.agencies.available-cdn-providers"
                attachRouteName="platform.agencies.cdn-providers.attach"
                setPrimaryRouteName="platform.agencies.cdn-providers.set-primary"
                providerKind="CDN"
                openConfirm={openConfirm}
            />
        </div>
    );
}
