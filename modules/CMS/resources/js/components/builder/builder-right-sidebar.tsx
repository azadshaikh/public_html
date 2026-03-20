import { EyeIcon, Layers3Icon, PaintbrushIcon } from 'lucide-react';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { BuilderEditableElement, BuilderElementStyleValues } from './builder-dom';
import { StructureTab } from './builder-right-sidebar-structure-tab';
import { StyleTab } from './builder-right-sidebar-style-tab';
import type { AstNodeId, AstNodeMap } from './core/ast-types';

type BuilderRightSidebarProps = {
    nodes: AstNodeMap;
    rootNodeId: AstNodeId;
    selectedNodeId: AstNodeId | null;
    selectedElement: BuilderEditableElement | null;
    onClearSelectedStyles: () => void;
    onUpdateElementField: (
        field: 'id' | 'className' | 'href' | 'textContent' | 'target' | 'rel' | 'buttonType' | 'disabled',
        value: string,
    ) => void;
    onUpdateElementStyle: (
        field: keyof BuilderElementStyleValues,
        value: string,
    ) => void;
    onUpdateElementStyles: (styles: Partial<BuilderElementStyleValues>) => void;
    onUpdateElementInteractiveStyle: (
        stateKey: 'hoverStyles' | 'focusStyles',
        field: keyof BuilderElementStyleValues,
        value: string,
    ) => void;
    onSelectNode: (nodeId: AstNodeId) => void;
    onMoveNode: (nodeId: AstNodeId, direction: 'up' | 'down') => void;
    onDuplicateNode: (nodeId: AstNodeId) => void;
    onDeleteNode: (nodeId: AstNodeId) => void;
};

export function BuilderRightSidebar({
    nodes,
    rootNodeId,
    selectedNodeId,
    selectedElement,
    onClearSelectedStyles,
    onUpdateElementField,
    onUpdateElementStyle,
    onUpdateElementStyles,
    onUpdateElementInteractiveStyle,
    onSelectNode,
    onMoveNode,
    onDuplicateNode,
    onDeleteNode,
}: BuilderRightSidebarProps) {
    return (
        <div className="flex h-full flex-col">
            <Tabs defaultValue="style" className="flex min-h-0 flex-1 flex-col">
                <TabsList className="grid w-full shrink-0 grid-cols-2 rounded-none border-b border-border/60" variant="line">
                    <TabsTrigger value="style" className="gap-1.5 text-xs">
                        <PaintbrushIcon className="size-3.5" />
                        Style
                    </TabsTrigger>
                    <TabsTrigger value="layers" className="gap-1.5 text-xs">
                        <Layers3Icon className="size-3.5" />
                        Structure
                    </TabsTrigger>
                </TabsList>

                <ScrollArea className="min-h-0 flex-1">
                    <TabsContent value="style" className="mt-0">
                        {selectedElement ? (
                            <StyleTab
                                selectedElement={selectedElement}
                                onClearAllStyles={onClearSelectedStyles}
                                onUpdateElementField={onUpdateElementField}
                                onUpdateElementStyle={onUpdateElementStyle}
                                onUpdateElementStyles={onUpdateElementStyles}
                                onUpdateElementInteractiveStyle={onUpdateElementInteractiveStyle}
                            />
                        ) : (
                            <EmptySelectionState />
                        )}
                    </TabsContent>

                    <TabsContent value="layers" className="mt-0">
                        <StructureTab
                            nodes={nodes}
                            rootNodeId={rootNodeId}
                            selectedNodeId={selectedNodeId}
                            onSelectNode={onSelectNode}
                            onMoveNode={onMoveNode}
                            onDuplicateNode={onDuplicateNode}
                            onDeleteNode={onDeleteNode}
                        />
                    </TabsContent>
                </ScrollArea>
            </Tabs>
        </div>
    );
}

function EmptySelectionState() {
    return (
        <div className="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center">
            <div className="rounded-full bg-muted/50 p-3">
                <EyeIcon className="size-6 text-muted-foreground/50" />
            </div>
            <p className="text-sm font-medium text-muted-foreground">No element selected</p>
            <p className="text-xs text-muted-foreground/70">
                Select an item in the structure or click an element in the preview to inspect and edit it.
            </p>
        </div>
    );
}

