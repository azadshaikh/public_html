'use client';

import { createSlateEditor } from 'platejs';
import type { Value } from 'platejs';
import { serializeHtml } from 'platejs/static';

import { hasMeaningfulHtmlContent } from '@/components/editor/html-editor-utils';
import { BaseBasicBlocksKit } from '@/components/editor/plugins/basic-blocks-base-kit';
import { BaseBasicMarksKit } from '@/components/editor/plugins/basic-marks-base-kit';
import { BaseListKit } from '@/components/editor/plugins/list-base-kit';
import { EditorStatic } from '@/components/ui/editor-static';

export const NoteBaseKit = [
    ...BaseBasicBlocksKit,
    ...BaseBasicMarksKit,
    ...BaseListKit,
];

export async function serializeNoteValue(value: Value): Promise<string> {
    const editor = createSlateEditor({
        plugins: NoteBaseKit,
        value,
    });

    let html = await serializeHtml(editor, {
        editorComponent: EditorStatic,
        props: {
            className: 'px-0 py-0',
            variant: 'none',
        },
        stripClassNames: true,
        stripDataAttributes: true,
    });

    // Strip the outer editor wrapper <div>…</div> to get only the inner content
    html = html.replace(/^<div[^>]*>(.*)<\/div>$/s, '$1').trim();

    // Convert soft-break newlines (\n from Shift+Enter) to <br> tags so
    // line breaks persist when rendered as standard HTML.
    html = html.replace(/\n/g, '<br>');

    return hasMeaningfulHtmlContent(html) ? html : '';
}

/**
 * Simple HTML prettifier for human-readable source debugging.
 *
 * Inserts line-breaks around block-level elements and applies basic
 * indentation so source view output is easier to read when needed.
 */
export function prettifyHtml(html: string): string {
    let result = html.trim();

    result = result.replace(/>\s+</g, '><');

    result = result.replace(
        /(<(?:p|h[1-6]|blockquote|ul|ol|li|hr|pre|div|section|article|table|thead|tbody|tr|td|th)[\s>/])/gi,
        '\n$1',
    );

    result = result.replace(
        /(<\/(?:p|h[1-6]|blockquote|ul|ol|li|pre|div|section|article|table|thead|tbody|tr|td|th)>)/gi,
        '$1\n',
    );

    const lines = result
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0);

    const indented: string[] = [];
    let depth = 0;

    for (const line of lines) {
        const closes = /^<\//.test(line);

        if (closes && depth > 0) {
            depth--;
        }

        indented.push('  '.repeat(depth) + line);

        const opens =
            /^<(?:ul|ol|li|blockquote|div|section|article|table|thead|tbody|tr)[\s>]/i.test(
                line,
            ) && !/<\/[^>]+>\s*$/.test(line);

        if (opens) {
            depth++;
        }
    }

    return indented.join('\n').trim();
}
