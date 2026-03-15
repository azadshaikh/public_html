'use client';

import { TrailingBlockPlugin } from 'platejs';
import { createPlatePlugin } from 'platejs/react';

import { AutoformatKit } from '@/components/editor/plugins/autoformat-kit';
import { BasicBlocksKit } from '@/components/editor/plugins/basic-blocks-kit';
import { BasicMarksKit } from '@/components/editor/plugins/basic-marks-kit';
import { ExitBreakKit } from '@/components/editor/plugins/exit-break-kit';
import { ListKit } from '@/components/editor/plugins/list-kit';
import { FixedToolbar } from '@/components/ui/fixed-toolbar';
import { NoteToolbarButtons } from '@/components/ui/note-toolbar-buttons';

const NoteToolbarKit = [
    createPlatePlugin({
        key: 'note-fixed-toolbar',
        render: {
            beforeEditable: () => (
                <FixedToolbar className="rounded-t-lg bg-background">
                    <NoteToolbarButtons />
                </FixedToolbar>
            ),
        },
    }),
];

export const NoteEditorKit = [
    // Elements
    ...BasicBlocksKit,

    // Marks
    ...BasicMarksKit,

    // Block Style
    ...ListKit,

    // Editing
    ...AutoformatKit,
    ...ExitBreakKit,
    TrailingBlockPlugin,

    // UI
    ...NoteToolbarKit,
];
