import type { FormDataType } from '@inertiajs/core';
import { SearchCheckIcon, HomeIcon } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { useAppForm } from '@/hooks/use-app-form';
import type {
    GoogleAdsenseSettings,
    GoogleAnalyticsSettings,
    GoogleTagsSettings,
    IntegrationSectionKey,
    MetaPixelSettings,
    MicrosoftClaritySettings,
    OtherIntegrationSettings,
    WebmasterToolsSettings,
} from '../../../../types/cms';
import {
    IntegrationCodeField,
    IntegrationSectionCard,
    IntegrationSubmitButton,
} from './integration-section-shell';

export type WebmasterToolsFormValues = WebmasterToolsSettings & {
    section: 'webmaster_tools';
};

export type GoogleAnalyticsFormValues = GoogleAnalyticsSettings & {
    section: 'google_analytics';
};

export type GoogleTagsFormValues = GoogleTagsSettings & {
    section: 'google_tags';
};

export type MetaPixelFormValues = MetaPixelSettings & {
    section: 'meta_pixel';
};

export type MicrosoftClarityFormValues = MicrosoftClaritySettings & {
    section: 'microsoft_clarity';
};

export type GoogleAdsenseFormValues = GoogleAdsenseSettings & {
    section: 'google_adsense';
};

export type OtherIntegrationFormValues = OtherIntegrationSettings & {
    section: 'other';
};

export type IntegrationForms = {
    webmasterForm: ReturnType<typeof useAppForm<WebmasterToolsFormValues>>;
    googleAnalyticsForm: ReturnType<
        typeof useAppForm<GoogleAnalyticsFormValues>
    >;
    googleTagsForm: ReturnType<typeof useAppForm<GoogleTagsFormValues>>;
    metaPixelForm: ReturnType<typeof useAppForm<MetaPixelFormValues>>;
    microsoftClarityForm: ReturnType<
        typeof useAppForm<MicrosoftClarityFormValues>
    >;
    googleAdsenseForm: ReturnType<typeof useAppForm<GoogleAdsenseFormValues>>;
    otherForm: ReturnType<typeof useAppForm<OtherIntegrationFormValues>>;
};

type IntegrationFormsMapArgs = {
    canManageIntegrations: boolean;
    forms: IntegrationForms;
    submitForm: <T extends FormDataType<T>>(
        event: FormEvent<HTMLFormElement>,
        form: ReturnType<typeof useAppForm<T>>,
        url: string,
        title: string,
    ) => void;
};

