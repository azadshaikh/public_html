import type { InstalledModule } from '@/types/module';

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
        viewUsers: boolean;
        addUsers: boolean;
        editUsers: boolean;
        deleteUsers: boolean;
    };
};

export type SharedData = {
    appName: string;
    auth: Auth;
    modules: {
        items: InstalledModule[];
    };
    sidebarOpen: boolean;
};

export type AuthenticatedSharedData = Omit<SharedData, 'auth'> & {
    auth: Auth & {
        user: User;
    };
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
