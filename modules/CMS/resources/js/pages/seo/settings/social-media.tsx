import {
    ExternalLinkIcon,
    ImageIcon,
    SaveIcon,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { MediaPickerField } from '@/components/media/media-picker-field';
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
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import SeoSettingsShell from '../../../components/seo-settings-shell';
import { getSeoSettingsBreadcrumbs } from '../../../lib/seo-settings';
import type {
    SocialMediaFormValues,
    SocialMediaPageProps,
} from '../../../types/seo';

function optionalUrlValidator(label: string) {
    return (value: string) => {
        if (value.trim() === '') {
            return undefined;
        }

        try {
            new URL(value);

            return undefined;
        } catch {
            return `${label} must be a valid URL.`;
        }
    };
}

export default function SeoSocialMediaPage({
    initialValues,
    twitterCardOptions,
    openGraphImageUrl,
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerStatistics,
}: SocialMediaPageProps) {
    const form = useAppForm<SocialMediaFormValues>({
        defaults: initialValues,
        rememberKey: 'seo.settings.social-media',
        dirtyGuard: { enabled: true },
        rules: {
            twitter_username: [formValidators.minLength('X username', 2)],
            facebook_page_url: [optionalUrlValidator('Facebook page URL')],
            facebook_authorship: [
                optionalUrlValidator('Facebook authorship URL'),
            ],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('seo.settings.socialmedia.update'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Social metadata updated',
                description:
                    'Open Graph, Facebook, and X card settings were saved.',
            },
        });
    };

    return (
        <SeoSettingsShell
            breadcrumbs={getSeoSettingsBreadcrumbs('Social Media')}
            title="Social Media"
        >
            <form
                className="flex flex-col gap-6"
                onSubmit={handleSubmit}
                noValidate
            >
                {form.dirtyGuardDialog}
                <FormErrorSummary errors={form.errors} minMessages={2} />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <ImageIcon className="size-4 text-muted-foreground" />
                                    <CardTitle>Open Graph defaults</CardTitle>
                                </div>
                                <CardDescription>
                                    Provide a consistent default image and
                                    platform metadata for richer previews.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-6">
                                <Field>
                                    <FieldLabel>Open Graph image</FieldLabel>
                                    <MediaPickerField
                                        value={
                                            form.data.open_graph_image || null
                                        }
                                        previewUrl={openGraphImageUrl}
                                        onChange={(item) => {
                                            form.setField(
                                                'open_graph_image',
                                                item ? item.id : '',
                                            );
                                            form.touch('open_graph_image');
                                        }}
                                        dialogTitle="Select Open Graph image"
                                        selectLabel="Select social preview image"
                                        pickerMedia={pickerMedia}
                                        pickerFilters={pickerFilters}
                                        uploadSettings={uploadSettings}
                                        pickerStatistics={pickerStatistics}
                                        pickerAction={route(
                                            'seo.settings.socialmedia',
                                        )}
                                    />
                                    <FieldDescription>
                                        Recommended size: 1200 × 630 pixels. Use
                                        a branded, legible image.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('open_graph_image')}
                                    </FieldError>
                                </Field>

                                <FieldGroup>
                                    <Field>
                                        <FieldLabel htmlFor="twitter_username">
                                            X username
                                        </FieldLabel>
                                        <Input
                                            id="twitter_username"
                                            value={form.data.twitter_username}
                                            onChange={(event) =>
                                                form.setField(
                                                    'twitter_username',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('twitter_username')
                                            }
                                            placeholder="@yourbrand"
                                        />
                                        <FieldDescription>
                                            Used for the $twitter:site$ tag.
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('twitter_username')}
                                        </FieldError>
                                    </Field>

                                    <Field>
                                        <FieldLabel htmlFor="twitter_card_type">
                                            X card type
                                        </FieldLabel>
                                        <NativeSelect
                                            id="twitter_card_type"
                                            className="w-full"
                                            value={form.data.twitter_card_type}
                                            onChange={(event) =>
                                                form.setField(
                                                    'twitter_card_type',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            {twitterCardOptions.map(
                                                (option) => (
                                                    <NativeSelectOption
                                                        key={String(
                                                            option.value,
                                                        )}
                                                        value={String(
                                                            option.value,
                                                        )}
                                                    >
                                                        {option.label}
                                                    </NativeSelectOption>
                                                ),
                                            )}
                                        </NativeSelect>
                                    </Field>
                                </FieldGroup>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Facebook configuration</CardTitle>
                                <CardDescription>
                                    Add optional identifiers used for domain
                                    verification, app ownership, and authorship.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-6">
                                <FieldGroup>
                                    <Field>
                                        <FieldLabel htmlFor="facebook_page_url">
                                            Facebook page URL
                                        </FieldLabel>
                                        <Input
                                            id="facebook_page_url"
                                            value={form.data.facebook_page_url}
                                            onChange={(event) =>
                                                form.setField(
                                                    'facebook_page_url',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('facebook_page_url')
                                            }
                                            placeholder="https://facebook.com/yourpage"
                                        />
                                        <FieldError>
                                            {form.error('facebook_page_url')}
                                        </FieldError>
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="facebook_authorship">
                                            Facebook authorship URL
                                        </FieldLabel>
                                        <Input
                                            id="facebook_authorship"
                                            value={
                                                form.data.facebook_authorship
                                            }
                                            onChange={(event) =>
                                                form.setField(
                                                    'facebook_authorship',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch(
                                                    'facebook_authorship',
                                                )
                                            }
                                            placeholder="https://facebook.com/author-profile"
                                        />
                                        <FieldError>
                                            {form.error('facebook_authorship')}
                                        </FieldError>
                                    </Field>
                                </FieldGroup>

                                <FieldGroup>
                                    <Field>
                                        <FieldLabel htmlFor="facebook_admin">
                                            Facebook admin ID
                                        </FieldLabel>
                                        <Input
                                            id="facebook_admin"
                                            value={form.data.facebook_admin}
                                            onChange={(event) =>
                                                form.setField(
                                                    'facebook_admin',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="1234567890"
                                        />
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="facebook_app">
                                            Facebook app ID
                                        </FieldLabel>
                                        <Input
                                            id="facebook_app"
                                            value={form.data.facebook_app}
                                            onChange={(event) =>
                                                form.setField(
                                                    'facebook_app',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="1234567890"
                                        />
                                    </Field>
                                </FieldGroup>

                                <Field>
                                    <FieldLabel htmlFor="facebook_secret">
                                        Facebook app secret
                                    </FieldLabel>
                                    <Input
                                        id="facebook_secret"
                                        type="password"
                                        value={form.data.facebook_secret}
                                        onChange={(event) =>
                                            form.setField(
                                                'facebook_secret',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Optional app secret"
                                    />
                                </Field>
                            </CardContent>
                            <CardFooter className="justify-end">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing ? (
                                        <Spinner className="mr-2 size-4" />
                                    ) : (
                                        <SaveIcon data-icon="inline-start" />
                                    )}
                                    Save social settings
                                </Button>
                            </CardFooter>
                        </Card>
                    </div>

                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Preview guidance</CardTitle>
                                <CardDescription>
                                    These links explain how each platform
                                    interprets your metadata.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3 text-sm">
                                {[
                                    ['Open Graph protocol', 'https://ogp.me/'],
                                    [
                                        'Facebook sharing docs',
                                        'https://developers.facebook.com/docs/sharing/webmasters/',
                                    ],
                                    [
                                        'X cards markup',
                                        'https://developer.x.com/en/docs/x-for-websites/cards/overview/markup',
                                    ],
                                ].map(([label, href]) => (
                                    <a
                                        key={href}
                                        href={href}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center justify-between rounded-lg border px-3 py-2 hover:bg-muted/40"
                                    >
                                        <span>{label}</span>
                                        <ExternalLinkIcon className="size-4 text-muted-foreground" />
                                    </a>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </SeoSettingsShell>
    );
}
