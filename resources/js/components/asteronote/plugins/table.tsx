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
            icon: <Rows2Icon className="mr-2 size-4" />,
        },
        {
            action: 'add-row-below',
            label: 'Add row below',
            icon: <Rows2Icon className="mr-2 size-4" />,
        },
        {
            action: 'add-column-left',
            label: 'Add column left',
            icon: <Columns2Icon className="mr-2 size-4" />,
        },
        {
            action: 'add-column-right',
            label: 'Add column right',
            icon: <Columns2Icon className="mr-2 size-4" />,
        },
        { action: 'delete-row', label: 'Delete row', icon: <MinusIcon className="mr-2 size-4" /> },
        {
            action: 'delete-column',
            label: 'Delete column',
            icon: <MinusIcon className="mr-2 size-4" />,
        },
        { action: 'delete-table', label: 'Delete table', icon: <EraserIcon className="mr-2 size-4" /> },
    ];

export function TablePluginControl({
    editor,
}: {
    editor: AsteroNoteController;
}) {
    const [open, setOpen] = React.useState(false);
    const [rows, setRows] = React.useState('3');
    const [columns, setColumns] = React.useState('3');

    const handleInsertTable = (e?: React.FormEvent) => {
        if (e) e.preventDefault();

        editor.insertTable({
            rows: Number.parseInt(rows, 10) || 1,
            columns: Number.parseInt(columns, 10) || 1,
        });
        setOpen(false);
    };

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
                    pressed={open || editor.formatState.inTable}
                    tooltip={editor.formatState.inTable ? "Table Options" : "Insert Table"}
                >
                    <Table2Icon />
                </ToolbarButton>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className="ignore-click-outside/toolbar min-w-[200px]"
                align="start"
                onCloseAutoFocus={(event) => event.preventDefault()}
            >
                {!editor.formatState.inTable && (
                    <>
                        <DropdownMenuLabel>Insert table</DropdownMenuLabel>
                        <form onSubmit={handleInsertTable} className="flex flex-col gap-3 p-2">
                            <div className="grid grid-cols-2 gap-3">
                                <Field>
                                    <FieldLabel htmlFor={`${editor.id}-table-rows`}>
                                        Rows
                                    </FieldLabel>
                                    <Input
                                        id={`${editor.id}-table-rows`}
                                        value={rows}
                                        onChange={(event) => setRows(event.target.value)}
                                        inputMode="numeric"
                                        min={1}
                                        max={20}
                                        type="number"
                                        className="h-8"
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor={`${editor.id}-table-columns`}>
                                        Columns
                                    </FieldLabel>
                                    <Input
                                        id={`${editor.id}-table-columns`}
                                        value={columns}
                                        onChange={(event) => setColumns(event.target.value)}
                                        inputMode="numeric"
                                        min={1}
                                        max={12}
                                        type="number"
                                        className="h-8"
                                    />
                                </Field>
                            </div>
                            <Button size="sm" type="submit" className="w-full">
                                Insert Table
                            </Button>
                        </form>
                    </>
                )}

                {editor.formatState.inTable && (
                    <>
                        <DropdownMenuLabel>Table actions</DropdownMenuLabel>
                        {rowActions.map((option) => (
                            <DropdownMenuItem
                                key={option.action}
                                onSelect={(event) => {
                                    event.preventDefault();
                                    editor.updateTable(option.action);
                                    setOpen(false);
                                }}
                                className={option.action === 'delete-table' ? 'text-destructive focus:bg-destructive/10 focus:text-destructive' : ''}
                            >
                                {option.icon}
                                {option.label}
                            </DropdownMenuItem>
                        ))}
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
