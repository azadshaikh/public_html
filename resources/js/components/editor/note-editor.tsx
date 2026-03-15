'use client';

import type { Value } from 'platejs';
import { Plate, usePlateEditor } from 'platejs/react';
import { useCallback, useEffect, useRef } from 'react';

import type { HtmlEditorProps } from '@/components/editor/html-editor-utils';
import { normalizeHtmlEditorValue } from '@/components/editor/html-editor-utils';
import { NoteEditorKit } from '@/components/editor/note-editor-kit';
import { serializeNoteValue } from '@/components/editor/note-serialization';
import {
    FullscreenProvider,
    useFullscreen,
} from '@/components/editor/use-fullscreen';
import { Editor, EditorContainer } from '@/components/ui/editor';
import { cn } from '@/lib/utils';

export function NoteEditor(props: HtmlEditorProps) {
    return (
        <FullscreenProvider>
            <NoteEditorInner {...props} />
        </FullscreenProvider>
    );
}

function NoteEditorInner({
    className,
    id,
    invalid = false,
    onBlur,
    onChange,
    placeholder = 'Write your note…',
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
        plugins: NoteEditorKit,
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

            void serializeNoteValue(nextValue).then((nextHtml) => {
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
                'overflow-hidden rounded-lg border border-border bg-background',
                invalid && 'border-destructive ring-1 ring-destructive/20',
                isFullscreen &&
                    'fixed inset-0 z-50 flex flex-col rounded-none border-0',
                className,
            )}
        >
            <Plate editor={editor} onChange={handleChange}>
                <EditorContainer
                    className={cn(
                        'min-h-[200px] rounded-b-[inherit] bg-background',
                        isFullscreen && 'min-h-0 flex-1 overflow-y-auto',
                    )}
                >
                    <Editor
                        id={id}
                        aria-invalid={invalid || undefined}
                        className="min-h-[inherit] px-4 py-4"
                        onBlur={onBlur}
                        placeholder={placeholder}
                        variant="none"
                    />
                </EditorContainer>
            </Plate>
        </div>
    );
}
