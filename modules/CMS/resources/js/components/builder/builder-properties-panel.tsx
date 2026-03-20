import {
    AlignCenterIcon,
    AlignJustifyIcon,
    AlignLeftIcon,
    AlignRightIcon,
    BoxIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    Code2Icon,
    EyeIcon,
    ImageIcon,
    LayoutIcon,
    LinkIcon,
    PaintbrushIcon,
    Settings2Icon,
    TypeIcon,
} from 'lucide-react';
import { useState } from 'react';
import { AceCodeEditor } from '@/components/code-editor/ace-editor';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type { BuilderCanvasItem } from '../../types/cms';
import type {
    BuilderEditableElement,
    BuilderElementPath,
    BuilderElementStyleValues,
} from './builder-dom';

type BuilderPropertiesPanelProps = {
    editableElements: BuilderEditableElement[];
    selectedElement: BuilderEditableElement | null;
    selectedItem: BuilderCanvasItem | null;
    customCss: string;
    customJs: string;
    onSelectElement: (path: BuilderElementPath) => void;
    onUpdateElementField: (
        field: 'textContent' | 'href' | 'src' | 'alt' | 'id' | 'className',
        value: string,
    ) => void;
    onUpdateElementStyle: (
        field: keyof BuilderElementStyleValues,
        value: string,
    ) => void;
    onUpdateSelectedItemHtml: (value: string) => void;
    onCustomCssChange: (value: string) => void;
    onCustomJsChange: (value: string) => void;
};

type CollapsibleSectionProps = {
    title: string;
    icon?: React.ReactNode;
    children: React.ReactNode;
    defaultOpen?: boolean;
};

function CollapsibleSection({ title, icon, children, defaultOpen = false }: CollapsibleSectionProps) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <div className="border-b border-border/50">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider transition-colors hover:bg-muted/40"
            >
                {open ? <ChevronDownIcon className="size-3.5" /> : <ChevronRightIcon className="size-3.5" />}
                {icon}
                <span className="flex-1">{title}</span>
            </button>
            {open ? <div className="px-3 pb-3">{children}</div> : null}
        </div>
    );
}

type StyleInputRowProps = {
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    suffix?: string;
    type?: string;
};

function StyleInputRow({ label, value, onChange, placeholder, suffix, type = 'text' }: StyleInputRowProps) {
    return (
        <div className="flex items-center gap-2">
            <label className="w-20 shrink-0 text-[11px] text-muted-foreground">{label}</label>
            <div className="relative flex-1">
                <input
                    type={type}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    className="h-7 w-full rounded-md border border-border/70 bg-background px-2 text-xs outline-none transition-colors focus:border-primary/50 focus:ring-1 focus:ring-primary/20"
                />
                {suffix ? (
                    <span className="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 text-[10px] text-muted-foreground">
                        {suffix}
                    </span>
                ) : null}
            </div>
        </div>
    );
}

type ColorInputRowProps = {
    label: string;
    value: string;
    onChange: (value: string) => void;
};

function ColorInputRow({ label, value, onChange }: ColorInputRowProps) {
    return (
        <div className="flex items-center gap-2">
            <label className="w-20 shrink-0 text-[11px] text-muted-foreground">{label}</label>
            <div className="flex flex-1 items-center gap-1.5">
                <input
                    type="color"
                    value={value || '#000000'}
                    onChange={(e) => onChange(e.target.value)}
                    className="size-7 shrink-0 cursor-pointer rounded border border-border/70 bg-background p-0.5"
                />
                <input
                    type="text"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder="rgba(0, 0, 0, 0)"
                    className="h-7 w-full rounded-md border border-border/70 bg-background px-2 text-xs outline-none transition-colors focus:border-primary/50 focus:ring-1 focus:ring-primary/20"
                />
            </div>
        </div>
    );
}

