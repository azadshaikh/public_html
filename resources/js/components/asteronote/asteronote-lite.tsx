'use client';

import { AsteroNoteEditor } from '@/components/asteronote/asteronote-editor';
import { ASTERONOTE_LITE_TOOLBAR } from '@/components/asteronote/asteronote-types';
import type { HtmlEditorProps } from '@/components/asteronote/html-editor-utils';

export function AsteroNoteLite(props: HtmlEditorProps) {
    return (
        <AsteroNoteEditor
            {...props}
            bundle="lite"
            toolbar={ASTERONOTE_LITE_TOOLBAR}
        />
    );
}
