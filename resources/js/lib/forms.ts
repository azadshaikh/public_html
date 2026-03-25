import type { FormDataType } from '@inertiajs/core';

export type FormFieldName<T extends object> = Extract<
    keyof T,
    string
>;

export type FormFieldErrors<T extends object> = Partial<
    Record<FormFieldName<T>, string>
>;

export type FormFieldValidator<
    T extends object,
    K extends FormFieldName<T>,
> = (value: T[K], data: T) => string | undefined;

export type FormValidationRules<T extends object> = Partial<{
    [K in FormFieldName<T>]:
        | FormFieldValidator<T, K>
        | Array<FormFieldValidator<T, K>>;
}>;

const knownServerErrorMessages: Record<string, string> = {
    'auth.invalid_two_factor_code': 'The authentication code is invalid.',
};

function toValidatorArray<
    T extends object,
    K extends FormFieldName<T>,
>(
    validator:
        | FormFieldValidator<T, K>
        | Array<FormFieldValidator<T, K>>
        | undefined,
): Array<FormFieldValidator<T, K>> {
    if (validator === undefined) {
        return [];
    }

    return Array.isArray(validator) ? validator : [validator];
}

function isBlankValue(value: unknown): boolean {
    if (value === null || value === undefined) {
        return true;
    }

    if (typeof value === 'string') {
        return value.trim() === '';
    }

    if (Array.isArray(value)) {
        return value.length === 0;
    }

    if (value instanceof FileList) {
        return value.length === 0;
    }

    return false;
}

export function validateFormData<T extends FormDataType<T>>(
    data: T,
    rules: FormValidationRules<T>,
): FormFieldErrors<T> {
    const errors: FormFieldErrors<T> = {};

    (Object.keys(rules) as Array<FormFieldName<T>>).forEach((field) => {
        const validators = toValidatorArray(rules[field]);

        for (const validator of validators) {
            const message = validator(data[field], data);

            if (message !== undefined) {
                errors[field] = message;
                break;
            }
        }
    });

    return errors;
}

export function normalizeFormErrorMessage(
    message: string | undefined,
): string | undefined {
    if (message === undefined) {
        return undefined;
    }

    return knownServerErrorMessages[message] ?? message;
}

export const formValidators = {
    required:
        <T extends object, K extends FormFieldName<T>>(
            label: string,
        ): FormFieldValidator<T, K> =>
        (value) =>
            isBlankValue(value) ? `${label} is required.` : undefined,

    email:
        <T extends object, K extends FormFieldName<T>>(
            label = 'Email address',
        ): FormFieldValidator<T, K> =>
        (value) => {
            if (isBlankValue(value)) {
                return undefined;
            }

            if (typeof value !== 'string') {
                return `${label} is invalid.`;
            }

            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())
                ? undefined
                : `${label} must be a valid email address.`;
        },

    minLength:
        <T extends object, K extends FormFieldName<T>>(
            label: string,
            min: number,
        ): FormFieldValidator<T, K> =>
        (value) => {
            if (isBlankValue(value)) {
                return undefined;
            }

            if (typeof value !== 'string') {
                return `${label} is invalid.`;
            }

            return value.trim().length >= min
                ? undefined
                : `${label} must be at least ${min} characters.`;
        },

    maxLength:
        <T extends object, K extends FormFieldName<T>>(
            label: string,
            max: number,
        ): FormFieldValidator<T, K> =>
        (value) => {
            if (isBlankValue(value)) {
                return undefined;
            }

            if (typeof value !== 'string') {
                return `${label} is invalid.`;
            }

            return value.trim().length <= max
                ? undefined
                : `${label} must be no more than ${max} characters.`;
        },
};
