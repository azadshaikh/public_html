'use client';

import {
    BoldIcon,
    EraserIcon,
    ItalicIcon,
    MinusIcon,
    RedoIcon,
    StrikethroughIcon,
    UnderlineIcon,
    UndoIcon,
} from 'lucide-react';

import type { AsteroNoteController } from '@/components/editor/asteronote-types';
import { ToolbarIconButton } from '@/components/editor/plugins/toolbar-icon-button';

export function UndoPluginControl({ editor }: { editor: AsteroNoteController }) {
    return (
        <ToolbarIconButton
            disabled={editor.isCodeView}
            editor={editor}
            icon={<UndoIcon />}
            tooltip="Undo"
            onPress={() => editor.applyInlineCommand('undo')}
        />
    );
}

export function RedoPluginControl({ editor }: { editor: AsteroNoteController }) {
    return (
        <ToolbarIconButton
            disabled={editor.isCodeView}
            editor={editor}
            icon={<RedoIcon />}
            tooltip="Redo"
            onPress={() => editor.applyInlineCommand('redo')}
        />
    );
}

export function BoldPluginControl({ editor }: { editor: AsteroNoteController }) {
    return (
        <ToolbarIconButton
            disabled={editor.isCodeView}
            editor={editor}
            icon={<BoldIcon />}
            pressed={editor.formatState.bold}
            tooltip="Bold"
            onPress={() => editor.applyInlineCommand('bold')}
        />
    );
}

export function ItalicPluginControl({ editor }: { editor: AsteroNoteController }) {
    return (
        <ToolbarIconButton
            disabled={editor.isCodeView}
            editor={editor}
            icon={<ItalicIcon />}
            pressed={editor.formatState.italic}
            tooltip="Italic"
            onPress={() => editor.applyInlineCommand('italic')}
        />
    );
}

export function UnderlinePluginControl({ editor }: { editor: AsteroNoteController }) {
    return (
        <ToolbarIconButton
            disabled={editor.isCodeView}
            editor={editor}
            icon={<UnderlineIcon />}
            pressed={editor.formatState.underline}
            tooltip="Underline"
            onPress={() => editor.applyInlineCommand('underline')}
        />
    );
}

export function StrikethroughPluginControl({
    editor,
}: {
    editor: AsteroNoteController;
}) {
    return (
        <ToolbarIconButton
            disabled={editor.isCodeView}
            editor={editor}
            icon={<StrikethroughIcon />}
            pressed={editor.formatState.strikethrough}
            tooltip="Strikethrough"
            onPress={() => editor.applyInlineCommand('strikeThrough')}
        />
    );
}

export function RemoveFormatPluginControl({
    editor,
}: {
    editor: AsteroNoteController;
}) {
    return (
        <ToolbarIconButton
            disabled={editor.isCodeView}
            editor={editor}
            icon={<EraserIcon />}
            tooltip="Remove formatting"
            onPress={() => editor.applyInlineCommand('removeFormat')}
        />
    );
}

export function HorizontalRulePluginControl({
    editor,
}: {
    editor: AsteroNoteController;
}) {
    return (
        <ToolbarIconButton
            disabled={editor.isCodeView}
            editor={editor}
            icon={<MinusIcon />}
            tooltip="Horizontal rule"
            onPress={() => editor.applyInlineCommand('insertHorizontalRule')}
        />
    );
}
