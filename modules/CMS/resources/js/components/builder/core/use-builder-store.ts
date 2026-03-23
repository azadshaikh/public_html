/**
 * React state management for the builder AST.
 *
 * Uses useReducer with a history stack for undo/redo.
 * All mutations go through actions → reducer ensures consistency.
 */

import { useEffect, useMemo, useReducer, useRef, useState } from 'react';
import {
    addNode,
    addSubtree,
    createEmptyPageAst,
    createNode,
    deleteNode,
    duplicateNode,
    moveNode,
    parseHtmlToAst,
    reorderChild,
    serializePageAst,
    updateNode,
    updateNodeProps,
    updateNodeStyles,
} from './ast-helpers';
import type { CreateNodeOptions } from './ast-helpers';
import type {
    AstNode,
    AstNodeId,
    AstNodeMap,
    BuilderEvents,
    DropIndicator,
    PageAst,
} from './ast-types';
import { sameDropIndicator, sameNodeIdList } from './builder-runtime-guards';

// ---------------------------------------------------------------------------
// State shape
// ---------------------------------------------------------------------------

export type BuilderState = {
    /** The page AST (source of truth). */
    ast: PageAst;

    /** UI events (hover, select, drag). */
    events: BuilderEvents;

    /** Drop indicator for drag-and-drop. */
    dropIndicator: DropIndicator | null;

    /** Undo/redo history. */
    history: {
        past: PageAst[];
        future: PageAst[];
    };
};

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------

type Action =
    | { type: 'SET_AST'; ast: PageAst; pushHistory?: boolean }
    | { type: 'ADD_NODE'; node: AstNode; parentId: AstNodeId; index?: number }
    | { type: 'ADD_SUBTREE'; subtreeNodes: AstNodeMap; subtreeRootId: AstNodeId; parentId: AstNodeId; index?: number }
    | { type: 'DELETE_NODE'; nodeId: AstNodeId }
    | { type: 'MOVE_NODE'; nodeId: AstNodeId; newParentId: AstNodeId; index: number }
    | { type: 'DUPLICATE_NODE'; nodeId: AstNodeId }
    | { type: 'UPDATE_NODE'; nodeId: AstNodeId; patch: Partial<Pick<AstNode, 'type' | 'displayName' | 'className' | 'tagName' | 'hidden' | 'isCanvas' | 'props' | 'styles' | 'custom'>> }
    | { type: 'UPDATE_PROPS'; nodeId: AstNodeId; props: Record<string, unknown> }
    | { type: 'UPDATE_STYLES'; nodeId: AstNodeId; styles: Record<string, string> }
    | { type: 'REORDER_CHILD'; parentId: AstNodeId; childId: AstNodeId; newIndex: number }
    | { type: 'SET_CSS'; css: string }
    | { type: 'SET_JS'; js: string }
    | { type: 'SET_HOVERED'; nodeId: AstNodeId | null }
    | { type: 'SET_SELECTED'; nodeIds: AstNodeId[] }
    | { type: 'SET_DRAGGED'; nodeId: AstNodeId | null }
    | { type: 'SET_DROP_INDICATOR'; indicator: DropIndicator | null }
    | { type: 'UNDO' }
    | { type: 'REDO' }
    | { type: 'IMPORT_HTML'; html: string; parentId: AstNodeId; index?: number; sectionName?: string; custom?: Record<string, unknown> };

// ---------------------------------------------------------------------------
// History helpers
// ---------------------------------------------------------------------------

const MAX_HISTORY = 50;

function pushHistory(state: BuilderState): BuilderState['history'] {
    const past = [...state.history.past, state.ast].slice(-MAX_HISTORY);

    return { past, future: [] };
}

// ---------------------------------------------------------------------------
// Reducer
// ---------------------------------------------------------------------------

