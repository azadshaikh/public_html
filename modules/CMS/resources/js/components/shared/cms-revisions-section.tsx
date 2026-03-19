'use client';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { CmsRevisionSummary } from '../../types/cms';

type CmsRevisionsSectionProps = {
    revisionsCount: number;
    revisions: CmsRevisionSummary[];
};

function pluralizeRevisions(count: number): string {
    return `${count} revision${count === 1 ? '' : 's'}`;
}

export function CmsRevisionsSection({
    revisionsCount,
    revisions,
}: CmsRevisionsSectionProps) {
    const visibleRevisionCount = revisions.length;

    return (
        <div className="rounded-xl border bg-muted/20 p-4">
            <div className="flex items-start justify-between gap-4">
                <div className="flex min-w-0 flex-col gap-1">
                    <div className="text-sm font-medium">Revisions</div>
                    <p className="text-sm text-muted-foreground">
                        {revisionsCount === 0
                            ? 'No saved revisions yet.'
                            : `Showing the latest ${visibleRevisionCount} of ${pluralizeRevisions(revisionsCount)}.`}
                    </p>
                </div>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={revisionsCount === 0}
                        >
                            {revisionsCount === 0
                                ? 'No revisions'
                                : `View ${pluralizeRevisions(revisionsCount)}`}
                        </Button>
                    </DialogTrigger>
                    <DialogContent className="max-w-4xl p-0 sm:max-w-4xl">
                        <DialogHeader className="px-6 pt-6">
                            <DialogTitle>Revision history</DialogTitle>
                            <DialogDescription>
                                Review the latest saved changes for this item.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="max-h-[65vh] overflow-y-auto px-6 pb-6">
                            <div className="flex flex-col gap-4">
                                {revisions.map((revision) => (
                                    <div
                                        key={revision.id}
                                        className="rounded-xl border p-4"
                                    >
                                        <div className="flex flex-col gap-1 border-b pb-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="text-sm font-medium">
                                                {revision.author_name ?? 'System'}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {revision.created_at_human ??
                                                    revision.created_at_formatted ??
                                                    'Saved recently'}
                                                {revision.created_at_formatted
                                                    ? ` (${revision.created_at_formatted})`
                                                    : ''}
                                            </div>
                                        </div>

                                        <div className="mt-4 flex flex-col gap-3">
                                            {revision.changes.map((change, index) => (
                                                <div
                                                    key={`${revision.id}-${change.field}-${index}`}
                                                    className="rounded-lg border bg-background p-3"
                                                >
                                                    <div className="text-sm font-medium">
                                                        {change.field}
                                                    </div>
                                                    <div className="mt-2 grid gap-3 text-sm md:grid-cols-2">
                                                        <div className="flex flex-col gap-1">
                                                            <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                                                Previous
                                                            </span>
                                                            <span className="break-words text-muted-foreground">
                                                                {change.old_value ?? 'Empty'}
                                                            </span>
                                                        </div>
                                                        <div className="flex flex-col gap-1">
                                                            <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                                                Updated
                                                            </span>
                                                            <span className="break-words">
                                                                {change.new_value ?? 'Empty'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <DialogFooter showCloseButton />
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}