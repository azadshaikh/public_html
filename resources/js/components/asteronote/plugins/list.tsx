'use client';

import { CheckIcon, ListIcon, ListOrderedIcon } from 'lucide-react';
import * as React from 'react';

import type { AsteroNoteController } from '@/components/asteronote/asteronote-types';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ToolbarButton } from '@/components/ui/toolbar';

const groupedOptions = [
    {
        label: 'Bulleted list',
        items: [
            {
                icon: <ListIcon className="mr-2 size-4" />,
                label: 'Bullet (•)',
                style: 'disc',
                type: 'ul' as const,
            },
            {
                icon: <ListIcon className="mr-2 size-4" />,
                label: 'Circle (○)',
                style: 'circle',
                type: 'ul' as const,
            },
            {
                icon: <ListIcon className="mr-2 size-4" />,
                label: 'Square (■)',
                style: 'square',
                type: 'ul' as const,
            },
        ],
    },
    {
        label: 'Numbered list',
        items: [
            {
                icon: <ListOrderedIcon className="mr-2 size-4" />,
                label: 'Decimal (1, 2, 3)',
                style: 'decimal',
                type: 'ol' as const,
            },
            {
                icon: <ListOrderedIcon className="mr-2 size-4" />,
                label: 'Lower alpha (a, b, c)',
                style: 'lower-alpha',
                type: 'ol' as const,
            },
            {
                icon: <ListOrderedIcon className="mr-2 size-4" />,
                label: 'Upper alpha (A, B, C)',
                style: 'upper-alpha',
                type: 'ol' as const,
            },
            {
                icon: <ListOrderedIcon className="mr-2 size-4" />,
                label: 'Lower roman (i, ii, iii)',
                style: 'lower-roman',
                type: 'ol' as const,
            },
            {
                icon: <ListOrderedIcon className="mr-2 size-4" />,
                label: 'Upper roman (I, II, III)',
                style: 'upper-roman',
                type: 'ol' as const,
            },
        ],
    },
];

export function ListPluginControl({
    editor,
}: {
    editor: AsteroNoteController;
}) {
    const [open, setOpen] = React.useState(false);
    const currentType = editor.formatState.listType ?? 'ul';
    const currentStyle = editor.formatState.listStyleType;
    const label = currentType === 'ol' ? 'Numbered list' : 'Bulleted list';
    const icon = currentType === 'ol' ? <ListOrderedIcon /> : <ListIcon />;

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
                    pressed={open || editor.formatState.listType !== null}
                    tooltip={label}
                >
                    {icon}
                </ToolbarButton>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className="ignore-click-outside/toolbar min-w-[200px]"
                align="start"
                onCloseAutoFocus={(event) => event.preventDefault()}
            >
                {groupedOptions.map((group, groupIdx) => (
                    <React.Fragment key={group.label}>
                        <DropdownMenuLabel>{group.label}</DropdownMenuLabel>
                        {group.items.map((item) => {
                            const active =
                                editor.formatState.listType === item.type &&
                                (currentStyle === item.style ||
                                    (currentStyle === null &&
                                        item.style === 'disc') ||
                                    (currentStyle === null &&
                                        item.style === 'decimal'));

                            return (
                                <DropdownMenuItem
                                    key={`${item.type}-${item.style}`}
                                    onSelect={(event) => {
                                        event.preventDefault();
                                        editor.applyList(item.type, item.style);
                                        setOpen(false);
                                    }}
                                >
                                    {item.icon}
                                    {item.label}
                                    {active ? (
                                        <CheckIcon className="ml-auto size-4" />
                                    ) : null}
                                </DropdownMenuItem>
                            );
                        })}
                        {groupIdx < groupedOptions.length - 1 && <DropdownMenuSeparator />}
                    </React.Fragment>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
