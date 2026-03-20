'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { type GroupImperativeHandle } from 'react-resizable-panels';
import { showAppToast } from '@/components/forms/form-success-toast';
import { useDirtyFormGuard } from '@/hooks/use-dirty-form-guard';
import type { BuilderEditableElement, BuilderElementStyleValues } from '../../../components/builder/builder-dom';
import {
    buildPreviewDocument,
    extractErrorMessage,
    getCsrfToken,
    type BuilderDeviceMode,
} from '../../../components/builder/builder-utils';
import { ROOT_NODE_ID } from '../../../components/builder/core/ast-types';
import { getNode, serializePageAst } from '../../../components/builder/core/ast-helpers';
import { buildEffectivePageCss, renderCleanNodeToHtml, renderCleanPageContent } from '../../../components/builder/core/ast-to-html';
import { useBuilderStore } from '../../../components/builder/core/use-builder-store';
import { getElementByAstId } from '../../../components/builder/core/iframe-sync';
import {
    astToCanvasItems,
    buildInitialAst,
    BUILDER_PANEL_LAYOUT_STORAGE_KEY,
    BUILDER_PANEL_WIDTHS_STORAGE_KEY,
    clampSidebarPercentage,
    DEFAULT_LEFT_PANEL_SIZE,
    DEFAULT_RIGHT_PANEL_SIZE,
    formatHtmlForDisplay,
    isStoredPanelLayout,
    normalizePanelLayout,
    toEditableElement,
    type BuilderPanelLayout,
    type FooterEditorDrafts,
} from './edit-support';
import { BuilderEditLayout } from './builder-edit-layout';
import { useBuilderCanvasRuntime } from './use-builder-canvas-runtime';
import { useBuilderFooterEditor } from './use-builder-footer-editor';
import { useBuilderInlineTextEditor } from './use-builder-inline-text-editor';
import type {
    BuilderEditPageProps,
    BuilderLibraryItem,
} from '../../../types/cms';

