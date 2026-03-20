/**
 * Pure helper functions for manipulating the AST node map.
 *
 * Every function takes the current node map (or PageAst) and returns
 * a new copy — no mutation. This makes undo/redo trivial and is
 * safe for AI tool-call usage.
 */

import {
    AST_VERSION,
    CANVAS_TYPES,
    DEFAULT_TAG_MAP,
    ROOT_NODE_ID,
    type AstNode,
    type AstNodeId,
    type AstNodeMap,
    type AstNodeType,
    type PageAst,
    type SerializedPageAst,
} from './ast-types';

// ---------------------------------------------------------------------------
// ID generation
// ---------------------------------------------------------------------------

let counter = 0;

export function generateNodeId(): AstNodeId {
    counter += 1;

    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `node-${Date.now()}-${counter}-${Math.random().toString(36).slice(2, 8)}`;
}

// ---------------------------------------------------------------------------
// Node creation
// ---------------------------------------------------------------------------

export type CreateNodeOptions = {
    type: AstNodeType;
    displayName?: string;
    props?: Record<string, unknown>;
    styles?: Record<string, string>;
    className?: string;
    tagName?: string | null;
    custom?: Record<string, unknown>;
    isCanvas?: boolean;
    hidden?: boolean;
    id?: AstNodeId;
};

export function createNode(options: CreateNodeOptions): AstNode {
    return {
        id: options.id ?? generateNodeId(),
        type: options.type,
        displayName: options.displayName ?? options.type,
        parentId: null,
        childIds: [],
        isCanvas: options.isCanvas ?? CANVAS_TYPES.has(options.type),
        props: options.props ?? {},
        styles: options.styles ?? {},
        className: options.className ?? '',
        tagName: options.tagName ?? null,
        custom: options.custom ?? {},
        hidden: options.hidden ?? false,
    };
}

// ---------------------------------------------------------------------------
// Empty page AST
// ---------------------------------------------------------------------------

export function createEmptyPageAst(): PageAst {
    const root = createNode({
        id: ROOT_NODE_ID,
        type: 'page',
        displayName: 'Page',
        isCanvas: true,
    });

    return {
        rootNodeId: ROOT_NODE_ID,
        nodes: { [ROOT_NODE_ID]: root },
        css: '',
        js: '',
        version: AST_VERSION,
    };
}

// ---------------------------------------------------------------------------
// Read helpers
// ---------------------------------------------------------------------------

export function getNode(nodes: AstNodeMap, id: AstNodeId): AstNode | null {
    return nodes[id] ?? null;
}

export function getParent(nodes: AstNodeMap, id: AstNodeId): AstNode | null {
    const node = nodes[id];

    if (!node?.parentId) {
        return null;
    }

    return nodes[node.parentId] ?? null;
}

export function getChildren(nodes: AstNodeMap, id: AstNodeId): AstNode[] {
    const node = nodes[id];

    if (!node) {
        return [];
    }

    return node.childIds.map((childId) => nodes[childId]).filter(Boolean);
}

export function getAncestors(nodes: AstNodeMap, id: AstNodeId): AstNode[] {
    const ancestors: AstNode[] = [];
    let current = nodes[id];

    while (current?.parentId) {
        const parent = nodes[current.parentId];

        if (!parent) {
            break;
        }

        ancestors.push(parent);
        current = parent;
    }

    return ancestors;
}

export function getDescendantIds(nodes: AstNodeMap, id: AstNodeId): AstNodeId[] {
    const result: AstNodeId[] = [];
    const stack = [id];

    while (stack.length > 0) {
        const currentId = stack.pop()!;
        const node = nodes[currentId];

        if (!node) {
            continue;
        }

        if (currentId !== id) {
            result.push(currentId);
        }

        // Push in reverse so left children are processed first
        for (let i = node.childIds.length - 1; i >= 0; i--) {
            stack.push(node.childIds[i]);
        }
    }

    return result;
}

/**
 * Find the nearest canvas ancestor (a node that accepts children).
 */
export function getNearestCanvas(nodes: AstNodeMap, id: AstNodeId): AstNode | null {
    let current = nodes[id];

    while (current) {
        if (current.isCanvas) {
            return current;
        }

        if (!current.parentId) {
            break;
        }

        current = nodes[current.parentId];
    }

    return null;
}

