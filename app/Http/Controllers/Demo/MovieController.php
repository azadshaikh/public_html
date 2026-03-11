<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Movies\MovieFormRequest;
use App\Http\Requests\Movies\StoreMovieRequest;
use App\Http\Requests\Movies\UpdateMovieRequest;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MovieController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $movies = Movie::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query
                        ->where('title', 'ilike', "%{$filters['search']}%")
                        ->orWhere('slug', 'ilike', "%{$filters['search']}%")
                        ->orWhere('director', 'ilike', "%{$filters['search']}%")
                        ->orWhere('studio', 'ilike', "%{$filters['search']}%");
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['rating'] !== '', fn (Builder $query) => $query->where('rating', $filters['rating']))
            ->when($filters['genre'] !== '', fn (Builder $query) => $query->whereJsonContains('genres', $filters['genre']))
            ->when($filters['featured'] === 'featured', fn (Builder $query) => $query->where('is_featured', true))
            ->when($filters['featured'] === 'standard', fn (Builder $query) => $query->where('is_featured', false))
            ->tap(fn (Builder $query) => $this->applySort($query, $filters['sort']))
            ->paginate(8)
            ->withQueryString()
            ->through(fn (Movie $movie): array => $this->movieListItem($movie));

        return Inertia::render('demo/movies/index', [
            'filters' => $filters,
            'movies' => $movies,
            'stats' => $this->stats(),
            'options' => $this->formOptions(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('demo/movies/create', [
            'movie' => null,
            'initialValues' => Movie::defaultFormData(),
            'options' => $this->formOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMovieRequest $request): RedirectResponse
    {
        $movie = new Movie;

        $this->saveMovie($request, $movie);

        return to_route('demo.movies.show', $movie);
    }

    /**
     * Display the specified resource.
     */
    public function show(Movie $movie): Response
    {
        return Inertia::render('demo/movies/show', [
            'movie' => $this->movieDetail($movie),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Movie $movie): Response
    {
        return Inertia::render('demo/movies/edit', [
            'movie' => [
                'id' => $movie->id,
                'title' => $movie->title,
                'poster_url' => $this->mediaUrl($movie->poster_path),
                'backdrop_url' => $this->mediaUrl($movie->backdrop_path),
            ],
            'initialValues' => $this->movieFormData($movie),
            'options' => $this->formOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMovieRequest $request, Movie $movie): RedirectResponse
    {
        $this->saveMovie($request, $movie);

        return to_route('demo.movies.show', $movie);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Movie $movie): RedirectResponse
    {
        $this->deleteMedia($movie->poster_path);
        $this->deleteMedia($movie->backdrop_path);

        $movie->delete();

        return to_route('demo.movies.index');
    }

    /**
     * Save the incoming movie data and synchronize uploads.
     */
    protected function saveMovie(MovieFormRequest $request, Movie $movie): void
    {
        $movie->fill($request->movieAttributes());

        $this->syncUpload($movie, 'poster_path', $request->poster(), 'movies/posters', $request->shouldRemovePoster());
        $this->syncUpload($movie, 'backdrop_path', $request->backdrop(), 'movies/backdrops', $request->shouldRemoveBackdrop());

        $movie->save();
    }

    /**
     * Synchronize a single uploaded asset.
     */
    protected function syncUpload(
        Movie $movie,
        string $column,
        ?UploadedFile $file,
        string $directory,
        bool $shouldRemove,
    ): void {
        $existingPath = $movie->getAttribute($column);

        if ($shouldRemove && is_string($existingPath) && $existingPath !== '') {
            $this->deleteMedia($existingPath);
            $movie->setAttribute($column, null);
        }

        if (! $file instanceof UploadedFile) {
            return;
        }

        if (is_string($existingPath) && $existingPath !== '') {
            $this->deleteMedia($existingPath);
        }

        $movie->setAttribute($column, $file->store($directory, 'public'));
    }

    /**
     * Delete a stored media asset.
     */
    protected function deleteMedia(?string $path): void
    {
        if (is_string($path) && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Get the sanitized filters for the listing page.
     *
     * @return array<string, string>
     */
    protected function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => $this->sanitizeFilter((string) $request->query('status', ''), array_keys(Movie::STATUSES)),
            'rating' => $this->sanitizeFilter((string) $request->query('rating', ''), array_keys(Movie::RATINGS)),
            'genre' => $this->sanitizeFilter((string) $request->query('genre', ''), array_keys(Movie::GENRES)),
            'featured' => $this->sanitizeFilter((string) $request->query('featured', ''), ['featured', 'standard']),
            'sort' => $this->sanitizeFilter(
                (string) $request->query('sort', 'release_desc'),
                ['release_desc', 'release_asc', 'title_asc', 'runtime_desc', 'recently_added'],
                'release_desc',
            ),
        ];
    }

    /**
     * Apply the requested sort order.
     */
    protected function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'release_asc' => $query->orderBy('release_date')->orderBy('title'),
            'title_asc' => $query->orderBy('title'),
            'runtime_desc' => $query->orderByDesc('runtime_minutes')->orderBy('title'),
            'recently_added' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('release_date')->orderBy('title'),
        };
    }

    /**
     * Build the options needed by the index and form pages.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    protected function formOptions(): array
    {
        return [
            'statusOptions' => $this->optionList(Movie::STATUSES),
            'ratingOptions' => $this->optionList(Movie::RATINGS),
            'languageOptions' => $this->optionList(Movie::LANGUAGES),
            'genreOptions' => $this->optionList(Movie::GENRES),
            'formatOptions' => $this->optionList(Movie::FORMATS),
            'streamingOptions' => $this->optionList(Movie::STREAMING_PLATFORMS),
            'warningOptions' => $this->optionList(Movie::CONTENT_WARNINGS),
            'sortOptions' => [
                ['value' => 'release_desc', 'label' => 'Newest release'],
                ['value' => 'release_asc', 'label' => 'Oldest release'],
                ['value' => 'title_asc', 'label' => 'Title A-Z'],
                ['value' => 'runtime_desc', 'label' => 'Longest runtime'],
                ['value' => 'recently_added', 'label' => 'Recently added'],
            ],
            'featuredOptions' => [
                ['value' => '', 'label' => 'All titles'],
                ['value' => 'featured', 'label' => 'Featured only'],
                ['value' => 'standard', 'label' => 'Standard only'],
            ],
        ];
    }

    /**
     * Transform a movie for the listing table.
     *
     * @return array<string, mixed>
     */
    protected function movieListItem(Movie $movie): array
    {
        return [
            'id' => $movie->id,
            'title' => $movie->title,
            'slug' => $movie->slug,
            'director' => $movie->director,
            'status' => $movie->status,
            'status_label' => $this->labelFor(Movie::STATUSES, $movie->status),
            'rating' => $movie->rating,
            'release_date' => $movie->release_date?->toDateString(),
            'runtime_label' => $this->runtimeLabel($movie->runtime_minutes),
            'imdb_rating' => $movie->imdb_rating !== null ? number_format((float) $movie->imdb_rating, 1) : null,
            'metascore' => $movie->metascore,
            'language' => $movie->language !== null ? $this->labelFor(Movie::LANGUAGES, $movie->language) : null,
            'genres' => $this->labelsFor(Movie::GENRES, $movie->genres ?? []),
            'is_featured' => (bool) $movie->is_featured,
            'is_now_showing' => (bool) $movie->is_now_showing,
            'poster_url' => $this->mediaUrl($movie->poster_path),
        ];
    }

    /**
     * Transform a movie for the show page.
     *
     * @return array<string, mixed>
     */
    protected function movieDetail(Movie $movie): array
    {
        return [
            'id' => $movie->id,
            'title' => $movie->title,
            'slug' => $movie->slug,
            'tagline' => $movie->tagline,
            'synopsis' => $movie->synopsis,
            'notes' => $movie->notes,
            'director' => $movie->director,
            'studio' => $movie->studio,
            'status' => $movie->status,
            'status_label' => $this->labelFor(Movie::STATUSES, $movie->status),
            'rating' => $movie->rating,
            'release_date' => $movie->release_date?->toDateString(),
            'release_date_label' => $movie->release_date?->toFormattedDateString(),
            'release_time' => $this->formatReleaseTime($movie->release_time),
            'runtime_minutes' => $movie->runtime_minutes,
            'runtime_label' => $this->runtimeLabel($movie->runtime_minutes),
            'language' => $movie->language,
            'language_label' => $movie->language !== null ? $this->labelFor(Movie::LANGUAGES, $movie->language) : null,
            'budget' => $movie->budget,
            'budget_formatted' => $this->currency($movie->budget),
            'box_office' => $movie->box_office,
            'box_office_formatted' => $this->currency($movie->box_office),
            'ticket_price' => $movie->ticket_price,
            'ticket_price_formatted' => $this->currency($movie->ticket_price),
            'metascore' => $movie->metascore,
            'audience_score' => $movie->audience_score,
            'imdb_rating' => $movie->imdb_rating !== null ? number_format((float) $movie->imdb_rating, 1) : null,
            'trailer_url' => $movie->trailer_url,
            'official_site' => $movie->official_site,
            'genres' => $this->labelsFor(Movie::GENRES, $movie->genres ?? []),
            'spoken_languages' => $this->labelsFor(Movie::LANGUAGES, $movie->spoken_languages ?? []),
            'available_formats' => $this->labelsFor(Movie::FORMATS, $movie->available_formats ?? []),
            'streaming_platforms' => $this->labelsFor(Movie::STREAMING_PLATFORMS, $movie->streaming_platforms ?? []),
            'content_warnings' => $this->labelsFor(Movie::CONTENT_WARNINGS, $movie->content_warnings ?? []),
            'is_featured' => (bool) $movie->is_featured,
            'is_now_showing' => (bool) $movie->is_now_showing,
            'has_post_credit_scene' => (bool) $movie->has_post_credit_scene,
            'is_family_friendly' => (bool) $movie->is_family_friendly,
            'poster_url' => $this->mediaUrl($movie->poster_path),
            'backdrop_url' => $this->mediaUrl($movie->backdrop_path),
            'created_at' => $movie->created_at?->toDateTimeString(),
            'updated_at' => $movie->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * Transform a movie for the edit form.
     *
     * @return array<string, array<int, string>|bool|string|null>
     */
    protected function movieFormData(Movie $movie): array
    {
        return [
            'title' => $movie->title,
            'slug' => $movie->slug,
            'tagline' => $movie->tagline ?? '',
            'synopsis' => $movie->synopsis,
            'notes' => $movie->notes ?? '',
            'director' => $movie->director,
            'studio' => $movie->studio ?? '',
            'status' => $movie->status,
            'rating' => $movie->rating,
            'language' => $movie->language ?? 'en',
            'release_date' => $movie->release_date?->toDateString() ?? '',
            'release_time' => $this->formatReleaseTime($movie->release_time) ?? '',
            'runtime_minutes' => $movie->runtime_minutes !== null ? (string) $movie->runtime_minutes : '',
            'budget' => $movie->budget !== null ? number_format((float) $movie->budget, 2, '.', '') : '',
            'box_office' => $movie->box_office !== null ? number_format((float) $movie->box_office, 2, '.', '') : '',
            'ticket_price' => $movie->ticket_price !== null ? number_format((float) $movie->ticket_price, 2, '.', '') : '',
            'metascore' => $movie->metascore !== null ? (string) $movie->metascore : '',
            'audience_score' => $movie->audience_score !== null ? (string) $movie->audience_score : '',
            'imdb_rating' => $movie->imdb_rating !== null ? number_format((float) $movie->imdb_rating, 1, '.', '') : '',
            'trailer_url' => $movie->trailer_url ?? '',
            'official_site' => $movie->official_site ?? '',
            'genres' => $movie->genres ?? [],
            'spoken_languages' => $movie->spoken_languages ?? [],
            'available_formats' => $movie->available_formats ?? [],
            'streaming_platforms' => $movie->streaming_platforms ?? [],
            'content_warnings' => $movie->content_warnings ?? [],
            'is_featured' => (bool) $movie->is_featured,
            'is_now_showing' => (bool) $movie->is_now_showing,
            'has_post_credit_scene' => (bool) $movie->has_post_credit_scene,
            'is_family_friendly' => (bool) $movie->is_family_friendly,
            'remove_poster' => false,
            'remove_backdrop' => false,
        ];
    }

    /**
     * Get dashboard-style stats for the movie demo.
     *
     * @return array<string, int|string>
     */
    protected function stats(): array
    {
        return [
            'total' => Movie::query()->count(),
            'released' => Movie::query()->where('status', 'released')->count(),
            'featured' => Movie::query()->where('is_featured', true)->count(),
            'now_showing' => Movie::query()->where('is_now_showing', true)->count(),
            'average_imdb' => number_format((float) (Movie::query()->avg('imdb_rating') ?? 0), 1),
        ];
    }

    /**
     * Map an option dictionary to front-end friendly objects.
     *
     * @param  array<string, string>  $options
     * @return array<int, array<string, string>>
     */
    protected function optionList(array $options): array
    {
        $mapped = [];

        foreach ($options as $value => $label) {
            $mapped[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $mapped;
    }

    /**
     * Get a single label from an option map.
     */
    protected function labelFor(array $options, string $value): string
    {
        return $options[$value] ?? Str::headline($value);
    }

    /**
     * Get multiple labels from an option map.
     *
     * @param  array<string, string>  $options
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    protected function labelsFor(array $options, array $values): array
    {
        return array_values(array_map(
            fn (string $value): string => $this->labelFor($options, $value),
            $values,
        ));
    }

    /**
     * Get a public media URL.
     */
    protected function mediaUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Format a duration in minutes.
     */
    protected function runtimeLabel(?int $runtimeMinutes): ?string
    {
        if ($runtimeMinutes === null) {
            return null;
        }

        $hours = intdiv($runtimeMinutes, 60);
        $minutes = $runtimeMinutes % 60;

        if ($hours === 0) {
            return $minutes.'m';
        }

        return $hours.'h '.$minutes.'m';
    }

    /**
     * Format a time value to HH:MM.
     */
    protected function formatReleaseTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr($value, 0, 5);
    }

    /**
     * Format a currency value.
     */
    protected function currency(string|float|int|null $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return '$'.number_format((float) $amount, 2);
    }

    /**
     * Sanitize a query-string filter.
     *
     * @param  array<int, string>  $allowedValues
     */
    protected function sanitizeFilter(string $value, array $allowedValues, string $default = ''): string
    {
        return in_array($value, $allowedValues, true) ? $value : $default;
    }
}
