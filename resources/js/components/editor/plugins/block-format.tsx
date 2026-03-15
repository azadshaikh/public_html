'use client';

import {
    DropdownMenuItemIndicator,
    DropdownMenuRadioItem,
} from '@radix-ui/react-dropdown-menu';
import {
    CheckIcon,
    Code2Icon,
    Heading1Icon,
    Heading2Icon,
    Heading3Icon,
    Heading4Icon,
    Heading5Icon,
    Heading6Icon,
    PilcrowIcon,
    QuoteIcon,
} from 'lucide-react';
import * as React from 'react';

import type {
    AsteroNoteBlockTag,
    AsteroNoteController,
} from '@/components/editor/asteronote-types';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ToolbarButton, ToolbarMenuGroup } from '@/components/ui/toolbar';

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

function blockTagIcon(tag: AsteroNoteBlockTag): React.ReactNode {
    const icons: Record<AsteroNoteBlockTag, React.ReactNode> = {
        p: <PilcrowIcon />,
        h1: <Heading1Icon />,
        h2: <Heading2Icon />,
        h3: <Heading3Icon />,
        h4: <Heading4Icon />,
        h5: <Heading5Icon />,
        h6: <Heading6Icon />,
        blockquote: <QuoteIcon />,
        pre: <Code2Icon />,
    };

    return icons[tag];
}

export { blockTagLabel };

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
                    onMouseDown={(event: React.MouseEvent) =>
                        event.preventDefault()
                    }
                    tooltip={labelOverride ?? blockTagLabel(selectedBlock)}
                >
                    {labelOverride ?? blockTagLabel(selectedBlock)}
                </ToolbarButton>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                className="ignore-click-outside/toolbar min-w-[13rem]"
                align="start"
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                }}
            >
                <ToolbarMenuGroup
                    value={selectedBlock}
                    onValueChange={(value) => {
                        editor.applyBlockTag(value as AsteroNoteBlockTag);
                        setOpen(false);
                    }}
                    label={labelOverride ?? 'Format'}
                >
                    {allowedBlocks.map((block) => (
                        <DropdownMenuRadioItem
                            key={block}
                            className="pl-2 *:first:[span]:hidden"
                            value={block}
                            onSelect={(event) => {
                                event.preventDefault();
                                editor.applyBlockTag(block);
                                setOpen(false);
                            }}
                        >
                            <span className="pointer-events-none absolute right-2 flex size-3.5 items-center justify-center">
                                <DropdownMenuItemIndicator>
                                    <CheckIcon />
                                </DropdownMenuItemIndicator>
                            </span>
                            {blockTagIcon(block)}
                            {blockTagLabel(block)}
                        </DropdownMenuRadioItem>
                    ))}
                </ToolbarMenuGroup>
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
            allowedBlocks={[
                'p',
                'h1',
                'h2',
                'h3',
                'h4',
                'h5',
                'h6',
                'blockquote',
                'pre',
            ]}
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
            allowedBlocks={['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6']}
            labelOverride="Heading"
        />
    );
}
