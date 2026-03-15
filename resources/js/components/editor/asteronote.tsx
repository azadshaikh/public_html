'use client';

import { AsteroNoteEditor } from '@/components/editor/asteronote-editor';
import { ASTERONOTE_FULL_TOOLBAR } from '@/components/editor/asteronote-types';
import type { HtmlEditorProps } from '@/components/editor/html-editor-utils';

export function AsteroNote(props: HtmlEditorProps) {
    return (
        <AsteroNoteEditor
            {...props}
            bundle="full"
            toolbar={ASTERONOTE_FULL_TOOLBAR}
        />
    );
}
