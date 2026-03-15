'use client';

import { AsteroNoteEditor } from '@/components/asteronote/asteronote-editor';
import { ASTERONOTE_FULL_TOOLBAR } from '@/components/asteronote/asteronote-types';
import type { HtmlEditorProps } from '@/components/asteronote/html-editor-utils';

export function AsteroNote(props: HtmlEditorProps) {
    return (
        <AsteroNoteEditor
            {...props}
            bundle="full"
            toolbar={ASTERONOTE_FULL_TOOLBAR}
        />
    );
}
