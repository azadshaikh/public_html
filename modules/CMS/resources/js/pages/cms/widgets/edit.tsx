import { Link, useHttp } from '@inertiajs/react';
import {
    ArrowDownIcon,
    ArrowLeftIcon,
    ArrowUpIcon,
    GripVerticalIcon,
    PencilIcon,
    PlusIcon,
    SaveIcon,
    Trash2Icon,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { DragEvent } from 'react';
import { showAppToast } from '@/components/forms/form-success-toast';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    AvailableWidget,
    WidgetEditPageProps,
    WidgetInstance,
    WidgetSettingField,
} from '../../../types/cms';

// ----------------------------------------------------------------
// Local types
// ----------------------------------------------------------------

type DraftWidget = WidgetInstance;

type SavePayload = {
    widgets: Record<string, DraftWidget[]>;
};

type SaveResponse = {
    success: boolean;
    message: string;
};

type WidgetLibraryGroup = Array<{ key: string; widget: AvailableWidget }>;

// ----------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------

function generateId(): string {
    return (
        'widget-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8)
    );
}

function buildDefaultSettings(
    schema: Record<string, WidgetSettingField>,
): Record<string, string | boolean | number> {
    const defaults: Record<string, string | boolean | number> = {};
    for (const [key, field] of Object.entries(schema)) {
        defaults[key] = field.default ?? '';
    }
    return defaults;
}

// ----------------------------------------------------------------
// Settings field renderer
// ----------------------------------------------------------------

function SettingField({
    fieldKey,
    field,
    value,
    onChange,
}: {
    fieldKey: string;
    field: WidgetSettingField;
    value: string | boolean | number;
    onChange: (val: string | boolean | number) => void;
}) {
    const id = `setting-${fieldKey}`;

    return (
        <Field>
            <FieldLabel htmlFor={id}>
                {field.label}
                {field.required && <span className="text-destructive"> *</span>}
            </FieldLabel>

            {field.type === 'textarea' ? (
                <Textarea
                    id={id}
                    rows={4}
                    value={String(value)}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={field.description}
                />
            ) : field.type === 'select' && field.options ? (
                <NativeSelect
                    id={id}
                    value={String(value)}
                    onChange={(e) => onChange(e.target.value)}
                >
                    {Object.entries(field.options).map(([optVal, optLabel]) => (
                        <NativeSelectOption key={optVal} value={optVal}>
                            {String(optLabel)}
                        </NativeSelectOption>
                    ))}
                </NativeSelect>
            ) : field.type === 'checkbox' ? (
                <div className="flex items-center gap-2">
                    <Switch
                        id={id}
                        checked={Boolean(value)}
                        onCheckedChange={(checked) => onChange(checked)}
                    />
                    {field.description && (
                        <FieldDescription className="text-xs">
                            {field.description}
                        </FieldDescription>
                    )}
                </div>
            ) : field.type === 'color' ? (
                <div className="flex items-center gap-2">
                    <input
                        id={id}
                        type="color"
                        className="size-9 cursor-pointer rounded border"
                        value={String(value)}
                        onChange={(e) => onChange(e.target.value)}
                    />
                    <Input
                        value={String(value)}
                        onChange={(e) => onChange(e.target.value)}
                        className="font-mono"
                        placeholder="#000000"
                    />
                </div>
            ) : (
                <Input
                    id={id}
                    type={
                        field.type === 'url'
                            ? 'url'
                            : field.type === 'email'
                              ? 'email'
                              : 'text'
                    }
                    value={String(value)}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={field.description}
                />
            )}

            {field.description && field.type !== 'checkbox' && (
                <FieldDescription>{field.description}</FieldDescription>
            )}
        </Field>
    );
}

// ----------------------------------------------------------------
// Widget item row
// ----------------------------------------------------------------

