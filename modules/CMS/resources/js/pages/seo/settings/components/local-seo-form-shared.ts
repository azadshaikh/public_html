import type { CmsOption } from '../../../../types/cms';
import type {
    LocalSeoFormValues,
    LocalSeoPageProps,
} from '../../../../types/seo';

export type LocalSeoFieldName = keyof LocalSeoFormValues;

export type LocalSeoFormErrors = Partial<
    Record<LocalSeoFieldName, string | undefined>
>;

export type LocalSeoFormBindings = {
    values: LocalSeoFormValues;
    errors: LocalSeoFormErrors;
    invalid: (field: LocalSeoFieldName) => boolean;
    touch: (field: LocalSeoFieldName) => void;
    setField: <K extends LocalSeoFieldName>(
        field: K,
        value: LocalSeoFormValues[K],
    ) => void;
};

export type LocalSeoPickerProps = Pick<
    LocalSeoPageProps,
    | 'logoImageUrl'
    | 'pickerMedia'
    | 'pickerFilters'
    | 'uploadSettings'
    | 'pickerStatistics'
>;

export type LocalSeoOption = CmsOption;
