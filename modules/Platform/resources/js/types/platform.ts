import type { PaginatedData } from '@/types';
import type {
    MediaListItem,
    MediaPickerFilters,
    UploadSettings,
} from '@/types/media';
import type {
    ScaffoldActionConfig,
    ScaffoldEmptyStateConfig,
    ScaffoldFilterState,
    ScaffoldInertiaConfig,
    ScaffoldRowActionPayload,
    ScaffoldStatusTabConfig,
} from '@/types/scaffold';

export type PlatformOption = {
    value: string | number;
    label: string;
};

export type PlatformMediaPickerPageProps = {
    pickerMedia: PaginatedData<MediaListItem> | null;
    pickerFilters: MediaPickerFilters | null;
    uploadSettings: UploadSettings | null;
    pickerStatistics?: {
        total: number;
        trash: number;
    } | null;
};

export type PlatformActivity = {
    id: number;
    description: string;
    created_at: string | null;
    causer_name: string | null;
};

export type PlatformActionPayload = ScaffoldRowActionPayload;

export type PlatformActionConfig = ScaffoldActionConfig;

export type PlatformStatusTabConfig = ScaffoldStatusTabConfig;

export type PlatformScaffoldConfig = ScaffoldInertiaConfig;

export type PlatformFilterState = ScaffoldFilterState;

export type PlatformIndexPageProps<T> = {
    config: PlatformScaffoldConfig;
    rows: PaginatedData<T>;
    filters: PlatformFilterState;
    statistics: Record<string, number>;
    empty_state_config?: ScaffoldEmptyStateConfig | null;
};

export type AgencyListItem = {
    id: number;
    uid: string | null;
    name: string;
    email: string | null;
    owner_name: string;
    plan_label: string;
    type_label: string;
    status_label: string;
    websites_count: number;
    created_at: string | null;
    actions?: Record<string, PlatformActionPayload>;
    is_trashed?: boolean;
};

export type AgencyFormValues = {
    name: string;
    email: string;
    type: string;
    plan: string;
    owner_id: string;
    website_id_prefix: string;
    website_id_zero_padding: string;
    agency_website_id: string;
    webhook_url: string;
    phone_code: string;
    phone: string;
    country: string;
    country_code: string;
    state: string;
    state_code: string;
    city_code: string;
    city: string;
    zip: string;
    address1: string;
    branding_name: string;
    branding_website: string;
    branding_logo: string;
    branding_icon: string;
    status: string;
};

export type AgencyShowData = {
    id: number;
    uid: string | null;
    name: string;
    email: string | null;
    owner_id: number | null;
    type: string | null;
    type_label: string | null;
    plan: string | null;
    plan_label: string | null;
    website_limit: number | null;
    plan_usage_percent: number | null;
    status: string | null;
    status_label: string | null;
    has_secret_key: boolean;
    is_whitelabel: boolean;
    is_trashed: boolean;
    deleted_at: string | null;
    owner_name: string | null;
    owner_email: string | null;
    website_id_prefix: string | null;
    website_id_zero_padding: number | null;
    webhook_url: string | null;
    statistics: {
        websites: number;
        servers: number;
        dnsProviders: number;
        cdnProviders: number;
        providers: number;
    };
    agency_website: {
        id: number;
        name: string;
        href: string;
    } | null;
    branding: {
        name: string | null;
        website: string | null;
        logo: string | null;
        icon: string | null;
    };
    address: {
        address1: string | null;
        city: string | null;
        state: string | null;
        state_code: string | null;
        country: string | null;
        country_code: string | null;
        zip: string | null;
        phone_code: string | null;
        phone: string | null;
    };
    created_at: string | null;
    updated_at: string | null;
};

export type AgencyRelationItem = {
    id: number;
    name: string;
    href?: string;
    subtitle?: string | null;
    status?: string | null;
    status_label?: string | null;
    is_primary?: boolean;
};

export type AgencyServerItem = AgencyRelationItem & {
    type?: string | null;
    type_label?: string | null;
};

export type AgencyProviderItem = AgencyRelationItem & {
    vendor?: string | null;
    vendor_label?: string | null;
    type?: string | null;
    type_label?: string | null;
};

export type ServerListItem = {
    id: number;
    uid: string | null;
    name: string;
    ip: string;
    type?: string | null;
    status?: string | null;
    provider_name: string;
    type_label: string;
    status_label: string;
    domain_usage_current: number;
    domain_usage_max: number | null;
    domain_usage_percent?: number | null;
    created_at: string | null;
    actions?: Record<string, PlatformActionPayload>;
    is_trashed?: boolean;
};

