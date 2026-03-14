import { Link } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    Clock3Icon,
    LaptopIcon,
    LogOutIcon,
    MapPinIcon,
    ShieldAlertIcon,
    ShieldCheckIcon,
    SmartphoneIcon,
    TabletSmartphoneIcon,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
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
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type SessionItem = {
    id: string;
    ip_address: string;
    is_current: boolean;
    device: string;
    platform: string;
    browser: string;
    last_active_label: string;
};

type SessionsPageProps = {
    sessions: SessionItem[];
    sessionManagementSupported: boolean;
};

type SessionActionResponse = {
    success?: boolean;
    message?: string;
    deleted_count?: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: route('dashboard'),
    },
    {
        title: 'Profile',
        href: route('app.profile'),
    },
    {
        title: 'Security',
        href: route('app.profile.security'),
    },
    {
        title: 'Active Sessions',
        href: route('app.profile.security.sessions'),
    },
];

function SectionCard({
    title,
    description,
    badge,
    children,
}: {
    title: string;
    description: string;
    badge?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Card className="py-6">
            <CardHeader className="gap-2">
                <CardTitle className="flex items-center gap-2 text-[1.05rem] font-semibold">
                    <ShieldCheckIcon className="size-4.5 shrink-0" />
                    <span>{title}</span>
                </CardTitle>
                <CardDescription className="max-w-2xl text-sm leading-6">
                    {description}
                </CardDescription>
                {badge ? <CardAction>{badge}</CardAction> : null}
            </CardHeader>
            <CardContent className="space-y-4">{children}</CardContent>
        </Card>
    );
}

function SessionDeviceIcon({ device }: { device: string }) {
    if (device === 'Mobile') {
        return <SmartphoneIcon className="size-5 shrink-0 text-foreground" />;
    }

    if (device === 'Tablet') {
        return (
            <TabletSmartphoneIcon className="size-5 shrink-0 text-foreground" />
        );
    }

    return <LaptopIcon className="size-5 shrink-0 text-foreground" />;
}

function showToast(variant: 'success' | 'error' | 'info', message: string) {
    if (message.length <= 52) {
        showAppToast({
            variant,
            title: message,
        });

        return;
    }

    showAppToast({
        variant,
        description: message,
    });
}