/**
 * Check if `ancestorId` is an ancestor of `nodeId`.
 */
export function isAncestor(nodes: AstNodeMap, nodeId: AstNodeId, ancestorId: AstNodeId): boolean {
    let current = nodes[nodeId];

    while (current?.parentId) {
        if (current.parentId === ancestorId) {
            return true;
        }

        current = nodes[current.parentId];
    }

    return false;
}

// ---------------------------------------------------------------------------
// Mutation helpers (return new maps — never mutate)
// ---------------------------------------------------------------------------

/**
 * Add a node as a child of `parentId` at `index`.
 * Returns a new node map.
 */
export function addNode(
    nodes: AstNodeMap,
    node: AstNode,
    parentId: AstNodeId,
    index?: number,
): AstNodeMap {
    const parent = nodes[parentId];

    if (!parent) {
        return nodes;
    }

    const insertIndex = index !== undefined
        ? Math.max(0, Math.min(index, parent.childIds.length))
        : parent.childIds.length;

    const newChildIds = [...parent.childIds];
    newChildIds.splice(insertIndex, 0, node.id);

    return {
        ...nodes,
        [node.id]: { ...node, parentId },
        [parentId]: { ...parent, childIds: newChildIds },
    };
}

/**
 * Add a node and all its descendants (a subtree) to the map.
 * The subtree must have internal parent/child references already set.
 * The root of the subtree is attached to `parentId` at `index`.
 */
export function addSubtree(
    nodes: AstNodeMap,
    subtreeNodes: AstNodeMap,
    subtreeRootId: AstNodeId,
    parentId: AstNodeId,
    index?: number,
): AstNodeMap {
    const parent = nodes[parentId];

    if (!parent) {
        return nodes;
    }

    const insertIndex = index !== undefined
        ? Math.max(0, Math.min(index, parent.childIds.length))
        : parent.childIds.length;

    const newChildIds = [...parent.childIds];
    newChildIds.splice(insertIndex, 0, subtreeRootId);

    // Merge subtree nodes, set root's parentId
    const merged: AstNodeMap = { ...nodes, ...subtreeNodes };
    merged[subtreeRootId] = { ...merged[subtreeRootId], parentId };
    merged[parentId] = { ...parent, childIds: newChildIds };

    return merged;
}

/**
 * Remove a node and all its descendants from the map.
 * Returns a new node map.
 */
export function deleteNode(nodes: AstNodeMap, id: AstNodeId): AstNodeMap {
    const node = nodes[id];

    if (!node || id === ROOT_NODE_ID) {
        return nodes;
    }

    // Collect all IDs to remove
    const idsToRemove = new Set([id, ...getDescendantIds(nodes, id)]);

    // Remove from parent's childIds
    const newNodes: AstNodeMap = {};

    for (const [nodeId, n] of Object.entries(nodes)) {
        if (idsToRemove.has(nodeId)) {
            continue;
        }

        if (nodeId === node.parentId) {
            newNodes[nodeId] = {
                ...n,
                childIds: n.childIds.filter((cid) => cid !== id),
            };
        } else {
            newNodes[nodeId] = n;
        }
    }

    return newNodes;
}

/**
 * Move a node to a new parent at `index`.
 * Handles removing from old parent and adding to new parent.
 */
export function moveNode(
    nodes: AstNodeMap,
    nodeId: AstNodeId,
    newParentId: AstNodeId,
    index: number,
): AstNodeMap {
    const node = nodes[nodeId];
    const newParent = nodes[newParentId];

    if (!node || !newParent || nodeId === ROOT_NODE_ID) {
        return nodes;
    }

    // Can't move into own descendant
    if (isAncestor(nodes, newParentId, nodeId)) {
        return nodes;
    }

    const result = { ...nodes };

    // Remove from old parent
    if (node.parentId && result[node.parentId]) {
        const oldParent = result[node.parentId];
        result[node.parentId] = {
            ...oldParent,
            childIds: oldParent.childIds.filter((cid) => cid !== nodeId),
        };
    }

    // Calculate proper index (if moving within same parent, account for removal)
    let adjustedIndex = index;
    const currentParent = result[newParentId];

    if (node.parentId === newParentId) {
        const oldIndex = nodes[newParentId].childIds.indexOf(nodeId);

        if (oldIndex !== -1 && oldIndex < index) {
            adjustedIndex = index - 1;
        }
    }

    // Add to new parent
    const newChildIds = [...currentParent.childIds];
    adjustedIndex = Math.max(0, Math.min(adjustedIndex, newChildIds.length));
    newChildIds.splice(adjustedIndex, 0, nodeId);

    result[newParentId] = { ...currentParent, childIds: newChildIds };
    result[nodeId] = { ...node, parentId: newParentId };

    return result;
}

