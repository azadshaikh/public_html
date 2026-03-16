import type { PaginatedData } from '@/types/pagination';
import type { MediaListItem, MediaPickerFilters, UploadSettings } from '@/types/media';

// ================================================================
// Common types
// ================================================================

export type CmsOption = {
    value: string | number;
    label: string;
    disabled?: boolean;
};

export type DefaultPagesSettings = {
    home_page: string;
    blogs_page: string;
    blog_base_url: string;
    contact_page: string;
    about_page: string;
    privacy_policy_page: string;
    terms_of_service_page: string;
    blog_same_as_home: boolean;
};

export type DefaultPagesPageProps = {
    pageOptions: CmsOption[];
    settings: DefaultPagesSettings;
    publishedPageCount: number;
};

// ================================================================
// Shared scaffold filter state (from collectRequestFilters)
// ================================================================

export type ScaffoldFilters = {
    search: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    view?: string;
    [key: string]: string | number | undefined;
};

// ================================================================
// Shared scaffold config types (from toInertiaConfig)
// ================================================================

export type StatusTabConfig = {
    key: string;
    label: string;
    value?: string;
    icon?: string;
    color?: string;
    default?: boolean;
};

export type InertiaConfig = {
    columns: Record<string, unknown>[];
    filters: Record<string, unknown>[];
    actions: Record<string, unknown>[];
    statusTabs: StatusTabConfig[];
    settings: {
        perPage: number;
        defaultSort: string;
        defaultDirection: 'asc' | 'desc';
        enableBulkActions: boolean;
        enableExport: boolean;
        hasNotes: boolean;
        entityName: string;
        entityPlural: string;
        routePrefix: string;
        statusField: string | null;
    };
};

export type EmptyStateConfig = {
    icon?: string;
    title: string;
    message: string;
    action?: { label: string; url: string };
};

// ================================================================
// Base scaffold index page props (all scaffold pages receive these)
// ================================================================

export type ScaffoldIndexPageProps<T> = {
    config: InertiaConfig;
    rows: PaginatedData<T>;
    filters: ScaffoldFilters;
    statistics: Record<string, number>;
    empty_state_config: EmptyStateConfig;
    status?: string;
    error?: string;
};

// ================================================================
// Posts
// ================================================================

export type PostListItem = {
    id: number;
    title: string;
    title_with_meta: string;
    slug: string;
    excerpt: string | null;
    status: string;
    status_label: string;
    status_class: string;
    author_name: string;
    featured_image_url: string | null;
    is_featured: boolean;
    featured_badge: string;
    categories: { id: number; title: string }[];
    categories_display: string;
    permalink_url: string | null;
    published_at: string | null;
    published_at_formatted: string | null;
    updated_at_formatted: string;
    display_date: string;
    edit_url: string;
    is_trashed: boolean;
    actions: Record<string, unknown>[];
};

export type PostFormValues = {
    title: string;
    slug: string;
    content: string;
    excerpt: string;
    feature_image: number | '';
    is_featured: boolean;
    status: string;
    visibility: string;
    post_password: string;
    password_hint: string;
    author_id: number | '';
    published_at: string;
    meta_title: string;
    meta_description: string;
    meta_robots: string;
    og_title: string;
    og_description: string;
    og_image: string;
    og_url: string;
    schema: string;
    template: string;
    categories: number[];
    tags: number[];
};

// ================================================================
// Media picker (shared by any form page using HasMediaPicker trait)
// ================================================================

export type MediaPickerPageProps = {
    pickerMedia: PaginatedData<MediaListItem> | null;
    pickerFilters: MediaPickerFilters | null;
    uploadSettings: UploadSettings | null;
};

// ================================================================
// Post form options
// ================================================================

export type PostFormOptions = {
    initialValues?: PostFormValues;
    categoryOptions: CmsOption[];
    tagOptions: CmsOption[];
    authorOptions: CmsOption[];
    metaRobotsOptions: CmsOption[];
    statusOptions: CmsOption[];
    visibilityOptions: CmsOption[];
    templateOptions: CmsOption[];
    preSlug: string;
    baseUrl: string;
    defaults: Record<string, string>;
} & MediaPickerPageProps;

export type PostIndexPageProps = ScaffoldIndexPageProps<PostListItem>;

export type PostCreatePageProps = PostFormOptions;

export type PostEditDetail = {
    id: number;
    title: string;
    permalink_url: string | null;
    featured_image_url: string | null;
    is_password_protected: boolean;
    updated_at_formatted: string;
    updated_at_human: string | null;
};

export type PostEditPageProps = PostFormOptions & {
    post: PostEditDetail;
};

