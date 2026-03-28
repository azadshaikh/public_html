import { Globe2Icon } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import type { LocalSeoFormBindings } from './local-seo-form-shared';

type LocalSeoSocialProfilesCardProps = {
    form: LocalSeoFormBindings;
    organizationMode: boolean;
};

export function LocalSeoSocialProfilesCard({
    form,
    organizationMode,
}: LocalSeoSocialProfilesCardProps) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center gap-2">
                    <Globe2Icon className="size-4 text-muted-foreground" />
                    <CardTitle>Social profiles and extras</CardTitle>
                </div>
                <CardDescription>
                    Connect official profiles to strengthen entity matching
                    across search engines and assistants.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-6">
                <FieldGroup>
                    <Field>
                        <FieldLabel htmlFor="facebook_url">Facebook</FieldLabel>
                        <Input
                            id="facebook_url"
                            value={form.values.facebook_url}
                            onChange={(event) =>
                                form.setField(
                                    'facebook_url',
                                    event.target.value,
                                )
                            }
                            onBlur={() => form.touch('facebook_url')}
                            placeholder="https://facebook.com/yourpage"
                        />
                        <FieldError>{form.errors.facebook_url}</FieldError>
                    </Field>
                    <Field>
                        <FieldLabel htmlFor="twitter_url">X</FieldLabel>
                        <Input
                            id="twitter_url"
                            value={form.values.twitter_url}
                            onChange={(event) =>
                                form.setField(
                                    'twitter_url',
                                    event.target.value,
                                )
                            }
                            onBlur={() => form.touch('twitter_url')}
                            placeholder="https://x.com/yourhandle"
                        />
                        <FieldError>{form.errors.twitter_url}</FieldError>
                    </Field>
                </FieldGroup>
                <FieldGroup>
                    <Field>
                        <FieldLabel htmlFor="linkedin_url">LinkedIn</FieldLabel>
                        <Input
                            id="linkedin_url"
                            value={form.values.linkedin_url}
                            onChange={(event) =>
                                form.setField(
                                    'linkedin_url',
                                    event.target.value,
                                )
                            }
                            onBlur={() => form.touch('linkedin_url')}
                            placeholder="https://linkedin.com/company/yourcompany"
                        />
                        <FieldError>{form.errors.linkedin_url}</FieldError>
                    </Field>
                    <Field>
                        <FieldLabel htmlFor="instagram_url">
                            Instagram
                        </FieldLabel>
                        <Input
                            id="instagram_url"
                            value={form.values.instagram_url}
                            onChange={(event) =>
                                form.setField(
                                    'instagram_url',
                                    event.target.value,
                                )
                            }
                            onBlur={() => form.touch('instagram_url')}
                            placeholder="https://instagram.com/yourprofile"
                        />
                        <FieldError>{form.errors.instagram_url}</FieldError>
                    </Field>
                </FieldGroup>
                <FieldGroup>
                    <Field>
                        <FieldLabel htmlFor="youtube_url">YouTube</FieldLabel>
                        <Input
                            id="youtube_url"
                            value={form.values.youtube_url}
                            onChange={(event) =>
                                form.setField(
                                    'youtube_url',
                                    event.target.value,
                                )
                            }
                            onBlur={() => form.touch('youtube_url')}
                            placeholder="https://youtube.com/@yourchannel"
                        />
                        <FieldError>{form.errors.youtube_url}</FieldError>
                    </Field>
                    {organizationMode ? (
                        <>
                            <Field>
                                <FieldLabel htmlFor="price_range">
                                    Price range
                                </FieldLabel>
                                <NativeSelect
                                    id="price_range"
                                    className="w-full"
                                    value={form.values.price_range}
                                    onChange={(event) =>
                                        form.setField(
                                            'price_range',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <NativeSelectOption value="">
                                        Not specified
                                    </NativeSelectOption>
                                    <NativeSelectOption value="$">
                                        $
                                    </NativeSelectOption>
                                    <NativeSelectOption value="$$">
                                        $$
                                    </NativeSelectOption>
                                    <NativeSelectOption value="$$$">
                                        $$$
                                    </NativeSelectOption>
                                    <NativeSelectOption value="$$$$">
                                        $$$$
                                    </NativeSelectOption>
                                </NativeSelect>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="founding_date">
                                    Founded date
                                </FieldLabel>
                                <Input
                                    id="founding_date"
                                    type="date"
                                    value={form.values.founding_date}
                                    onChange={(event) =>
                                        form.setField(
                                            'founding_date',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </>
                    ) : null}
                </FieldGroup>
            </CardContent>
        </Card>
    );
}
