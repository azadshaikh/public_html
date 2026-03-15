'use client';

import { DropdownMenuItemIndicator } from '@radix-ui/react-dropdown-menu';
import {
    BoldIcon,
    CheckIcon,
    Heading2Icon,
    Heading3Icon,
    ItalicIcon,
    ListIcon,
    ListOrderedIcon,
    MaximizeIcon,
    MinimizeIcon,
    PilcrowIcon,
    RedoIcon,
    StrikethroughIcon,
    UnderlineIcon,
    UndoIcon,
} from 'lucide-react';
import * as React from 'react';

import type { HtmlEditorProps } from '@/components/editor/html-editor-utils';
import {
    hasMeaningfulHtmlContent,
    normalizeHtmlEditorValue,
} from '@/components/editor/html-editor-utils';
import {
    FullscreenProvider,
    useFullscreen,
} from '@/components/editor/use-fullscreen';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { FixedToolbar } from '@/components/ui/fixed-toolbar';
import {
    Toolbar,
    ToolbarButton,
    ToolbarGroup,
    ToolbarMenuGroup,
} from '@/components/ui/toolbar';
import { cn } from '@/lib/utils';

type EditorMode = 'note' | 'full';
type BlockType = 'p' | 'h2' | 'h3' | 'ul' | 'ol';

type SimpleHtmlEditorProps = HtmlEditorProps & {
    mode: EditorMode;
    floatingToolbar?: boolean;
};

type FloatingPosition = {
    left: number;
    top: number;
};

type FormatState = {
    blockType: BlockType;
    bold: boolean;
    italic: boolean;
    underline: boolean;
    strikethrough: boolean;
};

const blockOptions: Array<{
    icon: React.ReactNode;
    label: string;
    value: BlockType;
}> = [
    { icon: <PilcrowIcon />, label: 'Text', value: 'p' },
    { icon: <Heading2Icon />, label: 'Heading 2', value: 'h2' },
    { icon: <Heading3Icon />, label: 'Heading 3', value: 'h3' },
    { icon: <ListIcon />, label: 'Bulleted list', value: 'ul' },
    { icon: <ListOrderedIcon />, label: 'Numbered list', value: 'ol' },
];

const defaultFormatState: FormatState = {
    blockType: 'p',
    bold: false,
    italic: false,
    underline: false,
    strikethrough: false,
};

function isSelectionInside(root: HTMLElement | null): boolean {
    const selection = window.getSelection();

    if (!root || !selection || selection.rangeCount === 0) {
        return false;
    }

    const { anchorNode, focusNode } = selection;

    return (
        !!anchorNode &&
        !!focusNode &&
        root.contains(anchorNode) &&
        root.contains(focusNode)
    );
}

function renameElement(element: HTMLElement, tagName: string): HTMLElement {
    const replacement = document.createElement(tagName);

    while (element.firstChild) {
        replacement.appendChild(element.firstChild);
    }

    element.replaceWith(replacement);

    return replacement;
}

function unwrapElement(element: HTMLElement): void {
    const parent = element.parentNode;

    if (!parent) {
        return;
    }

    while (element.firstChild) {
        parent.insertBefore(element.firstChild, element);
    }

    parent.removeChild(element);
}

