'use client';

import * as React from 'react';

import {
    buildImageHtml,
    buildVideoHtml,
    convertListRoot,
    createTableHtml,
    defaultAsteroNoteFormatState,
    escapeHtmlAttr,
    escapeHtmlContent,
    getClosestLink,
    getClosestListRoot,
    getCurrentFormatState,
    getCurrentTableCell,
    getSelectionRect,
    insertHtmlAtSelection,
    isSelectionInside,
    normalizeEditorOutput,
    placeCaretInCell,
    restoreSelectionRange,
    sanitizeAsteroNoteHtml,
    saveSelectionRange,
    updateTableAtSelection,
} from '@/components/asteronote/asteronote-dom';
import {
    ASTERONOTE_FULL_TOOLBAR,
    ASTERONOTE_LITE_TOOLBAR,
} from '@/components/asteronote/asteronote-types';
import type {
    AsteroNoteAction,
    AsteroNoteBlockTag,
    AsteroNoteBundle,
    AsteroNoteController,
    AsteroNotePluginComponent,
    AsteroNoteTableAction,
    AsteroNoteToolbar,
} from '@/components/asteronote/asteronote-types';
import {
    hasMeaningfulHtmlContent,
    normalizeHtmlEditorValue,
} from '@/components/asteronote/html-editor-utils';
import type { HtmlEditorProps } from '@/components/asteronote/html-editor-utils';
import {
    asteronotePluginMap,
    BubbleToolbar,
} from '@/components/asteronote/plugins';
import {
    flattenToolbar,
} from '@/components/asteronote/toolbar-utils';
import {
    FullscreenProvider,
    useFullscreen,
} from '@/components/asteronote/use-fullscreen';
import { MonacoEditor } from '@/components/code-editor/monaco-editor';
import { FixedToolbar } from '@/components/ui/fixed-toolbar';
import { cn } from '@/lib/utils';

const defaultHeights: Record<AsteroNoteBundle, number> = {
    full: 360,
    lite: 220,
};

const defaultMinHeights: Record<AsteroNoteBundle, number> = {
    full: 320,
    lite: 180,
};

export type AsteroNoteEditorProps = HtmlEditorProps & {
    bundle: AsteroNoteBundle;
    toolbar?: AsteroNoteToolbar;
};

function getToolbarForBundle(bundle: AsteroNoteBundle): AsteroNoteToolbar {
    return bundle === 'full'
        ? ASTERONOTE_FULL_TOOLBAR
        : ASTERONOTE_LITE_TOOLBAR;
}

/**
 * Keyboard shortcuts matching the old AsteroNote plugin pattern.
 */
const KEYBOARD_SHORTCUTS: Array<{
    ctrl: boolean;
    shift?: boolean;
    key: string;
    command: Parameters<AsteroNoteController['applyInlineCommand']>[0];
}> = [
    { ctrl: true, key: 'b', command: 'bold' },
    { ctrl: true, key: 'i', command: 'italic' },
    { ctrl: true, key: 'u', command: 'underline' },
    { ctrl: true, shift: true, key: 's', command: 'strikeThrough' },
];

function createGroupDefinitions(
    toolbar: AsteroNoteToolbar,
    pluginMap: Record<string, AsteroNotePluginComponent>,
) {
    const regions = toolbar.some(Array.isArray)
        ? toolbar.map((item) => (Array.isArray(item) ? item : [item]))
        : [flattenToolbar(toolbar)];

    return regions.map((region) =>
        region
            .map((action) => {
                const Plugin = pluginMap[action];

                if (!Plugin) {
                    return null;
                }

                return { action, Plugin };
            })
            .filter(
                (
                    item,
                ): item is {
                    action: AsteroNoteAction;
                    Plugin: AsteroNotePluginComponent;
                } => item !== null,
            ),
    );
}