export function buildIntegrationSectionMap({
    canManageIntegrations,
    forms,
    submitForm,
}: IntegrationFormsMapArgs): Record<IntegrationSectionKey, ReactNode> {
    const {
        webmasterForm,
        googleAnalyticsForm,
        googleTagsForm,
        metaPixelForm,
        microsoftClarityForm,
        googleAdsenseForm,
        otherForm,
    } = forms;

    return {
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
                <IntegrationSectionCard
                    title="Webmaster verification"
                    description="Paste the verification meta tags provided by search engines and directory services."
                    footer={
                        <IntegrationSubmitButton
                            processing={webmasterForm.processing}
                            disabled={!canManageIntegrations}
                            label="Save verification tags"
                        />
                    }
                >
                    <Alert>
                        <SearchCheckIcon />
                        <AlertTitle>Head-safe tags only</AlertTitle>
                        <AlertDescription>
                            Only valid head tags such as <code>&lt;meta&gt;</code>,{' '}
                            <code>&lt;script&gt;</code>, <code>&lt;link&gt;</code>,{' '}
                            <code>&lt;style&gt;</code>, and <code>&lt;noscript&gt;</code> are kept on save.
                        </AlertDescription>
                    </Alert>
                    <FieldGroup>
                        <IntegrationCodeField
                            id="google_search_console"
                            label="Google Search Console"
                            description="Paste the full verification meta tag from Google Search Console."
                            value={webmasterForm.data.google_search_console}
                            error={webmasterForm.error('google_search_console')}
                            invalid={webmasterForm.invalid('google_search_console')}
                            onChange={(value) =>
                                webmasterForm.setField('google_search_console', value)
                            }
                            onBlur={() => webmasterForm.touch('google_search_console')}
                            height="8rem"
                            placeholder='<meta name="google-site-verification" content="your-code" />'
                        />
                        <IntegrationCodeField
                            id="bing_webmaster"
                            label="Bing Webmaster Tools"
                            description="Paste the verification meta tag from Bing Webmaster Tools."
                            value={webmasterForm.data.bing_webmaster}
                            error={webmasterForm.error('bing_webmaster')}
                            invalid={webmasterForm.invalid('bing_webmaster')}
                            onChange={(value) => webmasterForm.setField('bing_webmaster', value)}
                            onBlur={() => webmasterForm.touch('bing_webmaster')}
                            height="8rem"
                            placeholder='<meta name="msvalidate.01" content="your-code" />'
                        />
                        <IntegrationCodeField
                            id="baidu_webmaster"
                            label="Baidu Webmaster"
                            description="Paste the verification meta tag from Baidu Webmaster."
                            value={webmasterForm.data.baidu_webmaster}
                            error={webmasterForm.error('baidu_webmaster')}
                            invalid={webmasterForm.invalid('baidu_webmaster')}
                            onChange={(value) => webmasterForm.setField('baidu_webmaster', value)}
                            onBlur={() => webmasterForm.touch('baidu_webmaster')}
                            height="8rem"
                            placeholder='<meta name="baidu-site-verification" content="your-code" />'
                        />
                        <IntegrationCodeField
                            id="yandex_verification"
                            label="Yandex Webmaster"
                            description="Paste the verification meta tag from Yandex Webmaster."
                            value={webmasterForm.data.yandex_verification}
                            error={webmasterForm.error('yandex_verification')}
                            invalid={webmasterForm.invalid('yandex_verification')}
                            onChange={(value) =>
                                webmasterForm.setField('yandex_verification', value)
                            }
                            onBlur={() => webmasterForm.touch('yandex_verification')}
                            height="8rem"
                            placeholder='<meta name="yandex-verification" content="your-code" />'
                        />
                        <IntegrationCodeField
                            id="pinterest_verification"
                            label="Pinterest"
                            description="Paste the verification meta tag from Pinterest."
                            value={webmasterForm.data.pinterest_verification}
                            error={webmasterForm.error('pinterest_verification')}
                            invalid={webmasterForm.invalid('pinterest_verification')}
                            onChange={(value) =>
                                webmasterForm.setField('pinterest_verification', value)
                            }
                            onBlur={() => webmasterForm.touch('pinterest_verification')}
                            height="8rem"
                            placeholder='<meta name="p:domain_verify" content="your-code" />'
                        />
                        <IntegrationCodeField
                            id="norton_verification"
                            label="Norton Safe Web"
                            description="Paste the verification meta tag from Norton Safe Web."
                            value={webmasterForm.data.norton_verification}
                            error={webmasterForm.error('norton_verification')}
                            invalid={webmasterForm.invalid('norton_verification')}
                            onChange={(value) =>
                                webmasterForm.setField('norton_verification', value)
                            }
                            onBlur={() => webmasterForm.touch('norton_verification')}
                            height="8rem"
                            placeholder='<meta name="norton-safeweb-site-verification" content="your-code" />'
                        />
                        <IntegrationCodeField
                            id="custom_meta_tags"
                            label="Other meta tags"
                            description="Add any additional webmaster verification tags not covered above."
                            value={webmasterForm.data.custom_meta_tags}
                            error={webmasterForm.error('custom_meta_tags')}
                            invalid={webmasterForm.invalid('custom_meta_tags')}
                            onChange={(value) =>
                                webmasterForm.setField('custom_meta_tags', value)
                            }
                            onBlur={() => webmasterForm.touch('custom_meta_tags')}
                            height="12rem"
                            placeholder='<meta name="example" content="value" />'
                        />
                    </FieldGroup>
                </IntegrationSectionCard>
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
                <IntegrationSectionCard
                    title="Google Analytics"
                    description="Paste the Google Analytics tracking script you want injected into the document head."
                    footer={
                        <IntegrationSubmitButton
                            processing={googleAnalyticsForm.processing}
                            disabled={!canManageIntegrations}
                            label="Save Google Analytics"
                        />
                    }
                >
                    <IntegrationCodeField
                        id="google_analytics"
                        label="Tracking script"
                        description="Paste the full analytics script snippet from Google Analytics."
                        value={googleAnalyticsForm.data.google_analytics}
                        error={googleAnalyticsForm.error('google_analytics')}
                        invalid={googleAnalyticsForm.invalid('google_analytics')}
                        onChange={(value) =>
                            googleAnalyticsForm.setField('google_analytics', value)
                        }
                        onBlur={() => googleAnalyticsForm.touch('google_analytics')}
                        placeholder='<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXX"></script>'
                    />
                </IntegrationSectionCard>
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
                <IntegrationSectionCard
                    title="Google Tag Manager"
                    description="Add the script snippet provided by Google Tag Manager."
                    footer={
                        <IntegrationSubmitButton
                            processing={googleTagsForm.processing}
                            disabled={!canManageIntegrations}
                            label="Save Tag Manager"
                        />
                    }
                >
                    <IntegrationCodeField
                        id="google_tags"
                        label="Tag Manager script"
                        description="Paste the full Google Tag Manager snippet."
                        value={googleTagsForm.data.google_tags}
                        error={googleTagsForm.error('google_tags')}
                        invalid={googleTagsForm.invalid('google_tags')}
                        onChange={(value) =>
                            googleTagsForm.setField('google_tags', value)
                        }
                        onBlur={() => googleTagsForm.touch('google_tags')}
                        placeholder="<script>(function(w,d,s,l,i){...})(window,document,'script','dataLayer','GTM-XXXX');</script>"
                    />
                </IntegrationSectionCard>
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
                <IntegrationSectionCard
                    title="Meta Pixel"
                    description="Add the Meta Pixel script used for conversion and audience tracking."
                    footer={
                        <IntegrationSubmitButton
                            processing={metaPixelForm.processing}
                            disabled={!canManageIntegrations}
                            label="Save Meta Pixel"
                        />
                    }
                >
                    <IntegrationCodeField
                        id="meta_pixel"
                        label="Meta Pixel code"
                        description="Paste the full Meta Pixel code provided in Meta Events Manager."
                        value={metaPixelForm.data.meta_pixel}
                        error={metaPixelForm.error('meta_pixel')}
                        invalid={metaPixelForm.invalid('meta_pixel')}
                        onChange={(value) =>
                            metaPixelForm.setField('meta_pixel', value)
                        }
                        onBlur={() => metaPixelForm.touch('meta_pixel')}
                        placeholder="<script>!function(f,b,e,v,n,t,s){...}</script>"
                    />
                </IntegrationSectionCard>
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
                <IntegrationSectionCard
                    title="Microsoft Clarity"
                    description="Inject the Clarity tag to enable heatmaps and session replays."
                    footer={
                        <IntegrationSubmitButton
                            processing={microsoftClarityForm.processing}
                            disabled={!canManageIntegrations}
                            label="Save Clarity"
                        />
                    }
                >
                    <IntegrationCodeField
                        id="ms_clarity"
                        label="Clarity script"
                        description="Paste the Clarity script from Microsoft Clarity."
                        value={microsoftClarityForm.data.ms_clarity}
                        error={microsoftClarityForm.error('ms_clarity')}
                        invalid={microsoftClarityForm.invalid('ms_clarity')}
                        onChange={(value) =>
                            microsoftClarityForm.setField('ms_clarity', value)
                        }
                        onBlur={() => microsoftClarityForm.touch('ms_clarity')}
                        placeholder='<script type="text/javascript">(function(c,l,a,r,i,t,y){...}</script>'
                    />
                </IntegrationSectionCard>
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
                <IntegrationSectionCard
                    title="Google AdSense"
                    description="Enable AdSense, manage the injected script, and control where ads appear."
                    footer={
                        <IntegrationSubmitButton
                            processing={googleAdsenseForm.processing}
                            disabled={!canManageIntegrations}
                            label="Save AdSense settings"
                        />
                    }
                >
                    <Field orientation="horizontal">
                        <Switch
                            id="google_adsense_enabled"
                            checked={googleAdsenseForm.data.google_adsense_enabled}
                            onCheckedChange={(checked) =>
                                googleAdsenseForm.setField(
                                    'google_adsense_enabled',
                                    checked === true,
                                )
                            }
                        />
                        <div className="flex flex-col gap-1">
                            <FieldLabel htmlFor="google_adsense_enabled">
                                Enable Google AdSense
                            </FieldLabel>
                            <FieldDescription>
                                Turn AdSense on globally before configuring ads.txt or display rules.
                            </FieldDescription>
                        </div>
                    </Field>

                    {googleAdsenseForm.data.google_adsense_enabled ? (
                        <>
                            <Field>
                                <FieldLabel htmlFor="google_adsense_ads_txt">
                                    ads.txt content
                                </FieldLabel>
                                <FieldDescription>
                                    This content is written to the public ads.txt file.
                                </FieldDescription>
                                <Textarea
                                    id="google_adsense_ads_txt"
                                    value={googleAdsenseForm.data.google_adsense_ads_txt}
                                    onChange={(event) =>
                                        googleAdsenseForm.setField(
                                            'google_adsense_ads_txt',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() =>
                                        googleAdsenseForm.touch('google_adsense_ads_txt')
                                    }
                                    rows={8}
                                    placeholder="google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0"
                                />
                            </Field>

                            <IntegrationCodeField
                                id="google_adsense_code"
                                label="AdSense head script"
                                description="Paste the AdSense script snippet to inject into your pages."
                                value={googleAdsenseForm.data.google_adsense_code}
                                error={googleAdsenseForm.error('google_adsense_code')}
                                invalid={googleAdsenseForm.invalid('google_adsense_code')}
                                onChange={(value) =>
                                    googleAdsenseForm.setField('google_adsense_code', value)
                                }
                                onBlur={() =>
                                    googleAdsenseForm.touch('google_adsense_code')
                                }
                                placeholder='<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-xxxx"></script>'
                            />

                            <FieldGroup>
                                <Field orientation="horizontal">
                                    <Switch
                                        id="google_adsense_hide_for_logged_in"
                                        checked={googleAdsenseForm.data.google_adsense_hide_for_logged_in}
                                        onCheckedChange={(checked) =>
                                            googleAdsenseForm.setField(
                                                'google_adsense_hide_for_logged_in',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <div className="flex flex-col gap-1">
                                        <FieldLabel htmlFor="google_adsense_hide_for_logged_in">
                                            Hide ads for logged-in users
                                        </FieldLabel>
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
                                            googleAdsenseForm.setField(
                                                'google_adsense_hide_on_homepage',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <div className="flex flex-col gap-1">
                                        <FieldLabel htmlFor="google_adsense_hide_on_homepage">
                                            Hide ads on homepage
                                        </FieldLabel>
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
                </IntegrationSectionCard>
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
                <IntegrationSectionCard
                    title="Custom head code"
                    description="Add custom scripts, styles, meta tags, or other allowed head markup."
                    footer={
                        <IntegrationSubmitButton
                            processing={otherForm.processing}
                            disabled={!canManageIntegrations}
                            label="Save custom code"
                        />
                    }
                >
                    <IntegrationCodeField
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
                </IntegrationSectionCard>
            </form>
        ),
    };
}
