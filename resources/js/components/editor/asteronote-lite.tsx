'use client';

import { AsteroNoteEditor } from '@/components/editor/asteronote-editor';
import { ASTERONOTE_LITE_TOOLBAR } from '@/components/editor/asteronote-types';
import type { HtmlEditorProps } from '@/components/editor/html-editor-utils';

export function AsteroNoteLite(props: HtmlEditorProps) {
    return (
        <AsteroNoteEditor
            {...props}
            bundle="lite"
            toolbar={ASTERONOTE_LITE_TOOLBAR}
        />
    );
}
