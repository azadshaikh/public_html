import type { FormDataType } from '@inertiajs/core';
import { usePage } from '@inertiajs/react';
import {
    BadgeCheckIcon,
    BarChart3Icon,
    Code2Icon,
    ExternalLinkIcon,
    FileTextIcon,
    HomeIcon,
    MegaphoneIcon,
    SearchCheckIcon,
    SaveIcon,
    TagsIcon,
} from 'lucide-react';
import { useMemo } from 'react';
import type { ComponentType, FormEvent, ReactNode } from 'react';
import { MonacoEditor } from '@/components/code-editor/monaco-editor';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import type { AuthenticatedSharedData, BreadcrumbItem, SettingsNavItem } from '@/types';
import type {
    GoogleAdsenseSettings,
    GoogleAnalyticsSettings,
    GoogleTagsSettings,
    IntegrationSectionKey,
    IntegrationsPageProps,
    MetaPixelSettings,
    MicrosoftClaritySettings,
    OtherIntegrationSettings,
    WebmasterToolsSettings,
} from '../../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'CMS', href: route('cms.posts.index', 'all') },
    { title: 'Integrations', href: route('cms.integrations.index') },
];

type SectionMeta = {
    key: IntegrationSectionKey;
    title: string;
    description: string;
    category: string;
    icon: ComponentType<{ className?: string }>;
    emptyLabel: string;
};

type SectionCardProps = {
    title: string;
    description: string;
    footer?: ReactNode;
    children: ReactNode;
};

type CodeFieldProps = {
    id: string;
    label: string;
    description: string;
    value: string;
    error?: string;
    invalid: boolean;
    onChange: (value: string) => void;
    onBlur: () => void;
    placeholder?: string;
    language?: string;
    height?: string;
};

type WebmasterToolsFormValues = WebmasterToolsSettings & {
    section: 'webmaster_tools';
};

type GoogleAnalyticsFormValues = GoogleAnalyticsSettings & {
    section: 'google_analytics';
};

type GoogleTagsFormValues = GoogleTagsSettings & {
    section: 'google_tags';
};

type MetaPixelFormValues = MetaPixelSettings & {
    section: 'meta_pixel';
};

type MicrosoftClarityFormValues = MicrosoftClaritySettings & {
    section: 'microsoft_clarity';
};

type GoogleAdsenseFormValues = GoogleAdsenseSettings & {
    section: 'google_adsense';
};

type OtherIntegrationFormValues = OtherIntegrationSettings & {
    section: 'other';
};

const sections: SectionMeta[] = [
    {
        key: 'webmaster_tools',
        title: 'Webmaster verification',
        description: 'Add verification tags from search engines and webmaster platforms.',
        category: 'Verification',
        icon: SearchCheckIcon,
        emptyLabel: 'Not set',
    },
    {
        key: 'google_analytics',
        title: 'Google Analytics',
        description: 'Inject your Google Analytics tracking script into the site head.',
        category: 'Analytics',
        icon: BarChart3Icon,
        emptyLabel: 'Not set',
    },
    {
        key: 'google_tags',
        title: 'Google Tag Manager',
        description: 'Add your Google Tag Manager script and head snippet.',
        category: 'Analytics',
        icon: TagsIcon,
        emptyLabel: 'Not set',
    },
    {
        key: 'meta_pixel',
        title: 'Meta Pixel',
        description: 'Configure Meta Pixel for ad conversion and campaign attribution.',
        category: 'Analytics',
        icon: BadgeCheckIcon,
        emptyLabel: 'Not set',
    },
    {
        key: 'microsoft_clarity',
        title: 'Microsoft Clarity',
        description: 'Enable Microsoft Clarity session replay and heatmap tracking.',
        category: 'Analytics',
        icon: FileTextIcon,
        emptyLabel: 'Not set',
    },
    {
        key: 'google_adsense',
        title: 'Google AdSense',
        description: 'Control Google AdSense scripts, ads.txt content, and display rules.',
        category: 'Advertising',
        icon: MegaphoneIcon,
        emptyLabel: 'Disabled',
    },
    {
        key: 'other',
        title: 'Custom head code',
        description: 'Add other script tags, meta tags, or custom head markup.',
        category: 'Custom code',
        icon: Code2Icon,
        emptyLabel: 'Not set',
    },
];

