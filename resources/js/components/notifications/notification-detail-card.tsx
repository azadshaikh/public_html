import { ExternalLinkIcon, InboxIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import type { NotificationListItem } from '@/types/notification';
import type { BadgeVariant } from '@/types/ui';

const PRIORITY_VARIANT: Record<string, BadgeVariant> = {
    high: 'danger',
    medium: 'warning',
    low: 'secondary',
};

const CATEGORY_VARIANT: Record<string, BadgeVariant> = {
    system: 'danger',
    website: 'info',
    user: 'success',
    cms: 'outline',
    broadcast: 'secondary',
};

function formatNotificationDate(value: string): string {
    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function detailRow(label: string, value: string) {
    return (
        <div className="flex flex-col gap-1 border-b py-3 last:border-b-0 last:pb-0 first:pt-0">
            <span className="text-xs font-medium uppercase tracking-[0.08em] text-muted-foreground">
                {label}
            </span>
            <span className="text-sm text-foreground">{value}</span>
        </div>
    );
}

type NotificationDetailCardProps = {
    notification: NotificationListItem | null;
    isLoading?: boolean;
    headerActions?: ReactNode;
    emptyTitle?: string;
    emptyDescription?: string;
};

export function NotificationDetailCard({
    notification,
    isLoading = false,
    headerActions,
    emptyTitle = 'Select a notification',
    emptyDescription = 'Choose a notification from the inbox to read it here without leaving the page.',
}: NotificationDetailCardProps) {
    if (isLoading) {
        return (
            <Card>
                <CardHeader>
                    <div className="flex items-start gap-4">
                        <Skeleton className="size-12 rounded-full" />
                        <div className="flex min-w-0 flex-1 flex-col gap-2">
                            <Skeleton className="h-5 w-32 rounded-full" />
                            <Skeleton className="h-7 w-2/3" />
                            <Skeleton className="h-4 w-48" />
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <Skeleton className="h-32 rounded-xl" />
                    <Skeleton className="h-24 rounded-xl" />
                </CardContent>
            </Card>
        );
    }

    if (!notification) {
        return (
            <Card>
                <CardContent className="p-4">
                    <Empty className="border bg-muted/20 py-12">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <InboxIcon />
                            </EmptyMedia>
                            <EmptyTitle>{emptyTitle}</EmptyTitle>
                            <EmptyDescription>
                                {emptyDescription}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge
                                variant={
                                    PRIORITY_VARIANT[notification.priority] ??
                                    'outline'
                                }
                            >
                                {notification.priority_label}
                            </Badge>
                            <Badge
                                variant={
                                    CATEGORY_VARIANT[notification.category] ??
                                    'outline'
                                }
                            >
                                {notification.category_label}
                            </Badge>
                            <Badge
                                variant={
                                    notification.is_read
                                        ? 'outline'
                                        : 'secondary'
                                }
                            >
                                {notification.is_read ? 'Read' : 'Unread'}
                            </Badge>
                        </div>

                        <CardTitle className="mt-3 text-xl">
                            {notification.title_text}
                        </CardTitle>
                        <CardDescription className="mt-2 text-sm leading-6">
                            Received{' '}
                            {formatNotificationDate(notification.created_at)}
                        </CardDescription>
                    </div>

                {headerActions ? (
                    <div className="flex flex-wrap items-center gap-2 pt-4">
                        {headerActions}
                    </div>
                ) : null}
            </CardHeader>

            <CardContent className="flex flex-col gap-6">
                {notification.sanitized_message ? (
                    <div
                        className="rounded-xl border bg-background/80 px-4 py-3 text-sm leading-6 text-foreground [&_a]:font-medium [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_a:hover]:text-primary/80 [&_blockquote]:my-2 [&_blockquote]:border-l-2 [&_blockquote]:pl-4 [&_blockquote]:italic [&_p]:my-2 [&_p:first-child]:mt-0 [&_p:last-child]:mb-0 [&_strong]:font-semibold [&_u]:underline"
                        dangerouslySetInnerHTML={{
                            __html: notification.sanitized_message,
                        }}
                    />
                ) : (
                    <div className="rounded-xl border border-dashed bg-muted/20 px-4 py-6 text-sm text-muted-foreground">
                        This notification does not include a message body.
                    </div>
                )}

                {notification.content_links.length > 0 ? (
                    <div className="flex flex-col gap-3">
                        <div>
                            <h2 className="text-sm font-medium text-foreground">
                                Related links
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Open related pages from inside the notification content.
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {notification.content_links.map((link) => (
                                <Button
                                    key={`${notification.id}-${link.href}`}
                                    asChild
                                    variant={
                                        link.external ? 'outline' : 'default'
                                    }
                                >
                                    <a
                                        href={link.href}
                                        target={
                                            link.external ? '_blank' : undefined
                                        }
                                        rel={
                                            link.external
                                                ? 'noreferrer noopener'
                                                : undefined
                                        }
                                    >
                                        <ExternalLinkIcon data-icon="inline-start" />
                                        {link.label}
                                    </a>
                                </Button>
                            ))}
                        </div>
                    </div>
                ) : null}

                <div className="grid gap-4 rounded-xl border bg-muted/15 p-4 lg:grid-cols-2">
                    {detailRow(
                        'Status',
                        notification.is_read ? 'Read' : 'Unread',
                    )}
                    {detailRow('Category', notification.category_label)}
                    {detailRow('Priority', notification.priority_label)}
                    {detailRow(
                        'Received',
                        formatNotificationDate(notification.created_at),
                    )}
                    {detailRow('Type', notification.type)}
                    {detailRow(
                        'Updated',
                        formatNotificationDate(notification.updated_at),
                    )}
                </div>
            </CardContent>
        </Card>
    );
}