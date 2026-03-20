'use client';

import {
    BracesIcon,
    CodeXmlIcon,
    ExpandIcon,
    Minimize2Icon,
    PaintbrushIcon,
    XIcon,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { MonacoEditor } from '@/components/code-editor/monaco-editor';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
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
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { type GroupImperativeHandle } from 'react-resizable-panels';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useDirtyFormGuard } from '@/hooks/use-dirty-form-guard';
import { cn } from '@/lib/utils';
import { BuilderLeftSidebar } from '../../../components/builder/builder-left-sidebar';
import { BuilderHeader } from '../../../components/builder/builder-header';
import {
    type BuilderEditableElement,
    type BuilderElementStyleValues,
} from '../../../components/builder/builder-dom';
import { BuilderPreviewPanel } from '../../../components/builder/builder-preview-panel';
import { BuilderRightSidebar } from '../../../components/builder/builder-right-sidebar';
import {
    buildPreviewDocument,
    extractErrorMessage,
    getCsrfToken,
    type BuilderDeviceMode,
} from '../../../components/builder/builder-utils';
import { ROOT_NODE_ID, type AstNode } from '../../../components/builder/core/ast-types';
import { parseHtmlToAst, createEmptyPageAst, getNode, serializePageAst } from '../../../components/builder/core/ast-helpers';
import { renderCleanNodeToHtml, renderCleanPageContent, renderNodeToHtml } from '../../../components/builder/core/ast-to-html';
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

const BUILDER_PANEL_LAYOUT_STORAGE_KEY = 'cms-builder-panel-layout';
const BUILDER_PANEL_WIDTHS_STORAGE_KEY = 'cms-builder-panel-widths';
const DEFAULT_LEFT_PANEL_SIZE = 15;
const DEFAULT_RIGHT_PANEL_SIZE = 15;
const MIN_SIDEBAR_PERCENTAGE = 12;
const MAX_SIDEBAR_PERCENTAGE = 25;

type BuilderPanelLayout = {
    left: number;
    center: number;
    right: number;
};

type FooterEditorTab = 'html' | 'css' | 'js';
type FooterEditorDrafts = Record<FooterEditorTab, string>;

function clampSidebarPercentage(value: number): number {
    return Math.min(MAX_SIDEBAR_PERCENTAGE, Math.max(MIN_SIDEBAR_PERCENTAGE, value));
}

function normalizePanelLayout(layout?: Partial<BuilderPanelLayout> | null): BuilderPanelLayout {
    const left = layout?.left === 0 ? 0 : clampSidebarPercentage(layout?.left ?? DEFAULT_LEFT_PANEL_SIZE);
    const right = layout?.right === 0 ? 0 : clampSidebarPercentage(layout?.right ?? DEFAULT_RIGHT_PANEL_SIZE);

    return {
        left,
        center: 100 - left - right,
        right,
    };
}

function isStoredPanelLayout(value: unknown): value is Partial<BuilderPanelLayout> {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    return ['left', 'center', 'right'].some((key) => typeof candidate[key] === 'number');
}

function buildAstFromPageContent(html: string, css: string, js: string) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(`<body>${html}</body>`, 'text/html');
    const ast = createEmptyPageAst();

    ast.css = css;
    ast.js = js;

    for (const child of Array.from(doc.body.children)) {
        const parsed = parseHtmlToAst(child.outerHTML, 'section');
        const parsedRoot = parsed.nodes[parsed.rootId];

        if (!parsedRoot) {
            continue;
        }

        parsedRoot.parentId = ROOT_NODE_ID;
        ast.nodes[parsed.rootId] = parsedRoot;
        ast.nodes[ROOT_NODE_ID].childIds.push(parsed.rootId);

        for (const [id, node] of Object.entries(parsed.nodes)) {
            if (id !== parsed.rootId) {
                ast.nodes[id] = node;
            }
        }
    }

    return ast;
}

