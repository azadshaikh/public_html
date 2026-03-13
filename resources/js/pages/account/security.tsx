import { Link } from '@inertiajs/react';
import {
    ChevronRightIcon,
    KeyRoundIcon,
    LaptopIcon,
    LinkIcon,
    ShieldCheckIcon,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AccountLayout from '@/layouts/account/layout';
import AppLayout from '@/layouts/app-layout';
import { security as securityRoute } from '@/routes/app/profile';
import {
    password as passwordRoute,
    twoFactor as twoFactorRoute,
} from '@/routes/app/profile/security';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Security',
        href: securityRoute(),
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

export default function Security({
    twoFactorEnabled,
    twoFactorPending,
    showSocialLoginCard,
    connectedProviderCount,
    activeSessionCount,
    sessionManagementSupported,
    hasPassword,
}: SecurityProps) {
    const securityItems = [
        {
            title: 'Password',
            description: hasPassword
                ? 'Change your account password'
                : 'Set up a password for your account',
            icon: <KeyRoundIcon className="size-5" />,
            href: passwordRoute(),
            status: hasPassword ? (
                <Badge variant="default">Set</Badge>
            ) : (
                <Badge variant="destructive">Not set</Badge>
            ),
        },
        {
            title: 'Two-Factor Authentication',
            description: 'Add an extra layer of security to your account',
            icon: <ShieldCheckIcon className="size-5" />,
            href: twoFactorRoute(),
            status: twoFactorEnabled ? (
                <Badge variant="default">Enabled</Badge>
            ) : twoFactorPending ? (
                <Badge variant="secondary">Pending Setup</Badge>
            ) : (
                <Badge variant="outline">Disabled</Badge>
            ),
        },
    ];

    if (showSocialLoginCard) {
        securityItems.push({
            title: 'Social Logins',
            description: 'Manage connected social accounts',
            icon: <LinkIcon className="size-5" />,
            href: passwordRoute().replace('password', 'social-logins'),
            status:
                connectedProviderCount > 0 ? (
                    <Badge variant="default">
                        {connectedProviderCount} connected
                    </Badge>
                ) : (
                    <Badge variant="outline">None connected</Badge>
                ),
        });
    }

    if (sessionManagementSupported) {
        securityItems.push({
            title: 'Active Sessions',
            description: 'Manage your active browser sessions',
            icon: <LaptopIcon className="size-5" />,
            href: passwordRoute().replace('password', 'sessions'),
            status: (
                <Badge variant="secondary">{activeSessionCount} active</Badge>
            ),
        });
    }

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Security"
            description="Manage your account security settings."
        >
            <AccountLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Security settings"
                        description="Manage your password, two-factor authentication, and session security"
                    />

                    <div className="space-y-3">
                        {securityItems.map((item) => (
                            <Link
                                key={item.title}
                                href={item.href}
                                className="block"
                            >
                                <Card className="transition-colors hover:bg-muted/50">
                                    <CardContent className="flex items-center gap-4 py-4">
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            {item.icon}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <h3 className="text-sm font-medium text-foreground">
                                                    {item.title}
                                                </h3>
                                                {item.status}
                                            </div>
                                            <p className="mt-0.5 text-sm text-muted-foreground">
                                                {item.description}
                                            </p>
                                        </div>
                                        <ChevronRightIcon className="size-5 shrink-0 text-muted-foreground" />
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                </div>
            </AccountLayout>
        </AppLayout>
    );
}
