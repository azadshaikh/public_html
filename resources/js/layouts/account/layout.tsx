import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { profile as profileRoute } from '@/routes/app';
import { security as securityRoute } from '@/routes/app/profile';
import {
    password as passwordRoute,
    twoFactor as twoFactorRoute,
} from '@/routes/app/profile/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: profileRoute(),
        icon: null,
    },
    {
        title: 'Security',
        href: securityRoute(),
        icon: null,
    },
    {
        title: 'Password',
        href: passwordRoute(),
        icon: null,
    },
    {
        title: 'Two-factor auth',
        href: twoFactorRoute(),
        icon: null,
    },
];

export default function AccountLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    return (
        <div className="flex flex-col gap-8 lg:grid lg:grid-cols-[220px_minmax(0,1fr)] lg:items-start">
            <aside className="w-full">
                <div className="rounded-2xl border bg-card p-3">
                    <nav className="flex flex-col gap-1" aria-label="Account">
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="size-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </div>
            </aside>

            <div className="min-w-0 lg:max-w-3xl">
                <section className="space-y-10">{children}</section>
            </div>
        </div>
    );
}
