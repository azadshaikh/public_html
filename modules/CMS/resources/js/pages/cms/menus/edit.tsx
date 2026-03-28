import { Link, useHttp } from '@inertiajs/react';
import { ArrowLeftIcon, LinkIcon } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { DragEvent } from 'react';
import { showAppToast } from '@/components/forms/form-success-toast';
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
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Kbd, KbdGroup } from '@/components/ui/kbd';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    DraftMenuItem,
    MenuSettings,
    SavePayload,
    SaveResponse,
} from '../../../components/menus/menu-editor-types';
import {
    applyDrop,
    buildRenderOrder,
    collectDescendantIds,
    indentItem,
    moveItemDown,
    moveItemUp,
    outdentItem,
} from '../../../components/menus/menu-editor-utils';
import { MenuItemEditSheet } from '../../../components/menus/menu-item-edit-sheet';
import { MenuItemLibraryPanel } from '../../../components/menus/menu-item-library-panel';
import { MenuItemRow } from '../../../components/menus/menu-item-row';
import { CmsSaveFooter } from '../../../components/shared/cms-save-footer';
import type { MenuEditPageProps } from '../../../types/cms';

export default function MenusEdit({
    menu,
    pages,
    categories,
    tags,
    itemTypes,
    itemTargets,
    menuSettings,
    locationOptions,
}: MenuEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Menus', href: route('cms.appearance.menus.index') },
        {
            title: menu.name,
            href: route('cms.appearance.menus.edit', { menu: menu.id }),
        },
    ];

    const maxDepth = (menuSettings?.max_depth as number | undefined) ?? 3;
    const nextTempId = useRef(-1);

    const [items, setItems] = useState<DraftMenuItem[]>(() =>
        menu.all_items.map((item) => ({
            id: item.id,
            parent_id: item.parent_id,
            title: item.title,
            url: item.url,
            type: item.type,
            target: item.target,
            icon: item.icon,
            css_classes: item.css_classes,
            link_title: item.link_title,
            link_rel: item.link_rel,
            description: item.description,
            object_id: item.object_id,
            sort_order: item.sort_order,
            is_active: item.is_active,
        })),
    );
    const [deletedIds, setDeletedIds] = useState<number[]>([]);
    const [isDirty, setIsDirty] = useState(false);
    const [settings, setSettings] = useState<MenuSettings>({
        name: menu.name,
        location: menu.location ?? '',
        is_active: menu.is_active,
        description: menu.description ?? '',
    });
    const [draggedId, setDraggedId] = useState<number | null>(null);
    const [dropTarget, setDropTarget] = useState<{
        id: number;
        position: 'before' | 'after';
    } | null>(null);
    const [editingItem, setEditingItem] = useState<DraftMenuItem | null>(null);
    const [sheetOpen, setSheetOpen] = useState(false);

    const saveRequest = useHttp<SavePayload, SaveResponse>({
        settings,
        items: { new: [], updated: [], deleted: [], order: [] },
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

    const renderOrder = useMemo(
        () => buildRenderOrder(items, menu.id),
        [items, menu.id],
    );

    const updateSettings = useCallback(
        <K extends keyof MenuSettings>(key: K, value: MenuSettings[K]) => {
            setSettings((previous) => ({ ...previous, [key]: value }));
            setIsDirty(true);
        },
        [],
    );

    const markDirty = useCallback(() => setIsDirty(true), []);

    const addItem = useCallback(
        (overrides: Omit<DraftMenuItem, 'id' | 'parent_id' | 'sort_order'>) => {
            const topLevelItems = items.filter(
                (item) => item.parent_id === menu.id,
            );
            const maxSortOrder = topLevelItems.reduce(
                (maximum, item) => Math.max(maximum, item.sort_order),
                -1,
            );
            const tempId = nextTempId.current--;
            const newItem: DraftMenuItem = {
                ...overrides,
                id: tempId,
                parent_id: menu.id,
                sort_order: maxSortOrder + 1,
            };

            setItems((previous) => [...previous, newItem]);
            setIsDirty(true);
        },
        [items, menu.id],
    );

    const deleteItem = useCallback(
        (itemId: number) => {
            const descendantIds = collectDescendantIds(items, itemId);
            const allToDelete = [itemId, ...descendantIds];
            const serverIds = allToDelete.filter((id) => id > 0);

            if (serverIds.length > 0) {
                setDeletedIds((previous) => [...previous, ...serverIds]);
            }

            setItems((previous) => {
                const remaining = previous.filter(
                    (item) => !allToDelete.includes(item.id),
                );
                const parentId =
                    previous.find((item) => item.id === itemId)?.parent_id ??
                    menu.id;
                const siblings = remaining
                    .filter((item) => item.parent_id === parentId)
                    .sort((left, right) => left.sort_order - right.sort_order);

                siblings.forEach((sibling, index) => {
                    sibling.sort_order = index;
                });

                return [...remaining];
            });
            setIsDirty(true);
        },
        [items, menu.id],
    );

    const updateItem = useCallback((updated: DraftMenuItem) => {
        setItems((previous) =>
            previous.map((item) =>
                item.id === updated.id ? { ...updated } : item,
            ),
        );
        setIsDirty(true);
    }, []);

    const handleDragStart = useCallback(
        (event: DragEvent<HTMLButtonElement>, id: number) => {
            event.dataTransfer.effectAllowed = 'move';
            setDraggedId(id);
        },
        [],
    );

    const handleDragOver = useCallback(
        (event: DragEvent<HTMLDivElement>, id: number) => {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';

            if (!draggedId || draggedId === id) {
                return;
            }

            const rect = event.currentTarget.getBoundingClientRect();
            const midPoint = rect.top + rect.height / 2;
            const position: 'before' | 'after' =
                event.clientY < midPoint ? 'before' : 'after';

            setDropTarget((previous) =>
                previous?.id === id && previous.position === position
                    ? previous
                    : { id, position },
            );
        },
        [draggedId],
    );

    const handleDrop = useCallback(
        (event: DragEvent<HTMLDivElement>) => {
            event.preventDefault();
            if (!draggedId || !dropTarget) {
                return;
            }

            setItems((previous) =>
                applyDrop(previous, draggedId, dropTarget.id, dropTarget.position),
            );
            setIsDirty(true);
            setDraggedId(null);
            setDropTarget(null);
        },
        [draggedId, dropTarget],
    );

    const handleDragEnd = useCallback(() => {
        setDraggedId(null);
        setDropTarget(null);
    }, []);

    const handleMoveUp = useCallback(
        (id: number) => {
            setItems((previous) => moveItemUp(previous, id));
            markDirty();
        },
        [markDirty],
    );

    const handleMoveDown = useCallback(
        (id: number) => {
            setItems((previous) => moveItemDown(previous, id));
            markDirty();
        },
        [markDirty],
    );

    const handleIndent = useCallback(
        (id: number) => {
            setItems((previous) => indentItem(previous, id, menu.id, maxDepth));
            markDirty();
        },
        [markDirty, maxDepth, menu.id],
    );

    const handleOutdent = useCallback(
        (id: number) => {
            setItems((previous) => outdentItem(previous, id, menu.id));
            markDirty();
        },
        [markDirty, menu.id],
    );

    const handleEditItem = useCallback((item: DraftMenuItem) => {
        setEditingItem(item);
        setSheetOpen(true);
    }, []);

    const handleSave = useCallback(async () => {
        const payload: SavePayload = {
            settings,
            items: {
                new: items.filter((item) => item.id < 0),
                updated: items.filter((item) => item.id > 0),
                deleted: deletedIds.map((id) => ({ id })),
                order: items.map((item) => ({
                    id: item.id,
                    parent_id: item.parent_id,
                    sort_order: item.sort_order,
                })),
            },
        };

        try {
            saveRequest.transform(() => payload);
            const response = await saveRequest.post(
                route('cms.appearance.menus.save-all', { menu: menu.id }),
            );

            if (!response.success) {
                throw new Error(response.details || response.message || 'Save failed.');
            }

            if (
                response.newItemIds &&
                Object.keys(response.newItemIds).length > 0
            ) {
                const idMap = new Map<number, number>();

                for (const [tempId, realId] of Object.entries(response.newItemIds)) {
                    idMap.set(parseInt(tempId, 10), realId);
                }

                setItems((previous) =>
                    previous.map((item) => ({
                        ...item,
                        id: item.id < 0 ? (idMap.get(item.id) ?? item.id) : item.id,
                        parent_id:
                            item.parent_id < 0
                                ? (idMap.get(item.parent_id) ?? item.parent_id)
                                : item.parent_id,
                    })),
                );
            }

            setDeletedIds([]);
            setIsDirty(false);

            showAppToast({
                variant: 'success',
                title: 'Menu saved',
                description: response.message,
            });
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Save failed',
                description:
                    error instanceof Error
                        ? error.message
                        : 'An unexpected error occurred.',
            });
        }
    }, [deletedIds, items, menu.id, saveRequest, settings]);

    useEffect(() => {
        const handleKeydown = (event: KeyboardEvent) => {
            if (
                (event.ctrlKey || event.metaKey) &&
                event.key === 's' &&
                isDirty &&
                !saveRequest.processing
            ) {
                event.preventDefault();
                void handleSave();
            }
        };

        window.addEventListener('keydown', handleKeydown);

        return () => window.removeEventListener('keydown', handleKeydown);
    }, [handleSave, isDirty, saveRequest.processing]);

    const handleDiscardChanges = useCallback(() => {
        setItems(menu.all_items.map((item) => ({ ...item })));
        setDeletedIds([]);
        setSettings({
            name: menu.name,
            location: menu.location ?? '',
            is_active: menu.is_active,
            description: menu.description ?? '',
        });
        setIsDirty(false);
        setSheetOpen(false);
        setEditingItem(null);
    }, [menu]);

    const isSaving = saveRequest.processing;
    const showUnsavedChangesStatus = isDirty && !isSaving;
    const footerStatusText = isSaving
        ? 'Saving changes...'
        : showUnsavedChangesStatus
          ? 'You have unsaved menu changes.'
          : 'All changes saved.';

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit Menu: ${menu.name}`}
            description="Build your navigation menu structure by adding and reordering items."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('cms.appearance.menus.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <div className="flex flex-1 flex-col gap-6 pb-20">
                <Card>
                    <CardHeader className="pb-4">
                        <CardTitle className="text-base">Menu Settings</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Field className="lg:col-span-2">
                                <FieldLabel htmlFor="menu-name">
                                    Name <span className="text-destructive">*</span>
                                </FieldLabel>
                                <Input
                                    id="menu-name"
                                    value={settings.name}
                                    onChange={(event) =>
                                        updateSettings('name', event.target.value)
                                    }
                                    placeholder="Menu name"
                                />
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="menu-location">
                                    Location
                                </FieldLabel>
                                <NativeSelect
                                    id="menu-location"
                                    value={settings.location}
                                    onChange={(event) =>
                                        updateSettings(
                                            'location',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <NativeSelectOption value="">
                                        — None —
                                    </NativeSelectOption>
                                    {locationOptions.map((option) => (
                                        <NativeSelectOption
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                            </Field>

                            <Field
                                orientation="horizontal"
                                className="items-start pt-6"
                            >
                                <Switch
                                    checked={settings.is_active}
                                    onCheckedChange={(checked) =>
                                        updateSettings('is_active', checked)
                                    }
                                />
                                <div className="flex flex-col gap-0.5">
                                    <FieldLabel>Active</FieldLabel>
                                    <FieldDescription>
                                        Visible on the front end.
                                    </FieldDescription>
                                </div>
                            </Field>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <Card className="flex min-h-[540px] flex-col">
                        <CardHeader className="pb-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <CardTitle className="text-base">
                                    Menu Structure
                                </CardTitle>
                                <Badge variant="outline">
                                    {items.length}{' '}
                                    {items.length === 1 ? 'item' : 'items'}
                                </Badge>
                                {isDirty ? (
                                    <Badge variant="warning">
                                        Unsaved changes
                                    </Badge>
                                ) : null}
                            </div>
                            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                <span>
                                    Drag items to reorder. Use the arrow buttons
                                    to adjust hierarchy.
                                </span>
                                <KbdGroup>
                                    <Kbd>Ctrl</Kbd>
                                    <span>+</span>
                                    <Kbd>S</Kbd>
                                </KbdGroup>
                            </div>
                        </CardHeader>
                        <CardContent className="flex flex-1 flex-col gap-1 p-3">
                            {renderOrder.length === 0 ? (
                                <Empty className="flex-1 rounded-2xl border border-dashed bg-muted/10 py-16">
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <LinkIcon />
                                        </EmptyMedia>
                                        <EmptyTitle>
                                            No items in this menu
                                        </EmptyTitle>
                                        <EmptyDescription>
                                            Start with the library on the right
                                            to add pages, categories, tags, and
                                            custom links.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            ) : (
                                renderOrder.map(({ item, depth }) => (
                                    <MenuItemRow
                                        key={item.id}
                                        renderItem={{ item, depth }}
                                        allItems={items}
                                        menuId={menu.id}
                                        maxDepth={maxDepth}
                                        isDragging={draggedId === item.id}
                                        isDraggedOver={
                                            dropTarget?.id === item.id
                                                ? dropTarget.position
                                                : null
                                        }
                                        onDragStart={handleDragStart}
                                        onDragOver={handleDragOver}
                                        onDrop={handleDrop}
                                        onDragEnd={handleDragEnd}
                                        onMoveUp={handleMoveUp}
                                        onMoveDown={handleMoveDown}
                                        onIndent={handleIndent}
                                        onOutdent={handleOutdent}
                                        onEdit={handleEditItem}
                                        onDelete={deleteItem}
                                    />
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <MenuItemLibraryPanel
                        pages={pages}
                        categories={categories}
                        tags={tags}
                        currentItems={items}
                        onAddItem={addItem}
                    />
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
                        label: 'Save Menu',
                        submit: false,
                        onClick: handleSave,
                        disabled: !isDirty || isSaving,
                    }}
                />
            </div>

            <MenuItemEditSheet
                key={`${editingItem?.id ?? 'empty'}-${sheetOpen ? 'open' : 'closed'}`}
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                item={editingItem}
                itemTypes={itemTypes}
                itemTargets={itemTargets}
                onSave={updateItem}
            />
        </AppLayout>
    );
}
