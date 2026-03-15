'use client';

import { AsteroNoteEditor } from '@/components/editor/asteronote-editor';
import {
    ASTERONOTE_FULL_TOOLBAR,
    ASTERONOTE_LITE_TOOLBAR,
} from '@/components/editor/asteronote-types';
import type { HtmlEditorProps } from '@/components/editor/html-editor-utils';

type EditorMode = 'note' | 'full';

type SimpleHtmlEditorProps = HtmlEditorProps & {
    mode: EditorMode;
    floatingToolbar?: boolean;
};

export function SimpleHtmlEditor({ mode, ...props }: SimpleHtmlEditorProps) {
    return (
        <AsteroNoteEditor
            {...props}
            bundle={mode === 'full' ? 'full' : 'lite'}
            toolbar={
                mode === 'full'
                    ? ASTERONOTE_FULL_TOOLBAR
                    : ASTERONOTE_LITE_TOOLBAR
            }
        />
    );
}
