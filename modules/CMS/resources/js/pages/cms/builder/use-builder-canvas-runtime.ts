import { useCallback, useEffect, useRef, type RefObject } from 'react';
import { getNode } from '../../../components/builder/core/ast-helpers';
import type { AstNodeId, AstNodeMap, DropIndicator } from '../../../components/builder/core/ast-types';
import { BuilderDragDrop, type DragDropCallbacks } from '../../../components/builder/core/drag-drop';
import { getElementByAstId, syncAstToIframe } from '../../../components/builder/core/iframe-sync';
import { IframeInteractionHandler, type IframeInteractionCallbacks } from '../../../components/builder/core/iframe-interactions';
import { BuilderOverlay, type OverlayCallbacks } from '../../../components/builder/core/overlay-engine';

type CanvasRuntimeActions = {
    deleteNode: (nodeId: AstNodeId) => void;
    duplicateNode: (nodeId: AstNodeId) => void;
    importHtml: (html: string, parentId: AstNodeId, index?: number, sectionName?: string) => void;
    moveNode: (nodeId: AstNodeId, newParentId: AstNodeId, index: number) => void;
    redo: () => void;
    reorderChild: (parentId: AstNodeId, childId: AstNodeId, newIndex: number) => void;
    setDragged: (nodeId: AstNodeId | null) => void;
    setDropIndicator: (indicator: DropIndicator | null) => void;
    setHovered: (nodeId: AstNodeId | null) => void;
    setSelected: (nodeIds: AstNodeId[]) => void;
    undo: () => void;
};

type UseBuilderCanvasRuntimeOptions = {
    actions: CanvasRuntimeActions;
    canNodeEditInlineText: (nodeId: AstNodeId) => boolean;
    dropIndicator: DropIndicator | null;
    editingTextNodeId: AstNodeId | null;
    handleApplyCode: () => void;
    handleApplyFooterEditor: () => void;
    handleRequestInlineTextEdit: (nodeId: AstNodeId) => void;
    handleSave: () => Promise<void>;
    hoveredId: AstNodeId | null;
    iframeReadyRef: RefObject<boolean>;
    iframeRef: RefObject<HTMLIFrameElement | null>;
    nodes: AstNodeMap;
    nodesRef: RefObject<AstNodeMap>;
    onViewCode: (nodeId: AstNodeId) => void;
    overlayContainerRef: RefObject<HTMLDivElement | null>;
    previewLoadedAt: number;
    previewUrl: string | null;
    rootNodeId: AstNodeId;
    scrollIframeToNode: (nodeId: AstNodeId) => void;
    selectedIds: AstNodeId[];
    selectedItemId: AstNodeId | null;
    stateCss: string;
    stateJs: string;
};

type UseBuilderCanvasRuntimeResult = {
    startPanelDrag: (html: string, type: string, displayName: string) => void;
};