function toEditableElement(node: AstNode): BuilderEditableElement {
    const tagName = (node.tagName ?? node.type).toLowerCase();

    return {
        alt: typeof node.props.alt === 'string' ? node.props.alt : '',
        canEditText: typeof node.props.content === 'string' || tagName === 'a' || tagName === 'button',
        className: node.className,
        href: typeof node.props.href === 'string' ? node.props.href : '',
        id: typeof node.props.attr_id === 'string' ? node.props.attr_id : '',
        isImage: node.type === 'image',
        isLink: node.type === 'link' || tagName === 'a',
        label: node.displayName,
        path: [],
        pathKey: node.id,
        src: typeof node.props.src === 'string' ? node.props.src : '',
        styles: {
            backgroundColor: node.styles.backgroundColor ?? '',
            borderRadius: node.styles.borderRadius ?? '',
            color: node.styles.color ?? '',
            fontSize: node.styles.fontSize ?? '',
            fontWeight: node.styles.fontWeight ?? '',
            height: node.styles.height ?? '',
            marginBottom: node.styles.marginBottom ?? '',
            marginLeft: node.styles.marginLeft ?? '',
            marginRight: node.styles.marginRight ?? '',
            marginTop: node.styles.marginTop ?? '',
            opacity: node.styles.opacity ?? '',
            paddingBottom: node.styles.paddingBottom ?? '',
            paddingLeft: node.styles.paddingLeft ?? '',
            paddingRight: node.styles.paddingRight ?? '',
            paddingTop: node.styles.paddingTop ?? '',
            textAlign: node.styles.textAlign ?? '',
            width: node.styles.width ?? '',
        },
        tagName,
        textContent: typeof node.props.content === 'string' ? node.props.content : '',
    };
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
    const [footerEditorTab, setFooterEditorTab] = useState<FooterEditorTab>('html');
    const [footerEditorOpen, setFooterEditorOpen] = useState(false);
    const [footerEditorFullscreen, setFooterEditorFullscreen] = useState(false);
    const [footerEditorDrafts, setFooterEditorDrafts] = useState<FooterEditorDrafts>({
        html: '',
        css: '',
        js: '',
    });
    const iframeRef = useRef<HTMLIFrameElement | null>(null);
    const overlayContainerRef = useRef<HTMLDivElement | null>(null);
    const [previewLoadedAt, setPreviewLoadedAt] = useState(0);
    const iframeReadyRef = useRef(false);
    const [addedBadge, setAddedBadge] = useState<string | null>(null);
    const addedBadgeTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const panelGroupRef = useRef<GroupImperativeHandle | null>(null);
    const lastSidebarWidthsRef = useRef({
        left: DEFAULT_LEFT_PANEL_SIZE,
        right: DEFAULT_RIGHT_PANEL_SIZE,
    });

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

    const selectedElement = useMemo(() => {
        return selectedNode ? toEditableElement(selectedNode) : null;
    }, [selectedNode]);

    // Fallback standalone preview document (used when no permalink URL is available)
    const previewDocument = useMemo(
        () => (previewUrl ? '' : buildPreviewDocument(canvasItems, state.ast.css, state.ast.js)),
        [canvasItems, state.ast.css, state.ast.js, previewUrl],
    );
    const fullPageHtml = useMemo(
        () => formatHtmlForDisplay(renderCleanPageContent(nodes, rootNodeId)),
        [nodes, rootNodeId],
    );
    const footerEditorSources = useMemo<FooterEditorDrafts>(() => ({
        html: fullPageHtml,
        css: state.ast.css,
        js: state.ast.js,
    }), [fullPageHtml, state.ast.css, state.ast.js]);
    const previousFooterEditorSourcesRef = useRef<FooterEditorDrafts | null>(null);

    const dirtyGuard = useDirtyFormGuard({
        enabled: isDirty,
    });

    const handlePanelLayoutChanged = useCallback((layout: { [panelId: string]: number }): void => {
        const normalizedLayout = normalizePanelLayout(layout);

        setLeftPanelCollapsed(normalizedLayout.left === 0);
        setRightPanelCollapsed(normalizedLayout.right === 0);

        if (normalizedLayout.left > 0) {
            lastSidebarWidthsRef.current.left = normalizedLayout.left;
        }

        if (normalizedLayout.right > 0) {
            lastSidebarWidthsRef.current.right = normalizedLayout.right;
        }

        if (typeof window !== 'undefined') {
            window.localStorage.setItem(BUILDER_PANEL_LAYOUT_STORAGE_KEY, JSON.stringify(normalizedLayout));
            window.localStorage.setItem(BUILDER_PANEL_WIDTHS_STORAGE_KEY, JSON.stringify(lastSidebarWidthsRef.current));
        }
    }, []);

    const setPanelLayout = useCallback((layout: BuilderPanelLayout): void => {
        panelGroupRef.current?.setLayout(layout);
        handlePanelLayoutChanged(layout);
    }, [handlePanelLayoutChanged]);

    const handleToggleLeftPanel = useCallback((): void => {
        const currentLayout = normalizePanelLayout(panelGroupRef.current?.getLayout());

        if (currentLayout.left === 0) {
            setPanelLayout(normalizePanelLayout({
                left: lastSidebarWidthsRef.current.left,
                right: currentLayout.right,
            }));

            return;
        }

        setPanelLayout(normalizePanelLayout({
            left: 0,
            right: currentLayout.right,
        }));
    }, [setPanelLayout]);

    const handleToggleRightPanel = useCallback((): void => {
        const currentLayout = normalizePanelLayout(panelGroupRef.current?.getLayout());

        if (currentLayout.right === 0) {
            setPanelLayout(normalizePanelLayout({
                left: currentLayout.left,
                right: lastSidebarWidthsRef.current.right,
            }));

            return;
        }

        setPanelLayout(normalizePanelLayout({
            left: currentLayout.left,
            right: 0,
        }));
    }, [setPanelLayout]);

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
        actions.importHtml(item.html, ROOT_NODE_ID, undefined, item.name);
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

    const handleMoveNode = useCallback(
        (nodeId: string, direction: 'up' | 'down') => {
            if (!nodeId) {
                return;
            }

            const node = getNode(nodes, nodeId);

            if (!node?.parentId) {
                return;
            }

            const parent = nodes[node.parentId];

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
        [actions, nodes, scrollIframeToNode],
    );

    const handleDuplicateNode = useCallback((nodeId: string) => {
        actions.duplicateNode(nodeId);
    }, [actions]);

    const handleRemoveNode = useCallback((nodeId: string) => {
        actions.deleteNode(nodeId);
    }, [actions]);

    const handleSelectNode = useCallback((nodeId: string) => {
        actions.setSelected([nodeId]);
        scrollIframeToNode(nodeId);
    }, [actions, scrollIframeToNode]);

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

    const handleUpdateElementField = useCallback(
        (field: 'id' | 'className' | 'href' | 'textContent', value: string) => {
            if (!selectedItemId) {
                return;
            }

            if (field === 'id') {
                actions.updateNode(selectedItemId, {
                    props: { attr_id: value },
                });

                return;
            }

            if (field === 'href') {
                actions.updateNode(selectedItemId, {
                    props: { href: value },
                });

                return;
            }

            if (field === 'textContent') {
                actions.updateNode(selectedItemId, {
                    props: { content: value },
                });

                return;
            }

            actions.updateNode(selectedItemId, {
                className: value,
            });
        },
        [actions, selectedItemId],
    );

    const handleUpdateElementStyle = useCallback(
        (field: keyof BuilderElementStyleValues, value: string) => {
            if (!selectedItemId) {
                return;
            }

            actions.updateNode(selectedItemId, {
                styles: { [field]: value },
            });
        },
        [actions, selectedItemId],
    );

    const handleOpenFooterEditor = useCallback((tab: FooterEditorTab): void => {
        setFooterEditorTab(tab);
        setFooterEditorOpen(true);
        setFooterEditorFullscreen(false);
    }, []);

    const handleCloseFooterEditor = useCallback((): void => {
        setFooterEditorOpen(false);
        setFooterEditorFullscreen(false);
    }, []);

    const handleToggleFooterEditorFullscreen = useCallback((): void => {
        setFooterEditorFullscreen((value) => !value);
    }, []);

    const handleFooterEditorValueChange = useCallback((value: string): void => {
        setFooterEditorDrafts((current) => ({
            ...current,
            [footerEditorTab]: value,
        }));
    }, [footerEditorTab]);

    const handleApplyFooterEditor = useCallback((): void => {
        const currentDraft = footerEditorDrafts[footerEditorTab];

        if (footerEditorTab === 'css') {
            actions.setCss(currentDraft);

            return;
        }

        if (footerEditorTab === 'js') {
            actions.setJs(currentDraft);

            return;
        }

        const nextAst = buildAstFromPageContent(currentDraft, state.ast.css, state.ast.js);

        actions.setAst(nextAst);
        actions.clearSelection();
    }, [actions, footerEditorDrafts, footerEditorTab, state.ast.css, state.ast.js]);

    const footerEditorValue = footerEditorDrafts[footerEditorTab];

    const footerEditorIsDirty = useMemo(() => {
        return footerEditorValue !== footerEditorSources[footerEditorTab];
    }, [footerEditorSources, footerEditorTab, footerEditorValue]);

    const footerEditorLanguage = footerEditorTab === 'html' ? 'html' : footerEditorTab;

    const footerEditorTitle = footerEditorTab === 'html'
        ? 'HTML'
        : footerEditorTab === 'css'
          ? 'CSS'
          : 'JS';

    useEffect(() => {
        setFooterEditorDrafts((current) => {
            const previousSources = previousFooterEditorSourcesRef.current;
            const next = { ...current };

            for (const tab of ['html', 'css', 'js'] as FooterEditorTab[]) {
                if (
                    previousSources === null
                    || current[tab] === previousSources[tab]
                    || current[tab] === footerEditorSources[tab]
                ) {
                    next[tab] = footerEditorSources[tab];
                }
            }

            return next;
        });

        previousFooterEditorSourcesRef.current = footerEditorSources;
    }, [footerEditorSources]);

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

                        return;
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

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        try {
            const storedWidths = JSON.parse(window.localStorage.getItem(BUILDER_PANEL_WIDTHS_STORAGE_KEY) ?? 'null');

            if (storedWidths && typeof storedWidths === 'object') {
                const candidate = storedWidths as Record<string, unknown>;

                if (typeof candidate.left === 'number' && candidate.left > 0) {
                    lastSidebarWidthsRef.current.left = clampSidebarPercentage(candidate.left);
                }

                if (typeof candidate.right === 'number' && candidate.right > 0) {
                    lastSidebarWidthsRef.current.right = clampSidebarPercentage(candidate.right);
                }
            }

            const storedLayout = JSON.parse(window.localStorage.getItem(BUILDER_PANEL_LAYOUT_STORAGE_KEY) ?? 'null');

            if (isStoredPanelLayout(storedLayout)) {
                setPanelLayout(normalizePanelLayout(storedLayout));

                return;
            }
        } catch {
            // Ignore invalid localStorage state and fall back to defaults.
        }

        setPanelLayout(normalizePanelLayout());
    }, [setPanelLayout]);

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
                        onToggleLeftPanel={handleToggleLeftPanel}
                        onToggleRightPanel={handleToggleRightPanel}
                        onOpenMobileSidebar={() => setMobileSidebarOpen(true)}
                        onDeviceModeChange={setDeviceMode}
                        onSave={() => void handleSave()}
                        onUndo={() => actions.undo()}
                        onRedo={() => actions.redo()}
                    />

                    {/* Three-column layout with resizable panels */}
                    <ResizablePanelGroup
                        orientation="horizontal"
                        className="min-h-0 flex-1"
                        groupRef={panelGroupRef}
                        defaultLayout={normalizePanelLayout()}
                        onLayoutChanged={handlePanelLayoutChanged}
                    >
                        {/* Left panel: Components */}
                        <ResizablePanel
                            id="left"
                            defaultSize="15%"
                            minSize="240px"
                            maxSize="25%"
                            collapsible
                            collapsedSize={0}
                            className="hidden bg-background lg:block"
                        >
                            <div className="relative flex h-full flex-col">
                                <BuilderLeftSidebar
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
                        </ResizablePanel>
                        <ResizableHandle withHandle className="hidden lg:flex" />

                        {/* Center: Canvas */}
                        <ResizablePanel id="center" defaultSize="70%" minSize="40%">
                            <div className="flex h-full min-w-0 flex-col">
                                <div className="flex min-h-0 flex-1 flex-col overflow-hidden border border-border/60 border-b-0 bg-background">
                                    {footerEditorOpen && footerEditorFullscreen ? (
                                        <div className="flex min-h-0 flex-1 flex-col">
                                            <div className="flex items-center justify-between border-b border-border/60 px-2 py-1">
                                                <div className="flex items-center gap-1">
                                                    <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Editor</span>
                                                    <span className="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-foreground/80">
                                                        {footerEditorTitle}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={handleApplyFooterEditor}
                                                        disabled={!footerEditorIsDirty}
                                                        className="h-6 px-2.5 text-[11px]"
                                                    >
                                                        Apply
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        onClick={handleToggleFooterEditorFullscreen}
                                                        aria-label="Exit fullscreen editor"
                                                    >
                                                        <Minimize2Icon className="size-3.5" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        onClick={handleCloseFooterEditor}
                                                        aria-label="Close editor"
                                                    >
                                                        <XIcon className="size-3.5" />
                                                    </Button>
                                                </div>
                                            </div>
                                            <div className="min-h-0 flex-1" data-builder-shortcut-scope="footer-code">
                                                <MonacoEditor
                                                    value={footerEditorValue}
                                                    onChange={handleFooterEditorValueChange}
                                                    language={footerEditorLanguage}
                                                    height="100%"
                                                />
                                            </div>
                                        </div>
                                    ) : (
                                        <>
                                            {footerEditorOpen ? (
                                                <ResizablePanelGroup orientation="vertical" className="min-h-0 flex-1">
                                                    <ResizablePanel defaultSize="58%" minSize="25%">
                                                        <div className="relative h-full min-h-0 overflow-hidden bg-[#f0f2f5] p-1.5 sm:p-2 lg:bg-transparent lg:p-0">
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
                                                    </ResizablePanel>
                                                    <ResizableHandle withHandle />
                                                    <ResizablePanel defaultSize="42%" minSize="18%">
                                                        <div className="flex h-full min-h-0 flex-col border-t border-border/60">
                                                            <div className="flex items-center justify-between border-b border-border/60 px-2 py-1">
                                                                <div className="flex items-center gap-1">
                                                                    <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Editor</span>
                                                                    <span className="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-foreground/80">
                                                                        {footerEditorTitle}
                                                                    </span>
                                                                </div>
                                                                <div className="flex items-center gap-1">
                                                                    <Button
                                                                        variant="outline"
                                                                        size="sm"
                                                                        onClick={handleApplyFooterEditor}
                                                                        disabled={!footerEditorIsDirty}
                                                                        className="h-6 px-2.5 text-[11px]"
                                                                    >
                                                                        Apply
                                                                    </Button>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon-sm"
                                                                        onClick={handleToggleFooterEditorFullscreen}
                                                                        aria-label="Expand editor"
                                                                    >
                                                                        <ExpandIcon className="size-3.5" />
                                                                    </Button>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon-sm"
                                                                        onClick={handleCloseFooterEditor}
                                                                        aria-label="Close editor"
                                                                    >
                                                                        <XIcon className="size-3.5" />
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                            <div className="min-h-0 flex-1" data-builder-shortcut-scope="footer-code">
                                                                <MonacoEditor
                                                                    value={footerEditorValue}
                                                                    onChange={handleFooterEditorValueChange}
                                                                    language={footerEditorLanguage}
                                                                    height="100%"
                                                                />
                                                            </div>
                                                        </div>
                                                    </ResizablePanel>
                                                </ResizablePanelGroup>
                                            ) : (
                                                <div className="relative min-h-0 flex-1 overflow-hidden bg-[#f0f2f5] p-1.5 sm:p-2 lg:bg-transparent lg:p-0">
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
                                            )}
                                        </>
                                    )}

                                    <div className="flex items-center justify-between border-t border-border/60 bg-background px-2 py-1">
                                        <div className="flex items-center gap-1">
                                            <Button
                                                variant={footerEditorOpen && footerEditorTab === 'html' ? 'secondary' : 'ghost'}
                                                size="sm"
                                                onClick={() => handleOpenFooterEditor('html')}
                                                className="h-6 gap-1 px-2 text-[11px]"
                                            >
                                                <CodeXmlIcon className="size-3" />
                                                HTML
                                            </Button>
                                            <Button
                                                variant={footerEditorOpen && footerEditorTab === 'css' ? 'secondary' : 'ghost'}
                                                size="sm"
                                                onClick={() => handleOpenFooterEditor('css')}
                                                className="h-6 gap-1 px-2 text-[11px]"
                                            >
                                                <PaintbrushIcon className="size-3" />
                                                CSS
                                            </Button>
                                            <Button
                                                variant={footerEditorOpen && footerEditorTab === 'js' ? 'secondary' : 'ghost'}
                                                size="sm"
                                                onClick={() => handleOpenFooterEditor('js')}
                                                className="h-6 gap-1 px-2 text-[11px]"
                                            >
                                                <BracesIcon className="size-3" />
                                                JS
                                            </Button>
                                        </div>
                                        <span className="text-[10px] text-muted-foreground">
                                            {footerEditorOpen ? `${footerEditorTitle} editor open` : 'Open code editor'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </ResizablePanel>

                        {/* Right panel: Inspector */}
                        <ResizableHandle withHandle className="hidden lg:flex" />
                        <ResizablePanel
                            id="right"
                            defaultSize="15%"
                            minSize="240px"
                            maxSize="25%"
                            collapsible
                            collapsedSize={0}
                            className="hidden bg-background lg:block"
                        >
                            <BuilderRightSidebar
                                nodes={nodes}
                                rootNodeId={rootNodeId}
                                selectedNodeId={selectedItemId}
                                selectedElement={selectedElement}
                                onUpdateElementField={handleUpdateElementField}
                                onUpdateElementStyle={handleUpdateElementStyle}
                                onSelectNode={handleSelectNode}
                                onMoveNode={handleMoveNode}
                                onDuplicateNode={handleDuplicateNode}
                                onDeleteNode={handleRemoveNode}
                            />
                        </ResizablePanel>
                    </ResizablePanelGroup>
                </div>

                {/* Code editor dialog */}
                <Dialog open={codeDialogNodeId !== null} onOpenChange={(open) => { if (!open) setCodeDialogNodeId(null); }}>
                    <DialogContent className="flex h-[calc(100vh-4rem)] max-h-[calc(100vh-4rem)] flex-col gap-3 p-3 sm:w-[min(calc(100vw-4rem),72rem)] sm:max-w-6xl">
                        <DialogHeader className="gap-1">
                            <DialogTitle>Edit Element Code</DialogTitle>
                        </DialogHeader>
                        <div className="min-h-0 flex-1" data-builder-shortcut-scope="element-code">
                            <MonacoEditor
                                value={codeDialogValue}
                                onChange={setCodeDialogValue}
                                language="html"
                                height="100%"
                            />
                        </div>
                        <DialogFooter className="-mx-3 -mb-3 gap-2 rounded-b-xl border-t bg-muted/35 px-3 py-2 sm:px-3">
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
                            <BuilderLeftSidebar
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
