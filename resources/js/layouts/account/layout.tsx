import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: route('app.profile'),
        icon: null,
    },
    {
        title: 'Security',
        href: route('app.profile.security'),
        icon: null,
    },
    {
        title: 'Password',
        href: route('app.profile.security.password'),
        icon: null,
    },
    {
        title: 'Two-factor auth',
        href: route('app.profile.security.two-factor'),
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
