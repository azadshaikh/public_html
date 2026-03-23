import { useEffect, useMemo, useRef, useState } from 'react';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { loadMonacoEditor } from '@/lib/monaco/loader';
import type {
    MonacoEditorConstructionOptions,
    MonacoStandaloneEditor,
} from '@/lib/monaco/loader';
import { cn } from '@/lib/utils';

export type MonacoEditorProps = {
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    language?: string;
    height?: number | string;
    name?: string;
    disabled?: boolean;
    placeholder?: string;
    className?: string;
    textareaClassName?: string;
    editorClassName?: string;
    loadingLabel?: string;
    fallbackLabel?: string;
    options?: Partial<MonacoEditorConstructionOptions>;
};

const ASTERO_MONACO_THEME = 'vs-dark';

function normalizeHeight(height: MonacoEditorProps['height']): string {
    if (typeof height === 'number') {
        return `${height}px`;
    }

    return height ?? '32rem';
}

export function MonacoEditor({
    value,
    onChange,
    onBlur,
    language = 'shell',
    height = '34rem',
    name,
    disabled = false,
    placeholder,
    className,
    textareaClassName,
    editorClassName,
    loadingLabel = 'Loading Monaco editor…',
    fallbackLabel = 'Textarea fallback active',
    options,
}: MonacoEditorProps) {
    const mountRef = useRef<HTMLDivElement | null>(null);
    const resolvedHeight = useMemo(() => normalizeHeight(height), [height]);
    const mergedOptions = useMemo<MonacoEditorConstructionOptions>(
        () => ({
            value,
            language,
            theme: ASTERO_MONACO_THEME,
            readOnly: disabled,
            automaticLayout: true,
            minimap: { enabled: false },
            lineNumbers: 'on',
            lineNumbersMinChars: 4,
            lineDecorationsWidth: 6,
            scrollBeyondLastLine: false,
            wordWrap: 'on',
            fontSize: 14,
            fontFamily:
                "'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace",
            padding: { top: 12, bottom: 12 },
            renderLineHighlight: 'line',
            renderWhitespace: 'selection',
            scrollbar: {
                vertical: 'auto',
                horizontal: 'auto',
            },
            tabSize: 2,
            insertSpaces: true,
            detectIndentation: false,
            quickSuggestions: true,
            suggestOnTriggerCharacters: true,
            parameterHints: { enabled: true },
            folding: true,
            foldingStrategy: 'indentation',
            showFoldingControls: 'mouseover',
            bracketPairColorization: { enabled: true },
            matchBrackets: 'always',
            formatOnType: true,
            formatOnPaste: true,
            autoClosingBrackets: 'always',
            autoClosingQuotes: 'always',
            autoSurround: 'languageDefined',
            linkedEditing: true,
            ...options,
        }),
        [disabled, language, options, value],
    );
    const editorRef = useRef<MonacoStandaloneEditor | null>(null);
    const lastValueRef = useRef(value);
    const creationOptionsRef =
        useRef<MonacoEditorConstructionOptions>(mergedOptions);
    const shouldFocusEditorRef = useRef(false);
    const [loaderState, setLoaderState] = useState<
        'loading' | 'ready' | 'fallback'
    >('loading');

    useEffect(() => {
        lastValueRef.current = value;
    }, [value]);

    useEffect(() => {
        creationOptionsRef.current = mergedOptions;
    }, [mergedOptions]);

    useEffect(() => {
        let cancelled = false;

        void loadMonacoEditor()
            .then((monaco) => {
                if (cancelled) {
                    return;
                }

                monaco.editor.setTheme(ASTERO_MONACO_THEME);
                setLoaderState('ready');
            })
            .catch(() => {
                if (cancelled) {
                    return;
                }

                setLoaderState('fallback');
            });

        return () => {
            cancelled = true;
        };
    }, []);

    useEffect(() => {
        if (loaderState !== 'ready' || !mountRef.current || editorRef.current) {
            return;
        }

        const monaco = window.monaco;

        if (!monaco?.editor) {
            return;
        }

        const editor = monaco.editor.create(mountRef.current, {
            ...creationOptionsRef.current,
            theme: ASTERO_MONACO_THEME,
        });

        editorRef.current = editor;
        monaco.editor.setTheme(ASTERO_MONACO_THEME);

        const changeSubscription = editor.onDidChangeModelContent(() => {
            const nextValue = editor.getValue();

            if (nextValue === lastValueRef.current) {
                return;
            }

            lastValueRef.current = nextValue;
            onChange(nextValue);
        });

        const blurSubscription = editor.onDidBlurEditorText?.(() => {
            onBlur?.();
        });

        const stopEnterPropagation = (event: KeyboardEvent) => {
            const activeElement = document.activeElement;

            if (
                event.key !== 'Enter' ||
                !mountRef.current ||
                !activeElement ||
                !mountRef.current.contains(activeElement)
            ) {
                return;
            }

            event.stopPropagation();
        };

        window.addEventListener('keydown', stopEnterPropagation, true);
        window.addEventListener('keyup', stopEnterPropagation, true);

        requestAnimationFrame(() => {
            editor.layout();

            if (
                shouldFocusEditorRef.current &&
                typeof editor.focus === 'function'
            ) {
                editor.focus();
                shouldFocusEditorRef.current = false;
            }
        });

        return () => {
            const model = editor.getModel();

            changeSubscription.dispose();
            blurSubscription?.dispose();
            window.removeEventListener('keydown', stopEnterPropagation, true);
            window.removeEventListener('keyup', stopEnterPropagation, true);
            editor.dispose();

            if (
                model &&
                typeof model.dispose === 'function' &&
                typeof model.isDisposed === 'function' &&
                !model.isDisposed()
            ) {
                model.dispose();
            }

            editorRef.current = null;
        };
    }, [loaderState, onBlur, onChange]);

    useEffect(() => {
        const editor = editorRef.current;

        if (!editor || value === editor.getValue()) {
            return;
        }

        editor.setValue(value);
    }, [value]);

    useEffect(() => {
        const editor = editorRef.current;
        const monaco = window.monaco;

        if (!editor || !monaco?.editor) {
            return;
        }

        const model = editor.getModel();
        if (model) {
            monaco.editor.setModelLanguage(model, language);
        }

        editor.updateOptions({
            ...options,
            readOnly: disabled,
        });
    }, [disabled, language, options]);

    const fallbackEditor = (
        <div className="relative h-full">
            <Textarea
                value={value}
                name={name}
                onFocus={() => {
                    shouldFocusEditorRef.current = true;
                }}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                spellCheck={false}
                disabled={disabled}
                className={cn(
                    'h-full min-h-full resize-none font-mono text-sm leading-6',
                    textareaClassName,
                )}
                style={{ minHeight: resolvedHeight }}
            />
            <div className="pointer-events-none absolute top-3 right-3 flex items-center gap-2 rounded-md border bg-background/95 px-2 py-1 text-xs text-muted-foreground shadow-sm">
                {loaderState === 'loading' ? (
                    <>
                        <Spinner className="size-3.5" />
                        <span>{loadingLabel}</span>
                    </>
                ) : (
                    <span>{fallbackLabel}</span>
                )}
            </div>
        </div>
    );

    return (
        <div
            className={cn('relative', className)}
            style={{ height: resolvedHeight }}
        >
            {loaderState === 'ready' ? (
                <>
                    {name ? (
                        <textarea
                            name={name}
                            value={value}
                            readOnly
                            hidden
                            aria-hidden="true"
                            tabIndex={-1}
                        />
                    ) : null}
                    <div
                        className={cn(
                            'h-full overflow-hidden rounded-[4px] border border-[#ced4da] bg-[#0f111a]',
                            editorClassName,
                        )}
                    >
                        <div ref={mountRef} className="h-full w-full" />
                    </div>
                </>
            ) : (
                fallbackEditor
            )}
        </div>
    );
}
