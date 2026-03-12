import type { AuthenticatedSharedData } from '@/types/auth';
import type { PaginatedData } from '@/types/pagination';

export type MovieOption = {
    value: string;
    label: string;
};

export type MovieFilters = {
    search: string;
    status: string;
    rating: string;
    genre: string;
    featured: string;
    sort: string;
};

export type MovieFormOptions = {
    statusOptions: MovieOption[];
    ratingOptions: MovieOption[];
    languageOptions: MovieOption[];
    genreOptions: MovieOption[];
    formatOptions: MovieOption[];
    streamingOptions: MovieOption[];
    warningOptions: MovieOption[];
    sortOptions: MovieOption[];
    featuredOptions: MovieOption[];
};

export type MovieFormDefaults = {
    title: string;
    slug: string;
    tagline: string;
    synopsis: string;
    notes: string;
    director: string;
    studio: string;
    status: string;
    rating: string;
    language: string;
    release_date: string;
    release_time: string;
    runtime_minutes: string;
    budget: string;
    box_office: string;
    ticket_price: string;
    metascore: string;
    audience_score: string;
    imdb_rating: string;
    trailer_url: string;
    official_site: string;
    genres: string[];
    spoken_languages: string[];
    available_formats: string[];
    streaming_platforms: string[];
    content_warnings: string[];
    is_featured: boolean;
    is_now_showing: boolean;
    has_post_credit_scene: boolean;
    is_family_friendly: boolean;
    remove_poster: boolean;
    remove_backdrop: boolean;
};

export type MovieFormValues = MovieFormDefaults & {
    poster: File | null;
    backdrop: File | null;
};

export type MovieEditingTarget = {
    id: number;
    title: string;
    poster_url: string | null;
    backdrop_url: string | null;
};

export type MovieListItem = {
    id: number;
    title: string;
    slug: string;
    director: string;
    status: string;
    status_label: string;
    rating: string;
    release_date: string | null;
    runtime_label: string | null;
    imdb_rating: string | null;
    metascore: number | null;
    language: string | null;
    genres: string[];
    is_featured: boolean;
    is_now_showing: boolean;
    poster_url: string | null;
};

export type MovieDetail = {
    id: number;
    title: string;
    slug: string;
    tagline: string | null;
    synopsis: string;
    notes: string | null;
    director: string;
    studio: string | null;
    status: string;
    status_label: string;
    rating: string;
    release_date: string | null;
    release_date_label: string | null;
    release_time: string | null;
    runtime_minutes: number | null;
    runtime_label: string | null;
    language: string | null;
    language_label: string | null;
    budget: string | null;
    budget_formatted: string | null;
    box_office: string | null;
    box_office_formatted: string | null;
    ticket_price: string | null;
    ticket_price_formatted: string | null;
    metascore: number | null;
    audience_score: number | null;
    imdb_rating: string | null;
    trailer_url: string | null;
    official_site: string | null;
    genres: string[];
    spoken_languages: string[];
    available_formats: string[];
    streaming_platforms: string[];
    content_warnings: string[];
    is_featured: boolean;
    is_now_showing: boolean;
    has_post_credit_scene: boolean;
    is_family_friendly: boolean;
    poster_url: string | null;
    backdrop_url: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type MovieStats = {
    total: number;
    released: number;
    featured: number;
    now_showing: number;
    average_imdb: string;
};

export type MoviesIndexPageProps = AuthenticatedSharedData & {
    filters: MovieFilters;
    movies: PaginatedData<MovieListItem>;
    stats: MovieStats;
    options: MovieFormOptions;
};

export type MovieFormPageProps = AuthenticatedSharedData & {
    movie: MovieEditingTarget | null;
    initialValues: MovieFormDefaults;
    options: MovieFormOptions;
};

export type MovieShowPageProps = AuthenticatedSharedData & {
    movie: MovieDetail;
};
