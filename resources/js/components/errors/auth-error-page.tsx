import { Link, type InertiaLinkProps, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    LayoutDashboard,
    type LucideIcon,
    LogIn,
    LogOut,
    RefreshCw,
} from 'lucide-react';
import type { ComponentProps } from 'react';
import AppHead from '@/components/app-head';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import AuthLayout from '@/layouts/auth-layout';
import { dashboard, login, logout } from '@/routes/index';
import type { SharedData } from '@/types';

type ButtonVariant = NonNullable<ComponentProps<typeof Button>['variant']>;

type ErrorLinkAction = {
    label: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
    method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
    variant?: ButtonVariant;
};

type AuthErrorPageProps = {
    status: number;
    title: string;
    description: string;
    defaultMessage: string;
    message?: string | null;
    icon: LucideIcon;
    iconClassName?: string;
    accentClassName?: string;
    actions?: ErrorLinkAction[];
    showBackButton?: boolean;
    showReloadButton?: boolean;
    showLogoutButton?: boolean;
};

function ErrorLinkButton({
    label,
    href,
    icon: Icon,
    method = 'get',
    variant = 'outline',
}: ErrorLinkAction) {
    return (
        <Button asChild variant={variant} className="w-full sm:w-auto">
            <Link href={href} method={method} as={method === 'get' ? 'a' : 'button'}>
                <Icon className="size-4" />
                {label}
            </Link>
        </Button>
    );
}

export default function AuthErrorPage({
    status,
    title,
    description,
    defaultMessage,
    message,
    icon: Icon,
    iconClassName,
    accentClassName,
    actions = [],
    showBackButton = true,
    showReloadButton = false,
    showLogoutButton = false,
}: AuthErrorPageProps) {
    const { auth } = usePage<SharedData>().props;

    const primaryAction: ErrorLinkAction = auth.user
        ? {
              label: 'Go to dashboard',
              href: dashboard(),
              icon: LayoutDashboard,
              variant: 'default',
          }
        : {
              label: 'Go to login',
              href: login(),
              icon: LogIn,
              variant: 'default',
          };

    const resolvedMessage = message?.trim() ? message : defaultMessage;

    return (
        <AuthLayout
            title={`${status} · ${title}`}
            description={description}
            maxWidthClassName="max-w-2xl"
        >
            <AppHead title={`${status} ${title}`} description={description} />

            <div className="overflow-hidden rounded-3xl border border-border/70 bg-card shadow-sm">
                <div className={cn('h-1 w-full bg-primary', accentClassName)} />

                <div className="flex flex-col gap-8 p-6 sm:p-10">
                    <div className="flex flex-col items-center gap-4 text-center">
                        <div
                            className={cn(
                                'flex size-16 items-center justify-center rounded-full border border-primary/15 bg-primary/10 text-primary',
                                iconClassName,
                            )}
                        >
                            <Icon className="size-7" />
                        </div>

                        <div className="inline-flex items-center rounded-full border border-border/70 px-3 py-1 text-[0.7rem] font-semibold tracking-[0.24em] text-muted-foreground uppercase">
                            Error {status}
                        </div>

                        <div className="space-y-3">
                            <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                {title}
                            </h2>

                            <p className="mx-auto max-w-xl text-sm leading-6 text-muted-foreground sm:text-base">
                                {resolvedMessage}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                        <ErrorLinkButton {...primaryAction} />

                        {actions.map((action) => (
                            <ErrorLinkButton
                                key={`${action.label}-${action.method ?? 'get'}`}
                                {...action}
                            />
                        ))}

                        {showReloadButton ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full sm:w-auto"
                                onClick={() => window.location.reload()}
                            >
                                <RefreshCw className="size-4" />
                                Try again
                            </Button>
                        ) : null}

                        {showBackButton ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full sm:w-auto"
                                onClick={() => window.history.back()}
                            >
                                <ArrowLeft className="size-4" />
                                Go back
                            </Button>
                        ) : null}

                        {showLogoutButton && auth.user ? (
                            <ErrorLinkButton
                                label="Log out"
                                href={logout()}
                                method="post"
                                icon={LogOut}
                                variant="destructive"
                            />
                        ) : null}
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}