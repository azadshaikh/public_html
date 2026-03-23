import { CmsSaveFooter } from './cms-save-footer';

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
        <CmsSaveFooter
            statusText={footerStatusText}
            showStatusIcon={showUnsavedChangesStatus}
            isProcessing={isProcessing}
            position="sticky"
            secondaryAction={{
                href: backHref,
                label: backLabel,
            }}
            primaryAction={{
                label: submitLabel,
            }}
        />
    );
}