function builderReducer(state: BuilderState, action: Action): BuilderState {
    switch (action.type) {
        case 'SET_AST':
            return {
                ...state,
                ast: action.ast,
                history: action.pushHistory !== false ? pushHistory(state) : state.history,
            };

        case 'ADD_NODE':
            return {
                ...state,
                ast: {
                    ...state.ast,
                    nodes: addNode(state.ast.nodes, action.node, action.parentId, action.index),
                },
                history: pushHistory(state),
            };

        case 'ADD_SUBTREE':
            return {
                ...state,
                ast: {
                    ...state.ast,
                    nodes: addSubtree(state.ast.nodes, action.subtreeNodes, action.subtreeRootId, action.parentId, action.index),
                },
                history: pushHistory(state),
            };

        case 'DELETE_NODE': {
            const newNodes = deleteNode(state.ast.nodes, action.nodeId);

            // Clear selection if deleted node was selected
            const newSelectedIds = state.events.selectedIds.filter((id) => newNodes[id] !== undefined);

            return {
                ...state,
                ast: { ...state.ast, nodes: newNodes },
                events: { ...state.events, selectedIds: newSelectedIds },
                history: pushHistory(state),
            };
        }

        case 'MOVE_NODE':
            return {
                ...state,
                ast: {
                    ...state.ast,
                    nodes: moveNode(state.ast.nodes, action.nodeId, action.newParentId, action.index),
                },
                history: pushHistory(state),
            };

        case 'DUPLICATE_NODE': {
            const [newNodes, newId] = duplicateNode(state.ast.nodes, action.nodeId);

            return {
                ...state,
                ast: { ...state.ast, nodes: newNodes },
                events: { ...state.events, selectedIds: [newId] },
                history: pushHistory(state),
            };
        }

        case 'UPDATE_NODE':
            return {
                ...state,
                ast: {
                    ...state.ast,
                    nodes: updateNode(state.ast.nodes, action.nodeId, action.patch),
                },
                history: pushHistory(state),
            };

        case 'UPDATE_PROPS':
            return {
                ...state,
                ast: {
                    ...state.ast,
                    nodes: updateNodeProps(state.ast.nodes, action.nodeId, action.props),
                },
                history: pushHistory(state),
            };

        case 'UPDATE_STYLES':
            return {
                ...state,
                ast: {
                    ...state.ast,
                    nodes: updateNodeStyles(state.ast.nodes, action.nodeId, action.styles),
                },
                history: pushHistory(state),
            };

        case 'REORDER_CHILD':
            return {
                ...state,
                ast: {
                    ...state.ast,
                    nodes: reorderChild(state.ast.nodes, action.parentId, action.childId, action.newIndex),
                },
                history: pushHistory(state),
            };

        case 'SET_CSS':
            return {
                ...state,
                ast: { ...state.ast, css: action.css },
            };

        case 'SET_JS':
            return {
                ...state,
                ast: { ...state.ast, js: action.js },
            };

        case 'SET_HOVERED':
            if (state.events.hoveredId === action.nodeId) {
                return state;
            }

            return {
                ...state,
                events: { ...state.events, hoveredId: action.nodeId },
            };

        case 'SET_SELECTED':
            if (sameNodeIdList(state.events.selectedIds, action.nodeIds)) {
                return state;
            }

            return {
                ...state,
                events: { ...state.events, selectedIds: action.nodeIds },
            };

        case 'SET_DRAGGED':
            if (state.events.draggedId === action.nodeId) {
                return state;
            }

            return {
                ...state,
                events: { ...state.events, draggedId: action.nodeId },
            };

        case 'SET_DROP_INDICATOR':
            if (sameDropIndicator(state.dropIndicator, action.indicator)) {
                return state;
            }

            return {
                ...state,
                dropIndicator: action.indicator,
            };

        case 'UNDO': {
            if (state.history.past.length === 0) {
                return state;
            }

            const past = [...state.history.past];
            const previousAst = past.pop()!;

            return {
                ...state,
                ast: previousAst,
                history: {
                    past,
                    future: [state.ast, ...state.history.future].slice(0, MAX_HISTORY),
                },
            };
        }

        case 'REDO': {
            if (state.history.future.length === 0) {
                return state;
            }

            const future = [...state.history.future];
            const nextAst = future.shift()!;

            return {
                ...state,
                ast: nextAst,
                history: {
                    past: [...state.history.past, state.ast].slice(-MAX_HISTORY),
                    future,
                },
            };
        }

        case 'IMPORT_HTML': {
            const { nodes: subtreeNodes, rootId } = parseHtmlToAst(
                action.html,
                'section',
                action.sectionName ?? '',
                action.custom ?? {},
            );

            return {
                ...state,
                ast: {
                    ...state.ast,
                    nodes: addSubtree(state.ast.nodes, subtreeNodes, rootId, action.parentId, action.index),
                },
                events: { ...state.events, selectedIds: [rootId] },
                history: pushHistory(state),
            };
        }

        default:
            return state;
    }
}

// ---------------------------------------------------------------------------
// Hook
// ---------------------------------------------------------------------------

