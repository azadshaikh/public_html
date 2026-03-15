'use client';

import type { AsteroNoteController } from '@/components/asteronote/asteronote-types';
import {
    BlockFormatDropdown,
    blockTagLabel,
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
    if (!editor.floatingToolbarEnabled || !editor.floatingPosition) {
        return null;
    }

    return (
        <Toolbar
            className="fixed z-50 -translate-x-1/2 -translate-y-full overflow-hidden rounded-lg border bg-popover p-1 shadow-lg"
            style={{
                left: editor.floatingPosition.left,
                top: editor.floatingPosition.top,
            }}
        >
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
                    allowedBlocks={['p', 'h2', 'h3']}
                    labelOverride={blockTagLabel(
                        editor.formatState.blockTag === 'h2' ||
                            editor.formatState.blockTag === 'h3'
                            ? editor.formatState.blockTag
                            : 'p',
                    )}
                />
            </ToolbarGroup>
        </Toolbar>
    );
}