function sanitizeEditorHtml(html: string): string {
    const container = document.createElement('div');
    container.innerHTML = html;

    const cleanNode = (node: Node): void => {
        if (node.nodeType === Node.COMMENT_NODE) {
            node.parentNode?.removeChild(node);
            return;
        }

        if (node.nodeType !== Node.ELEMENT_NODE) {
            return;
        }

        let element = node as HTMLElement;
        const tag = element.tagName.toLowerCase();

        if (tag === 'b') {
            element = renameElement(element, 'strong');
        } else if (tag === 'i') {
            element = renameElement(element, 'em');
        } else if (tag === 'strike') {
            element = renameElement(element, 's');
        }

        for (const attribute of Array.from(element.attributes)) {
            element.removeAttribute(attribute.name);
        }

        for (const child of Array.from(element.childNodes)) {
            cleanNode(child);
        }

        if (['span', 'font'].includes(element.tagName.toLowerCase())) {
            unwrapElement(element);
        }
    };

    for (const child of Array.from(container.childNodes)) {
        cleanNode(child);
    }

    for (const child of Array.from(container.childNodes)) {
        if (child.nodeType === Node.TEXT_NODE && child.textContent?.trim()) {
            const paragraph = document.createElement('p');
            paragraph.textContent = child.textContent;
            container.replaceChild(paragraph, child);
            continue;
        }

        if (child.nodeType !== Node.ELEMENT_NODE) {
            continue;
        }

        const element = child as HTMLElement;
        const tag = element.tagName.toLowerCase();
        const hasBlockChild = !!element.querySelector(
            'p, h1, h2, h3, h4, h5, h6, ul, ol, li, blockquote, pre',
        );

        if (tag === 'div' && !hasBlockChild) {
            renameElement(element, 'p');
        }
    }

    const cleaned = container.innerHTML.trim();

    return cleaned === '<p><br></p>' || cleaned === '<br>' ? '' : cleaned;
}

function getSelectedBlockType(root: HTMLElement | null): BlockType {
    if (!root || !isSelectionInside(root)) {
        return 'p';
    }

    const selection = window.getSelection();
    let node = selection?.anchorNode ?? null;

    while (node && node !== root) {
        if (node instanceof HTMLElement) {
            const tag = node.tagName.toLowerCase();

            if (
                tag === 'h2' ||
                tag === 'h3' ||
                tag === 'ul' ||
                tag === 'ol' ||
                tag === 'p'
            ) {
                return tag;
            }

            if (tag === 'li') {
                const list = node.closest('ul,ol');
                const listTag = list?.tagName.toLowerCase();

                if (listTag === 'ul' || listTag === 'ol') {
                    return listTag;
                }
            }
        }

        node = node.parentNode;
    }

    return 'p';
}

function queryCommandState(command: string): boolean {
    try {
        return document.queryCommandState(command);
    } catch {
        return false;
    }
}

function useSimpleEditorState(
    editorRef: React.RefObject<HTMLDivElement | null>,
    floatingToolbar: boolean,
) {
    const savedRangeRef = React.useRef<Range | null>(null);
    const [formatState, setFormatState] =
        React.useState<FormatState>(defaultFormatState);
    const [floatingPosition, setFloatingPosition] =
        React.useState<FloatingPosition | null>(null);

    const refreshState = React.useCallback(() => {
        const root = editorRef.current;
        const selection = window.getSelection();

        setFormatState({
            blockType: getSelectedBlockType(root),
            bold: queryCommandState('bold'),
            italic: queryCommandState('italic'),
            underline: queryCommandState('underline'),
            strikethrough: queryCommandState('strikeThrough'),
        });

        if (
            !floatingToolbar ||
            !root ||
            !selection ||
            selection.rangeCount === 0 ||
            selection.isCollapsed ||
            !isSelectionInside(root) ||
            selection.toString().trim() === ''
        ) {
            setFloatingPosition(null);
            return;
        }

        const range = selection.getRangeAt(0);
        const rect = range.getBoundingClientRect();
        savedRangeRef.current = range.cloneRange();

        if (!rect.width && !rect.height) {
            setFloatingPosition(null);
            return;
        }

        setFloatingPosition({
            left: rect.left + rect.width / 2,
            top: rect.top - 12,
        });
    }, [editorRef, floatingToolbar]);

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

    const restoreSelection = React.useCallback(() => {
        const selection = window.getSelection();
        const root = editorRef.current;
        const range = savedRangeRef.current;

        if (!selection || !root || !range) {
            return;
        }

        root.focus();
        selection.removeAllRanges();
        selection.addRange(range);
    }, [editorRef]);

    return {
        floatingPosition,
        formatState,
        refreshState,
        restoreSelection,
    };
}