export function BuilderPropertiesPanel({
    editableElements,
    selectedElement,
    selectedItem,
    customCss,
    customJs,
    onSelectElement,
    onUpdateElementField,
    onUpdateElementStyle,
    onUpdateSelectedItemHtml,
    onCustomCssChange,
    onCustomJsChange,
}: BuilderPropertiesPanelProps) {
    if (!selectedItem || !selectedElement) {
        return (
            <div className="flex h-full flex-col">
                <div className="border-b border-border/60 px-3 py-2">
                    <div className="flex items-center gap-2">
                        <Settings2Icon className="size-4 text-muted-foreground" />
                        <span className="text-sm font-semibold">Properties</span>
                    </div>
                </div>
                <div className="flex flex-1 flex-col items-center justify-center gap-2 px-4 py-12 text-center">
                    <div className="rounded-full bg-muted/50 p-3">
                        <EyeIcon className="size-6 text-muted-foreground/50" />
                    </div>
                    <p className="text-sm font-medium text-muted-foreground">No element selected</p>
                    <p className="text-xs text-muted-foreground/70">
                        Click on an element in the preview to edit its properties.
                    </p>
                </div>

                <CodeSection
                    customCss={customCss}
                    customJs={customJs}
                    onCustomCssChange={onCustomCssChange}
                    onCustomJsChange={onCustomJsChange}
                />
            </div>
        );
    }

    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-border/60 px-3 py-2">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Settings2Icon className="size-4 text-muted-foreground" />
                        <span className="text-sm font-semibold">Properties</span>
                    </div>
                    <Badge variant="outline" className="text-[10px]">
                        {selectedElement.tagName}
                    </Badge>
                </div>
            </div>

            <Tabs defaultValue="content" className="flex min-h-0 flex-1 flex-col">
                <TabsList className="grid w-full shrink-0 grid-cols-4 rounded-none border-b border-border/60" variant="line">
                    <TabsTrigger value="content" className="text-xs">
                        Content
                    </TabsTrigger>
                    <TabsTrigger value="style" className="text-xs">
                        Style
                    </TabsTrigger>
                    <TabsTrigger value="advanced" className="text-xs">
                        Advanced
                    </TabsTrigger>
                    <TabsTrigger value="code" className="text-xs">
                        Code
                    </TabsTrigger>
                </TabsList>

                <ScrollArea className="min-h-0 flex-1">
                    <TabsContent value="content" className="mt-0">
                        <ContentTab
                            selectedElement={selectedElement}
                            onUpdateElementField={onUpdateElementField}
                        />
                    </TabsContent>

                    <TabsContent value="style" className="mt-0">
                        <StyleTab
                            selectedElement={selectedElement}
                            onUpdateElementStyle={onUpdateElementStyle}
                        />
                    </TabsContent>

                    <TabsContent value="advanced" className="mt-0">
                        <AdvancedTab
                            editableElements={editableElements}
                            selectedElement={selectedElement}
                            selectedItem={selectedItem}
                            onSelectElement={onSelectElement}
                            onUpdateElementField={onUpdateElementField}
                            onUpdateSelectedItemHtml={onUpdateSelectedItemHtml}
                        />
                    </TabsContent>

                    <TabsContent value="code" className="mt-0">
                        <CodeSection
                            customCss={customCss}
                            customJs={customJs}
                            onCustomCssChange={onCustomCssChange}
                            onCustomJsChange={onCustomJsChange}
                        />
                    </TabsContent>
                </ScrollArea>
            </Tabs>
        </div>
    );
}

function ContentTab({
    selectedElement,
    onUpdateElementField,
}: {
    selectedElement: BuilderEditableElement;
    onUpdateElementField: BuilderPropertiesPanelProps['onUpdateElementField'];
}) {
    return (
        <div className="flex flex-col">
            {selectedElement.canEditText ? (
                <CollapsibleSection title="Text" icon={<TypeIcon className="size-3" />} defaultOpen>
                    <Textarea
                        value={selectedElement.textContent}
                        onChange={(e) => onUpdateElementField('textContent', e.target.value)}
                        className="min-h-20 resize-y text-xs"
                        placeholder="Element text content"
                    />
                </CollapsibleSection>
            ) : null}

            {selectedElement.isLink ? (
                <CollapsibleSection title="Link" icon={<LinkIcon className="size-3" />} defaultOpen>
                    <div className="flex flex-col gap-2">
                        <StyleInputRow
                            label="URL"
                            value={selectedElement.href}
                            onChange={(v) => onUpdateElementField('href', v)}
                            placeholder="https://example.com"
                        />
                    </div>
                </CollapsibleSection>
            ) : null}

            {selectedElement.isImage ? (
                <CollapsibleSection title="Image" icon={<ImageIcon className="size-3" />} defaultOpen>
                    <div className="flex flex-col gap-2">
                        <StyleInputRow
                            label="Source"
                            value={selectedElement.src}
                            onChange={(v) => onUpdateElementField('src', v)}
                            placeholder="https://..."
                        />
                        <StyleInputRow
                            label="Alt text"
                            value={selectedElement.alt}
                            onChange={(v) => onUpdateElementField('alt', v)}
                            placeholder="Describe the image"
                        />
                    </div>
                </CollapsibleSection>
            ) : null}

            {!selectedElement.canEditText && !selectedElement.isLink && !selectedElement.isImage ? (
                <div className="flex flex-col items-center justify-center gap-2 px-4 py-8 text-center">
                    <p className="text-xs text-muted-foreground">
                        No editable content for <code className="rounded bg-muted px-1">{selectedElement.tagName}</code>
                    </p>
                </div>
            ) : null}
        </div>
    );
}

