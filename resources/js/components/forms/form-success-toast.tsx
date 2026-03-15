import {
    AlertCircleIcon,
    CircleCheckBigIcon,
    InfoIcon,
    XIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';

export type AppToastVariant = 'success' | 'error' | 'info';

export type AppToastOptions = {
    id?: string;
    variant?: AppToastVariant;
    title?: string;
    description?: string;
    duration?: number;
};

/** @deprecated Use AppToastOptions instead */
export type FormSuccessToastOptions = AppToastOptions;

const VARIANT_STYLES: Record<
    AppToastVariant,
    { border: string; bg: string; text: string; icon: ReactNode }
> = {
    success: {
        border: 'border-[var(--success-border)] dark:border-[var(--success-dark-border)]',
        bg: 'bg-[var(--success-bg)] text-[var(--success-foreground)] dark:bg-[var(--success-dark-bg)] dark:text-[var(--success-dark-foreground)]',
        text: '',
        icon: <CircleCheckBigIcon className="size-4.5" />,
    },
    error: {
        border: 'border-red-200/70 dark:border-red-500/20',
        bg: 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-300',
        text: '',
        icon: <AlertCircleIcon className="size-4.5" />,
    },
    info: {
        border: 'border-blue-200/70 dark:border-blue-500/20',
        bg: 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300',
        text: '',
        icon: <InfoIcon className="size-4.5" />,
    },
};

const VARIANT_DEFAULTS: Record<
    AppToastVariant,
    { title: string; description: string }
> = {
    success: {
        title: 'Saved',
        description: 'Your changes have been saved successfully.',
    },
    error: {
        title: 'Error',
        description: 'Something went wrong. Please try again.',
    },
    info: {
        title: 'Info',
        description: '',
    },
};

function AppToastContent({
    toastId,
    variant,
    title,
    description,
}: {
    toastId: string | number;
    variant: AppToastVariant;
    title: string;
    description: string;
}) {
    const styles = VARIANT_STYLES[variant];

    const dismissToast = (
        event:
            | React.PointerEvent<HTMLButtonElement>
            | React.MouseEvent<HTMLButtonElement>,
    ) => {
        event.preventDefault();
        event.stopPropagation();

        toast.dismiss(toastId);
    };

    return (
        <div
            className={`pointer-events-auto flex w-[min(24rem,calc(100vw-2rem))] items-start gap-3 rounded-2xl border bg-background/95 p-3 shadow-lg ring-1 ring-foreground/5 backdrop-blur-sm dark:bg-popover/95 dark:ring-white/5 ${styles.border}`}
        >
            <div
                className={`flex size-10 shrink-0 items-center justify-center rounded-full ${styles.bg}`}
            >
                {styles.icon}
            </div>

            <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold text-foreground">{title}</p>
                {description ? (
                    <p className="mt-1 text-sm leading-5 text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>

            <button
                type="button"
                onPointerDown={(event) => {
                    event.preventDefault();
                    event.stopPropagation();
                }}
                onClick={dismissToast}
                className="pointer-events-auto mt-0.5 inline-flex size-7 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                aria-label="Dismiss notification"
            >
                <XIcon className="size-4" />
            </button>
        </div>
    );
}

export function showAppToast({
    id,
    variant = 'success',
    title,
    description,
    duration = 4000,
}: AppToastOptions = {}) {
    const defaults = VARIANT_DEFAULTS[variant];
    const toastTitle = title ?? defaults.title;
    const toastDescription = description ?? defaults.description;

    return toast.custom(
        (toastId) => (
            <AppToastContent
                toastId={toastId}
                variant={variant}
                title={toastTitle}
                description={toastDescription}
            />
        ),
        {
            id,
            duration,
            position: 'top-right',
        },
    );
}

/** @deprecated Use showAppToast instead */
export const showFormSuccessToast = showAppToast;
