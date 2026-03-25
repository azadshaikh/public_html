import { Link, router } from '@inertiajs/react';
import { ArrowLeftIcon, MailOpenIcon, Settings2Icon } from 'lucide-react';
import { NotificationDetailCard } from '@/components/notifications/notification-detail-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { NotificationShowPageProps } from '@/types/notification';

export default function NotificationsShow({
    notification,
}: NotificationShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Notifications', href: route('app.notifications.index') },
        {
            title: notification.title_text,
            href: route('app.notifications.show', notification.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={notification.title_text}
            description="Read the notification in-app and follow any links from within the message body."
        >
            <NotificationDetailCard
                notification={notification}
                headerActions={
                    <>
                        <Button asChild variant="outline">
                            <Link href={route('app.notifications.index')}>
                                <ArrowLeftIcon data-icon="inline-start" />
                                Back to inbox
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                router.post(
                                    route(
                                        'app.notifications.mark-unread',
                                        notification.id,
                                    ),
                                    {},
                                    {
                                        preserveScroll: true,
                                        preserveState: true,
                                    },
                                );
                            }}
                        >
                            <MailOpenIcon data-icon="inline-start" />
                            Mark as unread
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={route('app.notifications.preferences')}>
                                <Settings2Icon data-icon="inline-start" />
                                Preferences
                            </Link>
                        </Button>
                    </>
                }
            />
        </AppLayout>
    );
}