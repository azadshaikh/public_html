import type { InstalledModule } from '@/types/module';
import type { NavigationByArea } from '@/types/navigation';

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    two_factor_enabled?: boolean;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
    abilities: {
        manageModules: boolean;
        [key: string]: boolean | undefined;
    };
    impersonation: {
        active: true;
        impersonator: {
            id: number;
            name: string;
            email: string;
        };
        stopUrl: string;
    } | null;
};

export type FlashMessage =
    | string
    | {
        title?: string;
        message?: string;
    };

export type FlashData = {
    success?: FlashMessage;
    error?: FlashMessage;
    info?: FlashMessage;
    status?: FlashMessage;
};

export type SharedData = {
    appName: string;
    appVersion: string;
    branding: {
        name: string;
        website: string;
        logo: string;
        icon: string;
    };
    runtime: {
        inertiaHardReloadPageLimit: number;
    };
    auth: Auth;
    navigation: NavigationByArea;
    modules: {
        items: InstalledModule[];
    };
    sidebarOpen: boolean;
    flash: FlashData;
};

export type AuthenticatedSharedData = Omit<SharedData, 'auth'> & {
    auth: Auth & {
        user: User;
    };
};