function StyleTab({
    selectedElement,
    onUpdateElementStyle,
}: {
    selectedElement: BuilderEditableElement;
    onUpdateElementStyle: BuilderPropertiesPanelProps['onUpdateElementStyle'];
}) {
    return (
        <div className="flex flex-col">
            <CollapsibleSection title="Display" icon={<LayoutIcon className="size-3" />} defaultOpen>
                <div className="flex flex-col gap-2">
                    <div className="flex items-center gap-2">
                        <label className="w-20 shrink-0 text-[11px] text-muted-foreground">Opacity</label>
                        <input
                            type="range"
                            min="0"
                            max="1"
                            step="0.01"
                            defaultValue="1"
                            className="h-1.5 flex-1 cursor-pointer accent-primary"
                        />
                    </div>
                </div>
            </CollapsibleSection>

            <CollapsibleSection title="Typography" icon={<TypeIcon className="size-3" />} defaultOpen>
                <div className="flex flex-col gap-2">
                    <div className="flex items-center gap-2">
                        <label className="w-20 shrink-0 text-[11px] text-muted-foreground">Align</label>
                        <ToggleGroup
                            type="single"
                            size="sm"
                            value={selectedElement.styles.textAlign || 'left'}
                            onValueChange={(value) => {
                                if (value !== '') {
                                    onUpdateElementStyle('textAlign', value);
                                }
                            }}
                            className="gap-0.5"
                        >
                            <ToggleGroupItem value="left" aria-label="Left" className="size-7 p-0">
                                <AlignLeftIcon className="size-3.5" />
                            </ToggleGroupItem>
                            <ToggleGroupItem value="center" aria-label="Center" className="size-7 p-0">
                                <AlignCenterIcon className="size-3.5" />
                            </ToggleGroupItem>
                            <ToggleGroupItem value="right" aria-label="Right" className="size-7 p-0">
                                <AlignRightIcon className="size-3.5" />
                            </ToggleGroupItem>
                            <ToggleGroupItem value="justify" aria-label="Justify" className="size-7 p-0">
                                <AlignJustifyIcon className="size-3.5" />
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>
                    <StyleInputRow
                        label="Font size"
                        value={selectedElement.styles.fontSize}
                        onChange={(v) => onUpdateElementStyle('fontSize', v)}
                        placeholder="16"
                        suffix="px"
                    />
                    <div className="flex items-center gap-2">
                        <label className="w-20 shrink-0 text-[11px] text-muted-foreground">Weight</label>
                        <select
                            value={selectedElement.styles.fontWeight || ''}
                            onChange={(e) => onUpdateElementStyle('fontWeight', e.target.value)}
                            className="h-7 w-full rounded-md border border-border/70 bg-background px-2 text-xs outline-none transition-colors focus:border-primary/50"
                        >
                            <option value="">Default</option>
                            <option value="100">Thin</option>
                            <option value="200">Extra Light</option>
                            <option value="300">Light</option>
                            <option value="400">Normal</option>
                            <option value="500">Medium</option>
                            <option value="600">Semi Bold</option>
                            <option value="700">Bold</option>
                            <option value="800">Extra Bold</option>
                            <option value="900">Black</option>
                        </select>
                    </div>
                    <ColorInputRow
                        label="Text color"
                        value={selectedElement.styles.color}
                        onChange={(v) => onUpdateElementStyle('color', v)}
                    />
                </div>
            </CollapsibleSection>

            <CollapsibleSection title="Background" icon={<PaintbrushIcon className="size-3" />}>
                <div className="flex flex-col gap-2">
                    <ColorInputRow
                        label="Color"
                        value={selectedElement.styles.backgroundColor}
                        onChange={(v) => onUpdateElementStyle('backgroundColor', v)}
                    />
                </div>
            </CollapsibleSection>

            <CollapsibleSection title="Spacing" icon={<BoxIcon className="size-3" />}>
                <div className="flex flex-col gap-1.5">
                    <p className="mb-1 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Padding</p>
                    <div className="grid grid-cols-2 gap-1.5">
                        <StyleInputRow
                            label="Top"
                            value={selectedElement.styles.paddingTop}
                            onChange={(v) => onUpdateElementStyle('paddingTop', v)}
                            placeholder="0"
                            suffix="px"
                        />
                        <StyleInputRow
                            label="Right"
                            value={selectedElement.styles.paddingRight}
                            onChange={(v) => onUpdateElementStyle('paddingRight', v)}
                            placeholder="0"
                            suffix="px"
                        />
                        <StyleInputRow
                            label="Bottom"
                            value={selectedElement.styles.paddingBottom}
                            onChange={(v) => onUpdateElementStyle('paddingBottom', v)}
                            placeholder="0"
                            suffix="px"
                        />
                        <StyleInputRow
                            label="Left"
                            value={selectedElement.styles.paddingLeft}
                            onChange={(v) => onUpdateElementStyle('paddingLeft', v)}
                            placeholder="0"
                            suffix="px"
                        />
                    </div>
                    <p className="mt-2 mb-1 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Margin</p>
                    <div className="grid grid-cols-2 gap-1.5">
                        <StyleInputRow
                            label="Top"
                            value={selectedElement.styles.marginTop}
                            onChange={(v) => onUpdateElementStyle('marginTop', v)}
                            placeholder="0"
                            suffix="px"
                        />
                        <StyleInputRow
                            label="Bottom"
                            value={selectedElement.styles.marginBottom}
                            onChange={(v) => onUpdateElementStyle('marginBottom', v)}
                            placeholder="0"
                            suffix="px"
                        />
                    </div>
                </div>
            </CollapsibleSection>

            <CollapsibleSection title="Border">
                <div className="flex flex-col gap-2">
                    <StyleInputRow
                        label="Radius"
                        value={selectedElement.styles.borderRadius}
                        onChange={(v) => onUpdateElementStyle('borderRadius', v)}
                        placeholder="0"
                        suffix="px"
                    />
                </div>
            </CollapsibleSection>
        </div>
    );
}

