<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Enums\NoteType;
use App\Enums\NoteVisibility;
use App\Enums\Status;
use App\Models\Note;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $this->admin->assignRole(Role::findByName('administrator', 'web'));
    }

    // =========================================================================
    // INDEX — Authentication & Authorization
    // =========================================================================

    public function test_guests_cannot_access_users_index(): void
    {
        $this->get(route('app.users.index'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_access_users_index(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Regular',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($user)
            ->get(route('app.users.index'))
            ->assertForbidden();
    }

    public function test_user_with_view_users_permission_can_access_index(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Viewer',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $user->givePermissionTo('view_users');

        $this->actingAs($user)
            ->get(route('app.users.index'))
            ->assertOk();
    }

    // =========================================================================
    // INDEX — Inertia Props
    // =========================================================================

    public function test_admin_can_access_users_index(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('users/index')
                ->has('config.columns', 8)
                ->where('config.columns.1.width', '250px')
                ->has('users')
                ->has('users.data')
                ->has('statistics')
                ->has('filters')
                ->has('roles')
                ->has('showPendingTab')
                ->has('registrationSettings')
            );
    }

    public function test_users_index_returns_paginated_users(): void
    {
        User::factory()->count(5)->create(['status' => Status::ACTIVE]);

        $this->actingAs($this->admin)
            ->get(route('app.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('users/index')
                ->has('users.data', 6) // 5 created + 1 admin
            );
    }

    public function test_users_index_returns_correct_statistics(): void
    {
        User::factory()->create(['status' => Status::ACTIVE]);
        User::factory()->create(['status' => Status::PENDING]);
        User::factory()->create(['status' => Status::SUSPENDED]);
        User::factory()->create(['status' => Status::BANNED]);

        $trashed = User::factory()->create(['status' => Status::ACTIVE]);
        $trashed->delete();

        $this->actingAs($this->admin)
            ->get(route('app.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('statistics.total', 5) // 4 + admin (trash excluded from total)
                ->where('statistics.active', 2) // 1 active + admin
                ->where('statistics.pending', 1)
                ->where('statistics.suspended', 1)
                ->where('statistics.banned', 1)
                ->where('statistics.trash', 1)
            );
    }

    public function test_users_index_returns_user_resource_shape(): void
    {
        User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('users.data.0', fn (Assert $user): Assert => $user
                    ->has('id')
                    ->has('name')
                    ->has('email')
                    ->has('status')
                    ->has('status_label')
                    ->has('email_verified')
                    ->has('roles')
                    ->has('created_at')
                    ->has('show_url')
                    ->has('actions')
                    ->etc()
                )
            );
    }

    public function test_administrator_can_start_impersonation_via_post_request(): void
    {
        $targetUser = User::factory()->create([
            'first_name' => 'Target',
            'last_name' => 'User',
            'email_verified_at' => now(),
            'status' => Status::ACTIVE,
        ]);
        $targetUser->assignRole(Role::findByName('user', 'web'));

        $this->actingAs($this->admin)
            ->post(route('app.users.impersonate', $targetUser))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($targetUser);
        $this->assertSame($this->admin->id, session('impersonator_id'));
    }

    public function test_inertia_impersonation_request_forces_a_full_browser_reload(): void
    {
        $targetUser = User::factory()->create([
            'first_name' => 'Target',
            'last_name' => 'User',
            'email_verified_at' => now(),
            'status' => Status::ACTIVE,
        ]);
        $targetUser->assignRole(Role::findByName('user', 'web'));

        $this->actingAs($this->admin)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('app.users.impersonate', $targetUser))
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('dashboard'));

        $this->assertAuthenticatedAs($targetUser);
        $this->assertSame($this->admin->id, session('impersonator_id'));
    }

    public function test_impersonated_sessions_share_impersonation_state_with_inertia(): void
    {
        $impersonator = User::factory()->create([
            'first_name' => 'Original',
            'last_name' => 'Admin',
            'email_verified_at' => now(),
            'status' => Status::ACTIVE,
        ]);
        $impersonator->assignRole(Role::findByName('administrator', 'web'));

        $impersonatedUser = User::factory()->create([
            'first_name' => 'Target',
            'last_name' => 'Admin',
            'email_verified_at' => now(),
            'status' => Status::ACTIVE,
        ]);
        $impersonatedUser->assignRole(Role::findByName('administrator', 'web'));

        $this->withSession(['impersonator_id' => $impersonator->id])
            ->actingAs($impersonatedUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('auth.impersonation.active', true)
                ->where('auth.impersonation.impersonator.id', $impersonator->id)
                ->where('auth.impersonation.impersonator.name', $impersonator->name)
                ->where('auth.impersonation.impersonator.email', $impersonator->email)
                ->where('auth.impersonation.stopUrl', route('app.users.stop-impersonating'))
            );
    }

    public function test_impersonation_can_be_stopped_via_post_request(): void
    {
        $targetUser = User::factory()->create([
            'first_name' => 'Target',
            'last_name' => 'User',
            'email_verified_at' => now(),
            'status' => Status::ACTIVE,
        ]);
        $targetUser->assignRole(Role::findByName('user', 'web'));

        $this->actingAs($this->admin)
            ->post(route('app.users.impersonate', $targetUser));

        $this->post(route('app.users.stop-impersonating'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_stopping_impersonation_via_inertia_forces_a_full_browser_reload(): void
    {
        $targetUser = User::factory()->create([
            'first_name' => 'Target',
            'last_name' => 'User',
            'email_verified_at' => now(),
            'status' => Status::ACTIVE,
        ]);
        $targetUser->assignRole(Role::findByName('user', 'web'));

        $this->actingAs($this->admin)
            ->post(route('app.users.impersonate', $targetUser));

        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->post(route('app.users.stop-impersonating'))
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('dashboard'));

        $this->assertAuthenticatedAs($this->admin);
        $this->assertNull(session('impersonator_id'));
    }

    // =========================================================================
    // INDEX — Filters
    // =========================================================================

    public function test_users_index_filters_by_status_tab(): void
    {
        User::factory()->create(['status' => Status::SUSPENDED]);

        $this->actingAs($this->admin)
            ->get(route('app.users.index', ['status' => 'suspended']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('users.data', 1)
                ->where('filters.status', 'suspended')
            );
    }

    public function test_users_index_filters_by_search(): void
    {
        User::factory()->create([
            'name' => 'FindMe Uniquely',
            'first_name' => 'FindMe',
            'last_name' => 'Uniquely',
            'status' => Status::ACTIVE,
        ]);
        User::factory()->create([
            'name' => 'Other Person',
            'first_name' => 'Other',
            'last_name' => 'Person',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.users.index', ['search' => 'FindMe']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('users.data', 1)
                ->where('filters.search', 'FindMe')
            );
    }

    public function test_users_index_filters_by_role(): void
    {
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);
        $userWithRole = User::factory()->create(['status' => Status::ACTIVE]);
        $userWithRole->assignRole($role);

        User::factory()->create(['status' => Status::ACTIVE]);

        $this->actingAs($this->admin)
            ->get(route('app.users.index', ['role_id' => $role->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('users.data', 1)
                ->where('filters.role_id', (string) $role->id)
            );
    }

    public function test_users_index_filters_by_email_verified(): void
    {
        User::factory()->unverified()->create(['status' => Status::ACTIVE]);

        $this->actingAs($this->admin)
            ->get(route('app.users.index', ['email_verified' => 'unverified']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('filters.email_verified', 'unverified')
            );
    }

    public function test_users_index_filters_by_date_range(): void
    {
        User::factory()->create([
            'status' => Status::ACTIVE,
            'created_at' => now()->subYear(),
        ]);
        User::factory()->create([
            'status' => Status::ACTIVE,
            'created_at' => now()->subDays(5),
        ]);

        $from = now()->subMonth()->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $this->actingAs($this->admin)
            ->get(route('app.users.index', ['created_at' => "{$from},{$to}"]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('filters.created_at', "{$from},{$to}")
            );
    }

    public function test_users_index_preserves_filter_state_in_response(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.users.index', [
                'search' => 'test',
                'sort' => 'name',
                'direction' => 'asc',
                'per_page' => 25,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('filters.search', 'test')
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc')
                ->where('filters.per_page', 25)
            );
    }

    public function test_users_index_keeps_stable_default_order_after_status_updates(): void
    {
        $sharedCreatedAt = now()->addDay()->startOfMinute();

        $earlierUser = User::factory()->create([
            'first_name' => 'Earlier',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
            'created_at' => $sharedCreatedAt,
            'updated_at' => $sharedCreatedAt,
        ]);

        $laterUser = User::factory()->create([
            'first_name' => 'Later',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
            'created_at' => $sharedCreatedAt,
            'updated_at' => $sharedCreatedAt,
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.data.0.id', $laterUser->id)
                ->where('users.data.1.id', $earlierUser->id)
            );

        $this->actingAs($this->admin)
            ->patch(route('app.users.suspend', $laterUser))
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get(route('app.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('users.data.0.id', $laterUser->id)
                ->where('users.data.1.id', $earlierUser->id)
                ->where('users.data.0.status', Status::SUSPENDED->value)
            );
    }

    // =========================================================================
    // SHOW
    // =========================================================================

    public function test_guests_cannot_access_users_show(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);

        $this->get(route('app.users.show', $user))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_view_user_show_page(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.users.show', $user))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('users/show')
                ->has('user.id')
                ->has('user.email')
                ->has('user.status')
                ->has('user.actions')
                ->has('userActivities')
            );
    }

    public function test_admin_can_view_trashed_user(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $user->delete();

        $this->actingAs($this->admin)
            ->get(route('app.users.show', $user))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('users/show')
                ->has('user')
            );
    }

    public function test_user_show_page_includes_note_props(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);

        Note::query()->create([
            'noteable_type' => User::class,
            'noteable_id' => $user->id,
            'content' => 'Follow up on profile completion.',
            'type' => NoteType::Note,
            'visibility' => NoteVisibility::Team,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.users.show', $user))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('users/show')
                ->has('notes', 1)
                ->where('notes.0.content', 'Follow up on profile completion.')
                ->where('notes.0.visibility.value', NoteVisibility::Team->value)
                ->where('noteTarget.type', User::class)
                ->where('noteTarget.id', $user->id)
                ->has('noteVisibilityOptions', 3)
            );
    }

    public function test_private_notes_are_hidden_from_other_users_on_the_user_show_page(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $viewer = User::factory()->create([
            'first_name' => 'Viewer',
            'last_name' => 'Admin',
            'status' => Status::ACTIVE,
        ]);
        $viewer->assignRole(Role::findByName('administrator', 'web'));

        Note::query()->create([
            'noteable_type' => User::class,
            'noteable_id' => $user->id,
            'content' => 'Private onboarding reminder.',
            'type' => NoteType::Note,
            'visibility' => NoteVisibility::Private,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Note::query()->create([
            'noteable_type' => User::class,
            'noteable_id' => $user->id,
            'content' => 'Shared team note.',
            'type' => NoteType::Note,
            'visibility' => NoteVisibility::Team,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('app.users.show', $user))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('users/show')
                ->has('notes', 1)
                ->where('notes.0.content', 'Shared team note.')
            );
    }

    public function test_admin_can_add_a_note_to_a_user_via_the_notes_endpoint(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);

        $this->actingAs($this->admin)
            ->postJson(route('app.notes.store'), [
                'content' => 'Reach out about completing setup.',
                'noteable_type' => User::class,
                'noteable_id' => $user->id,
                'visibility' => NoteVisibility::Team->value,
            ])
            ->assertOk()
            ->assertJson([
                'status' => 1,
                'message' => 'Note added successfully.',
            ])
            ->assertJsonPath('notes.0.content', 'Reach out about completing setup.');

        $this->assertDatabaseHas('notes', [
            'noteable_type' => User::class,
            'noteable_id' => $user->id,
            'content' => 'Reach out about completing setup.',
            'visibility' => NoteVisibility::Team->value,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_update_a_user_note_via_the_notes_endpoint(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $note = Note::query()->create([
            'noteable_type' => User::class,
            'noteable_id' => $user->id,
            'content' => 'Initial note content.',
            'type' => NoteType::Note,
            'visibility' => NoteVisibility::Team,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->putJson(route('app.notes.update', $note), [
                'content' => 'Updated note content.',
                'visibility' => NoteVisibility::Private->value,
            ])
            ->assertOk()
            ->assertJson([
                'status' => 1,
                'message' => 'Note updated successfully.',
            ])
            ->assertJsonPath('note.id', $note->id)
            ->assertJsonPath('note.content', 'Updated note content.')
            ->assertJsonPath('note.visibility.value', NoteVisibility::Private->value);

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'content' => 'Updated note content.',
            'visibility' => NoteVisibility::Private->value,
            'updated_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_toggle_pin_for_a_user_note_via_the_notes_endpoint(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $note = Note::query()->create([
            'noteable_type' => User::class,
            'noteable_id' => $user->id,
            'content' => 'Important onboarding detail.',
            'type' => NoteType::Note,
            'visibility' => NoteVisibility::Team,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('app.notes.toggle-pin', $note))
            ->assertOk()
            ->assertJson([
                'status' => 1,
                'message' => 'Note pinned successfully.',
            ])
            ->assertJsonPath('note.id', $note->id)
            ->assertJsonPath('note.is_pinned', true);

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'is_pinned' => true,
            'pinned_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_delete_a_user_note_via_the_notes_endpoint(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $note = Note::query()->create([
            'noteable_type' => User::class,
            'noteable_id' => $user->id,
            'content' => 'Temporary follow-up note.',
            'type' => NoteType::Note,
            'visibility' => NoteVisibility::Team,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson(route('app.notes.destroy', $note))
            ->assertOk()
            ->assertJson([
                'status' => 1,
                'message' => 'Note deleted successfully.',
            ])
            ->assertJsonPath('notes', []);

        $this->assertSoftDeleted('notes', [
            'id' => $note->id,
            'deleted_by' => $this->admin->id,
        ]);
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    public function test_admin_can_access_create_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.users.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('users/create')
                ->has('initialValues')
                ->has('availableRoles')
                ->has('statusOptions')
                ->has('genderOptions')
                ->where('initialValues.name', '')
                ->where('initialValues.first_name', '')
                ->where('initialValues.last_name', '')
                ->where('initialValues.email', '')
                ->where('initialValues.username', '')
                ->where('initialValues.status', 'active')
                ->where('initialValues.roles', [])
                ->where('initialValues.password', '')
            );
    }

    public function test_guests_cannot_access_create_page(): void
    {
        $this->get(route('app.users.create'))
            ->assertRedirect(route('login'));
    }

    // =========================================================================
    // EDIT
    // =========================================================================

    public function test_admin_can_access_edit_page(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.users.edit', $user))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('users/edit')
                ->has('initialValues')
                ->has('availableRoles')
                ->has('statusOptions')
                ->has('user')
            );
    }

    // =========================================================================
    // DESTROY
    // =========================================================================

    public function test_admin_can_soft_delete_user(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);

        $this->actingAs($this->admin)
            ->delete(route('app.users.destroy', $user))
            ->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_super_user_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('app.users.destroy', 1))
            ->assertRedirect()
            ->assertSessionHas('error', 'Cannot delete the super user account.');
    }

    // =========================================================================
    // STATUS ACTIONS
    // =========================================================================

    public function test_admin_can_suspend_user(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);

        $this->actingAs($this->admin)
            ->patch(route('app.users.suspend', $user))
            ->assertRedirect();

        $user->refresh();
        $this->assertEquals(Status::SUSPENDED, $user->status);
    }

    public function test_admin_can_ban_user(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);

        $this->actingAs($this->admin)
            ->patch(route('app.users.ban', $user))
            ->assertRedirect();

        $user->refresh();
        $this->assertEquals(Status::BANNED, $user->status);
    }

    public function test_admin_can_unban_user(): void
    {
        $user = User::factory()->create(['status' => Status::BANNED]);

        $this->actingAs($this->admin)
            ->patch(route('app.users.unban', $user))
            ->assertRedirect();

        $user->refresh();
        $this->assertEquals(Status::ACTIVE, $user->status);
    }

    public function test_admin_can_restore_trashed_user(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $user->delete();

        $this->actingAs($this->admin)
            ->patch(route('app.users.restore', $user))
            ->assertRedirect();

        $user->refresh();
        $this->assertNull($user->deleted_at);
    }

    // =========================================================================
    // BULK ACTIONS
    // =========================================================================

    public function test_admin_can_bulk_delete_users(): void
    {
        $user1 = User::factory()->create(['status' => Status::ACTIVE]);
        $user2 = User::factory()->create(['status' => Status::ACTIVE]);

        $this->actingAs($this->admin)
            ->post(route('app.users.bulk-action'), [
                'action' => 'delete',
                'ids' => [$user1->id, $user2->id],
            ])
            ->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $user1->id]);
        $this->assertSoftDeleted('users', ['id' => $user2->id]);
    }

    public function test_user_with_restore_permission_can_bulk_restore_users_without_delete_permission(): void
    {
        $trashedUser = User::factory()->create(['status' => Status::ACTIVE]);
        $trashedUser->delete();

        $restorer = User::factory()->create([
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
            'first_name' => 'Restore',
        ]);
        $restorer->givePermissionTo(['view_users', 'restore_users']);

        $this->actingAs($restorer)
            ->post(route('app.users.bulk-action'), [
                'action' => 'restore',
                'ids' => [$trashedUser->id],
                'status' => 'trash',
            ])
            ->assertRedirect();

        $trashedUser->refresh();
        $this->assertNull($trashedUser->deleted_at);
    }

    public function test_user_with_edit_permission_can_bulk_suspend_users_without_delete_permission(): void
    {
        $targetUser = User::factory()->create(['status' => Status::ACTIVE]);

        $editor = User::factory()->create([
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
            'first_name' => 'Editor',
        ]);
        $editor->givePermissionTo(['view_users', 'edit_users']);

        $this->actingAs($editor)
            ->post(route('app.users.bulk-action'), [
                'action' => 'suspend',
                'ids' => [$targetUser->id],
            ])
            ->assertRedirect();

        $targetUser->refresh();
        $this->assertEquals(Status::SUSPENDED, $targetUser->status);
    }

    public function test_admin_can_bulk_suspend_users(): void
    {
        $user1 = User::factory()->create(['status' => Status::ACTIVE]);
        $user2 = User::factory()->create(['status' => Status::ACTIVE]);

        $this->actingAs($this->admin)
            ->post(route('app.users.bulk-action'), [
                'action' => 'suspend',
                'ids' => [$user1->id, $user2->id],
            ])
            ->assertRedirect();

        $user1->refresh();
        $user2->refresh();
        $this->assertEquals(Status::SUSPENDED, $user1->status);
        $this->assertEquals(Status::SUSPENDED, $user2->status);
    }

    public function test_admin_can_bulk_ban_users(): void
    {
        $user1 = User::factory()->create(['status' => Status::ACTIVE]);
        $user2 = User::factory()->create(['status' => Status::ACTIVE]);

        $this->actingAs($this->admin)
            ->post(route('app.users.bulk-action'), [
                'action' => 'ban',
                'ids' => [$user1->id, $user2->id],
            ])
            ->assertRedirect();

        $user1->refresh();
        $user2->refresh();
        $this->assertEquals(Status::BANNED, $user1->status);
        $this->assertEquals(Status::BANNED, $user2->status);
    }

    public function test_admin_can_bulk_unban_users(): void
    {
        $user1 = User::factory()->create(['status' => Status::BANNED]);
        $user2 = User::factory()->create(['status' => Status::BANNED]);

        $this->actingAs($this->admin)
            ->post(route('app.users.bulk-action'), [
                'action' => 'unban',
                'ids' => [$user1->id, $user2->id],
            ])
            ->assertRedirect();

        $user1->refresh();
        $user2->refresh();
        $this->assertEquals(Status::ACTIVE, $user1->status);
        $this->assertEquals(Status::ACTIVE, $user2->status);
    }

    public function test_bulk_action_protects_super_user_from_delete(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.users.bulk-action'), [
                'action' => 'delete',
                'ids' => [1],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_bulk_action_protects_super_user_from_ban(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.users.bulk-action'), [
                'action' => 'ban',
                'ids' => [1],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_bulk_action_validates_action_input(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.users.bulk-action'), [
                'action' => 'invalid_action',
                'ids' => [1],
            ])
            ->assertSessionHasErrors('action');
    }

    public function test_bulk_action_validates_ids_required(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.users.bulk-action'), [
                'action' => 'delete',
            ])
            ->assertSessionHasErrors('ids');
    }

    // =========================================================================
    // VERIFY EMAIL
    // =========================================================================

    public function test_admin_can_verify_user_email(): void
    {
        $user = User::factory()->unverified()->create(['status' => Status::ACTIVE]);
        $this->assertNull($user->email_verified_at);

        $this->actingAs($this->admin)
            ->post(route('app.users.verify-email', $user))
            ->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }
}