/**
 * Update a node's properties (shallow merge).
 */
export function updateNodeProps(
    nodes: AstNodeMap,
    id: AstNodeId,
    props: Record<string, unknown>,
): AstNodeMap {
    const node = nodes[id];

    if (!node) {
        return nodes;
    }

    return {
        ...nodes,
        [id]: { ...node, props: { ...node.props, ...props } },
    };
}

/**
 * Update a node's inline styles (shallow merge).
 */
export function updateNodeStyles(
    nodes: AstNodeMap,
    id: AstNodeId,
    styles: Record<string, string>,
): AstNodeMap {
    const node = nodes[id];

    if (!node) {
        return nodes;
    }

    return {
        ...nodes,
        [id]: { ...node, styles: { ...node.styles, ...styles } },
    };
}

/**
 * Update arbitrary node fields.
 */
export function updateNode(
    nodes: AstNodeMap,
    id: AstNodeId,
    patch: Partial<Pick<AstNode, 'type' | 'displayName' | 'className' | 'tagName' | 'hidden' | 'isCanvas' | 'props' | 'styles' | 'custom'>>,
): AstNodeMap {
    const node = nodes[id];

    if (!node) {
        return nodes;
    }

    return {
        ...nodes,
        [id]: {
            ...node,
            ...patch,
            props: patch.props ? { ...node.props, ...patch.props } : node.props,
            styles: patch.styles ? { ...node.styles, ...patch.styles } : node.styles,
            custom: patch.custom ? { ...node.custom, ...patch.custom } : node.custom,
        },
    };
}

/**
 * Duplicate a node (and all descendants). Returns [newNodes, newRootId].
 */
export function duplicateNode(
    nodes: AstNodeMap,
    id: AstNodeId,
): [AstNodeMap, AstNodeId] {
    const node = nodes[id];

    if (!node || id === ROOT_NODE_ID) {
        return [nodes, id];
    }

    // Build ID remapping
    const allIds = [id, ...getDescendantIds(nodes, id)];
    const idMap = new Map<AstNodeId, AstNodeId>();

    for (const oldId of allIds) {
        idMap.set(oldId, generateNodeId());
    }

    const newRootId = idMap.get(id)!;

    // Clone all nodes with new IDs
    let result = { ...nodes };

    for (const oldId of allIds) {
        const original = nodes[oldId];

        if (!original) {
            continue;
        }

        const newId = idMap.get(oldId)!;
        const newParentId = oldId === id
            ? original.parentId
            : (idMap.get(original.parentId!) ?? original.parentId);

        result[newId] = {
            ...original,
            id: newId,
            parentId: newParentId,
            childIds: original.childIds.map((cid) => idMap.get(cid) ?? cid),
            displayName: oldId === id ? `${original.displayName} Copy` : original.displayName,
        };
    }

    // Insert the duplicate after the original in the parent's childIds
    if (node.parentId && result[node.parentId]) {
        const parent = result[node.parentId];
        const originalIndex = parent.childIds.indexOf(id);
        const newChildIds = [...parent.childIds];
        newChildIds.splice(originalIndex + 1, 0, newRootId);
        result[node.parentId] = { ...parent, childIds: newChildIds };
    }

    return [result, newRootId];
}

// ---------------------------------------------------------------------------
// Reorder within parent
// ---------------------------------------------------------------------------

export function reorderChild(
    nodes: AstNodeMap,
    parentId: AstNodeId,
    childId: AstNodeId,
    newIndex: number,
): AstNodeMap {
    const parent = nodes[parentId];

    if (!parent) {
        return nodes;
    }

    const oldIndex = parent.childIds.indexOf(childId);

    if (oldIndex === -1) {
        return nodes;
    }

    const newChildIds = [...parent.childIds];
    newChildIds.splice(oldIndex, 1);
    const clampedIndex = Math.max(0, Math.min(newIndex, newChildIds.length));
    newChildIds.splice(clampedIndex, 0, childId);

    return {
        ...nodes,
        [parentId]: { ...parent, childIds: newChildIds },
    };
}

