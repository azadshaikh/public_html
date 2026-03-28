import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { useAppForm } from '@/hooks/use-app-form';
import type {
    AgencyFormValues,
    PlatformMediaPickerPageProps,
    PlatformOption,
} from '../../types/platform';
import {
    AgencyFormMainSections,
    AgencyFormSidebar,
    buildWebsiteIdExample,
} from './agency-form-sections';

type AgencyFormProps = {
    mode: 'create' | 'edit';
    agency?: {
        id: number;
        name: string;
    };
    initialValues: AgencyFormValues;
    typeOptions: PlatformOption[];
    ownerOptions: PlatformOption[];
    planOptions: PlatformOption[];
    statusOptions: PlatformOption[];
    websiteOptions: PlatformOption[];
    phoneCodeOptions: PlatformOption[];
    defaultCountryCode: string;
    defaultPhoneCode: string;
} & PlatformMediaPickerPageProps;

export default function AgencyForm({
    mode,
    agency,
    initialValues,
    typeOptions,
    ownerOptions,
    planOptions,
    statusOptions,
    websiteOptions,
    phoneCodeOptions,
    defaultCountryCode,
    defaultPhoneCode,
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerStatistics = null,
}: AgencyFormProps) {
    const form = useAppForm<AgencyFormValues>({
        defaults: {
            ...initialValues,
            country_code: initialValues.country_code || defaultCountryCode,
            phone_code: initialValues.phone_code || defaultPhoneCode,
        },
        rememberKey:
            mode === 'create'
                ? 'platform.agencies.create'
                : `platform.agencies.edit.${agency?.id ?? 'new'}`,
        dirtyGuard: true,
    });

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('platform.agencies.store')
            : route('platform.agencies.update', agency!.id);
    const cancelUrl =
        mode === 'create'
            ? route('platform.agencies.index', { status: 'all' })
            : route('platform.agencies.show', agency!.id);
    const pickerAction =
        mode === 'create'
            ? route('platform.agencies.create')
            : route('platform.agencies.edit', agency!.id);
    const websiteIdExample = buildWebsiteIdExample(
        form.data.website_id_prefix,
        form.data.website_id_zero_padding,
    );
    const canLinkAgencyWebsite = mode === 'edit';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast:
                mode === 'create'
                    ? 'Agency created successfully.'
                    : 'Agency updated successfully.',
        });
    };

    return (
        <form
            className="mx-auto flex w-full max-w-6xl flex-col gap-6"
            onSubmit={handleSubmit}
            noValidate
        >
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <AgencyFormMainSections
                    form={form}
                    typeOptions={typeOptions}
                    ownerOptions={ownerOptions}
                    planOptions={planOptions}
                    phoneCodeOptions={phoneCodeOptions}
                    pickerMedia={pickerMedia}
                    pickerFilters={pickerFilters}
                    uploadSettings={uploadSettings}
                    pickerStatistics={pickerStatistics}
                    pickerAction={pickerAction}
                />

                <AgencyFormSidebar
                    form={form}
                    mode={mode}
                    statusOptions={statusOptions}
                    websiteOptions={websiteOptions}
                    cancelUrl={cancelUrl}
                    canLinkAgencyWebsite={canLinkAgencyWebsite}
                    websiteIdExample={websiteIdExample}
                />
            </div>
        </form>
    );
}