function WidgetRow({
    widget,
    availableWidgets,
    index,
    total,
    isDragging,
    isDropTarget,
    onEdit,
    onRemove,
    onMoveUp,
    onMoveDown,
    onDragStart,
    onDragOver,
    onDragEnd,
    onDrop,
}: {
    widget: DraftWidget;
    availableWidgets: Record<string, AvailableWidget>;
    index: number;
    total: number;
    isDragging: boolean;
    isDropTarget: boolean;
    onEdit: () => void;
    onRemove: () => void;
    onMoveUp: () => void;
    onMoveDown: () => void;
    onDragStart: (e: DragEvent<HTMLDivElement>) => void;
    onDragOver: (e: DragEvent<HTMLDivElement>) => void;
    onDragEnd: () => void;
    onDrop: (e: DragEvent<HTMLDivElement>) => void;
}) {
    const info = availableWidgets[widget.type];
    const typeName = info?.name ?? widget.type;
    const category = info?.category ?? '';

    return (
        <div
            draggable
            onDragStart={onDragStart}
            onDragOver={onDragOver}
            onDragEnd={onDragEnd}
            onDrop={onDrop}
            className={[
                'flex items-center gap-2 rounded-md border bg-card p-3 transition-opacity',
                isDragging ? 'opacity-40' : 'opacity-100',
                isDropTarget ? 'border-primary ring-1 ring-primary' : '',
            ]
                .filter(Boolean)
                .join(' ')}
        >
            <button
                type="button"
                className="cursor-grab text-muted-foreground/50 hover:text-muted-foreground active:cursor-grabbing"
                aria-label="Drag to reorder"
            >
                <GripVerticalIcon className="size-4" />
            </button>

            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium">
                    {widget.title || 'Untitled Widget'}
                </p>
                <p className="truncate text-xs text-muted-foreground">
                    {typeName}
                    {category && (
                        <span className="ml-1 text-muted-foreground/60">
                            · {category}
                        </span>
                    )}
                </p>
            </div>

            <div className="flex shrink-0 items-center gap-1">
                {/* Mobile move buttons */}
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-7 sm:hidden"
                    disabled={index === 0}
                    onClick={onMoveUp}
                    aria-label="Move up"
                >
                    <ArrowUpIcon className="size-3.5" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-7 sm:hidden"
                    disabled={index === total - 1}
                    onClick={onMoveDown}
                    aria-label="Move down"
                >
                    <ArrowDownIcon className="size-3.5" />
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    onClick={onEdit}
                    aria-label="Edit widget settings"
                >
                    <PencilIcon className="size-3.5" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-7 text-destructive hover:text-destructive"
                    onClick={onRemove}
                    aria-label="Remove widget"
                >
                    <Trash2Icon className="size-3.5" />
                </Button>
            </div>
        </div>
    );
}

// ----------------------------------------------------------------
// Main component
// ----------------------------------------------------------------

