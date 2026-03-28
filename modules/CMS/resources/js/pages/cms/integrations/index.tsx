import type { FormDataType } from '@inertiajs/core';
import { usePage } from '@inertiajs/react';
import { ExternalLinkIcon } from 'lucide-react';
import { useMemo } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import type { AuthenticatedSharedData, SettingsNavItem } from '@/types';
import type { IntegrationSectionKey, IntegrationsPageProps } from '../../../types/cms';
import { buildIntegrationSectionMap } from './components/integration-section-forms';
import type {
    GoogleAdsenseFormValues,
    GoogleAnalyticsFormValues,
    GoogleTagsFormValues,
    MetaPixelFormValues,
    MicrosoftClarityFormValues,
    OtherIntegrationFormValues,
    WebmasterToolsFormValues,
} from './components/integration-section-forms';
import {
    integrationSections,
    integrationsBreadcrumbs,
} from './components/integrations-meta';

export default function IntegrationsIndex({
    activeSection,
    statuses,
    settings,
}: IntegrationsPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canManageIntegrations =
        page.props.auth.abilities.manageIntegrationsSeoSettings ?? false;
    const currentSection = activeSection;

    const settingsNav: SettingsNavItem[] = useMemo(() => {
        const sectionRoutes: Record<IntegrationSectionKey, string> = {
            webmaster_tools: route('cms.integrations.webmastertools'),
            google_analytics: route('cms.integrations.googleanalytics'),
            google_tags: route('cms.integrations.googletags'),
            meta_pixel: route('cms.integrations.metapixel'),
            microsoft_clarity: route('cms.integrations.microsoftclarity'),
            google_adsense: route('cms.integrations.googleadsense'),
            other: route('cms.integrations.other'),
        };

        return integrationSections.map((section) => ({
            slug: section.key,
            label: section.title,
            href: sectionRoutes[section.key],
            icon: section.icon,
        }));
    }, []);

    const enabledCount = useMemo(
        () => Object.values(statuses).filter(Boolean).length,
        [statuses],
    );

    const webmasterForm = useAppForm<WebmasterToolsFormValues>({
        defaults: {
            ...settings.webmaster_tools,
            section: 'webmaster_tools',
        },
        rememberKey: 'cms.integrations.webmaster_tools',
        dirtyGuard: { enabled: true },
    });

    const googleAnalyticsForm = useAppForm<GoogleAnalyticsFormValues>({
        defaults: {
            ...settings.google_analytics,
            section: 'google_analytics',
        },
        rememberKey: 'cms.integrations.google_analytics',
        dirtyGuard: { enabled: true },
    });

    const googleTagsForm = useAppForm<GoogleTagsFormValues>({
        defaults: {
            ...settings.google_tags,
            section: 'google_tags',
        },
        rememberKey: 'cms.integrations.google_tags',
        dirtyGuard: { enabled: true },
    });

    const metaPixelForm = useAppForm<MetaPixelFormValues>({
        defaults: {
            ...settings.meta_pixel,
            section: 'meta_pixel',
        },
        rememberKey: 'cms.integrations.meta_pixel',
        dirtyGuard: { enabled: true },
    });

    const microsoftClarityForm = useAppForm<MicrosoftClarityFormValues>({
        defaults: {
            ...settings.microsoft_clarity,
            section: 'microsoft_clarity',
        },
        rememberKey: 'cms.integrations.microsoft_clarity',
        dirtyGuard: { enabled: true },
    });

    const googleAdsenseForm = useAppForm<GoogleAdsenseFormValues>({
        defaults: {
            ...settings.google_adsense,
            section: 'google_adsense',
        },
        rememberKey: 'cms.integrations.google_adsense',
        dirtyGuard: { enabled: true },
    });

    const otherForm = useAppForm<OtherIntegrationFormValues>({
        defaults: {
            ...settings.other,
            section: 'other',
        },
        rememberKey: 'cms.integrations.other',
        dirtyGuard: { enabled: true },
    });

    const submitForm = <T extends FormDataType<T>>(
        event: FormEvent<HTMLFormElement>,
        form: ReturnType<typeof useAppForm<T>>,
        url: string,
        title: string,
    ) => {
        event.preventDefault();

        form.submit('post', url, {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title,
                description: 'Integration settings saved successfully.',
            },
        });
    };

    const sectionMap = buildIntegrationSectionMap({
        canManageIntegrations,
        forms: {
            webmasterForm,
            googleAnalyticsForm,
            googleTagsForm,
            metaPixelForm,
            microsoftClarityForm,
            googleAdsenseForm,
            otherForm,
        },
        submitForm,
    });

    const currentMeta =
        integrationSections.find((section) => section.key === currentSection) ??
        integrationSections[0];

    return (
        <SettingsLayout
            settingsNav={settingsNav}
            activeSlug={currentSection}
            railLabel="CMS integrations"
            breadcrumbs={integrationsBreadcrumbs}
            title="Integrations"
            description="Manage verification tags, analytics scripts, AdSense, and other head integrations for your CMS-driven site."
        >
            <div className="flex min-w-0 flex-col gap-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-3">
                        <Badge variant="secondary">{enabledCount} active</Badge>
                        <Badge
                            variant={
                                statuses[currentSection]
                                    ? 'success'
                                    : 'secondary'
                            }
                        >
                            {statuses[currentSection]
                                ? 'Configured'
                                : currentMeta.emptyLabel}
                        </Badge>
                    </div>
                    <Button variant="outline" asChild>
                        <a
                            href="https://developers.google.com/search/docs/monitor-debug/search-console/get-started"
                            target="_blank"
                            rel="noreferrer"
                        >
                            <ExternalLinkIcon data-icon="inline-start" />
                            Integration docs
                        </a>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{currentMeta.title}</CardTitle>
                        <CardDescription>
                            {currentMeta.description}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div className="rounded-xl border bg-muted/30 px-4 py-3">
                            <p className="text-sm font-medium">
                                Active integrations
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {enabledCount} of {integrationSections.length}{' '}
                                sections configured.
                            </p>
                        </div>
                        <div className="rounded-xl border bg-muted/30 px-4 py-3">
                            <p className="text-sm font-medium">
                                Sanitized on save
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Only head-safe tags are kept after validation.
                            </p>
                        </div>
                        <div className="rounded-xl border bg-muted/30 px-4 py-3">
                            <p className="text-sm font-medium">
                                AdSense support
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Includes ads.txt editing and display conditions.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {sectionMap[currentSection]}
            </div>
        </SettingsLayout>
    );
}
