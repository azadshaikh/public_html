import { NoteEditor } from '@/components/editor/note-editor';
import { hasMeaningfulHtmlContent } from '@/components/editor/html-editor-utils';

type NoteRichTextEditorProps = {
    id: string;
    value: string;
    onBlur?: () => void;
    onChange: (value: string) => void;
    placeholder?: string;
    invalid?: boolean;
    className?: string;
};

export function hasMeaningfulNoteContent(value: string): boolean {
    return hasMeaningfulHtmlContent(value);
}

export function NoteRichTextEditor(props: NoteRichTextEditorProps) {
    return (
        <NoteEditor
            {...props}
            placeholder={
                props.placeholder ??
                'Add context, follow-up details, or an internal reminder.'
            }
        />
    );
}
