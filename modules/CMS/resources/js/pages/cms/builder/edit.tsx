'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { MonacoEditor } from '@/components/code-editor/monaco-editor';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useDirtyFormGuard } from '@/hooks/use-dirty-form-guard';
import { cn } from '@/lib/utils';
import { BuilderCanvasToolbar } from '../../../components/builder/builder-canvas-toolbar';
import { BuilderComponentsPanel } from '../../../components/builder/builder-components-panel';
import { BuilderHeader } from '../../../components/builder/builder-header';
import {
    collectEditableElements,
    findEditableElement,
    updateItemElement,
    type BuilderElementPath,
    type BuilderElementStyleValues,
} from '../../../components/builder/builder-dom';
import { BuilderPreviewPanel } from '../../../components/builder/builder-preview-panel';
import { BuilderPropertiesPanel } from '../../../components/builder/builder-properties-panel';
import {
    buildPreviewDocument,
    extractErrorMessage,
    getCsrfToken,
    type BuilderDeviceMode,
} from '../../../components/builder/builder-utils';
import { ROOT_NODE_ID } from '../../../components/builder/core/ast-types';
import { parseHtmlToAst, createEmptyPageAst, getNode, serializePageAst } from '../../../components/builder/core/ast-helpers';
import { renderCleanNodeToHtml, renderCleanPageContent, renderNodeToHtml, renderPageContent } from '../../../components/builder/core/ast-to-html';
import { useBuilderStore } from '../../../components/builder/core/use-builder-store';
import { getElementByAstId, syncAstToIframe } from '../../../components/builder/core/iframe-sync';
import { BuilderOverlay, type OverlayCallbacks } from '../../../components/builder/core/overlay-engine';
import { BuilderDragDrop, type DragDropCallbacks } from '../../../components/builder/core/drag-drop';
import { IframeInteractionHandler, type IframeInteractionCallbacks } from '../../../components/builder/core/iframe-interactions';
import ThemeCustomizerLayout from '../../../components/theme-customizer/theme-customizer-layout';
import type {
    BuilderCanvasItem,
    BuilderEditPageProps,
    BuilderLibraryItem,
} from '../../../types/cms';

// ---------------------------------------------------------------------------
// Helpers: Convert legacy builder state items into AST
// ---------------------------------------------------------------------------

const VOID_ELEMENTS = new Set([
    'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
    'link', 'meta', 'param', 'source', 'track', 'wbr',
]);

function formatHtmlForDisplay(html: string): string {
    const tokens = html.replace(/>\s*</g, '>\n<').split('\n');
    let indent = 0;
    const lines: string[] = [];

    for (const token of tokens) {
        const trimmed = token.trim();

        if (!trimmed) {
            continue;
        }

        const isClosing = /^<\//.test(trimmed);
        const tagMatch = trimmed.match(/^<\/?([a-zA-Z][a-zA-Z0-9-]*)/);
        const tagName = tagMatch?.[1]?.toLowerCase() ?? '';
        const isSelfClosing = /\/>$/.test(trimmed) || VOID_ELEMENTS.has(tagName);
        const isOpening = /^<[a-zA-Z]/.test(trimmed) && !isClosing;

        if (isClosing) {
            indent = Math.max(0, indent - 1);
        }

        lines.push('  '.repeat(indent) + trimmed);

        if (isOpening && !isSelfClosing) {
            indent++;
        }
    }

    return lines.join('\n');
}

function buildInitialAst(items: BuilderCanvasItem[], css: string, js: string) {
    if (items.length === 0) {
        const ast = createEmptyPageAst();
        ast.css = css;
        ast.js = js;

        return ast;
    }

    // Create the page root node
    const ast = createEmptyPageAst();
    ast.css = css;
    ast.js = js;

    // Parse each item's HTML as a section subtree and add to the root
    for (const item of items) {
        const trimmed = item.html.trim();

        if (!trimmed) {
            continue;
        }

        const htmlToUse = /^<section[\s>]/i.test(trimmed) ? trimmed : `<section>${trimmed}</section>`;
        const parsed = parseHtmlToAst(htmlToUse, 'section', item.label || 'Section', {});

        // Re-parent the parsed root under our page ROOT
        const parsedRoot = parsed.nodes[parsed.rootId];

        if (parsedRoot) {
            parsedRoot.parentId = ROOT_NODE_ID;
            ast.nodes[parsed.rootId] = parsedRoot;
            ast.nodes[ROOT_NODE_ID].childIds.push(parsed.rootId);

            // Copy all descendants
            for (const [id, node] of Object.entries(parsed.nodes)) {
                if (id !== parsed.rootId) {
                    ast.nodes[id] = node;
                }
            }
        }
    }

    return ast;
}

