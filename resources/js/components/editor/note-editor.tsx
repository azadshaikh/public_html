'use client';

import { AsteroNoteLite } from '@/components/editor/asteronote-lite';
import type { HtmlEditorProps } from '@/components/editor/html-editor-utils';

export function NoteEditor(props: HtmlEditorProps) {
    return <AsteroNoteLite {...props} />;
}