// ================================================================
// Pages
// ================================================================

export type PageListItem = {
    id: number;
    title: string;
    title_with_meta: string;
    slug: string;
    status: string;
    status_label: string;
    status_class: string;
    author_name: string;
    featured_image_url: string | null;
    parent_name: string | null;
    parent_display: string;
    permalink_url: string | null;
    published_at: string | null;
    published_at_formatted: string | null;
    updated_at_formatted: string;
    display_date: string;
    edit_url: string;
    is_trashed: boolean;
    actions: Record<string, unknown>[];
};

export type PageFormValues = {
    title: string;
    slug: string;
    content: string;
    excerpt: string;
    feature_image: number | '';
    status: string;
    visibility: string;
    post_password: string;
    password_hint: string;
    author_id: number | '';
    published_at: string;
    parent_id: string;
    template: string;
    meta_title: string;
    meta_description: string;
    meta_robots: string;
    og_title: string;
    og_description: string;
    og_image: string;
    og_url: string;
    schema: string;
};

export type PageFormOptions = {
    initialValues?: PageFormValues;
    parentPageOptions: CmsOption[];
    authorOptions: CmsOption[];
    metaRobotsOptions: CmsOption[];
    statusOptions: CmsOption[];
    visibilityOptions: CmsOption[];
    templateOptions: CmsOption[];
    preSlug: string;
    baseUrl: string;
    defaults: Record<string, string>;
} & MediaPickerPageProps;

export type PageIndexPageProps = ScaffoldIndexPageProps<PageListItem>;

export type PageCreatePageProps = PageFormOptions;

export type PageEditDetail = {
    id: number;
    title: string;
    permalink_url: string | null;
    featured_image_url: string | null;
    is_password_protected: boolean;
    updated_at_formatted: string;
    updated_at_human: string | null;
};

export type PageEditPageProps = PageFormOptions & {
    page: PageEditDetail;
};

// ================================================================
// Categories
// ================================================================

export type CategoryListItem = {
    id: number;
    title: string;
    title_with_meta: string;
    slug: string;
    status: string;
    status_label: string;
    status_class: string;
    parent_name: string | null;
    parent_display: string;
    posts_count: number;
    permalink_url: string | null;
    created_at: string | null;
    updated_at_formatted: string;
    display_date: string;
    edit_url: string;
    is_trashed: boolean;
    actions: Record<string, unknown>[];
};

export type CategoryFormValues = {
    title: string;
    slug: string;
    content: string;
    excerpt: string;
    status: string;
    parent_id: string;
    feature_image: number | '';
    template: string;
    meta_title: string;
    meta_description: string;
    meta_robots: string;
};

export type CategoryFormOptions = {
    initialValues?: CategoryFormValues;
    parentCategoryOptions: CmsOption[];
    statusOptions: CmsOption[];
    metaRobotsOptions: CmsOption[];
    templateOptions: CmsOption[];
    preSlug: string;
    baseUrl: string;
    defaults: Record<string, string>;
} & MediaPickerPageProps;

export type CategoryIndexPageProps = ScaffoldIndexPageProps<CategoryListItem>;

export type CategoryCreatePageProps = CategoryFormOptions;

export type CategoryEditDetail = {
    id: number;
    title: string;
    permalink_url: string | null;
    featured_image_url: string | null;
    updated_at_formatted: string;
    updated_at_human: string | null;
};

export type CategoryEditPageProps = CategoryFormOptions & {
    category: CategoryEditDetail;
};

// ================================================================
// Tags
// ================================================================

export type TagListItem = {
    id: number;
    title: string;
    title_with_meta: string;
    slug: string;
    status: string;
    status_label: string;
    status_class: string;
    posts_count: number;
    permalink_url: string | null;
    created_at: string | null;
    updated_at_formatted: string;
    display_date: string;
    edit_url: string;
    is_trashed: boolean;
    actions: Record<string, unknown>[];
};

export type TagFormValues = {
    title: string;
    slug: string;
    content: string;
    excerpt: string;
    status: string;
    feature_image: number | '';
    template: string;
    meta_title: string;
    meta_description: string;
    meta_robots: string;
};

export type TagFormOptions = {
    initialValues?: TagFormValues;
    statusOptions: CmsOption[];
    metaRobotsOptions: CmsOption[];
    templateOptions: CmsOption[];
    preSlug: string;
    baseUrl: string;
    defaults: Record<string, string>;
} & MediaPickerPageProps;

export type TagIndexPageProps = ScaffoldIndexPageProps<TagListItem>;

export type TagCreatePageProps = TagFormOptions;

