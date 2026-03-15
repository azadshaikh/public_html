'use client';

import type { HtmlEditorProps } from '@/components/editor/html-editor-utils';
import { SimpleHtmlEditor } from '@/components/editor/simple-html-editor';

export function NoteEditor(props: HtmlEditorProps) {
    return <SimpleHtmlEditor {...props} mode="note" floatingToolbar />;
}