// ---------------------------------------------------------------------------
// Serialization
// ---------------------------------------------------------------------------

export function serializePageAst(ast: PageAst): SerializedPageAst {
    const serializedNodes: Record<AstNodeId, SerializedPageAst['nodes'][string]> = {};

    for (const [id, node] of Object.entries(ast.nodes)) {
        serializedNodes[id] = {
            id: node.id,
            type: node.type,
            displayName: node.displayName,
            parentId: node.parentId,
            childIds: [...node.childIds],
            isCanvas: node.isCanvas,
            props: { ...node.props },
            styles: { ...node.styles },
            className: node.className,
            tagName: node.tagName,
            custom: { ...node.custom },
            hidden: node.hidden,
        };
    }

    return {
        rootNodeId: ast.rootNodeId,
        nodes: serializedNodes,
        css: ast.css,
        js: ast.js,
        version: ast.version,
    };
}

export function deserializePageAst(data: SerializedPageAst): PageAst {
    const nodes: AstNodeMap = {};

    for (const [id, serialized] of Object.entries(data.nodes)) {
        nodes[id] = {
            id: serialized.id,
            type: serialized.type,
            displayName: serialized.displayName,
            parentId: serialized.parentId,
            childIds: [...serialized.childIds],
            isCanvas: serialized.isCanvas,
            props: { ...serialized.props },
            styles: { ...serialized.styles },
            className: serialized.className,
            tagName: serialized.tagName,
            custom: { ...serialized.custom },
            hidden: serialized.hidden,
        };
    }

    return {
        rootNodeId: data.rootNodeId,
        nodes,
        css: data.css ?? '',
        js: data.js ?? '',
        version: data.version ?? AST_VERSION,
    };
}

// ---------------------------------------------------------------------------
// HTML import: parse an HTML string into an AST subtree
// ---------------------------------------------------------------------------

/**
 * Parse an HTML string into a subtree of AST nodes.
 * Returns the subtree node map and the root node ID.
 * Used for importing palette sections/blocks and existing page content.
 */