export default function WidgetsEdit({
    widgetArea,
    currentWidgets,
    availableWidgets,
}: WidgetEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Appearance', href: route('cms.appearance.themes.index') },
        { title: 'Widgets', href: route('cms.appearance.widgets.index') },
        {
            title: widgetArea.name,
            href: route('cms.appearance.widgets.edit', {
                area_id: widgetArea.id,
            }),
        },
    ];

    // ---- State ----
    const [items, setItems] = useState<DraftWidget[]>(() =>
        [...currentWidgets].sort((a, b) => a.position - b.position),
    );
    const [isDirty, setIsDirty] = useState(false);

    // Drag state
    const dragId = useRef<string | null>(null);
    const [dropTargetId, setDropTargetId] = useState<string | null>(null);

    // Settings sheet state
    const [editingWidget, setEditingWidget] = useState<DraftWidget | null>(
        null,
    );
    const [sheetOpen, setSheetOpen] = useState(false);
    const [editTitle, setEditTitle] = useState('');
    const [editSettings, setEditSettings] = useState<
        Record<string, string | boolean | number>
    >({});
    const saveRequest = useHttp<SavePayload, SaveResponse>({
        widgets: {
            [widgetArea.id]: [...currentWidgets].sort(
                (a, b) => a.position - b.position,
            ),
        },
    });

    // ---- Dirty guard ----
    useEffect(() => {
        const handler = (e: BeforeUnloadEvent) => {
            if (isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    }, [isDirty]);

    // ---- Grouped widgets for library ----
    const groupedWidgets = useMemo(() => {
        const groups: Record<string, WidgetLibraryGroup> = {};
        for (const [key, widget] of Object.entries(availableWidgets) as Array<
            [string, AvailableWidget]
        >) {
            const cat = widget.category || 'General';
            if (!groups[cat]) {
                groups[cat] = [];
            }
            groups[cat].push({ key, widget });
        }
        return groups;
    }, [availableWidgets]);

    // ---- Add widget from library ----
    const handleAddWidget = useCallback(
        (widgetKey: string) => {
            const info = availableWidgets[widgetKey];
            if (!info) return;

            const nextPos = items.length;
            const newWidget: DraftWidget = {
                id: generateId(),
                type: widgetKey,
                title: info.name,
                settings: buildDefaultSettings(info.settings_schema ?? {}),
                position: nextPos,
            };

            setItems((prev) => [...prev, newWidget]);
            setIsDirty(true);
        },
        [availableWidgets, items.length],
    );

    // ---- Remove widget ----
    const handleRemove = useCallback((id: string) => {
        setItems((prev) => prev.filter((w) => w.id !== id));
        setIsDirty(true);
    }, []);

    // ---- Move up/down (mobile) ----
    const handleMoveUp = useCallback((index: number) => {
        if (index === 0) return;
        setItems((prev) => {
            const next = [...prev];
            [next[index - 1], next[index]] = [next[index], next[index - 1]];
            return next.map((w, i) => ({ ...w, position: i }));
        });
        setIsDirty(true);
    }, []);

    const handleMoveDown = useCallback((index: number) => {
        setItems((prev) => {
            if (index >= prev.length - 1) return prev;
            const next = [...prev];
            [next[index], next[index + 1]] = [next[index + 1], next[index]];
            return next.map((w, i) => ({ ...w, position: i }));
        });
        setIsDirty(true);
    }, []);

    // ---- DnD ----
    const handleDragStart = useCallback(
        (e: DragEvent<HTMLDivElement>, id: string) => {
            dragId.current = id;
            e.dataTransfer.effectAllowed = 'move';
        },
        [],
    );

    const handleDragOver = useCallback(
        (e: DragEvent<HTMLDivElement>, id: string) => {
            e.preventDefault();
            if (dragId.current !== id) {
                setDropTargetId(id);
            }
        },
        [],
    );

    const handleDragEnd = useCallback(() => {
        dragId.current = null;
        setDropTargetId(null);
    }, []);

    const handleDrop = useCallback(
        (e: DragEvent<HTMLDivElement>, targetId: string) => {
            e.preventDefault();
            const sourceId = dragId.current;
            if (!sourceId || sourceId === targetId) {
                dragId.current = null;
                setDropTargetId(null);
                return;
            }

            setItems((prev) => {
                const next = [...prev];
                const fromIdx = next.findIndex((w) => w.id === sourceId);
                const toIdx = next.findIndex((w) => w.id === targetId);
                if (fromIdx === -1 || toIdx === -1) return prev;
                const [moved] = next.splice(fromIdx, 1);
                next.splice(toIdx, 0, moved);
                return next.map((w, i) => ({ ...w, position: i }));
            });
            dragId.current = null;
            setDropTargetId(null);
            setIsDirty(true);
        },
        [],
    );

    // ---- Edit settings ----
    const handleOpenEdit = useCallback((widget: DraftWidget) => {
        setEditingWidget(widget);
        setEditTitle(widget.title);
        setEditSettings({ ...widget.settings });
        setSheetOpen(true);
    }, []);

    const handleSaveSettings = useCallback(() => {
        if (!editingWidget) return;
        setItems((prev) =>
            prev.map((w) =>
                w.id === editingWidget.id
                    ? { ...w, title: editTitle, settings: editSettings }
                    : w,
            ),
        );
        setIsDirty(true);
        setSheetOpen(false);
        setEditingWidget(null);
    }, [editingWidget, editTitle, editSettings]);

    const handleSettingChange = useCallback(
        (key: string, value: string | boolean | number) => {
            setEditSettings((prev) => ({ ...prev, [key]: value }));
        },
        [],
    );

    // ---- Save ----
    const handleSave = useCallback(async () => {
        const payload: SavePayload = {
            widgets: {
                [widgetArea.id]: items.map((w, i) => ({ ...w, position: i })),
            },
        };

        try {
            saveRequest.transform(() => payload);

            const response = await saveRequest.post(
                route('cms.appearance.widgets.save-all'),
            );

            if (!response.success) {
                throw new Error(response.message || 'Save failed.');
            }

            setIsDirty(false);

            showAppToast({
                variant: 'success',
                title: 'Widgets saved',
                description: response.message,
            });
        } catch (error) {
            const message =
                error instanceof Error
                    ? error.message
                    : 'An unexpected error occurred.';
            showAppToast({
                variant: 'error',
                title: 'Save failed',
                description: message,
            });
        }
    }, [items, saveRequest, widgetArea.id]);

    const isSaving = saveRequest.processing;

    // ---- Keyboard shortcut Ctrl+S ----
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (
                (e.ctrlKey || e.metaKey) &&
                e.key === 's' &&
                isDirty &&
                !isSaving
            ) {
                e.preventDefault();
                handleSave();
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [handleSave, isDirty, isSaving]);

    // ---- Settings schema for editing widget ----
    const editingSchema = editingWidget
        ? (availableWidgets[editingWidget.type]?.settings_schema ?? {})
        : {};

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit Widget Area: ${widgetArea.name}`}
            description={
                widgetArea.description ||
                'Configure widget content for this theme area.'
            }
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('cms.appearance.widgets.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>
                    <Button
                        onClick={handleSave}
                        disabled={!isDirty || isSaving}
                    >
                        {isSaving ? (
                            <Spinner />
                        ) : (
                            <SaveIcon data-icon="inline-start" />
                        )}
                        Save
                    </Button>
                </div>
            }
        >
            <div className="grid gap-4 md:grid-cols-3">
                {/* ---- Widget list ---- */}
                <div className="md:col-span-2">
                    <Card>
                        <CardHeader className="pb-3">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-base">
                                    Widget Structure
                                    <span className="ml-2 text-sm font-normal text-muted-foreground">
                                        {items.length}{' '}
                                        {items.length === 1 ? 'item' : 'items'}
                                    </span>
                                </CardTitle>
                                {isDirty && (
                                    <span className="text-xs font-medium text-amber-600">
                                        Unsaved changes
                                    </span>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            {items.length === 0 ? (
                                <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
                                    <p className="text-sm font-medium">
                                        No widgets in this area
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Add widgets from the library panel on
                                        the right.
                                    </p>
                                </div>
                            ) : (
                                <div className="flex flex-col gap-2">
                                    {items.map((widget, index) => (
                                        <WidgetRow
                                            key={widget.id}
                                            widget={widget}
                                            availableWidgets={availableWidgets}
                                            index={index}
                                            total={items.length}
                                            isDragging={
                                                dragId.current === widget.id
                                            }
                                            isDropTarget={
                                                dropTargetId === widget.id
                                            }
                                            onEdit={() =>
                                                handleOpenEdit(widget)
                                            }
                                            onRemove={() =>
                                                handleRemove(widget.id)
                                            }
                                            onMoveUp={() => handleMoveUp(index)}
                                            onMoveDown={() =>
                                                handleMoveDown(index)
                                            }
                                            onDragStart={(e) =>
                                                handleDragStart(e, widget.id)
                                            }
                                            onDragOver={(e) =>
                                                handleDragOver(e, widget.id)
                                            }
                                            onDragEnd={handleDragEnd}
                                            onDrop={(e) =>
                                                handleDrop(e, widget.id)
                                            }
                                        />
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* ---- Widget library ---- */}
                <div>
                    <Card className="sticky top-4">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">
                                Widget Library
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="pt-0">
                            {Object.keys(availableWidgets).length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No widgets available. Check your theme's
                                    widgets folder.
                                </p>
                            ) : (
                                <Accordion
                                    type="multiple"
                                    defaultValue={Object.keys(
                                        groupedWidgets,
                                    ).slice(0, 1)}
                                >
                                    {Object.entries(groupedWidgets).map(
                                        ([category, widgets]) => (
                                            <AccordionItem
                                                key={category}
                                                value={category}
                                            >
                                                <AccordionTrigger className="py-2 text-sm capitalize">
                                                    {category}
                                                    <span className="mr-2 ml-auto text-xs text-muted-foreground">
                                                        {widgets.length}
                                                    </span>
                                                </AccordionTrigger>
                                                <AccordionContent>
                                                    <div className="flex flex-col gap-1 pb-1">
                                                        {widgets.map(
                                                            ({
                                                                key,
                                                                widget: w,
                                                            }) => (
                                                                <button
                                                                    key={key}
                                                                    type="button"
                                                                    onClick={() =>
                                                                        handleAddWidget(
                                                                            key,
                                                                        )
                                                                    }
                                                                    className="flex items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                                                >
                                                                    <PlusIcon className="size-3.5 shrink-0 text-primary" />
                                                                    <span className="min-w-0 flex-1 truncate">
                                                                        {w.name}
                                                                    </span>
                                                                </button>
                                                            ),
                                                        )}
                                                    </div>
                                                </AccordionContent>
                                            </AccordionItem>
                                        ),
                                    )}
                                </Accordion>
                            )}

                            <p className="mt-3 text-xs text-muted-foreground">
                                Click a widget to add it. Drag items in the list
                                to reorder. Use{' '}
                                <kbd className="rounded border px-1 font-mono text-xs">
                                    Ctrl
                                </kbd>
                                +
                                <kbd className="rounded border px-1 font-mono text-xs">
                                    S
                                </kbd>{' '}
                                to save.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* ---- Sticky save bar ---- */}
            {isDirty && (
                <div className="fixed inset-x-0 bottom-0 z-50 border-t bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                    <div className="mx-auto flex max-w-screen-xl items-center justify-between gap-4 px-4 py-3">
                        <p className="text-sm font-medium text-amber-600">
                            You have unsaved widget changes.
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={isSaving}
                                onClick={() => {
                                    setItems(
                                        [...currentWidgets].sort(
                                            (a, b) => a.position - b.position,
                                        ),
                                    );
                                    setIsDirty(false);
                                }}
                            >
                                Discard
                            </Button>
                            <Button
                                size="sm"
                                disabled={isSaving}
                                onClick={handleSave}
                            >
                                {isSaving ? (
                                    <Spinner />
                                ) : (
                                    <SaveIcon data-icon="inline-start" />
                                )}
                                Save Widgets
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* ---- Settings sheet ---- */}
            <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
                <SheetContent
                    side="right"
                    className="flex w-full flex-col sm:max-w-md"
                >
                    <SheetHeader>
                        <SheetTitle>
                            {editingWidget
                                ? (availableWidgets[editingWidget.type]?.name ??
                                  editingWidget.type)
                                : 'Widget Settings'}
                        </SheetTitle>
                        <SheetDescription>
                            Configure the settings for this widget instance.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-5 overflow-y-auto py-4">
                        {/* Always-present title field */}
                        <Field>
                            <FieldLabel htmlFor="widget-title">
                                Widget Title{' '}
                                <span className="text-destructive">*</span>
                            </FieldLabel>
                            <Input
                                id="widget-title"
                                value={editTitle}
                                onChange={(e) => setEditTitle(e.target.value)}
                                placeholder="Widget display title"
                            />
                        </Field>

                        {/* Dynamic settings fields */}
                        {(
                            Object.entries(editingSchema) as Array<
                                [string, WidgetSettingField]
                            >
                        ).map(([key, field]) => (
                            <SettingField
                                key={key}
                                fieldKey={key}
                                field={field}
                                value={editSettings[key] ?? field.default ?? ''}
                                onChange={(val) =>
                                    handleSettingChange(key, val)
                                }
                            />
                        ))}
                    </div>

                    <SheetFooter>
                        <Button
                            variant="outline"
                            onClick={() => setSheetOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button onClick={handleSaveSettings}>
                            <SaveIcon data-icon="inline-start" />
                            Apply
                        </Button>
                    </SheetFooter>
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
