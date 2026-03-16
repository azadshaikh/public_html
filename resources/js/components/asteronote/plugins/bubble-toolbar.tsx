'use client';

import * as React from 'react';

import type { AsteroNoteController } from '@/components/asteronote/asteronote-types';
import { AlignPluginControl } from '@/components/asteronote/plugins/align';
import { FormatBlockPluginControl } from '@/components/asteronote/plugins/block-format';
import {
    BoldPluginControl,
    ItalicPluginControl,
    StrikethroughPluginControl,
    UnderlinePluginControl,
} from '@/components/asteronote/plugins/inline-format';
import { LinkPluginControl } from '@/components/asteronote/plugins/link';
import { ListPluginControl } from '@/components/asteronote/plugins/list';
import { SeparatorPluginControl } from '@/components/asteronote/plugins/separator';

export function BubbleToolbar({ editor }: { editor: AsteroNoteController }) {
    if (!editor.floatingPosition || editor.isCodeView) {
        return null;
    }

    return (
        <div
            className="absolute z-50 flex items-center gap-1 rounded-lg border border-border/40 bg-popover/95 p-1.5 shadow-md backdrop-blur-md transition-all duration-150 animate-in fade-in zoom-in-95"
            style={{
                top: editor.floatingPosition.top,
                left: editor.floatingPosition.left,
                transform: 'translate(-50%, -100%)',
                marginTop: '-12px',
            }}
            onMouseDown={(event) => event.preventDefault()}
        >
            <FormatBlockPluginControl editor={editor} />
            <SeparatorPluginControl />
            <div className="flex items-center">
                <BoldPluginControl editor={editor} />
                <ItalicPluginControl editor={editor} />
                <UnderlinePluginControl editor={editor} />
                <StrikethroughPluginControl editor={editor} />
            </div>
            <SeparatorPluginControl />
            <LinkPluginControl editor={editor} />
            <SeparatorPluginControl />
            <ListPluginControl editor={editor} />
            <SeparatorPluginControl />
            <AlignPluginControl editor={editor} />

            <div
                className="absolute left-1/2 top-full -mt-[1px] h-2 w-2 -translate-x-1/2 rotate-45 border-b border-r border-border/40 bg-popover/95"
                aria-hidden="true"
            />
        </div>
    );
}

export function BubbleToolbarPluginControl() {
    return null;
}
