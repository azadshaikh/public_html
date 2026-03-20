import { useId, useMemo } from 'react';
import AceEditor, { type IAceEditorProps } from 'react-ace';
import 'ace-builds/src-noconflict/ext-language_tools';
import 'ace-builds/src-noconflict/mode-css';
import 'ace-builds/src-noconflict/mode-html';
import 'ace-builds/src-noconflict/mode-javascript';
import 'ace-builds/src-noconflict/theme-monokai';
import { cn } from '@/lib/utils';

export type AceCodeEditorProps = {
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    language?: 'css' | 'html' | 'javascript';
    height?: number | string;
    name?: string;
    disabled?: boolean;
    placeholder?: string;
    className?: string;
    editorClassName?: string;
    options?: IAceEditorProps['setOptions'];
};

function normalizeHeight(height: AceCodeEditorProps['height']): string {
    if (typeof height === 'number') {
        return `${height}px`;
    }

    return height ?? '16rem';
}

export function AceCodeEditor({
    value,
    onChange,
    onBlur,
    language = 'javascript',
    height = '16rem',
    name,
    disabled = false,
    placeholder,
    className,
    editorClassName,
    options,
}: AceCodeEditorProps) {
    const generatedId = useId();
    const resolvedHeight = useMemo(() => normalizeHeight(height), [height]);
    const resolvedName = useMemo(() => name ?? `ace-editor-${generatedId.replace(/:/g, '-')}`, [generatedId, name]);
    const setOptions = useMemo<NonNullable<IAceEditorProps['setOptions']>>(
        () => ({
            enableBasicAutocompletion: true,
            enableLiveAutocompletion: true,
            enableSnippets: true,
            showLineNumbers: true,
            tabSize: 2,
            useWorker: false,
            wrap: true,
            ...(options ?? {}),
        }),
        [options],
    );

    return (
        <div className={cn('relative', className)} style={{ height: resolvedHeight }}>
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

            <div className={cn('h-full overflow-hidden rounded-md border border-border/70 bg-[#272822]', editorClassName)}>
                <AceEditor
                    mode={language}
                    theme="monokai"
                    name={resolvedName}
                    value={value}
                    onChange={onChange}
                    onBlur={() => onBlur?.()}
                    readOnly={disabled}
                    placeholder={placeholder}
                    width="100%"
                    height={resolvedHeight}
                    fontSize={12}
                    showPrintMargin={false}
                    showGutter
                    highlightActiveLine={!disabled}
                    wrapEnabled
                    editorProps={{ $blockScrolling: true }}
                    setOptions={setOptions}
                    className="h-full w-full"
                />
            </div>
        </div>
    );
}