export type ServerFormValues = {
    creation_mode: 'manual' | 'provision';
    name: string;
    ip: string;
    fqdn: string;
    type: string;
    provider_id: string;
    monitor: boolean;
    status: string;
    location_country_code: string;
    location_country: string;
    location_city_code: string;
    location_city: string;
    port: string;
    access_key_id: string;
    access_key_secret: string;
    release_api_key: string;
    max_domains: string;
    ssh_port: string;
    ssh_user: string;
    ssh_public_key: string;
    ssh_private_key: string;
    server_cpu: string;
    server_ccore: string;
    server_ram: string;
    server_storage: string;
    server_os: string;
    astero_version: string;
    hestia_version: string;
    release_zip_url: string;
    install_port: string;
    install_lang: string;
    install_apache: boolean;
    install_phpfpm: boolean;
    install_multiphp: boolean;
    install_multiphp_versions: string;
    install_vsftpd: boolean;
    install_proftpd: boolean;
    install_named: boolean;
    install_mysql: boolean;
    install_mysql8: boolean;
    install_postgresql: boolean;
    install_exim: boolean;
    install_dovecot: boolean;
    install_sieve: boolean;
    install_clamav: boolean;
    install_spamassassin: boolean;
    install_iptables: boolean;
    install_fail2ban: boolean;
    install_quota: boolean;
    install_resourcelimit: boolean;
    install_webterminal: boolean;
    install_api: boolean;
    install_force: boolean;
};

export type ServerProvisioningStep = {
    key: string;
    title: string;
    description: string;
    status: string;
    message?: string | null;
    started_at?: string | null;
    completed_at?: string | null;
};

export type ProvisioningRunTimestamps = {
    started_at: string | null;
    completed_at: string | null;
};

export type ServerSecretItem = {
    id: number;
    key: string;
    label: string;
    username: string | null;
};

export type ServerAgencyItem = {
    id: number;
    name: string;
    status: string | null;
    is_primary: boolean;
};

export type ServerMetadataItem = {
    key: string;
    label: string;
    value: string;
};

export type ServerShowData = {
    id: number;
    uid: string | null;
    name: string;
    ip: string;
    fqdn: string | null;
    type: string | null;
    type_label: string | null;
    status: string | null;
    status_label: string | null;
    provisioning_status: string | null;
    provider_id: number | null;
    provider_name: string | null;
    location_label: string | null;
    port: number | null;
    ssh_port: number | null;
    ssh_user: string | null;
    access_key_id: string | null;
    has_access_key_secret: boolean;
    has_ssh_credentials: boolean;
    current_domains: number;
    max_domains: number | null;
    creation_mode: string;
    server_ccore: number | null;
    server_ram: number | null;
    server_storage: number | null;
    server_ram_used: number | null;
    server_storage_used: number | null;
    astero_version: string | null;
    hestia_version: string | null;
    server_os: string | null;
    server_uptime: string | null;
    last_synced_at: string | null;
    acme_configured: boolean;
    acme_email: string | null;
    is_trashed: boolean;
    created_at: string | null;
    updated_at: string | null;
};

export type WebsiteListItem = {
    id: number;
    uid: string | null;
    name: string;
    domain: string;
    type: string | null; // Added type
    status: string | null; // Added status
    agency_name: string;
    server_name: string;
    status_label: string;
    dns_mode_label: string;
    cdn_status_label: string;
    domain_usage_percent?: number | null; // Optional domain usage percent
    domain_url?: string | null;
    actions?: Record<string, PlatformActionPayload>;
    is_trashed?: boolean;
};

export type WebsiteFormValues = {
    name: string;
    domain: string;
    type: string;
    plan: string;
    order_id: string;
    item_id: string;
    server_id: string;
    agency_id: string;
    dns_provider_id: string;
    cdn_provider_id: string;
    dns_mode: string;
    website_username: string;
    owner_password: string;
    customer_name: string;
    customer_email: string;
    status: string;
    expired_on: string;
    is_www: boolean;
    is_agency: boolean;
    skip_cdn: boolean;
    skip_dns: boolean;
    skip_ssl_issue: boolean;
    skip_email: boolean;
};

export type WebsiteProvisioningStep = {
    key: string;
    title: string;
    status: string;
    description?: string | null;
    message?: string | null;
    dns_instructions?: WebsiteDnsInstructions | null;
    dns_validation?: WebsiteDnsValidation | null;
    started_at?: string | null;
    completed_at?: string | null;
};

export type WebsiteDnsInstructionRecord = {
    type: string;
    name: string;
    host_label: string;
    fqdn: string;
    value: string;
};

export type WebsiteDnsInstructions = {
    mode: 'managed' | 'external';
    domain: string;
    nameservers?: string[];
    records?: WebsiteDnsInstructionRecord[];
};

