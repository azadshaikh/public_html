<?php

namespace App\Http\Requests\Movies;

use App\Models\Movie;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

abstract class MovieFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $title = trim((string) $this->input('title', ''));
        $slug = trim((string) $this->input('slug', ''));

        $this->merge([
            'title' => $title,
            'slug' => $slug !== '' ? Str::slug($slug) : Str::slug($title),
            'tagline' => $this->nullableString('tagline'),
            'synopsis' => trim((string) $this->input('synopsis', '')),
            'notes' => $this->nullableString('notes'),
            'director' => trim((string) $this->input('director', '')),
            'studio' => $this->nullableString('studio'),
            'language' => $this->nullableString('language'),
            'release_date' => $this->nullableString('release_date'),
            'release_time' => $this->nullableString('release_time'),
            'runtime_minutes' => $this->nullableString('runtime_minutes'),
            'budget' => $this->nullableString('budget'),
            'box_office' => $this->nullableString('box_office'),
            'ticket_price' => $this->nullableString('ticket_price'),
            'metascore' => $this->nullableString('metascore'),
            'audience_score' => $this->nullableString('audience_score'),
            'imdb_rating' => $this->nullableString('imdb_rating'),
            'trailer_url' => $this->nullableString('trailer_url'),
            'official_site' => $this->nullableString('official_site'),
            'genres' => $this->sanitizeOptionArray('genres', array_keys(Movie::GENRES)),
            'spoken_languages' => $this->sanitizeOptionArray('spoken_languages', array_keys(Movie::LANGUAGES)),
            'available_formats' => $this->sanitizeOptionArray('available_formats', array_keys(Movie::FORMATS)),
            'streaming_platforms' => $this->sanitizeOptionArray('streaming_platforms', array_keys(Movie::STREAMING_PLATFORMS)),
            'content_warnings' => $this->sanitizeOptionArray('content_warnings', array_keys(Movie::CONTENT_WARNINGS)),
            'is_featured' => $this->boolean('is_featured'),
            'is_now_showing' => $this->boolean('is_now_showing'),
            'has_post_credit_scene' => $this->boolean('has_post_credit_scene'),
            'is_family_friendly' => $this->boolean('is_family_friendly'),
            'remove_poster' => $this->boolean('remove_poster'),
            'remove_backdrop' => $this->boolean('remove_backdrop'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique(Movie::class, 'slug')->ignore($this->movie()?->getKey()),
            ],
            'tagline' => ['nullable', 'string', 'max:255'],
            'synopsis' => ['required', 'string', 'min:40', 'max:6000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'director' => ['required', 'string', 'max:255'],
            'studio' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(Movie::STATUSES))],
            'rating' => ['required', Rule::in(array_keys(Movie::RATINGS))],
            'language' => ['required', Rule::in(array_keys(Movie::LANGUAGES))],
            'release_date' => ['nullable', 'date'],
            'release_time' => ['nullable', 'date_format:H:i'],
            'runtime_minutes' => ['nullable', 'integer', 'min:45', 'max:320'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'box_office' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'ticket_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'metascore' => ['nullable', 'integer', 'min:0', 'max:100'],
            'audience_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'imdb_rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'trailer_url' => ['nullable', 'url', 'max:255'],
            'official_site' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array', 'min:1', 'max:4'],
            'genres.*' => [Rule::in(array_keys(Movie::GENRES))],
            'spoken_languages' => ['required', 'array', 'min:1', 'max:4'],
            'spoken_languages.*' => [Rule::in(array_keys(Movie::LANGUAGES))],
            'available_formats' => ['nullable', 'array', 'max:4'],
            'available_formats.*' => [Rule::in(array_keys(Movie::FORMATS))],
            'streaming_platforms' => ['nullable', 'array', 'max:4'],
            'streaming_platforms.*' => [Rule::in(array_keys(Movie::STREAMING_PLATFORMS))],
            'content_warnings' => ['nullable', 'array', 'max:6'],
            'content_warnings.*' => [Rule::in(array_keys(Movie::CONTENT_WARNINGS))],
            'is_featured' => ['boolean'],
            'is_now_showing' => ['boolean'],
            'has_post_credit_scene' => ['boolean'],
            'is_family_friendly' => ['boolean'],
            'remove_poster' => ['boolean'],
            'remove_backdrop' => ['boolean'],
            'poster' => ['nullable', 'image', 'max:5120'],
            'backdrop' => ['nullable', 'image', 'max:6144'],
        ];
    }

    /**
     * Get the validated movie attributes excluding uploads.
     *
     * @return array<string, mixed>
     */
    public function movieAttributes(): array
    {
        return Arr::except($this->validated(), [
            'poster',
            'backdrop',
            'remove_poster',
            'remove_backdrop',
        ]);
    }

    /**
     * Get the uploaded poster file.
     */
    public function poster(): ?UploadedFile
    {
        $file = $this->file('poster');

        return $file instanceof UploadedFile ? $file : null;
    }

    /**
     * Get the uploaded backdrop file.
     */
    public function backdrop(): ?UploadedFile
    {
        $file = $this->file('backdrop');

        return $file instanceof UploadedFile ? $file : null;
    }

    /**
     * Determine if the poster should be removed.
     */
    public function shouldRemovePoster(): bool
    {
        return $this->boolean('remove_poster');
    }

    /**
     * Determine if the backdrop should be removed.
     */
    public function shouldRemoveBackdrop(): bool
    {
        return $this->boolean('remove_backdrop');
    }

    /**
     * Get the route-bound movie instance, if present.
     */
    protected function movie(): ?Movie
    {
        $movie = $this->route('movie');

        return $movie instanceof Movie ? $movie : null;
    }

    /**
     * Get a nullable trimmed string field.
     */
    protected function nullableString(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }

    /**
     * Sanitize a multi-select option array.
     *
     * @param  array<int, string>  $allowedValues
     * @return array<int, string>
     */
    protected function sanitizeOptionArray(string $key, array $allowedValues): array
    {
        $values = $this->input($key, []);

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $value): string => is_string($value) ? trim($value) : '',
                $values,
            ),
            static fn (string $value): bool => $value !== '' && in_array($value, $allowedValues, true),
        )));
    }
}
