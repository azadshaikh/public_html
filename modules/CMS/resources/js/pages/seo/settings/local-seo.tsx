import {
    Building2Icon,
    Clock3Icon,
    Globe2Icon,
    MapPinIcon,
    SaveIcon,
    SparklesIcon,
} from 'lucide-react';
import { useMemo } from 'react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { MediaPickerField } from '@/components/media/media-picker-field';
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
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import { formValidators } from '@/lib/forms';
import { getSeoSettingsBreadcrumbs, getSeoSettingsNav } from '../../../lib/seo-settings';
import type { LocalSeoFormValues, LocalSeoPageProps } from '../../../types/seo';

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

function buildScore(values: LocalSeoFormValues): {
    score: number;
    grade: string;
    completed: number;
    total: number;
} {
    const requiredFields = [
        values.name,
        values.description,
        values.url,
        values.phone,
        values.email,
        values.street_address,
        values.locality,
        values.region,
        values.postal_code,
        values.country_code,
        values.facebook_url,
        values.twitter_url,
        values.linkedin_url,
        values.instagram_url,
        values.youtube_url,
    ];

    const total = requiredFields.length + 2;
    const completed =
        requiredFields.filter((value) => value.trim() !== '').length +
        (values.logo_image ? 1 : 0) +
        (values.is_opening_hour_24_7 || values.opening_hour_day.some((day) => day.trim() !== '') ? 1 : 0);

    const score = Math.round((completed / total) * 100);
    const grade = score >= 90 ? 'A' : score >= 75 ? 'B' : score >= 60 ? 'C' : score >= 40 ? 'D' : 'F';

    return { score, grade, completed, total };
}

