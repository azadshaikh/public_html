<?php

namespace App\Models;

use Database\Factories\MovieFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $tagline
 * @property string $synopsis
 * @property string|null $notes
 * @property string $director
 * @property string|null $studio
 * @property string $status
 * @property string $rating
 * @property string|null $language
 * @property Carbon|null $release_date
 * @property string|null $release_time
 * @property int|null $runtime_minutes
 * @property string|null $budget
 * @property string|null $box_office
 * @property string|null $ticket_price
 * @property int|null $metascore
 * @property int|null $audience_score
 * @property string|null $imdb_rating
 * @property string|null $trailer_url
 * @property string|null $official_site
 * @property list<string>|null $genres
 * @property list<string>|null $spoken_languages
 * @property list<string>|null $available_formats
 * @property list<string>|null $streaming_platforms
 * @property list<string>|null $content_warnings
 * @property bool $is_featured
 * @property bool $is_now_showing
 * @property bool $has_post_credit_scene
 * @property bool $is_family_friendly
 * @property string|null $poster_path
 * @property string|null $backdrop_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Movie extends Model
{
    /** @use HasFactory<MovieFactory> */
    use HasFactory;

    public const STATUSES = [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'released' => 'Released',
        'archived' => 'Archived',
    ];

    public const RATINGS = [
        'G' => 'G',
        'PG' => 'PG',
        'PG-13' => 'PG-13',
        'R' => 'R',
        'NC-17' => 'NC-17',
        'Unrated' => 'Unrated',
    ];

    public const LANGUAGES = [
        'en' => 'English',
        'es' => 'Spanish',
        'fr' => 'French',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'hi' => 'Hindi',
    ];

    public const GENRES = [
        'action' => 'Action',
        'adventure' => 'Adventure',
        'animation' => 'Animation',
        'comedy' => 'Comedy',
        'crime' => 'Crime',
        'drama' => 'Drama',
        'fantasy' => 'Fantasy',
        'horror' => 'Horror',
        'romance' => 'Romance',
        'science-fiction' => 'Science fiction',
        'thriller' => 'Thriller',
    ];

    public const FORMATS = [
        'imax' => 'IMAX',
        'dolby-cinema' => 'Dolby Cinema',
        'three-d' => '3D',
        'four-dx' => '4DX',
        'streaming' => 'Streaming',
        'blu-ray' => 'Blu-ray',
    ];

    public const STREAMING_PLATFORMS = [
        'netflix' => 'Netflix',
        'prime-video' => 'Prime Video',
        'disney-plus' => 'Disney+',
        'max' => 'Max',
        'apple-tv-plus' => 'Apple TV+',
        'hulu' => 'Hulu',
    ];

    public const CONTENT_WARNINGS = [
        'violence' => 'Violence',
        'gore' => 'Gore',
        'strong-language' => 'Strong language',
        'jump-scares' => 'Jump scares',
        'smoking' => 'Smoking',
        'nudity' => 'Nudity',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'tagline',
        'synopsis',
        'notes',
        'director',
        'studio',
        'status',
        'rating',
        'language',
        'release_date',
        'release_time',
        'runtime_minutes',
        'budget',
        'box_office',
        'ticket_price',
        'metascore',
        'audience_score',
        'imdb_rating',
        'trailer_url',
        'official_site',
        'genres',
        'spoken_languages',
        'available_formats',
        'streaming_platforms',
        'content_warnings',
        'is_featured',
        'is_now_showing',
        'has_post_credit_scene',
        'is_family_friendly',
        'poster_path',
        'backdrop_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'genres' => 'array',
            'spoken_languages' => 'array',
            'available_formats' => 'array',
            'streaming_platforms' => 'array',
            'content_warnings' => 'array',
            'is_featured' => 'boolean',
            'is_now_showing' => 'boolean',
            'has_post_credit_scene' => 'boolean',
            'is_family_friendly' => 'boolean',
            'budget' => 'decimal:2',
            'box_office' => 'decimal:2',
            'ticket_price' => 'decimal:2',
            'imdb_rating' => 'decimal:1',
        ];
    }

    /**
     * Get empty form defaults for movie forms.
     *
     * @return array<string, array<int, string>|bool|string>
     */
    public static function defaultFormData(): array
    {
        return [
            'title' => '',
            'slug' => '',
            'tagline' => '',
            'synopsis' => '',
            'notes' => '',
            'director' => '',
            'studio' => '',
            'status' => 'draft',
            'rating' => 'PG-13',
            'language' => 'en',
            'release_date' => '',
            'release_time' => '',
            'runtime_minutes' => '',
            'budget' => '',
            'box_office' => '',
            'ticket_price' => '',
            'metascore' => '',
            'audience_score' => '',
            'imdb_rating' => '',
            'trailer_url' => '',
            'official_site' => '',
            'genres' => [],
            'spoken_languages' => [],
            'available_formats' => [],
            'streaming_platforms' => [],
            'content_warnings' => [],
            'is_featured' => false,
            'is_now_showing' => false,
            'has_post_credit_scene' => false,
            'is_family_friendly' => false,
            'remove_poster' => false,
            'remove_backdrop' => false,
        ];
    }
}
