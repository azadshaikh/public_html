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

const NavigationLink = React.forwardRef<
    HTMLElement,
    NavigationLinkProps
>(({ item, children, ...props }, ref) => {
    if (!item.url) {
        return <>{children}</>;
    }

    const { onClick, ...restProps } = props;
    const linkProps = {
        ...restProps,
        onClick,
    } as React.ComponentProps<typeof Link>;

    const hasCustomAttributes = Object.keys(item.attributes ?? {}).length > 0;
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
        <Link ref={ref as React.Ref<unknown>} href={item.url} {...linkProps}>
            {children}
        </Link>
    );
});
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
            <SidebarMenuSubButton asChild isActive={item.active}>
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

    if (depth === 0) {
        return (
            <Collapsible
                asChild
                defaultOpen={item.default_open || item.active}
                className="group/collapsible"
            >
                <SidebarMenuItem>
                    <CollapsibleTrigger asChild>
                        <SidebarMenuButton tooltip={item.label}>
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
                defaultOpen={item.default_open || item.active}
                className="group/sub-collapsible"
            >
                <div>
                    <CollapsibleTrigger asChild>
                        <SidebarMenuSubButton isActive={item.active}>
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
        <>
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
        </>
    );
}
