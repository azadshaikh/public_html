import { Link } from '@inertiajs/react';
import { ChevronRightIcon } from 'lucide-react';
import * as React from 'react';
import type { AnchorHTMLAttributes, HTMLAttributes } from 'react';
import { NavBadge } from '@/components/nav-badge';
import { NavigationIcon } from '@/components/navigation-icon';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import type { NavigationItem, NavigationSection } from '@/types';

// Module-level storage that persists across Inertia navigations
const openItemsStorage = new Set<string>();
const closedItemsStorage = new Set<string>(); // Track explicitly closed items

// Context to share open items state across all NavMainBranch instances
type OpenItemsContextType = {
    isOpen: (key: string) => boolean;
    isClosed: (key: string) => boolean;
    toggle: (key: string, open: boolean) => void;
};

const OpenItemsContext = React.createContext<OpenItemsContextType | null>(null);

function useOpenItems() {
    const context = React.useContext(OpenItemsContext);
    if (!context) {
        throw new Error('useOpenItems must be used within OpenItemsProvider');
    }
    return context;
}

function OpenItemsProvider({ children }: { children: React.ReactNode }) {
    // Initialize React state from module-level storage
    const [openItems, setOpenItems] = React.useState<Set<string>>(
        () => new Set(openItemsStorage),
    );
    const [closedItems, setClosedItems] = React.useState<Set<string>>(
        () => new Set(closedItemsStorage),
    );

    const isOpen = React.useCallback(
        (key: string) => {
            return openItems.has(key);
        },
        [openItems],
    );

    const isClosed = React.useCallback(
        (key: string) => {
            return closedItems.has(key);
        },
        [closedItems],
    );

    const toggle = React.useCallback((key: string, open: boolean) => {
        // Update module-level storage first
        if (open) {
            openItemsStorage.add(key);
            closedItemsStorage.delete(key);
        } else {
            openItemsStorage.delete(key);
            closedItemsStorage.add(key);
        }
        // Then sync React state
        setOpenItems(new Set(openItemsStorage));
        setClosedItems(new Set(closedItemsStorage));
    }, []);

    return (
        <OpenItemsContext.Provider value={{ isOpen, isClosed, toggle }}>
            {children}
        </OpenItemsContext.Provider>
    );
}

function buildAnchorAttributes(
    item: NavigationItem,
): AnchorHTMLAttributes<HTMLAnchorElement> {
    const attributes = Object.fromEntries(
        Object.entries(item.attributes ?? {}).filter(
            ([, value]) => value !== null,
        ),
    ) as AnchorHTMLAttributes<HTMLAnchorElement>;

    return {
        ...attributes,
        target: item.target ?? attributes.target,
        rel:
            item.target === '_blank'
                ? ((attributes.rel as string | undefined) ??
                  'noreferrer noopener')
                : attributes.rel,
    };
}

type NavigationLinkProps = Omit<HTMLAttributes<HTMLElement>, 'onClick'> & {
    item: NavigationItem;
    onClick?: (event: React.MouseEvent<Element>) => void;
};

const NavigationLink = React.forwardRef<HTMLElement, NavigationLinkProps>(
    ({ item, children, ...props }, ref) => {
        if (!item.url) {
            return <>{children}</>;
        }

        const { onClick, ...restProps } = props;
        const linkProps = {
            ...restProps,
            onClick,
        } as React.ComponentProps<typeof Link>;

        const hasCustomAttributes =
            Object.keys(item.attributes ?? {}).length > 0;
        const shouldUseAnchor =
            item.hard_reload || item.target || hasCustomAttributes;

        if (shouldUseAnchor) {
            return (
                <a
                    ref={ref as React.Ref<HTMLAnchorElement>}
                    href={item.url}
                    {...buildAnchorAttributes(item)}
                    {...restProps}
                    onClick={
                        onClick as React.MouseEventHandler<HTMLAnchorElement>
                    }
                >
                    {children}
                </a>
            );
        }

        return (
            <Link
                ref={ref as React.Ref<unknown>}
                href={item.url}
                {...linkProps}
            >
                {children}
            </Link>
        );
    },
);
NavigationLink.displayName = 'NavigationLink';

