import type { InstalledModule } from '@/types/module';
import type { NavigationByArea } from '@/types/navigation';

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
    abilities: {
        manageModules: boolean;
        viewRoles: boolean;
        addRoles: boolean;
        editRoles: boolean;
        deleteRoles: boolean;
        restoreRoles: boolean;
        viewUsers: boolean;
        addUsers: boolean;
        editUsers: boolean;
        deleteUsers: boolean;
        restoreUsers: boolean;
        impersonateUsers: boolean;
        viewAddresses: boolean;
        addAddresses: boolean;
        editAddresses: boolean;
        deleteAddresses: boolean;
        restoreAddresses: boolean;
        viewEmailProviders: boolean;
        addEmailProviders: boolean;
        editEmailProviders: boolean;
        deleteEmailProviders: boolean;
        restoreEmailProviders: boolean;
        viewEmailTemplates: boolean;
        addEmailTemplates: boolean;
        editEmailTemplates: boolean;
        deleteEmailTemplates: boolean;
        restoreEmailTemplates: boolean;
        viewEmailLogs: boolean;
        /** Module-provided abilities (resolved dynamically via AbilityAggregator) */
        [key: string]: boolean;
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
