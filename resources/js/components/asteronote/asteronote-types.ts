import type * as React from 'react';

export type AsteroNoteAction =
    | 'undo'
    | 'redo'
    | 'bold'
    | 'italic'
    | 'underline'
    | 'strikethrough'
    | 'removeFormat'
    | 'list'
    | 'link'
    | 'formatblock'
    | 'heading'
    | 'hr'
    | 'align'
    | 'image'
    | 'codeview'
    | 'fullscreen'
    | 'video'
    | 'table'
    | 'separator'
    | 'bubbleToolbar';

/**
 * Nested arrays represent toolbar regions, e.g. left / center / right.
 * Flat strings inside a region are rendered in order.
 */
export type AsteroNoteToolbar = Array<AsteroNoteAction | AsteroNoteAction[]>;

export type AsteroNoteBundle = 'full' | 'lite';

export type AsteroNoteBlockTag =
    | 'p'
    | 'h1'
    | 'h2'
    | 'h3'
    | 'h4'
    | 'h5'
    | 'h6'
    | 'blockquote'
    | 'pre';

export type AsteroNoteListType = 'ul' | 'ol' | null;

export type AsteroNoteAlign = 'left' | 'center' | 'right' | 'justify';

export type AsteroNoteFloatingPosition = {
    left: number;
    top: number;
    placement: 'top' | 'bottom';
    arrowOffset: number;
};

export type AsteroNoteFormatState = {
    blockTag: AsteroNoteBlockTag;
    listType: AsteroNoteListType;
    listStyleType: string | null;
    align: AsteroNoteAlign;
    bold: boolean;
    italic: boolean;
    underline: boolean;
    strikethrough: boolean;
    link: boolean;
    inTable: boolean;
};

export type AsteroNoteLinkPayload = {
    text: string;
    url: string;
    target?: string;
    rel?: string;
};

export type AsteroNoteImagePayload = {
    src: string;
    alt?: string;
    srcset?: string;
    sizes?: string;
    width?: string;
    height?: string;
    loading?: 'lazy' | 'eager';
};

export type AsteroNoteVideoPayload = {
    url: string;
};

export type AsteroNoteTablePayload = {
    rows: number;
    columns: number;
};

export type AsteroNoteTableAction =
    | 'add-row-above'
    | 'add-row-below'
    | 'add-column-left'
    | 'add-column-right'
    | 'delete-row'
    | 'delete-column'
    | 'delete-table';

export interface AsteroNoteController {
    id: string;
    bundle: AsteroNoteBundle;
    isCodeView: boolean;
    isFullscreen: boolean;
    floatingToolbarEnabled: boolean;
    floatingPosition: AsteroNoteFloatingPosition | null;
    formatState: AsteroNoteFormatState;
    codeViewValue: string;
    focus: () => void;
    captureSelection: () => void;
    restoreSelection: () => void;
    refreshState: () => void;
    applyInlineCommand: (
        command:
            | 'undo'
            | 'redo'
            | 'bold'
            | 'italic'
            | 'underline'
            | 'strikeThrough'
            | 'removeFormat'
            | 'insertHorizontalRule',
    ) => void;
    applyBlockTag: (tag: AsteroNoteBlockTag) => void;
    applyList: (type: 'ul' | 'ol', style?: string) => void;
    applyAlignment: (action: AsteroNoteAlign | 'indent' | 'outdent') => void;
    insertLink: (payload: AsteroNoteLinkPayload) => void;
    removeLink: () => void;
    insertImage: (payload: AsteroNoteImagePayload) => void;
    insertVideo: (payload: AsteroNoteVideoPayload) => void;
    insertTable: (payload: AsteroNoteTablePayload) => void;
    updateTable: (action: AsteroNoteTableAction) => void;
    setCodeViewActive: (active: boolean) => void;
    setCodeViewValue: (value: string) => void;
    toggleFullscreen: () => void;
}

export type AsteroNotePluginComponent = React.ComponentType<{
    editor: AsteroNoteController;
}>;

export const ASTERONOTE_FULL_TOOLBAR: AsteroNoteToolbar = [
    [
        'undo',
        'redo',
        'separator',
        'formatblock',
        'list',
        'table',
        'separator',
        'bold',
        'italic',
        'underline',
        'strikethrough',
        'align',
        'separator',
        'hr',
        'link',
        'image',
        'video',
    ],
    ['removeFormat'],
    ['codeview', 'fullscreen', 'bubbleToolbar'],
];

export const ASTERONOTE_LITE_TOOLBAR: AsteroNoteToolbar = [
    [
        'heading',
        'separator',
        'list',
        'separator',
        'bold',
        'italic',
        'underline',
        'strikethrough',
        'separator',
        'link',
        'bubbleToolbar',
    ],
];
