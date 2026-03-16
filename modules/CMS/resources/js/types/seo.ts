import type { MediaPickerPageProps } from './cms';
import type { CmsOption } from './cms';

export type TitlesMetaSectionKey =
    | 'general'
    | 'posts'
    | 'pages'
    | 'categories'
    | 'tags'
    | 'authors'
    | 'search'
    | 'error_page';

export type TitlesMetaGeneralValues = {
    section: 'general';
    separator_character: string;
    secondary_separator_character: string;
    cms_base: string;
    url_extension: string;
    search_engine_visibility: boolean;
};

export type TitlesMetaTemplateValues = {
    section: Exclude<TitlesMetaSectionKey, 'general'>;
    permalink_base: string;
    title_template: string;
    description_template: string;
    robots_default: string;
    permalink_structure: string;
    enable_multiple_categories: boolean;
    enable_pagination_indexing: boolean;
};

export type TitlesMetaSectionConfig = {
    key: Exclude<TitlesMetaSectionKey, 'general'>;
    title: string;
    description: string;
    helperText?: string | null;
    supportsPermalinkBase: boolean;
    supportsPermalinkStructure: boolean;
    supportsMultipleCategories: boolean;
    supportsPaginationIndexing: boolean;
    previewPattern?: string | null;
    initialValues: TitlesMetaTemplateValues;
};

export type TitlesMetaPageProps = {
    activeSection: TitlesMetaSectionKey;
    metaRobotsOptions: CmsOption[];
    urlExtensionOptions: CmsOption[];
    generalInitialValues: TitlesMetaGeneralValues;
    sections: TitlesMetaSectionConfig[];
    searchEngineVisibility: boolean;
};

export type LocalSeoFormValues = {
    is_schema: boolean;
    type: 'Organization' | 'Person';
    business_type: string;
    name: string;
    description: string;
    street_address: string;
    locality: string;
    region: string;
    postal_code: string;
    country_code: string;
    phone: string;
    email: string;
    logo_image: number | '';
    url: string;
    is_opening_hour_24_7: boolean;
    opening_hour_day: string[];
    opening_hours: string[];
    closing_hours: string[];
    price_range: string;
    geo_coordinates_latitude: string;
    geo_coordinates_longitude: string;
    facebook_url: string;
    twitter_url: string;
    linkedin_url: string;
    instagram_url: string;
    youtube_url: string;
    founding_date: string;
};

export type LocalSeoPageProps = MediaPickerPageProps & {
    initialValues: LocalSeoFormValues;
    businessTypeOptions: CmsOption[];
    openingDayOptions: CmsOption[];
    logoImageUrl: string | null;
};

export type SocialMediaFormValues = {
    facebook_page_url: string;
    facebook_authorship: string;
    facebook_admin: string;
    facebook_app: string;
    facebook_secret: string;
    twitter_username: string;
    open_graph_image: number | '';
    twitter_card_type: string;
};

export type SocialMediaPageProps = MediaPickerPageProps & {
    initialValues: SocialMediaFormValues;
    twitterCardOptions: CmsOption[];
    openGraphImageUrl: string | null;
};

export type SchemaFormValues = {
    enable_article_schema: boolean;
    enable_breadcrumb_schema: boolean;
};

export type SchemaPageProps = {
    initialValues: SchemaFormValues;
};

export type SitemapTypeStatus = {
    label: string;
    icon: string;
    enabled: boolean;
    count: number;
};

export type SitemapFormValues = {
    enabled: boolean;
    posts_enabled: boolean;
    pages_enabled: boolean;
    categories_enabled: boolean;
    tags_enabled: boolean;
    authors_enabled: boolean;
    auto_regenerate: boolean;
    links_per_file: number;
};

export type SitemapPageProps = {
    initialValues: SitemapFormValues;
    sitemapStatus: {
        enabled: boolean;
        last_generated_at: string | null;
        total_urls: number;
        types: Record<string, SitemapTypeStatus>;
    };
};

export type RobotsFormValues = {
    robots_txt: string;
};

export type RobotsPageProps = {
    initialValues: RobotsFormValues;
    robotsUrl: string;
    sitemapUrl: string;
};

export type ImportExportPageProps = {
    seoGroups: string[];
};
