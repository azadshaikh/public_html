<?php

declare(strict_types=1);

namespace Tests\Feature\Revisions;

use App\Enums\Status;
use App\Models\Revision;
use App\Models\RevisionData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RevisionActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->create([
            'first_name' => 'Revision',
            'last_name' => 'Manager',
            'name' => 'Revision Manager',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    public function test_revision_show_returns_normalized_revision_fields(): void
    {
        $subject = User::factory()->create([
            'name' => 'Updated Name',
            'status' => Status::ACTIVE,
        ]);

        $revision = Revision::query()->create([
            'revisionable_type' => $subject->getMorphClass(),
            'revisionable_id' => (string) $subject->getKey(),
            'created_by' => $this->actor->id,
        ]);

        RevisionData::query()->forceCreate([
            'revision_id' => $revision->id,
            'field_key' => 'name',
            'old_value' => 'Original Name',
            'new_value' => 'Updated Name',
            'created_by' => $this->actor->id,
        ]);

        RevisionData::query()->forceCreate([
            'revision_id' => $revision->id,
            'field_key' => 'notification_preferences',
            'old_value' => '{"email":false}',
            'new_value' => '{"email":true}',
            'created_by' => $this->actor->id,
        ]);

        $this->actingAs($this->actor)
            ->postJson(route('app.revisions.show'), [
                'revision_id' => $revision->id,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('revision.id', $revision->id)
            ->assertJsonPath('fields.0.field', 'name')
            ->assertJsonPath('fields.0.old', 'Original Name')
            ->assertJsonPath('fields.0.new', 'Updated Name')
            ->assertJsonPath('fields.1.language', 'json');
    }

    public function test_revision_restore_restores_previous_values(): void
    {
        $subject = User::factory()->create([
            'name' => 'Updated Name',
            'status' => Status::ACTIVE,
        ]);

        $revision = Revision::query()->create([
            'revisionable_type' => $subject->getMorphClass(),
            'revisionable_id' => (string) $subject->getKey(),
            'created_by' => $this->actor->id,
        ]);

        RevisionData::query()->forceCreate([
            'revision_id' => $revision->id,
            'field_key' => 'name',
            'old_value' => 'Original Name',
            'new_value' => 'Updated Name',
            'created_by' => $this->actor->id,
        ]);

        Artisan::shouldReceive('call')
            ->once()
            ->with('astero:recache')
            ->andReturn(0);

        $this->actingAs($this->actor)
            ->postJson(route('app.revisions.restore', $revision->id))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('model_type', 'User')
            ->assertJsonPath('model_id', $subject->id);

        $subject->refresh();

        $this->assertSame('Original Name', $subject->name);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'Revision',
            'event' => 'restore',
        ]);
    }
}