function NavMainLeaf({ item, depth }: { item: NavigationItem; depth: number }) {
    if (!item.url) {
        return null;
    }

    if (depth === 0) {
        return (
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    tooltip={item.label}
                    isActive={item.active}
                    className="scroll-m-0"
                >
                    <NavigationLink item={item}>
                        <NavigationIcon svg={item.icon} />
                        <span>{item.label}</span>
                        {item.badge?.value ? (
                            <NavBadge badge={item.badge} />
                        ) : null}
                    </NavigationLink>
                </SidebarMenuButton>
            </SidebarMenuItem>
        );
    }

    return (
        <SidebarMenuSubItem>
            <SidebarMenuSubButton
                asChild
                isActive={item.active}
                className="scroll-m-0"
            >
                <NavigationLink item={item}>
                    <span>{item.label}</span>
                </NavigationLink>
            </SidebarMenuSubButton>
        </SidebarMenuSubItem>
    );
}

function NavMainBranch({
    item,
    depth,
}: {
    item: NavigationItem;
    depth: number;
}) {
    const children = item.children ?? [];
    const { isOpen, isClosed, toggle } = useOpenItems();

    // Determine if this item should be open
    // If user explicitly closed it, keep it closed
    // Otherwise, open if: explicitly opened OR default_open OR active
    const isItemOpen = isClosed(item.key)
        ? false
        : isOpen(item.key) || item.default_open || item.active;

    const handleOpenChange = (open: boolean) => {
        toggle(item.key, open);
    };

    if (depth === 0) {
        return (
            <Collapsible
                asChild
                open={isItemOpen}
                onOpenChange={handleOpenChange}
                className="group/collapsible"
            >
                <SidebarMenuItem>
                    <CollapsibleTrigger asChild>
                        <SidebarMenuButton
                            tooltip={item.label}
                            className="cursor-pointer scroll-m-0"
                        >
                            <NavigationIcon svg={item.icon} />
                            <span>{item.label}</span>
                            {item.badge?.value ? (
                                <NavBadge badge={item.badge} />
                            ) : null}
                            <ChevronRightIcon className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            {children.map((child) => (
                                <NavMainNode
                                    key={`${item.key}-${child.key}`}
                                    item={child}
                                    depth={depth + 1}
                                />
                            ))}
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            </Collapsible>
        );
    }

    return (
        <SidebarMenuSubItem>
            <Collapsible
                asChild
                open={isItemOpen}
                onOpenChange={handleOpenChange}
                className="group/sub-collapsible"
            >
                <div>
                    <CollapsibleTrigger asChild>
                        <SidebarMenuSubButton
                            isActive={item.active}
                            className="cursor-pointer scroll-m-0"
                        >
                            <span>{item.label}</span>
                            <ChevronRightIcon className="ml-auto transition-transform duration-200 group-data-[state=open]/sub-collapsible:rotate-90" />
                        </SidebarMenuSubButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            {children.map((child) => (
                                <NavMainNode
                                    key={`${item.key}-${child.key}`}
                                    item={child}
                                    depth={depth + 1}
                                />
                            ))}
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </div>
            </Collapsible>
        </SidebarMenuSubItem>
    );
}

function NavMainNode({ item, depth }: { item: NavigationItem; depth: number }) {
    if (item.children?.length) {
        return <NavMainBranch item={item} depth={depth} />;
    }

    return <NavMainLeaf item={item} depth={depth} />;
}

export function NavMain({ sections }: { sections: NavigationSection[] }) {
    return (
        <OpenItemsProvider>
            {sections.map((section) => (
                <SidebarGroup key={section.key}>
                    {section.show_label ? (
                        <SidebarGroupLabel>{section.label}</SidebarGroupLabel>
                    ) : null}
                    <SidebarMenu>
                        {section.items.map((item) => (
                            <NavMainNode key={item.key} item={item} depth={0} />
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </OpenItemsProvider>
    );
}
