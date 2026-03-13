import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    BanIcon,
    CalendarIcon,
    CheckCircleIcon,
    ClockIcon,
    ExternalLinkIcon,
    GlobeIcon,
    MailIcon,
    PauseCircleIcon,
    PencilIcon,
    RefreshCwIcon,
    ShieldAlertIcon,
    ShieldCheckIcon,
    Trash2Icon,
    UserCogIcon,
    UserIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { ResourceFeedbackAlerts } from '@/components/resource/resource-feedback-alerts';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/index';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { UsersShowPageProps } from '@/types/user-management';

// =========================================================================
// HELPER COMPONENTS
// =========================================================================

function DetailRow({
    label,
    value,
    icon,
}: {
    label: string;
    value: ReactNode;
    icon?: ReactNode;
}) {
    if (!value) return null;

    return (
        <div className="flex items-start gap-3 py-2">
            {icon && (
                <span className="mt-0.5 text-muted-foreground">{icon}</span>
            )}
            <div className="flex min-w-0 flex-col gap-0.5">
                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </span>
                <span className="text-sm text-foreground">{value}</span>
            </div>
        </div>
    );
}

function SocialLink({ url, label }: { url: string | null; label: string }) {
    if (!url) return null;

    return (
        <a
            href={url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
            <ExternalLinkIcon className="size-3.5" />
            {label}
        </a>
    );
}

// =========================================================================
// COMPONENT
// =========================================================================

export default function UsersShow({
    user,
    userActivities,
    status,
    error,
}: UsersShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const getInitials = useInitials();

    const canEdit = page.props.auth.abilities.editUsers;
    const canDelete = page.props.auth.abilities.deleteUsers;
    const canImpersonate =
        page.props.auth.user.id !== user.id && user.actions.impersonate;
    const isTrashed = user.deleted_at !== null;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Users', href: UserController.index() },
        { title: user.name, href: UserController.show(user.id) },
    ];

    const handleAction = (
        actionKey: string,
        options?: { fullReload?: boolean },
    ) => {
        const action = user.actions[actionKey];
        if (!action) return;

        if (action.confirm && !window.confirm(action.confirm)) return;

        if (options?.fullReload || action.fullReload) {
            window.location.href = action.url;
            return;
        }

        const method = action.method.toLowerCase() as
            | 'get'
            | 'post'
            | 'put'
            | 'patch'
            | 'delete';

        if (method === 'get') {
            router.get(action.url, {}, { preserveScroll: true });
        } else {
            router[method](action.url, {}, { preserveScroll: true });
        }
    };

    const socialLinks = [
        { url: user.website_url, label: 'Website' },
        { url: user.twitter_url, label: 'Twitter / X' },
        { url: user.facebook_url, label: 'Facebook' },
        { url: user.instagram_url, label: 'Instagram' },
        { url: user.linkedin_url, label: 'LinkedIn' },
    ].filter((link) => link.url);

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={user.name}
            description={`User profile and management`}
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={UserController.index()}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>

                    {canEdit && !isTrashed && user.actions.edit && (
                        <Button asChild>
                            <Link href={user.actions.edit.url}>
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <ResourceFeedbackAlerts
                    status={status}
                    statusIcon={<ShieldCheckIcon />}
                    error={error}
                    errorIcon={<ShieldAlertIcon />}
                />

                {/* User identity header */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-col items-start gap-6 sm:flex-row">
                            <Avatar className="size-20 overflow-hidden rounded-full">
                                <AvatarImage
                                    src={user.avatar_url ?? undefined}
                                    alt={user.name}
                                />
                                <AvatarFallback className="rounded-lg bg-neutral-200 text-2xl text-black dark:bg-neutral-700 dark:text-white">
                                    {getInitials(user.name)}
                                </AvatarFallback>
                            </Avatar>

                            <div className="flex min-w-0 flex-1 flex-col gap-2">
                                <div className="flex flex-wrap items-center gap-3">
                                    <h2 className="text-xl font-semibold text-foreground">
                                        {user.name}
                                    </h2>
                                    <Badge
                                        variant={user.status_badge ?? 'outline'}
                                    >
                                        {user.status_label}
                                    </Badge>
                                    {isTrashed && (
                                        <Badge variant="destructive">
                                            Trashed
                                        </Badge>
                                    )}
                                </div>

                                <p className="text-sm text-muted-foreground">
                                    {user.email}
                                    {user.username && (
                                        <span className="ml-2 text-xs">
                                            @{user.username}
                                        </span>
                                    )}
                                </p>

                                {user.tagline && (
                                    <p className="text-sm text-muted-foreground">
                                        {user.tagline}
                                    </p>
                                )}

                                <div className="mt-1 flex flex-wrap gap-1">
                                    {user.roles.map((role) => (
                                        <Badge key={role} variant="outline">
                                            {role}
                                        </Badge>
                                    ))}
                                </div>
                            </div>

                            {/* Action buttons */}
                            <div className="flex flex-wrap gap-2">
                                {canImpersonate && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handleAction('impersonate', {
                                                fullReload: true,
                                            })
                                        }
                                    >
                                        <UserCogIcon data-icon="inline-start" />
                                        Impersonate
                                    </Button>
                                )}

                                {!isTrashed && user.actions.suspend && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleAction('suspend')}
                                    >
                                        <PauseCircleIcon data-icon="inline-start" />
                                        Suspend
                                    </Button>
                                )}

                                {!isTrashed && user.actions.ban && (
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => handleAction('ban')}
                                    >
                                        <BanIcon data-icon="inline-start" />
                                        Ban
                                    </Button>
                                )}

                                {user.actions.unban && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleAction('unban')}
                                    >
                                        <CheckCircleIcon data-icon="inline-start" />
                                        Unban
                                    </Button>
                                )}

                                {isTrashed && user.actions.restore && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleAction('restore')}
                                    >
                                        <RefreshCwIcon data-icon="inline-start" />
                                        Restore
                                    </Button>
                                )}

                                {canDelete && user.actions.delete && (
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => handleAction('delete')}
                                    >
                                        <Trash2Icon data-icon="inline-start" />
                                        Trash
                                    </Button>
                                )}

                                {canDelete && user.actions.force_delete && (
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() =>
                                            handleAction('force_delete')
                                        }
                                    >
                                        <Trash2Icon data-icon="inline-start" />
                                        Delete
                                    </Button>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Tabbed content */}
                <Tabs defaultValue="overview">
                    <TabsList variant="line">
                        <TabsTrigger value="overview">Overview</TabsTrigger>
                        <TabsTrigger value="activity">
                            Activity ({userActivities.length})
                        </TabsTrigger>
                    </TabsList>

                    {/* Overview Tab */}
                    <TabsContent value="overview">
                        <div className="grid gap-6 lg:grid-cols-2">
                            {/* Personal Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Personal Information</CardTitle>
                                    <CardDescription>
                                        Basic user details and contact
                                        information.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="divide-y">
                                        <DetailRow
                                            label="Full Name"
                                            value={user.full_name}
                                            icon={
                                                <UserIcon className="size-4" />
                                            }
                                        />
                                        <DetailRow
                                            label="Email"
                                            value={
                                                <span className="flex items-center gap-2">
                                                    {user.email}
                                                    <Badge
                                                        variant={
                                                            user.email_verified
                                                                ? 'secondary'
                                                                : 'outline'
                                                        }
                                                        className="text-[0.65rem]"
                                                    >
                                                        {user.email_verified
                                                            ? 'Verified'
                                                            : 'Unverified'}
                                                    </Badge>
                                                </span>
                                            }
                                            icon={
                                                <MailIcon className="size-4" />
                                            }
                                        />
                                        {user.username && (
                                            <DetailRow
                                                label="Username"
                                                value={`@${user.username}`}
                                                icon={
                                                    <UserIcon className="size-4" />
                                                }
                                            />
                                        )}
                                        {user.phone && (
                                            <DetailRow
                                                label="Phone"
                                                value={user.phone}
                                            />
                                        )}
                                        {user.gender && (
                                            <DetailRow
                                                label="Gender"
                                                value={
                                                    user.gender
                                                        .charAt(0)
                                                        .toUpperCase() +
                                                    user.gender.slice(1)
                                                }
                                            />
                                        )}
                                        {user.birth_date && (
                                            <DetailRow
                                                label="Birth Date"
                                                value={user.birth_date}
                                            />
                                        )}
                                        {user.bio && (
                                            <DetailRow
                                                label="Bio"
                                                value={user.bio}
                                            />
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Account Details */}
                            <div className="flex flex-col gap-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Account Details</CardTitle>
                                        <CardDescription>
                                            Status, roles, and important dates.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="divide-y">
                                            <DetailRow
                                                label="Status"
                                                value={
                                                    <Badge
                                                        variant={
                                                            user.status_badge ??
                                                            'outline'
                                                        }
                                                    >
                                                        {user.status_label}
                                                    </Badge>
                                                }
                                                icon={
                                                    <ShieldCheckIcon className="size-4" />
                                                }
                                            />
                                            <DetailRow
                                                label="Roles"
                                                value={
                                                    <div className="flex flex-wrap gap-1">
                                                        {user.roles.map(
                                                            (role) => (
                                                                <Badge
                                                                    key={role}
                                                                    variant="outline"
                                                                >
                                                                    {role}
                                                                </Badge>
                                                            ),
                                                        )}
                                                    </div>
                                                }
                                            />
                                            <DetailRow
                                                label="Registered"
                                                value={
                                                    <span
                                                        title={user.created_at}
                                                    >
                                                        {
                                                            user.created_at_formatted
                                                        }{' '}
                                                        <span className="text-xs text-muted-foreground">
                                                            (
                                                            {
                                                                user.created_at_human
                                                            }
                                                            )
                                                        </span>
                                                    </span>
                                                }
                                                icon={
                                                    <CalendarIcon className="size-4" />
                                                }
                                            />
                                            {user.last_access_human && (
                                                <DetailRow
                                                    label="Last Access"
                                                    value={
                                                        user.last_access_human
                                                    }
                                                    icon={
                                                        <ClockIcon className="size-4" />
                                                    }
                                                />
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Social Links */}
                                {socialLinks.length > 0 && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Social Links</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="flex flex-col gap-2">
                                                {socialLinks.map((link) => (
                                                    <SocialLink
                                                        key={link.label}
                                                        url={link.url}
                                                        label={link.label}
                                                    />
                                                ))}
                                            </div>
                                        </CardContent>
                                    </Card>
                                )}
                            </div>
                        </div>
                    </TabsContent>

                    {/* Activity Tab */}
                    <TabsContent value="activity">
                        <Card>
                            <CardHeader>
                                <CardTitle>Activity Log</CardTitle>
                                <CardDescription>
                                    Recent activity for this user account (last
                                    50 entries).
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {userActivities.length === 0 ? (
                                    <p className="py-8 text-center text-sm text-muted-foreground">
                                        No activity recorded yet.
                                    </p>
                                ) : (
                                    <div className="divide-y">
                                        {userActivities.map((activity) => (
                                            <div
                                                key={activity.id}
                                                className="flex items-start gap-3 py-3"
                                            >
                                                <div className="mt-0.5 rounded-full bg-muted p-1.5">
                                                    <GlobeIcon className="size-3.5 text-muted-foreground" />
                                                </div>
                                                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                                    <p className="text-sm text-foreground">
                                                        {activity.description}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        by{' '}
                                                        {activity.causer_name}
                                                        {activity.created_at_human && (
                                                            <>
                                                                {' '}
                                                                &middot;{' '}
                                                                <span
                                                                    title={
                                                                        activity.created_at ??
                                                                        undefined
                                                                    }
                                                                >
                                                                    {
                                                                        activity.created_at_human
                                                                    }
                                                                </span>
                                                            </>
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
