'use client';

import { Code2Icon, MaximizeIcon, MinimizeIcon } from 'lucide-react';

import type { AsteroNoteController } from '@/components/asteronote/asteronote-types';
import { ToolbarIconButton } from '@/components/asteronote/plugins/toolbar-icon-button';

export function FullscreenPluginControl({
    editor,
}: {
    editor: AsteroNoteController;
}) {
    return (
        <ToolbarIconButton
            editor={editor}
            icon={editor.isFullscreen ? <MinimizeIcon /> : <MaximizeIcon />}
            pressed={editor.isFullscreen}
            tooltip={editor.isFullscreen ? 'Exit fullscreen' : 'Fullscreen'}
            onPress={() => editor.toggleFullscreen()}
        />
    );
}

export function CodeViewPluginControl({
    editor,
}: {
    editor: AsteroNoteController;
}) {
    return (
        <ToolbarIconButton
            editor={editor}
            icon={<Code2Icon />}
            pressed={editor.isCodeView}
            tooltip={editor.isCodeView ? 'Visual editor' : 'Code view'}
            onPress={() => editor.setCodeViewActive(!editor.isCodeView)}
        />
    );
}
