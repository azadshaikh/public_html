'use client';

import * as React from 'react';

import type { AsteroNoteController } from '@/components/editor/asteronote-types';
import { ToolbarButton } from '@/components/ui/toolbar';

/**
 * Shared toolbar button wrapper for AsteroNote plugins.
 *
 * CRITICAL: onMouseDown captures the current selection before the toolbar
 * steals focus, then calls onPress, and finally refreshes state.
 */
export function ToolbarIconButton({
    disabled,
    editor,
    icon,
    onPress,
    pressed,
    tooltip,
}: {
    disabled?: boolean;
    editor: AsteroNoteController;
    icon: React.ReactNode;
    onPress: () => void;
    pressed?: boolean;
    tooltip: string;
}) {
    return (
        <ToolbarButton
            disabled={disabled}
            pressed={pressed}
            tooltip={tooltip}
            onMouseDown={(event: React.MouseEvent) => {
                event.preventDefault();
                editor.captureSelection();
                onPress();
                editor.refreshState();
            }}
        >
            {icon}
        </ToolbarButton>
    );
}