export function useBuilderStore(initialAst?: PageAst) {
    const initialState: BuilderState = {
        ast: initialAst ?? createEmptyPageAst(),
        events: {
            hoveredId: null,
            selectedIds: [],
            draggedId: null,
        },
        dropIndicator: null,
        history: { past: [], future: [] },
    };

    const [state, dispatch] = useReducer(builderReducer, initialState);

    const [dirtyBaseline, setDirtyBaseline] = useState(() => JSON.stringify(serializePageAst(initialState.ast)));

    const astRef = useRef(state.ast);

    useEffect(() => {
        astRef.current = state.ast;
    }, [state.ast]);

    // Derived state
    const selectedNode = useMemo((): AstNode | null => {
        const id = state.events.selectedIds[0];

        return id ? (state.ast.nodes[id] ?? null) : null;
    }, [state.events.selectedIds, state.ast.nodes]);

    const isDirty = useMemo(() => {
        return JSON.stringify(serializePageAst(state.ast)) !== dirtyBaseline;
    }, [dirtyBaseline, state.ast]);

    // ---------------------------------------------------------------------------
    // Actions (stable callbacks — dispatch is stable from useReducer)
    // ---------------------------------------------------------------------------

    const actions = useMemo(() => ({
        setAst: (ast: PageAst, pushHistory = true) => {
            dispatch({ type: 'SET_AST', ast, pushHistory });
        },

        addNode: (options: CreateNodeOptions, parentId: AstNodeId, index?: number) => {
            const node = createNode(options);
            dispatch({ type: 'ADD_NODE', node, parentId, index });

            return node.id;
        },

        deleteNode: (nodeId: AstNodeId) => {
            dispatch({ type: 'DELETE_NODE', nodeId });
        },

        moveNode: (nodeId: AstNodeId, newParentId: AstNodeId, index: number) => {
            dispatch({ type: 'MOVE_NODE', nodeId, newParentId, index });
        },

        duplicateNode: (nodeId: AstNodeId) => {
            dispatch({ type: 'DUPLICATE_NODE', nodeId });
        },

        updateNode: (nodeId: AstNodeId, patch: Partial<Pick<AstNode, 'type' | 'displayName' | 'className' | 'tagName' | 'hidden' | 'isCanvas' | 'props' | 'styles' | 'custom'>>) => {
            dispatch({ type: 'UPDATE_NODE', nodeId, patch });
        },

        updateProps: (nodeId: AstNodeId, props: Record<string, unknown>) => {
            dispatch({ type: 'UPDATE_PROPS', nodeId, props });
        },

        updateStyles: (nodeId: AstNodeId, styles: Record<string, string>) => {
            dispatch({ type: 'UPDATE_STYLES', nodeId, styles });
        },

        reorderChild: (parentId: AstNodeId, childId: AstNodeId, newIndex: number) => {
            dispatch({ type: 'REORDER_CHILD', parentId, childId, newIndex });
        },

        setCss: (css: string) => {
            dispatch({ type: 'SET_CSS', css });
        },

        setJs: (js: string) => {
            dispatch({ type: 'SET_JS', js });
        },

        importHtml: (html: string, parentId: AstNodeId, index?: number, sectionName?: string, custom?: Record<string, unknown>) => {
            dispatch({ type: 'IMPORT_HTML', html, parentId, index, sectionName, custom });
        },

        addSubtree: (subtreeNodes: AstNodeMap, subtreeRootId: AstNodeId, parentId: AstNodeId, index?: number) => {
            dispatch({ type: 'ADD_SUBTREE', subtreeNodes, subtreeRootId, parentId, index });
        },

        // Event actions
        setHovered: (nodeId: AstNodeId | null) => {
            dispatch({ type: 'SET_HOVERED', nodeId });
        },

        setSelected: (nodeIds: AstNodeId[]) => {
            dispatch({ type: 'SET_SELECTED', nodeIds });
        },

        selectNode: (nodeId: AstNodeId) => {
            dispatch({ type: 'SET_SELECTED', nodeIds: [nodeId] });
        },

        clearSelection: () => {
            dispatch({ type: 'SET_SELECTED', nodeIds: [] });
        },

        setDragged: (nodeId: AstNodeId | null) => {
            dispatch({ type: 'SET_DRAGGED', nodeId });
        },

        setDropIndicator: (indicator: DropIndicator | null) => {
            dispatch({ type: 'SET_DROP_INDICATOR', indicator });
        },

        undo: () => {
            dispatch({ type: 'UNDO' });
        },

        redo: () => {
            dispatch({ type: 'REDO' });
        },

        resetDirty: () => {
            setDirtyBaseline(JSON.stringify(serializePageAst(astRef.current)));
        },
    }), [setDirtyBaseline]);

    return {
        state,
        actions,
        selectedNode,
        isDirty,

        // Convenient accessors
        nodes: state.ast.nodes,
        rootNodeId: state.ast.rootNodeId,
        events: state.events,
        dropIndicator: state.dropIndicator,
        canUndo: state.history.past.length > 0,
        canRedo: state.history.future.length > 0,
    };
}

export type BuilderStore = ReturnType<typeof useBuilderStore>;
