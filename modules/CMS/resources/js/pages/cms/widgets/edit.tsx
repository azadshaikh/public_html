import { Link, useHttp } from '@inertiajs/react';
import {
    ArrowDownIcon,
    ArrowLeftIcon,
    ArrowUpIcon,
    GripVerticalIcon,
    LayoutGridIcon,
    PencilIcon,
    PlusIcon,
    SaveIcon,
    Trash2Icon,
} from 'lucide-react';
import {
    Fragment,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import type { DragEvent } from 'react';
import { showAppToast } from '@/components/forms/form-success-toast';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Kbd, KbdGroup } from '@/components/ui/kbd';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { ScrollArea } from '@/components/ui/scroll-area';
import { SearchInput } from '@/components/ui/search-input';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { CmsSaveFooter } from '../../../components/shared/cms-save-footer';
import type {
    AvailableWidget,
    WidgetEditPageProps,
    WidgetInstance,
    WidgetSettingField,
} from '../../../types/cms';

type DraftWidget = WidgetInstance;

type SavePayload = {
    widgets: Record<string, DraftWidget[]>;
};

type SaveResponse = {
    success: boolean;
    message: string;
};

type WidgetLibraryEntry = {
    key: string;
    widget: AvailableWidget;
};

type WidgetLibrarySection = {
    category: string;
    widgets: WidgetLibraryEntry[];
};

function generateId(): string {
    return (
        'widget-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8)
    );
}

function sortWidgetsByPosition(widgets: WidgetInstance[]): WidgetInstance[] {
    return [...widgets].sort((a, b) => a.position - b.position);
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

function formatLabel(value: string): string {
    return value
        .replace(/[-_]+/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function reorderWidgets(
    widgets: DraftWidget[],
    sourceId: string,
    targetIndex: number,
): DraftWidget[] {
    const sourceIndex = widgets.findIndex((widget) => widget.id === sourceId);

    if (sourceIndex === -1) {
        return widgets;
    }

    const insertionIndex =
        sourceIndex < targetIndex ? targetIndex - 1 : targetIndex;

    if (insertionIndex === sourceIndex) {
        return widgets;
    }

    const nextWidgets = [...widgets];
    const [movedWidget] = nextWidgets.splice(sourceIndex, 1);

    nextWidgets.splice(insertionIndex, 0, movedWidget);

    return nextWidgets.map((widget, index) => ({
        ...widget,
        position: index,
    }));
}

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
                    onChange={(event) => onChange(event.target.value)}
                    placeholder={field.description}
                />
            ) : field.type === 'select' && field.options ? (
                <NativeSelect
                    id={id}
                    value={String(value)}
                    onChange={(event) => onChange(event.target.value)}
                >
                    {Object.entries(field.options).map(
                        ([optionValue, optionLabel]) => (
                            <NativeSelectOption
                                key={optionValue}
                                value={optionValue}
                            >
                                {String(optionLabel)}
                            </NativeSelectOption>
                        ),
                    )}
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
                        className="size-9 cursor-pointer rounded-md border border-input bg-background"
                        value={String(value)}
                        onChange={(event) => onChange(event.target.value)}
                    />
                    <Input
                        value={String(value)}
                        onChange={(event) => onChange(event.target.value)}
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
                              : field.type === 'number'
                                ? 'number'
                                : 'text'
                    }
                    value={String(value)}
                    onChange={(event) =>
                        onChange(
                            field.type === 'number'
                                ? event.target.value === ''
                                    ? ''
                                    : Number(event.target.value)
                                : event.target.value,
                        )
                    }
                    placeholder={field.description}
                />
            )}

            {field.description && field.type !== 'checkbox' && (
                <FieldDescription>{field.description}</FieldDescription>
            )}
        </Field>
    );
}

function WidgetDropZone({
    active,
    visible,
    onDragOver,
    onDrop,
}: {
    active: boolean;
    visible: boolean;
    onDragOver: (event: DragEvent<HTMLDivElement>) => void;
    onDrop: (event: DragEvent<HTMLDivElement>) => void;
}) {
    return (
        <div
            onDragOver={onDragOver}
            onDrop={onDrop}
            className={cn(
                'px-1 transition-all',
                visible || active ? 'py-1.5' : 'py-2',
            )}
        >
            <div
                className={cn(
                    'flex items-center justify-center rounded-xl border border-dashed transition-all',
                    visible || active
                        ? 'h-8 opacity-100'
                        : 'h-0 border-transparent opacity-0',
                    active
                        ? 'border-primary bg-primary/8 text-primary'
                        : 'border-border/60 bg-muted/25 text-transparent',
                )}
            >
                <span className="text-[11px] font-medium">
                    {active ? 'Drop widget here' : 'Drop widget here'}
                </span>
            </div>
        </div>
    );
}