export type WebsiteDnsValidation = {
    confirmed_by_user: boolean;
    confirmed_at?: string | null;
    check_count: number;
    domain_not_registered: boolean;
    observed_nameservers: string[];
    confirm_url: string;
    stop_url: string;
};

export type WebsiteUpdateItem = {
    key: string;
    label: string;
    value: string;
};

export type WebsiteSecretItem = {
    id: number;
    key: string;
    label: string;
    username: string | null;
};

export type WebsiteShowData = {
    id: number;
    uid: string | null;
    name: string;
    domain: string;
    domain_url: string | null;
    primary_hostname: string | null;
    alternate_hostname: string | null;
    primary_hostname_sync: {
        status: string;
        target: string | null;
        message: string | null;
        updated_at: string | null;
    } | null;
    type: string | null;
    plan: string | null;
    status: string | null;
    status_label: string | null;
    dns_mode: string | null;
    astero_version: string | null;
    admin_slug: string | null;
    media_slug: string | null;
    is_www: boolean;
    supports_www_feature: boolean;
    is_agency: boolean;
    skip_cdn: boolean;
    skip_dns: boolean;
    skip_ssl_issue: boolean;
    skip_email: boolean;
    created_at: string | null;
    updated_at: string | null;
    expired_on: string | null;
    is_trashed: boolean;
    has_update: boolean;
    server_version: string | null;
    niches: string[];

    // Infrastructure
    server_id: number | null;
    server_name: string | null;
    server_ip: string | null;
    server_fqdn: string | null;
    dns_provider_name: string | null;
    cdn_provider_name: string | null;

    // Ownership
    agency_id: number | null;
    agency_name: string | null;
    customer_name: string | null;
    ssl_summary: {
        certificate_name: string;
        certificate_href: string | null;
        expires_at: string | null;
        websites_count: number;
        websites: Array<{
            id: number;
            name: string;
            domain: string;
            href: string;
        }>;
        domain_name: string | null;
        domain_href: string | null;
    } | null;

    // Runtime
    disk_usage: string | null;
    last_synced_at: string | null;
    queue_worker_status: string | null;
    queue_worker_running: number;
    queue_worker_total: number;
    cron_status: string | null;
};

export type DomainListItem = {
    id: number;
    name: string;
    agency_name: string | null;
    type_label: string;
    registrar_name: string | null;
    expiry_date: string | null;
    status_label: string;
    created_at: string | null;
    actions?: Record<string, PlatformActionPayload>;
    is_trashed?: boolean;
};

export type DomainFormValues = {
    name: string;
    type: string;
    agency_id: string;
    status: string;
    registrar_id: string;
    registrar_name: string;
    registered_date: string;
    expires_date: string;
    updated_date: string;
    domain_name_server_1: string;
    domain_name_server_2: string;
    domain_name_server_3: string;
    domain_name_server_4: string;
    dns_provider: string;
    dns_zone_id: string;
};

