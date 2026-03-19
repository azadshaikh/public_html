import { Link } from '@inertiajs/react';
import { ServerIcon, Trash2Icon } from 'lucide-react';
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
import type { AgencyServerItem, AgencyShowData } from '../../../types/platform';
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

type AgencyServersTabProps = {
    agency: AgencyShowData;
    servers: AgencyServerItem[];
    canEdit: boolean;
    openConfirm: (
        title: string,
        description: string,
        confirmLabel: string,
        action: () => void,
        tone?: ConfirmState['tone'],
    ) => void;
};

type ServerOptionsResponse = {
    success: boolean;
    servers: AgencyServerItem[];
};

function toServerOption(server: AgencyServerItem): ManagementOption {
    return {
        value: server.id,
        label: server.subtitle ? `${server.name} (${server.subtitle})` : server.name,
        description: server.type_label ?? server.status_label ?? undefined,
    };
}

export function AgencyServersTab({
    agency,
    servers,
    canEdit,
    openConfirm,
}: AgencyServersTabProps) {
    const relationAction = useAgencyRelationAction();
    const [manageOpen, setManageOpen] = useState(false);
    const [loadingOptions, setLoadingOptions] = useState(false);
    const [availableServers, setAvailableServers] = useState<AgencyServerItem[]>(
        [],
    );
    const [selectedServerIds, setSelectedServerIds] = useState<number[]>([]);
    const [primaryServerId, setPrimaryServerId] = useState('');

    useEffect(() => {
        if (!manageOpen) {
            return;
        }

        if (!canEdit || agency.is_trashed) {
            return;
        }

        const controller = new AbortController();

        void requestJson<ServerOptionsResponse>(
            route('platform.agencies.available-servers', agency.id),
            { signal: controller.signal },
        )
            .then((payload) => {
                setAvailableServers(payload.servers);
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
                            : 'Failed to load available servers.',
                });
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoadingOptions(false);
                }
            });

        return () => controller.abort();
    }, [agency.id, agency.is_trashed, canEdit, manageOpen, servers]);

    const serverOptions = mergeManagementOptions(
        servers.map(toServerOption),
        availableServers.map(toServerOption),
    );

    function handleManageOpenChange(open: boolean): void {
        if (open) {
            const selectedIds = servers.map((server) => server.id);
            const currentPrimary =
                servers.find((server) => server.is_primary)?.id ?? null;

            setSelectedServerIds(selectedIds);
            setPrimaryServerId(currentPrimary ? String(currentPrimary) : '');
            setLoadingOptions(canEdit && !agency.is_trashed);
        }

        setManageOpen(open);
    }

    function handleSelectedServerIdsChange(values: number[]): void {
        setSelectedServerIds(values);

        if (primaryServerId && !values.includes(Number(primaryServerId))) {
            setPrimaryServerId('');
        }
    }

    function saveServers(): void {
        void relationAction.perform(
            'POST',
            route('platform.agencies.servers.attach', agency.id),
            {
                body: {
                    server_ids: selectedServerIds,
                    primary_server_id: primaryServerId
                        ? Number(primaryServerId)
                        : null,
                },
                reloadOnly: ['agency', 'servers', 'activities'],
                onSuccess: () => setManageOpen(false),
            },
        );
    }

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <ServerIcon className="size-4 text-muted-foreground" />
                            <CardTitle>Associated Servers</CardTitle>
                        </div>
                        <CardDescription className="mt-2">
                            Attach servers to the agency, assign a primary server,
                            or remove infrastructure links.
                        </CardDescription>
                    </div>
                    {canEdit && !agency.is_trashed ? (
                        <Button type="button" onClick={() => setManageOpen(true)}>
                            <ServerIcon data-icon="inline-start" />
                            Manage Servers
                        </Button>
                    ) : null}
                </div>
            </CardHeader>
            <CardContent>
                {servers.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-lg border border-dashed px-4 py-10 text-center">
                        <ServerIcon className="mb-3 size-8 text-muted-foreground/60" />
                        <p className="text-sm text-muted-foreground">
                            No servers are linked to this agency yet.
                        </p>
                        {canEdit && !agency.is_trashed ? (
                            <Button
                                type="button"
                                className="mt-4"
                                onClick={() => setManageOpen(true)}
                            >
                                Manage Servers
                            </Button>
                        ) : null}
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Server Name</TableHead>
                                <TableHead>IP Address</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Primary</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {servers.map((server) => (
                                <TableRow key={server.id}>
                                    <TableCell className="font-medium">
                                        {server.href ? (
                                            <Link
                                                href={server.href}
                                                className="text-foreground hover:text-primary"
                                            >
                                                {server.name}
                                            </Link>
                                        ) : (
                                            server.name
                                        )}
                                    </TableCell>
                                    <TableCell className="font-mono text-xs">
                                        {server.subtitle ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="warning">
                                            {server.type_label ?? 'Unknown'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                server.status === 'active'
                                                    ? 'success'
                                                    : 'secondary'
                                            }
                                        >
                                            {server.status_label ??
                                                formatRelationStatus(server.status)}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {server.is_primary ? (
                                            <Badge variant="info">Primary</Badge>
                                        ) : canEdit && !agency.is_trashed ? (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={relationAction.processing}
                                                onClick={() =>
                                                    openConfirm(
                                                        'Set Primary Server',
                                                        `Set "${server.name}" as the primary server for this agency?`,
                                                        'Set Primary',
                                                        () =>
                                                            void relationAction.perform(
                                                                'POST',
                                                                route(
                                                                    'platform.agencies.servers.set-primary',
                                                                    {
                                                                        agency: agency.id,
                                                                        server: server.id,
                                                                    },
                                                                ),
                                                                {
                                                                    reloadOnly: [
                                                                        'agency',
                                                                        'servers',
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
                                            {server.href ? (
                                                <Button variant="outline" asChild>
                                                    <Link href={server.href}>View</Link>
                                                </Button>
                                            ) : null}
                                            {canEdit && !agency.is_trashed ? (
                                                <Button
                                                    variant="outline"
                                                    className="border-destructive/30 text-destructive hover:bg-destructive/10"
                                                    disabled={relationAction.processing}
                                                    onClick={() =>
                                                        openConfirm(
                                                            'Remove Server',
                                                            `Remove "${server.name}" from this agency?`,
                                                            'Remove',
                                                            () =>
                                                                void relationAction.perform(
                                                                    'DELETE',
                                                                    route(
                                                                        'platform.agencies.servers.detach',
                                                                        {
                                                                            agency: agency.id,
                                                                            server: server.id,
                                                                        },
                                                                    ),
                                                                    {
                                                                        reloadOnly: [
                                                                            'agency',
                                                                            'servers',
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
                title="Manage Agency Servers"
                description="Select the servers that belong to this agency and choose the primary infrastructure host."
                selectionLabel="Select Servers"
                selectionHelp="Choose one or more servers to associate with this agency."
                primaryLabel="Primary Server"
                primaryHelp="The primary server must also be selected in the list above."
                options={serverOptions}
                selectedValues={selectedServerIds}
                onSelectedValuesChange={handleSelectedServerIdsChange}
                primaryValue={primaryServerId}
                onPrimaryValueChange={setPrimaryServerId}
                loading={loadingOptions}
                saving={relationAction.processing}
                onSave={saveServers}
            />
        </Card>
    );
}
