import { useCallback, useEffect, useRef, useState, type ReactNode, type RefObject } from 'react';
import { Button } from '@/components/ui/button';
import type { AstNodeId, AstNodeMap } from '../../../components/builder/core/ast-types';
import { getElementByAstId } from '../../../components/builder/core/iframe-sync';

type InlineTextEditorActions = {
    setSelected: (nodeIds: AstNodeId[]) => void;
    updateNode: (nodeId: AstNodeId, patch: { props?: Record<string, unknown> }) => void;
};

type UseBuilderInlineTextEditorOptions = {
    actions: InlineTextEditorActions;
    iframeReadyRef: RefObject<boolean>;
    iframeRef: RefObject<HTMLIFrameElement | null>;
    nodes: AstNodeMap;
    nodesRef: RefObject<AstNodeMap>;
    overlayContainerRef: RefObject<HTMLDivElement | null>;
    selectedItemId: AstNodeId | null;
};

type UseBuilderInlineTextEditorResult = {
    canNodeEditInlineText: (nodeId: AstNodeId) => boolean;
    editingTextNodeId: AstNodeId | null;
    inlineTextToolbar: ReactNode;
    requestInlineTextEdit: (nodeId: AstNodeId) => void;
};

export function useBuilderInlineTextEditor({
    actions,
    iframeReadyRef,
    iframeRef,
    nodes,
    nodesRef,
    overlayContainerRef,
    selectedItemId,
}: UseBuilderInlineTextEditorOptions): UseBuilderInlineTextEditorResult {
    const [editingTextNodeId, setEditingTextNodeId] = useState<AstNodeId | null>(null);
    const [inlineTextToolbarState, setInlineTextToolbarState] = useState({
        isBold: false,
        isItalic: false,
        isStrike: false,
        isUnderline: false,
        left: 0,
        top: 0,
        visible: false,
    });
    const editingTextElementRef = useRef<HTMLElement | null>(null);
    const editingSelectionRangeRef = useRef<Range | null>(null);

    const canNodeEditInlineText = useCallback((nodeId: AstNodeId): boolean => {
        const node = nodesRef.current[nodeId];

        if (!node) {
            return false;
        }

        const tagName = (node.tagName ?? node.type).toLowerCase();

        return typeof node.props.content === 'string' || tagName === 'a' || tagName === 'button';
    }, [nodesRef]);

    const focusEditableNodeAtEnd = useCallback((element: HTMLElement): void => {
        const selection = element.ownerDocument.defaultView?.getSelection();

        if (!selection) {
            element.focus();

            return;
        }

        const range = element.ownerDocument.createRange();
        range.selectNodeContents(element);
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
        element.focus();
    }, []);

    const insertHtmlAtSelection = useCallback((element: HTMLElement, html: string): void => {
        const selection = element.ownerDocument.defaultView?.getSelection();

        if (!selection || selection.rangeCount === 0) {
            element.insertAdjacentHTML('beforeend', html);
            focusEditableNodeAtEnd(element);

            return;
        }

        const range = selection.getRangeAt(0);
        const fragment = range.createContextualFragment(html);
        const lastNode = fragment.lastChild;

        range.deleteContents();
        range.insertNode(fragment);

        if (lastNode) {
            range.setStartAfter(lastNode);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
        }
    }, [focusEditableNodeAtEnd]);

    const insertPlainTextAtSelection = useCallback((element: HTMLElement, text: string): void => {
        const escapedText = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\r\n?/g, '\n')
            .replace(/\n/g, '<br>');

        insertHtmlAtSelection(element, escapedText);
    }, [insertHtmlAtSelection]);

    const insertSoftLineBreakAtSelection = useCallback((element: HTMLElement): void => {
        insertHtmlAtSelection(element, '<br>');
    }, [insertHtmlAtSelection]);

    const sanitizeInlineEditableHtml = useCallback((element: HTMLElement): string => {
        const container = element.ownerDocument.createElement('div');
        const template = element.ownerDocument.createElement('template');
        template.innerHTML = element.innerHTML;

        const appendSanitizedNode = (node: Node, parent: Node): void => {
            if (node.nodeType === Node.TEXT_NODE) {
                parent.appendChild(element.ownerDocument.createTextNode(node.textContent ?? ''));

                return;
            }

            if (node.nodeType !== Node.ELEMENT_NODE) {
                return;
            }

            const htmlElement = node as HTMLElement;
            const tagName = htmlElement.tagName.toLowerCase();
            const fontWeight = htmlElement.style.fontWeight.trim().toLowerCase();
            const fontWeightNumber = Number.parseInt(fontWeight, 10);
            const textDecoration = `${htmlElement.style.textDecoration} ${htmlElement.style.textDecorationLine}`.trim().toLowerCase();
            const isBoldSpan = tagName === 'span' && (fontWeight === 'bold' || (!Number.isNaN(fontWeightNumber) && fontWeightNumber >= 600));
            const isItalicSpan = tagName === 'span' && /italic|oblique/.test(htmlElement.style.fontStyle.trim().toLowerCase());
            const isUnderlineSpan = tagName === 'span' && textDecoration.includes('underline');
            const isStrikeSpan = tagName === 'span' && (textDecoration.includes('line-through') || textDecoration.includes('strikethrough'));

            if (tagName === 'br') {
                parent.appendChild(element.ownerDocument.createElement('br'));

                return;
            }

            if (tagName === 'div' || tagName === 'p') {
                const fragment = element.ownerDocument.createDocumentFragment();

                Array.from(htmlElement.childNodes).forEach((childNode) => appendSanitizedNode(childNode, fragment));
                parent.appendChild(fragment);
                parent.appendChild(element.ownerDocument.createElement('br'));

                return;
            }

            if (
                tagName === 'strong'
                || tagName === 'b'
                || tagName === 'em'
                || tagName === 'i'
                || tagName === 'u'
                || tagName === 'ins'
                || tagName === 's'
                || tagName === 'strike'
                || tagName === 'del'
                || isBoldSpan
                || isItalicSpan
                || isUnderlineSpan
                || isStrikeSpan
            ) {
                const shouldWrapStrong = tagName === 'strong' || tagName === 'b' || isBoldSpan;
                const shouldWrapEmphasis = tagName === 'em' || tagName === 'i' || isItalicSpan;
                const shouldWrapUnderline = tagName === 'u' || tagName === 'ins' || isUnderlineSpan;
                const shouldWrapStrike = tagName === 's' || tagName === 'strike' || tagName === 'del' || isStrikeSpan;
                let wrapperParent = parent;

                if (shouldWrapStrong) {
                    const strongWrapper = element.ownerDocument.createElement('strong');
                    parent.appendChild(strongWrapper);
                    wrapperParent = strongWrapper;
                }

                if (shouldWrapEmphasis) {
                    const emphasisWrapper = element.ownerDocument.createElement('em');
                    wrapperParent.appendChild(emphasisWrapper);
                    wrapperParent = emphasisWrapper;
                }

                if (shouldWrapUnderline) {
                    const underlineWrapper = element.ownerDocument.createElement('u');
                    wrapperParent.appendChild(underlineWrapper);
                    wrapperParent = underlineWrapper;
                }

                if (shouldWrapStrike) {
                    const strikeWrapper = element.ownerDocument.createElement('s');
                    wrapperParent.appendChild(strikeWrapper);
                    wrapperParent = strikeWrapper;
                }

                Array.from(htmlElement.childNodes).forEach((childNode) => appendSanitizedNode(childNode, wrapperParent));

                return;
            }

            Array.from(htmlElement.childNodes).forEach((childNode) => appendSanitizedNode(childNode, parent));
        };

        Array.from(template.content.childNodes).forEach((childNode) => appendSanitizedNode(childNode, container));

        return container.innerHTML
            .replace(/(?:<br>\s*){3,}/g, '<br><br>')
            .replace(/^(?:<br>\s*)+|(?:<br>\s*)+$/g, '');
    }, []);

    const toggleInlineFormat = useCallback((element: HTMLElement, command: 'bold' | 'italic' | 'underline' | 'strikeThrough'): void => {
        const editableDocument = element.ownerDocument as Document & {
            execCommand?: (commandId: string, showUi?: boolean, value?: string) => boolean;
        };

        editableDocument.execCommand?.(command, false);
    }, []);

    const getInlineSelectionRange = useCallback((element: HTMLElement): Range | null => {
        const selection = element.ownerDocument.defaultView?.getSelection();

        if (!selection || selection.rangeCount === 0) {
            return null;
        }

        const range = selection.getRangeAt(0);

        if (!element.contains(range.commonAncestorContainer)) {
            return null;
        }

        return range;
    }, []);

    const restoreInlineSelectionRange = useCallback((element: HTMLElement): void => {
        const selection = element.ownerDocument.defaultView?.getSelection();

        if (!selection) {
            element.focus();

            return;
        }

        const savedRange = editingSelectionRangeRef.current;

        if (!savedRange) {
            focusEditableNodeAtEnd(element);

            return;
        }

        selection.removeAllRanges();
        selection.addRange(savedRange);
        element.focus();
    }, [focusEditableNodeAtEnd]);

    const updateInlineTextToolbar = useCallback((): void => {
        const element = editingTextElementRef.current;
        const iframe = iframeRef.current;
        const overlayContainer = overlayContainerRef.current;

        if (!editingTextNodeId || !element || !iframe || !overlayContainer) {
            editingSelectionRangeRef.current = null;
            setInlineTextToolbarState((current) => current.visible
                ? { ...current, visible: false }
                : current);

            return;
        }

        const selectionRange = getInlineSelectionRange(element);

        if (selectionRange) {
            editingSelectionRangeRef.current = selectionRange.cloneRange();
        }

        const selectionRect = selectionRange?.getBoundingClientRect();
        const elementRect = element.getBoundingClientRect();
        const targetRect = selectionRect && (selectionRect.width > 0 || selectionRect.height > 0)
            ? selectionRect
            : elementRect;
        const iframeRect = iframe.getBoundingClientRect();
        const containerRect = overlayContainer.parentElement?.getBoundingClientRect() ?? overlayContainer.getBoundingClientRect();
        const editableDocument = element.ownerDocument as Document & {
            queryCommandState?: (commandId: string) => boolean;
        };
        const nextLeft = iframeRect.left - containerRect.left + targetRect.left + (targetRect.width / 2);
        const nextTop = iframeRect.top - containerRect.top + targetRect.top - 14;

        setInlineTextToolbarState({
            isBold: editableDocument.queryCommandState?.('bold') ?? false,
            isItalic: editableDocument.queryCommandState?.('italic') ?? false,
            isStrike: editableDocument.queryCommandState?.('strikeThrough') ?? false,
            isUnderline: editableDocument.queryCommandState?.('underline') ?? false,
            left: Math.max(48, Math.min(nextLeft, containerRect.width - 48)),
            top: Math.max(12, nextTop),
            visible: true,
        });
    }, [editingTextNodeId, getInlineSelectionRange, iframeRef, overlayContainerRef]);

    const applyInlineToolbarAction = useCallback((action: 'bold' | 'italic' | 'underline' | 'strikeThrough' | 'line-break'): void => {
        const element = editingTextElementRef.current;

        if (!element) {
            return;
        }

        restoreInlineSelectionRange(element);

        if (action === 'bold') {
            toggleInlineFormat(element, 'bold');
        } else if (action === 'italic') {
            toggleInlineFormat(element, 'italic');
        } else if (action === 'underline') {
            toggleInlineFormat(element, 'underline');
        } else if (action === 'strikeThrough') {
            toggleInlineFormat(element, 'strikeThrough');
        } else {
            insertSoftLineBreakAtSelection(element);
        }

        requestAnimationFrame(() => {
            updateInlineTextToolbar();
        });
    }, [insertSoftLineBreakAtSelection, restoreInlineSelectionRange, toggleInlineFormat, updateInlineTextToolbar]);

    const requestInlineTextEdit = useCallback((nodeId: AstNodeId): void => {
        if (!canNodeEditInlineText(nodeId)) {
            return;
        }

        actions.setSelected([nodeId]);
        setEditingTextNodeId(nodeId);
    }, [actions, canNodeEditInlineText]);

    useEffect(() => {
        if (!editingTextNodeId) {
            return;
        }

        if (selectedItemId !== editingTextNodeId || !nodes[editingTextNodeId]) {
            setEditingTextNodeId(null);
        }
    }, [editingTextNodeId, nodes, selectedItemId]);

    useEffect(() => {
        const iframeDoc = iframeRef.current?.contentDocument;

        if (!iframeDoc || !iframeReadyRef.current || !editingTextNodeId) {
            return;
        }

        const element = getElementByAstId(iframeDoc, editingTextNodeId);

        if (!element) {
            setEditingTextNodeId((current) => current === editingTextNodeId ? null : current);

            return;
        }

        editingTextElementRef.current = element;

        const originalHtml = element.innerHTML;
        const originalContentEditable = element.getAttribute('contenteditable');
        const originalSpellcheck = element.getAttribute('spellcheck');
        const originalCursor = element.style.cursor;
        const originalOutline = element.style.outline;
        const originalOutlineOffset = element.style.outlineOffset;
        const originalBoxShadow = element.style.boxShadow;
        const originalBackgroundColor = element.style.backgroundColor;
        const originalCaretColor = element.style.caretColor;
        let isFinalized = false;

        const finalizeEditing = (mode: 'commit' | 'revert'): void => {
            if (isFinalized) {
                return;
            }

            isFinalized = true;

            const nextContent = mode === 'revert'
                ? originalHtml
                : sanitizeInlineEditableHtml(element).replace(/\u00A0/g, ' ');

            if (mode === 'revert') {
                element.innerHTML = originalHtml;
            }

            element.removeEventListener('blur', handleBlur);
            element.removeEventListener('keydown', handleKeyDown);
            element.removeEventListener('paste', handlePaste);
            element.removeEventListener('click', stopPropagation);
            delete element.dataset.builderInlineEditing;

            if (originalContentEditable === null) {
                element.removeAttribute('contenteditable');
            } else {
                element.setAttribute('contenteditable', originalContentEditable);
            }

            if (originalSpellcheck === null) {
                element.removeAttribute('spellcheck');
            } else {
                element.setAttribute('spellcheck', originalSpellcheck);
            }

            element.style.cursor = originalCursor;
            element.style.outline = originalOutline;
            element.style.outlineOffset = originalOutlineOffset;
            element.style.boxShadow = originalBoxShadow;
            element.style.backgroundColor = originalBackgroundColor;
            element.style.caretColor = originalCaretColor;
            editingTextElementRef.current = null;
            editingSelectionRangeRef.current = null;
            setInlineTextToolbarState((current) => current.visible
                ? { ...current, visible: false }
                : current);

            setEditingTextNodeId((current) => current === editingTextNodeId ? null : current);

            if (mode === 'commit') {
                const currentNode = nodesRef.current[editingTextNodeId];
                const currentContent = typeof currentNode?.props.content === 'string' ? currentNode.props.content : originalHtml;

                if (nextContent !== currentContent) {
                    actions.updateNode(editingTextNodeId, {
                        props: {
                            content: nextContent,
                        },
                    });
                }
            }
        };

        const stopPropagation = (event: MouseEvent): void => {
            event.stopPropagation();
        };

        const handleBlur = (): void => {
            finalizeEditing('commit');
        };

        const handleKeyDown = (event: KeyboardEvent): void => {
            event.stopPropagation();

            const isModKey = event.metaKey || event.ctrlKey;

            if (event.key === 'Escape') {
                event.preventDefault();
                finalizeEditing('revert');

                return;
            }

            if (isModKey && event.key.toLowerCase() === 'b') {
                event.preventDefault();
                toggleInlineFormat(element, 'bold');

                return;
            }

            if (isModKey && event.key.toLowerCase() === 'i') {
                event.preventDefault();
                toggleInlineFormat(element, 'italic');

                return;
            }

            if (event.key === 'Enter' && isModKey) {
                event.preventDefault();
                finalizeEditing('commit');

                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                insertSoftLineBreakAtSelection(element);
            }
        };

        const handlePaste = (event: ClipboardEvent): void => {
            event.preventDefault();
            event.stopPropagation();

            insertPlainTextAtSelection(element, event.clipboardData?.getData('text/plain') ?? '');
        };

        const handleToolbarRefresh = (): void => {
            requestAnimationFrame(() => {
                updateInlineTextToolbar();
            });
        };

        const iframeWindow = iframeDoc.defaultView;

        element.setAttribute('contenteditable', 'true');
        element.setAttribute('spellcheck', 'false');
        element.dataset.builderInlineEditing = 'true';
        element.style.cursor = 'text';
        element.style.outline = '2px solid color-mix(in srgb, rgb(14 165 233) 75%, white)';
        element.style.outlineOffset = '4px';
        element.style.boxShadow = '0 0 0 6px color-mix(in srgb, rgb(14 165 233) 18%, transparent)';
        element.style.backgroundColor = 'color-mix(in srgb, rgb(14 165 233) 10%, transparent)';
        element.style.caretColor = 'rgb(14 165 233)';
        element.addEventListener('blur', handleBlur);
        element.addEventListener('keydown', handleKeyDown);
        element.addEventListener('paste', handlePaste);
        element.addEventListener('click', stopPropagation);
        element.addEventListener('mouseup', handleToolbarRefresh);
        element.addEventListener('keyup', handleToolbarRefresh);
        element.addEventListener('input', handleToolbarRefresh);
        iframeDoc.addEventListener('selectionchange', handleToolbarRefresh);
        iframeWindow?.addEventListener('scroll', handleToolbarRefresh);
        window.addEventListener('resize', handleToolbarRefresh);

        requestAnimationFrame(() => {
            if (!isFinalized) {
                focusEditableNodeAtEnd(element);
                updateInlineTextToolbar();
            }
        });

        return () => {
            element.removeEventListener('mouseup', handleToolbarRefresh);
            element.removeEventListener('keyup', handleToolbarRefresh);
            element.removeEventListener('input', handleToolbarRefresh);
            iframeDoc.removeEventListener('selectionchange', handleToolbarRefresh);
            iframeWindow?.removeEventListener('scroll', handleToolbarRefresh);
            window.removeEventListener('resize', handleToolbarRefresh);
            finalizeEditing('commit');
        };
    }, [
        actions,
        editingTextNodeId,
        focusEditableNodeAtEnd,
        iframeReadyRef,
        iframeRef,
        insertPlainTextAtSelection,
        insertSoftLineBreakAtSelection,
        nodesRef,
        sanitizeInlineEditableHtml,
        toggleInlineFormat,
        updateInlineTextToolbar,
    ]);

    const inlineTextToolbar = inlineTextToolbarState.visible ? (
        <div
            className="pointer-events-none absolute z-[70]"
            style={{
                left: inlineTextToolbarState.left,
                top: inlineTextToolbarState.top,
                transform: 'translate(-50%, -100%)',
            }}
        >
            <div className="pointer-events-auto flex items-center gap-0.5 rounded-lg border border-slate-200 bg-white px-1 py-1 shadow-[0_10px_24px_rgba(15,23,42,0.16)]">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Bold"
                    aria-pressed={inlineTextToolbarState.isBold}
                    className={inlineTextToolbarState.isBold
                        ? 'size-7 rounded-md bg-sky-100 text-sky-700 hover:bg-sky-100 hover:text-sky-700'
                        : 'size-7 rounded-md text-slate-700 hover:bg-slate-100 hover:text-slate-900'}
                    onMouseDown={(event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        applyInlineToolbarAction('bold');
                    }}
                >
                    <span className="text-[11px] font-bold">B</span>
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Italic"
                    aria-pressed={inlineTextToolbarState.isItalic}
                    className={inlineTextToolbarState.isItalic
                        ? 'size-7 rounded-md bg-sky-100 text-sky-700 hover:bg-sky-100 hover:text-sky-700'
                        : 'size-7 rounded-md text-slate-700 hover:bg-slate-100 hover:text-slate-900'}
                    onMouseDown={(event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        applyInlineToolbarAction('italic');
                    }}
                >
                    <span className="text-[11px] italic">I</span>
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Underline"
                    aria-pressed={inlineTextToolbarState.isUnderline}
                    className={inlineTextToolbarState.isUnderline
                        ? 'size-7 rounded-md bg-sky-100 text-sky-700 hover:bg-sky-100 hover:text-sky-700'
                        : 'size-7 rounded-md text-slate-700 hover:bg-slate-100 hover:text-slate-900'}
                    onMouseDown={(event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        applyInlineToolbarAction('underline');
                    }}
                >
                    <span className="text-[11px] underline underline-offset-2">U</span>
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Strikethrough"
                    aria-pressed={inlineTextToolbarState.isStrike}
                    className={inlineTextToolbarState.isStrike
                        ? 'size-7 rounded-md bg-sky-100 text-sky-700 hover:bg-sky-100 hover:text-sky-700'
                        : 'size-7 rounded-md text-slate-700 hover:bg-slate-100 hover:text-slate-900'}
                    onMouseDown={(event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        applyInlineToolbarAction('strikeThrough');
                    }}
                >
                    <span className="text-[11px] line-through">S</span>
                </Button>
                <div className="mx-0.5 h-4 w-px bg-slate-200" />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Insert line break"
                    className="size-7 rounded-md text-slate-700 hover:bg-slate-100 hover:text-slate-900"
                    onMouseDown={(event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        applyInlineToolbarAction('line-break');
                    }}
                >
                    <span className="text-[13px] leading-none">↵</span>
                </Button>
            </div>
        </div>
    ) : null;

    return {
        canNodeEditInlineText,
        editingTextNodeId,
        inlineTextToolbar,
        requestInlineTextEdit,
    };
}
