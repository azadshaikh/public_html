<?php

namespace Tests\Feature\Demo;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MovieCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('demo.movies.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_and_filter_the_movies_index(): void
    {
        $user = User::factory()->create();

        Movie::factory()->create([
            'title' => 'Arc Light',
            'slug' => 'arc-light',
            'status' => 'released',
        ]);

        Movie::factory()->create([
            'title' => 'Rough Cut',
            'slug' => 'rough-cut',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->get(route('demo.movies.index', [
                'status' => 'released',
                'search' => 'Arc',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('demo/movies/index')
                ->where('filters.status', 'released')
                ->where('filters.search', 'Arc')
                ->has('movies.data', 1)
                ->where('movies.data.0.title', 'Arc Light'));
    }

    public function test_authenticated_users_can_create_a_movie_with_media_uploads(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('demo.movies.store'), [
            ...$this->validPayload(),
            'poster' => UploadedFile::fake()->image('poster.jpg', 800, 1200),
            'backdrop' => UploadedFile::fake()->image('backdrop.jpg', 1600, 900),
        ]);

        $movie = Movie::query()->firstWhere('slug', 'the-midnight-circuit');

        $this->assertNotNull($movie);

        $response->assertRedirect(route('demo.movies.show', $movie));
        $this->assertDatabaseHas('movies', [
            'title' => 'The Midnight Circuit',
            'slug' => 'the-midnight-circuit',
            'status' => 'released',
            'is_featured' => true,
        ]);
        $this->assertNotNull($movie->poster_path);
        $this->assertNotNull($movie->backdrop_path);
        Storage::disk('public')->assertExists($movie->poster_path);
        Storage::disk('public')->assertExists($movie->backdrop_path);
    }

    public function test_authenticated_users_can_update_a_movie_and_replace_media(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Storage::disk('public')->put('movies/posters/original-poster.jpg', 'poster');
        Storage::disk('public')->put('movies/backdrops/original-backdrop.jpg', 'backdrop');

        $movie = Movie::factory()->create([
            'slug' => 'original-cut',
            'poster_path' => 'movies/posters/original-poster.jpg',
            'backdrop_path' => 'movies/backdrops/original-backdrop.jpg',
        ]);

        $response = $this->actingAs($user)->patch(route('demo.movies.update', $movie), [
            ...$this->validPayload([
                'title' => 'The Midnight Circuit Returns',
                'slug' => 'the-midnight-circuit-returns',
                'remove_backdrop' => true,
                'is_featured' => false,
                'is_now_showing' => false,
            ]),
            'poster' => UploadedFile::fake()->image('replacement-poster.jpg', 800, 1200),
        ]);

        $movie->refresh();

        $response->assertRedirect(route('demo.movies.show', $movie));
        $this->assertSame('The Midnight Circuit Returns', $movie->title);
        $this->assertSame('the-midnight-circuit-returns', $movie->slug);
        $this->assertNull($movie->backdrop_path);
        $this->assertNotSame('movies/posters/original-poster.jpg', $movie->poster_path);
        Storage::disk('public')->assertMissing('movies/posters/original-poster.jpg');
        Storage::disk('public')->assertMissing('movies/backdrops/original-backdrop.jpg');
        Storage::disk('public')->assertExists($movie->poster_path);
    }

    public function test_authenticated_users_can_delete_a_movie_and_its_media(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Storage::disk('public')->put('movies/posters/delete-me.jpg', 'poster');
        Storage::disk('public')->put('movies/backdrops/delete-me.jpg', 'backdrop');

        $movie = Movie::factory()->create([
            'poster_path' => 'movies/posters/delete-me.jpg',
            'backdrop_path' => 'movies/backdrops/delete-me.jpg',
        ]);

        $this->actingAs($user)
            ->delete(route('demo.movies.destroy', $movie))
            ->assertRedirect(route('demo.movies.index'));

        $this->assertDatabaseMissing('movies', [
            'id' => $movie->id,
        ]);
        Storage::disk('public')->assertMissing('movies/posters/delete-me.jpg');
        Storage::disk('public')->assertMissing('movies/backdrops/delete-me.jpg');
    }

    public function test_movie_creation_requires_a_unique_slug(): void
    {
        $user = User::factory()->create();

        Movie::factory()->create([
            'slug' => 'the-midnight-circuit',
        ]);

        $this->actingAs($user)
            ->from(route('demo.movies.create'))
            ->post(route('demo.movies.store'), $this->validPayload())
            ->assertRedirect(route('demo.movies.create'))
            ->assertSessionHasErrors(['slug']);

        $this->assertSame(1, Movie::query()->count());
    }

    /**
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'title' => 'The Midnight Circuit',
            'slug' => 'the-midnight-circuit',
            'tagline' => 'A neon-soaked pursuit through a city wired for chaos.',
            'synopsis' => "A burned-out courier uncovers a conspiracy hidden inside the city's transit grid and has one night to deliver the evidence before the network collapses. The chase moves through rooftops, underground markets, and overloaded data tunnels as every faction in the metropolis turns against her.",
            'notes' => 'Use this record to validate long text, grouped checkboxes, switches, and media uploads.',
            'director' => 'Jordan Vega',
            'studio' => 'North Star Pictures',
            'status' => 'released',
            'rating' => 'PG-13',
            'language' => 'en',
            'release_date' => '2026-03-11',
            'release_time' => '19:30',
            'runtime_minutes' => '126',
            'budget' => '185000000',
            'box_office' => '462500000',
            'ticket_price' => '18.50',
            'metascore' => '81',
            'audience_score' => '92',
            'imdb_rating' => '8.4',
            'trailer_url' => 'https://www.youtube.com/watch?v=midnightdemo',
            'official_site' => 'https://midnight-circuit.example',
            'genres' => ['action', 'science-fiction', 'thriller'],
            'spoken_languages' => ['en', 'ja'],
            'available_formats' => ['imax', 'dolby-cinema', 'streaming'],
            'streaming_platforms' => ['netflix', 'max'],
            'content_warnings' => ['violence', 'strong-language'],
            'is_featured' => true,
            'is_now_showing' => true,
            'has_post_credit_scene' => true,
            'is_family_friendly' => false,
            'remove_poster' => false,
            'remove_backdrop' => false,
        ], $overrides);
    }
}
