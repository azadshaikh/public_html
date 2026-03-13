import { CircleCheckBigIcon, XIcon } from 'lucide-react';
import { toast } from 'sonner';

export type FormSuccessToastOptions = {
    id?: string;
    title?: string;
    description?: string;
    duration?: number;
};

function FormSuccessToastContent({
    toastId,
    title,
    description,
}: {
    toastId: string | number;
    title: string;
    description: string;
}) {
    return (
        <div className="pointer-events-auto flex w-[min(24rem,calc(100vw-2rem))] items-start gap-3 rounded-2xl border border-emerald-200/70 bg-background/95 p-3 shadow-lg ring-1 ring-foreground/5 backdrop-blur-sm dark:border-emerald-500/20 dark:bg-popover/95 dark:ring-white/5">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300">
                <CircleCheckBigIcon className="size-4.5" />
            </div>

            <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold text-foreground">{title}</p>
                <p className="mt-1 text-sm leading-5 text-muted-foreground">
                    {description}
                </p>
            </div>

            <button
                type="button"
                onClick={() => toast.dismiss(toastId)}
                className="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                aria-label="Dismiss notification"
            >
                <XIcon className="size-4" />
            </button>
        </div>
    );
}

export function showFormSuccessToast({
    id,
    title = 'Saved',
    description = 'Your changes have been saved successfully.',
    duration = 4000,
}: FormSuccessToastOptions = {}) {
    return toast.custom(
        (toastId) => (
            <FormSuccessToastContent
                toastId={toastId}
                title={title}
                description={description}
            />
        ),
        {
            id,
            duration,
            position: 'top-right',
        },
    );
}