function getCsrfToken(): string {
    return (
        document.head
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function deleteJson(url: string): Promise<SessionActionResponse> {
    const response = await fetch(url, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
    });

    const payload = (await response
        .json()
        .catch(() => ({}))) as SessionActionResponse;

    if (!response.ok || !payload.success) {
        throw new Error(payload.message || 'Something went wrong.');
    }

    return payload;
}

export default function Sessions({
    sessions: initialSessions,
    sessionManagementSupported,
}: SessionsPageProps) {
    const [sessions, setSessions] = useState(initialSessions);
    const [revokeSessionId, setRevokeSessionId] = useState<string | null>(null);
    const [showRevokeOthersDialog, setShowRevokeOthersDialog] = useState(false);
    const [pendingAction, setPendingAction] = useState<string | null>(null);

    const currentSession =
        sessions.find((session) => session.is_current) ?? null;
    const revokableSessions = sessions.filter((session) => !session.is_current);
    const sessionCountBadge = sessionManagementSupported
        ? `${sessions.length} active`
        : 'Limited';

    const handleRevokeSession = async (sessionId: string) => {
        setPendingAction(sessionId);

        try {
            const payload = await deleteJson(
                route('app.profile.sessions.delete', { session: sessionId }),
            );

            setSessions((currentSessions) =>
                currentSessions.filter((session) => session.id !== sessionId),
            );
            setRevokeSessionId(null);
            showToast(
                'success',
                payload.message || 'Session revoked successfully.',
            );
        } catch (error) {
            showToast(
                'error',
                error instanceof Error
                    ? error.message
                    : 'Failed to revoke the session.',
            );
        } finally {
            setPendingAction(null);
        }
    };

    const handleRevokeOtherSessions = async () => {
        setPendingAction('others');

        try {
            const payload = await deleteJson(
                route('app.profile.sessions.delete-others'),
            );

            setSessions((currentSessions) =>
                currentSessions.filter((session) => session.is_current),
            );
            setShowRevokeOthersDialog(false);
            showToast(
                'success',
                payload.message || 'Other sessions have been logged out.',
            );
        } catch (error) {
            showToast(
                'error',
                error instanceof Error
                    ? error.message
                    : 'Failed to log out other sessions.',
            );
        } finally {
            setPendingAction(null);
        }
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Active Sessions"
            description="Review signed-in devices and revoke sessions you no longer use."
            headerActions={
                <Button asChild variant="outline">
                    <Link href={route('app.profile.security')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <SectionCard
                    title="Session controls"
                    description="See where your account is signed in and remove access from devices you no longer recognize."
                    badge={
                        <Badge
                            variant={
                                sessionManagementSupported
                                    ? 'info'
                                    : 'secondary'
                            }
                        >
                            {sessionCountBadge}
                        </Badge>
                    }
                >
                    {!sessionManagementSupported ? (
                        <Alert>
                            <ShieldAlertIcon className="size-4" />
                            <AlertTitle>
                                Session management is limited
                            </AlertTitle>
                            <AlertDescription>
                                Your current session can still be reviewed here,
                                but revoking other devices requires the database
                                session driver.
                            </AlertDescription>
                        </Alert>
                    ) : revokableSessions.length > 0 ? (
                        <div className="space-y-3">
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full border-destructive/30 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                disabled={pendingAction !== null}
                                onClick={() => setShowRevokeOthersDialog(true)}
                            >
                                {pendingAction === 'others' ? (
                                    <Spinner />
                                ) : (
                                    <LogOutIcon data-icon="inline-start" />
                                )}
                                Log out other sessions
                            </Button>

                            <p className="text-sm leading-6 text-muted-foreground">
                                Your current session stays signed in. All other
                                devices will need to log in again.
                            </p>
                        </div>
                    ) : (
                        <Alert>
                            <ShieldCheckIcon className="size-4" />
                            <AlertTitle>No other active devices</AlertTitle>
                            <AlertDescription>
                                You&apos;re only signed in on this device right
                                now.
                            </AlertDescription>
                        </Alert>
                    )}
                </SectionCard>

                <SectionCard
                    title="Signed-in devices"
                    description="Devices are ordered by recent activity so you can quickly spot anything unfamiliar."
                    badge={
                        <Badge variant="secondary">
                            {sessions.length === 1
                                ? '1 device'
                                : `${sessions.length} devices`}
                        </Badge>
                    }
                >
                    {sessions.length === 0 ? (
                        <Empty className="border bg-muted/20 py-10">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <LaptopIcon />
                                </EmptyMedia>
                                <EmptyTitle>No active sessions</EmptyTitle>
                                <EmptyDescription>
                                    There are no signed-in devices to review.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <div className="overflow-hidden rounded-xl border">
                            {sessions.map((session, index) => {
                                const isRevoking = pendingAction === session.id;

                                return (
                                    <div
                                        key={session.id}
                                        className={cn(
                                            'flex flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between',
                                            index > 0 && 'border-t',
                                        )}
                                    >
                                        <div className="flex min-w-0 items-start gap-4">
                                            <div className="flex size-11 shrink-0 items-center justify-center rounded-xl border bg-muted/30">
                                                <SessionDeviceIcon
                                                    device={session.device}
                                                />
                                            </div>

                                            <div className="min-w-0 space-y-2">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-semibold text-foreground">
                                                        {session.browser}
                                                    </p>
                                                    {session.is_current ? (
                                                        <Badge variant="success">
                                                            Current session
                                                        </Badge>
                                                    ) : null}
                                                </div>

                                                <div className="flex flex-col gap-1 text-sm leading-6 text-muted-foreground">
                                                    <p>
                                                        {session.platform} ·{' '}
                                                        {session.device}
                                                    </p>
                                                    <p className="inline-flex items-center gap-2">
                                                        <MapPinIcon className="size-3.5 shrink-0" />
                                                        <span>
                                                            {session.ip_address}
                                                        </span>
                                                    </p>
                                                    <p className="inline-flex items-center gap-2">
                                                        <Clock3Icon className="size-3.5 shrink-0" />
                                                        <span>
                                                            Last active{' '}
                                                            {
                                                                session.last_active_label
                                                            }
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        {sessionManagementSupported &&
                                        !session.is_current ? (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="w-full border-destructive/30 text-destructive hover:bg-destructive/10 hover:text-destructive sm:w-auto"
                                                disabled={
                                                    pendingAction !== null
                                                }
                                                onClick={() =>
                                                    setRevokeSessionId(
                                                        session.id,
                                                    )
                                                }
                                            >
                                                {isRevoking ? (
                                                    <Spinner />
                                                ) : (
                                                    <LogOutIcon data-icon="inline-start" />
                                                )}
                                                Revoke
                                            </Button>
                                        ) : currentSession?.id ===
                                          session.id ? (
                                            <p className="text-sm text-muted-foreground">
                                                This device stays signed in.
                                            </p>
                                        ) : null}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </SectionCard>
            </div>

            <AlertDialog
                open={showRevokeOthersDialog}
                onOpenChange={setShowRevokeOthersDialog}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogMedia>
                            <LogOutIcon />
                        </AlertDialogMedia>
                        <AlertDialogTitle>
                            Log out other sessions?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Every device except this one will be signed out
                            immediately and must log in again.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={pendingAction !== null}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            disabled={pendingAction !== null}
                            onClick={() => void handleRevokeOtherSessions()}
                        >
                            {pendingAction === 'others' ? (
                                <Spinner />
                            ) : (
                                'Log out others'
                            )}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog
                open={revokeSessionId !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setRevokeSessionId(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogMedia>
                            <LogOutIcon />
                        </AlertDialogMedia>
                        <AlertDialogTitle>
                            Revoke this session?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            The selected device will be signed out immediately.
                            Use this if you no longer recognize or trust it.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={pendingAction !== null}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            disabled={
                                revokeSessionId === null ||
                                pendingAction !== null
                            }
                            onClick={() => {
                                if (revokeSessionId) {
                                    void handleRevokeSession(revokeSessionId);
                                }
                            }}
                        >
                            {pendingAction === revokeSessionId ? (
                                <Spinner />
                            ) : (
                                'Revoke session'
                            )}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
