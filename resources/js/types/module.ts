export type InstalledModule = {
    name: string;
    slug: string;
    version: string;
    description: string;
    inertiaNamespace: string;
    url: string;
};

export type ManagedModule = InstalledModule & {
    status: 'enabled' | 'disabled';
    enabled: boolean;
};