function SectionCard({ title, description, footer, children }: SectionCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-6">{children}</CardContent>
            {footer ? <CardFooter>{footer}</CardFooter> : null}
        </Card>
    );
}

function CodeField({
    id,
    label,
    description,
    value,
    error,
    invalid,
    onChange,
    onBlur,
    placeholder,
    language = 'html',
    height = '22rem',
}: CodeFieldProps) {
    return (
        <Field data-invalid={invalid || undefined}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <FieldDescription>{description}</FieldDescription>
            <MonacoEditor
                name={id}
                value={value}
                onChange={onChange}
                onBlur={onBlur}
                language={language}
                height={height}
                placeholder={placeholder}
                className="w-full"
            />
            <FieldError>{error}</FieldError>
        </Field>
    );
}

export default function IntegrationsIndex({ activeSection, statuses, settings }: IntegrationsPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canManageIntegrations = page.props.auth.abilities.manageIntegrationsSeoSettings;
    const currentSection = activeSection;

    const settingsNav: SettingsNavItem[] = useMemo(
        () =>
            sections.map((section) => ({
                slug: section.key,
                label: section.title,
                href: route('cms.integrations.index', { section: section.key }),
            })),
        [],
    );

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

    const sectionMap: Record<IntegrationSectionKey, ReactNode> = {
        webmaster_tools: (
            <form
                noValidate
                className="flex flex-col gap-6"
                onSubmit={(event) =>
                    submitForm(
                        event,
                        webmasterForm,
                        route('cms.integrations.webmastertools.update'),
                        'Webmaster tags updated',
                    )
                }
            >
                {webmasterForm.dirtyGuardDialog}
                <FormErrorSummary errors={webmasterForm.errors} minMessages={2} />
                <SectionCard
                    title="Webmaster verification"
                    description="Paste the verification meta tags provided by search engines and directory services."
                    footer={
                        <Button type="submit" disabled={webmasterForm.processing || !canManageIntegrations}>
                            {webmasterForm.processing ? <Spinner /> : <SaveIcon data-icon="inline-start" />}
                            Save verification tags
                        </Button>
                    }
                >
                    <Alert>
                        <SearchCheckIcon />
                        <AlertTitle>Head-safe tags only</AlertTitle>
                        <AlertDescription>
                            Only valid head tags such as <code>&lt;meta&gt;</code>, <code>&lt;script&gt;</code>, <code>&lt;link&gt;</code>, <code>&lt;style&gt;</code>, and <code>&lt;noscript&gt;</code> are kept on save.
                        </AlertDescription>
                    </Alert>
                    <FieldGroup>
                        <CodeField
                            id="google_search_console"
                            label="Google Search Console"
                            description="Paste the full verification meta tag from Google Search Console."
                            value={webmasterForm.data.google_search_console}
                            error={webmasterForm.error('google_search_console')}
                            invalid={webmasterForm.invalid('google_search_console')}
                            onChange={(value) => webmasterForm.setField('google_search_console', value)}
                            onBlur={() => webmasterForm.touch('google_search_console')}
                            height="8rem"
                            placeholder={'<meta name="google-site-verification" content="your-code" />'}
                        />
                        <CodeField
                            id="bing_webmaster"
                            label="Bing Webmaster Tools"
                            description="Paste the verification meta tag from Bing Webmaster Tools."
                            value={webmasterForm.data.bing_webmaster}
                            error={webmasterForm.error('bing_webmaster')}
                            invalid={webmasterForm.invalid('bing_webmaster')}
                            onChange={(value) => webmasterForm.setField('bing_webmaster', value)}
                            onBlur={() => webmasterForm.touch('bing_webmaster')}
                            height="8rem"
                            placeholder={'<meta name="msvalidate.01" content="your-code" />'}
                        />
                        <CodeField
                            id="baidu_webmaster"
                            label="Baidu Webmaster"
                            description="Paste the verification meta tag from Baidu Webmaster."
                            value={webmasterForm.data.baidu_webmaster}
                            error={webmasterForm.error('baidu_webmaster')}
                            invalid={webmasterForm.invalid('baidu_webmaster')}
                            onChange={(value) => webmasterForm.setField('baidu_webmaster', value)}
                            onBlur={() => webmasterForm.touch('baidu_webmaster')}
                            height="8rem"
                            placeholder={'<meta name="baidu-site-verification" content="your-code" />'}
                        />
                        <CodeField
                            id="yandex_verification"
                            label="Yandex Webmaster"
                            description="Paste the verification meta tag from Yandex Webmaster."
                            value={webmasterForm.data.yandex_verification}
                            error={webmasterForm.error('yandex_verification')}
                            invalid={webmasterForm.invalid('yandex_verification')}
                            onChange={(value) => webmasterForm.setField('yandex_verification', value)}
                            onBlur={() => webmasterForm.touch('yandex_verification')}
                            height="8rem"
                            placeholder={'<meta name="yandex-verification" content="your-code" />'}
                        />
                        <CodeField
                            id="pinterest_verification"
                            label="Pinterest"
                            description="Paste the verification meta tag from Pinterest."
                            value={webmasterForm.data.pinterest_verification}
                            error={webmasterForm.error('pinterest_verification')}
                            invalid={webmasterForm.invalid('pinterest_verification')}
                            onChange={(value) => webmasterForm.setField('pinterest_verification', value)}
                            onBlur={() => webmasterForm.touch('pinterest_verification')}
                            height="8rem"
                            placeholder={'<meta name="p:domain_verify" content="your-code" />'}
                        />
                        <CodeField
                            id="norton_verification"
                            label="Norton Safe Web"
                            description="Paste the verification meta tag from Norton Safe Web."
                            value={webmasterForm.data.norton_verification}
                            error={webmasterForm.error('norton_verification')}
                            invalid={webmasterForm.invalid('norton_verification')}
                            onChange={(value) => webmasterForm.setField('norton_verification', value)}
                            onBlur={() => webmasterForm.touch('norton_verification')}
                            height="8rem"
                            placeholder={'<meta name="norton-safeweb-site-verification" content="your-code" />'}
                        />
                        <CodeField
                            id="custom_meta_tags"
                            label="Other meta tags"
                            description="Add any additional webmaster verification tags not covered above."
                            value={webmasterForm.data.custom_meta_tags}
                            error={webmasterForm.error('custom_meta_tags')}
                            invalid={webmasterForm.invalid('custom_meta_tags')}
                            onChange={(value) => webmasterForm.setField('custom_meta_tags', value)}
                            onBlur={() => webmasterForm.touch('custom_meta_tags')}
                            height="12rem"
                            placeholder={'<meta name="example" content="value" />'}
                        />
                    </FieldGroup>
                </SectionCard>
            </form>
        ),
        google_analytics: (
            <form
                noValidate
                className="flex flex-col gap-6"
                onSubmit={(event) =>
                    submitForm(
                        event,
                        googleAnalyticsForm,
                        route('cms.integrations.googleanalytics.update'),
                        'Google Analytics updated',
                    )
                }
            >
                {googleAnalyticsForm.dirtyGuardDialog}
                <FormErrorSummary errors={googleAnalyticsForm.errors} minMessages={2} />
                <SectionCard
                    title="Google Analytics"
                    description="Paste the Google Analytics tracking script you want injected into the document head."
                    footer={
                        <Button type="submit" disabled={googleAnalyticsForm.processing || !canManageIntegrations}>
                            {googleAnalyticsForm.processing ? <Spinner /> : <SaveIcon data-icon="inline-start" />}
                            Save Google Analytics
                        </Button>
                    }
                >
                    <CodeField
                        id="google_analytics"
                        label="Tracking script"
                        description="Paste the full analytics script snippet from Google Analytics."
                        value={googleAnalyticsForm.data.google_analytics}
                        error={googleAnalyticsForm.error('google_analytics')}
                        invalid={googleAnalyticsForm.invalid('google_analytics')}
                        onChange={(value) => googleAnalyticsForm.setField('google_analytics', value)}
                        onBlur={() => googleAnalyticsForm.touch('google_analytics')}
                        placeholder={'<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXX"></script>'}
                    />
                </SectionCard>
            </form>
        ),
        google_tags: (
            <form
                noValidate
                className="flex flex-col gap-6"
                onSubmit={(event) =>
                    submitForm(
                        event,
                        googleTagsForm,
                        route('cms.integrations.googletags.update'),
                        'Google Tag Manager updated',
                    )
                }
            >
                {googleTagsForm.dirtyGuardDialog}
                <FormErrorSummary errors={googleTagsForm.errors} minMessages={2} />
                <SectionCard
                    title="Google Tag Manager"
                    description="Add the script snippet provided by Google Tag Manager."
                    footer={
                        <Button type="submit" disabled={googleTagsForm.processing || !canManageIntegrations}>
                            {googleTagsForm.processing ? <Spinner /> : <SaveIcon data-icon="inline-start" />}
                            Save Tag Manager
                        </Button>
                    }
                >
                    <CodeField
                        id="google_tags"
                        label="Tag Manager script"
                        description="Paste the full Google Tag Manager snippet."
                        value={googleTagsForm.data.google_tags}
                        error={googleTagsForm.error('google_tags')}
                        invalid={googleTagsForm.invalid('google_tags')}
                        onChange={(value) => googleTagsForm.setField('google_tags', value)}
                        onBlur={() => googleTagsForm.touch('google_tags')}
                        placeholder="<script>(function(w,d,s,l,i){...})(window,document,'script','dataLayer','GTM-XXXX');</script>"
                    />
                </SectionCard>
            </form>
        ),
        meta_pixel: (
            <form
                noValidate
                className="flex flex-col gap-6"
                onSubmit={(event) =>
                    submitForm(
                        event,
                        metaPixelForm,
                        route('cms.integrations.metapixel.update'),
                        'Meta Pixel updated',
                    )
                }
            >
                {metaPixelForm.dirtyGuardDialog}
                <FormErrorSummary errors={metaPixelForm.errors} minMessages={2} />
                <SectionCard
                    title="Meta Pixel"
                    description="Add the Meta Pixel script used for conversion and audience tracking."
                    footer={
                        <Button type="submit" disabled={metaPixelForm.processing || !canManageIntegrations}>
                            {metaPixelForm.processing ? <Spinner /> : <SaveIcon data-icon="inline-start" />}
                            Save Meta Pixel
                        </Button>
                    }
                >
                    <CodeField
                        id="meta_pixel"
                        label="Meta Pixel code"
                        description="Paste the full Meta Pixel code provided in Meta Events Manager."
                        value={metaPixelForm.data.meta_pixel}
                        error={metaPixelForm.error('meta_pixel')}
                        invalid={metaPixelForm.invalid('meta_pixel')}
                        onChange={(value) => metaPixelForm.setField('meta_pixel', value)}
                        onBlur={() => metaPixelForm.touch('meta_pixel')}
                        placeholder="<script>!function(f,b,e,v,n,t,s){...}</script>"
                    />
                </SectionCard>
            </form>
        ),
        microsoft_clarity: (
            <form
                noValidate
                className="flex flex-col gap-6"
                onSubmit={(event) =>
                    submitForm(
                        event,
                        microsoftClarityForm,
                        route('cms.integrations.microsoftclarity.update'),
                        'Microsoft Clarity updated',
                    )
                }
            >
                {microsoftClarityForm.dirtyGuardDialog}
                <FormErrorSummary errors={microsoftClarityForm.errors} minMessages={2} />
                <SectionCard
                    title="Microsoft Clarity"
                    description="Inject the Clarity tag to enable heatmaps and session replays."
                    footer={
                        <Button type="submit" disabled={microsoftClarityForm.processing || !canManageIntegrations}>
                            {microsoftClarityForm.processing ? <Spinner /> : <SaveIcon data-icon="inline-start" />}
                            Save Clarity
                        </Button>
                    }
                >
                    <CodeField
                        id="ms_clarity"
                        label="Clarity script"
                        description="Paste the Clarity script from Microsoft Clarity."
                        value={microsoftClarityForm.data.ms_clarity}
                        error={microsoftClarityForm.error('ms_clarity')}
                        invalid={microsoftClarityForm.invalid('ms_clarity')}
                        onChange={(value) => microsoftClarityForm.setField('ms_clarity', value)}
                        onBlur={() => microsoftClarityForm.touch('ms_clarity')}
                        placeholder={'<script type="text/javascript">(function(c,l,a,r,i,t,y){...}</script>'}
                    />
                </SectionCard>
            </form>
        ),
        google_adsense: (
            <form
                noValidate
                className="flex flex-col gap-6"
                onSubmit={(event) =>
                    submitForm(
                        event,
                        googleAdsenseForm,
                        route('cms.integrations.googleadsense.update'),
                        'Google AdSense updated',
                    )
                }
            >
                {googleAdsenseForm.dirtyGuardDialog}
                <FormErrorSummary errors={googleAdsenseForm.errors} minMessages={2} />
                <SectionCard
                    title="Google AdSense"
                    description="Enable AdSense, manage the injected script, and control where ads appear."
                    footer={
                        <Button type="submit" disabled={googleAdsenseForm.processing || !canManageIntegrations}>
                            {googleAdsenseForm.processing ? <Spinner /> : <SaveIcon data-icon="inline-start" />}
                            Save AdSense settings
                        </Button>
                    }
                >
                    <Field orientation="horizontal">
                        <Switch
                            id="google_adsense_enabled"
                            checked={googleAdsenseForm.data.google_adsense_enabled}
                            onCheckedChange={(checked) =>
                                googleAdsenseForm.setField('google_adsense_enabled', checked === true)
                            }
                        />
                        <div className="flex flex-col gap-1">
                            <FieldLabel htmlFor="google_adsense_enabled">Enable Google AdSense</FieldLabel>
                            <FieldDescription>
                                Turn AdSense on globally before configuring ads.txt or display rules.
                            </FieldDescription>
                        </div>
                    </Field>

                    {googleAdsenseForm.data.google_adsense_enabled ? (
                        <>
                            <Field>
                                <FieldLabel htmlFor="google_adsense_ads_txt">ads.txt content</FieldLabel>
                                <FieldDescription>
                                    This content is written to the public ads.txt file.
                                </FieldDescription>
                                <Textarea
                                    id="google_adsense_ads_txt"
                                    value={googleAdsenseForm.data.google_adsense_ads_txt}
                                    onChange={(event) =>
                                        googleAdsenseForm.setField('google_adsense_ads_txt', event.target.value)
                                    }
                                    onBlur={() => googleAdsenseForm.touch('google_adsense_ads_txt')}
                                    rows={8}
                                    placeholder="google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0"
                                />
                            </Field>

                            <CodeField
                                id="google_adsense_code"
                                label="AdSense head script"
                                description="Paste the AdSense script snippet to inject into your pages."
                                value={googleAdsenseForm.data.google_adsense_code}
                                error={googleAdsenseForm.error('google_adsense_code')}
                                invalid={googleAdsenseForm.invalid('google_adsense_code')}
                                onChange={(value) => googleAdsenseForm.setField('google_adsense_code', value)}
                                onBlur={() => googleAdsenseForm.touch('google_adsense_code')}
                                placeholder={'<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-xxxx"></script>'}
                            />

                            <FieldGroup>
                                <Field orientation="horizontal">
                                    <Switch
                                        id="google_adsense_hide_for_logged_in"
                                        checked={googleAdsenseForm.data.google_adsense_hide_for_logged_in}
                                        onCheckedChange={(checked) =>
                                            googleAdsenseForm.setField('google_adsense_hide_for_logged_in', checked === true)
                                        }
                                    />
                                    <div className="flex flex-col gap-1">
                                        <FieldLabel htmlFor="google_adsense_hide_for_logged_in">Hide ads for logged-in users</FieldLabel>
                                        <FieldDescription>
                                            Useful for member areas, editors, or private dashboards.
                                        </FieldDescription>
                                    </div>
                                </Field>

                                <Field orientation="horizontal">
                                    <Switch
                                        id="google_adsense_hide_on_homepage"
                                        checked={googleAdsenseForm.data.google_adsense_hide_on_homepage}
                                        onCheckedChange={(checked) =>
                                            googleAdsenseForm.setField('google_adsense_hide_on_homepage', checked === true)
                                        }
                                    />
                                    <div className="flex flex-col gap-1">
                                        <FieldLabel htmlFor="google_adsense_hide_on_homepage">Hide ads on homepage</FieldLabel>
                                        <FieldDescription>
                                            Keep the front page ad-free while still serving ads deeper in the site.
                                        </FieldDescription>
                                    </div>
                                </Field>
                            </FieldGroup>
                        </>
                    ) : (
                        <Alert>
                            <HomeIcon />
                            <AlertTitle>AdSense is currently disabled</AlertTitle>
                            <AlertDescription>
                                Enable AdSense to reveal the script, ads.txt, and display option fields.
                            </AlertDescription>
                        </Alert>
                    )}
                </SectionCard>
            </form>
        ),
        other: (
            <form
                noValidate
                className="flex flex-col gap-6"
                onSubmit={(event) =>
                    submitForm(
                        event,
                        otherForm,
                        route('cms.integrations.other.update'),
                        'Custom head code updated',
                    )
                }
            >
                {otherForm.dirtyGuardDialog}
                <FormErrorSummary errors={otherForm.errors} minMessages={2} />
                <SectionCard
                    title="Custom head code"
                    description="Add custom scripts, styles, meta tags, or other allowed head markup."
                    footer={
                        <Button type="submit" disabled={otherForm.processing || !canManageIntegrations}>
                            {otherForm.processing ? <Spinner /> : <SaveIcon data-icon="inline-start" />}
                            Save custom code
                        </Button>
                    }
                >
                    <CodeField
                        id="other"
                        label="Custom head tags"
                        description="This content is injected into the page head after validation and sanitization."
                        value={otherForm.data.other}
                        error={otherForm.error('other')}
                        invalid={otherForm.invalid('other')}
                        onChange={(value) => otherForm.setField('other', value)}
                        onBlur={() => otherForm.touch('other')}
                        placeholder="<script>window.dataLayer = window.dataLayer || [];</script>"
                    />
                </SectionCard>
            </form>
        ),
    };

    const currentMeta = sections.find((section) => section.key === currentSection) ?? sections[0];

    return (
        <SettingsLayout
            settingsNav={settingsNav}
            activeSlug={currentSection}
            railLabel="CMS integrations"
            breadcrumbs={breadcrumbs}
            title="Integrations"
            description="Manage verification tags, analytics scripts, AdSense, and other head integrations for your CMS-driven site."
        >
            <div className="flex min-w-0 flex-col gap-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-3">
                        <Badge variant="secondary">{enabledCount} active</Badge>
                        <Badge variant={statuses[currentSection] ? 'success' : 'secondary'}>
                            {statuses[currentSection] ? 'Configured' : currentMeta.emptyLabel}
                        </Badge>
                    </div>
                    <Button variant="outline" asChild>
                        <a href="https://developers.google.com/search/docs/monitor-debug/search-console/get-started" target="_blank" rel="noreferrer">
                            <ExternalLinkIcon data-icon="inline-start" />
                            Integration docs
                        </a>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{currentMeta.title}</CardTitle>
                        <CardDescription>{currentMeta.description}</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div className="rounded-xl border bg-muted/30 px-4 py-3">
                            <p className="text-sm font-medium">Active integrations</p>
                            <p className="text-sm text-muted-foreground">{enabledCount} of {sections.length} sections configured.</p>
                        </div>
                        <div className="rounded-xl border bg-muted/30 px-4 py-3">
                            <p className="text-sm font-medium">Sanitized on save</p>
                            <p className="text-sm text-muted-foreground">Only head-safe tags are kept after validation.</p>
                        </div>
                        <div className="rounded-xl border bg-muted/30 px-4 py-3">
                            <p className="text-sm font-medium">AdSense support</p>
                            <p className="text-sm text-muted-foreground">Includes ads.txt editing and display conditions.</p>
                        </div>
                    </CardContent>
                </Card>

                {sectionMap[currentSection]}
            </div>
        </SettingsLayout>
    );
}
