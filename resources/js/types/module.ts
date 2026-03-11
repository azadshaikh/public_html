export type InstalledModule = {
    name: string;
    slug: string;
    version: string;
    description: string;
    inertiaNamespace: string;
    url: string;
};

export type ManagedModule = {
    name: string;
    version: string;
    description: string;
    status: 'enabled' | 'disabled';
    enabled: boolean;
};