function TurnIntoButton({
    blockType,
    onSelect,
}: {
    blockType: BlockType;
    onSelect: (type: BlockType) => void;
}) {
    const [open, setOpen] = React.useState(false);
    const selectedItem =
        blockOptions.find((item) => item.value === blockType) ??
        blockOptions[0];

    return (
        <DropdownMenu open={open} onOpenChange={setOpen} modal={false}>
            <DropdownMenuTrigger asChild>
                <ToolbarButton
                    className="min-w-[125px]"
                    pressed={open}
                    tooltip="Turn into"
                    isDropdown
                    onMouseDown={(event) => event.preventDefault()}
                >
                    {selectedItem.label}
                </ToolbarButton>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                className="ignore-click-outside/toolbar min-w-0"
                align="start"
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                }}
            >
                <ToolbarMenuGroup
                    value={blockType}
                    onValueChange={(value) => onSelect(value as BlockType)}
                    label="Turn into"
                >
                    {blockOptions.map(({ icon, label, value }) => (
                        <DropdownMenuRadioItem
                            key={value}
                            className="min-w-[180px] pl-2 *:first:[span]:hidden"
                            value={value}
                            onSelect={(event) => {
                                event.preventDefault();
                                onSelect(value);
                                setOpen(false);
                            }}
                        >
                            <span className="pointer-events-none absolute right-2 flex size-3.5 items-center justify-center">
                                <DropdownMenuItemIndicator>
                                    <CheckIcon />
                                </DropdownMenuItemIndicator>
                            </span>
                            {icon}
                            {label}
                        </DropdownMenuRadioItem>
                    ))}
                </ToolbarMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function EditorToolbar({
    blockType,
    bold,
    italic,
    underline,
    strikethrough,
    isFullscreen,
    onBlock,
    onCommand,
    onUndo,
    onRedo,
    onToggleFullscreen,
}: {
    blockType: BlockType;
    bold: boolean;
    italic: boolean;
    underline: boolean;
    strikethrough: boolean;
    isFullscreen: boolean;
    onBlock: (type: BlockType) => void;
    onCommand: (
        command: 'bold' | 'italic' | 'underline' | 'strikeThrough',
    ) => void;
    onUndo: () => void;
    onRedo: () => void;
    onToggleFullscreen: () => void;
}) {
    return (
        <>
            <ToolbarGroup>
                <ToolbarButton
                    tooltip="Undo"
                    onMouseDown={(event) => {
                        event.preventDefault();
                        onUndo();
                    }}
                >
                    <UndoIcon />
                </ToolbarButton>
                <ToolbarButton
                    tooltip="Redo"
                    onMouseDown={(event) => {
                        event.preventDefault();
                        onRedo();
                    }}
                >
                    <RedoIcon />
                </ToolbarButton>
            </ToolbarGroup>

            <ToolbarGroup>
                <TurnIntoButton blockType={blockType} onSelect={onBlock} />
            </ToolbarGroup>

            <ToolbarGroup>
                <ToolbarButton
                    tooltip="Bold (⌘+B)"
                    pressed={bold}
                    onMouseDown={(event) => {
                        event.preventDefault();
                        onCommand('bold');
                    }}
                >
                    <BoldIcon />
                </ToolbarButton>
                <ToolbarButton
                    tooltip="Italic (⌘+I)"
                    pressed={italic}
                    onMouseDown={(event) => {
                        event.preventDefault();
                        onCommand('italic');
                    }}
                >
                    <ItalicIcon />
                </ToolbarButton>
                <ToolbarButton
                    tooltip="Underline (⌘+U)"
                    pressed={underline}
                    onMouseDown={(event) => {
                        event.preventDefault();
                        onCommand('underline');
                    }}
                >
                    <UnderlineIcon />
                </ToolbarButton>
                <ToolbarButton
                    tooltip="Strikethrough"
                    pressed={strikethrough}
                    onMouseDown={(event) => {
                        event.preventDefault();
                        onCommand('strikeThrough');
                    }}
                >
                    <StrikethroughIcon />
                </ToolbarButton>
            </ToolbarGroup>

            <div className="grow" />

            <ToolbarGroup>
                <ToolbarButton
                    tooltip={
                        isFullscreen ? 'Exit fullscreen (Esc)' : 'Fullscreen'
                    }
                    onMouseDown={(event) => {
                        event.preventDefault();
                        onToggleFullscreen();
                    }}
                >
                    {isFullscreen ? <MinimizeIcon /> : <MaximizeIcon />}
                </ToolbarButton>
            </ToolbarGroup>
        </>
    );
}

