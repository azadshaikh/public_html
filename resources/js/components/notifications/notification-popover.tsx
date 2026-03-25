import { Link } from '@inertiajs/react';
import { BellIcon, InboxIcon } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Skeleton } from '@/components/ui/skeleton';
import { usePageVisibility } from '@/hooks/use-page-visibility';
import { cn } from '@/lib/utils';
import type { NotificationDropdownItem } from '@/types/notification';

type NotificationPopoverProps = {
    initialUnreadCount: number;
};

type NotificationDropdownResponse = {
    count: number;
    notifications: NotificationDropdownItem[];
};

const NOTIFICATION_POLL_INTERVAL_MS = 30_000;

function notificationPreview(html?: string | null): string {
    return (html ?? '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
}

export function NotificationPopover({
    initialUnreadCount,
}: NotificationPopoverProps) {
    const isPageVisible = usePageVisibility();
    const [open, setOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [notifications, setNotifications] = useState<NotificationDropdownItem[]>([]);
    const [unreadCount, setUnreadCount] = useState(initialUnreadCount);
    const [shouldPulseBadge, setShouldPulseBadge] = useState(false);
    const previousUnreadCountRef = useRef(initialUnreadCount);

    const hasUnreadNotifications = unreadCount > 0;

    useEffect(() => {
        setUnreadCount(initialUnreadCount);
    }, [initialUnreadCount]);

    useEffect(() => {
        const previousUnreadCount = previousUnreadCountRef.current;

        if (unreadCount > previousUnreadCount) {
            setShouldPulseBadge(true);

            const timeoutId = window.setTimeout(() => {
                setShouldPulseBadge(false);
            }, 1400);

            previousUnreadCountRef.current = unreadCount;

            return () => {
                window.clearTimeout(timeoutId);
            };
        }

        previousUnreadCountRef.current = unreadCount;

        if (unreadCount === 0 && shouldPulseBadge) {
            setShouldPulseBadge(false);
        }
    }, [shouldPulseBadge, unreadCount]);

    const loadUnreadCount = useCallback(async () => {
        const response = await fetch(route('app.notifications.unread-count'), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to load unread notification count.');
        }

        const payload = (await response.json()) as { count: number };

        setUnreadCount(payload.count);
    }, []);

    const loadNotifications = useCallback(async () => {
        setIsLoading(true);

        try {
            const response = await fetch(route('app.notifications.dropdown'), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to load notifications.');
            }

            const payload =
                (await response.json()) as NotificationDropdownResponse;

            setNotifications(payload.notifications);
            setUnreadCount(payload.count);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const handleOpenChange = useCallback(
        (nextOpen: boolean) => {
            setOpen(nextOpen);

            if (nextOpen) {
                void loadNotifications();
            }
        },
        [loadNotifications],
    );

    useEffect(() => {
        if (!isPageVisible) {
            return;
        }

        let active = true;
        let timeoutId: number | null = null;

        const scheduleNextPoll = () => {
            timeoutId = window.setTimeout(() => {
                void pollNotifications();
            }, NOTIFICATION_POLL_INTERVAL_MS);
        };

        const pollNotifications = async () => {
            try {
                if (open) {
                    await loadNotifications();
                } else {
                    await loadUnreadCount();
                }
            } catch {
                // Keep polling even if one refresh fails.
            } finally {
                if (active) {
                    scheduleNextPoll();
                }
            }
        };

        scheduleNextPoll();

        return () => {
            active = false;

            if (timeoutId !== null) {
                window.clearTimeout(timeoutId);
            }
        };
    }, [isPageVisible, loadNotifications, loadUnreadCount, open]);

    return (
        <Popover open={open} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    className={cn(
                        'relative rounded-full border border-border/80 bg-card shadow-sm transition-colors hover:bg-muted',
                        hasUnreadNotifications
                            ? 'text-foreground'
                            : 'text-foreground',
                    )}
                    aria-label="Notifications"
                >
                    <BellIcon />
                    {hasUnreadNotifications ? (
                        <>
                            <span
                                className={cn(
                                    'absolute -top-1.5 -right-1.5 flex min-w-5 items-center justify-center rounded-full border-2 border-background bg-destructive px-1.5 text-[10px] font-bold text-white shadow-sm',
                                    shouldPulseBadge ? 'animate-pulse' : '',
                                )}
                            >
                                {unreadCount > 99 ? '99+' : unreadCount}
                            </span>
                        </>
                    ) : null}
                </Button>
            </PopoverTrigger>

            <PopoverContent
                align="end"
                collisionPadding={8}
                className="w-[min(24rem,calc(100vw-1rem))] max-h-[550px] gap-0 overflow-hidden p-0"
            >
                <div className="flex items-center justify-between gap-3 border-b px-4 py-3">
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-medium">Notifications</span>
                        <Badge
                            variant={hasUnreadNotifications ? 'destructive' : 'secondary'}
                        >
                            {unreadCount} unread
                        </Badge>
                    </div>

                    <Button asChild variant="ghost" size="sm">
                        <Link href={route('app.notifications.index')}>
                            View inbox
                        </Link>
                    </Button>
                </div>

                {isLoading ? (
                    <div className="flex flex-col gap-2 p-3">
                        {Array.from({ length: 3 }).map((_, index) => (
                            <Skeleton
                                key={index}
                                className="h-20 rounded-xl"
                            />
                        ))}
                    </div>
                ) : notifications.length === 0 ? (
                    <div className="p-3">
                        <Empty className="border bg-muted/20 py-10">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <InboxIcon />
                                </EmptyMedia>
                                <EmptyTitle>No notifications yet</EmptyTitle>
                                <EmptyDescription>
                                    New activity will appear here as it arrives.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    </div>
                ) : (
                    <div className="max-h-[460px] overflow-y-auto">
                        <div className="flex flex-col gap-2 p-3">
                            {notifications.map((notification) => {
                                const preview = notificationPreview(
                                    notification.sanitized_message,
                                );

                                return (
                                    <Link
                                        key={notification.id}
                                        href={route(
                                            'app.notifications.show',
                                            notification.id,
                                        )}
                                        className={cn(
                                            'flex items-start rounded-xl border p-3 transition-colors hover:bg-muted/40',
                                            notification.is_read
                                                ? 'border-border/70'
                                                : 'border-primary/20 bg-primary/4',
                                        )}
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="min-w-0">
                                                    <p
                                                        className={cn(
                                                            'truncate text-sm',
                                                            notification.is_read
                                                                ? 'text-foreground'
                                                                : 'font-medium text-foreground',
                                                        )}
                                                    >
                                                        {notification.title_text}
                                                    </p>

                                                    {preview ? (
                                                        <p className="mt-1 line-clamp-2 text-xs leading-5 text-muted-foreground">
                                                            {preview}
                                                        </p>
                                                    ) : null}
                                                </div>

                                                {!notification.is_read ? (
                                                    <span className="mt-1 size-2 shrink-0 rounded-full bg-primary" />
                                                ) : null}
                                            </div>

                                            <div className="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
                                                <span>
                                                    {notification.time_ago ?? 'Just now'}
                                                </span>
                                                <span aria-hidden="true">•</span>
                                                <span>
                                                    {notification.category_label}
                                                </span>
                                            </div>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                )}
            </PopoverContent>
        </Popover>
    );
}