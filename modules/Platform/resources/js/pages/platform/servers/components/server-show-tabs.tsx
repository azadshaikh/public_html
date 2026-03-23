import { ActivityIcon, Building2Icon, CodeIcon, GlobeIcon, InfoIcon, KeyRoundIcon, ListChecksIcon, StickyNoteIcon } from 'lucide-react';
import type { Dispatch, SetStateAction } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import type {
    PlatformActivity,
    ProvisioningRunTimestamps,
    ServerAgencyItem,
    ServerMetadataItem,
    ServerProvisioningStep,
    ServerSecretItem,
    ServerShowData,
} from '../../../types/platform';
import { InfoRow } from './show-shared';
import { ServerProvisioningStepsTable } from './server-provisioning-steps-table';
import { ServerSecretsPanel } from './server-secrets-panel';
import { ServerWebsitesTab } from './server-websites-tab';

type ServerShowTabsProps = {
    activeTab: string;
    setActiveTab: Dispatch<SetStateAction<string>>;
    isMobile: boolean;
    server: ServerShowData;
    secrets: ServerSecretItem[];
    agencies: ServerAgencyItem[];
    metadataItems: ServerMetadataItem[];
    canRevealSecrets: boolean;
    canRevealSshKeyPair: boolean;
    provisioningSteps: ServerProvisioningStep[];
    provisioningRun: ProvisioningRunTimestamps;
    activities: PlatformActivity[];
};