function FloatingEditorToolbar({
    position,
    blockType,
    bold,
    italic,
    underline,
    strikethrough,
    onBlock,
    onCommand,
}: {
    position: FloatingPosition;
    blockType: BlockType;
    bold: boolean;
    italic: boolean;
    underline: boolean;
    strikethrough: boolean;
    onBlock: (type: BlockType) => void;
    onCommand: (
        command: 'bold' | 'italic' | 'underline' | 'strikeThrough',
    ) => void;
}) {
    return (
        <Toolbar
            className="fixed z-50 -translate-x-1/2 -translate-y-full overflow-x-auto rounded-md border bg-popover p-1 whitespace-nowrap shadow-md"
            style={{ left: position.left, top: position.top }}
        >
            <ToolbarGroup>
                <TurnIntoButton blockType={blockType} onSelect={onBlock} />
            </ToolbarGroup>
            <ToolbarGroup>
                <ToolbarButton
                    tooltip="Bold (⌘+B)"
                    pressed={bold}
                    onMouseDown={(event) => {
                        event.preventDefault();
                        onCommand('bold');
                    }}
                >
                    <BoldIcon />
                </ToolbarButton>
                <ToolbarButton
                    tooltip="Italic (⌘+I)"
                    pressed={italic}
                    onMouseDown={(event) => {
                        event.preventDefault();
                        onCommand('italic');
                    }}
                >
                    <ItalicIcon />
                </ToolbarButton>
                <ToolbarButton
                    tooltip="Underline (⌘+U)"
                    pressed={underline}
                    onMouseDown={(event) => {
                        event.preventDefault();
                        onCommand('underline');
                    }}
                >
                    <UnderlineIcon />
                </ToolbarButton>
                <ToolbarButton
                    tooltip="Strikethrough"
                    pressed={strikethrough}
                    onMouseDown={(event) => {
                        event.preventDefault();
                        onCommand('strikeThrough');
                    }}
                >
                    <StrikethroughIcon />
                </ToolbarButton>
            </ToolbarGroup>
        </Toolbar>
    );
}

