import { Link } from '@inertiajs/react';
import { ChevronRightIcon } from 'lucide-react';
import type { AnchorHTMLAttributes } from 'react';
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
import { cn } from '@/lib/utils';
import type { NavigationItem, NavigationSection } from '@/types';

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

function NavigationLink({
    item,
    children,
}: {
    item: NavigationItem;
    children: React.ReactNode;
}) {
    if (!item.url) {
        return <>{children}</>;
    }

    const hasCustomAttributes = Object.keys(item.attributes ?? {}).length > 0;
    const shouldUseAnchor =
        item.hard_reload || item.target || hasCustomAttributes;

    if (shouldUseAnchor) {
        return (
            <a href={item.url} {...buildAnchorAttributes(item)}>
                {children}
            </a>
        );
    }

    return <Link href={item.url}>{children}</Link>;
}

function NavItemLabel({
    item,
    depth,
}: {
    item: NavigationItem;
    depth: number;
}) {
    const isTopLevel = depth === 0;

    return (
        <>
            <NavigationIcon
                svg={item.icon}
                className={cn(
                    isTopLevel
                        ? 'text-sidebar-foreground/70 [&_svg]:size-3.5'
                        : 'text-sidebar-foreground/65 [&_svg]:size-3.5',
                )}
            />
            <span
                className={cn(
                    'truncate',
                    isTopLevel
                        ? 'text-sidebar-foreground'
                        : 'text-sidebar-foreground/85',
                )}
            >
                {item.label}
            </span>
            {item.badge?.value ? (
                <span className="ml-auto rounded-full bg-sidebar-foreground/10 px-1.5 py-0.5 text-[10px] leading-none font-semibold text-sidebar-foreground/70 group-data-[collapsible=icon]:hidden">
                    {item.badge.value}
                </span>
            ) : null}
        </>
    );
}

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
                    size="default"
                    className="h-8 rounded-md px-2 font-normal shadow-none hover:bg-sidebar-accent/55 data-[active=true]:bg-sidebar-accent/70 data-[active=true]:font-medium"
                >
                    <NavigationLink item={item}>
                        <NavItemLabel item={item} depth={depth} />
                    </NavigationLink>
                </SidebarMenuButton>
            </SidebarMenuItem>
        );
    }

    return (
        <SidebarMenuSubItem>
            <SidebarMenuSubButton asChild isActive={item.active}>
                <NavigationLink item={item}>
                    <NavItemLabel item={item} depth={depth} />
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

    if (depth === 0) {
        return (
            <Collapsible
                asChild
                defaultOpen={item.active}
                className="group/collapsible"
            >
                <SidebarMenuItem>
                    <CollapsibleTrigger asChild>
                        <SidebarMenuButton
                            tooltip={item.label}
                            isActive={item.active}
                            size="default"
                            className="h-8 rounded-md px-2 font-normal shadow-none hover:bg-sidebar-accent/55 data-[active=true]:bg-sidebar-accent/70 data-[active=true]:font-medium"
                        >
                            <NavItemLabel item={item} depth={depth} />
                            <ChevronRightIcon className="ml-auto size-4 text-sidebar-foreground/45 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent className="pt-1">
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
                defaultOpen={item.active}
                className="group/sub-collapsible"
            >
                <div>
                    <CollapsibleTrigger asChild>
                        <SidebarMenuSubButton isActive={item.active}>
                            <NavItemLabel item={item} depth={depth} />
                            <ChevronRightIcon className="ml-auto transition-transform duration-200 group-data-[state=open]/sub-collapsible:rotate-90" />
                        </SidebarMenuSubButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub className="mx-2.5 mt-1">
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
        <>
            {sections.map((section) => (
                <SidebarGroup key={section.key} className="py-0.5">
                    {section.show_label ? (
                        <SidebarGroupLabel className="px-2 text-xs font-medium text-sidebar-foreground/50">
                            {section.label}
                        </SidebarGroupLabel>
                    ) : null}
                    <SidebarMenu className="gap-0">
                        {section.items.map((item) => (
                            <NavMainNode key={item.key} item={item} depth={0} />
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
