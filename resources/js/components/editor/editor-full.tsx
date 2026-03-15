'use client';

import { AsteroNote } from '@/components/editor/asteronote';
import type { HtmlEditorProps } from '@/components/editor/html-editor-utils';

export function EditorFull(props: HtmlEditorProps) {
    return <AsteroNote {...props} />;
}
