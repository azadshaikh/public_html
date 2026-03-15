'use client';

import { CheckIcon } from 'lucide-react';
import * as React from 'react';

import type {
    AsteroNoteBlockTag,
    AsteroNoteController,
} from '@/components/asteronote/asteronote-types';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ToolbarButton } from '@/components/ui/toolbar';

function blockTagLabel(tag: AsteroNoteBlockTag): string {
    const labels: Record<AsteroNoteBlockTag, string> = {
        p: 'Paragraph',
        h1: 'Heading 1',
        h2: 'Heading 2',
        h3: 'Heading 3',
        h4: 'Heading 4',
        h5: 'Heading 5',
        h6: 'Heading 6',
        blockquote: 'Quote',
        pre: 'Code block',
    };

    return labels[tag];
}

function blockTagShortLabel(tag: AsteroNoteBlockTag): string {
    const labels: Record<AsteroNoteBlockTag, string> = {
        p: 'P',
        h1: 'H1',
        h2: 'H2',
        h3: 'H3',
        h4: 'H4',
        h5: 'H5',
        h6: 'H6',
        blockquote: '❝',
        pre: '</>',
    };

    return labels[tag];
}

function isHeadingBlock(tag: AsteroNoteBlockTag): boolean {
    return tag.startsWith('h');
}

export { blockTagLabel };

export const ASTERONOTE_FULL_BLOCK_FORMATS: AsteroNoteBlockTag[] = [
    'p',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'blockquote',
    'pre',
];

export const ASTERONOTE_LITE_BLOCK_FORMATS: AsteroNoteBlockTag[] = [
    'p',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
];

export function BlockFormatDropdown({
    editor,
    allowedBlocks,
    labelOverride,
}: {
    editor: AsteroNoteController;
    allowedBlocks: AsteroNoteBlockTag[];
    labelOverride?: string;
}) {
    const [open, setOpen] = React.useState(false);
    const selectedBlock = allowedBlocks.includes(editor.formatState.blockTag)
        ? editor.formatState.blockTag
        : allowedBlocks[0];
    const headingBlocks = allowedBlocks.filter(isHeadingBlock);
    const contentBlocks = allowedBlocks.filter(
        (block) => !isHeadingBlock(block),
    );
    const triggerLabel = blockTagShortLabel(selectedBlock);

    const groupedBlocks = [
        {
            label: 'Headings',
            items: headingBlocks,
        },
        {
            label: 'Blocks',
            items: contentBlocks,
        },
    ].filter((group) => group.items.length > 0);

    const renderBlockItem = (block: AsteroNoteBlockTag) => (
        <DropdownMenuItem
            key={block}
            className="gap-2"
            onSelect={(event) => {
                event.preventDefault();
                editor.applyBlockTag(block);
                setOpen(false);
            }}
        >
            <span className="flex min-w-8 shrink-0 items-center justify-center text-xs font-semibold tracking-wide text-foreground">
                {blockTagShortLabel(block)}
            </span>

            <span className="truncate text-sm text-foreground">
                {blockTagLabel(block)}
            </span>

            {selectedBlock === block ? <CheckIcon className="ml-auto" /> : null}
        </DropdownMenuItem>
    );

    return (
        <DropdownMenu
            open={open}
            onOpenChange={(nextOpen) => {
                if (nextOpen) {
                    editor.captureSelection();
                }
                setOpen(nextOpen);
            }}
            modal={false}
        >
            <DropdownMenuTrigger asChild>
                <ToolbarButton
                    disabled={editor.isCodeView}
                    isDropdown
                    className="min-w-14"
                    onMouseDown={(event: React.MouseEvent) =>
                        event.preventDefault()
                    }
                    tooltip={labelOverride ?? blockTagLabel(selectedBlock)}
                >
                    {triggerLabel}
                </ToolbarButton>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                className="ignore-click-outside/toolbar min-w-52"
                align="start"
                sideOffset={6}
                collisionPadding={12}
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                }}
            >
                {groupedBlocks.map((group, groupIndex) => (
                    <React.Fragment key={group.label}>
                        <DropdownMenuLabel>{group.label}</DropdownMenuLabel>
                        {group.items.map(renderBlockItem)}
                        {groupIndex < groupedBlocks.length - 1 ? (
                            <DropdownMenuSeparator />
                        ) : null}
                    </React.Fragment>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function FormatBlockPluginControl({
    editor,
}: {
    editor: AsteroNoteController;
}) {
    return (
        <BlockFormatDropdown
            editor={editor}
            allowedBlocks={ASTERONOTE_FULL_BLOCK_FORMATS}
        />
    );
}

export function HeadingPluginControl({
    editor,
}: {
    editor: AsteroNoteController;
}) {
    return (
        <BlockFormatDropdown
            editor={editor}
            allowedBlocks={ASTERONOTE_LITE_BLOCK_FORMATS}
            labelOverride="Heading"
        />
    );
}
