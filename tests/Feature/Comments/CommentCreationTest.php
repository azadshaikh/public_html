<?php

declare(strict_types=1);

namespace Tests\Feature\Comments;

use App\Enums\Status;
use App\Models\Comments;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_away_from_comment_creation(): void
    {
        $subject = User::factory()->create();

        $this->post(route('app.comments.store'), [
            'body' => 'A guest comment',
            'commentable_type' => User::class,
            'commentable_id' => $subject->id,
        ])->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_create_comments(): void
    {
        $author = User::factory()->create([
            'first_name' => 'Comment',
            'last_name' => 'Author',
            'name' => 'Comment Author',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $subject = User::factory()->create([
            'name' => 'Comment Subject',
        ]);

        $this->actingAs($author)
            ->postJson(route('app.comments.store'), [
                'body' => 'A useful comment',
                'commentable_type' => User::class,
                'commentable_id' => $subject->id,
            ])
            ->assertOk()
            ->assertJsonPath('status', '1')
            ->assertJsonPath('comment.body', 'A useful comment')
            ->assertJsonPath('comment.user.id', $author->id)
            ->assertJsonPath('comment.user.name', 'Comment Author');

        $this->assertDatabaseHas('comments', [
            'body' => 'A useful comment',
            'commentable_type' => User::class,
            'commentable_id' => $subject->id,
            'user_id' => $author->id,
        ]);
    }

    public function test_comment_creation_requires_a_body(): void
    {
        $author = User::factory()->create([
            'first_name' => 'Comment',
            'last_name' => 'Validator',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $subject = User::factory()->create();

        $this->actingAs($author)
            ->postJson(route('app.comments.store'), [
                'body' => '',
                'commentable_type' => User::class,
                'commentable_id' => $subject->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body']);

        $this->assertSame(0, Comments::query()->count());
    }
}
