'use client';

import * as React from 'react';

import type { AsteroNoteController } from '@/components/asteronote/asteronote-types';
import { FormatBlockPluginControl } from '@/components/asteronote/plugins/block-format';
import {
    BoldPluginControl,
    ItalicPluginControl,
    StrikethroughPluginControl,
    UnderlinePluginControl,
} from '@/components/asteronote/plugins/inline-format';
import { LinkPluginControl } from '@/components/asteronote/plugins/link';
import { SeparatorPluginControl } from '@/components/asteronote/plugins/separator';
import { Toolbar } from '@/components/ui/toolbar';
import { cn } from '@/lib/utils';

export function BubbleToolbar({ editor }: { editor: AsteroNoteController }) {
    if (!editor.floatingPosition || editor.isCodeView) {
        return null;
    }

    const { top, left, placement, arrowOffset } = editor.floatingPosition;
    const isAbove = placement === 'top';

    return (
        <div
            className={cn(
                'absolute z-50 animate-in rounded-md border border-border/40 bg-popover/95 px-0.5 py-0.5 shadow-md backdrop-blur-md transition-all duration-150 zoom-in-95 fade-in',
                isAbove ? 'origin-bottom' : 'origin-top',
            )}
            style={{
                top: top,
                left: left,
                transform: isAbove
                    ? 'translate(-50%, calc(-100% - 8px))'
                    : 'translate(-50%, 8px)',
            }}
            onMouseDown={(event) => event.preventDefault()}
        >
            <Toolbar className="gap-0.5">
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
            </Toolbar>
        </div>
    );
}

export function BubbleToolbarPluginControl() {
    return null;
}