export function parseHtmlToAst(
    html: string,
    rootType: AstNodeType = 'section',
    displayName: string = '',
    custom: Record<string, unknown> = {},
): { nodes: AstNodeMap; rootId: AstNodeId } {
    const parser = new DOMParser();
    const doc = parser.parseFromString(`<body>${html}</body>`, 'text/html');
    const body = doc.body;

    const nodes: AstNodeMap = {};

    function processElement(el: Element, parentId: AstNodeId | null, isRoot: boolean): AstNodeId {
        const tag = el.tagName.toLowerCase();
        const type = isRoot ? inferNodeType(tag, el) : inferNodeType(tag, el);
        const id = generateNodeId();

        const props: Record<string, unknown> = {};
        const styles: Record<string, string> = {};
        let className = '';

        // Extract attributes
        for (const attr of Array.from(el.attributes)) {
            if (attr.name === 'class') {
                className = attr.value;
            } else if (attr.name === 'style') {
                // Parse inline styles
                const styleEl = el as HTMLElement;

                for (let i = 0; i < styleEl.style.length; i++) {
                    const prop = styleEl.style[i];
                    styles[camelCase(prop)] = styleEl.style.getPropertyValue(prop);
                }
            } else if (attr.name === 'src' || attr.name === 'href' || attr.name === 'alt') {
                props[attr.name] = attr.value;
            } else if (attr.name !== 'data-ast-id') {
                props[`attr_${attr.name}`] = attr.value;
            }
        }

        // Text content for leaf nodes
        if (el.children.length === 0 && el.textContent) {
            props.content = el.textContent;
        }

        const node = createNode({
            id,
            type,
            displayName: isRoot ? (displayName || buildDisplayName(tag, className)) : buildDisplayName(tag, className),
            props,
            styles,
            className,
            tagName: tag,
            isCanvas: CANVAS_TYPES.has(type),
            custom: isRoot ? custom : {},
        });

        node.parentId = parentId;
        nodes[id] = node;

        // Process children
        for (const child of Array.from(el.children)) {
            const childId = processElement(child, id, false);
            node.childIds.push(childId);
        }

        // If element has mixed content (text + elements), wrap text nodes
        if (el.children.length > 0) {
            for (const childNode of Array.from(el.childNodes)) {
                if (childNode.nodeType === Node.TEXT_NODE && childNode.textContent?.trim()) {
                    const textId = generateNodeId();
                    const textNode = createNode({
                        id: textId,
                        type: 'text',
                        displayName: 'text',
                        props: { content: childNode.textContent.trim() },
                        tagName: 'span',
                    });

                    textNode.parentId = id;
                    nodes[textId] = textNode;

                    // Insert at the right position
                    const nextSibling = (childNode as ChildNode & { nextElementSibling: Element | null }).nextElementSibling;

                    if (nextSibling) {
                        const siblingId = findNodeIdByElement(nodes, id, nextSibling);
                        const idx = node.childIds.indexOf(siblingId);

                        if (idx !== -1) {
                            node.childIds.splice(idx, 0, textId);
                        } else {
                            node.childIds.push(textId);
                        }
                    } else {
                        node.childIds.push(textId);
                    }
                }
            }
        }

        return id;
    }

    // Process root elements (there may be multiple top-level elements)
    let rootId: AstNodeId;

    if (body.children.length === 1) {
        rootId = processElement(body.children[0], null, true);
    } else if (body.children.length > 1) {
        // Wrap in a container
        const wrapperNode = createNode({
            type: rootType,
            displayName: displayName || rootType,
            tagName: DEFAULT_TAG_MAP[rootType] ?? 'div',
            isCanvas: true,
            custom,
        });

        wrapperNode.parentId = null;
        nodes[wrapperNode.id] = wrapperNode;
        rootId = wrapperNode.id;

        for (const child of Array.from(body.children)) {
            const childId = processElement(child, rootId, false);
            wrapperNode.childIds.push(childId);
        }
    } else {
        // Empty HTML — create empty section
        const emptySection = createNode({
            type: rootType,
            displayName,
            isCanvas: true,
            custom,
        });

        emptySection.parentId = null;
        nodes[emptySection.id] = emptySection;
        rootId = emptySection.id;
    }

    return { nodes, rootId };
}

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

function inferNodeType(tag: string, _el: Element): AstNodeType {
    switch (tag) {
        case 'section':
            return 'section';
        case 'img':
            return 'image';
        case 'button':
            return 'button';
        case 'a':
            return 'link';
        case 'h1':
        case 'h2':
        case 'h3':
        case 'h4':
        case 'h5':
        case 'h6':
            return 'heading';
        case 'hr':
            return 'divider';
        case 'ul':
        case 'ol':
            return 'list';
        case 'form':
            return 'form';
        case 'video':
        case 'iframe':
            return 'video';
        case 'svg':
        case 'i':
            return 'icon';
        case 'p':
        case 'span':
        case 'label':
        case 'small':
        case 'strong':
        case 'em':
        case 'b':
            return 'text';
        case 'div':
        case 'main':
        case 'article':
        case 'aside':
        case 'header':
        case 'footer':
        case 'nav':
            return 'container';
        default:
            return 'container';
    }
}

function camelCase(str: string): string {
    return str.replace(/-([a-z])/g, (_, c: string) => c.toUpperCase());
}

function findNodeIdByElement(nodes: AstNodeMap, parentId: AstNodeId, el: Element): AstNodeId {
    const parent = nodes[parentId];

    if (!parent) {
        return '';
    }

    // Simple approach: match by tag and position
    for (const childId of parent.childIds) {
        const child = nodes[childId];

        if (child && child.tagName === el.tagName.toLowerCase()) {
            return childId;
        }
    }

    return '';
}

/**
 * Build a human-readable display name from the HTML tag and class list.
 * e.g. "H2 .display-5", "NAV .navbar", "DIV .container"
 */
function buildDisplayName(tag: string, className: string): string {
    const label = tag.toUpperCase();
    const firstClass = className.trim().split(/\s+/)[0];

    if (firstClass) {
        return `${label} .${firstClass}`;
    }

    return label;
}
