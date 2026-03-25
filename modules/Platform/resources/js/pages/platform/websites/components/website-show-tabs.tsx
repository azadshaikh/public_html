import { ActivityIcon, ClockIcon, CodeIcon, InfoIcon, KeyRoundIcon, ListChecksIcon, StickyNoteIcon } from 'lucide-react';
import type { Dispatch, SetStateAction } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import type {
    PlatformActivity,
    WebsiteProvisioningStep,
    WebsiteSecretItem,
    WebsiteShowData,
    WebsiteUpdateItem,
} from '../../../../types/platform';
import { InfoRow } from './show-shared';
import { WebsiteProvisioningStepsTable } from './website-provisioning-steps-table';
import { WebsiteSecretsTable } from './website-secrets-table';

type WebsiteShowTabsProps = {
    activeTab: string;
    setActiveTab: Dispatch<SetStateAction<string>>;
    isMobile: boolean;
    website: WebsiteShowData;
    secrets: WebsiteSecretItem[];
    canRevealSecrets: boolean;
    provisioningSteps: WebsiteProvisioningStep[];
    updates: WebsiteUpdateItem[];
    activities: PlatformActivity[];
};

export function WebsiteShowTabs({
    activeTab,
    setActiveTab,
    isMobile,
    website,
    secrets,
    canRevealSecrets,
    provisioningSteps,
    updates,
    activities,
}: WebsiteShowTabsProps) {
    const isProvisioning = website.status === 'provisioning';

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
                {secrets.length > 0 ? (
                    <TabsTrigger value="secrets" className={cn(!isMobile && 'shrink-0')}>
                        <KeyRoundIcon data-icon="inline-start" />
                        <span>Secrets</span>
                        <Badge variant="secondary" className="rounded-full px-1.5 py-0 text-[0.7rem]">{secrets.length}</Badge>
                    </TabsTrigger>
                ) : null}
                <TabsTrigger value="provision" className={cn(!isMobile && 'shrink-0')}>
                    <ListChecksIcon data-icon="inline-start" />
                    Provision
                </TabsTrigger>
                <TabsTrigger value="updates" className={cn(!isMobile && 'shrink-0')}>
                    <ClockIcon data-icon="inline-start" />
                    Updates
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
                                <p className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Business Niches</p>
                                {website.niches.length > 0 ? (
                                    <div className="flex flex-wrap gap-2">
                                        {website.niches.map((niche) => (
                                            <Badge key={niche} variant="default">{niche}</Badge>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">—</p>
                                )}
                            </div>
                            <div>
                                <p className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Timestamps</p>
                                <div className="flex flex-col gap-2">
                                    <InfoRow label="Created">{website.created_at ?? '—'}</InfoRow>
                                    <InfoRow label="Updated">{website.updated_at ?? '—'}</InfoRow>
                                    <InfoRow label="Last Synced">{website.last_synced_at ?? 'Never'}</InfoRow>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            {secrets.length > 0 ? (
                <TabsContent value="secrets">
                    <Card>
                        <CardContent className="pt-6">
                            <WebsiteSecretsTable websiteId={website.id} secrets={secrets} canReveal={canRevealSecrets} />
                        </CardContent>
                    </Card>
                </TabsContent>
            ) : null}

            <TabsContent value="provision">
                <Card>
                    <CardContent className="pt-6">
                        <WebsiteProvisioningStepsTable
                            websiteId={website.id}
                            steps={provisioningSteps}
                            isProvisioning={isProvisioning}
                            websiteStatus={website.status}
                        />
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="updates">
                <Card>
                    <CardContent className="pt-6">
                        {updates.length === 0 ? (
                            <div className="py-10 text-center text-muted-foreground">
                                <ClockIcon className="mx-auto mb-3 size-8 opacity-50" />
                                <p>No sync data available yet.</p>
                            </div>
                        ) : (
                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                {updates.map((update) => (
                                    <div key={update.key} className="rounded-lg border bg-muted/30 p-3">
                                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">{update.label}</p>
                                        <p className="mt-0.5 text-sm font-medium text-foreground">{update.value}</p>
                                    </div>
                                ))}
                            </div>
                        )}
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
                        {updates.length === 0 ? (
                            <div className="py-10 text-center text-muted-foreground">
                                <CodeIcon className="mx-auto mb-3 size-8 opacity-50" />
                                <p>No metadata stored for this website.</p>
                            </div>
                        ) : (
                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                {updates.map((update) => (
                                    <div key={update.key} className="rounded-lg border bg-muted/30 p-3">
                                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">{update.label}</p>
                                        <p className="mt-0.5 break-all text-sm font-medium text-foreground">{update.value}</p>
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
                                <p>No activity logs found for this website.</p>
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
