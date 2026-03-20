import type { ReactNode, RefObject } from 'react';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { GroupImperativeHandle } from 'react-resizable-panels';
import { BuilderCenterWorkspace } from '../../../components/builder/builder-center-workspace';
import { BuilderCodeEditorDialog } from '../../../components/builder/builder-code-editor-dialog';
import { BuilderHeader } from '../../../components/builder/builder-header';
import { BuilderLeftSidebar } from '../../../components/builder/builder-left-sidebar';
import { BuilderRightSidebar } from '../../../components/builder/builder-right-sidebar';
import type { BuilderEditableElement, BuilderElementStyleValues } from '../../../components/builder/builder-dom';
import type { BuilderDeviceMode } from '../../../components/builder/builder-utils';
import type { AstNodeId, AstNodeMap } from '../../../components/builder/core/ast-types';
import ThemeCustomizerLayout from '../../../components/theme-customizer/theme-customizer-layout';
import type { FooterEditorTab } from './edit-support';
import type { BuilderEditPageProps } from '../../../types/cms';

type BuilderEditLayoutProps = {
    activeLibrary: 'sections' | 'blocks';
    activeTheme: BuilderEditPageProps['activeTheme'];
    addedBadge: string | null;
    backHref: string;
    canRedo: boolean;
    canUndo: boolean;
    codeDialogNodeId: string | null;
    codeDialogValue: string;
    deviceMode: BuilderDeviceMode;
    dirtyDialog: ReactNode;
    footerEditorFullscreen: boolean;
    footerEditorIsDirty: boolean;
    footerEditorLanguage: 'html' | 'css' | 'js';
    footerEditorOpen: boolean;
    footerEditorTab: FooterEditorTab;
    footerEditorTitle: string;
    footerEditorValue: string;
    iframeRef: RefObject<HTMLIFrameElement | null>;
    inlineTextToolbar: ReactNode;
    isDirty: boolean;
    isSaving: boolean;
    leftPanelCollapsed: boolean;
    mobileSidebarOpen: boolean;
    nodes: AstNodeMap;
    onActiveLibraryChange: (library: 'sections' | 'blocks') => void;
    onAddLibraryItem: (item: BuilderEditPageProps['palette']['sections'][number]['items'][number]) => void;
    onApplyCode: () => void;
    onApplyFooterEditor: () => void;
    onCloseCodeDialog: () => void;
    onCloseFooterEditor: () => void;
    onDeviceModeChange: (mode: BuilderDeviceMode) => void;
    onDragStartItem: (item: BuilderEditPageProps['palette']['sections'][number]['items'][number]) => void;
    onFooterEditorValueChange: (value: string) => void;
    onOpenFooterEditor: (tab: FooterEditorTab) => void;
    onOpenMobileSidebar: () => void;
    onPanelLayoutChanged: (layout: { [panelId: string]: number }) => void;
    onPreviewLoad: () => void;
    onRedo: () => void;
    onSave: () => void;
    onSelectNode: (nodeId: AstNodeId) => void;
    onSetCodeDialogValue: (value: string) => void;
    onToggleFooterEditorFullscreen: () => void;
    onToggleLeftPanel: () => void;
    onToggleRightPanel: () => void;
    onUndo: () => void;
    overlayContainerRef: RefObject<HTMLDivElement | null>;
    page: BuilderEditPageProps['page'];
    palette: BuilderEditPageProps['palette'];
    panelGroupRef: RefObject<GroupImperativeHandle | null>;
    previewDocument: string;
    previewUrl: string | null;
    rightPanelCollapsed: boolean;
    rootNodeId: AstNodeId;
    selectedElement: BuilderEditableElement | null;
    selectedItemId: AstNodeId | null;
    setMobileSidebarOpen: (open: boolean) => void;
    onClearSelectedStyles: () => void;
    onDeleteNode: (nodeId: AstNodeId) => void;
    onDuplicateNode: (nodeId: AstNodeId) => void;
    onMoveNode: (nodeId: AstNodeId, direction: 'up' | 'down') => void;
    onUpdateElementField: (
        field: 'id' | 'className' | 'href' | 'textContent' | 'target' | 'rel' | 'buttonType' | 'disabled',
        value: string,
    ) => void;
    onUpdateElementInteractiveStyle: (
        stateKey: 'hoverStyles' | 'focusStyles',
        field: keyof BuilderElementStyleValues,
        value: string,
    ) => void;
    onUpdateElementStyle: (field: keyof BuilderElementStyleValues, value: string) => void;
    onUpdateElementStyles: (styles: Partial<BuilderElementStyleValues>) => void;
    viewHref: string | null;
};

