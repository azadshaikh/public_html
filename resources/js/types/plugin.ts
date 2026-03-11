export type InstalledPlugin = {
    name: string;
    slug: string;
    version: string;
    description: string;
    inertiaNamespace: string;
    url: string;
};

export type ManagedPlugin = InstalledPlugin & {
    status: 'enabled' | 'disabled';
    enabled: boolean;
};
