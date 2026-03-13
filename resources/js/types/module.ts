export type InstalledModule = {
    name: string;
    slug: string;
    version: string;
    description: string;
    author: string | null;
    homepage: string | null;
    icon: string | null;
    inertiaNamespace: string;
    url: string;
};

export type ManagedModule = {
    name: string;
    version: string;
    description: string;
    author: string | null;
    homepage: string | null;
    icon: string | null;
    status: 'enabled' | 'disabled';
    enabled: boolean;
};
