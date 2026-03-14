import { Link } from '@inertiajs/react';
import {
    ChevronRightIcon,
    Link2Icon,
    LockKeyholeIcon,
    ShieldCheckIcon,
    SmartphoneIcon,
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

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
];

type SecurityProps = {
    twoFactorEnabled: boolean;
    twoFactorPending: boolean;
    showSocialLoginCard: boolean;
    connectedProviderCount: number;
    activeSessionCount: number;
    sessionManagementSupported: boolean;
    hasPassword: boolean;
};

type SecurityCard = {
    title: string;
    description: string;
    href: string;
    icon: typeof LockKeyholeIcon;
    status?: {
        label: string;
        className: string;
    };
};

function SecurityStatus({
    label,
    className,
}: {
    label: string;
    className: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex h-5 items-center rounded-full px-2 text-[11px] font-semibold',
                className,
            )}
        >
            {label}
        </span>
    );
}

function SecurityCardLink({
    title,
    description,
    href,
    icon: Icon,
    status,
}: SecurityCard) {
    return (
        <Link
            href={href}
            className="group block rounded-xl border bg-card transition-all duration-150 hover:-translate-y-0.5 hover:border-foreground/20 hover:shadow-[0_14px_30px_-22px_rgba(15,23,42,0.4)]"
        >
            <div className="flex items-start justify-between gap-4 px-6 py-5">
                <div className="min-w-0 flex-1">
                    <h2 className="flex items-center gap-2 text-[1.05rem] font-semibold text-foreground">
                        <Icon className="size-[18px] shrink-0 text-foreground" />
                        <span>{title}</span>
                    </h2>

                    <p className="mt-3 text-sm leading-6 text-muted-foreground">
                        {description}
                    </p>

                    {status ? (
                        <div className="mt-4">
                            <SecurityStatus {...status} />
                        </div>
                    ) : null}
                </div>

                <ChevronRightIcon className="mt-1 size-5 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
            </div>
        </Link>
    );
}

export default function Security({
    twoFactorEnabled,
    twoFactorPending,
    showSocialLoginCard,
    connectedProviderCount,
    activeSessionCount,
    sessionManagementSupported,
    hasPassword,
}: SecurityProps) {
    const securityCards: SecurityCard[] = [
        {
            title: 'Password & Security',
            description: hasPassword
                ? 'Strengthen your account by using a strong password.'
                : 'Set a password to secure your account and sign-in options.',
            href: route('app.profile.security.password'),
            icon: LockKeyholeIcon,
        },
        {
            title: 'Two-Factor Authentication (2FA)',
            description: 'Manage two-factor authentication and recovery codes.',
            href: route('app.profile.security.two-factor'),
            icon: ShieldCheckIcon,
            status: twoFactorEnabled
                ? {
                      label: 'Enabled',
                      className:
                          'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                  }
                : twoFactorPending
                  ? {
                        label: 'Setup Pending',
                        className:
                            'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                    }
                  : {
                        label: 'Not Enabled',
                        className: 'bg-secondary text-secondary-foreground',
                    },
        },
    ];

    if (showSocialLoginCard) {
        securityCards.push({
            title: 'Social Login',
            description: 'Manage social providers connected to your account.',
            href: route('app.profile.security.social-logins'),
            icon: Link2Icon,
            status: {
                label: `${connectedProviderCount} connected`,
                className:
                    'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
            },
        });
    }

    securityCards.push({
        title: 'Active Sessions',
        description: sessionManagementSupported
            ? 'Review and revoke active devices signed into your account.'
            : 'Session management is limited for your current driver.',
        href: route('app.profile.security.sessions'),
        icon: SmartphoneIcon,
        status: sessionManagementSupported
            ? {
                  label: `${activeSessionCount} active`,
                  className: 'bg-secondary text-secondary-foreground',
              }
            : {
                  label: 'Limited',
                  className: 'bg-secondary text-secondary-foreground',
              },
    });

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Security"
            description="Manage your password and account security settings"
        >
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4">
                {securityCards.map((card) => (
                    <SecurityCardLink key={card.title} {...card} />
                ))}
            </div>
        </AppLayout>
    );
}