export function useBuilderCanvasRuntime({
    actions,
    canNodeEditInlineText,
    dropIndicator,
    editingTextNodeId,
    handleApplyCode,
    handleApplyFooterEditor,
    handleRequestInlineTextEdit,
    handleSave,
    hoveredId,
    iframeReadyRef,
    iframeRef,
    nodes,
    nodesRef,
    onViewCode,
    overlayContainerRef,
    previewLoadedAt,
    previewUrl,
    rootNodeId,
    scrollIframeToNode,
    selectedIds,
    selectedItemId,
    stateCss,
    stateJs,
}: UseBuilderCanvasRuntimeOptions): UseBuilderCanvasRuntimeResult {
    const overlayRef = useRef<BuilderOverlay | null>(null);
    const dragDropRef = useRef<BuilderDragDrop | null>(null);
    const interactionRef = useRef<IframeInteractionHandler | null>(null);
    const handleSaveRef = useRef<() => void>(() => { });
    const handleViewCodeRef = useRef<(nodeId: AstNodeId) => void>(() => { });

    handleSaveRef.current = () => {
        void handleSave();
    };
    handleViewCodeRef.current = onViewCode;

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            const shortcutScope = event.target instanceof Element
                ? event.target.closest('[data-builder-shortcut-scope]') as HTMLElement | null
                : null;
            const mod = event.ctrlKey || event.metaKey;

            if (shortcutScope) {
                if (mod && event.key.toLowerCase() === 's') {
                    event.preventDefault();

                    if (shortcutScope.dataset.builderShortcutScope === 'element-code') {
                        handleApplyCode();

                        return;
                    }

                    if (shortcutScope.dataset.builderShortcutScope === 'footer-code') {
                        handleApplyFooterEditor();
                    }
                }

                return;
            }

            if (mod && event.key.toLowerCase() === 's') {
                event.preventDefault();
                void handleSave();
            }

            if (mod && event.key.toLowerCase() === 'z' && !event.shiftKey) {
                event.preventDefault();
                actions.undo();
            }

            if (mod && event.key.toLowerCase() === 'y') {
                event.preventDefault();
                actions.redo();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => {
            window.removeEventListener('keydown', onKeyDown);
        };
    }, [actions, handleApplyCode, handleApplyFooterEditor, handleSave]);

    useEffect(() => {
        const iframeDoc = iframeRef.current?.contentDocument;

        if (!iframeDoc || !iframeReadyRef.current) {
            return;
        }

        if (previewUrl) {
            syncAstToIframe(iframeDoc, nodes, rootNodeId, stateCss, stateJs);
        }
    }, [iframeReadyRef, iframeRef, nodes, previewLoadedAt, previewUrl, rootNodeId, stateCss, stateJs]);

    useEffect(() => {
        const iframe = iframeRef.current;
        const overlayContainer = overlayContainerRef.current;

        if (!iframe || !overlayContainer || !iframeReadyRef.current) {
            return;
        }

        overlayRef.current?.destroy();
        dragDropRef.current?.destroy();
        interactionRef.current?.destroy();

        const overlayCb: OverlayCallbacks = {
            onMoveNode: (nodeId, direction) => {
                const currentNodes = nodesRef.current;
                const node = getNode(currentNodes, nodeId);

                if (!node?.parentId) {
                    return;
                }

                const parent = currentNodes[node.parentId];

                if (!parent) {
                    return;
                }

                const idx = parent.childIds.indexOf(nodeId);
                const newIdx = direction === 'up' ? idx - 1 : idx + 1;

                if (newIdx >= 0 && newIdx < parent.childIds.length) {
                    actions.reorderChild(node.parentId, nodeId, newIdx);
                    scrollIframeToNode(nodeId);
                }
            },
            onDuplicateNode: (nodeId) => actions.duplicateNode(nodeId),
            onDeleteNode: (nodeId) => actions.deleteNode(nodeId),
            onSelect: (nodeId) => actions.setSelected([nodeId]),
            onStartDrag: (nodeId) => {
                dragDropRef.current?.startCanvasDrag(nodeId);
            },
            onViewCode: (nodeId) => handleViewCodeRef.current(nodeId),
        };

        const overlay = new BuilderOverlay(overlayContainer, iframe, overlayCb);
        overlayRef.current = overlay;

        const dragCb: DragDropCallbacks = {
            onDragStart: (source) => {
                if (source.kind === 'existing') {
                    actions.setDragged(source.nodeId);
                }
            },
            onDragMove: (indicator) => {
                actions.setDropIndicator(indicator);

                if (dragDropRef.current) {
                    dragDropRef.current.setLastIndicator(indicator);
                }
            },
            onDropExisting: (nodeId, parentId, index) => {
                actions.moveNode(nodeId, parentId, index);
            },
            onDropNew: (html, parentId, index) => {
                actions.importHtml(html, parentId, index);
            },
            onDragEnd: () => {
                actions.setDragged(null);
                actions.setDropIndicator(null);
            },
        };

        const dragDrop = new BuilderDragDrop(iframe, dragCb);
        dragDrop.updateNodes(nodes);
        dragDropRef.current = dragDrop;

        const interactionCb: IframeInteractionCallbacks = {
            onHover: (nodeId) => actions.setHovered(nodeId),
            onSelect: (nodeId) => {
                if (nodeId) {
                    actions.setSelected([nodeId]);
                } else {
                    actions.setSelected([]);
                }
            },
            onRequestTextEdit: (nodeId) => handleRequestInlineTextEdit(nodeId),
            onDelete: (nodeId) => actions.deleteNode(nodeId),
            onEscape: () => actions.setSelected([]),
            onMoveUp: (nodeId) => {
                const currentNodes = nodesRef.current;
                const node = getNode(currentNodes, nodeId);

                if (!node?.parentId) {
                    return;
                }

                const parent = currentNodes[node.parentId];

                if (!parent) {
                    return;
                }

                const idx = parent.childIds.indexOf(nodeId);

                if (idx > 0) {
                    actions.reorderChild(node.parentId, nodeId, idx - 1);
                    scrollIframeToNode(nodeId);
                }
            },
            onMoveDown: (nodeId) => {
                const currentNodes = nodesRef.current;
                const node = getNode(currentNodes, nodeId);

                if (!node?.parentId) {
                    return;
                }

                const parent = currentNodes[node.parentId];

                if (!parent) {
                    return;
                }

                const idx = parent.childIds.indexOf(nodeId);

                if (idx < parent.childIds.length - 1) {
                    actions.reorderChild(node.parentId, nodeId, idx + 1);
                    scrollIframeToNode(nodeId);
                }
            },
            onRedo: () => actions.redo(),
            onSave: () => handleSaveRef.current(),
            onUndo: () => actions.undo(),
            canEditText: (nodeId) => canNodeEditInlineText(nodeId),
            isTextEditing: () => editingTextNodeId !== null,
        };

        const interaction = new IframeInteractionHandler(iframe, interactionCb);
        interaction.bind();
        interactionRef.current = interaction;

        return () => {
            overlay.destroy();
            dragDrop.destroy();
            interaction.destroy();
        };
    }, [actions, canNodeEditInlineText, editingTextNodeId, handleRequestInlineTextEdit, iframeReadyRef, iframeRef, nodes, nodesRef, overlayContainerRef, previewLoadedAt, scrollIframeToNode]);

    useEffect(() => {
        dragDropRef.current?.updateNodes(nodes);
    }, [nodes]);

    useEffect(() => {
        interactionRef.current?.setSelectedId(selectedItemId);
    }, [selectedItemId]);

    useEffect(() => {
        overlayRef.current?.update({
            hoveredId: editingTextNodeId ? null : hoveredId,
            selectedIds: editingTextNodeId ? [] : selectedIds,
            dropIndicator,
            nodes,
            rootNodeId,
        });
    }, [dropIndicator, editingTextNodeId, hoveredId, nodes, rootNodeId, selectedIds]);

    useEffect(() => {
        const overlayContainer = overlayContainerRef.current;

        if (!overlayContainer) {
            return;
        }

        const handleDragOver = (event: DragEvent) => {
            event.preventDefault();

            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'copy';
            }

            overlayContainer.style.pointerEvents = 'auto';
            dragDropRef.current?.handleDragMove(event.clientX, event.clientY);
        };

        const handleDrop = (event: DragEvent) => {
            event.preventDefault();
            event.stopPropagation();

            dragDropRef.current?.handleDrop();
            overlayContainer.style.pointerEvents = 'none';
        };

        const handleDragLeave = (event: DragEvent) => {
            if (!overlayContainer.contains(event.relatedTarget as Node)) {
                overlayContainer.style.pointerEvents = 'none';
                actions.setDropIndicator(null);
            }
        };

        const handleDocumentDragEnd = () => {
            overlayContainer.style.pointerEvents = 'none';

            if (dragDropRef.current?.isActive()) {
                dragDropRef.current.handleDrop();
            }
        };

        overlayContainer.addEventListener('dragover', handleDragOver);
        overlayContainer.addEventListener('drop', handleDrop);
        overlayContainer.addEventListener('dragleave', handleDragLeave);
        document.addEventListener('dragend', handleDocumentDragEnd);

        return () => {
            overlayContainer.removeEventListener('dragover', handleDragOver);
            overlayContainer.removeEventListener('drop', handleDrop);
            overlayContainer.removeEventListener('dragleave', handleDragLeave);
            document.removeEventListener('dragend', handleDocumentDragEnd);
        };
    }, [actions, overlayContainerRef]);

    const startPanelDrag = useCallback((html: string, type: string, displayName: string): void => {
        dragDropRef.current?.startPanelDrag(html, type, displayName);
    }, []);

    return {
        startPanelDrag,
    };
}
