import type { DraftMenuItem, RenderItem } from './menu-editor-types';

export function buildRenderOrder(
    items: DraftMenuItem[],
    parentId: number,
    depth = 0,
): RenderItem[] {
    return items
        .filter((item) => item.parent_id === parentId)
        .sort((left, right) => left.sort_order - right.sort_order)
        .flatMap((item) => [
            { item, depth },
            ...buildRenderOrder(items, item.id, depth + 1),
        ]);
}

export function getItemDepth(
    items: DraftMenuItem[],
    itemId: number,
    menuId: number,
): number {
    const item = items.find((candidate) => candidate.id === itemId);
    if (!item || item.parent_id === menuId) {
        return 0;
    }

    return 1 + getItemDepth(items, item.parent_id, menuId);
}

export function getSubtreeMaxDepth(
    items: DraftMenuItem[],
    itemId: number,
    menuId: number,
): number {
    const baseDepth = getItemDepth(items, itemId, menuId);
    const children = items.filter((item) => item.parent_id === itemId);
    if (children.length === 0) {
        return baseDepth;
    }

    return Math.max(
        ...children.map((child) => getSubtreeMaxDepth(items, child.id, menuId)),
    );
}

export function isDescendant(
    items: DraftMenuItem[],
    ancestorId: number,
    targetId: number,
): boolean {
    const target = items.find((item) => item.id === targetId);
    if (!target) {
        return false;
    }

    if (target.parent_id === ancestorId) {
        return true;
    }

    return isDescendant(items, ancestorId, target.parent_id);
}

export function applyDrop(
    previousItems: DraftMenuItem[],
    draggedId: number,
    targetId: number,
    position: 'before' | 'after',
): DraftMenuItem[] {
    if (draggedId === targetId) {
        return previousItems;
    }

    if (isDescendant(previousItems, draggedId, targetId)) {
        return previousItems;
    }

    const items = previousItems.map((item) => ({ ...item }));
    const dragged = items.find((item) => item.id === draggedId);
    const target = items.find((item) => item.id === targetId);

    if (!dragged || !target) {
        return previousItems;
    }

    const oldParentId = dragged.parent_id;
    dragged.parent_id = target.parent_id;

    if (oldParentId !== target.parent_id) {
        items
            .filter(
                (item) =>
                    item.parent_id === oldParentId && item.id !== draggedId,
            )
            .sort((left, right) => left.sort_order - right.sort_order)
            .forEach((item, index) => {
                item.sort_order = index;
            });
    }

    const siblings = items
        .filter(
            (item) =>
                item.parent_id === target.parent_id && item.id !== draggedId,
        )
        .sort((left, right) => left.sort_order - right.sort_order);

    const targetIndex = siblings.findIndex((item) => item.id === targetId);
    const insertAt =
        position === 'before' ? Math.max(0, targetIndex) : targetIndex + 1;

    siblings.splice(insertAt, 0, dragged);
    siblings.forEach((item, index) => {
        item.sort_order = index;
    });

    return [...items];
}

export function moveItemUp(
    previousItems: DraftMenuItem[],
    itemId: number,
): DraftMenuItem[] {
    const items = previousItems.map((item) => ({ ...item }));
    const item = items.find((candidate) => candidate.id === itemId);
    if (!item) {
        return previousItems;
    }

    const siblings = items
        .filter((candidate) => candidate.parent_id === item.parent_id)
        .sort((left, right) => left.sort_order - right.sort_order);
    const itemIndex = siblings.findIndex((candidate) => candidate.id === itemId);

    if (itemIndex <= 0) {
        return previousItems;
    }

    const previousSibling = siblings[itemIndex - 1];
    const temporarySortOrder = previousSibling.sort_order;
    previousSibling.sort_order = item.sort_order;
    item.sort_order = temporarySortOrder;

    return [...items];
}

export function moveItemDown(
    previousItems: DraftMenuItem[],
    itemId: number,
): DraftMenuItem[] {
    const items = previousItems.map((item) => ({ ...item }));
    const item = items.find((candidate) => candidate.id === itemId);
    if (!item) {
        return previousItems;
    }

    const siblings = items
        .filter((candidate) => candidate.parent_id === item.parent_id)
        .sort((left, right) => left.sort_order - right.sort_order);
    const itemIndex = siblings.findIndex((candidate) => candidate.id === itemId);

    if (itemIndex >= siblings.length - 1) {
        return previousItems;
    }

    const nextSibling = siblings[itemIndex + 1];
    const temporarySortOrder = nextSibling.sort_order;
    nextSibling.sort_order = item.sort_order;
    item.sort_order = temporarySortOrder;

    return [...items];
}

export function indentItem(
    previousItems: DraftMenuItem[],
    itemId: number,
    menuId: number,
    maxDepth: number,
): DraftMenuItem[] {
    const items = previousItems.map((item) => ({ ...item }));
    const item = items.find((candidate) => candidate.id === itemId);
    if (!item) {
        return previousItems;
    }

    const siblings = items
        .filter((candidate) => candidate.parent_id === item.parent_id)
        .sort((left, right) => left.sort_order - right.sort_order);
    const itemIndex = siblings.findIndex((candidate) => candidate.id === itemId);

    if (itemIndex <= 0) {
        return previousItems;
    }

    const newParent = siblings[itemIndex - 1];
    const newParentDepth = getItemDepth(items, newParent.id, menuId);
    const subtreeHeight =
        getSubtreeMaxDepth(items, itemId, menuId) -
        getItemDepth(items, itemId, menuId);

    if (newParentDepth + 1 + subtreeHeight >= maxDepth) {
        return previousItems;
    }

    item.parent_id = newParent.id;
    siblings
        .filter((sibling) => sibling.id !== itemId)
        .forEach((sibling, index) => {
            sibling.sort_order = index;
        });

    const newParentChildren = items
        .filter(
            (candidate) =>
                candidate.parent_id === newParent.id && candidate.id !== itemId,
        )
        .sort((left, right) => left.sort_order - right.sort_order);
    item.sort_order = newParentChildren.length;

    return [...items];
}

export function outdentItem(
    previousItems: DraftMenuItem[],
    itemId: number,
    menuId: number,
): DraftMenuItem[] {
    const items = previousItems.map((item) => ({ ...item }));
    const item = items.find((candidate) => candidate.id === itemId);
    if (!item || item.parent_id === menuId) {
        return previousItems;
    }

    const parent = items.find((candidate) => candidate.id === item.parent_id);
    if (!parent) {
        return previousItems;
    }

    const grandParentId = parent.parent_id;

    const oldSiblings = items
        .filter(
            (candidate) =>
                candidate.parent_id === item.parent_id && candidate.id !== itemId,
        )
        .sort((left, right) => left.sort_order - right.sort_order);
    oldSiblings.forEach((sibling, index) => {
        sibling.sort_order = index;
    });

    const grandParentChildren = items
        .filter(
            (candidate) =>
                candidate.parent_id === grandParentId &&
                candidate.id !== itemId,
        )
        .sort((left, right) => left.sort_order - right.sort_order);
    const parentIndex = grandParentChildren.findIndex(
        (candidate) => candidate.id === parent.id,
    );
    grandParentChildren.splice(parentIndex + 1, 0, item);
    grandParentChildren.forEach((child, index) => {
        child.sort_order = index;
    });
    item.parent_id = grandParentId;

    return [...items];
}

export function collectDescendantIds(
    items: DraftMenuItem[],
    itemId: number,
): number[] {
    const children = items.filter((item) => item.parent_id === itemId);

    return children.flatMap((child) => [
        child.id,
        ...collectDescendantIds(items, child.id),
    ]);
}
