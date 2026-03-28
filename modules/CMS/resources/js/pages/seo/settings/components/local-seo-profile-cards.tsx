import { Building2Icon, MapPinIcon } from 'lucide-react';
import { MediaPickerField } from '@/components/media/media-picker-field';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type {
    LocalSeoFormBindings,
    LocalSeoPickerProps,
} from './local-seo-form-shared';

type LocalSeoProfileCardsProps = LocalSeoPickerProps & {
    form: LocalSeoFormBindings;
    organizationMode: boolean;
};

export function LocalSeoBasicIdentityCard({
    form,
    logoImageUrl,
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerStatistics,
}: LocalSeoProfileCardsProps) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center gap-2">
                    <Building2Icon className="size-4 text-muted-foreground" />
                    <CardTitle>Basic identity</CardTitle>
                </div>
                <CardDescription>
                    Define the public name, URL, description, and logo used in
                    structured data.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-6">
                <FieldGroup>
                    <Field data-invalid={form.invalid('name') || undefined}>
                        <FieldLabel htmlFor="name">
                            Business or person name
                        </FieldLabel>
                        <Input
                            id="name"
                            value={form.values.name}
                            onChange={(event) =>
                                form.setField('name', event.target.value)
                            }
                            onBlur={() => form.touch('name')}
                            aria-invalid={form.invalid('name') || undefined}
                            placeholder="Acme Studio"
                        />
                        <FieldError>{form.errors.name}</FieldError>
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="url">Website URL</FieldLabel>
                        <Input
                            id="url"
                            value={form.values.url}
                            onChange={(event) =>
                                form.setField('url', event.target.value)
                            }
                            onBlur={() => form.touch('url')}
                            placeholder="https://example.com"
                        />
                        <FieldError>{form.errors.url}</FieldError>
                    </Field>
                </FieldGroup>

                <Field>
                    <FieldLabel htmlFor="description">Description</FieldLabel>
                    <Textarea
                        id="description"
                        rows={4}
                        value={form.values.description}
                        onChange={(event) =>
                            form.setField('description', event.target.value)
                        }
                        onBlur={() => form.touch('description')}
                        placeholder="Describe what the business offers and what makes it unique."
                    />
                    <FieldDescription>
                        Keep this concise and consistent with other business
                        profiles.
                    </FieldDescription>
                    <FieldError>{form.errors.description}</FieldError>
                </Field>

                <Field>
                    <FieldLabel>Logo</FieldLabel>
                    <MediaPickerField
                        value={form.values.logo_image || null}
                        previewUrl={logoImageUrl}
                        onChange={(item) => {
                            form.setField('logo_image', item ? item.id : '');
                            form.touch('logo_image');
                        }}
                        dialogTitle="Select business logo"
                        selectLabel="Select business logo"
                        pickerMedia={pickerMedia}
                        pickerFilters={pickerFilters}
                        uploadSettings={uploadSettings}
                        pickerStatistics={pickerStatistics}
                        pickerAction={route('seo.settings.localseo')}
                    />
                    <FieldDescription>
                        Use a square logo whenever possible for better
                        rendering.
                    </FieldDescription>
                    <FieldError>{form.errors.logo_image}</FieldError>
                </Field>
            </CardContent>
        </Card>
    );
}

export function LocalSeoContactCard({
    form,
    organizationMode,
}: LocalSeoProfileCardsProps) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center gap-2">
                    <MapPinIcon className="size-4 text-muted-foreground" />
                    <CardTitle>Contact and address</CardTitle>
                </div>
                <CardDescription>
                    Match your official NAP details so search engines can
                    confidently associate local listings.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-6">
                <FieldGroup>
                    <Field>
                        <FieldLabel htmlFor="phone">Phone</FieldLabel>
                        <Input
                            id="phone"
                            value={form.values.phone}
                            onChange={(event) =>
                                form.setField('phone', event.target.value)
                            }
                            onBlur={() => form.touch('phone')}
                            placeholder="+1 (555) 123-4567"
                        />
                        <FieldError>{form.errors.phone}</FieldError>
                    </Field>
                    <Field>
                        <FieldLabel htmlFor="email">Email</FieldLabel>
                        <Input
                            id="email"
                            type="email"
                            value={form.values.email}
                            onChange={(event) =>
                                form.setField('email', event.target.value)
                            }
                            onBlur={() => form.touch('email')}
                            placeholder="hello@example.com"
                        />
                        <FieldError>{form.errors.email}</FieldError>
                    </Field>
                </FieldGroup>

                <Field>
                    <FieldLabel htmlFor="street_address">
                        Street address
                    </FieldLabel>
                    <Input
                        id="street_address"
                        value={form.values.street_address}
                        onChange={(event) =>
                            form.setField('street_address', event.target.value)
                        }
                        onBlur={() => form.touch('street_address')}
                        placeholder="123 Main Street"
                    />
                </Field>

                <FieldGroup>
                    <Field>
                        <FieldLabel htmlFor="locality">City</FieldLabel>
                        <Input
                            id="locality"
                            value={form.values.locality}
                            onChange={(event) =>
                                form.setField('locality', event.target.value)
                            }
                            onBlur={() => form.touch('locality')}
                        />
                    </Field>
                    <Field>
                        <FieldLabel htmlFor="region">Region</FieldLabel>
                        <Input
                            id="region"
                            value={form.values.region}
                            onChange={(event) =>
                                form.setField('region', event.target.value)
                            }
                            onBlur={() => form.touch('region')}
                        />
                    </Field>
                </FieldGroup>

                <FieldGroup>
                    <Field>
                        <FieldLabel htmlFor="postal_code">
                            Postal code
                        </FieldLabel>
                        <Input
                            id="postal_code"
                            value={form.values.postal_code}
                            onChange={(event) =>
                                form.setField('postal_code', event.target.value)
                            }
                            onBlur={() => form.touch('postal_code')}
                        />
                    </Field>
                    <Field>
                        <FieldLabel htmlFor="country_code">Country</FieldLabel>
                        <Input
                            id="country_code"
                            value={form.values.country_code}
                            onChange={(event) =>
                                form.setField(
                                    'country_code',
                                    event.target.value,
                                )
                            }
                            onBlur={() => form.touch('country_code')}
                            placeholder="US"
                        />
                    </Field>
                </FieldGroup>

                {organizationMode ? (
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor="geo_coordinates_latitude">
                                Latitude
                            </FieldLabel>
                            <Input
                                id="geo_coordinates_latitude"
                                value={form.values.geo_coordinates_latitude}
                                onChange={(event) =>
                                    form.setField(
                                        'geo_coordinates_latitude',
                                        event.target.value,
                                    )
                                }
                                onBlur={() =>
                                    form.touch('geo_coordinates_latitude')
                                }
                                placeholder="37.7749"
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="geo_coordinates_longitude">
                                Longitude
                            </FieldLabel>
                            <Input
                                id="geo_coordinates_longitude"
                                value={form.values.geo_coordinates_longitude}
                                onChange={(event) =>
                                    form.setField(
                                        'geo_coordinates_longitude',
                                        event.target.value,
                                    )
                                }
                                onBlur={() =>
                                    form.touch('geo_coordinates_longitude')
                                }
                                placeholder="-122.4194"
                            />
                        </Field>
                    </FieldGroup>
                ) : null}
            </CardContent>
        </Card>
    );
}
