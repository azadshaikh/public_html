import {
    BracesIcon,
    CodeXmlIcon,
    ExpandIcon,
    Minimize2Icon,
    PaintbrushIcon,
    XIcon,
} from 'lucide-react';
import type { ReactNode, RefObject } from 'react';
import { MonacoEditor } from '@/components/code-editor/monaco-editor';
import { Button } from '@/components/ui/button';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { BuilderPreviewPanel } from './builder-preview-panel';
import type { BuilderDeviceMode } from './builder-utils';

type FooterEditorTab = 'html' | 'css' | 'js';

type BuilderCenterWorkspaceProps = {
    deviceMode: BuilderDeviceMode;
    iframeRef: RefObject<HTMLIFrameElement | null>;
    overlayContainerRef: RefObject<HTMLDivElement | null>;
    previewUrl: string | null;
    previewDocument: string;
    pageTitle: string;
    overlayContent?: ReactNode;
    footerEditorOpen: boolean;
    footerEditorFullscreen: boolean;
    footerEditorTab: FooterEditorTab;
    footerEditorTitle: string;
    footerEditorLanguage: 'html' | 'css' | 'js';
    footerEditorValue: string;
    footerEditorIsDirty: boolean;
    onPreviewLoad: () => void;
    onOpenFooterEditor: (tab: FooterEditorTab) => void;
    onCloseFooterEditor: () => void;
    onToggleFooterEditorFullscreen: () => void;
    onFooterEditorValueChange: (value: string) => void;
    onApplyFooterEditor: () => void;
};

export function BuilderCenterWorkspace({
    deviceMode,
    iframeRef,
    overlayContainerRef,
    previewUrl,
    previewDocument,
    pageTitle,
    overlayContent,
    footerEditorOpen,
    footerEditorFullscreen,
    footerEditorTab,
    footerEditorTitle,
    footerEditorLanguage,
    footerEditorValue,
    footerEditorIsDirty,
    onPreviewLoad,
    onOpenFooterEditor,
    onCloseFooterEditor,
    onToggleFooterEditorFullscreen,
    onFooterEditorValueChange,
    onApplyFooterEditor,
}: BuilderCenterWorkspaceProps) {
    return (
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
                                    onClick={onApplyFooterEditor}
                                    disabled={!footerEditorIsDirty}
                                    className="h-6 px-2.5 text-[11px]"
                                >
                                    Apply
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    onClick={onToggleFooterEditorFullscreen}
                                    aria-label="Exit fullscreen editor"
                                >
                                    <Minimize2Icon className="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    onClick={onCloseFooterEditor}
                                    aria-label="Close editor"
                                >
                                    <XIcon className="size-3.5" />
                                </Button>
                            </div>
                        </div>
                        <div className="min-h-0 flex-1" data-builder-shortcut-scope="footer-code">
                            <MonacoEditor
                                value={footerEditorValue}
                                onChange={onFooterEditorValueChange}
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
                                    <PreviewSurface
                                        deviceMode={deviceMode}
                                        iframeRef={iframeRef}
                                        overlayContainerRef={overlayContainerRef}
                                        previewUrl={previewUrl}
                                        previewDocument={previewDocument}
                                        pageTitle={pageTitle}
                                        overlayContent={overlayContent}
                                        onPreviewLoad={onPreviewLoad}
                                    />
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
                                                    onClick={onApplyFooterEditor}
                                                    disabled={!footerEditorIsDirty}
                                                    className="h-6 px-2.5 text-[11px]"
                                                >
                                                    Apply
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={onToggleFooterEditorFullscreen}
                                                    aria-label="Expand editor"
                                                >
                                                    <ExpandIcon className="size-3.5" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={onCloseFooterEditor}
                                                    aria-label="Close editor"
                                                >
                                                    <XIcon className="size-3.5" />
                                                </Button>
                                            </div>
                                        </div>
                                        <div className="min-h-0 flex-1" data-builder-shortcut-scope="footer-code">
                                            <MonacoEditor
                                                value={footerEditorValue}
                                                onChange={onFooterEditorValueChange}
                                                language={footerEditorLanguage}
                                                height="100%"
                                            />
                                        </div>
                                    </div>
                                </ResizablePanel>
                            </ResizablePanelGroup>
                        ) : (
                            <PreviewSurface
                                deviceMode={deviceMode}
                                iframeRef={iframeRef}
                                overlayContainerRef={overlayContainerRef}
                                previewUrl={previewUrl}
                                previewDocument={previewDocument}
                                pageTitle={pageTitle}
                                overlayContent={overlayContent}
                                onPreviewLoad={onPreviewLoad}
                            />
                        )}
                    </>
                )}

                <div className="flex items-center justify-between border-t border-border/60 bg-background px-2 py-1">
                    <div className="flex items-center gap-1">
                        <Button
                            variant={footerEditorOpen && footerEditorTab === 'html' ? 'secondary' : 'ghost'}
                            size="sm"
                            onClick={() => onOpenFooterEditor('html')}
                            className="h-6 gap-1 px-2 text-[11px]"
                        >
                            <CodeXmlIcon className="size-3" />
                            HTML
                        </Button>
                        <Button
                            variant={footerEditorOpen && footerEditorTab === 'css' ? 'secondary' : 'ghost'}
                            size="sm"
                            onClick={() => onOpenFooterEditor('css')}
                            className="h-6 gap-1 px-2 text-[11px]"
                        >
                            <PaintbrushIcon className="size-3" />
                            CSS
                        </Button>
                        <Button
                            variant={footerEditorOpen && footerEditorTab === 'js' ? 'secondary' : 'ghost'}
                            size="sm"
                            onClick={() => onOpenFooterEditor('js')}
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
    );
}

function PreviewSurface({
    deviceMode,
    iframeRef,
    overlayContainerRef,
    previewUrl,
    previewDocument,
    pageTitle,
    overlayContent,
    onPreviewLoad,
}: {
    deviceMode: BuilderDeviceMode;
    iframeRef: RefObject<HTMLIFrameElement | null>;
    overlayContainerRef: RefObject<HTMLDivElement | null>;
    previewUrl: string | null;
    previewDocument: string;
    pageTitle: string;
    overlayContent?: ReactNode;
    onPreviewLoad: () => void;
}) {
    return (
        <div className="relative h-full min-h-0 overflow-hidden bg-[#f0f2f5] p-1.5 sm:p-2 lg:bg-transparent lg:p-0">
            <BuilderPreviewPanel
                deviceMode={deviceMode}
                iframeRef={iframeRef}
                onLoad={onPreviewLoad}
                previewUrl={previewUrl}
                previewHtml={previewDocument || undefined}
                title={`Builder preview for ${pageTitle}`}
            />
            <div ref={overlayContainerRef} className="pointer-events-none absolute inset-0 z-50" />
            <div className="pointer-events-none absolute inset-0 z-[60]">
                {overlayContent}
            </div>
        </div>
    );
}