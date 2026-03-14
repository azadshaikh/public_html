import type { PaginatedData } from '@/types';

export type LaravelToolKey =
    | 'env'
    | 'artisan'
    | 'config'
    | 'routes'
    | 'php';

export type LaravelToolsStats = {
    php_version: string;
    laravel_version: string;
    environment: string;
    debug_mode: boolean;
    cache_driver: string;
    cache_prefix: string;
    session_driver: string;
    database_connection: string;
    timezone: string;
    locale: string;
};

export type LaravelToolsDashboardPageProps = {
    stats: LaravelToolsStats;
    status?: string;
    error?: string;
};

export type EnvBackup = {
    name: string;
    size: number;
    date: string;
};

export type LaravelToolsEnvPageProps = {
    envContent: string;
    protectedKeys: string[];
    backups: EnvBackup[];
    status?: string;
    error?: string;
};

export type ArtisanCommand = {
    name: string;
    description: string;
};

export type LaravelToolsArtisanPageProps = {
    commands: ArtisanCommand[];
    status?: string;
    error?: string;
};

export type LaravelConfigValue =
    | string
    | number
    | boolean
    | null
    | LaravelConfigValue[]
    | { [key: string]: LaravelConfigValue };

export type LaravelConfigFile = {
    name: string;
};

export type LaravelToolsConfigPageProps = {
    configFiles: LaravelConfigFile[];
    selectedFile: string | null;
    selectedConfig: LaravelConfigValue | null;
    status?: string;
    error?: string;
};

export type LaravelRouteListItem = {
    methods: string[];
    method_label: string;
    uri: string;
    name: string | null;
    action: string;
    middleware: string[];
};

export type LaravelToolsRoutesPageProps = {
    routes: PaginatedData<LaravelRouteListItem>;
    total: number;
    filters: {
        search: string;
        method: string;
        sort: string;
        direction: 'asc' | 'desc';
        per_page: number;
    };
    status?: string;
    error?: string;
};

export type PhpSettingGroups = Record<string, Record<string, string>>;

export type PhpExtension = {
    name: string;
    version: string;
};

export type LaravelToolsPhpPageProps = {
    summary: {
        php_version: string;
        sapi: string;
        ini_file: string;
        memory_limit: string;
        max_execution_time: string;
        opcache_enabled: boolean;
    };
    settingGroups: PhpSettingGroups;
    extensions: PhpExtension[];
    pdoDrivers: string[];
    status?: string;
    error?: string;
};