function AdvancedTab({
    editableElements,
    selectedElement,
    selectedItem,
    onSelectElement,
    onUpdateElementField,
    onUpdateSelectedItemHtml,
}: {
    editableElements: BuilderEditableElement[];
    selectedElement: BuilderEditableElement;
    selectedItem: BuilderCanvasItem;
    onSelectElement: BuilderPropertiesPanelProps['onSelectElement'];
    onUpdateElementField: BuilderPropertiesPanelProps['onUpdateElementField'];
    onUpdateSelectedItemHtml: BuilderPropertiesPanelProps['onUpdateSelectedItemHtml'];
}) {
    return (
        <div className="flex flex-col">
            <CollapsibleSection title="Attributes" defaultOpen>
                <div className="flex flex-col gap-2">
                    <StyleInputRow
                        label="ID"
                        value={selectedElement.id}
                        onChange={(v) => onUpdateElementField('id', v)}
                        placeholder="element-id"
                    />
                    <StyleInputRow
                        label="Classes"
                        value={selectedElement.className}
                        onChange={(v) => onUpdateElementField('className', v)}
                        placeholder="class-name"
                    />
                </div>
            </CollapsibleSection>

            <CollapsibleSection title="Elements">
                <div className="flex max-h-44 flex-col gap-1 overflow-auto">
                    {editableElements.map((element) => {
                        const isActive = element.pathKey === selectedElement.pathKey;

                        return (
                            <button
                                key={element.pathKey}
                                type="button"
                                onClick={() => onSelectElement(element.path)}
                                className={cn(
                                    'rounded px-2 py-1.5 text-left text-xs transition',
                                    isActive
                                        ? 'bg-primary text-primary-foreground'
                                        : 'hover:bg-muted/50',
                                )}
                            >
                                <span className="block truncate font-medium">{element.label}</span>
                                <span
                                    className={cn(
                                        'block text-[10px]',
                                        isActive ? 'text-primary-foreground/70' : 'text-muted-foreground',
                                    )}
                                >
                                    {element.tagName}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </CollapsibleSection>

            <CollapsibleSection title="Raw HTML" icon={<Code2Icon className="size-3" />}>
                <AceCodeEditor
                    value={selectedItem.html}
                    onChange={onUpdateSelectedItemHtml}
                    language="html"
                    height={176}
                    placeholder="<!-- Edit raw HTML for this section -->"
                    className="mt-1"
                />
            </CollapsibleSection>
        </div>
    );
}

function CodeSection({
    customCss,
    customJs,
    onCustomCssChange,
    onCustomJsChange,
}: {
    customCss: string;
    customJs: string;
    onCustomCssChange: (value: string) => void;
    onCustomJsChange: (value: string) => void;
}) {
    return (
        <div className="flex flex-col">
            <CollapsibleSection title="Custom CSS" icon={<Code2Icon className="size-3" />} defaultOpen>
                <AceCodeEditor
                    value={customCss}
                    onChange={onCustomCssChange}
                    language="css"
                    height={176}
                    placeholder="/* Add custom CSS styles... */"
                    className="mt-1"
                />
            </CollapsibleSection>
            <CollapsibleSection title="Custom JavaScript" icon={<Code2Icon className="size-3" />}>
                <AceCodeEditor
                    value={customJs}
                    onChange={onCustomJsChange}
                    language="javascript"
                    height={176}
                    placeholder="// Add custom JavaScript..."
                    className="mt-1"
                />
            </CollapsibleSection>
        </div>
    );
}
