import type {
    FormDataErrors,
    FormDataKeys,
    FormDataType,
    UrlMethodPair,
    UseFormSubmitOptions,
} from '@inertiajs/core';
import { router, useForm } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { FormSuccessToastOptions } from '@/components/forms/form-success-toast';
import { showFormSuccessToast } from '@/components/forms/form-success-toast';
import { useDirtyFormGuard } from '@/hooks/use-dirty-form-guard';
import {
    normalizeFormErrorMessage,
    validateFormData,
} from '@/lib/forms';
import type {
    FormFieldErrors,
    FormValidationRules,
} from '@/lib/forms';

type DirtyGuardOptions =
    | boolean
    | {
          enabled?: boolean;
          message?: string;
      };

type UseAppFormOptions<T extends FormDataType<T>> = {
    defaults: T;
    rememberKey: string;
    dontRemember?: Array<AppFormFieldName<T>>;
    dirtyGuard?: DirtyGuardOptions;
    rules?: FormValidationRules<T>;
};

type AppFormSubmitOptions = UseFormSubmitOptions & {
    setDefaultsOnSuccess?: boolean;
    successToast?: boolean | string | FormSuccessToastOptions;
};

type AppFormFieldName<T extends FormDataType<T>> = Extract<keyof T, string> &
    FormDataKeys<T>;

function omitField<T extends Record<string, unknown>>(
    errors: FormFieldErrors<T>,
    field: Extract<keyof T, string>,
): FormFieldErrors<T> {
    if (!(field in errors)) {
        return errors;
    }

    const nextErrors = { ...errors };
    delete nextErrors[field];

    return nextErrors;
}

