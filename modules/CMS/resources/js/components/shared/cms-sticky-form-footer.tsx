import { Link } from '@inertiajs/react';
import { AlertCircleIcon, SaveIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

type CmsStickyFormFooterProps = {
    backHref: string;
    backLabel: string;
    submitLabel: string;
    isCreate: boolean;
    isDirty: boolean;
    isProcessing: boolean;
};

export function CmsStickyFormFooter({
    backHref,
    backLabel,
    submitLabel,
    isCreate,
    isDirty,
    isProcessing,
}: CmsStickyFormFooterProps) {
    const showUnsavedChangesStatus = isDirty && !isProcessing;
    const footerStatusText = isProcessing
        ? 'Saving changes...'
        : showUnsavedChangesStatus
          ? 'You have unsaved changes.'
          : isCreate
            ? 'Start editing to create this item.'
            : 'All changes saved.';

    return (
        <div className="sticky bottom-0 z-20 mt-auto">
            <Card className="border-x-0 border-b-0 rounded-xl bg-background/95 supports-backdrop-filter:backdrop-blur-sm sm:border-x">
                <CardContent className="flex items-center justify-between gap-3 px-3 py-0 sm:px-4">
                    <div className="hidden min-w-0 flex-1 items-center gap-1.5 text-sm text-muted-foreground sm:flex">
                        {showUnsavedChangesStatus ? (
                            <AlertCircleIcon className="size-4 shrink-0" />
                        ) : null}
                        <span className="truncate">{footerStatusText}</span>
                    </div>

                    <div className="flex w-full items-center gap-2 sm:w-auto sm:flex-none">
                        <Button
                            type="button"
                            variant="outline"
                            size="comfortable"
                            className="min-w-0 flex-1 sm:flex-none"
                            asChild
                        >
                            <Link href={backHref}>{backLabel}</Link>
                        </Button>

                        <Button
                            type="submit"
                            size="comfortable"
                            className="min-w-0 flex-1 sm:flex-none"
                            disabled={isProcessing}
                        >
                            {isProcessing ? (
                                <Spinner className="size-4" />
                            ) : (
                                <SaveIcon data-icon="inline-start" />
                            )}
                            {submitLabel}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}