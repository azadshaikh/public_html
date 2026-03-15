import { Link, router, usePage } from '@inertiajs/react';
import {
    BanIcon,
    CheckCircleIcon,
    ExternalLinkIcon,
    GlobeIcon,
    MailCheckIcon,
    MailIcon,
    MapPinIcon,
    PauseCircleIcon,
    PencilIcon,
    RefreshCwIcon,
    ShieldCheckIcon,
    Trash2Icon,
    UserCogIcon,
    UserIcon,
    ZapIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { NotesPanel } from '@/components/notes/notes-panel';
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
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { UsersShowPageProps } from '@/types/user-management';

// =========================================================================
// HELPERS
// =========================================================================

/**
 * Compact label + value row used inside the Command Center identity/lifecycle grids.
 * Renders "—" when value is null/undefined/empty so optional fields remain visible.
 */
function InfoRow({
    label,
    value,
}: {
    label: string;
    value: ReactNode;
}) {
    return (
        <div className="flex items-start gap-2 py-1">
            <span className="w-28 shrink-0 text-xs text-muted-foreground">
                {label}
            </span>
            <span className="min-w-0 break-words text-xs font-medium text-foreground">
                {value ?? '—'}
            </span>
        </div>
    );
}

/**
 * External link chip used in the Social Links card.
 */
function SocialLink({ url, label }: { url: string | null; label: string }) {
    if (!url) return null;

    return (
        <a
            href={url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
            <ExternalLinkIcon className="size-3.5 shrink-0" />
            {label}
        </a>
    );
}

// =========================================================================
// PAGE COMPONENT
// =========================================================================

export default function UsersShow({
    user,
    userActivities,
    notes,
    noteTarget,
    noteVisibilityOptions,
}: UsersShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const getInitials = useInitials();

    const canEdit = page.props.auth.abilities.editUsers;
    const canDelete = page.props.auth.abilities.deleteUsers;
    const canImpersonate =
        page.props.auth.user.id !== user.id && !!user.actions.impersonate;
    const isTrashed = user.deleted_at !== null;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Users', href: route('app.users.index') },
        { title: user.name, href: route('app.users.show', user.id) },
    ];

    const handleAction = (actionKey: string) => {
        const action = user.actions[actionKey];
        if (!action) return;

        if (action.confirm && !window.confirm(action.confirm)) return;

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

    const addressParts = [
        user.address1,
        user.address2,
        [user.city, user.state, user.zip].filter(Boolean).join(', '),
        user.country,
    ].filter(Boolean);

    const socialLinks = [
        { url: user.website_url, label: 'Website' },
        { url: user.twitter_url, label: 'Twitter / X' },
        { url: user.facebook_url, label: 'Facebook' },
        { url: user.instagram_url, label: 'Instagram' },
        { url: user.linkedin_url, label: 'LinkedIn' },
    ].filter((l) => l.url);

    const hasDangerActions =
        !isTrashed &&
        (!!user.actions.suspend ||
            !!user.actions.ban ||
            (canDelete && !!user.actions.delete));

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={user.name}
            description="View user account details, activity, and notes."
            headerActions={
                <div className="flex items-center gap-2">
                    {canEdit && !isTrashed && user.actions.edit && (
                        <Button asChild>
                            <Link href={user.actions.edit.url}>
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    )}
                    <Button variant="outline" asChild>
                        <Link href={route('app.users.index')}>← Back</Link>
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {/* ── Top grid: Command Center + Operations ── */}
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    {/* ── Command Center ── */}
                    <Card>
                        <CardHeader className="pb-3">
                            <div className="flex items-start justify-between gap-4">
                                <CardTitle className="text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                                    Command Center
                                </CardTitle>
                                <div className="text-right text-xs text-muted-foreground">
                                    <div>User ID {user.id}</div>
                                    <div>
                                        Last access:{' '}
                                        {user.last_access_human ?? 'Never'}
                                    </div>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent className="flex flex-col gap-5">
                            {/* Avatar + identity header */}
                            <div className="flex items-start gap-4">
                                <Avatar className="size-14 shrink-0 overflow-hidden rounded-full">
                                    <AvatarImage
                                        src={user.avatar_url ?? undefined}
                                        alt={user.name}
                                    />
                                    <AvatarFallback className="rounded-full bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                        {getInitials(user.name)}
                                    </AvatarFallback>
                                </Avatar>

                                <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                                    <div>
                                        <h2 className="font-semibold text-foreground">
                                            {user.name}
                                        </h2>
                                        <p className="text-xs text-muted-foreground">
                                            {user.email}
                                        </p>
                                    </div>

                                    <p className="text-xs text-muted-foreground">
                                        Operational snapshot for account
                                        moderation and lifecycle actions.
                                    </p>

                                    {/* Status + role badges */}
                                    <div className="flex flex-wrap gap-1.5">
                                        <Badge
                                            variant={
                                                user.status_badge ?? 'outline'
                                            }
                                        >
                                            {user.status_label}
                                        </Badge>

                                        {isTrashed && (
                                            <Badge variant="destructive">
                                                Trashed
                                            </Badge>
                                        )}

                                        {user.roles.map((role) => (
                                            <Badge
                                                key={role}
                                                variant="outline"
                                                className="gap-1"
                                            >
                                                <ShieldCheckIcon className="size-3" />
                                                Role: {role}
                                            </Badge>
                                        ))}

                                        <Badge
                                            variant={
                                                user.email_verified
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                            className="gap-1"
                                        >
                                            <MailIcon className="size-3" />
                                            Email:{' '}
                                            {user.email_verified
                                                ? 'Verified'
                                                : 'Unverified'}
                                        </Badge>
                                    </div>
                                </div>
                            </div>

                            <Separator />

                            {/* Identity + Lifecycle grids */}
                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div>
                                    <p className="mb-2 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                                        Identity
                                    </p>
                                    <InfoRow
                                        label="Username"
                                        value={
                                            user.username
                                                ? `@${user.username}`
                                                : null
                                        }
                                    />
                                    <InfoRow
                                        label="Gender"
                                        value={
                                            user.gender
                                                ? user.gender
                                                      .charAt(0)
                                                      .toUpperCase() +
                                                  user.gender.slice(1)
                                                : null
                                        }
                                    />
                                    <InfoRow
                                        label="Birth Date"
                                        value={user.birth_date}
                                    />
                                    <InfoRow
                                        label="Tagline"
                                        value={user.tagline}
                                    />
                                </div>

                                <div>
                                    <p className="mb-2 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                                        Lifecycle
                                    </p>
                                    <InfoRow
                                        label="Registered"
                                        value={user.created_at_formatted}
                                    />
                                    <InfoRow
                                        label="Updated"
                                        value={
                                            user.updated_at_human ??
                                            user.updated_at_formatted
                                        }
                                    />
                                    <InfoRow
                                        label="Email Verified"
                                        value={user.email_verified_at_formatted}
                                    />
                                    <InfoRow
                                        label="Last Access"
                                        value={user.last_access_formatted}
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* ── Operations ── */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <ZapIcon className="size-4 text-muted-foreground" />
                                <CardTitle>Operations</CardTitle>
                            </div>
                            <CardDescription>
                                High-impact actions for account access,
                                verification, and moderation.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="flex flex-col gap-2">
                            {/* Primary actions */}
                            {canEdit && !isTrashed && user.actions.edit && (
                                <Button
                                    variant="outline"
                                    className="w-full justify-start"
                                    asChild
                                >
                                    <Link href={user.actions.edit.url}>
                                        <PencilIcon data-icon="inline-start" />
                                        Edit User
                                    </Link>
                                </Button>
                            )}

                            {canImpersonate && (
                                <Button
                                    variant="outline"
                                    className="w-full justify-start"
                                    onClick={() => handleAction('impersonate')}
                                >
                                    <UserCogIcon data-icon="inline-start" />
                                    Impersonate
                                </Button>
                            )}

                            {user.actions.unban && (
                                <Button
                                    variant="outline"
                                    className="w-full justify-start"
                                    onClick={() => handleAction('unban')}
                                >
                                    <CheckCircleIcon data-icon="inline-start" />
                                    Unban
                                </Button>
                            )}

                            {user.actions.verify_email && (
                                <Button
                                    variant="outline"
                                    className="w-full justify-start"
                                    onClick={() => handleAction('verify_email')}
                                >
                                    <MailCheckIcon data-icon="inline-start" />
                                    Verify Email
                                </Button>
                            )}

                            {/* Danger zone */}
                            {hasDangerActions && (
                                <>
                                    <Separator className="my-1" />

                                    {user.actions.suspend && (
                                        <Button
                                            variant="outline"
                                            className="w-full justify-start border-orange-200 text-orange-700 hover:bg-orange-50 hover:text-orange-800 dark:border-orange-800 dark:text-orange-400 dark:hover:bg-orange-950"
                                            onClick={() =>
                                                handleAction('suspend')
                                            }
                                        >
                                            <PauseCircleIcon data-icon="inline-start" />
                                            Suspend
                                        </Button>
                                    )}

                                    {user.actions.ban && (
                                        <Button
                                            variant="outline"
                                            className="border-destructive/30 w-full justify-start text-destructive hover:bg-destructive/5"
                                            onClick={() => handleAction('ban')}
                                        >
                                            <BanIcon data-icon="inline-start" />
                                            Ban
                                        </Button>
                                    )}

                                    {canDelete && user.actions.delete && (
                                        <Button
                                            variant="outline"
                                            className="border-destructive/30 w-full justify-start text-destructive hover:bg-destructive/5"
                                            onClick={() =>
                                                handleAction('delete')
                                            }
                                        >
                                            <Trash2Icon data-icon="inline-start" />
                                            Move to Trash
                                        </Button>
                                    )}
                                </>
                            )}

                            {/* Trashed actions */}
                            {isTrashed && (
                                <>
                                    {user.actions.restore && (
                                        <Button
                                            variant="outline"
                                            className="w-full justify-start"
                                            onClick={() =>
                                                handleAction('restore')
                                            }
                                        >
                                            <RefreshCwIcon data-icon="inline-start" />
                                            Restore
                                        </Button>
                                    )}

                                    {canDelete && user.actions.force_delete && (
                                        <Button
                                            variant="outline"
                                            className="border-destructive/30 w-full justify-start text-destructive hover:bg-destructive/5"
                                            onClick={() =>
                                                handleAction('force_delete')
                                            }
                                        >
                                            <Trash2Icon data-icon="inline-start" />
                                            Delete Permanently
                                        </Button>
                                    )}
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* ── Tabs ── */}
                <Tabs defaultValue="general">
                    <TabsList variant="line">
                        <TabsTrigger value="general">General</TabsTrigger>
                        <TabsTrigger value="notes">
                            Notes ({notes.length})
                        </TabsTrigger>
                        <TabsTrigger value="activity">
                            Activity ({userActivities.length})
                        </TabsTrigger>
                    </TabsList>

                    {/* General Tab */}
                    <TabsContent value="general">
                        <div className="grid gap-6 lg:grid-cols-2">
                            {/* Roles & Permissions */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        Roles &amp; Permissions
                                    </CardTitle>
                                    <CardDescription>
                                        Assigned roles for this user account.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {user.roles.length > 0 ? (
                                        <div className="flex flex-wrap gap-2">
                                            {user.roles.map((role) => (
                                                <Badge
                                                    key={role}
                                                    variant="outline"
                                                    className="gap-1.5"
                                                >
                                                    <ShieldCheckIcon className="size-3" />
                                                    {role}
                                                </Badge>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            No roles assigned.
                                        </p>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Bio */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Bio</CardTitle>
                                    <CardDescription>
                                        User&apos;s biographical information.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {user.bio ? (
                                        <p className="whitespace-pre-wrap text-sm text-foreground">
                                            {user.bio}
                                        </p>
                                    ) : (
                                        <div className="flex flex-col items-center gap-2 py-6 text-muted-foreground">
                                            <UserIcon className="size-8 opacity-30" />
                                            <p className="text-sm">
                                                No bio provided
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Contact & Address */}
                            {(user.phone !== null ||
                                addressParts.length > 0) && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            Contact &amp; Address
                                        </CardTitle>
                                        <CardDescription>
                                            Primary address and contact details.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="flex flex-col gap-4">
                                        {user.phone && (
                                            <div className="flex items-center gap-3 text-sm">
                                                <MailIcon className="size-4 shrink-0 text-muted-foreground" />
                                                <span>{user.phone}</span>
                                            </div>
                                        )}

                                        {addressParts.length > 0 && (
                                            <div className="flex items-start gap-3">
                                                <MapPinIcon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                                <div className="flex flex-col gap-0.5 text-sm text-foreground">
                                                    {user.address1 && (
                                                        <span>
                                                            {user.address1}
                                                        </span>
                                                    )}
                                                    {user.address2 && (
                                                        <span>
                                                            {user.address2}
                                                        </span>
                                                    )}
                                                    {(user.city ||
                                                        user.state ||
                                                        user.zip) && (
                                                        <span>
                                                            {[
                                                                user.city,
                                                                user.state,
                                                                user.zip,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(', ')}
                                                        </span>
                                                    )}
                                                    {user.country && (
                                                        <span>
                                                            {user.country}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            )}

                            {/* Social Links */}
                            {socialLinks.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Social Links</CardTitle>
                                        <CardDescription>
                                            External profiles and websites.
                                        </CardDescription>
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
                    </TabsContent>

                    {/* Notes Tab */}
                    <TabsContent value="notes">
                        <NotesPanel
                            notes={notes}
                            noteTarget={noteTarget}
                            noteVisibilityOptions={noteVisibilityOptions}
                            title="User notes"
                            description="Keep internal context, follow-ups, and account-specific reminders in one place."
                        />
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
                                                <div className="bg-muted mt-0.5 rounded-full p-1.5">
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
