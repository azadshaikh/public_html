import { AsteroNoteLite } from '@/components/asteronote/asteronote-lite';
import { hasMeaningfulHtmlContent } from '@/components/asteronote/html-editor-utils';

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
        <AsteroNoteLite
            {...props}
            placeholder={
                props.placeholder ??
                'Add context, follow-up details, or an internal reminder.'
            }
        />
    );
}