export default function BuilderEditPage({
    activeTheme,
    palette,
    builderState,
    page,
    pickerFilters,
    pickerMedia,
    pickerStatistics,
    uploadSettings,
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

    const initialAst = useMemo(
        () => buildInitialAst(
            builderState.items,
            builderState.css || page.css || '',
            builderState.js || page.js || '',
        ),
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

    const previewUrl = useMemo(() => {
        if (!page.permalink_url) {
            return null;
        }

        const separator = page.permalink_url.includes('?') ? '&' : '?';

        return `${page.permalink_url}${separator}editor_preview=1&r=${Date.now()}`;
    }, [page.permalink_url]);

    const canvasItems = useMemo(() => astToCanvasItems(nodes, rootNodeId), [nodes, rootNodeId]);
    const selectedItemId = events.selectedIds[0] ?? null;
    const selectedElement = useMemo<BuilderEditableElement | null>(() => {
        return selectedNode ? toEditableElement(selectedNode) : null;
    }, [selectedNode]);
    const previewDocument = useMemo(
        () => (previewUrl ? '' : buildPreviewDocument(canvasItems, buildEffectivePageCss(nodes, state.ast.css), state.ast.js)),
        [canvasItems, nodes, previewUrl, state.ast.css, state.ast.js],
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

    const dirtyGuard = useDirtyFormGuard({
        enabled: isDirty,
    });

    const {
        footerEditorFullscreen,
        footerEditorIsDirty,
        footerEditorLanguage,
        footerEditorOpen,
        footerEditorTab,
        footerEditorTitle,
        footerEditorValue,
        handleApplyFooterEditor,
        handleCloseFooterEditor,
        handleFooterEditorValueChange,
        handleOpenFooterEditor,
        handleToggleFooterEditorFullscreen,
    } = useBuilderFooterEditor({
        actions,
        currentCss: state.ast.css,
        currentJs: state.ast.js,
        footerEditorSources,
    });

    const {
        canNodeEditInlineText,
        editingTextNodeId,
        inlineTextToolbar,
        requestInlineTextEdit,
    } = useBuilderInlineTextEditor({
        actions,
        iframeReadyRef,
        iframeRef,
        nodes,
        nodesRef,
        overlayContainerRef,
        selectedItemId,
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

    const pendingScrollRef = useRef<string | null>(null);

    const scrollIframeToNode = useCallback((nodeId: string) => {
        requestAnimationFrame(() => {
            const iframeDoc = iframeRef.current?.contentDocument;

            if (!iframeDoc) {
                return;
            }

            const element = getElementByAstId(iframeDoc, nodeId);
            element?.scrollIntoView({ behavior: 'smooth', block: 'center' });
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

    const handleMoveNode = useCallback((nodeId: string, direction: 'up' | 'down') => {
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
    }, [actions, nodes, scrollIframeToNode]);

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

    const handleViewCode = useCallback((nodeId: string) => {
        const html = renderCleanNodeToHtml(nodes, nodeId);
        setCodeDialogValue(formatHtmlForDisplay(html));
        setCodeDialogNodeId(nodeId);
    }, [nodes]);

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

    const handleUpdateElementField = useCallback((field: 'id' | 'className' | 'href' | 'textContent' | 'target' | 'rel' | 'buttonType' | 'disabled' | 'src' | 'alt', value: string) => {
        if (!selectedItemId) {
            return;
        }

        if (field === 'id') {
            actions.updateNode(selectedItemId, { props: { attr_id: value } });

            return;
        }

        if (field === 'href') {
            actions.updateNode(selectedItemId, { props: { href: value } });

            return;
        }

        if (field === 'src') {
            actions.updateNode(selectedItemId, { props: { src: value } });

            return;
        }

        if (field === 'alt') {
            actions.updateNode(selectedItemId, { props: { alt: value } });

            return;
        }

        if (field === 'textContent') {
            actions.updateNode(selectedItemId, { props: { content: value } });

            return;
        }

        if (field === 'target') {
            actions.updateNode(selectedItemId, { props: { attr_target: value } });

            return;
        }

        if (field === 'rel') {
            actions.updateNode(selectedItemId, { props: { attr_rel: value } });

            return;
        }

        if (field === 'buttonType') {
            actions.updateNode(selectedItemId, { props: { attr_type: value } });

            return;
        }

        if (field === 'disabled') {
            actions.updateNode(selectedItemId, { props: { attr_disabled: value === '' ? '' : 'true' } });

            return;
        }

        actions.updateNode(selectedItemId, {
            className: value,
        });
    }, [actions, selectedItemId]);

    const handleUpdateElementInteractiveStyle = useCallback((stateKey: 'hoverStyles' | 'focusStyles', field: keyof BuilderElementStyleValues, value: string) => {
        if (!selectedItemId || !selectedNode) {
            return;
        }

        const existingValue = selectedNode.props[stateKey];
        const nextStyles = typeof existingValue === 'object' && existingValue !== null
            ? { ...(existingValue as Record<string, string>) }
            : {};

        if (value.trim() === '') {
            delete nextStyles[field];
        } else {
            nextStyles[field] = value;
        }

        actions.updateNode(selectedItemId, {
            props: {
                [stateKey]: nextStyles,
            },
        });
    }, [actions, selectedItemId, selectedNode]);

    const handleUpdateElementStyle = useCallback((field: keyof BuilderElementStyleValues, value: string) => {
        if (!selectedItemId) {
            return;
        }

        actions.updateNode(selectedItemId, {
            styles: { [field]: value },
        });
    }, [actions, selectedItemId]);

    const handleUpdateElementStyles = useCallback((styles: Partial<BuilderElementStyleValues>) => {
        if (!selectedItemId) {
            return;
        }

        actions.updateNode(selectedItemId, { styles });
    }, [actions, selectedItemId]);

    const handleClearSelectedStyles = useCallback((): void => {
        if (!selectedItemId || !selectedNode) {
            return;
        }

        const nextProps = { ...selectedNode.props };

        delete nextProps.hoverStyles;
        delete nextProps.focusStyles;

        actions.setAst({
            ...state.ast,
            nodes: {
                ...state.ast.nodes,
                [selectedItemId]: {
                    ...selectedNode,
                    props: nextProps,
                    styles: {},
                },
            },
        });
    }, [actions, selectedItemId, selectedNode, state.ast]);

    const handlePreviewLoad = useCallback((): void => {
        iframeReadyRef.current = true;
        setPreviewLoadedAt(Date.now());
    }, []);

    const handleSave = useCallback(async () => {
        setIsSaving(true);

        const flattenedHtml = renderCleanPageContent(nodes, rootNodeId);
        const effectiveCss = buildEffectivePageCss(nodes, state.ast.css);

        const payload = {
            content: flattenedHtml,
            css: effectiveCss,
            js: state.ast.js,
            format: 'pagebuilder',
            builder_state: {
                css: state.ast.css,
                js: state.ast.js,
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
                description: error instanceof Error
                    ? error.message
                    : 'The builder could not save your changes.',
            });
        } finally {
            setIsSaving(false);
        }
    }, [actions, canvasItems, nodes, page.id, rootNodeId, state.ast]);

    const { startPanelDrag } = useBuilderCanvasRuntime({
        actions,
        canNodeEditInlineText,
        dropIndicator,
        editingTextNodeId,
        handleApplyCode,
        handleApplyFooterEditor,
        handleRequestInlineTextEdit: requestInlineTextEdit,
        handleSave,
        hoveredId: events.hoveredId,
        iframeReadyRef,
        iframeRef,
        nodes,
        nodesRef,
        onViewCode: handleViewCode,
        overlayContainerRef,
        previewLoadedAt,
        previewUrl,
        rootNodeId,
        scrollIframeToNode,
        selectedIds: events.selectedIds,
        selectedItemId,
        stateCss: state.ast.css,
        stateJs: state.ast.js,
    });

    const handleDragStartLibraryItem = useCallback((item: BuilderLibraryItem) => {
        startPanelDrag(item.html, activeLibrary === 'sections' ? 'section' : 'block', item.name);
    }, [activeLibrary, startPanelDrag]);

    useEffect(() => {
        if (pendingScrollRef.current !== 'pending' || !selectedItemId) {
            return;
        }

        pendingScrollRef.current = null;
        scrollIframeToNode(selectedItemId);
    }, [nodes, scrollIframeToNode, selectedItemId]);

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
        <BuilderEditLayout
            activeLibrary={activeLibrary}
            activeTheme={activeTheme}
            addedBadge={addedBadge}
            backHref={page.editor_url}
            canRedo={canRedo}
            canUndo={canUndo}
            codeDialogNodeId={codeDialogNodeId}
            codeDialogValue={codeDialogValue}
            deviceMode={deviceMode}
            dirtyDialog={dirtyGuard.dialog}
            footerEditorFullscreen={footerEditorFullscreen}
            footerEditorIsDirty={footerEditorIsDirty}
            footerEditorLanguage={footerEditorLanguage}
            footerEditorOpen={footerEditorOpen}
            footerEditorTab={footerEditorTab}
            footerEditorTitle={footerEditorTitle}
            footerEditorValue={footerEditorValue}
            iframeRef={iframeRef}
            inlineTextToolbar={inlineTextToolbar}
            isDirty={isDirty}
            isSaving={isSaving}
            leftPanelCollapsed={leftPanelCollapsed}
            mobileSidebarOpen={mobileSidebarOpen}
            nodes={nodes}
            onActiveLibraryChange={setActiveLibrary}
            onAddLibraryItem={handleAddLibraryItem}
            onApplyCode={handleApplyCode}
            onApplyFooterEditor={handleApplyFooterEditor}
            onClearSelectedStyles={handleClearSelectedStyles}
            onCloseCodeDialog={() => setCodeDialogNodeId(null)}
            onCloseFooterEditor={handleCloseFooterEditor}
            onDeleteNode={handleRemoveNode}
            onDeviceModeChange={setDeviceMode}
            onDragStartItem={handleDragStartLibraryItem}
            onDuplicateNode={handleDuplicateNode}
            onFooterEditorValueChange={handleFooterEditorValueChange}
            onMoveNode={handleMoveNode}
            onOpenFooterEditor={handleOpenFooterEditor}
            onOpenMobileSidebar={() => setMobileSidebarOpen(true)}
            onPanelLayoutChanged={handlePanelLayoutChanged}
            onPreviewLoad={handlePreviewLoad}
            onRedo={() => actions.redo()}
            onSave={() => void handleSave()}
            onSelectNode={handleSelectNode}
            onSetCodeDialogValue={setCodeDialogValue}
            onToggleFooterEditorFullscreen={handleToggleFooterEditorFullscreen}
            onToggleLeftPanel={handleToggleLeftPanel}
            onToggleRightPanel={handleToggleRightPanel}
            onUndo={() => actions.undo()}
            onUpdateElementField={handleUpdateElementField}
            onUpdateElementInteractiveStyle={handleUpdateElementInteractiveStyle}
            onUpdateElementStyle={handleUpdateElementStyle}
            onUpdateElementStyles={handleUpdateElementStyles}
            overlayContainerRef={overlayContainerRef}
            page={page}
            palette={palette}
            pickerAction={route('cms.builder.edit', page.id)}
            pickerFilters={pickerFilters}
            pickerMedia={pickerMedia}
            pickerStatistics={pickerStatistics}
            panelGroupRef={panelGroupRef}
            previewDocument={previewDocument}
            previewUrl={previewUrl}
            rightPanelCollapsed={rightPanelCollapsed}
            rootNodeId={rootNodeId}
            selectedElement={selectedElement}
            selectedItemId={selectedItemId}
            setMobileSidebarOpen={setMobileSidebarOpen}
            uploadSettings={uploadSettings}
            viewHref={page.permalink_url}
        />
    );
}