export default function SeoLocalSeoPage({
    initialValues,
    businessTypeOptions,
    openingDayOptions,
    logoImageUrl,
    pickerMedia,
    pickerFilters,
    uploadSettings,
}: LocalSeoPageProps) {
    const form = useAppForm<LocalSeoFormValues>({
        defaults: initialValues,
        rememberKey: 'seo.settings.local-seo',
        dirtyGuard: { enabled: true },
        rules: {
            name: [
                (value, data) =>
                    data.is_schema && value.trim() === ''
                        ? 'Business or person name is required when schema is enabled.'
                        : undefined,
            ],
            email: [formValidators.email('Email')],
            url: [optionalUrlValidator('Website URL')],
            facebook_url: [optionalUrlValidator('Facebook URL')],
            twitter_url: [optionalUrlValidator('X URL')],
            linkedin_url: [optionalUrlValidator('LinkedIn URL')],
            instagram_url: [optionalUrlValidator('Instagram URL')],
            youtube_url: [optionalUrlValidator('YouTube URL')],
        },
    });

    const organizationMode = form.data.type === 'Organization';
    const score = useMemo(() => buildScore(form.data), [form.data]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('seo.settings.localseo.update'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Local SEO settings updated',
                description: 'Business details, hours, and profile data were saved.',
            },
        });
    };

    const addHourRow = () => {
        form.setField('opening_hour_day', [...form.data.opening_hour_day, '']);
        form.setField('opening_hours', [...form.data.opening_hours, '']);
        form.setField('closing_hours', [...form.data.closing_hours, '']);
    };

    const removeHourRow = (index: number) => {
        form.setField(
            'opening_hour_day',
            form.data.opening_hour_day.filter((_, rowIndex) => rowIndex !== index),
        );
        form.setField(
            'opening_hours',
            form.data.opening_hours.filter((_, rowIndex) => rowIndex !== index),
        );
        form.setField(
            'closing_hours',
            form.data.closing_hours.filter((_, rowIndex) => rowIndex !== index),
        );
    };

    const rows = Math.max(form.data.opening_hour_day.length, 1);

    return (
        <SettingsLayout
            settingsNav={getSeoSettingsNav()}
            breadcrumbs={getSeoSettingsBreadcrumbs('Local SEO')}
            title="Local SEO"
            description="Publish structured business details for map packs, knowledge panels, and richer local search results."
            activeSlug="localseo"
            railLabel="SEO settings"
        >
            <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
                {form.dirtyGuardDialog}
                <FormErrorSummary errors={form.errors} minMessages={2} />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <Alert>
                            <SparklesIcon className="size-4" />
                            <AlertTitle>Improve local visibility</AlertTitle>
                            <AlertDescription>
                                Complete profiles with consistent business details help search engines trust and surface your brand more often.
                            </AlertDescription>
                        </Alert>

                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between gap-4">
                                    <div>
                                        <CardTitle>Structured data</CardTitle>
                                        <CardDescription>
                                            Enable schema markup and choose whether this profile represents an organisation or a person.
                                        </CardDescription>
                                    </div>
                                    <Switch
                                        checked={form.data.is_schema}
                                        onCheckedChange={(checked) =>
                                            form.setField('is_schema', checked)
                                        }
                                    />
                                </div>
                            </CardHeader>
                            {form.data.is_schema ? (
                                <CardContent className="flex flex-col gap-6">
                                    <FieldGroup>
                                        <Field>
                                            <FieldLabel htmlFor="type">Entity type</FieldLabel>
                                            <NativeSelect
                                                id="type"
                                                className="w-full"
                                                value={form.data.type}
                                                onChange={(event) =>
                                                    form.setField(
                                                        'type',
                                                        event.target.value as 'Organization' | 'Person',
                                                    )
                                                }
                                            >
                                                <NativeSelectOption value="Organization">
                                                    Organization / Business
                                                </NativeSelectOption>
                                                <NativeSelectOption value="Person">
                                                    Person / Individual
                                                </NativeSelectOption>
                                            </NativeSelect>
                                        </Field>

                                        {organizationMode ? (
                                            <Field>
                                                <FieldLabel htmlFor="business_type">
                                                    Business type
                                                </FieldLabel>
                                                <NativeSelect
                                                    id="business_type"
                                                    className="w-full"
                                                    value={form.data.business_type}
                                                    onChange={(event) =>
                                                        form.setField(
                                                            'business_type',
                                                            event.target.value,
                                                        )
                                                    }
                                                >
                                                    {businessTypeOptions.map((option) => (
                                                        <NativeSelectOption
                                                            key={String(option.value)}
                                                            value={String(option.value)}
                                                        >
                                                            {option.label}
                                                        </NativeSelectOption>
                                                    ))}
                                                </NativeSelect>
                                            </Field>
                                        ) : null}
                                    </FieldGroup>
                                </CardContent>
                            ) : null}
                        </Card>

                        {form.data.is_schema ? (
                            <>
                                <Card>
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Building2Icon className="size-4 text-muted-foreground" />
                                            <CardTitle>Basic identity</CardTitle>
                                        </div>
                                        <CardDescription>
                                            Define the public name, URL, description, and logo used in structured data.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="flex flex-col gap-6">
                                        <FieldGroup>
                                            <Field data-invalid={form.invalid('name') || undefined}>
                                                <FieldLabel htmlFor="name">Business or person name</FieldLabel>
                                                <Input
                                                    id="name"
                                                    value={form.data.name}
                                                    onChange={(event) =>
                                                        form.setField('name', event.target.value)
                                                    }
                                                    onBlur={() => form.touch('name')}
                                                    aria-invalid={form.invalid('name') || undefined}
                                                    placeholder="Acme Studio"
                                                />
                                                <FieldError>{form.error('name')}</FieldError>
                                            </Field>

                                            <Field>
                                                <FieldLabel htmlFor="url">Website URL</FieldLabel>
                                                <Input
                                                    id="url"
                                                    value={form.data.url}
                                                    onChange={(event) =>
                                                        form.setField('url', event.target.value)
                                                    }
                                                    onBlur={() => form.touch('url')}
                                                    placeholder="https://example.com"
                                                />
                                                <FieldError>{form.error('url')}</FieldError>
                                            </Field>
                                        </FieldGroup>

                                        <Field>
                                            <FieldLabel htmlFor="description">Description</FieldLabel>
                                            <Textarea
                                                id="description"
                                                rows={4}
                                                value={form.data.description}
                                                onChange={(event) =>
                                                    form.setField('description', event.target.value)
                                                }
                                                onBlur={() => form.touch('description')}
                                                placeholder="Describe what the business offers and what makes it unique."
                                            />
                                            <FieldDescription>
                                                Keep this concise and consistent with other business profiles.
                                            </FieldDescription>
                                            <FieldError>{form.error('description')}</FieldError>
                                        </Field>

                                        <Field>
                                            <FieldLabel>Logo</FieldLabel>
                                            <MediaPickerField
                                                value={form.data.logo_image || null}
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
                                                pickerAction={route('seo.settings.localseo')}
                                            />
                                            <FieldDescription>
                                                Use a square logo whenever possible for better rendering.
                                            </FieldDescription>
                                            <FieldError>{form.error('logo_image')}</FieldError>
                                        </Field>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <MapPinIcon className="size-4 text-muted-foreground" />
                                            <CardTitle>Contact and address</CardTitle>
                                        </div>
                                        <CardDescription>
                                            Match your official NAP details so search engines can confidently associate local listings.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="flex flex-col gap-6">
                                        <FieldGroup>
                                            <Field>
                                                <FieldLabel htmlFor="phone">Phone</FieldLabel>
                                                <Input
                                                    id="phone"
                                                    value={form.data.phone}
                                                    onChange={(event) =>
                                                        form.setField('phone', event.target.value)
                                                    }
                                                    onBlur={() => form.touch('phone')}
                                                    placeholder="+1 (555) 123-4567"
                                                />
                                                <FieldError>{form.error('phone')}</FieldError>
                                            </Field>
                                            <Field>
                                                <FieldLabel htmlFor="email">Email</FieldLabel>
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    value={form.data.email}
                                                    onChange={(event) =>
                                                        form.setField('email', event.target.value)
                                                    }
                                                    onBlur={() => form.touch('email')}
                                                    placeholder="hello@example.com"
                                                />
                                                <FieldError>{form.error('email')}</FieldError>
                                            </Field>
                                        </FieldGroup>

                                        <Field>
                                            <FieldLabel htmlFor="street_address">Street address</FieldLabel>
                                            <Input
                                                id="street_address"
                                                value={form.data.street_address}
                                                onChange={(event) =>
                                                    form.setField(
                                                        'street_address',
                                                        event.target.value,
                                                    )
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
                                                    value={form.data.locality}
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
                                                    value={form.data.region}
                                                    onChange={(event) =>
                                                        form.setField('region', event.target.value)
                                                    }
                                                    onBlur={() => form.touch('region')}
                                                />
                                            </Field>
                                        </FieldGroup>

                                        <FieldGroup>
                                            <Field>
                                                <FieldLabel htmlFor="postal_code">Postal code</FieldLabel>
                                                <Input
                                                    id="postal_code"
                                                    value={form.data.postal_code}
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
                                                    value={form.data.country_code}
                                                    onChange={(event) =>
                                                        form.setField('country_code', event.target.value)
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
                                                        value={form.data.geo_coordinates_latitude}
                                                        onChange={(event) =>
                                                            form.setField(
                                                                'geo_coordinates_latitude',
                                                                event.target.value,
                                                            )
                                                        }
                                                        onBlur={() =>
                                                            form.touch(
                                                                'geo_coordinates_latitude',
                                                            )
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
                                                        value={form.data.geo_coordinates_longitude}
                                                        onChange={(event) =>
                                                            form.setField(
                                                                'geo_coordinates_longitude',
                                                                event.target.value,
                                                            )
                                                        }
                                                        onBlur={() =>
                                                            form.touch(
                                                                'geo_coordinates_longitude',
                                                            )
                                                        }
                                                        placeholder="-122.4194"
                                                    />
                                                </Field>
                                            </FieldGroup>
                                        ) : null}
                                    </CardContent>
                                </Card>

                                {organizationMode ? (
                                    <Card>
                                        <CardHeader>
                                            <div className="flex items-center justify-between gap-4">
                                                <div className="flex items-center gap-2">
                                                    <Clock3Icon className="size-4 text-muted-foreground" />
                                                    <CardTitle>Business hours</CardTitle>
                                                </div>
                                                <Field orientation="horizontal">
                                                    <Switch
                                                        checked={form.data.is_opening_hour_24_7}
                                                        onCheckedChange={(checked) =>
                                                            form.setField(
                                                                'is_opening_hour_24_7',
                                                                checked,
                                                            )
                                                        }
                                                    />
                                                    <FieldLabel>Open 24/7</FieldLabel>
                                                </Field>
                                            </div>
                                            <CardDescription>
                                                Add one or more opening windows. Split shifts can be represented with multiple rows.
                                            </CardDescription>
                                        </CardHeader>
                                        {!form.data.is_opening_hour_24_7 ? (
                                            <CardContent className="flex flex-col gap-4">
                                                {Array.from({ length: rows }).map((_, index) => (
                                                    <div
                                                        key={`hour-row-${index}`}
                                                        className="grid gap-3 rounded-xl border p-4 md:grid-cols-[1.2fr_1fr_1fr_auto]"
                                                    >
                                                        <Field>
                                                            <FieldLabel htmlFor={`opening-day-${index}`}>
                                                                Day
                                                            </FieldLabel>
                                                            <NativeSelect
                                                                id={`opening-day-${index}`}
                                                                className="w-full"
                                                                value={form.data.opening_hour_day[index] ?? ''}
                                                                onChange={(event) => {
                                                                    const next = [...form.data.opening_hour_day];
                                                                    next[index] = event.target.value;
                                                                    form.setField('opening_hour_day', next);
                                                                }}
                                                            >
                                                                <NativeSelectOption value="">
                                                                    Select day
                                                                </NativeSelectOption>
                                                                {openingDayOptions.map((option) => (
                                                                    <NativeSelectOption
                                                                        key={String(option.value)}
                                                                        value={String(option.value)}
                                                                    >
                                                                        {option.label}
                                                                    </NativeSelectOption>
                                                                ))}
                                                            </NativeSelect>
                                                        </Field>
                                                        <Field>
                                                            <FieldLabel htmlFor={`opening-hours-${index}`}>
                                                                Opens
                                                            </FieldLabel>
                                                            <Input
                                                                id={`opening-hours-${index}`}
                                                                type="time"
                                                                value={form.data.opening_hours[index] ?? ''}
                                                                onChange={(event) => {
                                                                    const next = [...form.data.opening_hours];
                                                                    next[index] = event.target.value;
                                                                    form.setField('opening_hours', next);
                                                                }}
                                                            />
                                                        </Field>
                                                        <Field>
                                                            <FieldLabel htmlFor={`closing-hours-${index}`}>
                                                                Closes
                                                            </FieldLabel>
                                                            <Input
                                                                id={`closing-hours-${index}`}
                                                                type="time"
                                                                value={form.data.closing_hours[index] ?? ''}
                                                                onChange={(event) => {
                                                                    const next = [...form.data.closing_hours];
                                                                    next[index] = event.target.value;
                                                                    form.setField('closing_hours', next);
                                                                }}
                                                            />
                                                        </Field>
                                                        <div className="flex items-end">
                                                            <Button
                                                                type="button"
                                                                variant={index === 0 ? 'outline' : 'destructive'}
                                                                onClick={() =>
                                                                    index === 0 ? addHourRow() : removeHourRow(index)
                                                                }
                                                            >
                                                                {index === 0 ? 'Add row' : 'Remove'}
                                                            </Button>
                                                        </div>
                                                    </div>
                                                ))}
                                            </CardContent>
                                        ) : null}
                                    </Card>
                                ) : null}

                                <Card>
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Globe2Icon className="size-4 text-muted-foreground" />
                                            <CardTitle>Social profiles and extras</CardTitle>
                                        </div>
                                        <CardDescription>
                                            Connect official profiles to strengthen entity matching across search engines and assistants.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="flex flex-col gap-6">
                                        <FieldGroup>
                                            <Field>
                                                <FieldLabel htmlFor="facebook_url">Facebook</FieldLabel>
                                                <Input
                                                    id="facebook_url"
                                                    value={form.data.facebook_url}
                                                    onChange={(event) =>
                                                        form.setField('facebook_url', event.target.value)
                                                    }
                                                    onBlur={() => form.touch('facebook_url')}
                                                    placeholder="https://facebook.com/yourpage"
                                                />
                                                <FieldError>{form.error('facebook_url')}</FieldError>
                                            </Field>
                                            <Field>
                                                <FieldLabel htmlFor="twitter_url">X</FieldLabel>
                                                <Input
                                                    id="twitter_url"
                                                    value={form.data.twitter_url}
                                                    onChange={(event) =>
                                                        form.setField('twitter_url', event.target.value)
                                                    }
                                                    onBlur={() => form.touch('twitter_url')}
                                                    placeholder="https://x.com/yourhandle"
                                                />
                                                <FieldError>{form.error('twitter_url')}</FieldError>
                                            </Field>
                                        </FieldGroup>
                                        <FieldGroup>
                                            <Field>
                                                <FieldLabel htmlFor="linkedin_url">LinkedIn</FieldLabel>
                                                <Input
                                                    id="linkedin_url"
                                                    value={form.data.linkedin_url}
                                                    onChange={(event) =>
                                                        form.setField('linkedin_url', event.target.value)
                                                    }
                                                    onBlur={() => form.touch('linkedin_url')}
                                                    placeholder="https://linkedin.com/company/yourcompany"
                                                />
                                                <FieldError>{form.error('linkedin_url')}</FieldError>
                                            </Field>
                                            <Field>
                                                <FieldLabel htmlFor="instagram_url">Instagram</FieldLabel>
                                                <Input
                                                    id="instagram_url"
                                                    value={form.data.instagram_url}
                                                    onChange={(event) =>
                                                        form.setField('instagram_url', event.target.value)
                                                    }
                                                    onBlur={() => form.touch('instagram_url')}
                                                    placeholder="https://instagram.com/yourprofile"
                                                />
                                                <FieldError>{form.error('instagram_url')}</FieldError>
                                            </Field>
                                        </FieldGroup>
                                        <FieldGroup>
                                            <Field>
                                                <FieldLabel htmlFor="youtube_url">YouTube</FieldLabel>
                                                <Input
                                                    id="youtube_url"
                                                    value={form.data.youtube_url}
                                                    onChange={(event) =>
                                                        form.setField('youtube_url', event.target.value)
                                                    }
                                                    onBlur={() => form.touch('youtube_url')}
                                                    placeholder="https://youtube.com/@yourchannel"
                                                />
                                                <FieldError>{form.error('youtube_url')}</FieldError>
                                            </Field>
                                            {organizationMode ? (
                                                <>
                                                    <Field>
                                                        <FieldLabel htmlFor="price_range">Price range</FieldLabel>
                                                        <NativeSelect
                                                            id="price_range"
                                                            className="w-full"
                                                            value={form.data.price_range}
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
                                                            <NativeSelectOption value="$">$</NativeSelectOption>
                                                            <NativeSelectOption value="$$">$$</NativeSelectOption>
                                                            <NativeSelectOption value="$$$">$$$</NativeSelectOption>
                                                            <NativeSelectOption value="$$$$">$$$$</NativeSelectOption>
                                                        </NativeSelect>
                                                    </Field>
                                                    <Field>
                                                        <FieldLabel htmlFor="founding_date">
                                                            Founded date
                                                        </FieldLabel>
                                                        <Input
                                                            id="founding_date"
                                                            type="date"
                                                            value={form.data.founding_date}
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
                            </>
                        ) : null}
                    </div>

                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Completion score</CardTitle>
                                <CardDescription>
                                    A quick view of how complete the local profile is for search engines.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <div className="flex items-end justify-between gap-3">
                                    <div>
                                        <div className="text-4xl font-semibold tabular-nums">
                                            {score.score}
                                            <span className="text-lg text-muted-foreground">%</span>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {score.completed} of {score.total} recommended signals completed.
                                        </p>
                                    </div>
                                    <Badge variant="secondary">Grade {score.grade}</Badge>
                                </div>
                                <div className="h-2 rounded-full bg-muted">
                                    <div
                                        className="h-2 rounded-full bg-primary transition-all"
                                        style={{ width: `${score.score}%` }}
                                    />
                                </div>
                                <Alert>
                                    <SparklesIcon className="size-4" />
                                    <AlertTitle>Recommended next step</AlertTitle>
                                    <AlertDescription>
                                        {score.score >= 80
                                            ? 'Your profile is in strong shape. Keep hours and profile links updated.'
                                            : 'Add a logo, full address, and official social links to improve trust signals.'}
                                    </AlertDescription>
                                </Alert>
                            </CardContent>
                            <CardFooter>
                                <Button type="submit" className="w-full" disabled={form.processing}>
                                    {form.processing ? (
                                        <Spinner className="mr-2 size-4" />
                                    ) : (
                                        <SaveIcon data-icon="inline-start" />
                                    )}
                                    Save local SEO settings
                                </Button>
                            </CardFooter>
                        </Card>
                    </div>
                </div>
            </form>
        </SettingsLayout>
    );
}