export function BuilderEditLayout({
    activeLibrary,
    activeTheme,
    addedBadge,
    backHref,
    canRedo,
    canUndo,
    codeDialogNodeId,
    codeDialogValue,
    deviceMode,
    dirtyDialog,
    footerEditorFullscreen,
    footerEditorIsDirty,
    footerEditorLanguage,
    footerEditorOpen,
    footerEditorTab,
    footerEditorTitle,
    footerEditorValue,
    iframeRef,
    inlineTextToolbar,
    isDirty,
    isSaving,
    leftPanelCollapsed,
    mobileSidebarOpen,
    nodes,
    onActiveLibraryChange,
    onAddLibraryItem,
    onApplyCode,
    onApplyFooterEditor,
    onClearSelectedStyles,
    onCloseCodeDialog,
    onCloseFooterEditor,
    onDeleteNode,
    onDeviceModeChange,
    onDragStartItem,
    onDuplicateNode,
    onFooterEditorValueChange,
    onMoveNode,
    onOpenFooterEditor,
    onOpenMobileSidebar,
    onPanelLayoutChanged,
    onPreviewLoad,
    onRedo,
    onSave,
    onSelectNode,
    onSetCodeDialogValue,
    onToggleFooterEditorFullscreen,
    onToggleLeftPanel,
    onToggleRightPanel,
    onUndo,
    onUpdateElementField,
    onUpdateElementInteractiveStyle,
    onUpdateElementStyle,
    onUpdateElementStyles,
    overlayContainerRef,
    page,
    palette,
    panelGroupRef,
    previewDocument,
    previewUrl,
    rightPanelCollapsed,
    rootNodeId,
    selectedElement,
    selectedItemId,
    setMobileSidebarOpen,
    viewHref,
}: BuilderEditLayoutProps) {
    return (
        <ThemeCustomizerLayout
            title={`Builder: ${page.title}`}
            description="Page builder"
        >
            <TooltipProvider delayDuration={300}>
                {dirtyDialog}

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
                        backHref={backHref}
                        viewHref={viewHref}
                        onToggleLeftPanel={onToggleLeftPanel}
                        onToggleRightPanel={onToggleRightPanel}
                        onOpenMobileSidebar={onOpenMobileSidebar}
                        onDeviceModeChange={onDeviceModeChange}
                        onSave={onSave}
                        onUndo={onUndo}
                        onRedo={onRedo}
                    />

                    <ResizablePanelGroup
                        orientation="horizontal"
                        className="min-h-0 flex-1"
                        groupRef={panelGroupRef}
                        defaultLayout={{ left: 15, center: 70, right: 15 }}
                        onLayoutChanged={onPanelLayoutChanged}
                    >
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
                                    onActiveLibraryChange={onActiveLibraryChange}
                                    onAddLibraryItem={onAddLibraryItem}
                                    onDragStartItem={onDragStartItem}
                                />
                                {addedBadge ? (
                                    <div className="absolute inset-x-0 bottom-3 z-10 flex justify-center pointer-events-none">
                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-600 px-3 py-1 text-xs font-medium text-white shadow-md animate-in fade-in zoom-in-95 duration-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="size-3.5"><path fillRule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clipRule="evenodd" /></svg>
                                            {addedBadge} added
                                        </span>
                                    </div>
                                ) : null}
                            </div>
                        </ResizablePanel>
                        <ResizableHandle withHandle className="hidden lg:flex" />

                        <ResizablePanel id="center" defaultSize="70%" minSize="40%">
                            <BuilderCenterWorkspace
                                deviceMode={deviceMode}
                                iframeRef={iframeRef}
                                overlayContainerRef={overlayContainerRef}
                                previewUrl={previewUrl}
                                previewDocument={previewDocument}
                                pageTitle={page.title}
                                overlayContent={inlineTextToolbar}
                                footerEditorOpen={footerEditorOpen}
                                footerEditorFullscreen={footerEditorFullscreen}
                                footerEditorTab={footerEditorTab}
                                footerEditorTitle={footerEditorTitle}
                                footerEditorLanguage={footerEditorLanguage}
                                footerEditorValue={footerEditorValue}
                                footerEditorIsDirty={footerEditorIsDirty}
                                onPreviewLoad={onPreviewLoad}
                                onOpenFooterEditor={onOpenFooterEditor}
                                onCloseFooterEditor={onCloseFooterEditor}
                                onToggleFooterEditorFullscreen={onToggleFooterEditorFullscreen}
                                onFooterEditorValueChange={onFooterEditorValueChange}
                                onApplyFooterEditor={onApplyFooterEditor}
                            />
                        </ResizablePanel>

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
                                onClearSelectedStyles={onClearSelectedStyles}
                                onUpdateElementField={onUpdateElementField}
                                onUpdateElementStyle={onUpdateElementStyle}
                                onUpdateElementStyles={onUpdateElementStyles}
                                onUpdateElementInteractiveStyle={onUpdateElementInteractiveStyle}
                                onSelectNode={onSelectNode}
                                onMoveNode={onMoveNode}
                                onDuplicateNode={onDuplicateNode}
                                onDeleteNode={onDeleteNode}
                            />
                        </ResizablePanel>
                    </ResizablePanelGroup>
                </div>

                <BuilderCodeEditorDialog
                    open={codeDialogNodeId !== null}
                    value={codeDialogValue}
                    onChange={onSetCodeDialogValue}
                    onClose={onCloseCodeDialog}
                    onApply={onApplyCode}
                />

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
                                onActiveLibraryChange={onActiveLibraryChange}
                                onAddLibraryItem={onAddLibraryItem}
                                onDragStartItem={onDragStartItem}
                            />
                        </div>
                    </SheetContent>
                </Sheet>
            </TooltipProvider>
        </ThemeCustomizerLayout>
    );
}
