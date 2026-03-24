import { Link, usePage } from '@inertiajs/react';
import {
    ChevronsUpDownIcon,
    BadgeCheckIcon,
    CreditCardIcon,
    BellIcon,
    LogOutIcon,
} from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useInitials } from '@/hooks/use-initials';
import type { SharedData } from '@/types';

export function NavUser({
    user,
}: {
    user: {
        name: string;
        email: string;
        avatar?: string | null;
    };
}) {
    const { modules } = usePage<SharedData>().props;
    const { isMobile } = useSidebar();
    const getInitials = useInitials();
    const isBillingEnabled = modules.items.some((moduleItem) => moduleItem.slug === 'billing');

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        >
                            <Avatar className="h-8 w-8 rounded-lg">
                                <AvatarImage
                                    src={user.avatar ?? undefined}
                                    alt={user.name}
                                />
                                <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                    {getInitials(user.name)}
                                </AvatarFallback>
                            </Avatar>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-medium">
                                    {user.name}
                                </span>
                                <span className="truncate text-xs">
                                    {user.email}
                                </span>
                            </div>
                            <ChevronsUpDownIcon className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        side={isMobile ? 'bottom' : 'right'}
                        align="end"
                        sideOffset={4}
                    >
                        <DropdownMenuLabel className="p-0 font-normal">
                            <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <Avatar className="h-8 w-8 rounded-lg">
                                    <AvatarImage
                                        src={user.avatar ?? undefined}
                                        alt={user.name}
                                    />
                                    <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                        {getInitials(user.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-medium">
                                        {user.name}
                                    </span>
                                    <span className="truncate text-xs">
                                        {user.email}
                                    </span>
                                </div>
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuGroup>
                            <DropdownMenuItem asChild>
                                <Link
                                    href={route('app.profile')}
                                    className="w-full cursor-pointer"
                                >
                                    <BadgeCheckIcon />
                                    Account
                                </Link>
                            </DropdownMenuItem>
                            {isBillingEnabled && route().has('agency.billing.index') ? (
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={route('agency.billing.index')}
                                        className="w-full cursor-pointer"
                                    >
                                        <CreditCardIcon />
                                        Billing
                                    </Link>
                                </DropdownMenuItem>
                            ) : null}
                            {route().has('app.notifications.index') ? (
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={route('app.notifications.index')}
                                        className="w-full cursor-pointer"
                                    >
                                        <BellIcon />
                                        Notifications
                                    </Link>
                                </DropdownMenuItem>
                            ) : null}
                        </DropdownMenuGroup>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="w-full cursor-pointer"
                            >
                                <LogOutIcon />
                                Log out
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