export type DomainShowData = {
    id: number;
    name: string;
    type: string | null;
    type_label: string | null;
    status: string | null;
    status_label: string | null;
    agency_id: number | null;
    agency_name: string | null;
    dns_mode: string | null;
    dns_status: string | null;
    ssl_status: string | null;
    registrar_name: string | null;
    dns_provider: string | null;
    dns_zone_id: string | null;
    registered_date: string | null;
    expires_date: string | null;
    updated_date: string | null;
    name_servers: string[];
    websites_count: number;
    dns_records_count: number;
    ssl_certificates_count: number;
    latest_certificate_websites_count: number;
    latest_certificate_expires_at: string | null;
    is_trashed: boolean;
    deleted_at: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type DomainWebsiteItem = {
    id: number;
    name: string;
    domain: string;
    status_label: string;
    uses_latest_ssl: boolean;
    href: string;
};

export type DomainDnsRecordItem = {
    id: number;
    type: string;
    name: string;
    value: string;
    ttl: number | null;
    disabled: boolean;
};

export type DomainDnsRecordListItem = {
    id: number;
    domain_name: string | null;
    name: string;
    type_label: string;
    value: string;
    ttl: number | null;
    updated_at: string | null;
    actions?: Record<string, PlatformActionPayload>;
    is_trashed?: boolean;
};

export type DomainDnsRecordFormValues = {
    domain_id: string;
    name: string;
    type: string;
    value: string;
    ttl: string;
    priority: string;
    weight: string;
    port: string;
    disabled: boolean;
    record_id: string;
    zone_id: string;
};

export type DomainDnsRecordShowData = {
    id: number;
    domain_id: number;
    domain_name: string | null;
    name: string;
    type: number | null;
    type_label: string;
    value: string | null;
    ttl: number | null;
    priority: number | null;
    weight: number | null;
    port: number | null;
    disabled: boolean;
    record_id: string | null;
    zone_id: string | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
};

export type DomainSslCertificateItem = {
    id: number;
    name: string;
    authority: string;
    expires_at: string | null;
    is_expired: boolean;
    websites_count: number;
    href: string;
};

export type ProviderListItem = {
    id: number;
    name: string;
    email: string | null;
    type_label: string;
    vendor_label: string;
    status_label: string;
    created_at: string | null;
    actions?: Record<string, PlatformActionPayload>;
    is_trashed?: boolean;
};

export type ProviderCredentialValues = {
    api_key: string;
    api_token: string;
    api_secret: string;
    api_user: string;
    username: string;
    account_id: string;
    zone_id: string;
    client_ip: string;
};

export type ProviderFormValues = {
    name: string;
    email: string;
    type: string;
    vendor: string;
    status: string;
    credentials: ProviderCredentialValues;
};

export type ProviderShowData = {
    id: number;
    name: string;
    email: string | null;
    type: string | null;
    type_label: string | null;
    vendor: string | null;
    vendor_label: string | null;
    status: string | null;
    status_label: string | null;
    websites_count: number;
    domains_count: number;
    servers_count: number;
    agencies_count: number;
    credential_keys: string[];
    created_at: string | null;
    updated_at: string | null;
};

export type SecretListItem = {
    id: number;
    key: string;
    username: string | null;
    type_label: string;
    is_active_label: string;
    expires_at: string | null;
    created_at: string | null;
    actions?: Record<string, PlatformActionPayload>;
    is_trashed?: boolean;
};

export type SecretFormValues = {
    secretable_type: string;
    secretable_id: string;
    key: string;
    username: string;
    type: string;
    value: string;
    is_active: boolean;
    expires_at: string;
};

export type SecretShowData = {
    id: number;
    key: string;
    username: string | null;
    type: string | null;
    type_label: string | null;
    secretable_type: string | null;
    secretable_type_label: string;
    secretable_id: number | null;
    secretable_name: string | null;
    secretable_href: string | null;
    is_active: boolean;
    is_active_label: string;
    is_expired: boolean;
    expires_at: string | null;
    metadata: Record<string, unknown>;
    created_at: string | null;
    updated_at: string | null;
};

export type TldListItem = {
    id: number;
    tld: string;
    whois_server: string | null;
    price: string | null;
    sale_price: string | null;
    status_label: string;
    is_suggested_label: string;
    created_at: string | null;
    actions?: Record<string, PlatformActionPayload>;
    is_trashed?: boolean;
};

export type TldFormValues = {
    tld: string;
    whois_server: string;
    pattern: string;
    price: string;
    sale_price: string;
    affiliate_link: string;
    status: boolean;
    is_main: boolean;
    is_suggested: boolean;
    tld_order: string;
};

export type TldShowData = {
    id: number;
    tld: string;
    whois_server: string | null;
    pattern: string | null;
    price: string | null;
    sale_price: string | null;
    affiliate_link: string | null;
    status: boolean;
    status_label: string;
    is_main: boolean;
    is_suggested: boolean;
    tld_order: number | null;
    created_at: string | null;
    updated_at: string | null;
};

export type SslCertificateListItem = {
    id: number;
    name: string;
    domain_name: string | null;
    certificate_authority: string;
    expires_at: string | null;
    status_label: string;
    show_url?: string | null;
    domain_url?: string | null;
    actions?: Record<string, PlatformActionPayload>;
};

export type SslCertificateFormValues = {
    name: string;
    certificate_authority: string;
    is_wildcard: boolean;
    domains: string;
    private_key: string;
    certificate: string;
    ca_bundle: string;
    issuer: string;
    issued_at: string;
    expires_at: string;
};

export type SslCertificateShowData = {
    id: number;
    name: string;
    certificate_authority: string;
    issuer: string | null;
    subject: string | null;
    issued_at: string | null;
    expires_at: string | null;
    serial_number: string | null;
    fingerprint: string | null;
    domains: string[];
    is_wildcard: boolean;
    is_expired: boolean;
    is_expiring_soon: boolean;
    days_until_expiry: number | null;
    download_private_key_url: string;
    download_certificate_url: string;
    created_at: string | null;
    updated_at: string | null;
};

export type SelfSignedCertificateValues = {
    name: string;
    key_type: string;
    validity_days: string;
    common_name: string;
    country: string;
    state: string;
    city: string;
    organization: string;
    org_unit: string;
    san_domains: string;
};
