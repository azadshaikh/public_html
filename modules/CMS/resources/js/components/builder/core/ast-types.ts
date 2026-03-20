/**
 * AST-based page model for the builder.
 *
 * Inspired by craft.js: a flat node map with parent/child references.
 * This is the single source of truth — the DOM is a projection of this data.
 *
 * Design for AI: every mutation is a pure function call on the node map,
 * so an AI agent can build pages by calling addNode/updateNode/moveNode/deleteNode
 * without any awareness of DOM or React.
 */

// ---------------------------------------------------------------------------
// Node types
// ---------------------------------------------------------------------------

/**
 * Every element type the builder can represent.
 * Extensible — new types can be added without touching core logic.
 */
export type AstNodeType =
    | 'page'
    | 'section'
    | 'container'
    | 'row'
    | 'column'
    | 'text'
    | 'heading'
    | 'image'
    | 'button'
    | 'video'
    | 'divider'
    | 'spacer'
    | 'html'
    | 'icon'
    | 'link'
    | 'list'
    | 'form';

// ---------------------------------------------------------------------------
// Node definition
// ---------------------------------------------------------------------------

export type AstNodeId = string;

/**
 * A single node in the page AST.
 *
 * The flat map stores nodes by ID. Parent/child relationships are maintained
 * via `parentId` and `childIds` — like craft.js, not nested objects.
 */
export type AstNode = {
    /** Unique identifier. */
    id: AstNodeId;

    /** Node type determines rendering and available props. */
    type: AstNodeType;

    /** Display name shown in the layers panel. */
    displayName: string;

    /** Parent node ID. `null` only for the root page node. */
    parentId: AstNodeId | null;

    /** Ordered child node IDs. */
    childIds: AstNodeId[];

    /**
     * Whether this node accepts children via drag-and-drop.
     * Containers, sections, rows, columns = true.
     * Leaf nodes (text, image, button) = false.
     */
    isCanvas: boolean;

    /**
     * Type-specific properties (text content, src, href, alt, etc.).
     * Intentionally loose — each node type defines its own shape.
     */
    props: Record<string, unknown>;

    /**
     * Inline CSS styles.
     * Stored as camelCase key → string value (like React style objects).
     */
    styles: Record<string, string>;

    /**
     * CSS class names as a space-separated string.
     */
    className: string;

    /**
     * Tag override. e.g. a "text" node can be rendered as <p>, <span>, <h1>, etc.
     * When null, the renderer uses the default tag for the node type.
     */
    tagName: string | null;

    /**
     * Arbitrary custom data. Used for catalog references, AI metadata, etc.
     */
    custom: Record<string, unknown>;

    /**
     * Whether the node is hidden in the preview.
     */
    hidden: boolean;
};

// ---------------------------------------------------------------------------
// Node map (the AST)
// ---------------------------------------------------------------------------

export type AstNodeMap = Record<AstNodeId, AstNode>;

// ---------------------------------------------------------------------------
// Page AST (top-level structure)
// ---------------------------------------------------------------------------

export type PageAst = {
    /** The root node ID (always present, type = 'page'). */
    rootNodeId: AstNodeId;

    /** Flat map of all nodes. */
    nodes: AstNodeMap;

    /** Page-level custom CSS. */
    css: string;

    /** Page-level custom JS. */
    js: string;

    /** Schema version for migration support. */
    version: number;
};

// ---------------------------------------------------------------------------
// Editor events state (like craft.js events)
// ---------------------------------------------------------------------------

export type BuilderEvents = {
    /** Currently hovered node ID. */
    hoveredId: AstNodeId | null;

    /** Currently selected node IDs (supports multi-select in future). */
    selectedIds: AstNodeId[];

    /** Node being dragged (null when not dragging). */
    draggedId: AstNodeId | null;
};

// ---------------------------------------------------------------------------
// Drop indicator
// ---------------------------------------------------------------------------

export type DropPosition = 'before' | 'after' | 'inside';

export type DropIndicator = {
    /** The target node that the dragged item is near. */
    targetId: AstNodeId;

    /** Where relative to the target node. */
    position: DropPosition;

    /** Pixel coordinates for rendering the indicator line. */
    rect: { top: number; left: number; width: number; height: number };

    /** Whether the drop is allowed. */
    isValid: boolean;

    /** The parent that would receive the dropped node. */
    parentId: AstNodeId;

    /** The index in the parent's childIds where the node would be inserted. */
    index: number;
};

// ---------------------------------------------------------------------------
// Serialization types (for saving / AI tool calls)
// ---------------------------------------------------------------------------

export type SerializedNode = {
    id: AstNodeId;
    type: AstNodeType;
    displayName: string;
    parentId: AstNodeId | null;
    childIds: AstNodeId[];
    isCanvas: boolean;
    props: Record<string, unknown>;
    styles: Record<string, string>;
    className: string;
    tagName: string | null;
    custom: Record<string, unknown>;
    hidden: boolean;
};

export type SerializedPageAst = {
    rootNodeId: AstNodeId;
    nodes: Record<AstNodeId, SerializedNode>;
    css: string;
    js: string;
    version: number;
};

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

export const ROOT_NODE_ID = 'ROOT';

export const AST_VERSION = 1;

/**
 * Default tag names for each node type.
 */
export const DEFAULT_TAG_MAP: Record<AstNodeType, string> = {
    page: 'div',
    section: 'section',
    container: 'div',
    row: 'div',
    column: 'div',
    text: 'p',
    heading: 'h2',
    image: 'img',
    button: 'button',
    video: 'div',
    divider: 'hr',
    spacer: 'div',
    html: 'div',
    icon: 'span',
    link: 'a',
    list: 'ul',
    form: 'form',
};

/**
 * Node types that can accept children.
 */
export const CANVAS_TYPES: Set<AstNodeType> = new Set([
    'page',
    'section',
    'container',
    'row',
    'column',
    'html',
    'link',
    'list',
    'form',
]);