export function ServerShowTabs({
    activeTab,
    setActiveTab,
    isMobile,
    server,
    secrets,
    agencies,
    metadataItems,
    canRevealSecrets,
    canRevealSshKeyPair,
    provisioningSteps,
    provisioningRun,
    activities,
}: ServerShowTabsProps) {
    return (
        <Tabs
            value={activeTab}
            onValueChange={setActiveTab}
            size="comfortable"
            className="min-w-0 flex-1 flex-col"
            orientation={isMobile ? 'vertical' : 'horizontal'}
        >
            <TabsList
                className={cn(
                    'w-full md:w-fit',
                    !isMobile && 'min-w-0 overflow-x-auto pr-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
                )}
            >
                <TabsTrigger value="general" className={cn(!isMobile && 'shrink-0')}>
                    <InfoIcon data-icon="inline-start" />
                    General
                </TabsTrigger>
                {(secrets.length > 0 || server.has_access_key_secret || server.has_ssh_credentials) ? (
                    <TabsTrigger value="secrets" className={cn(!isMobile && 'shrink-0')}>
                        <KeyRoundIcon data-icon="inline-start" />
                        <span>Secrets</span>
                        <Badge variant="secondary" className="rounded-full px-1.5 py-0 text-[0.7rem]">
                            {secrets.length + (server.has_access_key_secret ? 1 : 0) + (server.has_ssh_credentials ? 1 : 0)}
                        </Badge>
                    </TabsTrigger>
                ) : null}
                <TabsTrigger value="websites" className={cn(!isMobile && 'shrink-0')}>
                    <GlobeIcon data-icon="inline-start" />
                    Websites
                </TabsTrigger>
                <TabsTrigger value="agencies" className={cn(!isMobile && 'shrink-0')}>
                    <Building2Icon data-icon="inline-start" />
                    Agencies
                </TabsTrigger>
                <TabsTrigger value="provision" className={cn(!isMobile && 'shrink-0')}>
                    <ListChecksIcon data-icon="inline-start" />
                    Provision
                </TabsTrigger>
                <TabsTrigger value="notes" className={cn(!isMobile && 'shrink-0')}>
                    <StickyNoteIcon data-icon="inline-start" />
                    Notes
                </TabsTrigger>
                <TabsTrigger value="metadata" className={cn(!isMobile && 'shrink-0')}>
                    <CodeIcon data-icon="inline-start" />
                    Metadata
                </TabsTrigger>
                <TabsTrigger value="activity" className={cn(!isMobile && 'shrink-0')}>
                    <ActivityIcon data-icon="inline-start" />
                    Activity
                </TabsTrigger>
            </TabsList>

            <TabsContent value="general">
                <Card>
                    <CardContent className="pt-6">
                        <div className="grid gap-6 md:grid-cols-2">
                            <div>
                                <p className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Connection Details</p>
                                <div className="flex flex-col gap-2">
                                    <InfoRow label="SSH User">{server.ssh_user ?? '—'}</InfoRow>
                                    <InfoRow label="SSH Port">{server.ssh_port ?? '—'}</InfoRow>
                                    <InfoRow label="Hestia Port">{server.port ?? '—'}</InfoRow>
                                    <InfoRow label="Creation Mode">{server.creation_mode}</InfoRow>
                                </div>
                            </div>
                            <div>
                                <p className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Timestamps</p>
                                <div className="flex flex-col gap-2">
                                    <InfoRow label="Created">{server.created_at ?? '—'}</InfoRow>
                                    <InfoRow label="Updated">{server.updated_at ?? '—'}</InfoRow>
                                    <InfoRow label="Last Synced">{server.last_synced_at ?? 'Never'}</InfoRow>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            {(secrets.length > 0 || server.has_access_key_secret || server.has_ssh_credentials) ? (
                <TabsContent value="secrets">
                    <Card>
                        <CardContent className="pt-6">
                            <ServerSecretsPanel
                                serverId={server.id}
                                secrets={secrets}
                                canReveal={canRevealSecrets}
                                canRevealSshKeyPair={canRevealSshKeyPair}
                                hasAccessKeySecret={server.has_access_key_secret}
                                hasSshCredentials={server.has_ssh_credentials}
                            />
                        </CardContent>
                    </Card>
                </TabsContent>
            ) : null}

            <TabsContent value="websites">
                <Card>
                    <CardContent className="pt-6">
                        <ServerWebsitesTab serverId={server.id} active={activeTab === 'websites'} />
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="agencies">
                <Card>
                    <CardContent className="pt-6">
                        {agencies.length === 0 ? (
                            <div className="py-10 text-center text-muted-foreground">
                                <Building2Icon className="mx-auto mb-3 size-8 opacity-50" />
                                <p>No agencies are assigned to this server.</p>
                            </div>
                        ) : (
                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                {agencies.map((agency) => (
                                    <div key={agency.id} className="rounded-lg border bg-muted/30 p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-medium text-foreground">{agency.name}</p>
                                                <p className="text-xs text-muted-foreground">Agency #{agency.id}</p>
                                            </div>
                                            <div className="flex gap-2">
                                                {agency.is_primary ? <Badge variant="success">Primary</Badge> : null}
                                                {agency.status ? <Badge variant="secondary">{agency.status}</Badge> : null}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="provision">
                <Card>
                    <CardContent className="pt-6">
                        <ServerProvisioningStepsTable
                            serverId={server.id}
                            steps={provisioningSteps}
                            provisioningRun={provisioningRun}
                            provisioningStatus={server.provisioning_status}
                        />
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="notes">
                <Card>
                    <CardContent className="pt-6">
                        <p className="text-sm text-muted-foreground">Notes functionality will be available here.</p>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="metadata">
                <Card>
                    <CardContent className="pt-6">
                        {metadataItems.length === 0 ? (
                            <div className="py-10 text-center text-muted-foreground">
                                <CodeIcon className="mx-auto mb-3 size-8 opacity-50" />
                                <p>No metadata stored for this server.</p>
                            </div>
                        ) : (
                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                {metadataItems.map((item) => (
                                    <div key={item.key} className="rounded-lg border bg-muted/30 p-3">
                                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">{item.label}</p>
                                        <p className="mt-0.5 break-all text-sm font-medium text-foreground">{item.value}</p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="activity">
                <Card>
                    <CardContent className="pt-6">
                        {activities.length === 0 ? (
                            <div className="py-10 text-center text-muted-foreground">
                                <ActivityIcon className="mx-auto mb-3 size-8 opacity-50" />
                                <p>No activity logs found for this server.</p>
                            </div>
                        ) : (
                            <div className="flex flex-col gap-3">
                                {activities.map((activity) => (
                                    <div key={activity.id} className="flex items-start justify-between gap-4 rounded-lg border p-3">
                                        <div>
                                            <p className="text-sm font-medium text-foreground">{activity.description}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {activity.causer_name ? `${activity.causer_name} · ` : ''}
                                                {activity.created_at ?? 'Unknown time'}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    );
}
