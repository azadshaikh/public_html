import { Link, router } from '@inertiajs/react';
import { ArrowLeftIcon, GithubIcon, Link2Icon, ShieldCheckIcon, UnplugIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
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

type ConnectedProvider = {
    key: string;
    label: string;
    connected_at: string | null;
    connected_at_label: string;
};

type AvailableProvider = {
    key: string;
    label: string;
};

type SocialLoginsPageProps = {
    connectedProviders: ConnectedProvider[];
    availableProviders: AvailableProvider[];
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
        title: 'Social Login',
        href: route('app.profile.security.social-logins'),
    },
];

function ProviderIcon({
    provider,
    className,
}: {
    provider: string;
    className?: string;
}) {
    if (provider === 'google') {
        return (
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 48 48"
                aria-hidden="true"
                className={cn('size-5 shrink-0', className)}
            >
                <path
                    fill="#FFC107"
                    d="M43.611 20.083H42V20H24v8h11.303C33.655 32.657 29.195 36 24 36c-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.963 3.037l5.657-5.657C34.046 6.053 29.278 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"
                />
                <path
                    fill="#FF3D00"
                    d="M6.306 14.691 12.88 19.51A11.99 11.99 0 0 1 24 12c3.059 0 5.842 1.154 7.963 3.037l5.657-5.657C34.046 6.053 29.278 4 24 4c-7.682 0-14.353 4.337-17.694 10.691z"
                />
                <path
                    fill="#4CAF50"
                    d="M24 44c5.176 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.159 35.09 26.715 36 24 36c-5.175 0-9.628-3.327-11.286-7.946l-6.523 5.025C9.499 39.556 16.227 44 24 44z"
                />
                <path
                    fill="#1976D2"
                    d="M43.611 20.083H42V20H24v8h11.303a12.05 12.05 0 0 1-4.084 5.57l.003-.002 6.19 5.238C36.971 39.204 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"
                />
            </svg>
        );
    }

    return (
        <GithubIcon className={cn('size-5 shrink-0 text-foreground', className)} />
    );
}

function getProviderDescription(provider: string): string {
    return provider === 'google'
        ? 'Use your Google account for a fast sign-in without remembering another password.'
        : 'Use your GitHub account for a developer-friendly sign-in option.';
}

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

export default function SocialLogins({
    connectedProviders,
    availableProviders,
}: SocialLoginsPageProps) {
    const [connectingProvider, setConnectingProvider] = useState<string | null>(
        null,
    );
    const [disconnectingProvider, setDisconnectingProvider] = useState<
        string | null
    >(null);

    const handleConnect = (provider: string) => {
        setConnectingProvider(provider);
        window.location.assign(route('app.profile.security.social-logins.connect', { provider }));
    };

    const handleDisconnect = (provider: string) => {
        setDisconnectingProvider(provider);

        router.delete(route('app.profile.security.social-logins.disconnect', { provider }), {
            preserveScroll: true,
            onFinish: () => setDisconnectingProvider(null),
        });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Social Login"
            description="Connect trusted sign-in providers and remove ones you no longer use."
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
                    title="Connected accounts"
                    description="Review every social provider currently linked to your account and disconnect providers you no longer trust."
                    badge={
                        <Badge
                            variant={
                                connectedProviders.length > 0
                                    ? 'success'
                                    : 'secondary'
                            }
                        >
                            {connectedProviders.length > 0
                                ? `${connectedProviders.length} connected`
                                : 'No providers linked'}
                        </Badge>
                    }
                >
                    {connectedProviders.length === 0 ? (
                        <Empty className="border bg-muted/20 py-10">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Link2Icon />
                                </EmptyMedia>
                                <EmptyTitle>No connected social accounts</EmptyTitle>
                                <EmptyDescription>
                                    You&apos;re currently signing in with your
                                    email and password only. Connect a provider
                                    below if you want a quicker login option.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <div className="overflow-hidden rounded-xl border">
                            {connectedProviders.map((provider, index) => {
                                const isDisconnecting =
                                    disconnectingProvider === provider.key;

                                return (
                                    <div
                                        key={provider.key}
                                        className={cn(
                                            'flex flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between',
                                            index > 0 && 'border-t',
                                        )}
                                    >
                                        <div className="flex min-w-0 items-start gap-4">
                                            <div className="flex size-11 shrink-0 items-center justify-center rounded-xl border bg-muted/30">
                                                <ProviderIcon
                                                    provider={provider.key}
                                                />
                                            </div>

                                            <div className="min-w-0 space-y-1">
                                                <p className="font-semibold text-foreground">
                                                    {provider.label}
                                                </p>
                                                <p className="text-sm leading-6 text-muted-foreground">
                                                    Connected on{' '}
                                                    {provider.connected_at_label}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex flex-col gap-3 sm:items-end">
                                            <Badge variant="success">
                                                Connected
                                            </Badge>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={
                                                    isDisconnecting ||
                                                    connectingProvider !== null
                                                }
                                                className="w-full border-destructive/30 text-destructive hover:bg-destructive/10 hover:text-destructive sm:w-auto"
                                                onClick={() =>
                                                    handleDisconnect(
                                                        provider.key,
                                                    )
                                                }
                                            >
                                                {isDisconnecting ? (
                                                    <Spinner />
                                                ) : (
                                                    <UnplugIcon data-icon="inline-start" />
                                                )}
                                                Disconnect
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </SectionCard>

                <SectionCard
                    title="Available providers"
                    description="Add another trusted sign-in method. You’ll be redirected to the provider to approve access."
                    badge={
                        <Badge
                            variant={
                                availableProviders.length > 0
                                    ? 'info'
                                    : 'secondary'
                            }
                        >
                            {availableProviders.length > 0
                                ? `${availableProviders.length} available`
                                : 'All available providers connected'}
                        </Badge>
                    }
                >
                    {availableProviders.length === 0 ? (
                        <Empty className="border bg-muted/20 py-10">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <ShieldCheckIcon />
                                </EmptyMedia>
                                <EmptyTitle>Everything is already connected</EmptyTitle>
                                <EmptyDescription>
                                    Every enabled social provider is already
                                    linked to this account.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <div className="grid gap-3">
                            {availableProviders.map((provider) => {
                                const isConnecting =
                                    connectingProvider === provider.key;

                                return (
                                    <div
                                        key={provider.key}
                                        className="flex flex-col gap-4 rounded-xl border bg-muted/20 p-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="flex min-w-0 items-start gap-4">
                                            <div className="flex size-11 shrink-0 items-center justify-center rounded-xl border bg-background">
                                                <ProviderIcon
                                                    provider={provider.key}
                                                />
                                            </div>

                                            <div className="min-w-0 space-y-1">
                                                <p className="font-semibold text-foreground">
                                                    {provider.label}
                                                </p>
                                                <p className="text-sm leading-6 text-muted-foreground">
                                                    {getProviderDescription(
                                                        provider.key,
                                                    )}
                                                </p>
                                            </div>
                                        </div>

                                        <Button
                                            type="button"
                                            disabled={
                                                isConnecting ||
                                                disconnectingProvider !== null
                                            }
                                            className="w-full sm:w-auto"
                                            onClick={() =>
                                                handleConnect(provider.key)
                                            }
                                        >
                                            {isConnecting ? (
                                                <Spinner />
                                            ) : (
                                                <ProviderIcon
                                                    provider={provider.key}
                                                />
                                            )}
                                            Connect with {provider.label}
                                        </Button>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </SectionCard>
            </div>
        </AppLayout>
    );
}
