'use client';

import {
    Columns2Icon,
    EraserIcon,
    MinusIcon,
    Rows2Icon,
    Table2Icon,
} from 'lucide-react';
import * as React from 'react';

import type {
    AsteroNoteController,
    AsteroNoteTableAction,
} from '@/components/asteronote/asteronote-types';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { ToolbarButton } from '@/components/ui/toolbar';

const rowActions: Array<{
    action: AsteroNoteTableAction;
    label: string;
    icon: React.ReactNode;
}> = [
    {
        action: 'add-row-above',
        label: 'Add row above',
        icon: <Rows2Icon />,
    },
    {
        action: 'add-row-below',
        label: 'Add row below',
        icon: <Rows2Icon />,
    },
    {
        action: 'add-column-left',
        label: 'Add column left',
        icon: <Columns2Icon />,
    },
    {
        action: 'add-column-right',
        label: 'Add column right',
        icon: <Columns2Icon />,
    },
    { action: 'delete-row', label: 'Delete row', icon: <MinusIcon /> },
    {
        action: 'delete-column',
        label: 'Delete column',
        icon: <MinusIcon />,
    },
    { action: 'delete-table', label: 'Delete table', icon: <EraserIcon /> },
];

export function TablePluginControl({
    editor,
}: {
    editor: AsteroNoteController;
}) {
    const [open, setOpen] = React.useState(false);
    const [rows, setRows] = React.useState('3');
    const [columns, setColumns] = React.useState('3');

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
                    tooltip="Table"
                >
                    <Table2Icon />
                </ToolbarButton>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className="ignore-click-outside/toolbar min-w-[16rem]"
                align="start"
                onCloseAutoFocus={(event) => event.preventDefault()}
            >
                <DropdownMenuLabel>Insert table</DropdownMenuLabel>
                <div className="flex flex-col gap-3 p-2">
                    <div className="grid grid-cols-2 gap-3">
                        <Field>
                            <FieldLabel htmlFor={`${editor.id}-table-rows`}>
                                Rows
                            </FieldLabel>
                            <Input
                                id={`${editor.id}-table-rows`}
                                value={rows}
                                onChange={(event) =>
                                    setRows(event.target.value)
                                }
                                inputMode="numeric"
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor={`${editor.id}-table-columns`}>
                                Columns
                            </FieldLabel>
                            <Input
                                id={`${editor.id}-table-columns`}
                                value={columns}
                                onChange={(event) =>
                                    setColumns(event.target.value)
                                }
                                inputMode="numeric"
                            />
                        </Field>
                    </div>
                    <Button
                        size="sm"
                        onClick={() => {
                            editor.insertTable({
                                rows: Number.parseInt(rows, 10) || 1,
                                columns: Number.parseInt(columns, 10) || 1,
                            });
                            setOpen(false);
                        }}
                    >
                        Insert table
                    </Button>
                </div>
                <DropdownMenuSeparator />
                <DropdownMenuLabel>Table actions</DropdownMenuLabel>
                {rowActions.map((option) => (
                    <DropdownMenuItem
                        key={option.action}
                        disabled={!editor.formatState.inTable}
                        onSelect={(event) => {
                            event.preventDefault();
                            editor.updateTable(option.action);
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