export function useAppForm<T extends FormDataType<T>>({
    defaults,
    rememberKey,
    dontRemember = [],
    dirtyGuard = false,
    rules = {},
}: UseAppFormOptions<T>) {
    const inertiaForm = useForm<T>(rememberKey, defaults);
    const [clientErrors, setClientErrors] = useState<FormFieldErrors<T>>({});
    const [touchedFields, setTouchedFields] = useState<
        Partial<Record<AppFormFieldName<T>, boolean>>
    >({});
    const [submitAttempted, setSubmitAttempted] = useState(false);
    const isSubmittingRef = useRef(false);
    const defaultsRef = useRef(defaults);

    const dirtyGuardOptions =
        typeof dirtyGuard === 'boolean' ? { enabled: dirtyGuard } : dirtyGuard;

    useEffect(() => {
        if (dontRemember.length === 0) {
            return;
        }

        inertiaForm.dontRemember(...dontRemember);
    }, [dontRemember, inertiaForm]);

    useEffect(() => {
        defaultsRef.current = defaults;
    }, [defaults]);

    const resetValidationState = useCallback(() => {
        setClientErrors({});
        setTouchedFields({});
        setSubmitAttempted(false);
    }, []);

    const clearRememberedState = useCallback(
        (nextDefaults: T) => {
            router.remember(nextDefaults, `${rememberKey}:data`);
            router.remember({}, `${rememberKey}:errors`);
        },
        [rememberKey],
    );

    const setDefaults: typeof inertiaForm.setDefaults = useCallback(
        <K extends AppFormFieldName<T>>(
            fieldOrFields?: K | Partial<T>,
            value?: T[K],
        ) => {
            if (fieldOrFields === undefined) {
                inertiaForm.setDefaults();
                defaultsRef.current = inertiaForm.data;

                return;
            }

            if (typeof fieldOrFields === 'string') {
                inertiaForm.setDefaults(fieldOrFields, value as T[K]);
                defaultsRef.current = {
                    ...defaultsRef.current,
                    [fieldOrFields]: value,
                };

                return;
            }

            inertiaForm.setDefaults(fieldOrFields);
            defaultsRef.current = {
                ...defaultsRef.current,
                ...fieldOrFields,
            };
        },
        [inertiaForm],
    );

    const discard = useCallback(() => {
        inertiaForm.reset();
        inertiaForm.clearErrors();
        resetValidationState();
        clearRememberedState(defaultsRef.current);
    }, [clearRememberedState, inertiaForm, resetValidationState]);

    const dirtyFormGuard = useDirtyFormGuard({
        enabled:
            (dirtyGuardOptions?.enabled ?? false) &&
            inertiaForm.isDirty &&
            !inertiaForm.processing,
        message: dirtyGuardOptions?.message,
        onDiscard: discard,
        shouldPrompt: () => !isSubmittingRef.current,
    });

    const errors = useMemo(
        () =>
            ({
                ...Object.fromEntries(
                    Object.entries(inertiaForm.errors as FormDataErrors<T>).map(
                        ([field, message]) => [
                            field,
                            normalizeFormErrorMessage(
                                typeof message === 'string'
                                    ? message
                                    : undefined,
                            ),
                        ],
                    ),
                ),
                ...clientErrors,
            }) as FormDataErrors<T>,
        [clientErrors, inertiaForm.errors],
    );

    const validateField = useCallback(
        (field: AppFormFieldName<T>, data: T): string | undefined =>
            validateFormData(data, rules)[field],
        [rules],
    );

    const setField = useCallback(
        <K extends AppFormFieldName<T>>(field: K, value: T[K]) => {
            const nextData = {
                ...inertiaForm.data,
                [field]: value,
            } as T;
            const shouldValidate =
                submitAttempted ||
                clientErrors[field] !== undefined ||
                Boolean((inertiaForm.errors as Record<string, unknown>)[field]);

            inertiaForm.setData(field, value);
            inertiaForm.clearErrors(field);

            setClientErrors((currentErrors) => {
                if (!shouldValidate) {
                    return omitField(currentErrors, field);
                }

                const nextError = validateField(field, nextData);

                if (nextError === undefined) {
                    return omitField(currentErrors, field);
                }

                return {
                    ...currentErrors,
                    [field]: nextError,
                };
            });
        },
        [
            clientErrors,
            inertiaForm,
            submitAttempted,
            validateField,
        ],
    );

    const touch = useCallback(
        <K extends AppFormFieldName<T>>(field: K) => {
            setTouchedFields((currentFields) => ({
                ...currentFields,
                [field]: true,
            }));
        },
        [],
    );

    const validate = useCallback(() => {
        const nextErrors = validateFormData(inertiaForm.data, rules);

        setSubmitAttempted(true);
        setTouchedFields(
            Object.keys(inertiaForm.data).reduce<
                Partial<Record<AppFormFieldName<T>, boolean>>
            >((fields, key) => {
                fields[key as AppFormFieldName<T>] = true;

                return fields;
            }, {}),
        );
        setClientErrors(nextErrors);

        return Object.keys(nextErrors).length === 0;
    }, [inertiaForm.data, rules]);

    const resolveSuccessToast = useCallback(
        (
            successToast: AppFormSubmitOptions['successToast'],
        ): FormSuccessToastOptions | null => {
            if (successToast === undefined || successToast === false) {
                return null;
            }

            if (successToast === true) {
                return {
                    id: rememberKey,
                };
            }

            if (typeof successToast === 'string') {
                return {
                    id: rememberKey,
                    description: successToast,
                };
            }

            return {
                id: rememberKey,
                ...successToast,
            };
        },
        [rememberKey],
    );

    const submit = useCallback(
        (action: UrlMethodPair, options: AppFormSubmitOptions = {}) => {
            if (!validate()) {
                return false;
            }

            const {
                setDefaultsOnSuccess = false,
                successToast,
                onSuccess,
                onFinish,
                ...submitOptions
            } = options;

            isSubmittingRef.current = true;

            inertiaForm.submit(action, {
                preserveScroll: true,
                ...submitOptions,
                onSuccess: (...args) => {
                    setClientErrors({});
                    resetValidationState();

                    if (setDefaultsOnSuccess) {
                        setDefaults();
                        clearRememberedState(inertiaForm.data);
                    }

                    onSuccess?.(...args);

                    const resolvedSuccessToast =
                        resolveSuccessToast(successToast);

                    if (resolvedSuccessToast !== null) {
                        showFormSuccessToast(resolvedSuccessToast);
                    }
                },
                onFinish: (...args) => {
                    isSubmittingRef.current = false;
                    onFinish?.(...args);
                },
            });

            return true;
        },
        [
            clearRememberedState,
            inertiaForm,
            resetValidationState,
            resolveSuccessToast,
            setDefaults,
            validate,
        ],
    );

    const error = useCallback(
        <K extends AppFormFieldName<T>>(field: K): string | undefined => {
            const value = (errors as Record<string, unknown>)[field];

            return typeof value === 'string' ? value : undefined;
        },
        [errors],
    );

    const invalid = useCallback(
        <K extends AppFormFieldName<T>>(field: K) => error(field) !== undefined,
        [error],
    );

    return {
        ...inertiaForm,
        errors,
        clientErrors,
        touchedFields,
        submitAttempted,
        dirtyGuardDialog: dirtyFormGuard.dialog,
        discard,
        error,
        invalid,
        setDefaults,
        setField,
        submit,
        touch,
        validate,
    };
}
