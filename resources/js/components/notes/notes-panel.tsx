import { router } from '@inertiajs/react';
import {
    LockIcon,
    PencilIcon,
    PinIcon,
    PinOffIcon,
    SaveIcon,
    StickyNoteIcon,
    Trash2Icon,
    UsersIcon,
    UserIcon,
    XIcon,
} from 'lucide-react';
import { useState } from 'react';
import {
    NoteRichTextEditor,
    hasMeaningfulNoteContent,
} from '@/components/notes/note-rich-text-editor';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAppForm } from '@/hooks/use-app-form';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type {
    AppNote,
    NoteTarget,
    NoteVisibilityOption,
    NoteVisibilityValue,
} from '@/types/notes';

type NoteFormValues = {
    content: string;
    visibility: NoteVisibilityValue;
    noteable_type: string;
    noteable_id: number;
};

type NoteEditFormValues = {
    content: string;
    visibility: NoteVisibilityValue;
};

type NotesPanelProps = {
    notes: AppNote[];
    noteTarget: NoteTarget;
    noteVisibilityOptions: NoteVisibilityOption[];
    title?: string;
    description?: string;
    readOnly?: boolean;
};

function visibilityIcon(value: NoteVisibilityValue) {
    if (value === 'private') {
        return <LockIcon className="size-3.5" />;
    }

    if (value === 'customer') {
        return <UserIcon className="size-3.5" />;
    }

    return <UsersIcon className="size-3.5" />;
}

