import { Link } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    BellIcon,
    BellOffIcon,
    CheckCircle2Icon,
    FileTextIcon,
    GlobeIcon,
    RadioTowerIcon,
    SaveIcon,
    ShieldAlertIcon,
    TriangleAlertIcon,
    UserIcon,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type {
    NotificationPreferenceOption,
    NotificationsPreferencesPageProps,
} from '@/types/notification';

type NotificationPreferencesFormData = {
    notifications_enabled: boolean;
    preferences: {
        categories: Record<string, boolean>;
        priorities: Record<string, boolean>;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Notifications', href: route('app.notifications.index') },
    {
        title: 'Preferences',
        href: route('app.notifications.preferences'),
    },
];

const priorityDescriptions: Record<string, string> = {
    high: 'Critical alerts that usually need attention right away.',
    medium: 'Important updates that are useful to review soon.',
    low: 'Routine activity and lower urgency updates.',
};

const categoryDescriptions: Record<string, string> = {
    system: 'Security, maintenance, and account-protection activity.',
    website: 'Announcements and updates related to the website experience.',
    user: 'Changes tied to your account, profile, and access.',
    cms: 'Content and editorial workflow activity from content modules.',
    broadcast: 'Wide announcements sent to multiple users at once.',
};

const priorityIcons: Record<string, ReactNode> = {
    high: <TriangleAlertIcon className="size-4 text-destructive" />,
    medium: <BellIcon className="size-4 text-amber-600" />,
    low: <CheckCircle2Icon className="size-4 text-emerald-600" />,
};

const categoryIcons: Record<string, ReactNode> = {
    system: <ShieldAlertIcon className="size-4 text-destructive" />,
    website: <GlobeIcon className="size-4 text-sky-600" />,
    user: <UserIcon className="size-4 text-blue-600" />,
    cms: <FileTextIcon className="size-4 text-emerald-600" />,
    broadcast: <RadioTowerIcon className="size-4 text-amber-600" />,
};

function PreferenceRow({
    id,
    title,
    description,
    icon,
    checked,
    onCheckedChange,
}: {
    id: string;
    title: string;
    description: string;
    icon: ReactNode;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <Field>
            <div className="flex items-start justify-between gap-4 rounded-xl border bg-muted/15 px-4 py-4">
                <div className="min-w-0 space-y-1.5">
                    <div className="flex items-center gap-2">
                        <div className="flex size-8 shrink-0 items-center justify-center rounded-lg border bg-background">
                            {icon}
                        </div>
                        <FieldLabel htmlFor={id}>{title}</FieldLabel>
                    </div>
                    <FieldDescription className="pl-10">
                        {description}
                    </FieldDescription>
                </div>
                <Switch
                    id={id}
                    checked={checked}
                    onCheckedChange={(value) => onCheckedChange(value === true)}
                    size="comfortable"
                />
            </div>
        </Field>
    );
}

function PreferenceCard({
    title,
    description,
    badge,
    options,
    values,
    descriptions,
    icons,
    onToggle,
}: {
    title: string;
    description: string;
    badge: string;
    options: NotificationPreferenceOption[];
    values: Record<string, boolean>;
    descriptions: Record<string, string>;
    icons: Record<string, ReactNode>;
    onToggle: (key: string, checked: boolean) => void;
}) {
    return (
        <Card className="py-6">
            <CardHeader className="px-6">
                <div className="flex items-start justify-between gap-4">
                    <div className="space-y-1">
                        <CardTitle>{title}</CardTitle>
                        <CardDescription>{description}</CardDescription>
                    </div>
                    <Badge variant="outline">{badge}</Badge>
                </div>
            </CardHeader>
            <CardContent className="px-6">
                <div className="space-y-3">
                    {options.map((option) => (
                        <PreferenceRow
                            key={option.value}
                            id={`${title}-${option.value}`}
                            title={option.label}
                            description={
                                descriptions[option.value] ??
                                'This preference helps tailor what reaches your inbox.'
                            }
                            icon={
                                icons[option.value] ?? (
                                    <BellIcon className="size-4" />
                                )
                            }
                            checked={values[option.value] ?? false}
                            onCheckedChange={(checked) =>
                                onToggle(option.value, checked)
                            }
                        />
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

export default function NotificationsPreferences({
    notificationsEnabled,
    preferences,
    categoryPreferences,
    priorityPreferences,
}: NotificationsPreferencesPageProps) {
    const form = useAppForm<NotificationPreferencesFormData>({
        defaults: {
            notifications_enabled: notificationsEnabled,
            preferences,
        },
        rememberKey: 'notifications.preferences',
        dirtyGuard: {
            enabled: true,
        },
    });

    const enabledCategoryCount = Object.values(
        form.data.preferences.categories,
    ).filter(Boolean).length;
    const enabledPriorityCount = Object.values(
        form.data.preferences.priorities,
    ).filter(Boolean).length;

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('put', route('app.notifications.preferences.update'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Preferences updated',
                description:
                    'Your notification delivery settings were saved successfully.',
            },
        });
    };

    const updatePreferenceGroup = (
        group: 'categories' | 'priorities',
        key: string,
        checked: boolean,
    ) => {
        form.setField('preferences', {
            ...form.data.preferences,
            [group]: {
                ...form.data.preferences[group],
                [key]: checked,
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Notification Preferences"
            description="Choose which alerts reach you and how much signal you want from your inbox."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.notifications.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <form
                    noValidate
                    className="flex flex-col gap-6"
                    onSubmit={handleSubmit}
                >
                    {form.dirtyGuardDialog}

                    <Card className="py-6">
                        <CardHeader className="px-6">
                            <div className="flex items-start justify-between gap-4">
                                <div className="space-y-1">
                                    <CardTitle>Notification delivery</CardTitle>
                                    <CardDescription>
                                        Pause everything at once or keep alerts
                                        on and fine-tune the priority and
                                        category mix below.
                                    </CardDescription>
                                </div>
                                <Badge
                                    variant={
                                        form.data.notifications_enabled
                                            ? 'success'
                                            : 'secondary'
                                    }
                                >
                                    {form.data.notifications_enabled
                                        ? 'Enabled'
                                        : 'Paused'}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4 px-6">
                            <Field>
                                <div className="flex items-start justify-between gap-4 rounded-xl border bg-muted/15 px-4 py-4">
                                    <div className="min-w-0 space-y-1.5">
                                        <div className="flex items-center gap-2">
                                            <div
                                                className={cn(
                                                    'flex size-8 shrink-0 items-center justify-center rounded-lg border bg-background',
                                                    form.data
                                                        .notifications_enabled
                                                        ? 'text-primary'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {form.data
                                                    .notifications_enabled ? (
                                                    <BellIcon className="size-4" />
                                                ) : (
                                                    <BellOffIcon className="size-4" />
                                                )}
                                            </div>
                                            <FieldLabel htmlFor="notifications_enabled">
                                                Receive notifications
                                            </FieldLabel>
                                        </div>
                                        <FieldDescription className="pl-10">
                                            Turn this off to pause inbox, email,
                                            and high-priority alerts for this
                                            account without losing your saved
                                            preferences.
                                        </FieldDescription>
                                    </div>
                                    <Switch
                                        id="notifications_enabled"
                                        checked={
                                            form.data.notifications_enabled
                                        }
                                        onCheckedChange={(checked) =>
                                            form.setField(
                                                'notifications_enabled',
                                                checked === true,
                                            )
                                        }
                                        size="comfortable"
                                    />
                                </div>
                            </Field>

                            <div className="grid gap-3 md:grid-cols-2">
                                <div className="rounded-xl border bg-muted/10 px-4 py-3">
                                    <p className="text-xs font-medium tracking-[0.14em] text-muted-foreground uppercase">
                                        Priorities enabled
                                    </p>
                                    <p className="mt-1 text-lg font-semibold text-foreground">
                                        {enabledPriorityCount}/
                                        {priorityPreferences.length}
                                    </p>
                                </div>
                                <div className="rounded-xl border bg-muted/10 px-4 py-3">
                                    <p className="text-xs font-medium tracking-[0.14em] text-muted-foreground uppercase">
                                        Categories enabled
                                    </p>
                                    <p className="mt-1 text-lg font-semibold text-foreground">
                                        {enabledCategoryCount}/
                                        {categoryPreferences.length}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="grid gap-6 xl:grid-cols-2">
                        <PreferenceCard
                            title="Priority filters"
                            description="Control how much urgency reaches you without muting the rest of your inbox."
                            badge={`${enabledPriorityCount}/${priorityPreferences.length} on`}
                            options={priorityPreferences}
                            values={form.data.preferences.priorities}
                            descriptions={priorityDescriptions}
                            icons={priorityIcons}
                            onToggle={(key, checked) =>
                                updatePreferenceGroup(
                                    'priorities',
                                    key,
                                    checked,
                                )
                            }
                        />

                        <PreferenceCard
                            title="Category filters"
                            description="Tune the kinds of activity you want to hear about across the product."
                            badge={`${enabledCategoryCount}/${categoryPreferences.length} on`}
                            options={categoryPreferences}
                            values={form.data.preferences.categories}
                            descriptions={categoryDescriptions}
                            icons={categoryIcons}
                            onToggle={(key, checked) =>
                                updatePreferenceGroup(
                                    'categories',
                                    key,
                                    checked,
                                )
                            }
                        />
                    </div>

                    <div className="flex flex-col gap-3">
                        <Button
                            type="submit"
                            size="comfortable"
                            className="w-full"
                            disabled={form.processing}
                        >
                            <SaveIcon data-icon="inline-start" />
                            Save Preferences
                        </Button>

                        {form.isDirty ? (
                            <p className="text-center text-sm text-muted-foreground">
                                You have unsaved notification preference
                                changes.
                            </p>
                        ) : null}
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
