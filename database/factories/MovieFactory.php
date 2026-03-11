<?php

namespace Database\Factories;

use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @extends Factory<Movie>
 */
class MovieFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(fake()->numberBetween(2, 4), true));
        $releaseDate = fake()->dateTimeBetween('-8 years', '+18 months');
        $language = Arr::random(array_keys(Movie::LANGUAGES));

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(10, 999),
            'tagline' => fake()->optional()->sentence(),
            'synopsis' => fake()->paragraphs(fake()->numberBetween(2, 4), true),
            'notes' => fake()->optional()->paragraphs(2, true),
            'director' => fake()->name(),
            'studio' => fake()->company(),
            'status' => Arr::random(array_keys(Movie::STATUSES)),
            'rating' => Arr::random(array_keys(Movie::RATINGS)),
            'language' => $language,
            'release_date' => $releaseDate,
            'release_time' => fake()->time('H:i:s'),
            'runtime_minutes' => fake()->numberBetween(88, 182),
            'budget' => fake()->randomFloat(2, 10_000_000, 280_000_000),
            'box_office' => fake()->randomFloat(2, 25_000_000, 1_100_000_000),
            'ticket_price' => fake()->randomFloat(2, 8, 29),
            'metascore' => fake()->numberBetween(45, 98),
            'audience_score' => fake()->numberBetween(55, 99),
            'imdb_rating' => fake()->randomFloat(1, 5.8, 9.4),
            'trailer_url' => 'https://www.youtube.com/watch?v='.fake()->lexify('???????????'),
            'official_site' => fake()->url(),
            'genres' => fake()->randomElements(array_keys(Movie::GENRES), fake()->numberBetween(2, 4)),
            'spoken_languages' => fake()->randomElements(array_keys(Movie::LANGUAGES), fake()->numberBetween(1, 3)),
            'available_formats' => fake()->randomElements(array_keys(Movie::FORMATS), fake()->numberBetween(2, 4)),
            'streaming_platforms' => fake()->randomElements(array_keys(Movie::STREAMING_PLATFORMS), fake()->numberBetween(1, 3)),
            'content_warnings' => fake()->optional()->randomElements(array_keys(Movie::CONTENT_WARNINGS), fake()->numberBetween(0, 3)) ?: [],
            'is_featured' => fake()->boolean(35),
            'is_now_showing' => fake()->boolean(40),
            'has_post_credit_scene' => fake()->boolean(45),
            'is_family_friendly' => fake()->boolean(25),
            'poster_path' => null,
            'backdrop_path' => null,
        ];
    }
}
