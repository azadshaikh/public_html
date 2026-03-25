import { usePage } from '@inertiajs/react';
import { AppThemeToggle } from '@/components/app-theme-toggle';
import { NotificationPopover } from '@/components/notifications/notification-popover';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import type { AuthenticatedSharedData } from '@/types';

export function AppTopbar() {
    const { auth, notifications } = usePage<AuthenticatedSharedData>().props;
    const getInitials = useInitials();

    return (
        <header className="border-b border-border/70 bg-background/88 shadow-[0_1px_0_rgba(0,0,0,0.04)] backdrop-blur-xl supports-[backdrop-filter]:bg-background/76 dark:border-white/8 dark:shadow-none">
            <div className="mx-auto flex h-13 w-full max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <div className="flex items-center gap-3">
                    <SidebarTrigger className="rounded-full border border-border/80 bg-card shadow-sm hover:bg-muted" />
                </div>

                <div className="flex items-center gap-1 sm:gap-2">
                    <NotificationPopover
                        initialUnreadCount={notifications.unreadCount}
                    />

                    <AppThemeToggle />

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon-lg"
                                className="rounded-full border border-border/80 bg-card p-1 shadow-sm hover:bg-muted"
                            >
                                <Avatar className="size-8 overflow-hidden rounded-full">
                                    <AvatarImage
                                        src={auth.user.avatar}
                                        alt={auth.user.name}
                                    />
                                    <AvatarFallback className="rounded-full bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                        {getInitials(auth.user.name)}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="w-56" align="end">
                            <UserMenuContent user={auth.user} />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </header>
    );
}
