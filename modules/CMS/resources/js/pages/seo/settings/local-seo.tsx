import { useMemo } from 'react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import SeoSettingsShell from '../../../components/seo-settings-shell';
import { getSeoSettingsBreadcrumbs } from '../../../lib/seo-settings';
import type { LocalSeoFormValues, LocalSeoPageProps } from '../../../types/seo';
import { LocalSeoBusinessHoursCard } from './components/local-seo-business-hours-card';
import {
    buildScore,
    optionalUrlValidator,
} from './components/local-seo-helpers';
import {
    LocalSeoBasicIdentityCard,
    LocalSeoContactCard,
} from './components/local-seo-profile-cards';
import { LocalSeoScoreCard } from './components/local-seo-score-card';
import { LocalSeoSocialProfilesCard } from './components/local-seo-social-profiles-card';
import { LocalSeoStructuredDataCard } from './components/local-seo-structured-data-card';

export default function SeoLocalSeoPage({
    initialValues,
    businessTypeOptions,
    openingDayOptions,
    logoImageUrl,
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerStatistics,
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
    const formBindings = {
        values: form.data,
        errors: form.errors,
        invalid: form.invalid,
        touch: form.touch,
        setField: form.setField,
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('seo.settings.localseo.update'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Local SEO settings updated',
                description:
                    'Business details, hours, and profile data were saved.',
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
            form.data.opening_hour_day.filter(
                (_, rowIndex) => rowIndex !== index,
            ),
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
        <SeoSettingsShell
            breadcrumbs={getSeoSettingsBreadcrumbs('Local SEO')}
            title="Local SEO"
        >
            <form
                className="flex flex-col gap-6"
                onSubmit={handleSubmit}
                noValidate
            >
                {form.dirtyGuardDialog}
                <FormErrorSummary errors={form.errors} minMessages={2} />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <LocalSeoStructuredDataCard
                            form={formBindings}
                            businessTypeOptions={businessTypeOptions}
                            organizationMode={organizationMode}
                        />

                        {form.data.is_schema ? (
                            <>
                                <LocalSeoBasicIdentityCard
                                    form={formBindings}
                                    organizationMode={organizationMode}
                                    logoImageUrl={logoImageUrl}
                                    pickerMedia={pickerMedia}
                                    pickerFilters={pickerFilters}
                                    uploadSettings={uploadSettings}
                                    pickerStatistics={pickerStatistics}
                                />

                                <LocalSeoContactCard
                                    form={formBindings}
                                    organizationMode={organizationMode}
                                    logoImageUrl={logoImageUrl}
                                    pickerMedia={pickerMedia}
                                    pickerFilters={pickerFilters}
                                    uploadSettings={uploadSettings}
                                    pickerStatistics={pickerStatistics}
                                />

                                {organizationMode ? (
                                    <LocalSeoBusinessHoursCard
                                        form={formBindings}
                                        rows={rows}
                                        openingDayOptions={openingDayOptions}
                                        onAddHourRow={addHourRow}
                                        onRemoveHourRow={removeHourRow}
                                    />
                                ) : null}

                                <LocalSeoSocialProfilesCard
                                    form={formBindings}
                                    organizationMode={organizationMode}
                                />
                            </>
                        ) : null}
                    </div>

                    <div className="flex flex-col gap-4">
                        <LocalSeoScoreCard
                            score={score}
                            processing={form.processing}
                        />
                    </div>
                </div>
            </form>
        </SeoSettingsShell>
    );
}
