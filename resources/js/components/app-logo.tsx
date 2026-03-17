import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import type { SharedData } from '@/types';

export default function AppLogo() {
    const { appName, branding, modules } = usePage<SharedData>().props;
    const iconUrl = branding.icon.trim();
    const isCmsEnabled = modules.items.some((m) => m.slug === 'cms');

    const content = (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                {iconUrl !== '' ? (
                    <img
                        src={iconUrl}
                        alt={`${branding.name.trim() || appName} icon`}
                        className="size-full object-cover"
                    />
                ) : (
                    <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                )}
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {appName}
                </span>
            </div>
        </>
    );

    if (isCmsEnabled) {
        return (
            <a href="/" target="_blank" rel="noreferrer" className="flex items-center space-x-2">
                {content}
            </a>
        );
    }

    return (
        <div className="flex items-center">
            {content}
        </div>
    );
}
