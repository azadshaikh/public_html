'use client';

import {
    BaseBlockquotePlugin,
    BaseBoldPlugin,
    BaseH1Plugin,
    BaseH2Plugin,
    BaseH3Plugin,
    BaseItalicPlugin,
    BaseUnderlinePlugin,
} from '@platejs/basic-nodes';
import {
    BlockquotePlugin,
    BoldPlugin,
    H1Plugin,
    H2Plugin,
    H3Plugin,
    ItalicPlugin,
    UnderlinePlugin,
} from '@platejs/basic-nodes/react';
import { createSlateEditor } from 'platejs';
import type { Value } from 'platejs';
import { serializeHtml } from 'platejs/static';
import { Plate, usePlateEditor } from 'platejs/react';
import { useCallback, useEffect, useRef } from 'react';
import {
    hasMeaningfulHtmlContent,
    normalizeHtmlEditorValue,
    type HtmlEditorProps,
} from '@/components/editor/html-editor-utils';
import { BlockquoteElementStatic } from '@/components/ui/blockquote-node-static';
import { BlockquoteElement } from '@/components/ui/blockquote-node';
import { EditorStatic } from '@/components/ui/editor-static';
import { Editor, EditorContainer } from '@/components/ui/editor';
import { FixedToolbar } from '@/components/ui/fixed-toolbar';
import { H1Element, H2Element, H3Element } from '@/components/ui/heading-node';
import {
    H1ElementStatic,
    H2ElementStatic,
    H3ElementStatic,
} from '@/components/ui/heading-node-static';
import { MarkToolbarButton } from '@/components/ui/mark-toolbar-button';
import { ParagraphElementStatic } from '@/components/ui/paragraph-node-static';
import { ToolbarButton } from '@/components/ui/toolbar';
import { cn } from '@/lib/utils';

const liteEditorPlugins = [
    BoldPlugin,
    ItalicPlugin,
    UnderlinePlugin,
    H1Plugin.withComponent(H1Element),
    H2Plugin.withComponent(H2Element),
    H3Plugin.withComponent(H3Element),
    BlockquotePlugin.withComponent(BlockquoteElement),
];

const liteStaticPlugins = [
    BaseBoldPlugin,
    BaseItalicPlugin,
    BaseUnderlinePlugin,
    BaseH1Plugin,
    BaseH2Plugin,
    BaseH3Plugin,
    BaseBlockquotePlugin,
];

const liteStaticComponents = {
    blockquote: BlockquoteElementStatic,
    h1: H1ElementStatic,
    h2: H2ElementStatic,
    h3: H3ElementStatic,
    p: ParagraphElementStatic,
};

async function serializeLiteValue(value: Value): Promise<string> {
    const editor = createSlateEditor({
        components: liteStaticComponents,
        plugins: liteStaticPlugins,
        value,
    });

    const html = await serializeHtml(editor, {
        editorComponent: EditorStatic,
        props: {
            className: 'px-0 py-0',
            variant: 'none',
        },
    });

    return hasMeaningfulHtmlContent(html) ? html.trim() : '';
}

export function EditorLite({
    className,
    id,
    invalid = false,
    onBlur,
    onChange,
    placeholder = 'Type your message here.',
    value,
}: HtmlEditorProps) {
    const initialHtml = normalizeHtmlEditorValue(value);
    const latestHtmlRef = useRef(initialHtml);
    const resetValueRef = useRef(initialHtml);
    const requestIdRef = useRef(0);

    const editor = usePlateEditor({
        plugins: liteEditorPlugins,
        value: initialHtml,
    });

    useEffect(() => {
        const normalizedValue = normalizeHtmlEditorValue(value);

        if (normalizedValue === latestHtmlRef.current) {
            return;
        }

        editor.tf.setValue(normalizedValue);
        latestHtmlRef.current = normalizedValue;
    }, [editor, value]);

    const handleChange = useCallback(
        ({ value: nextValue }: { value: Value }) => {
            const requestId = requestIdRef.current + 1;
            requestIdRef.current = requestId;

            void serializeLiteValue(nextValue).then((nextHtml) => {
                if (requestIdRef.current !== requestId) {
                    return;
                }

                latestHtmlRef.current = normalizeHtmlEditorValue(nextHtml);
                onChange(nextHtml);
            });
        },
        [onChange],
    );

    const handleReset = useCallback(() => {
        const resetValue = resetValueRef.current;

        editor.tf.setValue(resetValue);
        latestHtmlRef.current = resetValue;
        onChange(hasMeaningfulHtmlContent(resetValue) ? resetValue : '');
    }, [editor, onChange]);

    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border border-border bg-background',
                invalid && 'border-destructive ring-1 ring-destructive/20',
                className,
            )}
        >
            <Plate editor={editor} onChange={handleChange}>
                <FixedToolbar className="flex justify-start gap-1 rounded-t-[inherit]">
                    <ToolbarButton
                        onClick={() => editor.tf.h1.toggle()}
                        tooltip="Heading 1"
                    >
                        H1
                    </ToolbarButton>
                    <ToolbarButton
                        onClick={() => editor.tf.h2.toggle()}
                        tooltip="Heading 2"
                    >
                        H2
                    </ToolbarButton>
                    <ToolbarButton
                        onClick={() => editor.tf.h3.toggle()}
                        tooltip="Heading 3"
                    >
                        H3
                    </ToolbarButton>
                    <ToolbarButton
                        onClick={() => editor.tf.blockquote.toggle()}
                        tooltip="Blockquote"
                    >
                        Quote
                    </ToolbarButton>

                    <MarkToolbarButton nodeType="bold" tooltip="Bold (⌘+B)">
                        B
                    </MarkToolbarButton>
                    <MarkToolbarButton nodeType="italic" tooltip="Italic (⌘+I)">
                        I
                    </MarkToolbarButton>
                    <MarkToolbarButton
                        nodeType="underline"
                        tooltip="Underline (⌘+U)"
                    >
                        U
                    </MarkToolbarButton>

                    <div className="flex-1" />

                    <ToolbarButton className="px-2" onClick={handleReset}>
                        Reset
                    </ToolbarButton>
                </FixedToolbar>
                <EditorContainer className="min-h-44 rounded-b-[inherit] bg-background">
                    <Editor
                        id={id}
                        aria-invalid={invalid || undefined}
                        className="min-h-44 px-5 py-4 text-sm leading-6"
                        onBlur={onBlur}
                        placeholder={placeholder}
                        variant="none"
                    />
                </EditorContainer>
            </Plate>
        </div>
    );
}
