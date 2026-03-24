import { Link } from '@inertiajs/react';
import {
    FileIcon,
    PaperclipIcon,
    SendIcon,
    UploadCloudIcon,
    XIcon,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { hasMeaningfulNoteContent, NoteRichTextEditor } from '@/components/notes/note-rich-text-editor';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
} from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const maxAttachments = 5;
const maxAttachmentBytes = 5 * 1024 * 1024;
const acceptedAttachmentExtensions = new Set([
    'jpg',
    'jpeg',
    'png',
    'gif',
    'pdf',
    'doc',
    'docx',
    'txt',
    'zip',
]);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Support', href: route('agency.tickets.index') },
    { title: 'Create Ticket', href: route('agency.tickets.create') },
];

function getPlainTextLength(value: string): number {
    return value
        .replace(/<[^>]+>/g, ' ')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'")
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/\s+/g, ' ')
        .trim().length;
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function getAttachmentExtension(file: File): string {
    return file.name.split('.').pop()?.toLowerCase() ?? '';
}

export default function AgencyTicketCreate() {
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const [isDragOver, setIsDragOver] = useState(false);

    const form = useAppForm<{
        subject: string;
        message: string;
        attachments: File[];
    }>({
        defaults: {
            subject: '',
            message: '',
            attachments: [],
        },
        rememberKey: 'agency.tickets.create',
        dontRemember: ['attachments'],
        dirtyGuard: { enabled: true },
        rules: {
            subject: [
                formValidators.required('Subject'),
                formValidators.maxLength('Subject', 255),
            ],
            message: [
                (value) =>
                    hasMeaningfulNoteContent(value)
                        ? undefined
                        : 'Message is required.',
                (value) =>
                    getPlainTextLength(value) >= 10
                        ? undefined
                        : 'Message must be at least 10 characters.',
            ],
            attachments: [
                (value) =>
                    value.length <= maxAttachments
                        ? undefined
                        : `You can attach up to ${maxAttachments} files.`,
                (value) => {
                    const oversizedFile = value.find(
                        (file) => file.size > maxAttachmentBytes,
                    );

                    if (oversizedFile) {
                        return `${oversizedFile.name} exceeds the 5 MB file size limit.`;
                    }

                    return undefined;
                },
                (value) => {
                    const invalidFile = value.find(
                        (file) =>
                            !acceptedAttachmentExtensions.has(
                                getAttachmentExtension(file),
                            ),
                    );

                    if (invalidFile) {
                        return `${invalidFile.name} is not a supported file type.`;
                    }

                    return undefined;
                },
            ],
        },
    });

    const setAttachments = (files: File[]) => {
        form.setField('attachments', files.slice(0, maxAttachments));
    };

    const mergeAttachments = (incomingFiles: File[]) => {
        const nextFiles = [...form.data.attachments, ...incomingFiles].slice(
            0,
            maxAttachments,
        );

        setAttachments(nextFiles);
    };

    const handleAttachmentSelection = (
        event: React.ChangeEvent<HTMLInputElement>,
    ) => {
        mergeAttachments(Array.from(event.target.files ?? []));
        event.target.value = '';
    };

    const handleDrop = (event: React.DragEvent<HTMLLabelElement>) => {
        event.preventDefault();
        setIsDragOver(false);
        mergeAttachments(Array.from(event.dataTransfer.files ?? []));
    };

    const removeAttachment = (fileIndex: number) => {
        setAttachments(
            form.data.attachments.filter((_, index) => index !== fileIndex),
        );
    };

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit('post', route('agency.tickets.store'), {
            forceFormData: true,
        });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Support Ticket"
            description="Submit a new support request and the team will pick it up with the context they need."
            headerActions={
                <Button asChild variant="outline">
                    <Link href={route('agency.tickets.index')}>Cancel</Link>
                </Button>
            }
        >
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6">
                {form.dirtyGuardDialog}

                <Card className="border-border/70 shadow-xs">
                    <CardContent className="p-4 sm:p-5 md:p-6">
                        <form
                            className="flex flex-col gap-5"
                            onSubmit={handleSubmit}
                            noValidate
                        >
                            <FormErrorSummary errors={form.errors} minMessages={2} />

                            <FieldGroup>
                                <Field data-invalid={form.invalid('subject') || undefined}>
                                    <FieldLabel htmlFor="subject">Subject</FieldLabel>
                                    <Input
                                        id="subject"
                                        size="xl"
                                        value={form.data.subject}
                                        onChange={(event) =>
                                            form.setField('subject', event.target.value)
                                        }
                                        onBlur={() => form.touch('subject')}
                                        aria-invalid={form.invalid('subject') || undefined}
                                        placeholder="Brief description of your issue"
                                    />
                                    <FieldError>{form.error('subject')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('message') || undefined}>
                                    <FieldLabel htmlFor="message">Details</FieldLabel>
                                    <div className="overflow-hidden rounded-lg border border-input bg-background">
                                        <NoteRichTextEditor
                                            id="message"
                                            value={form.data.message}
                                            onChange={(value) =>
                                                form.setField('message', value)
                                            }
                                            onBlur={() => form.touch('message')}
                                            invalid={form.invalid('message')}
                                            placeholder="Please describe your issue in detail, including what happened, what you expected, and anything you already tried."
                                            className="min-h-[320px]"
                                        />
                                    </div>
                                    <FieldDescription>
                                        Include steps to reproduce, affected domain or website, and any recent changes.
                                    </FieldDescription>
                                    <FieldError>{form.error('message')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('attachments') || undefined}>
                                    <div className="flex items-center justify-between gap-3">
                                        <FieldLabel htmlFor="attachments">Attachments</FieldLabel>
                                        <span className="text-xs text-muted-foreground">
                                            Up to 5 files, 5 MB each
                                        </span>
                                    </div>

                                    <label
                                        htmlFor="attachments"
                                        className={`flex min-h-28 cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed px-4 py-6 text-center transition-colors ${
                                            isDragOver
                                                ? 'border-foreground/40 bg-muted/70'
                                                : 'border-border bg-muted/20 hover:border-foreground/30 hover:bg-muted/50'
                                        }`}
                                        onDragEnter={() => setIsDragOver(true)}
                                        onDragLeave={() => setIsDragOver(false)}
                                        onDragOver={(event) => event.preventDefault()}
                                        onDrop={handleDrop}
                                    >
                                        <UploadCloudIcon className="size-5 text-muted-foreground" />
                                        <div className="space-y-1">
                                            <p className="text-sm font-medium text-foreground">
                                                Attach files
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Drag and drop screenshots, logs, or documents here, or browse from your device.
                                            </p>
                                        </div>
                                    </label>

                                    <input
                                        ref={fileInputRef}
                                        id="attachments"
                                        type="file"
                                        multiple
                                        className="sr-only"
                                        onChange={handleAttachmentSelection}
                                        accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.zip"
                                    />

                                    {form.data.attachments.length > 0 ? (
                                        <div className="grid gap-2">
                                            {form.data.attachments.map((file, index) => (
                                                <div
                                                    key={`${file.name}-${file.lastModified}-${index}`}
                                                    className="flex items-center justify-between gap-3 rounded-lg border border-border/70 bg-muted/20 px-3 py-2"
                                                >
                                                    <div className="flex min-w-0 items-center gap-3">
                                                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                                            <FileIcon className="size-4" />
                                                        </div>
                                                        <div className="min-w-0">
                                                            <p className="truncate text-sm font-medium text-foreground">
                                                                {file.name}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {formatFileSize(file.size)}
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        onClick={() => removeAttachment(index)}
                                                        aria-label={`Remove ${file.name}`}
                                                    >
                                                        <XIcon className="size-4" />
                                                    </Button>
                                                </div>
                                            ))}
                                        </div>
                                    ) : null}

                                    <FieldError>{form.error('attachments')}</FieldError>
                                </Field>
                            </FieldGroup>

                            <Button
                                type="submit"
                                size="xl"
                                className="w-full rounded-lg bg-foreground text-background hover:bg-foreground/90"
                                disabled={form.processing}
                            >
                                {form.processing ? (
                                    'Submitting Ticket...'
                                ) : (
                                    <>
                                        <SendIcon data-icon="inline-start" />
                                        Submit Ticket
                                    </>
                                )}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <div className="flex items-start gap-3 rounded-lg border border-border/70 bg-muted/20 px-4 py-3 text-sm text-muted-foreground">
                    <PaperclipIcon className="mt-0.5 size-4 shrink-0" />
                    <p>
                        Attach screenshots or error logs when they help explain the problem. The clearer the report, the faster support can move.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
