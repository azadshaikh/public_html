'use client';

import * as React from 'react';

import type { AsteroNoteController } from '@/components/asteronote/asteronote-types';
import {
    ASTERONOTE_FULL_BLOCK_FORMATS,
    ASTERONOTE_LITE_BLOCK_FORMATS,
    BlockFormatDropdown,
} from '@/components/asteronote/plugins/block-format';
import {
    BoldPluginControl,
    ItalicPluginControl,
    RemoveFormatPluginControl,
    StrikethroughPluginControl,
    UnderlinePluginControl,
} from '@/components/asteronote/plugins/inline-format';
import { LinkPluginControl } from '@/components/asteronote/plugins/link';
import { Toolbar, ToolbarGroup } from '@/components/ui/toolbar';

export function BubbleToolbar({ editor }: { editor: AsteroNoteController }) {
    const toolbarRef = React.useRef<HTMLDivElement | null>(null);
    const [resolvedPosition, setResolvedPosition] = React.useState<{
        left: number;
        top: number;
    } | null>(null);

    React.useLayoutEffect(() => {
        const toolbar = toolbarRef.current;
        const desiredPosition = editor.floatingPosition;
        const viewport = toolbar?.parentElement;

        if (!toolbar || !viewport || !desiredPosition) {
            setResolvedPosition(null);
            return;
        }

        const edgePadding = 8;
        const minLeft = viewport.scrollLeft + edgePadding;
        const maxLeft =
            viewport.scrollLeft +
            viewport.clientWidth -
            toolbar.offsetWidth -
            edgePadding;
        const minTop = viewport.scrollTop + edgePadding;
        const maxTop =
            viewport.scrollTop +
            viewport.clientHeight -
            toolbar.offsetHeight -
            edgePadding;

        setResolvedPosition({
            left: Math.min(
                Math.max(
                    desiredPosition.left - toolbar.offsetWidth / 2,
                    minLeft,
                ),
                Math.max(minLeft, maxLeft),
            ),
            top: Math.min(
                Math.max(desiredPosition.top, minTop),
                Math.max(minTop, maxTop),
            ),
        });
    }, [editor.floatingPosition, editor.formatState.blockTag]);

    if (!editor.floatingToolbarEnabled || !editor.floatingPosition) {
        return null;
    }

    const allowedBlocks =
        editor.bundle === 'full'
            ? ASTERONOTE_FULL_BLOCK_FORMATS
            : ASTERONOTE_LITE_BLOCK_FORMATS;

    return (
        <div
            ref={toolbarRef}
            className="absolute z-20 w-max max-w-[min(calc(100%-1rem),26rem)]"
            style={{
                left: resolvedPosition?.left ?? editor.floatingPosition.left,
                top: resolvedPosition?.top ?? editor.floatingPosition.top,
            }}
        >
            <Toolbar className="overflow-x-auto rounded-xl border border-border/80 bg-background/95 p-1 shadow-lg ring-1 ring-border/50 backdrop-blur supports-backdrop-blur:bg-background/85">
                <ToolbarGroup>
                    <BoldPluginControl editor={editor} />
                    <ItalicPluginControl editor={editor} />
                    <UnderlinePluginControl editor={editor} />
                    <StrikethroughPluginControl editor={editor} />
                </ToolbarGroup>
                <ToolbarGroup>
                    <LinkPluginControl editor={editor} />
                    <RemoveFormatPluginControl editor={editor} />
                </ToolbarGroup>
                <ToolbarGroup>
                    <BlockFormatDropdown
                        editor={editor}
                        allowedBlocks={allowedBlocks}
                    />
                </ToolbarGroup>
            </Toolbar>
        </div>
    );
}