// ---------------------------------------------------------------------------
// Bridge: Convert AST top-level children to virtual canvas items (for legacy panels)
// ---------------------------------------------------------------------------

function astToCanvasItems(nodes: Record<string, import('../../../components/builder/core/ast-types').AstNode>, rootNodeId: string): BuilderCanvasItem[] {
    const root = nodes[rootNodeId];

    if (!root) {
        return [];
    }

    return root.childIds.map((childId) => {
        const node = nodes[childId];

        return {
            uid: childId,
            catalog_id: null,
            type: node?.type ?? 'section',
            category: 'section',
            label: node?.displayName ?? 'Section',
            html: renderNodeToHtml(nodes, childId),
            css: '',
            js: '',
            preview_image_url: null,
            source: 'database' as const,
        };
    });
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export default function BuilderEdit({
    activeTheme,
    page,
    palette,
    builderState,
}: BuilderEditPageProps) {
    const [deviceMode, setDeviceMode] = useState<BuilderDeviceMode>('desktop');
    const [leftPanelCollapsed, setLeftPanelCollapsed] = useState(false);
    const [rightPanelCollapsed, setRightPanelCollapsed] = useState(false);
    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);
    const [activeLibrary, setActiveLibrary] = useState<'sections' | 'blocks'>(
        palette.sections.length > 0 ? 'sections' : 'blocks',
    );
    const [isSaving, setIsSaving] = useState(false);
    const [codeDialogNodeId, setCodeDialogNodeId] = useState<string | null>(null);
    const [codeDialogValue, setCodeDialogValue] = useState('');
    const [selectedElementPath, setSelectedElementPath] =
        useState<BuilderElementPath>([]);
    const iframeRef = useRef<HTMLIFrameElement | null>(null);
    const overlayContainerRef = useRef<HTMLDivElement | null>(null);
    const [previewLoadedAt, setPreviewLoadedAt] = useState(0);
    const iframeReadyRef = useRef(false);
    const [addedBadge, setAddedBadge] = useState<string | null>(null);
    const addedBadgeTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Imperative refs for interaction subsystems
    const overlayRef = useRef<BuilderOverlay | null>(null);
    const dragDropRef = useRef<BuilderDragDrop | null>(null);
    const interactionRef = useRef<IframeInteractionHandler | null>(null);
    const handleSaveRef = useRef<() => void>(() => {});
    const handleViewCodeRef = useRef<(nodeId: string) => void>(() => {});

    // AST store (replaces canvasItems + selectedItemId)
    const initialAst = useMemo(
        () =>
            buildInitialAst(
                builderState.items,
                builderState.css || page.css || '',
                builderState.js || page.js || '',
            ),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );

    const {
        state,
        actions,
        selectedNode,
        isDirty,
        nodes,
        rootNodeId,
        events,
        dropIndicator,
        canUndo,
        canRedo,
    } = useBuilderStore(initialAst);

    const nodesRef = useRef(nodes);
    nodesRef.current = nodes;

    // Build the preview URL
    const previewUrl = useMemo(() => {
        if (!page.permalink_url) {
            return null;
        }

        const separator = page.permalink_url.includes('?') ? '&' : '?';

        return `${page.permalink_url}${separator}editor_preview=1&r=${Date.now()}`;
    }, [page.permalink_url]);

    // Bridge: compute virtual canvas items from AST (for legacy child components)
    const canvasItems = useMemo(() => astToCanvasItems(nodes, rootNodeId), [nodes, rootNodeId]);

    const selectedItemId = events.selectedIds[0] ?? null;

    // Resolve the selected node to its top-level section ancestor (direct child of ROOT)
    // so the properties panel can find a matching canvas item.
    const resolvedSelectedItemId = useMemo(() => {
        if (!selectedItemId) {
            return null;
        }

        // Walk up the AST tree until we find a node whose parentId is rootNodeId
        let currentId: string | null = selectedItemId;

        while (currentId) {
            const node = nodes[currentId];

            if (!node) {
                return selectedItemId;
            }

            if (node.parentId === rootNodeId) {
                return currentId;
            }

            currentId = node.parentId;
        }

        return selectedItemId;
    }, [nodes, selectedItemId, rootNodeId]);

    const selectedItem = useMemo(
        () => canvasItems.find((item) => item.uid === resolvedSelectedItemId) ?? null,
        [canvasItems, resolvedSelectedItemId],
    );

    const editableElements = useMemo(
        () => (selectedItem ? collectEditableElements(selectedItem.html) : []),
        [selectedItem],
    );

    const selectedElement = useMemo(() => {
        if (!selectedItem) {
            return null;
        }

        return (
            findEditableElement(selectedItem.html, selectedElementPath) ??
            editableElements[0] ??
            null
        );
    }, [editableElements, selectedElementPath, selectedItem]);

    // Fallback standalone preview document (used when no permalink URL is available)
    const previewDocument = useMemo(
        () => (previewUrl ? '' : buildPreviewDocument(canvasItems, state.ast.css, state.ast.js)),
        [canvasItems, state.ast.css, state.ast.js, previewUrl],
    );

    const dirtyGuard = useDirtyFormGuard({
        enabled: isDirty,
    });

    // -----------------------------------------------------------------------
    // Handlers
    // -----------------------------------------------------------------------

    const pendingScrollRef = useRef<string | null>(null);

    const scrollIframeToNode = useCallback((nodeId: string) => {
        requestAnimationFrame(() => {
            const iframeDoc = iframeRef.current?.contentDocument;

            if (!iframeDoc) {
                return;
            }

            const el = getElementByAstId(iframeDoc, nodeId);
            el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }, []);

    const handleAddLibraryItem = useCallback((item: BuilderLibraryItem) => {
        actions.importHtml(item.html, ROOT_NODE_ID);
        pendingScrollRef.current = 'pending';

        if (addedBadgeTimerRef.current) {
            clearTimeout(addedBadgeTimerRef.current);
        }

        setAddedBadge(item.name);
        addedBadgeTimerRef.current = setTimeout(() => setAddedBadge(null), 3000);
    }, [actions]);

    const handleDragStartLibraryItem = useCallback((item: BuilderLibraryItem) => {
        dragDropRef.current?.startPanelDrag(item.html, activeLibrary === 'sections' ? 'section' : 'block', item.name);
    }, [activeLibrary]);

    const handleMoveSelectedItem = useCallback(
        (direction: 'up' | 'down') => {
            if (!selectedItemId) {
                return;
            }

            const node = getNode(nodes, selectedItemId);

            if (!node?.parentId) {
                return;
            }

            const parent = nodes[node.parentId];

            if (!parent) {
                return;
            }

            const idx = parent.childIds.indexOf(selectedItemId);
            const newIdx = direction === 'up' ? idx - 1 : idx + 1;

            if (newIdx >= 0 && newIdx < parent.childIds.length) {
                actions.reorderChild(node.parentId, selectedItemId, newIdx);
                scrollIframeToNode(selectedItemId);
            }
        },
        [actions, nodes, selectedItemId, scrollIframeToNode],
    );

    const handleDuplicateSelectedItem = useCallback(() => {
        if (selectedItemId) {
            actions.duplicateNode(selectedItemId);
        }
    }, [actions, selectedItemId]);

    const handleRemoveSelectedItem = useCallback(() => {
        if (selectedItemId) {
            actions.deleteNode(selectedItemId);
        }
    }, [actions, selectedItemId]);

    const handleViewCode = useCallback(
        (nodeId: string) => {
            const html = renderCleanNodeToHtml(nodes, nodeId);
            setCodeDialogValue(formatHtmlForDisplay(html));
            setCodeDialogNodeId(nodeId);
        },
        [nodes],
    );

    const handleApplyCode = useCallback(() => {
        if (!codeDialogNodeId) {
            return;
        }

        const node = getNode(nodes, codeDialogNodeId);

        if (!node?.parentId) {
            return;
        }

        const parent = nodes[node.parentId];

        if (!parent) {
            return;
        }

        const idx = parent.childIds.indexOf(codeDialogNodeId);
        const parentId = node.parentId;

        actions.deleteNode(codeDialogNodeId);
        actions.importHtml(codeDialogValue, parentId, idx >= 0 ? idx : undefined);
        setCodeDialogNodeId(null);
    }, [actions, codeDialogNodeId, codeDialogValue, nodes]);

    const handleUpdateSelectedItemHtml = useCallback(
        (value: string) => {
            if (!selectedItemId) {
                return;
            }

            // Re-parse HTML and replace the node's subtree
            const tempAst = parseHtmlToAst(value, 'section', 'Section', {});
            const tempRoot = tempAst.nodes[tempAst.rootNodeId];

            if (tempRoot && tempRoot.childIds.length > 0) {
                // Replace existing node's props with first child of parsed AST
                const firstChildId = tempRoot.childIds[0];
                const firstChild = tempAst.nodes[firstChildId];

                if (firstChild) {
                    actions.updateNode(selectedItemId, {
                        props: firstChild.props,
                        styles: firstChild.styles,
                        className: firstChild.className,
                        tagName: firstChild.tagName,
                    });
                }
            }
        },
        [actions, selectedItemId],
    );

    const handleUpdateElementField = useCallback(
        (
            field: 'textContent' | 'href' | 'src' | 'alt' | 'id' | 'className',
            value: string,
        ) => {
            if (!selectedItem) {
                return;
            }

            // Use legacy DOM mutation bridge for now
            const updated = updateItemElement(selectedItem, selectedElementPath, {
                [field]: value,
            });

            // Re-parse the updated HTML back into AST
            if (selectedItemId && updated.html !== selectedItem.html) {
                const tempAst = parseHtmlToAst(updated.html, 'section', 'Section', {});
                actions.deleteNode(selectedItemId);

                const tempRoot = tempAst.nodes[tempAst.rootNodeId];

                if (tempRoot) {
                    for (const childId of tempRoot.childIds) {
                        actions.addSubtree(tempAst.nodes, childId, ROOT_NODE_ID);
                    }
                }
            }
        },
        [actions, selectedElementPath, selectedItem, selectedItemId],
    );

    const handleUpdateElementStyle = useCallback(
        (field: keyof BuilderElementStyleValues, value: string) => {
            if (!selectedItem) {
                return;
            }

            const updated = updateItemElement(selectedItem, selectedElementPath, {
                styles: { [field]: value },
            });

            if (selectedItemId && updated.html !== selectedItem.html) {
                const tempAst = parseHtmlToAst(updated.html, 'section', 'Section', {});
                actions.deleteNode(selectedItemId);

                const tempRoot = tempAst.nodes[tempAst.rootNodeId];

                if (tempRoot) {
                    for (const childId of tempRoot.childIds) {
                        actions.addSubtree(tempAst.nodes, childId, ROOT_NODE_ID);
                    }
                }
            }
        },
        [actions, selectedElementPath, selectedItem, selectedItemId],
    );

    const handleSave = useCallback(async () => {
        setIsSaving(true);

        const flattenedHtml = renderCleanPageContent(nodes, rootNodeId);

        const payload = {
            content: flattenedHtml,
            css: state.ast.css,
            js: state.ast.js,
            format: 'pagebuilder',
            builder_state: {
                css: state.ast.css,
                js: state.ast.js,
                // Save both AST JSON and legacy items for backward compatibility
                items: canvasItems,
                ast: serializePageAst(state.ast),
            },
        };

        try {
            const response = await fetch(route('cms.builder.save', page.id), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const result = (await response.json()) as unknown;

            if (!response.ok) {
                throw new Error(extractErrorMessage(result));
            }

            actions.setAst(state.ast);
            showAppToast({
                variant: 'success',
                title: 'Builder saved',
                description: 'Page content saved successfully.',
            });
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Save failed',
                description:
                    error instanceof Error
                        ? error.message
                        : 'The builder could not save your changes.',
            });
        } finally {
            setIsSaving(false);
        }
    }, [actions, canvasItems, nodes, page.id, rootNodeId, state.ast]);

    // Keep handleSaveRef fresh for iframe interaction callbacks
    handleSaveRef.current = () => void handleSave();
    handleViewCodeRef.current = handleViewCode;

    // -----------------------------------------------------------------------
    // Keyboard shortcuts (save, undo, redo)
    // -----------------------------------------------------------------------

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            const mod = event.ctrlKey || event.metaKey;

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
    }, [handleSave, actions]);

    useEffect(() => {
        setSelectedElementPath([]);
    }, [selectedItemId]);

    useEffect(() => {
        if (selectedElement === null && editableElements[0]) {
            setSelectedElementPath(editableElements[0].path);
        }
    }, [editableElements, selectedElement]);

    // -----------------------------------------------------------------------
    // Sync AST to iframe
    // -----------------------------------------------------------------------

    useEffect(() => {
        const iframeDoc = iframeRef.current?.contentDocument;

        if (!iframeDoc || !iframeReadyRef.current) {
            return;
        }

        // For src-based mode (previewUrl), inject AST content into the theme page
        if (previewUrl) {
            syncAstToIframe(iframeDoc, nodes, rootNodeId, state.ast.css, state.ast.js);
        }
    }, [previewLoadedAt, nodes, rootNodeId, state.ast.css, state.ast.js, previewUrl]);

    // Scroll to newly added section after AST sync completes
    useEffect(() => {
        if (pendingScrollRef.current !== 'pending' || !selectedItemId) {
            return;
        }

        pendingScrollRef.current = null;
        scrollIframeToNode(selectedItemId);
    }, [nodes, selectedItemId, scrollIframeToNode]);

    // -----------------------------------------------------------------------
    // Overlay, interaction, and drag-drop setup
    // -----------------------------------------------------------------------

    useEffect(() => {
        const iframe = iframeRef.current;
        const overlayContainer = overlayContainerRef.current;

        if (!iframe || !overlayContainer || !iframeReadyRef.current) {
            return;
        }

        // Clean up previous instances
        overlayRef.current?.destroy();
        dragDropRef.current?.destroy();
        interactionRef.current?.destroy();

        // --- Overlay engine ---
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
            onStartDrag: (nodeId) => {
                dragDropRef.current?.startCanvasDrag(nodeId);
            },
            onSelect: (nodeId) => actions.setSelected([nodeId]),
            onViewCode: (nodeId) => handleViewCodeRef.current(nodeId),
        };

        const overlay = new BuilderOverlay(overlayContainer, iframe, overlayCb);
        overlayRef.current = overlay;

        // --- Drag-drop engine ---
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

        // --- Iframe interaction handler ---
        const interactionCb: IframeInteractionCallbacks = {
            onHover: (nodeId) => actions.setHovered(nodeId),
            onSelect: (nodeId) => {
                if (nodeId) {
                    actions.setSelected([nodeId]);
                } else {
                    actions.setSelected([]);
                }
            },
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
            onUndo: () => actions.undo(),
            onRedo: () => actions.redo(),
            onSave: () => handleSaveRef.current(),
        };

        const interaction = new IframeInteractionHandler(iframe, interactionCb);
        interaction.bind();
        interactionRef.current = interaction;

        return () => {
            overlay.destroy();
            dragDrop.destroy();
            interaction.destroy();
        };
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [previewLoadedAt]);

    // Keep subsystems in sync with latest state
    useEffect(() => {
        dragDropRef.current?.updateNodes(nodes);
    }, [nodes]);

    useEffect(() => {
        interactionRef.current?.setSelectedId(selectedItemId);
    }, [selectedItemId]);

    // Update overlay with latest state
    useEffect(() => {
        overlayRef.current?.update({
            hoveredId: events.hoveredId,
            selectedIds: events.selectedIds,
            dropIndicator,
            nodes,
            rootNodeId,
        });
    }, [events.hoveredId, events.selectedIds, dropIndicator, nodes, rootNodeId]);

    // Overlay container drag listeners — captures HTML5 drag events from the parent
    // document (e.g. dragging components from the sidebar) and maps them to iframe
    // coordinates for the drag-drop engine. This bridges the cross-document gap since
    // HTML5 drag events don't fire inside the iframe when dragging from the parent.
    useEffect(() => {
        const overlayContainer = overlayContainerRef.current;

        if (!overlayContainer) {
            return;
        }

        const handleDragOver = (e: DragEvent) => {
            e.preventDefault();

            if (e.dataTransfer) {
                e.dataTransfer.dropEffect = 'copy';
            }

            // Enable pointer events during drag so we capture the events
            overlayContainer.style.pointerEvents = 'auto';

            dragDropRef.current?.handleDragMove(e.clientX, e.clientY);
        };

        const handleDrop = (e: DragEvent) => {
            e.preventDefault();
            e.stopPropagation();

            dragDropRef.current?.handleDrop();
            overlayContainer.style.pointerEvents = 'none';
        };

        const handleDragLeave = (e: DragEvent) => {
            // Only trigger when leaving the overlay container entirely
            if (!overlayContainer.contains(e.relatedTarget as Node)) {
                overlayContainer.style.pointerEvents = 'none';
                actions.setDropIndicator(null);
            }
        };

        // dragend fires on the drag SOURCE (sidebar button) and bubbles to
        // document. This is the most reliable way to detect the end of a
        // cross-document drag over an iframe overlay where `drop` may never
        // fire. If performDrop already ran via the `drop` handler above, the
        // double-execution guard inside BuilderDragDrop prevents a second drop.
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
    }, [actions]);

    return (
        <ThemeCustomizerLayout
            title={`Builder: ${page.title}`}
            description="Page builder"
        >
            <TooltipProvider delayDuration={300}>
                {dirtyGuard.dialog}

                <div className="flex min-h-0 flex-1 flex-col">
                    <BuilderHeader
                        activeTheme={activeTheme}
                        pageTitle={page.title}
                        deviceMode={deviceMode}
                        leftPanelCollapsed={leftPanelCollapsed}
                        rightPanelCollapsed={rightPanelCollapsed}
                        isSaving={isSaving}
                        isDirty={isDirty}
                        canUndo={canUndo}
                        canRedo={canRedo}
                        backHref={page.editor_url}
                        viewHref={page.permalink_url}
                        onToggleLeftPanel={() => setLeftPanelCollapsed((v) => !v)}
                        onToggleRightPanel={() => setRightPanelCollapsed((v) => !v)}
                        onOpenMobileSidebar={() => setMobileSidebarOpen(true)}
                        onDeviceModeChange={setDeviceMode}
                        onSave={() => void handleSave()}
                        onUndo={() => actions.undo()}
                        onRedo={() => actions.redo()}
                    />

                    {/* Three-column layout */}
                    <div className="flex min-h-0 flex-1">
                        {/* Left panel: Components */}
                        <aside
                            className={cn(
                                'hidden shrink-0 border-r border-border/60 bg-background transition-[width] duration-200 ease-in-out lg:block',
                                leftPanelCollapsed ? 'w-0 overflow-hidden border-r-0' : 'w-[260px]',
                            )}
                        >
                            {!leftPanelCollapsed ? (
                                <div className="relative flex h-full flex-col">
                                    <BuilderComponentsPanel
                                        activeLibrary={activeLibrary}
                                        palette={palette}
                                        onActiveLibraryChange={setActiveLibrary}
                                        onAddLibraryItem={handleAddLibraryItem}
                                        onDragStartItem={handleDragStartLibraryItem}
                                    />
                                    {addedBadge ? (
                                        <div className="absolute inset-x-0 bottom-3 flex justify-center pointer-events-none z-10">
                                            <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-600 px-3 py-1 text-xs font-medium text-white shadow-md animate-in fade-in zoom-in-95 duration-200">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="size-3.5"><path fillRule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clipRule="evenodd" /></svg>
                                                {addedBadge} added
                                            </span>
                                        </div>
                                    ) : null}
                                </div>
                            ) : null}
                        </aside>

                        {/* Center: Canvas + Bottom toolbar */}
                        <div className="flex min-w-0 flex-1 flex-col">
                            <div className="relative min-h-0 flex-1 overflow-hidden bg-[#f0f2f5] p-1.5 sm:p-2 lg:p-3">
                                <BuilderPreviewPanel
                                    deviceMode={deviceMode}
                                    iframeRef={iframeRef}
                                    onLoad={() => {
                                        iframeReadyRef.current = true;
                                        setPreviewLoadedAt(Date.now());
                                    }}
                                    previewUrl={previewUrl}
                                    previewHtml={previewDocument || undefined}
                                    title={`Builder preview for ${page.title}`}
                                />
                                {/* Overlay container — positioned over the iframe */}
                                <div ref={overlayContainerRef} className="pointer-events-none absolute inset-0 z-50" />
                            </div>
                            <BuilderCanvasToolbar
                                canvasItems={canvasItems}
                                selectedItemId={selectedItemId}
                                selectedElement={selectedElement}
                                onSelectItem={(uid) => {
                                    actions.setSelected([uid]);
                                    scrollIframeToNode(uid);
                                }}
                                onMoveSelectedItem={handleMoveSelectedItem}
                                onDuplicateSelectedItem={handleDuplicateSelectedItem}
                                onRemoveSelectedItem={handleRemoveSelectedItem}
                            />
                        </div>

                        {/* Right panel: Properties */}
                        <aside
                            className={cn(
                                'hidden shrink-0 border-l border-border/60 bg-background transition-[width] duration-200 ease-in-out lg:block',
                                rightPanelCollapsed ? 'w-0 overflow-hidden border-l-0' : 'w-[280px]',
                            )}
                        >
                            {!rightPanelCollapsed ? (
                                <BuilderPropertiesPanel
                                    editableElements={editableElements}
                                    selectedElement={selectedElement}
                                    selectedItem={selectedItem}
                                    customCss={state.ast.css}
                                    customJs={state.ast.js}
                                    onSelectElement={setSelectedElementPath}
                                    onUpdateElementField={handleUpdateElementField}
                                    onUpdateElementStyle={handleUpdateElementStyle}
                                    onUpdateSelectedItemHtml={handleUpdateSelectedItemHtml}
                                    onCustomCssChange={(css) => actions.setCss(css)}
                                    onCustomJsChange={(js) => actions.setJs(js)}
                                />
                            ) : null}
                        </aside>
                    </div>
                </div>

                {/* Code editor dialog */}
                <Dialog open={codeDialogNodeId !== null} onOpenChange={(open) => { if (!open) setCodeDialogNodeId(null); }}>
                    <DialogContent className="flex h-[calc(100vh-4rem)] max-h-[calc(100vh-4rem)] flex-col sm:w-[min(calc(100vw-4rem),72rem)] sm:max-w-6xl">
                        <DialogHeader>
                            <DialogTitle>Edit Element Code</DialogTitle>
                            <DialogDescription>
                                View and edit the HTML source of the selected element.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="min-h-0 flex-1">
                            <MonacoEditor
                                value={codeDialogValue}
                                onChange={setCodeDialogValue}
                                language="html"
                                height="100%"
                            />
                        </div>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setCodeDialogNodeId(null)}>Cancel</Button>
                            <Button onClick={handleApplyCode}>Apply</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Mobile sidebar sheet */}
                <Sheet open={mobileSidebarOpen} onOpenChange={setMobileSidebarOpen}>
                    <SheetContent side="left" className="w-full p-0 sm:max-w-sm">
                        <SheetHeader className="border-b border-border/60 px-3 py-2">
                            <SheetTitle className="text-sm">Components</SheetTitle>
                            <SheetDescription>
                                Add sections and blocks to {page.title}.
                            </SheetDescription>
                        </SheetHeader>
                        <div className="min-h-0 flex-1 overflow-hidden">
                            <BuilderComponentsPanel
                                activeLibrary={activeLibrary}
                                palette={palette}
                                onActiveLibraryChange={setActiveLibrary}
                                onAddLibraryItem={handleAddLibraryItem}
                                onDragStartItem={handleDragStartLibraryItem}
                            />
                        </div>
                    </SheetContent>
                </Sheet>
            </TooltipProvider>
        </ThemeCustomizerLayout>
    );
}