function AsteroNoteEditorInner({
    bundle,
    className,
    id,
    invalid = false,
    onBlur,
    onChange,
    placeholder,
    toolbar,
    value,
}: AsteroNoteEditorProps) {
    const { isFullscreen, toggleFullscreen } = useFullscreen();
    const editorRef = React.useRef<HTMLDivElement | null>(null);
    const editorViewportRef = React.useRef<HTMLDivElement | null>(null);
    const resizerRef = React.useRef<{
        startY: number;
        startHeight: number;
    } | null>(null);
    const latestHtmlRef = React.useRef('');
    const codeViewValueRef = React.useRef(normalizeHtmlEditorValue(value));
    const savedRangeRef = React.useRef<Range | null>(null);
    const shouldHydrateVisualEditorRef = React.useRef(false);
    const toolbarConfig = React.useMemo(
        () => toolbar ?? getToolbarForBundle(bundle),
        [bundle, toolbar],
    );
    const toolbarActions = React.useMemo(
        () => flattenToolbar(toolbarConfig),
        [toolbarConfig],
    );
    const toolbarGroups = React.useMemo(
        () => createGroupDefinitions(toolbarConfig, asteronotePluginMap),
        [toolbarConfig],
    );
    const floatingToolbarEnabled = toolbarActions.includes('bubbleToolbar');

    const [htmlState, setHtmlState] = React.useState(() =>
        normalizeHtmlEditorValue(value),
    );
    const [codeViewValue, setCodeViewValue] = React.useState(() =>
        normalizeHtmlEditorValue(value),
    );
    const [isCodeView, setIsCodeView] = React.useState(false);
    const [isFocused, setIsFocused] = React.useState(false);
    const [floatingPosition, setFloatingPosition] = React.useState<{
        left: number;
        top: number;
    } | null>(null);
    const [formatState, setFormatState] = React.useState(
        defaultAsteroNoteFormatState,
    );
    const [height, setHeight] = React.useState(defaultHeights[bundle]);

    const minHeight = defaultMinHeights[bundle];
    const placeholderText =
        placeholder ??
        (bundle === 'full'
            ? 'Compose rich HTML content.'
            : 'Add context, follow-up details, or an internal reminder.');

    const focus = React.useCallback(() => {
        if (isCodeView) {
            return;
        }

        editorRef.current?.focus();
    }, [isCodeView]);

    const captureSelection = React.useCallback(() => {
        savedRangeRef.current = saveSelectionRange(editorRef.current);
    }, []);

    const restoreSelection = React.useCallback(() => {
        restoreSelectionRange(editorRef.current, savedRangeRef.current);
    }, []);

    const syncEditorDom = React.useCallback(
        (nextValue: string) => {
            const normalized = normalizeHtmlEditorValue(nextValue);
            latestHtmlRef.current = normalized;
            codeViewValueRef.current = normalized;
            setHtmlState(normalized);
            setCodeViewValue(normalized);

            if (!editorRef.current || isCodeView) {
                return;
            }

            const current = sanitizeAsteroNoteHtml(editorRef.current.innerHTML);
            const next = sanitizeAsteroNoteHtml(normalized);

            if (current !== next) {
                editorRef.current.innerHTML = normalized;
            }
        },
        [isCodeView],
    );

    React.useEffect(() => {
        const normalized = normalizeHtmlEditorValue(value);
        const currentEditorValue = editorRef.current
            ? sanitizeAsteroNoteHtml(editorRef.current.innerHTML)
            : null;
        const nextEditorValue = sanitizeAsteroNoteHtml(normalized);

        if (
            normalized !== latestHtmlRef.current ||
            (!isCodeView &&
                currentEditorValue !== null &&
                currentEditorValue !== nextEditorValue)
        ) {
            syncEditorDom(value);
        }
    }, [isCodeView, syncEditorDom, value]);

    React.useEffect(() => {
        if (!isFullscreen) {
            return;
        }

        const originalOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = originalOverflow;
        };
    }, [isFullscreen]);

    const refreshState = React.useCallback(() => {
        const root = editorRef.current;
        setFormatState(getCurrentFormatState(root));

        // CRITICAL: Always save the selection when it's inside the editor.
        // This enables toolbar buttons to restore the selection even when
        // a Radix toggle item or dropdown steals focus.
        if (root && isSelectionInside(root)) {
            savedRangeRef.current = saveSelectionRange(root);
        }

        if (
            !floatingToolbarEnabled ||
            isCodeView ||
            !root ||
            !isSelectionInside(root)
        ) {
            setFloatingPosition(null);
            return;
        }

        const rect = getSelectionRect(root);
        const viewport = editorViewportRef.current;

        if (!rect || !viewport) {
            setFloatingPosition(null);
            return;
        }

        const viewportRect = viewport.getBoundingClientRect();
        const toolbarHeight = 44;
        const edgePadding = 12;
        const verticalOffset = 8;
        const centeredLeft =
            rect.left -
            viewportRect.left +
            viewport.scrollLeft +
            rect.width / 2;
        const preferredTop =
            rect.top -
            viewportRect.top +
            viewport.scrollTop -
            toolbarHeight -
            verticalOffset;
        const fallbackTop =
            rect.bottom -
            viewportRect.top +
            viewport.scrollTop +
            verticalOffset;
        const minLeft = viewport.scrollLeft + edgePadding;
        const maxLeft =
            viewport.scrollLeft + viewport.clientWidth - edgePadding;
        const minTop = viewport.scrollTop + edgePadding;
        const maxTop =
            viewport.scrollTop +
            viewport.clientHeight -
            toolbarHeight -
            edgePadding;

        setFloatingPosition({
            left: Math.min(Math.max(centeredLeft, minLeft), maxLeft),
            top: Math.min(
                Math.max(
                    preferredTop >= minTop ? preferredTop : fallbackTop,
                    minTop,
                ),
                Math.max(minTop, maxTop),
            ),
        });
    }, [floatingToolbarEnabled, isCodeView]);

    React.useEffect(() => {
        if (
            isCodeView ||
            !shouldHydrateVisualEditorRef.current ||
            !editorRef.current
        ) {
            return;
        }

        editorRef.current.innerHTML = htmlState;
        shouldHydrateVisualEditorRef.current = false;
        refreshState();
    }, [htmlState, isCodeView, refreshState]);

    React.useEffect(() => {
        const handleSelectionChange = () => {
            refreshState();
        };

        document.addEventListener('selectionchange', handleSelectionChange);
        window.addEventListener('scroll', handleSelectionChange, true);
        window.addEventListener('resize', handleSelectionChange);

        return () => {
            document.removeEventListener(
                'selectionchange',
                handleSelectionChange,
            );
            window.removeEventListener('scroll', handleSelectionChange, true);
            window.removeEventListener('resize', handleSelectionChange);
        };
    }, [refreshState]);

    /** Light change on input — avoids heavy sanitization on every keystroke. */
    const emitVisualChange = React.useCallback(() => {
        if (!editorRef.current) {
            return;
        }

        const raw = editorRef.current.innerHTML;
        const meaningfulHtml = hasMeaningfulHtmlContent(raw) ? raw : '';
        const normalized = normalizeHtmlEditorValue(meaningfulHtml);

        latestHtmlRef.current = normalized;
        codeViewValueRef.current = normalized;
        setHtmlState(normalized);
        setCodeViewValue(normalized);
        onChange(meaningfulHtml);
        refreshState();
    }, [onChange, refreshState]);

    /** Full sanitized change — used on blur, command execution, etc. */
    const emitSanitizedChange = React.useCallback(() => {
        if (!editorRef.current) {
            return;
        }

        const nextHtml = normalizeEditorOutput(editorRef.current.innerHTML);
        const normalized = normalizeHtmlEditorValue(nextHtml);

        latestHtmlRef.current = normalized;
        codeViewValueRef.current = normalized;
        setHtmlState(normalized);
        setCodeViewValue(normalized);
        onChange(nextHtml);
        refreshState();
    }, [onChange, refreshState]);

    const commitCodeView = React.useCallback(
        (nextValue?: string) => {
            const nextHtml = nextValue ?? codeViewValueRef.current;
            const normalized = normalizeEditorOutput(nextHtml);
            const visualValue = normalizeHtmlEditorValue(normalized);

            latestHtmlRef.current = visualValue;
            codeViewValueRef.current = visualValue;
            setHtmlState(visualValue);
            setCodeViewValue(visualValue);
            onChange(normalized);

            if (editorRef.current) {
                editorRef.current.innerHTML = visualValue;
            }

            refreshState();
        },
        [onChange, refreshState],
    );

    const applyInlineCommand = React.useCallback(
        (
            command:
                | 'undo'
                | 'redo'
                | 'bold'
                | 'italic'
                | 'underline'
                | 'strikeThrough'
                | 'removeFormat'
                | 'insertHorizontalRule',
        ) => {
            if (isCodeView) {
                return;
            }

            restoreSelection();
            focus();
            document.execCommand(command, false);
            emitSanitizedChange();
        },
        [emitSanitizedChange, focus, isCodeView, restoreSelection],
    );

    const applyBlockTag = React.useCallback(
        (tag: AsteroNoteBlockTag) => {
            if (isCodeView) {
                return;
            }

            restoreSelection();
            focus();
            document.execCommand('formatBlock', false, `<${tag}>`);
            emitSanitizedChange();
        },
        [emitSanitizedChange, focus, isCodeView, restoreSelection],
    );

    const applyList = React.useCallback(
        (type: 'ul' | 'ol', style?: string) => {
            if (isCodeView) {
                return;
            }

            restoreSelection();
            focus();

            const currentList = getClosestListRoot(editorRef.current);

            if (currentList) {
                const currentType = currentList.tagName.toLowerCase();

                if (currentType !== type) {
                    const converted = convertListRoot(currentList, type);
                    converted.style.listStyleType = style ?? '';
                } else {
                    currentList.style.listStyleType = style ?? '';
                }
            } else {
                document.execCommand(
                    type === 'ul' ? 'insertUnorderedList' : 'insertOrderedList',
                    false,
                );
                const nextList = getClosestListRoot(editorRef.current);
                if (nextList) {
                    nextList.style.listStyleType = style ?? '';
                }
            }

            emitSanitizedChange();
        },
        [emitSanitizedChange, focus, isCodeView, restoreSelection],
    );

    const applyAlignment = React.useCallback(
        (action: Parameters<AsteroNoteController['applyAlignment']>[0]) => {
            if (isCodeView) {
                return;
            }

            restoreSelection();
            focus();

            const commandMap: Record<string, string> = {
                left: 'justifyLeft',
                center: 'justifyCenter',
                right: 'justifyRight',
                justify: 'justifyFull',
                indent: 'indent',
                outdent: 'outdent',
            };

            document.execCommand(commandMap[action], false);
            emitSanitizedChange();
        },
        [emitSanitizedChange, focus, isCodeView, restoreSelection],
    );

    const insertLink = React.useCallback(
        ({
            rel,
            target,
            text,
            url,
        }: Parameters<AsteroNoteController['insertLink']>[0]) => {
            if (isCodeView) {
                return;
            }

            const safeUrl = url.trim();

            restoreSelection();
            focus();

            if (safeUrl !== '' && /^\s*javascript:/i.test(safeUrl)) {
                return;
            }

            const existingLink = getClosestLink(editorRef.current);
            const selection = window.getSelection();

            if (existingLink) {
                if (safeUrl) {
                    existingLink.setAttribute('href', safeUrl);
                }
                if (text) {
                    existingLink.textContent = text;
                }
                if (target) {
                    existingLink.setAttribute('target', target);
                } else {
                    existingLink.removeAttribute('target');
                }
                if (rel) {
                    existingLink.setAttribute('rel', rel);
                } else {
                    existingLink.removeAttribute('rel');
                }
                emitSanitizedChange();
                return;
            }

            if (
                selection &&
                selection.rangeCount > 0 &&
                !selection.getRangeAt(0).collapsed
            ) {
                if (!safeUrl) {
                    return;
                }

                document.execCommand('createLink', false, safeUrl);
                const createdLink = getClosestLink(editorRef.current);

                if (createdLink) {
                    createdLink.setAttribute('href', safeUrl);
                    if (text) {
                        createdLink.textContent = text;
                    }
                    if (target) {
                        createdLink.setAttribute('target', target);
                    }
                    if (rel) {
                        createdLink.setAttribute('rel', rel);
                    }
                }
            } else if (safeUrl) {
                const safeTarget = target
                    ? ` target="${escapeHtmlAttr(target)}"`
                    : '';
                const safeRel = rel ? ` rel="${escapeHtmlAttr(rel)}"` : '';
                const safeText = escapeHtmlContent(text || safeUrl);

                insertHtmlAtSelection(
                    editorRef.current,
                    `<a href="${escapeHtmlAttr(safeUrl)}"${safeTarget}${safeRel}>${safeText}</a>`,
                );
            }

            emitSanitizedChange();
        },
        [emitSanitizedChange, focus, isCodeView, restoreSelection],
    );

    const removeLink = React.useCallback(() => {
        if (isCodeView) {
            return;
        }

        restoreSelection();
        focus();
        document.execCommand('unlink', false);
        emitSanitizedChange();
    }, [emitSanitizedChange, focus, isCodeView, restoreSelection]);

    const insertImage = React.useCallback(
        (payload: Parameters<AsteroNoteController['insertImage']>[0]) => {
            if (isCodeView || payload.src.trim() === '') {
                return;
            }

            restoreSelection();
            focus();
            insertHtmlAtSelection(editorRef.current, buildImageHtml(payload));
            emitSanitizedChange();
        },
        [emitSanitizedChange, focus, isCodeView, restoreSelection],
    );

    const insertVideo = React.useCallback(
        (payload: Parameters<AsteroNoteController['insertVideo']>[0]) => {
            if (isCodeView) {
                return;
            }

            const html = buildVideoHtml(payload);

            if (!html) {
                return;
            }

            restoreSelection();
            focus();
            insertHtmlAtSelection(editorRef.current, html);
            emitSanitizedChange();
        },
        [emitSanitizedChange, focus, isCodeView, restoreSelection],
    );

    const insertTable = React.useCallback(
        ({
            columns,
            rows,
        }: Parameters<AsteroNoteController['insertTable']>[0]) => {
            if (isCodeView) {
                return;
            }

            restoreSelection();
            focus();
            insertHtmlAtSelection(
                editorRef.current,
                createTableHtml(rows, columns),
            );

            // Focus the first cell of the LAST inserted table.
            const tables = editorRef.current?.querySelectorAll('table');
            const lastTable = tables?.[tables.length - 1];
            const firstCell =
                lastTable?.querySelector('tbody td') ??
                lastTable?.querySelector('td, th');

            if (firstCell instanceof HTMLTableCellElement) {
                placeCaretInCell(firstCell);
            }

            emitSanitizedChange();
        },
        [emitSanitizedChange, focus, isCodeView, restoreSelection],
    );

    const updateTable = React.useCallback(
        (action: AsteroNoteTableAction) => {
            if (isCodeView) {
                return;
            }

            restoreSelection();
            focus();
            updateTableAtSelection(editorRef.current, action);
            emitSanitizedChange();
        },
        [emitSanitizedChange, focus, isCodeView, restoreSelection],
    );

    const setCodeViewActive = React.useCallback(
        (active: boolean) => {
            if (active) {
                if (editorRef.current) {
                    const nextValue = sanitizeAsteroNoteHtml(
                        editorRef.current.innerHTML,
                    );
                    codeViewValueRef.current = nextValue;
                    setCodeViewValue(nextValue);
                }
                setIsCodeView(true);
                setFloatingPosition(null);
                return;
            }

            commitCodeView();
            shouldHydrateVisualEditorRef.current = true;
            setIsCodeView(false);
            requestAnimationFrame(() => {
                focus();
            });
        },
        [commitCodeView, focus],
    );

    const handleEditorFocus = React.useCallback(() => {
        setIsFocused(true);

        try {
            document.execCommand('defaultParagraphSeparator', false, 'p');
        } catch {
            // Browser-specific no-op.
        }

        refreshState();
    }, [refreshState]);

    const handleEditorBlur = React.useCallback(() => {
        setIsFocused(false);
        emitSanitizedChange();
        onBlur?.();
    }, [emitSanitizedChange, onBlur]);

    const handleKeyDown = React.useCallback(
        (event: React.KeyboardEvent<HTMLDivElement>) => {
            if (isCodeView) {
                return;
            }

            // Tab navigation in tables
            if (event.key === 'Tab') {
                const cell = getCurrentTableCell(editorRef.current);

                if (cell) {
                    event.preventDefault();
                    const row = cell.closest('tr');

                    if (!row) {
                        return;
                    }

                    const cells = Array.from(row.cells);
                    const idx = cells.indexOf(cell);

                    if (event.shiftKey) {
                        if (idx > 0) {
                            placeCaretInCell(cells[idx - 1]);
                        } else {
                            const prevRow =
                                row.previousElementSibling as HTMLTableRowElement | null;
                            if (prevRow?.cells.length) {
                                placeCaretInCell(
                                    prevRow.cells[prevRow.cells.length - 1],
                                );
                            }
                        }
                    } else {
                        if (idx < cells.length - 1) {
                            placeCaretInCell(cells[idx + 1]);
                        } else {
                            const nextRow =
                                row.nextElementSibling as HTMLTableRowElement | null;
                            if (nextRow?.cells.length) {
                                placeCaretInCell(nextRow.cells[0]);
                            }
                        }
                    }
                }

                return;
            }

            // Keyboard shortcuts
            const isMac = /Mac|iPod|iPhone|iPad/.test(navigator.platform);
            const ctrl = isMac ? event.metaKey : event.ctrlKey;

            if (!ctrl) {
                return;
            }

            const key = event.key.toLowerCase();

            for (const shortcut of KEYBOARD_SHORTCUTS) {
                if (shortcut.key !== key) {
                    continue;
                }
                if (shortcut.shift && !event.shiftKey) {
                    continue;
                }
                if (!shortcut.shift && event.shiftKey) {
                    continue;
                }

                event.preventDefault();
                event.stopPropagation();
                applyInlineCommand(shortcut.command);
                return;
            }
        },
        [applyInlineCommand, isCodeView],
    );

    const handlePaste = React.useCallback(
        (event: React.ClipboardEvent<HTMLDivElement>) => {
            if (isCodeView) {
                return;
            }

            const html = event.clipboardData.getData('text/html');
            const plain = event.clipboardData.getData('text/plain');

            if (!html && !plain) {
                return;
            }

            event.preventDefault();

            if (html) {
                const clean = sanitizeAsteroNoteHtml(html);
                document.execCommand('insertHTML', false, clean);
            } else {
                const escaped = plain
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
                const paragraphs = escaped
                    .split(/\n{2,}/)
                    .map((p) => `<p>${p.replace(/\n/g, '<br>')}</p>`)
                    .join('');
                document.execCommand(
                    'insertHTML',
                    false,
                    paragraphs || `<p>${escaped}</p>`,
                );
            }

            emitSanitizedChange();
        },
        [emitSanitizedChange, isCodeView],
    );

    const startResizing = React.useCallback(
        (event: React.MouseEvent<HTMLButtonElement>) => {
            event.preventDefault();
            resizerRef.current = {
                startY: event.clientY,
                startHeight: height,
            };

            const handleMouseMove = (moveEvent: MouseEvent) => {
                if (!resizerRef.current) {
                    return;
                }

                const delta = moveEvent.clientY - resizerRef.current.startY;
                setHeight(
                    Math.max(minHeight, resizerRef.current.startHeight + delta),
                );
            };

            const handleMouseUp = () => {
                resizerRef.current = null;
                document.removeEventListener('mousemove', handleMouseMove);
                document.removeEventListener('mouseup', handleMouseUp);
            };

            document.addEventListener('mousemove', handleMouseMove);
            document.addEventListener('mouseup', handleMouseUp);
        },
        [height, minHeight],
    );

    const controller: AsteroNoteController = React.useMemo(
        () => ({
            id,
            bundle,
            isCodeView,
            isFullscreen,
            floatingToolbarEnabled,
            floatingPosition,
            formatState,
            codeViewValue,
            focus,
            captureSelection,
            restoreSelection,
            refreshState,
            applyInlineCommand,
            applyBlockTag,
            applyList,
            applyAlignment,
            insertLink,
            removeLink,
            insertImage,
            insertVideo,
            insertTable,
            updateTable,
            setCodeViewActive,
            setCodeViewValue,
            toggleFullscreen,
        }),
        [
            id,
            bundle,
            isCodeView,
            isFullscreen,
            floatingToolbarEnabled,
            floatingPosition,
            formatState,
            codeViewValue,
            focus,
            captureSelection,
            restoreSelection,
            refreshState,
            applyInlineCommand,
            applyBlockTag,
            applyList,
            applyAlignment,
            insertLink,
            removeLink,
            insertImage,
            insertVideo,
            insertTable,
            updateTable,
            setCodeViewActive,
            toggleFullscreen,
        ],
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
            <FixedToolbar className="rounded-t-[inherit] bg-background">
                <div className="w-full overflow-x-auto">
                    <div className="flex min-w-max items-center gap-2 pr-1">
                        {toolbarGroups[0]?.map(({ action, Plugin }, index) => (
                            <Plugin
                                key={`left-${action}-${index}`}
                                editor={controller}
                            />
                        ))}

                        {toolbarGroups[1]?.length ? (
                            <div className="flex items-center gap-2 px-1">
                                {toolbarGroups[1].map(
                                    ({ action, Plugin }, index) => (
                                        <Plugin
                                            key={`center-${action}-${index}`}
                                            editor={controller}
                                        />
                                    ),
                                )}
                            </div>
                        ) : null}

                        {toolbarGroups[2]?.length ? (
                            <div className="flex items-center gap-2 pl-1">
                                {toolbarGroups[2].map(
                                    ({ action, Plugin }, index) => (
                                        <Plugin
                                            key={`right-${action}-${index}`}
                                            editor={controller}
                                        />
                                    ),
                                )}
                            </div>
                        ) : null}
                    </div>
                </div>
            </FixedToolbar>

            <div
                className={cn(
                    'relative bg-background',
                    isFullscreen && 'flex min-h-0 flex-1 flex-col',
                )}
            >
                {!isCodeView &&
                !isFocused &&
                !hasMeaningfulHtmlContent(htmlState) ? (
                    <div className="pointer-events-none absolute inset-x-4 top-4 text-sm text-muted-foreground/80">
                        {placeholderText}
                    </div>
                ) : null}

                {isCodeView ? (
                    <MonacoEditor
                        value={codeViewValue}
                        onChange={(value) => {
                            codeViewValueRef.current = value;
                            setCodeViewValue(value);
                            onChange(value);
                        }}
                        onBlur={() => {
                            commitCodeView();
                            onBlur?.();
                        }}
                        language="html"
                        height={height}
                        placeholder={placeholderText}
                        className={cn(
                            'min-h-0 rounded-none border-0 shadow-none',
                            isFullscreen && 'flex-1',
                        )}
                        editorClassName="h-full rounded-none border-0 bg-[#0f111a]"
                        textareaClassName="min-h-0 rounded-none border-0 font-mono text-sm shadow-none focus-visible:ring-0"
                    />
                ) : (
                    <div
                        ref={editorViewportRef}
                        className={cn(
                            'relative cursor-text scroll-py-14 overflow-auto px-4 py-4',
                            isFullscreen && 'min-h-0 flex-1',
                        )}
                        style={{ height }}
                        onMouseDown={(event) => {
                            if (event.target === event.currentTarget) {
                                editorRef.current?.focus();
                            }
                        }}
                        onScroll={refreshState}
                    >
                        {floatingToolbarEnabled ? (
                            <BubbleToolbar editor={controller} />
                        ) : null}

                        <div
                            id={id}
                            ref={editorRef}
                            contentEditable
                            suppressContentEditableWarning
                            role="textbox"
                            aria-invalid={invalid || undefined}
                            aria-multiline="true"
                            className={cn(
                                'relative min-h-full w-full outline-none',
                                'break-words whitespace-pre-wrap',
                                '[&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4',
                                '[&_blockquote]:my-4 [&_blockquote]:border-l-2 [&_blockquote]:border-border [&_blockquote]:pl-4 [&_blockquote]:text-muted-foreground',
                                '[&_figure]:my-4 [&_figure_img]:max-w-full [&_figure_img]:rounded-md [&_figure_img]:border [&_figure_img]:border-border',
                                '[&_figcaption]:mt-2 [&_figcaption]:text-center [&_figcaption]:text-sm [&_figcaption]:text-muted-foreground',
                                '[&_h1]:mt-4 [&_h1]:mb-2 [&_h1]:text-3xl [&_h1]:font-semibold',
                                '[&_h2]:mt-4 [&_h2]:mb-2 [&_h2]:text-2xl [&_h2]:font-semibold',
                                '[&_h3]:mt-4 [&_h3]:mb-2 [&_h3]:text-xl [&_h3]:font-semibold',
                                '[&_h4]:mt-4 [&_h4]:mb-2 [&_h4]:text-lg [&_h4]:font-semibold',
                                '[&_h5]:mt-4 [&_h5]:mb-2 [&_h5]:font-semibold',
                                '[&_h6]:mt-4 [&_h6]:mb-2 [&_h6]:font-semibold [&_h6]:text-muted-foreground',
                                '[&_iframe]:aspect-video [&_iframe]:w-full [&_iframe]:rounded-md [&_iframe]:border [&_iframe]:border-border',
                                '[&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:marker:text-muted-foreground',
                                '[&_p]:my-2',
                                '[&_pre]:my-4 [&_pre]:overflow-x-auto [&_pre]:rounded-md [&_pre]:bg-muted [&_pre]:p-3 [&_pre]:font-mono [&_pre]:text-sm',
                                '[&_s]:line-through [&_strong]:font-semibold [&_u]:underline',
                                '[&_table]:my-4 [&_table]:w-full [&_table]:border-collapse',
                                '[&_tbody_td]:border [&_tbody_td]:border-border [&_tbody_td]:p-2',
                                '[&_thead_th]:border [&_thead_th]:border-border [&_thead_th]:bg-muted [&_thead_th]:p-2 [&_thead_th]:text-left',
                                '[&_ul]:list-disc [&_ul]:pl-6 [&_ul]:marker:text-muted-foreground',
                                '[&_video]:my-4 [&_video]:max-w-full [&_video]:rounded-md [&_video]:border [&_video]:border-border',
                            )}
                            onBlur={handleEditorBlur}
                            onFocus={handleEditorFocus}
                            onInput={emitVisualChange}
                            onKeyDown={handleKeyDown}
                            onKeyUp={refreshState}
                            onMouseUp={refreshState}
                            onPaste={handlePaste}
                        />
                    </div>
                )}
            </div>

            <button
                type="button"
                aria-label="Resize editor"
                className="flex h-5 w-full cursor-row-resize items-center justify-center border-t border-border bg-muted/20 text-muted-foreground"
                onMouseDown={startResizing}
            >
                <span className="h-1 w-8 rounded-full bg-border" />
            </button>
        </div>
    );
}

export function AsteroNoteEditor(props: AsteroNoteEditorProps) {
    return (
        <FullscreenProvider>
            <AsteroNoteEditorInner {...props} />
        </FullscreenProvider>
    );
}
