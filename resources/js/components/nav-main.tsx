import { Link } from '@inertiajs/react';
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
import { ChevronRightIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type NavMainItem = {
  title: string;
  url?: string;
  icon?: ReactNode;
  isActive?: boolean;
  items?: NavMainItem[];
};

function NavMainLeaf({ item, depth }: { item: NavMainItem; depth: number }) {
  if (!item.url) {
    return null;
  }

  if (depth === 0) {
    return (
      <SidebarMenuItem>
        <SidebarMenuButton
          asChild
          tooltip={item.title}
          isActive={item.isActive}
        >
          <Link href={item.url}>
            {item.icon}
            <span>{item.title}</span>
          </Link>
        </SidebarMenuButton>
      </SidebarMenuItem>
    );
  }

  return (
    <SidebarMenuSubItem>
      <SidebarMenuSubButton asChild isActive={item.isActive}>
        <Link href={item.url}>
          {item.icon}
          <span>{item.title}</span>
        </Link>
      </SidebarMenuSubButton>
    </SidebarMenuSubItem>
  );
}

function NavMainBranch({ item, depth }: { item: NavMainItem; depth: number }) {
  const children = item.items ?? [];

  if (depth === 0) {
    return (
      <Collapsible
        asChild
        defaultOpen={item.isActive}
        className="group/collapsible"
      >
        <SidebarMenuItem>
          <CollapsibleTrigger asChild>
            <SidebarMenuButton tooltip={item.title} isActive={item.isActive}>
              {item.icon}
              <span>{item.title}</span>
              <ChevronRightIcon className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
            </SidebarMenuButton>
          </CollapsibleTrigger>
          <CollapsibleContent>
            <SidebarMenuSub>
              {children.map((child) => (
                <NavMainNode
                  key={`${item.title}-${child.title}`}
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
        defaultOpen={item.isActive}
        className="group/sub-collapsible"
      >
        <div>
          <CollapsibleTrigger asChild>
            <SidebarMenuSubButton isActive={item.isActive}>
              {item.icon}
              <span>{item.title}</span>
              <ChevronRightIcon className="ml-auto transition-transform duration-200 group-data-[state=open]/sub-collapsible:rotate-90" />
            </SidebarMenuSubButton>
          </CollapsibleTrigger>
          <CollapsibleContent>
            <SidebarMenuSub className="mx-2.5 mt-1">
              {children.map((child) => (
                <NavMainNode
                  key={`${item.title}-${child.title}`}
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

function NavMainNode({ item, depth }: { item: NavMainItem; depth: number }) {
  if (item.items?.length) {
    return <NavMainBranch item={item} depth={depth} />;
  }

  return <NavMainLeaf item={item} depth={depth} />;
}

export function NavMain({ items }: { items: NavMainItem[] }) {
  return (
    <SidebarGroup>
      <SidebarGroupLabel>Platform</SidebarGroupLabel>
      <SidebarMenu>
        {items.map((item) => (
          <NavMainNode key={item.title} item={item} depth={0} />
        ))}
      </SidebarMenu>
    </SidebarGroup>
  );
}