function NoteVisibilityField({
    value,
    onChange,
    options,
    invalid,
}: {
    value: NoteVisibilityValue;
    onChange: (value: NoteVisibilityValue) => void;
    options: NoteVisibilityOption[];
    invalid?: boolean;
}) {
    const activeOption =
        options.find((option) => option.value === value) ?? options[0] ?? null;

    return (
        <Field data-invalid={invalid || undefined}>
            <FieldSet>
                <FieldLegend>Visibility</FieldLegend>
                <FieldDescription>
                    Choose who can read this note on the record.
                </FieldDescription>
                <ToggleGroup
                    type="single"
                    value={value}
                    onValueChange={(nextValue) => {
                        if (nextValue === '') {
                            return;
                        }

                        onChange(nextValue as NoteVisibilityValue);
                    }}
                    aria-invalid={invalid || undefined}
                    className="w-full flex-wrap"
                    variant="outline"
                >
                    {options.map((option) => (
                        <ToggleGroupItem
                            key={option.value}
                            value={option.value}
                            className="min-w-[9rem] flex-1"
                        >
                            {option.label}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
                {activeOption ? (
                    <FieldDescription>
                        {activeOption.description}
                    </FieldDescription>
                ) : null}
            </FieldSet>
        </Field>
    );
}

function CreateNoteForm({
    noteTarget,
    noteVisibilityOptions,
}: {
    noteTarget: NoteTarget;
    noteVisibilityOptions: NoteVisibilityOption[];
}) {
    const defaultVisibility = noteVisibilityOptions[0]?.value ?? 'team';
    const form = useAppForm<NoteFormValues>({
        defaults: {
            content: '',
            visibility: defaultVisibility,
            noteable_type: noteTarget.type,
            noteable_id: noteTarget.id,
        },
        rememberKey: `notes.create.${noteTarget.type}.${noteTarget.id}`,
        rules: {
            content: [
                (value) =>
                    typeof value === 'string' &&
                    hasMeaningfulNoteContent(value)
                        ? undefined
                        : 'Please enter a note.',
            ],
        },
    });

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('app.notes.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                form.clearErrors();
            },
            successToast: {
                title: 'Note added',
                description: 'The note was added to this record.',
            },
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Add note</CardTitle>
                <CardDescription>
                    Capture internal context, reminders, or follow-up details.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form noValidate onSubmit={handleSubmit} className="flex flex-col gap-5">
                    <Field data-invalid={form.invalid('content') || undefined}>
                        <FieldLabel htmlFor={`note-content-${noteTarget.id}`}>
                            Note
                        </FieldLabel>
                        <NoteRichTextEditor
                            id={`note-content-${noteTarget.id}`}
                            value={form.data.content}
                            onChange={(nextValue) =>
                                form.setField('content', nextValue)
                            }
                            onBlur={() => form.touch('content')}
                            invalid={form.invalid('content')}
                            placeholder="Add context, follow-up details, or an internal reminder."
                        />
                        <FieldDescription>
                            Notes stay attached to the record for future staff context.
                        </FieldDescription>
                        <FieldError>{form.error('content')}</FieldError>
                    </Field>

                    <NoteVisibilityField
                        value={form.data.visibility}
                        onChange={(value) => form.setField('visibility', value)}
                        options={noteVisibilityOptions}
                        invalid={form.invalid('visibility')}
                    />
                    <FieldError>{form.error('visibility')}</FieldError>

                    <div className="flex items-center justify-end gap-2">
                        <Button type="submit" disabled={form.processing}>
                            <StickyNoteIcon data-icon="inline-start" />
                            Add Note
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function EditNoteForm({
    note,
    noteVisibilityOptions,
    onCancel,
}: {
    note: AppNote;
    noteVisibilityOptions: NoteVisibilityOption[];
    onCancel: () => void;
}) {
    const form = useAppForm<NoteEditFormValues>({
        defaults: {
            content: note.content,
            visibility: note.visibility.value,
        },
        rememberKey: `notes.edit.${note.id}`,
        rules: {
            content: [
                (value) =>
                    typeof value === 'string' &&
                    hasMeaningfulNoteContent(value)
                        ? undefined
                        : 'Please enter a note.',
            ],
        },
    });

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('put', note.actions.update, {
            preserveScroll: true,
            onSuccess: () => {
                onCancel();
            },
            successToast: {
                title: 'Note updated',
                description: 'The note changes were saved.',
            },
        });
    };

    return (
        <form noValidate onSubmit={handleSubmit} className="flex flex-col gap-5">
            <Field data-invalid={form.invalid('content') || undefined}>
                <FieldLabel htmlFor={`note-edit-content-${note.id}`}>
                    Edit note
                </FieldLabel>
                <NoteRichTextEditor
                    id={`note-edit-content-${note.id}`}
                    value={form.data.content}
                    onChange={(nextValue) => form.setField('content', nextValue)}
                    onBlur={() => form.touch('content')}
                    invalid={form.invalid('content')}
                />
                <FieldError>{form.error('content')}</FieldError>
            </Field>

            <NoteVisibilityField
                value={form.data.visibility}
                onChange={(value) => form.setField('visibility', value)}
                options={noteVisibilityOptions}
                invalid={form.invalid('visibility')}
            />
            <FieldError>{form.error('visibility')}</FieldError>

            <div className="flex items-center justify-end gap-2">
                <Button type="button" variant="outline" onClick={onCancel}>
                    <XIcon data-icon="inline-start" />
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing}>
                    <SaveIcon data-icon="inline-start" />
                    Save Changes
                </Button>
            </div>
        </form>
    );
}

function NoteCard({
    note,
    noteVisibilityOptions,
    readOnly,
}: {
    note: AppNote;
    noteVisibilityOptions: NoteVisibilityOption[];
    readOnly: boolean;
}) {
    const [isEditing, setIsEditing] = useState(false);
    const [pendingAction, setPendingAction] = useState<null | 'pin' | 'delete'>(
        null,
    );
    const getInitials = useInitials();

    const handleTogglePin = () => {
        setPendingAction('pin');

        router.post(
            note.actions.toggle_pin,
            {},
            {
                preserveScroll: true,
                onFinish: () => setPendingAction(null),
            },
        );
    };

    const handleDelete = () => {
        if (!window.confirm('Delete this note?')) {
            return;
        }

        setPendingAction('delete');

        router.delete(note.actions.destroy, {
            preserveScroll: true,
            onFinish: () => setPendingAction(null),
        });
    };

    return (
        <Card
            className={cn(
                note.is_pinned &&
                    'border-primary/30 bg-primary/5 shadow-sm dark:bg-primary/10',
            )}
        >
            <CardContent className="pt-6">
                {isEditing ? (
                    <EditNoteForm
                        note={note}
                        noteVisibilityOptions={noteVisibilityOptions}
                        onCancel={() => setIsEditing(false)}
                    />
                ) : (
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div className="flex min-w-0 items-start gap-3">
                                <Avatar className="size-10">
                                    <AvatarImage
                                        src={note.author?.avatar_url ?? undefined}
                                        alt={note.author?.name ?? 'System'}
                                    />
                                    <AvatarFallback>
                                        {getInitials(note.author?.name ?? 'System')}
                                    </AvatarFallback>
                                </Avatar>

                                <div className="flex min-w-0 flex-1 flex-col gap-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-sm font-medium text-foreground">
                                            {note.author?.name ?? 'System'}
                                        </span>
                                        {note.is_pinned ? (
                                            <Badge variant="secondary">
                                                Pinned
                                            </Badge>
                                        ) : null}
                                        <Badge variant={note.type.badge}>
                                            {note.type.label}
                                        </Badge>
                                        <Badge variant={note.visibility.badge}>
                                            <span className="mr-1 inline-flex">
                                                {visibilityIcon(
                                                    note.visibility.value,
                                                )}
                                            </span>
                                            {note.visibility.label}
                                        </Badge>
                                    </div>

                                    <p className="text-xs text-muted-foreground">
                                        {note.created_at_formatted ??
                                            note.created_at_human ??
                                            'Just now'}
                                        {note.updated_at_human &&
                                        note.updated_at_human !==
                                            note.created_at_human
                                            ? ` · Updated ${note.updated_at_human}`
                                            : ''}
                                    </p>
                                </div>
                            </div>

                            {!readOnly ? (
                                <div className="flex items-center gap-2 self-end sm:self-start">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={pendingAction !== null}
                                        onClick={handleTogglePin}
                                    >
                                        {note.is_pinned ? (
                                            <PinOffIcon data-icon="inline-start" />
                                        ) : (
                                            <PinIcon data-icon="inline-start" />
                                        )}
                                        {note.is_pinned ? 'Unpin' : 'Pin'}
                                    </Button>
                                    {note.is_editable ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setIsEditing(true)}
                                        >
                                            <PencilIcon data-icon="inline-start" />
                                            Edit
                                        </Button>
                                    ) : null}
                                    {note.is_deletable ? (
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            disabled={pendingAction !== null}
                                            onClick={handleDelete}
                                        >
                                            <Trash2Icon data-icon="inline-start" />
                                            Delete
                                        </Button>
                                    ) : null}
                                </div>
                            ) : null}
                        </div>

                        <div className="rounded-xl border bg-background/80 px-4 py-3 text-sm text-foreground">
                            <div
                                className="leading-6 [&_blockquote]:my-2 [&_blockquote]:border-l-2 [&_blockquote]:pl-4 [&_blockquote]:italic [&_h1]:mt-4 [&_h1]:text-2xl [&_h1]:font-semibold [&_h2]:mt-3 [&_h2]:text-xl [&_h2]:font-semibold [&_h3]:mt-3 [&_h3]:text-lg [&_h3]:font-semibold [&_p]:my-2 [&_p:first-child]:mt-0 [&_p:last-child]:mb-0 [&_strong]:font-semibold [&_u]:underline"
                                dangerouslySetInnerHTML={{
                                    __html: note.content_html,
                                }}
                            />
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export function NotesPanel({
    notes,
    noteTarget,
    noteVisibilityOptions,
    title = 'Notes',
    description = 'Internal context and follow-up history for this record.',
    readOnly = false,
}: NotesPanelProps) {
    return (
        <div className="flex flex-col gap-6">
            {!readOnly ? (
                <CreateNoteForm
                    noteTarget={noteTarget}
                    noteVisibilityOptions={noteVisibilityOptions}
                />
            ) : null}

            <Card>
                <CardHeader>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>
                        {description}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {notes.length === 0 ? (
                        <Empty className="border bg-muted/20 py-10">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <StickyNoteIcon />
                                </EmptyMedia>
                                <EmptyTitle>No notes yet</EmptyTitle>
                                <EmptyDescription>
                                    Add the first note to capture internal context for this record.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <div className="flex flex-col gap-4">
                            {notes.map((note) => (
                                <NoteCard
                                    key={note.id}
                                    note={note}
                                    noteVisibilityOptions={noteVisibilityOptions}
                                    readOnly={readOnly}
                                />
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