export type TagEditDetail = {
    id: number;
    title: string;
    permalink_url: string | null;
    featured_image_url: string | null;
    updated_at_formatted: string;
    updated_at_human: string | null;
};

export type TagEditPageProps = TagFormOptions & {
    tag: TagEditDetail;
};

// ================================================================
// Redirections
// ================================================================

export type RedirectionListItem = {
    id: number;
    source_url: string;
    target_url: string;
    redirect_type: string;
    redirect_type_label: string;
    redirect_type_class: string;
    url_type: string;
    url_type_label: string;
    url_type_class: string;
    match_type: string;
    match_type_label: string;
    match_type_class: string;
    hits: number;
    status: string;
    status_label: string;
    status_class: string;
    show_url: string | null;
    created_at: string;
    is_trashed: boolean;
    actions: Record<string, unknown>[];
};

export type RedirectionFormValues = {
    source_url: string;
    target_url: string;
    redirect_type: string;
    url_type: string;
    match_type: string;
    status: string;
    notes: string;
};

export type RedirectionFormOptions = {
    initialValues?: RedirectionFormValues;
    statusOptions: CmsOption[];
    redirectTypeOptions: CmsOption[];
    urlTypeOptions: CmsOption[];
    matchTypeOptions: CmsOption[];
};

export type RedirectionIndexPageProps = ScaffoldIndexPageProps<RedirectionListItem>;

export type RedirectionCreatePageProps = RedirectionFormOptions;

export type RedirectionEditPageProps = RedirectionFormOptions & {
    redirection: RedirectionListItem & Record<string, unknown>;
};

// ================================================================
// Forms
// ================================================================

export type FormListItem = {
    id: number;
    title: string;
    slug: string;
    template: string;
    template_label: string;
    template_class: string;
    submissions_count: number;
    conversion_rate_display: string;
    is_active: boolean;
    is_active_label: string;
    is_active_class: string;
    status: string;
    status_label: string;
    status_class: string;
    show_url: string;
    created_at: string;
    is_trashed: boolean;
    actions: Record<string, unknown>[];
};

export type FormFormValues = {
    title: string;
    slug: string;
    status: string;
    template: string;
    is_active: boolean;
};

export type FormFormOptions = {
    initialValues?: FormFormValues;
    statusOptions: CmsOption[];
};

export type FormIndexPageProps = ScaffoldIndexPageProps<FormListItem>;

export type FormCreatePageProps = FormFormOptions;

export type FormEditPageProps = FormFormOptions & {
    form: FormListItem & Record<string, unknown>;
};

// ================================================================
// Design Blocks
// ================================================================

export type DesignBlockListItem = {
    id: number;
    title: string;
    slug: string;
    status: string;
    status_label: string;
    status_class: string;
    preview_image_url: string;
    design_type: string;
    design_type_label: string;
    design_type_class: string;
    block_type: string;
    block_type_label: string;
    block_type_class: string;
    category_id: number | null;
    category_name: string;
    created_at: string;
    edit_url: string;
    is_trashed: boolean;
    actions: Record<string, unknown>[];
};

export type DesignBlockFormValues = {
    title: string;
    slug: string;
    description: string;
    html: string;
    css: string;
    scripts: string;
    preview_image_url: string;
    design_type: string;
    block_type: string;
    design_system: string;
    category_id: string;
    status: string;
};

export type DesignBlockFormOptions = {
    initialValues?: DesignBlockFormValues;
    statusOptions: CmsOption[];
    designTypeOptions: CmsOption[];
    blockTypeOptions: CmsOption[];
    categoryOptions: CmsOption[];
    designSystemOptions: CmsOption[];
    defaults: Record<string, string | number>;
};

export type DesignBlockEditDetail = {
    id: number;
    title: string;
    updated_at_formatted: string;
    updated_at_human: string | null;
};

export type DesignBlockIndexPageProps = ScaffoldIndexPageProps<DesignBlockListItem> & {
    designTypeOptions: CmsOption[];
    categoryOptions: CmsOption[];
    designSystemOptions: CmsOption[];
};

export type DesignBlockCreatePageProps = DesignBlockFormOptions;

export type DesignBlockEditPageProps = DesignBlockFormOptions & {
    designBlock: DesignBlockEditDetail;
};

// ================================================================
// Menus
// ================================================================

export type MenuListItem = {
    id: number;
    name: string;
    slug: string;
    location: string;
    location_label: string | null;
    description: string | null;
    is_active: boolean;
    is_active_label: string;
    is_active_class: string;
    items_count: number;
    items_count_label: string;
    items_count_class: string;
    items_preview: { id: number; title: string; type: string }[];
    updated_at: string;
    created_at: string;
    updated_at_for_humans: string | null;
    created_at_for_humans: string | null;
    edit_url: string;
    show_url: string;
    is_trashed: boolean;
    actions: Record<string, unknown>[];
};

