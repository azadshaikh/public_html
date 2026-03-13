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
        viewUsers: boolean;
        addUsers: boolean;
        editUsers: boolean;
        deleteUsers: boolean;
    };
};

export type FlashData = {
    success?: string;
    error?: string;
    info?: string;
    status?: string;
};

export type SharedData = {
    appName: string;
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