function WidgetRow({
    widget,
    availableWidgets,
    index,
    total,
    isDragging,
    onEdit,
    onRemove,
    onMoveUp,
    onMoveDown,
    onDragStart,
    onDragEnd,
}: {
    widget: DraftWidget;
    availableWidgets: Record<string, AvailableWidget>;
    index: number;
    total: number;
    isDragging: boolean;
    onEdit: () => void;
    onRemove: () => void;
    onMoveUp: () => void;
    onMoveDown: () => void;
    onDragStart: (event: DragEvent<HTMLButtonElement>) => void;
    onDragEnd: () => void;
}) {
    const info = availableWidgets[widget.type];
    const typeName = info?.name ?? widget.type;

    return (
        <div
            className={cn(
                'group rounded-xl border bg-card p-4 shadow-xs transition-all',
                isDragging ? 'scale-[0.99] opacity-45' : 'opacity-100',
            )}
        >
            <div className="flex items-start gap-3">
                <div className="flex items-center gap-2 self-stretch">
                    <button
                        type="button"
                        draggable
                        onDragStart={onDragStart}
                        onDragEnd={onDragEnd}
                        className="mt-0.5 rounded-lg border bg-muted/40 p-2 text-muted-foreground transition hover:bg-accent hover:text-foreground active:cursor-grabbing"
                        aria-label="Drag to reorder"
                        title="Drag to reorder"
                    >
                        <GripVerticalIcon className="size-4" />
                    </button>
                </div>

                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="truncate text-sm font-medium">
                            {widget.title || 'Untitled Widget'}
                        </p>
                        <Badge variant="outline">{typeName}</Badge>
                        {info?.category ? (
                            <Badge variant="secondary">
                                {formatLabel(info.category)}
                            </Badge>
                        ) : null}
                    </div>

                    <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                        {info?.description ||
                            'Add content to this widget area and tune the settings as needed.'}
                    </p>
                </div>

                <div className="flex shrink-0 items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="sm:hidden"
                        disabled={index === 0}
                        onClick={onMoveUp}
                        aria-label="Move up"
                    >
                        <ArrowUpIcon />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="sm:hidden"
                        disabled={index === total - 1}
                        onClick={onMoveDown}
                        aria-label="Move down"
                    >
                        <ArrowDownIcon />
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={onEdit}
                        aria-label="Edit widget settings"
                    >
                        <PencilIcon />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="text-destructive hover:text-destructive"
                        onClick={onRemove}
                        aria-label="Remove widget"
                    >
                        <Trash2Icon />
                    </Button>
                </div>
            </div>
        </div>
    );
}

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

    const initialWidgets = useMemo(
        () => sortWidgetsByPosition(currentWidgets),
        [currentWidgets],
    );

    const [items, setItems] = useState<DraftWidget[]>(initialWidgets);
    const [isDirty, setIsDirty] = useState(false);
    const [libraryQuery, setLibraryQuery] = useState('');

    const draggedWidgetId = useRef<string | null>(null);
    const [activeDragId, setActiveDragId] = useState<string | null>(null);
    const [dropIndex, setDropIndex] = useState<number | null>(null);

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
            [widgetArea.id]: initialWidgets,
        },
    });

    useEffect(() => {
        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            if (!isDirty) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () =>
            window.removeEventListener('beforeunload', handleBeforeUnload);
    }, [isDirty]);

    const widgetUsageCount = useMemo(() => {
        return items.reduce<Record<string, number>>((counts, widget) => {
            counts[widget.type] = (counts[widget.type] ?? 0) + 1;

            return counts;
        }, {});
    }, [items]);

    const groupedWidgets = useMemo<WidgetLibrarySection[]>(() => {
        const groups: Record<string, WidgetLibraryEntry[]> = {};
        const query = libraryQuery.trim().toLowerCase();

        for (const [key, widget] of Object.entries(availableWidgets) as Array<
            [string, AvailableWidget]
        >) {
            const matchesQuery =
                query === '' ||
                widget.name.toLowerCase().includes(query) ||
                widget.description.toLowerCase().includes(query) ||
                widget.category.toLowerCase().includes(query) ||
                key.toLowerCase().includes(query);

            if (!matchesQuery) {
                continue;
            }

            const category = widget.category || 'General';

            if (!groups[category]) {
                groups[category] = [];
            }

            groups[category].push({ key, widget });
        }

        return Object.entries(groups)
            .sort(([left], [right]) => left.localeCompare(right))
            .map(([category, widgets]) => ({
                category,
                widgets: widgets.sort((left, right) =>
                    left.widget.name.localeCompare(right.widget.name),
                ),
            }));
    }, [availableWidgets, libraryQuery]);

    const availableWidgetCount = Object.keys(availableWidgets).length;
    const filteredWidgetCount = groupedWidgets.reduce(
        (count, group) => count + group.widgets.length,
        0,
    );

    const handleOpenEdit = useCallback((widget: DraftWidget) => {
        setEditingWidget(widget);
        setEditTitle(widget.title);
        setEditSettings({ ...widget.settings });
        setSheetOpen(true);
    }, []);

    const handleSheetOpenChange = useCallback((open: boolean) => {
        setSheetOpen(open);

        if (!open) {
            setEditingWidget(null);
        }
    }, []);

    const handleAddWidget = useCallback(
        (widgetKey: string) => {
            const info = availableWidgets[widgetKey];

            if (!info) {
                return;
            }

            const newWidget: DraftWidget = {
                id: generateId(),
                type: widgetKey,
                title: info.name,
                settings: buildDefaultSettings(info.settings_schema ?? {}),
                position: items.length,
            };

            setItems((previousItems) => [...previousItems, newWidget]);
            setIsDirty(true);
        },
        [availableWidgets, items.length],
    );

    const handleRemove = useCallback(
        (id: string) => {
            setItems((previousItems) =>
                previousItems.filter((widget) => widget.id !== id),
            );

            if (editingWidget?.id === id) {
                setSheetOpen(false);
                setEditingWidget(null);
            }

            setIsDirty(true);
        },
        [editingWidget],
    );

    const handleMoveUp = useCallback((index: number) => {
        if (index === 0) {
            return;
        }

        setItems((previousItems) => {
            const nextItems = [...previousItems];
            [nextItems[index - 1], nextItems[index]] = [
                nextItems[index],
                nextItems[index - 1],
            ];

            return nextItems.map((widget, nextIndex) => ({
                ...widget,
                position: nextIndex,
            }));
        });

        setIsDirty(true);
    }, []);

    const handleMoveDown = useCallback((index: number) => {
        setItems((previousItems) => {
            if (index >= previousItems.length - 1) {
                return previousItems;
            }

            const nextItems = [...previousItems];
            [nextItems[index], nextItems[index + 1]] = [
                nextItems[index + 1],
                nextItems[index],
            ];

            return nextItems.map((widget, nextIndex) => ({
                ...widget,
                position: nextIndex,
            }));
        });

        setIsDirty(true);
    }, []);

    const handleDragStart = useCallback(
        (
            event: DragEvent<HTMLButtonElement>,
            widgetId: string,
            index: number,
        ) => {
            draggedWidgetId.current = widgetId;
            setActiveDragId(widgetId);
            setDropIndex(index);
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', widgetId);
        },
        [],
    );

    const handleDragOverSlot = useCallback(
        (event: DragEvent<HTMLDivElement>, targetIndex: number) => {
            event.preventDefault();

            if (!draggedWidgetId.current) {
                return;
            }

            event.dataTransfer.dropEffect = 'move';
            setDropIndex(targetIndex);
        },
        [],
    );

    const resetDragState = useCallback(() => {
        draggedWidgetId.current = null;
        setActiveDragId(null);
        setDropIndex(null);
    }, []);

    const handleDrop = useCallback(
        (event: DragEvent<HTMLDivElement>, targetIndex: number) => {
            event.preventDefault();

            const sourceId = draggedWidgetId.current;

            if (!sourceId) {
                resetDragState();

                return;
            }

            let didMove = false;

            setItems((previousItems) => {
                const reorderedItems = reorderWidgets(
                    previousItems,
                    sourceId,
                    targetIndex,
                );

                didMove = reorderedItems !== previousItems;

                return reorderedItems;
            });

            if (didMove) {
                setIsDirty(true);
            }

            resetDragState();
        },
        [resetDragState],
    );

    const handleSaveSettings = useCallback(() => {
        if (!editingWidget) {
            return;
        }

        setItems((previousItems) =>
            previousItems.map((widget) =>
                widget.id === editingWidget.id
                    ? { ...widget, title: editTitle, settings: editSettings }
                    : widget,
            ),
        );
        setIsDirty(true);
        setSheetOpen(false);
        setEditingWidget(null);
    }, [editSettings, editTitle, editingWidget]);

    const handleSettingChange = useCallback(
        (key: string, value: string | boolean | number) => {
            setEditSettings((previousSettings) => ({
                ...previousSettings,
                [key]: value,
            }));
        },
        [],
    );

    const handleDiscardChanges = useCallback(() => {
        setItems(initialWidgets);
        setIsDirty(false);
        setLibraryQuery('');
        resetDragState();
        setSheetOpen(false);
        setEditingWidget(null);
    }, [initialWidgets, resetDragState]);

    const handleSave = useCallback(async () => {
        const payload: SavePayload = {
            widgets: {
                [widgetArea.id]: items.map((widget, index) => ({
                    ...widget,
                    position: index,
                })),
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

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (
                (event.ctrlKey || event.metaKey) &&
                event.key === 's' &&
                isDirty &&
                !isSaving
            ) {
                event.preventDefault();
                handleSave();
            }
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [handleSave, isDirty, isSaving]);

    const editingSchema = editingWidget
        ? (availableWidgets[editingWidget.type]?.settings_schema ?? {})
        : {};

    const defaultOpenLibrarySections = groupedWidgets
        .slice(0, 2)
        .map(({ category }) => category);
    const showUnsavedChangesStatus = isDirty && !isSaving;
    const footerStatusText = isSaving
        ? 'Saving changes...'
        : showUnsavedChangesStatus
          ? 'You have unsaved widget changes.'
          : 'All changes saved.';

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit Widget Area: ${widgetArea.name}`}
            description={
                widgetArea.description ||
                'Configure widget content for this theme area.'
            }
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('cms.appearance.widgets.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <div className="flex flex-1 flex-col gap-6 pb-20">
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <Card className="flex min-h-[540px] flex-col">
                        <CardHeader className="pb-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <CardTitle className="text-base">
                                    Widget Structure
                                </CardTitle>
                                <Badge variant="outline">
                                    {items.length}{' '}
                                    {items.length === 1 ? 'widget' : 'widgets'}
                                </Badge>
                                {isDirty ? (
                                    <Badge variant="warning">
                                        Unsaved changes
                                    </Badge>
                                ) : null}
                            </div>
                            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                <span>
                                    Drag by the grip and drop between rows to
                                    reorder.
                                </span>
                                <KbdGroup>
                                    <Kbd>Ctrl</Kbd>
                                    <span>+</span>
                                    <Kbd>S</Kbd>
                                </KbdGroup>
                            </div>
                        </CardHeader>

                        <CardContent className="flex flex-1 flex-col">
                            {items.length === 0 ? (
                                <Empty className="flex-1 rounded-2xl border border-dashed bg-muted/10 py-16">
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <LayoutGridIcon />
                                        </EmptyMedia>
                                        <EmptyTitle>
                                            No widgets in this area
                                        </EmptyTitle>
                                        <EmptyDescription>
                                            Start with the library on the right
                                            to add content blocks, lists, forms,
                                            and marketing widgets.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            ) : (
                                <div className="flex flex-1 flex-col">
                                    <WidgetDropZone
                                        active={dropIndex === 0}
                                        visible={activeDragId !== null}
                                        onDragOver={(event) =>
                                            handleDragOverSlot(event, 0)
                                        }
                                        onDrop={(event) => handleDrop(event, 0)}
                                    />

                                    <div className="flex flex-col">
                                        {items.map((widget, index) => (
                                            <Fragment key={widget.id}>
                                                <WidgetRow
                                                    widget={widget}
                                                    availableWidgets={
                                                        availableWidgets
                                                    }
                                                    index={index}
                                                    total={items.length}
                                                    isDragging={
                                                        activeDragId ===
                                                        widget.id
                                                    }
                                                    onEdit={() =>
                                                        handleOpenEdit(widget)
                                                    }
                                                    onRemove={() =>
                                                        handleRemove(widget.id)
                                                    }
                                                    onMoveUp={() =>
                                                        handleMoveUp(index)
                                                    }
                                                    onMoveDown={() =>
                                                        handleMoveDown(index)
                                                    }
                                                    onDragStart={(event) =>
                                                        handleDragStart(
                                                            event,
                                                            widget.id,
                                                            index,
                                                        )
                                                    }
                                                    onDragEnd={resetDragState}
                                                />

                                                <WidgetDropZone
                                                    active={
                                                        dropIndex === index + 1
                                                    }
                                                    visible={
                                                        activeDragId !== null
                                                    }
                                                    onDragOver={(event) =>
                                                        handleDragOverSlot(
                                                            event,
                                                            index + 1,
                                                        )
                                                    }
                                                    onDrop={(event) =>
                                                        handleDrop(
                                                            event,
                                                            index + 1,
                                                        )
                                                    }
                                                />
                                            </Fragment>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="sticky top-4 flex max-h-[calc(100vh-7rem)] min-h-0 flex-col overflow-hidden">
                        <CardHeader className="pb-0">
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-base">
                                    Widget Library
                                </CardTitle>
                                <Badge variant="secondary">
                                    {filteredWidgetCount}
                                </Badge>
                            </div>
                        </CardHeader>

                        <CardContent className="flex min-h-0 flex-1 flex-col gap-4 p-4 pt-3">
                            <SearchInput
                                value={libraryQuery}
                                onChange={setLibraryQuery}
                                size="comfortable"
                                placeholder="Search by widget name, type, or category"
                                containerClassName="w-full"
                            />

                            <ScrollArea className="min-h-0 flex-1">
                                <div className="flex flex-col gap-4 pr-1">
                                    {availableWidgetCount === 0 ? (
                                        <Empty className="rounded-2xl border border-dashed bg-muted/10 py-12">
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    <PlusIcon />
                                                </EmptyMedia>
                                                <EmptyTitle>
                                                    No widgets available
                                                </EmptyTitle>
                                                <EmptyDescription>
                                                    The active theme did not
                                                    expose any widget manifests
                                                    yet.
                                                </EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    ) : groupedWidgets.length === 0 ? (
                                        <Empty className="rounded-2xl border border-dashed bg-muted/10 py-12">
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    <LayoutGridIcon />
                                                </EmptyMedia>
                                                <EmptyTitle>
                                                    No matches found
                                                </EmptyTitle>
                                                <EmptyDescription>
                                                    Try a broader search or
                                                    clear the current filter to
                                                    see all widget types again.
                                                </EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    ) : (
                                        <Accordion
                                            type="multiple"
                                            defaultValue={
                                                defaultOpenLibrarySections
                                            }
                                            className="flex flex-col gap-3"
                                        >
                                            {groupedWidgets.map(
                                                ({ category, widgets }) => (
                                                    <AccordionItem
                                                        key={category}
                                                        value={category}
                                                        className="overflow-hidden rounded-xl border px-0"
                                                    >
                                                        <AccordionTrigger className="px-4 py-3 text-left text-sm font-medium hover:bg-muted/20 hover:no-underline">
                                                            <div className="flex min-w-0 flex-1 items-center gap-3">
                                                                <div className="min-w-0 flex-1">
                                                                    <p className="truncate">
                                                                        {formatLabel(
                                                                            category,
                                                                        )}
                                                                    </p>
                                                                    <p className="text-xs font-normal text-muted-foreground">
                                                                        {
                                                                            widgets.length
                                                                        }{' '}
                                                                        {widgets.length ===
                                                                        1
                                                                            ? 'widget'
                                                                            : 'widgets'}
                                                                    </p>
                                                                </div>
                                                                <Badge variant="outline">
                                                                    {
                                                                        widgets.length
                                                                    }
                                                                </Badge>
                                                            </div>
                                                        </AccordionTrigger>
                                                        <AccordionContent className="px-4 pb-4">
                                                            <div className="flex flex-col gap-3">
                                                                {widgets.map(
                                                                    ({
                                                                        key,
                                                                        widget,
                                                                    }) => {
                                                                        const settingsCount =
                                                                            Object.keys(
                                                                                widget.settings_schema ??
                                                                                    {},
                                                                            ).length;
                                                                        const usageCount =
                                                                            widgetUsageCount[
                                                                                key
                                                                            ] ??
                                                                            0;

                                                                        return (
                                                                            <button
                                                                                key={
                                                                                    key
                                                                                }
                                                                                type="button"
                                                                                onClick={() =>
                                                                                    handleAddWidget(
                                                                                        key,
                                                                                    )
                                                                                }
                                                                                className="group w-full rounded-xl border bg-background p-4 text-left transition hover:border-primary/40 hover:bg-accent/30"
                                                                            >
                                                                                <div className="flex items-start gap-3">
                                                                                    <div className="flex-1 space-y-1">
                                                                                        <div className="flex items-center gap-2">
                                                                                            <p className="font-medium">
                                                                                                {
                                                                                                    widget.name
                                                                                                }
                                                                                            </p>
                                                                                            {usageCount >
                                                                                            0 ? (
                                                                                                <Badge variant="secondary">
                                                                                                    Used{' '}
                                                                                                    {
                                                                                                        usageCount
                                                                                                    }

                                                                                                    x
                                                                                                </Badge>
                                                                                            ) : null}
                                                                                        </div>
                                                                                        <p className="line-clamp-2 text-sm text-muted-foreground">
                                                                                            {
                                                                                                widget.description
                                                                                            }
                                                                                        </p>
                                                                                    </div>

                                                                                    <div className="rounded-lg border bg-muted/40 p-2 text-primary transition group-hover:border-primary/40 group-hover:bg-primary group-hover:text-primary-foreground">
                                                                                        <PlusIcon className="size-4" />
                                                                                    </div>
                                                                                </div>

                                                                                <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                                                    <Badge variant="outline">
                                                                                        {
                                                                                            settingsCount
                                                                                        }{' '}
                                                                                        {settingsCount ===
                                                                                        1
                                                                                            ? 'setting'
                                                                                            : 'settings'}
                                                                                    </Badge>
                                                                                </div>
                                                                            </button>
                                                                        );
                                                                    },
                                                                )}
                                                            </div>
                                                        </AccordionContent>
                                                    </AccordionItem>
                                                ),
                                            )}
                                        </Accordion>
                                    )}
                                </div>
                            </ScrollArea>
                        </CardContent>

                        <Separator />

                        <div className="p-4 text-sm text-muted-foreground">
                            <div className="flex flex-wrap items-center gap-2">
                                <span>Save anytime with</span>
                                <KbdGroup>
                                    <Kbd>Ctrl</Kbd>
                                    <span>+</span>
                                    <Kbd>S</Kbd>
                                </KbdGroup>
                            </div>
                        </div>
                    </Card>
                </div>

                <CmsSaveFooter
                    statusText={footerStatusText}
                    showStatusIcon={showUnsavedChangesStatus}
                    isProcessing={isSaving}
                    secondaryAction={{
                        label: 'Discard Changes',
                        onClick: handleDiscardChanges,
                        disabled: !isDirty || isSaving,
                    }}
                    primaryAction={{
                        label: 'Save Widgets',
                        submit: false,
                        onClick: handleSave,
                        disabled: !isDirty || isSaving,
                    }}
                />
            </div>

            <Sheet open={sheetOpen} onOpenChange={handleSheetOpenChange}>
                <SheetContent
                    side="right"
                    className="flex w-full flex-col sm:max-w-md"
                >
                    <SheetHeader className="px-6 pt-6 pb-4">
                        <SheetTitle>
                            {editingWidget
                                ? (availableWidgets[editingWidget.type]?.name ??
                                  editingWidget.type)
                                : 'Widget Settings'}
                        </SheetTitle>
                        <SheetDescription>
                            Update the content and presentation settings for
                            this widget instance.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex-1 overflow-y-auto px-6 py-5">
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="widget-title">
                                    Widget Title{' '}
                                    <span className="text-destructive">*</span>
                                </FieldLabel>
                                <Input
                                    id="widget-title"
                                    value={editTitle}
                                    onChange={(event) =>
                                        setEditTitle(event.target.value)
                                    }
                                    placeholder="Widget display title"
                                />
                            </Field>

                            {(
                                Object.entries(editingSchema) as Array<
                                    [string, WidgetSettingField]
                                >
                            ).map(([key, field]) => (
                                <SettingField
                                    key={key}
                                    fieldKey={key}
                                    field={field}
                                    value={
                                        editSettings[key] ?? field.default ?? ''
                                    }
                                    onChange={(value) =>
                                        handleSettingChange(key, value)
                                    }
                                />
                            ))}
                        </FieldGroup>
                    </div>

                    <SheetFooter className="px-6 pt-4 pb-6">
                        <Button
                            variant="outline"
                            onClick={() => handleSheetOpenChange(false)}
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