function SimpleHtmlEditorInner({
    className,
    id,
    invalid = false,
    mode,
    onBlur,
    onChange,
    placeholder,
    value,
    floatingToolbar = false,
}: SimpleHtmlEditorProps) {
    const { isFullscreen, toggleFullscreen } = useFullscreen();
    const editorRef = React.useRef<HTMLDivElement | null>(null);
    const latestHtmlRef = React.useRef('');
    const [isFocused, setIsFocused] = React.useState(false);
    const [htmlState, setHtmlState] = React.useState('');
    const { floatingPosition, formatState, refreshState, restoreSelection } =
        useSimpleEditorState(editorRef, floatingToolbar);

    const syncEditorValue = React.useCallback((nextValue: string) => {
        const normalized = normalizeHtmlEditorValue(nextValue);
        latestHtmlRef.current = normalized;
        setHtmlState(normalized);

        if (!editorRef.current) {
            return;
        }

        const current = sanitizeEditorHtml(editorRef.current.innerHTML);

        if (current !== sanitizeEditorHtml(normalized)) {
            editorRef.current.innerHTML = normalized;
        }
    }, []);

    React.useEffect(() => {
        syncEditorValue(value);
    }, [syncEditorValue, value]);

    React.useEffect(() => {
        if (!isFullscreen) {
            return;
        }

        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = '';
        };
    }, [isFullscreen]);

    const emitChange = React.useCallback(() => {
        if (!editorRef.current) {
            return;
        }

        const normalized = sanitizeEditorHtml(editorRef.current.innerHTML);
        const nextHtml = hasMeaningfulHtmlContent(normalized) ? normalized : '';

        latestHtmlRef.current = normalizeHtmlEditorValue(nextHtml);
        setHtmlState(latestHtmlRef.current);
        onChange(nextHtml);
        refreshState();
    }, [onChange, refreshState]);

    const applyCommand = React.useCallback(
        (command: 'bold' | 'italic' | 'underline' | 'strikeThrough') => {
            restoreSelection();
            document.execCommand(command, false);
            emitChange();
        },
        [emitChange, restoreSelection],
    );

    const applyBlock = React.useCallback(
        (type: BlockType) => {
            restoreSelection();

            if (type === 'ul') {
                document.execCommand('insertUnorderedList', false);
            } else if (type === 'ol') {
                document.execCommand('insertOrderedList', false);
            } else {
                document.execCommand('formatBlock', false, type);
            }

            emitChange();
        },
        [emitChange, restoreSelection],
    );

    const handleUndo = React.useCallback(() => {
        restoreSelection();
        document.execCommand('undo', false);
        emitChange();
    }, [emitChange, restoreSelection]);

    const handleRedo = React.useCallback(() => {
        restoreSelection();
        document.execCommand('redo', false);
        emitChange();
    }, [emitChange, restoreSelection]);

    const handleFocus = React.useCallback(() => {
        setIsFocused(true);

        try {
            document.execCommand('defaultParagraphSeparator', false, 'p');
        } catch {
            // No-op.
        }

        refreshState();
    }, [refreshState]);

    const minHeightClass = mode === 'note' ? 'min-h-[200px]' : 'min-h-[320px]';
    const placeholderText =
        placeholder ??
        (mode === 'note'
            ? 'Add context, follow-up details, or an internal reminder.'
            : 'Compose the formatted email body that will be sent to recipients.');

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
                <EditorToolbar
                    blockType={formatState.blockType}
                    bold={formatState.bold}
                    italic={formatState.italic}
                    underline={formatState.underline}
                    strikethrough={formatState.strikethrough}
                    isFullscreen={isFullscreen}
                    onBlock={applyBlock}
                    onCommand={applyCommand}
                    onRedo={handleRedo}
                    onUndo={handleUndo}
                    onToggleFullscreen={toggleFullscreen}
                />
            </FixedToolbar>

            <div
                className={cn(
                    'relative cursor-text bg-background px-4 py-4',
                    minHeightClass,
                    isFullscreen && 'min-h-0 flex-1 overflow-y-auto',
                )}
                onMouseDown={(event) => {
                    if (event.target === event.currentTarget) {
                        editorRef.current?.focus();
                    }
                }}
            >
                {!isFocused && !hasMeaningfulHtmlContent(htmlState) ? (
                    <div className="pointer-events-none absolute inset-x-4 top-4 text-sm text-muted-foreground/80">
                        {placeholderText}
                    </div>
                ) : null}

                <div
                    id={id}
                    ref={editorRef}
                    contentEditable
                    suppressContentEditableWarning
                    role="textbox"
                    aria-multiline="true"
                    aria-invalid={invalid || undefined}
                    className={cn(
                        'relative w-full outline-none',
                        minHeightClass,
                        'break-words whitespace-pre-wrap',
                        '[&_h2]:mt-4 [&_h2]:mb-2 [&_h2]:text-lg [&_h2]:font-semibold',
                        '[&_h3]:mt-4 [&_h3]:mb-2 [&_h3]:text-base [&_h3]:font-semibold',
                        '[&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-2 [&_strong]:font-semibold [&_u]:underline [&_ul]:list-disc [&_ul]:pl-6',
                    )}
                    onBlur={() => {
                        setIsFocused(false);
                        emitChange();
                        onBlur?.();
                    }}
                    onFocus={handleFocus}
                    onInput={emitChange}
                    onKeyUp={refreshState}
                    onMouseUp={refreshState}
                />
            </div>

            {floatingToolbar && floatingPosition ? (
                <FloatingEditorToolbar
                    position={floatingPosition}
                    blockType={formatState.blockType}
                    bold={formatState.bold}
                    italic={formatState.italic}
                    underline={formatState.underline}
                    strikethrough={formatState.strikethrough}
                    onBlock={applyBlock}
                    onCommand={applyCommand}
                />
            ) : null}
        </div>
    );
}

export function SimpleHtmlEditor(props: SimpleHtmlEditorProps) {
    return (
        <FullscreenProvider>
            <SimpleHtmlEditorInner {...props} />
        </FullscreenProvider>
    );
}
