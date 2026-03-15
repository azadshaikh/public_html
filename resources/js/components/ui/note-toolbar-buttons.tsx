'use client';

import * as React from 'react';

import type { DropdownMenuProps } from '@radix-ui/react-dropdown-menu';
import type { TElement } from 'platejs';

import { DropdownMenuItemIndicator } from '@radix-ui/react-dropdown-menu';
import {
    BoldIcon,
    CheckIcon,
    Heading2Icon,
    Heading3Icon,
    ItalicIcon,
    ListIcon,
    ListOrderedIcon,
    MaximizeIcon,
    MinimizeIcon,
    PilcrowIcon,
    StrikethroughIcon,
    UnderlineIcon,
} from 'lucide-react';
import { KEYS } from 'platejs';
import { useEditorReadOnly, useEditorRef, useSelectionFragmentProp } from 'platejs/react';

import { useFullscreen } from '@/components/editor/use-fullscreen';
import {
    getBlockType,
    setBlockType,
} from '@/components/editor/transforms';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { RedoToolbarButton, UndoToolbarButton } from './history-toolbar-button';
import { MarkToolbarButton } from './mark-toolbar-button';
import { ToolbarButton, ToolbarGroup, ToolbarMenuGroup } from './toolbar';

const noteTurnIntoItems = [
    {
        icon: <PilcrowIcon />,
        label: 'Text',
        value: KEYS.p,
    },
    {
        icon: <Heading2Icon />,
        label: 'Heading 2',
        value: 'h2',
    },
    {
        icon: <Heading3Icon />,
        label: 'Heading 3',
        value: 'h3',
    },
    {
        icon: <ListIcon />,
        label: 'Bulleted list',
        value: KEYS.ul,
    },
    {
        icon: <ListOrderedIcon />,
        label: 'Numbered list',
        value: KEYS.ol,
    },
];

function NoteTurnIntoButton(props: DropdownMenuProps) {
    const editor = useEditorRef();
    const [open, setOpen] = React.useState(false);

    const value = useSelectionFragmentProp({
        defaultValue: KEYS.p,
        getProp: (node) => getBlockType(node as TElement),
    });

    const selectedItem = React.useMemo(
        () =>
            noteTurnIntoItems.find((item) => item.value === (value ?? KEYS.p)) ??
            noteTurnIntoItems[0],
        [value],
    );

    return (
        <DropdownMenu open={open} onOpenChange={setOpen} modal={false} {...props}>
            <DropdownMenuTrigger asChild>
                <ToolbarButton
                    className="min-w-[125px]"
                    pressed={open}
                    tooltip="Turn into"
                    isDropdown
                >
                    {selectedItem.label}
                </ToolbarButton>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                className="ignore-click-outside/toolbar min-w-0"
                onCloseAutoFocus={(e) => {
                    e.preventDefault();
                    editor.tf.focus();
                }}
                align="start"
            >
                <ToolbarMenuGroup
                    value={value}
                    onValueChange={(type) => {
                        setBlockType(editor, type);
                    }}
                    label="Turn into"
                >
                    {noteTurnIntoItems.map(({ icon, label, value: itemValue }) => (
                        <DropdownMenuRadioItem
                            key={itemValue}
                            className="min-w-[180px] pl-2 *:first:[span]:hidden"
                            value={itemValue}
                        >
                            <span className="pointer-events-none absolute right-2 flex size-3.5 items-center justify-center">
                                <DropdownMenuItemIndicator>
                                    <CheckIcon />
                                </DropdownMenuItemIndicator>
                            </span>
                            {icon}
                            {label}
                        </DropdownMenuRadioItem>
                    ))}
                </ToolbarMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function NoteToolbarButtons() {
    const readOnly = useEditorReadOnly();
    const { isFullscreen, toggleFullscreen } = useFullscreen();

    return (
        <div className="flex w-full">
            {!readOnly && (
                <>
                    <ToolbarGroup>
                        <UndoToolbarButton />
                        <RedoToolbarButton />
                    </ToolbarGroup>

                    <ToolbarGroup>
                        <NoteTurnIntoButton />
                    </ToolbarGroup>

                    <ToolbarGroup>
                        <MarkToolbarButton nodeType={KEYS.bold} tooltip="Bold (⌘+B)">
                            <BoldIcon />
                        </MarkToolbarButton>

                        <MarkToolbarButton nodeType={KEYS.italic} tooltip="Italic (⌘+I)">
                            <ItalicIcon />
                        </MarkToolbarButton>

                        <MarkToolbarButton
                            nodeType={KEYS.underline}
                            tooltip="Underline (⌘+U)"
                        >
                            <UnderlineIcon />
                        </MarkToolbarButton>

                        <MarkToolbarButton
                            nodeType={KEYS.strikethrough}
                            tooltip="Strikethrough (⌘+⇧+M)"
                        >
                            <StrikethroughIcon />
                        </MarkToolbarButton>
                    </ToolbarGroup>
                </>
            )}

            <div className="grow" />

            <ToolbarGroup>
                <ToolbarButton
                    onClick={toggleFullscreen}
                    tooltip={isFullscreen ? 'Exit fullscreen (Esc)' : 'Fullscreen'}
                >
                    {isFullscreen ? <MinimizeIcon /> : <MaximizeIcon />}
                </ToolbarButton>
            </ToolbarGroup>
        </div>
    );
}
