'use client';

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
import { BaseEditorKit } from '@/components/editor/editor-base-kit';
import { EditorKit } from '@/components/editor/editor-kit';
import {
    FullscreenProvider,
    useFullscreen,
} from '@/components/editor/use-fullscreen';
import { EditorStatic } from '@/components/ui/editor-static';
import { Editor, EditorContainer } from '@/components/ui/editor';
import { SettingsDialog } from '@/components/editor/settings-dialog';
import { cn } from '@/lib/utils';

async function serializeFullValue(value: Value): Promise<string> {
    const editor = createSlateEditor({
        plugins: BaseEditorKit,
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

export function EditorFull(props: HtmlEditorProps) {
    return (
        <FullscreenProvider>
            <EditorFullInner {...props} />
        </FullscreenProvider>
    );
}

function EditorFullInner({
    className,
    id,
    invalid = false,
    onBlur,
    onChange,
    placeholder = 'Compose a rich HTML message…',
    value,
}: HtmlEditorProps) {
    const { isFullscreen } = useFullscreen();
    const initialHtml = normalizeHtmlEditorValue(value);
    const latestHtmlRef = useRef(initialHtml);
    const requestIdRef = useRef(0);

    // Lock body scroll in fullscreen mode
    useEffect(() => {
        if (!isFullscreen) return;

        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = '';
        };
    }, [isFullscreen]);

    const editor = usePlateEditor({
        plugins: EditorKit,
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

            void serializeFullValue(nextValue).then((nextHtml) => {
                if (requestIdRef.current !== requestId) {
                    return;
                }

                latestHtmlRef.current = normalizeHtmlEditorValue(nextHtml);
                onChange(nextHtml);
            });
        },
        [onChange],
    );

    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border border-border bg-background',
                invalid && 'border-destructive ring-1 ring-destructive/20',
                isFullscreen &&
                    'fixed inset-0 z-50 flex flex-col rounded-none border-0',
                className,
            )}
        >
            <Plate editor={editor} onChange={handleChange}>
                <EditorContainer
                    className={cn(
                        'min-h-[500px] rounded-b-[inherit] bg-background',
                        isFullscreen && 'min-h-0 flex-1 overflow-y-auto',
                    )}
                >
                    <Editor
                        id={id}
                        aria-invalid={invalid || undefined}
                        className="px-[96px] py-16"
                        onBlur={onBlur}
                        placeholder={placeholder}
                        variant="fullWidth"
                    />
                </EditorContainer>

                <SettingsDialog />
            </Plate>
        </div>
    );
}
