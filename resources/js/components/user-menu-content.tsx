import { Link, router, usePage } from '@inertiajs/react';
import { BadgeCheck, Bell, CreditCard, LogOut } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import type { SharedData, User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const { modules } = usePage<SharedData>().props;
    const cleanup = useMobileNavigation();
    const isBillingEnabled = modules.items.some((moduleItem) => moduleItem.slug === 'billing');

    const handleLogout = () => {
        cleanup();
        router.cancelAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={route('app.profile')}
                        prefetch
                        onClick={cleanup}
                    >
                        <BadgeCheck className="mr-2" />
                        Account
                    </Link>
                </DropdownMenuItem>
                {isBillingEnabled && route().has('agency.billing.index') ? (
                    <DropdownMenuItem asChild>
                        <Link
                            className="block w-full cursor-pointer"
                            href={route('agency.billing.index')}
                            prefetch
                            onClick={cleanup}
                        >
                            <CreditCard className="mr-2" />
                            Billing
                        </Link>
                    </DropdownMenuItem>
                ) : null}
                {route().has('app.notifications.index') ? (
                    <DropdownMenuItem asChild>
                        <Link
                            className="block w-full cursor-pointer"
                            href={route('app.notifications.index')}
                            prefetch
                            onClick={cleanup}
                        >
                            <Bell className="mr-2" />
                            Notifications
                        </Link>
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={route('logout')}
                    method="post"
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
