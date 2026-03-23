import { Link } from '@inertiajs/react';
import { AlertCircleIcon, SaveIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type CmsSaveFooterAction = {
    label: string;
    href?: string;
    onClick?: () => void;
    disabled?: boolean;
    variant?: React.ComponentProps<typeof Button>['variant'];
    size?: React.ComponentProps<typeof Button>['size'];
};

type CmsSaveFooterProps = {
    statusText: string;
    showStatusIcon?: boolean;
    isProcessing?: boolean;
    position?: 'sticky' | 'fixed';
    primaryAction: CmsSaveFooterAction & {
        submit?: boolean;
        icon?: ReactNode;
    };
    secondaryAction?: CmsSaveFooterAction | null;
};

function CmsSaveFooterButton({
    action,
    defaultVariant,
}: {
    action: CmsSaveFooterAction;
    defaultVariant: React.ComponentProps<typeof Button>['variant'];
}) {
    const variant = action.variant ?? defaultVariant;
    const size = action.size ?? 'comfortable';

    if (action.href) {
        return (
            <Button
                type="button"
                variant={variant}
                size={size}
                className="min-w-0 flex-1 sm:flex-none"
                asChild
            >
                <Link href={action.href}>{action.label}</Link>
            </Button>
        );
    }

    return (
        <Button
            type="button"
            variant={variant}
            size={size}
            className="min-w-0 flex-1 sm:flex-none"
            disabled={action.disabled}
            onClick={action.onClick}
        >
            {action.label}
        </Button>
    );
}

export function CmsSaveFooter({
    statusText,
    showStatusIcon = false,
    isProcessing = false,
    position = 'sticky',
    primaryAction,
    secondaryAction = null,
}: CmsSaveFooterProps) {
    const containerClassName =
        position === 'fixed'
            ? 'fixed inset-x-0 bottom-0 z-40 px-4 py-3'
            : 'sticky bottom-0 z-20 mt-auto';

    const innerClassName =
        position === 'fixed' ? 'mx-auto max-w-screen-2xl' : '';

    return (
        <div className={containerClassName}>
            <div className={innerClassName}>
                <Card className="rounded-xl border-x-0 border-b-0 bg-background/95 supports-[backdrop-filter]:backdrop-blur-sm sm:border-x">
                    <CardContent className="flex items-center justify-between gap-3 px-3 py-0 sm:px-4">
                        <div className="hidden min-w-0 flex-1 items-center gap-1.5 text-sm text-muted-foreground sm:flex">
                            {showStatusIcon ? (
                                <AlertCircleIcon className="size-4 shrink-0" />
                            ) : null}
                            <span className="truncate">{statusText}</span>
                        </div>

                        <div className="flex w-full items-center gap-2 sm:w-auto sm:flex-none">
                            {secondaryAction ? (
                                <CmsSaveFooterButton
                                    action={secondaryAction}
                                    defaultVariant="outline"
                                />
                            ) : null}

                            <Button
                                type={
                                    primaryAction.submit === false
                                        ? 'button'
                                        : 'submit'
                                }
                                size={primaryAction.size ?? 'comfortable'}
                                variant={primaryAction.variant ?? 'default'}
                                className={cn(
                                    'min-w-0 flex-1 sm:flex-none',
                                    secondaryAction ? null : 'sm:ml-auto',
                                )}
                                disabled={
                                    primaryAction.disabled || isProcessing
                                }
                                onClick={primaryAction.onClick}
                            >
                                {isProcessing ? (
                                    <Spinner className="size-4" />
                                ) : (
                                    (primaryAction.icon ?? (
                                        <SaveIcon data-icon="inline-start" />
                                    ))
                                )}
                                {primaryAction.label}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
