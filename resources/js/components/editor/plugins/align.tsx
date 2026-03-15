'use client';

import {
    AlignCenterIcon,
    AlignJustifyIcon,
    AlignLeftIcon,
    AlignRightIcon,
    CheckIcon,
    IndentDecreaseIcon,
    IndentIncreaseIcon,
} from 'lucide-react';
import * as React from 'react';

import type { AsteroNoteController } from '@/components/editor/asteronote-types';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ToolbarButton } from '@/components/ui/toolbar';

const alignOptions = [
    {
        action: 'left' as const,
        label: 'Align left',
        icon: <AlignLeftIcon />,
    },
    {
        action: 'center' as const,
        label: 'Align center',
        icon: <AlignCenterIcon />,
    },
    {
        action: 'right' as const,
        label: 'Align right',
        icon: <AlignRightIcon />,
    },
    {
        action: 'justify' as const,
        label: 'Justify',
        icon: <AlignJustifyIcon />,
    },
];

const indentOptions: Array<{
    action: 'indent' | 'outdent';
    label: string;
    icon: React.ReactNode;
}> = [
    { action: 'outdent', label: 'Outdent', icon: <IndentDecreaseIcon /> },
    { action: 'indent', label: 'Indent', icon: <IndentIncreaseIcon /> },
];

const iconMap: Record<string, React.ReactNode> = {
    left: <AlignLeftIcon />,
    center: <AlignCenterIcon />,
    right: <AlignRightIcon />,
    justify: <AlignJustifyIcon />,
};

export function AlignPluginControl({ editor }: { editor: AsteroNoteController }) {
    const [open, setOpen] = React.useState(false);
    const align = editor.formatState.align;

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
                    pressed={open}
                    tooltip="Alignment"
                >
                    {iconMap[align]}
                </ToolbarButton>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className="ignore-click-outside/toolbar min-w-[14rem]"
                align="start"
                onCloseAutoFocus={(event) => event.preventDefault()}
            >
                <DropdownMenuLabel>Alignment</DropdownMenuLabel>
                {alignOptions.map((option) => (
                    <DropdownMenuItem
                        key={option.action}
                        onSelect={(event) => {
                            event.preventDefault();
                            editor.applyAlignment(option.action);
                            setOpen(false);
                        }}
                    >
                        {option.icon}
                        {option.label}
                        {align === option.action ? (
                            <CheckIcon className="ml-auto" />
                        ) : null}
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                {indentOptions.map((option) => (
                    <DropdownMenuItem
                        key={option.action}
                        onSelect={(event) => {
                            event.preventDefault();
                            editor.applyAlignment(option.action);
                            setOpen(false);
                        }}
                    >
                        {option.icon}
                        {option.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