export type MenuItem = {
    id: number;
    parent_id: number;
    title: string;
    url: string;
    type: string;
    target: string;
    icon: string;
    css_classes: string;
    link_title: string;
    link_rel: string;
    description: string;
    object_id: number | null;
    sort_order: number;
    is_active: boolean;
};

export type MenuDetail = {
    id: number;
    name: string;
    slug: string;
    location: string;
    description: string | null;
    is_active: boolean;
    all_items: MenuItem[];
};

export type LocationAssignment = {
    key: string;
    label: string;
    menu: MenuListItem | null;
    items_count: number;
    status: 'assigned' | 'unassigned';
};

export type MenuIndexPageProps = ScaffoldIndexPageProps<MenuListItem> & {
    menus: MenuListItem[];
    locations: Record<string, string>;
    locationAssignments: LocationAssignment[];
};

export type MenuCreatePageProps = {
    locations: Record<string, string>;
    assignedMenus: Record<string, MenuListItem>;
    statusOptions: CmsOption[];
    locationOptions: CmsOption[];
};

export type MenuEditPageProps = {
    menu: MenuDetail;
    pages: { id: number; title: string; slug: string }[];
    categories: { id: number; title: string; slug: string }[];
    tags: { id: number; title: string; slug: string }[];
    itemTypes: Record<string, string>;
    itemTargets: Record<string, string>;
    locations: Record<string, string>;
    menuSettings: Record<string, unknown>;
    statusOptions: CmsOption[];
    locationOptions: CmsOption[];
};

// ================================================================
// Widgets
// ================================================================

export type WidgetArea = {
    id: string;
    name: string;
    description: string;
};

export type WidgetSettingField = {
    type: 'text' | 'textarea' | 'select' | 'checkbox' | 'color' | 'url' | 'email' | string;
    label: string;
    default: string | boolean | number;
    description?: string;
    required?: boolean;
    options?: Record<string, string>;
};

export type AvailableWidget = {
    name: string;
    description: string;
    category: string;
    settings_schema: Record<string, WidgetSettingField>;
};

export type WidgetInstance = {
    id: string;
    type: string;
    title: string;
    settings: Record<string, string | boolean | number>;
    position: number;
};

export type WidgetIndexPageProps = {
    widgetAreas: WidgetArea[];
    currentWidgets: Record<string, WidgetInstance[]>;
    availableWidgets: Record<string, AvailableWidget>;
};

export type WidgetEditPageProps = {
    widgetArea: WidgetArea;
    currentWidgets: WidgetInstance[];
    availableWidgets: Record<string, AvailableWidget>;
};

// ================================================================
// Themes
// ================================================================

export type ThemeListItem = {
    directory: string;
    name: string;
    description: string;
    author: string;
    author_uri: string;
    version: string;
    screenshot: string | null;
    is_active: boolean;
    is_child: boolean;
    parent: string | null;
    has_children: boolean;
    child_count: number;
    is_protected: boolean;
    tags: string[];
    supports: string[];
};

export type ThemeIndexFilters = {
    search: string;
    filter: 'all' | 'active' | 'inactive' | 'supports' | string;
    supports: string[];
};

export type ThemeIndexStatistics = {
    total: number;
    active: number;
    inactive: number;
    child: number;
    protected: number;
};

export type ThemeIndexPageProps = {
    themes: ThemeListItem[];
    activeTheme: ThemeListItem | null;
    filters: ThemeIndexFilters;
    statistics: ThemeIndexStatistics;
    availableSupports: string[];
};

export type ThemeEditorThemeSummary = {
    directory: string;
    name: string;
    description: string;
    author: string;
    version: string;
    screenshot: string | null;
    is_active: boolean;
    parent: string | null;
};

export type ThemeEditorFileNode = {
    name: string;
    path: string;
    type: 'file' | 'directory';
    extension?: string;
    size?: number;
    editable?: boolean;
    protected?: boolean;
    inherited?: boolean;
    inheritedFrom?: string;
    override?: boolean;
    overrides?: string;
    children?: ThemeEditorFileNode[];
};

export type ThemeEditorPageProps = {
    theme: ThemeEditorThemeSummary;
    themeDirectory: string;
    files: ThemeEditorFileNode[];
    isChildTheme: boolean;
    parentTheme: ThemeEditorThemeSummary | null;
};
