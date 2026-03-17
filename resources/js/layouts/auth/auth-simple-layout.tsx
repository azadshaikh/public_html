import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { AppThemeToggle } from '@/components/app-theme-toggle';
import { cn } from '@/lib/utils';
import type { AuthLayoutProps, SharedData } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
    maxWidthClassName,
}: AuthLayoutProps) {
    const { appName, branding } = usePage<SharedData>().props;
    const iconUrl = branding?.icon?.trim() ?? '';

    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div className="absolute top-6 right-6">
                <AppThemeToggle />
            </div>

            <div className={cn('w-full max-w-sm', maxWidthClassName)}>
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href="/"
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <div className="flex h-12 w-12 items-center justify-center overflow-hidden rounded-lg bg-primary text-primary-foreground">
                                {iconUrl !== '' ? (
                                    <img
                                        src={iconUrl}
                                        alt={`${branding.name.trim() || appName} icon`}
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <AppLogoIcon className="size-7 fill-current" />
                                )}
                            </div>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
