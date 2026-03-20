import {
    BlocksIcon,
    Heading1Icon,
    ImageIcon,
    LayoutTemplateIcon,
    LinkIcon,
    ListIcon,
    MinusIcon,
    PlayIcon,
    SearchIcon,
    SquareIcon,
    TableIcon,
    TextIcon,
    TypeIcon,
} from 'lucide-react';
import { useDeferredValue, useMemo, useState } from 'react';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { ScrollArea } from '@/components/ui/scroll-area';
import { SearchInput } from '@/components/ui/search-input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { BuilderLibraryGroup, BuilderLibraryItem } from '../../types/cms';

type BuilderLeftSidebarProps = {
    activeLibrary: 'sections' | 'blocks';
    palette: { sections: BuilderLibraryGroup[]; blocks: BuilderLibraryGroup[] };
    onActiveLibraryChange: (value: 'sections' | 'blocks') => void;
    onAddLibraryItem: (item: BuilderLibraryItem) => void;
    onDragStartItem?: (item: BuilderLibraryItem) => void;
};

const COMPONENT_ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
    heading: Heading1Icon,
    image: ImageIcon,
    paragraph: TextIcon,
    link: LinkIcon,
    button: SquareIcon,
    list: ListIcon,
    table: TableIcon,
    form: SquareIcon,
    input: MinusIcon,
    textarea: TextIcon,
    video: PlayIcon,
    text: TypeIcon,
    block: BlocksIcon,
};

function getComponentIcon(name: string): React.ComponentType<{ className?: string }> {
    const normalizedName = name.toLowerCase();

    for (const [key, Icon] of Object.entries(COMPONENT_ICONS)) {
        if (normalizedName.includes(key)) {
            return Icon;
        }
    }

    return LayoutTemplateIcon;
}

export function BuilderLeftSidebar({
    activeLibrary,
    palette,
    onActiveLibraryChange,
    onAddLibraryItem,
    onDragStartItem,
}: BuilderLeftSidebarProps) {
    const [search, setSearch] = useState('');
    const deferredSearch = useDeferredValue(search);
    const groups = palette[activeLibrary] ?? [];

    const filteredGroups = useMemo(() => {
        const normalizedSearch = deferredSearch.trim().toLowerCase();

        if (normalizedSearch === '') {
            return groups;
        }

        return groups
            .map((group) => ({
                ...group,
                items: group.items.filter((item) => {
                    const haystack = [item.name, item.category, item.category_label, item.source]
                        .join(' ')
                        .toLowerCase();

                    return haystack.includes(normalizedSearch);
                }),
            }))
            .filter((group) => group.items.length > 0);
    }, [deferredSearch, groups]);

    const defaultOpenSections = useMemo(
        () => filteredGroups.slice(0, 3).map((group) => group.key),
        [filteredGroups],
    );

    return (
        <div className="flex h-full flex-col">
            <div className="flex items-center justify-between border-b border-border/60 px-3 py-2">
                <Tabs
                    value={activeLibrary}
                    onValueChange={(value) => onActiveLibraryChange(value as 'sections' | 'blocks')}
                    className="w-full"
                >
                    <TabsList className="grid w-full grid-cols-2" variant="line">
                        <TabsTrigger value="sections">
                            <LayoutTemplateIcon className="mr-1 size-3.5" />
                            Sections
                        </TabsTrigger>
                        <TabsTrigger value="blocks">
                            <BlocksIcon className="mr-1 size-3.5" />
                            Blocks
                        </TabsTrigger>
                    </TabsList>
                </Tabs>
            </div>

            <div className="border-b border-border/60 px-3 py-2">
                <SearchInput
                    value={search}
                    onChange={setSearch}
                    placeholder={`Search ${activeLibrary}...`}
                />
            </div>

            <ScrollArea className="min-h-0 flex-1">
                {filteredGroups.length > 0 ? (
                    <Accordion
                        type="multiple"
                        defaultValue={defaultOpenSections}
                        className="px-2 py-2"
                    >
                        {filteredGroups.map((group) => (
                            <AccordionItem
                                key={group.key}
                                value={group.key}
                                className="border-b-0"
                            >
                                <AccordionTrigger className="rounded-md px-2 py-1.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider hover:bg-muted/50 hover:no-underline">
                                    <span className="flex items-center gap-1.5">
                                        {group.label}
                                        <span className="rounded-full bg-muted px-1.5 py-px text-[10px] font-medium text-muted-foreground normal-case">
                                            {group.items.length}
                                        </span>
                                    </span>
                                </AccordionTrigger>
                                <AccordionContent className="pb-1 pt-0.5">
                                    <div className="grid grid-cols-2 gap-1.5 px-1">
                                        {group.items.map((item) => {
                                            const Icon = getComponentIcon(item.name);

                                            return (
                                                <button
                                                    key={item.id}
                                                    type="button"
                                                    onClick={() => onAddLibraryItem(item)}
                                                    draggable
                                                    onDragStart={(e) => {
                                                        e.dataTransfer.setData('text/plain', item.name);
                                                        e.dataTransfer.effectAllowed = 'copy';
                                                        onDragStartItem?.(item);
                                                    }}
                                                    className="group flex flex-col items-center gap-1.5 rounded-lg border border-border/60 bg-background p-2.5 text-center transition hover:border-primary/40 hover:bg-primary/5 hover:shadow-sm active:scale-[0.97]"
                                                >
                                                    {item.preview_image_url ? (
                                                        <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded">
                                                            <img
                                                                src={item.preview_image_url}
                                                                alt={item.name}
                                                                className="size-full object-cover"
                                                                loading="lazy"
                                                            />
                                                        </div>
                                                    ) : (
                                                        <Icon className="size-6 text-muted-foreground transition-colors group-hover:text-primary" />
                                                    )}
                                                    <span className="w-full truncate text-[11px] font-medium leading-tight text-foreground/80">
                                                        {item.name}
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </AccordionContent>
                            </AccordionItem>
                        ))}
                    </Accordion>
                ) : (
                    <div className="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center">
                        <SearchIcon className="size-8 text-muted-foreground/50" />
                        <p className="text-sm font-medium text-muted-foreground">No matches</p>
                        <p className="text-xs text-muted-foreground/70">Try a broader term or switch tabs.</p>
                    </div>
                )}
            </ScrollArea>
        </div>
    );
}
