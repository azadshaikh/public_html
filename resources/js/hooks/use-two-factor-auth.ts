import { useCallback, useEffect, useRef, useState } from 'react';
import type { TwoFactorSecretKey, TwoFactorSetupData } from '@/types';

export type UseTwoFactorAuthReturn = {
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    recoveryCodesList: string[];
    hasSetupData: boolean;
    errors: string[];
    clearErrors: () => void;
    clearSetupData: () => void;
    fetchQrCode: () => Promise<void>;
    fetchSetupKey: () => Promise<void>;
    fetchSetupData: () => Promise<void>;
    fetchRecoveryCodes: () => Promise<void>;
};

export const OTP_MAX_LENGTH = 6;

const unavailableMessage =
    'Two-factor setup data is not available from the current route helpers.';

export const useTwoFactorAuth = (): UseTwoFactorAuthReturn => {
    const hasReportedUnavailableRef = useRef(false);
    const [qrCodeSvg, setQrCodeSvg] = useState<string | null>(null);
    const [manualSetupKey, setManualSetupKey] = useState<string | null>(null);
    const [recoveryCodesList, setRecoveryCodesList] = useState<string[]>([]);
    const [errors, setErrors] = useState<string[]>([]);

    const hasSetupData = qrCodeSvg !== null && manualSetupKey !== null;

    const reportUnavailable = useCallback((): void => {
        if (hasReportedUnavailableRef.current) {
            return;
        }

        hasReportedUnavailableRef.current = true;
        setErrors([unavailableMessage]);
    }, []);

    const clearErrors = useCallback((): void => {
        setErrors([]);
        hasReportedUnavailableRef.current = false;
    }, []);

    const fetchQrCode = useCallback(async (): Promise<void> => {
        setQrCodeSvg(null);
        reportUnavailable();
    }, [reportUnavailable]);

    const fetchSetupKey = useCallback(async (): Promise<void> => {
        setManualSetupKey(null);
        reportUnavailable();
    }, [reportUnavailable]);

    const clearSetupData = useCallback((): void => {
        setManualSetupKey(null);
        setQrCodeSvg(null);
        clearErrors();
    }, [clearErrors]);

    const fetchRecoveryCodes = useCallback(async (): Promise<void> => {
        clearErrors();
        setRecoveryCodesList([]);
        reportUnavailable();
    }, [clearErrors, reportUnavailable]);

    const fetchSetupData = useCallback(async (): Promise<void> => {
        clearErrors();
        await Promise.all([fetchQrCode(), fetchSetupKey()]);
    }, [clearErrors, fetchQrCode, fetchSetupKey]);

    return {
        qrCodeSvg,
        manualSetupKey,
        recoveryCodesList,
        hasSetupData,
        errors,
        clearErrors,
        clearSetupData,
        fetchQrCode,
        fetchSetupKey,
        fetchSetupData,
        fetchRecoveryCodes,
    };
};
